<?php

namespace Tests\Unit\Support;

use App\Models\BookingAppointment;
use App\Support\AppointmentActivityDescription;
use Carbon\Carbon;
use Tests\TestCase;

class AppointmentActivityDescriptionTest extends TestCase
{
    public function test_build_description_includes_labelled_category_appt_type_and_query(): void
    {
        $appointment = new BookingAppointment([
            'service_id' => 2,
            'noe_id' => 11,
            'service_type' => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
            'meeting_type' => 'in_person',
            'preferred_language' => 'Punjabi',
            'enquiry_details' => 'Test. Pls ignore',
            'location' => 'melbourne',
            'appointment_datetime' => Carbon::parse('2026-08-13 12:00:00'),
            'timeslot_full' => '12:00 PM-12:20 PM',
        ]);

        $html = AppointmentActivityDescription::buildDescription($appointment);

        $this->assertStringContainsString('appointment-activity-detail__chip', $html);
        $this->assertStringContainsString('appointment-activity-detail__row--datetime', $html);
        $this->assertStringContainsString('data-field="datetime"', $html);
        $this->assertStringContainsString('Category:', $html);
        $this->assertStringContainsString('Family Visas (Parent Visa, Partner Visa, Child Visa)', $html);
        $this->assertStringContainsString('Appt. Type:', $html);
        $this->assertStringContainsString('Free Consultation · In Person', $html);
        $this->assertStringContainsString('Query:', $html);
        $this->assertStringContainsString('Test. Pls ignore', $html);
        $this->assertStringContainsString('Language:', $html);
        $this->assertStringContainsString('Punjabi', $html);
        $this->assertStringContainsString('Location:', $html);
        $this->assertStringContainsString('Melbourne Free PR', $html);
        $this->assertStringContainsString('Date &amp; Time:', $html);
        $this->assertStringContainsString('13 Aug 2026 · 12:00 PM-12:20 PM', $html);
    }

    public function test_activity_subject_uses_correct_grammar(): void
    {
        $this->assertSame('scheduled a free appointment', AppointmentActivityDescription::activitySubject(2));
        $this->assertSame('scheduled a paid appointment', AppointmentActivityDescription::activitySubject(1));
        $this->assertSame('scheduled an appointment', AppointmentActivityDescription::activitySubject(null));
    }

    public function test_category_falls_back_to_noe_id_when_service_type_missing(): void
    {
        $appointment = new BookingAppointment([
            'noe_id' => 9,
            'service_type' => null,
        ]);

        $this->assertSame('EOI/ROI', AppointmentActivityDescription::categoryLabel($appointment));
    }

    public function test_category_falls_back_to_ajay_and_arun_noe_labels(): void
    {
        $ajay = new BookingAppointment([
            'noe_id' => 13,
            'service_type' => null,
        ]);
        $arun = new BookingAppointment([
            'noe_id' => 14,
            'service_type' => null,
        ]);

        $this->assertSame('Ajay', AppointmentActivityDescription::categoryLabel($ajay));
        $this->assertSame('Arun', AppointmentActivityDescription::categoryLabel($arun));
    }
}
