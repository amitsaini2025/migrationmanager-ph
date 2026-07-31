<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\BookingAppointment;
use App\Models\AppointmentSyncLog;
use App\Models\ActivitiesLog;
use App\Support\AppointmentActivityDescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class AppointmentSyncService
{
    protected BansalApiClient $apiClient;
    protected ClientMatchingService $clientMatcher;
    protected ConsultantAssignmentService $consultantAssigner;
    
    protected AppointmentSyncLog $syncLog;

    public function __construct(
        BansalApiClient $apiClient,
        ClientMatchingService $clientMatcher,
        ConsultantAssignmentService $consultantAssigner
    ) {
        $this->apiClient = $apiClient;
        $this->clientMatcher = $clientMatcher;
        $this->consultantAssigner = $consultantAssigner;
    }

    /**
     * Sync recent appointments (main polling method)
     */
    public function syncRecentAppointments(int $minutes = 10): array
    {
        // Create sync log
        $this->syncLog = AppointmentSyncLog::create([
            'sync_type' => 'polling',
            'started_at' => now(),
            'status' => 'running'
        ]);

        $stats = [
            'fetched' => 0,
            'new' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            Log::info('Starting appointment sync', ['minutes' => $minutes]);

            // Fetch appointments from Bansal API
            $appointments = $this->apiClient->getRecentAppointments($minutes);
            $stats['fetched'] = count($appointments);

            Log::info("Fetched {$stats['fetched']} appointments from Bansal API");

            // Process each appointment
            foreach ($appointments as $appointmentData) {
                try {
                    $result = $this->processAppointment($appointmentData);
                    
                    if ($result === 'new') {
                        $stats['new']++;
                    } elseif ($result === 'updated') {
                        $stats['updated']++;
                    } elseif ($result === 'skipped') {
                        $stats['skipped']++;
                    }
                } catch (Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'appointment_id' => $appointmentData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to process appointment', [
                        'appointment_id' => $appointmentData['id'] ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Update sync log
            $this->syncLog->update([
                'completed_at' => now(),
                'status' => $stats['failed'] > 0 ? 'failed' : 'success',
                'appointments_fetched' => $stats['fetched'],
                'appointments_new' => $stats['new'],
                'appointments_updated' => $stats['updated'],
                'appointments_skipped' => $stats['skipped'],
                'appointments_failed' => $stats['failed'],
                'sync_details' => json_encode($stats)
            ]);

            Log::info('Appointment sync completed', $stats);

            return $stats;
        } catch (Exception $e) {
            $this->syncLog->update([
                'completed_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'appointments_fetched' => $stats['fetched'],
                'appointments_failed' => $stats['failed']
            ]);

            Log::error('Appointment sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process single appointment
     */
    protected function processAppointment(array $appointmentData): string
    {
        // Debug: Check $appointmentData array
        Log::info('Processing appointment data', [
            'full_data' => $appointmentData,
            'status' => $appointmentData['status'] ?? 'not_set',
            'is_paid' => $appointmentData['is_paid'] ?? 'not_set',
            'payment_data' => $appointmentData['payment'] ?? 'not_set'
        ]);
        
        $bansalId = $appointmentData['id'];

        // Check if already exists — selectively heal payment/status/cancel only
        $existingAppointment = BookingAppointment::where('bansal_appointment_id', $bansalId)->first();

        if ($existingAppointment) {
            return $this->updateExistingAppointmentPaymentAndStatus($existingAppointment, $appointmentData);
        }

        // Match or create client
        $client = $this->clientMatcher->findOrCreateClient($appointmentData);

        // Calculate service_id and noe_id BEFORE assigning consultant
        // These are needed by assignConsultant() to determine calendar type
        $serviceId = $this->mapServiceId($appointmentData);
        $noeId = $this->mapNoeId($appointmentData);
        $rawLocation = $appointmentData['location'] ?? null;
        $location = $rawLocation ? strtolower(trim((string) $rawLocation)) : null;
        $inpersonAddress = $location ? $this->mapInpersonAddress($location) : null;

        // Prepare appointment data with calculated values for consultant assignment
        $appointmentDataForConsultant = array_merge($appointmentData, [
            'service_id' => $serviceId,
            'noe_id' => $noeId,
            'inperson_address' => $inpersonAddress,
            'location' => $location,
            'specific_service' => $appointmentData['specific_service'] ?? null,
        ]);

        // Assign consultant (now has access to service_id and noe_id)
        $consultant = $this->consultantAssigner->assignConsultant($appointmentDataForConsultant);

        // Map status
        $status = $this->mapStatus($appointmentData['status'] ?? 'pending');

        // Create appointment record
        $appointment = BookingAppointment::create([
            'bansal_appointment_id' => $bansalId,
            'order_hash' => $appointmentData['order_hash'] ?? null,
            
            'client_id' => $client?->id,
            'consultant_id' => $consultant?->id,
            
            'client_name' => $appointmentData['full_name'],
            'client_email' => $appointmentData['email'],
            'client_phone' => $appointmentData['phone'] ?? null,
            'client_timezone' => 'Australia/Melbourne',
            
            'appointment_datetime' => Carbon::parse($appointmentData['appointment_datetime']),
            'timeslot_full' => $appointmentData['appointment_time'] ?? null,
            'duration_minutes' => $appointmentData['duration_minutes'] ?? 15,
            'location' => $location ?? $appointmentData['location'],
            'inperson_address' => $inpersonAddress,
            'meeting_type' => $this->mapMeetingType($appointmentData['meeting_type'] ?? null),
            'preferred_language' => $appointmentData['preferred_language'] ?? 'English',
            
            'service_id' => $serviceId,
            'noe_id' => $noeId,
            'enquiry_type' => $appointmentData['enquiry_type'] ?? null,
            'service_type' => $appointmentData['service_type'] ?? null,
            'enquiry_details' => $appointmentData['enquiry_details'] ?? null,
            
            'status' => $status,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            
            'is_paid' => $appointmentData['is_paid'] ?? false,
            'amount' => $appointmentData['amount'] ?? 0,
            'discount_amount' => $appointmentData['discount_amount'] ?? 0,
            'final_amount' => $appointmentData['final_amount'] ?? 0,
            'promo_code' => $appointmentData['promo_code'] ?? null,
            'payment_status' => $this->mapPaymentStatus($appointmentData),
            'payment_method' => $appointmentData['payment']['payment_method'] ?? null,
            'paid_at' => !empty($appointmentData['payment']['paid_at']) 
                ? Carbon::parse($appointmentData['payment']['paid_at']) 
                : null,
            
            'synced_from_bansal_at' => now(),
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ]);

        Log::info('Created new appointment', [
            'bansal_id' => $bansalId,
            'crm_id' => $appointment->id,
            'client_id' => $client?->id,
            'consultant_id' => $consultant?->id
        ]);

        // Create activity log for synced appointment (only if client exists)
        if ($appointment->client_id) {
            try {
                $this->createActivityLogForSyncedAppointment($appointment, $serviceId, $noeId);
            } catch (Exception $e) {
                // Log error but don't fail the sync process
                Log::warning('Failed to create activity log for synced appointment', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return 'new';
    }

    /**
     * Selectively update an existing CRM appointment from Bansal (payment/status/cancel only).
     * Does not overwrite consultant, client, notes, datetime, meeting type, or other CRM-owned fields.
     * Avoids downgrading CRM-local paid state when Bansal still shows unpaid (e.g. CRM paid first).
     */
    protected function updateExistingAppointmentPaymentAndStatus(
        BookingAppointment $appointment,
        array $appointmentData
    ): string {
        $bansalId = $appointmentData['id'] ?? $appointment->bansal_appointment_id;
        $status = $this->mapStatus($appointmentData['status'] ?? 'pending');
        $paymentStatus = $this->mapPaymentStatus($appointmentData);
        $isPaid = filter_var($appointmentData['is_paid'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($paymentStatus === 'completed' || $status === 'paid') {
            $isPaid = true;
            if ($paymentStatus === null && $status === 'paid') {
                $paymentStatus = 'completed';
            }
        }

        $crmPaid = (bool) $appointment->is_paid
            || $appointment->status === 'paid'
            || $appointment->payment_status === 'completed';

        $updates = [];

        if ($appointment->status !== $status) {
            $bansalUnpaidPending = $status === 'pending' && !$isPaid;
            $isTerminalFromBansal = in_array($status, ['cancelled', 'completed', 'no_show'], true);

            if ($isTerminalFromBansal || !($crmPaid && $bansalUnpaidPending)) {
                $updates['status'] = $status;
            }
        }

        if ($isPaid && !$appointment->is_paid) {
            $updates['is_paid'] = true;
        }

        if ($paymentStatus !== null && $appointment->payment_status !== $paymentStatus) {
            $crmCompleted = $appointment->payment_status === 'completed';
            if (!($crmCompleted && $paymentStatus !== 'completed')) {
                $updates['payment_status'] = $paymentStatus;
            }
        }

        $paymentMethod = $appointmentData['payment']['payment_method'] ?? null;
        if (!empty($paymentMethod) && $appointment->payment_method !== $paymentMethod) {
            if (empty($appointment->payment_method) || $isPaid) {
                $updates['payment_method'] = $paymentMethod;
            }
        }

        if (!empty($appointmentData['payment']['paid_at']) && empty($appointment->paid_at)) {
            $updates['paid_at'] = Carbon::parse($appointmentData['payment']['paid_at']);
        }

        foreach (['amount', 'discount_amount', 'final_amount'] as $amountField) {
            if (!array_key_exists($amountField, $appointmentData) || $appointmentData[$amountField] === null) {
                continue;
            }
            if (!$isPaid && (float) $appointmentData[$amountField] == 0.0 && (float) $appointment->{$amountField} > 0) {
                continue;
            }
            if ((string) $appointment->{$amountField} !== (string) $appointmentData[$amountField]) {
                $updates[$amountField] = $appointmentData[$amountField];
            }
        }

        $effectiveStatus = $updates['status'] ?? $appointment->status;

        if ($effectiveStatus === 'confirmed' && empty($appointment->confirmed_at)) {
            $updates['confirmed_at'] = now();
        }
        if ($effectiveStatus === 'completed' && empty($appointment->completed_at)) {
            $updates['completed_at'] = now();
        }
        if ($effectiveStatus === 'cancelled' && empty($appointment->cancelled_at)) {
            $updates['cancelled_at'] = now();
        }

        $cancelReason = $appointmentData['cancellation_reason']
            ?? $appointmentData['cancel_reason']
            ?? null;
        if ($effectiveStatus === 'cancelled' && !empty($cancelReason) && empty($appointment->cancellation_reason)) {
            $updates['cancellation_reason'] = $cancelReason;
        }

        if ($updates === []) {
            Log::info('Appointment already exists, no payment/status changes', ['bansal_id' => $bansalId]);
            return 'skipped';
        }

        $updates['last_synced_at'] = now();
        $updates['sync_status'] = 'synced';
        $updates['sync_error'] = null;

        $appointment->fill($updates)->save();

        Log::info('Updated existing appointment payment/status from Bansal', [
            'bansal_id' => $bansalId,
            'crm_id' => $appointment->id,
            'updated_fields' => array_keys($updates),
        ]);

        return 'updated';
    }

    /**
     * Map location to inperson_address (legacy compatibility)
     */
    protected function mapInpersonAddress(string $location): ?int
    {
        return match (strtolower(trim($location))) {
            'adelaide' => 1,
            'melbourne' => 2,
            default => null,
        };
    }

    /**
     * Map service_id from Bansal data
     */
    protected function mapServiceId(array $appointmentData): ?int
    {
        // Check if paid
        if (!empty($appointmentData['is_paid']) && $appointmentData['is_paid'] === true) {

            if (!empty($appointmentData['specific_service']) && $appointmentData['specific_service'] === 'overseas-enquiry') {
                return 3; // Paid Overseas
            } elseif (!empty($appointmentData['specific_service']) && $appointmentData['specific_service'] === 'paid-consultation') {
                return 1; // Paid Migration advice
            } 
        }
        
        if (!empty($appointmentData['final_amount']) && $appointmentData['final_amount'] > 0) {
            if (!empty($appointmentData['specific_service']) && $appointmentData['specific_service'] === 'overseas-enquiry') {
                return 3; // Paid Overseas
            } elseif (!empty($appointmentData['specific_service']) && $appointmentData['specific_service'] === 'paid-consultation') {
                return 1; // Paid Migration advice
            } 
        }

        return 2; // Free
    }

    /**
     * Map noe_id from enquiry_type
     */
    protected function mapNoeId(array $appointmentData): ?int
    {
        $serviceType = $appointmentData['service_type'] ?? null;

        if ($serviceType === null || $serviceType === '') {
            return null;
        }

        return match ($serviceType) {
            'permanent-residency' => 1,
            'gsm-visas' => 1,
            'temporary-residency' => 2,
            'jrp-skill-assessment' => 3,
            'tourist-visa' => 4,
            'education-visa' => 5,
            'complex-matters' => 6,
            'visa-cancellation' => 7,
            'international-migration' => 8,
            'eoi-roi' => 9,
            'employer-sponsored' => 10,
            'family-visas' => 11,
            'citizenship' => 12,
            default => null,
        };
    }

    /**
     * Map meeting type from Bansal API to CRM enum values
     * Handles various formats and normalizes to: 'in_person', 'phone', 'video'
     */
    protected function mapMeetingType(?string $meetingType): string
    {
        // Handle NULL or empty string
        if (empty($meetingType)) {
            return 'in_person'; // Default value
        }

        // Normalize: convert to lowercase and replace spaces/hyphens with underscores
        $normalized = strtolower(trim($meetingType));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match($normalized) {
            'in_person', 'inperson', 'in-person', 'in person', 'office', 'onsite' => 'in_person',
            'phone', 'telephone', 'call' => 'phone',
            'video', 'videocall', 'video_call', 'zoom', 'online' => 'video',
            default => 'in_person' // Default fallback
        };
    }

    /**
     * Map status from Bansal to CRM
     */
    protected function mapStatus(string $bansalStatus): string
    {
        $normalized = strtolower(trim($bansalStatus));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'pending' => 'pending',
            'paid' => 'paid',
            'confirmed' => 'confirmed',
            'completed' => 'completed',
            'cancelled', 'canceled' => 'cancelled',
            'no_show' => 'no_show',
            default => 'pending',
        };
    }

    /**
     * Map payment status
     */
    protected function mapPaymentStatus(array $appointmentData): ?string
    {
        if (empty($appointmentData['payment'])) {
            return null;
        }

        $paymentStatus = $appointmentData['payment']['status'] ?? null;

        return match($paymentStatus) {
            'completed', 'succeeded' => 'completed',
            'pending', 'processing' => 'pending',
            'failed' => 'failed',
            'refunded' => 'refunded',
            default => null
        };
    }

    /**
     * Backfill historical appointments
     */
    public function backfillHistoricalData(Carbon $startDate, Carbon $endDate): array
    {
        $this->syncLog = AppointmentSyncLog::create([
            'sync_type' => 'backfill',
            'started_at' => now(),
            'status' => 'running'
        ]);

        $stats = [
            'fetched' => 0,
            'new' => 0,
            'skipped' => 0,
            'failed' => 0
        ];

        try {
            Log::info('Starting backfill', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ]);

            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                $response = $this->apiClient->getAppointmentsByDateRange(
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                    $page
                );

                $appointments = $response['data'] ?? [];
                $pagination = $response['pagination'] ?? [];

                $stats['fetched'] += count($appointments);

                foreach ($appointments as $appointmentData) {
                    try {
                        $result = $this->processAppointment($appointmentData);
                        if ($result === 'new') {
                            $stats['new']++;
                        } else {
                            $stats['skipped']++;
                        }
                    } catch (Exception $e) {
                        $stats['failed']++;
                        Log::error('Backfill: Failed to process appointment', [
                            'appointment_id' => $appointmentData['id'] ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Check if more pages
                $hasMore = !empty($pagination['current_page']) && 
                          !empty($pagination['last_page']) && 
                          $pagination['current_page'] < $pagination['last_page'];
                $page++;

                // Add delay between pages to avoid rate limiting
                if ($hasMore) {
                    sleep(2);
                }
            }

            $this->syncLog->update([
                'completed_at' => now(),
                'status' => 'success',
                'appointments_fetched' => $stats['fetched'],
                'appointments_new' => $stats['new'],
                'appointments_skipped' => $stats['skipped'],
                'appointments_failed' => $stats['failed']
            ]);

            Log::info('Backfill completed', $stats);

            return $stats;
        } catch (Exception $e) {
            $this->syncLog->update([
                'completed_at' => now(),
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Push status update to Bansal API.
     */
    public function pushStatusUpdate(BookingAppointment $appointment, string $status, ?string $reason = null): ?array
    {
        if (empty($appointment->bansal_appointment_id)) {
            Log::warning('Skipping Bansal status update: missing bansal_appointment_id', [
                'appointment_id' => $appointment->id,
                'status' => $status,
            ]);
            return null;
        }

        $type = match ($status) {
            'cancelled' => 'cancel',
            'completed' => 'complete',
            'confirmed' => 'confirm',
            'paid' => 'pay',
            default => null,
        };

        if ($type === null) {
            Log::warning('Skipping Bansal status update: unsupported status type', [
                'appointment_id' => $appointment->id,
                'status' => $status,
            ]);
            return null;
        }

        try {
            $response = $this->apiClient->updateAppointmentStatus(
                $appointment->bansal_appointment_id,
                $type,
                $reason
            );

            Log::info('Bansal appointment status updated', [
                'appointment_id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'status' => $status,
                'response' => $response,
            ]);

            return $response;
        } catch (Exception $e) {
            Log::error('Failed to push status update to Bansal API', [
                'appointment_id' => $appointment->id,
                'bansal_appointment_id' => $appointment->bansal_appointment_id,
                'status' => $status,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create activity log entry for synced appointment
     * 
     * @param BookingAppointment $appointment
     * @param int|null $serviceId
     * @param int|null $noeId
     * @return void
     */
    protected function createActivityLogForSyncedAppointment(BookingAppointment $appointment, ?int $serviceId, ?int $noeId): void
    {
        ActivitiesLog::create(
            AppointmentActivityDescription::buildActivityLogPayload(
                $appointment,
                (int) $appointment->client_id,
                $serviceId,
                $noeId
            )
        );
    }
}

