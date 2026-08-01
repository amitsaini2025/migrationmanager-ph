<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Signer;
class DocumentVisibilityTest extends TestCase
{

    /** @test */
    public function user_only_sees_documents_they_created()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);

        $myDocument = Document::factory()->create(['created_by' => $user->id]);
        $otherDocument = Document::factory()->create(['created_by' => $otherUser->id]);

        $visibleDocs = Document::visible($user)->get();

        $this->assertTrue($visibleDocs->contains($myDocument));
        $this->assertFalse($visibleDocs->contains($otherDocument));
    }

    /** @test */
    public function user_sees_documents_where_they_are_signer()
    {
        $user = Admin::factory()->create(['role' => 2, 'email' => 'test@example.com']);
        $otherUser = Admin::factory()->create(['role' => 2]);

        $document = Document::factory()->create(['created_by' => $otherUser->id]);
        Signer::factory()->create([
            'document_id' => $document->id,
            'email' => $user->email
        ]);

        $visibleDocs = Document::visible($user)->get();

        $this->assertTrue($visibleDocs->contains($document));
    }

    /** @test */
    public function user_sees_documents_associated_with_their_entities()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);

        // Document associated with user's lead
        $myLead = Lead::factory()->create(['user_id' => $user->id]);
        $myLeadDoc = Document::factory()->create([
            'created_by' => $otherUser->id,
            'lead_id' => $myLead->id,
            'client_id' => null
        ]);

        // Document associated with other user's lead
        $otherLead = Lead::factory()->create(['user_id' => $otherUser->id]);
        $otherLeadDoc = Document::factory()->create([
            'created_by' => $otherUser->id,
            'lead_id' => $otherLead->id,
            'client_id' => null
        ]);

        $visibleDocs = Document::visible($user)->get();

        $this->assertTrue($visibleDocs->contains($myLeadDoc));
        $this->assertFalse($visibleDocs->contains($otherLeadDoc));
    }

    /** @test */
    public function admin_sees_all_documents()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $user1 = Admin::factory()->create(['role' => 2]);
        $user2 = Admin::factory()->create(['role' => 2]);

        $doc1 = Document::factory()->create(['created_by' => $user1->id]);
        $doc2 = Document::factory()->create(['created_by' => $user2->id]);
        $doc3 = Document::factory()->create(['created_by' => $admin->id]);

        $visibleDocs = Document::visible($admin)->get();

        $this->assertCount(3, $visibleDocs);
        $this->assertTrue($visibleDocs->contains($doc1));
        $this->assertTrue($visibleDocs->contains($doc2));
        $this->assertTrue($visibleDocs->contains($doc3));
    }

    /** @test */
    public function dashboard_index_enforces_visibility()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);

        $myDocument = Document::factory()->create(['created_by' => $user->id]);
        $otherDocument = Document::factory()->create(['created_by' => $otherUser->id]);

        $response = $this->actingAs($user, 'admin')
            ->get(route('signatures.index'));

        $response->assertStatus(200);
        $response->assertSee($myDocument->file_name ?? 'Document');
        $response->assertDontSee($otherDocument->file_name ?? 'Document');
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

        $response = $this->actingAs($user, 'admin')
            ->get(route('signatures.show', $document->id));

        $response->assertStatus(403); // Forbidden
    }

    /** @test */
    public function user_can_view_associated_document()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);
        $lead = Lead::factory()->create(['user_id' => $user->id]);

        $document = Document::factory()->create([
            'created_by' => $otherUser->id,
            'lead_id' => $lead->id,
            'client_id' => null
        ]);

        $response = $this->actingAs($user, 'admin')
            ->get(route('signatures.show', $document->id));

        $response->assertStatus(200);
        $response->assertSee($document->display_title);
    }

    /** @test */
    public function admin_can_view_all_documents_with_organization_scope()
    {
        $admin = Admin::factory()->create(['role' => 1]);
        $user = Admin::factory()->create(['role' => 2]);

        $userDoc = Document::factory()->create(['created_by' => $user->id]);
        $adminDoc = Document::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('signatures.index', ['scope' => 'organization']));

        $response->assertStatus(200);
        $response->assertSee($userDoc->display_title);
        $response->assertSee($adminDoc->display_title);
    }

    /** @test */
    public function regular_user_cannot_access_organization_scope()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);

        $myDoc = Document::factory()->create(['created_by' => $user->id]);
        $otherDoc = Document::factory()->create(['created_by' => $otherUser->id]);

        $response = $this->actingAs($user, 'admin')
            ->get(route('signatures.index', ['scope' => 'organization']));

        $response->assertStatus(200);
        $response->assertSee($myDoc->display_title);
        $response->assertDontSee($otherDoc->display_title);
    }

    /** @test */
    public function associated_documents_inherit_entity_permissions()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $lead = Lead::factory()->create(['user_id' => $user->id]);
        
        // Another user creates a document and associates it with user's lead
        $otherUser = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'created_by' => $otherUser->id
        ]);

        // Associate after creation (like in real workflow)
        $document->update([
            'lead_id' => $lead->id,
            'client_id' => null
        ]);

        $visibleDocs = Document::visible($user)->get();

        $this->assertTrue($visibleDocs->contains($document), 
            'User should see document associated with their lead');
    }

    /** @test */
    public function visibility_scope_works_with_other_filters()
    {
        $user = Admin::factory()->create(['role' => 2]);

        $myDraft = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'draft'
        ]);
        $mySent = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'sent'
        ]);
        $mySigned = Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'signed'
        ]);

        $visibleSent = Document::visible($user)
            ->byStatus('sent')
            ->get();

        $this->assertCount(1, $visibleSent);
        $this->assertTrue($visibleSent->contains($mySent));
        $this->assertFalse($visibleSent->contains($myDraft));
        $this->assertFalse($visibleSent->contains($mySigned));
    }

    /** @test */
    public function visibility_badge_shows_correct_type_for_owner()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user, 'admin');

        $badge = $document->visibility_badge;

        $this->assertEquals('owner', $document->visibility_type);
        $this->assertEquals('🔒', $badge['icon']);
        $this->assertEquals('My Document', $badge['label']);
    }

    /** @test */
    public function visibility_badge_shows_correct_type_for_signer()
    {
        $user = Admin::factory()->create(['role' => 2, 'email' => 'signer@test.com']);
        $otherUser = Admin::factory()->create(['role' => 2]);
        
        $document = Document::factory()->create(['created_by' => $otherUser->id]);
        Signer::factory()->create([
            'document_id' => $document->id,
            'email' => $user->email
        ]);

        $this->actingAs($user, 'admin');

        $badge = $document->visibility_badge;

        $this->assertEquals('signer', $document->visibility_type);
        $this->assertEquals('✍️', $badge['icon']);
        $this->assertEquals('Need to Sign', $badge['label']);
    }

    /** @test */
    public function visibility_badge_shows_correct_type_for_associated()
    {
        $user = Admin::factory()->create(['role' => 2]);
        $otherUser = Admin::factory()->create(['role' => 2]);
        $lead = Lead::factory()->create(['user_id' => $user->id]);

        $document = Document::factory()->create([
            'created_by' => $otherUser->id,
            'lead_id' => $lead->id,
            'client_id' => null
        ]);

        $this->actingAs($user, 'admin');

        $badge = $document->visibility_badge;

        $this->assertEquals('associated', $document->visibility_type);
        $this->assertEquals('🔗', $badge['icon']);
        $this->assertEquals('Associated', $badge['label']);
    }
}

