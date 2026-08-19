<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use App\Support\AppointmentMeetingTypeCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentClientConfirmed extends Mailable
{
    use Queueable, SerializesModels, UsesAppointmentMailFrom;

    public function __construct(
        public array $details
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->appointmentFromAddress(),
            subject: 'Appointment Confirmed - Bansal Immigration',
        );
    }

    public function content(): Content
    {
        $meetingType = (string) ($this->details['meeting_type'] ?? '');

        return new Content(
            view: 'emails.appointment-client-confirmed',
            with: [
                'clientName' => $this->details['client_name'] ?? 'Valued Client',
                'appointmentDate' => $this->details['appointment_datetime']?->format('l, d F Y') ?? 'N/A',
                'appointmentTime' => $this->details['timeslot_full'] ?? 'N/A',
                'locationAddress' => $this->getLocationAddress($this->details['location'] ?? 'melbourne'),
                'serviceType' => filled($this->details['service_type'] ?? null)
                    ? (string) $this->details['service_type']
                    : 'N/A',
                'meetingTypeLabel' => AppointmentMeetingTypeCopy::label($meetingType),
                'locationPhone' => $this->getLocationPhone($this->details['location'] ?? 'melbourne'),
                'locationPhoneTel' => str_replace(
                    [' ', '-'],
                    '',
                    $this->getLocationPhone($this->details['location'] ?? 'melbourne')
                ),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
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
