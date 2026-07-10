@extends('layouts.crm_client_detail')
@section('title', 'Add Workflow Stage')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ route('adminconsole.features.workflow.store') }}" name="add-workflow" autocomplete="off" enctype="multipart/form-data" method="POST">
				@csrf
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
						<div class="card-header">
						<h4>
							Add Workflow Stage
							@if(isset($workflow)) to {{ $workflow->name }}@endif
							@if(!empty($insertAfterStage))
							<small class="text-muted font-weight-normal">(after &ldquo;{{ \Illuminate\Support\Str::limit($insertAfterStage->name, 60) }}&rdquo;)</small>
							@endif
						</h4>
							<div class="card-header-action">
									<a href="{{ isset($workflow) ? route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id))) : route('adminconsole.features.workflow.index') }}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
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
											<h4>Add Workflow Stage</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											@if(isset($workflow))
											<input type="hidden" name="workflow_id" value="{{ $workflow->id }}">
											@endif
											@if(!empty($insertAfterStage))
											<input type="hidden" name="after_stage_id" value="{{ $insertAfterStage->id }}">
											<div class="alert alert-info mb-3" role="alert">
												<strong>Insert position:</strong> new stage(s) will be placed <strong>immediately after</strong>
												<em>{{ $insertAfterStage->name }}</em> in this workflow.
											</div>
											@endif
											<div class="row">
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group">
														<!--<label for="stages">Workflow Stages <span class="span_req">*</span></label>-->
														<div class="workflow_stges">
															<table class="table">
																<thead>
																	<tr>
																		<th>Stage Name</th>
																		<th class="text-nowrap">Protected</th>
																		<th></th>
																	</tr>
																</thead>
																<tbody>
																<tr>
																	<td>
																		<input data-valid="required" type="text" name="stage_name[]" placeholder="Stage Name" class="form-control">
																	</td>
																	<td class="align-middle">
																		<div class="custom-control custom-checkbox">
																			<input type="checkbox" class="custom-control-input" id="is_protected_0" name="is_protected[0]" value="1">
																			<label class="custom-control-label" for="is_protected_0">Protected</label>
																		</div>
																	</td>
																	<td></td>
																</tr>
																</tbody>
															</table>
														</div>
														<div class="">
															<a href="javascript:;" class="add_stage btn btn-info">Add Stage</a>
														</div>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="form-group float-right">
									<button type="submit" class="btn btn-primary">Save Workflow Stage</button>
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
	var stageRowIndex = 1;

	$('.add_stage').on('click', function(){
		var rowIndex = stageRowIndex++;
		var checkboxId = 'is_protected_' + rowIndex;
		var html = '<tr>'+
            '<td><input type="text" data-valid="required" name="stage_name[]" placeholder="Stage Name" class="form-control"></td>'+
            '<td class="align-middle"><div class="custom-control custom-checkbox">'+
				'<input type="checkbox" class="custom-control-input" id="' + checkboxId + '" name="is_protected[' + rowIndex + ']" value="1">'+
				'<label class="custom-control-label" for="' + checkboxId + '">Protected</label>'+
			'</div></td>'+
            '<td><a href="javascript:;" class="remove_stage">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash') : '<i class="fas fa-trash"></i>') + '</a></td>'+
        '</tr>';
        $('.workflow_stges table tbody').append(html);
	});

	$(document).delegate('.remove_stage', 'click', function(){
		$(this).closest('tr').remove();
	});
});
</script>
@endpush
