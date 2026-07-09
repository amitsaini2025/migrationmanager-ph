@extends('layouts.crm_client_detail_dashboard')
@section('title', 'Smart Email Import')

@section('content')
@php
    $emailUploadExtensions = config('crm.email_upload_allowed_extensions', ['msg', 'eml']);
    $emailUploadAccept = collect($emailUploadExtensions)->map(fn ($e) => '.' . ltrim($e, '.'))->implode(',');
    $emailUploadLabel = collect($emailUploadExtensions)->map(fn ($e) => '.' . ltrim($e, '.'))->implode(', ');
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-fluid py-4" id="smart-import-app">

    {{-- ================================================================
         Page header
    ================================================================ --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0">@icon('fa-mail-bulk', ['class' => 'mr-2 text-primary']) Smart Email Import</h4>
            <small class="text-muted">Upload {{ $emailUploadLabel }} files, review suggested client &amp; matter assignments, then confirm.</small>
        </div>
        <button id="btn-upload-more" class="btn btn-outline-secondary btn-sm d-none">
            @icon('fa-upload', ['class' => 'mr-1']) Upload More
        </button>
    </div>

    {{-- ================================================================
         Upload panel
    ================================================================ --}}
    <div id="upload-panel">
        <div class="card shadow-sm">
            <div class="card-body">
                <div id="dropzone-area"
                     class="border-2 border-dashed rounded p-5 text-center"
                     style="border: 2px dashed #ced4da; cursor:pointer; transition: background .2s;">
                    @icon('fa-cloud-upload-alt', ['class' => 'text-muted mb-3', 'style' => 'width: 3em; height: 3em;'])
                    <p class="mb-1 font-weight-semibold">Drag &amp; drop Outlook email files ({{ $emailUploadLabel }}) here</p>
                    <p class="text-muted small mb-3">or click to browse — up to 20 files, 30 MB each</p>
                    <input type="file" id="file-input" accept="{{ $emailUploadAccept }}" multiple style="display:none">
                    <button type="button" class="btn btn-primary" id="btn-browse">
                        @icon('fa-folder-open', ['class' => 'mr-1']) Browse Files
                    </button>
                </div>

                <div id="file-list" class="mt-3" style="display:none">
                    <hr>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <strong id="file-count-label">0 file(s) selected</strong>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-clear-files">
                            @icon('fa-times') Clear
                        </button>
                    </div>
                    <ul id="selected-files-ul" class="list-group list-group-flush small"></ul>
                    <div class="mt-3 text-right">
                        <button type="button" class="btn btn-success" id="btn-analyze">
                            @icon('fa-search', ['class' => 'mr-1']) Analyze Emails
                        </button>
                    </div>
                </div>

                <div id="analyze-progress" class="mt-3 d-none">
                    <div class="progress" style="height:6px">
                        <div class="progress-bar progress-bar-striped progress-bar-animated w-100" role="progressbar"></div>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Parsing emails and matching clients… please wait.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================
         Review panel (hidden until analyze returns)
    ================================================================ --}}
    <div id="review-panel" class="d-none">

        {{-- Bulk action bar --}}
        <div class="card shadow-sm mb-3">
            <div class="card-body py-2 d-flex align-items-center flex-wrap gap-2">
                <button id="btn-confirm-high-confidence" class="btn btn-success btn-sm mr-2">
                    @icon('fa-check-double', ['class' => 'mr-1']) Confirm High Confidence (≥80%)
                </button>
                <button id="btn-confirm-selected" class="btn btn-primary btn-sm mr-2">
                    @icon('fa-check', ['class' => 'mr-1']) Confirm Selected
                </button>
                <span class="text-muted small ml-2" id="bulk-status-text"></span>
            </div>
        </div>

        {{-- Review table --}}
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="review-table">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:36px">
                                    <input type="checkbox" id="select-all-checkbox" title="Select all">
                                </th>
                                <th>Email</th>
                                <th style="min-width:100px">Type</th>
                                <th style="min-width:260px">Client</th>
                                <th style="min-width:220px">Matter</th>
                                <th style="width:90px">Match&nbsp;%</th>
                                <th style="width:90px">Action</th>
                            </tr>
                        </thead>
                        <tbody id="review-tbody">
                            {{-- Rows injected by JS --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Parse errors (if any) --}}
        <div id="parse-errors-panel" class="d-none mt-3">
            <div class="alert alert-warning">
                <strong>@icon('fa-exclamation-triangle', ['class' => 'mr-1']) The following files could not be parsed:</strong>
                <ul id="parse-errors-ul" class="mb-0 mt-2 small"></ul>
            </div>
        </div>
    </div>

    {{-- Quick-peek modal --}}
    <div class="modal fade" id="peek-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" id="peek-subject"></h6>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body small">
                    <div class="row mb-2">
                        <div class="col-sm-2 text-muted font-weight-bold">From</div>
                        <div class="col-sm-10" id="peek-from"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-2 text-muted font-weight-bold">To</div>
                        <div class="col-sm-10" id="peek-to"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-2 text-muted font-weight-bold">Date</div>
                        <div class="col-sm-10" id="peek-date"></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-2 text-muted font-weight-bold">Attachments</div>
                        <div class="col-sm-10" id="peek-attachments"></div>
                    </div>
                    <hr>
                    <div id="peek-snippet" class="text-muted" style="white-space:pre-wrap;max-height:300px;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cross-access modal is already included by the crm_client_detail_dashboard layout --}}

</div>

<style>
#dropzone-area.drag-over {
    background: #eaf2ff;
    border-color: #0d6efd !important;
}
.confidence-badge {
    display: inline-block;
    min-width: 42px;
    text-align: center;
    font-size: .78rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
}
.conf-high   { background:#d4edda; color:#155724; }
.conf-medium { background:#fff3cd; color:#856404; }
.conf-low    { background:#f8d7da; color:#721c24; }
.conf-none   { background:#e9ecef; color:#6c757d; }
.row-saved   { background:#f0fff4 !important; opacity:.6; pointer-events:none; }
.row-failed  { background:#fff5f5 !important; }
.matter-select { max-width: 220px; }
</style>

<script>
    window.__CRM_EMAIL_ALLOWED_EXTENSIONS__ = @json($emailUploadExtensions);
    window.SmartImportConfig = {
        analyzeUrl:  "{{ route('email.smart-import.analyze') }}",
        confirmUrl:  "{{ route('email.smart-import.confirm') }}",
        clientSearchUrl: "{{ route('clients.getallclients') }}",
        getMattersUrl:   "/get-client-matters/",
        csrfToken:   "{{ csrf_token() }}"
    };
</script>
<script src="{{ asset('js/email-upload-helpers.js') }}"></script>
<script src="{{ asset('js/smart-email-import.js') }}?v={{ file_exists(public_path('js/smart-email-import.js')) ? filemtime(public_path('js/smart-email-import.js')) : 1 }}"></script>

@endsection
