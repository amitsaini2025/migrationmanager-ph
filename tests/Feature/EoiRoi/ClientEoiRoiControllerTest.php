<?php

namespace Tests\Feature\EoiRoi;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\ClientEoiReference;
use Illuminate\Foundation\Testing\WithFaker;

class ClientEoiRoiControllerTest extends TestCase
{
    use WithFaker;

    protected Admin $admin;
    protected Admin $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create an admin user
        $this->admin = Admin::factory()->create([
            'role' => 1, // Admin role
            'email' => 'admin@test.com',
        ]);

        // Create a client
        $this->client = Admin::factory()->create([
            'type' => 'client',
            'dob' => now()->subYears(30)->format('Y-m-d'),
        ]);
    }

    /** @test */
    public function it_can_list_eoi_records_for_a_client()
    {
        // Create some EOI records
        ClientEoiReference::factory()->count(3)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson("/clients/{$this->client->id}/eoi-roi");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'eoi_number',
                        'eoi_subclasses',
                        'eoi_states',
                        'formatted_subclasses',
                        'formatted_states',
                        'points',
                        'status',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_show_a_single_eoi_record()
    {
        $eoi = ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson("/clients/{$this->client->id}/eoi-roi/{$eoi->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'eoi_number',
                    'eoi_subclasses',
                    'eoi_states',
                ]
            ]);
    }

    /** @test */
    public function it_can_create_a_new_eoi_record()
    {
        $eoiData = [
            'eoi_number' => 'EOI123456',
            'eoi_subclasses' => ['189', '190'],
            'eoi_states' => ['VIC', 'NSW'],
            'eoi_occupation' => '261313',
            'eoi_points' => 85,
            'eoi_status' => 'draft',
            'eoi_submission_date' => '15/10/2024',
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", $eoiData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('client_eoi_references', [
            'client_id' => $this->client->id,
            'EOI_number' => 'EOI123456',
            'EOI_point' => 85,
        ]);
    }

    /** @test */
    public function it_can_update_an_existing_eoi_record()
    {
        $eoi = ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
            'EOI_number' => 'OLD123',
        ]);

        $updateData = [
            'id' => $eoi->id,
            'eoi_number' => 'NEW456',
            'eoi_subclasses' => ['491'],
            'eoi_states' => ['SA'],
            'eoi_points' => 95,
            'eoi_status' => 'submitted',
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('client_eoi_references', [
            'id' => $eoi->id,
            'EOI_number' => 'NEW456',
            'EOI_point' => 95,
        ]);
    }

    /** @test */
    public function it_can_delete_an_eoi_record()
    {
        $eoi = ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->deleteJson("/clients/{$this->client->id}/eoi-roi/{$eoi->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('client_eoi_references', [
            'id' => $eoi->id,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_eoi()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['eoi_number', 'eoi_subclasses', 'eoi_states']);
    }

    /** @test */
    public function it_validates_subclass_values()
    {
        $eoiData = [
            'eoi_number' => 'EOI123',
            'eoi_subclasses' => ['invalid', '999'], // Invalid subclasses
            'eoi_states' => ['VIC'],
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", $eoiData);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_state_values()
    {
        $eoiData = [
            'eoi_number' => 'EOI123',
            'eoi_subclasses' => ['189'],
            'eoi_states' => ['INVALID'], // Invalid state
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", $eoiData);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_can_calculate_points_for_a_client()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->getJson("/clients/{$this->client->id}/eoi-roi/calculate-points?subclass=190");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'breakdown' => [
                        'age',
                        'english',
                        'employment',
                        'education',
                        'bonuses',
                        'partner',
                        'nomination',
                    ],
                    'warnings',
                ]
            ]);
    }

    /** @test */
    public function it_requires_authentication_to_access_eoi_endpoints()
    {
        $response = $this->getJson("/clients/{$this->client->id}/eoi-roi");

        $response->assertStatus(401);
    }

    /** @test */
    public function it_syncs_scalar_fields_from_arrays()
    {
        $eoiData = [
            'eoi_number' => 'EOI789',
            'eoi_subclasses' => ['190', '491'], // 190 should become scalar value
            'eoi_states' => ['NSW', 'VIC'], // NSW should become scalar value
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", $eoiData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('client_eoi_references', [
            'EOI_number' => 'EOI789',
            'EOI_subclass' => '190', // First array value
            'EOI_state' => 'NSW', // First array value
        ]);
    }

    /** @test */
    public function it_prevents_accessing_other_clients_eoi_records()
    {
        $otherClient = Admin::factory()->create(['type' => 'client']);
        $eoi = ClientEoiReference::factory()->create([
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson("/clients/{$this->client->id}/eoi-roi/{$eoi->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_normalizes_dates_correctly()
    {
        $eoiData = [
            'eoi_number' => 'EOI-DATE-TEST',
            'eoi_subclasses' => ['189'],
            'eoi_states' => ['VIC'],
            'eoi_submission_date' => '25/12/2024', // dd/mm/yyyy format
        ];

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson("/clients/{$this->client->id}/eoi-roi", $eoiData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('client_eoi_references', [
            'EOI_number' => 'EOI-DATE-TEST',
            'EOI_submission_date' => '2024-12-25', // Normalized to Y-m-d
        ]);
    }
}

