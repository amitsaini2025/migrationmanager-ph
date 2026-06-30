<!-- Legacy create_checklist modal removed - functionality moved to adminconsole DocumentChecklist -->
<style>
/* Tom Select menu appended to body while these modals are open; stack above modal + backdrop */
.ts-dropdown.mm-checklist-create-dropdown {
	z-index: 100060 !important;
	width: min(520px, 92vw) !important;
	min-width: 260px !important;
	box-sizing: border-box;
}
</style>

<!-- Add Personal Checklist Modal -->
<div class="modal fade create_education_docs custom_modal" id="openeducationdocsmodal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="taskModalLabel">Add Personal Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-edu-checklist')}}" name="edu_upload_form" id="edu_upload_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="personal">
                    <input type="hidden" name="doccategory" id="doccategory" value="">
                    <input type="hidden" name="folder_name" id="folder_name" value="">

                    <div class="row">
                        <div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="checklist">Select Checklist<span class="span_req">*</span></label>
								<select data-valid="required" class="form-control mm-select" name="checklist[]" id="checklist" multiple>
									<option value="">Select</option>
									<?php
									$eduChkList = \App\Models\DocumentChecklist::where('status',1)->where('doc_type',1)->get();
									foreach($eduChkList as $edulist){
									?>
										<option value="{{$edulist->name}}">{{$edulist->name}}</option>
									<?php
									}
									?>
								</select>
								<span class="custom-error checklist_name_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
                    </div>
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('edu_upload_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Add Visa Checklist Modal -->
<div class="modal fade create_migration_docs custom_modal" id="openmigrationdocsmodal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="taskModalLabel">Add Visa Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-visa-checklist')}}" name="mig_upload_form" id="mig_upload_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="visa">
                    <input type="hidden" name="client_matter_id" id="hidden_client_matter_id" value="">
                    <input type="hidden" name="folder_name" id="visa_folder_name" value="">

					<div class="row">
                        <div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="visa_checklist">Select Checklist<span class="span_req">*</span></label>
								<select data-valid="required" class="form-control mm-select" name="visa_checklist[]" id="visa_checklist" multiple>
									<option value="">Select</option>
									<?php
									$visaChkList = \App\Models\DocumentChecklist::where('status',1)->where('doc_type',2)->get();
									foreach($visaChkList as $visalist){
									?>
										<option value="{{$visalist->name}}">{{$visalist->name}}</option>
									<?php
									}
									?>
								</select>
								<span class="custom-error visa_checklist_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
                    </div>

                    <div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('mig_upload_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Add Nomination Checklist Modal -->
<div class="modal fade create_nomination_docs custom_modal" id="opennominationdocsmodal" tabindex="-1" role="dialog" aria-labelledby="taskModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="taskModalLabel">Add Nomination Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{ URL::to('/documents/add-nomination-checklist') }}" name="nom_upload_checklist_form" id="nom_upload_checklist_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="nomination">
                    <input type="hidden" name="client_matter_id" id="hidden_nomination_client_matter_id" value="">
                    <input type="hidden" name="folder_name" id="nomination_folder_name" value="">

					<div class="row">
                        <div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="nomination_checklist">Select Checklist<span class="span_req">*</span></label>
								<select data-valid="required" class="form-control mm-select" name="nomination_checklist[]" id="nomination_checklist" multiple>
									<option value="">Select</option>
									<?php
									$nomChkList = \App\Models\DocumentChecklist::where('status',1)->where('doc_type',3)->get();
									foreach($nomChkList as $nomlist){
									?>
										<option value="{{$nomlist->name}}">{{$nomlist->name}}</option>
									<?php
									}
									?>
								</select>
								<span class="custom-error nomination_checklist_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
                    </div>

                    <div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('nom_upload_checklist_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- create_checklist modal REMOVED - workflow checklist unused --}}

<!-- Inline Signature Placement Modal (Checklist Agreements) -->
<div class="modal fade" id="signaturePlacementModal" tabindex="-1" role="dialog" aria-labelledby="signaturePlacementModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width: 95%; max-height: 90vh;">
		<div class="modal-content" style="max-height: 90vh;">
			<div class="modal-header bg-warning text-dark">
				<h5 class="modal-title" id="signaturePlacementModalLabel">
					@icon('fa-pen-nib', ['class' => 'mr-2'])Place Signature Fields
				</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4" style="max-height: calc(90vh - 150px); overflow-y: auto;">
				<div id="signature-placement-loading" class="text-center py-5">
					<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
					<p class="mt-2" style="color: #374151;">Loading document...</p>
				</div>
				<div id="signature-placement-content" style="display: none;">
					<div class="row">
						<div class="col-lg-8">
							<div class="mb-2">
								<small style="color: #4b5563;">Document Preview — Click on the document to add signature fields, then drag to position them.</small>
							</div>
							<div id="signature-page-nav" class="mb-3" style="display: none;">
								<div class="btn-group btn-group-sm">
									<button type="button" class="btn btn-outline-secondary" id="sig-prev-page">&larr; Prev</button>
									<span class="px-3 align-self-center" id="sig-page-info">Page 1</span>
									<button type="button" class="btn btn-outline-secondary" id="sig-next-page">Next &rarr;</button>
								</div>
							</div>
							<div class="position-relative d-inline-block border rounded overflow-hidden" id="sig-preview-container" style="min-height: 400px;">
								<img id="sig-preview-image" src="" alt="PDF Preview" style="max-width: 100%; height: auto; display: block;">
								<div id="sig-fields-preview"></div>
							</div>
						</div>
						<div class="col-lg-4">
							<h6 class="font-weight-bold mb-2">Signature Fields</h6>
							<div id="sig-fields-container" class="mb-3" style="max-height: 280px; overflow-y: auto;"></div>
							<button type="button" class="btn btn-outline-primary btn-sm btn-block mb-3" id="sig-add-field">
								@icon('fa-plus', ['class' => 'mr-1'])Add Signature Field
							</button>
						</div>
					</div>
				</div>
				<div id="signature-placement-error" class="alert alert-danger" style="display: none;"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-success" id="sig-save-btn">
					@icon('fa-save', ['class' => 'mr-1'])Save Signature Locations
				</button>
			</div>
		</div>
	</div>
</div>

