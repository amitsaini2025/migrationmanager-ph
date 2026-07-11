@php
    $cpPortalIsActive = isset($fetchedData->cp_status) && in_array($fetchedData->cp_status, [1, 2]);
    $cpHasWfHeader = !empty($cpActivitiesWf) && !empty($cpActivitiesWf['matter']);
    if ($cpHasWfHeader) {
        extract($cpActivitiesWf, EXTR_SKIP);
        $wfMatterNameLen = mb_strlen(trim((string) ($matterName ?? '')));
        $wfTitleSizeClass = $wfMatterNameLen > 50
            ? 'is-xlong'
            : ($wfMatterNameLen > 35 ? 'is-long' : ($wfMatterNameLen > 22 ? 'is-medium' : ''));
    }
@endphp

<div class="workflow-v2 workflow-v2--client-portal-page">
    <div class="workflow-v2-header workflow-v2-header--client-portal-page">
        @if($cpHasWfHeader)
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
        @else
            <h2 class="workflow-v2-case-title workflow-v2-case-title--portal-fallback">
                @icon('fa-globe')
                Client Portal Access
            </h2>
        @endif

        <div class="workflow-v2-header-progress workflow-v2-header-progress--client-portal">
            @if($cpHasWfHeader)
                <div class="workflow-v2-progress-top">
                    <span class="workflow-v2-progress-label">File Progress</span>
                    <span class="workflow-v2-progress-value">{{ $progressPercentage }}%</span>
                </div>
                <div class="workflow-v2-progress-bar">
                    <div class="workflow-v2-progress-bar-fill" style="width: {{ $progressPercentage }}%;"></div>
                </div>
            @endif

            <div class="workflow-v2-header-bottom workflow-v2-header-bottom--client-portal">
                @if($cpHasWfHeader)
                    <div class="workflow-v2-header-actions">
                        @if($isDiscontinued)
                            @if($canReopen)
                                <button type="button"
                                    class="workflow-v2-header-icon-btn workflow-v2-header-icon-btn--primary matter-detail-reopen-btn client-portal-reopen-btn"
                                    id="client-portal-reopen"
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
                                id="back-to-previous-stage"
                                data-matter-id="{{ $matter->id }}"
                                data-tooltip="Back to Previous Stage"
                                title="Back to Previous Stage"
                                aria-label="Back to Previous Stage"
                                {{ $isFirstStage ? 'disabled' : '' }}>
                                <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <button type="button"
                                class="workflow-v2-header-icon-btn workflow-v2-header-icon-btn--primary"
                                id="proceed-to-next-stage"
                                data-matter-id="{{ $matter->id }}"
                                data-next-stage-name="{{ $nextStageName ?? '' }}"
                                data-current-stage-name="{{ $currentStageName ?? '' }}"
                                data-tooltip="Proceed to Next Stage"
                                title="Proceed to Next Stage"
                                aria-label="Proceed to Next Stage"
                                {{ $nextBtnDisabled ? 'disabled' : '' }}>
                                <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                            @if($canDiscontinue)
                                <button type="button"
                                    class="workflow-v2-header-icon-btn workflow-v2-header-icon-btn--danger client-portal-discontinue-btn"
                                    data-matter-id="{{ $matter->id }}"
                                    data-tooltip="Discontinue"
                                    title="Discontinue"
                                    aria-label="Discontinue">
                                    <svg class="lucide icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                                </button>
                            @endif
                        @endif
                    </div>
                @else
                    <div class="workflow-v2-header-actions"></div>
                @endif

                <div class="workflow-v2-portal-controls">
                <div class="workflow-v2-portal-status-badge">
                    @if($cpPortalIsActive)
                        <span class="badge badge-success">@icon('fa-check-circle') Active</span>
                    @else
                        <span class="badge badge-secondary">@icon('fa-times-circle') Inactive</span>
                    @endif
                </div>

                @if($client_matters_exist)
                    <div class="workflow-v2-portal-toggle">
                        <label class="workflow-v2-portal-toggle-label">
                            <span class="workflow-v2-portal-toggle-text">Portal Access:</span>
                            <div class="toggle-switch">
                                <input type="checkbox"
                                    id="client-portal-toggle-tab"
                                    data-client-id="{{ $fetchedData->id }}"
                                    {{ $cpPortalIsActive ? 'checked' : '' }}>
                                <span class="toggle-slider"></span>
                            </div>
                            <span class="portal-toggle-loader" id="portal-toggle-loader-tab" style="display: none;">
                                @icon('fa-spinner', ['spin' => true])
                            </span>
                        </label>
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>
</div>