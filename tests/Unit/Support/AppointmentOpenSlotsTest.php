<?php

namespace Tests\Unit\Support;

use App\Support\AppointmentOpenSlots;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentOpenSlotsTest extends TestCase
{
    #[Test]
    public function it_excludes_disabled_and_past_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 10:05:00', 'Australia/Melbourne'));

        try {
            $slots = AppointmentOpenSlots::build(
                '09:00',
                '11:00',
                20,
                ['9:00 AM', '10:20 AM'],
                Carbon::parse('2026-08-20', 'Australia/Melbourne'),
                Carbon::parse('2026-08-20 10:05:00', 'Australia/Melbourne')
            );

            $starts = array_column($slots, 'start_24');
            $this->assertSame(['10:40'], $starts);
            $this->assertSame('10:40 AM – 11:00 AM', $slots[0]['display']);
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function it_keeps_future_day_slots_that_are_not_disabled(): void
    {
        $slots = AppointmentOpenSlots::build(
            '09:00',
            '10:00',
            30,
            ['9:30 AM'],
            Carbon::parse('2026-08-21', 'Australia/Melbourne'),
            Carbon::parse('2026-08-20 10:00:00', 'Australia/Melbourne')
        );

        $this->assertSame(['09:00'], array_column($slots, 'start_24'));
    }
}
