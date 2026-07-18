<?php

use App\Http\Controllers\CRM\ClientsController;
use App\Http\Controllers\CRM\ClientEoiRoiController;
use App\Http\Controllers\AdminConsole\AnzscoOccupationController;
use App\Http\Controllers\CRM\Clients\ClientNotesController;
use App\Http\Controllers\CRM\ClientPersonalDetailsController;
use App\Http\Controllers\CRM\PhoneVerificationController;
use App\Http\Controllers\CRM\EmailVerificationController;
use App\Http\Controllers\CRM\Leads\LeadController;
use App\Http\Controllers\CRM\Leads\LeadAssignmentController;
use App\Http\Controllers\CRM\Leads\LeadConversionController;
use App\Http\Controllers\CRM\Leads\LeadAnalyticsController;
use App\Http\Controllers\CRM\DashboardController;
use App\Http\Controllers\CRM\CRMUtilityController;
use App\Http\Controllers\CRM\AssigneeController;
use App\Http\Controllers\CRM\ActiveStaffController;
use App\Http\Controllers\CRM\BroadcastNotificationAjaxController;
use App\Http\Controllers\CRM\BroadcastController;
// use App\Http\Controllers\CRM\EmailTemplateController; // DISABLED: email_templates table has been deleted
use App\Http\Controllers\CRM\AuditLogController;
use App\Http\Controllers\CRM\ReportController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\CRM\ReverbMessagingLabController;
use App\Http\Controllers\Public\PublicLeadInquiryController;
use App\Http\Controllers\Public\PublicAppointmentPaymentController;
use App\Http\Controllers\Public\PublicPhoneCallController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/*--------------------------------------------------
| SECTION: Root & General Routes
|--------------------------------------------------*/

// Root route - redirect to CRM login
Route::get('/', function() {
    return redirect()->route('crm.login');
});

/*--------------------------------------------------
| Public lead inquiry (no auth)
|--------------------------------------------------*/
$publicLeadPath = config('public_lead_form.path', 'lead-client-info-form');
Route::middleware('throttle:30,1')->group(function () use ($publicLeadPath) {
    Route::get("/{$publicLeadPath}", [PublicLeadInquiryController::class, 'showForm'])->name('public.lead-inquiry');
});
Route::post("/{$publicLeadPath}", [PublicLeadInquiryController::class, 'submit'])
    ->middleware('throttle:10,1')
    ->name('public.lead-inquiry.submit');
Route::post("/{$publicLeadPath}/confirm", [PublicLeadInquiryController::class, 'confirmUpdate'])
    ->middleware('throttle:10,1')
    ->name('public.lead-inquiry.confirm');
Route::post("/{$publicLeadPath}/cancel", [PublicLeadInquiryController::class, 'cancelUpdate'])
    ->middleware('throttle:10,1')
    ->name('public.lead-inquiry.cancel');

Route::middleware('throttle:60,1')->get('/phone-call', PublicPhoneCallController::class)->name('public.phone-call');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/appointment/pay/{token}', [PublicAppointmentPaymentController::class, 'show'])->name('public.appointment.pay');
    Route::post('/appointment/pay/{token}/intent', [PublicAppointmentPaymentController::class, 'createIntent'])->name('public.appointment.pay.intent');
    Route::post('/appointment/pay/{token}/complete', [PublicAppointmentPaymentController::class, 'complete'])->name('public.appointment.pay.complete');
});

// Cache clearing route - protected with authentication
Route::get('/clear-cache', function() {
    Artisan::call('config:clear');
    Artisan::call('view:clear');
    Artisan::call('route:clear');
    Artisan::call('route:cache');
    return response()->json([
        'success' => true,
        'message' => 'Cache cleared successfully'
    ]);
})->middleware('auth');

/*--------------------------------------------------
| SECTION: Exception Handling
|--------------------------------------------------*/
Route::get('/exception', [\App\Http\Controllers\ExceptionController::class, 'index'])->name('exception.index');
Route::post('/exception', [\App\Http\Controllers\ExceptionController::class, 'index'])->name('exception.store');

/*--------------------------------------------------
| SECTION: Authentication Routes
|--------------------------------------------------*/
// Auth::routes(); // Disabled - Using custom admin login at /admin and API login at /api/login instead

/*--------------------------------------------------
| SECTION: Admin Console Routes
|--------------------------------------------------*/
require __DIR__ . '/adminconsole.php';

/*--------------------------------------------------
| SECTION: Authentication Routes
|--------------------------------------------------*/
// CRM authentication routes (no /admin prefix)
Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('crm.login');
Route::post('/login', [AdminLoginController::class, 'login'])->name('crm.login.post');
Route::post('/logout', [AdminLoginController::class, 'logout'])->name('crm.logout');
Route::get('/logout', function() {
    return redirect()->route('crm.login');
})->name('crm.logout.get');

/*--------------------------------------------------
| SECTION: CRM Application Routes (Protected)
|--------------------------------------------------*/
// Main CRM routes at root level with auth:admin middleware
Route::middleware(['auth:admin'])->group(function() {

	/*---------- Dashboard Routes ----------*/
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/matters-fragment', [DashboardController::class, 'mattersFragment'])->name('dashboard.matters-fragment');
    Route::get('/dashboard/cases-fragment', [DashboardController::class, 'casesFragment'])->name('dashboard.cases-fragment');
    Route::post('/dashboard/column-preferences', [DashboardController::class, 'saveColumnPreferences'])->name('dashboard.column-preferences');
    Route::post('/dashboard/extend-deadline', [DashboardController::class, 'extendDeadlineDate'])->name('dashboard.extend-deadline');
    Route::post('/dashboard/update-action-completed', [DashboardController::class, 'updateActionCompleted'])->name('dashboard.update-action-completed');
    Route::get('/dashboard/fetch-notifications', [CRMUtilityController::class, 'fetchnotification'])->name('dashboard.fetch-notifications');
    Route::get('/dashboard/fetch-office-visit-notifications', [CRMUtilityController::class, 'fetchOfficeVisitNotifications'])->name('dashboard.fetch-office-visit-notifications');
    Route::post('/dashboard/mark-notification-seen', [CRMUtilityController::class, 'markNotificationSeen'])->name('dashboard.mark-notification-seen');
    Route::get('/dashboard/fetch-visa-expiry-messages', [CRMUtilityController::class, 'fetchvisaexpirymessages'])->name('dashboard.fetch-visa-expiry-messages');
    Route::get('/dashboard/fetch-in-person-waiting-count', [CRMUtilityController::class, 'fetchInPersonWaitingCount'])->name('dashboard.fetch-in-person-waiting-count');
    Route::get('/dashboard/fetch-total-activity-count', [CRMUtilityController::class, 'fetchTotalActivityCount'])->name('dashboard.fetch-total-activity-count');
    Route::post('/dashboard/check-checkin-status', [DashboardController::class, 'checkCheckinStatus'])->name('dashboard.check-checkin-status');
    Route::post('/dashboard/update-checkin-status', [DashboardController::class, 'updateCheckinStatus'])->name('dashboard.update-checkin-status');

	/*---------- General Admin Routes ----------*/
    Route::get('/my_profile', [CRMUtilityController::class, 'myProfile'])->name('my_profile');
    Route::post('/my_profile', [CRMUtilityController::class, 'myProfile'])->name('my_profile.update');
    Route::get('/change_password', [CRMUtilityController::class, 'change_password'])->name('change_password');
    Route::post('/change_password', [CRMUtilityController::class, 'change_password'])->name('change_password.update');
    Route::post('/update_action', [CRMUtilityController::class, 'updateAction']);
    Route::post('/approved_action', [CRMUtilityController::class, 'approveAction']);
    Route::post('/process_action', [CRMUtilityController::class, 'processAction']);
    Route::post('/archive_action', [CRMUtilityController::class, 'archiveAction']);
    Route::post('/declined_action', [CRMUtilityController::class, 'declinedAction']);
    Route::post('/delete_action', [CRMUtilityController::class, 'deleteAction']);
    Route::post('/move_action', [CRMUtilityController::class, 'moveAction']);

    // WARNING: Old appointment calendar routes removed - old appointment system deleted
    // These methods don't exist in CRMUtilityController
    // Route::get('/appointments-education', [CRMUtilityController::class, 'appointmentsEducation'])->name('appointments-education'); // REMOVED
    // Route::get('/appointments-jrp', [CRMUtilityController::class, 'appointmentsJrp'])->name('appointments-jrp'); // REMOVED
    // Route::get('/appointments-tourist', [CRMUtilityController::class, 'appointmentsTourist'])->name('appointments-tourist'); // REMOVED
    // Route::get('/appointments-others', [CRMUtilityController::class, 'appointmentsOthers'])->name('appointments-others'); // REMOVED

    Route::post('/add_ckeditior_image', [CRMUtilityController::class, 'addCkeditiorImage'])->name('add_ckeditior_image');
    Route::post('/get_chapters', [CRMUtilityController::class, 'getChapters'])->name('get_chapters');
    // REMOVED: get_states route - State model deleted, no frontend calls this route
    Route::get('/settings/taxes/returnsetting', [CRMUtilityController::class, 'returnsetting'])->name('returnsetting');
    Route::post('/settings/taxes/savereturnsetting', [CRMUtilityController::class, 'returnsetting'])->name('savereturnsetting');
    Route::get('/getassigneeajax', [CRMUtilityController::class, 'getassigneeajax']);
    Route::get('/getpartnerajax', [CRMUtilityController::class, 'getpartnerajax']);
    Route::get('/checkclientexist', [CRMUtilityController::class, 'checkclientexist']);

    Route::get('/notifications/broadcasts/manage', [BroadcastController::class, 'index'])->name('notifications.broadcasts.index');
    /* Legacy broadcast notification links: /broadcasts/{uuid} -> redirect to manage page (fixes 404) */
    Route::get('/broadcasts/{batchUuid}', function (string $batchUuid) {
        return redirect('/notifications/broadcasts/manage?batch=' . urlencode($batchUuid));
    })->where('batchUuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    Route::redirect('/dashboard/active-users', '/dashboard/active-staff', 301);
    Route::get('/dashboard/active-staff', [ActiveStaffController::class, 'index'])->name('dashboard.active-staff');

    Route::prefix('notifications/broadcasts')->name('notifications.broadcasts.')->group(function () {
        Route::post('/send', [BroadcastNotificationAjaxController::class, 'store'])->name('send');
        
        // History routes (specific routes first)
        Route::get('/history', [BroadcastNotificationAjaxController::class, 'history'])->name('history'); // Global history
        Route::get('/my-history', [BroadcastNotificationAjaxController::class, 'myHistory'])->name('my-history'); // My sent broadcasts
        Route::get('/read-history', [BroadcastNotificationAjaxController::class, 'readHistory'])->name('read-history'); // My read broadcasts
        Route::get('/unread', [BroadcastNotificationAjaxController::class, 'unread'])->name('unread');
        
        // Parameterized routes with constraints for extra safety
        Route::get('/{batchUuid}/details', [BroadcastNotificationAjaxController::class, 'details'])
            ->name('details')
            ->where('batchUuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
        Route::post('/{notificationId}/read', [BroadcastNotificationAjaxController::class, 'markAsRead'])
            ->name('read')
            ->where('notificationId', '[0-9]+');
        Route::post('/{notificationId}/start-read-timer', [BroadcastNotificationAjaxController::class, 'startReadTimer'])
            ->name('start-read-timer')
            ->where('notificationId', '[0-9]+');
        Route::get('/{notificationId}/receiver-detail', [BroadcastNotificationAjaxController::class, 'receiverDetail'])
            ->name('receiver-detail')
            ->where('notificationId', '[0-9]+');
        Route::delete('/{batchUuid}', [BroadcastNotificationAjaxController::class, 'delete'])
            ->name('delete')
            ->where('batchUuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    });

    // Staff Login Analytics Routes (was user-login-analytics)
    Route::redirect('/user-login-analytics', '/staff-login-analytics', 301);
    Route::get('/staff-login-analytics', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'index'])->name('staff-login-analytics.index');
    Route::prefix('api/staff-login-analytics')->name('api.staff-login-analytics.')->group(function () {
        Route::get('/daily', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'daily'])->name('daily');
        Route::get('/weekly', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'weekly'])->name('weekly');
        Route::get('/monthly', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'monthly'])->name('monthly');
        Route::get('/hourly', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'hourly'])->name('hourly');
        Route::get('/summary', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'summary'])->name('summary');
        Route::get('/top-staff', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'topStaff'])->name('top-staff');
        Route::get('/trends', [\App\Http\Controllers\CRM\StaffLoginAnalyticsController::class, 'trends'])->name('trends');
    });

    /*---------- Reports Routes ----------*/
    Route::get('/reports/visaexpires', [ReportController::class, 'visaexpires'])->name('reports.visaexpires');

	/*---------- CRM & Staff Management Routes ----------*/
    // All staff management routes moved to routes/adminconsole.php
    // - Staff management: Use adminconsole.staff routes
    // - Clients: Use adminconsole.system.clients routes (ClientController)
    // - Staff types/roles: Use adminconsole.system.roles routes

    /*---------- Leads Management (Modern Laravel Syntax) ----------*/
    // Lead CRUD operations
    Route::prefix('leads')->name('leads.')->group(function () {
        // List & Detail
        Route::get('/', [LeadController::class, 'index'])->name('index');
        Route::get('/detail/{id}', [LeadController::class, 'detail'])->name('detail');
        Route::get('/history/{id}', [LeadController::class, 'history'])->name('history');
        
        // Create
        Route::get('/create', [LeadController::class, 'create'])->name('create');
        Route::post('/store', [LeadController::class, 'store'])->name('store');
        Route::get('/check-contact-match', [LeadController::class, 'checkContactMatch'])->name('check.contact.match');
        
        // Edit & Update (RESTful pattern)
        Route::get('/{id}/edit', [LeadController::class, 'edit'])->name('edit');
        Route::put('/{id}', [LeadController::class, 'update'])->name('update');
        Route::patch('/{id}', [LeadController::class, 'update'])->name('patch');
        
        // Assignment operations
        Route::post('/assign', [LeadAssignmentController::class, 'assign'])->name('assign');
        Route::post('/bulk-assign', [LeadAssignmentController::class, 'bulkAssign'])->name('bulk_assign');
        Route::get('/assignable-staff', [LeadAssignmentController::class, 'getAssignableStaff'])->name('assignable_staff');
        
        // Conversion operations
        Route::get('/convert', [LeadConversionController::class, 'convertToClient'])->name('convert');
        Route::post('/convert-single', [LeadConversionController::class, 'convertSingleLead'])->name('convert_single');
        Route::post('/bulk-convert', [LeadConversionController::class, 'bulkConvertToClient'])->name('bulk_convert');
        Route::get('/conversion-stats', [LeadConversionController::class, 'getConversionStats'])->name('conversion_stats');
        
        // Archive operations
        Route::post('/archive/{id}', [LeadController::class, 'archive'])->name('archive');
        
        // Analytics (Admin/Team Lead only)
        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/', [LeadAnalyticsController::class, 'index'])->name('index');
            Route::get('/trends', [LeadAnalyticsController::class, 'getTrends'])->name('trends');
            Route::get('/export', [LeadAnalyticsController::class, 'export'])->name('export');
            Route::post('/compare-agents', [LeadAnalyticsController::class, 'compareAgents'])->name('compare');
        });
        
        // Legacy routes (deprecated functionality)
        Route::get('/notes/delete/{id}', [LeadController::class, 'leaddeleteNotes'])->name('notes.delete');
        Route::get('/pin/{id}', [LeadController::class, 'leadPin'])->name('pin');
    });
    
    // Global route (outside leads prefix) - kept for backward compatibility
    Route::get('/get-notedetail', [LeadController::class, 'getnotedetail'])->name('get-notedetail');

	/*---------- Email Templates ----------*/
	// DISABLED: email_templates table has been deleted
    // Route::get('/email_templates', [EmailTemplateController::class, 'index'])->name('email.index');
    // Route::get('/email_templates/create', [EmailTemplateController::class, 'create'])->name('email.create');
    // Route::post('/email_templates/store', [EmailTemplateController::class, 'store'])->name('email.store');
    // Route::get('/edit_email_template/{id}', [EmailTemplateController::class, 'editEmailTemplate'])->name('edit_email_template');
    // Route::post('/edit_email_template', [EmailTemplateController::class, 'editEmailTemplate'])->name('edit_email_template.update');

	/*---------- API Settings ----------*/
    Route::get('/api-key', [CRMUtilityController::class, 'editapi'])->name('api');
    Route::post('/api-key', [CRMUtilityController::class, 'editapi'])->name('api.update');

	/*--------------------------------------------------
	| SECTION: Client Management Routes
	|--------------------------------------------------*/
	// All client routes moved to routes/clients.php
	// Includes: CRUD, documents, verification, invoices, EOI/ROI, notes, agreements
	require __DIR__ . '/clients.php';

	/*--------------------------------------------------
	| SECTION: Applications & Office Visits Routes
	|--------------------------------------------------*/
	// Client Portal, Office Visits, and Booking Appointments routes
	require __DIR__ . '/client_portal.php';

	/*---------- Front-Desk Check-In Wizard ----------*/
	Route::prefix('front-desk/checkin')->name('front-desk.checkin.')->group(function () {
		Route::get('/', [\App\Http\Controllers\CRM\FrontDeskCheckInController::class, 'index'])->name('index');
		Route::post('/lookup', [\App\Http\Controllers\CRM\FrontDeskCheckInController::class, 'lookupContact'])->name('lookup');
		Route::post('/appointments', [\App\Http\Controllers\CRM\FrontDeskCheckInController::class, 'getAppointments'])->name('appointments');
		Route::post('/submit', [\App\Http\Controllers\CRM\FrontDeskCheckInController::class, 'submit'])->name('submit');
		Route::post('/create-lead', [\App\Http\Controllers\CRM\FrontDeskCheckInController::class, 'createLead'])->name('create-lead');
	});

	/*---------- Audit Logs ----------*/
	Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('auditlogs.index');

	/*---------- Notifications ----------*/
	Route::get('/fetch-notification', [CRMUtilityController::class, 'fetchnotification']);
	Route::get('/fetch-messages', [CRMUtilityController::class, 'fetchmessages']);
	Route::get('/fetch-office-visit-notifications', [CRMUtilityController::class, 'fetchOfficeVisitNotifications']);
	Route::post('/mark-notification-seen', [CRMUtilityController::class, 'markNotificationSeen']);
	Route::get('/check-checkin-status', [DashboardController::class, 'checkCheckinStatus']);
	Route::post('/update-checkin-status', [DashboardController::class, 'updateCheckinStatus']);
	Route::get('/all-notifications', [CRMUtilityController::class, 'allnotification']);
	Route::get('/fetch-InPersonWaitingCount', [CRMUtilityController::class, 'fetchInPersonWaitingCount']);
	Route::get('/fetch-TotalActivityCount', [CRMUtilityController::class, 'fetchTotalActivityCount']);

	/*---------- Assignee Module ----------*/
	// Explicit routes for assignee module (replaced resource route to avoid deprecated methods)
	Route::get('/assignee', [AssigneeController::class, 'index'])->name('assignee.index');
	Route::delete('/assignee/{assignee}', [AssigneeController::class, 'destroy'])->name('assignee.destroy');
        Route::get('/assignee-completed', [AssigneeController::class, 'completed']); //completed list only

        Route::post('/update-action-completed', [AssigneeController::class, 'updateActionCompleted']); //update action to be completed
        Route::post('/update-action-not-completed', [AssigneeController::class, 'updateActionNotCompleted']); //update action to be not completed

        Route::get('/assigned_by_me', [AssigneeController::class, 'assigned_by_me'])->name('assignee.assigned_by_me'); //assigned by me
        Route::get('/assigned_to_me', [AssigneeController::class, 'assigned_to_me'])->name('assignee.assigned_to_me'); //assigned to me

        Route::delete('/destroy_by_me/{note_id}', [AssigneeController::class, 'destroy_by_me'])->name('assignee.destroy_by_me'); //assigned by me
        Route::delete('/destroy_to_me/{note_id}', [AssigneeController::class, 'destroy_to_me'])->name('assignee.destroy_to_me'); //assigned to me
        Route::get('/action_completed', [AssigneeController::class, 'action_completed'])->name('assignee.action_completed'); //action completed


        Route::delete('/destroy_activity/{note_id}', [AssigneeController::class, 'destroy_activity'])->name('assignee.destroy_activity'); //delete activity
        Route::delete('/destroy_complete_activity/{note_id}', [AssigneeController::class, 'destroy_complete_activity'])->name('assignee.destroy_complete_activity'); //delete completed activity

	/*---------- Task Management ----------*/
	// Task routes for email and contact uniqueness
        Route::post('/is_email_unique', [LeadController::class, 'is_email_unique']);
        Route::post('/is_contactno_unique', [LeadController::class, 'is_contactno_unique']);

	// Activity management
        Route::post('/extenddeadlinedate', [CRMUtilityController::class, 'extenddeadlinedate']);
        Route::post('/update-stage', [CRMUtilityController::class, 'updateStage']);

	// Get assigne list
	Route::post('/get_assignee_list', [AssigneeController::class, 'get_assignee_list']);

	// Update action
        Route::post('/update-action', [AssigneeController::class, 'updateAction']);
        Route::get('/action/counts', [AssigneeController::class, 'getActionCounts'])->name('action.counts');

	// For datatable - Action list routes
	Route::get('/action', [AssigneeController::class, 'action'])->name('assignee.action');
	Route::get('/action/list', [AssigneeController::class, 'getAction'])->name('action.list');

	/*---------- Matter Office Management ----------*/
	Route::post('/matters/update-office', [ClientsController::class, 'updateMatterOffice'])->name('matters.update-office');

	/*---------- End of Admin Routes ----------*/


	});

/*
| Reverb lab: outside the block above so unauthenticated visitors can be
| signed in via .env (REVERB_ACCESS_*) before auth:admin runs.
*/
Route::middleware(['reverb.lab.env.auto', 'auth:admin'])->group(function () {
    Route::get('/reverb-messaging-test', [ReverbMessagingLabController::class, 'index'])->name('reverb-messaging-lab.index');
    Route::post('/reverb-messaging-test/resolve-matter', [ReverbMessagingLabController::class, 'resolveMatter'])->name('reverb-messaging-lab.resolve-matter');
});

/*--------------------------------------------------
| SECTION: Document Signature Routes (Admin & Public)
|--------------------------------------------------*/
// Admin document management and public client signing
// Loaded outside admin group to allow proper prefix handling
require __DIR__ . '/documents.php';

/*--------------------------------------------------
| SECTION: Public Email Verification
|--------------------------------------------------*/
// Public email verification route loaded from clients.php

// Public email verification route - no authentication required
Route::get('/verify-email/{token}', [EmailVerificationController::class, 'verifyEmail'])->name('clients.email.verify');

/*--------------------------------------------------
|| SECTION: Public Client EOI Confirmation Routes
||--------------------------------------------------*/
// These routes are accessible without authentication for client confirmation
Route::get('/client/eoi/confirm/{token}', [\App\Http\Controllers\CRM\EoiRoiSheetController::class, 'showConfirmationPage'])->name('client.eoi.confirm');
Route::get('/client/eoi/amend/{token}', [\App\Http\Controllers\CRM\EoiRoiSheetController::class, 'showAmendmentPage'])->name('client.eoi.amend');
Route::post('/client/eoi/process/{token}', [\App\Http\Controllers\CRM\EoiRoiSheetController::class, 'processClientConfirmation'])->name('client.eoi.process');
Route::get('/client/eoi/success/{token}', [\App\Http\Controllers\CRM\EoiRoiSheetController::class, 'showSuccessPage'])->name('client.eoi.success');
