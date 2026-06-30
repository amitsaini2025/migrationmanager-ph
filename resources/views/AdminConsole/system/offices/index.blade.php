@extends('layouts.crm_client_detail')
@section('title', 'Branches')

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
							<h4>All Branches</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.system.offices.create')}}" class="btn btn-primary">Create Branch</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive"> 
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>Name</th>
											<th>City</th>
											<th>Country</th>
											<th>Mobile</th>
											<th>Phone</th>
											<th>Contact Person</th>
											<th></th>
										</tr> 
									</thead>
									@if(@$totalData !== 0)
									@foreach (@$lists as $list)
									<tbody class="tdata">	
										<tr id="id_{{@$list->id}}">
											<td><a href="{{route('adminconsole.system.offices.view', $list->id)}}">{{ @$list->office_name == "" ? config('constants.empty') : Str::limit(@$list->office_name, '50', '...') }}</a></td> 
											<td>{{ @$list->city == "" ? config('constants.empty') : Str::limit(@$list->city, '50', '...') }}</td> 
											<td>{{ @$list->country == "" ? config('constants.empty') : Str::limit(@$list->country, '50', '...') }}</td> 
											<td>{{ @$list->mobile == "" ? config('constants.empty') : Str::limit(@$list->mobile, '50', '...') }}</td> 
											<td>{{ @$list->phone == "" ? config('constants.empty') : Str::limit(@$list->phone, '50', '...') }}</td> 
											<td>{{ @$list->contact_person == "" ? config('constants.empty') : Str::limit(@$list->contact_person, '50', '...') }}</td> 	
											<td>
												<div class="dropdown d-inline">
													<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
													<div class="dropdown-menu">
														<a class="dropdown-item has-icon" href="{{route('adminconsole.system.offices.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
														<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'branches')">@icon('fa-trash') Delete</a>
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
		</div>
	</section>
</div>

@endsection