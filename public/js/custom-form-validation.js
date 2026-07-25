var requiredError = 'This field is required.';
var emailError = "Please enter the valid email address.";
var captcha = "Captcha invalid.";
var maxError = "Number should be less than or equal to ";
var min = "This field should be greater than or equal to ";
var max = "This field should be less than or equal to ";
var equal = "This field should be equal to ";

/** UTF-8 safe base64 for compose email bodies (avoids WAF/mod_security 403 on HTML/URLs). */
function mmUtf8ToBase64(str) {
	try {
		return btoa(unescape(encodeURIComponent(String(str))));
	} catch (e) {
		return '';
	}
}

/** Allowed document upload filename pattern (personal, visa, education tabs). */
var MM_DOCUMENT_FILENAME_REGEX = /^[a-zA-Z0-9_\-\.\s\$\(\),&+']+$/;

function mmIsAllowedDocumentFilename(filename) {
	return !!(filename && MM_DOCUMENT_FILENAME_REGEX.test(filename));
}

function mmDocumentFilenameValidationMessage() {
	return "File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), apostrophes ('), and plus signs (+). Please rename the file and try again.";
}

/** Sanitize document upload filename for multipart POST (WAF-safe). */
function mmSanitizeDocumentUploadFilename(filename) {
	if (!filename || typeof filename !== 'string') {
		return 'document_' + Date.now();
	}
	var lastDot = filename.lastIndexOf('.');
	var extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
	var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;
	var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
	sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
	if (!sanitizedName) {
		sanitizedName = 'document_' + Date.now();
	}
	var sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
	if (sanitizedFilename.length > 255) {
		var maxNameLength = 255 - extension.length - (extension ? 1 : 0);
		if (maxNameLength > 0) {
			sanitizedName = sanitizedName.slice(0, maxNameLength);
			sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
		} else {
			sanitizedFilename = 'document_' + Date.now() + (extension ? '.' + extension : '');
		}
	}
	return sanitizedFilename;
}

/** Replace document_upload in FormData with WAF-safe multipart filename. */
function mmSetDocumentUploadFile(formData, file) {
	if (!formData || !file) {
		return;
	}
	try {
		formData.delete('document_upload');
	} catch (e) { /* ignore */ }
	formData.set('document_upload', file, mmSanitizeDocumentUploadFilename(file.name));
}

/** Append bulk document upload file with WAF-safe multipart filename (personal/visa/nomination tabs). */
function mmAppendBulkDocumentUploadFile(formData, file) {
	if (!formData || !file) {
		return;
	}
	formData.append('files[]', file, mmSanitizeDocumentUploadFilename(file.name));
}

/** Sanitize .msg upload filename for multipart POST (WAF-safe; matches backend EmailUploadController). */
function mmSanitizeEmailUploadFilename(filename) {
	if (!filename || typeof filename !== 'string') {
		return 'email_' + Date.now() + '.msg';
	}
	var lastDot = filename.lastIndexOf('.');
	var extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
	var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;
	var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
	sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
	if (!sanitizedName) {
		sanitizedName = 'email_' + Date.now();
	}
	var sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
	if (sanitizedFilename.length > 255) {
		var maxNameLength = 255 - extension.length - (extension ? 1 : 0);
		if (maxNameLength > 0) {
			sanitizedName = sanitizedName.slice(0, maxNameLength);
			sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
		} else {
			sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
		}
	}
	return sanitizedFilename;
}

/** Rebuild FormData for email upload forms with WAF-safe filenames. */
function mmBuildEmailUploadFormData(form) {
	var formData = new FormData(form);
	var fileInput = form.querySelector('input[name="email_files[]"]');
	if (!fileInput || !fileInput.files || !fileInput.files.length) {
		return formData;
	}
	var rebuilt = new FormData();
	formData.forEach(function(value, key) {
		if (key !== 'email_files[]') {
			rebuilt.append(key, value);
		}
	});
	Array.from(fileInput.files).forEach(function(file) {
		rebuilt.append('email_files[]', file, mmSanitizeEmailUploadFilename(file.name));
	});
	return rebuilt;
}

/** User-facing message for email upload 403 (Laravel JSON vs WAF HTML). */
function mmEmailUpload403Message(xhr) {
	if (!xhr || xhr.status !== 403) {
		return null;
	}
	var responseText = xhr.responseText || '';
	var isHtml = /<html[\s>]/i.test(responseText) || /<!DOCTYPE/i.test(responseText);
	if (isHtml || (responseText.indexOf('Forbidden') !== -1 && !(xhr.responseJSON && xhr.responseJSON.message))) {
		return 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes and try again.';
	}
	if (xhr.responseJSON && xhr.responseJSON.message) {
		return xhr.responseJSON.message;
	}
	return 'Access denied. You may not have permission to upload emails for this client.';
}

/** Apply WAF-safe encoding to compose-email FormData before POST /sendmail. */
function mmGenerateInvoiceSubmissionToken() {
	return 'inv_' + Date.now() + '_' + Math.random().toString(36).slice(2, 12);
}

function mmRefreshInvoiceSubmissionToken() {
	var $field = $('#invoice_receipt_form input[name="submission_token"]');
	if ($field.length) {
		$field.val(mmGenerateInvoiceSubmissionToken());
	}
}

function mmAcquireInvoiceSubmitLock() {
	var $form = $('#invoice_receipt_form');
	if (!$form.length) {
		return { acquired: true, $form: $form, $buttons: $() };
	}
	if ($form.data('invoice-submitting') === true) {
		return { acquired: false, $form: $form, $buttons: $() };
	}
	var $buttons = $form.find('button.btn-primary');
	$form.data('invoice-submitting', true);
	$buttons.prop('disabled', true);
	return { acquired: true, $form: $form, $buttons: $buttons };
}

function mmReleaseInvoiceSubmitLock() {
	var $form = $('#invoice_receipt_form');
	if (!$form.length) {
		return;
	}
	$form.data('invoice-submitting', false);
	$form.find('button.btn-primary').prop('disabled', false);
}

window.mmRefreshInvoiceSubmissionToken = mmRefreshInvoiceSubmissionToken;
window.mmReleaseInvoiceSubmitLock = mmReleaseInvoiceSubmitLock;

function mmPrepareComposeEmailFormData(myform, fd, csrfToken) {
	fd.set('_token', csrfToken);

	var clientIdEl = myform.querySelector('input[name="client_id"]');
	var clientIdVal = clientIdEl && clientIdEl.value ? String(clientIdEl.value).trim() : '';
	if (!clientIdVal && typeof window.ClientDetailConfig !== 'undefined' && window.ClientDetailConfig.clientId) {
		clientIdVal = String(window.ClientDetailConfig.clientId);
	}
	if (clientIdVal) {
		fd.set('client_id', clientIdVal);
	}

	var leadIdEl = myform.querySelector('input[name="lead_id"]');
	if (leadIdEl && leadIdEl.value) {
		fd.set('lead_id', String(leadIdEl.value).trim());
	}

	var subjectEl = myform.querySelector('input[name="subject"], [name="subject"]');
	if (subjectEl && subjectEl.value) {
		fd.set('subject', String(subjectEl.value).replace(/\&/g, '__AMP__'));
	}

	var messageEl = myform.querySelector('textarea[name="message"], [name="message"]');
	if (messageEl) {
		var messageVal = messageEl.value != null ? String(messageEl.value) : '';
		var encodedMessage = mmUtf8ToBase64(messageVal);
		if (encodedMessage) {
			fd.set('message', encodedMessage);
			fd.set('message_encoding', 'base64');
		}
	}

	var signingUrlEl = myform.querySelector('input[name="signing_url"], [name="signing_url"]');
	if (signingUrlEl && signingUrlEl.value) {
		var signingUrlVal = String(signingUrlEl.value).trim();
		var encodedSigningUrl = mmUtf8ToBase64(signingUrlVal);
		if (encodedSigningUrl) {
			fd.set('signing_url', encodedSigningUrl);
			fd.set('signing_url_encoding', 'base64');
		}
	}
}

function customValidate(formName, savetype = '')
	{ //alert(formName);
		if (formName === 'convert_lead_to_client') {
			console.log('[ConvertLeadToClient] customValidate called (Save clicked)');
		}
		if (formName === 'change_matter_assignee') {
			console.log('[ChangeMatterAssignee] customValidate called, formName=', formName);
		}
		$(".popuploader").show(); //all form submit

		// Legacy sales forecast flows are disabled
		if (formName === 'saleforcast' || formName === 'saleforcastservice') {
			$(".popuploader").hide();
			return false;
		}

		var i = 0;
		$(".custom-error").remove(); //remove all errors when submit the button

		var $inputsToValidate;
		if (formName === 'convert_lead_to_client') {
			$inputsToValidate = $("#convertLeadToClientModal form[name='convert_lead_to_client'] :input[data-valid]");
		} else if (formName === 'change_matter_assignee') {
			$inputsToValidate = $("#changeMatterAssigneeModal form[name='change_matter_assignee'] :input[data-valid]");
		} else {
			$inputsToValidate = $("form[name="+formName+"] :input[data-valid]");
		}
		if (formName === 'change_matter_assignee') {
			console.log('[ChangeMatterAssignee] inputs to validate count=', $inputsToValidate.length, 'selector= #changeMatterAssigneeModal form[name=change_matter_assignee] :input[data-valid]');
		}
		$inputsToValidate.each(function(){
			var dataValidation = $(this).attr('data-valid');
			var splitDataValidation = dataValidation.split(' ');

			var j = 0; //for serial wise errors shown
			if($.inArray("required", splitDataValidation) !== -1) //for required
				{
					var for_class = $(this).attr('class') || '';
					var $element = $(this);
					var isMmSelect = $element.hasClass('mm-select-initialized') || $element.data('mmSelect');
					var isMultiple = $element.prop('multiple');
					
					if(for_class.indexOf('multiselect_subject') != -1)
						{
							var value = $.trim($(this).val());
							if (value.length === 0)
								{
									i++;
									j++;
									$(this).parent().after(errorDisplay(requiredError));
								}
						}
					else if(isMmSelect && isMultiple)
						{
							// Tom Select / legacy enhanced multi-select
							var selectedValues = $element.val();
							if(!selectedValues || selectedValues.length === 0 || (selectedValues.length === 1 && selectedValues[0] === ''))
								{
									i++;
									j++;
									$(this).after(errorDisplay(requiredError));
								}
						}
					else
						{
							var rawVal = $(this).val();
							var trimVal = $.trim(typeof rawVal === 'string' ? rawVal : (Array.isArray(rawVal) ? (rawVal[0] || '') : (rawVal || '')));
							if (formName === 'change_matter_assignee' && (i + j) < 3) {
								console.log('[ChangeMatterAssignee] required check: name=', $(this).attr('name'), 'id=', $(this).attr('id'), 'rawVal=', rawVal, 'trimVal=', trimVal, 'empty=', !trimVal);
							}
							if( !trimVal )
								{
									i++;
									j++;
									$(this).after(errorDisplay(requiredError));
								}
						}
				}
			if(j <= 0)
				{
					if($.inArray("email", splitDataValidation) !== -1) //for email
						{
							if(!validateEmail($.trim($(this).val())))
								{
									i++;
									$(this).after(errorDisplay(emailError));
								}
						}


					var forMin = splitDataValidation.find(a =>a.includes("min"));
					if(typeof forMin != 'undefined')
						{
							var breakMin = forMin.split('-');
							var digit = breakMin[1];

							var value = $.trim($(this).val()).length;
							if(value < digit)
								{
									i++;
									$(this).after(errorDisplay(min+' '+digit+' character.'));
								}
						}

					var forMax = splitDataValidation.find(a =>a.includes("max"));
					if(typeof forMax != 'undefined')
						{
							var breakMax = forMax.split('-');
							var digit = breakMax[1];

							var value = $.trim($(this).val()).length;
							if(value > digit)
								{
									i++;
									$(this).after(errorDisplay(max+' '+digit+' character.'));
								}
						}

					var forEqual = splitDataValidation.find(a =>a.includes("equal"));
					if(typeof forEqual != 'undefined')
						{
							var breakEqual = forEqual.split('-');
							var digit = breakEqual[1];

							var value = ($.trim($(this).val()).replace(/[^a-z0-9\s]/gi, '').replace(/[_\s]/g, '-')).length;
							if(value != digit)
								{
									i++;
									$(this).after(errorDisplay(equal+' '+digit+' character.'));
								}
						}
				}
		});

		if(i > 0)
			{
				if (formName === 'convert_lead_to_client') {
					console.warn('[ConvertLeadToClient] Validation failed (i=' + i + '), form not submitted');
				}
				if (formName === 'change_matter_assignee') {
					console.warn('[ChangeMatterAssignee] Validation FAILED, error count i=' + i + ', form will NOT submit. Check required fields (Migration Agent, Person Responsible, Person Assisting).');
				}
				if(formName == 'add-query'){
					$('html, body').animate({scrollTop:$("#row_scroll"). offset(). top}, 'slow');
				}else if(formName != 'upload-answer')	{
					$('html, body').animate({scrollTop:0}, 'slow');
				}
				$(".popuploader").hide();
				return false;
			}
		else
			{
				if(formName == 'add-query')
					{
						$('#preloader').show();
						$('#preloader div').show();
						var myform = document.getElementById('enquiryco');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('#preloader').hide();
								$('#preloader div').hide();
								var obj = $.parseJSON(response);
								if(obj.success){
									window.location = redirecturl;
								}else{
									$('.customerror').html(obj.message);
									$('html, body').animate({scrollTop:$("#row_scroll"). offset(). top}, 'slow');
								}
							}
						});
					}else if(formName == 'queryform')
					{
						$('#preloader').show();
						$('#preloader div').show();
						var myform = document.getElementById('popenquiryco');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('#preloader').hide();
								$('#preloader div').hide();
								var obj = $.parseJSON(response);
								if(obj.success){
									window.location = redirecturl;
								}else{
									$('.customerror').html(obj.message);

								}
							}
						});
					}else if(formName == 'add-note')
					{
						var myform = document.getElementById('addnoteform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
				$('.popuploader').hide();
				let obj;
				try {
					obj = (typeof response === 'string') ? JSON.parse(response) : response;
				} catch (error) {
					console.error('Unable to parse adjust invoice response', response, error);
					alert('Unexpected server response. Please try again.');
					return;
				}
								if(obj.success){
									$('#myAddnotes .modal-title').html('');
									$('#myAddnotes #note_type').html('');
									$('#myAddnotes').modal('hide');
									myfollowuplist(obj.leadid);
								}else{
									$('#myAddnotes .customerror').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'edit-note')
					{
						var myform = document.getElementById('editnoteform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								if(obj.success){
									$('#myeditnotes .modal-title').html('');
									$('#myeditnotes #note_type').html('');
									$('#myeditnotes').modal('hide');
									myfollowuplist(obj.leadid);
								}else{
									$('#myeditnotes .customerror').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'appnotetermform')
					{
				var noteid = $('#appnotetermform input[name="noteid"]').val();
						var myform = document.getElementById('appnotetermform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
									$('.popuploader').hide();
								var obj = $.parseJSON(response);
								if(obj.status){
									$('#create_matternote').modal('hide');
									$.ajax({
										url: site_url+'/client-portal/logs',
										type:'GET',
										data:{id: noteid},
										success: function(responses){

											$('#accordion').html(responses);
										}
									});
								}else{
									$('#create_matternote .customerror').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'clientnotetermform')
					{

						var client_id = $('input[name="client_id"]').val();
						var myform = document.getElementById('clientnotetermform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#create_note').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								$.ajax({
									url: site_url+'/get-notes',
									type:'GET',
									data:{clientid:client_id,type:'partner'},
									success: function(responses){

										$('.note_term_list').html(responses);
									}
								});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'clientcontact')
					{
                        //nt.getElementById('clientcontact');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#add_clientcontact').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								$.ajax({
									url: site_url+'/get-contacts',
									type:'GET',
									data:{clientid:client_id,type:'partner'},
									success: function(responses){

										$('.contact_term_list').html(responses);
									}
								});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'clientbranch')
					{

						var client_id = $('input[name="client_id"]').val();
						var myform = document.getElementById('clientbranch');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#add_clientbranch').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								$.ajax({
									url: site_url+'/get-branches',
									type:'GET',
									data:{clientid:client_id,type:'partner'},
									success: function(responses){

										$('.branch_term_list').html(responses);
									}
								});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}
					// Old taskform removed - Task table/model no longer exists
                    else if(formName == 'mig_upload_form'){
						var client_id = $('#mig_upload_form input[name="clientid"]').val();
                        var folder_name = $('#mig_upload_form input[name="folder_name"]').val();
						var myform = document.getElementById('mig_upload_form');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							//datatype:'json',
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#openmigrationdocsmodal').modal('hide');
								if(obj.status){ //alert('folder_name=='+folder_name);
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$('.migdocumnetlist_'+folder_name).html(obj.data);
									//$('.miggriddata').show();
									$('.miggriddata').html(obj.griddata);
									
									// Re-initialize drag and drop for newly added checklist items
									if (typeof initVisaDocDragDrop === 'function') {
										setTimeout(function() {
											initVisaDocDragDrop();
										}, 100);
									}
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
								}
                                //Fetch All Activities
								getallactivities(client_id);
							}
						});
					}
					else if(formName == 'nom_upload_checklist_form'){
						var client_id = $('#nom_upload_checklist_form input[name="clientid"]').val();
                        var folder_name = $('#nom_upload_checklist_form input[name="folder_name"]').val();
						var myform = document.getElementById('nom_upload_checklist_form');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#opennominationdocsmodal').modal('hide');
								if(obj.status){
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									var $pane = $('#nominationdocuments-tab .migdocumnetlist_'+folder_name);
									if ($pane.length) {
										$pane.html(obj.data);
									} else {
										$('.migdocumnetlist_'+folder_name).html(obj.data);
									}
									$('.nomgriddata').html(obj.griddata);
									if (typeof initNominationDocDragDrop === 'function') {
										setTimeout(function() {
											initNominationDocDragDrop();
										}, 100);
									}
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
								}
								getallactivities(client_id);
							}
						});
					}
					else if(formName == 'edu_upload_form'){
						var client_id = $('#edu_upload_form input[name="clientid"]').val();
						var myform = document.getElementById('edu_upload_form');
                        var doccategory = $('#edu_upload_form input[name="doccategory"]').val();
                        //console.log(doccategory);
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							//datatype:'json',
							success: function(response){
								$('.popuploader').hide();
								try {
									var obj = typeof response === 'string' ? $.parseJSON(response) : response;
									$('#openeducationdocsmodal').modal('hide');
									if(obj.status){
										$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
										$('.documnetlist_'+doccategory).html(obj.data);
										$('.griddata_'+doccategory).html(obj.griddata);
										
										// Re-initialize drag and drop for newly added checklist items
										// Use setTimeout to ensure DOM is fully updated before initialization
										setTimeout(function() {
											if (typeof initPersonalDocDragDrop === 'function') {
												console.log('🔄 Re-initializing drag and drop after adding checklist...');
												initPersonalDocDragDrop();
											}
										}, 100);
									}else{
										$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
									}
									//Fetch All Activities
									getallactivities(client_id);
								} catch(e) {
									console.error('Error parsing response:', e);
									$('.popuploader').hide();
									$('#openeducationdocsmodal').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-danger">An error occurred while processing your request. Please try again.</span>');
								}
							},
							error: function(xhr, status, error){
								$('.popuploader').hide();
								console.error('AJAX Error:', status, error);
								var errorMessage = 'An error occurred while adding the checklist. ';
								if(xhr.status === 0) {
									errorMessage += 'Please check your internet connection.';
								} else if(xhr.status === 500) {
									errorMessage += 'Server error. Please try again later.';
								} else if(xhr.status === 422) {
									errorMessage += 'Validation error. Please check your input.';
								} else {
									errorMessage += 'Please try again.';
								}
								$('.custom-error-msg').html('<span class="alert alert-danger">'+errorMessage+'</span>');
							}
						});
					}

					else if(formName == 'uploadAndFetchMail'){
						var client_id = $('#uploadAndFetchMail input[name="client_id"]').val();
						var myform = document.getElementById('uploadAndFetchMail');
                        var fd = mmBuildEmailUploadFormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							//datatype:'json',

                            success: function(response) {
                                $('.popuploader').hide();
                                $('.custom-error-msg').html('');
                                if (response.status) {
                                    $('#uploadAndFetchMailModel').modal('hide');
									localStorage.setItem('activeTab', 'emails');
                                    location.reload();
                                    $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                                } else {
                                    $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                                }
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    let errors = xhr.responseJSON.errors;
                                    displayValidationErrors(errors);
                                } else {
                                    var msg403 = mmEmailUpload403Message(xhr);
                                    var errMsg = msg403 || 'An unexpected error occurred. Please try again.';
                                    $('.custom-error-msg').html('<span class="alert alert-danger">' + errMsg + '</span>');
                                }
                            }
						});
					}


                    else if(formName == 'uploadSentAndFetchMail'){
						var client_id = $('#uploadSentAndFetchMail input[name="client_id"]').val();
						var myform = document.getElementById('uploadSentAndFetchMail');
                        var fd = mmBuildEmailUploadFormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							//datatype:'json',
                            success: function(response) {
                                $('.popuploader').hide();
                                $('.custom-error-msg').html('');
                                if (response.status) {
                                    $('#uploadSentAndFetchMailModel').modal('hide');
									localStorage.setItem('activeTab', 'emails');
                                    location.reload();

                                    $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                                } else {
                                    $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                                }
                            },
                            error: function(xhr) {
                                if (xhr.status === 422) {
                                    let errors = xhr.responseJSON.errors;
                                    displayValidationErrors2(errors);
                                } else {
                                    var msg403 = mmEmailUpload403Message(xhr);
                                    var errMsg = msg403 || 'An unexpected error occurred. Please try again.';
                                    $('.custom-error-msg').html('<span class="alert alert-danger">' + errMsg + '</span>');
                                }
                            }
						});
					}

                    else if(formName == 'change_matter_assignee'){
                        console.log('[ChangeMatterAssignee] Validation passed, entering submit block');
                        var myform = document.getElementById('change_matter_assignee');
                        if (!myform) {
                            console.error('[ChangeMatterAssignee] Form element #change_matter_assignee not found');
                            $('.popuploader').hide();
                            return;
                        }
                        // Ensure Tom Select / native select values are in sync for FormData
                        var selectIds = ['change_sel_migration_agent_id', 'change_sel_person_responsible_id', 'change_sel_person_assisting_id', 'change_office_id'];
                        for (var s = 0; s < selectIds.length; s++) {
                            var selEl = document.getElementById(selectIds[s]);
                            if (selEl) {
                                var chosen = $('#' + selectIds[s]).val();
                                selEl.value = (chosen === null || chosen === undefined) ? '' : (Array.isArray(chosen) ? (chosen[0] || '') : chosen);
                                console.log('[ChangeMatterAssignee] select sync:', selectIds[s], 'value=', selEl.value);
                            }
                        }
                        var fd = new FormData(myform);
                        var postUrl = $("form[name=\"change_matter_assignee\"]").attr('action');
                        console.log('[ChangeMatterAssignee] Submitting to URL=', postUrl, 'FormData keys: _token, client_id, user_id, selectedMatterLM, migration_agent, person_responsible, person_assisting, office_id');
                        console.log('[ChangeMatterAssignee] selectedMatterLM=', document.getElementById('selectedMatterLM') ? document.getElementById('selectedMatterLM').value : 'N/A');
                        $.ajax({
                            type:'post',
                            url: postUrl,
                            processData: false,
                            contentType: false,
                            data: fd,
                            dataType: 'json',
                            success: function(response){
                                console.log('[ChangeMatterAssignee] AJAX success, response=', response);
                                var obj = (typeof response === 'string' ? (function(){ try { return JSON.parse(response); } catch(e){ return {}; } })() : response) || {};
                                $('.popuploader').hide();
                                $('#changeMatterAssigneeModal').modal('hide');
                                if(obj.status){
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+(obj.message || 'Matter assignee updated successfully.')+'</span>');
                                }else{
                                    $('.custom-error-msg').html('<span class="alert alert-danger">'+(obj.message || 'Something went wrong. Please try again.')+'</span>');
                                }
                                location.reload();
                            },
                            error: function(xhr, textStatus, errorThrown){
                                console.error('[ChangeMatterAssignee] AJAX error: status=', xhr.status, 'statusText=', xhr.statusText, 'textStatus=', textStatus, 'errorThrown=', errorThrown);
                                console.error('[ChangeMatterAssignee] responseText=', xhr.responseText ? xhr.responseText.substring(0, 500) : 'none');
                                if (xhr.responseJSON) console.error('[ChangeMatterAssignee] responseJSON=', xhr.responseJSON);
                                $('.popuploader').hide();
                                var errMsg = 'Unable to update matter assignee. Please try again.';
                                if (xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
                                else if (xhr.responseJSON && xhr.responseJSON.errors) errMsg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                                else if (xhr.status === 419) errMsg = 'Session expired. Please refresh the page and try again.';
                                else if (xhr.status >= 500) errMsg = 'Server error. Please try again later.';
                                $('.custom-error-msg').html('<span class="alert alert-danger">'+errMsg+'</span>');
                            }
                        });
                    }

					else if(formName == 'costAssignmentformlead'){
						var client_id = $('#costAssignmentformlead input[name="client_id"]').val();
						var myform = document.getElementById('costAssignmentformlead');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							dataType: 'json',
							success: function(response){
								var obj = (typeof response === 'string' ? JSON.parse(response) : response) || {};
								var msg = (obj.message != null && obj.message !== '') ? obj.message : (obj.status ? 'Cost assignment saved successfully.' : 'An error occurred. Please try again.');
								$('#costAssignmentCreateFormModelLead').modal('hide');
								$('.popuploader').hide();
								localStorage.setItem('activeTab', 'checklists');
								if(obj.status){
									$('.custom-error-msg').html('<span class="alert alert-success">'+msg+'</span>');
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+msg+'</span>');
								}
								location.reload();
							},
							error: function(xhr){
								$('#costAssignmentCreateFormModelLead').modal('hide');
								$('.popuploader').hide();
								var errMsg = 'An error occurred while saving. Please try again.';
								if (xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
								else if (xhr.responseJSON && xhr.responseJSON.errors) errMsg = Object.values(xhr.responseJSON.errors).flat().join(' ');
								$('.custom-error-msg').html('<span class="alert alert-danger">'+errMsg+'</span>');
							}
						});
					}

                    else if(formName == 'add_pers_doc_cat_form'){
                        var myform = document.getElementById('add_pers_doc_cat_form');
                        var fd = new FormData(myform);
                        var $createBtn = $('#addpersonaldoccatmodel').find('button.btn-primary').first();
                        $createBtn.prop('disabled', true);
                        // popuploader already shown by customValidate() for all form submits
                        $.ajax({
                            type:'post',
                            url:$("form[name="+formName+"]").attr('action'),
                            processData: false,
                            contentType: false,
                            data: fd,
                            success: function(response){
                                var obj = response; // Remove $.parseJSON(response)
                                $('#addpersonaldoccatmodel').modal('hide');
                                if(obj.status){
									localStorage.setItem('activeTab', 'personaldocuments');
									$('#personal_doc_category').val('');
									var appended = false;
									try {
										if (typeof window.appendPersonalDocCategoryUi === 'function') {
											appended = !!window.appendPersonalDocCategoryUi(obj);
										}
									} catch (appendErr) {
										console.warn('[AddPersDocCat] UI append failed, falling back to reload', appendErr);
										appended = false;
									}
									if (!appended) {
										location.reload();
										return;
									}
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									if (typeof toastr !== 'undefined' && typeof toastr.success === 'function') {
										toastr.success(obj.message);
									} else if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
										iziToast.success({ message: obj.message, position: 'topRight', timeout: 3000 });
									}
                                }else{
                                    $('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
                            },
                            error: function(){
                                $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred while adding the category. Please try again.</span>');
                            },
                            complete: function(){
                                $('.popuploader').hide();
                                $createBtn.prop('disabled', false);
                            }
                        });
                    }

                    else if(formName == 'add_visa_doc_cat_form'){
                        var myform = document.getElementById('add_visa_doc_cat_form');
                        var fd = new FormData(myform);
                        var $createBtn = $('#addvisadoccatmodel').find('button.btn-primary').first();
                        $createBtn.prop('disabled', true);
                        // popuploader already shown by customValidate() for all form submits
                        $.ajax({
                            type:'post',
                            url:$("form[name="+formName+"]").attr('action'),
                            processData: false,
                            contentType: false,
                            data: fd,
                            success: function(response){
                                var obj = response; // Remove $.parseJSON(response)
                                $('#addvisadoccatmodel').modal('hide');
                                if(obj.status){
									localStorage.setItem('activeTab', 'visadocuments');
									$('#visa_doc_category').val('');
									var appended = false;
									try {
										if (typeof window.appendVisaDocCategoryUi === 'function') {
											appended = !!window.appendVisaDocCategoryUi(obj);
										}
									} catch (appendErr) {
										console.warn('[AddVisaDocCat] UI append failed, falling back to reload', appendErr);
										appended = false;
									}
									if (!appended) {
										location.reload();
										return;
									}
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									if (typeof toastr !== 'undefined' && typeof toastr.success === 'function') {
										toastr.success(obj.message);
									} else if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
										iziToast.success({ message: obj.message, position: 'topRight', timeout: 3000 });
									}
                                }else{
                                    $('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
                            },
                            error: function(){
                                $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred while adding the category. Please try again.</span>');
                            },
                            complete: function(){
                                $('.popuploader').hide();
                                $createBtn.prop('disabled', false);
                            }
                        });
                    }

                    else if(formName == 'add_nom_doc_cat_form'){
                        var myform = document.getElementById('add_nom_doc_cat_form');
                        var fd = new FormData(myform);
                        var $createBtn = $('#addnominationdoccatmodel').find('button.btn-primary').first();
                        $createBtn.prop('disabled', true);
                        // popuploader already shown by customValidate() for all form submits
                        $.ajax({
                            type:'post',
                            url:$("form[name="+formName+"]").attr('action'),
                            processData: false,
                            contentType: false,
                            data: fd,
                            success: function(response){
                                var obj = response;
                                $('#addnominationdoccatmodel').modal('hide');
                                if(obj.status){
									localStorage.setItem('activeTab', 'nominationdocuments');
									$('#nomination_doc_category').val('');
									var appended = false;
									try {
										if (typeof window.appendNominationDocCategoryUi === 'function') {
											appended = !!window.appendNominationDocCategoryUi(obj);
										}
									} catch (appendErr) {
										console.warn('[AddNomDocCat] UI append failed, falling back to reload', appendErr);
										appended = false;
									}
									if (!appended) {
										location.reload();
										return;
									}
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									if (typeof toastr !== 'undefined' && typeof toastr.success === 'function') {
										toastr.success(obj.message);
									} else if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
										iziToast.success({ message: obj.message, position: 'topRight', timeout: 3000 });
									}
                                }else{
                                    $('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
                            },
                            error: function(){
                                $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred while adding the category. Please try again.</span>');
                            },
                            complete: function(){
                                $('.popuploader').hide();
                                $createBtn.prop('disabled', false);
                            }
                        });
                    }

					else if(formName == 'client_receipt_form'){
						var client_id = $('#client_receipt_form input[name="client_id"]').val();
						var myform = document.getElementById('client_receipt_form');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
                            success: function(response){
                                var obj = response;
                                $('.popuploader').hide();
                                if(obj.status){
                                    $('#createreceiptmodal').modal('hide');
                                    
                                    localStorage.setItem('activeTab', 'accounts');
                                    
                                    // Store client_id in localStorage so we can call getallactivities after reload
                                    if (client_id) {
                                        localStorage.setItem('pendingGetActivities', client_id);
                                    }
                                    
                                    location.reload();
									if(obj.requestData){
										var reqData = obj.requestData;
										var awsUrl = obj.awsUrl; //console.log('awsUrl='+awsUrl);
										var trRows = "";
										$.each(reqData, function(index, subArray) {
											// Determine icon based on type
											let typeIcon = '';
											if(subArray.client_fund_ledger_type == 'Deposit'){
												typeIcon = 'fa-arrow-down';
											} else if(subArray.client_fund_ledger_type == 'Fee Transfer'){
												typeIcon = 'fa-arrow-right-from-bracket';
											} else if(subArray.client_fund_ledger_type == 'Disbursement'){
												typeIcon = 'fa-arrow-up';
											}

											// Create AWS link if available
											let awsLink = (awsUrl != "") ? '<a target="_blank" class="link-primary" href="'+awsUrl+'" title="View Receipt '+subArray.trans_no+'">' + crmI('fas fa-file-pdf') + '</a>' : '';

											// Format currency
											let depositAmount = subArray.deposit_amount ? "$" + parseFloat(subArray.deposit_amount).toFixed(2) : '';
											let withdrawAmount = subArray.withdraw_amount ? "$" + parseFloat(subArray.withdraw_amount).toFixed(2) : '';
											let balanceAmount = subArray.balance_amount ? "$" + parseFloat(subArray.balance_amount).toFixed(2) : '';

											const pm = (subArray.payment_method && String(subArray.payment_method).trim()) ? subArray.payment_method : '—';
											let methodCellDyn = pm;
											if (subArray.eftpos_surcharge_amount && parseFloat(subArray.eftpos_surcharge_amount) > 0) {
												methodCellDyn += '<br/><span style="font-size:11px;color:#6c757d;">+$' + parseFloat(subArray.eftpos_surcharge_amount).toFixed(2) + ' surcharge</span>';
											}
											trRows += `<tr>
												<td>${subArray.trans_date} ${awsLink}</td>
												<td class="type-cell">
													<i class="fas ${typeIcon} type-icon" title="${subArray.client_fund_ledger_type}"></i>
													<span>${subArray.client_fund_ledger_type}  ${subArray.invoice_no ? `(${subArray.invoice_no})` : ''}</span>
												</td>
												<td style="font-size:0.9em;color:#495057;">${methodCellDyn}</td>
												<td class="description">${subArray.description}</td>
												<td><a href="#" title="View Receipt ${subArray.trans_no}">${subArray.trans_no}</a></td>
												<td class="currency text-success">${depositAmount}</td>
												<td class="currency">${withdrawAmount}</td>
												<td class="currency balance">${balanceAmount}</td>
											</tr>`;
										});
									}
				                    //console.log('trRows='+trRows);
									$('.productitemList').append(trRows);
									if(obj.db_total_balance_amount){
										let db_total_balance_amount = obj.db_total_balance_amount ? "$" + parseFloat(obj.db_total_balance_amount).toFixed(2) : '';
										$('.funds-held').html(db_total_balance_amount);
									}

                                    // Now find and update the row
                                    if( obj.invoice_no != "" ) {
                                        let invoiceNo = obj.invoice_no;
                                        let invoiceBalance = obj.invoice_balance;
                                        let invoiceStatus = obj.invoice_status;

                                        // Define the status class and description
                                        let statusClassMap = {
                                            '0': 'status-unpaid',
                                            '1': 'status-paid',
                                            '2': 'status-partial',
                                            '3': 'status-void'
                                        };

                                        let statusVal = {
                                            '0': 'Unpaid',
                                            '1': 'Paid',
                                            '2': 'Partial',
                                            '3': 'Void'
                                        };

                                        let statusClass = statusClassMap[invoiceStatus];
                                        let statusText = statusVal[invoiceStatus];

                                        $(".productitemList_invoice tr").each(function () {
                                            let $row = $(this);
                                            let rowInvoiceNo = $.trim($row.find("td:first").clone().children().remove().end().text());
                                            console.log('rowInvoiceNo='+rowInvoiceNo);
                                            console.log('invoiceNo='+invoiceNo);
                                            if (rowInvoiceNo === invoiceNo) {
                                                // Update Amount
                                                $row.find("td.currency").html(`$ ${invoiceBalance}`);

                                                // Update Status
                                                $row.find("td:last").html(
                                                    `<span class="status-badge ${statusClass}">${statusText}</span>`
                                                );
                                            }
                                        });
                                        $('.outstanding-balance').text('$ ' + obj.outstanding_balance);
                                    }
                                    //Fetch All Activities - handled after page reload via localStorage mechanism
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								}else{
									alert(obj.message);
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
								}
							}
						});
					}

					else if(formName == 'invoice_receipt_form'){
						var invoiceSubmitLock = mmAcquireInvoiceSubmitLock();
						if (!invoiceSubmitLock.acquired) {
							$('.popuploader').hide();
							return false;
						}
						var client_id = $('#invoice_receipt_form input[name="client_id"]').val();
						var myform = document.getElementById('invoice_receipt_form');
						var fd = new FormData(myform);
						fd.append('save_type', savetype);
						if (!fd.get('submission_token')) {
							mmRefreshInvoiceSubmissionToken();
							fd.set('submission_token', $('#invoice_receipt_form input[name="submission_token"]').val() || mmGenerateInvoiceSubmissionToken());
						}
							$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								let obj = response;
								if (typeof response === 'string') {
									try {
										obj = $.parseJSON(response);
									} catch (error) {
										console.error('Unable to parse invoice response', response, error);
										alert('Unexpected server response. Please try again.');
										mmReleaseInvoiceSubmitLock();
										return;
									}
								}
								if (!obj || !(obj.status === true || obj.status === 1)) {
									var failMsg = (obj && obj.message) ? obj.message : 'Could not save invoice. Please try again.';
									alert(failMsg);
									mmReleaseInvoiceSubmitLock();
									return;
								}
								var invNo = obj.invoice_no || '';
								var edited = obj.function_type === 'edit';
								var okSuffix = edited ? ' updated.' : (savetype === 'draft' ? ' saved as draft.' : ' created.');
								var okMsg = invNo
									? ('Invoice No - ' + invNo + okSuffix)
									: (edited ? 'Invoice updated.' : (savetype === 'draft' ? 'Draft saved.' : 'Invoice created.'));
								alert(okMsg);
								$('#createreceiptmodal').modal('hide');
                                localStorage.setItem('activeTab', 'accounts');
                                
                                var matterIdUsed = $('#client_matter_id_invoice').val();
                                
                                var currentPath = window.location.pathname;
                                var pathMatch = currentPath.match(/\/clients\/detail\/([^\/]+)/);
                                var encodedClientId = pathMatch ? pathMatch[1] : null;
                                
                                if (!encodedClientId) {
                                    console.error('Could not extract client ID from URL, using location.reload()');
                                    location.reload();
                                    return;
                                }
                                
                                var matterRefNo = '';
                                if (matterIdUsed && matterIdUsed !== '' && matterIdUsed !== 'null') {
                                    var $selectedOption = $('#sel_matter_id_client_detail option[value="' + matterIdUsed + '"]');
                                    if ($selectedOption.length) {
                                        matterRefNo = $selectedOption.data('clientuniquematterno') || '';
                                    }
                                    if (!matterRefNo) {
                                        var urlSegments = window.location.pathname.split('/');
                                        if (urlSegments.length > 4 && urlSegments[4] && urlSegments[4] !== 'account' && urlSegments[4] !== 'personaldetails' && urlSegments[4] !== 'notes' && urlSegments[4] !== 'emails') {
                                            matterRefNo = urlSegments[4];
                                        }
                                    }
                                } else {
                                    var urlSegments2 = window.location.pathname.split('/');
                                    if (urlSegments2.length > 4 && urlSegments2[4] && urlSegments2[4] !== 'account' && urlSegments2[4] !== 'personaldetails' && urlSegments2[4] !== 'notes' && urlSegments2[4] !== 'emails') {
                                        matterRefNo = urlSegments2[4];
                                    }
                                }
                                
                                var baseUrl = window.location.origin + '/clients/detail/' + encodedClientId;
                                if (matterRefNo) {
                                    baseUrl += '/' + encodeURIComponent(matterRefNo);
                                }
                                baseUrl += '/account';
                                window.location.href = baseUrl;
							},
							error: function(xhr) {
								$('.popuploader').hide();
								mmReleaseInvoiceSubmitLock();
								var msg = 'Could not save invoice. Please try again.';
								if (xhr.responseJSON && xhr.responseJSON.message) {
									msg = xhr.responseJSON.message;
								}
								alert(msg);
							}
						});
					}

					// Office Receipt Form Validation
                    else if (formName == 'office_receipt_form') {
                        // Step 1: Perform the invoice amount validation before submitting the form
                        let officeFormEl = document.getElementById('office_receipt_form');
                        let invoiceNos = officeFormEl ? officeFormEl.querySelectorAll('[name="invoice_no[]"]') : [];
                        let receivedAmounts = officeFormEl ? officeFormEl.querySelectorAll('[name="deposit_amount[]"]') : [];
                        let surchargeInputs = officeFormEl ? officeFormEl.querySelectorAll('[name="eftpos_surcharge_amount[]"]') : [];
                        let paymentMethodSelects = officeFormEl ? officeFormEl.querySelectorAll('[name="payment_method[]"]') : [];
                        let shouldProceed = true;

                        // Group received amounts by invoice number
                        let invoiceTotals = {};

                        for (let i = 0; i < invoiceNos.length; i++) {
                            let invoiceNo = invoiceNos[i].value; // e.g., INV-005
                            let principal = parseFloat(receivedAmounts[i] ? receivedAmounts[i].value : 0) || 0;
                            let pm = paymentMethodSelects[i] ? paymentMethodSelects[i].value : '';
                            let sur = (pm === 'EFTPOS' && surchargeInputs[i]) ? (parseFloat(surchargeInputs[i].value) || 0) : 0;
                            let receivedAmount = principal + sur;

                            if (invoiceNo) { // Only process if invoiceNo is not empty
                                if (!invoiceTotals[invoiceNo]) {
                                    invoiceTotals[invoiceNo] = 0;
                                }
                                invoiceTotals[invoiceNo] += receivedAmount;
                            }
                        }

                        // Step 2: Compare the summed received amount for each invoice with its invoice amount
                        // Since getInvoiceAmount is async, we need to handle this asynchronously
                        (async function() {
                            for (let invoiceNo in invoiceTotals) {
                                let totalReceivedAmount = invoiceTotals[invoiceNo];
                                let invoiceAmount = await getInvoiceAmount(invoiceNo); // Fetch invoice amount via AJAX

                                // Case 1: Total Received Amount > Invoice Amount
                                if (totalReceivedAmount > invoiceAmount) {
                                    let confirmMessage = `The total received amount ($${totalReceivedAmount.toFixed(2)}) for invoice ${invoiceNo} exceeds the invoice amount ($${invoiceAmount.toFixed(2)}). Do you want to continue with this amount?`;
                                    if (!confirm(confirmMessage)) { // Show warning popup
                                        shouldProceed = false; // If user clicks "No", stop submission
                                        $('.popuploader').hide();
                                        break;
                                    }
                                }
                                // Case 2: Total Received Amount <= Invoice Amount (no popup, proceed directly)
                            }

                            // Step 3: If validation passes, proceed with the form submission
                            if (shouldProceed) {
                                var client_id = $('#office_receipt_form input[name="client_id"]').val();
                                var myform = document.getElementById('office_receipt_form');
                                
                                // FIX: Set save_type in hidden field and FormData (was missing, causing intermittent issues)
                                $('#office_receipt_form input[name="save_type"]').val(savetype);
                                
                                var fd = new FormData(myform);
                                // Also append explicitly to ensure it's sent
                                fd.append('save_type', savetype);

                                $('.popuploader').show(); // Show loader if part of your UI

                                $.ajax({
                                    type: 'post',
                                    url: $("form[name=" + formName + "]").attr('action'),
                                    processData: false,
                                    contentType: false,
                                    data: fd,
                                    success: function(response) {
                                        $('.popuploader').hide();
                                        var obj = response; // Remove $.parseJSON(response)
                                        $('#createreceiptmodal').modal('hide');
                                        localStorage.setItem('activeTab', 'accounts');
                                        location.reload();

                                        if (obj.status) {
                                            if (obj.requestData) {
                                                var reqData = obj.requestData;
                                                var awsUrl = obj.awsUrl;
                                                var trRows_office = "";
                                                $.each(reqData, function(index, subArray) {
                                                    let awsLink = awsUrl !== "" ? '<a target="_blank" class="link-primary" href="' + awsUrl + '">' + crmI('fas fa-file-pdf') + '</a>' : '';

                                                    let payIconMap = {
                                                        'Cash': 'fa-arrow-down',
                                                        'Bank transfer': 'fa-arrow-right-from-bracket',
                                                        'EFTPOS': 'fa-arrow-right-from-bracket',
                                                        'Refund': 'fa-arrow-right-from-bracket'
                                                    };
                                                    let paymentIcon = payIconMap[subArray.payment_method] || 'fa-money-bill';

                                                    let depositAmount = subArray.deposit_amount ? '$ ' + parseFloat(subArray.deposit_amount).toFixed(2) : '';

                                                    trRows_office += `
                                                        <tr>
                                                            <td>${subArray.trans_date} ${awsLink}</td>
                                                            <td class="type-cell">
                                                                <i class="fas ${paymentIcon} type-icon"></i>
                                                                <span>
                                                                ${subArray.payment_method}<br/>
                                                                (${subArray.invoice_no})
                                                                </span>
                                                            </td>
                                                            <td></td>
                                                            <td class="description">${subArray.description}</td>
                                                            <td><a href="#" title="View Receipt ${subArray.trans_no}">${subArray.trans_no}</a></td>
                                                            <td class="currency text-success">${depositAmount}</td>
                                                        </tr>
                                                    `;
                                                });
                                                $('.productitemList_office').append(trRows_office);
                                            }

                                            // Update invoice row if applicable
                                            if (obj.invoice_no != "") {
                                                let invoiceNo = obj.invoice_no;
                                                let invoiceBalance = obj.invoice_balance;
                                                let invoiceStatus = obj.invoice_status;

                                                let statusClassMap = {
                                                    '0': 'status-unpaid',
                                                    '1': 'status-paid',
                                                    '2': 'status-partial',
                                                    '3': 'status-void'
                                                };

                                                let statusVal = {
                                                    '0': 'Unpaid',
                                                    '1': 'Paid',
                                                    '2': 'Partial',
                                                    '3': 'Void'
                                                };

                                                let statusClass = statusClassMap[invoiceStatus];
                                                let statusText = statusVal[invoiceStatus];

                                                $(".productitemList_invoice tr").each(function() {
                                                    let $row = $(this);
                                                    let rowInvoiceNo = $.trim($row.find("td:first").clone().children().remove().end().text());
                                                    if (rowInvoiceNo === invoiceNo) {
                                                        $row.find("td.currency").html(`$ ${invoiceBalance}`);
                                                        $row.find("td:last").html(
                                                            `<span class="status-badge ${statusClass}">${statusText}</span>`
                                                        );
                                                    }
                                                });
                                                $('.outstanding-balance').text('$ ' + obj.outstanding_balance);
                                            }

                                            //Fetch All Activities
                                            getallactivities(client_id);
                                            $('.custom-error-msg').html('<span class="alert alert-success">' + obj.message + '</span>');
                                        } else {
                                            $('.custom-error-msg').html('<span class="alert alert-danger">' + obj.message + '</span>');
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        $('.popuploader').hide();
                                        $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred while submitting the form.</span>');
                                    }
                                });
                            }
                        })();
                    }


                    else if(formName == 'adjust_invoice_receipt_form'){
						var client_id = $('#adjust_invoice_receipt_form input[name="client_id"]').val();
						var myform = document.getElementById('adjust_invoice_receipt_form');
						var fd = new FormData(myform);
						fd.append('save_type', savetype);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = response;
								if (typeof response === 'string') {
									try {
										obj = $.parseJSON(response);
									} catch (e) {
										obj = null;
									}
								}
								if (!obj || typeof obj !== 'object') {
									alert('Invalid server response. Please try again.');
									return;
								}
								$('#createadjustinvoicereceiptmodal').modal('hide');
								if (obj.status === true || obj.status === 1) {
									localStorage.setItem('activeTab', 'accounts');
									location.reload();
								} else {
									var adjMsg = obj.message || 'Could not save invoice. Please try again.';
									alert(adjMsg);
								}
							},
							error: function(xhr) {
								$('.popuploader').hide();
								var msg = 'Could not save invoice. Please try again.';
								if (xhr.responseJSON && xhr.responseJSON.message) {
									msg = xhr.responseJSON.message;
								}
								alert(msg);
							}
						});
					}

					else if(formName == 'create_journal_receipt'){
						var client_id = $('#create_journal_receipt input[name="client_id"]').val();
						var myform = document.getElementById('create_journal_receipt');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#createjournalreceiptmodal').modal('hide');
								if(obj.status){
									if(obj.requestData){
										var reqData = obj.requestData;
										var awsUrl = obj.awsUrl; //console.log('awsUrl='+awsUrl);
										var trRows_journal = "";
										$.each(reqData, function(index, subArray) {
											if(awsUrl != ""){
												var awsLink = '<a target="_blank" class="link-primary" href="'+awsUrl+'">' + crmI('fas fa-file-pdf') + '</a>';
											} else {
												var awsLink = '';
											}
											trRows_journal += "<tr><td>"+subArray.trans_date+" "+awsLink+"</td><td>"+subArray.entry_date+"</td><td>"+subArray.trans_no+"</td><td>"+subArray.invoice_no+"</td><td>"+subArray.description+"</td><td>"+subArray.withdrawal_amount+"</td></tr>";
										});
									}
									//console.log('trRows_journal='+trRows_journal);
									$('.lastRow_journal').before(trRows_journal);

									if(obj.db_total_withdrawal_amount){
										$('.totWithdrwalAmTillNow_journal').html("$"+obj.db_total_withdrawal_amount);
									}

									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
								}
							}
						});
					}

					else if(formName == 'tasktermclientform'){
						var client_id = $('#tasktermclientform input[name="partnerid"]').val();
						var myform = document.getElementById('tasktermclientform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#opentaskmodal').modal('hide');
								if(obj.status){
									$('#create_note').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/partner/get-tasks',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){
											 $('#my-datatable').DataTable().destroy();
											$('.taskdata').html(responses);
											$('#my-datatable').DataTable({
												"searching": false,
												"lengthChange": false,
											  "columnDefs": [
												{ "sortable": false, "targets": [0, 2, 3] }
											  ],
											  order: [[1, "desc"]] //column indexes is zero based


											}).draw();

										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					// educationform validation REMOVED - form deleted in Phase 1 (education system deprecated)
					}else if(formName == 'feeform'){
						var product_id = $('#feeform input[name="product_id"]').val();
						var myform = document.getElementById('feeform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){

								var obj = $.parseJSON(response);

								if(obj.status){
									$('#new_fee_option').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-all-fees',
										type:'GET',
										data:{clientid:product_id},
										success: function(responses){
											 $('.popuploader').hide();
											$('.feeslist').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					// Application fee form has been removed
					}else if(formName == 'applicationfeeform'){
						$('.custom-error-msg').html('<span class="alert alert-danger">Application fee options feature has been removed.</span>');
				// servicefeeform handler REMOVED - form/modals no longer exist; /get-services route removed
				// Payment schedule handlers (setuppaymentschedule, editinvpaymentschedule, addinvpaymentschedule) REMOVED - feature unused
					}else if(formName == 'checklistform'){

						var myform = document.getElementById('checklistform');
						var checklist_type = $('#checklist_type').val();
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('#create_checklist').modal('hide');
								$('.popuploader').hide();
								var obj = $.parseJSON(response)
								if(obj.status){
									$('#document_type').val();
									$('#checklistdesc').val();
									$('.due_date_col').hide();
									$('.checklistdue_date').val(0);
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$('.'+checklist_type+'_checklists').html(obj.data);
									$('.checklistcount').html(obj.countchecklist);
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});

					}else if(formName == 'editfeeform'){
						var product_id = $('#editfeeform input[name="product_id"]').val();
						var myform = document.getElementById('editfeeform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#editfeeoption').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-all-fees',
										type:'GET',
										data:{clientid:product_id},
										success: function(responses){
											 $('.popuploader').hide();
											$('.feeslist').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'promotionform'){
						var client_id = $('#promotionform input[name="client_id"]').val();
						var myform = document.getElementById('promotionform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#create_promotion').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-promotions',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){

											$('.promotionlists').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'editpromotionform'){
						var client_id = $('#editpromotionform input[name="client_id"]').val();
						var myform = document.getElementById('editpromotionform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#edit_promotion').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-promotions',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){

											$('.promotionlists').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'saveacademic'){
						var client_id = $('#saveacademic input[name="client_id"]').val();
						var myform = document.getElementById('saveacademic');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#add_academic_requirement').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$('.education_list').html(obj.data);
									$('.editacademic').attr('data-academic_score_per', obj.requirment.academic_score_per);
									$('.editacademic').attr('data-academic_score_type', obj.requirment.academic_score_type);
									$('.editacademic').attr('data-degree', obj.requirment.degree);
									$('.editacademic').show();
									$('.add_academic_requirement').hide();
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					// editeducationform validation REMOVED - form deleted in Phase 1 (education system deprecated)
					}else if(formName == 'testscoreform'){
						var client_id = $('#testscoreform input[name="client_id"]').val();
						var myform = document.getElementById('testscoreform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('.edit_english_test').modal('hide');
								if(obj.status){

								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								$('.tofl_lis').html(obj.toefl_Listening);
								$('.tofl_reading').html(obj.toefl_Reading);
								$('.tofl_writing').html(obj.toefl_Writing);
								$('.tofl_speaking').html(obj.toefl_Speaking);
								$('.tofl_score').html(obj.score_1);
								$('.toefl_date').html(obj.toefl_Date);
								$('.ilets_Listening').html(obj.ilets_Listening);
								$('.ilets_Reading').html(obj.ilets_Reading);
								$('.ilets_Writing').html(obj.ilets_Writing);
								$('.ilets_speaking').html(obj.ilets_Speaking);
								$('.ilets_score').html(obj.score_2);
								$('.ilets_date').html(obj.ilets_date);
								$('.pte_Listening').html(obj.pte_Listening);
								$('.pte_Reading').html(obj.pte_Reading);
								$('.pte_Writing').html(obj.pte_Writing);
								$('.pte_Speaking').html(obj.pte_Speaking);
								$('.pte_score').html(obj.score_3);
								$('.pte_date').html(obj.pte_Date);
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'saveagreement'){

						var myform = document.getElementById('saveagreement');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								if(obj.status){
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'savesubjectarea'){

						var myform = document.getElementById('savesubjectarea');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								if(obj.status){
									$('#other_info_add').modal('hide');
									$('.otherinfolist').html(obj.data);
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'editsubjectarea'){

						var myform = document.getElementById('editsubjectarea');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								if(obj.status){
									$('#other_info_edit').modal('hide');
									$('.otherinfolist').html(obj.data);
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'othertestform'){
						var client_id = $('#othertestform input[name="client_id"]').val();
						var myform = document.getElementById('othertestform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('.edit_other_test').modal('hide');
								if(obj.status){

								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								$('.gmat').html(obj.gmat);
								$('.gre').html(obj.gre);
								$('.sat_ii').html(obj.sat_ii);
								$('.sat_i').html(obj.sat_i);

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					// ajaxinvoicepaymentform REMOVED - addpaymentmodal and invoice/payment-store route removed (unused)
					}else if(formName == 'discontinue_matter'){
							var client_id = $('#discontinue_matter input[name="client_id"]').val();
						var myform = document.getElementById('discontinue_matter');
						var fd = new FormData(myform);

						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#discon_application').modal('hide');
								if(obj.status){
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									 $('.ifdiscont').hide();
									 $('.revertapp').show();
									$('.matterstatus').html('Discontinued');

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});

					}else if(formName == 'revertapplication'){
						var appliid = $('#revertapplication input[name="revapp_id"]').val();
						var myform = document.getElementById('revertapplication');
						var fd = new FormData(myform);

						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#revert_matter').modal('hide');
								if(obj.status){

									$.ajax({
										url: site_url+'/client-portal/logs',
										type:'GET',
										data:{id: appliid},
										success: function(responses){

											$('#accordion').html(responses);
										}
									});
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');

								$('.progress-circle span').html(obj.width+' %');
				var over = '';
				if(obj.width > 50){
					over = '50';
				}
				$("#progresscir").removeClass();
				$("#progresscir").addClass('progress-circle');
				$("#progresscir").addClass('prgs_'+obj.width);
				$("#progresscir").addClass('over_'+over);
									 $('.ifdiscont').show();
									$('.completestage').show();
									 $('.nextstage').hide();
									 $('.revertapp').hide();
									 $('.matterstatus').html('In Progress');

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});

					}else if(formName == 'xmatter_ownership'){

						var myform = document.getElementById('xmatter_ownership');
						var fd = new FormData(myform);

						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#matter_ownership').modal('hide');
								if(obj.status){
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');

									$('.matter_ownership').attr('data-ration',obj.ratio);
								}else{
									alert(obj.message);

								}
							}
						});

					}else if(formName == 'saleforcast'){

						var myform = document.getElementById('saleforcast');
						var fd = new FormData(myform);

						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);
								$('#application_opensaleforcast').modal('hide');
								if(obj.status){
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									 $('.opensaleforcast').attr('data-client_revenue',obj.client_revenue);
									 $('.opensaleforcast').attr('data-partner_revenue',obj.partner_revenue);
									 $('.opensaleforcast').attr('data-discounts',obj.discounts);

									$('.appsaleforcast .client_revenue').html(obj.client_revenue);
									$('.appsaleforcast .partner_revenue').html(obj.partner_revenue);
									$('.appsaleforcast .discounts').html(obj.discounts);
									var t = parseFloat(obj.client_revenue) + parseFloat(obj.partner_revenue) - parseFloat(obj.discounts);
									$('.appsaleforcast .netrevenue').html(t);
									$('.app_sale_forcast').html(t+ 'AUD');
								}else{
									alert(obj.message);

								}
							}
						});

				// saleforcastservice, inter_servform, inter_servform_serv, editinter_servform handlers REMOVED
				// Modals/forms removed; /get-services route removed; Interested Services feature deprecated
				}

                    else if(formName == 'appointform'){
						var client_id = $('#appointform input[name="client_id"]').val();
                        if (typeof window.syncAppointFormServiceIdFromRadio === 'function') {
                            window.syncAppointFormServiceIdFromRadio();
                        } else {
                            var checkedService = $('#appointform input[name="radioGroup"]:checked').val();
                            if (checkedService) {
                                $('#appointform input[name="service_id"]').val(checkedService);
                            }
                        }
                        var selectedService = $('#appointform input[name="radioGroup"]:checked').val();
                        var hiddenService = $('#appointform input[name="service_id"]').val();
                        if (selectedService && hiddenService && selectedService !== hiddenService) {
                            $('.popuploader').hide();
                            $('.custom-error-msg').html('<span class="alert alert-danger">Service selection is out of sync. Please re-select the service and time slot.</span>');
                            return false;
                        }
						 var appoint_date = $('#timeslot_col_date').val(); //alert(appoint_date);
                        var appoint_time = $('#timeslot_col_time').val(); //alert(appoint_time);

						if( appoint_date == "" || appoint_time == ""){
                            $('.popuploader').hide();
                            $('.timeslot_col_date_time').show();
                            return false;
                        } else {
							$('.timeslot_col_date_time').hide();
							var myform = document.getElementById('appointform');
							var fd = new FormData(myform);
                            $.ajax({
                                type:'post',
                                url:$("form[name="+formName+"]").attr('action'),
                                processData: false,
                                contentType: false,
                                data: fd,
                                dataType: 'json', // Expect JSON response
                                success: function(response){
                                    $('.popuploader').hide();
                                    
                                    // Response is already parsed as JSON when dataType is set
                                    var obj = response;
                                    
                                    // Fallback: if response is a string, parse it
                                    if (typeof response === 'string') {
                                        try {
                                            obj = $.parseJSON(response);
                                        } catch (e) {
                                            console.error('JSON parse error:', e);
                                            console.error('Response:', response);
                                            $('.custom-error-msg').html('<span class="alert alert-danger">Invalid server response. Please try again.</span>');
                                            return;
                                        }
                                    }

                                    if(obj.status){
                                        /*if(obj.reloadpage){
                                            location.reload();
                                        }*/
                                        $('.add_appointment').modal('hide');
                                        $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
                                        $.ajax({
                                            url: site_url+'/get-appointments',
                                            type:'GET',
                                            data:{clientid:client_id},
                                            success: function(responses){
                                                $('.appointmentlist').html(responses);
                                            }
                                        });
                                        //Fetch All Activities
                                        getallactivities(client_id);
									}else{
										$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                    }
								},
                                error: function(xhr, status, error) {
                                    $('.popuploader').hide();
                                    console.error('AJAX Error:', error);
                                    console.error('Status:', status);
                                    console.error('Response:', xhr.responseText);
                                    
                                    var errorMessage = 'Failed to create appointment. Please try again.';
                                    try {
                                        var errorObj = JSON.parse(xhr.responseText);
                                        if (errorObj.message) {
                                            errorMessage = errorObj.message;
                                        }
                                    } catch (e) {
                                        // Response is not JSON
                                    }
                                    
                                    $('.custom-error-msg').html('<span class="alert alert-danger">' + errorMessage + '</span>');
                                }
							});
					  	}
					}

                    else if(formName == 'partnerappointform'){
						var client_id = $('#partnerappointform input[name="client_id"]').val();
						var myform = document.getElementById('partnerappointform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#create_appoint').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/partner/get-appointments',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){

											$('.appointmentlist').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'appliappointform'){
						var client_id = $('#appliappointform input[name="client_id"]').val();
						var noteid = $('#appliappointform input[name="noteid"]').val();
						var myform = document.getElementById('appliappointform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('.add_appointment').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-appointments',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){

											$('.appointmentlist').html(responses);
										}
									});

									$.ajax({
										url: site_url+'/client-portal/logs',
										type:'GET',
										data:{id: noteid},
										success: function(responses){

											$('#accordion').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}else if(formName == 'appkicationsendmail'){
						var client_id = $('#appkicationsendmail input[name="client_id"]').val();
						var noteid = $('#appkicationsendmail input[name="noteid"]').val();
						var myform = document.getElementById('appkicationsendmail');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#matteremailmodal').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									// Show prominent success alert so user sees confirmation
									var successMsg = obj.message || 'Email successfully sent.';
									if (typeof iziToast !== 'undefined' && iziToast.success) {
										iziToast.success({
											title: 'Success',
											message: successMsg,
											position: 'topRight',
											timeout: 4000
										});
									} else {
										alert(successMsg);
									}
									$.ajax({
										url: site_url+'/get-appointments',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){

											$('.appointmentlist').html(responses);
										}
									});

									$.ajax({
										url: site_url+'/client-portal/logs',
										type:'GET',
										data:{id: noteid},
										success: function(responses){

											$('#accordion').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					// DEPRECATED: Appointment system removed - editappointment form no longer exists
					}else if(formName == 'editappointment'){
						console.warn('editappointment form validation has been removed - appointment system deprecated');
						return false;
						/*
						var client_id = $('#editappointment input[name="client_id"]').val();
						var myform = document.getElementById('editappointment');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#edit_appointment').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-appointments',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){
                                            $('.appointmentlist').html(responses);
										}
									});
									//Fetch All Activities
                                    getallactivities(client_id);
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
							}
						});
						*/
						// End of deprecated editappointment validation
					}else if(formName == 'editpartnerappointment'){
						var client_id = $('#editpartnerappointment input[name="client_id"]').val();
						var myform = document.getElementById('editpartnerappointment');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('.popuploader').hide();
								var obj = $.parseJSON(response);

								if(obj.status){
									$('#edit_appointment').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/partner/get-appointments',
										type:'GET',
										data:{clientid:client_id},
										success: function(responses){

											$('.appointmentlist').html(responses);
										}
									});

								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');

								}
							}
						});
					}
				else if(formName == 'notetermform')
				{
					var client_id = $('input[name="client_id"]').val() || window.ClientDetailConfig?.clientId;
					var myform = document.getElementById('notetermform');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
						success: function(response){
							console.log('Note form success callback reached');
							$('.popuploader').hide();
							// Handle both string and already-parsed JSON responses
							var obj = typeof response === 'string' ? $.parseJSON(response) : response;
							console.log('Parsed response:', obj);
							if(obj.status){
								console.log('Note added successfully, client_id:', client_id);
								$('#create_note').modal('hide');
								$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
									$.ajax({
										url: site_url+'/get-notes',
										type:'GET',
										data:{clientid:client_id,type:'client'},
										success: function(responses){
											$('.note_term_list').html(responses);
											if (typeof window.filterNotes === 'function') {
												window.filterNotes();
											}
										}
									});
									getallactivities(client_id);
								} else {
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
								}
							}
						});
					}
					else if(formName == 'notetermform_n')
					{
                        var client_id = $('input[name="client_id"]').val() || window.ClientDetailConfig?.clientId;
						console.log('notetermform_n handler - client_id:', client_id);
						var myform = document.getElementById('notetermform_n');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								console.log('notetermform_n success callback');
								$('.popuploader').hide();
								// Handle both string and already-parsed JSON responses
								var obj = typeof response === 'string' ? $.parseJSON(response) : response;
								console.log('notetermform_n parsed response:', obj);

								if(obj.status){
									console.log('notetermform_n note added successfully');
								    $('#create_note_d input[name="title"]').val('');
								    $('#create_note_d input[name="title"]').val('');
									$("#create_note_d .tinymce-editor").val('');
									$('#create_note_d input[name="noteid"]').val('');
									// Clear TinyMCE editor if initialized
									if (typeof tinymce !== 'undefined') {
										$("#create_note_d .tinymce-editor").each(function() {
											var editorId = $(this).attr('id');
											if (editorId && tinymce.get(editorId)) {
												tinymce.get(editorId).setContent('');
											}
										});
									}
									$('#create_note_d').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');

									$.ajax({
										url: site_url+'/get-notes',
										type:'GET',
										data:{clientid:client_id,type:'client'},
										success: function(responses){
											$('.note_term_list').html(responses);
											if (typeof window.filterNotes === 'function') {
												window.filterNotes();
											}
										}
									});
                                    getallactivities(client_id);
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
							}
						});
					}
				// addtoapplicationform handler REMOVED - #add_application modal removed; savetoapplication route removed

			    else if(formName == 'submit-review')
					{
						$("form[name=submit-review] :input[data-max]").each(function(){
							var data_max  = $(this).attr('data-max');
							var value = $.trim($(this).val());
							if(parseInt(value) > parseInt(data_max))
								{
									$(this).after(errorDisplay(maxError + data_max));
									$("#loader").hide();
									return false;
								}
							else
								{
									$("form[name="+formName+"]").submit();
									return true;
								}
						});
					}

                    else if(formName == 'inbox-email-assign-to-client-matter'){
                        var myform = document.getElementById('inbox-email-assign-to-client-matter');
                        var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('#inbox_assignemail_modal').modal('hide');
								var obj = $.parseJSON(response);
                                if(obj.status){
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								} else {
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
							}
						});
                    }

                    else if(formName == 'sent-email-assign-to-client-matter'){
                        var myform = document.getElementById('sent-email-assign-to-client-matter');
                        var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('#sent_assignemail_modal').modal('hide');
								var obj = $.parseJSON(response);
                                if(obj.status){
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								} else {
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
							}
						});
                    }


                    else if(formName == 'inbox-email-reassign-to-client-matter'){
                        var myform = document.getElementById('inbox-email-reassign-to-client-matter');
                        var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
                                $('.popuploader').hide();
								$('#inbox_reassignemail_modal').modal('hide');
                                location.reload();
								var obj = $.parseJSON(response);
                                if(obj.status){
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								} else {
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
							}
						});
                    }

					else if(formName == 'sent-email-reassign-to-client-matter') {
						var myform = document.getElementById('sent-email-reassign-to-client-matter');
                        var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
                                $('.popuploader').hide();
								$('#sent_reassignemail_modal').modal('hide');
                                location.reload();
								var obj = $.parseJSON(response);
                                if(obj.status){
                                    $('.custom-error-msg').html('<span class="alert alert-success">'+obj.message+'</span>');
								} else {
									$('.custom-error-msg').html('<span class="alert alert-danger">'+obj.message+'</span>');
                                }
							}
						});
                    }

					else if(formName == 'sendmail' || formName == 'add-compose'){
						var myform = document.querySelector('form[name="' + formName + '"]');
						if(!myform){
							$('.popuploader').hide();
							$('.custom-error-msg').html('<span class="alert alert-danger">Form not found. Please refresh the page and try again.</span>');
							return false;
						}
						
						// Get CSRF token from meta tag (most current source)
						var csrfToken = $('meta[name="csrf-token"]').attr('content');
						if (!csrfToken || csrfToken.length === 0) {
							$('.popuploader').hide();
							$('.custom-error-msg').html('<span class="alert alert-danger">Security token missing. Please refresh the page and try again.</span>');
							return false;
						}
						
						// Persist TinyMCE (and similar) into textareas before FormData — required for AJAX submit
						if (typeof tinymce !== 'undefined' && typeof tinymce.triggerSave === 'function') {
							tinymce.triggerSave();
						}

						// Create FormData from form
						var fd = new FormData(myform);
						mmPrepareComposeEmailFormData(myform, fd, csrfToken);
						
						// Use same-origin URL to avoid 403 from wrong domain (e.g. APP_URL vs current host)
						var actionAttr = $("form[name=\"" + formName + "\"]").attr('action') || '';
						var sendmailUrl = actionAttr;
						try {
							if (actionAttr.indexOf('http') === 0) {
								var actionUrl = new URL(actionAttr, window.location.origin);
								if (actionUrl.origin !== window.location.origin) {
									sendmailUrl = window.location.origin + actionUrl.pathname + (actionUrl.search || '');
								} else {
									sendmailUrl = actionUrl.pathname + (actionUrl.search || '');
								}
							}
						} catch (e) {}
						
						$.ajax({
							type:'post',
							url: sendmailUrl,
							processData: false,
							contentType: false,
							data: fd,
							dataType: 'json',  // Expect JSON so Laravel returns 401/419 JSON instead of HTML redirect
							xhrFields: {
								withCredentials: true  // Ensure session cookies are sent
							},
							headers: {
								'X-CSRF-TOKEN': csrfToken,
								'Accept': 'application/json',  // Tell Laravel to return JSON for auth/CSRF errors
								'X-Requested-With': 'XMLHttpRequest'
							},
							success: function(response){
								$('.popuploader').hide();
								// Handle both JSON string and object responses
								var obj = typeof response === 'string' ? $.parseJSON(response) : response;
								
								if(obj.status || (response.success !== undefined && response.success)){
									$('#emailmodal').modal('hide');
									$('.custom-error-msg').html('<span class="alert alert-success">'+(obj.message || response.message || 'Email sent successfully!')+'</span>');
									// On reload: switch to Emails tab and Sent view (for client detail page)
									try {
										localStorage.setItem('activeTab', 'emails');
										localStorage.setItem('emailTabSwitchToSent', '1');
									} catch (e) {}
									// Show prominent success alert so user sees confirmation
									var successMsg = obj.message || response.message || 'Email successfully sent.';
									if (typeof iziToast !== 'undefined' && iziToast.success) {
										iziToast.success({
											title: 'Success',
											message: successMsg,
											position: 'topRight',
											timeout: 4000
										});
									} else {
										alert(successMsg);
									}
									// Reload page or refresh email list if needed
									setTimeout(function(){
										location.reload();
									}, 1500);
								}else{
									$('.custom-error-msg').html('<span class="alert alert-danger">'+(obj.message || response.message || 'Failed to send email. Please try again.')+'</span>');
								}
							},
							error: function(xhr, status, error){
								$('.popuploader').hide();
								var errorMessage = 'Failed to send email. Please try again.';
								
								// JSON parse error (server returned non-JSON e.g. HTML)
								if (status === 'parsererror') {
									$('.custom-error-msg').html('<span class="alert alert-danger">Invalid server response. Please refresh the page and try again.</span>');
									return;
								}
								
								// 401 Unauthenticated - session expired
								if(xhr.status === 401){
									$('.custom-error-msg').html('<span class="alert alert-warning">Your session has expired. Refreshing page...</span>');
									setTimeout(function(){ location.reload(); }, 1500);
									return;
								}
								// 419 CSRF token mismatch - token expired (modal open too long)
								if(xhr.status === 419){
									$('.custom-error-msg').html('<span class="alert alert-warning">Security token expired. Refreshing page...</span>');
									setTimeout(function(){ location.reload(); }, 1500);
									return;
								}
								// 403 Forbidden — compose CRM denial vs server/WAF block
								if(xhr.status === 403){
									var responseText = xhr.responseText || '';
									var responseJSON = xhr.responseJSON || {};
									var isCsrf = responseText.includes('CSRF') || responseText.includes('csrf') ||
										(responseJSON.message && (responseJSON.message.includes('CSRF') || responseJSON.message.includes('csrf')));
									if (isCsrf) {
										$('.custom-error-msg').html('<span class="alert alert-warning">Security token expired. Refresh the page and try again.</span>');
										return;
									}
									var isComposeForbidden = responseJSON.error_type === 'compose_email_forbidden';
									var msg;
									if (isComposeForbidden) {
										msg = (responseJSON.message && String(responseJSON.message).trim() !== '')
											? responseJSON.message
											: 'You are not authorized to send email.';
									} else if (!responseJSON.message || responseText.indexOf('<html') !== -1 || responseText.indexOf('<!DOCTYPE') !== -1) {
										msg = 'The server blocked this email request (security filter). Please try again or contact support if it persists.';
									} else {
										msg = responseJSON.message;
									}
									$('.custom-error-msg').html('<span class="alert alert-warning">' + msg + '</span>');
									alert(msg);
									return;
								}
								if(xhr.status === 422){
									var errors = xhr.responseJSON && xhr.responseJSON.errors ? xhr.responseJSON.errors : {};
									var errorHtml = '<span class="alert alert-danger">Validation errors:<ul>';
									for(var field in errors){
										if(errors.hasOwnProperty(field)){
											errorHtml += '<li>'+errors[field][0]+'</li>';
										}
									}
									errorHtml += '</ul></span>';
									$('.custom-error-msg').html(errorHtml);
									return;
								}
								if(xhr.responseJSON && xhr.responseJSON.message){
									errorMessage = xhr.responseJSON.message;
								}
								
								$('.custom-error-msg').html('<span class="alert alert-danger">'+errorMessage+'</span>');
							}
						});
					}
					else if(formName == 'checkinmodalsave')
					{
						// Validate that utype is set before submitting
						var utype = $('#utype').val();
						if(!utype || utype.trim() === '') {
							$('.popuploader').hide();
							// Show error near the contact field
							var contactField = $('.js-data-example-ajax-check');
							if(contactField.length) {
								contactField.after('<span class="custom-error" role="alert"><strong>Please select a contact to set the contact type.</strong></span>');
							} else {
								alert('Please select a contact before submitting.');
							}
							$('html, body').animate({scrollTop:0}, 'slow');
							return false;
						}
						// Validate that contact is selected
						var contactValue = $('.js-data-example-ajax-check').val();
						if(!contactValue || contactValue.length === 0) {
							$('.popuploader').hide();
							var contactField = $('.js-data-example-ajax-check');
							contactField.after('<span class="custom-error" role="alert"><strong>Please select a contact.</strong></span>');
							$('html, body').animate({scrollTop:0}, 'slow');
							return false;
						}
						$("form[name="+formName+"]").submit();
						return true;
					}
					else if(formName == 'stags_matter')
					{
						var $form = $("form[name="+formName+"]");
						var $container = $form.find('#tags_modal_container');
						var tags = [];
						if ($container.length) {
							$container.find('.tag-pill').each(function(){
								var n = $(this).attr('data-tag-name');
								if (n) tags.push(n);
							});
						}
						var fd = new FormData($form[0]);
						fd.delete('tag[]');
						tags.forEach(function(tag){ fd.append('tag[]', tag); });
						$.ajax({
							type: 'post',
							url: $form.attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							dataType: 'json',
							headers: { 'X-Requested-With': 'XMLHttpRequest' },
							success: function(resp){
								$('.popuploader').hide();
								$('#tags_clients').modal('hide');
								if (typeof window.mmUpdateClientTagsUi === 'function') {
									window.mmUpdateClientTagsUi(resp || {});
								}
								var okMsg = (resp && resp.message) ? resp.message : 'Tags saved successfully';
								if (typeof iziToast !== 'undefined' && iziToast.success) {
									iziToast.success({ title: 'Success', message: okMsg, position: 'topRight', timeout: 3000 });
								}
							},
							error: function(xhr){
								$('.popuploader').hide();
								var msg = 'Failed to save tags. Please try again.';
								if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
								else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
									var errs = xhr.responseJSON.errors;
									msg = (errs.client_id && errs.client_id[0]) || (errs.tag && errs.tag[0]) || msg;
								}
								$('.custom-error-msg').html('<span class="alert alert-danger">'+msg+'</span>');
								if (typeof iziToast !== 'undefined' && iziToast.error) {
									iziToast.error({ title: 'Error', message: msg, position: 'topRight', timeout: 4000 });
								}
							}
						});
						return true;
					}
					else if(formName == 'create_invoice_receipt')
					{
						$('form[name="create_invoice_receipt"] input[name="save_type"]').val(savetype);
						$(".popuploader").hide();
						$("form[name="+formName+"]").submit();
						return true;
					}
					else if(formName == 'convert_lead_to_client')
					{
						console.log('[ConvertLeadToClient] Save clicked, validation passed');
						$(".popuploader").hide();
						var formEl = document.querySelector("#convertLeadToClientModal form[name='convert_lead_to_client']") || document.querySelector("form[name='convert_lead_to_client']");
						if (!formEl) {
							console.warn('[ConvertLeadToClient] Form not found in modal, falling back to form[name=convert_lead_to_client]');
							$("form[name="+formName+"]").submit();
							return true;
						}
						console.log('[ConvertLeadToClient] Form found', { action: formEl.action, method: formEl.method });
						var generalCheckbox = document.getElementById('general_matter_checkbox_new');
						var matterSelect = document.getElementById('sel_matter_id');
						var matterHidden = document.getElementById('convert_matter_id_final');
						var matterVal = (generalCheckbox && generalCheckbox.checked) ? '1' : (matterSelect ? (matterSelect.value || '').trim() : '');
						console.log('[ConvertLeadToClient] Matter resolution', { generalCheckboxChecked: !!(generalCheckbox && generalCheckbox.checked), matterSelectValue: matterSelect ? matterSelect.value : null, matterVal: matterVal });
						if (!matterVal) {
							console.warn('[ConvertLeadToClient] Matter required but empty, showing error');
							$(".custom-error").remove();
							var errMsg = "<span class='custom-error' role='alert'>" + requiredError + "</span>";
							if (matterSelect && matterSelect.parentNode) {
								$(matterSelect).after(errMsg);
							}
							$('html, body').animate({scrollTop: $('#convertLeadToClientModal').offset().top - 100}, 'slow');
							return false;
						}
						if (matterHidden) {
							matterHidden.value = matterVal;
							console.log('[ConvertLeadToClient] Set hidden matter_id to', matterVal);
						} else {
							console.warn('[ConvertLeadToClient] Hidden #convert_matter_id_final not found');
						}
						console.log('[ConvertLeadToClient] Submitting form now');
						formEl.submit();
						return true;
					}
                    else
					{
						if(formName == 'invoiceform')
						{
							$('input[name="btn"]').val(savetype);
						}
						$("form[name="+formName+"]").submit();
						return true;
					}
			}

	}


function customInvoiceValidate(formName, savetype)
	{
		$("#loader").show(); //all form submit

		var i = 0;
		$(".custom-error").remove(); //remove all errors when submit the button
		$("#save_type").val(savetype);
		$("form[name="+formName+"] :input[data-valid]").each(function(){
			var dataValidation = $(this).attr('data-valid');
			var splitDataValidation = dataValidation.split(' ');

			var j = 0; //for serial wise errors shown
			if($.inArray("required", splitDataValidation) !== -1) //for required
				{
					var for_class = $(this).attr('class');
					if(for_class.indexOf('multiselect_subject') != -1)
						{
							var value = $.trim($(this).val());
							if (value.length === 0)
								{
									i++;
									j++;
									$(this).parent().after(errorDisplay(requiredError));
								}
						}
					else
						{
							if( !$.trim($(this).val()) )
								{
									i++;
									j++;
									$(this).after(errorDisplay(requiredError));
								}
						}
				}
			if(j <= 0)
				{
					if($.inArray("email", splitDataValidation) !== -1) //for email
						{
							if(!validateEmail($.trim($(this).val())))
								{
									i++;
									$(this).after(errorDisplay(emailError));
								}
						}


					var forMin = splitDataValidation.find(a =>a.includes("min"));
					if(typeof forMin != 'undefined')
						{
							var breakMin = forMin.split('-');
							var digit = breakMin[1];

							var value = $.trim($(this).val()).length;
							if(value < digit)
								{
									i++;
									$(this).after(errorDisplay(min+' '+digit+' character.'));
								}
						}

					var forMax = splitDataValidation.find(a =>a.includes("max"));
					if(typeof forMax != 'undefined')
						{
							var breakMax = forMax.split('-');
							var digit = breakMax[1];

							var value = $.trim($(this).val()).length;
							if(value > digit)
								{
									i++;
									$(this).after(errorDisplay(max+' '+digit+' character.'));
								}
						}

					var forEqual = splitDataValidation.find(a =>a.includes("equal"));
					if(typeof forEqual != 'undefined')
						{
							var breakEqual = forEqual.split('-');
							var digit = breakEqual[1];

							var value = ($.trim($(this).val()).replace(/[^a-z0-9\s]/gi, '').replace(/[_\s]/g, '-')).length;
							if(value != digit)
								{
									i++;
									$(this).after(errorDisplay(equal+' '+digit+' character.'));
								}
						}
				}
		});

		if(i > 0)
			{
				if(formName == 'add-query'){
					$('html, body').animate({scrollTop:$("#row_scroll"). offset(). top}, 'slow');
				}else if(formName != 'upload-answer')	{
					$('html, body').animate({scrollTop:0}, 'slow');
				}
				$("#loader").hide();
				return false;
			}
		else
			{
				if(formName == 'add-query')
					{
						$('#preloader').show();
						$('#preloader div').show();
						var myform = document.getElementById('enquiryco');
						var fd = new FormData(myform);
						$.ajax({
							type:'post',
							url:$("form[name="+formName+"]").attr('action'),
							processData: false,
							contentType: false,
							data: fd,
							success: function(response){
								$('#preloader').hide();
								$('#preloader div').hide();
								var obj = $.parseJSON(response);
								if(obj.success){
									window.location = redirecturl;
								}else{
									$('.customerror').html(obj.message);
									$('html, body').animate({scrollTop:$("#row_scroll"). offset(). top}, 'slow');
								}
							}
						});
					}
				else
					{

						$("form[name="+formName+"]").submit();
						return true;
					}
			}

	}

function errorDisplay(error) {
	return "<span class='custom-error' role='alert'>"+error+"</span>";
}

function validateEmail(sEmail) {
    var filter = /^([\w-\.]+)@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.)|(([\w-]+\.)+))([a-zA-Z]{2,4}|[0-9]{1,3})(\]?)$/;
    if (filter.test(sEmail)) {
		return true;
	}
    else {
		return false;
    }
}


// Function to show validation errors dynamically
function displayValidationErrors(errors) {
    $('.error-message').remove(); // Remove existing messages
    $('.popuploader').hide();
    $.each(errors, function(field, messages) { //alert(field+'---'+messages);
        //let inputField = $('#'+field);
        let errorMessage = '<small style="display: inline-block;" class="text-danger error-message">' + messages[0] + '</small>';
        //inputField.after(errorMessage);
        $('#email_files').after(errorMessage);
    });
}

// Function to show validation errors dynamically
function displayValidationErrors2(errors) {
    $('.error-message').remove(); // Remove existing messages
    $('.popuploader').hide();
    $.each(errors, function(field, messages) { //alert(field+'---'+messages);
        //let inputField = $('#'+field);
        let errorMessage = '<small style="display: inline-block;" class="text-danger error-message">' + messages[0] + '</small>';
        $('#email_files1').after(errorMessage);
    });
}

// Function to fetch invoice amount via AJAX
async function getInvoiceAmount(invoiceNo) {
    try {
        const response = await fetch('/clients/invoiceamount', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), // Include CSRF token
            },
            body: JSON.stringify({ invoice_no: invoiceNo }),
        });

        const data = await response.json();

        if (data.success) {
            return parseFloat(data.balance_amount) || 0;
        } else {
            console.error('Invoice not found:', data.message);
            return 0;
        }
    } catch (error) {
        console.error('Error fetching invoice amount:', error);
        return 0; // Return 0 in case of error
    }
}

//Fetch All Activities
function getallactivities(client_id){
	if (typeof window.loadActivities === 'function') {
		window.loadActivities({ reset: true });
		$('.popuploader').hide();
		return;
	}
	$.ajax({
		url: site_url+'/get-activities',
		type:'GET',
		datatype:'json',
		data:{id:client_id, page: 1, per_page: 40},
		success: function(responses, textStatus, xhr){
			// Check if response is empty or invalid
			if (!responses || responses === '' || (typeof responses === 'string' && responses.trim() === '')) {
				return; // Exit early to prevent JSON.parse error
			}
			
			// Check if response is already parsed (jQuery might auto-parse JSON)
			var ress;
			if (typeof responses === 'object') {
				ress = responses;
			} else if (typeof responses === 'string') {
				// Check if it looks like JSON
				var trimmed = responses.trim();
				if (trimmed.charAt(0) !== '{' && trimmed.charAt(0) !== '[') {
					return; // Exit early if not JSON
				}
				
				try {
					ress = JSON.parse(responses);
				} catch(e) {
					return; // Exit early on parse error
				}
			} else {
				return;
			}
			if (!ress.data || !$.isArray(ress.data)) {
				ress.data = [];
			}
			var html = '';
		$.each(ress.data, function (k, v) {
			var activityType = v.activity_type ?? '';
			var noteSubtypeClass = '';
			var subjectIcon;
			var iconClass = '';
			var subject = v.subject ?? '';
			var subjectLower = subject.toLowerCase();

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
				subjectIcon = crmI('fas fa-bolt');
				iconClass = 'feed-icon-activity';
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
			} else if (subjectLower.indexOf('invoice') !== -1 || subjectLower.indexOf('receipt') !== -1 || subjectLower.indexOf('ledger') !== -1 || subjectLower.indexOf('payment') !== -1 || subjectLower.indexOf('account') !== -1) {
				subjectIcon = crmI('fas fa-dollar-sign');
				iconClass = 'feed-icon-financial';
			} else if (subjectLower.indexOf('document') !== -1 && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
				subjectIcon = crmI('fas fa-file-alt');
				iconClass = '';
			} else if (subjectLower.indexOf('document') !== -1) {
				subjectIcon = crmI('fas fa-file-alt');
				iconClass = '';
			} else {
				subjectIcon = crmI('fas fa-sticky-note');
				iconClass = '';
			}

			var description = v.message ?? '';
			var taskGroup = v.task_group ?? '';
			var followupDate = v.followup_date ?? '';
			var date = v.date ?? '';
			var fullName = v.name ?? '';
			var activityTypeClass = activityType ? 'activity-type-' + activityType : '';
			if (!activityTypeClass) {
				if (/uploaded email:/i.test(subjectLower)) {
					activityTypeClass = 'activity-type-email';
				} else if (subjectLower.indexOf('invoice') !== -1 || subjectLower.indexOf('receipt') !== -1 || subjectLower.indexOf('ledger') !== -1 || subjectLower.indexOf('payment') !== -1 || subjectLower.indexOf('account') !== -1) {
					activityTypeClass = 'activity-type-financial';
				} else if (subjectLower.indexOf('document') !== -1 && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
					activityTypeClass = 'activity-type-document';
				}
			}
			var headline = v.subject_without_staff_prefix === true ? subject : (fullName + ' ' + subject);
			var feedItemClass = activityType === 'stage' ? 'feed-item--stage' : 'feed-item--email';
			var createdAtYmd = v.created_at_ymd || '';

			var innerContent;
			if (activityType === 'stage') {
				innerContent =
					'<div class="feed-item-stage">' +
						'<div class="feed-item-stage-header">' +
							'<span class="feed-item-staff">' + fullName + '</span>' +
							'<span class="feed-timestamp">' + date + '</span>' +
						'</div>' +
						'<div class="feed-item-stage-body">' + (v.message ? v.message : '') + '</div>' +
					'</div>';
			} else {
				innerContent =
					'<p><strong>' + headline + '</strong></p>' +
					(description !== '' ? '<p>' + description + '</p>' : '') +
					(taskGroup !== '' ? '<p>' + taskGroup + '</p>' : '') +
					(followupDate !== '' ? '<p>' + followupDate + '</p>' : '') +
					'<span class="feed-timestamp">' + date + '</span>';
			}

			html += `
				<li class="feed-item ${feedItemClass} activity ${activityTypeClass}${noteSubtypeClass}" id="activity_${v.activity_id}" data-created-at="${createdAtYmd}">
					<span class="feed-icon ${iconClass}">
						${subjectIcon}
					</span>
					<div class="feed-content">
						${innerContent}
					</div>
				</li>
			`;
		});
			$('.feed-list').html(html);
			$('.popuploader').hide();
			if (window.ActivityFeed && typeof window.ActivityFeed.reapplyCurrentFilter === 'function') {
				window.ActivityFeed.reapplyCurrentFilter();
			}
		},
		error: function(xhr, status, error){
			// Silent error handling
		}
	});
}

/** Escape HTML for tag names rendered into the Tag(s) list / modal pills. */
function mmEscapeTagHtml(str) {
	return String(str == null ? '' : str)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

function mmTagUiIcon(legacyClass) {
	if (typeof window.crmI === 'function') {
		return window.crmI(legacyClass);
	}
	if (typeof crmIconLegacy === 'function') {
		return crmIconLegacy(legacyClass);
	}
	return '<i class="' + legacyClass + '"></i>';
}

/**
 * Refresh Tag(s) list + modal pills after AJAX save (no page reload).
 * Used by stags_matter save on client/company detail pages.
 */
window.mmUpdateClientTagsUi = function(resp) {
	var tags = (resp && resp.tags) ? resp.tags : {};
	var normalTags = Array.isArray(tags.normal) ? tags.normal : [];
	var redTags = Array.isArray(tags.red) ? tags.red : [];
	var clientId = (resp && resp.client_id) ? String(resp.client_id) : '';
	var $list = $('.client-tags-list').first();
	var html = '';
	var i;

	for (i = 0; i < normalTags.length; i++) {
		html += '<span class="ui label tag-normal ag-flex ag-align-center ag-space-between" style="display: inline-flex; margin: 5px 5px 5px 0;">' +
			'<span class="col-hr-1" style="font-size: 12px;">' + mmEscapeTagHtml(normalTags[i].name) + '</span>' +
			'</span>';
	}

	if (redTags.length > 0) {
		html += '<div class="red-tags-section" style="display: none; margin-top: 10px;">' +
			'<div style="margin-bottom: 5px; font-size: 11px; color: #dc3545; font-weight: bold;">' +
			mmTagUiIcon('fa-exclamation-triangle') + ' Red Tags:' +
			'</div>';
		for (i = 0; i < redTags.length; i++) {
			html += '<span class="ui label tag-red ag-flex ag-align-center ag-space-between" style="display: inline-flex; margin: 5px 5px 5px 0; background-color: #dc3545; border: 1px solid #c82333;">' +
				'<span class="col-hr-1" style="font-size: 12px;">' + mmEscapeTagHtml(redTags[i].name) + '</span>' +
				'</span>';
		}
		html += '</div>' +
			'<div style="margin-top: 10px;">' +
			'<a href="javascript:;" id="toggleRedTags" class="btn btn-sm btn-outline-danger" data-client-id="' + mmEscapeTagHtml(clientId) + '" title="Show Red Tags">' +
			mmTagUiIcon('fa-eye') +
			'</a></div>';
	}

	if ($list.length) {
		$list.html(html);
		mmBindRedTagsToggle($list, clientId);
	}

	// Keep modal pills in sync so reopening Edit shows saved state without reload
	var $pillsInner = $('#tags_modal_container .tags-pills-inner');
	if ($pillsInner.length) {
		var allTags = normalTags.concat(redTags);
		var pillsHtml = '';
		for (i = 0; i < allTags.length; i++) {
			var name = allTags[i].name;
			pillsHtml += '<span class="tag-pill" data-tag-name="' + mmEscapeTagHtml(name) + '">' +
				'<span class="tag-pill-text">' + mmEscapeTagHtml(name) + '</span>' +
				'<button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button>' +
				'</span>';
		}
		pillsHtml += '<input type="text" id="tag_input" class="tag-input-inline" placeholder="Type and press comma or Enter to add" autocomplete="off">';
		$pillsInner.html(pillsHtml);
		$('#tags_validation').val(allTags.length > 0 ? '1' : '');
	}
};

function mmBindRedTagsToggle($list, clientId) {
	var $btn = $list.find('#toggleRedTags');
	var $section = $list.find('.red-tags-section');
	if (!$btn.length || !$section.length) {
		return;
	}
	var storageKey = 'redTagsVisible_' + (clientId || $btn.attr('data-client-id') || '');
	var isVisible = false;
	try {
		isVisible = sessionStorage.getItem(storageKey) === 'true';
	} catch (e) { /* ignore */ }

	if (isVisible) {
		$section.css('display', 'block');
		$btn.html(mmTagUiIcon('fas fa-eye-slash'));
		$btn.removeClass('btn-outline-danger').addClass('btn-danger');
		$btn.attr('title', 'Hide Red Tags');
	}

	$btn.off('click.mmRedTags').on('click.mmRedTags', function() {
		var currentlyVisible = $section.css('display') !== 'none';
		if (currentlyVisible) {
			$section.css('display', 'none');
			$(this).html(mmTagUiIcon('fas fa-eye'));
			$(this).removeClass('btn-danger').addClass('btn-outline-danger');
			$(this).attr('title', 'Show Red Tags');
			try { sessionStorage.setItem(storageKey, 'false'); } catch (e) { /* ignore */ }
		} else {
			$section.css('display', 'block');
			$(this).html(mmTagUiIcon('fas fa-eye-slash'));
			$(this).removeClass('btn-outline-danger').addClass('btn-danger');
			$(this).attr('title', 'Hide Red Tags');
			try { sessionStorage.setItem(storageKey, 'true'); } catch (e) { /* ignore */ }
		}
	});
}
