<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientEmail;
use App\Models\Staff;
use App\Models\DeviceToken;
use App\Models\RefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ClientPortalController extends Controller
{
    
    /**
     * Login
     * POST /api/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
            'device_token' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $admin = Admin::whereRaw('LOWER(email) = ?', [$email])
                     ->whereIn('type', ['client', 'lead'])
                     ->whereIn('cp_status', $this->allowedClientPortalStatuses())
                     ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Create Sanctum token with device information
        $deviceName = $request->device_name ?? 'client-portal-app';
        $token = $admin->createToken($deviceName)->plainTextToken;

        // Handle device token for push notifications
        if ($request->device_token) {
            $this->handleDeviceToken($admin->id, $request->device_token, $deviceName, DeviceToken::USER_TYPE_ADMIN);
        }

        // Generate refresh token using DB query
        try {
            $refreshTokenValue = Str::random(64);
            $expiresAt = Carbon::now()->addDays(30);
            
            // Prepare insert data
            $insertData = [
                'user_id' => $admin->id,
                'user_type' => RefreshToken::USER_TYPE_ADMIN,
                'token' => $refreshTokenValue,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'is_revoked' => 0,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
            
            // Log the data being inserted for debugging
            Log::info('Attempting to insert refresh token', [
                'user_id' => $admin->id,
                'user_type' => RefreshToken::USER_TYPE_ADMIN,
                'data' => array_merge($insertData, ['token' => substr($refreshTokenValue, 0, 10) . '...'])
            ]);
            
            $refreshTokenId = DB::table('refresh_tokens')->insertGetId($insertData);
            
        } catch (\Illuminate\Database\QueryException $e) {
            $errorDetails = $this->handleRefreshTokenError($e, $admin->id, $insertData ?? [], $refreshTokenValue ?? '');
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete login. Please try again.',
                'error' => 'Token generation failed',
                'problematic_field' => $errorDetails['field'],
                'error_details' => config('app.debug') ? $errorDetails : null
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate refresh token during login (non-database error)', [
                'user_id' => $admin->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete login. Please try again.',
                'error' => 'Token generation failed',
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

        // Update last login timestamp (if you have this field)
        $admin->touch();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'refresh_token' => $refreshTokenValue,
                'client' => [
                    'id' => $admin->id,
                    'name' => $admin->first_name . ' ' . $admin->last_name,
                    'email' => $admin->email,
                    'client_id' => $admin->client_id,
                    'cp_status' => (int) ($admin->cp_status ?? 0),
                    'cp_status_text' => $this->getCpStatusText((int) ($admin->cp_status ?? 0)),
                ]
            ]
        ], 200);
    }

    /**
     * Admin Login
     * POST /api/admin-login
     * 
     * Login for staff users with roles 1, 12, 13, 16 (Staff model)
     */
    public function adminLogin(Request $request)
    {   
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
            'device_token' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $staff = Staff::where('email', $request->email)
                     ->whereIn('role', [1, 12, 13, 16]) // Admin roles
                     ->where('status', 1) // Active status
                     ->first(); 

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Create Sanctum token with device information
        $deviceName = $request->device_name ?? 'admin-portal-app';
        $token = $staff->createToken($deviceName)->plainTextToken;

        // Handle device token for push notifications
        if ($request->device_token) {
            $this->handleDeviceToken($staff->id, $request->device_token, $deviceName, DeviceToken::USER_TYPE_STAFF);
        }

        // Generate refresh token using DB query
        try {
            $refreshTokenValue = Str::random(64);
            $expiresAt = Carbon::now()->addDays(30);
            
            // Prepare insert data (staff.id — not an admins FK)
            $insertData = [
                'user_id' => $staff->id,
                'user_type' => RefreshToken::USER_TYPE_STAFF,
                'token' => $refreshTokenValue,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'is_revoked' => 0,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
            
            $refreshTokenId = DB::table('refresh_tokens')->insertGetId($insertData);
            
        } catch (\Illuminate\Database\QueryException $e) {
            $errorDetails = $this->handleRefreshTokenError($e, $staff->id, $insertData ?? [], $refreshTokenValue ?? '');
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete login. Please try again.',
                'error' => 'Token generation failed',
                'problematic_field' => $errorDetails['field'],
                'error_details' => config('app.debug') ? $errorDetails : null
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate refresh token during admin login (non-database error)', [
                'user_id' => $staff->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete login. Please try again.',
                'error' => 'Token generation failed',
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

        // Update last login timestamp
        $staff->touch();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'refresh_token' => $refreshTokenValue,
                'user' => [
                    'id' => $staff->id,
                    'name' => $staff->first_name . ' ' . $staff->last_name,
                    'email' => $staff->email,
                    'role' => $staff->role
                ]
            ]
        ], 200);
    }

    /**
     * Logout
     * POST /api/logout
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $userType = $this->tokenUserTypeFor($user);
            
            // Delete current access token
            $user->currentAccessToken()->delete();

            // Revoke all refresh tokens for this user using DB query
            DB::table('refresh_tokens')
                ->where('user_id', $user->id)
                ->where('user_type', $userType)
                ->update(['is_revoked' => 1, 'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

            // Optionally deactivate device token (but keep it for potential re-login)
            // DeviceToken::where('user_id', $user->id)->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout from all devices
     * POST /api/logout-all
     */
    public function logoutAll(Request $request)
    {
        try {
            $user = $request->user();
            $userType = $this->tokenUserTypeFor($user);
            
            // Delete all access tokens for the user
            $user->tokens()->delete();

            // Revoke all refresh tokens for the user using DB query
            DB::table('refresh_tokens')
                ->where('user_id', $user->id)
                ->where('user_type', $userType)
                ->update(['is_revoked' => 1, 'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);

            // Deactivate all device tokens for the user
            DeviceToken::where('user_id', $user->id)
                ->where('user_type', $userType)
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout from all devices failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Forgot Password
     * POST /api/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = strtolower(trim($request->email));
        
        // Check if admin exists (case-insensitive email lookup, same as login)
        $admin = Admin::whereRaw('LOWER(email) = ?', [$email])->first();
        
        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'User is not exist in database.'
            ], 200);
        }

        // Verify this is a client (type=client or lead)
        if (!in_array($admin->type ?? '', ['client', 'lead'])) {
            return response()->json([
                'success' => false,
                'message' => 'Your record exists in DB but your account type is not Client. So you cannot access this mobile app.'
            ], 200);
        }
        
        // Same portal statuses as login (approved + approval pending)
        if (! $this->hasAllowedClientPortalStatus($admin)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access this mobile app because your client portal is not active from website. Please contact Administrator.'
            ], 200);
        }
        
        // Generate 6-digit random code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Update existing admin with new verification code and timestamp
        $admin->update([
            'cp_random_code' => $verificationCode,
            'cp_code_verify' => 0,
            'cp_token_generated_at' => now() // Store when code was generated
        ]);

        // Send verification email (use admin's stored email for correct delivery)
        try { 
            Mail::raw("Your password reset verification code is: {$verificationCode}\n\nThis code will expire in 10 minutes.\n\nIf you did not request this password reset, please ignore this email.\n\nConsumer guide: https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf", function ($message) use ($admin) {
                $message->to($admin->email)
                        ->subject('Client Portal - Password Reset Verification Code');
            });

            return response()->json([
                'success' => true,
                'message' => 'Password reset verification code sent successfully',
                'data' => [
                    'email' => $admin->email,
                    'expires_in' => 600 // 10 minutes in seconds
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset Password
     * POST /api/reset-password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $admin = Admin::whereRaw('LOWER(email) = ?', [$email])
                     ->where('cp_random_code', $request->code)
                     ->whereIn('type', ['client', 'lead'])
                     ->whereIn('cp_status', $this->allowedClientPortalStatuses())
                     ->first();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code or email'
            ], 400);
        }

        // Check if verification code has expired (10 minutes)
        if ($admin->cp_token_generated_at) {
            $tokenGeneratedAt = \Carbon\Carbon::parse($admin->cp_token_generated_at);
            if ($tokenGeneratedAt->diffInMinutes(now()) > 10) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code has expired. Please request a new code.'
                ], 400);
            }
        }

        // Update password and clear verification code
        $admin->update([
            'password' => Hash::make($request->password),
            'cp_random_code' => null,
            'cp_code_verify' => 0,
            'cp_token_generated_at' => null // Clear the timestamp
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.',
            'data' => [
                'email' => $admin->email,
                'status' => 'password_reset'
            ]
        ], 200);
    }

    /**
     * Update Password
     * POST /api/update-password
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $admin->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ], 200);
    }


    /**
     * Get Client Profile
     * GET /api/profile
     */
    public function getProfile(Request $request)
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $admin->id,
                'client_id' => $admin->client_id,
                'first_name' => $admin->first_name,
                'last_name' => $admin->last_name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'address' => $admin->address,
                'city' => $admin->city,
                'state' => $admin->state,
                'zip' => $admin->zip,
                'country' => $admin->country,
                'marital_status' => $admin->marital_status ?? null,
                'gender' => $admin->gender ?? null,
                'dob' => $admin->dob ?? null,
                'profile_img' => $admin->profile_img,
                'status' => $admin->status,
                'cp_status' => $admin->cp_status,
                'cp_code_verify' => $admin->cp_code_verify,
                'email_verified_at' => $admin->email_verified_at,
                'created_at' => $admin->created_at,
                'updated_at' => $admin->updated_at
            ]
        ], 200);
    }

    /**
     * Refresh Token
     * POST /api/refresh
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the refresh token using DB query
        $refreshTokenData = DB::table('refresh_tokens')
            ->where('token', $request->refresh_token)
            ->where('is_revoked', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$refreshTokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }

        $userType = $refreshTokenData->user_type ?? RefreshToken::USER_TYPE_ADMIN;

        if ($userType === RefreshToken::USER_TYPE_STAFF) {
            return $this->refreshStaffToken($refreshTokenData);
        }

        return $this->refreshClientToken($refreshTokenData);
    }

    /**
     * Refresh a client-portal (Admin) access token.
     */
    private function refreshClientToken(object $refreshTokenData)
    {
        $admin = Admin::find($refreshTokenData->user_id);

        // Same portal statuses as login (approved + approval pending)
        $isClient = $admin && in_array($admin->type ?? '', ['client', 'lead']);
        if (! $isClient || ! $this->hasAllowedClientPortalStatus($admin)) {
            $this->revokeRefreshTokenRow($refreshTokenData->id);

            return response()->json([
                'success' => false,
                'message' => 'User account is no longer active'
            ], 401);
        }

        $this->revokeRefreshTokenRow($refreshTokenData->id);

        $deviceName = $refreshTokenData->device_name ?? 'client-portal-app';
        $newAccessToken = $admin->createToken($deviceName)->plainTextToken;

        try {
            $newRefreshTokenValue = Str::random(64);
            $expiresAt = Carbon::now()->addDays(30);

            $insertData = [
                'user_id' => $admin->id,
                'user_type' => RefreshToken::USER_TYPE_ADMIN,
                'token' => $newRefreshTokenValue,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'is_revoked' => 0,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            DB::table('refresh_tokens')->insertGetId($insertData);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorDetails = $this->handleRefreshTokenError($e, $admin->id, $insertData ?? [], $newRefreshTokenValue ?? '');

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token. Please login again.',
                'error' => 'Token generation failed',
                'problematic_field' => $errorDetails['field'],
                'error_details' => config('app.debug') ? $errorDetails : null
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to generate refresh token during token refresh (non-database error)', [
                'user_id' => $admin->id,
                'user_type' => RefreshToken::USER_TYPE_ADMIN,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token. Please login again.',
                'error' => 'Token generation failed',
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $newAccessToken,
                'refresh_token' => $newRefreshTokenValue,
                'client' => [
                    'id' => $admin->id,
                    'name' => $admin->first_name . ' ' . $admin->last_name,
                    'email' => $admin->email,
                    'client_id' => $admin->client_id,
                    'cp_status' => (int) ($admin->cp_status ?? 0),
                    'cp_status_text' => $this->getCpStatusText((int) ($admin->cp_status ?? 0)),
                ]
            ]
        ], 200);
    }

    /**
     * Refresh a staff (admin-login) access token.
     */
    private function refreshStaffToken(object $refreshTokenData)
    {
        $staff = Staff::find($refreshTokenData->user_id);

        if (! $staff || (int) ($staff->status ?? 0) !== 1 || ! in_array((int) $staff->role, [1, 12, 13, 16], true)) {
            $this->revokeRefreshTokenRow($refreshTokenData->id);

            return response()->json([
                'success' => false,
                'message' => 'User account is no longer active'
            ], 401);
        }

        $this->revokeRefreshTokenRow($refreshTokenData->id);

        $deviceName = $refreshTokenData->device_name ?? 'admin-portal-app';
        $newAccessToken = $staff->createToken($deviceName)->plainTextToken;

        try {
            $newRefreshTokenValue = Str::random(64);
            $expiresAt = Carbon::now()->addDays(30);

            $insertData = [
                'user_id' => $staff->id,
                'user_type' => RefreshToken::USER_TYPE_STAFF,
                'token' => $newRefreshTokenValue,
                'device_name' => $deviceName,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'is_revoked' => 0,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ];

            DB::table('refresh_tokens')->insertGetId($insertData);
        } catch (\Illuminate\Database\QueryException $e) {
            $errorDetails = $this->handleRefreshTokenError($e, $staff->id, $insertData ?? [], $newRefreshTokenValue ?? '');

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token. Please login again.',
                'error' => 'Token generation failed',
                'problematic_field' => $errorDetails['field'],
                'error_details' => config('app.debug') ? $errorDetails : null
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to generate refresh token during staff token refresh (non-database error)', [
                'user_id' => $staff->id,
                'user_type' => RefreshToken::USER_TYPE_STAFF,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token. Please login again.',
                'error' => 'Token generation failed',
                'error_details' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $newAccessToken,
                'refresh_token' => $newRefreshTokenValue,
                'user' => [
                    'id' => $staff->id,
                    'name' => $staff->first_name . ' ' . $staff->last_name,
                    'email' => $staff->email,
                    'role' => $staff->role,
                ]
            ]
        ], 200);
    }

    private function revokeRefreshTokenRow(int $id): void
    {
        DB::table('refresh_tokens')
            ->where('id', $id)
            ->update(['is_revoked' => 1, 'updated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    /**
     * Handle refresh token database errors and return detailed error information
     */
    private function handleRefreshTokenError($e, $userId, $insertData, $tokenValue)
    {
        $errorMessage = $e->getMessage();
        $problematicField = null;
        
        // Detect which field is causing the issue
        if (str_contains($errorMessage, 'user_id')) {
            $problematicField = 'user_id';
        } elseif (str_contains($errorMessage, 'token')) {
            $problematicField = 'token';
        } elseif (str_contains($errorMessage, 'device_name')) {
            $problematicField = 'device_name';
        } elseif (str_contains($errorMessage, 'expires_at')) {
            $problematicField = 'expires_at';
        } elseif (str_contains($errorMessage, 'is_revoked')) {
            $problematicField = 'is_revoked';
        } elseif (str_contains($errorMessage, 'created_at')) {
            $problematicField = 'created_at';
        } elseif (str_contains($errorMessage, 'updated_at')) {
            $problematicField = 'updated_at';
        } elseif (str_contains($errorMessage, 'Duplicate entry')) {
            $problematicField = 'token (duplicate)';
        } elseif (str_contains($errorMessage, 'foreign key constraint')) {
            $problematicField = 'user_id (foreign key constraint - user may not exist)';
        } elseif (str_contains($errorMessage, 'cannot be null')) {
            // Extract field name from error
            preg_match("/Column '([^']+)' cannot be null/", $errorMessage, $matches);
            $problematicField = $matches[1] ?? 'unknown field';
        }
        
        Log::error('Failed to generate refresh token', [
            'user_id' => $userId,
            'error' => $errorMessage,
            'problematic_field' => $problematicField,
            'error_code' => $e->getCode(),
            'sql_state' => $e->errorInfo[0] ?? null,
            'driver_code' => $e->errorInfo[1] ?? null,
            'sql_message' => $e->errorInfo[2] ?? null,
            'insert_data' => array_merge($insertData, ['token' => substr($tokenValue, 0, 10) . '...']),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'field' => $problematicField,
            'message' => $errorMessage,
            'sql_state' => $e->errorInfo[0] ?? null,
            'driver_code' => $e->errorInfo[1] ?? null,
        ];
    }

    /**
     * Handle device token registration/update
     */
    private function handleDeviceToken($userId, $deviceToken, $deviceName = null, string $userType = DeviceToken::USER_TYPE_ADMIN)
    {
        try {
            // Check if device token already exists
            $existingToken = DeviceToken::where('device_token', $deviceToken)->first();
            
            if ($existingToken) {
                // Update existing token
                $existingToken->update([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'device_name' => $deviceName,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            } else {
                // Create new device token
                DeviceToken::create([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'device_token' => $deviceToken,
                    'device_name' => $deviceName,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the login
            Log::error('Failed to handle device token: ' . $e->getMessage(), [
                'user_id' => $userId,
                'user_type' => $userType,
                'device_token' => substr($deviceToken, 0, 20) . '...'
            ]);
        }
    }

    /**
     * Map an authenticated Sanctum user to refresh/device token user_type.
     */
    private function tokenUserTypeFor($user): string
    {
        return $user instanceof Staff
            ? RefreshToken::USER_TYPE_STAFF
            : RefreshToken::USER_TYPE_ADMIN;
    }

    /**
     * Portal statuses allowed for login, refresh, and password reset.
     * 1 = approved, 2 = approval_pending. Excludes 0 (inactive/default).
     */
    private function allowedClientPortalStatuses(): array
    {
        return [1, 2];
    }

    private function hasAllowedClientPortalStatus(?Admin $admin): bool
    {
        return $admin !== null
            && in_array((int) ($admin->cp_status ?? 0), $this->allowedClientPortalStatuses(), true);
    }

    /**
     * Get cp_status_text for a given cp_status value.
     * 0 = default, 1 = approve, 2 = approval_pending
     */
    private function getCpStatusText(int $cpStatus): string
    {
        return match ($cpStatus) {
            1 => 'approve',
            2 => 'approval_pending',
            default => 'default',
        };
    }

    /**
     * Check User Authentication
     * GET /api/check-user-authentication
     *
     * Returns cp_status and cp_status_text for the logged-in user. No input parameters.
     * cp_status: 0 = default, 1 = approve, 2 = approval_pending
     */
    public function checkUserAuthentication(Request $request)
    {
        $user = $request->user();
        $cpStatus = 0;
        if ($user && isset($user->cp_status)) {
            $cpStatus = (int) $user->cp_status;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'cp_status' => $cpStatus,
                'cp_status_text' => $this->getCpStatusText($cpStatus),
            ],
        ], 200);
    }

    /**
     * Expire Token
     * POST /api/expire-token
     * 
     * Expires a specific access token by updating its expires_at column
     */
    public function expireToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the token using Sanctum's findToken method
            $accessToken = PersonalAccessToken::findToken($request->token);

            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not found or invalid'
                ], 404);
            }

            // Check if token is already expired
            if ($accessToken->expires_at && $accessToken->expires_at < Carbon::now()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Token is already expired',
                    'data' => [
                        'token_id' => $accessToken->id,
                        'user_id' => $accessToken->tokenable_id,
                        'expires_at' => $accessToken->expires_at,
                        'status' => 'already_expired'
                    ]
                ], 200);
            }

            // Update expires_at to current time (expire immediately)
            $accessToken->update([
                'expires_at' => Carbon::now()
            ]);

            Log::info('Token expired via API', [
                'token_id' => $accessToken->id,
                'user_id' => $accessToken->tokenable_id,
                'expires_at' => $accessToken->expires_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token expired successfully',
                'data' => [
                    'token_id' => $accessToken->id,
                    'user_id' => $accessToken->tokenable_id,
                    'expires_at' => $accessToken->expires_at,
                    'status' => 'expired'
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to expire token', [
                'error' => $e->getMessage(),
                'token_preview' => substr($request->token, 0, 20) . '...',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to expire token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update Profile
     * PUT /api/profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Validate the request (post_code/zip both map to DB column 'zip')
            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => ['sometimes', 'email', 'max:255', 'unique:admins,email,' . $clientId],
                'phone' => 'sometimes|string|max:255',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:255',
                'state' => 'sometimes|string|max:255',
                'post_code' => 'sometimes|string|max:20',
                'zip' => 'sometimes|string|max:20',
                'country' => 'sometimes|string|max:255',
                'dob' => 'sometimes|date|before:today',
                'gender' => 'sometimes|string|in:Male,Female,Other',
                'marital_status' => 'sometimes|string|in:Never Married,Engaged,Married,De Facto,Defacto,Separated,Divorced,Widowed,Single'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get the client record
            $client = DB::table('admins')->where('id', $clientId)->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            // Prepare update data (post_code maps to DB column 'zip')
            $updateData = [];
            $allowedFields = [
                'first_name', 'last_name', 'email', 'phone', 'address', 'city',
                'state', 'country', 'dob', 'gender', 'marital_status'
            ];

            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);
                    if ($field === 'marital_status' && $value === 'Single') {
                        $value = 'Never Married';
                    }
                    if ($field === 'marital_status' && $value === 'Defacto') {
                        $value = 'De Facto';
                    }
                    $updateData[$field] = $value;
                }
            }

            // Map post_code or zip to DB column 'zip'
            if ($request->has('zip')) {
                $updateData['zip'] = $request->input('zip');
            } elseif ($request->has('post_code')) {
                $updateData['zip'] = $request->input('post_code');
            }

            // Add updated_at timestamp
            $updateData['updated_at'] = now();

            // Update the client record
            $updated = DB::table('admins')
                ->where('id', $clientId)
                ->update($updateData);

            if ($updated) {
                // Sync email to client_emails table when email was updated
                if (isset($updateData['email'])) {
                    $newEmail = $updateData['email'];
                    $emailType = $client->email_type ?? 'Personal';

                    $clientEmail = ClientEmail::where('client_id', $clientId)
                        ->where('email_type', $emailType)
                        ->first();

                    if ($clientEmail) {
                        $clientEmail->update(['email' => $newEmail]);
                    } else {
                        // Create record if none exists for this type (keep client_emails in sync)
                        ClientEmail::create([
                            'client_id' => $clientId,
                            'admin_id' => $clientId,
                            'email_type' => $emailType,
                            'email' => $newEmail,
                            'is_verified' => false,
                        ]);
                    }
                }

                // Get updated client data
                $updatedClient = DB::table('admins')->where('id', $clientId)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'data' => [
                        'id' => $updatedClient->id,
                        'client_id' => $updatedClient->client_id,
                        'first_name' => $updatedClient->first_name,
                        'last_name' => $updatedClient->last_name,
                        'email' => $updatedClient->email,
                        'phone' => $updatedClient->phone,
                        'address' => $updatedClient->address,
                        'city' => $updatedClient->city,
                        'state' => $updatedClient->state,
                        'zip' => $updatedClient->zip,
                        'country' => $updatedClient->country,
                        'marital_status' => $updatedClient->marital_status ?? null,
                        'gender' => $updatedClient->gender ?? null,
                        'dob' => $updatedClient->dob ?? null,
                        'profile_img' => asset('img/avatar.png'),
                        'status' => $updatedClient->status,
                        'cp_status' => $updatedClient->cp_status,
                        'cp_code_verify' => $updatedClient->cp_code_verify,
                        'email_verified_at' => $updatedClient->email_verified_at,
                        'created_at' => $updatedClient->created_at,
                        'updated_at' => $updatedClient->updated_at
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update profile'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Update Profile API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}