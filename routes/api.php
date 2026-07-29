<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ServiceAccountController;
use App\Http\Controllers\API\BroadcastNotificationController;
use App\Http\Controllers\API\ClientPortalController;
use App\Http\Controllers\API\ClientPortalDashboardController;
use App\Http\Controllers\API\ClientPortalDocumentController;
use App\Http\Controllers\API\ClientPortalWorkflowController;
use App\Http\Controllers\API\ClientPortalMessageController;
use App\Http\Controllers\API\ClientPortalNotificationController;
use App\Http\Controllers\API\ClientPortalPersonalDetailsController;
use App\Http\Controllers\API\ClientPortalCommonListingController;
use App\Http\Controllers\API\ClientPortalAppointmentController;
use App\Http\Controllers\API\ClientPortalBillingController;
use App\Http\Controllers\API\FCMController;
use App\Http\Controllers\API\OthersController;
use App\Http\Controllers\API\VisaPricingEstimatorController;
use App\Http\Controllers\API\SignupController;
use App\Http\Controllers\API\ChatbotController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes (no authentication required)
Route::post('/login', [ClientPortalController::class, 'login']);
Route::post('/admin-login', [ClientPortalController::class, 'adminLogin']);
Route::post('/signup', [SignupController::class, 'signup']);
Route::post('/refresh', [ClientPortalController::class, 'refresh']);
Route::post('/forgot-password', [ClientPortalController::class, 'forgotPassword']);
Route::post('/reset-password', [ClientPortalController::class, 'resetPassword']);
Route::post('/expire-token', [ClientPortalController::class, 'expireToken']);

// Countries API (public route)
Route::get('/countries', [ClientPortalCommonListingController::class, 'getCountries']);

// Visa Types API (public route)
Route::get('/visa-types', [ClientPortalCommonListingController::class, 'getVisaTypes']);

// Search Occupations API (public route). Trailing-slash /api/... URLs are not 301’d by Apache (see public/.htaccess) so CORS stays on the JSON response.
Route::get('/search-occupation', [ClientPortalCommonListingController::class, 'searchOccupationDetail']);
Route::get('/occupation-result', [ClientPortalCommonListingController::class, 'getOccupationResult']);
Route::get('/occupation-all', [ClientPortalCommonListingController::class, 'getOccupationAll']);

// Appointment Variable Lists API (public route)
Route::get('/appointment-variable-lists', [ClientPortalAppointmentController::class, 'getAppointmentVariableLists']);

// Add Appointment Without Login (public route)
Route::post('/appointments/add-appointment-without-login', [ClientPortalAppointmentController::class, 'addAppointmentWithoutLogin']);

// Get Disabled Dates/Slots (public - no auth required, used for calendar availability)
Route::post('/appointments/get-disabled-dates', [ClientPortalAppointmentController::class, 'getDisabledDateFromCalendar']);
Route::post('/appointments/get-disabled-slots', [ClientPortalAppointmentController::class, 'getDisabledSlotsOfAnyDateFromCalendar']);

// Record Payment Without Login (public - for guests who booked via add-appointment-without-login)
Route::post('/appointments/record-payment-without-login', [ClientPortalAppointmentController::class, 'recordAppointmentPaymentWithoutLogin']);
Route::post('/appointments/record-payment-without-login-wallet', [ClientPortalAppointmentController::class, 'recordAppointmentPaymentWithoutLoginWallet']);

// Create Payment Intent (public - works with or without auth; used by both logged-in users and guests)
Route::post('/payments/create-payment-intent', function (Request $request) {
    $validated = $request->validate([
        'amount' => ['required', 'integer', 'min:50'],
        'currency' => ['sometimes', 'string', 'size:3'],
        'customer' => ['sometimes', 'string'],
        'description' => ['sometimes', 'string', 'max:255'],
        'metadata' => ['sometimes', 'array'],
        'receipt_email' => ['sometimes', 'email'],
        'automatic_payment_methods.enabled' => ['sometimes', 'boolean'],
    ]);

    try {
        $stripeSecret = config('services.stripe.secret');

        if (!$stripeSecret) {
            return response()->json([
                'message' => 'Stripe secret key is not configured.',
            ], 500);
        }

        $stripe = new \Stripe\StripeClient($stripeSecret);

        $payload = [
            'amount' => $validated['amount'],
            'currency' => strtolower($validated['currency'] ?? 'usd'),
            'automatic_payment_methods' => [
                'enabled' => data_get($validated, 'automatic_payment_methods.enabled', true),
            ],
        ];

        if (isset($validated['customer'])) {
            $payload['customer'] = $validated['customer'];
        }

        if (isset($validated['description'])) {
            $payload['description'] = $validated['description'];
        }

        if (isset($validated['metadata'])) {
            $payload['metadata'] = $validated['metadata'];
        }

        if (isset($validated['receipt_email'])) {
            $payload['receipt_email'] = $validated['receipt_email'];
        }

        $paymentIntent = $stripe->paymentIntents->create($payload);

        return response()->json([
            'id' => $paymentIntent->id,
            'status' => $paymentIntent->status,
            'client_secret' => $paymentIntent->client_secret,
            'amount' => $paymentIntent->amount,
            'currency' => $paymentIntent->currency,
        ], 201);
    } catch (\Stripe\Exception\ApiErrorException $exception) {
        Log::error('Stripe PaymentIntent creation failed', [
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'Unable to create payment intent.',
            'error' => $exception->getMessage(),
        ], 400);
    } catch (\Throwable $exception) {
        Log::error('Unexpected error creating PaymentIntent', [
            'message' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => 'An unexpected error occurred.',
        ], 500);
    }
});

// Blog routes (list is public; detail requires authentication — see auth:sanctum group)
Route::get('/blogs/list', [OthersController::class, 'getBlogList']);

// Full Australian postcode list (public — proxies Bansal; postcode-search / postcode-result remain Sanctum)
Route::get('/postcode-all', [OthersController::class, 'getPostcodeAll']);

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [ClientPortalController::class, 'logout']);
    Route::post('/logout-all', [ClientPortalController::class, 'logoutAll']);
    Route::get('/check-user-authentication', [ClientPortalController::class, 'checkUserAuthentication']);
    Route::get('/profile', [ClientPortalController::class, 'getProfile']);
    Route::post('/profile', [ClientPortalController::class, 'updateProfile']);
    Route::post('/update-password', [ClientPortalController::class, 'updatePassword']);

    // Blog detail (authenticated only; list remains public)
    Route::get('/blogs/detail/{id}', [OthersController::class, 'getBlogDetail']);

    // PR Point Calculator (authenticated — client portal Sanctum)
    Route::get('/pr-point-calc-lists', [OthersController::class, 'getPrPointCalcLists']);
    Route::post('/pr-point-calc-result', [OthersController::class, 'calculatePrPointsResult']);

    // Student Calculator (authenticated)
    Route::get('/student-calc-lists', [OthersController::class, 'getStudentCalcLists']);
    Route::post('/student-calc-result', [OthersController::class, 'calculateStudentFinancialRequirements']);

    // Occupation Finder (authenticated)
    Route::get('/occupation-finder', [OthersController::class, 'searchOccupation']);

    // Postcode Checker (authenticated — full list is public: GET /postcode-all)
    Route::get('/postcode-search', [OthersController::class, 'searchPostcode']);
    Route::get('/postcode-result', [OthersController::class, 'getPostcodeResult']);

    // Visa Estimate (authenticated)
    // Reference: https://immi.homeaffairs.gov.au/visas/visa-pricing-estimator
    Route::prefix('visa-estimate')->name('visa-estimate.')->group(function () {
        Route::get('/visa-list', [VisaPricingEstimatorController::class, 'getVisaList']);
        Route::post('/estimate', [VisaPricingEstimatorController::class, 'getEstimate']);
    });
    
    // Dashboard routes
    Route::get('/dashboard', [ClientPortalDashboardController::class, 'dashboard']);
    Route::get('/recent-cases', [ClientPortalDashboardController::class, 'recentCaseViewAll']);
    Route::get('/documents', [ClientPortalDashboardController::class, 'documentViewAll']);
    Route::get('/upcoming-deadlines', [ClientPortalDashboardController::class, 'upcomingDeadlinesViewAll']);
    Route::get('/recent-activity', [ClientPortalDashboardController::class, 'recentActivityViewAll']);
    
    // Matters routes
    Route::get('/matters', [ClientPortalDashboardController::class, 'getAllMatters']);

    // Billing routes
    Route::get('/billing/list', [ClientPortalBillingController::class, 'list']);
    Route::post('/billing/create-payment-intent', [ClientPortalBillingController::class, 'createPaymentIntent']);
    Route::post('/billing/invoice-update', [ClientPortalBillingController::class, 'updateInvoice']);

    // Client Personal Details routes
    Route::get('/get-client-personal-detail', [ClientPortalPersonalDetailsController::class, 'getClientPersonalDetail']);
    Route::post('/update-client-basic-detail', [ClientPortalPersonalDetailsController::class, 'updateClientBasicDetail']);
    Route::post('/update-client-phone-detail', [ClientPortalPersonalDetailsController::class, 'updateClientPhoneDetail']);
    Route::post('/update-client-email-detail', [ClientPortalPersonalDetailsController::class, 'updateClientEmailDetail']);
    Route::post('/update-client-address-detail', [ClientPortalPersonalDetailsController::class, 'updateClientAddressDetail']);
    Route::post('/update-client-travel-detail', [ClientPortalPersonalDetailsController::class, 'updateClientTravelDetail']);
    Route::post('/update-client-qualification-detail', [ClientPortalPersonalDetailsController::class, 'updateClientQualificationDetail']);
    Route::post('/update-client-experience-detail', [ClientPortalPersonalDetailsController::class, 'updateClientExperienceDetail']);
    Route::post('/update-client-occupation-detail', [ClientPortalPersonalDetailsController::class, 'updateClientOccupationDetail']);
    Route::post('/update-client-testscore-detail', [ClientPortalPersonalDetailsController::class, 'updateClientTestScoreDetail']);
    Route::post('/update-client-passport-detail', [ClientPortalPersonalDetailsController::class, 'updateClientPassportDetail']);
    Route::post('/delete-client-tab-detail', [ClientPortalPersonalDetailsController::class, 'deleteClientTabDetail']);
    Route::post('/delete-client-passport-detail', [ClientPortalPersonalDetailsController::class, 'deleteClientPassportDetail']); // Deprecated: Use delete-client-tab-detail instead
    Route::post('/update-client-visa-detail', [ClientPortalPersonalDetailsController::class, 'updateClientVisaDetail']);
    

    // Document Management routes
    Route::get('/documents/personal/categories', [ClientPortalDocumentController::class, 'getPersonalDocumentCategories']);
    Route::get('/documents/personal/checklist', [ClientPortalDocumentController::class, 'getPersonalDocumentChecklist']);
    Route::get('/documents/visa/categories', [ClientPortalDocumentController::class, 'getVisaDocumentCategories']);
    Route::get('/documents/visa/checklist', [ClientPortalDocumentController::class, 'getVisaDocumentChecklist']);
    Route::post('/documents/checklist', [ClientPortalDocumentController::class, 'addDocumentChecklist']);
    Route::post('/documents/upload', [ClientPortalDocumentController::class, 'uploadDocument']);
    
    // Workflow Management routes
    Route::get('/workflow/stages', [ClientPortalWorkflowController::class, 'getWorkflowStages']);
    Route::get('/workflow/stages/{stage_id}', [ClientPortalWorkflowController::class, 'getWorkflowStageDetails']);
    Route::get('/workflow/allowed-checklist', [ClientPortalWorkflowController::class, 'getAllowedChecklist']);
    Route::post('/workflow/upload-allowed-checklist', [ClientPortalWorkflowController::class, 'uploadAllowedChecklistDocument']);
    Route::post('/workflow/upload-allowed-checklist-bulk-upload', [ClientPortalWorkflowController::class, 'uploadAllowedChecklistDocumentBulk']);
    
    // Messaging routes (specific routes first to avoid conflicts)
    Route::post('/messages/send', [ClientPortalMessageController::class, 'sendMessage']);
    Route::post('/messages/send-to-client', [ClientPortalMessageController::class, 'sendMessageToClient']);
    Route::get('/messages', [ClientPortalMessageController::class, 'getMessages']);
    Route::get('/messages/unread-count', [ClientPortalMessageController::class, 'getUnreadCount']);
    Route::get('/messages/attachments/{id}/download', [ClientPortalMessageController::class, 'downloadAttachment']);
    Route::post('/messages/{id}/read', [ClientPortalMessageController::class, 'markAsRead']);
    Route::get('/messages/{id}', [ClientPortalMessageController::class, 'getMessageDetails']);

    // Action Required (cp_action_requires) — register /action-required/unread before /action-required
    Route::get('/action-required/unread', [ClientPortalNotificationController::class, 'actionRequiredUnread']);
    Route::get('/action-required', [ClientPortalNotificationController::class, 'actionRequiredIndex']);

    // Notifications routes (client portal) — specific paths before /notifications/{id}
    Route::get('/notifications', [ClientPortalNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [ClientPortalNotificationController::class, 'unreadCount']);
    Route::get('/notifications/recent-unread', [ClientPortalNotificationController::class, 'recentUnread']);
    Route::get('/notifications/{id}', [ClientPortalNotificationController::class, 'show']);
    Route::post('/notifications/{id}/read', [ClientPortalNotificationController::class, 'markAsRead']);

    // Broadcast notifications
    Route::get('/notifications/broadcasts/unread', [BroadcastNotificationController::class, 'unread']);
    Route::post('/notifications/broadcasts', [BroadcastNotificationController::class, 'store']);
    Route::get('/notifications/broadcasts', [BroadcastNotificationController::class, 'index']);
    Route::get('/notifications/broadcasts/{batchUuid}', [BroadcastNotificationController::class, 'show']);
    Route::post('/notifications/broadcasts/{notificationId}/read', [BroadcastNotificationController::class, 'markAsRead']);
    Route::post('/notifications/broadcasts/{notificationId}/start-read-timer', [BroadcastNotificationController::class, 'startReadTimer']);
    Route::get('/notifications/broadcasts/{notificationId}/receiver-detail', [BroadcastNotificationController::class, 'receiverDetail']);
    
    // FCM Push Notification routes
    Route::post('/fcm/register-token', [FCMController::class, 'registerToken']);
    Route::post('/fcm/unregister-token', [FCMController::class, 'unregisterToken']);
    Route::post('/fcm/test', [FCMController::class, 'testNotification']);
    Route::post('/fcm/send-message', [FCMController::class, 'sendMessage']);
    
    // Appointment routes (specific paths before /appointments/{id} to avoid 405 on POST)
    Route::get('/appointments', [ClientPortalAppointmentController::class, 'getAppointmentList']);
    Route::post('/appointments/process-payment', [ClientPortalAppointmentController::class, 'processAppointmentPayment']);
    Route::post('/appointments/record-payment', [ClientPortalAppointmentController::class, 'recordAppointmentPayment']);
    Route::post('/appointments/record-payment-wallet', [ClientPortalAppointmentController::class, 'recordAppointmentPaymentWallet']);
    Route::get('/appointments/{id}/payment-history', [ClientPortalAppointmentController::class, 'getPaymentHistory']);
    Route::get('/appointments/{id}', [ClientPortalAppointmentController::class, 'getSingleAppointment']);
    Route::post('/appointments', [ClientPortalAppointmentController::class, 'addAppointment']);
    Route::post('/appointments/{id}/status', [ClientPortalAppointmentController::class, 'updateAppointmentStatus']);
    Route::post('/appointments/update-appointment', [ClientPortalAppointmentController::class, 'updateAppointment']);

    // Chatbot (Anthropic Claude — server uses ANTHROPIC_API_KEY)
    Route::post('/chatbot', [ChatbotController::class, 'chat']);
    
});

// Broadcasting auth route for WebSocket authentication
Route::post('/broadcasting/auth', function (Request $request) {
    try {
        // Get the authorization header
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Extract token
        $token = substr($authHeader, 7);
        
        // Get request data - handle both form and JSON content types
        $socketId = $request->input('socket_id');
        $channelName = $request->input('channel_name');
        
        // Validate token using Sanctum
        $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
        
        if (!$user) {
            Log::error('Invalid token provided for channel auth', ['token' => substr($token, 0, 10) . '...']);
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        // Log the request details for debugging
        Log::info('Broadcasting auth request', [
            'content_type' => $request->header('Content-Type'),
            'socket_id' => $socketId,
            'channel_name' => $channelName,
            'user_id' => $user->id,
            'request_data' => $request->all()
        ]);
        
        // Set the authenticated user for the request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });
        
        // Validate channel name format and authorization
        if (!preg_match('/^private-(user|matter)\.\d+$/', $channelName)) {
            Log::warning('Invalid channel format', ['user_id' => $user->id, 'channel' => $channelName]);
            return response()->json(['error' => 'Invalid channel format'], 403);
        }
        
        // Ensure we have required parameters
        if (!$socketId || !$channelName) {
            Log::warning('Missing required parameters', [
                'socket_id' => $socketId,
                'channel_name' => $channelName,
                'user_id' => $user->id
            ]);
            return response()->json(['error' => 'Missing required parameters'], 400);
        }
        
        // Check channel authorization based on channel type
        if (str_starts_with($channelName, 'private-user.')) {
            $requestedUserId = (int) substr($channelName, 13); // Remove 'private-user.'
            if ($user->id !== $requestedUserId) {
                Log::warning('User cannot access another user\'s channel', [
                    'user_id' => $user->id, 
                    'requested_user_id' => $requestedUserId,
                    'channel' => $channelName
                ]);
                return response()->json(['error' => 'Channel access denied'], 403);
            }
        } elseif (str_starts_with($channelName, 'private-matter.')) {
            $matterId = (int) substr($channelName, 15); // Remove 'private-matter.'
            
            // Check if user is associated with this matter or is superadmin
            $isAssociated = DB::table('client_matters')
                ->where('id', $matterId)
                ->where(function($query) use ($user) {
                    $query->where('sel_migration_agent', $user->id)
                          ->orWhere('sel_person_responsible', $user->id)
                          ->orWhere('sel_person_assisting', $user->id);
                })
                ->exists();
            
            $isSuperAdmin = $user->role == 1;
            
            if (!$isAssociated && !$isSuperAdmin) {
                Log::warning('User cannot access matter channel', [
                    'user_id' => $user->id, 
                    'matter_id' => $matterId,
                    'channel' => $channelName
                ]);
                return response()->json(['error' => 'Channel access denied'], 403);
            }
        }
        
        Log::info('Channel auth successful', ['user_id' => $user->id, 'channel' => $channelName]);

        // Sign with Laravel Reverb (Pusher protocol); must match REVERB_APP_KEY the client uses.
        $reverb = config('broadcasting.connections.reverb');
        $reverbKey = $reverb['key'] ?? '';
        $reverbSecret = $reverb['secret'] ?? '';
        $reverbAppId = $reverb['app_id'] ?? '';

        if ($reverbKey === '' || $reverbSecret === '' || $reverbAppId === '') {
            Log::error('Broadcasting auth: Reverb credentials missing (REVERB_APP_KEY / REVERB_APP_SECRET / REVERB_APP_ID)');

            return response()->json([
                'error' => 'Broadcasting is not configured. Set REVERB_APP_KEY, REVERB_APP_SECRET, and REVERB_APP_ID.',
            ], 503);
        }

        $reverbOpts = $reverb['options'] ?? [];
        $pusher = new \Pusher\Pusher(
            $reverbKey,
            $reverbSecret,
            $reverbAppId,
            [
                'host' => $reverbOpts['host'] ?? '127.0.0.1',
                'port' => (int) ($reverbOpts['port'] ?? 8080),
                'scheme' => $reverbOpts['scheme'] ?? 'http',
                'useTLS' => (bool) ($reverbOpts['useTLS'] ?? false),
            ]
        );

        $authResponse = $pusher->authorizeChannel($channelName, $socketId);

        return response($authResponse, 200, [
            'Content-Type' => 'application/json',
        ]);
        
    } catch (\Exception $e) {
        Log::error('Broadcasting auth error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request_data' => [
                'socket_id' => $request->input('socket_id'),
                'channel_name' => $request->input('channel_name'),
                'auth_header' => $request->header('Authorization') ? 'Present' : 'Missing'
            ]
        ]);
        return response()->json(['error' => 'Authentication failed: ' . $e->getMessage()], 500);
    }
});

// Service Account Token Generation
Route::post('/service-account/generate-token', [ServiceAccountController::class, 'generateToken']);

// ANZSCO Occupation API Routes moved to web.php for proper authentication

    