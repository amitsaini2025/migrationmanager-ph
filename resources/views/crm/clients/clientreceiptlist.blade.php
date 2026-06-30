@extends('layouts.crm_client_detail')
@section('title', 'Client Receipt List')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Modern Page Styling */
    .listing-container {
        background: #f8fafc;
        min-height: 100vh;
    }

    .listing-section {
        padding-top: 24px !important;
    }

    /* Modern Card Styling */
    .listing-container .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        overflow: hidden;
        background: white;
    }

    /* Modern Header with Gradient - Sticky so title + Filter/Validate stay visible on scroll */
    .listing-container .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 24px 32px;
        border-bottom: none;
        position: sticky;
        top: 0;
        z-index: 102;
    }

    .listing-container .card-header h4 {
        color: white;
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.5px;
    }

    /* Per Page Selector */
    .listing-container #per_page {
        border: 2px solid rgba(255, 255, 255, 0.3) !important;
        border-radius: 10px !important;
        background: rgba(255, 255, 255, 0.2) !important;
        color: white !important;
        font-weight: 600 !important;
        padding: 8px 12px !important;
        backdrop-filter: blur(10px);
    }

    .listing-container #per_page option {
        background: #667eea;
        color: white;
    }

    /* Modern Button Styling */
    .listing-container .btn {
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 14px;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .listing-container .btn:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .listing-container .btn:active {
    }

    .listing-container .btn-theme {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        backdrop-filter: blur(10px);
    }

    .listing-container .btn-theme:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .listing-container .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .listing-container .btn-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .listing-container .Validate_Receipt {
        background: white !important;
        color: #667eea !important;
        font-weight: 700;
    }

    .listing-container .Validate_Receipt[style*="background-color: #394eea"],
    .Validate_Receipt[style*="background-color: #394eea"] {
        color: white !important;
    }

    .listing-container .Validate_Receipt:hover {
        background: rgba(255, 255, 255, 0.95) !important;
    }

    /* Modern Filter Panel */
    .listing-container .filter_panel {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid #e2e8f0;
    }

    .listing-container .filter_panel h4 {
        color: #1e293b !important;
        font-size: 18px !important;
        font-weight: 700 !important;
        margin-bottom: 16px !important;
        padding-bottom: 12px !important;
        border-bottom: 2px solid #667eea;
        display: inline-block;
    }

    /* Modern Form Inputs */
    .listing-container .form-group label {
        color: #475569 !important;
        font-weight: 600 !important;
        font-size: 13px !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px !important;
    }

    .listing-container .form-control {
        border: 2px solid #e2e8f0 !important;
        border-radius: 10px !important;
        padding: 10px 16px !important;
        font-size: 14px !important;
        background: white !important;
        height: auto !important;
    }

    .listing-container .form-control:focus {
        border-color: #667eea !important;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
        outline: none;
    }

    .listing-container .ts-wrapper .ts-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        min-height: 44px;
        padding: 6px 16px;
    }
    .listing-container .ts-wrapper.focus .ts-control {
        border-color: #667eea;
    }

    .listing-container .btn-info {
        background: linear-gradient(135deg, #00b4db 0%, #0083b0 100%);
        color: white;
    }

    /* Modern Table Styling */
    .listing-container .card-body {
        padding: 32px;
    }

    .listing-container .table-responsive {
        border-radius: 12px;
        overflow: auto;
        max-height: calc(100vh - 280px);
        box-shadow: 0 0 0 1px #e2e8f0;
    }

    .listing-container .table {
        margin-bottom: 0;
        font-size: 14px;
    }

    .listing-container .table thead {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }

    .listing-container .table thead th {
        border: none;
        padding: 16px 20px;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 101;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    }

    .listing-container .table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .listing-container .table tbody tr:hover {
        background: #f8fafc;
        transform: scale(1.001);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .listing-container .table tbody td {
        padding: 16px 20px;
        vertical-align: middle;
        border: none;
        color: #334155;
    }

    /* Modern Status Badges */
    .modern-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .modern-badge.badge-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .modern-badge.badge-danger {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    /* Modern Icons */
    .listing-container [data-lucide="circle-check"] {
        color: #10b981;
        font-size: 16px;
        margin-right: 6px;
    }

    /* Modern Checkbox */
    .listing-container .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
    }

    /* Modern Error/Success Messages */
    .listing-container .custom-error-msg {
        border-radius: 12px;
        margin: 0 32px 20px;
        padding: 16px 20px;
        font-weight: 600;
        display: none;
    }

    .listing-container .custom-error-msg.alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 2px solid #10b981;
    }

    .listing-container .custom-error-msg.alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 2px solid #ef4444;
    }

    /* Modern Pagination */
    .listing-container .card-footer {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        padding: 20px 32px;
        border-radius: 0 0 16px 16px;
    }

    .listing-container .pagination {
        margin: 0;
    }

    .listing-container .pagination .page-link {
        border: 2px solid #e2e8f0;
        color: #667eea;
        margin: 0 4px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .listing-container .pagination .page-link:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
        transform: translateY(-2px);
    }

    .listing-container .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
    }

    /* No Records State */
    .listing-container .table tbody td[colspan] {
        padding: 60px 20px !important;
        text-align: center;
        color: #94a3b8;
        font-size: 16px;
        font-weight: 600;
    }

    /* Amount Styling */
    .listing-container .table tbody td[id^="deposit_"],
    .listing-container .table tbody td[id^="withdraw_"] {
        font-weight: 700;
        font-family: 'Courier New', monospace;
    }

    .listing-container .table tbody td[id^="deposit_"] {
        color: #059669;
    }

    .listing-container .table tbody td[id^="withdraw_"] {
        color: #dc2626;
    }

    /* Sortable Column Headers */
    .listing-container .sortable-header {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 30px !important;
        transition: all 0.2s ease;
    }

    .listing-container .sortable-header:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea;
    }

    .listing-container .sort-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-flex;
        flex-direction: column;
        gap: 2px;
        opacity: 0.3;
        transition: all 0.2s ease;
    }

    .listing-container .sortable-header:hover .sort-icon {
        opacity: 0.6;
    }

    .listing-container .sort-icon i {
        font-size: 8px;
        line-height: 1;
        color: #475569;
    }

    .listing-container .sortable-header.sort-asc .sort-icon {
        opacity: 1;
    }

    .listing-container .sortable-header.sort-asc .sort-icon [data-lucide="chevron-up"] {
        color: #667eea;
        font-size: 10px;
    }

    .listing-container .sortable-header.sort-desc .sort-icon {
        opacity: 1;
    }

    .listing-container .sortable-header.sort-desc .sort-icon [data-lucide="chevron-down"] {
        color: #667eea;
        font-size: 10px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .listing-container .card-header {
            padding: 20px;
        }

        .listing-container .card-header h4 {
            font-size: 20px;
            width: 100%;
        }

        .listing-container .card-body {
            padding: 20px;
        }

        .listing-container .filter_panel {
            padding: 16px;
        }

        .listing-container .table {
            font-size: 12px;
        }

        .listing-container .table thead th,
        .listing-container .table tbody td {
            padding: 12px 10px;
        }
    }

    /* Animation for filter panel */
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

    .listing-container .filter_panel {
        animation: slideDown 0.3s ease;
    }

    @include('crm.clients.partials.enhanced-date-filter-styles')
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center" style="width: 100%;">
                        <h4 style="margin-bottom: 0; flex-shrink: 0;">All Clients Receipt List</h4>
                        
                        <div class="d-flex align-items-center" style="margin-left: auto;">
                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                            <a href="{{ route('clients.analytics-dashboard') }}" class="btn btn-theme btn-theme-sm" title="View Financial Analytics Dashboard" style="margin-right: 10px;">@icon('fa-chart-line') Analytics</a>
                            @endif
                            <select name="per_page" id="per_page" class="form-control" style="width: auto; min-width: 80px; border-radius: 0; border: 1px solid #ddd; padding: 6px 12px; margin-right: 10px;">
                                <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20</option>
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                <option value="200" {{ $perPage == 200 ? 'selected' : '' }}>200</option>
                                <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500</option>
                            </select>
                            
                            <a href="javascript:;" style="background: #394eea;color: white; margin-right: 10px;"  class="btn btn-theme btn-theme-sm filter_btn">@icon('fa-filter') Filter</a>
                            
                            @if (Auth::user()->role == '1' && Auth::user()->email == 'celestyparmar.62@gmail.com')
                                <button class="btn btn-danger Delete_Receipt" style="margin-right: 10px;">
                                    @icon('fa-trash-alt')
                                    Delete Receipt
                                </button>
                            @endif

                            <button class="btn btn-primary Validate_Receipt" style="background-color: #394eea !important; color: white !important;">
                                @icon('fa-check-circle')
                                Validate Receipt
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="filter_panel">
                        <h4 style="margin-top: 0; margin-bottom: 8px;">
                            Search By Details
                            @if(request()->hasAny(['client_id', 'client_matter_id', 'amount', 'client_fund_ledger_type', 'receipt_validate', 'date_filter_type', 'from_date', 'to_date', 'financial_year']))
                                <span class="active-filters-badge">
                                    @icon('fa-filter')
                                    {{ collect([request('client_id'), request('client_matter_id'), request('amount'), request('client_fund_ledger_type'), request('receipt_validate'), request('date_filter_type'), request('from_date'), request('to_date'), request('financial_year')])->filter()->count() }} Active
                                </span>
                            @endif
                        </h4>
                        <form action="{{URL::to('/clients/clientreceiptlist')}}" method="get" id="filterForm">
                            <div class="row d-flex align-items-end">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="client_id" class="col-form-label" style="color:#4a5568 !important;">Client ID</label>
                                        <select name="client_id" id="client_id" class="form-control mm-select" style="max-width: 150px;">
                                            <option value="">Select Client</option>
                                            @foreach($clientIds as $client)
                                                <option value="{{ $client->client_id }}" {{ request('client_id') == $client->client_id ? 'selected' : '' }}>
                                                    {{ $client->first_name.' '.$client->last_name.'('.$client->client_unique_id.')' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="client_matter_id" class="col-form-label" style="color:#4a5568 !important;">Client Matter ID</label>
                                        <select name="client_matter_id" id="client_matter_id" class="form-control mm-select" style="max-width: 150px;">
                                            <option value="">Select Matter</option>
                                            @foreach($matterIds as $matter)
                                                <option value="{{ $matter->client_matter_id }}" {{ request('client_matter_id') == $matter->client_matter_id ? 'selected' : '' }}>
                                                    {{ $matter->client_unique_id }}-{{ $matter->client_unique_matter_no }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label for="amount" class="col-form-label" style="color:#000;">Amount</label>
                                        <input type="number" name="amount" value="{{ old('amount', Request::get('amount')) }}" class="form-control" step="0.01" placeholder="Enter amount" style="max-width: 120px;">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="client_fund_ledger_type" class="col-form-label" style="color:#000;">Type</label>
                                        <select class="form-control" name="client_fund_ledger_type" style="width: 100%;">
                                            <option value="">Select</option>
                                            <option value="Deposit" {{ request('client_fund_ledger_type') == 'Deposit' ? 'selected' : '' }}>Deposit</option>
                                            <option value="Fee Transfer" {{ request('client_fund_ledger_type') == 'Fee Transfer' ? 'selected' : '' }}>Fee Transfer</option>
                                            <option value="Disbursement" {{ request('client_fund_ledger_type') == 'Disbursement' ? 'selected' : '' }}>Disbursement</option>
                                            <option value="Refund" {{ request('client_fund_ledger_type') == 'Refund' ? 'selected' : '' }}>Refund</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="receipt_validate" class="col-form-label" style="color:#000;">Receipt Validate</label>
                                        <select class="form-control" name="receipt_validate" style="width: 100%;">
                                            <option value="">Select</option>
                                            <option value="1" {{ request('receipt_validate') == '1' ? 'selected' : '' }}>Yes</option>
                                            <option value="0" {{ request('receipt_validate') == '0' ? 'selected' : '' }}>No</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Enhanced Date Filter -->
                            @include('crm.clients.partials.enhanced-date-filter')

                            <!-- Action Buttons -->
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container" style="margin-top: 20px;">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">
                                            @icon('fa-search') Search
                                        </button>
                                        <a class="btn btn-info" href="{{URL::to('/clients/clientreceiptlist')}}">
                                            @icon('fa-redo') Reset All
                                        </a>
                                        @if(request()->hasAny(['client_id', 'client_matter_id', 'amount', 'client_fund_ledger_type', 'receipt_validate', 'date_filter_type', 'from_date', 'to_date', 'financial_year']))
                                            <button type="button" class="clear-filter-btn ml-2" id="clearDateFilters">
                                                @icon('fa-times-circle') Clear Date Filters
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">
                                        <div class="custom-checkbox custom-control">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
                                            <label for="checkbox-all" class="custom-control-label"></label>
                                        </div>
                                    </th>
                                    <!--<th>SNo.</th>-->
                                    <th class="sortable-header {{ request('sort_by') == 'client_id' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="client_id">
                                        Client ID
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'client_matter' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="client_matter">
                                        Client Matter
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'name' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="name">
                                        Name
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'trans_date' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="trans_date">
                                        Date
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <!--<th>Entry Date</th>-->
                                    <th class="sortable-header {{ request('sort_by') == 'type' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="type">
                                        Type
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'reference' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="reference">
                                        Reference
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'funds_in' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="funds_in">
                                        Funds In (+)
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'funds_out' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="funds_out">
                                        Funds Out (-)
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'validate_receipt' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="validate_receipt">
                                        Receipt Validate
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'validated_by' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="validated_by">
                                        Validated By
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                    @foreach (@$lists as $list)
                                        <?php
                                        $client_info = \App\Models\Admin::select('id','first_name','last_name','client_id')->where('id', $list->client_id)->first();
                                        $client_full_name = $client_info ? $client_info->first_name.' '.$client_info->last_name : '-';
                                        $client_id_display = $client_info ? $client_info->client_id : '-';

                                        $client_matter_info = \App\Models\ClientMatter::select('client_unique_matter_no')->where('id', $list->client_matter_id)->first();
                                        $client_matter_display = $client_matter_info ? $client_id_display.'-'.$client_matter_info->client_unique_matter_no : '-';


                                        $id = $list->id ?? '-';
                                        $trans_date = $list->trans_date ?? '-';
                                        //$entry_date = $list->entry_date ?? '-';
                                        $Reference = $list->trans_no ?? '-';
                                        $client_fund_ledger_type = $list->client_fund_ledger_type ?? '-';
                                        $invoice_no = !empty($list->invoice_no) ? '('.$list->invoice_no.')' : '';
                                        $total_fund_in_amount = $list->deposit_amount ?? '-';
                                        $total_fund_out_amount = $list->withdraw_amount ?? '-';
                                        $receipt_validate = ($list->validate_receipt == 1) ? 'Yes' : 'No';

                                        if(isset($list->voided_or_validated_by) && $list->voided_or_validated_by != ""){
                                            $validate_by = \App\Models\Staff::select('id','first_name','last_name')->where('id', $list->voided_or_validated_by)->first();
                                            $validate_by_full_name = $validate_by ? $validate_by->first_name.' '.$validate_by->last_name : 'N/A';
                                        } else {
                                            $validate_by_full_name = "-";
                                        }
                                        ?>
                                        <tr id="id_{{@$list->id}}">
                                            <td class="text-center">
                                                <div class="custom-checkbox custom-control">
                                                    <input data-id="{{@$list->id}}"
                                                           data-email="{{@$list->email ?? ''}}"
                                                           data-name="{{$client_full_name}}"
                                                           data-clientid="{{$client_id_display}}"
                                                           data-receiptid="{{@$list->receipt_id}}"
                                                           type="checkbox"
                                                           data-checkboxes="mygroup"
                                                           class="cb-element custom-control-input your-checkbox"
                                                           id="checkbox-{{$loop->index}}">
                                                    <label for="checkbox-{{$loop->index}}" class="custom-control-label"></label>
                                                </div>
                                            </td>
                                            <!--<td>{{--$id--}}</td>-->
                                            <td>{{ $client_id_display }}</td>
                                            <td>{{ $client_matter_display }}</td>
                                            <td>{{ $client_full_name }}</td>
                                            <td>{{ $trans_date }}</td>
                                            <td>{{ $client_fund_ledger_type }} {{$invoice_no }}</td>
                                            <td>{{ $Reference }}</td>
                                            <td id="deposit_{{@$list->id}}">{{ is_numeric($total_fund_in_amount) ? '$'.number_format((float)$total_fund_in_amount, 2) : '-' }}</td>
                                            <td id="withdraw_{{@$list->id}}">{{ is_numeric($total_fund_out_amount) ? '$'.number_format((float)$total_fund_out_amount, 2) : '-' }}</td>
                                            <td id="validate_{{@$list->id}}">
                                                <span class="modern-badge {{ $receipt_validate == 'Yes' ? 'badge-success' : 'badge-danger' }}">
                                                    @if($receipt_validate == 'Yes')
                                                        @icon('fa-check')
                                                    @else
                                                        @icon('fa-times')
                                                    @endif
                                                    {{ $receipt_validate }}
                                                </span>
                                            </td>

                                            <td id="validateby_{{@$list->id}}">{{ $validate_by_full_name }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="11" style="text-align: center; padding: 60px 20px;">
                                            <div style="opacity: 0.5;">
                                                @icon('fa-inbox', ['style' => 'font-size: 48px; color: #cbd5e1; margin-bottom: 16px;'])
                                                <div style="font-size: 18px; font-weight: 600; color: #64748b;">No Records Found</div>
                                                <div style="font-size: 14px; color: #94a3b8; margin-top: 8px;">Try adjusting your filters to find what you're looking for</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="card-footer">
                    {!! $lists->appends(\Request::except('page'))->render() !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
{{-- Bootstrap datepicker removed - using Flatpickr via enhanced-date-filter-scripts --}}
<script>
console.log('=== CLIENT RECEIPT LIST JS LOADED - Version 2.0 ===');
console.log('Timestamp:', new Date().toISOString());
jQuery(document).ready(function($){
    // Check if any filter has a value and keep panel open
    function checkFilterValues() {
        var hasFilterValue = false;
        
        // Check all filter inputs
        $('.filter_panel input, .filter_panel select').each(function() {
            if ($(this).val() && $(this).val() !== '') {
                hasFilterValue = true;
                return false; // Break the loop
            }
        });
        
        // If any filter has a value, show the panel and make header/filter fixed
        if (hasFilterValue) {
            $('.listing-container .filter_panel').show();
            $('.listing-container').addClass('filters-active');
            console.log('Filters active class added to listing-container');
        } else {
            $('.listing-container').removeClass('filters-active');
            console.log('Filters active class removed from listing-container');
        }
    }
    
    // Check on page load
    checkFilterValues();
    
    // Re-check when any filter input changes
    $('.filter_panel input, .filter_panel select').on('change keyup', function() {
        checkFilterValues();
    });

    // Handle records per page dropdown change
    $('#per_page').on('change', function() {
        var perPage = $(this).val();
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.delete('page'); // Reset to first page when changing per_page
        window.location.href = currentUrl.toString();
    });

    $('.listing-container .filter_btn').on('click', function(){
		$('.listing-container .filter_panel').toggle();
	});

    // Searchable dropdowns (mmSelect / Tom Select)
    $('.listing-container .mm-select').mmSelect({
        placeholder: "Search...",
        allowClear: true,
        width: '100%',
        dropdownParent: $('body') // Ensure dropdown appears above other elements
    }).on('mmselect:open', function() {
        $('.ts-dropdown').css('z-index', '9999');
    });

    // Enhanced Date Filter Scripts
    @include('crm.clients.partials.enhanced-date-filter-scripts')

    $('.listing-container [data-checkboxes]').each(function () {
        var me = $(this),
        group = me.data('checkboxes'),
        role = me.data('checkbox-role');

        me.change(function () {
            var all = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"])'),
            checked = $('.listing-container [data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"]):checked'),
            dad = $('.listing-container [data-checkboxes="' + group + '"][data-checkbox-role="dad"]'),
            total = all.length,
            checked_length = checked.length;
            if (role == 'dad') {
                if (me.is(':checked')) {
                    all.prop('checked', true);

                } else {
                    all.prop('checked', false);

                }
            } else {
                if (checked_length >= total) {
                    dad.prop('checked', true);
                    $('.listing-container .is_checked_client').show();
                    $('.listing-container .is_checked_clientn').hide();
                } else {
                    dad.prop('checked', false);
                    $('.listing-container .is_checked_client').hide();
                    $('.listing-container .is_checked_clientn').show();
                }
            }

        });
    });

    var clickedReceiptIds = [];
    $(document).delegate('.listing-container .your-checkbox', 'click', function(){
        var clicked_receipt_id = $(this).data('id');
        if ($(this).is(':checked')) {
            //clickedReceiptIds.push(clicked_receipt_id);

            // For deletion, allow only one receipt to be selected
            if ($('.listing-container .Delete_Receipt').length > 0) {
                $('.listing-container .your-checkbox').not(this).prop('checked', false);
                clickedReceiptIds = [clicked_receipt_id];
            } else {
                clickedReceiptIds.push(clicked_receipt_id);
            }
        } else {
            var index2 = clickedReceiptIds.indexOf(clicked_receipt_id);
            if (index2 !== -1) {
                clickedReceiptIds.splice(index2, 1);
            }
        }
    });

    //validate receipt
    $(document).delegate('.listing-container .Validate_Receipt', 'click', function(){
        console.log('Validate Receipt clicked');
        console.log('clickedReceiptIds:', clickedReceiptIds);
        
        if ( clickedReceiptIds.length > 0)
        {
            var mergeStr = "Are you sure want to validate these receipt?";
            if (confirm(mergeStr)) {
                console.log('Starting AJAX request...');
                console.log('URL:', "{{URL::to('/')}}/validate_receipt");
                console.log('Data:', {clickedReceiptIds:clickedReceiptIds, receipt_type:1});
                
                $.ajax({
                    type:'post',
                    url:"{{URL::to('/')}}/validate_receipt",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {clickedReceiptIds:clickedReceiptIds,receipt_type:1},
                    dataType: 'json',
                    beforeSend: function() {
                        console.log('AJAX request being sent...');
                    },
                    success: function(response){
                        console.log('AJAX Success! Response:', response);
                        console.log('Response type:', typeof response);
                        
                        // Parse response if it's a string (fallback for older jQuery versions)
                        var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                        console.log('Parsed object:', obj);
                        
                        if(!obj.status) {
                            alert('Error: ' + obj.message);
                            return;
                        }
                        
                        //location.reload(true);
                        var record_data = obj.record_data;
                        console.log('Record data:', record_data);
                        
                        $.each(record_data, function(index, subArray) {
                            console.log('Processing record:', subArray);
                            //console.log('index=='+index);
                            //console.log('subArray=='+subArray.id);
                            $('.listing-container #validate_' + subArray.id +' span')
                                .removeClass('badge-danger')
                                .addClass('modern-badge badge-success')
                                .html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Yes');
                            if(subArray.first_name != ""){
                                var validateby_full_name = subArray.first_name+" "+subArray.last_name;
                            } else {
                                var validateby_full_name = "-";
                            }
                            $('.listing-container #validateby_'+subArray.id).text(validateby_full_name);
                        });
                        $('.listing-container .custom-error-msg').text(obj.message);
                        $('.listing-container .custom-error-msg').show();
                        $('.listing-container .custom-error-msg').addClass('alert alert-success');
                        
                        // Clear checkboxes after successful validation
                        clickedReceiptIds = [];
                        $('.listing-container .cb-element').prop('checked', false);
                        $('.listing-container #checkbox-all').prop('checked', false);
                    },
                    error: function(xhr, status, error) {
                        console.error('=== AJAX ERROR ===');
                        console.error('Status:', status);
                        console.error('Error:', error);
                        console.error('XHR Status:', xhr.status);
                        console.error('XHR Status Text:', xhr.statusText);
                        console.error('Response Text:', xhr.responseText);
                        console.error('Response JSON:', xhr.responseJSON);
                        console.error('==================');
                        
                        var errorMessage = 'Unknown error occurred';
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                            console.log('Using JSON error message:', errorMessage);
                        } else if(xhr.responseText) {
                            // Check if it's HTML
                            if(xhr.responseText.trim().startsWith('<')) {
                                console.error('Server returned HTML instead of JSON!');
                                console.error('First 500 chars:', xhr.responseText.substring(0, 500));
                                errorMessage = 'Server returned an HTML page instead of JSON. This usually means:\n' +
                                              '1. The route is not found (404)\n' +
                                              '2. Authentication failed (redirected to login)\n' +
                                              '3. Server error (500)\n\n' +
                                              'Check the console for the full HTML response.';
                            } else {
                                errorMessage = 'Server returned: ' + xhr.responseText.substring(0, 200);
                            }
                        } else {
                            errorMessage = error || 'Unknown error';
                        }
                        
                        alert('Error validating receipt: ' + errorMessage + '\n\nPlease check the browser console (F12) for more details.');
                    }
                });
            }
        } else {
            alert('Please select atleast 1 receipt.');
        }
    });

    // Delete receipt by super admin
    $(document).delegate('.listing-container .Delete_Receipt', 'click', function(){
        if (clickedReceiptIds.length === 0) {
            alert('Please select a receipt to delete.');
            return;
        }
        if (clickedReceiptIds.length > 1) {
            alert('Please select only one receipt to delete.');
            return;
        }
        var mergeStr = "Are you sure want to delete this receipt?";
        if (confirm(mergeStr)) {
            $.ajax({
                type: 'post',
                url: "{{URL::to('/')}}/delete_receipt",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                data: { receiptId: clickedReceiptIds[0], receipt_type: 1 },
                dataType: 'json',
                success: function(response) {
                    // Parse response if it's a string (fallback for older jQuery versions)
                    var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                    if (obj.status) {
                        $('.listing-container #id_' + clickedReceiptIds[0]).remove();
                        clickedReceiptIds = [];
                        $('.listing-container .custom-error-msg').text(obj.message);
                        $('.listing-container .custom-error-msg').show();
                        $('.listing-container .custom-error-msg').addClass('alert alert-success');
                        // Check if table is empty after deletion
                        if ($('.listing-container .tdata tr').length === 0) {
                            $('.listing-container .tdata').html('<tr><td colspan="11" style="text-align: center; padding: 20px;">No Record Found</td></tr>');
                        }
                    } else {
                        $('.listing-container .custom-error-msg').text(obj.message);
                        $('.listing-container .custom-error-msg').show();
                        $('.listing-container .custom-error-msg').addClass('alert alert-danger');
                    }
                },
                error: function() {
                    $('.listing-container .custom-error-msg').text('An error occurred while deleting the receipt.');
                    $('.listing-container .custom-error-msg').show();
                    $('.listing-container .custom-error-msg').addClass('alert alert-danger');
                }
            });
        }
    });

    $('.listing-container .cb-element').change(function () {
        if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
            $('.listing-container #checkbox-all').prop('checked',true);
        } else {
            $('.listing-container #checkbox-all').prop('checked',false);
        }
    });

    // Sortable column headers
    $('.listing-container .sortable-header').on('click', function() {
        var sortBy = $(this).data('sort');
        var currentUrl = new URL(window.location.href);
        var currentSortBy = currentUrl.searchParams.get('sort_by');
        var currentSortOrder = currentUrl.searchParams.get('sort_order');
        
        // Determine new sort order
        var newSortOrder = 'asc';
        if (currentSortBy === sortBy && currentSortOrder === 'asc') {
            newSortOrder = 'desc';
        }
        
        // Set sort parameters
        currentUrl.searchParams.set('sort_by', sortBy);
        currentUrl.searchParams.set('sort_order', newSortOrder);
        
        // Redirect to new URL
        window.location.href = currentUrl.toString();
    });
});
</script>
@endpush
