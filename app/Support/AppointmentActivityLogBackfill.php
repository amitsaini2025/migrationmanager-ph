<?php

namespace App\Support;

use App\Models\ActivitiesLog;
use App\Models\BookingAppointment;
use Carbon\Carbon;

/**
 * Matches legacy appointment scheduling activity logs to booking_appointments
 * and rebuilds subject/description using AppointmentActivityDescription.
 */
class AppointmentActivityLogBackfill
{
    private const NEW_FORMAT_MARKER = 'appointment-activity-detail';

    public static function isEligible(ActivitiesLog $log): bool
    {
        $subject = strtolower(trim((string) ($log->subject ?? '')));
        $description = (string) ($log->description ?? '');

        if ($description === '' || str_contains($description, self::NEW_FORMAT_MARKER)) {
            return false;
        }

        if (str_starts_with($subject, 'appointment created:')) {
            return false;
        }

        if (! preg_match('/^scheduled an? (?:free |paid )?appointment$/', $subject)) {
            return false;
        }

        $activityType = $log->activity_type ?? '';

        return $activityType === '' || $activityType === 'activity';
    }

    /**
     * @param  array<int>  $excludeBookingIds
     */
    public static function resolveBookingAppointment(ActivitiesLog $log, array $excludeBookingIds = []): ?BookingAppointment
    {
        if (! $log->client_id) {
            return null;
        }

        $logCreatedAt = Carbon::parse($log->created_at);
        $hints = self::parseLegacyDescriptionHints((string) ($log->description ?? ''));

        $tightCandidates = self::candidateQuery($log, $excludeBookingIds, $logCreatedAt, 5, 1)->get();
        $match = self::pickBestMatch($tightCandidates, $log, $hints, 30, 0);

        if ($match !== null) {
            return $match;
        }

        $wideCandidates = self::candidateQuery($log, $excludeBookingIds, $logCreatedAt, 30, 2)->get();

        return self::pickBestMatch($wideCandidates, $log, $hints, 55, 15);
    }

    /**
     * @return array{subject: string, description: string}
     */
    public static function refreshedFields(BookingAppointment $appointment): array
    {
        return [
            'subject' => AppointmentActivityDescription::activitySubject($appointment->service_id),
            'description' => AppointmentActivityDescription::buildDescription($appointment),
        ];
    }

    /**
     * @return array{appointment_date: ?string, timeslot: ?string, enquiry_fragment: ?string}
     */
    public static function parseLegacyDescriptionHints(string $description): array
    {
        $hints = [
            'appointment_date' => null,
            'timeslot' => null,
            'enquiry_fragment' => null,
        ];

        if ($description === '') {
            return $hints;
        }

        if (preg_match('/@\s*([^<]+)/', $description, $timeMatch)) {
            $hints['timeslot'] = trim(html_entity_decode(strip_tags($timeMatch[1])));
        }

        $dayMonth = null;
        $year = null;

        if (preg_match('/>(\d{1,2}\s+[A-Za-z]{3})</', $description, $dayMatch)) {
            $dayMonth = trim($dayMatch[1]);
        }

        if (preg_match('/line-height:\s*21px[^>]*>(\d{4})</', $description, $yearMatch)) {
            $year = trim($yearMatch[1]);
        }

        if ($dayMonth && $year) {
            try {
                $hints['appointment_date'] = Carbon::parse($dayMonth.' '.$year)->format('Y-m-d');
            } catch (\Throwable) {
                $hints['appointment_date'] = null;
            }
        }

        if (preg_match_all('/<span class="text-semi-bold">([^<]*)<\/span>/', $description, $spanMatches)) {
            $parts = array_values(array_filter(array_map('trim', $spanMatches[1])));
            if ($parts !== []) {
                $hints['enquiry_fragment'] = (string) end($parts);
            }
        }

        return $hints;
    }

    /**
     * @param  iterable<BookingAppointment>  $candidates
     * @param  array{appointment_date: ?string, timeslot: ?string, enquiry_fragment: ?string}  $hints
     */
    private static function pickBestMatch(
        iterable $candidates,
        ActivitiesLog $log,
        array $hints,
        int $minScore,
        int $minLead
    ): ?BookingAppointment {
        $scored = [];

        foreach ($candidates as $candidate) {
            $score = self::scoreCandidate($candidate, $log, $hints);
            if ($score >= 0) {
                $scored[] = ['appointment' => $candidate, 'score' => $score];
            }
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $best = $scored[0];
        $secondScore = $scored[1]['score'] ?? -1;

        if ($best['score'] < $minScore) {
            return null;
        }

        if (count($scored) > 1 && ($best['score'] - $secondScore) < $minLead) {
            return null;
        }

        return $best['appointment'];
    }

    /**
     * @param  array{appointment_date: ?string, timeslot: ?string, enquiry_fragment: ?string}  $hints
     */
    private static function scoreCandidate(BookingAppointment $booking, ActivitiesLog $log, array $hints): int
    {
        if (! $booking->created_at || ! $log->created_at) {
            return -1;
        }

        $createdDiffSeconds = abs(
            Carbon::parse($booking->created_at)->diffInSeconds(Carbon::parse($log->created_at))
        );

        if ($createdDiffSeconds > 1800) {
            return -1;
        }

        $score = 0;

        if ($createdDiffSeconds <= 120) {
            $score += 50;
        } elseif ($createdDiffSeconds <= 600) {
            $score += 35;
        } elseif ($createdDiffSeconds <= 1800) {
            $score += 20;
        }

        $description = (string) ($log->description ?? '');

        if (
            $hints['appointment_date']
            && $booking->appointment_datetime
            && Carbon::parse($booking->appointment_datetime)->format('Y-m-d') === $hints['appointment_date']
        ) {
            $score += 40;
        }

        if ($hints['timeslot'] && $booking->timeslot_full) {
            $hintSlot = strtolower($hints['timeslot']);
            $bookingSlot = strtolower((string) $booking->timeslot_full);

            if (
                str_contains($bookingSlot, $hintSlot)
                || str_contains($hintSlot, $bookingSlot)
            ) {
                $score += 30;
            }
        }

        if ($booking->enquiry_details && $description !== '') {
            $enquiry = trim((string) $booking->enquiry_details);

            if ($enquiry !== '' && stripos($description, $enquiry) !== false) {
                $score += 35;
            } elseif (
                $hints['enquiry_fragment']
                && (
                    stripos($enquiry, $hints['enquiry_fragment']) !== false
                    || stripos($hints['enquiry_fragment'], $enquiry) !== false
                )
            ) {
                $score += 25;
            }
        }

        return $score;
    }

    /**
     * @param  array<int>  $excludeBookingIds
     */
    private static function candidateQuery(
        ActivitiesLog $log,
        array $excludeBookingIds,
        Carbon $logCreatedAt,
        int $minutesBefore,
        int $minutesAfter
    ) {
        $from = $logCreatedAt->copy()->subMinutes($minutesBefore);
        $to = $logCreatedAt->copy()->addMinutes($minutesAfter);

        $query = BookingAppointment::query()
            ->where('client_id', $log->client_id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at');

        if ($excludeBookingIds !== []) {
            $query->whereNotIn('id', $excludeBookingIds);
        }

        return $query;
    }
}
