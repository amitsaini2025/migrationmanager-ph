<?php
namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;

use App\Models\Lead;
use App\Models\Admin;
use App\Models\Country;
// use App\Models\WebsiteSetting; // removed website settings dependency
// use App\Models\State; // REMOVED: State model has been deleted
use PDF;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivitiesLog;
use App\Models\Note;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Services\MatterEmailBodyCleanupService;
use Carbon\Carbon;
use App\Models\ClientVisaCountry;
use App\Models\McqChapter;
use App\Models\McqSubject;
use App\Services\EmailService;
use App\Services\CrmSentEmailS3Service;
use App\Support\ComposeEmailPayload;
use App\Support\SendGridFromAllowedDomains;
use App\Support\StaffClientVisibility;
use App\Models\WorkflowStage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class CRMUtilityController extends Controller
{
    use EnsuresCrmRecordAccess;

    protected EmailService $emailService;
    protected CrmSentEmailS3Service $crmSentEmailS3Service;

    public function __construct(EmailService $emailService, CrmSentEmailS3Service $crmSentEmailS3Service)
    {
        $this->middleware('auth:admin');
        $this->emailService = $emailService;
        $this->crmSentEmailS3Service = $crmSentEmailS3Service;
    }
    // Dashboard functionality moved to DashboardController

    public function fetchnotification(Request $request){
        // $notificalists = \App\Models\Notification::where('receiver_id', Auth::user()->id)->where('receiver_status', 0)->orderby('created_at','DESC')->paginate(5);
         $notificalistscount = \App\Models\Notification::query()
            ->where('receiver_id', '=', (int) Auth::user()->id)
            ->where('receiver_status', '=', 0)
            ->count();
        /* $output = '';
	    foreach($notificalists as $listnoti){
	        $output .= '<a href="'.$listnoti->url.'?t='.$listnoti->id.'" class="dropdown-item dropdown-item-unread">
						<span class="dropdown-item-icon bg-primary text-white">
							<?php echo \App\Helpers\IconHelper::fromLegacy('fas fa-code'); ?>
						</span>
						<span class="dropdown-item-desc">'.$listnoti->message.' <span class="time">'.date('d/m/Y h:i A',strtotime($listnoti->created_at)).'</span></span>
					</a>';
	    }*/

	    $data = array(
           //'notification' => $output,
           'unseen_notification'  => $notificalistscount
        );
        echo json_encode($data);
    }

    public function fetchmessages(Request $request){
        $notificalists = \App\Models\Notification::query()
            ->where('receiver_id', '=', (int) Auth::user()->id)
            ->where('seen', '=', 0)
            ->first();
        if($notificalists){
            $obj = \App\Models\Notification::query()->whereKey($notificalists->id)->first();
            $obj->seen = 1;
            $obj->save();
            return $notificalists->message;
        }else{
            return 0;
        }
    }

    // Moved to DashboardController

    // Moved to DashboardController

    // Dashboard notification methods moved to DashboardController

    /**
     * My Profile.
     *
     * @return \Illuminate\Http\Response
     */
	public function returnsetting(Request $request){
		return view('crm.settings.returnsetting');
	}
	public function myProfile(Request $request)
	{
		/* Get all Select Data */
			$countries = array();
		/* Get all Select Data */

		if ($request->isMethod('post'))
		{
			$requestData 		= 	$request->all();

			$this->validate($request, [
										'first_name' => 'required',
										'last_name' => 'nullable',
										'country' => 'required',
										'phone' => 'required',
										'state' => 'required',
										'city' => 'required',
										'address' => 'required',
										'zip' => 'required'
									  ]);

			$obj							= 	\App\Models\Staff::query()->whereKey((int) Auth::user()->id)->first();

			$obj->first_name				=	@$requestData['first_name'];
			$obj->last_name					=	@$requestData['last_name'];
			$obj->phone						=	@$requestData['phone'];
			$obj->address					=	@$requestData['address'];
			$obj->company_name				=	@$requestData['company_name'];
			$obj->company_website			=	@$requestData['company_website'];

			$saved							=	$obj->save();

			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return Redirect::to('/my_profile')->with('success', 'Your Profile has been edited successfully.');
			}
		}
		else
		{
			$id = Auth::user()->id;
			$fetchedData = \App\Models\Staff::query()->whereKey((int) $id)->first();

			return view('crm.my_profile', compact(['fetchedData', 'countries']));
		}
	}
	/**
     * Change password and Logout automatiaclly.
     *
     * @return \Illuminate\Http\Response
     */
	public function change_password(Request $request)
	{
		//check authorization start
			/* $check = $this->checkAuthorizationAction('Admin', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			} */
		//check authorization end

		if ($request->isMethod('post'))
		{
			$this->validate($request, [
										'old_password' => 'required|min:6',
										'password' => 'required|confirmed|min:6',
										'password_confirmation' => 'required|min:6'
									  ]);

			$requestData 	= 	$request->all();
			$admin_id = Auth::user()->id;

			$fetchedData = \App\Models\Staff::query()->where('id', '=', $admin_id, 'and')->first();
			if(!empty($fetchedData))
				{
					if($admin_id == trim($requestData['admin_id']))
						{
							 if (!(Hash::check($request->get('old_password'), Auth::user()->password)))
								{
									return redirect()->back()->with("error","Your current password does not matches with the password you provided. Please try again.");
								}
							else
								{
									$admin = \App\Models\Staff::query()->whereKey((int) $requestData['admin_id'])->first();
									$admin->password = Hash::make($requestData['password']);
									if($admin->save())
										{
											Auth::guard('admin')->logout();
											$request->session()->flush();

											return redirect()->route('dashboard')->with('success', 'Your Password has been changed successfully.');
										}
									else
										{
											return redirect()->back()->with('error', config('constants.server_error'));
										}
								}
						}
					else
						{
							return redirect()->back()->with('error', 'You can change the password only your account.');
						}
				}
			else
				{
					return redirect()->back()->with('error', 'Staff member does not exist, so you cannot change the password.');
				}
		}
		return view('crm.change_password');
	}


	public function websiteSetting(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('Admin', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end

		if ($request->isMethod('post'))
		{
			$requestData 		= 	$request->all();

			$this->validate($request, [
										'phone' => 'required|max:20',
										'ofc_timing' => 'nullable|max:255',
										'email' => 'required|max:255'
									  ]);

			/* Logo Upload Function Start */
				if($request->hasfile('logo'))
				{
					/* Unlink File Function Start */
						if(@$requestData['logo'] != '')
							{
								$this->unlinkFile(@$requestData['old_logo'], config('constants.logo'));
							}
					/* Unlink File Function End */

					$logo = $this->uploadFile($request->file('logo'), config('constants.logo'));
				}
				else
				{
					$logo = @$requestData['old_logo'];
				}
			/* Logoe Upload Function End */

		// Website settings functionality disabled - WebsiteSetting model has been removed
		return redirect()->back()->with('error', 'Website settings functionality has been disabled - WebsiteSetting model has been removed');
		}
	else
	{
		// Website settings functionality disabled - WebsiteSetting model has been removed
		return redirect()->back()->with('error', 'Website settings functionality has been disabled - WebsiteSetting model has been removed');
	}
	}

	public function editapi(Request $request)
	{
		//check authorization start
			$check = $this->checkAuthorizationAction('api_key', $request->route()->getActionMethod(), Auth::user()->role);
			if($check)
			{
				return Redirect::to('/dashboard')->with('error',config('constants.unauthorized'));
			}
		//check authorization end
		if ($request->isMethod('post'))
		{
			$obj	= 	\App\Models\Staff::query()->whereKey((int) Auth::user()->id)->first();
			$obj->client_id	=	md5(Auth::user()->id.time());
			$saved				=	$obj->save();
			if(!$saved)
			{
				return redirect()->back()->with('error', config('constants.server_error'));
			}
			else
			{
				return Redirect::to('/api-key')->with('success', 'Api Key'.config('constants.edited'));
			}
		}else{
			return view('crm.apikey');
		}
	}

	public function updateAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);
			$requestData['current_status'] = trim($requestData['current_status']);
			$requestData['table'] = trim($requestData['table']);
			$requestData['col'] = trim($requestData['colname']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id']) && isset($requestData['current_status']) && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{
							if($requestData['current_status'] == 0)
							{
								$updated_status = 1;
								$message = 'Record has been enabled successfully.';
							}
							else
							{
								$updated_status = 0;
								$message = 'Record has been disabled successfully.';
							}
							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update([$requestData['col'] => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		 die;

	}

	public function moveAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);
			$requestData['col'] = trim($requestData['col']);

				if(isset($requestData['id']) && !empty($requestData['id']) && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

							$update = [$requestData['col'] => 0];
							if ($requestData['col'] === 'is_archived') {
								$update['archived_by'] = null;
								$update['archived_on'] = null;
							}

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update($update);
							if($response)
							{
								$status = 1;
								$message = 'Record successfully moved';
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}

		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function declinedAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 2;
								$message = 'Record has been disabled successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function approveAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 1;
								$message = 'Record has been approved successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error').'sss';
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function processAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 4;
								$message = 'Record has been processed successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $updated_status]);
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error').'sss';
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function archiveAction(Request $request)
	{
		$status 			= 	0;
		$astatus            =   '';
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			$requestData['table'] = trim($requestData['table']);

			$astatus = '';
			$role = Auth::user()->role;
			$userType = Auth::user()->type ?? '';
			if($role == 1 || in_array($userType, ['client', 'lead']))
			{
				if(isset($requestData['id']) && !empty($requestData['id'])  && isset($requestData['table']) && !empty($requestData['table']))
				{
					$tableExist = Schema::hasTable(trim($requestData['table']));

					if($tableExist)
					{
						$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();

						if($recordExist)
						{

								$updated_status = 1;
								$message = 'Record has been archived successfully.';

							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['is_archive' => $updated_status]);
							$getarchive 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->first();
							if($getarchive->status == 0){
								$astatus = '<span title="draft" class="ui label uppercase">Draft</span><span> (Archived)</span>';
							}else if($getarchive->status == 1){
								$astatus = '<span title="draft" class="ui label uppercase yellow">Sent</span><span> (Archived)</span>';
							}else if($getarchive->status == 2){
								$astatus = '<span title="draft" class="ui label uppercase text-danger">Declined</span><span> (Archived)</span>';
							}
							if($response)
							{
								$status = 1;
							}
							else
							{
								$message = config('constants.server_error');
							}
						}
						else
						{
							$message = 'ID does not exist, please check it once again.';
						}
					}
					else
					{
						$message = 'Table does not exist, please check it once again.';
					}
				}
				else
				{
					$message = 'Id OR Current Status OR Table does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'You are not authorized person to perform this action.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message, 'astatus'=>$astatus));
		die;
	}

	public function deleteAction(Request $request)
	{
		$status 			= 	0;
		$method 			= 	$request->method();
		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all(); //dd($requestData);
            $requestData['id'] = trim($requestData['id']);
			$requestData['table'] = trim($requestData['table']);
            $role = Auth::user()->role;
            if(isset($requestData['id']) && !empty($requestData['id']) && isset($requestData['table']) && !empty($requestData['table']))
			{
				$tableExist = Schema::hasTable(trim($requestData['table']));
                if($tableExist)
				{
					$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                    if($recordExist)
					{
						if($requestData['table'] == 'admins'){
                            $o = \App\Models\Admin::query()->where('id', '=', $requestData['id'], 'and')->first();
							if($o->status == 1){
								$is_status = 0;
							}else{
								$is_status = 1;
							}
							$response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['status' => $is_status, 'updated_at' => date('Y-m-d H:i:s')]);
							if($response) {
								$status = 1;
                                if($is_status == 0 ) {
                                    $message = 'Record has been inactive successfully.';
                                } else {
                                    $message = 'Record has been active successfully.';
                                }
                            }
							else {
								$message = config('constants.server_error');
							}
						}
                        else if($requestData['table'] == 'client_matters'){
                            $matterId = (int) $requestData['id'];
                            $response = false;

                            DB::transaction(function () use ($requestData, $matterId, &$response) {
                                $response = DB::table($requestData['table'])
                                    ->where('id', $requestData['id'])
                                    ->update(['matter_status' => 0]);

                                if ($response) {
                                    app(MatterEmailBodyCleanupService::class)->clearBodiesForMatter($matterId);
                                }
                            });

							if($response) {
								$status = 1;
								$message = 'Record has been enabled successfully.';
							} else {
								$message = config('constants.server_error');
							}
						}
                        else if($requestData['table'] == 'quotations'){
                            $response 	= 	DB::table($requestData['table'])->where('id', $requestData['id'])->update(['is_archive' => 1]);
							if($response) {
								$status = 1;
								$message = 'Record has been enabled successfully.';
							} else {
								$message = config('constants.server_error');
							}
						}
                        else if($requestData['table'] == 'templates'){
                            $isexist	=	$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                            if($isexist){
                                $response	=	DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                DB::table('template_infos')->where('quotation_id', @$requestData['id'])->delete();
                                if($response) {
                                    $status = 1;
                                    $message = 'Record has been deleted successfully.';
                                } else {
                                    $message = config('constants.server_error');
                                }
                            }else{
                                $message = 'ID does not exist, please check it once again.';
                            }
						}
                        else if($requestData['table'] == 'products'){
                            // applications table removed - product_id check no longer needed
                            $applicationisexist = false;

                            if($applicationisexist){
                                $message = "Can't Delete its have relation with other records";
                            }else{
                                $isexist	=	$recordExist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                                if($isexist){
                                $response	=	DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                DB::table('template_infos')->where('quotation_id', @$requestData['id'])->delete();

                                if($response) {
                                    $status = 1;
                                    $message = 'Record has been deleted successfully.';
                                } else {
                                    $message = config('constants.server_error');
                                }
                                }else{
                                    $message = 'ID does not exist, please check it once again.';
                                }
                            }
                        }
                        else if($requestData['table'] == 'email_labels'){
                            $label = DB::table($requestData['table'])->where('id', $requestData['id'])->first();
                            if($label && $label->type == 'system'){
                                $message = 'System labels cannot be deleted.';
                            } else {
                                $isexist = DB::table($requestData['table'])->where('id', $requestData['id'])->exists();
                                if($isexist){
                                    $response = DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                    if($response) {
                                        $status = 1;
                                        $message = 'Record has been deleted successfully.';
                                    } else {
                                        $message = config('constants.server_error');
                                    }
                                }else{
                                    $message = 'ID does not exist, please check it once again.';
                                }
                            }
                        }
                        else if ($requestData['table'] == 'workflow_stages') {
                            $stage = WorkflowStage::with('workflow')->find($requestData['id']);
                            if ($stage && $stage->isFrozen()) {
                                $message = 'This workflow stage is frozen and cannot be deleted.';
                            } else {
                                $response = DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                                if ($response) {
                                    $status = 1;
                                    $message = 'Record has been deleted successfully.';
                                } else {
                                    $message = config('constants.server_error');
                                }
                            }
                        }
                        else{
                            $response	=	DB::table($requestData['table'])->where('id', @$requestData['id'])->delete();
                            if($response) {
                                $status = 1;
                                $message = 'Record has been deleted successfully.';
                            } else {
                                $message = config('constants.server_error');
                            }
                        }
					}
                    else
                    {
                        $message = 'ID does not exist, please check it once again.';
                    }
                }
                else
                {
                    $message = 'Table does not exist, please check it once again.';
                }
            }
            else
            {
                $message = 'Id OR Table does not exist, please check it once again.';
            }
        }
		else {
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message));
		die;
	}

	public function getStates(Request $request)
	{
		$status 			= 	0;
		$data				=	array();
		$method 			= 	$request->method();

		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			if(isset($requestData['id']) && !empty($requestData['id']))
			{
				$recordExist = Country::query()->where('id', '=', $requestData['id'], 'and')->exists();

			if($recordExist)
			{
				// State functionality disabled - State model has been removed
				$data = [];
				$status = 0;
				$message = 'State functionality has been disabled - State model has been removed';
			}
			else
			{
				$message = 'ID does not exist, please check it once again.';
			}
		}
		else
		{
			$message = 'ID does not exist, please check it once again.';
		}
	}
	else
	{
		$message = config('constants.post_method');
	}
	echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));
	die;
}

public function getChapters(Request $request)
	{
		$status 			= 	0;
		$data				=	array();
		$method 			= 	$request->method();

		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			$requestData['id'] = trim($requestData['id']);

			if(isset($requestData['id']) && !empty($requestData['id']))
			{
				$recordExist = McqSubject::query()->where('id', '=', $requestData['id'], 'and')->exists();

				if($recordExist)
				{
					$data 	= 	McqChapter::query()->where('subject_id', '=', $requestData['id'], 'and')->get();

					if($data)
					{
						$status = 1;
						$message = 'Record has been fetched successfully.';
					}
					else
					{
						$message = config('constants.server_error');
					}
				}
				else
				{
					$message = 'ID does not exist, please check it once again.';
				}
			}
			else
			{
				$message = 'ID does not exist, please check it once again.';
			}
		}
		else
		{
			$message = config('constants.post_method');
		}
		echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));
		die;
	}

	public function addCkeditiorImage(Request $request)
	{
		echo "<pre>";
		print_r($_FILES);die;

		$status 			= 	0;
		$method 			= 	$request->method();

		if ($request->isMethod('post'))
		{
			$requestData 	= 	$request->all();

			echo "<pre>";
			print_r($requestData);die;

			if(isset($requestData['id']) && !empty($requestData['id']))
			{
			$recordExist = Country::query()->where('id', '=', $requestData['id'], 'and')->exists();

			if($recordExist)
			{
				// State functionality disabled - State model has been removed
				$data = [];
				$status = 0;
				$message = 'State functionality has been disabled - State model has been removed';
			}
			else
			{
				$message = 'ID does not exist, please check it once again.';
			}
		}
		else
		{
			$message = 'ID does not exist, please check it once again.';
		}
	}
	else
	{
		$message = config('constants.post_method');
	}
	echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));
	die;
}

	public function gettemplates(Request $request){
		$id = $request->id;
		$template = \App\Models\EmailTemplate::query()->whereKey((int) $id)->first();
		if ($template) {
			echo json_encode(array('subject' => $template->subject, 'description' => $template->description));
		} else {
			echo json_encode(array('subject' => '', 'description' => ''));
		}
	}

	/**
	 * Get compose defaults for a client matter: first email template, dedicated checklist IDs, and macro values.
	 * Used to auto-select matter's first email and checklists when opening compose modal.
	 * macro_values enables replacement of {ClientID}, {ApplicantGivenNames}, {visa_apply}, etc. in First email template.
	 */
	public function getComposeDefaults(Request $request){
		$clientMatterId = $request->client_matter_id;
		if (!$clientMatterId) {
			return response()->json(['template' => null, 'checklist_ids' => [], 'macro_values' => null]);
		}
		$clientMatter = ClientMatter::query()->whereKey((int) $clientMatterId)->first();
		if (!$clientMatter || !$clientMatter->sel_matter_id) {
			return response()->json(['template' => null, 'checklist_ids' => [], 'macro_values' => null]);
		}
		$matterId = $clientMatter->sel_matter_id;
		$clientId = $clientMatter->client_id;

		// First Email template - one per matter
		$firstTemplate = \App\Models\EmailTemplate::forMatter($matterId)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->orderBy('id', 'asc')->first();

		// Additional matter templates - multiple per matter
		$otherTemplates = \App\Models\EmailTemplate::forMatter($matterId)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_OTHER)->orderBy('id', 'asc')->get();

		// Build full list: first email first, then other templates
		$allTemplates = [];
		if ($firstTemplate) {
			$allTemplates[] = ['id' => $firstTemplate->id, 'name' => $firstTemplate->name, 'subject' => $firstTemplate->subject, 'description' => $firstTemplate->description];
		}
		foreach ($otherTemplates as $t) {
			$allTemplates[] = ['id' => $t->id, 'name' => $t->name, 'subject' => $t->subject, 'description' => $t->description];
		}

		$checklistIds = \App\Models\UploadChecklist::query()->where('matter_id', '=', $matterId, 'and')->pluck('id')->toArray();

		// Build macro values for First email template replacement
		$macroValues = $this->getComposeMacroValues($clientId, $clientMatterId);

		return response()->json([
			'template' => $firstTemplate ? ['id' => $firstTemplate->id, 'name' => $firstTemplate->name, 'subject' => $firstTemplate->subject, 'description' => $firstTemplate->description] : null,
			'matter_templates' => $allTemplates,
			'checklist_ids' => $checklistIds,
			'macro_values' => $macroValues,
		]);
	}

	/**
	 * Get macro replacement values for a client matter (ClientID, ApplicantGivenNames, visa_apply, fees, etc.)
	 */
	protected function getComposeMacroValues(int $clientId, int $clientMatterId)
	{
		$client = Admin::query()->whereKey((int) $clientId)->first();
		if (!$client) {
			return null;
		}

		$clientMatter = ClientMatter::query()->whereKey((int) $clientMatterId)->first();
		if (!$clientMatter) {
			return null;
		}

		$values = [
			'ClientID' => $client->client_id ?? '',
			'ApplicantGivenNames' => $client->first_name ?? '',
			'ApplicantSurname' => $client->last_name ?? '',
			'client_firstname' => ($client->first_name ?? '') ? ucfirst($client->first_name) : '',
			'client_reference' => $client->client_id ?? '',
			'visa_apply' => '',
			'Blocktotalfeesincltax' => '',
			'TotalDoHASurcharges' => '',
			'TotalEstimatedOthCosts' => '',
			'GrandTotalFeesAndCosts' => '',
			'PDF_url_for_sign' => '',
		];

		// Get matter/cost assignment info
		$matterInfo = null;
		$costAssignment = \App\Models\CostAssignmentForm::query()
            ->where('client_id', '=', $clientId, 'and')
            ->where('client_matter_id', '=', $clientMatterId, 'and')
            ->first();
		if ($costAssignment) {
			$matterInfo = DB::table('cost_assignment_forms')->where('client_id', $clientId)->where('client_matter_id', $clientMatterId)->first();
		}
		if (!$matterInfo && $clientMatter->sel_matter_id) {
			$clientMatterInfo = DB::table('client_matters')->select('sel_matter_id')->where('id', $clientMatterId)->first();
			if ($clientMatterInfo) {
				$matterInfo = DB::table('matters')->where('id', $clientMatterInfo->sel_matter_id)->first();
			}
		}

		// visa_apply must always come from matter title (matters table), not cost_assignment_forms
		if ($clientMatter->sel_matter_id) {
			$matterTitleRow = DB::table('matters')->select('title')->where('id', $clientMatter->sel_matter_id)->first();
			$values['visa_apply'] = $matterTitleRow->title ?? '';
		}

		if ($matterInfo) {

			$block1 = floatval($matterInfo->Block_1_Ex_Tax ?? 0);
			$block2 = floatval($matterInfo->Block_2_Ex_Tax ?? 0);
			$block3 = floatval($matterInfo->Block_3_Ex_Tax ?? 0);
			$blockTotal = $block1 + $block2 + $block3;
			$totalSurcharges = floatval($matterInfo->TotalDoHASurcharges ?? 0);
			$totalOther = floatval($matterInfo->additional_fee_1 ?? 0);
			// Department charges total (Dept. Charges on checklist) - from TotalDoHACharges when present (e.g. cost_assignment_forms), else sum Dept_* for compatibility
			$totalDepartmentCharges = floatval($matterInfo->TotalDoHACharges ?? 0);
			if ($totalDepartmentCharges == 0 && isset($costAssignment)) {
				$totalDepartmentCharges =
					floatval($matterInfo->Dept_Base_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Non_Internet_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Additional_Applicant_Charge_18_Plus ?? 0) +
					floatval($matterInfo->Dept_Additional_Applicant_Charge_Under_18 ?? 0) +
					floatval($matterInfo->Dept_Subsequent_Temp_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Second_VAC_Instalment_Charge_18_Plus ?? 0) +
					floatval($matterInfo->Dept_Second_VAC_Instalment_Under_18 ?? 0) +
					floatval($matterInfo->Dept_Nomination_Application_Charge ?? 0) +
					floatval($matterInfo->Dept_Sponsorship_Application_Charge ?? 0);
			}
			$grandTotal = $blockTotal + $totalDepartmentCharges + $totalSurcharges + $totalOther;

			$values['Blocktotalfeesincltax'] = number_format($blockTotal, 2, '.', '');
			// First email template: "Department fee, including the card Surcharge" row uses ${TotalDoHASurcharges} → send department total to match checklist
			$values['TotalDoHASurcharges'] = number_format($totalDepartmentCharges, 2, '.', '');
			// First email template: "Other Costs (estimated)" row uses ${TotalEstimatedOthCosts} → send surcharge to match checklist
			$values['TotalEstimatedOthCosts'] = number_format($totalSurcharges, 2, '.', '');
			$values['GrandTotalFeesAndCosts'] = number_format($grandTotal, 2, '.', '');
		}

		$signingUrl = $this->getAgreementSigningUrlForMatter($clientMatterId);
		if ($signingUrl !== '') {
			$values['PDF_url_for_sign'] = $signingUrl;
		}

		return $values;
	}

	/**
	 * Resolve the pending agreement signing URL for a client matter (compose email / First email macro).
	 * Matches checklist logic: latest agreement doc with signature_doc_link when not yet signed.
	 */
	protected function getAgreementSigningUrlForMatter(int $clientMatterId): string
	{
		$agreementDoc = Document::query()
            ->where('client_matter_id', '=', $clientMatterId, 'and')
			->where('doc_type', '=', 'agreement', 'and')
			->latest()
			->first();

		if (!$agreementDoc || $agreementDoc->status === 'signed' || empty($agreementDoc->signature_doc_link)) {
			return '';
		}

		$signatureLinks = json_decode($agreementDoc->signature_doc_link, true);
		if (!is_array($signatureLinks)) {
			return '';
		}

		$primaryLink = $signatureLinks[0] ?? null;
		$url = is_array($primaryLink) ? trim((string) ($primaryLink['url'] ?? '')) : '';

		if ($url === '') {
			return '';
		}

		return ComposeEmailPayload::normalizeSigningUrl($url) ?? '';
	}

	/**
	 * Convert plain URLs in HTML content to clickable links (open in new tab, copyable).
	 * Skips text inside existing <a>...</a> blocks so link text URLs are not double-wrapped.
	 */
	protected function linkifyUrlsInHtml(string $html): string
	{
		if (empty(trim($html))) {
			return $html;
		}

		$parts = preg_split('#(<a\b[^>]*>.*?</a>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		if ($parts === false) {
			return $this->linkifyPlainUrlsInHtml($html);
		}

		$result = '';
		foreach ($parts as $i => $part) {
			if ($i % 2 === 1) {
				$result .= $part;
				continue;
			}
			$result .= $this->linkifyPlainUrlsInHtml($part);
		}

		return $result;
	}

	/**
	 * Linkify bare URLs in HTML fragments that are not already inside anchor tags.
	 */
	protected function linkifyPlainUrlsInHtml(string $html): string
	{
		if ($html === '') {
			return $html;
		}

		$pattern = '#(^|[\s>])(https?://[^\s<>"\']+)#i';

		return preg_replace_callback($pattern, function ($m) {
			$prefix = $m[1];
			$url = $m[2];
			$link = '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;word-break:break-all;">' . htmlspecialchars($url) . '</a>';

			return $prefix . $link;
		}, $html);
	}

    public function sendmail(Request $request){
		$requestData = $request->all();
		// WAF-safe transport: subject placeholders + optional base64 message body (see ComposeEmailPayload)
		$requestData['subject'] = ComposeEmailPayload::decodeSubject($requestData);
		$requestData['message'] = ComposeEmailPayload::decodeMessage($requestData);
		//echo '<pre>'; print_r($requestData); die;

		// Compose email: explicit CRM record access + structured deny message + audit log
		$associatedAdminId = $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
		if ($associatedAdminId) {
			$this->assertComposeEmailCanAccessCrmRecord($request, (int) $associatedAdminId);
		}

		$fromForCompose = trim((string) ($requestData['email_from'] ?? ''));
		if ($fromForCompose === '' || ! SendGridFromAllowedDomains::allowsEmail($fromForCompose)) {
			$msg = 'The selected From address is not allowed for this CRM.';
			if ($request->ajax() || $request->wantsJson()) {
				return response()->json([
					'status' => false,
					'success' => false,
					'message' => $msg,
				], 422);
			}

			return redirect()->back()->withErrors(['email_from' => $msg])->withInput();
		}

		$requestData['email_from'] = $fromForCompose;

		$user_id = @Auth::user()->id;
		$reciept_id = null;

		$obj = new \App\Models\EmailLog;
		$obj->user_id 		=  $user_id;
		$obj->from_mail 	=  $requestData['email_from'];
		// email_to / email_cc are Admin or Agent row IDs from the compose UI — store actual addresses
		$emailToList = isset($requestData['email_to']) && is_array($requestData['email_to'])
			? $requestData['email_to']
			: array_filter([(string) ($requestData['email_to'] ?? '')]);
		$resolvedTo = [];
		$unresolvedRecipientLabels = [];
		foreach ($emailToList as $recipientId) {
			if ($recipientId === '' || $recipientId === null) {
				continue;
			}
			if (($requestData['type'] ?? '') === 'agent') {
				$r = \App\Models\AgentDetails::query()->where('id', '=', $recipientId, 'and')->first();
				if ($r) {
					$em = $r->email ?: $r->business_email;
					if ($em) {
						$resolvedTo[] = $em;
					}
				}
			} else {
				$r = \App\Models\Admin::query()->where('id', '=', $recipientId, 'and')->first();
				if (! $r) {
					continue;
				}
				// Prefer real contact/client_emails over company @lead.internal placeholders
				$em = $r->resolveDeliverableEmail();
				if ($em) {
					$resolvedTo[] = $em;
				} else {
					$label = trim((string) (($r->first_name ?? '') . ' ' . ($r->last_name ?? '')));
					if ($label === '') {
						$label = trim((string) ($r->company_name ?? ''));
					}
					$unresolvedRecipientLabels[] = $label !== '' ? $label : ('ID ' . $r->id);
				}
			}
		}
		if ($unresolvedRecipientLabels !== []) {
			$msg = 'Cannot send email: no real recipient email found for '
				. implode(', ', array_unique($unresolvedRecipientLabels))
				. '. Please add a real email on the company/contact profile.';
			if ($request->ajax() || $request->wantsJson()) {
				return response()->json([
					'status' => false,
					'success' => false,
					'message' => $msg,
				], 422);
			}

			return redirect()->back()->with('error', $msg)->withInput();
		}
		$obj->to_mail = $resolvedTo !== []
			? implode(',', array_unique($resolvedTo))
			: implode(',', $emailToList);
		if (isset($requestData['email_cc']) && ! empty($requestData['email_cc'])) {
			$obj->cc = implode(',', $this->resolveComposeEmailCcAddresses($requestData['email_cc']));
		}
        $obj->template_id 	=  $requestData['template'] ?? null;
		$obj->reciept_id 	=  $reciept_id;
		$obj->subject		=  $requestData['subject'];
		if(isset($requestData['type'])){
		    $obj->type 			=  @$requestData['type'];
		}
		$obj->message		 =  $requestData['message'];
        $obj->mail_type		 =  $requestData['mail_type'] ?? 1;
        $obj->client_id		 =  $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
        $obj->client_matter_id	=  $requestData['compose_client_matter_id'] ?? null;
        $obj->delivery_status  =  'pending';

		$attachments = array();
		if(isset($requestData['checklistfile'])){
            if(!empty($requestData['checklistfile'])){
                $checklistfiles = $requestData['checklistfile'];
                $attachments = array();
                foreach($checklistfiles as $checklistfile){
                    $filechecklist =  \App\Models\UploadChecklist::query()->where('id', '=', $checklistfile, 'and')->first();
                    if($filechecklist){
                        $attachments[] = array('file_name' => $filechecklist->name,'file_url' => $filechecklist->file);
                    }
                }
                //$obj->attachments = json_encode($attachments);
            }
        }

        $attachments2 = array();
        if(isset($requestData['checklistfile_document'])){
            if(!empty($requestData['checklistfile_document'])){
                $checklistfiles_documents = $requestData['checklistfile_document'];
                $attachments2 = array();
                foreach($checklistfiles_documents as $checklistfile1){
                    $filechecklist_doc =  \App\Models\Document::query()->where('id', '=', $checklistfile1, 'and')->first();
                    if($filechecklist_doc){
                        if( $filechecklist_doc->doc_type == "education" || $filechecklist_doc->doc_type == "migration" ){
                            $attachments2[] = array('file_name' => $filechecklist_doc->name,'file_url' => $filechecklist_doc->file);
                        }
                        else if( $filechecklist_doc->doc_type == "documents")  {
                            $attachments2[] = array('file_name' => $filechecklist_doc->file_name,'file_url' => $filechecklist_doc->myfile);
                        }
                    }
                }
                //$obj->attachments = json_encode($attachments);
            }
        }

        $attachments = array_merge($attachments, $attachments2);
        if ($request->hasFile('attach')) {
            foreach ($request->file('attach') as $upFile) {
                if ($upFile->isValid()) {
                    $attachments[] = [
                        'file_name' => $upFile->getClientOriginalName(),
                        'file_url' => 'user_upload',
                    ];
                }
            }
        }
        if (!empty($attachments) && count($attachments) > 0) {
            $obj->attachments = json_encode($attachments);
        }

        $saved	=	$obj->save();
        $activityClientId = $requestData['client_id'] ?? $requestData['lead_id'] ?? null;
        if ($activityClientId === null && ! empty($emailToList) && ($requestData['type'] ?? '') !== 'agent') {
            $activityClientId = (int) reset($emailToList);
        }
        if (isset($requestData['checklistfile'])) {
            if (! empty($requestData['checklistfile']) && $activityClientId) {
                $objs = new \App\Models\ActivitiesLog;
                $objs->client_id = $activityClientId;
                $objs->created_by = Auth::user()->id;
                $objs->subject = 'Checklist sent to client';
                $objs->task_status = 0;
                $objs->pin = 0;
                $objs->save();
            }
        }

        if (isset($requestData['checklistfile_document'])) {
            if (! empty($requestData['checklistfile_document']) && $activityClientId) {
                $objs = new \App\Models\ActivitiesLog;
                $objs->client_id = $activityClientId;
                $objs->created_by = Auth::user()->id;
                $objs->subject = 'Document Checklist sent to client';
                $objs->task_status = 0;
                $objs->pin = 0;
                $objs->save();
            }
        }

        // Visa sheet integration: when checklist sent, update the correct reference table per subclass (TR, Visitor, Student, PR, Employer Sponsored)
        $checklistWasSent = (!empty($requestData['checklistfile']) || !empty($requestData['checklistfile_document']));
        $clientMatterId = $requestData['compose_client_matter_id'] ?? null;
        $isLead = (($requestData['type'] ?? '') === 'lead');
        $leadId = $requestData['lead_id'] ?? ($requestData['client_id'] ?? null);
        $composeMatterId = $requestData['compose_matter_id'] ?? null;

        if ($checklistWasSent && $clientMatterId) {
            $clientMatter = ClientMatter::with('matter')->find($clientMatterId);
            if ($clientMatter) {
                $clientMatter->recordChecklistSent(Auth::user()->id);
            }
        } elseif ($checklistWasSent && $isLead && $leadId && $composeMatterId) {
            \App\Services\VisaSheetService::recordLeadChecklistSent((int) $leadId, (int) $composeMatterId, Auth::user()->id);
        }

		$subject = $requestData['subject'];
		$message = $requestData['message'];

		// Replace First email macros when matter context is present
		$clientMatterIdForMacros = $requestData['compose_client_matter_id'] ?? null;
		$clientIdForMacros = $requestData['client_id'] ?? null;
		if ($clientMatterIdForMacros && $clientIdForMacros) {
			$macroValues = $this->getComposeMacroValues($clientIdForMacros, $clientMatterIdForMacros);
		if ($macroValues) {
			foreach ($macroValues as $key => $val) {
				if ((string)$val === '' || $key === 'PDF_url_for_sign') continue;
				$subject = str_replace('{' . $key . '}', $val, $subject);
				$subject = str_replace('${' . $key . '}', $val, $subject);
				$message = str_replace('{' . $key . '}', $val, $message);
				$message = str_replace('${' . $key . '}', $val, $message);
			}
		}
		}

		$signingUrl = ComposeEmailPayload::decodeSigningUrl($requestData);
		if ($signingUrl === null && $clientMatterIdForMacros) {
			$dbSigningUrl = $this->getAgreementSigningUrlForMatter($clientMatterIdForMacros);
			$signingUrl = $dbSigningUrl !== '' ? $dbSigningUrl : null;
		}

		$message = ComposeEmailPayload::applySigningLinkToMessage($message, $signingUrl);

		// Convert plain URLs to clickable links (open in new tab, copyable)
		$message = $this->linkifyUrlsInHtml($message);

		$baseSubject = $subject;
		$baseMessage = $message;

		$preparedSendPaths = [];
		if (isset($requestData['checklistfile']) && !empty($requestData['checklistfile'])) {
			foreach ($requestData['checklistfile'] as $checklistfile) {
				$filechecklist = \App\Models\UploadChecklist::query()->where('id', '=', $checklistfile, 'and')->first();
				if ($filechecklist) {
					$preparedSendPaths[] = public_path('checklists' . DIRECTORY_SEPARATOR . $filechecklist->file);
				}
			}
		}
		if (isset($requestData['checklistfile_document']) && !empty($requestData['checklistfile_document'])) {
			foreach ($requestData['checklistfile_document'] as $checklistfile1) {
				$filechecklist_doc = \App\Models\Document::query()->where('id', '=', $checklistfile1, 'and')->first();
				if ($filechecklist_doc) {
					if ($filechecklist_doc->doc_type == 'education' || $filechecklist_doc->doc_type == 'migration') {
						$preparedSendPaths[] = public_path('img' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $filechecklist_doc->myfile);
					} elseif ($filechecklist_doc->doc_type == 'documents') {
						$fileUrl = $filechecklist_doc->myfile;
						if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
							$tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($fileUrl);
							@file_put_contents($tempPath, @file_get_contents($fileUrl));
							$preparedSendPaths[] = $tempPath;
						} else {
							$preparedSendPaths[] = $fileUrl;
						}
					}
				}
			}
		}
		if ($request->hasFile('attach')) {
			foreach ($request->file('attach') as $file1) {
				if ($file1->isValid()) {
					$displayName = preg_replace('/[^a-zA-Z0-9._\-\s]/', '_', $file1->getClientOriginalName());
					$content = file_get_contents($file1->getRealPath());
					if ($content === false || $content === '') {
						continue;
					}
					$preparedSendPaths[] = [
						'content' => $content,
						'name' => $displayName !== '' ? $displayName : 'attachment',
					];
				}
			}
		}

		$attachmentTuplesForArchive = [];
		foreach ($preparedSendPaths as $p) {
			if (is_array($p) && !empty($p['content'])) {
				$attachmentTuplesForArchive[] = [
					'content' => $p['content'],
					'name' => $p['name'] ?? 'attachment',
				];
			} elseif (is_string($p) && is_readable($p)) {
				$attachmentTuplesForArchive[] = ['path' => $p, 'name' => basename($p)];
			}
		}

		$emailToRecipients = $requestData['email_to'] ?? null;
		if (!is_array($emailToRecipients)) {
			$emailToRecipients = $emailToRecipients !== null && $emailToRecipients !== '' ? [(string) $emailToRecipients] : [];
		}

		$anySendSuccess = false;
		$lastSendSubject = $baseSubject;
		$lastSendMessage = $baseMessage;

		if ($saved && $obj->id) {
		foreach ($emailToRecipients as $l) {
			$subject = $baseSubject;
			$message = $baseMessage;
			if (@$requestData['type'] == 'agent') {
				$client = \App\Models\AgentDetails::query()->where('id', '=', $l, 'and')->first();
				if (!$client) {
					continue;
				}
				$subject = str_replace('{Client First Name}', $client->full_name, $subject);
				$message = str_replace('{Client First Name}', $client->full_name, $message);
			} else {
				$client = \App\Models\Admin::query()->where('id', '=', $l, 'and')->first();
				if (!$client) {
					continue;
				}
				$subject = str_replace('{Client First Name}', $client->first_name, $subject);
				$message = str_replace('{Client First Name}', $client->first_name, $message);
			}

			$message = str_replace('{Client Assignee Name}', $client->first_name, $message);
			$message = str_replace('{Company Name}', optional(Auth::user())->company_name ?? '', $message);

			$ccarray = $this->resolveComposeEmailCcAddresses($requestData['email_cc'] ?? []);

			try {
				// Clients/leads: resolve real address (skip @lead.internal). Agents: unchanged.
				if (@$requestData['type'] == 'agent') {
					$recipientEmail = $client->email;
				} else {
					$recipientEmail = $client->resolveDeliverableEmail();
					if (! $recipientEmail) {
						$label = trim((string) (($client->first_name ?? '') . ' ' . ($client->last_name ?? '')));
						if ($label === '') {
							$label = trim((string) ($client->company_name ?? 'recipient'));
						}
						throw new \RuntimeException(
							'No real recipient email found for ' . $label . '. Please add a real email on the company/contact profile.'
						);
					}
				}

				$this->emailService->sendEmail(
					'emails.template',
					['content' => $message],
					$recipientEmail,
					$subject,
					$requestData['email_from'],
					$preparedSendPaths,
					$ccarray,
					$obj->id
				);
				$anySendSuccess = true;
				$lastSendSubject = $subject;
				$lastSendMessage = $message;

				Log::info('Compose email sent successfully', [
					'staff_id' => Auth::guard('admin')->id(),
					'email_log_id' => $obj->id,
					'crm_admin_id' => $obj->client_id,
					'recipient_admin_or_agent_id' => $l,
					'recipient_email' => $recipientEmail,
					'mail_type' => $requestData['type'] ?? 'client',
				]);
			} catch (\Exception $e) {
				if (! $anySendSuccess) {
					$obj->update([
						'delivery_status' => 'send_failed',
						'status_reason'   => \Illuminate\Support\Str::limit($e->getMessage(), 500),
					]);
				}

				Log::error('Compose email send failed', [
					'staff_id' => Auth::guard('admin')->id(),
					'crm_admin_id' => $requestData['client_id'] ?? $requestData['lead_id'] ?? null,
					'error' => $e->getMessage(),
				]);
				if ($request->ajax() || $request->wantsJson()) {
					return response()->json([
						'status' => false,
						'success' => false,
						'message' => 'Failed to send email: ' . $e->getMessage(),
					], 422);
				}
				return redirect()->back()->with('error', 'Failed to send email: ' . $e->getMessage())->withInput();
			}
		}
		}

		if ($anySendSuccess) {
			try {
				$this->crmSentEmailS3Service->storeToS3(
					$obj,
					$lastSendSubject,
					$lastSendMessage,
					$attachmentTuplesForArchive
				);
			} catch (\Exception $s3Ex) {
				Log::warning('CRM sent email S3 storage failed (email still sent)', ['error' => $s3Ex->getMessage()]);
			}
			$this->recordChecklistEmailReminderIfRequested($requestData, $clientMatterId, $checklistWasSent);
			if ($request->ajax() || $request->wantsJson()) {
				return response()->json([
					'status' => true,
					'success' => true,
					'message' => 'Email sent successfully!',
				]);
			}
			return redirect()->back()->with('success', 'Email sent successfully!');
		}
        if(!$saved) {
            // Return JSON response for AJAX requests, redirect for regular form submissions
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => config('constants.server_error')
                ], 500);
            }
            return redirect()->back()->with('error', config('constants.server_error'));
        } else {
            // Return JSON response for AJAX requests, redirect for regular form submissions
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => 'Email Sent Successfully'
                ]);
            }
            return redirect()->back()->with('success', 'Email Sent Successfully');
        }
	}

	/**
	 * Log a visa-sheet email reminder when staff sent from the checklist reminder popup.
	 */
	private function recordChecklistEmailReminderIfRequested(array $requestData, int $clientMatterId, bool $checklistWasSent): void
	{
		if (($requestData['checklist_reminder_type'] ?? '') !== 'email') {
			return;
		}

		$matterId = (int) ($clientMatterId ?? 0);
		if ($matterId <= 0 || $checklistWasSent) {
			return;
		}

		$clientMatter = ClientMatter::query()->whereKey((int) $matterId)->first();
		if ($clientMatter) {
			$clientMatter->recordMatterReminder('email', Auth::user()->id);
		}
	}

	/**
	 * Resolve compose CC values: CRM Admin IDs or raw email addresses from Tom Select tags.
	 *
	 * @param  array<int|string>  $ccValues
	 * @return array<int, string>
	 */
	private function resolveComposeEmailCcAddresses(array $ccValues): array
	{
		$ccEmails = [];

		foreach ($ccValues as $ccValue) {
			$ccValue = trim((string) $ccValue);
			if ($ccValue === '') {
				continue;
			}

			if (filter_var($ccValue, FILTER_VALIDATE_EMAIL)) {
				$clean = Admin::sanitizeEmailAddress($ccValue);
				// Never CC internal company-lead placeholders
				if ($clean !== '' && ! Admin::isInternalPlaceholderEmail($clean)) {
					$ccEmails[] = $clean;
				}
				continue;
			}

			if (! ctype_digit($ccValue)) {
				continue;
			}

			$ccRow = Admin::query()->where('id', '=', (int) $ccValue, 'and')->first();
			if ($ccRow) {
				$em = $ccRow->resolveDeliverableEmail();
				if ($em) {
					$ccEmails[] = $em;
				}
			}
		}

		return array_values(array_unique($ccEmails));
	}

	/**
	 * Same visibility rules as EnsuresCrmRecordAccess::ensureCrmRecordAccess, with a compose-email-specific
	 * JSON message and logging so staff see a clear denial and support can trace failures.
	 */
	private function assertComposeEmailCanAccessCrmRecord(Request $request, int $adminId): void
	{
		if ($adminId <= 0) {
			return;
		}

		$user = Auth::guard('admin')->user();

		// Super Admin, Admin, and other exempt roles always may compose for any client/lead.
		if ($user && StaffClientVisibility::isExemptFromAllocation($user)) {
			return;
		}

		$row = Admin::query()
			->where('id', $adminId)
			->whereIn('type', ['client', 'lead'])
			->first(['id', 'type']);

		if (! $row) {
			return;
		}

		if (StaffClientVisibility::canAccessClientOrLead($adminId, $user)) {
			return;
		}

		Log::warning('Compose email denied: staff not authorized for CRM record', [
			'staff_id' => optional($user)->id,
			'crm_admin_id' => $adminId,
			'record_type' => $row->type,
			'ip' => $request->ip(),
			'user_agent' => substr((string) $request->userAgent(), 0, 500),
		]);

		$payload = [
			'status' => false,
			'success' => false,
			'message' => 'You are not authorized to send email.',
			'error_type' => 'compose_email_forbidden',
		];

		if ($request->expectsJson() || $request->ajax()) {
			throw new HttpResponseException(response()->json($payload, 403));
		}

		abort(403, 'You are not authorized to send email.');
	}

		public function getpartnerajax(Request $request){
	    // Partner functionality removed - no partners available
	    $agents = array();
		echo json_encode($agents);
	}

	public function getassigneeajax(Request $request){
	    Log::info('📋 getassigneeajax called', [
	        'search' => $request->likevalue,
	        'user_id' => Auth::id(),
	    ]);
	    
	    try {
	        $squery = $request->likevalue;
	        
	        $query = \App\Models\Staff::query()
                ->where('status', 1);  // Only active staff
                
            // Apply search filter if provided
            if (!empty($squery)) {
                $query->where(function($q) use ($squery) {
                    $squeryLower = strtolower($squery);
                    $q->whereRaw('LOWER(email) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$squeryLower.'%'])
                      ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$squeryLower.'%']);
                });
            }
            
            $fetchedData = $query->orderBy('first_name')->orderBy('last_name')->get();

    		$agents = array();
    		foreach($fetchedData as $list){
    			$agents[] = array(
    				'id' => $list->id,
    				'agent_id' => $list->first_name.' '.$list->last_name,
    				'assignee' => $list->first_name.' '.$list->last_name,
    			);
    		}
    
    		Log::info('✅ getassigneeajax success', [
    		    'count' => count($agents),
    		    'sample' => array_slice($agents, 0, 3),
    		]);
    
    		return response()->json($agents);
	    } catch (\Exception $e) {
	        Log::error('❌ getassigneeajax failed', [
	            'error' => $e->getMessage(),
	            'trace' => $e->getTraceAsString(),
	        ]);
	        
	        return response()->json([
	            'error' => 'Failed to load staff list',
	            'message' => $e->getMessage(),
	        ], 500);
	    }
	}


    public function checkclientexist(Request $request){
        if($request->type == 'email'){
         $clientexists = \App\Models\Admin::query()->where('email', '=', $request->vl, 'and')->whereIn('type', ['client', 'lead'], 'and', false)->exists();
            if($clientexists){
                echo 1;
            }else{
                echo 0;
            }
        }else if($request->type == 'clientid'){
         $clientexists = \App\Models\Admin::query()->where('client_id', '=', $request->vl, 'and')->whereIn('type', ['client', 'lead'], 'and', false)->exists();
            if($clientexists){
                echo 1;
            }else{
                echo 0;
            }
        }else{
            $clientexists = \App\Models\Admin::query()->where('phone', '=', $request->vl, 'and')->whereIn('type', ['client', 'lead'], 'and', false)->exists();
            if($clientexists){
                echo 1;
            }else{
                echo 0;
            }
        }
    }

	public function allnotification(Request $request){
		$lists = \App\Models\Notification::query()
            ->where('receiver_id', '=', (int) Auth::user()->id, 'and')
            ->orderby('created_at','DESC')
            ->paginate(20);
		// Fix URLs for notifications that point to non-existent or wrong routes
		$lists->getCollection()->transform(function ($notification) {
			// Message notifications: /messages (404) -> client detail + client portal tab
			if ($notification->notification_type === 'message' && ($notification->url === '/messages' || str_starts_with($notification->url ?? '', '/messages'))) {
				$clientMatter = DB::table('client_matters')->where('id', $notification->module_id)->first();
				if ($clientMatter) {
					$path = '/clients/detail/' . base64_encode(convert_uuencode($clientMatter->client_id));
					if (!empty($clientMatter->client_unique_matter_no)) {
						$path .= '/' . $clientMatter->client_unique_matter_no;
					}
					$notification->url = url($path . '/client_portal');
				}
			}
			// Broadcast notifications: /broadcasts/{uuid} (404) -> manage page with batch param
			if ($notification->notification_type === 'broadcast' && str_starts_with($notification->url ?? '', '/broadcasts/')) {
				$batchUuid = \Illuminate\Support\Str::afterLast($notification->url ?? '', '/');
				if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $batchUuid)) {
					$notification->url = url('/notifications/broadcasts/manage?batch=' . urlencode($batchUuid));
				}
			}
			return $notification;
		});
		return view('crm.notifications', compact(['lists']));
	}

    // Dashboard methods moved to DashboardController

	//Get matter templates
	public function getmattertemplates(Request $request){
		$id = $request->id;
		$template = \App\Models\EmailTemplate::query()->whereKey((int) $id)->first();
		if ($template) {
			echo json_encode(array('subject' => $template->subject, 'description' => $template->description));
		} else {
			echo json_encode(array('subject' => '', 'description' => ''));
		}
	}

    // Column preferences method moved to DashboardController

    /**
     * Get office visit notifications (optimized - no N+1 queries)
     */
    public function fetchOfficeVisitNotifications(Request $request)
    {
        // Fetch notifications with sender relationship eager loaded
        $notifications = \App\Models\Notification::with(['sender:id,first_name,last_name'])
            ->where('receiver_id', '=', (int) Auth::id(), 'and')
            ->where('notification_type', '=', 'officevisit', 'and')
            ->where('receiver_status', '=', 0, 'and')
            ->orderBy('created_at', 'DESC')
            ->get();

        if ($notifications->isEmpty()) {
            return response()->json(['notifications' => [], 'count' => 0]);
        }

        // Batch-load check-in logs (eliminates N+1)
        $checkinLogIds = $notifications->pluck('module_id')->filter()->unique();
        $receptionUserId = (int) config('constants.reception_user_id', 36730);
        $viewerIsReception = (int) Auth::id() === $receptionUserId;

        $checkinQuery = \App\Models\CheckinLog::query()->whereIn('id', $checkinLogIds->all(), 'and', false);
        if (!$viewerIsReception) {
            $checkinQuery->where('status', '=', 0, 'and');
        } else {
            // Reception: include attending (e.g. after "Pls Send") so poll/fetch can still show unread alerts
            $checkinQuery->whereIn('status', [0, 2], 'and', false);
        }
        $checkinLogs = $checkinQuery->get()->keyBy('id');

        if ($checkinLogs->isEmpty()) {
            return response()->json(['notifications' => [], 'count' => 0]);
        }

        $clientIds = $checkinLogs->pluck('client_id')->filter()->unique()->values();
        $contacts = $clientIds->isNotEmpty()
            ? \App\Models\Admin::query()
                ->whereIn('type', ['client', 'lead'], 'and', false)
                ->whereIn('id', $clientIds->all(), 'and', false)
                ->get()
                ->keyBy('id')
            : collect();

        // Build response data
        $data = [];
        foreach ($notifications as $notification) {
            $checkinLog = $checkinLogs->get($notification->module_id);
            
            if (!$checkinLog) {
                continue;
            }

            $preloaded = $checkinLog->client_id ? $contacts->get($checkinLog->client_id) : null;

            $isReceptionAlert = $viewerIsReception
                && ((int) $checkinLog->wait_type === 1 || (int) $checkinLog->status === 2);

            $data[] = [
                'id' => $notification->id,
                'checkin_id' => $checkinLog->id,
                'is_reception_alert' => $isReceptionAlert,
                'message' => $notification->message,
                'sender_name' => $notification->sender 
                    ? $notification->sender->first_name . ' ' . $notification->sender->last_name 
                    : 'System',
                'client_name' => $checkinLog->contactDisplayLabel($preloaded),
                'visit_purpose' => $checkinLog->visit_purpose,
                'created_at' => $notification->created_at->format('d/m/Y h:i A'),
                'url' => $notification->url
            ];
        }

        return response()->json(['notifications' => $data, 'count' => count($data)]);
    }

    /**
     * Get in-person waiting count
     */
    public function fetchInPersonWaitingCount(Request $request)
    {
        $InPersonwaitingCount = \App\Models\CheckinLog::inPersonWaitingCountForViewer();

        return response()->json(['InPersonwaitingCount' => $InPersonwaitingCount]);
    }

    /**
     * Get total activity count
     */
    public function fetchTotalActivityCount(Request $request)
    {
        if (Auth::user()->role == 1) {
            $assigneesCount = \App\Models\Note::query()
                ->where('type', '=', 'client', 'and')
                ->whereNotNull('client_id')
                ->where('is_action', '=', 1, 'and')
                ->where('status', '=', 0, 'and')
                ->count();
        } else {
            $assigneesCount = \App\Models\Note::query()
                ->where('assigned_to', '=', (int) Auth::user()->id, 'and')
                ->where('type', '=', 'client', 'and')
                ->where('is_action', '=', 1, 'and')
                ->where('status', '=', 0, 'and')
                ->count();
        }
        
        return response()->json(['assigneesCount' => $assigneesCount]);
    }

    /**
     * Get visa expiry messages
     */
    public function fetchvisaexpirymessages(Request $request)
    {
        $this->validate($request, [
            'client_id' => 'required|integer'
        ]);

        $this->ensureCrmRecordAccess((int) $request->client_id);

        $visaInfo = \App\Models\ClientVisaCountry::query()
            ->where('client_id', '=', (int) $request->client_id, 'and')
            ->latest('id')
            ->first();

        if (!$visaInfo || !$visaInfo->visa_expiry_date) {
            return '';
        }

        $visaExpiredAt = \Carbon\Carbon::parse($visaInfo->visa_expiry_date);
        $today = \Carbon\Carbon::now();
        $sevenDaysFromNow = \Carbon\Carbon::now()->addDays(7);

        if ($visaExpiredAt->lt($today)) {
            return "Your visa has expired. Please contact us immediately.";
        } elseif ($visaExpiredAt->gte($today) && $visaExpiredAt->lte($sevenDaysFromNow)) {
            $daysRemaining = $visaExpiredAt->diffInDays($today);
            return "Your visa is expiring in next $daysRemaining day" . ($daysRemaining == 1 ? '' : 's');
        }

        return '';
    }

    /**
     * Mark notification as seen
     */
    public function markNotificationSeen(Request $request)
    {
        $notification = \App\Models\Notification::query()->whereKey((int) $request->notification_id)->first();
        
        if (!$notification || $notification->receiver_id != Auth::id()) {
            return response()->json(['status' => 'error']);
        }

        $notification->receiver_status = 1;
        $notification->save();

        \App\Support\CachedHeaderCounts::forgetNotificationUnread((int) Auth::id());

        return response()->json(['status' => 'success']);
    }
}