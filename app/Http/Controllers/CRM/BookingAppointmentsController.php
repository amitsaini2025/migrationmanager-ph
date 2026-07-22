<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\BookingAppointment;
use App\Models\AppointmentConsultant;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\AppointmentSyncLog;
use App\Models\ActivitiesLog;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;
use App\Support\BansalSchedulingServiceType;
use App\Support\StaffClientVisibility;
use App\Helpers\IconHelper;
use Yajra\DataTables\Facades\DataTables;

class BookingAppointmentsController extends Controller
{
    protected AppointmentSyncService $syncService;
    protected BansalApiClient $bansalApiClient;
    protected BansalAppointmentRecoveryService $recoveryService;

    public function __construct(
        AppointmentSyncService $syncService,
        BansalApiClient $bansalApiClient,
        BansalAppointmentRecoveryService $recoveryService
    ) {
        $this->middleware('auth:admin');
        $this->syncService = $syncService;
        $this->bansalApiClient = $bansalApiClient;
        $this->recoveryService = $recoveryService;
    }

    protected function assertBookingAppointmentAccess(BookingAppointment $appointment): void
    {
        StaffClientVisibility::abortUnlessMayAccessBookingAppointment($appointment);
    }

    /**
     * Display appointment list
     */
    public function index(Request $request)
    {
        $query = BookingAppointment::with(['client', 'consultant']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);
        
        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('consultant_id')) {
            $query->where('consultant_id', $request->consultant_id);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_datetime', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_datetime', '<=', $request->date_to);
        }
        
        // Search in Client Reference, Description, and Email
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchTermLower = strtolower($searchTerm);
            $query->where(function($q) use ($searchTerm, $searchTermLower) {
                // Search in enquiry_details
                $q->whereRaw('LOWER(enquiry_details) LIKE ?', ['%' . $searchTermLower . '%'])
                  // Search in appointment client email (includes guests without linked client)
                  ->orWhereRaw('LOWER(client_email) LIKE ?', ['%' . $searchTermLower . '%'])
                  // Search in client_unique_matter_no via ClientMatter
                  ->orWhereIn('client_id', function($subQuery) use ($searchTermLower) {
                      $subQuery->select('client_id')
                               ->from('client_matters')
                               ->whereRaw('LOWER(client_unique_matter_no) LIKE ?', ['%' . $searchTermLower . '%']);
                  })
                  // Search in admins.client_id column
                  ->orWhereIn('client_id', function($subQuery) use ($searchTermLower) {
                      $subQuery->select('id')
                               ->from('admins')
                               ->whereRaw('LOWER(client_id) LIKE ?', ['%' . $searchTermLower . '%']);
                  })
                  // Search in linked client profile email
                  ->orWhereIn('client_id', function($subQuery) use ($searchTermLower) {
                      $subQuery->select('id')
                               ->from('admins')
                               ->whereRaw('LOWER(email) LIKE ?', ['%' . $searchTermLower . '%']);
                  });
            });
        }
        
        // Paginate appointments ordered by ID (descending - newest first)
        // Preserve filter parameters in pagination links
        $appointments = $query->orderByDesc('id')
            ->paginate(20)
            ->appends($request->except('page'));

        // Map latest matter reference for each appointment client
        $clientMatterRefs = [];
        $clientIds = $appointments->pluck('client_id')->filter()->unique();

        if ($clientIds->isNotEmpty()) {
            $clientMatterRefs = ClientMatter::whereIn('client_id', $clientIds)
                ->select('client_id', 'client_unique_matter_no')
                ->orderByDesc('id')
                ->get()
                ->unique('client_id')
                ->pluck('client_unique_matter_no', 'client_id')
                ->toArray();
        }
        
        // List filter: all active consultants; labels use crm_display_label (e.g. Employer Sponsored Calendar, not personal names)
        $calendarOrder = ['paid' => 1, 'jrp' => 2, 'education' => 3, 'tourist' => 4, 'adelaide' => 5, 'adelaide_education' => 6, 'ajay' => 7, 'arun' => 8];
        $consultants = AppointmentConsultant::active()
            ->get()
            ->sortBy(fn ($c) => $calendarOrder[$c->calendar_type] ?? 99)
            ->values();
        
        $statsBase = BookingAppointment::query();
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($statsBase);

        // Calculate statistics (same visibility as list)
        $stats = [
            'pending' => (clone $statsBase)->where('status', 'pending')->where('is_paid', 1)->count(),
            'paid' => (clone $statsBase)->where('status', 'paid')->where('is_paid', 1)->count(),
            'confirmed' => (clone $statsBase)->where('status', 'confirmed')->count(),
            'today' => (clone $statsBase)->whereDate('appointment_datetime', today())->count(),
            'total' => (clone $statsBase)->count(),
        ];
        
        return view('crm.booking.appointments.index', compact('appointments', 'consultants', 'stats', 'clientMatterRefs'));
    }

    /**
     * Get appointments for DataTables
     */
    public function getAppointments(Request $request)
    {
        $query = BookingAppointment::with(['client', 'consultant']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);

        // Filter by calendar type (consultant type)
        if ($request->filled('type')) {
            $query->whereHas('consultant', function($q) use ($request) {
                $q->where('calendar_type', $request->type);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by consultant
        if ($request->filled('consultant_id')) {
            $query->where('consultant_id', $request->consultant_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_datetime', '<=', $request->date_to);
        }

        // Check if calendar format is requested
        if ($request->get('format') === 'calendar') {
            // Show today's appointments (all times) and future appointments
            // Filter out only past appointments (before today)
            // Use explicit timezone-aware comparison to ensure accuracy
            $currentDateTime = Carbon::now(config('app.timezone'));
            $startOfToday = Carbon::today(config('app.timezone'));
            
            // Show appointments from start of today onwards (includes all of today + future)
            $query->where('appointment_datetime', '>=', $startOfToday);
            
            $appointments = $query->get();
            
            // Debug logging to verify filter is working
            Log::info('Calendar API Request - Showing Today and Future Appointments', [
                'type' => $request->get('type'),
                'start' => $request->get('start'),
                'end' => $request->get('end'),
                'start_of_today_filter' => $startOfToday->toDateTimeString(),
                'current_datetime' => $currentDateTime->toDateTimeString(),
                'timezone' => $startOfToday->timezone->getName(),
                'appointments_count_after_filter' => $appointments->count(),
                'sample_appointment_dates' => $appointments->take(5)->map(function($apt) use ($startOfToday, $currentDateTime) {
                    return [
                        'id' => $apt->id,
                        'datetime' => $apt->appointment_datetime->toDateTimeString(),
                        'is_today' => $apt->appointment_datetime->isToday(),
                        'is_future' => $apt->appointment_datetime->gt($currentDateTime),
                        'days_from_now' => $apt->appointment_datetime->diffInDays($currentDateTime, false)
                    ];
                })->toArray()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $appointments->map(function ($appointment) {
                    $encodedClientId = $appointment->client_id 
                        ? base64_encode(convert_uuencode($appointment->client_id)) 
                        : null;
                    
                    return [
                        'id' => $appointment->id,
                        'client_id' => $appointment->client_id,
                        'client_id_encoded' => $encodedClientId,
                        'client_name' => $appointment->client_name,
                        'client_email' => $appointment->client_email,
                        'client_phone' => $appointment->client_phone,
                        'service_type' => $appointment->service_type,
                        'appointment_datetime' => $appointment->appointment_datetime->toIso8601String(),
                        'duration_minutes' => $appointment->duration_minutes,
                        'status' => $appointment->status,
                        'location' => $appointment->location,
                        'meeting_type' => $appointment->meeting_type,
                        'preferred_language' => $appointment->preferred_language ?? 'English',
                        'is_paid' => $appointment->is_paid,
                        'final_amount' => $appointment->final_amount ?? 0,
                        'consultant' => $appointment->consultant ? [
                            'id' => $appointment->consultant->id,
                            'name' => $appointment->consultant->name,
                            'crm_display_label' => $appointment->consultant->crm_display_label,
                            'calendar_type' => $appointment->consultant->calendar_type,
                        ] : null,
                    ];
                })
            ]);
        }

        // Default: Return DataTables format ordered by ID (descending - newest first)
        $query->orderByDesc('id');

        return DataTables::of($query)
            ->addColumn('client_info', function ($appointment) {
                if ($appointment->client_id) {
                    $clientLink = route('clients.detail', base64_encode(convert_uuencode($appointment->client_id)));
                    
                    return '<a href="' . $clientLink . '" target="_blank">' . 
                           '<strong>' . e($appointment->client_name) . '</strong><br>' .
                           '<small>' . e($appointment->client_email) . '</small>' .
                           '</a>';
                }
                
                return '<strong>' . e($appointment->client_name) . '</strong><br>' .
                       '<small>' . e($appointment->client_email) . '</small>';
            })
            ->addColumn('appointment_info', function ($appointment) {
                return '<strong>' . $appointment->appointment_datetime->format('d/m/Y') . '</strong><br>' .
                       '<small>' . ($appointment->timeslot_full ?? $appointment->appointment_datetime->format('h:i A')) . '</small>';
            })
            ->addColumn('consultant_info', function ($appointment) {
                return $appointment->consultant 
                    ? '<span class="badge badge-info">' . e($appointment->consultant->crm_display_label) . '</span>'
                    : '<span class="badge badge-secondary">Unassigned</span>';
            })
            ->addColumn('status_badge', function ($appointment) {
                $color = $appointment->status_badge;
                $label = ucfirst(str_replace('_', ' ', $appointment->status));
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('payment_info', function ($appointment) {
                if ($appointment->is_paid) {
                    return '<span class="badge badge-success">Paid</span><br>' .
                           '<small>$' . number_format($appointment->final_amount, 2) . '</small>';
                }
                return '<span class="badge badge-secondary">Free</span>';
            })
            ->addColumn('actions', function ($appointment) {
                return '<a href="' . route('booking.appointments.show', $appointment->id) . '" class="btn btn-sm btn-primary">' .
                       IconHelper::fromLegacy('fas fa-eye') . ' View' .
                       '</a>';
            })
            ->rawColumns(['client_info', 'appointment_info', 'consultant_info', 'status_badge', 'payment_info', 'actions'])
            ->make(true);
    }

    /**
     * Show appointment detail
     */
    public function show($id)
    {
        $appointment = BookingAppointment::with(['client', 'consultant', 'assignedBy'])->findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);
        $consultants = AppointmentConsultant::active()->shownInFilter()->get();
        $latestClientMatter = null;

        if ($appointment->client_id) {
            $latestClientMatter = ClientMatter::where('client_id', $appointment->client_id)
                ->orderByDesc('id')
                ->first();
        }
        
        return view('crm.booking.appointments.show', compact('appointment', 'consultants', 'latestClientMatter'));
    }

    /**
     * Show edit appointment form (date & time only).
     */
    public function edit($id)
    {
        $appointment = BookingAppointment::with(['client', 'consultant'])->findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);
        return view('crm.booking.appointments.edit', compact('appointment'));
    }

    /**
     * Calendar view by type
     */
    public function calendar($type)
    {
        $validTypes = ['paid', 'jrp', 'education', 'tourist', 'adelaide', 'adelaide_education', 'ajay', 'arun'];
        
        if (!in_array($type, $validTypes)) {
            abort(404);
        }

        $appointmentsQuery = BookingAppointment::with(['client', 'consultant'])
            ->where(function ($query) use ($type) {
                $query->whereHas('consultant', function ($q) use ($type) {
                    $q->where('calendar_type', $type);
                })->orWhereNull('consultant_id');
            })
            ->where('appointment_datetime', '>', Carbon::now(config('app.timezone')))
            ->orderBy('appointment_datetime');
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($appointmentsQuery);
        $appointments = $appointmentsQuery->get();

        $calendarTitle = match($type) {
            'paid' => 'Employer Sponsored Calendar',
            'jrp' => 'JRP/Skill Assessment',
            'education' => 'Education/Student Visa',
            'tourist' => 'Tourist Visa',
            'adelaide' => 'Adelaide Office',
            'adelaide_education' => 'Adelaide Education',
            'ajay' => 'Ajay Calendar',
            'arun' => 'Arun Calendar',
            default => ucfirst($type)
        };

        $calendarStatsBase = function () use ($type) {
            $q = BookingAppointment::query()->whereHas('consultant', function ($q2) use ($type) {
                $q2->where('calendar_type', $type);
            });
            StaffClientVisibility::restrictBookingAppointmentEloquentQuery($q);

            return $q;
        };

        // Calculate statistics for this calendar type (same visibility as calendar events)
        $stats = [
            'this_month' => (clone $calendarStatsBase())->whereMonth('appointment_datetime', now()->month)->count(),
            'today' => (clone $calendarStatsBase())->whereDate('appointment_datetime', today())->count(),
            'upcoming' => (clone $calendarStatsBase())->where('appointment_datetime', '>', now())->count(),
            'pending' => (clone $calendarStatsBase())->where('status', 'pending')->where('is_paid', 1)->count(),
            'paid' => (clone $calendarStatsBase())->where('status', 'paid')->where('is_paid', 1)->count(),
            'no_show' => (clone $calendarStatsBase())->where('status', 'no_show')->count(),
        ];

        // Get active consultants for transfer dropdown (including Ajay calendar for transfers)
        // Use distinct() to ensure no duplicates
        $consultants = AppointmentConsultant::active()->shownInFilter()->distinct()->get();

        // Use FullCalendar v6 version
        return view('crm.booking.appointments.calendar-v6', compact('type', 'appointments', 'calendarTitle', 'stats', 'consultants'));
    }

    /**
     * Update appointment status
     */
    public function updateStatus(Request $request, $id)
    {
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $request->validate([
            'status' => 'required|in:pending,paid,confirmed,completed,cancelled,no_show,rescheduled',
            'cancellation_reason' => 'required_if:status,cancelled|nullable|string'
        ]);

        $oldStatus = $appointment->status;
        $appointment->status = $request->status;

        // Set timestamp based on status
        switch ($request->status) {
            case 'confirmed':
                $appointment->confirmed_at = now();
                break;
            case 'completed':
                $appointment->completed_at = now();
                break;
            case 'cancelled':
                $appointment->cancelled_at = now();
                // Cancellation reason is now required when status is cancelled
                $appointment->cancellation_reason = $request->cancellation_reason ?? null;
                break;
        }

        $appointment->save();

        $syncError = null;
        $shouldSyncStatus = in_array($request->status, ['cancelled', 'completed', 'confirmed']);

        if ($shouldSyncStatus) {
            if ($appointment->bansal_appointment_id) {
                $result = $this->recoveryService->syncStatus(
                    $appointment,
                    $request->status,
                    $request->status === 'cancelled' ? $request->cancellation_reason : null
                );

                if ($result['synced']) {
                    if ($result['bansal_appointment_id'] !== null) {
                        $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
                    }

                    $appointment->forceFill([
                        'last_synced_at' => now(),
                        'sync_status' => 'synced',
                        'sync_error' => null,
                    ])->save();
                } else {
                    $syncError = $result['error'];

                    Log::error('Failed to sync appointment status with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $appointment->bansal_appointment_id,
                        'status' => $request->status,
                        'error' => $syncError,
                    ]);

                    $appointment->forceFill([
                        'sync_status' => 'error',
                        'sync_error' => $syncError,
                    ])->save();
                }
            } else {
                Log::warning('Skipping Bansal sync because appointment is missing bansal_appointment_id', [
                    'appointment_id' => $appointment->id,
                    'status' => $request->status,
                ]);
                $syncError = 'Missing Bansal appointment identifier.';
            }
        }

        // Log activity using existing codebase pattern (only if client exists)
        if ($appointment->client_id) {
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $appointment->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->subject = 'Booking appointment status updated';
            $activityLog->description = '<p><strong>Status changed:</strong> ' . ucfirst($oldStatus) . ' → ' . ucfirst($request->status) . '</p>' .
                                       ($request->cancellation_reason ? '<p><strong>Reason:</strong> ' . e($request->cancellation_reason) . '</p>' : '');
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();
        }

        // Send cancellation confirmation email to client if requested
        if ($request->status === 'cancelled' && $request->boolean('send_cancellation_confirmation')) {
            try {
                $notificationService = app(\App\Services\BansalAppointmentSync\NotificationService::class);
                $notificationService->sendCancellationConfirmationEmail(
                    $appointment->fresh(),
                    $request->cancellation_reason
                );
            } catch (Exception $e) {
                Log::error('Failed to send appointment cancellation confirmation email', [
                    'appointment_id' => $appointment->id,
                    'client_email' => $appointment->client_email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $message = $syncError
            ? 'Status updated locally. Sync with website failed: ' . $syncError
            : 'Status updated successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'sync_error' => $syncError
        ]);
    }

    /**
     * Update consultant assignment
     */
    public function updateConsultant(Request $request, $id)
    {
        try {
            $appointment = BookingAppointment::findOrFail($id);
            $this->assertBookingAppointmentAccess($appointment);

            $request->validate([
                'consultant_id' => 'required|exists:appointment_consultants,id',
                'appointment_date_melbourne' => 'nullable|date_format:Y-m-d',
            ]);

            if ((int) $request->consultant_id === (int) ($appointment->consultant_id ?? 0)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Consultant assigned successfully',
                ]);
            }

            $newConsultant = AppointmentConsultant::find($request->consultant_id);
            $dbYmd = $appointment->appointment_datetime
                ? $appointment->appointment_datetime->copy()->timezone('Australia/Melbourne')->format('Y-m-d')
                : null;
            $modalYmd = $request->input('appointment_date_melbourne');
            if (! is_string($modalYmd) || $modalYmd === '') {
                $modalYmd = null;
            }
            if ($newConsultant && in_array($newConsultant->calendar_type, ['ajay', 'arun'], true)) {
                if ($this->isAjayArunBlockedForMelbourneDate($dbYmd, $newConsultant->calendar_type)
                    || $this->isAjayArunBlockedForMelbourneDate($modalYmd, $newConsultant->calendar_type)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You cannot assign this appointment to Ajay Calendar or Arun Calendar from 23 Apr 2026 to 3 May 2026. These consultants are not available for appointment dates in this period.',
                    ], 422);
                }
            }

            $oldConsultantId = $appointment->consultant_id;
            $appointment->consultant_id = $request->consultant_id;
            
            // Only set assigned_by_admin_id if user is authenticated and exists
            $adminId = Auth::id();
            if ($adminId) {
                // Verify admin exists before assigning (prevents FK constraint violation)
                $adminExists = Admin::where('id', $adminId)->exists();
                if ($adminExists) {
                    $appointment->assigned_by_admin_id = $adminId;
                }
                // If admin doesn't exist, leave it as null (column is nullable)
            }
            // If Auth::id() is null, leave assigned_by_admin_id as null (column is nullable)
            
            $appointment->save();

            // Log activity using existing codebase pattern (only if client exists)
            if ($appointment->client_id) {
                $consultant = AppointmentConsultant::find($request->consultant_id);
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $appointment->client_id;
                $activityLog->created_by = Auth::id();
                $activityLog->subject = 'Booking appointment consultant reassigned';
                $activityLog->description = '<p><strong>Consultant assigned:</strong> ' . ($consultant ? e($consultant->crm_display_label) : 'N/A') . '</p>';
                $activityLog->task_status = 0;
                $activityLog->pin = 0;
                $activityLog->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Consultant assigned successfully'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return JSON for validation errors
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Return JSON for not found errors
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
            
        } catch (\Illuminate\Database\QueryException $e) {
            // Log database-specific errors with more details
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            Log::error('Database error updating consultant', [
                'appointment_id' => $id,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'sql_state' => $e->errorInfo[0] ?? null,
                'sql_code' => $e->errorInfo[1] ?? null,
                'sql_message' => $e->errorInfo[2] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check for specific database errors
            if (strpos($errorMessage, 'assigned_by_admin_id') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: assigned_by_admin_id column issue. Please check database schema.'
                ], 500);
            }
            
            if (strpos($errorMessage, 'foreign key constraint') !== false || strpos($errorMessage, 'a foreign key constraint fails') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Foreign key constraint violation. The admin user may not exist in the system.'
                ], 500);
            }
            
            if (strpos($errorMessage, "doesn't exist") !== false || strpos($errorMessage, 'Unknown column') !== false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database column missing. Please run migrations on production server.'
                ], 500);
            }
            
            // Return JSON for any other database errors
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred while updating consultant. Please check server logs.'
            ], 500);
            
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error updating consultant', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return JSON for any other errors
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating consultant. Please try again.'
            ], 500);
        }
    }

    /**
     * Update meeting type
     */
    public function updateMeetingType(Request $request, $id)
    {
        try {
            $appointment = BookingAppointment::findOrFail($id);
            $this->assertBookingAppointmentAccess($appointment);

            $request->validate([
                'meeting_type' => 'required|in:in_person,phone,video'
            ]);

            // Validate: Video meeting type is only allowed for paid appointments
            if ($request->meeting_type === 'video' && !$appointment->is_paid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video meeting type is only available for paid appointments.'
                ], 422);
            }

            $oldMeetingType = $appointment->meeting_type;
            $appointment->meeting_type = $request->meeting_type;
            $appointment->save();

            // Log activity using existing codebase pattern (only if client exists)
            if ($appointment->client_id) {
                $oldDisplay = ucfirst(str_replace('_', ' ', $oldMeetingType));
                $newDisplay = ucfirst(str_replace('_', ' ', $request->meeting_type));
                
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $appointment->client_id;
                $activityLog->created_by = Auth::id();
                $activityLog->subject = 'Booking appointment meeting type updated';
                $activityLog->description = '<p><strong>Meeting type changed:</strong> ' . $oldDisplay . ' → ' . $newDisplay . '</p>';
                $activityLog->task_status = 0;
                $activityLog->pin = 0;
                $activityLog->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Meeting type updated successfully',
                'meeting_type' => $appointment->meeting_type
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error updating meeting type', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating meeting type. Please try again.'
            ], 500);
        }
    }

    /**
     * Update appointment date and time.
     */
    public function update(Request $request, $id)
    {  
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $this->normalizePreferredLanguageRequest($request);

        $request->validate([
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i',
            'meeting_type' => 'required|in:in_person,phone,video',
            'preferred_language' => 'required|string|in:English,Hindi,Punjabi',
        ]);

        // Validate: Video meeting type is only allowed for paid appointments
        if ($request->meeting_type === 'video' && !$appointment->is_paid) {
            return $this->handleUpdateError($request, 'Video meeting type is only available for paid appointments.', 422);
        }

        $oldDatetime = $appointment->appointment_datetime;
        $oldMeetingType = $appointment->meeting_type;
        $oldPreferredLanguage = $appointment->preferred_language ?? 'English';
        
        try {
            $newDatetime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $request->appointment_date . ' ' . $request->appointment_time,
                config('app.timezone')
            );
        } catch (Exception $e) {
            return $this->handleUpdateError($request, 'Invalid date or time provided.', 422, $e->getMessage());
        }

        // Check if anything has changed
        $datetimeChanged = !$oldDatetime || !$oldDatetime->equalTo($newDatetime);
        $meetingTypeChanged = $oldMeetingType !== $request->meeting_type;
        $preferredLanguageChanged = $oldPreferredLanguage !== $request->preferred_language;
        
        if (!$datetimeChanged && !$meetingTypeChanged && !$preferredLanguageChanged) {
            $message = 'No changes detected. Appointment details remain unchanged.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()
                ->back()
                ->with('success', $message);
        }

        // Slot conflict check: block if another active appointment for the same consultant is already at the new datetime
        if ($datetimeChanged) {
            $slotTaken = BookingAppointment::where('consultant_id', $appointment->consultant_id)
                ->where('appointment_datetime', $newDatetime)
                ->where('id', '!=', $appointment->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->exists();

            if ($slotTaken) {
                $conflictMsg = 'The time slot ' . $newDatetime->format('d M Y \a\t h:i A') . ' is already booked for this consultant. Please select a different date or time.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $conflictMsg,
                    ], 409);
                }

                return redirect()->back()->withErrors(['appointment_datetime' => $conflictMsg])->withInput();
            }
        }

        // Update appointment fields in local database FIRST (always update locally)
        if ($datetimeChanged) {
            $appointment->appointment_datetime = $newDatetime;
            $appointment->timeslot_full = $newDatetime->format('h:i A');
        }
        
        if ($meetingTypeChanged) {
            $appointment->meeting_type = $request->meeting_type;
        }
        
        if ($preferredLanguageChanged) {
            $appointment->preferred_language = $request->preferred_language;
        }

        // Try to sync with Bansal API if any field changed AND bansal_appointment_id exists
        $syncError = null;
        $apiSynced = false;
        $newBansalAppointmentId = null;

        if (($datetimeChanged || $meetingTypeChanged || $preferredLanguageChanged) && !empty($appointment->bansal_appointment_id)) {
            $apiDate = $datetimeChanged ? $request->appointment_date : $appointment->appointment_datetime->format('Y-m-d');
            $apiTime = $datetimeChanged ? $request->appointment_time : $appointment->appointment_datetime->format('H:i');
            $apiMeetingType = $meetingTypeChanged ? $request->meeting_type : ($appointment->meeting_type ?? 'in_person');
            $apiPreferredLanguage = $preferredLanguageChanged ? $request->preferred_language : ($appointment->preferred_language ?? 'English');

            $result = $this->recoveryService->syncReschedule(
                $appointment,
                $apiDate,
                $apiTime,
                $apiMeetingType,
                $apiPreferredLanguage
            );

            $apiSynced = $result['synced'];
            $syncError = $result['error'];
            $newBansalAppointmentId = $result['created_new'] ? $result['bansal_appointment_id'] : null;

            if ($result['synced']) {
                if ($result['bansal_appointment_id'] !== null) {
                    $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
                }
                $appointment->last_synced_at = now();
                $appointment->sync_status = 'synced';
                $appointment->sync_error = null;
            } else {
                $appointment->sync_status = 'error';
                $appointment->sync_error = $syncError;
            }
        } else {
            // No bansal_appointment_id, so just update locally
            if ($datetimeChanged || $meetingTypeChanged || $preferredLanguageChanged) {
                $appointment->sync_status = 'new';
                $appointment->sync_error = null;
            }
        }
        
        $appointment->save();

        // Log activity if client exists
        if ($appointment->client_id) {
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $appointment->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->task_status = 0;
            $activityLog->pin = 0;

            $descriptionParts = [];
            
            if ($datetimeChanged) {
                $from = $oldDatetime ? $oldDatetime->format('d M Y, h:i A') : 'N/A';
                $to = $newDatetime->format('d M Y, h:i A');
                $descriptionParts[] = sprintf(
                    '<p><strong>Appointment rescheduled:</strong> %s → %s</p>',
                    e($from),
                    e($to)
                );
            }
            
            if ($meetingTypeChanged) {
                $oldDisplay = ucfirst(str_replace('_', ' ', $oldMeetingType));
                $newDisplay = ucfirst(str_replace('_', ' ', $request->meeting_type));
                $descriptionParts[] = sprintf(
                    '<p><strong>Meeting type changed:</strong> %s → %s</p>',
                    e($oldDisplay),
                    e($newDisplay)
                );
            }
            
            if ($preferredLanguageChanged) {
                $descriptionParts[] = sprintf(
                    '<p><strong>Preferred language changed:</strong> %s → %s</p>',
                    e($oldPreferredLanguage),
                    e($request->preferred_language)
                );
            }

            if (!empty($descriptionParts)) {
                $activityLog->subject = 'Booking appointment updated';
                $activityLog->description = implode('', $descriptionParts);
                $activityLog->save();
            }
        }

        // Send reschedule confirmation email to client when datetime changed
        if ($datetimeChanged && !empty($appointment->client_email)) {
            try {
                $notificationService = app(\App\Services\BansalAppointmentSync\NotificationService::class);
                $notificationService->sendRescheduleEmail($appointment, $oldDatetime);
            } catch (\Exception $e) {
                Log::warning('Failed to send reschedule email', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Build success message
        $messageParts = [];
        if ($datetimeChanged) {
            $messageParts[] = 'date and time';
        }
        if ($meetingTypeChanged) {
            $messageParts[] = 'meeting type';
        }
        if ($preferredLanguageChanged) {
            $messageParts[] = 'preferred language';
        }
        
        $message = 'Appointment ' . implode(', ', $messageParts) . ' updated successfully.';
        
        // Add warning if API sync failed
        if ($syncError) {
            $message .= ' However, sync with website failed: ' . $syncError;
        } elseif ($newBansalAppointmentId) {
            $message .= ' Note: A new appointment was created on the website (previous appointment ID was invalid).';
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'appointment_datetime' => $appointment->appointment_datetime->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('booking.appointments.show', $appointment->id)
            ->with('success', $message);
    }

    /**
     * Create appointment via Bansal API when update fails due to invalid appointment ID
     */
    protected function createAppointmentViaApi(BookingAppointment $appointment, Request $request, bool $datetimeChanged, bool $meetingTypeChanged, bool $preferredLanguageChanged): ?int
    {
        try {
            // Use updated values from appointment object (already updated locally but not saved yet)
            // Map meeting_type from database format to API format
            $currentMeetingType = $meetingTypeChanged ? $request->meeting_type : ($appointment->meeting_type ?? 'in_person');
            $meetingTypeForApi = match($currentMeetingType) {
                'video' => 'video-call',
                'in_person' => 'in-person',
                'phone' => 'phone',
                default => 'in-person'
            };
            
            // Get updated datetime
            $appointmentDatetime = $datetimeChanged ? $appointment->appointment_datetime : $appointment->appointment_datetime;
            
            // Get updated preferred language
            $currentPreferredLanguage = $preferredLanguageChanged ? $request->preferred_language : ($appointment->preferred_language ?? 'English');
            
            // Determine specific_service from enquiry_type or service_type
            $specificService = $this->determineSpecificService($appointment);
            
            // Build payload for createAppointment API
            $payload = [
                'full_name' => $appointment->client_name,
                'email' => $appointment->client_email,
                'phone' => $appointment->client_phone ?? '',
                'appointment_date' => $appointmentDatetime->format('Y-m-d'),
                'appointment_time' => $appointmentDatetime->format('H:i'),
                'appointment_datetime' => $appointmentDatetime->format('Y-m-d H:i:s'),
                'duration_minutes' => $appointment->duration_minutes ?? 15,
                'location' => $appointment->location ?? 'melbourne',
                'meeting_type' => $meetingTypeForApi,
                'preferred_language' => $currentPreferredLanguage,
                'specific_service' => $specificService,
                'enquiry_type' => BansalSchedulingServiceType::bansalEnquiryTypeForApi(
                    $appointment->noe_id ?? 0,
                    $appointment->location ?? 'melbourne',
                    $appointment->enquiry_type ?? 'pr_complex'
                ),
                'service_type' => BansalSchedulingServiceType::bansalServiceTypeForApi(
                    $appointment->noe_id ?? 0,
                    $appointment->service_type ?? 'Permanent Residency'
                ),
                'enquiry_details' => $appointment->enquiry_details ?? '',
                'is_paid' => $appointment->is_paid ?? false,
                'amount' => $appointment->amount ?? 0,
                'final_amount' => $appointment->final_amount ?? 0,
                'payment_status' => $appointment->payment_status ?? ($appointment->is_paid ? 'pending' : null),
                'slot_overwrite' => 0,
            ];
            
            $apiResponse = $this->bansalApiClient->createAppointment($payload);
            
            if ($apiResponse['success'] ?? false) {
                // Extract new bansal_appointment_id from response
                if (isset($apiResponse['data']['id'])) {
                    return (int) $apiResponse['data']['id'];
                } elseif (isset($apiResponse['data']['appointment_id'])) {
                    return (int) $apiResponse['data']['appointment_id'];
                } elseif (isset($apiResponse['appointment_id'])) {
                    return (int) $apiResponse['appointment_id'];
                }
            }
            
            Log::warning('Bansal API createAppointment returned success but no appointment ID', [
                'appointment_id' => $appointment->id,
                'response' => $apiResponse,
            ]);
            
            return null;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Log as WARNING instead of ERROR since this is expected in some cases
            // (e.g., when time slot is not available)
            Log::warning('Failed to create appointment via API', [
                'appointment_id' => $appointment->id,
                'error' => $errorMessage,
                'appointment_date' => $appointment->appointment_datetime->format('Y-m-d'),
                'appointment_time' => $appointment->appointment_datetime->format('H:i'),
            ]);
            
            // Re-throw the exception so it can be handled by the caller
            throw $e;
        }
    }
    
    /**
     * Determine specific_service for API based on appointment data
     */
    protected function determineSpecificService(BookingAppointment $appointment): string
    {
        // If enquiry_type exists, try to map it
        if ($appointment->enquiry_type) {
            $enquiryType = strtolower($appointment->enquiry_type);
            
            // Map common enquiry types to specific_service
            if (strpos($enquiryType, 'overseas') !== false || $enquiryType === 'international') {
                return 'overseas-enquiry';
            } elseif ($appointment->is_paid) {
                return 'paid-consultation';
            } else {
                return 'consultation';
            }
        }
        
        // Fallback based on is_paid
        if ($appointment->is_paid) {
            return 'paid-consultation';
        }
        
        return 'consultation';
    }

    protected function handleUpdateError(Request $request, string $message, int $status = 422, $context = null)
    {
        if ($context) {
            Log::warning('Booking appointment update failed', [
                'message' => $message,
                'context' => $context,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', $message);
    }

    /**
     * Map common casings (e.g. from sync/API) to validated canonical labels.
     * Unknown values are left unchanged so validation still rejects them.
     */
    protected function normalizePreferredLanguageRequest(Request $request): void
    {
        $raw = $request->input('preferred_language');
        if (! is_string($raw)) {
            return;
        }

        $key = strtolower(trim($raw));
        $canonical = [
            'english' => 'English',
            'hindi' => 'Hindi',
            'punjabi' => 'Punjabi',
        ];

        if (isset($canonical[$key])) {
            $request->merge(['preferred_language' => $canonical[$key]]);
        }
    }

    /**
     * Add admin note
     */
    public function addNote(Request $request, $id)
    {
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $request->validate([
            'note' => 'required|string|max:2000'
        ]);

        $timestamp = now()->format('Y-m-d H:i');
        $adminName = Auth::user()->first_name . ' ' . Auth::user()->last_name;
        $newNote = "[{$timestamp} - {$adminName}]\n" . $request->note;

        $appointment->admin_notes = $appointment->admin_notes 
            ? $appointment->admin_notes . "\n\n" . $newNote
            : $newNote;
        
        $appointment->save();

        // Log activity using existing codebase pattern
        if ($appointment->client_id) {
            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $appointment->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->subject = 'Note added to booking appointment';
            $activityLog->description = '<p>' . e($request->note) . '</p>';
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully',
            'notes' => $appointment->admin_notes
        ]);
    }

    /**
     * Sync dashboard
     */
    public function syncDashboard()
    {
        // Get sync logs with pagination
        $syncLogs = AppointmentSyncLog::orderBy('created_at', 'desc')->paginate(20);
        
        // Get last successful sync
        $lastSync = AppointmentSyncLog::where('status', 'success')
            ->latest('created_at')
            ->first();
        
        // Determine system status
        $lastLog = AppointmentSyncLog::latest('created_at')->first();
        $systemStatus = [
            'status' => 'success',
            'message' => 'All systems operational'
        ];
        
        if ($lastLog) {
            if ($lastLog->status === 'failed') {
                $systemStatus = [
                    'status' => 'error',
                    'message' => 'Last sync failed: ' . ($lastLog->error_message ?? 'Unknown error')
                ];
            } elseif ($lastLog->status === 'running') {
                $systemStatus = [
                    'status' => 'running',
                    'message' => 'Sync currently in progress'
                ];
            }
        }
        
        // Calculate next sync time (every 10 minutes)
        $nextSync = $lastSync ? $lastSync->created_at->addMinutes(10)->diffForHumans() : 'Within 10 minutes';
        
        // Calculate statistics
        $totalSyncs = AppointmentSyncLog::where('status', 'success')->count();
        $failedSyncs = AppointmentSyncLog::where('status', 'failed')->count();
        $totalAttempts = $totalSyncs + $failedSyncs;
        $successRate = $totalAttempts > 0 ? round(($totalSyncs / $totalAttempts) * 100) : 100;
        
        $syncStatsApptBase = BookingAppointment::query();
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($syncStatsApptBase);

        $stats = [
            'total_synced' => (clone $syncStatsApptBase)->count(),
            'today' => AppointmentSyncLog::whereDate('created_at', today())->count(),
            'failed' => $failedSyncs,
            'success_rate' => $successRate,
        ];

        return view('crm.booking.sync.dashboard', compact('syncLogs', 'systemStatus', 'lastSync', 'nextSync', 'stats'));
    }

    /**
     * Manual sync trigger (admin only)
     */
    public function manualSync(Request $request)
    {
        // Check authorization using Gate
        if (!Gate::allows('trigger-manual-sync')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        try {
            $minutes = $request->input('minutes', 60);
            $stats = $this->syncService->syncRecentAppointments($minutes);

            // Log activity - no need to log this as it's already logged in AppointmentSyncLog

            return response()->json([
                'success' => true,
                'message' => 'Sync completed successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointment details as JSON (for modal/AJAX)
     */
    public function getAppointmentJson($id)
    {
        $appointment = BookingAppointment::with(['client', 'consultant', 'assignedBy'])->findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'client_id' => $appointment->client_id,
                'client_name' => $appointment->client_name,
                'client_email' => $appointment->client_email,
                'client_phone' => $appointment->client_phone,
                'appointment_datetime' => $appointment->appointment_datetime,
                'formatted_date' => $appointment->formatted_date,
                'formatted_time' => $appointment->formatted_time,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
                'location_display' => $appointment->location_display,
                'full_address' => $appointment->full_address,
                'service_type' => $appointment->service_type,
                'enquiry_type' => $appointment->enquiry_type,
                'enquiry_details' => $appointment->enquiry_details,
                'meeting_type' => $appointment->meeting_type,
                'status' => $appointment->status,
                'status_badge' => $appointment->status_badge,
                'is_paid' => $appointment->is_paid,
                'final_amount' => $appointment->final_amount,
                'payment_status' => $appointment->payment_status,
                'payment_method' => $appointment->payment_method,
                'paid_at' => $appointment->paid_at?->format('d/m/Y h:i A'),
                'promo_code' => $appointment->promo_code,
                'admin_notes' => $appointment->admin_notes,
                'consultant' => $appointment->consultant ? [
                    'id' => $appointment->consultant->id,
                    'name' => $appointment->consultant->name,
                    'crm_display_label' => $appointment->consultant->crm_display_label,
                    'calendar_type' => $appointment->consultant->calendar_type,
                ] : null,
                'synced_from_bansal_at' => $appointment->synced_from_bansal_at?->format('d/m/Y h:i A'),
                'updated_at' => $appointment->updated_at?->format('d/m/Y h:i A'),
            ]
        ]);
    }

    /**
     * Send reminder manually
     */
    public function sendReminder(Request $request, $id)
    {
        $appointment = BookingAppointment::findOrFail($id);
        $this->assertBookingAppointmentAccess($appointment);

        $notificationService = app(\App\Services\BansalAppointmentSync\NotificationService::class);
        
        $validated = $request->validate([
            'type' => 'nullable|in:email,sms,both',
        ]);
        $type = $validated['type'] ?? 'email';

        $results = [
            'email' => null,
            'sms' => null
        ];

        if (in_array($type, ['email', 'both'], true)) {
            $results['email'] = $notificationService->sendAppointmentReminderEmail($appointment);
        }

        if (in_array($type, ['sms', 'both'], true)) {
            $results['sms'] = $notificationService->sendReminderSms($appointment);
        }

        $success = ($results['email'] !== false && $results['sms'] !== false);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Reminder sent successfully' : 'Failed to send reminder',
            'results' => $results
        ]);
    }

    /**
     * Get sync statistics
     */
    public function syncStats()
    {
        $appt24 = BookingAppointment::query()->where('created_at', '>=', now()->subDay());
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($appt24);

        $stats = [
            'today' => [
                'syncs' => AppointmentSyncLog::today()->count(),
                'successful' => AppointmentSyncLog::today()->success()->count(),
                'failed' => AppointmentSyncLog::today()->failed()->count(),
                'appointments_synced' => AppointmentSyncLog::today()->sum('appointments_new'),
            ],
            'last_24h' => [
                'appointments' => (clone $appt24)->count(),
                'pending' => (clone $appt24)->where('status', 'pending')->count(),
            ],
            'last_sync' => AppointmentSyncLog::latest('started_at')->first(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Export appointments to CSV
     */
    public function export(Request $request)
    {
        $query = BookingAppointment::with(['client', 'consultant']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);

        // Apply same filters as index
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('consultant_id')) {
            $query->where('consultant_id', $request->consultant_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('appointment_datetime', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('appointment_datetime', '<=', $request->date_to);
        }

        $appointments = $query->orderBy('appointment_datetime', 'desc')->get();

        $filename = 'booking_appointments_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($appointments) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'ID',
                'Bansal ID',
                'Client Name',
                'Email',
                'Phone',
                'Appointment Date',
                'Time',
                'Location',
                'Service Type',
                'Consultant',
                'Status',
                'Payment Status',
                'Amount',
                'Synced At'
            ]);

            // Data
            foreach ($appointments as $apt) {
                fputcsv($file, [
                    $apt->id,
                    $apt->bansal_appointment_id,
                    $apt->client_name,
                    $apt->client_email,
                    $apt->client_phone,
                    $apt->appointment_datetime->format('d/m/Y'),
                    $apt->timeslot_full ?? $apt->appointment_datetime->format('h:i A'),
                    $apt->location,
                    $apt->service_type,
                    $apt->consultant?->name ?? 'Unassigned',
                    $apt->status,
                    $apt->is_paid ? 'Paid' : 'Free',
                    $apt->is_paid ? $apt->final_amount : '0.00',
                    $apt->synced_from_bansal_at?->format('d/m/Y h:i A')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'appointment_ids' => 'required|array',
            'appointment_ids.*' => 'exists:booking_appointments,id',
            'status' => 'required|in:pending,confirmed,completed,cancelled,no_show'
        ]);

        $updated = 0;
        
        foreach ($request->appointment_ids as $id) {
            $appointment = BookingAppointment::find($id);
            if ($appointment) {
                $this->assertBookingAppointmentAccess($appointment);
                $appointment->status = $request->status;
                
                if ($request->status === 'confirmed') {
                    $appointment->confirmed_at = now();
                } elseif ($request->status === 'completed') {
                    $appointment->completed_at = now();
                } elseif ($request->status === 'cancelled') {
                    $appointment->cancelled_at = now();
                }
                
                $appointment->save();
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Updated {$updated} appointments"
        ]);
    }

    /**
     * Ajay/Arun calendars are not assignable for Melbourne-local appointment dates in this inclusive window.
     *
     * @param  string|null  $melbourneYmd  Y-m-d in Australia/Melbourne (from DB or modal date field)
     */
    protected function isAjayArunBlockedForMelbourneDate(?string $melbourneYmd, ?string $calendarType): bool
    {
        if ($melbourneYmd === null || $melbourneYmd === '') {
            return false;
        }

        if (! in_array($calendarType, ['ajay', 'arun'], true)) {
            return false;
        }

        return $melbourneYmd >= '2026-04-23' && $melbourneYmd <= '2026-05-03';
    }
}

