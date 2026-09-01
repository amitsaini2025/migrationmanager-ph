<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardWorkloadMarkupTest extends TestCase
{
    public function test_dashboard_includes_workload_strip_instead_of_legacy_kpi_cards(): void
    {
        $blade = file_get_contents(resource_path('views/crm/dashboard-optimized.blade.php'));
        $this->assertNotFalse($blade);

        $this->assertStringContainsString('x-dashboard.workload-strip', $blade);
        $this->assertStringContainsString('workloadDrilldownModal', $blade);
        $this->assertStringNotContainsString('Active Matters', $blade);
        $this->assertStringNotContainsString('Urgent Notes Deadlines', $blade);
        $this->assertStringNotContainsString('x-dashboard.kpi-card', $blade);
    }
}
