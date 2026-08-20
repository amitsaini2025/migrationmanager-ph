<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Decides when a website appointment datetime should overwrite the CRM copy.
 */
class BansalAppointmentDatetimeSync
{
    public static function parseIncoming(array $appointmentData): ?Carbon
    {
        if (! empty($appointmentData['appointment_datetime'])) {
            try {
                return Carbon::parse($appointmentData['appointment_datetime'])->seconds(0);
            } catch (\Throwable) {
                // Fall through to date + time.
            }
        }

        $date = $appointmentData['appointment_date'] ?? null;
        $time = $appointmentData['appointment_time'] ?? null;
        if (! $date || ! $time) {
            return null;
        }

        try {
            $normalizedTime = Carbon::parse((string) $time)->format('H:i');

            return Carbon::parse($date.' '.$normalizedTime)->seconds(0);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Apply website datetime only when it differs and the website row was changed
     * after the CRM last synced (so a failed CRM→website push is not overwritten).
     */
    public static function shouldApply(
        Carbon $incoming,
        ?Carbon $currentCrmDatetime,
        ?Carbon $websiteUpdatedAt,
        ?Carbon $crmLastSyncedAt
    ): bool {
        if ($currentCrmDatetime && $currentCrmDatetime->copy()->seconds(0)->equalTo($incoming->copy()->seconds(0))) {
            return false;
        }

        if ($websiteUpdatedAt === null) {
            return false;
        }

        if ($crmLastSyncedAt && $websiteUpdatedAt->lte($crmLastSyncedAt)) {
            return false;
        }

        return true;
    }

    public static function timeslotFull(Carbon $start, int $durationMinutes): string
    {
        $durationMinutes = $durationMinutes > 0 ? $durationMinutes : 30;

        return $start->format('g:i A').' – '.$start->copy()->addMinutes($durationMinutes)->format('g:i A');
    }
}
