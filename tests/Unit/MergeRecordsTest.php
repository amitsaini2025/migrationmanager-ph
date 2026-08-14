<?php

namespace Tests\Unit;

use App\Http\Middleware\TrackStaffCrmActivity;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MergeRecordsTest extends TestCase
{
    protected Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            TrackStaffCrmActivity::class,
        ]);

        $this->createMergeSchema();

        $this->staff = Staff::create([
            'first_name' => 'Merge',
            'last_name' => 'Tester',
            'email' => 'merge-tester@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    #[Test]
    public function merge_keeps_the_target_record_and_soft_deletes_the_source(): void
    {
        $source = Admin::factory()->create([
            'type' => 'lead',
            'first_name' => 'Source',
            'last_name' => 'Lead',
            'email' => 'source.merge@test.com',
        ]);
        $target = Admin::factory()->create([
            'type' => 'lead',
            'first_name' => 'Target',
            'last_name' => 'Lead',
            'email' => 'target.merge@test.com',
        ]);

        $this->staff->refresh();

        $response = $this->actingAs($this->staff, 'admin')
            ->post('/merge_records', [
                'merge_from' => $source->id,
                'merge_into' => $target->id,
            ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertTrue($payload['status'] ?? false, $payload['message'] ?? 'Merge failed');

        $this->assertDatabaseHas('admins', [
            'id' => $source->id,
            'is_deleted' => 1,
        ]);
        $this->assertDatabaseHas('admins', [
            'id' => $target->id,
            'is_deleted' => null,
            'email' => 'target.merge@test.com',
        ]);
    }

    #[Test]
    public function merge_moves_notes_onto_the_surviving_record(): void
    {
        $source = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'source.notes.merge@test.com',
        ]);
        $target = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'target.notes.merge@test.com',
        ]);

        $this->staff->refresh();

        $noteId = DB::table('notes')->insertGetId([
            'user_id' => $this->staff->id,
            'client_id' => $source->id,
            'title' => 'Source note',
            'description' => 'Moved during merge',
            'type' => 'client',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->staff, 'admin')
            ->post('/merge_records', [
                'merge_from' => $source->id,
                'merge_into' => $target->id,
            ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertTrue($payload['status'] ?? false, $payload['message'] ?? 'Merge failed');

        $this->assertDatabaseHas('notes', [
            'id' => $noteId,
            'client_id' => $target->id,
            'title' => 'Source note',
        ]);
        $this->assertDatabaseMissing('notes', [
            'id' => $noteId,
            'client_id' => $source->id,
        ]);
    }

    #[Test]
    public function merge_moves_related_emails_and_skips_duplicates_on_survivor(): void
    {
        $source = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'source.emails.merge@test.com',
        ]);
        $target = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'target.emails.merge@test.com',
        ]);

        $this->staff->refresh();

        DB::table('client_emails')->insert([
            [
                'client_id' => $source->id,
                'email' => 'shared.merge@test.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_id' => $source->id,
                'email' => 'only-source.merge@test.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'client_id' => $target->id,
                'email' => 'shared.merge@test.com',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($this->staff, 'admin')
            ->post('/merge_records', [
                'merge_from' => $source->id,
                'merge_into' => $target->id,
            ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertTrue($payload['status'] ?? false, $payload['message'] ?? 'Merge failed');

        $this->assertSame(1, DB::table('client_emails')->where('client_id', $target->id)->where('email', 'only-source.merge@test.com')->count());
        $this->assertSame(1, DB::table('client_emails')->where('client_id', $target->id)->where('email', 'shared.merge@test.com')->count());
        $this->assertSame(1, DB::table('client_emails')->where('client_id', $source->id)->where('email', 'shared.merge@test.com')->count());
    }

    #[Test]
    public function merge_moves_visa_matters_onto_the_surviving_record(): void
    {
        $source = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'source.matter.merge@test.com',
        ]);
        $target = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'target.matter.merge@test.com',
        ]);

        $this->staff->refresh();

        $matterRowId = DB::table('client_matters')->insertGetId([
            'client_id' => $source->id,
            'sel_matter_id' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->staff, 'admin')
            ->post('/merge_records', [
                'merge_from' => $source->id,
                'merge_into' => $target->id,
            ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertTrue($payload['status'] ?? false, $payload['message'] ?? 'Merge failed');

        $this->assertDatabaseHas('client_matters', [
            'id' => $matterRowId,
            'client_id' => $target->id,
            'sel_matter_id' => 99,
        ]);
        $this->assertDatabaseMissing('client_matters', [
            'id' => $matterRowId,
            'client_id' => $source->id,
        ]);
    }

    #[Test]
    public function merge_rejects_the_same_record_twice(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'same.merge@test.com',
        ]);

        $this->staff->refresh();

        $response = $this->actingAs($this->staff, 'admin')
            ->post('/merge_records', [
                'merge_from' => $lead->id,
                'merge_into' => $lead->id,
            ]);

        $response->assertOk();
        $payload = $response->json();
        $this->assertFalse($payload['status'] ?? true);
        $this->assertSame('Please select two different records to merge.', $payload['message'] ?? '');
        $this->assertDatabaseHas('admins', [
            'id' => $lead->id,
            'is_deleted' => null,
        ]);
    }

    #[Test]
    public function merge_search_finds_matching_lead_and_excludes_selected_record(): void
    {
        $selected = Admin::factory()->create([
            'type' => 'lead',
            'first_name' => 'Sidra',
            'last_name' => 'Test',
            'email' => 'sidra.search@test.com',
            'phone' => '0400111222',
            'client_id' => 'SIDR2614521',
            'is_archived' => 0,
        ]);
        $match = Admin::factory()->create([
            'type' => 'lead',
            'first_name' => 'Anil',
            'last_name' => 'Test',
            'email' => 'anil.search@test.com',
            'phone' => '0400333444',
            'client_id' => 'ANIL2614520',
            'is_archived' => 0,
        ]);

        $this->staff->refresh();

        $response = $this->actingAs($this->staff, 'admin')
            ->getJson('/merge_records/search?'.http_build_query([
                'q' => 'anil.search@test.com',
                'exclude_id' => $selected->id,
                'type' => 'lead',
            ]));

        $response->assertOk();
        $ids = collect($response->json('results'))->pluck('id')->all();
        $this->assertContains($match->id, $ids);
        $this->assertNotContains($selected->id, $ids);
        $matched = collect($response->json('results'))->firstWhere('id', $match->id);
        $this->assertSame('lead', $matched['type'] ?? null);
        $this->assertSame('Lead', $matched['type_label'] ?? null);
    }

    #[Test]
    public function merge_search_includes_matching_clients_with_type_label(): void
    {
        $selected = Admin::factory()->create([
            'type' => 'lead',
            'first_name' => 'Sidra',
            'email' => 'sidra.clientsearch@test.com',
            'client_id' => 'SIDR2614999',
            'is_archived' => 0,
        ]);
        $client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'Vipul',
            'last_name' => 'Kumar',
            'email' => 'vipul.clientsearch@test.com',
            'client_id' => 'VIPU2614063',
            'is_archived' => 0,
        ]);

        $this->staff->refresh();

        $response = $this->actingAs($this->staff, 'admin')
            ->getJson('/merge_records/search?'.http_build_query([
                'q' => 'vipul.clientsearch@test.com',
                'exclude_id' => $selected->id,
                'type' => 'lead',
            ]));

        $response->assertOk();
        $matched = collect($response->json('results'))->firstWhere('id', $client->id);
        $this->assertNotNull($matched);
        $this->assertSame('client', $matched['type'] ?? null);
        $this->assertSame('Client', $matched['type_label'] ?? null);
    }

    #[Test]
    public function merge_search_requires_at_least_two_characters(): void
    {
        $selected = Admin::factory()->create([
            'type' => 'lead',
            'email' => 'short.search@test.com',
            'is_archived' => 0,
        ]);

        $this->staff->refresh();

        $response = $this->actingAs($this->staff, 'admin')
            ->getJson('/merge_records/search?'.http_build_query([
                'q' => 'a',
                'exclude_id' => $selected->id,
                'type' => 'lead',
            ]));

        $response->assertStatus(422);
    }

    private function createMergeSchema(): void
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
                $table->string('client_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('admins', 'is_deleted')) {
                Schema::table('admins', function (Blueprint $table) {
                    $table->unsignedInteger('is_deleted')->nullable();
                });
            }
            if (! Schema::hasColumn('admins', 'is_archived')) {
                Schema::table('admins', function (Blueprint $table) {
                    $table->unsignedInteger('is_archived')->nullable();
                });
            }
        }

        if (! Schema::hasTable('client_emails')) {
            Schema::create('client_emails', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
                $table->unique(['client_id', 'email']);
            });
        }

        if (! Schema::hasTable('client_matters')) {
            Schema::create('client_matters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('sel_matter_id')->nullable();
                $table->timestamps();
            });
        }

        $this->createNullableClientTable('notes', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('mail_id')->nullable();
            $table->string('type')->nullable();
            $table->integer('pin')->nullable();
            $table->dateTime('action_date')->nullable();
            $table->integer('is_action')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('status')->nullable();
            $table->string('task_group')->nullable();
        });

        $this->createNullableClientTable('activities_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->string('use_for')->nullable();
            $table->dateTime('followup_date')->nullable();
            $table->string('task_group')->nullable();
            $table->integer('task_status')->nullable();
            $table->string('source')->nullable();
        });

        $this->createNullableClientTable('documents', function (Blueprint $table) {
            $table->string('document')->nullable();
            $table->string('filetype')->nullable();
            $table->string('myfile')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('file_size')->nullable();
            $table->string('type')->nullable();
            $table->string('doc_type')->nullable();
        });

        $this->createNullableClientTable('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('noe_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('timezone')->nullable();
            $table->date('date')->nullable();
            $table->string('time')->nullable();
            $table->string('timeslot_full')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('invites')->nullable();
            $table->text('appointment_details')->nullable();
            $table->string('status')->nullable();
            $table->string('assignee')->nullable();
            $table->string('priority')->nullable();
            $table->integer('priority_no')->nullable();
            $table->string('related_to')->nullable();
            $table->string('order_hash')->nullable();
        });

        $this->createNullableClientTable('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('total_fee')->nullable();
            $table->string('status')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('currency')->nullable();
            $table->integer('is_archive')->nullable();
        });

        $this->createNullableClientTable('email_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('from_mail')->nullable();
            $table->string('to_mail')->nullable();
            $table->text('cc')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('reciept_id')->nullable();
            $table->text('attachments')->nullable();
            $table->string('mail_type')->nullable();
        });

        $this->createNullableClientTable('checkin_logs', function (Blueprint $table) {
            $table->string('contact_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('visit_purpose')->nullable();
            $table->string('status')->nullable();
            $table->date('date')->nullable();
            $table->string('sesion_start')->nullable();
            $table->string('sesion_end')->nullable();
            $table->string('wait_time')->nullable();
            $table->string('attend_time')->nullable();
            $table->string('office')->nullable();
            $table->string('wait_type')->nullable();
        });
    }

    /**
     * @param  callable(Blueprint): void  $extraColumns
     */
    private function createNullableClientTable(string $table, callable $extraColumns): void
    {
        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $blueprint) use ($extraColumns) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('client_id')->nullable();
            $extraColumns($blueprint);
            $blueprint->timestamps();
        });
    }
}
