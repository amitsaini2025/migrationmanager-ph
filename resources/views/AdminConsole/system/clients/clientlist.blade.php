@extends('layouts.crm_client_detail')
@section('title', 'Client List')

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
							<h4>Client List</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.system.clients.createclient')}}" class="btn btn-primary">Create Client</a>
							</div>
						</div>
						<div class="card-body">
							<table class="table">
								<thead>
									<tr>
										<th>Company Name</th>
										<th>Owner Name</th>
										<th>Email</th>
										<th>Phone</th>
										<th>Is Active</th>
										<th></th>
									</tr> 
								</thead>
								<tbody class="tdata">	
									@if(@$totalData !== 0)
									@foreach (@$lists as $list)	
									<tr id="id_{{@$list->id}}"> 
										<td>{{ @$list->company_name == "" ? config('constants.empty') : Str::limit(@$list->company_name, '50', '...') }}</td> 
										<td>{{ @$list->first_name == "" ? config('constants.empty') : Str::limit(@$list->first_name, '50', '...') }} {{ @$list->last_name == "" ? config('constants.empty') : Str::limit(@$list->last_name, '50', '...') }}</td> 
										<td>{{ @$list->email == "" ? config('constants.empty') : Str::limit(@$list->email, '50', '...') }}</td> 
										<td>{{ @$list->phone == "" ? config('constants.empty') : Str::limit(@$list->phone, '50', '...') }}</td>
										<td>
											<label class="custom-switch">
												<input type="checkbox" name="is_active" class="custom-switch-input" data-id="{{@$list->id}}"  data-status="{{@$list->status}}" data-col="status" data-table="admins" name="option" {{ (@$list->status == 1 ? 'checked' : '')}}>
												<span class="custom-switch-indicator"></span>
											</label>
										</td>
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.system.clients.editclient', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
													<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'admins')">@icon('fa-trash') Delete</a>
												</div>
											</div>								  
										</td>
									</tr>	
								@endforeach	
								</tbody>
								@else
								<tbody> 
									<tr>
										<td style="text-align:center;" colspan="6">
											No Record found
										</td>
									</tr>
								</tbody>
								@endif
							</table> 
						</div>
						<div class="card-footer">
							{!! $lists->appends(\Request::except('page'))->render() !!}
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
