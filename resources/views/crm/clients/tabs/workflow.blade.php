<link rel="stylesheet" href="{{ URL::asset('css/workflow-tab.css') }}?v={{ time() }}">

<!-- Workflow Tab — redesigned case workflow UI -->
<div class="tab-pane" id="workflow-tab">
    <div class="workflow-v2">
        <div class="card full-width workflow-tab-container">
            <?php
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

            $workflowTotalStages = $workflowAllStages->count();
            $workflowCurrentStageRow = $workflowCurrentStageId ? $workflowAllStages->firstWhere('id', $workflowCurrentStageId) : null;
            $workflowCurrentSortVal = $workflowCurrentStageRow ? ($workflowCurrentStageRow->sort_order ?? $workflowCurrentStageRow->id) : null;
            $workflowCurrentStageIndex = $workflowCurrentSortVal !== null
                ? $workflowAllStages->where(fn($s) => ($s->sort_order ?? $s->id) <= $workflowCurrentSortVal)->count()
                : 0;
            $workflowProgressPercentage = $workflowTotalStages > 0
                ? round(($workflowCurrentStageIndex / $workflowTotalStages) * 100)
                : 0;

            $workflowMarnNumber = null;
            if ($workflowSelectedMatter && !empty($workflowSelectedMatter->sel_migration_agent)) {
                $workflowMarnNumber = DB::table('staff')
                    ->where('id', $workflowSelectedMatter->sel_migration_agent)
                    ->value('marn_number');
            }

            $workflowClientDisplayName = trim(
                ($fetchedData->first_name ? mb_substr($fetchedData->first_name, 0, 1) . '. ' : '')
                . ($fetchedData->last_name ?? '')
            );

            $workflowStageDisplay = null;
            if ($workflowCurrentStageName) {
                $stageDefaults = config('workflow.stage_display_defaults', []);
                $stageNameKey = strtolower(trim($workflowCurrentStageName));
                foreach ($stageDefaults as $key => $meta) {
                    if (strtolower(trim($key)) === $stageNameKey) {
                        $workflowStageDisplay = $meta;
                        break;
                    }
                }
            }

            $workflowChecklistRows = [];
            $workflowOutstandingRequired = 0;

            if ($workflowSelectedMatter && $workflowCurrentStageName) {
                $cpChecklists = DB::table('cp_doc_checklists')
                    ->where('client_matter_id', $workflowSelectedMatter->id)
                    ->where('wf_stage', $workflowCurrentStageName)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($cpChecklists->count() > 0) {
                    foreach ($cpChecklists as $cpItem) {
                        $uploadCount = DB::table('documents')
                            ->where('cp_list_id', $cpItem->id)
                            ->where('type', 'workflow_checklist')
                            ->count();
                        $isDone = $uploadCount > 0;
                        $workflowChecklistRows[] = [
                            'label' => $cpItem->cp_checklist_name ?? 'Checklist item',
                            'required' => true,
                            'done' => $isDone,
                        ];
                        if (!$isDone) {
                            $workflowOutstandingRequired++;
                        }
                    }
                } elseif ($workflowStageDisplay && !empty($workflowStageDisplay['checklist_items'])) {
                    foreach ($workflowStageDisplay['checklist_items'] as $item) {
                        $workflowChecklistRows[] = [
                            'label' => $item['label'] ?? 'Item',
                            'required' => !empty($item['required']),
                            'done' => false,
                        ];
                        if (!empty($item['required'])) {
                            $workflowOutstandingRequired++;
                        }
                    }
                }
            }

            $workflowIsDiscontinued = $workflowSelectedMatter && ($workflowSelectedMatter->matter_status ?? 1) == 0;
            $workflowCanReopen = in_array(
                (int) (Auth::guard('admin')->user()->role ?? 0),
                config('crm.matter_discontinue_role_ids', [1, 17, 16]),
                true
            );

            $workflowIsFirstStage = false;
            $workflowNextStageName = null;
            $workflowNextStage = null;
            if ($workflowCurrentStageId && $workflowAllStages->count() > 0) {
                $workflowFirstStage = $workflowAllStages->first();
                $workflowIsFirstStage = ($workflowCurrentStageId == $workflowFirstStage->id);
                $workflowCurrentOrder = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                $workflowCurrentSort = $workflowCurrentOrder ? ($workflowCurrentOrder->sort_order ?? $workflowCurrentOrder->id) : null;
                $workflowNextStage = $workflowCurrentSort !== null
                    ? $workflowAllStages->first(fn($s) => ($s->sort_order ?? $s->id) > $workflowCurrentSort)
                    : $workflowAllStages->where('id', '>', $workflowCurrentStageId)->first();
                $workflowNextStageName = $workflowNextStage ? $workflowNextStage->name : null;
            }
            $workflowIsLastStage = $workflowNextStage === null;
            $workflowNextBtnDisabled = $workflowIsLastStage;

            $workflowAdminForDiscontinue = Auth::guard('admin')->user();
            $workflowCanDiscontinue = $workflowAdminForDiscontinue
                && in_array((int) ($workflowAdminForDiscontinue->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);

            $workflowIsActive = $workflowSelectedMatter && ($workflowSelectedMatter->matter_status ?? 1) == 1;
            ?>

            @if($workflowSelectedMatter)
                {{-- Dark header --}}
                <div class="workflow-v2-header">
                    <div class="workflow-v2-header-main">
                        <h2 class="workflow-v2-case-title">
                            {{ $fetchedData->client_id }} &mdash; {{ $workflowMatterName }} &middot; {{ $workflowMatterNumber }}
                        </h2>
                        <div class="workflow-v2-header-meta">
                            <span>Client: <strong>{{ $workflowClientDisplayName }}</strong></span>
                            @if($workflowMarnNumber)
                                <span>RMA / MARN: <span class="workflow-v2-marn">{{ $workflowMarnNumber }}</span></span>
                            @endif
                            <span>Current stage: <strong>{{ $workflowCurrentStageName ?? 'N/A' }}</strong></span>
                        </div>
                    </div>
                    <div class="workflow-v2-header-progress">
                        <div class="workflow-v2-progress-label">File Progress</div>
                        <div class="workflow-v2-progress-value">{{ $workflowProgressPercentage }}%</div>
                        <div class="workflow-v2-progress-bar">
                            <div class="workflow-v2-progress-bar-fill" style="width: {{ $workflowProgressPercentage }}%;"></div>
                        </div>
                    </div>
                </div>

                {{-- Toolbar: status, deadline, secondary actions --}}
                <div class="workflow-v2-toolbar">
                    <span class="workflow-v2-status-pill {{ $workflowIsActive ? 'is-active' : 'is-inactive' }}">
                        {{ $workflowIsActive ? 'Active' : 'In-active' }}
                    </span>

                    <div class="workflow-v2-deadline">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="workflow-set-deadline"
                                data-matter-id="{{ $workflowSelectedMatter->id }}"
                                {{ $workflowSelectedMatter->deadline ? 'checked' : '' }}>
                            <label class="custom-control-label" for="workflow-set-deadline">Set Deadline</label>
                        </div>
                        <div class="workflow-deadline-date-wrapper workflow-v2-deadline-date-wrapper"
                            style="{{ $workflowSelectedMatter->deadline ? '' : 'display: none;' }}">
                            <label for="workflow-deadline-date" class="sr-only">Deadline Date</label>
                            <input type="date" class="form-control form-control-sm" id="workflow-deadline-date"
                                value="{{ $workflowSelectedMatter->deadline ? \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('Y-m-d') : '' }}"
                                data-matter-id="{{ $workflowSelectedMatter->id }}">
                        </div>
                        @if($workflowSelectedMatter->deadline)
                            <span class="badge badge-info">
                                @icon('fa-calendar-alt') {{ \Carbon\Carbon::parse($workflowSelectedMatter->deadline)->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>

                    <div class="workflow-v2-toolbar-actions stage-navigation-buttons">
                        @if($workflowIsDiscontinued)
                            @if($workflowCanReopen)
                                <button class="btn btn-primary btn-sm matter-detail-reopen-btn" id="workflow-tab-reopen"
                                    data-matter-id="{{ $workflowSelectedMatter->id }}" title="Reopen Matter">
                                    @icon('fa-redo') Reopen
                                </button>
                            @endif
                            <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow"
                                data-matter-id="{{ $workflowSelectedMatter->id }}"
                                data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}"
                                title="Change workflow for this matter">
                                @icon('fa-exchange-alt') Change Workflow
                            </button>
                        @else
                            <button class="btn btn-outline-primary btn-sm" id="workflow-tab-back-to-previous-stage"
                                data-matter-id="{{ $workflowSelectedMatter->id }}"
                                title="Back to Previous Stage"
                                {{ $workflowIsFirstStage ? 'disabled' : '' }}>
                                @icon('fa-angle-left') Back
                            </button>
                            @if($workflowCanDiscontinue)
                                <button class="btn btn-outline-danger btn-sm" id="workflow-tab-discontinue"
                                    data-matter-id="{{ $workflowSelectedMatter->id }}" title="Discontinue Matter">
                                    @icon('fa-ban') Discontinue
                                </button>
                            @endif
                            <button class="btn btn-outline-secondary btn-sm" id="workflow-tab-change-workflow"
                                data-matter-id="{{ $workflowSelectedMatter->id }}"
                                data-current-workflow-id="{{ $workflowSelectedMatter->workflow_id ?? '' }}"
                                title="Change workflow for this matter">
                                @icon('fa-exchange-alt') Change Workflow
                            </button>
                        @endif
                    </div>
                </div>

                @if($workflowAllStages->count() > 0)
                    <div class="workflow-v2-body">
                        {{-- Left: stage sidebar --}}
                        <aside class="workflow-v2-stages">
                            <h3 class="workflow-v2-stages-title">Case Stages</h3>
                            <ul class="workflow-v2-stages-list">
                                @foreach($workflowAllStages as $stageIndex => $stage)
                                    @php
                                        $wfIsActive = ($workflowCurrentStageId && $workflowCurrentStageId == $stage->id);
                                        $stageSort = $stage->sort_order ?? $stage->id;
                                        $currentStageRowForList = $workflowAllStages->firstWhere('id', $workflowCurrentStageId);
                                        $currentStageSortForList = $currentStageRowForList
                                            ? ($currentStageRowForList->sort_order ?? $currentStageRowForList->id)
                                            : null;
                                        $wfIsCompleted = ($workflowCurrentStageId && $currentStageSortForList !== null && $stageSort < $currentStageSortForList);
                                        $wfIsLocked = !$wfIsActive && !$wfIsCompleted;
                                        $itemClass = $wfIsActive ? 'is-active' : ($wfIsCompleted ? 'is-completed' : '');
                                    @endphp
                                    <li class="workflow-v2-stage-item {{ $itemClass }}">
                                        <span class="workflow-v2-stage-num">{{ $stageIndex + 1 }}</span>
                                        <span class="workflow-v2-stage-name">{{ $stage->name }}</span>
                                        @if($wfIsCompleted)
                                            <span class="workflow-v2-stage-lock" title="Completed">@icon('fa-check')</span>
                                        @elseif($wfIsLocked)
                                            <span class="workflow-v2-stage-lock" title="Locked">@icon('fa-lock')</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </aside>

                        {{-- Right: current stage detail --}}
                        <main class="workflow-v2-panel">
                            <div class="workflow-v2-panel-eyebrow">
                                Stage {{ $workflowCurrentStageIndex }} of {{ $workflowTotalStages }}
                            </div>
                            <h2 class="workflow-v2-panel-title">{{ $workflowCurrentStageName ?? 'N/A' }}</h2>

                            @if($workflowStageDisplay && !empty($workflowStageDisplay['pending_from']))
                                <div class="workflow-v2-badges">
                                    <span class="workflow-v2-badge pending">
                                        @icon('fa-hourglass-half') Pending from: {{ $workflowStageDisplay['pending_from'] }}
                                    </span>
                                </div>
                            @endif

                            @if($workflowStageDisplay && !empty($workflowStageDisplay['completion_rule']))
                                <div class="workflow-v2-completion-rule">
                                    <strong>Completion rule:</strong> {{ $workflowStageDisplay['completion_rule'] }}
                                </div>
                            @endif

                            <h3 class="workflow-v2-section-label">Checklist</h3>
                            @if(count($workflowChecklistRows) > 0)
                                <div class="workflow-v2-checklist">
                                    @foreach($workflowChecklistRows as $checkItem)
                                        <div class="workflow-v2-checklist-item {{ !empty($checkItem['done']) ? 'is-done' : '' }}">
                                            <input type="checkbox"
                                                {{ !empty($checkItem['done']) ? 'checked' : '' }}
                                                disabled
                                                aria-label="{{ $checkItem['label'] }}">
                                            <span class="workflow-v2-checklist-label">{{ $checkItem['label'] }}</span>
                                            @if(!empty($checkItem['required']))
                                                <span class="workflow-v2-required-badge">Required</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="workflow-v2-checklist-empty">
                                    No checklist items for this stage.
                                    Add items from Client Portal &rarr; Documents, or configure defaults in Admin Console.
                                </div>
                            @endif

                            @if($workflowStageDisplay && !empty($workflowStageDisplay['file_note_section']))
                                <div class="workflow-v2-file-note">
                                    <h3 class="workflow-v2-section-label">File Note (Record Keeping)</h3>
                                    <textarea rows="4"
                                        placeholder="e.g. {{ now()->format('d/m/y') }} — client emailed re signed agreement..."
                                        aria-label="Workflow file note"></textarea>
                                    <p class="workflow-v2-file-note-hint">Auto-stamped with user + timestamp on save.</p>
                                </div>
                            @endif

                            <div class="workflow-v2-footer">
                                @if($workflowOutstandingRequired > 0)
                                    <div class="workflow-v2-outstanding">
                                        <span class="workflow-v2-outstanding-dot"></span>
                                        {{ $workflowOutstandingRequired }} Required item{{ $workflowOutstandingRequired === 1 ? '' : 's' }} outstanding
                                    </div>
                                @else
                                    <div class="workflow-v2-outstanding is-clear">
                                        <span class="workflow-v2-outstanding-dot"></span>
                                        All required items complete
                                    </div>
                                @endif

                                @if(!$workflowIsDiscontinued)
                                    <button class="workflow-v2-advance-btn"
                                        id="workflow-tab-proceed-to-next-stage"
                                        data-matter-id="{{ $workflowSelectedMatter->id }}"
                                        data-next-stage-name="{{ $workflowNextStageName ?? '' }}"
                                        data-current-stage-name="{{ $workflowCurrentStageName ?? '' }}"
                                        title="Proceed to Next Stage"
                                        {{ $workflowNextBtnDisabled ? 'disabled' : '' }}>
                                        Advance to next stage &rarr;
                                    </button>
                                @endif
                            </div>
                        </main>
                    </div>
                @else
                    <div class="workflow-v2-empty">
                        <p>No workflow stages defined. Add stages from Admin Console &rarr; Workflows.</p>
                    </div>
                @endif
            @else
                <div class="workflow-v2-empty">
                    <p>No matter selected. Please select a matter from the sidebar dropdown.</p>
                </div>
            @endif
        </div>
    </div>
</div>
