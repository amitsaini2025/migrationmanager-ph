@extends('layouts.crm_client_detail')
@section('title', 'Workflow Stages')

@section('styles')
<style>
	.workflow-stage-protected-lock {
		display: inline-flex;
		align-items: center;
		margin-left: 0.35rem;
		vertical-align: middle;
		color: #d97706;
		line-height: 1;
	}
	.workflow-stage-protected-lock__icon {
		width: 0.95rem;
		height: 0.95rem;
		stroke-width: 2.25;
	}
</style>
@endsection

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
					<div class="card">
						<div class="card-header">
							<h4>Workflow Stages</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.workflow.create')}}" class="btn btn-primary">Add Workflow Stage</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
                                        <th>Stage</th>
										<th>Total Matters</th>
										<!--<th>Status</th>-->
										<th></th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<?php
									$countmatters = \App\Models\ClientMatter::where('workflow_stage_id', $list->id)->count();
									$legacyWorkflowName = isset($list->workflow_id) && $list->workflow_id
										? optional(\App\Models\Workflow::find($list->workflow_id))->name
										: null;
									$legacyStageFrozen = \App\Support\WorkflowStageFreeze::isFrozen($list->name ?? '', $legacyWorkflowName);
									?>
									<tr id="id_{{@$list->id}}">
                                        <td>{{ @$list->name == "" ? config('constants.empty') : $list->name }}@if($legacyStageFrozen) @include('AdminConsole.features.workflow.partials.protected-lock')@endif</td>
										<td>{{$countmatters}}</td>
										<!--<td>{{--@if($list->status == 1) Active @else Inactive @endif--}}</td>-->
										<td>
											@if(!$legacyStageFrozen)
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.workflow.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
													{{--@if($list->status == 1)--}}
														<!--<a class="dropdown-item has-icon " href="{{--URL::to('/admin/workflow/deactivate-workflow/'.base64_encode(convert_uuencode(@$list->id)))--}}" onClick="">@icon('fa-trash') Deactivate</a>-->
                                                    {{--@else--}}
														<!--<a class="dropdown-item has-icon " href="{{--URL::to('/admin/workflow/activate-workflow/'.base64_encode(convert_uuencode(@$list->id)))--}}" onClick="">@icon('fa-trash') Activate</a>-->
                                                    {{--@endif--}}
												<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'workflow_stages')">@icon('fa-trash') Delete</a>
												</div>
											</div>
											@endif
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
										<td style="text-align:center;" colspan="7">
											No Record found
										</td>
									</tr>
								</tbody>
								@endif
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>

@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
	$('.cb-element').change(function () {
	if ($('.cb-element:checked').length == $('.cb-element').length){
	  $('#checkbox-all').prop('checked',true);
	}
	else {
	  $('#checkbox-all').prop('checked',false);
	}
	/* if ($('.cb-element:checked').length > 0){
			$('.is_checked_client').show();
			$('.is_checked_clientn').hide();
		}else{
			$('.is_checked_client').hide();
			$('.is_checked_clientn').show();
		} */
	});
});
</script>
@endpush
