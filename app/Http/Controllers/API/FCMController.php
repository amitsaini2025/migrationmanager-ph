<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FCMController extends Controller
{
    /**
     * Register or update FCM device token
     * 
     * POST /api/fcm/register-token
     * 
     * Request body:
     * {
     *   "token": "fcm_token",
     *   "client_id": "user_id" (optional, defaults to authenticated user)
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerToken(Request $request)
    {
        try {
            // Validate the request - only token and client_id required
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|max:500',
                'client_id' => 'required|integer|exists:admins,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get authenticated user
            $authenticatedUser = $request->user();
            
            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // client_id is required
            $targetUserId = $request->input('client_id');
            
            // Verify the target user exists
            $targetUser = Admin::find($targetUserId);
            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target user not found'
                ], 404);
            }
            
            $deviceToken = $request->input('token');
            
            // Set default values for optional fields
            $deviceName = 'Unknown Device';
            $deviceType = null;
            $appVersion = null;
            $osVersion = null;

            // Check if device token already exists
            $existingToken = DeviceToken::where('device_token', $deviceToken)->first();
            
            if ($existingToken) {
                // Update existing token
                $existingToken->update([
                    'user_id' => $targetUserId,
                    'user_type' => DeviceToken::USER_TYPE_ADMIN,
                    'device_name' => $deviceName,
                    'device_type' => $deviceType,
                    'app_version' => $appVersion,
                    'os_version' => $osVersion,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                Log::info('FCM device token updated', [
                    'user_id' => $targetUserId,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token updated successfully',
                    'data' => [
                        'id' => $existingToken->id,
                        'user_id' => $existingToken->user_id,
                        'is_active' => $existingToken->is_active,
                        'updated_at' => $existingToken->updated_at->toISOString(),
                    ]
                ], 200);
            } else {
                // Create new device token
                $newToken = DeviceToken::create([
                    'user_id' => $targetUserId,
                    'user_type' => DeviceToken::USER_TYPE_ADMIN,
                    'device_token' => $deviceToken,
                    'device_name' => $deviceName,
                    'device_type' => $deviceType,
                    'app_version' => $appVersion,
                    'os_version' => $osVersion,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                Log::info('FCM device token registered', [
                    'user_id' => $targetUserId,
                    'device_token' => substr($deviceToken, 0, 20) . '...',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token registered successfully',
                    'data' => [
                        'id' => $newToken->id,
                        'user_id' => $newToken->user_id,
                        'is_active' => $newToken->is_active,
                        'created_at' => $newToken->created_at->toISOString(),
                    ]
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Failed to register FCM device token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'token' => $request->input('token') ? substr($request->input('token'), 0, 20) . '...' : null,
                    'client_id' => $request->input('client_id'),
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register device token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unregister FCM device token
     * 
     * POST /api/fcm/unregister-token
     * 
     * Request body:
     * {
     *   "token": "fcm_token"
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unregisterToken(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get authenticated user
            $authenticatedUser = $request->user();
            
            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $deviceToken = $request->input('token');

            // Find the device token
            $token = DeviceToken::where('device_token', $deviceToken)
                ->where('user_id', $authenticatedUser->id)
                ->first();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device token not found'
                ], 404);
            }

            // Deactivate the token instead of deleting it (for audit purposes)
            $token->update([
                'is_active' => false,
            ]);

            Log::info('FCM device token unregistered', [
                'user_id' => $authenticatedUser->id,
                'device_token' => substr($deviceToken, 0, 20) . '...',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token unregistered successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to unregister FCM device token', [
                'error' => $e->getMessage(),
                'request_data' => [
                    'token' => $request->input('token') ? substr($request->input('token'), 0, 20) . '...' : null,
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unregister device token',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test FCM notification sending
     * 
     * POST /api/fcm/test
     * 
     * Request body:
     * {
     *   "user_id": "user_id" (optional, defaults to authenticated user),
     *   "title": "Test Notification" (optional),
     *   "body": "This is a test notification" (optional)
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testNotification(Request $request)
    {
        try {
            // Get authenticated user
            $authenticatedUser = $request->user();
            
            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Determine target user
            $targetUserId = $request->input('user_id', $authenticatedUser->id);
            
            // Validate that user has permission
            if ($targetUserId != $authenticatedUser->id && $authenticatedUser->role != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to send test notifications for this user'
                ], 403);
            }

            // Check if service account is configured
            $serviceAccountPath = config('services.fcm.service_account_path');
            if (!$serviceAccountPath) {
                return response()->json([
                    'success' => false,
                    'message' => 'FCM service account not configured. Please set FCM_SERVICE_ACCOUNT_PATH in .env',
                    'config_status' => 'missing'
                ], 500);
            }

            $fullPath = storage_path('app/' . ltrim($serviceAccountPath, '/'));
            if (!file_exists($fullPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'FCM service account file not found',
                    'expected_path' => $fullPath,
                    'config_status' => 'file_not_found'
                ], 500);
            }

            // Check if user has device tokens
            $deviceTokens = \App\Models\DeviceToken::active()
                ->forUser($targetUserId)
                ->forUserType(\App\Models\DeviceToken::USER_TYPE_ADMIN)
                ->pluck('device_token')
                ->toArray();

            if (empty($deviceTokens)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active device tokens found for this user. Please register a device token first.',
                    'user_id' => $targetUserId,
                    'device_tokens_count' => 0,
                    'config_status' => 'no_tokens'
                ], 404);
            }

            // Send test notification
            $fcmService = new \App\Services\FCMService();
            $title = $request->input('title', 'Test Notification');
            $body = $request->input('body', 'This is a test notification from the FCM integration');
            
            $data = [
                'type' => 'test_notification',
                'timestamp' => now()->toISOString(),
                'test' => 'true'
            ];

            // Check if service account is loaded
            $reflection = new \ReflectionClass($fcmService);
            $serviceAccountDataProperty = $reflection->getProperty('serviceAccountData');
            $serviceAccountDataProperty->setAccessible(true);
            $serviceAccountData = $serviceAccountDataProperty->getValue($fcmService);
            
            $projectIdProperty = $reflection->getProperty('projectId');
            $projectIdProperty->setAccessible(true);
            $projectId = $projectIdProperty->getValue($fcmService);

            if (!$serviceAccountData) {
                Log::error('FCM service account data not loaded', [
                    'user_id' => $targetUserId,
                    'service_account_path' => $serviceAccountPath
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'FCM service account data not loaded. Check if the JSON file is valid.',
                    'user_id' => $targetUserId,
                    'device_tokens_count' => count($deviceTokens),
                    'config_status' => 'invalid_json'
                ], 500);
            }

            if (!$projectId) {
                Log::error('FCM project ID not found in service account', [
                    'user_id' => $targetUserId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'FCM project ID not found in service account file.',
                    'user_id' => $targetUserId,
                    'device_tokens_count' => count($deviceTokens),
                    'config_status' => 'missing_project_id'
                ], 500);
            }

            $result = $fcmService->sendToUser($targetUserId, $title, $body, $data);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test notification sent successfully',
                    'data' => [
                        'user_id' => $targetUserId,
                        'device_tokens_count' => count($deviceTokens),
                        'title' => $title,
                        'body' => $body,
                        'sent_at' => now()->toISOString()
                    ],
                    'config_status' => 'working'
                ], 200);
            } else {
                // Get more detailed error information
                Log::error('FCM test notification send failed', [
                    'user_id' => $targetUserId,
                    'device_tokens_count' => count($deviceTokens),
                    'project_id' => $projectId,
                    'service_account_loaded' => !empty($serviceAccountData)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send test notification. Check logs for details. Possible issues: Access token generation failed, invalid device tokens, or FCM API error.',
                    'user_id' => $targetUserId,
                    'device_tokens_count' => count($deviceTokens),
                    'project_id' => $projectId,
                    'config_status' => 'send_failed',
                    'hint' => 'Check Laravel logs for detailed error messages. Common issues: Invalid service account credentials, network connectivity, or invalid device tokens.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('FCM test notification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->input('user_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending test notification',
                'error' => $e->getMessage(),
                'config_status' => 'error'
            ], 500);
        }
    }

    /**
     * Send FCM notification message
     * 
     * POST /api/fcm/send-message
     * 
     * Request body:
     * {
     *   "message": {
     *     "token": "fcm_token",
     *     "data": {
     *       "title": "Test Title",
     *       "body": "Test notification",
     *       "type": "chat",
     *       "userId": "123"
     *     }
     *   }
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'message' => 'required|array',
                'message.token' => 'required|string|max:500',
                'message.data' => 'required|array',
                'message.data.title' => 'required|string|max:255',
                'message.data.body' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get authenticated user
            $authenticatedUser = $request->user();
            
            if (!$authenticatedUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $messageData = $request->input('message');
            $deviceToken = $messageData['token'];
            $data = $messageData['data'];
            
            $title = $data['title'] ?? 'Notification';
            $body = $data['body'] ?? '';
            
            // Check if the device token is registered and active
            $token = DeviceToken::where('device_token', $deviceToken)->first();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device token is not registered. Please register the token first.',
                    'error' => 'Token not found in database'
                ], 404);
            }
            
            if (!$token->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device token is not active. The token has been unregistered.',
                    'error' => 'Token is inactive',
                    'token_status' => 'unregistered'
                ], 400);
            }
            
            // Remove title and body from data payload (they go in notification)
            $notificationData = $data;
            unset($notificationData['title'], $notificationData['body']);

            // Send notification using FCMService
            $fcmService = new \App\Services\FCMService();
            
            // Use sendToSingleDevice method via reflection or create a public method
            // For now, we'll send to the specific token
            $result = $fcmService->sendToMultipleDevices([$deviceToken], $title, $body, $notificationData);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'data' => [
                        'token' => substr($deviceToken, 0, 20) . '...',
                        'sent_at' => now()->toISOString()
                    ]
                ], 200);
            } else {
                Log::error('FCM send message failed', [
                    'token' => substr($deviceToken, 0, 20) . '...',
                    'title' => $title
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification. Check logs for details.',
                    'hint' => 'Possible issues: Invalid token, SenderId mismatch, or FCM API error.'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('FCM send message error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error sending notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

