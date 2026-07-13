           <!-- Checklists Tab -->
           <div class="tab-pane" id="checklists-tab">
                <div class="card full-width checklists-container">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">@icon('fa-tasks', ['class' => 'mr-2'])Checklists</h4>
                        <div class="checklist-add-wrapper position-relative">
                            <button type="button" class="btn btn-primary btn-add-checklist" id="btn-add-checklist">
                                @icon('fa-plus', ['class' => 'mr-2'])Create Checklist
                            </button>
                            <div class="checklist-create-dropdown" id="checklist-create-dropdown" style="display: none;">
                                <div class="dropdown-arrow"></div>
                                <div class="dropdown-body">
                                    <h6 class="dropdown-title mb-3">Create New Checklist</h6>
                                    <form id="checklist-create-form" class="checklist-create-form">
                                        <div class="row">
                                            <!-- Migration Agent - same design as Convert Lead To Client -->
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="form-group">
                                                    <label for="checklist_migration_agent">Migration Agent <span class="span_req">*</span></label>
                                                    <select data-valid="required" class="form-control mm-select checklist-field" name="checklist_migration_agent" id="checklist_migration_agent">
                                                        <option value="">Select Migration Agent</option>
                                                        @foreach(\App\Models\Staff::assignmentDropdownMigrationAgentsQuery()->get() as $migAgntlist)
                                                            <option value="{{$migAgntlist->id}}">{{@$migAgntlist->first_name}} {{@$migAgntlist->last_name}} ({{@$migAgntlist->email}})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Person Responsible -->
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="form-group">
                                                    <label for="checklist_person_responsible">Person Responsible <span class="span_req">*</span></label>
                                                    <select data-valid="required" class="form-control mm-select checklist-field" name="checklist_person_responsible" id="checklist_person_responsible">
                                                        <option value="">Select Person Responsible</option>
                                                        @foreach(\App\Models\Staff::assignmentDropdownPersonResponsibleQuery()->get() as $perreslist)
                                                            <option value="{{$perreslist->id}}">{{@$perreslist->first_name}} {{@$perreslist->last_name}} ({{@$perreslist->email}})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Person Assisting -->
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="form-group">
                                                    <label for="checklist_person_assisting">Person Assisting <span class="span_req">*</span></label>
                                                    <select data-valid="required" class="form-control mm-select checklist-field" name="checklist_person_assisting" id="checklist_person_assisting">
                                                        <option value="">Select Person Assisting</option>
                                                        @foreach(\App\Models\Staff::assignmentDropdownPersonAssistingQuery()->get() as $perassislist)
                                                            <option value="{{$perassislist->id}}">{{@$perassislist->first_name}} {{@$perassislist->last_name}} ({{@$perassislist->email}})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Handling Office -->
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="form-group">
                                                    <label for="checklist_office">Handling Office <span class="span_req">*</span></label>
                                                    <select data-valid="required" class="form-control mm-select checklist-field" name="checklist_office" id="checklist_office">
                                                        <option value="">Select Office</option>
                                                        @foreach(\App\Models\Branch::orderBy('office_name')->get() as $office)
                                                            <option value="{{$office->id}}" {{ Auth::user()->office_id == $office->id ? 'selected' : '' }}>{{$office->office_name}}</option>
                                                        @endforeach
                                                    </select>
                                                    <small class="form-text text-muted">
                                                        @icon('fa-building') This matter will be handled by the selected office
                                                    </small>
                                                </div>
                                            </div>

                                            <!-- Select Matter - same design as Convert Lead To Client -->
                                            <div class="col-12 col-md-12 col-lg-12">
                                                <div class="form-group">
                                                    <label for="checklist_matter_select">Select Matter <span class="span_req">*</span></label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="checklist_general_matter" id="checklist_general_matter_checkbox" value="1">
                                                        <label class="form-check-label" for="checklist_general_matter_checkbox">General Matter</label>
                                                    </div>
                                                    <label class="form-check-label">Or Select any option</label>
                                                    <select data-valid="required" class="form-control mm-select checklist-field" name="checklist_matter" id="checklist_matter_select">
                                                        <option value="">Select Matter</option>
                                                        @php
                                                            $matterQuery = \App\Models\Matter::select('id','title')->where('status',1)
                                                                ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                                            $matterList = $matterQuery->get();
                                                        @endphp
                                                        @foreach($matterList as $matterlist)
                                                            <option value="{{$matterlist->id}}" data-matter-id="{{$matterlist->id}}">{{@$matterlist->title}}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Action Buttons - same style as Convert Lead To Client -->
                                            <div class="col-9 col-md-9 col-lg-9 text-right">
                                                <button type="button" class="btn btn-primary btn-continue-cost-assignment">Save</button>
                                                <button type="button" class="btn btn-secondary btn-cancel-checklist">Close</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="checklists-sent-section">
                            <h5 class="font-weight-bold mb-3">@icon('fa-list', ['class' => 'mr-2'])Your Checklists</h5>
                            <div id="checklists-list-container">
                                <?php
                                $checklist_forms = \App\Models\CostAssignmentForm::where('client_id', $fetchedData->id)
                                    ->whereHas('clientMatter', function ($query) {
                                        $query->where('matter_status', 1);
                                    })
                                    ->with(['client', 'agent', 'clientMatter'])
                                    ->orderBy('created_at', 'DESC')
                                    ->get();
                                ?>
                                @if($checklist_forms->isEmpty())
                                    <div class="alert alert-info" id="checklists-empty-state">
                                        @icon('fa-info-circle', ['class' => 'mr-2'])
                                        No checklists yet. Click <strong>Create Checklist</strong> to add one. You'll select matter, assign migration agent and team, complete cost assignment, and create cost agreement.
                                    </div>
                                    <div id="checklists-list" style="display: none;"></div>
                                @else
                                    <div id="checklists-empty-state" style="display: none;"></div>
                                    <div id="checklists-list" class="checklist-accordion">
                                        @foreach($checklist_forms as $form)
                                            @php
                                                $matterName = $form->clientMatter ? ($form->clientMatter->client_unique_matter_no . ($form->clientMatter->matter ? ' - ' . $form->clientMatter->matter->title : '')) : 'N/A';
                                                $clientMatter = $form->clientMatter;
                                                $migrationAgent = $clientMatter ? $clientMatter->migrationAgent : null;
                                                $personResponsible = $clientMatter ? $clientMatter->personResponsible : null;
                                                $personAssisting = $clientMatter ? $clientMatter->personAssisting : null;
                                                $office = $clientMatter ? $clientMatter->office : null;
                                                
                                                // Calculate costs (use saved person-aware totals when available)
                                                $totalDeptCost = $form->TotalDoHACharges ?? null;
                                                if ($totalDeptCost === null) {
                                                    $totalDeptCost =
                                                        ($form->Dept_Base_Application_Charge ?? 0) +
                                                        ($form->Dept_Non_Internet_Application_Charge ?? 0) +
                                                        ($form->Dept_Additional_Applicant_Charge_18_Plus ?? 0) +
                                                        ($form->Dept_Additional_Applicant_Charge_Under_18 ?? 0) +
                                                        ($form->Dept_Subsequent_Temp_Application_Charge ?? 0) +
                                                        ($form->Dept_Second_VAC_Instalment_Charge_18_Plus ?? 0) +
                                                        ($form->Dept_Second_VAC_Instalment_Under_18 ?? 0) +
                                                        ($form->Dept_Nomination_Application_Charge ?? 0) +
                                                        ($form->Dept_Sponsorship_Application_Charge ?? 0);
                                                }
                                                    
                                                $totalSurcharge = $form->TotalDoHASurcharges ?? 0;
                                                $totalOurCost = $form->TotalBLOCKFEE ?? 0;
                                                $totalAdditionalFee1 = floatval($form->additional_fee_1 ?? 0);
                                                $totalCost = $totalOurCost + $totalDeptCost + $totalSurcharge + $totalAdditionalFee1;
                                                
                                                // Check if agreement document exists
                                                $agreementDoc = \App\Models\Document::where('client_matter_id', $form->client_matter_id)
                                                    ->where('doc_type', 'agreement')
                                                    ->latest()
                                                    ->first();
                                            @endphp
                                            <div class="checklist-item-wrapper" data-id="{{ $form->id }}">
                                                <div class="checklist-item-header" data-bs-toggle="collapse" data-bs-target="#checklist-detail-{{ $form->id }}">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="d-flex align-items-center">
                                                            @icon('fa-chevron-right', ['class' => 'checklist-toggle-icon mr-2'])
                                                            <div>
                                                                <strong class="checklist-matter-name">{{ $matterName }}</strong>
                                                                <span class="checklist-date ml-2 small">{{ $form->created_at ? $form->created_at->format('d/m/Y') : '' }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="checklist-summary d-flex align-items-center">
                                                            @if(($fetchedData->type ?? '') === 'lead')
                                                            <button type="button" class="btn btn-sm btn-outline-primary convertLeadToClient mr-2" onclick="event.stopPropagation(); $('#convertLeadToClientModal').modal('show');" title="Convert to Client">
                                                                @icon('fa-user-check', ['class' => 'mr-1']) Convert to Client
                                                            </button>
                                                            @endif
                                                            <span class="badge badge-info mr-2">
                                                                @icon('fa-users') {{ $office ? $office->office_name : 'No Office' }}
                                                            </span>
                                                            <span class="badge badge-success">
                                                                @icon('fa-dollar-sign') ${{ number_format($totalCost, 2) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div id="checklist-detail-{{ $form->id }}" class="checklist-item-details collapse">
                                                    <div class="checklist-detail-content">
                                                        <div class="row">
                                                            <!-- Team Section -->
                                                            <div class="col-md-6 mb-3">
                                                                <h6 class="font-weight-bold mb-3">@icon('fa-users', ['class' => 'mr-2'])Team Members</h6>
                                                                <div class="team-member mb-2">
                                                                    <label class="mb-1">Migration Agent:</label>
                                                                    <div class="font-weight-500">
                                                                        {{ $migrationAgent ? $migrationAgent->first_name . ' ' . $migrationAgent->last_name : 'Not Assigned' }}
                                                                    </div>
                                                                </div>
                                                                <div class="team-member mb-2">
                                                                    <label class="mb-1">Person Responsible:</label>
                                                                    <div class="font-weight-500">
                                                                        {{ $personResponsible ? $personResponsible->first_name . ' ' . $personResponsible->last_name : 'Not Assigned' }}
                                                                    </div>
                                                                </div>
                                                                <div class="team-member mb-2">
                                                                    <label class="mb-1">Person Assisting:</label>
                                                                    <div class="font-weight-500">
                                                                        {{ $personAssisting ? $personAssisting->first_name . ' ' . $personAssisting->last_name : 'Not Assigned' }}
                                                                    </div>
                                                                </div>
                                                                <div class="team-member">
                                                                    <label class="mb-1">Handling Office:</label>
                                                                    <div class="font-weight-500">
                                                                        @icon('fa-building', ['class' => 'mr-1 text-primary']){{ $office ? $office->office_name : 'No Office Assigned' }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Cost Breakdown Section (compact) -->
                                                            <div class="col-md-6 mb-3 cost-breakdown-col">
                                                                <h6 class="font-weight-bold cost-breakdown-title">@icon('fa-calculator', ['class' => 'mr-2'])Cost Breakdown</h6>
                                                                <div class="cost-item cost-breakdown-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Our Cost (Block Fees):</span>
                                                                        <strong class="text-primary" style="font-size: 1.05rem;">${{ number_format($totalOurCost, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="cost-item cost-breakdown-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Dept. Charges:</span>
                                                                        <strong class="text-info" style="font-size: 1.05rem;">${{ number_format($totalDeptCost, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                @if($totalSurcharge > 0)
                                                                <div class="cost-item cost-breakdown-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Surcharges:</span>
                                                                        <strong class="text-danger" style="font-size: 1.05rem;">${{ number_format($totalSurcharge, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                @endif
                                                                @if($totalAdditionalFee1 > 0)
                                                                <div class="cost-item cost-breakdown-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Additional Fee1:</span>
                                                                        <strong class="text-warning" style="font-size: 1.05rem;">${{ number_format($totalAdditionalFee1, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                @endif
                                                                <hr class="cost-breakdown-hr">
                                                                <div class="cost-item cost-breakdown-total">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="font-weight-bold" style="color: #1b5e20; font-size: 1rem;">Total Cost:</span>
                                                                        <strong class="text-success" style="font-size: 1.1rem; font-weight: 700;">${{ number_format($totalCost, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="cost-breakdown-edit mt-2">
                                                                    <button type="button" class="btn btn-outline-secondary btn-sm btn-amend-checklist" data-id="{{ $form->id }}" data-client-id="{{ $fetchedData->id ?? '' }}" data-client-matter-id="{{ $form->client_matter_id }}" title="Amend Cost Assignment">
                                                                        @icon('fa-edit', ['class' => 'mr-1'])Edit
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Actions Section -->
                                                        <div class="checklist-actions-section mt-3 pt-3 border-top">
                                                            <div class="row">
                                                                <div class="col-12">
                                                                    <h6 class="font-weight-bold mb-3">@icon('fa-tools', ['class' => 'mr-2'])Actions</h6>
                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        @if($agreementDoc && $agreementDoc->status === 'signed' && $agreementDoc->signed_doc_link)
                                                                        <a href="{{ route('documents.download.signed', $agreementDoc->id) }}" target="_blank" class="btn btn-success btn-sm" title="Download Signed Document">
                                                                            @icon('fa-download', ['class' => 'mr-1'])Download Signed
                                                                        </a>
                                                                        @endif
                                                                        <button type="button" class="btn btn-outline-info btn-sm btn-send-checklist" title="Send Document Checklist to client"
                                                                            data-client-id="{{ $fetchedData->id ?? '' }}"
                                                                            data-client-email="{{ $fetchedData->email ?? '' }}"
                                                                            data-client-name="{{ trim(($fetchedData->first_name ?? '') . ' ' . ($fetchedData->last_name ?? '')) ?: ($fetchedData->company_name ?? '') }}"
                                                                            data-client-matter-id="{{ $form->client_matter_id ?? '' }}">
                                                                            @icon('fa-paper-plane', ['class' => 'mr-1'])Send Checklist
                                                                        </button>
                                                                        <button type="button" class="btn btn-primary btn-sm visaAgreementCreateForm" data-id="{{ $form->id }}" data-client-matter-id="{{ $form->client_matter_id }}" title="Create Visa Agreement">
                                                                            @icon('fa-file-contract', ['class' => 'mr-1'])Create Visa Agreement
                                                                        </button>
                                                                        <button type="button" class="btn btn-success btn-sm finalizeAgreementConvertToPdf" data-id="{{ $form->id }}" data-client-matter-id="{{ $form->client_matter_id }}" title="Upload PDF and place signature fields">
                                                                            @icon('fa-lock', ['class' => 'mr-1'])Upload and Place Signatures
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            @if($agreementDoc && $agreementDoc->status === 'signed' && $agreementDoc->signed_doc_link)
                                                            <!-- Document Signed - Download Section -->
                                                            <div class="signature-section mt-3 p-3 rounded border border-success" style="background-color: rgba(40, 167, 69, 0.1);">
                                                                <h6 class="font-weight-bold mb-2 text-success">@icon('fa-check-circle', ['class' => 'mr-2'])Document Signed</h6>
                                                                <p class="mb-2 small text-muted">The client has signed the cost agreement. Download the signed copy below.</p>
                                                                <a href="{{ route('documents.download.signed', $agreementDoc->id) }}" target="_blank" class="btn btn-success btn-sm">
                                                                    @icon('fa-download', ['class' => 'mr-1'])Download Signed Document
                                                                </a>
                                                            </div>
                                                            @elseif($agreementDoc && $agreementDoc->signature_doc_link)
                                                            @php
                                                                // Decode the JSON signature link
                                                                $signatureLinks = json_decode($agreementDoc->signature_doc_link, true);
                                                                $primaryLink = $signatureLinks[0] ?? null;
                                                                $signingUrl = $primaryLink['url'] ?? '';
                                                                $signerName = $primaryLink['name'] ?? '';
                                                                $signerEmail = $primaryLink['email'] ?? '';
                                                                // Pending signer for reminder (only when doc not signed)
                                                                $primarySigner = $agreementDoc->status !== 'signed' ? $agreementDoc->signers()->where('status', 'pending')->first() : null;
                                                            @endphp
                                                            <!-- Signature Link Section -->
                                                            <div class="signature-section mt-3 p-3 bg-light rounded">
                                                                <h6 class="font-weight-bold mb-2">@icon('fa-signature', ['class' => 'mr-2'])Signature Link</h6>
                                                                @if($signingUrl)
                                                                <div class="mb-2">
                                                                    <small class="text-muted">
                                                                        <strong>Signer:</strong> {{ $signerName }} ({{ $signerEmail }})
                                                                    </small>
                                                                </div>
                                                                <div class="input-group">
                                                                    <input type="text" class="form-control signature-link-input" value="{{ $signingUrl }}" readonly>
                                                                    <div class="input-group-append">
                                                                        <button class="btn btn-outline-secondary btn-copy-signature-link" type="button" data-link="{{ $signingUrl }}">
                                                                            @icon('fa-copy') Copy
                                                                        </button>
                                                                        <a href="{{ $signingUrl }}" target="_blank" class="btn btn-outline-primary">
                                                                            @icon('fa-external-link-alt') View
                                                                        </a>
                                                                        <button type="button" class="btn btn-info btn-send-signature-email" title="Send Email with Signature Link"
                                                                            data-signing-url="{{ $signingUrl }}"
                                                                            data-signer-email="{{ $signerEmail }}"
                                                                            data-signer-name="{{ $signerName }}"
                                                                            data-client-id="{{ $fetchedData->id }}"
                                                                            data-client-email="{{ $fetchedData->email ?? '' }}"
                                                                            data-client-name="{{ $fetchedData->first_name ?? '' }} {{ $fetchedData->last_name ?? '' }}"
                                                                            data-client-matter-id="{{ $form->client_matter_id }}">
                                                                            @icon('fa-envelope', ['class' => 'mr-1']) Send Email
                                                                        </button>
                                                                        @if($primarySigner)
                                                                        <button type="button" class="btn btn-warning btn-send-signature-reminder ml-1" title="Send reminder to signer"
                                                                            data-document-id="{{ $agreementDoc->id }}"
                                                                            data-signer-id="{{ $primarySigner->id }}"
                                                                            data-reminder-count="{{ $primarySigner->reminder_count }}"
                                                                            {{ $primarySigner->reminder_count >= 3 ? 'disabled' : '' }}>
                                                                            @icon('fa-bell', ['class' => 'mr-1']) Send Reminder ({{ $primarySigner->reminder_count }}/3)
                                                                        </button>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <small class="text-muted d-block mt-2">
                                                                    @icon('fa-info-circle') Share this link with the client to sign the agreement
                                                                </small>
                                                                @if($primarySigner)
                                                                <small class="text-muted d-block mt-1 checklist-signature-reminder-status">
                                                                    @if($primarySigner->last_reminder_sent_at)
                                                                        @icon('fa-clock') Last reminder: {{ $primarySigner->last_reminder_sent_at->format('M d, Y g:i A') }}
                                                                    @else
                                                                        @icon('fa-info-circle') No reminders sent yet
                                                                    @endif
                                                                </small>
                                                                @endif
                                                                @else
                                                                <div class="alert alert-warning mb-0">
                                                                    @icon('fa-exclamation-triangle') Signature link data is invalid. Please try placing signature fields again.
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @elseif($agreementDoc)
                                                            <!-- Document Uploaded - Awaiting Signature Setup -->
                                                            <div class="signature-section mt-3 p-3 bg-warning-light rounded border border-warning">
                                                                <h6 class="font-weight-bold mb-2">@icon('fa-exclamation-triangle', ['class' => 'mr-2 text-warning'])Signature Setup Required</h6>
                                                                <p class="mb-2 small">The agreement has been uploaded but signature fields haven't been placed yet.</p>
                                                                <button type="button" class="btn btn-warning btn-sm btn-place-signature-fields" data-document-id="{{ $agreementDoc->id }}" title="Place signature fields inline">
                                                                    @icon('fa-pen-nib', ['class' => 'mr-1'])Place Signature Fields
                                                                </button>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
           </div>

<style>
/* Inline signature placement modal */
#sig-preview-container { position: relative; }
#sig-fields-preview {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
}
.sig-field-preview {
    position: absolute;
    border: 2px dashed #3b82f6;
    background: rgba(59, 130, 246, 0.15);
    cursor: move;
    pointer-events: auto;
    user-select: none;
    touch-action: none;
}
.sig-field-preview:hover { background: rgba(59, 130, 246, 0.25); }
.sig-field-preview.dragging { border-color: #1d4ed8; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.4); }
.sig-field-preview.sig-field-preview-selected { border-color: #1d4ed8; box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.5); background: rgba(59, 130, 246, 0.25); }
.sig-field-row-selected { background-color: rgba(59, 130, 246, 0.08); border-color: #3b82f6; }
.sig-field-label {
    position: absolute;
    top: -18px;
    left: 0;
    background: #3b82f6;
    color: #fff;
    padding: 2px 6px;
    font-size: 10px;
    border-radius: 3px;
}

.checklist-add-wrapper { position: relative; }
/* Create Checklist popup: Tom Select menus use dropdownParent: body + .mm-checklist-create-dropdown
   so they position under the control (viewport coords). z-index must stay above this shell. */
.checklist-create-dropdown {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    min-width: 420px;
    max-width: 520px;
    overflow: visible;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    border: 1px solid #e2e8f0;
    z-index: 1060;
}
.checklist-create-dropdown .dropdown-arrow { display: none; }
.checklist-create-dropdown .dropdown-body {
    padding: 24px;
    max-height: calc(90vh - 48px);
    overflow-y: auto;
    overflow-x: hidden;
}
/* Menu is appended to body (see initChecklistMmSelect); keep above .checklist-create-dropdown (1060) */
.ts-dropdown.mm-checklist-create-dropdown {
    z-index: 100060 !important;
    width: min(520px, 92vw) !important;
    min-width: 260px !important;
    box-sizing: border-box;
}
.checklist-create-dropdown .dropdown-title { color: #334155; }

/* Checklist Accordion Styles */
.checklist-accordion {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.checklist-item-wrapper {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.checklist-item-wrapper:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.checklist-item-header {
    padding: 16px 20px;
    cursor: pointer;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    transition: all 0.3s ease;
    border-bottom: 1px solid transparent;
}

.checklist-item-header:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
}

.checklist-item-header[aria-expanded="true"] {
    background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
    border-bottom: 1px solid #e2e8f0;
}

.checklist-item-header[aria-expanded="true"] .checklist-matter-name,
.checklist-item-header[aria-expanded="true"] .text-muted,
.checklist-item-header[aria-expanded="true"] .checklist-date,
.checklist-item-header[aria-expanded="true"] .checklist-toggle-icon {
    color: #fff !important;
}

.checklist-item-header[aria-expanded="true"] .badge {
    background-color: rgba(255,255,255,0.35) !important;
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.5);
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.checklist-toggle-icon {
    transition: transform 0.3s ease;
    color: #4b5563;
}

.checklist-item-header[aria-expanded="true"] .checklist-toggle-icon {
    transform: rotate(90deg);
}

.checklist-matter-name {
    font-size: 1rem;
    color: #1f2937;
}

.checklist-summary {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

/* Badge contrast - ensure readable text on light backgrounds */
.checklist-summary .badge-info {
    background-color: #0d6efd !important;
    color: #fff !important;
    border: none;
}

.checklist-summary .badge-success {
    background-color: #198754 !important;
    color: #fff !important;
    border: none;
}

/* Fallback for badge without office - ensure dark enough */
.checklist-summary .badge {
    font-weight: 600;
}

.checklist-summary .btn-outline-primary,
.checklist-item-header .btn-outline-primary {
    color: #0a58ca !important;
    border-color: #0a58ca !important;
    background-color: #e7f1ff;
}

.checklist-summary .btn-outline-primary:hover,
.checklist-item-header .btn-outline-primary:hover {
    color: #084298 !important;
    border-color: #084298 !important;
    background-color: #cfe2ff;
}

/* Date - avoid light grey on light background */
.checklist-date {
    color: #4b5563 !important;
}

.checklist-item-details {
    border-top: 1px solid #e2e8f0;
}

.checklist-detail-content {
    padding: 20px;
    background: #ffffff;
}

.checklist-detail-content h6 {
    color: #212529;
    font-weight: 700;
}

.team-member, .cost-item {
    background: #fff;
    padding: 10px 14px;
    border-radius: 4px;
    border-left: 3px solid #4a90e2;
}

.team-member label {
    color: #495057 !important;
    font-weight: 600;
    font-size: 0.875rem;
}

.team-member .font-weight-500 {
    color: #212529;
    font-size: 0.95rem;
}

.cost-item {
    border-left-color: #28a745;
}

/* Cost Breakdown: compact layout */
.cost-breakdown-col .cost-breakdown-title {
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.cost-breakdown-col .cost-breakdown-item,
.cost-breakdown-col .cost-breakdown-total {
    padding: 6px 10px;
    margin-bottom: 4px;
    border-radius: 4px;
}

.cost-breakdown-col .cost-breakdown-item:last-of-type {
    margin-bottom: 4px;
}

.cost-breakdown-col .cost-breakdown-total {
    background: #e8f5e9;
    border-left-width: 4px;
    border-left-color: #28a745;
}

.cost-breakdown-col .cost-breakdown-hr {
    margin: 6px 0;
    border-top: 2px solid #dee2e6;
}

.cost-breakdown-col .cost-breakdown-edit {
    margin-top: 0.5rem !important;
}

.cost-item .text-muted {
    color: #495057 !important;
    font-weight: 500;
}

.cost-item strong {
    color: #212529;
    font-size: 1rem;
}

/* Force visible colour for cost amounts (override Bootstrap .text-primary etc so never white-on-white) */
.cost-item strong.text-primary {
    color: #007bff !important;
}

.cost-item strong.text-info {
    color: #0dcaf0 !important;
}

.cost-item strong.text-danger {
    color: #dc3545 !important;
}

.cost-item strong.text-success {
    color: #28a745 !important;
}

.font-weight-500 {
    font-weight: 500;
}

.checklist-actions-section {
    background: #f8f9fa;
    border-radius: 6px;
    padding: 15px;
}

.checklist-actions-section h6 {
    color: #212529;
    font-weight: 700;
}

.checklist-actions-section .gap-2 {
    gap: 8px;
}

/* Ensure action button icons are visible (avoid white icon on white outline button) */
.checklist-actions-section .btn-outline-primary,
.checklist-actions-section .btn-outline-primary i {
    color: #007bff !important;
}

.checklist-actions-section .btn-outline-primary:hover,
.checklist-actions-section .btn-outline-primary:hover i {
    color: #fff !important;
}

.signature-section {
    animation: fadeIn 0.3s ease-in;
}

.signature-section h6 {
    color: #212529;
    font-weight: 700;
}

.signature-section p {
    color: #495057;
}

/* WCAG AA contrast: text-muted (#6c757d) on bg-light (#f8f9fa) ≈ 3.8:1 (fails 4.5:1). Use darker gray. */
.signature-section .text-muted {
    color: #495057 !important;
}

.signature-section .btn-outline-secondary {
    color: #495057;
    border-color: #495057;
}

.signature-section .btn-outline-secondary:hover {
    color: #fff;
    background-color: #495057;
    border-color: #495057;
}

.bg-warning-light {
    background-color: #fff3cd;
}

.bg-warning-light p {
    color: #856404;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.signature-link-input {
    font-family: monospace;
    font-size: 0.85rem;
    background-color: #fff;
}

.btn-copy-signature-link:hover {
    background-color: #6c757d;
    color: #fff;
}

.btn-send-signature-email {
    color: #fff;
    border-color: #17a2b8;
}
.btn-send-signature-email:hover {
    background-color: #138496;
    border-color: #117a8b;
    color: #fff;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .checklist-summary {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .checklist-item-header {
        padding: 12px 16px;
    }
    
    .checklist-detail-content {
        padding: 16px;
    }
    
    .checklist-actions-section .d-flex {
        flex-direction: column;
    }
    
    .checklist-actions-section .btn {
        width: 100%;
        margin-bottom: 8px;
    }
}
</style>

@push('scripts')
<script>
(function($) {
    'use strict';
    $(document).ready(function() {
        var $btnAdd = $('#btn-add-checklist');
        var $dropdown = $('#checklist-create-dropdown');
        var $matterSelect = $('#checklist_matter_select');

        function destroyChecklistMmSelect() {
            if (typeof $.fn.mmSelect === 'undefined') {
                return;
            }
            $('#checklist_matter_select,#checklist_migration_agent,#checklist_person_responsible,#checklist_person_assisting,#checklist_office').each(function() {
                var $el = $(this);
                if ($el.data('mmSelect')) {
                    $el.mmSelect('destroy');
                }
            });
        }

        /** Tear down Tom Select on lead cost-assignment fields so copied values bind to the native selects before re-init (avoids blank labels when reopening). */
        function destroyLeadCostAssignmentMmSelect() {
            if (typeof $.fn.mmSelect === 'undefined') {
                return;
            }
            $('#sel_migration_agent_id_lead,#sel_person_responsible_id_lead,#sel_person_assisting_id_lead,#sel_office_id_lead,#sel_matter_id_lead').each(function() {
                var el = this;
                if (el.tomselect) {
                    $(el).mmSelect('destroy');
                }
            });
        }

        // Toggle dropdown on plus button click
        $btnAdd.on('click', function(e) {
            e.stopPropagation();
            $dropdown.toggle();
            if ($dropdown.is(':visible')) {
                initChecklistMmSelect();
            } else {
                destroyChecklistMmSelect();
            }
        });

        // Close panel when clicking outside (do not close while the Tom Select menu is open)
        $(document).on('click', function(e) {
            if (!$dropdown.is(':visible')) {
                return;
            }
            if ($btnAdd.is(e.target) || $btnAdd.has(e.target).length) {
                return;
            }
            if ($dropdown.is(e.target) || $dropdown.has(e.target).length) {
                return;
            }
            if ($(e.target).closest('.ts-dropdown').length && $dropdown.find('.ts-wrapper.dropdown-active').length) {
                return;
            }
            destroyChecklistMmSelect();
            $dropdown.hide();
        });

        // Cancel button
        $dropdown.on('click', '.btn-cancel-checklist', function() {
            destroyChecklistMmSelect();
            $dropdown.hide();
        });

        // General Matter checkbox: when checked, use matter 1 (same as Convert Lead To Client)
        $('#checklist_general_matter_checkbox').on('change', function() {
            if ($(this).is(':checked')) {
                $matterSelect.val('1').trigger('change');
            } else {
                $matterSelect.val('').trigger('change');
            }
        });

        // Continue / Save - uses Lead flow (matter type from admin list)
        $dropdown.on('click', '.btn-continue-cost-assignment', function() {
            var generalMatterChecked = $('#checklist_general_matter_checkbox').is(':checked');
            var matterId = generalMatterChecked ? '1' : $matterSelect.val();
            var clientId = window.ClientDetailConfig ? window.ClientDetailConfig.clientId : $('.crm-container').data('client-id');

            if (!clientId) {
                alert('Client ID not found. Please refresh the page.');
                return;
            }

            if (!matterId) {
                alert('Please select a Matter or check General Matter.');
                return;
            }

            var migrationAgent = $('#checklist_migration_agent').val();
            var personResponsible = $('#checklist_person_responsible').val();
            var personAssisting = $('#checklist_person_assisting').val();
            var officeId = $('#checklist_office').val();

            if (!migrationAgent || !personResponsible || !personAssisting || !officeId) {
                alert('Please fill Migration Agent, Person Responsible, Person Assisting, and Office.');
                return;
            }

            // Open Lead cost assignment modal (creates ClientMatter + CostAssignmentForm)
            destroyLeadCostAssignmentMmSelect();
            $('#cost_assignment_lead_id').val(clientId);
            $('#sel_matter_id_lead').val(matterId);
            $('#sel_migration_agent_id_lead').val(migrationAgent);
            $('#sel_person_responsible_id_lead').val(personResponsible);
            $('#sel_person_assisting_id_lead').val(personAssisting);
            $('#sel_office_id_lead').val(officeId);
            $('#sel_migration_agent_id_lead,#sel_person_responsible_id_lead,#sel_person_assisting_id_lead,#sel_office_id_lead,#sel_matter_id_lead').mmSelect({
                dropdownParent: $('#costAssignmentCreateFormModelLead'),
                minimumResultsForSearch: 0,
                width: '100%'
            });
            $('#sel_matter_id_lead').trigger('change');
            $('#costAssignmentCreateFormModelLead').modal('show');
            destroyChecklistMmSelect();
            $dropdown.hide();
        });

        function initChecklistMmSelect() {
            if (typeof $.fn.mmSelect === 'undefined') {
                return;
            }
            destroyChecklistMmSelect();
            var $fields = $('#checklist_matter_select,#checklist_migration_agent,#checklist_person_responsible,#checklist_person_assisting,#checklist_office');
            $fields.each(function() {
                $(this).mmSelect({
                    dropdownParent: $('body'),
                    width: '100%',
                    dropdownCssClass: 'mm-checklist-create-dropdown',
                    minimumResultsForSearch: 0
                });
            });
        }

        // Accordion toggle functionality
        $('.checklist-item-header').on('click', function() {
            var $this = $(this);
            var isExpanded = $this.attr('aria-expanded') === 'true';
            
            // Close all other accordions
            $('.checklist-item-header').not($this).attr('aria-expanded', 'false');
            $('.checklist-item-details').not($this.next()).removeClass('show');
            
            // Toggle current accordion
            $this.attr('aria-expanded', !isExpanded);
        });

        // Handle Bootstrap collapse events for proper aria-expanded state
        $('.checklist-item-details').on('shown.bs.collapse', function() {
            $(this).prev('.checklist-item-header').attr('aria-expanded', 'true');
        }).on('hidden.bs.collapse', function() {
            $(this).prev('.checklist-item-header').attr('aria-expanded', 'false');
        });

        // Send Checklist - open compose modal with matter selected (checklist rows filtered; user picks attachments)
        $(document).on('click', '.btn-send-checklist', function(e) {
            e.stopPropagation();
            var $btn = $(this);
            var clientId = $btn.data('client-id');
            var clientEmail = ($btn.data('client-email') || '').trim();
            var clientName = ($btn.data('client-name') || '').trim();
            var clientMatterId = $btn.data('client-matter-id');

            if (!clientId || !clientEmail) {
                alert('Client email is required to send checklist. Please ensure the client has an email address.');
                return;
            }
            if (!clientMatterId) {
                alert('Matter is required to send checklist.');
                return;
            }
            if (!$('#emailmodal').length) {
                alert('Email compose form not found. Please ensure you are on the client detail page.');
                return;
            }

            // Set compose matter only — do not trigger sidebar matter dropdown change (that reloads the page).
            $('#emailmodal #compose_client_matter_id').val(clientMatterId || '');

            // Default subject for checklist email
            var subject = 'Checklist sent to client';
            var message = '<p>Dear ' + (clientName.trim() || 'there') + ',</p>' +
                '<p>Please find attached the checklist for your matter.</p>' +
                '<p>If you have any questions, please contact us.</p>' +
                '<p><strong>Regards,</strong><br>Bansal Migration Team</p>';

            $('#compose_email_subject').val(subject);

            // Set To field with client
            var array = [];
            var data = [];
            if (clientId && clientEmail) {
                array.push(clientId);
                data.push({
                    id: clientId,
                    text: clientName || clientEmail,
                    html: "<div class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +
                        "<div class='ag-flex ag-align-start'><div class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span class='mm-result-repository__title text-semi-bold'>" + (clientName || clientEmail) + "</span></div>" +
                        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>" + clientEmail + "</small></div></div></div>" +
                        "<div class='ag-flex ag-flex-column ag-align-end'><span class='ui label yellow mm-result-repository__statistics'>Client</span></div></div>",
                    title: clientName || clientEmail
                });
            }

            var $toSelect = $('#emailmodal .js-data-example-ajax');
            if ($toSelect.data('mmSelect')) {
                $toSelect.mmSelect('destroy');
            }
            $toSelect.mmSelect({
                data: data,
                escapeMarkup: function(markup) { return markup; },
                templateResult: function(d) { return d.html; },
                templateSelection: function(d) { return d.text; },
                dropdownParent: $('body'),
                dropdownCssClass: 'mm-compose-email-recipients-dropdown',
                multiple: true,
                closeOnSelect: false
            });
            $toSelect.val(array).trigger('change');

            // Set TinyMCE content after modal is shown
            $('#emailmodal').one('shown.bs.modal', function() {
                if (typeof setTinyMCEContent === 'function') {
                    setTinyMCEContent('compose_email_message', message);
                } else if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                    tinymce.get('compose_email_message').setContent(message);
                } else {
                    $('#compose_email_message').val(message);
                }
            });

            $('#emailmodal').modal('show');
        });

        // Copy signature link to clipboard
        $(document).on('click', '.btn-copy-signature-link', function() {
            var link = $(this).data('link');
            var $input = $(this).closest('.input-group').find('.signature-link-input');
            
            // Select and copy
            $input.select();
            document.execCommand('copy');
            
            // Visual feedback
            var $btn = $(this);
            var originalHtml = $btn.html();
            $btn.html(crmI('fas fa-check') + ' Copied!');
            $btn.addClass('btn-success').removeClass('btn-outline-secondary');
            
            setTimeout(function() {
                $btn.html(originalHtml);
                $btn.removeClass('btn-success').addClass('btn-outline-secondary');
            }, 2000);
            
            // Deselect
            window.getSelection().removeAllRanges();
        });

        // Send Email with Signature Link - opens compose modal with default checklist/signature email
        $(document).on('click', '.btn-send-signature-email', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var signingUrl = ($btn.attr('data-signing-url') || '').trim();
            var signerName = ($btn.data('signer-name') || '').trim() || 'there';
            var clientId = $btn.data('client-id');
            var clientEmail = ($btn.data('client-email') || '').trim();
            var clientName = ($btn.data('client-name') || '').trim();
            var clientMatterId = $btn.data('client-matter-id');

            if (!signingUrl) {
                alert('Signature link is not available.');
                return;
            }
            if (!clientId || !clientEmail) {
                alert('Client email is required to send. Please ensure the client has an email address.');
                return;
            }
            if (!$('#emailmodal').length) {
                alert('Email compose form not found. Please ensure you are on the client detail page.');
                return;
            }

            // Set compose matter only — do not trigger sidebar matter dropdown change (that reloads the page).
            $('#emailmodal #compose_client_matter_id').val(clientMatterId || '');
            $('#emailmodal').data('pdfUrlForSign', signingUrl || '');
            $('#emailmodal').data('fromSignatureSend', true);
            $('#compose_signing_url').val(signingUrl || '');

            // Default subject and message for checklist signature email
            var subject = 'Action Required: Please Sign Your Visa Agreement';
            var message = '<p>Dear ' + (signerName !== 'there' ? signerName : 'there') + ',</p>' +
                '<p>We have prepared an agreement document that requires your review and signature.</p>' +
                '<p>Please click the link below to access and sign the document:</p>' +
                '<p style="margin:20px 0;"><a href="' + signingUrl + '" target="_blank" rel="noopener noreferrer" style="display:inline-block;background-color:#2563eb;color:#fff;text-decoration:none;padding:12px 24px;font-weight:600;">Sign Document Now</a></p>' +
                '<p>Or copy this link: <a href="' + signingUrl + '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;">' + signingUrl + '</a></p>' +
                '<p>If you have any questions, please contact us.</p>' +
                '<p><strong>Regards,</strong><br>Bansal Migration Team</p>';

            $('#compose_email_subject').val(subject);

            // Set To field: use client (recipient ID) for backend compatibility
            var array = [];
            var data = [];
            if (clientId && clientEmail) {
                array.push(clientId);
                data.push({
                    id: clientId,
                    text: clientName || clientEmail,
                    html: "<div class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +
                        "<div class='ag-flex ag-align-start'><div class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span class='mm-result-repository__title text-semi-bold'>" + (clientName || clientEmail) + "</span></div>" +
                        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>" + clientEmail + "</small></div></div></div>" +
                        "<div class='ag-flex ag-flex-column ag-align-end'><span class='ui label yellow mm-result-repository__statistics'>Client</span></div></div>",
                    title: clientName || clientEmail
                });
            }

            var $toSelect = $('#emailmodal .js-data-example-ajax');
            if ($toSelect.data('mmSelect')) {
                $toSelect.mmSelect('destroy');
            }
            $toSelect.mmSelect({
                data: data,
                escapeMarkup: function(markup) { return markup; },
                templateResult: function(d) { return d.html; },
                templateSelection: function(d) { return d.text; },
                dropdownParent: $('body'),
                dropdownCssClass: 'mm-compose-email-recipients-dropdown',
                multiple: true,
                closeOnSelect: false
            });
            $toSelect.val(array).trigger('change');

            // Set TinyMCE content after modal is shown
            $('#emailmodal').one('shown.bs.modal', function() {
                if (typeof setTinyMCEContent === 'function') {
                    setTinyMCEContent('compose_email_message', message);
                } else if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                    tinymce.get('compose_email_message').setContent(message);
                } else {
                    $('#compose_email_message').val(message);
                }
            });

            $('#emailmodal').modal('show');
        });

        // Send Reminder - AJAX (no full page reload)
        $(document).on('click', '.btn-send-signature-reminder', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }

            var docId = $btn.data('document-id');
            var signerId = $btn.data('signer-id');
            if (!docId || !signerId) {
                alert('Unable to send reminder: missing document or signer information.');
                return;
            }

            if (!confirm('Send a reminder email to the signer?')) {
                return;
            }

            var originalHtml = $btn.html();
            $btn.prop('disabled', true).html(crmI('fas fa-spinner fa-spin', { class: 'mr-1' }) + ' Sending...');

            $.ajax({
                url: '{{ url("/signatures") }}/' + docId + '/reminder',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    signer_id: signerId
                },
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                dataType: 'json'
            }).done(function(resp) {
                if (resp && resp.success) {
                    var count = resp.reminder_count != null ? resp.reminder_count : (parseInt($btn.data('reminder-count'), 10) + 1);
                    $btn.data('reminder-count', count);
                    $btn.html(crmI('fas fa-bell', { class: 'mr-1' }) + ' Send Reminder (' + count + '/3)');
                    if (count >= 3) {
                        $btn.prop('disabled', true);
                    } else {
                        $btn.prop('disabled', false);
                    }

                    var $status = $btn.closest('.signature-section').find('.checklist-signature-reminder-status');
                    if ($status.length) {
                        if (resp.last_reminder_sent_at) {
                            $status.html(crmI('fas fa-clock') + ' Last reminder: ' + resp.last_reminder_sent_at);
                        } else {
                            $status.html(crmI('fas fa-info-circle') + ' No reminders sent yet');
                        }
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'success', title: 'Success', text: resp.message || 'Reminder sent successfully!', timer: 2000 });
                    } else if (typeof toastr !== 'undefined') {
                        toastr.success(resp.message || 'Reminder sent successfully!');
                    } else {
                        alert(resp.message || 'Reminder sent successfully!');
                    }
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    var errMsg = (resp && resp.message) ? resp.message : 'Failed to send reminder.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
                    } else {
                        alert(errMsg);
                    }
                }
            }).fail(function(xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                var errMsg = 'Failed to send reminder. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg = xhr.responseJSON.message;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: errMsg });
                } else {
                    alert(errMsg);
                }
            });
        });

        // Amend checklist - opens the cost assignment modal to make changes
        $(document).on('click', '.btn-amend-checklist', function(e) {
            e.stopPropagation();
            var clientId = $(this).data('client-id');
            var clientMatterId = $(this).data('client-matter-id');
            
            if (!clientId || !clientMatterId) {
                alert('Unable to open cost assignment: missing client or matter information.');
                return;
            }
            
            // Set client/matter IDs in the modal form (scope to modal to avoid subtab form)
            var $modal = $('#costAssignmentCreateFormModel');
            $modal.find('#cost_assignment_client_id').val(clientId);
            $modal.find('#cost_assignment_client_matter_id').val(clientMatterId);
            
            // Update modal title for edit mode
            $modal.find('#costAssignmentModalLabel').text('Amend Cost Assignment');
            
            // Load existing cost assignment data into the modal, then show it when loaded
            if (typeof window.getCostAssignmentMigrationAgentDetail === 'function') {
                window.getCostAssignmentMigrationAgentDetail(clientId, clientMatterId, '#costAssignmentCreateFormModel', function() {
                    $modal.modal('show');
                });
            } else if (typeof getCostAssignmentMigrationAgentDetail === 'function') {
                getCostAssignmentMigrationAgentDetail(clientId, clientMatterId, '#costAssignmentCreateFormModel', function() {
                    $modal.modal('show');
                });
            } else {
                alert('Cost assignment function not available. Please refresh the page.');
            }
        });

        // When finalize button is clicked and agreement is uploaded, handle signature flow
        $(document).on('agreementUploaded', function(e, data) {
            if (data.signatureLink) {
                // Reload the checklist tab to show the signature link
                location.reload();
            }
        });

        // --- Inline Signature Placement Modal ---
        var sigState = {
            documentId: null,
            pdfPages: 1,
            pagesDimensions: {},
            pdfWidthMM: 210,
            pdfHeightMM: 297,
            currentPage: 1,
            signatureFields: [],
            selectedFieldIndex: -1,
            isDragging: false,
            dragFieldIndex: -1,
            dragOffsetX: 0,
            dragOffsetY: 0
        };

        function openSignaturePlacementModal(docId) {
            if (!docId) return;
            sigState.documentId = docId;
            sigState.signatureFields = [];
            sigState.currentPage = 1;
            sigState.selectedFieldIndex = -1;
            $('#signaturePlacementModal').modal('show');
            $('#signature-placement-loading').show();
            $('#signature-placement-content').hide();
            $('#signature-placement-error').hide();

            $.ajax({
                url: '/documents/' + docId + '/signature-placement-data',
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            }).done(function(data) {
                if (!data.success) {
                    $('#signature-placement-loading').hide();
                    $('#signature-placement-error').text(data.message || 'Failed to load document.').show();
                    return;
                }
                sigState.pdfPages = data.pdfPages || 1;
                sigState.pagesDimensions = data.pagesDimensions || {};
                sigState.pdfWidthMM = data.pdfWidthMM || 210;
                sigState.pdfHeightMM = data.pdfHeightMM || 297;
                sigState.signatureFields = (data.existingFields || []).map(function(f, i) {
                    var toDecimal = function(v) { return (v > 1 ? v / 100 : v) || 0; };
                    return {
                        id: Date.now() + i,
                        page_number: f.page_number,
                        x_percent: toDecimal(f.x_percent),
                        y_percent: toDecimal(f.y_percent),
                        w_percent: Math.max(0.05, toDecimal(f.w_percent)),
                        h_percent: Math.max(0.03, toDecimal(f.h_percent))
                    };
                });

                $('#signature-placement-loading').hide();
                $('#signature-placement-content').show();
                $('#sig-preview-image').attr('src', '/debug-pdf-page/' + docId + '/1');
                if (sigState.pdfPages > 1) {
                    $('#signature-page-nav').show();
                    $('#sig-prev-page').prop('disabled', true);
                    $('#sig-next-page').prop('disabled', sigState.pdfPages <= 1);
                } else {
                    $('#signature-page-nav').hide();
                }
                sigState.currentPage = 1;
                updateSigPageInfo();
                updateSigForm();
                updateSigPreview();
                bindSigEvents();
            }).fail(function(xhr) {
                $('#signature-placement-loading').hide();
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load document.';
                $('#signature-placement-error').text(msg).show();
            });
        }

        $(document).on('click', '.btn-place-signature-fields', function() {
            openSignaturePlacementModal($(this).data('document-id'));
        });

        $(document).on('openSignaturePlacementModal', function(e, data) {
            if (data && data.documentId) openSignaturePlacementModal(data.documentId);
        });

        function updateSigPageInfo() {
            $('#sig-page-info').text('Page ' + sigState.currentPage + ' of ' + sigState.pdfPages);
            $('#sig-prev-page').prop('disabled', sigState.currentPage <= 1);
            $('#sig-next-page').prop('disabled', sigState.currentPage >= sigState.pdfPages);
        }

        function getSigDisplayDims() {
            var $img = $('#sig-preview-image');
            return { width: $img.length ? $img[0].clientWidth : 0, height: $img.length ? $img[0].clientHeight : 0 };
        }

        function sigSwitchPage(p) {
            if (p < 1 || p > sigState.pdfPages) return;
            sigState.currentPage = p;
            $('#sig-preview-image').attr('src', '/debug-pdf-page/' + sigState.documentId + '/' + p);
            updateSigPageInfo();
            updateSigPreview();
        }

        function sigAddField(page, x, y) {
            var dims = getSigDisplayDims();
            var w = 150, h = 75;
            var xP = dims.width ? x / dims.width : 0;
            var yP = dims.height ? y / dims.height : 0;
            var wP = dims.width ? w / dims.width : 0.2;
            var hP = dims.height ? h / dims.height : 0.1;
            sigState.signatureFields.push({ id: Date.now(), page_number: page, x_percent: xP, y_percent: yP, w_percent: wP, h_percent: hP });
            updateSigForm();
            updateSigPreview();
            sigState.selectedFieldIndex = sigState.signatureFields.length - 1;
        }

        function updateSigForm() {
            var html = '';
            sigState.signatureFields.forEach(function(f, i) {
                var rowClass = 'd-flex justify-content-between align-items-center mb-2 p-2 border rounded sig-field-row';
                if (i === sigState.selectedFieldIndex) rowClass += ' sig-field-row-selected';
                var editBtnClass = (i === sigState.selectedFieldIndex) ? 'btn btn-primary btn-sm sig-edit-field mr-1' : 'btn btn-outline-secondary btn-sm sig-edit-field mr-1';
                html += '<div class="' + rowClass + '" data-index="' + i + '">';
                html += '<span class="small">Signature ' + (i + 1) + ' (Pg ' + f.page_number + ')</span>';
                html += '<div><button type="button" class="' + editBtnClass + '" data-index="' + i + '">Edit</button>';
                html += '<button type="button" class="btn btn-outline-danger btn-sm sig-delete-field" data-index="' + i + '">Delete</button></div></div>';
            });
            $('#sig-fields-container').html(html || '<small class="text-muted">No fields. Click on the document or Add Signature Field.</small>');
        }

        function updateSigPreview() {
            var $container = $('#sig-fields-preview');
            $container.empty();
            var dims = getSigDisplayDims();
            sigState.signatureFields.forEach(function(f, i) {
                if (f.page_number !== sigState.currentPage) return;
                var previewClass = 'sig-field-preview';
                if (i === sigState.selectedFieldIndex) previewClass += ' sig-field-preview-selected';
                var $el = $('<div class="' + previewClass + '" data-index="' + i + '"></div>');
                $el.css({ left: (f.x_percent * dims.width) + 'px', top: (f.y_percent * dims.height) + 'px', width: (f.w_percent * dims.width) + 'px', height: (f.h_percent * dims.height) + 'px' });
                $el.html('<span class="sig-field-label">Signature ' + (i + 1) + '</span>');
                $container.append($el);
            });
        }

        function bindSigEvents() {
            $('#sig-preview-container').off('click.sig').on('click.sig', function(e) {
                if ($(e.target).is('#sig-preview-image')) {
                    var rect = e.target.getBoundingClientRect();
                    sigAddField(sigState.currentPage, e.clientX - rect.left, e.clientY - rect.top);
                }
            });
            $('#sig-add-field').off('click.sig').on('click.sig', function() {
                var dims = getSigDisplayDims();
                sigAddField(sigState.currentPage, dims.width / 2, dims.height / 2);
            });
            $('#sig-prev-page').off('click.sig').on('click.sig', function() { sigSwitchPage(sigState.currentPage - 1); });
            $('#sig-next-page').off('click.sig').on('click.sig', function() { sigSwitchPage(sigState.currentPage + 1); });
            $(document).off('click.sig', '.sig-delete-field').on('click.sig', '.sig-delete-field', function(e) {
                e.preventDefault();
                var i = parseInt($(this).data('index'));
                if (!isNaN(i) && confirm('Delete this signature field?')) {
                    sigState.signatureFields.splice(i, 1);
                    sigState.selectedFieldIndex = -1;
                    updateSigForm();
                    updateSigPreview();
                }
            });
            $(document).off('click.sig', '.sig-edit-field').on('click.sig', '.sig-edit-field', function(e) {
                e.preventDefault();
                var i = parseInt($(this).data('index'));
                if (!isNaN(i) && sigState.signatureFields[i]) {
                    sigState.selectedFieldIndex = i;
                    if (sigState.signatureFields[i].page_number !== sigState.currentPage) {
                        sigSwitchPage(sigState.signatureFields[i].page_number);
                    }
                    updateSigForm();
                    updateSigPreview();
                }
            });
            $('#sig-preview-image').off('load.sig').on('load.sig', function() { updateSigPreview(); });

            // Drag to reposition signature fields (mouse + touch)
            function getSigPointerCoords(e) {
                var clientX = e.clientX, clientY = e.clientY, offsetX = e.offsetX, offsetY = e.offsetY;
                if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length) {
                    var t = e.originalEvent.touches[0];
                    clientX = t.clientX;
                    clientY = t.clientY;
                    var target = e.target;
                    var tr = target.getBoundingClientRect();
                    offsetX = t.clientX - tr.left;
                    offsetY = t.clientY - tr.top;
                }
                return { clientX: clientX, clientY: clientY, offsetX: offsetX, offsetY: offsetY };
            }
            $(document).off('mousedown.sig touchstart.sig', '.sig-field-preview').on('mousedown.sig touchstart.sig', '.sig-field-preview', function(e) {
                e.preventDefault();
                var coords = (e.type === 'touchstart') ? getSigPointerCoords(e) : { clientX: e.clientX, clientY: e.clientY, offsetX: e.offsetX, offsetY: e.offsetY };
                var i = parseInt($(this).data('index'));
                if (isNaN(i) || !sigState.signatureFields[i]) return;
                sigState.isDragging = true;
                sigState.dragFieldIndex = i;
                sigState.dragOffsetX = coords.offsetX;
                sigState.dragOffsetY = coords.offsetY;
                $(this).addClass('dragging');
            });
            $(document).off('mousemove.sig touchmove.sig').on('mousemove.sig touchmove.sig', function(e) {
                if (!sigState.isDragging || sigState.dragFieldIndex < 0) return;
                if (e.type === 'touchmove') e.preventDefault();
                var coords = (e.type === 'touchmove') ? getSigPointerCoords(e) : { clientX: e.clientX, clientY: e.clientY };
                var f = sigState.signatureFields[sigState.dragFieldIndex];
                if (!f || f.page_number !== sigState.currentPage) return;
                var $img = $('#sig-preview-image');
                if (!$img.length) return;
                var rect = $img[0].getBoundingClientRect();
                var w = rect.width, h = rect.height;
                if (!w || !h) return;
                var localX = coords.clientX - rect.left - sigState.dragOffsetX;
                var localY = coords.clientY - rect.top - sigState.dragOffsetY;
                var maxX = w * (1 - f.w_percent);
                var maxY = h * (1 - f.h_percent);
                localX = Math.max(0, Math.min(localX, maxX));
                localY = Math.max(0, Math.min(localY, maxY));
                f.x_percent = localX / w;
                f.y_percent = localY / h;
                var $el = $('#sig-fields-preview .sig-field-preview[data-index="' + sigState.dragFieldIndex + '"]');
                if ($el.length) {
                    $el.css({ left: (f.x_percent * w) + 'px', top: (f.y_percent * h) + 'px' }).addClass('dragging');
                } else {
                    updateSigPreview();
                    $('#sig-fields-preview .sig-field-preview[data-index="' + sigState.dragFieldIndex + '"]').addClass('dragging');
                }
            });
            $(document).off('mouseup.sig touchend.sig touchcancel.sig').on('mouseup.sig touchend.sig touchcancel.sig', function(e) {
                if (sigState.isDragging) {
                    sigState.isDragging = false;
                    sigState.dragFieldIndex = -1;
                    $('.sig-field-preview').removeClass('dragging');
                }
            });

            $('#sig-save-btn').off('click.sig').on('click.sig', function() {
                if (sigState.signatureFields.length === 0) {
                    alert('Please add at least one signature field.');
                    return;
                }
                var signatures = sigState.signatureFields.map(function(f) {
                    return {
                        page_number: parseInt(f.page_number, 10),
                        x_percent: parseFloat((f.x_percent * 100).toFixed(2)),
                        y_percent: parseFloat((f.y_percent * 100).toFixed(2)),
                        w_percent: parseFloat((f.w_percent * 100).toFixed(2)),
                        h_percent: parseFloat((f.h_percent * 100).toFixed(2))
                    };
                });
                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Saving...');
                var postData = {
                    _method: 'PATCH',
                    _token: ($('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()),
                    signatures: signatures
                };
                $.ajax({
                    url: '/documents/' + sigState.documentId,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(postData),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).done(function(resp) {
                    if (resp && resp.source) $('#signaturePlacementModal').data('lastSaveSource', resp.source);
                    else $('#signaturePlacementModal').removeData('lastSaveSource');
                    $('#signaturePlacementModal').modal('hide');
                    if (resp && resp.success) {
                        alert(resp.message || 'Signature fields saved. The signing link is now available.');
                        if (resp.redirect_url) window.location.href = resp.redirect_url;
                    } else {
                        alert((resp && resp.message) ? resp.message : 'An error occurred.');
                    }
                }).fail(function(xhr) {
                    var msg = 'Failed to save signature fields.';
                    if (xhr.responseJSON) {
                        if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
                        else if (xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                    } else if (xhr.status === 419) msg = 'Session expired. Please refresh the page and try again.';
                    else if (xhr.responseText && xhr.responseText.length < 200) msg = xhr.responseText;
                    alert(msg);
                }).always(function() {
                    $btn.prop('disabled', false).html(crmI('fas fa-save', { class: 'mr-1' }) + 'Save Signature Locations');
                });
            });
        }

        $('#signaturePlacementModal').on('hidden.bs.modal', function() {
            sigState.isDragging = false;
            sigState.dragFieldIndex = -1;
            $('.sig-field-preview').removeClass('dragging');
            $('#sig-preview-image').attr('src', '');
            var source = $('#signaturePlacementModal').data('lastSaveSource');
            if (source === 'visa_documents') {
                localStorage.setItem('activeTab', 'visadocuments');
            } else {
                localStorage.setItem('activeTab', 'checklists');
            }
            location.reload();
        });
    });
})(jQuery);
</script>
@endpush
