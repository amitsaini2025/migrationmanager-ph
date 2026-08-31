<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\BookingAppointment;
use App\Support\BookingAppointmentStatus;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard appointment calendar feed.
 *
 * Appointments only (no personal events, hearings, or deadlines).
 * Does not change booking calendar controllers or views.
 */
class StaffPersonalCalendarFeedService
{
    public const DEFAULT_TYPE = 'paid';

    /**
     * Same types as BookingAppointmentsController::calendar().
     *
     * @var array<string, string>
     */
    public const CALENDAR_TYPES = [
        'paid' => 'Employer Sponsored',
        'jrp' => 'JRP',
        'education' => 'Education',
        'tourist' => 'Tourist',
        'adelaide' => 'Adelaide',
        'adelaide_education' => 'Adelaide Education',
        'ajay' => 'Ajay',
        'arun' => 'Arun',
    ];

    public function normalizeCalendarType(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return array_key_exists($type, self::CALENDAR_TYPES) ? $type : self::DEFAULT_TYPE;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function calendarTypeOptions(): array
    {
        $options = [];
        foreach (self::CALENDAR_TYPES as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label];
        }

        return $options;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function appointmentsForType(string $calendarType, Request $request): array
    {
        $calendarType = $this->normalizeCalendarType($calendarType);

        if (! Schema::hasTable('booking_appointments')) {
            return [];
        }

        $query = BookingAppointment::query()->with(['client', 'consultant']);
        $query->whereHas('consultant', function (Builder $q) use ($calendarType) {
            $q->where('calendar_type', $calendarType);
        });
        $query->whereNotIn('status', ['cancelled', 'no_show']);

        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($query);
        $this->applyDatetimeWindow($query, 'appointment_datetime', $request);

        return $query->orderBy('appointment_datetime')->get()
            ->map(fn (BookingAppointment $appointment) => $this->payloadFromBookingAppointment($appointment))
            ->values()
            ->all();
    }

    /**
     * @return array{today: int, this_week: int, upcoming: int}
     */
    public function statsForType(string $calendarType): array
    {
        $calendarType = $this->normalizeCalendarType($calendarType);
        $tz = config('app.timezone');
        $today = Carbon::today($tz);
        $weekEnd = $today->copy()->endOfWeek();

        if (! Schema::hasTable('booking_appointments')) {
            return ['today' => 0, 'this_week' => 0, 'upcoming' => 0];
        }

        $base = BookingAppointment::query();
        $base->whereHas('consultant', function (Builder $q) use ($calendarType) {
            $q->where('calendar_type', $calendarType);
        });
        $base->whereNotIn('status', ['cancelled', 'no_show']);
        StaffClientVisibility::restrictBookingAppointmentEloquentQuery($base);
        $base->where('appointment_datetime', '>=', $today);

        return [
            'today' => (clone $base)->whereDate('appointment_datetime', $today->toDateString())->count(),
            'this_week' => (clone $base)->where('appointment_datetime', '<=', $weekEnd)->count(),
            'upcoming' => (clone $base)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadFromBookingAppointment(BookingAppointment $appointment): array
    {
        $tz = config('app.timezone');
        $start = $appointment->appointment_datetime
            ? $appointment->appointment_datetime->copy()->timezone($tz)
            : Carbon::now($tz);
        $duration = (int) ($appointment->duration_minutes ?: 15);
        if ($duration < 15) {
            $duration = 15;
        }
        $end = $start->copy()->addMinutes($duration);

        $clientName = $this->clientDisplayName($appointment->client)
            ?: trim((string) ($appointment->client_name ?? ''))
            ?: 'Client';
        $status = (string) ($appointment->status ?? 'pending');
        $meetingType = trim((string) ($appointment->meeting_type ?? ''));
        $meetingTypeDisplay = $meetingType !== ''
            ? ucwords(str_replace('_', ' ', $meetingType))
            : 'Appointment';

        return [
            'id' => (string) $appointment->id,
            'booking_appointment_id' => $appointment->id,
            'title' => $clientName.' ('.$meetingTypeDisplay.')',
            'starts_at' => $start->toIso8601String(),
            'ends_at' => $end->toIso8601String(),
            'appointment_datetime' => $start->toIso8601String(),
            'duration_minutes' => $duration,
            'is_all_day' => false,
            'client_id' => $appointment->client_id,
            'client_id_encoded' => $appointment->client_id
                ? base64_encode(convert_uuencode((string) $appointment->client_id))
                : null,
            'client_name' => $clientName,
            'client_email' => $appointment->client_email ?: $this->clientEmail($appointment->client),
            'client_phone' => $appointment->client_phone,
            'location' => $appointment->location,
            'meeting_type' => $appointment->meeting_type,
            'meeting_type_label' => $meetingTypeDisplay,
            'status' => $status,
            'status_label' => $this->bookingStatusLabel($status),
            'is_paid' => (bool) $appointment->is_paid,
            'detail_url' => url('/booking/appointments/'.$appointment->id),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function toFullCalendarEvent(array $row): array
    {
        $status = (string) ($row['status'] ?? 'pending');
        $color = $this->colorForBookingStatus($status);

        return [
            'id' => (string) ($row['id'] ?? ''),
            'title' => (string) ($row['title'] ?? 'Appointment'),
            'start' => $row['starts_at'] ?? $row['appointment_datetime'] ?? null,
            'end' => $row['ends_at'] ?? null,
            'allDay' => false,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => $status === 'pending' ? '#1A2C40' : '#fff',
            'classNames' => ['event-appointment', 'event-status-'.$status],
            'url' => (string) ($row['detail_url'] ?? ''),
            'extendedProps' => $row,
        ];
    }

    protected function applyDatetimeWindow(Builder $query, string $column, Request $request): void
    {
        $tz = config('app.timezone');
        $floor = Carbon::today($tz)->startOfDay();

        if ($request->boolean('upcoming')) {
            $horizon = $floor->copy()->addMonths(6)->endOfDay();
            $query->where($column, '>=', $floor)->where($column, '<=', $horizon);

            return;
        }

        try {
            $rangeStart = $request->filled('start')
                ? Carbon::parse((string) $request->get('start'), $tz)
                : $floor->copy();
        } catch (Exception) {
            $rangeStart = $floor->copy();
        }

        if ($rangeStart->lt($floor)) {
            $rangeStart = $floor->copy();
        }

        $query->where($column, '>=', $rangeStart);

        if ($request->filled('end')) {
            try {
                $rangeEnd = Carbon::parse((string) $request->get('end'), $tz);
                $query->where($column, '<', $rangeEnd);
            } catch (Exception) {
                // Ignore invalid end; keep open-ended from start.
            }
        }
    }

    protected function clientDisplayName(?Admin $client): ?string
    {
        if (! $client) {
            return null;
        }

        $name = trim(($client->first_name ?? '').' '.($client->last_name ?? ''));

        return $name !== '' ? $name : ($client->client_id ?? 'Client #'.$client->id);
    }

    protected function clientEmail(?Admin $client): ?string
    {
        if (! $client) {
            return null;
        }

        $email = trim((string) ($client->email ?? ''));

        return $email !== '' ? $email : null;
    }

    protected function bookingStatusLabel(string $status): string
    {
        return BookingAppointmentStatus::label($status);
    }

    protected function colorForBookingStatus(string $status): string
    {
        return BookingAppointmentStatus::color($status);
    }
}
