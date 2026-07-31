<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\BookingAppointment;
use App\Models\ClientAccessGrant;
use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Row-level visibility for CRM staff.
 *
 * Matter allocation: access is granted when the staff member appears on any client_matters row
 * for that CRM record (admins.id) as sel_migration_agent, sel_person_responsible, or
 * sel_person_assisting — in addition to admins.user_id and active cross-access grants.
 *
 * Cross-access grants and strict allocation are controlled by config/crm_access.php
 * (CRM_ACCESS_STRICT_ALLOCATION, exempt roles, approvers, quick access).
 */
final class StaffClientVisibility
{
    private const DEFAULT_PERSON_ASSISTING_ROLE_IDS = [13];

    /**
     * Roles that get full lead visibility in non-strict mode.
     * In strict mode (CRM_ACCESS_STRICT_ALLOCATION=true) this list is IGNORED and
     * everyone (including PR role 12) is subject to allocation + grant checks.
     * Must stay aligned with config/crm.php.
     *
     * @see userMaySeeByAllocation for the strict-mode enforcement path
     */
    private const DEFAULT_LEAD_FULL_ACCESS_ROLE_IDS = [1, 12, 17];

    public static function personAssistingRoleIds(): array
    {
        $ids = config('crm.person_assisting_role_ids', self::DEFAULT_PERSON_ASSISTING_ROLE_IDS);
        $filtered = array_values(array_filter(
            array_map('intval', (array) $ids),
            static fn (int $id) => $id > 0
        ));

        return $filtered !== [] ? $filtered : self::DEFAULT_PERSON_ASSISTING_ROLE_IDS;
    }

    /**
     * @return list<int>
     */
    public static function leadFullAccessRoleIds(): array
    {
        $ids = config('crm.lead_full_access_role_ids', self::DEFAULT_LEAD_FULL_ACCESS_ROLE_IDS);
        $filtered = array_values(array_filter(
            array_map('intval', (array) $ids),
            static fn (int $id) => $id > 0
        ));

        return $filtered !== [] ? $filtered : self::DEFAULT_LEAD_FULL_ACCESS_ROLE_IDS;
    }

    /**
     * @return array{status: bool, message: string, error_type: string}
     */
    public static function unauthorizedPayload(): array
    {
        return [
            'status' => false,
            'message' => 'Unauthorized',
            'error_type' => 'forbidden',
        ];
    }

    public static function isRestrictedPersonAssisting(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) ($user->role ?? 0) === 1) {
            return false;
        }

        return in_array((int) $user->role, self::personAssistingRoleIds(), true);
    }

    public static function isExemptFromAllocation(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user instanceof Staff && app(CrmAccessService::class)->hasGrantedSuperAdminLevelAccess($user)) {
            return true;
        }

        $staffId = (int) ($user->id ?? 0);
        if ($staffId > 0 && in_array($staffId, config('crm_access.exempt_staff_ids', []), true)) {
            return true;
        }

        return in_array((int) ($user->role ?? 0), config('crm_access.exempt_role_ids', [1, 17]), true);
    }

    public static function isQuickAccessOnly(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array((int) ($user->role ?? 0), config('crm_access.quick_access_only_role_ids', [14]), true);
    }

    /**
     * @return array{show_quick: bool, show_supervisor: bool}
     */
    public static function crossAccessUiFlags(?Authenticatable $user): array
    {
        if (! $user || self::isExemptFromAllocation($user)) {
            return ['show_quick' => false, 'show_supervisor' => false];
        }

        if (self::isQuickAccessOnly($user)) {
            $enabled = (bool) ($user->quick_access_enabled ?? false);

            return ['show_quick' => $enabled, 'show_supervisor' => false];
        }

        $quick = (bool) ($user->quick_access_enabled ?? false);

        return ['show_quick' => $quick, 'show_supervisor' => true];
    }

    /**
     * @param  array<string, mixed>  $item  Must include 'cid'
     * @return array<string, mixed>
     */
    public static function enrichGlobalSearchItem(array $item, string $recordType, ?Authenticatable $user = null): array
    {
        $user = $user ?? Auth::user();
        $cid = (int) ($item['cid'] ?? 0);
        if ($cid <= 0 || ! $user) {
            $item['locked'] = false;
            $item['record_type'] = $recordType;
            $item['access_ui'] = ['show_quick' => false, 'show_supervisor' => false];

            return $item;
        }

        $can = self::canAccessClientOrLead($cid, $user);
        $item['locked'] = ! $can;
        $item['record_type'] = $recordType;
        $item['access_ui'] = $can
            ? ['show_quick' => false, 'show_supervisor' => false]
            : self::crossAccessUiFlags($user);

        return $item;
    }

    /**
     * Same visibility rules as repeated {@see enrichGlobalSearchItem}, but one access map for all rows.
     *
     * @param  list<array<string, mixed>>  $items  Each must include `cid` and `record_type`
     * @return list<array<string, mixed>>
     */
    public static function enrichGlobalSearchItemsBatch(array $items, ?Authenticatable $user = null): array
    {
        $user = $user ?? Auth::user();
        $cids = [];
        foreach ($items as $it) {
            $cid = (int) ($it['cid'] ?? 0);
            if ($cid > 0) {
                $cids[] = $cid;
            }
        }
        $accessMap = self::globalSearchCanAccessMap($cids, $user);

        foreach ($items as &$item) {
            $cid = (int) ($item['cid'] ?? 0);
            $recordType = (string) ($item['record_type'] ?? 'client');
            if ($cid <= 0 || ! $user) {
                $item['locked'] = false;
                $item['record_type'] = $recordType;
                $item['access_ui'] = ['show_quick' => false, 'show_supervisor' => false];

                continue;
            }
            $can = $accessMap[$cid] ?? false;
            $item['locked'] = ! $can;
            $item['record_type'] = $recordType;
            $item['access_ui'] = $can
                ? ['show_quick' => false, 'show_supervisor' => false]
                : self::crossAccessUiFlags($user);
        }
        unset($item);

        return $items;
    }

    /**
     * Batch equivalent of {@see canAccessClientOrLead} for global search candidates.
     *
     * @param  list<int>  $adminIds
     * @return array<int, bool>
     */
    public static function globalSearchCanAccessMap(array $adminIds, ?Authenticatable $user = null): array
    {
        $user = $user ?? Auth::user();
        $adminIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $adminIds),
            static fn (int $id) => $id > 0
        )));
        $out = array_fill_keys($adminIds, false);

        if (! $user || $adminIds === []) {
            return $out;
        }

        $rows = Admin::query()
            ->whereIn('id', $adminIds)
            ->whereIn('type', ['client', 'lead'])
            ->get(['id', 'type', 'user_id', 'client_id'])
            ->keyBy('id');

        $role = (int) ($user->role ?? 0);
        $staffId = (int) ($user->id ?? 0);
        $isStaff = $user instanceof Staff;

        $allocatingClientIdFlip = null;
        if (! self::isExemptFromAllocation($user)) {
            $allocatingClientIdFlip = [];
            $allocatingIds = DB::table('client_matters')
                ->whereIn('client_id', $adminIds)
                ->where(function ($q) use ($staffId) {
                    $q->where('sel_migration_agent', $staffId)
                        ->orWhere('sel_person_responsible', $staffId)
                        ->orWhere('sel_person_assisting', $staffId);
                })
                ->pluck('client_id');
            foreach ($allocatingIds as $cid) {
                $allocatingClientIdFlip[(int) $cid] = true;
            }
        }

        $grantFlip = [];
        if ($isStaff && (int) ($user->status ?? 0) === 1 && ! self::isExemptFromAllocation($user)) {
            $now = Carbon::now('UTC');
            $grantIds = ClientAccessGrant::query()
                ->where('staff_id', $staffId)
                ->whereIn('admin_id', $adminIds)
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->where('ends_at', '>', $now)
                ->pluck('admin_id');
            foreach ($grantIds as $gid) {
                $grantFlip[(int) $gid] = true;
            }
        }

        foreach ($adminIds as $id) {
            $row = $rows->get($id);
            if (! $row) {
                continue;
            }

            if (self::isSuperAdminOnlyLockedClient($row->type ?? null, $row->client_id ?? null)) {
                $out[$id] = ($role === 1);

                continue;
            }

            if (self::isExemptFromAllocation($user)) {
                if ($isStaff) {
                    self::logExemptAccessIfNeeded($staffId, $id, (string) ($row->type ?? 'client'));
                }
                $out[$id] = true;

                continue;
            }

            if ($isStaff && isset($grantFlip[$id])) {
                $out[$id] = true;

                continue;
            }

            $out[$id] = self::userMaySeeByAllocation($id, $user, $row, $allocatingClientIdFlip);
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function restrictMatterListToAllocatedClients($query, string $cmAlias = 'cm', string $adAlias = 'ad'): void
    {
        $user = Auth::user();
        if (! $user || self::isExemptFromAllocation($user)) {
            return;
        }
        $strict = (bool) config('crm_access.strict_allocation', false);
        if (! $strict && ! self::isRestrictedPersonAssisting($user)) {
            return;
        }
        $staffId = (int) $user->id;
        $query->where(function ($q) use ($staffId, $cmAlias, $adAlias) {
            $q->whereExists(function ($sub) use ($staffId, $cmAlias) {
                $sub->select(DB::raw('1'))
                    ->from('client_matters')
                    ->whereColumn('client_matters.client_id', $cmAlias . '.client_id');
                self::whereClientMatterRowAssignedToStaff($sub, $staffId);
            })->orWhere($adAlias . '.user_id', $staffId)
              ->orWhereExists(function ($sub) use ($staffId, $cmAlias) {
                  $sub->select(DB::raw('1'))
                      ->from('client_access_grants')
                      ->whereColumn('client_access_grants.admin_id', $cmAlias . '.client_id')
                      ->where('client_access_grants.staff_id', $staffId)
                      ->where('client_access_grants.status', 'active')
                      ->whereNotNull('client_access_grants.ends_at')
                      ->where('client_access_grants.ends_at', '>', Carbon::now('UTC'));
              });
        });
    }

    /**
     * @return list<string>
     */
    public static function superAdminOnlyLockedClientFileIds(): array
    {
        return config('crm.super_admin_only_client_file_ids', []);
    }

    /**
     * @return list<string> Uppercased trimmed values for case-insensitive SQL matching
     */
    public static function normalizedSuperAdminOnlyLockedClientFileIdsUpper(): array
    {
        $ids = self::superAdminOnlyLockedClientFileIds();
        $out = [];
        foreach ($ids as $id) {
            $u = strtoupper(trim((string) $id));
            if ($u !== '') {
                $out[] = $u;
            }
        }

        return array_values(array_unique($out));
    }

    public static function isSuperAdminOnlyLockedClient(?string $adminType, ?string $clientFileId): bool
    {
        if (($adminType ?? '') !== 'client') {
            return false;
        }
        $file = strtoupper(trim((string) $clientFileId));
        if ($file === '') {
            return false;
        }

        return in_array($file, self::normalizedSuperAdminOnlyLockedClientFileIdsUpper(), true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Admin>|\Illuminate\Database\Query\Builder  $query
     */
    public static function excludeSuperAdminOnlyLockedClientsFromAdminQuery($query, ?Authenticatable $viewer = null): void
    {
        $viewer = $viewer ?? Auth::user();
        if (! $viewer || (int) ($viewer->role ?? 0) === 1) {
            return;
        }

        $upperIds = self::normalizedSuperAdminOnlyLockedClientFileIdsUpper();
        if ($upperIds === []) {
            return;
        }

        $table = method_exists($query, 'getModel')
            ? $query->getModel()->getTable()
            : 'admins';

        $placeholders = implode(',', array_fill(0, count($upperIds), '?'));

        $query->where(function ($q) use ($table, $upperIds, $placeholders) {
            $q->where("{$table}.type", '!=', 'client')
                ->orWhereNull("{$table}.client_id")
                ->orWhereRaw(
                    "UPPER(TRIM({$table}.client_id)) NOT IN ({$placeholders})",
                    $upperIds
                );
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, string $adminsAlias, ?Authenticatable $viewer = null): void
    {
        $viewer = $viewer ?? Auth::user();
        if (! $viewer || (int) ($viewer->role ?? 0) === 1) {
            return;
        }

        $upperIds = self::normalizedSuperAdminOnlyLockedClientFileIdsUpper();
        if ($upperIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($upperIds), '?'));

        $query->where(function ($q) use ($adminsAlias, $upperIds, $placeholders) {
            $q->where("{$adminsAlias}.type", '!=', 'client')
                ->orWhereNull("{$adminsAlias}.client_id")
                ->orWhereRaw(
                    "UPPER(TRIM({$adminsAlias}.client_id)) NOT IN ({$placeholders})",
                    $upperIds
                );
        });
    }

    public static function personAssistingStaffIdOrNull(?Authenticatable $user): ?int
    {
        return self::isRestrictedPersonAssisting($user) ? (int) $user->id : null;
    }

    /**
     * @param  Builder<\App\Models\Lead>  $query
     */
    public static function restrictLeadListQuery(Builder $query): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        if (self::isExemptFromAllocation($user)) {
            return;
        }

        $staffId = (int) $user->id;

        if ((bool) config('crm_access.strict_allocation', false)) {
            $query->where(function (Builder $q) use ($staffId) {
                $q->whereExists(function ($sub) use ($staffId) {
                    $sub->select(DB::raw('1'))
                        ->from('client_matters')
                        ->whereColumn('client_matters.client_id', 'admins.id');
                    self::whereClientMatterRowAssignedToStaff($sub, $staffId);
                })->orWhere('admins.user_id', $staffId)
                  ->orWhereExists(function ($sub) use ($staffId) {
                      $sub->select(DB::raw('1'))
                          ->from('client_access_grants')
                          ->whereColumn('client_access_grants.admin_id', 'admins.id')
                          ->where('client_access_grants.staff_id', $staffId)
                          ->where('client_access_grants.status', 'active')
                          ->whereNotNull('client_access_grants.ends_at')
                          ->where('client_access_grants.ends_at', '>', Carbon::now('UTC'));
                  });
            });

            return;
        }

        if (in_array((int) ($user->role ?? 0), self::leadFullAccessRoleIds(), true)) {
            return;
        }

        $column = $query->getModel()->qualifyColumn('user_id');
        $query->where(function (Builder $q) use ($column, $staffId) {
            $q->where($column, $staffId)
              ->orWhereExists(function ($sub) use ($staffId) {
                  $sub->select(DB::raw('1'))
                      ->from('client_matters')
                      ->whereColumn('client_matters.client_id', 'admins.id');
                  self::whereClientMatterRowAssignedToStaff($sub, $staffId);
              })
              ->orWhereExists(function ($sub) use ($staffId) {
                  $sub->select(DB::raw('1'))
                      ->from('client_access_grants')
                      ->whereColumn('client_access_grants.admin_id', 'admins.id')
                      ->where('client_access_grants.staff_id', $staffId)
                      ->where('client_access_grants.status', 'active')
                      ->whereNotNull('client_access_grants.ends_at')
                      ->where('client_access_grants.ends_at', '>', Carbon::now('UTC'));
              });
        });
    }

    /**
     * @param  Builder<\App\Models\Admin>  $query
     */
    public static function restrictAdminEloquentQuery(Builder $query): void
    {
        $user = Auth::user();
        self::excludeSuperAdminOnlyLockedClientsFromAdminQuery($query, $user);

        if (! $user || self::isExemptFromAllocation($user)) {
            return;
        }

        $strict = (bool) config('crm_access.strict_allocation', false);
        if (! $strict && ! self::isRestrictedPersonAssisting($user)) {
            return;
        }

        $staffId = (int) $user->id;

        $query->where(function (Builder $q) use ($staffId) {
            // Allocated by matter (MA / PR / PA) or user_id
            $q->whereExists(function ($sub) use ($staffId) {
                $sub->select(DB::raw('1'))
                    ->from('client_matters')
                    ->whereColumn('client_matters.client_id', 'admins.id');
                self::whereClientMatterRowAssignedToStaff($sub, $staffId);
            })->orWhere('admins.user_id', $staffId)
              // OR has an active cross-access grant
              ->orWhereExists(function ($sub) use ($staffId) {
                  $sub->select(DB::raw('1'))
                      ->from('client_access_grants')
                      ->whereColumn('client_access_grants.admin_id', 'admins.id')
                      ->where('client_access_grants.staff_id', $staffId)
                      ->where('client_access_grants.status', 'active')
                      ->whereNotNull('client_access_grants.ends_at')
                      ->where('client_access_grants.ends_at', '>', Carbon::now('UTC'));
              });
        });
    }

    public static function canAccessClientOrLead(int $adminId, ?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return false;
        }

        $row = Admin::query()
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->first(['id', 'type', 'user_id', 'client_id']);

        if (! $row) {
            return false;
        }

        if (self::isSuperAdminOnlyLockedClient($row->type ?? null, $row->client_id ?? null)) {
            return (int) ($user->role ?? 0) === 1;
        }

        if (self::isExemptFromAllocation($user)) {
            if ($user instanceof Staff) {
                self::logExemptAccessIfNeeded((int) $user->id, $adminId, (string) ($row->type ?? 'client'));
            }

            return true;
        }

        if ($user instanceof Staff) {
            $svc = app(CrmAccessService::class);
            if ($svc->hasActiveGrant($user, $adminId)) {
                return true;
            }
        }

        return self::userMaySeeByAllocation($adminId, $user, $row);
    }

    /**
     * Write one exempt audit row per calendar day (UTC) per staff + admin combo.
     * Silently swallowed on failure to avoid disrupting the main request.
     */
    private static function logExemptAccessIfNeeded(int $staffId, int $adminId, string $recordType): void
    {
        try {
            // Range on timestamptz uses the "exempt dedup" partial index; same UTC calendar day as whereDate(created_at, today).
            $dayStart = Carbon::now('UTC')->startOfDay();
            $dayEnd = Carbon::now('UTC')->copy()->addDay()->startOfDay();
            $exists = ClientAccessGrant::query()
                ->where('staff_id', $staffId)
                ->where('admin_id', $adminId)
                ->where('grant_type', 'exempt')
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->exists();

            if (! $exists) {
                $rt = in_array($recordType, ['client', 'lead'], true) ? $recordType : 'client';
                ClientAccessGrant::query()->create([
                    'staff_id' => $staffId,
                    'admin_id' => $adminId,
                    'record_type' => $rt,
                    'grant_type' => 'exempt',
                    'access_type' => 'exempt',
                    'status' => 'active',
                    'requested_at' => Carbon::now('UTC'),
                    'starts_at' => Carbon::now('UTC'),
                ]);
            }
        } catch (\Throwable) {
            // Never disrupt the main access check
        }
    }

    /**
     * Limit signature / document listings to records the viewer may access (client_id / lead_id on documents).
     *
     * Uses the model's own table name (via qualifyColumn) so callers that join the documents
     * table under an alias, or that use a different primary table, still get correct SQL.
     *
     * @param  Builder<\App\Models\Document>  $query
     */
    public static function restrictDocumentEloquentQuery(Builder $query, ?Authenticatable $user = null): void
    {
        $user = $user ?? Auth::user();
        if (! $user || self::isExemptFromAllocation($user)) {
            return;
        }

        $strict = (bool) config('crm_access.strict_allocation', false);
        if (! $strict && ! self::isRestrictedPersonAssisting($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $model   = $query->getModel();
        $table   = $model->getTable();

        $query->where(function (Builder $outer) use ($staffId, $table) {
            $outer->where(function (Builder $q) use ($table) {
                $q->whereNull("{$table}.client_id")->whereNull("{$table}.lead_id");
            });

            foreach (['client_id', 'lead_id'] as $col) {
                $outer->orWhere(function (Builder $docQ) use ($staffId, $col, $table) {
                    $docQ->whereNotNull("{$table}.{$col}")
                        ->where(function (Builder $accessQ) use ($staffId, $col, $table) {
                            $accessQ->whereExists(function ($sub) use ($staffId, $col, $table) {
                                $sub->select(DB::raw('1'))
                                    ->from('client_matters')
                                    ->whereColumn('client_matters.client_id', "{$table}.{$col}");
                                self::whereClientMatterRowAssignedToStaff($sub, $staffId);
                            })->orWhereExists(function ($sub) use ($staffId, $col, $table) {
                                $sub->select(DB::raw('1'))
                                    ->from('admins')
                                    ->whereColumn('admins.id', "{$table}.{$col}")
                                    ->where('admins.user_id', $staffId);
                            })->orWhereExists(function ($sub) use ($staffId, $col, $table) {
                                $sub->select(DB::raw('1'))
                                    ->from('client_access_grants')
                                    ->whereColumn('client_access_grants.admin_id', "{$table}.{$col}")
                                    ->where('client_access_grants.staff_id', $staffId)
                                    ->where('client_access_grants.status', 'active')
                                    ->whereNotNull('client_access_grants.ends_at')
                                    ->where('client_access_grants.ends_at', '>', Carbon::now('UTC'));
                            });
                        });
                });
            }
        });
    }

    /**
     * Limit booking appointment listings to clients the viewer may access (linked CRM client_id).
     *
     * Rows with no linked client remain visible (public / unlinked bookings).
     *
     * @param  Builder<\App\Models\BookingAppointment>  $query
     */
    public static function restrictBookingAppointmentEloquentQuery(Builder $query, ?Authenticatable $user = null): void
    {
        $user = $user ?? Auth::user();
        if (! $user || self::isExemptFromAllocation($user)) {
            return;
        }

        $strict = (bool) config('crm_access.strict_allocation', false);
        if (! $strict && ! self::isRestrictedPersonAssisting($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $table   = $query->getModel()->getTable();

        $query->where(function (Builder $outer) use ($staffId, $table) {
            $outer->whereNull("{$table}.client_id")
                ->orWhere("{$table}.client_id", '<=', 0);

            $outer->orWhere(function (Builder $inner) use ($staffId, $table) {
                $inner->where("{$table}.client_id", '>', 0)
                    ->where(function (Builder $accessQ) use ($staffId, $table) {
                        $accessQ->whereExists(function ($sub) use ($staffId, $table) {
                            $sub->select(DB::raw('1'))
                                ->from('client_matters')
                                ->whereColumn('client_matters.client_id', "{$table}.client_id");
                            self::whereClientMatterRowAssignedToStaff($sub, $staffId);
                        })->orWhereExists(function ($sub) use ($staffId, $table) {
                            $sub->select(DB::raw('1'))
                                ->from('admins')
                                ->whereColumn('admins.id', "{$table}.client_id")
                                ->where('admins.user_id', $staffId);
                        })->orWhereExists(function ($sub) use ($staffId, $table) {
                            $sub->select(DB::raw('1'))
                                ->from('client_access_grants')
                                ->whereColumn('client_access_grants.admin_id', "{$table}.client_id")
                                ->where('client_access_grants.staff_id', $staffId)
                                ->where('client_access_grants.status', 'active')
                                ->whereNotNull('client_access_grants.ends_at')
                                ->where('client_access_grants.ends_at', '>', Carbon::now('UTC'));
                        });
                    });
            });
        });
    }

    /**
     * Abort 403 unless this booking row would pass restrictBookingAppointmentEloquentQuery (same rule as calendar/list API).
     * Keeps write access aligned with what the user can see on the booking calendar.
     */
    public static function abortUnlessMayAccessBookingAppointment(BookingAppointment $appointment, ?Authenticatable $user = null): void
    {
        $query = BookingAppointment::query()->whereKey($appointment->getKey());
        self::restrictBookingAppointmentEloquentQuery($query, $user);
        if ($query->exists()) {
            return;
        }

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(
                response()->json(self::unauthorizedPayload(), 403)
            );
        }

        abort(403, 'Unauthorized');
    }

    /**
     * @param  \App\Models\Admin  $row
     * @param  array<int, bool>|null  $allocatingClientIdFlip  When non-null, replaces per-call EXISTS for allocation (client_id => true)
     */
    private static function userMaySeeByAllocation(int $adminId, Authenticatable $user, Admin $row, ?array $allocatingClientIdFlip = null): bool
    {
        $strict = (bool) config('crm_access.strict_allocation', false);
        $staffId = (int) $user->id;
        $hasAllocatingMatter = $allocatingClientIdFlip !== null
            ? isset($allocatingClientIdFlip[$adminId])
            : self::clientHasAllocatingMatter($adminId, $staffId);

        if (($row->type ?? '') === 'lead') {
            if (! $strict) {
                // Non-strict mode: PR (role 12) and other full-access roles see all leads.
                // When strict_allocation = true, ALL non-exempt roles (including PR 12) fall
                // through to the allocation check below — enforcing plan §2 / §9.
                if (! self::isRestrictedPersonAssisting($user)) {
                    if (in_array((int) ($user->role ?? 0), self::leadFullAccessRoleIds(), true)) {
                        return true;
                    }
                }
                if ($hasAllocatingMatter) {
                    return true;
                }

                return (int) ($row->user_id ?? 0) === $staffId;
            }

            // Strict mode: allocation-only regardless of role (PR 12 included).
            if ($hasAllocatingMatter) {
                return true;
            }

            return (int) ($row->user_id ?? 0) === $staffId;
        }

        if (! $strict) {
            if (! self::isRestrictedPersonAssisting($user)) {
                return true;
            }
            if ($hasAllocatingMatter) {
                return true;
            }

            return (int) ($row->user_id ?? 0) === $staffId;
        }

        if ($hasAllocatingMatter) {
            return true;
        }

        return (int) ($row->user_id ?? 0) === $staffId;
    }

    /**
     * True when any client_matters row for this CRM client/lead (admins.id) lists the staff
     * as migration agent, person responsible, or person assisting.
     */
    private static function clientHasAllocatingMatter(int $clientAdminId, int $staffId): bool
    {
        if ($clientAdminId <= 0 || $staffId <= 0) {
            return false;
        }

        return DB::table('client_matters')
            ->where('client_id', $clientAdminId)
            ->where(function ($q) use ($staffId) {
                $q->where('sel_migration_agent', $staffId)
                    ->orWhere('sel_person_responsible', $staffId)
                    ->orWhere('sel_person_assisting', $staffId);
            })
            ->exists();
    }

    /**
     * For whereExists subqueries after ->from('client_matters') (table name must be `client_matters`, not aliased).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $sub
     */
    private static function whereClientMatterRowAssignedToStaff($sub, int $staffId): void
    {
        $sub->where(function ($w) use ($staffId) {
            $w->where('client_matters.sel_migration_agent', $staffId)
                ->orWhere('client_matters.sel_person_responsible', $staffId)
                ->orWhere('client_matters.sel_person_assisting', $staffId);
        });
    }
}
