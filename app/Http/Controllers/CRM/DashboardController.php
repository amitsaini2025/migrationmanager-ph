<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\DashboardRequest;
use App\Models\Staff;
use App\Services\DashboardService;
use App\Services\StaffPersonalCalendarFeedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    protected StaffPersonalCalendarFeedService $personalCalendarFeed;

    public function __construct(
        DashboardService $dashboardService,
        StaffPersonalCalendarFeedService $personalCalendarFeed
    ) {
        $this->middleware('auth:admin');
        $this->dashboardService = $dashboardService;
        $this->personalCalendarFeed = $personalCalendarFeed;
    }

    /**
     * Show the dashboard
     */
    public function index(DashboardRequest $request)
    {
        $dashboardData = $this->dashboardService->getDashboardData($request);

        $dashboardData['calendarTypes'] = $this->personalCalendarFeed->calendarTypeOptions();
        $dashboardData['defaultCalendarType'] = StaffPersonalCalendarFeedService::DEFAULT_TYPE;
        $dashboardData['calendarStats'] = ['today' => 0, 'this_week' => 0, 'upcoming' => 0];

        return view('crm.dashboard-optimized', $dashboardData);
    }

    /**
     * JSON feed for dashboard appointment calendar.
     * Lazy-loaded — does not run on the initial dashboard HTML request.
     */
    public function calendarEvents(Request $request)
    {
        $staff = Auth::user();
        if (! $staff instanceof Staff) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $type = $this->personalCalendarFeed->normalizeCalendarType($request->input('type'));
        $rows = $this->personalCalendarFeed->appointmentsForType($type, $request);
        $events = array_map(
            fn (array $row) => $this->personalCalendarFeed->toFullCalendarEvent($row),
            $rows
        );

        return response()->json([
            'success' => true,
            'type' => $type,
            'data' => $events,
            'stats' => $this->personalCalendarFeed->statsForType($type),
        ]);
    }

    /**
     * HTML fragment for cases requiring attention widget (lazy-loaded).
     */
    public function casesFragment(DashboardRequest $request)
    {
        if (! $request->ajax()) {
            return redirect()->route('dashboard');
        }

        $payload = $this->dashboardService->getCasesRequiringAttentionPayload(Auth::user());

        return response()->view('crm.partials.dashboard-cases-attention-fragment', $payload);
    }

    /**
     * HTML fragment for client matters table + pagination (smooth pagination without full page reload).
     */
    public function mattersFragment(DashboardRequest $request)
    {
        if (! $request->ajax()) {
            return redirect()->route('dashboard', $request->query());
        }

        $payload = $this->dashboardService->getClientMattersTablePayload($request, Auth::user());
        /** @var \Illuminate\Pagination\LengthAwarePaginator $data */
        $data = $payload['data'];
        $data->setPath(route('dashboard'));

        return response()->view('crm.partials.dashboard-client-matters-fragment', [
            'data' => $data,
            'filters' => $payload['filters'],
        ]);
    }

    /**
     * Save column preferences
     */
    public function saveColumnPreferences(Request $request)
    {
        $this->dashboardService->saveColumnPreferences($request);
        
        return response()->json([
            'success' => true,
            'message' => 'Column preferences saved successfully'
        ]);
    }

    /**
     * Get dashboard notifications
     */
    public function fetchNotifications(Request $request)
    {
        $notifications = $this->dashboardService->getNotifications();
        
        return response()->json([
            'unseen_notification' => $notifications['count']
        ]);
    }

    /**
     * Get office visit notifications
     */
    public function fetchOfficeVisitNotifications(Request $request)
    {
        $notifications = $this->dashboardService->getOfficeVisitNotifications();
        
        return response()->json([
            'notifications' => $notifications,
            'count' => count($notifications)
        ]);
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationSeen(Request $request)
    {
        $result = $this->dashboardService->markNotificationAsSeen($request->notification_id);
        
        return response()->json($result);
    }

    /**
     * Extend note deadline
     */
    public function extendDeadlineDate(Request $request)
    {
        try {
            $this->validate($request, [
                'note_id' => 'required|integer',
                'unique_group_id' => 'required|string',
                'description' => 'required|string',
                'note_deadline' => 'required|date'
            ]);

            Log::info('Extend deadline request data:', $request->all());

            $result = $this->dashboardService->extendNoteDeadline($request->all());
            
            Log::info('Extend deadline result:', $result);
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in extendDeadlineDate:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_flatten($e->errors()))
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in extendDeadlineDate: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while extending the deadline'
            ], 500);
        }
    }

    /**
     * Update action completion status
     */
    public function updateActionCompleted(Request $request)
    {
        try {
            Log::info('Update action completed request data:', $request->all());
            
            $this->validate($request, [
                'id' => 'required|integer',
                'unique_group_id' => 'nullable|string',
                'completion_notes' => 'nullable|string|max:5000'
            ]);

            $result = $this->dashboardService->updateActionCompleted(
                $request->id,
                $request->unique_group_id ?? '',
                $request->completion_notes
            );
            
            Log::info('Update action completed result:', $result);
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in updateActionCompleted:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_flatten($e->errors()))
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in updateActionCompleted: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating action completion'
            ], 500);
        }
    }

    /**
     * Get visa expiry messages
     */
    public function fetchVisaExpiryMessages(Request $request)
    {
        $this->validate($request, [
            'client_id' => 'required|integer'
        ]);

        $message = $this->dashboardService->getVisaExpiryMessage($request->client_id);
        
        return $message;
    }

    /**
     * Check checkin status
     */
    public function checkCheckinStatus(Request $request)
    {
        try {
            $checkinLog = \App\Models\CheckinLog::where('id', $request->checkin_id)->first();
            
            if ($checkinLog) {
                return response()->json([
                    'success' => true,
                    'status' => $checkinLog->status
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Checkin not found']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error checking checkin status']);
        }
    }

    /**
     * Update checkin status
     */
    public function updateCheckinStatus(Request $request)
    { 
        try {
            $checkinLog = \App\Models\CheckinLog::where('id', $request->checkin_id)->first();
            
            if ($checkinLog) {
                $checkinLog->status = $request->status;
                
                if ($request->has('wait_type')) {
                    $checkinLog->wait_type = $request->wait_type;
                }
                
                $saved = $checkinLog->save();
                
                if ($saved) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Status updated successfully to ' . $request->status
                    ]);
                } else {
                    return response()->json(['success' => false, 'message' => 'Failed to save status update']);
                }
            }

            return response()->json(['success' => false, 'message' => 'Checkin not found']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating checkin status: ' . $e->getMessage()]);
        }
    }

    /**
     * Get in-person waiting count
     */
    public function fetchInPersonWaitingCount(Request $request)
    {
        $InPersonwaitingCount = \App\Models\CheckinLog::inPersonWaitingCountForViewer();

        return response()->json(['InPersonwaitingCount' => $InPersonwaitingCount]);
    }

    /**
     * Get total activity count
     */
    public function fetchTotalActivityCount(Request $request)
    {
        if (Auth::user()->role == 1) {
            $assigneesCount = \App\Models\Note::query()->where('type', 'client')
                ->whereNotNull('client_id')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        } else {
            $assigneesCount = \App\Models\Note::query()->where('assigned_to', Auth::user()->id)
                ->where('type', 'client')
                ->where('is_action', 1)
                ->where('status', 0)
                ->count();
        }
        
        return response()->json(['assigneesCount' => $assigneesCount]);
    }
}
