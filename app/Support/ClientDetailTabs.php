<?php

namespace App\Support;

/**
 * Client-detail sidebar tab contract.
 *
 * Pane DOM ids, URL slugs, and fragment route names must stay stable:
 * sidebar-tabs.js, matter-change filters, and deep links depend on them.
 *
 * v1 must keep Personal Details, Activity feed, and sidebar/matter context
 * (#client-sidebar, #sel_matter_id_client_detail) in the first HTML payload.
 * Do not add fragment routes or *_lazy blades for those.
 *
 * Account, documents, and checklists never eager-render from detail(): their
 * queries run only on the fragment actions. Deep links boot those stubs via JS.
 */
final class ClientDetailTabs
{
    /**
     * URL slugs that must never be treated as a matter reference.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return [
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
        ];
    }

    /**
     * Sidebar tab pane DOM ids. Do not rename — JS and CSS target these.
     *
     * @return array<string, string>
     */
    public static function paneIds(): array
    {
        return [
            'personaldetails' => 'personaldetails-tab',
            'activityfeed' => 'activityfeed-tab',
            'noteterm' => 'noteterm-tab',
            'personaldocuments' => 'personaldocuments-tab',
            'visadocuments' => 'visadocuments-tab',
            'nominationdocuments' => 'nominationdocuments-tab',
            'eoiroi' => 'eoiroi-tab',
            'emails' => 'emails-tab',
            'account' => 'account-tab',
            'checklists' => 'checklists-tab',
            'workflow' => 'workflow-tab',
            'client_portal' => 'client_portal-tab',
            'notuseddocuments' => 'notuseddocuments-tab',
        ];
    }

    /**
     * Named routes that return a tab HTML fragment (lazy load).
     *
     * @return array<string, string>
     */
    public static function fragmentRouteNames(): array
    {
        return [
            'workflow' => 'clients.detail.workflow-tab',
            'client_portal' => 'clients.detail.client-portal-tab',
            'account' => 'clients.detail.account-tab',
            'checklists' => 'clients.detail.checklists-tab',
            'emails' => 'clients.detail.emails-tab',
            'personaldocuments' => 'clients.detail.personal-documents-tab',
            'visadocuments' => 'clients.detail.visa-documents-tab',
            'notuseddocuments' => 'clients.detail.not-used-documents-tab',
            'noteterm' => 'clients.detail.notes-tab',
        ];
    }

    /**
     * Always-loaded JS files for client detail tab switching. Do not rename.
     *
     * @return list<string>
     */
    public static function tabScriptFilenames(): array
    {
        return [
            'js/crm/clients/sidebar-tabs.js',
            'js/crm/clients/detail-main.js',
            'js/crm/clients/workflow-tab.js',
            'js/crm/clients/account-tab.js',
            'js/crm/clients/checklists-tab.js',
            'js/crm/clients/emails-tab.js',
            'js/crm/clients/documents-tabs.js',
            'js/crm/clients/notes-tab.js',
        ];
    }

    /**
     * Tabs that must stay in the first HTML payload (v1). Not fragment-loaded.
     *
     * @return list<string>
     */
    public static function alwaysEagerSlugs(): array
    {
        return [
            'personaldetails',
            'activityfeed',
        ];
    }

    /**
     * Fragment tabs whose queries must not run in the main detail() action.
     * First paint always uses the *_lazy stub; JS loads the fragment on open/deep-link.
     *
     * @return list<string>
     */
    public static function deferredFragmentSlugs(): array
    {
        return [
            'account',
            'checklists',
            'personaldocuments',
            'visadocuments',
            'notuseddocuments',
        ];
    }

    public static function paneId(string $slug): string
    {
        return self::paneIds()[$slug] ?? $slug.'-tab';
    }

    public static function isKnownSlug(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower($value), self::slugs(), true);
    }

    public static function isAlwaysEager(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower($value), self::alwaysEagerSlugs(), true);
    }

    public static function isLazyFragmentSlug(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return array_key_exists(strtolower($value), self::fragmentRouteNames());
    }

    public static function isDeferredFragment(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array(strtolower($value), self::deferredFragmentSlugs(), true);
    }

    public static function shouldEagerRender(string $slug, ?string $activeTab): bool
    {
        if (self::isDeferredFragment($slug)) {
            return false;
        }

        return strtolower((string) $activeTab) === strtolower($slug);
    }
}
