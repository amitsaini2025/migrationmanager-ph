<?php

namespace Tests\Unit\Support;

use App\Support\AppointmentBookingWindow;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentBookingWindowTest extends TestCase
{
    #[Test]
    public function it_rejects_same_day_in_office_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-27 16:00:00', 'Australia/Melbourne'));

        try {
            $todaySlot = Carbon::parse('2026-08-27 14:00:00', 'Australia/Melbourne');
            $this->assertTrue(AppointmentBookingWindow::isOnOrBeforeToday($todaySlot));
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function it_allows_tomorrow_in_office_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-27 16:00:00', 'Australia/Melbourne'));

        try {
            $tomorrowSlot = Carbon::parse('2026-08-28 09:00:00', 'Australia/Melbourne');
            $this->assertFalse(AppointmentBookingWindow::isOnOrBeforeToday($tomorrowSlot));
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function it_rejects_today_even_when_the_clock_is_already_past_the_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-27 21:00:00', 'Australia/Melbourne'));

        try {
            $earlierToday = Carbon::parse('2026-08-27 02:00:00', 'Australia/Melbourne');
            $this->assertTrue(AppointmentBookingWindow::isOnOrBeforeToday($earlierToday));
        } finally {
            Carbon::setTestNow();
        }
    }
}
