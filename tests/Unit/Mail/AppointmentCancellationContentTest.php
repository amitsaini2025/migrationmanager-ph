<?php

namespace Tests\Unit\Mail;

use App\Mail\AppointmentCancellation;
use Illuminate\Mail\Mailables\Attachment;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AppointmentCancellationContentTest extends TestCase
{
    #[Test]
    public function it_renders_the_confirmation_layout_and_keeps_reschedule_and_call_actions(): void
    {
        $mailable = new AppointmentCancellation($this->details('phone', 'Client requested a new time'));
        $html = $mailable->render();

        $this->assertStringContainsString('Registered Migration Agents', $html);
        $this->assertStringContainsString('Appointment Details', $html);
        $this->assertStringContainsString('width:50%', $html);
        $this->assertStringContainsString('max-width:240px', $html);
        $this->assertStringContainsString('Phone Call', $html);
        $this->assertStringContainsString('EOI/ROI', $html);
        $this->assertStringContainsString('11:00 AM', $html);
        $this->assertStringNotContainsString('11:20 AM', $html);
        $this->assertStringContainsString('Free appointment is of 10 mins and Paid is of 30 mins.', $html);
        $this->assertStringContainsString('CANCELLED', $html);
        $this->assertStringContainsString('Client requested a new time', $html);
        $this->assertStringContainsString('Request to Reschedule', $html);
        $this->assertStringContainsString('Call Us', $html);
        $this->assertStringContainsString('mailto:info@bansalimmigration.com.au', $html);
        $this->assertStringContainsString('Reschedule%20Request', $html);
        $this->assertStringNotContainsString('/appointment/', $html);
        $mailable->assertHasAttachment(
            Attachment::fromPath(public_path('img/logo.png'))
                ->as('Bansal-Immigration-Logo.png')
                ->withMime('image/png')
        );
    }

    #[Test]
    public function it_renders_without_meeting_type_or_reason(): void
    {
        $details = $this->details('in_person');
        unset($details['meeting_type'], $details['service_type'], $details['cancellation_reason']);

        $html = (new AppointmentCancellation($details))->render();

        $this->assertStringContainsString('In-Person', $html);
        $this->assertStringContainsString('N/A', $html);
        $this->assertStringContainsString('No additional reason was provided', $html);
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(string $meetingType, ?string $reason = null): array
    {
        return [
            'client_name' => 'Vipul Kumar',
            'appointment_datetime' => now()->addDay(),
            'timeslot_full' => '11:00 AM – 11:20 AM',
            'location' => 'melbourne',
            'meeting_type' => $meetingType,
            'service_type' => 'EOI/ROI',
            'cancellation_reason' => $reason,
        ];
    }
}
