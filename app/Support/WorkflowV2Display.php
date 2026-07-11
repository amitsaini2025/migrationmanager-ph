<?php

namespace App\Support;

use App\Models\ClientMatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkflowV2Display
{
    /**
     * Build shared view data for the workflow v2 UI (Workflow tab + Client Portal Activities).
     */
    public static function build(?object $matter, object $client, $allStages, ?int $viewStageId = null): array
    {
        if ($matter) {
            WorkflowStageChecklistSync::ensureSeededForMatter($matter);
        }

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

        $stagesPayload = self::buildStagesPayload($matter, $allStages, $currentStageId);

        $resolvedViewStageId = $viewStageId ?: $currentStageId;
        $viewStage = $resolvedViewStageId ? $allStages->firstWhere('id', $resolvedViewStageId) : null;
        if (!$viewStage && $currentStageRow) {
            $viewStage = $currentStageRow;
            $resolvedViewStageId = $currentStageId;
        }

        $viewStageName = $viewStage ? $viewStage->name : null;
        $viewStageSort = $viewStage ? ($viewStage->sort_order ?? $viewStage->id) : null;
        $viewStageIndex = $viewStageSort !== null
            ? $allStages->where(fn ($s) => ($s->sort_order ?? $s->id) <= $viewStageSort)->count()
            : 0;

        $viewStageDisplay = $viewStageName ? self::stageDisplayMeta($viewStageName) : null;
        $viewChecklist = ($matter && $viewStage)
            ? self::checklistForStage($matter, (int) $viewStage->id, $viewStageName)
            : ['rows' => [], 'outstanding' => 0];

        $checklistRows = $viewChecklist['rows'];
        $outstandingRequired = $viewChecklist['outstanding'];
        $stageDisplay = $viewStageDisplay;

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
            'isActive',
            'stagesPayload',
            'viewStageId',
            'viewStageIndex',
            'viewStageName'
        ) + [
            'clientId' => $client->client_id ?? '',
            'viewStageId' => $resolvedViewStageId,
        ];
    }

    /**
     * Per-stage data for client-side stage switching.
     */
    public static function buildStagesPayload(?object $matter, $allStages, ?int $currentStageId): array
    {
        $payload = [];
        $currentStageRow = $currentStageId ? $allStages->firstWhere('id', $currentStageId) : null;
        $currentStageSort = $currentStageRow ? ($currentStageRow->sort_order ?? $currentStageRow->id) : null;

        foreach ($allStages as $stageIndex => $stage) {
            $stageSort = $stage->sort_order ?? $stage->id;
            $isActive = $currentStageId && (int) $currentStageId === (int) $stage->id;
            $isCompleted = $currentStageId && $currentStageSort !== null && $stageSort < $currentStageSort;
            $isLocked = !$isActive && !$isCompleted;

            $stageName = $stage->name;
            $stageDisplay = self::stageDisplayMeta($stageName);
            $checklist = ($matter && $stageName)
                ? self::checklistForStage($matter, (int) $stage->id, $stageName)
                : ['rows' => [], 'outstanding' => 0];

            $payload[] = [
                'id' => (int) $stage->id,
                'index' => $stageIndex + 1,
                'name' => $stageName,
                'status' => $isActive ? 'active' : ($isCompleted ? 'completed' : 'locked'),
                'isCurrent' => $isActive,
                'stageDisplay' => $stageDisplay ? [
                    'pending_from' => $stageDisplay['pending_from'] ?? null,
                    'completion_rule' => $stageDisplay['completion_rule'] ?? null,
                    'file_note_section' => !empty($stageDisplay['file_note_section']),
                ] : null,
                'checklistRows' => $checklist['rows'],
                'outstandingRequired' => $checklist['outstanding'],
            ];
        }

        return $payload;
    }

    public static function stageDisplayMeta(?string $stageName): ?array
    {
        if (!$stageName) {
            return null;
        }

        $stageDefaults = config('workflow.stage_display_defaults', []);
        $stageNameKey = strtolower(trim($stageName));
        foreach ($stageDefaults as $key => $meta) {
            if (strtolower(trim($key)) === $stageNameKey) {
                return $meta;
            }
        }

        return null;
    }

    /**
     * Resolve checklist rows for a matter + stage (cp_doc_checklists, admin templates, config fallback).
     *
     * @return array{rows: array<int, array{label: string, required: bool, done: bool}>, outstanding: int}
     */
    public static function checklistForStage(?object $matter, int $stageId, ?string $stageName): array
    {
        $rows = [];
        $outstanding = 0;

        if (!$matter || !$stageName) {
            return ['rows' => $rows, 'outstanding' => $outstanding];
        }

        $seenNames = [];

        $cpChecklists = DB::table('cp_doc_checklists')
            ->where('client_matter_id', $matter->id)
            ->where('wf_stage', $stageName)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($cpChecklists as $cpItem) {
            $label = trim((string) ($cpItem->cp_checklist_name ?? 'Checklist item'));
            $norm = strtolower($label);
            if ($norm === '' || isset($seenNames[$norm])) {
                continue;
            }
            $seenNames[$norm] = true;

            $uploadCount = DB::table('documents')
                ->where('cp_list_id', $cpItem->id)
                ->where('type', 'workflow_checklist')
                ->count();
            $isDone = $uploadCount > 0;
            $itemRequired = Schema::hasColumn('cp_doc_checklists', 'is_required')
                ? (bool) ($cpItem->is_required ?? true)
                : true;

            $rows[] = [
                'label' => $label,
                'required' => $itemRequired,
                'done' => $isDone,
            ];
            if ($itemRequired && !$isDone) {
                $outstanding++;
            }
        }

        if (Schema::hasTable('workflow_stage_checklists') && !empty($matter->workflow_id)) {
            $templates = DB::table('workflow_stage_checklists')
                ->where('workflow_id', $matter->workflow_id)
                ->where('workflow_stage_id', $stageId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($templates as $template) {
                $label = trim((string) $template->name);
                $norm = strtolower($label);
                if ($norm === '' || isset($seenNames[$norm])) {
                    continue;
                }
                $seenNames[$norm] = true;

                $itemRequired = (bool) ($template->is_required ?? true);
                $rows[] = [
                    'label' => $label,
                    'required' => $itemRequired,
                    'done' => false,
                ];
                if ($itemRequired) {
                    $outstanding++;
                }
            }
        }

        if (count($rows) === 0) {
            $stageDisplay = self::stageDisplayMeta($stageName);
            $hasAdminTemplates = Schema::hasTable('workflow_stage_checklists')
                && !empty($matter->workflow_id)
                && DB::table('workflow_stage_checklists')
                    ->where('workflow_id', $matter->workflow_id)
                    ->where('workflow_stage_id', $stageId)
                    ->exists();

            if (!$hasAdminTemplates && $stageDisplay && !empty($stageDisplay['checklist_items'])) {
                foreach ($stageDisplay['checklist_items'] as $item) {
                    $rows[] = [
                        'label' => $item['label'] ?? 'Item',
                        'required' => !empty($item['required']),
                        'done' => false,
                    ];
                    if (!empty($item['required'])) {
                        $outstanding++;
                    }
                }
            }
        }

        return ['rows' => $rows, 'outstanding' => $outstanding];
    }
}
