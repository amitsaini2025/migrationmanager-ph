<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentPaidPaymentLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $details
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complete Your Appointment Payment - Bansal Immigration',
        );
    }

    public function content(): Content
    {
        $location = $this->details['location'] ?? 'melbourne';

        return new Content(
            view: 'emails.appointment-paid-payment',
            with: [
                'clientName' => $this->details['client_name'] ?? 'Valued Client',
                'appointmentDate' => $this->details['appointment_datetime']?->format('l, d F Y') ?? 'N/A',
                'appointmentTime' => $this->details['timeslot_full'] ?? 'N/A',
                'locationAddress' => $this->getLocationAddress($location),
                'serviceType' => filled($this->details['service_type'] ?? null)
                    ? (string) $this->details['service_type']
                    : 'N/A',
                'amount' => number_format((float) ($this->details['amount'] ?? 0), 2),
                'paymentUrl' => $this->details['payment_url'] ?? '#',
                'locationPhone' => $this->getLocationPhone($location),
                'locationPhoneTel' => str_replace(
                    [' ', '-'],
                    '',
                    $this->getLocationPhone($location)
                ),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    protected function getLocationAddress(string $location): string
    {
        return match ($location) {
            'melbourne' => 'Level 8/278 Collins St, Melbourne VIC 3000, Australia',
            'adelaide' => 'Unit 5, 55 Gawler Pl, Adelaide SA 5000, Australia',
            default => 'Bansal Immigration Office',
        };
    }

    protected function getLocationPhone(string $location): string
    {
        return match ($location) {
            'adelaide' => '0883171340',
            'melbourne' => '+61 3 9602 1330',
            default => '1300 859 368',
        };
    }
}
