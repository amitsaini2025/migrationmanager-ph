@extends('layouts.crm_client_detail')
@section('title', 'Edit Tax')

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
							<h4>Edit Tax</h4>
							<div class="card-header-action">
								<a href="{{route('returnsetting')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
							</div>
						</div>
						<form action="{{ url('settings/taxes/savereturnsetting') }}" method="POST" name="add-city" autocomplete="off" enctype="multipart/form-data">
							@csrf
							<input type="hidden" name="id" value="{{ @$fetchedData->id }}">
						<div class="card-body">
							<div class="row">
								<div class="col-12 col-md-6 col-lg-6">
									<div class="form-group"> 
										<label for="name">Tax Name <span class="span_req">*</span></label>
										<input type="text" name="name" value="{{ @$fetchedData->name }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Tax Name">
						
										@if ($errors->has('name'))
											<span class="custom-error" role="alert">
												<strong>{{ @$errors->first('name') }}</strong>
											</span> 
										@endif
									</div>
								</div>
								<div class="col-12 col-md-6 col-lg-6">
									<div class="form-group"> 
										<label for="rate">Rate % <span class="span_req">*</span></label>
										<input type="text" name="rate" value="{{@$fetchedData->rate}}" onkeyup="this.value=this.value.replace(/[^0-9\.]/g,'')" autocomplete="off" class="form-control" data-valid="required" placeholder="Enter Rate">
										@if ($errors->has('rate'))
											<span class="custom-error" role="alert">
												<strong>{{ @$errors->first('rate') }}</strong>
											</span> 
										@endif
									</div>
								</div>
								<div class="col-12">
									<div class="form-group float-right">
										<button type="submit" class="btn btn-primary" onClick="customValidate('add-city')">@icon('fa-save') Update</button>
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