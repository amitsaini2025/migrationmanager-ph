<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OfficeVisitRoutesTest extends TestCase
{
    public function test_dead_office_visits_create_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('officevisits.create'));
        $this->assertNull(collect(Route::getRoutes())->first(function ($route) {
            return in_array('GET', $route->methods(), true)
                && $route->uri() === 'office-visits/create';
        }));
    }

    public function test_live_office_visit_and_front_desk_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('officevisits.index'));
        $this->assertTrue(Route::has('officevisits.waiting'));
        $this->assertTrue(Route::has('officevisits.attending'));
        $this->assertTrue(Route::has('officevisits.completed'));
        $this->assertTrue(Route::has('front-desk.checkin.index'));
        $this->assertTrue(Route::has('front-desk.checkin.lookup'));
        $this->assertTrue(Route::has('front-desk.checkin.appointments'));
        $this->assertTrue(Route::has('front-desk.checkin.submit'));
        $this->assertTrue(Route::has('front-desk.checkin.create-lead'));

        $this->assertSame(url('/office-visits/waiting'), route('officevisits.waiting'));
        $this->assertSame(url('/office-visits/attending'), route('officevisits.attending'));
        $this->assertSame(url('/office-visits/completed'), route('officevisits.completed'));
        $this->assertSame(url('/front-desk/checkin'), route('front-desk.checkin.index'));
    }

    public function test_office_visit_views_do_not_reference_the_removed_create_route(): void
    {
        $paths = [
            resource_path('views/crm/officevisits/index.blade.php'),
            resource_path('views/crm/front_desk/checkin.blade.php'),
            resource_path('views/Elements/CRM/header_client_detail.blade.php'),
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents, "Failed to read [{$path}].");
            $this->assertStringNotContainsString('officevisits.create', $contents);
            $this->assertStringNotContainsString('/office-visits/create', $contents);
        }
    }

    public function test_office_visit_index_view_only_references_registered_named_routes(): void
    {
        $blade = file_get_contents(resource_path('views/crm/officevisits/index.blade.php'));
        $this->assertNotFalse($blade);

        preg_match_all("/route\(\s*['\"]([^'\"]+)['\"]/", $blade, $matches);

        $this->assertNotEmpty($matches[1], 'Office visit index should generate URLs with route().');

        foreach ($matches[1] as $name) {
            $this->assertTrue(
                Route::has($name),
                "Office visit index references undefined route [{$name}]."
            );
        }
    }
}
