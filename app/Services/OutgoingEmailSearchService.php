<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutgoingEmailSearchService
{
    /**
     * Base query scoped to CRM-logged outgoing emails only.
     * Excludes inbox-synced rows; includes CRM-sent and fetched-sent.
     */
    public function baseQuery(): Builder
    {
        return EmailLog::query()
            ->where('mail_type', 1)
            ->forCrmSentMailbox();
    }

    /**
     * Apply search/filter parameters from a GET request onto the base query.
     */
    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $pattern = '%' . mb_strtolower(trim($request->input('search')), 'UTF-8') . '%';
            $query->where(function (Builder $q) use ($pattern) {
                $q->whereRaw('LOWER(subject) LIKE ?', [$pattern])
                  ->orWhereRaw('LOWER(from_mail) LIKE ?', [$pattern])
                  ->orWhereRaw('LOWER(to_mail) LIKE ?', [$pattern]);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('sender_id')) {
            $query->where('user_id', (int) $request->input('sender_id'));
        }

        if ($request->filled('from_address')) {
            $query->whereRaw('LOWER(from_mail) LIKE ?', [
                '%' . mb_strtolower(trim($request->input('from_address')), 'UTF-8') . '%',
            ]);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->input('client_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('has_attachments')) {
            if ($request->input('has_attachments') === '1') {
                $query->has('attachments');
            } elseif ($request->input('has_attachments') === '0') {
                $query->doesntHave('attachments');
            }
        }

        return $query;
    }

    /**
     * Aggregate stats for the dashboard cards.
     */
    public function getDashboardStats(): array
    {
        $base = $this->baseQuery();

        $today      = now()->startOfDay();
        $weekStart  = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $totalToday = (clone $base)
            ->where('created_at', '>=', $today)->count();

        $totalWeek = (clone $base)
            ->where('created_at', '>=', $weekStart)->count();

        $totalMonth = (clone $base)
            ->where('created_at', '>=', $monthStart)->count();

        $withAttachments = (clone $base)->has('attachments')->count();

        // Top 5 senders this month
        $topSenders = (clone $base)
            ->where('created_at', '>=', $monthStart)
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('COUNT(*) as send_count'))
            ->groupBy('user_id')
            ->orderByDesc('send_count')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $staff = Staff::select('id', 'first_name', 'last_name')->find($row->user_id);
                return [
                    'name'       => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown',
                    'send_count' => $row->send_count,
                ];
            });

        return compact('totalToday', 'totalWeek', 'totalMonth', 'withAttachments', 'topSenders');
    }

    /**
     * Resolve the S3 preview URL for an email log (mirrors ClientsController::filterSentEmails logic).
     * Returns null when no archive exists.
     */
    public function resolveS3PreviewUrl(EmailLog $email): ?string
    {
        if (empty($email->uploaded_doc_id)) {
            return null;
        }

        $docInfo = Document::select('id', 'doc_type', 'myfile', 'myfile_key', 'mail_type')
            ->where('id', $email->uploaded_doc_id)
            ->first();

        if (!$docInfo) {
            return null;
        }

        // If the document already has a full URL stored (myfile_key present → S3 pre-signed or public URL)
        if (!empty($docInfo->myfile_key)) {
            return $docInfo->myfile ?: null;
        }

        // Build the legacy-style S3 URL
        $bucket = env('AWS_BUCKET');
        $region = env('AWS_DEFAULT_REGION');
        if (!$bucket || !$region) {
            return null;
        }

        $adminInfo   = Admin::select('client_id')->where('id', $email->client_id)->first();
        $clientRef   = ($adminInfo && $adminInfo->client_id)
            ? $adminInfo->client_id
            : 'client_' . ($email->client_id ?? 0);

        $baseUrl = 'https://' . $bucket . '.s3.' . $region . '.amazonaws.com/';

        return $baseUrl
            . $clientRef . '/'
            . ($docInfo->doc_type ?? 'mail') . '/'
            . ($docInfo->mail_type ?? 'sent') . '/'
            . ($docInfo->myfile ?? '');
    }
}
