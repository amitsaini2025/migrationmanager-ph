{{-- ========================================
    ALL RECEIPT-RELATED MODALS
    This file contains all receipt and financial reporting modals
    Total: 6 large modals for comprehensive financial management
    ======================================== --}}

<style>
/* ============================================================================
   CLIENT FUNDS LEDGER - DRAG AND DROP ZONE STYLES
   ============================================================================ */

.ledger-drag-drop-zone {
    border: 2px dashed #ccc;
    border-radius: 6px;
    padding: 15px 12px;
    text-align: center;
    background-color: #f9f9f9;
    cursor: pointer !important;
    transition: all 0.3s ease;
    min-height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0;
    width: auto;
    min-width: 250px;
    max-width: 300px;
    position: relative;
    z-index: 1;
}

.ledger-drag-drop-zone:hover {
    border-color: #007bff;
    background-color: #f0f8ff;
    transform: translateY(-2px);
}

.ledger-drag-drop-zone.drag_over {
    border-color: #28a745;
    background-color: #e8f5e9;
    border-width: 3px;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
}

.ledger-drag-drop-zone .drag-zone-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.ledger-drag-drop-zone .drag-zone-inner i {
    font-size: 28px;
    color: #2563eb;
    transition: all 0.3s ease;
}

.ledger-drag-drop-zone:hover .drag-zone-inner i {
    transform: scale(1.1);
    color: #0056b3;
}

.ledger-drag-drop-zone .drag-zone-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.ledger-drag-drop-zone .drag-zone-text {
    font-size: 13px;
    font-weight: 500;
    color: #333;
    margin: 0;
    line-height: 1.3;
}

.ledger-drag-drop-zone .drag-zone-formats {
    font-size: 11px;
    color: #4b5563;
    line-height: 1.2;
}

.ledger-drag-drop-zone.file-selected {
    border-color: #28a745;
    background-color: #f0fff4;
}

/* Selected Files Display */
.ledger-selected-files-display {
    padding: 8px;
    background-color: #e8f5e9;
    border-radius: 6px;
    border: 1px solid #c3e6cb;
    margin-bottom: 10px;
    max-width: 350px;
}

.ledger-selected-files-display .files-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
    margin-bottom: 8px;
}

.ledger-selected-files-display .file-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    background-color: white;
    border-radius: 4px;
    font-size: 13px;
}

.ledger-selected-files-display .file-item i {
    color: #28a745;
}

.ledger-selected-files-display .file-item .file-name {
    flex: 1;
    color: #155724;
    word-break: break-word;
}

.ledger-selected-files-display .file-item .remove-file {
    padding: 0;
    margin: 0;
    line-height: 1;
    color: #dc3545;
    cursor: pointer;
}

.ledger-selected-files-display .file-item .remove-file:hover {
    opacity: 0.8;
}

.ledger-selected-files-display .remove-all-files {
    padding: 5px 10px;
    font-size: 12px;
}
</style>

{{-- 1. Create Receipt Modal (Multi-Type: Client Funds Ledger, Invoice, Office Receipt) --}}
<div class="modal fade custom_modal" id="createreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">
		  	<div class="modal-header">
				<h5 class="modal-title">Create Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
		    </div>

		  	<div class="modal-body">
				<!-- Radio Button Selection -->
				<div class="form-group">
			  		<label><strong>Select Report Type:</strong></label><br>
			  		<label class="mr-3">
						<input type="radio" name="receipt_type" value="client_receipt" checked> Client Funds Ledger
			  		</label>

			  		<label class="mr-3">
						<input type="radio" name="receipt_type" value="invoice_receipt"> Invoices Issued
			  		</label>

			  		<label class="mr-3">
						<input type="radio" name="receipt_type" value="office_receipt"> Direct Office Receipts
			  		</label>
				</div>

				<!-- Client Funds Ledger Form -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveaccountreport')}}" name="client_receipt_form" autocomplete="off" id="client_receipt_form" enctype="multipart/form-data">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="1">
                    <input type="hidden" name="client_ledger_balance_amount" id="client_ledger_balance_amount" value="">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_ledger" value="">
					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                       	<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:10%;color: #34395e;">Trans. Date</th>
                                            <th style="width:10%;color: #34395e;">Entry Date</th>
                                            <th style="width:10%;color: #34395e;">Type</th>
                                            <th style="width:11%;color: #34395e;" title="Required when Type is Fee Transfer">Invoice</th>
                                            <th style="width:10%;color: #34395e;">Payment method</th>
                                            <th style="width:22%;color: #34395e;">Description</th>
                                            <th style="width:10%;color: #34395e;">Funds In (+)</th>
											<th style="width:10%;color: #34395e;">Funds Out (-)</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem">
                                        <tr class="clonedrow">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control client_fund_ledger_type" name="client_fund_ledger_type[]" data-valid="required">
                                                    <option value="">Select</option>
                                                    <option value="Deposit">Deposit</option>
                                                    <option value="Fee Transfer">Fee Transfer</option>
                                                    <option value="Disbursement">Disbursement</option>
													<option value="Refund">Refund</option>
                                                </select>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="ledger-invoice-placeholder text-muted">—</span>
                                                <select class="form-control invoice_no_cls" name="invoice_no[]" style="display:none;">
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control ledger-payment-method" name="payment_method[]">
                                                    <option value="">—</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank transfer">Bank transfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                    <option value="Refund">Refund</option>
                                                </select>
                                                <div class="ledger-eftpos-surcharge-block" style="display:none;margin-top:6px;">
                                                    <label class="text-muted" style="font-size:11px;margin:0;display:block;">Card surcharge ($)</label>
                                                    <input type="text" class="form-control ledger-eftpos-surcharge-input" name="eftpos_surcharge_amount[]" inputmode="decimal" autocomplete="off" placeholder="0.00" style="font-size:12px;padding:4px 8px;" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="">
                                                </div>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control deposit_amount_per_row" name="deposit_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" readonly/>
                                            </td>

											<td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_per_row" name="withdraw_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" readonly/>
                                            </td>

                                            <td>
                                                <a class="removeitems" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="6" style="text-align:right;color: #34395e;">Totals</td>
                                            <td>
                                                <span class="total_deposit_amount_all_rows" style="color: #34395e;"></span>
                                            </td>
											<td colspan="2">
                                                <span class="total_withdraw_amount_all_rows" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">

                            <div class="upload_client_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="client_receipt">
                                
                                <!-- NEW: Drag and Drop Zone -->
                                <div class="ledger-drag-drop-zone" id="ledgerDragDropZone">
                                    <div class="drag-zone-inner">
                                        @icon('fa-cloud-upload-alt')
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Multiple files allowed)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep existing file input (hidden, used as fallback) -->
                                <input class="docclientreceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <!-- File selection display (shown after files are selected) -->
                                <div id="ledger-selected-files-display" class="ledger-selected-files-display" style="display: none;">
                                    <div id="ledger-files-list" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files" title="Remove all files">
                                        @icon('fa-times') Clear All
                                    </button>
                                </div>
                                
                                <!-- Keep existing file-selection-hint for compatibility -->
                                <span class="file-selection-hint" style="margin-left: 10px; color: #34395e;"></span>
                            </div>
							<button onclick="customValidate('client_receipt_form')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

				<!-- Invoice Receipt Form -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveinvoicereport')}}" name="invoice_receipt_form" autocomplete="off" id="invoice_receipt_form" style="display:none;">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="3">
					<input type="hidden" name="receipt_id" id="receipt_id" value="">
					<input type="hidden" name="function_type" id="function_type" value="">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_invoice" value="">
					<input type="hidden" name="submission_token" id="invoice_submission_token" value="">

					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No – <span class="text-muted">Auto-assigned on save</span></b>
                            </div>
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:13%;color: #34395e;">Gst Incl.</th>
                                            <th style="width:5%;color: #34395e;">Payment Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control payment_type_invoice_per_row" name="payment_type[]">
                                                    <option value="">Select</option>
                                                    <option value="Professional Fee">Professional Fee</option>
                                                    <option value="Department Charges">Department Charges</option>
                                                    <option value="Surcharge">Surcharge</option>
                                                    <option value="Disbursements">Disbursements</option>
                                                    <option value="Other Cost">Other Cost</option>
                                                    <option value="Discount">Discount</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row payment_type_invoice_per_row" name="withdraw_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_invoice">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('invoice_receipt_form','draft')" type="button" class="btn btn-primary" style="margin:0px !important;">Draft Invoice</button>
							<button onclick="customValidate('invoice_receipt_form','final')" type="button" class="btn btn-primary" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

				<!-- Office Receipt Form -->
				<form class="form-type"  method="post" action="{{URL::to('/clients/saveofficereport')}}" name="office_receipt_form" autocomplete="off" id="office_receipt_form" style="display:none;">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="2">
                    <input type="hidden" name="client_matter_id" id="client_matter_id_office" value="">
                    <input type="hidden" name="save_type" class="save_type_office" value="">
					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								<input type="text" name="client" class="form-control" data-valid="required" autocomplete="off" placeholder="" value="{{ $fetchedData->first_name.' '.$fetchedData->last_name }}">
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <!-- Quick Actions Toolbar -->
                                <div class="quick-actions-toolbar" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea;">
                                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <span style="font-weight: 600; color: #667eea;">
                                            @icon('fa-bolt') Quick Actions:
                                        </span>
                                        <button type="button" class="btn btn-sm btn-outline-primary paste-clipboard-btn" 
                                                title="Paste amount from clipboard">
                                            @icon('fa-clipboard') Paste from Clipboard
                                            <span class="clipboard-preview" style="margin-left: 5px; font-weight: bold; color: #28a745;"></span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-info repeat-last-entry-btn"
                                                title="Repeat last office receipt entry">
                                            @icon('fa-redo') Repeat Last Entry
                                        </button>
                                        <small style="margin-left: auto; color: #4b5563;">
                                            @icon('fa-info-circle') Use these shortcuts to speed up data entry
                                        </small>
                                    </div>
                                </div>

                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Invoice No</th>
                                            <th style="width:5%;color: #34395e;">Payment method</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Received</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_office">
                                        <tr class="clonedrow_office">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_office" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_office" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control office-receipt-payment-method" name="payment_method[]" data-valid="required" >
                                                    <option value="">Select</option>
													<option value="Cash">Cash</option>
                                                    <option value="Bank transfer">Bank transfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                    <option value="Refund">Refund</option>
                                                </select>
                                                <div class="office-eftpos-surcharge-block" style="display:none;margin-top:6px;">
                                                    <label class="text-muted" style="font-size:11px;margin:0;display:block;">Card surcharge ($)</label>
                                                    <input type="text" class="form-control office-eftpos-surcharge-input" name="eftpos_surcharge_amount[]" inputmode="decimal" autocomplete="off" placeholder="0.00" style="font-size:12px;padding:4px 8px;" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="">
                                                </div>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_deposit_amount_office" name="deposit_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_office" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_deposit_amount_all_rows_office" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_office">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                            <div class="upload_office_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                
                                <!-- NEW: Drag and Drop Zone -->
                                <div class="ledger-drag-drop-zone office-drag-drop-zone" id="officeDragDropZone">
                                    <div class="drag-zone-inner">
                                        @icon('fa-cloud-upload-alt')
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Multiple files allowed)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep existing file input (hidden, used as fallback) -->
                                <input class="docofficereceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <!-- File selection display (shown after files are selected) -->
                                <div id="office-selected-files-display" class="ledger-selected-files-display" style="display: none;">
                                    <div id="office-files-list" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files-office" title="Remove all files">
                                        @icon('fa-times') Clear All
                                    </button>
                                </div>
                                
                                <!-- Keep existing file-selection-hint for compatibility -->
                                <span class="file-selection-hint1" style="margin-right: 10px; color: #34395e;"></span>
                            </div>

                            <button onclick="customValidate('office_receipt_form','draft')" type="button" class="btn btn-secondary" style="margin: 0px !important;">@icon('fa-save') Save Draft</button>
                            <button onclick="customValidate('office_receipt_form','final')" type="button" class="btn btn-primary" style="margin: 0px !important;">@icon('fa-check') Save and Finalize</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
		  	</div>
		</div>
	</div>
</div>

{{-- 2. Adjust Invoice Receipt Modal --}}
<!-- Create Adjust Invoice Receipt  -->
<div class="modal fade custom_modal" id="createadjustinvoicereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
		  	<div class="modal-header">
				<h5 class="modal-title">Adjust Invoice</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
		    </div>

		  	<div class="modal-body">
				<!-- Invoice Receipt Form -->
				<form class="form-type" method="post" action="{{URL::to('/clients/saveadjustinvoicereport')}}" name="adjust_invoice_receipt_form" autocomplete="off" id="adjust_invoice_receipt_form">
					@csrf
					<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
					<input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
					<input type="hidden" name="receipt_type" value="3">
					<input type="hidden" name="receipt_id" id="receipt_id" value="">
					<input type="hidden" name="function_type" id="function_type" value="add">

					<div class="row">
						<div class="col-3 col-md-3 col-lg-3">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								{!! html()->text('client')->class('form-control')->attribute('data-valid', 'required')->attribute('autocomplete', 'off')->attribute('placeholder', '') !!}
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12">
                            <div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No – <span class="text-muted">Auto-assigned on save</span></b>
                            </div>
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:13%;color: #34395e;">Gst Incl.</th>
                                            <th style="width:5%;color: #34395e;">Payment Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control" name="payment_type[]">
                                                    <option value="">Select</option>
                                                    <option value="Adjust">Adjust/Discount</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('adjust_invoice_receipt_form','final')" type="button" class="btn btn-primary" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 3. Create Client Receipt Modal --}}
<!-- Create Client Receipt Modal -->
<div class="modal fade custom_modal" id="createclientreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Client Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveaccountreport')}}" name="create_client_receipt" autocomplete="off" id="create_client_receipt" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="1">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								{!! html()->text('client')->class('form-control')->attribute('data-valid', 'required')->attribute('autocomplete', 'off')->attribute('placeholder', '') !!}
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="agent_id">Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="agent_id" id="sel_client_agent_id">
                                    <option value="">Select Agent</option>
                                    @foreach(\App\Models\AgentDetails::where('status',1)->get() as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->full_name}} ({{@$aplist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Trans. No</th>
                                            <th style="width:5%;color: #34395e;">Payment Method</th>
                                            <th style="width:35%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Deposit</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem">
                                        <tr class="clonedrow">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="payment_method[]">
                                                    <option value="">Select</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank tansfer">Bank tansfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control deposit_amount_per_row" name="deposit_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_deposit_amount_all_rows" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">

                            <div class="upload_client_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="client_receipt">
                                <a href="javascript:;" class="btn btn-primary">@icon('fa-plus') Add Document</a>
                                <input class="docclientreceiptupload" type="file" name="document_upload[]"/>
                            </div>

                            <button onclick="customValidate('create_client_receipt')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

			</div>
		</div>
	</div>
</div>

{{-- 4. Create Invoice Receipt Modal --}}
<!-- Create Invoice Receipt Modal -->
<div class="modal fade custom_modal" id="createinvoicereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Invoice</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="invoice_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveinvoicereport')}}" name="create_invoice_receipt" autocomplete="off" id="create_invoice_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="3">
                <input type="hidden" name="receipt_id" value="">
                <input type="hidden" name="function_type" value="add">

					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								{!! html()->text('client')->class('form-control')->attribute('data-valid', 'required')->attribute('autocomplete', 'off')->attribute('placeholder', '') !!}
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="agent_id">Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="agent_id" id="sel_invoice_agent_id">
                                    <option value="">Select Agent</option>
                                    @foreach(\App\Models\AgentDetails::where('status',1)->get() as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->full_name}} ({{@$aplist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
                            <div class="Invoic_no_cls" style="text-align: center;">
                                <b>Invoice No – <span class="text-muted">Auto-assigned on save</span></b>
                            </div>
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Trans. No</th>
                                            <th style="width:13%;color: #34395e;">Gst Incl.</th>
                                            <th style="width:5%;color: #34395e;">Payment Type</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Amount</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_invoice">
                                        <tr class="clonedrow_invoice">
                                            <td>
                                                <input name="id[]" type="hidden" value="" />
                                                <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_invoice" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_invoice" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control" name="gst_included[]">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-control payment_type_invoice_per_row" name="payment_type[]">
                                                    <option value="">Select</option>
                                                    <option value="Professional Fee">Professional Fee</option>
                                                    <option value="Department Charges">Department Charges</option>
                                                    <option value="Surcharge">Surcharge</option>
                                                    <option value="Disbursements">Disbursements</option>
                                                    <option value="Other Cost">Other Cost</option>
                                                    <option value="Discount">Discount</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row payment_type_invoice_per_row" name="withdraw_amount[]" type="text" oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1').replace(/(\.\d{2}).*/g, '$1')" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_invoice" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_invoice" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_invoice">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">
                            <input type="hidden" name="save_type" class="save_type" value="">
                            <button onclick="customValidate('create_invoice_receipt','draft')" type="button" class="btn btn-primary" style="margin:0px !important;">Draft Invoice</button>
							<button onclick="customValidate('create_invoice_receipt','final')" type="button" class="btn btn-primary" style="margin:0px !important;">Create Invoice</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- 5. Create Office Receipt Modal --}}
<!-- Create Office Receipt Modal -->
<div class="modal fade custom_modal" id="createofficereceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Office Receipt</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="office_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/saveofficereport')}}" name="create_office_receipt" autocomplete="off" id="create_office_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="2">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								{!! html()->text('client')->class('form-control')->attribute('data-valid', 'required')->attribute('autocomplete', 'off')->attribute('placeholder', '') !!}
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="agent_id">Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="agent_id" id="sel_office_agent_id">
                                    <option value="">Select Agent</option>
                                    @foreach(\App\Models\AgentDetails::where('status',1)->get() as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->full_name}} ({{@$aplist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:15%;color: #34395e;">Receipt No</th>
                                            <th style="width:15%;color: #34395e;">Invoice No</th>
                                            <th style="width:5%;color: #34395e;">Payment method</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:14%;color: #34395e;">Received</th>
                                            <th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_office">
                                        <tr class="clonedrow_office">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_office" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_office" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_office" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_office" name="trans_no[]" type="hidden" value="" />
                                            </td>
                                            <td>
                                                <select class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control" name="payment_method[]" data-valid="required" >
                                                    <option value="">Select</option>
                                                    <option value="Cash">Cash</option>
                                                    <option value="Bank tansfer">Bank tansfer</option>
                                                    <option value="EFTPOS">EFTPOS</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_withdrawal_amount_office" name="withdraw_amount[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <a class="removeitems_office" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:83.6%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2">
                                                <span class="total_withdraw_amount_all_rows_office" style="color: #34395e;"></span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_office">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right" style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                            <div class="upload_office_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="office_receipt">
                                
                                <!-- NEW: Drag and Drop Zone -->
                                <div class="ledger-drag-drop-zone office-drag-drop-zone" id="officeDragDropZone2">
                                    <div class="drag-zone-inner">
                                        @icon('fa-cloud-upload-alt')
                                        <div class="drag-zone-content">
                                            <p class="drag-zone-text">Drag files here or <strong>click to browse</strong></p>
                                            <small class="drag-zone-formats">Accepted: PDF, JPG, PNG, DOC, DOCX (Multiple files allowed)</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep existing file input (hidden, used as fallback) -->
                                <input class="docofficereceiptupload d-none" type="file" name="document_upload[]" multiple style="display: none;">
                                
                                <!-- File selection display (shown after files are selected) -->
                                <div id="office-selected-files-display2" class="ledger-selected-files-display" style="display: none;">
                                    <div id="office-files-list2" class="files-list"></div>
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-all-files-office" title="Remove all files">
                                        @icon('fa-times') Clear All
                                    </button>
                                </div>
                            </div>

                            <button onclick="customValidate('create_office_receipt')" type="button" class="btn btn-primary" style="margin: 0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>

			</div>
		</div>
	</div>
</div>

{{-- 6. Create Journal Modal --}}
<!-- Create Journal Modal -->
<div class="modal fade custom_modal" id="createjournalreceiptmodal" tabindex="-1" role="dialog" aria-labelledby="create_noteModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Create Journal</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <input type="hidden"  id="journal_top_value_db" value="">
				<form method="post" action="{{URL::to('/clients/savejournalreport')}}" name="create_journal_receipt" autocomplete="off" id="create_journal_receipt" >
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                <input type="hidden" name="loggedin_staffid" value="{{@Auth::user()->id}}">
                <input type="hidden" name="receipt_type" value="4">
					<div class="row">
						<div class="col-6 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="client">Client <span class="span_req">*</span></label>
								{!! html()->text('client')->class('form-control')->attribute('data-valid', 'required')->attribute('autocomplete', 'off')->attribute('placeholder', '') !!}
								<span class="custom-error title_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

                        <div class="col-6 col-md-6 col-lg-6">
                            <div class="form-group">
                                <label for="agent_id">Agent <span class="span_req">*</span></label>
                                <select data-valid="required" class="form-control mm-select" name="agent_id" id="sel_journal_agent_id">
                                    <option value="">Select Agent</option>
                                    @foreach(\App\Models\AgentDetails::where('status',1)->get() as $aplist)
                                        <option value="{{$aplist->id}}">{{@$aplist->full_name}} ({{@$aplist->email}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
                                <table border="1" style="margin-bottom:0rem !important;" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <thead>
                                        <tr>
                                            <th style="width:15%;color: #34395e;">Trans. Date</th>
                                            <th style="width:15%;color: #34395e;">Entry Date</th>
                                            <th style="width:12%;color: #34395e;">Trans. No</th>
                                            <th style="width:13%;color: #34395e;">Invoice No</th>
                                            <th style="width:25%;color: #34395e;">Description</th>
                                            <th style="width:15%;color: #34395e;">Transfer</th>
											<th style="width:1%;color: #34395e;"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="productitem_journal">
                                        <tr class="clonedrow_journal">
                                            <td>
                                                <input data-valid="required"  class="form-control report_date_fields_journal" name="trans_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input data-valid="required" class="form-control report_entry_date_fields_journal" name="entry_date[]" type="text" value="" />
                                            </td>
                                            <td>
                                                <input class="form-control unique_trans_no_journal" type="text" value="" readonly/>
                                                <input class="unique_trans_no_hidden_journal" name="trans_no[]" type="hidden" value="" />
                                            </td>

                                            <td>
                                                <select data-valid="required" class="form-control invoice_no_cls"  name="invoice_no[]">
                                                </select>
                                            </td>

                                            <td>
                                                <input data-valid="required" class="form-control" name="description[]" type="text" value="" />
                                            </td>

                                            <td>
                                                <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>
                                                <input data-valid="required" style="display: inline-block;" class="form-control total_withdrawal_amount_journal" name="withdraw_amount[]" type="text" value="" />
                                            </td>

					                        <td>
                                                <a class="removeitems_journal" href="javascript:;">@icon('fa-times')</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <table border="1" class="table text_wrap table-striped table-hover table-md vertical_align">
                                    <tbody>
                                        <tr>
                                            <td colspan="5" style="width:48.99%;text-align:right;color: #34395e;">Totals</td>
                                            <td colspan="2" style="width:10.99%;">
                                                <span class="total_withdraw_amount_all_rows_journal" style="color: #34395e;"></span>
                                            </td>
										</tr>
                                    </tbody>
                                </table>
                            </div>
						</div>

                        <div class="col-3 col-md-3 col-lg-3">
                            <a href="javascript:;" class="openproductrinfo_journal">@icon('fa-plus') Add New Line</a>
                        </div>

						<div class="col-9 col-md-9 col-lg-9 text-right">

                            <div class="upload_journal_receipt_document" style="display:inline-block;">
                                <input type="hidden" name="type" value="client">
                                <input type="hidden" name="doctype" value="journal_receipt">
                                <a href="javascript:;" class="btn btn-primary">@icon('fa-plus') Add Document</a>

                                <input class="docjournalreceiptupload" type="file" name="document_upload[]"/>
                            </div>

                            <button onclick="customValidate('create_journal_receipt')" type="button" class="btn btn-primary" style="margin:0px !important;">Save Entry</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
                    </div>
				</form>
            </div>
		</div>
	</div>
</div>

