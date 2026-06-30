<div class="tab-pane active" id="companydetails-tab">
    @php $comp = $fetchedData->company ?? null; @endphp
    <style>
        #companydetails-tab .sponsorship-details-card { align-self: start; display: block; }
        #companydetails-tab .sponsorship-details-card h3 { margin-bottom: 10px; }
        #companydetails-tab .sponsorship-details-block { border-bottom: 1px solid #e9ecef; padding-bottom: 6px; margin-bottom: 6px; }
        #companydetails-tab .sponsorship-details-block:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
        #companydetails-tab .sponsorship-details-subheading { margin: 10px 0 4px 0; font-weight: 600; color: #495057; font-size: 0.95em; }
        #companydetails-tab .sponsorship-details-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 6px 12px; }
        #companydetails-tab .sponsorship-details-card--single .sponsorship-details-fields { margin-top: 10px; }
        #companydetails-tab .sponsorship-details-fields .field-group { padding: 4px 0; }
        #companydetails-tab .nomination-details-card { align-self: start; display: block; }
        #companydetails-tab .nomination-details-card h3 { margin-bottom: 8px; }
        #companydetails-tab .nomination-details-block { border-bottom: 1px solid #e9ecef; padding-bottom: 4px; margin-bottom: 4px; }
        #companydetails-tab .nomination-details-block:last-child { border-bottom: none; padding-bottom: 0; margin-bottom: 0; }
        #companydetails-tab .nomination-details-subheading { margin: 6px 0 2px 0; font-weight: 600; color: #495057; font-size: 0.9em; line-height: 1.2; }
        #companydetails-tab .nomination-details-fields { display: flex; flex-direction: column; gap: 0; }
        #companydetails-tab .nomination-details-card--single .nomination-details-fields { margin-top: 8px; }
        #companydetails-tab .nomination-details-fields .field-group { padding: 2px 0; min-height: 0; }
        #companydetails-tab .nomination-details-fields .field-label { white-space: nowrap; flex-shrink: 0; padding-right: 8px; }
        #companydetails-tab .nomination-details-fields .field-value { text-align: right; word-break: break-word; }
    </style>
    <div class="content-grid">
        {{-- Company Information Card --}}
        <div class="card" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3>@icon('fa-building') Company Information</h3>
                <a href="{{ route('clients.edit', base64_encode(convert_uuencode($fetchedData->id))) }}" 
                   class="btn btn-sm btn-primary">
                    @icon('fa-edit') Edit
                </a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <div class="field-group">
                    <span class="field-label">Company Name:</span>
                    <span class="field-value">{{ optional($fetchedData->company)->company_name ?? 'N/A' }}</span>
                </div>
                @php
                    $comp = $fetchedData->company ?? null;
                    $tradingNamesDisplay = $comp && ($comp->tradingNames?->isNotEmpty() ?? false)
                        ? $comp->tradingNames->pluck('trading_name')->join(', ')
                        : ($comp->trading_name ?? null);
                @endphp
                @if($tradingNamesDisplay)
                <div class="field-group">
                    <span class="field-label">Trading Name(s):</span>
                    <span class="field-value">{{ $tradingNamesDisplay }}</span>
                </div>
                @endif
                @if(optional($fetchedData->company)->ABN_number)
                <div class="field-group">
                    <span class="field-label">ABN:</span>
                    <span class="field-value">{{ $fetchedData->company->ABN_number }}</span>
                </div>
                @endif
                @if(optional($fetchedData->company)->ACN)
                <div class="field-group">
                    <span class="field-label">ACN:</span>
                    <span class="field-value">{{ $fetchedData->company->ACN }}</span>
                </div>
                @endif
                @if(optional($fetchedData->company)->company_type)
                <div class="field-group">
                    <span class="field-label">Business Type:</span>
                    <span class="field-value">{{ \App\Models\Company::businessTypeLabel($fetchedData->company->company_type) }}</span>
                </div>
                @endif
                @if(optional($fetchedData->company)->company_website)
                <div class="field-group">
                    <span class="field-label">Website:</span>
                    <span class="field-value">
                        <a href="{{ $fetchedData->company->company_website }}" target="_blank" rel="noopener noreferrer">
                            {{ $fetchedData->company->company_website }}
                        </a>
                    </span>
                </div>
                @endif
                @if($comp && $comp->isTrusteeBusiness() && ($comp->trust_name || $comp->trust_abn || $comp->trustee_name || $comp->trustee_details))
                <div class="field-group" style="grid-column: 1 / -1;">
                    <span class="field-label">Trust details:</span>
                    <span class="field-value">
                        @if($comp->trust_name) Trust name: {{ $comp->trust_name }}@endif
                        @if($comp->trust_abn) @if($comp->trust_name) | @endif ABN/ACN: {{ $comp->trust_abn }}@endif
                        @if($comp->trustee_name) @if($comp->trust_name || $comp->trust_abn) | @endif Trustee: {{ $comp->trustee_name }}@endif
                        @if($comp->trustee_details) | {{ $comp->trustee_details }}@endif
                    </span>
                </div>
                @endif
            </div>
        </div>

        {{-- Sponsorship Card(s) --}}
        @if($comp && $comp->sponsorships->isNotEmpty())
            @php $sponsorshipCount = $comp->sponsorships->count(); @endphp
            <div class="card sponsorship-details-card{{ $sponsorshipCount === 1 ? ' sponsorship-details-card--single' : '' }}" style="margin-bottom: 20px;">
                <h3>@icon('fa-file-contract') {{ $sponsorshipCount > 1 ? 'Sponsorships' : 'Sponsorship' }}</h3>
                @foreach($comp->sponsorships as $idx => $s)
                <div class="{{ $sponsorshipCount > 1 ? 'sponsorship-details-block' : '' }}">
                    @if($sponsorshipCount > 1)
                    <p class="sponsorship-details-subheading">Sponsorship {{ $idx + 1 }}</p>
                    @endif
                    <div class="sponsorship-details-fields">
                        @if($s->sponsorship_type)<div class="field-group"><span class="field-label">Type:</span><span class="field-value">{{ $s->sponsorship_type }}</span></div>@endif
                        @if($s->sponsorship_status)<div class="field-group"><span class="field-label">Status:</span><span class="field-value">{{ $s->sponsorship_status }}</span></div>@endif
                        @if($s->trn)<div class="field-group"><span class="field-label">TRN:</span><span class="field-value">{{ $s->trn }}</span></div>@endif
                        @if($s->sponsorship_start_date)<div class="field-group"><span class="field-label">Start:</span><span class="field-value">{{ $s->sponsorship_start_date->format('d/m/Y') }}</span></div>@endif
                        @if($s->sponsorship_end_date)<div class="field-group"><span class="field-label">End:</span><span class="field-value">{{ $s->sponsorship_end_date->format('d/m/Y') }}</span></div>@endif
                        @if($s->regional_sponsorship)<div class="field-group"><span class="field-label">Regional:</span><span class="field-value">Yes</span></div>@endif
                        @if($s->adverse_information)<div class="field-group"><span class="field-label">Adverse information:</span><span class="field-value">Yes</span></div>@endif
                        @if($s->previous_sponsorship_notes)<div class="field-group" style="grid-column: 1 / -1;"><span class="field-label">Notes:</span><span class="field-value">{{ $s->previous_sponsorship_notes }}</span></div>@endif
                    </div>
                </div>
                @endforeach
            </div>
        @elseif($comp && ($comp->sponsorship_type || $comp->sponsorship_status || $comp->trn))
        <div class="card sponsorship-details-card sponsorship-details-card--single" style="margin-bottom: 20px;">
            <h3>@icon('fa-file-contract') Sponsorship</h3>
            <div class="sponsorship-details-fields">
                @if($comp->sponsorship_type)<div class="field-group"><span class="field-label">Type:</span><span class="field-value">{{ $comp->sponsorship_type }}</span></div>@endif
                @if($comp->sponsorship_status)<div class="field-group"><span class="field-label">Status:</span><span class="field-value">{{ $comp->sponsorship_status }}</span></div>@endif
                @if($comp->trn)<div class="field-group"><span class="field-label">TRN:</span><span class="field-value">{{ $comp->trn }}</span></div>@endif
                @if($comp->sponsorship_start_date)<div class="field-group"><span class="field-label">Start:</span><span class="field-value">{{ $comp->sponsorship_start_date->format('d/m/Y') }}</span></div>@endif
                @if($comp->sponsorship_end_date)<div class="field-group"><span class="field-label">End:</span><span class="field-value">{{ $comp->sponsorship_end_date->format('d/m/Y') }}</span></div>@endif
            </div>
        </div>
        @endif

        {{-- Directors Card --}}
        @if($comp && $comp->directors->isNotEmpty())
        <div class="card" style="margin-bottom: 20px;">
            <h3>@icon('fa-users-cog') Directors</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                @foreach($comp->directors as $dir)
                @php $dirName = $dir->directorClient ? trim($dir->directorClient->first_name.' '.$dir->directorClient->last_name) : ($dir->director_name ?? ''); @endphp
                <div class="field-group"><span class="field-label">{{ $dirName }}</span><span class="field-value">{{ $dir->director_role ?? '' }}@if($dir->director_dob) (DOB: {{ $dir->director_dob->format('d/m/Y') }})@endif</span></div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Financial Card (multiple years + legacy columns fallback) --}}
        @php
            $financialDetailRows = ($comp && $comp->relationLoaded('financials') && $comp->financials->isNotEmpty()) ? $comp->financials : collect();
            if ($financialDetailRows->isEmpty() && $comp && (($comp->annual_turnover ?? null) !== null || ($comp->wages_expenditure ?? null) !== null)) {
                $financialDetailRows = collect([(object) [
                    'financial_year' => null,
                    'annual_turnover' => $comp->annual_turnover,
                    'wages_expenditure' => $comp->wages_expenditure,
                ]]);
            }
        @endphp
        @if($financialDetailRows->isNotEmpty())
        <div class="card" style="margin-bottom: 20px;">
            <h3>@icon('fa-dollar-sign') Financial</h3>
            @foreach($financialDetailRows as $f)
            <div style="display: flex; flex-wrap: wrap; align-items: baseline; column-gap: 1.75rem; row-gap: 0.35rem; margin-top: {{ $loop->first ? '15px' : '12px' }}; padding-bottom: 12px;{{ !$loop->last ? ' border-bottom: 1px solid #eee;' : '' }}">
                @if(!empty($f->financial_year))
                <div style="display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem;"><span class="field-label">Financial Year:</span><span class="field-value">{{ $f->financial_year }}</span></div>
                @endif
                @if($f->annual_turnover !== null && $f->annual_turnover !== '')
                <div style="display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem;"><span class="field-label">Annual Turnover:</span><span class="field-value">${{ number_format((float) $f->annual_turnover, 2) }}</span></div>
                @endif
                @if($f->wages_expenditure !== null && $f->wages_expenditure !== '')
                <div style="display: inline-flex; flex-wrap: wrap; align-items: baseline; gap: 0.35rem;"><span class="field-label">Wages Expenditure:</span><span class="field-value">${{ number_format((float) $f->wages_expenditure, 2) }}</span></div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Workforce Card --}}
        @php $hasWorkforce = $comp && ($comp->workforce_australian_citizens !== null || $comp->workforce_permanent_residents !== null || $comp->workforce_temp_visa_holders !== null || $comp->workforce_total !== null || $comp->workforce_foreign_494 !== null || $comp->workforce_foreign_other_temp_activity !== null || $comp->workforce_foreign_overseas_students !== null || $comp->workforce_foreign_working_holiday !== null || $comp->workforce_foreign_other !== null); @endphp
        @if($hasWorkforce)
        <div class="card" style="margin-bottom: 20px;">
            <h3>@icon('fa-users') Workforce</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px;">
                @if($comp->workforce_australian_citizens !== null)<div class="field-group"><span class="field-label">workforce_aus_professionals:</span><span class="field-value">{{ $comp->workforce_australian_citizens }}</span></div>@endif
                @if($comp->workforce_permanent_residents !== null)<div class="field-group"><span class="field-label">workforce_aus_tradespersons:</span><span class="field-value">{{ $comp->workforce_permanent_residents }}</span></div>@endif
                @if($comp->workforce_temp_visa_holders !== null)<div class="field-group"><span class="field-label">workforce_aus_employment_other:</span><span class="field-value">{{ $comp->workforce_temp_visa_holders }}</span></div>@endif
                @if($comp->workforce_total !== null)<div class="field-group"><span class="field-label">workforce_foreign_482_457:</span><span class="field-value">{{ $comp->workforce_total }}</span></div>@endif
                @if($comp->workforce_foreign_494 !== null)<div class="field-group"><span class="field-label">workforce_foreign_494:</span><span class="field-value">{{ $comp->workforce_foreign_494 }}</span></div>@endif
                @if($comp->workforce_foreign_other_temp_activity !== null)<div class="field-group"><span class="field-label">workforce_foreign_other_temp_activity:</span><span class="field-value">{{ $comp->workforce_foreign_other_temp_activity }}</span></div>@endif
                @if($comp->workforce_foreign_overseas_students !== null)<div class="field-group"><span class="field-label">workforce_foreign_overseas_students:</span><span class="field-value">{{ $comp->workforce_foreign_overseas_students }}</span></div>@endif
                @if($comp->workforce_foreign_working_holiday !== null)<div class="field-group"><span class="field-label">workforce_foreign_working_holiday:</span><span class="field-value">{{ $comp->workforce_foreign_working_holiday }}</span></div>@endif
                @if($comp->workforce_foreign_other !== null)<div class="field-group"><span class="field-label">workforce_foreign_other:</span><span class="field-value">{{ $comp->workforce_foreign_other }}</span></div>@endif
            </div>
        </div>
        @endif

        {{-- Operations Card --}}
        @if($comp && ($comp->business_operating_since || $comp->main_business_activity))
        <div class="card" style="margin-bottom: 20px;">
            <h3>@icon('fa-briefcase') Operations</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                @if($comp->business_operating_since)<div class="field-group"><span class="field-label">Operating Since:</span><span class="field-value">{{ $comp->business_operating_since->format('d/m/Y') }}</span></div>@endif
                @if($comp->main_business_activity)<div class="field-group"><span class="field-label">Main Activity:</span><span class="field-value">{{ $comp->main_business_activity }}</span></div>@endif
            </div>
        </div>
        @endif

        {{-- Training Card --}}
        @if($comp && ($comp->training_position_title || $comp->trainer_name))
        <div class="card" style="margin-bottom: 20px;">
            <h3>@icon('fa-graduation-cap') Training</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                @if($comp->training_position_title)<div class="field-group"><span class="field-label">Position Title:</span><span class="field-value">{{ $comp->training_position_title }}</span></div>@endif
                @if($comp->trainer_name)<div class="field-group"><span class="field-label">Trainer Name:</span><span class="field-value">{{ $comp->trainer_name }}</span></div>@endif
            </div>
        </div>
        @endif

        {{-- Nominations Card --}}
        @if($comp && $comp->nominations->isNotEmpty())
            @php $nominationCount = $comp->nominations->count(); @endphp
            <div class="card nomination-details-card{{ $nominationCount === 1 ? ' nomination-details-card--single' : '' }}" style="margin-bottom: 20px;">
                <h3>@icon('fa-user-check') {{ $nominationCount > 1 ? 'Nominations' : 'Nomination' }}</h3>
                @foreach($comp->nominations as $idx => $nom)
                @php
                    $nomineeLabel = $nom->nominatedClient
                        ? trim($nom->nominatedClient->first_name.' '.$nom->nominatedClient->last_name)
                        : ($nom->nominated_person_name ?? null);
                    $mayOpenNominee = $nom->nominatedClient
                        && \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $nom->nominatedClient->id, auth()->user());
                @endphp
                <div class="{{ $nominationCount > 1 ? 'nomination-details-block' : '' }}">
                    @if($nominationCount > 1)
                    <p class="nomination-details-subheading">Nomination {{ $idx + 1 }}</p>
                    @endif
                    <div class="nomination-details-fields">
                        <div class="field-group">
                            <span class="field-label">Position:</span>
                            <span class="field-value">{{ $nom->position_title ?: 'N/A' }}</span>
                        </div>
                        <div class="field-group">
                            <span class="field-label">{{ $nom->trn ? 'Nominate Person:' : 'Person:' }}</span>
                            <span class="field-value">
                                @if($nomineeLabel)
                                    @if($mayOpenNominee)
                                        <a href="{{ route('clients.detail', base64_encode(convert_uuencode($nom->nominatedClient->id))) }}"
                                           style="color: #007bff; text-decoration: none;"
                                           title="Open client profile">
                                            {{ $nomineeLabel }}
                                        </a>
                                    @else
                                        {{ $nomineeLabel }}
                                    @endif
                                    @if($nom->trn) (TRN: {{ $nom->trn }})@endif
                                @else
                                    {{ $nom->trn ? '(TRN: '.$nom->trn.')' : 'N/A' }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        @endif

        {{-- Company Phone & Email Card --}}
        <div class="card" style="margin-bottom: 20px;">
            <h3>@icon('fa-phone') Contact Information</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                {{-- Company Phone Number --}}
                <div class="field-group">
                    <span class="field-label">Phone:</span>
                    <span class="field-value">
                        <?php
                        if( \App\Models\ClientContact::where('client_id', $fetchedData->id)->exists()) {
                            $companyContacts = \App\Models\ClientContact::select('phone','country_code','contact_type','is_verified','verified_at')
                                ->where('client_id', $fetchedData->id)
                                ->where('contact_type', '!=', 'Not In Use')
                                ->get();
                        } else {
                            if( \App\Models\Admin::where('id', $fetchedData->id)->exists()){
                                $companyContacts = \App\Models\Admin::select('phone','country_code','contact_type')
                                    ->where('id', $fetchedData->id)
                                    ->get();
                            } else {
                                $companyContacts = [];
                            }
                        }
                        if( !empty($companyContacts) && count($companyContacts)>0 ){
                            $phonenoStr = "";
                            foreach($companyContacts as $conKey=>$conVal){
                                $check_verified_phoneno = $conVal->country_code."".$conVal->phone;
                                if( isset($conVal->country_code) && $conVal->country_code != "" ){
                                    $country_code = $conVal->country_code;
                                } else {
                                    $country_code = "";
                                }

                                // Format phone number to Australian standard
                                $formattedPhone = \App\Helpers\PhoneValidationHelper::formatAustralianPhone($conVal->phone, $country_code);

                                if( isset($conVal->contact_type) && $conVal->contact_type != "" ){
                                    if ( $conVal->is_verified ) {
                                        $phonenoStr .= $formattedPhone.' ' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . ' <br/>';
                                    } else {
                                        $phonenoStr .= $formattedPhone.' ' . \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>';
                                    }
                                } else {
                                    if ( isset($conVal->is_verified) && $conVal->is_verified ) {
                                        $phonenoStr .= $formattedPhone.' ' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . ' <br/>';
                                    } else {
                                        $phonenoStr .= $formattedPhone.' ' . \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>';
                                    }
                                }
                            }
                            echo $phonenoStr;
                        } else {
                            echo "N/A";
                        }?>
                    </span>
                </div>

                {{-- Company Email Address --}}
                <div class="field-group">
                    <span class="field-label">Email:</span>
                    <span class="field-value">
                        <?php
                        if( \App\Models\ClientEmail::where('client_id', $fetchedData->id)->exists()) {
                            $companyEmails = \App\Models\ClientEmail::select('email','email_type','is_verified','verified_at')
                                ->where('client_id', $fetchedData->id)
                                ->get();
                        } else {
                            if( \App\Models\Admin::where('id', $fetchedData->id)->exists()){
                                $companyEmails = \App\Models\Admin::select('email','email_type')
                                    ->where('id', $fetchedData->id)
                                    ->get();
                            } else {
                                $companyEmails = [];
                            }
                        }
                        if( !empty($companyEmails) && count($companyEmails)>0 ){
                            $emailStr = "";
                            foreach($companyEmails as $emailKey=>$emailVal){
                                $check_verified_email = $emailVal->email_type."".$emailVal->email;
                                if( isset($emailVal->email_type) && $emailVal->email_type != "" ){
                                    if ( $emailVal->is_verified ) {
                                        $emailStr .= $emailVal->email.' ' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . ' <br/>';
                                    } else {
                                        $emailStr .= $emailVal->email.' ' . \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>';
                                    }
                                } else {
                                    if ( isset($emailVal->is_verified) && $emailVal->is_verified ) {
                                        $emailStr .= $emailVal->email.' ' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . ' <br/>';
                                    } else {
                                        $emailStr .= $emailVal->email.' ' . \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>';
                                    }
                                }
                            }
                            echo $emailStr;
                        } else {
                            echo "N/A";
                        }?>
                    </span>
                </div>
            </div>
        </div>

        @include('crm.clients.partials.matter_assignee_card')
        
        {{-- Primary Contact Person Card --}}
        @if($fetchedData->company->contactPerson)
            <div class="card" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>@icon('fa-user-tie') Primary Contact Person</h3>
                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($fetchedData->company->contactPerson->id))) }}" 
                       class="btn btn-sm btn-outline-primary">
                        @icon('fa-external-link-alt') View Profile
                    </a>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div class="field-group">
                        <span class="field-label">Name:</span>
                        <span class="field-value">
                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($fetchedData->company->contactPerson->id))) }}" 
                               style="color: #007bff; text-decoration: none;">
                                {{ $fetchedData->company->contactPerson->first_name }} {{ $fetchedData->company->contactPerson->last_name }}
                            </a>
                        </span>
                    </div>
                    @if($fetchedData->company->contact_person_position)
                    <div class="field-group">
                        <span class="field-label">Position:</span>
                        <span class="field-value">{{ $fetchedData->company->contact_person_position }}</span>
                    </div>
                    @endif
                    @if($fetchedData->company->contactPerson->email)
                    <div class="field-group">
                        <span class="field-label">Email:</span>
                        <span class="field-value">
                            <a href="mailto:{{ $fetchedData->company->contactPerson->email }}" style="color: #007bff; text-decoration: none;">
                                {{ $fetchedData->company->contactPerson->email }}
                            </a>
                        </span>
                    </div>
                    @endif
                    @if($fetchedData->company->contactPerson->phone)
                    <div class="field-group">
                        <span class="field-label">Phone:</span>
                        <span class="field-value">{{ $fetchedData->company->contactPerson->phone }}</span>
                    </div>
                    @endif
                    @if($fetchedData->company->contactPerson->client_id)
                    <div class="field-group">
                        <span class="field-label">Client ID:</span>
                        <span class="field-value">{{ $fetchedData->company->contactPerson->client_id }}</span>
                    </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Tags Section --}}
        <div class="card">
            <h3>@icon('fa-address-card') Tag(s):   
                <span class="float-right text-muted" style="margin-left:180px;">
                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-primary opentagspopup btn-sm">Add Tag</a>
                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-outline-danger openredtagspopup btn-sm ml-1" title="Add Tag (hidden by default)">
                    @icon('fa-exclamation-triangle') Add Tag
                </a>
                </span>
            </h3>
           

            <div class="" style="overflow-wrap: break-word; word-wrap: break-word; max-width: 100%;">
                <?php 
                $normalTags = [];
                $redTags = [];
                $redTagCount = 0;
                
                if($fetchedData->tagname != ''){
                    $rs = explode(',', $fetchedData->tagname);
                    
                    // Separate IDs and names for bulk query optimization
                    $tagIds = [];
                    $tagNames = [];
                    
                    foreach($rs as $key=>$r){
                        $r = trim($r);
                        if (empty($r)) continue;
                        
                        // Separate numeric IDs from tag names
                        if (is_numeric($r) && $r > 0) {
                            $tagIds[] = (int)$r;
                        } else {
                            $tagNames[] = $r;
                        }
                    }
                    
                    // Bulk fetch tags by IDs (single query for all IDs)
                    $tagsByIds = [];
                    if (!empty($tagIds)) {
                        $tagsByIds = \App\Models\Tag::whereIn('id', $tagIds)->get()->keyBy('id');
                    }
                    
                    // Bulk fetch tags by names (single query for all names)
                    $tagsByNames = [];
                    if (!empty($tagNames)) {
                        $tagsByNames = \App\Models\Tag::whereIn('name', $tagNames)->get()->keyBy('name');
                    }
                    
                    // Process all tags and categorize them
                    foreach($rs as $key=>$r){
                        $r = trim($r);
                        if (empty($r)) continue;
                        
                        $stagd = null;
                        
                        // Try to get tag by ID first
                        if (is_numeric($r) && $r > 0) {
                            $stagd = $tagsByIds[(int)$r] ?? null;
                        }
                        
                        // If not found by ID, try by name
                        if (!$stagd) {
                            $stagd = $tagsByNames[$r] ?? null;
                        }
                        
                        // Categorize tag if found
                        if($stagd) {
                            if($stagd->tag_type == 'red') {
                                $redTags[] = $stagd;
                                $redTagCount++;
                            } else {
                                $normalTags[] = $stagd;
                            }
                        }
                    }
                }
                
                // Display normal tags
                foreach($normalTags as $tag) { ?>
                    <span class="ui label tag-normal ag-flex ag-align-center ag-space-between" style="display: inline-flex; margin: 5px 5px 5px 0;">
                        <span class="col-hr-1" style="font-size: 12px;">{{@$tag->name}}</span>
                    </span>
                <?php }
                
                // Display red tags section (hidden by default)
                if($redTagCount > 0) { ?>
                    <div class="red-tags-section" style="display: none; margin-top: 10px;">
                        <div style="margin-bottom: 5px; font-size: 11px; color: #dc3545; font-weight: bold;">
                            @icon('fa-exclamation-triangle') Red Tags:
                        </div>
                        <?php foreach($redTags as $tag) { ?>
                            <span class="ui label tag-red ag-flex ag-align-center ag-space-between" style="display: inline-flex; margin: 5px 5px 5px 0; background-color: #dc3545; border: 1px solid #c82333;">
                                <span class="col-hr-1" style="font-size: 12px;">{{@$tag->name}}</span>
                            </span>
                        <?php } ?>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <a href="javascript:;" id="toggleRedTags" class="btn btn-sm btn-outline-danger" data-client-id="{{$fetchedData->id}}" title="Show Red Tags">
                            @icon('fa-eye')
                        </a>
                    </div>
                <?php }
                ?>
            </div>
        </div>
        <style>
            .ui.label:first-child {
                margin-left: 0;
            }
            .ui.label {
                display: inline-block;
                line-height: 1;
                vertical-align: baseline;
                margin: 0 0.14285714em;
                background-color: #6777ef;
                background-image: none;
                padding: 0.5833em 0.833em;
                color: #fff;
                text-transform: none;
                font-weight: 700;
                border: 0 solid transparent;
                border-radius: 0.28571429rem;
                -webkit-transition: background .1s ease;
                transition: background .1s ease;
            }
            .ui.label.tag-red {
                background-color: #dc3545 !important;
                border: 1px solid #c82333 !important;
                color: #fff !important;
            }
            .ui.label.tag-normal {
                background-color: #6777ef;
            }
            .ag-align-center {
                align-items: center;
            }
            .ag-space-between {
                justify-content: space-between;
            }
            .col-hr-1 {
                margin-right: 5px !important;
            }
            .red-tags-section {
                padding: 10px;
                background-color: #fff5f5;
                border-left: 3px solid #dc3545;
                border-radius: 4px;
                margin-top: 10px;
            }
            #toggleRedTags {
                transition: all 0.3s ease;
            }
            #toggleRedTags:hover {
                transform: translateY(-1px);
                box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
            }
        </style>
    </div>
</div>

<!-- Red Tags Toggle JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Red Tags Toggle Functionality
    const toggleRedTagsBtn = document.getElementById('toggleRedTags');
    const redTagsSection = document.querySelector('.red-tags-section');
    
    if (toggleRedTagsBtn && redTagsSection) {
        // Store toggle state in sessionStorage
        const storageKey = 'redTagsVisible_' + toggleRedTagsBtn.getAttribute('data-client-id');
        const isVisible = sessionStorage.getItem(storageKey) === 'true';
        
        // Set initial state
        if (isVisible) {
            redTagsSection.style.display = 'block';
            toggleRedTagsBtn.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-eye-slash') : '<i class="fas fa-eye-slash"></i>');
            toggleRedTagsBtn.classList.remove('btn-outline-danger');
            toggleRedTagsBtn.classList.add('btn-danger');
            toggleRedTagsBtn.title = 'Hide Red Tags';
        }
        
        toggleRedTagsBtn.addEventListener('click', function() {
            const isCurrentlyVisible = redTagsSection.style.display !== 'none';
            
            if (isCurrentlyVisible) {
                // Hide red tags
                redTagsSection.style.display = 'none';
                this.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-eye') : '<i class="fas fa-eye"></i>');
                this.classList.remove('btn-danger');
                this.classList.add('btn-outline-danger');
                this.title = 'Show Red Tags';
                sessionStorage.setItem(storageKey, 'false');
            } else {
                // Show red tags
                redTagsSection.style.display = 'block';
                this.innerHTML = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-eye-slash') : '<i class="fas fa-eye-slash"></i>');
                this.classList.remove('btn-outline-danger');
                this.classList.add('btn-danger');
                this.title = 'Hide Red Tags';
                sessionStorage.setItem(storageKey, 'true');
            }
        });
    }
});
</script>
