@extends('layouts.crm_client_detail')
@section('title', 'Financial Analytics Dashboard')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    /* Modern Dashboard Styling */
    .analytics-container {
        background: #f8fafc;
        min-height: 100vh;
        padding: 24px 32px;
    }

    /* Page Header */
    .analytics-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 32px;
        box-shadow: 0 4px 6px rgba(102, 126, 234, 0.2);
    }

    .analytics-header h1 {
        color: white;
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
        letter-spacing: -0.5px;
    }

    .analytics-header p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        margin: 0;
    }

    /* Date Range Selector */
    .date-range-selector {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        padding: 16px 20px;
        margin-top: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .date-range-selector label {
        color: white;
        font-weight: 600;
        margin: 0;
    }

    .date-range-selector input,
    .date-range-selector select {
        background: rgba(255, 255, 255, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.4);
        border-radius: 8px;
        color: white;
        padding: 8px 12px;
        font-weight: 500;
    }

    .date-range-selector select option {
        background: #ffffff;
        color: #1e293b;
        padding: 8px 12px;
        font-weight: 500;
    }

    .date-range-selector input::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }

    .date-range-selector .btn-apply {
        background: white;
        color: #667eea;
        border: none;
        border-radius: 8px;
        padding: 8px 24px;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .date-range-selector .btn-apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
    }

    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .stat-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .stat-card-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .stat-card-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
    .stat-card-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
    .stat-card-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
    .stat-card-icon.red { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
    .stat-card-icon.teal { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); color: white; }

    .stat-card-title {
        font-size: 14px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .stat-card-value {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
        line-height: 1;
    }

    .stat-card-subtitle {
        font-size: 13px;
        color: #94a3b8;
        margin-bottom: 12px;
    }

    .stat-card-trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .stat-card-trend.up {
        background: #dcfce7;
        color: #166534;
    }

    .stat-card-trend.down {
        background: #fee2e2;
        color: #991b1b;
    }

    .stat-card-trend.neutral {
        background: #f1f5f9;
        color: #475569;
    }

    /* Chart Cards */
    .chart-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
        margin-bottom: 32px;
    }

    @media (min-width: 768px) {
        .chart-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .chart-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .chart-card.full-width {
        grid-column: 1 / -1;
    }

    .chart-card-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    /* Table Card */
    .table-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        margin-bottom: 24px;
    }

    .table-card-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .analytics-table {
        width: 100%;
        border-collapse: collapse;
    }

    .analytics-table thead {
        background: #f8fafc;
    }

    .analytics-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .analytics-table td {
        padding: 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 14px;
        color: #475569;
    }

    .analytics-table tr:hover {
        background: #f8fafc;
    }

    .analytics-table .client-name {
        font-weight: 600;
        color: #1e293b;
    }

    .analytics-table .amount {
        font-weight: 700;
        color: #059669;
    }

    /* Quick Links Section */
    .quick-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 32px;
    }

    .quick-link-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        border: 2px solid transparent;
    }

    .quick-link-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        border-color: #667eea;
        text-decoration: none;
    }

    .quick-link-card i {
        font-size: 32px;
        margin-bottom: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .quick-link-card h4 {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .analytics-container {
            padding: 16px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .chart-grid {
            grid-template-columns: 1fr;
        }

        .quick-links {
            grid-template-columns: repeat(2, 1fr);
        }

        .analytics-header h1 {
            font-size: 24px;
        }
    }

    /* Loading State */
    .loading-skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Tabs Styling */
    .analytics-tabs {
        background: white;
        border-radius: 16px;
        padding: 0;
        margin-bottom: 32px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .tabs-nav {
        display: flex;
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        overflow-x: auto;
    }

    .tab-item {
        flex: 1;
        min-width: 150px;
        padding: 16px 24px;
        text-align: center;
        cursor: pointer;
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        position: relative;
        white-space: nowrap;
    }

    .tab-item:hover {
        background: rgba(102, 126, 234, 0.05);
        color: #667eea;
    }

    .tab-item.active {
        color: #667eea;
        background: white;
    }

    .tab-item.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .tab-item i {
        margin-right: 8px;
        font-size: 16px;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    @media (max-width: 768px) {
        .tabs-nav {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-item {
            min-width: 120px;
            padding: 12px 16px;
            font-size: 13px;
        }
    }

    /* ========================================================================= */
    /* FONT AWESOME SAFETY */
    /* ========================================================================= */
    .fas {
        font-family: "Font Awesome 5 Free" !important;
        font-weight: 900 !important;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        -webkit-font-smoothing: antialiased;
    }

    .far {
        font-family: "Font Awesome 5 Free" !important;
        font-weight: 400 !important;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        -webkit-font-smoothing: antialiased;
    }

    .fab {
        font-family: "Font Awesome 5 Brands" !important;
        font-weight: 400 !important;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        -webkit-font-smoothing: antialiased;
    }

    /* ========================================================================= */
    /* ACCESSIBILITY FOCUS STATES */
    /* ========================================================================= */
    .tab-item:focus,
    .btn-apply:focus,
    .quick-link-card:focus,
    select:focus,
    input[type="date"]:focus,
    button:focus {
        outline: 3px solid #667eea !important;
        outline-offset: 2px;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }

    .stat-card:focus-within {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }

    .quick-link-card {
        cursor: pointer;
        display: block;
        position: relative;
    }

    .quick-link-card:active {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
    }
</style>
@endsection

@section('content')
<div class="analytics-container">
    <!-- Page Header -->
    <div class="analytics-header">
        <h1>@icon('fa-chart-line') Financial Analytics Dashboard</h1>
        <p>Comprehensive overview of your financial performance and key metrics</p>
        <p style="font-size: 13px; margin-top: 8px; opacity: 0.85; color: rgba(255, 255, 255, 0.9);">
            @icon('fa-calendar-alt') Data period: {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}
        </p>
        
        <!-- Date Range Selector -->
        <div class="date-range-selector">
            <form method="GET" action="{{ route('clients.analytics-dashboard') }}" id="dateRangeForm" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap; width: 100%;">
                @if($receiptType !== null)
                <input type="hidden" name="receipt_type" value="{{ $receiptType }}">
                @endif
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="quick_select">Quick Select:</label>
                    <select name="quick_select" id="quick_select" onchange="handleQuickSelect(this.value)">
                        <option value="" {{ $quickSelect === '' ? 'selected' : '' }}>Custom Range</option>
                        <option value="this_month" {{ $quickSelect === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $quickSelect === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_quarter" {{ $quickSelect === 'this_quarter' ? 'selected' : '' }}>This Quarter</option>
                        <option value="this_year" {{ $quickSelect === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="last_30_days" {{ $quickSelect === 'last_30_days' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="last_90_days" {{ $quickSelect === 'last_90_days' ? 'selected' : '' }}>Last 90 Days</option>
                    </select>
                </div>
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="start_date">From:</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate->format('Y-m-d') }}">
                </div>
                
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="end_date">To:</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate->format('Y-m-d') }}">
                </div>
                
                <button type="submit" class="btn-apply">
                    @icon('fa-sync-alt') Apply
                </button>
            </form>
        </div>
    </div>

    @php
        $metricValues = [
            $dashboardStats['monthly_stats']['total_deposits'] ?? 0,
            $dashboardStats['monthly_stats']['deposit_count'] ?? 0,
            $dashboardStats['monthly_stats']['total_fee_transfers'] ?? 0,
            $dashboardStats['monthly_stats']['total_office_receipts'] ?? 0,
            $dashboardStats['monthly_stats']['office_receipt_count'] ?? 0,
            $dashboardStats['monthly_stats']['total_invoices_issued'] ?? 0,
            $dashboardStats['monthly_stats']['invoice_count'] ?? 0,
            $dashboardStats['monthly_stats']['total_journal_receipts'] ?? 0,
            $dashboardStats['monthly_stats']['journal_receipt_count'] ?? 0,
            $dashboardStats['receipt_stats']['allocated_count'] ?? 0,
            $dashboardStats['receipt_stats']['unallocated_count'] ?? 0,
            $dashboardStats['invoice_stats']['unpaid_invoices'] ?? 0,
            $dashboardStats['invoice_stats']['unpaid_amount'] ?? 0,
            $dashboardStats['invoice_stats']['paid_invoices'] ?? 0,
            $dashboardStats['invoice_stats']['total_invoices'] ?? 0,
            $dashboardStats['allocation_metrics']['average_days_to_allocate'] ?? 0,
            $dashboardStats['allocation_metrics']['old_unallocated_count'] ?? 0,
        ];

        $trendSums = [
            array_sum($dashboardStats['trend_data']['deposits'] ?? []),
            array_sum($dashboardStats['trend_data']['office_receipts'] ?? []),
            array_sum($dashboardStats['trend_data']['invoices'] ?? []),
        ];

        $metricValues = array_merge($metricValues, $trendSums);

        $hasData = collect($metricValues)->contains(function ($value) {
            return is_numeric($value) && (float) $value > 0;
        });

        if (!$hasData && isset($dashboardStats['top_clients'])) {
            $hasData = collect($dashboardStats['top_clients'])->contains(function ($client) {
                return (isset($client['total_deposits']) && (float) $client['total_deposits'] > 0)
                    || (isset($client['transaction_count']) && (int) $client['transaction_count'] > 0);
            });
        }
    @endphp

    @unless($hasData)
    <div class="alert alert-info" style="background: #e3f2fd; border: 1px solid #2196f3; border-radius: 12px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
        @icon('fa-info-circle', ['style' => 'color: #2196f3; font-size: 24px;'])
        <div>
            <strong style="color: #1565c0; font-size: 16px;">No Data Available</strong>
            <p style="color: #1976d2; margin: 4px 0 0 0; font-size: 14px;">
                There are no transactions for the selected date range ({{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}).
                Try selecting a different period or ensure recent transactions have been recorded.
            </p>
        </div>
    </div>
    @endunless

    <!-- Receipt Type Tabs -->
    <div class="analytics-tabs">
        <div class="tabs-nav" role="tablist" aria-label="Transaction type filter">
            <button class="tab-item {{ $receiptType === null ? 'active' : '' }}"
                    data-type=""
                    onclick="switchTab('')"
                    role="tab"
                    aria-label="View all transaction types"
                    aria-selected="{{ $receiptType === null ? 'true' : 'false' }}">
                @icon('fa-chart-pie', ['aria-hidden' => 'true']) All Types
            </button>
            <button class="tab-item {{ $receiptType == 1 ? 'active' : '' }}"
                    data-type="1"
                    onclick="switchTab('1')"
                    role="tab"
                    aria-label="View client receipts only"
                    aria-selected="{{ $receiptType == 1 ? 'true' : 'false' }}">
                @icon('fa-receipt', ['aria-hidden' => 'true']) Client Receipts
            </button>
            <button class="tab-item {{ $receiptType == 2 ? 'active' : '' }}"
                    data-type="2"
                    onclick="switchTab('2')"
                    role="tab"
                    aria-label="View office receipts only"
                    aria-selected="{{ $receiptType == 2 ? 'true' : 'false' }}">
                @icon('fa-building', ['aria-hidden' => 'true']) Office Receipts
            </button>
            <button class="tab-item {{ $receiptType == 3 ? 'active' : '' }}"
                    data-type="3"
                    onclick="switchTab('3')"
                    role="tab"
                    aria-label="View invoices only"
                    aria-selected="{{ $receiptType == 3 ? 'true' : 'false' }}">
                @icon('fa-file-invoice-dollar', ['aria-hidden' => 'true']) Invoices
            </button>
            <button class="tab-item {{ $receiptType == 4 ? 'active' : '' }}"
                    data-type="4"
                    onclick="switchTab('4')"
                    role="tab"
                    aria-label="View journal receipts only"
                    aria-selected="{{ $receiptType == 4 ? 'true' : 'false' }}">
                @icon('fa-book', ['aria-hidden' => 'true']) Journal Receipts
            </button>
        </div>
    </div>

    <!-- Key Metrics Grid -->
    <div class="stats-grid">
        <!-- Total Deposits -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Total Deposits</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_deposits'], 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['deposit_count'], 0) }} transactions</div>
                </div>
                <div class="stat-card-icon blue">
                    @icon('fa-dollar-sign')
                </div>
            </div>
            @if($dashboardStats['monthly_stats']['trends']['deposits']['direction'] != 'neutral')
            <span class="stat-card-trend {{ $dashboardStats['monthly_stats']['trends']['deposits']['direction'] }}">
                {!! \App\Helpers\IconHelper::fromLegacy('fas fa-arrow-' . ($dashboardStats['monthly_stats']['trends']['deposits']['direction'] == 'up' ? 'up' : 'down')) !!}
                {{ $dashboardStats['monthly_stats']['trends']['deposits']['percentage'] }}% vs last period
            </span>
            @endif
        </div>
        @endif

        <!-- Total Fee Transfers -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Fee Transfers</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_fee_transfers'], 2) }}</div>
                    <div class="stat-card-subtitle">This period</div>
                </div>
                <div class="stat-card-icon green">
                    @icon('fa-exchange-alt')
                </div>
            </div>
        </div>
        @endif

        <!-- Total Office Receipts -->
        @if($receiptType === null || $receiptType == 2)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Office Receipts</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_office_receipts'], 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['office_receipt_count'], 0) }} transactions</div>
                </div>
                <div class="stat-card-icon orange">
                    @icon('fa-building')
                </div>
            </div>
            @if($dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] != 'neutral')
            <span class="stat-card-trend {{ $dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] }}">
                {!! \App\Helpers\IconHelper::fromLegacy('fas fa-arrow-' . ($dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] == 'up' ? 'up' : 'down')) !!}
                {{ $dashboardStats['monthly_stats']['trends']['office_receipts']['percentage'] }}% vs last period
            </span>
            @endif
        </div>
        @endif

        <!-- Unallocated Receipts -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Unallocated Receipts</div>
                    <div class="stat-card-value">{{ number_format($dashboardStats['receipt_stats']['unallocated_count'], 0) }}</div>
                    <div class="stat-card-subtitle">Require attention</div>
                </div>
                <div class="stat-card-icon purple">
                    @icon('fa-exclamation-triangle')
                </div>
            </div>
            <span class="stat-card-trend neutral">
                {{ $dashboardStats['receipt_stats']['allocation_percentage'] }}% allocation rate
            </span>
        </div>
        @endif

        <!-- Average Days to Allocate -->
        @if($receiptType === null || $receiptType == 1)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Avg. Days to Allocate</div>
                    <div class="stat-card-value">{{ number_format($dashboardStats['allocation_metrics']['average_days_to_allocate'], 1) }}</div>
                    <div class="stat-card-subtitle">Processing time</div>
                </div>
                <div class="stat-card-icon teal">
                    @icon('fa-clock')
                </div>
            </div>
            @if($dashboardStats['allocation_metrics']['old_unallocated_count'] > 0)
            <span class="stat-card-trend down">
                {{ number_format($dashboardStats['allocation_metrics']['old_unallocated_count'], 0) }} receipts > 30 days old
            </span>
            @endif
        </div>
        @endif

        <!-- Unpaid Invoices -->
        @if($receiptType === null || $receiptType == 3)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Unpaid Invoices</div>
                    <div class="stat-card-value">{{ number_format($dashboardStats['invoice_stats']['unpaid_invoices'], 0) }}</div>
                    <div class="stat-card-subtitle">${{ number_format($dashboardStats['invoice_stats']['unpaid_amount'], 2) }} outstanding</div>
                </div>
                <div class="stat-card-icon red">
                    @icon('fa-file-invoice-dollar')
                </div>
            </div>
            @if($dashboardStats['invoice_stats']['overdue_invoices'] > 0)
            <span class="stat-card-trend down">
                {{ number_format($dashboardStats['invoice_stats']['overdue_invoices'], 0) }} overdue
            </span>
            @endif
        </div>
        @endif

        <!-- Invoice Payment Rate -->
        @if($receiptType === null || $receiptType == 3)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Invoice Payment Rate</div>
                    <div class="stat-card-value">{{ $dashboardStats['invoice_stats']['payment_rate'] }}%</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['invoice_stats']['paid_invoices'], 0) }} of {{ number_format($dashboardStats['invoice_stats']['total_invoices'], 0) }} paid</div>
                </div>
                <div class="stat-card-icon green">
                    @icon('fa-check-circle')
                </div>
            </div>
        </div>
        @endif

        <!-- Total Invoices Issued -->
        @if($receiptType === null || $receiptType == 3)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Invoices Issued</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_invoices_issued'], 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['invoice_count'], 0) }} invoices</div>
                </div>
                <div class="stat-card-icon blue">
                    @icon('fa-receipt')
                </div>
            </div>
        </div>
        @endif

        <!-- Total Journal Receipts -->
        @if($receiptType === null || $receiptType == 4)
        <div class="stat-card">
            <div class="stat-card-header">
                <div>
                    <div class="stat-card-title">Journal Receipts</div>
                    <div class="stat-card-value">${{ number_format($dashboardStats['monthly_stats']['total_journal_receipts'] ?? 0, 2) }}</div>
                    <div class="stat-card-subtitle">{{ number_format($dashboardStats['monthly_stats']['journal_receipt_count'] ?? 0, 0) }} transactions</div>
                </div>
                <div class="stat-card-icon teal">
                    @icon('fa-book')
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Charts Section -->
    <div class="chart-grid">
        <!-- Trend Chart -->
        <div class="chart-card full-width">
            <h3 class="chart-card-title">
                @icon('fa-chart-line') 6-Month Financial Trend
            </h3>
            <div class="chart-container">
                <canvas id="trendChart" role="img" aria-label="Line chart showing 6-month financial trend for deposits, office receipts, and invoices"></canvas>
            </div>
        </div>

        <!-- Payment Method Breakdown -->
        <div class="chart-card">
            <h3 class="chart-card-title">
                @icon('fa-credit-card') Payment Methods
            </h3>
            <div class="chart-container">
                <canvas id="paymentMethodChart" role="img" aria-label="Doughnut chart showing payment method distribution"></canvas>
            </div>
        </div>

        <!-- Receipt Allocation Status -->
        <div class="chart-card">
            <h3 class="chart-card-title">
                @icon('fa-tasks') Receipt Allocation
            </h3>
            <div class="chart-container">
                <canvas id="allocationChart" role="img" aria-label="Pie chart showing allocated versus unallocated receipt ratio"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Clients Table -->
    @if(isset($dashboardStats['top_clients']) && count($dashboardStats['top_clients']) > 0)
    <div class="table-card">
        <h3 class="table-card-title">
            @icon('fa-trophy') Top Clients by Transaction Volume
        </h3>
        <table class="analytics-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Client ID</th>
                    <th>Client Name</th>
                    <th>Total Deposits</th>
                    <th>Transactions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dashboardStats['top_clients'] as $index => $client)
                <tr>
                    <td>
                        @if($index == 0)
                            @icon('fa-trophy', ['style' => 'color: #fbbf24;']) #{{ $index + 1 }}
                        @elseif($index == 1)
                            @icon('fa-medal', ['style' => 'color: #94a3b8;']) #{{ $index + 1 }}
                        @elseif($index == 2)
                            @icon('fa-award', ['style' => 'color: #cd7f32;']) #{{ $index + 1 }}
                        @else
                            #{{ $index + 1 }}
                        @endif
                    </td>
                    <td><strong>{{ $client['client_unique_id'] }}</strong></td>
                    <td class="client-name">{{ $client['name'] }}</td>
                    <td class="amount">${{ number_format($client['total_deposits'], 2) }}</td>
                    <td>{{ number_format($client['transaction_count'], 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="table-card">
        <h3 class="table-card-title">
            @icon('fa-trophy') Top Clients by Transaction Volume
        </h3>
        <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
            @icon('fa-users', ['style' => 'font-size: 48px; margin-bottom: 16px; opacity: 0.5;'])
            <p style="font-size: 16px; margin: 0;">No client data available for this period</p>
        </div>
    </div>
    @endif

    <!-- Quick Links -->
    <h3 style="font-size: 20px; font-weight: 700; color: #1e293b; margin-bottom: 16px;">
        @icon('fa-link') Quick Access
    </h3>
    <div class="quick-links">
        <a href="{{ route('clients.clientreceiptlist') }}" class="quick-link-card">
            @icon('fa-receipt')
            <h4>Client Receipts</h4>
        </a>
        <a href="{{ route('clients.invoicelist') }}" class="quick-link-card">
            @icon('fa-file-invoice-dollar')
            <h4>Invoice Lists</h4>
        </a>
        <a href="{{ route('clients.officereceiptlist') }}" class="quick-link-card">
            @icon('fa-building')
            <h4>Office Receipts</h4>
        </a>
        <a href="{{ route('clients.journalreceiptlist') }}" class="quick-link-card">
            @icon('fa-book')
            <h4>Journal Receipts</h4>
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded. Charts will not display.');
        return;
    }

    const chartColors = {
        blue: 'rgba(102, 126, 234, 0.8)',
        green: 'rgba(17, 153, 142, 0.8)',
        orange: 'rgba(240, 147, 251, 0.8)',
        purple: 'rgba(79, 172, 254, 0.8)',
        red: 'rgba(250, 112, 154, 0.8)',
    };

    // Trend Chart
    try {
        const trendData = @json($dashboardStats['trend_data'] ?? null);

        if (!trendData || !trendData.months || trendData.months.length === 0) {
            document.getElementById('trendChart').parentElement.innerHTML =
                '<p style="text-align:center;padding:100px 20px;color:#94a3b8;font-size:14px;">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-info-circle') : '<i class="fa fa-info-circle"></i>') + ' No trend data available for the selected period</p>';
        } else {
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendData.months,
                    datasets: [
                        {
                            label: 'Deposits',
                            data: trendData.deposits || [],
                            borderColor: chartColors.blue,
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true,
                        },
                        {
                            label: 'Office Receipts',
                            data: trendData.office_receipts || [],
                            borderColor: chartColors.green,
                            backgroundColor: 'rgba(17, 153, 142, 0.1)',
                            tension: 0.4,
                            fill: true,
                        },
                        {
                            label: 'Invoices',
                            data: trendData.invoices || [],
                            borderColor: chartColors.orange,
                            backgroundColor: 'rgba(240, 147, 251, 0.1)',
                            tension: 0.4,
                            fill: true,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing trend chart:', error);
        document.getElementById('trendChart').parentElement.innerHTML =
            '<p style="text-align:center;padding:100px 20px;color:#dc3545;font-size:14px;">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-exclamation-triangle') : '<i class="fa fa-exclamation-triangle"></i>') + ' Error loading chart</p>';
    }

    // Payment Method Chart
    try {
        const paymentMethods = @json($paymentMethods ?? []);

        if (!paymentMethods || paymentMethods.length === 0) {
            document.getElementById('paymentMethodChart').parentElement.innerHTML =
                '<p style="text-align:center;padding:100px 20px;color:#94a3b8;font-size:14px;">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-info-circle') : '<i class="fa fa-info-circle"></i>') + ' No payment method data for this period</p>';
        } else {
            const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
            new Chart(paymentMethodCtx, {
                type: 'doughnut',
                data: {
                    labels: paymentMethods.map(pm => pm.method || 'Not Specified'),
                    datasets: [{
                        data: paymentMethods.map(pm => parseFloat(pm.total) || 0),
                        backgroundColor: [
                            chartColors.blue,
                            chartColors.green,
                            chartColors.orange,
                            chartColors.purple,
                            chartColors.red,
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': $' + context.parsed.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                }
                            }
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing payment method chart:', error);
        document.getElementById('paymentMethodChart').parentElement.innerHTML =
            '<p style="text-align:center;padding:100px 20px;color:#dc3545;font-size:14px;">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-exclamation-triangle') : '<i class="fa fa-exclamation-triangle"></i>') + ' Error loading chart</p>';
    }

    // Allocation Chart
    try {
        const receiptStats = @json($dashboardStats['receipt_stats'] ?? null);

        if (!receiptStats || ((parseInt(receiptStats.allocated_count) || 0) === 0 && (parseInt(receiptStats.unallocated_count) || 0) === 0)) {
            document.getElementById('allocationChart').parentElement.innerHTML =
                '<p style="text-align:center;padding:100px 20px;color:#94a3b8;font-size:14px;">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-info-circle') : '<i class="fa fa-info-circle"></i>') + ' No allocation data available</p>';
        } else {
            const allocationCtx = document.getElementById('allocationChart').getContext('2d');
            new Chart(allocationCtx, {
                type: 'pie',
                data: {
                    labels: ['Allocated', 'Unallocated'],
                    datasets: [{
                        data: [
                            parseInt(receiptStats.allocated_count) || 0,
                            parseInt(receiptStats.unallocated_count) || 0
                        ],
                        backgroundColor: [
                            chartColors.green,
                            chartColors.orange,
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        }
    } catch (error) {
        console.error('Error initializing allocation chart:', error);
        document.getElementById('allocationChart').parentElement.innerHTML =
            '<p style="text-align:center;padding:100px 20px;color:#dc3545;font-size:14px;">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-exclamation-triangle') : '<i class="fa fa-exclamation-triangle"></i>') + ' Error loading chart</p>';
    }
});

function switchTab(receiptType) {
    // Show loading indicator
    const loader = document.querySelector('.loader');
    if (loader) {
        loader.style.display = 'block';
    }
    
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);

    if (receiptType === '' || receiptType === null) {
        params.delete('receipt_type');
    } else {
        params.set('receipt_type', receiptType);
    }

    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate) params.set('start_date', startDate);
    if (endDate) params.set('end_date', endDate);

    window.location.href = url.pathname + '?' + params.toString();
}

function handleQuickSelect(value) {
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const today = new Date();

    let startDate;
    let endDate;

    switch (value) {
        case 'this_month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'last_month':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'this_quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            startDate = new Date(today.getFullYear(), quarter * 3, 1);
            endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
            break;
        case 'this_year':
            startDate = new Date(today.getFullYear(), 0, 1);
            endDate = new Date(today.getFullYear(), 11, 31);
            break;
        case 'last_30_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 30);
            endDate = today;
            break;
        case 'last_90_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 90);
            endDate = today;
            break;
        default:
            return;
    }

    if (startDate && endDate) {
        startDateInput.value = startDate.toISOString().split('T')[0];
        endDateInput.value = endDate.toISOString().split('T')[0];
        // Auto-submit the form when a quick select option is chosen
        document.getElementById('dateRangeForm').submit();
    }
}

const dateRangeForm = document.getElementById('dateRangeForm');
if (dateRangeForm) {
    dateRangeForm.addEventListener('submit', function() {
        const loader = document.querySelector('.loader');
        if (loader) {
            loader.style.display = 'block';
        }
    });
}

window.switchTab = switchTab;
window.handleQuickSelect = handleQuickSelect;

</script>
@endpush

