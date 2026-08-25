<?php

namespace App\Support;

/**
 * Client-detail sidebar tab contract.
 *
 * Pane DOM ids, URL slugs, and fragment route names must stay stable:
 * sidebar-tabs.js, matter-change filters, and deep links depend on them.
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
            'personaldocuments' => 'clients.detail.personaldocuments-tab',
            'visadocuments' => 'clients.detail.visadocuments-tab',
            'notuseddocuments' => 'clients.detail.notuseddocuments-tab',
            'noteterm' => 'clients.detail.noteterm-tab',
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
            'js/crm/clients/personaldocuments-tab.js',
            'js/crm/clients/visadocuments-tab.js',
            'js/crm/clients/notuseddocuments-tab.js',
            'js/crm/clients/notes-tab.js',
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

    public static function shouldEagerRender(string $slug, ?string $activeTab): bool
    {
        return strtolower((string) $activeTab) === strtolower($slug);
    }

    /**
     * View keys that must not be built in ClientsController::detail().
     * Those tabs load via fragment routes or eager-if-active blades that self-build.
     *
     * @return list<string>
     */
    public static function detailDeferredViewKeys(): array
    {
        return [
            'clientNotes',
            'accountTabPayload',
            'checklistsTabPayload',
        ];
    }

    /**
     * ORDER BY equivalent of "{column} {direction} NULLS LAST" on Postgres and SQLite.
     * Pass a trusted identifier only — never user input.
     */
    public static function nullsLastSql(string $column, string $direction = 'desc'): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new \InvalidArgumentException('Invalid SQL column identifier.');
        }

        $dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        return "({$column} IS NULL) ASC, {$column} {$dir}";
    }
}
