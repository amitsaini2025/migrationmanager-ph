<?php

namespace Tests\Unit\Mail;

use App\Mail\AppointmentReschedule;
use Illuminate\Mail\Mailables\Attachment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentRescheduleContentTest extends TestCase
{
    #[Test]
    public function it_renders_type_specific_copy_and_hides_buttons_without_appointment_id(): void
    {
        $mailable = new AppointmentReschedule($this->details('phone'));
        $html = $mailable->render();

        $this->assertStringContainsString('Registered Migration Agents', $html);
        $this->assertStringContainsString('Appointment Details', $html);
        $this->assertStringContainsString('width:50%', $html);
        $this->assertStringContainsString('max-width:240px', $html);
        $this->assertStringContainsString('Previous Date', $html);
        $this->assertStringContainsString('Phone Call', $html);
        $this->assertStringContainsString('Phone Appointment Reminder', $html);
        $this->assertStringContainsString('What to Have Ready', $html);
        $this->assertStringContainsString('11:00 AM', $html);
        $this->assertStringNotContainsString('11:20 AM', $html);
        $this->assertStringContainsString('Free appointment is of 10 mins and Paid is of 30 mins.', $html);
        $this->assertStringNotContainsString('>Cancel</a>', $html);
        $this->assertStringNotContainsString('>Confirm</a>', $html);
        $mailable->assertHasAttachment(
            Attachment::fromPath(public_path('img/logo.png'))
                ->as('Bansal-Immigration-Logo.png')
                ->withMime('image/png')
        );
    }

    #[Test]
    public function it_renders_in_person_copy_and_action_buttons_when_appointment_id_is_present(): void
    {
        $details = $this->details('in_person');
        $details['appointment_id'] = 42;

        $html = (new AppointmentReschedule($details))->render();

        $this->assertStringContainsString('In-Person', $html);
        $this->assertStringContainsString('In-Person Appointment Reminder', $html);
        $this->assertStringContainsString('10 minutes before', $html);
        $this->assertStringContainsString('What to Bring', $html);
        $this->assertStringContainsString('Cancel</a>', $html);
        $this->assertStringContainsString('Reschedule</a>', $html);
        $this->assertStringContainsString('Confirm</a>', $html);
        $this->assertStringContainsString('This appointment stays pending until you confirm it', $html);
        $this->assertStringContainsString('/appointment/42/cancel', $html);
        $this->assertStringContainsString('at='.$details['appointment_datetime']->getTimestamp(), $html);
    }

    #[Test]
    public function it_renders_video_call_copy(): void
    {
        $html = (new AppointmentReschedule($this->details('video')))->render();

        $this->assertStringContainsString('Video Call', $html);
        $this->assertStringContainsString('Video Call Appointment Reminder', $html);
        $this->assertStringContainsString('camera', strtolower($html));
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(string $meetingType): array
    {
        return [
            'client_name' => 'Vipul Kumar',
            'old_datetime' => now(),
            'appointment_datetime' => now()->addDay(),
            'timeslot_full' => '11:00 AM – 11:20 AM',
            'location' => 'melbourne',
            'meeting_type' => $meetingType,
            'service_type' => 'EOI/ROI',
        ];
    }
}
