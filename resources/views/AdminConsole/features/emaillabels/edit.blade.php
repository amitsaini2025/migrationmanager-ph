@extends('layouts.crm_client_detail')
@section('title', 'Edit Email Label')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
		<form action="{{ route('adminconsole.features.emaillabels.update', $fetchedData->id) }}" name="edit-email-label" autocomplete="off" enctype="multipart/form-data" method="POST">
			@csrf
			@method('PUT')
				<div class="row">   
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Edit Email Label</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.features.emaillabels.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
								</div>
							</div>
						</div>
					</div>
					<div class="col-3 col-md-3 col-lg-3">
			        	@include('../Elements/CRM/setting')
    		        </div>       
    				<div class="col-9 col-md-9 col-lg-9">
						<div class="card">
							<div class="card-body">
								<div id="accordion"> 
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
											<h4>Primary Information</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											<div class="row"> 						
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group"> 
														<label for="name">Label Name <span class="span_req">*</span></label>
														<input type="text" name="name" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Label Name" value="{{ old('name', @$fetchedData->name) }}" required>
														@if ($errors->has('name'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('name') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group"> 
														<label for="type">Type</label>
														<input type="text" class="form-control" value="{{ @$fetchedData->type == 'system' ? 'System' : 'Custom' }}" disabled>
														<input type="hidden" name="type" value="{{ @$fetchedData->type }}">
														<small class="form-text text-muted">Label type cannot be changed</small>
													</div>
												</div>
												
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group"> 
														<label for="color">Color <span class="span_req">*</span></label>
														<div class="input-group">
															<input type="color" name="color" class="form-control" id="colorPicker" value="{{ old('color', @$fetchedData->color ?: '#3B82F6') }}" style="height: 38px;" required>
															<input type="text" name="color_hex" class="form-control" id="colorHex" value="{{ old('color', @$fetchedData->color ?: '#3B82F6') }}" placeholder="#3B82F6" pattern="^#[0-9A-Fa-f]{6}$" required>
														</div>
														<small class="form-text text-muted">Select a color or enter hex code (e.g., #3B82F6)</small>
														@if ($errors->has('color'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('color') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group"> 
														<label for="icon">Icon</label>
														<input type="text" name="icon" class="form-control" autocomplete="off" placeholder="tag" value="{{ old('icon', @$fetchedData->icon ?: 'tag') }}" list="lucide-icon-suggestions">
														<datalist id="lucide-icon-suggestions">
															<option value="tag"><option value="inbox"><option value="send"><option value="star">
															<option value="flag"><option value="pencil"><option value="trash-2"><option value="archive">
															<option value="briefcase"><option value="user"><option value="triangle-alert">
														</datalist>
														<small class="form-text text-muted">Lucide icon name (e.g. tag, inbox, star). Legacy Font Awesome classes still work.</small>
														@if ($errors->has('icon'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('icon') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group"> 
														<label for="description">Description</label>
														<textarea name="description" class="form-control" rows="3" placeholder="Enter description (optional)">{{ old('description', @$fetchedData->description) }}</textarea>
														@if ($errors->has('description'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('description') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group"> 
														<label for="is_active">Status</label>
														<select name="is_active" class="form-control">
															<option value="1" {{ old('is_active', @$fetchedData->is_active) == 1 ? 'selected' : '' }}>Active</option>
															<option value="0" {{ old('is_active', @$fetchedData->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
														</select>
														@if ($errors->has('is_active'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('is_active') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												
											</div>
										</div>
									</div>
								</div>
							<div class="form-group float-right">
								<button type="submit" class="btn btn-primary">Update</button>
							</div>
							</div>
						</div>	
					</div>
				</div>
			</form>	
		</div>
	</section>
</div>

@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
	// Sync color picker with hex input
	$('#colorPicker').on('change', function() {
		$('#colorHex').val($(this).val());
	});
	
	// Sync hex input with color picker
	$('#colorHex').on('input', function() {
		var hex = $(this).val();
		if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
			$('#colorPicker').val(hex);
		}
	});
	
	// Update hidden color field on form submit
	$('form[name="edit-email-label"]').on('submit', function(e) {
		var hexValue = $('#colorHex').val();
		if (/^#[0-9A-Fa-f]{6}$/.test(hexValue)) {
			$('input[name="color"]').val(hexValue);
		}
	});
});
</script>
@endpush

