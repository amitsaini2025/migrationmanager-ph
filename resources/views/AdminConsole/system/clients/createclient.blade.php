@extends('layouts.crm_client_detail')
@section('title', 'Create Client')

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
			<form action="{{ route('adminconsole.system.clients.storeclient') }}" name="add-client" autocomplete="off" enctype="multipart/form-data" method="POST">
			@csrf
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Add Client</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.system.clients.clientlist')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
								</div>
							</div>
						</div>
					</div>	
					<div class="col-12 col-md-6 col-lg-6">
						<div class="card">
							<div class="card-body">
							<div class="form-group">
								<label for="first_name">First Name</label>
								<input type="text" name="first_name" class="form-control" data-valid="required" autocomplete="off" placeholder="First Name" value="{{ old('first_name') }}">
								@if ($errors->has('first_name'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('first_name') }}</strong>
									</span> 
								@endif
							</div>
							<div class="form-group">
								<label for="last_name">Last Name</label>
								<input type="text" name="last_name" class="form-control" data-valid="required" autocomplete="off" placeholder="Last Name" value="{{ old('last_name') }}">
								@if ($errors->has('last_name'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('last_name') }}</strong>
									</span> 
								@endif
							</div>
							<div class="form-group"> 
								<label for="company_name">Company Name</label>
								<input type="text" name="company_name" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Company Name" value="{{ old('company_name') }}">
								@if ($errors->has('company_name'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('company_name') }}</strong>
									</span> 
								@endif
							</div>
							<div class="form-group">
								<label for="company_website">Company Website</label>
								<input type="text" name="company_website" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Company Website" value="{{ old('company_website') }}">
								@if ($errors->has('company_website'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('company_website') }}</strong>
									</span> 
								@endif
							</div>
							<div class="form-group">
								<label for="email">Email</label>
								<input type="text" name="email" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Email" value="{{ old('email') }}">
								@if ($errors->has('email'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email') }}</strong>
									</span> 
								@endif
							</div>
								<div class="form-group">
									<label for="password">Password</label>
									<input type="password" name="password" class="form-control" autocomplete="off" placeholder="Enter Password" data-valid="required" />							
									@if ($errors->has('password'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('password') }}</strong>
										</span> 
									@endif
								</div> 
							</div>
						</div>
					</div>
					<div class="col-12 col-md-6 col-lg-6">
						<div class="card">
							<div class="card-body">
							<div class="form-group">
								<label for="phone">Phone No.</label>
								<input type="text" name="phone" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Phone Number" value="{{ old('phone') }}">
								@if ($errors->has('phone'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('phone') }}</strong>
									</span> 
								@endif
							</div>
								<div class="form-group">
									<label>Company Logo</label>
									<div class="show-uploded-img" style="width:140px;">
										<img style="width:100%;" src="{{ asset('img/avatar.png') }}" class="img-avatar" alt="Avatar"/>
									</div>
								</div>
								<div class="form-group country_field"> 
									<label for="country" class="">Country <span style="color:#ff0000;">*</span></label>
									<div name="country" class="country_input niceCountryInputSelector" id="basic" data-selectedcountry="IN" data-showspecial="false" data-showflags="true" data-i18nall="All selected" data-i18nnofilter="No selection" data-i18nfilter="Filter" data-onchangecallback="onChangeCallback"></div>		 							
									@if ($errors->has('country'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('country') }}</strong>
										</span> 
									@endif 
								</div>
							<div class="form-group">
								<label for="city">City</label>
								<input type="text" name="city" class="form-control" data-valid="required" autocomplete="off" placeholder="City" value="{{ old('city') }}">
								@if ($errors->has('city'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('city') }}</strong>
									</span> 
								@endif 
							</div>
							<div class="form-group float-right">
								<button type="submit" class="btn btn-primary">Save</button>
							</div>
							</div>
						</div>
					</div>
				</div>
			</form>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
