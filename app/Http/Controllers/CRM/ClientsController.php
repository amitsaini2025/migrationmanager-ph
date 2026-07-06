<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\Admin;
use App\Models\Staff;
use App\Models\Company;
use App\Models\Lead;
use App\Models\ActivitiesLog;
// use App\Models\OnlineForm; // REMOVED: OnlineForm model has been deleted
use Illuminate\Support\Facades\Auth;
use App\Helpers\PhoneHelper;
use App\Helpers\IconHelper;
use Barryvdh\DomPDF\Facade as PDF;
use App\Models\CheckinLog;
use App\Models\Note;
use App\Models\BookingAppointment;
// clientServiceTaken model removed - table client_service_takens does not exist
use App\Models\AccountClientReceipt;

use App\Models\Matter;
use App\Models\ClientMatter;
use App\Models\Branch;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use App\Services\ClientReferenceService;
use App\Support\NoteDescriptionHtml;
use App\Support\StaffClientVisibility;

use DateTime;
use DateTimeZone;

use App\Models\ClientAddress; // Import the ClientAddress model
use App\Models\ClientContact; // Import the ClientAddress model
use App\Models\ClientEmail; // Import the ClientAddress model
use App\Models\ClientQualification; // Import the ClientAddress model
use App\Models\ClientExperience; // Import the ClientAddress model
use App\Models\ClientTestScore; // Import the ClientAddress model
use App\Models\ClientVisaCountry; // Import the ClientAddress model
use App\Models\ClientOccupation; // Import the ClientAddress model
use App\Models\ClientSpouseDetail; // Import the ClientAddress model
use App\Models\AppointmentConsultant; // Import the AppointmentConsultant model
use App\Support\BansalSchedulingServiceType;

use App\Models\EmailRecord;
use App\Models\ClientPoint;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;

use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientRelationship;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

use App\Models\Form956;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CostAssignmentForm;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use App\Models\ClientEoiReference;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Mail\HubdocInvoiceMail;
use App\Services\MatterEmailBodyCleanupService;
use App\Services\Sms\UnifiedSmsManager;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\ClientExportService;
use App\Services\FCMService;
use App\Services\ClientImportService;
use App\Services\JobReadyAgreementFeeTablePatcher;
use App\Services\PsaAgreementSection4SummaryTablePatcher;
use App\Services\PsaAgreementServiceTypeRowPatcher;
use App\Services\VisaAgreementAmountTablePatcher;
use App\Services\VisaAgreementServiceTypeRowPatcher;
use App\Services\CompanyAgreementDocxPatcher;
use App\Services\CompanyVisaAgreementMacroBuilder;
use App\Services\VisaAgreementApplicantAddressResolver;
use App\Services\VisaAgreementTemplateResolver;
use App\Traits\ClientAuthorization;
use App\Traits\ClientHelpers;
use App\Traits\ClientQueries;
use App\Traits\LogsClientActivity;

class ClientsController extends Controller
{
    use ClientAuthorization, ClientHelpers, ClientQueries, LogsClientActivity;
    
    protected $openAiClient;
    protected $smsManager;

    /** @var bool|null Cached for the current request only */
    protected $googleReviewCrmTemplateExistsCache = null;

    private const GLOBAL_SEARCH_BRANCH_LIMIT = 45;

    private const GLOBAL_SEARCH_OUTPUT_CAP = 80;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(UnifiedSmsManager $smsManager)
    {
        $this->middleware('auth:admin');
        $this->smsManager = $smsManager;

        $this->openAiClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Default "not picked call" SMS body from sms_templates (alias not_picked_call), with client name filled in.
     */
    protected function notPickedCallSmsDefaultForClient(Admin $client): string
    {
        $rawFirst = trim((string) ($client->first_name ?? ''));
        $first = $rawFirst !== ''
            ? mb_convert_case(mb_strtolower($rawFirst), MB_CASE_TITLE, 'UTF-8')
            : 'Client';

        $rendered = $this->smsManager->renderTemplateByAlias('not_picked_call', [
            'first_name' => $first,
            'office_phone' => '0396021330',
        ]);

        if ($rendered !== null) {
            return $rendered;
        }

        return "Hi {$first},\n\nWe tried reaching you but couldn't connect. Please call us at 0396021330 or let us know a suitable time.\n\nPlease do not reply via SMS.\n\nBansal Immigration";
    }

	/**
     * All Vendors.
     *
     * @return \Illuminate\Http\Response
    */
    public function index(Request $request)
	{
		// Check authorization using trait
		if ($this->hasModuleAccess('20')) {
		    $query = $this->getBaseClientQuery();
            $totalData = $query->count();
            
            // Apply filters using trait
            $query = $this->applyClientFilters($query, $request);

            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            
            $lists = $query->sortable(['id' => 'desc'])
                ->paginate($perPage)
                ->appends($request->except('page'));
		} else {
		    $query = $this->getEmptyClientQuery();
            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
		    $lists = $query->sortable(['id' => 'desc'])->paginate($perPage);
		    $totalData = 0;
		}
		
		return view('crm.clients.index', compact(['lists', 'totalData', 'perPage']));
    }

    public function clientsmatterslist(Request $request)
    {
        // Check authorization using trait
        $teamMembers = collect();
        if ($this->hasModuleAccess('20')) {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
            ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
            ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
            ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
            ->select('cm.*', 'ad.client_id as client_unique_id','ad.first_name','ad.last_name','ad.email','ma.title','ma.nick_name','ad.dob')
            ->where('cm.matter_status', '=', '1')
            ->where('ad.is_archived', '=', '0')
            ->whereIn('ad.type', ['client', 'lead'])
            ->whereNull('ad.is_deleted')
            ->where(function ($q) {
                $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];
                $q->whereNull('ws.name')
                    ->orWhereRaw('LOWER(TRIM(ws.name)) NOT IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')', $closedStages);
            });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            StaffClientVisibility::restrictMatterListToAllocatedClients($query, 'cm', 'ad');

            if ($request->has('sel_matter_id')) {
                $sel_matter_id = $request->input('sel_matter_id');
                if(trim($sel_matter_id) != '') {
                    $query->where('cm.sel_matter_id', '=', $sel_matter_id);
                }
            }

            if ($request->has('client_id')) {
                $client_id = $request->input('client_id');
                if(trim($client_id) != '') {
                    $query->where('ad.client_id', '=', $client_id);
                }
            }

            if ($request->has('name')) {
                $name = trim($request->input('name'));
                if ($name != '') {
                    $nameLower = strtolower($name);
                    $query->where(function ($q) use ($nameLower) {
                        $q->whereRaw('LOWER(ad.first_name) LIKE ?', ['%' . $nameLower . '%'])
                          ->orWhereRaw('LOWER(ad.last_name) LIKE ?', ['%' . $nameLower . '%'])
                          ->orWhereRaw("LOWER(COALESCE(ad.first_name, '') || ' ' || COALESCE(ad.last_name, '')) LIKE ?", ['%' . $nameLower . '%']);
                    });
                }
            }

            if ($request->filled('sel_migration_agent')) {
                $query->where('cm.sel_migration_agent', '=', $request->input('sel_migration_agent'));
            }

            if ($request->filled('sel_person_responsible')) {
                $query->where('cm.sel_person_responsible', '=', $request->input('sel_person_responsible'));
            }

            if ($request->filled('sel_person_assisting')) {
                $query->where('cm.sel_person_assisting', '=', $request->input('sel_person_assisting'));
            }

            if (
                $request->filled('quick_date_range') ||
                $request->filled('from_date') ||
                $request->filled('to_date')
            ) {
                [$startDate, $endDate] = $this->resolveClientDateRange($request);
                $dateField = $request->input('date_filter_field', 'created_at') === 'updated_at'
                    ? 'cm.updated_at'
                    : 'cm.created_at';

                if ($startDate && $endDate) {
                    $query->whereBetween($dateField, [$startDate, $endDate]);
                }
            }

            // Count AFTER all filters are applied, BEFORE orderBy
            $totalData = $query->count();

            // Apply orderBy AFTER count for pagination
            $query->orderBy($sortField, $sortDirection);

            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }

            $teamMembers = \App\Models\Staff::query()
                ->orderBy('first_name', 'asc')
                ->select('id', 'first_name', 'last_name')
                ->get();

            $lists = $query->paginate($perPage)->appends($request->except('page'));
        } else {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
            ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
            ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
            ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
            ->select('cm.*', 'ad.client_id as client_unique_id','ad.first_name','ad.last_name','ad.email','ma.title','ma.nick_name','ad.dob')
            ->where('cm.matter_status', '=', '1')
            ->where('ad.is_archived', '=', '0')
            ->whereIn('ad.type', ['client', 'lead'])
            ->whereNull('ad.is_deleted')
            ->where(function ($q) {
                $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];
                $q->whereNull('ws.name')
                    ->orWhereRaw('LOWER(TRIM(ws.name)) NOT IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')', $closedStages);
            });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            $query->orderBy($sortField, $sortDirection);
            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            $totalData = 0;
            $lists = $query->paginate($perPage);
        }
        //dd( $lists);
        return view('crm.clients.clientsmatterslist', compact(['lists', 'totalData', 'teamMembers', 'perPage']));
    }

    /**
     * Display closed matters (matter_status=0 or workflow stages: File Closed, Withdrawn, Refund, Discontinued).
     * Mirrors clientsmatterslist but filters for archived/closed matters.
     */
    public function closedmatterslist(Request $request)
    {
        $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];

        $teamMembers = collect();
        if ($this->hasModuleAccess('20')) {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
                ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
                ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
                ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
                ->select('cm.*', 'ad.client_id as client_unique_id', 'ad.first_name', 'ad.last_name', 'ad.email', 'ma.title', 'ma.nick_name', 'ad.dob', 'ws.name as workflow_stage_name')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) use ($closedStages) {
                    $q->where('cm.matter_status', '=', '0')
                        ->orWhereRaw('LOWER(TRIM(ws.name)) IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')', $closedStages);
                });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            StaffClientVisibility::restrictMatterListToAllocatedClients($query, 'cm', 'ad');

            if ($request->has('sel_matter_id')) {
                $sel_matter_id = $request->input('sel_matter_id');
                if (trim($sel_matter_id) != '') {
                    $query->where('cm.sel_matter_id', '=', $sel_matter_id);
                }
            }

            if ($request->has('client_id')) {
                $client_id = $request->input('client_id');
                if (trim($client_id) != '') {
                    $query->where('ad.client_id', '=', $client_id);
                }
            }

            if ($request->has('name')) {
                $name = trim($request->input('name'));
                if ($name != '') {
                    $nameLower = strtolower($name);
                    $query->where(function ($q) use ($nameLower) {
                        $q->whereRaw('LOWER(ad.first_name) LIKE ?', ['%' . $nameLower . '%'])
                            ->orWhereRaw('LOWER(ad.last_name) LIKE ?', ['%' . $nameLower . '%'])
                            ->orWhereRaw("LOWER(COALESCE(ad.first_name, '') || ' ' || COALESCE(ad.last_name, '')) LIKE ?", ['%' . $nameLower . '%']);
                    });
                }
            }

            if ($request->filled('sel_migration_agent')) {
                $query->where('cm.sel_migration_agent', '=', $request->input('sel_migration_agent'));
            }

            if ($request->filled('sel_person_responsible')) {
                $query->where('cm.sel_person_responsible', '=', $request->input('sel_person_responsible'));
            }

            if ($request->filled('sel_person_assisting')) {
                $query->where('cm.sel_person_assisting', '=', $request->input('sel_person_assisting'));
            }

            if (
                $request->filled('quick_date_range') ||
                $request->filled('from_date') ||
                $request->filled('to_date')
            ) {
                [$startDate, $endDate] = $this->resolveClientDateRange($request);
                $dateField = $request->input('date_filter_field', 'created_at') === 'updated_at'
                    ? 'cm.updated_at'
                    : 'cm.created_at';

                if ($startDate && $endDate) {
                    $query->whereBetween($dateField, [$startDate, $endDate]);
                }
            }

            $totalData = $query->count();
            $query->orderBy($sortField, $sortDirection);

            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }

            $teamMembers = \App\Models\Staff::query()
                ->orderBy('first_name', 'asc')
                ->select('id', 'first_name', 'last_name')
                ->get();

            $lists = $query->paginate($perPage)->appends($request->except('page'));
        } else {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
                ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
                ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
                ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
                ->select('cm.*', 'ad.client_id as client_unique_id', 'ad.first_name', 'ad.last_name', 'ad.email', 'ma.title', 'ma.nick_name', 'ad.dob', 'ws.name as workflow_stage_name')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) use ($closedStages) {
                    $q->where('cm.matter_status', '=', '0')
                        ->orWhereRaw('LOWER(TRIM(ws.name)) IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')', $closedStages);
                });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            $query->orderBy($sortField, $sortDirection);
            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (!in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            $totalData = 0;
            $lists = $query->paginate($perPage);
        }

        return view('crm.clients.closedmatterslist', compact(['lists', 'totalData', 'teamMembers', 'perPage']));
    }

    public function insights(Request $request)
    {
        // Restrict to admin and super admin only (roles 1, 12)
        if (!in_array(Auth::user()->role ?? 0, [1, 12])) {
            return redirect()->back()->with('error', 'Only admin and super admin can view insights.');
        }

        $section = $request->input('section', 'clients');
        $now = Carbon::now();

        // Client metrics
        $clientBaseQuery = $this->getBaseClientQuery();
        $clientStats = [
            'total' => (clone $clientBaseQuery)->count(),
            'new30' => (clone $clientBaseQuery)->where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'inactive' => (clone $clientBaseQuery)->where('status', 0)->count(),
            'archived' => Admin::where('is_archived', 1)
                ->where('type', 'client')
                ->whereNull('is_deleted')
                ->count(),
        ];

        $clientStatusBreakdown = (clone $clientBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(function ($row) {
                $row->label = ((int) $row->status === 1) ? 'Active' : 'Inactive';
                return $row;
            });

        $clientMonthlyGrowth = (clone $clientBaseQuery)
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as sort_key"),
                DB::raw("TO_CHAR(created_at, 'Mon YYYY') as label"),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('sort_key', 'label')
            ->orderBy('sort_key')
            ->get();

        $recentClients = (clone $clientBaseQuery)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'client_id', 'created_at', 'status']);

        // Matter metrics
        $matterBase = DB::table('client_matters as cm')->where('cm.matter_status', 1);
        $matterStats = [
            'total' => (clone $matterBase)->count(),
            'new30' => (clone $matterBase)->where('cm.created_at', '>=', $now->copy()->subDays(30))->count(),
            'assigned' => (clone $matterBase)->whereNotNull('cm.sel_migration_agent')->count(),
        ];

        $mattersByAgent = DB::table('client_matters as cm')
            ->leftJoin('staff as agent', 'agent.id', '=', 'cm.sel_migration_agent')
            ->select(
                DB::raw("COALESCE(agent.first_name || ' ' || agent.last_name, 'Unassigned') as agent_name"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('agent_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $recentMatters = DB::table('client_matters as cm')
            ->join('admins as client', 'client.id', '=', 'cm.client_id')
            ->leftJoin('staff as agent', 'agent.id', '=', 'cm.sel_migration_agent')
            ->select(
                'cm.client_unique_matter_no',
                'cm.created_at',
                'client.first_name as client_first_name',
                'client.last_name as client_last_name',
                'agent.first_name as agent_first_name',
                'agent.last_name as agent_last_name'
            )
            ->orderByDesc('cm.created_at')
            ->limit(5)
            ->get();

        // Lead metrics
        $leadBase = Lead::query();
        $leadStats = [
            'total' => (clone $leadBase)->count(),
            'new30' => (clone $leadBase)->where('created_at', '>=', $now->copy()->subDays(30))->count(),
        ];

        $leadsByStatus = (clone $leadBase)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $leadMonthlyGrowth = (clone $leadBase)
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as sort_key"),
                DB::raw("TO_CHAR(created_at, 'Mon YYYY') as label"),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('sort_key', 'label')
            ->orderBy('sort_key')
            ->get();

        $recentLeads = (clone $leadBase)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['first_name', 'last_name', 'status', 'created_at']);

        return view('crm.clients.insights', [
            'section' => $section,
            'clientStats' => $clientStats,
            'clientStatusBreakdown' => $clientStatusBreakdown,
            'clientMonthlyGrowth' => $clientMonthlyGrowth,
            'recentClients' => $recentClients,
            'matterStats' => $matterStats,
            'mattersByAgent' => $mattersByAgent,
            'recentMatters' => $recentMatters,
            'leadStats' => $leadStats,
            'leadsByStatus' => $leadsByStatus,
            'leadsByQuality' => collect(), // lead_quality column removed
            'leadMonthlyGrowth' => $leadMonthlyGrowth,
            'recentLeads' => $recentLeads,
        ]);
    }

    public function clientsemaillist(Request $request)
    {
        // Check authorization using trait
        if ($this->hasModuleAccess('20')) {
            $sortField = $request->get('sort', 'id');
            $sortDirection = $request->get('direction', 'desc');

            $query = Admin::where('is_archived', '=', '0')
                ->where('type', '=', 'client')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('is_deleted');
            StaffClientVisibility::restrictAdminEloquentQuery($query);
            $query->orderBy($sortField, $sortDirection);

            $totalData = $query->count();

            if ($request->has('client_id')) {
                $client_id = $request->input('client_id');
                if(trim($client_id) != '') {
                    $query->where('client_id', '=', $client_id);
                }
            }

            if ($request->has('name')) {
                $name = trim($request->input('name'));
                if ($name != '') {
                    $nameLower = strtolower($name);
                    $query->where(function ($q) use ($nameLower) {
                        $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $nameLower . '%'])
                          ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $nameLower . '%'])
                          ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%' . $nameLower . '%']);
                    });
                }
            }

            if ($request->has('email')) {
                $email = $request->input('email');
                if(trim($email) != '') {
                    $query->where('email', 'LIKE', '%' . $email . '%');
                }
            }

            $lists = $query->paginate(20);
        } else {
            $query = Admin::where('id', '=', '')->whereIn('type', ['client', 'lead'])->whereNull('is_deleted');
            $lists = $query->sortable(['id' => 'desc'])->paginate(20);
            $totalData = 0;
        }
        
        return view('crm.clients.clientsemaillist', compact(['lists', 'totalData']));
    }

    public function archived(Request $request)
	{
		$query 		= Admin::where('is_archived', '=', '1')
            ->whereIn('type', ['client', 'lead'])
            ->with('archivedByStaff');
        StaffClientVisibility::restrictAdminEloquentQuery($query);
        $query = $this->applyArchivedListFilters($query, $request);
        $totalData 	= $query->count();
        $lists		= $query->sortable(['id' => 'desc'])->paginate(20)->appends($request->except('page'));
        return view('crm.archived.index', compact(['lists', 'totalData']));
    }

    /**
     * Archive a client
     * Sets is_archived = 1, archived_by = current staff user, archived_on = now
     *
     * @param Request $request
     * @param string $id Encoded client ID
     * @return \Illuminate\Http\RedirectResponse
     */
    public function archive(Request $request, $id)
    {
        try {
            // Decode the client ID
            $decodedId = convert_uudecode(base64_decode($id));
            
            if (!is_numeric($decodedId)) {
                return redirect()->route('clients.index')
                    ->with('error', 'Invalid client ID.');
            }
            
            // Find the client
            $client = Admin::where('id', $decodedId)
                ->whereIn('type', ['client', 'lead'])
                ->first();
            
            if (!$client) {
                return redirect()->route('clients.index')
                    ->with('error', 'Client not found.');
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $decodedId, Auth::user())) {
                return redirect()->route('clients.index')
                    ->with('error', config('constants.unauthorized'));
            }
            
            // Check if already archived
            if ($client->is_archived == 1) {
                return redirect()->route('clients.index')
                    ->with('info', 'Client is already archived.');
            }
            
            // Archive the client (archived_by stores staff.id — admin guard uses staff provider)
            $client->is_archived = 1;
            $client->archived_by = Auth::guard('admin')->id();
            $client->archived_on = now();
            $client->save();
            
            return redirect()->route('clients.index')
                ->with('success', 'Client has been archived successfully.');
                
        } catch (\Exception $e) {
            Log::error('Error archiving client: ' . $e->getMessage());
            return redirect()->route('clients.index')
                ->with('error', 'An error occurred while archiving the client. Please try again.');
        }
    }

    /**
     * Unarchive a client
     * Sets is_archived = 0 for the specified client
     *
     * @param Request $request
     * @param int $id Client ID
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function unarchive(Request $request, $id)
    {
        try {
            // Find the client (including archived ones)
            $client = Admin::where('id', $id)
                ->whereIn('type', ['client', 'lead'])
                ->first();
            
            if (!$client) {
                $message = 'Client not found.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 404);
                }
                return redirect()->route('clients.archived')
                    ->with('error', $message);
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                $message = config('constants.unauthorized');
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 403);
                }
                return redirect()->route('clients.archived')
                    ->with('error', $message);
            }
            
            // Check if already unarchived
            if ($client->is_archived == 0) {
                $message = 'Client is already unarchived.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 200);
                }
                return redirect()->route('clients.archived')
                    ->with('info', $message);
            }
            
            // Unarchive the client and clear archive metadata
            $client->is_archived = 0;
            $client->archived_by = null;
            $client->archived_on = null;
            $client->save();
            
            $message = 'Client has been unarchived successfully.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 1, 'message' => $message], 200);
            }
            
            return redirect()->route('clients.archived')
                ->with('success', $message);
                
        } catch (\Exception $e) {
            Log::error('Error unarchiving client: ' . $e->getMessage());
            $message = 'An error occurred while unarchiving the client. Please try again.';
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 0, 'message' => $message], 500);
            }
            
            return redirect()->route('clients.archived')
                ->with('error', $message);
        }
    }

	// REMOVED - prospects method
	// public function prospects(Request $request)
	// {
    //     return view('crm.prospects.index');
    // }

    // NOTE: Client creation is done via lead conversion, not direct creation
    // The create() method has been removed as clients are created by converting leads
    // See: LeadConversionController for lead-to-client conversion

    public function store(Request $request)
    {   //dd($request->all());
        $requestData = $request->all();
        
        try {
            // Validate the request data
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'dob' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The date of birth cannot be a future date.');
                            }
                        } catch (\Exception $e) {
                            // Format validation handles invalid dates
                        }
                    }
                ],
                'dob_verified' => 'nullable|in:1',
                'dob_verify_document' => 'nullable|string|max:255',
                'age' => 'nullable|string',
                'gender' => 'nullable|in:Male,Female,Other',
                'marital_status' => 'nullable|in:Never Married,Engaged,Married,De Facto,Separated,Divorced,Widowed',

                'phone_verified' => 'nullable|in:1',
                'contact_type_hidden.*' => 'nullable|in:Personal,Office,Work,Mobile,Business,Secondary,Father,Mother,Brother,Sister,Uncle,Aunt,Cousin,Others,Partner,Not In Use',
                'country_code.*' => 'nullable|string|max:10',
                'phone.*' => 'nullable|string|max:20',
                'email_type_hidden.*' => 'nullable|in:Personal,Work,Business,Secondary,Additional,Sister,Brother,Father,Mother,Uncle,Auntie',
                'email.*' => 'nullable|email|max:255',
                'visa_country.*' => 'nullable|string|max:255',
                'passports.*.passport_number' => 'nullable|string|max:50',
                'passports.*.issue_date' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The document issue date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'passports.*.expiry_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'visas.*.visa_type' => 'nullable|exists:matters,id',
                'visas.*.expiry_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'visas.*.grant_date' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The visa grant date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'visas.*.description' => 'nullable|string|max:255',
                'visa_expiry_verified' => 'nullable|in:1',
                'is_current_address' => 'nullable|in:1',
                'address.*' => 'nullable|string|max:1000',
                'zip.*' => 'nullable|string|max:20',
                'regional_code.*' => 'nullable|string|max:50',
                'address_start_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The address start date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'address_end_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The address end date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'travel_country_visited.*' => 'nullable|string|max:255',
                'travel_arrival_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The travel arrival date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'travel_departure_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The travel departure date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'travel_purpose.*' => 'nullable|string|max:500',
                'level_hidden.*' => 'nullable|string|max:255',
                'name.*' => 'nullable|string|max:255',
                'country_hidden.*' => 'nullable|string|max:255',
                'start_date.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'finish_date.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'relevant_qualification_hidden.*' => 'nullable|in:1',
                'job_title.*' => 'nullable|string|max:255',
                'job_code.*' => 'nullable|string|max:50',
                'job_country_hidden.*' => 'nullable|string|max:255',
                'job_start_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The employment start date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'job_finish_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The employment finish date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'relevant_experience_hidden.*' => 'nullable|in:1',
                'nomi_occupation.*' => 'nullable|string|max:500',
                'occupation_code.*' => 'nullable|string|max:500',
                'list.*' => 'nullable|string|max:500',
                'visa_subclass.*' => 'nullable|string|max:500',
                'dates.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'expiry_dates.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'relevant_occupation_hidden.*' => 'nullable|in:1',
                'test_type_hidden.*' => 'nullable|in:IELTS,IELTS_A,PTE,TOEFL,CAE,OET,CELPIP,MET,LANGUAGECERT',
                'listening.*' => 'nullable|string|max:10',
                'reading.*' => 'nullable|string|max:10',
                'writing.*' => 'nullable|string|max:10',
                'speaking.*' => 'nullable|string|max:10',
                'overall_score.*' => 'nullable|string|max:10',
                'test_date.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'relevant_test_hidden.*' => 'nullable|in:1',
                'naati_test' => 'nullable|in:1',
                'naati_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'py_test' => 'nullable|in:1',
                'py_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'spouse_has_english_score' => 'nullable|in:Yes,No',
                'spouse_has_skill_assessment' => 'nullable|in:Yes,No',
                'spouse_test_type' => 'nullable|in:IELTS,IELTS_A,PTE,TOEFL,CAE,OET,CELPIP,MET,LANGUAGECERT',
                'spouse_listening_score' => 'nullable|string|max:10',
                'spouse_reading_score' => 'nullable|string|max:10',
                'spouse_writing_score' => 'nullable|string|max:10',
                'spouse_speaking_score' => 'nullable|string|max:10',
                'spouse_overall_score' => 'nullable|string|max:10',
                'spouse_test_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'spouse_skill_assessment_status' => 'nullable|string|max:255',
                'spouse_nomi_occupation' => 'nullable|string|max:255',
                'spouse_assessment_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'criminal_charges.*.details' => 'nullable|string|max:1000',
                'criminal_charges.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'military_service.*.details' => 'nullable|string|max:1000',
                'military_service.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'intelligence_work.*.details' => 'nullable|string|max:1000',
                'intelligence_work.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'visa_refusals.*.details' => 'nullable|string|max:1000',
                'visa_refusals.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'deportations.*.details' => 'nullable|string|max:1000',
                'deportations.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'citizenship_refusals.*.details' => 'nullable|string|max:1000',
                'citizenship_refusals.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'health_declarations.*.details' => 'nullable|string|max:1000',
                'health_declarations.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'source' => 'nullable|in:SubAgent,Others',
                'partner_details.*' => 'nullable|string|max:255',
                'partner_relationship_type.*' => 'nullable|in:Husband,Wife,Ex-Husband,Ex-Wife,Defacto,Engaged',
                'partner_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'partner_email.*' => 'nullable|email|max:255',
                'partner_first_name.*' => 'nullable|string|max:255',
                'partner_last_name.*' => 'nullable|string|max:255',
                'partner_phone.*' => 'nullable|string|max:20',
                'children_details.*' => 'nullable|string|max:255',
                'children_relationship_type.*' => 'nullable|in:Son,Daughter,Step Son,Step Daughter',
                'children_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'children_email.*' => 'nullable|email|max:255',
                'children_first_name.*' => 'nullable|string|max:255',
                'children_last_name.*' => 'nullable|string|max:255',
                'children_phone.*' => 'nullable|string|max:20',
                'parent_details.*' => 'nullable|string|max:255',
                'parent_relationship_type.*' => 'nullable|in:Father,Mother,Step Father,Step Mother,Mother-in-law,Father-in-law',
                'parent_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'parent_email.*' => 'nullable|email|max:255',
                'parent_first_name.*' => 'nullable|string|max:255',
                'parent_last_name.*' => 'nullable|string|max:255',
                'parent_phone.*' => 'nullable|string|max:20',
                'siblings_details.*' => 'nullable|string|max:255',
                'siblings_relationship_type.*' => 'nullable|in:Brother,Sister,Step Brother,Step Sister',
                'siblings_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'siblings_email.*' => 'nullable|email|max:255',
                'siblings_first_name.*' => 'nullable|string|max:255',
                'siblings_last_name.*' => 'nullable|string|max:255',
                'siblings_phone.*' => 'nullable|string|max:20',
                'others_details.*' => 'nullable|string|max:255',
                'others_relationship_type.*' => 'nullable|in:Cousin,Friend,Uncle,Aunt,Grandchild,Granddaughter,Grandparent,Niece,Nephew,Grandfather,Son-in-law,Daughter-in-law,Brother-in-law,Sister-in-law',
                'others_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'others_email.*' => 'nullable|email|max:255',
                'others_first_name.*' => 'nullable|string|max:255',
                'others_last_name.*' => 'nullable|string|max:255',
                'others_phone.*' => 'nullable|string|max:20',
                'type' => 'required|in:lead,client',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }

        // Custom validation: Check if at least one unique email is provided
        if (empty($validated['email']) || !array_filter($validated['email'])) {
            return redirect()->back()
                ->withErrors(['email' => 'At least one email address is required.'])
                ->withInput();
        }

        // Check if at least one email is unique (not already in database)
        $hasUniqueEmail = false;
        foreach ($validated['email'] as $email) {
            if (!empty($email) && !Admin::where('email', $email)->exists()) {
                $hasUniqueEmail = true;
                break;
            }
        }
        
        if (!$hasUniqueEmail) {
            return redirect()->back()
                ->withErrors(['email' => 'At least one unique email address is required.'])
                ->withInput();
        }

        // Custom validation: Check if at least one phone is provided
        if (empty($validated['phone']) || !array_filter($validated['phone'])) {
            return redirect()->back()
                ->withErrors(['phone' => 'At least one phone number is required.'])
                ->withInput();
        }

        // Check if at least one phone is unique (not already in database)
        $hasUniquePhone = false;
        foreach ($validated['phone'] as $index => $phone) {
            if (!empty($phone)) {
                $countryCode = $validated['country_code'][$index] ?? '';
                $fullPhone = $countryCode . $phone;
                if (!Admin::where('phone', $fullPhone)->exists()) {
                    $hasUniquePhone = true;
                    break;
                }
            }
        }
        
        if (!$hasUniquePhone) {
            return redirect()->back()
                ->withErrors(['phone' => 'At least one unique phone number is required.'])
                ->withInput();
        }

        // Check for duplicate Personal phone types
        if (!empty($validated['contact_type_hidden'])) {
            $personalPhoneCount = array_count_values($validated['contact_type_hidden'])['Personal'] ?? 0;
            if ($personalPhoneCount > 1) {
                return redirect()->back()->withErrors(['phone' => 'Only one phone number can be marked as Personal.'])->withInput();
            }
        }

        foreach ($validated['phone'] as $index => $phone) {
            if (!empty($phone)) {
                if (PhoneHelper::formatForStorage($validated['country_code'][$index] ?? '') === '') {
                    return redirect()->back()
                        ->withErrors(['country_code' => 'Please select a valid country code for each phone number.'])
                        ->withInput();
                }
            }
        }

        // Check for duplicate Personal email types
        if (!empty($validated['email_type_hidden'])) {
            $personalEmailCount = array_count_values($validated['email_type_hidden'])['Personal'] ?? 0;
            if ($personalEmailCount > 1) {
                return redirect()->back()->withErrors(['email' => 'Only one email address can be marked as Personal.'])->withInput();
            }
        }

        // Custom validation: DOB Verify Document is required when DOB is verified
        if (isset($validated['dob_verified']) && $validated['dob_verified'] === '1' && empty($requestData['dob_verify_document'])) {
            return redirect()->back()
                ->withErrors(['dob_verify_document' => 'DOB Verify Document is required when DOB is verified.'])
                ->withInput();
        }

        // Get the last email and email type
        $lastEmail = null;
        $lastEmailType = null;
        if (!empty($validated['email_type_hidden']) && !empty($validated['email'])) {
            $emailCount = count($validated['email']);
            for ($i = $emailCount - 1; $i >= 0; $i--) {
                if (!empty($validated['email'][$i])) {
                    $lastEmail = $validated['email'][$i];
                    $lastEmailType = $validated['email_type_hidden'][$i];
                    break;
                }
            }
        }

        // Get the last contact type and phone number
        $lastContactType = null;
        $lastPhone = null;
        $lastCountryCode = null;
        if (!empty($validated['contact_type_hidden']) && !empty($validated['phone'])) {
            $phoneCount = count($validated['phone']);
            for ($i = $phoneCount - 1; $i >= 0; $i--) {
                if (!empty($validated['phone'][$i])) {
                    $lastContactType = $validated['contact_type_hidden'][$i];
                    $lastCountryCode = PhoneHelper::formatForStorage($validated['country_code'][$i] ?? '');
                    $lastPhone = $validated['phone'][$i];
                    break;
                }
            }
        }

        // Handle special cases for duplicate email and phone
        $timestamp = time();
        $modifiedEmail = $lastEmail;
        $modifiedPhone = $lastPhone;
        $emailModified = false;
        $phoneModified = false;

                        // Check for duplicate email and handle special case
                if ($lastEmail) {
                    if (Admin::where('email', $lastEmail)->exists()) {
                        // Special case: allow demo@gmail.com to be duplicated with timestamp
                        if ($lastEmail === 'demo@gmail.com') {
                            // Add timestamp to local part (before @ symbol)
                            $emailParts = explode('@', $lastEmail);
                            $localPart = $emailParts[0];
                            $domainPart = $emailParts[1];
                            $modifiedEmail = $localPart . '_' . $timestamp . '@' . $domainPart;
                            $emailModified = true;
                        } else {
                            return redirect()->back()->withErrors(['email' => 'The provided email is already in use.'])->withInput();
                        }
                    }
                }

        // Check for duplicate phone and handle special case
        if ($lastPhone) {
            if (Admin::where('phone', $lastPhone)->exists()) {
                // Special case: allow 4444444444 to be duplicated with timestamp
                if ($lastPhone === '4444444444') {
                    $modifiedPhone = $lastPhone . '_' . $timestamp;
                    $phoneModified = true;
                } else {
                    return redirect()->back()->withErrors(['phone' => 'The provided phone number is already in use.'])->withInput();
                }
            }
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Generate client_counter and client_id using centralized service
            // This prevents race conditions and duplicate references
            $referenceService = app(ClientReferenceService::class);
            $reference = $referenceService->generateClientReference($validated['first_name']);
            $client_id = $reference['client_id'];
            $client_current_counter = $reference['client_counter'];

            // Create the main client/lead record in the admins table
            // Use Lead model if type is 'lead', otherwise use Admin model for clients
            $client = ($validated['type'] === 'lead') ? new \App\Models\Lead() : new Admin();
            $client->first_name = $validated['first_name'];
            $client->last_name = $validated['last_name'] ?? null;
            $client->dob = $validated['dob'] ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['dob']))) : null;

            $currentDateTime = \Carbon\Carbon::now();
            $currentUserId = Auth::user()->id;

            // DOB verification
            if (isset($validated['dob_verified']) && $validated['dob_verified'] === '1') {
                $client->dob_verified_date = $currentDateTime;
                $client->dob_verified_by = $currentUserId;
                
                // Recalculate age when DOB is verified (ensures age is current)
                if ($client->dob && $client->dob !== null) {
                    try {
                        $dobDate = \Carbon\Carbon::parse($client->dob);
                        $client->age = $dobDate->diff(\Carbon\Carbon::now())->format('%y years %m months');
                    } catch (\Exception $e) {
                        // If calculation fails, use provided age or keep existing
                        $client->age = $validated['age'] ?? null;
                    }
                } else {
                    $client->age = $validated['age'] ?? null;
                }
            } else {
                $client->dob_verified_date = null;
                $client->dob_verified_by = null;
                $client->age = $validated['age'] ?? null;
            }
            $client->gender = $validated['gender'] ?? null;
            $client->marital_status = $validated['marital_status'] ?? null;
            $client->country_passport = $validated['visa_country'][0] ?? null;
            $client->client_counter = $client_current_counter;
            $client->client_id = $client_id;
            $client->email = $modifiedEmail;
            $client->email_type = $lastEmailType ?? null;


            $client->country_code = $lastCountryCode ?? null;
            $client->contact_type = $lastContactType ?? null;
            $client->phone = $modifiedPhone;

            if (isset($validated['phone_verified']) && $validated['phone_verified'] === '1') {
                $client->phone_verified_date = $currentDateTime;
                $client->phone_verified_by = $currentUserId;
            } else {
                $client->phone_verified_date = null;
                $client->phone_verified_by = null;
            }

            $client->naati_test = isset($validated['naati_test']) ? 1 : 0;
            $client->naati_date = $validated['naati_date'] ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['naati_date']))) : null;
            $client->py_test = isset($validated['py_test']) ? 1 : 0;
            $client->py_date = $validated['py_date'] ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['py_date']))) : null;
            $client->source = $validated['source'] ?? null;
            $client->type = $validated['type'];
            // Staff id for lead list visibility (restrictLeadListQuery) and assignment; same for clients
            $client->user_id = $currentUserId;

            $client->dob_verify_document = $requestData['dob_verify_document'];

            $client->created_at = now();
            $client->updated_at = now();

            // Visa Expiry Verification
            if (isset($validated['visa_expiry_verified']) && $validated['visa_expiry_verified'] === '1') {
                if (isset($validated['visa_country'][0]) && $validated['visa_country'][0] === 'Australia') {
                    $client->visa_expiry_verified_at = null;
                    $client->visa_expiry_verified_by = null;
                } else {
                    $client->visa_expiry_verified_at = $currentDateTime;
                    $client->visa_expiry_verified_by = $currentUserId;
                }
            } else {
                $client->visa_expiry_verified_at = null;
                $client->visa_expiry_verified_by = null;
            }

            $client->save();

            // Save phone numbers
            if (!empty($validated['contact_type_hidden']) && !empty($validated['phone'])) {
                foreach ($validated['contact_type_hidden'] as $index => $contact_type) {
                    if (!empty($validated['phone'][$index])) {
                        $phoneToSave = $validated['phone'][$index];
                        
                        // If this is the last phone and it was modified, use the modified version
                        if ($index === array_key_last($validated['phone']) && $phoneModified) {
                            $phoneToSave = $modifiedPhone;
                        }
                        
                        ClientContact::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'contact_type' => $contact_type,
                            'country_code' => PhoneHelper::formatForStorage($validated['country_code'][$index] ?? ''),
                            'phone' => $phoneToSave,
                            'is_verified' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save email addresses
            if (!empty($validated['email_type_hidden']) && !empty($validated['email'])) {
                foreach ($validated['email_type_hidden'] as $index => $email_type) {
                    if (!empty($validated['email'][$index])) {
                        $emailToSave = $validated['email'][$index];
                        
                        // If this is the last email and it was modified, use the modified version
                        if ($index === array_key_last($validated['email']) && $emailModified) {
                            $emailToSave = $modifiedEmail;
                        }
                        
                        ClientEmail::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'email_type' => $email_type,
                            'email' => $emailToSave,
                            'is_verified' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save passports
            if (!empty($validated['passports'])) {
                foreach ($validated['passports'] as $index => $passport) {
                    if (!empty($passport['passport_number'])) {
                        ClientPassportInformation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'passport' => $passport['passport_number'],
                            'passport_issue_date' => !empty($passport['issue_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $passport['issue_date'])))
                                : null,
                            'passport_expiry_date' => !empty($passport['expiry_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $passport['expiry_date'])))
                                : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save visa details
            if (!empty($validated['visas']) && isset($validated['visa_country'][0]) && $validated['visa_country'][0] !== 'Australia') {
                foreach ($validated['visas'] as $index => $visa) {
                    if (!empty($visa['visa_type'])) {
                        ClientVisaCountry::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'visa_type' => $visa['visa_type'],
                            'visa_expiry_date' => !empty($visa['expiry_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $visa['expiry_date'])))
                                : null,
                            'visa_grant_date' => !empty($visa['grant_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $visa['grant_date'])))
                                : null,
                            'visa_description' => $visa['description'] ?? null,
                            'visa_expiry_verified_at' => isset($validated['visa_expiry_verified']) ? now() : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save addresses
            if (!empty($validated['address'])) {
                $count = count($validated['address']);
                if ($count > 0) {
                    $lastIndex = $count - 1;
                    $lastAddress = $validated['address'][$lastIndex];
                    $lastZip = $validated['zip'][$lastIndex];

                    if (!empty($lastAddress) || !empty($lastZip)) {
                        $client->address = $lastAddress;
                        $client->zip = $lastZip;
                        $client->save();
                    }

                    $isCurrentAddress = isset($validated['is_current_address']) && $validated['is_current_address'] === '1';
                    $reversedKeys = array_reverse(array_keys($validated['address']));
                    $lastIndexInLoop = count($reversedKeys) - 1;

                    foreach ($reversedKeys as $index => $key) {
                        $addr = $validated['address'][$key] ?? null;
                        $zip = $validated['zip'][$key] ?? null;
                        $regional_code = $validated['regional_code'][$key] ?? null;
                        $start_date = $validated['address_start_date'][$key] ?? null;
                        $end_date = $validated['address_end_date'][$key] ?? null;

                        $formatted_start_date = null;
                        if (!empty($start_date)) {
                            try {
                                $date = \Carbon\Carbon::createFromFormat('d/m/Y', $start_date);
                                $formatted_start_date = $date->format('Y-m-d');
                            } catch (\Exception $e) {
                                throw new \Exception('Invalid Address Start Date format: ' . $start_date);
                            }
                        }

                        $formatted_end_date = null;
                        if (!empty($end_date)) {
                            try {
                                $date = \Carbon\Carbon::createFromFormat('d/m/Y', $end_date);
                                $formatted_end_date = $date->format('Y-m-d');
                            } catch (\Exception $e) {
                                throw new \Exception('Invalid Address End Date format: ' . $end_date);
                            }
                        }

                        if (!empty($addr) || !empty($zip)) {
                            $isCurrent = ($index === $lastIndexInLoop && $isCurrentAddress) ? 1 : 0;
                            ClientAddress::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'address' => $addr,
                                'zip' => $zip,
                                'regional_code' => $regional_code,
                                'start_date' => $formatted_start_date,
                                'end_date' => $formatted_end_date,
                                'is_current' => $isCurrent,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // Save travel history
            if (!empty($validated['travel_country_visited'])) {
                foreach ($validated['travel_country_visited'] as $index => $country) {
                    if (!empty($country)) {
                        ClientTravelInformation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'travel_country_visited' => $country,
                            'travel_arrival_date' => !empty($validated['travel_arrival_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['travel_arrival_date'][$index])))
                                : null,
                            'travel_departure_date' => !empty($validated['travel_departure_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['travel_departure_date'][$index])))
                                : null,
                            'travel_purpose' => $validated['travel_purpose'][$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                
                // Log activity for travel history creation
                $newTravels = ClientTravelInformation::where('client_id', $client->id)->get();
                $travelDisplay = [];
                foreach ($newTravels as $travel) {
                    $display = [];
                    if ($travel->travel_country_visited) {
                        $display[] = 'Country: ' . $travel->travel_country_visited;
                    }
                    if ($travel->travel_arrival_date) {
                        $display[] = 'Arrival: ' . date('d/m/Y', strtotime($travel->travel_arrival_date));
                    }
                    if ($travel->travel_departure_date) {
                        $display[] = 'Departure: ' . date('d/m/Y', strtotime($travel->travel_departure_date));
                    }
                    if ($travel->travel_purpose) {
                        $display[] = 'Purpose: ' . $travel->travel_purpose;
                    }
                    $travelDisplay[] = !empty($display) ? implode(', ', $display) : 'Travel record';
                }
                $travelDisplayStr = !empty($travelDisplay) ? implode(' | ', $travelDisplay) : '(empty)';
                
                $this->logClientActivityWithChanges(
                    $client->id,
                    'added travel information',
                    ['Travel Information' => [
                        'old' => '(empty)',
                        'new' => $travelDisplayStr
                    ]],
                    'activity'
                );
            }

            // Save qualifications
            if (!empty($validated['level_hidden'])) {
                foreach ($validated['level_hidden'] as $index => $level) {
                    if (!empty($level)) {
                        ClientQualification::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'level' => $level,
                            'name' => $validated['name'][$index] ?? null,
                            'qual_college_name' => $requestData['qual_college_name'][$index] ?? null,
                            'qual_campus' => $requestData['qual_campus'][$index] ?? null,
                            'country' => $validated['country_hidden'][$index] ?? null,
                            'qual_state' => $requestData['qual_state'][$index] ?? null,
                            'start_date' => !empty($validated['start_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['start_date'][$index])))
                                : null,
                            'finish_date' => !empty($validated['finish_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['finish_date'][$index])))
                                : null,
                            'relevant_qualification' => isset($validated['relevant_qualification_hidden'][$index]) ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save work experiences
            if (!empty($validated['job_title'])) {
                foreach ($validated['job_title'] as $index => $job_title) {
                    if (!empty($job_title)) {
                        ClientExperience::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'job_title' => $job_title,
                            'job_code' => $validated['job_code'][$index] ?? null,
                            'job_country' => $validated['job_country_hidden'][$index] ?? null,
                            'job_start_date' => !empty($validated['job_start_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['job_start_date'][$index])))
                                : null,
                            'job_finish_date' => !empty($validated['job_finish_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['job_finish_date'][$index])))
                                : null,
                            'relevant_experience' => isset($validated['relevant_experience_hidden'][$index]) ? 1 : 0,
                            'job_emp_name' => $requestData['job_emp_name'][$index] ?? null,
                            'job_state' => $requestData['job_state'][$index] ?? null,
                            'job_type' => $requestData['job_type'][$index] ?? null,
                            'fte_multiplier' => 1.00,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save occupations
            if (!empty($validated['nomi_occupation'])) {
                foreach ($validated['nomi_occupation'] as $index => $nomi_occupation) {
                    if (!empty($nomi_occupation)) {
                        ClientOccupation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'nomi_occupation' => $nomi_occupation,
                            'occupation_code' => $validated['occupation_code'][$index] ?? null,
                            'list' => $validated['list'][$index] ?? null,
                            //'visa_subclass' => $validated['visa_subclass'][$index] ?? null,
                            'occ_reference_no' => $requestData['occ_reference_no'][$index] ?? null,
                            'dates' => !empty($validated['dates'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['dates'][$index])))
                                : null,
                            'expiry_dates' => !empty($validated['expiry_dates'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['expiry_dates'][$index])))
                                : null,
                            //'relevant_occupation' => isset($validated['relevant_occupation_hidden'][$index]) ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save test scores
            if (!empty($validated['test_type_hidden'])) {
                foreach ($validated['test_type_hidden'] as $index => $test_type) {
                    if (!empty($test_type)) {
                        ClientTestScore::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'test_type' => $test_type,
                            'listening' => $validated['listening'][$index] ?? null,
                            'reading' => $validated['reading'][$index] ?? null,
                            'writing' => $validated['writing'][$index] ?? null,
                            'speaking' => $validated['speaking'][$index] ?? null,
                            'overall_score' => $validated['overall_score'][$index] ?? null,
                            'test_date' => !empty($validated['test_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['test_date'][$index])))
                                : null,
                            'relevant_test' => isset($validated['relevant_test_hidden'][$index]) ? 1 : 0,
                            'test_reference_no' => $requestData['test_reference_no'][$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save spouse details
            if (isset($validated['marital_status']) && $validated['marital_status'] === 'Married') {
                ClientSpouseDetail::create([
                    'client_id' => $client->id,
                    'admin_id' => Auth::user()->id,
                    'spouse_has_english_score' => $validated['spouse_has_english_score'] ?? 'No',
                    'spouse_has_skill_assessment' => $validated['spouse_has_skill_assessment'] ?? 'No',
                    'spouse_test_type' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_test_type'] ?? null) : null,
                    'spouse_listening_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_listening_score'] ?? null) : null,
                    'spouse_reading_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_reading_score'] ?? null) : null,
                    'spouse_writing_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_writing_score'] ?? null) : null,
                    'spouse_speaking_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_speaking_score'] ?? null) : null,
                    'spouse_overall_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_overall_score'] ?? null) : null,
                    'spouse_test_date' => $validated['spouse_has_english_score'] === 'Yes' && !empty($validated['spouse_test_date'])
                        ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['spouse_test_date'])))
                        : null,
                    'spouse_skill_assessment_status' => $validated['spouse_has_skill_assessment'] === 'Yes' ? ($validated['spouse_skill_assessment_status'] ?? null) : null,
                    'spouse_nomi_occupation' => $validated['spouse_has_skill_assessment'] === 'Yes' ? ($validated['spouse_nomi_occupation'] ?? null) : null,
                    'spouse_assessment_date' => $validated['spouse_has_skill_assessment'] === 'Yes' && !empty($validated['spouse_assessment_date'])
                        ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['spouse_assessment_date'])))
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Save character and history details
            $characterSections = [
                'criminal_charges' => 1,
                'military_service' => 2,
                'intelligence_work' => 3,
                'visa_refusals' => 4,
                'deportations' => 5,
                'citizenship_refusals' => 6,
                'health_declarations' => 7,
            ];

            foreach ($characterSections as $field => $typeOfCharacter) {
                if (!empty($validated[$field])) {
                    foreach ($validated[$field] as $index => $record) {
                        if (!empty($record['details'])) {
                            ClientCharacter::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'type_of_character' => $typeOfCharacter,
                                'character_detail' => $record['details'],
                                'character_date' => !empty($record['date'])
                                    ? date('Y-m-d', strtotime(str_replace('/', '-', $record['date'])))
                                    : null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // Update Partner Handling to include all family member types
            $familyTypes = [
                'partner' => ['Husband', 'Wife', 'Ex-Wife', 'Defacto', 'Engaged'],
                'children' => ['Son', 'Daughter', 'Step Son', 'Step Daughter'],
                'parent' => ['Father', 'Mother', 'Step Father', 'Step Mother', 'Mother-in-law', 'Father-in-law'],
                'siblings' => ['Brother', 'Sister', 'Step Brother', 'Step Sister'],
                'others' => ['Cousin', 'Friend', 'Uncle', 'Aunt', 'Grandchild', 'Granddaughter', 'Grandparent', 'Niece', 'Nephew', 'Grandfather', 'Son-in-law', 'Daughter-in-law', 'Brother-in-law', 'Sister-in-law'],
            ];

            // Function to get reciprocal relationship based on gender
            $getReciprocalRelationship = function($relationshipType, $currentGender, $relatedGender, $clientGender = '') {
                switch ($relationshipType) {
                    // Partner relationships
                    case 'Husband':
                        return 'Wife';
                    case 'Wife':
                        return 'Husband';
                    case 'Ex-Wife':
                        return 'Ex-Husband';
                    case 'Defacto':
                        return 'Defacto';
                    case 'Engaged':
                        return 'Engaged';
                    
                    // Parent-Child relationships
                    case 'Son':
                        return $relatedGender === 'Female' ? 'Mother' : 'Father';
                    case 'Daughter':
                        return $relatedGender === 'Female' ? 'Mother' : 'Father';
                    case 'Step Son':
                        return $relatedGender === 'Female' ? 'Step Mother' : 'Step Father';
                    case 'Step Daughter':
                        return $relatedGender === 'Female' ? 'Step Mother' : 'Step Father';
                    case 'Father':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    case 'Mother':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    case 'Step Father':
                        return $relatedGender === 'Female' ? 'Step Daughter' : 'Step Son';
                    case 'Step Mother':
                        return $relatedGender === 'Female' ? 'Step Daughter' : 'Step Son';
                    case 'Mother-in-law':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    case 'Father-in-law':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    
                    // Sibling relationships
                    case 'Brother':
                        return $relatedGender === 'Female' ? 'Sister' : 'Brother';
                    case 'Sister':
                        return $relatedGender === 'Female' ? 'Sister' : 'Brother';
                    case 'Step Brother':
                        return $relatedGender === 'Female' ? 'Step Sister' : 'Step Brother';
                    case 'Step Sister':
                        return $relatedGender === 'Female' ? 'Step Sister' : 'Step Brother';
                    
                    // Other relationships
                    case 'Cousin':
                        return 'Cousin';
                    case 'Friend':
                        return 'Friend';
                    case 'Uncle':
                        return $relatedGender === 'Female' ? 'Niece' : 'Nephew';
                    case 'Aunt':
                        return $relatedGender === 'Female' ? 'Niece' : 'Nephew';
                    case 'Grandchild':
                        return $relatedGender === 'Female' ? 'Grandmother' : 'Grandfather';
                    case 'Granddaughter':
                        return $relatedGender === 'Female' ? 'Grandmother' : 'Grandfather';
                    case 'Grandparent':
                        return $relatedGender === 'Female' ? 'Granddaughter' : 'Grandson';
                    case 'Grandfather':
                        return $relatedGender === 'Female' ? 'Granddaughter' : 'Grandson';
                    case 'Grandmother':
                        return $relatedGender === 'Female' ? 'Granddaughter' : 'Grandson';
                    case 'Niece':
                        return $relatedGender === 'Female' ? 'Aunt' : 'Uncle';
                    case 'Nephew':
                        return $relatedGender === 'Female' ? 'Aunt' : 'Uncle';
                    // In-law reciprocals (based on client's gender when adding an "other")
                    case 'Son-in-law':
                        return $clientGender === 'Female' ? 'Mother-in-law' : 'Father-in-law';
                    case 'Daughter-in-law':
                        return $clientGender === 'Female' ? 'Mother-in-law' : 'Father-in-law';
                    case 'Brother-in-law':
                        return $clientGender === 'Female' ? 'Sister-in-law' : 'Brother-in-law';
                    case 'Sister-in-law':
                        return $clientGender === 'Female' ? 'Sister-in-law' : 'Brother-in-law';
                    
                    default:
                        return $relationshipType; // Fallback to same relationship type
                }
            };

            // Clear existing relationships for the client
            foreach ($familyTypes as $type => $relationships) {
                if (!empty($requestData["{$type}_details"]) || !empty($requestData["{$type}_relationship_type"])) {
                    $detailsArray = $requestData["{$type}_details"] ?? [];
                    $relationshipTypeArray = $requestData["{$type}_relationship_type"] ?? [];
                    $partnerIdArray = $requestData["{$type}_id"] ?? [];
                    $emailArray = $requestData["{$type}_email"] ?? [];
                    $firstNameArray = $requestData["{$type}_first_name"] ?? [];
                    $lastNameArray = $requestData["{$type}_last_name"] ?? [];
                    $phoneArray = $requestData["{$type}_phone"] ?? [];
                    $companyArray = $requestData["{$type}_company_type"] ?? [];
                    $genderArray = $requestData["{$type}_gender"] ?? [];
                    //$dobArray = $requestData["{$type}_dob"] ?? [];

                    $dobArray = [];
                    if (!empty($requestData["{$type}_dob"]) && is_array($requestData["{$type}_dob"])) {
                        foreach ($requestData["{$type}_dob"] as $dobIndex => $dobValue) {
                            if (!empty($dobValue)) {
                                try {
                                    $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $dobValue);
                                    $dobArray[$dobIndex] = $dobDate->format('Y-m-d'); // Convert to Y-m-d for storage
                                } catch (\Exception $e) {
                                    return redirect()->back()->withErrors(['dob' => 'Invalid Date of Birth format: ' . $dobValue . '. Must be in dd/mm/yyyy format.'])->withInput();
                                }
                            }
                        }
                    }

                    foreach ($detailsArray as $key => $details) {
                        $relationshipType = $relationshipTypeArray[$key] ?? null;
                        $partnerId = $partnerIdArray[$key] ?? null;
                        $email = $emailArray[$key] ?? null;
                        $firstName = $firstNameArray[$key] ?? null;
                        $lastName = $lastNameArray[$key] ?? null;
                        $phone = $phoneArray[$key] ?? null;
                        $companyType = $companyArray[$key] ?? null;
                        $gender = $genderArray[$key] ?? null;
                        $dob = $dobArray[$key] ?? null;

                        // Skip if neither details nor relationship type is provided
                        if (empty($details) && empty($relationshipType)) {
                            continue;
                        }

                        // Ensure relationship type is provided
                        if (empty($relationshipType)) {
                            throw new \Exception("Relationship type is required for {$type} entry at index {$key}.");
                        }
                        //dd($partnerId);
                        // Determine if we need to save extra fields (when related_client_id is not set)
                        $relatedClientId = $partnerId && is_numeric($partnerId) ? $partnerId : null;
                        $saveExtraFields = !$relatedClientId;

                        // Prepare data for the primary relationship
                        $partnerData = [
                            'admin_id' => Auth::user()->id,
                            'client_id' => $client->id,
                            'related_client_id' => $relatedClientId ? $relatedClientId : null,
                            'details' => $saveExtraFields ? $details : ($relatedClientId ? $details : null),
                            'relationship_type' => $relationshipType,
                            'company_type' => $companyType,
                            'email' => $saveExtraFields ? $email : null,
                            'first_name' => $saveExtraFields ? $firstName : null,
                            'last_name' => $saveExtraFields ? $lastName : null,
                            'phone' => $saveExtraFields ? $phone : null,
                            'gender' => $gender, // Always save gender as it's now a main field
                            'dob' => $saveExtraFields ? $dob : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // Save the primary relationship
                        $newPartner = ClientRelationship::create($partnerData);

                        // Create reciprocal relationship if related_client_id is set
                        if ($relatedClientId) {
                            $relatedClient = Admin::find($relatedClientId);
                            if ($relatedClient) {
                                // Get the reciprocal relationship type (for "others" in-laws, uses client's gender)
                                $reciprocalRelationshipType = $getReciprocalRelationship($relationshipType, $gender, $relatedClient->gender ?? 'Male', $type === 'others' ? ($client->gender ?? '') : '');
                                
                                ClientRelationship::create([
                                    'admin_id' => Auth::user()->id,
                                    'client_id' => $relatedClientId,
                                    'related_client_id' => $client->id,
                                    //'details' => $details,
                                    'details' => "{$client->first_name} {$client->last_name} ({$client->email}, {$client->phone}, {$client->client_id})",
                                    'relationship_type' => $reciprocalRelationshipType,
                                    'company_type' => $companyType,
                                    'email' => null,
                                    'first_name' => null,
                                    'last_name' => null,
                                    'phone' => null,
                                    'gender' =>  $client->gender ? $client->gender : null, // Save gender for reciprocal relationship too
                                    'dob' => null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            }

            // Commit the transaction
            DB::commit();

            // Redirect with success message
            if ($validated['type'] === 'lead') {
                return redirect()->route('leads.index')->with('success', 'Lead created successfully.');
            } else {
                return redirect()->route('clients.index')->with('success', 'Client created successfully.');
            }
        } catch (\Exception $e) {
            // Roll back the transaction on error
            DB::rollBack();

            // Log the error for debugging
            Log::error('Lead/Client creation failed: ' . $e->getMessage());

            // Redirect back with error message
            if ($validated['type'] === 'lead') {
                return redirect()->back()->withErrors(['error' => 'Failed to create lead. Please try again: ' . $e->getMessage()])->withInput();
            } else {
                return redirect()->back()->withErrors(['error' => 'Failed to create client. Please try again: ' . $e->getMessage()])->withInput();
            }
        }
    }

    // getNextCounter method moved to ClientHelpers trait

	/*public function downloadpdf(Request $request, $id = NULL){
	    $fetchd = \App\Models\Document::where('id',$id)->first();
	    $data = ['title' => 'Welcome to codeplaners.com','image' => $fetchd->myfile];
        $pdf = PDF::loadView('myPDF', $data);
        return $pdf->stream('codeplaners.pdf');
	}*/

	public function downloadpdf(Request $request, $id = NULL){
	    $fetchd = \App\Models\Document::where('id',$id)->first();
        if (!$fetchd || empty($fetchd->myfile)) {
            abort(404, 'Document not found.');
        }
        $admin = DB::table('admins')->select('client_id')->where('id', $fetchd->client_id)->first();
        if (!$admin) {
            abort(404, 'Client not found.');
        }
        // When myfile is already a full S3 URL (modern docs with myfile_key), use it directly
        if (str_starts_with($fetchd->myfile, 'http')) {
            $imageUrl = $fetchd->myfile;
        } else {
            // Legacy: construct S3 path using myfile_key (filename) or myfile, then get URL
            $fileName = $fetchd->myfile_key ?? $fetchd->myfile;
            if ($fetchd->doc_type == 'migration') {
                $filePath = $admin->client_id.'/'.$fetchd->folder_name.'/'.$fileName;
            } else {
                $filePath = $admin->client_id.'/'.$fetchd->doc_type.'/'.$fileName;
            }
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $imageUrl = $disk->url($filePath);
        }
        // Generate the PDF using service container to avoid facade type issues
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf = $pdf->loadView('myPDF', compact('imageUrl'));

        // Return the generated PDF
        return $pdf->stream('codeplaners.pdf');
    }

    public function edit($id)
    {
        // Check authorization (assumed to be handled elsewhere)
        if (isset($id) && !empty($id)) {
            $id = $this->decodeString($id);
            if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
            }
            if (Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->exists()) {
                $fetchedData = Admin::with('company.contactPerson')->find($id);
                
                // Route to appropriate edit page
                if ($fetchedData && $fetchedData->is_company) {
                    // Use service to get all data with optimized queries (prevents N+1)
                    $data = app(\App\Services\ClientEditService::class)->getClientEditData($id);
                    
                    // Use separate company edit page
                    return view('crm.clients.company_edit', $data);
                } else {
                    // Use service to get all data with optimized queries (prevents N+1)
                    $data = app(\App\Services\ClientEditService::class)->getClientEditData($id);
                    
                    return view('crm.clients.edit', $data);
                }
            } else {
                return Redirect::to('/clients')->with('error', 'Client does not exist.');
            }
        } else {
            return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
        }
    }

    /**
     * Handle legacy test scores form submission (old format with band_score fields)
     * Converts legacy format to new ClientTestScore structure
     */
    public function editTestScores(Request $request)
    {
        try {
            $requestData = $request->all();
            $clientId = $requestData['client_id'] ?? null;
            
            if (!$clientId) {
                return redirect()->back()->withErrors(['error' => 'Client ID is required'])->withInput();
            }

            // Verify client exists
            $client = Admin::find($clientId);
            $isClient = in_array($client->type ?? '', ['client', 'lead']);
            if (!$client || !$isClient) {
                return redirect()->back()->withErrors(['error' => 'Client not found'])->withInput();
            }

            // Delete existing TOEFL, IELTS, and PTE test scores for this client (only the ones handled by this legacy form)
            ClientTestScore::where('client_id', $clientId)
                ->whereIn('test_type', ['TOEFL', 'IELTS', 'PTE'])
                ->delete();

            // Process TOEFL scores
            if (!empty($requestData['band_score_1_1']) || !empty($requestData['band_score_2_1']) || 
                !empty($requestData['band_score_3_1']) || !empty($requestData['band_score_4_1']) || 
                !empty($requestData['score_1'])) {
                
                $testDate = $requestData['band_score_5_1'] ?? null;
                $formattedDate = null;
                if (!empty($testDate)) {
                    try {
                        $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $testDate);
                        $formattedDate = $dateObj->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date format, skip
                    }
                }

                if (!empty($requestData['band_score_1_1']) || !empty($requestData['band_score_2_1']) || 
                    !empty($requestData['band_score_3_1']) || !empty($requestData['band_score_4_1']) || 
                    !empty($requestData['score_1'])) {
                    ClientTestScore::create([
                        'admin_id' => Auth::user()->id,
                        'client_id' => $clientId,
                        'test_type' => 'TOEFL',
                        'listening' => $requestData['band_score_1_1'] ?? null,
                        'reading' => $requestData['band_score_2_1'] ?? null,
                        'writing' => $requestData['band_score_3_1'] ?? null,
                        'speaking' => $requestData['band_score_4_1'] ?? null,
                        'overall_score' => $requestData['score_1'] ?? null,
                        'test_date' => $formattedDate,
                        'relevant_test' => 1
                    ]);
                }
            }

            // Process IELTS scores
            if (!empty($requestData['band_score_5_2']) || !empty($requestData['band_score_6_2']) || 
                !empty($requestData['band_score_7_2']) || !empty($requestData['band_score_8_2']) || 
                !empty($requestData['score_2'])) {
                
                $testDate = $requestData['band_score_6_1'] ?? null;
                $formattedDate = null;
                if (!empty($testDate)) {
                    try {
                        $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $testDate);
                        $formattedDate = $dateObj->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date format, skip
                    }
                }

                if (!empty($requestData['band_score_5_2']) || !empty($requestData['band_score_6_2']) || 
                    !empty($requestData['band_score_7_2']) || !empty($requestData['band_score_8_2']) || 
                    !empty($requestData['score_2'])) {
                    ClientTestScore::create([
                        'admin_id' => Auth::user()->id,
                        'client_id' => $clientId,
                        'test_type' => 'IELTS',
                        'listening' => $requestData['band_score_5_2'] ?? null,
                        'reading' => $requestData['band_score_6_2'] ?? null,
                        'writing' => $requestData['band_score_7_2'] ?? null,
                        'speaking' => $requestData['band_score_8_2'] ?? null,
                        'overall_score' => $requestData['score_2'] ?? null,
                        'test_date' => $formattedDate,
                        'relevant_test' => 1
                    ]);
                }
            }

            // Process PTE scores
            if (!empty($requestData['band_score_9_3']) || !empty($requestData['band_score_10_3']) || 
                !empty($requestData['band_score_11_3']) || !empty($requestData['band_score_12_3']) || 
                !empty($requestData['score_3'])) {
                
                $testDate = $requestData['band_score_7_1'] ?? null;
                $formattedDate = null;
                if (!empty($testDate)) {
                    try {
                        $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $testDate);
                        $formattedDate = $dateObj->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date format, skip
                    }
                }

                if (!empty($requestData['band_score_9_3']) || !empty($requestData['band_score_10_3']) || 
                    !empty($requestData['band_score_11_3']) || !empty($requestData['band_score_12_3']) || 
                    !empty($requestData['score_3'])) {
                    ClientTestScore::create([
                        'admin_id' => Auth::user()->id,
                        'client_id' => $clientId,
                        'test_type' => 'PTE',
                        'listening' => $requestData['band_score_9_3'] ?? null,
                        'reading' => $requestData['band_score_10_3'] ?? null,
                        'writing' => $requestData['band_score_11_3'] ?? null,
                        'speaking' => $requestData['band_score_12_3'] ?? null,
                        'overall_score' => $requestData['score_3'] ?? null,
                        'test_date' => $formattedDate,
                        'relevant_test' => 1
                    ]);
                }
            }

            return redirect()->back()->with('success', 'Test scores updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Error updating test scores: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Resolve matter dropdown, header ref, workflow stage, and references once for client detail.
     */
    protected function buildClientDetailMatterContext(int $clientId, ?string $matterRefNo, array $validTabNames): array
    {
        $matterCount = ClientMatter::where('client_id', $clientId)
            ->where('matter_status', 1)
            ->count();

        $matterListQuery = DB::table('client_matters')
            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->select(
                'client_matters.id',
                'client_matters.client_unique_matter_no',
                'matters.title',
                'client_matters.sel_matter_id'
            )
            ->where('client_matters.client_id', $clientId)
            ->where('client_matters.matter_status', 1)
            ->whereNotNull('client_matters.sel_matter_id');

        $matterListArr = $matterListQuery->orderBy('client_matters.created_at', 'desc')->get()->all();

        $showMatterDropdown = false;
        $latestClientMatterId = null;

        if ($matterRefNo) {
            $urlMatterCount = DB::table('client_matters')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->where('matter_status', 1)
                ->whereNotNull('sel_matter_id')
                ->count();

            if ($urlMatterCount > 0) {
                $showMatterDropdown = true;
                usort($matterListArr, function ($a, $b) use ($matterRefNo) {
                    if ($a->client_unique_matter_no == $matterRefNo && $b->client_unique_matter_no != $matterRefNo) {
                        return -1;
                    }
                    if ($a->client_unique_matter_no != $matterRefNo && $b->client_unique_matter_no == $matterRefNo) {
                        return 1;
                    }
                    return 0;
                });
                $urlMatter = ClientMatter::select('id')
                    ->where('client_id', $clientId)
                    ->where('client_unique_matter_no', $matterRefNo)
                    ->first();
                $latestClientMatterId = $urlMatter ? $urlMatter->id : null;
            } elseif (count($matterListArr) > 0) {
                $showMatterDropdown = true;
                $latestClientMatter = ClientMatter::where('client_id', $clientId)
                    ->where('matter_status', 1)
                    ->latest()
                    ->first();
                $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
            }
        } elseif (count($matterListArr) > 0) {
            $showMatterDropdown = true;
            $latestClientMatter = ClientMatter::where('client_id', $clientId)
                ->where('matter_status', 1)
                ->latest()
                ->first();
            $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
        }

        if ($matterRefNo) {
            $matterInfoArr = ClientMatter::select('client_unique_matter_no')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } elseif ($matterCount > 0) {
            $matterInfoArr = ClientMatter::select('client_unique_matter_no')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $matterInfoArr = null;
        }

        $workflowStageArr = null;
        if ($matterRefNo) {
            $workflowStageArr = DB::table('client_matters')
                ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                ->select('workflow_stages.name')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } else {
            $clientMatterInfo = DB::table('client_matters')
                ->select('client_unique_matter_no')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();

            if ($clientMatterInfo) {
                $workflowStageArr = DB::table('client_matters')
                    ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                    ->select('workflow_stages.name')
                    ->where('client_id', $clientId)
                    ->where('client_unique_matter_no', $clientMatterInfo->client_unique_matter_no)
                    ->first();
            }
        }

        if ($matterRefNo) {
            $matterRefInfoArr = ClientMatter::select('department_reference', 'other_reference')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } elseif ($matterCount > 0) {
            $matterRefInfoArr = ClientMatter::select('department_reference', 'other_reference')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $matterRefInfoArr = null;
        }

        $isMatterIdInUrl = $matterRefNo !== null
            && $matterRefNo !== ''
            && ! in_array(strtolower($matterRefNo), array_map('strtolower', $validTabNames), true);

        return [
            'matter_cnt' => $matterCount,
            'matter_list_arr' => $matterListArr,
            'latestClientMatterId' => $latestClientMatterId,
            'matter_info_arr' => $matterInfoArr,
            'workflow_stage_arr' => $workflowStageArr,
            'matter__ref_info_arr' => $matterRefInfoArr,
            'showMatterDropdown' => $showMatterDropdown,
            'isMatterIdInUrl' => $isMatterIdInUrl,
            'validTabNames' => $validTabNames,
        ];
    }

    public function detail(Request $request, $id = NULL, $id1 = NULL, $tab = NULL)
    {
        $clientDetailQueryCount = 0;
        $clientDetailQueryTimeMs = 0.0;
        $shouldLogClientDetailQueries = (bool) env('CLIENT_DETAIL_QUERY_LOG', false);

        if ($shouldLogClientDetailQueries) {
            DB::listen(function ($query) use (&$clientDetailQueryCount, &$clientDetailQueryTimeMs) {
                $clientDetailQueryCount++;
                $clientDetailQueryTimeMs += (float) $query->time;
            });
        }

        if (isset($request->t)) {
            if (\App\Models\Notification::where('id', $request->t)->exists()) {
                $ovv = \App\Models\Notification::find($request->t);
                $ovv->receiver_status = 1;
                $ovv->save();
            }
        }

        if (isset($id) && !empty($id)) {
            $encodeId = $id;
            $id = $this->decodeString($id);

            // If $id1 holds a tab name rather than a matter reference (happens when the URL
            // only has two segments, e.g. /clients/detail/{client}/{tab}), move it to $tab
            // so that every downstream view receives a clean null $id1.
            $knownTabNames = [
                'personaldetails', 'companydetails', 'activityfeed', 'noteterm', 'personaldocuments', 'visadocuments', 'nominationdocuments',
                'eoiroi', 'emails', 'client_portal',
                // Legacy removed tab slugs - keep as reserved so they are not treated as matter IDs
                'formgenerations', 'formgenerationsl',
                'workflow', 'checklists', 'account', 'notuseddocuments',
            ];
            if ($id1 && in_array(strtolower($id1), $knownTabNames)) {
                if (empty($tab)) {
                    $tab = $id1;
                }
                $id1 = null;
            }

            $targetRecord = Admin::query()
                ->where('id', (int) $id)
                ->whereIn('type', ['client', 'lead'])
                ->first(['id', 'type', 'first_name', 'last_name', 'client_id']);

            if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                $displayName = trim((string) (($targetRecord->first_name ?? '') . ' ' . ($targetRecord->last_name ?? '')));
                if ($displayName === '') {
                    $displayName = (string) ($targetRecord->client_id ?? ('#' . (int) $id));
                }

                $accessModalPayload = [
                    'id' => $encodeId . '/Client',
                    'cid' => (int) $id,
                    'name' => $displayName,
                    'record_type' => (string) ($targetRecord->type ?? 'client'),
                    'redirect_to' => route('clients.detail', [
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1,
                        'tab' => $tab,
                    ]),
                ];

                return view('crm.access.detail-gate', [
                    'crossAccessAutoOpen' => $accessModalPayload,
                ]);
            }

            // Set default tab if not provided
            $activeTab = $tab ?? 'personaldetails';

            if (Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->exists()) {
                $fetchedData = Admin::with([
                    'company.contactPerson',
                    'company.tradingNames',
                    'company.directors.directorClient',
                    'company.nominations.nominatedClient',
                    'company.sponsorships',
                    'company.financials',
                    'companyNominationsAsNominee.company',
                ])->find($id); //dd($fetchedData);
                
                // Route to company detail page if this is a company
                if ($fetchedData && $fetchedData->is_company) {
                    // Fetch data needed for company detail page
                    $clientAddresses = ClientAddress::where('client_id', $id)->orderedForDisplay()->get();
                    $clientContacts = ClientContact::where('client_id', $id)->get();
                    $emails = ClientEmail::where('client_id', $id)->get() ?? [];
                    
                    $matter_cnt = \App\Models\ClientMatter::select('id')
                        ->where('client_id',$id)
                        ->where('matter_status',1)
                        ->count();
                    
                    // Get current admin user data for SMS templates
                    $currentAdmin = Auth::user();
                    $staffName = $currentAdmin->first_name . ' ' . $currentAdmin->last_name;
                    $matterNumber = $id1 ?? '';
                    $officePhone = $currentAdmin->phone ?? '';
                    $officeCountryCode = '+61';
                    $notPickedCallSmsDefault = $this->notPickedCallSmsDefaultForClient($fetchedData);
                    
                    $encodeId = base64_encode(convert_uuencode($id));
                    $activeTab = $tab ?? 'companydetails';
                    
                    return view('crm.companies.detail', compact(
                        'fetchedData', 'clientAddresses', 'clientContacts', 'emails',
                        'encodeId', 'id1', 'activeTab',
                        'staffName', 'matterNumber', 'officePhone', 'officeCountryCode',
                        'notPickedCallSmsDefault'
                    ));
                }


                //Fetch other client-related data
                $clientAddresses = ClientAddress::where('client_id', $id)->orderedForDisplay()->get();
                $clientContacts = ClientContact::where('client_id', $id)->get();
                $emails = ClientEmail::where('client_id', $id)->get() ?? [];
                $qualifications = ClientQualification::where('client_id', $id)->orderByRaw('finish_date DESC NULLS LAST')->get() ?? [];
                $experiences = ClientExperience::where('client_id', $id)->orderByRaw('job_finish_date DESC NULLS LAST')->get() ?? [];
                $testScores = ClientTestScore::where('client_id', $id)->get() ?? [];
                $visaCountries = ClientVisaCountry::where('client_id', $id)->get() ?? [];
                $clientSpouseDetail = ClientSpouseDetail::where('client_id', $id)->get();
                $clientOccupations = ClientOccupation::where('client_id', $id)->get();
                $ClientPoints = ClientPoint::where('client_id', $id)->get();

                // Fetch client family details with optimized query
                // Eager load related client to prevent N+1 queries in the view
                $clientFamilyDetails = ClientRelationship::where('client_id', $id)
                    ->with(['relatedClient:id,first_name,last_name,client_id'])
                    ->get() ?? [];
                
                // Detect if current matter is EOI-related
                $isEoiMatter = false;
                if ($id1) {
                    // Check if the current matter is EOI
                    $currentMatter = DB::table('client_matters as cm')
                        ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $id)
                        ->where('cm.client_unique_matter_no', $id1)
                        ->where('cm.matter_status', 1)
                        ->select('m.nick_name', 'm.title')
                        ->first();
                    
                    if ($currentMatter) {
                        $isEoiMatter = (
                            strtolower($currentMatter->nick_name) === 'eoi' ||
                            stripos($currentMatter->title, 'eoi') !== false ||
                            stripos($currentMatter->title, 'expression of interest') !== false ||
                            stripos($currentMatter->title, 'expression') !== false ||
                            stripos($currentMatter->title, 'interest') !== false
                        );
                    }
                } else {
                    // If no specific matter is selected, check if client has any EOI matter
                    $eoiMatterExists = DB::table('client_matters as cm')
                        ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $id)
                        ->where('cm.matter_status', 1)
                        ->where(function($query) {
                            $query->where('m.nick_name', 'ILIKE', 'eoi')
                                  ->orWhere('m.title', 'LIKE', '%eoi%')
                                  ->orWhere('m.title', 'LIKE', '%expression of interest%')
                                  ->orWhere('m.title', 'LIKE', '%expression%');
                        })
                        ->exists();
                    
                    $isEoiMatter = $eoiMatterExists;
                }
                
                //dd($clientFamilyDetails);
                
                // Check and insert/update application record when Client Portal tab is accessed
                // applications table removed - workflow is tracked via client_matters
                
                // Get current admin user data for SMS templates
                $currentAdmin = Auth::user();
                $staffName = $currentAdmin->first_name . ' ' . $currentAdmin->last_name;
                $matterNumber = $id1 ?? '';
                $officePhone = $currentAdmin->phone ?? '';
                $officeCountryCode = '+61';
                $notPickedCallSmsDefault = $this->notPickedCallSmsDefaultForClient($fetchedData);

                // Employer nominations: only list companies this staff may open (cross-access / allocation).
                $visibleNomineeNominations = $fetchedData->companyNominationsAsNominee
                    ->filter(function ($n) {
                        $companyAdminId = $n->company?->admin_id;
                        if ($companyAdminId === null) {
                            return true;
                        }

                        return StaffClientVisibility::canAccessClientOrLead((int) $companyAdminId, Auth::user());
                    })
                    ->values();

                $assignableStaff = collect();
                $leadStageLabels = [];
                if (($fetchedData->type ?? '') === 'lead') {
                    $assignableStaff = Staff::where('status', 1)
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get();
                    $leadStageLabels = [
                        'new' => 'New',
                        'follow_up' => 'Follow up',
                        'not_qualified' => 'Not qualified',
                        'hostile' => 'Hostile',
                    ];
                }

                $showGoogleReviewReminderModal = $this->shouldShowGoogleReviewReminderModal($fetchedData);

                // Companies where this client is the designated primary contact (separate from nominee nominations).
                $primaryContactCompaniesForClient = Company::query()
                    ->where('contact_person_id', (int) $id)
                    ->with(['tradingNames'])
                    ->get()
                    ->filter(function (Company $c) {
                        $aid = (int) ($c->admin_id ?? 0);
                        if ($aid <= 0) {
                            return false;
                        }

                        return StaffClientVisibility::canAccessClientOrLead($aid, Auth::user());
                    })
                    ->values();

                $validTabNames = [
                    'personaldetails', 'activityfeed', 'noteterm', 'personaldocuments', 'visadocuments', 'nominationdocuments',
                    'eoiroi', 'emails',
                    'formgenerations', 'formgenerationsl',
                    'client_portal', 'application', 'workflow', 'checklists',
                ];
                $matterContext = $this->buildClientDetailMatterContext((int) $id, $id1, $validTabNames);

                $clientNotes = Note::where('client_id', $id)
                    ->whereNull('assigned_to')
                    ->where('type', 'client')
                    ->with('user')
                    ->orderby('pin', 'DESC')
                    ->orderBy('updated_at', 'DESC')
                    ->get();

                $personalDetailContacts = $clientContacts->filter(function ($contact) {
                    return ($contact->contact_type ?? '') !== 'Not In Use';
                })->values();

                if ($shouldLogClientDetailQueries) {
                    Log::info('Client detail query profile', [
                        'client_id' => (int) $id,
                        'matter_ref' => $id1,
                        'tab' => $activeTab,
                        'query_count' => $clientDetailQueryCount,
                        'query_time_ms' => round($clientDetailQueryTimeMs, 2),
                    ]);
                }

                //Return the view with all data
                return view('crm.clients.detail', array_merge(compact(
                    'fetchedData', 'clientAddresses', 'clientContacts', 'emails', 'qualifications',
                    'experiences', 'testScores', 'visaCountries', 'clientOccupations','ClientPoints', 'clientSpouseDetail',
                    'encodeId', 'id1','clientFamilyDetails', 'activeTab', 'isEoiMatter',
                    'staffName', 'matterNumber', 'officePhone', 'officeCountryCode',
                    'visibleNomineeNominations', 'notPickedCallSmsDefault',
                    'assignableStaff', 'leadStageLabels', 'showGoogleReviewReminderModal',
                    'primaryContactCompaniesForClient', 'clientNotes', 'personalDetailContacts'
                ), $matterContext));
            } else {
                return redirect()->route('clients.index')->with('error', 'Clients Not Exist');
            }
        } else {
            return redirect()->route('clients.index')->with('error', config('constants.unauthorized'));
        }
    }

    protected function googleReviewCrmTemplateExists(): bool
    {
        if ($this->googleReviewCrmTemplateExistsCache !== null) {
            return $this->googleReviewCrmTemplateExistsCache;
        }

        $this->googleReviewCrmTemplateExistsCache = EmailTemplate::crm()
            ->where(function ($q) {
                $q->where('alias', 'google_review')
                    ->orWhere('name', 'like', '%Google Review%');
            })
            ->exists();

        return $this->googleReviewCrmTemplateExistsCache;
    }

    /**
     * Staff matching config `crm.google_review_reminder_exclude_role_ids` or
     * `crm.google_review_reminder_exclude_office_ids` must not see the reminder
     * modal or change reminder state via API (Calling, Accounts, INDIA office, etc.).
     */
    protected function currentStaffIsExcludedFromGoogleReviewReminder(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $roleId = (int) ($user->role ?? 0);
        $excludedRoles = config('crm.google_review_reminder_exclude_role_ids', [14, 15]);
        if ($roleId > 0 && in_array($roleId, $excludedRoles, true)) {
            return true;
        }

        $officeId = (int) ($user->office_id ?? 0);
        $excludedOffices = config('crm.google_review_reminder_exclude_office_ids', [8]);

        return $officeId > 0 && in_array($officeId, $excludedOffices, true);
    }

    protected function shouldShowGoogleReviewReminderModal(Admin $record): bool
    {
        if ($this->currentStaffIsExcludedFromGoogleReviewReminder()) {
            return false;
        }

        if ($record->is_company) {
            return false;
        }
        if ((int) ($record->is_archived ?? 0) === 1) {
            return false;
        }
        if (! in_array($record->type, ['client', 'lead'], true)) {
            return false;
        }
        if (trim((string) $record->email) === '') {
            return false;
        }

        $status = strtolower(trim((string) ($record->google_review_reminder_status ?? '')));
        if (in_array($status, [
            Admin::GOOGLE_REVIEW_REMINDER_NOT_INTERESTED,
            Admin::GOOGLE_REVIEW_REMINDER_REVIEW_RECEIVED,
        ], true)) {
            return false;
        }

        $until = $record->google_review_reminder_snooze_until;
        if ($until && $until->isFuture()) {
            return false;
        }

        if (! $this->googleReviewCrmTemplateExists()) {
            return false;
        }

        return true;
    }

    public function updateGoogleReviewReminder(Request $request)
    {
        if ($this->currentStaffIsExcludedFromGoogleReviewReminder()) {
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'client_id' => 'required|integer|min:1',
            'action' => 'required|in:snooze,snooze_one_day,not_interested,review_received',
        ]);

        $admin = Admin::query()
            ->where('id', $validated['client_id'])
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $admin || $admin->is_company) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if ((int) ($admin->is_archived ?? 0) === 1) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $admin->id, Auth::user())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        switch ($validated['action']) {
            case 'snooze':
                $admin->google_review_reminder_snooze_until = Carbon::now()->addWeek();
                break;
            case 'snooze_one_day':
                $admin->google_review_reminder_snooze_until = Carbon::now()->addDay();
                break;
            case 'not_interested':
                $admin->google_review_reminder_status = Admin::GOOGLE_REVIEW_REMINDER_NOT_INTERESTED;
                $admin->google_review_reminder_snooze_until = null;
                break;
            case 'review_received':
                $admin->google_review_reminder_status = Admin::GOOGLE_REVIEW_REMINDER_REVIEW_RECEIVED;
                $admin->google_review_reminder_snooze_until = null;
                break;
        }

        $admin->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Send SMS with Google review link from the client/lead detail reminder modal.
     * Looks up the active SMS template by title "Google_review_link" (case-insensitive),
     * falling back to the legacy title "Google review link". No env/config involvement.
     * Available template variables: {first_name}, {last_name}.
     */
    public function sendGoogleReviewReminderSms(Request $request)
    {
        if ($this->currentStaffIsExcludedFromGoogleReviewReminder()) {
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'client_id' => 'required|integer|min:1',
        ]);

        $admin = Admin::query()
            ->where('id', $validated['client_id'])
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $admin || $admin->is_company) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if ((int) ($admin->is_archived ?? 0) === 1) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $admin->id, Auth::user())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        $rawPhone = trim((string) ($admin->country_code ?? '')).trim((string) ($admin->phone ?? ''));
        if ($rawPhone === '') {
            return response()->json(['ok' => false, 'message' => 'No phone number on file for this contact'], 422);
        }

        $rawFirst = trim((string) ($admin->first_name ?? ''));
        $firstDisplay = $rawFirst !== ''
            ? mb_convert_case(mb_strtolower($rawFirst), MB_CASE_TITLE, 'UTF-8')
            : 'there';

        $variables = [
            'first_name' => $firstDisplay,
            'last_name'  => trim((string) ($admin->last_name ?? '')),
        ];

        $template = null;
        foreach (['Google_review_link', 'Google review link'] as $tryTitle) {
            $found = SmsTemplate::active()
                ->whereRaw('lower(title) = ?', [mb_strtolower($tryTitle)])
                ->orderBy('id')
                ->first();
            if ($found) {
                $template = $found;
                break;
            }
        }

        if (! $template) {
            return response()->json([
                'ok'      => false,
                'message' => 'Google review SMS template not found. Please ensure an active SMS template titled "Google_review_link" exists in the Admin Console.',
            ], 422);
        }

        $result = $this->smsManager->sendFromTemplate(
            $rawPhone,
            (int) $template->id,
            $variables,
            ['client_id' => (int) $admin->id]
        );

        if ($result['success'] ?? false) {
            return response()->json([
                'ok' => true,
                'message' => 'Review link sent by SMS',
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => $result['message'] ?? $result['error'] ?? 'Failed to send SMS',
        ], 422);
    }

    //Update session to be complete
    public function updatesessioncompleted(Request $request,CheckinLog $checkinLog)
    {
        $data = $request->all(); //dd($data['client_id']);
        $sessionExist = CheckinLog::where('client_id', $data['client_id'])
        ->where('status', 2)
        ->update(['status' => 1]);
        if($sessionExist){
            $response['status'] 	= 	true;
            $response['message']	=	'Session completed successfully';
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
        }
        echo json_encode($response);
    }

	public function getrecipients(Request $request){
		$squery = $request->q;
		if($squery != ''){
				$d = '';
			 $squeryLower = strtolower($squery);
			 $clients = \App\Models\Admin::with('company')
       ->where('is_archived', '=', 0)
       ->whereIn('type', ['client', 'lead'])
       ->where(
           function($query) use ($squeryLower) {
             return $query
                    ->whereRaw('LOWER(email) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(client_id) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$squeryLower.'%'])
                    ->orWhereHas('company', function($q) use ($squeryLower) {
                        $q->whereRaw('LOWER(company_name) LIKE ?', ['%'.$squeryLower.'%']);
                    });
            });
            StaffClientVisibility::restrictAdminEloquentQuery($clients);
            $clients = $clients->get();

            /* $leads = \App\Models\Lead::where('converted', '=', 0)
			->where(
			function($query) use ($squery,$d) {
				return $query
					->where('email', 'LIKE', '%'.$squery.'%')
					->orwhere('first_name', 'LIKE','%'.$squery.'%')->orwhere('last_name', 'LIKE','%'.$squery.'%')->orwhere('phone', 'LIKE','%'.$squery.'%')  ->orWhere(DB::raw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))"), 'LIKE', "%".$squery."%");
				})
            ->get();*/

			// Exclude contact persons when their company is already in results (avoid duplicates)
			$companyIdsInResults = $clients->where('is_company', true)->pluck('id')->toArray();
			$contactPersonIdsToExclude = \App\Models\Company::whereIn('admin_id', $companyIdsInResults)
				->pluck('contact_person_id')
				->filter()
				->unique()
				->values()
				->toArray();
			$contactPersonIdsToExclude = array_values(array_filter($contactPersonIdsToExclude, function ($pid) use ($squery) {
				return ! $this->globalSearchQueryMatchesContactPerson((int) $pid, $squery);
			}));

			$items = array();
			foreach($clients as $clint){
				if (!$clint->is_company && in_array($clint->id, $contactPersonIdsToExclude)) {
					continue; // Skip contact person - their company is already in results
				}
				$displayName = $clint->company_name_or_personal_name;
				$items[] = array('name' => $displayName,'email'=>$clint->email,'status'=>$clint->type,'id'=>$clint->id,'cid'=>base64_encode(convert_uuencode(@$clint->id)));
			}

			$litems = array();
			/*	foreach($leads as $lead){
				$litems[] = array('name' => $lead->first_name.' '.$lead->last_name,'email'=>$lead->email,'status'=>'Lead','id'=>$lead->id,'cid'=>base64_encode(convert_uuencode(@$lead->id)));
			}*/
			$m = array_merge($items, $litems);
			echo json_encode(array('items'=>$m));
		}
	}

	public function getonlyclientrecipients(Request $request){
		$squery = $request->q;
		if($squery != ''){
				$d = '';
			$squeryLower = strtolower($squery);
			$clients = \App\Models\Admin::with('company')
			->where('is_archived', '=', 0)
			->whereIn('type', ['client', 'lead'])
			->where(
           function($query) use ($squeryLower) {
             	return $query
                    ->whereRaw('LOWER(email) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(client_id) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$squeryLower.'%'])
                    ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$squeryLower.'%'])
                    ->orWhereHas('company', function($q) use ($squeryLower) {
                        $q->whereRaw('LOWER(company_name) LIKE ?', ['%'.$squeryLower.'%']);
                    });
            });
            StaffClientVisibility::restrictAdminEloquentQuery($clients);
            $clients = $clients->get();

			// Exclude contact persons when their company is already in results (avoid duplicates)
			$companyIdsInResults = $clients->where('is_company', true)->pluck('id')->toArray();
			$contactPersonIdsToExclude = \App\Models\Company::whereIn('admin_id', $companyIdsInResults)
				->pluck('contact_person_id')
				->filter()
				->unique()
				->values()
				->toArray();
			$contactPersonIdsToExclude = array_values(array_filter($contactPersonIdsToExclude, function ($pid) use ($squery) {
				return ! $this->globalSearchQueryMatchesContactPerson((int) $pid, $squery);
			}));

			$items = array();
			foreach($clients as $clint){
				if (!$clint->is_company && in_array($clint->id, $contactPersonIdsToExclude)) {
					continue; // Skip contact person - their company is already in results
				}
				$displayName = $clint->company_name_or_personal_name;
				$items[] = array('name' => $displayName,'email'=>$clint->email,'status'=>$clint->type,'id'=>$clint->id,'cid'=>base64_encode(convert_uuencode(@$clint->id)));
			}

			$litems = array();

			$m = array_merge($items, $litems);
			echo json_encode(array('items'=>$m));
		}
	}

    public function getallclients(Request $request)
    {
        $squery = trim((string) $request->q);
        if ($squery === '') {
            return response()->json(['items' => []]);
        }

        if (mb_strlen($squery) < 2) {
            $allowShort = (strpos($squery, '-') !== false)
                || $squery === 'demo@gmail.com'
                || $squery === '4444444444';
            if (! $allowShort) {
                return response()->json(['items' => []]);
            }
        }

        $rawResults = [];
        $lim = self::GLOBAL_SEARCH_BRANCH_LIMIT;

        $squeryLower = strtolower($squery);
        $isUniversalEmail = ($squery === 'demo@gmail.com');
        $isUniversalPhone = ($squery === '4444444444');
        $isClientReferenceSearch = $this->globalSearchQueryIsClientReference($squery);
        $mysqlFtPhrase = $isClientReferenceSearch ? '' : $this->mysqlGlobalSearchBooleanFulltext($squery);
        $useMatterFt = ! $isClientReferenceSearch
            && $mysqlFtPhrase !== ''
            && $this->globalSearchMysqlFulltextIndexExists('client_matters', 'client_matters_global_search_ft');
        $useAdminFt = ! $isUniversalEmail
            && ! $isClientReferenceSearch
            && $mysqlFtPhrase !== ''
            && $this->globalSearchMysqlFulltextIndexExists('admins', 'admins_global_search_ft');

        /**
         * 1. Composite references (client_id + matter_no)
         */
        if (strpos($squery, '-') !== false) {
            $parts = explode('-', $squery, 2);
            if (count($parts) == 2) {
                $clientIdPart = $parts[0];
                $matterNoPart = $parts[1];
                $clientIdPartLower = strtolower($clientIdPart);
                $matterNoPartLower = strtolower($matterNoPart);
                $matterResults = DB::table('admins')
                    ->join('client_matters', 'admins.id', '=', 'client_matters.client_id')
                    ->leftJoin('companies', 'companies.admin_id', '=', 'admins.id')
                    ->whereIn('admins.type', ['client', 'lead'])
                    ->whereNull('admins.is_deleted')
                    ->where('admins.is_archived', 0)
                    ->where('client_matters.matter_status', 1)
                    ->whereRaw('LOWER(admins.client_id) LIKE ?', ["%{$clientIdPartLower}%"])
                    ->whereRaw('LOWER(client_matters.client_unique_matter_no) LIKE ?', ["%{$matterNoPartLower}%"])
                    ->tap(function ($q) {
                        StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($q, 'admins');
                    })
                    ->select(
                        'admins.id as client_id',
                        'admins.client_id as client_reference',
                        'admins.first_name',
                        'admins.last_name',
                        'admins.is_company',
                        'admins.email',
                        'admins.is_archived',
                        'admins.type',
                        'companies.company_name',
                        'client_matters.client_unique_matter_no'
                    )
                    ->orderByDesc('client_matters.id')
                    ->limit($lim)
                    ->get();

                foreach ($matterResults as $result) {
                    $displayName = ($result->is_company && $result->company_name)
                        ? $result->company_name
                        : trim(($result->first_name ?? '') . ' ' . ($result->last_name ?? ''));
                    $rawResults[] = [
                        'id' => base64_encode(convert_uuencode($result->client_id)) . '/Matter/' . $result->client_unique_matter_no,
                        'name' => $displayName,
                        'email' => $result->email,
                        'status' => $result->is_archived ? 'Archived' : $result->type,
                        'cid' => $result->client_id,
                        'client_reference' => $result->client_reference ?? '',
                        'is_company' => (bool) $result->is_company,
                        'record_type' => $result->type,
                    ];
                }
            }
        }

        /**
         * 2. Matter references (department / other / unique matter no)
         */
        $matterMatches = collect();
        if (! $isClientReferenceSearch) {
        $matterMatches = DB::table('client_matters')
            ->join('admins', 'client_matters.client_id', '=', 'admins.id')
            ->leftJoin('companies', 'companies.admin_id', '=', 'admins.id')
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where('admins.is_archived', 0)
            ->where('client_matters.matter_status', 1)
            ->tap(function ($q) {
                StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($q, 'admins');
            })
            ->where(function ($query) use ($squery, $squeryLower, $useMatterFt, $mysqlFtPhrase) {
                if ($useMatterFt) {
                    $query->where(function ($matterFtQuery) use ($mysqlFtPhrase, $squery, $squeryLower) {
                        $matterFtQuery->whereRaw(
                            'MATCH(client_matters.department_reference, client_matters.other_reference, client_matters.client_unique_matter_no) AGAINST (? IN BOOLEAN MODE)',
                            [$mysqlFtPhrase]
                        )
                            ->orWhere('client_matters.department_reference', 'LIKE', "%{$squery}%")
                            ->orWhere('client_matters.other_reference', 'LIKE', "%{$squery}%")
                            ->orWhereRaw('LOWER(client_matters.client_unique_matter_no) LIKE ?', ["%{$squeryLower}%"]);
                    });
                } else {
                    $query->where('client_matters.department_reference', 'LIKE', "%{$squery}%")
                        ->orWhere('client_matters.other_reference', 'LIKE', "%{$squery}%")
                        ->orWhere('client_matters.client_unique_matter_no', 'LIKE', "%{$squery}%");
                }
            })
            ->select(
                'admins.id as client_id',
                'admins.client_id as client_reference',
                'admins.first_name',
                'admins.last_name',
                'admins.is_company',
                'admins.email',
                'admins.is_archived',
                'admins.type',
                'companies.company_name',
                'client_matters.client_unique_matter_no'
            )
            ->orderByDesc('client_matters.id')
            ->limit($lim)
            ->get();
        }

        foreach ($matterMatches as $matter) {
            $displayName = ($matter->is_company && $matter->company_name)
                ? $matter->company_name
                : trim(($matter->first_name ?? '') . ' ' . ($matter->last_name ?? ''));
            $rawResults[] = [
                'id' => base64_encode(convert_uuencode($matter->client_id)) . '/Matter/' . $matter->client_unique_matter_no,
                'name' => $displayName,
                'email' => $matter->email,
                'status' => $matter->is_archived ? 'Archived' : $matter->type,
                'cid' => $matter->client_id,
                'client_reference' => $matter->client_reference ?? '',
                'is_company' => (bool) $matter->is_company,
                'record_type' => $matter->type,
            ];
        }

        /**
         * 3. Admins (clients / leads)
         */
        $d = '';
        if (strstr($squery, '/')) {
            $dob = explode('/', $squery);
            if (! empty($dob) && is_array($dob)) {
                $d = $dob[2] . '/' . $dob[1] . '/' . $dob[0];
            }
        }

        $clientsQuery = Admin::query()
            ->with(['company.contactPerson'])
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where('admins.is_archived', 0);

        if ($isClientReferenceSearch) {
            $clientsQuery->where(function ($query) use ($squeryLower) {
                $query->whereRaw('LOWER(admins.client_id) = ?', [$squeryLower])
                    ->orWhereRaw('LOWER(admins.client_id) LIKE ?', [$squeryLower.'%']);
            });
        } else {
            $clientsQuery
            ->leftJoin('client_contacts', function ($join) use ($squery, $squeryLower, $isUniversalPhone) {
                $join->on('client_contacts.client_id', '=', 'admins.id');
                if ($isUniversalPhone) {
                    $join->where(function ($phoneQuery) use ($squery, $squeryLower) {
                        $phoneQuery->whereRaw('LOWER(client_contacts.phone) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(client_contacts.phone) LIKE ?', ["%{$squery}_%"]);
                    });
                } else {
                    $join->whereRaw('LOWER(client_contacts.phone) LIKE ?', ["%{$squeryLower}%"]);
                }
            })
            ->leftJoin('client_emails', function ($join) use ($squery, $squeryLower, $isUniversalEmail) {
                $join->on('client_emails.client_id', '=', 'admins.id');
                if ($isUniversalEmail) {
                    $join->where(function ($emailQuery) use ($squeryLower) {
                        $emailQuery->whereRaw('LOWER(client_emails.email) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(client_emails.email) LIKE ?', ['demo_%@gmail.com']);
                    });
                } else {
                    $join->whereRaw('LOWER(client_emails.email) LIKE ?', ["%{$squeryLower}%"]);
                }
            })
            ->where(function ($query) use ($squery, $squeryLower, $d, $isUniversalEmail, $isUniversalPhone, $useAdminFt, $mysqlFtPhrase) {
                if ($isUniversalEmail) {
                    $query->where(function ($emailSubQuery) use ($squeryLower) {
                        $emailSubQuery->whereRaw('LOWER(admins.email) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(admins.email) LIKE ?', ['demo_%@gmail.com']);
                    });
                } elseif ($useAdminFt) {
                    // FULLTEXT misses alphanumeric client refs (e.g. VIPL2400001); keep LIKE fallback.
                    $query->where(function ($adminFtQuery) use ($mysqlFtPhrase, $squeryLower) {
                        $adminFtQuery->whereRaw(
                            'MATCH(admins.first_name, admins.last_name, admins.email, admins.client_id) AGAINST (? IN BOOLEAN MODE)',
                            [$mysqlFtPhrase]
                        )
                            ->orWhereRaw('LOWER(admins.client_id) LIKE ?', ["%{$squeryLower}%"]);
                    });
                } else {
                    $query->whereRaw('LOWER(admins.email) LIKE ?', ["%{$squeryLower}%"]);
                }

                if ($isUniversalEmail) {
                    $query->orWhereRaw('LOWER(admins.first_name) LIKE ?', ["%{$squeryLower}%"])
                        ->orWhereRaw('LOWER(admins.last_name) LIKE ?', ["%{$squeryLower}%"])
                        ->orWhereRaw('LOWER(admins.client_id) LIKE ?', ["%{$squeryLower}%"]);
                } elseif (! $useAdminFt) {
                    $query->orWhereRaw('LOWER(admins.first_name) LIKE ?', ["%{$squeryLower}%"])
                        ->orWhereRaw('LOWER(admins.last_name) LIKE ?', ["%{$squeryLower}%"])
                        ->orWhereRaw('LOWER(admins.client_id) LIKE ?', ["%{$squeryLower}%"]);
                }

                $query->orWhereHas('company', function ($q) use ($squeryLower) {
                    $q->whereRaw('LOWER(company_name) LIKE ?', ["%{$squeryLower}%"]);
                });

                if ($isUniversalPhone) {
                    $query->orWhere(function ($phoneSubQuery) use ($squery, $squeryLower) {
                        $phoneSubQuery->whereRaw('LOWER(admins.phone) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(admins.phone) LIKE ?', ["%{$squery}_%"]);
                    });
                } else {
                    $query->orWhereRaw('LOWER(admins.phone) LIKE ?', ["%{$squeryLower}%"]);
                }

                $query->orWhereRaw("LOWER(COALESCE(admins.first_name, '') || ' ' || COALESCE(admins.last_name, '')) LIKE ?", ["%{$squeryLower}%"])
                    ->orWhereNotNull('client_contacts.client_id')
                    ->orWhereNotNull('client_emails.client_id');

                if ($d != '') {
                    $query->orWhere('admins.dob', '=', $d);
                }
            });
        }

        $clientsQuery->tap(function ($q) {
            StaffClientVisibility::excludeSuperAdminOnlyLockedClientsFromAdminQuery($q);
        });
        if ($isClientReferenceSearch) {
            $clientsQuery
                ->select('admins.*')
                ->orderByRaw('CASE WHEN LOWER(admins.client_id) = ? THEN 0 ELSE 1 END', [$squeryLower])
                ->orderBy('admins.client_id')
                ->limit($lim);
        } else {
            $clientsQuery = $clientsQuery
                ->select('admins.*')
                ->distinct();
        }
        $clientsQuery = $isClientReferenceSearch
            ? $clientsQuery->get()
            : $clientsQuery
                ->orderBy('admins.created_at', 'desc')
                ->limit($lim)
                ->get();

        $clientIds = $clientsQuery->pluck('id')->toArray();

        if ($clientIds !== []) {
            $phonesData = DB::table('client_contacts')
                ->whereIn('client_id', $clientIds)
                ->select('client_id', 'phone', 'contact_type')
                ->orderBy('client_id')
                ->orderBy('contact_type')
                ->get()
                ->groupBy('client_id');

            $emailsData = DB::table('client_emails')
                ->whereIn('client_id', $clientIds)
                ->select('client_id', 'email', 'email_type')
                ->orderBy('client_id')
                ->orderBy('email_type')
                ->get()
                ->groupBy('client_id');

            $maxMatterIds = DB::table('client_matters')
                ->whereIn('client_id', $clientIds)
                ->where('matter_status', 1)
                ->select('client_id', DB::raw('MAX(id) as max_id'))
                ->groupBy('client_id')
                ->pluck('max_id', 'client_id')
                ->toArray();

            $latestMatters = [];
            if ($maxMatterIds !== []) {
                $latestMatters = DB::table('client_matters')
                    ->whereIn('id', array_values($maxMatterIds))
                    ->select('client_id', 'client_unique_matter_no')
                    ->get()
                    ->keyBy('client_id');
            }

            foreach ($clientsQuery as $client) {
                $allPhones = '';
                if (isset($phonesData[$client->id])) {
                    $phones = $phonesData[$client->id]
                        ->sortBy('contact_type')
                        ->pluck('phone')
                        ->unique()
                        ->values()
                        ->toArray();
                    $allPhones = implode(', ', $phones);
                }

                $allEmails = '';
                if (isset($emailsData[$client->id])) {
                    $emails = $emailsData[$client->id]
                        ->sortBy('email_type')
                        ->pluck('email')
                        ->unique()
                        ->values()
                        ->toArray();
                    $allEmails = implode(', ', $emails);
                }

                $latestMatterNo = isset($latestMatters[$client->id])
                    ? $latestMatters[$client->id]->client_unique_matter_no
                    : null;

                $resultFinalId = $latestMatterNo
                    ? base64_encode(convert_uuencode($client->id)) . '/Matter/' . $latestMatterNo
                    : base64_encode(convert_uuencode($client->id)) . '/Client';

                $displayName = $client->company_name_or_personal_name;
                if ($client->is_company && $client->company?->contactPerson) {
                    $cp = $client->company->contactPerson;
                    $cpBits = array_filter([
                        trim((string) ($cp->client_id ?? '')),
                        trim(($cp->first_name ?? '') . ' ' . ($cp->last_name ?? '')),
                    ]);
                    if ($cpBits !== []) {
                        $displayName .= ' — ' . implode(' ', $cpBits);
                    }
                }

                $rawResults[] = [
                    'id' => $resultFinalId,
                    'name' => $displayName,
                    'email' => $client->email,
                    'status' => $client->is_archived ? 'Archived' : $client->type,
                    'cid' => $client->id,
                    'client_reference' => $client->client_id ?? '',
                    'phones' => $allPhones,
                    'emails' => $allEmails,
                    'is_company' => (bool) $client->is_company,
                    'record_type' => $client->type,
                ];
            }
        }

        $results = StaffClientVisibility::enrichGlobalSearchItemsBatch($rawResults);

        $seenCids = [];
        $results = array_values(array_filter($results, function ($r) use (&$seenCids) {
            $cid = $r['cid'] ?? null;
            if ($cid === null || isset($seenCids[$cid])) {
                return false;
            }
            $seenCids[$cid] = true;

            return true;
        }));

        $cidsInResults = array_column($results, 'cid');
        $companyIdsInResults = Admin::whereIn('id', $cidsInResults)
            ->where('is_company', 1)
            ->pluck('id')
            ->toArray();
        $contactPersonIdsToExclude = Company::whereIn('admin_id', $companyIdsInResults)
            ->pluck('contact_person_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $contactPersonIdsToExclude = array_values(array_filter($contactPersonIdsToExclude, function ($pid) use ($squery) {
            return ! $this->globalSearchQueryMatchesContactPerson((int) $pid, $squery);
        }));
        $results = array_values(array_filter($results, function ($r) use ($contactPersonIdsToExclude) {
            return ! in_array($r['cid'], $contactPersonIdsToExclude);
        }));

        if (count($results) > self::GLOBAL_SEARCH_OUTPUT_CAP) {
            $results = array_slice($results, 0, self::GLOBAL_SEARCH_OUTPUT_CAP);
        }

        return response()->json(['items' => $results]);
    }

    protected function globalSearchMysqlFulltextIndexExists(string $table, string $indexName): bool
    {
        static $cache = [];
        $key = $table.'.'.$indexName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (! in_array($table, ['admins', 'client_matters'], true)) {
            return $cache[$key] = false;
        }
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return $cache[$key] = false;
        }
        try {
            $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

            return $cache[$key] = (count($rows) > 0);
        } catch (\Throwable) {
            return $cache[$key] = false;
        }
    }

    /**
     * Client file references (e.g. VIPL2400001, john2608773): letters + digits, no spaces.
     * These must not use FULLTEXT/name search or "john" will match unrelated clients.
     */
    protected function globalSearchQueryIsClientReference(string $squery): bool
    {
        $squery = trim($squery);
        if ($squery === '' || str_contains($squery, ' ') || str_contains($squery, '@')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9]*\d[A-Za-z0-9]*$/', $squery);
    }

    /**
     * InnoDB FULLTEXT boolean mode string. Empty => use LIKE fallback (short tokens, non-MySQL, etc.).
     */
    protected function mysqlGlobalSearchBooleanFulltext(string $squery): string
    {
        $s = preg_replace('/[^\p{L}\p{N}@.]+/u', ' ', $squery);
        $words = array_values(array_filter(explode(' ', strtolower(trim($s)))));
        $parts = [];
        foreach ($words as $w) {
            $w = preg_replace('/[^a-z0-9@._-]+/i', '', $w);
            if ($w === '') {
                continue;
            }
            if (mb_strlen($w, 'UTF-8') < 3) {
                return '';
            }
            $parts[] = '+'.$w.'*';
        }

        return $parts ? implode(' ', $parts) : '';
    }

    /**
     * When a company and its primary contact both match search, keep the contact row if the query
     * matches the contact's own phone, email, client_id, or name (not only the company).
     */
    protected function globalSearchQueryMatchesContactPerson(int $contactPersonId, string $squery): bool
    {
        $squery = trim($squery);
        if ($squery === '' || $contactPersonId <= 0) {
            return false;
        }

        $squeryLower = strtolower($squery);
        $queryDigits = preg_replace('/\D+/', '', $squery);

        $admin = Admin::query()
            ->where('id', $contactPersonId)
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $admin) {
            return false;
        }

        if (Str::contains(strtolower((string) $admin->client_id), $squeryLower)
            || Str::contains(strtolower((string) $admin->email), $squeryLower)
            || Str::contains(strtolower((string) $admin->first_name), $squeryLower)
            || Str::contains(strtolower((string) $admin->last_name), $squeryLower)
        ) {
            return true;
        }

        $fullName = strtolower(trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')));
        if ($fullName !== '' && Str::contains($fullName, $squeryLower)) {
            return true;
        }

        if ($queryDigits !== '') {
            $phoneDigits = preg_replace('/\D+/', '', (string) $admin->phone);
            if ($phoneDigits !== '' && Str::contains($phoneDigits, $queryDigits)) {
                return true;
            }

            $extraPhones = DB::table('client_contacts')
                ->where('client_id', $contactPersonId)
                ->pluck('phone');
            foreach ($extraPhones as $p) {
                $pd = preg_replace('/\D+/', '', (string) $p);
                if ($pd !== '' && Str::contains($pd, $queryDigits)) {
                    return true;
                }
            }
        }

        $extraEmails = DB::table('client_emails')
            ->where('client_id', $contactPersonId)
            ->pluck('email');
        foreach ($extraEmails as $em) {
            if (Str::contains(strtolower((string) $em), $squeryLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get staff for assignment/search (e.g. assignee dropdown).
     * Returns staff from staff table with pagination.
     */
    public function getAllStaff(Request $request) {
        $query = \App\Models\Staff::query()->select('id', 'first_name', 'last_name', 'email');
        if ($request->q) {
            $q = '%' . strtolower($request->q) . '%';
            $query->whereRaw('LOWER(first_name) LIKE ? OR LOWER(last_name) LIKE ?', [$q, $q]);
        }
        return $query->paginate(10, ['*'], 'page', $request->page ?? 1)->toArray();
    }

	public function activities(Request $request){ 
		// Bypass all output buffering
		while (ob_get_level()) {
			ob_end_clean();
		}
		
		// Start fresh output buffer
		ob_start();
		
		// Force error reporting off
		@ini_set('display_errors', '0');
		@error_reporting(0);
		
		// Initialize response with default error state
		$response = [
			'status' => false,
			'message' => 'An error occurred while fetching activities'
		];

		try {
			// Validate request has id parameter
			if (!$request->has('id') || empty($request->id)) {
				$response['message'] = 'Client ID is required';
				header('Content-Type: application/json');
				echo json_encode($response);
				ob_end_flush();
				exit;
			}

			// Check if client exists - role must be integer for PostgreSQL compatibility
			$clientExists = Admin::whereIn('type', ['client', 'lead'])->where('id', $request->id)->exists();
			
			if($clientExists && ! StaffClientVisibility::canAccessClientOrLead((int) $request->id, Auth::user())){
				$response['message'] = config('constants.unauthorized');
				header('Content-Type: application/json');
				echo json_encode($response);
				ob_end_flush();
				exit;
			}
			
			if($clientExists){
				$perPage = min(max((int) $request->input('per_page', 40), 1), 100);
				$page = max((int) $request->input('page', 1), 1);
				$staffSearch = trim((string) ($request->input('staff', $request->input('user', ''))));
				$keywordSearch = trim((string) $request->input('keyword', ''));

				$query = ActivitiesLog::where('client_id', $request->id)
					->with('staff');

				if ($staffSearch !== '') {
					$query->whereHas('staff', function ($staffQuery) use ($staffSearch) {
						$staffSearchLower = strtolower($staffSearch);
						$staffQuery->whereRaw('LOWER(first_name) LIKE ?', ['%'.$staffSearchLower.'%']);
					});
				}

				if ($keywordSearch !== '') {
					$query->where(function ($keywordQuery) use ($keywordSearch) {
						$keywordQuery->where('description', 'like', '%'.$keywordSearch.'%')
							->orWhere('subject', 'like', '%'.$keywordSearch.'%');
					});
				}

				$activities = (clone $query)
					->orderby('created_at', 'DESC')
					->skip(($page - 1) * $perPage)
					->take($perPage + 1)
					->get();

				$hasMore = $activities->count() > $perPage;
				if ($hasMore) {
					$activities = $activities->take($perPage);
				}
				
				$data = array();
				
				foreach($activities as $activit){
					$admin = $activit->staff;
					$fullName = $admin ? trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')) : 'Unknown';
					if (empty(trim($fullName))) $fullName = $admin ? $admin->first_name : 'Unknown';
					$subjectWithoutStaffPrefix = ActivitiesLog::displaySubjectWithoutStaffPrefix(
						$activit->activity_type ?? null,
						$activit->subject ?? null
					);
					$data[] = array(
						'activity_id' => $activit->id,
						'subject' => $activit->subject ?? '',
						'subject_without_staff_prefix' => $subjectWithoutStaffPrefix,
						'createdname' => $admin ? substr($admin->first_name, 0, 1) : '?',
						'name' => $fullName,
						'message' => NoteDescriptionHtml::forDisplay($activit->description ?? ''),
						'date' => date('d M Y, H:i A', strtotime($activit->created_at)),
						'created_at_ymd' => $activit->created_at ? \Carbon\Carbon::parse($activit->created_at)->format('Y-m-d') : '',
						'followup_date' => $activit->followup_date ?? '',
						'task_group' => $activit->task_group ?? '',
						'pin' => $activit->pin ?? 0,
						'activity_type' => $activit->activity_type
					);
				}

				$response['status'] 	= 	true;
				$response['data']	=	$data;
				$response['page'] = $page;
				$response['per_page'] = $perPage;
				$response['has_more'] = $hasMore;
				unset($response['message']); // Remove error message on success
			}else{
				$response['status'] 	= 	false;
				$response['message']	=	'Client not found';
			}
		} catch (\Exception $e) {
			Log::error('Error fetching activities (Exception): ' . $e->getMessage(), [
				'client_id' => $request->id ?? 'N/A',
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString()
			]);
			$response['status'] = false;
			$response['message'] = 'Exception: ' . $e->getMessage();
		} catch (\Throwable $e) {
			// Catch fatal errors
			Log::error('Fatal error fetching activities (Throwable): ' . $e->getMessage(), [
				'client_id' => $request->id ?? 'N/A',
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString()
			]);
			$response['status'] = false;
			$response['message'] = 'Fatal: ' . $e->getMessage();
		}

		// Ensure JSON response is always returned
		header('Content-Type: application/json');
		$jsonOutput = json_encode($response);
		echo $jsonOutput;
		ob_end_flush();
		exit;
	}

	public function updateclientstatus(Request $request){
		if(Admin::whereIn('type', ['client', 'lead'])->where('id', $request->id)->exists()){
			if (! StaffClientVisibility::canAccessClientOrLead((int) $request->id, Auth::user())) {
				$response['status'] = false;
				$response['message'] = config('constants.unauthorized');
				echo json_encode($response);
				return;
			}
			// rating column dropped Phase 4 - no-op
			$response['status'] 	= 	true;
			$response['message']	=	'You\'ve successfully updated your client\'s information.';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

	public function uploadmail(Request $request){
		$requestData 		= 	$request->all();
        $obj				= 	new \App\Models\EmailLog;
		$obj->user_id		=	Auth::user()->id;
		$obj->from_mail 	=  $requestData['from'];
		$obj->to_mail 		=  $requestData['to'];
		$obj->subject		=  $requestData['subject'];
		$obj->message		=  $requestData['message'];
		$obj->mail_type		=  1;
		$obj->client_id		=  @$requestData['client_id'];
		$saved				=	$obj->save();
		if(!$saved) {
            return redirect()->back()->with('error', config('constants.server_error'));
        } else {
            return redirect()->back()->with('success', 'Email uploaded Successfully');
        }
	}


    /*public function merge_records(Request $request){
        if(isset($request->merge_record_ids) && $request->merge_record_ids != ""){
            if( strpos($request->merge_record_ids, ',') !== false ) {
                $merge_record_ids_arr = explode(",",$request->merge_record_ids);
                //echo "<pre>";print_r($merge_record_ids_arr);

                //check 1st and 2nd record
                $first_record = Admin::where('id', $merge_record_ids_arr[0])->select('id','phone','email')->first();
                //echo "<pre>";print_r($first_record);
                if(!empty($first_record)){
                    $first_phone = $first_record['phone'];
                    $first_email = $first_record['email'];
                }

                $second_record = Admin::where('id', $merge_record_ids_arr[1])->select('id','phone','email')->first();
                //echo "<pre>";print_r($second_record);
                if(!empty($second_record)){
                    $second_phone = $second_record['phone'];
                    $second_email = $second_record['email'];
                }

               DB::table('admins')
                ->where('id', $merge_record_ids_arr[0])
                ->update(['phone' => $second_phone,'email' => $second_email]);

                DB::table('admins')
                ->where('id', $merge_record_ids_arr[1])
                ->update(['phone' => $first_phone,'email' => $first_email]);

                $notelist1 = Note::where('client_id', $merge_record_ids_arr[0])->whereNull('assigned_to')->where('type', 'client')->orderby('pin', 'DESC')->orderBy('created_at', 'DESC')->get();
                //dd($notelist1);

                $notelist2 = Note::where('client_id', $merge_record_ids_arr[1])->whereNull('assigned_to')->where('type', 'client')->orderby('pin', 'DESC')->orderBy('created_at', 'DESC')->get();
                //dd($notelist2);

                if(!empty($notelist2)){
                    foreach($notelist2 as $key2=>$list2){
                        $obj1 = new \App\Models\Note;
                        $obj1->user_id = $list2->user_id;
                        $obj1->client_id = $merge_record_ids_arr[0];
                        $obj1->lead_id = $list2->lead_id;
                        $obj1->title = $list2->title;
                        $obj1->description = $list2->description;
                        $obj1->mail_id = $list2->mail_id;
                        $obj1->type = $list2->type;
                        $obj1->pin = $list2->pin;
                        $obj1->action_date = $list2->action_date;
                        $obj1->is_action = $list2->is_action;
                        $obj1->assigned_to = $list2->assigned_to;
                        $obj1->status = $list2->status;
                        $obj1->task_group = $list2->task_group;
                        $saved1 = $obj1->save();
                    }
                }

                if(!empty($notelist1)){
                    foreach($notelist1 as $key1=>$list1){
                        $obj2 = new \App\Models\Note;
                        $obj2->user_id = $list1->user_id;
                        $obj2->client_id = $merge_record_ids_arr[1];
                        $obj2->lead_id = $list1->lead_id;
                        $obj2->title = $list1->title;
                        $obj2->description = $list1->description;
                        $obj2->mail_id = $list1->mail_id;
                        $obj2->type = $list1->type;
                        $obj2->pin = $list1->pin;
                        $obj2->action_date = $list1->action_date;
                        $obj2->is_action = $list1->is_action;
                        $obj2->assigned_to = $list1->assigned_to;
                        $obj2->status = $list1->status;
                        $obj2->task_group = $list1->task_group;
                        $saved2 = $obj2->save();
                    }
                }

                if($saved2){
                    $response['status'] 	= 	true;
				    $response['message']	=	'You have successfully merged records.';
                }else{
                    $response['status'] 	= 	false;
                    $response['message']	=	'Please try again';
                }
                echo json_encode($response);
            }
        }
    }*/

    public function merge_records(Request $request){
        $response = array();
        if(
            ( isset($request->merge_from) && $request->merge_from != "" )
            && ( isset($request->merge_into) && $request->merge_into != "" )
        ){
            //Update merge_into to be deleted
            DB::table('admins')->where('id',$request->merge_into)->update( array('is_deleted'=>1) );

            //activities_logs
            $activitiesLogs = DB::table('activities_logs')->where('client_id', $request->merge_from)->get(); //dd($activitiesLogs);
            if(!empty($activitiesLogs)){
                foreach($activitiesLogs as $actkey=>$actval){
                    DB::table('activities_logs')->insert(
                        [
                            'client_id' => $request->merge_into,
                            'created_by' => $actval->created_by,
                            'description' => $actval->description,
                            'created_at' => $actval->created_at,
                            'updated_at' => $actval->updated_at,
                            'subject' => $actval->subject,
                            'use_for' => $actval->use_for,
                            'followup_date' => $actval->followup_date,
                            'task_group' => $actval->task_group,
                            'task_status' => $actval->task_status,
                            'source' => $actval->source ?? null
                        ]
                    );
                }
            }

            //notes
            $notes = DB::table('notes')->where('client_id', $request->merge_from)->get(); //dd($notes);
            if(!empty($notes)){
                foreach($notes as $notekey=>$noteval){
                    DB::table('notes')->insert(
                        [
                            'user_id'=> $noteval->user_id,
                            'client_id' => $request->merge_into,
                            'lead_id' => $noteval->lead_id,
                            'title' => $noteval->title,
                            'description' => $noteval->description,
                            'created_at' => $noteval->created_at,
                            'updated_at' => $noteval->updated_at,
                            'mail_id' => $noteval->mail_id,
                            'type' => $noteval->type,
                            'pin' => $noteval->pin,
                            'action_date' => $noteval->action_date,
                            'is_action' => $noteval->is_action,
                            'assigned_to' => $noteval->assigned_to,
                            'status' => $noteval->status,
                            'task_group' => $noteval->task_group,
                        ]
                    );
                }
            }

            // applications table has been removed - workflow is tracked via client_matters

            //education documents and migration documents
            $documents = DB::table('documents')->where('client_id', $request->merge_from)->get(); //dd($documents);
            if(!empty($documents)){
                foreach($documents as $dockey=>$docval){
                    DB::table('documents')->insert(
                        [
                            'document'=> $docval->document,
                            'filetype' => $docval->filetype,
                            'myfile' => $docval->myfile,
                            'user_id' => $docval->user_id,
                            'client_id' => $request->merge_into,
                            'file_size' => $docval->file_size,
                            'type' => $docval->type,
                            'doc_type' => $docval->doc_type,
                            'created_at' => $docval->created_at,
                            'updated_at' => $docval->updated_at
                        ]
                    );
                }
            }

            //appointments
            $appointments = DB::table('appointments')->where('client_id', $request->merge_from)->get(); //dd($appointments);
            if(!empty($appointments)){
                foreach($appointments as $appkey=>$appval){
                    DB::table('appointments')->insert(
                        [
                            'user_id'=> $appval->user_id,
                            'client_id' => $request->merge_into,
                            'service_id' => $appval->service_id,
                            'noe_id' => $appval->noe_id,
                            'full_name' => $appval->full_name,
                            'email' => $appval->email,
                            'phone' => $appval->phone,
                            'timezone' => $appval->timezone,
                            'date' => $appval->date,
                            'time' => $appval->time,
                            'timeslot_full' => $appval->timeslot_full,
                            'title' => $appval->title,
                            'description' => $appval->description,
                            'invites' => $appval->invites,
                            'appointment_details' => $appval->appointment_details,
                            'status' => $appval->status,
                            'assignee' => $appval->assignee,
                            'priority' => $appval->priority,
                            'priority_no' => $appval->priority_no,
                            'created_at' => $appval->created_at,
                            'updated_at' => $appval->updated_at,
                            'related_to' => $appval->related_to,
                            'order_hash' => $appval->order_hash
                        ]
                    );
                }
            }

            //quotations
            $quotations = DB::table('quotations')->where('client_id', $request->merge_from)->get(); //dd($quotations);
            if(!empty($quotations)){
                foreach($quotations as $quotekey=>$quoteval){
                    DB::table('quotations')->insert(
                        [
                            'client_id' => $request->merge_into,
                            'user_id'=> $quoteval->user_id,
                            'total_fee' => $quoteval->total_fee,
                            'status' => $quoteval->status,
                            'due_date' => $quoteval->due_date,
                            'created_by' => $quoteval->created_by,
                            'created_at' => $quoteval->created_at,
                            'updated_at' => $quoteval->updated_at,
                            'currency' => $quoteval->currency,
                            'is_archive' => $quoteval->is_archive
                        ]
                    );
                }
            }

            // Email history (email_logs)
            $conversations = DB::table('email_logs')->where('client_id', $request->merge_from)->get(); //dd($conversations);
            if(!empty($conversations)){
                foreach($conversations as $mailkey=>$mailval){
                    DB::table('email_logs')->insert(
                        [
                            'user_id' => $mailval->user_id,
                            'from_mail' => $mailval->from_mail,
                            'to_mail' => $mailval->to_mail,
                            'cc' => $mailval->cc,
                            'template_id' => $mailval->template_id,
                            'subject' => $mailval->subject,
                            'message' => $mailval->message,
                            'created_at' => $mailval->created_at,
                            'updated_at' => $mailval->updated_at,
                            'type' => $mailval->type,
                            'reciept_id' => $mailval->reciept_id,
                            'attachments' => $mailval->attachments,
                            'mail_type' => $mailval->mail_type,
                            'client_id' => $request->merge_into
                        ]
                    );
                }
            }

            // Education table removed - system deprecated (replaced by ClientQualification)
            // Table 'education' no longer exists in database - verified 2026-01-27
            // Current qualification system uses 'client_qualifications' table with ClientQualification model

            //CheckinLogs
            $checkinLogs = DB::table('checkin_logs')->where('client_id', $request->merge_from)->get(); //dd($checkinLogs);
            if(!empty($checkinLogs)){
                foreach($checkinLogs as $checkkey=>$checkval){
                    DB::table('checkin_logs')->insert(
                        [
                             'client_id' => $request->merge_into,
                             'contact_type' => $checkval->contact_type,
                             'user_id' => $checkval->user_id,
                             'visit_purpose' => $checkval->visit_purpose,
                             'status' => $checkval->status,
                             'date' => $checkval->date,
                             'sesion_start' => $checkval->sesion_start,
                             'sesion_end' => $checkval->sesion_end,
                             'created_at' => $checkval->created_at,
                             'updated_at' => $checkval->updated_at,
                             'wait_time' => $checkval->wait_time,
                             'attend_time' => $checkval->attend_time,
                             'office' => $checkval->office,
                             'wait_type' => $checkval->wait_type
                        ]
                    );
                }
            }

            // prev_visa column dropped Phase 4 - no longer copied during merge
        }
        $response['status'] 	= 	true;
        $response['message']	=	'You have successfully merged records.';
        echo json_encode($response);
    }

    //address_auto_populate
    public function address_auto_populate(Request $request){
        $address = $request->address;
        if( isset($address) && $address != ""){
            $result = app('geocoder')->geocode($address)->get(); //dd($result[0]);
            $postalCode = $result[0]->getPostalCode();
            $locality = $result[0]->getLocality();
            if( !empty($result) ){
                $response['status'] 	= 	1;
                $response['postal_code'] = 	$postalCode;
                $response['locality'] 	= 	$locality;
                $response['message']	=	"address is success.";
            } else {
                $response['status'] 	= 	0;
                $response['postal_code'] = 	"";
                $response['locality']    = 	"";
                $response['message']	=	"address is wrong.";
            }
            echo json_encode($response);
        }
    }

    //not picked call button click
    public function notpickedcall(Request $request){
        $data = $request->all(); //dd($data);
        //Get client phone and send message via UnifiedSmsManager
        $clientInfo = Admin::select('id','country_code','phone')->where('id', $data['id'])->first();//dd($clientInfo);
        
        $smsResult = null;
        if ($clientInfo) {
            $message = $data['message'];
            $clientPhone = $clientInfo->country_code."".$clientInfo->phone;
            
            // Use UnifiedSmsManager with proper context (auto-creates activity log)
            $smsResult = $this->smsManager->sendSms($clientPhone, $message, 'notification', [
                'client_id' => $data['id']
            ]);
        }
        
        $recExist = Admin::where('id', $data['id'])->update(['not_picked_call' => $data['not_picked_call']]);
        if($recExist){
            if($data['not_picked_call'] == 1){ //if checked true
                // Activity log is now automatically created by UnifiedSmsManager
                // No need to manually create it here
                
                $response['status'] 	= 	true;
                $response['message']	=	$smsResult && $smsResult['success'] 
                    ? 'Call not picked. SMS sent successfully!' 
                    : 'Call not picked. SMS failed to send.';
                $response['not_picked_call'] 	= 	$data['not_picked_call'];
            }
            else if($data['not_picked_call'] == 0){
                $response['status'] 	= 	true;
                $response['message']	=	'You have updated call not picked bit. Please try again';
                $response['not_picked_call'] 	= 	$data['not_picked_call'];
            }
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
            $response['not_picked_call'] 	= 	$data['not_picked_call'];
        }
        echo json_encode($response);
    }

    public function deleteactivitylog(Request $request){
		$activitylogid = $request->activitylogid; //dd($activitylogid);
		if(\App\Models\ActivitiesLog::where('id',$activitylogid)->exists()){
			$data = \App\Models\ActivitiesLog::select('client_id','subject','description')->where('id',$activitylogid)->first();
			$res = DB::table('activities_logs')->where('id', @$activitylogid)->delete();
			if($res){
				$response['status'] 	= 	true;
			    $response['data']	=	$data;
			}else{
				$response['status'] 	= 	false;
			    $response['message']	=	'Please try again';
			}
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

    public function pinactivitylog(Request $request){
		$requestData = $request->all();
        if(\App\Models\ActivitiesLog::where('id',$requestData['activity_id'])->exists()){
			$activity = \App\Models\ActivitiesLog::where('id',$requestData['activity_id'])->first();
			if($activity->pin == 0){
				$obj = \App\Models\ActivitiesLog::find($activity->id);
				$obj->pin = 1;
				$saved = $obj->save();
			}else{
				$obj = \App\Models\ActivitiesLog::find($activity->id);
				$obj->pin = 0;
				$saved = $obj->save();
			}
			$response['status'] 	= 	true;
			$response['message']	=	'Pin Option added successfully';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Record not found';
		}
		echo json_encode($response);
	}

    //Fetch all contact list of any client at create note popup



    //Re-assign inbox email
    public function reassiginboxemail(Request $request) {
		$requestData = $request->all(); //dd($requestData);
		$uploaded_doc_id = $requestData['uploaded_doc_id'];
        if( \App\Models\Document::where('id', '=', $uploaded_doc_id)->exists() )
		{
            //Get existing document info
            $document_info = \App\Models\Document::select('id','file_name','filetype','myfile','client_id')->where('id', '=', $uploaded_doc_id)->first();
            $source_doc_client_id = $document_info['client_id'];
            $source_doc_myfile = $document_info['myfile'];

            $source_doc_admin_info = \App\Models\Admin::select('client_id')->where('id', '=', $source_doc_client_id)->first();
            $source_doc_client_unique_id = $source_doc_admin_info['client_id'];

            $dest_assign_client_id = $requestData['reassign_client_id'];
            $dest_doc_admin_info = \App\Models\Admin::select('client_id')->where('id', '=', $dest_assign_client_id)->first();
            $dest_doc_client_unique_id = $dest_doc_admin_info['client_id'];

            // Define the source and destination paths
            $sourcePath = $source_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your source file path
            $destinationPath = $dest_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your destination file path

            try {
                // Check if the file exists before copying
                if (Storage::disk('s3')->exists($sourcePath)) {
                    // Use the copy method to copy the file within S3
                    Storage::disk('s3')->copy($sourcePath, $destinationPath);
                    Storage::disk('s3')->delete($sourcePath);
                    //echo "File copied successfully.";
                } else {
                    //echo "Source file does not exist.";
                }
            } catch (\Exception $e) {
                // Handle errors here
                echo "Error: " . $e->getMessage();
            }

            //Update document with client id and matter id
            $upd_doc_info = \App\Models\Document::find($uploaded_doc_id);
            $upd_doc_info->client_id = $requestData['reassign_client_id'];
            $upd_doc_info->user_id = Auth::user()->id;
            $upd_doc_info->client_matter_id = $requestData['reassign_client_matter_id'];
            $saved_doc_info = $upd_doc_info->save();
            if($saved_doc_info){
                //Update email_logs table with client id and matter id
                $id = $requestData['memail_id'];
                $email_log_info = \App\Models\EmailLog::find($id);
                $email_log_info->client_id = $requestData['reassign_client_id'];
                $email_log_info->user_id = Auth::user()->id;
                $email_log_info->client_matter_id = $requestData['reassign_client_matter_id'];
                $saved_mail_report_info = $email_log_info->save();
                if($saved_mail_report_info){
                    $client_matter_info = \App\Models\ClientMatter::select('client_unique_matter_no')->where('id', '=', $requestData['reassign_client_matter_id'])->first();
                    $subject = 'Inbox Email Re-assign';
                    $objs = new \App\Models\ActivitiesLog;
                    $objs->client_id = $requestData['reassign_client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = $dest_doc_client_unique_id.'-'.$client_matter_info['client_unique_matter_no'];
                    $objs->subject = $subject;
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
                }

                //Update date in client matter table
                if( isset( $requestData['reassign_client_matter_id'] ) && $requestData['reassign_client_matter_id'] != ""){
                    $obj1 = \App\Models\ClientMatter::find($requestData['reassign_client_matter_id']);
                    $obj1->updated_at = date('Y-m-d H:i:s');
                    $obj1->save();
                }
            }
            if(!$saved_mail_report_info) {
                return redirect()->back()->with('error', config('constants.server_error'));
            } else {
                return redirect()->back()->with('success', 'Inbox email re-assigned successfully');
            }
        } else {
            return redirect()->back()->with('error', config('constants.server_error'));
		}
    }

    //Re-assign sent email
    public function reassigsentemail(Request $request) {
		$requestData = $request->all(); //dd($requestData);
		$uploaded_doc_id = $requestData['uploaded_doc_id'];
        if( \App\Models\Document::where('id', '=', $uploaded_doc_id)->exists() )
		{
            //Get existing document info
            $document_info = \App\Models\Document::select('id','file_name','filetype','myfile','client_id')->where('id', '=', $uploaded_doc_id)->first();
            $source_doc_client_id = $document_info['client_id'];
            $source_doc_myfile = $document_info['myfile'];

            $source_doc_admin_info = \App\Models\Admin::select('client_id')->where('id', '=', $source_doc_client_id)->first();
            $source_doc_client_unique_id = $source_doc_admin_info['client_id'];

            $dest_assign_client_id = $requestData['reassign_sent_client_id'];
            $dest_doc_admin_info = \App\Models\Admin::select('client_id')->where('id', '=', $dest_assign_client_id)->first();
            $dest_doc_client_unique_id = $dest_doc_admin_info['client_id'];

            // Define the source and destination paths
            $sourcePath = $source_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your source file path
            $destinationPath = $dest_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your destination file path

            try {
                // Check if the file exists before copying
                if (Storage::disk('s3')->exists($sourcePath)) {
                    // Use the copy method to copy the file within S3
                    Storage::disk('s3')->copy($sourcePath, $destinationPath);
                    Storage::disk('s3')->delete($sourcePath);
                    //echo "File copied successfully.";
                } else {
                    //echo "Source file does not exist.";
                }
            } catch (\Exception $e) {
                // Handle errors here
                echo "Error: " . $e->getMessage();
            }

            //Update document with client id and matter id
            $upd_doc_info = \App\Models\Document::find($uploaded_doc_id);
            $upd_doc_info->client_id = $requestData['reassign_sent_client_id'];
            $upd_doc_info->user_id = Auth::user()->id;
            $upd_doc_info->client_matter_id = $requestData['reassign_sent_client_matter_id'];
            $saved_doc_info = $upd_doc_info->save();
            if($saved_doc_info){
                //Update email_logs table with client id and matter id
                $id = $requestData['memail_id'];
                $email_log_info = \App\Models\EmailLog::find($id);
                $email_log_info->client_id = $requestData['reassign_sent_client_id'];
                $email_log_info->user_id = Auth::user()->id;
                $email_log_info->client_matter_id = $requestData['reassign_sent_client_matter_id'];
                $saved_mail_report_info = $email_log_info->save();
                if($saved_mail_report_info){
                    $client_matter_info = \App\Models\ClientMatter::select('client_unique_matter_no')->where('id', '=', $requestData['reassign_sent_client_matter_id'])->first();
                    $subject = 'Sent Email Re-assign';
                    $objs = new \App\Models\ActivitiesLog;
                    $objs->client_id = $requestData['reassign_sent_client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = $dest_doc_client_unique_id.'-'.$client_matter_info['client_unique_matter_no'];
                    $objs->subject = $subject;
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
                }

                //Update date in client matter table
                if( isset($requestData['reassign_sent_client_matter_id']) && $requestData['reassign_sent_client_matter_id'] != ""){
                    $obj1 = \App\Models\ClientMatter::find($requestData['reassign_sent_client_matter_id']);
                    $obj1->updated_at = date('Y-m-d H:i:s');
                    $obj1->save();
                }
            }
            if(!$saved_mail_report_info) {
                return redirect()->back()->with('error', config('constants.server_error'));
            } else {
                return redirect()->back()->with('success', 'Sent email re-assigned successfully');
            }
        } else {
            return redirect()->back()->with('error', config('constants.server_error'));
		}
    }

    //Fetch selected client all matters at assign email to client popup
    public function listAllMattersWRTSelClient(Request $request){ //dd($request->all());
        if( ClientMatter::where('client_id', $request->client_id)->exists()){
            //Fetch All client matters
            $clientMatetrs = ClientMatter::join('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->select('client_matters.id', 'matters.title','client_matters.client_unique_matter_no')
            ->where('client_id', $request->client_id)
            ->get(); //dd($clientMatetrs);
            if( !empty($clientMatetrs) && count($clientMatetrs)>0 ){
                $response['status'] 	= 	true;
                $response['message']	=	'Client matter is successfully fetched.';
                $response['clientMatetrs']	=	$clientMatetrs;
            } else {
                $response['status'] 	= 	false;
                $response['message']	=	'Please try again';
                $response['clientMatetrs']	=	array();
            }
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
            $response['clientMatetrs']	=	array();
        }
        echo json_encode($response);
	}


    public function checkEmail(Request $request)
    {
        $email = $request->input('email');

        // Check if email exists in the database
        $exists = DB::table('client_emails')->where('email', $email)->exists();

        $exists_admin = DB::table('admins')->where('email', $email)->exists();

        if ($exists || $exists_admin) {
            return response()->json(['status' => 'exists']);
        } else {
            return response()->json(['status' => 'available']);
        }
    }

    public function checkContact(Request $request)
    {
        $contact = $request->input('phone');

        // Check if the contact number exists in the client_contacts table
        $exists = DB::table('client_contacts')->where('phone', $contact)->exists();
        $exists_admin = DB::table('admins')->where('phone', $contact)->exists();

        if ($exists || $exists_admin) {
            return response()->json(['status' => 'exists']);
        } else {
            return response()->json(['status' => 'available']);
        }
    }

    //mail preview click update mail_is_read bit
    public function updatemailreadbit(Request $request){ //dd($request->all());
        if( \App\Models\EmailLog::where('id', $request->mail_report_id)->exists()){
            $emailLogInfo = \App\Models\EmailLog::select('mail_is_read')->where('id', $request->mail_report_id)->first();
            //dd($emailLogInfo);
            if( $emailLogInfo ){
                $email_log_info = \App\Models\EmailLog::find($request->mail_report_id);
                $email_log_info->mail_is_read = 1;
                $email_log_info->save();

                $response['status'] 	= 	true;
                $response['message']	=	'Mail is successfully updated';
            } else {
                $response['status'] 	= 	false;
                $response['message']	=	'Please try again';
            }
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
        }
        echo json_encode($response);
	}

    //chatgpt enhance message
    public function enhanceMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            $response = $this->openAiClient->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-3.5-turbo', // or 'gpt-4' if you have access
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a professional email writer. Rewrite the following content in a more professional and polished manner:'
                        ],
                        [
                            'role' => 'user',
                            'content' => $request->message
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            $enhancedMessage = $result['choices'][0]['message']['content'];

            return response()->json(['enhanced_message' => $enhancedMessage]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to enhance message: ' . $e->getMessage()], 500);
        }
    }

    // Filter Inbox emails
    public function filterEmails(Request $request)
    {
        try {
            $client_id = $request->input('client_id');
            $client_matter_id = $request->input('client_matter_id');
            $status = $request->input('status');
            $search = $request->input('search');
            $label_id = $request->input('label_id');

            if (!$client_matter_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Matter ID is required'
                ], 400);
            }

            $query = \App\Models\EmailLog::where('client_matter_id', $client_matter_id)
                ->where('type', 'client')
                ->where('mail_type', 1)
                ->where('conversion_type', 'conversion_email_fetch')
                ->where('mail_body_type', 'inbox')
                ->with(['labels', 'attachments'])
                ->orderBy('created_at', 'DESC');

            if ($status !== null && $status !== '') {
                if ($status == 1) {
                    $query->where('mail_is_read', 1);
                } elseif ($status == 2) {
                    $query->where(function ($q) {
                        $q->where('mail_is_read', 0)
                          ->orWhereNull('mail_is_read');
                    });
                }
            }

            if ($search !== null && $search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                      ->orWhere('message', 'LIKE', "%{$search}%")
                      ->orWhere('from_mail', 'LIKE', "%{$search}%")
                      ->orWhere('to_mail', 'LIKE', "%{$search}%");
                });
            }

            if (!empty($label_id)) {
                $query->whereHas('labels', function ($q) use ($label_id) {
                    $q->where('email_labels.id', $label_id);
                });
            }

            $emails = $query->get();
            $url = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';

            $emails = $emails->map(function ($email) use ($url, $client_id) {
                $DocInfo = \App\Models\Document::select('id','doc_type','myfile','myfile_key','mail_type')
                    ->where('id', $email->uploaded_doc_id)
                    ->first();

                $AdminInfo = \App\Models\Admin::select('client_id')->where('id',$email->client_id)->first();

                $previewUrl = '';
                if ($DocInfo) {
                    if (!empty($DocInfo->myfile_key)) {
                        $previewUrl = $DocInfo->myfile;
                    } else {
                        $previewUrl = $url . $AdminInfo->client_id . '/' . ($DocInfo->doc_type ?? 'mail') . '/' . ($DocInfo->mail_type ?? 'inbox') . '/' . $DocInfo->myfile;
                    }
                }

                // Ensure attachments and labels relationships are loaded
                if (!$email->relationLoaded('attachments')) {
                    $email->load('attachments');
                }
                if (!$email->relationLoaded('labels')) {
                    $email->load('labels');
                }

                // Convert to array to ensure all relationships are properly serialized
                $emailArray = $email->toArray();
                
                // email_logs.attachments JSON column shadows $email->attachments — use getFileAttachmentCollection()
                $attachments = $email->getFileAttachmentCollection();
                if ($attachments->isEmpty()) {
                    $attachments = \App\Models\EmailLogAttachment::where('email_log_id', $email->id)->get();
                }

                if ($attachments->isNotEmpty()) {
                    $emailArray['attachments'] = $attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'mail_report_id' => $attachment->email_log_id,
                            'filename' => $attachment->filename,
                            'display_name' => $attachment->display_name ?? $attachment->filename,
                            'content_type' => $attachment->content_type,
                            'file_path' => $attachment->file_path,
                            's3_key' => $attachment->s3_key,
                            'file_size' => (int) $attachment->file_size,
                            'content_id' => $attachment->content_id,
                            'is_inline' => (bool) $attachment->is_inline, // Ensure boolean for frontend filtering
                            'description' => $attachment->description,
                            'extension' => $attachment->extension,
                        ];
                    })->values()->toArray(); // values() re-indexes the array
                } else {
                    // Ensure attachments key exists even if empty
                    $emailArray['attachments'] = [];
                }
                
                // Add preview_url to the array
                $emailArray['preview_url'] = $previewUrl;
                $emailArray['cc'] = \App\Models\EmailLog::resolveRecipientDisplay($emailArray['cc'] ?? '', $email->type ?? 'client');

                $emailArray = app(MatterEmailBodyCleanupService::class)->appendArchivedBodyMeta($emailArray, $email);
                
                return $emailArray;
            });

            return response()->json($emails, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            Log::error('Error in filterEmails: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching emails: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Super admin only: archive all email bodies for a matter to S3, then clear DB body fields.
     */
    public function sendMatterEmailBodiesToS3(Request $request)
    {
        if ((int) (Auth::user()->role ?? 0) !== 1) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only super admin can perform this action.',
            ], 403);
        }

        $matterId = (int) $request->input('client_matter_id');
        if ($matterId <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Matter ID is required.',
            ], 422);
        }

        $matter = ClientMatter::find($matterId);
        if (!$matter) {
            return response()->json([
                'status' => false,
                'message' => 'Client matter not found.',
            ], 404);
        }

        $service = app(MatterEmailBodyCleanupService::class);

        if (!$service->matterHasBodyContentInDatabase($matterId)) {
            return response()->json([
                'status' => false,
                'already_archived' => true,
                'message' => 'All emails are already moved to S3 from db.',
            ]);
        }

        try {
            $result = $service->sendAllBodiesToS3AndClearForMatter($matterId);

            return response()->json([
                'status' => true,
                'message' => 'Email bodies were sent to S3 and removed from the database for this matter.',
                'archived' => $result['archived'],
                'skipped' => $result['skipped'],
                'cleared' => $result['cleared'],
                'has_body_content' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send matter email bodies to S3', [
                'client_matter_id' => $matterId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send email bodies to S3: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream archived email body HTML from S3 (after super-admin S3 archive action).
     */
    public function viewArchivedEmailBody($id)
    {
        try {
            $emailLog = \App\Models\EmailLog::find($id);
            if (!$emailLog) {
                abort(404, 'Email not found.');
            }

            $service = app(MatterEmailBodyCleanupService::class);
            $s3Key = $service->resolveArchivedBodyS3Key($emailLog);

            if ($s3Key === null) {
                abort(404, 'Archived email body not found.');
            }

            $content = Storage::disk('s3')->get($s3Key);
            if ($content === null || $content === '') {
                abort(404, 'Archived email body is empty.');
            }

            if (Schema::hasColumn('email_logs', 'body_s3_key') && empty($emailLog->body_s3_key)) {
                $emailLog->body_s3_key = $s3Key;
                $emailLog->save();
            }

            return response($content, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to view archived email body', [
                'email_log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to load archived email body.');
        }
    }

    /**
     * Delete an email log (email).
     * Allowed roles: configurable (config/crm.php / CRM_EMAIL_LOG_DELETE_ROLE_IDS); default 1, 12, 16.
     * Accepts DELETE or POST /email-logs/{id}/delete (POST recommended where DELETE is blocked).
     */
    public function deleteEmailLog(Request $request, $id)
    {
        $allowedRoles = config('crm.email_log_delete_role_ids', [1, 12, 16]);
        if (!is_array($allowedRoles) || count($allowedRoles) === 0) {
            $allowedRoles = [1, 12, 16];
        }
        $allowedRoles = array_map('intval', $allowedRoles);

        if (!in_array((int) Auth::user()->role, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Your role cannot delete emails.',
            ], 403);
        }

        try {
            $emailLog = \App\Models\EmailLog::find($id);
            if (!$emailLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            $matterId = $request->input('client_matter_id');
            $clientId = $request->input('client_id');
            if ($matterId !== null && $matterId !== '') {
                if ((int) $emailLog->client_matter_id !== (int) $matterId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email does not belong to the open matter.',
                    ], 403);
                }
            } elseif ($clientId !== null && $clientId !== '') {
                if ((int) $emailLog->client_id !== (int) $clientId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email does not belong to the open client or lead.',
                    ], 403);
                }
            }

            $logClientId = (int) ($emailLog->client_id ?? 0);
            $logMatterId = $emailLog->client_matter_id;
            $attachmentCount = \App\Models\EmailLogAttachment::where('email_log_id', $id)->count();

            $matterRef = null;
            if (!empty($logMatterId)) {
                $matterRef = DB::table('client_matters')
                    ->where('id', $logMatterId)
                    ->value('client_unique_matter_no');
            }

            $snapshot = [
                'subject' => trim((string) ($emailLog->subject ?? '')),
                'from_mail' => (string) ($emailLog->from_mail ?? ''),
                'to_mail' => (string) ($emailLog->to_mail ?? ''),
                'cc' => $emailLog->cc ?? null,
                'mail_type' => $emailLog->mail_type ?? null,
                'record_type' => $emailLog->type ?? null,
            ];

            $staffId = Auth::user()->id ?? Auth::id();

            DB::transaction(function () use ($id, $logClientId, $logMatterId, $matterRef, $staffId, $attachmentCount, $snapshot) {
                DB::table('email_label_email_log')->where('email_log_id', $id)->delete();
                \App\Models\EmailLogAttachment::where('email_log_id', $id)->delete();

                $deletedRows = \App\Models\EmailLog::where('id', $id)->delete();
                if ($deletedRows !== 1) {
                    throw new \RuntimeException('Email log could not be deleted.');
                }

                if ($logClientId <= 0 || !$staffId) {
                    if ($logClientId > 0 && !$staffId) {
                        Log::warning('Email log deleted without activity log: no authenticated staff id', [
                            'email_log_id' => $id,
                            'client_id' => $logClientId,
                        ]);
                    }

                    return;
                }

                $description = $this->buildEmailLogDeletionActivityDescription(
                    (int) $id,
                    $snapshot,
                    $attachmentCount,
                    $matterRef !== null && $matterRef !== '' ? (string) $matterRef : null,
                    $logMatterId !== null ? (int) $logMatterId : null
                );

                $activityAttrs = [
                    'client_id' => $logClientId,
                    'created_by' => $staffId,
                    'subject' => 'Deleted email message',
                    'description' => $description,
                    'activity_type' => 'activity',
                    'task_status' => 0,
                    'pin' => 0,
                    'source' => 'crm_emails',
                ];
                if (!empty($logMatterId)) {
                    $activityAttrs['use_for'] = 'matter';
                }
                ActivitiesLog::create($activityAttrs);
            });

            return response()->json([
                'success' => true,
                'message' => 'Email deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting email log: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email: ' . $e->getMessage(),
            ], 500);
        }
    }

        //Filter Sent emails
    public function filterSentEmails(Request $request)
    {
        try
		{
            $client_id = $request->input('client_id');
            $client_matter_id = $request->input('client_matter_id'); // NEW: Filter by matter
            $type = $request->input('type');
            $status = $request->input('status');
            $search = $request->input('search');

            // Validate input
            if (!$client_matter_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Matter ID is required'
                ], 400);
            }

            // Base query for sent mail - FILTER BY MATTER ID instead of client_id
            $query = \App\Models\EmailLog::where('client_matter_id', $client_matter_id)
                ->where('type', 'client')
                ->where('mail_type', 1)
                ->where(function ($query) {
                    $query->whereNull('conversion_type')
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('conversion_type', 'conversion_email_fetch')
                                ->where('mail_body_type', 'sent');
                        });
                })
                ->with(['labels', 'attachments']) // Load labels and attachments relationships
                ->orderBy('created_at', 'DESC');

            // Filter by type
            if ($type !== '') {
                if ($type == 1) {
                    $query->whereNotNull('conversion_type');
                } elseif ($type == 2) {
                    $query->whereNull('conversion_type');
                }
            }

            // Filter by status
            if ($status !== '') {
                if ($status == 1) {
                    $query->where('mail_is_read', 1);
                } elseif ($status == 2) {
                    $query->where(function ($q) {
                        $q->where('mail_is_read', 0)
                          ->orWhereNull('mail_is_read');
                    });
                }
            }

            // Search filter
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                      ->orWhere('message', 'LIKE', "%{$search}%")
                      ->orWhere('from_mail', 'LIKE', "%{$search}%")
                      ->orWhere('to_mail', 'LIKE', "%{$search}%");
                });
            }

            // Fetch emails
            $emails = $query->get();

            // Base URL for AWS S3
            $url = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';

            // Map emails with additional data (matches filterEmails logic)
            $emails = $emails->map(function ($email) use ($url, $client_id) {
                $previewUrl = '';
                if (!empty($email->uploaded_doc_id)) {
                    $docInfo = \App\Models\Document::select('id', 'doc_type', 'myfile', 'myfile_key', 'mail_type')
                        ->where('id', $email->uploaded_doc_id)
                        ->first();
                    $adminInfo = \App\Models\Admin::select('client_id')->where('id', $email->client_id)->first();
                    if ($docInfo) {
                        if (!empty($docInfo->myfile_key)) {
                            $previewUrl = $docInfo->myfile;
                        } else {
                            $clientRef = ($adminInfo && $adminInfo->client_id) ? $adminInfo->client_id : ('client_' . ($email->client_id ?? $client_id ?? 0));
                            $previewUrl = $url . $clientRef . '/' . ($docInfo->doc_type ?? 'mail') . '/' . ($docInfo->mail_type ?? 'sent') . '/' . ($docInfo->myfile ?? '');
                        }
                    }
                }

				// Ensure attachments and labels relationships are loaded
				if (!$email->relationLoaded('attachments')) {
					$email->load('attachments');
				}
				if (!$email->relationLoaded('labels')) {
					$email->load('labels');
				}

				// Convert to array to ensure all relationships are properly serialized
				$emailArray = $email->toArray();
				
				$attachments = $email->getFileAttachmentCollection();
				if ($attachments->isEmpty()) {
					$attachments = \App\Models\EmailLogAttachment::where('email_log_id', $email->id)->get();
				}

				if ($attachments->isNotEmpty()) {
					$emailArray['attachments'] = $attachments->map(function ($attachment) {
						return [
							'id' => $attachment->id,
							'mail_report_id' => $attachment->email_log_id,
							'filename' => $attachment->filename,
							'display_name' => $attachment->display_name ?? $attachment->filename,
							'content_type' => $attachment->content_type,
							'file_path' => $attachment->file_path,
							's3_key' => $attachment->s3_key,
							'file_size' => (int) $attachment->file_size,
							'content_id' => $attachment->content_id,
							'is_inline' => (bool) $attachment->is_inline, // Ensure boolean for frontend filtering
							'description' => $attachment->description,
							'extension' => $attachment->extension,
						];
					})->values()->toArray(); // values() re-indexes the array
				} else {
					// Ensure attachments key exists even if empty
					$emailArray['attachments'] = [];
				}
				
				// Add preview_url and ensure required fields have defaults
				$emailArray['preview_url'] = $previewUrl;
				$emailArray['from_mail'] = $emailArray['from_mail'] ?? '';
				$emailArray['to_mail'] = \App\Models\EmailLog::resolveRecipientDisplay($emailArray['to_mail'] ?? '', $email->type ?? null);
				$emailArray['cc'] = \App\Models\EmailLog::resolveRecipientDisplay($emailArray['cc'] ?? '', $email->type ?? null);
				$emailArray['subject'] = $emailArray['subject'] ?? '';
				$emailArray['message'] = $emailArray['message'] ?? '';

				$emailArray = app(MatterEmailBodyCleanupService::class)->appendArchivedBodyMeta($emailArray, $email);
				
				return $emailArray;
			});

			return response()->json($emails, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		} catch (\Exception $e) {
			Log::error('Error in filterSentEmails: ' . $e->getMessage(), [
				'request' => $request->all(),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'status' => 'error',
				'message' => 'An error occurred while fetching emails'
			], 500);
		}
	}

    /**
     * Filter emails for a lead (no matter context).
     * Returns CRM-sent emails to the lead.
     */
    public function filterLeadEmails(Request $request)
    {
        try {
            $client_id = $request->input('client_id'); // lead id
            $status = $request->input('status');
            $search = $request->input('search');
            $label_id = $request->input('label_id');

            if (!$client_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lead ID is required'
                ], 400);
            }

            $query = \App\Models\EmailLog::where('client_id', $client_id)
                ->where('type', 'lead')
                ->where('mail_type', 1)
                ->where(function ($q) {
                    $q->whereNull('conversion_type')
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('conversion_type', 'conversion_email_fetch')
                                ->where('mail_body_type', 'sent');
                        });
                })
                ->with(['labels', 'attachments'])
                ->orderBy('created_at', 'DESC');

            if ($status !== null && $status !== '') {
                if ($status == 1) {
                    $query->where('mail_is_read', 1);
                } elseif ($status == 2) {
                    $query->where(function ($q) {
                        $q->where('mail_is_read', 0)->orWhereNull('mail_is_read');
                    });
                }
            }

            if ($search !== null && $search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                      ->orWhere('message', 'LIKE', "%{$search}%")
                      ->orWhere('from_mail', 'LIKE', "%{$search}%")
                      ->orWhere('to_mail', 'LIKE', "%{$search}%");
                });
            }

            if (!empty($label_id)) {
                $query->whereHas('labels', function ($q) use ($label_id) {
                    $q->where('email_labels.id', $label_id);
                });
            }

            $emails = $query->get();
            $url = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';

            $emails = $emails->map(function ($email) use ($url, $client_id) {
                $previewUrl = '';
                if (!empty($email->uploaded_doc_id)) {
                    $docInfo = \App\Models\Document::select('id', 'doc_type', 'myfile', 'myfile_key', 'mail_type')
                        ->where('id', $email->uploaded_doc_id)->first();
                    if ($docInfo) {
                        if (!empty($docInfo->myfile_key)) {
                            $previewUrl = $docInfo->myfile;
                        } else {
                            $adminInfo = \App\Models\Admin::withoutGlobalScopes()->select('client_id')->where('id', $email->client_id)->first();
                            $clientRef = $adminInfo && $adminInfo->client_id ? $adminInfo->client_id : ('client_' . $client_id);
                            $previewUrl = $url . $clientRef . '/' . ($docInfo->doc_type ?? 'mail') . '/' . ($docInfo->mail_type ?? 'sent') . '/' . ($docInfo->myfile ?? '');
                        }
                    }
                }

                if (!$email->relationLoaded('attachments')) {
                    $email->load('attachments');
                }
                if (!$email->relationLoaded('labels')) {
                    $email->load('labels');
                }

                $emailArray = $email->toArray();
                $attachments = $email->getFileAttachmentCollection();
                if ($attachments->isEmpty()) {
                    $attachments = \App\Models\EmailLogAttachment::where('email_log_id', $email->id)->get();
                }
                $emailArray['attachments'] = $attachments->isNotEmpty()
                    ? $attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'mail_report_id' => $attachment->email_log_id,
                            'filename' => $attachment->filename,
                            'display_name' => $attachment->display_name ?? $attachment->filename,
                            'content_type' => $attachment->content_type,
                            'file_path' => $attachment->file_path,
                            's3_key' => $attachment->s3_key,
                            'file_size' => (int) $attachment->file_size,
                            'content_id' => $attachment->content_id,
                            'is_inline' => (bool) $attachment->is_inline,
                            'description' => $attachment->description,
                            'extension' => $attachment->extension,
                        ];
                    })->values()->toArray()
                    : [];
                $emailArray['preview_url'] = $previewUrl;
                $emailArray['from_mail'] = $emailArray['from_mail'] ?? '';
                $emailArray['to_mail'] = \App\Models\EmailLog::resolveRecipientDisplay($emailArray['to_mail'] ?? '', $email->type ?? 'lead');
                $emailArray['cc'] = \App\Models\EmailLog::resolveRecipientDisplay($emailArray['cc'] ?? '', $email->type ?? 'lead');
                $emailArray['subject'] = $emailArray['subject'] ?? '';
                $emailArray['message'] = $emailArray['message'] ?? '';
                return $emailArray;
            });

            return response()->json($emails, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Exception $e) {
            Log::error('Error in filterLeadEmails: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching lead emails',
            ], 500);
        }
    }

     //Seach Client Relationship

    // OLD HTTP DOWNLOAD METHOD - COMMENTED OUT
    // public function download_document(Request $request)
    // {
    //     $fileUrl = $request->input('filelink');
    //     $filename = $request->input('filename', 'downloaded.pdf');

    //     if (!$fileUrl) {
    //         return abort(400, 'Missing file URL');
    //     }
      
    //     // Increase execution time for large files
    //     set_time_limit(900);

    //     // Increase HTTP client timeout
    //     $response = Http::timeout(120)->get($fileUrl);

    //     if (!$response->successful()) {
    //         return abort(404, 'File not found');
    //     }

    //     return response($response->body())
    //         ->header('Content-Type', 'application/pdf')
    //         ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    // }

    //Generate agreemnt
    public function generateagreement(Request $request)
    {
        try { //dd($request->all());
            $id = $request->client_id;
            $client = Admin::findOrFail($request->client_id);
            $responsiblePerson = \App\Models\Staff::findOrFail($request->agent_id); //dd($responsiblePerson);
            if (!$responsiblePerson) {
                return response()->json([
                    'success' => false,
                    'error' => 'No responsible person found in the database.',
                    'message' => 'No responsible person found in the database.'
                ], 400);
            }

            // Ensure templates directory exists
            $templatesDir = storage_path('app/templates');
            if (!file_exists($templatesDir)) {
                mkdir($templatesDir, 0755, true);
                Log::info('Created templates directory: ' . $templatesDir);
            }
            
            // Determine template filename (company / skill / conflict / default paths + legacy fallbacks)
            $matterNickName = null;
            $templateResolution = app(VisaAgreementTemplateResolver::class)->resolve(
                $client,
                isset($request->client_matter_id) ? (string) $request->client_matter_id : null
            );
            $matterNickName = $templateResolution['matter_nick_name'];
            $templateCandidates = $templateResolution['candidates'];
            Log::info('Agreement template resolution', [
                'rule' => $templateResolution['rule'],
                'candidates' => $templateCandidates,
                'client_id' => $client->id,
                'matter_nick' => $matterNickName,
            ]);

            $templateFileName = config('visa_agreement_templates.default', 'Service_Agreement_general.docx');
            $templatePath = storage_path('app/templates/' . $templateFileName);
            foreach ($templateCandidates as $candidateBasename) {
                $candidatePath = storage_path('app/templates/' . $candidateBasename);
                if (file_exists($candidatePath)) {
                    $templateFileName = $candidateBasename;
                    $templatePath = $candidatePath;
                    break;
                }
            }

            if (!file_exists($templatePath)) {
                Log::error('Agreement template file not found at: ' . $templatePath);
                // Try fallback to default template if specific template doesn't exist
                $defaultTemplatePath = storage_path('app/templates/agreement_template.docx');
                if (file_exists($defaultTemplatePath)) {
                    $templatePath = $defaultTemplatePath;
                    $templateFileName = 'agreement_template.docx';
                    Log::info('Using default template as fallback. Matter type: ' . ($matterNickName ?? 'unknown'));
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Template file not found.',
                        'message' => 'The agreement template file (' . $templateFileName . ') is missing. Please ensure the template file is placed at: storage/app/templates/' . $templateFileName,
                        'template_path' => $templatePath,
                        'help' => 'Contact your system administrator to upload the agreement template file.'
                    ], 404);
                }
            } else {
                Log::info('Using template: ' . $templateFileName . ' for matter type: ' . ($matterNickName ?? 'default') . ' (rule: ' . ($templateResolution['rule'] ?? '') . ')');
            }

            // Option 2: Patch template so "Amount incl Surcharge" total cell uses TotalDoHAChargesInclSurcharge
            // (replaces last occurrence of ${TotalDoHASurcharges} in word/document.xml to fix the total display)
            $pathToLoad = $templatePath;
            $patchedTempPath = null; // used for cleanup after saveAs; set only when patch is applied
            $scheduleATempPath = null; // working copy so Schedule A XML injection never mutates the stored template
            try {
                $tempDir = storage_path('app/temp');
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
                $patchedTempPath = $tempDir . '/agreement_patch_' . getmypid() . '_' . time() . '.docx';
                if (@copy($templatePath, $patchedTempPath)) {
                    $zip = new \ZipArchive();
                    if ($zip->open($patchedTempPath) === true) {
                        $xml = $zip->getFromName('word/document.xml');
                        if ($xml !== false) {
                            $xmlPatchesApplied = false;
                            $isCompanyAgreementTemplate = CompanyAgreementDocxPatcher::isCompanyAgreementTemplate($templateFileName);

                            if (! $isCompanyAgreementTemplate) {
                            $oldPlaceholder = '${TotalDoHASurcharges}';
                            $newPlaceholder = '${TotalDoHAChargesInclSurcharge}';
                            $countFullPlaceholder = substr_count($xml, $oldPlaceholder);
                            $countBareName = substr_count($xml, 'TotalDoHASurcharges');
                            // Try full placeholder first (PhpWord format)
                            $lastPos = strrpos($xml, $oldPlaceholder);
                            if ($lastPos !== false) {
                                $xml = substr_replace($xml, $newPlaceholder, $lastPos, strlen($oldPlaceholder));
                                $xmlPatchesApplied = true;
                                Log::info('[AgreementMacro:TotalDoHASurcharges] DOCX patch REPLACED last exact placeholder with TotalDoHAChargesInclSurcharge', [
                                    'client_id' => $request->client_id,
                                    'client_matter_id' => $request->client_matter_id ?? null,
                                    'occurrences_${TotalDoHASurcharges}' => $countFullPlaceholder,
                                    'occurrences_substring_TotalDoHASurcharges' => $countBareName,
                                    'last_match_byte_offset' => $lastPos,
                                    'patch_mode' => 'full_placeholder',
                                    'note' => 'Clause 4 cell will merge TotalDoHAChargesInclSurcharge (charges + surcharges), same as TotalDoHASurcharges.',
                                ]);
                            } else {
                                // Word may split placeholder across XML runs; try name only (last occurrence)
                                $oldName = 'TotalDoHASurcharges';
                                $newName = 'TotalDoHAChargesInclSurcharge';
                                $lastPos = strrpos($xml, $oldName);
                                if ($lastPos !== false) {
                                    $xml = substr_replace($xml, $newName, $lastPos, strlen($oldName));
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:TotalDoHASurcharges] DOCX patch REPLACED last bare name with TotalDoHAChargesInclSurcharge', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'occurrences_${TotalDoHASurcharges}' => $countFullPlaceholder,
                                        'occurrences_substring_TotalDoHASurcharges' => $countBareName,
                                        'last_match_byte_offset' => $lastPos,
                                        'patch_mode' => 'bare_name_only',
                                        'note' => 'Clause 4 cell will merge TotalDoHAChargesInclSurcharge (charges + surcharges), same as TotalDoHASurcharges.',
                                    ]);
                                }
                            }
                            if (! $xmlPatchesApplied) {
                                Log::info('[AgreementMacro:TotalDoHASurcharges] DOCX patch skipped (no TotalDoHASurcharges found in document.xml)', [
                                    'client_id' => $request->client_id,
                                    'client_matter_id' => $request->client_matter_id ?? null,
                                    'occurrences_${TotalDoHASurcharges}' => $countFullPlaceholder,
                                    'occurrences_substring_TotalDoHASurcharges' => $countBareName,
                                ]);
                            }
                            }

                            if ($isCompanyAgreementTemplate) {
                                $companyPatch = app(CompanyAgreementDocxPatcher::class)->patchDocumentXml($xml, $templateFileName);
                                $xml = $companyPatch['xml'];
                                if ($companyPatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:Company] DOCX patch applied to company agreement template', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                        'fixes' => $companyPatch['fixes'],
                                    ]);
                                }
                            }

                            if (VisaAgreementAmountTablePatcher::supportsTemplate($templateFileName)) {
                                $amountPatch = app(VisaAgreementAmountTablePatcher::class)->patchDocumentXml($xml);
                                $xml = $amountPatch['xml'];
                                if ($amountPatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:AmountAlignment] DOCX patch aligned fee and charge amount cells', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if (PsaAgreementSection4SummaryTablePatcher::supportsTemplate($templateFileName)) {
                                $psaSection4Patch = app(PsaAgreementSection4SummaryTablePatcher::class)->patchDocumentXml($xml);
                                $xml = $psaSection4Patch['xml'];
                                if ($psaSection4Patch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:Section4] DOCX patch fixed PSA authority charges amount cell', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if (VisaAgreementServiceTypeRowPatcher::supportsTemplate($templateFileName)) {
                                $serviceTypePatch = app(VisaAgreementServiceTypeRowPatcher::class)->patchDocumentXml($xml);
                                $xml = $serviceTypePatch['xml'];
                                if ($serviceTypePatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:ServiceType] DOCX patch right-aligned Subclass on service type row', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if (PsaAgreementServiceTypeRowPatcher::supportsTemplate($templateFileName)) {
                                $psaServiceTypePatch = app(PsaAgreementServiceTypeRowPatcher::class)->patchDocumentXml($xml);
                                $xml = $psaServiceTypePatch['xml'];
                                if ($psaServiceTypePatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:ServiceType] DOCX patch right-aligned Stream on PSA service type row', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if ($templateFileName === 'Service_Agreement_Job_Ready.docx') {
                                $jobReadyPatch = app(JobReadyAgreementFeeTablePatcher::class)->patchDocumentXml($xml);
                                $xml = $jobReadyPatch['xml'];
                                if ($jobReadyPatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:JobReady] DOCX patch aligned professional fee, Section 4 summary, and authority stage charge amount cells', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                    ]);
                                }
                            }

                            if ($xmlPatchesApplied) {
                                $zip->deleteName('word/document.xml');
                                $zip->addFromString('word/document.xml', $xml);
                                $zip->close();
                                $pathToLoad = $patchedTempPath;
                                Log::info('Patched agreement template working copy before PhpWord merge');
                            } else {
                                $zip->close();
                            }
                        } else {
                            $zip->close();
                        }
                    }
                    if ($pathToLoad === $templatePath && file_exists($patchedTempPath)) {
                        @unlink($patchedTempPath);
                        $patchedTempPath = null;
                    }
                }
            } catch (\Throwable $patchEx) {
                Log::warning('Template patch skipped: ' . $patchEx->getMessage());
                $pathToLoad = $templatePath;
                if ($patchedTempPath && file_exists($patchedTempPath)) {
                    @unlink($patchedTempPath);
                    $patchedTempPath = null;
                }
            }

            if ($pathToLoad === $templatePath && is_file($pathToLoad)) {
                $tempDir = storage_path('app/temp');
                if (! is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
                $scheduleATempPath = $tempDir . '/agreement_schedule_a_' . getmypid() . '_' . time() . '.docx';
                if (@copy($pathToLoad, $scheduleATempPath)) {
                    $pathToLoad = $scheduleATempPath;
                } else {
                    $scheduleATempPath = null;
                    Log::warning('Could not copy agreement template for Schedule A injection; skipping placeholder injection to protect original file.');
                }
            }

            if ($pathToLoad !== $templatePath) {
                $this->injectScheduleAFamilyPlaceholdersIfNeeded($pathToLoad);
            }

            $templateProcessor = new TemplateProcessor($pathToLoad);

            try {
                $tplVars = $templateProcessor->getVariables();
                $tplHasTdhs = in_array('TotalDoHASurcharges', $tplVars, true);
                $tplHasTdhcis = in_array('TotalDoHAChargesInclSurcharge', $tplVars, true);
                Log::info('[AgreementMacro:TotalDoHASurcharges] PhpWord template variables after load', [
                    'client_id' => $request->client_id,
                    'client_matter_id' => $request->client_matter_id ?? null,
                    'template_loaded_from_patched_copy' => ($pathToLoad !== $templatePath),
                    'has_placeholder_TotalDoHASurcharges' => $tplHasTdhs,
                    'has_placeholder_TotalDoHAChargesInclSurcharge' => $tplHasTdhcis,
                    'total_distinct_placeholders' => count($tplVars),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[AgreementMacro:TotalDoHASurcharges] Could not read template variables: ' . $e->getMessage());
            }

            // Log the values we're trying to set
            Log::info('Generating document for client: ' . $client->client_id);
            Log::info('Template path: ' . $templatePath);

            $dobFormated = 'NA';
            if($client->dob != ''){
                $dobArr = explode('-',$client->dob);
                if(!empty($dobArr)){
                    $dobFormated = $dobArr[2].'/'.$dobArr[1].'/'.$dobArr[0];
                } else{
                    $dobFormated = 'NA';
                }
            }

            // Try to find client address
            $addressRow = null;
            $address_record_cnt = DB::table('client_addresses')->where('client_id', $id)->count();
            if ($address_record_cnt > 0) {
                $addressRow = DB::table('client_addresses')->where('client_id', $id)->where('is_current', 1)->first();
                if ($addressRow === null) {
                    $addressRow = DB::table('client_addresses')->where('client_id', $id)->orderByRaw(ClientAddress::ORDER_BY_DISPLAY_SQL)->orderByDesc('id')->first();
                }
            }

            $addressMacros = app(VisaAgreementApplicantAddressResolver::class)->resolveForTemplate($addressRow, $templateFileName);
            $client_address = $addressMacros['street'];
            $client_zip = $addressMacros['postcode'];

            //Get client matter info
            $visa_subclass = '';
            $visa_stream = '';
            $professional_fee = 0;
            $gst_fee = 0;
            $visa_application_charge = 0;

            $Block_1_Description = '';
            $Block_1_Ex_Tax = 0;
            $Block_2_Description = '';
            $Block_2_Ex_Tax = 0;
            $Block_3_Description = '';
            $Block_3_Ex_Tax = 0;

            $Blocktotalfeesincltax = 0;

            $DoHAMainApplicantChargePersonCount = 0;
            $DoHAMainApplicantCharge = 0;
            $DoHAMainApplicantSurcharge = 0;

            $DoHAAdditionalApplicantCharge18PlusPersonCount = 0;
            $DoHAAdditionalApplicantCharge18Plus = 0;
            $DoHAAdditional18PlusSurcharge = 0;

            $DoHAAdditionalApplicantChargeUnder18PersonCount = 0;
            $DoHAAdditionalApplicantChargeUnder18 = 0;
            $DoHAAdditionalUnder18Surcharge = 0;

            $DoHASecondInstalmentMainPersonCount = 0;
            $DoHASecondInstalmentMain = 0;
            $DoHASecondInstalmentMainSurcharge = 0;

            $DoHASubsequentApplicantCharge18PlusPersonCount = 0;
            $DoHASubsequentApplicantCharge18Plus = 0;
            $DoHASubsequentApplicantCharge18PlusSurcharge = 0;

            $DoHASubsequentApplicantChargeUnder18PersonCount = 0;
            $DoHASubsequentTempAppCharge = 0;
            $DoHASubsequentTempAppSurcharge = 0;

            $DoHANonInternetChargePersonCount = 0;
            $DoHANonInternetCharge = 0;
            $DoHANonInternetSurcharge = 0;

            $TotalDoHACharges = 0;
            $TotalDoHASurcharges = 0;
            $TotalDoHAChargesInclSurcharge = '0.00';
            $TotalEstimatedOtherCosts = 0;
            $GrandTotalFeesAndCosts = 0;
            $GrandTotalFeesAndCostsFormated = '0.00';
            $BlocktotalfeesincltaxFormated = '0.00';

            if( isset($request->client_matter_id) && $request->client_matter_id != '' )
            {  //dd($request->client_matter_id);
                //First check cost is assigned for this matter wrt client or not
                $cost_assignment_cnt = \App\Models\CostAssignmentForm::where('client_id',$request->client_id)->where('client_matter_id',$request->client_matter_id)->count();
	            if($cost_assignment_cnt >0)
                { //dd('iff');
                    // Get cost assignment form fee info
                    $matter_info = DB::table('cost_assignment_forms')->where('client_id', $request->client_id)->where('client_matter_id', $request->client_matter_id)->first();

                    $client_matter_info = DB::table('client_matters')->select('sel_matter_id')->where('id', $request->client_matter_id)->first();
                    // Get matter info
                    if( $client_matter_info ){ //dd($client_matter_info);
                        $matter_info_arr = DB::table('matters')->select('title','nick_name','Block_1_Description','Block_2_Description','Block_3_Description')->where('id', $client_matter_info->sel_matter_id )->first();
                    }
                    if( $matter_info_arr ) {
                        $matter_info->title = $matter_info_arr->title ?? '';
                        $matter_info->nick_name = $matter_info_arr->nick_name ?? '';
                        $matter_info->Block_1_Description = $matter_info_arr->Block_1_Description ?? '';
                        $matter_info->Block_2_Description = $matter_info_arr->Block_2_Description ?? '';
                        $matter_info->Block_3_Description = $matter_info_arr->Block_3_Description ?? '';
                    }

                }
                else
                { //dd('elsee');
                    $client_matter_info = DB::table('client_matters')->select('sel_matter_id')->where('id', $request->client_matter_id)->first();
                    // Get matter info
                    if( $client_matter_info ){ //dd($client_matter_info);
                        $matter_info = DB::table('matters')->where('id', $client_matter_info->sel_matter_id )->first();
                    }
                }

                if ($matter_info)
                { //dd($matter_info);

                    $visa_subclass = $matter_info->title ?? '';
                    $visa_stream = $matter_info->nick_name ?? '';

                    //$professional_fee = $matter_info->our_fee;
                    //$gst_fee = 0;
                    //$visa_application_charge = $matter_info->main_applicant_fee;

                    $Block_1_Description = $matter_info->Block_1_Description ?? '';
                    $Block_1_Ex_Tax = $matter_info->Block_1_Ex_Tax ?? 0;

                    $Block_2_Description = $matter_info->Block_2_Description ?? '';
                    $Block_2_Ex_Tax = $matter_info->Block_2_Ex_Tax ?? 0;

                    $Block_3_Description = $matter_info->Block_3_Description ?? '';
                    $Block_3_Ex_Tax = $matter_info->Block_3_Ex_Tax ?? 0;

                    $Blocktotalfeesincltax = floatval($Block_1_Ex_Tax) + floatval($Block_2_Ex_Tax) + floatval($Block_3_Ex_Tax);
                    $BlocktotalfeesincltaxFormated = number_format($Blocktotalfeesincltax, 2, '.', '');
                    //dd($BlocktotalfeesincltaxFormated);

                    $surchargeApply = property_exists($matter_info, 'surcharge') ? $matter_info->surcharge : null;

                    $DoHAMainApplicantChargePersonCount = ($matter_info->Dept_Base_Application_Charge_no_of_person ?? 0) ."Person" ;
                    $DoHAMainApplicantCharge = $matter_info->Dept_Base_Application_Charge_after_person ?? 0;
                    $DoHAMainApplicantSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Base_Application_Charge_after_person ?? 0),
                        $matter_info->Dept_Base_Application_Charge_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHAAdditionalApplicantCharge18PlusPersonCount = ($matter_info->Dept_Additional_Applicant_Charge_18_Plus_no_of_person ?? 0) ."Person" ;
                    $DoHAAdditionalApplicantCharge18Plus = $matter_info->Dept_Additional_Applicant_Charge_18_Plus_after_person ?? 0;
                    $DoHAAdditional18PlusSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Additional_Applicant_Charge_18_Plus_after_person ?? 0),
                        $matter_info->Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHAAdditionalApplicantChargeUnder18PersonCount = ($matter_info->Dept_Additional_Applicant_Charge_Under_18_no_of_person ?? 0) ."Person" ;
                    $DoHAAdditionalApplicantChargeUnder18 = $matter_info->Dept_Additional_Applicant_Charge_Under_18_after_person ?? 0;
                    $DoHAAdditionalUnder18Surcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Additional_Applicant_Charge_Under_18_after_person ?? 0),
                        $matter_info->Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHASecondInstalmentMainPersonCount = ($matter_info->Dept_Subsequent_Temp_Application_Charge_no_of_person ?? 0) ."Person" ;
                    $DoHASecondInstalmentMain = $matter_info->Dept_Subsequent_Temp_Application_Charge_after_person ?? 0;
                    $DoHASecondInstalmentMainSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Subsequent_Temp_Application_Charge_after_person ?? 0),
                        $matter_info->Dept_Subsequent_Temp_Application_Charge_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHASubsequentApplicantCharge18PlusPersonCount = ($matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person ?? 0) ."Person" ;
                    $DoHASubsequentApplicantCharge18Plus = $matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person ?? 0;
                    $DoHASubsequentApplicantCharge18PlusSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person ?? 0),
                        $matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHASubsequentApplicantChargeUnder18PersonCount = ($matter_info->Dept_Second_VAC_Instalment_Under_18_no_of_person ?? 0) ."Person" ;
                    $DoHASubsequentTempAppCharge = $matter_info->Dept_Second_VAC_Instalment_Under_18_after_person ?? 0;
                    $DoHASubsequentTempAppSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Second_VAC_Instalment_Under_18_after_person ?? 0),
                        $matter_info->Dept_Second_VAC_Instalment_Under_18_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHANonInternetChargePersonCount = ($matter_info->Dept_Non_Internet_Application_Charge_no_of_person ?? 0) ."Person" ;
                    $DoHANonInternetCharge = $matter_info->Dept_Non_Internet_Application_Charge_after_person ?? 0;
                    $DoHANonInternetSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Non_Internet_Application_Charge_after_person ?? 0),
                        $matter_info->Dept_Non_Internet_Application_Charge_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $TotalDoHACharges = $matter_info->TotalDoHACharges ?? 0;
                    $TotalDoHASurcharges = $matter_info->TotalDoHASurcharges ?? 0;
                    // Section 4 "Total DOHA charges Inc Surcharges" = Total DoHA Charges + Total DoHA Surcharges only
                    $TotalDoHAChargesInclSurcharge = CompanyVisaAgreementMacroBuilder::calculateDohaChargesInclSurcharges(
                        floatval($TotalDoHACharges),
                        floatval($TotalDoHASurcharges)
                    );

                    $TotalEstimatedOtherCosts = $matter_info->additional_fee_1 ?? 0;
                    // Total Fees, Charges & Costs = professional fees + DoHA charges (incl surcharge) + estimated costs
                    $GrandTotalFeesAndCosts = floatval($Blocktotalfeesincltax) + floatval($TotalDoHAChargesInclSurcharge) + floatval($TotalEstimatedOtherCosts);
                    $GrandTotalFeesAndCostsFormated = number_format($GrandTotalFeesAndCosts, 2, '.', '');
                }
            }

            // ${TotalDoHASurcharges} / ${TotalDoHAChargesInclSurcharge}: Total DoHA Charges + Total DoHA Surcharges only
            // (Additional Fee 1 is merged via ${TotalEstimatedOthCosts}.)
            $TotalDoHASurchargesMacroSum = '0.00';
            $costRowForMacro = null;
            if (isset($request->client_matter_id) && $request->client_matter_id !== '') {
                $costRowForMacro = DB::table('cost_assignment_forms')
                    ->where('client_id', $request->client_id)
                    ->where('client_matter_id', $request->client_matter_id)
                    ->first();
                if ($costRowForMacro !== null) {
                    $dbBase = floatval($costRowForMacro->TotalDoHACharges ?? 0);
                    $dbSurchargeOnly = floatval($costRowForMacro->TotalDoHASurcharges ?? 0);
                    $TotalDoHASurchargesMacroSum = CompanyVisaAgreementMacroBuilder::calculateDohaChargesInclSurcharges(
                        $dbBase,
                        $dbSurchargeOnly
                    );
                    $TotalDoHAChargesInclSurcharge = $TotalDoHASurchargesMacroSum;
                    $GrandTotalFeesAndCostsFormated = CompanyVisaAgreementMacroBuilder::calculateGrandTotalFeesAndCosts(
                        floatval($Blocktotalfeesincltax),
                        $TotalDoHASurchargesMacroSum,
                        floatval($TotalEstimatedOtherCosts)
                    );
                    Log::info('[AgreementMacro:TotalDoHASurcharges] DoHA incl surcharge (charges + surcharges only)', [
                        'client_id' => $request->client_id,
                        'client_matter_id' => $request->client_matter_id,
                        'template' => $templateFileName,
                        'TotalDoHACharges' => $dbBase,
                        'TotalDoHASurcharges_surcharge_only' => $dbSurchargeOnly,
                        'macro_TotalDoHASurcharges_merge_value' => $TotalDoHASurchargesMacroSum,
                        'GrandTotalFeesAndCosts' => $GrandTotalFeesAndCostsFormated,
                    ]);
                } else {
                    Log::warning('[AgreementMacro:TotalDoHASurcharges] No cost_assignment_forms row for client/matter — macro stays 0.00', [
                        'client_id' => $request->client_id,
                        'client_matter_id' => $request->client_matter_id,
                    ]);
                }
            } else {
                Log::warning('[AgreementMacro:TotalDoHASurcharges] Missing client_matter_id — macro stays 0.00', [
                    'client_id' => $request->client_id,
                ]);
            }

            $scheduleAFamily = $this->buildScheduleAFamilyMacroStrings((int) $id);

            // Replace placeholders
            $replacements = [
                'ClientID' => $client->client_id,
                'ApplicantGivenNames' => $client->first_name,
                'ApplicantSurname' => $client->last_name,
                'ApplicantDOB' => $dobFormated,
                'ApplicantResidentialAddressStreet1and2' => $client_address,
                'ApplicantResidentialAddressPostcode' => $client_zip,
                //'ApplicantResidentialAddressSuburbAndTown' => '',
                //'ApplicantResidentialAddressState' => '',
                //'ApplicantResidentialAddressCountry' => '',
                'Contact_ContactEmail' => $client->email,
                'Contact_ContactMobile' => $client->phone ?? '',
                'ApplicantHomePhone_Number' => $client->phone ?? '',

                'VisaApplyingFor' => $visa_subclass,
                'VisaApplyingForStream' => $visa_stream,

                'Block1IncTax' => number_format($professional_fee, 2),
                'TotalAgentFeeGST' => number_format($gst_fee ?? 0, 2),
                'TotalAgentFeeIncTax' => number_format($professional_fee + ($gst_fee ?? 0), 2),
                'BaseApplicationCharge' => number_format($visa_application_charge, 2),
                'DOHABaseApplicationChargeIncCCSurcharge' => number_format($visa_application_charge, 2),

                'AgentName' => $responsiblePerson->first_name,
                'AgentSurName' => $responsiblePerson->last_name,
                'AgentTitle' => $responsiblePerson->company_name,
                'MARN' => $responsiblePerson->marn_number,

                'Visa_apply' => $visa_subclass,
                'visa_apply' => $visa_subclass,

                'Block1description'=>$Block_1_Description,
                'Block1feesincltax'=>$Block_1_Ex_Tax,
                'Block2description'=>$Block_2_Description,
                'Block2feesincltax'=>$Block_2_Ex_Tax,
                'Block3description'=>$Block_3_Description,
                'Block3feesincltax'=>$Block_3_Ex_Tax,
                'Blocktotalfeesincltax'=>$BlocktotalfeesincltaxFormated,

                'DoHAMainApplicantChargePersonCount'=>$DoHAMainApplicantChargePersonCount,
                'DoHAMainApplicantCharge'=>$DoHAMainApplicantCharge,
                'DoHAMainApplicantSurcharge'=>$DoHAMainApplicantSurcharge,

                'DoHAAdditionalApplicantCharge18PlusPersonCount'=>$DoHAAdditionalApplicantCharge18PlusPersonCount,
                'DoHAAdditionalApplicantCharge18Plus'=>$DoHAAdditionalApplicantCharge18Plus,
                'DoHAAdditional18PlusSurcharge'=>$DoHAAdditional18PlusSurcharge,

                'DoHAAdditionalApplicantChargeUnder18PersonCount'=>$DoHAAdditionalApplicantChargeUnder18PersonCount,
                'DoHAAdditionalApplicantChargeUnder18'=>$DoHAAdditionalApplicantChargeUnder18,
                'DoHAAdditionalUnder18Surcharge'=>$DoHAAdditionalUnder18Surcharge,

                'DoHASecondInstalmentMainPersonCount'=>$DoHASecondInstalmentMainPersonCount,
                'DoHASecondInstalmentMain'=>$DoHASecondInstalmentMain,
                'DoHASecondInstalmentMainSurcharge'=>$DoHASecondInstalmentMainSurcharge,

                'DoHASubsequentApplicantCharge18PlusPersonCount'=>$DoHASubsequentApplicantCharge18PlusPersonCount,
                'DoHASubsequentApplicantCharge18Plus'=>$DoHASubsequentApplicantCharge18Plus,
                'DoHASubsequentApplicantCharge18PlusSurcharge'=>$DoHASubsequentApplicantCharge18PlusSurcharge,

                'DoHASubsequentApplicantChargeUnder18PersonCount'=>$DoHASubsequentApplicantChargeUnder18PersonCount,
                'DoHASubsequentTempAppCharge'=>$DoHASubsequentTempAppCharge,
                'DoHASubsequentTempAppSurcharge'=>$DoHASubsequentTempAppSurcharge,

                'DoHANonInternetChargePersonCount'=>$DoHANonInternetChargePersonCount,
                'DoHANonInternetCharge'=>$DoHANonInternetCharge,
                'DoHANonInternetSurcharge'=>$DoHANonInternetSurcharge,

                'TotalDoHACharges'=>$TotalDoHACharges,
                'TotalDoHASurcharges'=>$TotalDoHASurchargesMacroSum,
                'TotalDoHAChargesInclSurcharge'=>$TotalDoHAChargesInclSurcharge,

                'TotalEstimatedOthCosts'=>$TotalEstimatedOtherCosts,
                'GrandTotalFeesAndCosts'=>$GrandTotalFeesAndCostsFormated,

                'ScheduleA_PartnerList' => $scheduleAFamily['partners'],
                'ScheduleA_ChildList' => $scheduleAFamily['children'],
            ];

            if ($client->isCompany()) {
                $replacements = array_merge(
                    $replacements,
                    app(CompanyVisaAgreementMacroBuilder::class)->build($client, $costRowForMacro)
                );
            }

            // Log each replacement
            foreach ($replacements as $key => $value) {
                // FIX: Handle NULL values properly - convert to empty string
                $safeValue = $value ?? '';
                Log::info("Setting {$key} to: {$safeValue}");
                $templateProcessor->setValue($key, $safeValue);
            }

            // FIX: Set ALL remaining template variables to empty string to prevent corruption
            // This prevents unreplaced ${VariableName} placeholders from remaining in the document
            // which causes Microsoft Word to show "cannot open file" error
            try {
                $allTemplateVars = $templateProcessor->getVariables();
                $fixedVarsCount = 0;
                foreach ($allTemplateVars as $templateVar) {
                    // Only set if not already in replacements array
                    if (! isset($replacements[$templateVar]) && $this->isSafePhpWordTemplateVariableName($templateVar)) {
                        $templateProcessor->setValue($templateVar, '');
                        $fixedVarsCount++;
                    } elseif (! isset($replacements[$templateVar])) {
                        Log::warning('Skipped clearing malformed agreement template variable', [
                            'variable_preview' => substr($templateVar, 0, 80),
                        ]);
                    }
                }
                Log::info("Fixed {$fixedVarsCount} unreplaced template variables to prevent document corruption");
            } catch (\Exception $e) {
                // Log error but don't fail - continue with document generation
                Log::warning('Could not fix unreplaced variables: ' . $e->getMessage());
            }

            // Create the output directory if it doesn't exist - use public directory for web access
            $outputDir = storage_path('app/public/agreements');
            //  $outputDir = public_path('agreements');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = $outputDir . '/agreement_' . $client->client_id . '.docx'; //dd($outputPath);
            $templateProcessor->saveAs($outputPath);

            // Remove patched temp template if Option 2 was used
            if (isset($patchedTempPath) && $patchedTempPath !== null && file_exists($patchedTempPath)) {
                @unlink($patchedTempPath);
            }
            if (isset($scheduleATempPath) && $scheduleATempPath !== null && file_exists($scheduleATempPath)) {
                @unlink($scheduleATempPath);
            }

            Log::info('Document generated successfully at: ' . $outputPath);
            
            // FIX: Validate the generated document to ensure it's not corrupted
            // This catches document corruption issues before user tries to open it
            try {
                $validationDoc = \PhpOffice\PhpWord\IOFactory::load($outputPath);
                Log::info('Document validation passed - file is valid');
                unset($validationDoc); // Free memory
            } catch (\Exception $validationException) {
                // Document is corrupted - delete it and return error
                Log::error('Generated document failed validation: ' . $validationException->getMessage());
                if (file_exists($outputPath)) {
                    unlink($outputPath);
                }
                return response()->json([
                    'success' => false,
                    'error' => 'Document validation failed.',
                    'message' => 'The generated document appears to be corrupted. Please ensure all client matter and cost assignment data is complete before generating the agreement.',
                    'technical_details' => $validationException->getMessage()
                ], 500);
            }

            // Upload to S3 and get download URL
            $fileName = 'agreement_' . $client->client_id . '_' . time() . '.docx';
            $s3Path = $client->client_id . '/cost_assignment_form/' . $fileName;
            $downloadUrl = null;
            $s3UploadSuccess = false;
            
            // Try to upload to S3
            try {
                $uploadResult = Storage::disk('s3')->put($s3Path, file_get_contents($outputPath));
                
                if ($uploadResult) {
                    // Get the S3 URL
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    $downloadUrl = $disk->url($s3Path);
                    
                    // Verify the URL is not empty
                    if (!empty($downloadUrl)) {
                        $s3UploadSuccess = true;
                        Log::info('Document uploaded to S3 successfully. URL: ' . $downloadUrl);
                    } else {
                        Log::warning('S3 upload succeeded but URL is empty');
                    }
                } else {
                    Log::warning('S3 upload returned false');
                }
            } catch (\Exception $s3Exception) {
                Log::error('S3 upload failed: ' . $s3Exception->getMessage());
                Log::error($s3Exception->getTraceAsString());
            }
            
            // If S3 upload failed or URL is empty, use local file as fallback
            if (!$s3UploadSuccess || empty($downloadUrl)) {
                // File is already in public storage (storage/app/public/agreements)
                // Generate public URL using the storage path
                // The file is saved as: agreement_{client_id}.docx
                $localFileName = basename($outputPath);
                $relativePath = 'agreements/' . $localFileName;
                $downloadUrl = asset('storage/' . $relativePath);
                
                // Verify the file exists before returning the URL
                if (!file_exists($outputPath)) {
                    throw new \Exception('Document was generated but file not found at: ' . $outputPath);
                }
                
                Log::info('Using local file as fallback. URL: ' . $downloadUrl);
                // Keep the local file for download (don't delete it)
            } else {
                // Clean up local file only if S3 upload was successful
                if (file_exists($outputPath)) {
                    unlink($outputPath);
                }
            }
            
            // Verify download URL is set
            if (empty($downloadUrl)) {
                Log::error('Download URL is empty after all attempts. Output path: ' . $outputPath);
                throw new \Exception('Failed to generate download URL. Document was created but could not be made available for download.');
            }
            
            // Log the final response for debugging
            Log::info('Returning success response with download_url: ' . $downloadUrl);
            
            // Log activity
            $matter = \App\Models\ClientMatter::find($request->client_matter_id);
            $matterName = $matter ? $matter->title : 'N/A';
            
            $activity = new \App\Models\ActivitiesLog;
            $activity->client_id = $request->client_id;
            $activity->created_by = Auth::user()->id;
            $activity->subject = 'created visa agreement';
            $activity->description = '<p>Visa agreement has been created for matter: <strong>' . $matterName . '</strong></p>';
            $activity->task_status = 0;
            $activity->pin = 0;
            $activity->save();
            
            // Return the download URL as JSON
            $response = [
                'success' => true,
                'download_url' => $downloadUrl,
                'filename' => $fileName,
                'message' => 'Document generated successfully'
            ];
            
            // Double-check response structure before returning
            if (!isset($response['success']) || !isset($response['download_url'])) {
                Log::error('Response structure is invalid: ' . json_encode($response));
                throw new \Exception('Invalid response structure generated.');
            }
            
            return response()->json($response);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Model not found in generateagreement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Client or agent not found.',
                'message' => 'Client or agent not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error generating document: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error generating document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ensure Schedule A partner/child merge fields exist in Service_Agreement_general-style templates.
     * Idempotent: skips when placeholders are already present or sample paragraphs were removed.
     */
    protected function injectScheduleAFamilyPlaceholdersIfNeeded(string $docxPath): void
    {
        if (! is_file($docxPath)) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        $original = $xml;

        if (! str_contains($xml, 'ScheduleA_PartnerList')) {
            $partnerRun = '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t>${ScheduleA_PartnerList}</w:t></w:r>';
            $partner = ' partner: </w:t></w:r></w:p>';
            if (substr_count($xml, $partner) === 1) {
                $xml = str_replace($partner, ' partner: </w:t></w:r>' . $partnerRun . '</w:p>', $xml);
            }
        }

        if (! str_contains($xml, 'ScheduleA_ChildList')) {
            $oldSampleChildren = '<w:p w14:paraId="76AE0EE9" w14:textId="19E35E7E" w:rsidR="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:proofErr w:type="spellStart"/><w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t>Gdsf</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t xml:space="preserve"> (1/1/19xx)</w:t></w:r></w:p>'
                . '<w:p w14:paraId="0E3F9316" w14:textId="76EFE0F1" w:rsidR="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr><w:t>Sf</w:t></w:r></w:p>'
                . '<w:p w14:paraId="3BC3631A" w14:textId="0BAE766C" w:rsidR="00A81247" w:rsidRPr="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr><w:t>sf</w:t></w:r>'
                . '<w:bookmarkStart w:id="3" w:name="_Hlk95643751"/></w:p>';

            $newChildParagraph = '<w:p w14:paraId="76AE0EE9" w14:textId="19E35E7E" w:rsidR="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t>${ScheduleA_ChildList}</w:t></w:r><w:bookmarkStart w:id="3" w:name="_Hlk95643751"/></w:p>';

            if (str_contains($xml, $oldSampleChildren)) {
                $xml = str_replace($oldSampleChildren, $newChildParagraph, $xml);
            }
        }

        if ($xml !== $original) {
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $xml);
            Log::info('Injected Schedule A family placeholders into agreement template', ['path' => $docxPath]);
        }

        $zip->close();
    }

    /**
     * Build multi-line merge values for Schedule A (partner rows; lettered child rows).
     *
     * @return array{partners: string, children: string}
     */
    protected function buildScheduleAFamilyMacroStrings(int $clientAdminId): array
    {
        $partnerTypes = ['Husband', 'Wife', 'Ex-Husband', 'Ex-Wife', 'Defacto', 'Engaged'];
        $childTypes = ['Son', 'Daughter', 'Step Son', 'Step Daughter'];

        $rows = ClientRelationship::query()
            ->where('client_id', $clientAdminId)
            ->with(['relatedClient:id,first_name,last_name,client_id,dob,email'])
            ->orderBy('id')
            ->get();

        $partnerLines = [];
        foreach ($rows as $r) {
            if (in_array($r->relationship_type, $partnerTypes, true)) {
                $partnerLines[] = $this->formatVisaAgreementFamilyLineForPartner($r);
            }
        }

        $childLines = [];
        $letterIndex = 0;
        foreach ($rows as $r) {
            if (in_array($r->relationship_type, $childTypes, true)) {
                if ($letterIndex < 26) {
                    $prefix = chr(ord('a') + $letterIndex) . '. ';
                } else {
                    $prefix = (string) ($letterIndex + 1) . '. ';
                }
                $childLines[] = $prefix . $this->formatVisaAgreementFamilyLineForChild($r);
                $letterIndex++;
            }
        }

        return [
            'partners' => $partnerLines === [] ? 'No' : implode("\n", $partnerLines),
            'children' => $childLines === [] ? 'No' : implode("\n", $childLines),
        ];
    }

    protected function formatVisaAgreementFamilyLineForPartner(ClientRelationship $relationship): string
    {
        $name = $this->agreementFamilyMemberDisplayName($relationship);
        $rel = trim((string) ($relationship->relationship_type ?? ''));
        $line = $rel !== '' ? $name . ' — ' . $rel : $name;

        $email = $this->agreementPartnerEmailForDisplay($relationship);
        if ($email !== '' && stripos($line, $email) === false) {
            $line .= ' — ' . $email;
        }

        return $line;
    }

    protected function formatVisaAgreementFamilyLineForChild(ClientRelationship $relationship): string
    {
        $name = $this->agreementFamilyMemberDisplayName($relationship);
        $dob = $this->agreementFormatDobSuffixForRelationship($relationship);
        $line = $dob !== '' ? $name . ' (' . $dob . ')' : $name;
        $rel = trim((string) ($relationship->relationship_type ?? ''));

        return $rel !== '' ? $line . ' — ' . $rel : $line;
    }

    protected function agreementFormatDobSuffixForRelationship(ClientRelationship $relationship): string
    {
        $raw = $relationship->dob;
        if (($raw === null || $raw === '') && $relationship->relationLoaded('relatedClient') && $relationship->relatedClient) {
            $raw = $relationship->relatedClient->dob ?? null;
        }
        if ($raw === null || $raw === '') {
            return '';
        }
        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Plain-text display name aligned with client detail Relationships / family forms.
     */
    protected function agreementFamilyMemberDisplayName(ClientRelationship $relationship): string
    {
        if (! empty($relationship->related_client_id)) {
            $relatedClientInfo = $relationship->relatedClient;
            if ($relatedClientInfo) {
                $relatedClientId = (string) ($relatedClientInfo->client_id ?? '');
                $clientFirstName = trim((string) ($relatedClientInfo->first_name ?? ''));
                $clientLastName = trim((string) ($relatedClientInfo->last_name ?? ''));

                if ($clientFirstName === '' && $clientLastName === '') {
                    return 'Client ID: ' . $relatedClientId;
                }
                if ($clientFirstName === '') {
                    return trim($clientLastName . ' — ' . $relatedClientId);
                }
                if ($clientLastName === '') {
                    return trim($clientFirstName . ' — ' . $relatedClientId);
                }

                return trim($clientFirstName . ' ' . $clientLastName . ' — ' . $relatedClientId);
            }

            return 'Client not found';
        }

        $firstName = trim((string) ($relationship->first_name ?? ''));
        $lastName = trim((string) ($relationship->last_name ?? ''));
        if ($firstName === '' && $lastName === '') {
            $detailsFallback = trim((string) ($relationship->details ?? ''));

            return $detailsFallback !== '' ? $detailsFallback : 'Name not provided';
        }
        if ($firstName === '') {
            return $lastName;
        }
        if ($lastName === '') {
            return $firstName;
        }

        return $firstName . ' ' . $lastName;
    }

    /**
     * Email for Schedule A partner lines: relationship row, linked profile, or extracted from details.
     */
    protected function agreementPartnerEmailForDisplay(ClientRelationship $relationship): string
    {
        $fromColumn = trim((string) ($relationship->email ?? ''));
        if ($fromColumn !== '') {
            return $fromColumn;
        }

        if ($relationship->relationLoaded('relatedClient') && $relationship->relatedClient) {
            $fromClient = trim((string) ($relationship->relatedClient->email ?? ''));
            if ($fromClient !== '') {
                return $fromClient;
            }
        }

        $details = (string) ($relationship->details ?? '');
        if ($details !== '' && preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $details, $m)) {
            return $m[0];
        }

        if ($details !== '' && preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/', $details, $m)) {
            return $m[0];
        }

        return '';
    }

    /**
     * PhpWord variable names must be simple tokens; malformed names (e.g. from broken ${...} in DOCX) must not be cleared.
     */
    protected function isSafePhpWordTemplateVariableName(string $name): bool
    {
        if ($name === '' || strlen($name) > 64) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    /**
     * Resolve per-line "amount incl surcharge" for agreement macros.
     *
     * Some legacy or partial saves left *_after_person_surcharge at 0 while *_after_person had
     * the base total. Recalculate only in that case using the same rule as cost assignment save:
     * surcharge only when the cost assignment `surcharge` flag is Yes (1.4% of line base).
     */
    protected function resolveDoHaAmountInclSurcharge(float $afterPerson, $storedInclSurcharge, $surchargeFlag): float
    {
        $base = $afterPerson;
        $stored = floatval($storedInclSurcharge ?? 0);

        if ($base <= 0) {
            return $stored;
        }

        if ($stored > 0) {
            return $stored;
        }

        if (is_string($surchargeFlag) && trim($surchargeFlag) === 'Yes') {
            return round($base * 0.014, 2) + $base;
        }

        return $base;
    }

    //Get Migration Agent Detail
    public function getMigrationAgentDetail(Request $request)
    {
        $requestData = 	$request->all();
        $client_matter_id = $requestData['client_matter_id'];
        $clientMatterInfo = DB::table('client_matters')->select('sel_migration_agent','sel_matter_id')->where('id',$client_matter_id)->first();
        //dd($clientMatterInfo);
        if($clientMatterInfo) {
            //get matter name
            $matterInfo = DB::table('matters')->select('title','nick_name')->where('id',$clientMatterInfo->sel_matter_id)->first();
            //dd($matterInfo);
            if($matterInfo){
                $response['matterInfo'] = $matterInfo;
            } else {
                $response['matterInfo'] = "";
            }

            $sel_migration_agent = $clientMatterInfo->sel_migration_agent;
            $agentInfo = DB::table('staff')->select(
                'id as agentId',
                'first_name',
                'last_name',
                'company_name',
                'is_migration_agent',
                'marn_number',
                'legal_practitioner_number',
                'business_address',
                'business_phone',
                'business_mobile',
                'business_email',
                'tax_number'
            )->where('id', $sel_migration_agent)->first();
            //dd($agentInfo);
            if($agentInfo){
                $response['agentInfo'] 	= $agentInfo;
                $response['status'] 	= 	true;
                $response['message']	=	'Record is exist';
            } else {
                $response['agentInfo'] 	= "";
                $response['status'] 	= 	false;
                $response['message']	=	'Record is not exist.Please try again';
            }
        }
        echo json_encode($response);
    }

    //Get Visa agreemnt Migration Agent Detail
    public function getVisaAggreementMigrationAgentDetail(Request $request)
    {
        $requestData = 	$request->all();
        $client_matter_id = $requestData['client_matter_id'];
        $clientMatterInfo = DB::table('client_matters')->select('sel_migration_agent','sel_matter_id')->where('id',$client_matter_id)->first();
        //dd($clientMatterInfo);
        if($clientMatterInfo) {
            //get matter name
            $matterInfo = DB::table('matters')->select('title','nick_name')->where('id',$clientMatterInfo->sel_matter_id)->first();
            //dd($matterInfo);
            if($matterInfo){
                $response['matterInfo'] = $matterInfo;
            } else {
                $response['matterInfo'] = "";
            }

            $sel_migration_agent = $clientMatterInfo->sel_migration_agent;
            $agentInfo = DB::table('staff')->select(
                'id as agentId',
                'first_name',
                'last_name',
                'company_name',
                'is_migration_agent',
                'marn_number',
                'legal_practitioner_number',
                'business_address',
                'business_phone',
                'business_mobile',
                'business_email',
                'tax_number'
            )->where('id', $sel_migration_agent)->first();
            //dd($agentInfo);
            if($agentInfo){
                $response['agentInfo'] 	= $agentInfo;
                $response['status'] 	= 	true;
                $response['message']	=	'Record is exist';
            } else {
                $response['agentInfo'] 	= "";
                $response['status'] 	= 	false;
                $response['message']	=	'Record is not exist.Please try again';
            }
        }
        echo json_encode($response);
    }

    //Get Cost assignment Migration Agent Detail
    public function getCostAssignmentMigrationAgentDetail(Request $request)
    {
        $requestData = 	$request->all(); //dd($requestData);
        $client_matter_id = $requestData['client_matter_id'];
        $clientMatterInfo = DB::table('client_matters')->select('sel_migration_agent','sel_matter_id')->where('id',$client_matter_id)->first();
        //dd($clientMatterInfo);
        if($clientMatterInfo) {
            //get matter name
            $matterInfo = DB::table('matters')->where('id',$clientMatterInfo->sel_matter_id)->first();
            //dd($matterInfo);
            if($matterInfo){
                $response['matterInfo'] = $matterInfo;
            } else {
                $response['matterInfo'] = "";
            }

            //get cost assignment matter fee
            $costassignmentmatterInfo = DB::table('cost_assignment_forms')->where('client_id',$requestData['client_id'])->where('client_matter_id',$requestData['client_matter_id'])->first();
            //dd($costassignmentmatterInfo);
            if($matterInfo){
                $response['cost_assignment_matterInfo'] = $costassignmentmatterInfo;
            } else {
                $response['cost_assignment_matterInfo'] = "";
            }

            $sel_migration_agent = $clientMatterInfo->sel_migration_agent;
            $agentInfo = DB::table('staff')->select(
                'id as agentId',
                'first_name',
                'last_name',
                'company_name',
                'is_migration_agent',
                'marn_number',
                'legal_practitioner_number',
                'business_address',
                'business_phone',
                'business_mobile',
                'business_email',
                'tax_number'
            )->where('id', $sel_migration_agent)->first();
            //dd($agentInfo);
            if($agentInfo){
                $response['agentInfo'] 	= $agentInfo;
                $response['status'] 	= 	true;
                $response['message']	=	'Record is exist';
            } else {
                $response['agentInfo'] 	= "";
                $response['status'] 	= 	false;
                $response['message']	=	'Record is not exist.Please try again';
            }
        }
        echo json_encode($response);
    }

    //Store Cost Assignment Form Values
    public function savecostassignment(Request $request)
    {   //dd( $request->all());
        if ($request->isMethod('post'))
        {
            $requestData = $request->all(); //dd($requestData);

            $adminClient = Admin::find($requestData['client_id'] ?? null);
            $isCompanyClient = $adminClient && (bool) $adminClient->is_company;
            $safLevyForSave = null;
            if ($isCompanyClient) {
                $safLevyRaw = isset($requestData['saf_levy']) ? trim((string) $requestData['saf_levy']) : '';
                $safLevyForSave = $safLevyRaw === '' ? null : $safLevyRaw;
            }

            if( isset($requestData['surcharge']) && $requestData['surcharge'] != '') {
                $surcharge = $requestData['surcharge'];
            } else {
                $surcharge = 'Yes';
            }

            $Dept_Base_Application_Charge = floatval($requestData['Dept_Base_Application_Charge'] ?? 0); //dd($Dept_Base_Application_Charge);
            $Dept_Base_Application_Charge_no_of_person = intval($requestData['Dept_Base_Application_Charge_no_of_person'] ?? 1); //dd($Dept_Base_Application_Charge_no_of_person);
            $Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge * $Dept_Base_Application_Charge_no_of_person;
            $Dept_Base_Application_Charge_after_person = floatval($Dept_Base_Application_Charge_after_person); //dd($Dept_Base_Application_Charge_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Base_Application_Surcharge = round($Dept_Base_Application_Charge_after_person * 0.014, 2);
            } else {
                $Dept_Base_Application_Surcharge = 0;
            }
            
            // Step 3: Final total after surcharge
            $Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person + $Dept_Base_Application_Surcharge; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

            $Dept_Non_Internet_Application_Charge = floatval($requestData['Dept_Non_Internet_Application_Charge'] ?? 0); //dd($Dept_Non_Internet_Application_Charge);
            $Dept_Non_Internet_Application_Charge_no_of_person = intval($requestData['Dept_Non_Internet_Application_Charge_no_of_person'] ?? 0); //dd($Dept_Non_Internet_Application_Charge_no_of_person);
            $Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge * $Dept_Non_Internet_Application_Charge_no_of_person;
            $Dept_Non_Internet_Application_Charge_after_person = floatval($Dept_Non_Internet_Application_Charge_after_person); //dd($Dept_Non_Internet_Application_Charge_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Non_Internet_Application_Surcharge = round($Dept_Non_Internet_Application_Charge_after_person * 0.014, 2);
            } else {
                $Dept_Non_Internet_Application_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Surcharge + $Dept_Non_Internet_Application_Charge_after_person; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

            $Dept_Additional_Applicant_Charge_18_Plus = floatval($requestData['Dept_Additional_Applicant_Charge_18_Plus'] ?? 0);
            $Dept_Additional_Applicant_Charge_18_Plus_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'] ?? 0);
            $Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus * $Dept_Additional_Applicant_Charge_18_Plus_no_of_person;
            $Dept_Additional_Applicant_Charge_18_Plus_after_person = floatval($Dept_Additional_Applicant_Charge_18_Plus_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Additional_Applicant_Charge_18_Surcharge = round($Dept_Additional_Applicant_Charge_18_Plus_after_person * 0.014, 2);
            } else {
                $Dept_Additional_Applicant_Charge_18_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Surcharge + $Dept_Additional_Applicant_Charge_18_Plus_after_person;

            $Dept_Additional_Applicant_Charge_Under_18 = floatval($requestData['Dept_Additional_Applicant_Charge_Under_18'] ?? 0);
            $Dept_Additional_Applicant_Charge_Under_18_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'] ?? 0);
            $Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18 * $Dept_Additional_Applicant_Charge_Under_18_no_of_person;
            $Dept_Additional_Applicant_Charge_Under_18_after_person = floatval($Dept_Additional_Applicant_Charge_Under_18_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Additional_Applicant_Charge_Under_18_Surcharge = round($Dept_Additional_Applicant_Charge_Under_18_after_person * 0.014, 2);
            } else {
                $Dept_Additional_Applicant_Charge_Under_18_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_Surcharge + $Dept_Additional_Applicant_Charge_Under_18_after_person;

            $Dept_Subsequent_Temp_Application_Charge = floatval($requestData['Dept_Subsequent_Temp_Application_Charge'] ?? 0);
            $Dept_Subsequent_Temp_Application_Charge_no_of_person = intval($requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'] ?? 0);
            $Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge * $Dept_Subsequent_Temp_Application_Charge_no_of_person;
            $Dept_Subsequent_Temp_Application_Charge_after_person = floatval($Dept_Subsequent_Temp_Application_Charge_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Subsequent_Temp_Application_Surcharge = round($Dept_Subsequent_Temp_Application_Charge_after_person * 0.014, 2);
            } else {
                $Dept_Subsequent_Temp_Application_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Surcharge + $Dept_Subsequent_Temp_Application_Charge_after_person;

            $Dept_Second_VAC_Instalment_Charge_18_Plus = floatval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'] ?? 0);
            $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'] ?? 0);
            $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus * $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person;
            $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = floatval($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Second_VAC_Instalment_18_Plus_Surcharge = round($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person * 0.014, 2);
            } else {
                $Dept_Second_VAC_Instalment_18_Plus_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_18_Plus_Surcharge + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;

            $Dept_Second_VAC_Instalment_Under_18 = floatval($requestData['Dept_Second_VAC_Instalment_Under_18'] ?? 0);
            $Dept_Second_VAC_Instalment_Under_18_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'] ?? 0);
            $Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18 * $Dept_Second_VAC_Instalment_Under_18_no_of_person;
            $Dept_Second_VAC_Instalment_Under_18_after_person = floatval($Dept_Second_VAC_Instalment_Under_18_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Second_VAC_Instalment_Under_18_Surcharge = round($Dept_Second_VAC_Instalment_Under_18_after_person * 0.014, 2);
            } else {
                $Dept_Second_VAC_Instalment_Under_18_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_Surcharge + $Dept_Second_VAC_Instalment_Under_18_after_person;

            // Get Nomination and Sponsorship charges (no person multiplier for these)
            $Dept_Nomination_Application_Charge = floatval($requestData['Dept_Nomination_Application_Charge'] ?? 0);
            $Dept_Sponsorship_Application_Charge = floatval($requestData['Dept_Sponsorship_Application_Charge'] ?? 0);

            $TotalDoHACharges = $Dept_Base_Application_Charge_after_person
                                + $Dept_Additional_Applicant_Charge_18_Plus_after_person
                                + $Dept_Additional_Applicant_Charge_Under_18_after_person
                                + $Dept_Subsequent_Temp_Application_Charge_after_person
                                + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person
                                + $Dept_Second_VAC_Instalment_Under_18_after_person
                                + $Dept_Non_Internet_Application_Charge_after_person
                                + $Dept_Nomination_Application_Charge
                                + $Dept_Sponsorship_Application_Charge;

            if ($isCompanyClient && $safLevyForSave !== null) {
                $TotalDoHACharges += (float) str_replace(',', '', (string) $safLevyForSave);
            }

            // Calculate surcharge as 1.4% of total DoHA charges (matching frontend calculation)
            if( $surcharge == 'Yes'){
                $TotalDoHASurcharges = round($TotalDoHACharges * 0.014, 2);
            } else {
                $TotalDoHASurcharges = 0;
            }

            $TotalBLOCKFEE = $requestData['Block_1_Ex_Tax'] + $requestData['Block_2_Ex_Tax'] +  $requestData['Block_3_Ex_Tax'];

            $cost_assignment_cnt = \App\Models\CostAssignmentForm::where('client_id',$requestData['client_id'])->where('client_matter_id',$requestData['client_matter_id'])->count();
            //dd($surcharge);
            if($cost_assignment_cnt >0){
                //update
                $costAssignment = \App\Models\CostAssignmentForm::where('client_id', $requestData['client_id'])
                ->where('client_matter_id', $requestData['client_matter_id'])
                ->first();
                if ($costAssignment) {
                    $saved = $costAssignment->update([
                        'agent_id' => $requestData['agent_id'],
                        'surcharge' => $surcharge,
                        
                        'Dept_Base_Application_Charge' => $requestData['Dept_Base_Application_Charge'],
                        'Dept_Base_Application_Charge_no_of_person' => $requestData['Dept_Base_Application_Charge_no_of_person'],
                        'Dept_Base_Application_Charge_after_person' => $Dept_Base_Application_Charge_after_person,
                        'Dept_Base_Application_Charge_after_person_surcharge' => $Dept_Base_Application_Charge_after_person_surcharge,

                        'Dept_Non_Internet_Application_Charge' => $requestData['Dept_Non_Internet_Application_Charge'],
                        'Dept_Non_Internet_Application_Charge_no_of_person' => $requestData['Dept_Non_Internet_Application_Charge_no_of_person'],
                        'Dept_Non_Internet_Application_Charge_after_person' => $Dept_Non_Internet_Application_Charge_after_person,
                        'Dept_Non_Internet_Application_Charge_after_person_surcharge' => $Dept_Non_Internet_Application_Charge_after_person_surcharge,

                        'Dept_Additional_Applicant_Charge_18_Plus' => $requestData['Dept_Additional_Applicant_Charge_18_Plus'],
                        'Dept_Additional_Applicant_Charge_18_Plus_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'],
                        'Dept_Additional_Applicant_Charge_18_Plus_after_person' => $Dept_Additional_Applicant_Charge_18_Plus_after_person,
                        'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge' => $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge,

                        'Dept_Additional_Applicant_Charge_Under_18' => $requestData['Dept_Additional_Applicant_Charge_Under_18'],
                        'Dept_Additional_Applicant_Charge_Under_18_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'],
                        'Dept_Additional_Applicant_Charge_Under_18_after_person' => $Dept_Additional_Applicant_Charge_Under_18_after_person,
                        'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge' => $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge,

                        'Dept_Subsequent_Temp_Application_Charge' => $requestData['Dept_Subsequent_Temp_Application_Charge'],
                        'Dept_Subsequent_Temp_Application_Charge_no_of_person' => $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'],
                        'Dept_Subsequent_Temp_Application_Charge_after_person' => $Dept_Subsequent_Temp_Application_Charge_after_person,
                        'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge' => $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge,

                        'Dept_Second_VAC_Instalment_Charge_18_Plus' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'],
                        'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'],
                        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person,
                        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge,

                        'Dept_Second_VAC_Instalment_Under_18' => $requestData['Dept_Second_VAC_Instalment_Under_18'],
                        'Dept_Second_VAC_Instalment_Under_18_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'],
                        'Dept_Second_VAC_Instalment_Under_18_after_person' => $Dept_Second_VAC_Instalment_Under_18_after_person,
                        'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge' => $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge,

                        'Dept_Nomination_Application_Charge' => $requestData['Dept_Nomination_Application_Charge'],
                        'Dept_Sponsorship_Application_Charge' => $requestData['Dept_Sponsorship_Application_Charge'],
                        'saf_levy' => $isCompanyClient ? $safLevyForSave : $costAssignment->saf_levy,
                        'Block_1_Ex_Tax' => $requestData['Block_1_Ex_Tax'],
                        'Block_2_Ex_Tax' => $requestData['Block_2_Ex_Tax'],
                        'Block_3_Ex_Tax' => $requestData['Block_3_Ex_Tax'],
                        'additional_fee_1' => $requestData['additional_fee_1'],
                        'TotalDoHACharges' => $TotalDoHACharges,
                        'TotalDoHASurcharges' => $TotalDoHASurcharges,
                        'TotalBLOCKFEE' => $TotalBLOCKFEE
                    ]);
                }
            }
            else
            {
                //insert
                $obj = new CostAssignmentForm;

                $obj->client_id = $requestData['client_id'];
                $obj->client_matter_id = $requestData['client_matter_id'];
                $obj->agent_id = $requestData['agent_id'];
                $obj->surcharge = $surcharge;
                
                $obj->Dept_Base_Application_Charge = $requestData['Dept_Base_Application_Charge'];
                $obj->Dept_Base_Application_Charge_no_of_person = $requestData['Dept_Base_Application_Charge_no_of_person'];
                $obj->Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge_after_person;
                $obj->Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person_surcharge;

                $obj->Dept_Non_Internet_Application_Charge = $requestData['Dept_Non_Internet_Application_Charge'];
                 $obj->Dept_Non_Internet_Application_Charge_no_of_person = $requestData['Dept_Non_Internet_Application_Charge_no_of_person'];
                $obj->Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge_after_person;
                $obj->Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Charge_after_person_surcharge;

                $obj->Dept_Additional_Applicant_Charge_18_Plus = $requestData['Dept_Additional_Applicant_Charge_18_Plus'];
                $obj->Dept_Additional_Applicant_Charge_18_Plus_no_of_person = $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'];
                $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus_after_person;
                $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge;

                $obj->Dept_Additional_Applicant_Charge_Under_18 = $requestData['Dept_Additional_Applicant_Charge_Under_18'];
                $obj->Dept_Additional_Applicant_Charge_Under_18_no_of_person = $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'];
                $obj->Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18_after_person;
                $obj->Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge;

                $obj->Dept_Subsequent_Temp_Application_Charge = $requestData['Dept_Subsequent_Temp_Application_Charge'];
                $obj->Dept_Subsequent_Temp_Application_Charge_no_of_person = $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'];
                $obj->Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge_after_person;
                $obj->Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge;

                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'];
                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'];
                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;
                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge;

                $obj->Dept_Second_VAC_Instalment_Under_18 = $requestData['Dept_Second_VAC_Instalment_Under_18'];
                $obj->Dept_Second_VAC_Instalment_Under_18_no_of_person = $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'];
                $obj->Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18_after_person;
                $obj->Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge;

                $obj->Dept_Nomination_Application_Charge = $requestData['Dept_Nomination_Application_Charge'];
                $obj->Dept_Sponsorship_Application_Charge = $requestData['Dept_Sponsorship_Application_Charge'];
                if ($isCompanyClient) {
                    $obj->saf_levy = $safLevyForSave;
                }

                $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'];
                $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'];
                $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'];
                $obj->additional_fee_1 = $requestData['additional_fee_1'];
                $obj->TotalDoHACharges = $TotalDoHACharges;
                $obj->TotalDoHASurcharges = $TotalDoHASurcharges;
                $obj->TotalBLOCKFEE = $TotalBLOCKFEE;
                $saved = $obj->save();
            }
            if (!$saved) {
                $response['status'] 	= 	false;
                $response['message']	=	'Cost assignment not added successfully.Please try again';
            } else {
                $response['status'] 	= 	true;
                $response['message']	=	'Cost assignment added successfully';
                
                // Log activity
                $action = ($cost_assignment_cnt > 0) ? 'updated' : 'created';
                $matter = \App\Models\ClientMatter::find($requestData['client_matter_id']);
                $matterName = $matter ? $matter->title : 'N/A';
                
                $activity = new \App\Models\ActivitiesLog;
                $activity->client_id = $requestData['client_id'];
                $activity->created_by = Auth::user()->id;
                $activity->subject = $action . ' cost assignment form';
                $activity->description = '<p>Cost assignment form has been ' . $action . ' for matter: <strong>' . $matterName . '</strong></p>';
                $activity->task_status = 0;
                $activity->pin = 0;
                $activity->save();
            }
        }
        echo json_encode($response);
    }

    public function deletecostagreement(Request $request)
    {
        $cost_agreement_id = $request->input('cost_agreement_id');
        
        if (!$cost_agreement_id) {
            return response()->json([
                'status' => false,
                'message' => 'Cost agreement ID is required'
            ]);
        }

        $costAssignment = \App\Models\CostAssignmentForm::find($cost_agreement_id);
        
        if (!$costAssignment) {
            return response()->json([
                'status' => false,
                'message' => 'Cost agreement not found'
            ]);
        }

        $client_id = $costAssignment->client_id;
        $matter = \App\Models\ClientMatter::find($costAssignment->client_matter_id);
        $matterName = $matter ? $matter->title : 'N/A';

        // Delete the cost assignment
        $deleted = $costAssignment->delete();

        if ($deleted) {
            // Log activity
            $activity = new \App\Models\ActivitiesLog;
            $activity->client_id = $client_id;
            $activity->created_by = Auth::user()->id;
            $activity->subject = 'deleted cost assignment form';
            $activity->description = '<p>Cost assignment form has been deleted for matter: <strong>' . $matterName . '</strong></p>';
            $activity->task_status = 0;
            $activity->pin = 0;
            $activity->save();

            return response()->json([
                'status' => true,
                'message' => 'Cost agreement deleted successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete cost agreement'
            ]);
        }
    }

    //save reference
    public function savereferences(Request $request)
    { 
        // Step 1: Validate required fields - client_id is mandatory
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:admins,id',
            'department_reference' => 'nullable|string|max:255',
            'other_reference' => 'nullable|string|max:255',
            'client_matter_id' => 'nullable|integer|exists:client_matters,id',
            'client_unique_matter_no' => 'nullable|string|max:255',
        ]);

        // Step 2: Find the matter - ALWAYS filter by client_id first for security
        // Priority: 1) Use client_unique_matter_no from URL (id1), 2) Use client_matter_id from dropdown, 3) Get latest active matter
        $matter = null;
        $lookupMethod = '';
        $clientId = (int)$request->client_id; // Ensure integer type
        
        if ($request->has('client_unique_matter_no') && !empty($request->client_unique_matter_no)) {
            // Priority 1: Use client_unique_matter_no from URL (id1) - MUST match client_id
            $matter = \App\Models\ClientMatter::where('client_id', $clientId)
                ->where('client_unique_matter_no', $request->client_unique_matter_no)
                ->first();
            $lookupMethod = 'client_unique_matter_no: ' . $request->client_unique_matter_no;
        } elseif ($request->has('client_matter_id') && !empty($request->client_matter_id)) {
            // Priority 2: Use the matter ID from dropdown - MUST match client_id
            $matter = \App\Models\ClientMatter::where('client_id', $clientId)
                ->where('id', (int)$request->client_matter_id)
                ->first();
            $lookupMethod = 'client_matter_id: ' . $request->client_matter_id;
        } else {
            // Priority 3: Fallback - Get latest active matter - MUST match client_id
            $matter = \App\Models\ClientMatter::where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
            $lookupMethod = 'latest active matter';
        }

        // Step 3: Verify matter exists and belongs to the client_id (double security check)
        if (!$matter) {
            Log::error('References save - Matter not found', [
                'client_id' => $clientId,
                'client_unique_matter_no' => $request->client_unique_matter_no ?? 'not provided',
                'client_matter_id' => $request->client_matter_id ?? 'not provided',
                'lookup_method' => $lookupMethod
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found for given client_id and matter information.'
            ], 404);
        }
        
        // Additional security check: Ensure the found matter actually belongs to the client_id
        if ($matter->client_id != $clientId) {
            Log::error('References save - Security violation: Matter does not belong to client', [
                'matter_id' => $matter->id,
                'matter_client_id' => $matter->client_id,
                'requested_client_id' => $clientId
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Security violation: Matter does not belong to the specified client.'
            ], 403);
        }
        
        Log::info('References save - Matter found', [
            'matter_id' => $matter->id,
            'client_id' => $matter->client_id,
            'client_unique_matter_no' => $matter->client_unique_matter_no,
            'lookup_method' => $lookupMethod,
            'current_department_reference' => $matter->department_reference,
            'current_other_reference' => $matter->other_reference
        ]);

        // Step 3: Perform the update - convert empty strings to null
        $deptRefInput = $request->input('department_reference', '');
        $otherRefInput = $request->input('other_reference', '');
        $deptRef = !empty($deptRefInput) && trim($deptRefInput) !== '' ? trim($deptRefInput) : null;
        $otherRef = !empty($otherRefInput) && trim($otherRefInput) !== '' ? trim($otherRefInput) : null;
        
        // Direct assignment and save (fields are in fillable, so this is safe)
        $matter->department_reference = $deptRef;
        $matter->other_reference = $otherRef;
        $saved = $matter->save();
        
        if (!$saved) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save references.'
            ], 500);
        }
        
        // Refresh to get latest values from database
        $matter->refresh();

        // Log for debugging
        Log::info('References saved', [
            'matter_id' => $matter->id,
            'client_id' => $request->client_id,
            'client_unique_matter_no' => $matter->client_unique_matter_no,
            'department_reference' => $matter->department_reference,
            'other_reference' => $matter->other_reference,
            'saved' => $saved
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'References updated successfully.',
            'data' => [
                'matter_id' => $matter->id,
                'client_unique_matter_no' => $matter->client_unique_matter_no,
                'department_reference' => $matter->department_reference,
                'other_reference' => $matter->other_reference
            ]
        ]);
    }

    //Check star client
    public function checkStarClient(Request $request)
    {
        $admin = \App\Models\Admin::find($request->admin_id);

        if (!$admin) {
            return response()->json(['status' => 'error', 'message' => 'Client not found']);
        }

        // is_star_client column dropped Phase 4 - always return not_star
        return response()->json(['status' => 'not_star']);
    }

    //Fetch client matter assignee


    //Delete Personal Doucment Category
    /*public function deletePersonalDocCategory(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:personal_document_types,id',
        ]);

        $category = PersonalDocumentType::findOrFail($request->id);

        // Check if the category is client-generated
        if ($category->client_id !== null) {
            $category->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Only client-generated categories can be deleted.']);
    }*/

    //Check same client_id and same client matter is already exist in db or not
    public function checkCostAssignment(Request $request)
    {
        $exists = \App\Models\CostAssignmentForm::where('client_id', $request->client_id)
                    ->where('client_matter_id', $request->client_matter_id)
                    ->exists();

        return response()->json(['exists' => $exists]);
    }

    //Store Cost Assignment Form Values of Lead
    public function savecostassignmentlead(Request $request)
    {   
        $response = ['status' => false, 'message' => 'An error occurred. Please try again.'];
        if ($request->isMethod('post'))
        {
            $requestData = $request->all(); //dd($requestData);
            $clientForMatter = Admin::find($requestData['client_id'] ?? null);
            if (!$clientForMatter || ! in_array($clientForMatter->type, ['client', 'lead'], true)) {
                $response['message'] = 'Invalid client.';
                echo json_encode($response);
                return;
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientForMatter->id, Auth::user())) {
                $response['message'] = config('constants.unauthorized');
                echo json_encode($response);
                return;
            }
            $isCompanyClientLead = (bool) $clientForMatter->is_company;
            $safLevyForSaveLead = null;
            if ($isCompanyClientLead) {
                $safLevyRawLead = isset($requestData['saf_levy']) ? trim((string) $requestData['saf_levy']) : '';
                $safLevyForSaveLead = $safLevyRawLead === '' ? null : $safLevyRawLead;
            }
            $matterId = (int) ($requestData['matter_id'] ?? 0);
            if (! Matter::allowedForClientIsCompany($matterId, (bool) $clientForMatter->is_company)) {
                $response['message'] = 'This matter type is not valid for this client record.';
                echo json_encode($response);
                return;
            }
            //insert into client matter table
            $obj5 = new ClientMatter();
            $obj5->user_id = Auth::user()->id;
            $obj5->client_id = $requestData['client_id'];
            $obj5->office_id = $requestData['office_id'] ?? optional(Auth::user())->office_id ?? null;
            $obj5->sel_migration_agent = $requestData['migration_agent'];
            $obj5->sel_person_responsible = $requestData['person_responsible'];
            $obj5->sel_person_assisting = $requestData['person_assisting'];
            $obj5->sel_matter_id = $requestData['matter_id'];
            
            $client_matters_cnt_per_client = DB::table('client_matters')->select('id')->where('sel_matter_id',$requestData['matter_id'])->where('client_id',$requestData['client_id'])->count();
            $client_matters_current_no = $client_matters_cnt_per_client+1;
            if($requestData['matter_id'] == 1) {
                $obj5->client_unique_matter_no = 'GN_'.$client_matters_current_no;
            } else {
                $matterInfo = Matter::select('nick_name')->where('id', '=', $requestData['matter_id'])->first();
                $prefix = ($matterInfo && $matterInfo->nick_name) ? $matterInfo->nick_name : 'Matter';
                $obj5->client_unique_matter_no = $prefix."_".$client_matters_current_no;
            }
            $matterType = Matter::find($requestData['matter_id']);
            $workflowId = $matterType && $matterType->workflow_id ? $matterType->workflow_id : \App\Models\Workflow::where('name', 'General')->value('id');
            $firstStageId = \App\Models\WorkflowStage::where('workflow_id', $workflowId)->orderByRaw('COALESCE(sort_order, id) ASC')->value('id')
                ?? \App\Models\WorkflowStage::orderByRaw('COALESCE(sort_order, id) ASC')->value('id') ?? 1;
            $obj5->workflow_id = $workflowId;
            $obj5->workflow_stage_id = $firstStageId;
            $obj5->matter_status = 1; // Active by default
            $saved5 = $obj5->save();
            $lastInsertedId = $obj5->id; // ← This gets the last inserted ID
            if($saved5) 
            {
                // Saving an active matter for a lead auto-promotes admins.type to 'client'
                // via the ClientMatter::saved hook (Admin::promoteLeadWithActiveMatterToClient).

                if( isset($requestData['surcharge']) && $requestData['surcharge'] != '') {
                    $surcharge = $requestData['surcharge'];
                } else {
                    $surcharge = 'Yes';
                }

                $Dept_Base_Application_Charge = floatval($requestData['Dept_Base_Application_Charge'] ?? 0); //dd($Dept_Base_Application_Charge);
                $Dept_Base_Application_Charge_no_of_person = intval($requestData['Dept_Base_Application_Charge_no_of_person'] ?? 1); //dd($Dept_Base_Application_Charge_no_of_person);
                $Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge * $Dept_Base_Application_Charge_no_of_person;
                $Dept_Base_Application_Charge_after_person = floatval($Dept_Base_Application_Charge_after_person); //dd($Dept_Base_Application_Charge_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Base_Application_Surcharge = round($Dept_Base_Application_Charge_after_person * 0.014, 2);
                } else {
                    $Dept_Base_Application_Surcharge = 0;
                }
            
                // Step 3: Final total after surcharge
                $Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person + $Dept_Base_Application_Surcharge; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

                $Dept_Non_Internet_Application_Charge = floatval($requestData['Dept_Non_Internet_Application_Charge'] ?? 0); //dd($Dept_Non_Internet_Application_Charge);
                $Dept_Non_Internet_Application_Charge_no_of_person = intval($requestData['Dept_Non_Internet_Application_Charge_no_of_person'] ?? 0); //dd($Dept_Non_Internet_Application_Charge_no_of_person);
                $Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge * $Dept_Non_Internet_Application_Charge_no_of_person;
                $Dept_Non_Internet_Application_Charge_after_person = floatval($Dept_Non_Internet_Application_Charge_after_person); //dd($Dept_Non_Internet_Application_Charge_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Non_Internet_Application_Surcharge = round($Dept_Non_Internet_Application_Charge_after_person * 0.014, 2);
                } else {
                    $Dept_Non_Internet_Application_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Surcharge + $Dept_Non_Internet_Application_Charge_after_person; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

                $Dept_Additional_Applicant_Charge_18_Plus = floatval($requestData['Dept_Additional_Applicant_Charge_18_Plus'] ?? 0);
                $Dept_Additional_Applicant_Charge_18_Plus_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'] ?? 0);
                $Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus * $Dept_Additional_Applicant_Charge_18_Plus_no_of_person;
                $Dept_Additional_Applicant_Charge_18_Plus_after_person = floatval($Dept_Additional_Applicant_Charge_18_Plus_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Additional_Applicant_Charge_18_Surcharge = round($Dept_Additional_Applicant_Charge_18_Plus_after_person * 0.014, 2);
                } else {
                    $Dept_Additional_Applicant_Charge_18_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Surcharge + $Dept_Additional_Applicant_Charge_18_Plus_after_person;

                $Dept_Additional_Applicant_Charge_Under_18 = floatval($requestData['Dept_Additional_Applicant_Charge_Under_18'] ?? 0);
                $Dept_Additional_Applicant_Charge_Under_18_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'] ?? 0);
                $Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18 * $Dept_Additional_Applicant_Charge_Under_18_no_of_person;
                $Dept_Additional_Applicant_Charge_Under_18_after_person = floatval($Dept_Additional_Applicant_Charge_Under_18_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Additional_Applicant_Charge_Under_18_Surcharge = round($Dept_Additional_Applicant_Charge_Under_18_after_person * 0.014, 2);
                } else {
                    $Dept_Additional_Applicant_Charge_Under_18_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_Surcharge + $Dept_Additional_Applicant_Charge_Under_18_after_person;

                $Dept_Subsequent_Temp_Application_Charge = floatval($requestData['Dept_Subsequent_Temp_Application_Charge'] ?? 0);
                $Dept_Subsequent_Temp_Application_Charge_no_of_person = intval($requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'] ?? 0);
                $Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge * $Dept_Subsequent_Temp_Application_Charge_no_of_person;
                $Dept_Subsequent_Temp_Application_Charge_after_person = floatval($Dept_Subsequent_Temp_Application_Charge_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Subsequent_Temp_Application_Surcharge = round($Dept_Subsequent_Temp_Application_Charge_after_person * 0.014, 2);
                } else {
                    $Dept_Subsequent_Temp_Application_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Surcharge + $Dept_Subsequent_Temp_Application_Charge_after_person;

                $Dept_Second_VAC_Instalment_Charge_18_Plus = floatval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'] ?? 0);
                $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'] ?? 0);
                $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus * $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person;
                $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = floatval($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Second_VAC_Instalment_18_Plus_Surcharge = round($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person * 0.014, 2);
                } else {
                    $Dept_Second_VAC_Instalment_18_Plus_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_18_Plus_Surcharge + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;

                $Dept_Second_VAC_Instalment_Under_18 = floatval($requestData['Dept_Second_VAC_Instalment_Under_18'] ?? 0);
                $Dept_Second_VAC_Instalment_Under_18_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'] ?? 0);
                $Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18 * $Dept_Second_VAC_Instalment_Under_18_no_of_person;
                $Dept_Second_VAC_Instalment_Under_18_after_person = floatval($Dept_Second_VAC_Instalment_Under_18_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Second_VAC_Instalment_Under_18_Surcharge = round($Dept_Second_VAC_Instalment_Under_18_after_person * 0.014, 2);
                } else {
                    $Dept_Second_VAC_Instalment_Under_18_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_Surcharge + $Dept_Second_VAC_Instalment_Under_18_after_person;

                // Get Nomination and Sponsorship charges (no person multiplier for these)
                $Dept_Nomination_Application_Charge = floatval($requestData['Dept_Nomination_Application_Charge'] ?? 0);
                $Dept_Sponsorship_Application_Charge = floatval($requestData['Dept_Sponsorship_Application_Charge'] ?? 0);

                $TotalDoHACharges = $Dept_Base_Application_Charge_after_person
                                    + $Dept_Additional_Applicant_Charge_18_Plus_after_person
                                    + $Dept_Additional_Applicant_Charge_Under_18_after_person
                                    + $Dept_Subsequent_Temp_Application_Charge_after_person
                                    + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person
                                    + $Dept_Second_VAC_Instalment_Under_18_after_person
                                    + $Dept_Non_Internet_Application_Charge_after_person
                                    + $Dept_Nomination_Application_Charge
                                    + $Dept_Sponsorship_Application_Charge;

                if ($isCompanyClientLead && $safLevyForSaveLead !== null) {
                    $TotalDoHACharges += (float) str_replace(',', '', (string) $safLevyForSaveLead);
                }

                // Calculate surcharge as 1.4% of total DoHA charges (matching frontend calculation)
                if( $surcharge == 'Yes'){
                    $TotalDoHASurcharges = round($TotalDoHACharges * 0.014, 2);
                } else {
                    $TotalDoHASurcharges = 0;
                }

                $TotalBLOCKFEE = $requestData['Block_1_Ex_Tax'] + $requestData['Block_2_Ex_Tax'] +  $requestData['Block_3_Ex_Tax'];

                $cost_assignment_cnt = \App\Models\CostAssignmentForm::where('client_id',$requestData['client_id'])->where('client_matter_id',$lastInsertedId)->count();
                //dd($surcharge);
                if($cost_assignment_cnt >0)
                {
                    //update
                    $costAssignment = \App\Models\CostAssignmentForm::where('client_id', $requestData['client_id'])
                    ->where('client_matter_id', $lastInsertedId)
                    ->first();
                    if ($costAssignment) 
                    {
                        $saved = $costAssignment->update([
                            'agent_id' => $requestData['agent_id'],
                            'surcharge' => $surcharge,
                            
                            'Dept_Base_Application_Charge' => $requestData['Dept_Base_Application_Charge'],
                            'Dept_Base_Application_Charge_no_of_person' => $requestData['Dept_Base_Application_Charge_no_of_person'],
                            'Dept_Base_Application_Charge_after_person' => $Dept_Base_Application_Charge_after_person,
                            'Dept_Base_Application_Charge_after_person_surcharge' => $Dept_Base_Application_Charge_after_person_surcharge,

                            'Dept_Non_Internet_Application_Charge' => $requestData['Dept_Non_Internet_Application_Charge'],
                            'Dept_Non_Internet_Application_Charge_no_of_person' => $requestData['Dept_Non_Internet_Application_Charge_no_of_person'],
                            'Dept_Non_Internet_Application_Charge_after_person' => $Dept_Non_Internet_Application_Charge_after_person,
                            'Dept_Non_Internet_Application_Charge_after_person_surcharge' => $Dept_Non_Internet_Application_Charge_after_person_surcharge,

                            'Dept_Additional_Applicant_Charge_18_Plus' => $requestData['Dept_Additional_Applicant_Charge_18_Plus'],
                            'Dept_Additional_Applicant_Charge_18_Plus_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'],
                            'Dept_Additional_Applicant_Charge_18_Plus_after_person' => $Dept_Additional_Applicant_Charge_18_Plus_after_person,
                            'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge' => $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge,

                            'Dept_Additional_Applicant_Charge_Under_18' => $requestData['Dept_Additional_Applicant_Charge_Under_18'],
                            'Dept_Additional_Applicant_Charge_Under_18_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'],
                            'Dept_Additional_Applicant_Charge_Under_18_after_person' => $Dept_Additional_Applicant_Charge_Under_18_after_person,
                            'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge' => $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge,

                            'Dept_Subsequent_Temp_Application_Charge' => $requestData['Dept_Subsequent_Temp_Application_Charge'],
                            'Dept_Subsequent_Temp_Application_Charge_no_of_person' => $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'],
                            'Dept_Subsequent_Temp_Application_Charge_after_person' => $Dept_Subsequent_Temp_Application_Charge_after_person,
                            'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge' => $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge,

                            'Dept_Second_VAC_Instalment_Charge_18_Plus' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'],
                            'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'],
                            'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person,
                            'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge,

                            'Dept_Second_VAC_Instalment_Under_18' => $requestData['Dept_Second_VAC_Instalment_Under_18'],
                            'Dept_Second_VAC_Instalment_Under_18_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'],
                            'Dept_Second_VAC_Instalment_Under_18_after_person' => $Dept_Second_VAC_Instalment_Under_18_after_person,
                            'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge' => $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge,

                            'Dept_Nomination_Application_Charge' => $requestData['Dept_Nomination_Application_Charge'],
                            'Dept_Sponsorship_Application_Charge' => $requestData['Dept_Sponsorship_Application_Charge'],
                            'saf_levy' => $isCompanyClientLead ? $safLevyForSaveLead : $costAssignment->saf_levy,
                            'Block_1_Ex_Tax' => $requestData['Block_1_Ex_Tax'],
                            'Block_2_Ex_Tax' => $requestData['Block_2_Ex_Tax'],
                            'Block_3_Ex_Tax' => $requestData['Block_3_Ex_Tax'],
                            'additional_fee_1' => $requestData['additional_fee_1'],
                            'TotalDoHACharges' => $TotalDoHACharges,
                            'TotalDoHASurcharges' => $TotalDoHASurcharges,
                            'TotalBLOCKFEE' => $TotalBLOCKFEE
                        ]);
                    }
                }
                else
                {
                    //insert
                    $obj = new CostAssignmentForm;
                    $obj->client_id = $requestData['client_id'];
                    $obj->client_matter_id = $lastInsertedId;
                    $obj->agent_id = $requestData['migration_agent'];
                    $obj->surcharge = $surcharge;
                    
                    $obj->Dept_Base_Application_Charge = $requestData['Dept_Base_Application_Charge'];
                    $obj->Dept_Base_Application_Charge_no_of_person = $requestData['Dept_Base_Application_Charge_no_of_person'];
                    $obj->Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge_after_person;
                    $obj->Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person_surcharge;

                    $obj->Dept_Non_Internet_Application_Charge = $requestData['Dept_Non_Internet_Application_Charge'];
                    $obj->Dept_Non_Internet_Application_Charge_no_of_person = $requestData['Dept_Non_Internet_Application_Charge_no_of_person'];
                    $obj->Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge_after_person;
                    $obj->Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Charge_after_person_surcharge;

                    $obj->Dept_Additional_Applicant_Charge_18_Plus = $requestData['Dept_Additional_Applicant_Charge_18_Plus'];
                    $obj->Dept_Additional_Applicant_Charge_18_Plus_no_of_person = $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'];
                    $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus_after_person;
                    $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge;

                    $obj->Dept_Additional_Applicant_Charge_Under_18 = $requestData['Dept_Additional_Applicant_Charge_Under_18'];
                    $obj->Dept_Additional_Applicant_Charge_Under_18_no_of_person = $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'];
                    $obj->Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18_after_person;
                    $obj->Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge;

                    $obj->Dept_Subsequent_Temp_Application_Charge = $requestData['Dept_Subsequent_Temp_Application_Charge'];
                    $obj->Dept_Subsequent_Temp_Application_Charge_no_of_person = $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'];
                    $obj->Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge_after_person;
                    $obj->Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge;

                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'];
                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'];
                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;
                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge;

                    $obj->Dept_Second_VAC_Instalment_Under_18 = $requestData['Dept_Second_VAC_Instalment_Under_18'];
                    $obj->Dept_Second_VAC_Instalment_Under_18_no_of_person = $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'];
                    $obj->Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18_after_person;
                    $obj->Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge;

                    $obj->Dept_Nomination_Application_Charge = $requestData['Dept_Nomination_Application_Charge'];
                    $obj->Dept_Sponsorship_Application_Charge = $requestData['Dept_Sponsorship_Application_Charge'];
                    if ($isCompanyClientLead) {
                        $obj->saf_levy = $safLevyForSaveLead;
                    }

                    $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'];
                    $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'];
                    $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'];
                    $obj->additional_fee_1 = $requestData['additional_fee_1'];
                    $obj->TotalDoHACharges = $TotalDoHACharges;
                    $obj->TotalDoHASurcharges = $TotalDoHASurcharges;
                    $obj->TotalBLOCKFEE = $TotalBLOCKFEE;
                    $saved = $obj->save();
                }
                if (!$saved) 
                {
                    $response['status'] 	= 	false;
                    $response['message']	=	'Cost assignment not added successfully.Please try again';
                } 
                else 
                {
                    $response['status'] 	= 	true;
                    $response['message']	=	'Cost assignment added successfully';
                }
            }
        }
        echo json_encode($response);
    }

    //Get Cost assignment Migration Agent Detail Lead
    public function getCostAssignmentMigrationAgentDetailLead(Request $request)
    {
        $requestData = 	$request->all(); //dd($requestData);
        //get matter info
		$matterInfo = DB::table('matters')->where('id',$requestData['client_matter_id'])->first();
		//dd($matterInfo);
		if($matterInfo){
			$response['matterInfo'] = $matterInfo;
			$response['status'] 	= 	true;
			$response['message']	=	'Record is exist';
		} else {
			$response['matterInfo'] = "";
			$response['status'] 	= 	false;
			$response['message']	=	'Record is not exist.Please try again';
		}

		//get cost assignment matter fee
		$costassignmentmatterInfo = DB::table('cost_assignment_forms')->where('client_id',$requestData['client_id'])->where('client_matter_id',$requestData['client_matter_id'])->first();
		//dd($costassignmentmatterInfo);
		if($costassignmentmatterInfo){
			$response['cost_assignment_matterInfo'] = $costassignmentmatterInfo;
		} else {
			$response['cost_assignment_matterInfo'] = "";
		}
		echo json_encode($response);
    }

    //Upload agreement in PDF
    public function uploadAgreement(Request $request, Admin $admin)
    {
        //1. Validate only PDF files (max 10MB)
        $request->validate([
            'agreement_doc' => 'required|mimes:pdf|max:10240', // 10MB max
        ]);

        $requestData = $request->all();
        $pdfFile = $request->file('agreement_doc');

        //2. Get file details
        $originalName = $pdfFile->getClientOriginalName();
        $size = $pdfFile->getSize();
        $timestampedName = time() . '_' . $originalName;

        //3. Build S3 path using client ID (admin is the client record)
        $clientUniqueId = $admin->client_id ?? "";
        $s3Path = $clientUniqueId . '/agreement/' . $timestampedName;

        //4. Upload directly to S3
        Storage::disk('s3')->put($s3Path, file_get_contents($pdfFile));

        //5. Save document details in DB
        $originalInfo = pathinfo($originalName);
        $doc = new \App\Models\Document;
        $doc->file_name = $originalInfo['filename']; // e.g., "passport" (without extension)
        $doc->filetype = 'pdf';
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $doc->myfile = $disk->url($s3Path);
        $doc->myfile_key = $timestampedName;
        $doc->user_id = Auth::user()->id;
        $doc->client_id = $admin->id;
        $doc->type = 'client';
        $doc->file_size = $size;
        $doc->doc_type = 'agreement';
        $doc->client_matter_id = $requestData['clientmatterid'];
        $saved = $doc->save();

        //6. Log activity if saved
        if ($saved) {
            $docName = htmlspecialchars($originalInfo['filename'] ?? pathinfo($originalName, PATHINFO_FILENAME));
            $desc = '<ul><li><strong>Document:</strong> ' . $docName . '.pdf</li><li><strong>Next:</strong> Place signature fields in the modal</li></ul>';
            \App\Models\ActivitiesLog::create([
                'client_id' => $admin->id,
                'created_by' => Auth::user()->id,
                'subject' => 'uploaded visa agreement PDF for signature',
                'description' => $desc,
                'activity_type' => 'signature',
                'task_status' => 0,
                'pin' => 0,
            ]);
        }

        //7. Return success response with document ID for signature placement
        return response()->json([
            'status' => true,
            'message' => 'PDF agreement uploaded successfully!',
            'document_id' => $doc->id,
            'edit_url' => route('documents.edit', $doc->id)
        ]);
    }
    
 

    //Convert activity to note
	public function convertActivityToNote(Request $request){
		try {
			// Validate request
			$request->validate([
				'activity_id' => 'required|integer',
				'client_id' => 'required|integer',
				'client_matter_id' => 'required|integer',
				'note_type' => 'required|string'
			]);

			// Get the activity details
			$activity = \App\Models\ActivitiesLog::find($request->activity_id);
			if (!$activity) {
				return response()->json([
					'success' => false,
					'message' => 'Activity not found'
				]);
			}

			// Check if client matter exists
			$clientMatter = \App\Models\ClientMatter::find($request->client_matter_id);
			if (!$clientMatter) {
				return response()->json([
					'success' => false,
					'message' => 'Client matter not found'
				]);
			}

			// Create new note
            $note = new \App\Models\Note;
            $note->client_id = $request->client_id;
            $note->user_id = Auth::user()->id;
            $note->title = 'Matter Discussion';
            $note->description = $request->note_description; // Use processed description
            $note->matter_id = $request->client_matter_id;
            $note->type = 'client';
            $note->task_group = $request->note_type;
            $note->status = 1;
			
			$saved = $note->save();

			if ($saved) {
				// Create activity log for the conversion
				$activityLog = new \App\Models\ActivitiesLog;
				$activityLog->client_id = $request->client_id;
				$activityLog->created_by = Auth::user()->id;
				$activityLog->description = '<span class="text-semi-bold">Activity Converted to Note</span><p>Activity "' . $activity->subject . '" has been converted to a note.</p>';
				$activityLog->subject = 'converted activity to note';
				$activityLog->task_status = 0;
				$activityLog->pin = 0;
				$activityLog->save();

				// Update client matter timestamp
				$clientMatter->updated_at = date('Y-m-d H:i:s');
				$clientMatter->save();

				return response()->json([
					'success' => true,
					'message' => 'Activity successfully converted to note'
				]);
			} else {
				return response()->json([
					'success' => false,
					'message' => 'Failed to save note'
				]);
			}

		} catch (\Exception $e) {
			Log::error('Error converting activity to note: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'An error occurred while converting activity to note'
			]);
		}
	}
	
	//Get client matters for activity conversion
	public function getClientMatters($clientId){
		try {
			$clientMatters = DB::table('client_matters')
				->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
				->select('client_matters.id', 'client_matters.client_unique_matter_no', 'matters.title', 'client_matters.sel_matter_id')
				->where('client_matters.matter_status', 1)
				->where('client_matters.client_id', $clientId)
				->orderBy('client_matters.id', 'desc')
				->get();
			
			$matters = [];
			foreach($clientMatters as $matter){
				// If sel_matter_id is 1 or title is null, use "General Matter"
				$matterName = 'General Matter';
				if ($matter->sel_matter_id != 1 && !empty($matter->title)) {
					$matterName = $matter->title;
				}
				
				$displayName = $matterName . ' - ' . $matter->client_unique_matter_no;
				$matters[] = [
					'id' => $matter->id,
					'display_name' => $displayName,
					'client_unique_matter_no' => $matter->client_unique_matter_no
				];
			}
			
			return response()->json([
				'success' => true,
				'matters' => $matters
			]);
			
		} catch (\Exception $e) {
			Log::error('Error fetching client matters: ' . $e->getMessage());
			return response()->json([
				'success' => false,
				'message' => 'An error occurred while fetching client matters'
			]);
		}
	}


    /**
     * Decode string helper method - consistent with other controllers
     * 
     * @param string|null $string
     * @return string|false
     */
    public function decodeString($string = null)
    {
        if (empty($string)) {
            return false;
        }
        
        if (base64_encode(base64_decode($string, true)) === $string) {
            return convert_uudecode(base64_decode($string));
        }
        
        return false;
    }

    // Service Taken methods REMOVED - client_service_takens table does not exist
    // Model clientServiceTaken.php deleted, table was never created in database
    // Methods removed: createservicetaken(), removeservicetaken(), getservicetaken()
    // Routes removed from routes/clients.php
    // Modals removed from detail.blade.php and companies/detail.blade.php

    /**
     * Change client type (lead to client conversion)
     */
    public function changetype(Request $request,$id = Null, $slug = Null){
        Log::info('ConvertLeadToClient: changetype called', ['id_raw' => $id, 'slug' => $slug, 'query' => $request->query()]);
        if(isset($id) && !empty($id)) {
            $id = $this->decodeString($id);
            Log::info('ConvertLeadToClient: decoded id', ['decoded_id' => $id]);
            if(Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->exists()) {
                $obj = Admin::find($id);
                $client_type = $obj->type;
                Log::info('ConvertLeadToClient: admin found', ['admin_id' => $id, 'client_type' => $client_type]);
                if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                    return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
                }
                if($slug == 'client') {
                    // Cross-check: form client_id must match the URL-encoded id
                    if ((int) ($request['client_id'] ?? 0) !== (int) $id) {
                        Log::warning('ConvertLeadToClient: client_id mismatch', ['url_id' => $id, 'form_client_id' => $request['client_id']]);
                        return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with('error', 'Invalid request.');
                    }
                    $matterIdChangetype = (int) ($request['matter_id'] ?? 0);
                    if (! Matter::allowedForClientIsCompany($matterIdChangetype, (bool) $obj->is_company)) {
                        return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with('error', 'This matter type is not valid for this client record.');
                    }

                    $obj->type = $slug;
                    $obj->user_id = $request['user_id'];
                    $saved = $obj->save();
                    Log::info('ConvertLeadToClient: admin type updated to client', ['saved' => $saved]);

                    $matter = new ClientMatter();
                    $matter->user_id = $request['user_id'];
                    $matter->client_id = (int) $id;
                    $matter->office_id = $request['office_id'] ?? optional(Auth::user())->office_id ?? null;
                    $matter->sel_migration_agent = $request['migration_agent'];
                    $matter->sel_person_responsible = $request['person_responsible'];
                    $matter->sel_person_assisting = $request['person_assisting'];
                    $matter->sel_matter_id = $request['matter_id'];
                    Log::info('ConvertLeadToClient: matter payload', [
                        'client_id' => $request['client_id'],
                        'user_id' => $request['user_id'],
                        'matter_id' => $request['matter_id'],
                        'office_id' => $matter->office_id,
                        'migration_agent' => $request['migration_agent'],
                    ]);

                    $client_matters_cnt_per_client = DB::table('client_matters')->select('id')->where('sel_matter_id',$request['matter_id'])->where('client_id',(int) $id)->count();
                    $client_matters_current_no = $client_matters_cnt_per_client+1;
                    if($request['matter_id'] == 1) {
                        $matter->client_unique_matter_no = 'GN_'.$client_matters_current_no;
                    } else {
                        $matterInfo = Matter::select('nick_name')->where('id', '=', $request['matter_id'])->first();
                        $prefix = ($matterInfo && $matterInfo->nick_name) ? $matterInfo->nick_name : 'Matter';
                        $matter->client_unique_matter_no = $prefix."_".$client_matters_current_no;
                    }
                    Log::info('ConvertLeadToClient: client_unique_matter_no', ['client_unique_matter_no' => $matter->client_unique_matter_no]);

                    $matterType = Matter::find($request['matter_id']);
                    $workflowId = $matterType && $matterType->workflow_id ? $matterType->workflow_id : \App\Models\Workflow::where('name', 'General')->value('id');
                    $firstStageId = \App\Models\WorkflowStage::where('workflow_id', $workflowId)->orderByRaw('COALESCE(sort_order, id) ASC')->value('id')
                        ?? \App\Models\WorkflowStage::orderByRaw('COALESCE(sort_order, id) ASC')->value('id') ?? 1;
                    $matter->workflow_id = $workflowId;
                    $matter->workflow_stage_id = $firstStageId;
                    $matter->matter_status = 1; // Active by default
                    $matter->save();
                    Log::info('ConvertLeadToClient: matter saved', ['matter_id' => $matter->id]);

                    if($client_type == 'lead'){
                        $activity = new \App\Models\ActivitiesLog;
                        $activity->client_id = $request['client_id'];
                        $activity->created_by = Auth::user()->id;
                        $activity->subject = 'Lead converted to client. Matter '.$matter->client_unique_matter_no. ' created';
                        $activity->description = 'Lead converted to client. Matter '.$matter->client_unique_matter_no. ' created';
                        $activity->task_status = 0;
                        $activity->pin = 0;
                        $activity->save();

                        $msg = 'Lead converted to client. Matter '.$matter->client_unique_matter_no. ' created';

                        // When cp_status=2 (approval pending), send push + in-app notification to client
                        if ((int) ($obj->cp_status ?? 0) === 2) {
                            $notificationMessage = 'Lead converted to client. Matter ' . $matter->client_unique_matter_no . ' created.';
                            $path = '/clients/detail/' . base64_encode(convert_uuencode($request['client_id'])) . '/' . $matter->client_unique_matter_no . '/client_portal';
                            DB::table('notifications')->insert([
                                'sender_id' => Auth::user()->id,
                                'receiver_id' => $request['client_id'],
                                'module_id' => $matter->id,
                                'url' => $path,
                                'notification_type' => 'lead_converted_to_client',
                                'message' => $notificationMessage,
                                'created_at' => now(),
                                'updated_at' => now(),
                                'sender_status' => 1,
                                'receiver_status' => 0,
                                'seen' => 0,
                            ]);
                            try {
                                $fcmService = new FCMService();
                                $fcmService->sendToUser($request['client_id'], 'Lead converted to client', $notificationMessage, [
                                    'type' => 'lead_converted_to_client',
                                    'client_matter_id' => (string) $matter->id,
                                    'message' => $notificationMessage,
                                ]);
                            } catch (\Exception $e) {
                                Log::warning('Failed to send push notification for lead converted to client', [
                                    'client_id' => $request['client_id'],
                                    'matter_id' => $matter->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            // Update cp_status to 1 (approved/active) after conversion when it was 2 (approval pending)
                            $obj->cp_status = 1;
                            $obj->save();
                        }
                    }  else if($client_type == 'client'){
                        $activity = new \App\Models\ActivitiesLog;
                        $activity->client_id = $request['client_id'];
                        $activity->created_by = Auth::user()->id;
                        $activity->subject = 'Matter '.$matter->client_unique_matter_no. ' created';
                        $activity->description = 'Matter '.$matter->client_unique_matter_no. ' created';
                        $activity->task_status = 0;
                        $activity->pin = 0;
                        $activity->save();

                        $msg = 'Matter '.$matter->client_unique_matter_no. ' created';
                    }
                    // Redirect with matter number in URL
                    $redirectUrl = '/clients/detail/'.base64_encode(convert_uuencode(@$id)).'/'.$matter->client_unique_matter_no;
                    Log::info('ConvertLeadToClient: success, redirecting', ['redirect_url' => $redirectUrl]);
                    return Redirect::to($redirectUrl)->with('success', $msg);
                } else if($slug == 'lead' ) {
                    $activeMatters = ClientMatter::where('client_id', (int) $id)->where('matter_status', 1)->count();
                    if ($activeMatters > 0) {
                        Log::info('ConvertLeadToClient: blocked revert to lead — active matters', ['admin_id' => $id, 'active_matters' => $activeMatters]);

                        return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with(
                            'error',
                            "Cannot revert to lead while {$activeMatters} active matter(s) exist. Close or set matters inactive first."
                        );
                    }
                    $obj->type = $slug;
                    $obj->user_id = "";
                    $saved = $obj->save();
                    Log::info('ConvertLeadToClient: reverted to lead');
                }
                Log::info('ConvertLeadToClient: redirecting to detail (slug was '.$slug.')');
                return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with('success', 'Record Updated successfully');
            } else {
                Log::warning('ConvertLeadToClient: admin not found or wrong type', ['id' => $id]);
                return Redirect::to('/clients')->with('error', 'Clients Not Exist');
            }
        } else {
                Log::warning('ConvertLeadToClient: missing or empty id', ['id' => $id]);
                return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
            }
	}

    /**
     * Convert lead to client only (no new matter - for leads who already have matters from cost assignment)
     */
    public function convertLeadOnly(Request $request)
    {
        $clientId = $request->input('client_id');
        if (empty($clientId)) {
            return redirect()->back()->with('error', 'Client ID is required.');
        }
        $obj = Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
        if (!$obj || $obj->type !== 'lead') {
            return redirect()->back()->with('error', 'Only leads can be converted.');
        }
        $obj->type = 'client';
        $obj->user_id = $request->input('user_id', Auth::user()->id);
        $obj->save();

        $activity = new \App\Models\ActivitiesLog;
        $activity->client_id = $clientId;
        $activity->created_by = Auth::user()->id;
        $activity->subject = 'Lead converted to client';
        $activity->description = 'Lead converted to client';
        $activity->task_status = 0;
        $activity->pin = 0;
        $activity->save();

        $firstMatter = \App\Models\ClientMatter::where('client_id', $clientId)->where('matter_status', 1)->orderBy('id')->first();
        $redirectUrl = '/clients/detail/' . base64_encode(convert_uuencode($clientId));
        if ($firstMatter) {
            $redirectUrl .= '/' . $firstMatter->client_unique_matter_no;
        }

        // When cp_status=2 (approval pending), send push + in-app notification to client
        if ((int) ($obj->cp_status ?? 0) === 2) {
            $notificationMessage = 'Lead converted to client.';
            $path = $redirectUrl . '/client_portal';
            DB::table('notifications')->insert([
                'sender_id' => Auth::user()->id,
                'receiver_id' => $clientId,
                'module_id' => $firstMatter ? $firstMatter->id : null,
                'url' => $path,
                'notification_type' => 'lead_converted_to_client',
                'message' => $notificationMessage,
                'created_at' => now(),
                'updated_at' => now(),
                'sender_status' => 1,
                'receiver_status' => 0,
                'seen' => 0,
            ]);
            try {
                $fcmService = new FCMService();
                $fcmService->sendToUser($clientId, 'Lead converted to client', $notificationMessage, [
                    'type' => 'lead_converted_to_client',
                    'client_matter_id' => $firstMatter ? (string) $firstMatter->id : '',
                    'message' => $notificationMessage,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to send push notification for lead converted to client (convertLeadOnly)', [
                    'client_id' => $clientId,
                    'error' => $e->getMessage(),
                ]);
            }
            // Update cp_status to 1 (approved/active) after conversion when it was 2 (approval pending)
            $obj->cp_status = 1;
            $obj->save();
        }

        return redirect($redirectUrl)->with('success', 'Lead converted to client.');
    }

    /**
     * Store action with assignee information
     * Handles the "Assign Staff" popup functionality
     * Supports both single and multiple assignees
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function actionStore(Request $request)
    {
        try {
            $requestData = $request->all();
            
            // Validate required fields
            if (empty($requestData['client_id'])) {
                echo json_encode(array('success' => false, 'message' => 'Client ID is required'));
                exit;
            }
            
            // Decode the client ID
            $clientId = $this->decodeString($requestData['client_id']);
            
            // Validate decoded client ID
            if ($clientId === false || empty($clientId)) {
                echo json_encode(array('success' => false, 'message' => 'Invalid client ID'));
                exit;
            }
            
            // Handle rem_cat - ensure it exists and is an array (PostgreSQL migration pattern)
            $remCat = $requestData['rem_cat'] ?? [];
            if (!is_array($remCat)) {
                // If it's a single value, convert to array
                $remCat = !empty($remCat) ? [$remCat] : [];
            }
            
            // Validate that at least one assignee is selected
            if (empty($remCat)) {
                echo json_encode(array('success' => false, 'message' => 'At least one assignee must be selected'));
                exit;
            }

            $targetClient = $this->findClientOrLeadForAction((int) $clientId);
            if (! $targetClient) {
                echo json_encode(array('success' => false, 'message' => 'Client or lead not found'));
                exit;
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                echo json_encode(array('success' => false, 'message' => config('constants.unauthorized')));
                exit;
            }
            $clientLabel = $this->actionClientDisplayName($targetClient);
            
            // Get the next unique ID for this action
            $actionUniqueId = 'group_' . uniqid('', true);

            // Loop through each assignee and create an action
            foreach ($remCat as $assigneeId) {
                // Create a new action for each assignee
                $action = new \App\Models\Note;
                $action->client_id = $clientId;
                $action->user_id = Auth::user()->id;
                $action->description = $requestData['description'] ?? '';
                $action->unique_group_id = $actionUniqueId;

                // Set the title for the current assignee
                $assigneeName = $this->getAssigneeName($assigneeId);
                $defaultTitle = ($clientLabel !== '' ? $clientLabel . ': ' : '') . 'Assigned to ' . $assigneeName;
                $action->title = ! empty($requestData['remindersubject']) ? $requestData['remindersubject'] : $defaultTitle;

                // PostgreSQL NOT NULL constraints - must set these fields (Notes Table pattern)
                $action->is_action = 1; // This is an action
                $action->pin = 0; // Default to not pinned
                $action->status = '0'; // Default status (string '0' = active, '1' = completed)
                $action->type = 'client';
                $action->task_group = $requestData['task_group'] ?? null;
                $action->assigned_to = $assigneeId;

                if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                    $action->action_date = $requestData['followup_datetime'];
                }

                //add note deadline
                if(isset($requestData['note_deadline_checkbox']) && $requestData['note_deadline_checkbox'] != ''){
                    if($requestData['note_deadline_checkbox'] == 1){
                        $action->note_deadline = $requestData['note_deadline'] ?? null;
                    } else {
                        $action->note_deadline = NULL;
                    }
                } else {
                    $action->note_deadline = NULL;
                }

                $saved = $action->save();

                if ($saved) {
                    // Update lead action date
                    if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                        $targetClient->followup_date = $requestData['followup_datetime'];
                        $targetClient->save();
                    }

                    // Create a notification for the current assignee
                    $o = new \App\Models\Notification;
                    $o->sender_id = Auth::user()->id;
                    $o->receiver_id = $assigneeId;
                    $o->module_id = $clientId;
                    $o->url = URL::to('/clients/detail/' . $requestData['client_id']);
                    $o->notification_type = 'client';
                    $o->receiver_status = 0; // Unread
                    $o->seen = 0; // Not seen
                    
                    $actionDateTime = $requestData['followup_datetime'] ?? now();
                    try {
                        if (is_numeric($actionDateTime)) {
                            $formattedDate = date('d/M/Y h:i A', $actionDateTime);
                        } else {
                            $timestamp = strtotime($actionDateTime);
                            $formattedDate = $timestamp !== false ? date('d/M/Y h:i A', $timestamp) : date('d/M/Y h:i A');
                        }
                    } catch (\Exception $dateEx) {
                        $formattedDate = date('d/M/Y h:i A');
                    }
                    
                    $o->message = ($clientLabel !== '' ? 'Action for ' . $clientLabel . '. ' : '')
                        . 'Assigned by ' . Auth::user()->first_name . ' ' . Auth::user()->last_name . ' on ' . $formattedDate;
                    $o->save();

                    // Log the activity for the current assignee
                    $objs = new ActivitiesLog;
                    $objs->client_id = $clientId;
                    $objs->created_by = Auth::user()->id;
                    $objs->subject = 'Set action for ' . $assigneeName;
                    $objs->description = '<span class="text-semi-bold">' . ($requestData['remindersubject'] ?? '') . '</span><p>' . ($requestData['description'] ?? '') . '</p>';
                    $objs->task_status = 0;
                    $objs->pin = 0;

                    if (Auth::user()->id != $assigneeId) {
                        $objs->use_for = $assigneeId;
                    } else {
                        $objs->use_for = "";
                    }

                    $objs->followup_date = $requestData['followup_datetime'] ?? null;
                    $objs->task_group = $requestData['task_group'] ?? null;
                    $objs->save();
                }
            }
            
            echo json_encode(array('success' => true, 'message' => 'successfully saved', 'clientID' => $requestData['client_id']));
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error in actionStore: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            echo json_encode(array('success' => false, 'message' => 'Error saving action. Please try again.'));
            exit;
        }
    }

    // Helper function to get assignee name
    protected function getAssigneeName($assigneeId)
    {
        $staff = \App\Models\Staff::find($assigneeId);
        return $staff ? $staff->first_name . ' ' . $staff->last_name : 'Unknown Assignee';
    }

    /**
     * Client/lead row for action APIs (with company for display name).
     */
    protected function findClientOrLeadForAction(int $id): ?Admin
    {
        return Admin::with('company')->whereIn('type', ['client', 'lead'])->find($id);
    }

    /**
     * Human label for actions/notifications: company name when is_company, else person name.
     */
    protected function actionClientDisplayName(Admin $client): string
    {
        $label = trim($client->company_name_or_personal_name);
        if ($label === '') {
            $label = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        }

        return $label;
    }

    /**
     * Save tags for a client
     * Handles the tag assignment functionality from the client detail modal
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function save_tag(Request $request)
    {
        try {
            // Validate required fields
            $request->validate([
                'client_id' => 'required|integer',
                'tag' => 'nullable|array',
            ]);

            $clientId = $request->input('client_id');
            $tags = $request->input('tag', []);
            $createNewAsRed = filter_var($request->input('create_new_as_red', false), FILTER_VALIDATE_BOOLEAN);

            // Find the client
            $client = \App\Models\Admin::where('id', $clientId)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (!$client) {
                return redirect()->back()->with('error', 'Client not found');
            }

            // Process tags - create new ones if they don't exist, get IDs for existing ones
            $tagIds = [];
            if (!empty($tags) && is_array($tags)) {
                foreach ($tags as $tagValue) {
                    if (!empty($tagValue)) {
                        // Check if tag exists by name first
                        $existingTag = \App\Models\Tag::where('name', $tagValue)->first();
                        
                        if ($existingTag) {
                            // Tag exists, use its ID
                            $tagIds[] = $existingTag->id;
                        } else {
                            // Check if it's an ID (numeric)
                            if (is_numeric($tagValue)) {
                                $tagById = \App\Models\Tag::find($tagValue);
                                if ($tagById) {
                                    $tagIds[] = $tagById->id;
                                }
                            } else {
                                // Create new tag (as normal or red based on create_new_as_red flag)
                                $newTag = new \App\Models\Tag();
                                $newTag->name = $tagValue;
                                $newTag->created_by = Auth::id();
                                if ($createNewAsRed) {
                                    $newTag->tag_type = \App\Models\Tag::TYPE_RED;
                                    $newTag->is_hidden = true;
                                } else {
                                    $newTag->tag_type = \App\Models\Tag::TYPE_NORMAL;
                                    $newTag->is_hidden = false;
                                }
                                $newTag->save();
                                $tagIds[] = $newTag->id;
                            }
                        }
                    }
                }
            }

            // Update the client's tagname field with tag IDs
            $client->tagname = implode(',', $tagIds);
            $client->save();

            return redirect()->back()->with('success', 'Tags saved successfully');

        } catch (\Exception $e) {
            Log::error('Error saving tags: ' . $e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while saving tags');
        }
    }

    /**
     * Store personal action (Add My Action functionality)
     * Used by: action.blade.php
     */
    public function storePersonalAction(Request $request)
    {
        try {
            $requestData = $request->all();
            
            // Decode the client ID - handle empty/null for personal actions
            $clientId = null;
            $encodedClientId = null;
            
            if (!empty($requestData['client_id'])) {
                // Extract just the encoded part (format: "ENCODED/Matter/NO" or "ENCODED/Client")
                $clientIdParts = explode('/', $requestData['client_id']);
                $encodedClientId = $clientIdParts[0];
                $decodedClient = $this->decodeString($encodedClientId);
                if ($decodedClient === false || $decodedClient === '') {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $decodedClient;
            }

            // Generate unique action ID
            $actionUniqueId = 'group_' . uniqid('', true);

            $clientLabel = '';
            $targetClient = null;
            if ($clientId !== null) {
                $targetClient = $this->findClientOrLeadForAction((int) $clientId);
                if (! $targetClient) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetClient);
            }

            // Handle single or multiple assignees
            $assignees = is_array($requestData['rem_cat']) ? $requestData['rem_cat'] : [$requestData['rem_cat']];

            // Loop through each assignee and create an action
            foreach ($assignees as $assigneeId) {
                // Create a new action for each assignee
                $action = new \App\Models\Note;
                $action->client_id = $clientId;
                $action->user_id = Auth::user()->id;
                $action->description = @$requestData['description'];
                $action->unique_group_id = $actionUniqueId;
                $action->is_action = 1;
                $action->type = 'client';
                $action->task_group = @$requestData['task_group'];
                $action->assigned_to = $assigneeId;
                $action->status = '0'; // Not completed
                $action->pin = 0; // Required field - default to not pinned
                $assigneeName = $this->getAssigneeName($assigneeId);
                $action->title = ($clientLabel !== '' ? $clientLabel . ': ' : '') . 'Assigned to ' . $assigneeName;
                
                if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                    $action->action_date = @$requestData['followup_datetime'];
                }

                $saved = $action->save();

                if ($saved) {
                    // Create a notification for the assignee
                    $notification = new \App\Models\Notification;
                    $notification->sender_id = Auth::user()->id;
                    $notification->receiver_id = $assigneeId;
                    $notification->module_id = $clientId;
                    
                    // Set URL based on whether client exists
                    if (!empty($requestData['client_id'])) {
                        $notification->url = URL::to('/clients/detail/' . $requestData['client_id']);
                    } else {
                        $notification->url = URL::to('/action');
                    }
                    
                    $notification->message = ($clientLabel !== '' ? 'Action for ' . $clientLabel . '. ' : '') . 'Assigned to you';
                    $notification->seen = 0;
                    $notification->save();
                }
            }

            return response()->json(['success' => true, 'message' => 'Action created successfully']);
        } catch (\Exception $e) {
            Log::error('Error in storePersonalAction: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json(['success' => false, 'message' => 'Error creating action: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update existing action
     * Used by: assign_by_me.blade.php
     */
    public function updateAction(Request $request)
    {
        $validated = $request->validate([
            'note_id' => 'required|integer|exists:notes,id',
            'description' => 'required|string',
            'rem_cat' => 'required|integer|exists:staff,id',
            'task_group' => 'required|string|in:Call,Checklist,Review,Query,Urgent,Personal Action,Client Portal,Follow Up',
            'followup_datetime' => 'nullable|date',
            'client_id' => 'nullable|string',
        ]);

        $requestData = array_merge($request->all(), $validated);

        try {
            // Find the existing action
            $action = \App\Models\Note::findOrFail($validated['note_id']);

            if ((int) $action->is_action !== 1 || (string) $action->type !== 'client') {
                return response()->json(['success' => false, 'message' => 'Invalid task'], 400);
            }

            if ((string) $action->status === '1') {
                return response()->json(['success' => false, 'message' => 'This task is already completed'], 400);
            }

            $staff = Auth::user();
            if (! $action->canBeModifiedBy($staff)) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
            }
            
            // Decode the client ID - handle empty/null for personal actions
            $clientId = null;
            $clientLabel = '';
            if (!empty($requestData['client_id'])) {
                // Extract just the encoded part (format: "ENCODED/Matter/NO" or "ENCODED/Client")
                $clientIdParts = explode('/', $requestData['client_id']);
                $encodedClientId = $clientIdParts[0];
                $decodedId = $this->decodeString($encodedClientId);
                if ($decodedId === false || $decodedId === '') {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $decodedId;
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, $staff)) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetForAction);
            } elseif ($action->client_id !== null) {
                // Keep linked client when the form does not post client_id (avoids clearing the client by mistake)
                $clientId = (int) $action->client_id;
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, $staff)) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetForAction);
            }
            
            $originalAssigneeId = (int) $action->assigned_to;

            // Update action fields
            $action->description = @$requestData['description'];
            $action->client_id = $clientId;
            $action->task_group = @$requestData['task_group'];
            $action->assigned_to = @$requestData['rem_cat'];

            // Assigner Name uses notes.user_id — reflect the staff member who last reassigned the task.
            if ((int) $action->assigned_to !== $originalAssigneeId) {
                $action->user_id = Auth::user()->id;
            }
            
            if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                $action->action_date = @$requestData['followup_datetime'];
            }
            
            $action->save();

            // Create notification for the assignee if changed
            if ((int) $action->assigned_to !== $originalAssigneeId) {
                $notification = new \App\Models\Notification;
                $notification->sender_id = Auth::user()->id;
                $notification->receiver_id = $action->assigned_to;
                $notification->module_id = $clientId;

                if ($clientId !== null) {
                    $clientPath = !empty($requestData['client_id'])
                        ? $requestData['client_id']
                        : base64_encode(convert_uuencode($clientId));
                    $notification->url = URL::to('/clients/detail/' . $clientPath);
                } else {
                    $notification->url = URL::to('/action');
                }
                
                $notification->message = ($clientLabel !== '' ? 'Action for ' . $clientLabel . '. ' : '') . 'Updated — reassigned to you';
                $notification->seen = 0;
                $notification->save();
            }

            // Log to Activity Feed when action is updated (only for client-linked actions)
            if ($clientId !== null) {
                $assigneeName = $this->getAssigneeName($action->assigned_to);
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $clientId;
                $activityLog->created_by = Auth::user()->id;
                $activityLog->subject = 'Updated action for ' . $assigneeName;
                $activityLog->description = '<span class="text-semi-bold">' . ($action->task_group ?? '') . '</span><p>' . ($action->description ?? '') . '</p>';
                $activityLog->task_status = $action->status === '1' ? 1 : 0;
                $activityLog->pin = 0;
                if (Auth::user()->id != $action->assigned_to) {
                    $activityLog->use_for = $action->assigned_to;
                } else {
                    $activityLog->use_for = null;
                }
                $activityLog->followup_date = isset($action->action_date) ? $action->action_date : null;
                $activityLog->task_group = $action->task_group;
                $activityLog->save();
            }

            return response()->json(['success' => true, 'message' => 'Action updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating action: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reassign action (for completed actions)
     * Used by: action_completed.blade.php
     */
    public function reassignAction(Request $request)
    {
        try {
            $requestData = $request->all();
            
            // Decode the client ID - handle empty/null for personal actions
            $clientId = null;
            $clientLabel = '';
            if (!empty($requestData['client_id'])) {
                // Extract just the encoded part (format: "ENCODED/Matter/NO" or "ENCODED/Client")
                $clientIdParts = explode('/', $requestData['client_id']);
                $encodedClientId = $clientIdParts[0];
                $decodedId = $this->decodeString($encodedClientId);
                if ($decodedId === false || $decodedId === '') {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $decodedId;
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetForAction);
            }

            // Generate unique action ID
            $actionUniqueId = 'group_' . uniqid('', true);

            // Create a new action
            $action = new \App\Models\Note;
            $action->client_id = $clientId;
            $action->user_id = Auth::user()->id;
            $action->description = @$requestData['description'];
            $action->unique_group_id = $actionUniqueId;
            $action->is_action = 1;
            $action->type = 'client';
            $action->task_group = @$requestData['task_group'];
            $action->assigned_to = @$requestData['rem_cat'];
            $action->status = '0'; // Not completed
            $action->pin = 0; // Required field - default to not pinned
            $assigneeName = $this->getAssigneeName($action->assigned_to);
            $action->title = ($clientLabel !== '' ? $clientLabel . ': ' : '') . 'Assigned to ' . $assigneeName;
            
            if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                $action->action_date = @$requestData['followup_datetime'];
            }

            $saved = $action->save();

            if ($saved) {
                // Create a notification for the assignee
                $notification = new \App\Models\Notification;
                $notification->sender_id = Auth::user()->id;
                $notification->receiver_id = $action->assigned_to;
                $notification->module_id = $clientId;
                
                // Set URL based on whether client exists
                if (!empty($requestData['client_id'])) {
                    $notification->url = URL::to('/clients/detail/' . $requestData['client_id']);
                } else {
                    $notification->url = URL::to('/action');
                }
                
                $notification->message = ($clientLabel !== '' ? 'Action for ' . $clientLabel . '. ' : '') . 'Assigned to you';
                $notification->seen = 0;
                $notification->save();
            }

            return response()->json(['success' => true, 'message' => 'Action created successfully']);
        } catch (\Exception $e) {
            Log::error('Error in reassignAction: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return response()->json(['success' => false, 'message' => 'Error creating action: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Test Python Accounting Processing
     * 
     * This is a test endpoint to experiment with Python-based accounting processing
     * Can be used to test data export, analytics, report generation, etc.
     */
    public function testPythonAccounting(Request $request)
    {
        try {
            $clientId = $request->input('client_id');
            $matterId = $request->input('matter_id');
            $processingType = $request->input('processing_type', 'analytics'); // analytics, export, report
            
            // Get accounting data
            $clientReceipts = DB::table('account_client_receipts')
                ->where('client_id', $clientId)
                ->where('client_matter_id', $matterId)
                ->get();
            
            $startTime = microtime(true);
            
            // Prepare data for Python service
            $accountingData = [
                'client_id' => $clientId,
                'matter_id' => $matterId,
                'receipts' => $clientReceipts->toArray(),
                'processing_type' => $processingType
            ];
            
            // TODO: Call Python service for processing
            // Example:
            // $pythonService = app(\App\Services\PythonService::class);
            // $result = $pythonService->processAccountingData($accountingData);
            
            // For now, return mock response
            $endTime = microtime(true);
            $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            return response()->json([
                'success' => true,
                'message' => 'Test completed successfully',
                'data' => [
                    'processing_time_ms' => round($processingTime, 2),
                    'records_count' => $clientReceipts->count(),
                    'processing_type' => $processingType,
                    'php_processing' => true,
                    'python_service_available' => false, // Will be true when Python service is integrated
                ],
                'note' => 'This is a test endpoint. Integrate with Python service for actual processing.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Test Python Accounting Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error during test processing',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update office assignment for a matter
     * POST /matters/update-office
     */
    public function updateMatterOffice(Request $request)
    {
        try {
            $this->validate($request, [
                'matter_id' => 'required|exists:client_matters,id',
                'office_id' => 'required|exists:branches,id',
            ]);
            
            $matter = ClientMatter::findOrFail($request->matter_id);
            $oldOffice = $matter->office ? $matter->office->office_name : 'None';
            $newOffice = Branch::findOrFail($request->office_id);
            
            // Update matter
            $matter->office_id = $request->office_id;
            $matter->save();
            
            // Log activity
            $activitySubject = $oldOffice === 'None' 
                ? "assigned matter to {$newOffice->office_name} office"
                : "changed matter office from {$oldOffice} to {$newOffice->office_name}";
            
            if (!empty($request->notes)) {
                $activitySubject .= " - Notes: {$request->notes}";
            }
            
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $matter->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->subject = $activitySubject;
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Office assigned successfully',
                'office_name' => $newOffice->office_name,
                'office_id' => $newOffice->id
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', $e->errors())
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating matter office: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign office: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add appointment (legacy appointment system using Note model)
     * POST /add-appointment
     */
    public function addAppointment(Request $request)
    {
        try {
            $requestData = $request->all();
            
            // Validate required fields
            $validator = Validator::make($requestData, [
                'client_id' => 'required|exists:admins,id',
                'title' => 'required|string|max:255',
                'appoint_date' => 'required|date',
                'appoint_time' => 'required',
                'timezone' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Combine date and time into datetime
            $appointmentDateTime = $requestData['appoint_date'] . ' ' . $requestData['appoint_time'];
            // Parse the datetime in the user's selected timezone, then convert to UTC for storage
            $followupDateTime = Carbon::createFromFormat('Y-m-d H:i', $appointmentDateTime, $requestData['timezone'])
                ->setTimezone(config('app.timezone', 'UTC'));
            
            // Create appointment as Note record
            $appointment = new Note();
            $appointment->client_id = $requestData['client_id'];
            $appointment->user_id = Auth::id();
            $appointment->title = $requestData['title'];
            $appointment->description = $requestData['description'] ?? '';
            $appointment->action_date = $followupDateTime->toDateTimeString();
            $appointment->type = 'application'; // Legacy appointment type
            $appointment->is_action = 1; // Active action
            $appointment->status = 0; // Incomplete
            $appointment->pin = 0;
            
            // Set assigned_to from invitees if provided
            if (!empty($requestData['invitees'])) {
                $appointment->assigned_to = $requestData['invitees'];
            }
            
            $appointment->save();

            // Log activity
            $activityLog = new ActivitiesLog();
            $activityLog->client_id = $requestData['client_id'];
            $activityLog->created_by = Auth::id();
            $activityLog->subject = 'Appointment created: ' . $requestData['title'];
            $activityLog->description = $requestData['description'] ?? '';
            $activityLog->followup_date = $followupDateTime->toDateTimeString();
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();

            // Return JSON response matching expected format (status instead of success)
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => true,
                    'success' => true, // Also include for compatibility
                    'message' => 'Appointment created successfully'
                ]);
            }

            return redirect()->back()->with('success', 'Appointment created successfully');
            
        } catch (\Exception $e) {
            Log::error('Error creating appointment: ' . $e->getMessage());
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => false,
                    'success' => false, // Also include for compatibility
                    'message' => 'Failed to create appointment: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to create appointment: ' . $e->getMessage());
        }
    }

    /**
     * Add booking appointment (new booking system using BookingAppointment model)
     * POST /add-appointment-book
     */
    public function addAppointmentBook(Request $request)
    {
        try {
            $requestData = $request->all();
            
            // Validate required fields
            $validator = Validator::make($requestData, [
                'client_id' => 'required|exists:admins,id',
                'noe_id' => 'required|integer|in:1,2,3,4,5,6,7,8,9,10,11,12',
                'service_id' => 'required|integer|in:1,2,3',
                'appoint_date' => 'required|string', // Accept string format (dd/mm/yyyy), validate after conversion
                'appoint_time' => 'required|string',
                'description' => 'required|string',
                'appointment_details' => 'required|in:phone,in_person,video_call',
                'preferred_language' => 'required|string',
                'inperson_address' => 'required|in:1,2',
                'send_confirmation_email' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed: ' . $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            // NOE 6 & 7: paid Comprehensive Migration Advice only (form service_id 2)
            if (in_array((int) $requestData['noe_id'], [6, 7], true) && (int) $requestData['service_id'] !== 2) {
                return response()->json([
                    'status' => false,
                    'message' => 'For this nature of enquiry, only Comprehensive Migration Advice is available.',
                    'errors' => ['service_id' => ['Only Comprehensive Migration Advice is available for this nature of enquiry.']],
                ], 422);
            }

            // Get client information
            $client = Admin::findOrFail($requestData['client_id']);
            
            // Validate client has required fields
            $clientName = trim($client->first_name . ' ' . ($client->last_name ?? ''));
            if (empty($clientName)) {
                $clientName = $client->email ?? 'Client ' . $client->id;
            }
            
            $clientEmail = $client->email ?? '';
            if (empty($clientEmail)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Client email is required. Please update client information first.'
                ], 422);
            }
            
            // Map service_id from form to actual service_id
            // Form: 1=Free Consultation, 2=Comprehensive Migration Advice, 3=Overseas Applicant Enquiry
            // DB: 1=Paid, 2=Free, 3=Paid Overseas
            $serviceIdMap = [
                1 => 2, // Free Consultation -> Free
                2 => 1, // Comprehensive Migration Advice -> Paid
                3 => 3, // Overseas Applicant Enquiry -> Paid Overseas
            ];
            $serviceId = $serviceIdMap[$requestData['service_id']] ?? 2;

            // Map NOE ID to service_type/enquiry_type
            // Note: enquiry_type values must match what Bansal API expects (e.g., 'pr_complex' not 'pr')
            $noeToServiceType = [
                1 => ['service_type' => 'GSM Visas: 491, 190, 189, 191', 'enquiry_type' => 'pr_complex'],
                2 => ['service_type' => 'TR: 485 visa', 'enquiry_type' => 'tr'],
                3 => ['service_type' => 'JRP/Skill Assessment', 'enquiry_type' => 'jrp'],
                4 => ['service_type' => 'Tourist Visa', 'enquiry_type' => 'tourist'],
                5 => ['service_type' => 'Education/Student Visa', 'enquiry_type' => 'education'],
                6 => ['service_type' => 'Complex Matters (ART, Protection visa, Federal Case)', 'enquiry_type' => 'complex'],
                7 => ['service_type' => 'Visa Cancellation/NOICC/Refusals', 'enquiry_type' => 'cancellation'],
                8 => ['service_type' => 'Anyone who is outside Australia', 'enquiry_type' => 'international'],
                9 => ['service_type' => 'EOI/ROI', 'enquiry_type' => 'eoi'],
                10 => ['service_type' => 'Employer Sponsored Visas: 494, 482, 186, DAMA', 'enquiry_type' => 'employer_sponsored'],
                11 => ['service_type' => 'Family Visas (Parent Visa, Partner Visa, Child Visa)', 'enquiry_type' => 'family_visas'],
                12 => ['service_type' => 'Citizenship', 'enquiry_type' => 'citizenship'],
            ];
            $serviceTypeMapping = $noeToServiceType[$requestData['noe_id']] ?? ['service_type' => 'Other', 'enquiry_type' => 'pr_complex']; // Default to pr_complex

            // Map location
            $locationMap = [1 => 'adelaide', 2 => 'melbourne'];
            $location = $locationMap[$requestData['inperson_address']] ?? 'melbourne';

            // Map meeting type
            $meetingTypeMap = [
                'phone' => 'phone',
                'in_person' => 'in_person',
                'video_call' => 'video',
            ];
            $meetingType = $meetingTypeMap[$requestData['appointment_details']] ?? 'in_person';

            // Parse appointment time - handle different formats
            // Time can be in format "10:00 AM - 10:15 AM" or "10:00 AM" or "10:00:00"
            $timeStr = trim($requestData['appoint_time']);
            
            // Extract start time if in range format (e.g., "10:00 AM - 10:15 AM")
            if (preg_match('/^([0-9]{1,2}:[0-9]{2}\s*(?:AM|PM)?)/i', $timeStr, $matches)) {
                $timeStr = trim($matches[1]);
            }
            
            // Parse time - handle 12-hour format with AM/PM
            try {
                if (preg_match('/(AM|PM)/i', $timeStr)) {
                    // 12-hour format with AM/PM
                    $parsedTime = Carbon::createFromFormat('g:i A', $timeStr);
                    $timeStr = $parsedTime->format('H:i');
                } else {
                    // 24-hour format - extract just HH:MM
                    if (preg_match('/^(\d{1,2}):(\d{2})/', $timeStr, $timeMatches)) {
                        $timeStr = $timeMatches[1] . ':' . $timeMatches[2];
                    }
                }
            } catch (\Exception $e) {
                // If parsing fails, try to extract HH:MM format
                if (preg_match('/^(\d{1,2}):(\d{2})/', $timeStr, $timeMatches)) {
                    $timeStr = $timeMatches[1] . ':' . $timeMatches[2];
                } else {
                    throw new \Exception('Invalid time format: ' . $requestData['appoint_time']);
                }
            }

            // Combine date and time
            $dateStr = $requestData['appoint_date'];
            $timezone = $requestData['timezone'] ?? 'Australia/Melbourne';
            
            // Convert date from dd/mm/yyyy to Y-m-d format if needed
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $dateMatches)) {
                // Date is in dd/mm/yyyy format, convert to Y-m-d
                $dateStr = $dateMatches[3] . '-' . $dateMatches[2] . '-' . $dateMatches[1];
            }
            
            try {
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr . ' ' . $timeStr, $timezone)
                    ->setTimezone(config('app.timezone', 'UTC'));
            } catch (\Exception $e) {
                // Try alternative date format
                try {
                    $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $timeStr . ':00', $timezone)
                        ->setTimezone(config('app.timezone', 'UTC'));
                } catch (\Exception $e2) {
                    throw new \Exception('Invalid date/time format. Date: ' . $requestData['appoint_date'] . ', Time: ' . $timeStr . '. Error: ' . $e2->getMessage());
                }
            }

            // Calculate duration based on service
            // Service 1 (Free Consultation) = 15 min, Service 2/3 (Paid) = 30 min
            $durationMinutes = $requestData['service_id'] == 1 ? 15 : 30;

            // Use ConsultantAssignmentService to assign consultant
            $consultantAssigner = app(\App\Services\BansalAppointmentSync\ConsultantAssignmentService::class);
            $specificServiceForCalendar = match ((int) $serviceId) {
                1 => 'paid-consultation',
                2 => 'consultation',
                3 => 'overseas-enquiry',
                default => 'consultation',
            };
            $appointmentDataForConsultant = [
                'noe_id' => $requestData['noe_id'],
                'service_id' => $serviceId,
                'location' => $location,
                'inperson_address' => $requestData['inperson_address'],
                'enquiry_type' => $serviceTypeMapping['enquiry_type'],
                'service_type' => $serviceTypeMapping['service_type'],
                'enquiry_details' => $requestData['description'] ?? '',
                'specific_service' => $specificServiceForCalendar,
                'preferred_language' => $requestData['preferred_language'],
                'is_paid' => ($serviceId == 2) ? false : true,
            ];
            $consultant = $consultantAssigner->assignConsultant($appointmentDataForConsultant);

            // Consultant is nullable, but log if not found
            if (!$consultant) {
                Log::warning('No consultant assigned for appointment', [
                    'noe_id' => $requestData['noe_id'],
                    'service_id' => $serviceId,
                    'location' => $location,
                    'inperson_address' => $requestData['inperson_address']
                ]);
            }

            // Map service_id to specific_service for Bansal API
            $specificServiceMap = [
                1 => 'paid-consultation',  // Paid Migration Advice
                2 => 'consultation',        // Free Consultation
                3 => 'overseas-enquiry',    // Overseas Applicant Enquiry
            ];
            $specificService = $specificServiceMap[$serviceId] ?? 'consultation';

            // Prepare appointment data for Bansal API
            // Format appointment date and time separately as API expects
            $appointmentDateForApi = $appointmentDateTime->copy()->setTimezone($timezone)->format('Y-m-d');
            
            // Format appointment time - API expects H:i format (without seconds) for validation
            // Extract the time from the parsed datetime in the original timezone
            $appointmentTimeForApi = $appointmentDateTime->copy()->setTimezone($timezone)->format('H:i');
            
            // Format appointment time slot for display (e.g., "1:00 PM-1:15 PM")
            $appointmentTimeSlot = $requestData['appoint_time'];

            // Build payload for Bansal API (matching the expected structure from API error response)
            $bansalApiPayload = [
                'full_name' => $clientName,
                'email' => $clientEmail,
                'phone' => $client->phone ?? '',
                'appointment_date' => $appointmentDateForApi,  // Required: YYYY-MM-DD format
                'appointment_time' => $appointmentTimeForApi, // Required: HH:MM:SS format
                'appointment_datetime' => $appointmentDateTime->copy()->setTimezone($timezone)->format('Y-m-d H:i:s'),
                'duration_minutes' => $durationMinutes,
                'location' => $location,
                'meeting_type' => $meetingType,
                'preferred_language' => $requestData['preferred_language'],
                'specific_service' => $specificService,
                'enquiry_type' => BansalSchedulingServiceType::bansalEnquiryTypeForApi(
                    $requestData['noe_id'],
                    $location,
                    $serviceTypeMapping['enquiry_type']
                ),
                'service_type' => BansalSchedulingServiceType::bansalServiceTypeForApi(
                    $requestData['noe_id'],
                    $serviceTypeMapping['service_type']
                ),
                'enquiry_details' => $requestData['description'],
                'is_paid' => ($serviceId == 2) ? false : true,
                'amount' => ($serviceId == 2) ? 0 : 150,
                'final_amount' => ($serviceId == 2) ? 0 : 150,
                'payment_status' => ($serviceId == 2) ? null : 'pending',
            ];

            // Call Bansal API to create appointment and get real bansal_appointment_id
            $bansalAppointmentId = null;
            $bansalApiError = null;
            
            try {
                $bansalApiClient = app(BansalApiClient::class);
                $bansalApiResponse = $bansalApiClient->createAppointment($bansalApiPayload);
                
                // Extract bansal_appointment_id from API response
                if (isset($bansalApiResponse['data']['id'])) {
                    $bansalAppointmentId = (int) $bansalApiResponse['data']['id'];
                } elseif (isset($bansalApiResponse['data']['appointment_id'])) {
                    $bansalAppointmentId = (int) $bansalApiResponse['data']['appointment_id'];
                } elseif (isset($bansalApiResponse['appointment_id'])) {
                    $bansalAppointmentId = (int) $bansalApiResponse['appointment_id'];
                } else {
                    throw new \Exception('Bansal API did not return appointment ID. Response: ' . json_encode($bansalApiResponse));
                }
                
                Log::info('Appointment created on Bansal website', [
                    'bansal_appointment_id' => $bansalAppointmentId,
                    'client_id' => $client->id,
                    'client_email' => $clientEmail
                ]);
            } catch (\Exception $apiException) {
                $bansalApiError = $apiException->getMessage();
                Log::error('Failed to create appointment on Bansal website via API', [
                    'error' => $bansalApiError,
                    'client_id' => $client->id,
                    'client_email' => $clientEmail,
                    'payload' => $bansalApiPayload,
                    'trace' => $apiException->getTraceAsString()
                ]);
                
                // If API call fails, we'll still create the appointment locally
                // but with a temporary ID that indicates it needs to be synced
                // This ensures existing functionality doesn't break
                $bansalAppointmentId = null; // Will be set to a placeholder if API fails
            }

            // If API call failed, use a placeholder ID that indicates manual creation
            // This allows the appointment to exist in CRM while we can retry API sync later
            if ($bansalAppointmentId === null) {
                // Generate temporary ID starting from 2000000 to distinguish from old system
                // This will be replaced when API sync succeeds
                $bansalAppointmentId = 2000000 + (time() % 900000) + mt_rand(1, 99999);
                
                // Ensure uniqueness
                while (BookingAppointment::where('bansal_appointment_id', $bansalAppointmentId)->exists()) {
                    $bansalAppointmentId = 2000000 + (time() % 900000) + mt_rand(1, 99999);
                }
                
                Log::warning('Using temporary bansal_appointment_id due to API failure', [
                    'temporary_id' => $bansalAppointmentId,
                    'api_error' => $bansalApiError,
                    'client_id' => $client->id
                ]);
            }

            // Create booking appointment
            $appointment = BookingAppointment::create([
                'bansal_appointment_id' => $bansalAppointmentId,
                'order_hash' => null, // No payment for manually created appointments
                
                'client_id' => $client->id,
                'consultant_id' => $consultant ? $consultant->id : null,
                'assigned_by_admin_id' => Auth::id() ?: null,
                
                'client_name' => $clientName,
                'client_email' => $clientEmail,
                'client_phone' => $client->phone ?? null,
                'client_timezone' => $requestData['timezone'] ?? 'Australia/Melbourne',
                
                'appointment_datetime' => $appointmentDateTime,
                'timeslot_full' => $requestData['appoint_time'], // Store as provided
                'duration_minutes' => $durationMinutes,
                'location' => $location,
                'inperson_address' => $requestData['inperson_address'],
                'meeting_type' => $meetingType,
                'preferred_language' => $requestData['preferred_language'],
                
                'service_id' => $serviceId,
                'noe_id' => $requestData['noe_id'],
                'enquiry_type' => $serviceTypeMapping['enquiry_type'],
                'service_type' => $serviceTypeMapping['service_type'],
                'enquiry_details' => $requestData['description'],
                
                // Determine status based on service type and payment status
                // Case 1: Free appointment (serviceId == 2) -> status = 'confirmed'
                // Case 2: Paid appointment (serviceId != 2) -> status = 'paid' if payment successful, 'pending' if payment failed
                'status' => ($serviceId == 2) 
                    ? 'confirmed' 
                    : (($requestData['payment_status'] ?? 'pending') === 'completed' ? 'paid' : 'pending'),
                'confirmed_at' => ($serviceId == 2) ? now() : null, // Set confirmed_at for free appointments
                'is_paid' => ($serviceId == 2) ? false : true, // Free service is not paid
                'amount' => ($serviceId == 2) ? 0 : 150, // Set appropriate amounts
                'final_amount' => ($serviceId == 2) ? 0 : 150,
                'payment_status' => ($serviceId == 2) ? null : ($requestData['payment_status'] ?? 'pending'),
                
                // Boolean fields with default values
                'confirmation_email_sent' => false,
                'reminder_sms_sent' => false,
                
                // Sync status tracking
                'sync_status' => $bansalApiError ? 'error' : 'synced',
                'sync_error' => $bansalApiError,
                'last_synced_at' => $bansalApiError ? null : now(),
                
                'user_id' => Auth::id(),
            ]);

            // Log activity with detailed appointment information
            $this->createActivityLogForBookingAppointment($appointment, $serviceId, $requestData['noe_id']);

            // Send confirmation email if checkbox was checked
            $confirmationEmailSent = false;
            $confirmationEmailFailed = false;
            if ($request->has('send_confirmation_email') && $request->boolean('send_confirmation_email')) {
                try {
                    $notificationService = app(\App\Services\BansalAppointmentSync\NotificationService::class);
                    $confirmationEmailSent = $notificationService->sendDetailedConfirmationEmail($appointment);
                    $confirmationEmailFailed = !$confirmationEmailSent;
                } catch (\Exception $e) {
                    $confirmationEmailFailed = true;
                    Log::error('Failed to send appointment confirmation email', [
                        'appointment_id' => $appointment->id,
                        'client_email' => $appointment->client_email,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Prepare response message
            if ($bansalApiError) {
                Log::warning('Appointment created locally but Bansal API sync failed', [
                    'appointment_id' => $appointment->id,
                    'bansal_appointment_id' => $bansalAppointmentId,
                    'api_error' => $bansalApiError
                ]);
            }
            $successMessage = 'Appointment booked successfully';
            if ($confirmationEmailFailed) {
                $successMessage = 'Appointment saved, but the confirmation email could not be sent.';
                if ($bansalApiError) {
                    $successMessage .= ' Note: Appointment created in CRM but could not be synced to Bansal website. Error: ' . $bansalApiError;
                }
            } elseif ($bansalApiError) {
                $successMessage .= '. Note: Appointment created in CRM but could not be synced to Bansal website. Error: ' . $bansalApiError;
            }

            // Return JSON response matching expected format
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => $successMessage,
                    'bansal_synced' => !$bansalApiError,
                    'bansal_appointment_id' => $bansalAppointmentId
                ]);
            }

            return redirect()->back()->with($bansalApiError ? 'warning' : 'success', $successMessage);
            
        } catch (\Exception $e) {
            Log::error('Error creating booking appointment: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'Failed to create appointment: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to create appointment: ' . $e->getMessage());
        }
    }

    /**
     * Get appointments HTML for a client (for AJAX refresh after booking)
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getAppointments(Request $request)
    {
        $clientId = $request->input('clientid');
        
        if (!$clientId) {
            return response()->json(['error' => 'Client ID is required'], 400);
        }

        // Get client
        $client = Admin::find($clientId);
        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        // Get appointments for this client
        $appointmentlists = BookingAppointment::where('client_id', $clientId)
            ->orderby('created_at', 'DESC')
            ->get();

        $appointmentlistslast = $appointmentlists->first();
        $appointmentdata = [];

        $html = '<div class="row">
            <div class="col-md-5 appointment_grid_list">';

        $rr = 0;
        foreach ($appointmentlists as $appointmentlist) {
            $admin = Staff::select('id', 'first_name', 'email')
                ->where('id', $appointmentlist->user_id)
                ->first();
            $first_name = $admin->first_name ?? 'N/A';
            $datetime = $appointmentlist->created_at;
            $timeago = \App\Http\Controllers\Controller::time_elapsed_string($datetime);

            // Extract start time from timeslot_full
            $appointmentTime = '';
            if ($appointmentlist->timeslot_full) {
                $timeslotParts = explode(' - ', $appointmentlist->timeslot_full);
                $appointmentTime = trim($timeslotParts[0] ?? '');
            }

            $appointmentdata[$appointmentlist->id] = [
                'title' => $appointmentlist->service_type ?? 'N/A',
                'time' => $appointmentTime,
                'date' => $appointmentlist->appointment_datetime ? date('d D, M Y', strtotime($appointmentlist->appointment_datetime)) : '',
                'description' => htmlspecialchars($appointmentlist->enquiry_details ?? '', ENT_QUOTES, 'UTF-8'),
                'createdby' => substr($first_name, 0, 1),
                'createdname' => $first_name,
                'createdemail' => $admin->email ?? 'N/A',
            ];

            $activeClass = ($rr == 0) ? 'active' : '';
            $appointmentDate = $appointmentlist->appointment_datetime ? date('d/m/Y', strtotime($appointmentlist->appointment_datetime)) : '';

            $html .= '<div class="appointmentdata ' . $activeClass . '" data-id="' . $appointmentlist->id . '">
                <div class="appointment_col">
                    <div class="appointdate">
                        <h5>' . $appointmentDate . '</h5>
                        <p>' . $appointmentTime . '<br>
                        <i><small>' . $timeago . '</small></i></p>
                    </div>
                    <div class="title_desc">
                        <h5>' . htmlspecialchars($appointmentlist->service_type) . '</h5>
                        <p>' . htmlspecialchars($appointmentlist->enquiry_details ?? '') . '</p>
                    </div>
                    <div class="appoint_created">
                        <span class="span_label">Created By:
                        <span>' . substr($first_name, 0, 1) . '</span></span>
                    </div>
                </div>
            </div>';
            
            $rr++;
        }

        $html .= '</div>
            <div class="col-md-7">
                <div class="editappointment">';

        if ($appointmentlistslast) {
            $adminfirst = Staff::select('id', 'first_name', 'email')
                ->where('id', $appointmentlistslast->user_id)
                ->first();
            
            $displayTimeLast = '';
            if ($appointmentlistslast->timeslot_full) {
                $timeslotPartsLast = explode(' - ', $appointmentlistslast->timeslot_full);
                $displayTimeLast = trim($timeslotPartsLast[0] ?? '');
            }
            
            $appointmentDateLast = $appointmentlistslast->appointment_datetime 
                ? date('d D, M Y', strtotime($appointmentlistslast->appointment_datetime)) 
                : '';

            $html .= '<div class="content">
                <h4 class="appointmentname">' . htmlspecialchars($appointmentlistslast->service_type) . '</h4>
                <div class="appitem">
                    ' . IconHelper::fromLegacy('fa fa-clock') . '
                    <span class="appcontent appointmenttime">' . $displayTimeLast . '</span>
                </div>
                <div class="appitem">
                    ' . IconHelper::fromLegacy('fa fa-calendar') . '
                    <span class="appcontent appointmentdate">' . $appointmentDateLast . '</span>
                </div>
                <div class="description appointmentdescription">
                    <p>' . htmlspecialchars($appointmentlistslast->enquiry_details ?? '') . '</p>
                </div>
                <div class="created_by">
                    <span class="label">Created By:</span>
                    <div class="createdby">
                        <span class="appointmentcreatedby">' . substr($adminfirst->first_name ?? 'N/A', 0, 1) . '</span>
                    </div>
                    <div class="createdinfo">
                        <a href="" class="appointmentcreatedname">' . htmlspecialchars($adminfirst->first_name ?? 'N/A') . '</a>
                        <p class="appointmentcreatedemail">' . htmlspecialchars($adminfirst->email ?? 'N/A') . '</p>
                    </div>
                </div>
            </div>';
        }

        $html .= '</div>
            </div>
        </div>';

        // Add JavaScript to update window.appointmentData
        $html .= '<script>
            window.appointmentData = ' . json_encode($appointmentdata, JSON_FORCE_OBJECT) . ';
        </script>';

        return $html;
    }

    /**
     * Create detailed activity log for booking appointment (manual creation from CRM)
     * 
     * @param BookingAppointment $appointment
     * @param int $serviceId
     * @param int $noeId
     * @return void
     */
    protected function createActivityLogForBookingAppointment(BookingAppointment $appointment, int $serviceId, int $noeId): void
    {
        // Determine subject based on service type
        $subject = 'scheduled an appointment';
        $serviceTitle = 'Appointment';
        
        if ($serviceId == 2) {
            $subject = 'scheduled an free appointment';
            $serviceTitle = 'Free Consultation';
        } elseif ($serviceId == 1) {
            $subject = 'scheduled an paid appointment';
            $serviceTitle = 'Comprehensive Migration Advice';
        } elseif ($serviceId == 3) {
            $subject = 'scheduled an paid appointment';
            $serviceTitle = 'Overseas Applicant Enquiry';
        }

        // Determine enquiry title based on noe_id
        $enquiryTitle = 'Appointment';
        if ($noeId == 1) {
            $enquiryTitle = 'GSM Visas: 491, 190, 189, 191';
        } elseif ($noeId == 2) {
            $enquiryTitle = 'TR: 485 visa';
        } elseif ($noeId == 3) {
            $enquiryTitle = 'JRP/Skill Assessment';
        } elseif ($noeId == 4) {
            $enquiryTitle = 'Tourist Visa';
        } elseif ($noeId == 5) {
            $enquiryTitle = 'Education/Course Change/Student Visa/Student Dependent Visa (for education selection only)';
        } elseif ($noeId == 6) {
            $enquiryTitle = 'Complex matters: ART, Protection visa, Federal Case';
        } elseif ($noeId == 7) {
            $enquiryTitle = 'Visa Cancellation/ NOICC/ Visa refusals';
        } elseif ($noeId == 8) {
            $enquiryTitle = 'Anyone who is outside Australia';
        } elseif ($noeId == 9) {
            $enquiryTitle = 'EOI/ROI';
        } elseif ($noeId == 10) {
            $enquiryTitle = 'Employer Sponsored Visas: 494, 482, 186, DAMA';
        } elseif ($noeId == 11) {
            $enquiryTitle = 'Family Visas (Parent Visa, Partner Visa, Child Visa)';
        } elseif ($noeId == 12) {
            $enquiryTitle = 'Citizenship';
        }

        // Format meeting type
        $appointmentDetails = '';
        if ($appointment->meeting_type) {
            $meetingType = strtolower($appointment->meeting_type);
            if ($meetingType === 'in_person') {
                $appointmentDetails = 'In Person';
            } elseif ($meetingType === 'phone') {
                $appointmentDetails = 'Phone';
            } elseif ($meetingType === 'video') {
                $appointmentDetails = 'Video Call';
            }
        }

        // Format appointment date
        $appointmentDate = $appointment->appointment_datetime;
        if ($appointmentDate instanceof Carbon) {
            $activityLogDate = $appointmentDate->format('Y-m-d');
        } elseif ($appointmentDate) {
            $activityLogDate = Carbon::parse($appointmentDate)->format('Y-m-d');
        } else {
            $activityLogDate = date('Y-m-d');
        }
        
        // Format appointment time
        $appointmentTime = $appointment->timeslot_full ?? '';
        if (empty($appointmentTime) && $appointmentDate) {
            if ($appointmentDate instanceof Carbon) {
                $appointmentTime = $appointmentDate->format('h:i A');
            } else {
                $appointmentTime = Carbon::parse($appointmentDate)->format('h:i A');
            }
        }

        // Get location display name
        $locationDisplay = '';
        if ($appointment->location) {
            $locationDisplay = ucfirst($appointment->location);
            if ($appointment->location === 'adelaide' && $appointment->service_id == 2) {
                $locationDisplay = 'Adelaide Free PR';
            } elseif ($appointment->location === 'melbourne' && $appointment->service_id == 2) {
                $locationDisplay = 'Melbourne Free PR';
            }
        }

        // Build description HTML (matching synced appointment format)
        $description = '<div style="display: -webkit-inline-box;">
                <span style="height: 60px; width: 60px; border: 1px solid rgb(3, 169, 244); border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2px;overflow: hidden;">
                    <span  style="flex: 1 1 0%; width: 100%; text-align: center; background: rgb(237, 237, 237); border-top-left-radius: 120px; border-top-right-radius: 120px; font-size: 12px;line-height: 24px;">
                        ' . date('d M', strtotime($activityLogDate)) . '
                    </span>
                    <span style="background: rgb(84, 178, 75); color: rgb(255, 255, 255); flex: 1 1 0%; width: 100%; border-bottom-left-radius: 120px; border-bottom-right-radius: 120px; text-align: center;font-size: 12px; line-height: 21px;">
                        ' . date('Y', strtotime($activityLogDate)) . '
                    </span>
                </span>
            </div>
            <div style="display:inline-grid;">
                <span class="text-semi-bold">' . e($enquiryTitle) . '</span> 
                <span class="text-semi-bold">' . e($serviceTitle) . '</span>';
        
        if ($appointmentDetails) {
            $description .= '  <span class="text-semi-bold">' . e($appointmentDetails) . '</span>';
        }
        
        if ($appointment->preferred_language) {
            $description .= '  <span class="text-semi-bold">' . e($appointment->preferred_language) . '</span>';
        }
        
        if ($appointment->enquiry_details) {
            $description .= '  <span class="text-semi-bold">' . e($appointment->enquiry_details) . '</span>';
        }
        
        if ($appointmentTime) {
            $description .= '  <p class="text-semi-light-grey col-v-1">@ ' . e($appointmentTime) . '</p>';
        }
        
        $description .= '</div>';

        // Get client name for subject
        $clientName = '';
        if ($appointment->client_id) {
            // Try to get client name from Admin model (first_name + last_name)
            $client = Admin::where('id', $appointment->client_id)
                ->whereIn('type', ['client', 'lead'])
                ->select('first_name', 'last_name')
                ->first();
            
            if ($client) {
                $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
            }
        }
        
        // Fallback to client_name field if Admin lookup didn't work
        if (empty($clientName) && $appointment->client_name) {
            $clientName = trim($appointment->client_name);
        }

        // Create activity log entry
        ActivitiesLog::create([
            'client_id' => $appointment->client_id,
            'created_by' => Auth::id(),
            'subject' => $subject,
            'description' => $description,
            'activity_type' => 'activity',
            'task_status' => 0,
            'pin' => 0,
        ]);
    }

    /**
     * Export client data to JSON file
     * 
     * @param string $id Encoded client ID
     * @return \Illuminate\Http\Response
     */
    public function export($id)
    {
        try {
            // Decode the client ID
            $clientId = $this->decodeString($id);
            
            if (!$clientId) {
                return redirect()->route('clients.index')
                    ->with('error', 'Invalid client ID.');
            }

            // Check if client exists
            $client = Admin::where('id', $clientId)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (!$client) {
                return redirect()->route('clients.index')
                    ->with('error', 'Client not found.');
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                return redirect()->route('clients.index')
                    ->with('error', config('constants.unauthorized'));
            }

            // Export client data
            $exportService = app(ClientExportService::class);
            $exportData = $exportService->exportClient($clientId);

            // Generate filename
            $filename = 'client_export_' . ($client->client_id ?? $clientId) . '_' . date('Y-m-d_His') . '.json';

            // Return JSON file download
            return response()->json($exportData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Client export error: ' . $e->getMessage(), [
                'client_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('clients.index')
                ->with('error', 'Failed to export client data: ' . $e->getMessage());
        }
    }

    /**
     * Import client data from JSON file
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function import(Request $request)
    {
        try {
            // Validate file upload (use extension check; mimes:json often fails when server reports .json as text/plain)
            $request->validate([
                'import_file' => [
                    'required',
                    'file',
                    'max:10240', // 10MB
                    function ($attribute, $value, $fail) {
                        $ext = strtolower($value->getClientOriginalExtension());
                        if ($ext !== 'json') {
                            $fail('The file must be a JSON file (.json).');
                        }
                    },
                ],
            ]);

            // Read and parse JSON file
            $file = $request->file('import_file');
            $jsonContent = file_get_contents($file->getRealPath());
            $importData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Invalid JSON file: ' . json_last_error_msg()])
                    ->withInput();
            }

            // Validate import data structure
            if (!isset($importData['client'])) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Invalid import file format: missing lead/client data'])
                    ->withInput();
            }

            // Check if lead email is required (email is unique and NOT NULL in admins table)
            if (empty($importData['client']['email'])) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Lead email is required and cannot be empty'])
                    ->withInput();
            }

            // Check if first_name is required
            if (empty($importData['client']['first_name'])) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Lead first name is required'])
                    ->withInput();
            }

            // Import as lead (JSON may contain client data; we always create a lead)
            $importData['client']['type'] = 'lead';
            $skipDuplicates = $request->has('skip_duplicates');
            $importService = app(ClientImportService::class);
            $result = $importService->importClient($importData, $skipDuplicates);

            if ($result['success']) {
                $message = 'Lead imported successfully. Lead ID: ' . ($result['client_id_reference'] ?? '');
                return redirect()->route('leads.index')
                    ->with('success', $message);
            } else {
                return redirect()->back()
                    ->withErrors(['import_file' => $result['message']])
                    ->withInput();
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Lead import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['import_file' => 'Failed to import lead: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Search for contact persons (clients/leads) by email, phone, name, or client ID
     * Used for company contact person selection
     * 
     * Search priority: Phone and Email are primary search fields
     */
    public function searchContactPerson(Request $request)
    {
        $query = $request->input('q', '');
        $excludeId = $request->input('exclude_id'); // Exclude current lead/client being edited
        
        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }
        
        // Use ILIKE for PostgreSQL, LIKE for MySQL
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        
        $results = Admin::where(function($q) use ($query, $likeOperator) {
                // Primary search: Phone and Email (as per requirement)
                $q->where('phone', $likeOperator, "%{$query}%")
                  ->orWhere('email', $likeOperator, "%{$query}%")
                  // Secondary search: Name and Client ID
                  ->orWhere('first_name', $likeOperator, "%{$query}%")
                  ->orWhere('last_name', $likeOperator, "%{$query}%")
                  ->orWhere('client_id', $likeOperator, "%{$query}%");
                
                // For PostgreSQL, use CONCAT with ILIKE
                if (DB::getDriverName() === 'pgsql') {
                    $q->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$query}%"]);
                } else {
                    // For MySQL, use CONCAT with LIKE
                    $q->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"]);
                }
            })
            ->whereIn('type', ['client', 'lead'])
            ->where(function($q) {
                $q->where('type', 'client')
                  ->orWhere('type', 'lead');
            })
            ->where('is_company', false) // Exclude companies from being contact persons
            ->when($excludeId, function($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'client_id', 'type')
            ->limit(20)
            ->get()
            ->map(function($person) {
                $fullName = trim($person->first_name . ' ' . $person->last_name);
                // Show phone and email in display text
                $displayText = "{$fullName}";
                if ($person->email) {
                    $displayText .= " ({$person->email})";
                }
                if ($person->phone) {
                    $displayText .= " - {$person->phone}";
                }
                $displayText .= " - {$person->client_id}";
                
                return [
                    'id' => $person->id,
                    'text' => $displayText,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'email' => $person->email,
                    'phone' => $person->phone,
                    'client_id' => $person->client_id,
                    'type' => $person->type
                ];
            });
        
        return response()->json(['results' => $results]);
    }

    /**
     * HTML description for activities_logs when an email_logs row is deleted from the CRM Emails tab.
     */
    protected function buildEmailLogDeletionActivityDescription(
        int $emailLogId,
        array $snapshot,
        int $attachmentCount,
        ?string $matterReference,
        ?int $matterInternalId
    ): string {
        $h = static function (?string $value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $displaySubject = ($snapshot['subject'] ?? '') !== '' ? $snapshot['subject'] : '(no subject)';
        $direction = $this->humanizeEmailLogMailType($snapshot['mail_type'] ?? null);
        $recordType = strtolower(trim((string) ($snapshot['record_type'] ?? '')));
        $recordLabel = $recordType === 'lead' ? 'Lead' : ($recordType === 'client' ? 'Client' : '');

        $lines = [];
        $lines[] = '<p>Removed an email from the CRM Emails tab.</p>';
        $lines[] = '<p><strong>Email log ID:</strong> ' . $h((string) $emailLogId) . '</p>';

        if ($matterReference !== null && $matterReference !== '') {
            $lines[] = '<p><strong>Matter:</strong> ' . $h($matterReference) . '</p>';
        } elseif ($matterInternalId !== null && $matterInternalId > 0) {
            $lines[] = '<p><strong>Matter ID:</strong> ' . $h((string) $matterInternalId) . '</p>';
        }

        if ($recordLabel !== '') {
            $lines[] = '<p><strong>Record:</strong> ' . $h($recordLabel) . '</p>';
        }

        if ($direction !== '') {
            $lines[] = '<p><strong>Direction:</strong> ' . $h($direction) . '</p>';
        }

        if ($attachmentCount > 0) {
            $lines[] = '<p><strong>Attachments removed:</strong> ' . $h((string) $attachmentCount) . '</p>';
        }

        $lines[] = '<p><strong>Subject:</strong> ' . $h($displaySubject) . '</p>';

        if (($snapshot['from_mail'] ?? '') !== '') {
            $lines[] = '<p><strong>From:</strong> ' . $h($snapshot['from_mail']) . '</p>';
        }
        if (($snapshot['to_mail'] ?? '') !== '') {
            $lines[] = '<p><strong>To:</strong> ' . $h($snapshot['to_mail']) . '</p>';
        }

        $ccLine = $this->formatEmailLogCcForActivityDescription($snapshot['cc'] ?? null);
        if ($ccLine !== null && $ccLine !== '') {
            $maxLen = 600;
            if (strlen($ccLine) > $maxLen) {
                $ccLine = substr($ccLine, 0, $maxLen) . '…';
            }
            $lines[] = '<p><strong>CC:</strong> ' . $h($ccLine) . '</p>';
        }

        return implode('', $lines);
    }

    /**
     * @param mixed $mailType Raw mail_type from email_logs (string or int depending on source)
     */
    protected function humanizeEmailLogMailType($mailType): string
    {
        if ($mailType === null || $mailType === '') {
            return '';
        }
        $s = is_string($mailType) ? strtolower(trim($mailType)) : (string) $mailType;

        if ($s === 'sent' || $s === '1') {
            return 'Sent';
        }
        if ($s === 'inbox' || $s === 'received' || $s === '0') {
            return 'Received';
        }

        return ucfirst($s);
    }

    /**
     * Normalize CC field (string or JSON array) for activity description.
     */
    protected function formatEmailLogCcForActivityDescription($cc): ?string
    {
        if ($cc === null) {
            return null;
        }
        if (is_array($cc)) {
            $flat = [];
            array_walk_recursive($cc, function ($v) use (&$flat) {
                if (is_string($v) && trim($v) !== '') {
                    $flat[] = trim($v);
                }
            });

            return $flat !== [] ? implode(', ', $flat) : null;
        }

        $trimmed = trim((string) $cc);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '[' || $trimmed[0] === '{') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $this->formatEmailLogCcForActivityDescription($decoded);
            }
        }

        return $trimmed;
    }

}
