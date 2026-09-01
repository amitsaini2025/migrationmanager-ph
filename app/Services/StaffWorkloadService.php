<?php

namespace App\Services;

use App\Models\ActivitiesLog;
use App\Models\Note;
use App\Models\Staff;
use App\Support\ActionTaskGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StaffWorkloadService
{
    public const METRIC_COMPLETED_EXCL_CALL = 'completed_excl_call';

    public const METRIC_UPDATED = 'updated';

    public const METRIC_PENDING = 'pending';

    public const METRIC_CALL_COMPLETED = 'call_completed';

    public const METRIC_CALL_NOTES = 'call_notes';

    public const METRIC_IN_PERSON = 'in_person';

    /**
     * @return list<string>
     */
    public static function metricKeys(): array
    {
        return [
            self::METRIC_COMPLETED_EXCL_CALL,
            self::METRIC_UPDATED,
            self::METRIC_PENDING,
            self::METRIC_CALL_COMPLETED,
            self::METRIC_CALL_NOTES,
            self::METRIC_IN_PERSON,
        ];
    }

    /**
     * @return array{
     *     date_label: string,
     *     date: string,
     *     timezone: string,
     *     completed_excl_call: array<string, mixed>,
     *     updated: array<string, mixed>,
     *     pending: array<string, mixed>,
     *     call_completed: array<string, mixed>,
     *     contact_today: array<string, mixed>
     * }
     */
    public function getDashboardWorkload(int $staffId, ?Carbon $day = null): array
    {
        [$start, $end] = $this->dayBounds($day);
        $dateKey = $start->toDateString();
        $ttl = max(1, (int) config('cache.dashboard_kpi_counts_ttl', 60));

        return Cache::remember($this->dashboardCacheKey($staffId, $dateKey), $ttl, function () use ($staffId, $start, $end, $dateKey) {
            return $this->buildWorkloadSummary($staffId, $start, $end, $dateKey);
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getAdminWorkloadRows(?Carbon $day = null): Collection
    {
        [$start, $end] = $this->dayBounds($day);
        $dateKey = $start->toDateString();

        $staffRows = Staff::query()
            ->active()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $completed = $this->aggregateFeedByStaff($start, $end, 'completed action for%', excludeCall: true);
        $callCompleted = $this->aggregateFeedByStaff($start, $end, 'completed action for%', onlyCall: true);
        $updated = $this->aggregateFeedByStaff($start, $end, 'Updated action for%');
        $pending = $this->aggregatePendingByStaff($start);
        $callNotes = $this->aggregateContactNotesByStaff($start, $end, 'Call');
        $inPerson = $this->aggregateContactNotesByStaff($start, $end, 'In-Person');

        return $staffRows->map(function (Staff $staff) use ($completed, $callCompleted, $updated, $pending, $callNotes, $inPerson, $dateKey) {
            $id = (int) $staff->id;

            return [
                'staff_id' => $id,
                'name' => trim($staff->first_name.' '.$staff->last_name),
                'date' => $dateKey,
                'completed_excl_call' => $completed[$id] ?? $this->emptyAudienceSplit(),
                'updated' => $updated[$id] ?? $this->emptyAudienceSplit(),
                'pending' => $pending[$id] ?? $this->emptyPendingSplit(),
                'call_completed' => $callCompleted[$id] ?? $this->emptyAudienceSplit(),
                'call_notes' => $callNotes[$id] ?? $this->emptyAudienceSplit(),
                'in_person' => $inPerson[$id] ?? $this->emptyAudienceSplit(),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDrillDownItems(int $staffId, string $metric, ?Carbon $day = null, int $limit = 50): array
    {
        [$start, $end] = $this->dayBounds($day);

        return match ($metric) {
            self::METRIC_COMPLETED_EXCL_CALL => $this->feedDrillDown($staffId, $start, $end, 'completed action for%', excludeCall: true, limit: $limit),
            self::METRIC_CALL_COMPLETED => $this->feedDrillDown($staffId, $start, $end, 'completed action for%', onlyCall: true, limit: $limit),
            self::METRIC_UPDATED => $this->feedDrillDown($staffId, $start, $end, 'Updated action for%', limit: $limit),
            self::METRIC_PENDING => $this->pendingDrillDown($staffId, $start, $limit),
            self::METRIC_CALL_NOTES => $this->contactDrillDown($staffId, $start, $end, 'Call', $limit),
            self::METRIC_IN_PERSON => $this->contactDrillDown($staffId, $start, $end, 'In-Person', $limit),
            default => [],
        };
    }

    public static function forgetForStaff(int $staffId, ?Carbon $day = null): void
    {
        $tz = (string) config('app.timezone');
        $dateKey = ($day ?? now($tz))->copy()->timezone($tz)->toDateString();
        Cache::forget('dashboard:workload:v1:'.$staffId.':'.$dateKey);
    }

    public static function forgetForStaffIds(int ...$staffIds): void
    {
        foreach (array_unique(array_filter($staffIds, static fn (int $id): bool => $id > 0)) as $staffId) {
            self::forgetForStaff($staffId);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function dayBounds(?Carbon $day = null): array
    {
        $tz = (string) config('app.timezone');
        $resolved = ($day ?? now($tz))->copy()->timezone($tz);

        return [$resolved->copy()->startOfDay(), $resolved->copy()->endOfDay()];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorkloadSummary(int $staffId, Carbon $start, Carbon $end, string $dateKey): array
    {
        $completed = $this->summariseFeed($staffId, $start, $end, 'completed action for%', excludeCall: true, withBreakdown: true);
        $callCompleted = $this->summariseFeed($staffId, $start, $end, 'completed action for%', onlyCall: true);
        $updated = $this->summariseFeed($staffId, $start, $end, 'Updated action for%');
        $pending = $this->summarisePending($staffId, $start);
        $callNotes = $this->summariseContactNotes($staffId, $start, $end, 'Call');
        $inPerson = $this->summariseContactNotes($staffId, $start, $end, 'In-Person');

        return [
            'date_label' => $start->format('l, j M Y'),
            'date' => $dateKey,
            'timezone' => (string) config('app.timezone'),
            'completed_excl_call' => $completed,
            'updated' => $updated,
            'pending' => $pending,
            'call_completed' => $callCompleted,
            'contact_today' => [
                'call_notes' => $callNotes,
                'in_person' => $inPerson,
            ],
        ];
    }

    private function dashboardCacheKey(int $staffId, string $dateKey): string
    {
        return 'dashboard:workload:v1:'.$staffId.':'.$dateKey;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function classificationCutoffs(Carbon $todayStart): array
    {
        $newDays = (int) config('crm.workload.new_record_days', 14);
        $gapDays = (int) config('crm.workload.returning_gap_days', 365);

        return [
            $todayStart->copy()->subDays($newDays),
            $todayStart->copy()->subDays($gapDays),
        ];
    }

    private function lastContactSubquery(Carbon $todayStart): QueryBuilder
    {
        return DB::table('notes')
            ->selectRaw('client_id, MAX(created_at) as last_at')
            ->where('is_action', 0)
            ->whereNull('assigned_to')
            ->whereIn('task_group', ['Call', 'In-Person'])
            ->where('created_at', '<', $todayStart->toDateTimeString())
            ->groupBy('client_id');
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyPersonJoins(Builder $query, string $clientIdColumn, Carbon $todayStart): void
    {
        $query->leftJoin('admins', 'admins.id', '=', $clientIdColumn);
        $query->leftJoinSub($this->lastContactSubquery($todayStart), 'last_contact', function ($join) use ($clientIdColumn) {
            $join->on('last_contact.client_id', '=', $clientIdColumn);
        });
    }

    private function audienceSql(): string
    {
        return "CASE WHEN admins.id IS NULL THEN 'personal' WHEN admins.type = 'lead' THEN 'leads' ELSE 'clients' END";
    }

    private function peopleClassSql(Carbon $newCutoff, Carbon $returningCutoff): string
    {
        $new = $this->quoteTimestamp($newCutoff);
        $returning = $this->quoteTimestamp($returningCutoff);

        return "CASE
            WHEN admins.id IS NULL THEN NULL
            WHEN admins.created_at >= {$new} THEN 'new'
            WHEN last_contact.last_at IS NULL OR last_contact.last_at < {$returning} THEN 'returning'
            ELSE 'current'
        END";
    }

    private function quoteTimestamp(Carbon $value): string
    {
        return DB::getPdo()->quote($value->toDateTimeString());
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<string, mixed>
     */
    private function tallyAudienceAndClass(Builder $query, string $clientIdColumn, Carbon $todayStart): array
    {
        [$newCutoff, $returningCutoff] = $this->classificationCutoffs($todayStart);
        $this->applyPersonJoins($query, $clientIdColumn, $todayStart);

        $audience = $this->audienceSql();
        $peopleClass = $this->peopleClassSql($newCutoff, $returningCutoff);

        $row = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN {$audience} = 'clients' THEN 1 ELSE 0 END) as clients")
            ->selectRaw("SUM(CASE WHEN {$audience} = 'leads' THEN 1 ELSE 0 END) as leads")
            ->selectRaw("SUM(CASE WHEN {$audience} = 'personal' THEN 1 ELSE 0 END) as personal")
            ->selectRaw("SUM(CASE WHEN {$peopleClass} = 'new' THEN 1 ELSE 0 END) as new_count")
            ->selectRaw("SUM(CASE WHEN {$peopleClass} = 'returning' THEN 1 ELSE 0 END) as returning_count")
            ->selectRaw("SUM(CASE WHEN {$peopleClass} = 'current' THEN 1 ELSE 0 END) as current_count")
            ->first();

        return $this->mapTallyRow($row);
    }

    /**
     * @return array<string, int>
     */
    private function mapTallyRow(?object $row): array
    {
        return [
            'total' => (int) ($row?->total ?? 0),
            'clients' => (int) ($row?->clients ?? 0),
            'leads' => (int) ($row?->leads ?? 0),
            'personal' => (int) ($row?->personal ?? 0),
            'new' => (int) ($row?->new_count ?? 0),
            'returning' => (int) ($row?->returning_count ?? 0),
            'current' => (int) ($row?->current_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summariseFeed(
        int $staffId,
        Carbon $start,
        Carbon $end,
        string $subjectLike,
        bool $excludeCall = false,
        bool $onlyCall = false,
        bool $withBreakdown = false,
    ): array {
        $query = $this->feedBaseQuery($staffId, $start, $end, $subjectLike, $excludeCall, $onlyCall);
        $split = $this->tallyAudienceAndClass($query, 'activities_logs.client_id', $start);

        if ($withBreakdown) {
            $split['breakdown'] = $this->feedBaseQuery($staffId, $start, $end, $subjectLike, $excludeCall, $onlyCall)
                ->selectRaw('activities_logs.task_group as task_group, COUNT(*) as cnt')
                ->groupBy('activities_logs.task_group')
                ->orderByDesc('cnt')
                ->limit(3)
                ->pluck('cnt', 'task_group')
                ->map(fn ($count) => (int) $count)
                ->all();
        }

        return $split;
    }

    /**
     * @return array<string, mixed>
     */
    private function summarisePending(int $staffId, Carbon $todayStart): array
    {
        $base = $this->pendingBaseQuery($staffId);
        $split = $this->tallyAudienceAndClass(clone $base, 'notes.client_id', $todayStart);
        $split['call'] = (clone $base)->where('notes.task_group', 'Call')->count();
        $split['other'] = max(0, $split['total'] - $split['call']);

        return $split;
    }

    /**
     * @return array<string, mixed>
     */
    private function summariseContactNotes(int $staffId, Carbon $start, Carbon $end, string $taskGroup): array
    {
        $query = $this->contactNoteBaseQuery($staffId, $start, $end, $taskGroup);

        return $this->tallyAudienceAndClass($query, 'notes.client_id', $start);
    }

    /**
     * @return Builder<Model>
     */
    private function feedBaseQuery(
        int $staffId,
        Carbon $start,
        Carbon $end,
        string $subjectLike,
        bool $excludeCall = false,
        bool $onlyCall = false,
    ): Builder {
        $query = ActivitiesLog::query()
            ->where('activities_logs.created_by', $staffId)
            ->where('activities_logs.subject', 'like', $subjectLike)
            ->whereBetween('activities_logs.created_at', [$start, $end]);

        if (str_starts_with($subjectLike, 'completed action')) {
            $query->where('activities_logs.task_status', 1);
        }

        if ($excludeCall) {
            $query->where(function ($inner) {
                $inner->whereNull('activities_logs.task_group')
                    ->orWhere('activities_logs.task_group', '!=', 'Call');
            });
        }

        if ($onlyCall) {
            $query->where('activities_logs.task_group', 'Call');
        }

        return $query;
    }

    /**
     * @return Builder<Model>
     */
    private function pendingBaseQuery(int $staffId): Builder
    {
        $query = Note::query()
            ->where('notes.type', 'client')
            ->where('notes.is_action', 1)
            ->where('notes.status', '<>', '1')
            ->where('notes.assigned_to', $staffId);

        ActionTaskGroup::hideFollowUpsNotYetDue($query, 'notes.task_group', 'notes.action_date');

        return $query;
    }

    /**
     * @return Builder<Model>
     */
    private function contactNoteBaseQuery(int $staffId, Carbon $start, Carbon $end, string $taskGroup): Builder
    {
        return Note::query()
            ->where('notes.user_id', $staffId)
            ->where('notes.is_action', 0)
            ->whereNull('notes.assigned_to')
            ->whereIn('notes.type', ['client', 'lead'])
            ->where('notes.task_group', $taskGroup)
            ->whereBetween('notes.created_at', [$start, $end]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function aggregateFeedByStaff(
        Carbon $start,
        Carbon $end,
        string $subjectLike,
        bool $excludeCall = false,
        bool $onlyCall = false,
    ): array {
        $query = ActivitiesLog::query()
            ->where('activities_logs.subject', 'like', $subjectLike)
            ->whereBetween('activities_logs.created_at', [$start, $end]);

        if (str_starts_with($subjectLike, 'completed action')) {
            $query->where('activities_logs.task_status', 1);
        }

        if ($excludeCall) {
            $query->where(function ($inner) {
                $inner->whereNull('activities_logs.task_group')
                    ->orWhere('activities_logs.task_group', '!=', 'Call');
            });
        }

        if ($onlyCall) {
            $query->where('activities_logs.task_group', 'Call');
        }

        return $this->groupTallyByStaff($query, 'activities_logs.created_by', 'activities_logs.client_id', $start);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function aggregatePendingByStaff(Carbon $todayStart): array
    {
        $query = Note::query()
            ->where('notes.type', 'client')
            ->where('notes.is_action', 1)
            ->where('notes.status', '<>', '1')
            ->whereNotNull('notes.assigned_to');

        ActionTaskGroup::hideFollowUpsNotYetDue($query, 'notes.task_group', 'notes.action_date');

        $grouped = $this->groupTallyByStaff($query, 'notes.assigned_to', 'notes.client_id', $todayStart);

        $callQuery = Note::query()
            ->where('notes.type', 'client')
            ->where('notes.is_action', 1)
            ->where('notes.status', '<>', '1')
            ->whereNotNull('notes.assigned_to')
            ->where('notes.task_group', 'Call');

        ActionTaskGroup::hideFollowUpsNotYetDue($callQuery, 'notes.task_group', 'notes.action_date');

        $callCounts = $callQuery
            ->selectRaw('notes.assigned_to as staff_id, COUNT(*) as cnt')
            ->groupBy('notes.assigned_to')
            ->pluck('cnt', 'staff_id');

        foreach ($grouped as $staffId => $row) {
            $call = (int) ($callCounts[$staffId] ?? 0);
            $grouped[$staffId]['call'] = $call;
            $grouped[$staffId]['other'] = max(0, $row['total'] - $call);
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function aggregateContactNotesByStaff(Carbon $start, Carbon $end, string $taskGroup): array
    {
        $query = Note::query()
            ->where('notes.is_action', 0)
            ->whereNull('notes.assigned_to')
            ->whereIn('notes.type', ['client', 'lead'])
            ->where('notes.task_group', $taskGroup)
            ->whereBetween('notes.created_at', [$start, $end]);

        return $this->groupTallyByStaff($query, 'notes.user_id', 'notes.client_id', $start);
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<int, array<string, mixed>>
     */
    private function groupTallyByStaff(Builder $query, string $staffColumn, string $clientIdColumn, Carbon $todayStart): array
    {
        [$newCutoff, $returningCutoff] = $this->classificationCutoffs($todayStart);
        $this->applyPersonJoins($query, $clientIdColumn, $todayStart);

        $audience = $this->audienceSql();
        $peopleClass = $this->peopleClassSql($newCutoff, $returningCutoff);

        $rows = $query
            ->selectRaw($staffColumn.' as staff_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN {$audience} = 'clients' THEN 1 ELSE 0 END) as clients")
            ->selectRaw("SUM(CASE WHEN {$audience} = 'leads' THEN 1 ELSE 0 END) as leads")
            ->selectRaw("SUM(CASE WHEN {$audience} = 'personal' THEN 1 ELSE 0 END) as personal")
            ->selectRaw("SUM(CASE WHEN {$peopleClass} = 'new' THEN 1 ELSE 0 END) as new_count")
            ->selectRaw("SUM(CASE WHEN {$peopleClass} = 'returning' THEN 1 ELSE 0 END) as returning_count")
            ->selectRaw("SUM(CASE WHEN {$peopleClass} = 'current' THEN 1 ELSE 0 END) as current_count")
            ->groupBy($staffColumn)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->staff_id;
            $out[$id] = $this->mapTallyRow($row);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function feedDrillDown(
        int $staffId,
        Carbon $start,
        Carbon $end,
        string $subjectLike,
        bool $excludeCall = false,
        bool $onlyCall = false,
        int $limit = 50,
    ): array {
        $query = $this->feedBaseQuery($staffId, $start, $end, $subjectLike, $excludeCall, $onlyCall);
        $this->applyPersonJoins($query, 'activities_logs.client_id', $start);

        return $query
            ->orderByDesc('activities_logs.created_at')
            ->limit($limit)
            ->get([
                'activities_logs.id',
                'activities_logs.client_id',
                'activities_logs.task_group',
                'activities_logs.created_at',
                'admins.first_name',
                'admins.last_name',
                'admins.client_id as client_ref',
                'admins.type as admin_type',
                'admins.created_at as admin_created_at',
                'last_contact.last_at as last_contact_at',
            ])
            ->map(fn ($row) => $this->mapPersonRow($row, $row->task_group, $row->created_at, $start))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingDrillDown(int $staffId, Carbon $todayStart, int $limit): array
    {
        $query = $this->pendingBaseQuery($staffId);
        $this->applyPersonJoins($query, 'notes.client_id', $todayStart);

        return $query
            ->orderByRaw('CASE WHEN notes.note_deadline IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('notes.action_date')
            ->limit($limit)
            ->get([
                'notes.id',
                'notes.client_id',
                'notes.task_group',
                'notes.action_date',
                'notes.created_at',
                'admins.first_name',
                'admins.last_name',
                'admins.client_id as client_ref',
                'admins.type as admin_type',
                'admins.created_at as admin_created_at',
                'last_contact.last_at as last_contact_at',
            ])
            ->map(fn ($row) => $this->mapPersonRow($row, $row->task_group, $row->action_date ?? $row->created_at, $todayStart))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contactDrillDown(int $staffId, Carbon $start, Carbon $end, string $taskGroup, int $limit): array
    {
        $query = $this->contactNoteBaseQuery($staffId, $start, $end, $taskGroup);
        $this->applyPersonJoins($query, 'notes.client_id', $start);

        return $query
            ->orderByDesc('notes.created_at')
            ->limit($limit)
            ->get([
                'notes.id',
                'notes.client_id',
                'notes.task_group',
                'notes.description',
                'notes.created_at',
                'admins.first_name',
                'admins.last_name',
                'admins.client_id as client_ref',
                'admins.type as admin_type',
                'admins.created_at as admin_created_at',
                'last_contact.last_at as last_contact_at',
            ])
            ->map(function ($row) use ($start) {
                $mapped = $this->mapPersonRow($row, $row->task_group, $row->created_at, $start);
                $mapped['snippet'] = mb_substr(trim(strip_tags((string) $row->description)), 0, 120);

                return $mapped;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPersonRow(object $row, ?string $taskGroup, mixed $at, Carbon $todayStart): array
    {
        $clientId = $row->client_id ? (int) $row->client_id : null;
        $audience = $clientId === null ? 'personal' : (($row->admin_type ?? '') === 'lead' ? 'lead' : 'client');
        $peopleClass = $this->classifyPerson($row->admin_created_at ?? null, $row->last_contact_at ?? null, $todayStart);

        $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
        if ($name === '') {
            $name = $audience === 'personal' ? 'Personal action' : 'Unknown';
        }

        return [
            'id' => (int) $row->id,
            'client_id' => $clientId,
            'name' => $name,
            'client_ref' => $row->client_ref ?? null,
            'audience' => $audience,
            'people_class' => $peopleClass,
            'task_group' => $taskGroup,
            'at' => $at ? Carbon::parse($at)->timezone((string) config('app.timezone'))->format('g:i a') : null,
            'url' => $clientId ? url('/clients/detail/'.base64_encode(convert_uuencode((string) $clientId))) : null,
        ];
    }

    private function classifyPerson(?string $adminCreatedAt, ?string $lastContactAt, Carbon $todayStart): ?string
    {
        if ($adminCreatedAt === null) {
            return null;
        }

        [$newCutoff, $returningCutoff] = $this->classificationCutoffs($todayStart);

        if (Carbon::parse($adminCreatedAt)->gte($newCutoff)) {
            return 'new';
        }

        if ($lastContactAt === null || Carbon::parse($lastContactAt)->lt($returningCutoff)) {
            return 'returning';
        }

        return 'current';
    }

    /**
     * @return array<string, int>
     */
    private function emptyAudienceSplit(): array
    {
        return [
            'total' => 0,
            'clients' => 0,
            'leads' => 0,
            'personal' => 0,
            'new' => 0,
            'returning' => 0,
            'current' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyPendingSplit(): array
    {
        return $this->emptyAudienceSplit() + [
            'call' => 0,
            'other' => 0,
        ];
    }
}
