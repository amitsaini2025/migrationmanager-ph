<?php

namespace Tests\Unit\Services;

use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentSyncServiceWebhookTest extends TestCase
{
    #[Test]
    public function sync_pushed_appointment_method_exists_and_is_public(): void
    {
        $this->assertTrue(method_exists(AppointmentSyncService::class, 'syncPushedAppointment'));

        $method = new \ReflectionMethod(AppointmentSyncService::class, 'syncPushedAppointment');
        $this->assertTrue($method->isPublic());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
