<?php

namespace Tests\Unit;

use App\Support\ClientDetailTabs;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Client-detail tab contract. Avoids Laravel helpers/facades so Intelephense
 * can resolve assertions. Fragment routes cover registered lazy tabs only.
 */
class ClientDetailTabsTest extends TestCase
{
    #[Test]
    public function reserved_slugs_match_the_existing_client_detail_url_contract(): void
    {
        Assert::assertSame([
            'personaldetails',
            'companydetails',
            'activityfeed',
            'noteterm',
            'personaldocuments',
            'visadocuments',
            'nominationdocuments',
            'eoiroi',
            'emails',
            'client_portal',
            'formgenerations',
            'formgenerationsl',
            'workflow',
            'checklists',
            'account',
            'notuseddocuments',
        ], ClientDetailTabs::slugs());
    }

    #[Test]
    public function tab_slugs_are_not_treated_as_matter_refs(): void
    {
        Assert::assertTrue(ClientDetailTabs::isKnownSlug('workflow'));
        Assert::assertTrue(ClientDetailTabs::isKnownSlug('PersonalDetails'));
        Assert::assertTrue(ClientDetailTabs::isKnownSlug('formgenerationsl'));
        Assert::assertTrue(ClientDetailTabs::isKnownSlug('account'));
        Assert::assertFalse(ClientDetailTabs::isKnownSlug('APC_3'));
        Assert::assertFalse(ClientDetailTabs::isKnownSlug(null));
        Assert::assertFalse(ClientDetailTabs::isKnownSlug(''));
    }

    #[Test]
    public function deep_link_eager_renders_only_the_active_lazy_tab(): void
    {
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('workflow', 'workflow'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('workflow', 'personaldetails'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('client_portal', 'client_portal'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('client_portal', 'account'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('account', 'account'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('account', 'personaldetails'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('checklists', 'checklists'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('checklists', 'account'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('emails', 'emails'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('emails', 'personaldetails'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('personaldocuments', 'personaldocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('personaldocuments', 'visadocuments'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('visadocuments', 'visadocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('visadocuments', 'personaldocuments'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('notuseddocuments', 'notuseddocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('notuseddocuments', 'personaldocuments'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('noteterm', 'noteterm'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('noteterm', 'personaldetails'));
    }

    #[Test]
    public function fragment_route_names_cover_registered_lazy_tabs_only(): void
    {
        Assert::assertSame([
            'workflow' => 'clients.detail.workflow-tab',
            'client_portal' => 'clients.detail.client-portal-tab',
            'account' => 'clients.detail.account-tab',
            'checklists' => 'clients.detail.checklists-tab',
            'emails' => 'clients.detail.emails-tab',
            'personaldocuments' => 'clients.detail.personaldocuments-tab',
            'visadocuments' => 'clients.detail.visadocuments-tab',
            'notuseddocuments' => 'clients.detail.notuseddocuments-tab',
            'noteterm' => 'clients.detail.noteterm-tab',
        ], ClientDetailTabs::fragmentRouteNames());
    }

    #[Test]
    public function tab_script_filenames_and_pushstate_stay_in_place(): void
    {
        foreach (ClientDetailTabs::tabScriptFilenames() as $relativePath) {
            Assert::assertFileExists(
                $this->projectPath('public/'.$relativePath),
                'Missing required script: '.$relativePath
            );
        }

        $sidebarTabs = file_get_contents($this->projectPath('public/js/crm/clients/sidebar-tabs.js'));
        Assert::assertNotFalse($sidebarTabs);
        Assert::assertStringContainsString('window.history.pushState', $sidebarTabs);
        Assert::assertStringContainsString("newUrl += '/' + tabId;", $sidebarTabs);
        Assert::assertStringContainsString('#${tabId}-tab', $sidebarTabs);
        Assert::assertStringContainsString('#sel_matter_id_client_detail', $sidebarTabs);
        Assert::assertStringContainsString('ensureWorkflowTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureClientPortalTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureAccountTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureChecklistsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureEmailsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensurePersonalDocumentsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureVisaDocumentsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureNotUsedDocumentsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('ensureNotesTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('bindNavButtons', $sidebarTabs);
        Assert::assertStringNotContainsString('loadEmails({ forceReload: true })', $sidebarTabs);
        Assert::assertStringContainsString('filterEmailsByMatter', $sidebarTabs);
        Assert::assertStringContainsString('filterVisaDocumentsByMatter', $sidebarTabs);
    }

    #[Test]
    public function client_detail_page_still_eager_includes_personal_details_and_non_lazy_tabs(): void
    {
        $detail = file_get_contents($this->projectPath('resources/views/crm/clients/detail.blade.php'));
        Assert::assertNotFalse($detail);

        foreach ([
            'crm.clients.tabs.personal_details',
            'crm.clients.tabs.activityfeed_tab',
            'crm.clients.tabs.notes',
            'crm.clients.tabs.personal_documents',
            'crm.clients.tabs.visa_documents',
            'crm.clients.tabs.account',
            'crm.clients.tabs.emails',
            'crm.clients.tabs.checklists',
            'crm.clients.tabs.not_used_documents',
        ] as $include) {
            Assert::assertStringContainsString("@include('".$include."')", $detail);
        }

        Assert::assertStringContainsString('id="sel_matter_id_client_detail"', $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'workflow'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.workflow')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.workflow_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'client_portal'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.client_portal')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.client_portal_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'account'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.account')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.account_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'checklists'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.checklists')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.checklists_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'emails'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.emails')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.emails_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'personaldocuments'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.personal_documents_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'visadocuments'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.visa_documents_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'notuseddocuments'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.not_used_documents_lazy')", $detail);
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'noteterm'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.notes_lazy')", $detail);
        // v1: Personal Details + Activity feed stay eager (never lazy stubs)
        Assert::assertStringContainsString("@include('crm.clients.tabs.personal_details')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.activityfeed_tab')", $detail);
        Assert::assertStringNotContainsString('personal_details_lazy', $detail);
        Assert::assertStringNotContainsString('activityfeed_lazy', $detail);
        Assert::assertStringNotContainsString('activity_feed_lazy', $detail);
    }

    #[Test]
    public function company_detail_still_eager_includes_workflow_and_client_portal(): void
    {
        $companyDetail = file_get_contents($this->projectPath('resources/views/crm/companies/detail.blade.php'));
        Assert::assertNotFalse($companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.workflow')", $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.client_portal')", $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.account')", $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.checklists')", $companyDetail);
        Assert::assertStringNotContainsString('workflow_lazy', $companyDetail);
        Assert::assertStringNotContainsString('client_portal_lazy', $companyDetail);
        Assert::assertStringNotContainsString('account_lazy', $companyDetail);
        Assert::assertStringNotContainsString('checklists_lazy', $companyDetail);
        Assert::assertStringNotContainsString('emails_lazy', $companyDetail);
        Assert::assertStringNotContainsString('personal_documents_lazy', $companyDetail);
        Assert::assertStringNotContainsString('visa_documents_lazy', $companyDetail);
        Assert::assertStringNotContainsString('not_used_documents_lazy', $companyDetail);
        Assert::assertStringNotContainsString('notes_lazy', $companyDetail);
        Assert::assertStringNotContainsString('account-tab.js', $companyDetail);
        Assert::assertStringNotContainsString('checklists-tab.js', $companyDetail);
        Assert::assertStringNotContainsString('emails-tab.js', $companyDetail);
        Assert::assertStringNotContainsString('personaldocuments-tab.js', $companyDetail);
        Assert::assertStringNotContainsString('visadocuments-tab.js', $companyDetail);
        Assert::assertStringNotContainsString('notuseddocuments-tab.js', $companyDetail);
        Assert::assertStringNotContainsString('notes-tab.js', $companyDetail);
    }

    #[Test]
    public function nulls_last_sql_is_sqlite_and_postgres_safe(): void
    {
        Assert::assertSame('(start_date IS NULL) ASC, start_date DESC', ClientDetailTabs::nullsLastSql('start_date'));
        Assert::assertSame('(finish_date IS NULL) ASC, finish_date ASC', ClientDetailTabs::nullsLastSql('finish_date', 'asc'));
        $this->expectException(\InvalidArgumentException::class);
        ClientDetailTabs::nullsLastSql('start_date; drop table admins');
    }

    #[Test]
    public function pane_ids_remain_in_the_existing_tab_blades(): void
    {
        $paneBlades = [
            'personaldetails' => 'crm/clients/tabs/personal_details.blade.php',
            'activityfeed' => 'crm/clients/tabs/activityfeed_tab.blade.php',
            'noteterm' => 'crm/clients/tabs/notes.blade.php',
            'personaldocuments' => 'crm/clients/tabs/personal_documents.blade.php',
            'visadocuments' => 'crm/clients/tabs/visa_documents.blade.php',
            'eoiroi' => 'crm/clients/tabs/eoi_roi.blade.php',
            'emails' => 'crm/clients/tabs/emails.blade.php',
            'account' => 'crm/clients/tabs/account.blade.php',
            'checklists' => 'crm/clients/tabs/checklists.blade.php',
            'workflow' => 'crm/clients/tabs/workflow.blade.php',
            'client_portal' => 'crm/clients/tabs/client_portal.blade.php',
            'notuseddocuments' => 'crm/clients/tabs/not_used_documents.blade.php',
        ];

        foreach ($paneBlades as $slug => $relativePath) {
            $path = $this->projectPath('resources/views/'.$relativePath);
            Assert::assertFileExists($path, 'Missing tab blade: '.$relativePath);
            $contents = file_get_contents($path);
            Assert::assertNotFalse($contents);
            Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId($slug).'"', $contents);
        }

        $workflowLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/workflow_lazy.blade.php'));
        Assert::assertNotFalse($workflowLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('workflow').'"', $workflowLazy);

        $portalLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/client_portal_lazy.blade.php'));
        Assert::assertNotFalse($portalLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('client_portal').'"', $portalLazy);

        $accountLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/account_lazy.blade.php'));
        Assert::assertNotFalse($accountLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('account').'"', $accountLazy);

        $checklistsLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/checklists_lazy.blade.php'));
        Assert::assertNotFalse($checklistsLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('checklists').'"', $checklistsLazy);

        $emailsLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/emails_lazy.blade.php'));
        Assert::assertNotFalse($emailsLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('emails').'"', $emailsLazy);

        $personalDocsLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/personal_documents_lazy.blade.php'));
        Assert::assertNotFalse($personalDocsLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('personaldocuments').'"', $personalDocsLazy);

        $visaDocsLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/visa_documents_lazy.blade.php'));
        Assert::assertNotFalse($visaDocsLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('visadocuments').'"', $visaDocsLazy);

        $notUsedLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/not_used_documents_lazy.blade.php'));
        Assert::assertNotFalse($notUsedLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('notuseddocuments').'"', $notUsedLazy);

        $notesLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/notes_lazy.blade.php'));
        Assert::assertNotFalse($notesLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('noteterm').'"', $notesLazy);
    }

    #[Test]
    public function account_tab_script_reruns_invoice_and_ledger_init_after_inject(): void
    {
        $accountTabJs = file_get_contents($this->projectPath('public/js/crm/clients/account-tab.js'));
        Assert::assertNotFalse($accountTabJs);
        Assert::assertStringContainsString('function ensureAccountTabLoaded', $accountTabJs);
        Assert::assertStringContainsString('function activateInjectedScripts', $accountTabJs);
        Assert::assertStringContainsString('window.listOfInvoice', $accountTabJs);
        Assert::assertStringContainsString('window.clientLedgerBalanceAmount', $accountTabJs);
        Assert::assertStringContainsString('#sel_matter_id_client_detail', $accountTabJs);
        Assert::assertStringContainsString('.general_matter_checkbox_client_detail', $accountTabJs);
    }

    #[Test]
    public function checklists_tab_script_rebinds_create_and_cost_assignment_after_inject(): void
    {
        $checklistsTabJs = file_get_contents($this->projectPath('public/js/crm/clients/checklists-tab.js'));
        Assert::assertNotFalse($checklistsTabJs);
        Assert::assertStringContainsString('function ensureChecklistsTabLoaded', $checklistsTabJs);
        Assert::assertStringContainsString('function activateInjectedScripts', $checklistsTabJs);
        Assert::assertStringContainsString('function importChecklistsOrphanAssets', $checklistsTabJs);
        Assert::assertStringContainsString('data-checklists-fragment-style', $checklistsTabJs);
        Assert::assertStringContainsString('__checklistsOrphanScriptsLoaded', $checklistsTabJs);
        Assert::assertStringContainsString('window.bindChecklistsTabUi', $checklistsTabJs);

        $checklistsBlade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/checklists.blade.php'));
        Assert::assertNotFalse($checklistsBlade);
        Assert::assertStringContainsString('ClientDetailChecklistsTab::build', $checklistsBlade);
        Assert::assertStringContainsString('btn-add-checklist', $checklistsBlade);
        Assert::assertStringContainsString('btn-setup-cost-assignment-for-matter', $checklistsBlade);
        Assert::assertStringContainsString('bindChecklistsTabUi', $checklistsBlade);
        Assert::assertStringContainsString('function showChecklistModal', $checklistsBlade);
        Assert::assertStringContainsString('function checklistJquery', $checklistsBlade);
        Assert::assertStringContainsString('bootstrap.Modal.getOrCreateInstance', $checklistsBlade);
        Assert::assertStringContainsString('startChecklistsBoot', $checklistsBlade);
        Assert::assertStringContainsString("document.readyState === 'loading'", $checklistsBlade);
        Assert::assertStringContainsString('initChecklistMmSelect', $checklistsBlade);
        Assert::assertStringContainsString('checklist_migration_agent', $checklistsBlade);
        Assert::assertStringContainsString('.checklist-accordion', $checklistsBlade);
        Assert::assertStringContainsString('.checklist-item-wrapper', $checklistsBlade);
        Assert::assertStringNotContainsString("@push('scripts')", $checklistsBlade);
    }

    #[Test]
    public function emails_tab_script_loads_once_then_caches_list(): void
    {
        $emailsTabJs = file_get_contents($this->projectPath('public/js/crm/clients/emails-tab.js'));
        Assert::assertNotFalse($emailsTabJs);
        Assert::assertStringContainsString('function ensureEmailsTabLoaded', $emailsTabJs);
        Assert::assertStringContainsString('function activateInjectedScripts', $emailsTabJs);
        Assert::assertStringContainsString('emailsListFetched', $emailsTabJs);
        Assert::assertStringContainsString('window.loadEmails', $emailsTabJs);

        $emailsJs = file_get_contents($this->projectPath('public/js/emails.js'));
        Assert::assertNotFalse($emailsJs);
        Assert::assertStringContainsString('forceReload', $emailsJs);
        Assert::assertStringContainsString('__emailsListCacheKey', $emailsJs);
        Assert::assertStringContainsString('window.reinitializeEmailsTabDom', $emailsJs);

        $emailsBlade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/emails.blade.php'));
        Assert::assertNotFalse($emailsBlade);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('emails').'"', $emailsBlade);
        Assert::assertStringContainsString("@include('crm.emails')", $emailsBlade);
    }

    #[Test]
    public function personal_documents_tab_script_rebinds_upload_and_nav_after_inject(): void
    {
        $js = file_get_contents($this->projectPath('public/js/crm/clients/personaldocuments-tab.js'));
        Assert::assertNotFalse($js);
        Assert::assertStringContainsString('function ensurePersonalDocumentsTabLoaded', $js);
        Assert::assertStringContainsString('function activateInjectedScripts', $js);
        Assert::assertStringContainsString('initPersonalDocDragDrop', $js);
        Assert::assertStringContainsString('initBulkUploadDragDrop', $js);
        Assert::assertStringContainsString('bindNavButtons', $js);
        Assert::assertStringContainsString('data-personaldocuments-fragment-style', $js);
        Assert::assertStringContainsString('function bindPersonalBulkUploadToggle', $js);
        Assert::assertStringContainsString('click.personalBulkToggle', $js);
        Assert::assertStringContainsString('function importPersonalDocumentsOrphanAssets', $js);
        Assert::assertStringContainsString('bulk-upload-toggle-btn', $js);

        $blade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/personal_documents.blade.php'));
        Assert::assertNotFalse($blade);
        Assert::assertStringContainsString('window.initPersonalDocDragDrop', $blade);
        Assert::assertStringContainsString('window.initBulkUploadDragDrop', $blade);
        Assert::assertStringContainsString('data-tab="notuseddocuments"', $blade);
        Assert::assertStringContainsString('document-drag-drop-zone', $blade);
        Assert::assertStringContainsString('Drag file here or', $blade);
        Assert::assertStringContainsString('bulk-upload-toggle-btn', $blade);
        Assert::assertStringContainsString('bulk-upload-dropzone-container', $blade);
        Assert::assertStringContainsString('bindPersonalBulkUploadToggle', $blade);
        Assert::assertStringContainsString('window.showFileContextMenu', $blade);
        Assert::assertStringContainsString('window.handleContextAction', $blade);
        Assert::assertStringContainsString('window.showPersonalChecklistContextMenu', $blade);

        $css = file_get_contents($this->projectPath('public/css/client-detail.css'));
        Assert::assertNotFalse($css);
        Assert::assertStringContainsString('.document-drag-drop-zone', $css);
        Assert::assertStringContainsString('border: 2px dashed #ccc', $css);
        Assert::assertStringContainsString('.drag-zone-inner', $css);
    }

    #[Test]
    public function visa_documents_tab_script_loads_then_filters_by_matter(): void
    {
        $js = file_get_contents($this->projectPath('public/js/crm/clients/visadocuments-tab.js'));
        Assert::assertNotFalse($js);
        Assert::assertStringContainsString('function ensureVisaDocumentsTabLoaded', $js);
        Assert::assertStringContainsString('filterVisaDocumentsByMatter', $js);
        Assert::assertStringContainsString('initVisaDocDragDrop', $js);
        Assert::assertStringContainsString('initVisaBulkUploadDragDrop', $js);
        Assert::assertStringContainsString('bindNavButtons', $js);
        Assert::assertStringContainsString('function importVisaDocumentsOrphanAssets', $js);
        Assert::assertStringContainsString('data-visadocuments-fragment-style', $js);
        Assert::assertStringContainsString('__visaDocumentsOrphanScriptsLoaded', $js);

        $detailMain = file_get_contents($this->projectPath('public/js/crm/clients/detail-main.js'));
        Assert::assertNotFalse($detailMain);
        Assert::assertStringContainsString('ensureVisaDocumentsTabLoaded', $detailMain);
        Assert::assertStringContainsString('contextmenu.softRestore', $detailMain);
        Assert::assertStringContainsString('appendForm956ChecklistRow', $detailMain);

        $blade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/visa_documents.blade.php'));
        Assert::assertNotFalse($blade);
        Assert::assertStringContainsString('window.initVisaDocDragDrop', $blade);
        Assert::assertStringContainsString('window.initVisaBulkUploadDragDrop', $blade);
        Assert::assertStringContainsString('data-tab="notuseddocuments"', $blade);
        Assert::assertStringContainsString('window.showVisaFileContextMenu', $blade);
        Assert::assertStringContainsString('window.handleVisaContextAction', $blade);
        Assert::assertStringContainsString('visaFileContextMenu', $blade);
        Assert::assertStringContainsString('function formatFileSize', $blade);
        Assert::assertStringContainsString('function escapeHtml', $blade);
        Assert::assertStringContainsString('function extractChecklistNameFromFile', $blade);
        Assert::assertStringContainsString('displayVisaMappingInterface', $blade);
        Assert::assertStringContainsString('id="bulk-upload-mapping-modal"', $blade);
        Assert::assertStringContainsString('.bulk-upload-mapping-modal', $blade);
    }

    #[Test]
    public function not_used_documents_tab_keeps_personal_jump(): void
    {
        $js = file_get_contents($this->projectPath('public/js/crm/clients/notuseddocuments-tab.js'));
        Assert::assertNotFalse($js);
        Assert::assertStringContainsString('function ensureNotUsedDocumentsTabLoaded', $js);
        Assert::assertStringContainsString('bindNavButtons', $js);
        Assert::assertStringContainsString('function bindNotUsedContextMenu', $js);
        Assert::assertStringContainsString('function importNotUsedDocumentsOrphanAssets', $js);
        Assert::assertStringContainsString('data-notuseddocuments-fragment-style', $js);
        Assert::assertStringContainsString('window.showNotUsedFileContextMenu', $js);
        Assert::assertStringContainsString('window.handleNotUsedContextAction', $js);
        Assert::assertStringContainsString('#notuseddocuments-tab .deletenote', $js);
        Assert::assertStringContainsString('#notuseddocuments-tab .backtodoc', $js);

        $blade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/not_used_documents.blade.php'));
        Assert::assertNotFalse($blade);
        Assert::assertStringContainsString('data-tab="personaldocuments"', $blade);
        Assert::assertStringContainsString('showNotUsedFileContextMenu', $blade);
        Assert::assertStringContainsString('handleNotUsedContextAction', $blade);
        Assert::assertStringContainsString('data-file-url', $blade);
        Assert::assertStringContainsString('notUsedFileContextMenu', $blade);
        Assert::assertStringContainsString('Back To Document', $blade);
        Assert::assertStringContainsString('notuseddocuments-tab.js', $blade);
    }

    #[Test]
    public function notes_tab_script_rebinds_filter_pin_and_type_pills(): void
    {
        $js = file_get_contents($this->projectPath('public/js/crm/clients/notes-tab.js'));
        Assert::assertNotFalse($js);
        Assert::assertStringContainsString('function ensureNotesTabLoaded', $js);
        Assert::assertStringContainsString('bindNotesTabUi', $js);
        Assert::assertStringContainsString('filterNotes', $js);

        $blade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/notes.blade.php'));
        Assert::assertNotFalse($blade);
        Assert::assertStringContainsString('window.filterNotes', $blade);
        Assert::assertStringContainsString('window.bindNotesTabUi', $blade);
        Assert::assertStringContainsString('pinnote', $blade);
        Assert::assertStringContainsString('subtab8-button', $blade);
        Assert::assertStringContainsString('notes-scope-tab', $blade);

        $detailMain = file_get_contents($this->projectPath('public/js/crm/clients/detail-main.js'));
        Assert::assertNotFalse($detailMain);
        Assert::assertStringContainsString('ensureNotesTabLoaded', $detailMain);
        Assert::assertStringContainsString('.pinnote', $detailMain);
    }

    #[Test]
    public function detail_action_defers_account_docs_checklist_and_notes_payloads(): void
    {
        Assert::assertSame([
            'clientNotes',
            'accountTabPayload',
            'checklistsTabPayload',
        ], ClientDetailTabs::detailDeferredViewKeys());

        $trait = file_get_contents($this->projectPath('app/Traits/ClientCrmFollowups.php'));
        Assert::assertNotFalse($trait);

        $detailStart = strpos($trait, 'public function detail(Request $request');
        $workflowStart = strpos($trait, 'public function workflowTab(Request $request');
        Assert::assertNotFalse($detailStart);
        Assert::assertNotFalse($workflowStart);
        Assert::assertGreaterThan($detailStart, $workflowStart);

        $detailMethod = substr($trait, $detailStart, $workflowStart - $detailStart);

        Assert::assertStringContainsString('buildClientDetailMatterContext', $detailMethod);
        Assert::assertStringContainsString('ClientDetailTabs::slugs()', $detailMethod);
        Assert::assertStringContainsString('personalDetailContacts', $detailMethod);

        foreach (ClientDetailTabs::detailDeferredViewKeys() as $key) {
            Assert::assertStringNotContainsString("'".$key."'", $detailMethod, 'detail() must not compact deferred key: '.$key);
        }

        Assert::assertStringNotContainsString('ClientDetailAccountTab::build', $detailMethod);
        Assert::assertStringNotContainsString('ClientDetailChecklistsTab::build', $detailMethod);
        Assert::assertStringNotContainsString('Note::where', $detailMethod);

        // Fragments still own those payloads.
        Assert::assertStringContainsString("'accountTabPayload' => ClientDetailAccountTab::build", $trait);
        Assert::assertStringContainsString("'checklistsTabPayload' => ClientDetailChecklistsTab::build", $trait);
        Assert::assertStringContainsString("'clientNotes' => \$clientNotes", $trait);
    }

    #[Test]
    public function account_blade_uses_payload_instead_of_inline_ledger_queries(): void
    {
        $accountBlade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/account.blade.php'));
        Assert::assertNotFalse($accountBlade);
        Assert::assertStringContainsString('ClientDetailAccountTab::build', $accountBlade);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('account').'"', $accountBlade);
        Assert::assertStringContainsString('data-account-entry="true"', $accountBlade);
        Assert::assertStringNotContainsString('DISTINCT ON (receipt_id)', $accountBlade);
        Assert::assertStringNotContainsString('SELECT DISTINCT ON', $accountBlade);
    }

    private function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
