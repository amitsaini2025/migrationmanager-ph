<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Note;
use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ActivitySearchController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Matches sidebar: Super Admin (role 1) or staff with grant_super_admin_access.
     */
    private function userCanAccessActivitySearch(): bool
    {
        $user = Auth::user();

        return $user instanceof Staff
            && app(CrmAccessService::class)->hasAdminConsoleLikeSuperAdminAccess($user);
    }

    /**
     * @return string|null Error message if any submitted date is invalid.
     */
    private function validateActivitySearchDates(Request $request): ?string
    {
        foreach (['date_from', 'date_to'] as $field) {
            if (! $request->filled($field)) {
                continue;
            }
            try {
                Carbon::parse($request->input($field));
            } catch (\Throwable $e) {
                return $field === 'date_from'
                    ? 'Invalid Date From.'
                    : 'Invalid Date To.';
            }
        }

        return null;
    }

    private function applyActivitySearchJoins(Builder $query): void
    {
        $query->leftJoin('staff as creator', 'notes.user_id', '=', 'creator.id')
            ->leftJoin('staff as assignee', 'notes.assigned_to', '=', 'assignee.id')
            ->leftJoin('admins as client', 'notes.client_id', '=', 'client.id');
    }

    /**
     * Case-insensitive substring match for action title/description.
     */
    private function applyKeywordFilter(Builder $query, string $keyword): void
    {
        $pattern = '%' . mb_strtolower($keyword, 'UTF-8') . '%';
        $query->where(function ($q) use ($pattern) {
            $q->whereRaw('LOWER(notes.title) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(notes.description) LIKE ?', [$pattern]);
        });
    }

    /**
     * Build the Action Search query from notes, using the same source of truth as /action.
     * Aliases preserve the existing Activity Search table/export column contract.
     */
    private function buildActionSearchQuery(Request $request): Builder
    {
        $query = Note::query()
            ->select(
                'notes.id',
                'notes.client_id',
                'notes.user_id as created_by',
                'notes.assigned_to as use_for',
                'notes.title as subject',
                'notes.description',
                'notes.action_date as followup_date',
                'notes.task_group',
                'notes.status as task_status',
                'notes.created_at',
                'notes.updated_at',
                'creator.first_name as creator_first_name',
                'creator.last_name as creator_last_name',
                'creator.email as creator_email',
                'assignee.first_name as assignee_first_name',
                'assignee.last_name as assignee_last_name',
                'assignee.email as assignee_email',
                'client.first_name as client_first_name',
                'client.last_name as client_last_name',
                'client.email as client_email'
            )
            ->selectRaw("'action' as activity_type")
            ->where('notes.type', 'client')
            ->where('notes.is_action', 1);

        $this->applyActivitySearchJoins($query);

        if ($request->filled('assigner_id')) {
            $query->where('notes.user_id', $request->assigner_id);
        }
        if ($request->filled('assignee_id')) {
            $query->where('notes.assigned_to', $request->assignee_id);
        }
        if ($request->filled('client_id')) {
            $query->where('notes.client_id', $request->client_id);
        }
        if ($request->filled('task_status')) {
            $query->where('notes.status', $request->task_status);
        }
        if ($request->filled('task_group')) {
            $query->where('notes.task_group', $request->task_group);
        }
        if ($request->filled('date_from')) {
            $query->where('notes.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('notes.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('keyword')) {
            $this->applyKeywordFilter($query, $request->keyword);
        }

        return $query;
    }

    /**
     * Display the activity search page
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (! $this->userCanAccessActivitySearch()) {
            return Redirect::to('/dashboard')->with('error', 'Unauthorized: Only authorized administrators can access Action Search.');
        }

        // Get all active staff
        $staffList = Staff::query()
            ->where('status', 1)
            ->orderBy('first_name', 'ASC')
            ->get()
            ->map(function($staff) {
                return [
                    'id' => $staff->id,
                    'name' => $staff->first_name . ' ' . $staff->last_name,
                    'email' => $staff->email
                ];
            });

        // Get task groups (action categories)
        $taskGroups = [
            'Call' => 'Call',
            'Checklist' => 'Checklist',
            'Review' => 'Review',
            'Query' => 'Query',
            'Urgent' => 'Urgent',
            'Personal Action' => 'Personal Action',
            'Client Portal' => 'Client Portal',
            'EOI/ROI Amendment' => 'EOI/ROI Amendment',
            'Follow Up' => 'Follow Up',
        ];

        $activities = collect();
        $totalActivities = 0;

        // Process search if form is submitted
        if ($request->has('search')) {
            if ($message = $this->validateActivitySearchDates($request)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $message);
            }

            $activities = $this->buildActionSearchQuery($request)
                ->orderBy('notes.created_at', 'DESC')
                ->paginate(50)
                ->appends($request->except('page'));
            $totalActivities = $activities->total();
        }

        return view('AdminConsole.system.activity-search.index', compact(
            'staffList',
            'taskGroups',
            'activities',
            'totalActivities'
        ));
    }

    /**
     * Single action row for the Action Search modal (JSON).
     */
    public function activityJson(int $id): JsonResponse
    {
        if (! $this->userCanAccessActivitySearch()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
        }

        $activity = Note::query()
            ->where('type', 'client')
            ->where('is_action', 1)
            ->find($id);
        if (! $activity) {
            return response()->json(['status' => false, 'message' => 'Action not found'], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $activity->id,
                'subject' => $activity->title ?: 'Action',
                'description' => strip_tags((string) ($activity->description ?? '')),
                'activity_type' => $activity->task_group ?: 'N/A',
                'created_at' => $activity->created_at?->toAtomString(),
            ],
        ]);
    }

    /**
     * Export activities to CSV
     *
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        if (! $this->userCanAccessActivitySearch()) {
            return Redirect::to('/dashboard')->with('error', 'Unauthorized: Only authorized administrators can export actions.');
        }

        if ($message = $this->validateActivitySearchDates($request)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $message);
        }

        // Limit export to 5000 records
        $activities = $this->buildActionSearchQuery($request)
            ->orderBy('notes.created_at', 'DESC')
            ->limit(5000)
            ->get();

        // Generate CSV
        $filename = 'activity_search_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($activities) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, [
                'Action ID',
                'Assign Date',
                'Assigner Name',
                'Assigner Email',
                'Assignee Name',
                'Assignee Email',
                'Client Name',
                'Client Email',
                'Type',
                'Status',
                'Note',
                'Description',
                'Created At'
            ]);

            // Add data rows
            foreach ($activities as $activity) {
                $assignerName = $activity->creator_first_name . ' ' . $activity->creator_last_name;
                $assigneeName = $activity->assignee_first_name ? ($activity->assignee_first_name . ' ' . $activity->assignee_last_name) : 'N/A';
                $clientName = $activity->client_first_name . ' ' . $activity->client_last_name;
                $status = $activity->task_status == 1 ? 'Completed' : 'Incomplete';
                $followupDate = $activity->followup_date
                    ? Carbon::parse($activity->followup_date)->format('Y-m-d H:i:s')
                    : '';

                fputcsv($file, [
                    $activity->id,
                    $followupDate,
                    $assignerName,
                    $activity->creator_email ?? '',
                    $assigneeName,
                    $activity->assignee_email ?? '',
                    $clientName,
                    $activity->client_email ?? '',
                    $activity->task_group ?? 'N/A',
                    $status,
                    $activity->subject ?? '',
                    strip_tags($activity->description ?? ''),
                    $activity->created_at ? $activity->created_at->format('Y-m-d H:i:s') : ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Search clients for autocomplete
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchClients(Request $request)
    {
        if (! $this->userCanAccessActivitySearch()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $clients = Admin::query()
            ->whereIn('type', ['client', 'lead'], 'and', false)
            ->where(function($q) use ($query) {
                $searchLower = strtolower($query);
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $searchLower . '%'])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $searchLower . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $searchLower . '%']);
            })
            ->limit(20)
            ->get()
            ->map(function($client) {
                return [
                    'id' => $client->id,
                    'text' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ')'
                ];
            });

        return response()->json($clients);
    }
}
