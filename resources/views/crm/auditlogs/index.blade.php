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
											<th>Level</th>
											<th>Date</th>
											<th>Staff</th>
											<th>IP Address</th>
											<th>Message</th>
										</tr>
									</thead>
									<tbody class="tdata">
									@if(count($lists) > 0)
										@foreach($lists as $list)
										<tr>
											<td>{{$list->id}}</td>
											<td>
												@if($list->level == 'info')
													<span class="badge badge-success">Info</span>
												@elseif($list->level == 'critical')
													<span class="badge badge-danger">Critical</span>
												@elseif($list->level == 'warning')
													<span class="badge badge-warning">Warning</span>
												@endif
											</td>
											<td>{{date('d/m/Y', strtotime($list->created_at))}}</td>
											<td>
											<?php
											if($list->user_id != ''){
												$staff = \App\Models\Staff::where('id', $list->user_id)->first();
												if($staff){
												?>
												<a href="#">{{$staff->first_name}}</a>
												<?php
												}
											}
											?>
											</td>
											<td><a target="_blank" href="https://whatismyipaddress.com/ip/{{$list->ip_address}}">{{$list->ip_address}}</a></td>
											<td>{{$list->message}}</td>
										</tr>
										@endforeach
									@else
										<tr>
											<td colspan="6" style="text-align:center;">No audit logs found</td>
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
