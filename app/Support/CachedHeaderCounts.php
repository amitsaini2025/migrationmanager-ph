<?php

namespace App\Support;

use App\Models\BookingAppointment;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * Short-TTL cached counts for the CRM topbar (shared across all layout pages).
 */
class CachedHeaderCounts
{
    private static function ttl(): int
    {
        return max(1, (int) config('cache.header_counts_ttl', 60));
    }

    public static function notificationUnread(int $userId): int
    {
        return (int) Cache::remember(
            'header:notif_unread:v1:' . $userId,
            self::ttl(),
            static fn () => Notification::query()
                ->where('receiver_id', $userId)
                ->where('receiver_status', 0)
                ->count()
        );
    }

    public static function bookingPendingPaid(): int
    {
        return (int) Cache::remember(
            'header:booking_pending_paid:v1',
            self::ttl(),
            static fn () => BookingAppointment::query()
                ->where('status', 'pending')
                ->where('is_paid', 1)
                ->count()
        );
    }

    /**
     * Bust header caches after notification read/mark (optional call sites).
     */
    public static function forgetNotificationUnread(int $userId): void
    {
        Cache::forget('header:notif_unread:v1:' . $userId);
    }

    public static function forgetBookingPending(): void
    {
        Cache::forget('header:booking_pending_paid:v1');
    }
}
