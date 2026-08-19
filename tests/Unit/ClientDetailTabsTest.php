<?php

namespace Tests\Unit;

use App\Support\ClientDetailTabs;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Client-detail tab contract. Pane ids, pushState, and fragment routes must stay stable.
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
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('account', 'account'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('account', 'personaldetails'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('checklists', 'checklists'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('checklists', 'emails'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('emails', 'emails'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('emails', 'personaldetails'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('personaldocuments', 'personaldocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('personaldocuments', 'visadocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('visadocuments', 'visadocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('visadocuments', 'notuseddocuments'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('notuseddocuments', 'notuseddocuments'));
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
            'personaldocuments' => 'clients.detail.personal-documents-tab',
            'visadocuments' => 'clients.detail.visa-documents-tab',
            'notuseddocuments' => 'clients.detail.not-used-documents-tab',
            'noteterm' => 'clients.detail.notes-tab',
        ], ClientDetailTabs::fragmentRouteNames());

        foreach (ClientDetailTabs::fragmentRouteNames() as $routeName) {
            Assert::assertTrue(Route::has($routeName), 'Missing fragment route: '.$routeName);
        }

        Assert::assertArrayNotHasKey('personaldetails', ClientDetailTabs::fragmentRouteNames());
        Assert::assertArrayNotHasKey('activityfeed', ClientDetailTabs::fragmentRouteNames());
        Assert::assertFalse(ClientDetailTabs::isLazyFragmentSlug('personaldetails'));
        Assert::assertFalse(ClientDetailTabs::isLazyFragmentSlug('activityfeed'));
        Assert::assertTrue(ClientDetailTabs::isLazyFragmentSlug('noteterm'));
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
        Assert::assertStringContainsString("case 'account':", $sidebarTabs);
        Assert::assertStringContainsString('ensureAccountTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString("case 'checklists':", $sidebarTabs);
        Assert::assertStringContainsString('ensureChecklistsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString("case 'emails':", $sidebarTabs);
        Assert::assertStringContainsString('ensureEmailsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString("case 'personaldocuments':", $sidebarTabs);
        Assert::assertStringContainsString('ensurePersonalDocumentsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString("case 'visadocuments':", $sidebarTabs);
        Assert::assertStringContainsString('ensureVisaDocumentsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString("case 'notuseddocuments':", $sidebarTabs);
        Assert::assertStringContainsString('ensureNotUsedDocumentsTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString("case 'noteterm':", $sidebarTabs);
        Assert::assertStringContainsString('ensureNotesTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('bindNavButtons', $sidebarTabs);
        Assert::assertStringContainsString('filterVisaDocumentsByMatter', $sidebarTabs);
        Assert::assertStringContainsString('loadEmails({ once: true })', $sidebarTabs);
        Assert::assertStringNotContainsString('loadEmails({ forceReload: true })', $sidebarTabs);

        $accountTabJs = file_get_contents($this->projectPath('public/js/crm/clients/account-tab.js'));
        Assert::assertNotFalse($accountTabJs);
        Assert::assertStringContainsString('function ensureAccountTabLoaded', $accountTabJs);
        Assert::assertStringContainsString('function bootAccountTabIfNeeded', $accountTabJs);
        Assert::assertStringContainsString('listOfInvoice', $accountTabJs);
        Assert::assertStringContainsString('clientLedgerBalanceAmount', $accountTabJs);

        $checklistsTabJs = file_get_contents($this->projectPath('public/js/crm/clients/checklists-tab.js'));
        Assert::assertNotFalse($checklistsTabJs);
        Assert::assertStringContainsString('function ensureChecklistsTabLoaded', $checklistsTabJs);
        Assert::assertStringContainsString('function bootChecklistsTabIfNeeded', $checklistsTabJs);
        Assert::assertStringContainsString('initChecklistsStaffDropdowns', $checklistsTabJs);
        Assert::assertStringContainsString('mmSelect', $checklistsTabJs);

        $emailsTabJs = file_get_contents($this->projectPath('public/js/crm/clients/emails-tab.js'));
        Assert::assertNotFalse($emailsTabJs);
        Assert::assertStringContainsString('function ensureEmailsTabLoaded', $emailsTabJs);
        Assert::assertStringContainsString('loadEmails({ once: true })', $emailsTabJs);

        $emailsJs = file_get_contents($this->projectPath('public/js/emails.js'));
        Assert::assertNotFalse($emailsJs);
        Assert::assertStringContainsString('emailsListLoadedKey', $emailsJs);
        Assert::assertStringContainsString('options.once === true', $emailsJs);
        Assert::assertStringContainsString('function emailsListCacheKey', $emailsJs);
        Assert::assertStringContainsString("getElementById('sel_matter_id_client_detail')", $emailsJs);
        Assert::assertStringContainsString('loadEmailsFromServer()', $emailsJs);
        Assert::assertStringContainsString("getElementById('emailmodal')", $emailsJs);
        Assert::assertStringContainsString('initializeFolderTabs', $emailsJs);

        $documentsTabsJs = file_get_contents($this->projectPath('public/js/crm/clients/documents-tabs.js'));
        Assert::assertNotFalse($documentsTabsJs);
        Assert::assertStringContainsString('window.ensurePersonalDocumentsTabLoaded', $documentsTabsJs);
        Assert::assertStringContainsString('window.ensureVisaDocumentsTabLoaded', $documentsTabsJs);
        Assert::assertStringContainsString('window.ensureNotUsedDocumentsTabLoaded', $documentsTabsJs);
        Assert::assertStringContainsString('initPersonalDocDragDrop', $documentsTabsJs);
        Assert::assertStringContainsString('initVisaDocDragDrop', $documentsTabsJs);
        Assert::assertStringContainsString('bindNavButtons', $documentsTabsJs);
        Assert::assertStringContainsString('filterVisaDocumentsByMatter', $documentsTabsJs);
        Assert::assertStringContainsString('rebindClientDocumentTab', $documentsTabsJs);
        Assert::assertStringContainsString('function bootDocumentTabIfNeeded', $documentsTabsJs);
        Assert::assertStringContainsString('function bootAllDocumentTabsIfNeeded', $documentsTabsJs);
        Assert::assertStringContainsString('hideDocumentGridView', $documentsTabsJs);
        Assert::assertStringContainsString(".grid_data').hide()", $documentsTabsJs);

        $notesTabJs = file_get_contents($this->projectPath('public/js/crm/clients/notes-tab.js'));
        Assert::assertNotFalse($notesTabJs);
        Assert::assertStringContainsString('window.ensureNotesTabLoaded', $notesTabJs);
        Assert::assertStringContainsString('filterNotes', $notesTabJs);
        Assert::assertStringContainsString('ensureAllTabActive', $notesTabJs);

        $notesBlade = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/notes.blade.php'));
        Assert::assertNotFalse($notesBlade);
        Assert::assertStringContainsString('window.filterNotes', $notesBlade);
        Assert::assertStringContainsString('pinnote', $notesBlade);
        Assert::assertStringContainsString('data-subtab8="All"', $notesBlade);
        Assert::assertStringContainsString('data-notes-scope', $notesBlade);
        Assert::assertStringContainsString("getElementById('sel_matter_id_client_detail')", $notesBlade);
    }

    #[Test]
    public function client_detail_page_still_eager_includes_personal_details_and_non_lazy_tabs(): void
    {
        $detail = file_get_contents($this->projectPath('resources/views/crm/clients/detail.blade.php'));
        Assert::assertNotFalse($detail);

        foreach ([
            'crm.clients.tabs.personal_details',
            'crm.clients.tabs.activityfeed_tab',
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
        Assert::assertStringContainsString("@include('crm.clients.tabs.account_lazy')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'account'", $detail);
        Assert::assertSame(0, preg_match("/@include\\('crm\\.clients\\.tabs\\.account'\\)/", $detail));
        Assert::assertStringContainsString("@include('crm.clients.tabs.checklists_lazy')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'checklists'", $detail);
        Assert::assertSame(0, preg_match("/@include\\('crm\\.clients\\.tabs\\.checklists'\\)/", $detail));
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'emails'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.emails')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.emails_lazy')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.personal_documents_lazy')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'personaldocuments'", $detail);
        Assert::assertSame(0, preg_match("/@include\\('crm\\.clients\\.tabs\\.personal_documents'\\)/", $detail));
        Assert::assertStringContainsString("@include('crm.clients.tabs.visa_documents_lazy')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'visadocuments'", $detail);
        Assert::assertSame(0, preg_match("/@include\\('crm\\.clients\\.tabs\\.visa_documents'\\)/", $detail));
        Assert::assertStringContainsString("@include('crm.clients.tabs.not_used_documents_lazy')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'notuseddocuments'", $detail);
        Assert::assertSame(0, preg_match("/@include\\('crm\\.clients\\.tabs\\.not_used_documents'\\)/", $detail));
        Assert::assertStringContainsString("(\$activeTab ?? '') === 'noteterm'", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.notes')", $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.notes_lazy')", $detail);

        Assert::assertStringContainsString("@include('crm.clients.tabs.personal_details')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'personaldetails'", $detail);
        Assert::assertStringNotContainsString('personal_details_lazy', $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.activityfeed_tab')", $detail);
        Assert::assertStringNotContainsString("(\$activeTab ?? '') === 'activityfeed'", $detail);
        Assert::assertStringNotContainsString('activityfeed_tab_lazy', $detail);
        Assert::assertStringNotContainsString('activity_feed_lazy', $detail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.activity_feed')", $detail);
        Assert::assertStringContainsString('id="client-sidebar"', $detail);
        Assert::assertStringContainsString('class="sidebar-matter-selection"', $detail);
        Assert::assertStringContainsString('id="sel_matter_id_client_detail"', $detail);

        $companyDetail = file_get_contents($this->projectPath('resources/views/crm/companies/detail.blade.php'));
        Assert::assertNotFalse($companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.account')", $companyDetail);
        Assert::assertStringNotContainsString('account_lazy', $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.checklists')", $companyDetail);
        Assert::assertStringNotContainsString('checklists_lazy', $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.emails')", $companyDetail);
        Assert::assertStringNotContainsString('emails_lazy', $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.personal_documents'", $companyDetail);
        Assert::assertStringNotContainsString('personal_documents_lazy', $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.not_used_documents')", $companyDetail);
        Assert::assertStringNotContainsString('not_used_documents_lazy', $companyDetail);
        Assert::assertStringNotContainsString('visa_documents_lazy', $companyDetail);
        Assert::assertStringContainsString("@include('crm.clients.tabs.notes')", $companyDetail);
        Assert::assertStringNotContainsString('notes_lazy', $companyDetail);

        $followups = file_get_contents($this->projectPath('app/Traits/ClientCrmFollowups.php'));
        Assert::assertNotFalse($followups);
        Assert::assertStringContainsString('ClientDetailTabs::isKnownSlug', $followups);
        Assert::assertSame(7, substr_count($followups, 'ClientDetailTabs::isKnownSlug'));
        Assert::assertStringContainsString('function accountTab', $followups);
        Assert::assertStringContainsString('function checklistsTab', $followups);
        Assert::assertStringContainsString('function emailsTab', $followups);
        Assert::assertStringContainsString('function personalDocumentsTab', $followups);
        Assert::assertStringContainsString('function visaDocumentsTab', $followups);
        Assert::assertStringContainsString('function notUsedDocumentsTab', $followups);
        Assert::assertStringContainsString('function notesTab', $followups);
        Assert::assertStringContainsString("shouldEagerRender('noteterm'", $followups);
        Assert::assertStringContainsString('deferredFragmentSlugs', $followups);
        Assert::assertStringContainsString('function clientDetailDocumentTabPayload', $followups);
        Assert::assertStringContainsString('ClientDetailDocumentsTab::personalDocumentsByFolder', $followups);
        Assert::assertStringContainsString('ClientDetailDocumentsTab::visaDocumentsByFolder', $followups);
        Assert::assertStringContainsString('ClientDetailDocumentsTab::notUsedDocuments', $followups);
    }

    #[Test]
    public function detail_action_skips_account_docs_and_checklist_payloads(): void
    {
        Assert::assertSame([
            'account',
            'checklists',
            'personaldocuments',
            'visadocuments',
            'notuseddocuments',
        ], ClientDetailTabs::deferredFragmentSlugs());

        foreach (ClientDetailTabs::deferredFragmentSlugs() as $slug) {
            Assert::assertTrue(ClientDetailTabs::isDeferredFragment($slug));
            Assert::assertTrue(ClientDetailTabs::isLazyFragmentSlug($slug));
            Assert::assertFalse(ClientDetailTabs::shouldEagerRender($slug, $slug));
            Assert::assertFalse(ClientDetailTabs::isAlwaysEager($slug));
        }

        Assert::assertFalse(ClientDetailTabs::isDeferredFragment('emails'));
        Assert::assertFalse(ClientDetailTabs::isDeferredFragment('noteterm'));
        Assert::assertFalse(ClientDetailTabs::isDeferredFragment('workflow'));
        Assert::assertFalse(ClientDetailTabs::isDeferredFragment(null));

        $followups = file_get_contents($this->projectPath('app/Traits/ClientCrmFollowups.php'));
        Assert::assertNotFalse($followups);
        $detailStart = strpos($followups, 'public function detail(');
        $workflowStart = strpos($followups, 'public function workflowTab(');
        Assert::assertNotFalse($detailStart);
        Assert::assertNotFalse($workflowStart);
        Assert::assertGreaterThan($detailStart, $workflowStart);
        $detailMethod = substr($followups, $detailStart, $workflowStart - $detailStart);

        Assert::assertStringContainsString('buildClientDetailMatterContext', $detailMethod);
        Assert::assertStringContainsString('ClientAddress::', $detailMethod);
        Assert::assertStringContainsString('ClientContact::', $detailMethod);
        Assert::assertStringContainsString('ClientEmail::', $detailMethod);
        Assert::assertStringContainsString('ClientQualification::', $detailMethod);
        Assert::assertStringNotContainsString('ClientDetailAccountTab::build', $detailMethod);
        Assert::assertStringNotContainsString('ClientDetailChecklistsTab::build', $detailMethod);
        Assert::assertStringNotContainsString('AccountClientReceipt::', $detailMethod);
        Assert::assertStringNotContainsString('CostAssignmentForm::', $detailMethod);
        Assert::assertStringNotContainsString('Document::', $detailMethod);
        Assert::assertStringNotContainsString('PersonalDocumentType::', $detailMethod);

        Assert::assertStringContainsString('ClientDetailAccountTab::build', $followups);
        Assert::assertStringContainsString('ClientDetailChecklistsTab::build', $followups);
        Assert::assertStringContainsString("'accountTabPayload'", $followups);
        Assert::assertStringContainsString("'checklistsTabPayload'", $followups);
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
        Assert::assertStringContainsString('data-tab="notuseddocuments"', file_get_contents($this->projectPath('resources/views/crm/clients/tabs/personal_documents.blade.php')));
        Assert::assertStringContainsString(
            'class="grid_data griddata_',
            file_get_contents($this->projectPath('resources/views/crm/clients/tabs/personal_documents.blade.php'))
        );
        Assert::assertStringContainsString(
            'style="display:none;"',
            file_get_contents($this->projectPath('resources/views/crm/clients/tabs/personal_documents.blade.php'))
        );

        $visaDocsLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/visa_documents_lazy.blade.php'));
        Assert::assertNotFalse($visaDocsLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('visadocuments').'"', $visaDocsLazy);

        $notUsedLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/not_used_documents_lazy.blade.php'));
        Assert::assertNotFalse($notUsedLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('notuseddocuments').'"', $notUsedLazy);

        $notesLazy = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/notes_lazy.blade.php'));
        Assert::assertNotFalse($notesLazy);
        Assert::assertStringContainsString('id="'.ClientDetailTabs::paneId('noteterm').'"', $notesLazy);

        $lazyLoadingPartial = $this->projectPath('resources/views/crm/clients/tabs/partials/lazy_loading.blade.php');
        Assert::assertFileExists($lazyLoadingPartial);
        $lazyLoading = file_get_contents($lazyLoadingPartial);
        Assert::assertNotFalse($lazyLoading);
        Assert::assertStringContainsString("URL::asset('img/spinner.svg')", $lazyLoading);

        foreach ([
            'workflow_lazy.blade.php',
            'client_portal_lazy.blade.php',
            'account_lazy.blade.php',
            'checklists_lazy.blade.php',
            'emails_lazy.blade.php',
            'notes_lazy.blade.php',
            'personal_documents_lazy.blade.php',
            'visa_documents_lazy.blade.php',
            'not_used_documents_lazy.blade.php',
        ] as $lazyBlade) {
            $contents = file_get_contents($this->projectPath('resources/views/crm/clients/tabs/'.$lazyBlade));
            Assert::assertNotFalse($contents, $lazyBlade);
            Assert::assertStringContainsString(
                'crm.clients.tabs.partials.lazy_loading',
                $contents,
                $lazyBlade.' must show the shared loader image + text'
            );
        }
    }

    #[Test]
    public function v1_keeps_personal_details_activity_feed_and_matter_context_eager(): void
    {
        Assert::assertSame(['personaldetails', 'activityfeed'], ClientDetailTabs::alwaysEagerSlugs());
        Assert::assertTrue(ClientDetailTabs::isAlwaysEager('personaldetails'));
        Assert::assertTrue(ClientDetailTabs::isAlwaysEager('ActivityFeed'));
        Assert::assertFalse(ClientDetailTabs::isAlwaysEager('noteterm'));
        Assert::assertFalse(ClientDetailTabs::isAlwaysEager(null));

        foreach (ClientDetailTabs::alwaysEagerSlugs() as $slug) {
            Assert::assertFalse(
                ClientDetailTabs::isLazyFragmentSlug($slug),
                $slug.' must not have a lazy fragment route in v1'
            );
        }

        foreach (array_keys(ClientDetailTabs::fragmentRouteNames()) as $slug) {
            Assert::assertFalse(
                ClientDetailTabs::isAlwaysEager($slug),
                $slug.' is lazy and must not be listed as always-eager'
            );
        }

        foreach ([
            'crm/clients/tabs/personal_details_lazy.blade.php',
            'crm/clients/tabs/activityfeed_tab_lazy.blade.php',
            'crm/clients/tabs/activity_feed_lazy.blade.php',
        ] as $relativePath) {
            Assert::assertFileDoesNotExist(
                $this->projectPath('resources/views/'.$relativePath),
                'v1 must not add a lazy stub: '.$relativePath
            );
        }

        $routes = file_get_contents($this->projectPath('routes/clients.php'));
        Assert::assertNotFalse($routes);
        Assert::assertStringNotContainsString('detail-personal-details-tab', $routes);
        Assert::assertStringNotContainsString('detail-activity-feed-tab', $routes);
        Assert::assertStringNotContainsString('detail-activityfeed-tab', $routes);

        $sidebarTabs = file_get_contents($this->projectPath('public/js/crm/clients/sidebar-tabs.js'));
        Assert::assertNotFalse($sidebarTabs);
        Assert::assertStringNotContainsString('ensurePersonalDetailsTabLoaded', $sidebarTabs);
        Assert::assertStringNotContainsString('ensureActivityFeedTabLoaded', $sidebarTabs);
        Assert::assertStringContainsString('#sel_matter_id_client_detail', $sidebarTabs);
    }

    private function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
