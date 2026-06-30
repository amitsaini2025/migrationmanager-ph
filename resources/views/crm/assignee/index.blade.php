@extends('layouts.crm_client_detail')
@section('title', 'assignees')

@section('content')
<style>
.fc-event-container .fc-h-event{cursor:pointer;}
#openassigneview .modal-body ul.navbar-nav li .dropdown-menu{transform: none!important; top:40px!important;}
.sort_col a { color: #212529 !important; font-weight: 700 !important;}
</style>
<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="card-header">
							<h4>Assignee's</h4>
							<div class="card-header-action">
								<!-- <a href="{{URL::to('admin/quotations/template/create')}}"  class="btn btn-primary is_checked_clientn">Create Template</a> -->
							</div>

                            <ul class="nav nav-pills" id="client_tabs" role="tablist">
                                <li class="nav-item is_checked_clientn">
									<a class="nav-link active" id="clients-tab"  href="{{URL::to('/assignee')}}">Incomplete</a>
								</li>
								<li class="nav-item is_checked_clientn11">
									<a class="nav-link" id="archived-tab"  href="{{URL::to('/assignee-completed')}}">Completed</a>
								</li>


                                <!--<li class="nav-item is_checked_clientn12">
									<a class="nav-link" id="assigned_by_me"  href="{{URL::to('/assigned_by_me')}}">Assigned by me</a>
								</li>

								<li class="nav-item is_checked_clientn13">
									<a class="nav-link" id="assigned_to_me"  href="{{URL::to('/assigned_to_me')}}">Assigned to me</a>
								</li>-->
							</ul>
						</div>
						<div class="card-body">
							<div class="tab-content" id="quotationContent">
                                <!--<div class="filter_panel">
                                    <form action="{{-- route('assignee.index') --}}" method="get">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
												{{-- <input type="text" name="name" value="{{ Request::get('name') }}" class="form-control agent_company_name" data-valid="" autocomplete="off" placeholder="Assignee name" id="name"> --}}
                                                </div>
                                            </div>
                                            <div class="col-md-3">
												{{-- <button type="submit" class="btn btn-primary btn-theme-lg">Search</button> --}}
                                            </div>
                                        </div>
                                    </form>
                                </div>-->

							<form action="{{ route('assignee.index') }}" method="get">
								<div class="row">
									<div class="col-md-3">
										<!-- <select  class="form-control mb-3" name="filter">
										<option>All assignees</option>
										<option value="today">Today</option>
										<option value="last week">Last Week</option>
										<option value="previous month">Previous Month</option>
										<option value="last 6 month">Last 6 Months</option>
										<option value="last year">Last Year</option>
										</select> -->
									</div>
									{{-- <div class="col-md-3">
									</div>
									<div class="col-md-4">
										<input type="text" class="form-control mb-3 ml-4" placeholder="Searching...." name="q">
									</div>
									<div class="col-md-2">
										<input type="submit" class="form-control mb-3 btn btn-primary" value="Search">
									</div> --}}
								</div>
							</form>
								<div class="tab-pane fade show active" id="active_quotation" role="tabpanel" aria-labelledby="active_quotation-tab">
									<div class="table-responsive common_table">
									<!-- @if ($message = Session::get('success'))
										<div class="alert alert-success">
											<p>{{ $message }}</p>
										</div>
									@endif   -->
									<table class="table table-bordered">
										<tr>
											<th>#</th>
											<th class="sort_col">@sortablelink('first_name','Assignee name')</th>
                                            <th>Assigner name</th>
											<th>Client Reference</th>
											<th class="sort_col">@sortablelink('action_date','Action Date')</th>
                                            <th class="sort_col">@sortablelink('task_group','Group')</th>
                                            <th>Note</th>
                                            <th width="280px">Action</th>

                                            <!--<th>Title</th>-->
											{{-- <th>Nature of enquiry</th> --}}
											<!--<th>Service</th>-->
                                            <!--<th>status</th>-->
                                        </tr>
                                        <?php //echo "<pre>assignees==";print_r($assignees);die; ?>
										@foreach ($assignees as $list)
                                        <?php //echo "<pre>list==";print_r($list);?>
										<tr>
                                            <?php
												if($list->noteClient){
													$client_name = trim($list->noteClient->company_name_or_personal_name);
													if ($client_name === '') {
														$client_name = trim($list->noteClient->first_name.' '.$list->noteClient->last_name);
													}
												}else{
													$client_name='N/P';
												}
											?>
											<td>{{ ++$i }}</td>
											<td>{{ $list->assigned_staff->first_name ?? ''}}  {{$list->assigned_staff->last_name ?? ''}}</td>
											<td>{{ $client_name??'N/P' }}</td>
                                            <td><a href="{{URL::to('/clients/detail/'.base64_encode(convert_uuencode(@$list->client_id)))}}" target="_blank" >{{ $list->noteClient->client_id ?? 'N/P' }}</a></td>
											<td>{{ date('d/m/Y',strtotime($list->action_date)) ?? 'N/P'}} </td>
                                            <td>{{ $list->task_group??'N/P' }}</td>
                                            <td>{{ $list->description??'N/P' }}</td>

                                            <!--
											<td>{{-- $list->title??'N/P' --}}</td>
											<td>{{-- $list->noteClient->service??'N/P' --}}</td>-->

											<!--@if($list->noteClient->status === 0)
											<td><span title="draft" class="ui label uppercase badge bg-warning">Pending</span></td>
											@elseif($list->noteClient->status === 1)
											<td><span title="draft" class="ui label uppercase badge bg-success">Approved</span></td>
											@elseif($list->noteClient->status === 'Unassigned')
											<td><span title="draft" class="ui label uppercase badge bg-warning">Unassigned</span></td>
											@elseif($list->noteClient->status === 'Assigned')
											<td><span title="draft" class="ui label uppercase badge bg-info">Assigned</span></td>
											@elseif($list->noteClient->status === 'In-Progress')

											<td><span title="draft" class="ui label uppercase badge bg-primary">In-Progress</span></td>
											@elseif($list->noteClient->status === 'Closed')
											<td><span title="draft" class="ui label uppercase badge bg-success">Closed</span></td>
											@else
											<td><span title="draft" class="ui label uppercase badge bg-warning">Pending</span></td>
											@endif-->


											<td>
												@if($list->noteClient)
												<form action="{{ route('assignee.destroy',$list->id) }}" method="POST">

													{{-- <a class="btn btn-info" href="{{ route('assignees.show',$list->id) }}">Show</a> --}}

													{{--<a class="btn btn-primary" href="{{ url('/clients/edit/'.base64_encode(convert_uuencode(@$list->client_id)).'') }}">Edit</a>--}}
                                                    <a class="btn btn-primary complete_task" data-id="{{ $list->id }}" href="javascript:void(0)">Complete</a>

													@csrf
													@method('DELETE')

													<button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure want to delete?');">Delete</button>
													<button type="button" class="btn btn-primary btn-block" data-container="body" data-role="popover" data-placement="bottom" data-html="true" data-content="<div id=&quot;popover-content&quot;>
														<h4 class=&quot;text-center&quot;>Re-Assign Staff</h4>
														<div class=&quot;clearfix&quot;></div>
													<div class=&quot;box-header with-border&quot;>
														<div class=&quot;form-group row&quot; style=&quot;margin-bottom:12px&quot; >
															<label for=&quot;inputSub3&quot; class=&quot;col-sm-3 control-label c6 f13&quot; style=&quot;margin-top:8px&quot;>Select Assignee</label>
															<div class=&quot;col-sm-9&quot;>
																<select class=&quot;assignee-mm-select form-control selec_reg&quot; id=&quot;rem_cat&quot; name=&quot;rem_cat&quot; onchange=&quot;&quot;>
																	<option value=&quot;&quot; >Select</option>
																	@foreach(\App\Models\Staff::orderby('first_name','ASC')->get() as $admin)
																	<?php
																	$branchname = \App\Models\Branch::where('id',$admin->office_id)->first();
																	?>
																	<option value=&quot;<?php echo $admin->id; ?>&quot;><?php echo $admin->first_name.' '.$admin->last_name.' ('.@$branchname->office_name.')'; ?></option>
																	@endforeach
																</select>
															</div>
															<div class=&quot;clearfix&quot;></div>
														</div>
													</div><div id=&quot;popover-content&quot;>
													<div class=&quot;box-header with-border&quot;>
														<div class=&quot;form-group row&quot; style=&quot;margin-bottom:12px&quot; >
															<label for=&quot;inputEmail3&quot; class=&quot;col-sm-3 control-label c6 f13&quot; style=&quot;margin-top:8px&quot;>Note</label>
															<div class=&quot;col-sm-9&quot;>
																<textarea id=&quot;assignnote&quot; class=&quot;form-control tinymce-editor f13&quot; placeholder=&quot;Enter an note....&quot; type=&quot;text&quot;></textarea>
															</div>
															<div class=&quot;clearfix&quot;></div>
														</div>
													</div>
													<div class=&quot;box-header with-border&quot;>
														<div class=&quot;form-group row&quot; style=&quot;margin-bottom:12px&quot; >
															<label for=&quot;inputEmail3&quot; class=&quot;col-sm-3 control-label c6 f13&quot; style=&quot;margin-top:8px&quot;>Date</label>
															<div class=&quot;col-sm-9&quot;>
																<input type=&quot;datetime-local&quot; class=&quot;form-control f13&quot; placeholder=&quot;yyyy-mm-dd,h:i:s&quot; id=&quot;popoverdatetime&quot; value=&quot;<?php echo date('Y-m-d h:i:s');?>&quot;name=&quot;popoverdate&quot;>
															</div>
															<div class=&quot;clearfix&quot;></div>
														</div>
													</div>

                                                    <div class=&quot;form-group row&quot; style=&quot;margin-bottom:12px&quot; >
                                                        <label for=&quot;inputSub3&quot; class=&quot;col-sm-3 control-label c6 f13&quot; style=&quot;margin-top:8px&quot;>Group</label>
                                                        <div class=&quot;col-sm-9&quot;>
                                                            <select class=&quot;assignee-mm-select form-control selec_reg&quot; id=&quot;task_group&quot; name=&quot;task_group&quot;>
                                                                <option value=&quot;&quot;>Select</option>
                                                                <option value=&quot;Call&quot;>Call</option>
                                                                <option value=&quot;Checklist&quot;>Checklist</option>
                                                                <option value=&quot;Review&quot;>Review</option>
                                                                <option value=&quot;Query&quot;>Query</option>
                                                                <option value=&quot;Urgent&quot;>Urgent</option>
                                                            </select>
                                                        </div>
                                                        <div class=&quot;clearfix&quot;></div>
                                                    </div>

													<input id=&quot;assign_client_id&quot;  type=&quot;hidden&quot; value=&quot;{{base64_encode(convert_uuencode(@$list->client_id))}}&quot;>
													<div class=&quot;box-footer&quot; style=&quot;padding:10px 0&quot;>
													<div class=&quot;row&quot;>
														<input type=&quot;hidden&quot; value=&quot;&quot; id=&quot;popoverrealdate&quot; name=&quot;popoverrealdate&quot; />
													</div>
													<div class=&quot;row text-center&quot;>
														<div class=&quot;col-md-12 text-center&quot;>
														<button  class=&quot;btn btn-info&quot; id=&quot;assignStaff&quot;>Assign Staff</button>
														</div>
													</div>
											</div>" data-original-title="" title="" style="width: 85px;display: inline;">Reassign</button>
									{{-- <a class="btn btn-primary openassigneview" id="{{$list->id}}" href="#">Reassign</a> --}}
												</form>
												@endif
											</td>


										</tr>
										@endforeach
									</table>
										{{-- {!! $assignees->appends(\Request::except('page'))->render() !!} --}}
   										 {!! $assignees->appends($_GET)->links() !!}
								</div>
								<div class="card-footer">

								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</div>
<!-- Assign Modal -->

<div class="modal fade custom_modal" id="openassigneview" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content taskview">

		</div>
	</div>
</div>

<!-- Task Completion Notes Modal -->
<div class="modal fade" id="completionNotesModal" tabindex="-1" role="dialog" aria-labelledby="completionNotesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #3498db; color: white;">
                <h5 class="modal-title" id="completionNotesModalLabel">
                    @icon('fa-check-circle') Complete Task
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.8;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="completionNotes" class="font-weight-bold">
                        @icon('fa-comment') Completion Notes/Feedback
                    </label>
                    <textarea 
                        class="form-control" 
                        id="completionNotes" 
                        rows="5" 
                        placeholder="Enter any notes or feedback about completing this task..."
                        style="resize: vertical; border: 2px solid #e9ecef; border-radius: 8px; padding: 12px;"
                    ></textarea>
                    <small class="form-text text-muted">
                        @icon('fa-info-circle') These notes will be saved in the activity log.
                    </small>
                </div>
            </div>
            <div class="modal-footer" style="background-color: #f8f9fa;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    @icon('fa-times') Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmTaskCompletion">
                    @icon('fa-check') Complete Task
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
@include('components.inputmask-scripts')
<script src="{{URL::to('/')}}/js/popover.js"></script>
<script>
	jQuery(document).ready(function($){
     $(document).delegate('.openassignee', 'click', function(){
        $('.assignee').show();
    });
	$(document).delegate('.closeassignee', 'click', function(){
        $('.assignee').hide();
    });

    //Function is used for complete the task - open modal
    var currentTaskId = null;
    var currentTaskGroupId = null;
    
	$(document).delegate('.complete_task', 'click', function(){
		var row_id = $(this).attr('data-id');
        var row_unique_group_id = $(this).attr('data-unique_group_id') || '';
        
        if(row_id !=""){ 
            // Store task IDs for later use
            currentTaskId = row_id;
            currentTaskGroupId = row_unique_group_id;
            
            // Clear previous notes
            $('#completionNotes').val('');
            
            // Show the completion notes modal
            $('#completionNotesModal').modal('show');
        }
	});
    
    // Handle task completion with notes
    $(document).on('click', '#confirmTaskCompletion', function() {
        var completionNotes = $('#completionNotes').val().trim();
        
        if (!currentTaskId) {
            console.error('No task ID found');
            return;
        }
        
        // Disable button to prevent double submission
        var $button = $(this);
        $button.prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-spinner fa-spin') : '<i class="fa fa-spinner fa-spin"></i>') + ' Completing...');
        
        $.ajax({
            type: 'post',
            url: "{{URL::to('/')}}/update-action-completed",
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {
                id: currentTaskId, 
                unique_group_id: currentTaskGroupId,
                completion_notes: completionNotes
            },
            success: function(response) {
                // Close modal
                $('#completionNotesModal').modal('hide');
                
                // Reset button
                $button.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Complete Task');
                
                // Clear stored IDs
                currentTaskId = null;
                currentTaskGroupId = null;
                
                // Reload page
                location.reload();
            },
            error: function(xhr) {
                console.error('Error completing task:', xhr.responseText);
                alert('An error occurred while completing the task.');
                
                // Reset button
                $button.prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fa fa-check') : '<i class="fa fa-check"></i>') + ' Complete Task');
            }
        });
    });

    $(document).delegate('#assignStaff','click', function(){
		$(".popuploader").show();
		var flag = true;
		var error ="";
		$(".custom-error").remove();
		// if($('#lead_id').val() == ''){
		// 	$('.popuploader').hide();
		// 	error="Lead field is required.";
		// 	$('#lead_id').after("<span class='custom-error' role='alert'>"+error+"</span>");
		// 	flag = false;
		// }
		if($('#rem_cat').val() == ''){
			$('.popuploader').hide();
			error="Assignee field is required.";
			$('#rem_cat').after("<span class='custom-error' role='alert'>"+error+"</span>");
			flag = false;
		}
		if($('#assignnote').val() == ''){
			$('.popuploader').hide();
			error="Note field is required.";
			$('#assignnote').after("<span class='custom-error' role='alert'>"+error+"</span>");
			flag = false;
		}
        if($('#task_group').val() == ''){
			$('.popuploader').hide();
			error="Group field is required.";
			$('#task_group').after("<span class='custom-error' role='alert'>"+error+"</span>");
			flag = false;
		}
		if(flag){
			$.ajax({
				type:'post',
					url:"{{URL::to('/')}}/clients/action/store",
					headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},

					data: {note_type:'follow_up',description:$('#assignnote').val(),client_id:$('#assign_client_id').val(),followup_datetime:$('#popoverdatetime').val(),assignee_name:$('#rem_cat :selected').text(),rem_cat:$('#rem_cat option:selected').val(),task_group:$('#task_group option:selected').val()},
					success: function(response){
						console.log(response);
						$('.popuploader').hide();
						var obj = $.parseJSON(response);
						if(obj.success){
							$("[data-role=popover]").each(function(){
									(($(this).popover('hide').data('bs.popover')||{}).inState||{}).click = false  // fix for BS 3.3.6
							});
							location.reload();

						}else{
							alert(obj.message);
							location.reload();

						}
					}
			});
		}else{
			$("#loader").hide();
		}
	});

	// REMOVED: Deprecated appointment system functionality
	// These endpoints were removed: /change_assignee, /update_apppointment_comment, /get-assigne-detail
	// $(document).delegate('.saveassignee', 'click', function(){ ... });
	// $(document).delegate('.savecomment', 'click', function(){ ... });
	// $(document).delegate('.openassigneview', 'click', function(){ ... });

	$(document).delegate('.changestatus', 'click', function(){
		var appliid = $(this).attr('data-id');
		var status = $(this).attr('data-status');
		var statusame = $(this).attr('data-status-name');
		$('.popuploader').show();

		$.ajax({
			url: site_url+'/update_list_status',
			type:'POST',
			data:{"_token":$('meta[name="csrf-token"]').attr('content'),id: appliid,statusname:statusame,status:status},
			success: function(responses){
				$('.popuploader').hide();
				var obj = JSON.parse(responses);
				if(obj.status){
				    console.log(obj.status);
				    $('.updatestatusview'+appliid).html(obj.viewstatus);
				}
				// REMOVED: Deprecated endpoint /get-assigne-detail
			}
		});
	});


	$(document).delegate('.changepriority', 'click', function(){
		var appliid = $(this).attr('data-id');
		var status = $(this).attr('data-status');
		$('.popuploader').show();

		$.ajax({
			url: site_url+'/update_list_priority',
			type:'POST',
			data:{"_token":$('meta[name="csrf-token"]').attr('content'),id: appliid,status:status},
			success: function(responses){
				$('.popuploader').hide();

				// REMOVED: Deprecated endpoint /get-assigne-detail
				$('.popuploader').hide();
			}
		});
	});

	$(document).delegate('.desc_click', 'click', function(){
		$(this).hide();
		$('.taskdesc').show();
		$('.taskdesc').focus();
	});
	$(document).delegate('.taskdesc', 'blur', function(){
		$(this).hide();
		$('.desc_click').show();
	});

	// REMOVED: Deprecated appointment system functionality
	// These endpoints were removed: /update_apppointment_description, /get-assigne-detail
	// $(document).delegate('.tasknewdesc', 'blur', function(){ ... });
	// $(document).delegate('.taskdesc', 'blur', function(){ ... });
});
</script>
@endpush
