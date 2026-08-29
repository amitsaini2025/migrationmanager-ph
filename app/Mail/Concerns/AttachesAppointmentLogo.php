<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Attachment;

trait AttachesAppointmentLogo
{
    /**
     * Downloadable Bansal logo attachment (inline header logo is unchanged).
     *
     * @return array<int, Attachment>
     */
    protected function appointmentLogoAttachments(): array
    {
        $path = public_path('img/logo.png');

        if (! is_file($path)) {
            return [];
        }

        return [
            Attachment::fromPath($path)
                ->as('Bansal-Immigration-Logo.png')
                ->withMime('image/png'),
        ];
    }
}
