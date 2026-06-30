{{-- ========================================
    ALL NOTE-RELATED MODALS
    This file contains all note modals for the client detail page
    ======================================== --}}

{{-- 1. Create Note Modal (Simple) --}}
<!-- Update note Modal -->
<div class="modal fade custom_modal" id="create_note" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Note</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
			<form method="post" action="{{URL::to('/create-note')}}" name="notetermform" autocomplete="off" id="notetermform" enctype="multipart/form-data">
			@csrf
			<input type="hidden" name="client_id" id="note_simple_client_id" value="{{$fetchedData->id}}">
				<input type="hidden" name="noteid" value="">
				<input type="hidden" name="mailid" value="0">
				<input type="hidden" name="vtype" value="client">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="task_group">Type <span class="span_req">*</span></label>
								<select name="task_group" class="form-control" data-valid="required" id="noteTypeSimple">
								    <option value="">Please Select Note</option>
								    <option value="Call">Call</option>
								    <option value="Email">Email</option>
								    <option value="In-Person">In-Person</option>
								    <option value="Others">Others</option>
								    <option value="Attention">Attention</option>
								</select>
								<!-- Container for additional inputs -->
						        <div id="additionalFieldsSimple" class="additional-fields-container"></div>
								<span class="custom-error task_group_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="description">Description <span class="span_req">*</span></label>
								<textarea  class="tinymce-editor" name="description" data-valid="required"></textarea>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<!--<div class="col-12 col-md-12 col-lg-12 is_not_note" style="display:none;">
							
							<div class="form-group">
								<label for="followup_date">Follow-up Date <span class="span_req">*</span></label>
								<input type="date" name="followup_date" class="form-control" data-valid="required">
								<span class="custom-error followup_date_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>-->

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('notetermform')" type="button" class="btn btn-primary">Submit</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2. Create Note with Matter Selection --}}
<!-- Enhanced Create note Modal -->
<div class="modal fade custom_modal" id="create_note_d" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content create-note-modal">
			<div class="modal-header create-note-header">
				<div class="modal-title-section">
					@icon('fa-sticky-note', ['class' => 'text-primary mr-2'])
					<h5 class="modal-title mb-0" id="appliationModalLabel">Create Note</h5>
				</div>
				<div class="modal-actions">
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			</div>
			<div class="modal-body create-note-body">
				<form method="post" action="{{URL::to('/create-note')}}" name="notetermform_n" autocomplete="off" id="notetermform_n" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="client_id" id="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="noteid" value="">
                    <input type="hidden" name="mailid" value="0">
                    <input type="hidden" name="vtype" value="client">
					<div class="row">
                        <div class="col-12 col-md-6">
							<div class="form-group enhanced-form-group">
								<label for="matter_id" class="form-label">
									@icon('fa-folder-open', ['class' => 'text-muted mr-1'])
									Select Matter
								</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text">@icon('fa-list-ul')</span>
                                        </div>
									<select name="matter_id" id="matter_id" class="form-control enhanced-select">
								    <option value="">Select Client Matters</option>
                                    <?php
	                                    // Get all active matters for the client (including sel_matter_id=1 as General Matter)
                                    $matter_list_arr = DB::table('client_matters')
                                    ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
	                                    ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                                    ->where('client_matters.matter_status',1)
                                    ->where('client_matters.client_id',@$fetchedData->id)
	                                    ->orderBy('client_matters.updated_at', 'desc')
                                    ->get();
                                    ?>
								    @foreach($matter_list_arr as $matterlist)
	                                        @php
	                                            // If sel_matter_id is 1 or title is null, use "General Matter"
	                                            $matterName = 'General Matter';
	                                            if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
	                                                $matterName = $matterlist->title;
	                                            }
	                                            
	                                            // Concatenate matter name with client_unique_matter_no if it exists
	                                            if (!empty($matterlist->client_unique_matter_no)) {
	                                                $matterName .= ' (' . $matterlist->client_unique_matter_no . ')';
	                                            }
	                                        @endphp
	                                        <option value="{{$matterlist->id}}">{{$matterName}}</option>
                                    @endforeach
								</select>
								</div>
								<span class="custom-error matter_id_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <input type="hidden" name="title" value="Matter Discussion">

                        <div class="col-12 col-md-6">
							<div class="form-group enhanced-form-group">
								<label for="task_group" class="form-label">
									@icon('fa-tag', ['class' => 'text-muted mr-1'])
									Type <span class="text-danger">*</span>
								</label>
								<div class="input-group">
									<div class="input-group-prepend">
										<span class="input-group-text">@icon('fa-list')</span>
									</div>
									<select name="task_group" class="form-control enhanced-select" data-valid="required" id="noteTypeEnhanced">
                                    <option value="">Please Select</option>
	                                    <option value="Call">📞 Call</option>
	                                    <option value="Email">📧 Email</option>
	                                    <option value="In-Person">👤 In-Person</option>
	                                    <option value="Others">📝 Others</option>
	                                    <option value="Attention">⚠️ Attention</option>
                                </select>
								</div>
                                <!-- Container for additional inputs -->
						        <div id="additionalFieldsEnhanced" class="additional-fields-container"></div>

								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

						<div class="col-12">
							<div class="form-group enhanced-form-group">
								<label for="description" class="form-label">
									@icon('fa-align-left', ['class' => 'text-muted mr-1'])
									Description <span class="text-danger">*</span>
								</label>
								<div class="rich-text-container">
									<textarea class="tinymce-editor enhanced-textarea" id="note_description" name="description" data-valid="required"></textarea>
								</div>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12">
							<div class="modal-footer-buttons">
								<button type="button" class="btn btn-primary btn-lg btn-create-action" data-container="body" data-role="popover" data-placement="bottom" data-html="true">
									@icon('fa-cog', ['class' => 'mr-2'])Create Action
								</button>
								<button onclick="customValidate('notetermform_n')" type="button" class="btn btn-primary btn-lg btn-create-note">
									@icon('fa-save', ['class' => 'mr-2'])Create Note
								</button>
								<button type="button" class="btn btn-outline-secondary btn-lg" data-bs-dismiss="modal">
									@icon('fa-times', ['class' => 'mr-2'])Cancel
								</button>
							</div>
                        </div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 3. View Note Modal --}}
<!-- Note & Terms Modal -->
<div class="modal fade custom_modal" id="view_note" tabindex="-1" role="dialog" aria-labelledby="view_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="note_detail_view"></div>
			</div>
		</div>
	</div>
</div>

{{-- 4. View Matter Note Modal --}}
<div class="modal fade custom_modal" id="view_matter_note" tabindex="-1" role="dialog" aria-labelledby="view_matter_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="matter_note_detail_view" class="note_content">
					<h5></h5>
					<p></p>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- 5. Create Matter Note Modal --}}
<div class="modal fade custom_modal" id="create_matternote" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Matter Note</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/create-app-note')}}" name="appnotetermform" autocomplete="off" id="appnotetermform" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
				<input type="hidden" name="noteid" id="noteid" value="">
				<input type="hidden" name="type" id="type" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="title">Title <span class="span_req">*</span></label>
								<input type="text" name="title" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Title">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="description">Description <span class="span_req">*</span></label>
								<textarea class="tinymce-editor" name="description" data-valid="required"></textarea>
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('appnotetermform')" type="button" class="btn btn-primary">Submit</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- Enhanced CSS Styles for Create Note Modal --}}
<style>
/* Enhanced Create Note Modal Styles */
.create-note-modal {
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    border: none;
    overflow: hidden;
}

.create-note-header {
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

.modal-actions .btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 8px 16px;
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

.create-note-body {
    padding: 30px 25px;
    background: #fafbfc;
}

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

.input-group-text {
    background: #f7fafc;
    border-color: #e2e8f0;
    color: #718096;
    border-radius: 8px 0 0 8px;
    padding: 12px 15px;
}

.enhanced-select {
    border-radius: 0 8px 8px 0;
    border-color: #e2e8f0;
    padding: 12px 15px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.enhanced-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

.rich-text-container {
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: white;
}

.enhanced-textarea {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    min-height: 120px;
}

.modal-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.btn-create-note,
.btn-create-action {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    padding: 12px 30px;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-create-note:hover,
.btn-create-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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

/* Responsive Design */
@media (max-width: 768px) {
    .modal-dialog.modal-lg {
        margin: 10px;
        max-width: calc(100% - 20px);
    }
    
    .create-note-header {
        padding: 15px 20px;
    }
    
    .create-note-body {
        padding: 20px 15px;
    }
    
    .modal-footer-buttons {
        flex-direction: column;
        gap: 10px;
    }
    
    .modal-footer-buttons .btn {
        width: 100%;
    }
}

/* Animation for modal appearance */
.modal.fade .modal-dialog {
    transform: scale(0.8) translateY(-50px);
    transition: all 0.3s ease;
}

.modal.show .modal-dialog {
    transform: scale(1) translateY(0);
}

/* Enhanced focus states */
.enhanced-select:focus,
.enhanced-textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}

/* Loading state for buttons */
.btn-create-note:disabled,
.btn-create-action:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* ========================================
   MODERN SUMMERNOTE EDITOR - GAP FIX + STYLING
   ======================================== */

/* STEP 1: Complete Reset - Remove ALL gaps and borders */
.rich-text-container .note-editor,
.rich-text-container .note-editor *,
.rich-text-container .note-toolbar,
.rich-text-container .note-editing-area,
.rich-text-container .note-editable,
.rich-text-container .card,
.rich-text-container .card-header,
.rich-text-container .card-block {
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    box-shadow: none !important;
}

/* STEP 2: Force Flexbox Layout (prevents gaps) */
.rich-text-container .note-editor.card {
    display: flex !important;
    flex-direction: column !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    overflow: hidden !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

/* STEP 3: Modern Toolbar Styling */
.rich-text-container .card-header.note-toolbar {
    flex: 0 0 auto !important;
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f5 100%) !important;
    border-bottom: 2px solid #e9ecef !important;
    padding: 10px 15px !important;
    border-radius: 0 !important;
}

/* STEP 4: Editing Area Styling */
.rich-text-container .note-editing-area {
    flex: 1 1 auto !important;
    background: white !important;
    border: none !important;
}

.rich-text-container .card-block.note-editable {
    padding: 12px 15px !important;
    min-height: 100px !important;
    line-height: 1.6 !important;
    font-size: 0.95rem !important;
    color: #2d3748 !important;
}

/* STEP 5: Modern Button Styling */
.note-toolbar .note-btn-group {
    margin: 0 6px !important;
    padding: 0 8px !important;
    border-right: 1px solid #dee2e6 !important;
}

.note-toolbar .note-btn-group:last-child {
    border-right: none !important;
}

.note-toolbar .note-btn {
    border: none !important;
    border-radius: 6px !important;
    padding: 8px 12px !important;
    margin: 0 2px !important;
    background: transparent !important;
    color: #4a5568 !important;
    transition: all 0.2s ease !important;
    font-size: 15px !important;
    font-weight: 500 !important;
}

.note-toolbar .note-btn:hover {
    background: #667eea !important;
    color: white !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3) !important;
}

.note-toolbar .note-btn.active {
    background: #667eea !important;
    color: white !important;
    box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3) !important;
}

/* STEP 6: Dropdown Styling */
.note-toolbar .dropdown-menu {
    border-radius: 8px !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    padding: 8px !important;
}

.note-toolbar .dropdown-item:hover {
    background: #667eea !important;
    color: white !important;
    border-radius: 6px !important;
}

/* STEP 7: Focus State */
.rich-text-container .note-editor.note-frame:focus-within,
.rich-text-container .note-editor.editor-focused {
    border-color: #667eea !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
}

/* STEP 8: Remove Status Bar Gap (if exists) */
.note-statusbar {
    display: none !important;
}

/* STEP 9: Placeholder Styling */
.note-editable[contenteditable="true"]:empty:before {
    content: attr(placeholder) !important;
    color: #a0aec0 !important;
    font-style: italic !important;
}

/* STEP 10: Selection Styling */
.note-editable ::selection {
    background: #667eea !important;
    color: white !important;
}

/* STEP 11: Container Wrapper */
.rich-text-container {
    position: relative !important;
    border-radius: 8px !important;
    background: transparent !important;
}

/* STEP 12: Responsive Mobile Styling */
@media (max-width: 768px) {
    .note-toolbar .note-btn {
        padding: 5px 8px !important;
        font-size: 12px !important;
    }
    
    .rich-text-container .card-block.note-editable {
        padding: 10px 12px !important;
        font-size: 14px !important;
    }
    
    .note-toolbar .note-btn-group {
        margin: 0 2px !important;
        padding: 0 4px !important;
    }
}

/* STEP 13: Icon Sizing and Alignment */
.note-toolbar .note-icon-bold,
.note-toolbar .note-icon-italic,
.note-toolbar .note-icon-underline,
.note-toolbar .note-icon-strikethrough {
    font-size: 16px !important;
}

.note-toolbar .note-current-fontname,
.note-toolbar .note-current-style {
    display: none !important;
}

/* Better spacing for toolbar */
.note-toolbar {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    flex-wrap: wrap !important;
}

/* Clean button groups */
.note-toolbar .note-btn-group {
    display: inline-flex !important;
    align-items: center !important;
    gap: 2px !important;
}

/* Color picker button styling */
.note-toolbar .note-color .dropdown-toggle {
    padding: 8px 12px !important;
    border-radius: 6px !important;
}

.note-toolbar .note-color .note-btn {
    position: relative !important;
}

/* Add visual indicators for color buttons */
.note-toolbar .note-color-btn:after {
    content: '' !important;
    position: absolute !important;
    bottom: 2px !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: 20px !important;
    height: 3px !important;
    border-radius: 2px !important;
}

/* Text color indicator */
.note-toolbar .note-btn[data-name="forecolor"]:after {
    background: #667eea !important;
}

/* Highlight color indicator */
.note-toolbar .note-btn[data-name="backcolor"]:after {
    background: #fbbf24 !important;
}

/* Better color palette styling */
.note-color .dropdown-menu {
    padding: 12px !important;
    border-radius: 8px !important;
}

.note-color .note-palette {
    margin: 0 !important;
}

.note-color .note-palette .note-color-row {
    height: 24px !important;
}

.note-color .note-palette .note-color-btn {
    width: 24px !important;
    height: 24px !important;
    margin: 2px !important;
    border: 1px solid rgba(0,0,0,0.1) !important;
    border-radius: 4px !important;
    transition: all 0.2s ease !important;
}

.note-color .note-palette .note-color-btn:hover {
    transform: scale(1.2) !important;
    border: 2px solid #667eea !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2) !important;
}

/* Link button styling */
.note-toolbar .note-btn i {
    margin: 0 !important;
}

/* Better icon for color buttons */
.note-toolbar .note-icon-magic {
    font-size: 14px !important;
}
</style>

{{-- Modern Summernote Enhancement --}}
<script>
$(document).ready(function() {
    // Force fix gaps after Summernote initialization
    setTimeout(function() {
        $('.note-toolbar').css({
            'margin': '0',
            'border': 'none',
            'border-bottom': '2px solid #e9ecef'
        });
        $('.note-editing-area').css({
            'margin': '0',
            'padding': '0',
            'border': 'none'
        });
        $('.note-editable').css({
            'padding': '12px 15px'
        });
    }, 200);

    // Fix gaps when modal opens
    $('#create_note_d').on('shown.bs.modal', function() {
        setTimeout(function() {
            $('.note-toolbar').css('margin-bottom', '0');
            $('.note-editing-area').css('margin-top', '0');
        }, 100);
    });

    // Add focus effects
    $(document).on('focus', '.note-editable', function() {
        $(this).closest('.note-editor').addClass('editor-focused');
    });
    
    $(document).on('blur', '.note-editable', function() {
        $(this).closest('.note-editor').removeClass('editor-focused');
    });
});
</script>