<?php

namespace App\Support;

use App\Models\BookingAppointment;

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

    public static function resolveDurationMinutes(BookingAppointment $appointment): int
    {
        // DB service_id: 2 = free (15 min), 1/3 = paid (30 min)
        if ((int) $appointment->service_id === 2) {
            return 15;
        }

        if (in_array((int) $appointment->service_id, [1, 3], true) || $appointment->is_paid) {
            return 30;
        }

        $stored = (int) ($appointment->duration_minutes ?? 0);

        return $stored > 0 ? $stored : 30;
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
