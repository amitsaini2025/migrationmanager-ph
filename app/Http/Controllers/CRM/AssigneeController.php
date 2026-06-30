<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder;

// WARNING: Appointment and AppointmentLog models have been removed - old appointment system deleted
// use App\Models\Appointment;
use App\Models\Note;
use App\Models\ClientMatter;
// use App\Models\AppointmentLog;
use App\Models\Notification;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\Staff;
use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\IconHelper;
use App\Services\FCMService;
use App\Events\NotificationCountUpdated;
use Yajra\DataTables\Facades\DataTables;
use App\Helpers\Utf8Helper;
use Illuminate\Support\Facades\URL;

class AssigneeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

     public function __construct()
     {
         $this->middleware('auth:admin');
     }

    //All action lists except completed = Closed
    public function index(Request $request)
    {
        $query = QueryBuilder::for(Note::class)
            ->allowedSorts(['first_name', 'action_date', 'task_group', 'created_at'])
            ->with(['noteStaff','noteClient.company','lead.service','assigned_staff'])
            ->where('type','client')
            ->where('is_action', 1)
            ->where('status','<>','1');

        if(Auth::user()->role == 1){
            $assignees = $query->whereNotNull('client_id')->paginate(20);
        }else{
            $assignees = $query->where('assigned_to',Auth::user()->id)->paginate(20);
        }

        return view('crm.assignee.index',compact('assignees'))
         ->with('i', (request()->input('page', 1) - 1) * 20);
    }

    //All completed action lists
    public function completed(Request $request)
    {
        if(Auth::user()->role == 1){
            $assignees = \App\Models\Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])->where('type','client')->whereNotNull('client_id')->where('is_action', 1)->where('status','1')->orderBy('created_at', 'desc')->latest()->paginate(20); //where('status','like','Closed')
        }else{
            $assignees = \App\Models\Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])->where('assigned_to',Auth::user()->id)->where('type','client')->where('is_action', 1)->where('status','1')->orderBy('created_at', 'desc')->latest()->paginate(20);
        }  //dd( $assignees);
        return view('crm.assignee.completed',compact('assignees'))
         ->with('i', (request()->input('page', 1) - 1) * 20);
    }

    //Update action to be complete
    public function updateActionCompleted(Request $request)
    {
        $data = $request->all(); //dd($data);
        $uniqueGroupId = $data['unique_group_id'] ?? '';
        // Try group update first (notes with same unique_group_id)
        $updated = Note::where('unique_group_id', $uniqueGroupId)
                ->whereNotNull('assigned_to')
                ->whereNotNull('unique_group_id')
                ->update(['status' => '1']);
        // Fallback: when unique_group_id is null/empty, update single note by id so completion still works
        if ($updated === 0) {
            $updated = Note::where('id', $data['id'])->update(['status' => '1']);
        }
        if ($updated) {
            $note_data = Note::where('id', $data['id'])->first(); //dd($note_data);
            if($note_data){
                $admin_data = Staff::where('id',$note_data['assigned_to'])->first(); //dd($admin_data);
                if($admin_data){
                    $assignee_name = $admin_data['first_name']." ".$admin_data['last_name'];
                } else {
                    $assignee_name = 'N/A';
                }
                
                // Prepare description with completion notes (completion notes appear first)
                $description = '';
                if (!empty($data['completion_notes'])) {
                    $description .= '<p>';
                    $description .= IconHelper::fromLegacy('fas fa-ellipsis-v', [
                        'class' => 'convert-activity-to-note',
                        'style' => 'cursor: pointer; color: #6c757d;',
                        'title' => 'Convert to Note',
                        'data-activity-id' => '',
                        'data-activity-subject' => 'Completion Notes',
                        'data-activity-description' => $data['completion_notes'],
                        'data-activity-created-by' => Auth::user()->id,
                        'data-activity-created-at' => now()->toIso8601String(),
                        'data-client-id' => $note_data['client_id'],
                    ]);
                    $description .= '</p>';
                    $description .= '<p>'.nl2br(htmlspecialchars($data['completion_notes'])).'</p>';
                    $description .= '<hr>';
                }
                $description .= '<p>'.@$note_data['description'].'</p>';

                // Skip Personal Details Activity Feed for Client Portal actions (Action page category);
                // client notifications below still run; other task groups keep the completion log.
                $taskGroup = $note_data['task_group'] ?? '';
                if ((string) $taskGroup !== 'Client Portal') {
                    $objs = new ActivitiesLog;
                    $objs->client_id = $note_data['client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->subject = 'completed action for '.@$assignee_name;
                    $objs->description = $description;
                    if(Auth::user()->id != @$note_data['assigned_to']){
                        $objs->use_for = @$note_data['assigned_to'];
                    } else {
                        $objs->use_for = null;
                    }
                    $objs->followup_date = @$note_data['updated_at']; // ActivitiesLog uses followup_date
                    $objs->task_group = $taskGroup;
                    $objs->task_status = 1; //marked completed
                    $objs->pin = 0;
                    $objs->save();
                }

                // Client Portal category only: notify client (notification list API + push + real-time)
                $clientId = $note_data['client_id'] ?? null;
                if ($clientId && (string) $taskGroup === 'Client Portal') {
                    $messageText = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", (string) ($note_data['description'] ?? ''))));
                    if (mb_strlen($messageText) > 200) {
                        $messageText = mb_substr($messageText, 0, 197) . '...';
                    }
                    $notificationMessage = 'This action is completed. ' . ($messageText ?: 'An action has been completed for your matter.');
                    // module_id = client matter id so notification appears in List API when client filters by client_matter_id
                    $moduleId = !empty($note_data['matter_id']) ? (int) $note_data['matter_id'] : null;
                    if ($moduleId === null) {
                        $moduleId = ClientMatter::where('client_id', $clientId)->orderByDesc('id')->value('id') ?? $clientId;
                    }
                    DB::table('notifications')->insert([
                        'sender_id' => Auth::user()->id,
                        'receiver_id' => $clientId,
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
                        $fcm->sendToUser($clientId, 'Action completed', $notificationMessage, [
                            'type' => 'action_completed',
                            'client_matter_id' => (string) $moduleId,
                            'url' => '/activities',
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('FCM send failed on action complete (Client Portal)', ['client_id' => $clientId, 'error' => $e->getMessage()]);
                    }
                    try {
                        $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                        broadcast(new NotificationCountUpdated($clientId, $clientCount, $notificationMessage, '/activities'));
                    } catch (\Exception $e) {
                        Log::warning('Broadcast failed on action complete (Client Portal)', ['client_id' => $clientId, 'error' => $e->getMessage()]);
                    }
                }
            }
            $response['status'] 	= 	true;
            $response['message']	=	'Action completed successfully';
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
        }
        echo json_encode($response);
    }

    //Update action to be not complete
    public function updateActionNotCompleted(Request $request)
    {
        $data = $request->all(); //dd($data['id']);
        $note = Note::where('unique_group_id',$data['unique_group_id'])
                    ->whereNotNull('assigned_to')
                    ->whereNotNull('unique_group_id')
                    ->update(['status'=>'0']);
        if($note){
            $response['status'] 	= 	true;
            $response['message']	=	'Action updated successfully';
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
        }
        echo json_encode($response);
    }

     //All assigned by me action list which r incomplete
     public function assigned_by_me(Request $request)
     {
        if(Auth::user()->role == 1){
             $assignees_notCompleted = \App\Models\Note::with(['noteStaff','noteClient.company','assigned_staff'])->where('status','<>',1)->where('type','client')->whereNotNull('client_id')->where('is_action', 1)->orderBy('created_at', 'desc')->latest()->paginate(20);
        } else {
             $assignees_notCompleted = \App\Models\Note::with(['noteStaff','noteClient.company','assigned_staff'])->where('status','<>',1)->where('user_id',Auth::user()->id)->where('type','client')->where('is_action', 1)->orderBy('created_at', 'desc')->latest()->paginate(20);
        }
         #dd($assignees_notCompleted);
         return view('crm.assignee.assign_by_me',compact('assignees_notCompleted'))
          ->with('i', (request()->input('page', 1) - 1) * 20);
     }

    //All assigned to me action list
    public function assigned_to_me(Request $request)
    {
        if(Auth::user()->role == 1){
            $assignees_notCompleted = \App\Models\Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])->where('status','<>','1')->where('assigned_to',Auth::user()->id)->where('type','client')->whereNotNull('client_id')->where('is_action', 1)->orderBy('created_at', 'desc')->latest()->paginate(20);//where('status','not like','Closed')

            $assignees_completed = \App\Models\Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])->where('status','1')->where('assigned_to',Auth::user()->id)->where('type','client')->whereNotNull('client_id')->where('is_action', 1)->orderBy('created_at', 'desc')->latest()->paginate(20);
        }else{
            $assignees_notCompleted = \App\Models\Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])->where('status','<>','1')->where('assigned_to',Auth::user()->id)->where('type','client')->where('is_action', 1)->orderBy('created_at', 'desc')->latest()->paginate(20);

            $assignees_completed = \App\Models\Note::with(['noteStaff','noteClient.company','lead.service','assigned_staff'])->where('status','1')->where('assigned_to',Auth::user()->id)->where('type','client')->where('is_action', 1)->orderBy('created_at', 'desc')->latest()->paginate(20);
        }
        return view('crm.assignee.assign_to_me',compact('assignees_notCompleted','assignees_completed'))
         ->with('i', (request()->input('page', 1) - 1) * 20);
    }

    public function action_completed(Request $request)
    {   //dd($request->all());
        $req_data = $request->all();
        if( isset($req_data['group_type'])  && $req_data['group_type'] != ""){
            $task_group = $req_data['group_type'];
        } else {
            $task_group = 'All';
        }
        $staff = Auth::user();

        $assignees_completed = \App\Models\Note::with([
                'noteStaff',
                'noteClient.company',
                'assigned_staff'
            ])
            ->where('status', 1)
            ->where('type', 'client')
            ->whereNotNull('client_id')
            ->where('is_action', 1)
            ->when($staff->role != 1, function ($query) use ($staff) {
                return $query->where('assigned_to', $staff->id);
            })
            ->when($task_group !== 'All', function ($query) use ($task_group) {
                return $query->where('task_group', 'like', $task_group);
            });

        // Apply sorting - match Action page: default action_date desc; respect sortable links
        $sortParam = $request->get('sort', '-action_date');
        $sortColumn = ltrim($sortParam, '-');
        $sortDirection = str_starts_with($sortParam, '-') ? 'desc' : 'asc';
        if (in_array($sortColumn, ['action_date', 'task_group', 'created_at'])) {
            $assignees_completed = $assignees_completed->orderBy('notes.' . $sortColumn, $sortDirection)->paginate(20);
        } else {
            $assignees_completed = $assignees_completed->orderBy('notes.action_date', 'desc')->paginate(20);
        }

        // Get action group counts with single optimized query
        $taskGroupCounts = $this->getCompletedActionGroupCounts($staff);
        
        //dd(count($assignees_completed));
        return view('crm.assignee.action_completed',compact('assignees_completed','task_group','taskGroupCounts'))->with('i', (request()->input('page', 1) - 1) * 20);
    }

    /**
     * Get completed action counts grouped by task_group (field name kept for database compatibility)
     * Uses a single query with GROUP BY for better performance
     * 
     * @param \App\Models\Admin $staff
     * @return array
     */
    private function getCompletedActionGroupCounts($staff)
    {
        $query = \App\Models\Note::where('status', 1)
            ->where('type', 'client')
            ->where('is_action', 1)
            ->when($staff->role != 1, function ($query) use ($staff) {
                return $query->where('assigned_to', $staff->id);
            });

        // For admin role, add client_id filter
        if ($staff->role == 1) {
            $query->whereNotNull('client_id');
        }

        // Get counts grouped by task_group
        $groupedCounts = $query->selectRaw('task_group, COUNT(*) as count')
            ->groupBy('task_group')
            ->pluck('count', 'task_group')
            ->toArray();

        // Initialize all action groups with default count of 0
        $counts = [
            'All' => array_sum($groupedCounts),
            'Call' => $groupedCounts['Call'] ?? 0,
            'Checklist' => $groupedCounts['Checklist'] ?? 0,
            'Review' => $groupedCounts['Review'] ?? 0,
            'Query' => $groupedCounts['Query'] ?? 0,
            'Urgent' => $groupedCounts['Urgent'] ?? 0,
            'Personal Action' => $groupedCounts['Personal Action'] ?? 0,
            'Client Portal' => $groupedCounts['Client Portal'] ?? 0,
            'Follow Up' => $groupedCounts['Follow Up'] ?? 0,
        ];

        return $counts;
    }

    public function action() {
        return view('crm.assignee.action');
    }

    public function getAction(Request $request)
    {
        try {
            if ($request->ajax()) {
                // Select specific columns from the notes table, using the correct column name 'user_id'
                $query = Note::select([
                        'notes.id',
                        'notes.user_id', // Changed from 'created_by' to 'user_id'
                        'notes.client_id',
                        'notes.assigned_to',
                        'notes.status',
                        'notes.type',
                        'notes.is_action',
                        'notes.action_date',
                        'notes.task_group',
                        'notes.description',
                        'notes.unique_group_id',
                        'notes.created_at'
                    ])
                    ->with(['noteStaff', 'noteClient.company', 'assigned_staff'])
                    ->where('notes.status', '<>', '1')
                    ->where('notes.type', 'client')
                    ->where('notes.is_action', 1);

                // Check if staff member is authenticated and has proper role
                if (Auth::check() && Auth::user()->role != 1) {
                    $query->where('notes.assigned_to', Auth::user()->id);
                }

                // Apply filter based on tab
                if ($request->filter && $request->filter != 'all') {
                    if ($request->filter == 'assigned_by_me') {
                        $query->where('notes.user_id', Auth::user()->id);
                    } elseif ($request->filter == 'completed') {
                        $query->where('notes.status', '1');
                    } else {
                        // Handle special cases for filter keys that use underscores (convert to correct task_group value)
                        $actionGroup = $request->filter;
                        if ($actionGroup == 'personal_action') {
                            $actionGroup = 'Personal Action';
                        } elseif ($actionGroup == 'client_portal') {
                            $actionGroup = 'Client Portal';
                        } elseif ($actionGroup == 'follow_up') {
                            $actionGroup = 'Follow Up';
                        } else {
                            $actionGroup = ucfirst($actionGroup);
                        }
                        $query->where('notes.task_group', $actionGroup);

                        // Super Admin Client Portal tab should show one row per grouped action.
                        if (
                            Auth::check()
                            && Auth::user()->role == 1
                            && $request->filter == 'client_portal'
                        ) {
                            $query->whereRaw(
                                "notes.id IN (
                                    SELECT MAX(grouped_notes.id)
                                    FROM notes as grouped_notes
                                    WHERE grouped_notes.status <> '1'
                                        AND grouped_notes.type = 'client'
                                        AND grouped_notes.is_action = 1
                                        AND grouped_notes.task_group = 'Client Portal'
                                    GROUP BY COALESCE(NULLIF(grouped_notes.unique_group_id, ''), CONCAT('note_', grouped_notes.id))
                                )"
                            );
                        }
                    }
                }

                // Note: Search functionality is now handled by Yajra DataTables filterColumn() definitions
                // The custom 'd.search' parameter from frontend is handled by DataTables' built-in search

                // Apply sorting
                $orderDirection = in_array($request->input('order.0.dir'), ['asc', 'desc']) 
                    ? $request->input('order.0.dir') 
                    : 'desc';

                if ($request->has('order')) {
                    $orderColumnIndex = (int) $request->order[0]['column'];
                    $columns = $request->columns;

                    $columnName = $columns[$orderColumnIndex]['name'] ?? '';

                    // Map DataTables column names to database columns
                    switch ($columnName) {
                        case 'assigner_name':
                            $query->leftJoin('staff as assigner_staff', 'notes.user_id', '=', 'assigner_staff.id')
                                ->orderByRaw("COALESCE(assigner_staff.first_name, '') " . $orderDirection . ", COALESCE(assigner_staff.last_name, '') " . $orderDirection);
                            break;
                        case 'client_reference':
                            $query->leftJoin('admins as client_admins', 'notes.client_id', '=', 'client_admins.id')
                                ->leftJoin('companies as action_client_companies', 'action_client_companies.admin_id', '=', 'client_admins.id')
                                ->orderByRaw(
                                    "LOWER(COALESCE(NULLIF(TRIM(action_client_companies.company_name), ''), COALESCE(client_admins.first_name, '') || ' ' || COALESCE(client_admins.last_name, ''))) "
                                    . $orderDirection
                                );
                            break;
                        case 'assign_date':
                            $query->orderBy('notes.action_date', $orderDirection);
                            break;
                        case 'task_group':
                            $query->orderBy('notes.task_group', $orderDirection);
                            break;
                        case 'note_description':
                            $query->orderBy('notes.description', $orderDirection);
                            break;
                        default:
                            $query->orderBy('notes.created_at', 'desc'); // Fallback sorting
                            break;
                    }
                } else {
                    $query->orderBy('notes.created_at', 'desc'); // Default sorting
                }

                $dataTable = DataTables::of($query)
                    ->addIndexColumn()
                    ->addColumn('done_action', function($data) {
                        $done_action = '<input type="radio" class="complete_task" data-toggle="tooltip" title="Mark Complete!" data-id="'.$data->id.'" data-unique_group_id="'.$data->unique_group_id.'">';
                        return $done_action;
                    })
                    ->addColumn('assigner_name', function($data) {
                        try {
                            // Client Portal actions created by client (e.g. upload from mobile): show client name as assigner
                            if (isset($data->task_group) && (string) $data->task_group === 'Client Portal' && (int) $data->user_id === (int) $data->client_id && $data->noteClient) {
                                $portalLabel = Utf8Helper::safeSanitize(trim($data->noteClient->company_name_or_personal_name ?? ''));
                                return $portalLabel !== '' ? $portalLabel : 'N/P';
                            }
                            if ($data->noteStaff) {
                                $firstName = Utf8Helper::safeSanitize($data->noteStaff->first_name ?? '');
                                $lastName = Utf8Helper::safeSanitize($data->noteStaff->last_name ?? '');
                                return $firstName . ' ' . $lastName;
                            }
                            return 'N/P';
                        } catch (\Exception $e) {
                            return 'N/P';
                        }
                    })
                    ->addColumn('client_reference', function($data) {
                        try {
                            if ($data->noteClient && $data->client_id) {
                                $clientId = Utf8Helper::safeSanitize($data->noteClient->client_id ?? '');
                                $label = Utf8Helper::safeSanitize(trim($data->noteClient->company_name_or_personal_name ?? ''));
                                if ($label === '') {
                                    $label = trim(Utf8Helper::safeSanitize($data->noteClient->first_name ?? '') . ' ' . Utf8Helper::safeSanitize($data->noteClient->last_name ?? ''));
                                }
                                $client_name = $label;
                                $client_name .= "<br>";
                                $client_encoded_id = base64_encode(convert_uuencode(@$data->client_id));
                                $client_name .= '<a href="'.url('/clients/detail/'.$client_encoded_id).'" target="_blank">'.$clientId.'</a>';
                            } else {
                                // Personal Action - no client assigned
                                $client_name = '<span class="badge badge-info">Personal Action</span>';
                            }
                            return $client_name;
                        } catch (\Exception $e) {
                            return 'N/P';
                        }
                    })
                    ->addColumn('assign_date', function($data) {
                        try {
                            return $data->action_date ? date('d/m/Y', strtotime($data->action_date)) : 'N/P';
                        } catch (\Exception $e) {
                            return 'N/P';
                        }
                    })
                    ->addColumn('task_group', function($data) {
                        try {
                            return $data->task_group ? Utf8Helper::safeSanitize($data->task_group) : 'N/P';
                        } catch (\Exception $e) {
                            return 'N/P';
                        }
                    })
                    ->addColumn('note_description', function($data) {
                        try {
                            if (isset($data->description) && $data->description != "") {
                                // Use Utf8Helper for consistent UTF-8 handling
                                $sanitized_description = Utf8Helper::safeSanitize($data->description);
                                
                                if (mb_strlen($sanitized_description, 'UTF-8') > 190) {
                                    // Base64 in attribute avoids broken markup from newlines/quotes in notes
                                    $encoded_for_attr = htmlspecialchars(base64_encode($sanitized_description), ENT_QUOTES, 'UTF-8');
                                    // For display: use safe truncation without encoding (DataTables rawColumns handles this)
                                    $truncated_desc = Utf8Helper::safeTruncate($sanitized_description, 190, '');
                                    $final_desc = $truncated_desc . '<button type="button" class="btn btn-link btn_readmore" data-toggle="popover" data-trigger="click" data-html="true" data-full-content="'.$encoded_for_attr.'" data-full-content-b64="1" data-placement="top">Read more</button>';
                                } else {
                                    $final_desc = $sanitized_description;
                                }
                            } else {
                                $final_desc = "N/P";
                            }
                            return $final_desc;
                        } catch (\Exception $e) {
                            return "N/P";
                        }
                    })
                    ->addColumn('action', function($list) {
                        try {
                            $actionBtn = '';
                            $current_date1 = $list->action_date ?: date('Y-m-d');

                            // Update Action button - available for all actions including Personal Actions
                            $note_raw = Utf8Helper::safeSanitize($list->description ?? '');
                            $safe_note_b64 = htmlspecialchars(base64_encode($note_raw), ENT_QUOTES, 'UTF-8');
                            $safe_task_group = htmlspecialchars(Utf8Helper::safeSanitize($list->task_group ?? ''), ENT_QUOTES, 'UTF-8');
                            
                            // For personal actions, client_id will be null, so use empty string for encoded value
                            $encoded_client_id = $list->client_id ? base64_encode(convert_uuencode($list->client_id)) : '';
                            
                            // No data-role="popover": action page uses Bootstrap 5 native popover (see action.blade.php)
                            $actionBtn .= '<button type="button" data-assignedto="'.$list->assigned_to.'" data-noteid="'.$safe_note_b64.'" data-note-b64="1" data-taskid="'.$list->id.'" data-taskgroupid="'.$safe_task_group.'" data-actiondate="'.$current_date1.'" data-clientid="'.$encoded_client_id.'" class="btn btn-primary btn-block update_task" style="width: 40px;display: inline;margin-top:0px;">'.IconHelper::fromLegacy('fa fa-edit', ['aria-hidden' => 'true']).'</button>';

                            // Delete button removed from action tab

                            return $actionBtn;
                        } catch (\Exception $e) {
                            return '';
                        }
                    })
                    ->rawColumns(['done_action', 'client_reference', 'note_description', 'action'])
                    // Define how to filter computed columns
                    ->filterColumn('assigner_name', function($query, $keyword) {
                        $keywordLower = strtolower($keyword);
                        $query->whereHas('noteStaff', function($q) use ($keywordLower) {
                            $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $keywordLower . '%'])
                              ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $keywordLower . '%'])
                              ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%' . $keywordLower . '%']);
                        });
                    })
                    ->filterColumn('client_reference', function($query, $keyword) {
                        $keywordLower = strtolower($keyword);
                        $query->whereHas('noteClient', function($q) use ($keywordLower) {
                            $q->whereRaw('LOWER(client_id) LIKE ?', ['%' . $keywordLower . '%'])
                              ->orWhereRaw('LOWER(first_name) LIKE ?', ['%' . $keywordLower . '%'])
                              ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $keywordLower . '%'])
                              ->orWhereHas('company', function ($cq) use ($keywordLower) {
                                  $cq->whereRaw('LOWER(company_name) LIKE ?', ['%' . $keywordLower . '%']);
                              });
                        });
                    });

                // Get the response and ensure UTF-8 encoding
                $response = $dataTable->make(true);
                
                // Set proper UTF-8 headers
                if ($response instanceof \Illuminate\Http\JsonResponse) {
                    $response->header('Content-Type', 'application/json; charset=utf-8');
                }
                
                return $response;
            }
        } catch (\Exception $e) {
            Log::error('Error in getAction: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'draw' => intval($request->get('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'An error occurred while processing the request'
            ], 500);
        }
    }

    public function getActionCounts(Request $request)
    {
        $counts = [
            'all' => 0,
            'call' => 0,
            'checklist' => 0,
            'review' => 0,
            'query' => 0,
            'urgent' => 0,
            'personal_action' => 0,
            'client_portal' => 0,
            'follow_up' => 0,
        ];

        $query = Note::where('status', '<>', '1')
            ->where('type', 'client')
            ->where('is_action', 1);

        if (Auth::user()->role != 1) {
            $query->where('assigned_to', Auth::user()->id);
        }

        $counts['all'] = (clone $query)->count();
        $counts['call'] = (clone $query)->where('task_group', 'Call')->count();
        $counts['checklist'] = (clone $query)->where('task_group', 'Checklist')->count();
        $counts['review'] = (clone $query)->where('task_group', 'Review')->count();
        $counts['query'] = (clone $query)->where('task_group', 'Query')->count();
        $counts['urgent'] = (clone $query)->where('task_group', 'Urgent')->count();
        $counts['personal_action'] = (clone $query)->where('task_group', 'Personal Action')->count();
        $counts['client_portal'] = Auth::user()->role == 1
            ? (clone $query)
                ->where('task_group', 'Client Portal')
                ->distinct(DB::raw("COALESCE(NULLIF(unique_group_id, ''), CONCAT('note_', id))"))
                ->count(DB::raw("COALESCE(NULLIF(unique_group_id, ''), CONCAT('note_', id))"))
            : (clone $query)->where('task_group', 'Client Portal')->count();
        $counts['follow_up'] = (clone $query)->where('task_group', 'Follow Up')->count();

        return response()->json($counts);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @param  Note  $Note
     * @return \Illuminate\Http\Response
     */
    public function destroy( $id,Note $Note)
    {   // dd($id);
        $appointment =Note::find($id);
        $appointment->is_action = 0;
        $appointment->save();

        return redirect()->route('assignee.index')
        ->with('success','Assingee deleted successfully');
    }

    public function destroy_by_me( $id,Note $Note)
    {
        $appointment =Note::find($id);
        $appointment->is_action = 0;
        if( $appointment->save() ){
            $objs = new ActivitiesLog;
            $objs->client_id = $appointment->client_id;
            $objs->created_by = Auth::user()->id;

            $assign_user = \App\Models\Staff::find($appointment->assigned_to);
            if($assign_user){
                $assign_full_name = $assign_user->first_name." ".$assign_user->last_name;
                $objs->subject = 'deleted action for '.@$assign_full_name;
            } else {
                $objs->subject = 'deleted action ';
            }

            $objs->description = '<p>'.$appointment->description.'</p>';
            if(Auth::user()->id != @$appointment->assigned_to){
                $objs->use_for = @$appointment->assigned_to;
            } else {
                $objs->use_for = null;
            }
            $objs->followup_date = @$appointment->action_date;
            $objs->task_group = @$appointment->task_group;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();
            return redirect()->route('assignee.assigned_by_me')->with('success','Activity deleted successfully');
        }
    }

    public function destroy_to_me( $id,Note $Note)
    {
        $appointment =Note::find($id);
        $appointment->is_action = 0;
        $appointment->save();
        return redirect()->route('assignee.assigned_to_me')->with('success','Assingee deleted successfully');
    }

    //incomplete activity remove
    public function destroy_activity($id,Note $Note)
    {
        $appointment = Note::find($id);
        if (! $appointment) {
            echo json_encode(['success' => false, 'message' => 'Activity not found']);
            exit;
        }

        if ((int) $appointment->is_action === 1 && (string) $appointment->type === 'client' && ! $appointment->canBeModifiedBy(Auth::user())) {
            echo json_encode(['success' => false, 'message' => config('constants.unauthorized')]);
            exit;
        }

        $appointment->is_action = 0;
        if( $appointment->save() ){
            $objs = new ActivitiesLog;
            $objs->client_id = $appointment->client_id;
            $objs->created_by = Auth::user()->id;

            $assign_user = \App\Models\Staff::find($appointment->assigned_to);
            if($assign_user){
                $assign_full_name = $assign_user->first_name." ".$assign_user->last_name;
                $objs->subject = 'deleted action for '.@$assign_full_name;
            } else {
                $objs->subject = 'deleted action ';
            }

            $objs->description = '<p>'.$appointment->description.'</p>';
            if(Auth::user()->id != @$appointment->assigned_to){
                $objs->use_for = @$appointment->assigned_to;
            } else {
                $objs->use_for = null;
            }
            $objs->followup_date = @$appointment->action_date;
            $objs->task_group = @$appointment->task_group;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();
            echo json_encode(array('success' => true, 'message' => 'Activity deleted successfully'));
            exit;
        }
    }

    //complete activity remove
    public function destroy_complete_activity( $id,Note $Note)
    {
        $appointment = Note::find($id);
        $appointment->is_action = 0;
        if( $appointment->save() ){
            $objs = new ActivitiesLog;
            $objs->client_id = $appointment->client_id;
            $objs->created_by = Auth::user()->id;

            $assign_user = \App\Models\Staff::find($appointment->assigned_to);
            if($assign_user){
                $assign_full_name = $assign_user->first_name." ".$assign_user->last_name;
                $objs->subject = 'deleted completed action for '.@$assign_full_name;
            } else {
                $objs->subject = 'deleted completed action ';
            }

            $objs->description = '<p>'.$appointment->description.'</p>';
            if(Auth::user()->id != @$appointment->assigned_to){
                $objs->use_for = @$appointment->assigned_to;
            } else {
                $objs->use_for = null;
            }
            $objs->followup_date = @$appointment->action_date;
            $objs->task_group = @$appointment->task_group;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();
            return redirect()->route('assignee.action_completed')->with('success','Action deleted successfully');
        }
    }


    //Get All assignee list dropdown
    public function get_assignee_list(Request $request){
        $assignedto = $request->assignedto;

        $content1 = array();
        foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
        {
            $branchname = \App\Models\Branch::where('id',$admin->office_id)->first();
            $option_value =  $admin->first_name.' '.$admin->last_name.' ('.@$branchname->office_name.')';

            if($admin->id == $assignedto){
                $content1[] = '<option value="'.$admin->id.'" selected>'.$option_value.'</option>';
            } else {
                $content1[] = '<option value="'.$admin->id.'">'.$option_value.'</option>';
            }
        }
        $response['status'] 	= 	true;
        $response['message']	=	$content1;
       echo json_encode($response);
    }

    // Helper function to get assignee name
    protected function getAssigneeName($assigneeId)
    {
        $staff = \App\Models\Staff::find($assigneeId);
        return $staff ? $staff->first_name . ' ' . $staff->last_name : 'Unknown Assignee';
    }

    /**
     * Update an action (Note) based on the provided data.
     * This function marks the current action as complete and creates a new action with the provided information.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAction(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'id' => 'required|exists:notes,id', // Ensure the action ID exists in the notes table
            'client_id' => 'nullable|string', // Client ID is optional for Personal Actions
            'assigned_to' => 'required|exists:staff,id',
            'description' => 'required|string',
            'task_group' => 'required|string|in:Call,Checklist,Review,Query,Urgent,Personal Action,Client Portal',
        ]);

        try {
            // Log the incoming assigned_to value for debugging
            Log::info('Updating action with assigned_to: ' . $validated['assigned_to']);

            // Decode client_id if it was encoded and not empty
            $clientId = null;
            if (!empty($validated['client_id'])) {
                $clientId = convert_uudecode(base64_decode($validated['client_id']));
            }

            // Find the current action (Note) by ID
            $currentAction = Note::findOrFail($validated['id']);

            // Get assignee information for activity logs
            $admin_data_old = Staff::where('id', $currentAction->assigned_to)->first();
            $assignee_name_old = $admin_data_old ? $admin_data_old->first_name . " " . $admin_data_old->last_name : 'N/A';

            // Step 1: Mark the current action as complete
            $currentAction->update(['status' => '1']);

            // Step 2: Activity Feed log for completed action (omit Client Portal — same as Action page complete flow)
            if ($currentAction->client_id && (string) ($currentAction->task_group ?? '') !== 'Client Portal') {
                $completionLog = new ActivitiesLog;
                $completionLog->client_id = $currentAction->client_id;
                $completionLog->created_by = Auth::user()->id;
                $completionLog->subject = 'Action completed for ' . $assignee_name_old;
                $completionLog->description = '<p>' . $currentAction->description . '</p>';
                if (Auth::user()->id != $currentAction->assigned_to) {
                    $completionLog->use_for = $currentAction->assigned_to;
                } else {
                    $completionLog->use_for = null;
                }
                $completionLog->followup_date = $currentAction->updated_at;
                $completionLog->task_group = $currentAction->task_group;
                $completionLog->task_status = 1; // Marked as completed
                $completionLog->pin = 0;
                $completionLog->save();
            }

            $admin_data = Staff::where('id', $validated['assigned_to'])->first();
            $assignee_name = $admin_data ? $admin_data->first_name . " " . $admin_data->last_name : 'N/A';

            // Use the original action's action_date or today's date if not available
            $followupDate = $currentAction->action_date ?: date('Y-m-d');

            // Step 3: Create a new action with the provided information
            $newAction = new Note;
            $newAction->user_id = Auth::user()->id;
            $newAction->client_id = $clientId;
            $newAction->assigned_to = $validated['assigned_to'];
            $newAction->description = $validated['description'];
            $newAction->action_date = $followupDate;
            $newAction->task_group = $validated['task_group'];
            $newAction->type = 'client';
            $newAction->is_action = 1;
            $newAction->status = '0'; // New action is incomplete
            $newAction->pin = 0; // Set pin to 0 (required field)
            $actionUniqueId = 'group_' . uniqid('', true);
            $newAction->unique_group_id = $actionUniqueId; // Generate unique group ID for the new action
            $newAction->save();

            // Step 4: Activity Feed log for the new action (omit when new row is Client Portal category)
            if ($clientId && (string) ($validated['task_group'] ?? '') !== 'Client Portal') {
                $newActionLog = new ActivitiesLog;
                $newActionLog->client_id = $clientId;
                $newActionLog->created_by = Auth::user()->id;
                $newActionLog->subject = 'New action assigned for ' . $assignee_name;
                $newActionLog->description = '<p>' . $validated['description'] . '</p>';
                if (Auth::user()->id != $validated['assigned_to']) {
                    $newActionLog->use_for = $validated['assigned_to'];
                } else {
                    $newActionLog->use_for = null;
                }
                $newActionLog->followup_date = $followupDate;
                $newActionLog->task_group = $validated['task_group'];
                $newActionLog->task_status = 0; // New action is incomplete
                $newActionLog->pin = 0;
                $newActionLog->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Action completed and new action created successfully.'
            ], 200);

        } catch (\Exception $e) {
            // Log the exception for debugging
            Log::error('Error updating action: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the action: ' . $e->getMessage()
            ], 500);
        }
    }

}