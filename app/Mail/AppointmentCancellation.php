<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use App\Support\AppointmentMeetingTypeCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AppointmentCancellation extends Mailable
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
            subject: 'Appointment Cancellation - Bansal Immigration',
        );
    }

    /**
     * Disable SendGrid click-tracking so mailto / tel / HTTPS call-bridge links are not rewritten.
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
        $clientName = $this->details['client_name'] ?? 'Valued Client';
        $locationKey = $this->normalizeLocationKey($this->details['location'] ?? null);
        $locationPhone = $this->getLocationPhone($locationKey);
        $callUsHref = $this->buildCallUsWebUrl($this->getCallBridgeDigits($locationKey));
        $rescheduleBody = "Hi Bansal Immigration Team,\r\n\r\nI would like to reschedule my cancelled appointment. Please let me know your available slots.\r\n\r\nRegards";
        $rescheduleMailtoHref = 'mailto:info@bansalimmigration.com.au?subject='.rawurlencode(
            'Reschedule Request – '.$clientName
        ).'&body='.rawurlencode($rescheduleBody);
        $meetingType = (string) ($this->details['meeting_type'] ?? '');

        return new Content(
            view: 'emails.appointment-cancellation',
            with: [
                'clientName' => $clientName,
                'appointmentDate' => $this->details['appointment_datetime']?->format('l, d F Y') ?? 'N/A',
                'appointmentTime' => $this->details['timeslot_full'] ?? 'N/A',
                'location' => ucfirst($locationKey),
                'locationAddress' => $this->getLocationAddress($locationKey),
                'serviceType' => filled($this->details['service_type'] ?? null)
                    ? (string) $this->details['service_type']
                    : 'N/A',
                'meetingTypeLabel' => AppointmentMeetingTypeCopy::label($meetingType),
                'locationPhone' => $locationPhone,
                'callUsHref' => $callUsHref,
                'rescheduleMailtoHref' => $rescheduleMailtoHref,
                'cancellationReason' => $this->details['cancellation_reason'] ?? null,
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
            default => 'Bansal Immigration Office',
        };
    }

    /**
     * Normalise location from DB/API (e.g. "Melbourne" → "melbourne") for match().
     */
    protected function normalizeLocationKey(mixed $location): string
    {
        if (! is_string($location)) {
            return 'melbourne';
        }

        $loc = strtolower(trim($location));

        return $loc !== '' ? $loc : 'melbourne';
    }

    protected function getLocationPhone(string $location): string
    {
        return match ($location) {
            'adelaide' => '0883171340',
            'melbourne' => '+61 3 9602 1330',
            default => '1300 859 368',
        };
    }

    /**
     * Digits for /phone-call?n= (bridge prepends "+" for tel:).
     */
    protected function getCallBridgeDigits(string $location): string
    {
        return match ($location) {
            'adelaide' => '61883171340',
            'melbourne' => '61396021330',
            default => '611300859368',
        };
    }

    /**
     * HTTPS bridge URL (same flow as website): opens browser, then navigates to tel: so the
     * OS "pick an application / dial" prompt can appear on desktop and mobile.
     */
    protected function buildCallUsWebUrl(string $digits): string
    {
        $base = config('services.bansal_public.call_us_bridge_base_url');
        if (is_string($base) && $base !== '') {
            $base = rtrim($base, '/');
            $sep = str_contains($base, '?') ? '&' : '?';

            return $base.$sep.'n='.rawurlencode($digits);
        }

        return route('public.phone-call', ['n' => $digits], true);
    }
}
