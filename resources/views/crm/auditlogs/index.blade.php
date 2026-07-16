@extends('layouts.crm_client_detail')
@section('title', 'Audit Logs')

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
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-header">
							<h4>Audit Logs</h4>
							<div class="card-header-action">
								<a href="{{ route('adminconsole.staff.active') }}" class="btn btn-primary">Back</a>
							</div>
						</div>
						<div class="card-body">
							<form method="GET" action="{{ route('auditlogs.index') }}" class="mb-4">
								<div class="row align-items-end">
									<div class="col-md-4">
										<label for="staff_id" class="font-weight-semibold text-dark">Staff</label>
										<select name="staff_id" id="staff_id" class="form-control">
											<option value="">All Staff</option>
											@foreach($staffList as $staff)
												<option value="{{ $staff->id }}" {{ (string) request('staff_id') === (string) $staff->id ? 'selected' : '' }}>
													{{ trim($staff->first_name . ' ' . $staff->last_name) ?: $staff->email }}
												</option>
											@endforeach
										</select>
									</div>
									<div class="col-md-4">
										<button type="submit" class="btn btn-primary">Filter</button>
										@if(request()->filled('staff_id'))
											<a href="{{ route('auditlogs.index') }}" class="btn btn-light">Clear</a>
										@endif
									</div>
								</div>
							</form>
							<div class="table-responsive common_table">
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>ID</th>
											<th>Staff</th>
											<th>Date & Time</th>
											<th>IP Address</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody class="tdata">
									@if(count($lists) > 0)
										@foreach($lists as $list)
										@php
											$staff = ($list->user_id && isset($staffById[$list->user_id])) ? $staffById[$list->user_id] : null;
											$isActivity = ($list->message === ($activityMessage ?? 'Active in CRM (session)'));
										@endphp
										<tr>
											<td>{{ $list->id }}</td>
											<td>
												@if($staff)
													<a href="#">{{ $staff->first_name }}</a>
												@endif
											</td>
											<td>
												@if($list->created_at)
													{{ date('d/m/Y H:i', strtotime($isActivity ? $list->updated_at : $list->created_at)) }}
												@else
													—
												@endif
											</td>
											<td>
												@if($list->ip_address)
													<a target="_blank" href="https://whatismyipaddress.com/ip/{{ $list->ip_address }}">{{ $list->ip_address }}</a>
												@else
													—
												@endif
											</td>
											<td>
												@if($isActivity)
													<span class="badge badge-info">Via Existing Session</span>
												@else
													<span class="badge badge-primary">Via Login Page</span>
												@endif
											</td>
										</tr>
										@endforeach
									@else
										<tr>
											<td colspan="5" style="text-align:center;">No audit logs found</td>
										</tr>
									@endif
									</tbody>
								</table>
							</div>
						</div>
						<div class="card-footer">{!! $lists->appends(\Request::except('page'))->render() !!}</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
