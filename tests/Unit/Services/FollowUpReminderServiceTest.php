<?php

namespace Tests\Unit\Services;

use App\Models\Admin;
use App\Services\FollowUpReminderService;
use App\Support\ActionTaskGroup;
use Tests\TestCase;

class FollowUpReminderServiceTest extends TestCase
{
    public function test_reminder_message_is_labelled_as_reminder(): void
    {
        $this->assertSame(
            'Reminder: Follow Up due tomorrow for Vipul Kumar.',
            FollowUpReminderService::reminderMessage('Vipul Kumar')
        );
        $this->assertSame(
            'Reminder: Follow Up due tomorrow for Vipul Kumar. Call the client',
            FollowUpReminderService::reminderMessage('Vipul Kumar', 'Call the client')
        );
    }

    public function test_note_snippet_strips_html_and_truncates(): void
    {
        $this->assertSame('Call the client', FollowUpReminderService::noteSnippet('<p>Call the client</p>'));
        $this->assertSame('', FollowUpReminderService::noteSnippet('   '));
        $long = str_repeat('a', 130);
        $snippet = FollowUpReminderService::noteSnippet($long);
        $this->assertTrue(str_ends_with($snippet, '...'));
        $this->assertSame(120, mb_strlen($snippet));
    }

    public function test_client_label_falls_back_when_missing(): void
    {
        $this->assertSame('Client', FollowUpReminderService::clientLabel(null));
    }

    public function test_client_label_uses_person_name(): void
    {
        $client = new Admin([
            'first_name' => 'Vipul',
            'last_name' => 'Kumar',
            'is_company' => 0,
        ]);

        $this->assertSame('Vipul Kumar', FollowUpReminderService::clientLabel($client));
    }

    public function test_due_date_matches_one_day_before_assign_date_window(): void
    {
        $today = new \DateTimeImmutable('2026-08-13');

        $this->assertSame('2026-08-14', ActionTaskGroup::latestVisibleFollowUpAssignDate($today));
        $this->assertSame(FollowUpReminderService::NOTIFICATION_TYPE, 'followup_reminder');
    }
}
