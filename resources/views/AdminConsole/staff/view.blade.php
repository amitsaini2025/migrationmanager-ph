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
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-header">
							<h4>View Staff</h4>
							<div class="card-header-action">
								<a href="{{ route('adminconsole.staff.edit', $fetchedData->id) }}" class="btn btn-primary">@icon('fa-edit') Edit</a>
								<a href="{{ route('adminconsole.staff.active') }}" class="btn btn-secondary">@icon('fa-arrow-left') Back</a>
							</div>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-6 col-lg-6">
					<div class="card">
						<div class="card-body">
							<h4>PERSONAL DETAILS</h4>
							<div class="form-group">
								<label for="first_name"><strong>First Name</strong></label>
								<p class="form-control-plaintext">{{ $fetchedData->first_name ?? 'N/A' }}</p>
							</div>
							<div class="form-group">
								<label for="last_name"><strong>Last Name</strong></label>
								<p class="form-control-plaintext">{{ $fetchedData->last_name ?? 'N/A' }}</p>
							</div>
							<div class="form-group">
								<label for="email"><strong>Email</strong></label>
								<p class="form-control-plaintext">{{ $fetchedData->email ?? 'N/A' }}</p>
							</div>
							<div class="form-group">
								<label for="phone"><strong>Phone Number</strong></label>
								<p class="form-control-plaintext">
									@if(!empty($fetchedData->phone))
										{{ $fetchedData->country_code }} {{ $fetchedData->phone }}
									@else
										N/A
									@endif
								</p>
							</div>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-6 col-lg-6">
					<div class="card">
						<div class="card-body">
							<h4>OFFICE DETAILS</h4>
							<div class="form-group">
								<label for="position"><strong>Position Title</strong></label>
								<p class="form-control-plaintext">{{ $fetchedData->position ?? 'N/A' }}</p>
							</div>
							<div class="form-group">
								<label for="role"><strong>User Role (Type)</strong></label>
								<p class="form-control-plaintext">{{ optional($fetchedData->usertype)->name ?? 'N/A' }}</p>
							</div>
							<div class="form-group">
								<label for="office"><strong>Office</strong></label>
								<p class="form-control-plaintext">{{ optional($fetchedData->office)->office_name ?? 'N/A' }}</p>
							</div>
							<div class="form-group">
								<label for="team"><strong>Department (Team)</strong></label>
								<p class="form-control-plaintext">
									<?php
									if($fetchedData->team != ""){
										$teamData = \App\Models\Team::select('name')->where('id', '=', $fetchedData->team)->first();
										$teamname = $teamData ? $teamData->name : "N/A";
									} else {
										$teamname = "N/A";
									}
									?>
									{{ $teamname }}
								</p>
							</div>
							<div class="form-group">
								<label for="permission"><strong>Permission</strong></label>
								<div class="form-control-plaintext">
									<?php
									if( isset($fetchedData->permission) && $fetchedData->permission !="")
									{
										if( strpos($fetchedData->permission,",") ){
											$permission_arr =  explode(",",$fetchedData->permission);
										} else {
											$permission_arr = [$fetchedData->permission];
										}
										?>
										<div><strong>Notes:</strong> 
											@if(in_array(1, $permission_arr)) <span class="badge badge-success">View</span> @endif
											@if(in_array(2, $permission_arr)) <span class="badge badge-success">Add/Edit</span> @endif
											@if(in_array(3, $permission_arr)) <span class="badge badge-success">Delete</span> @endif
										</div>
										<div class="mt-2"><strong>Documents:</strong> 
											@if(in_array(4, $permission_arr)) <span class="badge badge-info">View</span> @endif
											@if(in_array(5, $permission_arr)) <span class="badge badge-info">Add/Edit</span> @endif
											@if(in_array(6, $permission_arr)) <span class="badge badge-info">Delete</span> @endif
										</div>
									<?php
									}
									else
									{
									?>
										<span class="text-muted">No permissions assigned</span>
									<?php
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label for="sheet_access"><strong>CRM sheets</strong></label>
								<p class="form-control-plaintext">
									@php
										$rawSheetsView = $fetchedData->sheet_access ?? null;
										$sheetDefsView = \App\Support\CrmSheets::definitions();
										$sheetListView = is_string($rawSheetsView) ? json_decode($rawSheetsView, true) : null;
									@endphp
									@if($rawSheetsView === null || $rawSheetsView === '')
										<span class="badge badge-secondary">All sheets</span>
									@elseif(! is_array($sheetListView) || count($sheetListView) === 0)
										<span class="text-muted">None</span>
									@else
										@foreach($sheetListView as $sk)
											<span class="badge badge-primary mr-1 mb-1">{{ $sheetDefsView[$sk] ?? $sk }}</span>
										@endforeach
									@endif
								</p>
							</div>
							<div class="form-group">
								<label for="show_dashboard_per"><strong>Dashboard View Permission</strong></label>
								<p class="form-control-plaintext">
									@if($fetchedData->show_dashboard_per == 1)
										<span class="badge badge-success">Can view dashboard</span>
									@else
										<span class="badge badge-secondary">Cannot view dashboard</span>
									@endif
								</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Migration Agent Details Section -->
				@if($fetchedData->is_migration_agent == 1)
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-body">
							<h4 class="text-primary">
								@icon('fa-user-tie') MIGRATION AGENT DETAILS
							</h4>
							<hr>

							<div class="row">
								<div class="col-md-6">
									<h6 class="text-muted mt-3">Registration Details</h6>
									<div class="form-group">
										<label for="marn_number"><strong>MARN Number</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->marn_number ?? 'N/A' }}</p>
									</div>
									<div class="form-group">
										<label for="legal_practitioner_number"><strong>Legal Practitioner Number</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->legal_practitioner_number ?? 'N/A' }}</p>
									</div>
								</div>

								<div class="col-md-6">
									<h6 class="text-muted mt-3">Business Details</h6>
									<div class="form-group">
										<label for="company_name"><strong>Business Name</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->company_name ?? 'N/A' }}</p>
									</div>
									<div class="form-group">
										<label for="tax_number"><strong>Tax Number (ABN/ACN)</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->tax_number ?? 'N/A' }}</p>
									</div>
									<div class="form-group">
										<label for="business_address"><strong>Business Address</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->business_address ?? 'N/A' }}</p>
									</div>
								</div>
							</div>

							<div class="row mt-3">
								<div class="col-md-12">
									<h6 class="text-muted">Contact Details</h6>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="business_phone"><strong>Business Phone</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->business_phone ?? 'N/A' }}</p>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="business_mobile"><strong>Business Mobile</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->business_mobile ?? 'N/A' }}</p>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="business_email"><strong>Business Email</strong></label>
										<p class="form-control-plaintext">{{ $fetchedData->business_email ?? 'N/A' }}</p>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				@endif
			</div>
		</div>
	</section>
</div>
@endsection
