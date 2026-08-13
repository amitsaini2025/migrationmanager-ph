<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReschedule extends Mailable
{
    use Queueable, SerializesModels, UsesAppointmentMailFrom;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $details
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->appointmentFromAddress(),
            subject: 'Appointment Rescheduled - Bansal Immigration',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $meetingType = strtolower(trim((string) ($this->details['meeting_type'] ?? '')));
        $showInPersonArrival = $meetingType === '' || $meetingType === 'in_person';

        return new Content(
            view: 'emails.appointment-reschedule',
            with: [
                'clientName' => $this->details['client_name'] ?? 'Valued Client',
                'oldDate' => $this->details['old_datetime']?->format('l, d F Y') ?? 'N/A',
                'oldTime' => $this->details['old_datetime']?->format('h:i A') ?? 'N/A',
                'newDate' => $this->details['appointment_datetime']?->format('l, d F Y') ?? 'N/A',
                'newTime' => $this->details['timeslot_full'] ?? ($this->details['appointment_datetime']?->format('h:i A') ?? 'N/A'),
                'location' => ucfirst($this->details['location'] ?? 'melbourne'),
                'locationAddress' => $this->getLocationAddress($this->details['location'] ?? 'melbourne'),
                'locationPhone' => $this->getLocationPhone($this->details['location'] ?? 'melbourne'),
                'locationPhoneTel' => str_replace(
                    [' ', '-'],
                    '',
                    $this->getLocationPhone($this->details['location'] ?? 'melbourne')
                ),
                'showInPersonArrival' => $showInPersonArrival,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
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
