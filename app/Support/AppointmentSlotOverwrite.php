<?php

namespace App\Support;

class AppointmentSlotOverwrite
{
    /**
     * CRM schedule form sends slot_overwrite_hidden (0/1). Checkbox is a fallback.
     * Only the integer 1 enables overwrite; any other value is 0.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromRequest(array $input): int
    {
        $hidden = (int) ($input['slot_overwrite_hidden'] ?? 0);
        $checkbox = (int) ($input['slot_overwrite'] ?? 0);

        return ($hidden === 1 || $checkbox === 1) ? 1 : 0;
    }
}
