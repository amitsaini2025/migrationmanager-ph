<?php

namespace App\Traits;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\CheckinLog;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientExperience;
use App\Models\ClientMatter;
use App\Models\ClientOccupation;
use App\Models\ClientPassportInformation;
use App\Models\ClientPoint;
use App\Models\ClientQualification;
use App\Models\ClientRelationship;
use App\Models\ClientSpouseDetail;
use App\Models\ClientTestScore;
use App\Models\ClientTravelInformation;
use App\Models\ClientVisaCountry;
use App\Models\Company;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\Matter;
use App\Models\Note;
use App\Models\Notification;
use App\Models\SmsTemplate;
use App\Models\Staff;
use App\Models\Tag;
use App\Services\ClientEditService;
use App\Services\ClientExportService;
use App\Services\ClientImportService;
use App\Services\ClientLeadListExportService;
use App\Services\EmailLogListService;
use App\Services\FCMService;
use App\Services\LegalCrm\LegalCrmApiClient;
use App\Services\MatterEmailBodyCleanupService;
use App\Services\Sms\UnifiedSmsManager;
use App\Support\ActionTaskGroup;
use App\Support\ClientDetailAccountTab;
use App\Support\ClientDetailChecklistsTab;
use App\Support\ClientDetailTabs;
use App\Support\NoteDescriptionHtml;
use App\Support\StaffClientVisibility;
use App\Support\WorkflowAssignment;
use App\Support\WorkflowStageChecklistSync;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ClientsController CRM list, detail, search, activity, email, merge, and action methods.
 *
 * @mixin \Illuminate\Routing\Controller
 */
trait ClientCrmFollowups
{
    public function activities(Request $request)
    {
        // Bypass all output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Start fresh output buffer
        ob_start();

        // Force error reporting off
        @ini_set('display_errors', '0');
        @error_reporting(0);

        // Initialize response with default error state
        $response = [
            'status' => false,
            'message' => 'An error occurred while fetching activities',
        ];

        try {
            // Validate request has id parameter
            if (! $request->has('id') || empty($request->id)) {
                $response['message'] = 'Client ID is required';
                header('Content-Type: application/json');
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            // Check if client exists - role must be integer for PostgreSQL compatibility
            $clientExists = Admin::whereIn('type', ['client', 'lead'])->where('id', $request->id)->exists();

            if ($clientExists && ! StaffClientVisibility::canAccessClientOrLead((int) $request->id, Auth::user())) {
                $response['message'] = config('constants.unauthorized');
                header('Content-Type: application/json');
                echo json_encode($response);
                ob_end_flush();
                exit;
            }

            if ($clientExists) {
                $perPage = min(max((int) $request->input('per_page', 40), 1), 100);
                $page = max((int) $request->input('page', 1), 1);
                $staffSearch = trim((string) ($request->input('staff', $request->input('user', ''))));
                $keywordSearch = trim((string) $request->input('keyword', ''));

                $query = ActivitiesLog::where('client_id', $request->id)
                    ->with('staff');

                if ($staffSearch !== '') {
                    $query->whereHas('staff', function ($staffQuery) use ($staffSearch) {
                        $staffSearchLower = strtolower($staffSearch);
                        $staffQuery->whereRaw('LOWER(first_name) LIKE ?', ['%'.$staffSearchLower.'%']);
                    });
                }

                if ($keywordSearch !== '') {
                    $query->where(function ($keywordQuery) use ($keywordSearch) {
                        $keywordQuery->where('description', 'like', '%'.$keywordSearch.'%')
                            ->orWhere('subject', 'like', '%'.$keywordSearch.'%');
                    });
                }

                $activities = (clone $query)
                    ->orderby('created_at', 'DESC')
                    ->skip(($page - 1) * $perPage)
                    ->take($perPage + 1)
                    ->get();

                $hasMore = $activities->count() > $perPage;
                if ($hasMore) {
                    $activities = $activities->take($perPage);
                }

                $data = [];

                foreach ($activities as $activit) {
                    $admin = $activit->staff;
                    $fullName = $admin ? trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')) : 'Unknown';
                    if (empty(trim($fullName))) {
                        $fullName = $admin ? $admin->first_name : 'Unknown';
                    }
                    $subjectWithoutStaffPrefix = ActivitiesLog::displaySubjectWithoutStaffPrefix(
                        $activit->activity_type ?? null,
                        $activit->subject ?? null
                    );
                    $data[] = [
                        'activity_id' => $activit->id,
                        'subject' => $activit->subject ?? '',
                        'subject_without_staff_prefix' => $subjectWithoutStaffPrefix,
                        'createdname' => $admin ? substr($admin->first_name, 0, 1) : '?',
                        'name' => $fullName,
                        'message' => NoteDescriptionHtml::forDisplay($activit->description ?? ''),
                        'date' => date('d M Y, H:i A', strtotime($activit->created_at)),
                        'created_at_ymd' => $activit->created_at ? Carbon::parse($activit->created_at)->format('Y-m-d') : '',
                        'followup_date' => ActivitiesLog::formatFollowupDateForDisplay($activit->followup_date),
                        'task_group' => $activit->task_group ?? '',
                        'pin' => $activit->pin ?? 0,
                        'activity_type' => $activit->activity_type,
                    ];
                }

                $response['status'] = true;
                $response['data'] = $data;
                $response['page'] = $page;
                $response['per_page'] = $perPage;
                $response['has_more'] = $hasMore;
                unset($response['message']); // Remove error message on success
            } else {
                $response['status'] = false;
                $response['message'] = 'Client not found';
            }
        } catch (\Exception $e) {
            Log::error('Error fetching activities (Exception): '.$e->getMessage(), [
                'client_id' => $request->id ?? 'N/A',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response['status'] = false;
            $response['message'] = 'Exception: '.$e->getMessage();
        } catch (\Throwable $e) {
            // Catch fatal errors
            Log::error('Fatal error fetching activities (Throwable): '.$e->getMessage(), [
                'client_id' => $request->id ?? 'N/A',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response['status'] = false;
            $response['message'] = 'Fatal: '.$e->getMessage();
        }

        // Ensure JSON response is always returned
        header('Content-Type: application/json');
        $jsonOutput = json_encode($response);
        echo $jsonOutput;
        ob_end_flush();
        exit;
    }

    public function updateclientstatus(Request $request)
    {
        if (Admin::whereIn('type', ['client', 'lead'])->where('id', $request->id)->exists()) {
            if (! StaffClientVisibility::canAccessClientOrLead((int) $request->id, Auth::user())) {
                $response['status'] = false;
                $response['message'] = config('constants.unauthorized');
                echo json_encode($response);

                return;
            }
            // rating column dropped Phase 4 - no-op
            $response['status'] = true;
            $response['message'] = 'You\'ve successfully updated your client\'s information.';
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        echo json_encode($response);
    }

    public function uploadmail(Request $request)
    {
        $requestData = $request->all();
        $obj = new EmailLog;
        $obj->user_id = Auth::user()->id;
        $obj->from_mail = $requestData['from'];
        $obj->to_mail = $requestData['to'];
        $obj->subject = $requestData['subject'];
        $obj->message = $requestData['message'];
        $obj->mail_type = 1;
        $obj->client_id = @$requestData['client_id'];
        $saved = $obj->save();
        if (! $saved) {
            return redirect()->back()->with('error', config('constants.server_error'));
        } else {
            return redirect()->back()->with('success', 'Email uploaded Successfully');
        }
    }

    /*public function merge_records(Request $request){
        if(isset($request->merge_record_ids) && $request->merge_record_ids != ""){
            if( strpos($request->merge_record_ids, ',') !== false ) {
                $merge_record_ids_arr = explode(",",$request->merge_record_ids);
                //echo "<pre>";print_r($merge_record_ids_arr);

                //check 1st and 2nd record
                $first_record = Admin::where('id', $merge_record_ids_arr[0])->select('id','phone','email')->first();
                //echo "<pre>";print_r($first_record);
                if(!empty($first_record)){
                    $first_phone = $first_record['phone'];
                    $first_email = $first_record['email'];
                }

                $second_record = Admin::where('id', $merge_record_ids_arr[1])->select('id','phone','email')->first();
                //echo "<pre>";print_r($second_record);
                if(!empty($second_record)){
                    $second_phone = $second_record['phone'];
                    $second_email = $second_record['email'];
                }

               DB::table('admins')
                ->where('id', $merge_record_ids_arr[0])
                ->update(['phone' => $second_phone,'email' => $second_email]);

                DB::table('admins')
                ->where('id', $merge_record_ids_arr[1])
                ->update(['phone' => $first_phone,'email' => $first_email]);

                $notelist1 = Note::where('client_id', $merge_record_ids_arr[0])->whereNull('assigned_to')->where('type', 'client')->orderby('pin', 'DESC')->orderBy('created_at', 'DESC')->get();
                //dd($notelist1);

                $notelist2 = Note::where('client_id', $merge_record_ids_arr[1])->whereNull('assigned_to')->where('type', 'client')->orderby('pin', 'DESC')->orderBy('created_at', 'DESC')->get();
                //dd($notelist2);

                if(!empty($notelist2)){
                    foreach($notelist2 as $key2=>$list2){
                        $obj1 = new \App\Models\Note;
                        $obj1->user_id = $list2->user_id;
                        $obj1->client_id = $merge_record_ids_arr[0];
                        $obj1->lead_id = $list2->lead_id;
                        $obj1->title = $list2->title;
                        $obj1->description = $list2->description;
                        $obj1->mail_id = $list2->mail_id;
                        $obj1->type = $list2->type;
                        $obj1->pin = $list2->pin;
                        $obj1->action_date = $list2->action_date;
                        $obj1->is_action = $list2->is_action;
                        $obj1->assigned_to = $list2->assigned_to;
                        $obj1->status = $list2->status;
                        $obj1->task_group = $list2->task_group;
                        $saved1 = $obj1->save();
                    }
                }

                if(!empty($notelist1)){
                    foreach($notelist1 as $key1=>$list1){
                        $obj2 = new \App\Models\Note;
                        $obj2->user_id = $list1->user_id;
                        $obj2->client_id = $merge_record_ids_arr[1];
                        $obj2->lead_id = $list1->lead_id;
                        $obj2->title = $list1->title;
                        $obj2->description = $list1->description;
                        $obj2->mail_id = $list1->mail_id;
                        $obj2->type = $list1->type;
                        $obj2->pin = $list1->pin;
                        $obj2->action_date = $list1->action_date;
                        $obj2->is_action = $list1->is_action;
                        $obj2->assigned_to = $list1->assigned_to;
                        $obj2->status = $list1->status;
                        $obj2->task_group = $list1->task_group;
                        $saved2 = $obj2->save();
                    }
                }

                if($saved2){
                    $response['status'] 	= 	true;
                    $response['message']	=	'You have successfully merged records.';
                }else{
                    $response['status'] 	= 	false;
                    $response['message']	=	'Please try again';
                }
                echo json_encode($response);
            }
        }
    }*/

    public function merge_records(Request $request)
    {
        $response = [];
        $response['status'] = false;
        $response['message'] = 'Please try again';

        $mergeFrom = (int) $request->input('merge_from');
        $mergeInto = (int) $request->input('merge_into');

        if ($mergeFrom <= 0 || $mergeInto <= 0 || $mergeFrom === $mergeInto) {
            $response['message'] = 'Please select two different records to merge.';

            return response()->json($response);
        }

        if (
            ! StaffClientVisibility::canAccessClientOrLead($mergeFrom, Auth::user())
            || ! StaffClientVisibility::canAccessClientOrLead($mergeInto, Auth::user())
        ) {
            return response()->json(StaffClientVisibility::unauthorizedPayload());
        }

        $fromRecord = Admin::query()->where('id', $mergeFrom)->whereNull('is_deleted')->first();
        $intoRecord = Admin::query()->where('id', $mergeInto)->whereNull('is_deleted')->first();
        if (! $fromRecord || ! $intoRecord) {
            $response['message'] = 'One or both records were not found.';

            return response()->json($response);
        }

        if (
            (isset($request->merge_from) && $request->merge_from != '')
            && (isset($request->merge_into) && $request->merge_into != '')
        ) {
            DB::beginTransaction();

            try {
                $this->mergeClientRecords->move($mergeFrom, $mergeInto);

                // Survivor is merge_into; retire merge_from (the record being merged away).
                DB::table('admins')->where('id', $mergeFrom)->update(['is_deleted' => 1]);

                DB::commit();
                $response['status'] = true;
                $response['message'] = 'You have successfully merged records.';
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('merge_records failed', [
                    'merge_from' => $mergeFrom,
                    'merge_into' => $mergeInto,
                    'error' => $e->getMessage(),
                ]);
                $response['status'] = false;
                $response['message'] = 'Merge failed. Please try again.';
            }
        }

        return response()->json($response);
    }

    public function searchMergeRecords(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'exclude_id' => ['required', 'integer'],
            'type' => ['nullable', Rule::in(['lead', 'client'])],
        ]);

        $excludeId = (int) $validated['exclude_id'];
        $term = '%'.$validated['q'].'%';
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        if (! StaffClientVisibility::canAccessClientOrLead($excludeId, Auth::user())) {
            return response()->json(StaffClientVisibility::unauthorizedPayload(), 403);
        }

        $applySearch = function ($builder) use ($likeOperator, $term, $excludeId) {
            return $builder
                ->whereNull('is_deleted')
                ->where(function ($q) {
                    $q->where('is_archived', 0)->orWhereNull('is_archived');
                })
                ->where('id', '!=', $excludeId)
                ->where(function ($q) use ($likeOperator, $term) {
                    $q->where('phone', $likeOperator, $term)
                        ->orWhere('email', $likeOperator, $term)
                        ->orWhere('first_name', $likeOperator, $term)
                        ->orWhere('last_name', $likeOperator, $term)
                        ->orWhere('client_id', $likeOperator, $term);
                    if (DB::getDriverName() === 'pgsql') {
                        $q->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", [$term]);
                    } else {
                        $q->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$term]);
                    }
                })
                ->select('id', 'first_name', 'last_name', 'email', 'phone', 'client_id', 'type')
                ->orderByDesc('id')
                ->limit(20);
        };

        $leadBuilder = $applySearch(Admin::query()->where('type', 'lead'));
        StaffClientVisibility::restrictLeadListQuery($leadBuilder);

        $clientBuilder = $applySearch(Admin::query()->where('type', 'client'));
        StaffClientVisibility::restrictAdminEloquentQuery($clientBuilder);

        $mapRecord = function (Admin $person) {
            $fullName = trim($person->first_name.' '.$person->last_name);
            $type = $person->type === 'client' ? 'client' : 'lead';

            return [
                'id' => $person->id,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'name' => $fullName,
                'email' => $person->email,
                'phone' => $person->phone,
                'client_id' => $person->client_id,
                'type' => $type,
                'type_label' => $type === 'client' ? 'Client' : 'Lead',
                'label' => $fullName.' ('.$person->client_id.')',
            ];
        };

        $results = $leadBuilder->get()
            ->concat($clientBuilder->get())
            ->sortByDesc('id')
            ->take(20)
            ->values()
            ->map($mapRecord);

        return response()->json(['results' => $results]);
    }

    // address_auto_populate
    public function address_auto_populate(Request $request)
    {
        $address = $request->address;
        if (isset($address) && $address != '') {
            $result = app('geocoder')->geocode($address)->get(); // dd($result[0]);
            $postalCode = $result[0]->getPostalCode();
            $locality = $result[0]->getLocality();
            if (! empty($result)) {
                $response['status'] = 1;
                $response['postal_code'] = $postalCode;
                $response['locality'] = $locality;
                $response['message'] = 'address is success.';
            } else {
                $response['status'] = 0;
                $response['postal_code'] = '';
                $response['locality'] = '';
                $response['message'] = 'address is wrong.';
            }
            echo json_encode($response);
        }
    }

    // not picked call button click
    public function notpickedcall(Request $request)
    {
        $data = $request->all(); // dd($data);
        $response = [
            'status' => false,
            'message' => 'Please try again',
            'not_picked_call' => $data['not_picked_call'] ?? null,
        ];
        // Get client phone and send message via UnifiedSmsManager
        $clientInfo = Admin::select('id', 'country_code', 'phone')->where('id', $data['id'])->first(); // dd($clientInfo);

        $smsResult = null;
        if ($clientInfo) {
            $message = $data['message'];
            $clientPhone = $clientInfo->country_code.''.$clientInfo->phone;

            // Use UnifiedSmsManager with proper context (auto-creates activity log)
            $smsResult = $this->smsManager->sendSms($clientPhone, $message, 'notification', [
                'client_id' => $data['id'],
            ]);
        }

        $recExist = Admin::where('id', $data['id'])->update(['not_picked_call' => $data['not_picked_call']]);
        if ($recExist) {
            if ($data['not_picked_call'] == 1) { // if checked true
                // Activity log is now automatically created by UnifiedSmsManager
                // No need to manually create it here

                $response['status'] = true;
                $response['message'] = $smsResult && $smsResult['success']
                    ? 'Call not picked. SMS sent successfully!'
                    : 'Call not picked. SMS failed to send.';
                $response['not_picked_call'] = $data['not_picked_call'];
            } elseif ($data['not_picked_call'] == 0) {
                $response['status'] = true;
                $response['message'] = 'You have updated call not picked bit. Please try again';
                $response['not_picked_call'] = $data['not_picked_call'];
            }
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
            $response['not_picked_call'] = $data['not_picked_call'];
        }
        echo json_encode($response);
    }

    public function deleteactivitylog(Request $request)
    {
        $activitylogid = $request->activitylogid; // dd($activitylogid);
        if (ActivitiesLog::where('id', $activitylogid)->exists()) {
            $data = ActivitiesLog::select('client_id', 'subject', 'description')->where('id', $activitylogid)->first();
            $res = DB::table('activities_logs')->where('id', @$activitylogid)->delete();
            if ($res) {
                $response['status'] = true;
                $response['data'] = $data;
            } else {
                $response['status'] = false;
                $response['message'] = 'Please try again';
            }
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        echo json_encode($response);
    }

    public function pinactivitylog(Request $request)
    {
        $requestData = $request->all();
        if (ActivitiesLog::where('id', $requestData['activity_id'])->exists()) {
            $activity = ActivitiesLog::where('id', $requestData['activity_id'])->first();
            if ($activity->pin == 0) {
                $obj = ActivitiesLog::find($activity->id);
                $obj->pin = 1;
                $saved = $obj->save();
            } else {
                $obj = ActivitiesLog::find($activity->id);
                $obj->pin = 0;
                $saved = $obj->save();
            }
            $response['status'] = true;
            $response['message'] = 'Pin Option added successfully';
        } else {
            $response['status'] = false;
            $response['message'] = 'Record not found';
        }
        echo json_encode($response);
    }

    // Fetch all contact list of any client at create note popup

    // Re-assign inbox email
    public function reassiginboxemail(Request $request)
    {
        $requestData = $request->all(); // dd($requestData);
        $uploaded_doc_id = $requestData['uploaded_doc_id'];
        if (Document::where('id', '=', $uploaded_doc_id)->exists()) {
            // Get existing document info
            $document_info = Document::select('id', 'file_name', 'filetype', 'myfile', 'client_id')->where('id', '=', $uploaded_doc_id)->first();
            $source_doc_client_id = $document_info['client_id'];
            $source_doc_myfile = $document_info['myfile'];

            $source_doc_admin_info = Admin::select('client_id')->where('id', '=', $source_doc_client_id)->first();
            $source_doc_client_unique_id = $source_doc_admin_info['client_id'];

            $dest_assign_client_id = $requestData['reassign_client_id'];
            $dest_doc_admin_info = Admin::select('client_id')->where('id', '=', $dest_assign_client_id)->first();
            $dest_doc_client_unique_id = $dest_doc_admin_info['client_id'];

            // Define the source and destination paths
            $sourcePath = $source_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your source file path
            $destinationPath = $dest_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your destination file path

            try {
                // Check if the file exists before copying
                if (Storage::disk('s3')->exists($sourcePath)) {
                    // Use the copy method to copy the file within S3
                    Storage::disk('s3')->copy($sourcePath, $destinationPath);
                    Storage::disk('s3')->delete($sourcePath);
                    // echo "File copied successfully.";
                } else {
                    // echo "Source file does not exist.";
                }
            } catch (\Exception $e) {
                // Handle errors here
                echo 'Error: '.$e->getMessage();
            }

            // Update document with client id and matter id
            $upd_doc_info = Document::find($uploaded_doc_id);
            $upd_doc_info->client_id = $requestData['reassign_client_id'];
            $upd_doc_info->user_id = Auth::user()->id;
            $upd_doc_info->client_matter_id = $requestData['reassign_client_matter_id'];
            $saved_doc_info = $upd_doc_info->save();
            if ($saved_doc_info) {
                // Update email_logs table with client id and matter id
                $id = $requestData['memail_id'];
                $email_log_info = EmailLog::find($id);
                $email_log_info->client_id = $requestData['reassign_client_id'];
                $email_log_info->user_id = Auth::user()->id;
                $email_log_info->client_matter_id = $requestData['reassign_client_matter_id'];
                $saved_mail_report_info = $email_log_info->save();
                if ($saved_mail_report_info) {
                    $client_matter_info = ClientMatter::select('client_unique_matter_no')->where('id', '=', $requestData['reassign_client_matter_id'])->first();
                    $subject = 'Inbox Email Re-assign';
                    $objs = new ActivitiesLog;
                    $objs->client_id = $requestData['reassign_client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = $dest_doc_client_unique_id.'-'.$client_matter_info['client_unique_matter_no'];
                    $objs->subject = $subject;
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
                }

                // Update date in client matter table
                if (isset($requestData['reassign_client_matter_id']) && $requestData['reassign_client_matter_id'] != '') {
                    $obj1 = ClientMatter::find($requestData['reassign_client_matter_id']);
                    $obj1->updated_at = date('Y-m-d H:i:s');
                    $obj1->save();
                }
            }
            if (! $saved_mail_report_info) {
                return redirect()->back()->with('error', config('constants.server_error'));
            } else {
                return redirect()->back()->with('success', 'Inbox email re-assigned successfully');
            }
        } else {
            return redirect()->back()->with('error', config('constants.server_error'));
        }
    }

    // Re-assign sent email
    public function reassigsentemail(Request $request)
    {
        $requestData = $request->all(); // dd($requestData);
        $uploaded_doc_id = $requestData['uploaded_doc_id'];
        if (Document::where('id', '=', $uploaded_doc_id)->exists()) {
            // Get existing document info
            $document_info = Document::select('id', 'file_name', 'filetype', 'myfile', 'client_id')->where('id', '=', $uploaded_doc_id)->first();
            $source_doc_client_id = $document_info['client_id'];
            $source_doc_myfile = $document_info['myfile'];

            $source_doc_admin_info = Admin::select('client_id')->where('id', '=', $source_doc_client_id)->first();
            $source_doc_client_unique_id = $source_doc_admin_info['client_id'];

            $dest_assign_client_id = $requestData['reassign_sent_client_id'];
            $dest_doc_admin_info = Admin::select('client_id')->where('id', '=', $dest_assign_client_id)->first();
            $dest_doc_client_unique_id = $dest_doc_admin_info['client_id'];

            // Define the source and destination paths
            $sourcePath = $source_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your source file path
            $destinationPath = $dest_doc_client_unique_id.'/conversion_email_fetch/'.$requestData['mail_type'].'/'.$source_doc_myfile; // Replace with your destination file path

            try {
                // Check if the file exists before copying
                if (Storage::disk('s3')->exists($sourcePath)) {
                    // Use the copy method to copy the file within S3
                    Storage::disk('s3')->copy($sourcePath, $destinationPath);
                    Storage::disk('s3')->delete($sourcePath);
                    // echo "File copied successfully.";
                } else {
                    // echo "Source file does not exist.";
                }
            } catch (\Exception $e) {
                // Handle errors here
                echo 'Error: '.$e->getMessage();
            }

            // Update document with client id and matter id
            $upd_doc_info = Document::find($uploaded_doc_id);
            $upd_doc_info->client_id = $requestData['reassign_sent_client_id'];
            $upd_doc_info->user_id = Auth::user()->id;
            $upd_doc_info->client_matter_id = $requestData['reassign_sent_client_matter_id'];
            $saved_doc_info = $upd_doc_info->save();
            if ($saved_doc_info) {
                // Update email_logs table with client id and matter id
                $id = $requestData['memail_id'];
                $email_log_info = EmailLog::find($id);
                $email_log_info->client_id = $requestData['reassign_sent_client_id'];
                $email_log_info->user_id = Auth::user()->id;
                $email_log_info->client_matter_id = $requestData['reassign_sent_client_matter_id'];
                $saved_mail_report_info = $email_log_info->save();
                if ($saved_mail_report_info) {
                    $client_matter_info = ClientMatter::select('client_unique_matter_no')->where('id', '=', $requestData['reassign_sent_client_matter_id'])->first();
                    $subject = 'Sent Email Re-assign';
                    $objs = new ActivitiesLog;
                    $objs->client_id = $requestData['reassign_sent_client_id'];
                    $objs->created_by = Auth::user()->id;
                    $objs->description = $dest_doc_client_unique_id.'-'.$client_matter_info['client_unique_matter_no'];
                    $objs->subject = $subject;
                    $objs->task_status = 0;
                    $objs->pin = 0;
                    $objs->save();
                }

                // Update date in client matter table
                if (isset($requestData['reassign_sent_client_matter_id']) && $requestData['reassign_sent_client_matter_id'] != '') {
                    $obj1 = ClientMatter::find($requestData['reassign_sent_client_matter_id']);
                    $obj1->updated_at = date('Y-m-d H:i:s');
                    $obj1->save();
                }
            }
            if (! $saved_mail_report_info) {
                return redirect()->back()->with('error', config('constants.server_error'));
            } else {
                return redirect()->back()->with('success', 'Sent email re-assigned successfully');
            }
        } else {
            return redirect()->back()->with('error', config('constants.server_error'));
        }
    }

    // Fetch selected client all matters at assign email to client popup
    public function listAllMattersWRTSelClient(Request $request) // dd($request->all());
    {if (ClientMatter::where('client_id', $request->client_id)->exists()) {
        // Fetch All client matters
        $clientMatetrs = ClientMatter::join('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->select('client_matters.id', 'matters.title', 'client_matters.client_unique_matter_no')
            ->where('client_id', $request->client_id)
            ->get(); // dd($clientMatetrs);
        if (! empty($clientMatetrs) && count($clientMatetrs) > 0) {
            $response['status'] = true;
            $response['message'] = 'Client matter is successfully fetched.';
            $response['clientMatetrs'] = $clientMatetrs;
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
            $response['clientMatetrs'] = [];
        }
    } else {
        $response['status'] = false;
        $response['message'] = 'Please try again';
        $response['clientMatetrs'] = [];
    }
        echo json_encode($response);
    }

    public function checkEmail(Request $request)
    {
        $email = $request->input('email');

        // Check if email exists in the database
        $exists = DB::table('client_emails')->where('email', $email)->exists();

        $exists_admin = DB::table('admins')->where('email', $email)->exists();

        if ($exists || $exists_admin) {
            return response()->json(['status' => 'exists']);
        } else {
            return response()->json(['status' => 'available']);
        }
    }

    public function checkContact(Request $request)
    {
        $contact = $request->input('phone');

        // Check if the contact number exists in the client_contacts table
        $exists = DB::table('client_contacts')->where('phone', $contact)->exists();
        $exists_admin = DB::table('admins')->where('phone', $contact)->exists();

        if ($exists || $exists_admin) {
            return response()->json(['status' => 'exists']);
        } else {
            return response()->json(['status' => 'available']);
        }
    }

    // mail preview click update mail_is_read bit
    public function updatemailreadbit(Request $request) // dd($request->all());
    {if (EmailLog::where('id', $request->mail_report_id)->exists()) {
        $emailLogInfo = EmailLog::select('mail_is_read')->where('id', $request->mail_report_id)->first();
        // dd($emailLogInfo);
        if ($emailLogInfo) {
            $email_log_info = EmailLog::find($request->mail_report_id);
            $email_log_info->mail_is_read = 1;
            $email_log_info->save();

            $response['status'] = true;
            $response['message'] = 'Mail is successfully updated';
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
    } else {
        $response['status'] = false;
        $response['message'] = 'Please try again';
    }
        echo json_encode($response);
    }

    // chatgpt enhance message
    public function enhanceMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        try {
            $response = $this->openAiClient->post('chat/completions', [
                'json' => [
                    'model' => 'gpt-3.5-turbo', // or 'gpt-4' if you have access
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a professional email writer. Rewrite the following content in a more professional and polished manner:',
                        ],
                        [
                            'role' => 'user',
                            'content' => $request->message,
                        ],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            $enhancedMessage = $result['choices'][0]['message']['content'];

            return response()->json(['enhanced_message' => $enhancedMessage]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to enhance message: '.$e->getMessage()], 500);
        }
    }

    // Filter Inbox emails
    public function filterEmails(Request $request)
    {
        try {
            $client_id = $request->input('client_id');
            $client_matter_id = $request->input('client_matter_id');
            $status = $request->input('status');
            $search = $request->input('search');
            $label_id = $request->input('label_id');
            $sort = $request->input('sort', 'date');

            if (! $client_matter_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Matter ID is required',
                ], 400);
            }

            $listService = app(EmailLogListService::class);

            $query = EmailLog::where('client_matter_id', $client_matter_id)
                ->where('type', 'client')
                ->where('mail_type', 1)
                ->where('conversion_type', 'conversion_email_fetch')
                ->where('mail_body_type', 'inbox')
                ->with(['labels', 'attachments']);

            if ($status !== null && $status !== '') {
                if ($status == 1) {
                    $query->where('mail_is_read', 1);
                } elseif ($status == 2) {
                    $query->where(function ($q) {
                        $q->where('mail_is_read', 0)
                            ->orWhereNull('mail_is_read');
                    });
                }
            }

            if ($search !== null && $search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                        ->orWhere('message', 'LIKE', "%{$search}%")
                        ->orWhere('from_mail', 'LIKE', "%{$search}%")
                        ->orWhere('to_mail', 'LIKE', "%{$search}%");
                });
            }

            if (! empty($label_id)) {
                $query->whereHas('labels', function ($q) use ($label_id) {
                    $q->where('email_labels.id', $label_id);
                });
            }

            $query = $listService->applySort($query, $sort);
            $paginator = $listService->paginate($query, $request);

            return response()->json(
                $listService->buildPaginatedResponse($paginator, [
                    'client_id' => $client_id,
                    'default_mail_type' => 'inbox',
                    'recipient_type' => 'client',
                ]),
                200,
                [],
                EmailLogListService::API_JSON_FLAGS
            );
        } catch (\Exception $e) {
            Log::error('Error in filterEmails: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching emails: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Full email detail for reading pane, reply/forward, etc.
     */
    public function getEmailLogDetail($id)
    {
        try {
            $email = EmailLog::with(['labels', 'attachments'])->find($id);
            if (! $email) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email not found',
                ], 404);
            }

            $listService = app(EmailLogListService::class);

            return response()->json(
                $listService->mapForDetail($email, [
                    'client_id' => $email->client_id,
                    'default_mail_type' => $email->mail_body_type ?? 'inbox',
                    'recipient_type' => $email->type ?? 'client',
                    'admin_without_global_scopes' => ($email->type === 'lead'),
                ]),
                200,
                [],
                EmailLogListService::API_JSON_FLAGS
            );
        } catch (\Exception $e) {
            Log::error('Error in getEmailLogDetail: '.$e->getMessage(), [
                'email_log_id' => $id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching email details',
            ], 500);
        }
    }

    /**
     * Super admin only: archive all email bodies for a matter to S3, then clear DB body fields.
     */
    public function sendMatterEmailBodiesToS3(Request $request)
    {
        if ((int) (Auth::user()->role ?? 0) !== 1) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only super admin can perform this action.',
            ], 403);
        }

        $matterId = (int) $request->input('client_matter_id');
        if ($matterId <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'Matter ID is required.',
            ], 422);
        }

        $matter = ClientMatter::find($matterId);
        if (! $matter) {
            return response()->json([
                'status' => false,
                'message' => 'Client matter not found.',
            ], 404);
        }

        $service = app(MatterEmailBodyCleanupService::class);

        if (! $service->matterHasBodyContentInDatabase($matterId)) {
            return response()->json([
                'status' => false,
                'already_archived' => true,
                'message' => 'All emails are already moved to S3 from db.',
            ]);
        }

        try {
            $result = $service->sendAllBodiesToS3AndClearForMatter($matterId);

            return response()->json([
                'status' => true,
                'message' => 'Email bodies were sent to S3 and removed from the database for this matter.',
                'archived' => $result['archived'],
                'skipped' => $result['skipped'],
                'cleared' => $result['cleared'],
                'has_body_content' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send matter email bodies to S3', [
                'client_matter_id' => $matterId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send email bodies to S3: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream archived email body HTML from S3 (after super-admin S3 archive action).
     */
    public function viewArchivedEmailBody($id)
    {
        try {
            $emailLog = EmailLog::find($id);
            if (! $emailLog) {
                abort(404, 'Email not found.');
            }

            $service = app(MatterEmailBodyCleanupService::class);
            $s3Key = $service->resolveArchivedBodyS3Key($emailLog);

            if ($s3Key === null) {
                abort(404, 'Archived email body not found.');
            }

            $content = Storage::disk('s3')->get($s3Key);
            if ($content === null || $content === '') {
                abort(404, 'Archived email body is empty.');
            }

            if (Schema::hasColumn('email_logs', 'body_s3_key') && empty($emailLog->body_s3_key)) {
                $emailLog->body_s3_key = $s3Key;
                $emailLog->save();
            }

            return response($content, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Failed to view archived email body', [
                'email_log_id' => $id,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Failed to load archived email body.');
        }
    }

    /**
     * Delete an email log (email).
     * Allowed roles: configurable (config/crm.php / CRM_EMAIL_LOG_DELETE_ROLE_IDS); default 1, 12, 16.
     * Accepts DELETE or POST /email-logs/{id}/delete (POST recommended where DELETE is blocked).
     */
    public function deleteEmailLog(Request $request, $id)
    {
        $allowedRoles = config('crm.email_log_delete_role_ids', [1, 12, 16]);
        if (! is_array($allowedRoles) || count($allowedRoles) === 0) {
            $allowedRoles = [1, 12, 16];
        }
        $allowedRoles = array_map('intval', $allowedRoles);

        if (! in_array((int) Auth::user()->role, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Your role cannot delete emails.',
            ], 403);
        }

        try {
            $emailLog = EmailLog::find($id);
            if (! $emailLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email not found.',
                ], 404);
            }

            $matterId = $request->input('client_matter_id');
            $clientId = $request->input('client_id');
            if ($matterId !== null && $matterId !== '') {
                if ((int) $emailLog->client_matter_id !== (int) $matterId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email does not belong to the open matter.',
                    ], 403);
                }
            } elseif ($clientId !== null && $clientId !== '') {
                if ((int) $emailLog->client_id !== (int) $clientId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This email does not belong to the open client or lead.',
                    ], 403);
                }
            }

            $logClientId = (int) ($emailLog->client_id ?? 0);
            $logMatterId = $emailLog->client_matter_id;
            $attachmentCount = EmailLogAttachment::where('email_log_id', $id)->count();

            $matterRef = null;
            if (! empty($logMatterId)) {
                $matterRef = DB::table('client_matters')
                    ->where('id', $logMatterId)
                    ->value('client_unique_matter_no');
            }

            $snapshot = [
                'subject' => trim((string) ($emailLog->subject ?? '')),
                'from_mail' => (string) ($emailLog->from_mail ?? ''),
                'to_mail' => (string) ($emailLog->to_mail ?? ''),
                'cc' => $emailLog->cc ?? null,
                'mail_type' => $emailLog->mail_type ?? null,
                'record_type' => $emailLog->type ?? null,
            ];

            $staffId = Auth::user()->id ?? Auth::id();

            DB::transaction(function () use ($id, $logClientId, $logMatterId, $matterRef, $staffId, $attachmentCount, $snapshot) {
                DB::table('email_label_email_log')->where('email_log_id', $id)->delete();
                EmailLogAttachment::where('email_log_id', $id)->delete();

                $deletedRows = EmailLog::where('id', $id)->delete();
                if ($deletedRows !== 1) {
                    throw new \RuntimeException('Email log could not be deleted.');
                }

                if ($logClientId <= 0 || ! $staffId) {
                    if ($logClientId > 0 && ! $staffId) {
                        Log::warning('Email log deleted without activity log: no authenticated staff id', [
                            'email_log_id' => $id,
                            'client_id' => $logClientId,
                        ]);
                    }

                    return;
                }

                $description = $this->buildEmailLogDeletionActivityDescription(
                    (int) $id,
                    $snapshot,
                    $attachmentCount,
                    $matterRef !== null && $matterRef !== '' ? (string) $matterRef : null,
                    $logMatterId !== null ? (int) $logMatterId : null
                );

                $activityAttrs = [
                    'client_id' => $logClientId,
                    'created_by' => $staffId,
                    'subject' => 'Deleted email message',
                    'description' => $description,
                    'activity_type' => 'activity',
                    'task_status' => 0,
                    'pin' => 0,
                    'source' => 'crm_emails',
                ];
                if (! empty($logMatterId)) {
                    $activityAttrs['use_for'] = 'matter';
                }
                ActivitiesLog::create($activityAttrs);
            });

            return response()->json([
                'success' => true,
                'message' => 'Email deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting email log: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email: '.$e->getMessage(),
            ], 500);
        }
    }

    // Filter Sent emails
    public function filterSentEmails(Request $request)
    {
        try {
            $client_id = $request->input('client_id');
            $client_matter_id = $request->input('client_matter_id'); // NEW: Filter by matter
            $type = $request->input('type');
            $status = $request->input('status');
            $search = $request->input('search');
            $sort = $request->input('sort', 'date');

            // Validate input
            if (! $client_matter_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Matter ID is required',
                ], 400);
            }

            $listService = app(EmailLogListService::class);

            // Base query for sent mail - FILTER BY MATTER ID instead of client_id
            $query = EmailLog::where('client_matter_id', $client_matter_id)
                ->where('type', 'client')
                ->where('mail_type', 1)
                ->forCrmSentMailbox()
                ->with(['labels', 'attachments']);

            // Filter by type
            if ($type !== '') {
                if ($type == 1) {
                    // IMAP / uploaded fetched sent mail only
                    $query->where('conversion_type', 'conversion_email_fetch')
                        ->where('mail_body_type', 'sent');
                } elseif ($type == 2) {
                    // CRM compose + Account receipt/invoice sends
                    $query->where(function ($q) {
                        $q->whereNull('conversion_type')
                            ->orWhere(function ($sub) {
                                $sub->where('conversion_type', 'system_generated')
                                    ->whereIn('system_email_category', ['receipt', 'invoice']);
                            });
                    });
                }
            }

            // Filter by status
            if ($status !== '') {
                if ($status == 1) {
                    $query->where('mail_is_read', 1);
                } elseif ($status == 2) {
                    $query->where(function ($q) {
                        $q->where('mail_is_read', 0)
                            ->orWhereNull('mail_is_read');
                    });
                }
            }

            // Search filter
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                        ->orWhere('message', 'LIKE', "%{$search}%")
                        ->orWhere('from_mail', 'LIKE', "%{$search}%")
                        ->orWhere('to_mail', 'LIKE', "%{$search}%");
                });
            }

            $query = $listService->applySort($query, $sort);
            $paginator = $listService->paginate($query, $request);

            return response()->json(
                $listService->buildPaginatedResponse($paginator, [
                    'client_id' => $client_id,
                    'default_mail_type' => 'sent',
                    'recipient_type' => 'client',
                ]),
                200,
                [],
                EmailLogListService::API_JSON_FLAGS
            );
        } catch (\Exception $e) {
            Log::error('Error in filterSentEmails: '.$e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching emails',
            ], 500);
        }
    }

    /**
     * Filter emails for a lead (no matter context).
     * Returns CRM-sent emails to the lead.
     */
    public function filterLeadEmails(Request $request)
    {
        try {
            $client_id = $request->input('client_id'); // lead id
            $status = $request->input('status');
            $search = $request->input('search');
            $label_id = $request->input('label_id');
            $sort = $request->input('sort', 'date');

            if (! $client_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lead ID is required',
                ], 400);
            }

            $listService = app(EmailLogListService::class);

            $query = EmailLog::where('client_id', $client_id)
                ->where('type', 'lead')
                ->where('mail_type', 1)
                ->where(function ($q) {
                    $q->whereNull('conversion_type')
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('conversion_type', 'conversion_email_fetch')
                                ->where('mail_body_type', 'sent');
                        });
                })
                ->with(['labels', 'attachments']);

            if ($status !== null && $status !== '') {
                if ($status == 1) {
                    $query->where('mail_is_read', 1);
                } elseif ($status == 2) {
                    $query->where(function ($q) {
                        $q->where('mail_is_read', 0)->orWhereNull('mail_is_read');
                    });
                }
            }

            if ($search !== null && $search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'LIKE', "%{$search}%")
                        ->orWhere('message', 'LIKE', "%{$search}%")
                        ->orWhere('from_mail', 'LIKE', "%{$search}%")
                        ->orWhere('to_mail', 'LIKE', "%{$search}%");
                });
            }

            if (! empty($label_id)) {
                $query->whereHas('labels', function ($q) use ($label_id) {
                    $q->where('email_labels.id', $label_id);
                });
            }

            $query = $listService->applySort($query, $sort);
            $paginator = $listService->paginate($query, $request);

            return response()->json(
                $listService->buildPaginatedResponse($paginator, [
                    'client_id' => $client_id,
                    'default_mail_type' => 'sent',
                    'recipient_type' => 'lead',
                    'admin_without_global_scopes' => true,
                ]),
                200,
                [],
                EmailLogListService::API_JSON_FLAGS
            );
        } catch (\Exception $e) {
            Log::error('Error in filterLeadEmails: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching lead emails',
            ], 500);
        }
    }

    // Seach Client Relationship

    // OLD HTTP DOWNLOAD METHOD - COMMENTED OUT
    // public function download_document(Request $request)
    // {
    //     $fileUrl = $request->input('filelink');
    //     $filename = $request->input('filename', 'downloaded.pdf');

    //     if (!$fileUrl) {
    //         return abort(400, 'Missing file URL');
    //     }

    //     // Increase execution time for large files
    //     set_time_limit(900);

    //     // Increase HTTP client timeout
    //     $response = Http::timeout(120)->get($fileUrl);

    //     if (!$response->successful()) {
    //         return abort(404, 'File not found');
    //     }

    //     return response($response->body())
    //         ->header('Content-Type', 'application/pdf')
    //         ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    // }

    // Convert activity to note
    public function convertActivityToNote(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'activity_id' => 'required|integer',
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'note_type' => 'required|string',
            ]);

            // Get the activity details
            $activity = ActivitiesLog::find($request->activity_id);
            if (! $activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found',
                ]);
            }

            // Check if client matter exists
            $clientMatter = ClientMatter::find($request->client_matter_id);
            if (! $clientMatter) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client matter not found',
                ]);
            }

            // Create new note
            $note = new Note;
            $note->client_id = $request->client_id;
            $note->user_id = Auth::user()->id;
            $note->title = 'Matter Discussion';
            $note->description = $request->note_description; // Use processed description
            $note->matter_id = $request->client_matter_id;
            $note->type = 'client';
            $note->task_group = $request->note_type;
            $note->status = 1;

            $saved = $note->save();

            if ($saved) {
                // Create activity log for the conversion
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $request->client_id;
                $activityLog->created_by = Auth::user()->id;
                $activityLog->description = '<span class="text-semi-bold">Activity Converted to Note</span><p>Activity "'.$activity->subject.'" has been converted to a note.</p>';
                $activityLog->subject = 'converted activity to note';
                $activityLog->task_status = 0;
                $activityLog->pin = 0;
                $activityLog->save();

                // Update client matter timestamp
                $clientMatter->updated_at = date('Y-m-d H:i:s');
                $clientMatter->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Activity successfully converted to note',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save note',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error converting activity to note: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while converting activity to note',
            ]);
        }
    }

    // Get client matters for activity conversion
    public function getClientMatters($clientId)
    {
        try {
            $clientMatters = DB::table('client_matters')
                ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                ->select('client_matters.id', 'client_matters.client_unique_matter_no', 'matters.title', 'client_matters.sel_matter_id')
                ->where('client_matters.matter_status', 1)
                ->where('client_matters.client_id', $clientId)
                ->orderBy('client_matters.id', 'desc')
                ->get();

            $matters = [];
            foreach ($clientMatters as $matter) {
                // If sel_matter_id is 1 or title is null, use "General Matter"
                $matterName = 'General Matter';
                if ($matter->sel_matter_id != 1 && ! empty($matter->title)) {
                    $matterName = $matter->title;
                }

                $displayName = $matterName.' - '.$matter->client_unique_matter_no;
                $matters[] = [
                    'id' => $matter->id,
                    'display_name' => $displayName,
                    'client_unique_matter_no' => $matter->client_unique_matter_no,
                ];
            }

            return response()->json([
                'success' => true,
                'matters' => $matters,
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching client matters: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching client matters',
            ]);
        }
    }

    /**
     * Decode string helper method - consistent with other controllers
     *
     * @param  string|null  $string
     * @return string|false
     */
    public function decodeString($string = null)
    {
        if (empty($string)) {
            return false;
        }

        if (base64_encode(base64_decode($string, true)) === $string) {
            return convert_uudecode(base64_decode($string));
        }

        return false;
    }

    // Service Taken methods REMOVED - client_service_takens table does not exist
    // Model clientServiceTaken.php deleted, table was never created in database
    // Methods removed: createservicetaken(), removeservicetaken(), getservicetaken()
    // Routes removed from routes/clients.php
    // Modals removed from detail.blade.php and companies/detail.blade.php

    /**
     * Change client type (lead to client conversion)
     */
    public function changetype(Request $request, $id = null, $slug = null)
    {
        Log::info('ConvertLeadToClient: changetype called', ['id_raw' => $id, 'slug' => $slug, 'query' => $request->query()]);
        if (isset($id) && ! empty($id)) {
            $id = $this->decodeString($id);
            Log::info('ConvertLeadToClient: decoded id', ['decoded_id' => $id]);
            if (Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->exists()) {
                $obj = Admin::find($id);
                $client_type = $obj->type;
                Log::info('ConvertLeadToClient: admin found', ['admin_id' => $id, 'client_type' => $client_type]);
                if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                    return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
                }
                if ($slug == 'client') {
                    $formClientId = $request->input('client_id');
                    $formUserId = $request->input('user_id');
                    $formMatterId = $request->input('matter_id');
                    $formOfficeId = $request->input('office_id');
                    $formMigrationAgent = $request->input('migration_agent');
                    $formPersonResponsible = $request->input('person_responsible');
                    $formPersonAssisting = $request->input('person_assisting');
                    $msg = 'Record Updated successfully';

                    // Cross-check: form client_id must match the URL-encoded id
                    if ((int) ($formClientId ?? 0) !== (int) $id) {
                        Log::warning('ConvertLeadToClient: client_id mismatch', ['url_id' => $id, 'form_client_id' => $formClientId]);

                        return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with('error', 'Invalid request.');
                    }
                    $matterIdChangetype = (int) ($formMatterId ?? 0);
                    if (! Matter::allowedForClientIsCompany($matterIdChangetype, (bool) $obj->is_company)) {
                        return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with('error', 'This matter type is not valid for this client record.');
                    }

                    $obj->type = $slug;
                    $obj->user_id = $formUserId;
                    $saved = $obj->save();
                    Log::info('ConvertLeadToClient: admin type updated to client', ['saved' => $saved]);

                    $matter = new ClientMatter;
                    $matter->user_id = $formUserId;
                    $matter->client_id = (int) $id;
                    $matter->office_id = $formOfficeId ?? optional(Auth::user())->office_id ?? null;
                    $matter->sel_migration_agent = $formMigrationAgent;
                    $matter->sel_person_responsible = $formPersonResponsible;
                    $matter->sel_person_assisting = $formPersonAssisting;
                    $matter->sel_matter_id = $formMatterId;
                    Log::info('ConvertLeadToClient: matter payload', [
                        'client_id' => $formClientId,
                        'user_id' => $formUserId,
                        'matter_id' => $formMatterId,
                        'office_id' => $matter->office_id,
                        'migration_agent' => $formMigrationAgent,
                    ]);

                    $client_matters_cnt_per_client = DB::table('client_matters')->select('id')->where('sel_matter_id', $formMatterId)->where('client_id', (int) $id)->count();
                    $client_matters_current_no = $client_matters_cnt_per_client + 1;
                    if ($formMatterId == 1) {
                        $matter->client_unique_matter_no = 'GN_'.$client_matters_current_no;
                    } else {
                        $matterInfo = Matter::select('nick_name')->where('id', '=', $formMatterId)->first();
                        $prefix = ($matterInfo && $matterInfo->nick_name) ? $matterInfo->nick_name : 'Matter';
                        $matter->client_unique_matter_no = $prefix.'_'.$client_matters_current_no;
                    }
                    Log::info('ConvertLeadToClient: client_unique_matter_no', ['client_unique_matter_no' => $matter->client_unique_matter_no]);

                    $matterType = Matter::find($formMatterId);
                    $workflowId = WorkflowAssignment::resolveWorkflowIdForNewClientMatter($matterType);
                    $firstStageId = WorkflowAssignment::firstStageIdForWorkflow($workflowId);
                    $matter->workflow_id = $workflowId;
                    $matter->workflow_stage_id = $firstStageId;
                    $matter->matter_status = 1; // Active by default
                    $matter->save();
                    WorkflowStageChecklistSync::ensureSeededForMatter($matter);
                    Log::info('ConvertLeadToClient: matter saved', ['matter_id' => $matter->id]);

                    if ($client_type == 'lead') {
                        $activity = new ActivitiesLog;
                        $activity->client_id = $formClientId;
                        $activity->created_by = Auth::user()->id;
                        $activity->subject = 'Lead converted to client. Matter '.$matter->client_unique_matter_no.' created';
                        $activity->description = 'Lead converted to client. Matter '.$matter->client_unique_matter_no.' created';
                        $activity->task_status = 0;
                        $activity->pin = 0;
                        $activity->save();

                        $msg = 'Lead converted to client. Matter '.$matter->client_unique_matter_no.' created';

                        // When cp_status=2 (approval pending), send push + in-app notification to client
                        if ((int) ($obj->cp_status ?? 0) === 2) {
                            $notificationMessage = 'Lead converted to client. Matter '.$matter->client_unique_matter_no.' created.';
                            $path = '/clients/detail/'.base64_encode(convert_uuencode($formClientId)).'/'.$matter->client_unique_matter_no.'/client_portal';
                            DB::table('notifications')->insert([
                                'sender_id' => Auth::user()->id,
                                'receiver_id' => $formClientId,
                                'module_id' => $matter->id,
                                'url' => $path,
                                'notification_type' => 'lead_converted_to_client',
                                'message' => $notificationMessage,
                                'created_at' => now(),
                                'updated_at' => now(),
                                'sender_status' => 1,
                                'receiver_status' => 0,
                                'seen' => 0,
                            ]);
                            try {
                                $fcmService = new FCMService;
                                $fcmService->sendToUser($formClientId, 'Lead converted to client', $notificationMessage, [
                                    'type' => 'lead_converted_to_client',
                                    'client_matter_id' => (string) $matter->id,
                                    'message' => $notificationMessage,
                                ]);
                            } catch (\Exception $e) {
                                Log::warning('Failed to send push notification for lead converted to client', [
                                    'client_id' => $formClientId,
                                    'matter_id' => $matter->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                            // Update cp_status to 1 (approved/active) after conversion when it was 2 (approval pending)
                            $obj->cp_status = 1;
                            $obj->save();
                        }
                    } elseif ($client_type == 'client') {
                        $activity = new ActivitiesLog;
                        $activity->client_id = $formClientId;
                        $activity->created_by = Auth::user()->id;
                        $activity->subject = 'Matter '.$matter->client_unique_matter_no.' created';
                        $activity->description = 'Matter '.$matter->client_unique_matter_no.' created';
                        $activity->task_status = 0;
                        $activity->pin = 0;
                        $activity->save();

                        $msg = 'Matter '.$matter->client_unique_matter_no.' created';
                    }
                    // Redirect with matter number in URL
                    $redirectUrl = '/clients/detail/'.base64_encode(convert_uuencode(@$id)).'/'.$matter->client_unique_matter_no;
                    Log::info('ConvertLeadToClient: success, redirecting', ['redirect_url' => $redirectUrl]);

                    return Redirect::to($redirectUrl)->with('success', $msg);
                } elseif ($slug == 'lead') {
                    $activeMatters = ClientMatter::where('client_id', (int) $id)->where('matter_status', 1)->count();
                    if ($activeMatters > 0) {
                        Log::info('ConvertLeadToClient: blocked revert to lead — active matters', ['admin_id' => $id, 'active_matters' => $activeMatters]);

                        return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with(
                            'error',
                            "Cannot revert to lead while {$activeMatters} active matter(s) exist. Close or set matters inactive first."
                        );
                    }
                    $obj->type = $slug;
                    $obj->user_id = '';
                    $saved = $obj->save();
                    Log::info('ConvertLeadToClient: reverted to lead');
                }
                Log::info('ConvertLeadToClient: redirecting to detail (slug was '.$slug.')');

                return Redirect::to('/clients/detail/'.base64_encode(convert_uuencode(@$id)))->with('success', 'Record Updated successfully');
            } else {
                Log::warning('ConvertLeadToClient: admin not found or wrong type', ['id' => $id]);

                return Redirect::to('/clients')->with('error', 'Clients Not Exist');
            }
        } else {
            Log::warning('ConvertLeadToClient: missing or empty id', ['id' => $id]);

            return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
        }
    }

    /**
     * Convert lead to client only (no new matter - for leads who already have matters from cost assignment)
     */
    public function convertLeadOnly(Request $request)
    {
        $clientId = $request->input('client_id');
        if (empty($clientId)) {
            return redirect()->back()->with('error', 'Client ID is required.');
        }
        $obj = Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
        if (! $obj || $obj->type !== 'lead') {
            return redirect()->back()->with('error', 'Only leads can be converted.');
        }
        $obj->type = 'client';
        $obj->user_id = $request->input('user_id', Auth::user()->id);
        $obj->save();

        $activity = new ActivitiesLog;
        $activity->client_id = $clientId;
        $activity->created_by = Auth::user()->id;
        $activity->subject = 'Lead converted to client';
        $activity->description = 'Lead converted to client';
        $activity->task_status = 0;
        $activity->pin = 0;
        $activity->save();

        $firstMatter = ClientMatter::where('client_id', $clientId)->where('matter_status', 1)->orderBy('id')->first();
        $redirectUrl = '/clients/detail/'.base64_encode(convert_uuencode($clientId));
        if ($firstMatter) {
            $redirectUrl .= '/'.$firstMatter->client_unique_matter_no;
        }

        // When cp_status=2 (approval pending), send push + in-app notification to client
        if ((int) ($obj->cp_status ?? 0) === 2) {
            $notificationMessage = 'Lead converted to client.';
            $path = $redirectUrl.'/client_portal';
            DB::table('notifications')->insert([
                'sender_id' => Auth::user()->id,
                'receiver_id' => $clientId,
                'module_id' => $firstMatter ? $firstMatter->id : null,
                'url' => $path,
                'notification_type' => 'lead_converted_to_client',
                'message' => $notificationMessage,
                'created_at' => now(),
                'updated_at' => now(),
                'sender_status' => 1,
                'receiver_status' => 0,
                'seen' => 0,
            ]);
            try {
                $fcmService = new FCMService;
                $fcmService->sendToUser($clientId, 'Lead converted to client', $notificationMessage, [
                    'type' => 'lead_converted_to_client',
                    'client_matter_id' => $firstMatter ? (string) $firstMatter->id : '',
                    'message' => $notificationMessage,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to send push notification for lead converted to client (convertLeadOnly)', [
                    'client_id' => $clientId,
                    'error' => $e->getMessage(),
                ]);
            }
            // Update cp_status to 1 (approved/active) after conversion when it was 2 (approval pending)
            $obj->cp_status = 1;
            $obj->save();
        }

        return redirect($redirectUrl)->with('success', 'Lead converted to client.');
    }

    /**
     * Store action with assignee information
     * Handles the "Assign Staff" popup functionality
     * Supports both single and multiple assignees
     *
     * @return JsonResponse
     */
    public function actionStore(Request $request)
    {
        try {
            $requestData = $request->all();

            // Validate required fields
            if (empty($requestData['client_id'])) {
                echo json_encode(['success' => false, 'message' => 'Client ID is required']);
                exit;
            }

            // Decode the client ID
            $clientId = $this->decodeString($requestData['client_id']);

            // Validate decoded client ID
            if ($clientId === false || empty($clientId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
                exit;
            }

            // Handle rem_cat - ensure it exists and is an array (PostgreSQL migration pattern)
            $remCat = $requestData['rem_cat'] ?? [];
            if (! is_array($remCat)) {
                // If it's a single value, convert to array
                $remCat = ! empty($remCat) ? [$remCat] : [];
            }

            // Validate that at least one assignee is selected
            if (empty($remCat)) {
                echo json_encode(['success' => false, 'message' => 'At least one assignee must be selected']);
                exit;
            }

            $targetClient = $this->findClientOrLeadForAction((int) $clientId);
            if (! $targetClient) {
                echo json_encode(['success' => false, 'message' => 'Client or lead not found']);
                exit;
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                echo json_encode(['success' => false, 'message' => config('constants.unauthorized')]);
                exit;
            }
            $clientLabel = $this->actionClientDisplayName($targetClient);

            // Get the next unique ID for this action
            $actionUniqueId = 'group_'.uniqid('', true);

            // Loop through each assignee and create an action
            foreach ($remCat as $assigneeId) {
                // Create a new action for each assignee
                $action = new Note;
                $action->client_id = $clientId;
                $action->user_id = Auth::user()->id;
                $action->description = $requestData['description'] ?? '';
                $action->unique_group_id = $actionUniqueId;

                // Set the title for the current assignee
                $assigneeName = $this->getAssigneeName($assigneeId);
                $defaultTitle = ($clientLabel !== '' ? $clientLabel.': ' : '').'Assigned to '.$assigneeName;
                $action->title = ! empty($requestData['remindersubject']) ? $requestData['remindersubject'] : $defaultTitle;

                // PostgreSQL NOT NULL constraints - must set these fields (Notes Table pattern)
                $action->is_action = 1; // This is an action
                $action->pin = 0; // Default to not pinned
                $action->status = '0'; // Default status (string '0' = active, '1' = completed)
                $action->type = 'client';
                $action->task_group = $requestData['task_group'] ?? null;
                $action->assigned_to = $assigneeId;

                if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                    $action->action_date = $requestData['followup_datetime'];
                }

                // Follow-ups do not use Note Deadline; keep it null so they stay distinct from actions.
                if (ActionTaskGroup::isFollowUp($action->task_group)) {
                    $action->note_deadline = null;
                } elseif (isset($requestData['note_deadline_checkbox']) && $requestData['note_deadline_checkbox'] != '') {
                    if ($requestData['note_deadline_checkbox'] == 1) {
                        $action->note_deadline = $requestData['note_deadline'] ?? null;
                    } else {
                        $action->note_deadline = null;
                    }
                } else {
                    $action->note_deadline = null;
                }

                $saved = $action->save();

                if ($saved) {
                    // Update lead action date
                    if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                        $targetClient->followup_date = $requestData['followup_datetime'];
                        $targetClient->save();
                    }

                    // Create a notification for the current assignee
                    $o = new Notification;
                    $o->sender_id = Auth::user()->id;
                    $o->receiver_id = $assigneeId;
                    $o->module_id = $clientId;
                    $o->url = URL::to('/clients/detail/'.$requestData['client_id']);
                    $o->notification_type = 'client';
                    $o->receiver_status = 0; // Unread
                    $o->seen = 0; // Not seen

                    $actionDateTime = $requestData['followup_datetime'] ?? now();
                    try {
                        if (is_numeric($actionDateTime)) {
                            $formattedDate = date('d/M/Y h:i A', $actionDateTime);
                        } else {
                            $timestamp = strtotime($actionDateTime);
                            $formattedDate = $timestamp !== false ? date('d/M/Y h:i A', $timestamp) : date('d/M/Y h:i A');
                        }
                    } catch (\Exception $dateEx) {
                        $formattedDate = date('d/M/Y h:i A');
                    }

                    $itemLabel = ActionTaskGroup::isFollowUp($action->task_group) ? 'Followup' : 'Action';
                    $o->message = ($clientLabel !== '' ? $itemLabel.' for '.$clientLabel.'. ' : '')
                        .'Assigned by '.Auth::user()->first_name.' '.Auth::user()->last_name.' on '.$formattedDate;
                    $o->save();

                    // Log the activity for the current assignee
                    $objs = new ActivitiesLog;
                    $objs->client_id = $clientId;
                    $objs->created_by = Auth::user()->id;
                    $objs->subject = ActionTaskGroup::assignActivitySubject($assigneeName, $action->task_group);
                    $objs->description = '<span class="text-semi-bold">'.($requestData['remindersubject'] ?? '').'</span><p>'.($requestData['description'] ?? '').'</p>';
                    $objs->task_status = 0;
                    $objs->pin = 0;

                    if (Auth::user()->id != $assigneeId) {
                        $objs->use_for = $assigneeId;
                    } else {
                        $objs->use_for = '';
                    }

                    $objs->followup_date = $requestData['followup_datetime'] ?? null;
                    $objs->task_group = $requestData['task_group'] ?? null;
                    $objs->save();
                }
            }

            echo json_encode(['success' => true, 'message' => 'successfully saved', 'clientID' => $requestData['client_id']]);
            exit;

        } catch (\Exception $e) {
            Log::error('Error in actionStore: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            echo json_encode(['success' => false, 'message' => 'Error saving action. Please try again.']);
            exit;
        }
    }

    // Helper function to get assignee name
    protected function getAssigneeName($assigneeId)
    {
        $staff = Staff::find($assigneeId);

        return $staff ? $staff->first_name.' '.$staff->last_name : 'Unknown Assignee';
    }

    /**
     * Client/lead row for action APIs (with company for display name).
     */
    protected function findClientOrLeadForAction(int $id): ?Admin
    {
        return Admin::with('company')->whereIn('type', ['client', 'lead'])->find($id);
    }

    /**
     * Human label for actions/notifications: company name when is_company, else person name.
     */
    protected function actionClientDisplayName(Admin $client): string
    {
        $label = trim($client->company_name_or_personal_name);
        if ($label === '') {
            $label = trim(($client->first_name ?? '').' '.($client->last_name ?? ''));
        }

        return $label;
    }

    /**
     * Save tags for a client
     * Handles the tag assignment functionality from the client detail modal
     *
     * @return RedirectResponse
     */
    public function save_tag(Request $request)
    {
        try {
            // Validate required fields
            $request->validate([
                'client_id' => 'required|integer',
                'tag' => 'nullable|array',
            ]);

            $clientId = $request->input('client_id');
            $tags = $request->input('tag', []);
            $createNewAsRed = filter_var($request->input('create_new_as_red', false), FILTER_VALIDATE_BOOLEAN);
            $isAjax = $request->ajax() || $request->wantsJson();

            // Find the client
            $client = Admin::where('id', $clientId)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (! $client) {
                if ($isAjax) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Client not found',
                    ], 404);
                }

                return redirect()->back()->with('error', 'Client not found');
            }

            // Process tags - create new ones if they don't exist, get IDs for existing ones
            $tagIds = [];
            if (! empty($tags) && is_array($tags)) {
                foreach ($tags as $tagValue) {
                    if (! empty($tagValue)) {
                        // Check if tag exists by name first
                        $existingTag = Tag::where('name', $tagValue)->first();

                        if ($existingTag) {
                            // Tag exists, use its ID
                            $tagIds[] = $existingTag->id;
                        } else {
                            // Check if it's an ID (numeric)
                            if (is_numeric($tagValue)) {
                                $tagById = Tag::find($tagValue);
                                if ($tagById) {
                                    $tagIds[] = $tagById->id;
                                }
                            } else {
                                // Create new tag (as normal or red based on create_new_as_red flag)
                                $newTag = new Tag;
                                $newTag->name = $tagValue;
                                $newTag->created_by = Auth::id();
                                if ($createNewAsRed) {
                                    $newTag->tag_type = Tag::TYPE_RED;
                                    $newTag->is_hidden = true;
                                } else {
                                    $newTag->tag_type = Tag::TYPE_NORMAL;
                                    $newTag->is_hidden = false;
                                }
                                $newTag->save();
                                $tagIds[] = $newTag->id;
                            }
                        }
                    }
                }
            }

            // Update the client's tagname field with tag IDs
            $client->tagname = implode(',', $tagIds);
            $client->save();

            $normalTags = [];
            $redTags = [];
            if (! empty($tagIds)) {
                $savedTags = Tag::whereIn('id', $tagIds)->get()->keyBy('id');
                foreach ($tagIds as $tagId) {
                    $tag = $savedTags[$tagId] ?? null;
                    if (! $tag) {
                        continue;
                    }
                    $tagPayload = [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'tag_type' => $tag->tag_type,
                    ];
                    if ($tag->tag_type === Tag::TYPE_RED) {
                        $redTags[] = $tagPayload;
                    } else {
                        $normalTags[] = $tagPayload;
                    }
                }
            }

            if ($isAjax) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tags saved successfully',
                    'client_id' => (int) $clientId,
                    'tags' => [
                        'normal' => $normalTags,
                        'red' => $redTags,
                    ],
                ]);
            }

            return redirect()->back()->with('success', 'Tags saved successfully');

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error saving tags: '.$e->getMessage());
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while saving tags',
                ], 500);
            }

            return redirect()->back()->with('error', 'An error occurred while saving tags');
        }
    }

    /**
     * Store personal action (Add My Action functionality)
     * Used by: action.blade.php
     */
    public function storePersonalAction(Request $request)
    {
        try {
            $requestData = $request->all();

            // Decode the client ID - handle empty/null for personal actions
            $clientId = null;
            $encodedClientId = null;

            if (! empty($requestData['client_id'])) {
                // Extract just the encoded part (format: "ENCODED/Matter/NO" or "ENCODED/Client")
                $clientIdParts = explode('/', $requestData['client_id']);
                $encodedClientId = $clientIdParts[0];
                $decodedClient = $this->decodeString($encodedClientId);
                if ($decodedClient === false || $decodedClient === '') {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $decodedClient;
            }

            // Generate unique action ID
            $actionUniqueId = 'group_'.uniqid('', true);

            $clientLabel = '';
            $targetClient = null;
            if ($clientId !== null) {
                $targetClient = $this->findClientOrLeadForAction((int) $clientId);
                if (! $targetClient) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetClient);
            }

            // Handle single or multiple assignees
            $assignees = is_array($requestData['rem_cat']) ? $requestData['rem_cat'] : [$requestData['rem_cat']];

            // Loop through each assignee and create an action
            foreach ($assignees as $assigneeId) {
                // Create a new action for each assignee
                $action = new Note;
                $action->client_id = $clientId;
                $action->user_id = Auth::user()->id;
                $action->description = @$requestData['description'];
                $action->unique_group_id = $actionUniqueId;
                $action->is_action = 1;
                $action->type = 'client';
                $action->task_group = @$requestData['task_group'];
                $action->assigned_to = $assigneeId;
                $action->status = '0'; // Not completed
                $action->pin = 0; // Required field - default to not pinned
                $assigneeName = $this->getAssigneeName($assigneeId);
                $action->title = ($clientLabel !== '' ? $clientLabel.': ' : '').'Assigned to '.$assigneeName;

                if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                    $action->action_date = @$requestData['followup_datetime'];
                }

                $saved = $action->save();

                if ($saved) {
                    // Create a notification for the assignee
                    $notification = new Notification;
                    $notification->sender_id = Auth::user()->id;
                    $notification->receiver_id = $assigneeId;
                    $notification->module_id = $clientId;

                    // Set URL based on whether client exists
                    if (! empty($requestData['client_id'])) {
                        $notification->url = URL::to('/clients/detail/'.$requestData['client_id']);
                    } else {
                        $notification->url = URL::to('/action');
                    }

                    $notification->message = ($clientLabel !== '' ? 'Action for '.$clientLabel.'. ' : '').'Assigned to you';
                    $notification->seen = 0;
                    $notification->save();
                }
            }

            return response()->json(['success' => true, 'message' => 'Action created successfully']);
        } catch (\Exception $e) {
            Log::error('Error in storePersonalAction: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['success' => false, 'message' => 'Error creating action: '.$e->getMessage()], 500);
        }
    }

    /**
     * Update existing action
     * Used by: assign_by_me.blade.php
     */
    public function updateAction(Request $request)
    {
        $validated = $request->validate([
            'note_id' => 'required|integer|exists:notes,id',
            'description' => 'required|string',
            'rem_cat' => 'required|integer|exists:staff,id',
            'task_group' => 'required|string|in:Call,Checklist,Review,Query,Urgent,Personal Action,Client Portal,EOI/ROI Amendment,EOI/ROI Confirmation,Follow Up',
            'followup_datetime' => 'nullable|date',
            'client_id' => 'nullable|string',
        ]);

        $requestData = array_merge($request->all(), $validated);

        try {
            // Find the existing action
            $action = Note::findOrFail($validated['note_id']);

            if ((int) $action->is_action !== 1 || (string) $action->type !== 'client') {
                return response()->json(['success' => false, 'message' => 'Invalid task'], 400);
            }

            if ((string) $action->status === '1') {
                return response()->json(['success' => false, 'message' => 'This task is already completed'], 400);
            }

            $staff = Auth::user();
            if (! $action->canBeModifiedBy($staff)) {
                return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
            }

            // Decode the client ID - handle empty/null for personal actions
            $clientId = null;
            $clientLabel = '';
            if (! empty($requestData['client_id'])) {
                // Extract just the encoded part (format: "ENCODED/Matter/NO" or "ENCODED/Client")
                $clientIdParts = explode('/', $requestData['client_id']);
                $encodedClientId = $clientIdParts[0];
                $decodedId = $this->decodeString($encodedClientId);
                if ($decodedId === false || $decodedId === '') {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $decodedId;
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, $staff)) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetForAction);
            } elseif ($action->client_id !== null) {
                // Keep linked client when the form does not post client_id (avoids clearing the client by mistake)
                $clientId = (int) $action->client_id;
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, $staff)) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetForAction);
            }

            $originalAssigneeId = (int) $action->assigned_to;

            // Update action fields
            $action->description = @$requestData['description'];
            $action->client_id = $clientId;
            $action->task_group = @$requestData['task_group'];
            $action->assigned_to = @$requestData['rem_cat'];

            // Assigner Name uses notes.user_id — reflect the staff member who last reassigned the task.
            if ((int) $action->assigned_to !== $originalAssigneeId) {
                $action->user_id = Auth::user()->id;
            }

            if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                $action->action_date = @$requestData['followup_datetime'];
            }

            $action->save();

            // Create notification for the assignee if changed
            if ((int) $action->assigned_to !== $originalAssigneeId) {
                $notification = new Notification;
                $notification->sender_id = Auth::user()->id;
                $notification->receiver_id = $action->assigned_to;
                $notification->module_id = $clientId;

                if ($clientId !== null) {
                    $clientPath = ! empty($requestData['client_id'])
                        ? $requestData['client_id']
                        : base64_encode(convert_uuencode($clientId));
                    $notification->url = URL::to('/clients/detail/'.$clientPath);
                } else {
                    $notification->url = URL::to('/action');
                }

                $notification->message = ($clientLabel !== '' ? 'Action for '.$clientLabel.'. ' : '').'Updated — reassigned to you';
                $notification->seen = 0;
                $notification->save();
            }

            // Log to Activity Feed when action is updated (only for client-linked actions)
            if ($clientId !== null) {
                $assigneeName = $this->getAssigneeName($action->assigned_to);
                $activityLog = new ActivitiesLog;
                $activityLog->client_id = $clientId;
                $activityLog->created_by = Auth::user()->id;
                $activityLog->subject = 'Updated action for '.$assigneeName;
                $activityLog->description = '<span class="text-semi-bold">'.($action->task_group ?? '').'</span><p>'.($action->description ?? '').'</p>';
                $activityLog->task_status = $action->status === '1' ? 1 : 0;
                $activityLog->pin = 0;
                if (Auth::user()->id != $action->assigned_to) {
                    $activityLog->use_for = $action->assigned_to;
                } else {
                    $activityLog->use_for = null;
                }
                $activityLog->followup_date = isset($action->action_date) ? $action->action_date : null;
                $activityLog->task_group = $action->task_group;
                $activityLog->save();
            }

            return response()->json(['success' => true, 'message' => 'Action updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating action: '.$e->getMessage()], 500);
        }
    }

    /**
     * Reassign action (for completed actions)
     * Used by: action_completed.blade.php
     */
    public function reassignAction(Request $request)
    {
        try {
            $requestData = $request->all();

            // Decode the client ID - handle empty/null for personal actions
            $clientId = null;
            $clientLabel = '';
            if (! empty($requestData['client_id'])) {
                // Extract just the encoded part (format: "ENCODED/Matter/NO" or "ENCODED/Client")
                $clientIdParts = explode('/', $requestData['client_id']);
                $encodedClientId = $clientIdParts[0];
                $decodedId = $this->decodeString($encodedClientId);
                if ($decodedId === false || $decodedId === '') {
                    return response()->json(['success' => false, 'message' => 'Invalid client ID'], 400);
                }
                $clientId = (int) $decodedId;
                $targetForAction = $this->findClientOrLeadForAction($clientId);
                if (! $targetForAction) {
                    return response()->json(['success' => false, 'message' => 'Client or lead not found'], 404);
                }
                if (! StaffClientVisibility::canAccessClientOrLead($clientId, Auth::user())) {
                    return response()->json(['success' => false, 'message' => config('constants.unauthorized')], 403);
                }
                $clientLabel = $this->actionClientDisplayName($targetForAction);
            }

            // Generate unique action ID
            $actionUniqueId = 'group_'.uniqid('', true);

            // Create a new action
            $action = new Note;
            $action->client_id = $clientId;
            $action->user_id = Auth::user()->id;
            $action->description = @$requestData['description'];
            $action->unique_group_id = $actionUniqueId;
            $action->is_action = 1;
            $action->type = 'client';
            $action->task_group = @$requestData['task_group'];
            $action->assigned_to = @$requestData['rem_cat'];
            $action->status = '0'; // Not completed
            $action->pin = 0; // Required field - default to not pinned
            $assigneeName = $this->getAssigneeName($action->assigned_to);
            $action->title = ($clientLabel !== '' ? $clientLabel.': ' : '').'Assigned to '.$assigneeName;

            if (isset($requestData['followup_datetime']) && $requestData['followup_datetime'] != '') {
                $action->action_date = @$requestData['followup_datetime'];
            }

            $saved = $action->save();

            if ($saved) {
                // Create a notification for the assignee
                $notification = new Notification;
                $notification->sender_id = Auth::user()->id;
                $notification->receiver_id = $action->assigned_to;
                $notification->module_id = $clientId;

                // Set URL based on whether client exists
                if (! empty($requestData['client_id'])) {
                    $notification->url = URL::to('/clients/detail/'.$requestData['client_id']);
                } else {
                    $notification->url = URL::to('/action');
                }

                $notification->message = ($clientLabel !== '' ? 'Action for '.$clientLabel.'. ' : '').'Assigned to you';
                $notification->seen = 0;
                $notification->save();
            }

            return response()->json(['success' => true, 'message' => 'Action created successfully']);
        } catch (\Exception $e) {
            Log::error('Error in reassignAction: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['success' => false, 'message' => 'Error creating action: '.$e->getMessage()], 500);
        }
    }

    /**
     * Test Python Accounting Processing
     *
     * This is a test endpoint to experiment with Python-based accounting processing
     * Can be used to test data export, analytics, report generation, etc.
     */
    public function testPythonAccounting(Request $request)
    {
        try {
            $clientId = $request->input('client_id');
            $matterId = $request->input('matter_id');
            $processingType = $request->input('processing_type', 'analytics'); // analytics, export, report

            // Get accounting data
            $clientReceipts = DB::table('account_client_receipts')
                ->where('client_id', $clientId)
                ->where('client_matter_id', $matterId)
                ->get();

            $startTime = microtime(true);

            // Prepare data for Python service
            $accountingData = [
                'client_id' => $clientId,
                'matter_id' => $matterId,
                'receipts' => $clientReceipts->toArray(),
                'processing_type' => $processingType,
            ];

            // TODO: Call Python service for processing
            // Example:
            // $pythonService = app(\App\Services\PythonService::class);
            // $result = $pythonService->processAccountingData($accountingData);

            // For now, return mock response
            $endTime = microtime(true);
            $processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            return response()->json([
                'success' => true,
                'message' => 'Test completed successfully',
                'data' => [
                    'processing_time_ms' => round($processingTime, 2),
                    'records_count' => $clientReceipts->count(),
                    'processing_type' => $processingType,
                    'php_processing' => true,
                    'python_service_available' => false, // Will be true when Python service is integrated
                ],
                'note' => 'This is a test endpoint. Integrate with Python service for actual processing.',
            ]);

        } catch (\Exception $e) {
            Log::error('Test Python Accounting Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error during test processing',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update office assignment for a matter
     * POST /matters/update-office
     */
    public function updateMatterOffice(Request $request)
    {
        try {
            $this->validate($request, [
                'matter_id' => 'required|exists:client_matters,id',
                'office_id' => 'required|exists:branches,id',
            ]);

            $matter = ClientMatter::findOrFail($request->matter_id);
            $oldOffice = $matter->office ? $matter->office->office_name : 'None';
            $newOffice = Branch::findOrFail($request->office_id);

            // Update matter
            $matter->office_id = $request->office_id;
            $matter->save();

            // Log activity
            $activitySubject = $oldOffice === 'None'
                ? "assigned matter to {$newOffice->office_name} office"
                : "changed matter office from {$oldOffice} to {$newOffice->office_name}";

            if (! empty($request->notes)) {
                $activitySubject .= " - Notes: {$request->notes}";
            }

            $activityLog = new ActivitiesLog;
            $activityLog->client_id = $matter->client_id;
            $activityLog->created_by = Auth::id();
            $activityLog->subject = $activitySubject;
            $activityLog->task_status = 0;
            $activityLog->pin = 0;
            $activityLog->save();

            return response()->json([
                'success' => true,
                'message' => 'Office assigned successfully',
                'office_name' => $newOffice->office_name,
                'office_id' => $newOffice->id,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: '.implode(', ', $e->errors()),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating matter office: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign office: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export client data to JSON file
     *
     * @param  string  $id  Encoded client ID
     * @return Response
     */
    public function export($id)
    {
        try {
            // Decode the client ID
            $clientId = $this->decodeString($id);

            if (! $clientId) {
                return redirect()->route('clients.index')
                    ->with('error', 'Invalid client ID.');
            }

            // Check if client exists
            $client = Admin::where('id', $clientId)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (! $client) {
                return redirect()->route('clients.index')
                    ->with('error', 'Client not found.');
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                return redirect()->route('clients.index')
                    ->with('error', config('constants.unauthorized'));
            }

            // Export client data
            $exportService = app(ClientExportService::class);
            $exportData = $exportService->exportClient($clientId);

            // Generate filename
            $filename = 'client_export_'.($client->client_id ?? $clientId).'_'.date('Y-m-d_His').'.json';

            // Return JSON file download
            return response()->json($exportData, 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Exception $e) {
            Log::error('Client export error: '.$e->getMessage(), [
                'client_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('clients.index')
                ->with('error', 'Failed to export client data: '.$e->getMessage());
        }
    }

    /**
     * Import client data from JSON file
     *
     * @return RedirectResponse
     */
    public function import(Request $request)
    {
        try {
            // Validate file upload (use extension check; mimes:json often fails when server reports .json as text/plain)
            $request->validate([
                'import_file' => [
                    'required',
                    'file',
                    'max:10240', // 10MB
                    function ($attribute, $value, $fail) {
                        $ext = strtolower($value->getClientOriginalExtension());
                        if ($ext !== 'json') {
                            $fail('The file must be a JSON file (.json).');
                        }
                    },
                ],
            ]);

            // Read and parse JSON file
            $file = $request->file('import_file');
            $jsonContent = file_get_contents($file->getRealPath());
            $importData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Invalid JSON file: '.json_last_error_msg()])
                    ->withInput();
            }

            // Validate import data structure
            if (! isset($importData['client'])) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Invalid import file format: missing lead/client data'])
                    ->withInput();
            }

            // Check if lead email is required (email is unique and NOT NULL in admins table)
            if (empty($importData['client']['email'])) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Lead email is required and cannot be empty'])
                    ->withInput();
            }

            // Check if first_name is required
            if (empty($importData['client']['first_name'])) {
                return redirect()->back()
                    ->withErrors(['import_file' => 'Lead first name is required'])
                    ->withInput();
            }

            // Import as lead (JSON may contain client data; we always create a lead)
            $importData['client']['type'] = 'lead';
            $skipDuplicates = $request->has('skip_duplicates');
            $importService = app(ClientImportService::class);
            $result = $importService->importClient($importData, $skipDuplicates);

            if ($result['success']) {
                $message = 'Lead imported successfully. Lead ID: '.($result['client_id_reference'] ?? '');

                return redirect()->route('leads.index')
                    ->with('success', $message);
            } else {
                return redirect()->back()
                    ->withErrors(['import_file' => $result['message']])
                    ->withInput();
            }

        } catch (ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Lead import error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withErrors(['import_file' => 'Failed to import lead: '.$e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Search for contact persons (clients/leads) by email, phone, name, or client ID
     * Used for company contact person selection
     *
     * Search priority: Phone and Email are primary search fields
     */
    public function searchContactPerson(Request $request)
    {
        $query = $request->input('q', '');
        $excludeId = $request->input('exclude_id'); // Exclude current lead/client being edited

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        // Use ILIKE for PostgreSQL, LIKE for MySQL
        $likeOperator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $results = Admin::where(function ($q) use ($query, $likeOperator) {
            // Primary search: Phone and Email (as per requirement)
            $q->where('phone', $likeOperator, "%{$query}%")
                ->orWhere('email', $likeOperator, "%{$query}%")
              // Secondary search: Name and Client ID
                ->orWhere('first_name', $likeOperator, "%{$query}%")
                ->orWhere('last_name', $likeOperator, "%{$query}%")
                ->orWhere('client_id', $likeOperator, "%{$query}%");

            // For PostgreSQL, use CONCAT with ILIKE
            if (DB::getDriverName() === 'pgsql') {
                $q->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$query}%"]);
            } else {
                // For MySQL, use CONCAT with LIKE
                $q->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"]);
            }
        })
            ->whereIn('type', ['client', 'lead'])
            ->where(function ($q) {
                $q->where('type', 'client')
                    ->orWhere('type', 'lead');
            })
            ->where('is_company', false) // Exclude companies from being contact persons
            ->when($excludeId, function ($q) use ($excludeId) {
                $q->where('id', '!=', $excludeId);
            })
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'client_id', 'type')
            ->limit(20)
            ->get()
            ->map(function ($person) {
                $fullName = trim($person->first_name.' '.$person->last_name);
                // Show phone and email in display text
                $displayText = "{$fullName}";
                if ($person->email) {
                    $displayText .= " ({$person->email})";
                }
                if ($person->phone) {
                    $displayText .= " - {$person->phone}";
                }
                $displayText .= " - {$person->client_id}";

                return [
                    'id' => $person->id,
                    'text' => $displayText,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'email' => $person->email,
                    'phone' => $person->phone,
                    'client_id' => $person->client_id,
                    'type' => $person->type,
                ];
            });

        return response()->json(['results' => $results]);
    }

    /**
     * HTML description for activities_logs when an email_logs row is deleted from the CRM Emails tab.
     */
    protected function buildEmailLogDeletionActivityDescription(
        int $emailLogId,
        array $snapshot,
        int $attachmentCount,
        ?string $matterReference,
        ?int $matterInternalId
    ): string {
        $h = static function (?string $value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $displaySubject = ($snapshot['subject'] ?? '') !== '' ? $snapshot['subject'] : '(no subject)';
        $direction = $this->humanizeEmailLogMailType($snapshot['mail_type'] ?? null);
        $recordType = strtolower(trim((string) ($snapshot['record_type'] ?? '')));
        $recordLabel = $recordType === 'lead' ? 'Lead' : ($recordType === 'client' ? 'Client' : '');

        $lines = [];
        $lines[] = '<p>Removed an email from the CRM Emails tab.</p>';
        $lines[] = '<p><strong>Email log ID:</strong> '.$h((string) $emailLogId).'</p>';

        if ($matterReference !== null && $matterReference !== '') {
            $lines[] = '<p><strong>Matter:</strong> '.$h($matterReference).'</p>';
        } elseif ($matterInternalId !== null && $matterInternalId > 0) {
            $lines[] = '<p><strong>Matter ID:</strong> '.$h((string) $matterInternalId).'</p>';
        }

        if ($recordLabel !== '') {
            $lines[] = '<p><strong>Record:</strong> '.$h($recordLabel).'</p>';
        }

        if ($direction !== '') {
            $lines[] = '<p><strong>Direction:</strong> '.$h($direction).'</p>';
        }

        if ($attachmentCount > 0) {
            $lines[] = '<p><strong>Attachments removed:</strong> '.$h((string) $attachmentCount).'</p>';
        }

        $lines[] = '<p><strong>Subject:</strong> '.$h($displaySubject).'</p>';

        if (($snapshot['from_mail'] ?? '') !== '') {
            $lines[] = '<p><strong>From:</strong> '.$h($snapshot['from_mail']).'</p>';
        }
        if (($snapshot['to_mail'] ?? '') !== '') {
            $lines[] = '<p><strong>To:</strong> '.$h($snapshot['to_mail']).'</p>';
        }

        $ccLine = $this->formatEmailLogCcForActivityDescription($snapshot['cc'] ?? null);
        if ($ccLine !== null && $ccLine !== '') {
            $maxLen = 600;
            if (strlen($ccLine) > $maxLen) {
                $ccLine = substr($ccLine, 0, $maxLen).'…';
            }
            $lines[] = '<p><strong>CC:</strong> '.$h($ccLine).'</p>';
        }

        return implode('', $lines);
    }

    /**
     * @param  mixed  $mailType  Raw mail_type from email_logs (string or int depending on source)
     */
    protected function humanizeEmailLogMailType($mailType): string
    {
        if ($mailType === null || $mailType === '') {
            return '';
        }
        $s = is_string($mailType) ? strtolower(trim($mailType)) : (string) $mailType;

        if ($s === 'sent' || $s === '1') {
            return 'Sent';
        }
        if ($s === 'inbox' || $s === 'received' || $s === '0') {
            return 'Received';
        }

        return ucfirst($s);
    }

    /**
     * Normalize CC field (string or JSON array) for activity description.
     */
    protected function formatEmailLogCcForActivityDescription($cc): ?string
    {
        if ($cc === null) {
            return null;
        }
        if (is_array($cc)) {
            $flat = [];
            array_walk_recursive($cc, function ($v) use (&$flat) {
                if (is_string($v) && trim($v) !== '') {
                    $flat[] = trim($v);
                }
            });

            return $flat !== [] ? implode(', ', $flat) : null;
        }

        $trimmed = trim((string) $cc);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '[' || $trimmed[0] === '{') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $this->formatEmailLogCcForActivityDescription($decoded);
            }
        }

        return $trimmed;
    }

    public function getallclients(Request $request)
    {
        $squery = trim((string) $request->q);
        if ($squery === '') {
            return response()->json(['items' => []]);
        }

        if (mb_strlen($squery) < 2) {
            $allowShort = (strpos($squery, '-') !== false)
                || $squery === 'demo@gmail.com'
                || $squery === '4444444444';
            if (! $allowShort) {
                return response()->json(['items' => []]);
            }
        }

        $rawResults = [];
        $lim = 45;

        $squeryLower = strtolower($squery);
        $isUniversalEmail = ($squery === 'demo@gmail.com');
        $isUniversalPhone = ($squery === '4444444444');
        $isClientReferenceSearch = $this->globalSearchQueryIsClientReference($squery);
        $mysqlFtPhrase = $isClientReferenceSearch ? '' : $this->mysqlGlobalSearchBooleanFulltext($squery);
        $useMatterFt = ! $isClientReferenceSearch
            && $mysqlFtPhrase !== ''
            && $this->globalSearchMysqlFulltextIndexExists('client_matters', 'client_matters_global_search_ft');
        $useAdminFt = ! $isUniversalEmail
            && ! $isClientReferenceSearch
            && $mysqlFtPhrase !== ''
            && $this->globalSearchMysqlFulltextIndexExists('admins', 'admins_global_search_ft');

        // 1. Composite references (client_id + matter_no)
        if (strpos($squery, '-') !== false) {
            $parts = explode('-', $squery, 2);
            if (count($parts) == 2) {
                $clientIdPart = $parts[0];
                $matterNoPart = $parts[1];
                $clientIdPartLower = strtolower($clientIdPart);
                $matterNoPartLower = strtolower($matterNoPart);
                $matterResults = DB::table('admins')
                    ->join('client_matters', 'admins.id', '=', 'client_matters.client_id')
                    ->leftJoin('companies', 'companies.admin_id', '=', 'admins.id')
                    ->whereIn('admins.type', ['client', 'lead'])
                    ->whereNull('admins.is_deleted')
                    ->where('admins.is_archived', 0)
                    ->where('client_matters.matter_status', 1)
                    ->whereRaw('LOWER(admins.client_id) LIKE ?', ["%{$clientIdPartLower}%"])
                    ->whereRaw('LOWER(client_matters.client_unique_matter_no) LIKE ?', ["%{$matterNoPartLower}%"])
                    ->tap(function ($q) {
                        StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($q, 'admins');
                    })
                    ->select(
                        'admins.id as client_id',
                        'admins.client_id as client_reference',
                        'admins.first_name',
                        'admins.last_name',
                        'admins.is_company',
                        'admins.email',
                        'admins.is_archived',
                        'admins.type',
                        'companies.company_name',
                        'client_matters.client_unique_matter_no'
                    )
                    ->orderByDesc('client_matters.id')
                    ->limit($lim)
                    ->get();

                foreach ($matterResults as $result) {
                    $displayName = ($result->is_company && $result->company_name)
                        ? $result->company_name
                        : trim(($result->first_name ?? '').' '.($result->last_name ?? ''));
                    $rawResults[] = [
                        'id' => base64_encode(convert_uuencode($result->client_id)).'/Matter/'.$result->client_unique_matter_no,
                        'name' => $displayName,
                        'email' => $result->email,
                        'status' => $result->is_archived ? 'Archived' : $result->type,
                        'cid' => $result->client_id,
                        'client_reference' => $result->client_reference ?? '',
                        'is_company' => (bool) $result->is_company,
                        'record_type' => $result->type,
                    ];
                }
            }
        }

        // 2. Matter references (department / other / unique matter no)
        $matterMatches = collect();
        if (! $isClientReferenceSearch) {
            $matterMatches = DB::table('client_matters')
                ->join('admins', 'client_matters.client_id', '=', 'admins.id')
                ->leftJoin('companies', 'companies.admin_id', '=', 'admins.id')
                ->whereIn('admins.type', ['client', 'lead'])
                ->whereNull('admins.is_deleted')
                ->where('admins.is_archived', 0)
                ->where('client_matters.matter_status', 1)
                ->tap(function ($q) {
                    StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($q, 'admins');
                })
                ->where(function ($query) use ($squery, $squeryLower, $useMatterFt, $mysqlFtPhrase) {
                    if ($useMatterFt) {
                        $query->where(function ($matterFtQuery) use ($mysqlFtPhrase, $squery, $squeryLower) {
                            $matterFtQuery->whereRaw(
                                'MATCH(client_matters.department_reference, client_matters.other_reference, client_matters.client_unique_matter_no) AGAINST (? IN BOOLEAN MODE)',
                                [$mysqlFtPhrase]
                            )
                                ->orWhere('client_matters.department_reference', 'LIKE', "%{$squery}%")
                                ->orWhere('client_matters.other_reference', 'LIKE', "%{$squery}%")
                                ->orWhereRaw('LOWER(client_matters.client_unique_matter_no) LIKE ?', ["%{$squeryLower}%"]);
                        });
                    } else {
                        $query->where('client_matters.department_reference', 'LIKE', "%{$squery}%")
                            ->orWhere('client_matters.other_reference', 'LIKE', "%{$squery}%")
                            ->orWhere('client_matters.client_unique_matter_no', 'LIKE', "%{$squery}%");
                    }
                })
                ->select(
                    'admins.id as client_id',
                    'admins.client_id as client_reference',
                    'admins.first_name',
                    'admins.last_name',
                    'admins.is_company',
                    'admins.email',
                    'admins.is_archived',
                    'admins.type',
                    'companies.company_name',
                    'client_matters.client_unique_matter_no'
                )
                ->orderByDesc('client_matters.id')
                ->limit($lim)
                ->get();
        }

        foreach ($matterMatches as $matter) {
            $displayName = ($matter->is_company && $matter->company_name)
                ? $matter->company_name
                : trim(($matter->first_name ?? '').' '.($matter->last_name ?? ''));
            $rawResults[] = [
                'id' => base64_encode(convert_uuencode($matter->client_id)).'/Matter/'.$matter->client_unique_matter_no,
                'name' => $displayName,
                'email' => $matter->email,
                'status' => $matter->is_archived ? 'Archived' : $matter->type,
                'cid' => $matter->client_id,
                'client_reference' => $matter->client_reference ?? '',
                'is_company' => (bool) $matter->is_company,
                'record_type' => $matter->type,
            ];
        }

        // 3. Admins (clients / leads)
        $d = $this->globalSearchDobFromQuery($squery);

        $clientsQuery = Admin::query()
            ->with(['company.contactPerson'])
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted')
            ->where('admins.is_archived', 0);

        if ($isClientReferenceSearch) {
            $clientsQuery->where(function ($query) use ($squeryLower) {
                $query->whereRaw('LOWER(admins.client_id) = ?', [$squeryLower])
                    ->orWhereRaw('LOWER(admins.client_id) LIKE ?', [$squeryLower.'%']);
            });
        } else {
            $clientsQuery
                ->leftJoin('client_contacts', function ($join) use ($squery, $squeryLower, $isUniversalPhone) {
                    $join->on('client_contacts.client_id', '=', 'admins.id');
                    if ($isUniversalPhone) {
                        $join->where(function ($phoneQuery) use ($squery, $squeryLower) {
                            $phoneQuery->whereRaw('LOWER(client_contacts.phone) LIKE ?', ["%{$squeryLower}%"])
                                ->orWhereRaw('LOWER(client_contacts.phone) LIKE ?', ["%{$squery}_%"]);
                        });
                    } else {
                        $join->whereRaw('LOWER(client_contacts.phone) LIKE ?', ["%{$squeryLower}%"]);
                    }
                })
                ->leftJoin('client_emails', function ($join) use ($squeryLower, $isUniversalEmail) {
                    $join->on('client_emails.client_id', '=', 'admins.id');
                    if ($isUniversalEmail) {
                        $join->where(function ($emailQuery) use ($squeryLower) {
                            $emailQuery->whereRaw('LOWER(client_emails.email) LIKE ?', ["%{$squeryLower}%"])
                                ->orWhereRaw('LOWER(client_emails.email) LIKE ?', ['demo_%@gmail.com']);
                        });
                    } else {
                        $join->whereRaw('LOWER(client_emails.email) LIKE ?', ["%{$squeryLower}%"]);
                    }
                })
                ->where(function ($query) use ($squery, $squeryLower, $d, $isUniversalEmail, $isUniversalPhone, $useAdminFt, $mysqlFtPhrase) {
                    if ($isUniversalEmail) {
                        $query->where(function ($emailSubQuery) use ($squeryLower) {
                            $emailSubQuery->whereRaw('LOWER(admins.email) LIKE ?', ["%{$squeryLower}%"])
                                ->orWhereRaw('LOWER(admins.email) LIKE ?', ['demo_%@gmail.com']);
                        });
                    } elseif ($useAdminFt) {
                        // FULLTEXT misses alphanumeric client refs (e.g. VIPL2400001); keep LIKE fallback.
                        $query->where(function ($adminFtQuery) use ($mysqlFtPhrase, $squeryLower) {
                            $adminFtQuery->whereRaw(
                                'MATCH(admins.first_name, admins.last_name, admins.email, admins.client_id) AGAINST (? IN BOOLEAN MODE)',
                                [$mysqlFtPhrase]
                            )
                                ->orWhereRaw('LOWER(admins.client_id) LIKE ?', ["%{$squeryLower}%"]);
                        });
                    } else {
                        $query->whereRaw('LOWER(admins.email) LIKE ?', ["%{$squeryLower}%"]);
                    }

                    if ($isUniversalEmail) {
                        $query->orWhereRaw('LOWER(admins.first_name) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(admins.last_name) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(admins.client_id) LIKE ?', ["%{$squeryLower}%"]);
                    } elseif (! $useAdminFt) {
                        $query->orWhereRaw('LOWER(admins.first_name) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(admins.last_name) LIKE ?', ["%{$squeryLower}%"])
                            ->orWhereRaw('LOWER(admins.client_id) LIKE ?', ["%{$squeryLower}%"]);
                    }

                    $query->orWhereHas('company', function ($q) use ($squeryLower) {
                        $q->whereRaw('LOWER(company_name) LIKE ?', ["%{$squeryLower}%"]);
                    });

                    if ($isUniversalPhone) {
                        $query->orWhere(function ($phoneSubQuery) use ($squery, $squeryLower) {
                            $phoneSubQuery->whereRaw('LOWER(admins.phone) LIKE ?', ["%{$squeryLower}%"])
                                ->orWhereRaw('LOWER(admins.phone) LIKE ?', ["%{$squery}_%"]);
                        });
                    } else {
                        $query->orWhereRaw('LOWER(admins.phone) LIKE ?', ["%{$squeryLower}%"]);
                    }

                    $query->orWhereRaw("LOWER(COALESCE(admins.first_name, '') || ' ' || COALESCE(admins.last_name, '')) LIKE ?", ["%{$squeryLower}%"])
                        ->orWhereNotNull('client_contacts.client_id')
                        ->orWhereNotNull('client_emails.client_id');

                    if ($d != '') {
                        $query->orWhere('admins.dob', '=', $d);
                    }
                });
        }

        $clientsQuery->tap(function ($q) {
            StaffClientVisibility::excludeSuperAdminOnlyLockedClientsFromAdminQuery($q);
        });
        if ($isClientReferenceSearch) {
            $clientsQuery
                ->select('admins.*')
                ->orderByRaw('CASE WHEN LOWER(admins.client_id) = ? THEN 0 ELSE 1 END', [$squeryLower])
                ->orderBy('admins.client_id')
                ->limit($lim);
        } else {
            $clientsQuery = $clientsQuery
                ->select('admins.*')
                ->distinct();
        }
        $clientsQuery = $isClientReferenceSearch
            ? $clientsQuery->get()
            : $clientsQuery
                ->orderBy('admins.created_at', 'desc')
                ->limit($lim)
                ->get();

        $clientIds = $clientsQuery->pluck('id')->toArray();

        if ($clientIds !== []) {
            $phonesData = DB::table('client_contacts')
                ->whereIn('client_id', $clientIds)
                ->select('client_id', 'phone', 'contact_type')
                ->orderBy('client_id')
                ->orderBy('contact_type')
                ->get()
                ->groupBy('client_id');

            $emailsData = DB::table('client_emails')
                ->whereIn('client_id', $clientIds)
                ->select('client_id', 'email', 'email_type')
                ->orderBy('client_id')
                ->orderBy('email_type')
                ->get()
                ->groupBy('client_id');

            $maxMatterIds = DB::table('client_matters')
                ->whereIn('client_id', $clientIds)
                ->where('matter_status', 1)
                ->select('client_id', DB::raw('MAX(id) as max_id'))
                ->groupBy('client_id')
                ->pluck('max_id', 'client_id')
                ->toArray();

            $latestMatters = [];
            if ($maxMatterIds !== []) {
                $latestMatters = DB::table('client_matters')
                    ->whereIn('id', array_values($maxMatterIds))
                    ->select('client_id', 'client_unique_matter_no')
                    ->get()
                    ->keyBy('client_id');
            }

            foreach ($clientsQuery as $client) {
                $allPhones = '';
                if (isset($phonesData[$client->id])) {
                    $phones = $phonesData[$client->id]
                        ->sortBy('contact_type')
                        ->pluck('phone')
                        ->unique()
                        ->values()
                        ->toArray();
                    $allPhones = implode(', ', $phones);
                }

                $allEmails = '';
                if (isset($emailsData[$client->id])) {
                    $emails = $emailsData[$client->id]
                        ->sortBy('email_type')
                        ->pluck('email')
                        ->unique()
                        ->values()
                        ->toArray();
                    $allEmails = implode(', ', $emails);
                }

                $latestMatterNo = isset($latestMatters[$client->id])
                    ? $latestMatters[$client->id]->client_unique_matter_no
                    : null;

                $resultFinalId = $latestMatterNo
                    ? base64_encode(convert_uuencode($client->id)).'/Matter/'.$latestMatterNo
                    : base64_encode(convert_uuencode($client->id)).'/Client';

                $displayName = $client->company_name_or_personal_name;
                if ($client->is_company && $client->company?->contactPerson) {
                    $cp = $client->company->contactPerson;
                    $cpBits = array_filter([
                        trim((string) ($cp->client_id ?? '')),
                        trim(($cp->first_name ?? '').' '.($cp->last_name ?? '')),
                    ]);
                    if ($cpBits !== []) {
                        $displayName .= ' — '.implode(' ', $cpBits);
                    }
                }

                $rawResults[] = [
                    'id' => $resultFinalId,
                    'name' => $displayName,
                    'email' => $client->email,
                    'status' => $client->is_archived ? 'Archived' : $client->type,
                    'cid' => $client->id,
                    'client_reference' => $client->client_id ?? '',
                    'phones' => $allPhones,
                    'emails' => $allEmails,
                    'is_company' => (bool) $client->is_company,
                    'record_type' => $client->type,
                ];
            }
        }

        $results = StaffClientVisibility::enrichGlobalSearchItemsBatch($rawResults);

        $seenCids = [];
        $results = array_values(array_filter($results, function ($r) use (&$seenCids) {
            $cid = $r['cid'] ?? null;
            if ($cid === null || isset($seenCids[$cid])) {
                return false;
            }
            $seenCids[$cid] = true;

            return true;
        }));

        $cidsInResults = array_column($results, 'cid');
        $companyIdsInResults = Admin::whereIn('id', $cidsInResults)
            ->where('is_company', 1)
            ->pluck('id')
            ->toArray();
        $contactPersonIdsToExclude = Company::whereIn('admin_id', $companyIdsInResults)
            ->pluck('contact_person_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $contactPersonIdsToExclude = array_values(array_filter($contactPersonIdsToExclude, function ($pid) use ($squery) {
            return ! $this->globalSearchQueryMatchesContactPerson((int) $pid, $squery);
        }));
        $results = array_values(array_filter($results, function ($r) use ($contactPersonIdsToExclude) {
            return ! in_array($r['cid'], $contactPersonIdsToExclude);
        }));

        if (count($results) > 80) {
            $results = array_slice($results, 0, 80);
        }

        return response()->json(['items' => $results]);
    }

    protected function globalSearchMysqlFulltextIndexExists(string $table, string $indexName): bool
    {
        static $cache = [];
        $key = $table.'.'.$indexName;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        if (! in_array($table, ['admins', 'client_matters'], true)) {
            return $cache[$key] = false;
        }
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return $cache[$key] = false;
        }
        try {
            $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

            return $cache[$key] = (count($rows) > 0);
        } catch (\Throwable) {
            return $cache[$key] = false;
        }
    }

    /**
     * Reverse a dd/mm/yyyy search to yyyy/mm/dd for admins.dob. Incomplete slash queries stay empty.
     */
    protected function globalSearchDobFromQuery(string $squery): string
    {
        if (! str_contains($squery, '/')) {
            return '';
        }

        $dob = explode('/', $squery);
        if (count($dob) < 3 || $dob[0] === '' || $dob[1] === '' || $dob[2] === '') {
            return '';
        }

        return $dob[2].'/'.$dob[1].'/'.$dob[0];
    }

    /**
     * Client file references (e.g. VIPL2400001, john2608773): letters + digits, no spaces.
     * These must not use FULLTEXT/name search or "john" will match unrelated clients.
     */
    protected function globalSearchQueryIsClientReference(string $squery): bool
    {
        $squery = trim($squery);
        if ($squery === '' || str_contains($squery, ' ') || str_contains($squery, '@')) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9]*\d[A-Za-z0-9]*$/', $squery);
    }

    /**
     * InnoDB FULLTEXT boolean mode string. Empty => use LIKE fallback (short tokens, non-MySQL, etc.).
     */
    protected function mysqlGlobalSearchBooleanFulltext(string $squery): string
    {
        $s = preg_replace('/[^\p{L}\p{N}@.]+/u', ' ', $squery);
        $words = array_values(array_filter(explode(' ', strtolower(trim($s)))));
        $parts = [];
        foreach ($words as $w) {
            $w = preg_replace('/[^a-z0-9@._-]+/i', '', $w);
            if ($w === '') {
                continue;
            }
            if (mb_strlen($w, 'UTF-8') < 3) {
                return '';
            }
            $parts[] = '+'.$w.'*';
        }

        return $parts ? implode(' ', $parts) : '';
    }

    /**
     * When a company and its primary contact both match search, keep the contact row if the query
     * matches the contact's own phone, email, client_id, or name (not only the company).
     */
    protected function globalSearchQueryMatchesContactPerson(int $contactPersonId, string $squery): bool
    {
        $squery = trim($squery);
        if ($squery === '' || $contactPersonId <= 0) {
            return false;
        }

        $squeryLower = strtolower($squery);
        $queryDigits = preg_replace('/\D+/', '', $squery);

        $admin = Admin::query()
            ->where('id', $contactPersonId)
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $admin) {
            return false;
        }

        if (Str::contains(strtolower((string) $admin->client_id), $squeryLower)
            || Str::contains(strtolower((string) $admin->email), $squeryLower)
            || Str::contains(strtolower((string) $admin->first_name), $squeryLower)
            || Str::contains(strtolower((string) $admin->last_name), $squeryLower)
        ) {
            return true;
        }

        $fullName = strtolower(trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')));
        if ($fullName !== '' && Str::contains($fullName, $squeryLower)) {
            return true;
        }

        if ($queryDigits !== '') {
            $phoneDigits = preg_replace('/\D+/', '', (string) $admin->phone);
            if ($phoneDigits !== '' && Str::contains($phoneDigits, $queryDigits)) {
                return true;
            }

            $extraPhones = DB::table('client_contacts')
                ->where('client_id', $contactPersonId)
                ->pluck('phone');
            foreach ($extraPhones as $p) {
                $pd = preg_replace('/\D+/', '', (string) $p);
                if ($pd !== '' && Str::contains($pd, $queryDigits)) {
                    return true;
                }
            }
        }

        $extraEmails = DB::table('client_emails')
            ->where('client_id', $contactPersonId)
            ->pluck('email');
        foreach ($extraEmails as $em) {
            if (Str::contains(strtolower((string) $em), $squeryLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Default "not picked call" SMS body from sms_templates (alias not_picked_call), with client name filled in.
     */
    protected function notPickedCallSmsDefaultForClient(Admin $client): string
    {
        $rawFirst = trim((string) ($client->first_name ?? ''));
        $first = $rawFirst !== ''
            ? mb_convert_case(mb_strtolower($rawFirst), MB_CASE_TITLE, 'UTF-8')
            : 'Client';

        $rendered = $this->smsManager->renderTemplateByAlias('not_picked_call', [
            'first_name' => $first,
            'office_phone' => '0396021330',
        ]);

        if ($rendered !== null) {
            return $rendered;
        }

        return "Hi {$first},\n\nWe tried reaching you but couldn't connect. Please call us at 0396021330 or let us know a suitable time.\n\nPlease do not reply via SMS.\n\nBansal Immigration";
    }

    /**
     * All Vendors.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        // Check authorization using trait
        if ($this->hasModuleAccess('20')) {
            $query = $this->getBaseClientQuery();
            $totalData = $query->count();

            // Apply filters using trait
            $query = $this->applyClientFilters($query, $request);

            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (! in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }

            $lists = $query->sortable(['id' => 'desc'])
                ->paginate($perPage)
                ->appends($request->except('page'));
        } else {
            $query = $this->getEmptyClientQuery();
            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (! in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            $lists = $query->sortable(['id' => 'desc'])->paginate($perPage);
            $totalData = 0;
        }

        return view('crm.clients.index', compact(['lists', 'totalData', 'perPage']));
    }

    /**
     * Export filtered client list as CSV.
     */
    public function exportList(Request $request)
    {
        if (! $this->hasModuleAccess('20')) {
            return redirect()->route('clients.index')
                ->with('error', config('constants.unauthorized'));
        }

        if ((int) (Auth::user()->role ?? 0) !== 1) {
            return redirect()->route('clients.index')
                ->with('error', config('constants.unauthorized'));
        }

        $query = $this->applyClientFilters($this->getBaseClientQuery(), $request);

        return app(ClientLeadListExportService::class)
            ->export($query, 'client', 'clients_export');
    }

    public function clientsmatterslist(Request $request)
    {
        // Check authorization using trait
        $teamMembers = collect();
        if ($this->hasModuleAccess('20')) {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
                ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
                ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
                ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
                ->select('cm.*', 'ad.client_id as client_unique_id', 'ad.first_name', 'ad.last_name', 'ad.email', 'ma.title', 'ma.nick_name', 'ad.dob')
                ->where('cm.matter_status', '=', '1')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) {
                    $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];
                    $q->whereNull('ws.name')
                        ->orWhereRaw('LOWER(TRIM(ws.name)) NOT IN ('.implode(',', array_fill(0, count($closedStages), '?')).')', $closedStages);
                });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            StaffClientVisibility::restrictMatterListToAllocatedClients($query, 'cm', 'ad');

            if ($request->has('sel_matter_id')) {
                $sel_matter_id = $request->input('sel_matter_id');
                if (trim($sel_matter_id) != '') {
                    $query->where('cm.sel_matter_id', '=', $sel_matter_id);
                }
            }

            if ($request->has('client_id')) {
                $client_id = $request->input('client_id');
                if (trim($client_id) != '') {
                    $query->where('ad.client_id', '=', $client_id);
                }
            }

            if ($request->has('name')) {
                $name = trim($request->input('name'));
                if ($name != '') {
                    $nameLower = strtolower($name);
                    $query->where(function ($q) use ($nameLower) {
                        $q->whereRaw('LOWER(ad.first_name) LIKE ?', ['%'.$nameLower.'%'])
                            ->orWhereRaw('LOWER(ad.last_name) LIKE ?', ['%'.$nameLower.'%'])
                            ->orWhereRaw("LOWER(COALESCE(ad.first_name, '') || ' ' || COALESCE(ad.last_name, '')) LIKE ?", ['%'.$nameLower.'%']);
                    });
                }
            }

            if ($request->filled('sel_migration_agent')) {
                $query->where('cm.sel_migration_agent', '=', $request->input('sel_migration_agent'));
            }

            if ($request->filled('sel_person_responsible')) {
                $query->where('cm.sel_person_responsible', '=', $request->input('sel_person_responsible'));
            }

            if ($request->filled('sel_person_assisting')) {
                $query->where('cm.sel_person_assisting', '=', $request->input('sel_person_assisting'));
            }

            if (
                $request->filled('quick_date_range') ||
                $request->filled('from_date') ||
                $request->filled('to_date')
            ) {
                [$startDate, $endDate] = $this->resolveClientDateRange($request);
                $dateField = $request->input('date_filter_field', 'created_at') === 'updated_at'
                    ? 'cm.updated_at'
                    : 'cm.created_at';

                if ($startDate && $endDate) {
                    $query->whereBetween($dateField, [$startDate, $endDate]);
                }
            }

            // Count AFTER all filters are applied, BEFORE orderBy
            $totalData = $query->count();

            // Apply orderBy AFTER count for pagination
            $query->orderBy($sortField, $sortDirection);

            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (! in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }

            $teamMembers = Staff::query()
                ->orderBy('first_name', 'asc')
                ->select('id', 'first_name', 'last_name')
                ->get();

            $lists = $query->paginate($perPage)->appends($request->except('page'));
        } else {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
                ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
                ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
                ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
                ->select('cm.*', 'ad.client_id as client_unique_id', 'ad.first_name', 'ad.last_name', 'ad.email', 'ma.title', 'ma.nick_name', 'ad.dob')
                ->where('cm.matter_status', '=', '1')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) {
                    $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];
                    $q->whereNull('ws.name')
                        ->orWhereRaw('LOWER(TRIM(ws.name)) NOT IN ('.implode(',', array_fill(0, count($closedStages), '?')).')', $closedStages);
                });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            $query->orderBy($sortField, $sortDirection);
            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (! in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            $totalData = 0;
            $lists = $query->paginate($perPage);
        }

        // dd( $lists);
        return view('crm.clients.clientsmatterslist', compact(['lists', 'totalData', 'teamMembers', 'perPage']));
    }

    /**
     * Display closed matters (matter_status=0 or workflow stages: File Closed, Withdrawn, Refund, Discontinued).
     * Mirrors clientsmatterslist but filters for archived/closed matters.
     */
    public function closedmatterslist(Request $request)
    {
        $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];

        $teamMembers = collect();
        if ($this->hasModuleAccess('20')) {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
                ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
                ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
                ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
                ->select('cm.*', 'ad.client_id as client_unique_id', 'ad.first_name', 'ad.last_name', 'ad.email', 'ma.title', 'ma.nick_name', 'ad.dob', 'ws.name as workflow_stage_name')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) use ($closedStages) {
                    $q->where('cm.matter_status', '=', '0')
                        ->orWhereRaw('LOWER(TRIM(ws.name)) IN ('.implode(',', array_fill(0, count($closedStages), '?')).')', $closedStages);
                });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            StaffClientVisibility::restrictMatterListToAllocatedClients($query, 'cm', 'ad');

            if ($request->has('sel_matter_id')) {
                $sel_matter_id = $request->input('sel_matter_id');
                if (trim($sel_matter_id) != '') {
                    $query->where('cm.sel_matter_id', '=', $sel_matter_id);
                }
            }

            if ($request->has('client_id')) {
                $client_id = $request->input('client_id');
                if (trim($client_id) != '') {
                    $query->where('ad.client_id', '=', $client_id);
                }
            }

            if ($request->has('name')) {
                $name = trim($request->input('name'));
                if ($name != '') {
                    $nameLower = strtolower($name);
                    $query->where(function ($q) use ($nameLower) {
                        $q->whereRaw('LOWER(ad.first_name) LIKE ?', ['%'.$nameLower.'%'])
                            ->orWhereRaw('LOWER(ad.last_name) LIKE ?', ['%'.$nameLower.'%'])
                            ->orWhereRaw("LOWER(COALESCE(ad.first_name, '') || ' ' || COALESCE(ad.last_name, '')) LIKE ?", ['%'.$nameLower.'%']);
                    });
                }
            }

            if ($request->filled('sel_migration_agent')) {
                $query->where('cm.sel_migration_agent', '=', $request->input('sel_migration_agent'));
            }

            if ($request->filled('sel_person_responsible')) {
                $query->where('cm.sel_person_responsible', '=', $request->input('sel_person_responsible'));
            }

            if ($request->filled('sel_person_assisting')) {
                $query->where('cm.sel_person_assisting', '=', $request->input('sel_person_assisting'));
            }

            if (
                $request->filled('quick_date_range') ||
                $request->filled('from_date') ||
                $request->filled('to_date')
            ) {
                [$startDate, $endDate] = $this->resolveClientDateRange($request);
                $dateField = $request->input('date_filter_field', 'created_at') === 'updated_at'
                    ? 'cm.updated_at'
                    : 'cm.created_at';

                if ($startDate && $endDate) {
                    $query->whereBetween($dateField, [$startDate, $endDate]);
                }
            }

            $totalData = $query->count();
            $query->orderBy($sortField, $sortDirection);

            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (! in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }

            $teamMembers = Staff::query()
                ->orderBy('first_name', 'asc')
                ->select('id', 'first_name', 'last_name')
                ->get();

            $lists = $query->paginate($perPage)->appends($request->except('page'));
        } else {
            $sortField = $request->get('sort', 'cm.id');
            $sortDirection = $request->get('direction', 'desc');

            $query = DB::table('client_matters as cm')
                ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
                ->join('matters as ma', 'ma.id', '=', 'cm.sel_matter_id')
                ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
                ->select('cm.*', 'ad.client_id as client_unique_id', 'ad.first_name', 'ad.last_name', 'ad.email', 'ma.title', 'ma.nick_name', 'ad.dob', 'ws.name as workflow_stage_name')
                ->where('ad.is_archived', '=', '0')
                ->whereIn('ad.type', ['client', 'lead'])
                ->whereNull('ad.is_deleted')
                ->where(function ($q) use ($closedStages) {
                    $q->where('cm.matter_status', '=', '0')
                        ->orWhereRaw('LOWER(TRIM(ws.name)) IN ('.implode(',', array_fill(0, count($closedStages), '?')).')', $closedStages);
                });
            StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
            $query->orderBy($sortField, $sortDirection);
            $allowedPerPage = [10, 20, 50, 100, 200];
            $perPage = (int) $request->get('per_page', 20);
            if (! in_array($perPage, $allowedPerPage, true)) {
                $perPage = 20;
            }
            $totalData = 0;
            $lists = $query->paginate($perPage);
        }

        return view('crm.clients.closedmatterslist', compact(['lists', 'totalData', 'teamMembers', 'perPage']));
    }

    public function insights(Request $request)
    {
        // Restrict to admin and super admin only (roles 1, 12)
        if (! in_array(Auth::user()->role ?? 0, [1, 12])) {
            return redirect()->back()->with('error', 'Only admin and super admin can view insights.');
        }

        $section = $request->input('section', 'clients');
        $now = Carbon::now();

        // Client metrics
        $clientBaseQuery = $this->getBaseClientQuery();
        $clientStats = [
            'total' => (clone $clientBaseQuery)->count(),
            'new30' => (clone $clientBaseQuery)->where('created_at', '>=', $now->copy()->subDays(30))->count(),
            'inactive' => (clone $clientBaseQuery)->where('status', 0)->count(),
            'archived' => Admin::where('is_archived', 1)
                ->where('type', 'client')
                ->whereNull('is_deleted')
                ->count(),
        ];

        $clientStatusBreakdown = (clone $clientBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->get()
            ->map(function ($row) {
                $row->label = ((int) $row->status === 1) ? 'Active' : 'Inactive';

                return $row;
            });

        $clientMonthlyGrowth = (clone $clientBaseQuery)
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as sort_key"),
                DB::raw("TO_CHAR(created_at, 'Mon YYYY') as label"),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('sort_key', 'label')
            ->orderBy('sort_key')
            ->get();

        $recentClients = (clone $clientBaseQuery)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'first_name', 'last_name', 'client_id', 'created_at', 'status']);

        // Matter metrics
        $matterBase = DB::table('client_matters as cm')->where('cm.matter_status', 1);
        $matterStats = [
            'total' => (clone $matterBase)->count(),
            'new30' => (clone $matterBase)->where('cm.created_at', '>=', $now->copy()->subDays(30))->count(),
            'assigned' => (clone $matterBase)->whereNotNull('cm.sel_migration_agent')->count(),
        ];

        $mattersByAgent = DB::table('client_matters as cm')
            ->leftJoin('staff as agent', 'agent.id', '=', 'cm.sel_migration_agent')
            ->select(
                DB::raw("COALESCE(agent.first_name || ' ' || agent.last_name, 'Unassigned') as agent_name"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('agent_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $recentMatters = DB::table('client_matters as cm')
            ->join('admins as client', 'client.id', '=', 'cm.client_id')
            ->leftJoin('staff as agent', 'agent.id', '=', 'cm.sel_migration_agent')
            ->select(
                'cm.client_unique_matter_no',
                'cm.created_at',
                'client.first_name as client_first_name',
                'client.last_name as client_last_name',
                'agent.first_name as agent_first_name',
                'agent.last_name as agent_last_name'
            )
            ->orderByDesc('cm.created_at')
            ->limit(5)
            ->get();

        // Lead metrics
        $leadBase = Lead::query();
        $leadStats = [
            'total' => (clone $leadBase)->count(),
            'new30' => (clone $leadBase)->where('created_at', '>=', $now->copy()->subDays(30))->count(),
        ];

        $leadsByStatus = (clone $leadBase)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $leadMonthlyGrowth = (clone $leadBase)
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as sort_key"),
                DB::raw("TO_CHAR(created_at, 'Mon YYYY') as label"),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('sort_key', 'label')
            ->orderBy('sort_key')
            ->get();

        $recentLeads = (clone $leadBase)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['first_name', 'last_name', 'status', 'created_at']);

        return view('crm.clients.insights', [
            'section' => $section,
            'clientStats' => $clientStats,
            'clientStatusBreakdown' => $clientStatusBreakdown,
            'clientMonthlyGrowth' => $clientMonthlyGrowth,
            'recentClients' => $recentClients,
            'matterStats' => $matterStats,
            'mattersByAgent' => $mattersByAgent,
            'recentMatters' => $recentMatters,
            'leadStats' => $leadStats,
            'leadsByStatus' => $leadsByStatus,
            'leadsByQuality' => collect(), // lead_quality column removed
            'leadMonthlyGrowth' => $leadMonthlyGrowth,
            'recentLeads' => $recentLeads,
        ]);
    }

    public function clientsemaillist(Request $request)
    {
        // Check authorization using trait
        if ($this->hasModuleAccess('20')) {
            $sortField = $request->get('sort', 'id');
            $sortDirection = $request->get('direction', 'desc');

            $query = Admin::where('is_archived', '=', '0')
                ->where('type', '=', 'client')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNull('is_deleted');
            StaffClientVisibility::restrictAdminEloquentQuery($query);
            $query->orderBy($sortField, $sortDirection);

            $totalData = $query->count();

            if ($request->has('client_id')) {
                $client_id = $request->input('client_id');
                if (trim($client_id) != '') {
                    $query->where('client_id', '=', $client_id);
                }
            }

            if ($request->has('name')) {
                $name = trim($request->input('name'));
                if ($name != '') {
                    $nameLower = strtolower($name);
                    $query->where(function ($q) use ($nameLower) {
                        $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.$nameLower.'%'])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$nameLower.'%'])
                            ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$nameLower.'%']);
                    });
                }
            }

            if ($request->has('email')) {
                $email = $request->input('email');
                if (trim($email) != '') {
                    $query->where('email', 'LIKE', '%'.$email.'%');
                }
            }

            $lists = $query->paginate(20);
        } else {
            $query = Admin::where('id', '=', '')->whereIn('type', ['client', 'lead'])->whereNull('is_deleted');
            $lists = $query->sortable(['id' => 'desc'])->paginate(20);
            $totalData = 0;
        }

        return view('crm.clients.clientsemaillist', compact(['lists', 'totalData']));
    }

    public function archived(Request $request)
    {
        $query = Admin::where('is_archived', '=', '1')
            ->whereIn('type', ['client', 'lead'])
            ->with('archivedByStaff');
        StaffClientVisibility::restrictAdminEloquentQuery($query);
        $query = $this->applyArchivedListFilters($query, $request);
        $totalData = $query->count();
        $lists = $query->sortable(['id' => 'desc'])->paginate(20)->appends($request->except('page'));

        return view('crm.archived.index', compact(['lists', 'totalData']));
    }

    /**
     * Archive a client
     * Sets is_archived = 1, archived_by = current staff user, archived_on = now
     *
     * @param  string  $id  Encoded client ID
     * @return RedirectResponse|JsonResponse
     */
    public function archive(Request $request, $id)
    {
        try {
            // Decode the client ID
            $decodedId = convert_uudecode(base64_decode($id));

            if (! is_numeric($decodedId)) {
                $message = 'Invalid client ID.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 400);
                }

                return redirect()->route('clients.index')
                    ->with('error', $message);
            }

            // Find the client
            $client = Admin::where('id', $decodedId)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (! $client) {
                $message = 'Client not found.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 404);
                }

                return redirect()->route('clients.index')
                    ->with('error', $message);
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $decodedId, Auth::user())) {
                $message = config('constants.unauthorized');
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 403);
                }

                return redirect()->route('clients.index')
                    ->with('error', $message);
            }

            // Check if already archived
            if ($client->is_archived == 1) {
                $message = 'Client is already archived.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 200);
                }

                return redirect()->route('clients.index')
                    ->with('info', $message);
            }

            // Archive the client (archived_by stores staff.id — admin guard uses staff provider)
            $client->is_archived = 1;
            $client->archived_by = Auth::guard('admin')->id();
            $client->archived_on = now();
            $client->save();

            $message = 'Client has been archived successfully.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 1, 'message' => $message], 200);
            }

            return redirect()->route('clients.index')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error archiving client: '.$e->getMessage());
            $message = 'An error occurred while archiving the client. Please try again.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 0, 'message' => $message], 500);
            }

            return redirect()->route('clients.index')
                ->with('error', $message);
        }
    }

    /**
     * Sync a Migration CRM client to Legal CRM as a Legal Lead (instant).
     * On success sets send_to_legal_crm = 1; on failure leaves 0 so staff can retry.
     * If the person already exists in Legal CRM, returns a clear already-synced message.
     *
     * @param  string  $id  Encoded client ID
     * @return RedirectResponse|JsonResponse
     */
    public function sendToLegalCrm(Request $request, $id)
    {
        try {
            $decodedId = $this->decodeString($id);

            if (! $decodedId || ! is_numeric($decodedId)) {
                $message = 'Invalid client ID.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 400);
                }

                return redirect()->route('clients.index')->with('error', $message);
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $decodedId, Auth::user())) {
                $message = config('constants.unauthorized');
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 403);
                }

                return redirect()->route('clients.index')->with('error', $message);
            }

            $client = Admin::where('id', $decodedId)
                ->where('type', 'client')
                ->where('is_archived', 0)
                ->whereNull('is_deleted')
                ->first();

            if (! $client) {
                $message = 'Client not found.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 404);
                }

                return redirect()->route('clients.index')->with('error', $message);
            }

            if ($client->isSentToLegalCrm()) {
                $message = 'This client is already synced to Legal CRM as a Lead.';
                Log::channel('migration_legal_crm')->info('Client Send to Legal CRM skipped — already synced', [
                    'migration_client_id' => (int) $client->id,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'staff_id' => Auth::id(),
                ]);
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 1,
                        'message' => $message,
                        'send_to_legal_crm' => Admin::LEGAL_CRM_SENT,
                        'already_sent' => true,
                        'queued' => false,
                    ], 200);
                }

                return redirect()->route('clients.index')->with('info', $message);
            }

            LegalCrmApiClient::assertLeadHasRequiredFields($client);

            Log::channel('migration_legal_crm')->info('Client Send to Legal CRM started (as Legal Lead)', [
                'migration_client_id' => (int) $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'staff_id' => Auth::id(),
            ]);

            try {
                $apiResult = app(LegalCrmApiClient::class)->createLeadFromMigrationLead($client);
                $client->markSentToLegalCrm();

                $alreadyExists = (bool) ($apiResult['already_exists'] ?? false);
                $message = $alreadyExists
                    ? 'This person already exists in Legal CRM as a Lead and has been marked as synced.'
                    : 'Client has been synced to Legal CRM as a Lead successfully.';

                Log::channel('migration_legal_crm')->info('Client Send to Legal CRM succeeded', [
                    'migration_client_id' => (int) $client->id,
                    'legal_lead_id' => $apiResult['lead_id'] ?? null,
                    'legal_already_exists' => $alreadyExists,
                    'email' => $client->email,
                    'staff_id' => Auth::id(),
                ]);

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 1,
                        'message' => $message,
                        'send_to_legal_crm' => Admin::LEGAL_CRM_SENT,
                        'already_sent' => false,
                        'queued' => false,
                        'legal_lead_id' => $apiResult['lead_id'] ?? null,
                        'legal_already_exists' => $alreadyExists,
                    ], 200);
                }

                return redirect()->route('clients.index')->with('success', $message);
            } catch (\Exception $apiException) {
                if ((int) ($client->send_to_legal_crm ?? 0) !== Admin::LEGAL_CRM_SENT) {
                    $client->send_to_legal_crm = Admin::LEGAL_CRM_NOT_SENT;
                    $client->save();
                }

                $apiError = $apiException->getMessage();
                if ($apiError === '' || str_contains(strtolower($apiError), 'sqlstate')) {
                    $apiError = 'Legal CRM API request failed.';
                }

                Log::channel('migration_legal_crm')->warning('Client Send to Legal CRM failed — retry manually', [
                    'migration_client_id' => (int) $client->id,
                    'email' => $client->email,
                    'staff_id' => Auth::id(),
                    'error' => $apiError,
                ]);

                $message = 'Send to Legal CRM failed ('.$apiError.'). Please try again.';

                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 0,
                        'message' => $message,
                        'send_to_legal_crm' => Admin::LEGAL_CRM_NOT_SENT,
                        'already_sent' => false,
                        'queued' => false,
                        'instant_failed' => true,
                        'api_error' => $apiError,
                    ], 200);
                }

                return redirect()->route('clients.index')->with('error', $message);
            }
        } catch (\Exception $e) {
            Log::channel('migration_legal_crm')->error('Client Send to Legal CRM failed', [
                'error' => $e->getMessage(),
                'staff_id' => Auth::id(),
                'encoded_id' => $id,
            ]);
            Log::error('Error sending client to Legal CRM: '.$e->getMessage());

            $message = $e->getMessage();
            if ($message === '' || str_contains(strtolower($message), 'sqlstate')) {
                $message = 'An error occurred while sending the client to Legal CRM. Please try again.';
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 0, 'message' => $message], 500);
            }

            return redirect()->route('clients.index')->with('error', $message);
        }
    }

    /**
     * Unarchive a client
     * Sets is_archived = 0 for the specified client
     *
     * @param  int  $id  Client ID
     * @return RedirectResponse|JsonResponse
     */
    public function unarchive(Request $request, $id)
    {
        try {
            // Find the client (including archived ones)
            $client = Admin::where('id', $id)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (! $client) {
                $message = 'Client not found.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 404);
                }

                return redirect()->route('clients.archived')
                    ->with('error', $message);
            }

            if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                $message = config('constants.unauthorized');
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 403);
                }

                return redirect()->route('clients.archived')
                    ->with('error', $message);
            }

            // Check if already unarchived
            if ($client->is_archived == 0) {
                $message = 'Client is already unarchived.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['status' => 0, 'message' => $message], 200);
                }

                return redirect()->route('clients.archived')
                    ->with('info', $message);
            }

            // Unarchive the client and clear archive metadata
            $client->is_archived = 0;
            $client->archived_by = null;
            $client->archived_on = null;
            $client->save();

            $message = 'Client has been unarchived successfully.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 1, 'message' => $message], 200);
            }

            return redirect()->route('clients.archived')
                ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error unarchiving client: '.$e->getMessage());
            $message = 'An error occurred while unarchiving the client. Please try again.';

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 0, 'message' => $message], 500);
            }

            return redirect()->route('clients.archived')
                ->with('error', $message);
        }
    }

    public function downloadpdf(Request $request, $id = null)
    {
        $fetchd = Document::where('id', $id)->first();
        if (! $fetchd || empty($fetchd->myfile)) {
            abort(404, 'Document not found.');
        }
        $admin = DB::table('admins')->select('client_id')->where('id', $fetchd->client_id)->first();
        if (! $admin) {
            abort(404, 'Client not found.');
        }
        // When myfile is already a full S3 URL (modern docs with myfile_key), use it directly
        if (str_starts_with($fetchd->myfile, 'http')) {
            $imageUrl = $fetchd->myfile;
        } else {
            // Legacy: construct S3 path using myfile_key (filename) or myfile, then get URL
            $fileName = $fetchd->myfile_key ?? $fetchd->myfile;
            if ($fetchd->doc_type == 'migration') {
                $filePath = $admin->client_id.'/'.$fetchd->folder_name.'/'.$fileName;
            } else {
                $filePath = $admin->client_id.'/'.$fetchd->doc_type.'/'.$fileName;
            }
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $imageUrl = $disk->url($filePath);
        }
        // Generate the PDF using service container to avoid facade type issues
        /** @var object $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf = $pdf->loadView('myPDF', compact('imageUrl'));

        // Return the generated PDF
        return $pdf->stream('codeplaners.pdf');
    }

    public function edit(Request $request, $id)
    {
        // Persistence is via POST /clients/save-section (per-section AJAX).
        // Native form POST must not attempt a full update (would race section saves).
        if ($request->isMethod('post')) {
            if (isset($id) && $id !== '') {
                return redirect()->route('clients.edit', $id)
                    ->with('info', 'Please use the Save button on each section to save changes.');
            }

            return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
        }

        // Check authorization (assumed to be handled elsewhere)
        if (isset($id) && ! empty($id)) {
            $id = $this->decodeString($id);
            if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
            }
            if (Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->exists()) {
                $fetchedData = Admin::with('company.contactPerson')->find($id);

                // Route to appropriate edit page
                if ($fetchedData && $fetchedData->is_company) {
                    // Use service to get all data with optimized queries (prevents N+1)
                    $data = app(ClientEditService::class)->getClientEditData($id);

                    // Use separate company edit page
                    return view('crm.clients.company_edit', $data);
                } else {
                    // Use service to get all data with optimized queries (prevents N+1)
                    $data = app(ClientEditService::class)->getClientEditData($id);

                    return view('crm.clients.edit', $data);
                }
            } else {
                return Redirect::to('/clients')->with('error', 'Client does not exist.');
            }
        } else {
            return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
        }
    }

    /**
     * Handle legacy test scores form submission (old format with band_score fields)
     * Converts legacy format to new ClientTestScore structure
     */
    public function editTestScores(Request $request)
    {
        try {
            $requestData = $request->all();
            $clientId = $requestData['client_id'] ?? null;

            if (! $clientId) {
                return redirect()->back()->withErrors(['error' => 'Client ID is required'])->withInput();
            }

            // Verify client exists
            $client = Admin::find($clientId);
            $isClient = in_array($client->type ?? '', ['client', 'lead']);
            if (! $client || ! $isClient) {
                return redirect()->back()->withErrors(['error' => 'Client not found'])->withInput();
            }

            // Delete existing TOEFL, IELTS, and PTE test scores for this client (only the ones handled by this legacy form)
            ClientTestScore::where('client_id', $clientId)
                ->whereIn('test_type', ['TOEFL', 'IELTS', 'PTE'])
                ->delete();

            // Process TOEFL scores
            if (! empty($requestData['band_score_1_1']) || ! empty($requestData['band_score_2_1']) ||
                ! empty($requestData['band_score_3_1']) || ! empty($requestData['band_score_4_1']) ||
                ! empty($requestData['score_1'])) {

                $testDate = $requestData['band_score_5_1'] ?? null;
                $formattedDate = null;
                if (! empty($testDate)) {
                    try {
                        $dateObj = Carbon::createFromFormat('d/m/Y', $testDate);
                        $formattedDate = $dateObj->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date format, skip
                    }
                }

                if (! empty($requestData['band_score_1_1']) || ! empty($requestData['band_score_2_1']) ||
                    ! empty($requestData['band_score_3_1']) || ! empty($requestData['band_score_4_1']) ||
                    ! empty($requestData['score_1'])) {
                    ClientTestScore::create([
                        'admin_id' => Auth::user()->id,
                        'client_id' => $clientId,
                        'test_type' => 'TOEFL',
                        'listening' => $requestData['band_score_1_1'] ?? null,
                        'reading' => $requestData['band_score_2_1'] ?? null,
                        'writing' => $requestData['band_score_3_1'] ?? null,
                        'speaking' => $requestData['band_score_4_1'] ?? null,
                        'overall_score' => $requestData['score_1'] ?? null,
                        'test_date' => $formattedDate,
                        'relevant_test' => 1,
                    ]);
                }
            }

            // Process IELTS scores
            if (! empty($requestData['band_score_5_2']) || ! empty($requestData['band_score_6_2']) ||
                ! empty($requestData['band_score_7_2']) || ! empty($requestData['band_score_8_2']) ||
                ! empty($requestData['score_2'])) {

                $testDate = $requestData['band_score_6_1'] ?? null;
                $formattedDate = null;
                if (! empty($testDate)) {
                    try {
                        $dateObj = Carbon::createFromFormat('d/m/Y', $testDate);
                        $formattedDate = $dateObj->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date format, skip
                    }
                }

                if (! empty($requestData['band_score_5_2']) || ! empty($requestData['band_score_6_2']) ||
                    ! empty($requestData['band_score_7_2']) || ! empty($requestData['band_score_8_2']) ||
                    ! empty($requestData['score_2'])) {
                    ClientTestScore::create([
                        'admin_id' => Auth::user()->id,
                        'client_id' => $clientId,
                        'test_type' => 'IELTS',
                        'listening' => $requestData['band_score_5_2'] ?? null,
                        'reading' => $requestData['band_score_6_2'] ?? null,
                        'writing' => $requestData['band_score_7_2'] ?? null,
                        'speaking' => $requestData['band_score_8_2'] ?? null,
                        'overall_score' => $requestData['score_2'] ?? null,
                        'test_date' => $formattedDate,
                        'relevant_test' => 1,
                    ]);
                }
            }

            // Process PTE scores
            if (! empty($requestData['band_score_9_3']) || ! empty($requestData['band_score_10_3']) ||
                ! empty($requestData['band_score_11_3']) || ! empty($requestData['band_score_12_3']) ||
                ! empty($requestData['score_3'])) {

                $testDate = $requestData['band_score_7_1'] ?? null;
                $formattedDate = null;
                if (! empty($testDate)) {
                    try {
                        $dateObj = Carbon::createFromFormat('d/m/Y', $testDate);
                        $formattedDate = $dateObj->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Invalid date format, skip
                    }
                }

                if (! empty($requestData['band_score_9_3']) || ! empty($requestData['band_score_10_3']) ||
                    ! empty($requestData['band_score_11_3']) || ! empty($requestData['band_score_12_3']) ||
                    ! empty($requestData['score_3'])) {
                    ClientTestScore::create([
                        'admin_id' => Auth::user()->id,
                        'client_id' => $clientId,
                        'test_type' => 'PTE',
                        'listening' => $requestData['band_score_9_3'] ?? null,
                        'reading' => $requestData['band_score_10_3'] ?? null,
                        'writing' => $requestData['band_score_11_3'] ?? null,
                        'speaking' => $requestData['band_score_12_3'] ?? null,
                        'overall_score' => $requestData['score_3'] ?? null,
                        'test_date' => $formattedDate,
                        'relevant_test' => 1,
                    ]);
                }
            }

            return redirect()->back()->with('success', 'Test scores updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Error updating test scores: '.$e->getMessage()])->withInput();
        }
    }

    /**
     * Resolve matter dropdown, header ref, workflow stage, and references once for client detail.
     */
    protected function buildClientDetailMatterContext(int $clientId, ?string $matterRefNo, array $validTabNames): array
    {
        $matterCount = ClientMatter::where('client_id', $clientId)
            ->where('matter_status', 1)
            ->count();

        $matterListQuery = DB::table('client_matters')
            ->leftJoin('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
            ->select(
                'client_matters.id',
                'client_matters.client_unique_matter_no',
                'matters.title',
                'client_matters.sel_matter_id'
            )
            ->where('client_matters.client_id', $clientId)
            ->where('client_matters.matter_status', 1)
            ->whereNotNull('client_matters.sel_matter_id');

        $matterListArr = $matterListQuery->orderBy('client_matters.created_at', 'desc')->get()->all();

        $showMatterDropdown = false;
        $latestClientMatterId = null;

        if ($matterRefNo) {
            $urlMatterCount = DB::table('client_matters')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->where('matter_status', 1)
                ->whereNotNull('sel_matter_id')
                ->count();

            if ($urlMatterCount > 0) {
                $showMatterDropdown = true;
                usort($matterListArr, function ($a, $b) use ($matterRefNo) {
                    if ($a->client_unique_matter_no == $matterRefNo && $b->client_unique_matter_no != $matterRefNo) {
                        return -1;
                    }
                    if ($a->client_unique_matter_no != $matterRefNo && $b->client_unique_matter_no == $matterRefNo) {
                        return 1;
                    }

                    return 0;
                });
                $urlMatter = ClientMatter::select('id')
                    ->where('client_id', $clientId)
                    ->where('client_unique_matter_no', $matterRefNo)
                    ->first();
                $latestClientMatterId = $urlMatter ? $urlMatter->id : null;
            } elseif (count($matterListArr) > 0) {
                $showMatterDropdown = true;
                $latestClientMatter = ClientMatter::where('client_id', $clientId)
                    ->where('matter_status', 1)
                    ->latest()
                    ->first();
                $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
            }
        } elseif (count($matterListArr) > 0) {
            $showMatterDropdown = true;
            $latestClientMatter = ClientMatter::where('client_id', $clientId)
                ->where('matter_status', 1)
                ->latest()
                ->first();
            $latestClientMatterId = $latestClientMatter ? $latestClientMatter->id : null;
        }

        if ($matterRefNo) {
            $matterInfoArr = ClientMatter::select('client_unique_matter_no')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } elseif ($matterCount > 0) {
            $matterInfoArr = ClientMatter::select('client_unique_matter_no')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $matterInfoArr = null;
        }

        $workflowStageArr = null;
        if ($matterRefNo) {
            $workflowStageArr = DB::table('client_matters')
                ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                ->select('workflow_stages.name')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } else {
            $clientMatterInfo = DB::table('client_matters')
                ->select('client_unique_matter_no')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();

            if ($clientMatterInfo) {
                $workflowStageArr = DB::table('client_matters')
                    ->join('workflow_stages', 'client_matters.workflow_stage_id', '=', 'workflow_stages.id')
                    ->select('workflow_stages.name')
                    ->where('client_id', $clientId)
                    ->where('client_unique_matter_no', $clientMatterInfo->client_unique_matter_no)
                    ->first();
            }
        }

        if ($matterRefNo) {
            $matterRefInfoArr = ClientMatter::select('department_reference', 'other_reference')
                ->where('client_id', $clientId)
                ->where('client_unique_matter_no', $matterRefNo)
                ->first();
        } elseif ($matterCount > 0) {
            $matterRefInfoArr = ClientMatter::select('department_reference', 'other_reference')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
        } else {
            $matterRefInfoArr = null;
        }

        $isMatterIdInUrl = $matterRefNo !== null
            && $matterRefNo !== ''
            && ! in_array(strtolower($matterRefNo), array_map('strtolower', $validTabNames), true);

        return [
            'matter_cnt' => $matterCount,
            'matter_list_arr' => $matterListArr,
            'latestClientMatterId' => $latestClientMatterId,
            'matter_info_arr' => $matterInfoArr,
            'workflow_stage_arr' => $workflowStageArr,
            'matter__ref_info_arr' => $matterRefInfoArr,
            'showMatterDropdown' => $showMatterDropdown,
            'isMatterIdInUrl' => $isMatterIdInUrl,
            'validTabNames' => $validTabNames,
        ];
    }

    public function detail(Request $request, $id = null, $id1 = null, $tab = null)
    {
        $clientDetailQueryCount = 0;
        $clientDetailQueryTimeMs = 0.0;
        $shouldLogClientDetailQueries = (bool) env('CLIENT_DETAIL_QUERY_LOG', false);

        if ($shouldLogClientDetailQueries) {
            DB::listen(function ($query) use (&$clientDetailQueryCount, &$clientDetailQueryTimeMs) {
                $clientDetailQueryCount++;
                $clientDetailQueryTimeMs += (float) $query->time;
            });
        }

        if (isset($request->t)) {
            if (Notification::where('id', $request->t)->exists()) {
                $ovv = Notification::find($request->t);
                $ovv->receiver_status = 1;
                $ovv->save();
            }
        }

        if (isset($id) && ! empty($id)) {
            $encodeId = $id;
            $id = $this->decodeString($id);

            // If $id1 holds a tab name rather than a matter reference (happens when the URL
            // only has two segments, e.g. /clients/detail/{client}/{tab}), move it to $tab
            // so that every downstream view receives a clean null $id1.
            if ($id1 && ClientDetailTabs::isKnownSlug((string) $id1)) {
                if (empty($tab)) {
                    $tab = $id1;
                }
                $id1 = null;
            }

            $targetRecord = Admin::query()
                ->where('id', (int) $id)
                ->whereIn('type', ['client', 'lead'])
                ->first(['id', 'type', 'first_name', 'last_name', 'client_id']);

            if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                $displayName = trim((string) (($targetRecord->first_name ?? '').' '.($targetRecord->last_name ?? '')));
                if ($displayName === '') {
                    $displayName = (string) ($targetRecord->client_id ?? ('#'.(int) $id));
                }

                $accessModalPayload = [
                    'id' => $encodeId.'/Client',
                    'cid' => (int) $id,
                    'name' => $displayName,
                    'record_type' => (string) ($targetRecord->type ?? 'client'),
                    'redirect_to' => route('clients.detail', [
                        'client_id' => $encodeId,
                        'client_unique_matter_ref_no' => $id1,
                        'tab' => $tab,
                    ]),
                ];

                return view('crm.access.detail-gate', [
                    'crossAccessAutoOpen' => $accessModalPayload,
                ]);
            }

            // Set default tab if not provided
            $activeTab = $tab ?? 'personaldetails';

            if (Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->exists()) {
                $fetchedData = Admin::with([
                    'company.contactPerson',
                    'company.tradingNames',
                    'company.directors.directorClient',
                    'company.nominations.nominatedClient',
                    'company.sponsorships',
                    'company.financials',
                    'companyNominationsAsNominee.company',
                    'detailsVerifiedByStaff',
                ])->find($id); // dd($fetchedData);

                // Route to company detail page if this is a company
                if ($fetchedData && $fetchedData->is_company) {
                    // Fetch data needed for company detail page
                    $clientAddresses = ClientAddress::where('client_id', $id)->orderedForDisplay()->get();
                    $clientContacts = ClientContact::where('client_id', $id)->get();
                    $emails = ClientEmail::where('client_id', $id)->get() ?? [];

                    $matter_cnt = ClientMatter::select('id')
                        ->where('client_id', $id)
                        ->where('matter_status', 1)
                        ->count();

                    // Get current admin user data for SMS templates
                    $currentAdmin = Auth::user();
                    $staffName = $currentAdmin->first_name.' '.$currentAdmin->last_name;
                    $matterNumber = $id1 ?? '';
                    $officePhone = $currentAdmin->phone ?? '';
                    $officeCountryCode = '+61';
                    $notPickedCallSmsDefault = $this->notPickedCallSmsDefaultForClient($fetchedData);

                    $encodeId = base64_encode(convert_uuencode($id));
                    $activeTab = $tab ?? 'companydetails';

                    return view('crm.companies.detail', compact(
                        'fetchedData', 'clientAddresses', 'clientContacts', 'emails',
                        'encodeId', 'id1', 'activeTab',
                        'staffName', 'matterNumber', 'officePhone', 'officeCountryCode',
                        'notPickedCallSmsDefault'
                    ));
                }

                // Fetch other client-related data
                $clientAddresses = ClientAddress::where('client_id', $id)->orderedForDisplay()->get();
                $clientContacts = ClientContact::where('client_id', $id)->get();
                $emails = ClientEmail::where('client_id', $id)->get() ?? [];
                $qualifications = ClientQualification::where('client_id', $id)->orderByRaw('finish_date DESC NULLS LAST')->get() ?? [];
                $experiences = ClientExperience::where('client_id', $id)->orderedForDisplay()->get() ?? [];
                $testScores = ClientTestScore::where('client_id', $id)->get() ?? [];
                $visaCountries = ClientVisaCountry::where('client_id', $id)->get() ?? [];
                $clientSpouseDetail = ClientSpouseDetail::where('client_id', $id)->get();
                $clientOccupations = ClientOccupation::where('client_id', $id)->get();
                $ClientPoints = ClientPoint::where('client_id', $id)->get();

                // Fetch client family details with optimized query
                // Eager load related client to prevent N+1 queries in the view
                $clientFamilyDetails = ClientRelationship::where('client_id', $id)
                    ->with(['relatedClient:id,first_name,last_name,client_id'])
                    ->get() ?? [];

                // Detect if current matter is EOI-related
                $isEoiMatter = false;
                if ($id1) {
                    // Check if the current matter is EOI
                    $currentMatter = DB::table('client_matters as cm')
                        ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $id)
                        ->where('cm.client_unique_matter_no', $id1)
                        ->where('cm.matter_status', 1)
                        ->select('m.nick_name', 'm.title')
                        ->first();

                    if ($currentMatter) {
                        $isEoiMatter = (
                            strtolower($currentMatter->nick_name) === 'eoi' ||
                            stripos($currentMatter->title, 'eoi') !== false ||
                            stripos($currentMatter->title, 'expression of interest') !== false ||
                            stripos($currentMatter->title, 'expression') !== false ||
                            stripos($currentMatter->title, 'interest') !== false
                        );
                    }
                } else {
                    // If no specific matter is selected, check if client has any EOI matter
                    $eoiMatterExists = DB::table('client_matters as cm')
                        ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                        ->where('cm.client_id', $id)
                        ->where('cm.matter_status', 1)
                        ->where(function ($query) {
                            $query->where('m.nick_name', 'ILIKE', 'eoi')
                                ->orWhere('m.title', 'LIKE', '%eoi%')
                                ->orWhere('m.title', 'LIKE', '%expression of interest%')
                                ->orWhere('m.title', 'LIKE', '%expression%');
                        })
                        ->exists();

                    $isEoiMatter = $eoiMatterExists;
                }

                // dd($clientFamilyDetails);

                // Check and insert/update application record when Client Portal tab is accessed
                // applications table removed - workflow is tracked via client_matters

                // Get current admin user data for SMS templates
                $currentAdmin = Auth::user();
                $staffName = $currentAdmin->first_name.' '.$currentAdmin->last_name;
                $matterNumber = $id1 ?? '';
                $officePhone = $currentAdmin->phone ?? '';
                $officeCountryCode = '+61';
                $notPickedCallSmsDefault = $this->notPickedCallSmsDefaultForClient($fetchedData);

                // Employer nominations: only list companies this staff may open (cross-access / allocation).
                $visibleNomineeNominations = $fetchedData->companyNominationsAsNominee
                    ->filter(function ($n) {
                        $companyAdminId = $n->company?->admin_id;
                        if ($companyAdminId === null) {
                            return true;
                        }

                        return StaffClientVisibility::canAccessClientOrLead((int) $companyAdminId, Auth::user());
                    })
                    ->values();

                $assignableStaff = collect();
                $leadStageLabels = [];
                if (($fetchedData->type ?? '') === 'lead') {
                    $assignableStaff = Staff::where('status', 1)
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get();
                    $leadStageLabels = [
                        'new' => 'New',
                        'follow_up' => 'Follow up',
                        'not_qualified' => 'Not qualified',
                        'hostile' => 'Hostile',
                    ];
                }

                $showGoogleReviewReminderModal = $this->shouldShowGoogleReviewReminderModal($fetchedData);

                // Companies where this client is the designated primary contact (separate from nominee nominations).
                $primaryContactCompaniesForClient = Company::query()
                    ->where('contact_person_id', (int) $id)
                    ->with(['tradingNames'])
                    ->get()
                    ->filter(function (Company $c) {
                        $aid = (int) ($c->admin_id ?? 0);
                        if ($aid <= 0) {
                            return false;
                        }

                        return StaffClientVisibility::canAccessClientOrLead($aid, Auth::user());
                    })
                    ->values();

                $validTabNames = ClientDetailTabs::slugs();
                $matterContext = $this->buildClientDetailMatterContext((int) $id, $id1, $validTabNames);

                // Account / checklists / emails / documents / notes / workflow / portal
                // load via fragment routes (or eager-if-active tab blades). Do not preload
                // those payloads here — first paint is personal details + matter context.
                $personalDetailContacts = $clientContacts->filter(function ($contact) {
                    return ($contact->contact_type ?? '') !== 'Not In Use';
                })->values();

                if ($shouldLogClientDetailQueries) {
                    Log::info('Client detail query profile', [
                        'client_id' => (int) $id,
                        'matter_ref' => $id1,
                        'tab' => $activeTab,
                        'query_count' => $clientDetailQueryCount,
                        'query_time_ms' => round($clientDetailQueryTimeMs, 2),
                    ]);
                }

                // Return the view with personal-details data + matter sidebar context only.
                return view('crm.clients.detail', array_merge(compact(
                    'fetchedData', 'clientAddresses', 'clientContacts', 'emails', 'qualifications',
                    'experiences', 'testScores', 'visaCountries', 'clientOccupations', 'ClientPoints', 'clientSpouseDetail',
                    'encodeId', 'id1', 'clientFamilyDetails', 'activeTab', 'isEoiMatter',
                    'staffName', 'matterNumber', 'officePhone', 'officeCountryCode',
                    'visibleNomineeNominations', 'notPickedCallSmsDefault',
                    'assignableStaff', 'leadStageLabels', 'showGoogleReviewReminderModal',
                    'primaryContactCompaniesForClient', 'personalDetailContacts'
                ), $matterContext));
            } else {
                return redirect()->route('clients.index')->with('error', 'Clients Not Exist');
            }
        } else {
            return redirect()->route('clients.index')->with('error', config('constants.unauthorized'));
        }
    }

    /**
     * Lightweight HTML fragment for the Workflow tab only (lazy-load / partial refresh).
     * Does not render Client Portal or other detail tabs.
     */
    public function workflowTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.workflow', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'workflow',
        ]);
    }

    /**
     * Lightweight HTML fragment for the Client Portal tab only (lazy-load / partial refresh).
     * Mirrors getClientPortalDetail data so Details/Documents/Messages keep working.
     */
    public function clientPortalTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        $clientId = (int) $fetchedData->id;
        $clientContacts = ClientContact::where('client_id', $clientId)->orderBy('id')->get();
        $emails = ClientEmail::where('client_id', $clientId)->get();
        $clientAddresses = ClientAddress::where('client_id', $clientId)->orderedForDisplay()->get();
        $clientPassports = ClientPassportInformation::where('client_id', $clientId)->orderBy('id')->get();
        $visaCountries = ClientVisaCountry::with('matter')->where('client_id', $clientId)->orderBy('id')->get();
        $clientTravels = ClientTravelInformation::where('client_id', $clientId)
            ->orderByRaw(ClientDetailTabs::nullsLastSql('travel_arrival_date').', created_at DESC')
            ->get();
        $qualifications = ClientQualification::where('client_id', $clientId)
            ->orderByRaw(ClientDetailTabs::nullsLastSql('finish_date'))
            ->get();
        $experiences = ClientExperience::where('client_id', $clientId)->orderedForDisplay()->get();
        $clientOccupations = ClientOccupation::where('client_id', $clientId)->get();
        $testScores = ClientTestScore::where('client_id', $clientId)->get();

        return view('crm.clients.tabs.client_portal', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'client_portal',
            'clientContacts' => $clientContacts,
            'emails' => $emails,
            'clientAddresses' => $clientAddresses,
            'clientPassports' => $clientPassports,
            'visaCountries' => $visaCountries,
            'clientTravels' => $clientTravels,
            'qualifications' => $qualifications,
            'experiences' => $experiences,
            'clientOccupations' => $clientOccupations,
            'testScores' => $testScores,
        ]);
    }

    /**
     * Lightweight HTML fragment for the Account tab only (lazy-load).
     * Ledger/invoice/office queries live in ClientDetailAccountTab so first paint stays light.
     */
    public function accountTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.account', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'account',
            'accountTabPayload' => ClientDetailAccountTab::build($fetchedData, $client_unique_matter_ref_no),
        ]);
    }

    /**
     * Lightweight HTML fragment for the Checklists tab only (lazy-load).
     * Staff dropdowns / cost-assignment queries live in ClientDetailChecklistsTab.
     */
    public function checklistsTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.checklists', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'checklists',
            'checklistsTabPayload' => ClientDetailChecklistsTab::build($fetchedData, $client_unique_matter_ref_no),
        ]);
    }

    /**
     * Lightweight HTML fragment for the Emails tab only (lazy-load).
     * Shell is already light; list data is fetched once via loadEmails() then cached.
     */
    public function emailsTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.emails', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'emails',
        ]);
    }

    /**
     * Lightweight HTML fragment for the Personal Documents tab only (lazy-load).
     * Category/document queries stay in the tab blade; first paint uses a stub.
     */
    public function personalDocumentsTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.personal_documents', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'personaldocuments',
        ]);
    }

    /**
     * Lightweight HTML fragment for the Visa Documents tab only (lazy-load).
     * Matter resolution and category queries stay in the tab blade.
     */
    public function visaDocumentsTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.visa_documents', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'visadocuments',
        ]);
    }

    /**
     * Lightweight HTML fragment for the Not Used Documents tab only (lazy-load).
     */
    public function notUsedDocumentsTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        return view('crm.clients.tabs.not_used_documents', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'notuseddocuments',
        ]);
    }

    /**
     * Lightweight HTML fragment for the Notes tab only (lazy-load).
     * Pin, type pills, and matter filter re-bind after inject via notes-tab.js.
     * Personal Details / Activity feed / sidebar remain eager on detail().
     */
    public function notesTab(Request $request, $client_id = null, $client_unique_matter_ref_no = null)
    {
        if (empty($client_id)) {
            abort(404);
        }

        $encodeId = $client_id;
        $id = $this->decodeString($client_id);

        if ($client_unique_matter_ref_no && ClientDetailTabs::isKnownSlug((string) $client_unique_matter_ref_no)) {
            $client_unique_matter_ref_no = null;
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
            abort(403);
        }

        $fetchedData = Admin::where('id', (int) $id)->whereIn('type', ['client', 'lead'])->first();
        if (! $fetchedData) {
            abort(404);
        }

        $clientNotes = Note::where('client_id', (int) $id)
            ->whereNull('assigned_to')
            ->where('type', 'client')
            ->with('user')
            ->orderBy('pin', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->get();

        return view('crm.clients.tabs.notes', [
            'fetchedData' => $fetchedData,
            'encodeId' => $encodeId,
            'id1' => $client_unique_matter_ref_no,
            'activeTab' => 'noteterm',
            'clientNotes' => $clientNotes,
        ]);
    }

    protected function googleReviewCrmTemplateExists(): bool
    {
        if ($this->googleReviewCrmTemplateExistsCache !== null) {
            return $this->googleReviewCrmTemplateExistsCache;
        }

        $this->googleReviewCrmTemplateExistsCache = EmailTemplate::crm()
            ->where(function ($q) {
                $q->where('alias', 'google_review')
                    ->orWhere('name', 'like', '%Google Review%');
            })
            ->exists();

        return $this->googleReviewCrmTemplateExistsCache;
    }

    /**
     * Staff matching config `crm.google_review_reminder_exclude_role_ids` or
     * `crm.google_review_reminder_exclude_office_ids` must not see the reminder
     * modal or change reminder state via API (Calling, Accounts, INDIA office, etc.).
     */
    protected function currentStaffIsExcludedFromGoogleReviewReminder(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $roleId = (int) ($user->role ?? 0);
        $excludedRoles = config('crm.google_review_reminder_exclude_role_ids', [14, 15]);
        if ($roleId > 0 && in_array($roleId, $excludedRoles, true)) {
            return true;
        }

        $officeId = (int) ($user->office_id ?? 0);
        $excludedOffices = config('crm.google_review_reminder_exclude_office_ids', [8]);

        return $officeId > 0 && in_array($officeId, $excludedOffices, true);
    }

    protected function shouldShowGoogleReviewReminderModal(Admin $record): bool
    {
        // Master switch: keeps modal/routes/SMS code intact; only stops auto-open.
        if (! config('crm.google_review_reminder_enabled', false)) {
            return false;
        }

        if ($this->currentStaffIsExcludedFromGoogleReviewReminder()) {
            return false;
        }

        if ($record->is_company) {
            return false;
        }
        if ((int) ($record->is_archived ?? 0) === 1) {
            return false;
        }
        if (! in_array($record->type, ['client', 'lead'], true)) {
            return false;
        }
        if (trim((string) $record->email) === '') {
            return false;
        }

        $status = strtolower(trim((string) ($record->google_review_reminder_status ?? '')));
        if (in_array($status, [
            Admin::GOOGLE_REVIEW_REMINDER_NOT_INTERESTED,
            Admin::GOOGLE_REVIEW_REMINDER_REVIEW_RECEIVED,
        ], true)) {
            return false;
        }

        $until = $record->google_review_reminder_snooze_until;
        if ($until && $until->isFuture()) {
            return false;
        }

        if (! $this->googleReviewCrmTemplateExists()) {
            return false;
        }

        return true;
    }

    /**
     * Verify client/company form details (re-verifiable).
     * Saves details_verified_at / details_verified_by on admins and logs to activities_logs.
     */
    public function verifyDetails(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|integer|min:1',
        ]);

        $client = Admin::query()
            ->where('id', $validated['client_id'])
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $client) {
            return response()->json(['status' => 0, 'message' => 'Record not found.'], 404);
        }

        if ((int) ($client->is_archived ?? 0) === 1) {
            return response()->json(['status' => 0, 'message' => 'Archived records cannot be verified.'], 422);
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $client->id, Auth::user())) {
            return response()->json(['status' => 0, 'message' => 'Unauthorized.'], 403);
        }

        $staff = Auth::user();
        $staffId = (int) ($staff->id ?? 0);
        if ($staffId < 1) {
            return response()->json(['status' => 0, 'message' => 'Unauthorized.'], 403);
        }

        $verifiedAt = now();
        $client->details_verified_at = $verifiedAt;
        $client->details_verified_by = $staffId;
        $client->save();

        $staffName = trim(($staff->first_name ?? '').' '.($staff->last_name ?? ''));
        if ($staffName === '') {
            $staffName = $staff->email ?? 'Staff';
        }

        $label = $client->is_company ? 'company' : ($client->type === 'lead' ? 'lead' : 'client');
        $verifiedAtDisplay = $verifiedAt->format('d/m/Y g:i A');

        $this->logClientActivity(
            (int) $client->id,
            'verified '.$label.' details',
            '<p><strong>Verified By:</strong> '.e($staffName).'</p>'
                .'<p><strong>Verified At:</strong> '.e($verifiedAtDisplay).'</p>',
            'activity'
        );

        return response()->json([
            'status' => 1,
            'message' => 'Details verified successfully.',
            'verified_by' => $staffName,
            'verified_at' => $verifiedAtDisplay,
            'verified_at_iso' => $verifiedAt->toIso8601String(),
        ]);
    }

    public function updateGoogleReviewReminder(Request $request)
    {
        if ($this->currentStaffIsExcludedFromGoogleReviewReminder()) {
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'client_id' => 'required|integer|min:1',
            'action' => 'required|in:snooze,snooze_one_day,not_interested,review_received',
        ]);

        $admin = Admin::query()
            ->where('id', $validated['client_id'])
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $admin || $admin->is_company) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if ((int) ($admin->is_archived ?? 0) === 1) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $admin->id, Auth::user())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        switch ($validated['action']) {
            case 'snooze':
                $admin->google_review_reminder_snooze_until = Carbon::now()->addWeek();
                break;
            case 'snooze_one_day':
                $admin->google_review_reminder_snooze_until = Carbon::now()->addDay();
                break;
            case 'not_interested':
                $admin->google_review_reminder_status = Admin::GOOGLE_REVIEW_REMINDER_NOT_INTERESTED;
                $admin->google_review_reminder_snooze_until = null;
                break;
            case 'review_received':
                $admin->google_review_reminder_status = Admin::GOOGLE_REVIEW_REMINDER_REVIEW_RECEIVED;
                $admin->google_review_reminder_snooze_until = null;
                break;
        }

        $admin->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Send SMS with Google review link from the client/lead detail reminder modal.
     * Looks up the active SMS template by title "Google_review_link" (case-insensitive),
     * falling back to the legacy title "Google review link". No env/config involvement.
     * Available template variables: {first_name}, {last_name}.
     */
    public function sendGoogleReviewReminderSms(Request $request)
    {
        if ($this->currentStaffIsExcludedFromGoogleReviewReminder()) {
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'client_id' => 'required|integer|min:1',
        ]);

        $admin = Admin::query()
            ->where('id', $validated['client_id'])
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (! $admin || $admin->is_company) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if ((int) ($admin->is_archived ?? 0) === 1) {
            return response()->json(['ok' => false, 'message' => 'Record not found'], 404);
        }

        if (! StaffClientVisibility::canAccessClientOrLead((int) $admin->id, Auth::user())) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        $rawPhone = trim((string) ($admin->country_code ?? '')).trim((string) ($admin->phone ?? ''));
        if ($rawPhone === '') {
            return response()->json(['ok' => false, 'message' => 'No phone number on file for this contact'], 422);
        }

        $rawFirst = trim((string) ($admin->first_name ?? ''));
        $firstDisplay = $rawFirst !== ''
            ? mb_convert_case(mb_strtolower($rawFirst), MB_CASE_TITLE, 'UTF-8')
            : 'there';

        $variables = [
            'first_name' => $firstDisplay,
            'last_name' => trim((string) ($admin->last_name ?? '')),
        ];

        $template = null;
        foreach (['Google_review_link', 'Google review link'] as $tryTitle) {
            $found = SmsTemplate::active()
                ->whereRaw('lower(title) = ?', [mb_strtolower($tryTitle)])
                ->orderBy('id')
                ->first();
            if ($found) {
                $template = $found;
                break;
            }
        }

        if (! $template) {
            return response()->json([
                'ok' => false,
                'message' => 'Google review SMS template not found. Please ensure an active SMS template titled "Google_review_link" exists in the Admin Console.',
            ], 422);
        }

        $result = $this->smsManager->sendFromTemplate(
            $rawPhone,
            (int) $template->id,
            $variables,
            ['client_id' => (int) $admin->id]
        );

        if ($result['success'] ?? false) {
            return response()->json([
                'ok' => true,
                'message' => 'Review link sent by SMS',
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => $result['message'] ?? $result['error'] ?? 'Failed to send SMS',
        ], 422);
    }

    // Update session to be complete
    public function updatesessioncompleted(Request $request, CheckinLog $checkinLog)
    {
        $data = $request->all(); // dd($data['client_id']);
        $sessionExist = CheckinLog::where('client_id', $data['client_id'])
            ->where('status', 2)
            ->update(['status' => 1]);
        if ($sessionExist) {
            $response['status'] = true;
            $response['message'] = 'Session completed successfully';
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        echo json_encode($response);
    }

    public function getrecipients(Request $request)
    {
        $squery = $request->q;
        if ($squery != '') {
            $d = '';
            $squeryLower = strtolower($squery);
            $clients = Admin::with('company')
                ->where('is_archived', '=', 0)
                ->whereIn('type', ['client', 'lead'])
                ->where(
                    function ($query) use ($squeryLower) {
                        return $query
                            ->whereRaw('LOWER(email) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(client_id) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$squeryLower.'%'])
                            ->orWhereHas('company', function ($q) use ($squeryLower) {
                                $q->whereRaw('LOWER(company_name) LIKE ?', ['%'.$squeryLower.'%']);
                            });
                    });
            StaffClientVisibility::restrictAdminEloquentQuery($clients);
            $clients = $clients->get();

            // Exclude contact persons when their company is already in results (avoid duplicates)
            $companyIdsInResults = $clients->where('is_company', true)->pluck('id')->toArray();
            $contactPersonIdsToExclude = Company::whereIn('admin_id', $companyIdsInResults)
                ->pluck('contact_person_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            $contactPersonIdsToExclude = array_values(array_filter($contactPersonIdsToExclude, function ($pid) use ($squery) {
                return ! $this->globalSearchQueryMatchesContactPerson((int) $pid, $squery);
            }));

            $items = [];
            foreach ($clients as $clint) {
                if (! $clint->is_company && in_array($clint->id, $contactPersonIdsToExclude)) {
                    continue; // Skip contact person - their company is already in results
                }
                $displayName = $clint->company_name_or_personal_name;
                $items[] = ['name' => $displayName, 'email' => $clint->email, 'status' => $clint->type, 'id' => $clint->id, 'cid' => base64_encode(convert_uuencode(@$clint->id))];
            }

            $litems = [];
            $m = array_merge($items, $litems);
            echo json_encode(['items' => $m]);
        }
    }

    public function getonlyclientrecipients(Request $request)
    {
        $squery = $request->q;
        if ($squery != '') {
            $d = '';
            $squeryLower = strtolower($squery);
            $clients = Admin::with('company')
                ->where('is_archived', '=', 0)
                ->whereIn('type', ['client', 'lead'])
                ->where(
                    function ($query) use ($squeryLower) {
                        return $query
                            ->whereRaw('LOWER(email) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(first_name) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(client_id) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.$squeryLower.'%'])
                            ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%'.$squeryLower.'%'])
                            ->orWhereHas('company', function ($q) use ($squeryLower) {
                                $q->whereRaw('LOWER(company_name) LIKE ?', ['%'.$squeryLower.'%']);
                            });
                    });
            StaffClientVisibility::restrictAdminEloquentQuery($clients);
            $clients = $clients->get();

            // Exclude contact persons when their company is already in results (avoid duplicates)
            $companyIdsInResults = $clients->where('is_company', true)->pluck('id')->toArray();
            $contactPersonIdsToExclude = Company::whereIn('admin_id', $companyIdsInResults)
                ->pluck('contact_person_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            $contactPersonIdsToExclude = array_values(array_filter($contactPersonIdsToExclude, function ($pid) use ($squery) {
                return ! $this->globalSearchQueryMatchesContactPerson((int) $pid, $squery);
            }));

            $items = [];
            foreach ($clients as $clint) {
                if (! $clint->is_company && in_array($clint->id, $contactPersonIdsToExclude)) {
                    continue; // Skip contact person - their company is already in results
                }
                $displayName = $clint->company_name_or_personal_name;
                $items[] = ['name' => $displayName, 'email' => $clint->email, 'status' => $clint->type, 'id' => $clint->id, 'cid' => base64_encode(convert_uuencode(@$clint->id))];
            }

            $litems = [];

            $m = array_merge($items, $litems);
            echo json_encode(['items' => $m]);
        }
    }

    /**
     * Get staff for assignment/search (e.g. assignee dropdown).
     * Returns staff from staff table with pagination.
     */
    public function getAllStaff(Request $request)
    {
        $query = Staff::query()->select('id', 'first_name', 'last_name', 'email');
        if ($request->q) {
            $q = '%'.strtolower($request->q).'%';
            $query->whereRaw('LOWER(first_name) LIKE ? OR LOWER(last_name) LIKE ?', [$q, $q]);
        }

        return $query->paginate(10, ['*'], 'page', $request->page ?? 1)->toArray();
    }
}
