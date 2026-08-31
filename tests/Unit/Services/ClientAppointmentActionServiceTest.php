<?php

namespace Tests\Unit\Services;

use App\Models\BookingAppointment;
use App\Services\AppointmentOpenSlotService;
use App\Services\BansalAppointmentSync\BansalAppointmentRecoveryService;
use App\Services\BansalAppointmentSync\NotificationService;
use App\Services\ClientAppointmentActionService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientAppointmentActionServiceTest extends TestCase
{
    #[Test]
    public function it_blocks_cancelled_completed_and_no_show_appointments(): void
    {
        $service = new ClientAppointmentActionService(
            Mockery::mock(BansalAppointmentRecoveryService::class),
            Mockery::mock(NotificationService::class),
            Mockery::mock(AppointmentOpenSlotService::class),
        );

        $this->assertTrue($service->canAct(new BookingAppointment(['status' => 'pending'])));
        $this->assertTrue($service->canAct(new BookingAppointment(['status' => 'awaiting_confirmation'])));
        $this->assertTrue($service->canAct(new BookingAppointment(['status' => 'paid'])));
        $this->assertTrue($service->canAct(new BookingAppointment(['status' => 'confirmed'])));
        $this->assertFalse($service->canAct(new BookingAppointment(['status' => 'cancelled'])));
        $this->assertFalse($service->canAct(new BookingAppointment(['status' => 'completed'])));
        $this->assertFalse($service->canAct(new BookingAppointment(['status' => 'no_show'])));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
