<?php

namespace App\Services;

use App\Events\NotificationCountUpdated;
use App\Helpers\IconHelper;
use App\Models\ActivitiesLog;
use App\Models\CheckinLog;
use App\Models\ClientMatter;
use App\Models\ClientVisaCountry;
use App\Models\EmailLog;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DashboardService
{
    /**
     * Data for the client matters table partial only (AJAX pagination).
     *
     * @return array{data: LengthAwarePaginator, workflowStages: mixed, visibleColumns: array, filters: array<string, string>}
     */
    public function getClientMattersTablePayload(Request $request, Staff $user): array
    {
        return [
            'data' => $this->getClientMatters($request, $user),
            'workflowStages' => $this->getWorkflowStages(),
            'visibleColumns' => $this->getVisibleColumns(),
            'filters' => [
                'client_name' => $request->input('client_name') ?? '',
                'client_stage' => $request->input('client_stage') ?? '',
            ],
        ];
    }

    /**
     * Get all dashboard data.
     *
     * When $deferHeavyWidgets is true (default), cases list and client matters table
     * are omitted from the initial payload and loaded via AJAX fragments.
     */
    public function getDashboardData(Request $request, bool $deferHeavyWidgets = true): array
    {
        /** @var Staff $user */
        $user = Auth::user();

        $payload = [
            'notesData' => $this->getNotesData($user),
            'count_note_deadline' => $this->getNoteDeadlineCount($user),
            'count_cases_requiring_attention_data' => $this->getCasesRequiringAttentionCount($user),
            'filters' => [
                'client_name' => $request->client_name ?? '',
                'client_stage' => $request->client_stage ?? '',
            ],
            'visibleColumns' => $this->getVisibleColumns(),
            'workflowStages' => $this->getWorkflowStages(),
            'assignee' => $this->getAssignees(),
            'defer_heavy_widgets' => $deferHeavyWidgets,
        ];

        if ($deferHeavyWidgets) {
            $payload['data'] = null;
            $payload['cases_requiring_attention_data'] = [];
        } else {
            $payload['data'] = $this->getClientMatters($request, $user);
            $payload['cases_requiring_attention_data'] = $this->getCasesRequiringAttention($user);
        }

        return $payload;
    }

    /**
     * HTML fragment payload for the cases requiring attention widget.
     *
     * @return array{cases_requiring_attention_data: Collection, count: int}
     */
    public function getCasesRequiringAttentionPayload(Staff $user): array
    {
        return [
            'cases_requiring_attention_data' => $this->getCasesRequiringAttention($user),
            'count' => $this->getCasesRequiringAttentionCount($user),
        ];
    }

    /**
     * Get client matters with proper relationships
     */
    private function getClientMatters(Request $request, Staff $user): LengthAwarePaginator
    {
        // Load all relationships without column restrictions
        // Column restrictions can prevent relationships from loading if data doesn't match exactly
        $query = ClientMatter::with([
            'client:id,first_name,last_name,client_id,dob',
            'migrationAgent:id,first_name,last_name',
            'personResponsible:id,first_name,last_name',
            'personAssisting:id,first_name,last_name',
            'workflowStage:id,name',
            'matter:id,title',
        ]);

        // Apply role-based filtering
        $this->applyRoleBasedFiltering($query, $user);

        // Exclude discontinued matters (matter_status = 0)
        $query->where('matter_status', '=', 1, 'and');

        // Apply client name filter
        if ($request->has('client_name') && ! empty($request->client_name)) {
            $clientName = trim($request->client_name);
            $clientNameLower = strtolower($clientName);
            $query->whereHas('client', function ($q) use ($clientNameLower) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.$clientNameLower.'%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$clientNameLower.'%'])
                    ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$clientNameLower.'%'])
                    ->orWhereRaw('LOWER(client_id) LIKE ?', ['%'.$clientNameLower.'%']);
            });
        }

        // Apply stage filter (match by stage name so one dropdown entry covers all workflows)
        if ($request->has('client_stage') && ! empty($request->client_stage)) {
            $stageName = WorkflowStage::query()
                ->whereKey($request->client_stage)
                ->value('name');

            if ($stageName !== null) {
                $query->whereHas('workflowStage', function ($q) use ($stageName) {
                    $q->where('name', '=', $stageName, 'and');
                });
            }
        } else {
            $query->where('workflow_stage_id', '!=', 14, 'and');
        }

        $query->orderBy('updated_at', 'DESC');

        $perPage = 10;
        $ttl = config('cache.dashboard_client_matters_count_ttl', 45);
        $countCacheKey = $this->dashboardClientMattersCountCacheKey($user, $request);

        $total = Cache::remember($countCacheKey, max(1, $ttl), function () use ($query) {
            return $query->toBase()->getCountForPagination();
        });

        $paginator = $query->paginate($perPage, ['*'], 'page', null, $total);

        try {
            $this->hydrateDashboardUnreadMailCounts($paginator->getCollection());
        } catch (\Exception $e) {
            Log::debug('Dashboard unread mail batch count failed: '.$e->getMessage());
            foreach ($paginator->getCollection() as $matter) {
                $matter->setAttribute('dashboard_unread_mail_count', 0);
            }
        }

        return $paginator;
    }

    /**
     * One query for unread email badges on the dashboard table (matches per-row mailReports() count in Blade).
     *
     * @param  Collection<int, ClientMatter>  $matters
     */
    private function hydrateDashboardUnreadMailCounts(Collection $matters): void
    {
        foreach ($matters as $matter) {
            $matter->setAttribute('dashboard_unread_mail_count', 0);
        }

        $valid = $matters->filter(static function ($m) {
            return $m && $m->id && $m->client_id;
        });

        if ($valid->isEmpty()) {
            return;
        }

        $query = EmailLog::query()
            ->selectRaw('client_matter_id, COUNT(*) as dashboard_unread_cnt', [])
            ->where('conversion_type', '=', 'conversion_email_fetch', 'and')
            ->whereNull('mail_is_read')
            ->where(static function ($q): void {
                $q->where('mail_body_type', '=', 'inbox', 'and')
                    ->orWhere('mail_body_type', '=', 'sent', 'and');
            })
            ->where(static function ($q) use ($valid): void {
                foreach ($valid as $m) {
                    $q->orWhere(static function ($q2) use ($m): void {
                        $q2->where('client_matter_id', '=', $m->id, 'and')
                            ->where('client_id', '=', $m->client_id, 'and');
                    });
                }
            })
            ->groupBy('client_matter_id');

        foreach ($query->get() as $row) {
            $matter = $matters->firstWhere('id', (int) $row->client_matter_id);
            if ($matter) {
                $matter->setAttribute('dashboard_unread_mail_count', (int) $row->dashboard_unread_cnt);
            }
        }
    }

    /**
     * Get all actions (notes with is_action = 1) for the user
     * Shows actions with deadlines first (ordered by urgency), then actions without deadlines
     * Matches Action page: includes Personal Actions (null client_id) and all task groups
     */
    private function getNotesData(Staff $user): Collection
    {
        $query = Note::with([
            'client:id,first_name,last_name,client_id,is_company',
            'client.company:id,admin_id,company_name',
            'assignedUser:id,first_name,last_name',
        ])
            ->where('type', '=', 'client', 'and')
            ->where('is_action', '=', 1, 'and')
            ->where('status', '!=', 1, 'and');

        // Admin sees ALL actions (no assigned_to filter) - matching action page behavior
        // Other roles only see notes assigned to them
        if ($user->role != 1) {
            $query->where('assigned_to', '=', $user->id, 'and');
        }

        // Order: Actions with deadlines first (by deadline ASC), then actions without deadlines (by created_at DESC)
        return $query->orderByRaw('CASE WHEN note_deadline IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('note_deadline', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->limit(6) // Show only 6 most recent/urgent actions
            ->get();
    }

    /**
     * Get cases requiring attention
     */
    private function getCasesRequiringAttention(Staff $user): Collection
    {
        $query = ClientMatter::with([
            'client:id,first_name,last_name,client_id',
            'matter:id,title',
            'personResponsible:id,first_name,last_name',
        ])
            ->where('matter_status', '=', 1, 'and')
            ->where('updated_at', '>=', Carbon::now()->subDays(100), 'and');

        if ((int) $user->role !== 1) {
            $query->whereHas('client', function ($q) use ($user) {
                StaffClientVisibility::excludeSuperAdminOnlyLockedClientsFromAdminQuery($q, $user);
            });
        }

        // Apply role-based filtering
        $this->applyRoleBasedFiltering($query, $user);

        $cases = $query->orderByDesc('updated_at')
            ->limit(50) // Limit to 50 most recent cases to avoid timeout
            ->get();

        $head = $cases->take(20);
        $clientIds = $head->pluck('client_id')->unique()->filter(static function ($id) {
            return $id !== null && $id !== '';
        })->values()->all();

        try {
            $activityByClientId = $this->getLatestActivityMapForClientIds($clientIds);
        } catch (\Exception $e) {
            Log::debug('Error batch-fetching activities_log: '.$e->getMessage());
            $activityByClientId = [];
        }

        foreach ($head as $case) {
            $cid = $case->client_id;
            if ($cid !== null && $cid !== '' && isset($activityByClientId[(int) $cid])) {
                $case->latest_activity = $activityByClientId[(int) $cid];
            } else {
                $case->latest_activity = [
                    'type' => 'default',
                    'date' => $case->updated_at,
                ];
            }
        }

        foreach ($cases->slice(20) as $case) {
            $case->latest_activity = [
                'type' => 'default',
                'date' => $case->updated_at,
            ];
        }

        return $cases;
    }

    /**
     * Latest activities_log row per client_id (one query), same ordering as latest('created_at')->first()
     * with id DESC tie-break for stable results.
     *
     * @param  array<int, int|string>  $clientIds
     * @return array<int, array{type: string, date: Carbon|\Illuminate\Support\Carbon}>
     */
    private function getLatestActivityMapForClientIds(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $connection = DB::connection();
        $table = $connection->getTablePrefix().'activities_logs';
        $driver = $connection->getDriverName();
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $bindings = array_values($clientIds);

        if ($driver === 'pgsql') {
            $sql = "SELECT DISTINCT ON (client_id) client_id, subject, created_at
                FROM {$table}
                WHERE client_id IN ({$placeholders})
                ORDER BY client_id, created_at DESC NULLS LAST, id DESC";
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $sql = "SELECT client_id, subject, created_at FROM (
                    SELECT client_id, subject, created_at,
                        ROW_NUMBER() OVER (PARTITION BY client_id ORDER BY created_at DESC, id DESC) AS rn
                    FROM {$table}
                    WHERE client_id IN ({$placeholders})
                ) AS ranked
                WHERE rn = 1";
        } else {
            $sql = "SELECT client_id, subject, created_at FROM (
                    SELECT client_id, subject, created_at,
                        ROW_NUMBER() OVER (PARTITION BY client_id ORDER BY created_at DESC, id DESC) AS rn
                    FROM {$table}
                    WHERE client_id IN ({$placeholders})
                ) AS ranked
                WHERE rn = 1";
        }

        $rows = DB::select($sql, $bindings);
        $out = [];
        foreach ($rows as $row) {
            $payload = $this->latestActivityFromActivitiesLogRow($row);
            if ($payload !== null) {
                $out[(int) $row->client_id] = $payload;
            }
        }

        return $out;
    }

    /**
     * @param  object{client_id: mixed, subject: ?string, created_at: mixed}  $row
     * @return array{type: string, date: Carbon}|null
     */
    private function latestActivityFromActivitiesLogRow(object $row): ?array
    {
        if (empty($row->created_at)) {
            return null;
        }

        return [
            'type' => $this->mapActivitiesLogSubjectToType($row->subject ?? ''),
            'date' => Carbon::parse($row->created_at),
        ];
    }

    private function mapActivitiesLogSubjectToType(string $subject): string
    {
        $subject = strtolower($subject);
        if (Str::contains($subject, 'stage') || Str::contains($subject, 'workflow')) {
            return 'stage_updated';
        }
        if (Str::contains($subject, 'status')) {
            return 'status_changed';
        }
        if (Str::contains($subject, 'appointment') || Str::contains($subject, 'meeting')) {
            return 'appointment_scheduled';
        }
        if (Str::contains($subject, 'payment') || Str::contains($subject, 'invoice')) {
            return 'payment_received';
        }
        if (Str::contains($subject, 'note')) {
            return 'note_added';
        }
        if (Str::contains($subject, 'email')) {
            return 'email_sent';
        }
        if (Str::contains($subject, 'document') || Str::contains($subject, 'upload')) {
            return 'document_uploaded';
        }
        if (Str::contains($subject, 'sign')) {
            return 'signed';
        }

        return 'default';
    }

    /**
     * Cache key for dashboard client matters paginator total (same filters as getClientMatters).
     */
    private function dashboardClientMattersCountCacheKey(Staff $user, Request $request): string
    {
        $clientName = trim((string) ($request->input('client_name') ?? ''));
        $stage = trim((string) ($request->input('client_stage') ?? ''));
        $role = (int) ($user->role ?? 0);

        return 'dashboard:client_matters:count:v1:'
            .(int) $user->id
            .':'.$role
            .':'.md5($clientName)
            .':'.$stage;
    }

    /**
     * Apply role-based filtering to queries
     */
    private function applyRoleBasedFiltering(Builder $query, Staff $user): void
    {
        $role = (int) $user->role;
        if ($role === 1) {
            return;
        }
        // MA / PR / PA roles: any matter where they are assigned in any of the three roles
        if (in_array($role, [12, 13, 16], true)) {
            $uid = (int) $user->id;
            $query->where(function ($q) use ($uid) {
                $q->where('client_matters.sel_migration_agent', '=', $uid, 'and')
                    ->orWhere('client_matters.sel_person_responsible', '=', $uid)
                    ->orWhere('client_matters.sel_person_assisting', '=', $uid);
            });
        }
    }

    /**
     * Get active matter count with caching
     */
    private function getActiveMatterCount(): int
    {
        return Cache::remember('active_matter_count', 300, function () {
            return ClientMatter::query()->where('matter_status', '=', 1, 'and')->count();
        });
    }

    /**
     * Get note deadline count (all actions count)
     * Matches Action page getActionCounts: includes Personal Actions
     */
    private function getNoteDeadlineCount(Staff $user): int
    {
        $role = (int) ($user->role ?? 0);
        $ttl = max(1, (int) config('cache.dashboard_kpi_counts_ttl', 60));
        $cacheKey = 'dashboard:note_deadline_count:v1:'.(int) $user->id.':'.$role;

        return (int) Cache::remember($cacheKey, $ttl, function () use ($user) {
            $query = Note::query()
                ->where('type', '=', 'client', 'and')
                ->where('is_action', '=', 1, 'and')
                ->where('status', '!=', 1, 'and');

            if ($user->role != 1) {
                $query->where('assigned_to', '=', $user->id, 'and');
            }

            return $query->count();
        });
    }

    /**
     * Get cases requiring attention count
     */
    private function getCasesRequiringAttentionCount(Staff $user): int
    {
        $role = (int) ($user->role ?? 0);
        $ttl = max(1, (int) config('cache.dashboard_kpi_counts_ttl', 60));
        $cacheKey = 'dashboard:cases_attention_count:v1:'.(int) $user->id.':'.$role;

        return (int) Cache::remember($cacheKey, $ttl, function () use ($user) {
            $query = ClientMatter::query()
                ->join('admins as clients', 'client_matters.client_id', '=', 'clients.id', 'inner', false)
                ->where('client_matters.matter_status', '=', 1, 'and')
                ->where('client_matters.updated_at', '>=', Carbon::now()->subDays(100), 'and');

            if ((int) $user->role !== 1) {
                StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'clients', $user);
            }

            $this->applyRoleBasedFiltering($query, $user);

            return $query->count();
        });
    }

    /**
     * @param  array<int>  $additionalStaffIds
     */
    private function forgetDashboardKpiCountCaches(?int $userId = null, ?int $role = null, array $additionalStaffIds = []): void
    {
        $user = Auth::user();
        $userId = $userId ?? (int) ($user->id ?? 0);
        $role = $role ?? (int) ($user->role ?? 0);

        if ($userId <= 0) {
            return;
        }

        Cache::forget('dashboard:note_deadline_count:v1:'.$userId.':'.$role);
        Cache::forget('dashboard:cases_attention_count:v1:'.$userId.':'.$role);
        StaffWorkloadService::forgetForStaffIds($userId, ...$additionalStaffIds);
    }

    /**
     * Get visible columns from session
     */
    private function getVisibleColumns(): array
    {
        $defaultColumns = [
            'matter', 'client_id', 'client_name', 'dob',
            'migration_agent', 'person_responsible',
            'person_assisting', 'stage',
        ];

        return session('dashboard_column_preferences', $defaultColumns);
    }

    /**
     * Get workflow stages for the dashboard filter dropdown.
     *
     * Uses the General workflow template only so duplicate stage names from
     * per-matter workflows do not appear multiple times. Filtering matches by
     * stage name (see getClientMatters) so matters on other workflows still match.
     *
     * Cache plain rows only — caching Eloquent collections can deserialize as
     * __PHP_Incomplete_Class and break Blade (sanitizeComponentAttribute / method_exists).
     */
    private function getWorkflowStages(): Collection
    {
        $rows = Cache::remember('workflow_stages_v3_general', 3600, function () {
            $query = WorkflowStage::query()->orderByRaw('COALESCE(sort_order, id) ASC', []);

            $generalWorkflowId = Workflow::query()
                ->where(DB::raw('LOWER(name)'), '=', 'general', 'and')
                ->pluck('id')
                ->first();

            if ($generalWorkflowId) {
                $query->where('workflow_id', '=', $generalWorkflowId, 'and');
            }

            return $query
                ->get(['id', 'name'])
                ->map(fn (WorkflowStage $stage) => $stage->only(['id', 'name']))
                ->values()
                ->all();
        });

        return collect($rows)->map(fn (array $attrs) => (object) $attrs);
    }

    /**
     * Get assignees for action creation.
     *
     * Cache plain rows only — caching Eloquent collections can deserialize as
     * __PHP_Incomplete_Class and break the dashboard (see getWorkflowStages).
     */
    private function getAssignees(): Collection
    {
        $rows = Cache::remember('dashboard:assignees:v2', 3600, static function () {
            return Staff::query()
                ->select(['id', 'first_name', 'email'])
                ->where('role', '!=', 1, 'and')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Staff $staff) => $staff->only(['id', 'first_name', 'email']))
                ->values()
                ->all();
        });

        return collect($rows)->map(fn (array $attrs) => (object) $attrs);
    }

    /**
     * Save column preferences
     */
    public function saveColumnPreferences(Request $request): void
    {
        $visibleColumns = $request->input('visible_columns', []);

        $validColumns = [
            'matter', 'client_id', 'client_name', 'dob',
            'migration_agent', 'person_responsible',
            'person_assisting', 'stage',
        ];

        $filteredColumns = array_intersect($visibleColumns, $validColumns);

        session(['dashboard_column_preferences' => $filteredColumns]);
    }

    /**
     * Get notifications
     */
    public function getNotifications(): array
    {
        $count = Notification::query()
            ->where('receiver_id', '=', (int) Auth::id(), 'and')
            ->where('receiver_status', '=', 0, 'and')
            ->count();

        return ['count' => $count];
    }

    /**
     * Get office visit notifications
     */
    public function getOfficeVisitNotifications(): array
    {
        $notifications = Notification::with(['sender:id,first_name,last_name'])
            ->where('receiver_id', '=', (int) Auth::id(), 'and')
            ->where('notification_type', '=', 'officevisit', 'and')
            ->where('receiver_status', '=', 0, 'and')
            ->orderBy('created_at', 'DESC')
            ->get();

        $receptionUserId = (int) config('constants.reception_user_id', 36730);
        $viewerIsReception = (int) Auth::id() === $receptionUserId;

        $data = [];
        foreach ($notifications as $notification) {
            $checkinLog = CheckinLog::query()->whereKey((int) $notification->module_id)->first();

            if (! $checkinLog) {
                continue;
            }
            if (! $viewerIsReception && (int) $checkinLog->status !== 0) {
                continue;
            }
            if ($viewerIsReception && ! in_array((int) $checkinLog->status, [0, 2], true)) {
                continue;
            }

            $isReceptionAlert = $viewerIsReception
                && ((int) $checkinLog->wait_type === 1 || (int) $checkinLog->status === 2);

            $data[] = [
                'id' => $notification->id,
                'checkin_id' => $checkinLog->id,
                'is_reception_alert' => $isReceptionAlert,
                'message' => $notification->message,
                'sender_name' => $notification->sender
                    ? $notification->sender->first_name.' '.$notification->sender->last_name
                    : 'System',
                'client_name' => $checkinLog->contactDisplayLabel(),
                'visit_purpose' => $checkinLog->visit_purpose,
                'created_at' => $notification->created_at->format('d/m/Y h:i A'),
                'url' => $notification->url,
            ];
        }

        return $data;
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationAsSeen(int|string $notificationId): array
    {
        $notification = Notification::query()->whereKey((int) $notificationId)->first();

        if (! $notification || $notification->receiver_id != Auth::id()) {
            return ['status' => 'error'];
        }

        $notification->receiver_status = 1;
        $notification->save();

        return ['status' => 'success'];
    }

    /**
     * Extend note deadline
     */
    public function extendNoteDeadline(array $data): array
    {
        try {
            $notes = Note::query()
                ->where('unique_group_id', '=', $data['unique_group_id'], 'and')
                ->whereNotNull('unique_group_id')
                ->get();

            if ($notes->isEmpty()) {
                return ['success' => false, 'message' => 'No notes found with the provided unique group ID'];
            }

            $updated = Note::query()
                ->where('unique_group_id', '=', $data['unique_group_id'], 'and')
                ->whereNotNull('unique_group_id')
                ->update([
                    'description' => $data['description'],
                    'note_deadline' => $data['note_deadline'],
                    'user_id' => Auth::id(),
                ]);

            if ($updated > 0) {
                $this->forgetDashboardKpiCountCaches();

                // Create notification and activity log for the first note
                $firstNote = $notes->first();
                $this->createNotificationAndActivityLog($firstNote);

                return [
                    'success' => true,
                    'message' => 'Successfully updated',
                    'clientID' => $firstNote->client_id,
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to update notes'];
            }
        } catch (\Exception $e) {
            Log::error('Error extending note deadline: '.$e->getMessage());

            return ['success' => false, 'message' => 'An error occurred while extending the deadline'];
        }
    }

    /**
     * Update action completion status and create completed action activity
     * Matches Action tab behavior: updates note(s), creates ActivitiesLog with optional completion notes
     */
    public function updateActionCompleted(int $noteId, ?string $uniqueGroupId, ?string $completionNotes = null): array
    {
        $noteData = Note::query()
            ->where('id', '=', $noteId, 'and')
            ->where('unique_group_id', '=', $uniqueGroupId, 'and')
            ->first();

        if (! $noteData) {
            return ['success' => false, 'message' => 'Action not found'];
        }

        // Update all notes in the group (matches Action tab behavior), or single note if no group
        $updated = 0;
        if (! empty(trim($uniqueGroupId ?? ''))) {
            $updated = Note::query()
                ->where('unique_group_id', '=', $uniqueGroupId, 'and')
                ->whereNotNull('unique_group_id')
                ->update(['status' => 1]);
        }
        if (! $updated) {
            $updated = Note::query()->where('id', '=', $noteId, 'and')->update(['status' => 1]);
        }
        if (! $updated) {
            return ['success' => false, 'message' => 'Failed to complete action'];
        }

        $this->forgetDashboardKpiCountCaches(null, null, [(int) ($noteData->assigned_to ?? 0)]);

        // Activity Feed: log completion for client-linked actions, except Client Portal category (matches AssigneeController).
        if ($noteData->client_id) {
            $taskGroup = $noteData->task_group ?? '';

            if ((string) $taskGroup !== 'Client Portal') {
                $assigneeName = 'N/A';
                if ($noteData->assigned_to) {
                    $assignee = Staff::query()->whereKey((int) $noteData->assigned_to)->first();
                    $assigneeName = $assignee ? $assignee->first_name.' '.$assignee->last_name : 'N/A';
                }

                $description = '';
                if (! empty($completionNotes)) {
                    $description .= '<p>';
                    $description .= IconHelper::fromLegacy('fas fa-ellipsis-v', [
                        'class' => 'convert-activity-to-note',
                        'style' => 'cursor: pointer; color: #6c757d;',
                        'title' => 'Convert to Note',
                        'data-activity-id' => '',
                        'data-activity-subject' => 'Completion Notes',
                        'data-activity-description' => $completionNotes,
                        'data-activity-created-by' => Auth::id(),
                        'data-activity-created-at' => now()->toIso8601String(),
                        'data-client-id' => $noteData->client_id,
                    ]);
                    $description .= '</p>';
                    $description .= '<p>'.nl2br(htmlspecialchars($completionNotes)).'</p>';
                    $description .= '<hr>';
                }
                $description .= '<p>'.($noteData->description ?? '').'</p>';

                ActivitiesLog::create([
                    'client_id' => $noteData->client_id,
                    'created_by' => Auth::id(),
                    'subject' => 'completed action for '.$assigneeName,
                    'description' => $description,
                    'use_for' => (Auth::id() != $noteData->assigned_to) ? $noteData->assigned_to : null,
                    'followup_date' => $noteData->action_date ?? null,
                    'task_group' => $noteData->task_group ?? null,
                    'task_status' => 1,
                    'pin' => 0,
                ]);
            }

            // Client Portal category only: notify client (notification list API + push + real-time)
            if ((string) $taskGroup === 'Client Portal') {
                $messageText = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", (string) ($noteData->description ?? ''))));
                if (mb_strlen($messageText) > 200) {
                    $messageText = mb_substr($messageText, 0, 197).'...';
                }
                $notificationMessage = 'This action is completed. '.($messageText ?: 'An action has been completed for your matter.');
                // module_id = client matter id so notification appears in List API when client filters by client_matter_id
                $moduleId = ! empty($noteData->matter_id) ? (int) $noteData->matter_id : null;
                if ($moduleId === null) {
                    $moduleId = ClientMatter::query()
                        ->where('client_id', '=', $noteData->client_id, 'and')
                        ->orderByDesc('id')
                        ->value('id') ?? $noteData->client_id;
                }
                DB::table('notifications')->insert([
                    'sender_id' => Auth::id(),
                    'receiver_id' => $noteData->client_id,
                    'module_id' => $moduleId,
                    'url' => '/activities',
                    'notification_type' => 'action_completed',
                    'message' => $notificationMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $fcm = new FCMService;
                    $fcm->sendToUser($noteData->client_id, 'Action completed', $notificationMessage, [
                        'type' => 'action_completed',
                        'client_matter_id' => (string) $moduleId,
                        'url' => '/activities',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('FCM send failed on action complete (Client Portal)', ['client_id' => $noteData->client_id, 'error' => $e->getMessage()]);
                }
                try {
                    $clientCount = (int) DB::table('notifications')
                        ->where('receiver_id', '=', $noteData->client_id, 'and')
                        ->where('receiver_status', '=', 0, 'and')
                        ->count();
                    broadcast(new NotificationCountUpdated($noteData->client_id, $clientCount, $notificationMessage, '/activities'));
                } catch (\Exception $e) {
                    Log::warning('Broadcast failed on action complete (Client Portal)', ['client_id' => $noteData->client_id, 'error' => $e->getMessage()]);
                }
            }
        }

        return ['success' => true, 'message' => 'Action completed successfully'];
    }

    /**
     * Get visa expiry message
     */
    public function getVisaExpiryMessage(int $clientId): string
    {
        $visaInfo = ClientVisaCountry::query()
            ->where('client_id', '=', (int) $clientId, 'and')
            ->latest('id')
            ->first();

        if (! $visaInfo || ! $visaInfo->visa_expiry_date) {
            return '';
        }

        $visaExpiredAt = Carbon::parse($visaInfo->visa_expiry_date);
        $today = Carbon::now();
        $sevenDaysFromNow = Carbon::now()->addDays(7);

        if ($visaExpiredAt->lt($today)) {
            return 'Your visa is expired';
        } elseif ($visaExpiredAt->gte($today) && $visaExpiredAt->lte($sevenDaysFromNow)) {
            $daysRemaining = $visaExpiredAt->diffInDays($today);

            return "Your visa is expiring in next $daysRemaining day".($daysRemaining == 1 ? '' : 's');
        }

        return '';
    }

    /**
     * Create notification and activity log
     */
    private function createNotificationAndActivityLog(Note $note): void
    {
        try {
            // Create notification only if assigned_to exists
            if ($note->assigned_to) {
                $notificationUrl = $note->client_id
                    ? url('/clients/detail/'.base64_encode(convert_uuencode($note->client_id)))
                    : url('/action');
                Notification::create([
                    'sender_id' => Auth::id(),
                    'receiver_id' => $note->assigned_to,
                    'module_id' => $note->client_id ?? 0,
                    'url' => $notificationUrl,
                    'notification_type' => 'client',
                    'message' => 'Action Extended by '.Auth::user()->first_name.' '.Auth::user()->last_name.' on '.date('d/M/Y h:i A'),
                ]);
            }

            // Create activity log (client_id may be null for Personal Actions)
            ActivitiesLog::create([
                'client_id' => $note->client_id,
                'created_by' => Auth::id(),
                'subject' => 'Extended Note Deadline',
                'description' => '<span class="text-semi-bold">'.($note->title ?? 'Note').'</span><p>'.($note->description ?? '').'</p>',
                'use_for' => Auth::id() != $note->user_id ? $note->user_id : '',
                'followup_date' => $note->action_date ?? null,
                'task_group' => $note->task_group ?? null,
                'task_status' => 0,
                'pin' => 0,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't break the main functionality
            Log::error('Error creating notification/activity log: '.$e->getMessage());
        }
    }
}
