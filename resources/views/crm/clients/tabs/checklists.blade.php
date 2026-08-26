           <!-- Checklists Tab -->
           @php
                $checklistsTabFragmentUrl = ! empty($encodeId)
                    ? route('clients.detail.checklists-tab', array_filter([
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1 ?? null,
                    ], static function ($value) {
                        return $value !== null && $value !== '';
                    }))
                    : '';

                if (! isset($checklistsTabPayload) || ! is_array($checklistsTabPayload)) {
                    $checklistsTabPayload = \App\Support\ClientDetailChecklistsTab::build($fetchedData, $id1 ?? null);
                }

                $checklistCurrentMatterId = $checklistsTabPayload['checklistCurrentMatterId'];
                $checklistCurrentMatterRef = $checklistsTabPayload['checklistCurrentMatterRef'];
                $checklistCurrentMatterNeedsCostAssignment = $checklistsTabPayload['checklistCurrentMatterNeedsCostAssignment'];
                $checklistMigrationAgents = $checklistsTabPayload['checklistMigrationAgents'];
                $checklistPersonsResponsible = $checklistsTabPayload['checklistPersonsResponsible'];
                $checklistPersonsAssisting = $checklistsTabPayload['checklistPersonsAssisting'];
                $checklistOffices = $checklistsTabPayload['checklistOffices'];
                $checklistAuthOfficeId = $checklistsTabPayload['checklistAuthOfficeId'];
                $checklistMatterList = $checklistsTabPayload['checklistMatterList'];
                $checklist_forms = $checklistsTabPayload['checklistForms'];
           @endphp
           <div class="tab-pane" id="checklists-tab"
                data-current-matter-id="{{ $checklistCurrentMatterId ?? '' }}"
                data-current-matter-ref="{{ $checklistCurrentMatterRef ?? '' }}"
                data-needs-cost-assignment="{{ $checklistCurrentMatterNeedsCostAssignment ? '1' : '0' }}"
                @if($checklistsTabFragmentUrl !== '') data-checklists-url="{{ $checklistsTabFragmentUrl }}" @endif>
                <div class="card full-width checklists-container">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">@icon('fa-tasks', ['class' => 'mr-2'])Checklists</h4>
                        <div class="checklist-add-wrapper position-relative d-flex align-items-center flex-wrap" style="gap: 0.5rem;">
                            @if($checklistCurrentMatterNeedsCostAssignment)
                                <button type="button" class="btn btn-primary btn-setup-cost-assignment-for-matter" id="btn-setup-cost-assignment-for-matter">
                                    @icon('fa-plus', ['class' => 'mr-2'])Set up cost assignment
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-add-checklist" id="btn-add-checklist" title="Create a new matter with cost assignment">
                                    @icon('fa-plus', ['class' => 'mr-2'])Add new matter
                                </button>
                            @else
                                <button type="button" class="btn btn-primary btn-add-checklist" id="btn-add-checklist">
                                    @icon('fa-plus', ['class' => 'mr-2'])Create Checklist
                                </button>
                            @endif
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
                                                        @foreach($checklistMigrationAgents as $migAgntlist)
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
                                                        @foreach($checklistPersonsResponsible as $perreslist)
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
                                                        @foreach($checklistPersonsAssisting as $perassislist)
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
                                                        @foreach($checklistOffices as $office)
                                                            <option value="{{$office->id}}" {{ (string) $checklistAuthOfficeId === (string) $office->id ? 'selected' : '' }}>{{$office->office_name}}</option>
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
                                                        @foreach($checklistMatterList as $matterlist)
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
                            @if($checklistCurrentMatterNeedsCostAssignment)
                                <div class="alert alert-warning mb-3" id="checklist-matter-setup-banner">
                                    @icon('fa-exclamation-triangle', ['class' => 'mr-2'])
                                    Matter <strong>{{ $checklistCurrentMatterRef }}</strong> does not have a cost assignment yet.
                                    Set one up to create a cost agreement.
                                    <button type="button" class="btn btn-sm btn-primary ml-2 btn-setup-cost-assignment-for-matter">
                                        Set up cost assignment
                                    </button>
                                </div>
                            @endif
                            <div id="checklists-list-container">
                                <?php
                                ?>
                                @if($checklist_forms->isEmpty())
                                    <div class="alert alert-info" id="checklists-empty-state">
                                        @icon('fa-info-circle', ['class' => 'mr-2'])
                                        @if($checklistCurrentMatterNeedsCostAssignment)
                                            No checklists yet for matter <strong>{{ $checklistCurrentMatterRef }}</strong>.
                                            Click <strong>Set up cost assignment</strong> to assign the team, complete cost assignment, and create a cost agreement.
                                        @else
                                            No checklists yet. Click <strong>Create Checklist</strong> to add one. You'll select matter, assign migration agent and team, complete cost assignment, and create cost agreement.
                                        @endif
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
                                                $discountAmount = \App\Models\CostAssignmentForm::appliedDiscountFromRow($form);
                                                $totalCost = \App\Models\CostAssignmentForm::calculateTotalCost(
                                                    floatval($totalOurCost),
                                                    floatval($totalDeptCost),
                                                    floatval($totalSurcharge),
                                                    $totalAdditionalFee1,
                                                    $discountAmount
                                                );
                                                
                                                $agreementDoc = $form->agreement_document ?? null;
                                            @endphp
                                            <div class="checklist-item-wrapper" data-id="{{ $form->id }}" data-client-matter-id="{{ $form->client_matter_id }}">
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
                                                            <span class="badge badge-success js-checklist-total-badge">
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
                                                                        <strong class="text-primary js-cost-block-fee" style="font-size: 1.05rem;">${{ number_format($totalOurCost, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="cost-item cost-breakdown-item">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Dept. Charges:</span>
                                                                        <strong class="text-info js-cost-dept" style="font-size: 1.05rem;">${{ number_format($totalDeptCost, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="cost-item cost-breakdown-item js-cost-surcharge-row" @if($totalSurcharge <= 0) style="display: none;" @endif>
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Surcharges:</span>
                                                                        <strong class="text-danger js-cost-surcharge" style="font-size: 1.05rem;">${{ number_format($totalSurcharge, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="cost-item cost-breakdown-item js-cost-additional-row" @if($totalAdditionalFee1 <= 0) style="display: none;" @endif>
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Additional Fee1:</span>
                                                                        <strong class="text-warning js-cost-additional" style="font-size: 1.05rem;">${{ number_format($totalAdditionalFee1, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <div class="cost-item cost-breakdown-item js-cost-discount-row" @if($discountAmount <= 0) style="display: none;" @endif>
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span>Discount:</span>
                                                                        <strong class="text-success js-cost-discount" style="font-size: 1.05rem;">-${{ number_format($discountAmount, 2) }}</strong>
                                                                    </div>
                                                                </div>
                                                                <hr class="cost-breakdown-hr">
                                                                <div class="cost-item cost-breakdown-total">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="font-weight-bold" style="color: #1b5e20; font-size: 1rem;">Total Cost:</span>
                                                                        <strong class="text-success js-cost-total" style="font-size: 1.1rem; font-weight: 700;">${{ number_format($totalCost, 2) }}</strong>
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

<script>
(function() {
    function bootChecklistsTab() {
        'use strict';

        /**
         * Eager checklists boot runs inside the detail content section before layout
         * scripts replace window.jQuery and bootstrap5-jquery-compat.js adds $.fn.modal.
         * Always resolve the live jQuery / Bootstrap API at bind and show time.
         */
        function checklistJquery() {
            return window.jQuery;
        }

        function showChecklistModal($modal) {
            if (!$modal || !$modal.length) {
                return false;
            }
            var $jq = checklistJquery();
            if ($jq && typeof $jq.fn.modal === 'function') {
                $jq($modal.get()).modal('show');
                return true;
            }
            if (typeof $modal.modal === 'function') {
                $modal.modal('show');
                return true;
            }
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal && $modal[0]) {
                bootstrap.Modal.getOrCreateInstance($modal[0]).show();
                return true;
            }
            return false;
        }

        function hideChecklistModal($modal) {
            if (!$modal || !$modal.length) {
                return false;
            }
            var $jq = checklistJquery();
            if ($jq && typeof $jq.fn.modal === 'function') {
                $jq($modal.get()).modal('hide');
                return true;
            }
            if (typeof $modal.modal === 'function') {
                $modal.modal('hide');
                return true;
            }
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal && $modal[0]) {
                var instance = bootstrap.Modal.getInstance($modal[0]);
                if (instance) {
                    instance.hide();
                }
                return true;
            }
            return false;
        }

        function bindChecklistsTabUi() {
        var $ = checklistJquery();
        if (typeof $ === 'undefined') {
            return;
        }
        var $checklistsTab = $('#checklists-tab');
        if (!$checklistsTab.length || $checklistsTab.attr('data-checklists-lazy') === '1') {
            return;
        }
        $(document).off('.checklistsTabUi');
        $('#btn-add-checklist').off('.checklistsTabUi');
        $('#checklist-create-dropdown').off('.checklistsTabUi');
        $('.checklist-item-header').off('.checklistsTabUi');
        $('.checklist-item-details').off('.checklistsTabUi');
        $('#checklist_general_matter_checkbox').off('.checklistsTabUi');

        var currentClientMatterId = $checklistsTab.data('current-matter-id') || null;
        var currentMatterNeedsCostAssignment = String($checklistsTab.data('needs-cost-assignment') || '') === '1';
        var $btnAdd = $('#btn-add-checklist');
        var $dropdown = $('#checklist-create-dropdown');
        var $matterSelect = $('#checklist_matter_select');

        function openExistingMatterCostAssignmentModal(clientId, clientMatterId) {
            if (!clientId || !clientMatterId) {
                alert('Unable to open cost assignment: missing client or matter information.');
                return;
            }
            var $modal = $('#costAssignmentCreateFormModel');
            if (!$modal.length) {
                alert('Cost assignment form is not available. Please refresh the page.');
                return;
            }
            $modal.find('#cost_assignment_client_id').val(clientId);
            $modal.find('#cost_assignment_client_matter_id').val(clientMatterId);
            $modal.find('#costAssignmentModalLabel').text('Create Cost Assignment');
            var showModal = function() {
                showChecklistModal($modal);
            };
            if (typeof window.getCostAssignmentMigrationAgentDetail === 'function') {
                window.getCostAssignmentMigrationAgentDetail(clientId, clientMatterId, '#costAssignmentCreateFormModel', showModal);
            } else if (typeof getCostAssignmentMigrationAgentDetail === 'function') {
                getCostAssignmentMigrationAgentDetail(clientId, clientMatterId, '#costAssignmentCreateFormModel', showModal);
            } else {
                alert('Cost assignment function not available. Please refresh the page.');
            }
        }

        function openLeadCostAssignmentModal(clientId, matterId, migrationAgent, personResponsible, personAssisting, officeId) {
            destroyLeadCostAssignmentMmSelect();
            $('#cost_assignment_lead_id').val(clientId);
            $('#sel_matter_id_lead').val(matterId);
            $('#sel_migration_agent_id_lead').val(migrationAgent);
            $('#sel_person_responsible_id_lead').val(personResponsible);
            $('#sel_person_assisting_id_lead').val(personAssisting);
            $('#sel_office_id_lead').val(officeId);
            // Same guard as initChecklistMmSelect / destroyLeadCostAssignmentMmSelect — never block modal open
            if (typeof $.fn.mmSelect === 'function') {
                $('#sel_migration_agent_id_lead,#sel_person_responsible_id_lead,#sel_person_assisting_id_lead,#sel_office_id_lead,#sel_matter_id_lead').mmSelect({
                    dropdownParent: $('#costAssignmentCreateFormModelLead'),
                    minimumResultsForSearch: 0,
                    width: '100%'
                });
            }
            $('#sel_matter_id_lead').trigger('change');
            showChecklistModal($('#costAssignmentCreateFormModelLead'));
        }

        // Set up cost assignment for the matter already in the URL (does not create a new matter)
        $(document).on('click.checklistsTabUi', '.btn-setup-cost-assignment-for-matter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var clientId = window.ClientDetailConfig ? window.ClientDetailConfig.clientId : $('.crm-container').data('client-id');
            if (!clientId || !currentClientMatterId) {
                alert('Matter information not found. Please refresh the page.');
                return;
            }
            destroyChecklistMmSelect();
            $dropdown.hide();
            openExistingMatterCostAssignmentModal(clientId, currentClientMatterId);
        });

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
        $btnAdd.on('click.checklistsTabUi', function(e) {
            e.stopPropagation();
            $dropdown.toggle();
            if ($dropdown.is(':visible')) {
                initChecklistMmSelect();
            } else {
                destroyChecklistMmSelect();
            }
        });

        // Close panel when clicking outside (do not close while the Tom Select menu is open)
        $(document).on('click.checklistsTabUi', function(e) {
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
        $dropdown.on('click.checklistsTabUi', '.btn-cancel-checklist', function() {
            destroyChecklistMmSelect();
            $dropdown.hide();
        });

        // General Matter checkbox: when checked, use matter 1 (same as Convert Lead To Client)
        $('#checklist_general_matter_checkbox').on('change.checklistsTabUi', function() {
            if ($(this).is(':checked')) {
                $matterSelect.val('1').trigger('change');
            } else {
                $matterSelect.val('').trigger('change');
            }
        });

        // Continue / Save - uses Lead flow (matter type from admin list)
        $dropdown.on('click.checklistsTabUi', '.btn-continue-cost-assignment', function() {
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
            openLeadCostAssignmentModal(clientId, matterId, migrationAgent, personResponsible, personAssisting, officeId);
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

        window.initChecklistMmSelect = initChecklistMmSelect;

        // Accordion toggle functionality
        $('.checklist-item-header').on('click.checklistsTabUi', function() {
            var $this = $(this);
            var isExpanded = $this.attr('aria-expanded') === 'true';
            
            // Close all other accordions
            $('.checklist-item-header').not($this).attr('aria-expanded', 'false');
            $('.checklist-item-details').not($this.next()).removeClass('show');
            
            // Toggle current accordion
            $this.attr('aria-expanded', !isExpanded);
        });

        // Handle Bootstrap collapse events for proper aria-expanded state
        $('.checklist-item-details').on('shown.bs.collapse.checklistsTabUi', function() {
            $(this).prev('.checklist-item-header').attr('aria-expanded', 'true');
        }).on('hidden.bs.collapse.checklistsTabUi', function() {
            $(this).prev('.checklist-item-header').attr('aria-expanded', 'false');
        });

        // Send Checklist - open compose modal with matter selected (checklist rows filtered; user picks attachments)
        $(document).on('click.checklistsTabUi', '.btn-send-checklist', function(e) {
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

            showChecklistModal($('#emailmodal'));
        });

        // Copy signature link to clipboard
        $(document).on('click.checklistsTabUi', '.btn-copy-signature-link', function() {
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
        $(document).on('click.checklistsTabUi', '.btn-send-signature-email', function(e) {
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

            showChecklistModal($('#emailmodal'));
        });

        // Send Reminder - AJAX (no full page reload)
        $(document).on('click.checklistsTabUi', '.btn-send-signature-reminder', function(e) {
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
        $(document).on('click.checklistsTabUi', '.btn-amend-checklist', function(e) {
            e.stopPropagation();
            var clientId = $(this).data('client-id');
            var clientMatterId = $(this).data('client-matter-id');
            
            if (!clientId || !clientMatterId) {
                alert('Unable to open cost assignment: missing client or matter information.');
                return;
            }
            
            // Set client/matter IDs in the modal form (scope to modal to avoid subtab form)
            var $modal = $('#costAssignmentCreateFormModel');
            if (!$modal.length) {
                alert('Cost assignment form is not available. Please refresh the page.');
                return;
            }
            $modal.find('#cost_assignment_client_id').val(clientId);
            $modal.find('#cost_assignment_client_matter_id').val(clientMatterId);
            
            // Update modal title for edit mode
            $modal.find('#costAssignmentModalLabel').text('Amend Cost Assignment');
            
            // Load existing cost assignment data into the modal, then show it when loaded
            var showAmendModal = function() {
                showChecklistModal($modal);
            };
            if (typeof window.getCostAssignmentMigrationAgentDetail === 'function') {
                window.getCostAssignmentMigrationAgentDetail(clientId, clientMatterId, '#costAssignmentCreateFormModel', showAmendModal);
            } else if (typeof getCostAssignmentMigrationAgentDetail === 'function') {
                getCostAssignmentMigrationAgentDetail(clientId, clientMatterId, '#costAssignmentCreateFormModel', showAmendModal);
            } else {
                alert('Cost assignment function not available. Please refresh the page.');
            }
        });

        // When finalize button is clicked and agreement is uploaded, handle signature flow
        $(document).on('agreementUploaded.checklistsTabUi', function(e, data) {
            if (data.signatureLink) {
                // Reload the checklist tab to show the signature link
                location.reload();
            }
        });
        };

        window.bindChecklistsTabUi = bindChecklistsTabUi;
        // Already deferred past layout scripts (or fragment inject); bind immediately.
        bindChecklistsTabUi();
    }

    function startChecklistsBoot() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        bootChecklistsTab();
    }

    // Defer until DOMContentLoaded so layout's app.min.js + bootstrap5-jquery-compat
    // have replaced window.jQuery and registered $.fn.modal. Lazy fragment inject
    // already runs after that (readyState !== 'loading') and boots immediately.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startChecklistsBoot);
    } else {
        startChecklistsBoot();
    }
})();
</script>
