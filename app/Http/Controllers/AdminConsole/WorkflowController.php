<?php
namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Workflow;
use App\Models\WorkflowStage;
use App\Models\WorkflowStageChecklist;
use App\Models\ClientMatter;
use App\Support\WorkflowStageChecklistSync;

class WorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * List workflows (Workflow model).
     */
    public function index(Request $request)
    {
        $query = Workflow::with(['matter', 'stages'])->where('status', 1);
        $lists = $query->orderBy('name')->paginate(config('constants.limit', 20));
        return view('AdminConsole.features.workflow.workflows-index', compact('lists'));
    }

    /**
     * Create new workflow form.
     */
    public function create(Request $request)
    {
        return view('AdminConsole.features.workflow.workflow-create');
    }

    /**
     * Store new workflow.
     */
    public function storeWorkflow(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'matter_id' => 'nullable|exists:matters,id',
        ]);
        $wf = null;
        DB::transaction(function () use ($request, &$wf) {
            $wf = new Workflow();
            $wf->name = $request->name;
            $wf->matter_id = $request->matter_id ?: null;
            $wf->status = 1;
            $wf->save();

            // Copy stages from the General workflow so the new workflow is pre-populated.
            $generalWorkflow = Workflow::whereRaw('LOWER(name) = ?', ['general'])->first();
            $defaultStages = [];
            if ($generalWorkflow) {
                $defaultStages = WorkflowStage::where('workflow_id', $generalWorkflow->id)
                    ->orderByRaw('COALESCE(sort_order, id) ASC')
                    ->pluck('name')
                    ->toArray();
            }

            // Fallback when General workflow does not exist yet.
            if (empty($defaultStages)) {
                $defaultStages = ['Application Received', 'Checklist', 'Ready to Close', 'File Closed'];
            }

            foreach ($defaultStages as $i => $stageName) {
                $stage = new WorkflowStage();
                $stage->name = $stageName;
                $stage->workflow_id = $wf->id;
                $stage->sort_order = $i + 1;
                $stage->save();
            }
        });

        if (!$wf || !$wf->id) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow could not be created. Please try again.');
        }

        return redirect()
            ->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($wf->id)))
            ->with('success', 'Workflow created. Default stages copied from General — amend, remove or add as needed.');
    }

    /**
     * Edit workflow form.
     */
    public function editWorkflow($id)
    {
        $id = $this->decodeString($id);
        $workflow = Workflow::find($id);
        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }
        return view('AdminConsole.features.workflow.workflow-edit', compact('workflow'));
    }

    /**
     * Update workflow.
     */
    public function updateWorkflow(Request $request, $id)
    {
        $id = $this->decodeString($id);
        $workflow = Workflow::find($id);
        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'matter_id' => 'nullable|exists:matters,id',
        ]);
        $workflow->name = $request->name;
        $workflow->matter_id = $request->matter_id ?: null;
        $workflow->save();
        return redirect()->route('adminconsole.features.workflow.index')->with('success', 'Workflow Updated Successfully');
    }

    /**
     * List stages for a workflow.
     */
    public function stages($id)
    {
        $id = $this->decodeString($id);
        $workflow = Workflow::find($id);
        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }
        $lists = WorkflowStage::where('workflow_id', $workflow->id)
            ->with('workflow')
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->paginate(config('constants.limit', 50));

        // Single query for all matter counts rather than one per row.
        $stageIds = $lists->pluck('id')->toArray();
        $matterCounts = ClientMatter::where('workflow_id', $workflow->id)
            ->whereIn('workflow_stage_id', $stageIds)
            ->selectRaw('workflow_stage_id, COUNT(*) as cnt')
            ->groupBy('workflow_stage_id')
            ->pluck('cnt', 'workflow_stage_id');

        $checklistCounts = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('workflow_stage_checklists') && $stageIds) {
            $checklistCounts = WorkflowStageChecklist::where('workflow_id', $workflow->id)
                ->whereIn('workflow_stage_id', $stageIds)
                ->selectRaw('workflow_stage_id, COUNT(*) as cnt')
                ->groupBy('workflow_stage_id')
                ->pluck('cnt', 'workflow_stage_id');
        }

        return view('AdminConsole.features.workflow.stages-index', compact('workflow', 'lists', 'matterCounts', 'checklistCounts'));
    }

    /**
     * Create stage form (for a specific workflow).
     * Optional query ?after={encodedStageId} — new stages insert immediately after that stage.
     */
    public function createStage(Request $request, $workflowId)
    {
        $workflowId = $this->decodeString($workflowId);
        $workflow = Workflow::find($workflowId);
        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }
        $insertAfterStage = null;
        if ($request->filled('after')) {
            $afterId = $this->decodeString($request->query('after'));
            if ($afterId) {
                $insertAfterStage = WorkflowStage::where('id', $afterId)
                    ->where('workflow_id', $workflow->id)
                    ->first();
            }
        }
        return view('AdminConsole.features.workflow.create', compact('workflow', 'insertAfterStage'));
    }

    /**
     * Store new stage(s). Supports workflow_id for per-workflow stages.
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'stage_name' => 'required|array',
            'stage_name.*' => 'required|string|max:255',
            'is_protected' => 'nullable|array',
            'is_protected.*' => 'nullable|boolean',
            'after_stage_id' => 'nullable|integer|exists:workflow_stages,id',
        ]);
        $workflowId = $request->workflow_id;
        if (!$workflowId) {
            $general = Workflow::where('name', 'General')->first();
            $workflowId = $general ? $general->id : null;
        }
        $stages = $request->stage_name;
        $protectedFlags = $request->input('is_protected', []);
        $afterStageId = $request->input('after_stage_id');

        if ($afterStageId && !$workflowId) {
            return redirect()->back()->withInput()->with('error', 'Cannot insert after a stage without a workflow context.');
        }

        if ($afterStageId) {
            $afterStage = WorkflowStage::where('id', $afterStageId)->first();
            if (!$afterStage || (int) $afterStage->workflow_id !== (int) $workflowId) {
                return redirect()->back()->withInput()->with('error', 'Invalid “insert after” stage for this workflow.');
            }
        }

        DB::transaction(function () use ($stages, $workflowId, $afterStageId, $protectedFlags) {
            if ($afterStageId) {
                $afterStage = WorkflowStage::where('id', $afterStageId)->lockForUpdate()->first();
                $effectiveAfter = (int) ($afterStage->sort_order ?? $afterStage->id);
                $n = count($stages);
                $toShift = WorkflowStage::where('workflow_id', $workflowId)
                    ->where('id', '!=', $afterStage->id)
                    ->whereRaw('COALESCE(sort_order, id) > ?', [$effectiveAfter])
                    ->orderByRaw('COALESCE(sort_order, id) DESC')
                    ->lockForUpdate()
                    ->get();
                foreach ($toShift as $row) {
                    $curr = (int) ($row->sort_order ?? $row->id);
                    $row->sort_order = $curr + $n;
                    $row->save();
                }
                $pos = 0;
                foreach ($stages as $i => $stageName) {
                    $o = new WorkflowStage();
                    $o->name = $stageName;
                    $o->workflow_id = $workflowId;
                    $o->sort_order = $effectiveAfter + 1 + $pos;
                    $o->is_protected = !empty($protectedFlags[$i]);
                    $o->save();
                    $pos++;
                }
                return;
            }

            $sortQuery = WorkflowStage::query();
            if ($workflowId) {
                $sortQuery->where('workflow_id', $workflowId);
            } else {
                $sortQuery->whereNull('workflow_id');
            }
            $maxSortOrder = (int) ($sortQuery->max('sort_order') ?? $sortQuery->max('id') ?? 0);
            foreach ($stages as $i => $stageName) {
                $o = new WorkflowStage();
                $o->name = $stageName;
                $o->workflow_id = $workflowId;
                $o->sort_order = ++$maxSortOrder;
                $o->is_protected = !empty($protectedFlags[$i]);
                $o->save();
            }
        });

        if ($workflowId) {
            $msg = $afterStageId
                ? 'Stage(s) inserted after the selected stage.'
                : 'Workflow Stages Added Successfully';
            return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflowId)))
                ->with('success', $msg);
        }
        return redirect()->route('adminconsole.features.workflow.index')->with('success', 'Workflow Stages Added Successfully');
    }

    /**
     * Edit stage form.
     */
    public function edit($id)
    {
        $id = $this->decodeString($id);
        $fetchedData = WorkflowStage::with('workflow')->find($id);
        if (!$fetchedData) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow Stage Not Found');
        }
        $workflow = $fetchedData->workflow;
        return view('AdminConsole.features.workflow.edit', compact('fetchedData', 'workflow'));
    }

    /**
     * Update stage.
     */
    public function update(Request $request, $id)
    {
        $id = $this->decodeString($id);
        $stage = WorkflowStage::with('workflow')->find($id);
        if (!$stage) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow Stage Not Found');
        }
        $this->validate($request, [
            'stage_name' => 'required|array',
            'stage_name.*' => 'required|string|max:255',
            'is_protected' => 'nullable|boolean',
        ]);
        if ($stage->isConfigFrozen()) {
            $workflow = $stage->workflow;
            if ($workflow) {
                return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id)))
                    ->with('error', 'This workflow stage is protected and cannot be renamed.');
            }
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'This workflow stage is protected and cannot be renamed.');
        }
        $wasProtected = (bool) $stage->is_protected;
        $stage->is_protected = $request->boolean('is_protected');
        if ($stage->is_protected && $wasProtected) {
            if ($request->stage_name[0] !== $stage->name) {
                $workflow = $stage->workflow;
                if ($workflow) {
                    return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id)))
                        ->with('error', 'Protected stages cannot be renamed. Uncheck Protected first.');
                }
                return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Protected stages cannot be renamed. Uncheck Protected first.');
            }
        } else {
            $stage->name = $request->stage_name[0];
        }
        $stage->save();
        $workflow = $stage->workflow;
        if ($workflow) {
            return redirect()->route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id)))
                ->with('success', 'Workflow Stage Updated Successfully');
        }
        return redirect()->route('adminconsole.features.workflow.index')->with('success', 'Workflow Stage Updated Successfully');
    }

    /**
     * Manage default checklists for a workflow stage (templates for all matters on this workflow).
     */
    public function stageChecklists($workflowId, $stageId)
    {
        $workflowId = $this->decodeString($workflowId);
        $stageId = $this->decodeString($stageId);

        $workflow = Workflow::find($workflowId);
        $stage = WorkflowStage::where('id', $stageId)->where('workflow_id', $workflowId)->first();

        if (!$workflow || !$stage) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow stage not found');
        }

        $checklists = WorkflowStageChecklist::where('workflow_id', $workflowId)
            ->where('workflow_stage_id', $stageId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('AdminConsole.features.workflow.stage-checklists-index', compact('workflow', 'stage', 'checklists'));
    }

    /**
     * Store a checklist template on a workflow stage.
     */
    public function storeStageChecklist(Request $request)
    {
        $this->validate($request, [
            'workflow_id' => 'required|integer|exists:workflows,id',
            'workflow_stage_id' => 'required|integer|exists:workflow_stages,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'allow_client' => 'nullable|boolean',
            'is_required' => 'nullable|boolean',
        ]);

        $workflowId = (int) $request->workflow_id;
        $stageId = (int) $request->workflow_stage_id;

        $stage = WorkflowStage::where('id', $stageId)->where('workflow_id', $workflowId)->first();
        if (!$stage) {
            return redirect()->back()->withInput()->with('error', 'Invalid stage for this workflow.');
        }

        $maxSort = (int) WorkflowStageChecklist::where('workflow_stage_id', $stageId)->max('sort_order');

        WorkflowStageChecklist::create([
            'workflow_id' => $workflowId,
            'workflow_stage_id' => $stageId,
            'name' => trim($request->name),
            'description' => $request->description ? trim($request->description) : null,
            'allow_client' => $request->has('allow_client'),
            'is_required' => $request->has('is_required'),
            'sort_order' => $maxSort + 1,
        ]);

        WorkflowStageChecklistSync::ensureSeededForWorkflow($workflowId);

        return redirect()
            ->route('adminconsole.features.workflow.stageChecklists', [
                base64_encode(convert_uuencode($workflowId)),
                base64_encode(convert_uuencode($stageId)),
            ])
            ->with('success', 'Checklist added and applied to all matters using this workflow.');
    }

    /**
     * Push all stage checklist templates to every matter on this workflow.
     */
    public function syncWorkflowChecklists($workflowId)
    {
        $workflowId = $this->decodeString($workflowId);
        $workflow = Workflow::find($workflowId);

        if (!$workflow) {
            return redirect()->route('adminconsole.features.workflow.index')->with('error', 'Workflow not found');
        }

        WorkflowStageChecklistSync::ensureSeededForWorkflow((int) $workflowId);

        return redirect()->back()->with('success', 'Checklists applied to all existing matters using "' . $workflow->name . '".');
    }

    /**
     * Update a workflow stage checklist template.
     */
    public function updateStageChecklist(Request $request, $id)
    {
        $id = $this->decodeString($id);
        $item = WorkflowStageChecklist::find($id);

        if (!$item) {
            return redirect()->back()->with('error', 'Checklist not found.');
        }

        $this->validate($request, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'allow_client' => 'nullable|boolean',
            'is_required' => 'nullable|boolean',
        ]);

        $item->name = trim($request->name);
        $item->description = $request->description ? trim($request->description) : null;
        $item->allow_client = $request->has('allow_client');
        $item->is_required = $request->has('is_required');
        $item->save();

        return redirect()
            ->route('adminconsole.features.workflow.stageChecklists', [
                base64_encode(convert_uuencode($item->workflow_id)),
                base64_encode(convert_uuencode($item->workflow_stage_id)),
            ])
            ->with('success', 'Checklist updated. Use "Apply all checklists to existing matters" to sync changes to clients.');
    }

    /**
     * Delete a workflow stage checklist template.
     */
    public function destroyStageChecklist($id)
    {
        $id = $this->decodeString($id);
        $item = WorkflowStageChecklist::find($id);

        if (!$item) {
            return redirect()->back()->with('error', 'Checklist template not found.');
        }

        $workflowId = $item->workflow_id;
        $stageId = $item->workflow_stage_id;
        $item->delete();

        return redirect()
            ->route('adminconsole.features.workflow.stageChecklists', [
                base64_encode(convert_uuencode($workflowId)),
                base64_encode(convert_uuencode($stageId)),
            ])
            ->with('success', 'Checklist template removed. Existing client checklists are not affected.');
    }
}
