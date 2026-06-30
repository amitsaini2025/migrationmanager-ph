@extends('layouts.crm_client_detail')
@section('title', 'Matter Checklists')

@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ url('upload-checklists/store') }}" name="add-visatype" autocomplete="off" enctype="multipart/form-data" method="POST">
				@csrf
				<div class="row">   
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">

								<h4>
									@if(isset($matter))
										Matter Checklists - {{ $matter->title }} ({{ $matter->nick_name }})
									@else
										Matter Checklists
									@endif
								</h4>
								<div class="card-header-action">
									@if(isset($matter))
										<a href="{{route('adminconsole.features.matter.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back to Matters</a>
									@else
										<a href="{{route('adminconsole.features.documentchecklist.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
									@endif
								</div>
							</div>
						</div>
					</div>
					<div class="col-12 col-md-12 col-lg-12">
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
														<label for="matter_id">Select Matter</label>
														<select name="matter_id" id="matter_id" class="form-control" {{ isset($matter) ? 'disabled' : '' }}>
															<option value="">Select Matter</option>
															@foreach($matterIds as $matterOption)
																<option value="{{ $matterOption->id }}" {{ (isset($matter) && $matter->id == $matterOption->id) ? 'selected' : '' }}>
																	{{ $matterOption->title }} ({{ $matterOption->nick_name }})
																</option>
															@endforeach
														</select>
														@if(isset($matter))
															<input type="hidden" name="matter_id" value="{{ $matter->id }}">
														@endif
													</div>
												</div>					
												<div class="col-12 col-md-4 col-lg-4">
													<div class="form-group"> 
														<label for="name">Checklist Name <span class="span_req">*</span></label>
														<input type="text" name="name" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Name">
														@if ($errors->has('name'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('name') }}</strong>
															</span> 
														@endif
													</div>
												</div>
												<div class="col-12 col-md-4 col-lg-4">
													<div class="form-group"> 
														<label for="name">File <span class="span_req">*</span></label>
														<input data-valid="required" type="file" name="checklists" class="form-control">
														@if ($errors->has('file'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('file') }}</strong>
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
						
						
						<div class="card">
							<div class="card-body">
								<div id="accordion"> 
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
											<h4>Matter Checklists</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											<div class="table-responsive common_table"> 
												<table class="table text_wrap">
													<thead>
														<tr>
															@if(!isset($matter))
																<th>Matter Name</th> 
															@endif
															<th>Checklist Name</th>
															<th>File</th>
															<th>Action</th>
														</tr> 
													</thead>
													@if(@$totalData !== 0)
													<?php $i=0; ?>
													<tbody class="tdata">	
														@foreach (@$lists as $list)
														<tr id="id_{{@$list->id}}">
															@if(!isset($matter))
																<?php
																$matterName = 'NA';
																if( isset($list->matter_id) && $list->matter_id != '') {
																	$matterInfo = \App\Models\Matter::select('id','title','nick_name')->where('id', $list->matter_id)->first();
																	if($matterInfo){
																		$matterName = $matterInfo->title.' ('.$matterInfo->nick_name.')';
																	}
																} ?>
																<td>{{$matterName}}</td> 	
															@endif
															<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td> 	
															<td>
																<a href="{{URL::to('/public/checklists/'.$list->file)}}">File</a>							  
															</td>
															<td>
															<a style="background-color: #FFF;" class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'matter_checklists')">@icon('fa-trash') Delete</a>							  
															</td>
														</tr>	
														@endforeach	 
													</tbody>
													@else
													<tbody>
														<tr>
															<td style="text-align:center;" colspan="{{ isset($matter) ? '3' : '4' }}">
																No Record found
															</td>
														</tr>
													</tbody>
													@endif			
												</table> 
											</div>
										</div>
									</div>
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
    // Handle disabled select field for matter-specific view
    if ($('#matter_id').prop('disabled')) {
        $('#matter_id').css('background-color', '#e9ecef');
    }
});
</script>
@endpush