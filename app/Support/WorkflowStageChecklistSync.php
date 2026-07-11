<?php

namespace App\Support;

use App\Models\ClientMatter;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds cp_doc_checklists on a matter from workflow_stage_checklists templates.
 * Idempotent: skips items that already exist (same matter + stage + name).
 */
class WorkflowStageChecklistSync
{
    public static function ensureSeededForMatter($matter): void
    {
        if (!Schema::hasTable('workflow_stage_checklists') || !Schema::hasTable('cp_doc_checklists')) {
            return;
        }

        if ($matter instanceof ClientMatter) {
            $clientMatter = $matter;
        } elseif (is_numeric($matter)) {
            $clientMatter = ClientMatter::find((int) $matter);
        } else {
            return;
        }

        if (!$clientMatter || empty($clientMatter->workflow_id) || empty($clientMatter->id)) {
            return;
        }

        $templates = DB::table('workflow_stage_checklists')
            ->where('workflow_id', $clientMatter->workflow_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($templates->isEmpty()) {
            return;
        }

        $stageIds = $templates->pluck('workflow_stage_id')->unique()->filter()->values()->all();
        $stagesById = WorkflowStage::whereIn('id', $stageIds)->get()->keyBy('id');
        $now = now();

        foreach ($templates as $template) {
            $stage = $stagesById->get($template->workflow_stage_id);
            if (!$stage || empty($stage->name)) {
                continue;
            }

            $normalizedName = strtolower(trim((string) $template->name));
            if ($normalizedName === '') {
                continue;
            }

            $exists = DB::table('cp_doc_checklists')
                ->where('client_matter_id', $clientMatter->id)
                ->where('wf_stage', $stage->name)
                ->whereRaw('LOWER(TRIM(cp_checklist_name)) = ?', [$normalizedName])
                ->exists();

            if ($exists) {
                continue;
            }

            $payload = [
                'user_id' => null,
                'client_matter_id' => $clientMatter->id,
                'client_id' => $clientMatter->client_id,
                'wf_stage' => $stage->name,
                'wf_stage_id' => $stage->id,
                'cp_checklist_name' => trim($template->name),
                'description' => $template->description,
                'allow_client' => (int) ($template->allow_client ?? 1),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('cp_doc_checklists', 'is_required')) {
                $payload['is_required'] = (int) ($template->is_required ?? 1);
            }

            DB::table('cp_doc_checklists')->insert($payload);
        }
    }

    /**
     * Seed templates onto every matter using the given workflow.
     */
    public static function ensureSeededForWorkflow(int $workflowId): void
    {
        if ($workflowId <= 0) {
            return;
        }

        ClientMatter::where('workflow_id', $workflowId)
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($matters) {
                foreach ($matters as $matter) {
                    self::ensureSeededForMatter($matter->id);
                }
            });
    }
}
