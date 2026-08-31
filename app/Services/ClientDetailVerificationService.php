<?php

namespace App\Services;

use App\Helpers\PhoneValidationHelper;
use App\Mail\ClientDetailVerificationMail;
use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientDetailVerification;
use App\Models\ClientDetailVerificationField;
use App\Models\ClientEmail;
use App\Models\ClientPassportInformation;
use App\Models\ClientVisaCountry;
use App\Models\Matter;
use App\Support\ClientDetailVerificationFields;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ClientDetailVerificationService
{
    /**
     * @return array{success: bool, message: string}
     */
    public function sendLink(Admin $client, ?int $sentBy = null): array
    {
        $email = trim((string) ($client->email ?? ''));
        if ($email === '') {
            return [
                'success' => false,
                'message' => 'This client has no primary email address.',
            ];
        }

        $plainToken = ClientDetailVerification::generateToken();
        $snapshot = $this->snapshotFor($client);

        $verification = DB::transaction(function () use ($client, $sentBy, $email, $plainToken, $snapshot): ClientDetailVerification {
            $this->invalidateUnusedForClient((int) $client->id);

            return ClientDetailVerification::query()->create([
                'client_id' => $client->id,
                'token_hash' => ClientDetailVerification::hashToken($plainToken),
                'sent_to_email' => $email,
                'sent_by' => $sentBy,
                'snapshot' => $snapshot,
            ]);
        });

        $url = route('public.client-detail-verification.show', ['token' => $plainToken]);

        try {
            Mail::mailer('sendgrid')->to($email)->send(new ClientDetailVerificationMail(
                (string) $client->first_name,
                $url,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send client detail verification email', [
                'client_id' => $client->id,
                'verification_id' => $verification->id,
                'error' => $e->getMessage(),
            ]);

            $verification->update(['invalidated_at' => now()]);

            return [
                'success' => false,
                'message' => 'Failed to send the verification email. Please try again.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Verification link sent to '.$email,
        ];
    }

    public function findUsableByToken(string $token): ?ClientDetailVerification
    {
        if ($token === '') {
            return null;
        }

        $verification = ClientDetailVerification::query()
            ->where('token_hash', ClientDetailVerification::hashToken($token))
            ->first();

        if (! $verification || ! $verification->isUsable()) {
            return null;
        }

        return $verification;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function submit(ClientDetailVerification $verification, array $fields, ?string $ip = null, ?string $userAgent = null): void
    {
        $check = ClientDetailVerificationFields::validateSubmittedFields($fields);
        if (! $check['ok']) {
            throw ValidationException::withMessages([
                'fields' => [$check['message'] ?? 'Invalid verification payload.'],
            ]);
        }

        DB::transaction(function () use ($verification, $fields, $ip, $userAgent): void {
            $locked = ClientDetailVerification::query()
                ->where('id', $verification->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isUsable()) {
                throw ValidationException::withMessages([
                    'token' => ['This verification link has expired or has already been used.'],
                ]);
            }

            $byKey = [];
            foreach ($fields as $field) {
                $byKey[(string) $field['key']] = $field;
            }

            $snapshot = is_array($locked->snapshot) ? $locked->snapshot : [];

            foreach (ClientDetailVerificationFields::keys() as $key) {
                $field = $byKey[$key];
                $status = (string) $field['status'];

                ClientDetailVerificationField::query()->create([
                    'verification_id' => $locked->id,
                    'client_id' => $locked->client_id,
                    'field_key' => $key,
                    'original_value' => $snapshot[$key] ?? ($field['current_value'] ?? null),
                    'requested_value' => $status === ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED
                        ? trim((string) ($field['requested_value'] ?? ''))
                        : null,
                    'status' => $status,
                    'note' => isset($field['note']) ? trim((string) $field['note']) : null,
                ]);
            }

            $locked->update([
                'used_at' => now(),
                'submitted_at' => now(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
        });
    }

    public function acceptChange(ClientDetailVerificationField $field, int $acceptedBy): ClientDetailVerificationField
    {
        if (! $field->isPendingChange()) {
            throw ValidationException::withMessages([
                'field' => ['This change request has already been processed.'],
            ]);
        }

        return DB::transaction(function () use ($field, $acceptedBy): ClientDetailVerificationField {
            $locked = ClientDetailVerificationField::query()
                ->where('id', $field->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->isPendingChange()) {
                throw ValidationException::withMessages([
                    'field' => ['This change request has already been processed.'],
                ]);
            }

            $client = Admin::query()
                ->where('id', $locked->client_id)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (! $client) {
                throw ValidationException::withMessages([
                    'field' => ['Client record was not found.'],
                ]);
            }

            $this->applyAcceptedValue($client, $locked->field_key, (string) $locked->requested_value, (string) $locked->original_value);

            $locked->update([
                'status' => ClientDetailVerificationFields::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'accepted_by' => $acceptedBy,
            ]);

            return $locked->fresh();
        });
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function latestStatuses(int $clientId): array
    {
        if (! Schema::hasTable('client_detail_verification_fields')) {
            return [];
        }

        $rows = ClientDetailVerificationField::query()
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->get();

        $latest = [];
        foreach ($rows as $row) {
            if (isset($latest[$row->field_key])) {
                continue;
            }
            $latest[$row->field_key] = [
                'id' => $row->id,
                'field_key' => $row->field_key,
                'original_value' => $row->original_value,
                'requested_value' => $row->requested_value,
                'status' => $row->status,
            ];
        }

        return $latest;
    }

    /**
     * @return array<string, string>
     */
    public function snapshotFor(Admin $client): array
    {
        return ClientDetailVerificationFields::buildSnapshot([
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'dob' => $client->dob,
            'gender' => $client->gender,
            'marital_status' => $client->marital_status,
            'primary_email' => $client->email,
            'primary_phone' => $this->primaryPhoneDisplay($client),
            'address' => $this->primaryAddressDisplay($client),
            'visa_type' => $this->primaryVisaTypeDisplay($client),
            'visa_expiry' => $this->primaryVisaExpiryRaw($client),
            'passport_country' => $this->primaryPassportCountry($client),
            'location_status' => $this->locationStatusDisplay($client),
        ]);
    }

    public function invalidateUnusedForClient(int $clientId): void
    {
        ClientDetailVerification::query()
            ->where('client_id', $clientId)
            ->whereNull('used_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);
    }

    private function primaryPhoneDisplay(Admin $client): string
    {
        $phone = trim((string) ($client->phone ?? ''));
        if ($phone === '') {
            return '';
        }

        return PhoneValidationHelper::formatAustralianPhone($phone, $client->country_code);
    }

    private function primaryAddressDisplay(Admin $client): string
    {
        if (! Schema::hasTable('client_addresses')) {
            return ClientDetailVerificationFields::composeAddress(
                null,
                null,
                $client->city ?? null,
                $client->state ?? null,
                $client->country ?? null,
                $client->zip ?? null,
                $client->address ?? null,
            );
        }

        $address = ClientAddress::query()
            ->where('client_id', $client->id)
            ->where('is_current', 1)
            ->first();

        if (! $address) {
            $address = ClientAddress::query()
                ->where('client_id', $client->id)
                ->latest('id')
                ->first();
        }

        if (! $address) {
            return ClientDetailVerificationFields::composeAddress(
                null,
                null,
                $client->city ?? null,
                $client->state ?? null,
                $client->country ?? null,
                $client->zip ?? null,
                $client->address ?? null,
            );
        }

        $composed = ClientDetailVerificationFields::composeAddress(
            $address->address_line_1 ?? null,
            $address->address_line_2 ?? null,
            $address->suburb ?? null,
            $address->state ?? null,
            $address->country ?? null,
            $address->zip ?? null,
            $address->address ?? null,
        );

        return $composed === 'N/A' ? '' : $composed;
    }

    private function latestVisa(Admin $client): ?ClientVisaCountry
    {
        if (! Schema::hasTable('client_visa_countries')) {
            return null;
        }

        $withExpiry = ClientVisaCountry::query()
            ->where('client_id', $client->id)
            ->whereNotNull('visa_expiry_date')
            ->orderByDesc('visa_expiry_date')
            ->first();

        if ($withExpiry) {
            return $withExpiry;
        }

        return ClientVisaCountry::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->first();
    }

    private function primaryVisaTypeDisplay(Admin $client): string
    {
        $visa = $this->latestVisa($client);
        if ($visa && $visa->visa_type !== null && $visa->visa_type !== '') {
            if (Schema::hasTable('matters')) {
                $matter = Matter::query()->select(['id', 'title', 'nick_name'])->find($visa->visa_type);
                if ($matter) {
                    $title = trim((string) $matter->title);
                    $nick = trim((string) $matter->nick_name);

                    return $nick !== '' ? $title.'('.$nick.')' : $title;
                }
            }

            if ($visa->visa_description) {
                return (string) $visa->visa_description;
            }
        }

        return (string) ($client->visa_type ?? '');
    }

    private function primaryVisaExpiryRaw(Admin $client): string
    {
        $visa = $this->latestVisa($client);
        if ($visa && $visa->visa_expiry_date) {
            return (string) $visa->visa_expiry_date;
        }

        return (string) ($client->visaExpiry ?? '');
    }

    private function primaryPassportCountry(Admin $client): string
    {
        $fromAdmin = trim((string) ($client->country_passport ?? ''));
        if ($fromAdmin !== '') {
            return $fromAdmin;
        }

        if (! Schema::hasTable('client_passport_informations')) {
            return '';
        }

        $passport = ClientPassportInformation::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->first();

        return trim((string) ($passport->passport_country ?? ''));
    }

    private function locationStatusDisplay(Admin $client): string
    {
        $country = '';
        if (Schema::hasTable('client_addresses')) {
            $address = ClientAddress::query()
                ->where('client_id', $client->id)
                ->where('is_current', 1)
                ->first();
            $country = trim((string) ($address->country ?? ''));
        }

        if ($country === '') {
            $country = trim((string) ($client->country ?? ''));
        }

        $mapped = ClientDetailVerificationFields::locationFromCountry($country);

        return $mapped === 'N/A' ? '' : $mapped;
    }

    private function applyAcceptedValue(Admin $client, string $key, string $requested, string $original): void
    {
        $requested = trim($requested);

        match ($key) {
            'full_name' => $this->applyFullName($client, $requested),
            'dob' => $this->applyDob($client, $requested),
            'gender' => $this->assignAndSave($client, 'gender', $requested),
            'marital_status' => $this->assignAndSave($client, 'marital_status', $requested),
            'email' => $this->applyPrimaryEmail($client, $requested, $original),
            'phone' => $this->applyPrimaryPhone($client, $requested),
            'address' => $this->applyAddress($client, $requested),
            'visa_type' => $this->applyVisaType($client, $requested),
            'visa_expiry' => $this->applyVisaExpiry($client, $requested),
            'passport_country' => $this->applyPassportCountry($client, $requested),
            'location_status' => $this->applyLocationStatus($client, $requested),
            default => null,
        };
    }

    private function applyFullName(Admin $client, string $fullName): void
    {
        [$first, $last] = ClientDetailVerificationFields::splitFullName($fullName);
        $client->first_name = $first;
        $client->last_name = $last;
        $client->save();
    }

    private function applyDob(Admin $client, string $value): void
    {
        $client->dob = $this->parseDateOrRaw($value);
        $client->save();
    }

    private function applyPrimaryEmail(Admin $client, string $newEmail, string $original): void
    {
        $newEmail = Admin::sanitizeEmailAddress($newEmail);
        $oldEmail = Admin::sanitizeEmailAddress($original === 'N/A' ? (string) $client->email : $original);

        $client->email = $newEmail;
        $client->save();

        if (Schema::hasTable('client_emails')) {
            $row = ClientEmail::query()
                ->where('client_id', $client->id)
                ->where('email', $oldEmail)
                ->first();

            if ($row) {
                $row->update(['email' => $newEmail]);
            }
        }
    }

    private function applyPrimaryPhone(Admin $client, string $requested): void
    {
        $previousPhone = (string) $client->phone;
        $digits = preg_replace('/[^\d]/', '', $requested) ?? '';
        $client->phone = $digits !== '' ? $digits : $requested;
        $client->save();

        if (Schema::hasTable('client_contacts')) {
            $row = ClientContact::query()
                ->where('client_id', $client->id)
                ->where('phone', $previousPhone)
                ->first();

            if ($row) {
                $row->update(['phone' => $client->phone]);
            }
        }
    }

    private function applyAddress(Admin $client, string $requested): void
    {
        if (! Schema::hasTable('client_addresses')) {
            $client->address = $requested;
            $client->save();

            return;
        }

        $address = ClientAddress::query()
            ->where('client_id', $client->id)
            ->where('is_current', 1)
            ->first();

        if (! $address) {
            $address = ClientAddress::query()
                ->where('client_id', $client->id)
                ->latest('id')
                ->first();
        }

        if ($address) {
            $address->update([
                'address' => $requested,
                'address_line_1' => $requested,
            ]);

            return;
        }

        ClientAddress::query()->create([
            'client_id' => $client->id,
            'admin_id' => $client->id,
            'address' => $requested,
            'address_line_1' => $requested,
            'is_current' => 1,
        ]);
    }

    private function applyVisaType(Admin $client, string $requested): void
    {
        if (Schema::hasColumn('admins', 'visa_type')) {
            $client->visa_type = $requested;
            $client->save();
        }

        $visa = $this->latestVisa($client);
        if (! $visa) {
            return;
        }

        $matter = null;
        if (Schema::hasTable('matters')) {
            $matter = Matter::query()
                ->where(function ($query) use ($requested): void {
                    $query->where('title', $requested)->orWhere('nick_name', $requested);
                })
                ->first();
        }

        if ($matter) {
            $visa->update(['visa_type' => $matter->id]);

            return;
        }

        $visa->update(['visa_description' => $requested]);
    }

    private function applyVisaExpiry(Admin $client, string $requested): void
    {
        $date = $this->parseDateOrRaw($requested);
        $visa = $this->latestVisa($client);
        if ($visa) {
            $visa->update(['visa_expiry_date' => $date]);

            return;
        }

        if (Schema::hasColumn('admins', 'visaExpiry')) {
            $client->visaExpiry = $date;
            $client->save();
        }
    }

    private function applyPassportCountry(Admin $client, string $requested): void
    {
        if (Schema::hasColumn('admins', 'country_passport')) {
            $client->country_passport = $requested;
            $client->save();
        }

        if (! Schema::hasTable('client_passport_informations')) {
            return;
        }

        $passport = ClientPassportInformation::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->first();

        if ($passport) {
            $passport->update(['passport_country' => $requested]);
        }
    }

    private function applyLocationStatus(Admin $client, string $requested): void
    {
        if (stripos($requested, 'Onshore') !== false) {
            if (Schema::hasTable('client_addresses')) {
                $address = ClientAddress::query()
                    ->where('client_id', $client->id)
                    ->where('is_current', 1)
                    ->first();
                if ($address) {
                    $address->update(['country' => 'Australia']);
                }
            }
            $client->country = 'Australia';
            $client->save();
        }
    }

    private function assignAndSave(Admin $client, string $column, string $value): void
    {
        $client->{$column} = $value;
        $client->save();
    }

    private function parseDateOrRaw(string $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }
}
