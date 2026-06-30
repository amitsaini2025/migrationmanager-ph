@extends('layouts.crm_client_detail')
@section('title', 'Tags')
 
@section('content')

<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
			        	@include('../Elements/CRM/setting')
		        </div>       
				<div class="col-9 col-md-9 col-lg-9">
					<div class="card">
						<div class="card-header">
							<h4>Tags</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.tags.create')}}" class="btn btn-primary">Create Tag</a>
							</div>
						</div>
						<div class="card-body">
							<!-- Filter Section -->
							<div class="row mb-3">
								<div class="col-md-4">
									<form method="GET" action="{{ route('adminconsole.features.tags.index') }}">
										<div class="form-group">
											<label for="tag_type_filter">Filter by Type:</label>
											<select name="tag_type" id="tag_type_filter" class="form-control" onchange="this.form.submit()">
												<option value="">All Tags</option>
												<option value="normal" {{ request('tag_type') == 'normal' ? 'selected' : '' }}>Normal Tags</option>
												<option value="red" {{ request('tag_type') == 'red' ? 'selected' : '' }}>Red Tags</option>
											</select>
										</div>
									</form>
								</div>
							</div>
							
							<div class="table-responsive common_table"> 
								<table class="table text_wrap">
								<thead>
									<tr>
										
										<th>Name</th>
										<th>Type</th>
										<th>Created By</th>
										<th>Last Updated By</th>
										<th>Last Updated On</th>
										<th></th>
									</tr> 
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">	
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}" class="{{ @$list->tag_type == 'red' ? 'table-danger' : '' }}">
										
										<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td> 	
										<td>
											@if(@$list->tag_type == 'red')
												<span class="badge badge-danger">@icon('fa-tag') Red Tag</span>
											@else
												<span class="badge badge-primary">@icon('fa-tag') Normal</span>
											@endif
										</td>
										<td>{{@$list->createddetail->first_name}}</td> 	
										<td>{{@$list->updateddetail->first_name}}</td> 	
										<td>@if($list->created_at != '') {{date('Y-m-d', strtotime($list->created_at))}} @else - @endif</td> 	
										
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.tags.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
													<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'tags')">@icon('fa-trash') Delete</a>
													
												</div>
											</div>								  
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
	</section>
</div>
 
@endsection
@push('scripts') 
<script>
jQuery(document).ready(function($){	
	$('.cb-element').change(function () {		
	if ($('.cb-element:checked').length == $('.cb-element').length){
	  $('#checkbox-all').prop('checked',true);
	}
	else {
	  $('#checkbox-all').prop('checked',false);
	}
	/* if ($('.cb-element:checked').length > 0){
			$('.is_checked_client').show();
			$('.is_checked_clientn').hide();
		}else{
			$('.is_checked_client').hide();
			$('.is_checked_clientn').show();
		} */
	});	
});	
</script>
@endpush