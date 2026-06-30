@extends('layouts.crm_client_detail')
@section('title', 'Tags')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ url('adminconsole/features/tags/store') }}" name="add-visatype" autocomplete="off" enctype="multipart/form-data" method="POST">
			@csrf 
				<div class="row">   
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Tags</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.features.tags.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
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
												<div class="col-12 col-md-4 col-lg-4">
												<div class="form-group"> 
													<label for="name">Name <span class="span_req">*</span></label>
													<input type="text" name="name" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Name" value="{{ old('name') }}">
													@if ($errors->has('name'))
														<span class="custom-error" role="alert">
															<strong>{{ @$errors->first('name') }}</strong>
														</span> 
													@endif
												</div>
												</div>
												
												<div class="col-12 col-md-4 col-lg-4">
												<div class="form-group"> 
													<label for="tag_type">Tag Type <span class="span_req">*</span></label>
													<select name="tag_type" class="form-control" data-valid="required">
														<option value="normal" {{ old('tag_type', 'normal') == 'normal' ? 'selected' : '' }}>Normal Tag</option>
														<option value="red" {{ old('tag_type') == 'red' ? 'selected' : '' }}>Red Tag (Hidden)</option>
													</select>
													<small class="form-text text-muted">Red tags are hidden by default and only visible when toggled on client detail pages.</small>
													@if ($errors->has('tag_type'))
														<span class="custom-error" role="alert">
															<strong>{{ @$errors->first('tag_type') }}</strong>
														</span> 
													@endif
												</div>
												</div>
												
											</div>
										</div>
									</div>
								</div>
							<div class="form-group float-right">
								<button type="submit" class="btn btn-primary">Save</button>
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