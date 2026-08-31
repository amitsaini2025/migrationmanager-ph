<?php

namespace App\Http\Controllers\API;

use App\Mail\AppointmentDetailedConfirmation;
use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\AppointmentPayment;
use App\Models\BookingAppointment;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use App\Services\BansalAppointmentSync\ConsultantAssignmentService;
use App\Services\ClientReferenceService;
use App\Services\Payment\StripePaymentService;
use App\Support\BansalSchedulingServiceType;
use App\Support\BookingAppointmentStatus;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClientPortalAppointmentController extends BaseController
{
    /**
     * Get appointment variable lists
     *
     * Returns all appointment-related options including locations, meeting types,
     * preferred languages, services, and service types.
     *
     * @return JsonResponse
     */
    public function getAppointmentVariableLists(Request $request)
    {
        try {
            $result = [
                'location' => [
                    [
                        'id' => 1,
                        'name' => 'Adelaide Office',
                        'address' => 'Unit 5, 55 Gawler Pl',
                        'city' => 'Adelaide',
                        'state' => 'SA',
                        'postcode' => '5000',
                        'full_address' => 'Unit 5, 55 Gawler Pl Adelaide SA 5000',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Melbourne Office',
                        'address' => 'Level 8/278 Collins St',
                        'city' => 'Melbourne',
                        'state' => 'VIC',
                        'postcode' => '3000',
                        'full_address' => 'Level 8/278 Collins St Melbourne VIC 3000',
                    ],
                ],
                'meeting_type' => [
                    [
                        'id' => 1,
                        'name' => 'Phone Call',
                        'description' => 'Speak directly with our experts',
                        'icon' => 'phone',
                    ],
                    [
                        'id' => 2,
                        'name' => 'In Person',
                        'description' => 'Visit our office',
                        'icon' => 'building',
                    ],
                    [
                        'id' => 3,
                        'name' => 'Video Call',
                        'description' => 'Online consultation',
                        'note' => 'Available for paid appointments only',
                        'icon' => 'video',
                    ],
                ],
                'preferred_language' => [
                    [
                        'id' => 1,
                        'code' => 'en',
                        'name' => 'English',
                        'country_code' => 'AU',
                        'country_flag' => '🇦🇺',
                    ],
                    [
                        'id' => 2,
                        'code' => 'hi',
                        'name' => 'Hindi',
                        'country_code' => 'IN',
                        'country_flag' => '🇮🇳',
                    ],
                    [
                        'id' => 3,
                        'code' => 'pa',
                        'name' => 'Punjabi',
                        'country_code' => 'IN',
                        'country_flag' => '🇮🇳',
                    ],
                ],
                'select_your_service' => [
                    [
                        'id' => 1,
                        'name' => 'GSM Visas: 491, 190, 189, 191',
                    ],
                    [
                        'id' => 9,
                        'name' => 'EOI/ROI',
                    ],
                    [
                        'id' => 2,
                        'name' => 'TR: 485 visa',
                    ],
                    [
                        'id' => 10,
                        'name' => 'Employer Sponsored Visas: 494, 482, 186, DAMA',
                    ],
                    [
                        'id' => 3,
                        'name' => 'JRP/Skill Assessment',
                    ],
                    [
                        'id' => 4,
                        'name' => 'Tourist Visa',
                    ],
                    [
                        'id' => 5,
                        'name' => 'Education/Course Change/Student Visa/Student Dependent Visa (for education selection for Australian onshore clients only)',
                    ],
                    [
                        'id' => 6,
                        'name' => 'Complex matters: ART, Protection visa, Federal Case',
                    ],
                    [
                        'id' => 7,
                        'name' => 'Visa Cancellation/ NOICC/ Visa refusals',
                    ],
                    [
                        'id' => 11,
                        'name' => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
                    ],
                    [
                        'id' => 12,
                        'name' => 'Citizenship',
                    ],
                    [
                        'id' => 8,
                        'name' => 'Anyone who is outside Australia',
                    ],
                ],
                'service_type' => [
                    [
                        'id' => 1,
                        'name' => 'Free Consultation',
                        'price' => 0,
                        'price_display' => 'FREE',
                        'duration' => 15,
                        'duration_unit' => 'minutes',
                        'time_slots' => [
                            'start_time' => '10:45',
                            'end_time' => '16:00',
                            'time_format' => 'AM/PM',
                        ],
                        'availability' => [
                            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                            'time_slots' => '15-minute time slots',
                        ],
                        'description' => 'Perfect for initial inquiries: Quick assessment of your immigration situation, basic visa pathway guidance, and preliminary advice. Available for clients currently within Australia only. Includes initial case evaluation and next steps recommendation.',
                        'includes_video_call' => false,
                        'available_for_overseas' => false,
                    ],
                    [
                        'id' => 2,
                        'name' => 'Comprehensive Migration Advice',
                        'price' => 150,
                        'price_display' => '$150',
                        'duration' => 30,
                        'duration_unit' => 'minutes',
                        'time_slots' => [
                            'start_time' => '09:00',
                            'end_time' => '17:00',
                            'time_format' => 'AM/PM',
                        ],
                        'availability' => [
                            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                            'time_slots' => '30-minute time slots',
                        ],
                        'description' => 'In-depth professional consultation: Comprehensive case analysis, detailed migration strategy, complex visa applications, ART appeals, visa cancellations, protection visas, and personalized action plans. Suitable for overseas applicants and complex cases.',
                        'includes_video_call' => true,
                        'available_for_overseas' => true,
                    ],
                    [
                        'id' => 3,
                        'name' => 'Overseas Applicant Enquiry',
                        'price' => 150,
                        'price_display' => '$150',
                        'duration' => 30,
                        'duration_unit' => 'minutes',
                        'time_slots' => [
                            'start_time' => '09:00',
                            'end_time' => '17:00',
                            'time_format' => 'AM/PM',
                        ],
                        'availability' => [
                            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                            'time_slots' => '30-minute time slots',
                        ],
                        'description' => 'Specialized consultation for overseas applicants: For applicants currently outside Australia or inquiring on behalf of someone overseas. Includes detailed assessment and personalized migration strategy.',
                        'includes_video_call' => true,
                        'available_for_overseas' => true,
                    ],
                ],
            ];

            return $this->sendResponse($result, 'Appointment variable lists retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get appointment list for authenticated client
     *
     * Returns paginated list of appointments for the authenticated client.
     * Supports filtering by status, date range, location, and enquiry type.
     *
     * @return JsonResponse
     */
    public function getAppointmentList(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            $clientId = $user->id;

            // Build query for client's appointments
            $query = BookingAppointment::where('client_id', $clientId);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('appointment_datetime', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('appointment_datetime', '<=', $request->end_date);
            }

            if ($request->filled('location')) {
                $query->where('location', $request->location);
            }

            if ($request->filled('enquiry_type')) {
                $query->where('enquiry_type', $request->enquiry_type);
            }

            // Pagination
            $perPage = min((int) $request->input('per_page', 50), 100); // Max 100 per page
            $page = (int) $request->input('page', 1);

            // Order by appointment datetime (newest first)
            $query->orderByDesc('appointment_datetime');

            $appointments = $query->paginate($perPage, ['*'], 'page', $page);

            // Format response
            $formattedAppointments = $appointments->map(function ($appointment) {
                return $this->formatAppointmentData($appointment);
            });

            $result = [
                'data' => $formattedAppointments,
                'pagination' => [
                    'current_page' => $appointments->currentPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'last_page' => $appointments->lastPage(),
                    'from' => $appointments->firstItem(),
                    'to' => $appointments->lastItem(),
                ],
            ];

            return $this->sendResponse($result, 'Appointments retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Get Appointment List API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get single appointment by ID
     *
     * Returns a single appointment details for the authenticated client.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function getSingleAppointment(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            $clientId = $user->id;

            // Find appointment that belongs to the authenticated client
            $appointment = BookingAppointment::where('id', $id)
                ->where('client_id', $clientId)
                ->first();

            if (! $appointment) {
                return $this->sendError('Appointment not found', [], 404);
            }

            $result = $this->formatAppointmentData($appointment);

            return $this->sendResponse($result, 'Appointment retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Get Single Appointment API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'appointment_id' => $id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Format appointment data for API response
     *
     * @param  BookingAppointment  $appointment
     * @return array
     */
    private function formatAppointmentData($appointment)
    {
        // Map status to display format
        $statusDisplay = BookingAppointmentStatus::label((string) ($appointment->status ?? 'pending'));

        // Map enquiry type to display format
        $enquiryTypeDisplay = match ($appointment->enquiry_type) {
            'pr' => 'Permanent Residency',
            'tr' => 'Temporary Residency',
            'tourist' => 'Tourist Visa',
            'education' => 'Education/Student Visa',
            'pr_complex' => 'PR/Complex',
            'jrp' => 'JRP/Skill Assessment',
            'complex' => 'Complex matters',
            'cancellation' => 'Visa Cancellation/NOICC/Refusals',
            'international' => 'Outside Australia',
            'eoi' => 'EOI/ROI',
            'employer_sponsored' => 'Employer Sponsored',
            'visa_cancellation' => 'Visa Cancellation/NOICC/Refusals',
            'india_uk_canada_europe' => 'INDIA/UK/CANADA/EUROPE TO AUSTRALIA',
            'family_visas' => 'Family Visas',
            'citizenship' => 'Citizenship',
            default => ucfirst(str_replace('_', ' ', $appointment->enquiry_type ?? 'General'))
        };

        // Format meeting type
        $meetingTypeDisplay = match ($appointment->meeting_type) {
            'in_person' => 'In Person',
            'phone' => 'Phone Call',
            'video' => 'Video Call',
            default => ucfirst($appointment->meeting_type ?? 'In Person')
        };

        return [
            'id' => $appointment->id,
            'full_name' => $appointment->client_name,
            'email' => $appointment->client_email,
            'phone' => $appointment->client_phone,
            'location' => $appointment->location,
            'meeting_type' => $appointment->meeting_type,
            'meeting_type_display' => $meetingTypeDisplay,
            'preferred_language' => strtolower($appointment->preferred_language ?? 'english'),
            'enquiry_type' => $appointment->enquiry_type,
            'enquiry_type_display' => $enquiryTypeDisplay,
            'service_type' => $appointment->service_type,
            'specific_service' => $appointment->service_type ?? 'consultation',
            'enquiry_details' => $appointment->enquiry_details,
            'appointment_date' => $appointment->appointment_datetime ? $appointment->appointment_datetime->format('Y-m-d') : null,
            'appointment_time' => $appointment->appointment_datetime ? $appointment->appointment_datetime->format('H:i:s') : null,
            'appointment_datetime' => $appointment->appointment_datetime ? $appointment->appointment_datetime->toIso8601String() : null,
            'duration_minutes' => $appointment->duration_minutes ?? 15,
            'status' => $appointment->status,
            'status_display' => $statusDisplay,
            'is_paid' => $appointment->is_paid ?? false,
            'amount' => number_format((float) ($appointment->amount ?? 0), 2, '.', ''),
            'discount_amount' => number_format((float) ($appointment->discount_amount ?? 0), 2, '.', ''),
            'final_amount' => number_format((float) ($appointment->final_amount ?? 0), 2, '.', ''),
            'promo_code' => $appointment->promo_code,
            'assigned_admin' => $appointment->consultant ? [
                'id' => $appointment->consultant->id,
                'name' => $appointment->consultant->name ?? null,
            ] : null,
            'payment' => $appointment->payment_status ?? null,
            'admin_notes' => $appointment->admin_notes,
            'client_notes' => null, // Add if this field exists in the table
            'confirmed_at' => $appointment->confirmed_at ? $appointment->confirmed_at->toIso8601String() : null,
            'cancelled_at' => $appointment->cancelled_at ? $appointment->cancelled_at->toIso8601String() : null,
            'cancellation_reason' => $appointment->cancellation_reason,
            'created_at' => $appointment->created_at ? $appointment->created_at->toIso8601String() : null,
            'updated_at' => $appointment->updated_at ? $appointment->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Add new appointment
     *
     * Creates a new appointment for the authenticated client.
     * Uses the same logic as addAppointmentBook in ClientsController.
     *
     * @return JsonResponse
     */
    public function addAppointment(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            $requestData = $request->all();

            // Validate required fields
            $validator = Validator::make($requestData, [
                'noe_id' => 'required|integer|in:1,2,3,4,5,6,7,8,9,10,11,12',
                'service_id' => 'required|integer|in:1,2,3',
                'appoint_date' => 'required|string', // Accept string format (dd/mm/yyyy), validate after conversion
                'appoint_time' => 'required|string',
                'description' => 'required|string',
                'appointment_details' => 'required|integer|in:1,2,3', // 1=phone, 2=in_person, 3=video_call
                'preferred_language' => 'required|integer|in:1,2,3', // 1=English, 2=Hindi, 3=Punjabi
                'inperson_address' => 'required|in:1,2',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            if (in_array((int) $requestData['noe_id'], [6, 7], true) && (int) $requestData['service_id'] !== 2) {
                return $this->sendError(
                    'For this nature of enquiry, only Comprehensive Migration Advice is available.',
                    ['service_id' => ['Only Comprehensive Migration Advice is available for this nature of enquiry.']],
                    422
                );
            }

            // Get client information - logged in user_id is the client_id
            $client = Admin::findOrFail($user->id);

            // Get client_unique_id (which is the client_id field from admins table)
            $clientUniqueId = $client->client_id ?? null;

            // Validate client has required fields
            $clientName = trim($client->first_name.' '.($client->last_name ?? ''));
            if (empty($clientName)) {
                $clientName = $client->email ?? 'Client '.$client->id;
            }

            $clientEmail = $client->email ?? '';
            if (empty($clientEmail)) {
                return $this->sendError('Client email is required. Please update client information first.', [], 422);
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

            // Map meeting type (appointment_details: 1=phone, 2=in_person, 3=video_call)
            $appointmentDetailsToMeetingType = [
                1 => 'phone',
                2 => 'in_person',
                3 => 'video',
            ];
            $meetingType = $appointmentDetailsToMeetingType[(int) $requestData['appointment_details']] ?? 'in_person';

            // Map preferred language (1=English, 2=Hindi, 3=Punjabi)
            $preferredLanguageMap = [1 => 'English', 2 => 'Hindi', 3 => 'Punjabi'];
            $preferredLanguage = $preferredLanguageMap[(int) $requestData['preferred_language']] ?? 'English';

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
                        $timeStr = $timeMatches[1].':'.$timeMatches[2];
                    }
                }
            } catch (\Exception $e) {
                // If parsing fails, try to extract HH:MM format
                if (preg_match('/^(\d{1,2}):(\d{2})/', $timeStr, $timeMatches)) {
                    $timeStr = $timeMatches[1].':'.$timeMatches[2];
                } else {
                    throw new \Exception('Invalid time format: '.$requestData['appoint_time']);
                }
            }

            // Combine date and time
            $dateStr = $requestData['appoint_date'];
            $timezone = $requestData['timezone'] ?? 'Australia/Melbourne';

            // Convert date from dd/mm/yyyy to Y-m-d format if needed
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $dateMatches)) {
                // Date is in dd/mm/yyyy format, convert to Y-m-d
                $dateStr = $dateMatches[3].'-'.$dateMatches[2].'-'.$dateMatches[1];
            }

            try {
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr.' '.$timeStr, $timezone)
                    ->setTimezone(config('app.timezone', 'UTC'));
            } catch (\Exception $e) {
                // Try alternative date format
                try {
                    $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeStr.':00', $timezone)
                        ->setTimezone(config('app.timezone', 'UTC'));
                } catch (\Exception $e2) {
                    throw new \Exception('Invalid date/time format. Date: '.$requestData['appoint_date'].', Time: '.$timeStr.'. Error: '.$e2->getMessage());
                }
            }

            // Validate appointment is in the future
            if ($appointmentDateTime->isPast()) {
                return $this->sendError('Appointment date and time must be in the future', [], 422);
            }

            // Calculate duration based on service
            // Service 1 (Free Consultation) = 15 min, Service 2/3 (Paid) = 30 min
            $durationMinutes = $requestData['service_id'] == 1 ? 15 : 30;

            // Check for duplicate appointments - prevent booking the same time slot
            // An appointment is considered duplicate if:
            // 1. It's for the same client
            // 2. It's on the same date and time
            // 3. It's not cancelled or rescheduled
            $existingAppointment = BookingAppointment::where('client_id', $client->id)
                ->where('appointment_datetime', $appointmentDateTime)
                ->whereNotIn('status', ['cancelled', 'rescheduled'])
                ->first();

            if ($existingAppointment) {
                return $this->sendError('This appointment time slot already exists. Please try a different time slot.', [], 422);
            }

            // Use ConsultantAssignmentService to assign consultant
            $consultantAssigner = app(ConsultantAssignmentService::class);
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
                'preferred_language' => $preferredLanguage,
                'is_paid' => ($serviceId == 2) ? false : true,
            ];
            $consultant = $consultantAssigner->assignConsultant($appointmentDataForConsultant);

            // Consultant is nullable, but log if not found
            if (! $consultant) {
                Log::warning('No consultant assigned for appointment', [
                    'noe_id' => $requestData['noe_id'],
                    'service_id' => $serviceId,
                    'location' => $location,
                    'inperson_address' => $requestData['inperson_address'],
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
                'preferred_language' => $preferredLanguage,
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
                    throw new \Exception('Bansal API did not return appointment ID. Response: '.json_encode($bansalApiResponse));
                }

                Log::info('Appointment created on Bansal website', [
                    'bansal_appointment_id' => $bansalAppointmentId,
                    'client_id' => $client->id,
                    'client_email' => $clientEmail,
                ]);
            } catch (\Exception $apiException) {
                $bansalApiError = $apiException->getMessage();
                Log::error('Failed to create appointment on Bansal website via API', [
                    'error' => $bansalApiError,
                    'client_id' => $client->id,
                    'client_email' => $clientEmail,
                    'payload' => $bansalApiPayload,
                    'trace' => $apiException->getTraceAsString(),
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
                    'client_id' => $client->id,
                ]);
            }

            // Create booking appointment
            $appointment = BookingAppointment::create([
                'bansal_appointment_id' => $bansalAppointmentId,
                'order_hash' => null, // No payment for manually created appointments

                'client_id' => $client->id,
                'consultant_id' => $consultant ? $consultant->id : null,
                'assigned_by_admin_id' => null,

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
                'preferred_language' => $preferredLanguage,

                'service_id' => $serviceId,
                'noe_id' => $requestData['noe_id'],
                'enquiry_type' => $serviceTypeMapping['enquiry_type'],
                'service_type' => $serviceTypeMapping['service_type'],
                'enquiry_details' => $requestData['description'],

                'status' => BookingAppointmentStatus::forNewBooking(
                    (int) $serviceId,
                    $requestData['payment_status'] ?? null
                ),
                'confirmed_at' => null,
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

                'user_id' => $client->id,
            ]);

            // Format and return the created appointment
            $result = $this->formatAppointmentData($appointment);

            // Send confirmation email for free appointments (service_id input = 1)
            if ($serviceId == 2 && ! empty($clientEmail)) {
                try {
                    $emailDetails = [
                        'appointment_id' => $appointment->id,
                        'client_name' => $clientName,
                        'appointment_datetime' => $appointment->appointment_datetime,
                        'timeslot_full' => $appointment->timeslot_full,
                        'location' => $appointment->location,
                        'service_type' => $appointment->service_type,
                        'meeting_type' => $appointment->meeting_type,
                        'admin_notes' => $appointment->admin_notes ?? null,
                    ];
                    Mail::mailer('sendgrid')->to($clientEmail)->send(new AppointmentDetailedConfirmation($emailDetails));
                    $appointment->update([
                        'confirmation_email_sent' => true,
                        'confirmation_email_sent_at' => now(),
                    ]);
                    Log::info('Appointment confirmation email sent', [
                        'appointment_id' => $appointment->id,
                        'email' => $clientEmail,
                    ]);
                } catch (\Exception $mailEx) {
                    Log::error('Failed to send appointment confirmation email', [
                        'appointment_id' => $appointment->id,
                        'email' => $clientEmail,
                        'error' => $mailEx->getMessage(),
                    ]);
                }
            }

            // Prepare response message
            $successMessage = 'Appointment created successfully';
            if ($bansalApiError) {
                $successMessage .= '. Note: Appointment created in CRM but could not be synced to Bansal website. Error: '.$bansalApiError;
                Log::warning('Appointment created locally but Bansal API sync failed', [
                    'appointment_id' => $appointment->id,
                    'bansal_appointment_id' => $bansalAppointmentId,
                    'api_error' => $bansalApiError,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'bansal_synced' => ! $bansalApiError,
                'bansal_appointment_id' => $bansalAppointmentId,
                'client_unique_id' => $clientUniqueId,
                'data' => $result,
            ], 201);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Add Appointment API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Add appointment without login (public API)
     *
     * Creates a new appointment for guest users. No authentication required.
     * If email or phone matches existing client/lead, uses that client.
     * If no match, creates new lead in admins, client_emails, and client_contacts.
     *
     * @return JsonResponse
     */
    public function addAppointmentWithoutLogin(Request $request)
    {
        try {
            $requestData = $request->all();

            // Validate required fields (same as addAppointment + full_name, email, phone)
            $validator = Validator::make($requestData, [
                'noe_id' => 'required|integer|in:1,2,3,4,5,6,7,8,9,10,11,12',
                'service_id' => 'required|integer|in:1,2,3',
                'appoint_date' => 'required|string',
                'appoint_time' => 'required|string',
                'description' => 'required|string',
                'appointment_details' => 'required|integer|in:1,2,3',
                'preferred_language' => 'required|integer|in:1,2,3',
                'inperson_address' => 'required|in:1,2',
                'full_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:50',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            if (in_array((int) $requestData['noe_id'], [6, 7], true) && (int) $requestData['service_id'] !== 2) {
                return $this->sendError(
                    'For this nature of enquiry, only Comprehensive Migration Advice is available.',
                    ['service_id' => ['Only Comprehensive Migration Advice is available for this nature of enquiry.']],
                    422
                );
            }

            $email = strtolower(trim($requestData['email']));
            $phone = $this->normalizePhoneForMatching($requestData['phone'], $requestData['country_code'] ?? null);
            $fullName = trim($requestData['full_name']);

            // Find or create client
            $client = $this->findOrCreateClientForPublicAppointment($email, $phone, $fullName);

            if (! $client) {
                return $this->sendError('Unable to find or create client. Please ensure email and phone are valid.', [], 422);
            }

            // Build client info for appointment logic (same structure as addAppointment)
            $clientName = trim($client->first_name.' '.($client->last_name ?? ''));
            if (empty($clientName)) {
                $clientName = $client->email ?? 'Client '.$client->id;
            }

            $clientEmail = $client->email ?? '';
            $clientUniqueId = $client->client_id ?? null;

            // Map service_id from form to actual service_id
            $serviceIdMap = [
                1 => 2, // Free Consultation -> Free
                2 => 1, // Comprehensive Migration Advice -> Paid
                3 => 3, // Overseas Applicant Enquiry -> Paid Overseas
            ];
            $serviceId = $serviceIdMap[$requestData['service_id']] ?? 2;

            // Map NOE ID to service_type/enquiry_type
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
            $serviceTypeMapping = $noeToServiceType[$requestData['noe_id']] ?? ['service_type' => 'Other', 'enquiry_type' => 'pr_complex'];

            // Map location
            $locationMap = [1 => 'adelaide', 2 => 'melbourne'];
            $location = $locationMap[$requestData['inperson_address']] ?? 'melbourne';

            // Map meeting type
            $appointmentDetailsToMeetingType = [1 => 'phone', 2 => 'in_person', 3 => 'video'];
            $meetingType = $appointmentDetailsToMeetingType[(int) $requestData['appointment_details']] ?? 'in_person';

            // Map preferred language
            $preferredLanguageMap = [1 => 'English', 2 => 'Hindi', 3 => 'Punjabi'];
            $preferredLanguage = $preferredLanguageMap[(int) $requestData['preferred_language']] ?? 'English';

            // Parse appointment time
            $timeStr = trim($requestData['appoint_time']);
            if (preg_match('/^([0-9]{1,2}:[0-9]{2}\s*(?:AM|PM)?)/i', $timeStr, $matches)) {
                $timeStr = trim($matches[1]);
            }
            try {
                if (preg_match('/(AM|PM)/i', $timeStr)) {
                    $parsedTime = Carbon::createFromFormat('g:i A', $timeStr);
                    $timeStr = $parsedTime->format('H:i');
                } elseif (preg_match('/^(\d{1,2}):(\d{2})/', $timeStr, $timeMatches)) {
                    $timeStr = $timeMatches[1].':'.$timeMatches[2];
                }
            } catch (\Exception $e) {
                if (preg_match('/^(\d{1,2}):(\d{2})/', $timeStr, $timeMatches)) {
                    $timeStr = $timeMatches[1].':'.$timeMatches[2];
                } else {
                    throw new \Exception('Invalid time format: '.$requestData['appoint_time']);
                }
            }

            // Combine date and time
            $dateStr = $requestData['appoint_date'];
            $timezone = $requestData['timezone'] ?? 'Australia/Melbourne';
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $dateMatches)) {
                $dateStr = $dateMatches[3].'-'.$dateMatches[2].'-'.$dateMatches[1];
            }
            try {
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i', $dateStr.' '.$timeStr, $timezone)
                    ->setTimezone(config('app.timezone', 'UTC'));
            } catch (\Exception $e) {
                try {
                    $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeStr.':00', $timezone)
                        ->setTimezone(config('app.timezone', 'UTC'));
                } catch (\Exception $e2) {
                    throw new \Exception('Invalid date/time format. Date: '.$requestData['appoint_date'].', Time: '.$timeStr.'. Error: '.$e2->getMessage());
                }
            }

            if ($appointmentDateTime->isPast()) {
                return $this->sendError('Appointment date and time must be in the future', [], 422);
            }

            $durationMinutes = $requestData['service_id'] == 1 ? 15 : 30;

            // Check for duplicate appointments
            $existingAppointment = BookingAppointment::where('client_id', $client->id)
                ->where('appointment_datetime', $appointmentDateTime)
                ->whereNotIn('status', ['cancelled', 'rescheduled'])
                ->first();

            if ($existingAppointment) {
                return $this->sendError('This appointment time slot already exists. Please try a different time slot.', [], 422);
            }

            // Assign consultant
            $consultantAssigner = app(ConsultantAssignmentService::class);
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
                'preferred_language' => $preferredLanguage,
                'is_paid' => ($serviceId == 2) ? false : true,
            ];
            $consultant = $consultantAssigner->assignConsultant($appointmentDataForConsultant);

            if (! $consultant) {
                Log::warning('No consultant assigned for public appointment', [
                    'noe_id' => $requestData['noe_id'],
                    'service_id' => $serviceId,
                    'location' => $location,
                ]);
            }

            // Bansal API
            $specificServiceMap = [1 => 'paid-consultation', 2 => 'consultation', 3 => 'overseas-enquiry'];
            $specificService = $specificServiceMap[$serviceId] ?? 'consultation';

            $appointmentDateForApi = $appointmentDateTime->copy()->setTimezone($timezone)->format('Y-m-d');
            $appointmentTimeForApi = $appointmentDateTime->copy()->setTimezone($timezone)->format('H:i');

            $clientPhoneForApi = $client->phone ?? $requestData['phone'] ?? '';

            $bansalApiPayload = [
                'full_name' => $clientName,
                'email' => $clientEmail,
                'phone' => $clientPhoneForApi,
                'appointment_date' => $appointmentDateForApi,
                'appointment_time' => $appointmentTimeForApi,
                'appointment_datetime' => $appointmentDateTime->copy()->setTimezone($timezone)->format('Y-m-d H:i:s'),
                'duration_minutes' => $durationMinutes,
                'location' => $location,
                'meeting_type' => $meetingType,
                'preferred_language' => $preferredLanguage,
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

            $bansalAppointmentId = null;
            $bansalApiError = null;

            try {
                $bansalApiClient = app(BansalApiClient::class);
                $bansalApiResponse = $bansalApiClient->createAppointment($bansalApiPayload);

                if (isset($bansalApiResponse['data']['id'])) {
                    $bansalAppointmentId = (int) $bansalApiResponse['data']['id'];
                } elseif (isset($bansalApiResponse['data']['appointment_id'])) {
                    $bansalAppointmentId = (int) $bansalApiResponse['data']['appointment_id'];
                } elseif (isset($bansalApiResponse['appointment_id'])) {
                    $bansalAppointmentId = (int) $bansalApiResponse['appointment_id'];
                } else {
                    throw new \Exception('Bansal API did not return appointment ID. Response: '.json_encode($bansalApiResponse));
                }

                Log::info('Public appointment created on Bansal website', [
                    'bansal_appointment_id' => $bansalAppointmentId,
                    'client_id' => $client->id,
                    'client_email' => $clientEmail,
                ]);
            } catch (\Exception $apiException) {
                $bansalApiError = $apiException->getMessage();
                Log::error('Failed to create public appointment on Bansal website via API', [
                    'error' => $bansalApiError,
                    'client_id' => $client->id,
                    'client_email' => $clientEmail,
                    'payload' => $bansalApiPayload,
                    'trace' => $apiException->getTraceAsString(),
                ]);
                $bansalAppointmentId = null;

                // Slot availability error - do NOT create appointment, return error immediately
                $errorLower = strtolower($bansalApiError);
                $isSlotUnavailable = strpos($errorLower, 'time slot') !== false
                    || (strpos($errorLower, 'slot') !== false && strpos($errorLower, 'not available') !== false)
                    || (strpos($errorLower, 'slot') !== false && (strpos($errorLower, 'already') !== false || strpos($errorLower, 'booked') !== false || strpos($errorLower, 'taken') !== false));
                if ($isSlotUnavailable) {
                    return $this->sendError('The selected time slot is not available. Please choose a different time slot.', [], 422);
                }
            }

            if ($bansalAppointmentId === null) {
                $bansalAppointmentId = 2000000 + (time() % 900000) + mt_rand(1, 99999);
                while (BookingAppointment::where('bansal_appointment_id', $bansalAppointmentId)->exists()) {
                    $bansalAppointmentId = 2000000 + (time() % 900000) + mt_rand(1, 99999);
                }
                Log::warning('Using temporary bansal_appointment_id for public appointment due to API failure', [
                    'temporary_id' => $bansalAppointmentId,
                    'api_error' => $bansalApiError,
                    'client_id' => $client->id,
                ]);
            }

            // Create booking appointment
            $appointment = BookingAppointment::create([
                'bansal_appointment_id' => $bansalAppointmentId,
                'order_hash' => null,
                'client_id' => $client->id,
                'consultant_id' => $consultant ? $consultant->id : null,
                'assigned_by_admin_id' => null,
                'client_name' => $clientName,
                'client_email' => $clientEmail,
                'client_phone' => $client->phone ?? $clientPhoneForApi,
                'client_timezone' => $requestData['timezone'] ?? 'Australia/Melbourne',
                'appointment_datetime' => $appointmentDateTime,
                'timeslot_full' => $requestData['appoint_time'],
                'duration_minutes' => $durationMinutes,
                'location' => $location,
                'inperson_address' => $requestData['inperson_address'],
                'meeting_type' => $meetingType,
                'preferred_language' => $preferredLanguage,
                'service_id' => $serviceId,
                'noe_id' => $requestData['noe_id'],
                'enquiry_type' => $serviceTypeMapping['enquiry_type'],
                'service_type' => $serviceTypeMapping['service_type'],
                'enquiry_details' => $requestData['description'],
                'status' => BookingAppointmentStatus::forNewBooking(
                    (int) $serviceId,
                    $requestData['payment_status'] ?? null
                ),
                'confirmed_at' => null,
                'is_paid' => ($serviceId == 2) ? false : true,
                'amount' => ($serviceId == 2) ? 0 : 150,
                'final_amount' => ($serviceId == 2) ? 0 : 150,
                'payment_status' => ($serviceId == 2) ? null : ($requestData['payment_status'] ?? 'pending'),
                'confirmation_email_sent' => false,
                'reminder_sms_sent' => false,
                'sync_status' => $bansalApiError ? 'error' : 'synced',
                'sync_error' => $bansalApiError,
                'last_synced_at' => $bansalApiError ? null : now(),
                'user_id' => $client->id,
            ]);

            $result = $this->formatAppointmentData($appointment);

            // Send confirmation email for free appointments (service_id input = 1)
            if ($serviceId == 2 && ! empty($clientEmail)) {
                try {
                    $emailDetails = [
                        'appointment_id' => $appointment->id,
                        'client_name' => $clientName,
                        'appointment_datetime' => $appointment->appointment_datetime,
                        'timeslot_full' => $appointment->timeslot_full,
                        'location' => $appointment->location,
                        'service_type' => $appointment->service_type,
                        'meeting_type' => $appointment->meeting_type,
                        'admin_notes' => $appointment->admin_notes ?? null,
                    ];
                    Mail::mailer('sendgrid')->to($clientEmail)->send(new AppointmentDetailedConfirmation($emailDetails));
                    $appointment->update([
                        'confirmation_email_sent' => true,
                        'confirmation_email_sent_at' => now(),
                    ]);
                    Log::info('Appointment confirmation email sent (without login)', [
                        'appointment_id' => $appointment->id,
                        'email' => $clientEmail,
                    ]);
                } catch (\Exception $mailEx) {
                    Log::error('Failed to send appointment confirmation email (without login)', [
                        'appointment_id' => $appointment->id,
                        'email' => $clientEmail,
                        'error' => $mailEx->getMessage(),
                    ]);
                }
            }

            $successMessage = 'Appointment created successfully';
            if ($bansalApiError) {
                $successMessage .= '. Note: Appointment created in CRM but could not be synced to Bansal website. Error: '.$bansalApiError;
            }

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'bansal_synced' => ! $bansalApiError,
                'bansal_appointment_id' => $bansalAppointmentId,
                'client_unique_id' => $clientUniqueId,
                'data' => $result,
            ], 201);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Add Appointment Without Login API Error: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Find or create client for public appointment.
     * Matches by email first, then phone. Creates new lead with client_emails and client_contacts if no match.
     */
    private function findOrCreateClientForPublicAppointment(string $email, string $phone, string $fullName): ?Admin
    {
        $client = Admin::findClientOrLeadByNormalizedEmail($email);
        if ($client) {
            Log::info('Found existing client by email for public appointment', ['client_id' => $client->id, 'email' => $email]);

            return $client;
        }

        $client = Admin::findClientOrLeadByNormalizedPhone($phone);
        if ($client) {
            Log::info('Found existing client by phone for public appointment', ['client_id' => $client->id]);

            return $client;
        }

        return $this->createNewLeadForPublicAppointment($email, $phone, $fullName);
    }

    /**
     * Create new lead for public appointment (admins + client_emails + client_contacts)
     */
    private function createNewLeadForPublicAppointment(string $email, string $phone, string $fullName): ?Admin
    {
        DB::beginTransaction();

        try {
            $nameParts = $this->parseFullNameForPublicAppointment($fullName);
            $firstName = $nameParts['first_name'];
            $lastName = $nameParts['last_name'];

            $referenceService = app(ClientReferenceService::class);
            $reference = $referenceService->generateClientReference($firstName);
            $clientId = $reference['client_id'];
            $clientCounter = $reference['client_counter'];

            $countryCode = $this->extractCountryCodeFromPhone($phone);
            $phoneForStorage = (str_starts_with($phone, '+') ? $phone : '+'.ltrim($phone, '0'));

            $admin = new Admin;
            $admin->first_name = $firstName;
            $admin->last_name = $lastName;
            $admin->email = $email;
            $admin->phone = $phoneForStorage;
            $admin->country_code = $countryCode;
            $admin->client_counter = $clientCounter;
            $admin->client_id = $clientId;
            $admin->type = 'lead';
            $admin->source = 'Public Appointment';
            $admin->password = Hash::make('LEAD_PLACEHOLDER');
            $admin->status = '1';
            $admin->verified = 0;
            $admin->cp_status = 0;
            $admin->cp_code_verify = 0;
            $admin->australian_study = 0;
            $admin->specialist_education = 0;
            $admin->regional_study = 0;
            $admin->is_archived = 0;
            $admin->created_at = now();
            $admin->updated_at = now();
            $admin->save();

            $systemUserId = config('app.system_user_id', 1);

            // client_emails with email_type = 'Personal'
            ClientEmail::create([
                'admin_id' => $systemUserId,
                'client_id' => $admin->id,
                'email_type' => 'Personal',
                'email' => $email,
                'is_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // client_contacts with contact_type = 'Personal'
            ClientContact::create([
                'admin_id' => $systemUserId,
                'client_id' => $admin->id,
                'contact_type' => 'Personal',
                'country_code' => $countryCode,
                'phone' => $phoneForStorage,
                'is_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            Log::info('Created new lead from public appointment', [
                'client_id' => $admin->id,
                'client_code' => $clientId,
                'email' => $email,
            ]);

            return $admin;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create lead for public appointment', [
                'error' => $e->getMessage(),
                'email' => $email,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function parseFullNameForPublicAppointment(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? 'Unknown',
            'last_name' => $parts[1] ?? null,
        ];
    }

    private function extractCountryCodeFromPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        if (preg_match('/^\+(\d{1,3})/', $phone, $m)) {
            return '+'.$m[1];
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 2 && substr($digits, 0, 2) === '61') {
            return '+61';
        }

        return '+61';
    }

    private function normalizePhoneForMatching(?string $phone, ?string $countryCode = null): string
    {
        if (empty($phone)) {
            return '';
        }
        $phone = trim($phone);
        if ($countryCode && preg_match('/\+?(\d+)/', $countryCode, $m)) {
            $phone = $m[1].preg_replace('/\D/', '', $phone);
        } else {
            $phone = preg_replace('/\D/', '', $phone);
            if (strlen($phone) === 9 && $phone[0] === '4') {
                $phone = '61'.$phone;
            } elseif (strlen($phone) === 10 && $phone[0] === '0') {
                $phone = '61'.substr($phone, 1);
            }
        }

        return $phone;
    }

    private function phonesMatch(?string $dbPhone, string $inputPhone): bool
    {
        if (empty($dbPhone) || empty($inputPhone)) {
            return false;
        }
        $a = $this->normalizePhoneDigits($dbPhone);
        $b = $this->normalizePhoneDigits($inputPhone);

        return strlen($a) >= 8 && strlen($b) >= 8 && $a === $b;
    }

    /** Normalize phone to digits-only (Australian 04xx -> 61xxx, etc.) for matching. */
    private function normalizePhoneDigits(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 10 && $digits[0] === '0') {
            return '61'.substr($digits, 1);
        }
        if (strlen($digits) === 9 && $digits[0] === '4') {
            return '61'.$digits;
        }

        return $digits;
    }

    /**
     * Map meeting type from various formats to database format
     *
     * @param  string  $meetingType
     * @return string
     */
    /**
     * Get disabled dates from calendar
     *
     * Returns disabled dates array along with duration, start_time, end_time, and weeks
     * for the specified service type and location. Uses slot_overwrite=0 automatically.
     *
     * @return JsonResponse
     */
    public function getDisabledDateFromCalendar(Request $request)
    {
        try {
            // Get input parameters
            $id = $request->input('id'); // 1=>consultation, 2=>paid-consultation, 3=>overseas-enquiry
            $enquiry_item = $request->input('enquiry_item'); // 1=>permanent-residency, 2=>temporary-residency, etc.
            $inperson_address = $request->input('inperson_address'); // 1=>Adelaide, 2=>melbourne
            $slot_overwrite = 0; // Always set to 0, not passed in input

            // Validate required parameters
            if (empty($id) || empty($enquiry_item) || empty($inperson_address)) {
                return $this->sendError('Missing required parameters: id, enquiry_item, and inperson_address are required', [], 422);
            }

            Log::info('getDisabledDateFromCalendar called', [
                'id' => $id,
                'enquiry_item' => $enquiry_item,
                'inperson_address' => $inperson_address,
                'slot_overwrite' => $slot_overwrite,
            ]);

            // Map id to specific_service
            $specific_service_map = [
                1 => 'consultation',
                2 => 'paid-consultation',
                3 => 'overseas-enquiry',
            ];
            $specific_service = $specific_service_map[$id] ?? 'consultation';

            // Map inperson_address to location
            $location_map = [
                1 => 'adelaide',
                2 => 'melbourne',
            ];
            $location = $location_map[$inperson_address] ?? 'adelaide';

            $service_type = BansalSchedulingServiceType::fromEnquiryItem($enquiry_item, $location);

            // Prepare request data for external API
            $requestData = [
                'specific_service' => $specific_service,
                'service_type' => $service_type,
                'location' => $location,
                'slot_overwrite' => $slot_overwrite,
            ];
            [$isPaid, $preferredLanguage] = BansalSchedulingServiceType::melbourneApiExtras(
                $request,
                $location,
                (int) $id
            );
            if ($isPaid !== null) {
                $requestData['is_paid'] = $isPaid;
            }
            if ($preferredLanguage !== null) {
                $requestData['preferred_language'] = $preferredLanguage;
            }

            try {
                // Get API configuration
                $baseUrl = 'https://www.bansalimmigration.com.au/api/crm';
                $apiToken = config('services.bansal_api.token');
                $timeout = config('services.bansal_api.timeout', 30);

                if (empty($apiToken)) {
                    Log::error('Bansal API token not configured');

                    return $this->sendError('Bansal API token not configured. Set BANSAL_API_TOKEN in .env', [], 500);
                }

                // Make API call to external Bansal API
                $response = Http::timeout($timeout)
                    ->withToken($apiToken)
                    ->acceptJson()
                    ->post("{$baseUrl}/appointments/get-datetime-backend", $requestData);

                if ($response->failed()) {
                    Log::error('Bansal API get-datetime-backend Error', [
                        'method' => 'getDisabledDateFromCalendar',
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'request_data' => $requestData,
                    ]);

                    return $this->sendError('Failed to fetch datetime backend from external API', [
                        'error' => $response->status() === 404 ? 'Endpoint not found' : 'API request failed',
                    ], $response->status());
                }

                $data = $response->json();
                $disabledDates = $data['disabledatesarray'] ?? $data['disableddatesarray'] ?? [];

                // Format response to match expected output
                $result = [
                    'success' => $data['success'] ?? true,
                    'disabledatesarray' => $disabledDates,
                    'duration' => $data['duration'] ?? 15,
                    'start_time' => $data['start_time'] ?? '10:45',
                    'end_time' => $data['end_time'] ?? '16:00',
                    'weeks' => $data['weeks'] ?? [],
                ];

                return $this->sendResponse($result, 'Disabled dates retrieved successfully');

            } catch (RequestException $e) {
                $response = $e->response;
                $responseBody = $response?->json();
                $message = null;

                if (is_array($responseBody)) {
                    $message = $responseBody['message']
                        ?? ($responseBody['error']['message'] ?? null);
                }

                $message = $message ?: $response?->body() ?: $e->getMessage();

                Log::error('Bansal API get-datetime-backend Request Error', [
                    'method' => 'getDisabledDateFromCalendar',
                    'message' => $message,
                    'request_data' => $requestData,
                    'exception' => $e->getMessage(),
                ]);

                return $this->sendError('API request failed: '.$message, [], 500);
            } catch (\Exception $e) {
                Log::error('Bansal API get-datetime-backend Exception', [
                    'method' => 'getDisabledDateFromCalendar',
                    'message' => $e->getMessage(),
                    'request_data' => $requestData,
                    'trace' => $e->getTraceAsString(),
                ]);

                return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
            }

        } catch (\Exception $e) {
            Log::error('getDisabledDateFromCalendar Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get disabled time slots for a specific date from calendar
     *
     * Returns disabled time slots array for the specified date, service type and location.
     * Uses slot_overwrite=0 automatically.
     *
     * @return JsonResponse
     */
    public function getDisabledSlotsOfAnyDateFromCalendar(Request $request)
    {
        try {
            // Get input parameters
            $service_id = $request->input('service_id'); // 1=>consultation, 2=>paid-consultation, 3=>overseas-enquiry
            $enquiry_item = $request->input('enquiry_item'); // 1=>permanent-residency, 2=>temporary-residency, etc.
            $inperson_address = $request->input('inperson_address'); // 1=>adelaide, 2=>melbourne
            $sel_date = $request->input('sel_date'); // Date in dd/mm/yyyy format
            $slot_overwrite = 0; // Always set to 0, not passed in input

            // Validate required parameters
            if (empty($service_id) || empty($enquiry_item) || empty($inperson_address) || empty($sel_date)) {
                return $this->sendError('Missing required parameters: service_id, enquiry_item, inperson_address, and sel_date are required', [], 422);
            }

            // Validate date format (dd/mm/yyyy)
            if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $sel_date)) {
                return $this->sendError('Invalid date format. Date must be in dd/mm/yyyy format', [], 422);
            }

            Log::info('getDisabledSlotsOfAnyDateFromCalendar called', [
                'service_id' => $service_id,
                'enquiry_item' => $enquiry_item,
                'inperson_address' => $inperson_address,
                'sel_date' => $sel_date,
                'slot_overwrite' => $slot_overwrite,
            ]);

            // Map service_id to specific_service
            $specific_service_map = [
                1 => 'consultation',
                2 => 'paid-consultation',
                3 => 'overseas-enquiry',
            ];
            $specific_service = $specific_service_map[$service_id] ?? 'consultation';

            // Map inperson_address to location
            $location_map = [
                1 => 'adelaide',
                2 => 'melbourne',
            ];
            $location = $location_map[$inperson_address] ?? 'adelaide';

            $service_type = BansalSchedulingServiceType::fromEnquiryItem($enquiry_item, $location);

            [$isPaid, $preferredLanguage] = BansalSchedulingServiceType::melbourneApiExtras(
                $request,
                $location,
                (int) $service_id
            );

            try {
                // Use BansalApiClient to call the website API (same as getdisableddatetime)
                $apiClient = new BansalApiClient;
                $response = $apiClient->getDisabledDateTime(
                    $specific_service,
                    $service_type,
                    $location,
                    $sel_date,
                    $slot_overwrite,
                    $isPaid,
                    $preferredLanguage
                );

                // Format response to match expected output
                $result = [
                    'success' => $response['success'] ?? true,
                    'disabledtimeslotes' => $response['disabledtimeslotes'] ?? [],
                ];

                return $this->sendResponse($result, 'Disabled time slots retrieved successfully');

            } catch (\Exception $e) {
                Log::error('Bansal API get-disabled-datetime Exception', [
                    'method' => 'getDisabledSlotsOfAnyDateFromCalendar',
                    'message' => $e->getMessage(),
                    'service_id' => $service_id,
                    'enquiry_item' => $enquiry_item,
                    'inperson_address' => $inperson_address,
                    'sel_date' => $sel_date,
                    'slot_overwrite' => $slot_overwrite,
                    'trace' => $e->getTraceAsString(),
                ]);

                return $this->sendError('An error occurred: '.$e->getMessage(), [
                    'disabledtimeslotes' => [],
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('getDisabledSlotsOfAnyDateFromCalendar Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Update appointment status (Cancel or Complete only)
     *
     * Allows authenticated clients to update their appointment status.
     * Only supports 'cancel' and 'complete' status types for mobile app usage.
     *
     * @param  int  $id  Appointment ID
     * @return JsonResponse
     */
    public function updateAppointmentStatus(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            // Find appointment that belongs to the authenticated client
            $appointment = BookingAppointment::where('id', $id)
                ->where('client_id', $user->id)
                ->first();

            if (! $appointment) {
                return $this->sendError('Appointment not found', [], 404);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:cancel,complete',
                'cancel_reason' => 'required_if:type,cancel|nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            // Map 'cancel' to 'cancelled' and 'complete' to 'completed'
            $statusMap = [
                'cancel' => 'cancelled',
                'complete' => 'completed',
            ];
            $newStatus = $statusMap[$request->type];

            // Check if appointment can be updated
            if (in_array($appointment->status, ['cancelled', 'completed'])) {
                return $this->sendError('Cannot update appointment. Appointment is already '.$appointment->status, [], 422);
            }

            $oldStatus = $appointment->status;
            $appointment->status = $newStatus;

            // Set timestamp based on status
            switch ($newStatus) {
                case 'completed':
                    $appointment->completed_at = now();
                    break;
                case 'cancelled':
                    $appointment->cancelled_at = now();
                    $appointment->cancellation_reason = $request->cancel_reason ?? null;
                    break;
            }

            $appointment->save();

            // Sync with Bansal API if applicable
            $syncError = null;
            if ($appointment->bansal_appointment_id) {
                $recoveryService = app(BansalAppointmentRecoveryService::class);
                $result = $recoveryService->syncStatus(
                    $appointment,
                    $newStatus,
                    $request->cancel_reason ?? null
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

                    Log::info('Appointment status synced with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $appointment->bansal_appointment_id,
                        'status' => $newStatus,
                    ]);
                } else {
                    $syncError = $result['error'];

                    Log::error('Failed to sync appointment status with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $appointment->bansal_appointment_id,
                        'status' => $newStatus,
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
                    'status' => $newStatus,
                ]);
                $syncError = 'Missing Bansal appointment identifier.';
            }

            // Log activity
            if ($appointment->client_id) {
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $appointment->client_id;
                $activityLog->created_by = $user->id;
                $activityLog->subject = 'Appointment status updated via mobile app';
                $activityLog->description = '<p><strong>Status changed:</strong> '.ucfirst($oldStatus).' → '.ucfirst($newStatus).'</p>'.
                                           ($request->cancel_reason ? '<p><strong>Cancellation Reason:</strong> '.e($request->cancel_reason).'</p>' : '');
                $activityLog->task_status = 0;
                $activityLog->pin = 0;
                $activityLog->source = 'client_portal';
                $activityLog->save();
            }

            $message = 'Appointment status updated successfully';
            if ($syncError) {
                $message .= '. Note: Status updated locally but could not be synced to Bansal website. Error: '.$syncError;
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'status' => $newStatus,
                    'updated_at' => $appointment->updated_at->toIso8601String(),
                ],
                'bansal_synced' => ! $syncError,
            ], 200);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Update Appointment Status API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'appointment_id' => $id ?? null,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Update Appointment (Reschedule)
     *
     * Allows authenticated clients to update their appointment date, time, meeting type, and preferred language.
     * Syncs with Bansal API if appointment has bansal_appointment_id.
     *
     * @return JsonResponse
     */
    public function updateAppointment(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            // Validate input
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer|exists:booking_appointments,id',
                'appointment_date' => 'required|date|date_format:Y-m-d',
                'appointment_time' => 'required|date_format:H:i',
                'meeting_type' => 'required|integer|in:1,2,3', // 1=phone, 2=in_person, 3=video_call
                'preferred_language' => 'required|integer|in:1,2,3', // 1=English, 2=Hindi, 3=Punjabi
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors(), 422);
            }

            // Find appointment that belongs to the authenticated client
            $appointment = BookingAppointment::where('id', $request->appointment_id)
                ->where('client_id', $user->id)
                ->first();

            if (! $appointment) {
                return $this->sendError('Appointment not found or does not belong to you', [], 404);
            }

            // Map meeting_type (1=phone, 2=in_person, 3=video_call) and preferred_language (1=English, 2=Hindi, 3=Punjabi)
            $meetingTypeMap = [1 => 'phone', 2 => 'in_person', 3 => 'video'];
            $preferredLanguageMap = [1 => 'English', 2 => 'Hindi', 3 => 'Punjabi'];
            $meetingTypeNormalized = $meetingTypeMap[(int) $request->meeting_type] ?? 'in_person';
            $preferredLanguage = $preferredLanguageMap[(int) $request->preferred_language] ?? 'English';

            // Validate: Video meeting type is only allowed for paid appointments
            if ($meetingTypeNormalized === 'video' && ! $appointment->is_paid) {
                return $this->sendError('Video meeting type is only available for paid appointments', [], 422);
            }

            $oldDatetime = $appointment->appointment_datetime;
            $oldMeetingType = $appointment->meeting_type;
            $oldPreferredLanguage = $appointment->preferred_language ?? 'English';

            try {
                $newDatetime = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $request->appointment_date.' '.$request->appointment_time,
                    config('app.timezone')
                );
            } catch (\Exception $e) {
                return $this->sendError('Invalid date or time provided', ['error' => $e->getMessage()], 422);
            }

            // Check if anything has changed
            $datetimeChanged = ! $oldDatetime || ! $oldDatetime->equalTo($newDatetime);
            $meetingTypeChanged = $oldMeetingType !== $meetingTypeNormalized;
            $preferredLanguageChanged = $oldPreferredLanguage !== $preferredLanguage;

            if (! $datetimeChanged && ! $meetingTypeChanged && ! $preferredLanguageChanged) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes detected. Appointment details remain unchanged.',
                    'data' => $this->formatAppointmentData($appointment),
                ], 200);
            }

            // Update appointment fields in local database FIRST (always update locally)
            if ($datetimeChanged) {
                $appointment->appointment_datetime = $newDatetime;
                $appointment->timeslot_full = $newDatetime->format('h:i A');
            }

            if ($meetingTypeChanged) {
                $appointment->meeting_type = $meetingTypeNormalized;
            }

            if ($preferredLanguageChanged) {
                $appointment->preferred_language = $preferredLanguage;
            }

            // Try to sync with Bansal API if any field changed AND bansal_appointment_id exists
            $syncError = null;
            $apiSynced = false;
            $newBansalAppointmentId = null;

            if (($datetimeChanged || $meetingTypeChanged || $preferredLanguageChanged) && ! empty($appointment->bansal_appointment_id)) {
                $apiDate = $datetimeChanged ? $request->appointment_date : $appointment->appointment_datetime->format('Y-m-d');
                $apiTime = $datetimeChanged ? $request->appointment_time : $appointment->appointment_datetime->format('H:i');
                $apiMeetingType = $meetingTypeChanged ? $meetingTypeNormalized : ($appointment->meeting_type ?? 'in_person');
                $apiPreferredLanguage = $preferredLanguageChanged ? $preferredLanguage : ($appointment->preferred_language ?? 'English');

                $recoveryService = app(BansalAppointmentRecoveryService::class);
                $result = $recoveryService->syncReschedule(
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

            $message = 'Appointment '.implode(', ', $messageParts).' updated successfully.';

            // Add warning if API sync failed
            if ($syncError) {
                $message .= ' However, sync with website failed: '.$syncError;
            } elseif ($newBansalAppointmentId) {
                $message .= ' Note: A new appointment was created on the website (previous appointment ID was invalid).';
            }

            Log::info('Appointment updated via API', [
                'user_id' => $user->id,
                'appointment_id' => $appointment->id,
                'datetime_changed' => $datetimeChanged,
                'meeting_type_changed' => $meetingTypeChanged,
                'preferred_language_changed' => $preferredLanguageChanged,
                'bansal_synced' => $apiSynced,
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $this->formatAppointmentData($appointment),
                'bansal_synced' => $apiSynced,
                'sync_error' => $syncError,
            ], 200);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Update Appointment API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Helper method to create appointment via Bansal API when update fails due to invalid appointment ID
     */
    private function createAppointmentViaApi(
        BookingAppointment $appointment,
        BansalApiClient $bansalApiClient,
        string $appointmentDate,
        string $appointmentTime,
        string $meetingType,
        string $preferredLanguage
    ): ?int {
        try {
            // Map meeting_type from database format to API format
            $meetingTypeForApi = match ($meetingType) {
                'video' => 'video-call',
                'in_person' => 'in-person',
                'phone' => 'phone',
                default => 'in-person'
            };

            // Determine specific_service from enquiry_type or service_type
            $specificService = $this->determineSpecificService($appointment);

            // Build payload for createAppointment API
            $payload = [
                'full_name' => $appointment->client_name,
                'email' => $appointment->client_email,
                'phone' => $appointment->client_phone ?? '',
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'appointment_datetime' => $appointmentDate.' '.$appointmentTime.':00',
                'duration_minutes' => $appointment->duration_minutes ?? 15,
                'location' => $appointment->location ?? 'melbourne',
                'meeting_type' => $meetingTypeForApi,
                'preferred_language' => $preferredLanguage,
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

            $apiResponse = $bansalApiClient->createAppointment($payload);

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
        } catch (\Exception $e) {
            Log::warning('Failed to create appointment via API', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
            ]);

            throw $e;
        }
    }

    /**
     * Determine specific_service for API based on appointment data
     */
    private function determineSpecificService(BookingAppointment $appointment): string
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

        // Default fallback based on is_paid
        return $appointment->is_paid ? 'paid-consultation' : 'consultation';
    }

    private function mapMeetingType(string $meetingType): string
    {
        // Normalize: convert to lowercase and replace spaces/hyphens with underscores
        $normalized = strtolower(trim($meetingType));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'in_person', 'inperson', 'in-person', 'in person', 'office', 'onsite' => 'in_person',
            'phone', 'telephone', 'call', 'phone_call' => 'phone',
            'video', 'videocall', 'video_call', 'video-call', 'zoom', 'online' => 'video',
            default => 'in_person' // Default fallback
        };
    }

    /**
     * Process Stripe payment for appointment
     *
     * DEPRECATED (Option C): Use POST /appointments/record-payment with payment_intent_id instead.
     * Frontend should create and confirm the PaymentIntent with Stripe, then call record-payment.
     *
     * @return JsonResponse
     */
    public function processAppointmentPayment(Request $request)
    {
        // Option C: Frontend owns PaymentIntent; backend only records. This endpoint is deprecated.
        return $this->sendError(
            'This endpoint is deprecated. Use POST /appointments/record-payment with appointment_id and payment_intent_id after confirming payment on the frontend.',
            ['use_endpoint' => '/appointments/record-payment'],
            410
        );

        /*
        // --- COMMENTED OUT: Original process-payment logic (Option C: use record-payment instead) ---
        try {
            $user = $request->user();

            if (!$user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer|exists:booking_appointments,id',
                'payment_method_id' => 'required|string', // Stripe payment method ID from frontend
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: ' . $validator->errors()->first(), $validator->errors(), 422);
            }

            // Find appointment that belongs to the authenticated client
            $appointment = BookingAppointment::where('id', $request->appointment_id)
                ->where('client_id', $user->id)
                ->first();

            if (!$appointment) {
                return $this->sendError('Appointment not found or does not belong to you', [], 404);
            }

            // Validate appointment requires payment (service_id 2 or 3 means paid service)
            // From addAppointment logic: service_id mapping is 1=Paid, 2=Free, 3=Paid Overseas
            // So service_id 1 and 3 require payment
            if (!in_array($appointment->service_id, [1, 3])) {
                return $this->sendError('This appointment does not require payment', [], 422);
            }

            // Check if appointment is already paid (both is_paid and payment_status completed)
            if ($appointment->is_paid && $appointment->payment_status === 'completed') {
                return $this->sendError('This appointment has already been paid', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'cancelled') {
                return $this->sendError('This appointment has already been cancelled', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'completed') {
                return $this->sendError('This appointment has already been completed', [], 422);
            }

            // Verify appointment amount
            $expectedAmount = 150.00; // As per requirements
            $appointmentAmount = (float) ($appointment->final_amount ?? $appointment->amount);

            if ($appointmentAmount != $expectedAmount) {
                Log::warning('Appointment amount mismatch', [
                    'appointment_id' => $appointment->id,
                    'expected' => $expectedAmount,
                    'actual' => $appointmentAmount
                ]);
                // Still proceed but log the warning
            }

            // Process payment using Stripe service
            $stripeService = app(StripePaymentService::class);

            $metadata = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            $result = $stripeService->processPayment(
                $appointment,
                $request->payment_method_id,
                $metadata
            );

            // If payment requires additional action (3D Secure)
            if (isset($result['data']['requires_action']) && $result['data']['requires_action']) {
                return response()->json([
                    'success' => false,
                    'requires_action' => true,
                    'message' => $result['message'],
                    'data' => [
                        'payment_intent_id' => $result['data']['payment_intent_id'],
                        'client_secret' => $result['data']['client_secret'],
                    ]
                ], 200);
            }

            // If payment failed
            if (!$result['success']) {
                return $this->sendError($result['message'], $result['data'], 422);
            }

            // Payment succeeded - sync with Bansal API if applicable
            $syncError = null;
            if ($appointment->bansal_appointment_id) {
                try {
                    $bansalApiClient = app(BansalApiClient::class);

                    // Update appointment status on Bansal website
                    Log::info('Payment successful - should sync with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $appointment->bansal_appointment_id,
                    ]);
                } catch (\Exception $e) {
                    $syncError = $e->getMessage();
                    Log::error('Failed to sync payment status with Bansal API', [
                        'appointment_id' => $appointment->id,
                        'error' => $syncError,
                    ]);
                }
            }

            // Refresh appointment to get updated data
            $appointment->refresh();

            // Build success response
            $message = 'Payment processed successfully';
            if ($syncError) {
                $message .= '. Note: Payment completed but sync with website failed.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $result['data']['payment_id'],
                    'transaction_id' => $result['data']['payment_intent_id'],
                    'charge_id' => $result['data']['charge_id'],
                    'amount' => $result['data']['amount'],
                    'currency' => $result['data']['currency'],
                    'status' => 'paid',
                    'receipt_url' => $result['data']['receipt_url'] ?? null,
                    'paid_at' => $result['data']['paid_at'],
                    'appointment' => $this->formatAppointmentData($appointment),
                ],
                'bansal_synced' => !$syncError
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Process Payment API Error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('An error occurred while processing payment: ' . $e->getMessage(), [], 500);
        }
        */
    }

    /**
     * Record payment by PaymentIntent ID (Option C: frontend owns PaymentIntent, backend only records).
     * Call this after the frontend has created and confirmed the PaymentIntent with Stripe.
     *
     * @return JsonResponse
     */
    public function recordAppointmentPayment(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer|exists:booking_appointments,id',
                'payment_intent_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            $appointment = BookingAppointment::where('id', $request->appointment_id)
                ->where('client_id', $user->id)
                ->first();

            if (! $appointment) {
                return $this->sendError('Appointment not found or does not belong to you', [], 404);
            }

            if (! in_array($appointment->service_id, [1, 3])) {
                return $this->sendError('This appointment does not require payment', [], 422);
            }

            if ($appointment->is_paid && $appointment->payment_status === 'completed') {
                return $this->sendError('This appointment has already been paid', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'cancelled') {
                return $this->sendError('This appointment has already been cancelled', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'completed') {
                return $this->sendError('This appointment has already been completed', [], 422);
            }

            $stripeService = app(StripePaymentService::class);
            $metadata = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            $result = $stripeService->recordPaymentByIntent(
                $appointment,
                $request->payment_intent_id,
                $metadata
            );

            if (! $result['success']) {
                return $this->sendError($result['message'], $result['data'] ?? [], 422);
            }

            $syncError = $this->syncAppointmentPaidWithBansal($appointment);

            $appointment->refresh();

            // Send confirmation email on successful payment
            if (! empty($appointment->client_email)) {
                try {
                    $emailDetails = [
                        'appointment_id' => $appointment->id,
                        'client_name' => $appointment->client_name,
                        'appointment_datetime' => $appointment->appointment_datetime,
                        'timeslot_full' => $appointment->timeslot_full,
                        'location' => $appointment->location,
                        'service_type' => $appointment->service_type,
                        'meeting_type' => $appointment->meeting_type,
                        'admin_notes' => $appointment->admin_notes ?? null,
                    ];
                    Mail::mailer('sendgrid')->to($appointment->client_email)->send(new AppointmentDetailedConfirmation($emailDetails));
                    $appointment->update([
                        'confirmation_email_sent' => true,
                        'confirmation_email_sent_at' => now(),
                    ]);
                    Log::info('Appointment confirmation email sent after payment', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                    ]);
                } catch (\Exception $mailEx) {
                    Log::error('Failed to send appointment confirmation email after payment', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                        'error' => $mailEx->getMessage(),
                    ]);
                }
            }

            $message = $result['message'];
            if ($syncError) {
                $message .= ' Note: Payment completed but sync with website failed.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $result['data']['payment_id'],
                    'transaction_id' => $result['data']['payment_intent_id'],
                    'charge_id' => $result['data']['charge_id'],
                    'amount' => $result['data']['amount'],
                    'currency' => $result['data']['currency'],
                    'status' => 'paid',
                    'receipt_url' => $result['data']['receipt_url'] ?? null,
                    'paid_at' => $result['data']['paid_at'],
                    'appointment' => $this->formatAppointmentData($appointment),
                ],
                'bansal_synced' => ! $syncError,
            ], 200);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Record Payment API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred while recording payment: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Record a wallet (GPay/ApplePay) payment for an appointment.
     *
     * The PaymentIntent id is verified against Stripe through the same service the card
     * paths use, so status, amount and appointment binding are all checked. A token that
     * is not a PaymentIntent id cannot be verified and is rejected, unless
     * services.stripe.enforce_wallet_payment_verification is turned off to roll back to
     * the unverified behaviour for an older app.
     *
     * @param  string  $paymentType  gpay | applepay
     * @return array{success: bool, message: string, payment: AppointmentPayment|null}
     */
    private function recordWalletPayment(BookingAppointment $appointment, string $paymentIntentId, string $paymentType, Request $request): array
    {
        $metadata = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if (preg_match('/^pi_[A-Za-z0-9_]+$/', $paymentIntentId)) {
            $result = app(StripePaymentService::class)->recordPaymentByIntent($appointment, $paymentIntentId, $metadata);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'payment' => null,
                ];
            }

            $payment = AppointmentPayment::find($result['data']['payment_id'] ?? null)
                ?? AppointmentPayment::where('appointment_id', $appointment->id)->orderByDesc('id')->first();

            // Keep the wallet type in the audit trail without dropping the Stripe payload.
            if ($payment) {
                $transactionData = $payment->transaction_data ?? [];
                $transactionData['payment_type'] = $paymentType;
                $payment->update(['transaction_data' => $transactionData]);
            }

            return [
                'success' => true,
                'message' => $result['message'],
                'payment' => $payment,
            ];
        }

        if (config('services.stripe.enforce_wallet_payment_verification', true)) {
            Log::error('Wallet payment rejected: token is not a Stripe PaymentIntent id', [
                'appointment_id' => $appointment->id,
                'payment_type' => $paymentType,
                'token_prefix' => substr($paymentIntentId, 0, 8),
            ]);

            return [
                'success' => false,
                'message' => 'Payment could not be verified with Stripe. A confirmed PaymentIntent id is required.',
                'payment' => null,
            ];
        }

        Log::warning('Wallet payment recorded without Stripe verification', [
            'appointment_id' => $appointment->id,
            'payment_type' => $paymentType,
        ]);

        $appointmentAmount = (float) ($appointment->final_amount ?? $appointment->amount);

        DB::beginTransaction();
        try {
            $payment = AppointmentPayment::updateOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'payment_gateway' => 'stripe',
                    'transaction_id' => $paymentIntentId,
                    'charge_id' => null,
                    'customer_id' => null,
                    'payment_method_id' => null,
                    'amount' => $appointmentAmount,
                    'currency' => 'AUD',
                    'status' => 'succeeded',
                    'error_message' => null,
                    'transaction_data' => [
                        'payment_type' => $paymentType,
                        'payment_intent_id' => $paymentIntentId,
                        'stripe_verified' => false,
                    ],
                    'receipt_url' => null,
                    'client_ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'processed_at' => now(),
                ]
            );

            $appointment->update([
                'status' => 'paid',
                'is_paid' => true,
                'payment_status' => 'completed',
                'payment_method' => 'stripe',
                'paid_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => true,
            'message' => 'Payment recorded successfully',
            'payment' => $payment,
        ];
    }

    /**
     * Record payment with login for wallet methods (GPay/ApplePay).
     *
     * Same as recordAppointmentPayment plus:
     * - payment_type: gpay | applepay
     *
     * @return JsonResponse
     */
    public function recordAppointmentPaymentWallet(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer|exists:booking_appointments,id',
                'payment_intent_id' => 'required|string',
                'payment_type' => 'required|string|in:gpay,applepay',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            $appointment = BookingAppointment::where('id', $request->appointment_id)
                ->where('client_id', $user->id)
                ->first();

            if (! $appointment) {
                return $this->sendError('Appointment not found or does not belong to you', [], 404);
            }

            if (! in_array($appointment->service_id, [1, 3])) {
                return $this->sendError('This appointment does not require payment', [], 422);
            }

            if ($appointment->is_paid && $appointment->payment_status === 'completed') {
                return $this->sendError('This appointment has already been paid', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'cancelled') {
                return $this->sendError('This appointment has already been cancelled', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'completed') {
                return $this->sendError('This appointment has already been completed', [], 422);
            }

            $paymentType = strtolower((string) $request->payment_type);

            $walletResult = $this->recordWalletPayment(
                $appointment,
                (string) $request->payment_intent_id,
                $paymentType,
                $request
            );

            if (! $walletResult['success']) {
                return $this->sendError($walletResult['message'], [], 422);
            }

            $payment = $walletResult['payment'];

            $syncError = $this->syncAppointmentPaidWithBansal($appointment);

            $appointment->refresh();

            // Send confirmation email on successful payment
            if (! empty($appointment->client_email)) {
                try {
                    $emailDetails = [
                        'appointment_id' => $appointment->id,
                        'client_name' => $appointment->client_name,
                        'appointment_datetime' => $appointment->appointment_datetime,
                        'timeslot_full' => $appointment->timeslot_full,
                        'location' => $appointment->location,
                        'service_type' => $appointment->service_type,
                        'meeting_type' => $appointment->meeting_type,
                        'admin_notes' => $appointment->admin_notes ?? null,
                    ];
                    Mail::mailer('sendgrid')->to($appointment->client_email)->send(new AppointmentDetailedConfirmation($emailDetails));
                    $appointment->update([
                        'confirmation_email_sent' => true,
                        'confirmation_email_sent_at' => now(),
                    ]);
                    Log::info('Appointment confirmation email sent after wallet payment', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                        'payment_type' => $paymentType,
                    ]);
                } catch (\Exception $mailEx) {
                    Log::error('Failed to send appointment confirmation email after wallet payment', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                        'error' => $mailEx->getMessage(),
                    ]);
                }
            }

            $message = 'Payment recorded successfully';
            if ($syncError) {
                $message .= ' Note: Payment completed but sync with website failed.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $payment?->id,
                    'transaction_id' => $payment?->transaction_id,
                    'charge_id' => $payment?->charge_id,
                    'amount' => $payment?->amount,
                    'currency' => $payment?->currency,
                    'status' => 'paid',
                    'payment_type' => $paymentType,
                    'receipt_url' => $payment?->receipt_url,
                    'paid_at' => $appointment->paid_at ? $appointment->paid_at->toIso8601String() : null,
                    'appointment' => $this->formatAppointmentData($appointment),
                ],
                'bansal_synced' => ! $syncError,
            ], 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Record Payment Wallet API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred while recording payment: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Record payment without login (public API)
     *
     * For guest users who booked via add-appointment-without-login.
     * Finds appointment by appointment_id and client via client_id.
     * No authentication required.
     *
     * @return JsonResponse
     */
    public function recordAppointmentPaymentWithoutLogin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer|exists:booking_appointments,id',
                'payment_intent_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            $appointment = BookingAppointment::find($request->appointment_id);

            if (! $appointment) {
                return $this->sendError('Appointment not found', [], 404);
            }

            if (! in_array($appointment->service_id, [1, 3])) {
                return $this->sendError('This appointment does not require payment', [], 422);
            }

            if ($appointment->is_paid && $appointment->payment_status === 'completed') {
                return $this->sendError('This appointment has already been paid', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'cancelled') {
                return $this->sendError('This appointment has already been cancelled', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'completed') {
                return $this->sendError('This appointment has already been completed', [], 422);
            }

            $stripeService = app(StripePaymentService::class);
            $metadata = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];

            $result = $stripeService->recordPaymentByIntent(
                $appointment,
                $request->payment_intent_id,
                $metadata
            );

            if (! $result['success']) {
                return $this->sendError($result['message'], $result['data'] ?? [], 422);
            }

            $syncError = $this->syncAppointmentPaidWithBansal($appointment);

            $appointment->refresh();

            // Send confirmation email on successful payment
            if (! empty($appointment->client_email)) {
                try {
                    $emailDetails = [
                        'appointment_id' => $appointment->id,
                        'client_name' => $appointment->client_name,
                        'appointment_datetime' => $appointment->appointment_datetime,
                        'timeslot_full' => $appointment->timeslot_full,
                        'location' => $appointment->location,
                        'service_type' => $appointment->service_type,
                        'meeting_type' => $appointment->meeting_type,
                        'admin_notes' => $appointment->admin_notes ?? null,
                    ];
                    Mail::mailer('sendgrid')->to($appointment->client_email)->send(new AppointmentDetailedConfirmation($emailDetails));
                    $appointment->update([
                        'confirmation_email_sent' => true,
                        'confirmation_email_sent_at' => now(),
                    ]);
                    Log::info('Appointment confirmation email sent after payment (without login)', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                    ]);
                } catch (\Exception $mailEx) {
                    Log::error('Failed to send appointment confirmation email after payment (without login)', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                        'error' => $mailEx->getMessage(),
                    ]);
                }
            }

            $message = $result['message'];
            if ($syncError) {
                $message .= ' Note: Payment completed but sync with website failed.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $result['data']['payment_id'],
                    'transaction_id' => $result['data']['payment_intent_id'],
                    'charge_id' => $result['data']['charge_id'],
                    'amount' => $result['data']['amount'],
                    'currency' => $result['data']['currency'],
                    'status' => 'paid',
                    'receipt_url' => $result['data']['receipt_url'] ?? null,
                    'paid_at' => $result['data']['paid_at'],
                    'appointment' => $this->formatAppointmentData($appointment),
                ],
                'bansal_synced' => ! $syncError,
            ], 200);

        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Record Payment Without Login API Error: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred while recording payment: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Record payment without login for wallet methods (public API).
     *
     * Uses the same parameters as recordAppointmentPaymentWithoutLogin plus:
     * - payment_type: gpay | applepay
     *
     * @return JsonResponse
     */
    public function recordAppointmentPaymentWithoutLoginWallet(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer|exists:booking_appointments,id',
                'payment_intent_id' => 'required|string',
                'payment_type' => 'required|string|in:gpay,applepay',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed: '.$validator->errors()->first(), $validator->errors(), 422);
            }

            $appointment = BookingAppointment::find($request->appointment_id);

            if (! $appointment) {
                return $this->sendError('Appointment not found', [], 404);
            }

            if (! in_array($appointment->service_id, [1, 3])) {
                return $this->sendError('This appointment does not require payment', [], 422);
            }

            if ($appointment->is_paid && $appointment->payment_status === 'completed') {
                return $this->sendError('This appointment has already been paid', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'cancelled') {
                return $this->sendError('This appointment has already been cancelled', [], 422);
            }

            if ($appointment->is_paid && $appointment->status === 'completed') {
                return $this->sendError('This appointment has already been completed', [], 422);
            }

            $paymentType = strtolower((string) $request->payment_type);

            $walletResult = $this->recordWalletPayment(
                $appointment,
                (string) $request->payment_intent_id,
                $paymentType,
                $request
            );

            if (! $walletResult['success']) {
                return $this->sendError($walletResult['message'], [], 422);
            }

            $payment = $walletResult['payment'];

            $syncError = $this->syncAppointmentPaidWithBansal($appointment);

            $appointment->refresh();

            // Send confirmation email on successful payment
            if (! empty($appointment->client_email)) {
                try {
                    $emailDetails = [
                        'appointment_id' => $appointment->id,
                        'client_name' => $appointment->client_name,
                        'appointment_datetime' => $appointment->appointment_datetime,
                        'timeslot_full' => $appointment->timeslot_full,
                        'location' => $appointment->location,
                        'service_type' => $appointment->service_type,
                        'meeting_type' => $appointment->meeting_type,
                        'admin_notes' => $appointment->admin_notes ?? null,
                    ];
                    Mail::mailer('sendgrid')->to($appointment->client_email)->send(new AppointmentDetailedConfirmation($emailDetails));
                    $appointment->update([
                        'confirmation_email_sent' => true,
                        'confirmation_email_sent_at' => now(),
                    ]);
                    Log::info('Appointment confirmation email sent after wallet payment (without login)', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                        'payment_type' => $paymentType,
                    ]);
                } catch (\Exception $mailEx) {
                    Log::error('Failed to send appointment confirmation email after wallet payment (without login)', [
                        'appointment_id' => $appointment->id,
                        'email' => $appointment->client_email,
                        'error' => $mailEx->getMessage(),
                    ]);
                }
            }

            $message = 'Payment recorded successfully';
            if ($syncError) {
                $message .= ' Note: Payment completed but sync with website failed.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'appointment_id' => $appointment->id,
                    'payment_id' => $payment?->id,
                    'transaction_id' => $payment?->transaction_id,
                    'charge_id' => $payment?->charge_id,
                    'amount' => $payment?->amount,
                    'currency' => $payment?->currency,
                    'status' => 'paid',
                    'payment_type' => $paymentType,
                    'receipt_url' => $payment?->receipt_url,
                    'paid_at' => $appointment->paid_at ? $appointment->paid_at->toIso8601String() : null,
                    'appointment' => $this->formatAppointmentData($appointment),
                ],
                'bansal_synced' => ! $syncError,
            ], 200);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Record Payment Without Login Wallet API Error: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred while recording payment: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get payment history for an appointment
     *
     * Returns all payment attempts for a specific appointment.
     *
     * @param  int  $appointmentId
     * @return JsonResponse
     */
    public function getPaymentHistory(Request $request, $appointmentId)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->sendError('Unauthenticated', [], 401);
            }

            // Find appointment that belongs to the authenticated client
            $appointment = BookingAppointment::where('id', $appointmentId)
                ->where('client_id', $user->id)
                ->first();

            if (! $appointment) {
                return $this->sendError('Appointment not found', [], 404);
            }

            // Get payment history
            $payments = AppointmentPayment::where('appointment_id', $appointmentId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'charge_id' => $payment->charge_id,
                        'amount' => number_format((float) $payment->amount, 2, '.', ''),
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'payment_gateway' => $payment->payment_gateway,
                        'receipt_url' => $payment->receipt_url,
                        'error_message' => $payment->error_message,
                        'processed_at' => $payment->processed_at ? $payment->processed_at->toIso8601String() : null,
                        'created_at' => $payment->created_at->toIso8601String(),
                    ];
                });

            return $this->sendResponse([
                'appointment_id' => $appointment->id,
                'payments' => $payments,
                'total_attempts' => $payments->count(),
            ], 'Payment history retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Get Payment History API Error: '.$e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'appointment_id' => $appointmentId ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->sendError('An error occurred: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Push paid status to Bansal after CRM payment succeeds.
     * Never throws — payment success must not depend on website sync.
     *
     * @return string|null Sync error message, or null on success / skip
     */
    protected function syncAppointmentPaidWithBansal(BookingAppointment $appointment): ?string
    {
        if (empty($appointment->bansal_appointment_id)) {
            return null;
        }

        try {
            $recoveryService = app(BansalAppointmentRecoveryService::class);
            $result = $recoveryService->syncStatus($appointment, 'paid');

            if ($result['synced']) {
                if ($result['bansal_appointment_id'] !== null) {
                    $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
                }

                $appointment->forceFill([
                    'last_synced_at' => now(),
                    'sync_status' => 'synced',
                    'sync_error' => null,
                ])->save();

                Log::info('Payment status synced with Bansal API', [
                    'appointment_id' => $appointment->id,
                    'bansal_appointment_id' => $appointment->bansal_appointment_id,
                ]);

                return null;
            }

            $syncError = $result['error'] ?? 'Unknown Bansal sync error';

            $appointment->forceFill([
                'sync_status' => 'error',
                'sync_error' => $syncError,
            ])->save();

            Log::error('Failed to sync payment status with Bansal API', [
                'appointment_id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'error' => $syncError,
            ]);

            return $syncError;
        } catch (\Exception $e) {
            $syncError = $e->getMessage();
            Log::error('Failed to sync payment status with Bansal API', [
                'appointment_id' => $appointment->id,
                'error' => $syncError,
            ]);

            return $syncError;
        }
    }
}
