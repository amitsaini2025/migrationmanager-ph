<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentDetailedConfirmation extends Mailable
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
            subject: 'Appointment Confirmation - Bansal Immigration',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $meetingType = strtolower(trim((string) ($this->details['meeting_type'] ?? '')));
        $showInPersonArrival = $meetingType === '' || $meetingType === 'in_person';
        $resumeDateFragment = $this->details['appointment_datetime']?->format('j F Y') ?? 'N/A';
        $resumeMailtoHref = 'mailto:info@bansalimmigration.com.au?subject='.rawurlencode(
            'Resume – [Your Full Name] – '.$resumeDateFragment.' Appointment'
        );

        return new Content(
            view: 'emails.appointment-confirmation',
            with: [
                'clientName' => $this->details['client_name'] ?? 'Valued Client',
                'appointmentDate' => $this->details['appointment_datetime']?->format('l, d F Y') ?? 'N/A',
                'resumeDateForSubject' => $resumeDateFragment,
                'resumeMailtoHref' => $resumeMailtoHref,
                'appointmentTime' => $this->details['timeslot_full'] ?? 'N/A',
                'location' => ucfirst($this->details['location'] ?? 'melbourne'),
                'locationAddress' => $this->getLocationAddress($this->details['location'] ?? 'melbourne'),
                'serviceType' => filled($this->details['service_type'] ?? null)
                    ? (string) $this->details['service_type']
                    : 'N/A',
                'locationPhone' => $this->getLocationPhone($this->details['location'] ?? 'melbourne'),
                'locationPhoneTel' => str_replace(
                    [' ', '-'],
                    '',
                    $this->getLocationPhone($this->details['location'] ?? 'melbourne')
                ),
                'adminNotes' => $this->details['admin_notes'] ?? null,
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

    /**
     * Get full address for location
     */
    protected function getLocationAddress(string $location): string
    {
        return match ($location) {
            'melbourne' => 'Level 8/278 Collins St, Melbourne VIC 3000, Australia',
            'adelaide' => 'Unit 5, 55 Gawler Pl, Adelaide SA 5000, Australia',
            default => 'Bansal Immigration Office'
        };
    }

    /**
     * Get phone number for location (used in appointment emails)
     */
    protected function getLocationPhone(string $location): string
    {
        return match ($location) {
            'adelaide' => '0883171340',
            'melbourne' => '+61 3 9602 1330',
            default => '1300 859 368',
        };
    }
}
