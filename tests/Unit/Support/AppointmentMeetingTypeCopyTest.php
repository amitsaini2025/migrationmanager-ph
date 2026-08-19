<?php

namespace Tests\Unit\Support;

use App\Support\AppointmentMeetingTypeCopy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentMeetingTypeCopyTest extends TestCase
{
    #[Test]
    public function it_normalizes_and_labels_meeting_types(): void
    {
        $this->assertSame('In-Person', AppointmentMeetingTypeCopy::label('in_person'));
        $this->assertSame('Phone Call', AppointmentMeetingTypeCopy::label('phone'));
        $this->assertSame('Video Call', AppointmentMeetingTypeCopy::label('video_call'));
    }

    #[Test]
    public function it_changes_reminder_and_bring_copy_by_type(): void
    {
        $this->assertStringContainsString('10 minutes before', AppointmentMeetingTypeCopy::reminderBody('in_person'));
        $this->assertStringContainsString('phone', strtolower(AppointmentMeetingTypeCopy::reminderBody('phone')));
        $this->assertStringContainsString('internet', strtolower(AppointmentMeetingTypeCopy::reminderBody('video')));

        $this->assertSame('What to Bring', AppointmentMeetingTypeCopy::bringTitle('in_person'));
        $this->assertSame('What to Have Ready', AppointmentMeetingTypeCopy::bringTitle('phone'));
        $this->assertNotSame(
            AppointmentMeetingTypeCopy::bringItems('in_person'),
            AppointmentMeetingTypeCopy::bringItems('video')
        );
    }
}
