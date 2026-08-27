@extends('layouts.crm_client_detail')
@section('title', 'Client Detail')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="{{ URL::asset('css/client-detail.css') }}">

<?php
use App\Http\Controllers\Controller;
?>
<div class="crm-container" data-client-id="{{ $fetchedData->id }}">
    <!-- Collapsed Toggle Button (shown when sidebar is collapsed) -->
    <button id="collapsed-toggle" class="collapsed-toggle-btn" title="Show Sidebar">
        â˜°
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
                    if($id1) {
                    ?>
                        {{$fetchedData->client_id}}-{{$matter_info_arr ? $matter_info_arr->client_unique_matter_no : 'N/A'}}
                    <?php
                    } else {
                        if(($matter_cnt ?? 0) > 0){
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
                {{-- Personal Lead Display --}}
                <p class="client-name">
                    {{$fetchedData->first_name}} {{$fetchedData->last_name}} 
                    <a href="{{route('clients.edit', base64_encode(convert_uuencode(@$fetchedData->id)))}}" title="Client Details Form" class="client-name-edit">
                        @icon('fa-id-card')
                    </a>
                </p>
                
                <!-- Action Icons (left) and Client Portal Toggle (right) -->
                <div class="sidebar-actions-row">
                    <!-- Action Icons -->
                    <div class="client-actions">
                        <a href="javascript:;" class="create_note_d" datatype="note" title="Add Notes">@icon('fa-plus')</a>
                        <a href="javascript:;" data-id="{{@$fetchedData->id}}" data-email="{{@$fetchedData->email}}" data-name="{{@$fetchedData->first_name}} {{@$fetchedData->last_name}}" class="clientemail" title="Compose Mail">@icon('fa-envelope')</a>
                        @php
                            $googleReviewTemplate = \App\Models\EmailTemplate::crm()
                                ->where(function ($q) {
                                    $q->where('alias', 'google_review')->orWhere('name', 'like', '%Google Review%');
                                })
                                ->orderBy('id')
                                ->first();
                        @endphp
                        <a href="javascript:;" class="send-google-review" data-id="{{@$fetchedData->id}}" data-email="{{@$fetchedData->email}}" data-name="{{@$fetchedData->first_name}} {{@$fetchedData->last_name}}" data-template-id="{{ optional($googleReviewTemplate)->id ?? '' }}" title="Send Google Review">@icon('fa-google')</a>
                        <a href="javascript:;" class="send-sms-btn" data-client-id="{{@$fetchedData->id}}" data-client-name="{{@$fetchedData->first_name}} {{@$fetchedData->last_name}}" title="Send SMS">@icon('fa-sms')</a>
                        <a href="javascript:;" datatype="not_picked_call" class="not_picked_call" title="Not Picked Call">@icon('fa-mobile-alt')</a>
                        <a href="javascript:;" data-bs-toggle="modal" data-bs-target="#create_appoint" title="Add Appointment">@icon('fa-calendar-plus')</a>
                    </div>
                    
                </div>
            </div>
            
            <!-- Client/Lead status badge (display only, no click action) -->
            @if(($fetchedData->type ?? '') === 'lead')
            <div class="sidebar-client-lead-buttons">
                <span class="status-btn status-btn-lead lead-status-badge active">Lead</span>
            </div>
            @elseif(($fetchedData->type ?? '') === 'client')
            <div class="sidebar-client-lead-buttons">
                <span class="status-btn status-btn-client client-status-badge active">Client</span>
            </div>
            @endif
            
            <!-- Matter Selection Dropdown in Sidebar -->
            <div class="sidebar-matter-selection">
                @if($fetchedData->type)
                    @if(!empty($showMatterDropdown) && !empty($matter_list_arr))
                        <select name="matter_id" id="sel_matter_id_client_detail" class="form-control mm-select visa-dropdown" data-valid="required">
                            <option value="">Select Matters</option>
                            @foreach($matter_list_arr as $matterlist)
                                @php
                                    $matterName = 'General Matter';
                                    if ($matterlist->sel_matter_id != 1 && !empty($matterlist->title)) {
                                        $matterName = $matterlist->title;
                                    }
                                @endphp
                                <option value="{{$matterlist->id}}" {{ $matterlist->id == ($latestClientMatterId ?? null) ? 'selected' : '' }} data-clientuniquematterno="{{@$matterlist->client_unique_matter_no}}" data-sel-matter-id="{{@$matterlist->sel_matter_id}}">{{$matterName}}({{@$matterlist->client_unique_matter_no}})</option>
                            @endforeach
                        </select>
                    @endif
                @endif
            </div>
            
            <div class="matter-status-badge">
                <?php
                if ($workflow_stage_arr && $workflow_stage_arr->name) {
                    echo $workflow_stage_arr->name;
                } else {
                    echo 'N/A';
                }
                ?>
            </div>
            
            <!-- Matter References Section -->
            <div class="sidebar-references">
                <div class="sidebar-references-label" style="font-size: 0.75rem; font-weight: 600; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">Reference</div>
                
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
                    <div class="details-verify-line"><strong>Verified By:</strong> {{ $detailsVerifiedByName ?: 'â€”' }}</div>
                    <div class="details-verify-line"><strong>Verified At:</strong> {{ $fetchedData->details_verified_at->format('d/m/Y g:i A') }}</div>
                </div>
                @endif
            </div>
        </div>
        <nav class="client-sidebar-nav">
            <?php
            // Show client menu if: valid matter ID in URL OR client has any matters
            if( ($isMatterIdInUrl ?? false) || ($matter_cnt ?? 0) > 0 )
            {  //if client unique reference id is present in url
            ?>
                <button class="client-nav-button active" data-tab="personaldetails">
                    @icon('fa-user')
                    <span>Personal Details</span>
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
                    <span>Personal Documents</span>
                </button>
                <button class="client-nav-button" data-tab="visadocuments">
                    @icon('fa-file-contract')
                    <span>Visa Documents</span>
                </button>
                @if(isset($isEoiMatter) && $isEoiMatter)
                <button class="client-nav-button" data-tab="eoiroi">
                    @icon('fa-passport')
                    <span>EOI / ROI</span>
                </button>
                @endif
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
                <button class="client-nav-button active" data-tab="personaldetails">
                    @icon('fa-user')
                    <span>Personal Details</span>
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
                    <span>Personal Documents</span>
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
            {{-- Always a stub: personaldetails-tab.js fetches the fragment, including default /personaldetails. --}}
            @include('crm.clients.tabs.personal_details_lazy')
            
            @include('crm.clients.tabs.activityfeed_tab')
            
            @if(($activeTab ?? '') === 'noteterm')
                @include('crm.clients.tabs.notes')
            @else
                @include('crm.clients.tabs.notes_lazy')
            @endif
            
            @if(($activeTab ?? '') === 'personaldocuments')
                @include('crm.clients.tabs.personal_documents')
            @else
                @include('crm.clients.tabs.personal_documents_lazy')
            @endif
            
            @if((isset($id1) && $id1 != "") || ($matter_cnt ?? 0) > 0)
                @if(($activeTab ?? '') === 'visadocuments')
                    @include('crm.clients.tabs.visa_documents')
                @else
                    @include('crm.clients.tabs.visa_documents_lazy')
                @endif
                
                @if(isset($isEoiMatter) && $isEoiMatter)
                    @include('crm.clients.tabs.eoi_roi')
                @endif
                
                {{-- Always a stub: account-tab.js fetches the fragment, including deep-link /account. --}}
                @include('crm.clients.tabs.account_lazy')
                @if(($activeTab ?? '') === 'emails')
                    @include('crm.clients.tabs.emails')
                @else
                    @include('crm.clients.tabs.emails_lazy')
                @endif
                {{-- Always a stub: checklists-tab.js fetches the fragment, including deep-link /checklists. --}}
                @include('crm.clients.tabs.checklists_lazy')
                @if(($activeTab ?? '') === 'workflow')
                    @include('crm.clients.tabs.workflow')
                @else
                    @include('crm.clients.tabs.workflow_lazy')
                @endif
                @if(($activeTab ?? '') === 'client_portal')
                    @include('crm.clients.tabs.client_portal')
                @else
                    @include('crm.clients.tabs.client_portal_lazy')
                @endif
            @else
                {{-- Always a stub: checklists-tab.js fetches the fragment, including deep-link /checklists. --}}
                @include('crm.clients.tabs.checklists_lazy')
            @endif
            
            @if(($activeTab ?? '') === 'notuseddocuments')
                @include('crm.clients.tabs.not_used_documents')
            @else
                @include('crm.clients.tabs.not_used_documents_lazy')
            @endif
            
            </div>
        </div>
    </main>

    <!-- Activity Feed (Personal Details, Activity nav, etc.) -->
    @include('crm.clients.tabs.activity_feed')
</div>

{{-- Compose/SMS/notes/add-edit/management: lightweight ID stubs; HTML fetched on first open. Check-in stays in the layout. --}}
@include('crm.clients.modals.lazy_stubs')

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

<!-- confirmEducationModal removed - education system deprecated (replaced by ClientQualification) -->

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


@if($showGoogleReviewReminderModal ?? false)
<div class="modal fade custom_modal google-review-reminder-modal" id="googleReviewReminderModal" tabindex="-1" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="googleReviewReminderModalLabel" aria-describedby="googleReviewReminderModalDesc googleReviewReminderModalHint" data-backdrop="static" data-keyboard="false" data-auto-open="1">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="googleReviewReminderModalLabel">@icon('fa-google', ['class' => 'mr-2'])Google review reminder</h5>
				<button type="button" class="close grr-modal-close-btn js-google-review-reminder" data-action="snooze_one_day" aria-label="Close and remind again tomorrow" title="Close â€” ask again tomorrow">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="mb-2 grr-modal-text" id="googleReviewReminderModalDesc">Has this contact been asked to leave a Google review? Choose an option so we know whether to remind you next time you open their record.</p>
				<p class="mb-0 small grr-modal-hint" id="googleReviewReminderModalHint">Closing with the Ã— button above hides this until tomorrow (one-day snooze).</p>
			</div>
			<div class="modal-footer flex-wrap justify-content-stretch gap-2 grr-modal-footer">
				<button type="button" class="btn w-100 m-0 js-google-review-send-sms grr-btn grr-btn-sms">
					@icon('fa-sms', ['class' => 'mr-1'])Send SMS with review link
				</button>
				<button type="button" class="btn flex-grow-1 m-0 js-google-review-reminder grr-btn grr-btn-not-interested" data-action="not_interested">Not interested</button>
				<button type="button" class="btn flex-grow-1 m-0 js-google-review-reminder grr-btn grr-btn-snooze" data-action="snooze">Remind me in 1 week</button>
				<button type="button" class="btn flex-grow-1 m-0 js-google-review-reminder grr-btn grr-btn-received" data-action="review_received">Review received</button>
			</div>
		</div>
	</div>
</div>
@endif

@endsection
@push('scripts')
{{-- TinyMCE is already loaded by layouts.crm_client_detail --}}
<script>
// TinyMCE Configuration for Email Modals
var tinymceEmailConfig = {
    license_key: 'gpl',
    height: 300,
    menubar: false,
    plugins: ['lists', 'link', 'autolink'],
    toolbar: 'bold italic underline strikethrough | forecolor | bullist numlist | link',
    convert_urls: false,
    extended_valid_elements: 'table[border|cellpadding|cellspacing|width|style|class|align],thead,tbody,tfoot,tr[class|style],td[class|style|colspan|rowspan|align|valign|width],th[class|style|colspan|rowspan|align|valign|width],colgroup,col[span|width],hr[style|width]',
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

window.initTinyMCEForModals = initTinyMCEForModals;

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
<script src="{{URL::asset('js/crm/clients/sidebar-tabs.js')}}?v={{ file_exists(public_path('js/crm/clients/sidebar-tabs.js')) ? filemtime(public_path('js/crm/clients/sidebar-tabs.js')) : time() }}"></script>

{{-- Pass Blade variables to JavaScript --}}
<script>
    // Fallback until Vite lucide-init.js loads (module scripts run after inline handlers register).
    if (typeof window.crmI !== 'function') {
        window.crmI = function(legacyClass, options) {
            if (typeof window.crmIconAny === 'function') {
                return window.crmIconAny(legacyClass, options || {});
            }
            if (typeof window.crmIconLegacy === 'function') {
                return window.crmIconLegacy(legacyClass, options || {});
            }
            return '<i class="' + (legacyClass || '') + '"></i>';
        };
    }

    // Configuration object with all Blade variables needed for JavaScript
    window.ClientDetailConfig = {
        clientId: @json(($fetchedData->id ?? '')),
        encodeId: @json(($encodeId ?? '')),
        matterId: @json(($id1 ?? '')),
        activeTab: @json(($activeTab ?? 'personaldetails')),
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
            clientPortalTab: '{{ route("clients.detail.client-portal-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            workflowTab: '{{ route("clients.detail.workflow-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            accountTab: '{{ route("clients.detail.account-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            checklistsTab: '{{ route("clients.detail.checklists-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            emailsTab: '{{ route("clients.detail.emails-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            personalDocumentsTab: '{{ route("clients.detail.personaldocuments-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            visaDocumentsTab: '{{ route("clients.detail.visadocuments-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            notUsedDocumentsTab: '{{ route("clients.detail.notuseddocuments-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            notesTab: '{{ route("clients.detail.noteterm-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            personalDetailsTab: '{{ route("clients.detail.personaldetails-tab", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
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
            getCheckinOptionLists: '{{ URL::to("/get-checkin-option-lists") }}',
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
            completeWorkflowChecklist: '{{ route("clients.matter.complete-workflow-checklist") }}',
            saveWorkflowFileNote: '{{ route("clients.matter.workflow-file-note") }}',
            shellModals: '{{ route("clients.detail.shell-modals", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
            extraModals: '{{ route("clients.detail.extra-modals", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
        },
        lazyModals: {
            enabled: true,
            packs: {
                shell: {
                    url: '{{ route("clients.detail.shell-modals", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
                    ids: @json(\App\Support\ClientDetailModals::shellIds()),
                    triggers: @json(\App\Support\ClientDetailModals::packTriggers()['shell']),
                },
                extra: {
                    url: '{{ route("clients.detail.extra-modals", array_filter(["client_id" => $encodeId, "client_unique_matter_ref_no" => $id1 ?? null], static fn ($v) => $v !== null && $v !== "")) }}',
                    ids: @json(\App\Support\ClientDetailModals::extraIds()),
                    triggers: @json(\App\Support\ClientDetailModals::packTriggers()['extra']),
                },
            },
        }
    };
    
    // Global function to load activities feed (paginated via /get-activities)
    window.ActivityFeedState = window.ActivityFeedState || { page: 1, hasMore: false, loading: false, pendingReset: false, fetched: false };

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
                            subjectIcon = crmI('fas fa-sms');
                            iconClass = 'feed-icon-sms';
                        } else if (activityType === 'note') {
                            var noteIcon = 'fa-sticky-note';
                            if (subjectLower.indexOf('call') !== -1) { noteIcon = 'fa-phone'; noteSubtypeClass = ' activity-type-note-call'; }
                            else if (subjectLower.indexOf('email') !== -1) { noteIcon = 'fa-envelope'; noteSubtypeClass = ' activity-type-note-email'; }
                            else if (subjectLower.indexOf('in-person') !== -1) { noteIcon = 'fa-user-friends'; noteSubtypeClass = ' activity-type-note-in-person'; }
                            else if (subjectLower.indexOf('attention') !== -1) { noteIcon = 'fa-exclamation-triangle'; noteSubtypeClass = ' activity-type-note-attention'; }
                            else if (subjectLower.indexOf('others') !== -1) { noteIcon = 'fa-ellipsis-h'; noteSubtypeClass = ' activity-type-note-others'; }
                            subjectIcon = crmI('fas ' + noteIcon);
                            iconClass = 'feed-icon-note';
                        } else if (activityType === 'activity') {
                            if (isAppointmentActivity) {
                                subjectIcon = crmI('fas fa-calendar-check');
                                iconClass = 'feed-icon-appointment';
                            } else {
                                subjectIcon = crmI('fas fa-bolt');
                                iconClass = 'feed-icon-activity';
                            }
                        } else if (activityType === 'stage') {
                            subjectIcon = crmI('fas fa-route');
                            iconClass = 'feed-icon-stage';
                        } else if (activityType === 'financial') {
                            subjectIcon = crmI('fas fa-dollar-sign');
                            iconClass = 'feed-icon-financial';
                        } else if (activityType === 'email') {
                            subjectIcon = crmI('fas fa-envelope');
                            iconClass = 'feed-icon-email';
                        } else if (activityType === 'signature') {
                            subjectIcon = crmI('fas fa-file-signature');
                            iconClass = 'feed-icon-signature';
                        } else if (activityType === 'document') {
                            subjectIcon = crmI('fas fa-file-alt');
                            iconClass = '';
                        } else if (/uploaded email:/i.test(subjectLower)) {
                            subjectIcon = crmI('fas fa-envelope');
                            iconClass = 'feed-icon-email';
                        } else if (subjectLower.includes('invoice') || subjectLower.includes('receipt') || subjectLower.includes('ledger') || subjectLower.includes('payment') || subjectLower.includes('account')) {
                            subjectIcon = crmI('fas fa-dollar-sign');
                            iconClass = 'feed-icon-financial';
                        } else if (subjectLower.includes('document') && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
                            subjectIcon = crmI('fas fa-file-alt');
                            iconClass = '';
                        } else if (subjectLower.includes('document')) {
                            subjectIcon = crmI('fas fa-file-alt');
                            iconClass = '';
                        } else {
                            subjectIcon = crmI('fas fa-sticky-note');
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

                    window.ActivityFeedState.fetched = true;
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

    // First fetch is from sidebar-tabs when the feed is shown (Personal / Company / Activity).
</script>

{{-- Newly added external JS placeholders for progressive migration --}}
<script src="{{ URL::asset('js/crm/clients/shared.js') }}" defer></script>
<script src="{{ URL::asset('js/crm/clients/detail.js') }}" defer></script>
<script src="{{ URL::asset('js/crm/clients/tabs/client_portal.js') }}" defer></script>

{{-- Client detail utilities (must load before detail-main.js) --}}
<script src="{{ URL::asset('js/crm/clients/utils/flatpickr-helpers.js') }}?v={{ file_exists(public_path('js/crm/clients/utils/flatpickr-helpers.js')) ? filemtime(public_path('js/crm/clients/utils/flatpickr-helpers.js')) : time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/utils/editor-helpers.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/utils/dom-helpers.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/lazy-modals.js') }}?v={{ file_exists(public_path('js/crm/clients/lazy-modals.js')) ? filemtime(public_path('js/crm/clients/lazy-modals.js')) : time() }}"></script>
{{-- Phase 3 modules --}}
<script src="{{ URL::asset('js/crm/clients/modules/references.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/send-to-client.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/notes.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/checklist.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/checklist.js')) ? filemtime(public_path('js/crm/clients/modules/checklist.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/documents.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/documents.js')) ? filemtime(public_path('js/crm/clients/modules/documents.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/signature-placement.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/signature-placement.js')) ? filemtime(public_path('js/crm/clients/modules/signature-placement.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/accounts.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/accounts.js')) ? filemtime(public_path('js/crm/clients/modules/accounts.js')) : time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/invoices.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/appointments.js') }}?v={{ file_exists(public_path('js/crm/clients/modules/appointments.js')) ? filemtime(public_path('js/crm/clients/modules/appointments.js')) : 1 }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/visa-expiry.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/subtabs.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/modules/ledger-dragdrop.js') }}"></script>
<script src="{{ URL::asset('js/crm/clients/workflow-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/account-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/checklists-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/emails-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/personaldocuments-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/visadocuments-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/notuseddocuments-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/notes-tab.js') }}?v={{ time() }}"></script>
<script src="{{ URL::asset('js/crm/clients/personaldetails-tab.js') }}?v={{ time() }}"></script>
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
            
            // Parse response if it's a string (fallback; guard empty to prevent "Unexpected end of input")
            var data;
            try {
                data = (typeof response === 'string' && response.trim()) ? (typeof $.parseJSON === 'function' ? $.parseJSON(response) : JSON.parse(response)) : (response || {});
            } catch (e) {
                data = {};
            }
            
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
    
    // Reset form â€” trigger('input') re-runs the counter so badge/max/remaining all reset
    $('#sms_message').val('').trigger('input');
    $('#sms_template').val('');

    $('#sendSmsModal').modal('show');
});

// Delegated: #sendSmsModal HTML is injected by the lazy shell pack after page-load
// binds would miss #sms_template / #sms_message / #sendSmsForm. Do not also
// re-bind .send-sms-btn — that icon is always in the header (double-fire risk).
$(document)
    .off('change.clientSendSms', '#sms_template')
    .off('input.clientSendSms', '#sms_message')
    .off('submit.clientSendSms', '#sendSmsForm');

// Template selection (body loaded via API — avoids broken data-* with quotes/newlines)
$(document).on('change.clientSendSms', '#sms_template', function() {
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
$(document).on('input.clientSendSms', '#sms_message', function() {
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
$(document).on('submit.clientSendSms', '#sendSmsForm', function(e) {
    e.preventDefault();
    
    const submitBtn = $('#sendSmsBtn');
    const originalText = submitBtn.html();
    
    submitBtn.prop('disabled', true).html(crmI('fas fa-spinner fa-spin') + ' Sending...');
    
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

@if($showGoogleReviewReminderModal ?? false)
<script>
$(function () {
    var $modal = $('#googleReviewReminderModal');
    if (!$modal.length) { return; }
    var clientId = parseInt($('.crm-container').data('client-id'), 10);
    if (!clientId || clientId < 1) { return; }
    var token = $('meta[name="csrf-token"]').attr('content');
    var postUrl = @json(route('clients.google-review-reminder'));
    var postSmsUrl = @json(route('clients.google-review-reminder.sms'));
    var submitting = false;

    function grrAllControls() {
        return $modal.find('.js-google-review-reminder, .js-google-review-send-sms');
    }
    var reminderDelayMs = @json((int) config('crm.google_review_reminder_modal_delay_ms', 60000));
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        reminderDelayMs = Math.min(reminderDelayMs, 400);
    }
    var grrShowTimer = setTimeout(function () {
        $modal.modal('show');
    }, reminderDelayMs);
    $(window).on('pagehide.grr', function () {
        clearTimeout(grrShowTimer);
    });
    $modal.on('shown.bs.modal', function () {
        var $sms = $modal.find('.grr-modal-footer .js-google-review-send-sms');
        var $first = $sms.length ? $sms : $modal.find('.grr-modal-footer .js-google-review-reminder').first();
        if ($first.length) {
            $first.trigger('focus');
        }
    });
    $modal.off('click.grr', '.js-google-review-reminder').on('click.grr', '.js-google-review-reminder', function () {
        if (submitting) { return; }
        var action = $(this).data('action');
        var $btns = grrAllControls();
        submitting = true;
        $btns.prop('disabled', true);
        $.ajax({
            url: postUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            data: { client_id: clientId, action: action, _token: token },
            success: function (res) {
                if (res && res.ok) {
                    $modal.modal('hide');
                    if (typeof iziToast !== 'undefined') {
                        var toastMessages = {
                            snooze_one_day: 'Reminder snoozed until tomorrow',
                            snooze: 'Reminder snoozed for 1 week',
                            not_interested: 'Noted â€” won\'t be reminded again',
                            review_received: 'Great! Review marked as received'
                        };
                        iziToast.success({ message: toastMessages[action] || 'Saved', position: 'topRight' });
                    }
                } else {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ message: (res && res.message) ? res.message : 'Could not save', position: 'topRight' });
                    }
                }
            },
            error: function (xhr) {
                var msg = 'Could not save';
                var j = xhr.responseJSON;
                if (j) {
                    if (j.message) { msg = j.message; }
                    if (j.errors && typeof j.errors === 'object') {
                        var keys = Object.keys(j.errors);
                        if (keys.length && j.errors[keys[0]] && j.errors[keys[0]][0]) {
                            msg = j.errors[keys[0]][0];
                        }
                    }
                }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ message: msg, position: 'topRight' });
                }
            },
            complete: function () {
                submitting = false;
                $btns.prop('disabled', false);
            }
        });
    });

    $modal.off('click.grr-sms', '.js-google-review-send-sms').on('click.grr-sms', '.js-google-review-send-sms', function () {
        if (submitting) { return; }
        var $btns = grrAllControls();
        submitting = true;
        $btns.prop('disabled', true);
        $.ajax({
            url: postSmsUrl,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            },
            data: { client_id: clientId, _token: token },
            success: function (res) {
                if (res && res.ok) {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.success({ message: res.message || 'SMS sent successfully', position: 'topRight' });
                    }
                    if (typeof loadActivities === 'function') {
                        loadActivities();
                    }
                } else {
                    if (typeof iziToast !== 'undefined') {
                        iziToast.error({ message: (res && res.message) ? res.message : 'SMS failed', position: 'topRight' });
                    }
                }
            },
            error: function (xhr) {
                var msg = 'SMS failed';
                var j = xhr.responseJSON;
                if (j && j.message) { msg = j.message; }
                if (typeof iziToast !== 'undefined') {
                    iziToast.error({ message: msg, position: 'topRight' });
                }
            },
            complete: function () {
                submitting = false;
                grrAllControls().prop('disabled', false);
            }
        });
    });
});
</script>
@endif

@endpush
