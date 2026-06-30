<!-- Client Portal Tab -->
<div class="tab-pane" id="client_portal-tab">
    <div class="card full-width client-portal-container">
        <div class="portal-header">
            <h3>@icon('fa-globe') Client Portal Access</h3>
            <div class="portal-header-controls">
                <div class="portal-status-badge">
                    @if(isset($fetchedData->cp_status) && in_array($fetchedData->cp_status, [1, 2]))
                        <span class="badge badge-success">@icon('fa-check-circle') Active</span>
                    @else
                        <span class="badge badge-secondary">@icon('fa-times-circle') Inactive</span>
                    @endif
                </div>
                
                <!-- Portal Toggle Switch -->
                <?php
                // Check if client has any records in client_matters table
                $client_matters_exist = DB::table('client_matters')
                    ->where('client_id', $fetchedData->id)
                    ->exists();
                ?>
                @if($client_matters_exist)
                <div class="portal-toggle-container">
                    <label class="portal-toggle-label">
                        <span class="toggle-text">Portal Access:</span>
                        <div class="toggle-switch">
                            <input type="checkbox" id="client-portal-toggle-tab" 
                                   data-client-id="{{ $fetchedData->id}}" 
                                   {{ isset($fetchedData->cp_status) && in_array($fetchedData->cp_status, [1, 2]) ? 'checked' : '' }}>
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

        <div class="portal-content">
            @if(isset($fetchedData->cp_status) && in_array($fetchedData->cp_status, [1, 2]))
                <!-- Portal is Active -->
                <?php
                // Get the selected matter based on URL parameter or latest active matter
                $selectedMatter = null;
                $matterName = '';
                $matterNumber = '';
                
                // Tab names that should NOT be treated as matter reference - use latest matter instead
                $validTabNames = ['personaldetails', 'activityfeed', 'noteterm', 'personaldocuments', 'visadocuments', 'eoiroi', 'emails', 'formgenerations', 'formgenerationsl', 'client_portal', 'workflow', 'checklists'];
                $isMatterIdInUrl = isset($id1) && $id1 != "" && !in_array(strtolower($id1), array_map('strtolower', $validTabNames));
                
                if ($isMatterIdInUrl) {
                    // If client unique reference id is present in URL (and is not a tab name)
                    $selectedMatter = DB::table('client_matters as cm')
                        ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $fetchedData->id)
                        ->where('cm.client_unique_matter_no', $id1)
                        ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_id', 'cm.workflow_stage_id', 'cm.matter_status', 'cm.sel_migration_agent')
                        ->first();
                } else {
                    // Get the latest matter (active or inactive) - used when no matter in URL or URL has tab name
                    $selectedMatter = DB::table('client_matters as cm')
                        ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $fetchedData->id)
                        ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_id', 'cm.workflow_stage_id', 'cm.matter_status', 'cm.sel_migration_agent')
                        ->orderBy('cm.id', 'desc')
                        ->first();
                }
                
                if($selectedMatter) {
                    // Determine matter name (use "General Matter" if sel_matter_id is 1 or title is null)
                    if($selectedMatter->sel_matter_id == 1 || empty($selectedMatter->title)) {
                        $matterName = 'General Matter';
                    } else {
                        $matterName = $selectedMatter->title;
                    }
                    $matterNumber = $selectedMatter->client_unique_matter_no;
                    $currentWorkflowStageId = $selectedMatter->workflow_stage_id;
                } else {
                    $currentWorkflowStageId = null;
                }
                
                // Get all workflow stages
                $allWorkflowStages = DB::table('workflow_stages')
                    ->where('workflow_id', $selectedMatter->workflow_id)
                    ->orderByRaw('COALESCE(sort_order, id) ASC')
                    ->get(); //dd($allWorkflowStages);

                $currentStageName = null;
                if ($selectedMatter && $currentWorkflowStageId && $allWorkflowStages->count() > 0) {
                    $currentStageRow = $allWorkflowStages->firstWhere('id', $currentWorkflowStageId);
                    $currentStageName = $currentStageRow ? $currentStageRow->name : null;
                }
                ?>
                
                @if($selectedMatter)
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="info-card in-progress-section">
                                <div class="in-progress-single-line">
                                    <h5 class="in-progress-title">
                                        @if($selectedMatter && isset($selectedMatter->matter_status) && $selectedMatter->matter_status == 1)
                                            Active
                                        @else
                                            In-active
                                        @endif
                                    </h5>
                                    <div class="current-stage-info">
                                        <label class="stage-label">Current Stage:</label>
                                        <div class="stage-value-container">
                                            <span class="stage-value">
                                                @if($currentWorkflowStageId)
                                                    @php
                                                        $currentStage = $allWorkflowStages->where('id', $currentWorkflowStageId)->first();
                                                    @endphp
                                                    {{ $currentStage ? $currentStage->name : 'N/A' }}
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
                                                $totalStages = $allWorkflowStages->count();
                                                $currentStageIndex = $currentWorkflowStageId ? $allWorkflowStages->where('id', '<=', $currentWorkflowStageId)->count() : 0;
                                                $progressPercentage = $totalStages > 0 ? round(($currentStageIndex / $totalStages) * 100) : 0;
                                            @endphp
                                            <div class="progress-circle" data-progress="{{ $progressPercentage }}">
                                                <svg class="progress-ring" width="80" height="80">
                                                    <circle class="progress-ring-circle-bg" cx="40" cy="40" r="36" fill="transparent" stroke="#e9ecef" stroke-width="6"/>
                                                    <circle class="progress-ring-circle" cx="40" cy="40" r="36" fill="transparent" stroke="#007bff" stroke-width="6" stroke-dasharray="{{ 2 * M_PI * 36 }}" stroke-dashoffset="{{ 2 * M_PI * 36 * (1 - $progressPercentage / 100) }}"/>
                                                </svg>
                                                <div class="progress-text">{{ $progressPercentage }}%</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="stage-navigation-buttons">
                                        @php
                                            $portalIsDiscontinued = ($selectedMatter->matter_status ?? 1) == 0;
                                            $portalCanReopen = in_array((int) (Auth::guard('admin')->user()->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);
                                        @endphp
                                        @if($portalIsDiscontinued)
                                            {{-- Discontinued matter: show Reopen (same roles as discontinue) --}}
                                            @if($portalCanReopen)
                                            <button class="btn btn-primary btn-sm matter-detail-reopen-btn client-portal-reopen-btn" id="client-portal-reopen" data-matter-id="{{ $selectedMatter->id }}" title="Reopen Matter">
                                                @icon('fa-redo') Reopen
                                            </button>
                                            @endif
                                        @else
                                            {{-- Active matter: show normal workflow buttons --}}
                                            @php
                                                // Check if we're at the first stage (can't go back from first stage)
                                                $isFirstStage = false;
                                                $nextStageName = null;
                                                if($currentWorkflowStageId && $allWorkflowStages->count() > 0) {
                                                    $firstStage = $allWorkflowStages->first();
                                                    $isFirstStage = ($currentWorkflowStageId == $firstStage->id);
                                                    $currentOrder = $allWorkflowStages->firstWhere('id', $currentWorkflowStageId);
                                                    $currentSort = $currentOrder ? ($currentOrder->sort_order ?? $currentOrder->id) : null;
                                                    $nextStage = $currentSort !== null ? $allWorkflowStages->first(fn($s) => ($s->sort_order ?? $s->id) > $currentSort) : $allWorkflowStages->where('id', '>', $currentWorkflowStageId)->first();
                                                    $nextStageName = $nextStage ? $nextStage->name : null;
                                                }
                                            @endphp
                                            <button class="btn btn-outline-primary btn-sm" id="back-to-previous-stage" data-matter-id="{{ $selectedMatter->id }}" title="Back to Previous Stage" {{ $isFirstStage ? 'disabled' : '' }}>
                                                @icon('fa-angle-left') Back to Previous Stage
                                            </button>
                                            @php
                                                $portalAdminForDiscontinue = Auth::guard('admin')->user();
                                                $portalCanDiscontinue = $portalAdminForDiscontinue
                                                    && in_array((int) ($portalAdminForDiscontinue->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);
                                            @endphp
                                            <button class="btn btn-success btn-sm" id="proceed-to-next-stage" data-matter-id="{{ $selectedMatter->id }}" data-next-stage-name="{{ $nextStageName ?? '' }}" data-current-stage-name="{{ $currentStageName ?? '' }}" title="Proceed to Next Stage">
                                                Proceed to Next Stage @icon('fa-angle-right')
                                            </button>
                                            @if($portalCanDiscontinue)
                                                <button class="btn btn-outline-danger btn-sm client-portal-discontinue-btn" data-matter-id="{{ $selectedMatter->id }}" title="Discontinue Matter">
                                                    @icon('fa-ban') Discontinue
                                                </button>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="info-card">
                            <h5>
                                @icon('fa-folder-open') Selected Matter
                                @if($selectedMatter)
                                    - {{ $matterName }} ({{ $matterNumber }})
                                @endif
                            </h5>
                            
                            @if($selectedMatter && $allWorkflowStages->count() > 0)
                                <!-- Tabs Navigation -->
                                <div class="client-portal-tabs-container mt-3">
                                    <ul class="client-portal-tabs-nav" role="tablist">
                                        <li class="client-portal-tab-item active" data-tab="activities">
                                            <a href="javascript:void(0);" class="client-portal-tab-link">Activities</a>
                                        </li>
                                        <li class="client-portal-tab-item" data-tab="documents">
                                            <a href="javascript:void(0);" class="client-portal-tab-link">Documents</a>
                                        </li>
                                        <li class="client-portal-tab-item" data-tab="messages">
                                            <a href="javascript:void(0);" class="client-portal-tab-link">Messages</a>
                                        </li>
                                        <li class="client-portal-tab-item" data-tab="details">
                                            <a href="javascript:void(0);" class="client-portal-tab-link">Details</a>
                                        </li>
                                    </ul>
                                    
                                    <!-- Tab Contents -->
                                    <div class="client-portal-tabs-content">
                                        <!-- Activities Tab (Default) -->
                                        <div class="client-portal-tab-pane active" id="activities-tab">
                                            <div class="workflow-stages-container mt-3">
                                                <div class="workflow-stages-list">
                                                    @foreach($allWorkflowStages as $index => $stage)
                                                        @php
                                                            $isActive = ($currentWorkflowStageId && $currentWorkflowStageId == $stage->id);
                                                            $isCompleted = ($currentWorkflowStageId && $stage->id < $currentWorkflowStageId);
                                                            $isPending = (!$currentWorkflowStageId || $stage->id > $currentWorkflowStageId);
                                                            
                                                            // Determine stage class
                                                            if($isActive) {
                                                                $stageClass = 'workflow-stage-active';
                                                            } elseif($isCompleted) {
                                                                $stageClass = 'workflow-stage-completed';
                                                            } else {
                                                                $stageClass = 'workflow-stage-pending';
                                                            }
                                                        @endphp
                                                        <div class="workflow-stage-item {{ $stageClass }}">
                                                            <span class="stage-name">{{ $stage->name }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Documents Tab -->
                                        <div class="client-portal-tab-pane" id="documents-tab">
                                            @if($selectedMatter && $allWorkflowStages->count() > 0)
                                                <div class="documents-checklist-container">
                                                    <div class="row">
                                                        <!-- Left Column: Stages & Checklists -->
                                                        <div class="col-md-4">
                                                            <div class="stages-checklist-list">
                                                                <ul class="stages-list">
                                                                    @foreach($allWorkflowStages as $stage)
                                                                        @php
                                                                            $isActiveStage = ($currentWorkflowStageId && $currentWorkflowStageId == $stage->id);
                                                                            $stageChecklists = DB::table('cp_doc_checklists')
                                                                                ->where('client_matter_id', $selectedMatter->id)
                                                                                ->where('wf_stage', $stage->name)
                                                                                ->orderBy('id', 'asc')
                                                                                ->get();
                                                                        @endphp
                                                                        <li class="stage-checklist-item {{ $isActiveStage ? 'active' : '' }}"
                                                                            data-stage-name="{{ $stage->name }}">
                                                                            <div class="stage-header">
                                                                                <span class="stage-title">{{ $stage->name }}</span>
                                                                                <span class="stage-checklist-count">({{ $stageChecklists->count() }})</span>
                                                                            </div>

                                                                            @if($stageChecklists->count() > 0)
                                                                                <div class="stage-checklists">
                                                                                    <table class="table checklist-table">
                                                                                        <tbody>
                                                                                            @foreach($stageChecklists as $checklist)
                                                                                                @php
                                                                                                    $uploadCount = DB::table('documents')
                                                                                                        ->where('cp_list_id', $checklist->id)
                                                                                                        ->where('type', 'workflow_checklist')
                                                                                                        ->count();
                                                                                                @endphp
                                                                                                <tr class="checklist-row cursor-pointer cp-doc-checklist-row"
                                                                                                    data-checklist-id="{{ $checklist->id }}"
                                                                                                    data-checklist-name="{{ $checklist->cp_checklist_name ?? 'N/A' }}"
                                                                                                    data-stage-name="{{ $stage->name }}"
                                                                                                    data-matter-id="{{ $selectedMatter->id }}">
                                                                                                    <td class="checklist-status">
                                                                                                        @if($uploadCount > 0)
                                                                                                            <span class="check">@icon('fa-check')</span>
                                                                                                        @else
                                                                                                            <span class="round"></span>
                                                                                                        @endif
                                                                                                    </td>
                                                                                                    <td class="checklist-name">{{ $checklist->cp_checklist_name ?? 'N/A' }}</td>
                                                                                                    <td class="checklist-count">
                                                                                                        <div class="circular-box">
                                                                                                            <span>{{ $uploadCount }}</span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            @endforeach
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            @endif

                                                                            <a href="javascript:void(0);"
                                                                               class="add-checklist-link openchecklist"
                                                                               data-matter-id="{{ $selectedMatter->id }}"
                                                                               data-wf-stage="{{ $stage->name }}">
                                                                                @icon('fa-plus') Add New Checklist
                                                                            </a>
                                                                        </li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <!-- Right Column: Documents for selected checklist -->
                                                        <div class="col-md-8">
                                                            <div class="checklist-details-panel" id="cp-checklist-details-panel">
                                                                <div class="text-muted text-center py-4" id="cp-checklist-placeholder">
                                                                    @icon('fa-hand-point-left', ['class' => 'fa-2x mb-2'])
                                                                    <p>Select a checklist item on the left to view its documents.</p>
                                                                </div>
                                                                <div id="cp-checklist-documents-content" style="display:none;">
                                                                    <h6 class="mb-3" id="cp-checklist-selected-name"></h6>
                                                                    <div class="table-responsive">
                                                                        <table class="table checklist-details-table">
                                                                            <thead>
                                                                                <tr>
                                                                                    <th><div>File Name</div></th>
                                                                                    <th><div>Uploaded</div></th>
                                                                                    <th><div>Status</div></th>
                                                                                    <th><div>Actions</div></th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="cp-checklist-documents-tbody">
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="tab-content-placeholder">
                                                    <p class="text-muted">
                                                        @if(!$selectedMatter)
                                                            Please select a matter to view documents.
                                                        @else
                                                            No workflow stages available.
                                                        @endif
                                                    </p>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Messages Tab -->
                                        <div class="client-portal-tab-pane" id="messages-tab">
                                            <!-- Message info modal (WhatsApp-style) -->
                                            <div class="message-info-modal-overlay" id="message-info-modal">
                                                <div class="message-info-modal">
                                                    <div class="message-info-modal-header">
                                                        <button type="button" class="message-info-close" id="message-info-close" aria-label="Close">&times;</button>
                                                        <div class="message-info-title">
                                                            @icon('fa-info-circle')
                                                            <span>Message info</span>
                                                        </div>
                                                    </div>
                                                    <div class="message-info-modal-body">
                                                        <div class="message-info-preview-bubble" id="message-info-preview">
                                                            <div class="message-content" id="message-info-content"></div>
                                                        </div>
                                                        <div id="message-info-status-container"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="whatsapp-chat-container" id="whatsapp-chat-container">
                                                <div class="messages-loading" id="messages-loading" style="display: none;">
                                                    <div class="loading-spinner"></div>
                                                    <p>Loading messages...</p>
                                                </div>
                                                <div class="messages-empty" id="messages-empty" style="display: none;">
                                                    <p class="text-muted">No messages yet. Start a conversation!</p>
                                                </div>
                                                <div class="whatsapp-chat-messages" id="whatsapp-chat-messages">
                                                    <!-- Messages will be inserted here -->
                                                </div>
                                                <!-- WhatsApp-style document preview (shows when file selected) -->
                                                <div class="document-preview-panel" id="document-preview-panel" style="display:none">
                                                    <div class="document-preview-header">
                                                        <button type="button" id="document-preview-close" class="document-preview-close" aria-label="Close">&times;</button>
                                                        <div class="document-preview-info">
                                                            <span class="document-preview-filename" id="document-preview-filename"></span>
                                                            <span class="document-preview-meta" id="document-preview-meta"></span>
                                                        </div>
                                                    </div>
                                                    <div class="document-preview-content" id="document-preview-content"></div>
                                                </div>
                                                <div class="whatsapp-chat-input-container" id="whatsapp-chat-input-container">
                                                    <div class="chat-input-row">
                                                        <div class="chat-input-actions-left">
                                                            <button type="button" id="attach-file-btn" class="chat-action-btn" title="Attach file">
                                                                @icon('fa-plus')
                                                            </button>
                                                            <input type="file" id="message-attachments" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" style="display:none">
                                                            <div class="emoji-picker-wrapper">
                                                                <button type="button" id="emoji-picker-btn" class="chat-action-btn" title="Emoji">
                                                                    @icon('fa-smile')
                                                                </button>
                                                                <div id="emoji-picker-popover" class="emoji-picker-popover" style="display:none">
                                                                    <div class="emoji-picker-grid"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <textarea id="message-input" class="message-input" placeholder="Type a message" rows="1"></textarea>
                                                        <button id="send-message-btn" class="send-message-btn" title="Send message">
                                                            @icon('fa-paper-plane')
                                                        </button>
                                                    </div>
                                                    <div class="attachment-thumbnails-row" id="attachment-thumbnails-row" style="display:none">
                                                        <div class="attachment-thumbnails" id="attachment-thumbnails"></div>
                                                        <button type="button" id="add-more-attach-btn" class="chat-action-btn add-more-attach-btn" title="Add more">
                                                            @icon('fa-plus')
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Details Tab -->
                                        <div class="client-portal-tab-pane" id="details-tab">
                                            <div class="details-container" id="details-container">
                                                <div class="details-loading" id="details-loading" style="display: none;">
                                                    <div class="loading-spinner"></div>
                                                    <p>Loading details...</p>
                                                </div>
                                                <div class="details-content" id="details-content">
                                                    <?php
                                                    // Get client ID
                                                    $clientId = $fetchedData->id;
                                                    
                                                    // Start with values from admins table
                                                    $basicInfo = [
                                                        'first_name' => $fetchedData->first_name ?? null,
                                                        'last_name' => $fetchedData->last_name ?? null,
                                                        'client_id' => $fetchedData->client_id ?? null,
                                                        'dob' => $fetchedData->dob ?? null,
                                                        'age' => $fetchedData->age ?? null,
                                                        'gender' => $fetchedData->gender ?? null,
                                                        'marital_status' => $fetchedData->marital_status ?? null,
                                                    ];
                                                    
                                                    // Track audit entries for each field
                                                    $auditEntries = [];
                                                    
                                                    // Get latest audit values for basic fields and override admins table values
                                                    $basicFields = ['first_name', 'last_name', 'client_id', 'dob', 'age', 'gender', 'marital_status'];
                                                    
                                                    foreach ($basicFields as $field) {
                                                        // Get the latest audit entry for this field
                                                        $latestAudit = \App\Models\ClientPortalDetailAudit::where('client_id', $clientId)
                                                            ->where('meta_key', $field)
                                                            ->orderBy('updated_at', 'desc')
                                                            ->first();
                                                        
                                                        if ($latestAudit && $latestAudit->new_value !== null) {
                                                            // Check if audit value is different from admins table value
                                                            $adminValue = $basicInfo[$field];
                                                            $auditValue = $latestAudit->new_value;
                                                            
                                                            // For dob, compare dates
                                                            if ($field === 'dob') {
                                                                $adminValueStr = $adminValue ? date('Y-m-d', strtotime($adminValue)) : null;
                                                                $auditValueStr = $auditValue ? date('Y-m-d', strtotime($auditValue)) : null;
                                                                $hasChange = ($adminValueStr !== $auditValueStr);
                                                            } else {
                                                                $hasChange = ((string)$adminValue !== (string)$auditValue);
                                                            }
                                                            
                                                            if ($hasChange) {
                                                                // Store audit entry info
                                                                $auditEntries[$field] = [
                                                                    'id' => $latestAudit->id,
                                                                    'new_value' => $auditValue,
                                                                    'old_value' => $latestAudit->old_value
                                                                ];
                                                                
                                                                // Override with audit value
                                                                if ($field === 'dob') {
                                                                    $basicInfo['dob'] = $auditValue;
                                                                } else {
                                                                    $basicInfo[$field] = $auditValue;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Format date of birth
                                                    $dateOfBirth = 'Not set';
                                                    if ($basicInfo['dob']) {
                                                        try {
                                                            $dateOfBirth = date('d/m/Y', strtotime($basicInfo['dob']));
                                                        } catch(\Exception $e) {
                                                            $dateOfBirth = 'Not set';
                                                        }
                                                    }
                                                    
                                                    // Calculate age from date of birth
                                                    $age = 'Not calculated';
                                                    if ($basicInfo['dob']) {
                                                        try {
                                                            $dob = new \DateTime($basicInfo['dob']);
                                                            $now = new \DateTime();
                                                            $diff = $now->diff($dob);
                                                            $age = $diff->y . ' years ' . $diff->m . ' months';
                                                        } catch(\Exception $e) {
                                                            // If calculation fails, use stored age value
                                                            $age = $basicInfo['age'] ?? 'Not calculated';
                                                        }
                                                    } elseif ($basicInfo['age']) {
                                                        $age = $basicInfo['age'];
                                                    }
                                                    
                                                    // Build full name
                                                    $fullName = trim(($basicInfo['first_name'] ?? '') . ' ' . ($basicInfo['last_name'] ?? ''));
                                                    if(empty($fullName)) {
                                                        $fullName = $fetchedData->name ?? 'N/A';
                                                    }
                                                    
                                                    // Get display values
                                                    $displayClientId = $basicInfo['client_id'] ?? 'N/A';
                                                    $displayMaritalStatus = $basicInfo['marital_status'] ?? 'Not set';
                                                    $displayGender = $basicInfo['gender'] ?? 'Not set';
                                                    
                                                    // Field labels for messages
                                                    $fieldLabels = [
                                                        'first_name' => 'First Name',
                                                        'last_name' => 'Last Name',
                                                        'client_id' => 'Client ID',
                                                        'dob' => 'Date of Birth',
                                                        'age' => 'Age',
                                                        'gender' => 'Gender',
                                                        'marital_status' => 'Marital Status'
                                                    ];
                                                    ?>
                                                    
                                                    <!-- Basic Information Section -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-user') Basic Information</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                <div class="summary-item">
                                                                    <span class="summary-label">FIRST NAME:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['first_name']) ? 'audit-value' : '' }}">
                                                                        {{ $basicInfo['first_name'] ?? 'Not set' }}
                                                                        @if(isset($auditEntries['first_name']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['first_name']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['first_name']['id'] }}, 'first_name', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['first_name']['id'] }}, 'first_name', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="summary-item">
                                                                    <span class="summary-label">LAST NAME:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['last_name']) ? 'audit-value' : '' }}">
                                                                        {{ $basicInfo['last_name'] ?? 'Not set' }}
                                                                        @if(isset($auditEntries['last_name']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['last_name']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['last_name']['id'] }}, 'last_name', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['last_name']['id'] }}, 'last_name', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="summary-item">
                                                                    <span class="summary-label">CLIENT ID:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['client_id']) ? 'audit-value' : '' }}">
                                                                        {{ $displayClientId }}
                                                                        @if(isset($auditEntries['client_id']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['client_id']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['client_id']['id'] }}, 'client_id', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['client_id']['id'] }}, 'client_id', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="summary-item">
                                                                    <span class="summary-label">MARITAL STATUS:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['marital_status']) ? 'audit-value' : '' }}">
                                                                        {{ $displayMaritalStatus }}
                                                                        @if(isset($auditEntries['marital_status']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['marital_status']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['marital_status']['id'] }}, 'marital_status', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['marital_status']['id'] }}, 'marital_status', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="summary-item">
                                                                    <span class="summary-label">DATE OF BIRTH:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['dob']) ? 'audit-value' : '' }}">
                                                                        {{ $dateOfBirth }}
                                                                        @if(isset($auditEntries['dob']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['dob']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['dob']['id'] }}, 'dob', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['dob']['id'] }}, 'dob', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="summary-item">
                                                                    <span class="summary-label">AGE:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['age']) ? 'audit-value' : '' }}">
                                                                        {{ $age }}
                                                                        @if(isset($auditEntries['age']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['age']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['age']['id'] }}, 'age', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['age']['id'] }}, 'age', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <div class="summary-item">
                                                                    <span class="summary-label">GENDER:</span>
                                                                    <span class="summary-value {{ isset($auditEntries['gender']) ? 'audit-value' : '' }}">
                                                                        {{ $displayGender }}
                                                                        @if(isset($auditEntries['gender']))
                                                                            <span class="audit-badge" title="Pending approval">
                                                                                @icon('fa-clock')
                                                                            </span>
                                                                        @endif
                                                                    </span>
                                                                    @if(isset($auditEntries['gender']))
                                                                        <div class="audit-actions">
                                                                            <button type="button" class="btn-approve" onclick="approveAuditValue({{ $auditEntries['gender']['id'] }}, 'gender', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                @icon('fa-check-circle')
                                                                            </button>
                                                                            <button type="button" class="btn-reject" onclick="rejectAuditValue({{ $auditEntries['gender']['id'] }}, 'gender', {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                @icon('fa-times-circle')
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Phone Number Section (source: client_contacts + clientportal_details_audit) -->
                                                    <?php
                                                    $phonesMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedPhonesForClient($clientId);
                                                    ?>
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-phone') Phone Number</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($phonesMergedForDetails as $contact)
                                                                    <?php
                                                                    $phoneHasAudit = isset($contact['action']) && in_array($contact['action'], ['create', 'update'], true) && isset($contact['meta_order']);
                                                                    $phoneTypeLabel = strtoupper($contact['type'] ?? 'Phone');
                                                                    $phoneCountryCode = $contact['country_code'] ?? '';
                                                                    $phoneNumber = $contact['phone'] ?? '—';
                                                                    $phoneExtension = $contact['extension'] ?? null;
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">{{ $phoneTypeLabel }}:</span>
                                                                            <span class="summary-value {{ $phoneHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $phoneCountryCode }}{{ $phoneCountryCode ? ' ' : '' }}{{ $phoneNumber }}
                                                                                @if(!empty($phoneExtension))
                                                                                    <span class="text-muted">(ext. {{ $phoneExtension }})</span>
                                                                                @endif
                                                                                @if($phoneHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($phoneHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approvePhoneAudit({{ $contact['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectPhoneAudit({{ $contact['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">PHONE:</span>
                                                                        <span class="summary-value text-muted">No phone numbers recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php
                                                    // Email: use source + clientportal_details_audit (same as Visa section)
                                                    $emailsMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedEmailsForClient($clientId);
                                                    $clientAddressesForDetails = isset($clientAddresses) ? $clientAddresses : \App\Models\ClientAddress::where('client_id', $clientId)->orderedForDisplay()->get();
                                                    // Address: use source + clientportal_details_audit (same as Passport section)
                                                    $addressesMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedAddressesForClient($clientId);
                                                    // Travel: use source + clientportal_details_audit (same as Passport section)
                                                    $travelsMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedTravelsForClient($clientId);
                                                    $clientPassportsForDetails = isset($clientPassports) ? $clientPassports : \App\Models\ClientPassportInformation::where('client_id', $clientId)->orderBy('id')->get();
                                                    // Passport: use source + clientportal_details_audit (same as Visa section)
                                                    $passportsMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedPassportsForClient($clientId);
                                                    $visaCountriesForDetails = isset($visaCountries) ? $visaCountries : \App\Models\ClientVisaCountry::with('matter')->where('client_id', $clientId)->orderBy('id')->get();
                                                    // Visa: use source + clientportal_details_audit (same as Basic Details / API)
                                                    $visasMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedVisasForClient($clientId);
                                                    $clientTravelsForDetails = isset($clientTravels) ? $clientTravels : \App\Models\ClientTravelInformation::where('client_id', $clientId)->orderByRaw('travel_arrival_date DESC NULLS LAST, created_at DESC')->get();
                                                    // Qualifications: use source + clientportal_details_audit (same as Passport section)
                                                    $qualificationsMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedQualificationsForClient($clientId);
                                                    // Work Experience: use source + clientportal_details_audit (same as Qualification section)
                                                    $experiencesMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedExperiencesForClient($clientId);
                                                    // Occupation: use source + clientportal_details_audit (same as Qualification section)
                                                    $occupationsMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedOccupationsForClient($clientId);
                                                    // Test Score: use source + clientportal_details_audit (same as Qualification section)
                                                    $testScoresMergedForDetails = (new \App\Http\Controllers\API\ClientPortalPersonalDetailsController())->getMergedTestScoresForClient($clientId);
                                                    ?>

                                                    <!-- Email Address Section (source: client_emails + clientportal_details_audit) -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-envelope') Email Address</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($emailsMergedForDetails as $emailRec)
                                                                    <?php
                                                                    $emailHasAudit = isset($emailRec['action']) && in_array($emailRec['action'], ['create', 'update'], true) && isset($emailRec['meta_order']);
                                                                    $emailTypeLabel = strtoupper($emailRec['type'] ?? 'Email');
                                                                    $emailValue = $emailRec['email'] ?? '—';
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">{{ $emailTypeLabel }}:</span>
                                                                            <span class="summary-value {{ $emailHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $emailValue }}
                                                                                @if($emailHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($emailHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approveEmailAudit({{ $emailRec['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectEmailAudit({{ $emailRec['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">EMAIL:</span>
                                                                        <span class="summary-value text-muted">No email addresses recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Passport Information Section (source: client_passport_informations + clientportal_details_audit) -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-id-card') Passport Information</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($passportsMergedForDetails as $passport)
                                                                    <?php
                                                                    $passportHasAudit = isset($passport['action']) && in_array($passport['action'], ['create', 'update'], true) && isset($passport['meta_order']);
                                                                    $passportIssueDate = $passport['issue_date'] ?? null;
                                                                    $passportExpiryDate = $passport['expiry_date'] ?? null;
                                                                    ?>
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">PASSPORT NUMBER:</span>
                                                                        <span class="summary-value">{{ $passport['passport_number'] ?? '—' }}</span>
                                                                    </div>
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">COUNTRY:</span>
                                                                        <span class="summary-value">{{ $passport['country'] ?? '—' }}</span>
                                                                    </div>
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">ISSUE / EXPIRY:</span>
                                                                        <span class="summary-value {{ $passportHasAudit ? 'audit-value' : '' }}">
                                                                            {{ $passportIssueDate ?: '—' }}
                                                                            /
                                                                            {{ $passportExpiryDate ?: '—' }}
                                                                            @if($passportHasAudit)
                                                                                <span class="audit-badge" title="Pending approval">
                                                                                    @icon('fa-clock')
                                                                                </span>
                                                                            @endif
                                                                        </span>
                                                                        @if($passportHasAudit)
                                                                            <div class="audit-actions">
                                                                                <button type="button" class="btn-approve" onclick="approvePassportAudit({{ $passport['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                    @icon('fa-check-circle')
                                                                                </button>
                                                                                <button type="button" class="btn-reject" onclick="rejectPassportAudit({{ $passport['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                    @icon('fa-times-circle')
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">PASSPORT:</span>
                                                                        <span class="summary-value text-muted">No passport information recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Visa Information Section (source: client_visa_countries + clientportal_details_audit) -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-stamp') Visa Information</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($visasMergedForDetails as $visa)
                                                                    <?php
                                                                    $visaTypeLabel = '—';
                                                                    if (!empty($visa['visa_type'])) {
                                                                        $matter = is_numeric($visa['visa_type']) ? \App\Models\Matter::find($visa['visa_type']) : null;
                                                                        $visaTypeLabel = $matter ? $matter->title : $visa['visa_type'];
                                                                    }
                                                                    $visaHasAudit = isset($visa['action']) && in_array($visa['action'], ['create', 'update'], true) && isset($visa['meta_order']);
                                                                    ?>
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">VISA TYPE:</span>
                                                                        <span class="summary-value">{{ $visaTypeLabel }}</span>
                                                                    </div>
                                                                    @if(!empty($visa['visa_description']))
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">DESCRIPTION:</span>
                                                                            <span class="summary-value">{{ $visa['visa_description'] }}</span>
                                                                        </div>
                                                                    @endif
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">GRANT / EXPIRY:</span>
                                                                        <span class="summary-value {{ $visaHasAudit ? 'audit-value' : '' }}">
                                                                            {{ $visa['visa_grant_date'] ?? '—' }}
                                                                            /
                                                                            {{ $visa['visa_expiry_date'] ?? '—' }}
                                                                            @if($visaHasAudit)
                                                                                <span class="audit-badge" title="Pending approval">
                                                                                    @icon('fa-clock')
                                                                                </span>
                                                                            @endif
                                                                        </span>
                                                                        @if($visaHasAudit)
                                                                            <div class="audit-actions">
                                                                                <button type="button" class="btn-approve" onclick="approveVisaAudit({{ $visa['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                    @icon('fa-check-circle')
                                                                                </button>
                                                                                <button type="button" class="btn-reject" onclick="rejectVisaAudit({{ $visa['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                    @icon('fa-times-circle')
                                                                                </button>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">VISA:</span>
                                                                        <span class="summary-value text-muted">No visa information recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Address Information Section (source: client_addresses + clientportal_details_audit) -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-map-marker-alt') Address Information</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($addressesMergedForDetails as $addr)
                                                                    <?php
                                                                    $addrHasAudit = isset($addr['action']) && in_array($addr['action'], ['create', 'update'], true) && isset($addr['meta_order']);
                                                                    $addrLine = trim(implode(', ', array_filter([$addr['address_line_1'] ?? '', $addr['address_line_2'] ?? '', $addr['suburb'] ?? '', $addr['state'] ?? '', $addr['postcode'] ?? '', $addr['country'] ?? ''])));
                                                                    if ($addrLine === '') {
                                                                        $addrLine = $addr['search_address'] ?? '—';
                                                                    }
                                                                    if ($addrLine === '') {
                                                                        $addrLine = '—';
                                                                    }
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">ADDRESS:</span>
                                                                            <span class="summary-value">{{ $addrLine }}</span>
                                                                        </div>
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">PERIOD:</span>
                                                                            <span class="summary-value {{ $addrHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $addr['start_date'] ?? '—' }}
                                                                                to {{ $addr['end_date'] ?? '—' }}
                                                                                @if(!empty($addr['is_current'])) <span class="badge badge-success">Current</span> @endif
                                                                                @if($addrHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($addrHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approveAddressAudit({{ $addr['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectAddressAudit({{ $addr['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">ADDRESS:</span>
                                                                        <span class="summary-value text-muted">No address information recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Travel Information Section (source: client_travel_informations + clientportal_details_audit) -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-plane') Travel Information</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($travelsMergedForDetails as $travel)
                                                                    <?php
                                                                    $travelHasAudit = isset($travel['action']) && in_array($travel['action'], ['create', 'update'], true) && isset($travel['meta_order']);
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">COUNTRY:</span>
                                                                            <span class="summary-value">{{ $travel['country_visited'] ?? '—' }}</span>
                                                                        </div>
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">ARRIVAL / DEPARTURE:</span>
                                                                            <span class="summary-value {{ $travelHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $travel['arrival_date'] ?? '—' }}
                                                                                /
                                                                                {{ $travel['departure_date'] ?? '—' }}
                                                                                @if($travelHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($travelHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approveTravelAudit({{ $travel['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectTravelAudit({{ $travel['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                        @if(!empty($travel['purpose']))
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">PURPOSE:</span>
                                                                                <span class="summary-value">{{ $travel['purpose'] }}</span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">TRAVEL:</span>
                                                                        <span class="summary-value text-muted">No travel information recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Educational Qualifications Section -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-graduation-cap') Educational Qualifications</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($qualificationsMergedForDetails as $qual)
                                                                    <?php
                                                                    $qualHasAudit = isset($qual['action']) && in_array($qual['action'], ['create', 'update'], true) && isset($qual['meta_order']);
                                                                    $qualLevel = $qual['level'] ?? '—';
                                                                    $qualName = $qual['name'] ?? '';
                                                                    $qualCollege = $qual['college_name'] ?? '—';
                                                                    $qualCountry = $qual['country'] ?? '';
                                                                    $qualStart = $qual['start_date'] ?? null;
                                                                    $qualFinish = $qual['finish_date'] ?? null;
                                                                    $qualRelevant = !empty($qual['relevant_qualification']);
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">LEVEL / NAME:</span>
                                                                            <span class="summary-value">{{ $qualLevel }} {{ $qualName ? ' – ' . $qualName : '' }}</span>
                                                                        </div>
                                                                        @if($qualCollege !== '—' || $qualCountry)
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">INSTITUTION / COUNTRY:</span>
                                                                                <span class="summary-value">{{ $qualCollege }} {{ $qualCountry ? ', ' . $qualCountry : '' }}</span>
                                                                            </div>
                                                                        @endif
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">DATES:</span>
                                                                            <span class="summary-value {{ $qualHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $qualStart ?: '—' }}
                                                                                to {{ $qualFinish ?: '—' }}
                                                                                @if($qualRelevant) <span class="badge badge-info">Relevant</span> @endif
                                                                                @if($qualHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($qualHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approveQualificationAudit({{ $qual['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectQualificationAudit({{ $qual['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">QUALIFICATIONS:</span>
                                                                        <span class="summary-value text-muted">No educational qualifications recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Work Experience Section -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-briefcase') Work Experience</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($experiencesMergedForDetails as $exp)
                                                                    <?php
                                                                    $expHasAudit = isset($exp['action']) && in_array($exp['action'], ['create', 'update'], true) && isset($exp['meta_order']);
                                                                    $expJobTitle = $exp['job_title'] ?? '—';
                                                                    $expJobCode = $exp['job_code'] ?? null;
                                                                    $expEmployer = $exp['employer_name'] ?? null;
                                                                    $expCountry = $exp['country'] ?? '—';
                                                                    $expStart = $exp['start_date'] ?? null;
                                                                    $expFinish = $exp['finish_date'] ?? null;
                                                                    $expRelevant = !empty($exp['relevant_experience']);
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">JOB TITLE:</span>
                                                                            <span class="summary-value">{{ $expJobTitle }} @if($expJobCode) ({{ $expJobCode }}) @endif</span>
                                                                        </div>
                                                                        @if($expEmployer)
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">EMPLOYER:</span>
                                                                                <span class="summary-value">{{ $expEmployer }}</span>
                                                                            </div>
                                                                        @endif
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">COUNTRY / DATES:</span>
                                                                            <span class="summary-value {{ $expHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $expCountry }} —
                                                                                {{ $expStart ?: '—' }}
                                                                                to {{ $expFinish ?: '—' }}
                                                                                @if($expRelevant) <span class="badge badge-info">Relevant</span> @endif
                                                                                @if($expHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($expHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approveExperienceAudit({{ $exp['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectExperienceAudit({{ $exp['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">EXPERIENCE:</span>
                                                                        <span class="summary-value text-muted">No work experience recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Occupation Section -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-user-tie') Occupation</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($occupationsMergedForDetails as $occ)
                                                                    <?php
                                                                    $occHasAudit = isset($occ['action']) && in_array($occ['action'], ['create', 'update'], true) && isset($occ['meta_order']);
                                                                    $occNominated = $occ['nominated_occupation'] ?? '—';
                                                                    $occCode = $occ['occupation_code'] ?? null;
                                                                    $occSkill = $occ['skill_assessment'] ?? null;
                                                                    $occList = $occ['assessing_authority'] ?? null;
                                                                    $occSubclass = $occ['visa_subclass'] ?? null;
                                                                    $occDates = $occ['assessment_date'] ?? null;
                                                                    $occExpiry = $occ['expiry_date'] ?? null;
                                                                    $occRelevant = !empty($occ['relevant_occupation']);
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">OCCUPATION / CODE:</span>
                                                                            <span class="summary-value">{{ $occNominated }} {{ $occCode ? '(' . $occCode . ')' : '' }}</span>
                                                                        </div>
                                                                        @if($occSkill || $occList)
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">ASSESSMENT / LIST:</span>
                                                                                <span class="summary-value">{{ $occSkill ?? '—' }} {{ $occList ? ' / ' . $occList : '' }}</span>
                                                                            </div>
                                                                        @endif
                                                                        @if($occSubclass || $occDates || $occExpiry || $occHasAudit)
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">SUBCLASS / DATES:</span>
                                                                                <span class="summary-value {{ $occHasAudit ? 'audit-value' : '' }}">
                                                                                    {{ $occSubclass ?? '—' }} {{ $occDates ? ' / ' . $occDates : '' }}{{ $occExpiry ? ' / ' . $occExpiry : '' }}
                                                                                    @if($occHasAudit)
                                                                                        <span class="audit-badge" title="Pending approval">
                                                                                            @icon('fa-clock')
                                                                                        </span>
                                                                                    @endif
                                                                                </span>
                                                                                @if($occHasAudit)
                                                                                    <div class="audit-actions">
                                                                                        <button type="button" class="btn-approve" onclick="approveOccupationAudit({{ $occ['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                            @icon('fa-check-circle')
                                                                                        </button>
                                                                                        <button type="button" class="btn-reject" onclick="rejectOccupationAudit({{ $occ['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                            @icon('fa-times-circle')
                                                                                        </button>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        @endif
                                                                        @if($occRelevant)
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">RELEVANT:</span>
                                                                                <span class="summary-value"><span class="badge badge-info">Yes</span></span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">OCCUPATION:</span>
                                                                        <span class="summary-value text-muted">No occupation information recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Test Score Section -->
                                                    <div class="details-section-card">
                                                        <div class="details-section-header">
                                                            <h5>@icon('fa-chart-line') Test Score</h5>
                                                        </div>
                                                        <div class="summary-view">
                                                            <div class="summary-grid">
                                                                @forelse($testScoresMergedForDetails as $test)
                                                                    <?php
                                                                    $testHasAudit = isset($test['action']) && in_array($test['action'], ['create', 'update'], true) && isset($test['meta_order']);
                                                                    $testType = $test['test_type'] ?? '—';
                                                                    $testDate = $test['test_date'] ?? null;
                                                                    $testListening = $test['listening'] ?? '—';
                                                                    $testReading = $test['reading'] ?? '—';
                                                                    $testWriting = $test['writing'] ?? '—';
                                                                    $testSpeaking = $test['speaking'] ?? '—';
                                                                    $testOverall = $test['overall_score'] ?? '—';
                                                                    $testProficiency = $test['proficiency_level'] ?? null;
                                                                    $testRelevant = !empty($test['relevant_test']);
                                                                    ?>
                                                                    <div class="summary-row">
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">TEST TYPE:</span>
                                                                            <span class="summary-value">{{ $testType }} @if($testDate) ({{ $testDate }}) @endif</span>
                                                                        </div>
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">L / R / W / S:</span>
                                                                            <span class="summary-value">{{ $testListening }} / {{ $testReading }} / {{ $testWriting }} / {{ $testSpeaking }}</span>
                                                                        </div>
                                                                        <div class="summary-item">
                                                                            <span class="summary-label">OVERALL / PROFICIENCY:</span>
                                                                            <span class="summary-value {{ $testHasAudit ? 'audit-value' : '' }}">
                                                                                {{ $testOverall }} {{ $testProficiency ? ' (' . $testProficiency . ')' : '' }}
                                                                                @if($testHasAudit)
                                                                                    <span class="audit-badge" title="Pending approval">
                                                                                        @icon('fa-clock')
                                                                                    </span>
                                                                                @endif
                                                                            </span>
                                                                            @if($testHasAudit)
                                                                                <div class="audit-actions">
                                                                                    <button type="button" class="btn-approve" onclick="approveTestScoreAudit({{ $test['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Approve">
                                                                                        @icon('fa-check-circle')
                                                                                    </button>
                                                                                    <button type="button" class="btn-reject" onclick="rejectTestScoreAudit({{ $test['meta_order'] }}, {{ $clientId }}, {{ $selectedMatter ? $selectedMatter->id : 'null' }})" title="Reject">
                                                                                        @icon('fa-times-circle')
                                                                                    </button>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                        @if($testRelevant)
                                                                            <div class="summary-item">
                                                                                <span class="summary-label">RELEVANT:</span>
                                                                                <span class="summary-value"><span class="badge badge-info">Yes</span></span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @empty
                                                                    <div class="summary-item">
                                                                        <span class="summary-label">TEST SCORE:</span>
                                                                        <span class="summary-value text-muted">No test scores recorded</span>
                                                                    </div>
                                                                @endforelse
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            @else
                <!-- Portal is Inactive -->
                <div class="alert alert-warning">
                    @icon('fa-exclamation-triangle') Client portal is currently inactive. Use the toggle in the sidebar to activate it.
                </div>

                <div class="info-card">
                    <h5>@icon('fa-info-circle') About Client Portal</h5>
                    <p>The client portal allows clients to:</p>
                    <ul class="feature-list">
                        <li>@icon('fa-check', ['class' => 'text-success']) View their case status and progress</li>
                        <li>@icon('fa-check', ['class' => 'text-success']) Access and download documents</li>
                        <li>@icon('fa-check', ['class' => 'text-success']) Upload required documents</li>
                        <li>@icon('fa-check', ['class' => 'text-success']) View appointments and deadlines</li>
                        <li>@icon('fa-check', ['class' => 'text-success']) Communicate via secure messaging</li>
                        <li>@icon('fa-check', ['class' => 'text-success']) View invoices and payment history</li>
                        <li>@icon('fa-check', ['class' => 'text-success']) Update their profile information</li>
                    </ul>

                    <?php
                    // Check if client has required information for portal activation
                    $hasEmail = !empty($fetchedData->email);
                    $hasMatters = DB::table('client_matters')
                        ->where('client_id', $fetchedData->id)
                        ->where('matter_status', 1)
                        ->exists();
                    ?>

                    @if(!$hasEmail || !$hasMatters)
                        <div class="alert alert-danger mt-3">
                            <h6>@icon('fa-exclamation-circle') Portal Activation Requirements:</h6>
                            <ul class="mb-0">
                                @if(!$hasEmail)
                                    <li>@icon('fa-times', ['class' => 'text-danger']) Client email address is required</li>
                                @endif
                                @if(!$hasMatters)
                                    <li>@icon('fa-times', ['class' => 'text-danger']) At least one active matter is required</li>
                                @endif
                            </ul>
                            <p class="mt-2 mb-0"><strong>Please complete these requirements before activating the portal.</strong></p>
                        </div>
                    @else
                        <div class="alert alert-success mt-3">
                            @icon('fa-check-circle') All requirements met. You can activate the portal using the toggle in the sidebar.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<style>
/* WCAG contrast: text-muted on light backgrounds - use darker gray */
.client-portal-container .text-muted {
    color: #4b5563 !important;
}

.client-portal-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 0;
}

.portal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.portal-header-controls {
    display: flex;
    align-items: center;
    gap: 20px;
}

.portal-toggle-container {
    display: flex;
    align-items: center;
}

.portal-toggle-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    cursor: pointer;
}

.portal-toggle-loader {
    margin-left: 8px;
    color: white;
    font-size: 14px;
}

.portal-toggle-loader i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.toggle-text {
    font-size: 0.9rem;
    font-weight: 500;
    color: white;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.3);
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: #28a745;
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.portal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
}

.portal-status-badge .badge {
    font-size: 0.95rem;
    padding: 8px 15px;
}

.portal-content {
    padding: 25px;
}

.info-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.info-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.info-card h5 {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
}

.credential-item {
    margin-bottom: 15px;
}

.credential-item label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    display: block;
}

.credential-value {
    display: flex;
    align-items: center;
    gap: 10px;
}

.credential-value span {
    flex: 1;
    padding: 8px 12px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.copy-btn {
    padding: 6px 12px;
    border-radius: 4px;
    transition: all 0.2s;
}

.copy-btn:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.credential-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
}

.feature-list {
    list-style: none;
    padding-left: 0;
}

.feature-list li {
    padding: 8px 0;
    font-size: 1rem;
}

.feature-list li i {
    margin-right: 10px;
}

.matter-list {
    list-style: none;
    padding-left: 0;
}

.matter-list li {
    padding: 10px 15px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    margin-bottom: 8px;
}

.selected-matter-display {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.matter-info-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 15px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.matter-info-item label {
    font-weight: 600;
    color: #495057;
    min-width: 140px;
    margin: 0;
}

.matter-value {
    font-size: 1rem;
    color: #2c3e50;
    font-weight: 500;
    flex: 1;
}

.workflow-stages-container {
    margin-top: 20px;
}

.workflow-stages-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.workflow-stage-item {
    padding: 12px 16px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    transition: all 0.2s ease;
    cursor: default;
}

.workflow-stage-item .stage-name {
    font-size: 0.95rem;
    font-weight: 500;
    color: #495057;
}

.workflow-stage-completed {
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.workflow-stage-completed .stage-name {
    color: #155724;
}

.workflow-stage-active {
    background-color: #d4edda;
    border-color: #28a745;
    border-width: 2px;
}

.workflow-stage-active .stage-name {
    color: #155724;
    font-weight: 700;
}

.workflow-stage-pending {
    background-color: #e9ecef;
    border-color: #dee2e6;
}

.workflow-stage-pending .stage-name {
    color: #6c757d;
}

/* Client Portal Tabs Styles */
.client-portal-tabs-container {
    margin-top: 20px;
}

.client-portal-tabs-nav {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    border-bottom: 2px solid #e9ecef;
    gap: 0;
}

.client-portal-tab-item {
    margin: 0;
    padding: 0;
}

.client-portal-tab-link {
    display: block;
    padding: 12px 24px;
    text-decoration: none;
    color: #495057;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    cursor: pointer;
    background: transparent;
}

.client-portal-tab-item.active .client-portal-tab-link {
    color: #9333ea;
    border-bottom-color: #9333ea;
    background: rgba(147, 51, 234, 0.05);
}

.client-portal-tab-link:hover {
    color: #9333ea;
    background: rgba(147, 51, 234, 0.05);
}

.client-portal-tabs-content {
    position: relative;
    min-height: 200px;
}

.client-portal-tab-pane {
    display: none;
    padding: 20px 0;
    animation: fadeIn 0.3s ease;
}

.client-portal-tab-pane.active {
    display: block;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.tab-content-placeholder {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
}

/* WhatsApp Style Messages */
.whatsapp-chat-container {
    height: 600px;
    background: #e5ddd5;
    background-image: 
        repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,.03) 10px, rgba(0,0,0,.03) 20px);
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
}

.whatsapp-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    position: relative;
    min-height: 0;
}

.whatsapp-message {
    display: flex;
    margin-bottom: 4px;
    animation: messageSlideIn 0.3s ease;
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message-sent {
    justify-content: flex-end;
}

.message-received {
    justify-content: flex-start;
}

.message-bubble {
    max-width: 65%;
    padding: 8px 12px;
    border-radius: 7.5px;
    position: relative;
    word-wrap: break-word;
    box-shadow: 0 1px 0.5px rgba(0,0,0,0.13);
}

.message-sent .message-bubble {
    background-color: #dcf8c6;
    border-bottom-right-radius: 2px;
}

.message-received .message-bubble {
    background-color: #ffffff;
    border-bottom-left-radius: 2px;
}

.message-sender-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
}

.message-avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    flex-shrink: 0;
}

.sender-name {
    font-size: 13px;
    font-weight: 600;
    color: #667eea;
}

.message-content {
    font-size: 14px;
    line-height: 1.4;
    color: #303030;
    white-space: pre-wrap;
}

.message-timestamp {
    font-size: 11px;
    color: #667781;
    margin-top: 4px;
    text-align: right;
    padding-top: 2px;
    white-space: nowrap;
    flex-shrink: 0;
}

.message-sent .message-timestamp {
    text-align: right;
}

.message-received .message-timestamp {
    text-align: left;
}

/* WhatsApp-style read receipt icon (sent messages only) */
.message-timestamp-row {
    display: flex;
    flex-wrap: nowrap;
    align-items: baseline;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
}

.message-received .message-timestamp-row {
    justify-content: flex-start;
}

.message-read-icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
}

.message-read-icon svg {
    width: 16px;
    height: 14px;
}

.message-read-icon--unread {
    color: #8696a0;
}

.message-read-icon--read {
    color: #4FB6EC; /* WhatsApp Checkmark Blue */
}

/* Message info chevron trigger */
.message-info-chevron {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    margin-left: 2px;
    cursor: pointer;
    color: #667781;
    opacity: 0.8;
    flex-shrink: 0;
}
.message-info-chevron:hover {
    color: #333;
    opacity: 1;
}

/* Message info modal */
.message-info-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.4);
    z-index: 1050;
    align-items: center;
    justify-content: center;
}
.message-info-modal-overlay.active {
    display: flex;
}
.message-info-modal {
    background: #fff;
    border-radius: 12px;
    max-width: 400px;
    width: 90%;
    max-height: 85vh;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.15);
}
.message-info-modal-header {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e9edef;
}
.message-info-modal-header .message-info-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    margin-right: 12px;
    color: #667781;
    font-size: 20px;
}
.message-info-modal-header .message-info-close:hover {
    color: #333;
}
.message-info-modal-header .message-info-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
    color: #111b21;
}
.message-info-modal-header .message-info-title i {
    color: #667781;
}
.message-info-modal-body {
    padding: 20px;
    overflow-y: auto;
}
.message-info-preview-bubble {
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 20px;
}
.message-info-preview-bubble.sent {
    background: #dcf8c6;
}
.message-info-preview-bubble.received {
    background: #f0f2f5;
}
.message-info-preview-bubble .message-content {
    font-size: 14px;
    color: #111b21;
    white-space: pre-wrap;
}
.message-info-status-section {
    padding: 12px 0;
    border-bottom: 1px solid #f0f2f5;
}
.message-info-status-section:last-child {
    border-bottom: none;
}
.message-info-status-label {
    font-size: 14px;
    font-weight: 600;
    color: #111b21;
    margin-bottom: 4px;
}
.message-info-status-time {
    font-size: 13px;
    color: #667781;
}
.message-info-status-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.message-info-status-icon {
    flex-shrink: 0;
    color: #4FB6EC; /* WhatsApp Checkmark Blue */
}
.message-info-status-icon.message-info-status-icon--grey {
    color: #8696a0;
}
.message-info-status-icon svg {
    width: 18px;
    height: 14px;
}

.messages-loading {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 40px;
    color: #667781;
    z-index: 20;
    background: rgba(229, 221, 213, 0.95);
    border-radius: 8px;
    width: auto;
    min-width: 200px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e5ddd5;
    border-top-color: #25d366;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.messages-empty {
    display: none;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #667781;
    font-size: 14px;
    z-index: 10;
    pointer-events: none;
    width: 100%;
    text-align: center;
}

/* Chat Input Area */
.whatsapp-chat-input-container {
    background: #f0f0f0;
    border-top: 1px solid #ddd;
    padding: 10px;
    flex-shrink: 0;
}

.chat-input-row {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    background: #fff;
    border-radius: 21px;
    padding: 8px 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.attachment-thumbnails-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0 0;
    margin-top: 4px;
}
.attachment-thumbnails {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.attachment-thumbnail-item {
    position: relative;
    width: 48px;
    height: 48px;
    border-radius: 8px;
    overflow: hidden;
    background: #f0f2f5;
    display: flex;
    align-items: center;
    justify-content: center;
}
.attachment-thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.attachment-thumbnail-item.doc {
    color: #667781;
    font-size: 20px;
}
.attachment-thumbnails .attachment-thumbnail-item {
    width: 48px;
    height: 48px;
    font-size: 18px;
    cursor: pointer;
}
.attachment-thumbnails .attachment-thumbnail-item.active {
    border: 2px solid #25d366;
}
.attachment-thumbnail-item .thumb-check {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: #25d366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 10px;
}
.attachment-thumbnail-item .thumb-remove {
    position: absolute;
    top: 2px;
    right: 2px;
    z-index: 2;
    width: 16px;
    height: 16px;
    background: rgba(0,0,0,0.5);
    border-radius: 50%;
    border: none;
    color: #fff;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.add-more-attach-btn {
    width: 48px;
    height: 48px;
    border: 2px dashed #ddd;
    border-radius: 8px;
    color: #667781;
}

/* WhatsApp-style document preview panel */
.document-preview-panel {
    flex-shrink: 0;
    background: #fff;
    border-top: 1px solid #e9edef;
    height: 420px;
    min-height: 360px;
    max-height: 55vh;
    display: flex;
    flex-direction: column;
}
.document-preview-header {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid #e9edef;
    background: #f0f2f5;
}
.document-preview-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px 8px;
    margin-right: 12px;
    font-size: 22px;
    color: #667781;
    line-height: 1;
}
.document-preview-close:hover {
    color: #111;
}
.document-preview-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.document-preview-filename {
    font-size: 14px;
    font-weight: 500;
    color: #111b21;
}
.document-preview-meta {
    font-size: 12px;
    color: #667781;
}
.document-preview-content {
    flex: 1;
    min-height: 320px;
    overflow: auto;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e5ddd5;
}
.document-preview-content img {
    max-width: 100%;
    max-height: 380px;
    object-fit: contain;
}
.document-preview-content object,
.document-preview-content embed {
    width: 100%;
    min-height: 350px;
    height: 380px;
}
.document-preview-content .doc-placeholder {
    padding: 40px;
    text-align: center;
    color: #667781;
}
.document-preview-content .doc-placeholder i {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
}

.chat-input-actions-left {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

.chat-action-btn {
    background: none;
    border: none;
    color: #667781;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 50%;
    transition: background 0.2s, color 0.2s;
}

.chat-action-btn:hover {
    background: #f0f2f5;
    color: #111;
}

.emoji-picker-wrapper {
    position: relative;
}

.emoji-picker-popover {
    position: absolute;
    bottom: 100%;
    left: 0;
    margin-bottom: 8px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    padding: 8px;
    z-index: 1000;
    max-height: 220px;
    overflow-y: auto;
}

.emoji-picker-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
}

.emoji-picker-grid span {
    font-size: 20px;
    padding: 4px;
    cursor: pointer;
    border-radius: 6px;
    text-align: center;
    transition: background 0.2s;
}

.emoji-picker-grid span:hover {
    background: #f0f2f5;
}

.attachment-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0 0;
    margin-top: 4px;
    border-top: 1px solid #eee;
}

.attachment-preview-item,
.attachment-thumbnail-item {
    position: relative;
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    background: #f0f2f5;
}

.attachment-preview-item img,
.attachment-thumbnail-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.attachment-preview-item.doc,
.attachment-thumbnail-item.doc {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #667781;
}

.attachment-preview-item .remove-attachment {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: rgba(0,0,0,0.6);
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: 12px;
    line-height: 1;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.message-attachment-img {
    max-width: 240px;
    max-height: 240px;
    border-radius: 8px;
    display: block;
    margin-top: 4px;
    cursor: pointer;
}

.message-attachment-doc {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #fff;
    border-radius: 8px;
    margin-top: 8px;
    color: #111;
    text-decoration: none;
    font-size: 14px;
    max-width: 280px;
    border: 1px solid #e9edef;
    cursor: pointer;
    transition: background 0.2s;
}

.message-attachment-doc:hover {
    background: #f5f6f6;
}

.message-attachment-doc .doc-icon-pdf {
    width: 48px;
    height: 48px;
    min-width: 48px;
    background: #e74c3c;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
}

.message-attachment-doc .doc-icon-generic {
    width: 48px;
    height: 48px;
    min-width: 48px;
    background: #f0f2f5;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667781;
    font-size: 22px;
}

.message-attachment-doc .doc-info {
    flex: 1;
    min-width: 0;
}

.message-attachment-doc .doc-filename {
    font-weight: 600;
    color: #111b21;
    word-break: break-word;
    line-height: 1.3;
}

.message-attachment-doc .doc-meta {
    font-size: 12px;
    color: #667781;
    margin-top: 2px;
}

.message-input {
    flex: 1;
    border: none;
    outline: none;
    resize: none;
    font-size: 14px;
    font-family: inherit;
    max-height: 100px;
    overflow-y: auto;
    padding: 4px 0;
}

.send-message-btn {
    background: #25d366;
    color: white;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s;
}

.send-message-btn:hover {
    background: #20ba5a;
}

.send-message-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* Details Tab Styles */
.details-container {
    position: relative;
    min-height: 400px;
}

.details-loading {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    padding: 40px;
    color: #667781;
    z-index: 20;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 8px;
    width: auto;
    min-width: 200px;
}

.details-content {
    padding: 10px 0;
}

.details-section {
    margin-top: 15px;
}

.detail-item {
    margin-bottom: 15px;
}

.detail-label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.detail-value {
    display: block;
    color: #6c757d;
    font-size: 0.95rem;
    padding: 8px 0;
}

/* Details Section Card Styles */
.details-section-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.details-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.details-section-header h5 {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.details-section-header h5 i {
    color: #667eea;
}

/* Summary View Styles (matching edit form design) */
.summary-view {
    margin-top: 10px;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

/* One full row per address/travel/passport/visa record so 2nd record is 2nd row, etc. */
.summary-grid .summary-row {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    align-items: start;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.summary-item:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
    min-width: 140px;
    display: inline-block;
}

.summary-value {
    color: #212529;
    font-weight: 500;
    font-size: 0.95rem;
    flex: 1;
}

/* Verify Button Styles */
.btn-verify-details {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 0.85rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: background 0.2s;
    margin-left: auto;
}

.btn-verify-details:hover {
    background: #0056b3;
}

.btn-verify-details i {
    font-size: 0.8rem;
}

/* Verified Badge Styles */
.verified-badge {
    background: #28a745;
    color: white;
    border-radius: 4px;
    padding: 6px 12px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-left: auto;
}

.verified-badge i {
    font-size: 0.8rem;
}

/* Audit Value Styles */
.audit-value {
    position: relative;
    background-color: #fff3cd;
    padding: 4px 8px;
    border-radius: 4px;
    border-left: 3px solid #ffc107;
}

.audit-badge {
    display: inline-flex;
    align-items: center;
    margin-left: 8px;
    color: #ffc107;
    font-size: 0.85rem;
}

.audit-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
    margin-top: 5px;
}

.btn-approve {
    background: #28a745;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 10px;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    width: 36px;
    height: 36px;
    min-width: 36px;
}

.btn-approve:hover {
    background: #218838;
}

.btn-approve i {
    font-size: 1rem;
}

.btn-reject {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 10px;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
    width: 36px;
    height: 36px;
    min-width: 36px;
}

.btn-reject:hover {
    background: #c82333;
}

.btn-reject i {
    font-size: 1rem;
}

.summary-item {
    flex-wrap: wrap;
}

.summary-item .summary-value {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
}

/* Scrollbar styling for messages */
.whatsapp-chat-messages::-webkit-scrollbar {
    width: 6px;
}

.whatsapp-chat-messages::-webkit-scrollbar-track {
    background: transparent;
}

.whatsapp-chat-messages::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.2);
    border-radius: 3px;
}

.whatsapp-chat-messages::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.3);
}

/* Documents Tab Styles */
.documents-checklist-container {
    padding: 20px 0;
}

.stages-checklist-list {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    max-height: 600px;
    overflow-y: auto;
}

.stages-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.stage-checklist-item {
    margin-bottom: 15px;
    padding: 12px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.stage-checklist-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stage-checklist-item.active {
    border-color: #9333ea;
    background: rgba(147, 51, 234, 0.05);
}

.stage-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-weight: 600;
    color: #2c3e50;
}

.stage-title {
    flex: 1;
    word-break: break-word;
    overflow-wrap: break-word;
    min-width: 0;
}

.stage-checklist-count {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: normal;
}

.stage-checklists {
    margin: 10px 0;
}

.checklist-table {
    width: 100%;
    margin: 0;
    font-size: 0.9rem;
}

.checklist-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
}

.checklist-table tbody tr:last-child {
    border-bottom: none;
}

.checklist-row td {
    padding: 8px 4px;
    vertical-align: middle;
}

.cursor-pointer,
.cursor-pointer td {
    cursor: pointer;
}

.checklist-status {
    width: 30px;
    text-align: center;
}

.checklist-name {
    flex: 1;
    font-weight: 500;
    word-break: break-word;
    overflow-wrap: break-word;
    min-width: 0;
}

.checklist-count {
    width: 50px;
    text-align: center;
}

.checklist-action {
    width: 40px;
    text-align: center;
}

.checklist-action a {
    color: #9333ea;
    text-decoration: none;
    font-size: 1rem;
}

.checklist-action a:hover {
    color: #7c3aed;
}

.add-checklist-link {
    display: inline-block;
    margin-top: 10px;
    color: #9333ea;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: color 0.2s;
}

.add-checklist-link:hover {
    color: #7c3aed;
    text-decoration: none;
}

.add-checklist-link i {
    margin-right: 5px;
}

.no-checklists {
    padding: 10px;
    font-size: 0.85rem;
    text-align: center;
}

/* Checklist Details Panel */
.checklist-details-panel {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    max-height: 600px;
    overflow-y: auto;
}

.panel-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.checklist-details-panel .table-responsive {
    overflow-x: hidden;
    overflow-y: hidden;
    max-width: 100%;
    position: relative;
    z-index: 1;
}

/* Allow dropdown menus to extend beyond container */
.checklist-details-panel .table-responsive .dropdown-menu {
    position: absolute !important;
    z-index: 9999 !important;
    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
    overflow-y: visible !important;
    overflow-x: visible !important;
}

.checklist-details-panel .table-responsive .dropdown.show {
    z-index: 9999 !important;
}

.checklist-details-panel .table-responsive .dropdown.show .dropdown-menu {
    z-index: 9999 !important;
    display: block !important;
}

/* Prevent scrollbar when hovering over dropdown button */
.checklist-details-panel .table-responsive:has(.dropdown.show) {
    overflow-y: hidden !important;
}

.checklist-details-panel .table-responsive .table {
    margin-bottom: 0;
}

.checklist-details-table {
    background: white;
    border-radius: 6px;
    width: 100%;
    table-layout: fixed;
    max-width: 100%;
}

.checklist-details-table thead {
    background: #f8f9fa;
}

.checklist-details-table thead th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
    padding: 8px 10px;
    vertical-align: middle;
    font-size: 0.9rem;
}

.checklist-details-table thead th div {
    line-height: 1.4;
}

.checklist-details-table thead th:nth-child(1) {
    width: 28%;
}

.checklist-details-table thead th:nth-child(2) {
    width: 15%;
}

.checklist-details-table thead th:nth-child(3) {
    width: 100px;
    white-space: nowrap;
}

.checklist-details-table thead th:nth-child(4) {
    width: 22%;
}

.checklist-details-table thead th:nth-child(5) {
    width: 15%;
}

.checklist-details-table tbody td {
    padding: 8px 10px;
    vertical-align: top;
    word-wrap: break-word;
    font-size: 0.9rem;
}

.checklist-details-table tbody td:nth-child(3) {
    width: 100px;
    white-space: nowrap;
}

.checklist-details-table tbody td:nth-child(4) {
    white-space: normal;
    word-break: break-word;
}

.checklist-details-table tbody td:nth-child(4) .badge {
    white-space: normal;
    display: inline-block;
    max-width: 100%;
    word-wrap: break-word;
    text-align: center;
    font-size: 0.85rem;
    padding: 4px 8px;
}

/* Tooltip styling for rejected status */
.rejected-status-badge {
    cursor: help;
    position: relative;
}

.rejected-status-badge:hover {
    opacity: 0.9;
}

/* Custom tooltip styling for rejection reason */
.tooltip {
    z-index: 1051;
}

.tooltip-inner {
    max-width: 300px;
    word-wrap: break-word;
    white-space: normal;
    text-align: left;
    padding: 8px 12px;
    font-size: 0.875rem;
    line-height: 1.4;
}

.checklist-details-table tbody td:nth-child(5) {
    white-space: nowrap;
    text-align: center;
    position: relative;
    overflow: visible !important;
}

/* Action buttons container */
.checklist-details-table tbody td:nth-child(5) .action-buttons {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
}

.checklist-details-table tbody td:nth-child(5) .action-buttons .action-row {
    display: flex;
    gap: 4px;
    align-items: center;
    justify-content: flex-start;
}

.checklist-details-table tbody td:nth-child(5) .action-buttons .btn {
    padding: 4px 8px;
    min-width: 32px;
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1;
    margin: 0;
    flex: 0 0 32px;
}

.checklist-details-table tbody td:nth-child(5) .action-buttons .btn i {
    margin: 0;
    font-size: 14px;
}

.checklist-details-table tbody td:nth-child(5) .action-buttons .btn:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.2s ease;
    cursor: pointer;
}

.checklist-details-table tbody td:nth-child(5) .action-buttons .btn:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Action dropdown menu styling */
.checklist-details-table tbody td:nth-child(5) .dropdown-menu,
.checklist-details-table .action-dropdown-menu {
    position: absolute !important;
    right: 0 !important;
    left: auto !important;
    transform: none !important;
    top: 100% !important;
    bottom: auto !important;
    margin-top: 5px !important;
    margin-bottom: 0 !important;
    min-width: 170px !important;
    max-width: 250px !important;
    width: auto !important;
    z-index: 9999 !important;
    will-change: transform;
    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
    overflow-y: visible !important;
    overflow-x: visible !important;
    padding: 0.25rem 0 !important;
    display: none;
    visibility: visible !important;
    opacity: 1 !important;
}

/* When dropdown is shown - Bootstrap adds .show class */
.checklist-details-table tbody td:nth-child(5) .dropdown.show .dropdown-menu,
.checklist-details-table .dropdown.show .action-dropdown-menu,
.checklist-details-table tbody td:nth-child(5) .dropdown-menu.show,
.checklist-details-table .action-dropdown-menu.show {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    max-height: none !important;
    height: auto !important;
    overflow: visible !important;
    overflow-y: visible !important;
    overflow-x: visible !important;
    transform: none !important;
    pointer-events: auto !important;
}

/* Ensure all dropdown items are visible */
.checklist-details-table tbody td:nth-child(5) .dropdown-menu .dropdown-item,
.checklist-details-table .action-dropdown-menu .dropdown-item {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    white-space: nowrap !important;
    padding: 0.5rem 1rem !important;
    overflow: visible !important;
    height: auto !important;
    min-height: 2.25rem !important;
    line-height: 1.5 !important;
    width: 100% !important;
}

.checklist-details-table tbody td:nth-child(5) .dropdown-menu .dropdown-divider,
.checklist-details-table .action-dropdown-menu .dropdown-divider {
    margin: 0.5rem 0 !important;
    height: 1px !important;
    display: block !important;
    visibility: visible !important;
}

/* Ensure dropdown items are always visible */
.checklist-details-table tbody td:nth-child(5) .dropdown.show .dropdown-menu .dropdown-item {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
    height: auto !important;
    min-height: 2.5rem !important;
    line-height: 1.5 !important;
}

/* Fix for preventing scrollbar when dropdown opens */
.checklist-details-panel {
    overflow: visible !important;
}

.checklist-details-table {
    overflow: visible !important;
}

.checklist-details-table tbody {
    overflow: visible !important;
}

.checklist-details-table tbody tr {
    overflow: visible !important;
    position: relative;
}

.checklist-details-table tbody td {
    overflow: visible !important;
    position: relative;
}

/* Ensure dropdown menu doesn't trigger scrollbar */
.checklist-details-table tbody td:nth-child(5) .dropdown.show .dropdown-menu {
    position: absolute;
}

/* Prevent overflow scrollbar when dropdown menu is open */
.checklist-details-panel .table-responsive .dropdown.show ~ .dropdown-menu {
    position: absolute;
}

/* Ensure dropdown doesn't trigger scrollbar */
.checklist-details-table tbody td:nth-child(5) {
    overflow: visible !important;
}

.checklist-details-table tbody td:nth-child(5) .dropdown {
    overflow: visible !important;
}

/* Ensure text wraps properly in all columns */
.checklist-details-table tbody td:nth-child(1),
.checklist-details-table tbody td:nth-child(2),
.checklist-details-table tbody td:nth-child(3) {
    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

/* Make long filenames break */
.checklist-details-table tbody td:nth-child(1) a {
    word-break: break-all;
    display: inline-block;
    max-width: 100%;
}

/* Responsive adjustments */
@media (max-width: 1400px) {
    .checklist-details-table thead th {
        padding: 6px 8px;
        font-size: 0.85rem;
    }
    
    .checklist-details-table tbody td {
        padding: 6px 8px;
        font-size: 0.85rem;
    }
    
    .checklist-details-table tbody td:nth-child(4) .badge {
        font-size: 0.75rem;
        padding: 3px 6px;
    }
}

.user-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-avatar {
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    background: #03a9f4;
    color: white;
    border-radius: 50%;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.user-name {
    font-weight: 500;
    color: #2c3e50;
}

/* Checklist status indicators */
.checklist .round {
    background: #fff;
    border: 1px solid #000;
    border-radius: 50%;
    font-size: 10px;
    line-height: 14px;
    padding: 2px 5px;
    width: 16px;
    height: 16px;
    display: inline-block;
}

.checklist span.check, .checklist-details-table span.check {
    background: #71cc53;
    color: #fff;
    border-radius: 50%;
    font-size: 10px;
    line-height: 14px;
    padding: 2px 3px;
    width: 18px;
    height: 18px;
    display: inline-block;
}

.circular-box {
    height: 24px;
    width: 24px;
    line-height: 24px;
    display: inline-block;
    text-align: center;
    box-shadow: 0 4px 6px 0 rgb(34 36 38 / 12%), 0 2px 12px 0 rgb(34 36 38 / 15%);
    background: #fff;
    border: 1px solid #d2d2d2;
    border-radius: 50%;
}

.transparent-button {
    background-color: transparent;
    border: none;
    cursor: pointer;
    width: 100%;
    height: 100%;
    padding: 0;
    font-size: 0.85rem;
    font-weight: 500;
}

/* Modal Opacity Fix for Create Checklist Modal */
#create_checklist.modal {
    opacity: 1 !important;
    z-index: 1060 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    padding-top: 70px !important; /* Account for page header height */
    /* DO NOT use pointer-events: none on modal container - it blocks all interactions */
}

#create_checklist.modal.show {
    display: block !important;
    opacity: 1 !important;
}

#create_checklist.modal:not(.show) {
    display: none !important;
    opacity: 0 !important;
}

#create_checklist.modal.fade {
    opacity: 0;
    transition: opacity 0.15s linear;
}

#create_checklist.modal.fade.show {
    opacity: 1 !important;
}

#create_checklist .modal-dialog {
    opacity: 1 !important;
    transform: translate(0, 0) !important;
    transition: transform 0.3s ease-out;
    z-index: 1061 !important;
    position: relative !important;
    pointer-events: auto !important;
    margin: 1.75rem auto !important;
    max-height: calc(100vh - 70px - 3.5rem) !important; /* Account for header and margin */
    overflow-y: auto !important;
}

#create_checklist.modal.show .modal-dialog {
    opacity: 1 !important;
    transform: translate(0, 0) !important;
}

#create_checklist .modal-content {
    opacity: 1 !important;
    background-color: #fff !important;
    color: #212529 !important;
    border: 1px solid rgba(0, 0, 0, 0.2) !important;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    pointer-events: auto !important;
    position: relative !important;
    z-index: 1 !important;
}

#create_checklist .modal-header {
    opacity: 1 !important;
    background-color: #fff !important;
    color: #212529 !important;
    position: relative !important;
    z-index: 2 !important;
}

#create_checklist .modal-body {
    opacity: 1 !important;
    background-color: #fff !important;
    color: #212529 !important;
    pointer-events: auto !important;
}

#create_checklist .modal-body input,
#create_checklist .modal-body textarea,
#create_checklist .modal-body select,
#create_checklist .modal-body button,
#create_checklist .modal-body .ts-wrapper {
    pointer-events: auto !important;
    opacity: 1 !important;
}

#create_checklist .modal-body .ts-wrapper {
    width: 100% !important;
    display: block !important;
    min-height: 38px !important;
}
/* Tom Select menu is appended to body (dropdownParent); stack above modal + backdrop */
.ts-dropdown.mm-cp-create-checklist-dropdown {
    z-index: 100060 !important;
    width: min(520px, 92vw) !important;
    min-width: 260px !important;
    box-sizing: border-box;
}

#create_checklist_submit_btn {
    pointer-events: auto !important;
    cursor: pointer !important;
    z-index: 1 !important;
    position: relative !important;
}

#create_checklist_submit_btn:hover {
    opacity: 0.9 !important;
}

#create_checklist_submit_btn:disabled {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
}

/* Ensure backdrop doesn't block create_checklist modal interactions */
body:has(#create_checklist.modal.show) .modal-backdrop {
    z-index: 1059 !important;
}

#create_checklist.modal.show {
    z-index: 1060 !important;
}

#create_checklist .modal-dialog {
    pointer-events: auto !important;
    z-index: 1061 !important;
}

/* Ensure backdrop has proper opacity for create_checklist modal - set to 0.1 */
.modal-backdrop.create-checklist-backdrop,
.modal-backdrop.create-checklist-backdrop.show {
    opacity: 0.1 !important;
    background-color: rgba(0, 0, 0, 0.1) !important;
    z-index: 1059 !important;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    transition: all 0.2s;
}

.feature-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.feature-item i {
    font-size: 1.5rem;
}

.feature-item span {
    font-weight: 500;
    color: #2c3e50;
}

.text-purple {
    color: #9333ea !important;
}

.in-progress-section {
    padding: 20px;
}

.in-progress-single-line {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: space-between;
}

.in-progress-title {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    flex-shrink: 0;
}

.in-progress-actions {
    display: flex !important;
    gap: 10px;
    align-items: center;
    visibility: visible !important;
    opacity: 1 !important;
    flex-shrink: 0;
}

.in-progress-actions .btn {
    white-space: nowrap;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Reopen button - styled like matter list page (purple) */
.client-portal-reopen-btn,
.stage-navigation-buttons .matter-detail-reopen-btn.client-portal-reopen-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: 1px solid #667eea !important;
    color: white !important;
    padding: 6px 12px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
}
.client-portal-reopen-btn:hover,
.stage-navigation-buttons .matter-detail-reopen-btn.client-portal-reopen-btn:hover {
    background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
    border-color: #5a6fd8 !important;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    color: white !important;
}

.stage-navigation-buttons {
    display: flex !important;
    flex-direction: column;
    gap: 10px;
    align-items: flex-end;
    flex-shrink: 0;
    visibility: visible !important;
    opacity: 1 !important;
}

.stage-navigation-buttons .btn {
    white-space: nowrap;
    width: 100%;
    min-width: 180px;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Back to Previous Stage — always visible (workflow + client portal button IDs) */
.stage-navigation-buttons .btn-outline-primary,
#back-to-previous-stage,
#workflow-tab-back-to-previous-stage {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    z-index: 10 !important;
    width: 100% !important;
    min-width: 180px !important;
    color: #3490dc !important;
    border-color: #3490dc !important;
    background-color: #ffffff !important;
}

.stage-navigation-buttons .btn-outline-primary:hover,
.stage-navigation-buttons .btn-outline-primary:focus,
#back-to-previous-stage:hover,
#back-to-previous-stage:focus,
#workflow-tab-back-to-previous-stage:hover,
#workflow-tab-back-to-previous-stage:focus {
    color: #ffffff !important;
    background-color: #3490dc !important;
    border-color: #3490dc !important;
}

/* Override any global styles that might hide the button */
.stage-navigation-buttons #back-to-previous-stage,
.stage-navigation-buttons #workflow-tab-back-to-previous-stage,
.in-progress-section #back-to-previous-stage,
.in-progress-section #workflow-tab-back-to-previous-stage,
.info-card #back-to-previous-stage,
.info-card #workflow-tab-back-to-previous-stage {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.current-stage-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.stage-label {
    font-weight: 600;
    color: #495057;
    margin: 0;
    text-align: center;
}

.stage-value-container {
    display: flex;
    align-items: center;
    justify-content: center;
}

.stage-value {
    font-size: 1rem;
    color: #28a745;
    font-weight: 500;
    text-align: center;
}

.overall-progress-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.progress-label {
    font-weight: 600;
    color: #495057;
    margin: 0;
    text-align: center;
}

.progress-circle-wrapper {
    position: relative;
}

.progress-circle {
    position: relative;
    width: 80px;
    height: 80px;
}

.progress-ring {
    transform: rotate(-90deg);
}

.progress-ring-circle {
    transition: stroke-dashoffset 0.35s;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.9rem;
    font-weight: 600;
    color: #007bff;
}


@media (max-width: 768px) {
    .in-progress-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .in-progress-actions {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .in-progress-details {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
                                          
<?php
                // Tab names that should NOT be treated as matter reference
                $validTabNames = ['personaldetails', 'activityfeed', 'noteterm', 'personaldocuments', 'visadocuments', 'eoiroi', 'emails', 'formgenerations', 'formgenerationsl', 'client_portal', 'workflow', 'checklists'];
                $isMatterIdInUrl = isset($id1) && $id1 != "" && !in_array(strtolower($id1), array_map('strtolower', $validTabNames));
                
                if ($isMatterIdInUrl) {
                    // If client unique reference id is present in URL (and is not a tab name)
                    $selectedMatter = DB::table('client_matters as cm')
                        ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $fetchedData->id)
                        ->where('cm.client_unique_matter_no', $id1)
                        ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_id', 'cm.workflow_stage_id', 'cm.matter_status')
                        ->first();
                } else {
                    // Get the latest matter (active or inactive)
                    $selectedMatter = DB::table('client_matters as cm')
                        ->leftJoin('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $fetchedData->id)
                        ->select('cm.id', 'cm.client_unique_matter_no', 'm.title', 'cm.sel_matter_id', 'cm.workflow_id', 'cm.workflow_stage_id', 'cm.matter_status')
                        ->orderBy('cm.id', 'desc')
                        ->first();
                }
?>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // Portal toggle functionality (both sidebar and tab toggles)
    function handlePortalToggle(toggleElement) {
        const clientId = toggleElement.getAttribute('data-client-id');
        const isChecked = toggleElement.checked;
        const statusValue = isChecked ? 1 : 0;
        
        // Show loading state
        toggleElement.disabled = true;
        
        // Show loader based on which toggle was clicked
        const toggleId = toggleElement.id;
        let loaderElement = null;
        
        if (toggleId === 'client-portal-toggle-tab') {
            loaderElement = document.getElementById('portal-toggle-loader-tab');
        } else if (toggleId === 'client-portal-toggle') {
            loaderElement = document.getElementById('portal-toggle-loader-sidebar');
        }
        
        if (loaderElement) {
            loaderElement.style.display = 'inline-block';
        }
        
        fetch('{{ route("clients.toggleClientPortal") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                client_id: clientId,
                status: statusValue
            })
        })
        .then(response => response.json())
        .then(data => {
            toggleElement.disabled = false;
            
            // Hide loader
            if (loaderElement) {
                loaderElement.style.display = 'none';
            }
            
            if (data.success) {
                // Update both toggles to stay in sync
                const sidebarToggle = document.getElementById('client-portal-toggle');
                const tabToggle = document.getElementById('client-portal-toggle-tab');
                
                if (sidebarToggle) sidebarToggle.checked = !!statusValue;
                if (tabToggle) tabToggle.checked = !!statusValue;
                
                // Show success message
                alert(data.message);
                
                // Reload the page to update the tab content
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                // Revert toggle state on error
                toggleElement.checked = !isChecked;
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toggleElement.disabled = false;
            toggleElement.checked = !isChecked;
            
            // Hide loader on error
            if (loaderElement) {
                loaderElement.style.display = 'none';
            }
            
            alert('Error updating portal status. Please try again.');
        });
    }

    // Handle sidebar toggle
    const sidebarToggle = document.getElementById('client-portal-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('change', function() {
            handlePortalToggle(this);
        });
    }

    // Handle tab toggle
    const tabToggle = document.getElementById('client-portal-toggle-tab');
    if (tabToggle) {
        tabToggle.addEventListener('change', function() {
            handlePortalToggle(this);
        });
    }
    
    // Back to Previous Stage button handler
    function ensureBackButtonVisible() {
        ['back-to-previous-stage', 'workflow-tab-back-to-previous-stage'].forEach(function(btnId) {
            const backStageBtn = document.getElementById(btnId);
            if (!backStageBtn) return;

            backStageBtn.style.setProperty('display', 'inline-block', 'important');
            backStageBtn.style.setProperty('visibility', 'visible', 'important');
            backStageBtn.style.setProperty('opacity', '1', 'important');
            backStageBtn.style.setProperty('position', 'relative', 'important');
            backStageBtn.style.setProperty('z-index', '10', 'important');
            backStageBtn.style.setProperty('color', '#3490dc', 'important');
            backStageBtn.style.setProperty('border-color', '#3490dc', 'important');
            backStageBtn.style.setProperty('background-color', '#ffffff', 'important');

            const parentContainer = backStageBtn.closest('.stage-navigation-buttons');
            if (parentContainer) {
                parentContainer.style.setProperty('display', 'flex', 'important');
                parentContainer.style.setProperty('visibility', 'visible', 'important');
                parentContainer.style.setProperty('opacity', '1', 'important');
            }
        });
    }
    
    // Ensure button is visible immediately and after a short delay
    ensureBackButtonVisible();
    setTimeout(ensureBackButtonVisible, 100);
    setTimeout(ensureBackButtonVisible, 500);

    // Back to Previous Stage and Proceed to Next Stage — handled by workflow-tab.js (partial tab refresh, no full page reload)
    $(document).on('click', '#decision-received-submit', function() {
        const outcome = document.getElementById('decision-outcome')?.value;
        const note = document.getElementById('decision-note')?.value;
        const matterId = document.getElementById('decision-received-matter-id')?.value;
        const outcomeErr = document.querySelector('.decision-outcome-error strong');
        const noteErr = document.querySelector('.decision-note-error strong');

        if (outcomeErr) outcomeErr.textContent = '';
        if (noteErr) noteErr.textContent = '';

        if (!outcome || outcome.trim() === '') {
            if (outcomeErr) outcomeErr.textContent = 'Please select an outcome.';
            return;
        }
        if (!note || note.trim() === '') {
            if (noteErr) noteErr.textContent = 'Please enter a note.';
            return;
        }

        const btn = this;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '@icon('fa-spinner', ['spin' => true]) Processing...';
        $('#decision-received-modal').modal('hide');

        const decisionPayload = { matter_id: matterId, decision_outcome: outcome, decision_note: note };
        if (document.querySelector('.client-nav-button.active')?.getAttribute('data-tab') === 'client_portal') {
            decisionPayload.source = 'client_portal';
        }
        fetch('{{ route("clients.matter.update-next-stage") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            body: JSON.stringify(decisionPayload)
        })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = orig;
            if (data.status) {
                const activeTab = document.querySelector('.client-nav-button.active')?.getAttribute('data-tab');
                if (activeTab === 'workflow' && typeof window.handleWorkflowStageUpdateSuccess === 'function') {
                    window.handleWorkflowStageUpdateSuccess(data.message || 'Matter has been successfully moved to the next stage.');
                } else if (activeTab === 'client_portal' && typeof window.handleClientPortalStageUpdateSuccess === 'function') {
                    window.handleClientPortalStageUpdateSuccess(data.message || 'Matter has been successfully moved to the next stage.');
                } else {
                    alert(data.message || 'Matter has been successfully moved to the next stage.');
                    window.location.reload();
                }
            } else {
                alert(data.message || 'Failed to move to next stage.');
                $('#decision-received-modal').modal('show');
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = orig;
            alert('An error occurred.');
            $('#decision-received-modal').modal('show');
        });
    });

    // Discontinue button - opens modal (shared with Workflow tab)
    $(document).on('click', '.client-portal-discontinue-btn', function() {
        const matterId = this.getAttribute('data-matter-id');
        if (!matterId) { alert('Error: Matter ID not found'); return; }
        document.getElementById('discontinue-matter-id').value = matterId;
        document.getElementById('discontinue-reason').value = '';
        document.getElementById('discontinue-notes').value = '';
        const errEl = document.querySelector('.discontinue-reason-error strong');
        if (errEl) errEl.textContent = '';
        $('#discontinue-matter-modal').modal('show');
    });
    
    // Client Portal Tabs Switching Functionality
    const clientPortalTabItems = document.querySelectorAll('.client-portal-tab-item');
    const clientPortalTabPanes = document.querySelectorAll('.client-portal-tab-pane');
  
    
    
    // Store client matter ID and user info for messages (server value; fallback to dropdown)
    let clientMatterId = @json(($selectedMatter && isset($selectedMatter->id)) ? $selectedMatter->id : null);
    const currentUserId = @json(Auth::guard('admin')->id() ?? null);
    // Web route for attachment download (session auth) - use this so click-to-download works in browser
    const attachmentDownloadBaseUrl = '{{ url("/clients/message-attachment") }}';
    const markMessageAsReadBaseUrl = '{{ url("/clients/messages") }}';
    
    // Get effective client matter ID: server value or dropdown fallback (for URLs like /client_portal)
    function getEffectiveClientMatterId() {
        if (clientMatterId) return clientMatterId;
        const generalCheckbox = document.querySelector('.general_matter_checkbox_client_detail:checked');
        if (generalCheckbox && generalCheckbox.value) return generalCheckbox.value;
        const dropdown = document.getElementById('sel_matter_id_client_detail');
        if (dropdown && dropdown.value) return dropdown.value;
        return null;
    }
    // Use Reverb configuration (compatible with Pusher protocol)
    const pusherAppKey = '{{ config("broadcasting.connections.reverb.key") ?: config("broadcasting.connections.pusher.key") }}';
    const pusherCluster = '{{ config("broadcasting.connections.reverb.options.cluster") ?: config("broadcasting.connections.pusher.options.cluster", "ap2") }}';
    const reverbHost = '{{ config("broadcasting.connections.reverb.options.host", "127.0.0.1") }}';
    const reverbPort = {{ config("broadcasting.connections.reverb.options.port", 8080) }};
    const reverbScheme = '{{ config("broadcasting.connections.reverb.options.scheme", "http") }}';
    // Use ws (not wss) for localhost so Reverb works without TLS
    const reverbUseTLS = reverbScheme === 'https' && reverbHost !== 'localhost' && reverbHost !== '127.0.0.1';
    
    // Debug logging
    console.log('Messages Tab Initialization:', {
        clientMatterId: clientMatterId,
        currentUserId: currentUserId,
        pusherAppKey: pusherAppKey ? 'Set' : 'Missing',
        pusherCluster: pusherCluster,
        selectedMatterExists: @json($selectedMatter ? true : false)
    });
    
    // Pusher variables
    let pusher = null;
    let subscribedChannel = null;
    let messagesLoaded = false;
    
    clientPortalTabItems.forEach(function(tabItem) {
        const tabLink = tabItem.querySelector('.client-portal-tab-link');
        if (tabLink) {
            tabLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetTab = tabItem.getAttribute('data-tab');
                
                // Remove active class from all tabs and panes
                clientPortalTabItems.forEach(function(item) {
                    item.classList.remove('active');
                });
                clientPortalTabPanes.forEach(function(pane) {
                    pane.classList.remove('active');
                });
                
                // Add active class to clicked tab
                tabItem.classList.add('active');
                
                // Show corresponding tab pane
                const targetPane = document.getElementById(targetTab + '-tab');
                if (targetPane) {
                    targetPane.classList.add('active');
                    
                    // Initialize messages if Messages tab is clicked
                    if (targetTab === 'messages') {
                        const effectiveMatterId = getEffectiveClientMatterId();
                        console.log('Messages tab clicked', {
                            clientMatterId: effectiveMatterId,
                            currentUserId: currentUserId
                        });
                        
                        if (effectiveMatterId && currentUserId) {
                            initializeMessages();
                        } else {
                            const emptyDiv = document.getElementById('messages-empty');
                            if (emptyDiv) {
                                emptyDiv.style.display = 'block';
                                if (!effectiveMatterId) {
                                    emptyDiv.innerHTML = '<p class="text-muted">Please select a matter to view messages.</p>';
                                } else {
                                    emptyDiv.innerHTML = '<p class="text-muted">No messages yet. Start a conversation!</p>';
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    
    // Check if Messages tab is already active on page load
    setTimeout(function() {
        const messagesTab = document.getElementById('messages-tab');
        const messagesTabLink = document.querySelector('.client-portal-tab-item[data-tab="messages"]');
        
        // Check if Messages tab is active (either by class or by checking which tab link has active class)
        const isMessagesTabActive = (messagesTab && messagesTab.classList.contains('active')) || 
                                    (messagesTabLink && messagesTabLink.classList.contains('active'));
        
        const effectiveMatterId = getEffectiveClientMatterId();
        
        if (isMessagesTabActive && effectiveMatterId && currentUserId) {
            console.log('Messages tab is active on page load, initializing...');
            initializeMessages();
        } else if (!effectiveMatterId && isMessagesTabActive) {
            console.warn('Cannot load messages: clientMatterId is missing');
            const emptyDiv = document.getElementById('messages-empty');
            if (emptyDiv) {
                emptyDiv.style.display = 'block';
                emptyDiv.innerHTML = '<p class="text-muted">Please select a matter to view messages.</p>';
            }
        } else if (!currentUserId && isMessagesTabActive) {
            console.warn('Cannot load messages: currentUserId is missing');
        } else if (!isMessagesTabActive) {
            console.log('Messages tab not active on page load', {
                isMessagesTabActive: isMessagesTabActive,
                clientMatterId: effectiveMatterId,
                currentUserId: currentUserId
            });
        }
    }, 300); // Small delay to ensure DOM is fully loaded
    
    // Initialize Messages with Pusher
    function initializeMessages() {
        if (messagesLoaded) {
            console.log('Messages already loaded, skipping initialization');
            return;
        }
        
        const effectiveMatterId = getEffectiveClientMatterId();
        if (!effectiveMatterId) {
            console.warn('Cannot initialize messages: clientMatterId is missing');
            return;
        }
        
        if (!currentUserId) {
            console.warn('Cannot initialize messages: currentUserId is missing');
            return;
        }
        
        console.log('Initializing messages...');
        messagesLoaded = true;
        
        // Load existing messages first
        loadMessages(getEffectiveClientMatterId());
        
        // Then connect to Pusher for real-time updates (non-blocking)
        setTimeout(() => {
            connectToPusher();
        }, 500);
    }
    
    // Load existing messages from database
    async function loadMessages(matterIdParam) {
        const matterId = matterIdParam || getEffectiveClientMatterId();
        const messagesContainer = document.getElementById('whatsapp-chat-messages');
        const loadingDiv = document.getElementById('messages-loading');
        const emptyDiv = document.getElementById('messages-empty');
        
        if (!messagesContainer) {
            console.error('Messages container not found');
            return;
        }
        
        if (!matterId) {
            console.warn('Client Matter ID is missing. Cannot load messages.');
            if (emptyDiv) {
                emptyDiv.style.display = 'block';
                emptyDiv.innerHTML = '<p class="text-muted">Please select a matter to view messages.</p>';
            }
            return;
        }
        
        console.log('Loading messages for client_matter_id:', matterId);
        
        if (loadingDiv) loadingDiv.style.display = 'block';
        if (emptyDiv) emptyDiv.style.display = 'none';
        
        try {
            const url = `{{ URL::to('/clients/matter-messages') }}?client_matter_id=${matterId}`;
            console.log('Fetching messages from:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Messages data received:', data);
            
            if (loadingDiv) loadingDiv.style.display = 'none';
            
            // Check if response is successful and has messages
            if (data.success !== false && data.data && data.data.messages && Array.isArray(data.data.messages)) {
                if (data.data.messages.length > 0) {
                    console.log(`Displaying ${data.data.messages.length} messages`);
                    if (emptyDiv) emptyDiv.style.display = 'none';
                    displayMessages(data.data.messages);
                    markReceivedMessagesAsRead(data.data.messages);
                } else {
                    console.log('No messages found for this matter');
                    if (emptyDiv) {
                        emptyDiv.style.display = 'block';
                        emptyDiv.innerHTML = '<p class="text-muted">No messages yet. Start a conversation!</p>';
                    }
                }
            } else if (data.success === false) {
                console.error('API returned error:', data.message || data.error);
                if (emptyDiv) {
                    emptyDiv.style.display = 'block';
                    emptyDiv.innerHTML = '<p class="text-danger">Error: ' + (data.message || 'Failed to load messages') + '</p>';
                }
            } else {
                console.warn('Unexpected response format:', data);
                if (emptyDiv) {
                    emptyDiv.style.display = 'block';
                    emptyDiv.innerHTML = '<p class="text-muted">No messages yet. Start a conversation!</p>';
                }
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            if (loadingDiv) loadingDiv.style.display = 'none';
            if (emptyDiv) {
                emptyDiv.style.display = 'block';
                emptyDiv.innerHTML = '<p class="text-danger">Error loading messages. Please try again.</p>';
            }
        }
    }
    
    // Mark received (unread) messages as read when staff views them - so client sees "Read" on mobile
    function markMessageAsRead(messageId) {
        if (!messageId || !markMessageAsReadBaseUrl || !currentUserId) return;
        const url = markMessageAsReadBaseUrl + '/' + messageId + '/mark-read';
        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            credentials: 'same-origin'
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success && typeof updateMessageReadIcon === 'function') {
                updateMessageReadIcon(messageId, true, new Date().toISOString());
            }
        }).catch(function(err) { console.warn('Mark as read failed:', err); });
    }
    
    function markReceivedMessagesAsRead(messages) {
        if (!messages || !Array.isArray(messages) || !currentUserId) return;
        messages.forEach(function(msg) {
            if (msg.is_sent) return;
            const recipients = msg.recipients || [];
            const myRecipient = recipients.find(function(r) { return r.recipient_id == currentUserId; });
            if (myRecipient && !myRecipient.is_read && msg.id) {
                markMessageAsRead(msg.id);
            }
        });
    }
    
    // Connect to Pusher for real-time messaging
    function connectToPusher() {
        if (!pusherAppKey || !currentUserId) {
            console.warn('Pusher configuration missing - real-time updates will not work', {
                pusherAppKey: !!pusherAppKey,
                currentUserId: !!currentUserId
            });
            return;
        }
        
        console.log('Connecting to Pusher...');
        
        // Load Pusher JS if not already loaded
        if (typeof Pusher === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pusher/7.2.0/pusher.min.js';
            script.onload = function() {
                console.log('Pusher JS loaded, initializing...');
                initializePusher();
            };
            script.onerror = function() {
                console.error('Failed to load Pusher JS library');
            };
            document.head.appendChild(script);
        } else {
            initializePusher();
        }
    }
    
    function initializePusher() {
        try {
            // Check if using Reverb (local WebSocket server) or Pusher Cloud
            const isReverb = reverbHost && reverbPort && reverbScheme;
            
            const pusherConfig = {
                cluster: pusherCluster,
                forceTLS: reverbUseTLS,
                encrypted: reverbUseTLS,
                authEndpoint: '/broadcasting/auth',
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    }
                }
            };
            
            // If using Reverb, configure custom host and port
            if (isReverb) {
                pusherConfig.wsHost = reverbHost;
                pusherConfig.wsPort = reverbPort;
                pusherConfig.wssPort = reverbPort;
                pusherConfig.disableStats = true;
                pusherConfig.enabledTransports = ['ws', 'wss'];
                console.log('🔌 Configuring for Laravel Reverb:', { host: reverbHost, port: reverbPort, scheme: reverbScheme });
            }
            
            pusher = new Pusher(pusherAppKey, pusherConfig);
            
            pusher.connection.bind('connected', () => {
                console.log('✅ Connected to ' + (isReverb ? 'Laravel Reverb' : 'Pusher'));
                subscribeToChannel();
            });
            
            pusher.connection.bind('error', (error) => {
                console.error('Pusher error:', error);
            });
            
        } catch (error) {
            console.error('Failed to initialize Pusher:', error);
        }
    }
    
    function subscribeToChannel() {
        if (!pusher || !currentUserId) return;
        
        const channelName = `private-user.${currentUserId}`;
        
        try {
            subscribedChannel = pusher.subscribe(channelName);
            
            subscribedChannel.bind('pusher:subscription_succeeded', () => {
                console.log('✅ Subscribed to channel:', channelName);
            });
            
            // Listen for new messages
            subscribedChannel.bind('message.sent', (data) => {
                console.log('📨 New message received:', data);
                const effectiveMatterId = getEffectiveClientMatterId();
                if (data.message && data.message.client_matter_id == effectiveMatterId) {
                    addMessageToDisplay(data.message, data.message.sender_id == currentUserId);
                }
            });
            
            // Listen for message read status updates (recipient marked as read - show blue icon to sender)
            subscribedChannel.bind('message.updated', (data) => {
                console.log('📬 Message read status updated:', data);
                const effectiveMatterId = getEffectiveClientMatterId();
                if (data.message && data.message.client_matter_id == effectiveMatterId && data.message.sender_id == currentUserId && data.message.is_read) {
                    updateMessageReadIcon(data.message.id, true, data.message.read_at);
                }
            });
            
            // Listen for unread count updates
            subscribedChannel.bind('unread.count.updated', (data) => {
                console.log('📊 Unread count updated:', data);
            });

            // Listen for notification bell count updates (uses global handler - works even when Messages tab closed)
            subscribedChannel.bind('notification.count.updated', (data) => {
                try {
                    const count = data.unread_count !== undefined ? parseInt(data.unread_count, 10) : 0;
                    const opts = { showToast: true };
                    if (data.message) opts.message = data.message;
                    if (data.url) opts.url = data.url;
                    if (typeof window.updateNotificationBell === 'function') {
                        window.updateNotificationBell(count, opts);
                    } else {
                        const el = document.getElementById('countbell_notification');
                        if (el) {
                            el.textContent = count > 0 ? count : '';
                            el.style.display = count > 0 ? 'inline' : 'none';
                        }
                    }
                } catch (err) {
                    console.warn('Notification count update error:', err);
                }
            });

        } catch (error) {
            console.error('Failed to subscribe:', error);
        }
    }
    
    // Display messages in WhatsApp style
    function displayMessages(messages) {
        const messagesContainer = document.getElementById('whatsapp-chat-messages');
        if (!messagesContainer) return;
        
        messagesContainer.innerHTML = '';
        
        messages.forEach(function(message) {
            const isSent = message.is_sent;
            addMessageToDisplay(message, isSent, false);
        });
        
        scrollToBottom();
    }
    
    // Add a single message to display
    function addMessageToDisplay(message, isSent, animate = true) {
        const messagesContainer = document.getElementById('whatsapp-chat-messages');
        const emptyDiv = document.getElementById('messages-empty');
        
        if (!messagesContainer) return;
        
        // Duplicate check: skip if this message (by ID) is already displayed (prevents duplicate from optimistic update + Pusher)
        const messageId = message.id;
        if (messageId != null && messageId !== '' && messagesContainer.querySelector(`[data-message-id="${messageId}"]`)) {
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `whatsapp-message ${isSent ? 'message-sent' : 'message-received'}`;
        if (messageId != null && messageId !== '') {
            messageDiv.setAttribute('data-message-id', messageId);
        }
        if (!animate) {
            messageDiv.style.animation = 'none';
        }
        
        const messageBubble = document.createElement('div');
        messageBubble.className = 'message-bubble';
        
        // Sender info for received messages (handles broadcast: sender as string, getMatterMessages: sender as object)
        var displaySenderName = null;
        var displaySenderInitials = null;
        if (!isSent) {
            var raw = null;
            if (typeof message.sender === 'string' && (raw = String(message.sender || '').trim())) {
                displaySenderName = raw;
                displaySenderInitials = (message.sender_shortname || message.sender_initials) || (displaySenderName.length >= 2 ? (displaySenderName.split(/\s+/).map(function(w){ return (w || '').charAt(0); }).join('').substring(0,2).toUpperCase()) : (displaySenderName.charAt(0) || 'U').toUpperCase());
            } else if ((raw = (message.sender_name != null && message.sender_name !== '') ? String(message.sender_name).trim() : null)) {
                displaySenderName = raw;
                displaySenderInitials = (message.sender_initials || message.sender_shortname) || (message.sender && typeof message.sender === 'object' && message.sender.first_name ? ((message.sender.first_name || '').charAt(0) + (message.sender.last_name || '').charAt(0)).toUpperCase() : null) || (displaySenderName.length >= 2 ? displaySenderName.substring(0,2).toUpperCase() : 'U');
            } else if (message.sender && typeof message.sender === 'object' && (message.sender.full_name || message.sender.first_name || message.sender.last_name)) {
                raw = (message.sender.full_name || ((message.sender.first_name || '') + ' ' + (message.sender.last_name || '')).trim()).trim();
                displaySenderName = raw || 'Unknown';
                displaySenderInitials = (message.sender_initials || message.sender_shortname) || (message.sender.first_name ? (message.sender.first_name.charAt(0) || 'U').toUpperCase() : 'U');
            } else {
                displaySenderName = 'Unknown';
                displaySenderInitials = 'U';
            }
            displaySenderName = (displaySenderName && displaySenderName !== 'undefined') ? displaySenderName : 'Unknown';
            displaySenderInitials = (displaySenderInitials && displaySenderInitials !== 'undefined') ? displaySenderInitials : 'U';
            if (displaySenderName) {
                const senderInfo = document.createElement('div');
                senderInfo.className = 'message-sender-info';
                const avatar = document.createElement('div');
                avatar.className = 'message-avatar';
                avatar.textContent = displaySenderInitials || 'U';
                const senderName = document.createElement('span');
                senderName.className = 'sender-name';
                senderName.textContent = (displaySenderName && String(displaySenderName) !== 'undefined') ? String(displaySenderName) : 'Unknown';
                senderInfo.appendChild(avatar);
                senderInfo.appendChild(senderName);
                messageBubble.appendChild(senderInfo);
            }
        }
        
        // Message content (text + attachments)
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        const textPart = (message.message || '').trim();
        if (textPart) {
            const textEl = document.createElement('span');
            textEl.style.whiteSpace = 'pre-wrap';
            textEl.textContent = textPart;
            messageContent.appendChild(textEl);
        }
        const attachments = message.attachments || [];
        attachments.forEach(function(att) {
            var downloadUrl = (att.id && typeof attachmentDownloadBaseUrl !== 'undefined') ? (attachmentDownloadBaseUrl + '/' + att.id + '/download') : (att.url || '');
            if (!downloadUrl) return;
            if (att.type === 'image' && downloadUrl) {
                const img = document.createElement('a');
                img.href = downloadUrl;
                img.target = '_blank';
                img.rel = 'noopener';
                img.className = 'message-attachment-img';
                const imgEl = document.createElement('img');
                imgEl.src = downloadUrl;
                imgEl.alt = att.filename || 'Image';
                imgEl.style.maxWidth = '240px';
                imgEl.style.maxHeight = '240px';
                imgEl.style.borderRadius = '8px';
                imgEl.style.display = 'block';
                img.appendChild(imgEl);
                messageContent.appendChild(img);
            } else if (downloadUrl) {
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = att.filename || 'document';
                link.target = '_blank';
                link.rel = 'noopener';
                link.className = 'message-attachment-doc';
                const isPdf = (att.filename || '').toLowerCase().endsWith('.pdf') || att.type === 'document';
                const iconDiv = document.createElement('div');
                iconDiv.className = isPdf ? 'doc-icon-pdf' : 'doc-icon-generic';
                iconDiv.textContent = isPdf ? 'PDF' : '';
                if (!isPdf) {
                    iconDiv.innerHTML = crmI('fas fa-file-alt');
                }
                const infoDiv = document.createElement('div');
                infoDiv.className = 'doc-info';
                const nameEl = document.createElement('div');
                nameEl.className = 'doc-filename';
                nameEl.textContent = att.filename || 'Document';
                const metaEl = document.createElement('div');
                metaEl.className = 'doc-meta';
                const ext = (att.filename || '').split('.').pop().toUpperCase() || 'DOC';
                const sizeStr = att.size ? formatFileSize(att.size) : '';
                metaEl.textContent = (isPdf ? '1 page • ' : '') + ext + (sizeStr ? ' • ' + sizeStr : '');
                infoDiv.appendChild(nameEl);
                infoDiv.appendChild(metaEl);
                link.appendChild(iconDiv);
                link.appendChild(infoDiv);
                messageContent.appendChild(link);
            }
        });
        messageBubble.appendChild(messageContent);
        
        // Timestamp and read receipt (for sent messages: WhatsApp-style double-check icon)
        const timestampRow = document.createElement('div');
        timestampRow.className = 'message-timestamp-row';
        
        const timestamp = document.createElement('div');
        timestamp.className = 'message-timestamp';
        if (message.sent_at || message.created_at) {
            const dateStr = message.sent_at || message.created_at;
            const date = new Date(dateStr);
            if (!isNaN(date.getTime())) {
                timestamp.textContent = formatMessageTime(date);
            }
        }
        timestampRow.appendChild(timestamp);
        
        // Read receipt icon: sent messages - grey (unread) or blue (read); received messages - blue when staff has read
        const readIcon = document.createElement('span');
        readIcon.className = 'message-read-icon';
        if (isSent) {
            const readByRecipient = message.read_by_recipient === true;
            readIcon.classList.add(readByRecipient ? 'message-read-icon--read' : 'message-read-icon--unread');
            readIcon.setAttribute('data-read-status', readByRecipient ? 'read' : 'unread');
        } else {
            const myRecipient = (message.recipients || []).find(function(r) { return r.recipient_id == currentUserId; });
            const readByMe = myRecipient ? !!myRecipient.is_read : true;
            readIcon.classList.add(readByMe ? 'message-read-icon--read' : 'message-read-icon--unread');
            readIcon.setAttribute('data-read-status', readByMe ? 'read' : 'unread');
        }
        readIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 5l2.5 2.5L7 2"/><path d="M6 5l2.5 2.5L14 1"/></svg>';
        timestampRow.appendChild(readIcon);
        
        // Message info chevron - opens Message info popup
        var readByMe = false;
        if (!isSent) {
            const myRec = (message.recipients || []).find(function(r) { return r.recipient_id == currentUserId; });
            readByMe = myRec ? !!myRec.is_read : true;
        }
        const messageInfoData = {
            id: message.id,
            message: message.message || '',
            sent_at: message.sent_at || message.created_at,
            read_at: message.read_at || null,
            read_by_recipient: message.read_by_recipient === true,
            read_by_me: readByMe,
            is_sent: isSent
        };
        const chevronBtn = document.createElement('span');
        chevronBtn.className = 'message-info-chevron';
        chevronBtn.title = 'Message info';
        chevronBtn.setAttribute('data-message-info', JSON.stringify(messageInfoData));
        chevronBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>';
        chevronBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            try {
                const data = JSON.parse(this.getAttribute('data-message-info') || '{}');
                showMessageInfo(data);
            } catch (err) {
                console.error('Failed to parse message info:', err);
            }
        });
        timestampRow.appendChild(chevronBtn);
        
        messageBubble.appendChild(timestampRow);
        
        messageDiv.appendChild(messageBubble);
        messagesContainer.appendChild(messageDiv);
        
        if (!isSent && messageId && currentUserId) {
            const recipients = message.recipients || message.recipient_ids || [];
            const myRecipient = recipients.find(function(r) { return (r.recipient_id || r.id) == currentUserId; });
            if (myRecipient && myRecipient.is_read !== true) markMessageAsRead(messageId);
        }
        scrollToBottom();
    }
    
    // Update read receipt icon when recipient marks message as read (real-time)
    function updateMessageReadIcon(messageId, isRead, readAt) {
        const messagesContainer = document.getElementById('whatsapp-chat-messages');
        if (!messagesContainer) return;
        const messageEl = messagesContainer.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageEl) return;
        const readIcon = messageEl.querySelector('.message-read-icon');
        if (readIcon) {
            readIcon.className = 'message-read-icon ' + (isRead ? 'message-read-icon--read' : 'message-read-icon--unread');
            readIcon.setAttribute('data-read-status', isRead ? 'read' : 'unread');
            readIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 5l2.5 2.5L7 2"/><path d="M6 5l2.5 2.5L14 1"/></svg>';
        }
        // Update Message info chevron data so popup shows correct read status
        const chevron = messageEl.querySelector('.message-info-chevron');
        if (chevron) {
            try {
                const data = JSON.parse(chevron.getAttribute('data-message-info') || '{}');
                if (data.is_sent) data.read_by_recipient = isRead; else data.read_by_me = isRead;
                if (readAt) data.read_at = readAt;
                chevron.setAttribute('data-message-info', JSON.stringify(data));
            } catch (e) {}
        }
    }
    
    // Format file size (bytes to kB, MB)
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' kB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    // Format message time
    function formatMessageTime(date) {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
        
        if (messageDate.getTime() === today.getTime()) {
            return `${displayHours}:${displayMinutes} ${ampm}`;
        } else {
            const day = date.getDate();
            const month = date.toLocaleString('default', { month: 'short' });
            return `${day} ${month}, ${displayHours}:${displayMinutes} ${ampm}`;
        }
    }
    
    // Format time for Message info (e.g. "Today at 12:51")
    function formatMessageInfoTime(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
        if (messageDate.getTime() === today.getTime()) {
            return 'Today at ' + displayHours + ':' + displayMinutes + ' ' + ampm;
        } else {
            const day = date.getDate();
            const month = date.toLocaleString('default', { month: 'short' });
            return day + ' ' + month + ' at ' + displayHours + ':' + displayMinutes + ' ' + ampm;
        }
    }
    
    // Show Message info modal (WhatsApp-style)
    function showMessageInfo(msg) {
        const modal = document.getElementById('message-info-modal');
        const contentEl = document.getElementById('message-info-content');
        const container = document.getElementById('message-info-status-container');
        if (!modal || !contentEl || !container) return;
        
        contentEl.textContent = msg.message || '(No message content)';
        contentEl.style.whiteSpace = 'pre-wrap';
        
        const previewBubble = document.getElementById('message-info-preview');
        if (previewBubble) {
            previewBubble.classList.remove('sent', 'received');
            previewBubble.classList.add(msg.is_sent ? 'sent' : 'received');
        }
        
        const doubleCheckSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 11" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 5l2.5 2.5L7 2"/><path d="M6 5l2.5 2.5L14 1"/></svg>';
        
        let html = '';
        
        // Read - blue icon: sent messages when recipient read; received messages when I (staff) read
        if (msg.is_sent && msg.read_by_recipient) {
            const readTime = formatMessageInfoTime(msg.read_at) || formatMessageInfoTime(msg.sent_at);
            html += '<div class="message-info-status-section"><div class="message-info-status-row"><span class="message-info-status-icon">' + doubleCheckSvg + '</span><div><div class="message-info-status-label">Read</div><div class="message-info-status-time">' + readTime + '</div></div></div></div>';
        } else if (!msg.is_sent && msg.read_by_me) {
            const readTime = formatMessageInfoTime(msg.read_at) || formatMessageInfoTime(msg.sent_at);
            html += '<div class="message-info-status-section"><div class="message-info-status-row"><span class="message-info-status-icon">' + doubleCheckSvg + '</span><div><div class="message-info-status-label">Read</div><div class="message-info-status-time">' + readTime + '</div></div></div></div>';
        }
        
        // Delivered (always for sent messages; for received, show as "Received") - grey icon
        const deliveredLabel = msg.is_sent ? 'Delivered' : 'Received';
        const deliveredTime = formatMessageInfoTime(msg.sent_at);
        html += '<div class="message-info-status-section"><div class="message-info-status-row"><span class="message-info-status-icon message-info-status-icon--grey">' + doubleCheckSvg + '</span><div><div class="message-info-status-label">' + deliveredLabel + '</div><div class="message-info-status-time">' + deliveredTime + '</div></div></div></div>';
        
        container.innerHTML = html;
        modal.classList.add('active');
    }
    
    // Close Message info modal
    function closeMessageInfoModal() {
        document.getElementById('message-info-modal')?.classList.remove('active');
    }
    document.getElementById('message-info-close')?.addEventListener('click', closeMessageInfoModal);
    document.getElementById('message-info-modal')?.addEventListener('click', function(e) {
        if (e.target === this) closeMessageInfoModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('message-info-modal')?.classList.contains('active')) {
            closeMessageInfoModal();
        }
    });
    
    // Scroll to bottom
    function scrollToBottom() {
        const messagesContainer = document.getElementById('whatsapp-chat-messages');
        if (messagesContainer) {
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 100);
        }
    }
    
    // Emoji picker - common emojis
    const EMOJI_LIST = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😜','👍','👋','🙌','👏','❤️','😍','🔥','⭐','✨','💯','👍','👌','🙏','😊','🥳','🤗','😎','🤔','😅','😂','💪','🎉','✅','❌','⚠️','💡','📌'];
    
    const emojiPickerBtn = document.getElementById('emoji-picker-btn');
    const emojiPickerPopover = document.getElementById('emoji-picker-popover');
    const emojiPickerGrid = document.querySelector('.emoji-picker-grid');
    if (emojiPickerBtn && emojiPickerPopover && emojiPickerGrid) {
        emojiPickerGrid.innerHTML = EMOJI_LIST.map(e => '<span data-emoji="' + e + '">' + e + '</span>').join('');
        emojiPickerBtn.addEventListener('click', function() {
            const isOpen = emojiPickerPopover.style.display === 'block';
            emojiPickerPopover.style.display = isOpen ? 'none' : 'block';
        });
        emojiPickerGrid.addEventListener('click', function(e) {
            const span = e.target.closest('[data-emoji]');
            if (span && messageInput) {
                const emoji = span.getAttribute('data-emoji');
                const start = messageInput.selectionStart;
                const end = messageInput.selectionEnd;
                const text = messageInput.value;
                messageInput.value = text.slice(0, start) + emoji + text.slice(end);
                messageInput.selectionStart = messageInput.selectionEnd = start + emoji.length;
                messageInput.focus();
            }
        });
        document.addEventListener('click', function(e) {
            if (!emojiPickerPopover.contains(e.target) && !emojiPickerBtn.contains(e.target)) {
                emojiPickerPopover.style.display = 'none';
            }
        });
    }
    
    // Attachment button and file handling
    const attachFileBtn = document.getElementById('attach-file-btn');
    const messageAttachmentsInput = document.getElementById('message-attachments');
    let pendingFiles = [];
    
    if (attachFileBtn && messageAttachmentsInput) {
        attachFileBtn.addEventListener('click', function() { messageAttachmentsInput.click(); });
        messageAttachmentsInput.addEventListener('change', function() {
            const files = Array.from(this.files || []);
            pendingFiles = pendingFiles.concat(files);
            renderAttachmentPreview();
            this.value = '';
        });
    }
    
    function renderAttachmentPreview() {
        const thumbnailsEl = document.getElementById('attachment-thumbnails');
        const thumbnailsRow = document.getElementById('attachment-thumbnails-row');
        const docPreviewPanel = document.getElementById('document-preview-panel');
        const docPreviewFilename = document.getElementById('document-preview-filename');
        const docPreviewMeta = document.getElementById('document-preview-meta');
        const docPreviewContent = document.getElementById('document-preview-content');
        
        if (pendingFiles.length === 0) {
            if (thumbnailsRow) thumbnailsRow.style.display = 'none';
            if (docPreviewPanel) docPreviewPanel.style.display = 'none';
            return;
        }
        
        if (thumbnailsRow) thumbnailsRow.style.display = 'flex';
        if (thumbnailsEl) {
            thumbnailsEl.innerHTML = '';
            pendingFiles.forEach(function(file, idx) {
                const item = document.createElement('div');
                item.className = 'attachment-thumbnail-item' + (file.type.startsWith('image/') ? '' : ' doc') + (idx === 0 ? ' active' : '');
                item.addEventListener('click', function(e) {
                    if (e.target.closest('.thumb-remove')) return;
                    if (idx === 0) return;
                    const f = pendingFiles.splice(idx, 1)[0];
                    pendingFiles.unshift(f);
                    renderAttachmentPreview();
                });
                const check = document.createElement('span');
                check.className = 'thumb-check';
                check.innerHTML = '&#10003;';
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'thumb-remove';
                removeBtn.innerHTML = '×';
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    pendingFiles.splice(idx, 1);
                    renderAttachmentPreview();
                });
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    item.appendChild(img);
                } else {
                    item.innerHTML = crmI(file.type === 'application/pdf' ? 'fas fa-file-pdf' : 'fas fa-file-alt');
                }
                item.appendChild(check);
                item.appendChild(removeBtn);
                thumbnailsEl.appendChild(item);
            });
        }
        
        if (docPreviewPanel && docPreviewFilename && docPreviewMeta && docPreviewContent && pendingFiles.length > 0) {
            docPreviewPanel.style.display = 'flex';
            const file = pendingFiles[0];
            docPreviewFilename.textContent = file.name || 'Document';
            const isPdf = file.type === 'application/pdf';
            docPreviewMeta.textContent = isPdf ? '1 page' : (file.type.startsWith('image/') ? 'Image' : 'Document');
            docPreviewContent.innerHTML = '';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                docPreviewContent.appendChild(img);
            } else if (isPdf) {
                const obj = document.createElement('object');
                obj.data = URL.createObjectURL(file);
                obj.type = 'application/pdf';
                obj.style.width = '100%';
                obj.style.height = '380px';
                docPreviewContent.appendChild(obj);
            } else {
                const div = document.createElement('div');
                div.className = 'doc-placeholder';
                div.innerHTML = '@icon('fa-file-alt')<span>' + (file.name || 'Document') + '</span>';
                docPreviewContent.appendChild(div);
            }
        }
    }
    
    document.getElementById('document-preview-close')?.addEventListener('click', function() {
        pendingFiles = [];
        renderAttachmentPreview();
        messageAttachmentsInput.value = '';
    });
    
    document.getElementById('add-more-attach-btn')?.addEventListener('click', function() {
        messageAttachmentsInput?.click();
    });
    
    // Send message functionality
    const messageInput = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-message-btn');
    
    if (sendBtn) {
        sendBtn.addEventListener('click', sendMessage);
    }
    
    if (messageInput) {
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
    }
    
    async function sendMessage() {
        const input = document.getElementById('message-input');
        const messageText = (input?.value || '').trim();
        const effectiveMatterId = getEffectiveClientMatterId();
        const hasAttachments = typeof pendingFiles !== 'undefined' && pendingFiles && pendingFiles.length > 0;
        
        if (!effectiveMatterId) return;
        if (!messageText && !hasAttachments) return;
        
        if (sendBtn) sendBtn.disabled = true;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        try {
            let options = {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            if (hasAttachments) {
                const formData = new FormData();
                formData.append('message', messageText);
                formData.append('client_matter_id', effectiveMatterId);
                formData.append('source', 'client_portal');
                pendingFiles.forEach(function(f) { formData.append('attachments[]', f); });
                options.body = formData;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify({
                    message: messageText,
                    client_matter_id: effectiveMatterId,
                    source: 'client_portal'
                });
            }
            
            const response = await fetch('{{ route("clients.send-message") }}', options);
            const data = await response.json();
            
            if (response.ok && data.success) {
                input.value = '';
                input.style.height = 'auto';
                if (typeof pendingFiles !== 'undefined') pendingFiles = [];
                renderAttachmentPreview();
                
                // Immediately add the message to the UI (optimistic update)
                if (data.data && data.data.message) {
                    const messageData = data.data.message;
                    const formattedMessage = {
                        id: messageData.id || data.data.message_id,
                        message: messageData.message || messageText,
                        sender: messageData.sender || messageData.sender_name || 'You',
                        sender_id: messageData.sender_id || currentUserId,
                        sender_shortname: messageData.sender_initials || messageData.sender_shortname || 'AD',
                        sent_at: messageData.sent_at || messageData.created_at || new Date().toISOString(),
                        client_matter_id: messageData.client_matter_id || effectiveMatterId,
                        is_sent: true,
                        read_by_recipient: false,
                        attachments: messageData.attachments || []
                    };
                    addMessageToDisplay(formattedMessage, true, true);
                    scrollToBottom();
                    console.log('✅ Message added to UI immediately');
                }
            } else {
                alert('Failed to send message: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error sending message:', error);
            alert('Error sending message. Please try again.');
        } finally {
            if (sendBtn) sendBtn.disabled = false;
        }
    }

});
</script>

<!-- Create Checklist Modal -->
<div class="modal fade custom_modal" id="create_checklist" tabindex="-1" role="dialog" aria-labelledby="createChecklistModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createChecklistModalLabel">Add New Checklist</h5>
                <button type="button" class="close" id="create_checklist_close_btn" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="{{ URL::to('/add-checklists') }}" name="create_checklist_form" id="create_checklist_form" autocomplete="off">
                    @csrf
                    <input type="hidden" name="client_matter_id" id="checklist_client_matter_id" value="">
                    <input type="hidden" name="wf_stage" id="checklist_wf_stage" value="">
                    <div class="form-group">
                        <label for="cp_checklist_names">Select Checklist <span class="span_req">*</span></label>
                        <select name="cp_checklist_names[]" id="cp_checklist_names" class="form-control" multiple="multiple" style="width:100%;">
                        </select>
                        <small class="text-muted">You can select multiple checklists at once.</small>
                    </div>
                    <div class="form-group">
                        <label for="cp_checklist_description">Description <small class="text-muted">(optional)</small></label>
                        <textarea name="description" id="cp_checklist_description" class="form-control" rows="3" placeholder="Enter a description (optional)"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="cp_allow_client" name="allow_client" value="1" checked>
                            <label class="custom-control-label" for="cp_allow_client">Allow For Client</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" id="create_checklist_submit_btn" class="btn btn-primary">Add Checklist</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    // Tom Select only positions the dropdown when dropdownParent is the string "body" (see mm-tomselect-jquery.js).
    function destroyCpChecklistNamesSelect() {
        var $select = $('#cp_checklist_names');
        if ($select.length && ($select[0].tomselect || $select.data('mmSelect'))) {
            $select.off('.cpChecklistSelect');
            $select.mmSelect('destroy');
        }
    }

    function initCpChecklistNamesSelect() {
        var $select = $('#cp_checklist_names');
        if (!$select.length || typeof $.fn.mmSelect !== 'function') {
            return;
        }
        destroyCpChecklistNamesSelect();
        $select.mmSelect({
            dropdownParent: 'body',
            dropdownCssClass: 'mm-cp-create-checklist-dropdown',
            placeholder: 'Search and select checklists...',
            allowClear: true,
            multiple: true,
            create: true,
            minimumInputLength: 0,
            width: '100%',
            ajax: {
                url: '{{ URL::to('/crm/document-checklists-options') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                },
                cache: true
            }
        });
        // Reposition after modal layout settles; Bootstrap focus trap disabled via data-bs-focus="false".
        $select.on('mmselect:open.cpChecklistSelect', function () {
            var ts = this.tomselect;
            if (ts && typeof ts.positionDropdown === 'function') {
                setTimeout(function () { ts.positionDropdown(); }, 0);
            }
        });
    }

    // Open "Add New Checklist" modal when clicking any .openchecklist link
    $(document).on('click', '.openchecklist', function (e) {
        e.stopPropagation(); // prevent triggering checklist-row click
        var matterId = $(this).data('matter-id');
        var wfStage  = $(this).data('wf-stage');

        $('#checklist_client_matter_id').val(matterId);
        $('#checklist_wf_stage').val(wfStage);

        // Clear fields and any previous errors
        $('#cp_checklist_names').val(null).trigger('change');
        $('#cp_checklist_names').closest('.form-group').find('.custom-error').remove();
        $('#cp_checklist_description').val('');
        $('#cp_allow_client').prop('checked', true);

        $('#create_checklist').modal('show');
    });

    // Init only after modal is visible so Tom Select can measure the control and position the menu
    $('#create_checklist').on('shown.bs.modal', function () {
        initCpChecklistNamesSelect();
        var ts = document.getElementById('cp_checklist_names');
        if (ts && ts.tomselect) {
            ts.tomselect.focus();
        }
    });

    // Close modal cleanup
    $('#create_checklist').on('hidden.bs.modal', function () {
        destroyCpChecklistNamesSelect();
        $('#cp_checklist_names').val(null).trigger('change');
        $('#cp_checklist_names').closest('.form-group').find('.custom-error').remove();
        $('#cp_checklist_description').val('');
        $('#cp_allow_client').prop('checked', true);
        $('#create_checklist_submit_btn').prop('disabled', false).text('Add Checklist');
    });

    // Submit: Add New Checklist (supports multiple selections)
    $(document).on('click', '#create_checklist_submit_btn', function (e) {
        e.preventDefault();

        var selectedNames = $('#cp_checklist_names').val(); // array of selected name strings
        $('#cp_checklist_names').closest('.form-group').find('.custom-error').remove();

        if (!selectedNames || selectedNames.length === 0) {
            $('#cp_checklist_names').closest('.form-group')
                .append('<span class="custom-error" style="color:red;display:block;margin-top:4px;"><strong>Please select at least one checklist.</strong></span>');
            return;
        }

        var matterId = $('#checklist_client_matter_id').val();
        var wfStage  = $('#checklist_wf_stage').val();

        if (!matterId || !wfStage) {
            alert('Missing matter or stage information. Please try again.');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Adding...');

        $.ajax({
            url: '{{ URL::to('/add-checklists') }}',
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                client_matter_id: matterId,
                wf_stage: wfStage,
                'cp_checklist_names[]': selectedNames,
                description: $('#cp_checklist_description').val(),
                allow_client: $('#cp_allow_client').is(':checked') ? 1 : 0,
                source: 'client_portal'
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Add Checklist');

                if (response.success) {
                    $('#create_checklist').modal('hide');

                    var $stageItem = $('.stage-checklist-item[data-stage-name="' + wfStage + '"]');
                    var addedCount = 0;

                    if ($stageItem.length && response.data && response.data.length > 0) {
                        var $stageChecklists = $stageItem.find('.stage-checklists');
                        var newRows = '';

                        $.each(response.data, function (i, item) {
                            addedCount++;
                            newRows += '<tr class="checklist-row cursor-pointer cp-doc-checklist-row"'
                                + ' data-checklist-id="' + item.id + '"'
                                + ' data-checklist-name="' + $('<div>').text(item.cp_checklist_name).html() + '"'
                                + ' data-stage-name="' + $('<div>').text(wfStage).html() + '"'
                                + ' data-matter-id="' + matterId + '">'
                                + '<td class="checklist-status"><span class="round"></span></td>'
                                + '<td class="checklist-name">' + $('<div>').text(item.cp_checklist_name).html() + '</td>'
                                + '<td class="checklist-count"><div class="circular-box"><span>0</span></div></td>'
                                + '</tr>';
                        });

                        if ($stageChecklists.length) {
                            $stageChecklists.find('tbody').append(newRows);
                        } else {
                            var tableHtml = '<div class="stage-checklists"><table class="table checklist-table"><tbody>'
                                + newRows + '</tbody></table></div>';
                            $stageItem.find('.add-checklist-link').before(tableHtml);
                        }

                        // Update count badge
                        var currentCount = parseInt($stageItem.find('.stage-checklist-count').text().replace(/[()]/g, '')) || 0;
                        $stageItem.find('.stage-checklist-count').text('(' + (currentCount + addedCount) + ')');
                    }

                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Checklist(s) added successfully');
                    } else {
                        alert(response.message || 'Checklist(s) added successfully');
                    }
                } else {
                    alert(response.message || 'Failed to add checklist.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).text('Add Checklist');
                var msg = 'Failed to add checklist.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    msg = errors[Object.keys(errors)[0]][0];
                }
                alert(msg);
            }
        });
    });

});
</script>

<script>
    // Approve Audit Value Function - Must be globally accessible
    function approveAuditValue(auditId, fieldName, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this change? This will update the main record and remove the pending change.')) {
            return;
        }
        
        // Validate clientMatterId
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        
        $.ajax({
            url: '/api/client-portal-details/approve-audit',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                audit_id: auditId,
                field_name: fieldName,
                client_id: clientId,
                client_matter_id: clientMatterId
            },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Change approved successfully!');
                    } else {
                        alert(response.message || 'Change approved successfully!');
                    }
                    // Reload the page to show updated values
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve change.');
                    } else {
                        alert(response.message || 'Failed to approve change.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = 'Error approving change. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                if (typeof toastr !== 'undefined') {
                    toastr.error(errorMsg);
                } else {
                    alert(errorMsg);
                }
            }
        });
    }
    
    // Reject Audit Value Function - Must be globally accessible
    function rejectAuditValue(auditId, fieldName, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this change? A message will be sent to the client.')) {
            return;
        }
        
        $.ajax({
            url: '/api/client-portal-details/reject-audit',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                audit_id: auditId,
                field_name: fieldName,
                client_id: clientId,
                client_matter_id: clientMatterId
            },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Change rejected. Message sent to client.');
                    }
                    // Reload the page to show updated values
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject change.');
                    } else {
                        alert(response.message || 'Failed to reject change.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = 'Error rejecting change. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                if (typeof toastr !== 'undefined') {
                    toastr.error(errorMsg);
                } else {
                    alert(errorMsg);
                }
            }
        });
    }

    // Approve Visa Audit - save to client_visa_countries and remove from audit
    function approveVisaAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this visa? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-visa-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Visa approved and saved.');
                    } else {
                        alert(response.message || 'Visa approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve visa.');
                    } else {
                        alert(response.message || 'Failed to approve visa.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving visa. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Visa Audit - remove from audit table and notify client
    function rejectVisaAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this visa change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-visa-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Visa change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Visa change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject visa.');
                    } else {
                        alert(response.message || 'Failed to reject visa.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting visa. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Email Audit - save to client_emails and remove from audit
    function approveEmailAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this email? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-email-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Email approved and saved.');
                    } else {
                        alert(response.message || 'Email approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve email.');
                    } else {
                        alert(response.message || 'Failed to approve email.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving email. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Email Audit - remove from audit table and notify client
    function rejectEmailAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this email change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-email-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Email change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Email change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject email.');
                    } else {
                        alert(response.message || 'Failed to reject email.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting email. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Phone Audit - save to client_contacts and remove from audit
    function approvePhoneAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this phone number? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-phone-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Phone approved and saved.');
                    } else {
                        alert(response.message || 'Phone approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve phone.');
                    } else {
                        alert(response.message || 'Failed to approve phone.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving phone. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Phone Audit - remove from audit table and notify client
    function rejectPhoneAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this phone number change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-phone-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Phone change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Phone change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject phone.');
                    } else {
                        alert(response.message || 'Failed to reject phone.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting phone. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Passport Audit - save to client_passport_informations and remove from audit
    function approvePassportAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this passport? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-passport-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Passport approved and saved.');
                    } else {
                        alert(response.message || 'Passport approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve passport.');
                    } else {
                        alert(response.message || 'Failed to approve passport.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving passport. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Passport Audit - remove from audit table and notify client
    function rejectPassportAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this passport change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-passport-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Passport change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Passport change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject passport.');
                    } else {
                        alert(response.message || 'Failed to reject passport.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting passport. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Qualification Audit - save to client_qualifications and remove from audit
    function approveQualificationAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this qualification? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-qualification-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Qualification approved and saved.');
                    } else {
                        alert(response.message || 'Qualification approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve qualification.');
                    } else {
                        alert(response.message || 'Failed to approve qualification.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving qualification. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Qualification Audit - remove from audit table and notify client
    function rejectQualificationAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this qualification change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-qualification-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Qualification change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Qualification change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject qualification.');
                    } else {
                        alert(response.message || 'Failed to reject qualification.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting qualification. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Experience Audit - save to client_experiences and remove from audit
    function approveExperienceAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this work experience? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-experience-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Experience approved and saved.');
                    } else {
                        alert(response.message || 'Experience approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve experience.');
                    } else {
                        alert(response.message || 'Failed to approve experience.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving experience. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Experience Audit - remove from audit table and notify client
    function rejectExperienceAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this work experience change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-experience-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Experience change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Experience change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject experience.');
                    } else {
                        alert(response.message || 'Failed to reject experience.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting experience. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Occupation Audit - save to client_occupations and remove from audit
    function approveOccupationAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this occupation? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-occupation-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Occupation approved and saved.');
                    } else {
                        alert(response.message || 'Occupation approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve occupation.');
                    } else {
                        alert(response.message || 'Failed to approve occupation.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving occupation. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Occupation Audit - remove from audit table and notify client
    function rejectOccupationAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this occupation change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-occupation-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Occupation change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Occupation change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject occupation.');
                    } else {
                        alert(response.message || 'Failed to reject occupation.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting occupation. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Test Score Audit - save to client_testscore and remove from audit
    function approveTestScoreAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this test score? It will be saved to the main record and the pending change will be removed.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/approve-test-score-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Test score approved and saved.');
                    } else {
                        alert(response.message || 'Test score approved and saved.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to approve test score.');
                    } else {
                        alert(response.message || 'Failed to approve test score.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving test score. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Reject Test Score Audit - remove from audit table and notify client
    function rejectTestScoreAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this test score change? A message will be sent to the client.')) {
            return;
        }
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) {
            alert('Error: Client matter ID is required. Please select a matter first.');
            return;
        }
        $.ajax({
            url: '/api/client-portal-details/reject-test-score-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Test score change rejected. Message sent to client.');
                    } else {
                        alert(response.message || 'Test score change rejected.');
                    }
                    setTimeout(function() { location.reload(); }, 1000);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Failed to reject test score.');
                    } else {
                        alert(response.message || 'Failed to reject test score.');
                    }
                }
            },
            error: function(xhr) {
                var errorMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting test score. Please try again.';
                if (typeof toastr !== 'undefined') { toastr.error(errorMsg); } else { alert(errorMsg); }
            }
        });
    }

    // Approve Address Audit
    function approveAddressAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this address? It will be saved to the main record and the pending change will be removed.')) return;
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) { alert('Error: Client matter ID is required. Please select a matter first.'); return; }
        $.ajax({
            url: '/api/client-portal-details/approve-address-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(r) {
                if (r.success) { (typeof toastr !== 'undefined' ? toastr.success(r.message) : alert(r.message)); setTimeout(function() { location.reload(); }, 1000); }
                else { (typeof toastr !== 'undefined' ? toastr.error(r.message) : alert(r.message)); }
            },
            error: function(xhr) { var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving address.'; (typeof toastr !== 'undefined' ? toastr.error(msg) : alert(msg)); }
        });
    }
    // Reject Address Audit
    function rejectAddressAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this address change? A message will be sent to the client.')) return;
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) { alert('Error: Client matter ID is required. Please select a matter first.'); return; }
        $.ajax({
            url: '/api/client-portal-details/reject-address-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(r) {
                if (r.success) { (typeof toastr !== 'undefined' ? toastr.success(r.message) : alert(r.message)); setTimeout(function() { location.reload(); }, 1000); }
                else { (typeof toastr !== 'undefined' ? toastr.error(r.message) : alert(r.message)); }
            },
            error: function(xhr) { var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting address.'; (typeof toastr !== 'undefined' ? toastr.error(msg) : alert(msg)); }
        });
    }
    // Approve Travel Audit
    function approveTravelAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to approve this travel? It will be saved to the main record and the pending change will be removed.')) return;
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) { alert('Error: Client matter ID is required. Please select a matter first.'); return; }
        $.ajax({
            url: '/api/client-portal-details/approve-travel-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(r) {
                if (r.success) { (typeof toastr !== 'undefined' ? toastr.success(r.message) : alert(r.message)); setTimeout(function() { location.reload(); }, 1000); }
                else { (typeof toastr !== 'undefined' ? toastr.error(r.message) : alert(r.message)); }
            },
            error: function(xhr) { var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error approving travel.'; (typeof toastr !== 'undefined' ? toastr.error(msg) : alert(msg)); }
        });
    }
    // Reject Travel Audit
    function rejectTravelAudit(metaOrder, clientId, clientMatterId) {
        if (!confirm('Are you sure you want to reject this travel change? A message will be sent to the client.')) return;
        if (!clientMatterId || clientMatterId === 'null' || clientMatterId === null) { alert('Error: Client matter ID is required. Please select a matter first.'); return; }
        $.ajax({
            url: '/api/client-portal-details/reject-travel-audit',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: { client_id: clientId, client_matter_id: clientMatterId, meta_order: metaOrder },
            success: function(r) {
                if (r.success) { (typeof toastr !== 'undefined' ? toastr.success(r.message) : alert(r.message)); setTimeout(function() { location.reload(); }, 1000); }
                else { (typeof toastr !== 'undefined' ? toastr.error(r.message) : alert(r.message)); }
            },
            error: function(xhr) { var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error rejecting travel.'; (typeof toastr !== 'undefined' ? toastr.error(msg) : alert(msg)); }
        });
    }
</script>

<script>
// Documents Tab: Load documents when a checklist row is clicked
$(document).on('click', '.cp-doc-checklist-row', function () {
    var checklistId   = $(this).data('checklist-id');
    var checklistName = $(this).data('checklist-name');
    var matterId      = $(this).data('matter-id');

    // Highlight selected row
    $('.cp-doc-checklist-row').removeClass('table-active');
    $(this).addClass('table-active');

    $('#cp-checklist-placeholder').hide();
    $('#cp-checklist-documents-content').show();
    $('#cp-checklist-selected-name').text(checklistName);
    $('#cp-checklist-documents-tbody').html('<tr><td colspan="4" class="text-center">@icon('fa-spinner', ['spin' => true]) Loading...</td></tr>');

    $.ajax({
        url: '/api/client-portal/checklist-documents',
        method: 'GET',
        data: { checklist_id: checklistId, client_matter_id: matterId },
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: function (response) {
            var docs = response.documents || [];
            var html = '';
            if (docs.length === 0) {
                html = '<tr><td colspan="5" class="text-center text-muted">No documents uploaded yet.</td></tr>';
            } else {
                $.each(docs, function (i, doc) {
                    var statusBadge = '';
                    if (doc.cp_doc_status == 1) {
                        statusBadge = '<span class="badge badge-success">Approved</span>';
                    } else if (doc.cp_doc_status == 2) {
                        var reason = doc.cp_rejection_reason ? doc.cp_rejection_reason : 'No reason provided';
                        statusBadge = '<span class="badge badge-danger" title="' + $('<div>').text(reason).html() + '" style="cursor:help;">Rejected</span>';
                    } else {
                        statusBadge = '<span class="badge badge-warning">In Progress</span>';
                    }

                    // Row 2 buttons — no spacers; show only what's relevant for each status
                    var approveBtn = (doc.cp_doc_status != 1)   // hide when already Approved
                        ? '<a href="javascript:void(0);" class="btn btn-sm btn-success cp-approve-doc-btn" data-document-id="' + doc.id + '" title="Approve">@icon('fa-check-circle')</a>'
                        : '';

                    var rejectBtn = (doc.cp_doc_status != 2)    // hide when already Rejected
                        ? '<a href="javascript:void(0);" class="btn btn-sm btn-warning cp-reject-doc-btn" data-document-id="' + doc.id + '" title="Reject">@icon('fa-times-circle')</a>'
                        : '';

                    var fileUrl     = doc.myfile || '';
                    var fileNameDisplay = doc.file_name || 'N/A';
                    var fileNameCell = fileUrl
                        ? '<a href="' + fileUrl + '" target="_blank" title="Click to preview" style="color:inherit;text-decoration:underline;cursor:pointer;">' + fileNameDisplay + '</a>'
                        : fileNameDisplay;

                    var downloadBtn = '<a href="javascript:void(0);" class="btn btn-sm btn-primary cp-download-doc-btn" data-document-id="' + doc.id + '" data-file-name="' + (doc.file_name || 'document') + '" title="Download">@icon('fa-download')</a>';
                    var deleteBtn   = '<a href="javascript:void(0);" class="btn btn-sm btn-danger cp-delete-doc-btn" data-document-id="' + doc.id + '" data-list-id="' + checklistId + '" title="Delete">@icon('fa-trash')</a>';
                    var moveBtn     = (doc.cp_doc_status == 1)
                        ? '<a href="javascript:void(0);" class="btn btn-sm btn-info cp-move-doc-btn" data-document-id="' + doc.id + '" data-matter-id="' + (matterId || '') + '" data-list-id="' + checklistId + '" title="Move Document">@icon('fa-arrows-alt')</a>'
                        : '';

                    html += '<tr data-matter-id="' + (matterId || '') + '">'
                        + '<td>' + fileNameCell + '</td>'
                        + '<td>' + (doc.created_at ? (typeof formatDisplayDateTime === 'function' ? formatDisplayDateTime(doc.created_at) : String(doc.created_at)) : '') + '</td>'
                        + '<td>' + statusBadge + '</td>'
                        + '<td><div class="action-buttons"><div class="action-row">' + downloadBtn + deleteBtn + '</div><div class="action-row action-row-move">' + approveBtn + rejectBtn + moveBtn + '</div></div></td>'
                        + '</tr>';
                });
            }
            $('#cp-checklist-documents-tbody').html(html);
        },
        error: function () {
            $('#cp-checklist-documents-tbody').html('<tr><td colspan="4" class="text-center text-danger">Failed to load documents.</td></tr>');
        }
    });
});

// Download document
// Uses the server-side endpoint so the browser receives Content-Disposition: attachment,
// which forces a real file download regardless of file type or origin (S3 URLs are cross-origin
// so the HTML5 `download` attribute is ignored by browsers — server proxy is required).
$(document).on('click', '.cp-download-doc-btn', function () {
    var documentId = $(this).data('document-id');
    if (documentId) {
        window.location.href = '/client-portal/download-document?document_id=' + documentId;
    }
});

// Delete document
$(document).on('click', '.cp-delete-doc-btn', function () {
    if (!confirm('Are you sure you want to delete this document?')) return;
    var documentId = $(this).data('document-id');
    var listId     = $(this).data('list-id');
    var $row = $(this).closest('tr');
    $.ajax({
        url: '/api/client-portal/delete-document',
        method: 'POST',
        data: { document_id: documentId, source: 'client_portal', _token: $('meta[name="csrf-token"]').attr('content') },
        success: function (response) {
            if (response.success) {
                $row.remove();
                if ($('#cp-checklist-documents-tbody tr').length === 0) {
                    $('#cp-checklist-documents-tbody').html('<tr><td colspan="4" class="text-center text-muted">No documents uploaded yet.</td></tr>');
                }
                // Decrement the counter badge on the checklist row in the left panel
                var $checklistRow = $('.cp-doc-checklist-row[data-checklist-id="' + listId + '"]');
                if ($checklistRow.length) {
                    var $countSpan = $checklistRow.find('.checklist-count .circular-box span');
                    var current = parseInt($countSpan.text(), 10) || 0;
                    var newCount = Math.max(0, current - 1);
                    $countSpan.text(newCount);
                    // Update tick/circle icon based on new count
                    if (newCount === 0) {
                        $checklistRow.find('.checklist-status').html('<span class="round"></span>');
                    }
                }
            } else {
                alert(response.message || 'Failed to delete document.');
            }
        },
        error: function () { alert('Failed to delete document.'); }
    });
});

// Approve document
$(document).on('click', '.cp-approve-doc-btn', function () {
    if (!confirm('Are you sure you want to approve this document?')) return;
    var documentId = $(this).data('document-id');
    var $btn = $(this);
    // Capture DOM references BEFORE .html() detaches $btn from the DOM
    var $actionRow  = $btn.closest('.action-row');
    var $statusCell = $btn.closest('tr').find('td:nth-child(3)');
    var matterId    = $btn.closest('tr').data('matter-id') || '';
    $.ajax({
        url: '/api/client-portal/update-document-status',
        method: 'POST',
        data: { document_id: documentId, status: 1, source: 'client_portal', _token: $('meta[name="csrf-token"]').attr('content') },
        success: function (response) {
            if (response.success) {
                $statusCell.html('<span class="badge badge-success">Approved</span>');
                // Row 2: Approved → [Reject][Move]
                var listId = $actionRow.closest('td').find('.cp-delete-doc-btn').data('list-id') || '';
                $actionRow.html(
                    '<a href="javascript:void(0);" class="btn btn-sm btn-warning cp-reject-doc-btn" data-document-id="' + documentId + '" title="Reject">@icon('fa-times-circle')</a>' +
                    '<a href="javascript:void(0);" class="btn btn-sm btn-info cp-move-doc-btn" data-document-id="' + documentId + '" data-matter-id="' + matterId + '" data-list-id="' + listId + '" title="Move Document">@icon('fa-arrows-alt')</a>'
                );
                alert('Document has been approved successfully.');
            } else {
                alert(response.message || 'Failed to approve document.');
            }
        },
        error: function () { alert('Failed to approve document.'); }
    });
});

// Reject document
$(document).on('click', '.cp-reject-doc-btn', function () {
    var reason = prompt('Enter rejection reason (required):');
    if (reason === null) return; // cancelled
    reason = reason.trim();
    if (!reason) {
        alert('Rejection reason is required. Please enter a reason before rejecting.');
        return;
    }
    var documentId = $(this).data('document-id');
    var $btn = $(this);
    $.ajax({
        url: '/api/client-portal/update-document-status',
        method: 'POST',
        data: { document_id: documentId, status: 2, rejection_reason: reason, source: 'client_portal', _token: $('meta[name="csrf-token"]').attr('content') },
        success: function (response) {
            if (response.success) {
                $btn.closest('tr').find('td:nth-child(3)').html('<span class="badge badge-danger" title="' + $('<div>').text(reason || 'No reason provided').html() + '" style="cursor:help;">Rejected</span>');
                // Row 2: Rejected → [Approve] only (Move only shows when Approved)
                $btn.closest('.action-row').html(
                    '<a href="javascript:void(0);" class="btn btn-sm btn-success cp-approve-doc-btn" data-document-id="' + documentId + '" title="Approve">@icon('fa-check-circle')</a>'
                );
            } else {
                alert(response.message || 'Failed to reject document.');
            }
        },
        error: function () { alert('Failed to reject document.'); }
    });
});
</script>

{{-- ── Move Document Modal ─────────────────────────────────────────────── --}}
<div class="modal fade" id="cpWorkflowMoveDocModal" tabindex="-1" role="dialog" aria-labelledby="cpWorkflowMoveDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cpWorkflowMoveDocModalLabel">@icon('fa-arrows-alt', ['class' => 'mr-1']) Move Document</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="moveDocumentId">
                <input type="hidden" id="moveDocumentMatterId">
                <input type="hidden" id="moveDocumentListId">
                <div class="form-group">
                    <label for="moveDestination"><strong>Move to:</strong></label>
                    <select class="form-control" id="moveDestination">
                        <option value="">-- Select Destination --</option>
                        <option value="personal">Personal Documents</option>
                        <option value="visa">Visa Documents</option>
                    </select>
                </div>
                <div class="form-group" id="moveCategoryGroup" style="display:none;">
                    <label id="moveCategoryLabel"><strong>Select Category:</strong></label>
                    <select class="form-control" id="moveCategory">
                        <option value="">-- Select Category --</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="moveDocumentSubmitBtn" style="background:#6f42c1;border-color:#6f42c1;">Move Document</button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Move Document ─────────────────────────────────────────────────────────

// Open modal
$(document).on('click', '.cp-move-doc-btn', function () {
    var documentId = $(this).data('document-id');
    var matterId   = $(this).data('matter-id') || $(this).closest('tr').data('matter-id') || '';
    var listId     = $(this).data('list-id') || '';
    $('#moveDocumentId').val(documentId);
    $('#moveDocumentMatterId').val(matterId);
    $('#moveDocumentListId').val(listId);
    $('#moveDestination').val('');
    $('#moveCategoryGroup').hide();
    $('#moveCategory').html('<option value="">-- Select Category --</option>');
    $('#cpWorkflowMoveDocModal').modal('show');
});

// Load categories when destination changes
$('#moveDestination').on('change', function () {
    var type     = $(this).val();
    var matterId = $('#moveDocumentMatterId').val();
    var clientId = $('#client-portal-toggle-tab').data('client-id') || '';

    $('#moveCategory').html('<option value="">-- Select Category --</option>');
    $('#moveCategoryGroup').hide();

    if (!type) return;

    var params = { type: type, client_id: clientId, matter_id: matterId };

    $('#moveCategoryLabel').text(type === 'personal' ? 'Select Personal Category:' : 'Select Visa Category:');
    $('#moveCategory').html('<option value="">-- Loading... --</option>');
    $('#moveCategoryGroup').show();

    $.get('/client-portal/document-categories-for-move', params, function (response) {
        var options = '<option value="">-- Select Category --</option>';
        if (response.success && response.categories && response.categories.length) {
            $.each(response.categories, function (i, cat) {
                options += '<option value="' + cat.id + '">' + cat.title + '</option>';
            });
        }
        $('#moveCategory').html(options);
    }).fail(function () {
        $('#moveCategory').html('<option value="">-- Failed to load categories --</option>');
    });
});

// Submit move
$('#moveDocumentSubmitBtn').on('click', function () {
    var documentId = $('#moveDocumentId').val();
    var targetType = $('#moveDestination').val();
    var targetId   = $('#moveCategory').val();
    var listId     = $('#moveDocumentListId').val();

    if (!targetType) { alert('Please select a destination.'); return; }
    if (!targetId)   { alert('Please select a category.');    return; }

    var $btn = $(this).prop('disabled', true).text('Moving...');

    $.ajax({
        url: '/documents/move',
        method: 'POST',
        data: {
            document_id: documentId,
            target_type: targetType,
            target_id: targetId,
            target_matter_id: targetType === 'visa' ? ($('#moveDocumentMatterId').val() || '') : '',
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $btn.prop('disabled', false).text('Move Document');
            if (response.status) {
                $('#cpWorkflowMoveDocModal').modal('hide');
                // Remove the row — document now lives in personal/visa docs
                $('.cp-move-doc-btn[data-document-id="' + documentId + '"]').closest('tr').fadeOut(400, function () {
                    $(this).remove();
                    if ($('#cp-checklist-documents-tbody tr:visible').length === 0) {
                        $('#cp-checklist-documents-tbody').html('<tr><td colspan="4" class="text-center text-muted">No documents uploaded yet.</td></tr>');
                    }
                });
                // Decrement the checklist counter badge in the left panel
                if (listId) {
                    var $checklistRow = $('.cp-doc-checklist-row[data-checklist-id="' + listId + '"]');
                    if ($checklistRow.length) {
                        var $countSpan = $checklistRow.find('.checklist-count .circular-box span');
                        var newCount = Math.max(0, (parseInt($countSpan.text(), 10) || 0) - 1);
                        $countSpan.text(newCount);
                        if (newCount === 0) {
                            $checklistRow.find('.checklist-status').html('<span class="round"></span>');
                        }
                    }
                }
                alert(response.message || 'Document moved successfully.');
            } else {
                alert(response.message || 'Failed to move document.');
            }
        },
        error: function () {
            $btn.prop('disabled', false).text('Move Document');
            alert('Failed to move document. Please try again.');
        }
    });
});
</script>
