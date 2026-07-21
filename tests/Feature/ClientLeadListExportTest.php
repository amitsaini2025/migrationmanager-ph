<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Lead;
use App\Models\Staff;
use App\Services\ClientLeadListExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientLeadListExportTest extends TestCase
{
    use RefreshDatabase;

    protected Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = Staff::create([
            'first_name' => 'Export',
            'last_name' => 'Tester',
            'email' => 'export-tester@test.com',
            'password' => Hash::make('password'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    public function test_client_csv_export_includes_filtered_client_details(): void
    {
        Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'EXP1001',
            'first_name' => 'Exportable',
            'last_name' => 'Client',
            'email' => 'exportable.client@test.com',
            'phone' => '0400000001',
            'status' => '1',
            'is_archived' => 0,
        ]);

        Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'OTH1002',
            'first_name' => 'Hidden',
            'last_name' => 'Client',
            'email' => 'hidden.client@test.com',
            'status' => '1',
            'is_archived' => 0,
        ]);

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route('clients.export-list', ['name' => 'Exportable']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Client ID', $content);
        $this->assertStringContainsString('EXP1001', $content);
        $this->assertStringContainsString('exportable.client@test.com', $content);
        $this->assertStringNotContainsString('OTH1002', $content);
    }

    public function test_lead_csv_export_includes_lead_details(): void
    {
        Lead::create([
            'type' => 'lead',
            'client_id' => 'LED2001',
            'first_name' => 'Lead',
            'last_name' => 'Export',
            'email' => 'lead.export@test.com',
            'phone' => '0400000002',
            'status' => '1',
            'lead_status' => 'new',
            'is_archived' => 0,
        ]);

        $response = $this->actingAs($this->staff, 'admin')
            ->get(route('leads.export-list'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('LED2001', $content);
        $this->assertStringContainsString('lead.export@test.com', $content);
    }

    public function test_export_service_builds_complete_row_for_client(): void
    {
        $client = Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'ROW3001',
            'first_name' => 'Row',
            'last_name' => 'Builder',
            'email' => 'row.builder@test.com',
            'status' => '1',
            'is_archived' => 0,
        ]);

        $service = app(ClientLeadListExportService::class);
        $row = $service->buildRow($client, 'client');

        $this->assertSame('ROW3001', $row[0]);
        $this->assertSame('Row', $row[1]);
        $this->assertSame('Builder', $row[2]);
        $this->assertSame('row.builder@test.com', $row[3]);
        $this->assertSame('Active', $row[7]);
    }
}
