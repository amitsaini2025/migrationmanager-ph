<?php

namespace App\Services;

use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Support\AppointmentBookingWindow;
use App\Support\AppointmentOpenSlots;
use App\Support\BansalSchedulingServiceType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentOpenSlotService
{
    public function __construct(
        protected BansalApiClient $apiClient
    ) {}

    /**
     * @return array{
     *     success: bool,
     *     message?: string,
     *     duration: int,
     *     weeks: array<int, int>,
     *     start_time: string,
     *     end_time: string,
     *     disabled_dates: list<string>
     * }
     */
    public function availability(BookingAppointment $appointment): array
    {
        try {
            $payload = $this->schedulePayload($appointment);
            $response = $this->apiClient->getDateTimeBackend(
                $payload['specific_service'],
                $payload['service_type'],
                $payload['location'],
                0,
                $payload['is_paid'],
                $payload['preferred_language']
            );

            $disabledDates = $response['disabledatesarray']
                ?? $response['disableddatesarray']
                ?? [];

            return [
                'success' => true,
                'duration' => (int) ($response['duration'] ?? $appointment->duration_minutes ?? 30),
                'weeks' => self::mergeClosedWeekdays(array_map('intval', $response['weeks'] ?? [])),
                'start_time' => (string) ($response['start_time'] ?? '09:00'),
                'end_time' => (string) ($response['end_time'] ?? '17:00'),
                'disabled_dates' => array_values(array_filter(array_map('strval', is_array($disabledDates) ? $disabledDates : []))),
            ];
        } catch (\Throwable $e) {
            Log::warning('Public appointment availability lookup failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to load available dates. Please try again or contact our office.',
                'duration' => (int) ($appointment->duration_minutes ?: 30),
                'weeks' => self::mergeClosedWeekdays([]),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'disabled_dates' => [],
            ];
        }
    }

    /**
     * @return array{success: bool, message?: string, slots: list<array<string, string>>}
     */
    public function openSlotsForDate(BookingAppointment $appointment, Carbon $selectedDate): array
    {
        if (AppointmentBookingWindow::isInvalidEmailRescheduleDate($selectedDate)) {
            return [
                'success' => true,
                'slots' => [],
                'message' => AppointmentBookingWindow::EMAIL_RESCHEDULE_CLOSED_MESSAGE,
            ];
        }

        $availability = $this->availability($appointment);
        if (! ($availability['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $availability['message'] ?? 'Unable to load open slots.',
                'slots' => [],
            ];
        }

        $dayOfWeek = (int) $selectedDate->dayOfWeek;
        if (in_array($dayOfWeek, $availability['weeks'], true)) {
            return [
                'success' => true,
                'slots' => [],
            ];
        }

        $disabledDateLabels = array_map(
            fn (string $date): string => $this->normalizeDisabledDate($date),
            $availability['disabled_dates']
        );
        if (in_array($selectedDate->format('d/m/Y'), $disabledDateLabels, true)) {
            return [
                'success' => true,
                'slots' => [],
            ];
        }

        try {
            $payload = $this->schedulePayload($appointment);
            $response = $this->apiClient->getDisabledDateTime(
                $payload['specific_service'],
                $payload['service_type'],
                $payload['location'],
                $selectedDate->format('d/m/Y'),
                0,
                $payload['is_paid'],
                $payload['preferred_language']
            );

            $disabledLabels = $response['disabledtimeslotes'] ?? [];
            $slots = AppointmentOpenSlots::build(
                $availability['start_time'],
                $availability['end_time'],
                (int) $availability['duration'],
                is_array($disabledLabels) ? $disabledLabels : [],
                $selectedDate
            );

            return [
                'success' => true,
                'slots' => $slots,
            ];
        } catch (\Throwable $e) {
            Log::warning('Public appointment open-slot lookup failed', [
                'appointment_id' => $appointment->id,
                'date' => $selectedDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Unable to load open slots for that date. Please try another day or contact our office.',
                'slots' => [],
            ];
        }
    }

    /**
     * @return array{specific_service: string, service_type: string, location: string, is_paid: ?bool, preferred_language: ?string}
     */
    protected function schedulePayload(BookingAppointment $appointment): array
    {
        $location = strtolower(trim((string) ($appointment->location ?? 'melbourne')));
        if (! in_array($location, ['adelaide', 'melbourne'], true)) {
            $location = 'melbourne';
        }

        $specificService = match ((int) $appointment->service_id) {
            2 => 'paid-consultation',
            3 => 'overseas-enquiry',
            default => 'consultation',
        };

        [$isPaid, $preferredLanguage] = BansalSchedulingServiceType::melbourneApiExtrasFromValues(
            $location,
            (bool) $appointment->is_paid,
            $appointment->preferred_language
        );

        return [
            'specific_service' => $specificService,
            'service_type' => BansalSchedulingServiceType::fromEnquiryItem($appointment->noe_id, $location),
            'location' => $location,
            'is_paid' => $isPaid,
            'preferred_language' => $preferredLanguage,
        ];
    }

    protected function normalizeDisabledDate(string $date): string
    {
        $date = trim($date);
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date) === 1) {
            return $date;
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    /**
     * @param  list<int>  $weeks
     * @return list<int>
     */
    protected static function mergeClosedWeekdays(array $weeks): array
    {
        return array_values(array_unique(array_merge(
            $weeks,
            AppointmentBookingWindow::EMAIL_RESCHEDULE_CLOSED_WEEKDAYS
        )));
    }
}
