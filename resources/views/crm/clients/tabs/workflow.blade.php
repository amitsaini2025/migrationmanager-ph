<link rel="stylesheet" href="{{ URL::asset('css/workflow-tab.css') }}?v={{ time() }}">

<!-- Workflow Tab — redesigned case workflow UI -->
<div class="tab-pane" id="workflow-tab">
    <div class="workflow-v2">
        <div class="card full-width workflow-tab-container">
            <?php
            $workflowSelectedMatter = null;

            if (isset($id1) && $id1 != "") {
                $workflowSelectedMatter = DB::table('client_matters as cm')
                    ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                    ->where('cm.client_id', $fetchedData->id)
                    ->where('cm.client_unique_matter_no', $id1)
                    ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_stage_id', 'cm.workflow_id', 'cm.matter_status', 'cm.deadline', 'cm.sel_migration_agent')
                    ->first();
            } else {
                $workflowSelectedMatter = DB::table('client_matters as cm')
                    ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                    ->where('cm.client_id', $fetchedData->id)
                    ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_stage_id', 'cm.workflow_id', 'cm.matter_status', 'cm.deadline', 'cm.sel_migration_agent')
                    ->orderBy('cm.id', 'desc')
                    ->first();
            }

            $workflowId = $workflowSelectedMatter ? ($workflowSelectedMatter->workflow_id ?? null) : null;
            $workflowAllStages = $workflowId
                ? DB::table('workflow_stages')->where('workflow_id', $workflowId)->orderByRaw('COALESCE(sort_order, id) ASC')->get()
                : DB::table('workflow_stages')->orderByRaw('COALESCE(sort_order, id) ASC')->get();

            if ($workflowSelectedMatter) {
                \App\Support\WorkflowStageChecklistSync::ensureSeededForMatter($workflowSelectedMatter->id);
            }

            $wf = \App\Support\WorkflowV2Display::build($workflowSelectedMatter, $fetchedData, $workflowAllStages);
            extract($wf);
            ?>

            @include('crm.clients.tabs.partials.workflow-v2-content', [
                'wfShowHeader' => true,
                'wfShowToolbar' => true,
                'wfShowHeaderAdvance' => true,
                'wfShowFooterAdvance' => false,
                'wfAdvanceButtonId' => 'workflow-tab-proceed-to-next-stage',
                'wfBackButtonId' => 'workflow-tab-back-to-previous-stage',
                'wfReopenButtonId' => 'workflow-tab-reopen',
                'wfChangeWorkflowButtonId' => 'workflow-tab-change-workflow',
                'wfDiscontinueButtonId' => 'workflow-tab-discontinue',
            ])
        </div>
    </div>
</div>
