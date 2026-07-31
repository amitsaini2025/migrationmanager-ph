<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\BookingAppointment;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class BansalAppointmentRecoveryService
{
    public const APPOINTMENT_NOT_FOUND_SYNC_ERROR = 'Appointment not found';
    public function __construct(
        protected BansalApiClient $apiClient,
        protected RetryInvalidEnquirySyncService $createService
    ) {
    }

    public static function isUnsyncedBansalId(?int $bansalAppointmentId): bool
    {
        return $bansalAppointmentId !== null
            && $bansalAppointmentId >= RetryInvalidEnquirySyncService::MIN_UNSYNCED_BANSAL_ID;
    }

    public static function shouldRecoverWithCreate(?string $errorMessage): bool
    {
        if ($errorMessage === null || $errorMessage === '') {
            return false;
        }

        $normalized = strtolower($errorMessage);

        return str_contains($normalized, 'appointment id is invalid')
            || str_contains($normalized, 'appointment not found')
            || str_contains($normalized, 'status code 404');
    }

    public static function matchesAppointmentNotFoundSyncError(?string $syncError): bool
    {
        if ($syncError === null || $syncError === '') {
            return false;
        }

        if (RetryInvalidEnquirySyncService::matchesInvalidEnquirySyncError($syncError)) {
            return false;
        }

        $normalized = strtolower($syncError);

        return str_contains($normalized, strtolower(self::APPOINTMENT_NOT_FOUND_SYNC_ERROR))
            || str_contains($normalized, 'status code 404');
    }

    /**
     * Earliest appointment date included in bulk 404 retry (today in app timezone, including past times today).
     */
    public static function notFoundRetryEarliestAppointmentDate(): Carbon
    {
        return Carbon::today(config('app.timezone'));
    }

    /**
     * Existing CRM rows that failed because Bansal could not find the linked appointment ID.
     */
    public function eligibleNotFoundQuery(bool $includePast = false): Builder
    {
        $query = BookingAppointment::query()
            ->where('sync_status', 'error')
            ->where(function (Builder $query): void {
                $query->where('sync_error', 'like', '%' . self::APPOINTMENT_NOT_FOUND_SYNC_ERROR . '%')
                    ->orWhere('sync_error', 'like', '%status code 404%');
            })
            ->where(function (Builder $query): void {
                foreach (RetryInvalidEnquirySyncService::invalidEnquirySyncErrors() as $invalidEnquiryError) {
                    $query->where('sync_error', '!=', $invalidEnquiryError);
                }
                $query->where('sync_error', 'not like', '%' . RetryInvalidEnquirySyncService::INVALID_ENQUIRY_SYNC_ERROR . '%');
            })
            ->where('status', '!=', 'cancelled');

        if (!$includePast) {
            $query->whereDate(
                'appointment_datetime',
                '>=',
                self::notFoundRetryEarliestAppointmentDate()
            );
        }

        return $query->orderBy('appointment_datetime');
    }

    /**
     * @return array{synced: bool, error: ?string, bansal_appointment_id: ?int, created_new: bool, action: ?string}
     */
    public function retryNotFoundSync(BookingAppointment $appointment): array
    {
        $existingId = $this->findExistingBansalAppointmentId($appointment);
        $result = $this->recoverWithCreate(
            $appointment,
            $appointment->sync_error ?? 'Appointment not found on Bansal website.'
        );

        $action = 'failed';
        if ($result['synced']) {
            if ($existingId !== null && $result['bansal_appointment_id'] === $existingId) {
                $action = 'linked';
            } elseif ($result['created_new']) {
                $action = 'created';
            } else {
                $action = 'linked';
            }
        } else {
            $appointment->forceFill([
                'sync_status' => 'error',
                'sync_error' => $result['error'],
            ])->save();
        }

        return array_merge($result, ['action' => $action]);
    }

    /**
     * @return array{synced: bool, error: ?string, bansal_appointment_id: ?int, created_new: bool}
     */
    public function syncReschedule(
        BookingAppointment $appointment,
        string $apiDate,
        string $apiTime,
        string $apiMeetingType,
        string $apiPreferredLanguage
    ): array {
        if (self::isUnsyncedBansalId($appointment->bansal_appointment_id)) {
            return $this->recoverWithCreate($appointment, 'Temporary Bansal appointment ID detected during reschedule.');
        }

        try {
            $apiResponse = $this->apiClient->rescheduleAppointment(
                (int) $appointment->bansal_appointment_id,
                $apiDate,
                $apiTime,
                $apiMeetingType,
                $apiPreferredLanguage
            );

            if ($apiResponse['success'] ?? false) {
                return $this->syncedResult((int) $appointment->bansal_appointment_id, false);
            }

            $errorMessage = $apiResponse['message'] ?? 'Failed to update appointment on website.';
            $errors = $apiResponse['errors'] ?? [];

            if (self::shouldRecoverWithCreate($errorMessage)
                || (isset($errors['appointment_id']) && self::shouldRecoverWithCreate(implode(' ', $errors['appointment_id'])))) {
                return $this->recoverWithCreate($appointment, $errorMessage);
            }

            return $this->failedResult($errorMessage);
        } catch (Exception $e) {
            if (self::shouldRecoverWithCreate($e->getMessage())) {
                return $this->recoverWithCreate($appointment, $e->getMessage());
            }

            return $this->failedResult($e->getMessage());
        }
    }

    /**
     * @return array{synced: bool, error: ?string, bansal_appointment_id: ?int, created_new: bool}
     */
    public function syncStatus(BookingAppointment $appointment, string $status, ?string $reason = null): array
    {
        if (empty($appointment->bansal_appointment_id)) {
            return $this->failedResult('Missing Bansal appointment identifier.');
        }

        if (self::isUnsyncedBansalId($appointment->bansal_appointment_id)) {
            if ($status === 'cancelled') {
                return $this->syncedResult(null, false);
            }

            $recovery = $this->recoverWithCreate($appointment, 'Temporary Bansal appointment ID detected during status sync.');
            if (!$recovery['synced']) {
                return $recovery;
            }

            $appointment->bansal_appointment_id = $recovery['bansal_appointment_id'];
        }

        $type = match ($status) {
            'cancelled' => 'cancel',
            'completed' => 'complete',
            'confirmed' => 'confirm',
            'paid' => 'pay',
            default => null,
        };

        if ($type === null) {
            return $this->syncedResult($appointment->bansal_appointment_id, false);
        }

        try {
            $this->apiClient->updateAppointmentStatus(
                (int) $appointment->bansal_appointment_id,
                $type,
                $reason
            );

            return $this->syncedResult((int) $appointment->bansal_appointment_id, false);
        } catch (Exception $e) {
            if (self::shouldRecoverWithCreate($e->getMessage()) && $status !== 'cancelled') {
                $recovery = $this->recoverWithCreate($appointment, $e->getMessage());
                if (!$recovery['synced']) {
                    return $recovery;
                }

                $appointment->bansal_appointment_id = $recovery['bansal_appointment_id'];

                try {
                    $this->apiClient->updateAppointmentStatus(
                        (int) $appointment->bansal_appointment_id,
                        $type,
                        $reason
                    );

                    return $this->syncedResult((int) $appointment->bansal_appointment_id, $recovery['created_new']);
                } catch (Exception $retryException) {
                    return $this->failedResult($retryException->getMessage());
                }
            }

            if (self::shouldRecoverWithCreate($e->getMessage()) && $status === 'cancelled') {
                return $this->syncedResult(null, false);
            }

            return $this->failedResult($e->getMessage());
        }
    }

    /**
     * @return array{synced: bool, error: ?string, bansal_appointment_id: ?int, created_new: bool}
     */
    public function recoverWithCreate(BookingAppointment $appointment, string $originalError): array
    {
        try {
            $existingId = $this->findExistingBansalAppointmentId($appointment);
            if ($existingId !== null) {
                $this->assertBansalIdIsLinkable($appointment, $existingId);

                Log::info('Linked CRM appointment to existing Bansal record', [
                    'appointment_id' => $appointment->id,
                    'old_bansal_appointment_id' => $appointment->bansal_appointment_id,
                    'bansal_appointment_id' => $existingId,
                    'original_error' => $originalError,
                ]);

                return $this->syncedResult($existingId, false);
            }

            $bansalAppointmentId = $this->createService->syncAppointmentToBansal($appointment);

            return $this->syncedResult($bansalAppointmentId, true);
        } catch (Exception $e) {
            $existingId = $this->findExistingBansalAppointmentId($appointment);
            if ($existingId !== null) {
                try {
                    $this->assertBansalIdIsLinkable($appointment, $existingId);

                    $appointment->forceFill([
                        'bansal_appointment_id' => $existingId,
                        'sync_status' => 'synced',
                        'sync_error' => null,
                        'last_synced_at' => now(),
                    ])->save();

                    Log::info('Linked CRM appointment after create conflict on Bansal', [
                        'appointment_id' => $appointment->id,
                        'bansal_appointment_id' => $existingId,
                        'create_error' => $e->getMessage(),
                    ]);

                    return $this->syncedResult($existingId, false);
                } catch (Exception $linkException) {
                    return $this->failedResult($this->formatCreateSyncError($linkException));
                }
            }

            Log::warning('Failed to recover Bansal appointment via create/link', [
                'appointment_id' => $appointment->id,
                'old_bansal_appointment_id' => $appointment->bansal_appointment_id,
                'original_error' => $originalError,
                'error' => $e->getMessage(),
            ]);

            return $this->failedResult($this->formatCreateSyncError($e, $originalError));
        }
    }

    public function findExistingBansalAppointmentId(BookingAppointment $appointment): ?int
    {
        if ($appointment->appointment_datetime === null || empty($appointment->client_email)) {
            return null;
        }

        $date = $appointment->appointment_datetime->format('Y-m-d');
        $targetEmail = strtolower(trim($appointment->client_email));
        $targetMinute = $appointment->appointment_datetime->format('Y-m-d H:i');

        try {
            $response = $this->apiClient->getAppointmentsByDateRange($date, $date, 1);
            $appointments = $response['data'] ?? [];

            foreach ($appointments as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $email = strtolower(trim((string) ($row['email'] ?? '')));
                if ($email !== $targetEmail || empty($row['id'])) {
                    continue;
                }

                $rowMinute = null;
                if (!empty($row['appointment_datetime'])) {
                    $rowMinute = Carbon::parse($row['appointment_datetime'])->format('Y-m-d H:i');
                }

                if ($rowMinute !== $targetMinute) {
                    continue;
                }

                $foundId = (int) $row['id'];
                if ($this->isBansalIdLinkable($appointment, $foundId)) {
                    return $foundId;
                }
            }
        } catch (Exception $e) {
            Log::warning('Unable to search Bansal for an existing appointment before create', [
                'appointment_id' => $appointment->id,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function formatCreateSyncError(Exception $exception, ?string $originalError = null): string
    {
        $message = $exception->getMessage();

        if (stripos($message, 'time is outside of available booking hours') !== false
            || stripos($message, 'outside of available booking hours') !== false) {
            return 'The selected appointment time is not available for booking. Please choose a different time slot.';
        }

        if (stripos($message, 'time slot') !== false || stripos($message, 'slot') !== false) {
            return 'The selected time slot is not available. Please choose a different time.';
        }

        if ($originalError !== null && $originalError !== '') {
            return 'Failed to create appointment on website. Original error: ' . $originalError;
        }

        return 'Failed to create appointment on website: ' . $message;
    }

    protected function isBansalIdLinkable(BookingAppointment $appointment, int $bansalAppointmentId): bool
    {
        return !BookingAppointment::where('bansal_appointment_id', $bansalAppointmentId)
            ->where('id', '!=', $appointment->id)
            ->exists();
    }

    protected function assertBansalIdIsLinkable(BookingAppointment $appointment, int $bansalAppointmentId): void
    {
        if (!$this->isBansalIdLinkable($appointment, $bansalAppointmentId)) {
            throw new Exception(
                "Bansal appointment ID {$bansalAppointmentId} is already linked to another CRM record."
            );
        }

        $appointment->forceFill([
            'bansal_appointment_id' => $bansalAppointmentId,
            'sync_status' => 'synced',
            'sync_error' => null,
            'last_synced_at' => now(),
        ])->save();
    }

    /**
     * @return array{synced: bool, error: ?string, bansal_appointment_id: ?int, created_new: bool}
     */
    protected function syncedResult(?int $bansalAppointmentId, bool $createdNew): array
    {
        return [
            'synced' => true,
            'error' => null,
            'bansal_appointment_id' => $bansalAppointmentId,
            'created_new' => $createdNew,
        ];
    }

    /**
     * @return array{synced: bool, error: ?string, bansal_appointment_id: ?int, created_new: bool}
     */
    protected function failedResult(string $error): array
    {
        return [
            'synced' => false,
            'error' => $error,
            'bansal_appointment_id' => null,
            'created_new' => false,
        ];
    }
}
