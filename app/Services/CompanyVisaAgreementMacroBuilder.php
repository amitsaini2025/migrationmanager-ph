<?php

namespace App\Services;

use App\Http\Controllers\CRM\ClientsController;
use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\Company;
use App\Models\CompanyDirector;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds PhpWord merge keys for company nomination / sponsorship service agreement templates.
 */
class CompanyVisaAgreementMacroBuilder
{
    /**
     * @return array<string, string>
     */
    public function build(Admin $client, ?object $costRow = null): array
    {
        if (! $client->isCompany()) {
            return [];
        }

        $company = $client->company;
        if ($company === null) {
            $company = Company::query()->where('admin_id', $client->id)->first();
        }

        if ($company !== null && ! $company->relationLoaded('directors')) {
            $company->load(['directors.directorClient', 'contactPerson']);
        }

        $address = $this->resolveCurrentAddress((int) $client->id);
        $director = $this->resolvePrimaryDirector($company);
        $directorNames = $this->resolveDirectorNames($director);
        $surchargeFlag = $costRow->surcharge ?? null;

        $safLevy = floatval($costRow->saf_levy ?? 0);
        $nominationCharge = floatval($costRow->Dept_Nomination_Application_Charge ?? 0);
        $sponsorshipCharge = floatval($costRow->Dept_Sponsorship_Application_Charge ?? 0);

        return [
            'CompanyName' => trim((string) ($company?->company_name ?? '')),
            'CompanyABN' => trim((string) ($company?->ABN_number ?? '')),
            'DirectorGivenname' => $directorNames['given'],
            'DirectorSurname' => $directorNames['surname'],
            'DirectorDOB' => $this->formatDirectorDob($director),
            'BusinessAddressStreet1and2' => $address['street'],
            'BusinessAddressSuburbPostcodeState' => $address['suburbLine'],
            'PrimaryContact_ContactMobile' => trim((string) ($company?->contactPerson?->phone ?? '')),
            'PrimaryContact_ContactEmail' => trim((string) ($company?->contactPerson?->email ?? '')),
            'SAFLevycharge' => $this->formatMoney($safLevy),
            'SAFLevysurcharge' => $this->formatMoney($this->resolveAmountInclSurcharge($safLevy, null, $surchargeFlag)),
            'DoHANominationApplicantCharge' => $this->formatMoney($nominationCharge),
            'DoHANominationApplicantSurcharge' => $this->formatMoney($this->resolveAmountInclSurcharge($nominationCharge, null, $surchargeFlag)),
            'DoHASponsorshipApplicantCharge' => $this->formatMoney($sponsorshipCharge),
            'DoHASponsorshipApplicantSurcharge' => $this->formatMoney($this->resolveAmountInclSurcharge($sponsorshipCharge, null, $surchargeFlag)),
        ];
    }

    /**
     * @return array{street: string, suburbLine: string}
     */
    private function resolveCurrentAddress(int $clientId): array
    {
        $row = DB::table('client_addresses')
            ->where('client_id', $clientId)
            ->where('is_current', 1)
            ->first();

        if ($row === null) {
            $row = DB::table('client_addresses')
                ->where('client_id', $clientId)
                ->orderByRaw(ClientAddress::ORDER_BY_DISPLAY_SQL)
                ->orderByDesc('id')
                ->first();
        }

        if ($row === null) {
            return ['street' => '', 'suburbLine' => ''];
        }

        $line1 = trim((string) ($row->address_line_1 ?? ''));
        $line2 = trim((string) ($row->address_line_2 ?? ''));
        $legacy = trim((string) ($row->address ?? ''));

        if ($line1 !== '' || $line2 !== '') {
            $street = trim($line1.($line2 !== '' ? ', '.$line2 : ''));
        } else {
            $street = $legacy;
        }

        $parts = array_filter([
            trim((string) ($row->suburb ?? '')),
            trim((string) ($row->zip ?? '')),
            trim((string) ($row->state ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return [
            'street' => $street,
            'suburbLine' => implode(' ', $parts),
        ];
    }

    private function resolvePrimaryDirector(?Company $company): ?CompanyDirector
    {
        if ($company === null) {
            return null;
        }

        $directors = $company->directors;
        if ($directors->isEmpty()) {
            return null;
        }

        $primary = $directors->firstWhere('is_primary', true);

        return $primary ?? $directors->first();
    }

    /**
     * @return array{given: string, surname: string}
     */
    private function resolveDirectorNames(?CompanyDirector $director): array
    {
        if ($director === null) {
            return ['given' => '', 'surname' => ''];
        }

        if ($director->directorClient) {
            return [
                'given' => trim((string) $director->directorClient->first_name),
                'surname' => trim((string) $director->directorClient->last_name),
            ];
        }

        $name = trim((string) ($director->director_name ?? ''));
        if ($name === '') {
            return ['given' => '', 'surname' => ''];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [];

        return [
            'given' => $parts[0] ?? '',
            'surname' => $parts[1] ?? '',
        ];
    }

    private function formatDirectorDob(?CompanyDirector $director): string
    {
        if ($director === null) {
            return '';
        }

        $dob = $director->director_dob;
        if ($dob === null && $director->directorClient?->dob) {
            try {
                $dob = Carbon::parse($director->directorClient->dob);
            } catch (\Throwable) {
                return '';
            }
        }

        if ($dob === null) {
            return '';
        }

        if ($dob instanceof \DateTimeInterface) {
            return $dob->format('d/m/Y');
        }

        try {
            return Carbon::parse($dob)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Same rule as {@see ClientsController::resolveDoHaAmountInclSurcharge}.
     */
    private function resolveAmountInclSurcharge(float $base, $storedInclSurcharge, $surchargeFlag): float
    {
        if ($base <= 0) {
            return floatval($storedInclSurcharge ?? 0);
        }

        $stored = floatval($storedInclSurcharge ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        if (is_string($surchargeFlag) && trim($surchargeFlag) === 'Yes') {
            return round($base * 0.014, 2) + $base;
        }

        return $base;
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Section 4 "Total DOHA charges Inc Surcharges" for all service agreement templates.
     */
    public static function calculateDohaChargesInclSurcharges(float $totalDoHACharges, float $totalDoHASurcharges): string
    {
        return number_format($totalDoHACharges + $totalDoHASurcharges, 2, '.', '');
    }

    /**
     * First email / checklist compose fee macros — same mapping as agreement Section 4:
     * TotalDoHASurcharges = DoHA charges incl. surcharge; TotalEstimatedOthCosts = additional_fee_1.
     *
     * @return array{Blocktotalfeesincltax: string, TotalDoHASurcharges: string, TotalEstimatedOthCosts: string, DiscountAmount: string, GrandTotalFeesAndCosts: string}
     */
    public static function buildFirstEmailComposeFeeMacros(
        float $blockTotalFeesInclTax,
        float $totalDoHACharges,
        float $totalDoHASurchargesOnly,
        float $additionalFee1,
        float $discount = 0.0
    ): array {
        $dohaInclSurcharge = self::calculateDohaChargesInclSurcharges(
            $totalDoHACharges,
            $totalDoHASurchargesOnly
        );
        $appliedDiscount = max(0.0, $discount);
        $gross = $blockTotalFeesInclTax
            + floatval($dohaInclSurcharge)
            + $additionalFee1;
        if ($appliedDiscount > $gross) {
            $appliedDiscount = $gross;
        }
        $grandTotal = $gross - $appliedDiscount;

        return [
            'Blocktotalfeesincltax' => number_format($blockTotalFeesInclTax, 2, '.', ''),
            'TotalDoHASurcharges' => $dohaInclSurcharge,
            'TotalEstimatedOthCosts' => number_format($additionalFee1, 2, '.', ''),
            'DiscountAmount' => number_format($appliedDiscount, 2, '.', ''),
            'GrandTotalFeesAndCosts' => number_format($grandTotal, 2, '.', ''),
        ];
    }

    /**
     * Section 4 grand total for company nomination / sponsorship templates.
     *
     * Company templates merge TotalDoHASurcharges in the DoHA row (not TotalDoHAChargesInclSurcharge),
     * so the grand total must sum the same three section-4 values shown in the document.
     */
    public static function calculateGrandTotalFeesAndCosts(
        float $blockTotalFeesInclTax,
        string $section4DohaChargesInclSurcharges,
        float $totalEstimatedOtherCosts
    ): string {
        $total = $blockTotalFeesInclTax
            + floatval($section4DohaChargesInclSurcharges)
            + $totalEstimatedOtherCosts;

        return number_format($total, 2, '.', '');
    }
}
