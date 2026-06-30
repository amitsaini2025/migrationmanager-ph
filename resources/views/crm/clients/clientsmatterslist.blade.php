@extends('layouts.crm_client_detail')
@section('title', 'Clients Matters')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for clientsmatterslist */
    .listing-container .table th:first-child,
    .listing-container .table td:first-child {
        min-width: 250px;
        max-width: 300px;
        width: 25%;
    }
    
    .listing-container .table th:first-child {
        width: 25%;
    }
    
    /* Professional Action Button Design */
    .listing-container .table td .dropdown-toggle {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: 1px solid #667eea !important;
        min-width: 80px;
        max-width: 90px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
        color: white !important;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    
    .listing-container .table td .dropdown-toggle:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
        border-color: #5a6fd8 !important;
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
    }
    
    .listing-container .table td .dropdown-toggle:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.25);
    }
    
    .listing-container .table td .dropdown-toggle::after {
        content: '';
        display: inline-block;
        margin-left: 4px;
        vertical-align: middle;
        border-top: 4px solid;
        border-right: 4px solid transparent;
        border-bottom: 0;
        border-left: 4px solid transparent;
    }
    
    .listing-container .table td .dropdown-toggle.show::after {
        transform: rotate(180deg);
    }
    
    /* Enhanced Dropdown Menu — visual/position split: listing-container.css + custom.js Popper */
    
    /* Dropdown Items Styling */
    .listing-container .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 20px;
        clear: both;
        font-weight: 500;
        color: #495057;
        text-align: inherit;
        white-space: nowrap;
        background-color: transparent;
        border: 0;
        text-decoration: none;
        border-radius: 4px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }
    
    .listing-container .dropdown-item:hover {
        color: #667eea;
        text-decoration: none;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }
    
    .listing-container .dropdown-item:active {
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%);
    }
    
    .listing-container .dropdown-item.has-icon {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .listing-container .dropdown-item.has-icon i {
        width: 16px;
        text-align: center;
    }
    
    /* Ensure all dropdown items are visible */
    .listing-container .dropdown-menu .dropdown-item {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        height: auto !important;
        min-height: 32px !important;
        line-height: 1.5 !important;
    }
    
    /* Remove any potential overflow restrictions */
    .listing-container .table td {
        overflow: visible !important;
    }
    
    .listing-container .table td .dropdown {
        overflow: visible !important;
        position: relative;
        display: inline-block;
    }
    
    /* Ensure dropdown container doesn't clip content */
    .listing-container .dropdown {
        overflow: visible !important;
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
    .thCls,.tdCls {
        white-space: initial !important;
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
                    <h4>All Clients Matters</h4>
                    <div class="card-header-actions">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <a href="{{ route('clients.insights', ['section' => 'matters']) }}" class="btn btn-theme btn-theme-sm" title="Matter Insights">
                            @icon('fa-chart-line') Insights
                        </a>
                        @endif
                        <select name="per_page" id="per_page" class="form-control per-page-select">
                            @foreach([10, 20, 50, 100, 200] as $option)
                                <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>
                                    {{ $option }} / page
                                </option>
                            @endforeach
                        </select>
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">@icon('fa-filter') Filter</a>
                    </div>
                </div>
                
                <div class="card-body">
                    <ul class="nav nav-pills" id="matter_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="matters-tab" href="{{ route('clients.clientsmatterslist') }}">Matters</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="closed-matters-tab" href="{{ route('clients.closedmatterslist') }}">Closed Matters</a>
                        </li>
                    </ul>

                    @php
                        $matterFilters = collect([
                            'sel_matter_id' => request('sel_matter_id'),
                            'client_id' => request('client_id'),
                            'name' => request('name'),
                            'sel_migration_agent' => request('sel_migration_agent'),
                            'sel_person_responsible' => request('sel_person_responsible'),
                            'sel_person_assisting' => request('sel_person_assisting'),
                            'quick_date_range' => request('quick_date_range'),
                            'from_date' => request('from_date'),
                            'to_date' => request('to_date'),
                            'date_filter_field' => request('date_filter_field') !== 'created_at' ? request('date_filter_field') : null,
                        ]);
                        $activeMatterFilters = $matterFilters->filter(function ($value) {
                            return $value !== null && $value !== '';
                        })->count();
                    @endphp
                    <div class="filter_panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeMatterFilters > 0)
                                    <span class="active-filters-badge">
                                        @icon('fa-filter') {{ $activeMatterFilters }} Active
                                    </span>
                                @endif
                            </h4>
                            @if($activeMatterFilters > 0)
                                <button type="button" class="clear-filter-btn" id="clearMatterFilters">
                                    @icon('fa-undo') Clear Filters
                                </button>
                            @endif
                        </div>
                        <form action="{{URL::to('/clientsmatterslist')}}" method="get" id="matterFilterForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_matter_id" class="col-form-label" style="color:#4a5568 !important;">Matter</label>
                                        <select class="form-control" name="sel_matter_id" id="sel_matter_id">
                                            <option value="">Select Matter</option>
                                            @foreach(\App\Models\Matter::orderBy('title', 'asc')->get() as $matter)
                                                <option value="{{ $matter->id }}" {{ request('sel_matter_id') == $matter->id ? 'selected' : '' }}>{{ $matter->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="client_id" class="col-form-label" style="color:#4a5568 !important;">Client ID</label>
                                        <input type="text" name="client_id" value="{{ request('client_id') }}" class="form-control" autocomplete="off" placeholder="Client ID" id="client_id">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name" class="col-form-label" style="color:#4a5568 !important;">Client Name</label>
                                        <input type="text" name="name" value="{{ request('name') }}" class="form-control agent_company_name" autocomplete="off" placeholder="Name" id="name">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_migration_agent" class="col-form-label" style="color:#4a5568 !important;">Migration Agent</label>
                                        <select class="form-control" name="sel_migration_agent" id="sel_migration_agent">
                                            <option value="">All Agents</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_migration_agent') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_person_responsible" class="col-form-label" style="color:#4a5568 !important;">Person Responsible</label>
                                        <select class="form-control" name="sel_person_responsible" id="sel_person_responsible">
                                            <option value="">All</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_person_responsible') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sel_person_assisting" class="col-form-label" style="color:#4a5568 !important;">Person Assisting</label>
                                        <select class="form-control" name="sel_person_assisting" id="sel_person_assisting">
                                            <option value="">All</option>
                                            @foreach(($teamMembers ?? collect()) as $member)
                                                <option value="{{ $member->id }}" {{ request('sel_person_assisting') == $member->id ? 'selected' : '' }}>
                                                    {{ $member->first_name }} {{ $member->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="date-filter-section mt-3">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="date_filter_field" class="col-form-label" style="color:#4a5568 !important;">Date Field</label>
                                            <select name="date_filter_field" id="date_filter_field" class="form-control">
                                                <option value="created_at" {{ request('date_filter_field', 'created_at') === 'created_at' ? 'selected' : '' }}>Created Date</option>
                                                <option value="updated_at" {{ request('date_filter_field') === 'updated_at' ? 'selected' : '' }}>Last Updated</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="quick_date_range" id="matter_quick_date_range" value="{{ request('quick_date_range') }}">
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
                                        <span class="quick-filter-chip matter-quick-filter {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}">
                                            @icon('fa-calendar') {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date" class="col-form-label" style="color:#4a5568 !important;">From Date</label>
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" class="form-control" placeholder="Start date">
                                    </div>
                                    <span class="date-range-arrow">→</span>
                                    <div class="form-group">
                                        <label for="to_date" class="col-form-label" style="color:#4a5568 !important;">To Date</label>
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" class="form-control" placeholder="End date">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">Search</button>
                                        <a class="btn btn-info" href="{{URL::to('/clientsmatterslist')}}">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    @php
                        $currentSort = request('sort', 'cm.id');
                        $currentDirection = request('direction', 'desc');
                        $nextDirection = function ($column) use ($currentSort, $currentDirection) {
                            return ($currentSort === $column && $currentDirection === 'asc') ? 'desc' : 'asc';
                        };
                        $buildSortUrl = function ($column) use ($nextDirection) {
                            $query = request()->except('page');
                            $query['sort'] = $column;
                            $query['direction'] = $nextDirection($column);
                            return request()->url() . '?' . http_build_query($query);
                        };
                        $sortIcon = function ($column) use ($currentSort, $currentDirection) {
                            if ($currentSort !== $column) {
                                return \App\Helpers\IconHelper::fromLegacy('fas fa-sort', ['class' => 'text-muted']);
                            }
                            return $currentDirection === 'asc'
                                ? \App\Helpers\IconHelper::fromLegacy('fas fa-sort-up')
                                : \App\Helpers\IconHelper::fromLegacy('fas fa-sort-down');
                        };
                    @endphp
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="thCls sortable-header">
                                        <a href="{{ $buildSortUrl('ma.title') }}">
                                            Matter {!! $sortIcon('ma.title') !!}
                                        </a>
                                    </th>
                                    <th class="thCls sortable-header">
                                        <a href="{{ $buildSortUrl('ad.client_id') }}">
                                            Client ID {!! $sortIcon('ad.client_id') !!}
                                        </a>
                                    </th>
                                    <th class="thCls sortable-header">
                                        <a href="{{ $buildSortUrl('ad.first_name') }}">
                                            Client Name {!! $sortIcon('ad.first_name') !!}
                                        </a>
                                    </th>
                                    <th class="thCls sortable-header">
                                        <a href="{{ $buildSortUrl('ad.dob') }}">
                                            DOB {!! $sortIcon('ad.dob') !!}
                                        </a>
                                    </th>
                                    <th class="thCls">Migration Agent</th>
                                    <th class="thCls">Person Responsible</th>
                                    <th class="thCls">Person Assisting</th>
                                    <th class="thCls sortable-header">
                                        <a href="{{ $buildSortUrl('cm.created_at') }}">
                                            Created At {!! $sortIcon('cm.created_at') !!}
                                        </a>
                                    </th>
                                    <th class="thCls">Office</th>
                                    @if(Auth::user()->role == 1)
                                    <th class="thCls">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                        <?php
                                        $mig_agent_info = \App\Models\Staff::select('first_name','last_name')->where('id', $list->sel_migration_agent)->first();
                                        $person_responsible = \App\Models\Staff::select('first_name','last_name')->where('id', $list->sel_person_responsible)->first();
                                        $person_assisting = \App\Models\Staff::select('first_name','last_name')->where('id', $list->sel_person_assisting)->first();
                                        $matter_office = $list->office_id ? \App\Models\Branch::find($list->office_id) : null;
                                        ?>
                                        <tr id="id_{{@$list->id}}">
                                            <td class="tdCls"><a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)).'/'.$list->client_unique_matter_no )}}">{{ @$list->title == "" ? config('constants.empty') : Str::limit(@$list->title, '50', '...') }} ({{ @$list->client_unique_matter_no == "" ? config('constants.empty') : Str::limit(@$list->client_unique_matter_no, '50', '...') }}) </a></td>
                                            <td class="tdCls">{{ @$list->client_unique_id == "" ? config('constants.empty') : Str::limit(@$list->client_unique_id, '50', '...') }}</td>
                                            <td class="tdCls"><a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)) )}}">{{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}</a></td>
                                            <td class="tdCls">{{ @$list->dob == "" ? config('constants.empty') : (strtotime(@$list->dob) ? date('d/m/Y', strtotime(@$list->dob)) : Str::limit(@$list->dob, '50', '...')) }}</td>
                                            <td class="tdCls">{{ @$mig_agent_info->first_name == "" ? config('constants.empty') : Str::limit(@$mig_agent_info->first_name, '50', '...') }} {{ @$mig_agent_info->last_name == "" ? config('constants.empty') : Str::limit(@$mig_agent_info->last_name, '50', '...') }}</td>
                                            <td class="tdCls">{{ @$person_responsible->first_name == "" ? config('constants.empty') : Str::limit(@$person_responsible->first_name, '50', '...') }} {{ @$person_responsible->last_name == "" ? config('constants.empty') : Str::limit(@$person_responsible->last_name, '50', '...') }}</td>
                                            <td class="tdCls">{{ @$person_assisting->first_name == "" ? config('constants.empty') : Str::limit(@$person_assisting->first_name, '50', '...') }} {{ @$person_assisting->last_name == "" ? config('constants.empty') : Str::limit(@$person_assisting->last_name, '50', '...') }}</td>
                                            <td class="tdCls">{{date('d/m/Y', strtotime($list->created_at))}}</td>
                                            <td class="tdCls">
                                                @if($matter_office)
                                                    <span class="badge badge-info" style="font-size: 12px;">
                                                        @icon('fa-building') {{ $matter_office->office_name }}
                                                    </span>
                                                    <br>
                                                    <a href="javascript:;" class="btn btn-sm btn-outline-primary mt-1 edit-office-btn" 
                                                       data-matter-id="{{ $list->id }}" 
                                                       data-matter-no="{{ $list->client_unique_matter_no }}"
                                                       data-matter-title="{{ $list->title }}"
                                                       data-office-id="{{ $list->office_id }}"
                                                       title="Change Office">
                                                        @icon('fa-edit') Change
                                                    </a>
                                                @else
                                                    <span class="badge badge-warning" style="font-size: 11px;">
                                                        @icon('fa-exclamation-triangle') Not Assigned
                                                    </span>
                                                    <br>
                                                    <a href="javascript:;" class="btn btn-sm btn-success mt-1 assign-office-btn" 
                                                       data-matter-id="{{ $list->id }}" 
                                                       data-matter-no="{{ $list->client_unique_matter_no }}"
                                                       data-matter-title="{{ $list->title }}"
                                                       title="Assign Office">
                                                        @icon('fa-plus') Assign
                                                    </a>
                                                @endif
                                            </td>
                                            @if(Auth::user()->role == 1)
                                            <td class="tdCls">
                                                <div class="dropdown d-inline">
                                                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item has-icon" href="javascript:;" onclick="deleteAction({{$list->id}}, 'client_matters')">@icon('fa-trash') Delete Matter</a>
                                                    </div>
                                                </div>
                                            </td>
                                            @endif
                                        </tr>
                                        <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="{{ Auth::user()->role == 1 ? '10' : '9' }}" style="text-align: center; padding: 20px;">
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

{{-- Include Office Assignment Modal --}}
@include('crm.clients.modals.edit-matter-office')

@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
    $('#per_page').on('change', function(){
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', $(this).val());
        currentUrl.searchParams.delete('page');
        window.location.href = currentUrl.toString();
    });

    $('.matter-quick-filter').on('click', function(){
        var filter = $(this).data('filter');
        $('#matter_quick_date_range').val(filter);
        $('#from_date, #to_date').val('');
        $('#matterFilterForm').submit();
    });

    $('#from_date, #to_date').on('change', function(){
        $('#matter_quick_date_range').val('');
    });

    $('#clearMatterFilters').on('click', function(){
        window.location.href = "{{ URL::to('/clientsmatterslist') }}";
    });

    $('.listing-container .filter_btn').on('click', function(){
        $('.listing-container .filter_panel').toggle();
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
                    $('.listing-container .is_checked_clientn').hide();
                } else {
                    all.prop('checked', false);
                    $('.listing-container .is_checked_clientn').show();
                }
            } else {
                if (checked_length >= total) {
                    dad.prop('checked', true);
                    $('.listing-container .is_checked_clientn').hide();
                } else {
                    dad.prop('checked', false);
                    $('.listing-container .is_checked_clientn').show();
                }
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

    $('.listing-container .cb-element').change(function () {
        if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
            $('.listing-container #checkbox-all').prop('checked',true);
        } else {
            $('.listing-container #checkbox-all').prop('checked',false);
        }
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
                return { results: data.items };
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

    // ============================================
    // MATTER OFFICE ASSIGNMENT HANDLERS
    // ============================================
    
    // Handle Assign Office button click
    $(document).on('click', '.assign-office-btn, .edit-office-btn', function(e) {
        e.preventDefault();
        
        var matterId = $(this).data('matter-id');
        var matterNo = $(this).data('matter-no');
        var matterTitle = $(this).data('matter-title');
        var officeId = $(this).data('office-id') || '';
        
        // Populate modal
        $('#edit_matter_id').val(matterId);
        $('#modal_matter_number').text(matterNo);
        $('#modal_matter_title').text(matterTitle || 'N/A');
        $('#edit_office_id').val(officeId).trigger('change');
        
        // Show modal
        $('#editMatterOfficeModal').modal('show');
    });
    
    // Handle form submission
    $('#editMatterOfficeForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        // Disable button and show loading
        submitBtn.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Saving...');
        
        $.ajax({
            url: '{{ route("matters.update-office") }}',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    // Show success message
                    iziToast.success({
                        title: 'Success!',
                        message: response.message,
                        position: 'topRight'
                    });
                    // Reload page to show updated office after a short delay
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    iziToast.error({
                        title: 'Error!',
                        message: response.message || 'Failed to update office',
                        position: 'topRight'
                    });
                    submitBtn.prop('disabled', false).html(originalText);
                }
            },
            error: function(xhr) {
                var errorMsg = 'An error occurred. Please try again.';
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                iziToast.error({
                    title: 'Error!',
                    message: errorMsg,
                    position: 'topRight'
                });
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Reset form when modal is closed
    $('#editMatterOfficeModal').on('hidden.bs.modal', function() {
        $('#editMatterOfficeForm')[0].reset();
        $('#edit_office_id').val('').trigger('change');
    });
});
</script>
@endpush


