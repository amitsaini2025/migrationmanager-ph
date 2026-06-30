@extends('layouts.crm_client_detail')
@section('title', 'Leads')

@section('content')
<style>.popover {max-width:700px;}
.timeline{margin:0 0 45px;padding:0;position:relative}.timeline::before{border-radius:.25rem;background:#dee2e6;bottom:0;content:'';left:31px;margin:0;position:absolute;top:0;width:4px}.timeline>div{margin-bottom:15px;margin-right:10px;position:relative}.timeline>div::after,.timeline>div::before{content:"";display:table}.timeline>div>.timeline-item{box-shadow:0 0 1px rgba(0,0,0,.125),0 1px 3px rgba(0,0,0,.2);border-radius:.25rem;background:#fff;color:#495057;margin-left:60px;margin-right:15px;margin-top:0;padding:0;position:relative}.timeline>div>.timeline-item>.time{color:#999;float:right;font-size:12px;padding:10px}.timeline>div>.timeline-item>.timeline-header{border-bottom:1px solid rgba(0,0,0,.125);color:#495057;font-size:16px;line-height:1.1;margin:0;padding:10px}.timeline>div>.timeline-item>.timeline-header>a{font-weight:600}.timeline>div>.timeline-item>.timeline-body,.timeline>div>.timeline-item>.timeline-footer{padding:10px}.timeline>div>.timeline-item>.timeline-body>img{margin:10px}.timeline>div>.timeline-item>.timeline-body ol,.timeline>div>.timeline-item>.timeline-body ul,.timeline>div>.timeline-item>.timeline-body>dl{margin:0}.timeline>div>.timeline-item>.timeline-footer>a{color:#fff}.timeline>div>.fa,.timeline>div>.fab,.timeline>div>.far,.timeline>div>.fas,.timeline>div>.glyphicon,.timeline>div>.ion{background:#adb5bd;border-radius:50%;font-size:15px;height:30px;left:18px;line-height:30px;position:absolute;text-align:center;top:0;width:30px}.timeline>.time-label>span{border-radius:4px;background-color:#fff;display:inline-block;font-weight:600;padding:5px}.timeline-inverse>div>.timeline-item{box-shadow:none;background:#f8f9fa;border:1px solid #dee2e6}.timeline-inverse>div>.timeline-item>.timeline-header{border-bottom-color:#dee2e6}
.timeline i{color: #fff;}

/* Fix text contrast issues */
.card-body .float-left {
    color: #495057 !important;
    font-weight: 600;
}

.card-body .float-right.text-muted {
    color: #495057 !important;
    font-weight: 400;
}

.card-body .client_info_tags span {
    color: #495057 !important;
    font-weight: 600;
}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="row">
				<div class="col-md-12">
					<!-- Flash Message Start -->
					<div class="server-error">
						@include('../Elements/flash-message')
					</div>
					<!-- Flash Message End -->
				</div>									
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					<!-- Profile Image -->
					<div class="card author-box">
						<div class="card-body">
							<div class="author-box-center">
								<span class="author-avtar" style="background: rgb(68, 182, 174);"><b>{{substr($fetchedData->first_name, 0, 1)}}{{substr($fetchedData->last_name, 0, 1)}}</b></span>
								<div class="clearfix"></div>
								<div class="author-box-name">
									<a href="#">{{$fetchedData->first_name}} {{$fetchedData->last_name}}</a>
									<p class="text-muted text-center">@icon('fa-ticket') LEAD-{{str_pad($fetchedData->id, 3, '0', STR_PAD_LEFT)}}</p>
								</div>
								<div class="author-mail_sms">
								<a href="javascript:;" data-id="{{@$fetchedData->id}}" data-email="{{@$fetchedData->email}}" data-name="{{@$fetchedData->first_name}} {{@$fetchedData->last_name}}" class="clientemail" title="Compose Mail">@icon('fa-envelope')</a>
								<a href="{{route('leads.edit', base64_encode(convert_uuencode(@$fetchedData->id)))}}" title="Edit">@icon('fa-edit')</a>								
							</div>
							</div>
							
							
						</div>
					  <!-- /.card-body -->
					</div>
					<!-- /.card -->

					<!-- About Me Box -->
					<div class="card">
						<div class="card-header">
							<h5 class="">Personal Info</h5>
						</div>
					  <!-- /.card-header -->
						<div class="card-body">
							<div class="row">
							    <div class="col-md-12">
								@if($fetchedData->phone != '')
								<p class="clearfix"> 
    								<span class="float-left">Phone:</span>
    								<span class="float-right text-muted">{{@$fetchedData->country_code}} {{@$fetchedData->phone}}</span>
    							</p>
								@endif
								@if($fetchedData->email != '')
									<p class="clearfix"> 
    								<span class="float-left">Email:</span>
    								<span class="float-right text-muted">{{@$fetchedData->email}}</span>
    							</p>
								@endif
								@if($fetchedData->gender != '')
									<p class="clearfix"> 
    								<span class="float-left">Gender:</span>
    								<span class="float-right text-muted">{{@$fetchedData->gender}}</span>
    							</p>
								@endif
								@if($fetchedData->dob != '')
									<p class="clearfix"> 
    								<span class="float-left">Date of Birth:</span>
    								<span class="float-right text-muted">
    								    <?php
										if($fetchedData->dob != ''){
										    echo $dob = date('d/m/Y', strtotime($fetchedData->dob));
										}
										?>
    								    </span>
    							</p>
								@endif
								@if($fetchedData->marital_status != '')
								<p class="clearfix"> 
    								<span class="float-left">Marital Status:</span>
    								<span class="float-right text-muted">
    								    {{@$fetchedData->marital_status}}</span>
    							</p>
								@endif
								@if($fetchedData->visa_expiry_date != '')
								    <p class="clearfix"> 
								<span class="float-left">Visa Expiry Date:</span>
								<span class="float-right text-muted">
								     <?php
										if($fetchedData->visa_expiry_date != ''){
										    echo date('d/m/Y', strtotime($fetchedData->visa_expiry_date));
										}
										?>
								 </span>
							</p>
								@endif
								</div>
								<?php
									$assignee = \App\Models\Staff::where('id',@$fetchedData->user_id)->first();
								?>
								<div class="col-md-12"> 
									<div class="client_assign client_info_tags"> 
									<span class="">Assignee:</span>
										@if($assignee)
										<div class="client_info">
											<div class="cl_logo">{{substr(@$assignee->first_name, 0, 1)}}</div>
											<div class="cl_name">
												<span class="name">{{@$assignee->first_name}}</span>
												<span class="email">{{@$assignee->email}}</span>
											</div>
										</div>
										@else
											-
										@endif
									</div>
								</div>
							</div>
						</div>
					  <!-- /.card-body -->
					</div>
					<!-- /.card -->
				</div>
				<!-- /.col -->
				<div class="col-md-9"> 
					<div class="card card-danger card-outline">
						<div class="card-header p-2">
							<h5 class="">Followup History</h5>
						</div><!-- /.card-header -->						
						<div class="card-body">
							
							<div class="followup_btn"> 
								<ul class="navbar-nav" style="display: block;">
									<li class="nav-item d-sm-inline-block update_stage">
										<a style="background: #f59a0e;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;" class="nav-link opennotepopup" data-notename="Others" data-notetype="others" href="javascript:;">Add Note</a>
									</li> 
									<li class="nav-item d-sm-inline-block add_note">
										<button type="button" class="btn btn-secondary btn-block" data-container="body" data-role="popover" data-placement="bottom" data-html="true" data-content="<div id=&quot;popover-content&quot;>
							    <div class=&quot;box-header with-border&quot;>
								    <div class=&quot;form-group row&quot; style=&quot;margin-bottom:12px&quot; >
										<label for=&quot;inputSub3&quot; class=&quot;col-sm-3 control-label c6 f13&quot; style=&quot;margin-top:8px&quot;>Subject</label>
										<div class=&quot;col-sm-9&quot;>
										    <input id=&quot;remindersubject&quot; class=&quot;form-control f13&quot; placeholder=&quot;Enter an Subject....&quot; type=&quot;text&quot;>
										</div>
										<div class=&quot;clearfix&quot;></div>
								    </div>

							    </div><div id=&quot;popover-content&quot;>
							    <div class=&quot;box-header with-border&quot;>
								    <div class=&quot;form-group row&quot; style=&quot;margin-bottom:12px&quot; >
										<label for=&quot;inputEmail3&quot; class=&quot;col-sm-3 control-label c6 f13&quot; style=&quot;margin-top:8px&quot;>Note</label>
										<div class=&quot;col-sm-9&quot;>
										    <textarea id=&quot;remindernote&quot; class=&quot;form-control tinymce-editor f13&quot; placeholder=&quot;Enter an note....&quot; type=&quot;text&quot;></textarea>
										</div>
										<div class=&quot;clearfix&quot;></div>
								    </div>

							    </div> 
							    <div class=&quot;box-body&quot; style=&quot;padding-bottom:0&quot;>
								    <div class=&quot;c6 f13 text-bold&quot;>Set Date &amp; Time</div>
									<div class=&quot;row&quot;>
										<div class=&quot;col-sm-5 form-group mt2 f13&quot; id=&quot;timegroup&quot; style=&quot;line-height:28px&quot;>
											<label class=&quot;c6 f12&quot;>Presets</label><br/>
											<a href=&quot;#&quot; title=&quot;&quot; onmouseover=&quot;setTitledate(this)&quot; onClick=&quot;setDateTimeInput(this); return false;&quot; id=&quot;hrs2&quot; data-bs-toggle=&quot;tooltip&quot;>In 2 hours</a><br/>
											<a href=&quot;#&quot; title=&quot;&quot; onmouseover=&quot;setTitledate(this)&quot; onClick=&quot;setDateTimeInput(this);  return false;&quot; id=&quot;hrs4&quot;  data-bs-toggle=&quot;tooltip&quot;>In 4 hours</a><br/>
											<a href=&quot;#&quot; title=&quot;&quot; onmouseover=&quot;setTitledate(this)&quot; onClick=&quot;setDateTimeInput(this);  return false;&quot; id=&quot;tom_mor&quot; data-bs-toggle=&quot;tooltip&quot;>Tomorrow morning</a><br/>
											<a href=&quot;#&quot; title=&quot;&quot; onmouseover=&quot;setTitledate(this)&quot; onClick=&quot;setDateTimeInput(this);  return false;&quot; id=&quot;tom_eve&quot; data-bs-toggle=&quot;tooltip&quot;>Tomorrow evening</a><br/>
											<a href=&quot;#&quot; title=&quot;&quot; onmouseover=&quot;setTitledate(this)&quot; onClick=&quot;setDateTimeInput(this);  return false;&quot; id=&quot;tow_day&quot; data-bs-toggle=&quot;tooltip&quot;>In 2 Days</a><br/>
											<a href=&quot;#&quot; title=&quot;&quot; onmouseover=&quot;setTitledate(this)&quot; onClick=&quot;setDateTimeInput(this);  return false;&quot; id=&quot;in_week&quot;  data-bs-toggle=&quot;tooltip&quot;>In 1 Week</a><br/>
										</div>
										<div class=&quot;col-sm-1 no-margin&quot;></div>
										<div class=&quot;col-sm-6 form-group no-margin&quot;>
											<div class=&quot;&quot;>
												<div id=&quot;embeddingDatePicker&quot; class=&quot;f13&quot;></div>
											</div>
										</div>
								    </div>
							    </div>
							    <div class=&quot;box-footer&quot; style=&quot;padding:10px 0&quot;>
							    <div class=&quot;row&quot;>
							    <div class=&quot;col-sm-4 form-group&quot;>
							    <select class=&quot;form-control selec_reg&quot; id=&quot;rem_cat&quot; name=&quot;rem_cat&quot; onchange=&quot;&quot;>
								    <option value=&quot;1&quot; >Regardless</option>
								    <option value=&quot;2&quot;>If no reply</option>
							    </select>

							    </div> 

							    <div class=&quot;col-sm-5 form-group&quot;>
								    <div class=&quot;input-group date&quot; >
									<input type=&quot;text&quot; class=&quot;form-control f13&quot; placeholder=&quot;yyyy-mm-dd&quot;  onkeyup=&quot;changDatepickerDate(this)&quot; id=&quot;popoverdate&quot; name=&quot;popoverdate&quot;>
								    </div>
							    </div>
							    <div class=&quot;col-sm-3 form-group&quot;>
								    <div class=&quot;input-group time&quot;>
									<input type=&quot;text&quot; class=&quot;form-control f13&quot; placeholder=&quot;hh:mm am&quot; onkeyup=&quot;changDatepickerDate(this)&quot;  id=&quot;popovertime&quot; name=&quot;popovertime&quot;>
									<input id=&quot;leadid&quot;  type=&quot;hidden&quot; value=&quot;{{base64_encode(convert_uuencode(@$fetchedData->id))}}&quot;>
								    </div>
							    </div>
<input type=&quot;hidden&quot; value=&quot;&quot; id=&quot;popoverrealdate&quot; name=&quot;popoverrealdate&quot; />
							    </div>

							    <div class=&quot;row text-center&quot;>
									<div class=&quot;col-md-12 text-center&quot;>
									<button  class=&quot;btn btn-danger&quot; id=&quot;setreminder&quot;>Set Reminder</button>
									</div>

							    </div>


					    </div>" data-original-title="" title=""> Followup</button>
										
									</li>
								@if($fetchedData->converted == 0)
								<li class="nav-item d-sm-inline-block converclient">
								    <form method="POST" action="{{route('leads.convert_single')}}" style="display: inline;">
								        @csrf
								        <input type="hidden" name="lead_id" value="{{base64_encode(convert_uuencode($fetchedData->id))}}">
								        <button type="submit" style="background: #54ca68;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;cursor: pointer;" class="nav-link" onclick="return confirm('Are you sure you want to convert this lead to a client?')">
								            @icon('fa-user') Convert To Client
								        </button>
								    </form>
								    </li>
									    @endif
								</ul> 
							</div>
							<div class="history_timeline">
								<ul class="nav nav-tabs" id="myTab" role="tablist">
									<li class="nav-item "><a class="nav-link active" href="#timeline" data-bs-toggle="tab">History</a></li>
									<li class="nav-item"><a class="nav-link" href="#emails" data-bs-toggle="tab">@icon('fa-inbox') Emails</a></li>
								</ul>
								<div class="tab-content">								
									<div class="active tab-pane" id="timeline">
										<!-- The timeline -->
										<div class="timeline timeline-inverse followuphistory">
											<!-- END timeline item -->
											<div>
												@icon('fa-clock', ['class' => 'bg-gray'])
											</div>
										</div>
									</div>
									<!-- Emails Tab -->
									<div class="tab-pane" id="emails">
										<div style="padding: 20px;">
											@include('crm.emails_lead')
										</div>
									</div>
								</div>
								<!-- /.tab-content -->
							</div>
						</div><!-- /.card-body -->
					</div>
					<!-- /.nav-tabs-custom -->
				</div>
				<!-- /.col -->
			</div>
		</div>
	</section>
</div>

<div id="myAddnotes" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header"> 
			<h4 class="modal-title">Modal Header</h4>
				<button type="button" class="close" data-bs-dismiss="modal">&times;</button>
				
			</div>
			<form action="#" method="POST" name="add-note" autocomplete="off" enctype="multipart/form-data" id="addnoteform">
				@csrf
			<div class="modal-body">
				<div class="customerror"></div> 
				<div class="form-group row">
					<div class="col-sm-12">
						<input id="note_type" name="note_type" type="hidden" value="">
						<input name="lead_id" type="hidden" value="{{base64_encode(convert_uuencode(@$fetchedData->id))}}">
						<textarea id="description" name="description" class="form-control tinymce-editor" placeholder="Add note" style=""></textarea>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="submit" class="btn btn-primary" onClick="customValidate('add-note')">@icon('fa-save') Save</button>
			</div>
			</form>
		</div>
	</div>
</div>
<div id="myeditnotes" class="modal fade" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header"> 
			<h4 class="modal-title">Modal Header</h4>
				<button type="button" class="close" data-bs-dismiss="modal">&times;</button>
				
			</div>
				<div class="modal-body showeditform">
				    <h4>Please Wait...</h4>
			    </div>
		</div>
	</div>
</div>
@endsection
@push('scripts')
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
				<form action="{{ route('clients.sendmail') }}" method="POST" name="add-compose" autocomplete="off" enctype="multipart/form-data" id="addnoteform">
					@csrf
				<input name="type" type="hidden" value="lead">
				<input name="mail_type" type="hidden" value="1">
				<input name="client_id" type="hidden" value="{{ @$fetchedData->id }}">
				<input name="lead_id" type="hidden" value="{{ @$fetchedData->id }}">
				<input name="email_to[]" type="hidden" value="{{ @$fetchedData->id }}">
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_from_lead">From <span class="span_req">*</span></label>
								@include('partials.email-from-sendgrid', ['email_from_id' => 'email_from_lead'])
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="compose_matter_id_lead">Matter type (for checklist sheet)</label>
								<select name="compose_matter_id" id="compose_matter_id_lead" class="form-control">
									<option value="">Select matter type</option>
									@foreach(\App\Models\Matter::where('status',1)->orderBy('title')->get() as $m)
									<option value="{{ $m->id }}">{{ $m->title }} ({{ $m->nick_name ?? '' }})</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To <span class="span_req">*</span></label>
								<input type="email" id="email_to" value="{{ @$fetchedData->email }}" class="form-control" readonly placeholder="">
								
								@if ($errors->has('email_to'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('email_to') }}</strong>
									</span> 
								@endif
							</div>
						</div>
						
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="template">Templates </label>
								<select data-valid="" class="form-control mm-select selecttemplate" name="template">
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
								<input type="text" name="subject" value="" class="form-control selectedsubject" data-valid="required" autocomplete="off" placeholder="Enter Subject">
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
								<textarea class="tinymce-editor selectedmessage" name="message"></textarea>
								@if ($errors->has('message'))
									<span class="custom-error" role="alert">
										<strong>{{ @$errors->first('message') }}</strong>
									</span>  
								@endif
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group">
								<label>Checklist attachments</label>
								<div class="table-responsive"><table class="table table-sm table-bordered">
									<thead><tr><th></th><th>File name</th></tr></thead>
									<tbody>
									@foreach(\App\Models\UploadChecklist::all() as $uclist)
									<tr><td><input type="checkbox" name="checklistfile[]" value="{{ $uclist->id }}"></td><td><a href="{{ url('checklists/'.$uclist->file) }}" target="_blank">{{ $uclist->name }}</a></td></tr>
									@endforeach
									</tbody>
								</table></div>
							</div>
						</div>
						<div class="col-12 col-md-12 col-lg-12">
							<button onclick="customValidate('add-compose')" type="button" class="btn btn-primary">Send</button>
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@include('components.inputmask-scripts')
<script src="{{URL::to('/')}}/public/js/popover.js"></script>  
<script> 

var lead_id = '{{base64_encode(convert_uuencode(@$fetchedData->id))}}';
jQuery(document).ready(function($){
	
	$('.attach_more').on('click', function(){
		var numItems = $('.attfile').length;
		if(numItems <= 4){
		$('.filesdata').append('<div class="form-group row attfile"><div class="col-sm-12"><label for="subject" class="col-form-label col-sm-2">Attachment</label><div class="col-sm-6"><input type="file" name="attachemnt[]" class="form-control"></div><div class="col-sm-4"><a href="javascript:;" class="removeatt">Remove</a></div></div></div>');
		}
		var numItemss = $('.attfile').length;
		if(numItemss <= 4){}
		else{
			$('.attachore').hide();
		}
	});
	$(document).delegate('.removeatt','click', function(){
		$(this).parent().parent().parent().remove();
		var numItems = $('.attfile').length;
		if(numItems <= 4){
			$('.attachore').show();
		}
	});
	$('.composermodel').on('click', function(){
		$('#composermodel').modal('show');
	});
	setInterval(function(){
           myfollowuplist(lead_id);
            },
            1*60*1000);
	myfollowuplist(lead_id);

});
function myfollowuplist(lead_id) {
	$('.followuphistory').html('<div class="alert alert-info">Followup functionality has been removed.</div>');
}
$(function () {
	$(document).delegate('.clientemail', 'click', function(){ 

	$('#emailmodal').modal('show');
	var array = [];
	var data = [];

		
			var id = $(this).attr('data-id');
			 array.push(id);
			var email = $(this).attr('data-email');
			var name = $(this).attr('data-name');
			var status = 'Client';
			
			data.push({
				id: id,
  text: name,
  html:  "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

      "<div  class='ag-flex ag-align-start'>" +
        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +
        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>"+email+"</small ></div>" +
      
      "</div>" +
      "</div>" +
	   "<div class='ag-flex ag-flex-column ag-align-end'>" +
        
        "<span class='ui label yellow mm-result-repository__statistics'>"+ status +
          
        "</span>" +
      "</div>" +
    "</div>",
  title: name
				});
	
	$(".js-data-example-ajax").mmSelect({
  data: data,
  escapeMarkup: function(markup) {
    return markup;
  },
  templateResult: function(data) {
    return data.html;
  },
  templateSelection: function(data) {
    return data.text;
  }
})
	$('.js-data-example-ajax').val(array);
		$('.js-data-example-ajax').trigger('change');
	
});
  $('[data-bs-toggle="tooltip"]').tooltip();
   $('[data-bs-toggle="popover"]').popover();
   
   // Initialize Flatpickr for datepickers
   if (typeof flatpickr !== 'undefined') {
     $('.datepicker').each(function() {
       var $this = $(this);
       if (!$this.data('flatpickr')) {
         flatpickr(this, {
           dateFormat: 'd/m/Y', // DD/MM/YYYY format
           allowInput: true,
           clickOpens: true,
           locale: { firstDayOfWeek: 1 },
           onChange: function(selectedDates, dateStr, instance) {
             $this.val(dateStr);
             $this.trigger('change');
           }
         });
       }
     });
   }
			 
	$('.js-data-example-ajaxccd').mmSelect({
		 multiple: true,
		 closeOnSelect: false,
		dropdownParent: $('body'),
		dropdownCssClass: 'mm-compose-email-recipients-dropdown',
		  ajax: {
			url: '{{URL::to('/clients/get-recipients')}}',
			dataType: 'json',
			data: function (params) {
				return { q: params.term || '' };
			},
			processResults: function (data) {
			  // Transforms the top-level key of the response object from 'items' to 'results'
			  return {
				results: data.items
			  };
			  
			},
			 cache: true
			
		  },
	templateResult: formatRepo,
	templateSelection: formatRepoSelection
});
function formatRepo (repo) {
  if (repo.loading) {
    return repo.text;
  }

  var $container = $(
    "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

      "<div  class='ag-flex ag-align-start'>" +
        "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
        "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small ></div>" +
      
      "</div>" +
      "</div>" +
	   "<div class='ag-flex ag-flex-column ag-align-end'>" +
        
        "<span class='ui label yellow mm-result-repository__statistics'>" +
          
        "</span>" +
      "</div>" +
    "</div>"
  );

  $container.find(".mm-result-repository__title").text(repo.name);
  $container.find(".mm-result-repository__description").text(repo.email);
  $container.find(".mm-result-repository__statistics").append(repo.status);
 
  return $container;
}

function formatRepoSelection (repo) {
  return repo.name || repo.text;
}		

$(document).delegate('.selecttemplate', 'change', function(){
	var v = $(this).val();
	$.ajax({
		url: '{{URL::to('/get-templates')}}',
		type:'GET',
		datatype:'json',
		data:{id:v},
		success: function(response){
			var res = JSON.parse(response);
			$('.selectedsubject').val(res.subject);
			 // Clear and set TinyMCE editor content
                    $("#emailmodal .tinymce-editor").each(function() {
                        var editorId = $(this).attr('id');
                        if (editorId && typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                            tinymce.get(editorId).setContent(res.description || '');
                        } else {
                            $(this).val(res.description || '');
                        }
                    }); 
			
		}
	});
});

$(document).delegate('.editnote', 'click', function(){
	var v = $(this).attr('data-id');
	$('#myeditnotes').modal('show');
	$.ajax({
		url: '{{URL::to('/get-notedetail')}}',
		type:'GET',
		data:{id:v},
		success: function(response){
		    $('.showeditform').html(response);
		 // Initialize TinyMCE editor (replacing Summernote)
            if (typeof tinymce !== 'undefined') {
                $("#myeditnotes .tinymce-editor").each(function() {
                    var editorId = $(this).attr('id') || 'tinymce_' + Math.random().toString(36).substr(2, 9);
                    if (!$(this).attr('id')) {
                        $(this).attr('id', editorId);
                    }
                    if (!tinymce.get(editorId)) {
                        tinymce.init({
                            license_key: 'gpl',
                            selector: '#' + editorId,
                            license_key: 'gpl',
                            height: 150,
                            menubar: false,
                            plugins: ['lists', 'link', 'autolink'],
                            toolbar: 'bold italic underline strikethrough | bullist numlist | link',
                            branding: false,
                            promotion: false,
                            setup: function(editor) {
                                editor.on('change', function() {
                                    editor.save();
                                });
                            }
                        });
                    }
                });
            } 
		}
	});
});


$(document).delegate('.opennotepopup', 'click', function(){
		var notename = $.trim($(this).attr('data-notename'));
			var notetype = $.trim($(this).attr('data-notetype'));
		$('#myAddnotes .modal-title').html(notename);
		$('#myAddnotes #note_type').val(notetype);
		$('#myAddnotes').modal('show');
	});
$(document).delegate('#setreminder','click', function(){
		$(".popuploader").show(); 
		var flag = true;
		$(".custom-error").remove();
		if($('#popoverdate').val() == ''){
			$('#popoverdate').parent().after("<span class='custom-error' role='alert'>"+error+"</span>");
			flag = false;
		}
		if(flag){
			$.ajax({
				type:'post',
					url:"#",
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
					
					data: {note_type:'follow_up',description:$('#remindernote').val(),remindersubject:$('#remindersubject').val(),lead_id:$('#leadid').val(),followup_date:$('#popoverdate').val(),followup_time:$('#popovertime').val(),rem_cat:$('#rem_cat option:selected').val()},
					success: function(response){
						$('.popuploader').hide(); 
						var obj = $.parseJSON(response);
						if(obj.success){
							$("[data-role=popover]").each(function(){
           
                (($(this).popover('hide').data('bs.popover')||{}).inState||{}).click = false  // fix for BS 3.3.6
            
        });
							myfollowuplist(obj.leadid);
						}else{
							
							
						}
					}
			});
		}else{
			$("#loader").hide();
		}
	});
});
</script>
@endpush