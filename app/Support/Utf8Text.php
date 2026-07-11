<?php

namespace App\Support;

/**
 * Normalize text to valid UTF-8 so JSON API responses do not fail on imported email data.
 */
class Utf8Text
{
    public static function clean(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * Recursively sanitize strings inside arrays (e.g. email list payloads).
     *
     * @param  mixed  $value
     * @return mixed
     */
    public static function cleanDeep($value)
    {
        if (is_string($value)) {
            return self::clean($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::cleanDeep($item);
        }

        return $value;
    }
}
