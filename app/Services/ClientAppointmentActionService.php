<?php

namespace App\Services;

use App\Models\ActivitiesLog;
use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use App\Services\BansalAppointmentSync\NotificationService;
use App\Support\AppointmentEmailFormatter;
use App\Support\AppointmentOpenSlots;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ClientAppointmentActionService
{
    /**
     * @var list<string>
     */
    public const BLOCKED_STATUSES = ['cancelled', 'completed', 'no_show'];

    public function __construct(
        protected BansalAppointmentRecoveryService $recoveryService,
        protected NotificationService $notificationService,
        protected AppointmentOpenSlotService $openSlotService,
    ) {}

    public function canAct(BookingAppointment $appointment): bool
    {
        return ! in_array((string) $appointment->status, self::BLOCKED_STATUSES, true);
    }

    /**
     * @return array{ok: bool, already: bool, message: string, sync_error: ?string}
     */
    public function cancel(BookingAppointment $appointment, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return $this->failure('Please provide a cancellation reason.');
        }

        if (! $this->canAct($appointment)) {
            if ($appointment->status === 'cancelled') {
                return [
                    'ok' => true,
                    'already' => true,
                    'message' => 'This appointment has already been cancelled.',
                    'sync_error' => null,
                ];
            }

            return $this->failure('This appointment can no longer be cancelled.');
        }

        $appointment->status = 'cancelled';
        $appointment->cancelled_at = now();
        $appointment->cancellation_reason = $reason;
        $appointment->save();

        $syncError = $this->syncStatus($appointment, 'cancelled', $reason);
        $this->logActivity(
            $appointment,
            'Appointment cancelled by client',
            '<p><strong>Cancelled from email link.</strong></p><p><strong>Reason:</strong> '.e($reason).'</p>'
        );

        $this->notificationService->sendCancellationConfirmationEmail($appointment->fresh(), $reason, true);

        $message = 'Your appointment has been cancelled.';
        if ($syncError) {
            $message .= ' Note: local cancellation succeeded, but website sync is pending.';
        }

        return [
            'ok' => true,
            'already' => false,
            'message' => $message,
            'sync_error' => $syncError,
        ];
    }

    /**
     * @return array{ok: bool, already: bool, message: string, sync_error: ?string}
     */
    public function confirm(BookingAppointment $appointment): array
    {
        if (! $this->canAct($appointment)) {
            return $this->failure('This appointment can no longer be confirmed.');
        }

        if ((string) $appointment->status === 'confirmed') {
            return [
                'ok' => true,
                'already' => true,
                'message' => 'This appointment is already confirmed. We look forward to speaking with you.',
                'sync_error' => null,
            ];
        }

        $appointment->status = 'confirmed';
        $appointment->confirmed_at = $appointment->confirmed_at ?? now();
        $appointment->save();

        $syncError = $this->syncStatus($appointment, 'confirmed');
        $this->logActivity(
            $appointment,
            'Appointment confirmed by client',
            '<p>Client confirmed the booking from the appointment email.</p>'
        );

        $this->notificationService->sendClientConfirmedEmail($appointment->fresh(), true);

        $message = 'Thank you. Your appointment is now confirmed.';
        if ($syncError) {
            $message .= ' Note: confirmation was saved, but website sync is pending.';
        }

        return [
            'ok' => true,
            'already' => false,
            'message' => $message,
            'sync_error' => $syncError,
        ];
    }

    /**
     * @return array{ok: bool, already: bool, message: string, sync_error: ?string}
     */
    public function reschedule(BookingAppointment $appointment, string $date, string $time): array
    {
        if (! $this->canAct($appointment)) {
            return $this->failure('This appointment can no longer be rescheduled.');
        }

        try {
            $newDatetime = Carbon::createFromFormat(
                'Y-m-d H:i',
                $date.' '.$time,
                config('app.timezone')
            );
        } catch (\Throwable) {
            return $this->failure('The selected date or time is invalid.');
        }

        if ($newDatetime === false || $newDatetime->lessThan(now())) {
            return $this->failure('Please choose a future date and time.');
        }

        $oldDatetime = $appointment->appointment_datetime;
        if ($oldDatetime && $oldDatetime->equalTo($newDatetime)) {
            return [
                'ok' => true,
                'already' => true,
                'message' => 'That is already your current appointment time.',
                'sync_error' => null,
            ];
        }

        $openSlots = $this->openSlotService->openSlotsForDate($appointment, $newDatetime->copy()->startOfDay());
        if (! ($openSlots['success'] ?? false)) {
            return $this->failure($openSlots['message'] ?? 'Unable to verify open slots. Please try again.');
        }

        $requestedStart = $newDatetime->format('H:i');
        $matchingSlot = collect($openSlots['slots'] ?? [])->first(
            fn (array $slot): bool => ($slot['start_24'] ?? '') === $requestedStart
        );
        if ($matchingSlot === null) {
            return $this->failure('That time is no longer available. Please choose an open slot.');
        }

        if ($appointment->consultant_id) {
            $slotTaken = BookingAppointment::query()
                ->where('consultant_id', $appointment->consultant_id)
                ->where('appointment_datetime', $newDatetime)
                ->where('id', '!=', $appointment->id)
                ->whereNotIn('status', self::BLOCKED_STATUSES)
                ->exists();

            if ($slotTaken) {
                return $this->failure('That time slot is already booked. Please select a different date or time.');
            }
        }

        $duration = AppointmentEmailFormatter::resolveDurationMinutes($appointment);
        $endLabel = AppointmentOpenSlots::minutesToLabel(
            (AppointmentOpenSlots::parseMinutes($requestedStart) ?? 0) + $duration
        );

        $appointment->appointment_datetime = $newDatetime;
        $appointment->timeslot_full = $matchingSlot['display']
            ?? ($newDatetime->format('g:i A').' – '.$endLabel);
        $appointment->save();

        $syncError = null;
        if (! empty($appointment->bansal_appointment_id)) {
            $result = $this->recoveryService->syncReschedule(
                $appointment,
                $newDatetime->format('Y-m-d'),
                $newDatetime->format('H:i'),
                $appointment->meeting_type ?? 'in_person',
                $appointment->preferred_language ?? 'English'
            );

            if ($result['synced']) {
                if ($result['bansal_appointment_id'] !== null) {
                    $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
                }
                $appointment->last_synced_at = now();
                $appointment->sync_status = 'synced';
                $appointment->sync_error = null;
            } else {
                $syncError = $result['error'];
                $appointment->sync_status = 'error';
                $appointment->sync_error = $syncError;
            }
            $appointment->save();
        }

        $from = $oldDatetime ? $oldDatetime->format('d M Y, h:i A') : 'N/A';
        $to = $newDatetime->format('d M Y, h:i A');
        $this->logActivity(
            $appointment,
            'Appointment rescheduled by client',
            '<p><strong>Rescheduled from email link:</strong> '.e($from).' → '.e($to).'</p>'
        );

        if (! empty($appointment->client_email)) {
            $this->notificationService->sendRescheduleEmail($appointment->fresh(), $oldDatetime, true);
        }

        $message = 'Your appointment has been rescheduled.';
        if ($syncError) {
            $message .= ' Note: the new time was saved, but website sync is pending.';
        }

        return [
            'ok' => true,
            'already' => false,
            'message' => $message,
            'sync_error' => $syncError,
        ];
    }

    /**
     * @return array{ok: bool, already: bool, message: string, sync_error: ?string}
     */
    protected function failure(string $message): array
    {
        return [
            'ok' => false,
            'already' => false,
            'message' => $message,
            'sync_error' => null,
        ];
    }

    protected function syncStatus(BookingAppointment $appointment, string $status, ?string $reason = null): ?string
    {
        if (empty($appointment->bansal_appointment_id)) {
            return null;
        }

        $result = $this->recoveryService->syncStatus($appointment, $status, $reason);
        if ($result['synced']) {
            if ($result['bansal_appointment_id'] !== null) {
                $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
            }
            $appointment->forceFill([
                'last_synced_at' => now(),
                'sync_status' => 'synced',
                'sync_error' => null,
            ])->save();

            return null;
        }

        $syncError = $result['error'] ?? 'Unknown Bansal sync error';
        $appointment->forceFill([
            'sync_status' => 'error',
            'sync_error' => $syncError,
        ])->save();

        Log::warning('Client appointment action Bansal sync failed', [
            'appointment_id' => $appointment->id,
            'status' => $status,
            'error' => $syncError,
        ]);

        return $syncError;
    }

    protected function logActivity(BookingAppointment $appointment, string $subject, string $description): void
    {
        if (! $appointment->client_id) {
            return;
        }

        $activityLog = new ActivitiesLog;
        $activityLog->client_id = $appointment->client_id;
        $activityLog->created_by = config('app.system_user_id', 1);
        $activityLog->subject = $subject;
        $activityLog->description = $description;
        $activityLog->task_status = 0;
        $activityLog->pin = 0;
        $activityLog->save();
    }
}
