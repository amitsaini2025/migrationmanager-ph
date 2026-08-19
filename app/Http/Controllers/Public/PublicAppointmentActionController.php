<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicCancelAppointmentRequest;
use App\Http\Requests\PublicRescheduleAppointmentRequest;
use App\Models\BookingAppointment;
use App\Services\AppointmentOpenSlotService;
use App\Services\ClientAppointmentActionService;
use App\Support\AppointmentActionLink;
use App\Support\AppointmentEmailFormatter;
use App\Support\AppointmentMeetingTypeCopy;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicAppointmentActionController extends Controller
{
    public function __construct(
        protected ClientAppointmentActionService $actionService,
        protected AppointmentOpenSlotService $openSlotService,
    ) {}

    public function showCancel(Request $request, BookingAppointment $appointment): View
    {
        if ($blocked = $this->blockedView($request, $appointment, 'cancel')) {
            return $blocked;
        }

        if ($appointment->status === 'cancelled') {
            return view('public.appointment-action-result', $this->pageData($appointment, [
                'title' => 'Appointment cancelled',
                'heading' => 'Appointment cancelled',
                'ok' => true,
                'message' => 'This appointment has already been cancelled.',
            ]));
        }

        return view('public.appointment-action-cancel', $this->pageData($appointment, [
            'title' => 'Cancel appointment',
            'heading' => 'Cancel Appointment',
            'submitUrl' => AppointmentActionLink::cancelSubmitUrl((int) $appointment->id),
        ]));
    }

    public function cancel(PublicCancelAppointmentRequest $request, BookingAppointment $appointment): View
    {
        if ($blocked = $this->blockedView($request, $appointment, 'cancel')) {
            return $blocked;
        }

        $result = $this->actionService->cancel(
            $appointment,
            $request->validated('cancellation_reason')
        );

        return $this->resultView($appointment, 'Appointment cancelled', $result);
    }

    public function showConfirm(Request $request, BookingAppointment $appointment): View
    {
        if ($blocked = $this->blockedView($request, $appointment, 'confirm')) {
            return $blocked;
        }

        return view('public.appointment-action-confirm', $this->pageData($appointment, [
            'title' => 'Confirm appointment',
            'heading' => 'Confirm Appointment',
            'submitUrl' => AppointmentActionLink::confirmSubmitUrl((int) $appointment->id),
        ]));
    }

    public function confirm(Request $request, BookingAppointment $appointment): View
    {
        if ($blocked = $this->blockedView($request, $appointment, 'confirm')) {
            return $blocked;
        }

        $result = $this->actionService->confirm($appointment);

        return $this->resultView($appointment, 'Appointment confirmed', $result);
    }

    public function showReschedule(Request $request, BookingAppointment $appointment): View
    {
        if ($blocked = $this->blockedView($request, $appointment, 'reschedule')) {
            return $blocked;
        }

        return view('public.appointment-action-reschedule', $this->pageData($appointment, [
            'title' => 'Reschedule appointment',
            'heading' => 'Reschedule Appointment',
            'submitUrl' => AppointmentActionLink::rescheduleSubmitUrl((int) $appointment->id),
            'slotsUrl' => AppointmentActionLink::rescheduleSlotsUrl((int) $appointment->id),
            'availabilityUrl' => AppointmentActionLink::rescheduleAvailabilityUrl((int) $appointment->id),
        ]));
    }

    public function availability(Request $request, BookingAppointment $appointment): JsonResponse
    {
        if ($error = $this->jsonBlocked($request, $appointment)) {
            return $error;
        }

        return response()->json($this->openSlotService->availability($appointment));
    }

    public function slots(Request $request, BookingAppointment $appointment): JsonResponse
    {
        if ($error = $this->jsonBlocked($request, $appointment)) {
            return $error;
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $selectedDate = Carbon::createFromFormat('Y-m-d', $validated['date'], config('app.timezone'));
        if ($selectedDate === false) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date.',
                'slots' => [],
            ], 422);
        }

        return response()->json($this->openSlotService->openSlotsForDate($appointment, $selectedDate->startOfDay()));
    }

    public function reschedule(PublicRescheduleAppointmentRequest $request, BookingAppointment $appointment): View|RedirectResponse
    {
        if ($blocked = $this->blockedView($request, $appointment, 'reschedule')) {
            return $blocked;
        }

        $validated = $request->validated();
        $result = $this->actionService->reschedule(
            $appointment,
            $validated['appointment_date'],
            $validated['appointment_time']
        );

        if (! $result['ok']) {
            return redirect()
                ->to(AppointmentActionLink::rescheduleShowUrl((int) $appointment->id))
                ->withErrors(['appointment_time' => $result['message']])
                ->withInput();
        }

        return $this->resultView($appointment->fresh(), 'Appointment rescheduled', $result);
    }

    protected function blockedView(Request $request, BookingAppointment $appointment, string $action): ?View
    {
        if (! $request->hasValidSignature()) {
            return view('public.appointment-action-result', [
                'title' => 'Link expired',
                'ok' => false,
                'message' => 'This link is invalid or has expired. Please contact our office if you still need to '.$action.' your appointment.',
                'appointment' => null,
            ]);
        }

        if (! $this->actionService->canAct($appointment) && ! ($action === 'cancel' && $appointment->status === 'cancelled')) {
            $actionLabel = match ($action) {
                'cancel' => 'cancelled',
                'confirm' => 'confirmed',
                default => 'rescheduled',
            };

            return view('public.appointment-action-result', $this->pageData($appointment, [
                'title' => 'Appointment unavailable',
                'heading' => 'Appointment unavailable',
                'ok' => false,
                'message' => 'This appointment can no longer be '.$actionLabel.'.',
            ]));
        }

        return null;
    }

    protected function jsonBlocked(Request $request, BookingAppointment $appointment): ?JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return response()->json(['success' => false, 'message' => 'This link is invalid or has expired.'], 403);
        }

        if (! $this->actionService->canAct($appointment)) {
            return response()->json(['success' => false, 'message' => 'This appointment can no longer be changed.'], 422);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function pageData(BookingAppointment $appointment, array $extra = []): array
    {
        $locationKey = strtolower(trim((string) ($appointment->location ?? 'melbourne')));
        if ($locationKey === '') {
            $locationKey = 'melbourne';
        }

        $locationAddress = match ($locationKey) {
            'melbourne' => 'Level 8/278 Collins St, Melbourne VIC 3000, Australia',
            'adelaide' => 'Unit 5, 55 Gawler Pl, Adelaide SA 5000, Australia',
            default => filled($appointment->inperson_address)
                ? (string) $appointment->inperson_address
                : 'Bansal Immigration Office',
        };

        return array_merge([
            'appointment' => $appointment,
            'appointmentDate' => AppointmentEmailFormatter::formatDate($appointment),
            'appointmentTime' => AppointmentEmailFormatter::formatTimeRange($appointment),
            'meetingTypeLabel' => AppointmentMeetingTypeCopy::label($appointment->meeting_type),
            'serviceType' => filled($appointment->service_type) ? (string) $appointment->service_type : 'N/A',
            'locationAddress' => $locationAddress,
        ], $extra);
    }

    /**
     * @param  array{ok: bool, already: bool, message: string, sync_error: ?string}  $result
     */
    protected function resultView(BookingAppointment $appointment, string $title, array $result): View
    {
        return view('public.appointment-action-result', $this->pageData($appointment, [
            'title' => $title,
            'heading' => $title,
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]));
    }
}
