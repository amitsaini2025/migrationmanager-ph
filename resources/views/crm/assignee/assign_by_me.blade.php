@extends('layouts.crm_client_detail')
@section('title', 'Assigned by Me')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<link rel="stylesheet" href="{{ asset('css/listing-datepicker.css') }}">
<style>
    /* Page-specific styles for assign_by_me page */
    .listing-container .client-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .listing-container .client-header h1 {
        font-size: 1.8em;
        font-weight: 600;
        color: #212529;
        margin: 0;
        word-wrap: break-word;
    }
    
    .listing-container .client-status {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .listing-container .nav-pills .nav-item .nav-link {
        margin-left: 10px;
    }
    
    .listing-container .sort_col a {
        color: #0d6efd !important;
        text-decoration: none;
    }
    
    .listing-container .sort_col a:hover {
        text-decoration: underline;
    }
    
    .listing-container .countAction {
        background: #1f1655;
        padding: 2px 8px;
        border-radius: 50%;
        color: #fff;
        font-size: 0.8em;
        margin-left: 5px;
    }
    
    .listing-container .complete_task {
        cursor: pointer;
    }
    
    .listing-container .btn-sm {
        padding: 5px 10px;
        font-size: 0.85em;
    }
    
    .listing-container .modal-content {
        border-radius: 8px;
    }
    
    .listing-container .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }
    
    .listing-container .modal-body {
        padding: 20px;
    }
    
    .listing-container .ts-wrapper {
        z-index: 100000;
        width: 100% !important;
    }

    /* Page-specific margin fix for action page */
    .listing-container {
        margin-left: 80px !important; /* Add margin to prevent overlap with left sidebar */
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .listing-container .table th,
        .listing-container .table td {
            font-size: 0.85em;
            padding: 8px;
        }
        
        .listing-container .btn-sm {
            padding: 4px 8px;
        }
    }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 80px;">
        <div class="listing-section-body">
            @include('../Elements/flash-message')
            
            <div class="client-header">
                <h4>Assigned by Me</h4>
                <div class="client-status">
                    <ul class="nav nav-pills" id="client_tabs" role="tablist">
                        <li class="nav-item">
                            <a class="status-badge nav-link active" href="{{ URL::to('/action') }}">Incomplete</a>
                        </li>
                        <li class="nav-item">
                            <a class="status-badge nav-link" href="{{ URL::to('/action_completed') }}">Completed</a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form action="{{ route('assignee.assigned_by_me') }}" method="get" class="mb-4">
                        <div class="row">
                            <div class="col-md-12 group_type_section">
                                <!-- Add filters if needed -->
                            </div>
                        </div>
                    </form>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="active_quotation" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="5%" style="text-align: center;">Sno</th>
                                            <th width="5%" style="text-align: center;">Done</th>
                                            <th width="15%">Assignee Name</th>
                                            <th width="15%">Client Reference</th>
                                            <th width="15%" class="sort_col">@sortablelink('action_date', 'Assign Date')</th>
                                            <th width="10%" class="sort_col">@sortablelink('task_group', 'Type')</th>
                                            <th>Note</th>
                                            <th width="15%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if (count($assignees_notCompleted) > 0)
                                            @foreach ($assignees_notCompleted as $list)
                                                @php
                                                    $admin = \App\Models\Staff::where('id', $list->assigned_to)->first();
                                                    $full_name = $admin ? ($admin->first_name ?? 'N/A') . ' ' . ($admin->last_name ?? 'N/A') : 'N/P';
                                                    $client_name = $list->noteClient ? trim($list->noteClient->company_name_or_personal_name) : 'N/P';
                                                    if ($list->noteClient && $client_name === '') {
                                                        $client_name = trim($list->noteClient->first_name . ' ' . $list->noteClient->last_name) ?: 'N/P';
                                                    }
                                                @endphp
                                                <tr>
                                                    <td style="text-align: center;">{{ ++$i }}</td>
                                                    <td style="text-align: center;">
                                                        <input type="radio" class="complete_task" data-bs-toggle="tooltip" title="Mark Complete!" data-id="{{ $list->id }}" data-unique_group_id="{{ $list->unique_group_id }}">
                                                    </td>
                                                    <td>{{ $full_name }}</td>
                                                    <td>
                                                        {{ $client_name }}
                                                        <br>
                                                        @if ($list->noteClient)
                                                            <a href="{{ URL::to('/clients/detail/' . base64_encode(convert_uuencode($list->client_id))) }}" target="_blank">{{ $list->noteClient->client_id }}</a>
                                                        @endif
                                                    </td>
                                                    <td>{{ $list->action_date ? date('d/m/Y', strtotime($list->action_date)) : 'N/P' }}</td>
                                                    <td>{{ $list->task_group ?? 'N/P' }}</td>
                                                    <td>
                                                        @if (isset($list->description) && $list->description != "")
                                                            @if (strlen($list->description) > 190)
                                                                {!! substr($list->description, 0, 190) !!}
                                                                <button type="button" class="btn btn-link" data-bs-toggle="popover" title="" data-content="{{ $list->description }}">Read more</button>
                                                            @else
                                                                {!! $list->description !!}
                                                            @endif
                                                        @else
                                                            N/P
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($list->task_group != 'Personal Action')
                                                            <button type="button" data-noteid="{{ $list->description }}" data-taskid="{{ $list->id }}" data-taskgroupid="{{ $list->task_group }}" data-actiondate="{{ $list->action_date }}" class="btn btn-primary btn-sm update_task" data-bs-toggle="tooltip" title="Update Task" data-bs-container="body" data-role="popover" data-bs-placement="bottom" data-bs-html="true" data-bs-content='
                                                                <div id="popover-content">
                                                                    <h4 class="text-center">Update Task</h4>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="rem_cat" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Select Assignee</label>
                                                                        <div class="col-sm-9">
                                                                            <select class="assignee-mm-select form-control selec_reg" id="rem_cat" name="rem_cat">
                                                                                <option value="">Select</option>
                                                                                @foreach (\App\Models\Staff::where('status', 1)->orderBy('first_name', 'ASC')->get() as $admin)
                                                                                    @php
                                                                                        $branchname = \App\Models\Branch::where('id', $admin->office_id)->first();
                                                                                    @endphp
                                                                                    <option value="{{ $admin->id }}" {{ $admin->id == $list->assigned_to ? 'selected' : '' }}>{{ $admin->first_name . ' ' . $admin->last_name . ' (' . ($branchname->office_name ?? 'N/A') . ')' }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="assignnote" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Note</label>
                                                                        <div class="col-sm-9">
                                                                            <textarea id="assignnote" class="form-control tinymce-editor f13" placeholder="Enter a note..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="popoverdatetime" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Date</label>
                                                                        <div class="col-sm-9">
                                                                            <input type="date" class="form-control f13" placeholder="yyyy-mm-dd" id="popoverdatetime" value="{{ $list->action_date ? date('Y-m-d', strtotime($list->action_date)) : date('Y-m-d') }}" name="popoverdate">
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group row" style="margin-bottom:12px">
                                                                        <label for="task_group" class="col-sm-3 control-label c6 f13" style="margin-top:8px">Group</label>
                                                                        <div class="col-sm-9">
                                                                            <select class="assignee-mm-select form-control selec_reg" id="task_group" name="task_group">
                                                                                <option value="">Select</option>
                                                                                <option value="Call">Call</option>
                                                                                <option value="Checklist">Checklist</option>
                                                                                <option value="Review">Review</option>
                                                                                <option value="Query">Query</option>
                                                                                <option value="Urgent">Urgent</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                    <input id="assign_note_id" type="hidden" value="">
                                                                    <input id="assign_client_id" type="hidden" value="{{ base64_encode(convert_uuencode($list->client_id)) }}">
                                                                    <div class="text-center">
                                                                        <button class="btn btn-info" id="updateTask">Update Task</button>
                                                                    </div>
                                                                </div>'>
                                                                @icon('fa-edit', ['aria-hidden' => 'true'])
                                                            </button>
                                                            <button class="btn btn-danger btn-sm deleteNote" data-remote="/destroy_activity/{{ $list->id }}" data-bs-toggle="tooltip" title="Delete Task">
                                                                @icon('fa-trash', ['aria-hidden' => 'true'])
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="8" style="text-align: center; padding: 20px;">
                                                    No actions assigned by me.
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                                
                                <!-- Pagination -->
                                <div class="card-footer">
                                    {!! $assignees_notCompleted->appends($_GET)->links() !!}
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
<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content taskview">
            <!-- Modal content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Task Completion Notes Modal -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3498db; color: white;">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    @icon('fa-check-circle') Complete Task
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="completionNotes" class="font-weight-bold">
                        @icon('fa-comment') Completion Notes/Feedback
                    </label>
                    <textarea 
                        class="form-control" 
                        id="completionNotes" 
                        rows="5" 
                        placeholder="Enter any notes or feedback about completing this task..."
                        style="resize: vertical; border: 2px solid #e9ecef; border-radius: 8px; padding: 12px;"
                    ></textarea>
                    <small class="form-text text-muted">
                        @icon('fa-info-circle') These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer" style="background-color: #f8f9fa;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    @icon('fa-times') Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmTaskCompletion">
                    @icon('fa-check') Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="{{URL::to('/')}}/css/task-popover-modern.css">
@include('components.inputmask-scripts')
<script src="{{ URL::to('/') }}/js/popover.js"></script>
<script>
    jQuery(document).ready(function($) {
        // Initialize enhanced selects (mmSelect) for assignee dropdowns
        $('.listing-container .assignee-mm-select').mmSelect({
            dropdownParent: $('#openassigneview'),
        });

        // Open assignee modal
        $(document).on('click', '.listing-container .openassignee', function() {
            $('.assignee').show();
        });

        $(document).on('click', '.listing-container .closeassignee', function() {
            $('.assignee').hide();
        });

        // Reassign task
        $(document).on('click', '.listing-container .reassign_task', function() {
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

        // Mark task as not complete
        $(document).on('click', '.listing-container .not_complete_task', function() {
            var row_id = $(this).attr('data-id');
            var row_unique_group_id = $(this).attr('data-unique_group_id');
            if (row_id != "") {
                $.ajax({
                    type: 'post',
                    url: "{{ URL::to('/') }}/update-action-not-completed",
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { id: row_id, unique_group_id: row_unique_group_id },
                    success: function(response) {
                        location.reload();
                    }
                });
            }
        });

        // Mark task as complete - open modal
        var currentTaskId = null;
        var currentTaskGroupId = null;
        
        $(document).on('click', '.listing-container .complete_task', function() {
            var row_id = $(this).attr('data-id');
            var row_unique_group_id = $(this).attr('data-unique_group_id');
            
            if (row_id != "") {
                // Store task IDs for later use
                currentTaskId = row_id;
                currentTaskGroupId = row_unique_group_id;
                
                // Clear previous notes
                $('#completionNotes').val('');
                
                // Show the completion notes modal
                $('#completionNotesModal').modal('show');
            }
        });
        
        // Handle task completion with notes
        $(document).on('click', '#confirmTaskCompletion', function() {
            var completionNotes = $('#completionNotes').val().trim();
            
            if (!currentTaskId) {
                console.error('No task ID found');
                return;
            }
            
            // Disable button to prevent double submission
            var $button = $(this);
            $button.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Completing...');
            
            $.ajax({
                type: 'post',
                url: "{{ URL::to('/') }}/update-action-completed",
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    id: currentTaskId, 
                    unique_group_id: currentTaskGroupId,
                    completion_notes: completionNotes
                },
                success: function(response) {
                    // Close modal
                    $('#completionNotesModal').modal('hide');
                    
                    // Reset button
                    $button.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Complete Task');
                    
                    // Clear stored IDs
                    currentTaskId = null;
                    currentTaskGroupId = null;
                    
                    // Reload page
                    location.reload();
                },
                error: function(xhr) {
                    console.error('Error completing task:', xhr.responseText);
                    alert('An error occurred while completing the task.');
                    
                    // Reset button
                    $button.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Complete Task');
                }
            });
        });

        // Update task (scope fields to open popover — matches action.blade.php)
        $(document).on('click', '#updateTask', function() {
            var $popover = $(this).closest('.popover');
            if (!$popover.length) {
                return;
            }

            $(".popuploader").show();
            $popover.find('.custom-error').remove();

            var assignee = $popover.find('#rem_cat').val();
            var note = $popover.find('#assignnote').val();
            var taskGroup = $popover.find('#task_group').val();
            var flag = true;

            if (!assignee) {
                $popover.find('#rem_cat').after("<span class='custom-error' role='alert'>Assignee field is required.</span>");
                flag = false;
            }
            if (!note || !String(note).trim()) {
                $popover.find('#assignnote').after("<span class='custom-error' role='alert'>Note field is required.</span>");
                flag = false;
            }
            if (!taskGroup) {
                $popover.find('#task_group').after("<span class='custom-error' role='alert'>Group field is required.</span>");
                flag = false;
            }

            if (!flag) {
                $('.popuploader').hide();
                return;
            }

            $.ajax({
                type: 'post',
                url: "{{ URL::to('/') }}/clients/action/update",
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    note_id: $popover.find('#assign_note_id').val(),
                    note_type: 'follow_up',
                    description: note,
                    client_id: $popover.find('#assign_client_id').val(),
                    followup_datetime: $popover.find('#popoverdatetime').val(),
                    assignee_name: $popover.find('#rem_cat option:selected').text(),
                    rem_cat: assignee,
                    task_group: taskGroup
                },
                success: function(obj) {
                    $('.popuploader').hide();
                    if (obj && obj.success) {
                        $("[data-role=popover]").each(function() {
                            (($(this).popover('hide').data('bs.popover') || {}).inState || {}).click = false;
                        });
                        location.reload();
                    } else {
                        alert((obj && obj.message) ? obj.message : 'Update failed.');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    console.error('Error updating task:', xhr.responseText);
                    var msg = 'An error occurred while updating the task. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    alert(msg);
                }
            });
        });

        // REMOVED: Deprecated appointment system functionality
        // Open assignee view modal - endpoint /get-assigne-detail was removed
        // $(document).on('click', '.listing-container .openassigneview', function() { ... });

        // Delete task record
        $(document).on('click', '.listing-container .deleteNote', function(e) {
            e.preventDefault();
            var url = $(this).data('remote');
            
            if (confirm('Are you sure you want to delete this task?')) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });

                $.ajax({
                    type: 'DELETE',
                    url: url,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error deleting task: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error deleting task. Please try again.');
                        console.error('Delete error:', error);
                    }
                });
            }
        });
    });
</script>
@endpush
