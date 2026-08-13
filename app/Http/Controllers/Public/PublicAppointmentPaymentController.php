<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\AppointmentDetailedConfirmation;
use App\Models\BookingAppointment;
use App\Services\AppointmentPaymentLinkService;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use App\Services\Payment\StripePaymentService;
use App\Services\SystemEmailLogService;
use App\Support\AppointmentEmailFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PublicAppointmentPaymentController extends Controller
{
    public function __construct(
        protected AppointmentPaymentLinkService $paymentLinkService,
        protected StripePaymentService $stripePaymentService,
    ) {}

    public function show(string $token): View
    {
        $appointment = $this->paymentLinkService->findPayableAppointment($token);

        if (! $appointment) {
            return view('public.appointment-pay', [
                'error' => 'This payment link is invalid, expired, or payment has already been completed.',
                'appointment' => null,
                'stripeKey' => null,
                'token' => null,
            ]);
        }

        return view('public.appointment-pay', [
            'error' => null,
            'appointment' => $appointment,
            'appointmentDate' => AppointmentEmailFormatter::formatDate($appointment),
            'appointmentTime' => AppointmentEmailFormatter::formatTimeRange($appointment),
            'stripeKey' => config('services.stripe.key'),
            'token' => $token,
            'amount' => (float) ($appointment->final_amount ?? $appointment->amount),
        ]);
    }

    public function createIntent(Request $request, string $token): JsonResponse
    {
        $appointment = $this->paymentLinkService->findPayableAppointment($token);
        if (! $appointment) {
            return response()->json(['message' => 'Invalid or expired payment link.'], 404);
        }

        $result = $this->stripePaymentService->createPublicPaymentIntent($appointment);
        if (! ($result['success'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Unable to create payment.'], 422);
        }

        return response()->json($result['data'], 201);
    }

    public function complete(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string|starts_with:pi_',
        ]);

        $appointment = $this->paymentLinkService->findPayableAppointment($token);
        if (! $appointment) {
            return response()->json(['message' => 'Invalid or expired payment link.'], 404);
        }

        $result = $this->stripePaymentService->recordPaymentByIntent(
            $appointment,
            $request->payment_intent_id,
            [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        if (! ($result['success'] ?? false)) {
            return response()->json(['message' => $result['message'] ?? 'Payment could not be recorded.'], 422);
        }

        $syncError = $this->syncAppointmentPaidWithBansal($appointment);

        $appointment->refresh();

        $this->sendPostPaymentConfirmationEmail($appointment);

        $message = 'Payment successful. Thank you — your appointment is confirmed.';
        if ($syncError) {
            $message .= ' Note: Payment completed but sync with website failed.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result['data'] ?? [],
            'bansal_synced' => ! $syncError,
        ]);
    }

    /**
     * Push paid status to Bansal after pay-by-link success. Never throws.
     */
    protected function syncAppointmentPaidWithBansal(BookingAppointment $appointment): ?string
    {
        if (empty($appointment->bansal_appointment_id)) {
            return null;
        }

        try {
            $result = app(BansalAppointmentRecoveryService::class)->syncStatus($appointment, 'paid');

            if ($result['synced']) {
                if ($result['bansal_appointment_id'] !== null) {
                    $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
                }

                $appointment->forceFill([
                    'last_synced_at' => now(),
                    'sync_status' => 'synced',
                    'sync_error' => null,
                ])->save();

                Log::info('Pay-by-link payment status synced with Bansal API', [
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

            Log::error('Failed to sync pay-by-link payment status with Bansal API', [
                'appointment_id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'error' => $syncError,
            ]);

            return $syncError;
        } catch (\Exception $e) {
            $syncError = $e->getMessage();
            Log::error('Failed to sync pay-by-link payment status with Bansal API', [
                'appointment_id' => $appointment->id,
                'error' => $syncError,
            ]);

            return $syncError;
        }
    }

    protected function sendPostPaymentConfirmationEmail($appointment): void
    {
        if (empty($appointment->client_email) || $appointment->confirmation_email_sent) {
            return;
        }

        try {
            $emailDetails = [
                'client_name' => $appointment->client_name,
                'appointment_datetime' => $appointment->appointment_datetime,
                'timeslot_full' => $appointment->timeslot_full,
                'location' => $appointment->location,
                'service_type' => $appointment->service_type,
                'meeting_type' => $appointment->meeting_type,
                'admin_notes' => $appointment->admin_notes ?? null,
            ];

            app(SystemEmailLogService::class)->logAndSendMailable([
                'category' => 'appointment',
                'from_mail' => config('mail.noreply.address'),
                'to_mail' => $appointment->client_email,
                'subject' => 'Appointment Confirmation - Bansal Immigration',
                'client_id' => $appointment->client_id,
            ], new AppointmentDetailedConfirmation($emailDetails), $appointment->client_email);

            $appointment->update([
                'confirmation_email_sent' => true,
                'confirmation_email_sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send post-payment confirmation email', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
