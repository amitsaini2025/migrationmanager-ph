{{-- Create Action Modal - Simple Clean Design --}}
<div class="modal fade" id="create_task_modal" tabindex="-1" role="dialog" aria-labelledby="createActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content simple-action-modal">
            <div class="modal-header simple-modal-header">
                <h5 class="modal-title" id="createActionModalLabel">Create New Task</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body simple-modal-body">
                <form method="post" action="javascript:void(0);" name="newtaskform" autocomplete="off" id="tasktermform" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="is_ajax" value="0">
                    <input type="hidden" name="is_dashboard" value="true">
                    
                    <div class="simple-form-group">
                        <label class="simple-form-label">
                            @icon('user', ['class' => 'text-primary']) Client
                        </label>
                        <select class="simple-form-control" id="dashboard_client_select" name="client_id" required>
                            <option value="">Search client...</option>
                            @foreach(\App\Models\Admin::select('id','first_name','last_name','client_id')->whereIn('type', ['client', 'lead'])->orderBy('first_name')->get() as $client)
                                <option value="{{ base64_encode(convert_uuencode($client->id)) }}">{{ $client->first_name }} {{ $client->last_name }} ({{ $client->client_id }})</option>
                            @endforeach
                        </select>
                        <span class="custom-error client_error" role="alert" style="display: none;"></span>
                    </div>

                    <div class="simple-form-group">
                        <label class="simple-form-label">
                            @icon('users', ['class' => 'text-primary']) Assignees
                        </label>
                        <div class="dropdown-multi-select modern-dropdown">
                            <button type="button" class="btn btn-default dropdown-toggle simple-dropdown-btn" id="dashboard_dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="dashboard-selected-users-text">SELECT ASSIGNEES</span>
                                @icon('chevron-down', ['class' => 'icon-sm'])
                            </button>
                            <div class="dropdown-menu modern-dropdown-menu" aria-labelledby="dashboard_dropdownMenuButton">
                                <div class="dropdown-search">
                                    <input type="text" id="dashboard-staff-search" class="form-control" placeholder="Search staff...">
                                </div>
                                <div class="dropdown-actions">
                                    <button type="button" id="dashboard-select-all-staff" class="btn btn-sm btn-outline-primary">Select All</button>
                                    <button type="button" id="dashboard-select-none-staff" class="btn btn-sm btn-outline-secondary">Select None</button>
                                </div>
                                <hr class="dropdown-divider">
                                <div id="dashboard-staff-list" class="staff-list-container">
                                    @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                    <?php $branchname = \App\Models\Branch::where('id',$admin->office_id)->first(); ?>
                                    <div class="staff-item modern-staff-item" data-name="{{ strtolower($admin->first_name.' '.$admin->last_name.' '.@$branchname->office_name) }}">
                                        <label class="modern-staff-label">
                                            <input type="checkbox" class="checkbox-item modern-checkbox dashboard-checkbox-item" value="{{ $admin->id }}" data-name="{{ $admin->first_name }} {{ $admin->last_name }} ({{ @$branchname->office_name }})">
                                            @icon('circle-user', ['class' => 'mr-2 text-muted icon-sm'])
                                            <span class="staff-name">{{ $admin->first_name }} {{ $admin->last_name }}</span>
                                            <span class="staff-branch text-muted">({{ @$branchname->office_name }})</span>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <select class="d-none" id="dashboard_rem_cat" name="rem_cat[]" multiple="multiple">
                            @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                            <?php $branchname = \App\Models\Branch::where('id',$admin->office_id)->first(); ?>
                            <option value="{{ $admin->id }}">{{ $admin->first_name }} {{ $admin->last_name }} ({{ @$branchname->office_name }})</option>
                            @endforeach
                        </select>
                        <span class="custom-error assignee_error" role="alert" style="display: none;"></span>
                    </div>

                    <div class="simple-form-group">
                        <label class="simple-form-label">
                            @icon('align-left', ['class' => 'text-primary']) Task Description
                        </label>
                        <textarea class="simple-form-control simple-textarea js-staff-mentions" id="dashboard_assignnote" name="description" placeholder="Enter task description... (type @ to tag staff)" rows="4"></textarea>
                        <span class="custom-error note_error" role="alert" style="display: none;"></span>
                    </div>

                    <div class="simple-form-group">
                        <label class="simple-form-label">
                            @icon('calendar', ['class' => 'text-primary']) Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" id="dashboard_popoverdatetime" name="followup_datetime" class="simple-form-control" required value="{{ date('Y-m-d') }}">
                        <span class="custom-error date_error" role="alert" style="display: none;"></span>
                    </div>

                    <div class="simple-form-group">
                        <label class="simple-form-label">
                            @icon('tag', ['class' => 'text-primary']) Group <span class="text-danger">*</span>
                        </label>
                        <select class="simple-form-control" id="dashboard_task_group" name="task_group" required>
                            <option value="">Select Group</option>
                            <option value="Call">📞 Call</option>
                            <option value="Checklist">✅ Checklist</option>
                            <option value="Review">📋 Review</option>
                            <option value="Query">❓ Query</option>
                            <option value="Urgent">⚠️ Urgent</option>
                        </select>
                        <span class="custom-error group_error" role="alert" style="display: none;"></span>
                    </div>

                    <div class="simple-form-group">
                        <label class="simple-form-label d-flex align-items-center">
                            <input class="note_deadline_checkbox mr-2" type="checkbox" id="dashboard_note_deadline_checkbox" name="note_deadline_checkbox" value="">
                            @icon('clock', ['class' => 'text-primary']) Note Deadline
                        </label>
                        <input type="date" class="simple-form-control" id="dashboard_note_deadline" name="note_deadline" value="{{ date('Y-m-d', strtotime('+1 day')) }}" disabled>
                    </div>

                    <div class="simple-modal-footer">
                        <button type="button" class="btn btn-primary simple-submit-btn-action" id="dashboard_assignStaff">
                            @icon('plus', ['class' => 'icon-sm']) ADD MY TASK
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Simple Clean Modal Design */
.simple-action-modal {
    border-radius: 16px;
    border: none;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.simple-modal-header {
    padding: 24px 32px;
    border-bottom: 1px solid #e8e8e8;
    background: #fafafa;
    border-radius: 16px 16px 0 0;
}

.simple-modal-header .modal-title {
    font-size: 22px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.simple-modal-body {
    padding: 32px;
    background: white;
}

.simple-form-group {
    margin-bottom: 24px;
}

.simple-form-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #5a6c7d;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.simple-form-label i {
    font-size: 16px;
}

.simple-form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e8e8e8;
    border-radius: 8px;
    font-size: 14px;
    color: #2c3e50;
    transition: all 0.2s ease;
    background: white;
}

.simple-form-control:focus {
    outline: none;
    border-color: #6c7ff2;
    box-shadow: 0 0 0 3px rgba(108, 127, 242, 0.1);
}

.simple-textarea {
    resize: vertical;
    font-family: inherit;
    min-height: 100px;
}

.simple-dropdown-btn {
    width: 100%;
    text-align: left;
    padding: 12px 16px;
    border: 2px solid #e8e8e8;
    border-radius: 8px;
    background: white;
    color: #2c3e50;
    font-size: 14px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.simple-dropdown-btn:hover,
.simple-dropdown-btn:focus {
    border-color: #6c7ff2;
    box-shadow: 0 0 0 3px rgba(108, 127, 242, 0.1);
    outline: none;
}

.simple-dropdown-btn.has-selection {
    border-color: #6c7ff2;
}

.modern-dropdown-menu {
    max-height: 300px;
    overflow-y: auto;
    padding: 8px;
    width: 100%;
}

.dropdown-search {
    padding: 8px;
    border-bottom: 1px solid #e0e0e0;
}

.dropdown-actions {
    padding: 8px;
    display: flex;
    gap: 8px;
    border-bottom: 1px solid #e0e0e0;
}

.dropdown-divider {
    margin: 8px 0;
}

.modern-staff-item {
    padding: 8px;
    border-radius: 6px;
    transition: background 0.2s ease;
}

.modern-staff-item:hover {
    background: #f5f5f5;
}

.modern-staff-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
}

.modern-checkbox {
    margin-right: 10px;
}

.staff-list-container {
    max-height: 250px;
    overflow-y: auto;
}

.simple-modal-footer {
    margin-top: 32px;
    display: flex;
    justify-content: center;
}

.simple-submit-btn-action {
    background-color: #3498db !important;
    color: white !important;
    border: 2px solid #2980b9 !important;
    padding: 10px 20px !important;
    border-radius: 8px !important;
    font-size: 0.9em !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.3s ease !important;
    white-space: nowrap !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2) !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
}

.simple-submit-btn-action:hover {
    background-color: #2980b9 !important;
    box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3) !important;
    transform: translateY(-1px) !important;
}

.simple-submit-btn-action i {
    font-size: 14px !important;
}

.custom-error {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.selection-count-badge {
    position: absolute;
    right: 40px;
    top: 50%;
    transform: translateY(-50%);
    background: #6c7ff2;
    color: white;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
    .simple-modal-header,
    .simple-modal-body {
        padding: 20px;
    }
    
    .simple-modal-header .modal-title {
        font-size: 18px;
    }
}
</style>

{{-- Extend Note Deadline Modal --}}
<div class="modal fade custom_modal" id="extend_note_popup" tabindex="-1" role="dialog" aria-labelledby="create_action_popupLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="padding: 20px;">
            <div class="modal-header" style="padding-bottom: 11px;">
                <h5 class="modal-title assignnn" id="create_action_popupLabel" style="margin: 0 -24px;">Extend Notes Deadline</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <input id="note_id" type="hidden" value="">
            <input id="unique_group_id" type="hidden" value="">
            <div id="popover-content">
                <div class="box-header with-border">
                    <div class="form-group row" style="margin-bottom:12px;">
                        <label for="inputEmail3" class="col-sm-3 control-label c6 f13" style="margin-top:8px;">Note</label>
                        <div class="col-sm-9">
                            <textarea id="assignnote" class="form-control" placeholder="Enter a note..."></textarea>
                        </div>
                        <div class="clearfix"></div>
                    </div>
                </div>

                <div class="form-group row note_deadline">
                    <label for="inputSub3" class="col-sm-3 control-label c6 f13" style="margin-top:8px;">
                        Note Deadline
                    </label>
                    <div class="col-sm-9">
                        <input type="date" class="form-control f13" placeholder="yyyy-mm-dd" id="note_deadline" value="{{ date('Y-m-d') }}" name="note_deadline">
                    </div>
                    <div class="clearfix"></div>
                </div>

                <div class="box-footer" style="padding:10px 0;">
                    <div class="row text-center">
                        <div class="col-md-12 text-center">
                            <button class="btn btn-danger" id="extend_deadline">Extend Deadline</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Task Completion Notes Modal --}}
<div class="modal fade" id="dashboardCompletionNotesModal" tabindex="-1" role="dialog" aria-labelledby="dashboardCompletionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3498db; color: white;">
                <h5 class="modal-title" id="dashboardCompletionNotesModalLabel">
                    @icon('circle-check', ['class' => 'icon-sm']) Complete Task
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="dashboardCompletionNotes" class="font-weight-bold">
                        @icon('message-circle', ['class' => 'icon-sm']) Completion Notes (optional)
                    </label>
                    <textarea
                        class="form-control"
                        id="dashboardCompletionNotes"
                        rows="5"
                        placeholder="Add any notes or feedback about completing this task..."
                        style="resize: vertical; border: 2px solid #e9ecef; border-radius: 8px; padding: 12px;"
                    ></textarea>
                    <small class="form-text text-muted">
                        @icon('info', ['class' => 'icon-sm']) These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer" style="background-color: #f8f9fa;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    @icon('x', ['class' => 'icon-sm']) Cancel
                </button>
                <button type="button" class="btn btn-success" id="dashboardConfirmTaskCompletion">
                    @icon('check', ['class' => 'icon-sm']) Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
