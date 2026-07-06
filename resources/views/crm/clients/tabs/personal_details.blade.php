<div class="tab-pane active" id="personaldetails-tab">
                <div class="content-grid">
                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h3>@icon('fa-user') Personal Information</h3>
                        </div>
                        <div class="field-group">
                            <span class="field-label">Age / Date of Birth</span>
                            <span class="field-value">
                                <?php
                                if ( isset($fetchedData->age) && $fetchedData->age != '') {
                                    $verifiedDob = \App\Models\Admin::where('id',$fetchedData->id)->whereNotNull('dob_verified_date')->first();
                                    if ( $verifiedDob) {
                                        $verifiedDobTick = \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg']);
                                    } else {
                                        $verifiedDobTick = \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg']);
                                    }
                                    
                                    // Format DOB for display
                                    $formattedDob = 'N/A';
                                    if (isset($fetchedData->dob) && $fetchedData->dob != '') {
                                        try {
                                            $dobDate = \Carbon\Carbon::parse($fetchedData->dob);
                                            $formattedDob = $dobDate->format('d M Y'); // e.g., "15 Jan 2001"
                                        } catch (\Exception $e) {
                                            $formattedDob = 'N/A';
                                        }
                                    }
                                    ?>
                                    <span id="ageDobToggle" style="cursor: pointer;" 
                                          data-age="<?php echo htmlspecialchars($fetchedData->age); ?>" 
                                          data-dob="<?php echo htmlspecialchars($formattedDob); ?>">
                                        <span class="display-age"><?php echo $fetchedData->age; ?></span>
                                        <span class="display-dob" style="display: none;"><?php echo $formattedDob; ?></span>
                                        <?php echo $verifiedDobTick; ?>
                                    </span>
                                <?php
                                } else {
                                    echo 'N/A';
                                } ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Gender</span>
                            <span class="field-value">
                                <?php
                                if ( isset($fetchedData->gender) && $fetchedData->gender != '') {
                                    echo $fetchedData->gender;
                                } else {
                                    echo 'N/A';
                                } ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Marital Status</span>
                            <span class="field-value">
                                <?php
                                if ( isset($fetchedData->marital_status) && $fetchedData->marital_status != '') {
                                    echo $fetchedData->marital_status;
                                } else {
                                    echo 'N/A';
                                } ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Client Email</span>
                            <span class="field-value">
                                <?php
                                $clientEmails = ($emails ?? collect())->isNotEmpty()
                                    ? ($emails ?? collect())
                                    : collect([(object) [
                                        'email' => $fetchedData->email ?? null,
                                        'email_type' => $fetchedData->email_type ?? null,
                                        'is_verified' => $fetchedData->is_verified ?? null,
                                        'verified_at' => $fetchedData->verified_at ?? null,
                                    ]])->filter(function ($row) {
                                        return ! empty($row->email);
                                    });
                                if( !empty($clientEmails) && count($clientEmails)>0 ){
                                    $emailStr = "";
                                    foreach($clientEmails as $emailKey=>$emailVal){

                                        //Check email is verified or not
                                        $check_verified_email = $emailVal->email_type."".$emailVal->email;
                                        if( isset($emailVal->email_type) && $emailVal->email_type != "" ){
                                            // Show verification status for ALL email types
                                            if ( $emailVal->is_verified ) {
                                                $emailStr .= $emailVal->email.' ' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($emailVal->verified_at ? $emailVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . ' <br/>';
                                            } else {
                                                $emailStr .= $emailVal->email.' ' . \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>';
                                            }
                                        } else {
                                            // For emails without type, still show verification status if available
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

                        <div class="field-group">
                            <span class="field-label">Client Phone</span>
                            <span class="field-value">
                                <?php
                                $clientContacts = ($personalDetailContacts ?? collect())->isNotEmpty()
                                    ? ($personalDetailContacts ?? collect())
                                    : collect([(object) [
                                        'phone' => $fetchedData->phone ?? null,
                                        'country_code' => $fetchedData->country_code ?? null,
                                        'contact_type' => $fetchedData->contact_type ?? null,
                                        'is_verified' => $fetchedData->is_verified ?? null,
                                        'verified_at' => $fetchedData->verified_at ?? null,
                                    ]])->filter(function ($row) {
                                        return ! empty($row->phone);
                                    });
                                if( !empty($clientContacts) && count($clientContacts)>0 ){
                                    $phonenoStr = "";
                                    foreach($clientContacts as $conKey=>$conVal){
                                        //Check phone is verified or not
                                        $check_verified_phoneno = $conVal->country_code."".$conVal->phone;
                                        if( isset($conVal->country_code) && $conVal->country_code != "" ){
                                            $country_code = $conVal->country_code;
                                        } else {
                                            $country_code = "";
                                        }

                                        // Format phone number to Australian standard
                                        $formattedPhone = \App\Helpers\PhoneValidationHelper::formatAustralianPhone($conVal->phone, $country_code);

                                        if( isset($conVal->contact_type) && $conVal->contact_type != "" ){
                                            // Show verification status for ALL contact types
                                            if ( $conVal->is_verified ) {
                                                $phonenoStr .= $formattedPhone.' ' . \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . ' <br/>';
                                            } else {
                                                $phonenoStr .= $formattedPhone.' ' . \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>';
                                            }
                                        } else {
                                            // For phones without type, still show verification status if available
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

                        <?php
                        $address_Info = App\Models\ClientAddress::select('address','suburb','country','zip','regional_code')
                            ->where('client_id', $fetchedData->id)
                            ->where('is_current', 1)
                            ->first();
                        if (!$address_Info) {
                            $address_Info = App\Models\ClientAddress::select('address','suburb','country','zip','regional_code')
                                ->where('client_id', $fetchedData->id)
                                ->latest('id')
                                ->first();
                        }
                        ?>

                        <div class="field-group">
                            <span class="field-label">Address</span>
                            <span class="field-value">
                                <?php
                                if($address_Info) {
                                    $addressParts = array_filter([
                                        $address_Info->suburb ?? '',
                                        $address_Info->country ?? '',
                                        $address_Info->zip ?? ''
                                    ]);

                                    if (!empty($addressParts)) {
                                        echo implode(', ', $addressParts);
                                    } elseif (!empty($address_Info->address)) {
                                        echo $address_Info->address;
                                    } else {
                                        echo 'N/A';
                                    }
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>

                        <?php if($address_Info && $address_Info->regional_code): ?>
                        <div class="field-group">
                            <span class="field-label">Regional Classification</span>
                            <span class="field-value">
                                <?php echo $address_Info->regional_code; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    @php
                        $primaryContactCompaniesList = $primaryContactCompaniesForClient ?? collect();
                    @endphp
                    @if($primaryContactCompaniesList->isNotEmpty())
                    <div class="card">
                        <h3>@icon('fa-building') Company Detail</h3>
                        <p style="margin: 0 0 12px 0; color: #6c757d; font-size: 0.9rem;">
                            This person is the primary contact for the following company record(s). Only companies you are allowed to open are shown.
                        </p>
                        @foreach($primaryContactCompaniesList as $pcCompany)
                            @php
                                $pcAdminId = (int) ($pcCompany->admin_id ?? 0);
                                $pcTrading = $pcCompany->tradingNames?->isNotEmpty()
                                    ? $pcCompany->tradingNames->pluck('trading_name')->join(', ')
                                    : ($pcCompany->trading_name ?? null);
                            @endphp
                            <div style="padding-bottom: 14px; margin-bottom: 14px; {{ !$loop->last ? 'border-bottom: 1px dotted #dee2e6;' : '' }}">
                                <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 10px;">
                                    @if($pcAdminId > 0)
                                        <a href="{{ route('clients.detail', base64_encode(convert_uuencode($pcAdminId))) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            @icon('fa-external-link-alt') View company profile
                                        </a>
                                    @endif
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Company name</span>
                                    <span class="field-value">
                                        @if($pcAdminId > 0)
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($pcAdminId))) }}"
                                               style="color: #007bff; text-decoration: none;"
                                               title="Open company profile">
                                                {{ $pcCompany->company_name ?? 'N/A' }}
                                            </a>
                                        @else
                                            {{ $pcCompany->company_name ?? 'N/A' }}
                                        @endif
                                    </span>
                                </div>
                                @if($pcTrading)
                                <div class="field-group">
                                    <span class="field-label">Trading name(s)</span>
                                    <span class="field-value">{{ $pcTrading }}</span>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @endif

                    @php
                        $nomineeNominationsList = $visibleNomineeNominations ?? collect();
                    @endphp
                    @if($nomineeNominationsList->isNotEmpty())
                    <div class="card">
                        <h3>@icon('fa-user-check') Nominated by employer</h3>
                        <p style="margin: 0 0 12px 0; color: #6c757d; font-size: 0.9rem;">
                            This client is listed as a nominated employee on the following company record(s). Only employers you are allowed to open are shown.
                        </p>
                        @foreach($nomineeNominationsList as $nomRow)
                            @php
                                $nomCompany = $nomRow->company;
                                $companyAdminId = $nomCompany?->admin_id;
                            @endphp
                            <div style="padding-bottom: 14px; margin-bottom: 14px; {{ !$loop->last ? 'border-bottom: 1px dotted #dee2e6;' : '' }}">
                                <div class="field-group">
                                    <span class="field-label">Company</span>
                                    <span class="field-value">
                                        @if($nomCompany && $companyAdminId)
                                            <a href="{{ route('clients.detail', base64_encode(convert_uuencode($companyAdminId))) }}"
                                               style="color: #007bff; text-decoration: none;"
                                               title="Open company profile">
                                                {{ $nomCompany->company_name ?? 'Company' }}
                                            </a>
                                        @else
                                            {{ $nomCompany->company_name ?? 'Unknown company' }}
                                        @endif
                                    </span>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">ABN</span>
                                    <span class="field-value">{{ ($nomCompany && !empty($nomCompany->ABN_number)) ? $nomCompany->ABN_number : 'N/A' }}</span>
                                </div>
                                @php
                                    $nominationTrnDisplay = trim((string) ($nomRow->trn ?? ''));
                                @endphp
                                <div class="field-group">
                                    <span class="field-label">TRN</span>
                                    <span class="field-value">{{ $nominationTrnDisplay !== '' ? $nominationTrnDisplay : 'N/A' }}</span>
                                </div>
                                @if($nomRow->position_title)
                                <div class="field-group">
                                    <span class="field-label">Position</span>
                                    <span class="field-value">{{ $nomRow->position_title }}</span>
                                </div>
                                @endif
                                @if($nomRow->nomination_date)
                                <div class="field-group">
                                    <span class="field-label">Nomination date</span>
                                    <span class="field-value">{{ $nomRow->nomination_date->format('d/m/Y') }}</span>
                                </div>
                                @endif
                                @if($nomRow->expiry_date)
                                <div class="field-group">
                                    <span class="field-label">Expiry date</span>
                                    <span class="field-value">{{ $nomRow->expiry_date->format('d/m/Y') }}</span>
                                </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @endif


                    <div class="card">
                        <h3>@icon('fa-passport')Visa</h3>
                        <?php
                        // Get visa with latest expiry date
                        $visa_Info_with_expiry = App\Models\ClientVisaCountry::select('visa_type','visa_expiry_date','visa_grant_date','visa_description')
                            ->where('client_id', $fetchedData->id)
                            ->whereNotNull('visa_expiry_date')
                            ->orderBy('visa_expiry_date', 'desc')
                            ->first();
                        
                        // Get all visas without expiry date
                        $visas_without_expiry = App\Models\ClientVisaCountry::select('visa_type','visa_expiry_date','visa_grant_date','visa_description')
                            ->where('client_id', $fetchedData->id)
                            ->whereNull('visa_expiry_date')
                            ->get();
                        
                        // Combine both: visa with expiry first, then visas without expiry
                        $all_visas_to_display = collect();
                        if($visa_Info_with_expiry) {
                            $all_visas_to_display->push($visa_Info_with_expiry);
                        }
                        $all_visas_to_display = $all_visas_to_display->merge($visas_without_expiry);
                        ?>
                        
                        <?php if($all_visas_to_display->count() > 0): ?>
                            <?php foreach($all_visas_to_display as $visaIndex => $visa_Info): ?>
                                <?php if($visaIndex > 0): ?>
                                    <hr style="margin: 15px 0; border-top: 1px solid #dee2e6;">
                                <?php endif; ?>
                                
                                <div class="field-group">
                                    <span class="field-label">Visa Type</span>
                                    <span class="field-value">
                                        <?php
                                        if( $visa_Info && $visa_Info->visa_type != "" ){
                                            $Matter_get = App\Models\Matter::select('id','title','nick_name')->where('id',$visa_Info->visa_type)->first();
                                            if(!empty($Matter_get)){
                                                echo $Matter_get->title.'('.$Matter_get->nick_name.')';
                                            } else {
                                                echo 'N/A';
                                            }
                                        } else { echo 'N/A'; }
                                        ?>
                                    </span>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Visa Expiry Date</span>
                                    <span class="field-value">
                                        <?php
                                        if( $visa_Info && !empty($visa_Info->visa_expiry_date)){
                                            $verifiedVisa = \App\Models\Admin::where('id',$fetchedData->id)->whereNotNull('visa_expiry_verified_at')->first();
                                            if ( $verifiedVisa) {
                                                $verifiedVisaTick = \App\Helpers\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg']);
                                            } else {
                                                $verifiedVisaTick = \App\Helpers\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg']);
                                            }
                                            
                                            // Check if visa is expiring within 7 days (calendar days; avoids fractional diffInDays from time-of-day)
                                            $expiryDate = \Carbon\Carbon::parse($visa_Info->visa_expiry_date)->startOfDay();
                                            $today = \Carbon\Carbon::now()->startOfDay();
                                            $daysUntilExpiry = (int) $today->diffInDays($expiryDate, false);
                                            
                                            $expiryClass = '';
                                            $expiryWarning = '';
                                            if ($daysUntilExpiry <= 7 && $daysUntilExpiry >= 0) {
                                                $expiryClass = ' style="color: #dc3545; font-weight: bold;"';
                                                $expiryWarning = ' data-expiry-warning="true" data-days-left="' . $daysUntilExpiry . '"';
                                            }
                                            
                                            echo '<span' . $expiryClass . $expiryWarning . '>' . $expiryDate->format('d/m/Y') . '</span> ' . $verifiedVisaTick;
                                        } else { 
                                            echo 'No Expiry Date'; 
                                        }
                                        ?>
                                    </span>
                                </div>
                                @if($visa_Info->visa_grant_date && !empty($visa_Info->visa_grant_date))
                                <div class="field-group">
                                    <span class="field-label">Visa Grant Date</span>
                                    <span class="field-value">
                                        <?php 
                                        $grantDate = \Carbon\Carbon::parse($visa_Info->visa_grant_date);
                                        echo $grantDate->format('d/m/Y'); 
                                        ?>
                                    </span>
                                </div>
                                @endif
                                @if($visa_Info->visa_description != "")
                                <div class="field-group">
                                    <span class="field-label">Visa Description</span>
                                    <span class="field-value">
                                        <?php echo $visa_Info->visa_description; ?>
                                    </span>
                                </div>
                                @endif
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="field-group">
                                <span class="field-label">Visa Type</span>
                                <span class="field-value">N/A</span>
                            </div>
                            <div class="field-group">
                                <span class="field-label">Visa Expiry Date</span>
                                <span class="field-value">N/A</span>
                            </div>
                        <?php endif; ?>
                        <div class="field-group">
                            <span class="field-label">Country Of Passport</span>
                            <span class="field-value">
                                <?php
                                if( isset($fetchedData->country_passport) && $fetchedData->country_passport != "" ){ 
                                    echo $fetchedData->country_passport; 
                                } else { 
                                    echo 'N/A'; 
                                }
                                ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">Nomi Occupation / Code / Assessing Authority</span>
                            <span class="field-value">
                                <?php
                                $clientOccupation_Info = App\Models\ClientOccupation::select('skill_assessment','nomi_occupation','occupation_code','list','visa_subclass','dates')->where('client_id', $fetchedData->id)->latest('id')->first();
                                if( $clientOccupation_Info && $clientOccupation_Info->nomi_occupation != "" ){ echo $clientOccupation_Info->nomi_occupation; } else { echo 'N/A'; }
                                ?>
                                <?php
                                if( $clientOccupation_Info && $clientOccupation_Info->occupation_code != "" ){ echo ' / '.$clientOccupation_Info->occupation_code; } else { echo ' / '.'N/A'; }
                                ?>
                                <?php
                                if( $clientOccupation_Info && $clientOccupation_Info->list != "" ){ echo ' / '.$clientOccupation_Info->list; } else { echo ' / '.'N/A'; }
                                ?>
                            </span>
                        </div>

                        <div class="field-group">
                            <span class="field-label">English Test Score</span>
                            <span class="field-value">
                                <?php
                                $clientTest_Info = App\Models\ClientTestScore::select('test_type','listening','reading','writing','speaking','overall_score','test_date')->where('client_id', $fetchedData->id)->latest('id')->first();
                                if( $clientTest_Info && $clientTest_Info->test_type != "" ){ echo $clientTest_Info->test_type.": "; } else { echo 'N/A'; }
                                ?>


                                <?php
                                if( $clientTest_Info && $clientTest_Info->listening != "" ){ echo "L".$clientTest_Info->listening; } else { echo 'N/A'; }
                                ?>
                                <?php
                                if( $clientTest_Info && $clientTest_Info->reading != "" ){ echo " R".$clientTest_Info->reading; } else { echo 'N/A'; }
                                ?>
                                <?php
                                if( $clientTest_Info && $clientTest_Info->writing != "" ){ echo " W".$clientTest_Info->writing; } else { echo 'N/A'; }
                                ?>

                                <?php
                                if( $clientTest_Info && $clientTest_Info->speaking != "" ){ echo " S".$clientTest_Info->speaking; } else { echo 'N/A'; }
                                ?>

                                <?php
                                if( $clientTest_Info && $clientTest_Info->overall_score != "" ){ echo " O".$clientTest_Info->overall_score; } else { echo 'N/A'; }
                                ?>
                            </span>
                        </div>
                    </div>


                    <?php
                    $clientQualification_Info = App\Models\ClientQualification::select('level','name','qual_campus','finish_date')->where('client_id', $fetchedData->id)->orderByRaw('finish_date DESC NULLS LAST')->get();
                    ?>
                    @if(!empty($clientQualification_Info) && $clientQualification_Info->count() > 0)
                    <div class="card">
                        <div class="qualification-section">
                            <h3>@icon('fa-info-circle') Qualification</h3>
                            <div class="qualification-list" style="overflow-x: auto;">
                                <table class="table eoi-table">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th>Name</th>
                                            <th>Campus</th>
                                            <th>End Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clientQualification_Info as $qualification)
                                            <tr>
                                                <td>{{ $qualification->level ?: 'N/A' }}</td>
                                                <td>{{ $qualification->name ?: 'N/A' }}</td>
                                                <td>{{ $qualification->qual_campus ?: 'N/A' }}</td>
                                                <td>{{ $qualification->finish_date ? \Carbon\Carbon::parse($qualification->finish_date)->format('d/m/Y') : 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <style>
                        /*.qualification-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                        }
                        .qualification-table th, .qualification-table td {
                            padding: 10px;
                            border-bottom: 1px solid #dee2e6;
                            text-align: left;
                        }
                        .qualification-table th {
                            background-color: #f8f9fa;
                            font-weight: 600;
                            color: #6c757d !important;
                        }
                        .qualification-table tbody tr:hover {
                            background-color: #f1f5f9;
                        }
                        .qualification-table td {
                            color: #212529;
                        }*/
                    </style>


                    <?php
                    $clientExperience_Info = App\Models\ClientExperience::select('job_title','job_country','job_start_date','job_finish_date')->where('client_id', $fetchedData->id)->orderByRaw('job_finish_date DESC NULLS LAST')->get();
                    ?>
                    @if(!empty($clientExperience_Info) && $clientExperience_Info->count() > 0)
                    <div class="card">
                        <div class="experience-section">
                            <h3>@icon('fa-info-circle') Work Experience</h3>
                            <div class="experience-list" style="overflow-x: auto;">
                                <table class="table eoi-table">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Country</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clientExperience_Info as $experience)
                                            <tr>
                                                <td>{{ $experience->job_title ?: 'N/A' }}</td>
                                                <td>{{ $experience->job_country ?: 'N/A' }}</td>
                                                <td>{{ $experience->job_start_date ? \Carbon\Carbon::parse($experience->job_start_date)->format('d/m/Y') : 'N/A' }}</td>
                                                <td>{{ $experience->job_finish_date ? \Carbon\Carbon::parse($experience->job_finish_date)->format('d/m/Y') : 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <style>
                       /* .experience-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                        }
                        .experience-table th, .experience-table td {
                            padding: 10px;
                            border-bottom: 1px solid #dee2e6;
                            text-align: left;
                        }
                        .experience-table th {
                            background-color: #f8f9fa;
                            font-weight: 600;
                            color: #6c757d !important;
                        }
                        .experience-table tbody tr:hover {
                            background-color: #f1f5f9;
                        }
                        .experience-table td {
                            color: #212529;
                        }*/
                    </style>



                    @if(!empty($clientFamilyDetails) && $clientFamilyDetails->count() > 0)
                    <div class="card">
                        <div class="relationship-section">
                            <h3>@icon('fa-address-card') Relationships</h3>
                            <div class="relationship-list" style="max-height: 300px; overflow-y: auto;">
                                <table class="table relationship-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Relation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clientFamilyDetails as $relationship)
                                            <?php
                                            //dd($relationship->related_client_id);
                                            if(isset($relationship->related_client_id) && $relationship->related_client_id != "" && $relationship->related_client_id != 0)
                                            { //Existing Client
                                                // Use eager-loaded relatedClient instead of querying in loop (prevents N+1)
                                                $relatedClientInfo = $relationship->relatedClient;
                                                //dd($relatedClientInfo);
                                                if($relatedClientInfo){
                                                    $relatedClientId = $relatedClientInfo->client_id;
                                                    $clientFirstName = trim($relatedClientInfo->first_name ?? '');
                                                    $clientLastName = trim($relatedClientInfo->last_name ?? '');
                                                    
                                                    if (empty($clientFirstName) && empty($clientLastName)) {
                                                        $relatedClientFullName = 'Client ID: ' . $relatedClientId;
                                                    } elseif (empty($clientFirstName)) {
                                                        $relatedClientFullName = $clientLastName . "<br/>" . $relatedClientId;
                                                    } elseif (empty($clientLastName)) {
                                                        $relatedClientFullName = $clientFirstName . "<br/>" . $relatedClientId;
                                                    } else {
                                                        $relatedClientFullName = $clientFirstName.' '.$clientLastName."<br/>".$relatedClientId;
                                                    }
                                                } else {
                                                    $relatedClientId = 'NA';
                                                    $relatedClientFullName = 'Client not found';
                                                }
                                            }  else { //New Client
                                                $relatedClientId = 'NA';
                                                // Handle empty or null names properly
                                                $firstName = trim($relationship->first_name ?? '');
                                                $lastName = trim($relationship->last_name ?? '');
                                                // Fallback to details when first/last name are empty (e.g. details-only parents)
                                                if (empty($firstName) && empty($lastName)) {
                                                    $detailsFallback = trim($relationship->details ?? '');
                                                    $relatedClientFullName = $detailsFallback !== '' ? $detailsFallback : 'Name not provided';
                                                } elseif (empty($firstName)) {
                                                    $relatedClientFullName = $lastName;
                                                } elseif (empty($lastName)) {
                                                    $relatedClientFullName = $firstName;
                                                } else {
                                                    $relatedClientFullName = $firstName . ' ' . $lastName;
                                                }
                                            }?>
                                            <tr>
                                                <td style="color: #6c757d;">
                                                    <?php
                                                    if(isset($relationship->related_client_id) && $relationship->related_client_id != "" && $relationship->related_client_id != 0)
                                                    { ?>
                                                        <a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$relationship->related_client_id)))}}"><?php echo $relatedClientFullName;?> </a>
                                                    <?php
                                                    }  else {
                                                        echo $relatedClientFullName;
                                                    } ?>
                                                </td>
                                                <td style="color: #6c757d;">{{ $relationship->relationship_type ?? 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <style>
                        .relationship-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-top: 10px;
                        }
                        .relationship-table th, .relationship-table td {
                            padding: 10px;
                            border-bottom: 1px solid #dee2e6;
                            text-align: left;
                        }
                        .relationship-table th {
                            background-color: #f8f9fa;
                            font-weight: 600;
                            color: #6c757d !important;
                        }
                        .relationship-table tbody tr:hover {
                            background-color: #f1f5f9;
                        }
                    </style>


                    <?php
                    if($fetchedData->related_files != '')
                    { ?>
                    <div class="card">
                        <h3>@icon('fa-address-card') Related Files</h3>
                        <div class="field-group">
                            <ul style="margin-left: 15px;">
                                <?php
                                //if($fetchedData->related_files != '')
                                //{
                                    $exploder = explode(',', $fetchedData->related_files);
                                    foreach($exploder AS $EXP)
                                    {
                                        $relatedclients = \App\Models\Admin::where('id', $EXP)->first();
                                        ?>
                                        <li><a target="_blank" href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$relatedclients->id)))}}">{{$relatedclients->first_name}} {{$relatedclients->last_name}}</a></li>
                                    <?php
                                    }
                                //} ?>
                            </ul>
                        </div>
                    </div>
                    <?php
                    } ?>

                    <?php
                    $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                    //dd($matter_cnt);
                    if($matter_cnt >0)
                    {
                        //Display reference values
                        $matter_dis_ref_info_arr = []; // Always a Collection
                        if($id1)
                        { //if client unique reference id is present in url
                            $matter_dis_ref_info_arr = \App\Models\ClientMatter::select('department_reference','other_reference')->where('client_id',$fetchedData->id)->where('client_unique_matter_no',$id1)->first();
                        }
                        else
                        {
                            $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                            //dd($matter_cnt);
                            if($matter_cnt >0){
                                $matter_dis_ref_info_arr = \App\Models\ClientMatter::select('department_reference','other_reference')->where('client_id',$fetchedData->id)->where('matter_status',1)->orderBy('id', 'desc')->first();
                            }
                        } //dd($matter_dis_ref_info_arr);


                        if(
                            ( isset($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->department_reference != '' )
                            ||
                            ( isset($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->other_reference != '' )
                        )
                        { ?>
                            <div class="card">
                                <h3>@icon('fa-user') Reference Information</h3>
                                <div class="field-group">
                                    <span class="field-label">Department Reference</span>
                                    <span class="field-value">
                                        <?php
                                        if( isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->department_reference != '') {
                                            echo $matter_dis_ref_info_arr->department_reference;
                                        } else {
                                            echo 'N/A';
                                        }?>

                                    </span>
                                </div>
                                <div class="field-group">
                                    <span class="field-label">Other Reference</span>
                                    <span class="field-value">
                                        <?php
                                        if( isset($matter_dis_ref_info_arr) && !empty($matter_dis_ref_info_arr) && $matter_dis_ref_info_arr->other_reference != ''){
                                            echo $matter_dis_ref_info_arr->other_reference;
                                        } else {
                                            echo 'N/A';
                                        } ?>
                                    </span>
                                </div>
                            </div>
                        <?php
                        }
                    }
                    ?>

                    @include('crm.clients.partials.matter_assignee_card')


                    <?php
                    $clientEoi_Info = App\Models\ClientEoiReference::where('client_id', $fetchedData->id)->orderBy('id','desc')->get();
                    ?>
                    @if(!empty($clientEoi_Info) && $clientEoi_Info->count() > 0)
                    <div class="card">
                        <div class="eoi-section">
                            <h3>@icon('fa-file-alt') EOI Reference Information</h3>
                            <div class="eoi-list" style="overflow-x: auto;/*max-height: 300px; overflow-y: auto;*/">
                                <table class="table eoi-table">
                                    <thead>
                                        <tr>
                                            <th>Subclass</th>
                                            <th>Occupation</th>
                                            <th>Point</th>
                                            <th>State</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($clientEoi_Info as $Eoi_Info)
                                            <tr>
                                                <td>{{ $Eoi_Info->EOI_subclass ?: 'N/A' }}</td>
                                                <td>{{ $Eoi_Info->EOI_occupation ?: 'N/A' }}</td>
                                                <td>{{ $Eoi_Info->EOI_point ?: 'N/A' }}</td>
                                                <td>{{ $Eoi_Info->EOI_state ?: 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <style>
                        .eoi-table{
                            width: 100%;
                            min-width: 600px;
                            border-collapse: collapse;
                            margin-top: 10px;
                            table-layout: fixed;
                        }
                        .eoi-table th, .eoi-table td {
                            padding: 10px;
                            border-bottom: 1px solid #dee2e6;
                            text-align: left;
                            word-break: normal;
                            white-space: normal;
                        }
                        .eoi-table th {
                            background-color: #f8f9fa;
                            font-weight: 600;
                            color: #6c757d !important;
                            white-space: normal;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                            overflow: visible;
                            text-overflow: clip;
                        }
                        /* Qualification table column widths */
                        .qualification-section .eoi-table th:nth-child(1),
                        .qualification-section .eoi-table td:nth-child(1) { 
                            width: 20%; 
                        } /* Level */
                        .qualification-section .eoi-table th:nth-child(2),
                        .qualification-section .eoi-table td:nth-child(2) { 
                            width: 40%; 
                        } /* Name */
                        .qualification-section .eoi-table th:nth-child(3),
                        .qualification-section .eoi-table td:nth-child(3) { 
                            width: 18%; 
                        } /* Campus */
                        .qualification-section .eoi-table th:nth-child(4),
                        .qualification-section .eoi-table td:nth-child(4) { 
                            width: 22%; 
                        } /* End Date */
                        
                        /* Work Experience table column widths */
                        .experience-section .eoi-table th:nth-child(1),
                        .experience-section .eoi-table td:nth-child(1) { 
                            width: 25%; 
                        } /* Job Title */
                        .experience-section .eoi-table th:nth-child(2),
                        .experience-section .eoi-table td:nth-child(2) { 
                            width: 18%; 
                        } /* Country */
                        .experience-section .eoi-table th:nth-child(3),
                        .experience-section .eoi-table td:nth-child(3) { 
                            width: 22%; 
                        } /* Start Date */
                        .experience-section .eoi-table th:nth-child(4),
                        .experience-section .eoi-table td:nth-child(4) { 
                            width: 35%; 
                        } /* End Date */
                        
                        /* EOI table column widths */
                        .eoi-section .eoi-table th:nth-child(1),
                        .eoi-section .eoi-table td:nth-child(1) { 
                            width: 20%; 
                        } /* Subclass */
                        .eoi-section .eoi-table th:nth-child(2),
                        .eoi-section .eoi-table td:nth-child(2) { 
                            width: 35%; 
                        } /* Occupation */
                        .eoi-section .eoi-table th:nth-child(3),
                        .eoi-section .eoi-table td:nth-child(3) { 
                            width: 20%; 
                        } /* Point */
                        .eoi-section .eoi-table th:nth-child(4),
                        .eoi-section .eoi-table td:nth-child(4) { 
                            width: 25%; 
                        } /* State */
                        .eoi-table tbody tr:hover {
                            background-color: #f1f5f9;
                        }
                        .eoi-table td {
                            color: #212529;
                        }
                        /* Allow wrapping only for very long text in specific columns */
                        .qualification-section .eoi-table td:nth-child(2) {
                            white-space: normal;
                            word-break: break-word;
                            overflow-wrap: break-word;
                        }
                        .experience-section .eoi-table td:nth-child(1) {
                            white-space: normal;
                            word-break: break-word;
                            overflow-wrap: break-word;
                        }
                        .eoi-section .eoi-table td:nth-child(2) {
                            white-space: normal;
                            word-break: break-word;
                            overflow-wrap: break-word;
                        }
                        /* Allow State column to wrap properly */
                        .eoi-section .eoi-table td:nth-child(4) {
                            white-space: normal;
                            word-break: normal;
                            overflow-wrap: normal;
                        }
                        /* Allow End Date columns to wrap to 2 lines */
                        .qualification-section .eoi-table td:nth-child(4) {
                            white-space: normal;
                            word-break: break-word;
                            overflow-wrap: break-word;
                        }
                        .experience-section .eoi-table td:nth-child(4) {
                            white-space: normal;
                            word-break: break-word;
                            overflow-wrap: break-word;
                        }
                        /* Allow Level column to wrap for long values */
                        .qualification-section .eoi-table td:nth-child(1) {
                            white-space: normal;
                            word-break: normal;
                            overflow-wrap: normal;
                        }
                        
                        /* Tag spacing and layout */
                        .ui.label {
                            margin: 5px 5px 5px 0 !important;
                            display: inline-flex !important;
                            vertical-align: top;
                            max-width: 100%;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                        }
                        
                        .ui.label .col-hr-1 {
                            white-space: normal;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                            padding: 2px 8px;
                            border-radius: 4px;
                            font-size: 12px;
                            max-width: 100%;
                            box-sizing: border-box;
                        }
                    </style>

                    @if(($fetchedData->type ?? '') === 'lead')
                        @include('crm.clients.partials.lead_pipeline_card', [
                            'fetchedData' => $fetchedData,
                            'assignableStaff' => $assignableStaff ?? collect(),
                            'leadStageLabels' => $leadStageLabels ?? [],
                        ])
                    @endif

                    <div class="card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h3>@icon('fa-address-card') Tag(s):</h3>
                            <div class="d-flex gap-1">
                                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-primary opentagspopup btn-sm d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;padding:0;" title="Add Tag">@icon('fa-plus')</a>
                                <a href="javascript:;" data-id="{{$fetchedData->id}}" class="btn btn-danger openredtagspopup btn-sm d-inline-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;padding:0;" title="Add Tag (hidden by default)">@icon('fa-plus')</a>
                            </div>
                        </div>
                       

                        <div class="client-tags-list">
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
                        .client-tags-list {
                            overflow-wrap: break-word;
                            word-wrap: break-word;
                            max-width: 100%;
                            max-height: 280px;
                            overflow-y: auto;
                            padding-right: 4px;
                        }

                    </style>

                </div>
            </div>

            <!-- Age/DOB Toggle JavaScript -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ageDobToggle = document.getElementById('ageDobToggle');
                if (ageDobToggle) {
                    ageDobToggle.addEventListener('click', function() {
                        const ageSpan = this.querySelector('.display-age');
                        const dobSpan = this.querySelector('.display-dob');
                        
                        if (ageSpan && dobSpan) {
                            if (ageSpan.style.display === 'none') {
                                // Currently showing DOB, switch to Age
                                ageSpan.style.display = 'inline';
                                dobSpan.style.display = 'none';
                            } else {
                                // Currently showing Age, switch to DOB
                                ageSpan.style.display = 'none';
                                dobSpan.style.display = 'inline';
                            }
                        }
                    });
                }
                
                // Visa Expiry Warning Check
                const visaExpiryElement = document.querySelector('[data-expiry-warning="true"]');
                if (visaExpiryElement) {
                    const daysLeft = visaExpiryElement.getAttribute('data-days-left');
                    const expiryDate = visaExpiryElement.textContent;
                    
                    let message = '⚠️ VISA EXPIRY WARNING ⚠️\n\n';
                    if (daysLeft == 0) {
                        message += 'This visa expires TODAY (' + expiryDate + ')!\n\n';
                    } else if (daysLeft == 1) {
                        message += 'This visa expires TOMORROW (' + expiryDate + ')!\n\n';
                    } else {
                        message += 'This visa expires in ' + daysLeft + ' days (' + expiryDate + ')!\n\n';
                    }
                    message += 'Please take immediate action to renew or extend this visa.\n\nClick OK to continue viewing the client details.';
                    
                    alert(message);
                }
                
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
                        toggleRedTagsBtn.innerHTML = crmI('fas fa-eye-slash');
                        toggleRedTagsBtn.classList.remove('btn-outline-danger');
                        toggleRedTagsBtn.classList.add('btn-danger');
                        toggleRedTagsBtn.title = 'Hide Red Tags';
                    }
                    
                    toggleRedTagsBtn.addEventListener('click', function() {
                        const isCurrentlyVisible = redTagsSection.style.display !== 'none';
                        
                        if (isCurrentlyVisible) {
                            // Hide red tags
                            redTagsSection.style.display = 'none';
                            this.innerHTML = crmI('fas fa-eye');
                            this.classList.remove('btn-danger');
this.classList.add('btn-outline-danger');
                this.title = 'Show Red Tags';
                sessionStorage.setItem(storageKey, 'false');
                        } else {
                            // Show red tags
                            redTagsSection.style.display = 'block';
                            this.innerHTML = crmI('fas fa-eye-slash');
                            this.classList.remove('btn-outline-danger');
this.classList.add('btn-danger');
                this.title = 'Hide Red Tags';
                sessionStorage.setItem(storageKey, 'true');
                        }
                    });
                }
            });
            </script>
