@extends('layouts.crm_client_detail')
@section('title', 'Add Matter Email Template')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{route('adminconsole.features.matteremailtemplate.store')}}" name="add-matteremailtemplate" autocomplete="off" enctype="multipart/form-data" method="POST">
				<input type="hidden" name="_token" value="{{ csrf_token() }}">
				<input type="hidden" name="matter_id" value="{{ $matterId }}">
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Add Matter Email Template</h4>
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
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											<div class="row">
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group"> 
														<label for="name">Matter Name</label>
														<?php
														$matterName = 'NA';
														if( isset($matterId) && $matterId != '') {
															$matterInfo = \App\Models\Matter::select('id','title','nick_name')->where('id', $matterId)->first();
															if($matterInfo){
																$matterName = $matterInfo->title.' ('.$matterInfo->nick_name.')';
															}
														} ?>
															
														<input type="text" name="matter_name" value="{{$matterName}}" class="form-control" readonly>
													</div>
												</div> 						
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group"> 
														<label for="name">Template Name <span class="span_req">*</span></label>
														<input type="text" name="name" value="" class="form-control" data-valid="required" autocomplete="off" placeholder="">
														@if ($errors->has('name'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('name') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group"> 
														<label for="subject">Mail Subject</label>
														<input type="text" name="subject" value="" class="form-control" data-valid="" autocomplete="off" placeholder="">
														@if ($errors->has('subject'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('subject') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												<div class="col-12 col-md-12 col-lg-12">
													<div class="form-group"> 
														<label for="description">Mail Description</label>
														<textarea class="form-control tinymce-editor" name="description"></textarea>
													</div>
												</div> 
											</div>
										</div>
									</div>
								</div>
								<div class="form-group float-right">
									<button type="button" class="btn btn-primary" onclick='customValidate("add-matteremailtemplate")'>Save</button>
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