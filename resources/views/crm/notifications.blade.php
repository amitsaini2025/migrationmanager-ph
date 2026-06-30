@extends('layouts.crm_client_detail')
@section('title', 'Notifications')

@section('content')

<style>
/* Status column: CSS-based indicators that always display (no Font Awesome dependency) */
.notification-status-read,
.notification-status-unread {
	display: inline-block;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	vertical-align: middle;
}
.notification-status-read { background-color: #28a745; }
.notification-status-unread { background-color: #007bff; }
</style>

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
							<h4>Notifications</h4>
							<div class="card-header-action">
								<span class="badge badge-primary">{{ $lists->total() }} Total</span>
							</div>
						</div>
						<div class="card-body">
							@if($lists->count() > 0)
							<div class="table-responsive">
								<table class="table">
									<thead>
										<tr>
										  <th width="50">Status</th>
										  <th>Message</th>
										  <th width="200">Date</th>
										</tr> 
									</thead>
									<tbody class="tdata">	
										@foreach ($lists as $list)
										<tr id="id_{{@$list->id}}" style="{{ ($list->receiver_status ?? 0) == 0 ? 'background-color: #f8f9fa; font-weight: 600;' : '' }}"> 
											<td class="text-center">
												@if(($list->receiver_status ?? 0) == 1)
													<span class="notification-status-read" data-bs-toggle="tooltip" title="Read" aria-label="Read"></span>
												@else
													<span class="notification-status-unread" data-bs-toggle="tooltip" title="Unread" aria-label="Unread"></span>
												@endif
											</td>
											<td>
												<a href="{{$list->url}}?t={{$list->id}}">
													{{$list->message}}
												</a>
											</td> 
											<td>
												{{date('d/m/Y h:i A', strtotime($list->created_at))}}
											</td>
										</tr>	
										@endforeach	
									</tbody>
								</table>
							</div>
							@else
							<div class="text-center" style="padding: 40px;">
								@icon('fa-bell', ['style' => 'width: 48px; height: 48px; color: #ddd;'])
								<h5 class="mt-3">No Notifications</h5>
								<p class="text-muted">You don't have any notifications yet.</p>
							</div>
							@endif
						</div>
						@if($lists->count() > 0)
						<div class="card-footer">
							{!! $lists->appends(\Request::except('page'))->render() !!}
						</div>
						@endif
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection