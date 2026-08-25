<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\ClientMatter;
use App\Models\CostAssignmentForm;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Loads Checklists tab queries outside the Blade view.
 */
final class ClientDetailChecklistsTab
{
    /**
     * @return array{
     *     checklistCurrentMatterId: int|null,
     *     checklistCurrentMatterRef: string|null,
     *     checklistCurrentMatterNeedsCostAssignment: bool,
     *     checklistMigrationAgents: Collection,
     *     checklistPersonsResponsible: Collection,
     *     checklistPersonsAssisting: Collection,
     *     checklistOffices: Collection,
     *     checklistAuthOfficeId: int|string|null,
     *     checklistMatterList: Collection,
     *     checklistForms: Collection
     * }
     */
    public static function build(object $client, ?string $matterRefNo = null): array
    {
        $clientId = (int) ($client->id ?? 0);
        $currentMatter = self::resolveCurrentMatter($clientId, $matterRefNo);
        $currentMatterId = $currentMatter?->id;
        $needsCostAssignment = false;

        if ($currentMatterId !== null) {
            $needsCostAssignment = ! CostAssignmentForm::query()
                ->where('client_id', $clientId)
                ->where('client_matter_id', $currentMatterId)
                ->exists();
        }

        $forms = CostAssignmentForm::query()
            ->where('client_id', $clientId)
            ->whereHas('clientMatter', function ($query) {
                $query->where('matter_status', 1);
            })
            ->with([
                'client',
                'agent',
                'clientMatter.matter',
                'clientMatter.migrationAgent',
                'clientMatter.personResponsible',
                'clientMatter.personAssisting',
                'clientMatter.office',
            ])
            ->orderByDesc('created_at')
            ->get();

        self::attachAgreementDocuments($forms);

        return [
            'checklistCurrentMatterId' => $currentMatterId,
            'checklistCurrentMatterRef' => $currentMatter ? (string) ($matterRefNo ?? '') : null,
            'checklistCurrentMatterNeedsCostAssignment' => $needsCostAssignment,
            'checklistMigrationAgents' => Staff::assignmentDropdownMigrationAgentsQuery()->get(),
            'checklistPersonsResponsible' => Staff::assignmentDropdownPersonResponsibleQuery()->get(),
            'checklistPersonsAssisting' => Staff::assignmentDropdownPersonAssistingQuery()->get(),
            'checklistOffices' => Branch::query()->orderBy('office_name')->get(),
            'checklistAuthOfficeId' => Auth::user()?->office_id ?? null,
            'checklistMatterList' => Matter::query()
                ->select('id', 'title')
                ->where('status', 1)
                ->forClientType((bool) ($client->is_company ?? false))
                ->get(),
            'checklistForms' => $forms,
        ];
    }

    private static function resolveCurrentMatter(int $clientId, ?string $matterRefNo): ?ClientMatter
    {
        if ($matterRefNo === null || $matterRefNo === '' || ClientDetailTabs::isKnownSlug($matterRefNo)) {
            return null;
        }

        return ClientMatter::query()
            ->where('client_id', $clientId)
            ->where('client_unique_matter_no', $matterRefNo)
            ->where('matter_status', 1)
            ->first();
    }

    private static function attachAgreementDocuments(Collection $forms): void
    {
        $matterIds = $forms->pluck('client_matter_id')->filter()->unique()->values();
        if ($matterIds->isEmpty()) {
            foreach ($forms as $form) {
                $form->setAttribute('agreement_document', null);
            }

            return;
        }

        $docsByMatter = Document::query()
            ->whereIn('client_matter_id', $matterIds->all())
            ->where('doc_type', 'agreement')
            ->orderByDesc('id')
            ->get()
            ->groupBy('client_matter_id');

        foreach ($forms as $form) {
            $group = $docsByMatter->get($form->client_matter_id);
            $form->setAttribute('agreement_document', $group?->first());
        }
    }
}
