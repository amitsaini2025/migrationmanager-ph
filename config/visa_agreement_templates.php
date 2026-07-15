<?php

/**
 * Visa service agreement DOCX templates (storage/app/templates).
 *
 * Resolution order is implemented in {@see \App\Services\VisaAgreementTemplateResolver}.
 *
 * Primary templates (current product): use these filenames when present.
 * Legacy_* / fallback_skill_template keys extend specialist chains when newer files are missing.
 */
return [
    /**
     * Default general service agreement (also the usual tail after specialist templates).
     * {@see \App\Http\Controllers\CRM\ClientsController::generateagreement} still falls back to
     * agreement_template.docx on disk when no candidate file exists.
     */
    'default' => 'Service_Agreement_general.docx',

    'skill_assessment' => 'Service_Agreement_Skill_Assessment.docx',

    /*
    | Skill assessment template: matter nick_name (exact, case-insensitive) or title substring.
    | Covers named assessing authorities (APC, AASW, Physiotherapy Council), Occupation Suggestion,
    | plus generic skill assessment.
    */
    'skill_assessment_matter_nick_names' => [
        'skillassessment',
        'skillassment',
        'apc',
        'aasw',
        'os',
    ],

    'skill_assessment_title_markers' => [
        'skill assessment',
        'australian pharmacy council',
        'australian association of social workers',
        'australian physiotherapy council',
        'occupation suggestion',
    ],

    'psa' => 'Service_Agreement_PSA.docx',

    'job_ready' => 'Service_Agreement_Job_Ready.docx',

    'subclass_408' => 'Service_Agreement_408.docx',

    'art' => 'Service_Agreement_ART.docx',

    /*
    | ART template: matter nick_name (exact, case-insensitive) or title substring.
    */
    'art_matter_nick_names' => ['art', 'employerart'],

    'art_matter_title_markers' => [
        'administrative review tribunal',
        'administrative appeals tribunal',
    ],

    'citizenship' => 'Service_Agreement_citizenship.docx',

    'eoi_roi' => 'Service_Agreement_EOI_ROI.docx',

    'parents' => 'Service_Agreement_parents.docx',

    'company_sponsorship' => 'Service_Agreement_company_sponsorship.docx',

    'company_nomination' => 'Service_Agreement_company_nomination.docx',

    /*
    | Company nomination template: only these company matters (nick_name or title marker).
    */
    'company_nomination_matter_nick_names' => ['de', 'sesr', 'sidcoreskills', 'tn 407'],

    'company_nomination_title_markers' => [
        'training nomination',
        'regional employer nomination',
        'employer nomination scheme',
        'skill in demand nomination - core skills',
    ],

    /*
    | Company sponsorship template: only these company matters (nick_name or title marker).
    */
    'company_sponsorship_matter_nick_names' => ['sbs', 'tas'],

    'company_sponsorship_title_markers' => [
        'standard business sponsorship',
        'temporary activities sponsorship',
    ],

    /*
    |----------------------------------------------------------------------
    | Legacy / fallback templates (tried after primary when files missing)
    |----------------------------------------------------------------------
    */
    /** @deprecated Renamed to skill_assessment; kept as fallback */
    'fallback_skill_template' => 'Service_Agreement_template_Skill_Assessment.docx',

    /** @deprecated Retained for backward compatibility */
    'legacy_skill_assessment' => 'agreement_template-skillassment.docx',

    /** @deprecated Retained for backward compatibility */
    'legacy_jrp' => 'agreement_template-JRP.docx',

    /** @deprecated Retained for backward compatibility */
    'legacy_art' => 'agreement_template-ART.docx',

    /*
    | Parent / contributory parent subclasses (parents template).
    | Includes sponsored parent temporary (870) alongside contributory / aged parent subclasses.
    */
    'parents_subclass_pattern' => '/\b(143|173|103|804|864|884|870)\b/',

    /*
    | Partner subclasses (conflict-of-interest routing uses default general template).
    */
    'partner_subclass_pattern' => '/\b(820|801|309|100)\b/',

    /*
    | Employer-sponsored subclasses (personal conflict-of-interest routing only).
    */
    'employer_subclass_pattern' => '/\b(407|186|482|494)\b/',

    /*
    | Matter title substring suggesting subclass 600 visitor / tourist.
    */
    'visitor_subclass_markers' => ['600 -', '600-', 'subclass 600', 'sc 600'],

    /*
    | Phrases indicating family-sponsored visitor stream (conflict-of-interest routing).
    */
    'family_sponsored_visitor_markers' => [
        'sponsored family',
        'family sponsored',
        'sponsored family stream',
        'family sponsor',
        'sponsoring family',
    ],
];
