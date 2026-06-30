{{-- EOI/ROI Tab Content --}}
<div class="tab-pane" id="eoiroi-tab">
    <div class="eoi-roi-container">
        <div class="eoi-roi-header">
            <h2>
                @icon('fa-passport') EOI / ROI Management
            </h2>
            <button type="button" class="btn btn-primary" id="btn-add-eoi">
                @icon('fa-plus') Add New EOI
            </button>
        </div>

        {{-- EOI/ROI Entries Table --}}
        <div class="eoi-roi-section">
            <h3>EOI / ROI Entries</h3>
            <div class="table-responsive">
                <table class="table table-hover" id="eoi-roi-table">
                    <thead>
                        <tr>
                            <th>EOI Ref</th>
                            <th>Subclass(es)</th>
                            <th>State(s)</th>
                            <th>Occupation</th>
                            <th>Points</th>
                            <th>Submission</th>
                            <th>ROI</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="eoi-roi-tbody">
                        <tr class="no-data-row">
                            <td colspan="8" class="text-center">
                                @icon('fa-info-circle') No EOI/ROI records found. Click "Add New EOI" to get started.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- EOI/ROI Form --}}
        <div class="eoi-roi-section" id="eoi-roi-form-section" style="display:none;">
            <h3 id="form-title">Add / Edit EOI Record</h3>
            <form id="eoi-roi-form">
                <input type="hidden" id="eoi-id" name="id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="eoi-number">EOI Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="eoi-number" name="eoi_number" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="eoi-occupation">Occupation (ANZSCO) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control occupation-autocomplete" id="eoi-occupation" 
                                   name="eoi_occupation" placeholder="Search occupation..." autocomplete="off" required>
                            <div class="autocomplete-items"></div>
                            
                            <!-- Hidden fields to store selected occupation data -->
                            <input type="hidden" id="eoi-anzsco-id" name="anzsco_occupation_id">
                            <input type="hidden" id="eoi-anzsco-code" name="anzsco_code">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Subclass(es) <span class="text-danger">*</span></label>
                            <div class="checkbox-group">
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="eoi_subclasses[]" value="189"> 189
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="eoi_subclasses[]" value="190"> 190
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="eoi_subclasses[]" value="491"> 491
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="eoi_subclasses[]" value="491-Family"> 491-Family
                                </label>
                            </div>
                            <small class="form-text text-muted">Select all applicable subclasses</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>State(s) <span class="text-danger">*</span></label>
                            <select class="form-control mm-select-multiple" id="eoi-states" name="eoi_states[]" 
                                    multiple="multiple" required style="min-width: 200px;">
                                <option value="ACT">ACT</option>
                                <option value="NSW">NSW</option>
                                <option value="NT">NT</option>
                                <option value="QLD">QLD</option>
                                <option value="SA">SA</option>
                                <option value="TAS">TAS</option>
                                <option value="VIC">VIC</option>
                                <option value="WA">WA</option>
                                <option value="FED">FED (Federal)</option>
                            </select>
                            <small class="form-text text-muted">Select all applicable states</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="eoi-points">Points</label>
                            <input type="number" class="form-control" id="eoi-points" name="eoi_points" 
                                   min="0" max="200">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="eoi-submission-date">Submission Date</label>
                            <input type="text" class="form-control eoi-datepicker" id="eoi-submission-date" 
                                   name="eoi_submission_date" placeholder="dd/mm/yyyy">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="eoi-status">Status</label>
                            <select class="form-control" id="eoi-status" name="eoi_status">
                                <option value="draft">Draft</option>
                                <option value="submitted">Submitted</option>
                                <option value="invited">Invited</option>
                                <option value="nominated">Nominated</option>
                                <option value="rejected">Rejected</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="eoi-invitation-date">Invitation Date</label>
                            <input type="text" class="form-control eoi-datepicker" id="eoi-invitation-date" 
                                   name="eoi_invitation_date" placeholder="dd/mm/yyyy">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="eoi-nomination-date">Nomination Date</label>
                            <input type="text" class="form-control eoi-datepicker" id="eoi-nomination-date" 
                                   name="eoi_nomination_date" placeholder="dd/mm/yyyy">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="eoi-roi">ROI Reference</label>
                            <input type="text" class="form-control" id="eoi-roi" name="eoi_roi">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="eoi-password">EOI Portal Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="eoi-password" 
                                       name="eoi_password" autocomplete="new-password">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" 
                                            id="toggle-password" title="Show/Hide Password">
                                        @icon('fa-eye')
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Password will be encrypted</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="d-block">Document Attachments</label>
                            <div class="alert alert-info mt-2 mb-0">
                                @icon('fa-info-circle')
                                <strong>Attaching Documents:</strong> When you send the confirmation email to the client, you'll be able to select which visa documents to attach from a list. No pre-mapping required!
                            </div>
                            <div class="eoi-map-help mt-2">
                                <button type="button" class="btn btn-link btn-sm p-0 text-info" data-bs-toggle="collapse" data-bs-target="#eoi-map-help-collapse" aria-expanded="false">
                                    {{-- OLD help text removed --}}
                                </button>
                                <div class="collapse mt-1" id="eoi-map-help-collapse">
                                    <div class="small text-muted border rounded p-2 bg-light">
                                        <strong>To map documents to these buttons:</strong>
                                        <ol class="mb-0 pl-3">
                                            <li>Click any button above to go to the <strong>Visa Documents</strong> tab.</li>
                                            <li><strong>One EOI:</strong> Create categories named exactly <strong>EOI Summary</strong>, <strong>Points Summary</strong>, <strong>ROI Draft</strong> (Add Category if needed).</li>
                                            <li><strong>Two or more EOIs:</strong> Create <em>per-EOI</em> categories so the right documents attach to the right EOI: name them <strong>EOI Summary - E0121253652</strong>, <strong>Points Summary - E0121253652</strong>, <strong>ROI Draft - E0121253652</strong> (use the actual EOI number). Repeat for each EOI (e.g. … - E0121253653). When you send the confirmation email for an EOI, only documents from that EOI’s categories are attached.</li>
                                            <li>Open each category (subtab) and add documents via <strong>Add Checklist</strong>, <strong>Bulk Upload</strong>, or drag-and-drop.</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success" id="btn-save-eoi">
                        @icon('fa-save') Save EOI
                    </button>
                    <button type="button" class="btn btn-secondary" id="btn-cancel-eoi">
                        @icon('fa-times') Cancel
                    </button>
                    <button type="button" class="btn btn-danger float-right" id="btn-delete-eoi" 
                            style="display:none;">
                        @icon('fa-trash') Delete
                    </button>
                </div>

                {{-- Workflow Section - Compact Version inside form --}}
                <div id="workflow-section-compact" style="display:none; margin-top: 20px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                    <h4 style="font-size: 16px; margin-bottom: 15px; color: #333;">
                        @icon('fa-tasks') Workflow & Client Confirmation
                    </h4>
                    <div id="workflow-content-compact">
                        <!-- Workflow content will be loaded dynamically -->
                    </div>
                </div>
            </form>
        </div>

        {{-- Points Summary Section --}}
        <div class="eoi-roi-section" id="points-summary-section">
            <div class="points-summary-header">
                <h3>
                    @icon('fa-calculator') Points Summary
                </h3>
                <button type="button" class="btn btn-sm btn-info" id="btn-refresh-points">
                    @icon('fa-sync-alt') Refresh
                </button>
            </div>
            
            <div class="points-summary-controls">
                <label>Calculate for Subclass:</label>
                <select class="form-control" id="points-subclass-selector" style="width: auto; display: inline-block;">
                    <option value="189">Subclass 189</option>
                    <option value="190">Subclass 190</option>
                    <option value="491">Subclass 491</option>
                    <option value="491-Family">Subclass 491-Family</option>
                </select>
            </div>

            <div id="points-summary-content">
                <div class="text-center text-muted">
                    @icon('fa-spinner', ['spin' => true]) Loading points calculation...
                </div>
            </div>
        </div>
    </div>

    {{-- EOI Confirmation Email Compose Modal (NEW - adapted from existing email compose) --}}
    <div id="eoi-compose-modal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send EOI Confirmation Email</h5>
                    <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="eoi-compose-form" autocomplete="off">
                        @csrf
                        <input type="hidden" id="eoi-compose-eoi-id" value="">
                        <input type="hidden" id="eoi-compose-client-id" value="">
                        {{-- Client ID will be set dynamically by JavaScript --}}
                        
                        {{-- To Field (readonly) --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="eoi-email-to">To <span class="text-danger">*</span></label>
                                    <input type="text" readonly class="form-control" id="eoi-email-to" placeholder="Loading...">
                                    <small class="form-text text-muted" id="eoi-email-to-help"></small>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Subject Field --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="eoi-email-subject">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" id="eoi-email-subject" class="form-control" 
                                           placeholder="Enter Subject" maxlength="255" required>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Body Field (read-only for Phase 1) --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="eoi-email-body">Email Body (Preview)</label>
                                    <div id="eoi-email-body" 
                                         style="border: 1px solid #ddd; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto; background-color: #f9f9f9;">
                                        <div style="text-align: center; color: #999;">Loading preview...</div>
                                    </div>
                                    <small class="form-text text-muted">
                                        @icon('fa-info-circle') This shows how the email will appear to your client. Body is generated from template and contains confirmation links.
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Attachments Section (adapted from uploadchecklists pattern) --}}
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Attachments (Select from Visa Documents)</label>
                                    <div class="border p-3" style="max-height: 350px; overflow-y: auto; background-color: #f8f9fa;">
                                        <div id="eoi-attachment-list">
                                            <div class="text-center text-muted py-3">
                                                @icon('fa-spinner', ['spin' => true]) Loading documents...
                                            </div>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted mt-2">
                                        @icon('fa-paperclip') Maximum 10 documents, 25MB total
                                        <span id="eoi-attachment-summary" class="ml-2"></span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Actions --}}
                        <div class="row">
                            <div class="col-12">
                                <button type="button" class="btn btn-primary" id="btn-eoi-send-email">
                                    @icon('fa-paper-plane') Send Email
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    @icon('fa-times') Cancel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.eoi-roi-container {
    padding: 20px;
}

.eoi-roi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}

.eoi-roi-header h2 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.eoi-roi-section {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.eoi-roi-section h3 {
    font-size: 18px;
    margin-bottom: 20px;
    color: #333;
}

#eoi-roi-table {
    font-size: 14px;
}

#eoi-roi-table thead {
    background-color: #f8f9fa;
}

#eoi-roi-table tbody tr {
    cursor: pointer;
}

#eoi-roi-table tbody tr:hover {
    background-color: #f5f5f5;
}

.eoi-ref-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.eoi-ref-link:hover {
    color: #0056b3;
    text-decoration: underline;
}

/* Tom Select (#eoi-states multi) */
#eoi-states + .ts-wrapper {
    width: 100% !important;
}
#eoi-states + .ts-wrapper .ts-control {
    min-height: 38px !important;
    padding: 6px 8px !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.25rem !important;
    background-color: #fff !important;
}
#eoi-states + .ts-wrapper .item {
    background-color: #e3f2fd;
    border: 1px solid #2196f3;
    border-radius: 4px;
    color: #1976d2;
    font-size: 14px;
    padding: 4px 8px;
    margin: 2px 4px 2px 0;
}

.checkbox-inline {
    margin-right: 15px;
}

/* Map to Visa Documents buttons – solid colours for visibility on light background */
.eoi-doc-buttons .eoi-map-doc-btn {
    background-color: #0d6efd;
    color: #fff;
    border: 1px solid #0a58ca;
}
.eoi-doc-buttons .eoi-map-doc-btn:hover {
    background-color: #0b5ed7;
    color: #fff;
    border-color: #0a58ca;
}
.eoi-doc-buttons .eoi-map-doc-btn:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.5);
}

.form-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.points-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e0e0e0;
}

.points-summary-header h3 {
    margin: 0;
    font-size: 24px;
    color: #333;
}

.points-summary-controls {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.points-summary-controls label {
    margin-right: 10px;
    font-weight: 600;
}

.points-summary-layout {
    display: flex;
    gap: 30px;
    margin-top: 20px;
}

.points-summary-main {
    flex: 2;
}

.points-summary-sidebar {
    flex: 1;
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    height: fit-content;
}

.points-total-display {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 20px;
}

.points-total-number {
    font-size: 36px;
    font-weight: bold;
    margin: 0;
}

.points-total-label {
    font-size: 14px;
    opacity: 0.9;
    margin: 5px 0 0 0;
}

.points-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.points-table th {
    background-color: #f8f9fa;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}

.points-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
}

.points-table tr:last-child td {
    border-bottom: none;
}

.points-category {
    font-weight: 600;
    color: #333;
}

.points-details {
    color: #6c757d;
    font-size: 14px;
}

.points-value {
    font-weight: bold;
    color: #28a745;
    text-align: right;
}

.points-warnings-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 15px;
}

.points-warning {
    padding: 15px;
    margin-bottom: 10px;
    border-left: 4px solid #ffc107;
    background-color: #fff3cd;
    border-radius: 4px;
}

.points-warning.severity-high {
    border-left-color: #dc3545;
    background-color: #f8d7da;
}

.points-warning-icon {
    margin-right: 8px;
}

.points-info-text {
    font-size: 12px;
    color: #6c757d;
    line-height: 1.4;
    margin-top: 15px;
    padding: 10px;
    background-color: #e9ecef;
    border-radius: 4px;
}

.badge-status {
    padding: 5px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-status.draft { background-color: #6c757d; color: white; }
.badge-status.submitted { background-color: #007bff; color: white; }
.badge-status.invited { background-color: #28a745; color: white; }
.badge-status.nominated { background-color: #17a2b8; color: white; }
.badge-status.rejected { background-color: #dc3545; color: white; }
.badge-status.withdrawn { background-color: #6c757d; color: white; }

/* Occupation Autocomplete Styles */
.autocomplete-items {
    position: absolute;
    border: 1px solid #d4d4d4;
    border-bottom: none;
    border-top: none;
    z-index: 99;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    max-height: 200px;
    overflow-y: auto;
    display: none;
}

.autocomplete-item {
    padding: 10px;
    cursor: pointer;
    background-color: #fff;
    border-bottom: 1px solid #d4d4d4;
}

.autocomplete-item:hover {
    background-color: #e9e9e9;
}

.autocomplete-item.selected {
    background-color: #007bff;
    color: white;
}

.anzsco-loading {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.form-group {
    position: relative;
}

/* Workflow Section Styles - Compact Version */
.workflow-box {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 12px 15px;
    margin-bottom: 12px;
}

.workflow-box h5 {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.workflow-box p {
    font-size: 13px;
    margin-bottom: 8px;
    line-height: 1.4;
}

.workflow-box.success {
    background: #d4edda;
    border-color: #28a745;
}

.workflow-box.warning {
    background: #fff3cd;
    border-color: #ffc107;
}

.workflow-box.danger {
    background: #f8d7da;
    border-color: #dc3545;
}

.workflow-box.info {
    background: #d1ecf1;
    border-color: #17a2b8;
}

.workflow-detail {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 12px;
}

.workflow-detail strong {
    min-width: 100px;
    font-size: 12px;
}

.workflow-actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.workflow-actions .btn {
    font-size: 12px;
    padding: 5px 12px;
}

.amendment-notes {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 10px;
    margin-top: 8px;
    font-size: 12px;
    line-height: 1.5;
}

.amendment-notes strong {
    font-size: 12px;
}

.workflow-timeline {
    margin-top: 20px;
}

.timeline-item {
    position: relative;
    padding-left: 40px;
    padding-bottom: 20px;
    border-left: 2px solid #e0e0e0;
}

.timeline-item:last-child {
    border-left-color: transparent;
    padding-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -10px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    border: 2px solid #007bff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
}

.timeline-item.success .timeline-icon {
    border-color: #28a745;
    color: #28a745;
}

.timeline-item.warning .timeline-icon {
    border-color: #ffc107;
    color: #ffc107;
}

.timeline-item.danger .timeline-icon {
    border-color: #dc3545;
    color: #dc3545;
}

.timeline-content {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 12px;
}

.timeline-content h5 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 5px;
}

.timeline-content p {
    font-size: 13px;
    color: #666;
    margin-bottom: 5px;
}

.timeline-content small {
    font-size: 12px;
    color: #999;
}
</style>

@push('scripts')
<script src="{{ asset('js/clients/eoi-roi.js') }}"></script>
@endpush

