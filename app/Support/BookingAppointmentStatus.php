<?php

namespace App\Support;

class BookingAppointmentStatus
{
    public const PENDING = 'pending';

    public const AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public const PAID = 'paid';

    public const CONFIRMED = 'confirmed';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no_show';

    public const RESCHEDULED = 'rescheduled';

    public const COLOR_AWAITING_CONFIRMATION = '#fd7e14';

    /**
     * Free consultations (service_id 2) start awaiting client or calling-team confirmation.
     * Paid bookings keep Payment Pending / Paid.
     */
    public static function forNewBooking(int $serviceId, ?string $paymentStatus = null): string
    {
        if ($serviceId === 2) {
            return self::AWAITING_CONFIRMATION;
        }

        return ($paymentStatus ?? self::PENDING) === 'completed' ? self::PAID : self::PENDING;
    }

    /**
     * New website/Bansal rows: keep paid and terminal statuses; treat new free bookings as pending confirmation.
     */
    public static function forNewWebsiteBooking(string $mappedStatus, bool $isPaidBooking): string
    {
        if (in_array($mappedStatus, [self::PAID, self::CANCELLED, self::COMPLETED, self::NO_SHOW], true)) {
            return $mappedStatus;
        }

        if ($isPaidBooking) {
            return $mappedStatus === self::CONFIRMED ? self::CONFIRMED : self::PENDING;
        }

        return self::AWAITING_CONFIRMATION;
    }

    /**
     * Do not let a later Bansal "confirmed" sync overwrite CRM pending-confirmation.
     * Terminal and paid updates from the website still apply.
     */
    public static function shouldApplyIncomingWebsiteStatus(
        string $currentStatus,
        string $incomingStatus,
        bool $crmPaid,
        bool $bansalUnpaidPending
    ): bool {
        $isTerminalFromBansal = in_array($incomingStatus, [self::CANCELLED, self::COMPLETED, self::NO_SHOW], true);

        if ($currentStatus === self::AWAITING_CONFIRMATION && ! $isTerminalFromBansal && $incomingStatus !== self::PAID) {
            return false;
        }

        if ($isTerminalFromBansal) {
            return true;
        }

        return ! ($crmPaid && $bansalUnpaidPending);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Payment Pending',
            self::AWAITING_CONFIRMATION => 'Pending',
            self::PAID => 'Paid',
            self::CONFIRMED => 'Confirmed',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
            self::RESCHEDULED => 'Rescheduled',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    public static function badgeClass(string $status): string
    {
        return match ($status) {
            self::PENDING => 'warning',
            self::AWAITING_CONFIRMATION => 'warning',
            self::PAID => 'primary',
            self::CONFIRMED => 'success',
            self::COMPLETED => 'info',
            self::CANCELLED => 'danger',
            self::NO_SHOW => 'dark',
            self::RESCHEDULED => 'primary',
            default => 'secondary',
        };
    }

    public static function color(string $status): string
    {
        return match ($status) {
            self::PENDING => '#ffc107',
            self::AWAITING_CONFIRMATION => self::COLOR_AWAITING_CONFIRMATION,
            self::PAID => '#007bff',
            self::CONFIRMED => '#28a745',
            self::COMPLETED => '#17a2b8',
            self::CANCELLED => '#dc3545',
            self::NO_SHOW => '#6c757d',
            self::RESCHEDULED => '#007bff',
            default => '#6c757d',
        };
    }

    public static function textColor(string $status): string
    {
        return $status === self::PENDING ? '#000' : '#fff';
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::PENDING,
            self::AWAITING_CONFIRMATION,
            self::PAID,
            self::CONFIRMED,
            self::COMPLETED,
            self::CANCELLED,
            self::NO_SHOW,
            self::RESCHEDULED,
        ];
    }

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
