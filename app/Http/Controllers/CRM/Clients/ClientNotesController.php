<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Helpers\IconHelper;
use App\Http\Controllers\Controller;
use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Staff;
use App\Services\StaffWorkloadService;
use App\Support\NoteDescriptionHtml;
// use App\Models\OnlineForm; // REMOVED: OnlineForm model has been deleted
use App\Traits\LogsClientActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

/**
| * ClientNotesController
| *
| * Handles all note-related operations including creating, updating,
| * viewing, deleting, and pinning notes.
| *
| * Maps to: resources/views/Admin/clients/tabs/notes.blade.php
| */
class ClientNotesController extends Controller
{
    use LogsClientActivity;

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
     * Create or update a note
     *
     * @return json
     */
    public function createnote(Request $request)
    {
        // dd($request->all());
        $response = []; // Initialize response array
        $isUpdate = isset($request->noteid) && $request->noteid != '';
        $changedFields = [];
        $oldNote = null;

        if ($isUpdate) {
            $obj = Note::find($request->noteid);
            $oldNote = $obj->replicate(); // Keep a copy of old values for tracking changes
        } else {
            $obj = new Note;
            // Title field may not exist in simple form, use default if not provided
            $obj->title = $request->title ?? '';
            $obj->matter_id = $request->matter_id;
        }

        // Track changes for updates
        if ($isUpdate && $oldNote) {
            // Only track title changes if title is provided in request
            if (isset($request->title) && $oldNote->title !== $request->title) {
                $changedFields['Title'] = [
                    'old' => $oldNote->title,
                    'new' => $request->title,
                ];
            }
            if ($oldNote->description !== $request->description) {
                $changedFields['Description'] = [
                    'old' => $oldNote->description ? substr(strip_tags($oldNote->description), 0, 50).'...' : '(empty)',
                    'new' => $request->description ? substr(strip_tags($request->description), 0, 50).'...' : '(empty)',
                ];
            }
            if ($oldNote->task_group !== $request->task_group) {
                $changedFields['Note Type'] = [
                    'old' => $oldNote->task_group ?? 'Others',
                    'new' => $request->task_group ?? 'Others',
                ];
            }
        }

        $obj->client_id = $request->client_id;
        $obj->user_id = Auth::user()->id;
        $obj->description = $request->description;
        $obj->mail_id = $request->mailid;
        $obj->type = $request->vtype;
        /*if(isset($request->note_deadline_checkbox) && $request->note_deadline_checkbox != ''){
            if($request->note_deadline_checkbox == 1){
                $obj->note_deadline = $request->note_deadline;
            } else {
                $obj->note_deadline = NULL;
            }
        } else {
            $obj->note_deadline = NULL;
        }*/
        $obj->mobile_number = $request->mobileNumber ?? null; // Handle case when mobileNumber is not provided
        $obj->task_group = $request->task_group;

        // PostgreSQL NOT NULL constraints - must set these fields
        if (! $isUpdate) {
            $obj->pin = 0; // Default to not pinned
            $obj->is_action = 0; // Default to not an action
            $obj->status = '0'; // Default status
        }

        try {
            $saved = $isUpdate
                ? Note::withoutTimestamps(fn () => $obj->save())
                : $obj->save();

            if ($saved) {
                // BUGFIX #5: Log activity for BOTH client and lead notes (not just client)
                if ($request->vtype == 'client' || $request->vtype == 'lead') {
                    try {
                        // Get note type for enhanced subject line with proper formatting
                        $taskGroup = $request->task_group ?? 'General';
                        $noteTypeFormatted = ucfirst(strtolower($taskGroup));

                        // Determine the entity ID to use (client_id for client, lead_id for lead)
                        $entityId = $request->vtype == 'client' ? $request->client_id : ($request->lead_id ?? $request->client_id);

                        // Get matter reference (like TGV_1) - only for clients
                        // IMPORTANT: Only include matter reference if a specific matter was explicitly selected
                        $matterReference = '';
                        if ($request->vtype == 'client') {
                            if (isset($request->matter_id) && $request->matter_id != '' && $request->matter_id != null) {
                                $matter = ClientMatter::find($request->matter_id);
                                if ($matter && $matter->client_unique_matter_no) {
                                    $matterReference = $matter->client_unique_matter_no;
                                }
                            }
                            // DO NOT fetch latest matter automatically if no matter was selected
                            // This was causing confusion in Activity Feed for general client notes
                        }

                        // Format subject line with action word
                        $entityType = $request->vtype == 'client' ? 'Client' : 'Lead';
                        $callNumber = trim((string) ($request->mobileNumber ?? ''));
                        $activityPhonePrefix = $callNumber !== ''
                            ? '<p class="activity-note-call-number"><strong>Number:</strong> '.htmlspecialchars($callNumber, ENT_QUOTES, 'UTF-8').'</p>'
                            : '';
                        if ($isUpdate) {
                            // "updated Call Notes - TGV_1" or "updated Lead Call Notes"
                            $subjectLine = ! empty($matterReference)
                                ? "updated {$noteTypeFormatted} Notes - {$matterReference}"
                                : "updated {$entityType} {$noteTypeFormatted} Notes";

                            // Enhanced update logging with change tracking
                            if (! empty($changedFields)) {
                                $this->logClientActivityWithChanges(
                                    $entityId,
                                    $subjectLine,
                                    $changedFields,
                                    'note',
                                    $activityPhonePrefix
                                );
                            } else {
                                // Log full description without truncation
                                $description = $activityPhonePrefix.$request->description;
                                $this->logClientActivity(
                                    $entityId,
                                    $subjectLine,
                                    $description,
                                    'note'
                                );
                            }
                        } else {
                            // "added Call Notes - TGV_1" or "added Lead Call Notes"
                            $subjectLine = ! empty($matterReference)
                                ? "added {$noteTypeFormatted} Notes - {$matterReference}"
                                : "added {$entityType} {$noteTypeFormatted} Notes";

                            // Enhanced create logging - Log full description without truncation
                            $description = $activityPhonePrefix.$request->description;
                            $this->logClientActivity(
                                $entityId,
                                $subjectLine,
                                $description,
                                'note'
                            );
                        }
                    } catch (\Exception $logError) {
                        // Log the error but don't fail the note creation
                        Log::warning('Error logging note activity: '.$logError->getMessage(), [
                            'note_id' => $obj->id ?? null,
                            'entity_id' => $entityId ?? null,
                            'vtype' => $request->vtype,
                            'trace' => $logError->getTraceAsString(),
                        ]);
                    }

                    // Update date in client matter table
                    if (isset($request->matter_id) && $request->matter_id != '') {
                        try {
                            $obj1 = ClientMatter::find($request->matter_id);
                            if ($obj1) {
                                $obj1->updated_at = date('Y-m-d H:i:s');
                                $obj1->save();
                            }
                        } catch (\Exception $matterError) {
                            // Log but don't fail
                            Log::warning('Error updating matter timestamp: '.$matterError->getMessage());
                        }
                    }
                }

                if (! $obj->is_action && in_array((string) ($obj->task_group ?? ''), ['Call', 'In-Person'], true)) {
                    StaffWorkloadService::forgetForStaff((int) Auth::id());
                }

                $response['status'] = true;
                if ($isUpdate) {
                    $response['message'] = 'You have successfully updated Note';
                } else {
                    $response['message'] = 'You have successfully added Note';
                }
            } else {
                $response['status'] = false;
                $response['message'] = 'Please try again';
            }
        } catch (\Exception $e) {
            Log::error('Error saving note: '.$e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            $response['status'] = false;
            $response['message'] = 'Error saving note. Please try again.';
        }

        // Use proper Laravel response to prevent HTML error output
        return response()->json($response, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Update note datetime
     *
     * @return json
     */
    public function updateNoteDatetime(Request $request)
    {
        $note_id = $request->note_id;
        $datetime = $request->datetime;

        try {
            $carbonDateTime = Carbon::parse($datetime);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid date and time format',
            ]);
        }

        // Find note with specific conditions
        $note = Note::where('id', $note_id)
            ->whereNull('assigned_to')
            ->whereNull('unique_group_id')
            ->first();

        if ($note) {
            $note->updated_at = $carbonDateTime; // Carbon instance
            $saved = $note->save();

            if ($saved) {
                $response['status'] = true;
                $response['message'] = 'Date and time updated successfully';
            } else {
                $response['status'] = false;
                $response['message'] = 'Failed to update date and time';
            }
        } else {
            $response['status'] = false;
            $response['message'] = 'Note not found or does not meet the criteria';
        }

        return response()->json($response);
    }

    /**
     * Get note details for editing
     *
     * @return json
     */
    public function getnotedetail(Request $request)
    {
        $note_id = $request->note_id; // dd($note_id);
        if (Note::where('id', $note_id)->exists()) {
            $data = Note::select('title', 'description', 'task_group', 'mobile_number')->where('id', $note_id)->first();
            $response['status'] = true;
            $response['data'] = $data;
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        echo json_encode($response);
    }

    /**
     * View note details
     *
     * @return json
     */
    public function viewnotedetail(Request $request)
    {
        $note_id = $request->note_id;
        if (Note::where('id', $note_id)->exists()) {
            $data = Note::select('title', 'description', 'user_id', 'updated_at')->where('id', $note_id)->first();
            $admin = Admin::where('id', $data->user_id)->first();
            $s = substr(@$admin->first_name, 0, 1);
            $data->admin = $s;
            $response['status'] = true;
            $response['data'] = $data;
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        echo json_encode($response);
    }

    /**
     * View application note details
     *
     * @return json
     */
    public function viewapplicationnote(Request $request)
    {
        $note_id = $request->note_id;
        if (ActivitiesLog::where('activity_type', 'note')->where('use_for', 'matter')->where('id', $note_id)->exists()) {
            $data = ActivitiesLog::select('subject as title', 'description', 'created_by as user_id', 'updated_at')->where('activity_type', 'note')->where('use_for', 'matter')->where('id', $note_id)->first();
            $admin = Admin::where('id', $data->user_id)->first();
            $s = substr(@$admin->first_name, 0, 1);
            $data->admin = $s;
            $response['status'] = true;
            $response['data'] = $data;
        } else {
            $response['status'] = false;
            $response['message'] = 'Please try again';
        }
        echo json_encode($response);
    }

    /**
     * Get notes list for Notes Tab (redesigned)
     *
     * @return html
     */
    public function getnotes(Request $request)
    {
        $client_id = $request->clientid;
        $type = $request->type;
        $task_group = $request->task_group;
        // if($task_group == ''){
        $notelist = Note::where('client_id', $client_id)->whereNull('assigned_to')->where('type', $type)->orderby('pin', 'DESC')->orderBy('updated_at', 'DESC')->get();
        /*}else{
            $notelist = Note::where('client_id',$client_id)->whereNull('assigned_to')->where('type',$type)->where('task_group',$task_group)->orderby('pin', 'DESC')->orderBy('created_at', 'DESC')->get();
        }*/
        ob_start();
        foreach ($notelist as $list) {
            $staff = Staff::where('id', $list->user_id)->first();
            $authorFirstName = $staff ? ($staff->first_name ?? 'NA') : 'NA';
            $authorLastName = $staff ? ($staff->last_name ?? 'NA') : 'NA';

            // Determine type label, class, and inline class (match notes.blade.php)
            if ($list->task_group === null || $list->task_group === '') {
                $typeLabel = 'Others';
                $typeClass = 'note-type-others';
                $typeInlineClass = 'others';
            } else {
                $type11 = strtolower($list->task_group);
                $typeLabel = 'Others';
                $typeClass = 'note-type-others';
                $typeInlineClass = 'others';

                if (strpos($type11, 'call') !== false) {
                    $typeLabel = 'Call';
                    $typeClass = 'note-type-call';
                    $typeInlineClass = 'call';
                } elseif (strpos($type11, 'email') !== false) {
                    $typeLabel = 'Email';
                    $typeClass = 'note-type-email';
                    $typeInlineClass = 'email';
                } elseif (strpos($type11, 'in-person') !== false) {
                    $typeLabel = 'In-Person';
                    $typeClass = 'note-type-inperson';
                    $typeInlineClass = 'inperson';
                } elseif (strpos($type11, 'others') !== false) {
                    $typeLabel = 'Others';
                    $typeClass = 'note-type-others';
                    $typeInlineClass = 'others';
                } elseif (strpos($type11, 'attention') !== false) {
                    $typeLabel = 'Attention';
                    $typeClass = 'note-type-attention';
                    $typeInlineClass = 'attention';
                }
            }
            ?>
            <div class="note-card-redesign <?php if ($list->pin == 1) {
                echo 'pinned';
            } ?>" data-matterid="<?php echo $list->matter_id; ?>" id="note_id_<?php echo $list->id; ?>" data-id="<?php echo $list->id; ?>" data-type="<?php echo $typeLabel; ?>">
                <?php if ($list->pin == 1) { ?>
                    <div class="pined_note">
                        <?php echo IconHelper::fromLegacy('fa fa-thumb-tack'); ?>
                    </div>
                <?php } ?>

                <div class="date-time-menu-container">
                    <span class="author-updated-date-time"><?php echo date('d/m/Y h:i A', strtotime($list->updated_at)); ?></span>
                    <div class="note-toggle-btn-div">
                        <div class="dropdown">
                            <button class="btn btn-link dropdown-toggle note-toggle-btn-div-type" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php echo IconHelper::fromLegacy('fa fa-ellipsis-v'); ?>
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item opennoteform" data-id="<?php echo $list->id; ?>" href="javascript:;">Edit</a>
                                <?php if (Auth::user()->role == 1 || Auth::user()->role == 16) { ?>
                                    <a class="dropdown-item editdatetime" data-id="<?php echo $list->id; ?>" href="javascript:;">Edit Date Time</a>
                                <?php }?>
                                <a data-id="<?php echo $list->id; ?>" data-href="deletenote" class="dropdown-item deletenote" href="javascript:;">Delete</a>
                                <?php if ($list->pin == 1) { ?>
                                    <a data-id="<?php echo $list->id; ?>" class="dropdown-item pinnote" href="javascript:;">Unpin</a>
                                <?php } else { ?>
                                    <a data-id="<?php echo $list->id; ?>" class="dropdown-item pinnote" href="javascript:;">Pin</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="note-card-info">
                    <span class="author-name-created"><?php echo htmlspecialchars($authorFirstName); ?> <?php echo htmlspecialchars($authorLastName); ?> added the</span><span class="note-type-inline <?php echo $typeInlineClass; ?>"><?php echo $typeLabel; ?> notes</span>
                </div>
                <?php
                $notePhone = trim((string) ($list->mobile_number ?? ''));
            if ($notePhone !== '') { ?>
                <div class="note-meta-redesign" style="margin-bottom: 10px;">
                    <?php echo IconHelper::fromLegacy('fas fa-phone'); ?>
                    <strong style="margin-left: 6px;">Number:</strong> <?php echo htmlspecialchars($notePhone); ?>
                </div>
                <?php } ?>

                <div class="note-content-redesign">
                    <?php
                echo NoteDescriptionHtml::forDisplay($list->description ?? '');
            ?>
                </div>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Delete a note
     *
     * @return json
     */
    public function deletenote(Request $request)
    {
        $note_id = $request->note_id;
        if (Note::where('id', $note_id)->exists()) {
            $data = Note::select('client_id', 'title', 'description', 'task_group', 'type', 'mobile_number')->where('id', $note_id)->first();
            $res = DB::table('notes')->where('id', @$note_id)->delete();
            if ($res) {
                if ($data->type == 'client') {
                    // Enhanced delete logging with note type
                    $taskGroup = $data->task_group ?? 'General';
                    $noteTypeFormatted = ucfirst(strtolower($taskGroup));

                    $mnDel = trim((string) ($data->mobile_number ?? ''));
                    $deletePhonePrefix = $mnDel !== ''
                        ? '<p class="activity-note-call-number"><strong>Number:</strong> '.htmlspecialchars($mnDel, ENT_QUOTES, 'UTF-8').'</p>'
                        : '';
                    $description = $deletePhonePrefix.$data->description;

                    // Format as "deleted Call Notes"
                    $this->logClientActivity(
                        $data->client_id,
                        "deleted {$noteTypeFormatted} Notes",
                        $description,
                        'note'
                    );
                }
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

    /**
     * Pin or unpin a note
     *
     * @return json
     */
    public function pinnote(Request $request)
    {
        $noteId = $request->input('note_id');

        $note = $noteId ? Note::find($noteId) : null;

        if (! $note) {
            return response()->json(['status' => false, 'message' => 'Record not found']);
        }

        $currentPin = (int) $note->pin;
        $newPin = $currentPin ? 0 : 1;

        Note::withoutTimestamps(function () use ($note, $newPin) {
            $note->pin = $newPin;
            $note->save();
        });

        return response()->json([
            'status' => true,
            'message' => $newPin ? 'Note pinned successfully' : 'Note unpinned successfully',
        ]);
    }

    /**
     * Save previous visa information - DEPRECATED Phase 4
     * prev_visa column has been dropped from admins table.
     */
    public function saveprevvisa(Request $request)
    {
        return redirect()->back()->with('error', 'Previous visa functionality has been deprecated (prev_visa column removed).');
    }

    /**
     * Save online form data
     *
     * @return Redirect
     *
     * REMOVED: OnlineForm model has been deleted - this method is disabled
     */
    public function saveonlineform(Request $request)
    {
        // Online form functionality disabled - OnlineForm model has been removed
        return redirect()->back()->with('error', 'Online form functionality has been disabled - OnlineForm model has been removed');
    }
}
