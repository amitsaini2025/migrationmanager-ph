<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Signer;
use App\Policies\DocumentPolicy;
class DocumentPolicyTest extends TestCase
{

    protected $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentPolicy();
    }

    /** @test */
    public function admin_can_view_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]); // Super admin
        $document = Document::factory()->create();

        $this->assertTrue($this->policy->view($admin, $document));
    }

    /** @test */
    public function creator_can_view_their_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    /** @test */
    public function signer_can_view_document_they_need_to_sign()
    {
        $user = Admin::factory()->create(['role' => 2, 'email' => 'signer@example.com']);
        $document = Document::factory()->create();
        
        Signer::factory()->create([
            'document_id' => $document->id,
            'email' => $user->email
        ]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    /** @test */
    public function user_can_view_document_associated_with_their_client()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'client_id' => $user->id,
            'lead_id' => null
        ]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    /** @test */
    public function user_can_view_document_associated_with_their_lead()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $lead = Lead::factory()->create(['user_id' => $user->id]);
        $document = Document::factory()->create([
            'lead_id' => $lead->id,
            'client_id' => null
        ]);

        $this->assertTrue($this->policy->view($user, $document));
    }

    /** @test */
    public function user_cannot_view_others_private_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $otherUser->id,
            'client_id' => null,
            'lead_id' => null
        ]);

        $this->assertFalse($this->policy->view($user, $document));
    }

    /** @test */
    public function admin_can_update_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $document = Document::factory()->create();

        $this->assertTrue($this->policy->update($admin, $document));
    }

    /** @test */
    public function creator_can_update_their_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->update($user, $document));
    }

    /** @test */
    public function user_cannot_update_others_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $otherUser->id]);

        $this->assertFalse($this->policy->update($user, $document));
    }

    /** @test */
    public function admin_can_delete_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $document = Document::factory()->create(['status' => 'draft']);

        $this->assertTrue($this->policy->delete($admin, $document));
    }

    /** @test */
    public function creator_can_delete_their_draft_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'draft'
        ]);

        $this->assertTrue($this->policy->delete($user, $document));
    }

    /** @test */
    public function creator_cannot_delete_their_signed_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'signed'
        ]);

        $this->assertFalse($this->policy->delete($user, $document));
    }

    /** @test */
    public function user_cannot_delete_others_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $otherUser->id,
            'status' => 'draft'
        ]);

        $this->assertFalse($this->policy->delete($user, $document));
    }

    /** @test */
    public function only_admin_can_view_all_documents()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $user = Admin::factory()->create(['role' => 2]);

        $this->assertTrue($this->policy->viewAll($admin));
        $this->assertFalse($this->policy->viewAll($user));
    }

    /** @test */
    public function staff_members_can_create_documents()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $staff = Admin::factory()->create(['role' => 2]);
        $client = Admin::factory()->create(['type' => 'client']); // Client portal user

        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->create($staff));
        $this->assertFalse($this->policy->create($client));
    }

    /** @test */
    public function admin_can_send_reminder_for_any_document()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $document = Document::factory()->create();

        $this->assertTrue($this->policy->sendReminder($admin, $document));
    }

    /** @test */
    public function creator_can_send_reminder_for_their_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->sendReminder($user, $document));
    }

    /** @test */
    public function user_cannot_send_reminder_for_others_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $otherUser->id]);

        $this->assertFalse($this->policy->sendReminder($user, $document));
    }
}

