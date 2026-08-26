<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Note;
use App\Models\Staff;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LeadAnalyticsService
{
    /**
     * @var array<string, object>
     */
    private array $leadStageCache = [];

    /**
     * @var array<string, object>
     */
    private array $convertedCache = [];

    /**
     * Get conversion funnel statistics using actual pipeline stages
     */
    public function getConversionFunnel($startDate = null, $endDate = null)
    {
        $leadCounts = $this->leadStageCounts($startDate, $endDate);
        $converted = (int) $this->convertedStats($startDate, $endDate)->converted;
        $totalLeads = (int) $leadCounts->total_leads;
        $denominator = max($totalLeads, 1);

        return [
            'total_leads' => $totalLeads,
            'new' => $this->stageBucket((int) $leadCounts->stage_new, $denominator),
            'follow_up' => $this->stageBucket((int) $leadCounts->follow_up, $denominator),
            'not_qualified' => $this->stageBucket((int) $leadCounts->not_qualified, $denominator),
            'hostile' => $this->stageBucket((int) $leadCounts->hostile, $denominator),
            'converted' => $this->stageBucket($converted, $denominator),
        ];
    }

    /**
     * Get lead source performance
     */
    public function getSourcePerformance($startDate = null, $endDate = null)
    {
        $admins = (new Admin)->getTable();

        $sources = $this->applyCreatedAtRange(
            DB::table($admins)->where('type', 'lead'),
            $startDate,
            $endDate
        )
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->get();

        $convertedBySource = $this->applyCreatedAtRange(
            DB::table($admins)->where('type', 'client')->where('lead_status', 'converted'),
            $startDate,
            $endDate
        )
            ->select('source', DB::raw('COUNT(*) as converted'))
            ->groupBy('source')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) ($row->source ?? '') => (int) $row->converted]);

        $performance = [];
        foreach ($sources as $source) {
            $total = (int) $source->total;
            $converted = $convertedBySource[(string) ($source->source ?? '')] ?? 0;

            $performance[] = [
                'source' => $source->source ?: 'Unknown',
                'total_leads' => $total,
                'converted' => $converted,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 2) : 0,
            ];
        }

        usort($performance, fn ($a, $b) => $b['total_leads'] <=> $a['total_leads']);

        return $performance;
    }

    /**
     * Get agent performance metrics
     */
    public function getAgentPerformance($startDate = null, $endDate = null)
    {
        $agents = Staff::query()
            ->where('status', 1)
            ->get(['id', 'first_name', 'last_name']);

        $admins = (new Admin)->getTable();
        $now = now();

        $leadStats = $this->applyCreatedAtRange(
            DB::table($admins)->where('type', 'lead'),
            $startDate,
            $endDate
        )
            ->select('user_id')
            ->selectRaw('COUNT(*) as assigned_leads')
            ->selectRaw('COUNT(CASE WHEN lead_status = ? AND followup_date IS NOT NULL AND followup_date < ? THEN 1 END) as overdue_followups', ['follow_up', $now])
            ->groupBy('user_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $convertedStats = $this->applyCreatedAtRange(
            DB::table($admins)->where('type', 'client')->where('lead_status', 'converted'),
            $startDate,
            $endDate
        )
            ->select('user_id', DB::raw('COUNT(*) as converted_leads'))
            ->groupBy('user_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $completedFollowups = DB::table((new Note)->getTable())
            ->where('task_group', 'Follow Up')
            ->where('status', '1')
            ->select('assigned_to', DB::raw('COUNT(*) as completed_followups'))
            ->groupBy('assigned_to')
            ->pluck('completed_followups', 'assigned_to')
            ->mapWithKeys(fn ($count, $id) => [(int) $id => (int) $count]);

        $performance = [];
        foreach ($agents as $agent) {
            $assignedLeads = (int) ($leadStats[$agent->id]->assigned_leads ?? 0);
            $convertedLeads = (int) ($convertedStats[$agent->id]->converted_leads ?? 0);

            $performance[] = [
                'agent_id' => $agent->id,
                'agent_name' => $agent->first_name.' '.$agent->last_name,
                'assigned_leads' => $assignedLeads,
                'converted_leads' => $convertedLeads,
                'conversion_rate' => $assignedLeads > 0 ? round(($convertedLeads / $assignedLeads) * 100, 2) : 0,
                'completed_followups' => (int) ($completedFollowups[$agent->id] ?? 0),
                'overdue_followups' => (int) ($leadStats[$agent->id]->overdue_followups ?? 0),
                'avg_response_time_hours' => 0,
            ];
        }

        usort($performance, fn ($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return $performance;
    }

    /**
     * Calculate average response time for an agent
     * Note: Follow-up system removed, returns 0
     */
    protected function calculateAvgResponseTime($agentId, $startDate = null, $endDate = null)
    {
        return 0;
    }

    /**
     * Get time-based lead trends
     */
    public function getLeadTrends($period = 'month', $count = 12)
    {
        $windows = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $date = match ($period) {
                'week' => now()->subWeeks($i),
                'year' => now()->subYears($i),
                default => now()->subMonths($i),
            };

            $startDate = match ($period) {
                'week' => $date->copy()->startOfWeek(),
                'year' => $date->copy()->startOfYear(),
                default => $date->copy()->startOfMonth(),
            };

            $endDate = match ($period) {
                'week' => $date->copy()->endOfWeek(),
                'year' => $date->copy()->endOfYear(),
                default => $date->copy()->endOfMonth(),
            };

            $windows[] = [
                'start' => $startDate,
                'end' => $endDate,
                'label' => $startDate->format($period == 'year' ? 'Y' : ($period == 'week' ? 'M d' : 'M Y')),
            ];
        }

        $admins = (new Admin)->getTable();
        $bindings = [];
        $leadSelects = [];
        $convertedSelects = [];

        foreach ($windows as $index => $window) {
            $leadSelects[] = "COUNT(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 END) as l{$index}";
            $convertedSelects[] = "COUNT(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 END) as c{$index}";
            $bindings[] = $window['start'];
            $bindings[] = $window['end'];
        }

        $leadRow = DB::table($admins)
            ->where('type', 'lead')
            ->selectRaw(implode(', ', $leadSelects), $bindings)
            ->first();

        $convertedRow = DB::table($admins)
            ->where('type', 'client')
            ->where('lead_status', 'converted')
            ->selectRaw(implode(', ', $convertedSelects), $bindings)
            ->first();

        $trends = [];
        foreach ($windows as $index => $window) {
            $newLeads = (int) ($leadRow->{'l'.$index} ?? 0);
            $converted = (int) ($convertedRow->{'c'.$index} ?? 0);

            $trends[] = [
                'period' => $window['label'],
                'new_leads' => $newLeads,
                'converted' => $converted,
                'conversion_rate' => $newLeads > 0 ? round(($converted / $newLeads) * 100, 2) : 0,
            ];
        }

        return $trends;
    }

    /**
     * Get lead quality distribution
     */
    public function getLeadQualityDistribution($startDate = null, $endDate = null)
    {
        return [];
    }

    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats($startDate = null, $endDate = null)
    {
        $leadCounts = $this->leadStageCounts($startDate, $endDate);
        $converted = $this->convertedStats($startDate, $endDate);
        $followUp = (int) $leadCounts->follow_up;

        return [
            'total_leads' => (int) $leadCounts->total_leads,
            'new_this_month' => Admin::query()
                ->where('type', 'lead')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'converted' => (int) $converted->converted,
            'active' => (int) $leadCounts->active,
            'active_new' => (int) $leadCounts->stage_new,
            'active_follow_up' => $followUp,
            'pending_followups' => $followUp,
            'overdue_followups' => (int) $leadCounts->overdue_followups,
            'avg_conversion_time' => $converted->avg_conversion_days === null
                ? 0
                : round((float) $converted->avg_conversion_days, 1),
        ];
    }

    /**
     * @return array{count: int, percentage: float|int}
     */
    private function stageBucket(int $count, int $denominator): array
    {
        return [
            'count' => $count,
            'percentage' => round(($count / $denominator) * 100, 2),
        ];
    }

    private function leadStageCounts($startDate, $endDate): object
    {
        $key = $this->rangeKey($startDate, $endDate);
        if (isset($this->leadStageCache[$key])) {
            return $this->leadStageCache[$key];
        }

        $row = $this->applyCreatedAtRange(Admin::query()->where('type', 'lead'), $startDate, $endDate)
            ->toBase()
            ->selectRaw('COUNT(*) as total_leads')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as active', [1])
            ->selectRaw("COUNT(CASE WHEN lead_status = 'new' THEN 1 END) as stage_new")
            ->selectRaw("COUNT(CASE WHEN lead_status = 'follow_up' THEN 1 END) as follow_up")
            ->selectRaw("COUNT(CASE WHEN lead_status = 'not_qualified' THEN 1 END) as not_qualified")
            ->selectRaw("COUNT(CASE WHEN lead_status = 'hostile' THEN 1 END) as hostile")
            ->selectRaw('COUNT(CASE WHEN lead_status = ? AND followup_date IS NOT NULL AND followup_date < ? THEN 1 END) as overdue_followups', ['follow_up', now()])
            ->first();

        return $this->leadStageCache[$key] = $row ?? (object) [
            'total_leads' => 0,
            'active' => 0,
            'stage_new' => 0,
            'follow_up' => 0,
            'not_qualified' => 0,
            'hostile' => 0,
            'overdue_followups' => 0,
        ];
    }

    private function convertedStats($startDate, $endDate): object
    {
        $key = $this->rangeKey($startDate, $endDate);
        if (isset($this->convertedCache[$key])) {
            return $this->convertedCache[$key];
        }

        $avgExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => 'AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 86400.0)',
            'sqlite' => 'AVG((julianday(updated_at) - julianday(created_at)))',
            default => 'AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at) / 86400.0)',
        };

        $row = $this->applyCreatedAtRange(
            Admin::query()->where('type', 'client')->where('lead_status', 'converted'),
            $startDate,
            $endDate
        )
            ->toBase()
            ->selectRaw('COUNT(*) as converted')
            ->selectRaw("{$avgExpression} as avg_conversion_days")
            ->first();

        return $this->convertedCache[$key] = $row ?? (object) [
            'converted' => 0,
            'avg_conversion_days' => null,
        ];
    }

    private function applyCreatedAtRange(Builder|\Illuminate\Database\Eloquent\Builder $query, $startDate, $endDate): Builder|\Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', $endDate));
    }

    private function rangeKey($startDate, $endDate): string
    {
        $start = $startDate instanceof DateTimeInterface ? $startDate->format('c') : (string) $startDate;
        $end = $endDate instanceof DateTimeInterface ? $endDate->format('c') : (string) $endDate;

        return $start.'|'.$end;
    }
}
