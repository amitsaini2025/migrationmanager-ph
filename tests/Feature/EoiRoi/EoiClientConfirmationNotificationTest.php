<?php

namespace Tests\Feature\EoiRoi;

use App\Events\NotificationCountUpdated;
use App\Models\Admin;
use App\Models\ActivitiesLog;
use App\Models\ClientEoiReference;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EoiClientConfirmationNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected Admin $client;
    protected Staff $verifier;
    protected Staff $personAssisting;
    protected Staff $personResponsible;
    protected ClientMatter $matter;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([NotificationCountUpdated::class]);

        $this->client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'John',
            'last_name' => 'Son',
        ]);

        $this->verifier = Staff::create([
            'first_name' => 'Gurjent',
            'last_name' => 'Singh',
            'email' => 'verifier@test.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $this->personAssisting = Staff::create([
            'first_name' => 'PA',
            'last_name' => 'Staff',
            'email' => 'pa@test.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $this->personResponsible = Staff::create([
            'first_name' => 'PR',
            'last_name' => 'Staff',
            'email' => 'pr@test.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $this->matter = ClientMatter::create([
            'client_id' => $this->client->id,
            'client_unique_matter_no' => 'EOI_1',
            'matter_status' => 1,
            'sel_person_responsible' => $this->personResponsible->id,
            'sel_person_assisting' => $this->personAssisting->id,
        ]);
    }

    public function test_amendment_notifies_verifier_only_and_assigns_action_to_pa_only(): void
    {
        $token = 'test-amendment-token-' . str_repeat('a', 40);
        $eoi = ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
            'EOI_number' => 'E0039826225',
            'staff_verified' => true,
            'checked_by' => $this->verifier->id,
            'client_confirmation_token' => $token,
            'client_confirmation_status' => 'pending',
        ]);

        $response = $this->post(route('client.eoi.process', ['token' => $token]), [
            'action' => 'amend',
            'notes' => 'testing -- need to add points',
        ]);

        $response->assertRedirect(route('client.eoi.success', ['token' => $token]));

        $eoi->refresh();
        $this->assertSame('amendment_requested', $eoi->client_confirmation_status);

        $this->assertDatabaseHas('activities_logs', [
            'client_id' => $this->client->id,
            'activity_type' => 'eoi_amendment',
        ]);

        $this->assertDatabaseHas('notifications', [
            'sender_id' => $this->client->id,
            'receiver_id' => $this->verifier->id,
            'notification_type' => 'eoi_amendment',
        ]);

        $this->assertSame(1, Notification::where('notification_type', 'eoi_amendment')->count());

        $notification = Notification::where('receiver_id', $this->verifier->id)
            ->where('notification_type', 'eoi_amendment')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('E0039826225', $notification->message);
        $this->assertStringContainsString('testing -- need to add points', $notification->message);
        $this->assertStringContainsString('/eoiroi', $notification->url);

        $this->assertFalse(
            Notification::where('receiver_id', $this->personAssisting->id)->exists()
        );
        $this->assertFalse(
            Notification::where('receiver_id', $this->personResponsible->id)->exists()
        );

        $this->assertDatabaseHas('notes', [
            'client_id' => $this->client->id,
            'assigned_to' => $this->personAssisting->id,
            'is_action' => 1,
            'task_group' => 'EOI/ROI Amendment',
        ]);

        $this->assertFalse(
            Note::where('client_id', $this->client->id)
                ->where('assigned_to', $this->verifier->id)
                ->where('is_action', 1)
                ->exists()
        );
        $this->assertFalse(
            Note::where('client_id', $this->client->id)
                ->where('assigned_to', $this->personResponsible->id)
                ->where('is_action', 1)
                ->exists()
        );

        Event::assertDispatched(NotificationCountUpdated::class, function ($event) {
            return (int) $event->receiverId === (int) $this->verifier->id;
        });
    }

    public function test_client_confirmation_notifies_verifier_only_and_assigns_action_to_pa_only(): void
    {
        $token = 'test-confirm-token-' . str_repeat('b', 40);
        $eoi = ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
            'EOI_number' => 'E0012345678',
            'staff_verified' => true,
            'checked_by' => $this->verifier->id,
            'client_confirmation_token' => $token,
            'client_confirmation_status' => 'pending',
        ]);

        $response = $this->post(route('client.eoi.process', ['token' => $token]), [
            'action' => 'confirm',
        ]);

        $response->assertRedirect(route('client.eoi.success', ['token' => $token]));

        $this->assertDatabaseHas('activities_logs', [
            'client_id' => $this->client->id,
            'activity_type' => 'eoi_confirmation',
        ]);

        $this->assertDatabaseHas('notifications', [
            'receiver_id' => $this->verifier->id,
            'notification_type' => 'eoi_confirmation',
        ]);

        $this->assertSame(1, Notification::where('notification_type', 'eoi_confirmation')->count());

        $this->assertTrue(
            Note::where('client_id', $this->client->id)
                ->where('assigned_to', $this->personAssisting->id)
                ->where('is_action', 1)
                ->where('task_group', 'Client Portal')
                ->exists()
        );
    }

    public function test_amendment_still_succeeds_when_notification_creation_fails(): void
    {
        $token = 'test-resilience-token-' . str_repeat('c', 40);
        ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
            'staff_verified' => true,
            'checked_by' => null,
            'client_confirmation_token' => $token,
            'client_confirmation_status' => 'pending',
        ]);

        $response = $this->post(route('client.eoi.process', ['token' => $token]), [
            'action' => 'amend',
            'notes' => 'Should still save amendment',
        ]);

        $response->assertRedirect(route('client.eoi.success', ['token' => $token]));

        $this->assertDatabaseHas('client_eoi_references', [
            'client_confirmation_token' => $token,
            'client_confirmation_status' => 'amendment_requested',
        ]);

        $this->assertSame(1, ActivitiesLog::where('client_id', $this->client->id)
            ->where('activity_type', 'eoi_amendment')
            ->count());
    }
}
