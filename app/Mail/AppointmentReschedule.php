<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use App\Support\AppointmentActionLink;
use App\Support\AppointmentMeetingTypeCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
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
     * Disable SendGrid click-tracking so signed Cancel / Reschedule / Confirm links stay intact.
     */
    public function headers(): Headers
    {
        return new Headers(text: [
            'X-SMTPAPI' => json_encode([
                'filters' => [
                    'clicktrack' => [
                        'settings' => [
                            'enable' => 0,
                        ],
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $meetingType = (string) ($this->details['meeting_type'] ?? '');
        $locationKey = strtolower(trim((string) ($this->details['location'] ?? 'melbourne')));
        if ($locationKey === '') {
            $locationKey = 'melbourne';
        }
        $appointmentId = (int) ($this->details['appointment_id'] ?? 0);
        $actionUrls = AppointmentActionLink::emailButtonUrls($appointmentId > 0 ? $appointmentId : null);

        return new Content(
            view: 'emails.appointment-reschedule',
            with: [
                'clientName' => $this->details['client_name'] ?? 'Valued Client',
                'oldDate' => $this->details['old_datetime']?->format('l, d F Y') ?? 'N/A',
                'oldTime' => $this->details['old_datetime']?->format('h:i A') ?? 'N/A',
                'newDate' => $this->details['appointment_datetime']?->format('l, d F Y') ?? 'N/A',
                'newTime' => $this->details['timeslot_full'] ?? ($this->details['appointment_datetime']?->format('h:i A') ?? 'N/A'),
                'location' => ucfirst($locationKey),
                'locationAddress' => $this->getLocationAddress($locationKey),
                'serviceType' => filled($this->details['service_type'] ?? null)
                    ? (string) $this->details['service_type']
                    : 'N/A',
                'meetingTypeLabel' => AppointmentMeetingTypeCopy::label($meetingType),
                'reminderTitle' => AppointmentMeetingTypeCopy::reminderTitle($meetingType),
                'reminderBody' => AppointmentMeetingTypeCopy::reminderBody($meetingType),
                'bringTitle' => AppointmentMeetingTypeCopy::bringTitle($meetingType),
                'bringItems' => AppointmentMeetingTypeCopy::bringItems($meetingType),
                'locationPhone' => $this->getLocationPhone($locationKey),
                'locationPhoneTel' => str_replace(
                    [' ', '-'],
                    '',
                    $this->getLocationPhone($locationKey)
                ),
                'cancelUrl' => $actionUrls['cancel'] ?? null,
                'rescheduleUrl' => $actionUrls['reschedule'] ?? null,
                'confirmUrl' => $actionUrls['confirm'] ?? null,
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
