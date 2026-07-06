<?php

namespace Tests\Unit\Support;

use App\Models\ActivitiesLog;
use App\Models\BookingAppointment;
use App\Support\AppointmentActivityLogBackfill;
use Carbon\Carbon;
use Tests\TestCase;

class AppointmentActivityLogBackfillTest extends TestCase
{
    public function test_is_eligible_accepts_legacy_scheduling_logs_only(): void
    {
        $legacy = new ActivitiesLog([
            'subject' => 'scheduled an free appointment',
            'description' => '<span class="text-semi-bold">Family Visas</span>',
            'activity_type' => 'activity',
        ]);

        $alreadyNew = new ActivitiesLog([
            'subject' => 'scheduled a free appointment',
            'description' => '<div class="appointment-activity-detail"></div>',
            'activity_type' => 'activity',
        ]);

        $otherActivity = new ActivitiesLog([
            'subject' => 'added a note',
            'description' => '<p>Note body</p>',
            'activity_type' => 'note',
        ]);

        $this->assertTrue(AppointmentActivityLogBackfill::isEligible($legacy));
        $this->assertFalse(AppointmentActivityLogBackfill::isEligible($alreadyNew));
        $this->assertFalse(AppointmentActivityLogBackfill::isEligible($otherActivity));
    }

    public function test_parse_legacy_description_hints_extracts_date_time_and_query_fragment(): void
    {
        $description = '<span class="text-semi-bold">Family Visas</span>'
            .'<span>13 Aug</span><span>2026</span>'
            .'<span class="text-semi-bold">Test.Pls ignore</span>'
            .'<p class="text-semi-light-grey col-v-1">@ 12:00 PM-12:20 PM</p>';

        $hints = AppointmentActivityLogBackfill::parseLegacyDescriptionHints($description);

        $this->assertNull($hints['appointment_date']);
        $this->assertSame('12:00 PM-12:20 PM', $hints['timeslot']);
        $this->assertSame('Test.Pls ignore', $hints['enquiry_fragment']);
    }

    public function test_refreshed_fields_rebuilds_labelled_description(): void
    {
        $appointment = new BookingAppointment([
            'service_id' => 2,
            'noe_id' => 11,
            'service_type' => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
            'meeting_type' => 'in_person',
            'preferred_language' => 'Punjabi',
            'enquiry_details' => 'Test.Pls ignore',
            'location' => 'melbourne',
            'appointment_datetime' => Carbon::parse('2026-08-13 12:00:00'),
            'timeslot_full' => '12:00 PM-12:20 PM',
        ]);

        $fields = AppointmentActivityLogBackfill::refreshedFields($appointment);

        $this->assertSame('scheduled a free appointment', $fields['subject']);
        $this->assertStringContainsString('appointment-activity-detail', $fields['description']);
        $this->assertStringContainsString('Category:', $fields['description']);
        $this->assertStringContainsString('Query:', $fields['description']);
        $this->assertStringContainsString('Test.Pls ignore', $fields['description']);
    }
}
