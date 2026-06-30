@extends('layouts.crm_client_detail')
@section('title', 'Lead Details')

@section('content')
{{-- Bootstrap-datepicker removed - already loaded in layout, migrating to Flatpickr --}}
<style>
.popover {max-width:700px;}
.timeline{margin:0 0 45px;padding:0;position:relative}.timeline::before{border-radius:.25rem;background:#dee2e6;bottom:0;content:'';left:31px;margin:0;position:absolute;top:0;width:4px}.timeline>div{margin-bottom:15px;margin-right:10px;position:relative}.timeline>div::after,.timeline>div::before{content:"";display:table}.timeline>div>.timeline-item{box-shadow:0 0 1px rgba(0,0,0,.125),0 1px 3px rgba(0,0,0,.2);border-radius:.25rem;background:#fff;color:#495057;margin-left:60px;margin-right:15px;margin-top:0;padding:0;position:relative}.timeline>div>.timeline-item>.time{color:#999;float:right;font-size:12px;padding:10px}.timeline>div>.timeline-item>.timeline-header{border-bottom:1px solid rgba(0,0,0,.125);color:#495057;font-size:16px;line-height:1.1;margin:0;padding:10px}.timeline>div>.timeline-item>.timeline-header>a{font-weight:600}.timeline>div>.timeline-item>.timeline-body,.timeline>div>.timeline-item>.timeline-footer{padding:10px}.timeline>div>.timeline-item>.timeline-body>img{margin:10px}.timeline>div>.timeline-item>.timeline-body ol,.timeline>div>.timeline-item>.timeline-body>ul,.timeline>div>.timeline-item>.timeline-body>dl{margin:0}.timeline>div>.timeline-item>.timeline-footer>a{color:#fff}.timeline>div>.fa,.timeline>div>.fab,.timeline>div>.far,.timeline>div>.fas,.timeline>div>.glyphicon,.timeline>div>.ion{background:#adb5bd;border-radius:50%;font-size:15px;height:30px;left:18px;line-height:30px;position:absolute;text-align:center;top:0;width:30px}.timeline>.time-label>span{border-radius:4px;background-color:#fff;display:inline-block;font-weight:600;padding:5px}.timeline-inverse>div>.timeline-item{box-shadow:none;background:#f8f9fa;border:1px solid #dee2e6}.timeline-inverse>div>.timeline-item>.timeline-header{border-bottom-color:#dee2e6}
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

/* Table header styling for better contrast */
.table th {
    background-color: #f8f9fa !important;
    color: #495057 !important;
    font-weight: 600;
    border-color: #dee2e6;
}

.table td {
    background-color: #ffffff;
    color: #495057;
    border-color: #dee2e6;
}

.table tbody tr:nth-child(even) td {
    background-color: #f8f9fa;
}

/* Personal Info section styling */
.card-header h5 {
    color: #495057 !important;
    font-weight: 600;
}

/* Author box styling */
.author-box-name a {
    color: #495057 !important;
    font-weight: 600;
}

.author-box-name .text-muted {
    color: #6c757d !important;
}

/* Lead Overview section */
.card-header h5 {
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
								@if(false && $fetchedData->lead_quality != '')
								    <p class="clearfix"> 
								<span class="float-left">Lead Quality:</span>
								<span class="float-right text-muted">
								    <div class="lead_stars">@icon('fa-star')<span>{{@$fetchedData->lead_quality}}</span></div>
								 </span>
							</p>
								@endif
								@php
									$dStage = $fetchedData->lead_status ?: 'new';
									$dLabels = ['new' => 'New', 'follow_up' => 'Follow up', 'not_qualified' => 'Not qualified', 'hostile' => 'Hostile'];
									$dStageLabel = $dLabels[$dStage] ?? ucfirst(str_replace('_', ' ', $dStage));
								@endphp
								<p class="clearfix">
									<span class="float-left">Lead stage:</span>
									<span class="float-right text-muted">{{ $dStageLabel }}</span>
								</p>
								@if($fetchedData->lead_status === 'follow_up' && $fetchedData->followup_date)
								<p class="clearfix">
									<span class="float-left">Follow-up date:</span>
									<span class="float-right text-muted">
										{{ $fetchedData->followup_date instanceof \Carbon\Carbon ? $fetchedData->followup_date->format('d/m/Y') : date('d/m/Y', strtotime($fetchedData->followup_date)) }}
									</span>
								</p>
								@endif
								<p class="clearfix">
									<span class="float-left">Record:</span>
									<span class="float-right text-muted">{{ (int) $fetchedData->status === 1 ? 'Active' : 'Inactive' }}</span>
								</p>
								</div>
								<div class="col-md-12"> 
									<div class="client_assign client_info_tags"> 
									<span class="">Assignee:</span>
										@if($fetchedData->assignedTo)
										<div class="client_info">
											<div class="cl_logo">{{ substr($fetchedData->assignedTo->first_name, 0, 1) }}</div>
											<div class="cl_name">
												<span class="name">{{ $fetchedData->assignedTo->first_name }} {{ $fetchedData->assignedTo->last_name }}</span>
												<span class="email">{{ $fetchedData->assignedTo->email }}</span>
											</div>
										</div>
										@else
											<span class="text-muted">—</span>
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
							<h5 class="">Lead Overview</h5>
						</div><!-- /.card-header -->						
						<div class="card-body">
							
							<div class="followup_btn"> 
								<ul class="navbar-nav" style="display: block;">
									<li class="nav-item d-sm-inline-block">
										<a style="background: #6777ef;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;" class="nav-link" href="{{route('leads.edit', base64_encode(convert_uuencode(@$fetchedData->id)))}}">
										  @icon('fa-edit') Edit Lead
										</a>
									</li>
									<li class="nav-item d-sm-inline-block">
										<a style="background: #f59a0e;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;" class="nav-link" href="{{route('leads.history', base64_encode(convert_uuencode(@$fetchedData->id)))}}">
										  @icon('fa-history') View History
										</a>
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
									@else
									<li class="nav-item d-sm-inline-block">
									    <span style="background: #95a5a6;border-radius: 4px;padding: 7px 10px;font-size: 14px;line-height: 18px;color: #fff;border: 0px;" class="nav-link">
										  @icon('fa-check') Converted to Client
										</span>
									    </li>
									    @endif
								</ul> 
							</div>
							<div class="history_timeline">
								<ul class="nav nav-tabs" id="myTab" role="tablist">
									<li class="nav-item"><a class="nav-link active" href="#overview" data-bs-toggle="tab">Overview</a></li>
									<li class="nav-item"><a class="nav-link" href="#emails" data-bs-toggle="tab">@icon('fa-inbox') Emails</a></li>
									<li class="nav-item"><a class="nav-link" href="#notes" data-bs-toggle="tab">Notes</a></li>
								</ul>
								<div class="tab-content">								
									<!-- Overview Tab -->
									<div class="active tab-pane" id="overview">
										<div style="padding: 20px;">
											<h4>Lead Information</h4>
											<table class="table table-bordered">
												<tbody>
													<tr>
														<th style="width: 200px;">Lead ID</th>
														<td>{{$fetchedData->client_id}}</td>
													</tr>
													<tr>
														<th>Full Name</th>
														<td>{{$fetchedData->first_name}} {{$fetchedData->last_name}}</td>
													</tr>
													<tr>
														<th>Email</th>
														<td>{{$fetchedData->email}}</td>
													</tr>
													<tr>
														<th>Phone</th>
														<td>{{$fetchedData->country_code}} {{$fetchedData->phone}}</td>
													</tr>
													@if(false && $fetchedData->service != '')
													<tr>
														<th>Service Interested</th>
														<td>{{$fetchedData->service}}</td>
													</tr>
													@endif
													@if($fetchedData->lead_source != '')
													<tr>
														<th>Lead Source</th>
														<td>{{$fetchedData->lead_source}}</td>
													</tr>
													@endif
													<tr>
														<th>Created Date</th>
														<td>{{date('d/m/Y h:i:s a', strtotime($fetchedData->created_at))}}</td>
													</tr>
													<tr>
														<th>Last Updated</th>
														<td>{{date('d/m/Y h:i:s a', strtotime($fetchedData->updated_at))}}</td>
													</tr>
												</tbody>
											</table>
											
										</div>
									</div>
									
									<!-- Emails Tab -->
									<div class="tab-pane" id="emails">
										<div style="padding: 20px;">
											@include('crm.emails_lead')
										</div>
									</div>
									
									<!-- Notes Tab -->
									<div class="tab-pane" id="notes">
										<div style="padding: 20px;">
											<p>Notes functionality can be added here in future updates.</p>
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
								<small class="text-muted">Required when attaching checklist — determines which sheet (TR, Visitor, etc.) the lead appears in for follow-up.</small>
							</div>
						</div>
						<div class="col-12 col-md-6 col-lg-6">
							<div class="form-group">
								<label for="email_to">To</label>
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
<script>
jQuery(document).ready(function($){
	$(document).delegate('.clientemail', 'click', function(){ 

	$('#emailmodal').modal('show');
	var array = [];
	var data = [];

		
			var id = $(this).attr('data-id');
			 array.push(id);
			var email = $(this).attr('data-email');
			var name = $(this).attr('data-name');
			var status = 'Lead';
			
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

});
</script>
@endpush

