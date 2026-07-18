<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\BookingAppointment;
use App\Services\AppointmentPaymentLinkService;
use App\Services\Sms\UnifiedSmsManager;
use App\Services\SystemEmailLogService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected UnifiedSmsManager $smsManager;
    protected SystemEmailLogService $systemEmailLog;

    public function __construct(UnifiedSmsManager $smsManager, SystemEmailLogService $systemEmailLog)
    {
        $this->smsManager = $smsManager;
        $this->systemEmailLog = $systemEmailLog;
    }

    /**
     * Send the appropriate booking email: payment link for unpaid paid appointments,
     * or the standard detailed confirmation for free / already-paid bookings.
     */
    public function sendBookingConfirmationEmail(BookingAppointment $appointment): bool
    {
        $paymentLinkService = app(AppointmentPaymentLinkService::class);

        if ($paymentLinkService->requiresOnlinePayment($appointment)) {
            return $this->sendPaidAppointmentPaymentEmail($appointment);
        }

        return $this->sendDetailedConfirmationEmail($appointment);
    }

    /**
     * Send payment link email for paid appointments awaiting online payment.
     */
    public function sendPaidAppointmentPaymentEmail(BookingAppointment $appointment): bool
    {
        try {
            if (empty($appointment->client_email)) {
                return false;
            }

            $paymentLinkService = app(AppointmentPaymentLinkService::class);
            if (! $paymentLinkService->requiresOnlinePayment($appointment)) {
                return false;
            }

            $appointment = $paymentLinkService->ensurePaymentToken($appointment);
            $paymentUrl = $paymentLinkService->paymentUrl($appointment);
            if (! $paymentUrl) {
                return false;
            }

            $details = [
                'client_name' => $appointment->client_name,
                'appointment_datetime' => $appointment->appointment_datetime,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
                'service_type' => $appointment->service_type,
                'amount' => (float) ($appointment->final_amount ?? $appointment->amount),
                'payment_url' => $paymentUrl,
            ];

            $this->systemEmailLog->logAndSendMailable([
                'category' => 'appointment_payment',
                'from_mail' => config('mail.from.address'),
                'to_mail' => $appointment->client_email,
                'subject' => 'Complete Your Appointment Payment - Bansal Immigration',
                'client_id' => $appointment->client_id,
            ], new \App\Mail\AppointmentPaidPaymentLink($details), $appointment->client_email);

            Log::info('Sent paid appointment payment link email', [
                'appointment_id' => $appointment->id,
                'email' => $appointment->client_email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send paid appointment payment link email', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send detailed follow-up confirmation email
     * (This is sent AFTER customer already got instant confirmation from Bansal website)
     */
    public function sendDetailedConfirmationEmail(BookingAppointment $appointment): bool
    {
        try {
            // Only send if not already sent
            if ($appointment->confirmation_email_sent) {
                return true;
            }

            $details = [
                'client_name' => $appointment->client_name,
                'appointment_datetime' => $appointment->appointment_datetime,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
                'meeting_type' => $appointment->meeting_type,
                'service_type' => $appointment->service_type,
                'admin_notes' => $appointment->admin_notes,
            ];

            $this->systemEmailLog->logAndSendMailable([
                'category'  => 'appointment',
                'from_mail' => config('mail.from.address'),
                'to_mail'   => $appointment->client_email,
                'subject'   => 'Appointment Confirmation - Bansal Immigration',
                'client_id' => $appointment->client_id,
            ], new \App\Mail\AppointmentDetailedConfirmation($details), $appointment->client_email);

            $appointment->update([
                'confirmation_email_sent' => true,
                'confirmation_email_sent_at' => now()
            ]);

            Log::info('Sent detailed confirmation email', [
                'appointment_id' => $appointment->id,
                'email' => $appointment->client_email
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send confirmation email', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send cancellation confirmation email to client
     */
    public function sendCancellationConfirmationEmail(BookingAppointment $appointment, ?string $cancellationReason = null): bool
    {
        try {
            $details = [
                'client_name' => $appointment->client_name,
                'appointment_datetime' => $appointment->appointment_datetime,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
                'cancellation_reason' => $cancellationReason,
            ];

            $this->systemEmailLog->logAndSendMailable([
                'category'  => 'appointment_cancellation',
                'from_mail' => config('mail.from.address'),
                'to_mail'   => $appointment->client_email,
                'subject'   => 'Appointment Cancellation - Bansal Immigration',
                'client_id' => $appointment->client_id,
            ], new \App\Mail\AppointmentCancellation($details), $appointment->client_email);

            Log::info('Sent cancellation confirmation email', [
                'appointment_id' => $appointment->id,
                'email' => $appointment->client_email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send cancellation confirmation email', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send reschedule confirmation email to client when an appointment's date/time is updated by staff.
     *
     * @param  BookingAppointment  $appointment  The appointment with the NEW datetime already saved.
     * @param  \Carbon\Carbon|null  $oldDatetime  The previous appointment datetime.
     */
    public function sendRescheduleEmail(BookingAppointment $appointment, ?\Carbon\Carbon $oldDatetime): bool
    {
        try {
            $details = [
                'client_name'          => $appointment->client_name,
                'old_datetime'         => $oldDatetime,
                'appointment_datetime' => $appointment->appointment_datetime,
                'timeslot_full'        => $appointment->timeslot_full,
                'location'             => $appointment->location,
                'meeting_type'         => $appointment->meeting_type,
            ];

            $this->systemEmailLog->logAndSendMailable([
                'category'  => 'appointment_reschedule',
                'from_mail' => config('mail.from.address'),
                'to_mail'   => $appointment->client_email,
                'subject'   => 'Appointment Rescheduled - Bansal Immigration',
                'client_id' => $appointment->client_id,
            ], new \App\Mail\AppointmentReschedule($details), $appointment->client_email);

            Log::info('Sent reschedule confirmation email', [
                'appointment_id' => $appointment->id,
                'email'          => $appointment->client_email,
                'old_datetime'   => $oldDatetime?->toIso8601String(),
                'new_datetime'   => $appointment->appointment_datetime?->toIso8601String(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send reschedule email', [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send appointment reminder email (manual from CRM; distinct from the initial detailed confirmation).
     */
    public function sendAppointmentReminderEmail(BookingAppointment $appointment): bool
    {
        try {
            $details = [
                'client_name' => $appointment->client_name,
                'appointment_datetime' => $appointment->appointment_datetime,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
            ];

            $this->systemEmailLog->logAndSendMailable([
                'category'  => 'appointment_reminder',
                'from_mail' => config('mail.from.address'),
                'to_mail'   => $appointment->client_email,
                'subject'   => 'Appointment Reminder - Bansal Immigration',
                'client_id' => $appointment->client_id,
            ], new \App\Mail\AppointmentReminder($details), $appointment->client_email);

            Log::info('Sent appointment reminder email', [
                'appointment_id' => $appointment->id,
                'email' => $appointment->client_email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send appointment reminder email', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send reminder SMS (24 hours before appointment)
     */
    public function sendReminderSms(BookingAppointment $appointment): bool
    {
        try {
            if ($appointment->reminder_sms_sent) {
                return true;
            }

            $phone = $appointment->client_phone;
            if (empty($phone)) {
                Log::warning('No phone number for reminder SMS', [
                    'appointment_id' => $appointment->id
                ]);
                return false;
            }

            // Get office phone number based on location
            $officePhone = match($appointment->location) {
                'adelaide' => '08 8317 1340',
                'melbourne' => '03 9602 1330',
                default => '1300 859 368' // Fallback to original number
            };

            $meetingType = strtolower(trim($appointment->meeting_type ?? ''));
            $templateAlias = match ($meetingType) {
                'in_person' => 'booking_reminder_in_person',
                'phone' => 'booking_reminder_phone',
                'video' => 'booking_reminder_video',
                default => 'booking_reminder_default',
            };

            $variables = [
                'timeslot_full' => (string) $appointment->timeslot_full,
                'location' => (string) $appointment->location,
                'office_phone' => $officePhone,
            ];

            $context = [
                'appointment_id' => $appointment->id,
                'client_id' => $appointment->client_id,
            ];

            $result = $this->smsManager->sendFromTemplateByAlias($phone, $templateAlias, $variables, $context);

            if (! $result['success'] && str_contains($result['message'] ?? '', 'Template not found')) {
                $message = match ($meetingType) {
                    'in_person' => "BANSAL IMMIGRATION: Reminder - You have a scheduled In-Person appointment tomorrow at {$appointment->timeslot_full} at our {$appointment->location} office. Please be on time. If you need to reschedule, call us at {$officePhone}.",
                    'phone' => "BANSAL IMMIGRATION: Reminder - You have a scheduled Phone appointment tomorrow at {$appointment->timeslot_full} . Please be on time. If you need to reschedule, call us at {$officePhone}.",
                    'video' => "BANSAL IMMIGRATION: Reminder - You have a scheduled Video Call appointment tomorrow at {$appointment->timeslot_full} . Please be on time. If you need to reschedule, call us at {$officePhone}.",
                    default => "BANSAL IMMIGRATION: Reminder - You have a scheduled appointment tomorrow at {$appointment->timeslot_full} at our {$appointment->location} office. Please be on time. If you need to reschedule, call us at {$officePhone}.",
                };
                $result = $this->smsManager->sendSms($phone, $message, 'reminder', $context);
            }

            if ($result['success']) {
                $appointment->update([
                    'reminder_sms_sent' => true,
                    'reminder_sms_sent_at' => now()
                ]);

                Log::info('Sent reminder SMS', [
                    'appointment_id' => $appointment->id,
                    'phone' => $phone
                ]);
            }

            return $result['success'];
        } catch (\Exception $e) {
            Log::error('Failed to send reminder SMS', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send reminders for upcoming appointments (24 hours ahead)
     */
    public function sendUpcomingReminders(): array
    {
        $tomorrow = now()->addDay()->startOfDay();
        $endOfTomorrow = now()->addDay()->endOfDay();

        $appointments = BookingAppointment::where('reminder_sms_sent', false)
            ->where('status', 'confirmed')
            ->whereBetween('appointment_datetime', [$tomorrow, $endOfTomorrow])
            ->get();

        $stats = [
            'total' => $appointments->count(),
            'sent' => 0,
            'failed' => 0
        ];

        foreach ($appointments as $appointment) {
            if ($this->sendReminderSms($appointment)) {
                $stats['sent']++;
            } else {
                $stats['failed']++;
            }
        }

        Log::info('Sent appointment reminders', $stats);

        return $stats;
    }
}

