@extends('layouts.crm_client_detail')
@section('title', 'Staff')

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
							<h4>Staff</h4>
							<div class="card-header-action">
								<a href="{{ route('auditlogs.index') }}" class="btn btn-primary mr-2">Audit Logs</a>
								<a href="{{ route('adminconsole.staff.create') }}" class="btn btn-primary">Add Staff</a>
							</div>
						</div>
						<div class="card-body">
							<ul class="nav nav-pills" id="staff_tabs" role="tablist">
								<li class="nav-item">
									<a class="nav-link" id="active-tab" href="{{ route('adminconsole.staff.active') }}">Active</a>
								</li>
								<li class="nav-item">
									<a class="nav-link active" id="inactive-tab" href="{{ route('adminconsole.staff.inactive') }}">Inactive</a>
								</li>
								<li class="nav-item">
									<a class="nav-link" id="invited-tab" href="{{ route('adminconsole.staff.invited') }}">Invited</a>
								</li>
							</ul>
							<div class="tab-content" id="checkinContent">
								<div class="tab-pane fade show active" id="inactive" role="tabpanel" aria-labelledby="inactive-tab">
									<div class="table-responsive common_table">
										<table class="table">
											<thead>
												<tr>
													<th>Name</th>
													<th>Position</th>
													<th>Office</th>
													<th>Role</th>
													<th>Status</th>
												</tr>
											</thead>
											@if(@$totalData !== 0)
												@foreach ($lists as $list)
													@php $b = $list->office; @endphp
													<tbody class="tdata">
														<tr id="id_{{ $list->id }}">
															<td><a href="{{ route('adminconsole.staff.view', $list->id) }}">{{ $list->first_name }} {{ $list->last_name }}</a><br>{{ $list->email }}</td>
															<td>{{ $list->position }}</td>
															<td>
																@if($b && $b->id)
																	<a href="{{ route('adminconsole.system.offices.view', $b->id) }}">{{ $b->office_name }}</a>
																@else
																	<span class="text-muted">N/A</span>
																@endif
															</td>
															<td>{{ optional($list->usertype)->name ?: config('constants.empty') }}</td>
															<td>
																<div class="custom-switches">
																	<label class="">
																		<input value="1" data-id="{{ $list->id }}" data-status="{{ $list->status }}" data-col="status" data-table="staff" type="checkbox" name="custom-switch-checkbox" class="change-status custom-switch-input" {{ $list->status == 1 ? 'checked' : '' }}>
																		<span class="custom-switch-indicator"></span>
																	</label>
																</div>
															</td>
														</tr>
													</tbody>
												@endforeach
											@else
												<tbody>
													<tr>
														<td style="text-align:center;" colspan="5">No Record found</td>
													</tr>
												</tbody>
											@endif
										</table>
									</div>
								</div>
							</div>
						</div>
						<div class="card-footer">
							{!! $lists->appends(Request::except('page'))->render() !!}
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
