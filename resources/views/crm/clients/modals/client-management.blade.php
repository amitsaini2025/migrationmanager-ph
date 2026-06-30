<!-- Convert Lead to Client Modal -->
<div class="modal fade custom_modal" id="convertLeadToClientModal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Convert Lead To Client</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				@php
					$leadHasMatters = isset($fetchedData) && ($fetchedData->type ?? '') === 'lead' && \DB::table('client_matters')->where('client_id', $fetchedData->id)->where('matter_status', 1)->exists();
				@endphp
				@if($leadHasMatters)
				<div class="alert alert-info mb-3">
					<strong>This lead already has matter(s).</strong> You can convert to client without creating a new matter:
				</div>
				<form method="post" action="{{ route('clients.convertLeadOnly') }}" class="mb-4">
					@csrf
					<input type="hidden" name="client_id" value="{{ $fetchedData->id }}">
					<input type="hidden" name="user_id" value="{{ @Auth::user()->id }}">
					<button type="submit" class="btn btn-success">
						@icon('fa-user-check', ['class' => 'mr-1']) Convert to Client only
					</button>
				</form>
				<hr class="my-4">
				<p class="text-muted small mb-2">Or create a new matter and convert:</p>
				@endif
                <form method="get" action="{{URL::to('/clients/changetype/'.base64_encode(convert_uuencode($fetchedData->id)).'/client')}}" name="convert_lead_to_client" autocomplete="off" id="convert_lead_to_client">
				    @csrf
                    <div class="row">
                        <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                        <input type="hidden" name="user_id" value="{{@Auth::user()->id}}">
                        <input type="hidden" name="matter_id" id="convert_matter_id_final" value="">
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="migration_agent">Migration Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="migration_agent" id="sel_migration_agent_id">
                                    <option value="">Select Migration Agent</option>
                                    @foreach(\App\Models\Staff::where('role',16)->select('id','first_name','last_name','email')->where('status',1)->get() as $migAgntlist)
                                        <option value="{{$migAgntlist->id}}">{{@$migAgntlist->first_name}} {{@$migAgntlist->last_name}} ({{@$migAgntlist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="person_responsible">Person Responsible <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="person_responsible" id="sel_person_responsible_id">
                                    <option value="">Select Person Responsible</option>
                                    @foreach(\App\Models\Staff::where('role',12)->select('id','first_name','last_name','email')->where('status',1)->get() as $perreslist)
                                        <option value="{{$perreslist->id}}">{{@$perreslist->first_name}} {{@$perreslist->last_name}} ({{@$perreslist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="person_assisting">Person Assisting <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="person_assisting" id="sel_person_assisting_id">
                                    <option value="">Select Person Assisting</option>
                                    @foreach(\App\Models\Staff::where('role',13)->select('id','first_name','last_name','email')->where('status',1)->get() as $perassislist)
                                        <option value="{{$perassislist->id}}">{{@$perassislist->first_name}} {{@$perassislist->last_name}} ({{@$perassislist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="office_id">Handling Office <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="office_id" id="sel_office_id">
                                    <option value="">Select Office</option>
                                    @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                        <option value="{{$office->id}}" 
                                            {{ Auth::user()->office_id == $office->id ? 'selected' : '' }}>
                                            {{$office->office_name}}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    @icon('fa-building') This matter will be handled by the selected office
                                </small>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="matter_id">Select Matter <span class="span_req">*</span></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="general_matter_checkbox_new">
                                    <label class="form-check-label" for="general_matter_checkbox_new">General Matter</label>
                                </div>

                                <label class="form-check-label">Or Select any option</label>

                                <select class="form-control mm-select" name="matter_id_select" id="sel_matter_id">
                                    <option value="">Select Matter</option>
                                    @php
                                        $matterQuery = \App\Models\Matter::select('id','title')->where('status',1)
                                            ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                        $matterList = $matterQuery->get();
                                    @endphp
                                    @foreach($matterList as $matterlist)
                                        <option value="{{$matterlist->id}}">{{@$matterlist->title}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">
                            <button onclick="customValidate('convert_lead_to_client')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

<style>
    .dropdown-toggle::after {
        margin-left: 16.255em !important;
    }

    /* Custom styles for the assignee dropdown */
    #create_action_popup .dropdown-menu {
        padding: 12px !important;
    }

    #create_action_popup .staff-item {
        margin-bottom: 8px !important;
        padding-left: 0 !important;
    }

    #create_action_popup .staff-item label {
        margin-bottom: 0 !important;
        padding-left: 0 !important;
        margin-left: 0 !important;
        display: flex !important;
        align-items: center !important;
        text-align: left !important;
    }

    #create_action_popup .checkbox-item {
        margin-right: 8px !important;
        margin-left: 0 !important;
        flex-shrink: 0 !important;
    }

    #create_action_popup .staff-item span {
        margin-left: 0 !important;
        padding-left: 0 !important;
        flex: 1 !important;
        text-align: left !important;
        text-indent: 0 !important;
    }

    #create_action_popup #staff-list {
        margin-left: 0 !important;
        padding-left: 0 !important;
    }

    /* ========================================
       MODERN ASSIGN STAFF MODAL STYLES
       ======================================== */

    /* Modal Container */
    .assign-staff-modal {
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        border: none;
        overflow: hidden;
    }

    /* Header Styling */
    .assign-staff-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-bottom: none;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-title-section {
        display: flex;
        align-items: center;
    }

    .modal-title-section .modal-title {
        font-weight: 600;
        font-size: 1.4rem;
    }

    .modal-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-actions .close {
        color: white;
        opacity: 0.8;
        font-size: 1.5rem;
        padding: 0;
        margin: 0;
    }

    .modal-actions .close:hover {
        opacity: 1;
    }

    /* Body Styling */
    .assign-staff-body {
        padding: 30px 25px;
        background: #fafbfc;
    }

    /* Enhanced Form Groups */
    .enhanced-form-group {
        margin-bottom: 25px;
    }

    .form-label {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .form-label i {
        font-size: 0.9rem;
    }

    /* Input Groups */
    .input-group-text {
        background: #f7fafc;
        border-color: #e2e8f0;
        color: #718096;
        border-radius: 8px 0 0 8px;
        padding: 12px 15px;
    }

    .enhanced-input,
    .enhanced-select,
    .enhanced-textarea {
        border-radius: 0 8px 8px 0;
        border-color: #e2e8f0;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }

    .enhanced-input:focus,
    .enhanced-select:focus,
    .enhanced-textarea:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .enhanced-textarea {
        min-height: 100px;
        resize: vertical;
    }

    /* Modern Dropdown Styling */
    .modern-dropdown {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    /* Enhanced Dropdown Input Wrapper */
    .enhanced-dropdown-input-wrapper {
        position: relative;
        width: 100%;
        display: flex;
        align-items: center;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: white;
        min-height: 48px;
        transition: all 0.3s ease;
    }

    .enhanced-dropdown-input-wrapper:hover {
        border-color: #667eea;
    }

    .enhanced-dropdown-input-wrapper.has-selection {
        border-color: #667eea;
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
    }

    .dropdown-input-icon {
        position: absolute;
        left: 15px;
        color: #718096;
        z-index: 2;
        pointer-events: none;
    }

    .enhanced-dropdown-input {
        width: 100%;
        border: none;
        padding: 12px 40px 12px 50px;
        background: transparent;
        color: #2d3748;
        font-size: 0.95rem;
        outline: none;
        flex: 1;
    }

    .enhanced-dropdown-input:focus {
        box-shadow: none;
    }

    .enhanced-dropdown-input-wrapper:focus-within {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .selected-staff-display {
        position: absolute;
        left: 50px;
        top: 50%;
        transform: translateY(-50%);
        color: #2d3748;
        font-size: 0.95rem;
        pointer-events: none;
        white-space: nowrap;
        max-width: calc(100% - 90px);
        overflow: hidden;
        text-overflow: ellipsis;
        z-index: 1;
    }

    .enhanced-dropdown-input-wrapper.has-selection .selected-staff-display {
        color: #667eea;
        font-weight: 500;
    }

    .enhanced-dropdown-input-wrapper.has-selection .enhanced-dropdown-input {
        color: transparent;
    }

    .enhanced-dropdown-input-wrapper.has-selection .enhanced-dropdown-input::placeholder {
        color: transparent;
        opacity: 0;
        visibility: hidden;
    }

    /* Ensure placeholder is completely hidden when users are selected */
    .enhanced-dropdown-input-wrapper.has-selection .enhanced-dropdown-input::-webkit-input-placeholder {
        color: transparent;
        opacity: 0;
        visibility: hidden;
    }

    .enhanced-dropdown-input-wrapper.has-selection .enhanced-dropdown-input::-moz-placeholder {
        color: transparent;
        opacity: 0;
        visibility: hidden;
    }

    .enhanced-dropdown-input-wrapper.has-selection .enhanced-dropdown-input:-ms-input-placeholder {
        color: transparent;
        opacity: 0;
        visibility: hidden;
    }

    .enhanced-dropdown-input-wrapper.has-selection .enhanced-dropdown-input:-moz-placeholder {
        color: transparent;
        opacity: 0;
        visibility: hidden;
    }

    .dropdown-chevron-icon {
        position: absolute;
        right: 15px;
        color: #718096;
        z-index: 2;
        pointer-events: none;
    }

    .enhanced-dropdown-input-wrapper.has-selection .dropdown-chevron-icon {
        color: #667eea;
    }

    .modern-dropdown-menu {
        min-width: 100%;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        background: white;
        margin-top: 5px;
    }

    /* Remove search from dropdown since it's now in the main input */
    .dropdown-search {
        display: none;
    }

    .dropdown-actions {
        display: flex;
        gap: 8px;
        margin-bottom: 15px;
    }

    .dropdown-actions .btn {
        border-radius: 6px;
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 500;
        color: #2d3748;
    }

    .dropdown-actions .btn:hover {
        color: #2d3748;
    }

    .dropdown-divider {
        margin: 15px 0;
        border-color: #e2e8f0;
    }

    .staff-list-container {
        max-height: 200px;
        overflow-y: auto;
    }

    /* Modern Staff Items */
    .modern-staff-item {
        margin-bottom: 8px;
        padding: 8px;
        border-radius: 6px;
        transition: background-color 0.2s ease;
    }

    .modern-staff-item:hover {
        background-color: #f7fafc;
    }

    .modern-staff-label {
        margin: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 0;
        font-size: 0.9rem;
        color: #2d3748;
    }

    .modern-staff-label .text-muted {
        color: #2d3748 !important;
    }

    .modern-checkbox {
        margin-right: 10px;
        margin-left: 0;
        flex-shrink: 0;
        width: 16px;
        height: 16px;
    }

    .staff-name {
        font-weight: 500;
        color: #2d3748;
        margin-right: 5px;
    }

    .staff-branch {
        font-size: 0.85rem;
        color: #2d3748 !important;
        font-weight: 400;
    }

    /* Footer Buttons */
    .modal-footer-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-assign-staff {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-assign-staff:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
    }

    .btn-outline-secondary {
        border-radius: 8px;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 1rem;
        border-width: 2px;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
        transform: translateY(-1px);
    }

    /* Custom Error Styling */
    .custom-error {
        color: #e53e3e;
        font-size: 0.85rem;
        margin-top: 5px;
        font-weight: 500;
    }

    /* Note Deadline Styling */
    .note_deadline .form-label {
        margin-bottom: 0;
    }

    .note_deadline_checkbox {
        width: 18px;
        height: 18px;
        margin-right: 8px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .assign-staff-body {
            padding: 20px 15px;
        }
        
        .modal-footer-buttons {
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-assign-staff,
        .btn-outline-secondary {
            width: 100%;
            padding: 15px 20px;
        }
        
        .modal-title-section .modal-title {
            font-size: 1.2rem;
        }
    }

    /* Loading overlay: single .popuploader lives in layouts (components/crm-popuploader) */

    /* Enhanced Tooltip Styling */
    .tooltip-inner {
        background: #2d3748;
        color: white;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 0.85rem;
        line-height: 1.4;
        max-width: 300px;
        text-align: left;
        white-space: pre-line;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .tooltip.bs-tooltip-bottom .arrow::before {
        border-bottom-color: #2d3748;
    }

    /* Selection count badge */
    .selection-count-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #667eea;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
</style>

<!-- Assign Staff Modal -->
<div class="modal fade custom_modal" id="create_action_popup" tabindex="-1" role="dialog" aria-labelledby="create_action_popupLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content assign-staff-modal">
            <div class="modal-header assign-staff-header">
                <div class="modal-title-section">
                    @icon('fa-user-plus', ['class' => 'text-white mr-2'])
                    <h5 class="modal-title mb-0" id="create_action_popupLabel">Assign Staff</h5>
                </div>
                <div class="modal-actions">
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>

            <div class="modal-body assign-staff-body">
                <div class="row">
                    <div class="col-12">
                        <div class="form-group enhanced-form-group">
                            <label for="staff-search-input" class="form-label">
                                @icon('fa-users', ['class' => 'text-muted mr-1'])
                                Select Assignee <span class="text-danger">*</span>
                            </label>
                            <div class="dropdown-multi-select modern-dropdown">
                                <div class="enhanced-dropdown-input-wrapper">
                                    @icon('fa-user-plus', ['class' => 'dropdown-input-icon'])
                                    <input type="text" 
                                           id="staff-search-input" 
                                           class="form-control enhanced-dropdown-input" 
                                           placeholder="Search or select staff..." 
                                           autocomplete="off"
                                           aria-haspopup="true" 
                                           aria-expanded="false" style="padding-left: 38px !important;">
                                    <span id="selected-staff-display" class="selected-staff-display"></span>
                                    @icon('fa-chevron-down', ['class' => 'dropdown-chevron-icon'])
                                </div>
                                <div class="dropdown-menu modern-dropdown-menu" id="staffDropdownMenu" aria-labelledby="staff-search-input">
                                    <!-- Select All/None buttons -->
                                    <div class="dropdown-actions">
                                        <button type="button" id="select-all-staff" class="btn btn-sm btn-outline-primary">Select All</button>
                                        <button type="button" id="select-none-staff" class="btn btn-sm btn-outline-secondary">Select None</button>
                                    </div>
                                    <hr class="dropdown-divider">
                                    <!-- Staff list -->
                                    <div id="staff-list" class="staff-list-container">
                                        @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                                        <?php $branchname = \App\Models\Branch::where('id',$admin->office_id)->first(); ?>
                                        <div class="staff-item modern-staff-item" data-name="{{ strtolower($admin->first_name.' '.$admin->last_name.' '.@$branchname->office_name) }}">
                                            <label class="modern-staff-label">
                                                <input type="checkbox" class="checkbox-item modern-checkbox" value="{{ $admin->id }}" data-name="{{ $admin->first_name }} {{ $admin->last_name }} ({{ @$branchname->office_name }})">
                                                @icon('fa-user-circle', ['class' => 'mr-2 text-muted'])
                                                <span class="staff-name">{{ $admin->first_name }} {{ $admin->last_name }}</span>
                                                <span class="staff-branch text-muted">({{ @$branchname->office_name }})</span>
                                            </label>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden input to store selected values -->
                        <select class="d-none" id="rem_cat" name="rem_cat[]" multiple="multiple">
                            @foreach(\App\Models\Staff::where('status',1)->orderby('first_name','ASC')->get() as $admin)
                            <?php $branchname = \App\Models\Branch::where('id',$admin->office_id)->first(); ?>
                            <option value="{{ $admin->id }}">{{ $admin->first_name }} {{ $admin->last_name }} ({{ @$branchname->office_name }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-group enhanced-form-group">
                            <label for="assignnote" class="form-label">
                                @icon('fa-sticky-note', ['class' => 'text-muted mr-1'])
                                Note <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">@icon('fa-align-left')</span>
                                </div>
                                <textarea id="assignnote" class="form-control enhanced-textarea" placeholder="Enter a note..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group enhanced-form-group">
                            <label for="popoverdatetime" class="form-label">
                                @icon('fa-calendar', ['class' => 'text-muted mr-1'])
                                Date <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">@icon('fa-calendar-alt')</span>
                                </div>
                                <input type="date" class="form-control enhanced-input" placeholder="yyyy-mm-dd" id="popoverdatetime" value="{{ date('Y-m-d') }}" name="popoverdate">
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="form-group enhanced-form-group">
                            <label for="task_group" class="form-label">
                                @icon('fa-tag', ['class' => 'text-muted mr-1'])
                                Group <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">@icon('fa-list')</span>
                                </div>
                                <select class="form-control enhanced-select" id="task_group" name="task_group">
                                    <option value="">Select Group</option>
                                    <option value="Call">📞 Call</option>
                                    <option value="Checklist">✅ Checklist</option>
                                    <option value="Review">📋 Review</option>
                                    <option value="Query">❓ Query</option>
                                    <option value="Urgent">⚠️ Urgent</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-group enhanced-form-group note_deadline">
                            <div class="row align-items-center">
                                <div class="col-12 col-md-4">
                                    <label class="form-label d-flex align-items-center">
                                        <input class="note_deadline_checkbox mr-2" type="checkbox" id="note_deadline_checkbox" name="note_deadline_checkbox" value="">
                                        @icon('fa-clock', ['class' => 'text-muted mr-1'])
                                        Note Deadline
                                    </label>
                                </div>
                                <div class="col-12 col-md-8">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">@icon('fa-calendar-check')</span>
                                        </div>
                                        <input type="date" class="form-control enhanced-input" placeholder="yyyy-mm-dd" id="note_deadline" value="<?php echo date('Y-m-d');?>" name="note_deadline" disabled>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input id="assign_client_id" type="hidden" value="{{ base64_encode(convert_uuencode(@$fetchedData->id)) }}">
                <input type="hidden" value="" id="popoverrealdate" name="popoverrealdate" />
                
                <div class="modal-footer-buttons">
                    <button class="btn btn-primary btn-lg btn-assign-staff" id="assignStaff">
                        @icon('fa-user-plus', ['class' => 'mr-2'])Assign Staff
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">
                        @icon('fa-times', ['class' => 'mr-2'])Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Change Matter Assignee: Tom Select uses dropdownParent body so positionDropdown() runs; lift above modal/backdrop */
.ts-dropdown.mm-change-matter-assignee-dropdown {
	z-index: 100060 !important;
	box-sizing: border-box;
}
</style>

<!-- Change Matter Assignee Modal -->
<div class="modal fade custom_modal" id="changeMatterAssigneeModal" tabindex="-1" role="dialog" aria-labelledby="change_MatterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="change_MatterModalLabel">Change Matter Assignee</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <form method="post" action="{{URL::to('/clients/updateClientMatterAssignee')}}" name="change_matter_assignee" autocomplete="off" id="change_matter_assignee">
				    @csrf
                    <div class="row">
                        <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                        <input type="hidden" name="user_id" value="{{@Auth::user()->id}}">
                        <input type="hidden" name="selectedMatterLM" id="selectedMatterLM" value="">
                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="migration_agent">Migration Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="migration_agent" id="change_sel_migration_agent_id">
                                    <option value="">Select Migration Agent</option>
                                    @foreach(\App\Models\Staff::assignmentDropdownMigrationAgentsQuery()->get() as $migAgntlist)
                                        <option value="{{$migAgntlist->id}}">{{@$migAgntlist->first_name}} {{@$migAgntlist->last_name}} ({{@$migAgntlist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="person_responsible">Person Responsible <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="person_responsible" id="change_sel_person_responsible_id">
                                    <option value="">Select Person Responsible</option>
                                    @foreach(\App\Models\Staff::assignmentDropdownPersonResponsibleQuery()->get() as $perreslist)
                                        <option value="{{$perreslist->id}}">{{@$perreslist->first_name}} {{@$perreslist->last_name}} ({{@$perreslist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="person_assisting">Person Assisting <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="person_assisting" id="change_sel_person_assisting_id">
                                    <option value="">Select Person Assisting</option>
                                    @foreach(\App\Models\Staff::assignmentDropdownPersonAssistingQuery()->get() as $perassislist)
                                        <option value="{{$perassislist->id}}">{{@$perassislist->first_name}} {{@$perassislist->last_name}} ({{@$perassislist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                                <label for="office_id">Handling Office</label>
                                <select class="form-control mm-select" name="office_id" id="change_office_id">
                                    <option value="">Select Office</option>
                                    @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                        <option value="{{$office->id}}">{{$office->office_name}}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    @icon('fa-building') Optional - Leave blank to keep current office
                                </small>
                            </div>
                        </div>

                        <div class="col-12">
                            <p class="text-muted small mb-2">Adjust the assignees below, then click <strong>Save changes</strong> to update this matter.</p>
                        </div>
                        <div class="col-9 col-md-9 col-lg-9 text-right">
                            <button onclick="customValidate('change_matter_assignee')" type="button" class="btn btn-primary">Save changes</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

