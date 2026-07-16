<?php

namespace App\Http\Middleware;

use App\Models\StaffLoginLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records daily CRM presence into staff_login_logs (same table as login events).
 * Message is distinct so analytics that filter "Logged in" are unaffected.
 * Throttled; failures never block CRM requests.
 */
class TrackStaffCrmActivity
{
    public const ACTIVITY_MESSAGE = 'Active in CRM (session)';

    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->recordActivity($request);

        return $response;
    }

    protected function recordActivity(Request $request): void
    {
        try {
            $staff = Auth::guard('admin')->user();
            if (! $staff) {
                return;
            }

            $staffId = (int) $staff->id;
            if ($staffId < 1) {
                return;
            }

            $cacheKey = 'staff_crm_activity_' . $staffId;
            if (! Cache::add($cacheKey, 1, now()->addMinutes(self::THROTTLE_MINUTES))) {
                return;
            }

            $now = now();
            $ip = $request->ip();
            $userAgent = substr((string) $request->userAgent(), 0, 1000);

            $existing = StaffLoginLog::query()
                ->where('user_id', $staffId)
                ->where('message', self::ACTIVITY_MESSAGE)
                ->whereDate('created_at', $now->toDateString())
                ->first();

            if ($existing) {
                $existing->ip_address = $ip;
                $existing->user_agent = $userAgent;
                $existing->updated_at = $now;
                $existing->save();

                return;
            }

            $log = new StaffLoginLog;
            $log->level = 'info';
            $log->user_id = $staffId;
            $log->ip_address = $ip;
            $log->user_agent = $userAgent;
            $log->message = self::ACTIVITY_MESSAGE;
            $log->created_at = $now;
            $log->updated_at = $now;
            $log->save();
        } catch (\Throwable $e) {
            Log::warning('Staff CRM activity tracking failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
