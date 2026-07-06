{{-- Commission Invoice & General Invoice modals REMOVED - /create-invoice route and createInvoice controller do not exist --}}
{{-- Payment Details modal (addpaymentmodal) REMOVED - no UI opened it; invoice/payment-store route and /get-invoices do not exist --}}

<!-- Edit Client Funds Ledger Entry Modal -->
<div class="modal fade" id="editLedgerModal" tabindex="-1" role="dialog" aria-labelledby="editLedgerModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLedgerModalLabel">Edit Client Funds Ledger Entry</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editLedgerForm">
                    <input type="hidden" name="id">
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <div class="form-group">
                        <label for="trans_date">Transaction Date</label>
                        <input type="text" class="form-control" name="trans_date" required>
                    </div>
                    <div class="form-group">
                        <label for="entry_date">Entry Date</label>
                        <input type="text" class="form-control" name="entry_date" required>
                    </div>
                    <div class="form-group">
                        <label for="client_fund_ledger_type">Type</label>
                        <input type="text" class="form-control" name="client_fund_ledger_type" readonly>
                    </div>
                    <div class="form-group">
                        <label for="edit_ledger_payment_method">Payment method</label>
                        <select class="form-control" name="payment_method" id="edit_ledger_payment_method">
                            <option value="">—</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank transfer">Bank transfer</option>
                            <option value="EFTPOS">EFTPOS</option>
                            <option value="Refund">Refund</option>
                        </select>
                    </div>
                    <div class="form-group" id="edit_ledger_eftpos_surcharge_group" style="display:none;">
                        <label for="edit_ledger_eftpos_surcharge">Card surcharge ($)</label>
                        <input type="number" class="form-control" name="eftpos_surcharge_amount" id="edit_ledger_eftpos_surcharge" step="0.01" min="0" value="">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" name="description">
                    </div>
                    <div class="form-group">
                        <label for="deposit_amount">Funds In (+) <span class="text-muted" style="font-weight:normal;font-size:12px;">(excl. surcharge)</span></label>
                        <input type="number" class="form-control" name="deposit_amount" step="0.01" value="0.00">
                    </div>
                    <div class="form-group">
                        <label for="withdraw_amount">Funds Out (-)</label>
                        <input type="number" class="form-control" name="withdraw_amount" step="0.01" value="0.00">
                    </div>

            </div>
            <div class="modal-footer">
                <div class="upload_client_receipt_document" style="display:inline-block;">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="doctype" value="client_receipt">
                    <span class="file-selection-hint" style="margin-left: 10px; color: #34395e;"></span>
                    <a href="javascript:;" class="btn btn-primary add-document-btn">@icon('fa-plus') Add Document</a>
                    <input class="docclientreceiptupload" type="file" name="document_upload[]"/>
                </div>
                </form>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="updateLedgerEntryBtn">Update Entry</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Office Receipt Entry Modal -->
<div class="modal fade" id="editOfficeReceiptModal" tabindex="-1" role="dialog" aria-labelledby="editOfficeReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editOfficeReceiptModalLabel">@icon('fa-hand-holding-usd') Edit Direct Office Receipt</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editOfficeReceiptForm">
                    <input type="hidden" name="id">
                    <input type="hidden" name="receipt_id" id="edit_office_receipt_id">
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="client_matter_id" id="edit_office_client_matter_id">
                    <input type="hidden" name="receipt_type" value="2">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_trans_date">Transaction Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="trans_date" id="edit_office_trans_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_entry_date">Entry Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control datepicker" name="entry_date" id="edit_office_entry_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_payment_method">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-control" name="payment_method" id="edit_office_payment_method" required>
                                    <option value="">Select Method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank transfer">Bank Transfer</option>
                                    <option value="EFTPOS">EFTPOS</option>
                                    <option value="Refund">Refund</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_deposit_amount">Amount received <span class="text-danger">*</span> <span class="text-muted" style="font-weight:normal;font-size:12px;">(excl. surcharge)</span></label>
                                <input type="number" class="form-control" name="deposit_amount" id="edit_office_deposit_amount" step="0.01" value="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="edit_office_eftpos_surcharge_row" style="display:none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_office_eftpos_surcharge">Card surcharge ($)</label>
                                <input type="number" class="form-control" name="eftpos_surcharge_amount" id="edit_office_eftpos_surcharge" step="0.01" min="0" value="">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_office_invoice_no">Invoice Number (Optional)</label>
                                <select class="form-control" name="invoice_no" id="edit_office_invoice_no">
                                    <option value="">Select Invoice (Optional)</option>
                                </select>
                                <small class="form-text text-muted">Attach this payment to an invoice</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="edit_office_description">Description</label>
                                <textarea class="form-control" name="description" id="edit_office_description" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="upload_office_receipt_document_edit" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                <span class="file-selection-hint-edit" style="margin-left: 10px; color: #34395e;"></span>
                                <a href="javascript:;" class="btn btn-info add-document-btn-edit">@icon('fa-plus') Add/Update Document</a>
                                <input class="docofficereceiptupload_edit" type="file" name="document_upload[]"/>
                            </div>
                            <div id="current_document_display" class="mt-2"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-secondary" id="updateOfficeReceiptDraftBtn">@icon('fa-save') Save as Draft</button>
                <button type="button" class="btn btn-primary" id="updateOfficeReceiptFinalBtn">@icon('fa-check') Save and Finalize</button>
            </div>
        </div>
    </div>
</div>

<!-- Cost Assignment Form -->
<div class="modal fade custom_modal" id="costAssignmentCreateFormModel" tabindex="-1" role="dialog" aria-labelledby="costAssignmentModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="costAssignmentModalLabel">Create Cost Assignment</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" action="{{route('clients.savecostassignment')}}" name="costAssignmentform" id="costAssignmentform" autocomplete="off">
					@csrf
					<!-- Hidden Fields for Client and Client Matter ID -->
					<input type="hidden" name="client_id" id="cost_assignment_client_id">
					<input type="hidden" name="client_matter_id" id="cost_assignment_client_matter_id">
                    <input type="hidden" name="agent_id" id="costassign_agent_id">
					<!-- Error Message Container -->
					<div class="custom-error-msg"></div>

					<!-- Agent Details (Read-only, assuming agent is pre-fetched) -->
					<div class="row">
						<div class="col-12">
							<h6 class="font-medium text-gray-900">Agent Details</h6>
							<div class="row mt-2">
								<div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Agent Name - <span id="costassign_agent_name_label"></span></label>
                                    </div>
								</div>
								<div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Business Name - <span id="costassign_business_name_label"></span></label>
									</div>
								</div>

                                <div class="col-6">
									<div class="form-group">
										<label class="text-sm font-medium text-gray-700">Client Matter Name - <span id="costassign_client_matter_name_label"></span></label>
									</div>
								</div>
                            </div>
						</div>
					</div>

                    <div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">

						<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
							<h4>Block Fee</h4>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_1_Ex_Tax">Block 1 Incl. Tax</label>
									<input type="text" name="Block_1_Ex_Tax" class="form-control" id="Block_1_Ex_Tax" autocomplete="off" placeholder="Enter Block 1 Incl. Tax">
									@if ($errors->has('Block_1_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_1_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_2_Ex_Tax">Block 2 Incl. Tax</label>
									<input type="text" name="Block_2_Ex_Tax" class="form-control" id="Block_2_Ex_Tax" autocomplete="off" placeholder="Enter Block 2 Incl. Tax">
									@if ($errors->has('Block_2_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_2_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_3_Ex_Tax">Block 3 Incl. Tax</label>
									<input type="text" name="Block_3_Ex_Tax" class="form-control" id="Block_3_Ex_Tax" autocomplete="off" placeholder="Enter Block 3 Incl. Tax">
									@if ($errors->has('Block_3_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_3_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="TotalBLOCKFEE">Total Block Fee</label>
									<input type="text" name="TotalBLOCKFEE" class="form-control" id="TotalBLOCKFEE" autocomplete="off" placeholder="Enter Total Block Fee" readonly>
								</div>
							</div>
						</div>

                        <div style="margin-bottom: 15px;" class="accordion-header">
                            <h4>Department Fee</h4>
							<div class="col-3">
								<label for="surcharge">Surcharge</label>
								<select class="form-control" name="surcharge" id="surcharge">
									<option value="">Select</option>
									<option value="Yes">Yes</option>
									<option value="No">No</option>
								</select>
							</div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-9">
                                            <label for="Dept_Base_Application_Charge">Dept Base Application Charge</label>
                                            <input type="text" name="Dept_Base_Application_Charge" class="form-control" id="Dept_Base_Application_Charge" autocomplete="off" placeholder="Enter Dept Base Application Charge">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Base_Application_Charge_no_of_person">Person</label>
                                            <input type="number" name="Dept_Base_Application_Charge_no_of_person" id="Dept_Base_Application_Charge_no_of_person"
                                                class="form-control" placeholder="1" value="1" min="0" step="any" />
                                        </div>
                                    </div>

                                    @if ($errors->has('Dept_Base_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Base_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
				                        <div class="col-9">
                                            <label for="Dept_Non_Internet_Application_Charge">Dept Non Internet Application Charge</label>
                                            <input type="text" name="Dept_Non_Internet_Application_Charge" class="form-control" id="Dept_Non_Internet_Application_Charge" autocomplete="off" placeholder="Enter Dept Non Internet Application Charge">
                                        </div>
				                        <div class="col-3">
                                            <label for="Dept_Non_Internet_Application_Charge_no_of_person">Person</label>
                                            <input type="number" name="Dept_Non_Internet_Application_Charge_no_of_person" id="Dept_Non_Internet_Application_Charge_no_of_person"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Non_Internet_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Non_Internet_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-9">
                                            <label for="Dept_Additional_Applicant_Charge_18_Plus">Dept Additional Applicant Charge 18 +</label>
                                            <input type="text" name="Dept_Additional_Applicant_Charge_18_Plus" class="form-control" id="Dept_Additional_Applicant_Charge_18_Plus" autocomplete="off" placeholder="Enter Dept Additional Applicant Charge 18 Plus">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Additional_Applicant_Charge_18_Plus_no_of_person">Person</label>
                                            <input type="number" name="Dept_Additional_Applicant_Charge_18_Plus_no_of_person" id="Dept_Additional_Applicant_Charge_18_Plus_no_of_person"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Additional_Applicant_Charge_18_Plus'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Additional_Applicant_Charge_18_Plus') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Additional_Applicant_Charge_Under_18">Dept Add. Applicant Charge Under 18</label>
                                            <input type="text" name="Dept_Additional_Applicant_Charge_Under_18" class="form-control" id="Dept_Additional_Applicant_Charge_Under_18" autocomplete="off" placeholder="Enter Dept Additional Applicant Charge Under 18">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Additional_Applicant_Charge_Under_18_no_of_person">Person</label>
                                            <input type="number" name="Dept_Additional_Applicant_Charge_Under_18_no_of_person" id="Dept_Additional_Applicant_Charge_Under_18_no_of_person"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Additional_Applicant_Charge_Under_18'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Additional_Applicant_Charge_Under_18') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Subsequent_Temp_Application_Charge">Dept Subsequent Temp App Charge</label>
                                            <input type="text" name="Dept_Subsequent_Temp_Application_Charge" class="form-control" id="Dept_Subsequent_Temp_Application_Charge" autocomplete="off" placeholder="Enter Dept Subsequent Temp Application Charge">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Subsequent_Temp_Application_Charge_no_of_person">Person</label>
                                            <input type="number" name="Dept_Subsequent_Temp_Application_Charge_no_of_person" id="Dept_Subsequent_Temp_Application_Charge_no_of_person"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Subsequent_Temp_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Subsequent_Temp_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Second_VAC_Instalment_Charge_18_Plus">Dept Second VAC Instalment 18+</label>
                                            <input type="text" name="Dept_Second_VAC_Instalment_Charge_18_Plus" class="form-control" id="Dept_Second_VAC_Instalment_Charge_18_Plus" autocomplete="off" placeholder="Enter Dept Second VAC Instalment Charge 18 Plus">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person">Person</label>
                                            <input type="number" name="Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person" id="Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Second_VAC_Instalment_Charge_18_Plus'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Second_VAC_Instalment_Charge_18_Plus') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Second_VAC_Instalment_Under_18">Dept Second VAC Instalment Under 18</label>
                                            <input type="text" name="Dept_Second_VAC_Instalment_Under_18" class="form-control" id="Dept_Second_VAC_Instalment_Under_18" autocomplete="off" placeholder="Enter Dept Second VAC Instalment Under 18">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Second_VAC_Instalment_Under_18_no_of_person">Person</label>
                                            <input type="number" name="Dept_Second_VAC_Instalment_Under_18_no_of_person" id="Dept_Second_VAC_Instalment_Under_18_no_of_person"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Second_VAC_Instalment_Under_18'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Second_VAC_Instalment_Under_18') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="Dept_Nomination_Application_Charge">Dept Nomination Application Charge</label>
                                    <input type="text" name="Dept_Nomination_Application_Charge" class="form-control" id="Dept_Nomination_Application_Charge" autocomplete="off" placeholder="Enter Dept Nomination Application Charge">
                                    @if ($errors->has('Dept_Nomination_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Nomination_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="Dept_Sponsorship_Application_Charge">Dept Sponsorship Application Charge</label>
                                    <input type="text" name="Dept_Sponsorship_Application_Charge" class="form-control" id="Dept_Sponsorship_Application_Charge" autocomplete="off" placeholder="Enter Dept Sponsorship Application Charge">
                                    @if ($errors->has('Dept_Sponsorship_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Sponsorship_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if (isset($fetchedData) && !empty($fetchedData->is_company))
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="saf_levy">SAF Levy</label>
                                    <input type="text" name="saf_levy" id="saf_levy" class="form-control" autocomplete="off" placeholder="Enter SAF Levy" value="{{ old('saf_levy') }}" />
                                    @if ($errors->has('saf_levy'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ $errors->first('saf_levy') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="TotalDoHACharges">Total DoHA Charges</label>
                                    <input type="text" name="TotalDoHACharges" class="form-control" id="TotalDoHACharges" autocomplete="off" placeholder="Enter Total DoHA Charges" readonly>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="TotalDoHASurcharges">Total DoHA Surcharges</label>
                                    <input type="text" name="TotalDoHASurcharges" class="form-control" id="TotalDoHASurcharges" autocomplete="off" placeholder="Enter Total DoHA Surcharges" readonly>
                                </div>
                            </div>
                        </div>

						<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                            <h4>Additional Fee</h4>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="additional_fee_1">Additional Fee1</label>
                                    <input type="text" name="additional_fee_1" class="form-control" id="additional_fee_1" autocomplete="off" placeholder="Enter Additional Fee">
                                    @if ($errors->has('additional_fee_1'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('additional_fee_1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>

					<!-- Submit Button -->
					<div class="row mt-4">
						<div class="col-12">
							<button type="submit" class="btn btn-primary">Save Cost Assignment</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Lead Cost Assignment Form -->
<div class="modal fade custom_modal" id="costAssignmentCreateFormModelLead" tabindex="-1" role="dialog" aria-labelledby="costAssignmentModalLabelLead" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="costAssignmentModalLabelLead">Create Cost Assignment</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">×</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="POST" action="{{route('clients.savecostassignmentlead')}}" name="costAssignmentformlead" id="costAssignmentformlead" autocomplete="off">
					@csrf
					<!-- Hidden Fields for Client and Client Matter ID -->
					<input type="hidden" name="client_id" id="cost_assignment_lead_id">
					<!-- Error Message Container -->
					<div class="custom-error-msg"></div>
					<div class="row">
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="migration_agent">Select Migration Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="migration_agent" id="sel_migration_agent_id_lead">
                                    <option value="">Select Migration Agent</option>
                                    @foreach(\App\Models\Staff::assignmentDropdownMigrationAgentsQuery()->get() as $migAgntlist)
                                        <option value="{{$migAgntlist->id}}">{{@$migAgntlist->first_name}} {{@$migAgntlist->last_name}} ({{@$migAgntlist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="person_responsible">Select Person Responsible <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="person_responsible" id="sel_person_responsible_id_lead">
                                    <option value="">Select Person Responsible</option>
                                    @foreach(\App\Models\Staff::assignmentDropdownPersonResponsibleQuery()->get() as $perreslist)
                                        <option value="{{$perreslist->id}}">{{@$perreslist->first_name}} {{@$perreslist->last_name}} ({{@$perreslist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="person_assisting">Select Person Assisting <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="person_assisting" id="sel_person_assisting_id_lead">
                                    <option value="">Select Person Assisting</option>
                                    @foreach(\App\Models\Staff::assignmentDropdownPersonAssistingQuery()->get() as $perassislist)
                                        <option value="{{$perassislist->id}}">{{@$perassislist->first_name}} {{@$perassislist->last_name}} ({{@$perassislist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="office_id">Handling Office <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="office_id" id="sel_office_id_lead">
                                    <option value="">Select Office</option>
                                    @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                        <option value="{{$office->id}}" 
                                            {{ Auth::user()->office_id == $office->id ? 'selected' : '' }}>
                                            {{$office->office_name}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="matter_id">Select Matter <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="matter_id" id="sel_matter_id_lead">
                                    <option value="">Select Matter</option>
                                    @php
                                        $leadCostMatterQuery = \App\Models\Matter::select('id', 'title')->where('status', 1)
                                            ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                        $leadCostMatterList = $leadCostMatterQuery->get();
                                    @endphp
                                    @foreach($leadCostMatterList as $matterlist)
                                        <option value="{{$matterlist->id}}">{{@$matterlist->title}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
					</div>

					<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
                        <div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
							<h4>Block Fee</h4>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_1_Ex_Tax">Block 1 Incl. Tax</label>
									<input type="text" name="Block_1_Ex_Tax" class="form-control" id="Block_1_Ex_Tax_lead" autocomplete="off" placeholder="Enter Block 1 Incl. Tax">
									@if ($errors->has('Block_1_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_1_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_2_Ex_Tax">Block 2 Incl. Tax</label>
									<input type="text" name="Block_2_Ex_Tax" class="form-control" id="Block_2_Ex_Tax_lead" autocomplete="off" placeholder="Enter Block 2 Incl. Tax">
									@if ($errors->has('Block_2_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_2_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="Block_3_Ex_Tax">Block 3 Incl. Tax</label>
									<input type="text" name="Block_3_Ex_Tax" class="form-control" id="Block_3_Ex_Tax_lead" autocomplete="off" placeholder="Enter Block 3 Incl. Tax">
									@if ($errors->has('Block_3_Ex_Tax'))
										<span class="custom-error" role="alert">
											<strong>{{ @$errors->first('Block_3_Ex_Tax') }}</strong>
										</span>
									@endif
								</div>
							</div>

							<div class="col-12 col-md-6 col-lg-6">
								<div class="form-group">
									<label for="TotalBLOCKFEE">Total Block Fee</label>
									<input type="text" name="TotalBLOCKFEE" class="form-control" id="TotalBLOCKFEE_lead" autocomplete="off" placeholder="Enter Total Block Fee" readonly>
								</div>
							</div>
						</div>

                        <div style="margin-bottom: 15px;" class="accordion-header">
                            <h4>Department Fee</h4>
							<div class="col-3">
								<label for="surcharge">Surcharge</label>
								<select class="form-control" name="surcharge" id="surcharge_lead">
									<option value="">Select</option>
									<option value="Yes">Yes</option>
									<option value="No">No</option>
								</select>
							</div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-9">
                                            <label for="Dept_Base_Application_Charge">Dept Base Application Charge</label>
                                            <input type="text" name="Dept_Base_Application_Charge" class="form-control" id="Dept_Base_Application_Charge_lead" autocomplete="off" placeholder="Enter Dept Base Application Charge">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Base_Application_Charge_no_of_person">Person</label>
                                            <input type="number" name="Dept_Base_Application_Charge_no_of_person" id="Dept_Base_Application_Charge_no_of_person_lead"
                                                class="form-control" placeholder="1" value="1" min="0" step="any" />
                                        </div>
                                    </div>

                                    @if ($errors->has('Dept_Base_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Base_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
				                        <div class="col-9">
                                            <label for="Dept_Non_Internet_Application_Charge">Dept Non Internet Application Charge</label>
                                            <input type="text" name="Dept_Non_Internet_Application_Charge" class="form-control" id="Dept_Non_Internet_Application_Charge_lead" autocomplete="off" placeholder="Enter Dept Non Internet Application Charge">
                                        </div>
				                        <div class="col-3">
                                            <label for="Dept_Non_Internet_Application_Charge_no_of_person">Person</label>
                                            <input type="number" name="Dept_Non_Internet_Application_Charge_no_of_person" id="Dept_Non_Internet_Application_Charge_no_of_person_lead"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Non_Internet_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Non_Internet_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-9">
                                            <label for="Dept_Additional_Applicant_Charge_18_Plus">Dept Additional Applicant Charge 18 +</label>
                                            <input type="text" name="Dept_Additional_Applicant_Charge_18_Plus" class="form-control" id="Dept_Additional_Applicant_Charge_18_Plus_lead" autocomplete="off" placeholder="Enter Dept Additional Applicant Charge 18 Plus">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Additional_Applicant_Charge_18_Plus_no_of_person">Person</label>
                                            <input type="number" name="Dept_Additional_Applicant_Charge_18_Plus_no_of_person" id="Dept_Additional_Applicant_Charge_18_Plus_no_of_person_lead"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Additional_Applicant_Charge_18_Plus'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Additional_Applicant_Charge_18_Plus') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Additional_Applicant_Charge_Under_18">Dept Add. Applicant Charge Under 18</label>
                                            <input type="text" name="Dept_Additional_Applicant_Charge_Under_18" class="form-control" id="Dept_Additional_Applicant_Charge_Under_18_lead" autocomplete="off" placeholder="Enter Dept Additional Applicant Charge Under 18">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Additional_Applicant_Charge_Under_18_no_of_person">Person</label>
                                            <input type="number" name="Dept_Additional_Applicant_Charge_Under_18_no_of_person" id="Dept_Additional_Applicant_Charge_Under_18_no_of_person_lead"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Additional_Applicant_Charge_Under_18'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Additional_Applicant_Charge_Under_18') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Subsequent_Temp_Application_Charge">Dept Subsequent Temp App Charge</label>
                                            <input type="text" name="Dept_Subsequent_Temp_Application_Charge" class="form-control" id="Dept_Subsequent_Temp_Application_Charge_lead" autocomplete="off" placeholder="Enter Dept Subsequent Temp Application Charge">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Subsequent_Temp_Application_Charge_no_of_person">Person</label>
                                            <input type="number" name="Dept_Subsequent_Temp_Application_Charge_no_of_person" id="Dept_Subsequent_Temp_Application_Charge_no_of_person_lead"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Subsequent_Temp_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Subsequent_Temp_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Second_VAC_Instalment_Charge_18_Plus">Dept Second VAC Instalment 18+</label>
                                            <input type="text" name="Dept_Second_VAC_Instalment_Charge_18_Plus" class="form-control" id="Dept_Second_VAC_Instalment_Charge_18_Plus_lead" autocomplete="off" placeholder="Enter Dept Second VAC Instalment Charge 18 Plus">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person">Person</label>
                                            <input type="number" name="Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person" id="Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person_lead"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Second_VAC_Instalment_Charge_18_Plus'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Second_VAC_Instalment_Charge_18_Plus') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <div class="row">
			                            <div class="col-9">
                                            <label for="Dept_Second_VAC_Instalment_Under_18">Dept Second VAC Instalment Under 18</label>
                                            <input type="text" name="Dept_Second_VAC_Instalment_Under_18" class="form-control" id="Dept_Second_VAC_Instalment_Under_18_lead" autocomplete="off" placeholder="Enter Dept Second VAC Instalment Under 18">
                                        </div>
                                        <div class="col-3">
                                            <label for="Dept_Second_VAC_Instalment_Under_18_no_of_person">Person</label>
                                            <input type="number" name="Dept_Second_VAC_Instalment_Under_18_no_of_person" id="Dept_Second_VAC_Instalment_Under_18_no_of_person_lead"
                                                class="form-control" placeholder="0" value="0" min="0" step="any" />
                                        </div>
                                    </div>
                                    @if ($errors->has('Dept_Second_VAC_Instalment_Under_18'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Second_VAC_Instalment_Under_18') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="Dept_Nomination_Application_Charge">Dept Nomination Application Charge</label>
                                    <input type="text" name="Dept_Nomination_Application_Charge" class="form-control" id="Dept_Nomination_Application_Charge_lead" autocomplete="off" placeholder="Enter Dept Nomination Application Charge">
                                    @if ($errors->has('Dept_Nomination_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Nomination_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="Dept_Sponsorship_Application_Charge">Dept Sponsorship Application Charge</label>
                                    <input type="text" name="Dept_Sponsorship_Application_Charge" class="form-control" id="Dept_Sponsorship_Application_Charge_lead" autocomplete="off" placeholder="Enter Dept Sponsorship Application Charge">
                                    @if ($errors->has('Dept_Sponsorship_Application_Charge'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('Dept_Sponsorship_Application_Charge') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if (isset($fetchedData) && !empty($fetchedData->is_company))
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="saf_levy_lead">SAF Levy</label>
                                    <input type="text" name="saf_levy" id="saf_levy_lead" class="form-control" autocomplete="off" placeholder="Enter SAF Levy" value="{{ old('saf_levy') }}" />
                                    @if ($errors->has('saf_levy'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ $errors->first('saf_levy') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="TotalDoHACharges">Total DoHA Charges</label>
                                    <input type="text" name="TotalDoHACharges" class="form-control" id="TotalDoHACharges_lead" autocomplete="off" placeholder="Enter Total DoHA Charges" readonly>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="TotalDoHASurcharges">Total DoHA Surcharges</label>
                                    <input type="text" name="TotalDoHASurcharges" class="form-control" id="TotalDoHASurcharges_lead" autocomplete="off" placeholder="Enter Total DoHA Surcharges" readonly>
                                </div>
                            </div>
                        </div>

						<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                            <h4>Additional Fee</h4>
                        </div>

                        <div class="row">
                            <div class="col-12 col-md-6 col-lg-6">
                                <div class="form-group">
                                    <label for="additional_fee_1">Additional Fee1</label>
                                    <input type="text" name="additional_fee_1" class="form-control" id="additional_fee_1_lead" autocomplete="off" placeholder="Enter Additional Fee">
                                    @if ($errors->has('additional_fee_1'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('additional_fee_1') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>

					<!-- Submit Button -->
					<div class="row mt-4">
						<div class="col-12">
							<button onclick="customValidate('costAssignmentformlead')" type="button" class="btn btn-primary">Save Cost Assignment</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
