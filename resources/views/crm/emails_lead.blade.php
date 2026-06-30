<!-- Emails Interface for Leads (no matter context) -->
@php
    $leadData = $fetchedData ?? null;
    $canDeleteEmail = Auth::user() && in_array(
        (int) Auth::user()->role,
        config('crm.email_log_delete_role_ids', [1, 12, 16]) ?: [1, 12, 16],
        true
    );
@endphp
<div class="email-interface-container" data-context="lead" data-client-id="{{ $leadData->id ?? '' }}" data-matter-id="" data-can-delete-email="{{ $canDeleteEmail ? '1' : '0' }}">
    <!-- Top Control Bar (Search & Filters) -->
    <div class="email-control-bar">
        <div class="control-section search-section">
            <label for="emailSearchInput">Search:</label>
            <input type="text" id="emailSearchInput" class="search-input" placeholder="Search emails...">
        </div>
        
        <div class="control-section filter-section">
            <label for="labelFilter">Label:</label>
            <select id="labelFilter" class="filter-select">
                <option value="">All Labels</option>
                <!-- Populated dynamically -->
            </select>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="email-main-content">
        <!-- Left Email List Pane (no upload for leads - emails are from CRM send only) -->
        <div class="email-list-pane">
            <div class="upload-section-header lead-email-notice">
                <span class="upload-title">@icon('fa-info-circle') Emails sent to this lead from the CRM</span>
            </div>
            <div class="upload-section-container" style="display: none;">
                <!-- Upload hidden for leads - no .msg upload support yet -->
            </div>
            
            <!-- Email List Header -->
            <div class="email-list-header">
                <span class="results-count" id="resultsCount">0 results</span>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn">Prev</button>
                    <span class="page-info" id="pageInfo">1/1</span>
                    <button class="pagination-btn" id="nextBtn">Next</button>
                </div>
            </div>
            
            <div class="email-list" id="emailList">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        @icon('fa-inbox')
                    </div>
                    <div class="empty-state-text">
                        <h3>No emails found</h3>
                        <p>Emails sent to this lead from the CRM will appear here.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Content Viewing Pane -->
        <div class="email-content-pane">
            <div class="email-content-placeholder" id="emailContentPlaceholder">
                <div class="placeholder-content">
                    @icon('fa-envelope-open')
                    <h3>Select an email to view its contents</h3>
                </div>
            </div>
            
            <div class="email-content-view" id="emailContentView" style="display: none;">
                <!-- Email content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Attachment Preview Modal -->
<div id="attachmentPreviewModal" class="preview-modal" style="display: none;">
    <div class="preview-modal-overlay" id="previewOverlay"></div>
    <div class="preview-modal-content">
        <div class="preview-modal-header">
            <h3 id="previewFileName">Preview</h3>
            <button class="preview-close" id="closePreviewBtn">&times;</button>
        </div>
        <div class="preview-modal-body">
            <iframe id="previewFrame" src=""></iframe>
        </div>
    </div>
</div>

<!-- Email Context Menu -->
<div id="emailContextMenu" class="email-context-menu" style="display: none;">
    <div class="context-menu-item" data-action="apply-label">
        @icon('fa-tag')
        <span>Apply Label</span>
        @icon('fa-chevron-right', ['class' => 'context-menu-arrow'])
    </div>
    <div class="context-menu-item" data-action="reply">
        @icon('fa-reply')
        <span>Reply</span>
    </div>
    <div class="context-menu-item" data-action="forward">
        @icon('fa-share')
        <span>Forward</span>
    </div>
    @if($canDeleteEmail)
    <div class="context-menu-separator"></div>
    <div class="context-menu-item" data-action="delete">
        @icon('fa-trash')
        <span>Delete</span>
    </div>
    @endif
</div>

<!-- Label Submenu -->
<div id="labelSubmenu" class="email-context-submenu" style="display: none;">
    <div class="submenu-header">
        @icon('fa-arrow-left', ['class' => 'submenu-back'])
        <span>Select Label</span>
    </div>
    <div class="submenu-content" id="labelSubmenuContent">
        <!-- Labels will be populated dynamically -->
    </div>
</div>

<!-- Context Menu Overlay -->
<div id="contextMenuOverlay" class="context-menu-overlay" style="display: none;"></div>

<link rel="stylesheet" href="{{ asset('css/emails.css') }}">
<style>
.lead-email-notice { background: #f0f9ff; padding: 10px 15px; border-radius: 4px; font-size: 13px; color: #0369a1; }
</style>
<script src="{{ asset('js/emails.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initializeSearch === 'function') {
        window.initializeSearch();
    }
    if (typeof window.loadEmails === 'function') {
        setTimeout(function() { window.loadEmails(); }, 50);
    }
});
</script>
