<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackStaffCrmActivity;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FrontDeskCreateLeadUniquenessTest extends TestCase
{
    protected Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            TrackStaffCrmActivity::class,
        ]);

        $this->createSchema();

        $this->staff = Staff::create([
            'first_name' => 'Front',
            'last_name' => 'Desk',
            'email' => 'frontdesk-uniqueness@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    public function test_create_lead_rejects_duplicate_phone_ignoring_spaces_and_dashes(): void
    {
        Admin::factory()->create([
            'type' => 'lead',
            'email' => 'existing.phone@test.com',
            'phone' => '0412345678',
            'is_company' => 0,
            'is_deleted' => null,
        ]);

        $before = Admin::whereIn('type', ['client', 'lead'])->count();

        $response = $this->actingAs($this->staff, 'admin')
            ->postJson(route('front-desk.checkin.create-lead'), [
                'first_name' => 'New',
                'last_name' => 'Visitor',
                'phone' => '0412 345-678',
                'email' => 'brand.new@test.com',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('phone', strtolower((string) $response->json('message')));
        $this->assertSame($before, Admin::whereIn('type', ['client', 'lead'])->count());
    }

    public function test_create_lead_rejects_duplicate_email_ignoring_case(): void
    {
        Admin::factory()->create([
            'type' => 'client',
            'email' => 'vipul@yahoo.co.in',
            'phone' => '0499000111',
            'is_company' => 0,
            'is_deleted' => null,
        ]);

        $before = Admin::whereIn('type', ['client', 'lead'])->count();

        $response = $this->actingAs($this->staff, 'admin')
            ->postJson(route('front-desk.checkin.create-lead'), [
                'first_name' => 'Vipul',
                'last_name' => 'Kumar',
                'phone' => '0499000222',
                'email' => '  VIPUL@yahoo.co.in ',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('email', strtolower((string) $response->json('message')));
        $this->assertSame($before, Admin::whereIn('type', ['client', 'lead'])->count());
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->unsignedInteger('role')->nullable();
                $table->unsignedInteger('status')->nullable();
                $table->timestamps();
            });
        }

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
