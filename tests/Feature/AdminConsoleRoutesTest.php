<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Admin;
use App\Models\UserRole;

class AdminConsoleRoutesTest extends TestCase
{
    use WithFaker;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test admin user
        $this->admin = Admin::factory()->create([
            'role' => 1, // Super admin
            'email' => 'admin@test.com',
            'password' => bcrypt('password')
        ]);
    }

    /** @test */
    public function admin_can_access_adminconsole_features_matter_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/matter')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_features_matter_create()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/matter/create')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_features_tags_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/tags')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_features_workflow_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/workflow')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_features_emails_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/emails')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_system_users_index()
    {
        // /users redirects to clientlist
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/users')
             ->assertRedirect(route('adminconsole.system.clients.clientlist'));
    }

    /** @test */
    public function admin_can_access_adminconsole_system_clients_clientlist()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/clients')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_system_roles_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/roles')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_system_teams_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/teams')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_system_offices_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/offices')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_system_settings_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/system/settings')
             ->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_adminconsole_database_anzsco_index()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/database/anzsco')
             ->assertStatus(200);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_adminconsole_routes()
    {
        $this->get('/adminconsole/features/matter')
             ->assertRedirect('/login');
    }

    /** @test */
    public function non_admin_user_cannot_access_adminconsole_routes()
    {
        $user = Admin::factory()->create([
            'type' => 'client',
            'email' => 'client@test.com',
            'password' => bcrypt('password')
        ]);

        $this->actingAs($user, 'admin')
             ->get('/adminconsole/features/matter')
             ->assertStatus(403);
    }

    /** @test */
    public function adminconsole_routes_work_correctly()
    {
        $this->actingAs($this->admin, 'admin')
             ->get('/adminconsole/features/matter')
             ->assertStatus(200);
    }

    /** @test */
    public function adminconsole_routes_have_correct_names()
    {
        $this->actingAs($this->admin, 'admin');
        
        // Test that route names are correctly defined
        $this->assertEquals(
            url('/adminconsole/features/matter'),
            route('adminconsole.features.matter.index')
        );
        
        $this->assertEquals(
            url('/adminconsole/system/users'),
            route('adminconsole.system.users.index')
        );
        $this->assertEquals(
            url('/adminconsole/system/clients'),
            route('adminconsole.system.clients.clientlist')
        );
        
        $this->assertEquals(
            url('/adminconsole/database/anzsco'),
            route('adminconsole.database.anzsco.index')
        );
    }

    /** @test */
    public function adminconsole_routes_use_correct_middleware()
    {
        // Test that routes are protected by auth and admin middleware
        $this->get('/adminconsole/features/matter')
             ->assertRedirect('/login');
    }

    /** @test */
    public function adminconsole_navigation_links_work_correctly()
    {
        $this->actingAs($this->admin, 'admin');
        
        // Test that navigation links point to correct routes
        $response = $this->get('/adminconsole/features/matter');
        $response->assertSee('adminconsole.features.matter.index');
        
        $response = $this->get('/adminconsole/system/clients');
        $response->assertSee('adminconsole.system.clients.clientlist');
    }

    /** @test */
    public function adminconsole_forms_submit_to_correct_routes()
    {
        $this->actingAs($this->admin, 'admin');
        
        // Test matter creation form
        $response = $this->get('/adminconsole/features/matter/create');
        $response->assertSee('adminconsole.features.matter.store');
        
        // Test client creation form
        $response = $this->get('/adminconsole/system/clients/create');
        $response->assertSee('adminconsole.system.clients.storeclient');
    }

    /** @test */
    public function adminconsole_back_links_work_correctly()
    {
        $this->actingAs($this->admin, 'admin');
        
        // Test that back links in create/edit forms point to correct index routes
        $response = $this->get('/adminconsole/features/matter/create');
        $response->assertSee('adminconsole.features.matter.index');
        
        $response = $this->get('/adminconsole/system/clients/create');
        $response->assertSee('adminconsole.system.clients.clientlist');
    }
}
