@extends('layouts.crm_client_detail')
@section('title', 'Edit Matter Email Template')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<?php $effectiveMatterId = isset($matterId) && $matterId != '' ? $matterId : ($fetchedData->matter_id ?? null); ?>
			<form action="{{ route('adminconsole.features.matteremailtemplate.update', $fetchedData->id) }}" method="POST" name="edit-matteremailtemplate" autocomplete="off" enctype="multipart/form-data">
				@csrf
				@method('PUT')
				<input type="hidden" name="matter_id" value="{{ $effectiveMatterId ?? $fetchedData->matter_id ?? '' }}">
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Edit Matter Email Template</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.features.matter.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
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
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group">
														<label for="name">Matter Name</label>
														<?php
														$matterName = 'NA';
														if ($effectiveMatterId) {
															$matterInfo = \App\Models\Matter::select('id','title','nick_name')->where('id', $effectiveMatterId)->first();
															if($matterInfo){
																$matterName = $matterInfo->title . (isset($matterInfo->nick_name) && $matterInfo->nick_name ? ' (' . $matterInfo->nick_name . ')' : '');
															}
														} ?>

														<input type="text" name="matter_name" value="{{$matterName}}" class="form-control" readonly>
													</div>
												</div>

												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group">
														<label for="name">Template Name <span class="span_req">*</span></label>
														<input type="text" name="name" value="{{ @$fetchedData->name }}" class="form-control" data-valid="required" autocomplete="off" placeholder="">
														@if ($errors->has('name'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('name') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group">
														<label for="subject">Email Subject</label>
														<input type="text" name="subject" class="form-control" data-valid="" autocomplete="off" placeholder="" value="{{ @$fetchedData->subject }}">
														@if ($errors->has('subject'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('subject') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group">
														<label for="description">Email Description</label><br>
														<textarea class="form-control tinymce-editor" name="description">{{@$fetchedData->description}}</textarea>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="form-group float-right">
									<button type="button" class="btn btn-primary" onClick='customValidate("edit-matteremailtemplate")'>Update</button>
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
