@extends('layouts.crm_client_detail')
@section('title', 'Clients Invoice List')

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

    /* Modern Header with Gradient */
    .listing-container .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 24px 32px;
        border-bottom: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .listing-container .card-header h4 {
        color: white;
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.5px;
        flex: 1;
    }

    .listing-container .per-page-select {
        border: 2px solid rgba(255, 255, 255, 0.3) !important;
        border-radius: 10px !important;
        background: rgba(255, 255, 255, 0.2) !important;
        color: white !important;
        font-weight: 600 !important;
        padding: 8px 12px !important;
        min-width: 80px;
        width: auto;
        backdrop-filter: blur(10px);
    }

    .listing-container .per-page-select option {
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

    .listing-container .is_checked_client_void_invoice {
        background: white !important;
        color: #667eea !important;
        font-weight: 700;
    }

    .listing-container .is_checked_client_void_invoice:hover {
        background: rgba(255, 255, 255, 0.95) !important;
    }

    /* Modern Filter Panel */
    .listing-container .filter_panel {
        background: #f8fafc;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid #e2e8f0;
    }

    .listing-container .filter_panel h4 {
        color: #1e293b;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #667eea;
        display: inline-block;
    }

    /* Date Filter Section */
    .date-filter-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        border: 2px solid #e2e8f0;
    }

    .date-filter-section h5 {
        color: #1e293b;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .date-filter-section h5 i {
        color: #667eea;
        font-size: 16px;
    }

    /* Quick Filter Chips */
    .quick-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
    }

    .quick-filter-chip {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 20px;
        padding: 8px 18px;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .quick-filter-chip:hover {
        border-color: #667eea;
        color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    }

    .quick-filter-chip.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .quick-filter-chip i {
        font-size: 12px;
    }

    /* Date Range Inputs */
    .date-range-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .date-range-wrapper .form-group {
        margin-bottom: 0;
        flex: 1;
        min-width: 200px;
    }

    .date-range-arrow {
        color: #94a3b8;
        font-size: 18px;
        font-weight: 700;
        margin: 0 8px;
    }

    /* Financial Year Selector */
    .fy-selector {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .fy-selector label {
        margin-bottom: 0 !important;
        white-space: nowrap;
    }

    .fy-selector .form-control {
        max-width: 250px;
    }

    /* Active Filter Badge */
    .active-filters-badge {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 12px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 8px;
    }

    /* Clear Filters Button */
    .clear-filter-btn {
        background: transparent;
        border: 2px solid #ef4444;
        color: #ef4444;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clear-filter-btn:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-2px);
    }

    .divider-text {
        color: #94a3b8;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 16px 0 12px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .divider-text::before,
    .divider-text::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e2e8f0;
    }

    /* Modern Form Inputs */
    .listing-container .form-group label {
        color: #475569 !important;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .listing-container .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .listing-container .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

    .listing-container .filter-buttons-container {
        margin-top: 20px;
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
        overflow: hidden;
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

    /* Strike Through for Voided Invoices */
    .listing-container .strike-through {
        opacity: 0.6;
        text-decoration: line-through;
        background: #fee2e2 !important;
    }

    /* Hubdoc Status Checkbox */
    .hubdoc-status-check {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    .hubdoc-tick {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .hubdoc-tick.sent {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
    }

    .hubdoc-tick.not-sent {
        background: #e2e8f0;
        color: #94a3b8;
    }

    .hubdoc-tick:hover {
        transform: scale(1.1);
    }

    .hubdoc-sent-time {
        font-size: 10px;
        color: #94a3b8;
        margin-top: 4px;
        display: block;
        text-align: center;
    }

    .badge {
        padding: 0.35em 0.65em;
        font-weight: 500;
        border-radius: 0.25rem;
        display: inline-block;
    }

    /* Modern Icons */
    .listing-container .Validate_Receipt svg.lucide,
    .listing-container .is_checked_client_void_invoice svg.lucide {
        color: #10b981;
        stroke: #10b981;
        width: 16px;
        height: 16px;
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
    .listing-container .table tbody td[id^="deposit_"] {
        font-weight: 700;
        color: #059669;
        font-family: 'Courier New', monospace;
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

    .listing-container .sort-icon svg.lucide {
        width: 8px;
        height: 8px;
        color: #475569;
        stroke: #475569;
    }

    .listing-container .sortable-header.sort-asc .sort-icon {
        opacity: 1;
    }

    .listing-container .sortable-header.sort-asc .sort-icon svg.lucide:first-of-type {
        color: #667eea;
        stroke: #667eea;
        width: 10px;
        height: 10px;
    }

    .listing-container .sortable-header.sort-desc .sort-icon {
        opacity: 1;
    }

    .listing-container .sortable-header.sort-desc .sort-icon svg.lucide:last-of-type {
        color: #667eea;
        stroke: #667eea;
        width: 10px;
        height: 10px;
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
            padding: 20px;
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

    /* Aging Analysis Styles */
    .aging-summary-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
        padding: 0 32px;
    }

    .aging-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        border-left: 4px solid;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .aging-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }

    .aging-card.active {
        border-left-width: 6px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        transform: translateY(-2px);
    }

    .aging-card.active::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.03);
        pointer-events: none;
    }

    .aging-card.current {
        border-left-color: #10b981;
        background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
    }

    .aging-card.warning {
        border-left-color: #f59e0b;
        background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);
    }

    .aging-card.urgent {
        border-left-color: #f97316;
        background: linear-gradient(135deg, #ffffff 0%, #fff7ed 100%);
    }

    .aging-card.critical {
        border-left-color: #ef4444;
        background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);
    }

    .aging-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
    }

    .aging-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .aging-card.current .aging-icon {
        background: #d1fae5;
        color: #059669;
    }

    .aging-card.warning .aging-icon {
        background: #fef3c7;
        color: #d97706;
    }

    .aging-card.urgent .aging-icon {
        background: #fed7aa;
        color: #ea580c;
    }

    .aging-card.critical .aging-icon {
        background: #fecaca;
        color: #dc2626;
    }

    .aging-card-title {
        flex: 1;
    }

    .aging-card-title h5 {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }

    .aging-card-title p {
        margin: 2px 0 0 0;
        font-size: 11px;
        color: #94a3b8;
    }

    .aging-amount {
        font-size: 28px;
        font-weight: 700;
        font-family: 'Courier New', monospace;
        margin-bottom: 8px;
    }

    .aging-card.current .aging-amount {
        color: #059669;
    }

    .aging-card.warning .aging-amount {
        color: #d97706;
    }

    .aging-card.urgent .aging-amount {
        color: #ea580c;
    }

    .aging-card.critical .aging-amount {
        color: #dc2626;
    }

    .aging-count {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }

    /* Aging Badge in Table */
    .aging-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .aging-badge.badge-current {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .aging-badge.badge-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }

    .aging-badge.badge-urgent {
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(249, 115, 22, 0.3);
    }

    .aging-badge.badge-critical {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        animation: pulse-critical 2s ease-in-out infinite;
    }

    .aging-badge.badge-paid {
        background: linear-gradient(135deg, #34d399 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }

    .aging-badge.badge-void {
        background: linear-gradient(135deg, #cbd5f5 0%, #64748b 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(100, 116, 139, 0.25);
    }

    @keyframes pulse-critical {
        0%, 100% {
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        50% {
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.5);
        }
    }

    .aging-days {
        font-size: 10px;
        opacity: 0.9;
        display: block;
        margin-top: 2px;
    }
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
                    <div class="d-flex justify-content-between align-items-center w-100 flex-wrap" style="gap: 12px;">
                        <h4 class="mb-0" style="flex: 1 1 auto;">All Clients Invoice List</h4>

                        <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
                            @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                            <a href="{{ route('clients.analytics-dashboard') }}" class="btn btn-theme btn-theme-sm" title="View Financial Analytics Dashboard">@icon('fa-chart-line') Analytics</a>
                            @endif
                            <label for="per_page" class="sr-only">Items per page</label>
                            <select name="per_page" id="per_page" class="form-control per-page-select">
                                <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                <option value="20" {{ $perPage == 20 ? 'selected' : '' }}>20</option>
                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                <option value="200" {{ $perPage == 200 ? 'selected' : '' }}>200</option>
                                <option value="500" {{ $perPage == 500 ? 'selected' : '' }}>500</option>
                            </select>
                            <a href="javascript:;" style="background: #394eea;color: white;"  class="btn btn-theme btn-theme-sm filter_btn">@icon('fa-filter') Filter</a>
                            <button class="btn btn-primary is_checked_client_void_invoice" style="background-color: #394eea !important;">
                                @icon('fa-check-circle')
                                Void Invoice
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Aging Analysis Summary Dashboard -->
                    <?php
                    // Calculate aging analysis
                    $agingSummary = [
                        'current' => ['count' => 0, 'amount' => 0],
                        'warning' => ['count' => 0, 'amount' => 0],
                        'urgent' => ['count' => 0, 'amount' => 0],
                        'critical' => ['count' => 0, 'amount' => 0]
                    ];

                    foreach ($lists as $invoice) {
                        if ($invoice->void_invoice == 1) continue; // Skip voided invoices
                        $balanceAmount = isset($invoice->balance_amount) ? floatval($invoice->balance_amount) : 0;
                        if (isset($invoice->payment_type) && $invoice->payment_type === 'Discount') {
                            $balanceAmount = abs($balanceAmount);
                        }
                        $isPaidInvoice = ($invoice->invoice_status == 1) || ($balanceAmount <= 0);
                        if ($isPaidInvoice) continue; // Skip fully paid invoices

                        $transDate = !empty($invoice->trans_date) ? strtotime($invoice->trans_date) : false;
                        $today = strtotime(date('Y-m-d'));
                        $daysOld = $transDate ? floor(($today - $transDate) / (60 * 60 * 24)) : 0;
                        
                        // Outstanding amount for aging buckets
                        $amount = $balanceAmount > 0 ? $balanceAmount : 0;
                        
                        if ($daysOld <= 30) {
                            $agingSummary['current']['count']++;
                            $agingSummary['current']['amount'] += $amount;
                        } elseif ($daysOld <= 60) {
                            $agingSummary['warning']['count']++;
                            $agingSummary['warning']['amount'] += $amount;
                        } elseif ($daysOld <= 90) {
                            $agingSummary['urgent']['count']++;
                            $agingSummary['urgent']['amount'] += $amount;
                        } else {
                            $agingSummary['critical']['count']++;
                            $agingSummary['critical']['amount'] += $amount;
                        }
                    }
                    ?>

                    <div class="aging-summary-container">
                        <!-- Current (0-30 days) -->
                        <div class="aging-card current {{ request('aging_category') == 'current' ? 'active' : '' }}" 
                             data-aging-filter="current"
                             title="Click to filter Current invoices (0-30 days)">
                            <div class="aging-card-header">
                                <div class="aging-icon">
                                    @icon('fa-check-circle')
                                </div>
                                <div class="aging-card-title">
                                    <h5>Current</h5>
                                    <p>0-30 days</p>
                                </div>
                            </div>
                            <div class="aging-amount">${{ number_format($agingSummary['current']['amount'], 2) }}</div>
                            <div class="aging-count">{{ $agingSummary['current']['count'] }} {{ $agingSummary['current']['count'] == 1 ? 'invoice' : 'invoices' }}</div>
                        </div>

                        <!-- Warning (30-60 days) -->
                        <div class="aging-card warning {{ request('aging_category') == 'warning' ? 'active' : '' }}" 
                             data-aging-filter="warning"
                             title="Click to filter Warning invoices (30-60 days overdue)">
                            <div class="aging-card-header">
                                <div class="aging-icon">
                                    @icon('fa-exclamation-triangle')
                                </div>
                                <div class="aging-card-title">
                                    <h5>Warning</h5>
                                    <p>30-60 days overdue</p>
                                </div>
                            </div>
                            <div class="aging-amount">${{ number_format($agingSummary['warning']['amount'], 2) }}</div>
                            <div class="aging-count">{{ $agingSummary['warning']['count'] }} {{ $agingSummary['warning']['count'] == 1 ? 'invoice' : 'invoices' }}</div>
                        </div>

                        <!-- Urgent (60-90 days) -->
                        <div class="aging-card urgent {{ request('aging_category') == 'urgent' ? 'active' : '' }}" 
                             data-aging-filter="urgent"
                             title="Click to filter Urgent invoices (60-90 days overdue)">
                            <div class="aging-card-header">
                                <div class="aging-icon">
                                    @icon('fa-exclamation-circle')
                                </div>
                                <div class="aging-card-title">
                                    <h5>Urgent</h5>
                                    <p>60-90 days overdue</p>
                                </div>
                            </div>
                            <div class="aging-amount">${{ number_format($agingSummary['urgent']['amount'], 2) }}</div>
                            <div class="aging-count">{{ $agingSummary['urgent']['count'] }} {{ $agingSummary['urgent']['count'] == 1 ? 'invoice' : 'invoices' }}</div>
                        </div>

                        <!-- Critical (90+ days) -->
                        <div class="aging-card critical {{ request('aging_category') == 'critical' ? 'active' : '' }}" 
                             data-aging-filter="critical"
                             title="Click to filter Critical invoices (90+ days overdue)">
                            <div class="aging-card-header">
                                <div class="aging-icon">
                                    @icon('fa-times-circle')
                                </div>
                                <div class="aging-card-title">
                                    <h5>Critical</h5>
                                    <p>90+ days overdue</p>
                                </div>
                            </div>
                            <div class="aging-amount">${{ number_format($agingSummary['critical']['amount'], 2) }}</div>
                            <div class="aging-count">{{ $agingSummary['critical']['count'] }} {{ $agingSummary['critical']['count'] == 1 ? 'invoice' : 'invoices' }}</div>
                        </div>
                    </div>

                    <div class="filter_panel">
                        <h4>
                            Search By Details
                            @if(request()->hasAny(['client_id', 'client_matter_id', 'amount', 'hubdoc_status', 'aging_category', 'date_filter_type', 'from_date', 'to_date', 'financial_year']))
                                <span class="active-filters-badge">
                                    @icon('fa-filter')
                                    {{ collect([request('client_id'), request('client_matter_id'), request('amount'), request('hubdoc_status'), request('aging_category'), request('date_filter_type'), request('from_date'), request('to_date'), request('financial_year')])->filter()->count() }} Active
                                </span>
                            @endif
                        </h4>
                        <form action="{{URL::to('/clients/invoicelist')}}" method="get" id="filterForm">
                            <!-- Basic Filters -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="client_id" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-user') Client ID
                                        </label>
                                        <select name="client_id" id="client_id" class="form-control mm-select">
                                            <option value="">Select Client</option>
                                            @foreach($clientIds as $client)
                                                <option value="{{ $client->client_id }}" {{ request('client_id') == $client->client_id ? 'selected' : '' }}>
                                                    {{ $client->first_name.' '.$client->last_name.'('.$client->client_unique_id.')' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="client_matter_id" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-briefcase') Client Matter ID
                                        </label>
                                        <select name="client_matter_id" id="client_matter_id" class="form-control mm-select">
                                            <option value="">Select Matter</option>
                                            @foreach($matterIds as $matter)
                                                <option value="{{ $matter->client_matter_id }}" {{ request('client_matter_id') == $matter->client_matter_id ? 'selected' : '' }}>
                                                    {{ $matter->client_unique_id }}-{{ $matter->client_unique_matter_no }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="amount" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-dollar-sign') Amount
                                        </label>
                                        <input type="number" name="amount" id="amount" value="{{ old('amount', Request::get('amount')) }}" class="form-control" placeholder="Enter amount" step="0.01" min="0">
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="hubdoc_status" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-cloud-upload-alt') Hubdoc Status
                                        </label>
                                        <select name="hubdoc_status" id="hubdoc_status" class="form-control">
                                            <option value="">All Invoices</option>
                                            <option value="1" {{ request('hubdoc_status') == '1' ? 'selected' : '' }}>Sent to Hubdoc</option>
                                            <option value="0" {{ request('hubdoc_status') == '0' ? 'selected' : '' }}>Not Sent to Hubdoc</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Aging Analysis Filter -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="aging_category" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-clock') Aging Category
                                        </label>
                                        <select name="aging_category" id="aging_category" class="form-control">
                                            <option value="">All Aging Categories</option>
                                            <option value="current" {{ request('aging_category') == 'current' ? 'selected' : '' }}>🟢 Current (0-30 days)</option>
                                            <option value="warning" {{ request('aging_category') == 'warning' ? 'selected' : '' }}>🟡 Warning (30-60 days overdue)</option>
                                            <option value="urgent" {{ request('aging_category') == 'urgent' ? 'selected' : '' }}>🟠 Urgent (60-90 days overdue)</option>
                                            <option value="critical" {{ request('aging_category') == 'critical' ? 'selected' : '' }}>🔴 Critical (90+ days overdue)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Enhanced Date Filter Section -->
                            <div class="date-filter-section">
                                <h5>@icon('fa-calendar-alt') Date Filter</h5>
                                
                                <!-- Hidden field to track filter type -->
                                <input type="hidden" name="date_filter_type" id="date_filter_type" value="{{ request('date_filter_type', '') }}">
                                
                                <!-- Quick Filter Chips -->
                                <div class="quick-filters">
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'today' ? 'active' : '' }}" data-filter="today">
                                        @icon('fa-calendar-day') Today
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'this_week' ? 'active' : '' }}" data-filter="this_week">
                                        @icon('fa-calendar-week') This Week
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'this_month' ? 'active' : '' }}" data-filter="this_month">
                                        @icon('fa-calendar') This Month
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'this_quarter' ? 'active' : '' }}" data-filter="this_quarter">
                                        @icon('fa-calendar-check') This Quarter
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'this_year' ? 'active' : '' }}" data-filter="this_year">
                                        @icon('fa-calendar-alt') This Year
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'last_month' ? 'active' : '' }}" data-filter="last_month">
                                        @icon('fa-calendar-minus') Last Month
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'last_quarter' ? 'active' : '' }}" data-filter="last_quarter">
                                        @icon('fa-calendar-minus') Last Quarter
                                    </span>
                                    <span class="quick-filter-chip {{ request('date_filter_type') == 'last_year' ? 'active' : '' }}" data-filter="last_year">
                                        @icon('fa-calendar-minus') Last Year
                                    </span>
                                </div>

                                <div class="divider-text">Or Custom Range</div>

                                <!-- Custom Date Range -->
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-calendar-plus') From Date
                                        </label>
                                        <input type="text" name="from_date" id="from_date" value="{{ old('from_date', Request::get('from_date')) }}" class="form-control datepicker" autocomplete="off" placeholder="Select start date">
                                    </div>
                                    
                                    <span class="date-range-arrow">→</span>
                                    
                                    <div class="form-group">
                                        <label for="to_date" class="col-form-label" style="color:#4a5568 !important;">
                                            @icon('fa-calendar-check') To Date
                                        </label>
                                        <input type="text" name="to_date" id="to_date" value="{{ old('to_date', Request::get('to_date')) }}" class="form-control datepicker" autocomplete="off" placeholder="Select end date">
                                    </div>
                                </div>

                                <div class="divider-text">Or Financial Year</div>

                                <!-- Financial Year Selector -->
                                <div class="fy-selector">
                                    <label for="financial_year" class="col-form-label" style="color:#4a5568 !important;">
                                        @icon('fa-chart-line') Financial Year:
                                    </label>
                                    <select name="financial_year" id="financial_year" class="form-control">
                                        <option value="">Select Financial Year</option>
                                        <?php
                                        $currentYear = date('Y');
                                        $currentMonth = date('n');
                                        // Australian FY starts in July
                                        $startYear = ($currentMonth >= 7) ? $currentYear : $currentYear - 1;
                                        
                                        // Generate last 5 FY and next 2 FY
                                        for ($i = 2; $i >= -5; $i--) {
                                            $fyStart = $startYear - $i;
                                            $fyEnd = $fyStart + 1;
                                            $fyValue = $fyStart . '-' . $fyEnd;
                                            $fyLabel = 'FY ' . $fyStart . '-' . substr($fyEnd, -2);
                                            $selected = request('financial_year') == $fyValue ? 'selected' : '';
                                            echo "<option value=\"{$fyValue}\" {$selected}>{$fyLabel}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">
                                            @icon('fa-search') Search
                                        </button>
                                        <a class="btn btn-info" href="{{URL::to('/clients/invoicelist')}}">
                                            @icon('fa-redo') Reset All
                                        </a>
                                        @if(request()->hasAny(['client_id', 'client_matter_id', 'amount', 'hubdoc_status', 'aging_category', 'date_filter_type', 'from_date', 'to_date', 'financial_year']))
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
                                        <div class="custom-checkbox custom-checkbox-table custom-control">
                                            <input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
                                            <label for="checkbox-all" class="custom-control-label"></label>
                                        </div>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'client_id' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="client_id">
                                        Client Id
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
                                    <th class="sortable-header {{ request('sort_by') == 'reference' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="reference">
                                        Reference
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'trans_date' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="trans_date">
                                        Trans. Date
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'amount' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="amount">
                                        Amount
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'hubdoc_status' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="hubdoc_status" style="text-align: center;">
                                        @icon('fa-cloud-upload-alt', ['style' => 'color: #667eea; margin-right: 4px;'])Hubdoc
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'aging' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="aging">
                                        Aging Status
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'voided_by' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="voided_by">
                                        Voided By
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                    <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                        <?php
                                        $client_info = \App\Models\Admin::select('id','first_name','last_name','client_id')->where('id', $list->client_id)->first();
                                        $client_full_name = $client_info ? $client_info->first_name.' '.$client_info->last_name : '-';
                                        $client_id_display = $client_info ? $client_info->client_id : '-';

                                        $client_matter_info = \App\Models\ClientMatter::select('client_unique_matter_no')->where('id', $list->client_matter_id)->first();
                                        $client_matter_display = $client_matter_info ? $client_id_display.'-'.$client_matter_info->client_unique_matter_no : '-';


                                        $Reference = $list->trans_no ?? '-';
                                        $invoice_no = $list->invoice_no ?? '-';
                                        $trans_date = $list->trans_date ?? '-';

                                        if(isset($list->voided_or_validated_by) && $list->voided_or_validated_by != ""){
                                            $validate_by = \App\Models\Staff::select('id','first_name','last_name')->where('id', $list->voided_or_validated_by)->first();
                                            $validate_by_full_name = $validate_by ? $validate_by->first_name.' '.$validate_by->last_name : 'N/A';
                                        } else {
                                            $validate_by_full_name = "-";
                                        }
                                        ?>
                                        <?php
                                        if($list->void_invoice == 1 ) {
                                            $trcls = 'class="strike-through"';
                                        } else {
                                            $trcls = 'class=""';
                                        }
                                        ?>
                                        <tr id="id_{{@$list->id}}" <?php echo $trcls;?>>
                                            <td style="white-space: initial;" class="text-center">
                                                <div class="custom-checkbox custom-control">
                                                    <input data-id="{{@$list->id}}" data-receiptid="{{@$list->receipt_id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input  your-checkbox" id="checkbox-{{$i}}">
                                                    <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                                </div>
                                            </td>
                                            <td>{{ $client_id_display }}</td>
                                            <td>{{ $client_matter_display }}</td>
                                            <td>{{ $client_full_name }}</td>
                                            <td>{{ $Reference }}</td>
                                            <td>{{ $trans_date }}</td>
                                            <td id="deposit_{{@$list->id}}">
                                                @if($list->invoice_status == 1 && ($list->balance_amount == 0 || $list->balance_amount == 0.00))
                                                    {{ !empty($list->partial_paid_amount) ? '$ ' . number_format($list->partial_paid_amount, 2) : '' }}
                                                @else
                                                    {{ !empty($list->balance_amount) ? '$ ' . number_format($list->payment_type == 'Discount' ? abs($list->balance_amount) : $list->balance_amount, 2) : '' }}
                                                @endif
                                            </td>
                                            <td>
                                                <div class="hubdoc-status-check">
                                                    <div title="<?php echo (isset($list->hubdoc_sent) && $list->hubdoc_sent == 1) ? 'Sent to Hubdoc' . (isset($list->hubdoc_sent_at) ? ' on ' . date('d/m/Y H:i', strtotime($list->hubdoc_sent_at)) : '') : 'Not sent to Hubdoc'; ?>">
                                                        <?php
                                                        if(isset($list->hubdoc_sent) && $list->hubdoc_sent == 1) {
                                                            ?>
                                                            <div class="hubdoc-tick sent">
                                                                @icon('fa-check')
                                                            </div>
                                                            <?php
                                                        } else {
                                                            ?>
                                                            <div class="hubdoc-tick not-sent">
                                                                @icon('fa-minus')
                                                            </div>
                                                            <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                // Calculate aging status
                                                $transDate = !empty($list->trans_date) ? strtotime($list->trans_date) : false;
                                                $today = strtotime(date('Y-m-d'));
                                                $daysOld = $transDate ? floor(($today - $transDate) / (60 * 60 * 24)) : 0;

                                                $balanceAmount = isset($list->balance_amount) ? floatval($list->balance_amount) : 0;
                                                if (isset($list->payment_type) && $list->payment_type === 'Discount') {
                                                    $balanceAmount = abs($balanceAmount);
                                                }

                                                $isPaidInvoice = ($list->invoice_status == 1) || ($balanceAmount <= 0);

                                                if ($list->void_invoice == 1) {
                                                    $agingClass = 'badge-void';
                                                    $agingLabel = 'Voided';
                                                    $agingIcon = 'fa-ban';
                                                    $agingDays = null;
                                                } elseif ($isPaidInvoice) {
                                                    $agingClass = 'badge-paid';
                                                    $agingLabel = 'Paid';
                                                    $agingIcon = 'fa-check-circle';
                                                    $agingDays = null;
                                                } else {
                                                    if ($daysOld <= 30) {
                                                        $agingClass = 'badge-current';
                                                        $agingLabel = 'Current';
                                                        $agingIcon = 'fa-check-circle';
                                                    } elseif ($daysOld <= 60) {
                                                        $agingClass = 'badge-warning';
                                                        $agingLabel = 'Warning';
                                                        $agingIcon = 'fa-exclamation-triangle';
                                                    } elseif ($daysOld <= 90) {
                                                        $agingClass = 'badge-urgent';
                                                        $agingLabel = 'Urgent';
                                                        $agingIcon = 'fa-exclamation-circle';
                                                    } else {
                                                        $agingClass = 'badge-critical';
                                                        $agingLabel = 'Critical';
                                                        $agingIcon = 'fa-times-circle';
                                                    }
                                                    $agingDays = $daysOld . ' days';
                                                }
                                                ?>
                                                <span class="aging-badge <?php echo $agingClass; ?>">
                                                    <?php echo \App\Helpers\IconHelper::fromLegacy('fas ' . $agingIcon); ?>
                                                    <?php echo $agingLabel; ?>
                                                    <?php if (!empty($agingDays)) { ?>
                                                        <span class="aging-days"><?php echo $agingDays; ?></span>
                                                    <?php } ?>
                                                </span>
                                            </td>
                                            <td id="voidedby_{{@$list->id}}"><?php echo $validate_by_full_name;?></td>
                                        </tr>
                                        <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 60px 20px;">
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
<script>
jQuery(document).ready(function($){
     $('.listing-container .filter_btn').on('click', function(){
		$('.listing-container .filter_panel').toggle();
	});

    // Handle records per page dropdown change
    $('#per_page').on('change', function() {
        var perPage = $(this).val();
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    // Aging Card Click Handlers - Filter by aging category with toggle (Option A)
    $('.aging-card').on('click', function() {
        var agingCategory = $(this).data('aging-filter');
        var currentUrl = new URL(window.location.href);
        var currentAgingFilter = currentUrl.searchParams.get('aging_category');
        
        // Toggle behavior: If clicking the same category, remove the filter
        if (currentAgingFilter === agingCategory) {
            currentUrl.searchParams.delete('aging_category');
        } else {
            // Otherwise, set the new aging category filter
            currentUrl.searchParams.set('aging_category', agingCategory);
        }
        
        // Reset to page 1 when filtering changes
        currentUrl.searchParams.delete('page');
        
        // Redirect to filtered URL
        window.location.href = currentUrl.toString();
    });

    // Initialize Flatpickr for custom date range
    if (typeof flatpickr !== 'undefined') {
        $('.datepicker').each(function() {
            var $this = $(this);
            if (!$this.data('flatpickr')) {
                flatpickr(this, {
                    dateFormat: 'd/m/Y', // dd/mm/yyyy format
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: $this.val() || null,
                    locale: { firstDayOfWeek: 1 },
                    onChange: function(selectedDates, dateStr, instance) {
                        $this.val(dateStr);
                        $this.trigger('change');
                    }
                });
            }
        });
    }

    // Quick Filter Chips Functionality
    $('.quick-filter-chip').on('click', function() {
        var filterType = $(this).data('filter');
        
        // Remove active class from all chips
        $('.quick-filter-chip').removeClass('active');
        
        // Add active class to clicked chip
        $(this).addClass('active');
        
        // Set the hidden input value
        $('#date_filter_type').val(filterType);
        
        // Clear custom date fields and financial year when using quick filters
        $('#from_date').val('');
        $('#to_date').val('');
        $('#financial_year').val('');
        
        // Auto-submit form
        $('#filterForm').submit();
    });

    // Custom Date Range - Clear quick filters and FY when dates are entered
    $('#from_date, #to_date').on('change', function() {
        if ($('#from_date').val() || $('#to_date').val()) {
            $('.quick-filter-chip').removeClass('active');
            $('#date_filter_type').val('custom');
            $('#financial_year').val('');
        }
    });

    // Financial Year - Clear other date filters when FY is selected
    $('#financial_year').on('change', function() {
        if ($(this).val()) {
            $('.quick-filter-chip').removeClass('active');
            $('#date_filter_type').val('financial_year');
            $('#from_date').val('');
            $('#to_date').val('');
        }
    });

    // Clear Date Filters Button
    $('#clearDateFilters').on('click', function() {
        $('.quick-filter-chip').removeClass('active');
        $('#date_filter_type').val('');
        $('#from_date').val('');
        $('#to_date').val('');
        $('#financial_year').val('');
        $('#filterForm').submit();
    });

    // Validation before form submit
    $('#filterForm').on('submit', function(e) {
        var fromDate = $('#from_date').val();
        var toDate = $('#to_date').val();
        
        // If one custom date is filled, both should be filled
        if ((fromDate && !toDate) || (!fromDate && toDate)) {
            e.preventDefault();
            alert('Please select both From Date and To Date for custom range filtering.');
            return false;
        }
        
        // Validate date order if both are filled
        if (fromDate && toDate) {
            var from = parseDate(fromDate);
            var to = parseDate(toDate);
            
            if (from > to) {
                e.preventDefault();
                alert('From Date cannot be later than To Date.');
                return false;
            }
        }
    });

    // Helper function to parse dd/mm/yyyy format
    function parseDate(dateStr) {
        var parts = dateStr.split('/');
        // month is 0-based in JavaScript Date
        return new Date(parts[2], parts[1] - 1, parts[0]);
    }

    $("[data-checkboxes]").each(function () {
        var me = $(this),
        group = me.data('checkboxes'),
        role = me.data('checkbox-role');

        me.change(function () {
            var all = $('[data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"])'),
            checked = $('[data-checkboxes="' + group + '"]:not([data-checkbox-role="dad"]):checked'),
            dad = $('[data-checkboxes="' + group + '"][data-checkbox-role="dad"]'),
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
                    $('.is_checked_client').show();
                    $('.is_checked_clientn').hide();
                } else {
                    dad.prop('checked', false);
                    $('.is_checked_client').hide();
                    $('.is_checked_clientn').show();
                }
            }

        });
    });

    var clickedReceiptIds = [];
    $(document).delegate('.your-checkbox', 'click', function(){
        var clicked_receipt_id = $(this).data('receiptid');
        if ($(this).is(':checked')) {
            clickedReceiptIds.push(clicked_receipt_id);
        } else {
            var index2 = clickedReceiptIds.indexOf(clicked_receipt_id);
            if (index2 !== -1) {
                clickedReceiptIds.splice(index2, 1);
            }
        }
    });

    //merge task
    $(document).delegate('.is_checked_client_void_invoice', 'click', function(){
        if ( clickedReceiptIds.length > 0)
        {
            var mergeStr = "Are you sure want to void these invoice?";
            if (confirm(mergeStr)) {
                $.ajax({
                    type:'post',
                    url:"{{URL::to('/')}}/void_invoice",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {clickedReceiptIds:clickedReceiptIds},
                    success: function(response){
                        // Parse response if it's a string (fallback for older jQuery versions)
                        var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                        //location.reload(true);
                        
                        if(obj.status){
                            var debugMsg = '';
                            if(obj.debug_info){
                                debugMsg = '\n\nDebug: ' + obj.debug_info.total_reversals + ' reversals created';
                            }
                            
                            if(obj.reversals_created > 0){
                                // If fee transfers were voided, reload the page to show updated balances
                                alert(obj.message + debugMsg + '\n\nReloading page to show updated balances...');
                                window.location.reload();
                                return;
                            } else {
                                // No fee transfers found - just show message
                                alert(obj.message + debugMsg);
                            }
                        }
                        
                        var record_data = obj.record_data;
                        $.each(record_data, function(index, subArray) {
                            //console.log('index=='+index);
                            //console.log('subArray=='+subArray.id);
                            $('#deposit_'+subArray.id).text("$0.00");
                            if(subArray.first_name != ""){
                                var voidedby_full_name = subArray.first_name+" "+subArray.last_name;
                            } else {
                                var voidedby_full_name = "-";
                            }
                            $('#voidedby_'+subArray.id).text(voidedby_full_name);
                            $('#id_'+subArray.id).css("text-decoration","line-through");
                            $('#id_'+subArray.id).css("color","#000");
                        });
                        $('.listing-container .custom-error-msg').text(obj.message);
                        $('.listing-container .custom-error-msg').show();
                        $('.listing-container .custom-error-msg').addClass('alert alert-success');
                    }
                });
            }
        } else {
            alert('Please select atleast 1 invoice.');
        }
    });


    $('.cb-element').change(function () {
        if ($('.cb-element:checked').length == $('.cb-element').length){
            $('#checkbox-all').prop('checked',true);
        } else {
            $('#checkbox-all').prop('checked',false);
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
