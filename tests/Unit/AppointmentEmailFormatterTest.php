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
}
