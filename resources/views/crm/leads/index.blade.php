@extends('layouts.crm_client_detail')
@section('title', 'Leads')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for leads index page */
    
    /* Edit Icon Button Styling */
    .btn-edit-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        color: white !important;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
        margin-right: 8px;
    }

    .btn-edit-icon:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        color: white !important;
        text-decoration: none;
    }

    .btn-edit-icon:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
        color: white !important;
    }

    .btn-edit-icon i {
        font-size: 14px;
        color: white;
    }

    /* Archive Icon Button Styling */
    .btn-archive-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        border: none;
        border-radius: 8px;
        color: white !important;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
        cursor: pointer;
    }

    .btn-archive-icon:hover {
        background: linear-gradient(135deg, #e58e0a 0%, #c26505 100%);
        box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        color: white !important;
        text-decoration: none;
    }

    .btn-archive-icon:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.25);
        color: white !important;
    }

    .btn-archive-icon i {
        font-size: 14px;
        color: white;
    }

    .action-buttons {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .listing-container .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }

    .listing-container .card-header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .listing-container .card-header-title {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .listing-container .list-record-total {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
    }

    .listing-container .per-page-select {
        border: 1px solid white !important;
        border-radius: 8px !important;
        background: white !important;
        color: #667eea !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        min-width: 110px;
        width: auto !important;
        flex: 0 0 auto;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .listing-container .per-page-select:focus {
        outline: none;
        border-color: #667eea !important;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
    }

    .listing-container .per-page-select option {
        background: white;
        color: #667eea;
    }

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
        display: flex;
        align-items: center;
        gap: 12px;
    }

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
    }

    .clear-filter-btn {
        background: transparent;
        border: 2px solid #ef4444;
        color: #ef4444;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .clear-filter-btn:hover {
        background: #ef4444;
        color: white;
    }

    .status-badge {
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

    .status-badge.open {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
    }

    .status-badge.closed {
        background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
        color: white;
    }

    .status-badge.converted {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: white;
    }

    .sortable-header a {
        color: inherit;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .sortable-header i {
        color: #94a3b8;
    }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
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
                    <div class="card-header-title">
                        <h4>All Leads</h4>
                        @php
                            $filteredTotal = (int) ($lists->total() ?? 0);
                        @endphp
                        <span class="list-record-total" title="Total records matching current filters">
                            Total: {{ number_format($filteredTotal) }}
                        </span>
                    </div>

                    <div class="card-header-actions">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('clients.insights', ['section' => 'leads']) }}" class="btn btn-theme btn-theme-sm" title="Lead Insights">
                            @icon('fa-chart-line') Insights
                        </a>
                        @endif
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm" data-bs-toggle="modal" data-bs-target="#importLeadModal" title="Import Lead">
                            @icon('fa-upload') Import Lead
                        </a>
                        <a href="{{route('leads.create')}}" class="btn btn-primary">Create Lead</a>
                        <select name="per_page" id="per_page" class="form-control per-page-select">
                            @foreach([10, 20, 50, 100, 200] as $option)
                                <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>
                                    {{ $option }} / page
                                </option>
                            @endforeach
                        </select>
                        @if(Auth::user() && (int) Auth::user()->role === 1)
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm" onclick="exportLeadList({{ $filteredTotal }})" title="Export lead list as CSV">
                            @icon('fa-file-export') Export CSV
                        </a>
                        @endif
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">
                            @icon('fa-filter') Filter
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item is_checked_client" style="display:none;">
                            <a class="btn btn-primary emailmodal" href="javascript:;">Send Mail</a>
                        </li>
                        <li class="nav-item is_checked_client" style="display:none;">
                            <a class="btn btn-primary" href="javascript:;">Change Assignee</a>
                        </li>

                        <li class="nav-item is_checked_client_merge" style="display:none;">
                            <a class="btn btn-primary" href="javascript:;">Merge</a>
                        </li>

                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link " id="clients-tab"  href="{{URL::to('/clients')}}" >Clients</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="archived-tab"  href="{{URL::to('/archived')}}" >Archived</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link active" id="lead-tab"  href="{{URL::to('/leads')}}" >Leads</a>
                        </li>
                    </ul>

                    @php
                        $leadFilters = collect([
                            'client_id' => request('client_id'),
                            'name' => request('name'),
                            'email' => request('email'),
                            'phone' => request('phone'),
                            'lead_stage_filter' => request('lead_stage_filter'),
                            'include_inactive' => request('include_inactive'),
                            'quick_date_range' => request('quick_date_range'),
                            'from_date' => request('from_date'),
                            'to_date' => request('to_date'),
                            'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
                        ]);
                        $activeLeadFilters = $leadFilters->filter(function ($value) {
                            return $value !== null && $value !== '';
                        })->count();
                    @endphp

                    <div class="filter_panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeLeadFilters > 0)
                                    <span class="active-filters-badge">
                                        @icon('fa-filter') {{ $activeLeadFilters }} Active
                                    </span>
                                @endif
                            </h4>
                            @if($activeLeadFilters > 0)
                                <button type="button" class="clear-filter-btn" id="clearLeadFilters">
                                    @icon('fa-undo') Clear Filters
                                </button>
                            @endif
                        </div>
                        <form action="{{URL::to('/leads')}}" method="get" id="leadFilterForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="client_id">Client ID</label>
                                        <input type="text" name="client_id" id="client_id" value="{{ request('client_id') }}" class="form-control" placeholder="Client ID">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name">Name</label>
                                        <input type="text" name="name" id="name" value="{{ request('name') }}" class="form-control" placeholder="Lead name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="text" name="email" id="email" value="{{ request('email') }}" class="form-control" placeholder="Lead email">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="phone">Phone</label>
                                        <input type="text" name="phone" id="phone" value="{{ request('phone') }}" class="form-control" placeholder="Lead phone">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="lead_stage_filter">Stage</label>
                                        <select name="lead_stage_filter" id="lead_stage_filter" class="form-control">
                                            <option value="">All stages</option>
                                            @foreach(($leadStageLabels ?? []) as $stageVal => $stageLabel)
                                                <option value="{{ $stageVal }}" {{ request('lead_stage_filter') === $stageVal ? 'selected' : '' }}>
                                                    {{ $stageLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="d-block">&nbsp;</label>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="include_inactive" id="include_inactive" value="1" {{ request('include_inactive') ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="include_inactive">Include inactive (Not qualified / Hostile)</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date_filter_field">Date Field</label>
                                        <select name="date_filter_field" id="date_filter_field" class="form-control">
                                            <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                                            <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Last Updated</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="date-filter-section mt-2">
                                <input type="hidden" name="quick_date_range" id="lead_quick_date_range" value="{{ request('quick_date_range') }}">
                                @php
                                    $quickFilters = [
                                        'today' => 'Today',
                                        'this_week' => 'This Week',
                                        'this_month' => 'This Month',
                                        'last_month' => 'Last Month',
                                        'last_30_days' => 'Last 30 Days',
                                        'last_90_days' => 'Last 90 Days',
                                        'this_year' => 'This Year',
                                        'last_year' => 'Last Year',
                                    ];
                                @endphp
                                <div class="quick-filters">
                                    @foreach($quickFilters as $key => $label)
                                        <span class="quick-filter-chip lead-quick-filter {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}">
                                            @icon('fa-calendar') {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="lead_from_date">From Date</label>
                                        <input type="date" name="from_date" id="lead_from_date" value="{{ request('from_date') }}" class="form-control">
                                    </div>
                                    <span class="date-range-arrow">→</span>
                                    <div class="form-group">
                                        <label for="lead_to_date">To Date</label>
                                        <input type="date" name="to_date" id="lead_to_date" value="{{ request('to_date') }}" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">Apply Filters</button>
                                        <a class="btn btn-info" href="{{URL::to('/leads')}}">Reset</a>
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
                                            <label for="checkbox-all" class="custom-control-label">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th class="sortable-header">@sortablelink('first_name', 'Name')</th>
                                    <th>Info</th>
                                    <th class="sortable-header">@sortablelink('created_at', 'Contact Date')</th>
                                    <th class="sortable-header">@sortablelink('lead_status', 'Stage')</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if($filteredTotal > 0)
                                <?php $i = 0; ?>
                                @foreach (@$lists as $list)
                                    <?php
                                    // Followup functionality removed
                                    ?>
                                    <tr id="id_{{@$list->id}}">
                                        <td style="white-space: initial;" class="text-center">
                                            <div class="custom-checkbox custom-control">
                                                <input data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" data-clientid="{{@$list->client_id}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input  your-checkbox" id="checkbox-{{$i}}">
                                                <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td style="white-space: initial;">
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode(@$list->id))) }}">
                                                {{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }}
                                                {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}
                                            </a>

                                        </td>
                                        <td>@icon('fa-mobile') {{@$list->phone}} <br/> @icon('fa-envelope') {{@$list->email}}</td>
                                        <td>{{date('d/m/Y h:i:s a', strtotime($list->created_at))}}</td>
                                        <td>
                                            @php
                                                $stageKey = $list->lead_status ?: 'new';
                                                $stageLabel = ($leadStageLabels[$stageKey] ?? ucfirst(str_replace('_', ' ', $stageKey)));
                                                $stageSlug = \Illuminate\Support\Str::slug($stageKey, '_');
                                            @endphp
                                            <span class="status-badge {{ $stageSlug }}">
                                                @icon('fa-circle') {{ $stageLabel }}
                                            </span>
                                            @if($list->lead_status === 'follow_up' && $list->followup_date)
                                                <br><small class="text-muted">Follow-up: {{ $list->followup_date->format('d/m/Y') }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="{{route('clients.edit', base64_encode(convert_uuencode(@$list->id)))}}" class="btn-edit-icon" title="Edit Lead">
                                                    @icon('fa-edit')
                                                </a>
                                                <form action="{{ route('leads.archive', base64_encode(convert_uuencode(@$list->id))) }}" method="POST" class="archive-lead-form" style="display: inline-block;">
                                                    @csrf
                                                    <button type="button" class="btn-archive-icon" title="Archive Lead" onclick='confirmArchive(event, @json(trim(($list->first_name ?? '') . ' ' . ($list->last_name ?? ''))))'>
                                                        @icon('fa-archive')
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $i++; ?>
                                @endforeach

                                @else
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">
                                            No Record Found
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

<div class="modal fade" id="assignlead_modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                  <h4 class="modal-title">Assign Lead</h4>
                  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
            </div>
            <form action="{{ url('leads/assign') }}" method="POST" name="add-assign" autocomplete="off" enctype="multipart/form-data" id="addnoteform">
    @csrf
    <div class="modal-body">
        <div class="form-group row">
            <div class="col-sm-12">
                <input id="mlead_id" name="mlead_id" type="hidden" value="">
                <select name="assignto" class="form-control mm-select " style="width: 100%;">
                    <option value="">Select</option>
                    @foreach(\App\Models\Staff::where('status', 1)->orderBy('first_name')->get() as $ulist)
                    <option value="{{@$ulist->id}}">{{@$ulist->first_name}} {{@$ulist->last_name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" onClick='customValidate("add-assign")'>
            @icon('fa-save') Assign Lead
        </button>
    </div>
</form>
        </div>
    </div>
</div>

<!-- Import Lead Modal -->
<div id="importLeadModal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="importLeadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importLeadModalLabel">
                    @icon('fa-upload') Import Lead from File
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                @if ($errors->has('import_file'))
                    <div class="alert alert-danger">
                        @icon('fa-exclamation-circle')
                        <strong>{{ $errors->first('import_file') }}</strong>
                    </div>
                @endif

                <form method="post" name="importLeadForm" action="{{ URL::to('/clients/import') }}" autocomplete="off" enctype="multipart/form-data">
                    @csrf
                    <div class="alert alert-info">
                        @icon('fa-info-circle')
                        <strong>Instructions:</strong> Upload a JSON file from the lead form. A new lead will be created and the Notes field will be added as an activity note.
                    </div>

                    <div class="form-group">
                        <label for="import_lead_file">Select JSON File <span class="span_req">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="import_lead_file" name="import_file" accept=".json" required>
                            <label class="custom-file-label" for="import_lead_file">Choose file...</label>
                        </div>
                        <small class="form-text text-muted">JSON file from lead form or exported from CRM.</small>
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="skip_duplicates_lead" name="skip_duplicates" value="1" checked>
                            <label class="form-check-label" for="skip_duplicates_lead">
                                Skip if lead with same email or phone already exists
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            @icon('fa-upload') Import Lead
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
@push('scripts')
<script>
function exportLeadList(filteredTotal) {
    var exportLimit = {{ \App\Services\ClientLeadListExportService::EXPORT_LIMIT }};
    var total = parseInt(filteredTotal, 10) || 0;

    if (total === 0) {
        alert('No leads match the current filters.');
        return;
    }

    var exportCount = total;
    var batchCount = Math.ceil(total / exportLimit);
    var message = 'Export ' + exportCount + ' lead(s) as CSV?\n\nThe file will include an Export Summary footer showing total matching and exported counts.';

    if (batchCount > 1) {
        message = 'Export ' + exportCount + ' lead(s) automatically in ' + batchCount + ' CSV files inside one ZIP download.\n\nFiles will be named batch 1 of ' + batchCount + ', batch 2 of ' + batchCount + ', and so on.';
    }

    if (!confirm(message)) {
        return;
    }

    var params = new URLSearchParams(window.location.search);
    params.delete('page');
    params.delete('per_page');
    var baseUrl = '{{ route('leads.export-list') }}';
    window.location.href = baseUrl + (params.toString() ? '?' + params.toString() : '');
}

    jQuery(document).ready(function($){
        // Import Lead Modal - File input label update
        $('#import_lead_file').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName || 'Choose file...');
        });

        // Auto-open the import modal when there are import validation errors (after redirect back)
        @if ($errors->has('import_file'))
            $('#importLeadModal').modal('show');
        @endif

        $('#per_page').on('change', function(){
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', $(this).val());
            currentUrl.searchParams.delete('page');
            window.location.href = currentUrl.toString();
        });

        $('.lead-quick-filter').on('click', function(){
            var filter = $(this).data('filter');
            $('#lead_quick_date_range').val(filter);
            $('#lead_from_date, #lead_to_date').val('');
            $('#leadFilterForm').submit();
        });

        $('#lead_from_date, #lead_to_date').on('change', function(){
            $('#lead_quick_date_range').val('');
        });

        $('#clearLeadFilters').on('click', function(){
            window.location.href = "{{ URL::to('/leads') }}";
        });

        $('.listing-container .filter_btn').on('click', function(){
            $('.listing-container .filter_panel').toggle();
        });
        
        $('.listing-container .assignlead_modal').on('click', function(){
              var val = $(this).attr('mleadid');
              $('#assignlead_modal #mlead_id').val(val);
              $('#assignlead_modal').modal('show');
          });

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
                        $('.listing-container .is_checked_client').show();
                        $('.listing-container .is_checked_clientn').hide();
                    } else {
                        all.prop('checked', false);
                        $('.listing-container .is_checked_client').hide();
                        $('.listing-container .is_checked_clientn').show();
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
                if(checked_length == 2){
                    $('.listing-container .is_checked_client_merge').show();
                } else {
                    $('.listing-container .is_checked_client_merge').hide();
                }
            });
        });

        var clickedOrder = [];
        var clickedIds = [];
        $(document).delegate('.listing-container .your-checkbox', 'click', function(){
            var clicked_id = $(this).data('id');
            var nameStr = $(this).attr('data-name');
            var clientidStr = $(this).attr('data-clientid');
            var finalStr = nameStr+'('+clientidStr+')';
            if ($(this).is(':checked')) {
                clickedOrder.push(finalStr);
                clickedIds.push(clicked_id);
            } else {
                var index = clickedOrder.indexOf(finalStr);
                if (index !== -1) {
                    clickedOrder.splice(index, 1);
                }
                var index1 = clickedIds.indexOf(clicked_id);
                if (index1 !== -1) {
                    clickedIds.splice(index1, 1);
                }
            }
        });

        //merge task
        $(document).delegate('.listing-container .is_checked_client_merge', 'click', function(){
            if ( clickedOrder.length > 0 && clickedOrder.length == 2 )
            {
                var mergeStr = "Are you sure want to merge "+clickedOrder[0]+" record into this "+clickedOrder[1]+" record?";
                if (confirm(mergeStr)) {
                    $.ajax({
                        type:'post',
                        url:"{{URL::to('/')}}/merge_records",
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                        data: {merge_from:clickedIds[0],merge_into:clickedIds[1]},
                        success: function(response){
                            var obj = $.parseJSON(response);
                            location.reload(true);
                        }
                    });
                }
            }
        });

        $('.listing-container .cb-element').change(function () {
            if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
                $('.listing-container #checkbox-all').prop('checked',true);
            }
            else {
                $('.listing-container #checkbox-all').prop('checked',false);
            }

            if ($('.listing-container .cb-element:checked').length > 0){
                $('.listing-container .is_checked_client').show();
                $('.listing-container .is_checked_clientn').hide();
            }else{
                $('.listing-container .is_checked_client').hide();
                $('.listing-container .is_checked_clientn').show();
            }
        });

        $(document).delegate('.listing-container .emailmodal', 'click', function(){
            $('#emailmodal').modal('show');
            var array = [];
            var data = [];
            $('.listing-container .cb-element:checked').each(function(){
                var id = $(this).attr('data-id');
                array.push(id);
                var email = $(this).attr('data-email');
                var name = $(this).attr('data-name');
                var status = 'Client';

                data.push({
                    id: id,
                    text: name,
                    html:  "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

                        "<div  class='ag-flex ag-align-start'>" +
                            "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +
                            "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>"+email+"</small ></div>" +

                        "</div>" +
                        "</div>" +
                        "<div class='ag-flex ag-flex-column ag-align-end'>" +

                            "<span class='ui label yellow mm-result-repository__statistics'>"+ status +

                            "</span>" +
                        "</div>" +
                        "</div>",
                    title: name
                });
            });

            $(".js-data-example-ajax").mmSelect({
                data: data,
                escapeMarkup: function(markup) {
                    return markup;
                },
                templateResult: function(data) {
                    return data.html;
                },
                templateSelection: function(data) {
                    return data.text;
                }
            })

            $('.js-data-example-ajax').val(array);
            $('.js-data-example-ajax').trigger('change');

        });

        $(document).delegate('.listing-container .clientemail', 'click', function(){
            $('#emailmodal').modal('show');
            var array = [];
            var data = [];

            var id = $(this).attr('data-id');
            array.push(id);
            var email = $(this).attr('data-email');
            var name = $(this).attr('data-name');
            var status = 'Client';

            data.push({
                id: id,
                text: name,
                html:  "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

                    "<div  class='ag-flex ag-align-start'>" +
                        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +
                        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>"+email+"</small ></div>" +

                    "</div>" +
                    "</div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +

                        "<span class='ui label yellow mm-result-repository__statistics'>"+ status +

                        "</span>" +
                    "</div>" +
                    "</div>",
                title: name
            });

            $(".js-data-example-ajax").mmSelect({
                data: data,
                escapeMarkup: function(markup) {
                    return markup;
                },
                templateResult: function(data) {
                    return data.html;
                },
                templateSelection: function(data) {
                    return data.text;
                }
            })

            $('.js-data-example-ajax').val(array);
            $('.js-data-example-ajax').trigger('change');

        });

        $(document).delegate('.listing-container .selecttemplate', 'change', function(){
            var v = $(this).val();
            $.ajax({
                url: '{{URL::to('/get-templates')}}',
                type:'GET',
                datatype:'json',
                data:{id:v},
                success: function(response){
                    var res = JSON.parse(response);
                    $('.selectedsubject').val(res.subject);
                    // Clear and set TinyMCE editor content
                    $(".tinymce-editor").each(function() {
                        var editorId = $(this).attr('id');
                        if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                            tinymce.get(editorId).setContent(res.description || '');
                        } else {
                            $(this).val(res.description || '');
                        }
                    });
                }
            });
        });

        $('.js-data-example-ajax').mmSelect({
            multiple: true,
            closeOnSelect: false,
            dropdownParent: $('body'),
            dropdownCssClass: 'mm-compose-email-recipients-dropdown',
            ajax: {
                url: '{{URL::to('/clients/get-recipients')}}',
                dataType: 'json',
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                  // Transforms the top-level key of the response object from 'items' to 'results'
                    return {
                        results: data.items
                    };
                },
                cache: true
            },
            templateResult: formatRepo,
            templateSelection: formatRepoSelection
        });

        $('.js-data-example-ajaxcc').mmSelect({
            multiple: true,
            closeOnSelect: false,
            dropdownParent: $('body'),
            dropdownCssClass: 'mm-compose-email-recipients-dropdown',
            ajax: {
                url: '{{URL::to('/clients/get-recipients')}}',
                dataType: 'json',
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    // Transforms the top-level key of the response object from 'items' to 'results'
                    return {
                        results: data.items
                    };
                },
                cache: true
            },
            templateResult: formatRepo,
            templateSelection: formatRepoSelection
        });

        function formatRepo (repo) {
            if (repo.loading) {
                return repo.text;
            }

            var $container = $(
                "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

                "<div  class='ag-flex ag-align-start'>" +
                    "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
                    "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small ></div>" +

                "</div>" +
                "</div>" +
                "<div class='ag-flex ag-flex-column ag-align-end'>" +

                    "<span class='ui label yellow mm-result-repository__statistics'>" +

                    "</span>" +
                "</div>" +
                "</div>"
            );

            $container.find(".mm-result-repository__title").text(repo.name);
            $container.find(".mm-result-repository__description").text(repo.email);
            $container.find(".mm-result-repository__statistics").append(repo.status);

            return $container;
        }

        function formatRepoSelection (repo) {
            return repo.name || repo.text;
        }
    });
</script>
<script>
    // Archive lead confirmation function - Global scope
    function confirmArchive(event, leadName) {
        // Prevent default button behavior
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Find the form - try multiple methods for compatibility
        var form = null;
        if (event && event.target) {
            form = event.target.closest('form');
            if (!form && typeof jQuery !== 'undefined') {
                form = jQuery(event.target).closest('.archive-lead-form')[0];
            }
        }
        
        // If still no form found, try to find by button
        if (!form && event && event.target) {
            var button = event.target.closest('button') || event.target;
            if (button) {
                form = button.closest('form');
            }
        }
        
        var confirmMessage = 'Are you sure you want to archive the lead "' + (leadName || 'this lead') + '"?\n\nThis will move the lead to the archived list.';
        
        if (confirm(confirmMessage)) {
            if (form) {
                form.submit();
            } else {
                alert('Error: Could not find the form to submit. Please try again.');
                console.error('Archive form not found');
            }
        }
        
        return false;
    }
</script>
@endpush

