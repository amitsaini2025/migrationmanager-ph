@extends('layouts.crm_client_detail')
@section('title', 'Change Password')
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
							<h4>Change Password</h4>
						</div>
						<form action="{{ url('change_password') }}" method="POST" name="change-password">
							@csrf
							<input type="hidden" name="admin_id" value="{{ @Auth::user()->id }}">
							<div class="card-body">
								<div class="row">
									<div class="col-12 col-md-8 col-lg-8">
										<div class="form-group">
											<label for="old_password">Old Password <span class="span_req">*</span></label>
											<input type="password" name="old_password" class="form-control" data-valid="required" placeholder="Enter current password">
										
											@if ($errors->has('old_password'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('old_password') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="password">New Password <span class="span_req">*</span></label>
											<input type="password" name="password" class="form-control" data-valid="required" placeholder="Enter new password">
										
											@if ($errors->has('password'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('password') }}</strong>
												</span>
											@endif 
										</div>
										<div class="form-group">
											<label for="password_confirmation">Confirm Password <span class="span_req">*</span></label>
											<input type="password" name="password_confirmation" class="form-control" data-valid="required" placeholder="Confirm new password">
										
											@if ($errors->has('password_confirmation'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('password_confirmation') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<button type="submit" class="btn btn-primary px-4" onClick="customValidate('change-password')">@icon('fa-key') Change Password</button>
										</div>
									</div>
								</div>
							</div>    
						</form>	
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
@endsection