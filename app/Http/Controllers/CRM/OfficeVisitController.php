<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\CheckinLog;
use App\Models\CheckinHistory;
use App\Events\OfficeVisitNotificationCreated;

use Auth;

class OfficeVisitController extends Controller
{
    use EnsuresCrmRecordAccess;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }
	/**
     * All Vendors.
     *
     * @return \Illuminate\Http\Response
     */

	public function checkin(Request $request){
		try {
			// Handle enhanced multi-select values - get first value if array
			$contactValue = $request->input('contact');
			if (is_array($contactValue)) {
				$contactValue = !empty($contactValue) ? $contactValue[0] : null;
			}
			
			// Validate required fields - use custom validation for contact since it might be array
			$rules = [
				'assignee' => 'required|integer',
				'message' => 'required|string',
				'office' => 'required|integer',
				'utype' => 'required|string',
			];
			
			$messages = [
				'assignee.required' => 'Please select an assignee.',
				'assignee.integer' => 'Invalid assignee selected.',
				'message.required' => 'Visit purpose is required.',
				'office.required' => 'Please select an office.',
				'office.integer' => 'Invalid office selected.',
				'utype.required' => 'Contact type is required. Please select a contact.',
			];
			
			// Validate contact separately
			if (empty($contactValue)) {
				return redirect()->back()
					->withErrors(['contact' => 'Please select a contact.'])
					->withInput();
			}
			
			$contactId = (int) $contactValue;
			if ($contactId <= 0) {
				return redirect()->back()
					->withErrors(['contact' => 'Please select a valid contact.'])
					->withInput();
			}
			
			// Validate other fields
			$validated = $request->validate($rules, $messages);

			// Get validated data with null coalescing for safety
			$assigneeId = (int) $validated['assignee'];
			$officeId = (int) $validated['office'];
			$visitPurpose = trim($validated['message'] ?? '');
			
			// Normalize contact type (handle both lowercase and capitalized)
			$utypeRaw = strtolower(trim($validated['utype'] ?? ''));
			if ($utypeRaw === 'lead') {
				$contactType = 'Lead';
			} elseif ($utypeRaw === 'client') {
				$contactType = 'Client';
			} else {
				// If utype is something unexpected, try to infer from contact
				// Default to Client if we can't determine
				$contactType = 'Client';
			}

			// Verify contact exists based on type
			if ($contactType == 'Lead') {
				$clientExists = \App\Models\Lead::where('id', $contactId)->exists();
			} else {
				$clientExists = Admin::whereIn('type', ['client', 'lead'])->where('id', $contactId)->exists();
			}

			if (!$clientExists) {
				return redirect()->back()->with('error', 'Selected contact does not exist.');
			}

			$this->ensureCrmRecordAccess($contactId);

			// Verify assignee exists (staff table)
			$assigneeExists = \App\Models\Staff::where('id', $assigneeId)->exists();
			if (!$assigneeExists) {
				return redirect()->back()->with('error', 'Selected assignee does not exist.');
			}

			// Verify office exists
			$officeExists = \App\Models\Branch::where('id', $officeId)->exists();
			if (!$officeExists) {
				return redirect()->back()->with('error', 'Selected office does not exist.');
			}

			// Wrap all database operations in a transaction
			DB::beginTransaction();

			try {
				// Create CheckinLog
				$obj = new \App\Models\CheckinLog;
				$obj->client_id = $contactId;
				$obj->user_id = $assigneeId;
				$obj->visit_purpose = $visitPurpose;
				$obj->office = $officeId;
				$obj->contact_type = $contactType;
				$obj->status = 0;
				$obj->date = date('Y-m-d');
				
				if (!$obj->save()) {
					throw new \Exception('Failed to save check-in log.');
				}

			// Create Notification
			$notification = new \App\Models\Notification;
			$notification->sender_id = Auth::user()->id;
			$notification->receiver_id = $assigneeId;
			$notification->module_id = $obj->id;
			$notification->url = \URL::to('/office-visits/waiting');
			$notification->notification_type = 'officevisit';
			$notification->message = 'Office visit Assigned by ' . Auth::user()->first_name . ' ' . Auth::user()->last_name;
			$notification->seen = 0;              // Mark as unseen
			$notification->receiver_status = 0;   // Mark as unread by receiver
			$notification->sender_status = 1;     // Mark as sent by sender
			
			if (!$notification->save()) {
				throw new \Exception('Failed to save notification.');
			}

				// Broadcast real-time notification via Reverb (wrap in try-catch to prevent failures)
				try {
					broadcast(new OfficeVisitNotificationCreated(
						$notification->id,
						$notification->receiver_id,
						[
							'id' => $notification->id,
							'checkin_id' => $obj->id,
							'message' => $notification->message,
							'sender_name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
							'client_name' => $obj->contactDisplayLabel(),
							'visit_purpose' => $obj->visit_purpose,
							'created_at' => $notification->created_at ? $notification->created_at->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A'),
							'url' => $notification->url
						]
					));
				} catch (\Exception $broadcastException) {
					// Log broadcast error but don't fail the entire operation
					Log::warning('Failed to broadcast office visit notification', [
						'notification_id' => $notification->id,
						'error' => $broadcastException->getMessage()
					]);
				}

				// Create CheckinHistory
				$checkinHistory = new CheckinHistory;
				$checkinHistory->subject = 'has created check-in';
				$checkinHistory->created_by = Auth::user()->id;
				$checkinHistory->checkin_id = $obj->id;
				
				if (!$checkinHistory->save()) {
					throw new \Exception('Failed to save check-in history.');
				}

				if ($contactType === 'Client' || $contactType === 'Lead') {
					ActivitiesLog::create([
						'client_id' => $contactId,
						'created_by' => Auth::user()->id,
						'subject' => 'Office visit check-in created',
						'description' => 'Check-in created for office visit: ' . $visitPurpose,
						'activity_type' => 'office_visit_checkin',
						'task_status' => 0,
						'pin' => 0,
					]);
				}

				// Commit transaction
				DB::commit();

				return redirect()->back()->with('success', 'Checkin updated successfully');

			} catch (\Exception $e) {
				// Rollback transaction on any error
				DB::rollBack();
				
				// Log the error for debugging
				Log::error('Checkin creation failed', [
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
					'request_data' => $request->except(['_token'])
				]);

				return redirect()->back()->with('error', 'Failed to create check-in. Please try again.');
			}

		} catch (\Illuminate\Validation\ValidationException $e) {
			// Return validation errors
			return redirect()->back()
				->withErrors($e->errors())
				->withInput();
				
		} catch (\Exception $e) {
			// Log unexpected errors
			Log::error('Unexpected error in checkin method', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
				'request_data' => $request->except(['_token'])
			]);

			return redirect()->back()->with('error', config('constants.server_error') ?? 'An unexpected error occurred. Please try again.');
		}
	}

	public function getcheckin(Request $request)
	{
		$CheckinLog 		= CheckinLog::where('id', '=', $request->id)->first();
		if ($CheckinLog && $CheckinLog->client_id) {
			$this->ensureCrmRecordAccess((int) $CheckinLog->client_id);
		}

		if($CheckinLog){
			ob_start();
				$walkInDisplay = ($CheckinLog->contact_type === 'Walk-in' || !$CheckinLog->client_id);
				$client = $walkInDisplay ? null : $CheckinLog->resolveCrmContact();

			?>
			<div class="row">
				<div class="col-md-12">
					<?php
					if($CheckinLog->status == 0){
						?>
						<h5 class="text-warning">Waiting</h5>
						<?php
					}else if($CheckinLog->status == 2){
						?>
						<h5 class="text-info">Attending</h5>
						<?php
					}else if($CheckinLog->status == 1){
						?>
						<h5 class="text-success">Completed</h5>
						<?php
					}
					?>
				</div>
			</div>
			<div class="row">
					<div class="col-md-6">
						<b>Contact</b>
						<div class="clientinfo">
							<?php if ($walkInDisplay) { ?>
								<span class="text-muted">Walk-in (not linked to a CRM record)</span>
								<?php if (!empty($CheckinLog->walk_in_phone)) { ?><br><?php echo e($CheckinLog->walk_in_phone); ?><?php } ?>
								<?php if (!empty($CheckinLog->walk_in_email)) { ?><br><?php echo e($CheckinLog->walk_in_email); ?><?php } ?>
							<?php } elseif ($client) {
								$clientLabel = CheckinLog::labelForCrmContact($client);
								$hasName = trim($client->first_name.' '.$client->last_name) !== '';
								?>
								<a href="<?php echo \URL::to('/clients/detail/'.base64_encode(convert_uuencode($client->id))); ?>"><?php echo e($clientLabel); ?></a>
								<?php if ($hasName && !empty($client->email)) { ?>
								<br>
								<?php echo e($client->email); ?>
								<?php } ?>
							<?php } else { ?>
								<span class="text-muted">Contact record unavailable</span>
							<?php } ?>
						</div>
					</div>
					<div class="col-md-6">
						<b><?php echo $CheckinLog->contact_type; ?></b>
						<br>
						<?php
						$checkin = \App\Models\Branch::where('id', $CheckinLog->office)->first();
						if($checkin){
						echo '<a target="_blank" href="'.\URL::to('/branch/view/'.@$checkin->id).'">'.@$checkin->office_name.'</a>';
						}
						?>

					</div>

					<div class="col-md-12">
						<div class="form-group">
							<label>Visit Purpose</label>
								<textarea class="form-control visitpurpose" data-id="<?php echo $CheckinLog->id; ?>" ><?php echo $CheckinLog->visit_purpose; ?></textarea>
						</div>
					</div>

					<div class="col-md-7">
						<table class="table">
						<thead>
								<tr>
									<th>In Person Date</th>
									<th>Session Start</th>
									<th>Session End</th>
								</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php echo date('Y-m-d',strtotime($CheckinLog->created_at)); ?></td>
								<td><?php if($CheckinLog->sesion_start != '') { echo date('h:i A',strtotime($CheckinLog->sesion_start)); }else{ echo '-'; } ?></td>
								<td><?php if($CheckinLog->sesion_end != '') { echo date('h:i A',strtotime($CheckinLog->sesion_end)); }else{ echo '-'; } ?></td>
							</tr>

							</tbody>
						</table>
					</div>
					<div class="col-md-5">
						<div style="padding: 6px 8px; border-radius: 4px; background-color: rgb(84, 178, 75); margin-top: 14px;">
						<div class="row">
						<div class="col-md-6">
							<div class="ag-flex col-hr-3" style="flex-direction: column;"><p class="marginNone text-semi-bold text-white">Wait Time</p> <p class="marginNone small  text-white"><?php if($CheckinLog->status == 0){ ?><span id="waitcount"> 00h 0m 0s </span><?php }else if($CheckinLog->status == 2){ echo '<span>'.$CheckinLog->wait_time.'</span>'; }else if($CheckinLog->status == 1){ echo '<span>'.$CheckinLog->wait_time.'</span>'; }else{ echo '<span >-</span>'; } ?></p></div></div>
							<div class="col-md-6">
							<div class="ag-flex" style="flex-direction: column;"><p class="marginNone text-semi-bold  text-white">Attend Time</p> <p class="marginNone small  text-white"><?php if($CheckinLog->status == 2){ ?><span id="attendtime"> 00h 0m 0s </span><?php }else if($CheckinLog->status == 1){ echo '<span>'.$CheckinLog->attend_time.'</span>'; }else{ echo '<span >-</span>'; } ?>

							</p></div></div>
							</div>
						</div>
					</div>
					<div class="col-md-7">
						<b>In Person Assignee </b> <a class="openassignee" href="javascript:;" title="Change Person Assignee" aria-label="Change Person Assignee"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-edit'); ?></a>
						<br>
						<?php
						$admin = \App\Models\Staff::find($CheckinLog->user_id);
						?>
						<a href=""><?php echo @$admin->first_name.' '.@$admin->last_name; ?></a>
						<br>
						<span><?php echo @$admin->email; ?></span>
						<div class="assignee" style="display:none; margin-top: 8px;">
						    <div class="row align-items-center">
						        <div class="col-md-9">
						            <select class="form-control mm-select" id="changeassignee" name="changeassignee">
						                 <?php
											foreach(\App\Models\Staff::orderby('first_name','ASC')->get() as $staffOption){
												$branchname = \App\Models\Branch::where('id',$staffOption->office_id)->first();
												$isSelected = ((int) $staffOption->id === (int) $CheckinLog->user_id);
										?>
												<option value="<?php echo $staffOption->id; ?>"<?php echo $isSelected ? ' selected' : ''; ?>><?php echo $staffOption->first_name.' '.$staffOption->last_name.' ('.@$branchname->office_name.')'; ?></option>
										<?php } ?>
									</select>
								</div>
								<div class="col-md-3" style="display:flex; align-items:center; gap:6px; flex-wrap:nowrap;">
									<a class="saveassignee btn btn-success" data-id="<?php echo $CheckinLog->id; ?>" href="javascript:;" style="white-space:nowrap;">Save</a>
									<a class="closeassignee" href="javascript:;"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-times'); ?></a>
								</div>
							</div>
						</div>
					</div>

					<div class="col-md-5">
					<?php
					if($CheckinLog->status == 0){
					?>
						<a data-id="<?php echo $CheckinLog->id; ?>" href="javascript:;" class="btn btn-success attendsession">Attend Session</a>
					<?php }else if($CheckinLog->status == 2){ ?>
						<a data-id="<?php echo $CheckinLog->id; ?>" href="javascript:;" class="btn btn-success completesession">Complete Session</a>
					<?php } ?>
					</div>
					<input type="hidden" value="" id="waitcountdata">
					<input type="hidden" value="" id="attendcountdata">
					<div class="col-md-12">
						<div class="form-group">
							<label>Comment</label>
							<textarea class="form-control visit_comment" name="comment"></textarea>
						</div>
						<div class="form-group">
							<button data-id="<?php echo $CheckinLog->id; ?>" type="button" class="savevisitcomment btn btn-primary">Save</button>
						</div>
					</div>

					<div class="col-md-12">
						<h4>Logs</h4>
						<div class="logsdata">
						<?php
						$logslist = CheckinHistory::where('checkin_id',$CheckinLog->id)->orderby('created_at', 'DESC')->get();
						foreach($logslist as $llist){
							$admin = \App\Models\Staff::find($llist->created_by);
						?>
							<div class="logsitem">
								<div class="row">
									<div class="col-md-7">
										<span class="ag-avatar"><?php echo $admin ? substr($admin->first_name ?? '', 0, 1) : '?'; ?></span>
										<span class="text_info"><span><?php echo $admin ? ($admin->first_name ?? '') : 'System'; ?></span><?php echo $llist->subject; ?></span>
									</div>
									<div class="col-md-5">
										<span class="logs_date"><?php echo date('d M Y h:i A', strtotime($llist->created_at)); ?></span>
									</div>
									<?php if($llist->description != ''){ ?>
									<div class="col-md-12 logs_comment">
										<p><?php echo $llist->description; ?></p>
									</div>
									<?php } ?>
								</div>
							</div>
						<?php } ?>
						</div>
					</div>
				</div>
				<script>
				function pretty_time_stringd(num) {
					return ( num < 10 ? "0" : "" ) + num;
				}
				var start = new Date('<?php echo date('Y-m-d H:i:s',strtotime($CheckinLog->created_at)); ?>');
				setInterval(function() {
				  var total_seconds = (new Date - start) / 1000;

				  var hours = Math.floor(total_seconds / 3600);
				  total_seconds = total_seconds % 3600;

				  var minutes = Math.floor(total_seconds / 60);
				  total_seconds = total_seconds % 60;

				  var seconds = Math.floor(total_seconds);

				  hours = pretty_time_stringd(hours);
				  minutes = pretty_time_stringd(minutes);
				  seconds = pretty_time_stringd(seconds);

				  var currentTimeString = hours + "h:" + minutes + "m:" + seconds+'s';

				  $('#waitcount').text(currentTimeString);
				  $('#waitcountdata').val(currentTimeString);
				}, 1000);
				<?php
				if($CheckinLog->status == 2){
					?>
					var start = new Date('<?php echo date('Y-m-d H:i:s',strtotime($CheckinLog->sesion_start)); ?>');
				setInterval(function() {
				  var total_seconds = (new Date - start) / 1000;

				  var hours = Math.floor(total_seconds / 3600);
				  total_seconds = total_seconds % 3600;

				  var minutes = Math.floor(total_seconds / 60);
				  total_seconds = total_seconds % 60;

				  var seconds = Math.floor(total_seconds);

				  hours = pretty_time_stringd(hours);
				  minutes = pretty_time_stringd(minutes);
				  seconds = pretty_time_stringd(seconds);

				  var currentTimeString = hours + "h:" + minutes + "m:" + seconds+'s';

				  $('#attendtime').text(currentTimeString);
				  $('#attendcountdata').val(currentTimeString);
				}, 1000);
					<?php
				}
				?>
				</script>
			<?php
			return ob_get_clean();
		}

	}
	public function update_visit_purpose(Request $request){
		$obj = CheckinLog::find($request->id);
		if ($obj && $obj->client_id) {
			$this->ensureCrmRecordAccess((int) $obj->client_id);
		}
		$obj->visit_purpose = $request->visit_purpose;
		$saved = $obj->save();
		if($saved){
			$response['status'] 	= 	true;
			$response['message']	=	'saved successfully';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

	public function update_visit_comment(Request $request){
		$checkinForAuth = CheckinLog::select('client_id')->where('id', $request->id)->first();
		if ($checkinForAuth && $checkinForAuth->client_id) {
			$this->ensureCrmRecordAccess((int) $checkinForAuth->client_id);
		}
		$objs = new CheckinHistory;
		$objs->subject = 'has commented';
		$objs->created_by = Auth::user()->id;
		$objs->checkin_id = $request->id;
		$objs->description = $request->visit_comment;
		$saved = $objs->save();
		if($saved){
			$response['status'] 	= 	true;
			$response['message']	=	'saved successfully';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

	public function change_assignee(Request $request){
		$objs = CheckinLog::find($request->id);
		if ($objs && $objs->client_id) {
			$this->ensureCrmRecordAccess((int) $objs->client_id);
		}
		$objs->user_id = $request->assinee;

		$saved = $objs->save();
		if($objs->status == 2){
		    $t = 'attending';
		}else if($objs->status == 1){
		    $t = 'completed';
		}else if($objs->status == 0){
		    $t = 'waiting';
		}
		if($saved){
		    $o = new \App\Models\Notification;
	    	$o->sender_id = Auth::user()->id;
	    	$o->receiver_id = $request->assinee;
	    	$o->module_id = $request->id;
	    	$o->url = \URL::to('/office-visits/'.$t);
	    	$o->notification_type = 'officevisit';
	    	$o->message = 'Office Visit Assigned by '.Auth::user()->first_name.' '.Auth::user()->last_name;
	    	$o->seen = 0;              // Mark as unseen
	    	$o->receiver_status = 0;   // Mark as unread by receiver
	    	$o->sender_status = 1;     // Mark as sent by sender
	    	$o->save();
	    	
	    	$notifyClientName = $objs->contactDisplayLabel();

	    	// Broadcast real-time notification via Reverb (wrap in try-catch)
	    	try {
	    	    broadcast(new OfficeVisitNotificationCreated(
	    	        $o->id,
	    	        $o->receiver_id,
	    	        [
	    	            'id' => $o->id,
	    	            'checkin_id' => $objs->id,
	    	            'message' => $o->message,
	    	            'sender_name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
	    	            'client_name' => $notifyClientName,
	    	            'visit_purpose' => $objs->visit_purpose,
	    	            'created_at' => $o->created_at ? $o->created_at->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A'),
	    	            'url' => $o->url
	    	        ]
	    	    ));
	    	} catch (\Exception $e) {
	    	    Log::warning('Failed to broadcast office visit assignee notification', [
	    	        'notification_id' => $o->id,
	    	        'error' => $e->getMessage()
	    	    ]);
	    	}
	    	
			$response['status'] 	= 	true;
			$response['message']	=	'Updated successfully';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}


    public function attend_session(Request $request){ 
		$obj = CheckinLog::find($request->id);
		if ($obj && $obj->client_id) {
			$this->ensureCrmRecordAccess((int) $obj->client_id);
		}
		$obj->sesion_start = date('Y-m-d H:i');
		$obj->wait_time = $request->waitcountdata;

		if($request->waitingtype == 1){ //waiting type = Pls send
            $obj->status = 2; //attending session
			$t = 'attending';
        } else {  //waiting type = waiting
            $obj->status = 0; //waiting session
            $obj->wait_type = 1; //waiting type = Pls send
			$t = 'waiting';
        }

        $saved = $obj->save();

        if($saved){
		    // Notify reception on both: red "Waiting" (escalate to Pls Send) and green "Pls Send" (session started).
		    // Real-time delivery is via OfficeVisitNotificationCreated + Echo; no page refresh needed on reception side.
		    $receiverId = config('constants.reception_user_id', 36730);
		    $o = new \App\Models\Notification;
		    $o->sender_id = Auth::user()->id;
		    $o->receiver_id = $receiverId;
		    $o->module_id = $request->id;
		    $o->url = \URL::to('/office-visits/'.$t);
		    $o->notification_type = 'officevisit';
		    $o->message = 'Office Visit Assigned by '.Auth::user()->first_name.' '.Auth::user()->last_name;
		    $o->seen = 0;              // Mark as unseen
		    $o->receiver_status = 0;   // Mark as unread by receiver
		    $o->sender_status = 1;     // Mark as sent by sender
		    $o->save();

		    try {
		        broadcast(new OfficeVisitNotificationCreated(
		            $o->id,
		            $o->receiver_id,
		            [
		                'id' => $o->id,
		                'checkin_id' => $obj->id,
		                'is_reception_alert' => true,
		                'message' => $o->message,
		                'sender_name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
		                'client_name' => $obj->contactDisplayLabel(),
		                'visit_purpose' => $obj->visit_purpose,
		                'created_at' => $o->created_at ? $o->created_at->format('d/m/Y h:i A') : now()->format('d/m/Y h:i A'),
		                'url' => $o->url
		            ]
		        ));
		    } catch (\Exception $e) {
		        Log::warning('Failed to broadcast office visit attend notification', [
		            'notification_id' => $o->id,
		            'error' => $e->getMessage()
		        ]);
		    }
		}

		$objs = new CheckinHistory;
		$objs->subject = 'has started session';
		$objs->created_by = Auth::user()->id;
		$objs->checkin_id = $request->id;
		$saved = $objs->save();
		if($saved){
			$crmForLog = $obj->resolveCrmContact();
			if ($crmForLog) {
				ActivitiesLog::create([
					'client_id' => $crmForLog->id,
					'created_by' => Auth::user()->id,
					'subject' => 'Office visit session started',
					'description' => 'Session started for office visit (check-in #' . $obj->id . ')',
					'activity_type' => 'office_visit_attend',
					'task_status' => 0,
					'pin' => 0,
				]);
			}
			$response['status'] 	= 	true;
			$response['message']	=	'saved successfully';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

	public function complete_session(Request $request){
		$obj = CheckinLog::find($request->id);
		if ($obj && $obj->client_id) {
			$this->ensureCrmRecordAccess((int) $obj->client_id);
		}
		$obj->sesion_end = date('Y-m-d H:i');
		$obj->attend_time = $request->attendcountdata;
		$obj->status = 1;
		$saved = $obj->save();

		$objs = new CheckinHistory;
		$objs->subject = 'has completed session';
		$objs->created_by = Auth::user()->id;
		$objs->checkin_id = $request->id;
		$saved = $objs->save();
		if($saved){
			$crmForLog = $obj->resolveCrmContact();
			if ($crmForLog) {
				ActivitiesLog::create([
					'client_id' => $crmForLog->id,
					'created_by' => Auth::user()->id,
					'subject' => 'Office visit session completed',
					'description' => 'Session completed for office visit (check-in #' . $obj->id . ')',
					'activity_type' => 'office_visit_complete',
					'task_status' => 0,
					'pin' => 0,
				]);
			}
			$response['status'] 	= 	true;
			$response['message']	=	'saved successfully';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}
	public function waiting(Request $request)
	{
	      if(isset($request->t)){
    	    if(\App\Models\Notification::where('id', $request->t)->exists()){
    	       $ovv =  \App\Models\Notification::find($request->t);
    	       $ovv->receiver_status = 1;
    	       $ovv->save();
    	    }
	    }
		$officeId = $this->officeFilterFromRequest($request);
		$query = CheckinLog::where('status', '=', 0)->forOfficeVisitViewer();
		if ($officeId !== null) {
			$query->where('office', '=', $officeId);
		}
		$totalData = (clone $query)->count();
		$lists = $query->with('assignee')->sortable(['id' => 'desc'])->paginate(config('constants.limit'));

		$activeTab = 'waiting';
		$tabCounts = CheckinLog::officeVisitTabCounts($officeId);
		return view('crm.officevisits.index', array_merge(
			compact('lists', 'totalData', 'activeTab'),
			[
				'InPersonCount_waiting_type' => $tabCounts['waiting'],
				'InPersonCount_attending_type' => $tabCounts['attending'],
				'InPersonCount_completed_type' => $tabCounts['completed'],
			]
		));
	}
	public function attending(Request $request)
	{
	      if(isset($request->t)){
    	    if(\App\Models\Notification::where('id', $request->t)->exists()){
    	       $ovv =  \App\Models\Notification::find($request->t);
    	       $ovv->receiver_status = 1;
    	       $ovv->save();
    	    }
	    }
		$officeId = $this->officeFilterFromRequest($request);
		$query = CheckinLog::where('status', '=', '2')->forOfficeVisitViewer();
		if ($officeId !== null) {
			$query->where('office', '=', $officeId);
		}
		$totalData = (clone $query)->count();
		$lists = $query->with('assignee')->sortable(['id' => 'desc'])->paginate(config('constants.limit'));

		$activeTab = 'attending';
		$tabCounts = CheckinLog::officeVisitTabCounts($officeId);
		return view('crm.officevisits.index', array_merge(
			compact('lists', 'totalData', 'activeTab'),
			[
				'InPersonCount_waiting_type' => $tabCounts['waiting'],
				'InPersonCount_attending_type' => $tabCounts['attending'],
				'InPersonCount_completed_type' => $tabCounts['completed'],
			]
		));
	}
	public function completed(Request $request)
	{
	      if(isset($request->t)){
    	    if(\App\Models\Notification::where('id', $request->t)->exists()){
    	       $ovv =  \App\Models\Notification::find($request->t);
    	       $ovv->receiver_status = 1;
    	       $ovv->save();
    	    }
	    }
		$officeId = $this->officeFilterFromRequest($request);
		$query = CheckinLog::where('status', '=', '1')->forOfficeVisitViewer();
		if ($officeId !== null) {
			$query->where('office', '=', $officeId);
		}
		$totalData = (clone $query)->count();
		$lists = $query->with('assignee')->sortable(['id' => 'desc'])->paginate(config('constants.limit'));
		$activeTab = 'completed';
		$tabCounts = CheckinLog::officeVisitTabCounts($officeId);
		return view('crm.officevisits.index', array_merge(
			compact('lists', 'totalData', 'activeTab'),
			[
				'InPersonCount_waiting_type' => $tabCounts['waiting'],
				'InPersonCount_attending_type' => $tabCounts['attending'],
				'InPersonCount_completed_type' => $tabCounts['completed'],
			]
		));
	}
	public function create(Request $request){
		return view('crm.officevisits.create');
	}

	private function officeFilterFromRequest(Request $request): ?int
	{
		if (! $request->has('office')) {
			return null;
		}
		$office = $request->input('office');
		if (trim((string) $office) === '') {
			return null;
		}

		return (int) $office;
	}

}
