<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Http\Controllers\API\BansalAppointmentWebhookController;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BansalAppointmentWebhookControllerTest extends TestCase
{
    #[Test]
    public function it_passes_full_appointment_payload_not_only_validated_id(): void
    {
        config(['services.bansal_appointment_webhook.token' => 'test-token']);

        $fullAppointment = [
            'id' => 5473,
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '0400000000',
            'location' => 'melbourne',
            'meeting_type' => 'phone',
            'appointment_date' => '2026-09-10',
            'appointment_time' => '10:00',
            'appointment_datetime' => '2026-09-10T00:00:00.000000Z',
            'duration_minutes' => 15,
            'status' => 'confirmed',
            'is_paid' => false,
        ];

        $sync = Mockery::mock(AppointmentSyncService::class);
        $sync->shouldReceive('syncPushedAppointment')
            ->once()
            ->with(Mockery::on(function (array $data) use ($fullAppointment) {
                return ($data['id'] ?? null) === 5473
                    && ($data['full_name'] ?? null) === 'Test User'
                    && ($data['email'] ?? null) === 'test@example.com'
                    && ($data['appointment_datetime'] ?? null) === $fullAppointment['appointment_datetime'];
            }), 'booked')
            ->andReturn([
                'result' => 'new',
                'bansal_id' => 5473,
            ]);

        $controller = new BansalAppointmentWebhookController($sync);

        $request = Request::create('/api/webhooks/bansal/appointments', 'POST', [
            'event' => 'booked',
            'appointment' => $fullAppointment,
        ]);
        $request->headers->set('Authorization', 'Bearer test-token');

        $response = $controller($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
