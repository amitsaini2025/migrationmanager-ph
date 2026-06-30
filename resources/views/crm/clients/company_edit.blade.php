@extends('layouts.crm_client_detail_dashboard')

@php
    $latestMatterRefNo = null;
    if (isset($fetchedData) && $fetchedData->type === 'client') {
        $latestMatter = \App\Models\ClientMatter::where('client_id', $fetchedData->id)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->first();

        if ($latestMatter) {
            $latestMatterRefNo = $latestMatter->client_unique_matter_no;
        }
    }
    
    // Get company data
    $company = $fetchedData->company;
    $contactPerson = $company && $company->contact_person_id ? \App\Models\Admin::find($company->contact_person_id) : null;
    $isTrusteeBusinessType = $company && $company->isTrusteeBusiness();
    $companyTypeForForm = old('company_type', $company ? $company->company_type : '');
    $showTrusteeFieldsInitial = \App\Models\Company::isTrusteeBusinessType($companyTypeForForm);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/address-autocomplete.css') }}">
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
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
                    <h3>@icon('fa-building') {{ $fetchedData->type == 'lead' ? 'Edit Company Lead' : 'Edit Company Client' }} : {{ $company ? $company->company_name : 'Unnamed Company' }}</h3>
                    <div class="client-id">
                        {{ $fetchedData->type == 'lead' ? 'Lead ID' : ($fetchedData->type == 'client' ? 'Client ID' : '') }} : {{ $fetchedData->client_id }}
                    </div>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('companySection')">
                        @icon('fa-building')
                        <span>Company Information</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('contactPersonSection')">
                        @icon('fa-user-tie')
                        <span>Contact Person</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('sponsorshipSection')">
                        @icon('fa-file-contract')
                        <span>Sponsorship</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('directorsSection')">
                        @icon('fa-users-cog')
                        <span>Directors</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('financialSection')">
                        @icon('fa-dollar-sign')
                        <span>Financial</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('workforceSection')">
                        @icon('fa-users')
                        <span>Workforce</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('operationsSection')">
                        @icon('fa-briefcase')
                        <span>Operations</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('trainingSection')">
                        @icon('fa-graduation-cap')
                        <span>Training</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('nominationsSection')">
                        @icon('fa-user-check')
                        <span>Nominations</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('addressSection')">
                        @icon('fa-map-marker-alt')
                        <span>Business Address</span>
                    </button>
                    <button class="nav-item" onclick="scrollToSection('contactsSection')">
                        @icon('fa-phone')
                        <span>Contacts</span>
                    </button>
                </nav>
                
                <!-- Back Button in Sidebar -->
                <div class="sidebar-actions">
                    <button class="nav-item summary-nav back-btn" onclick="goBackWithRefresh()">
                        @icon('fa-arrow-left')
                        <span>Back</span>
                    </button>
                </div>
            </div>
            
            <!-- Configuration for external JavaScript -->
            <script>
                // Configuration object for edit-client.js
                window.editClientConfig = {
                    searchContactPersonRoute: '{{ route("api.search.contact.person") }}',
                    searchAddressRoute: '{{ route("clients.searchAddressFull") }}',
                    getPlaceDetailsRoute: '{{ route("clients.getPlaceDetails") }}',
                    csrfToken: '{{ csrf_token() }}'
                };
                
                // Current client ID for excluding from search results
                window.currentClientId = '{{ $fetchedData->id }}';
                window.currentClientType = @json($fetchedData->type);
                window.latestClientMatterRef = @json($latestMatterRefNo);
            </script>

            <!-- Main Content Area -->
            <div class="main-content-area">
                <form id="editCompanyForm" action="{{ route('clients.update') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $fetchedData->id }}">
                    <input type="hidden" name="type" value="{{ $fetchedData->type }}">

                <!-- Company Information Section -->
                <section id="companySection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-building') Company Information</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('companyInfo')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="companyInfoSummary" class="summary-view">
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <span class="summary-label">Company Name:</span>
                                    <span class="summary-value">{{ $company ? $company->company_name : 'Not set' }}</span>
                                </div>
                                @php
                                    $tradingNamesDisplay = $company && $company->tradingNames->isNotEmpty()
                                        ? $company->tradingNames->pluck('trading_name')->join(', ')
                                        : ($company && $company->trading_name ? $company->trading_name : null);
                                @endphp
                                @if($tradingNamesDisplay)
                                <div class="summary-item">
                                    <span class="summary-label">Trading Name(s):</span>
                                    <span class="summary-value">{{ $tradingNamesDisplay }}</span>
                                </div>
                                @endif
                                @if($company && $company->ABN_number)
                                <div class="summary-item">
                                    <span class="summary-label">ABN:</span>
                                    <span class="summary-value">{{ $company->ABN_number }}</span>
                                </div>
                                @endif
                                @if($company && $company->ACN)
                                <div class="summary-item">
                                    <span class="summary-label">ACN:</span>
                                    <span class="summary-value">{{ $company->ACN }}</span>
                                </div>
                                @endif
                                @if($company && $company->company_type)
                                <div class="summary-item">
                                    <span class="summary-label">Business Type:</span>
                                    <span class="summary-value">{{ \App\Models\Company::businessTypeLabel($company->company_type) }}</span>
                                </div>
                                @endif
                                @if($isTrusteeBusinessType && $company->trust_name)
                                <div class="summary-item">
                                    <span class="summary-label">Trust Name:</span>
                                    <span class="summary-value">{{ $company->trust_name }}</span>
                                </div>
                                @endif
                                @if($isTrusteeBusinessType && $company->trust_abn)
                                <div class="summary-item">
                                    <span class="summary-label">ABN/ACN (trust):</span>
                                    <span class="summary-value">{{ $company->trust_abn }}</span>
                                </div>
                                @endif
                                @if($company && $company->company_website)
                                <div class="summary-item">
                                    <span class="summary-label">Website:</span>
                                    <span class="summary-value">
                                        <a href="{{ $company->company_website }}" target="_blank">{{ $company->company_website }}</a>
                                    </span>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Edit View -->
                        <div id="companyInfoEdit" class="edit-view hidden">
                            @php
                                $defaultHasTrading = $company && ($company->has_trading_name || $company->trading_name || ($company->tradingNames?->isNotEmpty() ?? false)) ? 1 : 0;
                            @endphp
                            <div class="content-grid">
                                <div class="form-group">
                                    <label for="companyName">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" id="companyName" name="company_name" 
                                           value="{{ old('company_name', $company ? $company->company_name : '') }}" 
                                           required>
                                    @error('company_name')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label>Does this company have a trading name?</label>
                                    <div style="display: flex; gap: 20px; margin-top: 5px;">
                                        <label><input type="radio" name="has_trading_name" value="1" {{ old('has_trading_name', $defaultHasTrading) ? 'checked' : '' }}> Yes</label>
                                        <label><input type="radio" name="has_trading_name" value="0" {{ !old('has_trading_name', $defaultHasTrading) ? 'checked' : '' }}> No</label>
                                    </div>
                                </div>
                                <div id="tradingNamesContainer" class="form-group full-width" style="{{ !old('has_trading_name', $defaultHasTrading) ? 'display:none;' : '' }}">
                                    <label>Trading Names</label>
                                    <div id="tradingNamesList">
                                        @php
                                            $tradingNames = ($company && ($company->tradingNames?->isNotEmpty() ?? false)) ? $company->tradingNames : collect();
                                            if ($tradingNames->isEmpty() && $company && $company->trading_name) {
                                                $tradingNames = collect([(object)['trading_name' => $company->trading_name, 'is_primary' => true]]);
                                            }
                                            if ($tradingNames->isEmpty()) { $tradingNames = collect([(object)['trading_name' => '', 'is_primary' => false]]); }
                                        @endphp
                                        @foreach($tradingNames as $idx => $tn)
                                        <div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                                            <input type="text" name="trading_names[]" value="{{ old("trading_names.{$idx}", is_object($tn) ? $tn->trading_name : $tn) }}" placeholder="Trading name" style="flex: 1;">
                                            <label><input type="radio" name="trading_name_primary" value="{{ $idx }}" {{ ($tn->is_primary ?? ($idx === 0)) ? 'checked' : '' }}> Primary</label>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTradingName(this)">@icon('fa-times')</button>
                                        </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addTradingName()">@icon('fa-plus') Add another</button>
                                </div>
                                
                                <div class="form-group">
                                    <label for="abn">ABN</label>
                                    <input type="text" id="abn" name="ABN_number" 
                                           value="{{ old('ABN_number', $company ? $company->ABN_number : '') }}" 
                                           placeholder="12 345 678 901"
                                           maxlength="11">
                                    <small class="form-text text-muted">11 digits (spaces will be removed automatically)</small>
                                    @error('ABN_number')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label for="acn">ACN</label>
                                    <input type="text" id="acn" name="ACN" 
                                           value="{{ old('ACN', $company ? $company->ACN : '') }}" 
                                           placeholder="123 456 789"
                                           maxlength="9">
                                    <small class="form-text text-muted">9 digits (spaces will be removed automatically)</small>
                                    @error('ACN')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label for="companyType">Business Type</label>
                                    <select id="companyType" name="company_type">
                                        <option value="">Select Business Type</option>
                                        <option value="Sole Trader" {{ old('company_type', $company ? $company->company_type : '') == 'Sole Trader' ? 'selected' : '' }}>Sole Trader</option>
                                        <option value="Partnership" {{ old('company_type', $company ? $company->company_type : '') == 'Partnership' ? 'selected' : '' }}>Partnership</option>
                                        <option value="Proprietary Company" {{ old('company_type', $company ? $company->company_type : '') == 'Proprietary Company' ? 'selected' : '' }}>Proprietary Company (Pty Ltd)</option>
                                        <option value="Public Company" {{ old('company_type', $company ? $company->company_type : '') == 'Public Company' ? 'selected' : '' }}>Public Company</option>
                                        <option value="Not-for-Profit" {{ old('company_type', $company ? $company->company_type : '') == 'Not-for-Profit' ? 'selected' : '' }}>Not-for-Profit Organization</option>
                                        <option value="Trustee" {{ \App\Models\Company::isTrusteeBusinessType(old('company_type', $company ? $company->company_type : '')) ? 'selected' : '' }}>Trustee</option>
                                        <option value="Other" {{ old('company_type', $company ? $company->company_type : '') == 'Other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>

                                <div id="trusteeInlineFields" class="trustee-inline-fields" style="grid-column: 1 / -1; {{ $showTrusteeFieldsInitial ? '' : 'display: none;' }}">
                                    <div class="content-grid">
                                        <div class="form-group">
                                            <label for="companyTrustName">Trust Name</label>
                                            <input type="text" id="companyTrustName" name="trust_name"
                                                   value="{{ old('trust_name', $company ? $company->trust_name : '') }}"
                                                   placeholder="Name of the trust"
                                                   @if(!$showTrusteeFieldsInitial) disabled @endif>
                                        </div>
                                        <div class="form-group">
                                            <label for="companyTrustAbnAcn">ABN/ACN</label>
                                            <input type="text" id="companyTrustAbnAcn" name="trust_abn"
                                                   value="{{ old('trust_abn', $company ? $company->trust_abn : '') }}"
                                                   placeholder="Trust ABN or ACN"
                                                   maxlength="64"
                                                   @if(!$showTrusteeFieldsInitial) disabled @endif>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="companyWebsite">Company Website</label>
                                    <input type="url" id="companyWebsite" name="company_website" 
                                           value="{{ old('company_website', $company ? $company->company_website : '') }}" 
                                           placeholder="https://www.example.com">
                                    @error('company_website')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveCompanyInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('companyInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Primary Contact Person Section -->
                <section id="contactPersonSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-user-tie') Primary Contact Person</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('contactPersonInfo')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        
                        <!-- Summary View -->
                        <div id="contactPersonInfoSummary" class="summary-view">
                            @if($contactPerson)
                                <div class="summary-grid">
                                    <div class="summary-item">
                                        <span class="summary-label">Name:</span>
                                        <span class="summary-value">
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($contactPerson->id))) }}">
                                                {{ $contactPerson->first_name }} {{ $contactPerson->last_name }}
                                            </a>
                                        </span>
                                    </div>
                                    @if($company && $company->contact_person_position)
                                    <div class="summary-item">
                                        <span class="summary-label">Position:</span>
                                        <span class="summary-value">{{ $company->contact_person_position }}</span>
                                    </div>
                                    @endif
                                    @if($contactPerson->email)
                                    <div class="summary-item">
                                        <span class="summary-label">Email:</span>
                                        <span class="summary-value">
                                            <a href="mailto:{{ $contactPerson->email }}">{{ $contactPerson->email }}</a>
                                        </span>
                                    </div>
                                    @endif
                                    @if($contactPerson->phone)
                                    <div class="summary-item">
                                        <span class="summary-label">Phone:</span>
                                        <span class="summary-value">{{ $contactPerson->phone }}</span>
                                    </div>
                                    @endif
                                    @if($contactPerson->client_id)
                                    <div class="summary-item">
                                        <span class="summary-label">Client ID:</span>
                                        <span class="summary-value">{{ $contactPerson->client_id }}</span>
                                    </div>
                                    @endif
                                </div>
                            @else
                                <div class="empty-state">
                                    <p>No contact person assigned yet.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Edit View -->
                        <div id="contactPersonInfoEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group full-width">
                                    <label for="contactPersonSearch">Search Contact Person <span class="text-danger">*</span></label>
                                    <select id="contactPersonSearch" name="contact_person_id" 
                                            class="form-control mm-select-contact-person" 
                                            data-placeholder="Type phone, email, name, or client ID to search..."
                                            style="width: 100%;">
                                        @if($contactPerson)
                                            <option value="{{ $contactPerson->id }}" selected>
                                                {{ $contactPerson->first_name }} {{ $contactPerson->last_name }} 
                                                ({{ $contactPerson->email }}) - {{ $contactPerson->phone }}
                                            </option>
                                        @endif
                                    </select>
                                    <small class="form-text text-muted">
                                        Search existing clients/leads by phone, email, name, or client ID. Selected person's details will auto-fill below.
                                    </small>
                                    @error('contact_person_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonFirstName">First Name</label>
                                    <input type="text" id="contactPersonFirstName" name="contact_person_first_name" 
                                           value="{{ old('contact_person_first_name', $contactPerson ? $contactPerson->first_name : '') }}" 
                                           class="contact-person-field" readonly>
                                    <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonLastName">Last Name</label>
                                    <input type="text" id="contactPersonLastName" name="contact_person_last_name" 
                                           value="{{ old('contact_person_last_name', $contactPerson ? $contactPerson->last_name : '') }}" 
                                           class="contact-person-field" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonPosition">Position/Title</label>
                                    <input type="text" id="contactPersonPosition" name="contact_person_position" 
                                           value="{{ old('contact_person_position', $company ? $company->contact_person_position : '') }}" 
                                           placeholder="e.g., HR Manager, Director">
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonPhone">Phone</label>
                                    <input type="text" id="contactPersonPhone" name="contact_person_phone" 
                                           value="{{ old('contact_person_phone', $contactPerson ? $contactPerson->phone : '') }}" 
                                           class="contact-person-field" readonly>
                                    <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contactPersonEmailDisplay">Email</label>
                                    <input type="email" id="contactPersonEmailDisplay" 
                                           value="{{ old('contact_person_email_display', $contactPerson ? $contactPerson->email : '') }}" 
                                           class="contact-person-field" readonly>
                                    <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                </div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveContactPersonInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('contactPersonInfo')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Sponsorship Section -->
                <section id="sponsorshipSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-file-contract') Sponsorship</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('sponsorship')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addSponsorshipRow()" title="Add Sponsorship">@icon('fa-plus')</button>
                            </div>
                        </div>
                        <div id="sponsorshipSummary" class="summary-view">
                            @if($company && $company->sponsorships->isNotEmpty())
                            <div class="summary-grid">
                                @foreach($company->sponsorships as $s)
                                <div class="summary-item full-width" style="border-bottom:1px solid #e9ecef;padding-bottom:12px;margin-bottom:12px;">
                                    @if($company->sponsorships->count() > 1)
                                    <p style="margin:0 0 8px 0;font-weight:600;color:#495057;">Sponsorship {{ $loop->iteration }}</p>
                                    @endif
                                    <div class="summary-grid">
                                        @if($s->sponsorship_type)<div class="summary-item"><span class="summary-label">Type:</span><span class="summary-value">{{ $s->sponsorship_type }}</span></div>@endif
                                        @if($s->sponsorship_status)<div class="summary-item"><span class="summary-label">Status:</span><span class="summary-value">{{ $s->sponsorship_status }}</span></div>@endif
                                        @if($s->trn)<div class="summary-item"><span class="summary-label">TRN:</span><span class="summary-value">{{ $s->trn }}</span></div>@endif
                                        @if($s->sponsorship_start_date)<div class="summary-item"><span class="summary-label">Start:</span><span class="summary-value">{{ $s->sponsorship_start_date->format('d/m/Y') }}</span></div>@endif
                                        @if($s->sponsorship_end_date)<div class="summary-item"><span class="summary-label">End:</span><span class="summary-value">{{ $s->sponsorship_end_date->format('d/m/Y') }}</span></div>@endif
                                        @if($s->regional_sponsorship)<div class="summary-item"><span class="summary-label">Regional:</span><span class="summary-value">Yes</span></div>@endif
                                        @if($s->adverse_information)<div class="summary-item"><span class="summary-label">Adverse information:</span><span class="summary-value">Yes</span></div>@endif
                                        @if($s->previous_sponsorship_notes)<div class="summary-item full-width"><span class="summary-label">Notes:</span><span class="summary-value">{{ $s->previous_sponsorship_notes }}</span></div>@endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @elseif($company && ($company->sponsorship_type || $company->sponsorship_status || $company->trn))
                            <div class="summary-grid">
                                @if($company->sponsorship_type)<div class="summary-item"><span class="summary-label">Type:</span><span class="summary-value">{{ $company->sponsorship_type }}</span></div>@endif
                                @if($company->sponsorship_status)<div class="summary-item"><span class="summary-label">Status:</span><span class="summary-value">{{ $company->sponsorship_status }}</span></div>@endif
                                @if($company->trn)<div class="summary-item"><span class="summary-label">TRN:</span><span class="summary-value">{{ $company->trn }}</span></div>@endif
                                @if($company->sponsorship_start_date)<div class="summary-item"><span class="summary-label">Start:</span><span class="summary-value">{{ $company->sponsorship_start_date?->format('d/m/Y') }}</span></div>@endif
                                @if($company->sponsorship_end_date)<div class="summary-item"><span class="summary-label">End:</span><span class="summary-value">{{ $company->sponsorship_end_date?->format('d/m/Y') }}</span></div>@endif
                            </div>
                            @else
                            <div class="empty-state"><p>No sponsorship details added yet.</p></div>
                            @endif
                        </div>
                        <div id="sponsorshipEdit" class="edit-view hidden">
                            <div id="sponsorshipsContainer">
                                @php
                                    if ($company && $company->sponsorships->isNotEmpty()) {
                                        $sponsorshipRows = $company->sponsorships;
                                    } else {
                                        $sponsorshipRows = collect([(object) [
                                            'sponsorship_type' => optional($company)->sponsorship_type,
                                            'sponsorship_status' => optional($company)->sponsorship_status,
                                            'trn' => optional($company)->trn,
                                            'sponsorship_start_date' => optional($company)->sponsorship_start_date,
                                            'sponsorship_end_date' => optional($company)->sponsorship_end_date,
                                            'regional_sponsorship' => optional($company)->regional_sponsorship,
                                            'adverse_information' => optional($company)->adverse_information,
                                            'previous_sponsorship_notes' => optional($company)->previous_sponsorship_notes,
                                        ]]);
                                    }
                                @endphp
                                @php $sponsorshipTotal = $sponsorshipRows->count(); @endphp
                                @foreach($sponsorshipRows->values() as $idx => $s)
                                <div class="sponsorship-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <p class="sponsorship-row-label" style="margin:0 0 10px 0;font-weight:600;color:#495057;{{ $sponsorshipTotal > 1 ? '' : 'display:none;' }}">Sponsorship {{ $idx + 1 }}</p>
                                    <div class="content-grid">
                                        <div class="form-group"><label>Sponsorship Type</label><input type="text" name="sponsorship_types[]" value="{{ $s->sponsorship_type ?? '' }}" placeholder="e.g. 482, 494"></div>
                                        <div class="form-group"><label>Status</label><input type="text" name="sponsorship_statuses[]" value="{{ $s->sponsorship_status ?? '' }}" placeholder="Status"></div>
                                        <div class="form-group"><label>TRN</label><input type="text" name="sponsorship_trns[]" value="{{ $s->trn ?? '' }}" placeholder="Transaction Reference Number"></div>
                                        <div class="form-group"><label>Start Date</label><input type="date" name="sponsorship_start_dates[]" value="{{ ($s->sponsorship_start_date ?? null) ? \Carbon\Carbon::parse($s->sponsorship_start_date)->format('Y-m-d') : '' }}"></div>
                                        <div class="form-group"><label>End Date</label><input type="date" name="sponsorship_end_dates[]" value="{{ ($s->sponsorship_end_date ?? null) ? \Carbon\Carbon::parse($s->sponsorship_end_date)->format('Y-m-d') : '' }}"></div>
                                        <div class="form-group"><label><input type="checkbox" class="sponsorship-regional-cb" name="sponsorship_regional[{{ $idx }}]" value="1" {{ !empty($s->regional_sponsorship) ? 'checked' : '' }}> Regional Sponsorship</label></div>
                                        <div class="form-group"><label><input type="checkbox" class="sponsorship-adverse-cb" name="sponsorship_adverse[{{ $idx }}]" value="1" {{ !empty($s->adverse_information) ? 'checked' : '' }}> Adverse Information</label></div>
                                        <div class="form-group full-width"><label>Previous Sponsorship Notes</label><textarea name="sponsorship_previous_notes[]" rows="2">{{ $s->previous_sponsorship_notes ?? '' }}</textarea></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSponsorshipRow(this)">@icon('fa-times') Remove</button>
                                </div>
                                @endforeach
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveSponsorshipInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('sponsorship')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Directors Section -->
                <section id="directorsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-users-cog') Directors</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('directors')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addDirectorRow()" title="Add Director">@icon('fa-plus')</button>
                            </div>
                        </div>
                        <div id="directorsSummary" class="summary-view">
                            @if($company && $company->directors->isNotEmpty())
                            <div class="summary-grid">
                                @foreach($company->directors as $dir)
                                <div class="summary-item"><span class="summary-label">{{ $dir->directorClient ? ($dir->directorClient->first_name.' '.$dir->directorClient->last_name) : ($dir->director_name ?? '') }}</span><span class="summary-value">{{ $dir->director_role ?? '' }}@if($dir->director_dob) (DOB: {{ $dir->director_dob->format('d/m/Y') }})@endif</span></div>
                                @endforeach
                            </div>
                            @else
                            <div class="empty-state"><p>No directors added yet.</p></div>
                            @endif
                        </div>
                        <div id="directorsEdit" class="edit-view hidden">
                            <div class="form-group" style="margin-bottom:15px;">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addContactPersonAsDirector()">
                                    @icon('fa-user-plus') Add contact person as director
                                </button>
                                <small class="form-text text-muted">Quick-add the selected contact person (from Contact Person section) to the directors list.</small>
                            </div>
                            <div id="directorsContainer">
                                @php
                                    $directorsData = (optional($company)->directors?->isNotEmpty()) ? $company->directors : collect([(object)['id'=>null,'director_client_id'=>null,'directorClient'=>null,'director_name'=>'','director_dob'=>null,'director_role'=>'','is_primary'=>true]]);
                                @endphp
                                @foreach($directorsData as $idx => $dir)
                                <div class="director-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <input type="hidden" name="director_ids[]" value="{{ $dir->id ?? '' }}">
                                    <div class="form-group" style="margin-bottom:10px;">
                                        <label>Director</label>
                                        <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
                                            <div style="flex:1;min-width:200px;">
                                                <select name="director_client_ids[]" class="director-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;">
                                                    @if($dir->director_client_id && ($dir->directorClient ?? null))
                                                    <option value="{{ $dir->director_client_id }}" selected>{{ $dir->directorClient->first_name }} {{ $dir->directorClient->last_name }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                            <span>OR</span>
                                            <div style="flex:1;min-width:200px;">
                                                <input type="text" name="director_names[]" value="{{ $dir->director_name ?? '' }}" placeholder="Not in system - enter name">
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                        <input type="date" name="director_dobs[]" value="{{ isset($dir->director_dob) && $dir->director_dob ? $dir->director_dob->format('Y-m-d') : '' }}" placeholder="DOB" title="DOB">
                                        <input type="text" name="director_roles[]" value="{{ $dir->director_role ?? '' }}" placeholder="Role" style="width:120px;">
                                        <label><input type="radio" name="director_primary" value="{{ $idx }}" {{ ($dir->is_primary ?? ($idx===0)) ? 'checked' : '' }}> Primary</label>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDirectorRow(this)">@icon('fa-times') Remove</button>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" class="add-item-btn" onclick="addDirectorRow()">@icon('fa-plus-circle') Add Director</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveDirectorsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('directors')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Financial Section -->
                @php
                    $financialSummaryRows = collect();
                    if ($company) {
                        if ($company->relationLoaded('financials') && $company->financials->isNotEmpty()) {
                            $financialSummaryRows = $company->financials;
                        } elseif (($company->annual_turnover ?? null) !== null || ($company->wages_expenditure ?? null) !== null) {
                            $financialSummaryRows = collect([(object) [
                                'financial_year' => null,
                                'annual_turnover' => $company->annual_turnover,
                                'wages_expenditure' => $company->wages_expenditure,
                            ]]);
                        }
                    }
                    $financialEditRows = collect();
                    if ($company) {
                        if ($company->relationLoaded('financials') && $company->financials->isNotEmpty()) {
                            $financialEditRows = $company->financials;
                        } elseif (($company->annual_turnover ?? null) !== null || ($company->wages_expenditure ?? null) !== null) {
                            $financialEditRows = collect([(object) [
                                'financial_year' => '',
                                'annual_turnover' => $company->annual_turnover,
                                'wages_expenditure' => $company->wages_expenditure,
                            ]]);
                        }
                    }
                    if ($financialEditRows->isEmpty()) {
                        $financialEditRows = collect([(object) [
                            'financial_year' => '',
                            'annual_turnover' => null,
                            'wages_expenditure' => null,
                        ]]);
                    }
                @endphp
                <section id="financialSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-dollar-sign') Financial</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('financial')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        <div id="financialSummary" class="summary-view">
                            <div class="summary-grid">
                                @forelse($financialSummaryRows as $fin)
                                <div class="summary-block financial-summary-row" style="grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: baseline; column-gap: 1.5rem; row-gap: 0.35rem; border: 1px solid #e9ecef; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px;">
                                    @if(!empty($fin->financial_year))
                                    <div class="summary-item" style="display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem; margin: 0;"><span class="summary-label">Financial Year:</span><span class="summary-value">{{ $fin->financial_year }}</span></div>
                                    @endif
                                    @if($fin->annual_turnover !== null && $fin->annual_turnover !== '')
                                    <div class="summary-item" style="display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem; margin: 0;"><span class="summary-label">Annual Turnover:</span><span class="summary-value">${{ number_format((float) $fin->annual_turnover, 2) }}</span></div>
                                    @endif
                                    @if($fin->wages_expenditure !== null && $fin->wages_expenditure !== '')
                                    <div class="summary-item" style="display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem; margin: 0;"><span class="summary-label">Wages Expenditure:</span><span class="summary-value">${{ number_format((float) $fin->wages_expenditure, 2) }}</span></div>
                                    @endif
                                </div>
                                @empty
                                <div class="empty-state"><p>No financial details added yet.</p></div>
                                @endforelse
                            </div>
                        </div>
                        <div id="financialEdit" class="edit-view hidden">
                            <div id="financialRowsContainer">
                                @foreach($financialEditRows as $idx => $fin)
                                <div class="financial-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <div class="content-grid">
                                        <div class="form-group"><label>Financial Year</label><input type="text" name="financial_year[]" value="{{ old('financial_year.'.$idx, $fin->financial_year ?? '') }}" placeholder="e.g. 2024–25" maxlength="64"></div>
                                        <div class="form-group"><label>Annual Turnover</label><input type="number" name="financial_annual_turnover[]" value="{{ $fin->annual_turnover !== null && $fin->annual_turnover !== '' ? $fin->annual_turnover : '' }}" placeholder="0" step="0.01" min="0"></div>
                                        <div class="form-group"><label>Wages Expenditure</label><input type="number" name="financial_wages_expenditure[]" value="{{ $fin->wages_expenditure !== null && $fin->wages_expenditure !== '' ? $fin->wages_expenditure : '' }}" placeholder="0" step="0.01" min="0"></div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFinancialRow(this)">@icon('fa-times') Remove</button>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" class="add-item-btn" onclick="addFinancialRow()">@icon('fa-plus-circle') Add financial year</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveFinancialInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('financial')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Workforce Section -->
                <section id="workforceSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-users') Workforce</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('workforce')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        <div id="workforceSummary" class="summary-view">
                            <div class="summary-grid">
                                @php $hasWorkforce = $company && ($company->workforce_australian_citizens !== null || $company->workforce_permanent_residents !== null || $company->workforce_temp_visa_holders !== null || $company->workforce_total !== null || $company->workforce_foreign_494 !== null || $company->workforce_foreign_other_temp_activity !== null || $company->workforce_foreign_overseas_students !== null || $company->workforce_foreign_working_holiday !== null || $company->workforce_foreign_other !== null); @endphp
                                @if($hasWorkforce)
                                @if($company->workforce_australian_citizens !== null)<div class="summary-item"><span class="summary-label">workforce_aus_professionals:</span><span class="summary-value">{{ $company->workforce_australian_citizens }}</span></div>@endif
                                @if($company->workforce_permanent_residents !== null)<div class="summary-item"><span class="summary-label">workforce_aus_tradespersons:</span><span class="summary-value">{{ $company->workforce_permanent_residents }}</span></div>@endif
                                @if($company->workforce_temp_visa_holders !== null)<div class="summary-item"><span class="summary-label">workforce_aus_employment_other:</span><span class="summary-value">{{ $company->workforce_temp_visa_holders }}</span></div>@endif
                                @if($company->workforce_total !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_482_457:</span><span class="summary-value">{{ $company->workforce_total }}</span></div>@endif
                                @if($company->workforce_foreign_494 !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_494:</span><span class="summary-value">{{ $company->workforce_foreign_494 }}</span></div>@endif
                                @if($company->workforce_foreign_other_temp_activity !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_other_temp_activity:</span><span class="summary-value">{{ $company->workforce_foreign_other_temp_activity }}</span></div>@endif
                                @if($company->workforce_foreign_overseas_students !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_overseas_students:</span><span class="summary-value">{{ $company->workforce_foreign_overseas_students }}</span></div>@endif
                                @if($company->workforce_foreign_working_holiday !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_working_holiday:</span><span class="summary-value">{{ $company->workforce_foreign_working_holiday }}</span></div>@endif
                                @if($company->workforce_foreign_other !== null)<div class="summary-item"><span class="summary-label">workforce_foreign_other:</span><span class="summary-value">{{ $company->workforce_foreign_other }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No workforce details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="workforceEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>workforce_aus_professionals</label><input type="number" name="workforce_australian_citizens" value="{{ optional($company)->workforce_australian_citizens ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_aus_tradespersons</label><input type="number" name="workforce_permanent_residents" value="{{ optional($company)->workforce_permanent_residents ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_aus_employment_other</label><input type="number" name="workforce_temp_visa_holders" value="{{ optional($company)->workforce_temp_visa_holders ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_482_457</label><input type="number" name="workforce_total" value="{{ optional($company)->workforce_total ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_494</label><input type="number" name="workforce_foreign_494" value="{{ optional($company)->workforce_foreign_494 ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_other_temp_activity</label><input type="number" name="workforce_foreign_other_temp_activity" value="{{ optional($company)->workforce_foreign_other_temp_activity ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_overseas_students</label><input type="number" name="workforce_foreign_overseas_students" value="{{ optional($company)->workforce_foreign_overseas_students ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_working_holiday</label><input type="number" name="workforce_foreign_working_holiday" value="{{ optional($company)->workforce_foreign_working_holiday ?? '' }}" min="0"></div>
                                <div class="form-group"><label>workforce_foreign_other</label><input type="number" name="workforce_foreign_other" value="{{ optional($company)->workforce_foreign_other ?? '' }}" min="0"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveWorkforceInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('workforce')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Operations Section -->
                <section id="operationsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-briefcase') Operations</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('operations')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        <div id="operationsSummary" class="summary-view">
                            <div class="summary-grid">
                                @if($company && ($company->business_operating_since || $company->main_business_activity))
                                @if($company->business_operating_since)<div class="summary-item"><span class="summary-label">Operating Since:</span><span class="summary-value">{{ $company->business_operating_since->format('d/m/Y') }}</span></div>@endif
                                @if($company->main_business_activity)<div class="summary-item full-width"><span class="summary-label">Main Activity:</span><span class="summary-value">{{ $company->main_business_activity }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No operations details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="operationsEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>Business Operating Since</label><input type="date" name="business_operating_since" value="{{ optional($company)->business_operating_since?->format('Y-m-d') ?? '' }}"></div>
                                <div class="form-group full-width"><label>Main Business Activity</label><input type="text" name="main_business_activity" value="{{ optional($company)->main_business_activity ?? '' }}" placeholder="Primary business activity"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveOperationsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('operations')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Training Section -->
                <section id="trainingSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-graduation-cap') Training</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('training')">
                                    @icon('fa-pen')
                                </button>
                            </div>
                        </div>
                        <div id="trainingSummary" class="summary-view">
                            <div class="summary-grid">
                                @if($company && ($company->training_position_title || $company->trainer_name))
                                @if($company->training_position_title)<div class="summary-item"><span class="summary-label">Position Title:</span><span class="summary-value">{{ $company->training_position_title }}</span></div>@endif
                                @if($company->trainer_name)<div class="summary-item"><span class="summary-label">Trainer Name:</span><span class="summary-value">{{ $company->trainer_name }}</span></div>@endif
                                @else
                                <div class="empty-state"><p>No training details added yet.</p></div>
                                @endif
                            </div>
                        </div>
                        <div id="trainingEdit" class="edit-view hidden">
                            <div class="content-grid">
                                <div class="form-group"><label>Training Position Title</label><input type="text" name="training_position_title" value="{{ optional($company)->training_position_title ?? '' }}" placeholder="Position title"></div>
                                <div class="form-group"><label>Trainer Name</label><input type="text" name="trainer_name" value="{{ optional($company)->trainer_name ?? '' }}" placeholder="Trainer name"></div>
                            </div>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveTrainingInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('training')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Nominations Section -->
                <section id="nominationsSection" class="content-section">
                    <section class="form-section">
                        <div class="section-header">
                            <h3>@icon('fa-user-check') Nominations</h3>
                            <div class="section-actions">
                                <button type="button" class="edit-section-btn" onclick="toggleEditMode('nominations')">
                                    @icon('fa-pen')
                                </button>
                                <button type="button" class="add-section-btn" onclick="addNominationRow()" title="Add Nomination">@icon('fa-plus')</button>
                            </div>
                        </div>
                        <div id="nominationsSummary" class="summary-view">
                            @if($company && $company->nominations->isNotEmpty())
                            <div class="summary-grid">
                                @foreach($company->nominations as $nom)
                                <div class="summary-item"><span class="summary-label">{{ $nom->position_title ?? 'Position' }}:</span><span class="summary-value">{{ $nom->nominatedClient ? $nom->nominatedClient->first_name.' '.$nom->nominatedClient->last_name : ($nom->nominated_person_name ?? 'N/A') }}@if($nom->trn) (TRN: {{ $nom->trn }})@endif</span></div>
                                @endforeach
                            </div>
                            @else
                            <div class="empty-state"><p>No nominations added yet.</p></div>
                            @endif
                        </div>
                        <div id="nominationsEdit" class="edit-view hidden">
                            <div id="nominationsContainer">
                                @php
                                    $nominationsData = (optional($company)->nominations?->isNotEmpty()) ? $company->nominations : collect([(object)['id'=>null,'position_title'=>'','anzsco_code'=>'','position_description'=>'','salary'=>null,'duration'=>'','nominated_client_id'=>null,'nominated_person_name'=>'','trn'=>'','status'=>'','nomination_date'=>null,'expiry_date'=>null]]);
                                @endphp
                                @foreach($nominationsData as $idx => $nom)
                                <div class="nomination-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">
                                    <input type="hidden" name="nomination_ids[]" value="{{ $nom->id ?? '' }}">
                                    <div class="content-grid" style="margin-bottom:10px;">
                                        <div class="form-group"><label>Position Title</label><input type="text" name="nomination_position_titles[]" value="{{ $nom->position_title ?? '' }}" placeholder="Position title"></div>
                                        <div class="form-group"><label>ANZSCO Code</label><input type="text" name="nomination_anzsco_codes[]" value="{{ $nom->anzsco_code ?? '' }}" placeholder="e.g. 261312"></div>
                                        <div class="form-group full-width"><label>Description</label><textarea name="nomination_descriptions[]" rows="2" placeholder="Position description">{{ $nom->position_description ?? '' }}</textarea></div>
                                        <div class="form-group"><label>Salary</label><input type="number" name="nomination_salaries[]" value="{{ $nom->salary ?? '' }}" step="0.01" placeholder="0"></div>
                                        <div class="form-group"><label>Duration</label><input type="text" name="nomination_durations[]" value="{{ $nom->duration ?? '' }}" placeholder="e.g. 2 years"></div>
                                    </div>
                                    <div class="form-group" style="margin-bottom:10px;">
                                        <label>Nominated Person (Visa Applicant)</label>
                                        <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">
                                            <div style="flex:1;min-width:200px;">
                                                <select name="nomination_nominated_client_ids[]" class="nomination-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;">
                                                    @if($nom->nominated_client_id && $nom->nominatedClient)
                                                    <option value="{{ $nom->nominated_client_id }}" selected>{{ $nom->nominatedClient->first_name }} {{ $nom->nominatedClient->last_name }}</option>
                                                    @endif
                                                </select>
                                            </div>
                                            <span>OR</span>
                                            <div style="flex:1;min-width:200px;">
                                                <input type="text" name="nomination_person_names[]" value="{{ $nom->nominated_person_name ?? '' }}" placeholder="Not in system - enter name only">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="content-grid">
                                        <div class="form-group"><label>TRN</label><input type="text" name="nomination_trns[]" value="{{ $nom->trn ?? '' }}" placeholder="TRN"></div>
                                        <div class="form-group"><label>Status</label><input type="text" name="nomination_statuses[]" value="{{ $nom->status ?? '' }}" placeholder="Status"></div>
                                        <div class="form-group"><label>Nomination Date</label><input type="date" name="nomination_dates[]" value="{{ $nom->nomination_date?->format('Y-m-d') ?? '' }}"></div>
                                        <div class="form-group"><label>Expiry Date</label><input type="date" name="nomination_expiries[]" value="{{ $nom->expiry_date?->format('Y-m-d') ?? '' }}"></div>
                                        <div class="form-group" style="align-self:end;"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNominationRow(this)">@icon('fa-times') Remove</button></div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <button type="button" class="add-item-btn" onclick="addNominationRow()">@icon('fa-plus-circle') Add Nomination</button>
                            <div class="edit-actions">
                                <button type="button" class="btn btn-primary" onclick="saveNominationsInfo()">Save</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit('nominations')">Cancel</button>
                            </div>
                        </div>
                    </section>
                </section>

                <!-- Business Address Section -->
                <section id="addressSection" class="content-section">
                    <x-client-edit.address-section 
                        :clientAddresses="$clientAddresses"
                        :searchRoute="route('clients.searchAddressFull')"
                        :detailsRoute="route('clients.getPlaceDetails')"
                        :csrfToken="csrf_token()"
                    />
                </section>

                <!-- Contacts Section (Phone & Email) -->
                <section id="contactsSection" class="content-section">
                    <!-- Phone Numbers -->
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
                                            @if($contact->canVerify())
                                                @if($contact->is_verified)
                                                    <span class="verified-badge" title="Verified on {{ $contact->verified_at ? $contact->verified_at->format('M j, Y g:i A') : 'Unknown' }}">
                                                        @icon('fa-check-circle') Verified
                                                    </span>
                                                @else
                                                    <button type="button" class="btn-verify-phone" onclick="sendOTP({{ $contact->id ?? 'null' }}, '{{ $contact->phone }}', '{{ $contact->country_code }}')" data-contact-id="{{ $contact->id ?? '' }}">
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

                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    {{-- Tom Select (mmSelect) for contact person search --}}
    <script>
        window.countriesData = @json($countries ?? []);
        window.phonePopularIsoCodes = @json(config('phone.popular_countries'));
        window.phoneDefaultDialCode = @json(config('phone.default_country_code'));
    </script>
    <script src="{{ asset('js/shared/phone-country-select-builder.js') }}"></script>
    <script src="{{asset('js/clients/edit-client.js')}}"></script>
    <script src="{{asset('js/address-autocomplete.js')}}"></script>
    <script src="{{asset('js/clients/address-regional-codes.js')}}"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize mmSelect for contact person search
        $('#contactPersonSearch').mmSelect({
            ajax: {
                url: window.editClientConfig.searchContactPersonRoute,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term, // search term
                        exclude_id: window.currentClientId // Exclude current company being edited
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results.map(function(item) {
                            return {
                                id: item.id,
                                text: item.text,
                                first_name: item.first_name,
                                last_name: item.last_name,
                                email: item.email,
                                phone: item.phone,
                                client_id: item.client_id
                            };
                        })
                    };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: 'Type phone, email, name, or client ID to search...',
            allowClear: true
        });
        
        // Auto-fill contact person details when selected
        $('#contactPersonSearch').on('mmselect:select', function (e) {
            const data = e.params.data;
            
            // Auto-fill fields
            $('#contactPersonFirstName').val(data.first_name);
            $('#contactPersonLastName').val(data.last_name);
            $('#contactPersonPhone').val(data.phone || '');
            $('#contactPersonEmailDisplay').val(data.email);
            
            // Add visual indicator
            $('.contact-person-field').addClass('field-auto-filled');
        });
        
        // Clear fields when selection is cleared
        $('#contactPersonSearch').on('mmselect:clear', function (e) {
            $('#contactPersonFirstName').val('');
            $('#contactPersonLastName').val('');
            $('#contactPersonPhone').val('');
            $('#contactPersonEmailDisplay').val('');
            $('.contact-person-field').removeClass('field-auto-filled');
        });
        
        // Format ABN/ACN input (strip non-digits)
        $('#abn, #acn').on('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
        // Has trading name toggle
        $('input[name="has_trading_name"]').on('change', function() {
            $('#tradingNamesContainer').toggle($(this).val() === '1');
        });

        function toggleTrusteeCompanyFields() {
            var v = $('#companyType').val();
            var show = (v === 'Trustee' || v === 'Trust');
            var el = $('#trusteeInlineFields');
            el.toggle(show);
            el.find('input').prop('disabled', !show);
        }
        $('#companyType').on('change', toggleTrusteeCompanyFields);
        toggleTrusteeCompanyFields();

        // Init mmSelect for nomination person search (existing rows)
        $('.nomination-person-select').each(function() {
            if (!$(this).hasClass('mm-select-initialized')) {
                $(this).mmSelect({
                    ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                    minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
                });
            }
        });
        // Init mmSelect for director person search (existing rows)
        $('.director-person-select').each(function() {
            if (!$(this).hasClass('mm-select-initialized')) {
                $(this).mmSelect({
                    ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                    minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
                });
            }
        });
    });

    function addTradingName() {
        const container = $('#tradingNamesList');
        const idx = container.find('.trading-name-row').length;
        const row = $('<div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">' +
            '<input type="text" name="trading_names[]" placeholder="Trading name" style="flex: 1;">' +
            '<label><input type="radio" name="trading_name_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTradingName(this)">' + crmI('fas fa-times') + '</button>' +
            '</div>');
        container.append(row);
        // Update primary radio values
        container.find('.trading-name-row').each(function(i) {
            $(this).find('input[name="trading_name_primary"]').val(i);
        });
    }

    function removeTradingName(btn) {
        const container = $('#tradingNamesList');
        if (container.find('.trading-name-row').length <= 1) return;
        $(btn).closest('.trading-name-row').remove();
        $('#tradingNamesList .trading-name-row').each(function(i) {
            $(this).find('input[name="trading_name_primary"]').val(i);
        });
    }

    function addDirectorRow(prefillClientId, prefillName) {
        const container = $('#directorsContainer');
        const idx = container.find('.director-row').length;
        let selectOpt = '';
        if (prefillClientId && prefillName) {
            const escaped = String(prefillName).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            selectOpt = '<option value="' + String(prefillClientId).replace(/"/g,'&quot;') + '" selected>' + escaped + '</option>';
        }
        const row = '<div class="director-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">' +
            '<input type="hidden" name="director_ids[]" value="">' +
            '<div class="form-group" style="margin-bottom:10px;"><label>Director</label>' +
            '<div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">' +
            '<div style="flex:1;min-width:200px;"><select name="director_client_ids[]" class="director-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;">' + selectOpt + '</select></div>' +
            '<span>OR</span><div style="flex:1;min-width:200px;"><input type="text" name="director_names[]" placeholder="Not in system - enter name"></div></div></div>' +
            '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">' +
            '<input type="date" name="director_dobs[]" placeholder="DOB" title="DOB">' +
            '<input type="text" name="director_roles[]" placeholder="Role" style="width:120px;">' +
            '<label><input type="radio" name="director_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDirectorRow(this)">' + crmI('fas fa-times') + ' Remove</button></div></div>';
        container.append(row);
        container.find('.director-row').each(function(i) { $(this).find('input[name="director_primary"]').val(i); });
        if (typeof $.fn.mmSelect === 'function') {
            container.find('.director-person-select').last().mmSelect({
                ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
            });
        }
    }
    function addContactPersonAsDirector() {
        const clientId = $('#contactPersonSearch').val();
        if (!clientId) {
            if (typeof showNotification === 'function') showNotification('Please select a contact person first (Contact Person section).', 'error');
            else alert('Please select a contact person first (Contact Person section).');
            return;
        }
        const displayName = $('#contactPersonSearch').find('option:selected').text() || '';
        addDirectorRow(clientId, displayName);
        if (typeof showNotification === 'function') showNotification('Contact person added to directors list.', 'success');
    }
    function removeDirectorRow(btn) {
        const container = $('#directorsContainer');
        if (container.find('.director-row').length <= 1) return;
        $(btn).closest('.director-row').remove();
        $('#directorsContainer .director-row').each(function(i) { $(this).find('input[name="director_primary"]').val(i); });
    }

    function addNominationRow() {
        const container = $('#nominationsContainer');
        const row = '<div class="nomination-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">' +
            '<input type="hidden" name="nomination_ids[]" value="">' +
            '<div class="content-grid" style="margin-bottom:10px;">' +
            '<div class="form-group"><label>Position Title</label><input type="text" name="nomination_position_titles[]" placeholder="Position title"></div>' +
            '<div class="form-group"><label>ANZSCO Code</label><input type="text" name="nomination_anzsco_codes[]" placeholder="e.g. 261312"></div>' +
            '<div class="form-group full-width"><label>Description</label><textarea name="nomination_descriptions[]" rows="2" placeholder="Position description"></textarea></div>' +
            '<div class="form-group"><label>Salary</label><input type="number" name="nomination_salaries[]" step="0.01" placeholder="0"></div>' +
            '<div class="form-group"><label>Duration</label><input type="text" name="nomination_durations[]" placeholder="e.g. 2 years"></div></div>' +
            '<div class="form-group" style="margin-bottom:10px;"><label>Nominated Person (Visa Applicant)</label>' +
            '<div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;">' +
            '<div style="flex:1;min-width:200px;"><select name="nomination_nominated_client_ids[]" class="nomination-person-select form-control" data-placeholder="Search client/lead..." style="width:100%;"></select></div>' +
            '<span>OR</span><div style="flex:1;min-width:200px;"><input type="text" name="nomination_person_names[]" placeholder="Not in system - enter name only"></div></div></div>' +
            '<div class="content-grid"><div class="form-group"><label>TRN</label><input type="text" name="nomination_trns[]" placeholder="TRN"></div>' +
            '<div class="form-group"><label>Status</label><input type="text" name="nomination_statuses[]" placeholder="Status"></div>' +
            '<div class="form-group"><label>Nomination Date</label><input type="date" name="nomination_dates[]"></div>' +
            '<div class="form-group"><label>Expiry Date</label><input type="date" name="nomination_expiries[]"></div>' +
            '<div class="form-group" style="align-self:end;"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeNominationRow(this)">' + crmI('fas fa-times') + ' Remove</button></div></div></div>';
        container.append(row);
        if (typeof $.fn.mmSelect === 'function') {
            container.find('.nomination-person-select').last().mmSelect({
                ajax: { url: window.editClientConfig.searchContactPersonRoute, dataType: 'json', delay: 250, data: function(p) { return { q: p.term, exclude_id: window.currentClientId }; }, processResults: function(d) { return { results: d.results || [] }; } },
                minimumInputLength: 2, allowClear: true, placeholder: 'Search client/lead...'
            });
        }
    }
    function removeNominationRow(btn) {
        const container = $('#nominationsContainer');
        if (container.find('.nomination-row').length <= 1) return;
        $(btn).closest('.nomination-row').remove();
    }

    function reindexSponsorshipRows() {
        const rows = $('#sponsorshipsContainer .sponsorship-row');
        const showLabels = rows.length > 1;
        rows.each(function(i) {
            $(this).find('.sponsorship-regional-cb').attr('name', 'sponsorship_regional[' + i + ']');
            $(this).find('.sponsorship-adverse-cb').attr('name', 'sponsorship_adverse[' + i + ']');
            const label = $(this).find('.sponsorship-row-label');
            if (showLabels) {
                label.text('Sponsorship ' + (i + 1)).show();
            } else {
                label.hide();
            }
        });
    }

    function addSponsorshipRow() {
        // Open edit mode if currently showing summary
        const editView = document.getElementById('sponsorshipEdit');
        if (editView && (editView.style.display === 'none' || editView.classList.contains('hidden'))) {
            if (typeof toggleEditMode === 'function') toggleEditMode('sponsorship');
        }
        const container = $('#sponsorshipsContainer');
        const idx = container.find('.sponsorship-row').length;
        const row = '<div class="sponsorship-row repeatable-section" style="border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;">' +
            '<p class="sponsorship-row-label" style="margin:0 0 10px 0;font-weight:600;color:#495057;display:none;"></p>' +
            '<div class="content-grid">' +
            '<div class="form-group"><label>Sponsorship Type</label><input type="text" name="sponsorship_types[]" value="" placeholder="e.g. 482, 494"></div>' +
            '<div class="form-group"><label>Status</label><input type="text" name="sponsorship_statuses[]" value="" placeholder="Status"></div>' +
            '<div class="form-group"><label>TRN</label><input type="text" name="sponsorship_trns[]" value="" placeholder="Transaction Reference Number"></div>' +
            '<div class="form-group"><label>Start Date</label><input type="date" name="sponsorship_start_dates[]" value=""></div>' +
            '<div class="form-group"><label>End Date</label><input type="date" name="sponsorship_end_dates[]" value=""></div>' +
            '<div class="form-group"><label><input type="checkbox" class="sponsorship-regional-cb" name="sponsorship_regional[' + idx + ']" value="1"> Regional Sponsorship</label></div>' +
            '<div class="form-group"><label><input type="checkbox" class="sponsorship-adverse-cb" name="sponsorship_adverse[' + idx + ']" value="1"> Adverse Information</label></div>' +
            '<div class="form-group full-width"><label>Previous Sponsorship Notes</label><textarea name="sponsorship_previous_notes[]" rows="2"></textarea></div>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeSponsorshipRow(this)">' + crmI('fas fa-times') + ' Remove</button>' +
            '</div>';
        container.append(row);
        reindexSponsorshipRows();
    }

    function removeSponsorshipRow(btn) {
        const container = $('#sponsorshipsContainer');
        if (container.find('.sponsorship-row').length <= 1) return;
        $(btn).closest('.sponsorship-row').remove();
        reindexSponsorshipRows();
    }

    function addFinancialRow() {
        const editView = document.getElementById('financialEdit');
        if (editView && (editView.style.display === 'none' || editView.classList.contains('hidden'))) {
            if (typeof toggleEditMode === 'function') toggleEditMode('financial');
        }
        const container = document.getElementById('financialRowsContainer');
        if (!container) return;
        const row = document.createElement('div');
        row.className = 'financial-row repeatable-section';
        row.style.cssText = 'border:1px solid #dee2e6;padding:15px;margin-bottom:15px;border-radius:6px;';
        row.innerHTML = '<div class="content-grid">' +
            '<div class="form-group"><label>Financial Year</label><input type="text" name="financial_year[]" value="" placeholder="e.g. 2024–25" maxlength="64"></div>' +
            '<div class="form-group"><label>Annual Turnover</label><input type="number" name="financial_annual_turnover[]" value="" placeholder="0" step="0.01" min="0"></div>' +
            '<div class="form-group"><label>Wages Expenditure</label><input type="number" name="financial_wages_expenditure[]" value="" placeholder="0" step="0.01" min="0"></div>' +
            '</div>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFinancialRow(this)">' + crmI('fas fa-times') + ' Remove</button>';
        container.appendChild(row);
    }

    function removeFinancialRow(btn) {
        const container = document.getElementById('financialRowsContainer');
        if (!container) return;
        if (container.querySelectorAll('.financial-row').length <= 1) return;
        btn.closest('.financial-row').remove();
    }

    function saveSection(sectionName, callback) {
        const form = document.getElementById('editCompanyForm');
        const formData = new FormData(form);
        saveSectionData(sectionName, formData, function() { (callback || function(){})(); window.location.reload(); });
    }
    
    // Save functions - use saveSectionData for AJAX save (fixes broken form.submit to clients.update)
    function saveCompanyInfo() {
        const form = document.getElementById('editCompanyForm');
        const formData = new FormData(form);
        saveSectionData('companyInfo', formData, function() {
            toggleEditMode('companyInfo');
            window.location.reload();
        });
    }
    
    function saveContactPersonInfo() {
        const form = document.getElementById('editCompanyForm');
        const formData = new FormData(form);
        saveSectionData('contactPersonInfo', formData, function() {
            toggleEditMode('contactPersonInfo');
            window.location.reload();
        });
    }
    function saveSponsorshipInfo() { saveSection('sponsorship', function() { toggleEditMode('sponsorship'); }); }
    function saveDirectorsInfo() { saveSection('directors', function() { toggleEditMode('directors'); }); }
    function saveFinancialInfo() { saveSection('financial', function() { toggleEditMode('financial'); }); }
    function saveWorkforceInfo() { saveSection('workforce', function() { toggleEditMode('workforce'); }); }
    function saveOperationsInfo() { saveSection('operations', function() { toggleEditMode('operations'); }); }
    function saveTrainingInfo() { saveSection('training', function() { toggleEditMode('training'); }); }
    function saveNominationsInfo() { saveSection('nominations', function() { toggleEditMode('nominations'); }); }
    </script>
    @endpush
@endsection
