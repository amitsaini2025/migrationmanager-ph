<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\ClientMatter;
use App\Models\CostAssignmentForm;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Staff;
use Illuminate\Support\Collection;

/**
 * Loads Checklists tab data outside the Blade view (staff dropdowns, forms, matter context).
 */
final class ClientDetailChecklistsTab
{
    /**
     * @return array{
     *     checklistCurrentMatterId: int|null,
     *     checklistCurrentMatterRef: string|null,
     *     checklistCurrentMatterNeedsCostAssignment: bool,
     *     checklist_forms: Collection,
     *     checklistMigrationAgents: Collection,
     *     checklistPersonResponsibleStaff: Collection,
     *     checklistPersonAssistingStaff: Collection,
     *     checklistOffices: Collection,
     *     checklistMatterList: Collection
     * }
     */
    public static function build(object $client, ?string $matterRefNo = null): array
    {
        $clientId = (int) ($client->id ?? 0);
        $isMatterIdInUrl = $matterRefNo !== null
            && $matterRefNo !== ''
            && ! ClientDetailTabs::isKnownSlug($matterRefNo);

        $checklistCurrentMatterId = null;
        $checklistCurrentMatterRef = null;
        $checklistCurrentMatterNeedsCostAssignment = false;

        if ($isMatterIdInUrl) {
            $checklistCurrentMatter = ClientMatter::query()
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->where('matter_status', 1)
                ->first();
            if ($checklistCurrentMatter) {
                $checklistCurrentMatterId = (int) $checklistCurrentMatter->id;
                $checklistCurrentMatterRef = $matterRefNo;
                $checklistCurrentMatterNeedsCostAssignment = ! CostAssignmentForm::query()
                    ->where('client_id', $clientId)
                    ->where('client_matter_id', $checklistCurrentMatterId)
                    ->exists();
            }
        }

        $checklistForms = CostAssignmentForm::query()
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
            ->orderBy('created_at', 'DESC')
            ->get();

        self::attachAgreementDocs($checklistForms);

        return [
            'checklistCurrentMatterId' => $checklistCurrentMatterId,
            'checklistCurrentMatterRef' => $checklistCurrentMatterRef,
            'checklistCurrentMatterNeedsCostAssignment' => $checklistCurrentMatterNeedsCostAssignment,
            'checklist_forms' => $checklistForms,
            'checklistMigrationAgents' => Staff::assignmentDropdownMigrationAgentsQuery()->get(),
            'checklistPersonResponsibleStaff' => Staff::assignmentDropdownPersonResponsibleQuery()->get(),
            'checklistPersonAssistingStaff' => Staff::assignmentDropdownPersonAssistingQuery()->get(),
            'checklistOffices' => Branch::query()->orderBy('office_name')->get(),
            'checklistMatterList' => Matter::query()
                ->select('id', 'title')
                ->where('status', 1)
                ->forClientType((bool) ($client->is_company ?? false))
                ->get(),
        ];
    }

    private static function attachAgreementDocs(Collection $forms): void
    {
        $matterIds = $forms->pluck('client_matter_id')->filter()->unique()->values()->all();
        if ($matterIds === []) {
            foreach ($forms as $form) {
                $form->checklist_agreement_doc = null;
            }

            return;
        }

        $docs = Document::query()
            ->whereIn('client_matter_id', $matterIds)
            ->where('doc_type', 'agreement')
            ->orderByDesc('id')
            ->get()
            ->groupBy('client_matter_id')
            ->map(static fn ($group) => $group->first());

        foreach ($forms as $form) {
            $form->checklist_agreement_doc = $docs->get($form->client_matter_id);
        }
    }
}
