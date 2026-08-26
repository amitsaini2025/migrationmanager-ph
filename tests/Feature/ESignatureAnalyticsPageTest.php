<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminConsole\ESignatureController;
use App\Http\Middleware\TrackStaffCrmActivity;
use App\Models\Staff;
use App\Services\SignatureAnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ESignatureAnalyticsPageTest extends TestCase
{
    protected Staff $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([TrackStaffCrmActivity::class]);
        $this->createSchema();

        $this->admin = Staff::create([
            'first_name' => 'Esign',
            'last_name' => 'Admin',
            'email' => 'esign-admin@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    #[Test]
    public function esignature_named_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('adminconsole.features.esignature.index'));
        $this->assertTrue(Route::has('adminconsole.features.esignature.export'));
        $this->assertSame(url('/adminconsole/features/esignature'), route('adminconsole.features.esignature.index'));
    }

    #[Test]
    public function dashboard_view_only_references_registered_named_routes(): void
    {
        $blade = file_get_contents(resource_path('views/AdminConsole/features/esignature/index.blade.php'));
        $this->assertNotFalse($blade);

        preg_match_all("/route\(\s*['\"]([^'\"]+)['\"]/", $blade, $matches);
        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $name) {
            $this->assertTrue(Route::has($name), "E-signature dashboard references undefined route [{$name}].");
        }
    }

    #[Test]
    public function superadmin_index_loads_user_performance_once(): void
    {
        $service = $this->fakeAnalyticsService();
        $service->shouldReceive('getUserPerformance')->once()->andReturn(collect([
            [
                'user_id' => 1,
                'name' => 'Esign Admin',
                'email' => 'esign-admin@test.com',
                'total_sent' => 2,
                'signed' => 1,
                'pending' => 1,
                'completion_rate' => 50.0,
                'median_time_hours' => 4.0,
            ],
        ]));

        $this->instance(SignatureAnalyticsService::class, $service);
        $this->actingAs($this->admin, 'admin');

        $view = app(ESignatureController::class)->index(Request::create('/adminconsole/features/esignature', 'GET'));

        $this->assertInstanceOf(View::class, $view);
        $this->assertSame('AdminConsole.features.esignature.index', $view->name());
        $data = $view->getData();
        $this->assertArrayHasKey('userPerformance', $data);
        $this->assertArrayHasKey('documentTypeStats', $data);
        $this->assertArrayNotHasKey('activityByHour', $data);
        $this->assertSame(12.5, $data['medianHours']);
    }

    #[Test]
    public function non_superadmin_skips_user_performance_query(): void
    {
        $this->admin->role = 2;
        $this->admin->save();

        $service = $this->fakeAnalyticsService();
        $service->shouldNotReceive('getUserPerformance');

        $this->instance(SignatureAnalyticsService::class, $service);
        $this->actingAs($this->admin, 'admin');

        $view = app(ESignatureController::class)->index(Request::create('/adminconsole/features/esignature', 'GET'));

        $this->assertNull($view->getData()['userPerformance']);
    }

    private function fakeAnalyticsService(): SignatureAnalyticsService
    {
        $service = Mockery::mock(SignatureAnalyticsService::class);
        $service->shouldReceive('getMedianTimeToSign')->andReturn(12.5);
        $service->shouldReceive('getCompletionRate')->andReturn(80.0);
        $service->shouldReceive('getAverageReminders')->andReturn(1.2);
        $service->shouldReceive('getOverdueCount')->andReturn(0);
        $service->shouldReceive('getTopSigners')->andReturn(collect());
        $service->shouldReceive('getDocumentTypeStats')->andReturn(collect([
            (object) [
                'document_type' => 'general',
                'total' => 4,
                'signed' => 3,
                'pending' => 1,
                'completion_rate' => 75.0,
                'avg_time_hours' => 6.0,
            ],
        ]));
        $service->shouldReceive('getSignatureTrend')->andReturn([
            'labels' => ['2026-08-25'],
            'sent' => [1],
            'signed' => [1],
        ]);
        $service->shouldReceive('getOverdueAnalytics')->andReturn(collect());
        $service->shouldReceive('getActivityByHour')->never();

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
