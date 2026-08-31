<?php

namespace Tests\Unit\Support;

use App\Support\BansalSchedulingServiceType;
use PHPUnit\Framework\TestCase;

class BansalSchedulingServiceTypeTest extends TestCase
{
    public function test_melbourne_family_visas_uses_employer_sponsored_timeslots(): void
    {
        $this->assertSame(
            'employer-sponsored',
            BansalSchedulingServiceType::fromEnquiryItem(11, 'melbourne')
        );
    }

    public function test_melbourne_citizenship_uses_employer_sponsored_timeslots(): void
    {
        $this->assertSame(
            'employer-sponsored',
            BansalSchedulingServiceType::fromEnquiryItem(12, 'melbourne')
        );
    }

    public function test_adelaide_family_visas_keeps_own_service_type(): void
    {
        $this->assertSame(
            'family-visas',
            BansalSchedulingServiceType::fromEnquiryItem(11, 'adelaide')
        );
    }

    public function test_adelaide_citizenship_keeps_own_service_type(): void
    {
        $this->assertSame(
            'citizenship',
            BansalSchedulingServiceType::fromEnquiryItem(12, 'adelaide')
        );
    }

    public function test_employer_sponsored_unchanged_for_melbourne(): void
    {
        $this->assertSame(
            'employer-sponsored',
            BansalSchedulingServiceType::fromEnquiryItem(10, 'melbourne')
        );
    }

    public function test_melbourne_outside_australia_slot_lookup_uses_international_migration(): void
    {
        $this->assertSame(
            'international-migration',
            BansalSchedulingServiceType::fromEnquiryItem(8, 'melbourne')
        );
    }

    public function test_melbourne_outside_australia_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(8, 'melbourne', 'international')
        );
    }

    public function test_adelaide_outside_australia_bansal_sync_uses_ajay(): void
    {
        $this->assertSame(
            'ajay',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(8, 'adelaide', 'international')
        );
    }

    public function test_outside_australia_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'international-migration',
            BansalSchedulingServiceType::bansalServiceTypeForApi(
                8,
                'Anyone who is outside Australia'
            )
        );
    }

    public function test_melbourne_employer_sponsored_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(10, 'melbourne', 'employer_sponsored')
        );
    }

    public function test_adelaide_employer_sponsored_bansal_sync_uses_ajay(): void
    {
        $this->assertSame(
            'ajay',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(10, 'adelaide', 'employer_sponsored')
        );
    }

    public function test_employer_sponsored_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'employer-sponsored',
            BansalSchedulingServiceType::bansalServiceTypeForApi(
                10,
                'Employer Sponsored Visas: 494, 482, 186, DAMA'
            )
        );
    }

    public function test_melbourne_family_visas_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(11, 'melbourne', 'family_visas')
        );
    }

    public function test_melbourne_citizenship_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(12, 'melbourne', 'citizenship')
        );
    }

    public function test_adelaide_family_visas_bansal_sync_uses_ajay(): void
    {
        $this->assertSame(
            'ajay',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(11, 'adelaide', 'family_visas')
        );
    }

    public function test_adelaide_citizenship_bansal_sync_uses_ajay(): void
    {
        $this->assertSame(
            'ajay',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(12, 'adelaide', 'citizenship')
        );
    }

    public function test_melbourne_tr_bansal_sync_unchanged(): void
    {
        $this->assertSame(
            'tr',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(2, 'melbourne', 'tr')
        );
    }

    public function test_family_visas_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'family-visas',
            BansalSchedulingServiceType::bansalServiceTypeForApi(
                11,
                'Family Visas (Parent Visa, Partner Visa, Child Visa)'
            )
        );
    }

    public function test_citizenship_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'citizenship',
            BansalSchedulingServiceType::bansalServiceTypeForApi(12, 'Citizenship')
        );
    }

    public function test_other_noe_bansal_service_type_uses_slug(): void
    {
        $this->assertSame(
            'temporary-residency',
            BansalSchedulingServiceType::bansalServiceTypeForApi(2, 'TR: 485 visa')
        );
    }

    public function test_melbourne_eoi_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(9, 'melbourne', 'eoi')
        );
    }

    public function test_melbourne_jrp_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(3, 'melbourne', 'jrp')
        );
    }

    public function test_melbourne_gsm_bansal_sync_uses_pr_complex(): void
    {
        $this->assertSame(
            'pr_complex',
            BansalSchedulingServiceType::bansalEnquiryTypeForApi(1, 'melbourne', 'pr_complex')
        );
    }

    public function test_melbourne_eoi_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'eoi-roi',
            BansalSchedulingServiceType::bansalServiceTypeForApi(9, 'EOI/ROI')
        );
    }

    public function test_melbourne_jrp_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'jrp-skill-assessment',
            BansalSchedulingServiceType::bansalServiceTypeForApi(3, 'JRP/Skill Assessment')
        );
    }

    public function test_melbourne_gsm_bansal_service_type_slug(): void
    {
        $this->assertSame(
            'permanent-residency',
            BansalSchedulingServiceType::bansalServiceTypeForApi(1, 'GSM Visas: 491, 190, 189, 191')
        );
    }

    public function test_melbourne_crm_only_ajay_uses_ajay_calendar_slug_and_enquiry_type(): void
    {
        $this->assertSame('ajay', BansalSchedulingServiceType::fromEnquiryItem(13, 'melbourne'));
        $this->assertSame('ajay', BansalSchedulingServiceType::bansalEnquiryTypeForApi(13, 'melbourne', 'ajay'));
        $this->assertSame('ajay', BansalSchedulingServiceType::bansalServiceTypeForApi(13, 'Ajay'));
    }

    public function test_melbourne_crm_only_arun_uses_arun_calendar_slug_and_enquiry_type(): void
    {
        $this->assertSame('arun', BansalSchedulingServiceType::fromEnquiryItem(14, 'melbourne'));
        $this->assertSame('arun', BansalSchedulingServiceType::bansalEnquiryTypeForApi(14, 'melbourne', 'arun'));
        $this->assertSame('arun', BansalSchedulingServiceType::bansalServiceTypeForApi(14, 'Arun'));
    }

    public function test_crm_only_noe_ids(): void
    {
        $this->assertTrue(BansalSchedulingServiceType::isCrmOnlyNoe(13));
        $this->assertTrue(BansalSchedulingServiceType::isCrmOnlyNoe(14));
        $this->assertFalse(BansalSchedulingServiceType::isCrmOnlyNoe(6));
        $this->assertFalse(BansalSchedulingServiceType::isCrmOnlyNoe(7));
    }
}
