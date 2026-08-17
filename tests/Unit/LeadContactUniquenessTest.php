<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Services\BansalAppointmentSync\ClientMatchingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadContactUniquenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createUniquenessSchema();
    }

    public function test_email_normalization_is_lowercase_and_trimmed(): void
    {
        $this->assertSame(
            'vipul@yahoo.co.in',
            Admin::normalizeEmailForUniqueness('  Vipul@Yahoo.co.in  ')
        );
    }

    public function test_phone_normalization_keeps_digits_only(): void
    {
        $this->assertSame(
            '0412345678',
            Admin::normalizePhoneDigitsForUniqueness('0412 345-678')
        );
        $this->assertSame(
            '0412345678',
            Admin::normalizePhoneDigitsForUniqueness('(0412) 345.678')
        );
    }

    public function test_email_is_taken_ignores_case_and_spaces(): void
    {
        Admin::factory()->create([
            'type' => 'lead',
            'email' => 'vipul@yahoo.co.in',
            'is_company' => 0,
        ]);

        $this->assertTrue(Admin::emailIsTaken('  VIPUL@yahoo.co.in '));
        $this->assertFalse(Admin::emailIsTaken('other.person@test.com'));
    }

    public function test_email_is_taken_checks_client_emails_table(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'primary@test.com',
            'is_company' => 0,
        ]);

        ClientEmail::create([
            'client_id' => $lead->id,
            'email' => 'extra@test.com',
        ]);

        $this->assertTrue(Admin::emailIsTaken('EXTRA@test.com'));
        $this->assertFalse(Admin::emailIsTaken('extra@test.com', (int) $lead->id));
    }

    public function test_phone_is_taken_ignores_spaces_and_dashes(): void
    {
        Admin::factory()->create([
            'type' => 'lead',
            'email' => 'phone.owner@test.com',
            'phone' => '0412345678',
            'is_company' => 0,
        ]);

        $this->assertTrue(Admin::phoneIsTaken('0412 345-678'));
        $this->assertFalse(Admin::phoneIsTaken('0499888777'));
    }

    public function test_phone_is_taken_checks_client_contacts_table(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'contact.owner@test.com',
            'phone' => '0400000001',
            'is_company' => 0,
        ]);

        ClientContact::create([
            'client_id' => $lead->id,
            'phone' => '0412-999-888',
        ]);

        $this->assertTrue(Admin::phoneIsTaken('0412 999 888'));
        $this->assertFalse(Admin::phoneIsTaken('0412 999 888', (int) $lead->id));
    }

    public function test_find_personal_client_matches_normalized_email(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'first_name' => 'Vipul',
            'last_name' => 'Kumar',
            'email' => 'vipul@yahoo.co.in',
            'is_company' => 0,
        ]);

        $found = Admin::findPersonalClientOrLeadByNormalizedContact(null, 'VIPUL@yahoo.co.in');

        $this->assertNotNull($found);
        $this->assertSame($lead->id, $found->id);
    }

    public function test_appointment_match_reuses_email_ignoring_case_and_spaces(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'vipul@yahoo.co.in',
            'phone' => '0499111222',
            'is_company' => 0,
        ]);

        $found = Admin::findClientOrLeadByNormalizedEmail('  VIPUL@yahoo.co.in ');

        $this->assertNotNull($found);
        $this->assertSame($lead->id, $found->id);
    }

    public function test_appointment_match_reuses_extra_client_email(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'client',
            'email' => 'primary.match@test.com',
            'phone' => '0499111333',
            'is_company' => 0,
        ]);
        ClientEmail::create([
            'client_id' => $lead->id,
            'email' => 'extra.match@test.com',
        ]);

        $found = Admin::findClientOrLeadByNormalizedEmail('EXTRA.match@test.com');

        $this->assertNotNull($found);
        $this->assertSame($lead->id, $found->id);
    }

    public function test_appointment_match_reuses_au_phone_variant(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'au.phone@test.com',
            'phone' => '0412345678',
            'is_company' => 0,
        ]);

        $found = Admin::findClientOrLeadByNormalizedPhone('+61 412 345 678');

        $this->assertNotNull($found);
        $this->assertSame($lead->id, $found->id);
    }

    public function test_bansal_sync_reuses_existing_lead_instead_of_creating(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'bansal.match@test.com',
            'phone' => '0412000999',
            'is_company' => 0,
        ]);

        $matched = app(ClientMatchingService::class)->findOrCreateClient([
            'email' => 'BANSAL.match@test.com',
            'phone' => '0412 000 888',
            'full_name' => 'Someone Else',
        ]);

        $this->assertNotNull($matched);
        $this->assertSame($lead->id, $matched->id);
        $this->assertSame(1, Admin::whereIn('type', ['client', 'lead'])->count());
    }

    private function createUniquenessSchema(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('password')->nullable();
                $table->string('type')->nullable();
                $table->string('country')->nullable();
                $table->string('state')->nullable();
                $table->string('city')->nullable();
                $table->string('address')->nullable();
                $table->string('zip')->nullable();
                $table->integer('status')->nullable();
                $table->date('dob')->nullable();
                $table->unsignedInteger('is_deleted')->nullable();
                $table->unsignedInteger('is_archived')->nullable();
                $table->unsignedTinyInteger('is_company')->nullable();
                $table->string('client_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('admins', 'is_company')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->unsignedTinyInteger('is_company')->nullable();
            });
        }

        if (! Schema::hasTable('client_emails')) {
            Schema::create('client_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('email')->nullable();
                $table->string('email_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_contacts')) {
            Schema::create('client_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('phone')->nullable();
                $table->string('contact_type')->nullable();
                $table->timestamps();
            });
        }
    }
}
