@extends('layouts.crm_client_detail')
@section('title', 'Company Profile')
@section('content')

<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg"></div>



            <form action="{{ url('my_profile') }}" name="my-profile" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="id" value="{{ $fetchedData->id }}">

			<div class="row">
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-header">
							<h4>Company Profile</h4>
						</div>
					</div>
				</div>
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-body">
							<div class="row">
								<div class="col-6 col-md-6 col-lg-6">
									<div class="form-group">
										<label for="test_pdf">Company Logo</label>
										<div class="profile_upload">
											<div class="upload_content">
												<img id="output" src="{{ $fetchedData->profile_img }}" style="width:100px;height:100px;" alt="Avatar"/>
											</div>
										</div>
									</div>
										<div class="form-group">
											@if(Auth::user()->role == 3)
												<label for="first_name">Organization Name <span style="color:#ff0000;">*</span></label>
											@else
												<label for="first_name">First Name <span style="color:#ff0000;">*</span></label>
											@endif

											<input type="text" name="first_name" value="{{ old('first_name', @$fetchedData->first_name) }}" class="form-control" data-valid="required">

											@if ($errors->has('first_name'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('first_name') }}</strong>
												</span>
											@endif
										</div>
									@if(Auth::user()->role != 3)
										<div class="form-group">
											<label for="last_name">Last Name <span style="color:#ff0000;">*</span></label>
											<input type="text" name="last_name" value="{{ old('last_name', @$fetchedData->last_name) }}" class="form-control" data-valid="required">

											@if ($errors->has('last_name'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('last_name') }}</strong>
												</span>
											@endif
										</div>
									@endif
										<div class="form-group">
											<label for="email">Company Email <span style="color:#ff0000;">*</span></label>
											<input type="text" name="email" value="{{ old('email', @$fetchedData->email) }}" class="form-control" data-valid="required email" disabled>

											@if ($errors->has('email'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('email') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="phone">Company Phone <span style="color:#ff0000;">*</span></label>
											<input type="text" name="phone" value="{{ old('phone', @$fetchedData->phone) }}" class="form-control mask" data-valid="required" placeholder="000-000-0000">

											@if ($errors->has('phone'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('phone') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="company_name">Company Name <span style="color:#ff0000;">*</span></label>
											<input type="text" name="company_name" value="{{ old('company_name', @$fetchedData->company_name) }}" class="form-control mask" data-valid="required" placeholder="Company Name">

											@if ($errors->has('company_name'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('company_name') }}</strong>
												</span>
											@endif
										</div>
									</div>
									<div class="col-6 col-md-6 col-lg-6">
										<div class="form-group">
											<label for="company_website">Company Website</label>
											<input type="text" name="company_website" value="{{ old('company_website', @$fetchedData->company_website) }}" class="form-control mask" data-valid="" placeholder="Company Website">

											@if ($errors->has('company_website'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('company_website') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="country">Country <span style="color:#ff0000;">*</span></label>
											<select class="form-control mm-select" name="country" >
												<?php
													foreach(\App\Models\Country::all() as $list){
														?>
														<option value="{{@$list->sortname}}" <?php if($fetchedData->country == @$list->sortname){ echo 'selected'; } ?>>{{@$list->name}}</option>
														<?php
													}
													?>
												</select>

											@if ($errors->has('country'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('country') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="state">State <span style="color:#ff0000;">*</span></label>
											<input type="text" name="state" value="{{ old('state', @$fetchedData->state) }}" class="form-control" data-valid="required">

											@if ($errors->has('state'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('state') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="city">City <span style="color:#ff0000;">*</span></label>
											<input type="text" name="city" value="{{ old('city', @$fetchedData->city) }}" class="form-control" data-valid="required">

											@if ($errors->has('city'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('city') }}</strong>
												</span>
											@endif
										</div>
										<div class="form-group">
											<label for="zip">Zip Code <span style="color:#ff0000;">*</span></label>
											<input type="text" name="zip" value="{{ old('zip', @$fetchedData->zip) }}" class="form-control" data-valid="required">

											@if ($errors->has('zip'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('zip') }}</strong>
												</span>
											@endif
										</div>
										<!--<div class="form-group">
											<label for="gst_no">GST No. <span style="color:#ff0000;">*</span></label>
											<input type="text" name="gst_no" value="{{ old('gst_no', @$fetchedData->gst_no) }}" class="form-control" data-valid="required">

											@if ($errors->has('gst_no'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('gst_no') }}</strong>
												</span>
											@endif
										</div>-->
										<div class="form-group">
											<label for="address">Address <span style="color:#ff0000;">*</span></label>
											<input type="text" name="address" value="{{ old('address', @$fetchedData->address) }}" class="form-control" placeholder="Please write Address..." data-valid="required">

											@if ($errors->has('address'))
												<span class="custom-error" role="alert">
													<strong>{{ $errors->first('address') }}</strong>
												</span>
											@endif
										</div>
									</div>
								</div>
								<div class="form-group">
									<button type="button" class="btn btn-primary px-4" onclick='customValidate("my-profile")'>
                                        @icon('fa-edit') Update
                                    </button>

                                </div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</section>
</div>
@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
	$('#select_country').attr('data-selected-country','<?php echo @$fetchedData->country; ?>');
		$('#select_country').flagStrap();
});
</script>
<script>
  var loadFile = function(event) {
    var output = document.getElementById('output');
    output.src = URL.createObjectURL(event.target.files[0]);
    output.onload = function() {
      URL.revokeObjectURL(output.src); // free memory
	  $('.if_image').hide();
	  $('#output').css({'width':"100px",'height':"100px"});
    }
  };
</script>
@endpush
