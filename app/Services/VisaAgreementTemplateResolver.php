<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientOccupation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chooses ordered DOCX template candidates for visa service agreement generation.
 */
class VisaAgreementTemplateResolver
{
    public function resolve(Admin $client, ?string $clientMatterId): array
    {
        $default = config('visa_agreement_templates.default', 'Service_Agreement_general.docx');

        if ($clientMatterId === null || $clientMatterId === '') {
            return [
                'candidates' => [$default],
                'matter_nick_name' => null,
                'rule' => 'no_matter',
            ];
        }

        $row = DB::table('client_matters')
            ->join('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
            ->where('client_matters.id', $clientMatterId)
            ->where('client_matters.client_id', $client->id)
            ->select('matters.nick_name', 'matters.title')
            ->first();

        if (!$row) {
            return [
                'candidates' => [$default],
                'matter_nick_name' => null,
                'rule' => 'matter_not_found',
            ];
        }

        $nick = $row->nick_name;
        $title = (string) ($row->title ?? '');

        $hasVetassess = ClientOccupation::query()
            ->where('client_id', $client->id)
            ->where(function ($q): void {
                $q->where('list', 'ilike', '%vetassess%')
                    ->orWhere('skill_assessment', 'ilike', '%vetassess%');
            })
            ->exists();

        $decision = $this->determineCandidates($client->isCompany(), $nick, $title, $hasVetassess);

        return [
            'candidates' => $decision['candidates'],
            'matter_nick_name' => $nick !== null && $nick !== ''
                ? strtolower(trim((string) $nick))
                : null,
            'rule' => $decision['rule'],
        ];
    }

    /**
     * @return array{candidates: list<string>, rule: string}
     */
    public function determineCandidates(
        bool $isCompany,
        ?string $matterNickName,
        string $matterTitle,
        bool $hasVetassessOccupation
    ): array {
        $cfg = config('visa_agreement_templates', []);
        $nick = $matterNickName !== null ? strtolower(trim((string) $matterNickName)) : '';
        $titleLower = Str::lower($matterTitle);

        if ($isCompany) {
            return $this->resolveCompanyBranch($nick, $titleLower, $cfg);
        }

        if ($this->matchesParentsSubclass($titleLower, $cfg)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [$cfg['parents'] ?? ''],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'parents',
            ];
        }

        if ($this->matchesEoiOrRoi($nick, $titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [$cfg['eoi_roi'] ?? ''],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'eoi_roi',
            ];
        }

        if ($this->matchesCitizenship($titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [$cfg['citizenship'] ?? ''],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'citizenship',
            ];
        }

        if ($this->matchesArtMatter($nick, $titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [
                        $cfg['art'] ?? '',
                        $cfg['legacy_art'] ?? '',
                    ],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'art',
            ];
        }

        if ($this->matchesSubclass408($titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [$cfg['subclass_408'] ?? ''],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'subclass_408',
            ];
        }

        if ($this->matchesProvisionalSkillsAssessment($nick, $titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [$cfg['psa'] ?? ''],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'psa',
            ];
        }

        // Job Ready must win over client-wide VETASSESS occupation so JRP matters
        // always use Service_Agreement_Job_Ready.docx regardless of occupation data.
        if ($this->matchesJobReadyMatter($nick, $titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [
                        $cfg['job_ready'] ?? '',
                        $cfg['legacy_jrp'] ?? '',
                    ],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'job_ready',
            ];
        }

        if ($this->matchesSkillAssessmentOnly($nick, $titleLower, $hasVetassessOccupation)) {
            return [
                'candidates' => $this->uniqueFilenames(array_merge(
                    [
                        $cfg['skill_assessment'] ?? '',
                        $cfg['fallback_skill_template'] ?? '',
                        $cfg['legacy_skill_assessment'] ?? '',
                        $cfg['legacy_jrp'] ?? '',
                    ],
                    $this->standardNonCompanyTail($cfg)
                )),
                'rule' => 'skill_assessment',
            ];
        }

        if ($this->matchesConflictOfInterests($nick, $titleLower)) {
            return [
                'candidates' => $this->uniqueFilenames([$cfg['default'] ?? '']),
                'rule' => 'conflict_of_interests_visa',
            ];
        }

        return [
            'candidates' => $this->uniqueFilenames($this->standardNonCompanyTail($cfg)),
            'rule' => 'general',
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    private function standardNonCompanyTail(array $cfg): array
    {
        return [
            $cfg['default'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array{candidates: list<string>, rule: string}
     */
    private function resolveCompanyBranch(string $nick, string $titleLower, array $cfg): array
    {
        $nom = $this->matchesCompanyNominationHint($nick, $titleLower);
        $spon = $this->matchesCompanySponsorshipHint($nick, $titleLower);

        $nomFile = $cfg['company_nomination'] ?? '';
        $sponFile = $cfg['company_sponsorship'] ?? '';
        $defaultGeneral = $cfg['default'] ?? '';

        if ($nom && ! $spon) {
            $ordered = [$nomFile, $sponFile, $defaultGeneral];
            $rule = 'company_nomination';
        } elseif ($spon && ! $nom) {
            $ordered = [$sponFile, $nomFile, $defaultGeneral];
            $rule = 'company_sponsorship';
        } elseif ($nom && $spon) {
            $ordered = [$nomFile, $sponFile, $defaultGeneral];
            $rule = 'company_nomination_and_sponsorship_hint';
        } elseif ($this->matchesArtMatter($nick, $titleLower)) {
            $ordered = array_merge(
                [
                    $cfg['art'] ?? '',
                    $cfg['legacy_art'] ?? '',
                ],
                [$defaultGeneral]
            );
            $rule = 'company_art';
        } else {
            $ordered = [$defaultGeneral];
            $rule = 'company_general';
        }

        return [
            'candidates' => $this->uniqueFilenames($ordered),
            'rule' => $rule,
        ];
    }

    private function matchesCompanyNominationHint(string $nick, string $titleLower): bool
    {
        foreach (config('visa_agreement_templates.company_nomination_matter_nick_names', []) as $n) {
            if ($nick === strtolower(trim((string) $n))) {
                return true;
            }
        }

        foreach (config('visa_agreement_templates.company_nomination_title_markers', []) as $marker) {
            if (str_contains($titleLower, strtolower(trim((string) $marker)))) {
                return true;
            }
        }

        return false;
    }

    private function matchesCompanySponsorshipHint(string $nick, string $titleLower): bool
    {
        foreach (config('visa_agreement_templates.company_sponsorship_matter_nick_names', ['sbs', 'tas']) as $n) {
            if ($nick === strtolower(trim((string) $n))) {
                return true;
            }
        }

        foreach (config('visa_agreement_templates.company_sponsorship_title_markers', []) as $marker) {
            if (str_contains($titleLower, strtolower(trim((string) $marker)))) {
                return true;
            }
        }

        return false;
    }

    private function matchesParentsSubclass(string $titleLower, array $cfg): bool
    {
        $pattern = $cfg['parents_subclass_pattern'] ?? '/\b(143|173|103|804|864|884)\b/';

        return (bool) @preg_match($pattern, $titleLower);
    }

    private function matchesEoiOrRoi(string $nick, string $titleLower): bool
    {
        if ($nick === 'eoi') {
            return true;
        }

        $hasEoi = str_contains($titleLower, 'eoi')
            || str_contains($titleLower, 'expression of interest');
        $hasRoi = str_contains($titleLower, 'roi')
            || str_contains($titleLower, 'registration of interest');

        return $hasEoi || $hasRoi;
    }

    private function matchesCitizenship(string $titleLower): bool
    {
        return str_contains($titleLower, 'citizenship');
    }

    private function matchesArtMatter(string $nick, string $titleLower): bool
    {
        foreach (config('visa_agreement_templates.art_matter_nick_names', ['art']) as $n) {
            if ($nick === strtolower(trim((string) $n))) {
                return true;
            }
        }

        foreach (config('visa_agreement_templates.art_matter_title_markers', []) as $marker) {
            if (str_contains($titleLower, strtolower(trim((string) $marker)))) {
                return true;
            }
        }

        return (bool) @preg_match('/\bart\b/i', $titleLower);
    }

    private function matchesSubclass408(string $titleLower): bool
    {
        return (bool) @preg_match('/\b408\b/', $titleLower);
    }

    private function matchesProvisionalSkillsAssessment(string $nick, string $titleLower): bool
    {
        if ($nick === 'psa') {
            return true;
        }

        return str_contains($titleLower, 'provisional skills assessment');
    }

    private function matchesSkillAssessmentOnly(string $nick, string $titleLower, bool $hasVetassessOccupation): bool
    {
        if ($hasVetassessOccupation) {
            return true;
        }

        foreach (config('visa_agreement_templates.skill_assessment_matter_nick_names', [
            'skillassessment',
            'skillassment',
        ]) as $n) {
            if ($nick === strtolower(trim((string) $n))) {
                return true;
            }
        }

        foreach (config('visa_agreement_templates.skill_assessment_title_markers', [
            'skill assessment',
        ]) as $marker) {
            if (str_contains($titleLower, strtolower(trim((string) $marker)))) {
                return true;
            }
        }

        return str_contains($titleLower, 'vetassess');
    }

    private function matchesJobReadyMatter(string $nick, string $titleLower): bool
    {
        if ($nick === 'jrp') {
            return true;
        }

        return str_contains($titleLower, 'job ready')
            || str_contains($titleLower, 'jobready')
            || str_contains($titleLower, 'job-ready');
    }

    private function matchesConflictOfInterests(string $nick, string $titleLower): bool
    {
        $partnerPattern = config('visa_agreement_templates.partner_subclass_pattern', '/\b(820|801|309|100)\b/');
        if (@preg_match($partnerPattern, $titleLower)) {
            return true;
        }

        $employerPattern = config('visa_agreement_templates.employer_subclass_pattern', '/\b(407|186|482|494)\b/');
        if (@preg_match($employerPattern, $titleLower)) {
            return true;
        }

        return $this->isFamilySponsoredVisitorMatter($nick, $titleLower);
    }

    private function isFamilySponsoredVisitorMatter(string $nick, string $titleLower): bool
    {
        $visitorNicks = config('sheets.visa_types.visitor.matter_nick_names', []);
        foreach ($visitorNicks as $vn) {
            if ($nick === strtolower(trim((string) $vn))) {
                return $this->titleSuggestsFamilySponsoredVisitor($titleLower);
            }
        }

        foreach (config('visa_agreement_templates.visitor_subclass_markers', []) as $marker) {
            if (str_contains($titleLower, strtolower((string) $marker))) {
                return $this->titleSuggestsFamilySponsoredVisitor($titleLower);
            }
        }

        return false;
    }

    private function titleSuggestsFamilySponsoredVisitor(string $titleLower): bool
    {
        foreach (config('visa_agreement_templates.family_sponsored_visitor_markers', []) as $phrase) {
            if (str_contains($titleLower, strtolower((string) $phrase))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function uniqueFilenames(array $names): array
    {
        $out = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n === '') {
                continue;
            }
            if (! in_array($n, $out, true)) {
                $out[] = $n;
            }
        }

        return $out !== [] ? $out : ['agreement_template.docx'];
    }
}
