@extends('layouts.crm_client_detail')
@section('title', 'Journal Receipt List')

@section('styles')
<style>
    /* Modern Page Styling */
    .main-content {
        background: #f8fafc;
        min-height: 100vh;
    }

    .section {
        padding-top: 24px !important;
    }

    /* Modern Card Styling */
    .card {
        border: none;
        border-radius: 16px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        overflow: hidden;
        background: white;
        margin: 0;
        width: 100%;
    }

    /* Modern Header with Gradient */
    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 24px 32px;
        border-bottom: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .card-header h4 {
        color: white !important;
        font-size: 24px !important;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.5px;
        flex: 1;
    }

    .card-body {
        padding: 32px;
        background: white;
        border-radius: 0 0 16px 16px;
    }

    /* Modern Table Styling */
    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 0 0 1px #e2e8f0;
    }

    .table {
        margin-bottom: 0;
        font-size: 14px;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table thead {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }

    .table thead th {
        border: none;
        padding: 16px 20px;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569 !important;
        white-space: nowrap;
    }

    .table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .table tbody tr:hover {
        background: #f8fafc;
        transform: scale(1.001);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .table tbody td {
        padding: 16px 20px;
        vertical-align: middle;
        border: none;
        color: #334155 !important;
    }

    .table tbody tr:last-child {
        border-bottom: none;
    }

    /* Modern Checkbox */
    .custom-checkbox .custom-control-input:checked ~ .custom-control-label::before {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
    }

    .custom-control-label::before {
        border-radius: 4px;
        border: 2px solid #e2e8f0;
    }

    /* Modern Button Styling */
    .btn-primary.Validate_Receipt {
        background: white !important;
        color: #667eea !important;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn-primary.Validate_Receipt[style*="background-color: #394eea"],
    .Validate_Receipt[style*="background-color: #394eea"] {
        color: white !important;
    }

    .btn-primary.Validate_Receipt:hover {
        background: rgba(255, 255, 255, 0.95) !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary.Validate_Receipt:active {
        transform: translateY(0);
    }

    .btn-primary.Validate_Receipt svg.lucide {
        width: 14px;
        height: 14px;
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
    .Validate_Receipt svg.lucide {
        color: #10b981;
        stroke: #10b981;
        width: 16px;
        height: 16px;
        margin-right: 6px;
    }

    /* Amount Styling */
    .table tbody td[id^="deposit_"] {
        font-weight: 700;
        color: #dc2626;
        font-family: 'Courier New', monospace;
    }

    /* Modern Pagination */
    .card-footer {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        padding: 20px 32px;
        border-radius: 0 0 16px 16px;
    }

    .pagination {
        margin: 0;
        display: flex;
        list-style: none;
        padding: 0;
        justify-content: center;
    }

    .pagination .page-link {
        border: 2px solid #e2e8f0;
        color: #667eea;
        margin: 0 4px;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .pagination .page-link:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
        transform: translateY(-2px);
    }

    .pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
    }

    /* Sortable Column Headers */
    .sortable-header {
        cursor: pointer;
        user-select: none;
        position: relative;
        padding-right: 30px !important;
        transition: all 0.2s ease;
    }

    .sortable-header:hover {
        background: rgba(102, 126, 234, 0.1);
        color: #667eea !important;
    }

    .sort-icon {
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

    .sortable-header:hover .sort-icon {
        opacity: 0.6;
    }

    .sort-icon svg.lucide {
        width: 8px;
        height: 8px;
        color: #475569;
        stroke: #475569;
    }

    .sortable-header.sort-asc .sort-icon {
        opacity: 1;
    }

    .sortable-header.sort-asc .sort-icon svg.lucide:first-of-type {
        color: #667eea;
        stroke: #667eea;
        width: 10px;
        height: 10px;
    }

    .sortable-header.sort-desc .sort-icon {
        opacity: 1;
    }

    .sortable-header.sort-desc .sort-icon svg.lucide:last-of-type {
        color: #667eea;
        stroke: #667eea;
        width: 10px;
        height: 10px;
    }

    /* No Records State */
    .table tbody td[colspan] {
        padding: 60px 20px !important;
        text-align: center;
        color: #94a3b8;
        font-size: 16px;
        font-weight: 600;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .card-header {
            padding: 20px;
        }

        .card-header h4 {
            font-size: 20px !important;
            width: 100%;
        }

        .card-body {
            padding: 20px;
        }

        .table {
            font-size: 12px;
        }

        .table thead th,
        .table tbody td {
            padding: 12px 10px;
        }

        .btn-primary.Validate_Receipt {
            width: 100%;
            justify-content: center;
        }
    }

    /* Modern Error/Success Messages */
    .custom-error-msg {
        border-radius: 12px;
        margin: 0 32px 20px;
        padding: 16px 20px;
        font-weight: 600;
        display: none;
    }

    .custom-error-msg.alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 2px solid #10b981;
    }

    .custom-error-msg.alert-danger {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 2px solid #ef4444;
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

    /* Filter Panel Styles */
    .filter_panel {
        background: #f8fafc;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid #e2e8f0;
    }

    .filter_panel h4 {
        color: #1e293b;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #667eea;
        display: inline-block;
    }

    .filter_panel {
        animation: slideDown 0.3s ease;
    }

    /* Modern Form Inputs */
    .form-group label {
        color: #475569 !important;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    /* Modern Button Styling */
    .btn {
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-info {
        background: linear-gradient(135deg, #00b4db 0%, #0083b0 100%);
        color: white;
    }

    .btn-theme {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        backdrop-filter: blur(10px);
    }

    .filter-buttons-container {
        margin-top: 20px;
    }

    @include('crm.clients.partials.enhanced-date-filter-styles')
</style>
@endsection

@section('content')
<div class="main-content">
    <section class="section" style="padding-top: 40px;">
        <div class="section-body">
            @include('../Elements/flash-message')
            
            <div class="card">
                <div class="custom-error-msg">
                </div>
                <div class="card-header">
                    <h4 style="color: #4a5568 !important;">All Journal Receipt List</h4>
                    <div class="d-flex align-items-center">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('clients.analytics-dashboard') }}" class="btn btn-theme btn-theme-sm mr-2" title="View Financial Analytics Dashboard">@icon('fa-chart-line') Analytics</a>
                        @endif
                        <a href="javascript:;" style="background: #394eea;color: white;" class="btn btn-theme btn-theme-sm filter_btn mr-2">@icon('fa-filter') Filter</a>
                        <button class="btn btn-primary Validate_Receipt" style="background-color: #394eea !important; color: white !important;">
                            @icon('fa-check-circle')
                            Validate Receipt
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Enhanced Date Filter Panel -->
                    <div class="filter_panel">
                        <h4>
                            Search By Details
                            @if(request()->hasAny(['date_filter_type', 'from_date', 'to_date', 'financial_year']))
                                <span class="active-filters-badge">
                                    @icon('fa-filter')
                                    {{ collect([request('date_filter_type'), request('from_date'), request('to_date'), request('financial_year')])->filter()->count() }} Active
                                </span>
                            @endif
                        </h4>
                        <form action="{{URL::to('/clients/journalreceiptlist')}}" method="get" id="filterForm">
                            <!-- Enhanced Date Filter -->
                            @include('crm.clients.partials.enhanced-date-filter')

                            <!-- Action Buttons -->
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">
                                            @icon('fa-search') Search
                                        </button>
                                        <a class="btn btn-info" href="{{URL::to('/clients/journalreceiptlist')}}">
                                            @icon('fa-redo') Reset All
                                        </a>
                                        @if(request()->hasAny(['date_filter_type', 'from_date', 'to_date', 'financial_year']))
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
                                    <th class="sortable-header {{ request('sort_by') == 'receipt_id' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="receipt_id">
                                        SNo.
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'client_id' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="client_id">
                                        Client Id
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
                                        Trans. Date
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'entry_date' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="entry_date">
                                        Entry Date
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'trans_no' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="trans_no">
                                        Trans. No
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'invoice_no' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="invoice_no">
                                        Invoice No
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
                                    <th class="sortable-header {{ request('sort_by') == 'validate_receipt' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="validate_receipt">
                                        Receipt Validate
                                        <span class="sort-icon">
                                            @icon('fa-caret-up')
                                            @icon('fa-caret-down')
                                        </span>
                                    </th>
                                    <th class="sortable-header {{ request('sort_by') == 'validated_by' ? (request('sort_order') == 'desc' ? 'sort-desc' : 'sort-asc') : '' }}" data-sort="validated_by">
                                        Validate By
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
                                        if(isset($list->voided_or_validated_by) && $list->voided_or_validated_by != ""){
                                            $validate_by = \App\Models\Staff::select('id','first_name','last_name')->where('id', $list->voided_or_validated_by)->first();
                                            $validate_by_full_name = $validate_by ? $validate_by->first_name.' '.$validate_by->last_name : 'N/A';
                                        } else {
                                            $validate_by_full_name = "-";
                                        }?>
                                        <?php
                                        $receipt_validate = ($list->validate_receipt == 1) ? 'Yes' : 'No';
                                        ?>
                                        <tr id="id_{{@$list->id}}">
                                            <td style="white-space: initial;" class="text-center">
                                                <div class="custom-checkbox custom-control">
                                                    <input data-id="{{@$list->id}}" data-receiptid="{{@$list->receipt_id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input  your-checkbox" id="checkbox-{{$i}}">
                                                    <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                                </div>
                                            </td>
                                            <td><?php echo $list->receipt_id;?></td>
                                            <td><?php if(isset($client_info->client_id)) {echo $client_info->client_id;} else {echo 'N/A';}?></td>
                                            <td><?php if(isset($client_info->first_name)) { echo $client_info->first_name;} else {echo 'N/A';} ?></td><td><?php echo $list->trans_date;?></td>
                                            <td><?php echo $list->entry_date;?></td>
                                            <td><?php echo $list->trans_no;?></td>
                                            <td><?php echo $list->invoice_no;?></td>
                                            <td id="deposit_{{@$list->id}}"><?php echo "$".$list->total_withdrawal_amount;?></td>
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
                                            <td id="validateby_{{@$list->id}}"><?php echo $validate_by_full_name;?></td> <!-- New field data -->
                                        </tr>
                                        <?php $i++; ?>
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
jQuery(document).ready(function($){
    // Filter button toggle
    $('.filter_btn').on('click', function(){
        $('.filter_panel').toggle();
    });

    // Enhanced Date Filter Scripts
    @include('crm.clients.partials.enhanced-date-filter-scripts')

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

    //validate receipt
    $(document).delegate('.Validate_Receipt', 'click', function(){
        console.log('Validate Receipt clicked');
        console.log('clickedReceiptIds:', clickedReceiptIds);

        if ( clickedReceiptIds.length > 0)
        {

            var mergeStr = "Are you sure want to validate these receipt?";
            if (confirm(mergeStr)) {
                console.log('Starting AJAX request...');
                console.log('URL:', "{{URL::to('/')}}/validate_receipt");
                console.log('Data:', {clickedReceiptIds:clickedReceiptIds, receipt_type:4});
                
                $.ajax({
                    type:'post',
                    url:"{{URL::to('/')}}/validate_receipt",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                    data: {clickedReceiptIds:clickedReceiptIds,receipt_type:4},
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
                            $('#validate_' + subArray.id +' span')
                                .removeClass('badge-danger')
                                .addClass('modern-badge badge-success')
                                .html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Yes');
                            if(subArray.first_name != ""){
                                var validateby_full_name = subArray.first_name+" "+subArray.last_name;
                            } else {
                                var validateby_full_name = "-";
                            }
                            $('#validateby_'+subArray.id).text(validateby_full_name);
                        });
                        $('.custom-error-msg').text(obj.message);
                        $('.custom-error-msg').show();
                        $('.custom-error-msg').addClass('alert alert-success');
                        
                        // Clear checkboxes after successful validation
                        clickedReceiptIds = [];
                        $('.cb-element').prop('checked', false);
                        $('#checkbox-all').prop('checked', false);
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


    $('.cb-element').change(function () {
        if ($('.cb-element:checked').length == $('.cb-element').length){
            $('#checkbox-all').prop('checked',true);
        } else {
            $('#checkbox-all').prop('checked',false);
        }
    });

    // Sortable column headers
    $('.sortable-header').on('click', function() {
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
