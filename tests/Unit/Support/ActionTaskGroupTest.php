<?php

namespace Tests\Unit\Support;

use App\Support\ActionTaskGroup;
use Tests\TestCase;

class ActionTaskGroupTest extends TestCase
{
    public function test_follow_up_constant_matches_action_page_filter_value(): void
    {
        $this->assertSame('Follow Up', ActionTaskGroup::FOLLOW_UP);
    }

    public function test_is_follow_up_only_matches_follow_up_group(): void
    {
        $this->assertTrue(ActionTaskGroup::isFollowUp('Follow Up'));
        $this->assertFalse(ActionTaskGroup::isFollowUp('Call'));
        $this->assertFalse(ActionTaskGroup::isFollowUp('Review'));
        $this->assertFalse(ActionTaskGroup::isFollowUp(null));
        $this->assertFalse(ActionTaskGroup::isFollowUp(''));
    }

    public function test_assign_activity_subject_distinguishes_followup_from_action(): void
    {
        $this->assertSame(
            'Set followup for Jane Staff',
            ActionTaskGroup::assignActivitySubject('Jane Staff', ActionTaskGroup::FOLLOW_UP)
        );
        $this->assertSame(
            'Set action for Jane Staff',
            ActionTaskGroup::assignActivitySubject('Jane Staff', 'Call')
        );
    }

    public function test_follow_up_is_visible_from_one_day_before_assign_date(): void
    {
        $today = new \DateTimeImmutable('2026-08-13');

        $this->assertFalse(ActionTaskGroup::followUpIsVisibleOnActionPage('2026-08-15', $today));
        $this->assertTrue(ActionTaskGroup::followUpIsVisibleOnActionPage('2026-08-14', $today));
        $this->assertTrue(ActionTaskGroup::followUpIsVisibleOnActionPage('2026-08-13', $today));
        $this->assertTrue(ActionTaskGroup::followUpIsVisibleOnActionPage('2026-08-01', $today));
        $this->assertFalse(ActionTaskGroup::followUpIsVisibleOnActionPage('2026-10-30', $today));
        $this->assertFalse(ActionTaskGroup::followUpIsVisibleOnActionPage(null, $today));
        $this->assertSame('2026-08-14', ActionTaskGroup::latestVisibleFollowUpAssignDate($today));
    }
}
