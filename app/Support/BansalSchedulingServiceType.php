<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Maps CRM "nature of enquiry" (noe_id / enquiry_item) to Bansal schedule API service_type
 * and builds Melbourne-only extras (is_paid, preferred_language) for get-datetime-backend
 * and get-disabled-datetime. Adelaide uses no extras so payloads stay unchanged for legacy behaviour.
 * Melbourne Family Visas (11) and Citizenship (12) use employer-sponsored timeslots on the
 * schedule API. Outside Australia (8), Employer Sponsored (10), Family Visas (11), and Citizenship
 * (12) use Bansal-valid enquiry_type (pr_complex / ajay) and service_type slugs on add-appointment
 * sync; CRM keeps display labels locally.
 */
class BansalSchedulingServiceType
{
    /** NOE ids using Melbourne employer-sponsored timeslots (schedule API only). */
    private const FAMILY_VISA_AND_CITIZENSHIP_NOE_IDS = [11, 12];

    /** NOE ids whose CRM enquiry_type must be remapped for Bansal add-appointment / re-sync API. */
    private const BANSAL_SYNC_ENQUIRY_REMAP_NOE_IDS = [8, 10, 11, 12];
    /**
     * @var array<int, string>
     */
    public const ENQUIRY_TO_SERVICE_TYPE = [
        1 => 'permanent-residency',
        2 => 'temporary-residency',
        3 => 'jrp-skill-assessment',
        4 => 'tourist-visa',
        5 => 'education-visa',
        6 => 'complex-matters',
        7 => 'visa-cancellation',
        8 => 'international-migration',
        9 => 'eoi-roi',
        10 => 'employer-sponsored',
        11 => 'family-visas',
        12 => 'citizenship',
    ];

    public static function fromEnquiryItem(mixed $enquiryItem, ?string $location = null): string
    {
        $key = (int) $enquiryItem;

        if (self::melbourneUsesEmployerSponsoredRouting($key, $location)) {
            return 'employer-sponsored';
        }

        return self::ENQUIRY_TO_SERVICE_TYPE[$key] ?? 'permanent-residency';
    }

    /**
     * enquiry_type for Bansal add-appointment / re-sync API only.
     * Bansal accepts: tr, tourist, education, pr_complex, ajay, kunal.
     * Outside Australia / Employer Sponsored / Family Visas / Citizenship: Melbourne → pr_complex, Adelaide → ajay.
     */
    public static function bansalEnquiryTypeForApi(mixed $noeId, ?string $location, string $crmEnquiryType): string
    {
        $key = (int) $noeId;
        if (! in_array($key, self::BANSAL_SYNC_ENQUIRY_REMAP_NOE_IDS, true)) {
            return $crmEnquiryType;
        }

        $loc = $location !== null ? strtolower(trim($location)) : '';

        return match ($loc) {
            'melbourne' => 'pr_complex',
            'adelaide' => 'ajay',
            default => $crmEnquiryType,
        };
    }

    /**
     * service_type slug for Bansal add-appointment / re-sync API (Outside Australia, Employer Sponsored, Family Visas, Citizenship).
     */
    public static function bansalServiceTypeForApi(mixed $noeId, string $crmServiceType): string
    {
        $key = (int) $noeId;

        if (in_array($key, self::BANSAL_SYNC_ENQUIRY_REMAP_NOE_IDS, true)) {
            return self::ENQUIRY_TO_SERVICE_TYPE[$key];
        }

        return $crmServiceType;
    }

    public static function melbourneUsesEmployerSponsoredRouting(int $noeId, ?string $location): bool
    {
        if ($location === null || $location === '') {
            return false;
        }

        return strtolower(trim($location)) === 'melbourne'
            && in_array($noeId, self::FAMILY_VISA_AND_CITIZENSHIP_NOE_IDS, true);
    }

    /**
     * @return array{0: bool|null, 1: string|null} is_paid and preferred_language; nulls mean omit (non-Melbourne)
     */
    public static function melbourneApiExtras(Request $request, string $location, int $formServiceId): array
    {
        if ($location !== 'melbourne') {
            return [null, null];
        }
        $isPaid = $request->has('is_paid')
            ? $request->boolean('is_paid')
            : in_array($formServiceId, [2, 3], true);
        $lang = trim((string) $request->input('preferred_language', ''));
        if ($lang === '') {
            $lang = 'English';
        }

        return [$isPaid, $lang];
    }
}
