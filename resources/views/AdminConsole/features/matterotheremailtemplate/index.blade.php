@extends('layouts.crm_client_detail')
@section('title', 'Matter Email Template')
 
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
							<h4>Matter Email Templates - {{ $matter->title ?? $matter->nick_name ?? 'Unknown Matter' }}</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.matterotheremailtemplate.create', $matterId)}}" class="btn btn-primary">Create Email Template</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table"> 
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Template Name</th>
										<th>Subject</th>
										<th>Created Date</th>
										<th>Action</th>
									</tr> 
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">	
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
										<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td> 	
										<td>{{ @$list->subject == "" ? config('constants.empty') : Str::limit(@$list->subject, '50', '...') }}</td> 	
										<td>{{ @$list->created_at ? date('d M Y', strtotime(@$list->created_at)) : config('constants.empty') }}</td>
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.matterotheremailtemplate.edit', [$list->id, $matterId])}}">@icon('fa-edit') Edit</a>
													<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'email_templates')">@icon('fa-trash') Delete</a>
												</div>
											</div>								  
										</td>
									</tr>	
								@endforeach	 
								</tbody>
								@else
								<tbody>
									<tr>
										<td style="text-align:center;" colspan="4">
											No Email Templates found for this matter
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