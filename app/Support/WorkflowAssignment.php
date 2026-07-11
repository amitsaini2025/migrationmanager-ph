<?php

namespace App\Support;

use App\Models\Matter;
use App\Models\Workflow;
use App\Models\WorkflowStage;

/**
 * Resolves which workflow template to assign when a new client matter is created.
 */
class WorkflowAssignment
{
    public static function resolveWorkflowIdForNewClientMatter(?Matter $matterType): ?int
    {
        if ($matterType && $matterType->workflow_id) {
            return (int) $matterType->workflow_id;
        }

        $workflowName = self::workflowNameForMatterType($matterType);

        $workflowId = self::workflowIdByName($workflowName);
        if ($workflowId) {
            return $workflowId;
        }

        return self::generalWorkflowId();
    }

    public static function firstStageIdForWorkflow(?int $workflowId): int
    {
        if ($workflowId) {
            $stageId = WorkflowStage::where('workflow_id', $workflowId)
                ->orderByRaw('COALESCE(sort_order, id) ASC')
                ->value('id');

            if ($stageId) {
                return (int) $stageId;
            }
        }

        $fallbackStageId = WorkflowStage::orderByRaw('COALESCE(sort_order, id) ASC')->value('id');

        return (int) ($fallbackStageId ?? 1);
    }

    private static function workflowNameForMatterType(?Matter $matterType): string
    {
        $defaultName = (string) config('workflow.default_workflow_name', 'General');

        if (!$matterType) {
            return $defaultName;
        }

        $title = trim((string) $matterType->title);
        $map = config('workflow.matter_default_workflows', []);

        if ($title !== '' && isset($map[$title])) {
            return (string) $map[$title];
        }

        if ($title !== '') {
            foreach ($map as $matterTitle => $workflowName) {
                if (strcasecmp(trim((string) $matterTitle), $title) === 0) {
                    return (string) $workflowName;
                }
            }
        }

        return $defaultName;
    }

    private static function workflowIdByName(string $workflowName): ?int
    {
        $workflowName = trim($workflowName);
        if ($workflowName === '') {
            return null;
        }

        $id = Workflow::where('name', $workflowName)->value('id');
        if ($id) {
            return (int) $id;
        }

        $normalized = mb_strtolower($workflowName);
        $match = Workflow::query()
            ->get(['id', 'name'])
            ->first(fn ($row) => mb_strtolower(trim((string) $row->name)) === $normalized);

        return $match ? (int) $match->id : null;
    }

    private static function generalWorkflowId(): ?int
    {
        return self::workflowIdByName((string) config('workflow.default_workflow_name', 'General'));
    }
}
