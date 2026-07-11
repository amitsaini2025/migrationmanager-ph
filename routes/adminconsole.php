<?php

use App\Http\Controllers\AdminConsole\MatterController;
use App\Http\Controllers\AdminConsole\TagController;
use App\Http\Controllers\AdminConsole\WorkflowController;
use App\Http\Controllers\AdminConsole\EmailController;
use App\Http\Controllers\AdminConsole\CrmEmailTemplateController;
use App\Http\Controllers\AdminConsole\MatterEmailTemplateController;
use App\Http\Controllers\AdminConsole\MatterOtherEmailTemplateController;
use App\Http\Controllers\AdminConsole\PersonalDocumentTypeController;
use App\Http\Controllers\AdminConsole\VisaDocumentTypeController;
use App\Http\Controllers\AdminConsole\DocumentChecklistController;
use App\Http\Controllers\AdminConsole\ClientController;
use App\Http\Controllers\AdminConsole\StaffController;
use App\Http\Controllers\AdminConsole\UserroleController;
use App\Http\Controllers\AdminConsole\TeamController;
use App\Http\Controllers\AdminConsole\BranchesController;
use App\Http\Controllers\AdminConsole\AnzscoOccupationController;
use App\Http\Controllers\AdminConsole\Sms\SmsController;
use App\Http\Controllers\AdminConsole\Sms\SmsSendController;
use App\Http\Controllers\AdminConsole\Sms\SmsTemplateController;
use App\Http\Controllers\AdminConsole\Sms\SmsWebhookController;
use App\Http\Controllers\AdminConsole\ESignatureController;
use App\Http\Controllers\AdminConsole\EmailLabelController;
use App\Http\Controllers\AdminConsole\ActivitySearchController;
use App\Http\Controllers\AdminConsole\OutgoingEmailController;
use App\Http\Controllers\AdminConsole\SystemGeneratedEmailController;

/*
|--------------------------------------------------------------------------
| AdminConsole Routes
|--------------------------------------------------------------------------
|
| Here are the admin console routes for system configuration and settings.
| These routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('adminconsole')->name('adminconsole.')->middleware(['auth:admin', 'adminconsole'])->group(function() {
    
    // Features routes - Feature management
    Route::prefix('features')->name('features.')->group(function() {
        
        // Matter routes
        Route::get('/matter', [MatterController::class, 'index'])->name('matter.index');
        Route::get('/matter/create', [MatterController::class, 'create'])->name('matter.create');
        Route::post('/matter/store', [MatterController::class, 'store'])->name('matter.store');
        Route::get('/matter/edit/{id}', [MatterController::class, 'edit'])->name('matter.edit');
        Route::put('/matter/{id}', [MatterController::class, 'update'])->name('matter.update');
        
        // Tags routes
        Route::get('/tags', [TagController::class, 'index'])->name('tags.index');
        Route::get('/tags/create', [TagController::class, 'create'])->name('tags.create');
        Route::post('/tags/store', [TagController::class, 'store'])->name('tags.store');
        Route::get('/tags/edit/{id}', [TagController::class, 'edit'])->name('tags.edit');
        Route::put('/tags/{id}', [TagController::class, 'update'])->name('tags.update');
        
        // Email Labels routes
        Route::get('/email-labels', [EmailLabelController::class, 'index'])->name('emaillabels.index');
        Route::get('/email-labels/create', [EmailLabelController::class, 'create'])->name('emaillabels.create');
        Route::post('/email-labels/store', [EmailLabelController::class, 'store'])->name('emaillabels.store');
        Route::get('/email-labels/edit/{id}', [EmailLabelController::class, 'edit'])->name('emaillabels.edit');
        Route::put('/email-labels/{id}', [EmailLabelController::class, 'update'])->name('emaillabels.update');
        
        // Workflow routes (per-matter workflows)
        Route::get('/workflow', [WorkflowController::class, 'index'])->name('workflow.index');
        Route::get('/workflow/create', [WorkflowController::class, 'create'])->name('workflow.create');
        Route::post('/workflow/store-workflow', [WorkflowController::class, 'storeWorkflow'])->name('workflow.storeWorkflow');
        Route::get('/workflow/edit-workflow/{id}', [WorkflowController::class, 'editWorkflow'])->name('workflow.editWorkflow');
        Route::put('/workflow/update-workflow/{id}', [WorkflowController::class, 'updateWorkflow'])->name('workflow.updateWorkflow');
        Route::get('/workflow/{id}/stages', [WorkflowController::class, 'stages'])->name('workflow.stages');
        Route::get('/workflow/{id}/stage/create', [WorkflowController::class, 'createStage'])->name('workflow.createStage');
        Route::post('/workflow/store', [WorkflowController::class, 'store'])->name('workflow.store');
        Route::get('/workflow/edit/{id}', [WorkflowController::class, 'edit'])->name('workflow.edit');
        Route::put('/workflow/{id}', [WorkflowController::class, 'update'])->name('workflow.update');
        Route::get('/workflow/{workflowId}/stage/{stageId}/checklists', [WorkflowController::class, 'stageChecklists'])->name('workflow.stageChecklists');
        Route::post('/workflow/stage-checklists', [WorkflowController::class, 'storeStageChecklist'])->name('workflow.storeStageChecklist');
        Route::put('/workflow/stage-checklists/{id}', [WorkflowController::class, 'updateStageChecklist'])->name('workflow.updateStageChecklist');
        Route::post('/workflow/{workflowId}/sync-checklists', [WorkflowController::class, 'syncWorkflowChecklists'])->name('workflow.syncChecklists');
        Route::delete('/workflow/stage-checklists/{id}', [WorkflowController::class, 'destroyStageChecklist'])->name('workflow.destroyStageChecklist');
        
        // Email routes
        Route::get('/emails', [EmailController::class, 'index'])->name('emails.index');
        Route::get('/emails/create', [EmailController::class, 'create'])->name('emails.create');
        Route::post('/emails/store', [EmailController::class, 'store'])->name('emails.store');
        Route::get('/emails/edit/{id}', [EmailController::class, 'edit'])->name('emails.edit');
        Route::put('/emails/{id}', [EmailController::class, 'update'])->name('emails.update');
        
        // CRM Email Template routes
        Route::get('/crm-email-template', [CrmEmailTemplateController::class, 'index'])->name('crmemailtemplate.index');
        Route::get('/crm-email-template/create', [CrmEmailTemplateController::class, 'create'])->name('crmemailtemplate.create');
        Route::post('/crm-email-template/store', [CrmEmailTemplateController::class, 'store'])->name('crmemailtemplate.store');
        Route::get('/crm-email-template/edit/{id}', [CrmEmailTemplateController::class, 'edit'])->name('crmemailtemplate.edit');
        Route::put('/crm-email-template/{id}', [CrmEmailTemplateController::class, 'update'])->name('crmemailtemplate.update');
        
        // Matter Email Template routes
        Route::get('/matter-email-template', [MatterEmailTemplateController::class, 'index'])->name('matteremailtemplate.index');
        Route::get('/matter-email-template/create', [MatterEmailTemplateController::class, 'create'])->name('matteremailtemplate.create');
        Route::post('/matter-email-template/store', [MatterEmailTemplateController::class, 'store'])->name('matteremailtemplate.store');
        Route::get('/matter-email-template/edit/{id}', [MatterEmailTemplateController::class, 'edit'])->name('matteremailtemplate.edit');
        Route::put('/matter-email-template/{id}', [MatterEmailTemplateController::class, 'update'])->name('matteremailtemplate.update');
        
        // Matter Other Email Template routes (create before {matterId} to avoid "create" being matched as matterId)
        Route::get('/matter-other-email-template/create/{matterId}', [MatterOtherEmailTemplateController::class, 'create'])->name('matterotheremailtemplate.create');
        Route::get('/matter-other-email-template/{matterId}', [MatterOtherEmailTemplateController::class, 'index'])->name('matterotheremailtemplate.index');
        Route::post('/matter-other-email-template/store', [MatterOtherEmailTemplateController::class, 'store'])->name('matterotheremailtemplate.store');
        Route::get('/matter-other-email-template/edit/{id}', [MatterOtherEmailTemplateController::class, 'edit'])->name('matterotheremailtemplate.edit');
        Route::put('/matter-other-email-template/{id}', [MatterOtherEmailTemplateController::class, 'update'])->name('matterotheremailtemplate.update');
        
        // Personal Document Type routes
        Route::get('/personal-document-type', [PersonalDocumentTypeController::class, 'index'])->name('personaldocumenttype.index');
        Route::get('/personal-document-type/create', [PersonalDocumentTypeController::class, 'create'])->name('personaldocumenttype.create');
        Route::post('/personal-document-type/store', [PersonalDocumentTypeController::class, 'store'])->name('personaldocumenttype.store');
        Route::get('/personal-document-type/edit/{id}', [PersonalDocumentTypeController::class, 'edit'])->name('personaldocumenttype.edit');
        Route::put('/personal-document-type/{id}', [PersonalDocumentTypeController::class, 'update'])->name('personaldocumenttype.update');
        Route::post('/personal-document-type/checkcreatefolder', [PersonalDocumentTypeController::class, 'checkcreatefolder']);
        
        // Visa Document Type routes
        Route::get('/visa-document-type', [VisaDocumentTypeController::class, 'index'])->name('visadocumenttype.index');
        Route::get('/visa-document-type/create', [VisaDocumentTypeController::class, 'create'])->name('visadocumenttype.create');
        Route::post('/visa-document-type/store', [VisaDocumentTypeController::class, 'store'])->name('visadocumenttype.store');
        Route::get('/visa-document-type/edit/{id}', [VisaDocumentTypeController::class, 'edit'])->name('visadocumenttype.edit');
        Route::put('/visa-document-type/{id}', [VisaDocumentTypeController::class, 'update'])->name('visadocumenttype.update');
        Route::post('/visa-document-type/checkcreatefolder', [VisaDocumentTypeController::class, 'checkcreatefolder']);
        
        // Document Checklist routes
        Route::get('/document-checklist', [DocumentChecklistController::class, 'index'])->name('documentchecklist.index');
        Route::get('/document-checklist/create', [DocumentChecklistController::class, 'create'])->name('documentchecklist.create');
        Route::post('/document-checklist/store', [DocumentChecklistController::class, 'store'])->name('documentchecklist.store');
        Route::get('/document-checklist/edit/{id}', [DocumentChecklistController::class, 'edit'])->name('documentchecklist.edit');
        Route::put('/document-checklist/{id}', [DocumentChecklistController::class, 'update'])->name('documentchecklist.update');
        
        // SMS Management routes
        Route::prefix('sms')->name('sms.')->group(function() {
            // SMS Dashboard & History
            Route::get('/dashboard', [SmsController::class, 'dashboard'])->name('dashboard');
            Route::get('/history', [SmsController::class, 'history'])->name('history');
            Route::get('/history/{id}', [SmsController::class, 'show'])->name('history.show');
            
            // SMS Statistics & Status (API endpoints)
            Route::get('/statistics', [SmsController::class, 'statistics'])->name('statistics');
            Route::get('/status/{smsLogId}', [SmsController::class, 'checkStatus'])->name('status.check');
            
            // Manual SMS Sending
            Route::get('/send', [SmsSendController::class, 'create'])->name('send.create');
            Route::post('/send', [SmsSendController::class, 'send'])->name('send');
            Route::post('/send/template', [SmsSendController::class, 'sendFromTemplate'])->name('send.template');
            Route::post('/send/bulk', [SmsSendController::class, 'sendBulk'])->name('send.bulk');
            
            // SMS Templates
            Route::resource('templates', SmsTemplateController::class);
            Route::get('/templates-active', [SmsTemplateController::class, 'active'])->name('templates.active');
        });
        
        // Sent Emails — outgoing email log & search
        Route::prefix('sent-emails')->name('sent-emails.')->group(function () {
            Route::get('/dashboard', [OutgoingEmailController::class, 'dashboard'])->name('dashboard');
            Route::get('/',          [OutgoingEmailController::class, 'index'])->name('index');
            Route::get('/{id}',      [OutgoingEmailController::class, 'show'])->name('show');
        });

        // System-generated emails — invoices, reminders, appointments, etc.
        Route::prefix('system-emails')->name('system-emails.')->group(function () {
            Route::get('/dashboard', [SystemGeneratedEmailController::class, 'dashboard'])->name('dashboard');
            Route::get('/',          [SystemGeneratedEmailController::class, 'index'])->name('index');
            Route::get('/{id}',      [SystemGeneratedEmailController::class, 'show'])->name('show');
        });

        // E-Signature Management routes
        Route::prefix('esignature')->name('esignature.')->group(function() {
            Route::get('/', [ESignatureController::class, 'index'])->name('index');
            Route::get('/export', [ESignatureController::class, 'exportAudit'])->name('export');
        });
        
    });
    
    // Staff routes (dedicated staff table)
    Route::prefix('staff')->name('staff.')->group(function() {
        Route::get('/', [StaffController::class, 'index'])->name('index');
        Route::get('/active', [StaffController::class, 'active'])->name('active');
        Route::get('/inactive', [StaffController::class, 'inactive'])->name('inactive');
        Route::get('/invited', [StaffController::class, 'invited'])->name('invited');
        Route::get('/create', [StaffController::class, 'create'])->name('create');
        Route::post('/store', [StaffController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [StaffController::class, 'edit'])->name('edit');
        Route::put('/{id}', [StaffController::class, 'update'])->name('update');
        Route::get('/view/{id}', [StaffController::class, 'view'])->name('view');
        Route::post('/savezone', [StaffController::class, 'savezone']);
    });

    // System routes - System management
    Route::prefix('system')->name('system.')->group(function() {
        
        // DEPRECATED: Legacy /users - Staff moved to adminconsole.staff.* (StaffController + staff table).
        // Clients at adminconsole.system.clients.* (ClientController).
        // Redirect /users for backwards compatibility.
        Route::get('/users', fn () => redirect()->route('adminconsole.system.clients.clientlist'))->name('users.index');
        Route::get('/clients', [ClientController::class, 'clientlist'])->name('clients.clientlist');
        Route::get('/clients/create', [ClientController::class, 'createclient'])->name('clients.createclient');
        Route::post('/clients/store', [ClientController::class, 'storeclient'])->name('clients.storeclient');
        Route::get('/clients/edit/{id}', [ClientController::class, 'editclient'])->name('clients.editclient');
        Route::put('/clients/{id}', [ClientController::class, 'updateclient'])->name('clients.updateclient');
        
        // Roles routes
        Route::get('/roles', [UserroleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [UserroleController::class, 'create'])->name('roles.create');
        Route::post('/roles/store', [UserroleController::class, 'store'])->name('roles.store');
        Route::get('/roles/edit/{id}', [UserroleController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{id}', [UserroleController::class, 'update'])->name('roles.update');
        
        // Teams routes
        Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('/teams/edit/{id}', [TeamController::class, 'edit'])->name('teams.edit');
        Route::post('/teams/store', [TeamController::class, 'store'])->name('teams.store');
        Route::put('/teams/{id}', [TeamController::class, 'update'])->name('teams.update');
        
        // Offices routes
        Route::get('/offices', [BranchesController::class, 'index'])->name('offices.index');
        Route::get('/offices/create', [BranchesController::class, 'create'])->name('offices.create');
        Route::post('/offices/store', [BranchesController::class, 'store'])->name('offices.store');
        Route::get('/offices/edit/{id}', [BranchesController::class, 'edit'])->name('offices.edit');
        Route::get('/offices/view/{id}', [BranchesController::class, 'view'])->name('offices.view');
        Route::get('/offices/view/client/{id}', [BranchesController::class, 'viewclient'])->name('offices.viewclient');
        Route::put('/offices/{id}', [BranchesController::class, 'update'])->name('offices.update');
        
        // Client Email List route
        Route::get('/clientsemaillist', [\App\Http\Controllers\CRM\ClientsController::class, 'clientsemaillist'])->name('clientsemaillist');
        
        // Activity Search routes (Super Admin + grant_super_admin_access; see ActivitySearchController)
        Route::get('/activity-search/activity/{id}', [ActivitySearchController::class, 'activityJson'])
            ->whereNumber('id')
            ->name('activity-search.activity');
        Route::get('/activity-search', [ActivitySearchController::class, 'index'])->name('activity-search.index');
        Route::get('/activity-search/export', [ActivitySearchController::class, 'export'])->name('activity-search.export');
        Route::get('/activity-search/search-clients', [ActivitySearchController::class, 'searchClients'])->name('activity-search.search-clients');
        
    });
    
    // Database routes - Database management
    Route::prefix('database')->name('database.')->group(function() {
        
        // ANZSCO routes
        Route::get('/anzsco', [AnzscoOccupationController::class, 'index'])->name('anzsco.index');
        Route::get('/anzsco/data', [AnzscoOccupationController::class, 'getData'])->name('anzsco.data');
        Route::get('/anzsco/create', [AnzscoOccupationController::class, 'create'])->name('anzsco.create');
        Route::post('/anzsco/store', [AnzscoOccupationController::class, 'store'])->name('anzsco.store');
        Route::get('/anzsco/edit/{id}', [AnzscoOccupationController::class, 'edit'])->name('anzsco.edit');
        Route::put('/anzsco/{id}', [AnzscoOccupationController::class, 'update'])->name('anzsco.update');
        Route::get('/anzsco/import', [AnzscoOccupationController::class, 'importPage'])->name('anzsco.import');
        Route::post('/anzsco/import', [AnzscoOccupationController::class, 'import'])->name('anzsco.import.store');
    });
});
