<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\ClientPortalController;
use App\Http\Controllers\CRM\OfficeVisitController;
// WARNING: AppointmentsController has been deleted - old appointment system removed
// use App\Http\Controllers\CRM\AppointmentsController;
use App\Http\Controllers\CRM\BookingAppointmentsController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| Applications, Office Visits & Appointments Routes
|--------------------------------------------------------------------------
|
| All routes for visa application management, office visit tracking,
| and appointment scheduling functionality.
|
| Prefix: None (routes at root level)
| Middleware: auth:admin (inherited from web.php)
|
*/

/*---------- Applications Management ----------*/
// REMOVED - Standalone applications pages (not linked from anywhere, all functionality moved to client portal tab)
// Route::get('/applications', [ClientPortalController::class, 'index'])->name('applications.index');
// Route::get('/applications/create', [ClientPortalController::class, 'create'])->name('applications.create');
// Route::get('/applications/detail/{id}', [ClientPortalController::class, 'detail'])->name('applications.detail');
// Route::post('/applications-import', [ClientPortalController::class, 'import'])->name('applications.import');

/*---------- Application Operations ----------*/
Route::get('/client-portal/detail', [ClientPortalController::class, 'getClientPortalDetail']);
Route::post('/client-portal/load-matter-upsert', [ClientPortalController::class, 'loadMatterUpsert']);
Route::get('/updatestage', [ClientPortalController::class, 'updatestage']);
Route::get('/completestage', [ClientPortalController::class, 'completestage']);
Route::get('/updatebackstage', [ClientPortalController::class, 'updatebackstage']);
Route::post('/clients/matter/update-next-stage', [ClientPortalController::class, 'updateClientMatterNextStage'])->name('clients.matter.update-next-stage');
Route::post('/clients/matter/update-previous-stage', [ClientPortalController::class, 'updateClientMatterPreviousStage'])->name('clients.matter.update-previous-stage');
Route::post('/clients/matter/discontinue', [ClientPortalController::class, 'discontinueClientMatter'])->name('clients.matter.discontinue');
Route::post('/clients/matter/reopen', [ClientPortalController::class, 'reopenClientMatter'])->name('clients.matter.reopen');
Route::post('/clients/matter/delete', [ClientPortalController::class, 'deleteClientMatter'])->name('clients.matter.delete');
Route::post('/clients/matter/update-deadline', [ClientPortalController::class, 'updateClientMatterDeadline'])->name('clients.matter.update-deadline');
Route::post('/clients/matter/change-workflow', [ClientPortalController::class, 'changeClientMatterWorkflow'])->name('clients.matter.change-workflow');
Route::get('/client-portal/logs', [ClientPortalController::class, 'getMatterLogs']);
Route::get('/client-portal/list', [ClientPortalController::class, 'getapplications']);

Route::post('/client-portal/discontinue', [ClientPortalController::class, 'discontinueMatter']);
Route::post('/client-portal/revert', [ClientPortalController::class, 'revertMatter']);

/*---------- Application Notes & Communication ----------*/
Route::post('/create-app-note', [ClientPortalController::class, 'addNote']);
Route::get('/client-portal/notes', [ClientPortalController::class, 'getMatterNotes']);
Route::post('/client-portal/sendmail', [ClientPortalController::class, 'clientPortalSendmail']);

/*---------- Application Messages (Client Portal) ----------*/
Route::get('/clients/matter-messages', [ClientPortalController::class, 'getMatterMessages'])->name('clients.matter-messages');
Route::post('/clients/send-message', [ClientPortalController::class, 'sendMessageToClient'])->name('clients.send-message');
Route::post('/clients/messages/{id}/mark-read', [ClientPortalController::class, 'markMessageAsRead'])->name('clients.messages.mark-read');
Route::get('/clients/message-attachment/{id}/download', [ClientPortalController::class, 'downloadMessageAttachment'])->name('clients.message-attachment-download');

/*---------- Broadcasting Auth ----------*/
// Broadcasting authentication is handled by Laravel's built-in BroadcastServiceProvider
// Channel authorization is defined in routes/channels.php
// The /broadcasting/auth route is automatically registered by Broadcast::routes()

/*---------- Client Portal Updates ----------*/
Route::get('/client-portal/updateintake', [ClientPortalController::class, 'updateintake']);
Route::get('/client-portal/updatedates', [ClientPortalController::class, 'updatedates']);
Route::get('/client-portal/updateexpectwin', [ClientPortalController::class, 'updateexpectwin']);
// REMOVED - Unused route (no references found in views or JavaScript)
// Route::get('/application/getapplicationbycid', [ClientPortalController::class, 'getapplicationbycid']);

/*---------- Application Agents ----------*/
// REMOVED - Super/Sub agents for applications feature
// Route::post('/application/spagent_application', ...);
// Route::post('/application/sbagent_application', ...);
Route::post('/client-portal/ownership', [ClientPortalController::class, 'application_ownership']);
// Route::get('/superagent', ...);
// Route::get('/subagent', ...);

/*---------- Application Documents ----------*/
// REMOVED - Application PDF export route (functionality was broken and unused)
// Route::get('/application/export/pdf/{id}', [ClientPortalController::class, 'exportapplicationpdf']);
Route::get('/crm/document-checklists-options', [ClientPortalController::class, 'getDocumentChecklistsOptions']);
Route::post('/add-checklists', [ClientPortalController::class, 'addChecklist'])->name('client_portal.addChecklist');
Route::post('/clients/matter/complete-workflow-checklist', [ClientPortalController::class, 'completeWorkflowChecklist'])->name('clients.matter.complete-workflow-checklist');
Route::post('/clients/matter/workflow-file-note', [ClientPortalController::class, 'saveWorkflowFileNote'])->name('clients.matter.workflow-file-note');
// checklistupload REMOVED - workflow checklist upload flow dead (no UI triggers it)
// delete-docs, publishdoc, approve-document, reject-document REMOVED - workflow checklist unused
Route::get('/client-portal/download-document', [ClientPortalController::class, 'downloadDocument']);
Route::get('/client-portal/document-categories-for-move', [ClientPortalController::class, 'getDocumentCategoriesForMove']);

/*---------- Migration Index ----------*/
// REMOVED - Standalone migration index page (not linked from anywhere, orphaned page)
// Route::get('/migration', [ClientPortalController::class, 'migrationindex'])->name('migration.index');

/*
|--------------------------------------------------------------------------
| Office Visits Management
|--------------------------------------------------------------------------
*/

Route::get('/office-visits', fn () => redirect()->route('officevisits.waiting'))->name('officevisits.index');
Route::get('/office-visits/waiting', [OfficeVisitController::class, 'waiting'])->name('officevisits.waiting');
Route::get('/office-visits/attending', [OfficeVisitController::class, 'attending'])->name('officevisits.attending');
Route::get('/office-visits/completed', [OfficeVisitController::class, 'completed'])->name('officevisits.completed');
Route::get('/office-visits/create', [OfficeVisitController::class, 'create'])->name('officevisits.create');

/*---------- Office Visit Operations ----------*/
Route::post('/checkin', [OfficeVisitController::class, 'checkin']);
Route::get('/get-checkin-detail', [OfficeVisitController::class, 'getcheckin']);
Route::post('/update_visit_purpose', [OfficeVisitController::class, 'update_visit_purpose']);
Route::post('/update_visit_comment', [OfficeVisitController::class, 'update_visit_comment']);
Route::post('/attend_session', [OfficeVisitController::class, 'attend_session']);
Route::post('/complete_session', [OfficeVisitController::class, 'complete_session']);
Route::get('/office-visits/change_assignee', [OfficeVisitController::class, 'change_assignee']);

/*
|--------------------------------------------------------------------------
| Appointments Management - OLD SYSTEM REMOVED
|--------------------------------------------------------------------------
| WARNING: All old appointment routes have been commented out.
| The old appointment system (AppointmentsController) has been deleted.
| The new booking system uses BookingAppointmentsController and is located
| at /booking/appointments routes (see below).
|
| Consolidated from multiple locations in web.php (lines 303-311, 403-410, 653-661)
*/

/*---------- Appointment Resource Routes ----------*/
// Route::resource('appointments', AppointmentsController::class); // REMOVED - old system deleted

/*---------- Appointment Type Views ----------*/
// Route::get('/appointments-education', [AppointmentsController::class, 'appointmentsEducation'])->name('appointments-education'); // REMOVED
// Route::get('/appointments-jrp', [AppointmentsController::class, 'appointmentsJrp'])->name('appointments-jrp'); // REMOVED
// Route::get('/appointments-tourist', [AppointmentsController::class, 'appointmentsTourist'])->name('appointments-tourist'); // REMOVED
// Route::get('/appointments-others', [AppointmentsController::class, 'appointmentsOthers'])->name('appointments-others'); // REMOVED
// Route::get('/appointments-adelaide', [AppointmentsController::class, 'appointmentsAdelaide'])->name('appointments-adelaide'); // REMOVED

/*---------- Appointment CRUD Operations ----------*/
// Route::get('/deleteappointment', [AppointmentsController::class, 'deleteappointment']); // REMOVED
// Route::post('/add-appointment', [AppointmentsController::class, 'addAppointment']); // REMOVED
// Route::post('/add-appointment-book', [AppointmentsController::class, 'addAppointmentBook']); // REMOVED
// Route::post('/editappointment', [AppointmentsController::class, 'editappointment']); // REMOVED

/*---------- Appointment Updates ----------*/
// Route::post('/updatefollowupschedule', [AppointmentsController::class, 'updatefollowupschedule']); // REMOVED
// Route::get('/updateappointmentstatus/{status}/{id}', [AppointmentsController::class, 'updateappointmentstatus']); // REMOVED
// Route::post('/update_appointment_status', [AppointmentsController::class, 'update_appointment_status']); // REMOVED
// Route::post('/update_appointment_priority', [AppointmentsController::class, 'update_appointment_priority']); // REMOVED
// Route::post('/update_apppointment_comment', [AppointmentsController::class, 'update_apppointment_comment']); // REMOVED
// Route::post('/update_apppointment_description', [AppointmentsController::class, 'update_apppointment_description']); // REMOVED

/*---------- Appointment Data Retrieval ----------*/
// Route::get('/get-appointments', [AppointmentsController::class, 'getAppointments']); // REMOVED
// Route::get('/getAppointmentdetail', [AppointmentsController::class, 'getAppointmentdetail']); // REMOVED
// Route::get('/get-assigne-detail', [AppointmentsController::class, 'assignedetail']); // REMOVED

/*---------- Appointment Assignment ----------*/
// Route::get('/change_assignee', [AppointmentsController::class, 'change_assignee']); // REMOVED

/*---------- Appointment Scheduling Backend ----------*/
// Restored: Using HomeController methods with BookingAppointment model
Route::post('/getdatetimebackend', [HomeController::class, 'getdatetimebackend'])->name('getdatetimebackend');
Route::post('/getdisableddatetime', [HomeController::class, 'getdisableddatetime'])->name('getdisableddatetime');

/*
|--------------------------------------------------------------------------
| Website Booking Appointments (Synced from Bansal Website)
|--------------------------------------------------------------------------
|
| Routes for managing appointments synced from the Bansal Immigration
| public website. This is a separate system from manual appointments.
|
*/

Route::controller(BookingAppointmentsController::class)
    ->prefix('booking')
    ->name('booking.')
    ->group(function () {
        
        // Appointment List & Views
        Route::get('/appointments', 'index')->name('appointments.index');
        Route::get('/appointments/{id}/edit', 'edit')
            ->name('appointments.edit')
            ->whereNumber('id');
        Route::put('/appointments/{id}', 'update')
            ->name('appointments.update')
            ->whereNumber('id');
        
        // Appointment Detail
        Route::get('/appointments/{id}', 'show')
            ->name('appointments.show')
            ->whereNumber('id');
        
        // Get appointment as JSON (for modals/AJAX)
        Route::get('/appointments/{id}/json', 'getAppointmentJson')
            ->name('appointments.json')
            ->whereNumber('id');
        
        // Calendar Views (by type)
        Route::get('/calendar/{type}', 'calendar')
            ->name('appointments.calendar')
            ->whereIn('type', ['paid', 'jrp', 'education', 'tourist', 'adelaide', 'adelaide_education', 'ajay', 'kunal', 'arun']);
        
        // Update Actions
        Route::post('/appointments/{id}/update-status', 'updateStatus')
            ->name('appointments.update-status')
            ->whereNumber('id');
        
        Route::post('/appointments/{id}/update-consultant', 'updateConsultant')
            ->name('appointments.update-consultant')
            ->whereNumber('id');
        
        Route::post('/appointments/{id}/update-meeting-type', 'updateMeetingType')
            ->name('appointments.update-meeting-type')
            ->whereNumber('id');
        
        Route::post('/appointments/{id}/update-datetime', 'update')
            ->name('appointments.update-datetime')
            ->whereNumber('id');
        
        Route::post('/appointments/{id}/add-note', 'addNote')
            ->name('appointments.add-note')
            ->whereNumber('id');
        
        Route::post('/appointments/{id}/send-reminder', 'sendReminder')
            ->name('appointments.send-reminder')
            ->whereNumber('id');
        
        // Bulk Actions
        Route::post('/appointments/bulk-update-status', 'bulkUpdateStatus')
            ->name('appointments.bulk-update-status');
        
        // Export
        Route::get('/appointments/export', 'export')
            ->name('appointments.export');
        
        // Sync Management
        Route::get('/sync/dashboard', 'syncDashboard')
            ->name('sync.dashboard');
        
        Route::get('/sync/stats', 'syncStats')
            ->name('sync.stats');
        
        Route::post('/sync/manual', 'manualSync')
            ->name('sync.manual')
            ->middleware('can:trigger-manual-sync');
        
        // API endpoints for datatables
        Route::get('/api/appointments', 'getAppointments')
            ->name('api.appointments');
    });

