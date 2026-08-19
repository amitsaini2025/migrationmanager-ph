<?php

namespace Tests\Unit\Mail;

use App\Mail\AppointmentCancellation;
use App\Mail\AppointmentClientConfirmed;
use App\Mail\AppointmentDetailedConfirmation;
use App\Mail\AppointmentPaidPaymentLink;
use App\Mail\AppointmentReminder;
use App\Mail\AppointmentReschedule;
use App\Models\BookingAppointment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentMailFromAddressTest extends TestCase
{
    #[Test]
    public function appointment_mailables_use_appointment_from_address_not_global_from(): void
    {
        config([
            'mail.from.address' => 'info@bansalimmigration.com.au',
            'mail.from.name' => 'Bansal Immigration',
            'mail.noreply.address' => 'noreply@bansalimmigration.com.au',
        ]);

        $details = [
            'client_name' => 'Test Client',
            'appointment_datetime' => now()->addDay(),
            'timeslot_full' => '10:00 AM - 10:30 AM',
            'location' => 'melbourne',
            'meeting_type' => 'in_person',
            'service_type' => 'Consultation',
            'admin_notes' => null,
            'cancellation_reason' => null,
            'old_datetime' => now(),
        ];

        $appointment = new BookingAppointment([
            'client_name' => 'Test Client',
            'location' => 'melbourne',
            'service_type' => 'Consultation',
            'final_amount' => 100,
            'amount' => 100,
            'appointment_datetime' => now()->addDay(),
            'timeslot_full' => '10:00 AM - 10:30 AM',
            'client_timezone' => 'Australia/Melbourne',
        ]);

        $mailables = [
            new AppointmentDetailedConfirmation($details),
            new AppointmentReminder($details),
            new AppointmentCancellation($details),
            new AppointmentReschedule($details),
            new AppointmentPaidPaymentLink($appointment, 'https://example.test/pay'),
            new AppointmentClientConfirmed($details),
        ];

        foreach ($mailables as $mailable) {
            $mailable->assertFrom('noreply@bansalimmigration.com.au', 'Bansal Immigration');
            $this->assertNotSame(
                config('mail.from.address'),
                $mailable->envelope()->from->address
            );
        }
    }
}
