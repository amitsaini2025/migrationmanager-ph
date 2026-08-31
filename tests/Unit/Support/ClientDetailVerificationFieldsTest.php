<?php

namespace Tests\Unit\Support;

use App\Support\ClientDetailVerificationFields;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientDetailVerificationFieldsTest extends TestCase
{
    #[Test]
    public function build_snapshot_uses_primary_email_and_phone_only(): void
    {
        $snapshot = ClientDetailVerificationFields::buildSnapshot([
            'first_name' => 'Vipul',
            'last_name' => 'Kumar',
            'dob' => '1986-03-15',
            'gender' => 'Other',
            'marital_status' => 'Married',
            'primary_email' => 'primary@example.com',
            'primary_phone' => '0412 345 678',
            'address' => 'Melbourne, Australia',
            'visa_type' => '408 - Temporary Activity',
            'visa_expiry' => '2026-12-01',
            'passport_country' => 'India',
            'location_status' => 'Onshore - Australia',
        ]);

        $this->assertSame('Vipul Kumar', $snapshot['full_name']);
        $this->assertSame('15/03/1986', $snapshot['dob']);
        $this->assertSame('primary@example.com', $snapshot['email']);
        $this->assertSame('0412 345 678', $snapshot['phone']);
        $this->assertSame('01/12/2026', $snapshot['visa_expiry']);
        $this->assertArrayNotHasKey('work_email', $snapshot);
        $this->assertCount(11, $snapshot);
    }

    #[Test]
    public function validate_submitted_fields_requires_every_known_key(): void
    {
        $fields = array_map(static fn (string $key): array => [
            'key' => $key,
            'status' => ClientDetailVerificationFields::STATUS_CONFIRMED,
        ], ClientDetailVerificationFields::keys());

        $this->assertTrue(ClientDetailVerificationFields::validateSubmittedFields($fields)['ok']);

        array_pop($fields);
        $result = ClientDetailVerificationFields::validateSubmittedFields($fields);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Missing field', $result['message'] ?? '');
    }

    #[Test]
    public function change_request_requires_a_new_value(): void
    {
        $result = ClientDetailVerificationFields::validateSubmittedField([
            'key' => 'email',
            'status' => ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED,
            'requested_value' => '',
        ]);

        $this->assertFalse($result['ok']);
    }

    #[Test]
    public function split_full_name_and_location_helpers_work(): void
    {
        $this->assertSame(['Vipul', 'Kumar'], ClientDetailVerificationFields::splitFullName('Vipul Kumar'));
        $this->assertSame('Onshore - Australia', ClientDetailVerificationFields::locationFromCountry('Australia'));
        $this->assertSame('Offshore - Outside Australia', ClientDetailVerificationFields::locationFromCountry('India'));
    }
}
