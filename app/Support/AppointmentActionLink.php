<?php

namespace App\Support;

use App\Models\BookingAppointment;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

class AppointmentActionLink
{
    public const TTL_DAYS = 90;

    public static function cancelShowUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.cancel.show',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function cancelSubmitUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.cancel.submit',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function rescheduleShowUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.show',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function rescheduleSubmitUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.submit',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function rescheduleSlotsUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.slots',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function rescheduleAvailabilityUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.reschedule.availability',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function confirmShowUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.confirm.show',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    public static function confirmSubmitUrl(int $appointmentId, mixed $slotAt = null): string
    {
        return URL::temporarySignedRoute(
            'public.appointment.confirm.submit',
            now()->addDays(self::TTL_DAYS),
            self::routeParams($appointmentId, $slotAt)
        );
    }

    /**
     * @return array{cancel: string, reschedule: string, confirm: string}|null
     */
    public static function emailButtonUrls(?int $appointmentId, mixed $slotAt = null): ?array
    {
        if ($appointmentId === null || $appointmentId < 1) {
            return null;
        }

        return [
            'cancel' => self::cancelShowUrl($appointmentId, $slotAt),
            'reschedule' => self::rescheduleShowUrl($appointmentId, $slotAt),
            'confirm' => self::confirmShowUrl($appointmentId, $slotAt),
        ];
    }

    public static function urlsForAppointment(BookingAppointment $appointment): array
    {
        return self::emailButtonUrls(
            (int) $appointment->id,
            $appointment->appointment_datetime
        ) ?? [];
    }

    /**
     * Cancel / Confirm / Reschedule links must include `at` matching the current
     * appointment slot. Missing or stale `at` (previous emails after reschedule) expire.
     */
    public static function matchesCurrentSlot(BookingAppointment $appointment, mixed $providedAt): bool
    {
        $provided = self::slotTimestamp($providedAt);
        $current = self::slotTimestamp($appointment->appointment_datetime);

        if ($provided === null || $current === null) {
            return false;
        }

        return $current === $provided;
    }

    public static function slotTimestamp(mixed $slotAt): ?int
    {
        if ($slotAt instanceof CarbonInterface || $slotAt instanceof DateTimeInterface) {
            return $slotAt->getTimestamp();
        }

        if (is_int($slotAt) && $slotAt > 0) {
            return $slotAt;
        }

        if (is_string($slotAt) && ctype_digit($slotAt)) {
            $timestamp = (int) $slotAt;

            return $timestamp > 0 ? $timestamp : null;
        }

        if (is_numeric($slotAt)) {
            $timestamp = (int) $slotAt;

            return $timestamp > 0 ? $timestamp : null;
        }

        return null;
    }

    /**
     * @return array{appointment: int}|array{appointment: int, at: int}
     */
    protected static function routeParams(int $appointmentId, mixed $slotAt = null): array
    {
        $params = ['appointment' => $appointmentId];
        $timestamp = self::slotTimestamp($slotAt);
        if ($timestamp !== null) {
            $params['at'] = $timestamp;
        }

        return $params;
    }
}
