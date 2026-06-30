@extends('layouts.crm_client_detail')
@section('title', 'Signature Dashboard')

@section('styles')
<style>
    .signature-dashboard {
        padding: 20px;
    }
    
    .dashboard-header {
        margin-bottom: 30px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 10px;
    }
    
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
        border-radius: 10px;
        color: white;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .stat-card.sent {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-card.pending {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    .stat-card.signed {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    .stat-card.overdue {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    
    .stat-card h3 {
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 10px;
        opacity: 0.9;
    }
    
    .stat-card .number {
        font-size: 32px;
        font-weight: 700;
    }
    
    .tabs-container {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .nav-tabs {
        border-bottom: 2px solid #e9ecef;
        padding: 0 20px;
        background: #f8f9fa;
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        padding: 15px 20px;
        font-weight: 500;
        position: relative;
    }
    
    .nav-tabs .nav-link:hover {
        color: #667eea;
        border-color: transparent;
    }
    
    .nav-tabs .nav-link.active {
        color: #667eea;
        background: transparent;
        border-color: transparent;
    }
    
    .nav-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: #667eea;
    }
    
    .filter-bar {
        padding: 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .filter-bar select,
    .filter-bar input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .documents-table {
        width: 100%;
        padding: 20px;
    }
    
    .documents-table table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 10px;
    }
    
    .documents-table th {
        text-align: left;
        padding: 12px;
        color: #6c757d;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .documents-table td {
        padding: 15px 12px;
        background: #f8f9fa;
        vertical-align: middle;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .document-link {
        color: #2c3e50;
        text-decoration: none;
        transition: color 0.2s ease;
        cursor: pointer;
        display: block;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .document-link:hover {
        color: #667eea;
        text-decoration: none;
    }
    
    .document-link strong {
        font-weight: 600;
    }
    
    .documents-table tr {
        transition: all 0.2s ease;
    }
    
    .documents-table tr:hover td {
        background: #e9ecef;
    }
    
    .documents-table td:first-child {
        border-radius: 8px 0 0 8px;
    }
    
    .documents-table td:last-child {
        border-radius: 0 8px 8px 0;
    }
    
    .badge-status {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-draft {
        background: #6c757d;
        color: white;
    }
    
    .badge-sent {
        background: #ffc107;
        color: #000;
    }
    
    .badge-signed {
        background: #28a745;
        color: white;
    }
    
    .association-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        background: #e7f3ff;
        color: #0066cc;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .association-chip i {
        font-size: 10px;
    }
    
    .visibility-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .badge-owner {
        background: #e3f2fd;
        color: #1565c0;
    }
    
    .badge-signer {
        background: #fff3e0;
        color: #e65100;
    }
    
    .badge-associated {
        background: #f3e5f5;
        color: #6a1b9a;
    }
    
    .badge-admin {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .badge-visible {
        background: #fce4ec;
        color: #c2185b;
    }
    
    .btn-primary-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }
    
    .btn-primary-custom:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        color: white;
    }
    
    .overdue-indicator {
        color: #dc3545;
        font-weight: 600;
        font-size: 11px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
</style>
@endsection

@section('content')
<div class="signature-dashboard">
    <!-- Header -->
    <div class="dashboard-header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>📝 Signature Dashboard</h1>
                <p style="color: #6c757d;">Manage and track document signatures</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="{{ route('documents.create') }}" class="btn-primary-custom">
                    @icon('fa-plus') Send New Document
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-cards">
        <div class="stat-card sent">
            <h3>My Documents</h3>
            <div class="number">{{ $counts['sent_by_me'] ?? 0 }}</div>
        </div>
        <div class="stat-card pending">
            <h3>Pending Signature</h3>
            <div class="number">{{ $counts['pending'] ?? 0 }}</div>
        </div>
        <div class="stat-card signed">
            <h3>Signed</h3>
            <div class="number">{{ $counts['signed'] ?? 0 }}</div>
        </div>
        @if($counts['overdue'] ?? 0 > 0)
        <div class="stat-card overdue">
            <h3>Overdue</h3>
            <div class="number">{{ $counts['overdue'] ?? 0 }}</div>
        </div>
        @endif
        {{-- Previously this card was hidden for administrators (role 1). Now shown for all roles so the org-wide total is visible. --}}
        {{-- @if($staff->role !== 1) --}}
        <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
            <h3>Visible to Me</h3>
            <div class="number">{{ $counts['visible_to_me'] ?? 0 }}</div>
        </div>
        {{-- @endif --}}
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    @endif

    <!-- Tabs -->
    <div class="tabs-container">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ !request('tab') || request('tab') == 'sent_by_me' ? 'active' : '' }}" 
                   href="{{ route('signatures.index', ['tab' => 'sent_by_me']) }}">
                    Sent by Me
                </a>
            </li>
            @if($staff->role === 1)
            <li class="nav-item">
                <a class="nav-link {{ request('tab') == 'all' ? 'active' : '' }}" 
                   href="{{ route('signatures.index', ['tab' => 'all']) }}">
                    All Documents
                </a>
            </li>
            @endif
            <li class="nav-item">
                <a class="nav-link {{ request('tab') == 'pending' ? 'active' : '' }}" 
                   href="{{ route('signatures.index', ['tab' => 'pending']) }}">
                    Pending
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('tab') == 'signed' ? 'active' : '' }}" 
                   href="{{ route('signatures.index', ['tab' => 'signed']) }}">
                    Signed
                </a>
            </li>
        </ul>

        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" action="{{ route('signatures.index') }}" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; width: 100%;">
                <input type="hidden" name="tab" value="{{ request('tab') }}">
                
                <!-- Visibility Scope Filter -->
                <div style="display: flex; gap: 5px; padding: 4px; background: #e9ecef; border-radius: 8px;">
                    <a href="{{ route('signatures.index', array_merge(request()->except('scope'), ['scope' => 'team'])) }}" 
                       class="btn btn-sm {{ !request('scope') || request('scope') == 'team' ? 'btn-primary' : 'btn-light' }}"
                       style="padding: 6px 15px; font-size: 13px;">
                        👥 My Documents
                    </a>
                    @if($staff->role === 1)
                    <a href="{{ route('signatures.index', array_merge(request()->except('scope'), ['scope' => 'organization'])) }}" 
                       class="btn btn-sm {{ request('scope') == 'organization' ? 'btn-primary' : 'btn-light' }}"
                       style="padding: 6px 15px; font-size: 13px;">
                        🌐 Organization
                    </a>
                    @endif
                </div>
                
                <select name="association" class="form-control" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="associated" {{ request('association') == 'associated' ? 'selected' : '' }}>Associated</option>
                    <option value="adhoc" {{ request('association') == 'adhoc' ? 'selected' : '' }}>Ad-hoc</option>
                </select>
                
                <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                    <option value="signed" {{ request('status') == 'signed' ? 'selected' : '' }}>Signed</option>
                </select>
                
                <input type="text" name="search" class="form-control" placeholder="Search documents..." 
                       value="{{ request('search') }}" style="flex: 1; min-width: 200px;">
                
                <button type="submit" class="btn btn-primary">
                    @icon('fa-search') Search
                </button>
                
                @if(request()->anyFilled(['association', 'status', 'search', 'scope']))
                <a href="{{ route('signatures.index', ['tab' => request('tab')]) }}" class="btn btn-secondary">
                    Clear Filters
                </a>
                @endif
            </form>
        </div>

        <!-- Documents Table -->
        <div class="documents-table">
            @if($documents->count() > 0)
            
            <!-- Bulk Actions Bar -->
            <div id="bulk-actions-bar" style="display: none; padding: 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 15px;">
                <span id="selected-count" style="font-weight: 600; margin-right: 15px;">0 selected</span>
                <button type="button" onclick="bulkResend()" class="btn btn-sm btn-warning">
                    @icon('fa-bell') Send Reminders
                </button>
                <button type="button" onclick="bulkVoid()" class="btn btn-sm btn-danger">
                    @icon('fa-ban') Void
                </button>
                <button type="button" onclick="clearSelection()" class="btn btn-sm btn-light">
                    Clear Selection
                </button>
            </div>
            
            <form id="bulk-action-form" method="POST">
                @csrf
                <input type="hidden" name="ids" id="bulk-ids">
                <input type="hidden" name="reason" id="bulk-reason">
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                        </th>
                        <th style="width: 31%;">Document</th>
                        <th style="width: 10%;">Visibility</th>
                        <th style="width: 16%;">Signer</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 19%;">Association</th>
                        <th style="width: 12%;">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $doc)
                    <tr>
                        <td>
                            <input type="checkbox" class="doc-checkbox" value="{{ $doc->id }}" onchange="updateBulkActions()">
                        </td>
                        <td>
                            <a href="{{ route('signatures.show', $doc->id) }}" class="document-link">
                                <strong>{{ $doc->display_title }}</strong>
                            </a>
                            @if($doc->is_overdue)
                            <br><span class="overdue-indicator">⚠️ OVERDUE</span>
                            @endif
                        </td>
                        <td>
                            <span class="visibility-badge {{ $doc->visibility_badge['class'] }}" 
                                  title="{{ $doc->visibility_badge['label'] }}">
                                {{ $doc->visibility_badge['icon'] }} {{ $doc->visibility_badge['label'] }}
                            </span>
                        </td>
                        <td>
                            {{ $doc->primary_signer_email ?? 'N/A' }}
                            @if($doc->signer_count > 1)
                            <br><small>(+{{ $doc->signer_count - 1 }} more)</small>
                            @endif
                        </td>
                        <td>
                            <span class="badge-status badge-{{ $doc->status }}">
                                {{ ucfirst($doc->status) }}
                            </span>
                        </td>
                        <td>
                            @if(($doc->client_id && $doc->client) || ($doc->lead_id && $doc->lead))
                                @if($doc->client_id && $doc->client)
                                <a href="{{ route('clients.detail', $doc->client_id) }}" class="association-chip">
                                    @icon('fa-user')
                                    Client: {{ $doc->client->first_name }} {{ $doc->client->last_name }}
                                </a>
                                @elseif($doc->lead_id && $doc->lead)
                                <a href="{{ route('clients.detail', ['client_id' => base64_encode(convert_uuencode((string)(int) $doc->lead_id))]) }}" class="association-chip">
                                    @icon('fa-user-tag')
                                    Lead: {{ $doc->lead->first_name }} {{ $doc->lead->last_name }}
                                </a>
                                @endif
                            @else
                                <span style="color: #6c757d; font-size: 12px;">Ad-hoc</span>
                            @endif
                        </td>
                        <td>
                            {{ $doc->created_at->format('M d, Y') }}<br>
                            <small style="color: #6c757d;">{{ $doc->created_at->diffForHumans() }}</small>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div style="margin-top: 20px;">
                {{ $documents->appends(request()->query())->links() }}
            </div>
            @else
            <div class="empty-state">
                @icon('fa-inbox')
                <h3>No documents found</h3>
                <p>Start by sending a new document for signature</p>
                <a href="{{ route('documents.create') }}" class="btn-primary-custom" style="margin-top: 20px;">
                    @icon('fa-plus') Send New Document
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Attach Document Modal -->
<div class="modal fade" id="attachModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="attachForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        @icon('fa-link') Attach Document to Client/Lead
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Document</label>
                        <input type="text" id="attachDocTitle" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Attach To <span style="color: #dc3545;">*</span></label>
                        <select class="form-control" id="attachEntityType" name="entity_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="client">Client</option>
                            <option value="lead">Lead</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="entitySelectGroup" style="display: none;">
                        <label id="entitySelectLabel">Select Entity <span style="color: #dc3545;">*</span></label>
                        <select class="form-control" id="attachEntityId" name="entity_id" required>
                            <option value="">-- Select --</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Note (Optional)</label>
                        <textarea class="form-control" name="note" rows="3" placeholder="Add a note about this attachment..."></textarea>
                        <small class="form-text text-muted">This note will appear in the audit trail</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        @icon('fa-check') Attach Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Data from controller - Clean and simple
const clients = @json($clients ?? []);
const leads = @json($leads ?? []);
let currentDocId = null;

console.log('✅ Dashboard script loaded. Clients:', clients.length, 'Leads:', leads.length);

// Define openAttachModal globally - available immediately
function openAttachModal(docId, docTitle) {
    console.log('📝 openAttachModal called:', docId, docTitle);
    
    try {
        currentDocId = docId;
        
        // Get modal elements
        const docTitleEl = document.getElementById('attachDocTitle');
        const entityTypeEl = document.getElementById('attachEntityType');
        const entitySelectGroupEl = document.getElementById('entitySelectGroup');
        const attachEntityIdEl = document.getElementById('attachEntityId');
        const attachFormEl = document.getElementById('attachForm');
        
        // Validate elements exist
        if (!docTitleEl || !entityTypeEl || !entitySelectGroupEl || !attachEntityIdEl || !attachFormEl) {
            console.error('❌ Required modal elements not found');
            alert('Error: Modal elements not found. Please refresh the page and try again.');
            return;
        }
        
        // Reset and populate modal
        docTitleEl.value = docTitle;
        entityTypeEl.value = '';
        entitySelectGroupEl.style.display = 'none';
        attachEntityIdEl.innerHTML = '<option value="">-- Select --</option>';
        attachFormEl.action = '{{ url("/signatures") }}/' + docId + '/associate';
        
        // Show modal
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#attachModal').modal('show');
            console.log('✅ Modal opened (jQuery)');
        } else if (typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(document.getElementById('attachModal'));
            modal.show();
            console.log('✅ Modal opened (Bootstrap 5)');
        } else {
            console.error('❌ No modal library found');
            alert('Error: Modal library not found. Please refresh the page.');
        }
    } catch (error) {
        console.error('❌ Error in openAttachModal:', error);
        alert('Error opening attachment modal. Please try again.');
    }
}

// Wait for DOM to be fully loaded for event listeners
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM ready, setting up event listeners');
    
    // Set up entity type change listener
    const attachEntityTypeEl = document.getElementById('attachEntityType');
    if (attachEntityTypeEl) {
        attachEntityTypeEl.addEventListener('change', function() {
            const type = this.value;
            const selectGroup = document.getElementById('entitySelectGroup');
            const select = document.getElementById('attachEntityId');
            const label = document.getElementById('entitySelectLabel');
            
            if (!selectGroup || !select || !label) {
                console.error('❌ Required elements for entity selection not found');
                return;
            }
            
            if (!type) {
                selectGroup.style.display = 'none';
                return;
            }
            
            selectGroup.style.display = 'block';
            label.textContent = 'Select ' + (type === 'client' ? 'Client' : 'Lead') + ' ';
            
            const data = type === 'client' ? clients : leads;
            select.innerHTML = '<option value="">-- Select --</option>';
            
            if (data && data.length > 0) {
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = `${item.first_name} ${item.last_name} (${item.email})`;
                    select.appendChild(option);
                });
                console.log(`✅ Loaded ${data.length} ${type}s into dropdown`);
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No ' + (type === 'client' ? 'clients' : 'leads') + ' available';
                select.appendChild(option);
                console.warn(`⚠️ No ${type}s available`);
            }
        });
        console.log('✅ Entity type change listener attached');
    } else {
        console.error('❌ attachEntityType element not found');
    }
});

// Bulk Actions
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.doc-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.doc-checkbox:checked');
    const count = checkboxes.length;
    const bulkBar = document.getElementById('bulk-actions-bar');
    const countSpan = document.getElementById('selected-count');
    
    if (count > 0) {
        bulkBar.style.display = 'block';
        countSpan.textContent = `${count} selected`;
    } else {
        bulkBar.style.display = 'none';
        document.getElementById('select-all').checked = false;
    }
}

function getSelectedIds() {
    const checkboxes = document.querySelectorAll('.doc-checkbox:checked');
    return Array.from(checkboxes).map(cb => cb.value);
}

function bulkResend() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    
    if (confirm(`Send reminders for ${ids.length} document(s)?`)) {
        const form = document.getElementById('bulk-action-form');
        form.action = '{{ route("signatures.bulk-resend") }}';
        document.getElementById('bulk-ids').value = JSON.stringify(ids);
        form.submit();
    }
}

function bulkVoid() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    
    const reason = prompt('Reason for voiding (optional):');
    if (reason !== null) {
        const form = document.getElementById('bulk-action-form');
        form.action = '{{ route("signatures.bulk-void") }}';
        document.getElementById('bulk-ids').value = JSON.stringify(ids);
        document.getElementById('bulk-reason').value = reason;
        form.submit();
    }
}

function clearSelection() {
    document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all').checked = false;
    updateBulkActions();
}

// Verify function is defined
console.log('✅ openAttachModal defined:', typeof openAttachModal === 'function');
</script>
@endsection

