<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardAddTaskMarkupTest extends TestCase
{
    public function test_add_task_views_do_not_query_all_clients_or_n_plus_one_branches(): void
    {
        $popover = file_get_contents(resource_path('views/components/crm/add-my-task-popover-template.blade.php'));
        $modals = file_get_contents(resource_path('views/components/dashboard/modals.blade.php'));

        $this->assertNotFalse($popover);
        $this->assertNotFalse($modals);

        $this->assertStringContainsString('AssigneeDropdownStaff::activeWithOffice()', $popover);
        $this->assertStringContainsString('AssigneeDropdownStaff::activeWithOffice()', $modals);

        $this->assertStringNotContainsString("Staff::where('status',1)", $popover);
        $this->assertStringNotContainsString("Staff::where('status',1)", $modals);
        $this->assertStringNotContainsString('Branch::where(', $popover);
        $this->assertStringNotContainsString('Branch::where(', $modals);
        $this->assertStringNotContainsString("whereIn('type', ['client', 'lead'])", $modals);
        $this->assertStringContainsString('js-data-example-ajaxccsearch__dashboardtask', $modals);
        $this->assertStringContainsString('js-data-example-ajaxccsearch__addmytask', $popover);
    }

    public function test_dashboard_page_initializes_ajax_client_search_for_create_task_modal(): void
    {
        $blade = file_get_contents(resource_path('views/crm/dashboard-optimized.blade.php'));
        $this->assertNotFalse($blade);

        $this->assertStringContainsString("shown.bs.modal', '#create_task_modal'", $blade);
        $this->assertStringContainsString('dashboard_client_select', $blade);
        $this->assertStringContainsString('/clients/get-allclients', $blade);
    }
}
