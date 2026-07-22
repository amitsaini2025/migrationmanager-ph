<?php

namespace Tests\Unit\Services;

use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use App\Services\BansalAppointmentSync\RetryInvalidEnquirySyncService;
use Carbon\Carbon;
use Tests\TestCase;

class BansalAppointmentRecoveryServiceTest extends TestCase
{
    public function test_is_unsynced_bansal_id_detects_temp_ids(): void
    {
        $this->assertTrue(BansalAppointmentRecoveryService::isUnsyncedBansalId(2504132));
        $this->assertFalse(BansalAppointmentRecoveryService::isUnsyncedBansalId(4594));
        $this->assertFalse(BansalAppointmentRecoveryService::isUnsyncedBansalId(null));
    }

    public function test_should_recover_with_create_for_invalid_id_and_not_found_errors(): void
    {
        $this->assertTrue(BansalAppointmentRecoveryService::shouldRecoverWithCreate('appointment id is invalid'));
        $this->assertTrue(BansalAppointmentRecoveryService::shouldRecoverWithCreate(
            'HTTP request returned status code 404: {"success":false,"message":"Appointment not found"}'
        ));
        $this->assertFalse(BansalAppointmentRecoveryService::shouldRecoverWithCreate(
            'The selected time slot is not available.'
        ));
    }

    public function test_matches_appointment_not_found_sync_error_excludes_invalid_enquiry_errors(): void
    {
        $this->assertTrue(BansalAppointmentRecoveryService::matchesAppointmentNotFoundSyncError(
            'HTTP request returned status code 404: {"success":false,"message":"Appointment not found"}'
        ));
        $this->assertFalse(BansalAppointmentRecoveryService::matchesAppointmentNotFoundSyncError(
            RetryInvalidEnquirySyncService::INVALID_ENQUIRY_SYNC_ERROR
        ));
        $this->assertFalse(BansalAppointmentRecoveryService::matchesAppointmentNotFoundSyncError(
            'The selected time slot is not available.'
        ));
    }

    public function test_temp_id_threshold_matches_retry_service_constant(): void
    {
        $this->assertSame(
            RetryInvalidEnquirySyncService::MIN_UNSYNCED_BANSAL_ID,
            2000000
        );
    }

    public function test_not_found_retry_earliest_appointment_date_is_start_of_today_in_app_timezone(): void
    {
        config(['app.timezone' => 'Australia/Melbourne']);
        Carbon::setTestNow(Carbon::parse('2026-07-22 17:30:00', 'Australia/Melbourne'));

        try {
            $date = BansalAppointmentRecoveryService::notFoundRetryEarliestAppointmentDate();

            $this->assertSame('2026-07-22', $date->toDateString());
            $this->assertSame('00:00:00', $date->format('H:i:s'));
            $this->assertSame('Australia/Melbourne', $date->timezone->getName());
        } finally {
            Carbon::setTestNow();
        }
    }
}
