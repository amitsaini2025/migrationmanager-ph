<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Maps CRM "nature of enquiry" (noe_id / enquiry_item) to Bansal schedule API service_type
 * and builds Melbourne-only extras (is_paid, preferred_language) for get-datetime-backend
 * and get-disabled-datetime. Adelaide uses no extras so payloads stay unchanged for legacy behaviour.
 * Melbourne Family Visas (11) and Citizenship (12) use employer-sponsored timeslots on the
 * schedule API. add-appointment / re-sync use Bansal-valid enquiry_type slugs and service_type
 * slugs; CRM keeps display labels locally.
 */
class BansalSchedulingServiceType
{
    /** NOE ids using Melbourne employer-sponsored timeslots (schedule API only). */
    private const FAMILY_VISA_AND_CITIZENSHIP_NOE_IDS = [11, 12];

    /** enquiry_type values accepted by Bansal add-appointment API. */
    private const BANSAL_VALID_ENQUIRY_TYPES = ['tr', 'tourist', 'education', 'pr_complex', 'ajay', 'kunal'];

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
     */
    public static function bansalEnquiryTypeForApi(mixed $noeId, ?string $location, string $crmEnquiryType): string
    {
        $key = (int) $noeId;
        $loc = $location !== null ? strtolower(trim($location)) : '';

        $directByNoe = [
            2 => 'tr',
            4 => 'tourist',
            5 => 'education',
        ];
        if (isset($directByNoe[$key])) {
            return $directByNoe[$key];
        }

        if ($loc === 'melbourne') {
            if (in_array($key, [1, 3, 8, 9, 10, 11, 12], true)) {
                return 'pr_complex';
            }
            if (in_array($key, [6, 7], true)) {
                return 'ajay';
            }
        }

        if ($loc === 'adelaide') {
            if (in_array($key, [1, 3, 6, 7, 8, 9, 10, 11, 12], true)) {
                return 'ajay';
            }
        }

        $crm = strtolower(trim($crmEnquiryType));
        if (in_array($crm, self::BANSAL_VALID_ENQUIRY_TYPES, true)) {
            return $crm;
        }

        return $loc === 'adelaide' ? 'ajay' : 'pr_complex';
    }

    /**
     * service_type slug for Bansal add-appointment / re-sync API.
     */
    public static function bansalServiceTypeForApi(mixed $noeId, string $crmServiceType): string
    {
        $key = (int) $noeId;

        if (isset(self::ENQUIRY_TO_SERVICE_TYPE[$key])) {
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

    /**
     * Same Melbourne extras as melbourneApiExtras(), without an HTTP request.
     *
     * @return array{0: bool|null, 1: string|null}
     */
    public static function melbourneApiExtrasFromValues(string $location, bool $isPaid, ?string $preferredLanguage): array
    {
        if (strtolower(trim($location)) !== 'melbourne') {
            return [null, null];
        }

        $lang = trim((string) $preferredLanguage);
        if ($lang === '') {
            $lang = 'English';
        }

        return [$isPaid, $lang];
    }
}
