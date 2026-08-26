<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signer;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SignatureAnalyticsService
{
    /**
     * Per-request cache of signed-document durations (hours + owner).
     *
     * @var Collection<int, object>|null
     */
    private ?Collection $signedDurationRows = null;

    /**
     * Get median time to sign (in hours)
     *
     * @param  string|null  $documentType  Filter by document type
     * @param  int|null  $ownerId  Filter by owner
     */
    public function getMedianTimeToSign($documentType = null, $ownerId = null): float
    {
        return $this->median($this->signedHours($ownerId));
    }

    /**
     * Get top signers (repeat recipients)
     *
     * @return Collection<int, Signer>
     */
    public function getTopSigners(int $limit = 10)
    {
        $hoursExpr = $this->hoursBetweenSql('signers.created_at', 'signers.signed_at');

        return Signer::query()
            ->select('email', 'name')
            ->selectRaw('COUNT(*) as total_signed')
            ->selectRaw("COUNT(CASE WHEN status = 'signed' THEN 1 END) as completed_count")
            ->selectRaw("AVG(CASE WHEN signed_at IS NOT NULL THEN {$hoursExpr} END) as avg_time_hours")
            ->groupBy('email', 'name')
            ->orderByDesc('completed_count')
            ->limit($limit)
            ->get()
            ->map(function ($signer) {
                $signer->avg_time_hours = $signer->avg_time_hours !== null
                    ? round((float) $signer->avg_time_hours, 1)
                    : null;

                return $signer;
            });
    }

    /**
     * Get document type statistics
     *
     * @return Collection<int, object>
     */
    public function getDocumentTypeStats()
    {
        $stat = $this->signatureDocuments()
            ->toBase()
            ->selectRaw("'general' as document_type")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END), 0) as signed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) as draft")
            ->first();

        $total = (int) ($stat->total ?? 0);
        $signed = (int) ($stat->signed ?? 0);
        $avgHours = $this->average($this->signedHours());

        return collect([(object) [
            'document_type' => 'general',
            'total' => $total,
            'signed' => $signed,
            'pending' => (int) ($stat->pending ?? 0),
            'draft' => (int) ($stat->draft ?? 0),
            'avg_time_hours' => $avgHours > 0 ? round($avgHours, 1) : null,
            'completion_rate' => $total > 0 ? round(($signed / $total) * 100, 1) : 0,
        ]]);
    }

    /**
     * Get overdue documents with details
     *
     * @return Collection<int, mixed>
     */
    public function getOverdueAnalytics()
    {
        return collect(); // due_at column removed - no overdue analytics
    }

    /**
     * Get completion rate for a date range
     */
    public function getCompletionRate($startDate, $endDate): float
    {
        [$from, $to] = $this->dateBounds($startDate, $endDate);

        $row = $this->signatureDocuments()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', ['sent', 'signed'])
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END), 0) as signed")
            ->first();

        $total = (int) ($row->total ?? 0);
        if ($total === 0) {
            return 0;
        }

        return round(((int) $row->signed / $total) * 100, 1);
    }

    /**
     * Get average number of reminders sent
     */
    public function getAverageReminders($startDate, $endDate): float
    {
        [$from, $to] = $this->dateBounds($startDate, $endDate);

        $avg = Signer::query()
            ->join('documents', 'documents.id', '=', 'signers.document_id')
            ->whereNotNull('documents.created_by')
            ->where(function ($query) {
                $query->whereNull('documents.status')
                    ->orWhere('documents.status', '!=', 'archived');
            })
            ->whereBetween('documents.created_at', [$from, $to])
            ->avg('signers.reminder_count');

        return round($avg ?? 0, 1);
    }

    /**
     * Get overdue count
     */
    public function getOverdueCount(): int
    {
        return 0; // due_at column removed
    }

    /**
     * Get signature trend data for charts
     *
     * @param  string  $interval  (day, week, month)
     * @return array{labels: array<int, string>, sent: array<int, int>, signed: array<int, int>}
     */
    public function getSignatureTrend($startDate, $endDate, $interval = 'day'): array
    {
        [$from, $to] = $this->dateBounds($startDate, $endDate);
        $periodExpr = $this->periodExpression('documents.created_at', $interval);

        $rows = $this->signatureDocuments()
            ->toBase()
            ->selectRaw("{$periodExpr} as period")
            ->selectRaw('COUNT(*) as sent')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END), 0) as signed")
            ->whereBetween('documents.created_at', [$from, $to])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'labels' => $rows->pluck('period')->map(fn ($period) => (string) $period)->all(),
            'sent' => $rows->pluck('sent')->map(fn ($count) => (int) $count)->all(),
            'signed' => $rows->pluck('signed')->map(fn ($count) => (int) $count)->all(),
        ];
    }

    /**
     * Get dashboard summary statistics
     *
     * @param  int|null  $userId  Filter by user
     * @return array{total_sent: int, signed: int, pending: int, overdue: int, completion_rate: float, median_time_hours: float}
     */
    public function getDashboardStats($userId = null): array
    {
        $query = $this->signatureDocuments();

        if ($userId) {
            $query->where('created_by', $userId);
        }

        $row = $query->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('sent', 'signed') THEN 1 ELSE 0 END), 0) as total_sent")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END), 0) as signed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as pending")
            ->first();

        $totalSent = (int) ($row->total_sent ?? 0);
        $signed = (int) ($row->signed ?? 0);
        $pending = (int) ($row->pending ?? 0);

        return [
            'total_sent' => $totalSent,
            'signed' => $signed,
            'pending' => $pending,
            'overdue' => 0,
            'completion_rate' => $totalSent > 0 ? round(($signed / $totalSent) * 100, 1) : 0,
            'median_time_hours' => $this->median($this->signedHours($userId)),
        ];
    }

    /**
     * Get user performance comparison
     *
     * @return Collection<int, array{user_id: int, name: string, email: string, total_sent: int, signed: int, pending: int, completion_rate: float, median_time_hours: float}>
     */
    public function getUserPerformance()
    {
        $counts = $this->signatureDocuments()
            ->toBase()
            ->select('created_by')
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('sent', 'signed') THEN 1 ELSE 0 END), 0) as total_sent")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'signed' THEN 1 ELSE 0 END), 0) as signed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as pending")
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        $hoursByOwner = $this->signedHoursByOwner();

        return Staff::query()
            ->select('id', 'first_name', 'last_name', 'email')
            ->get()
            ->map(function ($user) use ($counts, $hoursByOwner) {
                $row = $counts->get($user->id);
                $totalSent = (int) ($row->total_sent ?? 0);
                $signed = (int) ($row->signed ?? 0);
                $pending = (int) ($row->pending ?? 0);

                return [
                    'user_id' => $user->id,
                    'name' => $user->first_name.' '.$user->last_name,
                    'email' => $user->email,
                    'total_sent' => $totalSent,
                    'signed' => $signed,
                    'pending' => $pending,
                    'completion_rate' => $totalSent > 0 ? round(($signed / $totalSent) * 100, 1) : 0,
                    'median_time_hours' => $this->median($hoursByOwner->get($user->id, collect())),
                ];
            })
            ->sortByDesc('total_sent')
            ->values();
    }

    /**
     * Get activity by hour of day (for optimization)
     *
     * @return array<int, array{hour: int, created: int, signed: int}>
     */
    public function getActivityByHour(): array
    {
        $createdHourExpr = $this->hourExpression('documents.created_at');
        $created = $this->signatureDocuments()
            ->toBase()
            ->selectRaw("{$createdHourExpr} as hour")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->all();

        $signedHourExpr = $this->hourExpression('signers.signed_at');
        $signed = Signer::query()
            ->where('status', 'signed')
            ->whereNotNull('signed_at')
            ->toBase()
            ->selectRaw("{$signedHourExpr} as hour")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->all();

        $result = [];
        foreach (range(0, 23) as $hour) {
            $result[] = [
                'hour' => $hour,
                'created' => (int) ($created[$hour] ?? $created[(string) $hour] ?? 0),
                'signed' => (int) ($signed[$hour] ?? $signed[(string) $hour] ?? 0),
            ];
        }

        return $result;
    }

    protected function signatureDocuments(): Builder
    {
        return Document::query()->forSignatureWorkflow()->notArchived();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateBounds(string $startDate, string $endDate): array
    {
        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay(),
        ];
    }

    /**
     * @return Collection<int, float>
     */
    protected function signedHours($ownerId = null): Collection
    {
        $rows = $this->signedDurationRows();

        if ($ownerId) {
            $rows = $rows->where('created_by', $ownerId);
        }

        return $rows->map(fn ($row) => (float) $row->hours)->values();
    }

    /**
     * @return Collection<int|string, Collection<int, float>>
     */
    protected function signedHoursByOwner(): Collection
    {
        return $this->signedDurationRows()
            ->groupBy('created_by')
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => (float) $row->hours)->values());
    }

    /**
     * @return Collection<int, object>
     */
    protected function signedDurationRows(): Collection
    {
        if ($this->signedDurationRows !== null) {
            return $this->signedDurationRows;
        }

        $hoursExpr = $this->lastSignerHoursSql();

        $this->signedDurationRows = DB::table('documents')
            ->join('signers', function ($join) {
                $join->on('signers.document_id', '=', 'documents.id')
                    ->whereNotNull('signers.signed_at');
            })
            ->where('documents.status', 'signed')
            ->whereNotNull('documents.created_by')
            ->groupBy('documents.id', 'documents.created_at', 'documents.created_by')
            ->select('documents.created_by')
            ->selectRaw("{$hoursExpr} as hours")
            ->get();

        return $this->signedDurationRows;
    }

    /**
     * @param  Collection<int, float>  $values
     */
    protected function median(Collection $values): float
    {
        $sorted = $values->filter(fn ($value) => $value !== null)->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return 0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 0 && $count > 1) {
            return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
        }

        return round((float) $sorted[$middle], 2);
    }

    /**
     * @param  Collection<int, float>  $values
     */
    protected function average(Collection $values): float
    {
        if ($values->isEmpty()) {
            return 0;
        }

        return (float) $values->avg();
    }

    protected function lastSignerHoursSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(EPOCH FROM (MAX(signers.signed_at) - documents.created_at)) / 3600.0',
            'sqlite' => '(julianday(MAX(signers.signed_at)) - julianday(documents.created_at)) * 24.0',
            default => 'TIMESTAMPDIFF(SECOND, documents.created_at, MAX(signers.signed_at)) / 3600.0',
        };
    }

    protected function hoursBetweenSql(string $startColumn, string $endColumn): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(EPOCH FROM ({$endColumn} - {$startColumn})) / 3600.0",
            'sqlite' => "(julianday({$endColumn}) - julianday({$startColumn})) * 24.0",
            default => "TIMESTAMPDIFF(SECOND, {$startColumn}, {$endColumn}) / 3600.0",
        };
    }

    protected function hourExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "EXTRACT(HOUR FROM {$column})",
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            default => "HOUR({$column})",
        };
    }

    protected function periodExpression(string $column, string $interval): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $format = match ($interval) {
                'week' => 'IYYY-IW',
                'month' => 'YYYY-MM',
                default => 'YYYY-MM-DD',
            };

            return "TO_CHAR({$column}, '{$format}')";
        }

        if ($driver === 'sqlite') {
            return match ($interval) {
                'week' => "strftime('%Y-%W', {$column})",
                'month' => "strftime('%Y-%m', {$column})",
                default => "strftime('%Y-%m-%d', {$column})",
            };
        }

        return match ($interval) {
            'week' => "DATE_FORMAT({$column}, '%x-%v')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }
}
