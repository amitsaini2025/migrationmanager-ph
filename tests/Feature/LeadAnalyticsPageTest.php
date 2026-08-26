<?php

namespace Tests\Feature;

use App\Http\Controllers\CRM\Leads\LeadAnalyticsController;
use App\Http\Middleware\TrackStaffCrmActivity;
use App\Models\Staff;
use App\Services\LeadAnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Mockery;
use Tests\TestCase;

class LeadAnalyticsPageTest extends TestCase
{
    protected Staff $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([TrackStaffCrmActivity::class]);

        $this->createSchema();

        $this->admin = Staff::create([
            'first_name' => 'Analytics',
            'last_name' => 'Admin',
            'email' => 'lead-analytics-admin@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    public function test_lead_analytics_named_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('leads.analytics.index'));
        $this->assertTrue(Route::has('leads.analytics.export'));
        $this->assertTrue(Route::has('leads.analytics.trends'));
        $this->assertTrue(Route::has('leads.analytics.compare'));

        $this->assertSame(url('/leads/analytics'), route('leads.analytics.index'));
        $this->assertSame(url('/leads/analytics/export'), route('leads.analytics.export'));
    }

    public function test_dashboard_view_only_references_registered_named_routes(): void
    {
        $blade = file_get_contents(resource_path('views/crm/leads/analytics/dashboard.blade.php'));
        $this->assertNotFalse($blade);

        preg_match_all("/route\(\s*['\"]([^'\"]+)['\"]/", $blade, $matches);

        $this->assertNotEmpty($matches[1], 'Dashboard view should generate URLs with route().');

        foreach ($matches[1] as $name) {
            $this->assertTrue(
                Route::has($name),
                "Lead analytics dashboard references undefined route [{$name}]."
            );
            route($name);
        }
    }

    public function test_admin_index_returns_dashboard_view_without_querying_live_data(): void
    {
        $this->instance(LeadAnalyticsService::class, $this->fakeAnalyticsService());

        $this->actingAs($this->admin, 'admin');

        $view = app(LeadAnalyticsController::class)->index(Request::create('/leads/analytics', 'GET'));

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('crm.leads.analytics.dashboard', $view->name());
        $this->assertArrayHasKey('dashboardStats', $view->getData());
        $this->assertArrayHasKey('conversionFunnel', $view->getData());
        $this->assertArrayHasKey('sourcePerformance', $view->getData());
        $this->assertArrayHasKey('agentPerformance', $view->getData());
        $this->assertArrayHasKey('leadQuality', $view->getData());
        $this->assertArrayHasKey('startDate', $view->getData());
        $this->assertArrayHasKey('endDate', $view->getData());
    }

    public function test_non_admin_is_redirected_away_from_analytics(): void
    {
        $staff = Staff::create([
            'first_name' => 'Regular',
            'last_name' => 'Staff',
            'email' => 'lead-analytics-staff@test.com',
            'password' => Hash::make('password'),
            'role' => 5,
            'status' => 1,
        ]);

        $response = $this->from('/leads')
            ->actingAs($staff, 'admin')
            ->get(route('leads.analytics.index'));

        $response->assertRedirect('/leads');
        $response->assertSessionHas('error');
    }

    public function test_admin_can_export_analytics_json(): void
    {
        $this->instance(LeadAnalyticsService::class, $this->fakeAnalyticsService());
        $this->actingAs($this->admin, 'admin');

        $response = app(LeadAnalyticsController::class)->export(Request::create('/leads/analytics/export', 'GET'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertArrayHasKey('dashboard_stats', $payload);
        $this->assertArrayHasKey('conversion_funnel', $payload);
        $this->assertArrayHasKey('source_performance', $payload);
        $this->assertArrayHasKey('agent_performance', $payload);
        $this->assertArrayHasKey('lead_quality', $payload);
    }

    public function test_admin_can_fetch_trends_json(): void
    {
        $this->instance(LeadAnalyticsService::class, $this->fakeAnalyticsService());
        $this->actingAs($this->admin, 'admin');

        $response = app(LeadAnalyticsController::class)->getTrends(Request::create('/leads/analytics/trends', 'GET'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $response->getData(true));
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardStats(): array
    {
        return [
            'total_leads' => 0,
            'new_this_month' => 0,
            'converted' => 0,
            'active' => 0,
            'active_new' => 0,
            'active_follow_up' => 0,
            'pending_followups' => 0,
            'overdue_followups' => 0,
            'avg_conversion_time' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyConversionFunnel(): array
    {
        return [
            'total_leads' => 0,
            'new' => ['count' => 0, 'percentage' => 0],
            'follow_up' => ['count' => 0, 'percentage' => 0],
            'not_qualified' => ['count' => 0, 'percentage' => 0],
            'hostile' => ['count' => 0, 'percentage' => 0],
            'converted' => ['count' => 0, 'percentage' => 0],
        ];
    }

    private function fakeAnalyticsService(): LeadAnalyticsService
    {
        $service = Mockery::mock(LeadAnalyticsService::class);
        $service->shouldReceive('getDashboardStats')->andReturn($this->emptyDashboardStats());
        $service->shouldReceive('getConversionFunnel')->andReturn($this->emptyConversionFunnel());
        $service->shouldReceive('getSourcePerformance')->andReturn([]);
        $service->shouldReceive('getAgentPerformance')->andReturn([]);
        $service->shouldReceive('getLeadQualityDistribution')->andReturn([]);
        $service->shouldReceive('getLeadTrends')->andReturn([]);

        return $service;
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
    }
}
