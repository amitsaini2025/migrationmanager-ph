<?php

namespace App\Support;

use Carbon\Carbon;

final class ClientDetailVerificationFields
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHANGE_REQUESTED = 'change_requested';

    public const STATUS_ACCEPTED = 'accepted';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'full_name',
            'dob',
            'gender',
            'marital_status',
            'email',
            'phone',
            'address',
            'visa_type',
            'visa_expiry',
            'passport_country',
            'location_status',
        ];
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'full_name' => 'Full Name',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'marital_status' => 'Marital Status',
            'email' => 'Email Address',
            'phone' => 'Mobile Number',
            'address' => 'Residential Address',
            'visa_type' => 'Current Visa Type / Status',
            'visa_expiry' => 'Visa Expiry Date',
            'passport_country' => 'Country of Passport',
            'location_status' => 'Current Location',
            default => $key,
        };
    }

    public static function isKnownKey(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_CONFIRMED,
            self::STATUS_CHANGE_REQUESTED,
            self::STATUS_ACCEPTED,
        ];
    }

    public static function displayValue(?string $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : 'N/A';
    }

    public static function formatDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'N/A';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return self::displayValue($value);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function buildSnapshot(array $input): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', trim(($input['first_name'] ?? '').' '.($input['last_name'] ?? ''))) ?? '');

        return [
            'full_name' => self::displayValue($fullName),
            'dob' => self::formatDate(isset($input['dob']) ? (string) $input['dob'] : null),
            'gender' => self::displayValue($input['gender'] ?? null),
            'marital_status' => self::displayValue($input['marital_status'] ?? null),
            'email' => self::displayValue($input['primary_email'] ?? null),
            'phone' => self::displayValue($input['primary_phone'] ?? null),
            'address' => self::displayValue($input['address'] ?? null),
            'visa_type' => self::displayValue($input['visa_type'] ?? null),
            'visa_expiry' => self::formatDate(isset($input['visa_expiry']) ? (string) $input['visa_expiry'] : null),
            'passport_country' => self::displayValue($input['passport_country'] ?? null),
            'location_status' => self::displayValue($input['location_status'] ?? null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{ok: bool, message?: string}
     */
    public static function validateSubmittedFields(array $fields): array
    {
        $byKey = [];
        foreach ($fields as $field) {
            $check = self::validateSubmittedField($field);
            if (! $check['ok']) {
                return $check;
            }
            $byKey[(string) ($field['key'] ?? '')] = true;
        }

        foreach (self::keys() as $key) {
            if (! isset($byKey[$key])) {
                return ['ok' => false, 'message' => 'Missing field: '.self::label($key)];
            }
        }

        return ['ok' => true];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitFullName(string $fullName): array
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = explode(' ', $fullName);
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $lastName = array_pop($parts);

        return [implode(' ', $parts), $lastName];
    }

    public static function composeAddress(
        ?string $line1,
        ?string $line2,
        ?string $suburb,
        ?string $state,
        ?string $country,
        ?string $zip,
        ?string $legacyAddress = null,
    ): string {
        $parts = array_filter([
            trim((string) $line1),
            trim((string) $line2),
            trim((string) $suburb),
            trim((string) $state),
            trim((string) $country),
            trim((string) $zip),
        ], static fn (string $part): bool => $part !== '');

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return self::displayValue($legacyAddress);
    }

    public static function locationFromCountry(?string $country): string
    {
        $country = trim((string) $country);
        if ($country === '') {
            return 'N/A';
        }

        if (strcasecmp($country, 'Australia') === 0 || strcasecmp($country, 'AU') === 0) {
            return 'Onshore - Australia';
        }

        return 'Offshore - Outside Australia';
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{ok: bool, message?: string}
     */
    public static function validateSubmittedField(array $field): array
    {
        $key = (string) ($field['key'] ?? '');
        if (! self::isKnownKey($key)) {
            return ['ok' => false, 'message' => 'Unknown field: '.$key];
        }

        $status = (string) ($field['status'] ?? '');
        if (! in_array($status, [self::STATUS_CONFIRMED, self::STATUS_CHANGE_REQUESTED], true)) {
            return ['ok' => false, 'message' => 'Invalid status for '.$key];
        }

        if ($status === self::STATUS_CHANGE_REQUESTED) {
            $requested = trim((string) ($field['requested_value'] ?? ''));
            if ($requested === '') {
                return ['ok' => false, 'message' => 'A new value is required for '.self::label($key)];
            }
        }

        return ['ok' => true];
    }
}
