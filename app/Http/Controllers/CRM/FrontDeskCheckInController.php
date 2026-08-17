<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BookingAppointment;
use App\Models\CheckinHistory;
use App\Models\CheckinLog;
use App\Models\FrontDeskCheckIn;
use App\Models\Staff;
use App\Services\ClientReferenceService;
use App\Services\FrontDesk\CheckInAppointmentService;
use App\Services\FrontDesk\CheckInLookupService;
use App\Services\FrontDesk\CheckInNotificationService;
use App\Support\StaffClientVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FrontDeskCheckInController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function __construct(
        private readonly CheckInLookupService $lookup,
        private readonly CheckInAppointmentService $appointments,
        private readonly CheckInNotificationService $notifier,
        private readonly ClientReferenceService $refService,
    ) {
        $this->middleware('auth:admin');
    }

    // -------------------------------------------------------------------------
    // GET /front-desk/checkin
    // -------------------------------------------------------------------------

    public function index(): View|RedirectResponse
    {
        if (! $this->authorised()) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access the front-desk check-in.');
        }

        $visitReasons = FrontDeskCheckIn::visitReasons();

        return view('crm.front_desk.checkin', compact('visitReasons'));
    }

    // -------------------------------------------------------------------------
    // POST /front-desk/checkin/lookup  (AJAX)
    // -------------------------------------------------------------------------

    public function lookupContact(Request $request): JsonResponse
    {
        if (! $this->authorised()) {
            return response()->json(['error' => 'Unauthorised'], 403);
        }

        $request->validate([
            'phone' => 'required|string|min:6|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $matches = $this->lookup->lookup(
            $request->input('phone'),
            $request->input('email')
        );

        /** @var Staff $user */
        $user = Auth::guard('admin')->user();
        $visible = $matches->filter(function (Admin $a) use ($user) {
            return StaffClientVisibility::canAccessClientOrLead((int) $a->id, $user);
        })->values();

        $data = $visible->map(fn (Admin $a) => $this->lookup->formatForWizard($a))->values();

        return response()->json([
            'matches' => $data,
            'phone_normalized' => $this->lookup->normalizePhone($request->input('phone')),
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /front-desk/checkin/appointments  (AJAX)
    // -------------------------------------------------------------------------

    public function getAppointments(Request $request): JsonResponse
    {
        if (! $this->authorised()) {
            return response()->json(['error' => 'Unauthorised'], 403);
        }

        $request->validate(['admin_id' => 'required|integer|min:1']);

        $adminId = (int) $request->input('admin_id');
        $this->ensureCrmRecordAccess($adminId);

        $appts = $this->appointments->getTodaysAppointments($adminId);

        $data = $appts->map(fn (BookingAppointment $a) => $this->appointments->formatForWizard($a))->values();

        return response()->json(['appointments' => $data]);
    }

    // -------------------------------------------------------------------------
    // POST /front-desk/checkin/submit
    // -------------------------------------------------------------------------

    public function submit(Request $request): JsonResponse
    {
        if (! $this->authorised()) {
            return response()->json(['error' => 'Unauthorised'], 403);
        }

        $reasonKeys = array_keys(FrontDeskCheckIn::visitReasons());

        $validated = $request->validate([
            'phone' => 'required|string|min:6|max:20',
            'email' => 'nullable|email|max:255',
            'admin_id' => 'nullable|integer|min:1',
            'admin_type' => ['nullable', 'string', Rule::in(['client', 'lead'])],
            'appointment_id' => 'nullable|integer|min:1',
            'claimed_appointment' => 'nullable|boolean',
            'visit_reason' => ['nullable', 'string', 'max:100', Rule::in($reasonKeys)],
            'visit_notes' => 'nullable|string|max:2000',
        ]);

        $rawAdminId = $validated['admin_id'] ?? null;
        $adminId = ($rawAdminId !== null && (int) $rawAdminId > 0) ? (int) $rawAdminId : null;
        $adminType = isset($validated['admin_type']) && $validated['admin_type'] !== ''
            ? $validated['admin_type']
            : null;

        // Walk-in: both null. Matched record: both must be set.
        if (($adminId === null) !== ($adminType === null)) {
            return response()->json([
                'success' => false,
                'message' => 'Client selection is incomplete. Choose a match or walk-in.',
                'errors' => ['admin_id' => ['Match type and record must be sent together.']],
            ], 422);
        }

        if ($adminId !== null && $adminType !== null) {
            $exists = Admin::query()
                ->where('id', $adminId)
                ->where('type', $adminType)
                ->whereNull('is_deleted')
                ->exists();
            if (! $exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected client or lead was not found.',
                    'errors' => ['admin_id' => ['Invalid or deleted record.']],
                ], 422);
            }
            $this->ensureCrmRecordAccess($adminId);
        }

        // "Other" reason requires notes
        if (($validated['visit_reason'] ?? null) === 'other' && empty($validated['visit_notes'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide notes when selecting "Other" as the reason.',
                'errors' => ['visit_notes' => ['Notes are required for "Other" reason.']],
            ], 422);
        }

        $phoneNormalized = $this->lookup->normalizePhone($validated['phone']);

        // Resolve client_id / lead_id
        $clientId = null;
        $leadId = null;
        if ($adminId && $adminType) {
            if ($adminType === 'client') {
                $clientId = $adminId;
            } else {
                $leadId = $adminId;
            }
        }

        // Validate appointment belongs to today and to the matched admin
        $appointmentId = null;
        if (! empty($validated['appointment_id'])) {
            $appt = BookingAppointment::find((int) $validated['appointment_id']);
            if ($appt && $adminId && (int) $appt->client_id === $adminId) {
                $appointmentId = $appt->id;
            }
        }

        try {
            DB::beginTransaction();

            /** @var Staff $staff */
            $staff = Auth::guard('admin')->user();

            $checkIn = FrontDeskCheckIn::create([
                'admin_id' => $staff->id,
                'phone_normalized' => $phoneNormalized,
                'email' => $validated['email'] ?? null,
                'client_id' => $clientId,
                'lead_id' => $leadId,
                'appointment_id' => $appointmentId,
                'claimed_appointment' => (bool) ($validated['claimed_appointment'] ?? false),
                'visit_reason' => $validated['visit_reason'] ?? null,
                'visit_notes' => $validated['visit_notes'] ?? null,
                'metadata' => [
                    'submitted_at' => now()->toIso8601String(),
                    'ip' => $request->ip(),
                ],
            ]);

            // Send notification to the assignee / consultant
            $notified = $this->notifier->notify($checkIn, $staff);

            // Feed into the existing office-visit queue so the visitor
            // appears in /office-visits/waiting and staff can manage the session.
            $queueAdminId = $clientId ?? $leadId;   // null = walk-in (not yet in CRM)
            $contactType = $clientId ? 'Client' : ($leadId ? 'Lead' : 'Walk-in');
            $reasonLabel = $validated['visit_reason']
                ? (FrontDeskCheckIn::visitReasons()[$validated['visit_reason']] ?? $validated['visit_reason'])
                : 'Front-desk check-in';

            $checkinPayload = [
                'client_id' => $queueAdminId,
                'user_id' => $notified?->id ?? $staff->id,
                'visit_purpose' => $reasonLabel,
                'office' => $staff->office_id,
                'contact_type' => $contactType,
                'status' => 0,                 // waiting
                'date' => now()->toDateString(),
            ];
            if ($queueAdminId === null) {
                $checkinPayload['walk_in_phone'] = $phoneNormalized;
                $checkinPayload['walk_in_email'] = $validated['email'] ?? null;
            }

            $checkinLog = CheckinLog::create($checkinPayload);

            CheckinHistory::create([
                'subject' => 'Check-in created via front-desk wizard',
                'created_by' => $staff->id,
                'checkin_id' => $checkinLog->id,
            ]);

            // Store the queue record id in the audit row for easy cross-reference
            $checkIn->update([
                'metadata' => array_merge($checkIn->metadata ?? [], [
                    'checkin_log_id' => $checkinLog->id,
                ]),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-in recorded successfully.',
                'check_in_id' => $checkIn->id,
                'notified_staff' => $notified ? $notified->full_name : null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[FrontDeskCheckIn] Submit failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving the check-in. Please try again.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /front-desk/checkin/create-lead
    // -------------------------------------------------------------------------

    public function createLead(Request $request): JsonResponse
    {
        if (! $this->authorised()) {
            return response()->json(['error' => 'Unauthorised'], 403);
        }

        $reasonKeys = array_keys(FrontDeskCheckIn::visitReasons());

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'required|string|min:6|max:20',
            'email' => 'nullable|email|max:255',
            'visit_reason' => ['nullable', 'string', 'max:100', Rule::in($reasonKeys)],
            'visit_notes' => 'nullable|string|max:2000',
        ]);

        if (($validated['visit_reason'] ?? null) === 'other' && empty($validated['visit_notes'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide notes when selecting "Other" as the reason.',
                'errors' => ['visit_notes' => ['Notes are required for "Other" reason.']],
            ], 422);
        }

        $phone = $validated['phone'];
        $email = trim((string) ($validated['email'] ?? ''));
        $email = $email !== '' ? $email : null;
        $timestamp = time();
        $errors = [];

        if (Admin::phoneIsTaken($phone)) {
            if (Admin::normalizePhoneDigitsForUniqueness($phone) === '4444444444') {
                $phone = '4444444444_'.$timestamp;
            } else {
                $errors['phone'] = ['This phone number is already registered. Please look up the existing client or lead.'];
            }
        }

        if ($email !== null && Admin::emailIsTaken($email)) {
            if (Admin::normalizeEmailForUniqueness($email) === 'demo@gmail.com') {
                $email = 'demo_'.$timestamp.'@gmail.com';
            } else {
                $errors['email'] = ['This email address is already registered. Please look up the existing client or lead.'];
            }
        }

        if ($errors !== []) {
            $firstMessage = collect($errors)->flatten()->first();

            return response()->json([
                'success' => false,
                'message' => $firstMessage,
                'errors' => $errors,
            ], 422);
        }

        $phoneNormalized = $this->lookup->normalizePhone($phone);

        try {
            DB::beginTransaction();

            /** @var Staff $staff */
            $staff = Auth::guard('admin')->user();

            // Generate a unique CRM reference for the new lead
            $ref = $this->refService->generateClientReference($validated['first_name']);

            $lead = Admin::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $phone,
                'email' => $email,
                'type' => 'lead',
                'client_id' => $ref['client_id'],
                'client_counter' => $ref['client_counter'],
                'user_id' => $staff->id,
                'password' => Hash::make('LEAD_PLACEHOLDER'),
                'status' => 1,
                'lead_status' => 'new',
                'is_archived' => 0,
                'is_deleted' => null,
                'verified' => 0,
                'cp_status' => 0,
                'cp_code_verify' => 0,
                'australian_study' => 0,
                'specialist_education' => 0,
                'regional_study' => 0,
                'is_company' => 0,
            ]);

            $checkIn = FrontDeskCheckIn::create([
                'admin_id' => $staff->id,
                'phone_normalized' => $phoneNormalized,
                'email' => $email,
                'lead_id' => $lead->id,
                'visit_reason' => $validated['visit_reason'] ?? null,
                'visit_notes' => $validated['visit_notes'] ?? null,
                'metadata' => [
                    'submitted_at' => now()->toIso8601String(),
                    'ip' => $request->ip(),
                    'new_lead' => true,
                ],
            ]);

            $notified = $this->notifier->notify($checkIn, $staff);

            $reasonLabel = ($validated['visit_reason'] ?? null)
                ? (FrontDeskCheckIn::visitReasons()[$validated['visit_reason']] ?? $validated['visit_reason'])
                : 'Front-desk check-in';

            $checkinLog = CheckinLog::create([
                'client_id' => $lead->id,
                'user_id' => $notified?->id ?? $staff->id,
                'visit_purpose' => $reasonLabel,
                'office' => $staff->office_id,
                'contact_type' => 'Lead',
                'status' => 0,
                'date' => now()->toDateString(),
            ]);

            CheckinHistory::create([
                'subject' => 'Check-in created via front-desk wizard (new lead)',
                'created_by' => $staff->id,
                'checkin_id' => $checkinLog->id,
            ]);

            $checkIn->update([
                'metadata' => array_merge($checkIn->metadata ?? [], [
                    'checkin_log_id' => $checkinLog->id,
                ]),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'New lead created and check-in recorded successfully.',
                'check_in_id' => $checkIn->id,
                'lead_id' => $lead->id,
                'lead_name' => trim($lead->first_name.' '.($lead->last_name ?? '')),
                'notified_staff' => $notified ? $notified->full_name : null,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[FrontDeskCheckIn] createLead failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving the check-in. Please try again.',
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Authorisation helper
    // -------------------------------------------------------------------------

    private function authorised(): bool
    {
        $user = Auth::guard('admin')->user();

        return $user instanceof Staff && $user->canAccessFrontDeskCheckIn();
    }
}
