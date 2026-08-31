<?php

namespace App\Traits;

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
use App\Services\MergeClientRecordsService;
use App\Support\ActionTaskGroup;
use App\Support\AppointmentActivityDescription;
use App\Support\BookingAppointmentStatus;
use App\Support\NoteDescriptionHtml;
use App\Support\StaffClientVisibility;
use App\Support\WorkflowAssignment;
use App\Services\LegalCrm\LegalCrmApiClient;

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
use App\Support\AppointmentBookingWindow;
use App\Support\AppointmentSlotOverwrite;
use App\Support\BansalSchedulingServiceType;

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
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CostAssignmentForm;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use App\Models\ClientEoiReference;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Mail\HubdocInvoiceMail;
use App\Services\MatterEmailBodyCleanupService;
use App\Services\EmailLogListService;
use App\Services\Sms\UnifiedSmsManager;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\ClientExportService;
use App\Services\ClientLeadListExportService;
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

trait ClientAppointments
{
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

            // Hidden service_id must match visible radioGroup (calendar uses radio; submit used to send stale service_id)
            if ($request->has('radioGroup')) {
                $radioServiceId = (int) $request->input('radioGroup');
                if (in_array($radioServiceId, [1, 2, 3], true) && (int) $requestData['service_id'] !== $radioServiceId) {
                    Log::warning('Appointment book service_id mismatch; using radioGroup', [
                        'service_id' => $requestData['service_id'],
                        'radioGroup' => $radioServiceId,
                        'client_id' => $requestData['client_id'] ?? null,
                    ]);
                    $requestData['service_id'] = $radioServiceId;
                }
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

            if (AppointmentBookingWindow::isOnOrBeforeToday($appointmentDateTime)) {
                return response()->json([
                    'status' => false,
                    'message' => AppointmentBookingWindow::SAME_DAY_MESSAGE,
                    'errors' => ['appoint_date' => [AppointmentBookingWindow::SAME_DAY_MESSAGE]],
                ], 422);
            }

            // Calculate duration based on service (fallback when API duration not sent)
            // Form service_id: 1 = Free → 15 min; 2/3 = Paid → 30 min
            // Prefer api_duration_minutes from getDateTimeBackend so Education etc. match slot interval (e.g. 60)
            $durationMinutes = ((int) $requestData['service_id'] === 1) ? 15 : 30;
            $apiDurationMinutes = (int) ($requestData['api_duration_minutes'] ?? 0);
            if ($apiDurationMinutes >= 5 && $apiDurationMinutes <= 180) {
                $durationMinutes = $apiDurationMinutes;
            }

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
                'slot_overwrite' => AppointmentSlotOverwrite::fromRequest($requestData),
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
                    $confirmationEmailSent = $notificationService->sendBookingConfirmationEmail($appointment);
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
        ActivitiesLog::create(
            AppointmentActivityDescription::buildActivityLogPayload(
                $appointment,
                (int) Auth::id(),
                $serviceId,
                $noeId
            )
        );
    }

}
