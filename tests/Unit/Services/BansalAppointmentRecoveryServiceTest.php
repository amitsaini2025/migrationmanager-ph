<?php

namespace Tests\Unit\Services;

use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use App\Services\BansalAppointmentSync\RetryInvalidEnquirySyncService;
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

    public function test_temp_id_threshold_matches_retry_service_constant(): void
    {
        $this->assertSame(
            RetryInvalidEnquirySyncService::MIN_UNSYNCED_BANSAL_ID,
            2000000
        );
    }
}
