<?php

namespace Tests\Unit\Services;

use App\Services\VisaAgreementTemplateResolver;
use Tests\TestCase;

class VisaAgreementTemplateResolverTest extends TestCase
{
    private VisaAgreementTemplateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VisaAgreementTemplateResolver();
    }

    public function test_company_client_without_nomination_or_sponsorship_hints_uses_general_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'gn', 'General', false);
        $this->assertSame('company_general', $r['rule']);
        $this->assertSame(['Service_Agreement_general.docx'], $r['candidates']);
    }

    public function test_company_client_standard_business_sponsorship_uses_sponsorship_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'sbs', 'Standard Business Sponsorship', false);
        $this->assertSame('company_sponsorship', $r['rule']);
        $this->assertSame('Service_Agreement_company_sponsorship.docx', $r['candidates'][0]);
    }

    public function test_company_client_temporary_activities_sponsorship_uses_sponsorship_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'tas', 'Temporary Activities Sponsorship', false);
        $this->assertSame('company_sponsorship', $r['rule']);
        $this->assertSame('Service_Agreement_company_sponsorship.docx', $r['candidates'][0]);
    }

    public function test_company_client_employer_nomination_scheme_uses_nomination_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'de', 'Employer Nomination Scheme', false);
        $this->assertSame('company_nomination', $r['rule']);
        $this->assertSame('Service_Agreement_company_nomination.docx', $r['candidates'][0]);
    }

    public function test_company_client_regional_employer_nomination_uses_nomination_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'sesr', 'Regional Employer Nomination', false);
        $this->assertSame('company_nomination', $r['rule']);
        $this->assertSame('Service_Agreement_company_nomination.docx', $r['candidates'][0]);
    }

    public function test_company_client_skill_in_demand_nomination_uses_nomination_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'sidcoreskills', 'Skill in Demand Nomination - Core Skills', false);
        $this->assertSame('company_nomination', $r['rule']);
        $this->assertSame('Service_Agreement_company_nomination.docx', $r['candidates'][0]);
    }

    public function test_company_client_training_nomination_uses_nomination_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'tn 407', 'Training Nomination', false);
        $this->assertSame('company_nomination', $r['rule']);
        $this->assertSame('Service_Agreement_company_nomination.docx', $r['candidates'][0]);
    }

    public function test_company_client_generic_nomination_title_without_listed_matter_uses_general_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'gn', 'Employer nomination subclass 186', false);
        $this->assertSame('company_general', $r['rule']);
        $this->assertSame('Service_Agreement_general.docx', $r['candidates'][0]);
    }

    public function test_company_client_employer_subclass_without_nomination_uses_general_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'es', 'TSS sponsorship 482', false);
        $this->assertSame('company_general', $r['rule']);
        $this->assertSame('Service_Agreement_general.docx', $r['candidates'][0]);
    }

    public function test_art_matter_uses_art_template_and_legacy_tail(): void
    {
        $r = $this->resolver->determineCandidates(false, 'art', 'ART', false);
        $this->assertSame('art', $r['rule']);
        $this->assertSame(
            [
                'Service_Agreement_ART.docx',
                'agreement_template-ART.docx',
                'Service_Agreement_general.docx',
            ],
            $r['candidates']
        );
    }

    public function test_skill_nick_maps_to_skill_template_chain(): void
    {
        $r = $this->resolver->determineCandidates(false, 'skillassessment', 'X', false);
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
        $this->assertSame('Service_Agreement_general.docx', end($r['candidates']));
    }

    public function test_vetassess_signal_triggers_skill_path(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'General', true);
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
    }

    public function test_job_ready_nick_maps_to_job_ready_chain(): void
    {
        $r = $this->resolver->determineCandidates(false, 'jrp', 'Job Ready Program', false);
        $this->assertSame('job_ready', $r['rule']);
        $this->assertSame('Service_Agreement_Job_Ready.docx', $r['candidates'][0]);
    }

    public function test_partner_subclass_triggers_conflict_chain_starting_with_general(): void
    {
        $r = $this->resolver->determineCandidates(false, 'pv', 'Partner visa subclass 820', false);
        $this->assertSame('conflict_of_interests_visa', $r['rule']);
        $this->assertSame(['Service_Agreement_general.docx'], $r['candidates']);
    }

    public function test_employer_subclass_triggers_conflict(): void
    {
        $r = $this->resolver->determineCandidates(false, 'es', 'Subclass 482 TSS', false);
        $this->assertSame('conflict_of_interests_visa', $r['rule']);
        $this->assertSame('Service_Agreement_general.docx', $r['candidates'][0]);
    }

    public function test_family_sponsored_visitor_triggers_conflict(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'vbv',
            '600 - Visitor — Sponsored Family stream',
            false
        );
        $this->assertSame('conflict_of_interests_visa', $r['rule']);
    }

    public function test_generic_non_company_uses_general_then_legacy_fallbacks(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'General skilled', false);
        $this->assertSame('general', $r['rule']);
        $this->assertSame(['Service_Agreement_general.docx'], $r['candidates']);
    }

    public function test_visitor_600_without_family_sponsor_is_not_conflict(): void
    {
        $r = $this->resolver->determineCandidates(false, 'vbv', '600 - Tourist stream', false);
        $this->assertSame('general', $r['rule']);
    }

    public function test_company_art_matter_uses_art_template(): void
    {
        $r = $this->resolver->determineCandidates(true, 'art', 'ART', false);
        $this->assertSame('company_art', $r['rule']);
        $this->assertSame(
            [
                'Service_Agreement_ART.docx',
                'agreement_template-ART.docx',
                'Service_Agreement_general.docx',
            ],
            $r['candidates']
        );
    }

    public function test_company_employer_art_nick_uses_art_template(): void
    {
        $r = $this->resolver->determineCandidates(
            true,
            'employerart',
            'EmployerART_1 - Administrative Review Tribunal',
            false
        );
        $this->assertSame('company_art', $r['rule']);
        $this->assertSame('Service_Agreement_ART.docx', $r['candidates'][0]);
    }

    public function test_company_administrative_review_tribunal_title_uses_art_template(): void
    {
        $r = $this->resolver->determineCandidates(
            true,
            'gn',
            'Administrative Review Tribunal',
            false
        );
        $this->assertSame('company_art', $r['rule']);
        $this->assertSame('Service_Agreement_ART.docx', $r['candidates'][0]);
    }

    public function test_company_nomination_takes_priority_over_art_title_marker(): void
    {
        $r = $this->resolver->determineCandidates(
            true,
            'de',
            'Employer Nomination Scheme — Administrative Review Tribunal',
            false
        );
        $this->assertSame('company_nomination', $r['rule']);
        $this->assertSame('Service_Agreement_company_nomination.docx', $r['candidates'][0]);
    }

    public function test_job_ready_path_takes_priority_over_conflict_subclass_in_title(): void
    {
        $r = $this->resolver->determineCandidates(false, 'jrp', 'JRP / 482 overlap', false);
        $this->assertSame('job_ready', $r['rule']);
    }

    public function test_parents_subclass_in_title_selects_parents_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'Contributory parent 143', false);
        $this->assertSame('parents', $r['rule']);
        $this->assertSame('Service_Agreement_parents.docx', $r['candidates'][0]);
    }

    public function test_sponsored_parent_870_subclass_in_title_selects_parents_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'gn',
            '870 - Sponsored Parent (Temporary)',
            false
        );
        $this->assertSame('parents', $r['rule']);
        $this->assertSame('Service_Agreement_parents.docx', $r['candidates'][0]);
        $this->assertSame('Service_Agreement_general.docx', end($r['candidates']));
    }

    public function test_eoi_and_roi_in_title_selects_eoi_roi_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'EOI and ROI for state nomination', false);
        $this->assertSame('eoi_roi', $r['rule']);
        $this->assertSame('Service_Agreement_EOI_ROI.docx', $r['candidates'][0]);
    }

    public function test_expression_of_interest_title_selects_eoi_roi_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'EOI_1 - Expression Of Interest', false);
        $this->assertSame('eoi_roi', $r['rule']);
        $this->assertSame('Service_Agreement_EOI_ROI.docx', $r['candidates'][0]);
    }

    public function test_eoi_nick_name_selects_eoi_roi_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'eoi', 'General', false);
        $this->assertSame('eoi_roi', $r['rule']);
        $this->assertSame('Service_Agreement_EOI_ROI.docx', $r['candidates'][0]);
    }

    public function test_registration_of_interest_only_title_selects_eoi_roi_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'SA Registration of Interest', false);
        $this->assertSame('eoi_roi', $r['rule']);
        $this->assertSame('Service_Agreement_EOI_ROI.docx', $r['candidates'][0]);
    }

    public function test_citizenship_in_title_selects_citizenship_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'Australian citizenship application', false);
        $this->assertSame('citizenship', $r['rule']);
        $this->assertSame('Service_Agreement_citizenship.docx', $r['candidates'][0]);
    }

    public function test_subclass_408_in_title_selects_408_template(): void
    {
        $r = $this->resolver->determineCandidates(false, 'gn', 'Temporary activity subclass 408', false);
        $this->assertSame('subclass_408', $r['rule']);
        $this->assertSame('Service_Agreement_408.docx', $r['candidates'][0]);
    }

    public function test_psa_nick_selects_psa_template_before_skill_assessment(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'psa',
            'Provisional Skills Assessment-PSA',
            false
        );
        $this->assertSame('psa', $r['rule']);
        $this->assertSame('Service_Agreement_PSA.docx', $r['candidates'][0]);
        $this->assertSame('Service_Agreement_general.docx', end($r['candidates']));
    }

    public function test_provisional_skills_assessment_in_title_selects_psa_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'gn',
            'Provisional Skills Assessment pathway',
            false
        );
        $this->assertSame('psa', $r['rule']);
        $this->assertSame('Service_Agreement_PSA.docx', $r['candidates'][0]);
    }

    public function test_vetassess_psa_matter_without_provisional_title_still_uses_skill_assessment(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'vetassespsa',
            'Skill Assessment Vetassess PSA',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
    }

    public function test_australian_pharmacy_council_apc_title_uses_skill_assessment_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'apc',
            'Skill Assessment_Australian Pharmacy Council_APC',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
        $this->assertSame('Service_Agreement_general.docx', end($r['candidates']));
    }

    public function test_aasw_matter_uses_skill_assessment_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'aasw',
            'Australian Association of Social Workers',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
    }

    public function test_australian_physiotherapy_council_matter_uses_skill_assessment_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'apc',
            'Skill assessment - Australian Physiotherapy Council',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
    }

    public function test_aasw_title_without_skill_nick_still_uses_skill_assessment_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'gn',
            'Australian Association of Social Workers',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
    }

    public function test_psa_still_takes_priority_over_skill_assessment_title_markers(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'psa',
            'Provisional Skills Assessment - Australian Physiotherapy Council',
            false
        );
        $this->assertSame('psa', $r['rule']);
        $this->assertSame('Service_Agreement_PSA.docx', $r['candidates'][0]);
    }

    public function test_occupation_suggestion_matter_uses_skill_assessment_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'os',
            'Occupation Suggestion',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
        $this->assertSame('Service_Agreement_general.docx', end($r['candidates']));
    }

    public function test_occupation_suggestion_title_without_os_nick_uses_skill_assessment_template(): void
    {
        $r = $this->resolver->determineCandidates(
            false,
            'gn',
            'Occupation Suggestion',
            false
        );
        $this->assertSame('skill_assessment', $r['rule']);
        $this->assertSame('Service_Agreement_Skill_Assessment.docx', $r['candidates'][0]);
    }
}
