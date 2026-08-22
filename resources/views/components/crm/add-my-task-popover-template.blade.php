{{-- Inert <template>: same Add My Task form for /action and dashboard (Bootstrap 5 native Popover). --}}
<template id="add-my-task-popover-template">
    <div class="modern-popover-content add-task-layout">
        <div class="form-group">
            <label class="control-label">@icon('circle-user', ['class' => 'icon-sm']) Client</label>
            <select id="assign_client_id" class="form-control js-data-example-ajaxccsearch__addmytask" data-placeholder="Search and select client..."></select>
            <div id="client-error" class="error-message"></div>
        </div>
        <div class="form-group">
            <label class="control-label">@icon('users', ['class' => 'icon-sm']) Assignees</label>
            <div class="dropdown-multi-select" style="width: 100%;">
                <button type="button" class="btn btn-default dropdown-toggle" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 100%;">
                    Select assignees <span class="selected-count"></span>
                </button>
                <div class="dropdown-menu" aria-labelledby="dropdownMenuButton" style="width: 100%;">
                    <div class="dropdown-search-wrapper" style="padding: 8px; border-bottom: 1px solid #e2e8f0;">
                        <input type="text" class="form-control assignee-search-input" placeholder="Search assignees..." style="font-size: 13px; padding: 6px 10px;">
                    </div>
                    <label class="dropdown-item"><input type="checkbox" id="select-all" /> <strong>Select All</strong></label>
                    <div style="border-top: 1px solid #e2e8f0; margin: 5px 0;"></div>
                    <div class="assignee-list">
                        @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                            @php
                                $branchname = \App\Models\Branch::where('id',$admin->office_id)->first();
                                $searchText = strtolower($admin->first_name . $admin->last_name . (@$branchname->office_name ?? ''));
                                $searchText = str_replace(' ', '', $searchText);
                            @endphp
                            <label class="dropdown-item assignee-item" data-searchtext="{{ e($searchText) }}">
                                <input type="checkbox" class="checkbox-item" value="{{ $admin->id }}">
                                {{ e($admin->first_name) }} {{ e($admin->last_name) }} ({{ e(@$branchname->office_name ?? '') }})
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <select class="d-none" id="rem_cat" name="rem_cat[]" multiple="multiple">
                @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                    <option value="{{ $admin->id }}">{{ e($admin->first_name) }} {{ e($admin->last_name) }}</option>
                @endforeach
            </select>
            <div id="assignees-error" class="error-message"></div>
        </div>
        <div class="form-group form-group-full-width">
            <label class="control-label">@icon('message-circle', ['class' => 'icon-sm']) Task Description</label>
            <textarea id="assignnote" class="form-control js-staff-mentions" rows="3" placeholder="Enter task description... (type @ to tag staff)"></textarea>
            <div id="note-error" class="error-message"></div>
        </div>
        <input id="task_group" name="task_group" type="hidden" value="Personal Action">
        <div class="text-center">
            <button type="button" class="btn btn-primary" id="add_my_task">
                @icon('circle-plus', ['class' => 'icon-sm']) Add My Task
            </button>
        </div>
    </div>
</template>
