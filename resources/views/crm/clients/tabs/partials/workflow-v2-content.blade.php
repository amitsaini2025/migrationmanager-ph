@php
    $wfShowHeader = $wfShowHeader ?? true;
    $wfShowToolbar = $wfShowToolbar ?? true;
    $wfShowFooterAdvance = $wfShowFooterAdvance ?? true;
    $wfAdvanceButtonId = $wfAdvanceButtonId ?? 'workflow-tab-proceed-to-next-stage';
    $wfBackButtonId = $wfBackButtonId ?? 'workflow-tab-back-to-previous-stage';
    $wfReopenButtonId = $wfReopenButtonId ?? 'workflow-tab-reopen';
    $wfChangeWorkflowButtonId = $wfChangeWorkflowButtonId ?? 'workflow-tab-change-workflow';
    $wfDiscontinueButtonId = $wfDiscontinueButtonId ?? 'workflow-tab-discontinue';
@endphp

@if($matter)
    @if($wfShowHeader)
        <div class="workflow-v2-header">
            <div class="workflow-v2-header-main">
                <h2 class="workflow-v2-case-title">
                    {{ $clientId }} &mdash; {{ $matterName }} &middot; {{ $matterNumber }}
                </h2>
                <div class="workflow-v2-header-meta">
                    <span>Client: <strong>{{ $clientDisplayName }}</strong></span>
                    @if($marnNumber)
                        <span>RMA / MARN: <span class="workflow-v2-marn">{{ $marnNumber }}</span></span>
                    @endif
                    <span>Current stage: <strong>{{ $currentStageName ?? 'N/A' }}</strong></span>
                </div>
            </div>
            <div class="workflow-v2-header-progress">
                <div class="workflow-v2-progress-label">File Progress</div>
                <div class="workflow-v2-progress-value">{{ $progressPercentage }}%</div>
                <div class="workflow-v2-progress-bar">
                    <div class="workflow-v2-progress-bar-fill" style="width: {{ $progressPercentage }}%;"></div>
                </div>
            </div>
        </div>
    @endif

    @if($wfShowToolbar)
        <div class="workflow-v2-toolbar">
            <span class="workflow-v2-status-pill {{ $isActive ? 'is-active' : 'is-inactive' }}">
                {{ $isActive ? 'Active' : 'In-active' }}
            </span>

            <div class="workflow-v2-deadline">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="workflow-set-deadline"
                        data-matter-id="{{ $matter->id }}"
                        {{ $matter->deadline ? 'checked' : '' }}>
                    <label class="custom-control-label" for="workflow-set-deadline">Set Deadline</label>
                </div>
                <div class="workflow-deadline-date-wrapper workflow-v2-deadline-date-wrapper"
                    style="{{ $matter->deadline ? '' : 'display: none;' }}">
                    <label for="workflow-deadline-date" class="sr-only">Deadline Date</label>
                    <input type="date" class="form-control form-control-sm" id="workflow-deadline-date"
                        value="{{ $matter->deadline ? \Carbon\Carbon::parse($matter->deadline)->format('Y-m-d') : '' }}"
                        data-matter-id="{{ $matter->id }}">
                </div>
                @if($matter->deadline)
                    <span class="badge badge-info">
                        @icon('fa-calendar-alt') {{ \Carbon\Carbon::parse($matter->deadline)->format('d/m/Y') }}
                    </span>
                @endif
            </div>

            <div class="workflow-v2-toolbar-actions stage-navigation-buttons">
                @if($isDiscontinued)
                    @if($canReopen)
                        <button class="btn btn-primary btn-sm matter-detail-reopen-btn" id="{{ $wfReopenButtonId }}"
                            data-matter-id="{{ $matter->id }}" title="Reopen Matter">
                            @icon('fa-redo') Reopen
                        </button>
                    @endif
                    <button class="btn btn-outline-secondary btn-sm" id="{{ $wfChangeWorkflowButtonId }}"
                        data-matter-id="{{ $matter->id }}"
                        data-current-workflow-id="{{ $matter->workflow_id ?? '' }}"
                        title="Change workflow for this matter">
                        @icon('fa-exchange-alt') Change Workflow
                    </button>
                @else
                    <button class="btn btn-outline-primary btn-sm" id="{{ $wfBackButtonId }}"
                        data-matter-id="{{ $matter->id }}"
                        title="Back to Previous Stage"
                        {{ $isFirstStage ? 'disabled' : '' }}>
                        @icon('fa-angle-left') Back
                    </button>
                    @if($canDiscontinue)
                        <button class="btn btn-outline-danger btn-sm" id="{{ $wfDiscontinueButtonId }}"
                            data-matter-id="{{ $matter->id }}" title="Discontinue Matter">
                            @icon('fa-ban') Discontinue
                        </button>
                    @endif
                    <button class="btn btn-outline-secondary btn-sm" id="{{ $wfChangeWorkflowButtonId }}"
                        data-matter-id="{{ $matter->id }}"
                        data-current-workflow-id="{{ $matter->workflow_id ?? '' }}"
                        title="Change workflow for this matter">
                        @icon('fa-exchange-alt') Change Workflow
                    </button>
                @endif
            </div>
        </div>
    @endif

    @if($allStages->count() > 0)
        <div class="workflow-v2-body">
            <aside class="workflow-v2-stages">
                <h3 class="workflow-v2-stages-title">Case Stages</h3>
                <ul class="workflow-v2-stages-list">
                    @foreach($allStages as $stageIndex => $stage)
                        @php
                            $wfIsActive = ($currentStageId && $currentStageId == $stage->id);
                            $stageSort = $stage->sort_order ?? $stage->id;
                            $currentStageRowForList = $allStages->firstWhere('id', $currentStageId);
                            $currentStageSortForList = $currentStageRowForList
                                ? ($currentStageRowForList->sort_order ?? $currentStageRowForList->id)
                                : null;
                            $wfIsCompleted = ($currentStageId && $currentStageSortForList !== null && $stageSort < $currentStageSortForList);
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

            <main class="workflow-v2-panel">
                <div class="workflow-v2-panel-eyebrow">
                    Stage {{ $currentStageIndex }} of {{ $totalStages }}
                </div>
                <h2 class="workflow-v2-panel-title">{{ $currentStageName ?? 'N/A' }}</h2>

                @if($stageDisplay && !empty($stageDisplay['pending_from']))
                    <div class="workflow-v2-badges">
                        <span class="workflow-v2-badge pending">
                            @icon('fa-hourglass-half') Pending from: {{ $stageDisplay['pending_from'] }}
                        </span>
                    </div>
                @endif

                @if($stageDisplay && !empty($stageDisplay['completion_rule']))
                    <div class="workflow-v2-completion-rule">
                        <strong>Completion rule:</strong> {{ $stageDisplay['completion_rule'] }}
                    </div>
                @endif

                <h3 class="workflow-v2-section-label">Checklist</h3>
                @if(count($checklistRows) > 0)
                    <div class="workflow-v2-checklist">
                        @foreach($checklistRows as $checkItem)
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

                @if($stageDisplay && !empty($stageDisplay['file_note_section']))
                    <div class="workflow-v2-file-note">
                        <h3 class="workflow-v2-section-label">File Note (Record Keeping)</h3>
                        <textarea rows="4"
                            placeholder="e.g. {{ now()->format('d/m/y') }} — client emailed re signed agreement..."
                            aria-label="Workflow file note"></textarea>
                        <p class="workflow-v2-file-note-hint">Auto-stamped with user + timestamp on save.</p>
                    </div>
                @endif

                <div class="workflow-v2-footer">
                    @if($outstandingRequired > 0)
                        <div class="workflow-v2-outstanding">
                            <span class="workflow-v2-outstanding-dot"></span>
                            {{ $outstandingRequired }} Required item{{ $outstandingRequired === 1 ? '' : 's' }} outstanding
                        </div>
                    @else
                        <div class="workflow-v2-outstanding is-clear">
                            <span class="workflow-v2-outstanding-dot"></span>
                            All required items complete
                        </div>
                    @endif

                    @if($wfShowFooterAdvance && !$isDiscontinued)
                        <button class="workflow-v2-advance-btn"
                            id="{{ $wfAdvanceButtonId }}"
                            data-matter-id="{{ $matter->id }}"
                            data-next-stage-name="{{ $nextStageName ?? '' }}"
                            data-current-stage-name="{{ $currentStageName ?? '' }}"
                            title="Proceed to Next Stage"
                            {{ $nextBtnDisabled ? 'disabled' : '' }}>
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
