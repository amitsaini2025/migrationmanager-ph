<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BookingAppointment;
use App\Helpers\Utf8Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientPortalDashboardController extends Controller
{
    /**
     * Get Dashboard Data
     * GET /api/dashboard
     */
    public function dashboard(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;
            
            // Validate required sel_matter_id parameter
            $request->validate([
                'sel_matter_id' => 'required|integer|min:1'
            ]);
            
            $selMatterId = $request->get('sel_matter_id');

            // Get document count
            $totalDocuments = $this->getTotalDocuments($clientId, $selMatterId);
            // Get appointment count
            $totalAppointments = $this->getTotalAppointments($clientId);

            // Get case summary statistics
            $caseSummary = $this->getCaseSummary($clientId, $selMatterId);
            
            // Get recent cases
            $recentCases = $this->getRecentCases($clientId, $selMatterId);

            // Get document status
            $documentStatus = $this->getDocumentStatus($clientId, $selMatterId);

            // Get upcoming deadlines
            $upcomingDeadlines = $this->getUpcomingDeadlines($clientId);
            
            // Get recent activity
            $recentActivity = $this->getRecentActivity($clientId);
            
            
            return response()->json([
                'success' => true,
                'data' => [
                    'active_cases' => $caseSummary['active_cases'],
                    'total_documents' => $totalDocuments,
                    'total_appointments' => $totalAppointments,
                    'case_summary' => $caseSummary,
                    'recent_cases' => $recentCases,
                    'document_status' => $documentStatus,
                    'upcoming_deadlines' => $upcomingDeadlines,
                    'recent_activity' => $recentActivity,
                    'selected_matter_id' => $selMatterId
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Dashboard API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Recent Cases (View All)
     * GET /api/recent-cases
     */
    public function recentCaseViewAll(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Validate required sel_matter_id parameter
            $request->validate([
                'sel_matter_id' => 'required|integer|min:1'
            ]);

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');
            $status = $request->get('status', '');
            $selMatterId = $request->get('sel_matter_id');

            // Build query for recent cases
            $query = DB::table('client_matters')
                ->join('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                ->leftJoin('staff as migration_agent', 'client_matters.sel_migration_agent', '=', 'migration_agent.id')
                ->leftJoin('staff as person_responsible', 'client_matters.sel_person_responsible', '=', 'person_responsible.id')
                ->leftJoin('staff as person_assisting', 'client_matters.sel_person_assisting', '=', 'person_assisting.id')
                ->where('client_matters.client_id', $clientId)
                ->where('client_matters.matter_status', 1);   // 1 for active case
                //->where('client_matters.workflow_stage_id','!=', 14);   // 14 for file closed stage

            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('matters.title', 'LIKE', "%{$search}%")
                      ->orWhere('client_matters.client_unique_matter_no', 'LIKE', "%{$search}%")
                      ->orWhere('workflow_stages.name', 'LIKE', "%{$search}%");
                });
            }

            // Apply status filter
            if (!empty($status)) {
                $query->where('workflow_stages.name', 'LIKE', "%{$status}%");
            }

            // Apply matter filter (required parameter)
            $query->where('client_matters.id', $selMatterId);

            // Get total count for pagination
            $totalCases = $query->count();

            // Get paginated results
            $recentCases = $query
                ->orderBy('client_matters.updated_at', 'desc')
                ->select(
                    'client_matters.*', 
                    'matters.title', 
                    'workflow_stages.name as stage_name',
                    'migration_agent.first_name as migration_agent_first_name',
                    'migration_agent.last_name as migration_agent_last_name',
                    'person_responsible.first_name as person_responsible_first_name',
                    'person_responsible.last_name as person_responsible_last_name',
                    'person_assisting.first_name as person_assisting_first_name',
                    'person_assisting.last_name as person_assisting_last_name'
                )
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(function ($case) {
                    $title = $case->title;
                    if ($case->client_unique_matter_no) {
                        $title .= ' (' . $case->client_unique_matter_no . ')';
                    }
                    
                    // Calculate progress percentage based on workflow_stage_id
                    // Total stages: 1-14, where 14 = 100% (file closed)
                    $workflowStageId = $case->workflow_stage_id ?? 1;
                    $progressPercentage = round(($workflowStageId / 14) * 100);
                    
                    // Determine case status based on workflow stage
                    $caseStatus = $case->stage_name ?? 'active';
                    $isFileClosedStatus = ($workflowStageId == 14);
                    
                    // Format agent names
                    $migrationAgentName = 'Unassigned';
                    if (!empty($case->migration_agent_first_name) || !empty($case->migration_agent_last_name)) {
                        $migrationAgentName = trim(($case->migration_agent_first_name ?? '') . ' ' . ($case->migration_agent_last_name ?? ''));
                    }
                    
                    $personResponsibleName = 'Unassigned';
                    if (!empty($case->person_responsible_first_name) || !empty($case->person_responsible_last_name)) {
                        $personResponsibleName = trim(($case->person_responsible_first_name ?? '') . ' ' . ($case->person_responsible_last_name ?? ''));
                    }
                    
                    $personAssistingName = 'Unassigned';
                    if (!empty($case->person_assisting_first_name) || !empty($case->person_assisting_last_name)) {
                        $personAssistingName = trim(($case->person_assisting_first_name ?? '') . ' ' . ($case->person_assisting_last_name ?? ''));
                    }
                    
                    return [
                        'id' => $case->id,
                        'title' => $title,
                        'case_number' => 'Case #' . $case->id,
                        'status' => ucfirst(str_replace('_', ' ', $caseStatus)),
                        'stage_name' => $case->stage_name,
                        'workflow_stage_id' => $workflowStageId,
                        'progress_percentage' => $progressPercentage,
                        'progress_display' => $progressPercentage . '%',
                        'is_file_closed' => $isFileClosedStatus,
                        'agents' => [
                            'migration_agent' => [
                                'id' => $case->sel_migration_agent,
                                'name' => $migrationAgentName
                            ],
                            'person_responsible' => [
                                'id' => $case->sel_person_responsible,
                                'name' => $personResponsibleName
                            ],
                            'person_assisting' => [
                                'id' => $case->sel_person_assisting,
                                'name' => $personAssistingName
                            ]
                        ],
                        'created_at' => $case->created_at,
                        'updated_at' => $case->updated_at,
                        'last_updated' => \Carbon\Carbon::parse($case->updated_at)->diffForHumans()
                    ];
                });

            // Calculate pagination info
            $totalPages = ceil($totalCases / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            return response()->json([
                'success' => true,
                'data' => [
                    'cases' => $recentCases,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_cases' => $totalCases,
                        'total_pages' => $totalPages,
                        'has_next_page' => $hasNextPage,
                        'has_prev_page' => $hasPrevPage
                    ],
                    'filters' => [
                        'search' => $search,
                        'status' => $status,
                        'sel_matter_id' => $selMatterId
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Recent Cases View All API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent cases',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Documents (View All)
     * GET /api/documents
     */
    public function documentViewAll(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Validate required sel_matter_id parameter
            $request->validate([
                'sel_matter_id' => 'required|integer|min:1'
            ]);

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');
            $status = $request->get('status', ''); // approved, pending, rejected
            $docType = $request->get('doc_type', ''); // visa, personal
            $selMatterId = $request->get('sel_matter_id');

            // Build query for documents
            $query = DB::table('documents')
                ->where('client_id', $clientId)
                ->whereNull('not_used_doc')
                ->whereIn('doc_type', ['visa', 'personal']);

            // Apply search filter
            if (!empty($search)) {
                $query->where('file_name', 'LIKE', "%{$search}%");
            }

            // Apply status filter
            if (!empty($status)) {
                switch (strtolower($status)) {
                    case 'approved':
                        $query->where('status', 'signed');
                        break;
                    case 'pending':
                        $query->whereIn('status', ['draft', 'sent']);
                        break;
                    case 'rejected':
                        $query->whereNotIn('status', ['signed', 'draft', 'sent']);
                        break;
                }
            }

            // Apply document type filter
            if (!empty($docType) && in_array($docType, ['visa', 'personal'])) {
                $query->where('doc_type', $docType);
            }

            // Apply matter filter (required parameter), but only for visa documents
            $query->where(function($subQuery) use ($selMatterId) {
                $subQuery->where(function($visaQuery) use ($selMatterId) {
                    // For visa documents, apply matter filter
                    $visaQuery->where('doc_type', 'visa')
                             ->where('client_matter_id', $selMatterId);
                })
                ->orWhere(function($personalQuery) {
                    // For personal documents, don't apply matter filter
                    $personalQuery->where('doc_type', 'personal');
                });
            });

            // Get total count for pagination
            $totalDocuments = $query->count();

            // Get paginated results
            $documents = $query
                ->orderBy('updated_at', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(function ($doc) {
                    // Determine display status based on document status
                    $displayStatus = $doc->status;
                    if (in_array($doc->status, ['draft', 'sent'])) {
                        $displayStatus = 'pending';
                    } elseif ($doc->status === 'signed') {
                        $displayStatus = 'approved';
                    } else {
                        $displayStatus = 'rejected';  // All other statuses
                    }

                    return [
                        'id' => $doc->id,
                        'name' => $doc->file_name ?? 'Document',
                        'file_name' => $doc->file_name,
                        'file_type' => $doc->filetype,
                        'doc_type' => $doc->doc_type,
                        'status' => ucfirst($displayStatus),
                        'original_status' => $doc->status,
                        'file_size' => $doc->file_size,
                        'uploaded_at' => $doc->created_at,
                        'updated_at' => $doc->updated_at,
                        'uploaded_days_ago' => \Carbon\Carbon::parse($doc->created_at)->diffInDays(now()),
                        'last_updated' => \Carbon\Carbon::parse($doc->updated_at)->diffForHumans(),
                        'file_url' => $doc->myfile ?? null,
                        'file_key' => $doc->myfile_key ?? null
                    ];
                });

            // Calculate pagination info
            $totalPages = ceil($totalDocuments / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            // Get summary counts for current filters
            $summaryQuery = DB::table('documents')
                ->where('client_id', $clientId)
                ->whereNull('not_used_doc')
                ->whereIn('doc_type', ['visa', 'personal']);

            // Apply same filters as main query for summary
            if (!empty($search)) {
                $summaryQuery->where('file_name', 'LIKE', "%{$search}%");
            }
            if (!empty($docType) && in_array($docType, ['visa', 'personal'])) {
                $summaryQuery->where('doc_type', $docType);
            }
            // Apply matter filter (required parameter), but only for visa documents
            $summaryQuery->where(function($subQuery) use ($selMatterId) {
                $subQuery->where(function($visaQuery) use ($selMatterId) {
                    // For visa documents, apply matter filter
                    $visaQuery->where('doc_type', 'visa')
                             ->where('client_matter_id', $selMatterId);
                })
                ->orWhere(function($personalQuery) {
                    // For personal documents, don't apply matter filter
                    $personalQuery->where('doc_type', 'personal');
                });
            });

            $approvedCount = (clone $summaryQuery)->where('status', 'signed')->count();
            $pendingCount = (clone $summaryQuery)->whereIn('status', ['draft', 'sent'])->count();
            $rejectedCount = (clone $summaryQuery)->whereNotIn('status', ['signed', 'draft', 'sent'])->count();
            
            $totalFilteredDocs = $approvedCount + $pendingCount + $rejectedCount;
            $progressPercentage = $totalFilteredDocs > 0 ? round(($approvedCount / $totalFilteredDocs) * 100) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'documents' => $documents,
                    'summary' => [
                        'approved' => $approvedCount,
                        'pending' => $pendingCount,
                        'rejected' => $rejectedCount,
                        'total' => $totalFilteredDocs
                    ],
                    'overall_progress' => $progressPercentage,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_documents' => $totalDocuments,
                        'total_pages' => $totalPages,
                        'has_next_page' => $hasNextPage,
                        'has_prev_page' => $hasPrevPage
                    ],
                    'filters' => [
                        'search' => $search,
                        'status' => $status,
                        'doc_type' => $docType,
                        'sel_matter_id' => $selMatterId
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Documents View All API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Upcoming Deadlines (View All)
     * GET /api/upcoming-deadlines
     */
    public function upcomingDeadlinesViewAll(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');

            // Get upcoming appointments (after current datetime)
            $upcomingAppointments = BookingAppointment::where('client_id', $clientId)
                ->where('appointment_datetime', '>', now())
                ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
                ->when(!empty($search), function($query) use ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('client_name', 'LIKE', "%{$search}%")
                          ->orWhere('service_type', 'LIKE', "%{$search}%");
                    });
                })
                ->orderBy('appointment_datetime', 'asc')
                ->get()
                ->map(function ($appointment) {
                    $appointmentDate = $appointment->appointment_datetime;
                    $daysUntil = $appointmentDate->diffInDays(now());
                    
                    // Use client_name or service_type as title
                    $title = $appointment->client_name;
                    if ($appointment->service_type) {
                        $title .= ' - ' . $appointment->service_type;
                    }
                    
                    return [
                        'id' => $appointment->id,
                        'title' => $title,
                        'date' => $appointmentDate->format('Y-m-d'),
                        'time' => $appointmentDate->format('H:i:s'),
                        'datetime' => $appointmentDate->format('M d, Y') . ' at ' . $appointmentDate->format('g:i A'),
                        'status' => ucfirst($appointment->status),
                        'days_until' => $daysUntil,
                        'type' => 'appointment',
                        'created_at' => $appointment->created_at,
                        'updated_at' => $appointment->updated_at,
                        'last_updated' => \Carbon\Carbon::parse($appointment->updated_at)->diffForHumans()
                    ];
                });

            // Get notes with deadlines (only future deadlines)
            $deadlineNotes = DB::table('notes')
                ->where('client_id', $clientId)
                ->whereNotNull('note_deadline') // note_deadline is not NULL
                ->where('note_deadline', '>', now()->toDateString()) // After current date
                ->where('status', '!=', 1)
                ->when(!empty($search), function($query) use ($search) {
                    $query->where('title', 'LIKE', "%{$search}%");
                })
                ->orderBy('note_deadline', 'asc')
                ->get()
                ->map(function ($note) {
                    $dueDate = \Carbon\Carbon::parse($note->note_deadline);
                    $daysUntil = $dueDate->diffInDays(now());
                    
                    return [
                        'id' => $note->id,
                        'title' => $note->title,
                        'due_date' => $note->note_deadline,
                        'due_datetime' => $dueDate->format('M d, Y'),
                        'status' => 'pending',
                        'days_until' => $daysUntil,
                        'priority' => $this->getTaskPriority($note->status ?? 'pending', $daysUntil),
                        'type' => 'deadline',
                        'is_overdue' => $daysUntil < 0,
                        'created_at' => $note->created_at,
                        'updated_at' => $note->updated_at,
                        'last_updated' => \Carbon\Carbon::parse($note->updated_at)->diffForHumans()
                    ];
                });

            // Combine and sort all items
            $allItems = $upcomingAppointments->concat($deadlineNotes);
            
            // Sort by date (deadlines and appointments)
            $allItems = $allItems->sortBy(function($item) {
                return $item['type'] === 'appointment' ? $item['date'] : $item['due_date'];
            })->values();

            // Get total count for pagination
            $totalItems = $allItems->count();

            // Apply pagination
            $paginatedItems = $allItems->slice(($page - 1) * $perPage, $perPage)->values();

            // Calculate pagination info
            $totalPages = ceil($totalItems / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            // Calculate summary counts
            $dueThisWeekCount = $deadlineNotes->where('days_until', '>=', 0)->where('days_until', '<=', 7)->count();
            $appointmentsCount = $upcomingAppointments->count();
            $overdueCount = $deadlineNotes->where('is_overdue', true)->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $paginatedItems,
                    'summary' => [
                        'due_this_week_count' => $dueThisWeekCount,
                        'appointments_count' => $appointmentsCount,
                        'overdue_count' => $overdueCount,
                        'total_items' => $totalItems
                    ],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_items' => $totalItems,
                        'total_pages' => $totalPages,
                        'has_next_page' => $hasNextPage,
                        'has_prev_page' => $hasPrevPage
                    ],
                    'filters' => [
                        'search' => $search
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Upcoming Deadlines View All API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming deadlines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get All Recent Activity (View All)
     * GET /api/recent-activity
     */
    public function recentActivityViewAll(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');
            $type = $request->get('type', ''); // Note, Action, Document, Email, Activity

            // Build query for recent activities (Client Portal only: source = client_portal OR client_portal_web)
            $query = DB::table('activities_logs')
                ->where('client_id', $clientId)
                ->whereIn('source', ['client_portal', 'client_portal_web']);

            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Get total count for pagination
            $totalActivities = $query->count();

            // Get paginated results
            $activities = $query
                ->orderBy('updated_at', 'desc')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()
                ->map(function ($activity) use ($type) {
                    // Sanitize subject and description for UTF-8 encoding before processing
                    $sanitizedSubject = Utf8Helper::safeSanitize($activity->subject ?? '');
                    $sanitizedDescription = Utf8Helper::safeSanitize($activity->description ?? '');
                    
                    // Determine type based on task_group column and subject content
                    $subject = mb_strtolower($sanitizedSubject, 'UTF-8');
                    $taskGroupNotEmpty = !empty($activity->task_group);
                    
                    // Condition 1: task_group not null AND subject contains note keywords => Note
                    if ($taskGroupNotEmpty && (mb_strpos($subject, 'added a note', 0, 'UTF-8') !== false || mb_strpos($subject, 'updated a note', 0, 'UTF-8') !== false)) {
                        $activityType = 'Note';
                    }
                    // Condition 2: task_group not null AND subject does NOT contain note keywords => Action
                    else if ($taskGroupNotEmpty) {
                        $activityType = 'Action';
                    }
                    // Condition 3a: task_group is null AND subject contains document keywords => Document
                    else if (mb_strpos($subject, 'added migration document', 0, 'UTF-8') !== false || mb_strpos($subject, 'added personal document', 0, 'UTF-8') !== false) {
                        $activityType = 'Document';
                    }
                    // Condition 3b: task_group is null AND subject contains email verification => Email
                    else if (mb_strpos($subject, 'email verification', 0, 'UTF-8') !== false) {
                        $activityType = 'Email';
                    }
                    // Condition 4: Else => Activity
                    else {
                        $activityType = 'Activity';
                    }

                    // Apply type filter if specified
                    if (!empty($type) && $activityType !== $type) {
                        return null; // Will be filtered out
                    }
                    
                    // Strip HTML tags and limit title to 1 line (approximately 50 characters)
                    $title = strip_tags($sanitizedSubject ?: 'Activity');
                    $title = mb_strlen($title, 'UTF-8') > 50 ? mb_substr($title, 0, 50, 'UTF-8') . '...' : $title;
                    
                    // Strip HTML tags and limit description to 2 lines (approximately 100 characters)
                    $description = strip_tags($sanitizedDescription ?: $sanitizedSubject);
                    $description = mb_strlen($description, 'UTF-8') > 100 ? mb_substr($description, 0, 100, 'UTF-8') . '...' : $description;
                    
                    return [
                        'id' => $activity->id,
                        'type' => $activityType,
                        'title' => $title,
                        'description' => $description,
                        'created_at' => $activity->created_at,
                        'updated_at' => $activity->updated_at,
                        'time_ago' => \Carbon\Carbon::parse($activity->updated_at)->diffForHumans(),
                        'task_group' => $activity->task_group
                    ];
                })
                ->filter() // Remove null values from type filtering
                ->values();

            // Calculate pagination info
            $totalPages = ceil($totalActivities / $perPage);
            $hasNextPage = $page < $totalPages;
            $hasPrevPage = $page > 1;

            // Get type counts for summary
            $typeCounts = [
                'Note' => 0,
                'Action' => 0,
                'Document' => 0,
                'Email' => 0,
                'Activity' => 0
            ];

            // Get all activities for type counting (without pagination) – same Client Portal filter
            $allActivities = DB::table('activities_logs')
                ->where('client_id', $clientId)
                ->whereIn('source', ['client_portal', 'client_portal_web'])
                ->when(!empty($search), function($query) use ($search) {
                    $query->where(function($q) use ($search) {
                        $q->where('subject', 'LIKE', "%{$search}%")
                          ->orWhere('description', 'LIKE', "%{$search}%");
                    });
                })
                ->get()
                ->map(function ($activity) {
                    // Sanitize subject for UTF-8 encoding before processing
                    $sanitizedSubject = Utf8Helper::safeSanitize($activity->subject ?? '');
                    $subject = mb_strtolower($sanitizedSubject, 'UTF-8');
                    $taskGroupNotEmpty = !empty($activity->task_group);
                    
                    if ($taskGroupNotEmpty && (mb_strpos($subject, 'added a note', 0, 'UTF-8') !== false || mb_strpos($subject, 'updated a note', 0, 'UTF-8') !== false)) {
                        return 'Note';
                    } else if ($taskGroupNotEmpty) {
                        return 'Action';
                    } else if (mb_strpos($subject, 'added migration document', 0, 'UTF-8') !== false || mb_strpos($subject, 'added personal document', 0, 'UTF-8') !== false) {
                        return 'Document';
                    } else if (mb_strpos($subject, 'email verification', 0, 'UTF-8') !== false) {
                        return 'Email';
                    } else {
                        return 'Activity';
                    }
                });

            // Count types
            foreach ($allActivities as $activityType) {
                if (isset($typeCounts[$activityType])) {
                    $typeCounts[$activityType]++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities,
                    'type_summary' => $typeCounts,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_activities' => $totalActivities,
                        'total_pages' => $totalPages,
                        'has_next_page' => $hasNextPage,
                        'has_prev_page' => $hasPrevPage
                    ],
                    'filters' => [
                        'search' => $search,
                        'type' => $type
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Recent Activity View All API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get case summary statistics
     */
    private function getCaseSummary($clientId, $selMatterId = null)
    {
        // Build base query
        $baseQuery = DB::table('client_matters')
            ->where('client_id', $clientId)
            ->where('matter_status', 1);   // 1 for active case
            
        // Apply matter filter if provided
        if (!is_null($selMatterId)) {
            $baseQuery->where('id', $selMatterId);
        }

        // Get total matters/cases for the client
        $totalCases = (clone $baseQuery)->count();

        $activeCases = (clone $baseQuery)
            ->where('workflow_stage_id','!=', 14)   // 14 for file closed stage
            ->count();

        $completedCases = (clone $baseQuery)
            ->where('workflow_stage_id', 14)   // 14 for file closed stage
            ->count();

        // Get total count of matters for the client
        $totalMatters = DB::table('client_matters')
            ->where('client_id', $clientId)
            ->where('matter_status', 1) // 1 for active matter
            ->count();

        return [
            'active_cases' => $activeCases,
            'completed_cases' => $completedCases,
            'total_cases' => $totalCases,
            'total_matters' => $totalMatters
        ];
    }

    /**
     * Get total documents count for the client
     */
    private function getTotalDocuments($clientId, $selMatterId = null)
    {
        $query = DB::table('documents')
            ->where('client_id', $clientId)
            ->whereNull('not_used_doc')
            ->whereIn('doc_type', ['visa', 'personal']);
            
        // Apply matter filter if provided, but only for visa documents
        if (!is_null($selMatterId)) {
            $query->where(function($subQuery) use ($selMatterId) {
                $subQuery->where(function($visaQuery) use ($selMatterId) {
                    // For visa documents, apply matter filter
                    $visaQuery->where('doc_type', 'visa')
                             ->where('client_matter_id', $selMatterId);
                })
                ->orWhere(function($personalQuery) {
                    // For personal documents, don't apply matter filter
                    $personalQuery->where('doc_type', 'personal');
                });
            });
        }

        $totalDocuments = $query->count();

        return $totalDocuments;
    }

    /**
     * Get total appointments count for the client
     */
    private function getTotalAppointments($clientId)
    {
        $totalAppointments = BookingAppointment::where('client_id', $clientId)
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->where('appointment_datetime', '>', now())
            ->count();

        return $totalAppointments;
    }

    /**
     * Get recent cases
     */
    private function getRecentCases($clientId, $selMatterId = null)
    {
        $query = DB::table('client_matters')
            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
            ->leftJoin('staff as migration_agent', 'client_matters.sel_migration_agent', '=', 'migration_agent.id')
            ->leftJoin('staff as person_responsible', 'client_matters.sel_person_responsible', '=', 'person_responsible.id')
            ->leftJoin('staff as person_assisting', 'client_matters.sel_person_assisting', '=', 'person_assisting.id')
            ->where('client_matters.client_id', $clientId)
            ->where('client_matters.matter_status', 1)   // 1 for active case
            ->where('client_matters.workflow_stage_id','!=', 14);   // 14 for file closed stage
            
        // Apply matter filter if provided
        if (!is_null($selMatterId)) {
            $query->where('client_matters.id', $selMatterId);
        }
            
        $recentCases = $query
            ->orderBy('client_matters.updated_at', 'desc')
            ->limit(3)
            ->select(
                'client_matters.*', 
                'matters.title', 
                'workflow_stages.name as stage_name',
                'migration_agent.first_name as migration_agent_first_name',
                'migration_agent.last_name as migration_agent_last_name',
                'person_responsible.first_name as person_responsible_first_name',
                'person_responsible.last_name as person_responsible_last_name',
                'person_assisting.first_name as person_assisting_first_name',
                'person_assisting.last_name as person_assisting_last_name'
            )
            ->get()
            ->map(function ($case) {
                // If sel_matter_id is 1 or title is null, use "General Matter"
                $title = 'General Matter';
                if ($case->sel_matter_id != 1 && !empty($case->title)) {
                    $title = $case->title;
                }
                
                if ($case->client_unique_matter_no) {
                    $title .= ' (' . $case->client_unique_matter_no . ')';
                }
                
                // Calculate progress percentage based on workflow_stage_id
                // Total stages: 1-14, where 14 = 100% (file closed)
                $workflowStageId = $case->workflow_stage_id ?? 1;
                $progressPercentage = round(($workflowStageId / 14) * 100);
                
                // Format agent names
                $migrationAgentName = 'Unassigned';
                if (!empty($case->migration_agent_first_name) || !empty($case->migration_agent_last_name)) {
                    $migrationAgentName = trim(($case->migration_agent_first_name ?? '') . ' ' . ($case->migration_agent_last_name ?? ''));
                }
                
                $personResponsibleName = 'Unassigned';
                if (!empty($case->person_responsible_first_name) || !empty($case->person_responsible_last_name)) {
                    $personResponsibleName = trim(($case->person_responsible_first_name ?? '') . ' ' . ($case->person_responsible_last_name ?? ''));
                }
                
                $personAssistingName = 'Unassigned';
                if (!empty($case->person_assisting_first_name) || !empty($case->person_assisting_last_name)) {
                    $personAssistingName = trim(($case->person_assisting_first_name ?? '') . ' ' . ($case->person_assisting_last_name ?? ''));
                }
                
                return [
                    'id' => $case->id,
                    'title' => $title,
                    'case_number' => 'Case #' . $case->id,
                    'status' => ucfirst(str_replace('_', ' ', $case->stage_name ?? 'active')),
                    'workflow_stage_id' => $workflowStageId,
                    'progress_percentage' => $progressPercentage,
                    'progress_display' => $progressPercentage . '%',
                    'agents' => [
                        'migration_agent' => [
                            'id' => $case->sel_migration_agent,
                            'name' => $migrationAgentName
                        ],
                        'person_responsible' => [
                            'id' => $case->sel_person_responsible,
                            'name' => $personResponsibleName
                        ],
                        'person_assisting' => [
                            'id' => $case->sel_person_assisting,
                            'name' => $personAssistingName
                        ]
                    ],
                    'updated_at' => $case->updated_at
                ];
            });

        return $recentCases;
    }

    /**
     * Get document status overview
     */
    private function getDocumentStatus($clientId, $selMatterId = null)
    {
        $baseQuery = DB::table('documents')
            ->where('client_id', $clientId)
            ->whereIn('doc_type', ['visa', 'personal'])
            ->whereNull('not_used_doc');
            
        // Apply matter filter if provided, but only for visa documents
        if (!is_null($selMatterId)) {
            $baseQuery->where(function($subQuery) use ($selMatterId) {
                $subQuery->where(function($visaQuery) use ($selMatterId) {
                    // For visa documents, apply matter filter
                    $visaQuery->where('doc_type', 'visa')
                             ->where('client_matter_id', $selMatterId);
                })
                ->orWhere(function($personalQuery) {
                    // For personal documents, don't apply matter filter
                    $personalQuery->where('doc_type', 'personal');
                });
            });
        }

        $approvedDocs = (clone $baseQuery)
            ->where('status', 'signed')
            ->count();

        $pendingDocs = (clone $baseQuery)
            ->whereIn('status', ['draft', 'sent'])
            ->count();

        $rejectedDocs = (clone $baseQuery)
            ->whereNotIn('status', ['signed', 'draft', 'sent'])
            ->count();

        $totalDocs = $approvedDocs + $pendingDocs + $rejectedDocs;
        $progressPercentage = $totalDocs > 0 ? round(($approvedDocs / $totalDocs) * 100) : 0;

        // Get recent documents
        $recentDocumentsQuery = DB::table('documents')
            ->where('client_id', $clientId)
            ->whereIn('doc_type', ['visa', 'personal'])
            ->whereNull('not_used_doc');
            
        // Apply matter filter if provided, but only for visa documents
        if (!is_null($selMatterId)) {
            $recentDocumentsQuery->where(function($subQuery) use ($selMatterId) {
                $subQuery->where(function($visaQuery) use ($selMatterId) {
                    // For visa documents, apply matter filter
                    $visaQuery->where('doc_type', 'visa')
                             ->where('client_matter_id', $selMatterId);
                })
                ->orWhere(function($personalQuery) {
                    // For personal documents, don't apply matter filter
                    $personalQuery->where('doc_type', 'personal');
                });
            });
        }
            
        $recentDocuments = $recentDocumentsQuery
            ->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($doc) {
                // Determine display status based on document status
                $displayStatus = $doc->status;
                if (in_array($doc->status, ['draft', 'sent'])) {
                    $displayStatus = 'pending';
                } elseif ($doc->status === 'signed') {
                    $displayStatus = 'approved';
                } else {
                    $displayStatus = 'rejected';  // All other statuses
                }

                return [
                    'id' => $doc->id,
                    'name' => $doc->file_name ?? 'Document',
                    'status' => ucfirst($displayStatus),
                    'uploaded_at' => $doc->created_at,
                    'uploaded_days_ago' => \Carbon\Carbon::parse($doc->created_at)->diffInDays(now())
                ];
            });

        return [
            'summary' => [
                'approved' => $approvedDocs,
                'pending' => $pendingDocs,
                'rejected' => $rejectedDocs
            ],
            'overall_progress' => $progressPercentage,
            'recent_documents' => $recentDocuments
        ];
    }

    /**
     * Get upcoming deadlines and appointments
     */
    private function getUpcomingDeadlines($clientId)
    {
        // Get upcoming appointments (after current datetime)
        $appointmentsQuery = BookingAppointment::where('client_id', $clientId)
            ->where('appointment_datetime', '>', now())
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed']);
            
        $upcomingAppointments = $appointmentsQuery
            ->orderBy('appointment_datetime', 'asc')
            ->limit(3)
            ->get()
            ->map(function ($appointment) {
                $appointmentDate = $appointment->appointment_datetime;
                $daysUntil = $appointmentDate->diffInDays(now());
                
                // Use client_name or service_type as title
                $title = $appointment->client_name;
                if ($appointment->service_type) {
                    $title .= ' - ' . $appointment->service_type;
                }
                
                return [
                    'id' => $appointment->id,
                    'title' => $title,
                    'date' => $appointmentDate->format('Y-m-d'),
                    'time' => $appointmentDate->format('H:i:s'),
                    'datetime' => $appointmentDate->format('M d, Y') . ' at ' . $appointmentDate->format('g:i A'),
                    'status' => ucfirst($appointment->status),
                    'days_until' => $daysUntil,
                    'type' => 'appointment'
                ];
            });

        // Get notes with deadlines for this week
        $notesQuery = DB::table('notes')
            ->where('client_id', $clientId)
            ->where('note_deadline', '>=', now()->toDateString())
            ->where('note_deadline', '<=', now()->addDays(7)->toDateString())
            ->where('status', '!=', 1);
            
       
            
        $dueThisWeekNotes = $notesQuery
            ->orderBy('note_deadline', 'asc')
            ->get()
            ->map(function ($note) {
                $dueDate = \Carbon\Carbon::parse($note->note_deadline);
                $daysUntil = $dueDate->diffInDays(now());
                
                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'due_date' => $note->note_deadline,
                    'due_datetime' => $dueDate->format('M d, Y'),
                    'status' =>  'pending',
                    'days_until' => $daysUntil,
                    'priority' => $this->getTaskPriority($note->status ?? 'pending', $daysUntil),
                    'type' => 'deadline'
                ];
            });

        // Get overdue notes (past deadline and status != 1)
        $overdueQuery = DB::table('notes')
            ->where('client_id', $clientId)
            ->where('note_deadline', '<', now()->toDateString())
            ->where('status', '!=', 1);
            
       
            
        $overdueCount = $overdueQuery->count();

        // due_this_week_list will only contain notes from notes table

        return [
            'summary' => [
                'due_this_week_count' => $dueThisWeekNotes->count(),
                'appointments_count' => $upcomingAppointments->count(),
                'overdue_count' => $overdueCount
            ],
            'due_this_week_list' => $dueThisWeekNotes
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity($clientId)
    {
        $query = DB::table('activities_logs')
            ->where('client_id', $clientId)
            ->whereIn('source', ['client_portal', 'client_portal_web']);

        $recentActivities = $query
            ->orderBy('updated_at', 'desc')
            ->limit(3)
            ->get()
            ->map(function ($activity) {
                // Sanitize subject and description for UTF-8 encoding before processing
                $sanitizedSubject = Utf8Helper::safeSanitize($activity->subject ?? '');
                $sanitizedDescription = Utf8Helper::safeSanitize($activity->description ?? '');
                
                // Determine type based on task_group column and subject content
                $subject = mb_strtolower($sanitizedSubject, 'UTF-8');
                $taskGroupNotEmpty = !empty($activity->task_group);
                
                // Condition 1: task_group not null AND subject contains note keywords => Note
                if ($taskGroupNotEmpty && (mb_strpos($subject, 'added a note', 0, 'UTF-8') !== false || mb_strpos($subject, 'updated a note', 0, 'UTF-8') !== false)) {
                    $type = 'Note';
                }
                // Condition 2: task_group not null AND subject does NOT contain note keywords => Action
                else if ($taskGroupNotEmpty) {
                    $type = 'Action';
                }
                // Condition 3a: task_group is null AND subject contains document keywords => Document
                else if (mb_strpos($subject, 'added migration document', 0, 'UTF-8') !== false || mb_strpos($subject, 'added personal document', 0, 'UTF-8') !== false) {
                    $type = 'Document';
                }
                // Condition 3b: task_group is null AND subject contains email verification => Email
                else if (mb_strpos($subject, 'email verification', 0, 'UTF-8') !== false) {
                    $type = 'Email';
                }
                // Condition 4: Else => Activity
                else {
                    $type = 'Activity';
                }
                
                // Strip HTML tags and limit title to 1 line (approximately 50 characters)
                $title = strip_tags($sanitizedSubject ?: 'Activity');
                $title = mb_strlen($title, 'UTF-8') > 50 ? mb_substr($title, 0, 50, 'UTF-8') . '...' : $title;
                
                // Strip HTML tags and limit description to 2 lines (approximately 100 characters)
                $description = strip_tags($sanitizedDescription ?: $sanitizedSubject);
                $description = mb_strlen($description, 'UTF-8') > 100 ? mb_substr($description, 0, 100, 'UTF-8') . '...' : $description;
                
                return [
                    'id' => $activity->id,
                    'type' => $type,
                    'title' => $title,
                    'description' => $description,
                    //'icon' => $activity->icon ?? 'info',
                   // 'icon_color' => $activity->icon_color ?? 'blue',
                    'created_at' => $activity->created_at,
                    'updated_at' => $activity->updated_at,
                    'time_ago' => \Carbon\Carbon::parse($activity->updated_at)->diffForHumans()
                ];
            });

        return $recentActivities;
    }

    /**
     * Get All Matters/Cases for Client
     * GET /api/matters
     */
    public function getAllMatters(Request $request)
    {
        try {
            $admin = $request->user();
            $clientId = $admin->id;

            // Get all active matters for the client (including sel_matter_id=1 as General Matter)
            $matters = DB::table('client_matters')
                ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                ->where('client_matters.client_id', $clientId)
                ->where('client_matters.matter_status', 1) // 1 for active matter
                ->orderBy('client_matters.updated_at', 'desc')
                ->select(
                    'client_matters.id',
                    'client_matters.sel_matter_id',
                    'client_matters.client_unique_matter_no',
                    'matters.title'
                )
                ->get()
                ->map(function ($matter) {
                    // If sel_matter_id is 1 or title is null, use "General Matter"
                    $matterName = 'General Matter';
                    if ($matter->sel_matter_id != 1 && !empty($matter->title)) {
                        $matterName = $matter->title;
                    }
                    
                    // Concatenate matter name with client_unique_matter_no if it exists
                    if (!empty($matter->client_unique_matter_no)) {
                        $matterName .= ' (' . $matter->client_unique_matter_no . ')';
                    }
                    
                    return [
                        'matter_id' => $matter->id,
                        'matter_name' => $matterName
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'matters' => $matters
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get All Matters API Error: ' . $e->getMessage(), [
                'user_id' => $admin->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch matters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper methods for status colors and formatting
     */
    private function getAppointmentStatusColor($status)
    {
        $colors = [
            'pending' => 'orange',
            'awaiting_confirmation' => 'orange',
            'confirmed' => 'blue',
            'cancelled' => 'red',
            'rescheduled' => 'purple',
            'completed' => 'green',
            'no_show' => 'red'
        ];
        return $colors[$status] ?? 'blue';
    }

    private function getTaskStatusColor($status)
    {
        $colors = [
            'pending' => 'orange',
            'in_progress' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            'on_hold' => 'gray'
        ];
        return $colors[$status] ?? 'blue';
    }

    private function getTaskPriority($status, $daysUntil)
    {
        if ($daysUntil <= 1) return 'high';
        if ($daysUntil <= 3) return 'medium';
        return 'low';
    }
}
