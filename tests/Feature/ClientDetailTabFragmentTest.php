<?php

namespace Tests\Feature;

use App\Support\ClientDetailTabs;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tab-contract checks only. HTTP fragment tests are omitted here because the
 * client-portal query uses Postgres NULLS LAST and the account-tab route is
 * not registered on this branch.
 */
class ClientDetailTabFragmentTest extends TestCase
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
        Assert::assertTrue(ClientDetailTabs::isKnownSlug('account'));
        Assert::assertFalse(ClientDetailTabs::isKnownSlug('APC_3'));
        Assert::assertFalse(ClientDetailTabs::isKnownSlug(null));
        Assert::assertFalse(ClientDetailTabs::isKnownSlug(''));
    }

    #[Test]
    public function pane_ids_match_the_existing_sidebar_dom_contract(): void
    {
        Assert::assertSame('workflow-tab', ClientDetailTabs::paneId('workflow'));
        Assert::assertSame('client_portal-tab', ClientDetailTabs::paneId('client_portal'));
        Assert::assertSame('account-tab', ClientDetailTabs::paneId('account'));
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
    public function deep_link_eager_renders_only_the_active_lazy_tab(): void
    {
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('workflow', 'workflow'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('workflow', 'personaldetails'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('client_portal', 'client_portal'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('client_portal', 'account'));
        Assert::assertTrue(ClientDetailTabs::shouldEagerRender('account', 'account'));
        Assert::assertFalse(ClientDetailTabs::shouldEagerRender('account', 'personaldetails'));
    }
}
