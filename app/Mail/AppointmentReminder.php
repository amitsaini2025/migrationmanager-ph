<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentReminder extends Mailable
{
    use Queueable, SerializesModels, UsesAppointmentMailFrom;

    public function __construct(
        public array $details
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->appointmentFromAddress(),
            subject: 'Appointment Reminder - Bansal Immigration',
        );
    }

    public function content(): Content
    {
        $dt = $this->details['appointment_datetime'] ?? null;
        $location = $this->details['location'] ?? 'melbourne';
        $clientName = $this->details['client_name'] ?? 'Valued Client';
        $appointmentDate = $dt?->format('l, d F Y') ?? 'N/A';
        $appointmentTime = $this->details['timeslot_full'] ?? 'N/A';
        $slotSubjectDate = $dt?->format('F j') ?? 'N/A';

        $locationPhone = $this->getLocationPhone($location);
        $locationPhoneTel = str_replace([' ', '-'], '', $locationPhone);
        $resumeDateFragment = $dt?->format('j F Y') ?? 'N/A';
        $resumeMailtoHref = 'mailto:info@bansalimmigration.com?subject='.rawurlencode(
            'Resume – [Your Full Name] – '.$resumeDateFragment.' Appointment'
        );

        $confirmBody = "Hi Bansal Immigration Team,\r\n\r\nI confirm my attendance for the appointment on {$appointmentDate} ({$appointmentTime}).\r\n\r\nRegards";
        $confirmMailtoHref = 'mailto:info@bansalimmigration.com?subject='.rawurlencode(
            'CONFIRM – '.$slotSubjectDate.' Appointment'
        ).'&body='.rawurlencode($confirmBody);

        $cancelBody = "Hi Bansal Immigration Team,\r\n\r\nI would like to cancel my appointment scheduled for {$appointmentDate} ({$appointmentTime}).\r\n\r\nReason (optional): [Please enter reason]\r\n\r\nRegards";
        $cancelMailtoHref = 'mailto:info@bansalimmigration.com?subject='.rawurlencode(
            'CANCEL – '.$slotSubjectDate.' Appointment'
        ).'&body='.rawurlencode($cancelBody);

        $daysUntil = 0;
        if ($dt) {
            $aptDay = $dt->copy()->startOfDay();
            $today = now()->startOfDay();
            if ($aptDay->gte($today)) {
                $daysUntil = (int) $today->diffInDays($aptDay);
            }
        }

        return new Content(
            view: 'emails.appointment-reminder',
            with: [
                'clientName' => $clientName,
                'appointmentDate' => $appointmentDate,
                'appointmentTime' => $appointmentTime,
                'locationAddress' => $this->getLocationAddress($location),
                'locationPhone' => $locationPhone,
                'locationPhoneTel' => $locationPhoneTel,
                'daysUntil' => $daysUntil,
                'confirmMailtoHref' => $confirmMailtoHref,
                'cancelMailtoHref' => $cancelMailtoHref,
                'resumeMailtoHref' => $resumeMailtoHref,
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
