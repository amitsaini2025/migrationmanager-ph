@extends('layouts.crm_client_detail')
@section('title', 'Stage Checklists: ' . ($stage->name ?? ''))

@section('content')
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					@include('../Elements/CRM/setting')
				</div>
				<div class="col-9 col-md-9 col-lg-9">
					<div class="card">
						<div class="card-header">
							<h4>Stage Checklists: {{ $stage->name }}</h4>
							<div class="card-header-action">
								<a href="{{ route('adminconsole.features.workflow.stages', base64_encode(convert_uuencode($workflow->id))) }}" class="btn btn-secondary">@icon('fa-arrow-left') Back to Stages</a>
							</div>
						</div>
						<div class="card-body">
							<p class="small text-muted mb-4">
								<strong>Workflow:</strong> {{ $workflow->name }} —
								These checklists apply to <strong>every client matter</strong> using this workflow.
								Client-specific items added from Client Portal are kept separately.
							</p>

							<form method="post" action="{{ route('adminconsole.features.workflow.syncChecklists', base64_encode(convert_uuencode($workflow->id))) }}" class="mb-3">
								@csrf
								<button type="submit" class="btn btn-outline-primary btn-sm">@icon('fa-sync') Apply all checklists to existing matters</button>
							</form>

							<div class="card border mb-4">
								<div class="card-header">
									<h5 class="mb-0">@icon('fa-plus') Add Checklist</h5>
								</div>
								<div class="card-body">
									<form method="post" action="{{ route('adminconsole.features.workflow.storeStageChecklist') }}">
										@csrf
										<input type="hidden" name="workflow_id" value="{{ $workflow->id }}">
										<input type="hidden" name="workflow_stage_id" value="{{ $stage->id }}">
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<label for="name">Checklist Name <span class="span_req">*</span></label>
													<input type="text" name="name" id="name" class="form-control" maxlength="255" value="{{ old('name') }}" required placeholder="e.g. Initial assessment recorded">
												</div>
											</div>
											<div class="col-md-6">
												<div class="form-group">
													<label for="description">Description <small class="text-muted">(optional)</small></label>
													<input type="text" name="description" id="description" class="form-control" maxlength="1000" value="{{ old('description') }}" placeholder="Optional notes">
												</div>
											</div>
											<div class="col-md-6">
												<div class="custom-control custom-checkbox">
													<input type="checkbox" class="custom-control-input" id="allow_client" name="allow_client" value="1" {{ old('allow_client', '1') ? 'checked' : '' }}>
													<label class="custom-control-label" for="allow_client">Allow For Client</label>
												</div>
											</div>
											<div class="col-md-6">
												<div class="custom-control custom-checkbox">
													<input type="checkbox" class="custom-control-input" id="is_required" name="is_required" value="1" {{ old('is_required', '1') ? 'checked' : '' }}>
													<label class="custom-control-label" for="is_required">Required</label>
												</div>
											</div>
											<div class="col-12">
												<button type="submit" class="btn btn-primary">@icon('fa-save') Save Checklist</button>
											</div>
										</div>
									</form>
								</div>
							</div>

							<div class="table-responsive common_table">
								<table class="table text_wrap">
									<thead>
										<tr>
											<th>#</th>
											<th>Checklist Name</th>
											<th>Description</th>
											<th>Allow Client</th>
											<th>Required</th>
											<th>Actions</th>
										</tr>
									</thead>
									@if($checklists->count() > 0)
									<tbody>
										@foreach($checklists as $index => $item)
										<tr>
											<td>{{ $index + 1 }}</td>
											<td>{{ $item->name }}</td>
											<td>{{ $item->description ?: '—' }}</td>
											<td>{{ $item->allow_client ? 'Yes' : 'No' }}</td>
											<td>{{ $item->is_required ? 'Yes' : 'No' }}</td>
											<td>
												<button type="button"
													class="btn btn-sm btn-primary btn-edit-stage-checklist"
													data-name="{{ e($item->name) }}"
													data-description="{{ e($item->description ?? '') }}"
													data-allow-client="{{ $item->allow_client ? '1' : '0' }}"
													data-is-required="{{ $item->is_required ? '1' : '0' }}"
													data-update-url="{{ route('adminconsole.features.workflow.updateStageChecklist', base64_encode(convert_uuencode($item->id))) }}">
													@icon('fa-edit') Edit
												</button>
												<form method="post" action="{{ route('adminconsole.features.workflow.destroyStageChecklist', base64_encode(convert_uuencode($item->id))) }}" class="d-inline" onsubmit="return confirm('Remove this checklist? Existing client checklists will not be deleted.');">
													@csrf
													@method('DELETE')
													<button type="submit" class="btn btn-sm btn-outline-danger">@icon('fa-trash') Delete</button>
												</form>
											</td>
										</tr>
										@endforeach
									</tbody>
									@else
									<tbody>
										<tr>
											<td colspan="6" class="text-center text-muted">No checklist templates for this stage yet.</td>
										</tr>
									</tbody>
									@endif
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

{{-- Edit Checklist Modal --}}
<div class="modal fade" id="editStageChecklistModal" tabindex="-1" role="dialog" aria-labelledby="editStageChecklistModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editStageChecklistModalLabel">@icon('fa-edit') Edit Checklist</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="post" id="edit_stage_checklist_form" action="">
				@csrf
				@method('PUT')
				<div class="modal-body">
					<div class="form-group">
						<label for="edit_checklist_name">Checklist Name <span class="span_req">*</span></label>
						<input type="text" name="name" id="edit_checklist_name" class="form-control" maxlength="255" required>
					</div>
					<div class="form-group">
						<label for="edit_checklist_description">Description <small class="text-muted">(optional)</small></label>
						<input type="text" name="description" id="edit_checklist_description" class="form-control" maxlength="1000">
					</div>
					<div class="form-group">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="edit_allow_client" name="allow_client" value="1">
							<label class="custom-control-label" for="edit_allow_client">Allow For Client</label>
						</div>
					</div>
					<div class="form-group mb-0">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input" id="edit_is_required" name="is_required" value="1">
							<label class="custom-control-label" for="edit_is_required">Required</label>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">@icon('fa-save') Save Checklist</button>
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.btn-edit-stage-checklist').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var form = document.getElementById('edit_stage_checklist_form');
			form.action = btn.getAttribute('data-update-url') || '';
			document.getElementById('edit_checklist_name').value = btn.getAttribute('data-name') || '';
			document.getElementById('edit_checklist_description').value = btn.getAttribute('data-description') || '';
			document.getElementById('edit_allow_client').checked = btn.getAttribute('data-allow-client') === '1';
			document.getElementById('edit_is_required').checked = btn.getAttribute('data-is-required') === '1';
			if (typeof $ !== 'undefined' && $('#editStageChecklistModal').modal) {
				$('#editStageChecklistModal').modal('show');
			}
		});
	});
});
</script>
@endsection
