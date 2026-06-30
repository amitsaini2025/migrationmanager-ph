@extends('layouts.crm_client_detail')
@section('title', 'Clients Closed Matters')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    .listing-container .table th:first-child,
    .listing-container .table td:first-child {
        min-width: 250px;
        max-width: 300px;
        width: 25%;
    }
    .listing-container .table th:first-child { width: 25%; }
    .listing-container .table td .closed-matter-reopen {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        border: 1px solid #667eea !important;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 500;
        color: white !important;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.2);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    .listing-container .table td .closed-matter-reopen:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%) !important;
        border-color: #5a6fd8 !important;
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        color: white !important;
    }
    .listing-container .card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .listing-container .card-header-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .listing-container .per-page-select {
        border: 1px solid white !important;
        border-radius: 8px !important;
        background: white !important;
        color: #667eea !important;
        font-weight: 600 !important;
        padding: 8px 16px !important;
        min-width: 110px;
    }
    .listing-container .filter_panel {
        background: #f8fafc;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: none;
        border: 1px solid #e2e8f0;
    }
    .listing-container .filter_panel h4 { color: #1e293b; font-size: 18px; font-weight: 700; margin-bottom: 20px; }
    .active-filters-badge {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border-radius: 12px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 700;
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
    }
    .clear-filter-btn:hover { background: #ef4444; color: white; }
    .sortable-header a { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
    .thCls,.tdCls { white-space: initial !important; }
    .badge-closed { background: #6b7280; color: white; }
    .badge-discontinued { background: #dc2626; color: white; }
</style>
@include('crm.clients.partials.enhanced-date-filter-styles')
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')

            <div class="card">
                <div class="custom-error-msg"></div>
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
                                <option value="{{ $option }}" {{ ($perPage ?? 20) == $option ? 'selected' : '' }}>{{ $option }} / page</option>
                            @endforeach
                        </select>
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">@icon('fa-filter') Filter</a>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills" id="matter_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link" id="matters-tab" href="{{ route('clients.clientsmatterslist') }}">Matters</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" id="closed-matters-tab" href="{{ route('clients.closedmatterslist') }}">Closed Matters</a>
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
                        $activeMatterFilters = $matterFilters->filter(fn($v) => $v !== null && $v !== '')->count();
                    @endphp
                    <div class="filter_panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeMatterFilters > 0)
                                    <span class="active-filters-badge">@icon('fa-filter') {{ $activeMatterFilters }} Active</span>
                                @endif
                            </h4>
                            @if($activeMatterFilters > 0)
                                <button type="button" class="clear-filter-btn" id="clearMatterFilters">@icon('fa-undo') Clear Filters</button>
                            @endif
                        </div>
                        <form action="{{ route('clients.closedmatterslist') }}" method="get" id="matterFilterForm">
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
                                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" autocomplete="off" placeholder="Name" id="name">
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
                                                <option value="{{ $member->id }}" {{ request('sel_migration_agent') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
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
                                                <option value="{{ $member->id }}" {{ request('sel_person_responsible') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
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
                                                <option value="{{ $member->id }}" {{ request('sel_person_assisting') == $member->id ? 'selected' : '' }}>{{ $member->first_name }} {{ $member->last_name }}</option>
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
                                    $quickFilters = ['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','last_30_days'=>'Last 30 Days','last_90_days'=>'Last 90 Days','this_year'=>'This Year','last_year'=>'Last Year'];
                                @endphp
                                <div class="quick-filters">
                                    @foreach($quickFilters as $key => $label)
                                        <span class="quick-filter-chip matter-quick-filter {{ request('quick_date_range') === $key ? 'active' : '' }}" data-filter="{{ $key }}">@icon('fa-calendar') {{ $label }}</span>
                                    @endforeach
                                </div>
                                <div class="divider-text">Or Custom Range</div>
                                <div class="date-range-wrapper">
                                    <div class="form-group">
                                        <label for="from_date" class="col-form-label" style="color:#4a5568 !important;">From Date</label>
                                        <input type="date" name="from_date" id="from_date" value="{{ request('from_date') }}" class="form-control">
                                    </div>
                                    <span class="date-range-arrow">→</span>
                                    <div class="form-group">
                                        <label for="to_date" class="col-form-label" style="color:#4a5568 !important;">To Date</label>
                                        <input type="date" name="to_date" id="to_date" value="{{ request('to_date') }}" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-theme-lg mr-3">Search</button>
                                    <a class="btn btn-info" href="{{ route('clients.closedmatterslist') }}">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                    @php
                        $currentSort = request('sort', 'cm.id');
                        $currentDirection = request('direction', 'desc');
                        $nextDirection = fn($col) => ($currentSort === $col && $currentDirection === 'asc') ? 'desc' : 'asc';
                        $buildSortUrl = function($column) use ($nextDirection) {
                            $q = request()->except('page');
                            $q['sort'] = $column;
                            $q['direction'] = $nextDirection($column);
                            return request()->url() . '?' . http_build_query($q);
                        };
                        $sortIcon = function($column) use ($currentSort, $currentDirection) {
                            if ($currentSort !== $column) return \App\Helpers\IconHelper::fromLegacy('fas fa-sort', ['class' => 'text-muted']);
                            return $currentDirection === 'asc' ? \App\Helpers\IconHelper::fromLegacy('fas fa-sort-up') : \App\Helpers\IconHelper::fromLegacy('fas fa-sort-down');
                        };
                        $closedMattersListCanReopen = in_array((int) (Auth::user()->role ?? 0), config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);
                    @endphp
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="thCls sortable-header"><a href="{{ $buildSortUrl('ma.title') }}">Matter {!! $sortIcon('ma.title') !!}</a></th>
                                    <th class="thCls sortable-header"><a href="{{ $buildSortUrl('ad.client_id') }}">Client ID {!! $sortIcon('ad.client_id') !!}</a></th>
                                    <th class="thCls sortable-header"><a href="{{ $buildSortUrl('ad.first_name') }}">Client Name {!! $sortIcon('ad.first_name') !!}</a></th>
                                    <th class="thCls">Migration Agent</th>
                                    <th class="thCls">Person Responsible</th>
                                    <th class="thCls">Person Assisting</th>
                                    <th class="thCls">Status</th>
                                    <th class="thCls sortable-header"><a href="{{ $buildSortUrl('cm.created_at') }}">Created At {!! $sortIcon('cm.created_at') !!}</a></th>
                                    <th class="thCls">Office</th>
                                    @if($closedMattersListCanReopen)
                                    <th class="thCls">Reopen</th>
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
                                        $statusLabel = ($list->matter_status ?? 1) == 0 ? 'Discontinued' : ($list->workflow_stage_name ?? 'Closed');
                                        $statusClass = ($list->matter_status ?? 1) == 0 ? 'badge-discontinued' : 'badge-closed';
                                        $isDiscontinued = ($list->matter_status ?? 1) == 0;
                                        ?>
                                        <tr id="id_{{@$list->id}}">
                                            <td class="tdCls"><a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)).'/'.$list->client_unique_matter_no )}}">{{ @$list->title == "" ? config('constants.empty') : Str::limit(@$list->title, '50', '...') }} ({{ @$list->client_unique_matter_no == "" ? config('constants.empty') : Str::limit(@$list->client_unique_matter_no, '50', '...') }})</a></td>
                                            <td class="tdCls">{{ @$list->client_unique_id == "" ? config('constants.empty') : Str::limit(@$list->client_unique_id, '50', '...') }}</td>
                                            <td class="tdCls"><a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)) )}}">{{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}</a></td>
                                            <td class="tdCls">{{ @$mig_agent_info->first_name ?? '' }} {{ @$mig_agent_info->last_name ?? '' }}</td>
                                            <td class="tdCls">{{ @$person_responsible->first_name ?? '' }} {{ @$person_responsible->last_name ?? '' }}</td>
                                            <td class="tdCls">{{ @$person_assisting->first_name ?? '' }} {{ @$person_assisting->last_name ?? '' }}</td>
                                            <td class="tdCls"><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                            <td class="tdCls">{{ date('d/m/Y', strtotime($list->created_at)) }}</td>
                                            <td class="tdCls">
                                                @if($matter_office)
                                                    <span class="badge badge-info" style="font-size: 12px;">@icon('fa-building') {{ $matter_office->office_name }}</span>
                                                @else
                                                    <span class="badge badge-warning" style="font-size: 11px;">@icon('fa-exclamation-triangle') Not Assigned</span>
                                                @endif
                                            </td>
                                            @if($closedMattersListCanReopen)
                                            <td class="tdCls">
                                                @if($isDiscontinued)
                                                <button class="btn btn-primary btn-sm closed-matter-reopen" type="button" data-matter-id="{{ $list->id }}">@icon('fa-redo') Reopen</button>
                                                @else
                                                <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            @endif
                                        </tr>
                                        <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="{{ $closedMattersListCanReopen ? '10' : '9' }}" style="text-align: center; padding: 20px;">No Record Found</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
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
    $('#from_date, #to_date').on('change', function(){ $('#matter_quick_date_range').val(''); });
    $('#clearMatterFilters').on('click', function(){
        window.location.href = "{{ route('clients.closedmatterslist') }}";
    });
    $('.listing-container .filter_btn').on('click', function(){
        $('.listing-container .filter_panel').toggle();
    });

    $(document).on('click', '.closed-matter-reopen', function(e){
        e.preventDefault();
        var matterId = $(this).data('matter-id');
        if (!matterId) return;
        if (!confirm('Reopen this matter? It will be moved back to active matters.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Reopening...');
        $.ajax({
            url: '{{ route("clients.matter.reopen") }}',
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            data: JSON.stringify({ matter_id: matterId, source: 'matter_list' }),
            success: function(resp){
                if (resp.status && resp.redirect_url) {
                    window.location.href = resp.redirect_url;
                } else {
                    alert(resp.message || 'Failed to reopen matter.');
                    $btn.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-redo') : '<i class="fa fa-redo"></i>') + ' Reopen');
                }
            },
            error: function(){
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-redo') : '<i class="fa fa-redo"></i>') + ' Reopen');
            }
        });
    });
});
</script>
@endpush
