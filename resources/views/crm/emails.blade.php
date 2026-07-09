<!-- Emails Interface (Outlook-style layout — client detail tab only) -->
@php
    $clientData = $fetchedData ?? $client ?? null;
    $clientRecordId = $clientData ? ($clientData->id ?? null) : null;

    $matterId = null;
    if ($clientRecordId) {
        if (isset($id1) && $id1 != "") {
            $clientMatter = \App\Models\ClientMatter::where('client_id', $clientRecordId)
                ->where('client_unique_matter_no', $id1)
                ->first();
            $matterId = $clientMatter ? $clientMatter->id : null;
        } else {
            $clientMatter = \App\Models\ClientMatter::where('client_id', $clientRecordId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
            $matterId = $clientMatter ? $clientMatter->id : null;
        }

        if (! $matterId && ! empty($latestClientMatterId)) {
            $matterId = $latestClientMatterId;
        }
    }
@endphp
@php
    $canDeleteEmail = Auth::user() && in_array(
        (int) Auth::user()->role,
        config('crm.email_log_delete_role_ids', [1, 12, 16]) ?: [1, 12, 16],
        true
    );
    $canSendEmailBodiesToS3 = Auth::user() && (int) Auth::user()->role === 1;

    $emailUploadExtensions = config('crm.email_upload_allowed_extensions', ['msg', 'eml']);
    $emailUploadAccept = collect($emailUploadExtensions)->map(fn ($e) => '.' . ltrim($e, '.'))->implode(',');
    $emailUploadLabel = collect($emailUploadExtensions)->map(fn ($e) => '.' . ltrim($e, '.'))->implode(', ');
@endphp
<div class="email-interface-container outlook-layout" data-client-id="{{ $clientRecordId ?? '' }}" data-matter-id="{{ $matterId ?? '' }}" data-can-delete-email="{{ $canDeleteEmail ? '1' : '0' }}" data-can-send-email-bodies-to-s3="{{ $canSendEmailBodiesToS3 ? '1' : '0' }}">

    {{-- Hidden select kept for JS mail-type persistence and API filtering --}}
    <select id="mailTypeFilter" class="filter-select" style="display:none;" aria-hidden="true">
        <option value="inbox" selected>Inbox</option>
        <option value="sent">Sent</option>
    </select>

    <div class="outlook-main-content">
        <div class="outlook-list-pane">
            <div class="list-toolbar">
                <div class="folder-tabs" role="tablist" aria-label="Mail folders">
                    <button type="button" class="folder-tab-btn folder-item active" data-folder="inbox" id="folder-tab-inbox" aria-selected="true">
                        @icon('inbox') Inbox
                    </button>
                    <button type="button" class="folder-tab-btn folder-item" data-folder="sent" id="folder-tab-sent" aria-selected="false">
                        @icon('fa-paper-plane') Sent
                    </button>
                </div>
                @if($canSendEmailBodiesToS3)
                <button type="button" id="sendEmailBodiesToS3Btn" class="archive-bodies-btn toolbar-s3-btn" title="Send all email bodies to S3 and remove them from the database">
                    Send All Email Body To S3 From Db
                </button>
                @endif
            </div>

            <div id="upload-area" class="inline-drop-zone drag-drop-zone" role="button" tabindex="0" aria-label="Upload email files">
                @icon('fa-cloud-upload-alt', ['class' => 'inline-drop-zone-icon'])
                <span>Drag &amp; drop Outlook email files ({{ $emailUploadLabel }}) here or <b>browse</b> to upload</span>
                <span id="file-count" class="file-count-badge">0</span>
                <input type="file" id="emailFileInput" class="file-input" accept="{{ $emailUploadAccept }}" multiple style="display: none;">
            </div>
            <div id="upload-progress" class="upload-progress">
                <span id="fileStatus">Ready to upload</span>
            </div>

            <div class="list-header">
                <div class="list-header-row">
                    <div class="search-box">
                        @icon('search', ['class' => 'search-box-icon'])
                        <input type="text" id="emailSearchInput" placeholder="Search emails..." aria-label="Search emails">
                    </div>
                </div>
                <div class="list-header-filters">
                    <select id="labelFilter" class="list-filter-select" aria-label="Filter by label">
                        <option value="">All Labels</option>
                    </select>
                </div>
            </div>

            <div class="email-list" id="emailList">
                <div class="empty-state empty-state--list">
                    <div class="empty-state-icon">
                        @icon('fa-inbox')
                    </div>
                    <div class="empty-state-text">
                        <h3>No emails found</h3>
                        <p>Upload {{ $emailUploadLabel }} files above to get started.</p>
                    </div>
                </div>
            </div>

            <div class="pagination-bar">
                <span id="pageInfo">1/1</span>
                <span id="resultsCount" class="visually-hidden">0 results</span>
                <div class="pagination-controls">
                    <button type="button" class="pagination-btn" id="prevBtn" aria-label="Previous page">@icon('chevron-left')</button>
                    <button type="button" class="pagination-btn" id="nextBtn" aria-label="Next page">@icon('chevron-right')</button>
                </div>
            </div>
        </div>

        <div class="outlook-content-pane outlook-reading-pane">
            <div class="empty-state" id="emailContentPlaceholder">
                @icon('fa-envelope-open', ['class' => 'empty-state-envelope-icon'])
                <p>Select an item to read</p>
            </div>

            <div class="reading-pane-content" id="emailContentView">
                <div class="reading-header">
                    <div class="action-bar">
                        <button type="button" class="action-btn" id="btnReply">@icon('reply') Reply</button>
                        <button type="button" class="action-btn" id="btnReplyAll">@icon('reply') Reply All</button>
                        <button type="button" class="action-btn" id="btnForward">@icon('share') Forward</button>
                        @if($canDeleteEmail)
                        <button type="button" class="action-btn action-btn--danger" id="btnDeleteEmail">@icon('trash') Delete</button>
                        @endif
                    </div>
                    <h2 class="email-full-subject" id="readSubject"></h2>
                    <div class="email-meta">
                        <div class="sender-avatar" id="readAvatar" aria-hidden="true"></div>
                        <div class="meta-details">
                            <div class="meta-sender" id="readSender"></div>
                            <div class="meta-recipients" id="readTo"></div>
                            <div class="meta-recipients meta-cc" id="readCc" hidden></div>
                        </div>
                        <div class="meta-date" id="readDate"></div>
                    </div>
                </div>
                <div id="attachmentsContainer" class="email-attachments-container reading-attachments" hidden></div>
                <div class="reading-body">
                    <iframe id="emailReadBody" class="email-read-body-iframe" title="Email content"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email upload loading overlay -->
<div class="email-upload-loading-overlay" id="emailUploadLoadingOverlay" aria-hidden="true" aria-live="polite" aria-busy="false">
    <div class="email-upload-loading-card" role="status">
        <div class="email-upload-loading-icon" aria-hidden="true">
            @icon('envelope')
            <span class="email-upload-loading-spinner"></span>
        </div>
        <h3 class="email-upload-loading-title" id="emailUploadLoadingTitle">Uploading email</h3>
        <p class="email-upload-loading-message" id="emailUploadLoadingMessage">Please wait while your email is being processed…</p>
        <p class="email-upload-loading-filename" id="emailUploadLoadingFilename"></p>
        <div class="email-upload-loading-progress" aria-hidden="true">
            <div class="email-upload-loading-progress-bar" id="emailUploadLoadingProgressBar"></div>
        </div>
        <p class="email-upload-loading-hint">Do not close or refresh this page</p>
    </div>
</div>

<!-- Attachment storage modal (pre-upload) -->
<div class="attachment-storage-modal-overlay" id="attachmentStorageModal" aria-hidden="true">
    <div class="attachment-storage-modal" role="dialog" aria-labelledby="attachmentStorageModalTitle" aria-modal="true">
        <div class="attachment-storage-modal__header">
            <h3 id="attachmentStorageModalTitle">Save Attachments</h3>
            <p class="attachment-storage-modal__subtitle">Rename files before saving. Optionally copy to the Documents tab.</p>
            <span class="attachment-storage-modal__count" id="attachmentStorageCount" aria-live="polite"></span>
        </div>
        <div class="attachment-storage-destination" id="attachmentStorageDestination">
            <label class="attachment-storage-checkbox">
                <input type="checkbox" id="attachmentSaveToDocuments">
                Also save copies to Documents tab
            </label>
            <select id="attachmentDocumentCategory" class="attachment-storage-select" aria-label="Document category" disabled>
                <option value="">Select category…</option>
            </select>
        </div>
        <div class="attachment-storage-per-email" id="attachmentStoragePerEmail" hidden></div>
        <div class="attachment-storage-table-wrap">
            <table class="attachment-storage-table">
                <thead>
                    <tr>
                        <th scope="col">File</th>
                        <th scope="col">Size</th>
                        <th scope="col">Save as</th>
                        <th scope="col" class="attachment-storage-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody id="attachmentStorageModalBody"></tbody>
            </table>
        </div>
        <div class="attachment-storage-modal__actions">
            <button type="button" class="attachment-storage-modal__btn attachment-storage-modal__btn--cancel" id="attachmentStorageCancel">Cancel upload</button>
            <button type="button" class="attachment-storage-modal__btn attachment-storage-modal__btn--confirm" id="attachmentStorageConfirm">Continue upload</button>
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

<!-- Duplicate email upload modal -->
<div class="duplicate-email-modal-overlay" id="duplicateEmailModal" aria-hidden="true">
    <div class="duplicate-email-modal" role="dialog" aria-labelledby="duplicateEmailModalTitle" aria-modal="true">
        <div class="duplicate-email-modal__icon" aria-hidden="true">
            @icon('envelope')
        </div>
        <h3 class="duplicate-email-modal__title" id="duplicateEmailModalTitle">Duplicate Email</h3>
        <p class="duplicate-email-modal__message">This email already exists.</p>
        <p class="duplicate-email-modal__filename" id="duplicateEmailFileName"></p>
        <p class="duplicate-email-modal__question">Do you want to upload it anyway?</p>
        <div class="duplicate-email-modal__actions">
            <button type="button" class="duplicate-email-modal__btn duplicate-email-modal__btn--reject" id="duplicateEmailReject">Reject</button>
            <button type="button" class="duplicate-email-modal__btn duplicate-email-modal__btn--accept" id="duplicateEmailAccept">Accept</button>
        </div>
    </div>
</div>

@include('crm.partials.email_delete_confirm_modal')

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

<!-- Context Menu Overlay (for closing menu on outside click) -->
<div id="contextMenuOverlay" class="context-menu-overlay" style="display: none;"></div>

<link rel="stylesheet" href="{{ asset('css/emails.css') }}">
<link rel="stylesheet" href="{{ asset('css/email-delete-confirm.css') }}">
<script>window.__CRM_EMAIL_ALLOWED_EXTENSIONS__ = @json($emailUploadExtensions);</script>
<script src="{{ asset('js/email-upload-helpers.js') }}"></script>
<script src="{{ asset('js/email-upload-flow.js') }}"></script>
<script src="{{ asset('js/email-delete-confirm.js') }}"></script>
<script src="{{ asset('js/emails.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initializeUpload === 'function') {
        window.initializeUpload();
    }
    if (typeof window.initializeSearch === 'function') {
        window.initializeSearch();
    }
    if (typeof window.initializeSendBodiesToS3Button === 'function') {
        window.initializeSendBodiesToS3Button();
    }
    if (typeof window.loadEmails === 'function') {
        setTimeout(function() {
            window.loadEmails();
        }, 0);
    }
});
</script>
