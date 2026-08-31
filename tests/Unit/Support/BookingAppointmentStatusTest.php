<?php

namespace Tests\Unit\Support;

use App\Support\BookingAppointmentStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingAppointmentStatusTest extends TestCase
{
    #[Test]
    public function free_bookings_start_awaiting_confirmation(): void
    {
        $this->assertSame(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::forNewBooking(2)
        );
        $this->assertSame(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::forNewBooking(2, 'completed')
        );
    }

    #[Test]
    public function paid_bookings_keep_payment_statuses(): void
    {
        $this->assertSame(BookingAppointmentStatus::PENDING, BookingAppointmentStatus::forNewBooking(1));
        $this->assertSame(BookingAppointmentStatus::PENDING, BookingAppointmentStatus::forNewBooking(1, 'pending'));
        $this->assertSame(BookingAppointmentStatus::PAID, BookingAppointmentStatus::forNewBooking(1, 'completed'));
        $this->assertSame(BookingAppointmentStatus::PAID, BookingAppointmentStatus::forNewBooking(3, 'completed'));
    }

    #[Test]
    public function new_website_free_bookings_are_pending_confirmation(): void
    {
        $this->assertSame(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::forNewWebsiteBooking('confirmed', false)
        );
        $this->assertSame(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::forNewWebsiteBooking('pending', false)
        );
    }

    #[Test]
    public function new_website_paid_and_terminal_statuses_are_unchanged(): void
    {
        $this->assertSame(BookingAppointmentStatus::PAID, BookingAppointmentStatus::forNewWebsiteBooking('paid', true));
        $this->assertSame(BookingAppointmentStatus::PENDING, BookingAppointmentStatus::forNewWebsiteBooking('pending', true));
        $this->assertSame(BookingAppointmentStatus::CONFIRMED, BookingAppointmentStatus::forNewWebsiteBooking('confirmed', true));
        $this->assertSame(BookingAppointmentStatus::CANCELLED, BookingAppointmentStatus::forNewWebsiteBooking('cancelled', false));
        $this->assertSame(BookingAppointmentStatus::COMPLETED, BookingAppointmentStatus::forNewWebsiteBooking('completed', false));
        $this->assertSame(BookingAppointmentStatus::NO_SHOW, BookingAppointmentStatus::forNewWebsiteBooking('no_show', true));
    }

    #[Test]
    public function website_sync_does_not_overwrite_pending_confirmation_with_confirmed(): void
    {
        $this->assertFalse(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::CONFIRMED,
            false,
            false
        ));
        $this->assertFalse(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::PENDING,
            false,
            true
        ));
        $this->assertTrue(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::CANCELLED,
            false,
            false
        ));
        $this->assertTrue(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::AWAITING_CONFIRMATION,
            BookingAppointmentStatus::PAID,
            false,
            false
        ));
    }

    #[Test]
    public function existing_paid_protection_still_blocks_unpaid_pending_downgrade(): void
    {
        $this->assertFalse(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::PAID,
            BookingAppointmentStatus::PENDING,
            true,
            true
        ));
        $this->assertTrue(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::PAID,
            BookingAppointmentStatus::CANCELLED,
            true,
            false
        ));
        $this->assertTrue(BookingAppointmentStatus::shouldApplyIncomingWebsiteStatus(
            BookingAppointmentStatus::CONFIRMED,
            BookingAppointmentStatus::COMPLETED,
            false,
            false
        ));
    }

    #[Test]
    public function pending_label_stays_distinct_from_payment_pending(): void
    {
        $this->assertSame('Pending', BookingAppointmentStatus::label(BookingAppointmentStatus::AWAITING_CONFIRMATION));
        $this->assertSame('Payment Pending', BookingAppointmentStatus::label(BookingAppointmentStatus::PENDING));
        $this->assertSame('#fd7e14', BookingAppointmentStatus::color(BookingAppointmentStatus::AWAITING_CONFIRMATION));
        $this->assertSame('#ffc107', BookingAppointmentStatus::color(BookingAppointmentStatus::PENDING));
    }

    #[Test]
    public function all_status_values_fit_the_widened_column(): void
    {
        foreach (BookingAppointmentStatus::values() as $status) {
            $this->assertLessThanOrEqual(32, strlen($status), $status.' exceeds the widened status column.');
        }

        $this->assertGreaterThan(11, strlen(BookingAppointmentStatus::AWAITING_CONFIRMATION));
    }
}
