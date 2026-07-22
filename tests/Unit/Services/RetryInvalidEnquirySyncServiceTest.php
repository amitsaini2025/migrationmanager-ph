<?php

namespace Tests\Unit\Services;

use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\BansalAppointmentSync\RetryInvalidEnquirySyncService;
use Carbon\Carbon;
use Tests\TestCase;

class RetryInvalidEnquirySyncServiceTest extends TestCase
{
    public function test_build_create_payload_maps_melbourne_jrp_enquiry_type_to_pr_complex(): void
    {
        $service = new RetryInvalidEnquirySyncService($this->createMock(BansalApiClient::class));

        $appointment = new BookingAppointment([
            'client_name' => 'Test Client',
            'client_email' => 'client@example.com',
            'client_phone' => '0400000000',
            'appointment_datetime' => Carbon::parse('2026-08-01 10:00:00'),
            'duration_minutes' => 15,
            'location' => 'melbourne',
            'meeting_type' => 'in_person',
            'preferred_language' => 'English',
            'service_id' => 2,
            'noe_id' => 3,
            'enquiry_type' => 'jrp',
            'service_type' => 'JRP/Skill Assessment',
            'enquiry_details' => 'Retry test',
            'is_paid' => false,
            'amount' => 0,
            'final_amount' => 0,
        ]);

        $payload = $service->buildCreatePayload($appointment);

        $this->assertSame('pr_complex', $payload['enquiry_type']);
        $this->assertSame('jrp-skill-assessment', $payload['service_type']);
        $this->assertSame('consultation', $payload['specific_service']);
        $this->assertSame('in-person', $payload['meeting_type']);
    }

    public function test_build_create_payload_maps_melbourne_complex_enquiry_type_to_ajay(): void
    {
        $service = new RetryInvalidEnquirySyncService($this->createMock(BansalApiClient::class));

        $appointment = new BookingAppointment([
            'client_name' => 'Test Client',
            'client_email' => 'client@example.com',
            'appointment_datetime' => Carbon::parse('2026-08-01 11:00:00'),
            'location' => 'melbourne',
            'meeting_type' => 'phone',
            'service_id' => 2,
            'noe_id' => 6,
            'enquiry_type' => 'complex',
            'service_type' => 'Complex Matters',
            'is_paid' => false,
        ]);

        $payload = $service->buildCreatePayload($appointment);

        $this->assertSame('ajay', $payload['enquiry_type']);
        $this->assertSame('complex-matters', $payload['service_type']);
    }

    public function test_extract_bansal_appointment_id_from_nested_data_id(): void
    {
        $service = new RetryInvalidEnquirySyncService($this->createMock(BansalApiClient::class));

        $this->assertSame(
            12345,
            $service->extractBansalAppointmentId(['success' => true, 'data' => ['id' => 12345]])
        );
    }

    public function test_matches_invalid_enquiry_sync_error_variants(): void
    {
        $this->assertTrue(RetryInvalidEnquirySyncService::matchesInvalidEnquirySyncError(
            RetryInvalidEnquirySyncService::INVALID_ENQUIRY_SYNC_ERROR
        ));
        $this->assertTrue(RetryInvalidEnquirySyncService::matchesInvalidEnquirySyncError(
            RetryInvalidEnquirySyncService::INVALID_ENQUIRY_SYNC_ERROR_PREFIXED
        ));
        $this->assertTrue(RetryInvalidEnquirySyncService::matchesInvalidEnquirySyncError(
            'Failed to create appointment on website. Original error: The selected enquiry type is invalid.'
        ));
        $this->assertFalse(RetryInvalidEnquirySyncService::matchesInvalidEnquirySyncError(
            'The selected time slot is not available.'
        ));
    }
}
