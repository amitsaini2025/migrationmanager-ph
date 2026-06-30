@extends('layouts.crm_client_detail')
@section('title', 'Broadcast Notifications')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <div>
                <h1 class="mb-0">Broadcast Notifications</h1>
                <p class="mb-0 broadcast-subtitle">Send announcements and monitor read receipts in real time.</p>
            </div>
        </div>

        <div class="section-body">
            <ul class="nav nav-tabs" id="broadcastTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="broadcasts-tab" data-bs-toggle="tab" href="#broadcasts" role="tab" aria-controls="broadcasts" aria-selected="true">
                        @icon('fa-bullhorn', ['class' => 'mr-1']) Broadcasts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="active-staff-tab" data-bs-toggle="tab" href="#active-staff" role="tab" aria-controls="active-staff" aria-selected="false">
                        @icon('fa-users', ['class' => 'mr-1']) Active Staff
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="broadcastTabsContent">
                <!-- Broadcasts Tab -->
                <div class="tab-pane fade show active" id="broadcasts" role="tabpanel" aria-labelledby="broadcasts-tab">
                    <div class="row mt-3">
                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="mb-0">Compose Broadcast</h4>
                                </div>
                                <div class="card-body">
                                    <div id="broadcast-compose-feedback" class="alert d-none" role="alert"></div>
                                    <form id="broadcast-compose-form" novalidate>
                                        @csrf
                                        <div class="form-group">
                                            <label for="broadcast-title">Title <span class="text-muted">(optional)</span></label>
                                            <input type="text" id="broadcast-title" name="title" class="form-control" maxlength="255" placeholder="System Maintenance">
                                        </div>

                                        <div class="form-group">
                                            <label for="broadcast-message">Message <span class="text-muted small">(Max 1000 characters)</span></label>
                                            <textarea id="broadcast-message" name="message" class="form-control" placeholder="Enter the announcement you want everyone to see..." required></textarea>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <small class="text-muted">Rich text formatting is supported</small>
                                                <small class="text-muted" id="broadcast-char-count">0 / 1000 characters</small>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="broadcast-scope">Audience</label>
                                            <select id="broadcast-scope" name="scope" class="form-control">
                                                <option value="all" selected>All staff</option>
                                                <option value="specific">Specific team members</option>
                                                <option value="team" disabled>Teams (coming soon)</option>
                                            </select>
                                        </div>

                                        <div class="form-group d-none" id="broadcast-recipient-group">
                                            <label for="broadcast-recipient-select">Select recipients</label>
                                            <select id="broadcast-recipient-select" class="form-control" name="recipient_ids[]" multiple="multiple" data-placeholder="Search team members"></select>
                                            <small class="form-text text-muted">Start typing to search for staff members. Portal client targeting will be added soon.</small>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <span class="text-muted small">
                                                Broadcasts send instantly and appear in the sticky banner for recipients.
                                            </span>
                                            <button type="submit" class="btn btn-primary" id="broadcast-submit-btn">
                                                <span class="submit-text">Send Broadcast</span>
                                                <span class="submit-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h4 class="mb-0">Broadcast History</h4>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge badge-light broadcast-count-badge" id="broadcast-history-count">0 broadcasts</span>
                                            <button type="button" class="btn btn-outline-light btn-sm broadcast-refresh-btn" id="broadcast-refresh-history">
                                                @icon('fa-sync-alt', ['class' => 'mr-1']) Refresh
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Tabs for different views -->
                                    <ul class="nav nav-pills nav-fill" id="history-tabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="all-broadcasts-tab" data-bs-toggle="pill" data-bs-target="#all-broadcasts" type="button" role="tab">
                                                @icon('fa-globe', ['class' => 'mr-1']) All Broadcasts
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="my-sent-tab" data-bs-toggle="pill" data-bs-target="#my-sent" type="button" role="tab">
                                                @icon('fa-paper-plane', ['class' => 'mr-1']) My Sent
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="my-read-tab" data-bs-toggle="pill" data-bs-target="#my-read" type="button" role="tab">
                                                @icon('fa-check-circle', ['class' => 'mr-1']) My Read
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content" id="history-tabs-content">
                                        <!-- All Broadcasts Tab -->
                                        <div class="tab-pane fade show active" id="all-broadcasts" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-striped" id="broadcast-history-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Sent By</th>
                                                            <th>Date</th>
                                                            <th>Message</th>
                                                            <th class="text-center">Read</th>
                                                            <th class="text-center">Unread</th>
                                                            <th class="text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="broadcast-history-body">
                                                        <tr>
                                                            <td colspan="6" class="text-center text-muted py-4">
                                                                @icon('fa-bullhorn', ['class' => 'mb-2', 'style' => 'font-size: 28px;'])
                                                                <div>No broadcasts yet. Send your first announcement!</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- My Sent Broadcasts Tab -->
                                        <div class="tab-pane fade" id="my-sent" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Message</th>
                                                            <th class="text-center">Read</th>
                                                            <th class="text-center">Unread</th>
                                                            <th class="text-right">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="my-sent-body">
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted py-4">
                                                                @icon('fa-paper-plane', ['class' => 'mb-2', 'style' => 'font-size: 28px;'])
                                                                <div>You haven't sent any broadcasts yet.</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- My Read Broadcasts Tab -->
                                        <div class="tab-pane fade" id="my-read" role="tabpanel">
                                            <div class="table-responsive">
                                                <table class="table table-striped">
                                                    <thead>
                                                        <tr>
                                                            <th>Sent By</th>
                                                            <th>Date</th>
                                                            <th>Message</th>
                                                            <th>Read At</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="my-read-body">
                                                        <tr>
                                                            <td colspan="4" class="text-center text-muted py-4">
                                                                @icon('fa-check-circle', ['class' => 'mb-2', 'style' => 'font-size: 28px;'])
                                                                <div>No read broadcasts yet.</div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-muted small">
                                    @icon('fa-info-circle') All staff can view broadcast history. Only super admins can delete broadcasts.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Staff Tab -->
                <div class="tab-pane fade" id="active-staff" role="tabpanel" aria-labelledby="active-staff-tab">
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card shadow-sm border-0 active-staff-modern-card" id="active-staff-card">
                                <!-- Modern Header Section -->
                                <div class="active-staff-header">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-3 mb-2">
                                                <div class="active-staff-icon-wrapper">
                                                    @icon('fa-users')
                                                </div>
                                                <div>
                                                    <h4 class="mb-1 active-staff-title">Active Staff</h4>
                                                    <p class="mb-0 active-staff-subtitle">Monitor real-time staff presence and activity</p>
                                                </div>
                                            </div>
                                            <div class="active-staff-stats">
                                                <span class="badge badge-pill active-staff-count-badge" id="active-staff-count">
                                                    @icon('fa-circle', ['class' => 'status-dot-online'])
                                                    <span class="count-text">1</span> online
                                                </span>
                                                <small class="text-muted ml-3">
                                                    @icon('fa-info-circle') Presence calculated from active sessions within the last 5 minutes
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                                            <a href="{{ route('staff-login-analytics.index') }}" class="btn btn-light btn-sm active-staff-action-btn">
                                                @icon('fa-chart-line')
                                                <span class="d-none d-md-inline">Analytics</span>
                                            </a>
                                            @endif
                                            <button type="button" class="btn btn-sm active-staff-refresh-btn" id="active-staff-refresh">
                                                @icon('fa-sync-alt')
                                                <span class="d-none d-md-inline">Refresh</span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Enhanced Search and Filters -->
                                    <div class="active-staff-filters">
                                        <div class="row g-3">
                                            <div class="col-md-5 col-lg-4">
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text bg-white border-right-0">
                                                            @icon('fa-search', ['class' => 'text-muted'])
                                                        </span>
                                                    </div>
                                                    <input type="text" 
                                                           class="form-control border-left-0 active-staff-search-input" 
                                                           id="active-staff-search" 
                                                           placeholder="Search by name or email...">
                                                </div>
                                            </div>
                                            <div class="col-md-3 col-lg-2">
                                                <select class="form-control form-control-sm active-staff-filter-select" id="active-staff-role-filter">
                                                    <option value="">All Roles</option>
                                                    @foreach(\App\Models\UserRole::all() as $role)
                                                        <option value="{{ $role->id }}">{{ $role->name ?? 'Role #' . $role->id }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-lg-2">
                                                <select class="form-control form-control-sm active-staff-filter-select" id="active-staff-team-filter">
                                                    <option value="">All Teams</option>
                                                    @foreach(\App\Models\Team::all() as $team)
                                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-1 col-lg-1">
                                                <button type="button" 
                                                        class="btn btn-outline-secondary btn-sm btn-block active-staff-clear-btn" 
                                                        id="active-staff-clear-filters"
                                                        title="Clear filters">
                                                    @icon('fa-times')
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Modern Table Section -->
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 active-staff-table-modern" id="active-staff-table">
                                            <thead>
                                                <tr>
                                                    <th class="sortable" data-sort="name">
                                                        <span class="th-content">
                                                            @icon('fa-user')
                                                            <span>Staff</span>
                                                            @icon('fa-sort', ['class' => 'sort-icon'])
                                                        </span>
                                                    </th>
                                                    <th class="text-center sortable" data-sort="status" style="width: 100px;">
                                                        <span class="th-content">
                                                            @icon('fa-circle')
                                                            <span>Status</span>
                                                        </span>
                                                    </th>
                                                    <th class="sortable" data-sort="role">
                                                        <span class="th-content">
                                                            @icon('fa-user-tag')
                                                            <span>Role</span>
                                                            @icon('fa-sort', ['class' => 'sort-icon'])
                                                        </span>
                                                    </th>
                                                    <th class="sortable" data-sort="team">
                                                        <span class="th-content">
                                                            @icon('fa-users-cog')
                                                            <span>Team</span>
                                                            @icon('fa-sort', ['class' => 'sort-icon'])
                                                        </span>
                                                    </th>
                                                    <th class="sortable" data-sort="last_activity">
                                                        <span class="th-content">
                                                            @icon('fa-clock')
                                                            <span>Last Activity</span>
                                                            @icon('fa-sort', ['class' => 'sort-icon'])
                                                        </span>
                                                    </th>
                                                    <th>
                                                        <span class="th-content">
                                                            @icon('fa-sign-in-alt')
                                                            <span>Last Login</span>
                                                        </span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="active-staff-body">
                                                <tr>
                                                    <td colspan="6" class="text-center py-5">
                                                        <div class="active-staff-empty-state">
                                                            <div class="spinner-border spinner-border-sm text-primary mb-3" role="status" id="active-staff-loading" style="display: none;">
                                                                <span class="sr-only">Loading...</span>
                                                            </div>
                                                            @icon('fa-users', ['class' => 'mb-3 empty-state-icon'])
                                                            <div id="active-staff-empty-message" class="empty-state-message">Click the tab to load active staff.</div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Modern Footer -->
                                <div class="card-footer bg-white border-top active-staff-footer">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <div class="text-muted small">
                                            @icon('fa-info-circle', ['class' => 'text-primary'])
                                            <span id="active-staff-info">Refreshing manually will recalculate active sessions in real time.</span>
                                            <span id="active-staff-last-refresh" class="ml-2"></span>
                                        </div>
                                        <nav aria-label="Active staff pagination" id="active-staff-pagination">
                                            <!-- Pagination will be inserted here by JavaScript -->
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="broadcastDetailModal" tabindex="-1" role="dialog" aria-labelledby="broadcastDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="broadcastDetailModalLabel">Broadcast Details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong id="broadcast-detail-title" class="d-block"></strong>
                    <span id="broadcast-detail-message" class="d-block"></span>
                    <small class="text-muted" id="broadcast-detail-meta"></small>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Status</th>
                                <th>Read at</th>
                            </tr>
                        </thead>
                        <tbody id="broadcast-detail-body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">Loading recipients…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Receiver Read Modal -->
<div class="modal fade" id="broadcastReaderModal" tabindex="-1" role="dialog" aria-labelledby="broadcastReaderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="broadcastReaderModalLabel">Broadcast Message</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <strong id="broadcast-reader-title" class="d-block"></strong>
                    <div id="broadcast-reader-message" class="d-block mt-2"></div>
                    <small class="text-muted d-block mt-2" id="broadcast-reader-meta"></small>
                </div>
                <div class="alert alert-info mb-0" id="broadcast-reader-countdown">
                    Please read this message. You can mark it as read shortly.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary d-none" id="broadcast-reader-mark-read">Read</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Initialize TinyMCE for broadcast message
    let broadcastEditor = null;
    
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#broadcast-message',
            license_key: 'gpl',
            height: 250,
            menubar: false,
            plugins: [
                'lists', 'link', 'autolink', 'code', 'wordcount'
            ],
            toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link | code | removeformat',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            placeholder: 'Enter the announcement you want everyone to see...',
            branding: false,
            promotion: false,
            statusbar: true,
            resize: true,
            max_chars: 1000,
            setup: function(editor) {
                broadcastEditor = editor;
                
                // Character counter
                editor.on('init', function() {
                    updateCharCount(editor);
                });
                
                editor.on('keyup change', function() {
                    updateCharCount(editor);
                });
                
                // Enforce character limit
                editor.on('keydown', function(e) {
                    const content = editor.getContent({format: 'text'});
                    if (content.length >= 1000 && e.keyCode !== 8 && e.keyCode !== 46) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    }
    
    function updateCharCount(editor) {
        const content = editor.getContent({format: 'text'});
        const charCount = content.length;
        const charCountEl = document.getElementById('broadcast-char-count');
        
        if (charCountEl) {
            charCountEl.textContent = `${charCount} / 1000 characters`;
            charCountEl.className = 'text-muted';
            
            if (charCount > 950) {
                charCountEl.className = 'text-warning font-weight-bold';
            }
            if (charCount >= 1000) {
                charCountEl.className = 'text-danger font-weight-bold';
            }
        }
    }
    
    (function () {
        const BROADCAST_READ_DELAY_SECONDS = {{ (int) ($broadcastReadDelaySeconds ?? 15) }};

        const composeForm = document.getElementById('broadcast-compose-form');
        const messageInput = document.getElementById('broadcast-message');
        const titleInput = document.getElementById('broadcast-title');
        const scopeSelect = document.getElementById('broadcast-scope');
        const recipientGroup = document.getElementById('broadcast-recipient-group');
        const recipientSelect = $('#broadcast-recipient-select');

        const feedbackEl = document.getElementById('broadcast-compose-feedback');
        const submitBtn = document.getElementById('broadcast-submit-btn');
        const submitText = submitBtn.querySelector('.submit-text');
        const submitSpinner = submitBtn.querySelector('.submit-spinner');

        const historyBody = document.getElementById('broadcast-history-body');
        const historyCount = document.getElementById('broadcast-history-count');
        const refreshBtn = document.getElementById('broadcast-refresh-history');

        const detailModal = $('#broadcastDetailModal');
        const detailTitle = document.getElementById('broadcast-detail-title');
        const detailMessage = document.getElementById('broadcast-detail-message');
        const detailMeta = document.getElementById('broadcast-detail-meta');
        const detailBody = document.getElementById('broadcast-detail-body');
        const readerModal = $('#broadcastReaderModal');
        const readerTitle = document.getElementById('broadcast-reader-title');
        const readerMessage = document.getElementById('broadcast-reader-message');
        const readerMeta = document.getElementById('broadcast-reader-meta');
        const readerCountdown = document.getElementById('broadcast-reader-countdown');
        const readerMarkReadBtn = document.getElementById('broadcast-reader-mark-read');

        const activeStaffBody = document.getElementById('active-staff-body');
        const activeStaffCount = document.getElementById('active-staff-count');
        const activeStaffRefresh = document.getElementById('active-staff-refresh');
        const activeStaffSearch = document.getElementById('active-staff-search');
        const activeStaffRoleFilter = document.getElementById('active-staff-role-filter');
        const activeStaffTeamFilter = document.getElementById('active-staff-team-filter');
        const activeStaffClearFilters = document.getElementById('active-staff-clear-filters');
        const activeStaffLoading = document.getElementById('active-staff-loading');
        const activeStaffEmptyMessage = document.getElementById('active-staff-empty-message');
        const activeStaffLastRefresh = document.getElementById('active-staff-last-refresh');
        const activeStaffPagination = document.getElementById('active-staff-pagination');
        const activeStaffTab = document.getElementById('active-staff-tab');

        // Active Staff State
        let activeStaffState = {
            loaded: false,
            loading: false,
            currentPage: 1,
            perPage: 15,
            sortBy: 'name',
            sortDir: 'asc',
            search: '',
            roleId: null,
            teamId: null,
            refreshTimeout: null,
            debounceTimeout: null,
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Setup CSRF for AJAX (including mmSelect remote search)
        if (typeof $ !== 'undefined' && $.ajaxSetup) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }

        function toggleRecipientsVisibility() {
            if (scopeSelect.value === 'specific') {
                recipientGroup.classList.remove('d-none');
            } else {
                recipientGroup.classList.add('d-none');
                recipientSelect.val(null).trigger('change');
            }
        }

        function showFeedback(type, message) {
            feedbackEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
            feedbackEl.classList.add(`alert-${type}`);
            feedbackEl.textContent = message;
        }

        function hideFeedback() {
            feedbackEl.classList.add('d-none');
            feedbackEl.textContent = '';
        }

        function setSubmitting(isSubmitting) {
            submitBtn.disabled = isSubmitting;
            submitText.classList.toggle('d-none', isSubmitting);
            submitSpinner.classList.toggle('d-none', !isSubmitting);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) {
                return '';
            }
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        }

        // State to track current staff and permissions
        let currentState = {
            isSuperAdmin: false,
            currentStaffId: null
        };

        let readerState = {
            notificationId: null,
            countdownTimer: null,
            remainingSeconds: 0,
        };

        // Helper function to truncate HTML message for display
        function truncateMessage(html, maxLength = 150) {
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const text = temp.textContent || temp.innerText || '';
            
            if (text.length <= maxLength) {
                return html;
            }
            
            // Truncate text and add ellipsis
            const truncated = text.substring(0, maxLength) + '...';
            return `<span title="${text.replace(/"/g, '&quot;')}">${truncated}</span>`;
        }

        function clearReaderTimer() {
            if (readerState.countdownTimer) {
                clearInterval(readerState.countdownTimer);
                readerState.countdownTimer = null;
            }
        }

        function updateReaderCountdown(remainingSeconds) {
            readerState.remainingSeconds = Math.max(0, Number(remainingSeconds) || 0);

            if (readerState.remainingSeconds > 0) {
                readerCountdown.textContent = `Please read this message. The Read button will appear in ${readerState.remainingSeconds}s.`;
                readerCountdown.className = 'alert alert-info mb-0';
                readerMarkReadBtn.classList.add('d-none');
                return;
            }

            readerCountdown.textContent = 'You can now tap Read to confirm you have read this message.';
            readerCountdown.className = 'alert alert-success mb-0';
            readerMarkReadBtn.classList.remove('d-none');
        }

        function startReaderCountdown(remainingSeconds) {
            clearReaderTimer();
            updateReaderCountdown(remainingSeconds);

            if ((Number(remainingSeconds) || 0) <= 0) {
                return;
            }

            readerState.countdownTimer = setInterval(() => {
                if (readerState.remainingSeconds <= 1) {
                    clearReaderTimer();
                    updateReaderCountdown(0);
                    return;
                }

                updateReaderCountdown(readerState.remainingSeconds - 1);
            }, 1000);
        }

        function openReceiverBroadcast(notificationId) {
            const parsedId = parseInt(notificationId, 10);
            if (!Number.isFinite(parsedId)) {
                return;
            }

            readerState.notificationId = parsedId;
            readerTitle.textContent = '';
            readerMessage.innerHTML = '';
            readerMeta.textContent = '';
            updateReaderCountdown(BROADCAST_READ_DELAY_SECONDS);
            readerModal.modal('show');

            fetch(`/notifications/broadcasts/${parsedId}/receiver-detail`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to load broadcast message.');
                    }
                    return payload;
                })
                .then((payload) => {
                    const data = payload.data || {};
                    readerTitle.textContent = data.title || '';
                    readerTitle.classList.toggle('d-none', !data.title);
                    readerMessage.innerHTML = data.message || '';
                    readerMeta.textContent = `${data.sender_name || 'System'} • ${formatDate(data.sent_at)}`;
                })
                .catch((error) => {
                    console.error(error);
                    readerCountdown.textContent = error.message || 'Failed to load message.';
                    readerCountdown.className = 'alert alert-danger mb-0';
                    readerMarkReadBtn.classList.add('d-none');
                });

            fetch(`/notifications/broadcasts/${parsedId}/start-read-timer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({}),
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to start read timer.');
                    }
                    return payload;
                })
                .then((payload) => {
                    startReaderCountdown(payload.remaining_seconds || 0);
                })
                .catch((error) => {
                    console.error(error);
                    readerCountdown.textContent = error.message || 'Unable to start read timer.';
                    readerCountdown.className = 'alert alert-danger mb-0';
                });
        }

        function renderHistoryTable(items, isSuperAdmin = false) {
            historyBody.innerHTML = '';

            if (!items.length) {
                historyBody.innerHTML = `<tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        @icon('fa-bullhorn', ['class' => 'mb-2', 'style' => 'font-size: 28px;'])
                        <div>No broadcasts yet. Send your first announcement!</div>
                    </td>
                </tr>`;
                historyCount.textContent = '0 broadcasts';
                return;
            }

            historyCount.textContent = `${items.length} broadcast${items.length !== 1 ? 's' : ''}`;

            items.forEach((item) => {
                const row = document.createElement('tr');
                const deleteBtn = isSuperAdmin 
                    ? `<button type="button" class="btn btn-outline-danger btn-sm ml-1" data-action="delete-broadcast" data-batch="${item.batch_uuid}" title="Delete broadcast">
                           @icon('fa-trash')
                       </button>`
                    : '';
                
                row.innerHTML = `
                    <td>
                        <strong>${item.sender_name || 'Unknown'}</strong>
                        <br><small class="text-muted">#${item.sender_id}</small>
                    </td>
                    <td>${formatDate(item.sent_at)}</td>
                    <td>
                        ${item.title ? `<strong>${item.title}</strong><br>` : ''}
                        <span class="broadcast-message-text">${item.message}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-success">${item.read_count}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-warning">${item.unread_count}</span>
                    </td>
                    <td class="text-right">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-action="view-broadcast" data-batch="${item.batch_uuid}">
                            @icon('fa-eye') Details
                        </button>
                        ${deleteBtn}
                    </td>
                `;
                historyBody.appendChild(row);
            });
        }

        function renderMySentTable(items, isSuperAdmin = false) {
            const mySentBody = document.getElementById('my-sent-body');
            mySentBody.innerHTML = '';

            if (!items.length) {
                mySentBody.innerHTML = `<tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        @icon('fa-paper-plane', ['class' => 'mb-2', 'style' => 'font-size: 28px;'])
                        <div>You haven't sent any broadcasts yet.</div>
                    </td>
                </tr>`;
                return;
            }

            items.forEach((item) => {
                const row = document.createElement('tr');
                const deleteBtn = isSuperAdmin 
                    ? `<button type="button" class="btn btn-outline-danger btn-sm ml-1" data-action="delete-broadcast" data-batch="${item.batch_uuid}" title="Delete broadcast">
                           @icon('fa-trash')
                       </button>`
                    : '';
                
                row.innerHTML = `
                    <td>${formatDate(item.sent_at)}</td>
                    <td>
                        ${item.title ? `<strong>${item.title}</strong><br>` : ''}
                        <span class="broadcast-message-text">${item.message}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-success">${item.read_count}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-warning">${item.unread_count}</span>
                    </td>
                    <td class="text-right">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-action="view-broadcast" data-batch="${item.batch_uuid}">
                            @icon('fa-eye') Details
                        </button>
                        ${deleteBtn}
                    </td>
                `;
                mySentBody.appendChild(row);
            });
        }

        function renderMyReadTable(items) {
            const myReadBody = document.getElementById('my-read-body');
            myReadBody.innerHTML = '';

            if (!items.length) {
                myReadBody.innerHTML = `<tr>
                    <td colspan="4" class="text-center text-muted py-4">
                        @icon('fa-check-circle', ['class' => 'mb-2', 'style' => 'font-size: 28px;'])
                        <div>No read broadcasts yet.</div>
                    </td>
                </tr>`;
                return;
            }

            items.forEach((item) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <strong>${item.sender_name || 'Unknown'}</strong>
                    </td>
                    <td>${formatDate(item.sent_at)}</td>
                    <td>
                        ${item.title ? `<strong>${item.title}</strong><br>` : ''}
                        <span class="broadcast-message-text">${item.message}</span>
                    </td>
                    <td>
                        <span class="text-success">
                            ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check mr-1') : '<i class="fas fa-check mr-1"></i>'}${formatDate(item.read_at)}
                        </span>
                    </td>
                `;
                myReadBody.appendChild(row);
            });
        }

        function formatTimeAgo(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) return '—';
            
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            
            return formatDate(dateString);
        }

        function getActivityDuration(lastActivity) {
            if (!lastActivity) return '—';
            const date = new Date(lastActivity);
            if (Number.isNaN(date.getTime())) return '—';
            
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''}`;
            const hours = Math.floor(diffMins / 60);
            return `${hours} hour${hours > 1 ? 's' : ''}`;
        }

        function getInitials(name) {
            if (!name) return '?';
            const parts = name.trim().split(/\s+/);
            if (parts.length >= 2) {
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        }

        function renderActiveStaff(data, meta) {
            activeStaffBody.innerHTML = '';
            activeStaffLoading.style.display = 'none';

            if (!data || !data.length) {
                activeStaffBody.innerHTML = `<tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="active-staff-empty-state">
                            @icon('fa-users', ['class' => 'mb-3 empty-state-icon'])
                            <div class="empty-state-message">No active staff detected in the last few minutes.</div>
                        </div>
                    </td>
                </tr>`;
                const countBadge = activeStaffCount.querySelector('.count-text') || activeStaffCount;
                if (countBadge.tagName === 'SPAN') {
                    countBadge.textContent = '0';
                } else {
                    activeStaffCount.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-circle status-dot-online') : '<i class="fas fa-circle status-dot-online"></i>') + '<span class="count-text">0</span> online';
                }
                activeStaffCount.className = 'badge badge-pill active-staff-count-badge';
                activeStaffEmptyMessage.textContent = 'No active staff detected in the last few minutes.';
                renderPagination(null);
                return;
            }

            const total = meta?.total || data.length;
            const countBadge = activeStaffCount.querySelector('.count-text');
            if (countBadge) {
                countBadge.textContent = total;
            } else {
                activeStaffCount.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-circle status-dot-online') : '<i class="fas fa-circle status-dot-online"></i>') + `<span class="count-text">${total}</span> online`;
            }
            activeStaffCount.className = 'badge badge-pill active-staff-count-badge';
            activeStaffEmptyMessage.textContent = '';

            data.forEach((staff) => {
                const row = document.createElement('tr');
                row.className = 'active-staff-row';
                
                const avatar = staff.profile_img 
                    ? `<img src="${staff.profile_img}" alt="${staff.name}" class="user-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                    : '';
                const avatarFallback = `<div class="user-avatar-fallback" style="${staff.profile_img ? 'display:none;' : ''}">${getInitials(staff.name)}</div>`;
                
                const teamBadge = staff.team_name 
                    ? `<span class="badge badge-light team-badge" ${staff.team_color ? `style="background-color: ${staff.team_color}20; color: ${staff.team_color}; border: 1px solid ${staff.team_color}40;"` : ''}>${staff.team_name}</span>`
                    : '<span class="text-muted">—</span>';
                
                const roleName = staff.role_name || `Role #${staff.role_id || '—'}`;
                const officeInfo = staff.office_name ? `<br><small class="text-muted">${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-building mr-1') : '<i class="fas fa-building mr-1"></i>'}${staff.office_name}</small>` : '';

                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar-wrapper mr-2">
                                ${avatar}
                                ${avatarFallback}
                            </div>
                            <div>
                                <strong>${staff.name}</strong>
                                <br>
                                <span class="text-muted small">#${staff.id}</span>
                                ${officeInfo}
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="status-indicator online" title="Online"></span>
                    </td>
                    <td>${roleName}</td>
                    <td>${teamBadge}</td>
                    <td>
                        <div>${formatTimeAgo(staff.last_activity)}</div>
                        <small class="text-muted">Active for ${getActivityDuration(staff.last_activity)}</small>
                    </td>
                    <td>${staff.last_login ? formatTimeAgo(staff.last_login) : '—'}</td>
                `;
                activeStaffBody.appendChild(row);
            });

            renderPagination(meta);
            updateSortIcons();
        }

        function renderPagination(meta) {
            if (!meta || meta.last_page <= 1) {
                activeStaffPagination.innerHTML = '';
                return;
            }

            let html = '<ul class="pagination pagination-sm mb-0">';
            
            // Previous button
            html += `<li class="page-item ${meta.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${meta.current_page - 1}" ${meta.current_page === 1 ? 'tabindex="-1"' : ''}>
                    @icon('fa-chevron-left')
                </a>
            </li>`;

            // Page numbers
            const startPage = Math.max(1, meta.current_page - 2);
            const endPage = Math.min(meta.last_page, meta.current_page + 2);

            if (startPage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
                if (startPage > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === meta.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }

            if (endPage < meta.last_page) {
                if (endPage < meta.last_page - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${meta.last_page}">${meta.last_page}</a></li>`;
            }

            // Next button
            html += `<li class="page-item ${meta.current_page === meta.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${meta.current_page + 1}" ${meta.current_page === meta.last_page ? 'tabindex="-1"' : ''}>
                    @icon('fa-chevron-right')
                </a>
            </li>`;

            html += '</ul>';
            activeStaffPagination.innerHTML = html;

            // Add click handlers
            activeStaffPagination.querySelectorAll('a[data-page]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = parseInt(link.getAttribute('data-page'));
                    if (page && page !== activeStaffState.currentPage) {
                        activeStaffState.currentPage = page;
                        loadActiveStaff();
                    }
                });
            });
        }

        function updateSortIcons() {
            document.querySelectorAll('.sortable').forEach(th => {
                const sortIcon = th.querySelector('.sort-icon');
                if (!sortIcon) return;
                
                const sortValue = th.getAttribute('data-sort');
                if (sortValue === activeStaffState.sortBy) {
                    th.classList.add('active');
                    sortIcon.className = `fas fa-sort-${activeStaffState.sortDir === 'asc' ? 'up' : 'down'} sort-icon`;
                    sortIcon.style.color = '#005792';
                } else {
                    th.classList.remove('active');
                    sortIcon.className = 'fas fa-sort sort-icon';
                    sortIcon.style.color = '';
                }
            });
        }

        function loadHistory() {
            historyBody.classList.add('loading');
            fetch('/notifications/broadcasts/history', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load broadcast history.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    // Store current staff info
                    currentState.isSuperAdmin = payload.is_super_admin || false;
                    currentState.currentStaffId = payload.current_staff_id || payload.current_user_id || null;
                    
                    renderHistoryTable(payload.data || [], currentState.isSuperAdmin);
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', 'Failed to load broadcast history. Please try again.');
                })
                .finally(() => {
                    historyBody.classList.remove('loading');
                });
        }

        function loadMySent() {
            const mySentBody = document.getElementById('my-sent-body');
            mySentBody.classList.add('loading');
            
            fetch('/notifications/broadcasts/my-history', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load your sent broadcasts.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    renderMySentTable(payload.data || [], currentState.isSuperAdmin);
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', 'Failed to load your sent broadcasts.');
                })
                .finally(() => {
                    mySentBody.classList.remove('loading');
                });
        }

        function loadMyRead() {
            const myReadBody = document.getElementById('my-read-body');
            myReadBody.classList.add('loading');
            
            fetch('/notifications/broadcasts/read-history', {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load your read broadcasts.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    renderMyReadTable(payload.data || []);
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', 'Failed to load your read broadcasts.');
                })
                .finally(() => {
                    myReadBody.classList.remove('loading');
                });
        }

        function deleteBroadcast(batchUuid) {
            if (!confirm('Are you sure you want to delete this broadcast? This action cannot be undone.')) {
                return;
            }

            fetch(`/notifications/broadcasts/${batchUuid}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Failed to delete broadcast.');
                    }
                    return payload;
                })
                .then(() => {
                    showFeedback('success', 'Broadcast deleted successfully.');
                    // Reload all tabs
                    loadHistory();
                    loadMySent();
                    loadMyRead();
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', error.message || 'Failed to delete broadcast.');
                });
        }

        function loadBroadcastDetails(batchUuid, onSuccess) {
            detailBody.innerHTML = `<tr>
                <td colspan="3" class="text-center text-muted py-3">Loading recipients…</td>
            </tr>`;

            return fetch(`/notifications/broadcasts/${batchUuid}/details`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to load broadcast details.');
                    }
                    return response.json();
                })
                .then((payload) => {
                    const data = payload.data;
                    detailTitle.textContent = data.title || '';
                    detailTitle.classList.toggle('d-none', !data.title);
                    detailMessage.innerHTML = data.message || ''; // Use innerHTML to render HTML content
                    detailMeta.textContent = `${data.sender_name || 'You'} • ${formatDate(data.sent_at)}`;

                    if (!Array.isArray(data.recipients) || !data.recipients.length) {
                        detailBody.innerHTML = `<tr>
                            <td colspan="3" class="text-center text-muted py-3">No recipients found.</td>
                        </tr>`;
                        if (typeof onSuccess === 'function') onSuccess();
                        return;
                    }

                    detailBody.innerHTML = '';
                    data.recipients.forEach((recipient) => {
                        const row = document.createElement('tr');
                        const statusBadge = recipient.read
                            ? '<span class="badge badge-success">Read</span>'
                            : '<span class="badge badge-secondary">Unread</span>';
                        row.innerHTML = `
                            <td>${recipient.receiver_name || `Staff #${recipient.receiver_id}`}</td>
                            <td>${statusBadge}</td>
                            <td>${recipient.read_at ? formatDate(recipient.read_at) : '-'}</td>
                        `;
                        detailBody.appendChild(row);
                    });
                    if (typeof onSuccess === 'function') onSuccess();
                })
                .catch((error) => {
                    console.error(error);
                    detailBody.innerHTML = `<tr>
                        <td colspan="3" class="text-center text-danger py-3">Failed to load recipients.</td>
                    </tr>`;
                });
        }

        function loadActiveStaff(showLoading = true) {
            if (activeStaffState.loading) return;
            
            activeStaffState.loading = true;
            if (showLoading) {
                activeStaffLoading.style.display = 'inline-block';
                activeStaffEmptyMessage.textContent = 'Loading active staff...';
            }

            const params = new URLSearchParams({
                threshold: 5,
                page: activeStaffState.currentPage,
                per_page: activeStaffState.perPage,
                sort_by: activeStaffState.sortBy,
                sort_dir: activeStaffState.sortDir,
            });

            if (activeStaffState.search) {
                params.append('search', activeStaffState.search);
            }
            if (activeStaffState.roleId) {
                params.append('role_id', activeStaffState.roleId);
            }
            if (activeStaffState.teamId) {
                params.append('team_id', activeStaffState.teamId);
            }

            fetch(`/dashboard/active-staff?${params.toString()}`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'include',
            })
                .then(async (response) => {
                    if (!response.ok) {
                        const error = await response.json().catch(() => ({}));
                        throw new Error(error.message || `HTTP ${response.status}: Unable to load active staff.`);
                    }
                    return response.json();
                })
                .then((payload) => {
                    activeStaffState.loaded = true;
                    activeStaffState.loading = false;
                    renderActiveStaff(payload.data || [], payload.meta || {});
                    activeStaffLastRefresh.textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
                })
                .catch((error) => {
                    console.error('Active staff load error:', error);
                    activeStaffState.loading = false;
                    activeStaffLoading.style.display = 'none';
                    activeStaffBody.innerHTML = `<tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="active-staff-empty-state">
                                @icon('fa-exclamation-triangle', ['class' => 'mb-3 empty-state-icon text-warning'])
                                <div class="empty-state-message">
                                    <strong class="text-danger d-block mb-2">Failed to load active staff</strong>
                                    <span class="text-muted">${error.message || 'Please try again or refresh the page.'}</span>
                                </div>
                                <button class="btn btn-sm mt-3" id="active-staff-retry-btn">
                                    @icon('fa-redo', ['class' => 'mr-1']) Retry
                                </button>
                            </div>
                        </td>
                    </tr>`;
                    activeStaffCount.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-circle status-dot-online') : '<i class="fas fa-circle status-dot-online"></i>') + '<span class="count-text">—</span> unavailable';
                    activeStaffCount.className = 'badge badge-pill active-staff-count-badge';
                    
                    // Add retry button handler
                    const retryBtn = activeStaffBody.querySelector('#active-staff-retry-btn');
                    if (retryBtn) {
                        retryBtn.addEventListener('click', () => loadActiveStaff(true));
                    }
                });
        }

        function debounceLoadActiveStaff(delay = 300) {
            clearTimeout(activeStaffState.debounceTimeout);
            activeStaffState.debounceTimeout = setTimeout(() => {
                activeStaffState.currentPage = 1; // Reset to first page on filter change
                loadActiveStaff();
            }, delay);
        }

        // Tab-based loading
        if (activeStaffTab) {
            // Use jQuery for Bootstrap tab events
            $('#active-staff-tab').on('shown.bs.tab', function() {
                if (!activeStaffState.loaded && !activeStaffState.loading) {
                    loadActiveStaff();
                }
            });
        }

        composeForm.addEventListener('submit', (event) => {
            event.preventDefault();
            hideFeedback();

            // Get message from TinyMCE editor
            let messageContent = '';
            if (broadcastEditor) {
                messageContent = broadcastEditor.getContent({format: 'html'}).trim();
                const textContent = broadcastEditor.getContent({format: 'text'}).trim();
                
                // Validate content
                if (!textContent) {
                    showFeedback('warning', 'Please enter a message before sending your broadcast.');
                    broadcastEditor.focus();
                    return;
                }
                
                // Validate character limit
                if (textContent.length > 1000) {
                    showFeedback('warning', 'Message exceeds 1000 character limit. Please shorten your message.');
                    broadcastEditor.focus();
                    return;
                }
            } else {
                // Fallback to textarea if TinyMCE isn't loaded
                messageContent = messageInput.value.trim();
                if (!messageContent) {
                    showFeedback('warning', 'Please enter a message before sending your broadcast.');
                    messageInput.focus();
                    return;
                }
            }

            if (scopeSelect.value === 'specific' && recipientSelect.val().length === 0) {
                showFeedback('warning', 'Select at least one recipient or switch back to All staff.');
                return;
            }

            setSubmitting(true);

            fetch('/notifications/broadcasts/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'include',
                body: JSON.stringify({
                    title: titleInput.value || null,
                    message: messageContent,
                    scope: scopeSelect.value,
                    recipient_ids: scopeSelect.value === 'specific' ? recipientSelect.val() : [],
                }),
            })
                .then(async (response) => {
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.message || 'Unable to send broadcast.');
                    }
                    return payload;
                })
                .then((payload) => {
                    showFeedback('success', 'Broadcast sent successfully.');
                    
                    // Reset form
                    composeForm.reset();
                    
                    // Reset TinyMCE editor
                    if (broadcastEditor) {
                        broadcastEditor.setContent('');
                        updateCharCount(broadcastEditor);
                    }
                    
                    // Reset recipient select
                    recipientSelect.val(null).trigger('change');
                    toggleRecipientsVisibility();
                    
                    // Reload history
                    loadHistory();
                })
                .catch((error) => {
                    console.error(error);
                    showFeedback('danger', error.message || 'Failed to send broadcast.');
                })
                .finally(() => {
                    setSubmitting(false);
                });
        });

        // Event delegation for history table actions (view and delete)
        historyBody.addEventListener('click', (event) => {
            const viewButton = event.target.closest('[data-action="view-broadcast"]');
            const deleteButton = event.target.closest('[data-action="delete-broadcast"]');
            
            if (viewButton) {
                const batchUuid = viewButton.getAttribute('data-batch');
                if (batchUuid) {
                    loadBroadcastDetails(batchUuid);
                    detailModal.modal('show');
                }
            }
            
            if (deleteButton) {
                const batchUuid = deleteButton.getAttribute('data-batch');
                if (batchUuid) {
                    deleteBroadcast(batchUuid);
                }
            }
        });

        // Event delegation for "My Sent" table
        const mySentBody = document.getElementById('my-sent-body');
        if (mySentBody) {
            mySentBody.addEventListener('click', (event) => {
                const viewButton = event.target.closest('[data-action="view-broadcast"]');
                const deleteButton = event.target.closest('[data-action="delete-broadcast"]');
                
                if (viewButton) {
                    const batchUuid = viewButton.getAttribute('data-batch');
                    if (batchUuid) {
                        loadBroadcastDetails(batchUuid);
                        detailModal.modal('show');
                    }
                }
                
                if (deleteButton) {
                    const batchUuid = deleteButton.getAttribute('data-batch');
                    if (batchUuid) {
                        deleteBroadcast(batchUuid);
                    }
                }
            });
        }

        // Tab switching event listeners
        $('#all-broadcasts-tab').on('shown.bs.tab', function() {
            loadHistory();
        });

        $('#my-sent-tab').on('shown.bs.tab', function() {
            loadMySent();
        });

        $('#my-read-tab').on('shown.bs.tab', function() {
            loadMyRead();
        });

        refreshBtn.addEventListener('click', (event) => {
            event.preventDefault();
            // Reload all tabs
            loadHistory();
            loadMySent();
            loadMyRead();
        });

        if (readerMarkReadBtn) {
            readerMarkReadBtn.addEventListener('click', () => {
                if (!readerState.notificationId) {
                    return;
                }

                fetch(`/notifications/broadcasts/${readerState.notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'include',
                    body: JSON.stringify({}),
                })
                    .then(async (response) => {
                        const payload = await response.json();
                        if (!response.ok) {
                            if (response.status === 422) {
                                startReaderCountdown(payload.remaining_seconds || 1);
                            }
                            throw new Error(payload.message || 'Unable to mark as read.');
                        }
                        return payload;
                    })
                    .then(() => {
                        showFeedback('success', 'Broadcast marked as read.');
                        readerModal.modal('hide');
                        loadHistory();
                        loadMyRead();
                    })
                    .catch((error) => {
                        console.error(error);
                        showFeedback('warning', error.message || 'Unable to mark as read.');
                    });
            });
        }

        readerModal.on('hidden.bs.modal', function() {
            clearReaderTimer();
            readerState.notificationId = null;
        });

        // Active Staff Event Listeners
        if (activeStaffRefresh) {
            activeStaffRefresh.addEventListener('click', (event) => {
                event.preventDefault();
                if (activeStaffState.loading) return;
                loadActiveStaff(true);
            });
        }

        if (activeStaffSearch) {
            activeStaffSearch.addEventListener('input', (e) => {
                activeStaffState.search = e.target.value.trim();
                debounceLoadActiveStaff(500);
            });
        }

        if (activeStaffRoleFilter) {
            activeStaffRoleFilter.addEventListener('change', (e) => {
                activeStaffState.roleId = e.target.value ? parseInt(e.target.value) : null;
                debounceLoadActiveStaff();
            });
        }

        if (activeStaffTeamFilter) {
            activeStaffTeamFilter.addEventListener('change', (e) => {
                activeStaffState.teamId = e.target.value ? parseInt(e.target.value) : null;
                debounceLoadActiveStaff();
            });
        }

        if (activeStaffClearFilters) {
            activeStaffClearFilters.addEventListener('click', (e) => {
                e.preventDefault();
                activeStaffState.search = '';
                activeStaffState.roleId = null;
                activeStaffState.teamId = null;
                activeStaffState.currentPage = 1;
                if (activeStaffSearch) activeStaffSearch.value = '';
                if (activeStaffRoleFilter) activeStaffRoleFilter.value = '';
                if (activeStaffTeamFilter) activeStaffTeamFilter.value = '';
                loadActiveStaff();
            });
        }

        // Sortable columns
        document.querySelectorAll('.sortable').forEach(th => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => {
                const sortValue = th.getAttribute('data-sort');
                if (sortValue === activeStaffState.sortBy) {
                    activeStaffState.sortDir = activeStaffState.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    activeStaffState.sortBy = sortValue;
                    activeStaffState.sortDir = 'asc';
                }
                loadActiveStaff();
            });
        });

        scopeSelect.addEventListener('change', function() {
            toggleRecipientsVisibility();
            
            // Re-initialize mmSelect when dropdown becomes visible (width/position)
            if (scopeSelect.value === 'specific' && !recipientSelect.data('mm-select-initialized')) {
                initializeRecipientSelect();
            }
        });

        function initializeRecipientSelect() {
            console.log('🔧 Initializing recipient mmSelect dropdown...');
            
            recipientSelect.mmSelect({
                width: '100%',
                placeholder: recipientSelect.data('placeholder') || 'Select recipients',
                minimumInputLength: 0,  // Allow showing all users when clicking dropdown
                ajax: {
                    url: '/getassigneeajax',
                    dataType: 'json',
                    delay: 250,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    data(params) {
                        return {
                            likevalue: params.term || '',
                        };
                    },
                    processResults(data, params) {
                        // Handle both array and object responses with error handling
                        let items = [];
                        
                        if (Array.isArray(data)) {
                            items = data;
                        } else if (data && Array.isArray(data.data)) {
                            items = data.data;
                        } else if (data && data.error) {
                            console.error('Error loading recipients:', data.error);
                            return { results: [] };
                        } else {
                            console.warn('Unexpected response format:', data);
                            return { results: [] };
                        }
                        
                        return {
                            results: items.map((item) => ({
                                id: item.id,
                                text: item.assignee || item.agent_id || `Staff #${item.id}`,
                            })),
                        };
                    },
                    transport: function(params, success, failure) {
                        // Custom transport to handle authentication and errors properly
                        const requestParams = params.data;
                        const url = params.url + '?' + new URLSearchParams(requestParams).toString();
                        
                        fetch(url, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'include',
                        })
                        .then(response => {
                            if (!response.ok) {
                                if (response.status === 419) {
                                    throw new Error('CSRF token mismatch. Please refresh the page.');
                                } else if (response.status === 401) {
                                    throw new Error('Authentication required. Please log in again.');
                                } else {
                                    throw new Error(`HTTP ${response.status}: Unable to load staff list.`);
                                }
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('✅ Recipients loaded:', data);
                            success(data);
                        })
                        .catch(error => {
                            console.error('❌ Failed to load recipients:', error);
                            failure();
                            
                            // Show user-friendly error
                            if (error.message.includes('CSRF')) {
                                showFeedback('danger', 'Session expired. Please refresh the page.');
                            } else if (error.message.includes('Authentication')) {
                                showFeedback('danger', 'Please log in again to continue.');
                            }
                        });
                        
                        return { abort: () => {} };
                    },
                    cache: true,
                },
            });
            
            recipientSelect.data('mm-select-initialized', true);
        }

        toggleRecipientsVisibility();
        loadHistory();

        // If arrived via broadcast notification link (?batch=xxx), open that broadcast's details modal
        const urlParams = new URLSearchParams(window.location.search);
        const batchParam = urlParams.get('batch');
        const notificationParam = urlParams.get('notification');

        if (notificationParam) {
            openReceiverBroadcast(notificationParam);
            const url = new URL(window.location);
            url.searchParams.delete('batch');
            url.searchParams.delete('notification');
            window.history.replaceState({}, '', url);
        } else if (batchParam) {
            loadBroadcastDetails(batchParam, () => {
                detailModal.modal('show');
                // Clear ?batch= from URL to avoid re-opening on refresh
                const url = new URL(window.location);
                url.searchParams.delete('batch');
                window.history.replaceState({}, '', url);
            });
        }
        // Active users will load when tab is clicked (tab-based loading)
    })();
</script>
@endpush

@push('styles')
<style>
    .broadcast-subtitle {
        color: #4a5568;
    }

    /* Broadcast History Header Improvements */
    .broadcast-count-badge {
        background-color: rgba(255, 255, 255, 0.95) !important;
        color: #005792 !important;
        font-weight: 600 !important;
        border: 1px solid rgba(0, 87, 146, 0.2) !important;
        padding: 0.4rem 0.75rem !important;
    }

    .broadcast-refresh-btn {
        background-color: rgba(255, 255, 255, 0.15) !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        color: #ffffff !important;
        font-weight: 500 !important;
        transition: all 0.2s ease;
    }

    .broadcast-refresh-btn:hover {
        background-color: rgba(255, 255, 255, 0.95) !important;
        border-color: #ffffff !important;
        color: #005792 !important;
        transform: translateY(-1px);
    }

    .broadcast-refresh-btn:active {
        transform: translateY(0);
    }

    /* Message text readability */
    .broadcast-message-text {
        color: #495057 !important;
        font-size: 0.9375rem;
    }

    /* History tabs styling */
    #history-tabs .nav-link {
        color: #6c757d;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        margin: 0 0.25rem;
        transition: all 0.2s ease;
        font-size: 0.875rem;
        font-weight: 500;
    }

    #history-tabs .nav-link:hover {
        background-color: rgba(0, 87, 146, 0.1);
        color: #005792;
    }

    #history-tabs .nav-link.active {
        background-color: #005792;
        color: #ffffff;
    }

    #history-tabs .nav-link i {
        font-size: 0.875rem;
    }

    /* Delete button styling */
    .btn-outline-danger.btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .btn-outline-danger.btn-sm:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }

    /* TinyMCE Editor Styling */
    .tox-tinymce {
        border: 1px solid #e4e6fc !important;
        border-radius: 8px !important;
        overflow: hidden;
    }

    .tox .tox-toolbar {
        background: #f8f9fa !important;
        border-bottom: 1px solid #e4e6fc !important;
    }

    .tox .tox-edit-area__iframe {
        background-color: #ffffff !important;
    }

    .tox .tox-statusbar {
        border-top: 1px solid #e4e6fc !important;
        background: #f8f9fa !important;
    }

    #broadcast-char-count {
        font-size: 0.875rem;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    /* Broadcast message display in history */
    .broadcast-message-text {
        color: #495057 !important;
        font-size: 0.9375rem;
        line-height: 1.5;
    }

    .broadcast-message-text p {
        margin-bottom: 0.5rem;
    }

    .broadcast-message-text ul,
    .broadcast-message-text ol {
        margin-left: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .broadcast-message-text strong {
        font-weight: 600;
    }

    .broadcast-message-text a {
        color: #005792;
        text-decoration: underline;
    }

    /* Modal message display */
    #broadcast-detail-message {
        line-height: 1.6;
        color: #495057;
    }

    #broadcast-detail-message p {
        margin-bottom: 0.5rem;
    }

    #broadcast-detail-message ul,
    #broadcast-detail-message ol {
        margin-left: 1.25rem;
        margin-bottom: 0.5rem;
    }

    #broadcast-detail-message strong {
        font-weight: 600;
    }

    #broadcast-detail-message a {
        color: #005792;
        text-decoration: underline;
    }

    #broadcast-detail-title {
        font-size: 1.25rem;
        color: #2d3748;
        margin-bottom: 1rem;
    }

    /* ============================================
       MODERN ACTIVE USERS SECTION STYLING
       ============================================ */
    
    /* Card Container */
    .active-staff-modern-card {
        border-radius: 12px;
        overflow: hidden;
        transition: box-shadow 0.3s ease;
    }

    .active-staff-modern-card:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08) !important;
    }

    /* Header Section */
    .active-staff-header {
        background: linear-gradient(135deg, #005792 0%, #00BBF0 100%);
        padding: 2rem;
        color: #ffffff;
    }

    .active-staff-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .active-staff-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: #ffffff;
        margin: 0;
        letter-spacing: -0.5px;
    }

    .active-staff-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.9375rem;
        font-weight: 400;
        margin: 0;
    }

    .active-staff-stats {
        margin-top: 1rem;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .active-staff-count-badge {
        background: rgba(255, 255, 255, 0.95) !important;
        color: #28a745 !important;
        font-weight: 600;
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .status-dot-online {
        font-size: 0.5rem;
        color: #28a745;
        animation: pulse-dot 2s infinite;
    }

    @keyframes pulse-dot {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }

    .count-text {
        font-weight: 700;
        font-size: 1rem;
    }

    .active-staff-action-btn,
    .active-staff-refresh-btn {
        border-radius: 8px;
        font-weight: 600;
        padding: 0.5rem 1rem;
        transition: all 0.2s ease;
        border: none;
    }

    .active-staff-action-btn {
        background: rgba(255, 255, 255, 0.95);
        color: #005792;
    }

    .active-staff-action-btn:hover {
        background: #ffffff;
        color: #005792;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .active-staff-refresh-btn {
        background: rgba(255, 255, 255, 0.95);
        color: #005792;
    }

    .active-staff-refresh-btn:hover {
        background: #ffffff;
        color: #005792;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .active-staff-refresh-btn:active {
        transform: translateY(0);
    }

    /* Filters Section */
    .active-staff-filters {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .active-staff-search-input,
    .active-staff-filter-select {
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        background: #ffffff;
        transition: all 0.2s ease;
    }

    .active-staff-search-input:focus,
    .active-staff-filter-select:focus {
        border-color: #005792;
        box-shadow: 0 0 0 3px rgba(0, 87, 146, 0.1);
        outline: none;
    }

    .active-staff-clear-btn {
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        background: #ffffff;
        color: #6c757d;
        transition: all 0.2s ease;
    }

    .active-staff-clear-btn:hover {
        background: #f8f9fa;
        border-color: #dee2e6;
        color: #495057;
    }

    /* Table Styling */
    .active-staff-table-modern {
        margin: 0;
    }

    .active-staff-table-modern thead {
        background: #f8f9fa;
    }

    .active-staff-table-modern thead th {
        font-weight: 600;
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
        border-bottom: 2px solid #e9ecef;
        padding: 1rem 1.25rem;
        vertical-align: middle;
    }

    .active-staff-table-modern thead th .th-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .active-staff-table-modern thead th .th-content i:first-child {
        color: #005792;
        font-size: 0.875rem;
    }

    .active-staff-table-modern tbody td {
        padding: 1rem 1.25rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f3f5;
        transition: background-color 0.2s ease;
    }

    .active-staff-table-modern tbody tr {
        transition: all 0.2s ease;
    }

    .active-staff-table-modern tbody tr:hover {
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .active-staff-table-modern tbody tr:last-child td {
        border-bottom: none;
    }

    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.8;
            transform: scale(1.1);
        }
    }

    .status-indicator.online {
        background-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
    }

    .status-indicator.offline {
        background-color: #6c757d;
        box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.2);
        animation: none;
    }

    .user-avatar-wrapper {
        position: relative;
        flex-shrink: 0;
    }

    .user-avatar,
    .user-avatar-fallback {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .user-avatar-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #005792 0%, #00BBF0 100%);
        color: white;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .sortable {
        user-select: none;
        position: relative;
        cursor: pointer;
    }

    .sortable:hover {
        background-color: #f1f3f5;
    }

    .sort-icon {
        font-size: 0.75rem;
        opacity: 0.4;
        transition: opacity 0.2s ease;
        margin-left: auto;
    }

    .sortable:hover .sort-icon {
        opacity: 0.7;
    }

    .sortable.active .sort-icon {
        opacity: 1;
        color: #005792;
    }

    .team-badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
        border-radius: 6px;
        font-weight: 500;
        display: inline-block;
    }

    /* Empty State */
    .active-staff-empty-state {
        padding: 3rem 1rem;
    }

    .empty-state-icon {
        font-size: 3rem;
        color: #dee2e6;
        display: block;
    }

    .empty-state-message {
        color: #6c757d;
        font-size: 0.9375rem;
        margin-top: 0.5rem;
    }

    #active-staff-retry-btn {
        background-color: #005792 !important;
        color: #ffffff !important;
        border-color: #005792 !important;
    }

    #active-staff-retry-btn:hover {
        background-color: #00BBF0 !important;
        border-color: #00BBF0 !important;
        color: #ffffff !important;
    }

    /* Footer Styling */
    .active-staff-footer {
        background: #ffffff;
        border-top: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
    }

    .active-staff-footer .text-muted {
        color: #6c757d !important;
        font-size: 0.875rem;
    }

    .active-staff-footer .text-muted i {
        margin-right: 0.5rem;
        color: #005792;
    }

    /* Pagination Styling */
    #active-staff-pagination .pagination {
        margin-bottom: 0;
    }

    #active-staff-pagination .page-link {
        color: #005792;
        border-color: #dee2e6;
        border-radius: 6px;
        margin: 0 2px;
        padding: 0.5rem 0.75rem;
        transition: all 0.2s ease;
    }

    #active-staff-pagination .page-item.active .page-link {
        background-color: #005792;
        border-color: #005792;
        color: #ffffff;
    }

    #active-staff-pagination .page-link:hover {
        color: #00BBF0;
        background-color: #f8f9fa;
        border-color: #dee2e6;
    }

    /* Typography Improvements */
    .active-staff-table-modern strong {
        font-weight: 600;
        color: #212529;
    }

    .active-staff-table-modern .text-muted {
        color: #6c757d !important;
        font-size: 0.8125rem;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
        .active-staff-header {
            padding: 1.5rem;
        }

        .active-staff-title {
            font-size: 1.5rem;
        }

        .active-staff-icon-wrapper {
            width: 48px;
            height: 48px;
            font-size: 1.25rem;
        }
    }

    @media (max-width: 768px) {
        .active-staff-header {
            padding: 1.25rem;
        }

        .active-staff-title {
            font-size: 1.25rem;
        }

        .active-staff-subtitle {
            font-size: 0.875rem;
        }

        .active-staff-icon-wrapper {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .active-staff-stats {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .active-staff-count-badge {
            font-size: 0.8125rem;
            padding: 0.4rem 0.8rem;
        }

        .active-staff-filters .row {
            margin: 0;
        }

        .active-staff-filters .col-md-5,
        .active-staff-filters .col-md-3,
        .active-staff-filters .col-md-1 {
            margin-bottom: 0.75rem;
        }

        .user-avatar,
        .user-avatar-fallback {
            width: 32px;
            height: 32px;
            font-size: 0.75rem;
        }

        .active-staff-table-modern thead th,
        .active-staff-table-modern tbody td {
            padding: 0.75rem 0.5rem;
            font-size: 0.8125rem;
        }

        .active-staff-table-modern thead th {
            font-size: 0.75rem;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
        }

        .active-staff-footer {
            padding: 0.75rem 1rem;
            flex-direction: column;
            align-items: flex-start !important;
            gap: 1rem !important;
        }
    }

    @media (max-width: 576px) {
        .active-staff-header {
            padding: 1rem;
        }

        .active-staff-filters .row > div {
            margin-bottom: 0.5rem;
        }

        .active-staff-action-btn span,
        .active-staff-refresh-btn span {
            display: none;
        }
    }

    #active-staff-last-refresh {
        color: #6c757d;
        font-weight: 500;
    }
</style>
@endpush

