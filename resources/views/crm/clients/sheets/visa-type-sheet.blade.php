@extends('layouts.crm_client_detail')
@section('title', ($config['title'] ?? 'Visa Sheet') . ' - ' . ($tabConfig['title'] ?? 'Ongoing'))

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<style>
    /* Remove top blank space */
    .visa-sheet-page.listing-container { 
        margin-top: 0 !important; 
        padding-top: 0 !important; 
    }
    .visa-sheet-page .listing-section {
        padding-top: 0 !important;
    }
    .visa-sheet-page .art-sheet-card {
        margin-top: 0 !important;
    }
    
    /* CRM Color Theme - Using #005792 */
    .visa-sheet-page .art-sheet-sticky-header {
        position: sticky; top: 70px; z-index: 100;
        background: linear-gradient(180deg, #f0f7fa 0%, #e6f2f7 100%);
        box-shadow: 0 2px 8px rgba(0, 87, 146, 0.1);
        border-bottom: 1px solid #b3d9ea;
        margin: 0 -1px 0 -1px;
    }
    .visa-sheet-page .art-sheet-top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 20px 0;
        border-bottom: 1px solid #b3d9ea;
    }
    .visa-sheet-page .art-sheet-title { 
        font-size: 1.2rem; 
        font-weight: 600; 
        color: #005792; 
        margin: 0;
        display: flex;
        align-items: center;
    }
    .visa-sheet-page .art-sheet-title i {
        margin-right: 8px;
        color: #005792;
    }
    .visa-sheet-page .btn-theme,
    .visa-sheet-page .btn-theme-sm {
        background: linear-gradient(135deg, #005792 0%, #004670 100%) !important;
        color: #fff !important;
        border: 1px solid #005792 !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    .visa-sheet-page .btn-theme:hover,
    .visa-sheet-page .btn-theme-sm:hover {
        background: linear-gradient(135deg, #004670 0%, #003a58 100%) !important;
        color: #fff !important;
        border-color: #004670 !important;
        box-shadow: 0 2px 8px rgba(0, 87, 146, 0.2);
    }
    .visa-sheet-page .visa-tabs-row {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 20px; background: #f0f7fa; border-bottom: 1px solid #b3d9ea;
    }
    .visa-sheet-page .visa-tabs-label {
        color: #374151 !important;
    }
    .visa-sheet-page .sheet-tabs {
        background: #b3d9ea; padding: 4px; border-radius: 8px;
        display: flex; gap: 2px; flex-wrap: wrap;
    }
    .visa-sheet-page .sheet-tab {
        padding: 10px 18px; color: #005792; text-decoration: none;
        font-weight: 600; font-size: 14px; border-radius: 6px;
        white-space: nowrap; transition: all 0.2s ease;
    }
    .visa-sheet-page .sheet-tab:hover { 
        color: #003a58; 
        background: #cce4f0; 
        text-decoration: none; 
    }
    .visa-sheet-page .sheet-tab.active {
        color: #fff; 
        background: linear-gradient(135deg, #005792 0%, #004670 100%);
        box-shadow: 0 1px 3px rgba(0, 87, 146, 0.3);
    }
    .visa-sheet-page .art-sheet-filter-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        padding: 10px 20px 12px;
        background: #fff;
        border-bottom: 1px solid #e6f2f7;
    }
    .visa-sheet-page .art-sheet-filter-bar .filter_btn {
        margin: 0;
        background: linear-gradient(135deg, #005792 0%, #004670 100%) !important;
        color: #fff !important;
        border: none !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
    }
    .visa-sheet-page .art-sheet-filter-bar .filter_btn:hover {
        background: linear-gradient(135deg, #004670 0%, #003a58 100%) !important;
        box-shadow: 0 2px 8px rgba(0, 87, 146, 0.2);
    }
    .visa-sheet-page .art-sheet-filter-bar .clear-filter-btn {
        margin: 0;
        background: #0087c3 !important;
        color: #fff !important;
        border: none !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .visa-sheet-page .art-sheet-filter-bar .clear-filter-btn:hover {
        background: #006a9a !important;
        text-decoration: none;
    }
    .visa-sheet-page .active-filters-badge {
        background: #ff6b6b;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-left: 4px;
    }
    .visa-sheet-page .comment-cell .sheet-comment-text { 
        max-height: 3.6em; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: pre-wrap; 
        word-break: break-word; 
        display: block;
    }
    .visa-sheet-page .comment-cell-editable {
        cursor: text;
        min-width: 140px;
        max-width: 240px;
        vertical-align: top;
    }
    .visa-sheet-page .comment-cell-editable:hover .sheet-comment-text:not(.is-editing) {
        background: #f8fafc;
        box-shadow: inset 0 0 0 1px #cbd5e1;
        border-radius: 4px;
    }
    .visa-sheet-page .comment-cell-editable .sheet-comment-text.is-placeholder {
        color: #94a3b8;
        font-style: italic;
    }
    .visa-sheet-page .comment-cell-editable .sheet-comment-edit {
        width: 100%;
        min-width: 130px;
        min-height: 72px;
        font-size: 12px;
        line-height: 1.4;
        padding: 6px 8px;
        border: 1px solid #3b82f6;
        border-radius: 4px;
        resize: vertical;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
    }
    .visa-sheet-page .comment-cell-editable .sheet-comment-edit:disabled {
        opacity: 0.7;
        cursor: wait;
    }
    .visa-sheet-page .comment-cell-editable .sheet-comment-hint {
        display: block;
        font-size: 10px;
        color: #64748b;
        margin-top: 2px;
    }
    .visa-sheet-page .checklist-status-cell .checklist-status-select { 
        min-width: 140px; 
        max-width: 180px; 
    }
    .visa-sheet-page .reminder-cell { min-width: 120px; }
    .visa-sheet-page .checklist-sent-cell { min-width: 120px; }
    .sheet-email-reminder-modal { z-index: 1060 !important; }
    .sheet-sms-reminder-modal { z-index: 1060 !important; }
    .modal-backdrop.show { z-index: 1055 !important; }
    .visa-sheet-page .checklist-not-sent-text { color: #374151; font-weight: 500; }
    .visa-sheet-page .filter_panel { display: none; }
    .visa-sheet-page .filter_panel.show { display: block; }
    .visa-sheet-page .filter_panel label { color: #374151; }
    
    /* Star/Pin Column Styles */
    .visa-sheet-page .pin-cell {
        width: 45px;
        min-width: 45px;
        max-width: 45px;
        text-align: center;
        padding: 8px !important;
    }
    .visa-sheet-page .pin-star {
        width: 18px;
        height: 18px;
        cursor: pointer;
        color: #cbd5e0;
        transition: color 0.2s ease, transform 0.2s ease;
        vertical-align: middle;
    }
    .visa-sheet-page .pin-star:hover {
        color: #f59e0b;
        transform: scale(1.2);
    }
    .visa-sheet-page .pin-star.pinned {
        color: #f59e0b;
        filter: drop-shadow(0 0 4px rgba(245, 158, 11, 0.35));
    }
    .visa-sheet-page .pin-star.pinned:hover {
        color: #cbd5e0;
    }
    .visa-sheet-page .visa-sheet-col-payment-request {
        min-width: 10rem;
    }
    .visa-sheet-page .visa-sheet-col-payment-receipt {
        min-width: 11rem;
    }
    .visa-sheet-page .refused-visa-type-cell .refused-visa-type-select {
        min-width: 11rem;
        max-width: 14rem;
    }
    .visa-sheet-page .refused-visa-suggest-hint {
        display: block;
        font-size: 11px;
        margin-top: 2px;
    }

    /* Table scroll area: vertical + horizontal scroll with sticky header */
    .visa-sheet-page .card-body {
        overflow: visible;
        padding: 20px 30px 30px;
    }
    .visa-sheet-page .table-container {
        position: relative;
    }
    .visa-sheet-page .scroll-indicator {
        position: absolute;
        top: 0;
        bottom: 20px;
        width: 40px;
        pointer-events: none;
        z-index: 14;
        transition: opacity 0.3s ease;
    }
    .visa-sheet-page .scroll-indicator-left {
        left: 0;
        background: linear-gradient(to right, rgba(255, 255, 255, 0.95), transparent);
        opacity: 0;
    }
    .visa-sheet-page .scroll-indicator-right {
        right: 0;
        background: linear-gradient(to left, rgba(255, 255, 255, 0.95), transparent);
    }
    .visa-sheet-page .scroll-indicator-left.visible,
    .visa-sheet-page .scroll-indicator-right.visible {
        opacity: 1;
    }
    .visa-sheet-page #visa-table-scroll {
        max-height: calc(100vh - 280px);
        min-height: 320px;
        overflow: auto;
        position: relative;
        -webkit-overflow-scrolling: touch;
    }
    .visa-sheet-page #visa-table-scroll::-webkit-scrollbar {
        height: 10px;
        width: 10px;
    }
    .visa-sheet-page #visa-table-scroll::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 8px;
    }
    .visa-sheet-page #visa-sheet-table {
        border-collapse: separate;
        border-spacing: 0;
    }
    .visa-sheet-page #visa-sheet-table thead th {
        position: sticky;
        top: 0;
        z-index: 11;
        background: #f8fafc !important;
        color: #334155 !important;
        box-shadow: inset 0 -2px 0 #e2e8f0;
    }
    .visa-sheet-page #visa-sheet-table .frozen-col {
        position: sticky;
        z-index: 10;
        background: #fff;
        overflow: visible;
    }
    .visa-sheet-page #visa-sheet-table thead th.frozen-col {
        z-index: 13;
        background: #f8fafc !important;
        overflow: visible;
    }
    .visa-sheet-page #visa-sheet-table .frozen-col-1 { left: var(--frozen-left-1, 0); }
    .visa-sheet-page #visa-sheet-table .frozen-col-2 { left: var(--frozen-left-2, 45px); }
    .visa-sheet-page #visa-sheet-table .frozen-col-3 { left: var(--frozen-left-3, 205px); }
    .visa-sheet-page #visa-sheet-table .frozen-col-3.frozen-col-last::after {
        content: '';
        position: absolute;
        top: 0;
        right: -6px;
        bottom: 0;
        width: 6px;
        pointer-events: none;
        background: linear-gradient(to right, rgba(15, 23, 42, 0.08), transparent);
    }
    .visa-sheet-page #visa-sheet-table tbody tr:hover .frozen-col {
        background: #f8fafc;
    }
    .visa-sheet-page #visa-sheet-table .matter-col {
        min-width: 150px;
        max-width: 200px;
    }
    .visa-sheet-page #visa-sheet-table .crm-ref-col {
        min-width: 110px;
    }
    .visa-sheet-page #visa-sheet-table .frozen-col,
    .visa-sheet-page #visa-sheet-table .matter-col,
    .visa-sheet-page #visa-sheet-table .crm-ref-col,
    .visa-sheet-page #visa-sheet-table .client-name-col {
        max-width: none;
        white-space: nowrap;
    }
    .visa-sheet-page #visa-sheet-table .client-name-col {
        min-width: 140px;
    }

    /* Sortable column headers */
    .visa-sheet-page .sortable {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 22px !important;
    }
    .visa-sheet-page .sortable:hover {
        background: rgba(0, 87, 146, 0.08) !important;
    }
    .visa-sheet-page .sortable::after {
        content: '⇅';
        font-family: inherit;
        font-weight: 600;
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.35;
        font-size: 11px;
        line-height: 1;
    }
    .visa-sheet-page .sortable.asc::after {
        content: '↑';
        opacity: 1;
        color: #005792;
    }
    .visa-sheet-page .sortable.desc::after {
        content: '↓';
        opacity: 1;
        color: #005792;
    }
</style>
@endsection

@section('content')
@php
    $sheetRoute = $config['route'] ?? 'clients.sheets.visa-type';
    $sheetRouteParams = ['visaType' => $visaType];
    $showRefusedVisaType = (bool) ($showRefusedVisaType ?? false);
    $emptyColspan = $tab === 'checklist' ? 15 : ($tab === 'discontinue' ? 15 : 13);
    if ($showRefusedVisaType) {
        $emptyColspan++;
    }
    $currentSort = request('sort');
    $currentDirection = request('direction', 'asc');
    $sortThClass = function (string $field) use ($currentSort, $currentDirection): string {
        if ($currentSort !== $field) {
            return '';
        }

        return $currentDirection === 'asc' ? 'asc' : 'desc';
    };
    $freezeThirdIsRefused = $showRefusedVisaType;
    $freezeThirdIsCrmRef = ! $freezeThirdIsRefused && $tab !== 'checklist';
    $freezeThirdIsClientName = ! $freezeThirdIsRefused && $tab === 'checklist';
@endphp
<div class="listing-container visa-sheet-page art-sheet-page">
    <section class="listing-section">
        <div class="listing-section-body">
            <div class="card art-sheet-card">
                <div class="art-sheet-sticky-header">
                    <div class="art-sheet-top-bar">
                        <h4 class="art-sheet-title">@icon('fa-clipboard-list') {{ $config['title'] ?? 'Visa Sheet' }}</h4>
                        <a href="{{ route('clients.index') }}" class="btn btn-theme btn-theme-sm">
                            @icon('fa-arrow-left') Back to Clients
                        </a>
                    </div>
                    <div class="visa-tabs-row">
                        <span class="visa-tabs-label font-weight-bold" style="font-size: 13px; color: #374151;">Tabs:</span>
                        <div class="sheet-tabs">
                            @foreach(['ongoing' => 'Ongoing', 'lodged' => 'Lodged', 'checklist' => 'Checklist', 'discontinue' => 'Discontinue'] as $t => $label)
                                <a href="{{ route($sheetRoute, array_merge($sheetRouteParams, request()->except('tab'), ['tab' => $t])) }}"
                                   class="sheet-tab {{ $tab === $t ? 'active' : '' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="art-sheet-filter-bar">
                        <button type="button" class="btn btn-theme btn-theme-sm filter_btn">
                            @icon('fa-filter') Filters
                            @if($activeFilterCount > 0)
                                <span class="active-filters-badge">{{ $activeFilterCount }}</span>
                            @endif
                        </button>
                        @if($activeFilterCount > 0)
                            <a href="{{ route($sheetRoute, array_merge($sheetRouteParams, ['tab' => $tab, 'clear_filters' => 1])) }}" class="clear-filter-btn">
                                @icon('fa-undo') Clear Filters
                            </a>
                        @endif
                        <label class="mb-0 ml-2 mr-2" style="font-size: 13px; color: #374151;">Migration Agent:</label>
                        <select name="assignee" id="visa_assignee" class="form-control" style="max-width: 180px;">
                            <option value="all" {{ request('assignee') === 'all' ? 'selected' : '' }}>All</option>
                            <option value="me" {{ request('assignee') === 'me' ? 'selected' : '' }}>Me</option>
                            @foreach($assignees as $a)
                                <option value="{{ $a->id }}" {{ request('assignee') == $a->id ? 'selected' : '' }}>
                                    {{ trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')) ?: '—' }}
                                </option>
                            @endforeach
                        </select>
                        <label class="mb-0 ml-2 mr-1" style="font-size: 13px; color: #374151;">Show:</label>
                        <select name="per_page" id="visa_per_page" class="form-control per-page-select" style="max-width: 100px;">
                            @foreach([10, 25, 50, 100, 200] as $opt)
                                <option value="{{ $opt }}" {{ $perPage == $opt ? 'selected' : '' }}>{{ $opt }}/page</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter_panel {{ $activeFilterCount > 0 ? 'show' : '' }}">
                        <form action="{{ route($sheetRoute, $sheetRouteParams) }}" method="get" id="visaFilterForm">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <input type="hidden" name="assignee" value="{{ request('assignee') }}">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label class="mb-2" style="font-weight: 600; color: #374151;">@icon('fa-building') Filter by Branch:</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach($branches as $b)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="branch[]" value="{{ $b->id }}" id="branch_{{ $b->id }}"
                                                    {{ in_array($b->id, (array)request('branch', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="branch_{{ $b->id }}" style="cursor: pointer;">{{ $b->office_name }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2">
                                    <label>Matter Type</label>
                                    <select name="matter_type" class="form-control">
                                        <option value="">All matter types</option>
                                        @foreach($matterTypes as $val => $lbl)
                                            <option value="{{ $val }}" {{ request('matter_type') == $val ? 'selected' : '' }}>{{ Str::limit($lbl, 40) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($showRefusedVisaType)
                                <div class="col-md-2">
                                    <label>{{ $refusedVisaTypeLabel ?? 'Category' }}</label>
                                    <select name="refused_visa_type" class="form-control">
                                        <option value="">All</option>
                                        @foreach($refusedVisaTypeOptions ?? [] as $val => $lbl)
                                            <option value="{{ $val }}" {{ request('refused_visa_type') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                                <div class="col-md-2">
                                    <label>Current Stage</label>
                                    <select name="current_stage" class="form-control">
                                        <option value="">All stages</option>
                                        @foreach($currentStages as $val => $lbl)
                                            <option value="{{ $val }}" {{ request('current_stage') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Visa Expiry From</label>
                                    <input type="text" name="visa_expiry_from" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('visa_expiry_from') }}" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label>Visa Expiry To</label>
                                    <input type="text" name="visa_expiry_to" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('visa_expiry_to') }}" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label>Deadline From</label>
                                    <input type="text" name="deadline_from" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('deadline_from') }}" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label>Deadline To</label>
                                    <input type="text" name="deadline_to" class="form-control datepicker" placeholder="dd/mm/yyyy" value="{{ request('deadline_to') }}" autocomplete="off">
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-4">
                                    <label>Search</label>
                                    <input type="text" name="search" class="form-control" placeholder="Name, CRM Ref, Matter ref..." value="{{ request('search') }}">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary mr-2">Apply Filters</button>
                                    <a href="{{ route($sheetRoute, array_merge($sheetRouteParams, ['tab' => $tab, 'clear_filters' => 1])) }}" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card-body">
                    @if($setupRequired ?? false)
                        <div class="alert alert-warning mb-3" role="alert">
                            @icon('fa-tools', ['class' => 'mr-1'])
                            <strong>Setup required:</strong> Run <code>php artisan migrate</code> to create <code>{{ $config['reference_table'] ?? 'client_matter_references' }}</code>, <code>{{ $config['checklist_status_column'] ?? 'tr_checklist_status' }}</code> (on client_matters), and <code>{{ $config['reminders_table'] ?? 'matter_reminders' }}</code>. Until then, the 4 tabs and filters are visible for structure review but no data will load.
                        </div>
                    @endif
                    <div class="visa-sheet-scroll-hint px-3 pt-2 mb-2" style="font-size: 13px; color: #64748b;">
                        @icon('fa-arrows-alt-h') Scroll inside the table to browse rows and columns. Hold <kbd>Shift</kbd> while scrolling to move horizontally.
                    </div>
                    <div class="table-container">
                        <div class="scroll-indicator scroll-indicator-left"></div>
                        <div class="scroll-indicator scroll-indicator-right visible"></div>
                        <div class="table-responsive" id="visa-table-scroll">
                            <table class="table table-bordered table-hover art-table" id="visa-sheet-table">
                                <thead>
                                    <tr>
                                        <th class="pin-cell frozen-col frozen-col-1" title="Click star to pin row to top">@icon('fa-star')</th>
                                        <th class="matter-col frozen-col frozen-col-2 sortable {{ $sortThClass('matter') }}" data-sort="matter">Matter / Course</th>
                                        @if($showRefusedVisaType)
                                        <th class="frozen-col frozen-col-3 frozen-col-last">{{ $refusedVisaTypeLabel ?? 'Category' }}</th>
                                        @endif
                                        @if($tab !== 'checklist')
                                        <th class="crm-ref-col {{ $freezeThirdIsCrmRef ? 'frozen-col frozen-col-3 frozen-col-last' : '' }} sortable {{ $sortThClass('crm_ref') }}" data-sort="crm_ref">CRM Ref</th>
                                        @endif
                                        <th class="client-name-col {{ $freezeThirdIsClientName ? 'frozen-col frozen-col-3 frozen-col-last' : '' }} sortable {{ $sortThClass('name') }}" data-sort="name">Client Name</th>
                                        <th class="sortable {{ $sortThClass('dob') }}" data-sort="dob">DOB</th>
                                        @if($tab === 'checklist')
                                        <th class="text-nowrap visa-sheet-col-payment-request" title="Payment Request">Payment Request</th>
                                        @elseif($tab === 'ongoing')
                                        <th class="text-nowrap visa-sheet-col-payment-receipt" title="Payment Receipt">Payment Receipt</th>
                                        @else
                                        <th>Payment Received</th>
                                        @endif
                                        @if($tab !== 'checklist')
                                        <th>Branch</th>
                                        @endif
                                        <th class="sortable {{ $sortThClass('assignee') }}" data-sort="assignee">Migration Agent</th>
                                        <th class="sortable {{ $sortThClass('visa_expiry') }}" data-sort="visa_expiry">Visa Expiry</th>
                                        <th class="sortable {{ $sortThClass('deadline') }}" data-sort="deadline">Deadline</th>
                                        @if($tab !== 'checklist')
                                        <th class="sortable {{ $sortThClass('stage') }}" data-sort="stage">Current Stage</th>
                                        @endif
                                        @if($tab === 'discontinue')
                                        <th>Outcome</th>
                                        <th>Decision Note</th>
                                        @endif
                                        <th>Comment</th>
                                        @if($tab === 'checklist')
                                        <th>Status</th>
                                        <th>Checklist sent</th>
                                        <th>Email reminder</th>
                                        <th>SMS reminder</th>
                                        <th>Phone reminder</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($rows->isEmpty())
                                        <tr>
                                            <td colspan="{{ $emptyColspan }}" class="text-center text-muted py-4">
                                                @if($setupRequired ?? false)
                                                    @icon('fa-info-circle') Run migrations to enable data. Add a {{ strtoupper($visaType) }} matter type and assign matters to clients.
                                                @else
                                                    @icon('fa-info-circle') No {{ $config['title'] ?? $visaType }} records found for this tab. Add a {{ strtoupper($visaType) }} matter type and assign matters to clients.
                                                @endif
                                            </td>
                                        </tr>
                                    @else
                                        @foreach($rows as $row)
                                            @php
                                                $isLead = !empty($row->is_lead);
                                                // Same unified CRM detail for leads and clients (Admin record types client | lead).
                                                $encodedId = base64_encode(convert_uuencode((string)(int) $row->client_id));
                                                $detailUrl = route('clients.detail', [
                                                    'client_id' => $encodedId,
                                                    'client_unique_matter_ref_no' => $row->client_unique_matter_no ?? '',
                                                ]);
                                                $matterId = $row->matter_internal_id ?? '';
                                                $clientName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                                            @endphp
                                            <tr style="cursor: pointer;" onclick="window.location.href='{{ $detailUrl }}'">
                                                <td class="pin-cell frozen-col frozen-col-1" onclick="event.stopPropagation();">
                                                    @if(!$isLead || !empty($row->matter_internal_id))
                                                    {!! \App\Helpers\IconHelper::fromLegacy('fas fa-star', [
                                                        'class' => 'pin-star ' . (($row->is_pinned ?? false) ? 'pinned' : ''),
                                                        'data-client-id' => $row->client_id,
                                                        'data-matter-id' => $matterId,
                                                        'data-visa-type' => $visaType,
                                                        'title' => ($row->is_pinned ?? false) ? 'Unpin from top' : 'Pin to top',
                                                    ]) !!}
                                                    @else
                                                    <span class="text-muted" title="Lead">{{ __('Lead') }}</span>
                                                    @endif
                                                </td>
                                                <td class="matter-col frozen-col frozen-col-2" onclick="event.stopPropagation();"><a href="{{ $detailUrl }}" class="art-link">{{ $row->matter_title ?? $row->client_unique_matter_no ?? $row->other_reference ?? '—' }}</a></td>
                                                @if($showRefusedVisaType)
                                                <td onclick="event.stopPropagation();" class="refused-visa-type-cell frozen-col frozen-col-3 frozen-col-last">
                                                    @if(! $isLead && ! empty($matterId))
                                                        @php
                                                            $currentRefused = $row->refused_visa_type ?? '';
                                                            $matterTitleForSuggest = $row->matter_title ?? '';
                                                        @endphp
                                                        <select class="form-control form-control-sm refused-visa-type-select"
                                                                data-client-id="{{ $row->client_id }}"
                                                                data-matter-id="{{ $matterId }}"
                                                                data-visa-type="{{ $visaType }}"
                                                                data-matter-title="{{ e($matterTitleForSuggest) }}"
                                                                title="{{ $refusedVisaTypeLabel ?? 'Category' }}">
                                                            <option value="">— Select —</option>
                                                            @foreach($refusedVisaTypeOptions ?? [] as $val => $lbl)
                                                                <option value="{{ $val }}" {{ $currentRefused === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                                            @endforeach
                                                        </select>
                                                        <small class="refused-visa-suggest-hint text-muted" style="display:none;"></small>
                                                    @else
                                                        <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                @endif
                                                @if($tab !== 'checklist')
                                                <td class="crm-ref-col {{ $freezeThirdIsCrmRef ? 'frozen-col frozen-col-3 frozen-col-last' : '' }}" onclick="event.stopPropagation();"><a href="{{ $detailUrl }}" class="art-link">{{ $row->crm_ref ?? '—' }}</a></td>
                                                @endif
                                                <td class="client-name-col {{ $freezeThirdIsClientName ? 'frozen-col frozen-col-3 frozen-col-last' : '' }}" onclick="event.stopPropagation();"><a href="{{ $detailUrl }}" class="art-link">{{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: '—' }}</a></td>
                                                <td>{{ $row->dob ? \Carbon\Carbon::parse($row->dob)->format('d/m/Y') : '—' }}</td>
                                                <td title="{{ $tab === 'checklist' ? 'Our Cost (Block Fees)' : ($tab === 'ongoing' ? 'Current Funds Held (Account → Client Funds Ledger)' : '') }}">
                                                    @if($tab === 'checklist')
                                                        @if(isset($row->checklist_block_fee) && $row->checklist_block_fee !== null && $row->checklist_block_fee !== '')
                                                            ${{ number_format((float) $row->checklist_block_fee, 2) }}
                                                        @else
                                                            —
                                                        @endif
                                                    @elseif($tab === 'ongoing' && !$isLead)
                                                        @if($row->current_funds_held !== null)
                                                            ${{ number_format((float) $row->current_funds_held, 2) }}
                                                        @else
                                                            —
                                                        @endif
                                                    @else
                                                        @if(($row->total_payment ?? 0) > 0)
                                                            ${{ number_format((float)($row->total_payment ?? 0), 2) }}
                                                        @elseif($row->payment_display_note ?? null)
                                                            {{ $row->payment_display_note }}
                                                        @else
                                                            —
                                                        @endif
                                                    @endif
                                                </td>
                                                @if($tab !== 'checklist')
                                                <td>{{ $row->branch_name ?? '—' }}</td>
                                                @endif
                                                <td>{{ trim($row->assignee_name ?? '') ?: '—' }}</td>
                                                <td>{{ isset($row->visa_expiry) && $row->visa_expiry && $row->visa_expiry != '0000-00-00' ? \Carbon\Carbon::parse($row->visa_expiry)->format('d/m/Y') : '—' }}</td>
                                                <td>{{ $row->deadline ? \Carbon\Carbon::parse($row->deadline)->format('d/m/Y') : '—' }}</td>
                                                @if($tab !== 'checklist')
                                                <td>{{ $row->application_stage ?? '—' }}</td>
                                                @endif
                                                @if($tab === 'discontinue')
                                                <td>{{ $row->decision_outcome ?? '—' }}</td>
                                                <td class="comment-cell" title="{{ $row->decision_note ?? '' }}">{{ Str::limit($row->decision_note ?? '—', 50) }}</td>
                                                @endif
                                                @php
                                                    $commentText = trim((string) ($row->sheet_comment_text ?? ''));
                                                    $canEditComment = ! $isLead && ! empty($matterId);
                                                @endphp
                                                <td class="art-comments-cell comment-cell {{ $canEditComment ? 'comment-cell-editable' : '' }}"
                                                    @if($canEditComment)
                                                        data-client-id="{{ $row->client_id }}"
                                                        data-matter-id="{{ $matterId }}"
                                                        title="Click to add or edit comment"
                                                    @else
                                                        title="{{ $commentText }}"
                                                    @endif
                                                >
                                                    <span class="sheet-comment-text {{ $commentText === '' ? 'is-placeholder' : '' }}"
                                                          data-full-comment="{{ $commentText }}">{{ $commentText !== '' ? Str::limit($commentText, 60) : '—' }}</span>
                                                    @if($canEditComment)
                                                        <span class="sheet-comment-hint">Click to edit</span>
                                                    @endif
                                                </td>
                                                @if($tab === 'checklist')
                                                <td onclick="event.stopPropagation();" class="checklist-status-cell">
                                                    @if(!empty($row->matter_internal_id))
                                                    @php
                                                        $currentStatus = $row->tr_checklist_status ?? 'active';
                                                        $statusLabels = ['active' => 'Active', 'convert_to_client' => 'Convert to client', 'discontinue' => 'Discontinue', 'hold' => 'Hold'];
                                                    @endphp
                                                    <select class="form-control form-control-sm checklist-status-select" data-matter-id="{{ $matterId }}" data-visa-type="{{ $visaType }}" title="Status">
                                                        @foreach($statusLabels as $val => $label)
                                                        <option value="{{ $val }}" {{ $currentStatus === $val ? 'selected' : '' }}>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @elseif($isLead)
                                                    <span class="badge badge-info" title="{{ __('Lead row without a client matter; status is fixed until a matter exists.') }}">Lead</span>
                                                    @else
                                                    <span class="text-muted">—</span>
                                                    @endif
                                                </td>
                                                <td onclick="event.stopPropagation();" class="checklist-sent-cell">
                                                    @if(!empty($row->checklist_sent_at))
                                                        {{ \Carbon\Carbon::parse($row->checklist_sent_at)->format('d/m/Y') }}
                                                    @else
                                                        <span class="checklist-not-sent-text">Not sent</span>
                                                    @endif
                                                </td>
                                                <td onclick="event.stopPropagation();" class="reminder-cell">
                                                    @if(!empty($row->email_reminder_latest ?? null))
                                                        {{ \Carbon\Carbon::parse($row->email_reminder_latest)->format('d/m/Y') }}@if(($row->email_reminder_count ?? 0) > 0) ({{ $row->email_reminder_count }})@endif
                                                    @else
                                                        —
                                                    @endif
                                                    <br><button type="button"
                                                        class="btn btn-sm btn-outline-secondary mt-1 checklist-email-reminder-btn"
                                                        data-client-id="{{ $row->client_id }}"
                                                        data-client-email="{{ $row->client_email ?? '' }}"
                                                        data-client-name="{{ $clientName }}"
                                                        data-crm-ref="{{ $row->crm_ref ?? '' }}"
                                                        data-matter-id="{{ $matterId }}"
                                                        title="Email reminder">Email reminder</button>
                                                </td>
                                                <td onclick="event.stopPropagation();" class="reminder-cell">
                                                    @if(!empty($row->sms_reminder_latest ?? null))
                                                        {{ \Carbon\Carbon::parse($row->sms_reminder_latest)->format('d/m/Y') }}@if(($row->sms_reminder_count ?? 0) > 0) ({{ $row->sms_reminder_count }})@endif
                                                    @else
                                                        —
                                                    @endif
                                                    <br><button type="button"
                                                        class="btn btn-sm btn-outline-secondary mt-1 checklist-sms-reminder-btn"
                                                        data-client-id="{{ $row->client_id }}"
                                                        data-client-name="{{ $clientName }}"
                                                        data-matter-id="{{ $matterId }}"
                                                        title="SMS reminder">SMS reminder</button>
                                                </td>
                                                <td onclick="event.stopPropagation();" class="reminder-cell">
                                                    @if(!empty($row->phone_reminder_latest ?? null))
                                                        {{ \Carbon\Carbon::parse($row->phone_reminder_latest)->format('d/m/Y') }}@if(($row->phone_reminder_count ?? 0) > 0) ({{ $row->phone_reminder_count }})@endif
                                                    @else
                                                        —
                                                    @endif
                                                    <br><button type="button" class="btn btn-sm btn-outline-secondary mt-1 checklist-phone-reminder-btn" data-matter-id="{{ $matterId }}" data-visa-type="{{ $visaType }}" title="Phone reminder">Phone reminder</button>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($rows->hasPages())
                        <div class="card-footer">
                            {!! $rows->appends(array_merge(request()->except('page'), ['tab' => $tab]))->render() !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</div>

@if($tab === 'checklist')
    @include('crm.clients.partials.sheet-email-reminder-modal')
    @include('crm.clients.partials.sheet-sms-reminder-modal')
@endif
@endsection

@push('scripts')
<script>
jQuery(document).ready(function($) {
    var $scroll = $('#visa-table-scroll');
    var $left = $('.scroll-indicator-left');
    var $right = $('.scroll-indicator-right');
    function updateScroll() {
        if (!$scroll.length || !$scroll[0]) return;
        var sl = $scroll.scrollLeft();
        var sw = $scroll[0].scrollWidth;
        var cw = $scroll[0].clientWidth;
        $left.toggleClass('visible', sl > 10);
        $right.toggleClass('visible', sl < sw - cw - 10);
    }
    function updateFrozenColumnOffsets() {
        var table = document.getElementById('visa-sheet-table');
        if (!table) return;
        var left = 0;
        [1, 2, 3].forEach(function(index) {
            var header = table.querySelector('thead th.frozen-col-' + index);
            if (!header) return;
            table.style.setProperty('--frozen-left-' + index, left + 'px');
            left += header.getBoundingClientRect().width;
        });
    }

    $scroll.on('scroll resize', updateScroll);
    setTimeout(function() {
        updateScroll();
        updateFrozenColumnOffsets();
    }, 100);
    setTimeout(updateFrozenColumnOffsets, 600);
    $(window).on('resize', updateFrozenColumnOffsets);
    $scroll.on('wheel', function(e) {
        if (e.shiftKey && e.originalEvent.deltaY && this.scrollWidth > this.clientWidth) {
            e.preventDefault();
            this.scrollLeft += e.originalEvent.deltaY;
        }
    });
    $('#visa_per_page').on('change', function() {
        var u = new URL(window.location.href);
        u.searchParams.set('per_page', $(this).val());
        u.searchParams.delete('page');
        window.location.href = u.toString();
    });
    $('#visa_assignee').on('change', function() {
        var u = new URL(window.location.href);
        u.searchParams.set('assignee', $(this).val());
        u.searchParams.delete('page');
        window.location.href = u.toString();
    });
    $('.filter_btn').on('click', function() {
        $('.filter_panel').toggleClass('show');
        setTimeout(updateFrozenColumnOffsets, 50);
    });
    $('.visa-sheet-page .sortable').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var sortField = $(this).data('sort');
        var currentSort = @json($currentSort);
        var currentDirection = @json($currentDirection);
        var newDirection = (currentSort === sortField && currentDirection === 'asc') ? 'desc' : 'asc';
        var url = new URL(window.location.href);
        url.searchParams.set('sort', sortField);
        url.searchParams.set('direction', newDirection);
        url.searchParams.delete('page');
        window.location.href = url.toString();
    });
    if (typeof flatpickr !== 'undefined') {
        $('.datepicker').each(function() {
            if (!$(this).data('flatpickr')) {
                flatpickr(this, { dateFormat: 'd/m/Y', allowInput: true, locale: { firstDayOfWeek: 1 } });
            }
        });
    }

    // Capture phase so reminder/pin clicks work despite td onclick stopPropagation and row navigation
    var visaTable = document.getElementById('visa-sheet-table');
    if (visaTable) {
        visaTable.addEventListener('click', function(e) {
        var emailBtn = e.target.closest('.checklist-email-reminder-btn');
        if (emailBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof openSheetEmailReminder === 'function') {
                openSheetEmailReminder($(emailBtn));
            }
            return;
        }

        var smsBtn = e.target.closest('.checklist-sms-reminder-btn');
        if (smsBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof openSheetSmsReminder === 'function') {
                openSheetSmsReminder($(smsBtn));
            }
            return;
        }

        var phoneBtn = e.target.closest('.checklist-phone-reminder-btn');
        if (phoneBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof handleSheetPhoneReminder === 'function') {
                handleSheetPhoneReminder($(phoneBtn));
            }
            return;
        }

        var star = e.target.closest('.pin-star');
        if (!star) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        var $star = $(star);
        var clientId = $star.data('client-id');
        var matterId = $star.data('matter-id');
        var visaType = $star.data('visa-type');
        
        if (!clientId || !matterId) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'Cannot pin: missing data', position: 'topRight' });
            }
            return;
        }
        
        // Disable clicking during request
        $star.css('pointer-events', 'none');
        
        $.ajax({
            url: '{{ url("/clients/sheets") }}/' + visaType + '/toggle-pin',
            method: 'POST',
            data: {
                client_id: clientId,
                matter_internal_id: matterId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    // Toggle star appearance
                    $star.toggleClass('pinned');
                    $star.attr('title', response.is_pinned ? 'Unpin from top' : 'Pin to top');
                    
                    // Show success message
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({
                            title: 'Success',
                            message: response.message,
                            position: 'topRight',
                            timeout: 2000
                        });
                    }
                    
                    // Reload page after short delay to show updated order
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
        }, true); // capture phase
    }

    $('.visa-sheet-page .checklist-status-select').each(function() {
        $(this).data('previous-status', $(this).val());
    });

    $(document).on('focus', '.visa-sheet-page .checklist-status-select', function() {
        $(this).data('previous-status', $(this).val());
    });
    $(document).on('change', '.visa-sheet-page .checklist-status-select', function() {
        var $sel = $(this);
        var matterId = $sel.data('matter-id');
        var visaType = $sel.data('visa-type');
        var status = $sel.val();
        var prev = $sel.data('previous-status');
        if (!matterId || !visaType) {
            if (prev !== undefined) {
                $sel.val(prev);
            }
            return;
        }
        $sel.prop('disabled', true);
        $.ajax({
            url: '{{ url('/clients/sheets') }}/' + encodeURIComponent(String(visaType)) + '/checklist-status',
            method: 'POST',
            data: {
                matter_internal_id: matterId,
                status: status,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $sel.prop('disabled', false);
                if (response.success) {
                    $sel.data('previous-status', status);
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ title: 'Saved', message: response.message || 'Status updated', position: 'topRight', timeout: 2000 });
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 450);
                } else {
                    if (prev !== undefined) {
                        $sel.val(prev);
                    }
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ title: 'Error', message: response.message || 'Could not update status', position: 'topRight' });
                    }
                }
            },
            error: function(xhr) {
                $sel.prop('disabled', false);
                if (prev !== undefined) {
                    $sel.val(prev);
                }
                var msg = 'Could not update status.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight' });
                }
            }
        });
    });

    @if($showRefusedVisaType ?? false)
    var refusedVisaTypeLabels = @json($refusedVisaTypeOptions ?? []);
    var refusedVisaTypeSuggestRules = @json($refusedVisaTypeSuggestRules ?? []);

    function suggestRefusedVisaTypeFromTitle(title) {
        title = (title || '').toLowerCase();
        if (!title) {
            return '';
        }
        var keys = Object.keys(refusedVisaTypeSuggestRules);
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var needles = refusedVisaTypeSuggestRules[key] || [];
            for (var j = 0; j < needles.length; j++) {
                if (title.indexOf(String(needles[j]).toLowerCase()) !== -1) {
                    return key;
                }
            }
        }
        return '';
    }

    $('.visa-sheet-page .refused-visa-type-select').each(function() {
        var $sel = $(this);
        if ($sel.val()) {
            return;
        }
        var suggested = suggestRefusedVisaTypeFromTitle($sel.data('matter-title'));
        if (suggested && refusedVisaTypeLabels[suggested]) {
            $sel.siblings('.refused-visa-suggest-hint').text('Suggested: ' + refusedVisaTypeLabels[suggested]).show();
            $sel.attr('data-suggested', suggested);
        }
    });

    $('.visa-sheet-page .refused-visa-type-select').each(function() {
        $(this).data('previous-refused', $(this).val());
    });

    $(document).on('focus', '.visa-sheet-page .refused-visa-type-select', function() {
        $(this).data('previous-refused', $(this).val());
    });

    $(document).on('change', '.visa-sheet-page .refused-visa-type-select', function() {
        var $sel = $(this);
        var clientId = $sel.data('client-id');
        var matterId = $sel.data('matter-id');
        var visaType = $sel.data('visa-type');
        var refusedType = $sel.val();
        var prev = $sel.data('previous-refused');
        if (!clientId || !matterId || !visaType) {
            if (prev !== undefined) {
                $sel.val(prev);
            }
            return;
        }
        $sel.prop('disabled', true);
        $.ajax({
            url: '{{ url('/clients/sheets') }}/' + encodeURIComponent(String(visaType)) + '/refused-visa-type',
            method: 'POST',
            data: {
                client_id: clientId,
                matter_internal_id: matterId,
                refused_visa_type: refusedType,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $sel.prop('disabled', false);
                if (response.success) {
                    $sel.data('previous-refused', refusedType);
                    $sel.siblings('.refused-visa-suggest-hint').hide();
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ title: 'Saved', message: response.message || 'Saved', position: 'topRight', timeout: 2000 });
                    }
                } else {
                    if (prev !== undefined) {
                        $sel.val(prev);
                    }
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ title: 'Error', message: response.message || 'Could not save', position: 'topRight' });
                    }
                }
            },
            error: function(xhr) {
                $sel.prop('disabled', false);
                if (prev !== undefined) {
                    $sel.val(prev);
                }
                var msg = 'Could not save.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight' });
                }
            }
        });
    });
    @endif

    var visaTypeForComments = @json($visaType);
    var activeCommentEditor = null;

    function truncateCommentText(text, maxLen) {
        text = text || '';
        if (text.length <= maxLen) {
            return text;
        }
        return text.substring(0, maxLen) + '…';
    }

    function renderCommentDisplay($cell, comment) {
        $cell.find('.sheet-comment-edit').remove();
        var $text = $cell.find('.sheet-comment-text');
        comment = comment || '';
        $text.show().removeClass('is-editing').attr('data-full-comment', comment);
        if (comment === '') {
            $text.addClass('is-placeholder').text('—');
        } else {
            $text.removeClass('is-placeholder').text(truncateCommentText(comment, 60));
        }
        $cell.find('.sheet-comment-hint').show();
        $cell.removeClass('is-editing-comment');
    }

    function closeCommentEditor(save) {
        if (!activeCommentEditor) {
            return;
        }
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
            url: '{{ url('/clients/sheets') }}/' + encodeURIComponent(String(visaTypeForComments)) + '/comment',
            method: 'POST',
            data: {
                client_id: $cell.data('client-id'),
                matter_internal_id: $cell.data('matter-id'),
                comment: newComment,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    var saved = response.comment || '';
                    renderCommentDisplay($cell, saved);
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
                var msg = 'Could not save comment.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight' });
                }
            }
        });
    }

    function openCommentEditor($cell) {
        if ($cell.hasClass('is-editing-comment')) {
            return;
        }

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

        activeCommentEditor = {
            $cell: $cell,
            $textarea: $textarea,
            originalComment: originalComment
        };

        $textarea.focus();

        $textarea.on('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeCommentEditor(false);
            } else if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                e.stopPropagation();
                closeCommentEditor(true);
            }
        });

        $textarea.on('blur', function() {
            var editorState = activeCommentEditor;
            setTimeout(function() {
                if (editorState && activeCommentEditor === editorState && editorState.$cell.is($cell)) {
                    closeCommentEditor(true);
                }
            }, 150);
        });

        $textarea.on('mousedown click', function(e) {
            e.stopPropagation();
        });
    }

    // Capture phase (same as pin) so row navigation does not run before the editor opens
    if (visaTable) {
        visaTable.addEventListener('click', function(e) {
            if (e.target.closest('.sheet-comment-edit')) {
                return;
            }
            var commentCell = e.target.closest('.comment-cell-editable');
            if (!commentCell) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            openCommentEditor($(commentCell));
        }, true);
    }

    @if($tab === 'checklist')
    var sheetEmailMacroValues = null;
    var sheetSmsClientName = '';

    function showSheetModal($modal) {
        if (!$modal || !$modal.length) {
            return;
        }
        if (typeof $modal.modal === 'function') {
            $modal.modal('show');
        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        }
    }

    function showSheetEmailModal() {
        showSheetModal($('#emailmodal'));
    }

    function showSheetSmsModal() {
        showSheetModal($('#sheetSmsModal'));
    }

    function initSheetEmailReminderTinyMce() {
        var run = function() {
            if (typeof tinymce === 'undefined' || !$('#compose_email_message').length) {
                return;
            }
            if (tinymce.get('compose_email_message')) {
                return;
            }
            tinymce.init({
                license_key: 'gpl',
                selector: '#compose_email_message',
                height: 280,
                menubar: false,
                plugins: ['lists', 'link', 'autolink'],
                toolbar: 'bold italic underline | bullist numlist | link',
                branding: false,
                promotion: false,
                setup: function(editor) {
                    editor.on('change', function() { editor.save(); });
                }
            });
        };
        if (typeof window.ensureTinyMCELoaded === 'function') {
            window.ensureTinyMCELoaded().then(run);
            return;
        }
        run();
    }

    window.saveComposeEmail = function() {
        if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
            tinymce.get('compose_email_message').save();
        }
        if (typeof customValidate === 'function') {
            customValidate('sendmail');
        }
    };

    function applySheetEmailMacroReplacements(text, clientFirstName, crmRef) {
        text = text || '';
        var first = clientFirstName || '';
        if (first) {
            first = first.charAt(0).toUpperCase() + first.slice(1);
        }
        text = text.replace(/\{Client First Name\}/g, first);
        text = text.replace(/\{client reference\}/g, crmRef || '');
        if (sheetEmailMacroValues) {
            Object.keys(sheetEmailMacroValues).forEach(function(key) {
                if (key === 'PDF_url_for_sign') {
                    return;
                }
                var val = sheetEmailMacroValues[key] || '';
                text = text.replace(new RegExp('\\{' + key + '\\}', 'g'), val);
                text = text.replace(new RegExp('\\$\\{' + key + '\\}', 'g'), val);
            });
        }
        return text;
    }

    function resetSheetEmailReminderForm() {
        $('#compose_checklist_reminder_type').val('email');
        $('#compose_email_subject').val('');
        $('#sheet_reminder_template').val('');
        sheetEmailMacroValues = null;
        if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
            tinymce.get('compose_email_message').setContent('');
        } else {
            $('#compose_email_message').val('');
        }
    }

    function resetSheetSmsReminderForm() {
        $('#sheet_sms_checklist_reminder').val('1');
        $('#sheet_sms_message').val('').trigger('input');
        $('#sheet_sms_template').val('');
    }

    function updateSheetSmsCharCounter() {
        var len = ($('#sheet_sms_message').val() || '').length;
        var segSize = 160;
        var segs = Math.max(1, Math.ceil(len / segSize));
        var left = (segs * segSize) - len;
        $('#sheet_sms_char_count').text(len);
        $('#sheet_sms_char_max').text(segs * segSize);
        $('#sheet_sms_chars_remaining').html('&nbsp;&middot;&nbsp; ' + left + ' left in this SMS');
        $('#sheet_sms_segment_badge')
            .text(segs + ' SMS')
            .removeClass('badge-success badge-warning')
            .addClass(segs === 1 ? 'badge-success' : 'badge-warning');
    }

    function loadSheetSmsPhones(clientId, onDone) {
        var $phoneSelect = $('#sheet_sms_phone');
        $phoneSelect.empty().append('<option value="">Loading phone numbers...</option>');
        $.ajax({
            url: '{{ URL::to("/clients/fetchClientContactNo") }}',
            type: 'POST',
            dataType: 'json',
            data: { _token: '{{ csrf_token() }}', client_id: clientId },
            success: function(response) {
                var data;
                try {
                    data = (typeof response === 'string' && response.trim()) ? JSON.parse(response) : (response || {});
                } catch (e) {
                    data = {};
                }
                $phoneSelect.empty().append('<option value="">Select phone number...</option>');
                if (data.clientContacts && data.clientContacts.length > 0) {
                    data.clientContacts.forEach(function(contact) {
                        var fullPhone = (contact.country_code || '') + (contact.phone || '');
                        var label = (contact.contact_type || 'Phone') + ': ' + fullPhone;
                        $phoneSelect.append($('<option></option>').val(fullPhone).text(label));
                    });
                } else {
                    $phoneSelect.append('<option value="">No phone numbers found</option>');
                }
            },
            error: function() {
                $phoneSelect.empty().append('<option value="">Error loading phone numbers</option>');
            },
            complete: function() {
                if (typeof onDone === 'function') {
                    onDone();
                }
            }
        });
    }

    function loadSheetSmsTemplates() {
        var $templateSelect = $('#sheet_sms_template');
        $templateSelect.empty().append('<option value="">Type your own message or select a template...</option>');
        $.ajax({
            url: '{{ route("clients.sms.templates.active") }}',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    response.data.forEach(function(template) {
                        $templateSelect.append($('<option></option>').val(template.id).text(template.title));
                    });
                }
            }
        });
    }

    window.openSheetEmailReminder = function($btn) {
        var clientId = $btn.data('client-id');
        var clientEmail = ($btn.data('client-email') || '').trim();
        var clientName = ($btn.data('client-name') || '').trim();
        var matterId = $btn.data('matter-id') || '';
        var crmRef = $btn.data('crm-ref') || '';

        if (!clientId) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'Missing client data', position: 'topRight' });
            }
            return;
        }
        if (!clientEmail) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'Client email is required to send a reminder.', position: 'topRight' });
            }
            return;
        }
        if (!matterId) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'A client matter is required to record this reminder.', position: 'topRight' });
            }
            return;
        }
        if (!$('#emailmodal').length) {
            if (typeof iziToast !== 'undefined') {
                iziToast.error({ title: 'Error', message: 'Email popup failed to load. Please refresh the page.', position: 'topRight' });
            }
            return;
        }

        resetSheetEmailReminderForm();
        $('#sheet_reminder_client_id').val(clientId).data('first-name', clientName.split(' ')[0] || '').data('crm-ref', crmRef);
        $('#sheet_reminder_email_to').val(clientId);
        $('#compose_client_matter_id').val(matterId);
        $('#compose_checklist_reminder_type').val('email');
        $('#sheet_reminder_to_display').text(clientName + ' <' + clientEmail + '>');
        $('#sheetEmailReminderLabel').text('Email Reminder — ' + (clientName || 'Client'));

        var $templateSelect = $('#sheet_reminder_template');
        if (!$templateSelect.data('default-html')) {
            $templateSelect.data('default-html', $templateSelect.html());
        } else {
            $templateSelect.html($templateSelect.data('default-html'));
        }
        $templateSelect.val('');

        $.get('{{ route('clients.getComposeDefaults') }}', { client_matter_id: matterId })
            .done(function(res) {
                sheetEmailMacroValues = res.macro_values || null;
                if (res.matter_templates && res.matter_templates.length) {
                    $templateSelect.empty().append('<option value="">Select</option>');
                    res.matter_templates.forEach(function(t) {
                        $templateSelect.append($('<option></option>').attr('value', t.id).text(t.name || 'Template'));
                    });
                }
            })
            .always(function() {
                initSheetEmailReminderTinyMce();
                showSheetEmailModal();
            });
    };

    window.openSheetSmsReminder = function($btn) {
        var clientId = $btn.data('client-id');
        var clientName = ($btn.data('client-name') || '').trim();
        var matterId = $btn.data('matter-id') || '';

        if (!clientId) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'Missing client data', position: 'topRight' });
            }
            return;
        }
        if (!matterId) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'A client matter is required to record this reminder.', position: 'topRight' });
            }
            return;
        }
        if (!$('#sheetSmsModal').length) {
            if (typeof iziToast !== 'undefined') {
                iziToast.error({ title: 'Error', message: 'SMS popup failed to load. Please refresh the page.', position: 'topRight' });
            }
            return;
        }

        sheetSmsClientName = clientName;
        resetSheetSmsReminderForm();
        $('#sheet_sms_client_id').val(clientId);
        $('#sheet_sms_matter_id').val(matterId);
        $('#sheetSmsReminderLabel').html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-sms') : '<i class="fas fa-sms"></i>') + ' SMS Reminder — ' + (clientName || 'Client'));
        loadSheetSmsTemplates();
        loadSheetSmsPhones(clientId, function() {
            var hasPhone = false;
            $('#sheet_sms_phone option').each(function() {
                if ($(this).val()) {
                    hasPhone = true;
                }
            });
            if (!hasPhone) {
                if (typeof iziToast !== 'undefined') {
                    iziToast.warning({ title: 'Error', message: 'Client phone number is required to send a reminder.', position: 'topRight' });
                }
                return;
            }
            showSheetSmsModal();
        });
    };

    window.handleSheetPhoneReminder = function($btn) {
        var matterId = $btn.data('matter-id');
        var visaType = $btn.data('visa-type');

        if (!matterId || !visaType) {
            if (typeof iziToast !== 'undefined') {
                iziToast.warning({ title: 'Error', message: 'Cannot record phone reminder: missing matter data', position: 'topRight' });
            }
            return;
        }
        if (!confirm('Record a phone reminder for this client?')) {
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: '{{ url('/clients/sheets') }}/' + encodeURIComponent(String(visaType)) + '/record-reminder',
            method: 'POST',
            data: {
                matter_internal_id: matterId,
                type: 'phone',
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ title: 'Recorded', message: response.message || 'Phone reminder recorded', position: 'topRight', timeout: 2000 });
                    }
                    setTimeout(function() { window.location.reload(); }, 500);
                } else if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: response.message || 'Could not record phone reminder', position: 'topRight' });
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not record phone reminder.';
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight' });
                }
            }
        });
    };

    $('#emailmodal').on('hidden.bs.modal', function() {
        resetSheetEmailReminderForm();
    });

    $('#sheet_reminder_template').on('change', function() {
        var templateId = $(this).val();
        if (!templateId) {
            return;
        }
        var clientFirst = $('#sheet_reminder_client_id').data('first-name') || '';
        var crmRef = $('#sheet_reminder_client_id').data('crm-ref') || '';
        $.get('{{ route('clients.gettemplates') }}', { id: templateId }, function(response) {
            var res = typeof response === 'string' ? JSON.parse(response) : response;
            if (!res) {
                return;
            }
            var subject = applySheetEmailMacroReplacements(res.subject || '', clientFirst, crmRef);
            var body = applySheetEmailMacroReplacements(res.description || '', clientFirst, crmRef);
            $('#compose_email_subject').val(subject);
            if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                tinymce.get('compose_email_message').setContent(body);
            } else {
                $('#compose_email_message').val(body);
            }
        });
    });

    $('#emailmodal').on('shown.bs.modal', function() {
        initSheetEmailReminderTinyMce();
    });

    $('#sheetSmsModal').on('hidden.bs.modal', function() {
        resetSheetSmsReminderForm();
    });

    $('#sheet_sms_message').on('input', updateSheetSmsCharCounter);

    $('#sheet_sms_template').on('change', function() {
        var id = $(this).val();
        if (!id) {
            return;
        }
        $.ajax({
            url: '/clients/sms-template/' + id + '/compose',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (!response.success || !response.data) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ title: 'Error', message: response.message || 'Could not load SMS template', position: 'topRight' });
                    }
                    return;
                }
                var name = sheetSmsClientName || '';
                var processedMessage = response.data.message || '';
                processedMessage = processedMessage.replace(/\{first_name\}/g, name.split(' ')[0] || '');
                processedMessage = processedMessage.replace(/\{last_name\}/g, name.split(' ').slice(1).join(' ') || '');
                processedMessage = processedMessage.replace(/\{client_name\}/g, name);
                processedMessage = processedMessage.replace(/\{full_name\}/g, name);
                if (processedMessage.length > 320) {
                    processedMessage = processedMessage.slice(0, 320);
                }
                $('#sheet_sms_message').val(processedMessage).trigger('input');
            }
        });
    });

    $('#sheetSmsForm').on('submit', function(e) {
        e.preventDefault();
        var $submitBtn = $('#sheetSendSmsBtn');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Sending...');

        $.ajax({
            url: '{{ route("clients.sms.send") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                client_id: $('#sheet_sms_client_id').val(),
                client_matter_id: $('#sheet_sms_matter_id').val(),
                checklist_reminder: 1,
                phone: $('#sheet_sms_phone').val(),
                message: $('#sheet_sms_message').val()
            },
            success: function(response) {
                if (response.success) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ title: 'Success', message: 'SMS sent successfully!', position: 'topRight' });
                    }
                    $('#sheetSmsModal').modal('hide');
                    setTimeout(function() { window.location.reload(); }, 800);
                } else if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: response.message || 'Failed to send SMS', position: 'topRight' });
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while sending SMS';
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ title: 'Error', message: msg, position: 'topRight' });
                }
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    @endif
});
</script>
@endpush
