@extends('layouts.crm_client_detail')
@section('title', 'Clients Archived')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for archived page */
    /* Action dropdown positioning handled by Popper (custom.js); see listing-container.css for menu styling */
    
    .listing-container .table td .dropdown {
        position: relative;
        display: inline-block;
    }
    
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
    
    .listing-container .dropdown-item {
        display: block;
        width: 100%;
        padding: 10px 20px;
        clear: both;
        font-weight: 500;
        color: #495057;
        text-align: inherit;
        white-space: nowrap;
        background-color: transparent !important;
        border: 0;
        text-decoration: none;
        border-radius: 4px;
        margin: 2px 8px;
        width: calc(100% - 16px);
    }
    
    .listing-container .dropdown-item:hover,
    .listing-container .dropdown-item:focus {
        color: #667eea !important;
        text-decoration: none !important;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%) !important;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
    }
    
    .listing-container .dropdown-item:active {
        color: #667eea !important;
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%) !important;
        text-decoration: none !important;
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
        background-color: transparent !important;
    }
    
    /* Override Bootstrap default white background on hover */
    .listing-container .dropdown-menu .dropdown-item:hover,
    .listing-container .dropdown-menu .dropdown-item:focus {
        color: #667eea !important;
        background: linear-gradient(135deg, #f8f9ff 0%, #e8ecff 100%) !important;
        text-decoration: none !important;
    }
    
    .listing-container .dropdown-menu .dropdown-item:active {
        color: #667eea !important;
        background: linear-gradient(135deg, #e8ecff 0%, #d8e0ff 100%) !important;
        text-decoration: none !important;
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
                    <h4>All Clients Archived</h4>
                    <div class="card-header-actions">
                        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">
                            @icon('fa-filter') Filter
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link " id="clients-tab"  href="{{URL::to('/clients')}}" >Clients</a>
                        </li>
                        <li class="nav-item ">
                            <a class="nav-link active" id="archived-tab"  href="{{URL::to('/archived')}}" >Archived</a>
                        </li>
                        <li class="nav-item is_checked_clientn">
                            <a class="nav-link" id="lead-tab"  href="{{URL::to('/leads')}}" >Leads</a>
                        </li>
                    </ul>

                    @php
                        $archivedFilters = collect([
                            'name' => request('name'),
                            'client_id' => request('client_id'),
                            'lead_id' => request('lead_id'),
                        ]);
                        $activeArchivedFilterCount = $archivedFilters->filter(function ($value) {
                            return $value !== null && $value !== '';
                        })->count();
                    @endphp

                    <div class="filter_panel" @if($activeArchivedFilterCount > 0) style="display: block;" @endif>
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h4>
                                Search By Details
                                @if($activeArchivedFilterCount > 0)
                                    <span class="active-filters-badge">
                                        @icon('fa-filter') {{ $activeArchivedFilterCount }} Active
                                    </span>
                                @endif
                            </h4>
                            @if($activeArchivedFilterCount > 0)
                                <button type="button" class="clear-filter-btn" id="clearArchivedFilters">
                                    @icon('fa-undo') Clear Filters
                                </button>
                            @endif
                        </div>
                        <form action="{{ URL::to('/archived') }}" method="get" id="archivedFilterForm">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="archived_name">Name</label>
                                        <input type="text" name="name" id="archived_name" value="{{ request('name') }}" class="form-control" placeholder="Name or email">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="archived_client_id">Client ID</label>
                                        <input type="text" name="client_id" id="archived_client_id" value="{{ request('client_id') }}" class="form-control" placeholder="Client ID">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="archived_lead_id">Lead ID</label>
                                        <input type="text" name="lead_id" id="archived_lead_id" value="{{ request('lead_id') }}" class="form-control" placeholder="Lead ID">
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12 text-center">
                                    <div class="filter-buttons-container">
                                        <button type="submit" class="btn btn-primary btn-theme-lg mr-3">Apply Filters</button>
                                        <a class="btn btn-info" href="{{ URL::to('/archived') }}">Reset</a>
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
                                    <th>Name</th>
                                    <th>Client ID</th>
                                    <th>Email</th>
                                    <th>Current City</th>
                                    <th>Assignee</th>
                                    <th>Archived By</th>
                                    <th>Archived On</th>
                                    <th>Added On</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody class="tdata">
                                @if(@$totalData !== 0)
                                <?php $i=0; ?>
                                    @foreach (@$lists as $list)
                                    <tr id="id_{{$list->id}}">
                                        <td style="white-space: initial;" class="text-center">
                                            <div class="custom-checkbox custom-control">
                                                <input type="checkbox" data-checkboxes="mygroup" class="custom-control-input" id="checkbox-{{$i}}">
                                                <label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td style="white-space: initial;">
                                            {{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}
                                            @if(!empty($list->type))
                                                <br><small class="text-muted">{{ $list->type === 'lead' ? 'Lead' : ($list->type === 'client' ? 'Client' : ucfirst($list->type)) }}</small>
                                            @endif
                                        </td>
                                        <td style="white-space: initial;">{{ @$list->client_id == "" ? config('constants.empty') : Str::limit(@$list->client_id, '50', '...') }}</td>
                                        <td style="white-space: initial;">{{ @$list->email == "" ? config('constants.empty') : Str::limit(@$list->email, '50', '...') }}</td>
                                        <td style="white-space: initial;">{{@$list->city}}</td>
                                        <?php
                                        $assignee = \App\Models\Staff::where('id',@$list->assignee)->first();
                                        ?>
                                        <td style="white-space: initial;">{{ @$assignee->first_name == "" ? config('constants.empty') : Str::limit(@$assignee->first_name, '50', '...') }}</td>
                                        <td style="white-space: initial;">
                                            @if($list->archivedByStaff)
                                                {{ trim($list->archivedByStaff->first_name . ' ' . $list->archivedByStaff->last_name) ?: config('constants.empty') }}
                                            @else
                                                {{ config('constants.empty') }}
                                            @endif
                                        </td>
                                        <td style="white-space: initial;">
                                            @if($list->archived_on)
                                                {{ $list->archived_on->format('d/m/Y') }}
                                            @else
                                                {{ config('constants.empty') }}
                                            @endif
                                        </td>
                                        <td style="white-space: initial;">{{date('d/m/Y', strtotime($list->created_at))}}</td>
                                        <td style="white-space: initial;">
                                            <div class="dropdown d-inline">
                                                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item has-icon" href="javascript:;" onclick="movetoclientAction({{$list->id}}, 'admins','is_archived')">Move to clients</a>
                                                    <a class="dropdown-item has-icon" href="javascript:;" onclick='unarchiveClientAction({{ $list->id }}, @json(trim(($list->first_name ?? '') . ' ' . ($list->last_name ?? ''))))'>
                                                        @icon('fa-undo') Unarchive
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php $i++; ?>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="10" style="text-align: center; padding: 20px;">
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

        $('#clearArchivedFilters').on('click', function(){
            window.location.href = "{{ URL::to('/archived') }}";
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
                    } else {
                        all.prop('checked', false);
                    }
                } else {
                    if (checked_length >= total) {
                        dad.prop('checked', true);
                    } else {
                        dad.prop('checked', false);
                    }
                }
            });
        });

        $('.listing-container .cb-element').change(function () {
            if ($('.listing-container .cb-element:checked').length == $('.listing-container .cb-element').length){
                $('.listing-container #checkbox-all').prop('checked',true);
            }
            else {
                $('.listing-container #checkbox-all').prop('checked',false);
            }
        });
    });
</script>
<script>
    // Unarchive client function - similar to movetoclientAction
    function unarchiveClientAction(id, clientName) {
        var confirmMessage = 'Are you sure you want to unarchive the client "' + clientName + '"?\n\nThis will move the client back to the active clients list.';
        var conf = confirm(confirmMessage);
        
        if(conf) {
            if(id == '') {
                alert('Please select a valid client ID.');
                return false;
            } else {
                $('.popuploader').show();
                $(".server-error").html(''); //remove server error.
                $(".custom-error-msg").html(''); //remove custom error.
                
                $.ajax({
                    type: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: '{{ route("clients.unarchive", ":id") }}'.replace(':id', id),
                    data: {},
                    dataType: 'json',
                    success: function(resp) {
                        $('.popuploader').hide();
                        var obj = resp;
                        
                        // Handle response - check if it's already parsed or needs parsing
                        if (typeof resp === 'string') {
                            try {
                                obj = $.parseJSON(resp);
                            } catch(e) {
                                console.error('JSON parse error:', e);
                                var html = errorMessage('Invalid server response. Please try again.');
                                $(".custom-error-msg").html(html);
                                $('html, body').animate({scrollTop:0}, 'slow');
                                return;
                            }
                        }
                        
                        if(obj.status == 1) {
                            // Remove the row from table
                            $("#id_"+id).fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if table is empty
                                if($('.tdata tr').length === 0) {
                                    $('.tdata').html('<tr><td colspan="10" style="text-align: center; padding: 20px;">No Record Found</td></tr>');
                                }
                            });
                            
                            // Show success message
                            var html = successMessage(obj.message || 'Client has been unarchived successfully.');
                            $(".custom-error-msg").html(html);
                        } else {
                            // Show error message even if status is 0
                            var html = errorMessage(obj.message || 'Failed to unarchive client.');
                            $(".custom-error-msg").html(html);
                        }
                        
                        $('html, body').animate({scrollTop:0}, 'slow');
                    },
                    error: function(xhr) {
                        $('.popuploader').hide();
                        var errorMessage = 'An error occurred while unarchiving the client.';
                        
                        if(xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if(xhr.responseText) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if(response.message) {
                                    errorMessage = response.message;
                                }
                            } catch(e) {
                                // Use default error message
                            }
                        }
                        
                        var html = errorMessage(errorMessage);
                        $(".custom-error-msg").html(html);
                        $('html, body').animate({scrollTop:0}, 'slow');
                    },
                    beforeSend: function() {
                        $("#loader").show();
                    },
                    complete: function() {
                        $("#loader").hide();
                    }
                });
            }
        }
    }
</script>
@endpush


