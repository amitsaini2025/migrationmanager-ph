<?php

namespace Tests\Unit\Services;

use App\Services\BansalAppointmentSync\ConsultantAssignmentService;
use PHPUnit\Framework\TestCase;

class ConsultantAssignmentServiceMelbournePunjabiTest extends TestCase
{
    private function calendarType(array $data): ?string
    {
        $svc = new class extends ConsultantAssignmentService
        {
            public function exposeDetermine(array $a): ?string
            {
                return $this->determineCalendarType($a);
            }
        };

        return $svc->exposeDetermine($data);
    }

    public function test_melbourne_punjabi_gsm_free_goes_jrp(): void
    {
        $this->assertSame('jrp', $this->calendarType([
            'noe_id' => 1,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'Punjabi',
            'service_type' => 'GSM Visas: 491, 190, 189, 191',
        ]));
    }

    public function test_melbourne_punjabi_eoi_free_goes_jrp(): void
    {
        $this->assertSame('jrp', $this->calendarType([
            'noe_id' => 9,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'punjabi',
            'service_type' => 'EOI/ROI',
        ]));
    }

    public function test_melbourne_punjabi_gsm_paid_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 1,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'preferred_language' => 'Punjabi',
            'service_type' => 'GSM Visas: 491, 190, 189, 191',
        ]));
    }

    public function test_melbourne_english_gsm_free_goes_jrp(): void
    {
        $this->assertSame('jrp', $this->calendarType([
            'noe_id' => 1,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'English',
            'service_type' => 'GSM Visas: 491, 190, 189, 191',
        ]));
    }

    public function test_melbourne_hindi_gsm_paid_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 1,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'preferred_language' => 'Hindi',
            'service_type' => 'GSM Visas: 491, 190, 189, 191',
        ]));
    }

    public function test_melbourne_english_eoi_paid_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 9,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'preferred_language' => 'English',
            'service_type' => 'EOI/ROI',
        ]));
    }

    public function test_melbourne_tr_485_free_goes_jrp(): void
    {
        $this->assertSame('jrp', $this->calendarType([
            'noe_id' => 2,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'English',
            'service_type' => 'TR: 485 visa',
        ]));
    }

    public function test_melbourne_tr_485_paid_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 2,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'preferred_language' => 'Hindi',
            'service_type' => 'TR: 485 visa',
        ]));
    }

    public function test_melbourne_jrp_skill_assessment_free_goes_jrp(): void
    {
        $this->assertSame('jrp', $this->calendarType([
            'noe_id' => 3,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'Punjabi',
            'service_type' => 'JRP/Skill Assessment',
        ]));
    }

    public function test_melbourne_jrp_skill_assessment_paid_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 3,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'preferred_language' => 'English',
            'service_type' => 'JRP/Skill Assessment',
        ]));
    }

    public function test_adelaide_punjabi_gsm_free_still_adelaide(): void
    {
        $this->assertSame('adelaide', $this->calendarType([
            'noe_id' => 1,
            'location' => 'adelaide',
            'inperson_address' => 1,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'Punjabi',
            'service_type' => 'GSM Visas: 491, 190, 189, 191',
        ]));
    }

    public function test_adelaide_tourist_goes_adelaide_education_calendar(): void
    {
        $this->assertSame('adelaide_education', $this->calendarType([
            'noe_id' => 4,
            'location' => 'adelaide',
            'inperson_address' => 1,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'service_type' => 'tourist-visa',
        ]));
    }

    public function test_adelaide_education_noe_goes_adelaide_education_calendar(): void
    {
        $this->assertSame('adelaide_education', $this->calendarType([
            'noe_id' => 5,
            'location' => 'adelaide',
            'inperson_address' => 1,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'service_type' => 'education-visa',
        ]));
    }

    public function test_melbourne_punjabi_tourist_unchanged(): void
    {
        $this->assertSame('tourist', $this->calendarType([
            'noe_id' => 4,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'preferred_language' => 'Punjabi',
            'service_type' => 'Tourist Visa',
        ]));
    }

    public function test_melbourne_family_visas_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 11,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'service_type' => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
        ]));
    }

    public function test_melbourne_citizenship_goes_employer_sponsored(): void
    {
        $this->assertSame('paid', $this->calendarType([
            'noe_id' => 12,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'service_type' => 'Citizenship',
        ]));
    }

    public function test_adelaide_family_visas_stays_adelaide(): void
    {
        $this->assertSame('adelaide', $this->calendarType([
            'noe_id' => 11,
            'location' => 'adelaide',
            'inperson_address' => 1,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'service_type' => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
        ]));
    }

    public function test_melbourne_crm_only_ajay_noe_goes_ajay_calendar(): void
    {
        $this->assertSame('ajay', $this->calendarType([
            'noe_id' => 13,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 2,
            'specific_service' => 'consultation',
            'service_type' => 'Ajay',
        ]));
    }

    public function test_melbourne_crm_only_arun_noe_goes_arun_calendar(): void
    {
        $this->assertSame('arun', $this->calendarType([
            'noe_id' => 14,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'service_type' => 'Arun',
        ]));
    }

    public function test_melbourne_complex_matters_still_goes_ajay(): void
    {
        $this->assertSame('ajay', $this->calendarType([
            'noe_id' => 6,
            'location' => 'melbourne',
            'inperson_address' => 2,
            'service_id' => 1,
            'specific_service' => 'paid-consultation',
            'service_type' => 'Complex Matters (ART, Protection visa, Federal Case)',
        ]));
    }
}
