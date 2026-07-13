{{-- ========================================
    MATTER-RELATED MODALS (Client Portal / Workflow)
    This file contains matter modals for the client detail page
    ======================================== --}}

{{-- 1. Add Application Modal - REMOVED (orphaned, no UI trigger; matters created via client_matters sync) --}}

{{-- 2. Discontinue Matter Modal --}}
<!-- Discontinue Matter Modal -->
<div class="modal fade custom_modal" id="discon_application" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Discontinue Matter</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/client-portal/discontinue')}}" name="discontinue_matter" id="discontinue_matter" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="diapp_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="workflow">Discontinue Reason <span class="span_req">*</span></label>
								<select data-valid="required" class="form-control workflow" id="workflow" name="workflow">
									<option value="">Please Select</option>
									<option value="Change of Matter">Change of Matter</option>
									<option value="Error by Team Member">Error by Team Member</option>
									<option value="Financial Difficulties">Financial Difficulties</option>
									<option value="Loss of competitor">Loss of competitor</option>
									<option value="Other Reasons">Other Reasons</option>

								</select>
								<span class="custom-error workflow_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label>Notes <span class="span_req">*</span></label>
								<textarea data-valid="required"  class="form-control" name="note"></textarea>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('discontinue_matter')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2a. Decision Received Modal (Granted/Refused/Withdrawn + note) --}}
<div class="modal fade custom_modal" id="decision-received-modal" tabindex="-1" role="dialog" aria-labelledby="decisionReceivedModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="decisionReceivedModalLabel">Decision Received</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-3" style="color: #374151;">Please select the outcome and add a note.</p>
				<form id="decision-received-form" name="decision-received-form" autocomplete="off">
					@csrf
					<input type="hidden" name="matter_id" id="decision-received-matter-id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="decision-outcome">Outcome <span class="span_req">*</span></label>
								<select class="form-control" id="decision-outcome" name="decision_outcome" data-valid="required" required>
									<option value="">Please Select</option>
									<option value="Granted">Granted</option>
									<option value="Refused">Refused</option>
									<option value="Withdrawn">Withdrawn</option>
								</select>
								<span class="custom-error decision-outcome-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="decision-note">Note <span class="span_req">*</span></label>
								<textarea class="form-control" id="decision-note" name="decision_note" rows="3" placeholder="Enter note..." required></textarea>
								<span class="custom-error decision-note-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="button" class="btn btn-primary" id="decision-received-submit">
								@icon('fa-check') Proceed to Decision Received
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2b. Discontinue Matter Modal (for Workflow tab - client_matters) --}}
<div class="modal fade custom_modal" id="discontinue-matter-modal" tabindex="-1" role="dialog" aria-labelledby="discontinueMatterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="discontinueMatterModalLabel">Discontinue Matter</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form id="discontinue-matter-form" name="discontinue-matter-form" autocomplete="off">
					@csrf
					<input type="hidden" name="matter_id" id="discontinue-matter-id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="discontinue-reason">Reason for Discontinue <span class="span_req">*</span></label>
								<select class="form-control" id="discontinue-reason" name="discontinue_reason" data-valid="required" required>
									<option value="">Please Select</option>
									<option value="Change of Matter">Change of Matter</option>
									<option value="Error by Team Member">Error by Team Member</option>
									<option value="Financial Difficulties">Financial Difficulties</option>
									<option value="Grant of Another visa">Grant of Another visa</option>
									<option value="Loss of Competitor">Loss of Competitor</option>
									<option value="Client Withdrew">Client Withdrew</option>
									<option value="Other Reasons">Other Reasons</option>
								</select>
								<span class="custom-error discontinue-reason-error" role="alert"><strong></strong></span>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="discontinue-notes">Notes</label>
								<textarea class="form-control" id="discontinue-notes" name="discontinue_notes" rows="3" placeholder="Optional additional notes"></textarea>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button type="button" class="btn btn-danger" id="discontinue-matter-submit">
								@icon('fa-ban') Discontinue
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 2c. Change Workflow Modal (for existing matters) --}}
<div class="modal fade custom_modal" id="change-workflow-modal" tabindex="-1" role="dialog" aria-labelledby="changeWorkflowModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="changeWorkflowModalLabel">Change Workflow</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="change-workflow-matter-id" value="">
				<div class="form-group">
					<label for="change-workflow-select">Select Workflow</label>
					<select class="form-control" id="change-workflow-select">
						@foreach(\App\Models\Workflow::orderBy('name')->get() as $wf)
						<option value="{{ $wf->id }}">{{ $wf->name }}{{ $wf->matter ? ' (' . $wf->matter->title . ')' : '' }}</option>
						@endforeach
					</select>
					<small class="form-text text-muted">Stage will be mapped by name; if no match, first stage is used.</small>
				</div>
				<button type="button" class="btn btn-primary" id="change-workflow-submit">
					@icon('fa-exchange-alt') Change Workflow
				</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
			</div>
		</div>
	</div>
</div>

{{-- 2d. Set Deadline Modal (Workflow tab) --}}
<div class="modal fade custom_modal" id="workflow-set-deadline-modal" tabindex="-1" role="dialog" aria-labelledby="workflowSetDeadlineModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="workflowSetDeadlineModalLabel">Set Deadline</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="workflow-set-deadline-matter-id" value="">
				<div class="form-group">
					<label for="workflow-set-deadline-date">Deadline Date <span class="span_req">*</span></label>
					<input type="date" class="form-control" id="workflow-set-deadline-date" required>
					<span class="custom-error workflow-set-deadline-error" role="alert"><strong></strong></span>
				</div>
				<button type="button" class="btn btn-primary" id="workflow-set-deadline-submit">
					@icon('fa-check') Save Deadline
				</button>
				<button type="button" class="btn btn-outline-danger" id="workflow-set-deadline-clear" style="display: none;">
					@icon('fa-times') Clear Deadline
				</button>
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
			</div>
		</div>
	</div>
</div>

{{-- 3. Revert Discontinued Matter Modal --}}
<div class="modal fade custom_modal" id="revert_matter" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Revert Discontinued Matter</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/client-portal/revert')}}" name="revertapplication" id="revertapplication" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="revapp_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label>Notes <span class="span_req">*</span></label>
								<textarea data-valid="required"  class="form-control" name="note"></textarea>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('revertapplication')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 4. Add Interested Service Modal - REMOVED --}}
{{-- Feature deprecated - no UI triggers exist --}}
{{-- Backend routes still exist (/interested-service, /get-services) but modal never opens --}}
{{-- Partner/Product/Branch dropdown population routes (getProduct, getBranch) were never implemented --}}

