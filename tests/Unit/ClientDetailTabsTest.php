<?php

namespace Tests\Unit;

use App\Support\ClientDetailTabs;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Client-detail tab contract. Avoids Laravel helpers/facades so Intelephense
 * can resolve assertions. Does not register an account-tab fragment route.
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
    }

    #[Test]
    public function fragment_route_names_cover_registered_lazy_tabs_only(): void
    {
        Assert::assertSame([
            'workflow' => 'clients.detail.workflow-tab',
            'client_portal' => 'clients.detail.client-portal-tab',
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
        Assert::assertStringNotContainsString("@include('crm.clients.tabs.account_lazy')", $detail);
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
    }

    private function projectPath(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
