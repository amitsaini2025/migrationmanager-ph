<?php

namespace Tests\Unit;

use App\Models\BookingAppointment;
use App\Support\AppointmentEmailFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentEmailFormatterTest extends TestCase
{
    #[Test]
    public function it_prefers_stored_duration_minutes_when_valid(): void
    {
        $appointment = new BookingAppointment([
            'service_id' => 1,
            'is_paid' => true,
            'duration_minutes' => 60,
        ]);

        $this->assertSame(60, AppointmentEmailFormatter::resolveDurationMinutes($appointment));
    }

    #[Test]
    public function it_falls_back_to_paid_default_when_stored_duration_missing(): void
    {
        $appointment = new BookingAppointment([
            'service_id' => 1,
            'is_paid' => true,
            'duration_minutes' => null,
        ]);

        $this->assertSame(30, AppointmentEmailFormatter::resolveDurationMinutes($appointment));
    }

    #[Test]
    public function it_falls_back_to_free_default_when_stored_duration_missing(): void
    {
        $appointment = new BookingAppointment([
            'service_id' => 2,
            'is_paid' => false,
            'duration_minutes' => 0,
        ]);

        $this->assertSame(15, AppointmentEmailFormatter::resolveDurationMinutes($appointment));
    }

    #[Test]
    public function it_formats_start_time_only_from_hyphen_range(): void
    {
        $this->assertSame(
            '10:00 AM',
            AppointmentEmailFormatter::formatStartTime('10:00 AM - 10:20 AM')
        );
    }

    #[Test]
    public function it_formats_start_time_only_from_en_dash_range(): void
    {
        $this->assertSame(
            '11:00 AM',
            AppointmentEmailFormatter::formatStartTime('11:00 AM – 11:20 AM')
        );
    }

    #[Test]
    public function it_keeps_single_start_time_unchanged(): void
    {
        $this->assertSame(
            '11:00 AM',
            AppointmentEmailFormatter::formatStartTime('11:00 AM')
        );
    }

    #[Test]
    public function it_falls_back_to_appointment_datetime_when_timeslot_missing(): void
    {
        $datetime = now()->setTime(14, 30);

        $this->assertSame(
            $datetime->format('g:i A'),
            AppointmentEmailFormatter::formatStartTime(null, $datetime)
        );
    }
}
