<?php

namespace App\Support;

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\CRM\ActiveStaffController;
use App\Http\Controllers\CRM\AssigneeController;
use App\Http\Controllers\CRM\AuditLogController;
use App\Http\Controllers\CRM\BroadcastController;
use App\Http\Controllers\CRM\BroadcastNotificationAjaxController;
use App\Http\Controllers\CRM\ClientsController;
use App\Http\Controllers\CRM\CRMUtilityController;
use App\Http\Controllers\CRM\DashboardController;
use App\Http\Controllers\CRM\EmailVerificationController;
use App\Http\Controllers\CRM\EoiRoiSheetController;
use App\Http\Controllers\CRM\FrontDeskCheckInController;
use App\Http\Controllers\CRM\Leads\LeadAnalyticsController;
use App\Http\Controllers\CRM\Leads\LeadAssignmentController;
use App\Http\Controllers\CRM\Leads\LeadController;
use App\Http\Controllers\CRM\Leads\LeadConversionController;
use App\Http\Controllers\CRM\ReportController;
use App\Http\Controllers\CRM\ReverbMessagingLabController;
use App\Http\Controllers\CRM\StaffLoginAnalyticsController;
use App\Http\Controllers\ExceptionController;
use App\Http\Controllers\Public\PublicAppointmentActionController;
use App\Http\Controllers\Public\PublicAppointmentPaymentController;
use App\Http\Controllers\Public\PublicClientDetailVerificationController;
use App\Http\Controllers\Public\PublicLeadInquiryController;
use App\Http\Controllers\Public\PublicPhoneCallController;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Routing\Router;

/**
 * Registers routes/web.php definitions via the router instance so IDEs can
 * resolve concrete Laravel types without the Route/Artisan facades.
 */
final class RegisterWebRoutes
{
    private mixed $router;

    public function __construct(
        mixed $router,
        private string $routesDirectory,
    ) {
        $this->router = $router;
    }

    public static function registerFromPath(string $routesDirectory): void
    {
        /** @disregard P1009 */
        $container = Container::getInstance();
        /** @disregard P1009 */
        $router = $container->make(Router::class);
        /** @disregard P1009 */
        assert($router instanceof Router);

        (new self($router, $routesDirectory))->register();
    }

    public function register(): void
    {
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
        $this->router->get('/', function () {
            return $this->redirector()->route('crm.login');
        });

        /*--------------------------------------------------
        | Public lead inquiry (no auth)
        |--------------------------------------------------*/
        $publicLeadPath = $this->config()->get('public_lead_form.path', 'lead-client-info-form');
        $this->router->middleware('throttle:30,1')->group(function () use ($publicLeadPath) {
            $this->router->get("/{$publicLeadPath}", [PublicLeadInquiryController::class, 'showForm'])->name('public.lead-inquiry');
        });
        $this->router->post("/{$publicLeadPath}", [PublicLeadInquiryController::class, 'submit'])
            ->middleware('throttle:10,1')
            ->name('public.lead-inquiry.submit');
        $this->router->post("/{$publicLeadPath}/confirm", [PublicLeadInquiryController::class, 'confirmUpdate'])
            ->middleware('throttle:10,1')
            ->name('public.lead-inquiry.confirm');
        $this->router->post("/{$publicLeadPath}/cancel", [PublicLeadInquiryController::class, 'cancelUpdate'])
            ->middleware('throttle:10,1')
            ->name('public.lead-inquiry.cancel');

        $this->router->middleware('throttle:60,1')->get('/phone-call', PublicPhoneCallController::class)->name('public.phone-call');

        $this->router->middleware('throttle:60,1')->group(function () {
            $this->router->get('/appointment/pay/{token}', [PublicAppointmentPaymentController::class, 'show'])->name('public.appointment.pay');
            $this->router->post('/appointment/pay/{token}/intent', [PublicAppointmentPaymentController::class, 'createIntent'])->name('public.appointment.pay.intent');
            $this->router->post('/appointment/pay/{token}/complete', [PublicAppointmentPaymentController::class, 'complete'])->name('public.appointment.pay.complete');
        });

        $this->router->middleware('throttle:20,1')->group(function () {
            $this->router->get('/appointment/{appointment}/cancel', [PublicAppointmentActionController::class, 'showCancel'])
                ->whereNumber('appointment')
                ->name('public.appointment.cancel.show');
            $this->router->post('/appointment/{appointment}/cancel', [PublicAppointmentActionController::class, 'cancel'])
                ->whereNumber('appointment')
                ->name('public.appointment.cancel.submit');
            $this->router->get('/appointment/{appointment}/confirm', [PublicAppointmentActionController::class, 'showConfirm'])
                ->whereNumber('appointment')
                ->name('public.appointment.confirm.show');
            $this->router->post('/appointment/{appointment}/confirm', [PublicAppointmentActionController::class, 'confirm'])
                ->whereNumber('appointment')
                ->name('public.appointment.confirm.submit');
            $this->router->get('/appointment/{appointment}/reschedule', [PublicAppointmentActionController::class, 'showReschedule'])
                ->whereNumber('appointment')
                ->name('public.appointment.reschedule.show');
            $this->router->post('/appointment/{appointment}/reschedule', [PublicAppointmentActionController::class, 'reschedule'])
                ->whereNumber('appointment')
                ->name('public.appointment.reschedule.submit');
            $this->router->get('/appointment/{appointment}/reschedule/availability', [PublicAppointmentActionController::class, 'availability'])
                ->whereNumber('appointment')
                ->name('public.appointment.reschedule.availability');
            $this->router->post('/appointment/{appointment}/reschedule/slots', [PublicAppointmentActionController::class, 'slots'])
                ->whereNumber('appointment')
                ->name('public.appointment.reschedule.slots');
        });

        // Cache clearing route - protected with authentication
        $this->router->get('/clear-cache', function () {
            $this->artisan()->call('config:clear');
            $this->artisan()->call('view:clear');
            $this->artisan()->call('route:clear');
            $this->artisan()->call('route:cache');

            return $this->responseFactory()->json([
                'success' => true,
                'message' => 'Cache cleared successfully',
            ]);
        })->middleware('auth');

        /*--------------------------------------------------
        | SECTION: Exception Handling
        |--------------------------------------------------*/
        $this->router->get('/exception', [ExceptionController::class, 'index'])->name('exception.index');
        $this->router->post('/exception', [ExceptionController::class, 'index'])->name('exception.store');

        /*--------------------------------------------------
        | SECTION: Authentication Routes
        |--------------------------------------------------*/
        // Auth::routes(); // Disabled - Using custom admin login at /admin and API login at /api/login instead

        /*--------------------------------------------------
        | SECTION: Admin Console Routes
        |--------------------------------------------------*/
        require $this->routesDirectory.'/adminconsole.php';

        /*--------------------------------------------------
        | SECTION: Authentication Routes
        |--------------------------------------------------*/
        // CRM authentication routes (no /admin prefix)
        $this->router->get('/login', [AdminLoginController::class, 'showLoginForm'])->name('crm.login');
        $this->router->post('/login', [AdminLoginController::class, 'login'])->name('crm.login.post');
        $this->router->post('/logout', [AdminLoginController::class, 'logout'])->name('crm.logout');
        $this->router->get('/logout', function () {
            return $this->redirector()->route('crm.login');
        })->name('crm.logout.get');

        /*--------------------------------------------------
        | SECTION: CRM Application Routes (Protected)
        |--------------------------------------------------*/
        // Main CRM routes at root level with auth:admin middleware
        $this->router->middleware(['auth:admin'])->group(function () {

            /* ---------- Dashboard Routes ---------- */
            $this->router->get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
            $this->router->get('/dashboard/calendar-events', [DashboardController::class, 'calendarEvents'])->name('dashboard.calendar-events');
            $this->router->get('/dashboard/matters-fragment', [DashboardController::class, 'mattersFragment'])->name('dashboard.matters-fragment');
            $this->router->get('/dashboard/cases-fragment', [DashboardController::class, 'casesFragment'])->name('dashboard.cases-fragment');
            $this->router->get('/dashboard/workload-drilldown', [DashboardController::class, 'workloadDrilldown'])->name('dashboard.workload-drilldown');
            $this->router->post('/dashboard/column-preferences', [DashboardController::class, 'saveColumnPreferences'])->name('dashboard.column-preferences');
            $this->router->post('/dashboard/extend-deadline', [DashboardController::class, 'extendDeadlineDate'])->name('dashboard.extend-deadline');
            $this->router->post('/dashboard/update-action-completed', [DashboardController::class, 'updateActionCompleted'])->name('dashboard.update-action-completed');
            $this->router->get('/dashboard/fetch-notifications', [CRMUtilityController::class, 'fetchnotification'])->name('dashboard.fetch-notifications');
            $this->router->get('/dashboard/fetch-office-visit-notifications', [CRMUtilityController::class, 'fetchOfficeVisitNotifications'])->name('dashboard.fetch-office-visit-notifications');
            $this->router->post('/dashboard/mark-notification-seen', [CRMUtilityController::class, 'markNotificationSeen'])->name('dashboard.mark-notification-seen');
            $this->router->get('/dashboard/fetch-visa-expiry-messages', [CRMUtilityController::class, 'fetchvisaexpirymessages'])->name('dashboard.fetch-visa-expiry-messages');
            $this->router->get('/dashboard/fetch-in-person-waiting-count', [CRMUtilityController::class, 'fetchInPersonWaitingCount'])->name('dashboard.fetch-in-person-waiting-count');
            $this->router->get('/dashboard/fetch-total-activity-count', [CRMUtilityController::class, 'fetchTotalActivityCount'])->name('dashboard.fetch-total-activity-count');
            $this->router->post('/dashboard/check-checkin-status', [DashboardController::class, 'checkCheckinStatus'])->name('dashboard.check-checkin-status');
            $this->router->post('/dashboard/update-checkin-status', [DashboardController::class, 'updateCheckinStatus'])->name('dashboard.update-checkin-status');

            /* ---------- General Admin Routes ---------- */
            $this->router->get('/my_profile', [CRMUtilityController::class, 'myProfile'])->name('my_profile');
            $this->router->post('/my_profile', [CRMUtilityController::class, 'myProfile'])->name('my_profile.update');
            $this->router->get('/change_password', [CRMUtilityController::class, 'change_password'])->name('change_password');
            $this->router->post('/change_password', [CRMUtilityController::class, 'change_password'])->name('change_password.update');
            $this->router->post('/update_action', [CRMUtilityController::class, 'updateAction']);
            $this->router->post('/approved_action', [CRMUtilityController::class, 'approveAction']);
            $this->router->post('/process_action', [CRMUtilityController::class, 'processAction']);
            $this->router->post('/archive_action', [CRMUtilityController::class, 'archiveAction']);
            $this->router->post('/declined_action', [CRMUtilityController::class, 'declinedAction']);
            $this->router->post('/delete_action', [CRMUtilityController::class, 'deleteAction']);
            $this->router->post('/move_action', [CRMUtilityController::class, 'moveAction']);

            // WARNING: Old appointment calendar routes removed - old appointment system deleted
            // These methods don't exist in CRMUtilityController
            // $this->router->get('/appointments-education', [CRMUtilityController::class, 'appointmentsEducation'])->name('appointments-education'); // REMOVED
            // $this->router->get('/appointments-jrp', [CRMUtilityController::class, 'appointmentsJrp'])->name('appointments-jrp'); // REMOVED
            // $this->router->get('/appointments-tourist', [CRMUtilityController::class, 'appointmentsTourist'])->name('appointments-tourist'); // REMOVED
            // $this->router->get('/appointments-others', [CRMUtilityController::class, 'appointmentsOthers'])->name('appointments-others'); // REMOVED

            $this->router->post('/add_ckeditior_image', [CRMUtilityController::class, 'addCkeditiorImage'])->name('add_ckeditior_image');
            $this->router->post('/get_chapters', [CRMUtilityController::class, 'getChapters'])->name('get_chapters');
            // REMOVED: get_states route - State model deleted, no frontend calls this route
            $this->router->get('/settings/taxes/returnsetting', [CRMUtilityController::class, 'returnsetting'])->name('returnsetting');
            $this->router->post('/settings/taxes/savereturnsetting', [CRMUtilityController::class, 'returnsetting'])->name('savereturnsetting');
            $this->router->get('/getassigneeajax', [CRMUtilityController::class, 'getassigneeajax']);
            $this->router->get('/getpartnerajax', [CRMUtilityController::class, 'getpartnerajax']);
            $this->router->get('/checkclientexist', [CRMUtilityController::class, 'checkclientexist']);

            $this->router->get('/notifications/broadcasts/manage', [BroadcastController::class, 'index'])->name('notifications.broadcasts.index');
            /* Legacy broadcast notification links: /broadcasts/{uuid} -> redirect to manage page (fixes 404) */
            $this->router->get('/broadcasts/{batchUuid}', function (string $batchUuid) {
                return $this->redirector()->to('/notifications/broadcasts/manage?batch='.urlencode($batchUuid));
            })->where('batchUuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            $this->router->redirect('/dashboard/active-users', '/dashboard/active-staff', 301);
            $this->router->get('/dashboard/active-staff', [ActiveStaffController::class, 'index'])->name('dashboard.active-staff');

            $this->router->prefix('notifications/broadcasts')->name('notifications.broadcasts.')->group(function () {
                $this->router->post('/send', [BroadcastNotificationAjaxController::class, 'store'])->name('send');

                // History routes (specific routes first)
                $this->router->get('/history', [BroadcastNotificationAjaxController::class, 'history'])->name('history'); // Global history
                $this->router->get('/my-history', [BroadcastNotificationAjaxController::class, 'myHistory'])->name('my-history'); // My sent broadcasts
                $this->router->get('/read-history', [BroadcastNotificationAjaxController::class, 'readHistory'])->name('read-history'); // My read broadcasts
                $this->router->get('/unread', [BroadcastNotificationAjaxController::class, 'unread'])->name('unread');

                // Parameterized routes with constraints for extra safety
                $this->router->get('/{batchUuid}/details', [BroadcastNotificationAjaxController::class, 'details'])
                    ->name('details')
                    ->where('batchUuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
                $this->router->post('/{notificationId}/read', [BroadcastNotificationAjaxController::class, 'markAsRead'])
                    ->name('read')
                    ->where('notificationId', '[0-9]+');
                $this->router->post('/{notificationId}/start-read-timer', [BroadcastNotificationAjaxController::class, 'startReadTimer'])
                    ->name('start-read-timer')
                    ->where('notificationId', '[0-9]+');
                $this->router->get('/{notificationId}/receiver-detail', [BroadcastNotificationAjaxController::class, 'receiverDetail'])
                    ->name('receiver-detail')
                    ->where('notificationId', '[0-9]+');
                $this->router->delete('/{batchUuid}', [BroadcastNotificationAjaxController::class, 'delete'])
                    ->name('delete')
                    ->where('batchUuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            });

            // Staff Login Analytics Routes (was user-login-analytics)
            $this->router->redirect('/user-login-analytics', '/staff-login-analytics', 301);
            $this->router->get('/staff-login-analytics', [StaffLoginAnalyticsController::class, 'index'])->name('staff-login-analytics.index');
            $this->router->prefix('api/staff-login-analytics')->name('api.staff-login-analytics.')->group(function () {
                $this->router->get('/daily', [StaffLoginAnalyticsController::class, 'daily'])->name('daily');
                $this->router->get('/weekly', [StaffLoginAnalyticsController::class, 'weekly'])->name('weekly');
                $this->router->get('/monthly', [StaffLoginAnalyticsController::class, 'monthly'])->name('monthly');
                $this->router->get('/hourly', [StaffLoginAnalyticsController::class, 'hourly'])->name('hourly');
                $this->router->get('/summary', [StaffLoginAnalyticsController::class, 'summary'])->name('summary');
                $this->router->get('/top-staff', [StaffLoginAnalyticsController::class, 'topStaff'])->name('top-staff');
                $this->router->get('/trends', [StaffLoginAnalyticsController::class, 'trends'])->name('trends');
            });

            /* ---------- Reports Routes ---------- */
            $this->router->get('/reports/visaexpires', [ReportController::class, 'visaexpires'])->name('reports.visaexpires');

            /* ---------- CRM & Staff Management Routes ---------- */
            // All staff management routes moved to routes/adminconsole.php
            // - Staff management: Use adminconsole.staff routes
            // - Clients: Use adminconsole.system.clients routes (ClientController)
            // - Staff types/roles: Use adminconsole.system.roles routes

            /* ---------- Leads Management (Modern Laravel Syntax) ---------- */
            // Lead CRUD operations
            $this->router->prefix('leads')->name('leads.')->group(function () {
                // List & Detail
                $this->router->get('/', [LeadController::class, 'index'])->name('index');
                $this->router->get('/export-list', [LeadController::class, 'exportList'])->name('export-list');
                $this->router->get('/detail/{id}', [LeadController::class, 'detail'])->name('detail');
                $this->router->get('/history/{id}', [LeadController::class, 'history'])->name('history');

                // Create
                $this->router->get('/create', [LeadController::class, 'create'])->name('create');
                $this->router->post('/store', [LeadController::class, 'store'])->name('store');
                $this->router->get('/check-contact-match', [LeadController::class, 'checkContactMatch'])->name('check.contact.match');

                // Edit & Update (RESTful pattern)
                $this->router->get('/{id}/edit', [LeadController::class, 'edit'])->name('edit');
                $this->router->put('/{id}', [LeadController::class, 'update'])->name('update');
                $this->router->patch('/{id}', [LeadController::class, 'update'])->name('patch');

                // Assignment operations
                $this->router->post('/assign', [LeadAssignmentController::class, 'assign'])->name('assign');
                $this->router->post('/bulk-assign', [LeadAssignmentController::class, 'bulkAssign'])->name('bulk_assign');
                $this->router->get('/assignable-staff', [LeadAssignmentController::class, 'getAssignableStaff'])->name('assignable_staff');

                // Conversion operations (no GET mass-convert — that endpoint converted up to 500 leads)
                $this->router->post('/convert-single', [LeadConversionController::class, 'convertSingleLead'])->name('convert_single');
                $this->router->post('/bulk-convert', [LeadConversionController::class, 'bulkConvertToClient'])->name('bulk_convert');
                $this->router->get('/conversion-stats', [LeadConversionController::class, 'getConversionStats'])->name('conversion_stats');

                // Archive operations
                $this->router->post('/archive/{id}', [LeadController::class, 'archive'])->name('archive');

                // Legal CRM handoff (instant sync only; bit 1 = synced)
                $this->router->post('/send-to-legal-crm/{id}', [LeadController::class, 'sendToLegalCrm'])->name('send_to_legal_crm');

                // Analytics (Admin/Team Lead only)
                $this->router->prefix('analytics')->name('analytics.')->group(function () {
                    $this->router->get('/', [LeadAnalyticsController::class, 'index'])->name('index');
                    $this->router->get('/trends', [LeadAnalyticsController::class, 'getTrends'])->name('trends');
                    $this->router->get('/export', [LeadAnalyticsController::class, 'export'])->name('export');
                    $this->router->post('/compare-agents', [LeadAnalyticsController::class, 'compareAgents'])->name('compare');
                });

                // Legacy routes (deprecated functionality)
                $this->router->get('/notes/delete/{id}', [LeadController::class, 'leaddeleteNotes'])->name('notes.delete');
                $this->router->get('/pin/{id}', [LeadController::class, 'leadPin'])->name('pin');
            });

            // Global route (outside leads prefix) - kept for backward compatibility
            $this->router->get('/get-notedetail', [LeadController::class, 'getnotedetail'])->name('get-notedetail');

            /* ---------- Email Templates ---------- */
            // DISABLED: email_templates table has been deleted
            // $this->router->get('/email_templates', [EmailTemplateController::class, 'index'])->name('email.index');
            // $this->router->get('/email_templates/create', [EmailTemplateController::class, 'create'])->name('email.create');
            // $this->router->post('/email_templates/store', [EmailTemplateController::class, 'store'])->name('email.store');
            // $this->router->get('/edit_email_template/{id}', [EmailTemplateController::class, 'editEmailTemplate'])->name('edit_email_template');
            // $this->router->post('/edit_email_template', [EmailTemplateController::class, 'editEmailTemplate'])->name('edit_email_template.update');

            /* ---------- API Settings ---------- */
            $this->router->get('/api-key', [CRMUtilityController::class, 'editapi'])->name('api');
            $this->router->post('/api-key', [CRMUtilityController::class, 'editapi'])->name('api.update');

            /*--------------------------------------------------
        	| SECTION: Client Management Routes
        	|--------------------------------------------------*/
            // All client routes moved to routes/clients.php
            // Includes: CRUD, documents, verification, invoices, EOI/ROI, notes, agreements
            require $this->routesDirectory.'/clients.php';

            /*--------------------------------------------------
        	| SECTION: Applications & Office Visits Routes
        	|--------------------------------------------------*/
            // Client Portal, Office Visits, and Booking Appointments routes
            require $this->routesDirectory.'/client_portal.php';

            /* ---------- Front-Desk Check-In Wizard ---------- */
            $this->router->prefix('front-desk/checkin')->name('front-desk.checkin.')->group(function () {
                $this->router->get('/', [FrontDeskCheckInController::class, 'index'])->name('index');
                $this->router->post('/lookup', [FrontDeskCheckInController::class, 'lookupContact'])->name('lookup');
                $this->router->post('/appointments', [FrontDeskCheckInController::class, 'getAppointments'])->name('appointments');
                $this->router->post('/submit', [FrontDeskCheckInController::class, 'submit'])->name('submit');
                $this->router->post('/create-lead', [FrontDeskCheckInController::class, 'createLead'])->name('create-lead');
            });

            /* ---------- Audit Logs ---------- */
            $this->router->get('/audit-logs', [AuditLogController::class, 'index'])->name('auditlogs.index');

            /* ---------- Notifications ---------- */
            $this->router->get('/fetch-notification', [CRMUtilityController::class, 'fetchnotification']);
            $this->router->get('/fetch-messages', [CRMUtilityController::class, 'fetchmessages']);
            $this->router->get('/fetch-office-visit-notifications', [CRMUtilityController::class, 'fetchOfficeVisitNotifications']);
            $this->router->post('/mark-notification-seen', [CRMUtilityController::class, 'markNotificationSeen']);
            $this->router->get('/check-checkin-status', [DashboardController::class, 'checkCheckinStatus']);
            $this->router->post('/update-checkin-status', [DashboardController::class, 'updateCheckinStatus']);
            $this->router->get('/all-notifications', [CRMUtilityController::class, 'allnotification']);
            $this->router->get('/fetch-InPersonWaitingCount', [CRMUtilityController::class, 'fetchInPersonWaitingCount']);
            $this->router->get('/fetch-TotalActivityCount', [CRMUtilityController::class, 'fetchTotalActivityCount']);

            /* ---------- Assignee Module ---------- */
            // Explicit routes for assignee module (replaced resource route to avoid deprecated methods)
            $this->router->get('/assignee', [AssigneeController::class, 'index'])->name('assignee.index');
            $this->router->delete('/assignee/{assignee}', [AssigneeController::class, 'destroy'])->name('assignee.destroy');
            $this->router->get('/assignee-completed', [AssigneeController::class, 'completed']); // completed list only

            $this->router->post('/update-action-completed', [AssigneeController::class, 'updateActionCompleted']); // update action to be completed
            $this->router->post('/update-action-not-completed', [AssigneeController::class, 'updateActionNotCompleted']); // update action to be not completed

            $this->router->get('/assigned_by_me', [AssigneeController::class, 'assigned_by_me'])->name('assignee.assigned_by_me'); // assigned by me
            $this->router->get('/assigned_to_me', [AssigneeController::class, 'assigned_to_me'])->name('assignee.assigned_to_me'); // assigned to me

            $this->router->delete('/destroy_by_me/{note_id}', [AssigneeController::class, 'destroy_by_me'])->name('assignee.destroy_by_me'); // assigned by me
            $this->router->delete('/destroy_to_me/{note_id}', [AssigneeController::class, 'destroy_to_me'])->name('assignee.destroy_to_me'); // assigned to me
            $this->router->get('/action_completed', [AssigneeController::class, 'action_completed'])->name('assignee.action_completed'); // action completed

            $this->router->delete('/destroy_activity/{note_id}', [AssigneeController::class, 'destroy_activity'])->name('assignee.destroy_activity'); // delete activity
            $this->router->delete('/destroy_complete_activity/{note_id}', [AssigneeController::class, 'destroy_complete_activity'])->name('assignee.destroy_complete_activity'); // delete completed activity

            /* ---------- Task Management ---------- */
            // Task routes for email and contact uniqueness
            $this->router->post('/is_email_unique', [LeadController::class, 'is_email_unique']);
            $this->router->post('/is_contactno_unique', [LeadController::class, 'is_contactno_unique']);

            // Activity management
            $this->router->post('/extenddeadlinedate', [CRMUtilityController::class, 'extenddeadlinedate']);
            $this->router->post('/update-stage', [CRMUtilityController::class, 'updateStage']);

            // Get assigne list
            $this->router->post('/get_assignee_list', [AssigneeController::class, 'get_assignee_list']);

            // Update action
            $this->router->post('/update-action', [AssigneeController::class, 'updateAction']);
            $this->router->get('/action/counts', [AssigneeController::class, 'getActionCounts'])->name('action.counts');

            // For datatable - Action list routes
            $this->router->get('/action', [AssigneeController::class, 'action'])->name('assignee.action');
            $this->router->get('/action/list', [AssigneeController::class, 'getAction'])->name('action.list');

            /* ---------- Matter Office Management ---------- */
            $this->router->post('/matters/update-office', [ClientsController::class, 'updateMatterOffice'])->name('matters.update-office');

            /* ---------- End of Admin Routes ---------- */

        });

        /*
        | Reverb lab: outside the block above so unauthenticated visitors can be
        | signed in via .env (REVERB_ACCESS_*) before auth:admin runs.
        */
        $this->router->middleware(['reverb.lab.env.auto', 'auth:admin'])->group(function () {
            $this->router->get('/reverb-messaging-test', [ReverbMessagingLabController::class, 'index'])->name('reverb-messaging-lab.index');
            $this->router->post('/reverb-messaging-test/resolve-matter', [ReverbMessagingLabController::class, 'resolveMatter'])->name('reverb-messaging-lab.resolve-matter');
        });

        /*--------------------------------------------------
        | SECTION: Document Signature Routes (Admin & Public)
        |--------------------------------------------------*/
        // Admin document management and public client signing
        // Loaded outside admin group to allow proper prefix handling
        require $this->routesDirectory.'/documents.php';

        /*--------------------------------------------------
        | SECTION: Public Email Verification
        |--------------------------------------------------*/
        // Public email verification route loaded from clients.php

        // Public email verification route - no authentication required
        $this->router->get('/verify-email/{token}', [EmailVerificationController::class, 'verifyEmail'])->name('clients.email.verify');

        $this->router->middleware('throttle:20,1')->group(function () {
            $this->router->get('/verify-details/{token}', [PublicClientDetailVerificationController::class, 'show'])
                ->where('token', '[A-Za-z0-9]{32,128}')
                ->name('public.client-detail-verification.show');
            $this->router->post('/verify-details/{token}', [PublicClientDetailVerificationController::class, 'submit'])
                ->where('token', '[A-Za-z0-9]{32,128}')
                ->name('public.client-detail-verification.submit');
        });

        /*--------------------------------------------------
        || SECTION: Public Client EOI Confirmation Routes
        ||--------------------------------------------------*/
        // These routes are accessible without authentication for client confirmation
        $this->router->get('/client/eoi/confirm/{token}', [EoiRoiSheetController::class, 'showConfirmationPage'])->name('client.eoi.confirm');
        $this->router->get('/client/eoi/amend/{token}', [EoiRoiSheetController::class, 'showAmendmentPage'])->name('client.eoi.amend');
        $this->router->post('/client/eoi/process/{token}', [EoiRoiSheetController::class, 'processClientConfirmation'])->name('client.eoi.process');
        $this->router->get('/client/eoi/success/{token}', [EoiRoiSheetController::class, 'showSuccessPage'])->name('client.eoi.success');

    }

    private function artisan(): ConsoleKernel
    {
        /** @var ConsoleKernel $kernel */
        /** @disregard P1009 */
        $kernel = Container::getInstance()->make(ConsoleKernel::class);

        return $kernel;
    }

    private function config(): ConfigRepository
    {
        /** @var ConfigRepository $config */
        /** @disregard P1009 */
        $config = Container::getInstance()->make('config');

        return $config;
    }

    private function redirector(): mixed
    {
        /** @disregard P1009 */
        return Container::getInstance()->make('redirect');
    }

    private function responseFactory(): ResponseFactory
    {
        /** @var ResponseFactory $responses */
        /** @disregard P1009 */
        $responses = Container::getInstance()->make(ResponseFactory::class);

        return $responses;
    }
}
