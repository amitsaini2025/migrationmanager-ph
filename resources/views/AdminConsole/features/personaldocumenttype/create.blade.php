@extends('layouts.crm_client_detail')
@section('title', 'Add Personal Document Category')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ url('adminconsole/features/personal-document-type/store') }}" name="add-create-folder" autocomplete="off" enctype="multipart/form-data" method="POST">
			@csrf
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Add Personal Document Category</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.features.personaldocumenttype.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
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
											<h4>Add Personal Document Category</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">

                                            <div class="row">
												<div class="col-12 col-md-4 col-lg-4">
												<div class="form-group">
													<label for="title">Title <span class="span_req">*</span></label>
													<input type="text" name="title" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Title" value="{{ old('title') }}">
													@if ($errors->has('title'))
														<span class="custom-error" role="alert">
															<strong>{{ @$errors->first('title') }}</strong>
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

