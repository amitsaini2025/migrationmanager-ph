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
        $completedStages = max(0, $currentStageIndex - 1);
        $progressPercentage = $totalStages > 0
            ? (int) round(($completedStages / $totalStages) * 100)
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
        $fileNoteBody = ($matter && $resolvedViewStageId)
            ? self::fileNoteBodyForStage($matter, (int) $resolvedViewStageId)
            : '';

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
        $currentStageOutstanding = 0;
        if ($matter && $currentStageRow) {
            $currentStageChecklist = self::checklistForStage(
                $matter,
                (int) $currentStageRow->id,
                $currentStageName
            );
            $currentStageOutstanding = (int) ($currentStageChecklist['outstanding'] ?? 0);
        }
        // Base disable = last stage only. Workflow tab applies outstanding gate via interactive flag / JS.
        $nextBtnDisabled = $isLastStage;

        $admin = Auth::guard('admin')->user();
        $canDiscontinue = $admin
            && in_array((int) ($admin->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);

        $isActive = $matter && ($matter->matter_status ?? 1) == 1;
        $activeChecklistIndex = self::activeChecklistIndex($checklistRows);

        return array_merge(compact(
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
            'viewStageIndex',
            'viewStageName',
            'activeChecklistIndex',
            'currentStageOutstanding',
            'fileNoteBody'
        ), [
            'clientId' => $client->client_id ?? '',
            'viewStageId' => $resolvedViewStageId,
        ]);
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
            $isProtected = self::stageIsProtected($stage);

            $stageName = $stage->name;
            $stageDisplay = self::stageDisplayMeta($stageName);
            $checklist = ($matter && $stageName)
                ? self::checklistForStage($matter, (int) $stage->id, $stageName)
                : ['rows' => [], 'outstanding' => 0];

            $payload[] = [
                'id' => (int) $stage->id,
                'index' => $stageIndex + 1,
                'name' => $stageName,
                'status' => $isActive ? 'active' : ($isCompleted ? 'completed' : ($isProtected ? 'locked' : 'future')),
                'isCurrent' => $isActive,
                'isCompleted' => (bool) $isCompleted,
                'isProtected' => $isProtected,
                'isFuture' => !$isActive && !$isCompleted,
                'stageDisplay' => $stageDisplay ? [
                    'pending_from' => $stageDisplay['pending_from'] ?? null,
                    'completion_rule' => $stageDisplay['completion_rule'] ?? null,
                    'file_note_section' => !empty($stageDisplay['file_note_section']),
                ] : null,
                'checklistRows' => $checklist['rows'],
                'outstandingRequired' => $checklist['outstanding'],
                'activeChecklistIndex' => self::activeChecklistIndex($checklist['rows']),
                'fileNoteBody' => ($matter && !empty($stageDisplay['file_note_section']))
                    ? self::fileNoteBodyForStage($matter, (int) $stage->id)
                    : '',
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
     * @return array{rows: array<int, array{id: int|null, label: string, required: bool, done: bool}>, outstanding: int}
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

            $isDone = self::checklistItemIsDone($cpItem);
            $itemRequired = Schema::hasColumn('cp_doc_checklists', 'is_required')
                ? (bool) $cpItem->is_required
                : false;

            $rows[] = [
                'id' => (int) $cpItem->id,
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

                $itemRequired = (bool) $template->is_required;
                $rows[] = [
                    'id' => null,
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
                        'id' => null,
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

    /**
     * Whether a cp_doc_checklists row is considered complete (manual check or document upload).
     */
    public static function checklistItemIsDone(object $cpItem): bool
    {
        if (Schema::hasColumn('cp_doc_checklists', 'is_completed') && !empty($cpItem->is_completed)) {
            return true;
        }

        return DB::table('documents')
            ->where('cp_list_id', $cpItem->id)
            ->where('type', 'workflow_checklist')
            ->exists();
    }

    /**
     * Index of the first incomplete checklist item (active for sequential checking), or -1 if all done.
     *
     * @param  array<int, array{done?: bool}>  $rows
     */
    public static function activeChecklistIndex(array $rows): int
    {
        foreach ($rows as $index => $row) {
            if (empty($row['done'])) {
                return (int) $index;
            }
        }

        return -1;
    }

    /**
     * Outstanding required checklist count for the matter's current workflow stage.
     */
    public static function outstandingRequiredForCurrentStage(?object $matter): int
    {
        if (!$matter || empty($matter->workflow_stage_id)) {
            return 0;
        }

        $stage = DB::table('workflow_stages')->where('id', $matter->workflow_stage_id)->first();
        if (!$stage || empty($stage->name)) {
            return 0;
        }

        $checklist = self::checklistForStage($matter, (int) $stage->id, $stage->name);

        return (int) ($checklist['outstanding'] ?? 0);
    }

    /**
     * Saved file note body for a matter + stage (empty string when none).
     */
    public static function fileNoteBodyForStage(?object $matter, int $stageId): string
    {
        if (!$matter || $stageId <= 0 || !Schema::hasTable('workflow_file_notes')) {
            return '';
        }

        $body = DB::table('workflow_file_notes')
            ->where('client_matter_id', $matter->id)
            ->where('workflow_stage_id', $stageId)
            ->value('body');

        return is_string($body) ? $body : '';
    }

    /**
     * Whether a workflow stage is marked Protected in Admin Console.
     */
    public static function stageIsProtected(object $stage): bool
    {
        if (!Schema::hasColumn('workflow_stages', 'is_protected')) {
            return false;
        }

        return (bool) ($stage->is_protected ?? false);
    }
}
