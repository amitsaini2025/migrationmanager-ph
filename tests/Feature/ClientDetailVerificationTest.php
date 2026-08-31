<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackStaffCrmActivity;
use App\Http\Middleware\VerifyCsrfToken;
use App\Mail\ClientDetailVerificationMail;
use App\Models\Admin;
use App\Models\ClientDetailVerification;
use App\Models\ClientDetailVerificationField;
use App\Models\Staff;
use App\Services\ClientDetailVerificationService;
use App\Support\ClientDetailVerificationFields;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDetailVerificationTest extends TestCase
{
    protected Staff $staff;

    protected Admin $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            TrackStaffCrmActivity::class,
        ]);

        $this->createSchema();

        $this->staff = Staff::query()->create([
            'first_name' => 'Super1',
            'last_name' => 'Admin1',
            'email' => 'verify-link-staff@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);

        $this->client = Admin::query()->create([
            'type' => 'client',
            'first_name' => 'Vipul',
            'last_name' => 'Kumar',
            'email' => 'vipul.primary@example.com',
            'phone' => '0412345678',
            'country_code' => '+61',
            'gender' => 'Other',
            'marital_status' => 'Married',
            'is_company' => 0,
            'is_deleted' => null,
        ]);
    }

    #[Test]
    public function sending_a_new_link_invalidates_an_unused_previous_link(): void
    {
        Mail::fake();
        $service = app(ClientDetailVerificationService::class);

        $first = $this->createOpenVerification('old-token-old-token-old-token-old-token-old-tok');
        $this->assertTrue($first->fresh()->isUsable());

        $result = $service->sendLink($this->client, $this->staff->id);

        $this->assertTrue($result['success']);
        $this->assertNotNull($first->fresh()->invalidated_at);
        $this->assertFalse($first->fresh()->isUsable());
        Mail::assertSent(ClientDetailVerificationMail::class, function (ClientDetailVerificationMail $mail): bool {
            return $mail->hasTo('vipul.primary@example.com') && $mail->firstName === 'Vipul';
        });
    }

    #[Test]
    public function public_form_shows_snapshot_and_submit_expires_the_token(): void
    {
        $token = 'usabletokenusabletokenusabletokenusabletokenusabletokenusable12';
        $verification = $this->createOpenVerification($token, [
            'full_name' => 'Vipul Kumar',
            'dob' => 'N/A',
            'gender' => 'Other',
            'marital_status' => 'Married',
            'email' => 'vipul.primary@example.com',
            'phone' => '0412345678',
            'address' => 'N/A',
            'visa_type' => 'N/A',
            'visa_expiry' => 'N/A',
            'passport_country' => 'N/A',
            'location_status' => 'N/A',
        ]);

        $this->get(route('public.client-detail-verification.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Vipul Kumar')
            ->assertSee('vipul.primary@example.com')
            ->assertDontSee('secondary@example.com');

        $payload = $this->confirmedPayload();
        $payload[4]['status'] = ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED;
        $payload[4]['requested_value'] = 'vipul.new@example.com';

        $this->post(route('public.client-detail-verification.submit', ['token' => $token]), [
            'declaration' => '1',
            'fields_json' => json_encode($payload),
        ])->assertOk()->assertSee('Verification Submitted');

        $this->assertFalse($verification->fresh()->isUsable());
        $this->assertNotNull($verification->fresh()->used_at);

        $this->get(route('public.client-detail-verification.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('This link is no longer valid');

        $emailField = ClientDetailVerificationField::query()
            ->where('verification_id', $verification->id)
            ->where('field_key', 'email')
            ->first();

        $this->assertNotNull($emailField);
        $this->assertSame(ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED, $emailField->status);
        $this->assertSame('vipul.new@example.com', $emailField->requested_value);
        $this->assertSame('vipul.primary@example.com', $this->client->fresh()->email);
    }

    #[Test]
    public function team_accept_finalizes_the_requested_primary_email(): void
    {
        $this->actingAs($this->staff, 'admin');

        $field = ClientDetailVerificationField::query()->create([
            'verification_id' => $this->createOpenVerification('accepttokenaccepttokenaccepttokenaccepttokenaccepttokenacce12')->id,
            'client_id' => $this->client->id,
            'field_key' => 'email',
            'original_value' => 'vipul.primary@example.com',
            'requested_value' => 'vipul.final@example.com',
            'status' => ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED,
        ]);

        $response = $this->post(route('clients.detail-verification.accept', ['field' => $field->id]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'display_value' => 'vipul.final@example.com',
                'field_key' => 'email',
            ]);
        $this->assertStringContainsString('title="Confirmed"', (string) $response->json('confirmed_icon'));

        $this->assertSame('vipul.final@example.com', $this->client->fresh()->email);
        $this->assertSame(ClientDetailVerificationFields::STATUS_ACCEPTED, $field->fresh()->status);
    }

    /**
     * @param  array<string, string>|null  $snapshot
     */
    private function createOpenVerification(string $token, ?array $snapshot = null): ClientDetailVerification
    {
        return ClientDetailVerification::query()->create([
            'client_id' => $this->client->id,
            'token_hash' => ClientDetailVerification::hashToken($token),
            'sent_to_email' => $this->client->email,
            'sent_by' => $this->staff->id,
            'snapshot' => $snapshot ?? ClientDetailVerificationFields::buildSnapshot([
                'first_name' => $this->client->first_name,
                'last_name' => $this->client->last_name,
                'primary_email' => $this->client->email,
                'primary_phone' => $this->client->phone,
                'gender' => $this->client->gender,
                'marital_status' => $this->client->marital_status,
            ]),
        ]);
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function confirmedPayload(): array
    {
        return array_map(static fn (string $key): array => [
            'key' => $key,
            'status' => ClientDetailVerificationFields::STATUS_CONFIRMED,
            'requested_value' => null,
            'current_value' => 'N/A',
        ], ClientDetailVerificationFields::keys());
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
                $table->string('type')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('country_code')->nullable();
                $table->string('gender')->nullable();
                $table->string('marital_status')->nullable();
                $table->string('dob')->nullable();
                $table->unsignedInteger('is_company')->nullable();
                $table->unsignedInteger('is_deleted')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->string('client_id')->nullable();
                $table->timestamps();
            });
        } else {
            foreach (['gender', 'marital_status', 'dob', 'phone', 'country_code'] as $column) {
                if (! Schema::hasColumn('admins', $column)) {
                    Schema::table('admins', function (Blueprint $table) use ($column) {
                        $table->string($column)->nullable();
                    });
                }
            }
        }

        if (! Schema::hasTable('client_detail_verifications')) {
            Schema::create('client_detail_verifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('client_id')->index();
                $table->string('token_hash', 64)->unique();
                $table->string('sent_to_email', 255);
                $table->unsignedInteger('sent_by')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamp('used_at')->nullable();
                $table->timestamp('invalidated_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_detail_verification_fields')) {
            Schema::create('client_detail_verification_fields', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('verification_id')->index();
                $table->unsignedInteger('client_id')->index();
                $table->string('field_key', 64);
                $table->text('original_value')->nullable();
                $table->text('requested_value')->nullable();
                $table->string('status', 32)->default('confirmed');
                $table->text('note')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->unsignedInteger('accepted_by')->nullable();
                $table->timestamps();
            });
        }
    }
}
