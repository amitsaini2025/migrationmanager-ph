{{-- openfileuploadmodal REMOVED - workflow checklist upload flow dead (no workflow UI with upload triggers; Client Portal refactored) --}}

<!-- Add Personal Document Category Modal -->
<div class="modal fade addpersonaldoccatmodel custom_modal" id="addpersonaldoccatmodel" tabindex="-1" role="dialog" aria-labelledby="addPersDocCatModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="addPersDocCatModalLabel">Add Personal Document Category</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-personal-category')}}" name="add_pers_doc_cat_form" id="add_pers_doc_cat_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="personal_doc_category">Category<span class="span_req">*</span></label>
								<input type="text" class="form-control" name="personal_doc_category" id="personal_doc_category" data-valid="required">

								<span class="custom-error personal_doc_category_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('add_pers_doc_cat_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Add Visa Document Category Modal -->
<div class="modal fade addvisadoccatmodel custom_modal" id="addvisadoccatmodel" tabindex="-1" role="dialog" aria-labelledby="addVisaDocCatModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="addVisaDocCatModalLabel">Add Visa Document Category</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-visa-category')}}" name="add_visa_doc_cat_form" id="add_visa_doc_cat_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
					<input type="hidden" name="clientmatterid" id="visaclientmatterid" value="">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="visa_doc_category">Category<span class="span_req">*</span></label>
								<input type="text" class="form-control" name="visa_doc_category" id="visa_doc_category" data-valid="required">

								<span class="custom-error visa_doc_category_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('add_visa_doc_cat_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Add Nomination Document Category Modal -->
<div class="modal fade addnominationdoccatmodel custom_modal" id="addnominationdoccatmodel" tabindex="-1" role="dialog" aria-labelledby="addNominationDocCatModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="addNominationDocCatModalLabel">Add File Document Category</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/documents/add-nomination-category')}}" name="add_nom_doc_cat_form" id="add_nom_doc_cat_form" autocomplete="off"  enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="clientid" value="{{$fetchedData->id}}">
					<input type="hidden" name="clientmatterid" id="nominationclientmatterid" value="">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="nomination_doc_category">Category<span class="span_req">*</span></label>
								<input type="text" class="form-control" name="nomination_doc_category" id="nomination_doc_category" data-valid="required">

								<span class="custom-error nomination_doc_category_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('add_nom_doc_cat_form')" type="button" class="btn btn-primary" style="margin: 0px !important;">Create</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

