@extends('layouts.crm_client_detail')
@section('title', 'Completed Action')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for action_completed page */
    .listing-container .filter-buttons { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 10px; 
        margin-bottom: 20px; 
        max-width: 100%;
    }
    
    .listing-container .filter-buttons a, 
    .listing-container .filter-buttons button {
        background-color: #0d6efd;
        color: #FFF;
        padding: 8px 15px;
        font-size: 0.9em;
        font-weight: 500;
        text-decoration: none;
        border: none;
        transition: background-color 0.2s ease;
        white-space: nowrap;
    }
    
    .listing-container .filter-buttons a.active, 
    .listing-container .filter-buttons button.active {
        background-color: #0d6efd;
        color: white;
    }
    
    .listing-container .filter-buttons a:hover, 
    .listing-container .filter-buttons button:hover {
        background-color: #d3d7db;
    }
    
    .listing-container .filter-buttons .countAction {
        background-color: #ffffff;
        color: #0d6efd;
        padding: 2px 8px;
        border-radius: 50%;
        font-size: 0.8em;
        margin-left: 5px;
    }
    
    .listing-container .action-buttons { 
        display: flex; 
        gap: 5px; 
        flex-wrap: wrap;
    }
    
    .listing-container .action-buttons .btn {
        padding: 5px 10px;
        font-size: 0.9em;
        border-radius: 4px;
        white-space: nowrap;
    }
    
    .listing-container .btn-primary { 
        background-color: #0d6efd; 
        color: white; 
    }
    
    .listing-container .btn-danger { 
        background-color: #dc3545; 
        color: white; 
    }
    
    .listing-container .btn-primary:hover { 
        background-color: #0b5ed7; 
    }
    
    .listing-container .btn-danger:hover { 
        background-color: #c82333; 
    }
    
    .listing-container .sort_col a { 
        color: #0d6efd !important; 
        text-decoration: none; 
    }
    
    .listing-container .sort_col a:hover { 
        text-decoration: underline; 
    }
    
    /* Column width specifications */
    .listing-container .table th:nth-child(1), 
    .listing-container .table td:nth-child(1) { /* Sno */
        width: 5%;
        min-width: 50px;
        max-width: 60px;
    }
    
    .listing-container .table th:nth-child(2), 
    .listing-container .table td:nth-child(2) { /* Done */
        width: 8%;
        min-width: 60px;
        max-width: 80px;
        text-align: center;
    }
    
    .listing-container .table th:nth-child(3), 
    .listing-container .table td:nth-child(3) { /* Assigner Name */
        width: 15%;
        min-width: 120px;
        max-width: 150px;
    }
    
    .listing-container .table th:nth-child(4), 
    .listing-container .table td:nth-child(4) { /* Client Reference */
        width: 15%;
        min-width: 120px;
        max-width: 150px;
    }
    
    .listing-container .table th:nth-child(5), 
    .listing-container .table td:nth-child(5) { /* Assign Date */
        width: 12%;
        min-width: 100px;
        max-width: 120px;
    }
    
    .listing-container .table th:nth-child(6), 
    .listing-container .table td:nth-child(6) { /* Type */
        width: 10%;
        min-width: 80px;
        max-width: 100px;
    }
    
    .listing-container .table th:nth-child(7), 
    .listing-container .table td:nth-child(7) { /* Note column */
        width: 25%;
        min-width: 200px;
        max-width: 300px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        line-height: 1.4;
    }
    
    .listing-container .table th:nth-child(8), 
    .listing-container .table td:nth-child(8) { /* Action column */
        width: 10%;
        min-width: 100px;
        max-width: 120px;
        white-space: nowrap;
        text-align: center;
    }
    
    /* Ensure popover content doesn't cause overflow */
    .listing-container .popover {
        max-width: 400px;
        word-wrap: break-word;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .listing-container .filter-buttons {
            flex-direction: column;
        }
        
        .listing-container .filter-buttons a, 
        .listing-container .filter-buttons button {
            width: 100%;
            text-align: center;
        }
        
        .listing-container .card-header .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        
        .listing-container .nav-pills {
            margin-top: 10px;
        }
        
        .listing-container .table th:nth-child(7), 
        .listing-container .table td:nth-child(7) { /* Note column on mobile */
            width: 20%;
            min-width: 150px;
            max-width: 200px;
        }
        
        .listing-container .action-buttons {
            flex-direction: column;
            gap: 3px;
        }
        
        .listing-container .action-buttons .btn {
            padding: 3px 6px;
            font-size: 0.8em;
        }
    }
    
    @media (max-width: 576px) {
        .listing-container .table th:nth-child(7), 
        .listing-container .table td:nth-child(7) { /* Note column */
            width: 15%;
            min-width: 120px;
            max-width: 150px;
        }
    }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 80px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h4>Completed Action</h4>
                        <ul class="nav nav-pills" id="client_tabs" role="tablist">
                            <li class="nav-item is_checked_clientn11">
                                <a class="nav-link active" id="archived-tab" href="{{URL::to('/action')}}">Incomplete</a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="quotationContent">
                        <form action="{{ route('assignee.action_completed') }}" method="get">
                            <div class="row">
                                <div class="col-md-12 filter-buttons">
                                    <a href="{{URL::to('/action_completed?group_type=All')}}" id="All" class="group_type {{ $task_group == 'All' ? 'active' : '' }}">All <span class="countAction">{{ $taskGroupCounts['All'] }}</span></a>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Call')}}" id="Call" class="group_type {{ $task_group == 'Call' ? 'active' : '' }}">@icon('fa-phone', ['aria-hidden' => 'true']) Call <span class="countAction">{{ $taskGroupCounts['Call'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Checklist')}}" id="Checklist" class="group_type {{ $task_group == 'Checklist' ? 'active' : '' }}">@icon('fa-bars', ['aria-hidden' => 'true']) Checklist <span class="countAction">{{ $taskGroupCounts['Checklist'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Review')}}" id="Review" class="group_type {{ $task_group == 'Review' ? 'active' : '' }}">@icon('fa-check', ['aria-hidden' => 'true']) Review <span class="countAction">{{ $taskGroupCounts['Review'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Query')}}" id="Query" class="group_type {{ $task_group == 'Query' ? 'active' : '' }}">@icon('fa-question', ['aria-hidden' => 'true']) Query <span class="countAction">{{ $taskGroupCounts['Query'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Urgent')}}" id="Urgent" class="group_type {{ $task_group == 'Urgent' ? 'active' : '' }}">@icon('fa-flag', ['aria-hidden' => 'true']) Urgent <span class="countAction">{{ $taskGroupCounts['Urgent'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Personal Action')}}" id="Personal Action" class="group_type {{ $task_group == 'Personal Action' ? 'active' : '' }}">@icon('fa-tasks', ['aria-hidden' => 'true']) Personal Action <span class="countAction">{{ $taskGroupCounts['Personal Action'] }}</span></a>
                                    </button>
                                    <button type="button">
                                        <a href="{{URL::to('/action_completed?group_type=Client Portal')}}" id="Client Portal" class="group_type {{ $task_group == 'Client Portal' ? 'active' : '' }}">@icon('fa-globe', ['aria-hidden' => 'true']) Client Portal <span class="countAction">{{ $taskGroupCounts['Client Portal'] }}</span></a>
                                        <a href="{{ URL::to('/action_completed?group_type=' . urlencode('EOI/ROI Amendment')) }}" id="EOI/ROI Amendment" class="group_type {{ $task_group == 'EOI/ROI Amendment' ? 'active' : '' }}">@icon('fa-edit', ['aria-hidden' => 'true']) EOI/ROI Amendment <span class="countAction">{{ $taskGroupCounts['EOI/ROI Amendment'] ?? 0 }}</span></a>
                                        <a href="{{URL::to('/action_completed?group_type=Follow Up')}}" id="Follow Up" class="group_type {{ $task_group == 'Follow Up' ? 'active' : '' }}">@icon('fa-calendar-check-o', ['aria-hidden' => 'true']) Follow up <span class="countAction">{{ $taskGroupCounts['Follow Up'] ?? 0 }}</span></a>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="tab-pane fade show active" id="active_quotation" role="tabpanel" aria-labelledby="active_quotation-tab">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th style="text-align: center;">Sno</th>
                                            <th style="text-align: center;">Done</th>
                                            <th>Assigner Name</th>
                                            <th>Client Reference</th>
                                            <th class="sort_col">@sortablelink('action_date','Assign Date')</th>
                                            <th class="sort_col">@sortablelink('task_group','Type')</th>
                                            <th>Note</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(count($assignees_completed) > 0)
                                            @foreach($assignees_completed as $list)
                                                <?php
                                                    $staff = $list->noteStaff;
                                                    $full_name = $staff ? ($staff->first_name ?? 'N/A') . ' ' . ($staff->last_name ?? 'N/A') : 'N/A';
                                                    $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
                                                    if ($list->noteClient && $client_name === '') {
                                                        $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
                                                    }
                                                ?>
                                                <tr>
                                                    <td style="text-align: center;">{{ ++$i }}</td>
                                                    <td style="text-align: center;">
                                                        <input type="radio" class="not_complete_task" data-bs-toggle="tooltip" title="Mark Incomplete!" data-id="{{ $list->id }}" data-unique_group_id="{{ $list->unique_group_id }}">
                                                    </td>
                                                    <td>{{ $full_name }}</td>
                                                    <td>
                                                        {{ $client_name }}<br>
                                                        @if($list->noteClient)
                                                            <a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)))}}" target="_blank">{{ $list->noteClient->client_id }}</a>
                                                        @endif
                                                    </td>
                                                    <td>{{ date('d/m/Y', strtotime($list->action_date)) ?? 'N/P' }}</td>
                                                    <td>{{ $list->task_group ?? 'N/P' }}</td>
                                                    <td>
                                                        @if(isset($list->description) && $list->description != "")
                                                            @if(strlen($list->description) > 190)
                                                                {{ substr($list->description, 0, 190) }}
                                                                <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ htmlspecialchars($list->description, ENT_QUOTES, 'UTF-8') }}">Read more</button>
                                                            @else
                                                                {{ $list->description }}
                                                            @endif
                                                        @else
                                                            N/P
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            @if($list->task_group != 'Personal Action')
                                                                <button type="button" data-noteid="{{ $list->description }}" data-taskid="{{ $list->id }}" data-taskgroupid="{{ $list->task_group }}" data-actiondate="{{ $list->action_date }}" data-bs-toggle="tooltip" title="Update Task" class="btn btn-primary update_task" data-bs-container="body" data-role="popover" data-bs-placement="bottom" data-bs-html="true" data-bs-content="<div id='popover-content'>
                                                                    <h4 class='text-center'>Update Task</h4>
                                                                    <div class='clearfix'></div>
                                                                    <div class='box-header with-border'>
                                                                        <div class='form-group row' style='margin-bottom:12px'>
                                                                            <label for='inputSub3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>Select Assignee</label>
                                                                            <div class='col-sm-9'>
                                                                                <select class='assignee-mm-select form-control selec_reg' id='rem_cat' name='rem_cat'>
                                                                                    <option value=''>Select</option>
                                                                                    @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                                                                        <?php $branchname = \App\Models\Branch::where('id', $admin->office_id)->first(); ?>
                                                                                        <option value='{{ $admin->id }}' {{ $admin->id == $list->assigned_to ? 'selected' : '' }}>{{ $admin->first_name . ' ' . $admin->last_name . ' (' . @$branchname->office_name . ')' }}</option>
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class='box-header with-border'>
                                                                        <div class='form-group row' style='margin-bottom:12px'>
                                                                            <label for='inputEmail3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>Note</label>
                                                                            <div class='col-sm-9'>
                                                                                <textarea id='assignnote' class='form-control tinymce-editor f13' placeholder='Enter a note....' type='text'></textarea>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class='box-header with-border'>
                                                                        <div class='form-group row' style='margin-bottom:12px'>
                                                                            <label for='inputEmail3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>DateTime</label>
                                                                            <div class='col-sm-9'>
                                                                                <input type='date' class='form-control f13' placeholder='yyyy-mm-dd' id='popoverdatetime' value='{{ date('Y-m-d') }}' name='popoverdate'>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class='form-group row' style='margin-bottom:12px'>
                                                                        <label for='inputSub3' class='col-sm-3 control-label c6 f13' style='margin-top:8px'>Group</label>
                                                                        <div class='col-sm-9'>
                                                                            <select class='assignee-mm-select form-control selec_reg' id='task_group' name='task_group'>
                                                                                <option value=''>Select</option>
                                                                                <option value='Call'>Call</option>
                                                                                <option value='Checklist'>Checklist</option>
                                                                                <option value='Review'>Review</option>
                                                                                <option value='Query'>Query</option>
                                                                                <option value='Urgent'>Urgent</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <input id='assign_note_id' type='hidden' value=''>
                                                                    <input id='assign_client_id' type='hidden' value='{{ base64_encode(convert_uuencode(@$list->client_id)) }}'>
                                                                    <div class='box-footer' style='padding:10px 0'>
                                                                        <div class='row text-center'>
                                                                            <div class='col-md-12 text-center'>
                                                                                <button class='btn btn-info' id='updateTask'>Update Task</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>">
                                                                    @icon('fa-edit', ['aria-hidden' => 'true'])
                                                                </button>
                                                            @endif

                                                            <form action="{{ route('assignee.destroy_complete_activity', $list->id) }}" method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger" data-bs-toggle="tooltip" title="Delete" onclick="return confirm('Are you sure want to delete?');">
                                                                    @icon('fa-trash', ['aria-hidden' => 'true'])
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 20px;">
                                                    There are no completed actions.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <div class="card-footer">
                                    {!! $assignees_completed->appends($_GET)->links() !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Assign Modal -->
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview"></div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
@include('components.inputmask-scripts')
<script src="{{URL::to('/')}}/js/popover.js"></script>
<script>
jQuery(document).ready(function($){
    $('.listing-container [data-bs-toggle="tooltip"]').tooltip();

    $(document).delegate('.listing-container .openassignee', 'click', function(){
        $('.assignee').show();
    });

    $(document).delegate('.listing-container .closeassignee', 'click', function(){
        $('.assignee').hide();
    });

    // Reassign task
    $(document).delegate('.listing-container .reassign_task', 'click', function(){
        var note_id = $(this).attr('data-noteid');
        $('#assignnote').val(note_id);
        var task_id = $(this).attr('data-taskid');
        $('#assign_note_id').val(task_id);
    });

    // Update task - set all fields when popover is shown (content must be in DOM first)
    $(document).on('shown.bs.popover', '.listing-container .update_task', function() {
        var $popover = $('.popover.show .popover-body');
        $popover.find('#assignnote').val($(this).attr('data-noteid') || '');
        $popover.find('#assign_note_id').val($(this).attr('data-taskid') || '');
        var taskgroup_id = $(this).attr('data-taskgroupid');
        $popover.find('#task_group').val(taskgroup_id || '').trigger('change');
        var followupdate_id = $(this).attr('data-actiondate');
        if (followupdate_id) {
            $popover.find('#popoverdatetime').val(followupdate_id.split(' ')[0]);
        }
    });

    // Mark task as incomplete
    $(document).delegate('.listing-container .not_complete_task', 'click', function(){
        var row_id = $(this).attr('data-id');
        var row_unique_group_id = $(this).attr('data-unique_group_id');
        if(row_id != ""){
            $.ajax({
                type: 'post',
                url: "{{URL::to('/')}}/update-action-not-completed",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { id: row_id, unique_group_id: row_unique_group_id },
                success: function(response){
                    var obj = $.parseJSON(response);
                    location.reload();
                }
            });
        }
    });

    // Assign staff
    $(document).delegate('#assignStaff', 'click', function(){
        $(".popuploader").show();
        var flag = true;
        var error = "";
        $(".custom-error").remove();

        if($('#rem_cat').val() == ''){
            $('.popuploader').hide();
            error = "Assignee field is required.";
            $('#rem_cat').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }
        if($('#assignnote').val() == ''){
            $('.popuploader').hide();
            error = "Note field is required.";
            $('#assignnote').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }
        if($('#task_group').val() == ''){
            $('.popuploader').hide();
            error = "Group field is required.";
            $('#task_group').after("<span class='custom-error' role='alert'>" + error + "</span>");
            flag = false;
        }
        if(flag){
            $.ajax({
                type: 'post',
                url: "{{URL::to('/')}}/clients/action/reassign",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    note_type: 'follow_up',
                    description: $('#assignnote').val(),
                    client_id: $('#assign_client_id').val(),
                    followup_datetime: $('#popoverdatetime').val(),
                    assignee_name: $('#rem_cat :selected').text(),
                    rem_cat: $('#rem_cat option:selected').val(),
                    task_group: $('#task_group option:selected').val()
                },
                success: function(response){
                    $('.popuploader').hide();
                    var obj = $.parseJSON(response);
                    if(obj.success){
                        $("[data-role=popover]").each(function(){
                            (($(this).popover('hide').data('bs.popover')||{}).inState||{}).click = false
                        });
                        location.reload();
                    } else {
                        alert(obj.message);
                        location.reload();
                    }
                }
            });
        } else {
            $("#loader").hide();
        }
    });

    // REMOVED: Deprecated appointment system functionality
    // Open assignee view modal - endpoint /get-assigne-detail was removed
    // $(document).delegate('.listing-container .openassigneview', 'click', function(){ ... });
});
</script>
@endpush
