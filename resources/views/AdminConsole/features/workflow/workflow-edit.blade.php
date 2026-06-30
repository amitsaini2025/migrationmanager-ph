@extends('layouts.crm_client_detail')
@section('title', 'Edit Workflow')

@section('content')
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ route('adminconsole.features.workflow.updateWorkflow', base64_encode(convert_uuencode($workflow->id))) }}" method="POST" autocomplete="off">
				@csrf
				@method('PUT')
				<div class="row">
					<div class="col-12">
						<div class="card">
							<div class="card-header">
								<h4>Edit Workflow</h4>
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
									<input type="text" name="name" class="form-control" required maxlength="255" value="{{ old('name', $workflow->name) }}">
									@error('name')<span class="custom-error">{{ $message }}</span>@enderror
								</div>
								<div class="form-group">
									<label for="matter_id">Link to Matter Type (optional)</label>
									<select name="matter_id" id="matter_id" class="form-control">
										<option value="">— None (use as General/custom) —</option>
										@foreach(\App\Models\Matter::orderBy('title')->get() as $m)
										<option value="{{ $m->id }}" {{ old('matter_id', $workflow->matter_id) == $m->id ? 'selected' : '' }}>{{ $m->title }} ({{ $m->nick_name }})</option>
										@endforeach
									</select>
								</div>
								<button type="submit" class="btn btn-primary">Update Workflow</button>
								<a href="{{ route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id))) }}" class="btn btn-secondary">Manage Stages</a>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</section>
</div>
@endsection
