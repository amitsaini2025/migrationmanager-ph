<?php

namespace App\Support;

use App\Models\BookingAppointment;
use Carbon\CarbonInterface;

class AppointmentEmailFormatter
{
    public static function clientTimezone(BookingAppointment $appointment): string
    {
        return filled($appointment->client_timezone)
            ? (string) $appointment->client_timezone
            : 'Australia/Melbourne';
    }

    public static function formatDate(BookingAppointment $appointment): string
    {
        if (! $appointment->appointment_datetime) {
            return 'N/A';
        }

        return $appointment->appointment_datetime
            ->copy()
            ->timezone(self::clientTimezone($appointment))
            ->format('l, d F Y');
    }

    public static function formatTimeRange(BookingAppointment $appointment): string
    {
        if ($appointment->appointment_datetime) {
            $start = $appointment->appointment_datetime
                ->copy()
                ->timezone(self::clientTimezone($appointment));

            $duration = self::resolveDurationMinutes($appointment);
            if ($duration > 0) {
                $end = $start->copy()->addMinutes($duration);

                return $start->format('g:i A').' - '.$end->format('g:i A');
            }

            return $start->format('g:i A');
        }

        return self::normalizeTimeslotFull($appointment->timeslot_full) ?? 'N/A';
    }

    /**
     * Start time only for client-facing confirmation / cancellation / reschedule emails.
     * Leaves formatTimeRange() unchanged for payment pages and other range displays.
     */
    public static function formatStartTime(?string $timeslotFull, mixed $appointmentDatetime = null): string
    {
        $timeslot = trim((string) $timeslotFull);
        if ($timeslot !== '') {
            $parts = preg_split('/\s*[-–—]\s*/u', $timeslot, 2);
            $start = trim((string) ($parts[0] ?? ''));
            if ($start !== '') {
                return $start;
            }
        }

        if ($appointmentDatetime instanceof CarbonInterface) {
            return $appointmentDatetime->format('g:i A');
        }

        return 'N/A';
    }

    public static function resolveDurationMinutes(BookingAppointment $appointment): int
    {
        // Prefer stored duration (CRM now persists getDateTimeBackend duration, e.g. Education paid = 60)
        $stored = (int) ($appointment->duration_minutes ?? 0);
        if ($stored >= 5 && $stored <= 180) {
            return $stored;
        }

        // DB service_id: 2 = free (15 min), 1/3 = paid (30 min)
        if ((int) $appointment->service_id === 2) {
            return 15;
        }

        if (in_array((int) $appointment->service_id, [1, 3], true) || $appointment->is_paid) {
            return 30;
        }

        return 30;
    }

    public static function normalizeTimeslotFull(?string $timeslot): ?string
    {
        $timeslot = trim((string) $timeslot);
        if ($timeslot === '') {
            return null;
        }

        return preg_replace('/\s*-\s*/', ' - ', $timeslot);
    }
}
