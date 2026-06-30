@extends('layouts.crm_client_detail')
@section('title', 'Document Details')

@section('styles')
<style>
    .document-detail-container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 20px;
    }
    
    .page-header {
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 10px;
    }
    
    .breadcrumb {
        background: transparent;
        padding: 0;
        margin: 0;
        font-size: 14px;
    }
    
    .header-actions {
        display: flex;
        gap: 10px;
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    
    .main-content-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }
    
    .sidebar-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 20px;
        height: fit-content;
    }
    
    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .section-title i {
        color: #667eea;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f8f9fa;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 14px;
    }
    
    .info-value {
        color: #2c3e50;
        font-size: 14px;
        text-align: right;
    }
    
    .status-badge {
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-badge.draft {
        background: #6c757d;
        color: white;
    }
    
    .status-badge.sent {
        background: #ffc107;
        color: #000;
    }
    
    .status-badge.signed {
        background: #28a745;
        color: white;
    }
    
    .status-badge.signature_placed {
        background: #17a2b8;
        color: white;
    }
    
    .status-badge.void {
        background: #dc3545;
        color: white;
    }
    
    .status-badge.archived {
        background: #6c757d;
        color: white;
    }
    
    .status-badge.opened-not-signed {
        background: #ffc107;
        color: #000;
    }
    
    .status-badge.ready-to-send {
        background: #17a2b8;
        color: white;
    }
    
    .status-badge.first-reminder {
        background: #fd7e14;
        color: white;
    }
    
    .status-badge.second-reminder {
        background: #e83e8c;
        color: white;
    }
    
    .status-badge.final-reminder {
        background: #dc3545;
        color: white;
    }
    
    .status-badge:not(.draft):not(.sent):not(.signed):not(.signature_placed):not(.void):not(.archived):not(.opened-not-signed):not(.ready-to-send):not(.first-reminder):not(.second-reminder):not(.final-reminder) {
        background: #6c757d;
        color: white;
    }
    
    .priority-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .priority-badge.low {
        background: #d1ecf1;
        color: #0c5460;
    }
    
    .priority-badge.normal {
        background: #d4edda;
        color: #155724;
    }
    
    .priority-badge.high {
        background: #f8d7da;
        color: #721c24;
    }
    
    .signer-list {
        margin-top: 20px;
    }
    
    .signer-item {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #667eea;
    }
    
    .signer-item.signed {
        border-left-color: #28a745;
    }
    
    .signer-item.pending {
        border-left-color: #ffc107;
    }
    
    .signer-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .signer-name {
        font-weight: 600;
        font-size: 16px;
        color: #2c3e50;
    }
    
    .signer-email {
        color: #6c757d;
        font-size: 14px;
    }
    
    .signer-status {
        padding: 5px 12px;
        border-radius: 15px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .signer-status.signed {
        background: #28a745;
        color: white;
    }
    
    .signer-status.opened {
        background: #ffc107;
        color: #000;
    }
    
    .signer-status.pending {
        background: #6c757d;
        color: white;
    }
    
    .signer-actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
    }
    
    .btn {
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .btn-primary {
        background: #667eea;
        color: white;
    }
    
    .btn-primary:hover {
        background: #5568d3;
    }
    
    .btn-warning {
        background: #ffc107;
        color: #000;
    }
    
    .btn-warning:hover {
        background: #e0a800;
    }
    
    .btn-success {
        background: #28a745;
        color: white;
    }
    
    .btn-success:hover {
        background: #218838;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .association-info {
        background: #e7f3ff;
        padding: 15px;
        border-radius: 8px;
        border-left: 4px solid #0066cc;
        margin-bottom: 20px;
    }
    
    .association-info a {
        color: #0066cc;
        font-weight: 600;
        text-decoration: none;
    }
    
    .association-info a:hover {
        text-decoration: underline;
    }
    
    .timeline-container {
        padding-left: 10px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .timeline-item {
        margin-bottom: 20px;
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    
    .timeline-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 30px;
        bottom: -20px;
        width: 2px;
        background: #e9ecef;
    }
    
    .timeline-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        flex-shrink: 0;
        position: relative;
        z-index: 1;
    }
    
    .timeline-item.created .timeline-icon {
        background: #6c757d;
        color: white;
    }
    
    .timeline-item.signature_placed .timeline-icon {
        background: #17a2b8;
        color: white;
    }
    
    .timeline-item.sent .timeline-icon {
        background: #ffc107;
        color: #000;
    }
    
    .timeline-item.signer_added .timeline-icon {
        background: #007bff;
        color: white;
    }
    
    .timeline-item.opened .timeline-icon {
        background: #fd7e14;
        color: white;
    }
    
    .timeline-item.reminder .timeline-icon {
        background: #e83e8c;
        color: white;
    }
    
    .timeline-item.signed .timeline-icon {
        background: #28a745;
        color: white;
    }
    
    .timeline-content {
        flex: 1;
        min-width: 0;
    }
    
    .timeline-date {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 4px;
        font-weight: 500;
    }
    
    .timeline-text {
        font-size: 14px;
        color: #495057;
        margin-bottom: 2px;
        line-height: 1.4;
    }
    
    .timeline-time {
        font-size: 11px;
        color: #adb5bd;
        font-style: italic;
    }
    
    .overdue-warning {
        background: #fff3cd;
        border: 1px solid #ffc107;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .overdue-warning i {
        color: #ff6b6b;
        font-size: 24px;
    }
    
    .match-alert {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border-left: 4px solid #4caf50;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: none;
        animation: slideDown 0.3s ease;
    }
    
    .match-alert.show {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .match-alert-icon {
        font-size: 32px;
        color: #4caf50;
    }
    
    .match-alert-content {
        flex: 1;
    }
    
    .match-alert-title {
        font-weight: 600;
        color: #2e7d32;
        margin-bottom: 5px;
    }
    
    .match-alert-text {
        color: #558b2f;
        font-size: 14px;
    }
    
    .match-alert-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-match-accept {
        background: #4caf50;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    
    .btn-match-dismiss {
        background: #757575;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-step {
        transition: all 0.3s ease;
    }

    .search-results {
        margin-top: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .search-results h6 {
        margin-bottom: 10px;
        color: #495057;
        font-weight: 600;
    }

    .match-item {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .match-item:hover {
        border-color: #667eea;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.1);
    }

    .match-item.selected {
        border-color: #667eea;
        background: #e8ecff;
    }

    .match-item:last-child {
        margin-bottom: 0;
    }

    .match-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .match-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
    }

    .match-type {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .match-type.client {
        background: #d1ecf1;
        color: #0c5460;
    }

    .match-type.lead {
        background: #fff3cd;
        color: #856404;
    }

    .match-email {
        color: #6c757d;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .match-matters {
        margin-top: 8px;
    }

    .match-matters-title {
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 5px;
    }

    .matter-item {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        color: #6c757d;
        margin-bottom: 3px;
        display: inline-block;
        margin-right: 5px;
    }
    
    .matter-item.clickable {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }
    
    .matter-item.clickable:hover {
        background: #e9ecef;
        border-color: #667eea;
        color: #667eea;
    }
    
    .matter-item.selected {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .no-matches {
        margin-top: 15px;
    }

    .no-matches .alert {
        margin-bottom: 0;
    }

    .step-indicator {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    .step-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #e9ecef;
        margin: 0 5px;
        transition: all 0.3s ease;
    }

    .step-dot.active {
        background: #667eea;
    }

    .step-dot.completed {
        background: #28a745;
    }

    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
        
        .page-header {
            flex-direction: column;
            gap: 15px;
        }
    }
</style>
@endsection

@section('content')
<div class="document-detail-container">
    <div class="page-header">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('signatures.index') }}">Signature Dashboard</a></li>
                    <li class="breadcrumb-item active">Document Details</li>
                </ol>
            </nav>
            <h1>{{ $document->display_title }}</h1>
        </div>
        <div class="header-actions">
            <a href="{{ route('signatures.index') }}" class="btn btn-secondary">
                @icon('fa-arrow-left') Back
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-bs-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-bs-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    @endif

    <!-- Association Info -->
    @if(($document->client_id && $document->client) || ($document->lead_id && $document->lead))
    <div class="association-info">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>@icon('fa-link') Associated with:</strong>
                @if($document->client_id && $document->client)
                <a href="{{ route('clients.detail', $document->client_id) }}">
                    Client: {{ $document->client->first_name }} {{ $document->client->last_name }}
                </a>
                @elseif($document->lead_id && $document->lead)
                <a href="{{ route('clients.detail', ['client_id' => base64_encode(convert_uuencode((string)(int) $document->lead_id))]) }}">
                    Lead: {{ $document->lead->first_name }} {{ $document->lead->last_name }}
                </a>
                @endif
            </div>
            @if(auth('admin')->user()->role === 1)
            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDetach()">
                @icon('fa-unlink') Detach
            </button>
            @endif
        </div>
    </div>
    @elseif($document->status === 'signed')
    <div class="association-info" style="background: #fff3cd; border-left: 4px solid #ffc107;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>@icon('fa-exclamation-triangle', ['style' => 'color: #ffc107;']) Not Associated</strong>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #856404;">
                    This signed document is not associated with any client or lead
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn btn-sm btn-primary" onclick="openAttachModal()">
                    @icon('fa-link') Attach Document
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Attach Document Modal -->
    <div class="modal fade" id="attachModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
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
                            <input type="text" class="form-control" value="{{ $document->display_title }}" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Signer Email <span style="color: #dc3545;">*</span></label>
                            <div class="input-group">
                                <input type="email" class="form-control" id="signerEmail" placeholder="Enter signer email address" value="{{ $document->signers->first()->email ?? '' }}" onkeypress="if(event.key==='Enter') lookupSigner()">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" onclick="lookupSigner()" id="lookupBtn">
                                        @icon('fa-search') Lookup
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Enter the signer's email to automatically find matching client or lead</small>
                        </div>
                        
                        <div id="lookupResults" style="display: none;">
                            <div class="form-group">
                                <label>Found Match</label>
                                <div class="alert alert-info" id="matchInfo">
                                    <!-- Match info will be populated here -->
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Entity Type <span style="color: #dc3545;">*</span></label>
                                <select class="form-control" id="entityType" name="entity_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="client">Client</option>
                                    <option value="lead">Lead</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="matterSelection" style="display: none;">
                                <label>Select Matter <span style="color: #dc3545;">*</span></label>
                                <select class="form-control" id="matterId" name="matter_id">
                                    <option value="">-- Select Matter --</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Document Category <span style="color: #dc3545;">*</span></label>
                                <select class="form-control" id="docCategory" name="doc_category" required>
                                    <option value="">-- Select Category --</option>
                                    <option value="visa" data-for="client">Visa Documents (Client)</option>
                                    <option value="personal" data-for="lead">Personal Documents (Lead)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Note (Optional)</label>
                                <textarea class="form-control" name="note" rows="3" placeholder="Add a note about this attachment..."></textarea>
                                <small class="form-text text-muted">This note will appear in the audit trail</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="attachBtn" disabled>
                            @icon('fa-check') Attach Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="detail-grid">
        <!-- Main Content -->
        <div>
            <!-- Document Info Card -->
            <div class="main-content-card">
                <h2 class="section-title">
                    @icon('fa-file-alt')
                    Document Information
                </h2>

                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        @php
                            $statusInfo = $document->getStatusInfo();
                        @endphp
                        <span class="status-badge {{ $statusInfo['class'] }}">
                            {{ $statusInfo['text'] }}
                        </span>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">File Name</span>
                    <span class="info-value">{{ $document->file_name }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Created By</span>
                    <span class="info-value">
                        {{ $document->creator ? $document->creator->first_name . ' ' . $document->creator->last_name : 'N/A' }}
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">Created At</span>
                    <span class="info-value">
                        {{ $document->created_at->format('M d, Y g:i A') }}<br>
                        <small style="color: #6c757d;">{{ $document->created_at->diffForHumans() }}</small>
                    </span>
                </div>

                @if($document->status === 'signed' && $document->signed_doc_link)
                <div style="margin-top: 20px; text-align: center;">
                    <a href="{{ route('documents.download.signed', $document->id) }}" class="btn btn-success btn-lg">
                        @icon('fa-download') Download Signed Document
                    </a>
                </div>
                @endif
            </div>

            <!-- Signers Card -->
            <div class="main-content-card" style="margin-top: 20px;">
                <h2 class="section-title">
                    @icon('fa-users')
                    Signers ({{ $document->signers->count() }})
                </h2>

                <div class="signer-list">
                    @forelse($document->signers as $signer)
                    <div class="signer-item {{ $signer->status }}">
                        <div class="signer-header">
                            <div>
                                <div class="signer-name">{{ $signer->name }}</div>
                                <div class="signer-email">{{ $signer->email }}</div>
                            </div>
                            <span class="signer-status {{ $signer->opened_at && $signer->status === 'pending' ? 'opened' : $signer->status }}">
                                @if($signer->status === 'signed')
                                    @icon('fa-check-circle') Signed
                                @elseif($signer->status === 'cancelled')
                                    @icon('fa-times-circle') Cancelled
                                @elseif($signer->opened_at && $signer->status === 'pending')
                                    @icon('fa-eye') Opened - Not Signed
                                @else
                                    @icon('fa-clock') Pending
                                @endif
                            </span>
                        </div>

                        @if($signer->opened_at)
                        <div style="margin-top: 10px; font-size: 13px; color: #6c757d;">
                            @icon('fa-eye') Opened: {{ $signer->opened_at->format('M d, Y g:i A') }}
                        </div>
                        @endif

                        @if($signer->signed_at)
                        <div style="margin-top: 5px; font-size: 13px; color: #6c757d;">
                            @icon('fa-check') Signed: {{ $signer->signed_at->format('M d, Y g:i A') }}
                        </div>
                        @endif

                        @if($signer->cancelled_at)
                        <div style="margin-top: 5px; font-size: 13px; color: #dc3545;">
                            @icon('fa-times') Cancelled: {{ $signer->cancelled_at->format('M d, Y g:i A') }}
                        </div>
                        @endif

                        @if($signer->status === 'pending')
                        <div class="signer-actions">
                            <form action="{{ route('signatures.reminder', $document->id) }}" method="POST" style="display: inline;">
                                @csrf
                                <input type="hidden" name="signer_id" value="{{ $signer->id }}">
                                <button type="submit" class="btn btn-warning" 
                                        {{ $signer->reminder_count >= 3 ? 'disabled' : '' }}>
                                    @icon('fa-bell') 
                                    Send Reminder ({{ $signer->reminder_count }}/3)
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-secondary" onclick="copySigningLink('{{ url("/sign/{$document->id}/{$signer->token}") }}')">
                                @icon('fa-copy') Copy Link
                            </button>

                            <form action="{{ route('signatures.cancel', $document->id) }}" method="POST" style="display: inline;" onsubmit="return confirmCancelSignature()">
                                @csrf
                                <input type="hidden" name="signer_id" value="{{ $signer->id }}">
                                <button type="submit" class="btn btn-danger">
                                    @icon('fa-times') Cancel Signature
                                </button>
                            </form>
                        </div>
                        
                        <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
                            @if($signer->last_reminder_sent_at)
                                @icon('fa-clock') Last reminder sent: 
                                <strong>{{ $signer->last_reminder_sent_at->format('M d, Y g:i A') }}</strong>
                                <span style="color: #9ca3af;">({{ $signer->last_reminder_sent_at->diffForHumans() }})</span>
                            @else
                                @icon('fa-info-circle') No reminders sent yet
                            @endif
                        </div>
                        @endif
                    </div>
                    @empty
                    <div style="text-align: center; padding: 40px; color: #6c757d;">
                        @icon('fa-user-slash', ['style' => 'font-size: 32px; margin-bottom: 10px;'])
                        <p>No signers added yet</p>
                        <div style="margin-top: 20px;">
                            <button type="button" class="btn btn-primary" onclick="openAddSignerModal()">
                                @icon('fa-user-plus') Add Signer
                            </button>
                        </div>
                    </div>
                    @endforelse
                </div>
                
                <!-- Add Another Signer Button -->
                @if($document->signers->count() > 0)
                <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn btn-outline-primary" onclick="openAddSignerModal()">
                        @icon('fa-user-plus') Add Another Signer
                    </button>
                </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Quick Actions Card -->
            <div class="sidebar-card">
                <h3 class="section-title" style="font-size: 16px;">
                    @icon('fa-bolt')
                    Quick Actions
                </h3>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @if($document->status === 'signed')
                    <a href="{{ route('documents.download.signed', $document->id) }}" class="btn btn-success btn-block">
                        @icon('fa-download') Download
                    </a>
                    @endif
                    
                    <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-primary btn-block">
                        @icon('fa-edit') Edit Signature Placement
                    </a>
                    
                    @if($document->signers()->where('status', 'pending')->count() > 0)
                        @if($document->status === 'sent')
                        <div style="display: flex; align-items: center; padding: 8px 16px; background: #e8f5e9; border-radius: 6px; color: #2e7d32; font-size: 14px; text-align: center;">
                            @icon('fa-check-circle', ['style' => 'margin-right: 8px;'])
                            Document sent for signature
                        </div>
                        @else
                        <form action="{{ route('signatures.send', $document->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block" 
                                    onclick="return confirm('Are you sure you want to send this document for signature? This will send signing links to all pending signers.')">
                                @icon('fa-paper-plane') Send for Signature
                            </button>
                        </form>
                        @endif
                    @else
                    <div style="display: flex; align-items: center; padding: 8px 16px; background: #f8f9fa; border-radius: 6px; color: #6c757d; font-size: 14px; text-align: center;">
                        @icon('fa-info-circle', ['style' => 'margin-right: 8px;'])
                        No pending signers to send to
                    </div>
                    @endif
                </div>
            </div>

            <!-- Activity Timeline Card -->
            <div class="sidebar-card" style="margin-top: 20px;">
                <h3 class="section-title" style="font-size: 16px;">
                    @icon('fa-history')
                    Activity Timeline
                </h3>

                <div class="timeline-container">
                    @php
                        $activities = collect();
                        
                        // Document creation
                        $activities->push([
                            'date' => $document->created_at,
                            'text' => 'Document created',
                            'icon' => 'fas fa-file-plus',
                            'type' => 'created'
                        ]);
                        
                        // Signature fields placed
                        if ($document->status === 'signature_placed' || $document->signatureFields->count() > 0) {
                            $signatureFieldsDate = $document->signatureFields->min('created_at') ?? $document->updated_at;
                            $activities->push([
                                'date' => $signatureFieldsDate,
                                'text' => 'Signature fields placed',
                                'icon' => 'fas fa-edit',
                                'type' => 'signature_placed'
                            ]);
                        }
                        
                        // Document sent for signature
                        if ($document->status === 'sent' || $document->status === 'signed') {
                            $sentDate = $document->signers->min('created_at') ?? $document->updated_at;
                            $activities->push([
                                'date' => $sentDate,
                                'text' => 'Document sent for signature',
                                'icon' => 'fas fa-paper-plane',
                                'type' => 'sent'
                            ]);
                        }
                        
                        // Signer activities
                        foreach ($document->signers as $signer) {
                            // Signer added
                            $activities->push([
                                'date' => $signer->created_at,
                                'text' => "Signer added: {$signer->name}",
                                'icon' => 'fas fa-user-plus',
                                'type' => 'signer_added'
                            ]);
                            
                            // Document opened
                            if ($signer->opened_at) {
                                $activities->push([
                                    'date' => $signer->opened_at,
                                    'text' => "Opened by {$signer->name}",
                                    'icon' => 'fas fa-eye',
                                    'type' => 'opened'
                                ]);
                            }
                            
                            // Reminders sent
                            if ($signer->reminder_count > 0) {
                                for ($i = 1; $i <= $signer->reminder_count; $i++) {
                                    $reminderDate = $signer->last_reminder_sent_at ?? $signer->updated_at;
                                    $activities->push([
                                        'date' => $reminderDate,
                                        'text' => "Reminder #{$i} sent to {$signer->name}",
                                        'icon' => 'fas fa-bell',
                                        'type' => 'reminder'
                                    ]);
                                }
                            }
                            
                            // Document signed
                            if ($signer->signed_at) {
                                $activities->push([
                                    'date' => $signer->signed_at,
                                    'text' => "Signed by {$signer->name}",
                                    'icon' => 'fas fa-check-circle',
                                    'type' => 'signed'
                                ]);
                            }
                            
                            // Signature cancelled
                            if ($signer->cancelled_at) {
                                $activities->push([
                                    'date' => $signer->cancelled_at,
                                    'text' => "Signature cancelled for {$signer->name}",
                                    'icon' => 'fas fa-times-circle',
                                    'type' => 'signature_cancelled'
                                ]);
                            }
                        }
                        
                        // Email delivery activities from SignatureActivity
                        foreach ($document->signatureActivities()->whereIn('action_type', ['email_sent', 'email_failed', 'email_delivered', 'signature_cancelled'])->get() as $note) {
                            $metadata = $note->metadata ?? [];
                            $signerName = $metadata['signer_name'] ?? 'Unknown';
                            $signerEmail = $metadata['signer_email'] ?? '';
                            
                            if ($note->action_type === 'email_sent') {
                                $status = $metadata['status'] ?? 'sent';
                                $activities->push([
                                    'date' => $note->created_at,
                                    'text' => "Email sent to {$signerName}" . ($signerEmail ? " ({$signerEmail})" : ''),
                                    'icon' => 'fas fa-envelope',
                                    'type' => 'email_sent',
                                    'note' => $note
                                ]);
                            } elseif ($note->action_type === 'email_failed') {
                                $error = $metadata['error'] ?? 'Unknown error';
                                $activities->push([
                                    'date' => $note->created_at,
                                    'text' => "Email failed to {$signerName}" . ($signerEmail ? " ({$signerEmail})" : ''),
                                    'icon' => 'fas fa-exclamation-triangle',
                                    'type' => 'email_failed',
                                    'note' => $note,
                                    'error' => $error
                                ]);
                            } elseif ($note->action_type === 'email_delivered') {
                                $activities->push([
                                    'date' => $note->created_at,
                                    'text' => "Email delivered to {$signerName}" . ($signerEmail ? " ({$signerEmail})" : ''),
                                    'icon' => 'fas fa-check',
                                    'type' => 'email_delivered',
                                    'note' => $note
                                ]);
                            } elseif ($note->action_type === 'signature_cancelled') {
                                $activities->push([
                                    'date' => $note->created_at,
                                    'text' => $note->note ?? "Signature cancelled for {$signerName}",
                                    'icon' => 'fas fa-times-circle',
                                    'type' => 'signature_cancelled',
                                    'note' => $note
                                ]);
                            }
                        }
                        
                        // Sort activities by date (newest first)
                        $activities = $activities->sortByDesc('date');
                    @endphp
                    
                    @if($activities->count() > 0)
                        @foreach($activities as $activity)
                        <div class="timeline-item {{ $activity['type'] }}" style="{{ $activity['type'] === 'email_failed' || $activity['type'] === 'signature_cancelled' ? 'border-left: 3px solid #dc3545;' : ($activity['type'] === 'email_sent' ? 'border-left: 3px solid #28a745;' : ($activity['type'] === 'email_delivered' ? 'border-left: 3px solid #17a2b8;' : '')) }}">
                            <div class="timeline-icon" style="{{ $activity['type'] === 'email_failed' || $activity['type'] === 'signature_cancelled' ? 'background-color: #dc3545;' : ($activity['type'] === 'email_sent' ? 'background-color: #28a745;' : ($activity['type'] === 'email_delivered' ? 'background-color: #17a2b8;' : '')) }}">
                                {!! \App\Helpers\IconHelper::fromLegacy($activity['icon']) !!}
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-date">{{ $activity['date']->format('M d, Y g:i A') }}</div>
                                <div class="timeline-text" style="{{ $activity['type'] === 'email_failed' || $activity['type'] === 'signature_cancelled' ? 'color: #dc3545; font-weight: 500;' : '' }}">{{ $activity['text'] }}</div>
                                @if(isset($activity['error']))
                                <div style="margin-top: 5px; padding: 6px 10px; background-color: #fee; border-left: 3px solid #dc3545; border-radius: 4px; font-size: 12px; color: #721c24;">
                                    <strong>Error:</strong> {{ \Illuminate\Support\Str::limit($activity['error'], 150) }}
                                </div>
                                @endif
                                @if(isset($activity['note']) && $activity['note']->metadata && isset($activity['note']->metadata['request_id']))
                                <div style="margin-top: 3px; font-size: 11px; color: #6c757d;">
                                    Request ID: {{ \Illuminate\Support\Str::limit($activity['note']->metadata['request_id'], 30) }}
                                </div>
                                @endif
                                <div class="timeline-time">{{ $activity['date']->diffForHumans() }}</div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <div class="timeline-text" style="color: #6c757d; font-style: italic;">No activity yet</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Signer Modal -->
<div class="modal fade" id="addSignerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="addSignerForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        @icon('fa-user-plus') Add Signer
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="step-indicator">
                    <div class="step-dot active" id="step1"></div>
                    <div class="step-dot" id="step2"></div>
                    <div class="step-dot" id="step3"></div>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Email Input -->
                    <div id="emailStep" class="modal-step">
                        <div class="form-group">
                            <label for="modal_signer_email">Signer Email <span style="color: #dc3545;">*</span></label>
                            <input type="email" class="form-control" id="modal_signer_email" name="signer_email" 
                                   placeholder="john@example.com" required>
                            <small class="form-help-text">Enter the email address to find existing clients/leads</small>
                        </div>
                        
                        <div id="emailSearchResults" class="search-results" style="display: none;">
                            <h6>Found Matches:</h6>
                            <div id="matchesList"></div>
                            <div id="matterSelectionConfirmation"></div>
                        </div>
                        
                        <div id="noMatchesMessage" class="no-matches" style="display: none;">
                            <div class="alert alert-info">
                                @icon('fa-info-circle') No existing clients or leads found with this email address.
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Client/Lead Selection -->
                    <div id="selectionStep" class="modal-step" style="display: none;">
                        <div class="form-group">
                            <label>Select Client/Lead:</label>
                            <div id="clientLeadSelection"></div>
                        </div>
                    </div>

                    <!-- Step 3: Manual Name Input (when no matches) -->
                    <div id="nameStep" class="modal-step" style="display: none;">
                        <div class="form-group">
                            <label for="modal_signer_name">Signer Name <span style="color: #dc3545;">*</span></label>
                            <input type="text" class="form-control" id="modal_signer_name" name="signer_name" 
                                   placeholder="John Doe">
                            <small class="form-help-text">Enter the full name of the signer</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="modal_from_email">Send From Email Account</label>
                        <select class="form-control" id="modal_from_email" name="from_email">
                            <option value="">-- Use Default --</option>
                            @foreach($emailAccounts as $account)
                                <option value="{{ $account->email }}">{{ $account->display_name }} ({{ $account->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_email_template">Email Template</label>
                        <select class="form-control" id="modal_email_template" name="email_template">
                            <option value="emails.signature.send">Standard - Professional signature request</option>
                            <option value="emails.signature.send_agreement">Agreement - With legal notices and attachments</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_email_subject">Email Subject (Optional)</label>
                        <input type="text" class="form-control" id="modal_email_subject" name="email_subject" 
                               placeholder="Leave blank for default subject">
                    </div>

                    <div class="form-group">
                        <label for="modal_email_message">Custom Message (Optional)</label>
                        <textarea class="form-control" id="modal_email_message" name="email_message" rows="3"
                                  placeholder="Add a personal message..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-secondary" id="backBtn" onclick="goBackStep()" style="display: none;">
                        @icon('fa-arrow-left') Back
                    </button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="goNextStep()" style="display: none;">
                        Next @icon('fa-arrow-right')
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                        @icon('fa-user-plus') Add Signer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Detach Confirmation Modal -->
<div class="modal fade" id="detachModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('signatures.detach', $document->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        @icon('fa-unlink') Detach Document
                    </h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        @icon('fa-exclamation-triangle')
                        <strong>Are you sure?</strong>
                        <p style="margin: 10px 0 0 0;">This will remove the association between this document and the client/lead.</p>
                    </div>
                    
                    <div class="form-group">
                        <label>Reason for detachment (Optional)</label>
                        <textarea class="form-control" name="reason" rows="3" placeholder="Why is this document being detached?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        @icon('fa-unlink') Detach Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Association functionality data
const clients = @json($clients ?? []);
const leads = @json($leads ?? []);
let currentMatch = null;

// Debug: Log the data
console.log('Clients loaded:', clients.length);
console.log('Leads loaded:', leads.length);
if (clients.length > 0) console.log('Sample client:', clients[0]);
if (leads.length > 0) console.log('Sample lead:', leads[0]);

// Open attach modal
function openAttachModal() {
    $('#attachModal').modal('show');
    // Reset form
    document.getElementById('lookupResults').style.display = 'none';
    document.getElementById('attachBtn').disabled = true;
    document.getElementById('entityType').value = '';
    document.getElementById('matterId').innerHTML = '<option value="">-- Select Matter --</option>';
    document.getElementById('matterSelection').style.display = 'none';
    document.getElementById('docCategory').value = '';
    
    // Setup auto-search
    setupAutoSearch();
}

// Lookup signer by email
function lookupSigner() {
    const email = document.getElementById('signerEmail').value.trim();
    if (!email) {
        alert('Please enter a signer email address');
        return;
    }
    
    // Show loading
    const lookupBtn = document.getElementById('lookupBtn');
    const originalText = lookupBtn.innerHTML;
    lookupBtn.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Searching...';
    lookupBtn.disabled = true;
    
    // Search in clients and leads
    const clientMatch = clients.find(client => 
        client.email && client.email.toLowerCase() === email.toLowerCase()
    );
    const leadMatch = leads.find(lead => 
        lead.email && lead.email.toLowerCase() === email.toLowerCase()
    );
    
    setTimeout(() => {
        lookupBtn.innerHTML = originalText;
        lookupBtn.disabled = false;
        
        if (clientMatch) {
            currentMatch = { type: 'client', data: clientMatch };
            showMatch(clientMatch, 'Client');
        } else if (leadMatch) {
            currentMatch = { type: 'lead', data: leadMatch };
            showMatch(leadMatch, 'Lead');
        } else {
            alert('No matching client or lead found for this email address');
        }
    }, 500);
}

// Auto-search as user types (with debounce)
let searchTimeout;
function setupAutoSearch() {
    const emailInput = document.getElementById('signerEmail');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const email = this.value.trim();
            
            if (email.length > 3) { // Only search if email has more than 3 characters
                searchTimeout = setTimeout(() => {
                    // Auto-search without showing loading
                    const clientMatch = clients.find(client => 
                        client.email && client.email.toLowerCase() === email.toLowerCase()
                    );
                    const leadMatch = leads.find(lead => 
                        lead.email && lead.email.toLowerCase() === email.toLowerCase()
                    );
                    
                    if (clientMatch) {
                        currentMatch = { type: 'client', data: clientMatch };
                        showMatch(clientMatch, 'Client');
                    } else if (leadMatch) {
                        currentMatch = { type: 'lead', data: leadMatch };
                        showMatch(leadMatch, 'Lead');
                    } else {
                        // Hide results if no match
                        document.getElementById('lookupResults').style.display = 'none';
                        currentMatch = null;
                    }
                }, 800); // 800ms delay
            } else {
                // Hide results if email is too short
                document.getElementById('lookupResults').style.display = 'none';
                currentMatch = null;
            }
        });
    }
}

// Show match results
function showMatch(match, type) {
    const matchInfo = document.getElementById('matchInfo');
    matchInfo.innerHTML = `
        <strong>${type}:</strong> ${match.first_name} ${match.last_name}<br>
        <strong>Email:</strong> ${match.email}<br>
        <strong>Type:</strong> ${type}
    `;
    
    document.getElementById('lookupResults').style.display = 'block';
    document.getElementById('entityType').value = type.toLowerCase();
    document.getElementById('attachBtn').disabled = false;
    
    // Load matters if client
    if (type === 'Client') {
        loadClientMatters(match.id);
    }
}

// Load client matters
function loadClientMatters(clientId) {
    fetch(`/api/client-matters/${clientId}`)
        .then(response => response.json())
        .then(data => {
            const matterSelect = document.getElementById('matterId');
            matterSelect.innerHTML = '<option value="">-- Select Matter --</option>';
            
            if (data.matters && data.matters.length > 0) {
                data.matters.forEach(matter => {
                    const option = document.createElement('option');
                    option.value = matter.id;
                    option.textContent = matter.client_unique_matter_no || `Matter #${matter.id}`;
                    matterSelect.appendChild(option);
                });
                document.getElementById('matterSelection').style.display = 'block';
            } else {
                document.getElementById('matterSelection').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading matters:', error);
            document.getElementById('matterSelection').style.display = 'none';
        });
}

// Handle entity type change
document.addEventListener('DOMContentLoaded', function() {
    const entityType = document.getElementById('entityType');
    const docCategory = document.getElementById('docCategory');
    
    if (entityType && docCategory) {
        entityType.addEventListener('change', function() {
            const type = this.value;
            const options = docCategory.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') return;
                const forType = option.getAttribute('data-for');
                if (forType === type) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            docCategory.value = '';
        });
    }
    
    // Handle form submission
    const attachForm = document.getElementById('attachForm');
    if (attachForm) {
        attachForm.addEventListener('submit', function(e) {
            if (!currentMatch) {
                e.preventDefault();
                alert('Please lookup a signer first');
                return;
            }
            
            // Set the form action and add hidden fields
            this.action = '{{ route("signatures.associate", $document->id) }}';
            
            // Add hidden fields for the match
            const entityIdInput = document.createElement('input');
            entityIdInput.type = 'hidden';
            entityIdInput.name = 'entity_id';
            entityIdInput.value = currentMatch.data.id;
            this.appendChild(entityIdInput);
            
            const entityTypeInput = document.createElement('input');
            entityTypeInput.type = 'hidden';
            entityTypeInput.name = 'entity_type';
            entityTypeInput.value = currentMatch.type;
            this.appendChild(entityTypeInput);
        });
    }
});

function copySigningLink(url) {
    navigator.clipboard.writeText(url).then(function() {
        alert('Signing link copied to clipboard!');
    }, function(err) {
        console.error('Failed to copy: ', err);
        prompt('Copy this link:', url);
    });
}

function confirmCancelSignature() {
    return confirm('Are you sure you want to cancel this signature? The signer will no longer be able to sign this document. This action cannot be undone.');
}

function viewDocument() {
    alert('Document viewer feature coming soon!');
}

function confirmDetach() {
    $('#detachModal').modal('show');
}

// ===== ADD SIGNER MODAL FUNCTIONALITY =====
let currentStep = 1;
let emailMatches = [];
let selectedMatch = null;
let selectedMatter = null;
let selectedClient = null;

function openAddSignerModal() {
    // Reset everything
    currentStep = 1;
    emailMatches = [];
    selectedMatch = null;
    selectedMatter = null;
    selectedClient = null;
    
    // Reset form
    document.getElementById('addSignerForm').reset();
    
    // Hide all steps
    document.querySelectorAll('.modal-step').forEach(step => step.style.display = 'none');
    
    // Show only email step
    document.getElementById('emailStep').style.display = 'block';
    
    // Reset step indicators
    document.querySelectorAll('.step-dot').forEach((dot, index) => {
        dot.classList.remove('active', 'completed');
        if (index === 0) dot.classList.add('active');
    });
    
    // Reset buttons
    document.getElementById('backBtn').style.display = 'none';
    document.getElementById('nextBtn').style.display = 'none';
    document.getElementById('submitBtn').style.display = 'none';
    
    // Set form action
    document.getElementById('addSignerForm').action = '{{ route("signatures.store") }}';
    
    // Add hidden document_id field
    let existingInput = document.getElementById('modal_document_id');
    if (!existingInput) {
        let hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'document_id';
        hiddenInput.id = 'modal_document_id';
        hiddenInput.value = '{{ $document->id }}';
        document.getElementById('addSignerForm').appendChild(hiddenInput);
    } else {
        existingInput.value = '{{ $document->id }}';
    }
    
    // Show modal
    $('#addSignerModal').modal('show');
}

// ===== EMAIL SEARCH FUNCTIONALITY =====
let emailCheckTimeout = null;

// Email field change detection with debounce
document.addEventListener('DOMContentLoaded', function() {
    const emailField = document.getElementById('modal_signer_email');
    if (emailField) {
        emailField.addEventListener('input', function() {
            clearTimeout(emailCheckTimeout);
            const email = this.value.trim();
            
            // Hide previous results
            document.getElementById('emailSearchResults').style.display = 'none';
            document.getElementById('noMatchesMessage').style.display = 'none';
            
            // Only check if it's a valid-looking email
            if (email && email.includes('@') && email.includes('.')) {
                emailCheckTimeout = setTimeout(() => {
                    searchEmailMatches(email);
                }, 800); // Wait 800ms after user stops typing
            }
        });
    }
});

// Search for email matches via API
function searchEmailMatches(email) {
    fetch('{{ route('signatures.suggest-association') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.matches && data.matches.length > 0) {
            // Matches found
            emailMatches = data.matches;
            
            if (data.matches.length === 1) {
                // Auto-select single match and go to step 3
                selectedMatch = data.matches[0];
                selectedClient = data.matches[0];
                document.getElementById('modal_signer_name').value = selectedMatch.name;
                currentStep = 3;
                showStep(3);
            } else {
                // Multiple matches, show selection
                displayEmailMatches();
            }
        } else {
            // No matches found
            emailMatches = [];
            document.getElementById('noMatchesMessage').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error checking email match:', error);
        emailMatches = [];
        document.getElementById('noMatchesMessage').style.display = 'block';
    });
}

// Display email matches
function displayEmailMatches() {
    const resultsDiv = document.getElementById('emailSearchResults');
    const matchesList = document.getElementById('matchesList');
    
    matchesList.innerHTML = '';
    
    emailMatches.forEach((match, index) => {
        const matchDiv = document.createElement('div');
        matchDiv.className = 'match-item';
        matchDiv.onclick = () => selectMatch(match, index);
        
        let mattersHtml = '';
        if (match.has_matters && match.matters.length > 0) {
            mattersHtml = `
                <div class="match-matters">
                    <div class="match-matters-title">Matters (click to select):</div>
                    ${match.matters.map(matter => `<span class="matter-item clickable" onclick="selectMatter('${match.id}', '${matter.id}', '${matter.label}')">${matter.label}</span>`).join('')}
                </div>
            `;
        }
        
        matchDiv.innerHTML = `
            <div class="match-header">
                <div class="match-name">${match.name}</div>
                <span class="match-type ${match.type}">${match.type}</span>
            </div>
            <div class="match-email">${match.email}</div>
            ${mattersHtml}
        `;
        
        matchesList.appendChild(matchDiv);
    });
    
    resultsDiv.style.display = 'block';
}

// Select a match
function selectMatch(match, index) {
    selectedMatch = match;
    selectedClient = match; // Store the selected client/lead
    
    // Update visual selection
    document.querySelectorAll('.match-item').forEach((item, i) => {
        item.classList.toggle('selected', i === index);
    });
    
    // Auto-fill name field
    document.getElementById('modal_signer_name').value = match.name;
    
    // Auto-advance to step 3 when a match is selected
    if (currentStep === 1 || currentStep === 2) {
        currentStep = 3;
        showStep(3);
    }
}

// Select a matter
function selectMatter(clientId, matterId, matterLabel) {
    selectedMatter = {
        client_id: clientId,
        matter_id: matterId,
        label: matterLabel
    };
    
    // Update visual selection
    document.querySelectorAll('.matter-item.clickable').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Find and highlight the selected matter
    const selectedMatterElement = document.querySelector(`[onclick="selectMatter('${clientId}', '${matterId}', '${matterLabel}')"]`);
    if (selectedMatterElement) {
        selectedMatterElement.classList.add('selected');
    }
    
    // Show a confirmation message
    const confirmationDiv = document.getElementById('matterSelectionConfirmation');
    if (confirmationDiv) {
        confirmationDiv.innerHTML = `
            <div class="alert alert-success" style="margin-top: 10px;">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check-circle') : '<i class="fas fa-check-circle"></i>'} Selected matter: <strong>${matterLabel}</strong>
            </div>
        `;
    }
}

// ===== STEP NAVIGATION =====
function goNextStep() {
    if (currentStep === 1) {
        // From email step
        if (emailMatches.length > 1 && !selectedMatch) {
            // Multiple matches, need to show selection step
            currentStep = 2;
            showStep(2);
        } else if (emailMatches.length === 1 && !selectedMatch) {
            // Single match, auto-select it
            selectedMatch = emailMatches[0];
            document.getElementById('modal_signer_name').value = selectedMatch.name;
            currentStep = 3; // Skip to final step
            showStep(3);
        } else if (emailMatches.length > 0 && selectedMatch) {
            // Match already selected, go to final step
            currentStep = 3;
            showStep(3);
        } else if (emailMatches.length === 0) {
            // No matches, go to name input step
            currentStep = 2;
            showStep(2);
        }
    } else if (currentStep === 2) {
        // From selection/name step to final step
        currentStep = 3;
        showStep(3);
    }
}

function goBackStep() {
    if (currentStep === 2) {
        currentStep = 1;
        showStep(1);
    } else if (currentStep === 3) {
        if (emailMatches.length > 0) {
            currentStep = 1;
            showStep(1);
        } else {
            currentStep = 2;
            showStep(2);
        }
    }
}

function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.modal-step').forEach(stepEl => stepEl.style.display = 'none');
    
    // Show current step
    if (step === 1) {
        document.getElementById('emailStep').style.display = 'block';
    } else if (step === 2) {
        // Determine if this is selection step or name step
        if (emailMatches.length > 1 && !selectedMatch) {
            // Show selection step
            document.getElementById('selectionStep').style.display = 'block';
            populateSelectionStep();
        } else {
            // Show name input step
            document.getElementById('nameStep').style.display = 'block';
        }
    } else if (step === 3) {
        // Show email settings (the existing form fields)
        // These are already visible, we just need to show the submit button
        // Also ensure the name field is visible and filled
        document.getElementById('nameStep').style.display = 'block';
    }
    
    // Update step indicators
    document.querySelectorAll('.step-dot').forEach((dot, index) => {
        dot.classList.remove('active', 'completed');
        if (index < step - 1) {
            dot.classList.add('completed');
        } else if (index === step - 1) {
            dot.classList.add('active');
        }
    });
    
    // Update buttons
    document.getElementById('backBtn').style.display = step > 1 ? 'inline-block' : 'none';
    document.getElementById('nextBtn').style.display = step < 3 ? 'inline-block' : 'none';
    document.getElementById('submitBtn').style.display = (step === 3 || selectedMatch) ? 'inline-block' : 'none';
}

// Populate the selection step with matches
function populateSelectionStep() {
    const selectionDiv = document.getElementById('clientLeadSelection');
    selectionDiv.innerHTML = '';
    
    emailMatches.forEach((match, index) => {
        const matchDiv = document.createElement('div');
        matchDiv.className = 'match-item';
        matchDiv.onclick = () => selectMatch(match, index);
        
        let mattersHtml = '';
        if (match.has_matters && match.matters.length > 0) {
            mattersHtml = `
                <div class="match-matters">
                    <div class="match-matters-title">Matters (click to select):</div>
                    ${match.matters.map(matter => `<span class="matter-item clickable" onclick="selectMatter('${match.id}', '${matter.id}', '${matter.label}')">${matter.label}</span>`).join('')}
                </div>
            `;
        }
        
        matchDiv.innerHTML = `
            <div class="match-header">
                <div class="match-name">${match.name}</div>
                <span class="match-type ${match.type}">${match.type}</span>
            </div>
            <div class="match-email">${match.email}</div>
            ${mattersHtml}
        `;
        
        selectionDiv.appendChild(matchDiv);
    });
}

// ===== FORM SUBMISSION =====
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addSignerForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate required fields
            const email = document.getElementById('modal_signer_email').value;
            const name = document.getElementById('modal_signer_name').value;
            
            if (!email || !name) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Add selected matter data to form if available
            if (selectedMatter) {
                const matterInput = document.createElement('input');
                matterInput.type = 'hidden';
                matterInput.name = 'client_matter_id';
                matterInput.value = selectedMatter.matter_id;
                form.appendChild(matterInput);
            } else if (selectedClient) {
                // If no matter selected but client/lead is selected, send client ID
                const clientInput = document.createElement('input');
                clientInput.type = 'hidden';
                clientInput.name = 'selected_client_id';
                clientInput.value = selectedClient.id;
                form.appendChild(clientInput);
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Adding Signer...';
            submitBtn.disabled = true;
            
            // Submit form
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => {
                if (response.ok) {
                    // Close modal and reload page
                    $('#addSignerModal').modal('hide');
                    location.reload();
                } else {
                    return response.text().then(text => {
                        throw new Error('Server error: ' + text);
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding signer: ' + error.message);
                
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});
</script>
@endsection

