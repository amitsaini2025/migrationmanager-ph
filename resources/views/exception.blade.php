<!DOCTYPE html>
<html lang="en">
	<head>
		<base href="./">
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
		<meta name="description" content="ApnaMentor for higher education">
		<meta name="author" content="Raghav Garg">
		<meta name="keyword" content="Bootstrap,Admin,Template,Open,Source,jQuery,CSS,HTML,RWD,Dashboard">
		<meta name="csrf-token" content="{{ csrf_token() }}">
		<title>Tour Planner | Exception</title>

		<!-- Main styles for this application-->
			<link rel="stylesheet" type="text/css" href="{{ asset('css/style.css') }}" />
			<link rel="stylesheet" type="text/css" href="{{ asset('css/pace.min.css') }}" />
			<link rel="stylesheet" type="text/css" href="{{ asset('css/admin.css') }}" />
	</head>
	<body class="app flex-row align-items-center">
		<div id="loader">
			<div class="loading_image">
				<div class="valid">
					<img src="{{ asset('img/loading.gif') }}" alt="">
				</div>
			</div>
		</div>
		<div class="container">
			<div class="row justify-content-center">
				<div class="col-md-8">
					@include('Elements/flash-message')
					<div class="card-group">
						<div class="card p-4">
							{!! html()->form('POST', url('/exception'))->attribute('name', 'exception')->open() !!}
								<div class="card-body">
									<h1>Exception</h1>
									<div class="input-group mb-3">
										<textarea class="form-control" name="comment" placeholder="Please write comment, what did you face." data-valid="required"></textarea>	
									</div>
									<div class="row">
										<div class="col-6">
											{!! html()->button('Post')->class('btn btn-primary px-4')->attribute('onClick', 'customValidate("exception")') !!}	
										</div>
									</div>
								</div>
							{!! html()->closeModel('form') !!}
						</div>
						<div class="card text-white bg-primary py-5 d-md-down-none" style="width:44%">
							<div class="card-body text-center">
								<div>
									<p>Please write your comment, if you are seeing this page.</p>
									<p>This page occur because you are facing any issue, so please write your comment what your are exactly facing, so we can resolve as soon as possible</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Apnamentor necessary plugins-->
			<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
			<script src="{{ asset('js/popper.js/js/popper.min.js') }}"></script>
			<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
			<script src="{{ asset('js/pace-progress/js/pace.min.js') }}"></script>
			<script src="{{ asset('js/perfect-scrollbar/js/perfect-scrollbar.min.js') }}"></script>
			<script src="{{ asset('icons/@coreui/coreui-pro/js/coreui.min.js') }}"></script>
			<script src="{{ asset('js/custom-form-validation.js') }}"></script>
	</body>
</html>