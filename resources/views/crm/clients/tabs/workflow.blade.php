<!-- Workflow Tab - Matter-specific, mirrors Client Portal workflow UI -->
<div class="tab-pane" id="workflow-tab">
    <div class="card full-width workflow-tab-container">
        <?php
        // Get the selected matter based on URL parameter or latest matter (same logic as Client Portal)
        $workflowSelectedMatter = null;
        $workflowMatterName = '';
        $workflowMatterNumber = '';

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

        if ($workflowSelectedMatter) {
            if ($workflowSelectedMatter->sel_matter_id == 1 || empty($workflowSelectedMatter->title)) {
                $workflowMatterName = 'General Matter';
            } else {
                $workflowMatterName = $workflowSelectedMatter->title;
            }
            $workflowMatterNumber = $workflowSelectedMatter->client_unique_matter_no;
            $workflowCurrentStageId = $workflowSelectedMatter->workflow_stage_id;
        } else {
            $workflowCurrentStageId = null;
        }

        $workflowId = $workflowSelectedMatter ? ($workflowSelectedMatter->workflow_id ?? null) : null;
        $workflowAllStages = $workflowId
            ? DB::table('workflow_stages')->where('workflow_id', $workflowId)->orderByRaw('COALESCE(sort_order, id) ASC')->get()
            : DB::table('workflow_stages')->orderByRaw('COALESCE(sort_order, id) ASC')->get();

        $workflowCurrentStageName = null;
        if ($workflowSelectedMatter && $workflowCurrentStageId && $workflowAllStages->count() > 0) {
            $currentStageRow = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
            $workflowCurrentStageName = $currentStageRow ? $currentStageRow->name : null;
        }
        ?>

        @if($workflowSelectedMatter)
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-card in-progress-section">
                        <div class="in-progress-single-line">
                            <h5 class="in-progress-title">
                                @if(isset($workflowSelectedMatter->matter_status) && $workflowSelectedMatter->matter_status == 1)
                                    Active
                                @else
                                    In-active
                                @endif
                            </h5>
                            <div class="current-stage-info">
                                <label class="stage-label">Current Stage:</label>
                                <div class="stage-value-container">
                                    <span class="stage-value">
                                        @if($workflowCurrentStageId)
                                            @php
                                                $workflowCurrentStage = $workflowAllStages->where('id', $workflowCurrentStageId)->first();
                                            @endphp
                                            {{ $workflowCurrentStage ? $workflowCurrentStage->name : 'N/A' }}
                                        @else
                                            N/A
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div class="overall-progress-container">
                                <label class="progress-label">Overall Progress:</label>
                                <div class="progress-circle-wrapper">
                                    @php
                                        $workflowTotalStages = $workflowAllStages->count();
                                        $workflowCurrentStageRow = $workflowCurrentStageId ? $workflowAllStages->firstWhere('id', $workflowCurrentStageId) : null;
                                        $workflowCurrentSortVal = $workflowCurrentStageRow ? ($workflowCurrentStageRow->sort_order ?? $workflowCurrentStageRow->id) : null;
                                        $workflowCurrentStageIndex = $workflowCurrentSortVal !== null ? $workflowAllStages->where(fn($s) => ($s->sort_order ?? $s->id) <= $workflowCurrentSortVal)->count() : 0;
                                        $workflowProgressPercentage = $workflowTotalStages > 0 ? round(($workflowCurrentStageIndex / $workflowTotalStages) * 100) : 0;
                                    @endphp
                                    <div class="progress-circle" data-progress="{{ $workflowProgressPercentage }}">
                                        <svg class="progress-ring" width="80" height="80">
                                            <circle class="progress-ring-circle-bg" cx="40" cy="40" r="36" fill="transparent" stroke="#e9ecef" stroke-width="6"/>
                                            <circle class="progress-ring-circle" cx="40" cy="40" r="36" fill="transparent" stroke="#007bff" stroke-width="6" stroke-dasharray="{{ 2 * M_PI * 36 }}" stroke-dashoffset="{{ 2 * M_PI * 36 * (1 - $workflowProgressPercentage / 100) }}"/>
                                        </svg>
                                        <div class="progress-text">{{ $workflowProgressPercentage }}%</div>
                                    </div>
                                </div>
                            </div>
                            <div class="deadline-section mt-3">
                                <div class="form-group mb-0">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="workflow-set-deadline" data-matter-id="{{ $workflowSelectedMatter->id }}"
                                            {{ $workflowSelectedMatter->deadline ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="workflow-set-deadline">Set Deadline</label>
                                    </div>
                                    <div class="workflow-deadline-date-wrapper mt-2" style="{{ $workflowSelectedMatter->deadline ? '' : 'display: none;' }}">
                                        <label for="workflow-deadline-date" class="sr-only">Deadline Date</label>
                                        <input type="date" class="form-control form-control-sm" id="workflow-deadline-date"
                                            value="{{ $workflowSelectedMatter->deadline ? \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('Y-m-d') : '' }}"
                                            data-matter-id="{{ $workflowSelectedMatter->id }}"
                                            style="max-width: 180px;">
                                        <small class="form-text text-muted">Select the matter deadline date.</small>
                                    </div>
                                    @if($workflowSelectedMatter->deadline)
                                        <div class="mt-2">
                                            <span class="badge badge-info">@icon('fa-calendar-alt') Deadline: {{ \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('d/m/Y') }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="stage-navigation-buttons">
                                @php
                                    $workflowIsDiscontinued = ($workflowSelectedMatter->matter_status ?? 1) == 0;
                                    $workflowCanReopen = in_array((int) (Auth::guard('admin')->user()->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);
                                @endphp
                                @if($workflowIsDiscontinued)
                                    {{-- Discontinued matter: show Reopen (same roles as discontinue), Change Workflow --}}
                                    @if($workflowCanReopen)
                                    <button class="btn btn-primary btn-sm matter-detail-reopen-btn" id="workflow-tab-reopen" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Reopen Matter">
                                        @icon('fa-redo') Reopen
                                    </button>
                                    @endif
                                    <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}" title="Change workflow for this matter">
                                        @icon('fa-exchange-alt') Change Workflow
                                    </button>
                                @else
                                    {{-- Active matter: show normal workflow buttons --}}
                                    @php
                                        $workflowIsFirstStage = false;
                                        $workflowNextStageName = null;
                                        $workflowNextStage = null;
                                        if ($workflowCurrentStageId && $workflowAllStages->count() > 0) {
                                            $workflowFirstStage = $workflowAllStages->first();
                                            $workflowIsFirstStage = ($workflowCurrentStageId == $workflowFirstStage->id);
                                            $workflowCurrentOrder = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                                            $workflowCurrentSort = $workflowCurrentOrder ? ($workflowCurrentOrder->sort_order ?? $workflowCurrentOrder->id) : null;
                                            $workflowNextStage = $workflowCurrentSort !== null ? $workflowAllStages->first(fn($s) => ($s->sort_order ?? $s->id) > $workflowCurrentSort) : $workflowAllStages->where('id', '>', $workflowCurrentStageId)->first();
                                            $workflowNextStageName = $workflowNextStage ? $workflowNextStage->name : null;
                                        }
                                        $workflowLastStage = $workflowAllStages->last();
                                        $workflowIsLastStage = $workflowNextStage === null;
                                    @endphp
                                    <button class="btn btn-outline-primary btn-sm" id="workflow-tab-back-to-previous-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Back to Previous Stage" {{ $workflowIsFirstStage ? 'disabled' : '' }}>
                                        @icon('fa-angle-left') Back to Previous Stage
                                    </button>
                                    @php
                                        $workflowNextBtnDisabled = $workflowIsLastStage;
                                        $workflowAdminForDiscontinue = Auth::guard('admin')->user();
                                        $workflowCanDiscontinue = $workflowAdminForDiscontinue
                                            && in_array((int) ($workflowAdminForDiscontinue->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);
                                    @endphp
                                    <button class="btn btn-success btn-sm" id="workflow-tab-proceed-to-next-stage" data-matter-id="{{ $workflowSelectedMatter->id }}" data-next-stage-name="{{ $workflowNextStageName ?? '' }}" data-current-stage-name="{{ $workflowCurrentStageName ?? '' }}" title="Proceed to Next Stage" {{ $workflowNextBtnDisabled ? 'disabled' : '' }}>
                                        Proceed to Next Stage @icon('fa-angle-right')
                                    </button>
                                    @if($workflowCanDiscontinue)
                                        <button class="btn btn-outline-danger btn-sm" id="workflow-tab-discontinue" data-matter-id="{{ $workflowSelectedMatter->id }}" title="Discontinue Matter">
                                            @icon('fa-ban') Discontinue
                                        </button>
                                    @endif
                                    <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow" data-matter-id="{{ $workflowSelectedMatter->id }}" data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}" title="Change workflow for this matter">
                                        @icon('fa-exchange-alt') Change Workflow
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="info-card">
                        <h5>
                            @icon('fa-folder-open') {{ $workflowMatterName }} ({{ $workflowMatterNumber }})
                        </h5>

                        @if($workflowAllStages->count() > 0)
                            <div class="workflow-stages-container mt-3">
                                <div class="workflow-stages-list">
                                    @foreach($workflowAllStages as $stage)
                                        @php
                                            $wfIsActive = ($workflowCurrentStageId && $workflowCurrentStageId == $stage->id);
                                            $stageSort = $stage->sort_order ?? $stage->id;
                                            $currentStageRow = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                                            $currentStageSort = $currentStageRow ? ($currentStageRow->sort_order ?? $currentStageRow->id) : null;
                                            $wfIsCompleted = ($workflowCurrentStageId && $currentStageSort !== null && $stageSort < $currentStageSort);
                                            $wfStageClass = $wfIsActive ? 'workflow-stage-active' : ($wfIsCompleted ? 'workflow-stage-completed' : 'workflow-stage-pending');
                                        @endphp
                                        <div class="workflow-stage-item {{ $wfStageClass }}">
                                            <span class="stage-name">{{ $stage->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <p class="text-muted">No workflow stages defined. Add stages from Admin Console → Workflows.</p>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="row mt-3">
                <div class="col-md-12">
                    <p class="text-muted">No matter selected. Please select a matter from the sidebar dropdown.</p>
                </div>
            </div>
        @endif
    </div>
</div>
