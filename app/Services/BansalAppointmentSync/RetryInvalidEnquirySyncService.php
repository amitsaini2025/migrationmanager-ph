<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\BookingAppointment;
use App\Support\BansalSchedulingServiceType;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class RetryInvalidEnquirySyncService
{
    public const INVALID_ENQUIRY_SYNC_ERROR = 'The selected enquiry type is invalid.';

    public const INVALID_ENQUIRY_SYNC_ERROR_PREFIXED = 'Failed to create appointment on website: The selected enquiry type is invalid.';

    public const MIN_UNSYNCED_BANSAL_ID = 2000000;

    /**
     * @return list<string>
     */
    public static function invalidEnquirySyncErrors(): array
    {
        return [
            self::INVALID_ENQUIRY_SYNC_ERROR,
            self::INVALID_ENQUIRY_SYNC_ERROR_PREFIXED,
        ];
    }

    public static function matchesInvalidEnquirySyncError(?string $syncError): bool
    {
        if ($syncError === null || $syncError === '') {
            return false;
        }

        return in_array($syncError, self::invalidEnquirySyncErrors(), true)
            || str_contains($syncError, self::INVALID_ENQUIRY_SYNC_ERROR);
    }

    public function __construct(
        protected BansalApiClient $apiClient
    ) {
    }

    /**
     * Appointments that failed because CRM sent an invalid enquiry_type slug to Bansal.
     */
    public function eligibleQuery(): Builder
    {
        return BookingAppointment::query()
            ->where('sync_status', 'error')
            ->where(function (Builder $query): void {
                $query->whereIn('sync_error', self::invalidEnquirySyncErrors())
                    ->orWhere('sync_error', 'like', '%' . self::INVALID_ENQUIRY_SYNC_ERROR);
            })
            ->where('bansal_appointment_id', '>=', self::MIN_UNSYNCED_BANSAL_ID)
            ->where('appointment_datetime', '>', now())
            ->where('status', '!=', 'cancelled')
            ->orderBy('appointment_datetime');
    }

    /**
     * Build add-appointment payload using the same mapping rules as live CRM booking.
     *
     * @return array<string, mixed>
     */
    public function buildCreatePayload(BookingAppointment $appointment): array
    {
        if ($appointment->appointment_datetime === null) {
            throw new Exception('Appointment is missing appointment_datetime.');
        }

        $appointmentDatetime = $appointment->appointment_datetime;
        $location = $appointment->location ?? 'melbourne';
        $meetingTypeForApi = $this->mapMeetingTypeForApi($appointment->meeting_type ?? 'in_person');

        return [
            'full_name' => $appointment->client_name,
            'email' => $appointment->client_email,
            'phone' => $appointment->client_phone ?? '',
            'appointment_date' => $appointmentDatetime->format('Y-m-d'),
            'appointment_time' => $appointmentDatetime->format('H:i'),
            'appointment_datetime' => $appointmentDatetime->format('Y-m-d H:i:s'),
            'duration_minutes' => $appointment->duration_minutes ?? 15,
            'location' => $location,
            'meeting_type' => $meetingTypeForApi,
            'preferred_language' => $appointment->preferred_language ?? 'English',
            'specific_service' => $this->determineSpecificService($appointment),
            'enquiry_type' => BansalSchedulingServiceType::bansalEnquiryTypeForApi(
                $appointment->noe_id ?? 0,
                $location,
                $appointment->enquiry_type ?? 'pr_complex'
            ),
            'service_type' => BansalSchedulingServiceType::bansalServiceTypeForApi(
                $appointment->noe_id ?? 0,
                $appointment->service_type ?? 'Permanent Residency'
            ),
            'enquiry_details' => $appointment->enquiry_details ?? '',
            'is_paid' => (bool) ($appointment->is_paid ?? false),
            'amount' => $appointment->amount ?? 0,
            'final_amount' => $appointment->final_amount ?? 0,
            'payment_status' => $appointment->payment_status ?? ($appointment->is_paid ? 'pending' : null),
            'slot_overwrite' => 0,
        ];
    }

    /**
     * Create the appointment on Bansal and persist the real bansal_appointment_id in CRM.
     */
    public function syncAppointmentToBansal(BookingAppointment $appointment): int
    {
        $payload = $this->buildCreatePayload($appointment);
        $apiResponse = $this->apiClient->createAppointment($payload);
        $bansalAppointmentId = $this->extractBansalAppointmentId($apiResponse);

        if ($bansalAppointmentId === null) {
            throw new Exception(
                'Bansal API did not return appointment ID. Response: ' . json_encode($apiResponse)
            );
        }

        if (BookingAppointment::where('bansal_appointment_id', $bansalAppointmentId)
            ->where('id', '!=', $appointment->id)
            ->exists()) {
            throw new Exception(
                "Bansal appointment ID {$bansalAppointmentId} is already linked to another CRM record."
            );
        }

        $oldBansalId = $appointment->bansal_appointment_id;

        $appointment->forceFill([
            'bansal_appointment_id' => $bansalAppointmentId,
            'sync_status' => 'synced',
            'sync_error' => null,
            'last_synced_at' => now(),
        ])->save();

        Log::info('Invalid enquiry sync retry succeeded', [
            'appointment_id' => $appointment->id,
            'old_bansal_appointment_id' => $oldBansalId,
            'bansal_appointment_id' => $bansalAppointmentId,
            'mapped_enquiry_type' => $payload['enquiry_type'],
        ]);

        return $bansalAppointmentId;
    }

    /**
     * @param array<string, mixed> $apiResponse
     */
    public function extractBansalAppointmentId(array $apiResponse): ?int
    {
        if (isset($apiResponse['data']['id'])) {
            return (int) $apiResponse['data']['id'];
        }

        if (isset($apiResponse['data']['appointment_id'])) {
            return (int) $apiResponse['data']['appointment_id'];
        }

        if (isset($apiResponse['appointment_id'])) {
            return (int) $apiResponse['appointment_id'];
        }

        return null;
    }

    protected function mapMeetingTypeForApi(string $meetingType): string
    {
        return match ($meetingType) {
            'video' => 'video-call',
            'in_person' => 'in-person',
            'phone' => 'phone',
            default => 'in-person',
        };
    }

    protected function determineSpecificService(BookingAppointment $appointment): string
    {
        $serviceId = (int) ($appointment->service_id ?? 0);

        return match ($serviceId) {
            1 => 'paid-consultation',
            2 => 'consultation',
            3 => 'overseas-enquiry',
            default => $this->determineSpecificServiceFromEnquiry($appointment),
        };
    }

    protected function determineSpecificServiceFromEnquiry(BookingAppointment $appointment): string
    {
        if ($appointment->enquiry_type) {
            $enquiryType = strtolower($appointment->enquiry_type);

            if (str_contains($enquiryType, 'overseas') || $enquiryType === 'international') {
                return 'overseas-enquiry';
            }

            if ($appointment->is_paid) {
                return 'paid-consultation';
            }

            return 'consultation';
        }

        return ($appointment->is_paid ?? false) ? 'paid-consultation' : 'consultation';
    }
}
