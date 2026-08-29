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

    #[Test]
    public function it_treats_friday_saturday_sunday_as_closed_for_email_reschedule(): void
    {
        $this->assertTrue(AppointmentBookingWindow::isEmailRescheduleClosedWeekday(
            Carbon::parse('2026-08-28', 'Australia/Melbourne') // Friday
        ));
        $this->assertTrue(AppointmentBookingWindow::isEmailRescheduleClosedWeekday(
            Carbon::parse('2026-08-29', 'Australia/Melbourne') // Saturday
        ));
        $this->assertTrue(AppointmentBookingWindow::isEmailRescheduleClosedWeekday(
            Carbon::parse('2026-08-30', 'Australia/Melbourne') // Sunday
        ));
        $this->assertFalse(AppointmentBookingWindow::isEmailRescheduleClosedWeekday(
            Carbon::parse('2026-08-31', 'Australia/Melbourne') // Monday
        ));
    }

    #[Test]
    public function it_skips_weekend_when_computing_earliest_email_reschedule_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-27 10:00:00', 'Australia/Melbourne')); // Thursday

        try {
            // Tomorrow is Friday → earliest open is Monday 31 Aug
            $this->assertSame(
                '2026-08-31',
                AppointmentBookingWindow::earliestEmailRescheduleDate()->toDateString()
            );
            $this->assertTrue(AppointmentBookingWindow::isInvalidEmailRescheduleDate(
                Carbon::parse('2026-08-27 15:00:00', 'Australia/Melbourne')
            ));
            $this->assertTrue(AppointmentBookingWindow::isInvalidEmailRescheduleDate(
                Carbon::parse('2026-08-28 10:00:00', 'Australia/Melbourne')
            ));
            $this->assertFalse(AppointmentBookingWindow::isInvalidEmailRescheduleDate(
                Carbon::parse('2026-08-31 10:00:00', 'Australia/Melbourne')
            ));
        } finally {
            Carbon::setTestNow();
        }
    }
}
