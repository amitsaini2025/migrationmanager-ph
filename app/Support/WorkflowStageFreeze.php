<?php

namespace App\Support;

/**
 * Determines whether a workflow stage is frozen (non-editable / non-deletable).
 */
class WorkflowStageFreeze
{
    public static function isFrozen(?string $name, ?string $workflowName = null): bool
    {
        if (!self::matchesFrozenStageName($name)) {
            return false;
        }

        if (config('workflow.freeze_protected_stages_only_on_general_workflow', true)) {
            if ($workflowName === null || trim($workflowName) === '') {
                // Legacy stages without workflow context stay protected.
                return true;
            }

            return self::isGeneralWorkflowName($workflowName);
        }

        return true;
    }

    public static function isGeneralWorkflowName(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        return mb_strtolower(trim($name)) === 'general';
    }

    public static function matchesFrozenStageName(?string $name): bool
    {
        if ($name === null || $name === '') {
            return false;
        }

        $normalized = mb_strtolower(trim($name));

        foreach (config('workflow.frozen_stage_names', []) as $exact) {
            if ($exact === null || $exact === '') {
                continue;
            }
            if ($normalized === mb_strtolower(trim((string) $exact))) {
                return true;
            }
        }

        foreach (config('workflow.frozen_stage_name_starts_with', []) as $prefix) {
            if ($prefix === null || $prefix === '') {
                continue;
            }
            $p = mb_strtolower(trim((string) $prefix));
            if ($p !== '' && str_starts_with($normalized, $p)) {
                return true;
            }
        }

        return false;
    }
}
