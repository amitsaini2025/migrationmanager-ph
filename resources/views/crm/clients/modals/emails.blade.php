<!-- Upload Mail Modal -->
<div class="modal fade custom_modal" id="uploadmail" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="paymentscheModalLabel">Upload Mail:</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
			<form method="post" action="{{URL::to('/upload-mail')}}" name="uploadmail"  autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" id="maclient_id">
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label>From <span class="span_req">*</span></label>
								<input type="text" data-valid="required" name="from" class="form-control">
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label>To <span class="span_req">*</span></label>
								<input type="text" data-valid="required" name="to" class="form-control">
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label>Subject <span class="span_req">*</span></label>
								<input type="text" data-valid="required" name="subject" class="form-control">
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="message">Message <span class="span_req">*</span></label>
								<textarea id="uploadmail_message" data-valid="required" class="tinymce-editor selectedmessage" name="message"></textarea>

							</div>
						</div>

                        <div class="col-4 col-md-4 col-lg-4">
							<div class="form-group">
								<button onclick="saveUploadMail()" class="btn btn-info" type="button">Create</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Compose Email Modal -->
<div id="matteremailmodal"  data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="clientModalLabel">Compose Email</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" name="appkicationsendmail" id="appkicationsendmail" action="{{URL::to('/client-portal/sendmail')}}" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" value="{{$fetchedData->id}}">
				<input type="hidden" id="type" name="type" value="application">
				<input type="hidden" id="appointid" name="noteid" value="">
				<input type="hidden"  name="atype" value="application">
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
								<input type="text" readonly class="form-control" name="to" value="{{$fetchedData->first_name}} {{$fetchedData->last_name}}">
								<input type="hidden" class="form-control" name="to" value="{{$fetchedData->email}}">
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_cc">CC </label>
								<select data-valid="" class="js-data-example-ajaxccapp" name="email_cc[]"></select>

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
								<select data-valid="" class="form-control mm-select selectmattertemplate" name="template">
									<option value="">Select</option>
									@foreach(\App\Models\EmailTemplate::crm()->orderBy('id', 'desc')->get() as $list)
										<option value="{{$list->id}}">{{$list->name}}</option>
									@endforeach
								</select>

							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label for="subject">Subject <span class="span_req">*</span></label>
								<input type="text" name="subject" class="form-control selectedappsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject">
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
								<textarea id="matter_email_message" class="tinymce-editor selectedmessage" name="message" data-valid="required"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="saveApplicationEmail()" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Upload Inbox Mail And Fetch Content Modal -->
<div class="modal fade custom_modal" id="uploadAndFetchMailModel" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="paymentscheModalLabel">Upload Inbox Mail And Fetch Content:</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
			<form method="post" action="{{URL::to('/upload-fetch-mail')}}" name="uploadAndFetchMail" id="uploadAndFetchMail" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" id="maclient_id_fetch">
                <input type="hidden" name="upload_inbox_mail_client_matter_id" id="upload_inbox_mail_client_matter_id" value="">
                <input type="hidden" name="type" value="client">
                      <!-- Error Message Container -->
                    <div class="custom-error-msg"></div>
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                               <label>Upload Outlook Email (.msg)<span class="span_req">*</span></label>
                               <input type="file" name="email_files[]" id="email_files" class="form-control" accept=".msg" multiple >
                            </div>
                       </div>

						<div class="col-4 col-md-4 col-lg-4">
							<div class="form-group">
								<button onclick="customValidate('uploadAndFetchMail')" class="btn btn-primary" type="button">Save</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<!-- Upload Sent Mail And Fetch Content Modal -->
<div class="modal fade custom_modal" id="uploadSentAndFetchMailModel" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="paymentscheModalLabel">Upload Sent Mail And Fetch Content:</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
			<form method="post" action="{{URL::to('/upload-sent-fetch-mail')}}" name="uploadSentAndFetchMail" id="uploadSentAndFetchMail" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<input type="hidden" name="client_id" id="maclient_id_fetch_sent" value="">
                <input type="hidden" name="upload_sent_mail_client_matter_id" id="upload_sent_mail_client_matter_id" value="">
                <input type="hidden" name="type" value="client">
                  <!-- Error Message Container -->
                  <div class="custom-error-msg"></div>
					<div class="row">
						<div class="col-12 col-md-12 col-lg-12">
                            <div class="form-group">
                               <label>Upload Outlook Email (.msg)<span class="span_req">*</span></label>
                               <input type="file" name="email_files[]" id="email_files1" class="form-control" accept=".msg" multiple >
                            </div>
                       </div>

						<div class="col-4 col-md-4 col-lg-4">
							<div class="form-group">
								<button onclick="customValidate('uploadSentAndFetchMail')" class="btn btn-primary" type="button">Save</button>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

