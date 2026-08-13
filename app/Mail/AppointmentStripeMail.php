<?php

namespace App\Mail;

use App\Mail\Concerns\UsesAppointmentMailFrom;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentStripeMail extends Mailable
{
    use Queueable, SerializesModels, UsesAppointmentMailFrom;

    public $details;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($details)
    {
        $this->details = $details;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $serviceTitle = $this->details['service'];
        $subject = "Confirmation of Your {$serviceTitle} Appointment";

        return $this->applyAppointmentFrom()
            ->subject($subject)
            ->view('emails.appointmentstripe');
    }
}
