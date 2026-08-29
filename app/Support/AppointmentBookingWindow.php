<?php

namespace App\Support;

use Carbon\Carbon;

class AppointmentBookingWindow
{
    public const SAME_DAY_MESSAGE = 'Appointment date must be tomorrow or later. Same-day booking is not available.';

    public const EMAIL_RESCHEDULE_CLOSED_MESSAGE = 'Please choose a Monday–Thursday date from tomorrow onwards. Friday, Saturday, Sunday, and today are not available.';

    /**
     * JS Date.getDay() / Carbon dayOfWeek: Sunday=0 … Saturday=6
     *
     * @var list<int>
     */
    public const EMAIL_RESCHEDULE_CLOSED_WEEKDAYS = [
        Carbon::SUNDAY,
        Carbon::FRIDAY,
        Carbon::SATURDAY,
    ];

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

    public static function isEmailRescheduleClosedWeekday(Carbon $date): bool
    {
        return in_array(
            (int) $date->copy()->timezone(self::timezone())->dayOfWeek,
            self::EMAIL_RESCHEDULE_CLOSED_WEEKDAYS,
            true
        );
    }

    /**
     * Email reschedule: no past/today, and no Friday–Sunday.
     */
    public static function isInvalidEmailRescheduleDate(Carbon $appointmentDatetime, ?Carbon $now = null): bool
    {
        return self::isOnOrBeforeToday($appointmentDatetime, $now)
            || self::isEmailRescheduleClosedWeekday($appointmentDatetime);
    }

    /**
     * Earliest date the email reschedule date picker may offer (tomorrow+, Mon–Thu).
     */
    public static function earliestEmailRescheduleDate(?Carbon $now = null): Carbon
    {
        $tz = self::timezone();
        $day = ($now ?? now($tz))->copy()->timezone($tz)->startOfDay()->addDay();

        while (self::isEmailRescheduleClosedWeekday($day)) {
            $day->addDay();
        }

        return $day;
    }
}
