@extends('layouts.crm_client_detail')
@section('title', 'Teams')

@section('content')

<div class="crm-container">
	<div class="main-content">
	    @if(@$fetchedData)
			<form action="{{ route('adminconsole.system.teams.update', $fetchedData->id) }}" method="POST" name="add-visatype" autocomplete="off" enctype="multipart/form-data">
		    	@csrf
		    	@method('PUT')
		@else
		<form action="{{ route('adminconsole.system.teams.store') }}" method="POST" name="add-visatype" autocomplete="off" enctype="multipart/form-data">
		@csrf
		@endif
				<div class="row">   
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Checklists</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.system.teams.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
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
														<input type="text" name="name" id="name" value="{{ old('name', $fetchedData->name ?? '') }}" 
														       class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Name">
														@if ($errors->has('name'))
															<span class="custom-error" role="alert">
																<strong>{{ @$errors->first('name') }}</strong>
															</span> 
														@endif
													</div>
												</div>
										<div class="col-12 col-md-2 col-lg-2">
													<div class="form-group"> 
														<label for="name">Color <span class="span_req">*</span></label>
													<input data-valid="required" type="color" value="{{@$fetchedData->color}}" name="color" class="">
													
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
											<h4>Teams</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											<div class="table-responsive common_table"> 
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Name</th>
										<th>Color</th>
										<th>Action</th>
									</tr> 
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">	
								@foreach (@$lists as $list)
								
									<tr id="id_{{@$list->id}}">
										
										<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td> 	
										
										<td>
											<span style="background-color:{{$list->color}};width: 40px;display: inline-block;">&nbsp;</span>						  
										</td>
										<td>
										<a class="dropdown-item has-icon" href="{{route('adminconsole.system.teams.edit', $list->id)}}" >@icon('fa-edit') Edit</a>							  
										</td>
									</tr>	
								@endforeach	 
								</tbody>
								@else
								<tbody>
									<tr>
										<td style="text-align:center;" colspan="7">
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
</div>

@endsection