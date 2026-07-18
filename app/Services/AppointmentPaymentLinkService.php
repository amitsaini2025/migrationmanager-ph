<?php

namespace App\Services;

use App\Models\BookingAppointment;
use Illuminate\Support\Str;

class AppointmentPaymentLinkService
{
    /** Days until payment link expires (null token expiry = no expiry check). */
    public const TOKEN_TTL_DAYS = 90;

    public function requiresOnlinePayment(BookingAppointment $appointment): bool
    {
        return (bool) $appointment->is_paid
            && $appointment->payment_status !== 'completed'
            && ! in_array($appointment->status, ['cancelled', 'completed'], true);
    }

    public function ensurePaymentToken(BookingAppointment $appointment): BookingAppointment
    {
        if (! $this->requiresOnlinePayment($appointment)) {
            return $appointment;
        }

        if (! empty($appointment->payment_token) && ! $this->tokenExpired($appointment)) {
            return $appointment;
        }

        $appointment->payment_token = Str::random(48);
        $appointment->payment_token_expires_at = now()->addDays(self::TOKEN_TTL_DAYS);
        $appointment->save();

        return $appointment->fresh();
    }

    public function paymentUrl(BookingAppointment $appointment): ?string
    {
        if (empty($appointment->payment_token)) {
            return null;
        }

        return route('public.appointment.pay', ['token' => $appointment->payment_token]);
    }

    public function findPayableAppointment(string $token): ?BookingAppointment
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $appointment = BookingAppointment::where('payment_token', $token)->first();
        if (! $appointment || ! $this->requiresOnlinePayment($appointment)) {
            return null;
        }

        if ($this->tokenExpired($appointment)) {
            return null;
        }

        return $appointment;
    }

    protected function tokenExpired(BookingAppointment $appointment): bool
    {
        if ($appointment->payment_token_expires_at === null) {
            return false;
        }

        return $appointment->payment_token_expires_at->isPast();
    }
}
