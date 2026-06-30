@extends('layouts.crm_client_detail')
@section('title', 'Create Workflow')

@section('content')
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ route('adminconsole.features.workflow.storeWorkflow') }}" method="POST" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<div class="row">
					<div class="col-12">
						<div class="card">
							<div class="card-header">
								<h4>Create Workflow</h4>
								<div class="card-header-action">
									<a href="{{ route('adminconsole.features.workflow.index') }}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
								</div>
							</div>
						</div>
					</div>
					<div class="col-3 col-md-3 col-lg-3">
						@include('../Elements/CRM/setting')
					</div>
					<div class="col-9 col-md-9 col-lg-9">
						<div class="card">
							<div class="card-body">
								<div class="form-group">
									<label for="name">Workflow Name <span class="span_req">*</span></label>
									<input type="text" name="name" class="form-control" required maxlength="255" placeholder="e.g. PR Visa Workflow" value="{{ old('name') }}">
									@error('name')<span class="custom-error">{{ $message }}</span>@enderror
								</div>
								<div class="form-group">
									<label for="matter_id">Link to Matter Type (optional)</label>
									<select name="matter_id" id="matter_id" class="form-control">
										<option value="">— None (use as General/custom) —</option>
										@foreach(\App\Models\Matter::orderBy('title')->get() as $m)
										<option value="{{ $m->id }}" {{ old('matter_id') == $m->id ? 'selected' : '' }}>{{ $m->title }} ({{ $m->nick_name }})</option>
										@endforeach
									</select>
									<small class="form-text text-muted">When set, new client matters of this type will default to this workflow.</small>
								</div>
								<div class="alert alert-info mb-3" role="alert">
									@icon('fa-info-circle')
									The new workflow will be pre-populated with all stages from the <strong>General</strong> workflow.
									Once created you will land on the stages page where you can add new stages or rename/delete any non-protected ones.
									Protected stages (e.g. Checklist, Verification, Decision Received, Ready to Close, File Closed) are required by the system and cannot be changed.
								</div>
								<button type="submit" class="btn btn-primary">Create Workflow</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</section>
</div>
@endsection
