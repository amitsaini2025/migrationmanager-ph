<?php

namespace App\Support;

use App\Models\BookingAppointment;
use Illuminate\Support\Facades\URL;

class AppointmentActionLink
{
    public const TTL_DAYS = 90;

    public static function cancelShowUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.cancel.show',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function cancelSubmitUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.cancel.submit',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function rescheduleShowUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.show',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function rescheduleSubmitUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.submit',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function rescheduleSlotsUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.slots',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function rescheduleAvailabilityUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.availability',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function confirmShowUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.confirm.show',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    public static function confirmSubmitUrl(int $appointmentId): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.confirm.submit',
            now()->addDays(self::TTL_DAYS),
            ['appointment' => $appointmentId]
        );
    }

    /**
     * @return array{cancel: string, reschedule: string, confirm: string}|null
     */
    public static function emailButtonUrls(?int $appointmentId): ?array
    {
        if ($appointmentId === null || $appointmentId < 1) {
            return null;
        }

        return [
            'cancel' => self::cancelShowUrl($appointmentId),
            'reschedule' => self::rescheduleShowUrl($appointmentId),
            'confirm' => self::confirmShowUrl($appointmentId),
        ];
    }

    public static function urlsForAppointment(BookingAppointment $appointment): array
    {
        return self::emailButtonUrls((int) $appointment->id) ?? [];
    }
}
