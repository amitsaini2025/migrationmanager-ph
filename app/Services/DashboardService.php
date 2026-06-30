<?php
namespace App\Services;

use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Notification;
use App\Models\CheckinLog;
use App\Models\ClientVisaCountry;
use App\Models\ActivitiesLog;
use App\Models\EmailLog;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Helpers\IconHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\FCMService;
use App\Events\NotificationCountUpdated;
use App\Support\StaffClientVisibility;

class DashboardService
{
    /**
     * Data for the client matters table partial only (AJAX pagination).
     *
     * @return array{data: \Illuminate\Contracts\Pagination\LengthAwarePaginator, workflowStages: mixed, visibleColumns: array, filters: array<string, string>}
     */
    public function getClientMattersTablePayload($request, $user): array
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
     * Get all dashboard data
     */
    public function getDashboardData($request): array
    {
        $user = Auth::user();
        
        return [
            'data' => $this->getClientMatters($request, $user),
            'notesData' => $this->getNotesData($user),
            'cases_requiring_attention_data' => $this->getCasesRequiringAttention($user),
            'count_active_matter' => $this->getActiveMatterCount(),
            'count_note_deadline' => $this->getNoteDeadlineCount($user),
            'count_cases_requiring_attention_data' => $this->getCasesRequiringAttentionCount($user),
            'filters' => [
                'client_name' => $request->client_name ?? '',
                'client_stage' => $request->client_stage ?? ''
            ],
            'visibleColumns' => $this->getVisibleColumns(),
            'workflowStages' => $this->getWorkflowStages(),
            'assignee' => $this->getAssignees()
        ];
    }

    /**
     * Get client matters with proper relationships
     */
    private function getClientMatters($request, $user)
    {
        // Load all relationships without column restrictions
        // Column restrictions can prevent relationships from loading if data doesn't match exactly
        $query = ClientMatter::with([
            'client',           // Load full client record
            'migrationAgent',  // Load full migration agent record
            'personResponsible', // Load full person responsible record
            'personAssisting',  // Load full person assisting record
            'workflowStage',    // Load workflow stage name (dashboard Stage column)
            'matter',            // Load matter type
        ]);

        // Apply role-based filtering
        $this->applyRoleBasedFiltering($query, $user);

        // Exclude discontinued matters (matter_status = 0)
        $query->where('matter_status', 1);

        // Apply client name filter
        if ($request->has('client_name') && !empty($request->client_name)) {
            $clientName = trim($request->client_name);
            $clientNameLower = strtolower($clientName);
            $query->whereHas('client', function ($q) use ($clientName, $clientNameLower) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $clientNameLower . '%'])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $clientNameLower . '%'])
                  ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%' . $clientNameLower . '%'])
                  ->orWhereRaw('LOWER(client_id) LIKE ?', ['%' . $clientNameLower . '%']);
            });
        }

        // Apply stage filter (match by stage name so one dropdown entry covers all workflows)
        if ($request->has('client_stage') && !empty($request->client_stage)) {
            $stageName = WorkflowStage::query()
                ->whereKey($request->client_stage)
                ->value('name');

            if ($stageName !== null) {
                $query->whereHas('workflowStage', function ($q) use ($stageName) {
                    $q->where('name', $stageName);
                });
            }
        } else {
            $query->where('workflow_stage_id', '!=', 14);
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
            Log::debug('Dashboard unread mail batch count failed: ' . $e->getMessage());
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
            ->selectRaw('client_matter_id, COUNT(*) as dashboard_unread_cnt')
            ->where('conversion_type', 'conversion_email_fetch')
            ->whereNull('mail_is_read')
            ->where(static function ($q): void {
                $q->where('mail_body_type', 'inbox')
                    ->orWhere('mail_body_type', 'sent');
            })
            ->where(static function ($q) use ($valid): void {
                foreach ($valid as $m) {
                    $q->orWhere(static function ($q2) use ($m): void {
                        $q2->where('client_matter_id', $m->id)
                            ->where('client_id', $m->client_id);
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
    private function getNotesData($user)
    {
        $query = Note::with([
            'client:id,first_name,last_name,client_id,is_company',
            'client.company:id,admin_id,company_name',
            'assignedUser:id,first_name,last_name',
        ])
            ->where('type', 'client')
            ->where('is_action', 1)
            ->where('status', '!=', 1);

        // Admin sees ALL actions (no assigned_to filter) - matching action page behavior
        // Other roles only see notes assigned to them
        if ($user->role != 1) {
            $query->where('assigned_to', $user->id);
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
    private function getCasesRequiringAttention($user)
    {
        $query = ClientMatter::with([
                'client:id,first_name,last_name,client_id',
                'matter:id,title',
                'personResponsible:id,first_name,last_name'
            ])
            ->where('matter_status', 1)
            ->where('updated_at', '>=', Carbon::now()->subDays(100));

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
            Log::debug('Error batch-fetching activities_log: ' . $e->getMessage());
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
     * @return array<int, array{type: string, date: \Carbon\Carbon|\Illuminate\Support\Carbon}>
     */
    private function getLatestActivityMapForClientIds(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $connection = DB::connection();
        $table = $connection->getTablePrefix() . 'activities_logs';
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
    private function dashboardClientMattersCountCacheKey($user, $request): string
    {
        $clientName = trim((string) ($request->input('client_name') ?? ''));
        $stage = trim((string) ($request->input('client_stage') ?? ''));
        $role = (int) ($user->role ?? 0);

        return 'dashboard:client_matters:count:v1:'
            . (int) $user->id
            . ':' . $role
            . ':' . md5($clientName)
            . ':' . $stage;
    }

    /**
     * Apply role-based filtering to queries
     */
    private function applyRoleBasedFiltering($query, $user)
    {
        $role = (int) $user->role;
        if ($role === 1) {
            return;
        }
        // MA / PR / PA roles: any matter where they are assigned in any of the three roles
        if (in_array($role, [12, 13, 16], true)) {
            $uid = (int) $user->id;
            $query->where(function ($q) use ($uid) {
                $q->where('client_matters.sel_migration_agent', $uid)
                    ->orWhere('client_matters.sel_person_responsible', $uid)
                    ->orWhere('client_matters.sel_person_assisting', $uid);
            });
        }
    }

    /**
     * Get active matter count with caching
     */
    private function getActiveMatterCount(): int
    {
        return Cache::remember('active_matter_count', 300, function () {
            return ClientMatter::where('matter_status', 1)->count();
        });
    }

    /**
     * Get note deadline count (all actions count)
     * Matches Action page getActionCounts: includes Personal Actions
     */
    private function getNoteDeadlineCount($user): int
    {
        $query = Note::where('type', 'client')
            ->where('is_action', 1)
            ->where('status', '!=', 1);

        // Admin sees ALL actions (no assigned_to filter) - matching action page behavior
        if ($user->role != 1) {
            $query->where('assigned_to', $user->id);
        }

        return $query->count();
    }

    /**
     * Get cases requiring attention count
     */
    private function getCasesRequiringAttentionCount($user): int
    {
        $query = ClientMatter::join('admins as clients', 'client_matters.client_id', '=', 'clients.id')
            ->where('client_matters.matter_status', 1)
            ->where('client_matters.updated_at', '>=', Carbon::now()->subDays(100));

        if ((int) $user->role !== 1) {
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'clients', $user);
        }

        $this->applyRoleBasedFiltering($query, $user);

        return $query->count();
    }

    /**
     * Get visible columns from session
     */
    private function getVisibleColumns(): array
    {
        $defaultColumns = [
            'matter', 'client_id', 'client_name', 'dob', 
            'migration_agent', 'person_responsible', 
            'person_assisting', 'stage'
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
            $query = WorkflowStage::query()->orderByRaw('COALESCE(sort_order, id) ASC');

            $generalWorkflowId = Workflow::query()
                ->whereRaw('LOWER(name) = ?', ['general'])
                ->value('id');

            if ($generalWorkflowId) {
                $query->where('workflow_id', $generalWorkflowId);
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
     * Get assignees for action creation
     */
    private function getAssignees()
    {
        return \App\Models\Staff::select('id', 'first_name', 'email')
            ->where('role', '!=', 1)
            ->get();
    }

    /**
     * Save column preferences
     */
    public function saveColumnPreferences($request): void
    {
        $visibleColumns = $request->input('visible_columns', []);
        
        $validColumns = [
            'matter', 'client_id', 'client_name', 'dob', 
            'migration_agent', 'person_responsible', 
            'person_assisting', 'stage'
        ];
        
        $filteredColumns = array_intersect($visibleColumns, $validColumns);
        
        session(['dashboard_column_preferences' => $filteredColumns]);
    }

    /**
     * Get notifications
     */
    public function getNotifications(): array
    {
        $count = Notification::where('receiver_id', Auth::id())
            ->where('receiver_status', 0)
            ->count();

        return ['count' => $count];
    }

    /**
     * Get office visit notifications
     */
    public function getOfficeVisitNotifications(): array
    {
        $notifications = Notification::with(['sender:id,first_name,last_name'])
            ->where('receiver_id', Auth::id())
            ->where('notification_type', 'officevisit')
            ->where('receiver_status', 0)
            ->orderBy('created_at', 'DESC')
            ->get();

        $receptionUserId = (int) config('constants.reception_user_id', 36730);
        $viewerIsReception = (int) Auth::id() === $receptionUserId;

        $data = [];
        foreach ($notifications as $notification) {
            $checkinLog = CheckinLog::find($notification->module_id);
            
            if (!$checkinLog) {
                continue;
            }
            if (!$viewerIsReception && (int) $checkinLog->status !== 0) {
                continue;
            }
            if ($viewerIsReception && !in_array((int) $checkinLog->status, [0, 2], true)) {
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
                    ? $notification->sender->first_name . ' ' . $notification->sender->last_name 
                    : 'System',
                'client_name' => $checkinLog->contactDisplayLabel(),
                'visit_purpose' => $checkinLog->visit_purpose,
                'created_at' => $notification->created_at->format('d/m/Y h:i A'),
                'url' => $notification->url
            ];
        }

        return $data;
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationAsSeen($notificationId): array
    {
        $notification = Notification::find($notificationId);
        
        if (!$notification || $notification->receiver_id != Auth::id()) {
            return ['status' => 'error'];
        }

        $notification->receiver_status = 1;
        $notification->save();

        return ['status' => 'success'];
    }

    /**
     * Extend note deadline
     */
    public function extendNoteDeadline($data): array
    {
        try {
            $notes = Note::where('unique_group_id', $data['unique_group_id'])
                ->whereNotNull('unique_group_id')
                ->get();

            if ($notes->isEmpty()) {
                return ['success' => false, 'message' => 'No notes found with the provided unique group ID'];
            }

            $updated = Note::where('unique_group_id', $data['unique_group_id'])
                ->whereNotNull('unique_group_id')
                ->update([
                    'description' => $data['description'],
                    'note_deadline' => $data['note_deadline'],
                    'user_id' => Auth::id()
                ]);

            if ($updated > 0) {
                // Create notification and activity log for the first note
                $firstNote = $notes->first();
                $this->createNotificationAndActivityLog($firstNote);

                return [
                    'success' => true, 
                    'message' => 'Successfully updated', 
                    'clientID' => $firstNote->client_id
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to update notes'];
            }
        } catch (\Exception $e) {
            Log::error('Error extending note deadline: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while extending the deadline'];
        }
    }

    /**
     * Update action completion status and create completed action activity
     * Matches Action tab behavior: updates note(s), creates ActivitiesLog with optional completion notes
     */
    public function updateActionCompleted($noteId, $uniqueGroupId, ?string $completionNotes = null): array
    {
        $noteData = Note::where('id', $noteId)
            ->where('unique_group_id', $uniqueGroupId)
            ->first();

        if (!$noteData) {
            return ['success' => false, 'message' => 'Action not found'];
        }

        // Update all notes in the group (matches Action tab behavior), or single note if no group
        $updated = 0;
        if (!empty(trim($uniqueGroupId ?? ''))) {
            $updated = Note::where('unique_group_id', $uniqueGroupId)
                ->whereNotNull('unique_group_id')
                ->update(['status' => 1]);
        }
        if (!$updated) {
            $updated = Note::where('id', $noteId)->update(['status' => 1]);
        }
        if (!$updated) {
            return ['success' => false, 'message' => 'Failed to complete action'];
        }

        // Activity Feed: log completion for client-linked actions, except Client Portal category (matches AssigneeController).
        if ($noteData->client_id) {
            $taskGroup = $noteData->task_group ?? '';

            if ((string) $taskGroup !== 'Client Portal') {
                $assigneeName = 'N/A';
                if ($noteData->assigned_to) {
                    $assignee = \App\Models\Staff::find($noteData->assigned_to);
                    $assigneeName = $assignee ? $assignee->first_name . ' ' . $assignee->last_name : 'N/A';
                }

                $description = '';
                if (!empty($completionNotes)) {
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
                    $description .= '<p>' . nl2br(htmlspecialchars($completionNotes)) . '</p>';
                    $description .= '<hr>';
                }
                $description .= '<p>' . ($noteData->description ?? '') . '</p>';

                ActivitiesLog::create([
                    'client_id' => $noteData->client_id,
                    'created_by' => Auth::id(),
                    'subject' => 'completed action for ' . $assigneeName,
                    'description' => $description,
                    'use_for' => (Auth::id() != $noteData->assigned_to) ? $noteData->assigned_to : null,
                    'followup_date' => $noteData->updated_at,
                    'task_group' => $noteData->task_group ?? null,
                    'task_status' => 1,
                    'pin' => 0,
                ]);
            }

            // Client Portal category only: notify client (notification list API + push + real-time)
            if ((string) $taskGroup === 'Client Portal') {
                $messageText = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", (string) ($noteData->description ?? ''))));
                if (mb_strlen($messageText) > 200) {
                    $messageText = mb_substr($messageText, 0, 197) . '...';
                }
                $notificationMessage = 'This action is completed. ' . ($messageText ?: 'An action has been completed for your matter.');
                // module_id = client matter id so notification appears in List API when client filters by client_matter_id
                $moduleId = !empty($noteData->matter_id) ? (int) $noteData->matter_id : null;
                if ($moduleId === null) {
                    $moduleId = ClientMatter::where('client_id', $noteData->client_id)->orderByDesc('id')->value('id') ?? $noteData->client_id;
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
                    $fcm = new FCMService();
                    $fcm->sendToUser($noteData->client_id, 'Action completed', $notificationMessage, [
                        'type' => 'action_completed',
                        'client_matter_id' => (string) $moduleId,
                        'url' => '/activities',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('FCM send failed on action complete (Client Portal)', ['client_id' => $noteData->client_id, 'error' => $e->getMessage()]);
                }
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $noteData->client_id)->where('receiver_status', 0)->count();
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
    public function getVisaExpiryMessage($clientId): string
    {
        $visaInfo = ClientVisaCountry::where('client_id', $clientId)
            ->latest('id')
            ->first();

        if (!$visaInfo || !$visaInfo->visa_expiry_date) {
            return '';
        }

        $visaExpiredAt = Carbon::parse($visaInfo->visa_expiry_date);
        $today = Carbon::now();
        $sevenDaysFromNow = Carbon::now()->addDays(7);

        if ($visaExpiredAt->lt($today)) {
            return 'Your visa is expired';
        } elseif ($visaExpiredAt->gte($today) && $visaExpiredAt->lte($sevenDaysFromNow)) {
            $daysRemaining = $visaExpiredAt->diffInDays($today);
            return "Your visa is expiring in next $daysRemaining day" . ($daysRemaining == 1 ? '' : 's');
        }

        return '';
    }

    /**
     * Create notification and activity log
     */
    private function createNotificationAndActivityLog($note): void
    {
        try {
            // Create notification only if assigned_to exists
            if ($note->assigned_to) {
                $notificationUrl = $note->client_id
                    ? url('/clients/detail/' . base64_encode(convert_uuencode($note->client_id)))
                    : url('/action');
                Notification::create([
                    'sender_id' => Auth::id(),
                    'receiver_id' => $note->assigned_to,
                    'module_id' => $note->client_id ?? 0,
                    'url' => $notificationUrl,
                    'notification_type' => 'client',
                    'message' => 'Action Extended by ' . Auth::user()->first_name . ' ' . Auth::user()->last_name . ' on ' . date('d/M/Y h:i A')
                ]);
            }

            // Create activity log (client_id may be null for Personal Actions)
            ActivitiesLog::create([
                'client_id' => $note->client_id,
                'created_by' => Auth::id(),
                'subject' => 'Extended Note Deadline',
                'description' => '<span class="text-semi-bold">' . ($note->title ?? 'Note') . '</span><p>' . ($note->description ?? '') . '</p>',
                'use_for' => Auth::id() != $note->user_id ? $note->user_id : '',
                'followup_date' => $note->action_date ?? null,
                'task_group' => $note->task_group ?? null,
                'task_status' => 0,
                'pin' => 0,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't break the main functionality
            Log::error('Error creating notification/activity log: ' . $e->getMessage());
        }
    }
}
