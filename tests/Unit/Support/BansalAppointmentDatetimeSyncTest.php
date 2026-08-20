<?php

namespace Tests\Unit\Support;

use App\Support\BansalAppointmentDatetimeSync;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BansalAppointmentDatetimeSyncTest extends TestCase
{
    #[Test]
    public function it_parses_iso_datetime_and_date_time_fields(): void
    {
        $fromIso = BansalAppointmentDatetimeSync::parseIncoming([
            'appointment_datetime' => '2026-09-24T00:20:00.000000Z',
        ]);
        $fromParts = BansalAppointmentDatetimeSync::parseIncoming([
            'appointment_date' => '2026-09-24',
            'appointment_time' => '10:20:00',
        ]);

        $this->assertNotNull($fromIso);
        $this->assertNotNull($fromParts);
        $this->assertSame('10:20', $fromParts->format('H:i'));
    }

    #[Test]
    public function it_applies_a_newer_website_reschedule(): void
    {
        $incoming = Carbon::parse('2026-09-24 11:00:00');
        $current = Carbon::parse('2026-09-24 10:20:00');
        $websiteUpdatedAt = Carbon::parse('2026-08-20 10:15:00');
        $lastSyncedAt = Carbon::parse('2026-08-19 09:00:00');

        $this->assertTrue(BansalAppointmentDatetimeSync::shouldApply(
            $incoming,
            $current,
            $websiteUpdatedAt,
            $lastSyncedAt
        ));
    }

    #[Test]
    public function it_does_not_overwrite_when_times_already_match(): void
    {
        $time = Carbon::parse('2026-09-24 10:20:00');

        $this->assertFalse(BansalAppointmentDatetimeSync::shouldApply(
            $time->copy(),
            $time->copy()->addSeconds(12),
            Carbon::parse('2026-08-20 10:15:00'),
            Carbon::parse('2026-08-19 09:00:00')
        ));
    }

    #[Test]
    public function it_does_not_overwrite_crm_time_with_stale_website_time(): void
    {
        $incoming = Carbon::parse('2026-09-24 10:20:00');
        $current = Carbon::parse('2026-09-25 09:00:00');
        $websiteUpdatedAt = Carbon::parse('2026-08-19 09:00:00');
        $lastSyncedAt = Carbon::parse('2026-08-20 10:15:00');

        $this->assertFalse(BansalAppointmentDatetimeSync::shouldApply(
            $incoming,
            $current,
            $websiteUpdatedAt,
            $lastSyncedAt
        ));
    }

    #[Test]
    public function it_skips_when_website_updated_at_is_missing(): void
    {
        $this->assertFalse(BansalAppointmentDatetimeSync::shouldApply(
            Carbon::parse('2026-09-24 11:00:00'),
            Carbon::parse('2026-09-24 10:20:00'),
            null,
            Carbon::parse('2026-08-19 09:00:00')
        ));
    }

    #[Test]
    public function it_builds_a_timeslot_label(): void
    {
        $this->assertSame(
            '10:20 AM – 10:40 AM',
            BansalAppointmentDatetimeSync::timeslotFull(Carbon::parse('2026-09-24 10:20:00'), 20)
        );
    }
}
