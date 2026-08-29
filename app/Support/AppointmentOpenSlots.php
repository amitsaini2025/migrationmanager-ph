<?php

namespace App\Support;

use Carbon\Carbon;

class AppointmentOpenSlots
{
    /**
     * Build open time slots for a date, excluding disabled 12-hour labels.
     *
     * @param  list<string>|array<int, string>  $disabledLabels
     * @return list<array{start_label: string, end_label: string, start_24: string, display: string}>
     */
    public static function build(
        string $startTime,
        string $endTime,
        int $durationMinutes,
        array $disabledLabels,
        Carbon $selectedDate,
        ?Carbon $now = null
    ): array {
        $durationMinutes = max(5, $durationMinutes);
        $now ??= now();
        $startMinutes = self::parseMinutes($startTime);
        $endMinutes = self::parseMinutes($endTime);
        if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
            return [];
        }

        $disabled = [];
        foreach ($disabledLabels as $label) {
            $normalized = self::normalizeLabel((string) $label);
            if ($normalized !== '') {
                $disabled[$normalized] = true;
            }
        }

        $isToday = $selectedDate->isSameDay($now);
        $slots = [];

        for ($cursor = $startMinutes; $cursor < $endMinutes; $cursor += $durationMinutes) {
            $slotEnd = $cursor + $durationMinutes;
            $startLabel = self::minutesToLabel($cursor);
            $endLabel = self::minutesToLabel($slotEnd);

            if (isset($disabled[self::normalizeLabel($startLabel)])) {
                continue;
            }

            if ($isToday) {
                $slotStart = $selectedDate->copy()->startOfDay()->addMinutes($cursor);
                if ($slotStart->lessThanOrEqualTo($now)) {
                    continue;
                }
            }

            $start24 = sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60);
            $slots[] = [
                'start_label' => $startLabel,
                'end_label' => $endLabel,
                'start_24' => $start24,
                'display' => $startLabel,
            ];
        }

        return $slots;
    }

    public static function parseMinutes(string $time): ?int
    {
        $time = trim($time);
        if (preg_match('/^(\d{1,2}):(\d{2})\s*(AM|PM)?$/i', $time, $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        $meridiem = strtoupper($matches[3] ?? '');

        if ($meridiem === 'PM' && $hour !== 12) {
            $hour += 12;
        }
        if ($meridiem === 'AM' && $hour === 12) {
            $hour = 0;
        }

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return ($hour * 60) + $minute;
    }

    public static function minutesToLabel(int $minutes): string
    {
        $minutes = $minutes % (24 * 60);
        $hour = intdiv($minutes, 60);
        $minute = $minutes % 60;
        $meridiem = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return $hour12.':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).' '.$meridiem;
    }

    public static function normalizeLabel(string $label): string
    {
        $label = strtoupper(trim(preg_replace('/\s+/', ' ', $label) ?? $label));

        return str_replace(['.', '–', '—'], ['', '-', '-'], $label);
    }
}
