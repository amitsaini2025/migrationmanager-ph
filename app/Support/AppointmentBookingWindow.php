<?php

namespace App\Support;

use Carbon\Carbon;

class AppointmentBookingWindow
{
    public const SAME_DAY_MESSAGE = 'Appointment date must be tomorrow or later. Same-day booking is not available.';

    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Australia/Melbourne');
    }

    /**
     * Bansal add-appointment rejects today. CRM must use the office timezone, not the browser's.
     */
    public static function isOnOrBeforeToday(Carbon $appointmentDatetime, ?Carbon $now = null): bool
    {
        $tz = self::timezone();
        $now ??= now($tz);
        $appointmentDay = $appointmentDatetime->copy()->timezone($tz)->startOfDay();
        $today = $now->copy()->timezone($tz)->startOfDay();

        return $appointmentDay->lessThanOrEqualTo($today);
    }
}
