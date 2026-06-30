@extends('layouts.crm_client_detail')
@section('title', 'Clients Email List')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for clientsemaillist */
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
        transition: all 0.3s ease;
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
        transform: translateY(-1px);
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
        transition: transform 0.2s ease;
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
        transition: all 0.2s ease;
        border-radius: 4px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }
    
    .listing-container .dropdown-item:hover {
        color: #667eea;
        text-decoration: none;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%);
        transform: translateX(2px);
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }
    
    .listing-container .dropdown-item:active {
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%);
        transform: translateX(1px);
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
    .thCls,.tdCls {
        white-space: initial !important;
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
                    <h4>All Clients Email List</h4>
                    <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">@icon('fa-filter') Filter</a>
                </div>
                
                <div class="card-body">
                    <div class="filter_panel">
                        <h4>Search By Details</h4>
                        <form action="{{URL::to('/adminconsole/clientsemaillist')}}" method="get">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="client_id" class="col-form-label" style="color:#4a5568 !important;">Client ID</label>
                                        <input type="text" name="client_id" value="{{ Request::get('client_id') }}" class="form-control" data-valid="" autocomplete="off" placeholder="Client ID" id="client_id">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="name" class="col-form-label" style="color:#4a5568 !important;">Client Name</label>
                                        <input type="text" name="name" value="{{ Request::get('name') }}" class="form-control" data-valid="" autocomplete="off" placeholder="Name" id="name">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="email" class="col-form-label" style="color:#4a5568 !important;">Email</label>
                                        <input type="text" name="email" value="{{ Request::get('email') }}" class="form-control" data-valid="" autocomplete="off" placeholder="Email" id="email">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">Search</button>
                                        <a class="btn btn-info" href="{{URL::to('/adminconsole/clientsemaillist')}}">Reset</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="thCls">Client ID</th>
                                    <th class="thCls">Client Name</th>
                                    <th class="thCls">Email</th>
                                    <th class="thCls">Phone</th>
                                    <th class="thCls">Type</th>
                                    <th class="thCls">Created At</th>
                                    @if(Auth::user()->role == 1)
                                    <th class="thCls">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                        <tr id="id_{{@$list->id}}">
                                            <td class="tdCls">{{ @$list->client_id == "" ? config('constants.empty') : Str::limit(@$list->client_id, '50', '...') }}</td>
                                            <td class="tdCls"><a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->id)) )}}">{{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}</a></td>
                                            <td class="tdCls">
                                                <a href="mailto:{{ @$list->email }}">{{ @$list->email == "" ? config('constants.empty') : Str::limit(@$list->email, '50', '...') }}</a>
                                            </td>
                                            <td class="tdCls">{{ @$list->phone == "" ? config('constants.empty') : Str::limit(@$list->phone, '50', '...') }}</td>
                                            <td class="tdCls">{{ @$list->type == "" ? config('constants.empty') : Str::limit(@$list->type, '50', '...') }}</td>
                                            <td class="tdCls">{{date('d/m/Y', strtotime($list->created_at))}}</td>
                                            @if(Auth::user()->role == 1)
                                            <td class="tdCls">
                                                <div class="dropdown d-inline">
                                                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                                                    <div class="dropdown-menu">
                                                        <a class="dropdown-item has-icon" href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->id)) )}}">@icon('fa-eye') View Details</a>
                                                        <a class="dropdown-item has-icon" href="mailto:{{ @$list->email }}">@icon('fa-envelope') Send Email</a>
                                                    </div>
                                                </div>
                                            </td>
                                            @endif
                                        </tr>
                                        <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="{{ Auth::user()->role == 1 ? '7' : '6' }}" style="text-align: center; padding: 20px;">
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
@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
    $('.listing-container .filter_btn').on('click', function(){
        $('.listing-container .filter_panel').toggle();
    });
});
</script>
@endpush
