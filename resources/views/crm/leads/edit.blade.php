@extends('layouts.crm_client_detail_dashboard')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
    <link rel="stylesheet" href="{{asset('css/leads/lead-form.css')}}">
    <link rel="stylesheet" href="{{asset('css/anzsco-admin.css')}}">
@endpush

@section('content')
    <div class="crm-container">
<div class="main-content">

            <!-- Display General Errors -->
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
			</div>
            @endif

            <!-- Mobile Sidebar Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                @icon('fa-bars')
            </button>

            <!-- Sidebar Navigation -->
            <div class="sidebar-navigation" id="sidebarNav">
                <div class="nav-header">
                    <h3>@icon('fa-user-edit') Edit Lead : {{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</h3>
                    <div class="client-id">
                        Lead ID : {{ $fetchedData->client_id }}
                    </div>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('personalSection')">
                        @icon('fa-user-circle')
                        <span>Personal</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('visaPassportSection')">
                        @icon('fa-id-card')
                        <span>Visa, Passport & Citizenship</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('addressTravelSection')">
                        @icon('fa-map-marker-alt')
                        <span>Address & Travel</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('skillsEducationSection')">
                        @icon('fa-briefcase')
                        <span>Skills & Education</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('otherInformationSection')">
                        @icon('fa-info-circle')
                        <span>Other Information</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('familySection')">
                        @icon('fa-users')
                        <span>Family Information</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('eoiReferenceSection')">
                        @icon('fa-file-alt')
                        <span>EOI Reference</span>
                    </button>
                </nav>
                
                <!-- Back Button in Sidebar -->
                <div class="sidebar-actions">
                    <button type="button" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;" onclick="document.getElementById('editLeadForm').submit();">
                        @icon('fa-save')
                        <span>Save Lead</span>
                    </button>
                    <a href="{{route('leads.index')}}" class="nav-item summary-nav back-btn">
                        @icon('fa-arrow-left')
                        <span>Back to Leads</span>
                    </a>
                </div>
            </div>
            
            <!-- Configuration for external JavaScript -->
            <script>
                window.editClientConfig = {
                    visaTypesRoute: '{{ route("getVisaTypes") }}',
                    countriesRoute: '{{ route("getCountries") }}',
                    searchPartnerRoute: '{{ route("clients.searchPartner") }}',
                    csrfToken: '{{ csrf_token() }}'
                };
                window.currentClientId = '{{ $fetchedData->id }}';
                window.currentClientType = @json($fetchedData->type);
                window.latestClientMatterRef = null;
            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">
                <form id="editLeadForm" action="{{ route('leads.update', base64_encode(convert_uuencode($fetchedData->id))) }}" method="POST" enctype="multipart/form-data">
				@csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

                <!-- Personal Section -->
                <section id="personalSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-user-circle') Basic Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('basicInfo')">
                                    @icon('fa-pen')
                                </button>
								</div>
							</div>
                        
                        <!-- Summary View -->
                        <div id="basicInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">Name:</span>
                                    <span class="summary-value">{{ $fetchedData->first_name }} {{ $fetchedData->last_name }}</span>
						</div>
                                <div class="summary-item">
                                    <span class="summary-label">{{ $fetchedData->type == 'lead' ? 'Lead ID' : 'Client ID' }}:</span>
                                    <span class="summary-value">{{ $fetchedData->client_id }}</span>
					</div>
                                <div class="summary-item">
                                    <span class="summary-label">Date of Birth:</span>
                                    <span class="summary-value">{{ $fetchedData->dob ? date('d/m/Y', strtotime($fetchedData->dob)) : 'Not set' }}</span>
												</div>
                                <div class="summary-item">
                                    <span class="summary-label">Age:</span>
                                    <span class="summary-value">{{ $fetchedData->age ?: 'Not calculated' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Gender:</span>
                                    <span class="summary-value">{{ $fetchedData->gender ?: 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Marital Status:</span>
                                    <span class="summary-value">{{ $fetchedData->marital_status ?: 'Not set' }}</span>
                                </div>
                                @if($fetchedData->type === 'lead')
                                    @php
                                        $sumStage = $fetchedData->lead_status ?: 'new';
                                        $sumStageLabel = ($leadStageLabels[$sumStage] ?? ucfirst(str_replace('_', ' ', $sumStage)));
                                    @endphp
                                    <div class="summary-item">
                                        <span class="summary-label">Lead stage:</span>
                                        <span class="summary-value">{{ $sumStageLabel }}</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Follow-up date:</span>
                                        <span class="summary-value">
                                            @if($fetchedData->lead_status === 'follow_up' && $fetchedData->followup_date)
                                                {{ $fetchedData->followup_date instanceof \Carbon\Carbon ? $fetchedData->followup_date->format('d/m/Y') : \Carbon\Carbon::parse($fetchedData->followup_date)->format('d/m/Y') }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Assigned to:</span>
                                        <span class="summary-value">
                                            @if($fetchedData->assignedTo)
                                                {{ $fetchedData->assignedTo->first_name }} {{ $fetchedData->assignedTo->last_name }}
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </div>
                                @endif
                            </div>
											</div>	
											
                        <!-- Edit View -->
                        <div id="basicInfoEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group">
                                    <label for="firstName">First Name</label>
                                    <input type="text" id="firstName" name="first_name" value="{{ $fetchedData->first_name }}" required>
                                    @error('first_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
										</div>
                                <div class="form-group">
                                    <label for="lastName">Last Name</label>
                                    <input type="text" id="lastName" name="last_name" value="{{ $fetchedData->last_name }}">
                                    @error('last_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
									</div>

												<div class="form-group"> 
                                    <label for="clientId">{{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }}</label>
                                    <input type="text" id="clientId" name="client_id" value="{{ $fetchedData->client_id }}" readonly>
                                    @error('client_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
												</div>

                                <div class="form-group">
                                    <label for="dob">Date of Birth</label>
                                    <input type="text" id="dob" name="dob" value="{{ $fetchedData->dob ? date('d/m/Y', strtotime($fetchedData->dob)) : '' }}" placeholder="dd/mm/yyyy" autocomplete="off">
                                    @error('dob')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
											</div>

												<div class="form-group"> 
                                    <label for="age">Age</label>
                                    <input type="text" id="age" name="age" value="{{ $fetchedData->age }}" readonly>
                                    @error('age')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
												</div>

                                <div class="form-group">
                                    <label for="gender">Gender <span class="text-danger">*</span></label>
                                    <select id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" {{ $fetchedData->gender == 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ $fetchedData->gender == 'Female' ? 'selected' : '' }}>Female</option>
                                        <option value="Other" {{ $fetchedData->gender == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                    @error('gender')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
											</div>

												<div class="form-group"> 
                                    <label for="maritalStatus">Marital Status</label>
                                    <select id="maritalStatus" name="marital_status">
                                        <option value="">Select Marital Status</option>
                                        <option value="Never Married" {{ ($fetchedData->marital_status == 'Never Married' || $fetchedData->marital_status == 'Single') ? 'selected' : '' }}>Never Married</option>
                                        <option value="Engaged" {{ $fetchedData->marital_status == 'Engaged' ? 'selected' : '' }}>Engaged</option>
                                        <option value="Married" {{ $fetchedData->marital_status == 'Married' ? 'selected' : '' }}>Married</option>
                                        <option value="De Facto" {{ ($fetchedData->marital_status == 'Defacto' || $fetchedData->marital_status == 'De Facto') ? 'selected' : '' }}>De Facto</option>
                                        <option value="Separated" {{ $fetchedData->marital_status == 'Separated' ? 'selected' : '' }}>Separated</option>
                                        <option value="Divorced" {{ $fetchedData->marital_status == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                                        <option value="Widowed" {{ $fetchedData->marital_status == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                    </select>
                                    @error('marital_status')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
													</div>
                                @if($fetchedData->type === 'lead')
                                    @php
                                        $editStage = old('lead_status', $fetchedData->lead_status ?: 'new');
                                        $editFu = old('followup_date');
                                        if ($editFu === null && $fetchedData->followup_date) {
                                            $editFu = $fetchedData->followup_date instanceof \Carbon\Carbon
                                                ? $fetchedData->followup_date->format('Y-m-d')
                                                : \Carbon\Carbon::parse($fetchedData->followup_date)->format('Y-m-d');
                                        }
                                    @endphp
                                    <div class="form-group">
                                        <label for="lead_pipeline_status_edit">Lead stage</label>
                                        <select id="lead_pipeline_status_edit" name="lead_status" class="form-control">
                                            @foreach(($leadStageLabels ?? []) as $val => $lbl)
                                                <option value="{{ $val }}" {{ $editStage === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                        @error('lead_status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group" id="lead_followup_date_wrap_edit" style="display: none;">
                                        <label for="lead_followup_date_edit">Follow-up date <span class="text-muted">(optional)</span></label>
                                        <input type="date" id="lead_followup_date_edit" name="followup_date" class="form-control" value="{{ $editFu }}">
                                        @error('followup_date')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="assigned_staff_id_edit">Assigned to</label>
                                        <select id="assigned_staff_id_edit" name="assigned_staff_id" class="form-control">
                                            @foreach(($assignableStaff ?? collect()) as $st)
                                                <option value="{{ $st->id }}" {{ (string) old('assigned_staff_id', $fetchedData->user_id) === (string) $st->id ? 'selected' : '' }}>
                                                    {{ $st->first_name }} {{ $st->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('assigned_staff_id')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                @endif
													</div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveBasicInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('basicInfo')">Cancel</button>
													</div>
                        </div>
                    </section>

                    <!-- Contact Information -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-mobile-alt') Phone Numbers</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('phoneNumbers')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPhoneNumber()" title="Add Phone Number">
                                    @icon('fa-plus')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="phoneNumbersSummary" class="summary-view">
                            @if($clientContacts->count() > 0)
                                <div class="summary-grid">
                                    @foreach($clientContacts as $index => $contact)
                                        <div class="summary-item">
                                            <span class="summary-label">{{ $contact->contact_type }}:</span>
                                            <span class="summary-value">{{ $contact->country_code }}{{ $contact->phone }}</span>
                                            <!-- Verification Button/Badge -->
                                            @if($contact->canVerify())
                                                @if($contact->is_verified)
                                                    <span class="verified-badge" title="Verified on {{ $contact->verified_at ? $contact->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                        @icon('fa-check-circle') Verified
														</span> 
                                                @else
                                                    <button type="button" class="btn-verify-phone" onclick="sendOTP({{ $contact->id }}, '{{ $contact->phone }}', '{{ $contact->country_code }}')" data-contact-id="{{ $contact->id }}">
                                                        @icon('fa-lock') Verify
                                                    </button>
                                                @endif
													@endif
												</div>
                                    @endforeach
											</div>
                            @else
                                <div class="empty-state">
                                    <p>No phone numbers added yet.</p>
															</div>
														@endif
													</div>

                        <!-- Edit View -->
                        <div id="phoneNumbersEdit" class="edit-view hidden">
                            <div id="phoneNumbersContainer">
                                @foreach($clientContacts as $index => $contact)
                                    <x-client-edit.phone-number-field :index="$index" :contact="$contact" />
                                @endforeach
												</div>

                            <button type="button" class="add-item-btn" onclick="addPhoneNumber()">@icon('fa-plus-circle') Add Phone Number</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePhoneNumbers()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('phoneNumbers')">Cancel</button>
											</div>
															</div>
                    </section>

                    <!-- Email Addresses -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-envelope') Email Addresses</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('emailAddresses')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addEmailAddress()" title="Add Email Address">
                                    @icon('fa-plus')
                                </button>
														</div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="emailAddressesSummary" class="summary-view">
                            @if($emails->count() > 0)
                                <div class="summary-grid">
                                    @foreach($emails as $index => $email)
                                        <div class="summary-item">
                                            <span class="summary-label">{{ $email->email_type }}:</span>
                                            <span class="summary-value">{{ $email->email }}</span>
                                            <!-- Verification Button/Badge -->
                                            @if($email->is_verified)
                                                <span class="verified-badge" title="Verified on {{ $email->verified_at ? $email->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                    @icon('fa-check-circle') Verified
															</span> 
                                            @else
                                                <button type="button" class="btn-verify-email" onclick="sendEmailVerification({{ $email->id }}, '{{ $email->email }}')" data-email-id="{{ $email->id }}">
                                                    @icon('fa-lock') Verify
                                                </button>
														@endif
													</div>
                                    @endforeach
												</div>
                            @else
                                <div class="empty-state">
                                    <p>No email addresses added yet.</p>
											</div>
													@endif
												</div>

                        <!-- Edit View -->
                        <div id="emailAddressesEdit" class="edit-view hidden">
                            <div id="emailAddressesContainer">
                                @foreach($emails as $index => $email)
                                    <x-client-edit.email-field :index="$index" :email="$email" />
                                @endforeach
											</div>

                            <button type="button" class="add-item-btn" onclick="addEmailAddress()">@icon('fa-plus-circle') Add Email Address</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveEmailAddresses()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('emailAddresses')">Cancel</button>
										</div>
									</div>
                    </section>
                </section>

                <!-- Visa, Passport & Citizenship Section -->
                <section id="visaPassportSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-id-card') Passport Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('passportInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addPassportDetail()" title="Add Passport">
                                    @icon('fa-plus')
                                </button>
										</div>
									</div>
                        
                        <!-- Summary View -->
                        <div id="passportInfoSummary" class="summary-view">
                            @if($clientPassports->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientPassports as $index => $passport)
                                        <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $passport->passport_country ?: 'Not set' }}</span>
												</div>	
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">PASSPORT #:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $passport->passport ?: 'Not set' }}</span>
											</div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ISSUE DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $passport->passport_issue_date ? date('d/m/Y', strtotime($passport->passport_issue_date)) : 'Not set' }}</span>
										</div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $passport->passport_expiry_date ? date('d/m/Y', strtotime($passport->passport_expiry_date)) : 'Not set' }}</span>
									</div>
										</div>
									</div>
                                    @endforeach
										</div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No passport details added yet.</p>
									</div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="passportInfoEdit" class="edit-view" style="display: none;">
                            <!-- Passport Details -->
                            <div id="passportDetailsContainer">
                                @foreach($clientPassports as $index => $passport)
                                    <x-client-edit.passport-field
                                        :index="$index"
                                        :passport="$passport"
                                    />
                                @endforeach
									</div> 

                            <button type="button" class="add-item-btn" onclick="addPassportDetail()">@icon('fa-plus-circle') Add Passport</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePassportInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('passportInfo')">Cancel</button>
												</div>	
                        </div>
                    </section>

                    <!-- Visa Information -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-plane-departure') Visa Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('visaInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addVisaDetail()" title="Add Visa Detail">
                                    @icon('fa-plus')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="visaInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">Visa Expiry Verified:</span>
                                    <span class="summary-value">{{ $fetchedData->visa_expiry_verified_at ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                            @if($visaCountries->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($visaCountries as $index => $visa)
                                        <div class="visa-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #28a745;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">VISA TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                        {{ $visa->matter ? $visa->matter->title . ' (' . $visa->matter->nick_name . ')' : 'Not set' }}
													</span> 
											</div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $visa->visa_expiry_date ? date('d/m/Y', strtotime($visa->visa_expiry_date)) : 'Not set' }}</span>
										</div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GRANT DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $visa->visa_grant_date ? date('d/m/Y', strtotime($visa->visa_grant_date)) : 'Not set' }}</span>
									</div>	
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DESCRIPTION:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $visa->visa_description ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
										@endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No visa details added yet.</p>
                                </div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="visaInfoEdit" class="edit-view" style="display: none;">
                            <!-- Visa Details -->
                            <div id="visaDetailsSection">
                                <div id="visaDetailsContainer">
                                    @foreach($visaCountries as $index => $visa)
                                        <x-client-edit.visa-field
                                            :index="$index"
                                            :visa="$visa"
                                        />
                                    @endforeach
									</div>

                                <button type="button" class="add-item-btn" onclick="addVisaDetail()">@icon('fa-plus-circle') Add Visa Detail</button>
													</div>

                            <!-- Visa Expiry Verified -->
                            <div id="visaExpiryVerifiedContainer" class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                                <label>Visa Expiry Verified?</label>
                                <label class="switch" style="margin: 0;">
                                    <input type="checkbox" name="visa_expiry_verified" value="1" {{ $fetchedData->visa_expiry_verified_at ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
												</div>
                            
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveVisaInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('visaInfo')">Cancel</button>
											</div>
										</div>
                    </section>
                </section>

                <!-- Address & Travel Section -->
                <section id="addressTravelSection" class="content-section">
                    <x-client-edit.address-section 
                        :clientAddresses="$clientAddresses"
                        :searchRoute="route('clients.searchAddressFull')"
                        :detailsRoute="route('clients.getPlaceDetails')"
                        :csrfToken="csrf_token()"
                    />
                    
                    <!-- Travel Information Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-plane-departure') Travel Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('travelInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addTravelDetail()" title="Add Travel Detail">
                                    @icon('fa-plus')
                                </button>
									</div>
													</div>
                        
                        <!-- Summary View -->
                        <div id="travelInfoSummary" class="summary-view">
                            @if($clientTravels->count() > 0)
                                <div>
                                    @foreach($clientTravels as $index => $travel)
                                        <div class="address-entry-compact">
                                            <div class="address-compact-grid">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">COUNTRY VISITED:</span>
                                                    <span class="summary-value">{{ $travel->country_visited ?: 'Not set' }}</span>
												</div>
                                                @if($travel->arrival_date)
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">ARRIVAL DATE:</span>
                                                    <span class="summary-value">{{ date('d/m/Y', strtotime($travel->arrival_date)) }}</span>
                                                </div>
												@endif
                                                @if($travel->departure_date)
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">DEPARTURE DATE:</span>
                                                    <span class="summary-value">{{ date('d/m/Y', strtotime($travel->departure_date)) }}</span>
											</div>
                                                @endif
                                                @if($travel->travel_purpose)
                                                <div class="summary-item-inline">
                                                    <span class="summary-label">TRAVEL PURPOSE:</span>
                                                    <span class="summary-value">{{ $travel->travel_purpose }}</span>
										</div> 
											@endif 
										</div>
									</div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No travel details added yet.</p>
                                </div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="travelInfoEdit" class="edit-view" style="display: none;">
                            <div id="travelDetailsContainer">
                                @foreach($clientTravels as $index => $travel)
                                    <x-client-edit.travel-field
                                        :index="$index"
                                        :travel="$travel"
                                    />
                                @endforeach
									</div>

                            <button type="button" class="add-item-btn" onclick="addTravelDetail()">@icon('fa-plus-circle') Add Travel Detail</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveTravelInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('travelInfo')">Cancel</button>
								</div>
                        </div>
                    </section>
                </section>

                <!-- Skills & Education Section -->
                <section id="skillsEducationSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-graduation-cap') Educational Qualifications</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('qualificationsInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addQualification()" title="Add Qualification">
                                    @icon('fa-plus')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="qualificationsInfoSummary" class="summary-view">
                            @if($qualifications->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($qualifications as $index => $qualification)
                                        <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #6f42c1;">
                                            <div style="display: grid; grid-template-columns: 180px 1fr auto auto auto auto auto auto; gap: 15px; align-items: start;">
                                                @if($qualification->level)
                                                <div class="summary-item-inline" style="grid-column: 1;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">LEVEL:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $qualification->level }}</span>
                                                </div>
											@endif
                                                @if($qualification->name)
                                                <div class="summary-item-inline" style="grid-column: 2;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">NAME:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $qualification->name }}</span>
										</div>
                                                @endif
                                                @if($qualification->qual_college_name)
                                                <div class="summary-item-inline" style="grid-column: 3;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">INSTITUTION:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->qual_college_name }}</span>
									</div>
											@endif
                                                @if($qualification->qual_campus)
                                                <div class="summary-item-inline" style="grid-column: 4;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CAMPUS/ADDRESS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->qual_campus }}</span>
										</div>
                                                @endif
                                                @if($qualification->country)
                                                <div class="summary-item-inline" style="grid-column: 5;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->country }}</span>
									</div>
											@endif
                                                @if($qualification->qual_state)
                                                <div class="summary-item-inline" style="grid-column: 6;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">STATUS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $qualification->qual_state }}</span>
										</div>
                                                @endif
                                                @if($qualification->start_date)
                                                <div class="summary-item-inline" style="grid-column: 7;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">START DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ date('d/m/Y', strtotime($qualification->start_date)) }}</span>
									</div>
                                                @endif
                                                @if($qualification->finish_date)
                                                <div class="summary-item-inline" style="grid-column: 8;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">FINISH DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ date('d/m/Y', strtotime($qualification->finish_date)) }}</span>
                                                </div>
                                                @endif
                                                @if($qualification->relevant_qualification)
                                                <div class="summary-item-inline" style="grid-column: 1 / -1;">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELEVANT:</span>
                                                    <span class="summary-value" style="color: #28a745; font-weight: 500;">
                                                        @icon('fa-check-circle') Yes
												</span> 
                                                </div>
											@endif
										</div>
									</div>
                                    @endforeach
								</div>
                            @else
                                <div class="empty-state">
                                    <p>No qualifications added yet.</p>
                                </div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="qualificationsInfoEdit" class="edit-view" style="display: none;">
                            <div id="qualificationsContainer">
                                @foreach($qualifications as $index => $qualification)
                                    <x-client-edit.qualification-field
                                        :index="$index"
                                        :qualification="$qualification"
                                    />
                                @endforeach
									</div>

                            <button type="button" class="add-item-btn" onclick="addQualification()">@icon('fa-plus-circle') Add Qualification</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveQualificationsInfo()">
                                    @icon('fa-save') Save
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('qualificationsInfo')">
                                    @icon('fa-times') Cancel
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Work Experience Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-briefcase') Work Experience</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('experienceInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addExperience()" title="Add Experience">
                                    @icon('fa-plus')
                                </button>
										</div>
									</div>
                        
                        <!-- Summary View -->
                        <div id="experienceInfoSummary" class="summary-view">
                            @if($experiences->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($experiences as $index => $experience)
                                        <div class="experience-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div class="experience-compact-grid">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB TITLE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $experience->job_title ?: 'Not set' }}</span>
										</div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ANZSCO CODE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $experience->job_code ?: 'Not set' }}</span>
									</div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EMPLOYER NAME:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $experience->job_emp_name ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COUNTRY:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_country ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ADDRESS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_state ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">JOB TYPE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">START DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_start_date ? date('d/m/Y', strtotime($experience->job_start_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">FINISH DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->job_finish_date ? date('d/m/Y', strtotime($experience->job_finish_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELEVANT:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $experience->relevant_experience ? 'Yes' : 'No' }}</span>
                                                </div>
                                                @if($index === 0)
                                                <div class="summary-item-inline experience-current-summary">
                                                    <span class="summary-label">CURRENT:</span>
                                                    <label class="switch switch-readonly" style="margin: 0;" title="Current experience">
                                                        <input type="checkbox" checked disabled>
                                                        <span class="slider round"></span>
                                                    </label>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No work experience added yet.</p>
                                </div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="experienceInfoEdit" class="edit-view" style="display: none;">
                            <div id="experienceContainer">
                                @foreach($experiences as $index => $experience)
                                    <x-client-edit.work-experience-field
                                        :index="$index"
                                        :experience="$experience"
                                        :isCurrent="$index === 0"
                                    />
                                @endforeach
									</div>

                            <button type="button" class="add-item-btn" onclick="addExperience()">@icon('fa-plus-circle') Add Experience</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveExperienceInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('experienceInfo')">Cancel</button>
										</div>
									</div>
                    </section>

                    <!-- Occupation & Skills Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-cogs') Occupation & Skills</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('occupationInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addOccupation()" title="Add Occupation">
                                    @icon('fa-plus')
                                </button>
										</div>
									</div>
                        
                        <!-- Summary View -->
                        <div id="occupationInfoSummary" class="summary-view">
                            @if($clientOccupations->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientOccupations as $index => $occupation)
                                        <div class="occupation-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #28a745;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SKILL ASSESSMENT:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $occupation->skill_assessment ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">NOMINATED OCCUPATION:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $occupation->nomi_occupation ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">OCCUPATION CODE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $occupation->occupation_code ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ASSESSING AUTHORITY:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->list ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">VISA SUBCLASS:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->visa_subclass ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ASSESSMENT DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->dates ? date('d/m/Y', strtotime($occupation->dates)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EXPIRY DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->expiry_dates ? date('d/m/Y', strtotime($occupation->expiry_dates)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">REFERENCE NO:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $occupation->occ_reference_no ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="no-data-message">
                                    <p>No occupation information available.</p>
                                </div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="occupationInfoEdit" class="edit-view" style="display: none;">
                            <div id="occupationContainer">
                                @foreach($clientOccupations as $index => $occupation)
                                    <x-client-edit.occupation-field 
                                        :index="$index" 
                                        :occupation="$occupation" 
                                    />
                                @endforeach
									</div>

                            <button type="button" class="add-item-btn" onclick="addOccupation()">@icon('fa-plus-circle') Add Occupation</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveOccupationInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('occupationInfo')">Cancel</button>
										</div>
									</div>
                    </section>

                    <!-- English Test Scores Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-language') English Test Scores</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('testScoreInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addTestScore()" title="Add Test Score">
                                    @icon('fa-plus')
                                </button>
										</div>
									</div>
                        
                        <!-- Summary View -->
                        <div id="testScoreInfoSummary" class="summary-view">
                            @if($testScores->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($testScores as $index => $testScore)
                                        <div class="test-score-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">TEST TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->test_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">LISTENING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->listening ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">READING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->reading ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">WRITING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->writing ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SPEAKING:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->speaking ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">OVERALL:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $testScore->overall_score ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">TEST DATE:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $testScore->test_date ? date('d/m/Y', strtotime($testScore->test_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">REFERENCE NO:</span>
                                                    <span class="summary-value" style="color: #212529;">{{ $testScore->test_reference_no ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">PROFICIENCY LEVEL:</span>
                                                    <span id="proficiency-level-{{ $index }}" class="proficiency-level-display" style="font-weight: 700; font-size: 0.9em; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                                        @icon('fa-spinner', ['spin' => true]) Calculating...
												</span> 
										</div>
									</div>
                                            
                                            <!-- Hidden data attributes for JavaScript calculation -->
                                            <div class="english-level-calculation-box" 
                                                 data-test-type="{{ $testScore->test_type }}" 
                                                 data-listening="{{ $testScore->listening }}" 
                                                 data-reading="{{ $testScore->reading }}" 
                                                 data-writing="{{ $testScore->writing }}" 
                                                 data-speaking="{{ $testScore->speaking }}" 
                                                 data-overall="{{ $testScore->overall_score }}" 
                                                 data-test-date="{{ $testScore->test_date ? date('d/m/Y', strtotime($testScore->test_date)) : '' }}"
                                                 style="display: none;">
										</div>
									</div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No test score information available.</p>
                                </div>
											@endif
										</div>

                        <!-- Edit View -->
                        <div id="testScoreInfoEdit" class="edit-view" style="display: none;">
                            <div id="testScoresContainer">
                                @foreach($testScores as $index => $testScore)
                                    <x-client-edit.test-score-field 
                                        :index="$index" 
                                        :testScore="$testScore" 
                                    />
                                @endforeach
									</div>

                            <button type="button" class="add-item-btn" onclick="addTestScore()">@icon('fa-plus-circle') Add Test Score</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveTestScoreInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('testScoreInfo')">Cancel</button>
								</div>
										</div>
                    </section>
                </section>

                <!-- Other Information Section -->
                <section id="otherInformationSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-info-circle') Additional Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('additionalInfo')">
                                    @icon('fa-pen')
                                </button>
									</div>
										</div>
                        
                        <!-- Summary View -->
                        <div id="additionalInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">NAATI Test:</span>
                                    <span class="summary-value">{{ $fetchedData->naati_test ? 'Yes' : 'No' }}</span>
									</div>
                                <div class="summary-item">
                                    <span class="summary-label">NAATI Date:</span>
                                    <span class="summary-value">{{ $fetchedData->naati_date ? date('d/m/Y', strtotime($fetchedData->naati_date)) : 'Not set' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">PY Test:</span>
                                    <span class="summary-value">{{ $fetchedData->py_test ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">PY Date:</span>
                                    <span class="summary-value">{{ $fetchedData->py_date ? date('d/m/Y', strtotime($fetchedData->py_date)) : 'Not set' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Edit View -->
                        <div id="additionalInfoEdit" class="edit-view" style="display: none;">
                            <div class="content-grid">
										<div class="form-group">
                                    <label for="naatiTest">NAATI Test</label>
                                    <select id="naatiTest" name="naati_test">
                                        <option value="0" {{ !$fetchedData->naati_test ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->naati_test ? 'selected' : '' }}>Yes</option>
											</select>
										</div>
                                <div class="form-group">
                                    <label for="naatiDate">NAATI Date</label>
                                    <input type="text" id="naatiDate" name="naati_date" value="{{ $fetchedData->naati_date ? date('d/m/Y', strtotime($fetchedData->naati_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
									</div>
										<div class="form-group">
                                    <label for="pyTest">PY Test</label>
                                    <select id="pyTest" name="py_test">
                                        <option value="0" {{ !$fetchedData->py_test ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ $fetchedData->py_test ? 'selected' : '' }}>Yes</option>
											</select>
										</div>
                                <div class="form-group">
                                    <label for="pyDate">PY Date</label>
                                    <input type="text" id="pyDate" name="py_date" value="{{ $fetchedData->py_date ? date('d/m/Y', strtotime($fetchedData->py_date)) : '' }}" placeholder="dd/mm/yyyy" class="date-picker">
									</div>
                            </div>
                            
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveAdditionalInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('additionalInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Character Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-shield-alt') Character/Health Declaration</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('characterInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addCharacterRow('characterContainer', 'character_detail')" title="Add Character/Health Declaration">
                                    @icon('fa-plus')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="characterInfoSummary" class="summary-view">
                            @if($clientCharacters->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientCharacters as $index => $character)
                                        <div class="passport-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #dc3545;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: start;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CHARACTER DETAIL:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $character->character_detail ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No character/health declaration added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="characterInfoEdit" class="edit-view" style="display: none;">
                            <div id="characterContainer">
                                @foreach($clientCharacters as $index => $character)
                                    <div class="repeatable-section">
                                        <button type="button" class="remove-item-btn" title="Remove Character" onclick="removeCharacterField(this)">@icon('fa-trash')</button>
                                        <input type="hidden" name="character_id[{{ $index }}]" value="{{ $character->id }}">
                                        <div class="content-grid">
										<div class="form-group">
                                                <label>Character/Health Declaration Detail</label>
                                                <textarea name="character_detail[{{ $index }}]" rows="3" placeholder="Enter character/health declaration details">{{ $character->character_detail }}</textarea>
                                            </div>
                                        </div>
                                    </div>
							@endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addCharacterRow('characterContainer', 'character_detail')">@icon('fa-plus-circle') Add Character/Health Declaration</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveCharacterInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('characterInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Related Files Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-link') Related Files</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('relatedFilesInfo')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="relatedFilesInfoSummary" class="summary-view">
                            @if($fetchedData->related_files && $fetchedData->related_files != '')
                                <div style="margin-top: 15px;">
                                    @php
                                        $relatedFileIds = explode(',', $fetchedData->related_files);
                                    @endphp
                                    @foreach($relatedFileIds as $relatedId)
                                        @php
                                            $relatedClient = \App\Models\Admin::find($relatedId);
                                        @endphp
                                        @if($relatedClient)
                                            <div class="related-file-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #17a2b8;">
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: center;">
                                                    <div class="summary-item-inline">
                                                        <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CLIENT NAME:</span>
                                                        <span class="summary-value" style="color: #212529; font-weight: 500;">
                                                            <a href="{{ URL::to('/clients/edit/'.base64_encode(convert_uuencode($relatedClient->id))) }}" target="_blank" style="color: #007bff; text-decoration: none;">
                                                                {{ $relatedClient->first_name }} {{ $relatedClient->last_name }}
                                                            </a>
												</span> 
                                                    </div>
                                                    <div class="summary-item-inline">
                                                        <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">CLIENT ID:</span>
                                                        <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $relatedClient->client_id ?: 'N/A' }}</span>
                                                    </div>
                                                    <div class="summary-item-inline">
                                                        <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EMAIL:</span>
                                                        <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $relatedClient->email ?: 'N/A' }}</span>
                                                    </div>
                                                </div>
                                            </div>
											@endif 
                                    @endforeach
										</div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No related files added yet.</p>
									</div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="relatedFilesInfoEdit" class="edit-view" style="display: none;">
                            <div class="content-grid">
                                @if($fetchedData->visa_type != "Citizen" && $fetchedData->visa_type != "PR")
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <label for="relatedFiles">Similar Related Files</label>
                                        <select multiple class="form-control" id="relatedFiles" name="related_files[]" style="width: 100%;">
                                            @if($fetchedData->related_files && $fetchedData->related_files != '')
                                                @php
                                                    $relatedFileIds = explode(',', $fetchedData->related_files);
                                                @endphp
                                                @foreach($relatedFileIds as $relatedId)
                                                    @php
                                                        $relatedClient = \App\Models\Admin::find($relatedId);
                                                    @endphp
                                                    @if($relatedClient)
                                                        <option value="{{ $relatedClient->id }}" selected>{{ $relatedClient->first_name }} {{ $relatedClient->last_name }} ({{ $relatedClient->client_id }})</option>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </select>
                                        <small class="form-text text-muted">Search and select clients by name or client ID. You can select multiple clients.</small>
                                        @if ($errors->has('related_files'))
                                            <span class="text-danger">
                                                <strong>{{ $errors->first('related_files') }}</strong>
												</span> 
											@endif
										</div>
                                @else
                                    <div class="alert alert-info">
                                        @icon('fa-info-circle') Related Files are only available for clients with visa types other than Citizen or PR.
									</div>
											@endif
										</div>
                            
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveRelatedFilesInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('relatedFilesInfo')">Cancel</button>
									</div> 
										</div>
                    </section>
                </section>

                <!-- Family Information Section -->
                <section id="familySection" class="content-section">
                    <!-- Partner Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-heart') Partner</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('partnerInfo')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>

                        <!-- Summary View -->
                        <div id="partnerInfoSummary" class="summary-view">
                            @php
                                $partners = $clientPartners->where('relationship_type', 'Husband')->merge($clientPartners->where('relationship_type', 'Wife'))->merge($clientPartners->where('relationship_type', 'Ex-Wife'))->merge($clientPartners->where('relationship_type', 'Defacto'));
                            @endphp
                            @if($partners->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($partners as $index => $partner)
                                        <div class="partner-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->relatedClient ? $partner->relatedClient->first_name . ' ' . $partner->relatedClient->last_name : $partner->details }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $partner->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No partner information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="partnerInfoEdit" class="edit-view" style="display: none;">
                            <div id="partnerContainer">
                                @foreach($partners as $index => $partner)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$partner"
                                        type="partner"
                                        :relationshipOptions="['Husband', 'Wife', 'Ex-Husband', 'Ex-Wife', 'Mother-in-law', 'Defacto']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('partner')">@icon('fa-plus-circle') Add Partner</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="savePartnerInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('partnerInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>

                    <!-- Children Section -->
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-child') Children</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('childrenInfo')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="childrenInfoSummary" class="summary-view">
                            @php
                                $children = $clientPartners->whereIn('relationship_type', ['Son', 'Daughter', 'Step Son', 'Step Daughter']);
                            @endphp
                            @if($children->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($children as $index => $child)
                                        <div class="children-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">DETAILS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->relatedClient ? $child->relatedClient->first_name . ' ' . $child->relatedClient->last_name : $child->details }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">RELATIONSHIP:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->relationship_type ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">GENDER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->gender ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">COMPANY TYPE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $child->company_type ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No children information added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="childrenInfoEdit" class="edit-view" style="display: none;">
                            <div id="childrenContainer">
                                @foreach($children as $index => $child)
                                    <x-client-edit.family-member-field 
                                        :index="$index"
                                        :member="$child"
                                        type="children"
                                        :relationshipOptions="['Son', 'Daughter', 'Step Son', 'Step Daughter']"
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addPartnerRow('children')">@icon('fa-plus-circle') Add Child</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveChildrenInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('childrenInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- EOI Reference Section -->
                <section id="eoiReferenceSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-file-alt') EOI References</h3>
                            <div class="section-actions" style="display: none;">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('eoiInfo')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addEoiReference()" title="Add EOI Reference">
                                    @icon('fa-plus')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="eoiInfoSummary" class="summary-view">
                            @if($clientEoiReferences->count() > 0)
                                <div style="margin-top: 15px;">
                                    @foreach($clientEoiReferences as $index => $eoi)
                                        <div class="eoi-entry-compact" style="margin-bottom: 12px; padding: 12px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #007bff;">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; align-items: center;">
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">EOI NUMBER:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_number ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SUBCLASS:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_subclass ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">OCCUPATION:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_occupation ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">POINT:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_point ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">STATE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_state ?: 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">SUBMISSION DATE:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_submission_date ? date('d/m/Y', strtotime($eoi->EOI_submission_date)) : 'Not set' }}</span>
                                                </div>
                                                <div class="summary-item-inline">
                                                    <span class="summary-label" style="font-weight: 600; color: #6c757d; font-size: 0.85em;">ROI:</span>
                                                    <span class="summary-value" style="color: #212529; font-weight: 500;">{{ $eoi->EOI_ROI ?: 'Not set' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="empty-state" style="margin-top: 15px;">
                                    <p>No EOI references added yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="eoiInfoEdit" class="edit-view" style="display: none;">
                            <div id="eoiReferencesContainer">
                                @foreach($clientEoiReferences as $index => $eoi)
                                    <x-client-edit.eoi-reference-field 
                                        :index="$index" 
                                        :eoi="$eoi" 
                                    />
                                @endforeach
                            </div>

                            <button type="button" class="add-item-btn" onclick="addEoiReference()" style="display: none;">@icon('fa-plus-circle') Add EOI Reference</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveEoiInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('eoiInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>
                </form>
            </div>
        </div>
    </div>

    <!-- Go to Top Button -->
    <button id="goToTopBtn" class="go-to-top-btn" onclick="scrollToTop()" title="Go to Top">
        @icon('fa-chevron-up')
    </button>


    <!-- OTP Verification Modal -->
    <div id="otpVerificationModal" class="modal" style="display: none;">
        <div class="modal-content otp-modal">
            <div class="modal-header">
                <h3>Verify Phone Number</h3>
                <button type="button" class="close-btn" onclick="closeOTPModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="otp-info">
                    <p>We've sent a 6-digit verification code to:</p>
                    <p class="phone-display" id="otpPhoneDisplay"></p>
                    <p class="otp-timer" id="otpTimer">Code expires in <span id="timerCountdown">5:00</span></p>
                    <div class="otp-instruction">
                        <p><strong>Please ask the client to provide the verification code they received via SMS.</strong></p>
                    </div>
                </div>
                
                <div class="otp-input-container">
                    <input type="text" maxlength="1" class="otp-digit" data-index="0" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="1" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="2" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="3" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="4" autocomplete="off">
                    <input type="text" maxlength="1" class="otp-digit" data-index="5" autocomplete="off">
                </div>
                
                <div class="otp-actions">
                    <button type="button" class="btn-resend-otp" id="resendOTPBtn" onclick="resendOTP()" disabled>
                        Resend Code
                    </button>
                    <span class="resend-timer" id="resendTimer" style="display: none;">Resend available in <span id="resendCountdown">30</span>s</span>
                </div>
                
                <div class="otp-messages">
                    <div id="otpErrorMessage" class="error-message" style="display: none;"></div>
                    <div id="otpSuccessMessage" class="success-message" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeOTPModal()">Cancel</button>
                <button type="button" class="btn btn-primary" id="verifyOTPBtn" onclick="verifyOTP()">Verify</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        window.phonePopularIsoCodes = @json(config('phone.popular_countries'));
        window.phoneDefaultDialCode = @json(config('phone.default_country_code'));
    </script>
    <script src="{{ asset('js/shared/phone-country-select-builder.js') }}"></script>
    <script>
        window.countriesData = @json($countries ?? []);
        window.visaTypesData = @json($visaTypes ?? []);
    </script>
    <script src="{{asset('js/clients/edit-client.js')}}"></script>
    <script>
        // Provide minimal, safe helpers so the pencil buttons work.
        window.toggleEditMode = window.toggleEditMode || function(sectionType) {
            const summaryView = document.getElementById(sectionType + 'Summary');
            const editView = document.getElementById(sectionType + 'Edit');
            if (!summaryView || !editView) return;

            summaryView.style.display = 'none';
            summaryView.classList.add('hidden');

            editView.style.display = 'block';
            editView.classList.remove('hidden');
        };

        window.cancelEdit = window.cancelEdit || function(sectionType) {
            const summaryView = document.getElementById(sectionType + 'Summary');
            const editView = document.getElementById(sectionType + 'Edit');
            if (!summaryView || !editView) return;

            editView.style.display = 'none';
            editView.classList.add('hidden');

            summaryView.style.display = 'block';
            summaryView.classList.remove('hidden');
        };

        (function () {
            function syncLeadFollowupDateEdit() {
                var sel = document.getElementById('lead_pipeline_status_edit');
                var wrap = document.getElementById('lead_followup_date_wrap_edit');
                if (!sel || !wrap) return;
                wrap.style.display = sel.value === 'follow_up' ? 'block' : 'none';
            }
            document.addEventListener('DOMContentLoaded', function () {
                var sel = document.getElementById('lead_pipeline_status_edit');
                if (sel) {
                    sel.addEventListener('change', syncLeadFollowupDateEdit);
                    syncLeadFollowupDateEdit();
                }
            });
        })();
    </script>
    <script src="{{asset('js/clients/english-proficiency.js')}}"></script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    <script src="{{asset('js/leads/lead-form-navigation.js')}}"></script>
    {{-- Google Maps library removed - using backend proxy for address autocomplete --}}
    @endpush
@endsection
