<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Services\LeadAnalyticsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadAnalyticsServiceTest extends TestCase
{
    private LeadAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-25 12:00:00'));
        $this->createSchema();
        $this->service = new LeadAnalyticsService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_funnel_and_dashboard_counts_match_seeded_leads(): void
    {
        $this->insertStaff('Pat', 'Lead');

        $inRange = Carbon::parse('2026-08-10 10:00:00');
        $outOfRange = Carbon::parse('2026-06-01 10:00:00');

        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'new', 'status' => 1, 'user_id' => 1, 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'follow_up', 'status' => 1, 'user_id' => 1, 'followup_date' => '2026-08-30 09:00:00', 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'follow_up', 'status' => 1, 'user_id' => 1, 'followup_date' => '2026-08-01 09:00:00', 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'not_qualified', 'status' => 0, 'user_id' => 1, 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'hostile', 'status' => 1, 'user_id' => 1, 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'new', 'status' => 1, 'user_id' => 1, 'created_at' => $outOfRange]);
        $this->insertAdmin([
            'type' => 'client',
            'lead_status' => 'converted',
            'status' => 1,
            'user_id' => 1,
            'created_at' => $inRange,
            'updated_at' => Carbon::parse('2026-08-12 10:00:00'),
        ]);

        $start = Carbon::parse('2026-08-01')->startOfDay();
        $end = Carbon::parse('2026-08-25')->endOfDay();

        $funnel = $this->service->getConversionFunnel($start, $end);
        $stats = $this->service->getDashboardStats($start, $end);

        $this->assertSame(5, $funnel['total_leads']);
        $this->assertSame(1, $funnel['new']['count']);
        $this->assertSame(2, $funnel['follow_up']['count']);
        $this->assertSame(1, $funnel['not_qualified']['count']);
        $this->assertSame(1, $funnel['hostile']['count']);
        $this->assertSame(1, $funnel['converted']['count']);
        $this->assertSame(20.0, $funnel['new']['percentage']);

        $this->assertSame(5, $stats['total_leads']);
        $this->assertSame(1, $stats['converted']);
        $this->assertSame(4, $stats['active']);
        $this->assertSame(1, $stats['active_new']);
        $this->assertSame(2, $stats['active_follow_up']);
        $this->assertSame(2, $stats['pending_followups']);
        $this->assertSame(1, $stats['overdue_followups']);
        $this->assertSame(5, $stats['new_this_month']);
        $this->assertEqualsWithDelta(2.0, $stats['avg_conversion_time'], 0.05);
    }

    public function test_source_performance_uses_grouped_conversion_counts(): void
    {
        $inRange = Carbon::parse('2026-08-10 10:00:00');

        $this->insertAdmin(['type' => 'lead', 'source' => 'Website', 'lead_status' => 'new', 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'source' => 'Website', 'lead_status' => 'new', 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'lead', 'source' => null, 'lead_status' => 'new', 'created_at' => $inRange]);
        $this->insertAdmin(['type' => 'client', 'source' => 'Website', 'lead_status' => 'converted', 'created_at' => $inRange]);

        $performance = $this->service->getSourcePerformance(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-25')->endOfDay()
        );

        $this->assertSame('Website', $performance[0]['source']);
        $this->assertSame(2, $performance[0]['total_leads']);
        $this->assertSame(1, $performance[0]['converted']);
        $this->assertSame(50.0, $performance[0]['conversion_rate']);
        $this->assertSame('Unknown', $performance[1]['source']);
        $this->assertSame(1, $performance[1]['total_leads']);
        $this->assertSame(0, $performance[1]['converted']);
    }

    public function test_agent_performance_includes_zero_lead_staff_and_followups(): void
    {
        $ann = $this->insertStaff('Ann', 'Alpha');
        $bob = $this->insertStaff('Bob', 'Beta');
        $this->insertStaff('Inactive', 'User', 0);

        $inRange = Carbon::parse('2026-08-10 10:00:00');
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'new', 'user_id' => $ann->id, 'created_at' => $inRange]);
        $this->insertAdmin([
            'type' => 'lead',
            'lead_status' => 'follow_up',
            'user_id' => $ann->id,
            'followup_date' => '2026-08-01 09:00:00',
            'created_at' => $inRange,
        ]);
        $this->insertAdmin(['type' => 'client', 'lead_status' => 'converted', 'user_id' => $ann->id, 'created_at' => $inRange]);

        DB::table('notes')->insert([
            'assigned_to' => $ann->id,
            'task_group' => 'Follow Up',
            'status' => '1',
            'created_at' => $inRange,
            'updated_at' => $inRange,
        ]);
        DB::table('notes')->insert([
            'assigned_to' => $ann->id,
            'task_group' => 'Call',
            'status' => '1',
            'created_at' => $inRange,
            'updated_at' => $inRange,
        ]);

        $performance = $this->service->getAgentPerformance(
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-25')->endOfDay()
        );

        $this->assertCount(2, $performance);
        $this->assertSame($ann->id, $performance[0]['agent_id']);
        $this->assertSame('Ann Alpha', $performance[0]['agent_name']);
        $this->assertSame(2, $performance[0]['assigned_leads']);
        $this->assertSame(1, $performance[0]['converted_leads']);
        $this->assertSame(50.0, $performance[0]['conversion_rate']);
        $this->assertSame(1, $performance[0]['completed_followups']);
        $this->assertSame(1, $performance[0]['overdue_followups']);
        $this->assertSame(0, $performance[0]['avg_response_time_hours']);

        $this->assertSame($bob->id, $performance[1]['agent_id']);
        $this->assertSame(0, $performance[1]['assigned_leads']);
        $this->assertSame(0, $performance[1]['conversion_rate']);
    }

    public function test_trends_bucket_current_month_without_per_period_queries(): void
    {
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'new', 'created_at' => Carbon::parse('2026-08-10 10:00:00')]);
        $this->insertAdmin(['type' => 'lead', 'lead_status' => 'new', 'created_at' => Carbon::parse('2026-07-10 10:00:00')]);
        $this->insertAdmin(['type' => 'client', 'lead_status' => 'converted', 'created_at' => Carbon::parse('2026-08-12 10:00:00')]);

        $trends = $this->service->getLeadTrends('month', 2);

        $this->assertCount(2, $trends);
        $this->assertSame('Jul 2026', $trends[0]['period']);
        $this->assertSame(1, $trends[0]['new_leads']);
        $this->assertSame(0, $trends[0]['converted']);
        $this->assertSame('Aug 2026', $trends[1]['period']);
        $this->assertSame(1, $trends[1]['new_leads']);
        $this->assertSame(1, $trends[1]['converted']);
        $this->assertSame(100.0, $trends[1]['conversion_rate']);
    }

    public function test_empty_dataset_returns_zero_buckets(): void
    {
        $funnel = $this->service->getConversionFunnel();
        $stats = $this->service->getDashboardStats();

        $this->assertSame(0, $funnel['total_leads']);
        $this->assertSame(0.0, $funnel['converted']['percentage']);
        $this->assertSame(0, $stats['total_leads']);
        $this->assertSame(0, $stats['avg_conversion_time']);
        $this->assertSame([], $this->service->getSourcePerformance());
        $this->assertSame([], $this->service->getAgentPerformance());
        $this->assertSame([], $this->service->getLeadQualityDistribution());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertAdmin(array $attributes): void
    {
        $now = now();
        DB::table('admins')->insert(array_merge([
            'first_name' => 'Test',
            'last_name' => 'Lead',
            'email' => fake()->unique()->safeEmail(),
            'type' => 'lead',
            'status' => 1,
            'lead_status' => 'new',
            'source' => null,
            'user_id' => null,
            'followup_date' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
    }

    private function insertStaff(string $firstName, string $lastName, int $status = 1): Staff
    {
        return Staff::query()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $firstName.'-'.uniqid('', true).'@test.com',
            'password' => Hash::make('password'),
            'role' => 2,
            'status' => $status,
        ]);
    }

    private function createSchema(): void
    {
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

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('type')->nullable();
            $table->integer('status')->nullable();
            $table->string('lead_status')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('followup_date')->nullable();
            $table->timestamps();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_group')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
}
