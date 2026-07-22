<?php

namespace App\Jobs;

use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PushBansalAppointmentStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $appointmentId,
        protected string $status,
        protected ?string $reason = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BansalAppointmentRecoveryService $recoveryService): void
    {
        $appointment = BookingAppointment::find($this->appointmentId);

        if (!$appointment) {
            Log::warning('PushBansalAppointmentStatusJob: appointment not found', [
                'appointment_id' => $this->appointmentId,
                'status' => $this->status,
            ]);
            return;
        }

        $result = $recoveryService->syncStatus($appointment, $this->status, $this->reason);

        if ($result['synced']) {
            if ($result['bansal_appointment_id'] !== null) {
                $appointment->bansal_appointment_id = $result['bansal_appointment_id'];
            }

            $appointment->forceFill([
                'last_synced_at' => now(),
                'sync_status' => 'synced',
                'sync_error' => null,
            ])->save();

            return;
        }

        $appointment->forceFill([
            'sync_status' => 'error',
            'sync_error' => $result['error'],
        ])->save();

        throw new Exception($result['error'] ?? 'Failed to sync appointment status with Bansal API.');
    }
}


