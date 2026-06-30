@extends('layouts.crm_client_detail')
@section('title', 'EOI/ROI Sheet')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<style>
    /* Sheet tabs styling */
    .sheet-tabs {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 0;
        margin: 0 -20px 20px -20px;
        display: flex;
        gap: 0;
        border-radius: 8px 8px 0 0;
    }
    
    .sheet-tab {
        flex: 1;
        padding: 15px 20px;
        text-align: center;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
        transition: all 0.3s ease;
        border-bottom: 3px solid transparent;
        position: relative;
    }
    
    .sheet-tab:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.1);
        text-decoration: none;
    }
    
    .sheet-tab.active {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.15);
        border-bottom-color: #ffffff;
    }
    
    .sheet-tab i {
        margin-right: 8px;
    }

    /* Table styling */
    .eoi-roi-table {
        font-size: 13px;
    }
    
    .eoi-roi-table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 600;
        white-space: nowrap;
        padding: 12px 8px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
        border-bottom: 2px solid #667eea;
    }

    /* Pin col (col 1) and EOI ID col (col 2) width overrides */
    .listing-container table.eoi-roi-table th:first-child,
    .listing-container table.eoi-roi-table td:first-child {
        width: 40px !important;
        min-width: 40px !important;
        max-width: 40px !important;
    }
    #eoi-roi-sheet-table .frozen-col-2,
    #eoi-roi-sheet-table thead th.frozen-col-2 {
        min-width: 140px;
        max-width: none !important;
        white-space: nowrap;
        overflow: visible !important;
        text-overflow: clip !important;
    }
    #eoi-roi-sheet-table .frozen-col-3,
    #eoi-roi-sheet-table thead th.frozen-col-3 {
        min-width: 130px;
        max-width: none !important;
        white-space: nowrap;
        overflow: visible !important;
    }
    
    .eoi-roi-table td {
        padding: 10px 8px;
        vertical-align: middle;
    }
    
    .eoi-roi-table tbody tr:hover {
        background-color: #f8f9ff;
    }
    
    .eoi-link {
        color: #667eea;
        font-weight: 600;
        text-decoration: none;
    }
    
.eoi-link:hover {
    color: #764ba2;
    text-decoration: underline;
}

/* Comments cell – warnings text */
.eoi-comments-cell {
    min-width: 300px;
    max-width: 400px;
    font-size: 12px;
    line-height: 1.4;
    word-wrap: break-word;
    white-space: normal;
}

.eoi-comments-cell .warning-text {
    color: #dc3545;
    font-weight: 500;
    display: block;
    margin-bottom: 4px;
}

.eoi-comments-cell.comment-cell-editable {
    cursor: text;
    vertical-align: top;
}

.eoi-comments-cell.comment-cell-editable:hover .sheet-comment-text:not(.is-editing) {
    background: #f8fafc;
    box-shadow: inset 0 0 0 1px #cbd5e1;
    border-radius: 4px;
}

.eoi-comments-cell .sheet-comment-text {
    display: block;
    max-height: 3.6em;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: pre-wrap;
    word-break: break-word;
}

.eoi-comments-cell .sheet-comment-text.is-placeholder {
    color: #94a3b8;
    font-style: italic;
}

.eoi-comments-cell .sheet-comment-edit {
    width: 100%;
    min-width: 180px;
    min-height: 72px;
    font-size: 12px;
    line-height: 1.4;
    padding: 6px 8px;
    border: 1px solid #3b82f6;
    border-radius: 4px;
    resize: vertical;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}

.eoi-comments-cell .sheet-comment-edit:disabled {
    opacity: 0.7;
    cursor: wait;
}

.eoi-comments-cell .sheet-comment-hint {
    display: block;
    font-size: 10px;
    color: #64748b;
    margin-top: 2px;
}

/* Highlight rows with warnings */
.eoi-roi-table tbody tr.has-warning {
    background-color: #fff5f5 !important;
    border-left: 3px solid #dc3545;
}

.eoi-roi-table tbody tr.has-warning:hover {
    background-color: #ffe5e5 !important;
}

/* Filters panel */
    .filter_panel {
        display: none;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .filter_panel.show {
        display: block;
    }
    
    .active-filters-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 10px;
    }
    
    .clear-filter-btn {
        background: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .clear-filter-btn:hover {
        background: #5a6268;
    }

    /* Per page select */
    .per-page-select {
        width: auto;
        display: inline-block;
        margin-left: 10px;
    }
    
    /* Sort icons */
    .sortable {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 20px !important;
    }
    
    .sortable:hover {
        background: rgba(102, 126, 234, 0.1);
    }
    
    .sortable::after {
        content: '⇅';
        font-family: inherit;
        font-weight: 600;
        position: absolute;
        right: 8px;
        opacity: 0.35;
        font-size: 11px;
        line-height: 1;
    }
    
    .sortable.asc::after {
        content: '↑';
        opacity: 1;
        color: #667eea;
    }
    
    .sortable.desc::after {
        content: '↓';
        opacity: 1;
        color: #667eea;
    }

    /* Action buttons */
    .btn-group-vertical .btn {
        margin-bottom: 5px;
    }

    .verify-btn, .send-email-btn, .view-notes-btn {
        white-space: nowrap;
    }

    /* Vertical + horizontal scroll container */
    .listing-container .card-body { overflow: visible; }
    #table-scroll-container {
        max-height: calc(100vh - 280px);
        min-height: 320px;
        overflow: auto !important;
        position: relative;
        -webkit-overflow-scrolling: touch;
    }
    #table-scroll-container::-webkit-scrollbar { height: 10px; width: 10px; }
    #table-scroll-container::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
    }

    /* Sticky thead */
    #eoi-roi-sheet-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    #eoi-roi-sheet-table thead th {
        position: sticky;
        top: 0;
        z-index: 11;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        border-bottom: none;
        box-shadow: inset 0 -2px 0 #667eea;
    }

    /* Frozen columns */
    #eoi-roi-sheet-table .frozen-col {
        position: sticky;
        z-index: 10;
        background: #fff;
        overflow: visible;
    }
    #eoi-roi-sheet-table thead th.frozen-col {
        z-index: 13;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
        overflow: visible;
    }
    #eoi-roi-sheet-table .frozen-col-1 { left: var(--eoi-frozen-left-1, 0); }
    #eoi-roi-sheet-table .frozen-col-2 { left: var(--eoi-frozen-left-2, 40px); }
    #eoi-roi-sheet-table .frozen-col-3 { left: var(--eoi-frozen-left-3, 180px); }
    #eoi-roi-sheet-table .frozen-col-3.frozen-col-last::after {
        content: '';
        position: absolute;
        top: 0; right: -6px; bottom: 0; width: 6px;
        pointer-events: none;
        background: linear-gradient(to right, rgba(15, 23, 42, 0.08), transparent);
    }
    #eoi-roi-sheet-table tbody tr:hover .frozen-col { background: #f8f9ff; }
    #eoi-roi-sheet-table tbody tr.has-warning .frozen-col { background: #fff5f5; }
    #eoi-roi-sheet-table tbody tr.has-warning:hover .frozen-col { background: #ffe5e5; }

    /* Scroll shadows */
    .table-container { position: relative; }
    .scroll-indicator {
        position: absolute; top: 0; bottom: 20px; width: 40px;
        pointer-events: none; z-index: 14; transition: opacity 0.3s ease;
    }
    .scroll-indicator-left {
        left: 0;
        background: linear-gradient(to right, rgba(255,255,255,0.95), transparent);
        opacity: 0;
    }
    .scroll-indicator-right {
        right: 0;
        background: linear-gradient(to left, rgba(255,255,255,0.95), transparent);
    }
    .scroll-indicator-left.visible, .scroll-indicator-right.visible { opacity: 1; }

    /* Pin star styles */
    .listing-container .pin-cell { width: 40px; text-align: center; }
    .listing-container .pin-star {
        width: 18px;
        height: 18px;
        cursor: pointer;
        color: #cbd5e0;
        transition: color 0.2s ease, transform 0.2s ease;
        vertical-align: middle;
    }
    .listing-container .pin-star:hover {
        color: #f59e0b;
        transform: scale(1.2);
    }
    .listing-container .pin-star.pinned {
        color: #f59e0b;
        filter: drop-shadow(0 0 4px rgba(245, 158, 11, 0.35));
    }
    .listing-container .pin-star.pinned:hover {
        color: #cbd5e0;
    }

    /* Scroll hint */
    .scroll-hint {
        text-align: center;
        padding: 10px;
        background: #e7f3ff;
        border-radius: 5px;
        margin-bottom: 10px;
        font-size: 13px;
        color: #0c5460;
    }

    .scroll-hint i {
        animation: slideHint 1.5s ease-in-out infinite;
    }

    @keyframes slideHint {
        0%, 100% { transform: translateX(-5px); }
        50% { transform: translateX(5px); }
    }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h4>@icon('fa-passport') EOI/ROI Sheet</h4>
                    <div class="card-header-actions">
                        <a href="{{ route('clients.index') }}" class="btn btn-theme btn-theme-sm" title="Back to Clients">
                            @icon('fa-arrow-left') Back to Clients
                        </a>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="sheet-tabs">
                    <a href="{{ route('clients.sheets.eoi-roi', request()->query()) }}" class="sheet-tab active">
                        @icon('fa-list') List
                    </a>
                    @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                    <a href="{{ route('clients.sheets.eoi-roi.insights', request()->query()) }}" class="sheet-tab">
                        @icon('fa-chart-bar') Insights
                    </a>
                    @endif
                </div>

                <div class="card-body">
                    {{-- Filter section --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <button type="button" class="btn btn-theme btn-theme-sm filter_btn">
                                @icon('fa-filter') Filters
                                @if($activeFilterCount > 0)
                                    <span class="active-filters-badge">{{ $activeFilterCount }}</span>
                                @endif
                            </button>
                            @if($activeFilterCount > 0)
                                <a href="{{ route('clients.sheets.eoi-roi') }}" class="clear-filter-btn">
                                    @icon('fa-undo') Clear Filters
                                </a>
                            @endif
                        </div>
                        <div>
                            <label for="per_page" style="display: inline; margin-right: 5px;">Show:</label>
                            <select name="per_page" id="per_page" class="form-control per-page-select">
                                @foreach([10, 25, 50, 100, 200] as $option)
                                    <option value="{{ $option }}" {{ $perPage == $option ? 'selected' : '' }}>
                                        {{ $option }} / page
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Office Filter (Always Visible) --}}
                    <div class="office-filter-section mb-3 p-3" style="background: #f8f9fa; border-radius: 5px; border: 1px solid #e3e6f0;">
                        <form action="{{ route('clients.sheets.eoi-roi') }}" method="get" id="officeFilterForm">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            @foreach(request()->except(['office', 'page', 'per_page']) as $key => $value)
                                @if(is_array($value))
                                    @foreach($value as $item)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            
                            <div class="d-flex align-items-center flex-wrap">
                                <label class="mb-0 mr-3" style="font-weight: 600; color: #6c757d;">
                                    @icon('fa-building') Filter by Office:
                                </label>
                                @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                    <div class="form-check form-check-inline mr-3">
                                        <input class="form-check-input office-filter-checkbox" 
                                               type="checkbox" 
                                               name="office[]" 
                                               value="{{ $office->id }}" 
                                               id="office_{{ $office->id }}"
                                               {{ is_array(request('office')) && in_array($office->id, request('office')) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="office_{{ $office->id }}" style="cursor: pointer;">
                                            {{ $office->office_name }}
                                        </label>
                                    </div>
                                @endforeach
                                @if(request('office'))
                                    <a href="{{ route('clients.sheets.eoi-roi', array_merge(request()->except(['office', 'page']), ['per_page' => $perPage])) }}" 
                                       class="btn btn-sm btn-secondary ml-2">
                                        @icon('fa-times') Clear Office Filter
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>

                    {{-- Filters panel --}}
                    <div class="filter_panel {{ $activeFilterCount > 0 ? 'show' : '' }}">
                        <form action="{{ route('clients.sheets.eoi-roi') }}" method="get" id="filterForm">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="search">Search</label>
                                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Client name or EOI number" id="search">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="occupation">Occupation</label>
                                        <input type="text" name="occupation" value="{{ request('occupation') }}" class="form-control" placeholder="Nominated occupation" id="occupation">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="eoi_status">EOI Status</label>
                                        <select name="eoi_status" id="eoi_status" class="form-control">
                                            <option value="">All Statuses</option>
                                            <option value="draft" {{ request('eoi_status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                            <option value="submitted" {{ request('eoi_status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                            <option value="invited" {{ request('eoi_status') == 'invited' ? 'selected' : '' }}>Invited</option>
                                            <option value="nominated" {{ request('eoi_status') == 'nominated' ? 'selected' : '' }}>Nominated</option>
                                            <option value="rejected" {{ request('eoi_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                            <option value="withdrawn" {{ request('eoi_status') == 'withdrawn' ? 'selected' : '' }}>Withdrawn</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="from_date">From Date</label>
                                        <input type="text" name="from_date" value="{{ request('from_date') }}" class="form-control datepicker" placeholder="dd/mm/yyyy" id="from_date" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="to_date">To Date</label>
                                        <input type="text" name="to_date" value="{{ request('to_date') }}" class="form-control datepicker" placeholder="dd/mm/yyyy" id="to_date" autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Subclass</label>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="189" id="subclass_189" {{ is_array(request('subclass')) && in_array('189', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_189">189</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="190" id="subclass_190" {{ is_array(request('subclass')) && in_array('190', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_190">190</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="491" id="subclass_491" {{ is_array(request('subclass')) && in_array('491', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_491">491</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="subclass[]" value="491-Family" id="subclass_491_family" {{ is_array(request('subclass')) && in_array('491-Family', request('subclass')) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="subclass_491_family">491-Family</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>State</label>
                                        <div class="d-flex gap-3 flex-wrap">
                                            @foreach(['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA', 'FED'] as $state)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="state[]" value="{{ $state }}" id="state_{{ $state }}" {{ is_array(request('state')) && in_array($state, request('state')) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="state_{{ $state }}">{{ $state }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary mr-2">Apply Filters</button>
                                    <a href="{{ route('clients.sheets.eoi-roi') }}" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- Scroll hint --}}
                    <div class="scroll-hint">
                        @icon('fa-arrows-alt-h') Scroll inside the table to browse rows and columns. Hold <kbd>Shift</kbd> while scrolling to move horizontally.
                    </div>

                    {{-- Table --}}
                    <div class="table-container">
                        <div class="scroll-indicator scroll-indicator-left"></div>
                        <div class="scroll-indicator scroll-indicator-right visible"></div>
                        <div class="table-responsive" id="table-scroll-container">
                            <table class="table table-bordered table-hover eoi-roi-table" id="eoi-roi-sheet-table">
                            <thead>
                                <tr>
                                    <th class="pin-cell frozen-col frozen-col-1" title="Click star to pin row to top">@icon('fa-star')</th>
                                    <th class="sortable frozen-col frozen-col-2 {{ request('sort') == 'eoi_number' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="eoi_number">EOI ID</th>
                                    <th class="sortable frozen-col frozen-col-3 frozen-col-last {{ request('sort') == 'client_name' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="client_name">Client Name</th>
                                    <th class="sortable {{ request('sort') == 'occupation' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="occupation">Nominated Occupation</th>
                                    <th>Current Job</th>
                                    <th class="sortable {{ request('sort') == 'individual_points' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="individual_points">Individual Points</th>
                                    <th class="sortable {{ request('sort') == 'marital_status' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="marital_status">Marital Status</th>
                                    <th>Partner Points</th>
                                    <th>State</th>
                                    <th>ROI Status</th>
                                    <th class="sortable {{ request('sort') == 'deadline' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="deadline">Deadline</th>
                                    <th>Comments</th>
                                    <th class="sortable {{ request('sort') == 'submission_date' ? (request('direction') == 'asc' ? 'asc' : 'desc') : '' }}" data-sort="submission_date">Last EOI/ROI Sent</th>
                                    <th>Verification Date</th>
                                    <th>Verified By</th>
                                    <th>Workflow Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if($rows->isEmpty())
                                    <tr>
                                        <td colspan="15" class="text-center text-muted py-4">
                                            @icon('fa-info-circle') No EOI/ROI records found matching your criteria.
                                        </td>
                                    </tr>
                                @else
                                    @foreach($rows as $row)
                                        @php
                                            $encodedClientId = base64_encode(convert_uuencode($row->client_id));
                                            $eoiPageUrl = route('clients.detail', [
                                                'client_id' => $encodedClientId,
                                                'client_unique_matter_ref_no' => $row->matter_id,
                                                'tab' => 'eoiroi'
                                            ]);
                                            $subclasses = json_decode($row->eoi_subclasses, true) ?? [];
                                            // eoi_states: may be JSON string (from query) or already array; fallback to legacy EOI_state
                                            $states = is_array($row->eoi_states ?? null) ? $row->eoi_states : (json_decode($row->eoi_states ?? '[]', true) ?: []);
                                            if (empty($states) && !empty($row->EOI_state ?? null)) {
                                                $states = [ $row->EOI_state ];
                                            }
                                            // Fetch full EOI record for confirmation workflow data
                                            $eoiRecord = \App\Models\ClientEoiReference::find($row->eoi_id);
                                        @endphp
                                        <tr class="{{ !empty($row->warnings_text) ? 'has-warning' : '' }}">
                                            <td class="pin-cell frozen-col frozen-col-1">
                                                {!! \App\Helpers\IconHelper::fromLegacy('fas fa-star', [
                                                    'class' => 'pin-star ' . (($row->is_pinned ?? false) ? 'pinned' : ''),
                                                    'data-eoi-id' => $row->eoi_id,
                                                    'title' => ($row->is_pinned ?? false) ? 'Unpin from top' : 'Pin to top',
                                                ]) !!}
                                            </td>
                                            <td class="frozen-col frozen-col-2"><a href="{{ $eoiPageUrl }}" class="eoi-link">{{ $row->EOI_number ?? '—' }}</a></td>
                                            <td class="frozen-col frozen-col-3 frozen-col-last"><a href="{{ $eoiPageUrl }}" class="eoi-link">{{ $row->first_name }} {{ $row->last_name }}</a></td>
                                            <td>{{ $row->EOI_occupation ?? '—' }}</td>
                                            <td class="text-muted">—</td>
                                            <td>{{ $row->individual_points ?? '—' }}</td>
                                            <td>{{ $row->marital_status ?? '—' }}</td>
                                            <td>{{ $row->partner_points ?? '—' }}</td>
                                            <td>{{ !empty($states) ? implode(', ', $states) : '—' }}</td>
                                            <td>
                                                @php
                                                    $roi = data_get($row, 'EOI_ROI') ?? data_get($row, 'eoi_roi');
                                                    $status = data_get($row, 'eoi_status');
                                                @endphp
                                                @if($roi)
                                                    {{ $roi }}
                                                @endif
                                                @if($status)
                                                    <span class="badge badge-{{ $status == 'invited' ? 'success' : ($status == 'submitted' ? 'primary' : 'secondary') }}">
                                                        {{ ucfirst($status) }}
                                                    </span>
                                                @endif
                                                @if(!$roi && !$status)
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $row->deadline ? \Carbon\Carbon::parse($row->deadline)->format('d/m/Y') : '—' }}</td>
                                            @php
                                                $sheetComment = trim((string) ($row->sheet_comments ?? ''));
                                            @endphp
                                            <td class="eoi-comments-cell comment-cell-editable"
                                                data-eoi-id="{{ $row->eoi_id }}"
                                                title="Click to add or edit comment">
                                                @if(!empty($row->warnings_text))
                                                    <span class="warning-text">{{ $row->warnings_text }}</span>
                                                @endif
                                                <span class="sheet-comment-text {{ $sheetComment === '' ? 'is-placeholder' : '' }}"
                                                      data-full-comment="{{ e($sheetComment) }}">{{ $sheetComment !== '' ? Str::limit($sheetComment, 80) : '—' }}</span>
                                                <span class="sheet-comment-hint">Click to edit</span>
                                            </td>
                                            <td>
                                                @if($eoiRecord && $eoiRecord->confirmation_email_sent_at)
                                                    {{ $eoiRecord->confirmation_email_sent_at->format('d/m/Y H:i') }}
                                                    <br><small class="text-muted">Email sent</small>
                                                @elseif($row->EOI_submission_date)
                                                    {{ \Carbon\Carbon::parse($row->EOI_submission_date)->format('d/m/Y') }}
                                                    <br><small class="text-muted">EOI submitted</small>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($eoiRecord && $eoiRecord->confirmation_date)
                                                    <div style="line-height: 1.6;">
                                                        <strong>{{ $eoiRecord->confirmation_date->format('d/m/Y H:i') }}</strong>
                                                        <br><small class="text-muted">@icon('fa-user-shield') Staff</small>
                                                    </div>
                                                    @if($eoiRecord->client_last_confirmation)
                                                        <div style="line-height: 1.6; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">
                                                            <strong>{{ $eoiRecord->client_last_confirmation->format('d/m/Y H:i') }}</strong>
                                                            <br><small class="text-success">@icon('fa-user-check') Client</small>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($eoiRecord && $eoiRecord->verifier)
                                                    <div style="line-height: 1.6;">
                                                        <strong>{{ $eoiRecord->verifier->first_name }} {{ $eoiRecord->verifier->last_name }}</strong>
                                                        <br><small class="text-muted">@icon('fa-user-shield') Staff</small>
                                                    </div>
                                                    @if($eoiRecord->client_confirmation_status === 'confirmed')
                                                        <div style="line-height: 1.6; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">
                                                            <strong class="text-success">Client Confirmed</strong>
                                                            <br><small class="text-success">@icon('fa-user-check') Client</small>
                                                        </div>
                                                    @elseif($eoiRecord->client_confirmation_status === 'amendment_requested')
                                                        <div style="line-height: 1.6; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e0e0e0;">
                                                            <strong class="text-warning">Amendment Requested</strong>
                                                            <br><small class="text-warning">@icon('fa-exclamation-triangle') Client</small>
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    // Determine workflow status
                                                    if (!$eoiRecord) {
                                                        $workflowStatus = 'draft';
                                                        $statusBadge = '<span class="badge badge-secondary">' . \App\Helpers\IconHelper::fromLegacy('fas fa-file') . ' Draft</span>';
                                                    } elseif (!$eoiRecord->staff_verified) {
                                                        $workflowStatus = 'pending_verification';
                                                        $statusBadge = '<span class="badge badge-warning">' . \App\Helpers\IconHelper::fromLegacy('fas fa-hourglass-half') . ' Pending Verification</span>';
                                                    } elseif ($eoiRecord->staff_verified && !$eoiRecord->confirmation_email_sent_at) {
                                                        $workflowStatus = 'verified';
                                                        $statusBadge = '<span class="badge badge-info">' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle') . ' Verified - Ready to Send</span>';
                                                    } elseif ($eoiRecord->client_confirmation_status === 'confirmed') {
                                                        $workflowStatus = 'completed';
                                                        $statusBadge = '<span class="badge badge-success">' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-double') . ' Client Confirmed</span>';
                                                    } elseif ($eoiRecord->client_confirmation_status === 'amendment_requested') {
                                                        $workflowStatus = 'amendment';
                                                        $statusBadge = '<span class="badge badge-danger">' . \App\Helpers\IconHelper::fromLegacy('fas fa-exclamation-triangle') . ' Amendment Requested</span>';
                                                    } elseif ($eoiRecord->client_confirmation_status === 'pending') {
                                                        $workflowStatus = 'awaiting_client';
                                                        $statusBadge = '<span class="badge badge-primary">' . \App\Helpers\IconHelper::fromLegacy('fas fa-clock') . ' Awaiting Client Response</span>';
                                                    } else {
                                                        $workflowStatus = 'unknown';
                                                        $statusBadge = '<span class="badge badge-secondary">—</span>';
                                                    }
                                                @endphp
                                                {!! $statusBadge !!}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                        </div>
                    </div>

                    {{-- Pagination --}}
                    @if($rows->hasPages())
                        <div class="card-footer">
                            {!! $rows->appends(\Request::except('page'))->render() !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
jQuery(document).ready(function($) {
    // Horizontal scroll indicators
    var $scrollContainer = $('#table-scroll-container');
    var $leftIndicator = $('.scroll-indicator-left');
    var $rightIndicator = $('.scroll-indicator-right');

    function updateScrollIndicators() {
        var scrollLeft = $scrollContainer.scrollLeft();
        var scrollWidth = $scrollContainer[0].scrollWidth;
        var clientWidth = $scrollContainer[0].clientWidth;
        var maxScroll = scrollWidth - clientWidth;
        $leftIndicator.toggleClass('visible', scrollLeft > 10);
        $rightIndicator.toggleClass('visible', scrollLeft < maxScroll - 10);
    }

    function updateFrozenColumnOffsets() {
        var table = document.getElementById('eoi-roi-sheet-table');
        if (!table) return;
        var left = 0;
        [1, 2, 3].forEach(function(index) {
            var header = table.querySelector('thead th.frozen-col-' + index);
            if (!header) return;
            table.style.setProperty('--eoi-frozen-left-' + index, left + 'px');
            left += header.getBoundingClientRect().width;
        });
    }

    $scrollContainer.on('scroll', updateScrollIndicators);
    $(window).on('resize', function() { updateScrollIndicators(); updateFrozenColumnOffsets(); });
    setTimeout(function() { updateScrollIndicators(); updateFrozenColumnOffsets(); }, 100);
    setTimeout(updateFrozenColumnOffsets, 600);

    $scrollContainer.on('wheel', function(e) {
        if (e.shiftKey && e.originalEvent.deltaY && this.scrollWidth > this.clientWidth) {
            e.preventDefault();
            this.scrollLeft += e.originalEvent.deltaY;
        }
    });

    // Per page change
    $('#per_page').on('change', function() {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', $(this).val());
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    // Filter panel toggle
    $('.filter_btn').on('click', function() {
        $('.filter_panel').toggleClass('show');
        setTimeout(updateFrozenColumnOffsets, 50);
    });

    // Datepicker - Flatpickr (loaded in layout)
    if (typeof flatpickr !== 'undefined') {
        $('.datepicker').each(function() {
            var $this = $(this);
            if (!$this.data('flatpickr')) {
                flatpickr(this, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    locale: { firstDayOfWeek: 1 },
                    onChange: function(selectedDates, dateStr) {
                        $this.val(dateStr);
                        $this.trigger('change');
                    }
                });
            }
        });
    }

    // Sortable columns
    $('.sortable').on('click', function() {
        var sortField = $(this).data('sort');
        var currentSort = '{{ request("sort") }}';
        var currentDirection = '{{ request("direction", "desc") }}';
        
        var newDirection = 'desc';
        if (currentSort === sortField && currentDirection === 'desc') {
            newDirection = 'asc';
        }
        
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', sortField);
        currentUrl.searchParams.set('direction', newDirection);
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    // Office filter auto-submit
    $('.office-filter-checkbox').on('change', function() {
        $('#officeFilterForm').submit();
    });

    // Handle star/pin clicks and inline comment editing (capture phase)
    var eoiTable = document.getElementById('eoi-roi-sheet-table');
    var activeCommentEditor = null;

    function truncateCommentText(text, maxLen) {
        text = text || '';
        return text.length <= maxLen ? text : text.substring(0, maxLen) + '…';
    }

    function renderCommentDisplay($cell, comment) {
        $cell.find('.sheet-comment-edit').remove();
        var $text = $cell.find('.sheet-comment-text');
        comment = comment || '';
        $text.show().removeClass('is-editing').attr('data-full-comment', comment);
        if (comment === '') {
            $text.addClass('is-placeholder').text('—');
        } else {
            $text.removeClass('is-placeholder').text(truncateCommentText(comment, 80));
        }
        $cell.find('.sheet-comment-hint').show();
        $cell.removeClass('is-editing-comment');
    }

    function closeCommentEditor(save) {
        if (!activeCommentEditor) return;
        var state = activeCommentEditor;
        activeCommentEditor = null;
        if (save && state.$textarea && state.$textarea.length) {
            saveCommentFromEditor(state);
            return;
        }
        renderCommentDisplay(state.$cell, state.originalComment);
    }

    function saveCommentFromEditor(state) {
        var $cell = state.$cell;
        var $textarea = state.$textarea;
        var newComment = $.trim($textarea.val());
        var originalComment = state.originalComment || '';
        if (newComment === originalComment) {
            renderCommentDisplay($cell, originalComment);
            return;
        }
        $textarea.prop('disabled', true);
        $.ajax({
            url: '{{ url('/clients/sheets/eoi-roi') }}/' + encodeURIComponent(String($cell.data('eoi-id'))) + '/comment',
            method: 'POST',
            data: { comment: newComment, _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    renderCommentDisplay($cell, response.comment || '');
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ title: 'Saved', message: response.message || 'Comment saved', position: 'topRight', timeout: 2000 });
                    }
                } else {
                    $textarea.prop('disabled', false).focus();
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ title: 'Error', message: response.message || 'Could not save comment', position: 'topRight' });
                    }
                }
            },
            error: function(xhr) {
                $textarea.prop('disabled', false).focus();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not save comment.';
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight' });
                }
            }
        });
    }

    function openCommentEditor($cell) {
        if ($cell.hasClass('is-editing-comment')) return;
        if (activeCommentEditor && !activeCommentEditor.$cell.is($cell)) {
            closeCommentEditor(true);
        }
        var $text = $cell.find('.sheet-comment-text');
        var originalComment = $text.attr('data-full-comment') || '';
        $cell.addClass('is-editing-comment');
        $cell.find('.sheet-comment-hint').hide();
        $text.hide();
        var $textarea = $('<textarea class="sheet-comment-edit" rows="3"></textarea>');
        $textarea.val(originalComment);
        $cell.append($textarea);
        activeCommentEditor = { $cell: $cell, $textarea: $textarea, originalComment: originalComment };
        $textarea.focus();
        $textarea.on('keydown', function(e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); closeCommentEditor(false); }
            else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); e.stopPropagation(); closeCommentEditor(true); }
        });
        $textarea.on('blur', function() {
            var editorState = activeCommentEditor;
            setTimeout(function() {
                if (editorState && activeCommentEditor === editorState && editorState.$cell.is($cell)) {
                    closeCommentEditor(true);
                }
            }, 150);
        });
        $textarea.on('mousedown click', function(e) { e.stopPropagation(); });
    }

    if (eoiTable) {
        eoiTable.addEventListener('click', function(e) {
            if (!e.target.closest('.sheet-comment-edit')) {
                var commentCell = e.target.closest('.comment-cell-editable');
                if (commentCell) {
                    e.preventDefault();
                    e.stopPropagation();
                    openCommentEditor($(commentCell));
                    return;
                }
            }

            var star = e.target.closest('.pin-star');
            if (!star) return;

            e.preventDefault();
            e.stopPropagation();

            var $star = $(star);
            var eoiId = $star.data('eoi-id');

            if (!eoiId) {
                if (typeof iziToast !== 'undefined') {
                    iziToast.warning({ title: 'Error', message: 'Cannot pin: missing data', position: 'topRight' });
                }
                return;
            }

            $star.css('pointer-events', 'none');

            $.ajax({
                url: '{{ url("/clients/sheets/eoi-roi") }}/' + eoiId + '/toggle-pin',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        $star.toggleClass('pinned');
                        $star.attr('title', response.is_pinned ? 'Unpin from top' : 'Pin to top');
                        if (typeof iziToast !== 'undefined') {
                            iziToast.success({
                                title: 'Success',
                                message: response.message,
                                position: 'topRight',
                                timeout: 2000
                            });
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    } else {
                        if (typeof iziToast !== 'undefined') {
                            iziToast.error({
                                title: 'Error',
                                message: response.message || 'Failed to update pin status',
                                position: 'topRight'
                            });
                        }
                        $star.css('pointer-events', 'auto');
                    }
                },
                error: function(xhr) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({
                            title: 'Error',
                            message: 'Failed to update pin status. Please try again.',
                            position: 'topRight'
                        });
                    }
                    $star.css('pointer-events', 'auto');
                }
            });
        }, true);
    }
});
</script>
@endpush
