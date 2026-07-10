@extends('layouts.crm_client_detail')
@section('title', 'Edit Workflow Stage')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ route('adminconsole.features.workflow.update', base64_encode(convert_uuencode($fetchedData->id))) }}" name="add-visatype" autocomplete="off" enctype="multipart/form-data" method="POST">
				@csrf
				@method('PUT')
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Edit Workflow Stage</h4>
							<div class="card-header-action">
									<a href="{{ isset($workflow) && $workflow ? route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id))) : route('adminconsole.features.workflow.index') }}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
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
											<h4>Edit Workflow Stage</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											@php
												$configFrozen = $fetchedData->isConfigFrozen();
												$stageFrozen = $fetchedData->isFrozen();
											@endphp
											@if($stageFrozen)
											<div class="alert alert-warning" role="alert">
												@if($configFrozen)
												<strong>Protected stage.</strong> This stage cannot be renamed or deleted. It is required for consistent matter workflow behaviour.
												@else
												<strong>Protected stage.</strong> This stage cannot be renamed or deleted while marked Protected. Uncheck Protected below to allow changes.
												@endif
											</div>
											@endif
											<div class="row">
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group">
														<label for="stage_name">Stage Name <span class="span_req">*</span></label>
														<input
															type="text"
															id="stage_name"
															name="stage_name[]"
															placeholder="Stage Name"
															class="form-control"
															value="{{ $fetchedData->name }}"
															@if($stageFrozen) readonly @endif
															required
															maxlength="255"
														>
													</div>
												</div>
												@if(!$configFrozen)
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group mb-0">
														<div class="custom-control custom-checkbox">
															<input
																type="checkbox"
																class="custom-control-input"
																id="is_protected"
																name="is_protected"
																value="1"
																@if($fetchedData->is_protected) checked @endif
															>
															<label class="custom-control-label" for="is_protected">Protected</label>
														</div>
														<small class="form-text text-muted">Protected stages cannot be renamed or deleted.</small>
													</div>
												</div>
												@endif
											</div>
										</div>
									</div>
								</div>
								<div class="form-group float-right">
									@if(isset($workflow) && $workflow)
									<a href="{{ route('adminconsole.features.workflow.createStage', base64_encode(convert_uuencode($workflow->id))) }}?after={{ rawurlencode(base64_encode(convert_uuencode($fetchedData->id))) }}" class="btn btn-info mr-2">@icon('fa-plus') Add stage after this one</a>
									@endif
									@if(!$configFrozen)
									<button type="submit" class="btn btn-primary">Save Workflow Stage</button>
									@endif
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
