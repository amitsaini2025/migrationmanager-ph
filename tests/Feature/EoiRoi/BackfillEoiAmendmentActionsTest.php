<?php

namespace Tests\Feature\EoiRoi;

use App\Models\Admin;
use App\Models\ClientEoiReference;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Staff;
use App\Services\EoiClientConfirmationNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackfillEoiAmendmentActionsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $client;
    protected Staff $verifier;
    protected Staff $personAssisting;
    protected ClientEoiReference $eoi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'John',
            'last_name' => 'Son',
        ]);

        $this->verifier = Staff::create([
            'first_name' => 'Verifier',
            'last_name' => 'Staff',
            'email' => 'verifier-backfill@test.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        $this->personAssisting = Staff::create([
            'first_name' => 'PA',
            'last_name' => 'Staff',
            'email' => 'pa-backfill@test.com',
            'password' => Hash::make('password'),
            'status' => 1,
        ]);

        ClientMatter::create([
            'client_id' => $this->client->id,
            'client_unique_matter_no' => 'EOI_1',
            'matter_status' => 1,
            'sel_person_assisting' => $this->personAssisting->id,
        ]);

        $this->eoi = ClientEoiReference::factory()->create([
            'client_id' => $this->client->id,
            'EOI_number' => 'E0099999999',
            'staff_verified' => true,
            'checked_by' => $this->verifier->id,
            'client_confirmation_status' => 'amendment_requested',
            'client_confirmation_notes' => 'Legacy amendment note',
        ]);
    }

    public function test_backfill_command_creates_missing_action_and_notification(): void
    {
        $exitCode = Artisan::call('eoi:backfill-amendment-actions', [
            '--eoi-id' => $this->eoi->id,
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertDatabaseHas('notes', [
            'client_id' => $this->client->id,
            'assigned_to' => $this->personAssisting->id,
            'task_group' => 'EOI/ROI Amendment',
            'is_action' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'sender_id' => $this->client->id,
            'receiver_id' => $this->verifier->id,
            'notification_type' => 'eoi_amendment',
        ]);
    }

    public function test_backfill_is_idempotent(): void
    {
        EoiClientConfirmationNotificationService::backfillAmendmentRequest($this->eoi);

        $firstActionCount = Note::where('client_id', $this->client->id)
            ->where('task_group', 'EOI/ROI Amendment')
            ->count();
        $firstNotificationCount = Notification::where('notification_type', 'eoi_amendment')
            ->where('receiver_id', $this->verifier->id)
            ->count();

        $result = EoiClientConfirmationNotificationService::backfillAmendmentRequest($this->eoi);

        $this->assertSame('skipped', $result['action']);
        $this->assertSame('skipped', $result['notification']);
        $this->assertSame($firstActionCount, Note::where('client_id', $this->client->id)
            ->where('task_group', 'EOI/ROI Amendment')
            ->count());
        $this->assertSame($firstNotificationCount, Notification::where('notification_type', 'eoi_amendment')
            ->where('receiver_id', $this->verifier->id)
            ->count());
    }

    public function test_dry_run_does_not_persist_records(): void
    {
        $exitCode = Artisan::call('eoi:backfill-amendment-actions', [
            '--eoi-id' => $this->eoi->id,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, Note::where('client_id', $this->client->id)->count());
        $this->assertSame(0, Notification::where('notification_type', 'eoi_amendment')->count());
    }
}
