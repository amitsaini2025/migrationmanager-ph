<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class BansalAppointmentWebhookController extends Controller
{
    public function __construct(
        protected AppointmentSyncService $syncService
    ) {}

    /**
     * Receive instant appointment pushes from the Bansal website.
     * POST /api/webhooks/bansal/appointments
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->authorizeRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $request->validate([
            'event' => 'nullable|string|max:50',
            'appointment' => 'required|array',
            'appointment.id' => 'required|integer|min:1',
        ]);

        // Use full request payload (not validated()) so nested fields are not stripped.
        // Poll sync also feeds processAppointment() the complete Bansal appointment array.
        $appointmentData = $request->input('appointment', []);
        $event = $request->input('event');

        try {
            $result = $this->syncService->syncPushedAppointment($appointmentData, $event);

            return response()->json([
                'success' => true,
                'result' => $result['result'],
                'bansal_id' => $result['bansal_id'],
            ]);
        } catch (Throwable $e) {
            Log::error('Bansal appointment webhook sync failed', [
                'event' => $event,
                'bansal_id' => $appointmentData['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sync failed',
            ], 500);
        }
    }

    protected function authorizeRequest(Request $request): bool
    {
        $token = config('services.bansal_appointment_webhook.token');

        if ($token === null || $token === '') {
            Log::warning('Bansal appointment webhook rejected: BANSAL_APPOINTMENT_WEBHOOK_TOKEN not configured');

            return false;
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-Webhook-Token')
            ?? $request->query('token');

        return is_string($provided) && hash_equals((string) $token, $provided);
    }
}
