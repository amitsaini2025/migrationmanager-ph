<?php

namespace Tests\Unit\Support;

use App\Support\AppointmentActionLink;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentActionLinkTest extends TestCase
{
    #[Test]
    public function it_builds_signed_email_button_urls(): void
    {
        $urls = AppointmentActionLink::emailButtonUrls(12);

        $this->assertIsArray($urls);
        $this->assertStringContainsString('/appointment/12/cancel', $urls['cancel']);
        $this->assertStringContainsString('/appointment/12/reschedule', $urls['reschedule']);
        $this->assertStringContainsString('/appointment/12/confirm', $urls['confirm']);
        $this->assertStringContainsString('signature=', $urls['cancel']);
    }

    #[Test]
    public function it_returns_null_without_appointment_id(): void
    {
        $this->assertNull(AppointmentActionLink::emailButtonUrls(null));
        $this->assertNull(AppointmentActionLink::emailButtonUrls(0));
    }
}
