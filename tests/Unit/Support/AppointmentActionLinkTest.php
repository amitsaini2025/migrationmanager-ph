<?php

namespace Tests\Unit\Support;

use App\Models\BookingAppointment;
use App\Support\AppointmentActionLink;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentActionLinkTest extends TestCase
{
    #[Test]
    public function it_builds_signed_email_button_urls(): void
    {
        $slotAt = now()->addDay()->startOfHour();
        $urls = AppointmentActionLink::emailButtonUrls(12, $slotAt);

        $this->assertIsArray($urls);
        $this->assertStringContainsString('/appointment/12/cancel', $urls['cancel']);
        $this->assertStringContainsString('/appointment/12/reschedule', $urls['reschedule']);
        $this->assertStringContainsString('/appointment/12/confirm', $urls['confirm']);
        $this->assertStringContainsString('signature=', $urls['cancel']);
        $this->assertStringContainsString('at='.$slotAt->getTimestamp(), $urls['cancel']);
        $this->assertStringContainsString('at='.$slotAt->getTimestamp(), $urls['confirm']);
        $this->assertStringNotContainsString('at=', $urls['reschedule']);
    }

    #[Test]
    public function it_returns_null_without_appointment_id(): void
    {
        $this->assertNull(AppointmentActionLink::emailButtonUrls(null));
        $this->assertNull(AppointmentActionLink::emailButtonUrls(0));
    }

    #[Test]
    public function it_allows_legacy_cancel_confirm_links_without_slot_param(): void
    {
        $appointment = new BookingAppointment([
            'appointment_datetime' => now()->addDays(2),
        ]);

        $this->assertTrue(AppointmentActionLink::matchesCurrentSlot($appointment, null));
        $this->assertTrue(AppointmentActionLink::matchesCurrentSlot($appointment, ''));
    }

    #[Test]
    public function it_rejects_cancel_confirm_links_after_reschedule_slot_changes(): void
    {
        $original = now()->addDay()->startOfHour();
        $rescheduled = $original->copy()->addHours(2);

        $appointment = new BookingAppointment([
            'appointment_datetime' => $rescheduled,
        ]);

        $this->assertFalse(AppointmentActionLink::matchesCurrentSlot($appointment, $original->getTimestamp()));
        $this->assertTrue(AppointmentActionLink::matchesCurrentSlot($appointment, $rescheduled->getTimestamp()));
        $this->assertTrue(AppointmentActionLink::matchesCurrentSlot($appointment, (string) $rescheduled->getTimestamp()));
    }
}
