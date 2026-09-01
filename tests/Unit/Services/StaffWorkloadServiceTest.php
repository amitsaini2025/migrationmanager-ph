<?php

namespace Tests\Unit\Services;

use App\Services\StaffWorkloadService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffWorkloadServiceTest extends TestCase
{
    private StaffWorkloadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' => 'Australia/Melbourne',
            'crm.workload.new_record_days' => 14,
            'crm.workload.returning_gap_days' => 365,
        ]);

        $this->createSchema();
        $this->service = new StaffWorkloadService;
    }

    #[Test]
    public function completed_excludes_call_but_call_completed_counts_call_actions(): void
    {
        $today = Carbon::parse('2026-09-01 10:00:00', 'Australia/Melbourne');
        Carbon::setTestNow($today);

        $this->insertStaff(1);
        $this->insertAdmin(10, 'client', $today->copy()->subYear());
        $this->insertAdmin(11, 'lead', $today->copy()->subYear());

        $this->insertActivity(1, 1, 10, 'completed action for Jane', 'Checklist', 1, $today);
        $this->insertActivity(2, 1, 11, 'completed action for Jane', 'Call', 1, $today);

        $summary = $this->service->getDashboardWorkload(1, $today);

        $this->assertSame(1, $summary['completed_excl_call']['total']);
        $this->assertSame(1, $summary['completed_excl_call']['clients']);
        $this->assertSame(1, $summary['call_completed']['total']);
        $this->assertSame(1, $summary['call_completed']['leads']);
    }

    #[Test]
    public function updated_actions_count_editor_activity_rows(): void
    {
        $today = Carbon::parse('2026-09-01 11:00:00', 'Australia/Melbourne');
        Carbon::setTestNow($today);

        $this->insertStaff(1);
        $this->insertAdmin(20, 'client', $today->copy()->subMonths(6));

        $this->insertActivity(3, 1, 20, 'Updated action for Bob', 'Call', 0, $today);

        $summary = $this->service->getDashboardWorkload(1, $today);

        $this->assertSame(1, $summary['updated']['total']);
        $this->assertSame(1, $summary['updated']['clients']);
    }

    #[Test]
    public function pending_counts_only_actions_assigned_to_staff(): void
    {
        $today = Carbon::parse('2026-09-01 09:00:00', 'Australia/Melbourne');
        Carbon::setTestNow($today);

        $this->insertStaff(1);
        $this->insertStaff(2);
        $this->insertAdmin(30, 'client', $today->copy()->subYear());

        $this->insertNote(1, 1, 30, 1, 'Call', '0', 1);
        $this->insertNote(2, 2, 30, 2, 'Checklist', '0', 1);

        $summary = $this->service->getDashboardWorkload(1, $today);

        $this->assertSame(1, $summary['pending']['total']);
        $this->assertSame(1, $summary['pending']['call']);
        $this->assertSame(0, $summary['pending']['other']);
    }

    #[Test]
    public function new_person_is_record_created_within_fourteen_days(): void
    {
        $today = Carbon::parse('2026-09-01 14:00:00', 'Australia/Melbourne');
        Carbon::setTestNow($today);

        $this->insertStaff(1);
        $this->insertAdmin(40, 'lead', $today->copy()->subDays(5));

        $this->insertNote(3, 1, 40, 0, 'Call', '0', 0, $today);

        $summary = $this->service->getDashboardWorkload(1, $today);

        $this->assertSame(1, $summary['contact_today']['call_notes']['total']);
        $this->assertSame(1, $summary['contact_today']['call_notes']['new']);
    }

    #[Test]
    public function returning_person_has_no_live_contact_in_over_one_year(): void
    {
        $today = Carbon::parse('2026-09-01 15:00:00', 'Australia/Melbourne');
        Carbon::setTestNow($today);

        $this->insertStaff(1);
        $this->insertAdmin(50, 'client', $today->copy()->subYears(2));

        $this->insertNote(4, 1, 50, 0, 'Call', '0', 0, $today->copy()->subYears(2));
        $this->insertNote(5, 1, 50, 0, 'Call', '0', 0, $today);

        $summary = $this->service->getDashboardWorkload(1, $today);

        $this->assertSame(1, $summary['contact_today']['call_notes']['total']);
        $this->assertSame(1, $summary['contact_today']['call_notes']['returning']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createSchema(): void
    {
        Schema::dropIfExists('activities_logs');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('staff');

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('client');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('client_id')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->default('client');
            $table->unsignedTinyInteger('is_action')->default(0);
            $table->string('status')->default('0');
            $table->string('task_group')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('action_date')->nullable();
            $table->dateTime('note_deadline')->nullable();
            $table->timestamps();
        });

        Schema::create('activities_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('task_group')->nullable();
            $table->unsignedTinyInteger('task_status')->default(0);
            $table->timestamps();
        });
    }

    private function insertStaff(int $id): void
    {
        DB::table('staff')->insert([
            'id' => $id,
            'first_name' => 'Staff',
            'last_name' => (string) $id,
            'email' => "staff{$id}@example.com",
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertAdmin(int $id, string $type, Carbon $createdAt): void
    {
        DB::table('admins')->insert([
            'id' => $id,
            'type' => $type,
            'first_name' => 'Person',
            'last_name' => (string) $id,
            'client_id' => 'C'.$id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertActivity(int $id, int $staffId, ?int $clientId, string $subject, ?string $taskGroup, int $taskStatus, Carbon $at): void
    {
        DB::table('activities_logs')->insert([
            'id' => $id,
            'client_id' => $clientId,
            'created_by' => $staffId,
            'subject' => $subject,
            'description' => 'Test',
            'task_group' => $taskGroup,
            'task_status' => $taskStatus,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function insertNote(
        int $id,
        int $staffId,
        ?int $clientId,
        int $assignedTo,
        string $taskGroup,
        string $status,
        int $isAction,
        ?Carbon $createdAt = null,
    ): void {
        $at = $createdAt ?? now();
        DB::table('notes')->insert([
            'id' => $id,
            'user_id' => $staffId,
            'client_id' => $clientId,
            'assigned_to' => $assignedTo ?: null,
            'type' => 'client',
            'is_action' => $isAction,
            'status' => $status,
            'task_group' => $taskGroup,
            'description' => 'Note',
            'action_date' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
