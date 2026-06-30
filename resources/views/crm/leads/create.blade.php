@extends('layouts.crm_client_detail_dashboard')

@push('styles')
    <link rel="stylesheet" href="{{asset('css/client-forms.css')}}">
    <link rel="stylesheet" href="{{asset('css/clients/edit-client-components.css')}}">
    <link rel="stylesheet" href="{{asset('css/leads/lead-form.css')}}">
    <style>
        /* Compact Error Display Styles */
        .form-validation-errors {
            margin: 20px 0;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .error-container {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .error-container h4 {
            color: #721c24;
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .error-container ul {
            margin: 0;
            padding-left: 20px;
            list-style-type: disc;
        }
        
        .error-container li {
            color: #721c24;
            font-size: 13px;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .error-container li:last-child {
            margin-bottom: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .form-validation-errors {
                margin: 15px 10px;
            }
            
            .error-container {
                padding: 12px 15px;
            }
            
            .error-container h4 {
                font-size: 13px;
            }
            
            .error-container li {
                font-size: 12px;
            }
        }
        
        /* Company fields styling */
        .company-lead-fields {
            animation: fadeIn 0.3s ease-in;
        }
        
        .personal-lead-fields {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .contact-person-field {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .contact-person-field.field-auto-filled {
            background-color: #e7f3ff;
            border-color: #0d6efd;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .mm-select-contact-person {
            width: 100% !important;
        }
    </style>
@endpush

@section('content')
    <div class="crm-container">
        <div class="main-content">


            <!-- Mobile Sidebar Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                @icon('fa-bars')
            </button>

            <!-- Sidebar Navigation -->
            <div class="sidebar-navigation" id="sidebarNav">
                <div class="nav-header">
                    <h3>@icon('fa-user-plus') Create New Lead</h3>
                </div>
                <nav class="nav-menu">
                    <button class="nav-item active" onclick="scrollToSection('personalSection')">
                        @icon('fa-user-circle')
                        <span>Personal</span>
                    </button>
                </nav>
                
                <!-- Actions in Sidebar -->
                <div class="sidebar-actions">
                    <button class="nav-item back-btn" onclick="window.location.href='{{ route('dashboard') }}'">
                        @icon('fa-arrow-left')
                        <span>Back</span>
                    </button>
                    <button type="submit" form="createLeadForm" class="nav-item save-btn">
                        @icon('fa-save')
                        <span>Save Lead</span>
                    </button>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="main-content-area">
                
                {{-- Error Display Section --}}
                @if($errors->any())
                    <div class="alert alert-danger" style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #721c24; font-size: 16px;">
                            @icon('fa-exclamation-triangle') Please fix the following errors:
                        </h4>
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach($errors->all() as $error)
                                <li style="color: #721c24; margin-bottom: 5px;">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger" style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px;">
                        <h4 style="margin: 0; color: #721c24; font-size: 16px;">
                            @icon('fa-exclamation-circle') {{ session('error') }}
                        </h4>
                    </div>
                @endif
                
                @if(session('success'))
                    <div class="alert alert-success" style="margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px;">
                        <h4 style="margin: 0; color: #155724; font-size: 16px;">
                            @icon('fa-check-circle') {{ session('success') }}
                        </h4>
                    </div>
                @endif
                
                <form id="createLeadForm" action="{{ route('leads.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf


                    {{-- ==================== PERSONAL SECTION ==================== --}}
                    <section id="personalSection" class="content-section">
                        <!-- Lead Type Toggle -->
                        <section class="form-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div class="section-header">
                                <h3>@icon('fa-building') Lead Type</h3>
                            </div>
                            
                            <div class="content-grid">
                                <div class="form-group full-width">
                                    <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                                        Is this new lead a company?
                                    </label>
                                    <div style="display: flex; gap: 20px; align-items: center;">
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" name="is_company" value="no" id="is_company_no" 
                                                   {{ old('is_company', 'no') == 'no' ? 'checked' : '' }} 
                                                   onchange="toggleCompanyFields(false)" style="margin-right: 8px;">
                                            <span>No (Personal Lead)</span>
                                        </label>
                                        <label style="display: flex; align-items: center; cursor: pointer;">
                                            <input type="radio" name="is_company" value="yes" id="is_company_yes" 
                                                   {{ old('is_company') == 'yes' ? 'checked' : '' }} 
                                                   onchange="toggleCompanyFields(true)" style="margin-right: 8px;">
                                            <span>Yes (Company Lead)</span>
                                        </label>
                                    </div>
                                    @error('is_company')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </section>

                        <!-- Basic Information -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3>@icon('fa-user-circle') Basic Information</h3>
                            </div>
                            
                            {{-- Personal Information Fields (shown when is_company = no) --}}
                            <div id="personalFields" class="personal-lead-fields">
                                <div class="content-grid">
                                    <div class="form-group">
                                        <label for="firstName">First Name <span class="text-danger">*</span></label>
                                        <input type="text" id="firstName" name="first_name" value="{{ old('first_name') }}" required>
                                        @error('first_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="lastName">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" id="lastName" name="last_name" value="{{ old('last_name') }}" required>
                                        @error('last_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="dob">Date of Birth <span class="text-danger">*</span></label>
                                        <input type="text" id="dob" name="dob" value="{{ old('dob') }}" class="date-picker" placeholder="dd/mm/yyyy" required>
                                        @error('dob')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="age">Age</label>
                                        <input type="text" id="age" name="age" value="{{ old('age') }}" readonly>
                                    </div>

                                    <div class="form-group">
                                        <label for="gender">Gender <span class="text-danger">*</span></label>
                                        <select id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                            <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                            <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                        @error('gender')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="maritalStatus">Marital Status</label>
                                        <select id="maritalStatus" name="marital_status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Never Married" {{ (old('marital_status') == 'Never Married' || old('marital_status') == 'Single') ? 'selected' : '' }}>Never Married</option>
                                            <option value="Engaged" {{ old('marital_status') == 'Engaged' ? 'selected' : '' }}>Engaged</option>
                                            <option value="Married" {{ old('marital_status') == 'Married' ? 'selected' : '' }}>Married</option>
                                            <option value="De Facto" {{ (old('marital_status') == 'Defacto' || old('marital_status') == 'De Facto') ? 'selected' : '' }}>De Facto</option>
                                            <option value="Separated" {{ old('marital_status') == 'Separated' ? 'selected' : '' }}>Separated</option>
                                            <option value="Divorced" {{ old('marital_status') == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                                            <option value="Widowed" {{ old('marital_status') == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                                        </select>
                                        @error('marital_status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Company Information Fields (shown when is_company = yes) --}}
                            <div id="companyFields" class="company-lead-fields" style="display: none;">
                                <div class="content-grid">
                                    <div class="form-group">
                                        <label for="companyName">Company Name <span class="text-danger">*</span></label>
                                        <input type="text" id="companyName" name="company_name" 
                                               value="{{ old('company_name') }}" 
                                               class="company-field company-required">
                                        @error('company_name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Does this company have a trading name?</label>
                                        <div style="display: flex; gap: 20px; margin-top: 5px;">
                                            <label><input type="radio" name="has_trading_name" value="1" {{ old('has_trading_name', 0) ? 'checked' : '' }} class="company-field"> Yes</label>
                                            <label><input type="radio" name="has_trading_name" value="0" {{ !old('has_trading_name', 0) ? 'checked' : '' }} class="company-field"> No</label>
                                        </div>
                                    </div>
                                    <div id="leadTradingNamesContainer" class="form-group" style="{{ !old('has_trading_name', 0) ? 'display:none;' : '' }}">
                                        <label>Trading Names</label>
                                        <div id="leadTradingNamesList">
                                            @php $leadTradingNames = old('trading_names', ['']); $leadTradingNames = is_array($leadTradingNames) ? $leadTradingNames : ['']; @endphp
                                            @foreach($leadTradingNames as $idx => $tn)
                                            <div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">
                                                <input type="text" name="trading_names[]" value="{{ is_string($tn) ? $tn : '' }}" placeholder="Trading name" class="company-field" style="flex: 1;">
                                                <label><input type="radio" name="trading_name_primary" value="{{ $idx }}" {{ $idx === 0 ? 'checked' : '' }}> Primary</label>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLeadTradingName(this)">@icon('fa-times')</button>
                                            </div>
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLeadTradingName()">@icon('fa-plus') Add another</button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="abn">ABN</label>
                                        <input type="text" id="abn" name="ABN_number" 
                                               value="{{ old('ABN_number') }}" 
                                               class="company-field" 
                                               placeholder="12 345 678 901"
                                               maxlength="14">
                                        <small class="form-text text-muted">11 digits (spaces optional)</small>
                                        @error('ABN_number')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="acn">ACN</label>
                                        <input type="text" id="acn" name="ACN" 
                                               value="{{ old('ACN') }}" 
                                               class="company-field" 
                                               placeholder="123 456 789"
                                               maxlength="11">
                                        <small class="form-text text-muted">9 digits (spaces optional)</small>
                                        @error('ACN')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="companyType">Business Type</label>
                                        <select id="companyType" name="company_type" class="company-field">
                                            <option value="">Select Business Type</option>
                                            <option value="Sole Trader" {{ old('company_type') == 'Sole Trader' ? 'selected' : '' }}>
                                                Sole Trader
                                            </option>
                                            <option value="Partnership" {{ old('company_type') == 'Partnership' ? 'selected' : '' }}>
                                                Partnership
                                            </option>
                                            <option value="Proprietary Company" {{ old('company_type') == 'Proprietary Company' ? 'selected' : '' }}>
                                                Proprietary Company (Pty Ltd)
                                            </option>
                                            <option value="Public Company" {{ old('company_type') == 'Public Company' ? 'selected' : '' }}>
                                                Public Company
                                            </option>
                                            <option value="Not-for-Profit" {{ old('company_type') == 'Not-for-Profit' ? 'selected' : '' }}>
                                                Not-for-Profit Organization
                                            </option>
                                            <option value="Trustee" {{ \App\Models\Company::isTrusteeBusinessType(old('company_type')) ? 'selected' : '' }}>
                                                Trustee
                                            </option>
                                            <option value="Other" {{ old('company_type') == 'Other' ? 'selected' : '' }}>
                                                Other
                                            </option>
                                        </select>
                                        @error('company_type')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="companyWebsite">Company Website</label>
                                        <input type="url" id="companyWebsite" name="company_website" 
                                               value="{{ old('company_website') }}" 
                                               class="company-field" 
                                               placeholder="https://www.example.com">
                                        @error('company_website')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                                
                                {{-- Primary Contact Person Section --}}
                                <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
                                    <h4 style="margin-bottom: 15px;">
                                        @icon('fa-user-tie') Primary Contact Person
                                    </h4>
                                    
                                    <div class="content-grid">
                                        <div class="form-group full-width">
                                            <label for="contactPersonEmail">Search Contact Person <span class="text-danger">*</span></label>
                                            <select id="contactPersonEmail" name="contact_person_id" 
                                                    class="form-control mm-select-contact-person company-required" 
                                                    data-placeholder="Type phone, email, name, or client ID to search..."
                                                    style="width: 100%;">
                                                @if(old('contact_person_id'))
                                                    @php
                                                        $oldContactPerson = \App\Models\Admin::find(old('contact_person_id'));
                                                    @endphp
                                                    @if($oldContactPerson)
                                                        <option value="{{ $oldContactPerson->id }}" selected>
                                                            {{ $oldContactPerson->first_name }} {{ $oldContactPerson->last_name }} 
                                                            ({{ $oldContactPerson->email }})
                                                        </option>
                                                    @endif
                                                @endif
                                            </select>
                                            <small class="form-text text-muted">
                                                Search existing clients/leads by email, name, phone, or client ID. Selected person's details will auto-fill below. Or enter phone/email below — if they match an existing person, they will be auto-associated.
                                            </small>
                                            <div id="associatedPersonAlert" class="alert alert-info mt-2" style="display: none;">
                                                @icon('fa-link') <strong>Associated:</strong> This phone/email belongs to <span id="associatedPersonName"></span>. They will be set as the contact person.
                                            </div>
                                            @error('contact_person_id')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonFirstName">First Name <span class="text-danger">*</span></label>
                                            <input type="text" id="contactPersonFirstName" name="contact_person_first_name" 
                                                   value="{{ old('contact_person_first_name') }}" 
                                                   class="company-field contact-person-field company-required" readonly>
                                            <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonLastName">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" id="contactPersonLastName" name="contact_person_last_name" 
                                                   value="{{ old('contact_person_last_name') }}" 
                                                   class="company-field contact-person-field company-required" readonly>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonPosition">Position/Title</label>
                                            <input type="text" id="contactPersonPosition" name="contact_person_position" 
                                                   value="{{ old('contact_person_position') }}" 
                                                   class="company-field" 
                                                   placeholder="e.g., HR Manager, Director">
                                            @error('contact_person_position')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonPhone">Phone</label>
                                            <input type="text" id="contactPersonPhone" name="contact_person_phone" 
                                                   value="{{ old('contact_person_phone') }}" 
                                                   class="company-field contact-person-field" readonly>
                                            <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contactPersonEmailDisplay">Email</label>
                                            <input type="email" id="contactPersonEmailDisplay" 
                                                   value="{{ old('contact_person_email_display') }}" 
                                                   class="company-field contact-person-field" readonly>
                                            <small class="form-text text-muted">Auto-filled from selected contact person</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Phone Numbers -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3>@icon('fa-phone') Phone Number <span class="text-danger">*</span></h3>
                            </div>
                            
                            <div class="repeatable-section">
                                <div class="content-grid">
                                    <div class="form-group">
                                        <label>Type</label>
                                        <select name="contact_type_hidden[0]" class="contact-type-selector">
                                            <option value="Personal">Personal</option>
                                            <option value="Work">Work</option>
                                            <option value="Mobile">Mobile</option>
                                            <option value="Business">Business</option>
                                            <option value="Secondary">Secondary</option>
                                            <option value="Father">Father</option>
                                            <option value="Mother">Mother</option>
                                            <option value="Brother">Brother</option>
                                            <option value="Sister">Sister</option>
                                            <option value="Uncle">Uncle</option>
                                            <option value="Aunt">Aunt</option>
                                            <option value="Cousin">Cousin</option>
                                            <option value="Partner">Partner</option>
                                            <option value="Others">Others</option>
                                            <option value="Not In Use">Not In Use</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Country Code</label>
                                        @php
                                            use App\Helpers\PhoneHelper;
                                            $leadCc = old('country_code.0');
                                            if ($leadCc === null || $leadCc === '') {
                                                $leadCc = PhoneHelper::getDefaultCountryCode();
                                            } else {
                                                $leadCc = PhoneHelper::normalizeCountryCode($leadCc);
                                            }
                                        @endphp
                                        @include('partials.country-code-select', [
                                            'name' => 'country_code[0]',
                                            'selected' => $leadCc,
                                            'selectClass' => 'country-code-selector',
                                            'showPlaceholder' => false,
                                        ])
                                    </div>
                                    <div class="form-group">
                                        <label>Phone Number <span class="text-danger">*</span></label>
                                        <input type="text" id="primaryPhoneInput" name="phone[0]" class="form-control" placeholder="Enter phone number" value="{{ old('phone.0') }}" required>
                                        @error('phone.0')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            
                        </section>

                        <!-- Email Addresses -->
                        <section class="form-section">
                            <div class="section-header">
                                <h3>@icon('fa-envelope') Email Address <span class="text-danger">*</span></h3>
                            </div>
                            
                            <div class="repeatable-section">
                            <div class="content-grid">
                                <div class="form-group">
                                        <label>Type</label>
                                        <select name="email_type_hidden[0]" class="email-type-selector">
                                            <option value="Personal">Personal</option>
                                            <option value="Work">Work</option>
                                            <option value="Business">Business</option>
                                            <option value="Secondary">Secondary</option>
                                            <option value="Father">Father</option>
                                            <option value="Mother">Mother</option>
                                            <option value="Brother">Brother</option>
                                            <option value="Sister">Sister</option>
                                            <option value="Uncle">Uncle</option>
                                            <option value="Aunt">Aunt</option>
                                            <option value="Cousin">Cousin</option>
                                            <option value="Partner">Partner</option>
                                            <option value="Others">Others</option>
                                            <option value="Not In Use">Not In Use</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                        <label>Email Address <span class="text-danger">*</span></label>
                                        <input type="email" id="primaryEmailInput" name="email[0]" class="form-control" placeholder="Enter email address" value="{{ old('email.0') }}" required>
                                        @error('email.0')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                </div>
                                <div class="form-group">
                                    <label for="lead_pipeline_status">Lead stage</label>
                                    <select id="lead_pipeline_status" name="lead_status" class="form-control">
                                        @foreach(($leadStageLabels ?? []) as $val => $lbl)
                                            <option value="{{ $val }}" {{ old('lead_status', 'new') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                    @error('lead_status')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group" id="lead_followup_date_wrap" style="display: none;">
                                    <label for="lead_followup_date">Follow-up date <span class="text-muted">(optional)</span></label>
                                    <input type="date" id="lead_followup_date" name="followup_date" class="form-control" value="{{ old('followup_date') }}">
                                    @error('followup_date')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <label for="assigned_staff_id">Assigned to</label>
                                    <select id="assigned_staff_id" name="assigned_staff_id" class="form-control">
                                        @foreach(($assignableStaff ?? collect()) as $st)
                                            <option value="{{ $st->id }}" {{ (string) old('assigned_staff_id', Auth::id()) === (string) $st->id ? 'selected' : '' }}>
                                                {{ $st->first_name }} {{ $st->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_staff_id')
                                        <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                                </div>
                            </div>
                            
                        </section>
                    </section>

                    <!-- Form Actions (Hidden for floating button) -->
                    <div class="form-actions" style="margin-top: 30px; padding: 20px; background: white; border-radius: 8px; display: flex; gap: 15px; justify-content: flex-end; visibility: hidden;">
                        <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                            @icon('fa-times') Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="hiddenSubmitBtn">
                            @icon('fa-save') Save Lead
                        </button>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>

    <!-- Floating Save Button -->
    <div class="floating-save-container">
        <div class="floating-save-buttons">
            <button type="button" class="btn btn-floating btn-cancel" onclick="window.history.back()">
                @icon('fa-times')
                <span>Cancel</span>
            </button>
            <button type="button" class="btn btn-floating btn-save" id="floatingSaveBtn">
                @icon('fa-save')
                <span>Save Lead</span>
            </button>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        window.countriesData = @json($countriesPhoneData ?? []);
        window.phonePopularIsoCodes = @json(config('phone.popular_countries'));
        window.phoneDefaultDialCode = @json(config('phone.default_country_code'));
    </script>
    <script src="{{ asset('js/shared/phone-country-select-builder.js') }}"></script>
    <script src="{{ asset('js/leads/lead-form-navigation.js') }}"></script>
    <script src="{{ asset('js/leads/lead-form.js') }}"></script>
    <script>
        function clearStuckGlobalLoaderForLeadCreate() {
            const loader = document.querySelector('.loader');
            if (!loader) return;

            const styles = window.getComputedStyle(loader);
            const isVisible = styles.display !== 'none' && styles.visibility !== 'hidden' && styles.opacity !== '0';
            if (!isVisible) return;

            // Safety net for this page only: a stuck global loader can block all form inputs.
            loader.style.display = 'none';
            loader.style.opacity = '0';
            loader.style.visibility = 'hidden';
            loader.style.pointerEvents = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            clearStuckGlobalLoaderForLeadCreate();
            setTimeout(clearStuckGlobalLoaderForLeadCreate, 300);

            console.log('DOM Content Loaded');
            
            const floatingSaveBtn = document.getElementById('floatingSaveBtn');
            const hiddenSubmitBtn = document.getElementById('hiddenSubmitBtn');
            const form = document.getElementById('createLeadForm');
            const floatingContainer = document.querySelector('.floating-save-container');
            
            console.log('Elements found:', {
                floatingSaveBtn: !!floatingSaveBtn,
                hiddenSubmitBtn: !!hiddenSubmitBtn,
                form: !!form,
                floatingContainer: !!floatingContainer
            });
            
            // Add form submit event listener for debugging
            form.addEventListener('submit', function(e) {
                console.log('Form submit event triggered');
                console.log('Form action:', form.action);
                console.log('Form method:', form.method);
                
                // Check CSRF token
                const csrfToken = document.querySelector('input[name="_token"]');
                console.log('CSRF token found:', !!csrfToken);
                if (csrfToken) {
                    console.log('CSRF token value:', csrfToken.value);
                }
            });
            
            // Add invalid event listener to show validation errors clearly
            form.addEventListener('invalid', function(e) {
                console.log('Form validation failed on field:', e.target.name);
                
                // Scroll to the first invalid field
                e.target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight the invalid field
                e.target.focus();
            }, true);
            
            // Handle floating save button click
            floatingSaveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Floating save button clicked');
                console.log('Form element:', form);
                console.log('Form action:', form.action);
                console.log('Form method:', form.method);
                
                // Check form data
                const formData = new FormData(form);
                console.log('Form data entries:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ': ' + value);
                }
                
                // Use requestSubmit() to trigger HTML5 validation and show error messages
                console.log('Submitting form with validation...');
                try {
                    // Try modern requestSubmit (triggers validation)
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        // Fallback: trigger click on hidden submit button
                        hiddenSubmitBtn.click();
                    }
                } catch (error) {
                    console.error('Form submission error:', error);
                    // Last resort fallback
                    hiddenSubmitBtn.click();
                }
            });
            
            // Add scroll-based visibility control
            let lastScrollTop = 0;
            let ticking = false;
            
            function updateFloatingButton() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight;
                
                // Show button when not at the very top or bottom
                if (scrollTop > 100 && scrollTop < documentHeight - windowHeight - 100) {
                    floatingContainer.classList.remove('hidden');
                    floatingContainer.classList.add('visible');
                } else if (scrollTop <= 100) {
                    floatingContainer.classList.add('hidden');
                    floatingContainer.classList.remove('visible');
                }
                
                lastScrollTop = scrollTop;
                ticking = false;
            }
            
            function requestTick() {
                if (!ticking) {
                    requestAnimationFrame(updateFloatingButton);
                    ticking = true;
                }
            }
            
            window.addEventListener('scroll', requestTick);
            
            // Initialize button state
            updateFloatingButton();
            
            // Add keyboard shortcut for save (Ctrl+S or Cmd+S)
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    console.log('Keyboard shortcut (Ctrl/Cmd+S) triggered');
                    // Trigger the floating save button click (which now properly validates)
                    floatingSaveBtn.click();
                }
            });
            
            // Add visual feedback for form changes
            const formInputs = form.querySelectorAll('input, select, textarea');
            let formChanged = false;
            
            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    formChanged = true;
                    updateSaveButtonState();
                });
                
                input.addEventListener('input', function() {
                    formChanged = true;
                    updateSaveButtonState();
                });
            });
            
            function updateSaveButtonState() {
                if (formChanged) {
                    floatingSaveBtn.style.background = 'linear-gradient(135deg, #dc3545 0%, #fd7e14 100%)';
                    floatingSaveBtn.querySelector('span').textContent = 'Save Changes';
                } else {
                    floatingSaveBtn.style.background = 'linear-gradient(135deg, #6777ef 0%, #47c363 100%)';
                    floatingSaveBtn.querySelector('span').textContent = 'Save Lead';
                }
            }
        });

        window.addEventListener('load', clearStuckGlobalLoaderForLeadCreate);
    </script>
    
    <script>
    // Initialize form with at least one field in each required sections
    document.addEventListener('DOMContentLoaded', function() {
        // Add initial phone and email fields
        // Phone and email fields are now static HTML, no need to initialize dynamically
        
        // Display validation errors for phone and email fields
        displayFieldErrors();
        
        // Add real-time error clearing for phone and email fields
        setupErrorClearing();
        
        // DOB to Age calculation (same as client edit page)
        const dobField = document.getElementById('dob');
        const ageField = document.getElementById('age');
        if (dobField && ageField) {
            // Initialize age if DOB exists
            if (dobField.value) {
                ageField.value = calculateAge(dobField.value);
            }

            // Handle manual input changes (e.g., typing or pasting)
            dobField.addEventListener('input', function() {
                ageField.value = calculateAge(this.value);
            });
        }
    });
    
    // Initialize Flatpickr after all scripts are loaded
    $(document).ready(function() {
        // Wait a bit for all scripts to load
        setTimeout(function() {
            initDatePicker();
        }, 500);
        
        // Initialize company toggle functionality
        initCompanyToggle();
        
        // Initialize contact person search and phone/email match check if company fields are visible
        @if(old('is_company') == 'yes')
            initContactPersonSearch();
            initContactMatchCheck();
        @endif

        // Has trading name toggle (company leads)
        $('input[name="has_trading_name"]').on('change', function() {
            $('#leadTradingNamesContainer').toggle($(this).val() === '1');
        });
    });

    function addLeadTradingName() {
        const container = $('#leadTradingNamesList');
        const idx = container.find('.trading-name-row').length;
        container.append('<div class="trading-name-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">' +
            '<input type="text" name="trading_names[]" placeholder="Trading name" class="company-field" style="flex: 1;">' +
            '<label><input type="radio" name="trading_name_primary" value="' + idx + '"> Primary</label>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLeadTradingName(this)">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-times') : '<i class="fas fa-times"></i>') + '</button></div>');
        container.find('.trading-name-row').each(function(i) { $(this).find('input[name="trading_name_primary"]').val(i); });
    }
    function removeLeadTradingName(btn) {
        const container = $('#leadTradingNamesList');
        if (container.find('.trading-name-row').length <= 1) return;
        $(btn).closest('.trading-name-row').remove();
        $('#leadTradingNamesList .trading-name-row').each(function(i) { $(this).find('input[name="trading_name_primary"]').val(i); });
    }
    
    // Toggle between personal and company fields
    function toggleCompanyFields(isCompany) {
        const personalFields = document.getElementById('personalFields');
        const companyFields = document.getElementById('companyFields');
        
        // Get all fields that should be required for personal leads
        const personalRequiredFields = personalFields ? personalFields.querySelectorAll('[required]') : [];
        
        // Get all company required fields by class selector (more reliable than [required])
        const companyRequiredFields = companyFields ? companyFields.querySelectorAll('.company-required') : [];
        
        if (isCompany) {
            // Show company fields, hide personal fields
            if (personalFields) personalFields.style.display = 'none';
            if (companyFields) companyFields.style.display = 'block';
            
            // Remove required from personal fields
            personalRequiredFields.forEach(field => {
                field.removeAttribute('required');
            });
            
            // Add required to company fields
            companyRequiredFields.forEach(field => {
                field.setAttribute('required', 'required');
            });
            
            // Clear personal field values (optional)
            if (personalFields) {
                personalFields.querySelectorAll('input, select').forEach(field => {
                    if (field.type !== 'hidden' && field.id !== 'age') {
                        field.value = '';
                    }
                });
            }
            
            // Initialize contact person search when company fields are shown
            setTimeout(function() {
                initContactPersonSearch();
                initContactMatchCheck();
            }, 100);
        } else {
            // Show personal fields, hide company fields
            if (personalFields) personalFields.style.display = 'block';
            if (companyFields) companyFields.style.display = 'none';
            $('#associatedPersonAlert').hide();
            
            // Remove required from company fields
            companyRequiredFields.forEach(field => {
                field.removeAttribute('required');
            });
            
            // Add required to personal fields
            personalRequiredFields.forEach(field => {
                field.setAttribute('required', 'required');
            });
            
            // Clear company field values (optional, but preserve contact person selection)
            if (companyFields) {
                companyFields.querySelectorAll('input, select').forEach(field => {
                    if (field.type !== 'hidden' && field.id !== 'contactPersonEmail') {
                        if (field.classList.contains('contact-person-field')) {
                            field.value = '';
                        } else {
                            field.value = '';
                        }
                    }
                });
            }
        }
    }
    
    // Initialize company toggle on page load
    function initCompanyToggle() {
        const isCompanyRadio = document.querySelector('input[name="is_company"][value="yes"]');
        const isPersonalRadio = document.querySelector('input[name="is_company"][value="no"]');
        
        // Set initial state based on old input or default
        const isCompany = @json(old('is_company') == 'yes');
        toggleCompanyFields(isCompany);
        
        // Add event listeners
        if (isCompanyRadio) {
            isCompanyRadio.addEventListener('change', function() {
                if (this.checked) {
                    toggleCompanyFields(true);
                }
            });
        }
        
        if (isPersonalRadio) {
            isPersonalRadio.addEventListener('change', function() {
                if (this.checked) {
                    toggleCompanyFields(false);
                }
            });
        }
    }
    
    // Initialize mmSelect for contact person search
    function initContactPersonSearch() {
        const contactPersonSelect = $('#contactPersonEmail');
        
        // Check if mmSelect is already active
        if (contactPersonSelect.hasClass('mm-select-initialized')) {
            return; // Already initialized
        }
        
        // Ensure mmSelect plugin is available
        if (typeof $.fn.mmSelect === 'undefined') {
            console.warn('mmSelect not loaded. Contact person search will not work.');
            return;
        }
        
        contactPersonSelect.mmSelect({
            ajax: {
                url: '{{ route("api.search.contact.person") }}',
                dataType: 'json',
                delay: 250,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: function (params) {
                    return {
                        q: params.term, // search term
                        exclude_id: null // Can exclude current lead if editing
                    };
                },
                processResults: function (data) {
                    // Validate response format
                    if (!data || typeof data !== 'object') {
                        console.error('Invalid response format:', data);
                        return { results: [] };
                    }
                    
                    // Check if results array exists
                    if (!data.results || !Array.isArray(data.results)) {
                        console.warn('Response missing results array:', data);
                        return { results: [] };
                    }
                    
                    // Map results for ajax template
                    try {
                        return {
                            results: data.results.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.text || (item.first_name + ' ' + item.last_name),
                                    first_name: item.first_name || '',
                                    last_name: item.last_name || '',
                                    email: item.email || '',
                                    phone: item.phone || '',
                                    client_id: item.client_id || ''
                                };
                            })
                        };
                    } catch (error) {
                        console.error('Error processing results:', error, data);
                        return { results: [] };
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Contact person search error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText,
                        statusCode: xhr.status
                    });
                    // Return empty results instead of showing error
                    return { results: [] };
                },
                cache: true
            },
            minimumInputLength: 2,
            placeholder: 'Type phone, email, name, or client ID to search...',
            allowClear: true,
            width: '100%'
        });
        
        // Auto-fill contact person details when selected
        contactPersonSelect.on('mmselect:select', function (e) {
            const data = e.params.data;
            
            // Auto-fill fields
            $('#contactPersonFirstName').val(data.first_name || '');
            $('#contactPersonLastName').val(data.last_name || '');
            $('#contactPersonPhone').val(data.phone || '');
            $('#contactPersonEmailDisplay').val(data.email || '');
            
            // Add visual indicator
            $('.contact-person-field').addClass('field-auto-filled');
            
            // Store contact person ID (already in select value)
            console.log('Contact person selected:', data);
        });
        
        // Clear fields when selection is cleared
        contactPersonSelect.on('mmselect:clear', function (e) {
            $('#contactPersonFirstName').val('');
            $('#contactPersonLastName').val('');
            $('#contactPersonPhone').val('');
            $('#contactPersonEmailDisplay').val('');
            $('.contact-person-field').removeClass('field-auto-filled');
            $('#associatedPersonAlert').hide();
        });
    }
    
    // Check phone/email for matching contact person (company leads only)
    var contactMatchTimeout = null;
    function initContactMatchCheck() {
        const phoneInput = document.getElementById('primaryPhoneInput');
        const emailInput = document.getElementById('primaryEmailInput');
        if (!phoneInput || !emailInput) return;
        
        function checkContactMatch() {
            const isCompany = document.querySelector('input[name="is_company"][value="yes"]')?.checked;
            if (!isCompany) {
                $('#associatedPersonAlert').hide();
                return;
            }
            const phone = (phoneInput.value || '').trim();
            const email = (emailInput.value || '').trim();
            if (!phone && !email) {
                $('#associatedPersonAlert').hide();
                return;
            }
            $.ajax({
                url: '{{ route("leads.check.contact.match") }}',
                method: 'GET',
                data: { phone: phone, email: email },
                success: function(res) {
                    if (res.found && res.person) {
                        $('#associatedPersonName').text(res.person.first_name + ' ' + res.person.last_name + (res.person.client_id ? ' (' + res.person.client_id + ')' : ''));
                        $('#associatedPersonAlert').show();
                        const $select = $('#contactPersonEmail');
                        const existingOpt = $select.find('option[value="' + res.person.id + '"]');
                        if (existingOpt.length) {
                            $select.val(res.person.id).trigger('change');
                        } else {
                            const option = new Option(res.person.text, res.person.id, true, true);
                            $select.append(option).trigger('change');
                        }
                        $('#contactPersonFirstName').val(res.person.first_name || '');
                        $('#contactPersonLastName').val(res.person.last_name || '');
                        $('#contactPersonPhone').val(res.person.phone || '');
                        $('#contactPersonEmailDisplay').val(res.person.email || '');
                        $('.contact-person-field').addClass('field-auto-filled');
                    } else {
                        $('#associatedPersonAlert').hide();
                    }
                }
            });
        }
        
        function debouncedCheck() {
            clearTimeout(contactMatchTimeout);
            contactMatchTimeout = setTimeout(checkContactMatch, 400);
        }
        
        $(phoneInput).off('blur.contactMatch input.contactMatch').on('blur.contactMatch input.contactMatch', debouncedCheck);
        $(emailInput).off('blur.contactMatch input.contactMatch').on('blur.contactMatch input.contactMatch', debouncedCheck);
    }
    
    // Function to display validation errors for each field
    function displayFieldErrors() {
        // Get all error messages from Laravel
        const errors = @json($errors->all());
        const errorBag = @json($errors->getMessageBag()->toArray());
        
        // Clear any existing error messages first
        document.querySelectorAll('.phone-error, .email-error, .field-error').forEach(el => {
            el.textContent = '';
            el.style.display = 'none';
        });
        
        // Check if we have field-specific errors
        let hasFieldSpecificErrors = false;
        
        // Display phone errors
        Object.keys(errorBag).forEach(key => {
            if (key.startsWith('phone.')) {
                hasFieldSpecificErrors = true;
                const index = key.split('.')[1];
                const errorElement = document.querySelector(`.phone-error-${index}`);
                if (errorElement) {
                    errorElement.textContent = errorBag[key][0];
                    errorElement.style.display = 'block';
                    errorElement.style.color = '#dc3545';
                    errorElement.style.fontSize = '12px';
                    errorElement.style.marginTop = '5px';
                }
            } else if (key === 'phone') {
                // General phone error - show in the section
                const phoneContainer = document.getElementById('phoneNumbersContainer');
                if (phoneContainer) {
                    // Remove existing general error
                    const existingError = phoneContainer.querySelector('.general-phone-error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'general-phone-error text-danger';
                    errorDiv.style.marginTop = '10px';
                    errorDiv.style.fontSize = '12px';
                    errorDiv.textContent = errorBag[key][0];
                    phoneContainer.appendChild(errorDiv);
                }
            }
        });
        
        // Display email errors
        Object.keys(errorBag).forEach(key => {
            if (key.startsWith('email.')) {
                hasFieldSpecificErrors = true;
                const index = key.split('.')[1];
                const errorElement = document.querySelector(`.email-error-${index}`);
                if (errorElement) {
                    errorElement.textContent = errorBag[key][0];
                    errorElement.style.display = 'block';
                    errorElement.style.color = '#dc3545';
                    errorElement.style.fontSize = '12px';
                    errorElement.style.marginTop = '5px';
                }
            } else if (key === 'email') {
                // General email error - show in the section
                const emailContainer = document.getElementById('emailAddressesContainer');
                if (emailContainer) {
                    // Remove existing general error
                    const existingError = emailContainer.querySelector('.general-email-error');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'general-email-error text-danger';
                    errorDiv.style.marginTop = '10px';
                    errorDiv.style.fontSize = '12px';
                    errorDiv.textContent = errorBag[key][0];
                    emailContainer.appendChild(errorDiv);
                }
            }
        });
        
        // Hide general error container if we have field-specific errors
        const generalErrorContainer = document.querySelector('.form-validation-errors');
        if (generalErrorContainer) {
            if (hasFieldSpecificErrors) {
                generalErrorContainer.style.display = 'none';
            } else {
                generalErrorContainer.style.display = 'block';
            }
        }
    }
    
    // Function to setup error clearing when user types
    function setupErrorClearing() {
        // Clear phone errors when user types
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('phone-number-input')) {
                const index = e.target.name.match(/\[(\d+)\]/)[1];
                const errorElement = document.querySelector(`.phone-error-${index}`);
                if (errorElement) {
                    errorElement.style.display = 'none';
                    errorElement.textContent = '';
                }
            }
            
            if (e.target.classList.contains('email-input')) {
                const index = e.target.name.match(/\[(\d+)\]/)[1];
                const errorElement = document.querySelector(`.email-error-${index}`);
                if (errorElement) {
                    errorElement.style.display = 'none';
                    errorElement.textContent = '';
                }
            }
        });
    }
    
    // Function to initialize Flatpickr for DOB field
    function initDatePicker() {
        try {
            // Check if Flatpickr is available
            if (typeof flatpickr !== 'undefined') {
                const dobInput = document.getElementById('dob');
                const ageInput = document.getElementById('age');
                
                if (dobInput && ageInput) {
                    // Check if already initialized
                    if ($(dobInput).data('flatpickr')) {
                        return;
                    }
                    
                    // Initialize Flatpickr for DOB field
                    flatpickr(dobInput, {
                        dateFormat: 'd/m/Y',
                        allowInput: true,
                        clickOpens: true,
                        defaultDate: dobInput.value || null,
                        maxDate: 'today', // DOB cannot be in the future
                        minDate: '01/01/1000',
                        locale: {
                            firstDayOfWeek: 1 // Monday
                        },
                        onChange: function(selectedDates, dateStr, instance) {
                            // Update age when date is selected
                            dobInput.value = dateStr;
                            ageInput.value = calculateAge(dateStr);
                        }
                    });
                    
                    console.log('✅ DOB Flatpickr initialized successfully');
                }
            } else {
                console.warn('⚠️ Flatpickr not available');
            }
        } catch(e) {
            console.error('❌ Flatpickr initialization failed:', e.message);
        }
    }
    
    // Age calculation function (same as client edit page)
    function calculateAge(dob) {
        if (!dob || !/^\d{2}\/\d{2}\/\d{4}$/.test(dob)) return '';

        try {
            const [day, month, year] = dob.split('/').map(Number);
            const dobDate = new Date(year, month - 1, day);
            if (isNaN(dobDate.getTime())) return ''; // Invalid date

            const today = new Date();
            let years = today.getFullYear() - dobDate.getFullYear();
            let months = today.getMonth() - dobDate.getMonth();

            if (months < 0) {
                years--;
                months += 12;
            }

            if (today.getDate() < dobDate.getDate()) {
                months--;
                if (months < 0) {
                    years--;
                    months += 12;
                }
            }

            return years + ' years ' + months + ' months';
        } catch (e) {
            return '';
        }
    }
    
    </script>
    <script>
        (function () {
            function syncLeadFollowupDateVisibility() {
                var sel = document.getElementById('lead_pipeline_status');
                var wrap = document.getElementById('lead_followup_date_wrap');
                if (!sel || !wrap) return;
                wrap.style.display = sel.value === 'follow_up' ? 'block' : 'none';
            }
            document.addEventListener('DOMContentLoaded', function () {
                var sel = document.getElementById('lead_pipeline_status');
                if (sel) {
                    sel.addEventListener('change', syncLeadFollowupDateVisibility);
                    syncLeadFollowupDateVisibility();
                }
            });
        })();
    </script>
@endpush

