<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkflowV2Display
{
    /**
     * Build shared view data for the workflow v2 UI (Workflow tab + Client Portal Activities).
     *
     * @param  object|null  $matter  client_matters row (id, workflow_stage_id, workflow_id, matter_status, deadline, sel_migration_agent, sel_matter_id, title, client_unique_matter_no)
     * @param  object  $client  client row (id, client_id, first_name, last_name)
     * @param  \Illuminate\Support\Collection  $allStages  workflow_stages collection
     */
    public static function build(?object $matter, object $client, $allStages): array
    {
        $matterName = '';
        $matterNumber = '';
        $currentStageId = null;
        $currentStageName = null;

        if ($matter) {
            if (($matter->sel_matter_id ?? null) == 1 || empty($matter->title)) {
                $matterName = 'General Matter';
            } else {
                $matterName = $matter->title;
            }
            $matterNumber = $matter->client_unique_matter_no ?? '';
            $currentStageId = $matter->workflow_stage_id ?? null;
        }

        $totalStages = $allStages->count();
        $currentStageRow = $currentStageId ? $allStages->firstWhere('id', $currentStageId) : null;
        $currentSortVal = $currentStageRow ? ($currentStageRow->sort_order ?? $currentStageRow->id) : null;
        $currentStageIndex = $currentSortVal !== null
            ? $allStages->where(fn ($s) => ($s->sort_order ?? $s->id) <= $currentSortVal)->count()
            : 0;
        $progressPercentage = $totalStages > 0
            ? (int) round(($currentStageIndex / $totalStages) * 100)
            : 0;

        if ($matter && $currentStageId && $totalStages > 0) {
            $currentStageName = $currentStageRow ? $currentStageRow->name : null;
        }

        $marnNumber = null;
        if ($matter && !empty($matter->sel_migration_agent)) {
            $marnNumber = DB::table('staff')
                ->where('id', $matter->sel_migration_agent)
                ->value('marn_number');
        }

        $clientDisplayName = trim(
            (($client->first_name ?? '') !== '' ? mb_substr($client->first_name, 0, 1) . '. ' : '')
            . ($client->last_name ?? '')
        );

        $stageDisplay = null;
        if ($currentStageName) {
            $stageDefaults = config('workflow.stage_display_defaults', []);
            $stageNameKey = strtolower(trim($currentStageName));
            foreach ($stageDefaults as $key => $meta) {
                if (strtolower(trim($key)) === $stageNameKey) {
                    $stageDisplay = $meta;
                    break;
                }
            }
        }

        $checklistRows = [];
        $outstandingRequired = 0;

        if ($matter && $currentStageName) {
            $cpChecklists = DB::table('cp_doc_checklists')
                ->where('client_matter_id', $matter->id)
                ->where('wf_stage', $currentStageName)
                ->orderBy('id', 'asc')
                ->get();

            if ($cpChecklists->count() > 0) {
                foreach ($cpChecklists as $cpItem) {
                    $uploadCount = DB::table('documents')
                        ->where('cp_list_id', $cpItem->id)
                        ->where('type', 'workflow_checklist')
                        ->count();
                    $isDone = $uploadCount > 0;
                    $checklistRows[] = [
                        'label' => $cpItem->cp_checklist_name ?? 'Checklist item',
                        'required' => true,
                        'done' => $isDone,
                    ];
                    if (!$isDone) {
                        $outstandingRequired++;
                    }
                }
            } elseif ($stageDisplay && !empty($stageDisplay['checklist_items'])) {
                foreach ($stageDisplay['checklist_items'] as $item) {
                    $checklistRows[] = [
                        'label' => $item['label'] ?? 'Item',
                        'required' => !empty($item['required']),
                        'done' => false,
                    ];
                    if (!empty($item['required'])) {
                        $outstandingRequired++;
                    }
                }
            }
        }

        $isDiscontinued = $matter && ($matter->matter_status ?? 1) == 0;
        $canReopen = in_array(
            (int) (Auth::guard('admin')->user()->role ?? 0),
            config('crm.matter_discontinue_role_ids', [1, 17, 16]),
            true
        );

        $isFirstStage = false;
        $nextStageName = null;
        $nextStage = null;
        if ($currentStageId && $totalStages > 0) {
            $firstStage = $allStages->first();
            $isFirstStage = ($currentStageId == $firstStage->id);
            $currentOrder = $allStages->firstWhere('id', $currentStageId);
            $currentSort = $currentOrder ? ($currentOrder->sort_order ?? $currentOrder->id) : null;
            $nextStage = $currentSort !== null
                ? $allStages->first(fn ($s) => ($s->sort_order ?? $s->id) > $currentSort)
                : $allStages->where('id', '>', $currentStageId)->first();
            $nextStageName = $nextStage ? $nextStage->name : null;
        }

        $isLastStage = $nextStage === null;
        $nextBtnDisabled = $isLastStage;

        $admin = Auth::guard('admin')->user();
        $canDiscontinue = $admin
            && in_array((int) ($admin->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);

        $isActive = $matter && ($matter->matter_status ?? 1) == 1;

        return compact(
            'matter',
            'matterName',
            'matterNumber',
            'currentStageId',
            'currentStageName',
            'allStages',
            'totalStages',
            'currentStageIndex',
            'progressPercentage',
            'marnNumber',
            'clientDisplayName',
            'stageDisplay',
            'checklistRows',
            'outstandingRequired',
            'isDiscontinued',
            'canReopen',
            'isFirstStage',
            'nextStageName',
            'nextBtnDisabled',
            'canDiscontinue',
            'isActive'
        ) + [
            'clientId' => $client->client_id ?? '',
        ];
    }
}
