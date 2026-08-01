<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Document;
use App\Models\Signer;
use App\Models\Admin;
use App\Models\Lead;
use App\Models\Email;
use App\Services\SignatureService;
use App\Services\EmailConfigService;
use App\Services\SystemEmailLogService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SignatureServiceTest extends TestCase
{

    protected SignatureService $signatureService;
    protected EmailConfigService $emailConfigService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->emailConfigService = new EmailConfigService();
        $this->signatureService = new SignatureService(
            $this->emailConfigService,
            app(SystemEmailLogService::class)
        );
        
        Storage::fake('s3');
        Mail::fake();
    }

    /** @test */
    public function it_can_send_document_for_signature()
    {
        // Create a test document
        $document = Document::factory()->create([
            'status' => 'draft',
            'title' => 'Test Agreement'
        ]);

        $signers = [
            ['email' => 'john@example.com', 'name' => 'John Doe'],
            ['email' => 'jane@example.com', 'name' => 'Jane Smith']
        ];

        $options = [
            'subject' => 'Please sign this document',
            'message' => 'Test message'
        ];

        // Send document for signature
        $result = $this->signatureService->send($document, $signers, $options);

        // Assert success
        $this->assertTrue($result);

        // Assert document status updated
        $this->assertEquals('sent', $document->fresh()->status);
        $this->assertEquals(2, $document->fresh()->signer_count);
        $this->assertEquals('john@example.com', $document->fresh()->primary_signer_email);

        // Assert signers created
        $this->assertCount(2, $document->signers);
        
        $firstSigner = $document->signers->first();
        $this->assertEquals('john@example.com', $firstSigner->email);
        $this->assertEquals('John Doe', $firstSigner->name);
        $this->assertEquals('pending', $firstSigner->status);
        $this->assertNotNull($firstSigner->token);
        $this->assertEquals(64, strlen($firstSigner->token));

        // Assert emails were queued
        Mail::assertSent(\Illuminate\Mail\Mailable::class, 2);
    }

    /** @test */
    public function it_can_send_reminder_to_signer()
    {
        $document = Document::factory()->create(['status' => 'sent']);
        $signer = Signer::factory()->create([
            'document_id' => $document->id,
            'status' => 'pending',
            'reminder_count' => 0,
            'last_reminder_sent_at' => null
        ]);

        $result = $this->signatureService->remind($signer);

        $this->assertTrue($result);
        
        $signer->refresh();
        $this->assertEquals(1, $signer->reminder_count);
        $this->assertNotNull($signer->last_reminder_sent_at);
    }

    /** @test */
    public function it_prevents_too_many_reminders()
    {
        $document = Document::factory()->create(['status' => 'sent']);
        $signer = Signer::factory()->create([
            'document_id' => $document->id,
            'status' => 'pending',
            'reminder_count' => 3,
            'last_reminder_sent_at' => now()
        ]);

        $result = $this->signatureService->remind($signer);

        $this->assertFalse($result);
        $this->assertEquals(3, $signer->fresh()->reminder_count);
    }

    /** @test */
    public function it_enforces_24_hour_cooldown_between_reminders()
    {
        $document = Document::factory()->create(['status' => 'sent']);
        $signer = Signer::factory()->create([
            'document_id' => $document->id,
            'status' => 'pending',
            'reminder_count' => 1,
            'last_reminder_sent_at' => now()->subHours(12) // Only 12 hours ago
        ]);

        $result = $this->signatureService->remind($signer);

        $this->assertFalse($result);
        $this->assertEquals(1, $signer->fresh()->reminder_count);
    }

    /** @test */
    public function it_can_void_a_document()
    {
        $document = Document::factory()->create(['status' => 'sent']);

        $result = $this->signatureService->void($document, 'No longer needed');

        $this->assertTrue($result);
        $this->assertEquals('voided', $document->fresh()->status);
    }

    /** @test */
    public function it_can_associate_document_with_client()
    {
        $document = Document::factory()->create();
        $client = Admin::factory()->create(['role' => 2]);
        
        $this->actingAs($client, 'admin');

        $result = $this->signatureService->associate($document, 'client', $client->id, 'Test note');

        $this->assertTrue($result);
        
        $document->refresh();
        $this->assertEquals($client->id, $document->client_id);
        $this->assertNull($document->lead_id);
    }

    /** @test */
    public function it_can_associate_document_with_lead()
    {
        $document = Document::factory()->create();
        $lead = Lead::factory()->create();
        
        $admin = Admin::factory()->create(['role' => 1]);
        $this->actingAs($admin, 'admin');

        $result = $this->signatureService->associate($document, 'lead', $lead->id, 'Test note');

        $this->assertTrue($result);
        
        $document->refresh();
        $this->assertEquals($lead->id, $document->lead_id);
        $this->assertNull($document->client_id);
    }

    /** @test */
    public function it_can_detach_document_from_association()
    {
        $client = Admin::factory()->create(['role' => 2]);
        $document = Document::factory()->create([
            'client_id' => $client->id,
            'lead_id' => null
        ]);

        $admin = Admin::factory()->create(['role' => 1]);
        $this->actingAs($admin, 'admin');

        $result = $this->signatureService->detach($document, 'Test reason');

        $this->assertTrue($result);
        
        $document->refresh();
        $this->assertNull($document->client_id);
        $this->assertNull($document->lead_id);
    }

    /** @test */
    public function it_can_suggest_association_by_email_for_client()
    {
        $client = Admin::factory()->create([
            'role' => 2,
            'email' => 'client@example.com',
            'first_name' => 'John',
            'last_name' => 'Client'
        ]);

        $suggestion = $this->signatureService->suggestAssociation('client@example.com');

        $this->assertNotNull($suggestion);
        $this->assertEquals('client', $suggestion['type']);
        $this->assertEquals($client->id, $suggestion['id']);
        $this->assertEquals('John Client', $suggestion['name']);
        $this->assertEquals('client@example.com', $suggestion['email']);
    }

    /** @test */
    public function it_can_suggest_association_by_email_for_lead()
    {
        $lead = Lead::factory()->create([
            'email' => 'lead@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Lead'
        ]);

        $suggestion = $this->signatureService->suggestAssociation('lead@example.com');

        $this->assertNotNull($suggestion);
        $this->assertEquals('lead', $suggestion['type']);
        $this->assertEquals($lead->id, $suggestion['id']);
        $this->assertEquals('Jane Lead', $suggestion['name']);
        $this->assertEquals('lead@example.com', $suggestion['email']);
    }

    /** @test */
    public function it_returns_null_when_no_match_found()
    {
        $suggestion = $this->signatureService->suggestAssociation('nonexistent@example.com');

        $this->assertNull($suggestion);
    }

    /** @test */
    public function it_can_archive_old_drafts()
    {
        // Create old drafts (35 days old)
        Document::factory()->count(3)->create([
            'status' => 'draft',
            'created_at' => now()->subDays(35),
        ]);

        // Create recent draft (10 days old)
        Document::factory()->create([
            'status' => 'draft',
            'created_at' => now()->subDays(10),
        ]);

        // Create sent document (should not be archived)
        Document::factory()->create([
            'status' => 'sent',
            'created_at' => now()->subDays(35),
        ]);

        $count = $this->signatureService->archiveOldDrafts(30);

        $this->assertEquals(3, $count);
        $this->assertEquals(3, Document::where('status', 'archived')->count());
    }

    /** @test */
    public function it_can_get_pending_count_for_user()
    {
        $user = Admin::factory()->create(['role' => 1]);

        // Create documents for this user
        Document::factory()->count(2)->create([
            'created_by' => $user->id,
            'status' => 'sent',
        ]);

        // Create document for another user
        Document::factory()->create([
            'created_by' => 999,
            'status' => 'sent',
        ]);

        // Create signed document
        Document::factory()->create([
            'created_by' => $user->id,
            'status' => 'signed',
        ]);

        $count = $this->signatureService->getPendingCount($user->id);

        $this->assertEquals(2, $count);
    }
}

