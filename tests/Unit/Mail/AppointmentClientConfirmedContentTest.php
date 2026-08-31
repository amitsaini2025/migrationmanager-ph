<?php

namespace Tests\Unit\Mail;

use App\Mail\AppointmentClientConfirmed;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentClientConfirmedContentTest extends TestCase
{
    #[Test]
    public function it_shows_start_time_only_after_email_confirm(): void
    {
        $html = (new AppointmentClientConfirmed($this->details()))->render();

        $this->assertStringContainsString('Appointment Confirmed - Bansal Immigration', $html);
        $this->assertStringContainsString('10:20 AM', $html);
        $this->assertStringNotContainsString('10:40 AM', $html);
        $this->assertStringContainsString('Tourist Visa', $html);
        $this->assertStringContainsString('In-Person', $html);
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        return [
            'client_name' => 'Vipul Kumar',
            'appointment_datetime' => now()->setTime(10, 20),
            'timeslot_full' => '10:20 AM-10:40 AM',
            'location' => 'melbourne',
            'meeting_type' => 'in_person',
            'service_type' => 'Tourist Visa',
        ];
    }
}
