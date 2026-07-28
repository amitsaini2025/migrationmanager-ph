<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;

class FCMService
{
    private $serviceAccountPath;
    private $serviceAccountData;
    private $projectId;
    private $accessToken;
    private $accessTokenExpiry;

    public function __construct()
    {
        $this->serviceAccountPath = config('services.fcm.service_account_path');
        $this->loadServiceAccount();
    }

    /**
     * Load service account JSON file
     */
    private function loadServiceAccount()
    {
        if (!$this->serviceAccountPath) {
            Log::error('FCM service account path not configured');
            return;
        }

        $fullPath = storage_path('app/' . ltrim($this->serviceAccountPath, '/'));
        
        if (!file_exists($fullPath)) {
            Log::error('FCM service account file not found', ['path' => $fullPath]);
            return;
        }

        $jsonContent = file_get_contents($fullPath);
        $this->serviceAccountData = json_decode($jsonContent, true);

        if (!$this->serviceAccountData) {
            Log::error('Invalid FCM service account JSON');
            return;
        }

        $this->projectId = $this->serviceAccountData['project_id'] ?? null;
        
        // Log service account loading for debugging
        if ($this->projectId) {
            Log::info('FCM service account loaded successfully', [
                'project_id' => $this->projectId,
                'client_email' => $this->serviceAccountData['client_email'] ?? 'N/A',
                'service_account_path' => $this->serviceAccountPath
            ]);
        } else {
            Log::error('FCM service account loaded but project_id is missing', [
                'service_account_path' => $this->serviceAccountPath,
                'keys_in_json' => array_keys($this->serviceAccountData ?? [])
            ]);
        }
    }

    /**
     * Get access token for FCM API
     */
    private function getAccessToken()
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->accessTokenExpiry && time() < $this->accessTokenExpiry) {
            return $this->accessToken;
        }

        if (!$this->serviceAccountData) {
            Log::error('Service account data not loaded');
            return null;
        }

        try {
            // Create JWT for service account
            $now = time();
            $jwt = [
                'iss' => $this->serviceAccountData['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ];

            // Sign JWT with private key
            $privateKey = $this->serviceAccountData['private_key'];
            $token = JWT::encode($jwt, $privateKey, 'RS256');

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $token,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to get FCM access token', [
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                return null;
            }

            $tokenData = $response->json();
            $this->accessToken = $tokenData['access_token'] ?? null;
            $this->accessTokenExpiry = $now + ($tokenData['expires_in'] ?? 3600) - 60; // 60 seconds buffer

            return $this->accessToken;
        } catch (\Exception $e) {
            Log::error('Error getting FCM access token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser($userId, $title, $body, $data = [], string $userType = DeviceToken::USER_TYPE_ADMIN)
    {
        $deviceTokens = DeviceToken::active()
            ->forUser($userId)
            ->forUserType($userType)
            ->pluck('device_token')
            ->toArray();

        if (empty($deviceTokens)) {
            Log::warning('No active device tokens found for user', [
                'user_id' => $userId,
                'project_id' => $this->projectId
            ]);
            return false;
        }

        $result = $this->sendToMultipleDevices($deviceTokens, $title, $body, $data);
        
        // Log summary if there were issues
        if (!$result) {
            Log::warning('FCM notification send failed for user', [
                'user_id' => $userId,
                'token_count' => count($deviceTokens),
                'project_id' => $this->projectId,
                'hint' => 'Check logs above for specific token errors. If UNREGISTERED, verify mobile app uses Firebase project: ' . $this->projectId
            ]);
        }
        
        return $result;
    }

    /**
     * Send notification to multiple devices
     * Note: FCM v1 API requires individual requests per token
     */
    public function sendToMultipleDevices($deviceTokens, $title, $body, $data = [])
    {
        if (empty($deviceTokens)) {
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('FCM access token not available', [
                'project_id' => $this->projectId,
                'service_account_loaded' => !empty($this->serviceAccountData)
            ]);
            return false;
        }
        
        if (!$this->projectId) {
            Log::error('FCM project ID not available', [
                'service_account_loaded' => !empty($this->serviceAccountData)
            ]);
            return false;
        }

        $successCount = 0;
        $failedTokens = [];

        // FCM v1 API requires individual requests per token
        foreach ($deviceTokens as $token) {
            $result = $this->sendToSingleDevice($token, $title, $body, $data, $accessToken);
            if ($result['success']) {
                $successCount++;
                // Reset failure count on successful send
                $cacheKey = 'fcm_failure_count_' . md5($token);
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                
                // Update last_used_at timestamp
                DeviceToken::where('device_token', $token)
                    ->update(['last_used_at' => now()]);
            } else {
                $failedTokens[] = [
                    'token' => $token,
                    'error' => $result['error'] ?? 'Unknown error',
                    'code' => $result['code'] ?? null,
                    'fcm_error_code' => $result['fcm_error_code'] ?? null,
                    'status_code' => $result['status_code'] ?? null
                ];
            }
        }

        // Handle failed tokens
        if (!empty($failedTokens)) {
            $this->handleFailedTokensV1($failedTokens);
        }

        return $successCount > 0;
    }

    /**
     * Send notification to a single device using FCM v1 API
     */
    private function sendToSingleDevice($deviceToken, $title, $body, $data = [], $accessToken = null)
    {
        if (!$accessToken) {
            $accessToken = $this->getAccessToken();
        }

        if (!$accessToken || !$this->projectId) {
            return ['success' => false, 'error' => 'Access token not available'];
        }

        // Prepare data payload - convert all values to strings (FCM requirement)
        $dataPayload = [];
        foreach ($data as $key => $value) {
            $dataPayload[$key] = (string) $value;
        }

        // FCM v1 API payload structure
        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $dataPayload,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'default',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            // Log the request details for debugging
            Log::debug('Sending FCM notification', [
                'project_id' => $this->projectId,
                'url' => $url,
                'token_preview' => substr($deviceToken, 0, 20) . '...',
                'has_access_token' => !empty($accessToken)
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                return ['success' => true];
            }

            $responseData = $response->json();
            $errorMessage = $responseData['error']['message'] ?? $response->body();
            $errorCode = $responseData['error']['code'] ?? null;
            
            // Extract FCM-specific error code from details (e.g., "UNREGISTERED", "INVALID_ARGUMENT")
            $fcmErrorCode = null;
            if (isset($responseData['error']['details']) && is_array($responseData['error']['details'])) {
                foreach ($responseData['error']['details'] as $detail) {
                    if (isset($detail['errorCode'])) {
                        $fcmErrorCode = $detail['errorCode'];
                        break;
                    }
                }
            }
            
            // Enhanced error logging with project mismatch detection
            $logContext = [
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'fcm_error_code' => $fcmErrorCode,
                'status_code' => $response->status(),
                'project_id' => $this->projectId,
                'token' => substr($deviceToken, 0, 20) . '...'
            ];
            
            // Detect project mismatch (UNREGISTERED usually means token from different project)
            if ($fcmErrorCode === 'UNREGISTERED') {
                $logContext['issue'] = 'PROJECT_MISMATCH';
                $logContext['message'] = 'Token appears to be from a different Firebase project. Verify mobile app uses project_id: ' . $this->projectId;
                $logContext['solution'] = 'Check mobile app google-services.json (Android) or GoogleService-Info.plist (iOS) matches project: ' . $this->projectId;
                
                Log::error('FCM v1 API request failed - Project Mismatch Detected', $logContext);
            } else {
                Log::error('FCM v1 API request failed', $logContext);
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'code' => $errorCode,
                'fcm_error_code' => $fcmErrorCode,
                'status_code' => $response->status(),
                'project_id' => $this->projectId,
                'project_mismatch' => $fcmErrorCode === 'UNREGISTERED'
            ];
        } catch (\Exception $e) {
            Log::error('FCM v1 API request failed', [
                'error' => $e->getMessage(),
                'token' => substr($deviceToken, 0, 20) . '...'
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle failed device tokens for v1 API
     * Only deactivates tokens when FCM explicitly indicates they are invalid
     * AND after multiple consecutive failures (to avoid deactivating valid tokens due to config issues)
     */
    private function handleFailedTokensV1($failedTokens)
    {
        foreach ($failedTokens as $failedToken) {
            $deviceToken = $failedToken['token'];
            $error = $failedToken['error'] ?? '';
            $errorCode = $failedToken['code'] ?? null;
            $fcmErrorCode = $failedToken['fcm_error_code'] ?? null;
            $statusCode = $failedToken['status_code'] ?? null;
            
            // Get the device token record to check its status
            $tokenRecord = DeviceToken::where('device_token', $deviceToken)->first();
            
            if (!$tokenRecord) {
                Log::warning('Device token not found in database', [
                    'device_token' => substr($deviceToken, 0, 20) . '...'
                ]);
                continue;
            }
            
            // Check if token was recently registered/updated (grace period: 2 hours)
            // This prevents deactivating tokens that might have config issues but are actually valid
            $gracePeriodMinutes = 120; // 2 hours
            $recentlyUpdated = $tokenRecord->updated_at && 
                             $tokenRecord->updated_at->diffInMinutes(now()) < $gracePeriodMinutes;
            
            // Track consecutive failures using cache (expires after 24 hours)
            $cacheKey = 'fcm_failure_count_' . md5($deviceToken);
            $failureCount = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
            $failureCount++;
            
            // Only deactivate tokens when FCM explicitly indicates they are invalid
            // FCM error codes that indicate invalid tokens:
            // - UNREGISTERED: Token is no longer valid (app uninstalled, token expired)
            // - INVALID_ARGUMENT: Token format is invalid
            $isInvalidTokenError = false;
            
            // Primary check: FCM-specific error code (most reliable indicator)
            if ($fcmErrorCode) {
                $fcmErrorCodeUpper = strtoupper($fcmErrorCode);
                if (in_array($fcmErrorCodeUpper, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                    $isInvalidTokenError = true;
                }
            }
            // Secondary check: HTTP 400 with specific error message patterns (for cases where FCM error code is missing)
            elseif ($statusCode == 400) {
                $errorLower = strtolower($error);
                if (
                    str_contains($errorLower, 'not a valid fcm registration token') ||
                    str_contains($errorLower, 'invalid_argument') ||
                    str_contains($errorLower, 'invalid registration token')
                ) {
                    $isInvalidTokenError = true;
                }
            }
            
            // Store failure count in cache (expires in 24 hours)
            \Illuminate\Support\Facades\Cache::put($cacheKey, $failureCount, now()->addHours(24));
            
            // Only deactivate if:
            // 1. FCM explicitly indicates invalid token error
            // 2. Token was NOT recently updated (outside grace period)
            // 3. We have multiple consecutive failures (at least 3) OR it's been failing for a while
            $shouldDeactivate = false;
            $deactivationReason = '';
            
            if ($isInvalidTokenError) {
                // Special handling for UNREGISTERED (project mismatch)
                if ($fcmErrorCode === 'UNREGISTERED') {
                    if ($recentlyUpdated) {
                        // Token was recently updated - likely project mismatch, not deactivating
                        Log::warning('FCM reports UNREGISTERED token - Project Mismatch (Grace Period Active)', [
                            'device_token' => substr($deviceToken, 0, 20) . '...',
                            'error' => $error,
                            'fcm_error_code' => $fcmErrorCode,
                            'status_code' => $statusCode,
                            'failure_count' => $failureCount,
                            'project_id' => $this->projectId,
                            'updated_at' => $tokenRecord->updated_at,
                            'minutes_since_update' => $tokenRecord->updated_at->diffInMinutes(now()),
                            'issue' => 'Token likely from different Firebase project',
                            'solution' => 'Verify mobile app uses Firebase project: ' . $this->projectId,
                            'reason' => 'Token recently updated - may be project configuration mismatch'
                        ]);
                    } elseif ($failureCount >= 3) {
                        // Multiple consecutive UNREGISTERED failures - likely project mismatch
                        $shouldDeactivate = true;
                        $deactivationReason = "FCM reports UNREGISTERED token after {$failureCount} consecutive failures - likely project mismatch";
                        
                        Log::error('Deactivating token due to persistent UNREGISTERED errors - Project Mismatch', [
                            'device_token' => substr($deviceToken, 0, 20) . '...',
                            'failure_count' => $failureCount,
                            'project_id' => $this->projectId,
                            'issue' => 'Token does not belong to Firebase project: ' . $this->projectId,
                            'solution' => 'User needs to re-register token from mobile app configured with correct Firebase project'
                        ]);
                    } else {
                        // First or second UNREGISTERED failure - likely project mismatch
                        Log::warning('FCM reports UNREGISTERED token - Possible Project Mismatch', [
                            'device_token' => substr($deviceToken, 0, 20) . '...',
                            'error' => $error,
                            'fcm_error_code' => $fcmErrorCode,
                            'status_code' => $statusCode,
                            'failure_count' => $failureCount,
                            'project_id' => $this->projectId,
                            'issue' => 'Token likely from different Firebase project',
                            'solution' => 'Verify mobile app google-services.json matches project: ' . $this->projectId,
                            'reason' => "Waiting for {$failureCount}/3 failures before deactivation"
                        ]);
                    }
                } elseif ($recentlyUpdated) {
                    // Token was recently updated - don't deactivate yet, might be config issue
                    Log::warning('FCM reports invalid token but token was recently updated - not deactivating (grace period)', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'error' => $error,
                        'fcm_error_code' => $fcmErrorCode,
                        'status_code' => $statusCode,
                        'failure_count' => $failureCount,
                        'updated_at' => $tokenRecord->updated_at,
                        'minutes_since_update' => $tokenRecord->updated_at->diffInMinutes(now()),
                        'reason' => 'Token recently updated - may be configuration issue'
                    ]);
                } elseif ($failureCount >= 3) {
                    // Multiple consecutive failures - likely truly invalid
                    $shouldDeactivate = true;
                    $deactivationReason = "FCM reports invalid token after {$failureCount} consecutive failures";
                } else {
                    // First or second failure - wait for more attempts
                    Log::warning('FCM reports invalid token but waiting for more failures before deactivating', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'error' => $error,
                        'fcm_error_code' => $fcmErrorCode,
                        'status_code' => $statusCode,
                        'failure_count' => $failureCount,
                        'reason' => "Waiting for {$failureCount}/3 failures before deactivation"
                    ]);
                }
            } else {
                // Not an invalid token error - reset failure count on next success
                // For now, just log it
                Log::warning('FCM request failed but not an invalid token error - may be temporary issue', [
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                    'error' => $error,
                    'error_code' => $errorCode,
                    'fcm_error_code' => $fcmErrorCode,
                    'status_code' => $statusCode,
                    'failure_count' => $failureCount,
                    'reason' => 'No explicit invalid token indication from FCM'
                ]);
            }
            
            if ($shouldDeactivate) {
                $updated = DeviceToken::where('device_token', $deviceToken)
                    ->update(['is_active' => false]);
                
                if ($updated) {
                    // Clear failure count cache after deactivation
                    \Illuminate\Support\Facades\Cache::forget($cacheKey);
                    
                    Log::info('Deactivated invalid device token (v1 API)', [
                        'device_token' => substr($deviceToken, 0, 20) . '...',
                        'error' => $error,
                        'error_code' => $errorCode,
                        'fcm_error_code' => $fcmErrorCode,
                        'status_code' => $statusCode,
                        'failure_count' => $failureCount,
                        'reason' => $deactivationReason
                    ]);
                }
            }
        }
    }

    /**
     * Send appointment reminder
     */
    public function sendAppointmentReminder($userId, $appointmentData)
    {
        $title = 'Appointment Reminder';
        $body = "You have an appointment scheduled for {$appointmentData['date']} at {$appointmentData['time']}";
        
        $data = [
            'type' => 'appointment_reminder',
            'appointment_id' => $appointmentData['id'],
            'action' => 'view_appointment'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send document approval notification
     */
    public function sendDocumentApproval($userId, $documentData)
    {
        $title = 'Document Approved';
        $body = "Your document '{$documentData['title']}' has been approved";
        
        $data = [
            'type' => 'document_approval',
            'document_id' => $documentData['id'],
            'action' => 'view_document'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send case status update
     */
    public function sendCaseStatusUpdate($userId, $caseData)
    {
        $title = 'Case Status Update';
        $body = "Your case '{$caseData['title']}' status has been updated to {$caseData['status']}";
        
        $data = [
            'type' => 'case_status_update',
            'case_id' => $caseData['id'],
            'action' => 'view_case'
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }
}
