@extends('layouts.crm_client_detail')
@section('title', 'Workflow Stages: ' . ($workflow->name ?? ''))

@section('styles')
<style>
	/* Stacked compact actions — avoids dropdown clipping / side-by-side overflow in narrow cells */
	.workflow-stages-table td.workflow-stage-actions-col {
		white-space: normal !important;
		vertical-align: middle;
		width: 1%;
		min-width: 7rem;
	}
	.workflow-stage-cell-actions {
		display: flex;
		flex-direction: column;
		align-items: stretch;
		gap: 0.25rem;
	}
	.workflow-stage-cell-actions .btn {
		font-size: 0.75rem;
		padding: 0.2rem 0.45rem;
		line-height: 1.25;
		white-space: nowrap;
		text-align: center;
	}
</style>
@endsection

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
							<h4>Workflow Stages: {{ $workflow->name }}</h4>
							<div class="card-header-action">
								<a href="{{ route('adminconsole.features.workflow.index') }}" class="btn btn-secondary">@icon('fa-arrow-left') Back to Workflows</a>
								<a href="{{ route('adminconsole.features.workflow.createStage', base64_encode(convert_uuencode($workflow->id))) }}" class="btn btn-primary">@icon('fa-plus') Add Stage</a>
							</div>
						</div>
						<div class="card-body">
							<p class="small mb-3 text-muted"><strong>Manage stages</strong> for this workflow below. Use <strong>Add Stage</strong> (top right) to add rows. Stages marked <span class="badge badge-secondary">Protected</span> cannot be renamed or removed — <strong>Edit</strong> still opens the stage (read-only); <strong>Delete</strong> is disabled.</p>
							<div class="table-responsive common_table">
								<table class="table text_wrap workflow-stages-table">
									<thead>
										<tr>
											<th>Stage</th>
											<th>Total Matters</th>
											<th class="text-nowrap">Actions</th>
										</tr>
									</thead>
									@if($lists->count() > 0)
									<tbody>
									@foreach ($lists as $list)
									<?php $countmatters = $matterCounts[$list->id] ?? 0; ?>
									<?php $stageFrozen = $list->isFrozen(); ?>
									<tr>
										<td>
											{{ $list->name ?: config('constants.empty', '—') }}
											@if($stageFrozen)
											<span class="badge badge-secondary ml-1 align-middle" title="This stage cannot be renamed or deleted">Protected</span>
											@endif
										</td>
										<td>{{ $countmatters }}</td>
										<td class="workflow-stage-actions-col">
											<div class="workflow-stage-cell-actions">
												<a class="btn btn-sm btn-primary" href="{{ route('adminconsole.features.workflow.edit', base64_encode(convert_uuencode($list->id))) }}" title="{{ $stageFrozen ? 'View (protected — name cannot be changed)' : 'Edit stage name' }}">@icon('fa-edit') Edit</a>
												<a class="btn btn-sm btn-info" href="{{ route('adminconsole.features.workflow.createStage', base64_encode(convert_uuencode($workflow->id))) }}?after={{ rawurlencode(base64_encode(convert_uuencode($list->id))) }}" title="Insert a new stage immediately after this one">@icon('fa-plus') Add After</a>
												@if($stageFrozen)
												<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Protected stages cannot be deleted">@icon('fa-trash') Delete</button>
												@else
												<a class="btn btn-sm btn-outline-danger" href="javascript:;" onclick="deleteAction({{ $list->id }}, 'workflow_stages')">@icon('fa-trash') Delete</a>
												@endif
											</div>
										</td>
									</tr>
									@endforeach
									</tbody>
									@else
									<tbody>
										<tr><td colspan="3" class="text-center">No stages. <a href="{{ route('adminconsole.features.workflow.createStage', base64_encode(convert_uuencode($workflow->id))) }}">Add stage</a>.</td></tr>
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
@endsection
