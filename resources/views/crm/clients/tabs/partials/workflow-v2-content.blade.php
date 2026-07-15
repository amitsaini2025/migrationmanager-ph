@php
    $wfShowHeader = $wfShowHeader ?? true;
    $wfShowToolbar = $wfShowToolbar ?? true;
    $wfShowHeaderAdvance = $wfShowHeaderAdvance ?? false;
    $wfShowFooterAdvance = $wfShowFooterAdvance ?? true;
    $wfShowPortalIdentity = $wfShowPortalIdentity ?? false;
    $wfChecklistInteractive = $wfChecklistInteractive ?? false;
    $wfAdvanceButtonId = $wfAdvanceButtonId ?? 'workflow-tab-proceed-to-next-stage';
    $wfBackButtonId = $wfBackButtonId ?? 'workflow-tab-back-to-previous-stage';
    $wfReopenButtonId = $wfReopenButtonId ?? 'workflow-tab-reopen';
    $wfChangeWorkflowButtonId = $wfChangeWorkflowButtonId ?? 'workflow-tab-change-workflow';
    $wfDiscontinueButtonId = $wfDiscontinueButtonId ?? 'workflow-tab-discontinue';
    $activeChecklistIndex = $activeChecklistIndex ?? \App\Support\WorkflowV2Display::activeChecklistIndex($checklistRows ?? []);
    $currentStageOutstanding = $currentStageOutstanding ?? $outstandingRequired ?? 0;
    $wfAdvanceDisabled = !empty($nextBtnDisabled)
        || ($wfChecklistInteractive && (int) $currentStageOutstanding > 0);
    $wfViewIsCurrent = $viewStageId && $currentStageId && (int) $viewStageId === (int) $currentStageId;
    $wfViewIsPrevious = false;
    if ($viewStageId && $currentStageId && !$wfViewIsCurrent) {
        $wfViewStageRow = $allStages->firstWhere('id', $viewStageId);
        $wfCurrentStageRow = $allStages->firstWhere('id', $currentStageId);
        if ($wfViewStageRow && $wfCurrentStageRow) {
            $wfViewSort = $wfViewStageRow->sort_order ?? $wfViewStageRow->id;
            $wfCurrentSort = $wfCurrentStageRow->sort_order ?? $wfCurrentStageRow->id;
            $wfViewIsPrevious = $wfViewSort < $wfCurrentSort;
        }
    }
@endphp

@if($matter)
    @if($wfShowHeader)
        @php
            $wfMatterNameLen = mb_strlen(trim((string) ($matterName ?? '')));
            $wfTitleSizeClass = $wfMatterNameLen > 50
                ? 'is-xlong'
                : ($wfMatterNameLen > 35 ? 'is-long' : ($wfMatterNameLen > 22 ? 'is-medium' : ''));
        @endphp
        <div class="workflow-v2-header">
            @if($wfShowPortalIdentity)
                <div class="workflow-v2-portal-identity">
                    @icon('fa-globe')
                    <span class="workflow-v2-portal-identity-label">Client Portal Access</span>
                </div>
            @endif
            <h2 class="workflow-v2-case-title {{ $wfTitleSizeClass }}">
                {{ $matterName }}
                <span class="workflow-v2-case-sep" aria-hidden="true">&middot;</span>
                {{ $matterNumber }}
            </h2>

            <div class="workflow-v2-header-meta">
                <div class="workflow-v2-meta-item">
                    <span class="workflow-v2-meta-label">Client</span>
                    <span class="workflow-v2-meta-value">{{ $clientDisplayName }}</span>
                </div>
                @if($marnNumber)
                    <div class="workflow-v2-meta-item">
                        <span class="workflow-v2-meta-label">RMA / MARN</span>
                        <span class="workflow-v2-meta-value">{{ $marnNumber }}</span>
                    </div>
                @endif
                <div class="workflow-v2-meta-item">
                    <span class="workflow-v2-meta-label">Current stage</span>
                    <span class="workflow-v2-meta-value">{{ $currentStageName ?? 'N/A' }}</span>
                </div>
            </div>

            <div class="workflow-v2-header-progress">
                <div class="workflow-v2-progress-top">
                    <span class="workflow-v2-progress-label">File Progress</span>
                    <span class="workflow-v2-progress-value">{{ $progressPercentage }}%</span>
                </div>
                <div class="workflow-v2-progress-bar">
                    <div class="workflow-v2-progress-bar-fill" style="width: {{ $progressPercentage }}%;"></div>
                </div>

                @if($wfShowToolbar)
                    <div class="workflow-v2-header-actions">
                        @if($isDiscontinued)
                            @if($canReopen)
                                <button type="button"
                                    class="workflow-v2-header-icon-btn workflow-v2-header-icon-btn--primary matter-detail-reopen-btn"
                                    id="{{ $wfReopenButtonId }}"
                                    data-matter-id="{{ $matter->id }}"
                                    data-tooltip="Reopen Matter"
                                    title="Reopen Matter"
                                    aria-label="Reopen Matter">
                                    <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                                </button>
                            @endif
                        @else
                            <button type="button"
                                class="workflow-v2-header-icon-btn"
                                id="{{ $wfBackButtonId }}"
                                data-matter-id="{{ $matter->id }}"
                                data-tooltip="Back to Previous Stage"
                                title="Back to Previous Stage"
                                aria-label="Back to Previous Stage"
                                {{ $isFirstStage ? 'disabled' : '' }}>
                                <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            @if($wfShowHeaderAdvance)
                                <button type="button"
                                    class="workflow-v2-header-icon-btn workflow-v2-header-icon-btn--primary js-workflow-advance-btn"
                                    id="{{ $wfShowFooterAdvance ? $wfAdvanceButtonId . '-header' : $wfAdvanceButtonId }}"
                                    data-matter-id="{{ $matter->id }}"
                                    data-next-stage-name="{{ $nextStageName ?? '' }}"
                                    data-current-stage-name="{{ $currentStageName ?? '' }}"
                                    data-tooltip="Proceed to Next Stage"
                                    title="Proceed to Next Stage"
                                    aria-label="Proceed to Next Stage"
                                    {{ $wfAdvanceDisabled ? 'disabled' : '' }}>
                                    <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                                </button>
                            @endif
                            @if($canDiscontinue)
                                <button type="button"
                                    class="workflow-v2-header-icon-btn workflow-v2-header-icon-btn--danger"
                                    id="{{ $wfDiscontinueButtonId }}"
                                    data-matter-id="{{ $matter->id }}"
                                    data-tooltip="Discontinue"
                                    title="Discontinue"
                                    aria-label="Discontinue">
                                    <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                                </button>
                            @endif
                        @endif
                        <button type="button"
                            class="workflow-v2-header-icon-btn"
                            id="{{ $wfChangeWorkflowButtonId }}"
                            data-matter-id="{{ $matter->id }}"
                            data-current-workflow-id="{{ $matter->workflow_id ?? '' }}"
                            data-tooltip="Change Workflow"
                            title="Change Workflow"
                            aria-label="Change Workflow">
                            <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 3 4 4-4 4"/><path d="M20 7H4"/><path d="m8 21-4-4 4-4"/><path d="M4 17h16"/></svg>
                        </button>
                        @if(!$isDiscontinued)
                            @php
                                $wfDeadlineYmd = $matter->deadline
                                    ? \Carbon\Carbon::parse($matter->deadline)->format('Y-m-d')
                                    : '';
                                $wfDeadlineDisplay = $matter->deadline
                                    ? \Carbon\Carbon::parse($matter->deadline)->format('d/m/Y')
                                    : '';
                            @endphp
                            <div class="workflow-v2-header-deadline-control {{ $wfDeadlineYmd ? 'has-deadline' : '' }}">
                                <button type="button"
                                    class="workflow-v2-header-icon-btn {{ $wfDeadlineYmd ? 'workflow-v2-header-icon-btn--deadline-active' : '' }}"
                                    id="workflow-set-deadline"
                                    data-matter-id="{{ $matter->id }}"
                                    data-current-deadline="{{ $wfDeadlineYmd }}"
                                    @if(!$wfDeadlineDisplay)
                                        data-tooltip="Set Deadline"
                                        title="Set Deadline"
                                    @endif
                                    aria-label="{{ $wfDeadlineDisplay ? 'Deadline: ' . $wfDeadlineDisplay . '. Click to change.' : 'Set Deadline' }}">
                                    <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                                </button>
                                @if($wfDeadlineDisplay)
                                    <span class="workflow-v2-header-deadline-display"
                                        id="workflow-deadline-display"
                                        aria-hidden="true">{{ $wfDeadlineDisplay }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($allStages->count() > 0)
        <div class="workflow-v2-body" id="workflow-v2-body"
            data-current-stage-id="{{ $currentStageId ?? '' }}"
            data-total-stages="{{ $totalStages }}"
            data-matter-id="{{ $matter->id }}"
            data-checklist-interactive="{{ $wfChecklistInteractive ? '1' : '0' }}">
            <aside class="workflow-v2-stages">
                <h3 class="workflow-v2-stages-title">Case Stages</h3>
                <ul class="workflow-v2-stages-list" id="workflow-v2-stages-list">
                    @foreach($allStages as $stageIndex => $stage)
                        @php
                            $wfIsCurrent = ($currentStageId && $currentStageId == $stage->id);
                            $wfIsViewing = ($viewStageId && $viewStageId == $stage->id);
                            $stageSort = $stage->sort_order ?? $stage->id;
                            $currentStageRowForList = $allStages->firstWhere('id', $currentStageId);
                            $currentStageSortForList = $currentStageRowForList
                                ? ($currentStageRowForList->sort_order ?? $currentStageRowForList->id)
                                : null;
                            $wfIsCompleted = ($currentStageId && $currentStageSortForList !== null && $stageSort < $currentStageSortForList);
                            $wfIsFuture = !$wfIsCurrent && !$wfIsCompleted;
                            $wfIsProtected = \App\Support\WorkflowV2Display::stageIsProtected($stage);
                            $wfShowLockIcon = $wfChecklistInteractive
                                ? $wfIsFuture
                                : ($wfIsProtected && !$wfIsCurrent && !$wfIsCompleted);
                            $wfStageClickable = !$wfChecklistInteractive || !$wfIsFuture;
                            $itemClass = trim(
                                ($wfIsCurrent ? 'is-active ' : '')
                                . ($wfIsCompleted ? 'is-completed ' : '')
                                . ($wfIsFuture ? 'is-future ' : '')
                                . ($wfIsViewing ? 'is-viewing ' : '')
                                . ($wfStageClickable ? 'is-clickable' : 'is-locked')
                            );
                        @endphp
                        <li class="workflow-v2-stage-item {{ $itemClass }}"
                            @if($wfStageClickable) role="button" tabindex="0" @else role="presentation" aria-disabled="true" @endif
                            data-stage-id="{{ $stage->id }}"
                            data-stage-index="{{ $stageIndex + 1 }}"
                            data-stage-status="{{ $wfIsCurrent ? 'current' : ($wfIsCompleted ? 'completed' : 'future') }}"
                            aria-label="{{ $wfStageClickable ? 'View stage' : 'Locked stage' }} {{ $stageIndex + 1 }}: {{ $stage->name }}"
                            aria-current="{{ $wfIsViewing ? 'step' : 'false' }}">
                            <span class="workflow-v2-stage-num">{{ $stageIndex + 1 }}</span>
                            <span class="workflow-v2-stage-name">{{ $stage->name }}</span>
                            @if($wfIsCompleted)
                                <span class="workflow-v2-stage-lock" title="Completed" aria-hidden="true">
                                    <svg class="lucide icon workflow-v2-stage-lock-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                            @elseif($wfShowLockIcon)
                                <span class="workflow-v2-stage-lock" title="{{ $wfIsProtected ? 'Protected stage' : 'Locked stage' }}" aria-hidden="true">
                                    <svg class="lucide icon workflow-v2-stage-lock-icon" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </aside>

            <main class="workflow-v2-panel" id="workflow-v2-panel" data-view-stage-id="{{ $viewStageId ?? '' }}">
                <div class="workflow-v2-panel-eyebrow" id="workflow-v2-panel-eyebrow">
                    Stage {{ $viewStageIndex }} of {{ $totalStages }}
                </div>
                <h2 class="workflow-v2-panel-title" id="workflow-v2-panel-title">{{ $viewStageName ?? 'N/A' }}</h2>

                <div id="workflow-v2-panel-badges" class="workflow-v2-badges"
                    style="{{ ($stageDisplay && !empty($stageDisplay['pending_from'])) ? '' : 'display:none;' }}">
                    @if($stageDisplay && !empty($stageDisplay['pending_from']))
                        <span class="workflow-v2-badge pending">
                            @icon('fa-hourglass-half') Pending from: <span id="workflow-v2-pending-from">{{ $stageDisplay['pending_from'] }}</span>
                        </span>
                    @endif
                </div>

                <div id="workflow-v2-panel-completion-rule" class="workflow-v2-completion-rule"
                    style="{{ ($stageDisplay && !empty($stageDisplay['completion_rule'])) ? '' : 'display:none;' }}">
                    @if($stageDisplay && !empty($stageDisplay['completion_rule']))
                        <strong>Completion rule:</strong> <span id="workflow-v2-completion-rule-text">{{ $stageDisplay['completion_rule'] }}</span>
                    @endif
                </div>

                <h3 class="workflow-v2-section-label">Checklist</h3>
                <div id="workflow-v2-checklist-container"
                    data-readonly="{{ (!$wfChecklistInteractive || !$wfViewIsCurrent) ? '1' : '0' }}">
                    @if(count($checklistRows) > 0)
                        <div class="workflow-v2-checklist" id="workflow-v2-checklist">
                            @foreach($checklistRows as $checkIndex => $checkItem)
                                @php
                                    $itemDone = !empty($checkItem['done']);
                                    $itemRequired = !empty($checkItem['required']);
                                    $itemId = $checkItem['id'] ?? null;
                                    $itemActive = $wfChecklistInteractive
                                        && $wfViewIsCurrent
                                        && !$itemDone
                                        && (int) $activeChecklistIndex === (int) $checkIndex
                                        && !empty($itemId);
                                    $itemDisabled = !$itemActive;
                                @endphp
                                <div class="workflow-v2-checklist-item {{ $itemDone ? 'is-done' : '' }} {{ $itemActive ? 'is-active-item' : '' }} {{ $itemDisabled && !$itemDone ? 'is-locked-item' : '' }}"
                                    data-checklist-id="{{ $itemId ?? '' }}"
                                    data-checklist-index="{{ $checkIndex }}"
                                    data-required="{{ $itemRequired ? '1' : '0' }}">
                                    <input type="checkbox"
                                        class="workflow-v2-checklist-checkbox"
                                        {{ $itemDone ? 'checked' : '' }}
                                        {{ $itemDisabled ? 'disabled' : '' }}
                                        data-checklist-id="{{ $itemId ?? '' }}"
                                        aria-label="{{ $checkItem['label'] }}">
                                    <span class="workflow-v2-checklist-label">{{ $checkItem['label'] }}</span>
                                    @if($itemRequired)
                                        <span class="workflow-v2-required-badge">Required</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="workflow-v2-checklist-empty" id="workflow-v2-checklist-empty">
                            No checklist items for this stage.
                            Add items from Client Portal &rarr; Documents, or configure templates in Admin Console.
                        </div>
                    @endif
                </div>

                <div id="workflow-v2-file-note-section" class="workflow-v2-file-note"
                    style="{{ ($stageDisplay && !empty($stageDisplay['file_note_section'])) ? '' : 'display:none;' }}">
                    <h3 class="workflow-v2-section-label">File Note (Record Keeping)</h3>
                    <div id="workflow-v2-file-note-history"
                        class="workflow-v2-file-note-history {{ empty($fileNoteBody) ? 'is-empty' : '' }}"
                        @if(empty($fileNoteBody)) style="display:none;" @endif>{{ $fileNoteBody ?? '' }}</div>
                    <textarea id="workflow-v2-file-note-input"
                        rows="4"
                        placeholder="e.g. {{ now()->format('d/m/y') }} — client emailed re signed agreement..."
                        aria-label="Workflow file note"
                        {{ ($wfChecklistInteractive && ($wfViewIsPrevious || !$wfViewIsCurrent)) ? 'disabled' : '' }}></textarea>
                    <p class="workflow-v2-file-note-hint">Auto-stamped with user + timestamp when you advance to the next stage.</p>
                </div>

                <div class="workflow-v2-footer">
                    <div id="workflow-v2-footer-outstanding"
                        class="workflow-v2-outstanding {{ $outstandingRequired > 0 ? '' : 'is-clear' }}">
                        <span class="workflow-v2-outstanding-dot"></span>
                        <span id="workflow-v2-outstanding-text">
                            @if($outstandingRequired > 0)
                                {{ $outstandingRequired }} Required item{{ $outstandingRequired === 1 ? '' : 's' }} outstanding
                            @else
                                All required items complete
                            @endif
                        </span>
                    </div>

                    @if($wfShowFooterAdvance && !$isDiscontinued)
                        <button class="workflow-v2-advance-btn js-workflow-advance-btn"
                            id="{{ $wfAdvanceButtonId }}"
                            data-matter-id="{{ $matter->id }}"
                            data-next-stage-name="{{ $nextStageName ?? '' }}"
                            data-current-stage-name="{{ $currentStageName ?? '' }}"
                            title="Proceed to Next Stage"
                            style="{{ ($viewStageId && $currentStageId && (int) $viewStageId === (int) $currentStageId) ? '' : 'display:none;' }}"
                            {{ $wfAdvanceDisabled ? 'disabled' : '' }}>
                            Advance to next stage &rarr;
                        </button>
                    @endif
                </div>
            </main>
        </div>

        @if(!empty($stagesPayload))
            <script type="application/json" id="workflow-v2-stages-data">
                {!! json_encode([
                    'stages' => $stagesPayload,
                    'currentStageId' => $currentStageId,
                    'totalStages' => $totalStages,
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
            </script>
        @endif
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
