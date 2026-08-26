{{-- Client-detail shell modals (compose, SMS, tags, reassign). Loaded on first open / prefetch. --}}
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
								<select data-valid="required" class="form-select js-data-example-ajax" name="email_to[]"></select>

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
								<select data-valid="" class="form-select js-data-example-ajaxccd" name="email_cc[]"></select>

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
                                $clientAssigneeName = ''; // assignee column removed
                                if(false){
                                } else {
                                    $clientAssigneeName = 'NA';
                                }
                                ?>
								<select data-valid="" class="form-control mm-select selecttemplate" name="template" data-clientid="{{@$fetchedData->id}}" data-clientfirstname="{{@$fetchedData->first_name}}" data-clientvisaExpiry="{{@$fetchedData->visaExpiry}}" data-clientreference_number="{{@$fetchedData->client_id}}" data-clientassignee_name="{{@$clientAssigneeName}}">
									<option value="">Select</option>
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
{{-- Table was for tracking Migration/Education services taken by clients --}}
{{-- Model clientServiceTaken.php deleted - no database backing --}}
{{-- Routes still exist but will fail: createservicetaken, removeservicetaken, getservicetaken --}}

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

