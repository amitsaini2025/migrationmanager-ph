<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

trait UsesAppointmentMailFrom
{
    protected function appointmentFromAddress(): Address
    {
        return new Address(
            (string) config('mail.noreply.address', 'noreply@bansalimmigration.com.au'),
            (string) config('mail.from.name', 'Bansal Immigration'),
        );
    }

    /**
     * @return $this
     */
    protected function applyAppointmentFrom(): static
    {
        $from = $this->appointmentFromAddress();

        return $this->from($from->address, $from->name);
    }
}
