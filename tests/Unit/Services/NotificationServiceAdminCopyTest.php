<?php

namespace Tests\Unit\Services;

use App\Mail\AppointmentCancellation;
use App\Mail\AppointmentClientConfirmed;
use App\Mail\AppointmentReschedule;
use App\Models\BookingAppointment;
use App\Services\BansalAppointmentSync\NotificationService;
use App\Services\Sms\UnifiedSmsManager;
use App\Services\SystemEmailLogService;
use Illuminate\Mail\Mailable;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationServiceAdminCopyTest extends TestCase
{
    #[Test]
    public function admin_copies_use_noreply_from_and_info_to(): void
    {
        config([
            'mail.from.address' => 'info@bansalimmigration.com.au',
            'mail.from.name' => 'Bansal Immigration',
            'mail.noreply.address' => 'noreply@bansalimmigration.com.au',
            'mail.info.address' => 'info@bansalimmigration.com.au',
        ]);

        $sends = [];
        $emailLog = Mockery::mock(SystemEmailLogService::class);
        $emailLog->shouldReceive('logAndSendMailable')
            ->twice()
            ->andReturnUsing(function (array $meta, Mailable $mailable, mixed $to) use (&$sends): void {
                $sends[] = [
                    'meta' => $meta,
                    'mailable' => $mailable,
                    'to' => $to,
                ];
            });

        $service = new NotificationService(
            Mockery::mock(UnifiedSmsManager::class),
            $emailLog,
        );

        $appointment = new BookingAppointment([
            'client_name' => 'Test Client',
            'client_email' => 'client@example.test',
            'client_id' => 1,
            'appointment_datetime' => now()->addDay(),
            'timeslot_full' => '10:00 AM - 10:30 AM',
            'location' => 'melbourne',
            'meeting_type' => 'in_person',
            'service_type' => 'Consultation',
            'cancellation_reason' => 'Client cancelled',
        ]);
        $appointment->id = 42;

        $service->sendCancellationConfirmationEmail($appointment, 'Client cancelled', true);

        $this->assertCount(2, $sends);
        $this->assertSame('client@example.test', $sends[0]['to']);
        $this->assertSame('info@bansalimmigration.com.au', $sends[1]['to']);
        $this->assertSame('noreply@bansalimmigration.com.au', $sends[1]['meta']['from_mail']);
        $this->assertStringStartsWith('[Info] ', $sends[1]['meta']['subject']);
        $this->assertInstanceOf(AppointmentCancellation::class, $sends[1]['mailable']);
        $sends[1]['mailable']->assertFrom('noreply@bansalimmigration.com.au', 'Bansal Immigration');
    }

    #[Test]
    public function reschedule_and_confirm_admin_copies_also_use_noreply_from(): void
    {
        config([
            'mail.from.address' => 'info@bansalimmigration.com.au',
            'mail.noreply.address' => 'noreply@bansalimmigration.com.au',
            'mail.info.address' => 'info@bansalimmigration.com.au',
        ]);

        $adminFroms = [];
        $emailLog = Mockery::mock(SystemEmailLogService::class);
        $emailLog->shouldReceive('logAndSendMailable')
            ->times(4)
            ->andReturnUsing(function (array $meta, Mailable $mailable, mixed $to) use (&$adminFroms): void {
                if (($meta['type'] ?? null) === 'admin') {
                    $adminFroms[] = [
                        'from_mail' => $meta['from_mail'] ?? null,
                        'to' => $to,
                        'mailable' => $mailable,
                    ];
                }
            });

        $service = new NotificationService(
            Mockery::mock(UnifiedSmsManager::class),
            $emailLog,
        );

        $appointment = new BookingAppointment([
            'client_name' => 'Test Client',
            'client_email' => 'client@example.test',
            'client_id' => 1,
            'appointment_datetime' => now()->addDay(),
            'timeslot_full' => '10:00 AM - 10:30 AM',
            'location' => 'melbourne',
            'meeting_type' => 'phone',
            'service_type' => 'Consultation',
        ]);
        $appointment->id = 42;

        $service->sendRescheduleEmail($appointment, now(), true);
        $service->sendClientConfirmedEmail($appointment, true);

        $this->assertCount(2, $adminFroms);
        $this->assertInstanceOf(AppointmentReschedule::class, $adminFroms[0]['mailable']);
        $this->assertInstanceOf(AppointmentClientConfirmed::class, $adminFroms[1]['mailable']);

        foreach ($adminFroms as $send) {
            $this->assertSame('noreply@bansalimmigration.com.au', $send['from_mail']);
            $this->assertSame('info@bansalimmigration.com.au', $send['to']);
            $send['mailable']->assertFrom('noreply@bansalimmigration.com.au', 'Bansal Immigration');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
