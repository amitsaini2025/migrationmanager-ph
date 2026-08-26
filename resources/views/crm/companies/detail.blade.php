@extends('layouts.crm_client_detail')
@section('title', 'Company Detail')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ URL::asset('css/client-detail.css') }}">

<?php
use App\Http\Controllers\Controller;
?>
<div class="crm-container" data-client-id="{{ $fetchedData->id }}">
    <!-- Collapsed Toggle Button (shown when sidebar is collapsed) -->
    <button id="collapsed-toggle" class="collapsed-toggle-btn" title="Show Sidebar">
        ☰
    </button>
    
    <!-- Client Navigation Sidebar -->
    <aside class="client-navigation-sidebar" id="client-sidebar">
        <div class="sidebar-header">
            <!-- Sidebar Toggle Button -->
            <button id="sidebar-toggle" class="sidebar-toggle-btn" title="Hide Sidebar">
                @icon('fa-chevron-left')
            </button>
            <div class="client-info">
                <h3 class="client-id">
                    <?php
                    if($id1) { //if client unique reference id is present in url
                        $matter_info_arr = \App\Models\ClientMatter::select('client_unique_matter_no')->where('client_id',$fetchedData->id)->where('client_unique_matter_no',$id1)->first();
                    ?>
                        {{$fetchedData->client_id}}-{{$matter_info_arr ? $matter_info_arr->client_unique_matter_no : 'N/A'}}
                    <?php
                    } else {
                        $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                        if($matter_cnt >0){
                            $matter_info_arr = \App\Models\ClientMatter::select('client_unique_matter_no')->where('client_id',$fetchedData->id)->where('matter_status',1)->orderBy('id', 'desc')->first();
                        ?>
                            {{$fetchedData->client_id}}-{{$matter_info_arr ? $matter_info_arr->client_unique_matter_no : 'N/A'}}
                        <?php
                        } else {
                        ?>
                            {{$fetchedData->client_id}}
                        <?php
                        }
                    } ?>
                </h3>
                {{-- Company Display --}}
                <p class="client-name">
                    {{ $fetchedData->company->company_name ?? 'Unnamed Company' }}
                    <a href="{{route('clients.edit', base64_encode(convert_uuencode(@$fetchedData->id)))}}" title="Edit" class="client-name-edit">
                        @icon('fa-edit')
                    </a>
                </p>
                
                {{-- Primary Contact Person Info --}}
                @if($fetchedData->company->contactPerson)
                    <div class="contact-person-info" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                        <small style="color: #6c757d; display: block; margin-bottom: 5px;">Primary Contact:</small>
                        <a href="{{ route('clients.detail', base64_encode(convert_uuencode($fetchedData->company->contactPerson->id))) }}" 
                           class="contact-person-link" 
                           style="color: #007bff; text-decoration: none; font-weight: 500; font-size: 0.9em;">
                            {{ $fetchedData->company->contactPerson->first_name }} {{ $fetchedData->company->contactPerson->last_name }}
                        </a>
                        @if($fetchedData->company->contact_person_position)
                            <br><small style="color: #6c757d; font-size: 0.85em;">{{ $fetchedData->company->contact_person_position }}</small>
                        @endif
                    </div>
                @endif
                
                <!-- Action Icons (left) and Client Portal Toggle (right) -->
                <div class="sidebar-actions-row">
                    <!-- Action Icons -->
                    <div class="client-actions">
                        <a href="javascript:;" class="create_note_d" datatype="note" title="Add Notes">@icon('fa-plus')</a>
                        <a href="javascript:;" data-id="{{@$fetchedData->id}}" data-email="{{@$fetchedData->email}}" data-name="{{ $fetchedData->company->company_name ?? 'Unnamed Company' }}" class="clientemail" title="Compose Mail">@icon('fa-envelope')</a>
                        <a href="javascript:;" class="send-sms-btn" data-client-id="{{@$fetchedData->id}}" data-client-name="{{ $fetchedData->company->company_name ?? 'Unnamed Company' }}" title="Send SMS">@icon('fa-sms')</a>
                        <a href="javascript:;" datatype="not_picked_call" class="not_picked_call" title="Not Picked Call">@icon('fa-mobile-alt')</a>
                        <a href="javascript:;" data-bs-toggle="modal" data-bs-target="#create_appoint" title="Add Appointment">@icon('fa-calendar-plus')</a>
                    </div>
                    
                </div>
            </div>
            
            <!-- Lead status badge (conversion action is handled in Checklist tab) -->
            @if(($fetchedData->type ?? '') === 'lead')
            <div class="sidebar-client-lead-buttons">
                <span class="status-btn status-btn-lead lead-status-badge active">Lead</span>
            </div>
            @endif
            
            <!-- Matter Selection Dropdown in Sidebar -->
            <div class="sidebar-matter-selection">
                <?php
                $assign_info_arr = \App\Models\Admin::select('type')->where('id',@$fetchedData->id)->first();
                ?>
                @if($assign_info_arr->type)
                    <?php 
                    if($id1)
                    {
                        //if client_unique_matter_no is present in url
                        $matter_cnt = DB::table('client_matters')
                        ->select('client_matters.id')
                        ->where('client_matters.client_id',@$fetchedData->id)
                        ->where('client_matters.client_unique_matter_no',$id1)
                        ->where('client_matters.matter_status',1)
                        ->whereNotNull('client_matters.sel_matter_id')
                        ->count();  
                        if( $matter_cnt >0 )
                        {
                            // Fetch all matters, but we'll sort them in Blade to prioritize the URL matter
                            $matter_list_arr = DB::table('client_matters')
                            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                            ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                            ->where('client_matters.client_id',@$fetchedData->id)
                            ->where('client_matters.matter_status',1)
                            ->get();
                            $clientmatter_info_arr = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('client_unique_matter_no',$id1)->first();
                            $latestClientMatterId = $clientmatter_info_arr ? $clientmatter_info_arr->id : null;

                            // Convert matter_list_arr to an array for sorting
                            $matter_list_arr = $matter_list_arr->toArray();
                            // Sort matters: URL matter ($id1) comes first, others follow
                            usort($matter_list_arr, function($a, $b) use ($id1) {
                                if ($a->client_unique_matter_no == $id1 && $b->client_unique_matter_no != $id1) {
                                    return -1; // $a (URL matter) comes first
                                } elseif ($a->client_unique_matter_no != $id1 && $b->client_unique_matter_no == $id1) {
                                    return 1; // $b (URL matter) comes first
                                }
                                return 0; // Maintain original order for other matters
                            });
                            ?>
                            <select name="matter_id" id="sel_matter_id_client_detail" class="form-control mm-select visa-dropdown" data-valid="required">
                                <option value="">Select Matters</option>
                                @foreach($matter_list_arr as $matterlist)
                                    @php
                                        // If sel_matter_id is 1 or title is null, use "General Matter"
                                        $matterName = 'General Matter';
                                        if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                            $matterName = $matterlist->title;
                                        }
                                    @endphp
                                    <option value="{{$matterlist->id}}" {{ $matterlist->id == $latestClientMatterId ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                                @endforeach
                            </select>
                        <?php
                        }  
                        else 
                        {
                            $matter_cnt = DB::table('client_matters')
                            ->select('client_matters.id')
                            ->where('client_matters.client_id',@$fetchedData->id)
                            ->where('client_matters.matter_status',1)
                            ->whereNotNull('client_matters.sel_matter_id')
                            ->count();
                            if( $matter_cnt >0 )
                            {
                                $matter_list_arr = DB::table('client_matters')
                                ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                                ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                                ->where('client_matters.client_id',@$fetchedData->id)
                                ->where('client_matters.matter_status',1)
                                ->orderBy('client_matters.created_at', 'desc')
                                ->get();
                                $latestClientMatter = \App\Models\ClientMatter::where('client_id',$fetchedData->id)->where('matter_status',1)->latest()->first();
                                $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
                                ?>
                                <select name="matter_id" id="sel_matter_id_client_detail" class="form-control mm-select visa-dropdown" data-valid="required">
                                    <option value="">Select Matters</option>
                                    @foreach($matter_list_arr as $matterlist)
                                        @php
                                            // If sel_matter_id is 1 or title is null, use "General Matter"
                                            $matterName = 'General Matter';
                                            if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                                $matterName = $matterlist->title;
                                            }
                                        @endphp
                                        <option value="{{$matterlist->id}}" {{ $matterlist->id == $latestClientMatterId ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                                    @endforeach
                                </select>
                            <?php
                            }
                        } 
                    }
                    else
                    {
                        $matter_cnt = DB::table('client_matters')
                        ->select('client_matters.id')
                        ->where('client_matters.client_id',@$fetchedData->id)
                        ->where('client_matters.matter_status',1)
                        ->whereNotNull('client_matters.sel_matter_id')
                        ->count();
                        if( $matter_cnt >0 )
                        {
                            $matter_list_arr = DB::table('client_matters')
                            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                            ->select('client_matters.id','client_matters.client_unique_matter_no','matters.title','client_matters.sel_matter_id')
                            ->where('client_matters.client_id',@$fetchedData->id)
                            ->where('client_matters.matter_status',1)
                            ->orderBy('client_matters.created_at', 'desc')
                            ->get();
                            $latestClientMatter = \App\Models\ClientMatter::where('client_id',$fetchedData->id)->where('matter_status',1)->latest()->first();
                            $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
                            ?>
                            <select name="matter_id" id="sel_matter_id_client_detail" class="form-control mm-select visa-dropdown" data-valid="required">
                                <option value="">Select Matters</option>
                                @foreach($matter_list_arr as $matterlist)
                                    @php
                                        // If sel_matter_id is 1 or title is null, use "General Matter"
                                        $matterName = 'General Matter';
                                        if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                            $matterName = $matterlist->title;
                                        }
                                    @endphp
                                    <option value="{{$matterlist->id}}" {{ $matterlist->id == $latestClientMatterId ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                                @endforeach
                            </select>
                        <?php
                        }
                    }
                    ?>
                @endif
            </div>
            
            <div class="matter-status-badge">
                <?php
                // Get the current workflow stage for this client matter
                $workflow_stage_arr = null;
                
                if ($id1) {
                    // If client unique reference id is present in url
                    $workflow_stage_arr = DB::table('client_matters')
                        ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                        ->select('workflow_stages.name')
                        ->where('client_id', $fetchedData->id)
                        ->where('client_unique_matter_no', $id1)
                        ->first();
                } else {
                    // Get the most recent active matter
                    $clientMatterInfo = DB::table('client_matters')
                        ->select('client_unique_matter_no')
                        ->where('client_id', $fetchedData->id)
                        ->where('matter_status', 1)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($clientMatterInfo) {
                        $workflow_stage_arr = DB::table('client_matters')
                            ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                            ->select('workflow_stages.name')
                            ->where('client_id', $fetchedData->id)
                            ->where('client_unique_matter_no', $clientMatterInfo->client_unique_matter_no)
                            ->first();
                    }
                }

                // Display the workflow stage name or default to N/A
                if ($workflow_stage_arr && $workflow_stage_arr->name) {
                    echo $workflow_stage_arr->name;
                } else {
                    echo "N/A";
                }
                ?>
            </div>
            
            <!-- Matter References Section -->
            <div class="sidebar-references">
                <div class="sidebar-references-label" style="font-size: 0.75rem; font-weight: 600; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Reference</div>
                <?php
                // Load reference values - SAME LOGIC AS ACCOUNTS TAB
                $matter__ref_info_arr = [];
                if($id1) {
                    // If client unique reference id is present in url
                    $matter__ref_info_arr = \App\Models\ClientMatter::select('department_reference','other_reference')
                        ->where('client_id', $fetchedData->id)
                        ->where('client_unique_matter_no', $id1)
                        ->first();
                } else {
                    $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id', $fetchedData->id)->where('matter_status', 1)->count();
                    if($matter_cnt > 0) {
                        $matter__ref_info_arr = \App\Models\ClientMatter::select('department_reference','other_reference')
                            ->where('client_id', $fetchedData->id)
                            ->where('matter_status', 1)
                            ->orderBy('id', 'desc')
                            ->first();
                    }
                }
                ?>
                
                <!-- Hidden inputs - SAME IDs AS ORIGINAL -->
                <input type="hidden" 
                       id="department_reference" 
                       name="department_reference" 
                       value="<?php if(isset($matter__ref_info_arr) && !empty($matter__ref_info_arr) && $matter__ref_info_arr->department_reference != ''){ echo $matter__ref_info_arr->department_reference; } ?>">
                
                <input type="hidden" 
                       id="other_reference" 
                       name="other_reference" 
                       value="<?php if(isset($matter__ref_info_arr) && !empty($matter__ref_info_arr) && $matter__ref_info_arr->other_reference != ''){ echo $matter__ref_info_arr->other_reference; } ?>">
                
                <!-- Reference Chips Container -->
                <div id="references-container" class="references-chips-container">
                    <!-- Dynamically generated chips -->
                </div>
                
                <!-- Input Container (hidden by default) -->
                <div id="reference-input-container" class="reference-input-wrapper" style="display: none;">
                    <input type="text" 
                           id="reference-input" 
                           class="form-control form-control-sm reference-input" 
                           placeholder="Type and press Enter..."
                           maxlength="50"
                           autocomplete="off">
                    <button class="btn-cancel-input" type="button" title="Cancel (Esc)">
                        @icon('fa-times')
                    </button>
                </div>
                
                <!-- Add Button -->
                <button id="btn-add-reference" class="btn-add-reference-chip" type="button">
                    @icon('fa-plus') Add Reference
                </button>

                @php
                    $detailsVerifiedByName = null;
                    if (!empty($fetchedData->details_verified_by)) {
                        $detailsVerifiedByName = optional($fetchedData->detailsVerifiedByStaff)->full_name
                            ?: optional(\App\Models\Staff::find($fetchedData->details_verified_by))->full_name;
                    }
                @endphp
                @if(!empty($fetchedData->details_verified_at))
                <div class="sidebar-details-verified-info" id="sidebarDetailsVerifiedInfo">
                    <div class="details-verify-line"><strong>Verified By:</strong> {{ $detailsVerifiedByName ?: '—' }}</div>
                    <div class="details-verify-line"><strong>Verified At:</strong> {{ $fetchedData->details_verified_at->format('d/m/Y g:i A') }}</div>
                </div>
                @endif
            </div>
        </div>
        <nav class="client-sidebar-nav">
            <?php
            $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
            
            // Valid tab names that should NOT be treated as matter IDs
            $validTabNames = ['companydetails', 'activityfeed', 'noteterm', 'personaldocuments', 'visadocuments', 'nominationdocuments',
                              'eoiroi', 'emails', 
                              // Legacy removed tab slugs
                              'formgenerations', 'formgenerationsl',
                              'client_portal', 'application', 'workflow', 'checklists'];
            
            // Check if $id1 is a valid matter ID (not a tab name)
            $isMatterIdInUrl = isset($id1) && $id1 != "" && !in_array(strtolower($id1), array_map('strtolower', $validTabNames));
            
            // Show client menu if: valid matter ID in URL OR client has any matters
            if( $isMatterIdInUrl || $matter_cnt > 0 )
            {  //if client unique reference id is present in url
            ?>
                <button class="client-nav-button active" data-tab="companydetails">
                    @icon('fa-building')
                    <span>Company Details</span>
                </button>
                <button class="client-nav-button" data-tab="activityfeed">
                    @icon('fa-history')
                    <span>Activity</span>
                </button>
                <button class="client-nav-button" data-tab="noteterm">
                    @icon('fa-sticky-note')
                    <span>Notes</span>
                </button>
                <button class="client-nav-button" data-tab="personaldocuments">
                    @icon('fa-folder-open')
                    <span>Company Documents</span>
                </button>
                <button class="client-nav-button" data-tab="nominationdocuments">
                    @icon('fa-file-contract')
                    <span>File Documents</span>
                </button>
                <button class="client-nav-button" data-tab="account">
                    @icon('fa-file-invoice-dollar')
                    <span>Account</span>
                </button>
                <button class="client-nav-button" data-tab="emails">
                    @icon('fa-inbox')
                    <span>Emails</span>
                </button>
                <button class="client-nav-button" data-tab="checklists">
                    @icon('fa-tasks')
                    <span>Checklists</span>
                </button>
                <button class="client-nav-button" data-tab="workflow">
                    @icon('fa-stream')
                    <span>Workflow</span>
                </button>
                <button class="client-nav-button" data-tab="client_portal">
                    @icon('fa-globe')
                    <span>Client Portal</span>
                </button>
                <?php
                // Get last updated date for the client record
                if (isset($fetchedData->updated_at) && $fetchedData->updated_at) {
                    try {
                        $updatedDate = \Carbon\Carbon::parse($fetchedData->updated_at);
                        echo '<div class="sidebar-last-updated" style="margin-top: 15px; padding: 10px 15px; color: #374151; font-size: 0.85em; text-align: center; border-top: 1px solid #e2e8f0;">Last update on ' . $updatedDate->format('d/m/Y') . '</div>';
                    } catch (\Exception $e) {
                        // Silently fail if date parsing fails
                    }
                }
                ?>
            <?php
            }
            else
            {  //If no matter is exist
            ?>
                <button class="client-nav-button active" data-tab="companydetails">
                    @icon('fa-building')
                    <span>Company Details</span>
                </button>
                <button class="client-nav-button" data-tab="activityfeed">
                    @icon('fa-history')
                    <span>Activity</span>
                </button>
                <button class="client-nav-button" data-tab="noteterm">
                    @icon('fa-sticky-note')
                    <span>Notes</span>
                </button>
                <button class="client-nav-button" data-tab="personaldocuments">
                    @icon('fa-folder-open')
                    <span>Company Documents</span>
                </button>
                <button class="client-nav-button" data-tab="checklists">
                    @icon('fa-tasks')
                    <span>Checklists</span>
                </button>
            <?php
            }
            ?>
        </nav>
    </aside>

    <main class="main-content" id="main-content">
        <div class="server-error">
            @include('../Elements/flash-message')
        </div>
        <div class="custom-error-msg">
        </div>
        <!-- Main Content Container with Vertical Tabs -->
        <div class="main-content-with-tabs">
            <!-- Tab Contents -->
            <div class="tab-content" id="tab-content">
            @include('crm.companies.tabs.company_details')
            
            @include('crm.clients.tabs.activityfeed_tab')
            
            @include('crm.clients.tabs.notes')
            
            @include('crm.clients.tabs.personal_documents', ['companyDocumentsOnlyGeneral' => true])
            
            <?php
            // Mirror the same condition used to render sidebar buttons so that
            // only panes for visible tabs are included (prevents duplicates)
            $matter_cnt = \App\Models\ClientMatter::select('id')
                ->where('client_id',$fetchedData->id)
                ->where('matter_status',1)
                ->count();
            ?>
            @if((isset($id1) && $id1 != "") || $matter_cnt > 0)
                @include('crm.companies.tabs.nomination_documents')
                @include('crm.clients.tabs.account')
                @include('crm.clients.tabs.emails')
                @include('crm.clients.tabs.checklists')
                @include('crm.clients.tabs.workflow')
                @include('crm.clients.tabs.client_portal')
            @else
                @include('crm.clients.tabs.checklists')
            @endif
            
            @include('crm.clients.tabs.not_used_documents')
            
            </div>
        </div>
    </main>

    <!-- Activity Feed (visible on Company Details tab; same partial as client detail) -->
    @include('crm.clients.tabs.activity_feed')
</div>

@include('crm.clients.addclientmodal')
@include('crm.clients.editclientmodal')
@include('crm.clients.modals.edit-matter-office')
@include('crm.clients.modals.client-management')






<div id="emailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmail" action="{{route('clients.sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				@csrf
                    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="type" value="client">
                    <input type="hidden" name="mail_type" value="1">
                    <input type="hidden" name="mail_body_type" value="sent">
                    <input type="hidden" name="compose_client_matter_id" id="compose_client_matter_id" value="">
                    <input type="hidden" name="signing_url" id="compose_signing_url" value="">
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from">From <span class="span_req">*</span></label>
								@include('partials.email-from-sendgrid')
								@if ($errors->has('email_from'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_from') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<select data-valid="required" class="js-data-example-ajax" name="email_to[]"></select>

								@if ($errors->has('email_to'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_to') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_cc">CC </label>
								<select data-valid="" class="js-data-example-ajaxccd" name="email_cc[]"></select>

								@if ($errors->has('email_cc'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_cc') }}</strong>
									</span>
								@endif
							</div>
						</div>

                        <div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="template">Templates </label>
                                <?php
                                $assignee = \App\Models\Staff::select('first_name')->where('id',@$fetchedData->assignee)->first();
                                if($assignee){
                                    $clientAssigneeName = $assignee->first_name;
                                } else {
                                    $clientAssigneeName = 'NA';
                                }
                                ?>
								<select data-valid="" class="form-control mm-select selecttemplate" name="template" data-clientid="{{@$fetchedData->id}}" data-clientfirstname="{{@$fetchedData->first_name}}" data-clientvisaExpiry="{{@$fetchedData->visaExpiry}}" data-clientreference_number="{{@$fetchedData->client_id}}" data-clientassignee_name="{{@$clientAssigneeName}}">
									<option value="">Select</option>
									@foreach( \App\Models\EmailTemplate::crm()->orderBy('id', 'desc')->get() as $list)
										<option value="{{$list->id}}">{{$list->name}}</option>
									@endforeach
								</select>
                            </div>
						</div>


						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" id="compose_email_subject" class="form-control selectedsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject" value="" />
								@if ($errors->has('subject'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('subject') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea class="tinymce-editor selectedmessage" id="compose_email_message" name="message" data-valid="required"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
						     <div class="form-group">
						        <label>Attachment</label>
						        <input type="file" name="attach[]" class="form-control" multiple>
						     </div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
						    <div class="table-responsive uploadchecklists">
							<table id="mychecklist-datatable" class="table text_wrap table-2">
							    <thead>
							        <tr>
							            <th></th>
							            <th>File Name</th>
							            <th>File</th>
							        </tr>
							    </thead>
							    <tbody>
							        @foreach(\App\Models\UploadChecklist::all() as $uclist)
							        <tr data-matter-id="{{ $uclist->matter_id ?? '' }}" data-checklist-id="{{ $uclist->id }}">
							            <td><input type="checkbox" name="checklistfile[]" value="<?php echo $uclist->id; ?>" class="checklistfile-cb"></td>
							            <td><?php echo $uclist->name; ?></td>
							             <td><a target="_blank" href="<?php echo URL::to('/checklists/'.$uclist->file); ?>"><?php echo $uclist->name; ?></a></td>
							        </tr>
							        @endforeach
							    </tbody>
							</table>
						</div>
							</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="saveComposeEmail()" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>


<!-- Send Message-->
<div id="sendmsgmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="messageModalLabel">Send Message</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="sendmsg" id="sendmsg" action="{{route('clients.sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				    @csrf
                    <input type="hidden" name="client_id" id="sendmsg_client_id" value="">
                    <input type="hidden" name="vtype" value="client">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea id="sendmsg_message" class="tinymce-editor selectedmessage" name="message" data-valid="required"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
                        <div class="col-12 col-md-12 col-lg-12">
							<button onclick="saveSendMessage()" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Send SMS Modal -->
<div id="sendSmsModal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="smsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="smsModalLabel">
					@icon('fa-sms') Send SMS
				</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form id="sendSmsForm">
				    @csrf
                    <input type="hidden" name="client_id" id="sms_client_id" value="">
                    
					<div class="row">
						<!-- Phone Number Selection -->
						<div class="col-12">
							<div class="form-group">
								<label for="sms_phone">Send To <span class="span_req">*</span></label>
								<select class="form-control" id="sms_phone" name="phone" required>
									<option value="">Select phone number...</option>
								</select>
								<small class="form-text text-muted">
									@icon('fa-info-circle') 
									Australian numbers will use Cellcast, international numbers will use Twilio
								</small>
							</div>
						</div>
						
						<!-- Template Selection -->
						<div class="col-12">
							<div class="form-group">
								<label for="sms_template">Quick Template (Optional)</label>
								<select class="form-control" id="sms_template">
									<option value="">Type your own message or select a template...</option>
								</select>
							</div>
						</div>
						
						<!-- Message -->
						<div class="col-12">
							<div class="form-group">
								<label for="sms_message">Message <span class="span_req">*</span></label>
								<textarea class="form-control" id="sms_message" name="message" rows="5" maxlength="320" required></textarea>
								<div class="d-flex justify-content-between align-items-center mt-1">
									<small class="text-muted">
										<span id="sms_char_count">0</span> / <span id="sms_char_max">160</span> chars
									</small>
									<small>
										<span id="sms_segment_badge" class="badge badge-success">1 SMS</span>
										<span id="sms_chars_remaining" class="text-muted">&nbsp;&middot;&nbsp; 160 left in this SMS</span>
									</small>
								</div>
							</div>
						</div>
						
						<!-- Buttons -->
                        <div class="col-12">
							<button type="submit" class="btn btn-primary" id="sendSmsBtn">
								@icon('fa-paper-plane') Send SMS
							</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

{{-- interest_service_view modal REMOVED - Interested Services feature deprecated (no UI triggers) --}}

<div id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this note?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmNotUseDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to send this document in Not Use Tab?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Send</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmBackToDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to send this in related document Tab again?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Send</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to verify this doc?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Verify</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>


<div id="confirmLogModal" tabindex="-1" role="dialog" aria-labelledby="confirmLogModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this log?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accept">Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmEducationModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this note?</h4>
				<button type="submit" style="margin-top: 40px;" class="button btn btn-danger accepteducation">Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmcompleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to complete the Application?</h4>
				<button  data-id="" type="submit" style="margin-top: 40px;" class="button btn btn-danger acceptapplication">Complete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

<div id="confirmCostAgreementModal" tabindex="-1" role="dialog" aria-labelledby="confirmCostAgreementModalLabel" aria-hidden="false" class="modal fade" >
	<div class="modal-dialog">
		<div class="modal-content popUp">
			<div class="modal-body text-center">
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title text-center message col-v-5">Do you want to delete this Cost Agreement?</h4>
				<button data-id="" type="submit" style="margin-top: 40px;" class="button btn btn-danger acceptCostAgreementDelete">Yes, Delete</button>
				<button type="button" style="margin-top: 40px;" data-bs-dismiss="modal" class="button btn btn-secondary cancel">Cancel</button>
			</div>
		</div>
	</div>
</div>

{{-- confirmpublishdocModal REMOVED - workflow checklist unused --}}

<div class="modal fade custom_modal" id="matter_ownership" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Matter Ownership Ratio</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{url('/client-portal/ownership')}}" name="xmatter_ownership" id="xmatter_ownership" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="mapp_id" id="mapp_id" value="">
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="sus_agent"> </label>
								<input type="number" max="100" min="0" step="0.01" class="form-control ration" name="ratio">
								<span class="custom-error workflow_error" role="alert">
									<strong></strong>
								</span>
							</div>
						</div>

						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('xmatter_ownership')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="modal fade custom_modal" id="tags_clients" tabindex="-1" role="dialog" aria-labelledby="matterModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appliationModalLabel">Tags</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
                <form method="post" action="{{url('/save_tag')}}" name="stags_matter" id="stags_matter" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" id="client_id" value="">
				<input type="hidden" name="create_new_as_red" id="create_new_as_red" value="0">
					<div id="tags_red_mode_hint" class="alert alert-warning py-2 mb-2" style="display: none;">
						@icon('fa-exclamation-triangle', ['class' => 'text-danger']) <strong>Red Tag mode:</strong> Any new tags you add will be created as Red tags (hidden by default).
					</div>
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="tags_modal_container">Tags</label>
								<?php 
								$tagIdsForModal = [];
								$tagNamesForModal = [];
								if(!empty($fetchedData->tagname)){
									$tagIdsForModal = array_filter(array_map('intval', explode(',', $fetchedData->tagname)));
									if(!empty($tagIdsForModal)){
										$tagNamesForModal = \App\Models\Tag::whereIn('id', $tagIdsForModal)->pluck('name')->toArray();
									}
								}
								?>
								<div id="tags_modal_container" class="tags-modal-container form-control">
									<div class="tags-pills-inner">
										@foreach($tagNamesForModal as $tagName)
										<span class="tag-pill" data-tag-name="{{ htmlspecialchars($tagName) }}">
											<span class="tag-pill-text">{{ $tagName }}</span>
											<button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button>
										</span>
										@endforeach
										<input type="text" id="tag_input" class="tag-input-inline" placeholder="Type and press comma or Enter to add" autocomplete="off">
									</div>
								</div>
								<input type="hidden" id="tags_validation" value="{{ count($tagNamesForModal) > 0 ? '1' : '' }}" aria-hidden="true">
								<small class="form-text text-muted">Separate tags with commas or press Enter to add.</small>
							</div>
						</div>

						<div class="col-12 col-md-12 col-lg-12 mt-2">
							<button onclick="customValidate('stags_matter')" type="button" class="btn btn-primary">Save</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<style>
.tags-modal-container {
	min-height: 80px;
	max-height: 320px;
	height: auto;
	padding: 6px 10px;
	display: flex;
	align-items: flex-start;
	flex-wrap: wrap;
	gap: 6px;
	overflow-y: auto;
	overflow-x: hidden;
}
.tags-modal-container.form-control { height: auto; }
.tags-pills-inner { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 6px; flex: 1; width: 100%; min-width: 0; }
.tag-pill { display: inline-flex; align-items: flex-start; gap: 6px; padding: 4px 10px; background-color: #6A60E3; color: #fff; border-radius: 6px; font-size: 13px; max-width: 100%; }
.tag-pill-text { white-space: normal; word-break: break-word; line-height: 1.3; min-width: 0; }
.tag-pill-remove { flex-shrink: 0; background: none; border: none; color: #fff; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 2px; opacity: 0.8; }
.tag-pill-remove:hover { opacity: 1; }
.tag-input-inline { flex: 1; min-width: 120px; border: none; outline: none; font-size: 14px; background: transparent; }
</style>

{{-- Service Taken Modal - REMOVED --}}
{{-- Feature deprecated - client_service_takens table does not exist --}}
{{-- Table was for tracking Migration/Education services taken by companies --}}
{{-- Model clientServiceTaken.php deleted - no database backing --}}

<div class="modal fade" id="inbox_reassignemail_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				  <h4 class="modal-title">Re-assign Inbox Email</h4>
				  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				  </button>
			</div>
			<form method="POST" action="{{ url('/reassiginboxemail') }}" name="inbox-email-reassign-to-client-matter" autocomplete="off" enctype="multipart/form-data" id="inbox-email-reassign-to-client-matter">
			@csrf
			<div class="modal-body">
				<div class="form-group row">
					<div class="col-sm-12">
						<input id="memail_id" name="memail_id" type="hidden" value="">
                        <input id="mail_type" name="mail_type" type="hidden" value="inbox">
                        <input id="staff_mail" name="staff_mail" type="hidden" value="">
                        <input id="uploaded_doc_id" name="uploaded_doc_id" type="hidden" value="">
						<select id="reassign_client_id" name="reassign_client_id" class="form-control mm-select js-reassign-client-ajax" style="width: 100%;" data-valid="required" data-placeholder="Search by name, email, or client ID...">
							<option value="">Select Client</option>
						</select>
					</div>
				</div>

                <div class="form-group row">
					<div class="col-sm-12">
						<select id="reassign_client_matter_id" name="reassign_client_matter_id" class="form-control mm-select " style="width: 100%;" disabled>
						</select>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" onclick="customValidate('inbox-email-reassign-to-client-matter')">
					@icon('fa-save') Re-assign Inbox Email
				</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="sent_reassignemail_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				  <h4 class="modal-title">Re-assign Sent Email</h4>
				  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				  </button>
			</div>
			<form method="POST" action="{{ url('/reassigsentemail') }}" name="sent-email-reassign-to-client-matter" autocomplete="off" enctype="multipart/form-data" id="sent-email-reassign-to-client-matter">
			@csrf
			<div class="modal-body">
				<div class="form-group row">
					<div class="col-sm-12">
						<input id="memail_id" name="memail_id" type="hidden" value="">
                        <input id="mail_type" name="mail_type" type="hidden" value="sent">
                        <input id="staff_mail" name="staff_mail" type="hidden" value="">
                        <input id="uploaded_doc_id" name="uploaded_doc_id" type="hidden" value="">
						<select id="reassign_sent_client_id" name="reassign_sent_client_id" class="form-control mm-select js-reassign-client-ajax" style="width: 100%;" data-valid="required" data-placeholder="Search by name, email, or client ID...">
							<option value="">Select Client</option>
						</select>
					</div>
				</div>

                <div class="form-group row">
					<div class="col-sm-12">
						<select id="reassign_sent_client_matter_id" name="reassign_sent_client_matter_id" class="form-control mm-select " style="width: 100%;" disabled>
						</select>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" onclick="customValidate('sent-email-reassign-to-client-matter')">
					@icon('fa-save') Re-assign Sent Email
				</button>
			</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="sent_mail_preview_modal">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				  <h4 class="modal-title" id="memail_subject"></h4>
				  <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				  </button>
			</div>
			<div class="modal-body">
				<div class="form-group row">
					<div class="col-sm-12" id="memail_message">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

@endsection
@push('scripts')
<script>
// TinyMCE Configuration for Email Modals
var tinymceEmailConfig = {
    license_key: 'gpl',
    height: 300,
    menubar: false,
    plugins: ['lists', 'link', 'autolink'],
    toolbar: 'bold italic underline strikethrough | forecolor | bullist numlist | link',
    convert_urls: false,
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
    branding: false,
    promotion: false,
    color_map: [
        "000000", "Black", "333333", "Dark Gray", "666666", "Medium Gray",
        "999999", "Light Gray", "CCCCCC", "Very Light Gray", "E0E0E0", "Pale Gray",
        "F5F5F5", "Off White", "FFFFFF", "White", "DC2626", "Red",
        "EA580C", "Orange", "D97706", "Amber", "059669", "Green",
        "0891B2", "Cyan", "2563EB", "Blue", "7C3AED", "Purple",
        "DB2777", "Pink", "EF4444", "Light Red", "F97316", "Light Orange",
        "F59E0B", "Light Amber", "10B981", "Light Green", "06B6D4", "Light Cyan",
        "3B82F6", "Light Blue", "8B5CF6", "Light Purple", "EC4899", "Light Pink"
    ],
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
        });
    }
};

// Initialize TinyMCE for all email modals
function initTinyMCEForModals() {
    if (typeof tinymce === 'undefined') {
        var inShownModal = $('#compose_email_message, #sendmsg_message, #matter_email_message, #uploadmail_message').closest('.modal.show').length;
        if (inShownModal && typeof window.ensureTinyMCELoaded === 'function') {
            window.ensureTinyMCELoaded().then(initTinyMCEForModals);
        }
        return;
    }
    // Compose Email Modal
    if ($('#compose_email_message').length && !tinymce.get('compose_email_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#compose_email_message',
            init_instance_callback: function(editor) {
                // Handle modal show event
                $('#emailmodal').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
    
    // Send Message Modal
    if ($('#sendmsg_message').length && !tinymce.get('sendmsg_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#sendmsg_message',
            init_instance_callback: function(editor) {
                $('#sendmsgmodal').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
    
    // Application Email Modal
    if ($('#matter_email_message').length && !tinymce.get('matter_email_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#matter_email_message',
            init_instance_callback: function(editor) {
                $('#matteremailmodal').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
    
    // Upload Mail Modal
    if ($('#uploadmail_message').length && !tinymce.get('uploadmail_message')) {
        tinymce.init({
            ...tinymceEmailConfig,
            selector: '#uploadmail_message',
            init_instance_callback: function(editor) {
                $('#uploadmail').on('shown.bs.modal', function() {
                    editor.focus();
                });
            }
        });
    }
}

// Helper functions to save TinyMCE content before form validation
window.saveComposeEmail = function() {
    if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
        tinymce.get('compose_email_message').save();
    }
    customValidate('sendmail');
};

window.saveSendMessage = function() {
    if (typeof tinymce !== 'undefined' && tinymce.get('sendmsg_message')) {
        tinymce.get('sendmsg_message').save();
    }
    customValidate('sendmsg');
};

window.saveApplicationEmail = function() {
    if (typeof tinymce !== 'undefined' && tinymce.get('matter_email_message')) {
        tinymce.get('matter_email_message').save();
    }
    customValidate('appkicationsendmail');
};

window.saveUploadMail = function() {
    if (typeof tinymce !== 'undefined' && tinymce.get('uploadmail_message')) {
        tinymce.get('uploadmail_message').save();
    }
    customValidate('uploadmail');
};

// Helper function to set TinyMCE content (can be called from anywhere)
window.setTinyMCEContent = function(editorId, content) {
    if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
        tinymce.get(editorId).setContent(content || '');
    } else {
        $('#' + editorId).val(content || '');
        // Try to initialize if not already initialized
        setTimeout(function() {
            initTinyMCEForModals();
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                tinymce.get(editorId).setContent(content || '');
            }
        }, 200);
    }
};

// Initialize TinyMCE when DOM is ready
$(document).ready(function() {
    // Call getallactivities after page load if pending (from receipt save)
    var pendingClientId = localStorage.getItem('pendingGetActivities');
    if (pendingClientId && typeof getallactivities === 'function') {
        // Wait for page to fully load and account tab to be active
        setTimeout(function() {
            var activeTab = localStorage.getItem('activeTab');
            
            if (activeTab === 'accounts' || activeTab === 'account') {
                getallactivities(pendingClientId);
                localStorage.removeItem('pendingGetActivities');
            } else {
                // Retry after tab activation
                setTimeout(function() {
                    if (typeof getallactivities === 'function') {
                        getallactivities(pendingClientId);
                        localStorage.removeItem('pendingGetActivities');
                    }
                }, 1000);
            }
        }, 500);
    }
    
    initTinyMCEForModals();
    
    // Re-initialize when modals are shown (in case they're dynamically loaded)
    $('#emailmodal, #sendmsgmodal, #matteremailmodal, #uploadmail').on('shown.bs.modal', function() {
        setTimeout(function() {
            initTinyMCEForModals();
        }, 100);
    });
    
    // When compose modal opens: wait for CRM template/checklist lists, then apply matter defaults.
    // Checklist attachment checkboxes stay unchecked until the user selects them.
    $('#emailmodal').on('shown.bs.modal', function() {
        var runComposeShown = function() {
        var $templateSelect = $('#emailmodal select.selecttemplate');
        if (typeof window.initComposeEmailTemplateSelect === 'function') {
            if (!$('#compose_client_matter_id').val() && typeof window.restoreComposeEmailTemplateCrmOptions === 'function') {
                window.restoreComposeEmailTemplateCrmOptions($templateSelect);
            }
            window.initComposeEmailTemplateSelect($templateSelect);
        }
        var clientMatterId = $('#compose_client_matter_id').val();
        if (!clientMatterId || !window.ClientDetailConfig || !window.ClientDetailConfig.urls || !window.ClientDetailConfig.urls.getComposeDefaults) {
            window.composeChecklistFilterIds = null;
            if ($('#mychecklist-datatable').length && $.fn.DataTable && $.fn.DataTable.isDataTable('#mychecklist-datatable')) {
                $('#mychecklist-datatable').DataTable().draw();
            }
            $('#emailmodal').removeData('composeMacroValues').removeData('pdfUrlForSign').removeData('fromSignatureSend');
            $('#compose_signing_url').val('');
            return;
        }
        $.get(window.ClientDetailConfig.urls.getComposeDefaults, { client_matter_id: clientMatterId })
            .done(function(res) {
                var $checklistCbs = $('#emailmodal .checklistfile-cb');
                if (res.macro_values) {
                    var macroVals = res.macro_values;
                    var pdfUrl = ($('#emailmodal').data('pdfUrlForSign') || $('#compose_signing_url').val() || macroVals.PDF_url_for_sign || '').trim();
                    if (pdfUrl) {
                        macroVals = Object.assign({}, macroVals, { PDF_url_for_sign: pdfUrl });
                        $('#compose_signing_url').val(pdfUrl);
                        $('#emailmodal').data('pdfUrlForSign', pdfUrl);
                    }
                    $('#emailmodal').data('composeMacroValues', macroVals);
                } else {
                    $('#emailmodal').removeData('composeMacroValues');
                }
                if (res.matter_templates !== undefined && $templateSelect.length) {
                    // Replace dropdown with matter-specific options only: First Email first, then Matter Other Email Templates
                    $templateSelect.empty().append($('<option value="">Select</option>'));
                    (res.matter_templates || []).forEach(function(t) {
                        $templateSelect.append($('<option></option>').attr('value', t.id).text(t.name || 'Template'));
                    });
                    if (typeof window.syncComposeEmailTemplateSelectFromDom === 'function') {
                        window.syncComposeEmailTemplateSelectFromDom($templateSelect);
                    }
                    // Reply/Forward from client email tab sets preserveReplyForwardBody so quoted content is not replaced by a template
                    if (!$('#emailmodal').data('preserveReplyForwardBody')) {
                        var fromSignature = $('#emailmodal').data('fromSignatureSend');
                        var toSelect = res.template ? res.template.id : (res.matter_templates && res.matter_templates[0] ? res.matter_templates[0].id : null);
                        if (toSelect) {
                            $templateSelect.val(toSelect).trigger('change');
                            if (fromSignature) $('#emailmodal').removeData('fromSignatureSend');
                        }
                    } else {
                        // Keep body/subject from reply/forward; reset template UI without loading a template (empty val skips AJAX in .selecttemplate handler).
                        $templateSelect.val('').trigger('change');
                    }
                }
                // Filter checklist table by matter using DataTables API
                window.composeChecklistFilterIds = (res.checklist_ids && res.checklist_ids.length) ? res.checklist_ids : [];
                if ($('#mychecklist-datatable').length && $.fn.DataTable && $.fn.DataTable.isDataTable('#mychecklist-datatable')) {
                    $('#mychecklist-datatable').DataTable().draw();
                }
                $checklistCbs.prop('checked', false);
            })
            .fail(function() {
                window.composeChecklistFilterIds = null;
                if ($('#mychecklist-datatable').length && $.fn.DataTable && $.fn.DataTable.isDataTable('#mychecklist-datatable')) {
                    $('#mychecklist-datatable').DataTable().draw();
                }
            });
        };
        if (typeof window.ensureComposeOptionListsLoaded === 'function') {
            $.when(window.ensureComposeOptionListsLoaded()).always(runComposeShown);
        } else {
            runComposeShown();
        }
    });

    $('#emailmodal').on('hidden.bs.modal', function() {
        $('#compose_signing_url').val('');
        $(this).removeData('pdfUrlForSign').removeData('fromSignatureSend');
    });
});
</script>
@include('components.inputmask-scripts')
<script src="{{URL::to('/')}}/js/popover.js"></script>

{{-- Activity Feed Functionality --}}
<script src="{{ URL::asset('js/crm/clients/tabs/activity-feed.js') }}"></script>

{{-- Sidebar Tabs Management - Dedicated file for sidebar navigation --}}
<script src="{{URL::asset('js/crm/clients/sidebar-tabs.js')}}"></script>

{{-- Pass Blade variables to JavaScript --}}
<script>
    // Configuration object with all Blade variables needed for JavaScript
    window.ClientDetailConfig = {
        clientId: @json(($fetchedData->id ?? '')),
        encodeId: @json(($encodeId ?? '')),
        matterId: @json(($id1 ?? '')),
        activeTab: @json(($activeTab ?? 'companydetails')),
        matterRefNo: @json(($id1 ?? '')),
        clientFirstName: @json(($fetchedData->first_name ?? 'client')),
        notPickedCallSmsDefault: @json($notPickedCallSmsDefault ?? ''),
        // SMS Template Variables
        staffName: @json(($staffName ?? '')),
        matterNumber: @json(($matterNumber ?? '')),
        officePhone: @json(($officePhone ?? '')),
        officeCountryCode: @json(($officeCountryCode ?? '+61')),
        csrfToken: @json(csrf_token()),
        timezone: @json(config('app.timezone')),
        currentDate: @json(date('Y-m-d')),
        appId: @json(($_GET['appid'] ?? '')),
        // AWS Configuration for document URLs
        aws: {
            bucket: @json(env('AWS_BUCKET', '')),
            region: @json(env('AWS_DEFAULT_REGION', 'ap-southeast-2'))
        },
        urls: {
            base: '{{ URL::to("/") }}',
            admin: '{{ URL::to("/") }}',
            fetchVisaExpiryMessages: '{{ URL::to("/fetch-visa_expiry_messages") }}',
            downloadDocument: '{{ url("/documents/download") }}',
            getTopInvoiceNo: '{{ URL::to("/clients/getTopInvoiceNoFromDB") }}',
            getTopReceiptVal: '{{ URL::to("/clients/getTopReceiptValInDB") }}',
            listOfInvoice: '{{ URL::to("/clients/listOfInvoice") }}',
            clientLedgerBalance: '{{ URL::to("/clients/clientLedgerBalanceAmount") }}',
            getInvoicesByMatter: '{{ URL::to("/get-invoices-by-matter") }}',
            loadMatterUpsert: '{{ URL::to("/client-portal/load-matter-upsert") }}',
            getClientPortalDetail: '{{ URL::to("/client-portal/detail") }}',
            updateIntake: '{{ URL::to("/client-portal/updateintake") }}',
            updateExpectWin: '{{ URL::to("/client-portal/updateexpectwin") }}',
            updateDates: '{{ URL::to("/client-portal/updatedates") }}',
            updateNoteDatetime: '{{ URL::to("/update-note-datetime") }}',
            referencesStore: '{{ route("references.store") }}',
            updateClientFundsLedger: '{{ route("clients.update-client-funds-ledger") }}',
            getMigrationAgentDetail: '{{ URL::to("/clients/getMigrationAgentDetail") }}',
            createIntakeUrl: '{{ url("/clients/store-application-doc-via-form") }}',
            toggleClientPortal: '{{ route("clients.toggleClientPortal") }}',
            enhanceMail: '{{ route("mail.enhance") }}',
            composeEmail: '{{ URL::to("/sendmail") }}',
            createNote: '{{ URL::to("/create-note") }}',
            getNoteDetail: '{{ URL::to("/getnotedetail") }}',
            deleteNote: '{{ URL::to("/deletenote") }}',
            checkStarClient: '{{ route("check.star.client") }}',
            getInfoByReceiptId: '{{ URL::to("/clients/getInfoByReceiptId") }}',
            notPickedCall: '{{ URL::to("/not-picked-call") }}',
            getDateTimeBackend: '{{ URL::to("/getdatetimebackend") }}',
            getDisabledDateTime: '{{ URL::to("/getdisableddatetime") }}',
            checkCostAssignment: '{{ URL::to("/clients/check-cost-assignment") }}',
            getVisaAgreementAgent: '{{ URL::to("/clients/getVisaAggreementMigrationAgentDetail") }}',
            generateAgreement: '{{ route("clients.generateagreement") }}',
            getCostAssignmentAgent: '{{ URL::to("/clients/getCostAssignmentMigrationAgentDetail") }}',
            getCostAssignmentAgentLead: '{{ URL::to("/clients/getCostAssignmentMigrationAgentDetailLead") }}',
            uploadAgreement: '{{ route("clients.uploadAgreement", $fetchedData->id) }}',
            fetchClientContactNo: '{{ URL::to("/clients/fetchClientContactNo") }}',
            followupStore: '{{ URL::to("/clients/action/store") }}',
            // publishDoc, deleteClientPortalDoc REMOVED - workflow checklist unused
            deleteCostagreement: '{{ URL::to("/deletecostagreement") }}',
            deleteAction: '{{ URL::to("/delete_action") }}',
            pinNote: '{{ URL::to("/pinnote") }}',
            pinActivityLog: '{{ URL::to("/pinactivitylog") }}',
            getRecipients: '{{ URL::to("/clients/get-recipients") }}',
            updateSessionCompleted: '{{ URL::to("/clients/update-session-completed") }}',
            viewNoteDetail: '{{ URL::to("/viewnotedetail") }}',
            viewMatterNote: '{{ URL::to("/viewmatternote") }}',
            changeClientStatus: '{{ URL::to("/change-client-status") }}',
            getTemplates: '{{ URL::to("/get-templates") }}',
            getComposeDefaults: '{{ URL::to("/get-compose-defaults") }}',
            getComposeOptionLists: '{{ URL::to("/get-compose-option-lists") }}',
            getPartner: '{{ URL::to("/getpartner") }}',
            renameDoc: '{{ URL::to("/documents/rename") }}',
            renameChecklistDoc: '{{ URL::to("/documents/rename-checklist") }}',
            deleteChecklist: '{{ route("clients.documents.deleteChecklist") }}',
            getInterestedService: '{{ URL::to("/getintrestedservice") }}',
            getInterestedServiceEdit: '{{ URL::to("/getintrestedserviceedit") }}',
            fetchClientMatterAssignee: '{{ URL::to("/clients/fetchClientMatterAssignee") }}',
            updateStage: '{{ URL::to("/updatestage") }}',
            completeStage: '{{ URL::to("/completestage") }}',
            updateBackStage: '{{ URL::to("/updatebackstage") }}',
            getMatterNotes: '{{ URL::to("/client-portal/notes") }}',
            sendToHubdoc: '{{ url("/clients/sendToHubdoc") }}',
            checkHubdocStatus: '{{ url("/clients/checkHubdocStatus") }}',
            sendToClientApplication: '{{ url("/clients/send-invoice-to-client-application") }}',
            updateMailReadBit: '{{ URL::to("/clients/updatemailreadbit") }}',
            listAllMatters: '{{ URL::to("/clients/listAllMattersWRTSelClient") }}',
            getActivities: '{{ route("clients.activities") }}',
            getNotes: '{{ URL::to("/get-notes") }}',
            updatePersonalCategory: '{{ route("clients.documents.updatePersonalDocCategory") }}',
            updateVisaCategory: '{{ route("clients.documents.updateVisaDocCategory") }}',
            updateNominationCategory: '{{ route("clients.documents.updateNominationDocCategory") }}',
            deletePersonalCategory: '{{ route("clients.documents.deletePersonalDocCategory") }}',
            deleteVisaCategory: '{{ route("clients.documents.deleteVisaDocCategory") }}',
            sendInvoiceToClient: '{{ url("/clients/send-invoice-to-client") }}',
            sendClientFundReceiptToClient: '{{ url("/clients/send-client-fund-receipt-to-client") }}',
            sendOfficeReceiptToClient: '{{ url("/clients/send-office-receipt-to-client") }}',
            updateNextStage: '{{ route("clients.matter.update-next-stage") }}',
            updatePreviousStage: '{{ route("clients.matter.update-previous-stage") }}',
            updateDeadline: '{{ route("clients.matter.update-deadline") }}',
            changeWorkflow: '{{ route("clients.matter.change-workflow") }}',
            discontinue: '{{ route("clients.matter.discontinue") }}',
            reopen: '{{ route("clients.matter.reopen") }}',
        }
    };
    
    // Global function to load activities feed (paginated via /get-activities)
    window.ActivityFeedState = window.ActivityFeedState || { page: 1, hasMore: false, loading: false, pendingReset: false };

    window.loadActivities = function(options) {
        var opts = options || {};
        var reset = opts.reset !== false;
        var append = opts.append === true;

        if (window.ActivityFeedState.loading) {
            if (reset && !append) {
                window.ActivityFeedState.pendingReset = true;
            }
            return;
        }

        if (reset) {
            window.ActivityFeedState.pendingReset = false;
            window.ActivityFeedState.page = 1;
            window.ActivityFeedState.hasMore = false;
            $('.activity-feed').scrollTop(0);
        } else if (append) {
            window.ActivityFeedState.page = (window.ActivityFeedState.page || 1) + 1;
        }

        window.ActivityFeedState.loading = true;
        $('#activity-feed-loading').show();
        $('#activity-feed-load-more').prop('disabled', true);

        var requestData = {
            id: window.ClientDetailConfig.clientId,
            page: window.ActivityFeedState.page,
            per_page: 40
        };

        var urlParams = new URLSearchParams(window.location.search);
        var staffFilter = urlParams.get('staff') || urlParams.get('user');
        var keywordFilter = urlParams.get('keyword');
        if (staffFilter) {
            requestData.staff = staffFilter;
        }
        if (keywordFilter) {
            requestData.keyword = keywordFilter;
        }

        $.ajax({
            url: window.ClientDetailConfig.urls.getActivities,
            type: 'GET',
            dataType: 'json',
            data: requestData,
            success: function(response) {
                if (response.status && response.data) {
                    // Escape template literal special characters to prevent syntax errors
                    function escapeTemplateLiteral(str) {
                        if (!str) return '';
                        return String(str)
                            .replace(/\\/g, '\\\\')
                            .replace(/`/g, '\\`')
                            .replace(/\$\{/g, '\\${');
                    }
                    
                    var html = '';
                    
                    $.each(response.data, function (k, v) {
                        var activityType = v.activity_type ?? '';
                        var noteSubtypeClass = '';
                        var subjectIcon;
                        var iconClass = '';
                        var subject = escapeTemplateLiteral(v.subject ?? '');
                        var subjectLower = subject.toLowerCase();
                        var rawMessage = v.message ?? '';
                        var isAppointmentActivity = String(rawMessage).indexOf('appointment-activity-detail') !== -1;

                        if (activityType === 'sms') {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-sms') : '<i class="fas fa-sms"></i>');
                            iconClass = 'feed-icon-sms';
                        } else if (activityType === 'note') {
                            var noteIcon = 'fa-sticky-note';
                            if (subjectLower.indexOf('call') !== -1) { noteIcon = 'fa-phone'; noteSubtypeClass = ' activity-type-note-call'; }
                            else if (subjectLower.indexOf('email') !== -1) { noteIcon = 'fa-envelope'; noteSubtypeClass = ' activity-type-note-email'; }
                            else if (subjectLower.indexOf('in-person') !== -1) { noteIcon = 'fa-user-friends'; noteSubtypeClass = ' activity-type-note-in-person'; }
                            else if (subjectLower.indexOf('attention') !== -1) { noteIcon = 'fa-exclamation-triangle'; noteSubtypeClass = ' activity-type-note-attention'; }
                            else if (subjectLower.indexOf('others') !== -1) { noteIcon = 'fa-ellipsis-h'; noteSubtypeClass = ' activity-type-note-others'; }
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas ' + noteIcon) : '<i class="fas ' + noteIcon + '"></i>');
                            iconClass = 'feed-icon-note';
                        } else if (activityType === 'activity') {
                            if (isAppointmentActivity) {
                                subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-calendar-check') : '<i class="fas fa-calendar-check"></i>');
                                iconClass = 'feed-icon-appointment';
                            } else {
                                subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-bolt') : '<i class="fas fa-bolt"></i>');
                                iconClass = 'feed-icon-activity';
                            }
                        } else if (activityType === 'stage') {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-route') : '<i class="fas fa-route"></i>');
                            iconClass = 'feed-icon-stage';
                        } else if (activityType === 'financial') {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-dollar-sign') : '<i class="fas fa-dollar-sign"></i>');
                            iconClass = 'feed-icon-financial';
                        } else if (activityType === 'email') {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-envelope') : '<i class="fas fa-envelope"></i>');
                            iconClass = 'feed-icon-email';
                        } else if (activityType === 'signature') {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-file-signature') : '<i class="fas fa-file-signature"></i>');
                            iconClass = 'feed-icon-signature';
                        } else if (activityType === 'document') {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-file-alt') : '<i class="fas fa-file-alt"></i>');
                            iconClass = '';
                        } else if (/uploaded email:/i.test(subjectLower)) {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-envelope') : '<i class="fas fa-envelope"></i>');
                            iconClass = 'feed-icon-email';
                        } else if (subjectLower.includes('invoice') || subjectLower.includes('receipt') || subjectLower.includes('ledger') || subjectLower.includes('payment') || subjectLower.includes('account')) {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-dollar-sign') : '<i class="fas fa-dollar-sign"></i>');
                            iconClass = 'feed-icon-financial';
                        } else if (subjectLower.includes('document') && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-file-alt') : '<i class="fas fa-file-alt"></i>');
                            iconClass = '';
                        } else if (subjectLower.includes('document')) {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-file-alt') : '<i class="fas fa-file-alt"></i>');
                            iconClass = '';
                        } else {
                            subjectIcon = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-sticky-note') : '<i class="fas fa-sticky-note"></i>');
                            iconClass = '';
                        }

                        var description = escapeTemplateLiteral(rawMessage);
                        var taskGroup = escapeTemplateLiteral(v.task_group ?? '');
                        var followupDate = escapeTemplateLiteral(v.followup_date ?? '');
                        var date = escapeTemplateLiteral(v.date ?? '');
                        var fullName = escapeTemplateLiteral(v.name ?? '');
                        var activityTypeClass = activityType ? 'activity-type-' + activityType : '';
                        if (!activityTypeClass) {
                            if (/uploaded email:/i.test(subjectLower)) {
                                activityTypeClass = 'activity-type-email';
                            } else if (subjectLower.includes('invoice') || subjectLower.includes('receipt') || subjectLower.includes('ledger') || subjectLower.includes('payment') || subjectLower.includes('account')) {
                                activityTypeClass = 'activity-type-financial';
                            } else if (subjectLower.includes('document') && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
                                activityTypeClass = 'activity-type-document';
                            }
                        }

                        var descriptionHtml = description !== '' ? '<p>' + description + '</p>' : '';
                        var taskGroupHtml = taskGroup !== '' ? '<p>' + taskGroup + '</p>' : '';
                        var followupDateHtml = followupDate !== '' ? '<p>' + followupDate + '</p>' : '';

                        var feedItemClass = activityType === 'stage' ? 'feed-item--stage' : 'feed-item--email';
                        var contentHtml;
                        if (activityType === 'stage') {
                            contentHtml = '<div class="feed-item-stage">' +
                                '<div class="feed-item-stage-header">' +
                                    '<span class="feed-item-staff">' + fullName + '</span>' +
                                    '<span class="feed-timestamp">' + date + '</span>' +
                                '</div>' +
                                '<div class="feed-item-stage-body">' + (v.message ? v.message : '') + '</div>' +
                            '</div>';
                        } else {
                            var subjectOnly = v.subject_without_staff_prefix === true;
                            var headline = subjectOnly ? subject : (fullName + ' ' + subject);
                            contentHtml = '<p><strong>' + headline + '</strong></p>' +
                                descriptionHtml +
                                taskGroupHtml +
                                followupDateHtml +
                                '<span class="feed-timestamp">' + date + '</span>';
                        }

                        var createdAtYmd = v.created_at_ymd || '';
                        var appointmentFeedClass = isAppointmentActivity ? ' feed-item--appointment' : '';
                        html += '<li class="feed-item ' + feedItemClass + ' activity ' + activityTypeClass + noteSubtypeClass + appointmentFeedClass + '" id="activity_' + v.activity_id + '" data-created-at="' + createdAtYmd + '">' +
                            '<span class="feed-icon ' + iconClass + '">' +
                                subjectIcon +
                            '</span>' +
                            '<div class="feed-content">' + contentHtml + '</div>' +
                        '</li>';
                    });

                    if (append) {
                        $('.feed-list .feed-item.activity').last().after(html);
                    } else {
                        $('.feed-list .feed-item.activity').remove();
                        var $emptyItem = $('.feed-list .feed-item--empty');
                        if ($emptyItem.length) {
                            $emptyItem.before(html);
                        } else if ($('.feed-list').length) {
                            $('.feed-list').prepend(html);
                        }
                    }

                    window.ActivityFeedState.hasMore = !!response.has_more;
                    $('#activity-feed-load-more-wrap').toggle(window.ActivityFeedState.hasMore);

                    if (typeof adjustActivityFeedHeight === 'function') {
                        adjustActivityFeedHeight();
                    }
                    if (window.ActivityFeed && typeof window.ActivityFeed.reapplyCurrentFilter === 'function') {
                        window.ActivityFeed.reapplyCurrentFilter();
                    }
                    if (window.ActivityFeed && typeof window.ActivityFeed.enhanceAppointmentActivityRows === 'function') {
                        window.ActivityFeed.enhanceAppointmentActivityRows();
                    }
                } else {
                    console.error('Failed to load activities:', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading activities:', error);
            },
            complete: function() {
                window.ActivityFeedState.loading = false;
                $('#activity-feed-loading').hide();
                $('#activity-feed-load-more').prop('disabled', false);
                if (window.ActivityFeed && typeof window.ActivityFeed.afterActivitiesLoaded === 'function') {
                    window.ActivityFeed.afterActivitiesLoaded();
                }
                if (window.ActivityFeedState.pendingReset) {
                    window.ActivityFeedState.pendingReset = false;
                    window.loadActivities({ reset: true });
                }
            }
        });
    };

    $(document).ready(function() {
        if ($('.activity-feed').length && typeof window.loadActivities === 'function') {
            window.loadActivities({ reset: true });
        }
    });
</script>

{{-- Newly added external JS placeholders for progressive migration --}}
<script src="{{ URL::asset('js/crm/clients/shared.js') }}" defer></script>
<script src="{{ URL::asset('js/crm/clients/detail.js') }}" defer></script>
<script src="{{ URL::asset('js/crm/clients/tabs/client_portal.js') }}" defer></script>

{{-- Client detail utilities (must load before detail-main.js) --}}
<script src="{{ URL::asset('js/crm/clients/utils/flatpickr-helpers.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/utils/editor-helpers.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/utils/dom-helpers.js') }}"></script>
{{-- Phase 3 modules --}}
<script src="{{ URL::asset('js/crm/clients/modules/references.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/send-to-client.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/notes.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/checklist.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/checklist.js')) ? filemtime(public_path('js/crm/clients/modules/checklist.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/documents.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/documents.js')) ? filemtime(public_path('js/crm/clients/modules/documents.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/signature-placement.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/signature-placement.js')) ? filemtime(public_path('js/crm/clients/modules/signature-placement.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/accounts.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/invoices.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/appointments.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/visa-expiry.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/subtabs.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/ledger-dragdrop.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/workflow-tab.js') }}?v={{ time() }}"></script>
{{-- Main detail page JavaScript --}}
<script src="{{ URL::asset('js/crm/clients/detail-main.js') }}?v={{ time() }}"></script>

{{-- Sidebar Toggle JavaScript --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const collapsedToggle = document.getElementById('collapsed-toggle');
    const sidebar = document.getElementById('client-sidebar');
    const container = document.querySelector('.crm-container');
    
    // Check if sidebar state is saved in localStorage
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    // Apply initial state
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        container.classList.add('sidebar-collapsed');
    }
    
    // Hide sidebar functionality
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.add('collapsed');
        container.classList.add('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'true');
    });
    
    // Show sidebar functionality
    collapsedToggle.addEventListener('click', function() {
        sidebar.classList.remove('collapsed');
        container.classList.remove('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', 'false');
    });
});

// SMS Modal Functionality
// Declare global variables for SMS functionality
let smsClientId = null;
let smsClientName = null;

$('.send-sms-btn').on('click', function() {
    smsClientId = $(this).data('client-id');
    smsClientName = $(this).data('client-name');
    
    $('#sms_client_id').val(smsClientId);
    $('#smsModalLabel').text(`Send SMS to ${smsClientName}`);
    
    // Show loading state
    const phoneSelect = $('#sms_phone');
    phoneSelect.empty();
    phoneSelect.append('<option value="">Loading phone numbers...</option>');
    
    // Load client phone numbers
    $.ajax({
        url: '{{ URL::to("/clients/fetchClientContactNo") }}',
        type: 'POST',
        dataType: 'json',
        data: {
            _token: '{{ csrf_token() }}',
            client_id: smsClientId
        },
        success: function(response) {
            console.log('Phone numbers response:', response);
            phoneSelect.empty();
            phoneSelect.append('<option value="">Select phone number...</option>');
            
            // Parse response if it's a string (fallback for older jQuery versions)
            const data = (typeof response === 'string') ? $.parseJSON(response) : response;
            
            if (data && data.clientContacts && data.clientContacts.length > 0) {
                data.clientContacts.forEach(function(contact) {
                    console.log('Processing contact:', contact);
                    // Handle missing fields gracefully
                    const countryCode = contact.country_code || '';
                    const phone = contact.phone || '';
                    const contactType = contact.contact_type || 'Phone';
                    const fullPhone = countryCode + phone;
                    const label = contactType + ': ' + fullPhone;
                    phoneSelect.append(`<option value="${fullPhone}">${label}</option>`);
                });
            } else {
                phoneSelect.append('<option value="">No phone numbers found</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch phone numbers:', error);
            phoneSelect.empty();
            phoneSelect.append('<option value="">Error loading phone numbers</option>');
            iziToast.error({
                title: 'Error',
                message: 'Failed to load phone numbers. Please try again.',
                position: 'topRight'
            });
        }
    });
    
    // Load SMS templates
    $.ajax({
        url: '{{ route("clients.sms.templates.active") }}',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            const templateSelect = $('#sms_template');
            templateSelect.empty();
            templateSelect.append('<option value="">Type your own message or select a template...</option>');
            
            if (response.success && response.data && response.data.length > 0) {
                response.data.forEach(function(template) {
                    $('<option></option>').val(template.id).text(template.title).appendTo(templateSelect);
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to fetch SMS templates:', error);
            const templateSelect = $('#sms_template');
            templateSelect.empty();
            templateSelect.append('<option value="">Error loading templates</option>');
            iziToast.error({
                title: 'Error',
                message: 'Failed to load SMS templates. Please try again.',
                position: 'topRight'
            });
        }
    });
    
    // Reset form — trigger('input') re-runs the counter so badge/max/remaining all reset
    $('#sms_message').val('').trigger('input');
    $('#sms_template').val('');

    $('#sendSmsModal').modal('show');
});

// Template selection (body loaded via API — avoids broken data-* with quotes/newlines)
$('#sms_template').on('change', function() {
    const id = $(this).val();
    if (!id) {
        return;
    }
    $.ajax({
        url: '/clients/sms-template/' + id + '/compose',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (!response.success || !response.data) {
                iziToast.error({
                    title: 'Error',
                    message: response.message || 'Could not load SMS template',
                    position: 'topRight'
                });
                return;
            }
            const name = (typeof smsClientName === 'string') ? smsClientName : '';
            let processedMessage = response.data.message || '';
            processedMessage = processedMessage.replace(/\{first_name\}/g, name.split(' ')[0] || '');
            processedMessage = processedMessage.replace(/\{last_name\}/g, name.split(' ').slice(1).join(' ') || '');
            processedMessage = processedMessage.replace(/\{client_name\}/g, name);
            processedMessage = processedMessage.replace(/\{full_name\}/g, name);
            processedMessage = processedMessage.replace(/\{staff_name\}/g, window.ClientDetailConfig.staffName || '');
            processedMessage = processedMessage.replace(/\{matter_number\}/g, window.ClientDetailConfig.matterNumber || '');
            const officePhone = window.ClientDetailConfig.officeCountryCode + window.ClientDetailConfig.officePhone;
            processedMessage = processedMessage.replace(/\{office_phone\}/g, officePhone || '');
            var smsBodyMaxChars = 320;
            if (processedMessage.length > smsBodyMaxChars) {
                processedMessage = processedMessage.slice(0, smsBodyMaxChars);
                iziToast.warning({
                    title: 'SMS length',
                    message: 'Template message was shortened to ' + smsBodyMaxChars + ' characters (2 SMS max).',
                    position: 'topRight'
                });
            }
            $('#sms_message').val(processedMessage).trigger('input');
        },
        error: function() {
            iziToast.error({
                title: 'Error',
                message: 'Could not load SMS template',
                position: 'topRight'
            });
        }
    });
});

// Character counter
$('#sms_message').on('input', function() {
    var len     = $(this).val().length;
    var segSize = 160;
    var segs    = Math.max(1, Math.ceil(len / segSize));
    var left    = (segs * segSize) - len;

    $('#sms_char_count').text(len);
    $('#sms_char_max').text(segs * segSize);
    $('#sms_chars_remaining').html('&nbsp;&middot;&nbsp; ' + left + ' left in this SMS');
    $('#sms_segment_badge')
        .text(segs + ' SMS')
        .removeClass('badge-success badge-warning')
        .addClass(segs === 1 ? 'badge-success' : 'badge-warning');
});

// Form submission
$('#sendSmsForm').on('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = $('#sendSmsBtn');
    const originalText = submitBtn.html();
    
    submitBtn.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Sending...');
    
    const formData = {
        _token: '{{ csrf_token() }}',
        client_id: $('#sms_client_id').val(),
        phone: $('#sms_phone').val(),
        message: $('#sms_message').val()
    };
    
    $.ajax({
        url: '{{ route("clients.sms.send") }}',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                iziToast.success({
                    title: 'Success',
                    message: 'SMS sent successfully!',
                    position: 'topRight'
                });
                $('#sendSmsModal').modal('hide');
                
                // Reload activity feed if exists
                if (typeof loadActivities === 'function') {
                    loadActivities();
                }
            } else {
                iziToast.error({
                    title: 'Error',
                    message: response.message || 'Failed to send SMS',
                    position: 'topRight'
                });
            }
        },
        error: function(xhr) {
            let errorMessage = 'An error occurred while sending SMS';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            }
            iziToast.error({
                title: 'Error',
                message: errorMessage,
                position: 'topRight'
            });
        },
        complete: function() {
            submitBtn.prop('disabled', false).html(originalText);
        }
    });
});
</script>

@endpush
