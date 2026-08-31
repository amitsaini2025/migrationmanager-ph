<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\AppointmentConsultant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ConsultantAssignmentService
{
    /**
     * Assign consultant based on appointment details.
     *
     * Adelaide: education (NOE 5) or tourist (NOE 4) → Adelaide Education calendar; all other Adelaide bookings → Adelaide calendar.
     * Melbourne: calendar follows service line (tourist, education, Ajay, JRP, employer-sponsored, etc.).
     * GSM, EOI/ROI, TR 485, and JRP/Skill Assessment use free → JRP calendar, paid → Employer Sponsored calendar (all languages).
     */
    public function assignConsultant(array $appointmentData): ?AppointmentConsultant
    {
        $calendarType = $this->determineCalendarType($appointmentData);

        if (! $calendarType) {
            Log::warning('Could not determine calendar type for appointment', [
                'appointment_id' => $appointmentData['id'] ?? null,
                'noe_id' => $appointmentData['noe_id'] ?? null,
                'service_id' => $appointmentData['service_id'] ?? null,
                'location' => $appointmentData['location'] ?? null,
            ]);

            return null;
        }

        $consultant = AppointmentConsultant::where('calendar_type', $calendarType)
            ->where('is_active', true)
            ->first();

        if (! $consultant) {
            Log::error('No active consultant found for calendar type', [
                'calendar_type' => $calendarType,
            ]);
        }

        return $consultant;
    }

    /**
     * Determine calendar type based on appointment data.
     */
    protected function determineCalendarType(array $appointment): ?string
    {
        $location = $this->normalizeLocationString($appointment['location'] ?? null);
        $inpersonAddress = $appointment['inperson_address'] ?? null;
        $serviceId = $appointment['service_id'] ?? null;
        $noeId = $this->resolveNoeId($appointment);

        if ($this->isAdelaideOffice($location, $inpersonAddress)) {
            if (in_array($noeId, [4, 5], true)) {
                return 'adelaide_education';
            }

            return 'adelaide';
        }

        if (! $this->shouldUseMelbourneCalendars($location, $inpersonAddress)) {
            return null;
        }

        // Tourist
        if ($noeId === 4) {
            return 'tourist';
        }

        // Education (incl. student / dependent flows that map to NOE 5)
        if ($noeId === 5) {
            return 'education';
        }

        // CRM-only Nature of Enquiry: Ajay / Arun (Melbourne)
        if ($noeId === 13) {
            return 'ajay';
        }
        if ($noeId === 14) {
            return 'arun';
        }

        // Complex matters → Ajay
        if ($noeId === 6) {
            return 'ajay';
        }

        // Visa cancellation / refusals → Ajay
        if ($noeId === 7) {
            return 'ajay';
        }

        // Family visas / Citizenship → Employer Sponsored Calendar (Melbourne)
        if (in_array($noeId, [11, 12], true)) {
            return 'paid';
        }

        // Outside Australia / international migration → Employer Sponsored Calendar
        if ($noeId === 8) {
            return 'paid';
        }

        // Employer Sponsored (494, 482, 186, DAMA)
        if ($noeId === 10) {
            return 'paid';
        }

        // GSM, EOI/ROI, TR (485), JRP / Skill assessment → free: JRP; paid: Employer Sponsored (all languages)
        if (in_array($noeId, [1, 2, 3, 9], true)) {
            return $this->melbourneFreePaidCalendarOverride($appointment, $noeId) ?? 'jrp';
        }

        // Unknown NOE: classify from text (EOI/GSM/employer keywords), else employer-sponsored (legacy PR)
        if ($noeId === null) {
            return $this->melbournePrCalendarType($appointment, $serviceId);
        }

        return 'paid';
    }

    /**
     * Classify Melbourne calendar when `noe_id` is missing (sync/legacy): EOI/GSM → free/paid split, employer keywords → paid, else paid (old PR bucket).
     */
    protected function melbournePrCalendarType(array $appointment, $serviceId): string
    {
        $haystack = $this->buildSearchableText($appointment);

        // Outside Australia (paid overseas path)
        if (($appointment['specific_service'] ?? null) === 'overseas-enquiry') {
            return 'paid';
        }
        if ((int) $serviceId === 3) {
            return 'paid';
        }

        // EOI / ROI
        if (preg_match('/\beoi\b|expression\s+of\s+interest|\broi\b|points\s+table|skillselect/i', $haystack)) {
            return $this->melbourneFreePaidCalendarOverride($appointment, null, true) ?? 'jrp';
        }

        // GSM subclasses / general skilled
        if (preg_match('/\b(491|190|189|191)\b|gsm|general\s+skilled|skilled\s+nominated|subclass\s*(491|190|189|191)/i', $haystack)) {
            return $this->melbourneFreePaidCalendarOverride($appointment, null, true) ?? 'jrp';
        }

        // Employer Sponsored (494, 482, 186, 407, DAMA, etc.)
        if (preg_match('/\b(482|494|186|407)\b|dama|employer\s+sponsored|sponsored\s+visa|labour\s+agreement|tss\b|subclass\s*(482|494|186|407)/i', $haystack)) {
            return 'paid';
        }

        // Legacy PR / default → Employer Sponsored Calendar (replaces old PR calendar)
        return 'paid';
    }

    protected function buildSearchableText(array $appointment): string
    {
        $parts = [
            $appointment['enquiry_type'] ?? '',
            $appointment['service_type'] ?? '',
            $appointment['enquiry_details'] ?? '',
        ];

        return strtolower(implode(' ', array_filter($parts, fn ($p) => $p !== null && $p !== '')));
    }

    /**
     * Melbourne GSM, EOI/ROI, TR 485, JRP/Skill Assessment: free → JRP calendar; paid → Employer Sponsored calendar.
     * Applies to all languages (English, Hindi, Punjabi).
     *
     * @param  int|null  $noeId  Resolved NOE when already known by caller.
     * @param  bool  $legacyGsmOrEoiFromText  When true, treat missing NOE as GSM/EOI if matched from enquiry text only.
     */
    protected function melbourneFreePaidCalendarOverride(array $appointment, ?int $noeId = null, bool $legacyGsmOrEoiFromText = false): ?string
    {
        $resolvedNoe = $noeId ?? $this->resolveNoeId($appointment);
        $applies = in_array($resolvedNoe, [1, 2, 3, 9], true)
            || ($legacyGsmOrEoiFromText && $resolvedNoe === null);

        if (! $applies) {
            return null;
        }

        $bucket = $this->resolveFreeVsPaidBucket($appointment);
        if ($bucket === null) {
            return null;
        }

        return $bucket === 'free' ? 'jrp' : 'paid';
    }

    /**
     * @return 'free'|'paid'|null null = unknown; callers fall back to default calendar (e.g. jrp)
     */
    protected function resolveFreeVsPaidBucket(array $appointment): ?string
    {
        $serviceId = $appointment['service_id'] ?? null;
        if ($serviceId !== null && $serviceId !== '') {
            $sid = (int) $serviceId;
            if ($sid === 2) {
                return 'free';
            }
            if ($sid === 1 || $sid === 3) {
                return 'paid';
            }
        }

        $specific = $appointment['specific_service'] ?? null;
        if ($specific !== null && $specific !== '') {
            if ($specific === 'consultation') {
                return 'free';
            }
            if (in_array($specific, ['paid-consultation', 'overseas-enquiry'], true)) {
                return 'paid';
            }
        }

        if (array_key_exists('is_paid', $appointment)) {
            $isPaid = $appointment['is_paid'];
            if ($isPaid === true || $isPaid === 1 || $isPaid === '1') {
                return 'paid';
            }
            if ($isPaid === false || $isPaid === 0 || $isPaid === '0') {
                return 'free';
            }
        }

        return null;
    }

    /**
     * Resolve NOE when only Bansal slug / display service_type is present.
     */
    protected function resolveNoeId(array $appointment): ?int
    {
        $raw = $appointment['noe_id'] ?? null;
        if ($raw !== null && $raw !== '') {
            return (int) $raw;
        }

        $slug = $appointment['service_type'] ?? null;
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $s = strtolower(trim($slug));

        $fromSlug = match ($s) {
            'permanent-residency' => 1,
            'gsm-visas' => 1,
            'temporary-residency' => 2,
            'jrp-skill-assessment' => 3,
            'tourist-visa' => 4,
            'education-visa' => 5,
            'complex-matters' => 6,
            'visa-cancellation' => 7,
            'international-migration' => 8,
            'eoi-roi' => 9,
            'employer-sponsored' => 10,
            'family-visas' => 11,
            'citizenship' => 12,
            'ajay' => 13,
            'arun' => 14,
            default => null,
        };

        if ($fromSlug !== null) {
            return $fromSlug;
        }

        // Display-style labels from CRM / API
        return match (true) {
            str_contains($s, 'permanent') && str_contains($s, 'residency') => 1,
            str_contains($s, 'gsm') || (str_contains($s, '491') && str_contains($s, '190')) => 1,
            str_contains($s, 'temporary') && str_contains($s, 'residency') => 2,
            str_contains($s, 'jrp') || str_contains($s, 'skill assessment') => 3,
            str_contains($s, 'tourist') => 4,
            str_contains($s, 'education') || str_contains($s, 'student') => 5,
            str_contains($s, 'complex') || str_contains($s, 'aat') || str_contains($s, 'art,') || str_contains($s, 'protection') || str_contains($s, 'federal case') => 6,
            str_contains($s, 'cancellation') || str_contains($s, 'refusal') || str_contains($s, 'noicc') => 7,
            str_contains($s, 'outside australia') || str_contains($s, 'india') || str_contains($s, 'international') || str_contains($s, 'europe') || str_contains($s, 'canada') => 8,
            (str_contains($s, 'eoi') || str_contains($s, 'roi')) && ! str_contains($s, 'employer') => 9,
            str_contains($s, 'employer sponsored') || str_contains($s, '494') || str_contains($s, '482') || str_contains($s, '186') || str_contains($s, 'dama') => 10,
            default => null,
        };
    }

    protected function normalizeLocationString(?string $location): ?string
    {
        if ($location === null || $location === '') {
            return null;
        }

        return strtolower(trim($location));
    }

    protected function isAdelaideOffice(?string $location, $inpersonAddress): bool
    {
        if ($inpersonAddress === 1 || $inpersonAddress === '1') {
            return true;
        }

        return $location === 'adelaide';
    }

    /**
     * Melbourne office + legacy default when office is unspecified (empty inperson).
     */
    protected function shouldUseMelbourneCalendars(?string $location, $inpersonAddress): bool
    {
        if ($this->isAdelaideOffice($location, $inpersonAddress)) {
            return false;
        }

        if ($location === 'melbourne') {
            return true;
        }

        if ($inpersonAddress === 2 || $inpersonAddress === '2') {
            return true;
        }

        $inpersonEmpty = $inpersonAddress === null || $inpersonAddress === '';

        return $inpersonEmpty;
    }

    /**
     * Get consultant by calendar type
     */
    public function getConsultantByCalendarType(string $calendarType): ?AppointmentConsultant
    {
        return AppointmentConsultant::where('calendar_type', $calendarType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active consultants
     */
    public function getAllConsultants(): Collection
    {
        return AppointmentConsultant::where('is_active', true)->get();
    }
}
