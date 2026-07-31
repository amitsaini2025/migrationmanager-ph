<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientEoiReference;
use App\Models\Document;
use App\Models\VisaDocumentType;
use App\Models\ActivitiesLog;
use App\Services\PointsService;
use App\Services\EmailConfigService;
use App\Services\EoiClientConfirmationNotificationService;
use App\Support\StaffClientVisibility;
use App\Traits\ClientAuthorization;
use App\Mail\EoiConfirmationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EoiRoiSheetController extends Controller
{
    use ClientAuthorization;

    protected PointsService $pointsService;

    protected EmailConfigService $emailConfigService;

    public function __construct(PointsService $pointsService, EmailConfigService $emailConfigService)
    {
        $this->pointsService = $pointsService;
        $this->emailConfigService = $emailConfigService;
        $this->middleware('auth:admin')->except([
            'showConfirmationPage',
            'showAmendmentPage',
            'processClientConfirmation',
            'showSuccessPage'
        ]);
    }

    /**
     * Display the EOI/ROI Sheet - List view
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('eoi-roi')) {
            abort(403, 'Unauthorized');
        }

        $perPage = (int) $request->get('per_page', 50);
        $allowedPerPage = [10, 25, 50, 100, 200];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }

        // Build base query
        $query = $this->buildBaseQuery($request);

        // Apply filters
        $query = $this->applyFilters($query, $request);

        // Apply sorting
        $query = $this->applySorting($query, $request);

        // Paginate
        $rows = $query->paginate($perPage)->appends($request->except('page'));

        // Calculate partner points and warnings (for Comments column) for each row
        $warningsCache = [];
        $rows->getCollection()->transform(function ($row) use (&$warningsCache) {
            $subclass = $this->getFirstSubclassFromRow($row);
            $row->partner_points = $this->calculatePartnerPoints($row->client_id, $subclass);

            // Warnings (English expiry, age bracket, employment) for Comments column – cache by client + subclass
            $cacheKey = $row->client_id . '_' . ($subclass ?? '');
            if (!isset($warningsCache[$cacheKey])) {
                $warningsCache[$cacheKey] = $this->getWarningsTextForClient($row->client_id, $subclass);
            }
            $row->warnings_text = $warningsCache[$cacheKey];

            return $row;
        });

        // Count active filters
        $activeFilterCount = $this->countActiveFilters($request);

        return view('crm.clients.sheets.eoi-roi', compact('rows', 'perPage', 'activeFilterCount'));
    }

    /**
     * Display the EOI/ROI Sheet - Insights view
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function insights(Request $request)
    {
        // Restrict to admin and super admin only (roles 1, 12)
        if (!in_array(\Auth::user()->role ?? 0, [1, 12])) {
            return redirect()->back()->with('error', 'Only admin and super admin can view insights.');
        }

        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('eoi-roi')) {
            abort(403, 'Unauthorized');
        }

        // Build base query (without pagination)
        $baseQuery = $this->buildBaseQuery($request);
        
        // Apply same filters as list view
        $baseQuery = $this->applyFilters($baseQuery, $request);

        // Get all matching records for insights
        $allRecords = $baseQuery->get();

        // Calculate insights metrics
        $insights = $this->calculateInsights($allRecords);

        // Count active filters
        $activeFilterCount = $this->countActiveFilters($request);

        return view('crm.clients.sheets.eoi-roi-insights', compact('insights', 'activeFilterCount'));
    }

    /**
     * Build the base query for EOI/ROI sheet
     * Uses a standalone subquery for "latest EOI matter per client" so we never reference outer tables inside the subquery (PostgreSQL scope).
     *
     * @param Request $request
     * @return \Illuminate\Database\Query\Builder
     */
    protected function buildBaseQuery(Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $quote = $driver === 'mysql' ? '`' : '"';

        // Standalone subquery: one row per client_id = latest EOI matter
        if ($driver === 'mysql') {
            // MySQL: use subquery with MAX(id) + GROUP BY (DISTINCT ON is PostgreSQL-only)
            $latestEoiMatterSql = "
                SELECT cm.client_id, cm.client_unique_matter_no, cm.id AS matter_id,
                       cm.office_id, cm.deadline
                FROM client_matters cm
                INNER JOIN matters m ON m.id = cm.sel_matter_id
                INNER JOIN (
                    SELECT client_id, MAX(id) AS max_id FROM client_matters cm2
                    INNER JOIN matters m2 ON m2.id = cm2.sel_matter_id
                    WHERE cm2.matter_status = 1
                      AND (LOWER(COALESCE(m2.nick_name, '')) = 'eoi'
                           OR LOWER(COALESCE(m2.title, '')) LIKE '%eoi%'
                           OR LOWER(COALESCE(m2.title, '')) LIKE '%expression of interest%'
                           OR LOWER(COALESCE(m2.title, '')) LIKE '%expression%')
                    GROUP BY client_id
                ) latest ON latest.client_id = cm.client_id AND latest.max_id = cm.id
                WHERE cm.matter_status = 1
                  AND (LOWER(COALESCE(m.nick_name, '')) = 'eoi'
                       OR LOWER(COALESCE(m.title, '')) LIKE '%eoi%'
                       OR LOWER(COALESCE(m.title, '')) LIKE '%expression of interest%'
                       OR LOWER(COALESCE(m.title, '')) LIKE '%expression%')
            ";
        } else {
            // PostgreSQL: DISTINCT ON
            $latestEoiMatterSql = "
                SELECT DISTINCT ON (cm.client_id)
                    cm.client_id,
                    cm.client_unique_matter_no,
                    cm.id AS matter_id,
                    cm.office_id,
                    cm.deadline
                FROM client_matters cm
                INNER JOIN matters m ON m.id = cm.sel_matter_id
                WHERE cm.matter_status = 1
                  AND (
                      LOWER(COALESCE(m.nick_name, '')) = 'eoi'
                      OR LOWER(COALESCE(m.title, '')) LIKE '%eoi%'
                      OR LOWER(COALESCE(m.title, '')) LIKE '%expression of interest%'
                      OR LOWER(COALESCE(m.title, '')) LIKE '%expression%'
                  )
                ORDER BY cm.client_id, cm.id DESC
            ";
        }

        $hasIsPinned = Schema::hasColumn('client_eoi_references', 'is_pinned');
        $aliasQuote = ($driver === 'pgsql') ? '"' : (($driver === 'mysql') ? '`' : '');
        $selects = [
            'eoi.id as eoi_id',
            DB::raw("eoi.{$quote}EOI_number{$quote} as {$aliasQuote}EOI_number{$aliasQuote}"),
            DB::raw("eoi.{$quote}EOI_occupation{$quote} as {$aliasQuote}EOI_occupation{$aliasQuote}"),
            DB::raw("eoi.{$quote}EOI_point{$quote} as individual_points"),
            DB::raw("eoi.{$quote}EOI_submission_date{$quote} as {$aliasQuote}EOI_submission_date{$aliasQuote}"),
            DB::raw("eoi.{$quote}EOI_ROI{$quote} as {$aliasQuote}EOI_ROI{$aliasQuote}"),
            'eoi.eoi_status',
            'eoi.eoi_subclasses',
            'eoi.eoi_states',
            DB::raw("eoi.{$quote}EOI_state{$quote} as {$aliasQuote}EOI_state{$aliasQuote}"),
            'eoi.client_id',
            'admins.first_name',
            'admins.last_name',
            'admins.marital_status',
            'latest_eoi_matter.client_unique_matter_no as matter_id',
            'latest_eoi_matter.matter_id as matter_internal_id',
            'latest_eoi_matter.office_id',
            'latest_eoi_matter.deadline',
        ];
        if ($hasIsPinned) {
            array_unshift($selects, 'eoi.is_pinned');
        }
        if (Schema::hasColumn('client_eoi_references', 'sheet_comments')) {
            $selects[] = 'eoi.sheet_comments';
        }

        $query = DB::table('client_eoi_references as eoi')
            ->join('admins', 'eoi.client_id', '=', 'admins.id')
            ->join(DB::raw('(' . $latestEoiMatterSql . ') AS latest_eoi_matter'), 'latest_eoi_matter.client_id', '=', 'admins.id')
            ->select($selects)
            ->where('admins.is_archived', 0)
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->whereNotNull('latest_eoi_matter.matter_id');

        // Person Assisting role: restrict to clients where they are MA / PR / PA on any matter
        if ($paId = StaffClientVisibility::personAssistingStaffIdOrNull(\Illuminate\Support\Facades\Auth::user())) {
            $query->where(function ($q) use ($paId) {
                $q->whereExists(function ($sub) use ($paId) {
                    $sub->select(DB::raw('1'))
                        ->from('client_matters')
                        ->whereColumn('client_matters.client_id', 'admins.id')
                        ->where(function ($w) use ($paId) {
                            $w->where('client_matters.sel_migration_agent', $paId)
                                ->orWhere('client_matters.sel_person_responsible', $paId)
                                ->orWhere('client_matters.sel_person_assisting', $paId);
                        });
                })->orWhere('admins.user_id', $paId);
            });
        }

        return $query;
    }

    /**
     * Apply filters to the query
     * 
     * @param \Illuminate\Database\Query\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applyFilters($query, Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $eoiQuote = $driver === 'mysql' ? '`' : '"';

        // EOI Status filter
        if ($request->filled('eoi_status')) {
            $query->where('eoi.eoi_status', $request->input('eoi_status'));
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $fromDate = Carbon::createFromFormat('d/m/Y', $request->input('from_date'))->startOfDay();
            $query->whereRaw("eoi.{$eoiQuote}EOI_submission_date{$eoiQuote} >= ?", [$fromDate]);
        }

        if ($request->filled('to_date')) {
            $toDate = Carbon::createFromFormat('d/m/Y', $request->input('to_date'))->endOfDay();
            $query->whereRaw("eoi.{$eoiQuote}EOI_submission_date{$eoiQuote} <= ?", [$toDate]);
        }

        // Subclass filter (JSON array contains)
        if ($request->filled('subclass')) {
            $subclasses = is_array($request->input('subclass')) ? $request->input('subclass') : [$request->input('subclass')];
            $query->where(function ($q) use ($subclasses, $driver) {
                foreach ($subclasses as $subclass) {
                    if ($driver === 'mysql') {
                        $q->orWhereRaw('JSON_CONTAINS(eoi.eoi_subclasses, ?, "$")', [json_encode($subclass)]);
                    } else {
                        $q->orWhereRaw('eoi.eoi_subclasses::jsonb @> ?', [json_encode([$subclass])]);
                    }
                }
            });
        }

        // State filter (JSON array contains)
        if ($request->filled('state')) {
            $states = is_array($request->input('state')) ? $request->input('state') : [$request->input('state')];
            $query->where(function ($q) use ($states, $driver) {
                foreach ($states as $state) {
                    if ($driver === 'mysql') {
                        $q->orWhereRaw('JSON_CONTAINS(eoi.eoi_states, ?, "$")', [json_encode($state)]);
                    } else {
                        $q->orWhereRaw('eoi.eoi_states::jsonb @> ?', [json_encode([$state])]);
                    }
                }
            });
        }

        // Search filter (client name or EOI number)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $searchLower = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($searchLower, $eoiQuote) {
                $q->whereRaw('LOWER(admins.first_name) LIKE ?', [$searchLower])
                  ->orWhereRaw('LOWER(admins.last_name) LIKE ?', [$searchLower])
                  ->orWhereRaw("LOWER(eoi.{$eoiQuote}EOI_number{$eoiQuote}) LIKE ?", [$searchLower]);
            });
        }

        // Occupation filter (nominated occupation – partial match on EOI_occupation)
        if ($request->filled('occupation')) {
            $occupation = $request->input('occupation');
            $query->whereRaw("LOWER(eoi.{$eoiQuote}EOI_occupation{$eoiQuote}) LIKE ?", ['%' . strtolower($occupation) . '%']);
        }

        // Office filter
        if ($request->filled('office')) {
            $offices = is_array($request->input('office')) ? $request->input('office') : [$request->input('office')];
            $query->whereIn('latest_eoi_matter.office_id', $offices);
        }

        return $query;
    }

    /**
     * Apply sorting to the query
     * 
     * @param \Illuminate\Database\Query\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applySorting($query, Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $eoiQuote = $driver === 'mysql' ? '`' : '"';

        // First priority: pinned items (is_pinned DESC) - only when column exists
        if (Schema::hasColumn('client_eoi_references', 'is_pinned')) {
            $pinnedExpr = $driver === 'mysql'
                ? "CASE WHEN COALESCE(eoi.is_pinned, 0) = 1 THEN 1 ELSE 0 END"
                : "CASE WHEN COALESCE(eoi.is_pinned, false) = true THEN 1 ELSE 0 END";
            $query->orderByRaw("{$pinnedExpr} DESC");
        }

        // Secondary: nearest deadline first, nulls last
        if ($driver === 'mysql') {
            $query->orderByRaw('latest_eoi_matter.deadline IS NULL ASC, latest_eoi_matter.deadline ASC');
        } else {
            $query->orderByRaw('latest_eoi_matter.deadline ASC NULLS LAST');
        }

        $sortField = $request->get('sort', 'submission_date');
        $sortDirection = $request->get('direction', 'desc');

        // Validate direction
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        $dir = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        // Map sort fields to actual columns (use raw SQL for eoi mixed-case columns)
        $sortableFieldsRaw = [
            'eoi_number' => "eoi.{$eoiQuote}EOI_number{$eoiQuote}",
            'client_name' => 'admins.first_name',
            'occupation' => "eoi.{$eoiQuote}EOI_occupation{$eoiQuote}",
            'individual_points' => "eoi.{$eoiQuote}EOI_point{$eoiQuote}",
            'marital_status' => 'admins.marital_status',
            'eoi_status' => 'eoi.eoi_status',
            'submission_date' => "eoi.{$eoiQuote}EOI_submission_date{$eoiQuote}",
            'deadline' => 'latest_eoi_matter.deadline',
        ];

        $actualSortField = $sortableFieldsRaw[$sortField] ?? "eoi.{$eoiQuote}EOI_submission_date{$eoiQuote}";
        $query->orderByRaw($actualSortField . ' ' . $dir);

        return $query;
    }

    /**
     * Calculate partner points for a client (Single = 10, partner citizen/PR = 10, partner skills = 10, partner English = 5, else 0).
     * For subclass 189: skilled partner requires occupation on MLTSSL; no Competent English = 0.
     *
     * @param int $clientId
     * @param string|null $subclass EOI subclass (189, 190, 491) for 189 MLTSSL rule
     * @return int|null
     */
    protected function calculatePartnerPoints($clientId, ?string $subclass = null)
    {
        try {
            $client = Admin::find($clientId);
            if (!$client) {
                return null;
            }
            return $this->pointsService->getPartnerPoints($client, $subclass);
        } catch (\Exception $e) {
            Log::error('Error calculating partner points', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calculate insights metrics from records
     * 
     * @param \Illuminate\Support\Collection $records
     * @return array
     */
    protected function calculateInsights($records)
    {
        $insights = [
            'total_records' => $records->count(),
            'by_status' => [],
            'by_subclass' => [],
            'by_state' => [],
            'avg_individual_points' => 0,
            'recent_submissions_7d' => 0,
            'recent_submissions_30d' => 0,
            'submissions_by_month' => [],
        ];

        if ($records->isEmpty()) {
            return $insights;
        }

        // By status
        $insights['by_status'] = $records->groupBy('eoi_status')
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();

        // By subclass (unnest JSON arrays)
        $subclassCounts = [];
        foreach ($records as $record) {
            $subclasses = json_decode($record->eoi_subclasses, true) ?? [];
            foreach ($subclasses as $subclass) {
                $subclassCounts[$subclass] = ($subclassCounts[$subclass] ?? 0) + 1;
            }
        }
        $insights['by_subclass'] = $subclassCounts;

        // By state (unnest JSON arrays)
        $stateCounts = [];
        foreach ($records as $record) {
            $states = json_decode($record->eoi_states, true) ?? [];
            foreach ($states as $state) {
                $stateCounts[$state] = ($stateCounts[$state] ?? 0) + 1;
            }
        }
        $insights['by_state'] = $stateCounts;

        // Average individual points (cast to float in case DB returns strings)
        $pointsSum = $records->sum(function ($record) {
            $v = $record->individual_points ?? 0;
            return is_numeric($v) ? (float) $v : 0;
        });
        $pointsCount = $records->filter(function ($record) {
            return $record->individual_points !== null && $record->individual_points !== '' && is_numeric($record->individual_points);
        })->count();
        $insights['avg_individual_points'] = $pointsCount > 0 ? round($pointsSum / $pointsCount, 1) : 0;

        // Recent submissions
        $now = Carbon::now();
        $insights['recent_submissions_7d'] = $records->filter(function ($record) use ($now) {
            return $record->EOI_submission_date && Carbon::parse($record->EOI_submission_date)->greaterThanOrEqualTo($now->copy()->subDays(7));
        })->count();

        $insights['recent_submissions_30d'] = $records->filter(function ($record) use ($now) {
            return $record->EOI_submission_date && Carbon::parse($record->EOI_submission_date)->greaterThanOrEqualTo($now->copy()->subDays(30));
        })->count();

        // Submissions by month (last 6 months)
        $monthlySubmissions = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $monthKey = $month->format('Y-m');
            $monthLabel = $month->format('M Y');
            
            $count = $records->filter(function ($record) use ($month) {
                return $record->EOI_submission_date && 
                       Carbon::parse($record->EOI_submission_date)->format('Y-m') === $month->format('Y-m');
            })->count();
            
            $monthlySubmissions[$monthLabel] = $count;
        }
        $insights['submissions_by_month'] = $monthlySubmissions;

        return $insights;
    }

    /**
     * Count active filters
     * 
     * @param Request $request
     * @return int
     */
    protected function countActiveFilters(Request $request)
    {
        $filters = ['eoi_status', 'from_date', 'to_date', 'subclass', 'state', 'search', 'occupation', 'office'];
        $count = 0;
        
        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Get first subclass from EOI row (for points calculation context)
     */
    protected function getFirstSubclassFromRow($row): ?string
    {
        $raw = $row->eoi_subclasses ?? null;
        if ($raw === null) {
            return null;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }
        return $decoded[0];
    }

    /**
     * Get formatted warnings text for a client (English expiry, age bracket, employment) for Comments column
     */
    protected function getWarningsTextForClient(int $clientId, ?string $subclass): string
    {
        try {
            $client = Admin::with(['testScores', 'qualifications', 'experiences', 'partner', 'occupations'])
                ->find($clientId);
            if (!$client) {
                return '';
            }
            $result = $this->pointsService->compute($client, $subclass, 6);
            $warnings = $result['warnings'] ?? [];
            return $this->formatWarningsForDisplay($warnings);
        } catch (\Throwable $e) {
            Log::warning('EoiRoiSheet: failed to get warnings for client', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * Format warnings array as a single line for display in Comments column
     */
    protected function formatWarningsForDisplay(array $warnings): string
    {
        if (empty($warnings)) {
            return '';
        }
        $messages = array_map(function ($w) {
            return $w['message'] ?? '';
        }, $warnings);
        return implode(' | ', array_filter($messages));
    }

    /**
     * Staff verifies EOI details
     * 
     * @param Request $request
     * @param int $eoiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyByStaff(Request $request, $eoiId)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('eoi-roi')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $eoi = ClientEoiReference::findOrFail($eoiId);
            
            // Update verification fields
            $eoi->staff_verified = true;
            $eoi->confirmation_date = Carbon::now();
            $eoi->checked_by = auth()->guard('admin')->id();
            $eoi->save();

            // Log activity
            $this->logActivity(
                $eoi->client_id,
                'EOI Verified by Staff',
                'EOI details verified by ' . auth()->guard('admin')->user()->first_name . ' ' . auth()->guard('admin')->user()->last_name . 
                ' for EOI #' . $eoi->EOI_number,
                'eoi_verification'
            );

            return response()->json([
                'success' => true,
                'message' => 'EOI details verified successfully. You can now send confirmation email to the client.',
                'confirmation_date' => $eoi->confirmation_date->format('d/m/Y H:i'),
                'checked_by' => auth()->guard('admin')->user()->first_name . ' ' . auth()->guard('admin')->user()->last_name
            ]);

        } catch (\Exception $e) {
            Log::error('Error verifying EOI', [
                'eoi_id' => $eoiId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error verifying EOI details'], 500);
        }
    }

    /**
     * Send confirmation email to client
     * 
     * @param Request $request
     * @param int $eoiId
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendConfirmationEmail(Request $request, $eoiId)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('eoi-roi')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $eoi = ClientEoiReference::with('client')->findOrFail($eoiId);
            
            // Check if staff has verified first
            if (!$eoi->staff_verified) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Please verify the EOI details first before sending to client.'
                ], 400);
            }

            // Check if client exists and has email
            if (!$eoi->client || !$eoi->client->email) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Client email not found. Please update client email first.'
                ], 400);
            }

            // Generate unique token for confirmation
            $token = Str::random(64);
            $eoi->client_confirmation_token = $token;
            $eoi->confirmation_email_sent_at = Carbon::now();
            $eoi->client_confirmation_status = 'pending';
            $eoi->save();

            // Get EOI/Points/ROI visa documents to attach (per-EOI or shared)
            $attachmentsData = $this->getEoiRelatedAttachments($eoi->client, $eoi);
            $attachmentLabels = $attachmentsData['labels'];
            $attachments = $attachmentsData['attachments'];

            $eoiFromConfig = $this->emailConfigService->getEoiFromAccount();
            $mail = new EoiConfirmationMail(
                $eoi,
                $eoi->client,
                $token,
                $attachments,
                $attachmentLabels
            );

            if ($eoiFromConfig) {
                $mail->from(
                    $eoiFromConfig['from_address'],
                    $eoiFromConfig['from_name'] ?? config('mail.from.name')
                );
            }

            app(\App\Services\SystemEmailLogService::class)->logAndSendMailable([
                'category'  => 'eoi',
                'from_mail' => is_array($eoiFromConfig) ? ($eoiFromConfig['from_address'] ?? config('mail.from.address')) : config('mail.from.address'),
                'to_mail'   => $eoi->client->email,
                'subject'   => $mail->customSubject ?? ('Please Confirm Your EOI Details - ' . $eoi->EOI_number),
                'client_id' => $eoi->client_id,
                'user_id'   => auth('admin')->id(),
            ], $mail, $eoi->client->email);

            // Log activity
            $this->logActivity(
                $eoi->client_id,
                'EOI Confirmation Email Sent',
                'Confirmation email sent to ' . $eoi->client->email . ' for EOI #' . $eoi->EOI_number,
                'email'
            );

            return response()->json([
                'success' => true,
                'message' => 'Confirmation email sent successfully to ' . $eoi->client->email,
                'sent_at' => $eoi->confirmation_email_sent_at->format('d/m/Y H:i')
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending confirmation email', [
                'eoi_id' => $eoiId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Error sending confirmation email'], 500);
        }
    }

    /**
     * Get visa documents from EOI Summary, Points Summary, ROI Draft categories for email attachment.
     * Prefers per-EOI categories (e.g. "EOI Summary - E0121253652") when present; otherwise shared.
     *
     * @return array{attachments: array<int, array{data: string, name: string, mime: string}>, labels: array<int, string>}
     */
    protected function getEoiRelatedAttachments(Admin $client, ClientEoiReference $eoiReference): array
    {
        $eoiNumber = $eoiReference->EOI_number ?? '';
        $bases = ['EOI Summary', 'Points Summary', 'ROI Draft'];

        $categoryIds = [];
        foreach ($bases as $base) {
            $perEoiTitle = $eoiNumber ? ($base . ' - ' . $eoiNumber) : null;
            $perEoi = $perEoiTitle ? VisaDocumentType::where('status', 1)->where('title', $perEoiTitle)
                ->where(function ($q) use ($client) {
                    $q->whereNull('client_id')->orWhere('client_id', $client->id);
                })->first() : null;
            $shared = VisaDocumentType::where('status', 1)->where('title', $base)
                ->where(function ($q) use ($client) {
                    $q->whereNull('client_id')->orWhere('client_id', $client->id);
                })->first();
            $chosen = $perEoi ?? $shared;
            if ($chosen) {
                $categoryIds[] = $chosen->id;
            }
        }

        if (empty($categoryIds)) {
            return ['attachments' => [], 'labels' => []];
        }

        $categories = VisaDocumentType::whereIn('id', $categoryIds)->pluck('title', 'id')->toArray();
        $documents = Document::where('client_id', $client->id)
            ->where('doc_type', 'visa')
            ->whereIn('folder_name', $categoryIds)
            ->whereNull('not_used_doc')
            ->where('type', 'client')
            ->whereNotNull('myfile')
            ->orderBy('folder_name')
            ->orderBy('created_at')
            ->get();

        $attachments = [];
        $labelsUsed = [];

        foreach ($documents as $doc) {
            $categoryTitle = $categories[$doc->folder_name] ?? 'Document';
            if (!in_array($categoryTitle, $labelsUsed, true)) {
                $labelsUsed[] = $categoryTitle;
            }

            $s3Key = null;
            if (!empty($doc->myfile) && (str_starts_with($doc->myfile, 'http'))) {
                $path = parse_url($doc->myfile, PHP_URL_PATH);
                if ($path) {
                    $s3Key = ltrim(urldecode($path), '/');
                }
            }
            if (empty($s3Key) && !empty($doc->myfile_key)) {
                $s3Key = $client->id . '/visa/' . $doc->myfile_key;
            }
            if (empty($s3Key)) {
                continue;
            }

            try {
                if (!Storage::disk('s3')->exists($s3Key)) {
                    Log::warning('EOI attachment: S3 file not found', ['key' => $s3Key, 'doc_id' => $doc->id]);
                    continue;
                }
                $data = Storage::disk('s3')->get($s3Key);
            } catch (\Throwable $e) {
                Log::warning('EOI attachment: failed to get S3 file', ['key' => $s3Key, 'error' => $e->getMessage()]);
                continue;
            }

            $ext = strtolower($doc->filetype ?? pathinfo($doc->myfile_key ?? '', PATHINFO_EXTENSION) ?: 'pdf');
            $mimeMap = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
            ];
            $mime = $mimeMap[$ext] ?? 'application/octet-stream';

            $fileName = $doc->file_name ?: ('document_' . $doc->id);
            $displayName = $categoryTitle . ' - ' . $fileName . (str_contains($fileName, '.') ? '' : '.' . $ext);
            $attachments[] = [
                'data' => $data,
                'name' => $displayName,
                'mime' => $mime,
            ];
        }

        return ['attachments' => $attachments, 'labels' => $labelsUsed];
    }

    /**
     * Client confirms EOI details (public route)
     * 
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function showConfirmationPage($token)
    {
        $eoi = ClientEoiReference::with('client')->where('client_confirmation_token', $token)->firstOrFail();
        
        return view('crm.clients.sheets.eoi-client-confirmation', [
            'eoi' => $eoi,
            'token' => $token,
            'action' => 'confirm'
        ]);
    }

    /**
     * Client requests amendment (public route)
     * 
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function showAmendmentPage($token)
    {
        $eoi = ClientEoiReference::with('client')->where('client_confirmation_token', $token)->firstOrFail();
        
        return view('crm.clients.sheets.eoi-client-confirmation', [
            'eoi' => $eoi,
            'token' => $token,
            'action' => 'amend'
        ]);
    }

    /**
     * Process client confirmation
     * 
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processClientConfirmation(Request $request, $token)
    {
        try {
            $eoi = ClientEoiReference::with('client')->where('client_confirmation_token', $token)->firstOrFail();

            // Reject replay / status flip; keep token so success page and staff
            // resolve-amendment → pending (same link) continue to work.
            if (in_array($eoi->client_confirmation_status, ['confirmed', 'amendment_requested'], true)) {
                return redirect()->route('client.eoi.success', ['token' => $token])
                    ->with('success', 'This confirmation link has already been used.');
            }
            
            $action = $request->input('action');
            
            if ($action === 'confirm') {
                // Client confirms details
                $eoi->client_confirmation_status = 'confirmed';
                $eoi->client_last_confirmation = Carbon::now();
                $eoi->save();

                // Log activity
                $this->logActivity(
                    $eoi->client_id,
                    'EOI Details Confirmed by Client',
                    'Client confirmed EOI details for EOI #' . $eoi->EOI_number,
                    'eoi_confirmation'
                );

                EoiClientConfirmationNotificationService::notifyStaff($eoi, 'confirmation');

                return redirect()->route('client.eoi.success', ['token' => $token])
                    ->with('success', 'Thank you! Your EOI details have been confirmed.');

            } elseif ($action === 'amend') {
                // Client requests amendments
                $request->validate([
                    'notes' => 'required|string|max:1000'
                ]);

                $eoi->client_confirmation_status = 'amendment_requested';
                $eoi->client_confirmation_notes = $request->input('notes');
                $eoi->client_last_confirmation = Carbon::now();
                $eoi->save();

                // Log activity
                $this->logActivity(
                    $eoi->client_id,
                    'EOI Amendment Requested by Client',
                    'Client requested amendments for EOI #' . $eoi->EOI_number . '. Notes: ' . $request->input('notes'),
                    'eoi_amendment'
                );

                EoiClientConfirmationNotificationService::notifyStaff(
                    $eoi,
                    'amendment',
                    $request->input('notes')
                );

                return redirect()->route('client.eoi.success', ['token' => $token])
                    ->with('success', 'Thank you! Your amendment request has been submitted.');
            }

            return redirect()->back()->with('error', 'Invalid action');

        } catch (\Exception $e) {
            Log::error('Error processing client confirmation', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * Show success page after client confirmation
     * 
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function showSuccessPage($token)
    {
        $eoi = ClientEoiReference::with('client')->where('client_confirmation_token', $token)->firstOrFail();
        
        return view('crm.clients.sheets.eoi-confirmation-success', ['eoi' => $eoi]);
    }

    /**
     * Log activity to activities_logs table
     * 
     * @param int $clientId
     * @param string $subject
     * @param string $description
     * @param string $activityType
     * @return void
     */
    protected function logActivity($clientId, $subject, $description, $activityType = 'note')
    {
        try {
            ActivitiesLog::create([
                'client_id' => $clientId,
                'created_by' => auth()->guard('admin')->check() ? auth()->guard('admin')->id() : null,
                'subject' => $subject,
                'description' => $description,
                'activity_type' => $activityType,
                'task_status' => 0, // Required NOT NULL field - 0 for non-task activities
                'pin' => 0, // Required NOT NULL field
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging activity', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Toggle pin status for an EOI record in the sheet.
     */
    public function togglePin(Request $request, $eoiId)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('eoi-roi')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $eoiId = (int) $eoiId;
        if (!$eoiId) {
            return response()->json(['success' => false, 'message' => 'Missing EOI ID'], 400);
        }

        try {
            $eoi = DB::table('client_eoi_references')->where('id', $eoiId)->first();
            if (!$eoi) {
                return response()->json(['success' => false, 'message' => 'EOI record not found'], 404);
            }

            $newPinStatus = !($eoi->is_pinned ?? false);
            DB::table('client_eoi_references')
                ->where('id', $eoiId)
                ->update([
                    'is_pinned' => $newPinStatus,
                    'updated_by' => auth()->guard('admin')->id(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'is_pinned' => $newPinStatus,
                'message' => $newPinStatus ? 'Item pinned to top' : 'Item unpinned'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating pin status: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save staff sheet comment for an EOI record (separate from auto-generated warnings).
     */
    public function updateComment(Request $request, $eoiId)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('eoi-roi')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (! Schema::hasColumn('client_eoi_references', 'sheet_comments')) {
            return response()->json(['success' => false, 'message' => 'Sheet comments are not available. Run migrations.'], 500);
        }

        $eoiId = (int) $eoiId;
        if ($eoiId <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing EOI ID'], 400);
        }

        $comment = $request->input('comment');
        if (is_string($comment)) {
            $comment = trim($comment);
            if ($comment === '') {
                $comment = null;
            }
        } else {
            $comment = null;
        }

        if ($comment !== null && mb_strlen($comment) > 5000) {
            return response()->json(['success' => false, 'message' => 'Comment must be 5000 characters or fewer'], 422);
        }

        try {
            $eoi = DB::table('client_eoi_references')->where('id', $eoiId)->first();
            if (! $eoi) {
                return response()->json(['success' => false, 'message' => 'EOI record not found'], 404);
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $eoi->client_id, auth()->guard('admin')->user())) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            DB::table('client_eoi_references')
                ->where('id', $eoiId)
                ->update([
                    'sheet_comments' => $comment,
                    'updated_by' => auth()->guard('admin')->id(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment saved',
                'comment' => $comment ?? '',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Could not save comment'], 500);
        }
    }
}
