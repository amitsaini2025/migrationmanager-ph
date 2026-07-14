<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use App\Models\Admin;
use App\Models\ActivitiesLog;
use App\Models\CpDocChecklist;
use App\Models\Document;
use App\Models\ClientPortalDetailAudit;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientExperience;
use App\Models\ClientMatter;
use App\Models\ClientOccupation;
use App\Models\ClientPassportInformation;
use App\Models\ClientQualification;
use App\Models\ClientTestScore;
use App\Models\ClientTravelInformation;
use App\Models\ClientVisaCountry;
use App\Services\ClientPortalActionNoteService;
use App\Services\MatterEmailBodyCleanupService;
use App\Services\MessageAttachmentStorageService;
use App\Models\WorkflowStage;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Helpers\IconHelper;
use App\Events\MessageReceived;
use App\Events\UnreadCountUpdated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\FCMService;
use App\Services\CrmAccess\CrmAccessService;

/**
 * ClientPortalController
 * 
 * Handles all client portal functionality including:
 * - Portal user management (activating/deactivating access, sending credentials)
 * - Application operations (stages, documents, notes, messaging)
 * - Client portal tab functionality
 * 
 * Previously split between ApplicationsController and ClientPortalController,
 * now consolidated into a single controller for all client portal operations.
 */
class ClientPortalController extends Controller
{
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
     * Toggle Client Portal Status and Send Email
     */
    public function toggleClientPortal(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'status' => 'required|in:true,false,1,0'
            ]);

            $clientId = $request->client_id;
            $status = ($request->status === true || $request->status === 'true' || $request->status === '1' || $request->status === 1) ? 1 : 0;

            // Update the client's cp_status
            $client = \App\Models\Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();

            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $client->cp_status = $status;
            
            // Handle password based on status
            if ($status == 1) {
                // Generate and save password when activating client portal
                $randomPassword = Str::random(12);
                $hashedPassword = Hash::make($randomPassword);
                $client->password = $hashedPassword;
            } else {
                // Clear password when deactivating client portal for security
                $client->password = null;
            }
            
            $client->save();

            // Send appropriate email based on status change
            if ($status == 1) {
                // Status is being turned ON - send activation email with password
                $this->sendClientPortalActivationEmail($client, $randomPassword);
            } else {
                // Status is being turned OFF - send deactivation email
                $this->sendClientPortalDeactivationEmail($client);
            }

            return response()->json([
                'success' => true,
                'message' => $status ? 'Client Portal activated and email sent successfully' : 'Client Portal deactivated and email sent successfully',
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating client portal status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send Client Portal activation email
     */
    private function sendClientPortalActivationEmail($client, $password)
    {
        try {
            // Get client's email directly from admins table
            $emailAddress = $client->email;

            if (!$emailAddress) {
                throw new \Exception('No email address found for client');
            }

            // Email content
            $subject = 'Client Portal Access Activated - Bansal Immigration';
            $message = "
                <html>
                <body>
                    <h2>Client Portal Access Activated</h2>
                    <p>Dear {$client->first_name} {$client->last_name},</p>
                    <p>Your client portal has been activated successfully. Below are your login credentials:</p>
                    <p><strong>Email:</strong> {$client->email}</p>
                    <p><strong>Password:</strong> {$password}</p>
                    <p>You can now access your client portal using the mobile app with these credentials to view your case details.</p>
                    <p>Download the mobile app from the following link: <a href='https://play.google.com/store/apps/details?id=com.bansalimmigration.clientportal'>https://play.google.com/store/apps/details?id=com.bansalimmigration.clientportal</a></p>
                    <p><strong>Important:</strong> Please keep your login credentials secure and do not share them with anyone. After Login in mboile App you can chnage your password.</p>
                    <p>Please contact us if you have any questions.</p>
                    <br>
                    <p>Best regards,<br>Bansal Immigration Team</p>
                </body>
                </html>
            ";

            // Send email using Mail facade
            Mail::mailer('sendgrid')->send('emails.client_portal_active_email', ['content' => $message], function($message) use ($emailAddress, $subject) {
                $message->to($emailAddress)
                       ->subject($subject)
                       ->from(config('mail.from.address'), config('mail.from.name'));
            });

        } catch (\Exception $e) {
            Log::error('Failed to send client portal activation email: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send Client Portal deactivation email
     */
    private function sendClientPortalDeactivationEmail($client)
    {
        try {
            // Get client's email directly from admins table
            $emailAddress = $client->email;

            if (!$emailAddress) {
                throw new \Exception('No email address found for client');
            }

            // Email content for deactivation
            $subject = 'Client Portal Access Deactivated - Bansal Immigration';
            $message = "
                <html>
                <body>
                    <h2>Client Portal Access Deactivated</h2>
                    <p>Dear {$client->first_name} {$client->last_name},</p>
                    <p>Your client portal access has been deactivated. Now you cannot access the client portal from mobile app.</p>
                    <p>Please contact the administrator for further assistance.</p>
                    <br>
                    <p>Best regards,<br>Bansal Immigration Team</p>
                </body>
                </html>
            ";

            // Send email using Mail facade
            Mail::mailer('sendgrid')->send('emails.client_portal_active_email', ['content' => $message], function($message) use ($emailAddress, $subject) {
                $message->to($emailAddress)
                       ->subject($subject)
                       ->from(config('mail.from.address'), config('mail.from.name'));
            });

        } catch (\Exception $e) {
            Log::error('Failed to send client portal deactivation email: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve Audit Value
     * Updates the admins table with the audit value and removes the audit entry
     */
    public function approveAuditValue(Request $request)
    {
        try {
            $request->validate([
                'audit_id' => 'required|integer',
                'field_name' => 'required|string',
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer'
            ]);

            $auditId = $request->audit_id;
            $fieldName = $request->field_name;
            $clientId = $request->client_id;
            $clientMatterId = $request->client_matter_id;

            // Get the audit entry
            $auditEntry = ClientPortalDetailAudit::find($auditId);

            if (!$auditEntry || $auditEntry->client_id != $clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit entry not found'
                ], 404);
            }

            // Get the client
            $client = Admin::find($clientId);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            DB::beginTransaction();

            try {
                $newValue = $auditEntry->new_value;

                // Map field names to database columns
                $fieldMapping = [
                    'first_name' => 'first_name',
                    'last_name' => 'last_name',
                    'client_id' => 'client_id',
                    'dob' => 'dob',
                    'age' => 'age',
                    'gender' => 'gender',
                    'marital_status' => 'marital_status',
                ];

                if (!isset($fieldMapping[$fieldName])) {
                    throw new \Exception('Invalid field name');
                }

                $dbField = $fieldMapping[$fieldName];

                // Update the admins table
                $client->$dbField = $newValue;
                
                // If DOB is updated, recalculate age
                if ($fieldName === 'dob' && $newValue) {
                    try {
                        $dob = \Carbon\Carbon::parse($newValue);
                        $now = \Carbon\Carbon::now();
                        $age = $dob->diff($now)->format('%y years %m months');
                        $client->age = $age;
                    } catch (\Exception $e) {
                        // Age calculation failed, continue without updating age
                    }
                }
                
                $client->save();

                // Delete the audit entry
                $auditEntry->delete();

                // If DOB is approved, also remove age audit entry since age will be recalculated
                if ($fieldName === 'dob') {
                    $ageAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'age')
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    
                    if ($ageAuditEntry) {
                        $ageAuditEntry->delete();
                    }
                }
                
                // If age is approved, also remove DOB audit entry since they are related
                if ($fieldName === 'age') {
                    $dobAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'dob')
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    
                    if ($dobAuditEntry) {
                        $dobAuditEntry->delete();
                    }
                }

                // Get field label for message
                $fieldLabels = [
                    'first_name' => 'First Name',
                    'last_name' => 'Last Name',
                    'client_id' => 'Client ID',
                    'dob' => 'Date of Birth',
                    'age' => 'Age',
                    'gender' => 'Gender',
                    'marital_status' => 'Marital Status'
                ];

                $fieldLabel = $fieldLabels[$fieldName] ?? $fieldName;

                // Get sender (admin) information
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';

                // Create approval message (for chat)
                $message = "Your Basic Detail {$fieldLabel} related changes approved by Admin. Please check at your end.";

                // Create message record
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $messageId = DB::table('messages')->insertGetId($messageData);

                if ($messageId) {
                    // Insert recipient into pivot table
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Broadcast message via Laravel Reverb (configured via BROADCAST_DRIVER in .env)
                    if (class_exists('\App\Events\MessageSent')) {
                        $senderDisplayStr = $senderName ?: 'Agent';
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderDisplayStr,
                            'sender_name' => $senderDisplayStr,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [[
                                'recipient_id' => $clientId,
                                'recipient' => $client->first_name . ' ' . $client->last_name
                            ]]
                        ];

                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('Failed to broadcast message to client', [
                                'client_id' => $clientId,
                                'message_id' => $messageId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                // Notify client (List Notifications API), badge broadcast, FCM push, and Action page
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatterId) : 'ID: ' . $clientMatterId;
                $actorName = ($sender && (int) $sender->role === 1) ? 'Super admin' : ($senderName ?: 'Staff');
                $fieldUpdateText = 'Field - ' . $fieldLabel . ' update to ' . (is_string($newValue) ? $newValue : (string) $newValue);
                $actionMessage = $actorName . ' approved your detail update request - "' . $fieldUpdateText . '" in ' . $matterNo . '.';

                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0
                ]);

                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('Detail approved: broadcast failed', ['client_id' => $clientId, 'error' => $e->getMessage()]);
                }

                try {
                    $fcmService = new FCMService();
                    $fcmService->sendToUser($clientId, 'Detail update approved', mb_strlen($actionMessage) > 100 ? mb_substr($actionMessage, 0, 100) . '...' : $actionMessage, [
                        'type' => 'detail_approved',
                        'clientMatterId' => (string) $clientMatterId,
                        'matterNo' => $matterNo,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Detail approved: FCM push failed', ['client_id' => $clientId, 'error' => $e->getMessage()]);
                }

                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Change approved and updated successfully. Message sent to client.'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving change: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject Audit Value
     * Removes the audit entry and sends a message to the client
     */
    public function rejectAuditValue(Request $request)
    {
        try {
            $request->validate([
                'audit_id' => 'required|integer',
                'field_name' => 'required|string',
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer'
            ]);

            $auditId = $request->audit_id;
            $fieldName = $request->field_name;
            $clientId = $request->client_id;
            $clientMatterId = $request->client_matter_id;

            // Get the audit entry
            $auditEntry = ClientPortalDetailAudit::find($auditId);

            if (!$auditEntry || $auditEntry->client_id != $clientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Audit entry not found'
                ], 404);
            }

            // Get field label for message
            $fieldLabels = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'client_id' => 'Client ID',
                'dob' => 'Date of Birth',
                'age' => 'Age',
                'gender' => 'Gender',
                'marital_status' => 'Marital Status'
            ];

            $fieldLabel = $fieldLabels[$fieldName] ?? $fieldName;

            DB::beginTransaction();

            try {
                // Delete the audit entry
                $auditEntry->delete();

                // If DOB is rejected, also remove age audit entry since age depends on DOB
                if ($fieldName === 'dob') {
                    $ageAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'age')
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    
                    if ($ageAuditEntry) {
                        $ageAuditEntry->delete();
                    }
                }
                
                // If age is rejected, also remove DOB audit entry since they are related
                if ($fieldName === 'age') {
                    $dobAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'dob')
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    
                    if ($dobAuditEntry) {
                        $dobAuditEntry->delete();
                    }
                }

                // Get sender (admin) information
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';

                // Get client information
                $client = Admin::find($clientId);
                if (!$client) {
                    throw new \Exception('Client not found');
                }

                // Create rejection message
                $message = "Your Basic Detail {$fieldLabel} related changes rejected by Admin. Please try again.";

                // Create message record
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $messageId = DB::table('messages')->insertGetId($messageData);

                if ($messageId) {
                    // Insert recipient into pivot table
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Broadcast message via Laravel Reverb (configured via BROADCAST_DRIVER in .env)
                    if (class_exists('\App\Events\MessageSent')) {
                        $senderDisplayStr = $senderName ?: 'Agent';
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderDisplayStr,
                            'sender_name' => $senderDisplayStr,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [[
                                'recipient_id' => $clientId,
                                'recipient' => $client->first_name . ' ' . $client->last_name
                            ]]
                        ];

                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('Failed to broadcast message to client', [
                                'client_id' => $clientId,
                                'message_id' => $messageId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }

                // Notify client (List Notifications API), badge broadcast, FCM push, and Action page
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatterId) : 'ID: ' . $clientMatterId;
                $actorName = ($sender && (int) $sender->role === 1) ? 'Super admin' : ($senderName ?: 'Staff');
                $rejectedValue = $auditEntry->new_value ?? '';
                $fieldUpdateText = 'Field - ' . $fieldLabel . ' update to ' . (is_string($rejectedValue) ? $rejectedValue : (string) $rejectedValue);
                $actionMessage = $actorName . ' rejected your detail update request - "' . $fieldUpdateText . '" in ' . $matterNo . '.';

                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0
                ]);

                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('Detail rejected: broadcast failed', ['client_id' => $clientId, 'error' => $e->getMessage()]);
                }

                try {
                    $fcmService = new FCMService();
                    $fcmService->sendToUser($clientId, 'Detail update rejected', mb_strlen($actionMessage) > 100 ? mb_substr($actionMessage, 0, 100) . '...' : $actionMessage, [
                        'type' => 'detail_rejected',
                        'clientMatterId' => (string) $clientMatterId,
                        'matterNo' => $matterNo,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Detail rejected: FCM push failed', ['client_id' => $clientId, 'error' => $e->getMessage()]);
                }

                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Change rejected and message sent to client'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting change: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve visa audit: save the audited visa to client_visa_countries and remove from clientportal_details_audit.
     */
    public function approveVisaAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $visaKeys = ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $visaKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending visa audit found for this row.'], 404);
            }

            $visaType = null;
            $visaDescription = null;
            $visaGrantDate = null;
            $visaExpiryDate = null;
            $existingVisaId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'visa' && $entry->meta_type !== null && $entry->meta_type !== '') {
                    $existingVisaId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                }
                switch ($entry->meta_key) {
                    case 'visa_type':
                        $visaType = $v;
                        break;
                    case 'visa_description':
                        $visaDescription = $v;
                        break;
                    case 'visa_grant_date':
                        $visaGrantDate = $this->parseVisaDate($v);
                        break;
                    case 'visa_expiry_date':
                        $visaExpiryDate = $this->parseVisaDate($v);
                        break;
                }
            }
            if ($visaType === null) {
                return response()->json(['success' => false, 'message' => 'Visa type is required.'], 422);
            }

            // meta_type can be a real client_visa_countries id (small integer) or a temp id for "create" (e.g. timestamp)
            // PostgreSQL integer max is 2147483647; values above that cause "out of range" - treat as new visa only
            $maxValidId = 2147483647;
            $isValidExistingId = $existingVisaId !== null && $existingVisaId > 0 && $existingVisaId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientVisaCountry::where('id', $existingVisaId)->where('client_id', $clientId)->exists()) {
                    ClientVisaCountry::where('id', $existingVisaId)->update([
                        'visa_type' => $visaType,
                        'visa_description' => $visaDescription,
                        'visa_grant_date' => $visaGrantDate,
                        'visa_expiry_date' => $visaExpiryDate,
                    ]);
                } else {
                    ClientVisaCountry::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'visa_type' => $visaType,
                        'visa_description' => $visaDescription,
                        'visa_grant_date' => $visaGrantDate,
                        'visa_expiry_date' => $visaExpiryDate,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Visa Information change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approveVisaAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your visa information update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'visa_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approveVisaAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Visa approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving visa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject visa audit: remove the audited visa from clientportal_details_audit and notify client.
     */
    public function rejectVisaAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $visaKeys = ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $visaKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending visa audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Visa Information change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectVisaAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your visa information update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'visa_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectVisaAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Visa change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting visa: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve email audit: save to client_emails and remove from clientportal_details_audit.
     */
    public function approveEmailAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $emailKeys = ['email', 'email_type'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $emailKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending email audit found for this row.'], 404);
            }

            $emailAddress = null;
            $emailType = null;
            $existingEmailId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'email') {
                    $emailAddress = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingEmailId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'email_type') {
                    $emailType = $v;
                }
            }
            if (empty($emailAddress)) {
                return response()->json(['success' => false, 'message' => 'Email address is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingEmailId !== null && $existingEmailId > 0 && $existingEmailId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientEmail::where('id', $existingEmailId)->where('client_id', $clientId)->exists()) {
                    ClientEmail::where('id', $existingEmailId)->update([
                        'email' => $emailAddress,
                        'email_type' => $emailType ?? 'Personal',
                    ]);
                } else {
                    ClientEmail::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'email' => $emailAddress,
                        'email_type' => $emailType ?? 'Personal',
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Email Address change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approveEmailAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your email address update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'email_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approveEmailAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Email approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject email audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectEmailAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $emailKeys = ['email', 'email_type'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $emailKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending email audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Email Address change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectEmailAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your email address update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'email_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectEmailAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Email change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve phone audit: save to client_contacts and remove from clientportal_details_audit.
     */
    public function approvePhoneAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $phoneKeys = ['phone', 'phone_type', 'phone_country_code', 'phone_extension'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $phoneKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending phone audit found for this row.'], 404);
            }

            $phoneNumber = null;
            $contactType = null;
            $countryCode = null;
            $existingPhoneId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'phone') {
                    $phoneNumber = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingPhoneId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'phone_type') {
                    $contactType = $v;
                } elseif ($entry->meta_key === 'phone_country_code') {
                    $countryCode = $v;
                }
            }
            if (empty($phoneNumber)) {
                return response()->json(['success' => false, 'message' => 'Phone number is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingPhoneId !== null && $existingPhoneId > 0 && $existingPhoneId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientContact::where('id', $existingPhoneId)->where('client_id', $clientId)->exists()) {
                    ClientContact::where('id', $existingPhoneId)->update([
                        'phone' => $phoneNumber,
                        'contact_type' => $contactType ?? 'Mobile',
                        'country_code' => $countryCode,
                    ]);
                } else {
                    ClientContact::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'phone' => $phoneNumber,
                        'contact_type' => $contactType ?? 'Mobile',
                        'country_code' => $countryCode,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Phone Number change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approvePhoneAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your phone number update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'phone_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approvePhoneAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Phone approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving phone: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject phone audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectPhoneAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $phoneKeys = ['phone', 'phone_type', 'phone_country_code', 'phone_extension'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $phoneKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending phone audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Phone Number change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectPhoneAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your phone number update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'phone_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectPhoneAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Phone change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting phone: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse visa date from audit (d/m/Y or Y-m-d) to Y-m-d for DB.
     */
    private function parseVisaDate($value)
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return \Carbon\Carbon::parse($value)->format('Y-m-d');
            }
            if (preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $value)) {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Approve passport audit: save to client_passport_informations and remove from clientportal_details_audit.
     */
    public function approvePassportAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $passportKeys = ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $passportKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending passport audit found for this row.'], 404);
            }

            $passportNumber = null;
            $passportCountry = null;
            $passportIssueDate = null;
            $passportExpiryDate = null;
            $existingPassportId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'passport') {
                    $passportNumber = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingPassportId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'passport_country') {
                    $passportCountry = $v;
                } elseif ($entry->meta_key === 'passport_issue_date') {
                    $passportIssueDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'passport_expiry_date') {
                    $passportExpiryDate = $this->parseVisaDate($v);
                }
            }
            if (empty($passportNumber)) {
                return response()->json(['success' => false, 'message' => 'Passport number is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingPassportId !== null && $existingPassportId > 0 && $existingPassportId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientPassportInformation::where('id', $existingPassportId)->where('client_id', $clientId)->exists()) {
                    ClientPassportInformation::where('id', $existingPassportId)->update([
                        'passport' => $passportNumber,
                        'passport_country' => $passportCountry,
                        'passport_issue_date' => $passportIssueDate,
                        'passport_expiry_date' => $passportExpiryDate,
                    ]);
                } else {
                    ClientPassportInformation::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'passport' => $passportNumber,
                        'passport_country' => $passportCountry,
                        'passport_issue_date' => $passportIssueDate,
                        'passport_expiry_date' => $passportExpiryDate,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Passport Information change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approvePassportAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your passport information update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'passport_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approvePassportAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Passport approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving passport: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject passport audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectPassportAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $passportKeys = ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $passportKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending passport audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Passport Information change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectPassportAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your passport information update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'passport_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectPassportAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Passport change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting passport: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve qualification audit: save to client_qualifications and remove from clientportal_details_audit.
     */
    public function approveQualificationAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $qualificationKeys = ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $qualificationKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending qualification audit found for this row.'], 404);
            }

            $level = null;
            $name = null;
            $collegeName = null;
            $campus = null;
            $country = null;
            $state = null;
            $startDate = null;
            $finishDate = null;
            $relevant = false;
            $existingQualificationId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'qualification_level') {
                    $level = $v;
                } elseif ($entry->meta_key === 'qualification_name') {
                    $name = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingQualificationId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'qualification_college_name') {
                    $collegeName = $v;
                } elseif ($entry->meta_key === 'qualification_campus') {
                    $campus = $v;
                } elseif ($entry->meta_key === 'qualification_country') {
                    $country = $v;
                } elseif ($entry->meta_key === 'qualification_state') {
                    $state = $v;
                } elseif ($entry->meta_key === 'qualification_start_date') {
                    $startDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'qualification_finish_date') {
                    $finishDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'qualification_relevant') {
                    $relevant = ($v == '1' || $v == 1);
                }
            }
            if (empty($level) && empty($name)) {
                return response()->json(['success' => false, 'message' => 'Level or name is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingQualificationId !== null && $existingQualificationId > 0 && $existingQualificationId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientQualification::where('id', $existingQualificationId)->where('client_id', $clientId)->exists()) {
                    ClientQualification::where('id', $existingQualificationId)->update([
                        'level' => $level,
                        'name' => $name,
                        'qual_college_name' => $collegeName,
                        'qual_campus' => $campus,
                        'country' => $country,
                        'qual_state' => $state,
                        'start_date' => $startDate,
                        'finish_date' => $finishDate,
                        'relevant_qualification' => $relevant ? 1 : 0,
                    ]);
                } else {
                    ClientQualification::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'level' => $level,
                        'name' => $name,
                        'qual_college_name' => $collegeName,
                        'qual_campus' => $campus,
                        'country' => $country,
                        'qual_state' => $state,
                        'start_date' => $startDate,
                        'finish_date' => $finishDate,
                        'relevant_qualification' => $relevant ? 1 : 0,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Educational Qualification change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approveQualificationAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your educational qualification update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'qualification_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approveQualificationAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Qualification approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving qualification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject qualification audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectQualificationAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $qualificationKeys = ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $qualificationKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending qualification audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Educational Qualification change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectQualificationAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your educational qualification update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'qualification_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectQualificationAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Qualification change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting qualification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve experience audit: save to client_experiences and remove from clientportal_details_audit.
     */
    public function approveExperienceAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $experienceKeys = ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $experienceKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending experience audit found for this row.'], 404);
            }

            $jobTitle = null;
            $jobCode = null;
            $country = null;
            $startDate = null;
            $finishDate = null;
            $relevant = false;
            $employerName = null;
            $state = null;
            $jobType = null;
            $fteMultiplier = null;
            $existingExperienceId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'experience_job_title') {
                    $jobTitle = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingExperienceId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'experience_job_code') {
                    $jobCode = $v;
                } elseif ($entry->meta_key === 'experience_country') {
                    $country = $v;
                } elseif ($entry->meta_key === 'experience_start_date') {
                    $startDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'experience_finish_date') {
                    $finishDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'experience_relevant') {
                    $relevant = ($v == '1' || $v == 1);
                } elseif ($entry->meta_key === 'experience_employer_name') {
                    $employerName = $v;
                } elseif ($entry->meta_key === 'experience_state') {
                    $state = $v;
                } elseif ($entry->meta_key === 'experience_job_type') {
                    $jobType = $v;
                } elseif ($entry->meta_key === 'experience_fte_multiplier') {
                    $fteMultiplier = $v !== null && $v !== '' ? (float) $v : null;
                }
            }
            if (empty($jobTitle)) {
                return response()->json(['success' => false, 'message' => 'Job title is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingExperienceId !== null && $existingExperienceId > 0 && $existingExperienceId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientExperience::where('id', $existingExperienceId)->where('client_id', $clientId)->exists()) {
                    ClientExperience::where('id', $existingExperienceId)->update([
                        'job_title' => $jobTitle,
                        'job_code' => $jobCode,
                        'job_country' => $country,
                        'job_start_date' => $startDate,
                        'job_finish_date' => $finishDate,
                        'relevant_experience' => $relevant ? 1 : 0,
                        'job_emp_name' => $employerName,
                        'job_state' => $state,
                        'job_type' => $jobType,
                        'fte_multiplier' => $fteMultiplier,
                    ]);
                } else {
                    ClientExperience::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'job_title' => $jobTitle,
                        'job_code' => $jobCode,
                        'job_country' => $country,
                        'job_start_date' => $startDate,
                        'job_finish_date' => $finishDate,
                        'relevant_experience' => $relevant ? 1 : 0,
                        'job_emp_name' => $employerName,
                        'job_state' => $state,
                        'job_type' => $jobType,
                        'fte_multiplier' => $fteMultiplier,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Work Experience change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approveExperienceAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your work experience update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'experience_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approveExperienceAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Experience approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving experience: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject experience audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectExperienceAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $experienceKeys = ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $experienceKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending experience audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Work Experience change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectExperienceAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your work experience update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'experience_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectExperienceAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Experience change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting experience: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve occupation audit: save to client_occupations and remove from clientportal_details_audit.
     */
    public function approveOccupationAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $occupationKeys = ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $occupationKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending occupation audit found for this row.'], 404);
            }

            $skillAssessment = null;
            $nominatedOccupation = null;
            $occupationCode = null;
            $assessingAuthority = null;
            $visaSubclass = null;
            $assessmentDate = null;
            $expiryDate = null;
            $referenceNo = null;
            $relevant = false;
            $anzscoId = null;
            $existingOccupationId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'occupation_skill_assessment') {
                    $skillAssessment = $v;
                } elseif ($entry->meta_key === 'occupation_nominated') {
                    $nominatedOccupation = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingOccupationId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'occupation_code') {
                    $occupationCode = $v;
                } elseif ($entry->meta_key === 'occupation_assessing_authority') {
                    $assessingAuthority = $v;
                } elseif ($entry->meta_key === 'occupation_visa_subclass') {
                    $visaSubclass = $v;
                } elseif ($entry->meta_key === 'occupation_assessment_date') {
                    $assessmentDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'occupation_expiry_date') {
                    $expiryDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'occupation_reference_no') {
                    $referenceNo = $v;
                } elseif ($entry->meta_key === 'occupation_relevant') {
                    $relevant = ($v == '1' || $v == 1);
                } elseif ($entry->meta_key === 'occupation_anzsco_id') {
                    $anzscoId = $v !== null && $v !== '' ? (int) $v : null;
                }
            }
            if (empty($nominatedOccupation) && empty($occupationCode)) {
                return response()->json(['success' => false, 'message' => 'Occupation or code is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingOccupationId !== null && $existingOccupationId > 0 && $existingOccupationId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientOccupation::where('id', $existingOccupationId)->where('client_id', $clientId)->exists()) {
                    ClientOccupation::where('id', $existingOccupationId)->update([
                        'skill_assessment' => $skillAssessment,
                        'nomi_occupation' => $nominatedOccupation,
                        'occupation_code' => $occupationCode,
                        'list' => $assessingAuthority,
                        'visa_subclass' => $visaSubclass,
                        'dates' => $assessmentDate,
                        'expiry_dates' => $expiryDate,
                        'occ_reference_no' => $referenceNo,
                        'relevant_occupation' => $relevant ? 1 : 0,
                        'anzsco_occupation_id' => $anzscoId,
                    ]);
                } else {
                    ClientOccupation::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'skill_assessment' => $skillAssessment,
                        'nomi_occupation' => $nominatedOccupation,
                        'occupation_code' => $occupationCode,
                        'list' => $assessingAuthority,
                        'visa_subclass' => $visaSubclass,
                        'dates' => $assessmentDate,
                        'expiry_dates' => $expiryDate,
                        'occ_reference_no' => $referenceNo,
                        'relevant_occupation' => $relevant ? 1 : 0,
                        'anzsco_occupation_id' => $anzscoId,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Occupation change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approveOccupationAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your occupation update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'occupation_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approveOccupationAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Occupation approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving occupation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject occupation audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectOccupationAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $occupationKeys = ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $occupationKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending occupation audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Occupation change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectOccupationAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your occupation update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'occupation_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectOccupationAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Occupation change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting occupation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve test score audit: save to client_testscore and remove from clientportal_details_audit.
     */
    public function approveTestScoreAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $testScoreKeys = ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $testScoreKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending test score audit found for this row.'], 404);
            }

            $testType = null;
            $listening = null;
            $reading = null;
            $writing = null;
            $speaking = null;
            $overallScore = null;
            $testDate = null;
            $referenceNo = null;
            $relevant = false;
            $existingTestScoreId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'test_score_test_type') {
                    $testType = $v;
                    if ($entry->meta_type !== null && $entry->meta_type !== '') {
                        $existingTestScoreId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                } elseif ($entry->meta_key === 'test_score_listening') {
                    $listening = $v !== null && $v !== '' ? (float) $v : null;
                } elseif ($entry->meta_key === 'test_score_reading') {
                    $reading = $v !== null && $v !== '' ? (float) $v : null;
                } elseif ($entry->meta_key === 'test_score_writing') {
                    $writing = $v !== null && $v !== '' ? (float) $v : null;
                } elseif ($entry->meta_key === 'test_score_speaking') {
                    $speaking = $v !== null && $v !== '' ? (float) $v : null;
                } elseif ($entry->meta_key === 'test_score_overall_score') {
                    $overallScore = $v !== null && $v !== '' ? (float) $v : null;
                } elseif ($entry->meta_key === 'test_score_test_date') {
                    $testDate = $this->parseVisaDate($v);
                } elseif ($entry->meta_key === 'test_score_reference_no') {
                    $referenceNo = $v;
                } elseif ($entry->meta_key === 'test_score_relevant') {
                    $relevant = ($v == '1' || $v == 1);
                }
            }
            if (empty($testType)) {
                return response()->json(['success' => false, 'message' => 'Test type is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingTestScoreId !== null && $existingTestScoreId > 0 && $existingTestScoreId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientTestScore::where('id', $existingTestScoreId)->where('client_id', $clientId)->exists()) {
                    ClientTestScore::where('id', $existingTestScoreId)->update([
                        'test_type' => $testType,
                        'listening' => $listening,
                        'reading' => $reading,
                        'writing' => $writing,
                        'speaking' => $speaking,
                        'overall_score' => $overallScore,
                        'test_date' => $testDate,
                        'test_reference_no' => $referenceNo,
                        'relevant_test' => $relevant ? 1 : 0,
                    ]);
                } else {
                    ClientTestScore::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'test_type' => $testType,
                        'listening' => $listening,
                        'reading' => $reading,
                        'writing' => $writing,
                        'speaking' => $speaking,
                        'overall_score' => $overallScore,
                        'test_date' => $testDate,
                        'test_reference_no' => $referenceNo,
                        'relevant_test' => $relevant ? 1 : 0,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }

                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Test Score change was approved by Admin.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('approveTestScoreAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' approved your test score update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'test_score_detail_approved',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('approveTestScoreAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Test score approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error approving test score: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject test score audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectTestScoreAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $testScoreKeys = ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $testScoreKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending test score audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $sender = Auth::guard('admin')->user();
                $senderId = $sender ? $sender->id : null;
                $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
                $message = 'Your Test Score change was rejected by Admin. Please try again.';
                $messageData = [
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now(),
                    'client_matter_id' => $clientMatterId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $messageId = DB::table('messages')->insertGetId($messageData);
                if ($messageId) {
                    MessageRecipient::insert([
                        'message_id' => $messageId,
                        'recipient_id' => $clientId,
                        'recipient' => $client->first_name . ' ' . $client->last_name,
                        'is_read' => false,
                        'read_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if (class_exists('\App\Events\MessageSent')) {
                        $messageForBroadcast = [
                            'id' => $messageId,
                            'message' => $message,
                            'sender' => $senderName,
                            'sender_name' => $senderName,
                            'sender_id' => $senderId,
                            'sent_at' => now()->toISOString(),
                            'created_at' => now()->toISOString(),
                            'client_matter_id' => $clientMatterId,
                            'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                        ];
                        try {
                            broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                        } catch (\Exception $e) {
                            Log::warning('rejectTestScoreAudit broadcast failed', ['error' => $e->getMessage()]);
                        }
                    }
                }
                $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
                $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
                $actionMessage = ($senderName ?: 'Admin') . ' rejected your test score update in ' . $matterNo . '.';
                DB::table('notifications')->insert([
                    'sender_id' => $senderId,
                    'receiver_id' => $clientId,
                    'module_id' => $clientMatterId,
                    'url' => '/details',
                    'notification_type' => 'test_score_detail_rejected',
                    'message' => $actionMessage,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'sender_status' => 1,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                try {
                    $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
                    broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
                } catch (\Exception $e) {
                    Log::warning('rejectTestScoreAudit NotificationCountUpdated failed', ['error' => $e->getMessage()]);
                }
                $clientMatterModel = ClientMatter::find($clientMatterId);
                if ($clientMatterModel) {
                    $this->createClientPortalAction($clientMatterModel, $actionMessage);
                }
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Test score change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting test score: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve address audit: save to client_addresses and remove from clientportal_details_audit.
     */
    public function approveAddressAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $addressKeys = ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $addressKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending address audit found for this row.'], 404);
            }

            $address = null;
            $addressLine1 = null;
            $addressLine2 = null;
            $suburb = null;
            $state = null;
            $postcode = null;
            $country = null;
            $regionalCode = null;
            $startDate = null;
            $endDate = null;
            $isCurrent = false;
            $existingAddressId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'address_line_1' && $entry->meta_type !== null && $entry->meta_type !== '') {
                    $existingAddressId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                }
                switch ($entry->meta_key) {
                    case 'address':
                        $address = $v;
                        break;
                    case 'address_line_1':
                        $addressLine1 = $v;
                        break;
                    case 'address_line_2':
                        $addressLine2 = $v;
                        break;
                    case 'address_suburb':
                        $suburb = $v;
                        break;
                    case 'address_state':
                        $state = $v;
                        break;
                    case 'address_postcode':
                        $postcode = $v;
                        break;
                    case 'address_country':
                        $country = $v;
                        break;
                    case 'address_regional_code':
                        $regionalCode = $v;
                        break;
                    case 'address_start_date':
                        $startDate = $this->parseVisaDate($v);
                        break;
                    case 'address_end_date':
                        $endDate = $this->parseVisaDate($v);
                        break;
                    case 'address_is_current':
                        $isCurrent = ($v == '1' || $v === 1);
                        break;
                }
            }
            if (empty($addressLine1) && empty($address)) {
                return response()->json(['success' => false, 'message' => 'Address line 1 or address is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingAddressId !== null && $existingAddressId > 0 && $existingAddressId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientAddress::where('id', $existingAddressId)->where('client_id', $clientId)->exists()) {
                    ClientAddress::where('id', $existingAddressId)->update([
                        'address' => $address,
                        'address_line_1' => $addressLine1,
                        'address_line_2' => $addressLine2,
                        'suburb' => $suburb,
                        'state' => $state,
                        'zip' => $postcode,
                        'country' => $country,
                        'regional_code' => $regionalCode,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_current' => $isCurrent ? 1 : 0,
                    ]);
                } else {
                    ClientAddress::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'address' => $address,
                        'address_line_1' => $addressLine1,
                        'address_line_2' => $addressLine2,
                        'suburb' => $suburb,
                        'state' => $state,
                        'zip' => $postcode,
                        'country' => $country,
                        'regional_code' => $regionalCode,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_current' => $isCurrent ? 1 : 0,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $this->sendApprovalMessageAndNotify($client, $clientMatterId, 'Address Information', $clientId);
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Address approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error approving address: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject address audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectAddressAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $addressKeys = ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $addressKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending address audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $this->sendRejectionMessageAndNotify($client, $clientMatterId, 'Address Information', $clientId);
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Address change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error rejecting address: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve travel audit: save to client_travel_informations and remove from clientportal_details_audit.
     */
    public function approveTravelAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $travelKeys = ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $travelKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending travel audit found for this row.'], 404);
            }

            $countryVisited = null;
            $arrivalDate = null;
            $departureDate = null;
            $purpose = null;
            $existingTravelId = null;
            foreach ($auditEntries as $entry) {
                $v = $entry->new_value;
                if ($entry->meta_key === 'travel_country_visited' && $entry->meta_type !== null && $entry->meta_type !== '') {
                    $existingTravelId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                }
                switch ($entry->meta_key) {
                    case 'travel_country_visited':
                        $countryVisited = $v;
                        break;
                    case 'travel_arrival_date':
                        $arrivalDate = $this->parseVisaDate($v);
                        break;
                    case 'travel_departure_date':
                        $departureDate = $this->parseVisaDate($v);
                        break;
                    case 'travel_purpose':
                        $purpose = $v;
                        break;
                }
            }
            if (empty($countryVisited)) {
                return response()->json(['success' => false, 'message' => 'Country visited is required.'], 422);
            }

            $maxValidId = 2147483647;
            $isValidExistingId = $existingTravelId !== null && $existingTravelId > 0 && $existingTravelId <= $maxValidId;

            DB::beginTransaction();
            try {
                if ($isValidExistingId && ClientTravelInformation::where('id', $existingTravelId)->where('client_id', $clientId)->exists()) {
                    ClientTravelInformation::where('id', $existingTravelId)->update([
                        'travel_country_visited' => $countryVisited,
                        'travel_arrival_date' => $arrivalDate,
                        'travel_departure_date' => $departureDate,
                        'travel_purpose' => $purpose,
                    ]);
                } else {
                    ClientTravelInformation::create([
                        'client_id' => $clientId,
                        'admin_id' => $clientId,
                        'travel_country_visited' => $countryVisited,
                        'travel_arrival_date' => $arrivalDate,
                        'travel_departure_date' => $departureDate,
                        'travel_purpose' => $purpose,
                    ]);
                }
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $this->sendApprovalMessageAndNotify($client, $clientMatterId, 'Travel Information', $clientId);
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Travel approved and saved. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error approving travel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject travel audit: remove from clientportal_details_audit and notify client.
     */
    public function rejectTravelAudit(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'client_matter_id' => 'required|integer',
                'meta_order' => 'required|integer',
            ]);
            $clientId = (int) $request->client_id;
            $clientMatterId = (int) $request->client_matter_id;
            $metaOrder = (int) $request->meta_order;

            $client = Admin::whereIn('type', ['client', 'lead'])->find($clientId);
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $travelKeys = ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'];
            $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_order', $metaOrder)
                ->whereIn('meta_key', $travelKeys)
                ->get();

            if ($auditEntries->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No pending travel audit found for this row.'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($auditEntries as $e) {
                    $e->delete();
                }
                $this->sendRejectionMessageAndNotify($client, $clientMatterId, 'Travel Information', $clientId);
                DB::commit();
                return response()->json(['success' => true, 'message' => 'Travel change rejected. Message sent to client.']);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error rejecting travel: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send approval message and notification (shared for address/travel to avoid duplication).
     */
    private function sendApprovalMessageAndNotify($client, $clientMatterId, $sectionName, $clientId)
    {
        $sender = Auth::guard('admin')->user();
        $senderId = $sender ? $sender->id : null;
        $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
        $message = 'Your ' . $sectionName . ' change was approved by Admin.';
        $messageData = [
            'message' => $message,
            'sender' => $senderName,
            'sender_id' => $senderId,
            'sent_at' => now(),
            'client_matter_id' => $clientMatterId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $messageId = DB::table('messages')->insertGetId($messageData);
        if ($messageId) {
            MessageRecipient::insert([
                'message_id' => $messageId,
                'recipient_id' => $clientId,
                'recipient' => $client->first_name . ' ' . $client->last_name,
                'is_read' => false,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (class_exists('\App\Events\MessageSent')) {
                $messageForBroadcast = [
                    'id' => $messageId,
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_name' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now()->toISOString(),
                    'created_at' => now()->toISOString(),
                    'client_matter_id' => $clientMatterId,
                    'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                ];
                try {
                    broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                } catch (\Exception $e) {
                    Log::warning('sendApprovalMessageAndNotify broadcast failed', ['error' => $e->getMessage()]);
                }
            }
        }
        $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
        $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
        $actionMessage = ($senderName ?: 'Admin') . ' approved your ' . strtolower($sectionName) . ' update in ' . $matterNo . '.';
        DB::table('notifications')->insert([
            'sender_id' => $senderId,
            'receiver_id' => $clientId,
            'module_id' => $clientMatterId,
            'url' => '/details',
            'notification_type' => 'detail_approved',
            'message' => $actionMessage,
            'created_at' => now(),
            'updated_at' => now(),
            'sender_status' => 1,
            'receiver_status' => 0,
            'seen' => 0,
        ]);
        try {
            $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
            broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
        } catch (\Exception $e) {
            Log::warning('sendApprovalMessageAndNotify NotificationCountUpdated failed', ['error' => $e->getMessage()]);
        }
        $clientMatterModel = ClientMatter::find($clientMatterId);
        if ($clientMatterModel) {
            $this->createClientPortalAction($clientMatterModel, $actionMessage);
        }
    }

    /**
     * Send rejection message and notification (shared for address/travel).
     */
    private function sendRejectionMessageAndNotify($client, $clientMatterId, $sectionName, $clientId)
    {
        $sender = Auth::guard('admin')->user();
        $senderId = $sender ? $sender->id : null;
        $senderName = $sender ? ($sender->first_name . ' ' . $sender->last_name) : 'Admin';
        $message = 'Your ' . $sectionName . ' change was rejected by Admin. Please try again.';
        $messageData = [
            'message' => $message,
            'sender' => $senderName,
            'sender_id' => $senderId,
            'sent_at' => now(),
            'client_matter_id' => $clientMatterId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $messageId = DB::table('messages')->insertGetId($messageData);
        if ($messageId) {
            MessageRecipient::insert([
                'message_id' => $messageId,
                'recipient_id' => $clientId,
                'recipient' => $client->first_name . ' ' . $client->last_name,
                'is_read' => false,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (class_exists('\App\Events\MessageSent')) {
                $messageForBroadcast = [
                    'id' => $messageId,
                    'message' => $message,
                    'sender' => $senderName,
                    'sender_name' => $senderName,
                    'sender_id' => $senderId,
                    'sent_at' => now()->toISOString(),
                    'created_at' => now()->toISOString(),
                    'client_matter_id' => $clientMatterId,
                    'recipients' => [['recipient_id' => $clientId, 'recipient' => $client->first_name . ' ' . $client->last_name]],
                ];
                try {
                    broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
                } catch (\Exception $e) {
                    Log::warning('sendRejectionMessageAndNotify broadcast failed', ['error' => $e->getMessage()]);
                }
            }
        }
        $clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
        $matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID:' . $clientMatterId) : 'ID:' . $clientMatterId;
        $actionMessage = ($senderName ?: 'Admin') . ' rejected your ' . strtolower($sectionName) . ' update in ' . $matterNo . '.';
        DB::table('notifications')->insert([
            'sender_id' => $senderId,
            'receiver_id' => $clientId,
            'module_id' => $clientMatterId,
            'url' => '/details',
            'notification_type' => 'detail_rejected',
            'message' => $actionMessage,
            'created_at' => now(),
            'updated_at' => now(),
            'sender_status' => 1,
            'receiver_status' => 0,
            'seen' => 0,
        ]);
        try {
            $clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
            broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/details'));
        } catch (\Exception $e) {
            Log::warning('sendRejectionMessageAndNotify NotificationCountUpdated failed', ['error' => $e->getMessage()]);
        }
        $clientMatterModel = ClientMatter::find($clientMatterId);
        if ($clientMatterModel) {
            $this->createClientPortalAction($clientMatterModel, $actionMessage);
        }
    }

	//Load Application Insert Update Data
		public function loadMatterUpsert(Request $request){
		$clientId = $request->client_id;
		$clientMatterId = $request->client_matter_id;

		$matter = DB::table('client_matters')
			->where('client_id', $clientId)
			->where('id', $clientMatterId)
			->first();

		if (!$matter) {
			return response()->json(['status' => false, 'message' => 'Matter not found'], 404);
		}
		
		return response()->json([
			'status' => true,
			'client_matter_id' => $clientMatterId,
			'message' => 'Ready'
		]);
	}

	/**
	 * Returns the client portal tab HTML for AJAX load when matter changes.
	 * Used by showClientMatterPortalData in detail-main.js.
	 */
	public function getClientPortalDetail(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter) {
			return response('<div class="p-4 text-danger">Matter not found.</div>', 404);
		}
		$fetchedData = Admin::whereIn('type', ['client', 'lead'])->find($clientMatter->client_id);
		if (!$fetchedData) {
			return response('<div class="p-4 text-danger">Client not found.</div>', 404);
		}
		$id1 = $clientMatter->client_unique_matter_no;
		$clientId = $fetchedData->id;
		$clientContacts = ClientContact::where('client_id', $clientId)->orderBy('id')->get();
		$emails = ClientEmail::where('client_id', $clientId)->get();
		$clientAddresses = ClientAddress::where('client_id', $clientId)->orderedForDisplay()->get();
		$clientPassports = ClientPassportInformation::where('client_id', $clientId)->orderBy('id')->get();
		$visaCountries = ClientVisaCountry::with('matter')->where('client_id', $clientId)->orderBy('id')->get();
		$clientTravels = ClientTravelInformation::where('client_id', $clientId)->orderByRaw('travel_arrival_date DESC NULLS LAST, created_at DESC')->get();
		$qualifications = ClientQualification::where('client_id', $clientId)->orderByRaw('finish_date DESC NULLS LAST')->get();
		$experiences = ClientExperience::where('client_id', $clientId)->orderedForDisplay()->get();
		$clientOccupations = ClientOccupation::where('client_id', $clientId)->get();
		$testScores = ClientTestScore::where('client_id', $clientId)->get();
		return view('crm.clients.tabs.client_portal', compact(
			'fetchedData', 'id1', 'clientContacts', 'emails', 'clientAddresses', 'clientPassports',
			'visaCountries', 'clientTravels', 'qualifications', 'experiences', 'clientOccupations', 'testScores'
		));
	}

	public function completestage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$stageName = $clientMatter->workflowStage?->name ?? '';
		$clientMatter->matter_status = 0; // Discontinued/completed
		$saved = $clientMatter->save();
		if ($saved) {
			$response = ['status' => true, 'stage' => $stageName, 'width' => 100, 'message' => 'Matter has been successfully completed.'];
		} else {
			$response = ['status' => false, 'message' => 'Please try again'];
		}
		echo json_encode($response);
	}
	public function updatestage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter || !$clientMatter->workflowStage) {
			echo json_encode(['status' => false, 'message' => 'Matter or stage not found']);
			return;
		}
		$currentStage = $clientMatter->workflowStage;
		$workflowId = $currentStage->w_id ?? $clientMatter->workflow_id;
		$nextStage = WorkflowStage::where('id', '>', $currentStage->id)
			->when($workflowId, fn($q) => $q->where('w_id', $workflowId))
			->orderBy('id','asc')->first();
		if (!$nextStage) {
			echo json_encode(['status' => false, 'message' => 'No next stage']);
			return;
		}
		$stages = WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
		$nextIndex = $stages->search(fn($s) => $s->id == $nextStage->id) + 1;
		$width = $stages->count() > 0 ? round(($nextIndex / $stages->count()) * 100) : 0;
		$clientMatter->workflow_stage_id = $nextStage->id;
		$saved = $clientMatter->save();
		if ($saved) {
			$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $nextStage->name . '</b>';
			$obj = new ActivitiesLog;
			$obj->client_id = $clientMatter->client_id;
			$obj->created_by = Auth::user()->id;
			$obj->subject = 'Stage: ' . $currentStage->name;
			$obj->description = $comments;
			$obj->activity_type = 'stage';
			$obj->use_for = 'matter';
			$obj->save();
			$lastStage = $stages->last();
			$displayback = $lastStage && $lastStage->name == $nextStage->name;
			$response = ['status' => true, 'stage' => $nextStage->name, 'width' => $width, 'displaycomplete' => $displayback, 'message' => 'Matter has been successfully moved to next stage.'];
		} else {
			$response = ['status' => false, 'message' => 'Please try again'];
		}
		echo json_encode($response);
	}

	public function updatebackstage(Request $request){
		$matterId = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($matterId);
		if (!$clientMatter || !$clientMatter->workflowStage) {
			echo json_encode(['status' => false, 'message' => 'Matter or stage not found']);
			return;
		}
		$currentStage = $clientMatter->workflowStage;
		$workflowId = $currentStage->w_id ?? $clientMatter->workflow_id;
		$prevStage = WorkflowStage::where('id', '<', $currentStage->id)
			->when($workflowId, fn($q) => $q->where('w_id', $workflowId))
			->orderBy('id','Desc')->first();
		if (!$prevStage) {
			echo json_encode(['status' => false, 'message' => '']);
			return;
		}
		$stages = WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
		$prevIndex = $stages->search(fn($s) => $s->id == $prevStage->id) + 1;
		$width = $stages->count() > 0 ? round(($prevIndex / $stages->count()) * 100) : 0;
		$clientMatter->workflow_stage_id = $prevStage->id;
		$saved = $clientMatter->save();
		if ($saved) {
			$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $prevStage->name . '</b>';
			$obj = new ActivitiesLog;
			$obj->client_id = $clientMatter->client_id;
			$obj->created_by = Auth::user()->id;
			$obj->subject = 'Stage: ' . $currentStage->name;
			$obj->description = $comments;
			$obj->activity_type = 'stage';
			$obj->use_for = 'matter';
			$obj->save();
			$lastStage = $stages->last();
			$displayback = $lastStage && $lastStage->name == $prevStage->name;
			$response = ['status' => true, 'stage' => $prevStage->name, 'width' => $width, 'displaycomplete' => $displayback, 'message' => 'Matter has been successfully moved to previous stage.'];
		} else {
			$response = ['status' => false, 'message' => 'Please try again'];
		}
		echo json_encode($response);
	}

	/**
	 * Move Client Matter to Next Stage
	 * 
	 * Updates the workflow_stage_id for a client_matter to the next stage in sequence
	 * Also updates the applications table if it exists (for backward compatibility)
	 * 
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateClientMatterNextStage(Request $request){
		try {
			$matterId = $request->input('matter_id');
			
			if (!$matterId) {
				return response()->json([
					'status' => false,
					'message' => 'Matter ID is required'
				], 422);
			}

			// Get the client matter
			$clientMatter = ClientMatter::find($matterId);
			
			if (!$clientMatter) {
				return response()->json([
					'status' => false,
					'message' => 'Client matter not found'
				], 404);
			}

			// Get current stage
			$currentStageId = $clientMatter->workflow_stage_id;
			
			if (!$currentStageId) {
				return response()->json([
					'status' => false,
					'message' => 'Current stage not found'
				], 404);
			}

			// Get current stage details
			$currentStage = WorkflowStage::find($currentStageId);
			
			if (!$currentStage) {
				return response()->json([
					'status' => false,
					'message' => 'Current workflow stage not found'
				], 404);
			}

			// Get next stage (ordered by sort_order, then id) - scope to same workflow as client matter
			$currentOrder = $currentStage->sort_order ?? $currentStage->id;
			$stageQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) > ?', [$currentOrder]);
			if ($clientMatter->workflow_id) {
				$stageQuery->where('workflow_id', $clientMatter->workflow_id);
			} elseif ($currentStage->workflow_id) {
				$stageQuery->where('workflow_id', $currentStage->workflow_id);
			}
			$nextStage = $stageQuery->orderByRaw('COALESCE(sort_order, id) ASC')->first();

			if (!$nextStage) {
				return response()->json([
					'status' => false,
					'message' => 'Already at the last stage',
					'is_last_stage' => true
				], 400);
			}

			// When advancing to "Decision Received", require decision_outcome and decision_note
			$nextStageName = $nextStage->name ?? '';
			$isAdvancingToDecisionReceived = (strtolower(trim($nextStageName)) === 'decision received');
			if ($isAdvancingToDecisionReceived) {
				$decisionOutcome = $request->input('decision_outcome');
				$decisionNote = $request->input('decision_note', '');
				if (!$decisionOutcome || trim($decisionOutcome) === '') {
					return response()->json([
						'status' => false,
						'message' => 'Please select an outcome (Granted/Refused/Withdrawn) for Decision Received.'
					], 422);
				}
				if (!in_array(trim($decisionOutcome), ['Granted', 'Refused', 'Withdrawn'])) {
					return response()->json([
						'status' => false,
						'message' => 'Invalid outcome. Must be Granted, Refused, or Withdrawn.'
					], 422);
				}
				if (!$decisionNote || trim($decisionNote) === '') {
					return response()->json([
						'status' => false,
						'message' => 'Please enter a note for Decision Received.'
					], 422);
				}
			}

			// Update client_matters table
			$clientMatter->workflow_stage_id = $nextStage->id;
			if ($isAdvancingToDecisionReceived) {
				$clientMatter->decision_outcome = trim($request->input('decision_outcome'));
				$clientMatter->decision_note = trim($request->input('decision_note', ''));
			}
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed - workflow tracked via client_matters.workflow_stage_id

				// Calculate progress percentage (by sort_order) - scope to same workflow
				$progressQuery = WorkflowStage::query();
				if ($clientMatter->workflow_id) {
					$progressQuery->where('workflow_id', $clientMatter->workflow_id);
				}
				$totalStages = (clone $progressQuery)->count();
				$nextOrder = $nextStage->sort_order ?? $nextStage->id;
				$currentStageIndex = (clone $progressQuery)->whereRaw('COALESCE(sort_order, id) <= ?', [$nextOrder])->count();
				$progressPercentage = $totalStages > 0 ? round(($currentStageIndex / $totalStages) * 100) : 0;

				// Check if this is the last stage
				$isLastStageQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) > ?', [$nextOrder]);
				if ($clientMatter->workflow_id) {
					$isLastStageQuery->where('workflow_id', $clientMatter->workflow_id);
				}
				$isLastStage = !$isLastStageQuery->exists();

				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;

				// Activity Feed (activities_logs): skip when request is from Client Portal tab; keep for Workflow tab etc.
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $nextStage->name . '</b>';
					if ($isAdvancingToDecisionReceived) {
						$decisionOutcome = $request->input('decision_outcome');
						$decisionNote = $request->input('decision_note', '');
						$comments .= '<br>Outcome: <b>' . e($decisionOutcome) . '</b>';
						if (!empty(trim($decisionNote))) {
							$comments .= '<br>Note: ' . e($decisionNote);
						}
					}
					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = $matterNo . ' Stage: ' . $currentStage->name;
					$activityLog->description = $comments;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'client_portal_web';
					$activityLog->save();
				}

				// Create action for Action page Client Portal tab (only when triggered from Client Portal tab)
				if ($request->input('source') === 'client_portal') {
					$desc = 'Matter ' . $matterNo . ' moved to next stage: ' . $currentStage->name . ' → ' . $nextStage->name;
					$this->createClientPortalAction($clientMatter, $desc);
				}

				// Notify client of stage change (for List Notifications API)
				$notificationMessage = 'Stage moved from ' . $currentStage->name . ' to ' . $nextStage->name . ' for matter ' . $matterNo;
				DB::table('notifications')->insert([
					'sender_id' => Auth::user()->id,
					'receiver_id' => $clientMatter->client_id,
					'module_id' => $matterId,
					'url' => '/documents',
					'notification_type' => 'stage_change',
					'message' => $notificationMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);

				// Send push notification to client mobile app when action is from Client Portal tab only
				$source = $request->input('source', '');
				if ($source === 'client_portal') {
					try {
						$fcmService = new FCMService();
						$notificationTitle = 'Stage Updated';
						$notificationBody = $notificationMessage;
						$notificationData = [
							'type' => 'stage_change',
							'client_matter_id' => (string) $matterId,
							'message' => $notificationMessage,
						];
						$fcmService->sendToUser($clientMatter->client_id, $notificationTitle, $notificationBody, $notificationData);
					} catch (\Exception $e) {
						Log::warning('Failed to send push notification for stage change', [
							'client_id' => $clientMatter->client_id,
							'matter_id' => $matterId,
							'error' => $e->getMessage()
						]);
					}
				}

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully moved to the next stage.',
					'stage_name' => $nextStage->name,
					'stage_id' => $nextStage->id,
					'progress_percentage' => $progressPercentage,
					'is_last_stage' => $isLastStage
				]);
			} else {
				return response()->json([
					'status' => false,
					'message' => 'Failed to update matter stage. Please try again.'
				], 500);
			}

		} catch (\Exception $e) {
			Log::error('Error updating client matter next stage: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'status' => false,
				'message' => 'An error occurred while updating the stage. Please try again.'
			], 500);
		}
	}

	/**
	 * Move Client Matter to Previous Stage
	 *
	 * Updates the workflow_stage_id for a client_matter to the previous stage in sequence.
	 * Also updates the applications table if it exists (for backward compatibility).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateClientMatterPreviousStage(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json([
					'status' => false,
					'message' => 'Matter ID is required'
				], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json([
					'status' => false,
					'message' => 'Client matter not found'
				], 404);
			}

			$currentStageId = $clientMatter->workflow_stage_id;

			if (!$currentStageId) {
				return response()->json([
					'status' => false,
					'message' => 'Current stage not found'
				], 404);
			}

			$currentStage = WorkflowStage::find($currentStageId);

			if (!$currentStage) {
				return response()->json([
					'status' => false,
					'message' => 'Current workflow stage not found'
				], 404);
			}

			$currentOrder = $currentStage->sort_order ?? $currentStage->id;
			$prevQuery = WorkflowStage::whereRaw('COALESCE(sort_order, id) < ?', [$currentOrder]);
			if ($clientMatter->workflow_id) {
				$prevQuery->where('workflow_id', $clientMatter->workflow_id);
			} elseif ($currentStage->workflow_id) {
				$prevQuery->where('workflow_id', $currentStage->workflow_id);
			}
			$prevStage = $prevQuery->orderByRaw('COALESCE(sort_order, id) DESC')->first();

			if (!$prevStage) {
				return response()->json([
					'status' => false,
					'message' => 'Already at the first stage',
					'is_first_stage' => true
				], 400);
			}

			$clientMatter->workflow_stage_id = $prevStage->id;
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed - workflow tracked via client_matters

				$totalStages = WorkflowStage::count();
				$prevOrder = $prevStage->sort_order ?? $prevStage->id;
				$currentStageIndex = WorkflowStage::whereRaw('COALESCE(sort_order, id) <= ?', [$prevOrder])->count();
				$progressPercentage = $totalStages > 0 ? round(($currentStageIndex / $totalStages) * 100) : 0;
				$isFirstStage = !WorkflowStage::whereRaw('COALESCE(sort_order, id) < ?', [$prevOrder])->exists();

				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;

				// Activity Feed (activities_logs): skip when request is from Client Portal tab; keep for Workflow tab etc.
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					$comments = 'moved the stage from <b>' . $currentStage->name . '</b> to <b>' . $prevStage->name . '</b>';

					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = $matterNo . ' Stage: ' . $currentStage->name;
					$activityLog->description = $comments;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'client_portal_web';
					$activityLog->save();
				}

				// Create action for Action page Client Portal tab (only when triggered from Client Portal tab)
				if ($request->input('source') === 'client_portal') {
					$desc = 'Matter ' . $matterNo . ' moved to previous stage: ' . $currentStage->name . ' → ' . $prevStage->name;
					$this->createClientPortalAction($clientMatter, $desc);
				}

				$notificationMessage = 'Stage moved from ' . $currentStage->name . ' to ' . $prevStage->name . ' for matter ' . $matterNo;
				DB::table('notifications')->insert([
					'sender_id' => Auth::user()->id,
					'receiver_id' => $clientMatter->client_id,
					'module_id' => $matterId,
					'url' => '/documents',
					'notification_type' => 'stage_change',
					'message' => $notificationMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully moved to the previous stage.',
					'stage_name' => $prevStage->name,
					'stage_id' => $prevStage->id,
					'progress_percentage' => $progressPercentage,
					'is_first_stage' => $isFirstStage
				]);
			}

			return response()->json([
				'status' => false,
				'message' => 'Failed to update matter stage. Please try again.'
			], 500);

		} catch (\Exception $e) {
			Log::error('Error updating client matter previous stage: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'status' => false,
				'message' => 'An error occurred while updating the stage. Please try again.'
			], 500);
		}
	}

	/**
	 * When staff act from the Client Portal tab (or legacy application tab), we skip writing to
	 * activities_logs so the Personal Details Activity Feed is not duplicated; Action page still
	 * uses notes via createClientPortalAction(). Workflow tab and matter list keep full logging.
	 * Pass source=client_portal (or current_tab client_portal/application) from the Client Portal UI
	 * for stage, matter, document, and staff message flows. Detail approve/reject endpoints do not
	 * insert activities_logs rows.
	 */
	private function shouldOmitActivitiesLogForClientPortalWebContext(Request $request): bool
	{
		if ($request->input('source') === 'client_portal') {
			return true;
		}
		$tab = (string) $request->input('current_tab', '');
		return in_array($tab, ['application', 'client_portal'], true);
	}

	/**
	 * Create action(s) (Note) for the Action page Client Portal tab: Person Responsible and
	 * Person Assisting each get a row with the same unique_group_id; the acting CRM user is omitted if they are PR/PA.
	 *
	 * @param ClientMatter $clientMatter
	 * @param string $description
	 * @return void
	 */
	private function createClientPortalAction(ClientMatter $clientMatter, string $description)
	{
		ClientPortalActionNoteService::createGroupedForMatter(
			(int) $clientMatter->client_id,
			(int) $clientMatter->id,
			$description,
			(int) Auth::user()->id,
			$clientMatter,
			(int) Auth::user()->id,
			(int) Auth::user()->id
		);
	}

	/**
	 * Change workflow for an existing client matter.
	 * Maps current stage by name to new workflow; falls back to first stage if no match.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function changeClientMatterWorkflow(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');
			$workflowId = $request->input('workflow_id');

			if (!$matterId || !$workflowId) {
				return response()->json(['status' => false, 'message' => 'Matter ID and Workflow ID are required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);
			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found'], 404);
			}

			$workflow = \App\Models\Workflow::find($workflowId);
			if (!$workflow) {
				return response()->json(['status' => false, 'message' => 'Workflow not found'], 404);
			}

			$currentStageName = null;
			if ($clientMatter->workflow_stage_id) {
				$currentStage = WorkflowStage::find($clientMatter->workflow_stage_id);
				$currentStageName = $currentStage ? trim($currentStage->name) : null;
			}

			$newStageId = null;
			if ($currentStageName) {
				$matched = WorkflowStage::where('workflow_id', $workflowId)
					->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($currentStageName)])
					->first();
				$newStageId = $matched ? $matched->id : null;
			}
			if (!$newStageId) {
				$firstStage = WorkflowStage::where('workflow_id', $workflowId)
					->orderByRaw('COALESCE(sort_order, id) ASC')
					->first();
				$newStageId = $firstStage ? $firstStage->id : null;
			}

			if (!$newStageId) {
				return response()->json(['status' => false, 'message' => 'Selected workflow has no stages. Add stages first.'], 400);
			}

			$clientMatter->workflow_id = $workflowId;
			$clientMatter->workflow_stage_id = $newStageId;
			$clientMatter->save();

			\App\Support\WorkflowStageChecklistSync::ensureSeededForMatter($clientMatter);

			$matterNo = $clientMatter->client_unique_matter_no ?? 'ID:' . $matterId;

			// Activity Feed: Workflow tab does not send source; omit only if Client Portal context is explicit.
			if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
				$activityLog = new ActivitiesLog;
				$activityLog->client_id = $clientMatter->client_id;
				$activityLog->created_by = Auth::user()->id;
				$activityLog->subject = $matterNo . ' Workflow changed to ' . $workflow->name;
				$activityLog->description = 'Workflow changed to <b>' . e($workflow->name) . '</b>. Stage mapped accordingly.';
				$activityLog->activity_type = 'stage';
				$activityLog->use_for = 'matter';
				$activityLog->task_status = 0;
				$activityLog->pin = 0;
				$activityLog->source = 'client_portal_web';
				$activityLog->save();
			}

			return response()->json([
				'status' => true,
				'message' => 'Workflow changed successfully.',
				'workflow_id' => $workflowId,
				'stage_id' => $newStageId,
			]);
		} catch (\Exception $e) {
			Log::error('Error changing client matter workflow: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while changing the workflow. Please try again.'
			], 500);
		}
	}

	/**
	 * Discontinue a client matter (set matter_status = 0)
	 * Requires discontinue_reason from dropdown. Logs activity with reason.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function discontinueClientMatter(Request $request)
	{
		try {
			if (! $this->currentStaffMayDiscontinueOrReopenMatter()) {
				return response()->json([
					'status' => false,
					'message' => 'Only Super admin, Admin, or Migration Agent users can discontinue matters.'
				], 403);
			}

			$matterId = $request->input('matter_id');
			$reason = $request->input('discontinue_reason');
			$notes = $request->input('discontinue_notes', '');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			if (!$reason || trim($reason) === '') {
				return response()->json(['status' => false, 'message' => 'Please select a reason for discontinuing.'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$saved = false;
			DB::transaction(function () use ($clientMatter, $matterId, &$saved) {
				$clientMatter->matter_status = 0;
				$saved = $clientMatter->save();

				if ($saved) {
					app(MatterEmailBodyCleanupService::class)->clearBodiesForMatter((int) $matterId);
				}
			});

			if ($saved) {
				// applications table removed

				$description = 'Discontinued matter. Reason: <b>' . e($reason) . '</b>';
				if (!empty(trim($notes))) {
					$description .= '<br>Notes: ' . e($notes);
				}

				// Activity Feed: omit when discontinuing from Client Portal / application tab (current_tab in request).
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = 'Matter Discontinued';
					$activityLog->description = $description;
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'client_portal_web';
					$activityLog->save();
				}

				// Notify client and send push when Discontinue is from Client Portal tab only
				$currentTab = $request->input('current_tab', 'personaldetails');
				if (in_array($currentTab, ['application', 'client_portal'])) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;
					$notificationMessage = 'Your matter ' . $matterNo . ' has been discontinued. Reason: ' . e($reason);
					DB::table('notifications')->insert([
						'sender_id' => Auth::user()->id,
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $matterId,
						'url' => '/documents',
						'notification_type' => 'matter_discontinued',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);

					try {
						$fcmService = new FCMService();
						$notificationTitle = 'Matter Discontinued';
						$notificationBody = 'Your matter ' . $matterNo . ' has been discontinued. Reason: ' . $reason;
						$notificationData = [
							'type' => 'matter_discontinued',
							'client_matter_id' => (string) $matterId,
							'message' => $notificationMessage,
						];
						$fcmService->sendToUser($clientMatter->client_id, $notificationTitle, $notificationBody, $notificationData);
					} catch (\Exception $e) {
						Log::warning('Failed to send push notification for matter discontinued', [
							'client_id' => $clientMatter->client_id,
							'matter_id' => $matterId,
							'error' => $e->getMessage()
						]);
					}
				}

				// Build redirect URL: go to another active matter, or revert to lead view (no matter)
				$encodeId = base64_encode(convert_uuencode($clientMatter->client_id));
				$otherMatter = ClientMatter::where('client_id', $clientMatter->client_id)
					->where('id', '!=', $matterId)
					->where('matter_status', 1)
					->orderBy('id', 'desc')
					->first();
				$redirectUrl = '/clients/detail/' . $encodeId;
				if ($otherMatter) {
					$redirectUrl .= '/' . $otherMatter->client_unique_matter_no . '/' . $currentTab;
				} else {
					$redirectUrl .= '/' . $currentTab;
				}

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully discontinued.',
					'redirect_url' => $redirectUrl
				]);
			}

			return response()->json(['status' => false, 'message' => 'Failed to discontinue matter.'], 500);

		} catch (\Exception $e) {
			Log::error('Error discontinuing client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while discontinuing the matter.'
			], 500);
		}
	}

	/**
	 * Reopen a discontinued client matter (set matter_status = 1).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function reopenClientMatter(Request $request)
	{
		try {
			if (! $this->currentStaffMayDiscontinueOrReopenMatter()) {
				return response()->json([
					'status' => false,
					'message' => 'Only Super admin, Admin, or Migration Agent users can reopen matters.'
				], 403);
			}

			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$clientMatter->matter_status = 1;
			$saved = $clientMatter->save();

			if ($saved) {
				// applications table removed

				// Activity Feed: omit when reopening from client detail Client Portal / application tab; keep for Workflow tab and matter list.
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					$activityLog = new ActivitiesLog;
					$activityLog->client_id = $clientMatter->client_id;
					$activityLog->created_by = Auth::user()->id;
					$activityLog->subject = 'Matter Reopened';
					$activityLog->description = 'Matter was reopened and set back to active.';
					$activityLog->activity_type = 'stage';
					$activityLog->use_for = 'matter';
					$activityLog->task_status = 0;
					$activityLog->pin = 0;
					$activityLog->source = 'client_portal_web';
					$activityLog->save();
				}

				// Notify client and send push when Reopen is from Client Portal tab OR from Matter List (only if Client Portal is active)
				$currentTab = $request->input('current_tab', '');
				$source = $request->input('source', '');
				$shouldNotify = false;

				if (in_array($currentTab, ['application', 'client_portal'])) {
					// Reopen from Client Portal tab on client detail page - always notify
					$shouldNotify = true;
				} elseif ($source === 'matter_list') {
					// Reopen from Matter List - only notify if Client Portal is active for the client
					$client = Admin::find($clientMatter->client_id);
					$shouldNotify = $client && ((int) ($client->cp_status ?? 0) === 1);
				}

				if ($shouldNotify) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $matterId;
					$notificationMessage = 'Your matter ' . $matterNo . ' has been reopened and is now active again.';
					DB::table('notifications')->insert([
						'sender_id' => Auth::user()->id,
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $matterId,
						'url' => '/documents',
						'notification_type' => 'matter_reopened',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);

					try {
						$fcmService = new FCMService();
						$notificationTitle = 'Matter Reopened';
						$notificationBody = $notificationMessage;
						$notificationData = [
							'type' => 'matter_reopened',
							'client_matter_id' => (string) $matterId,
							'message' => $notificationMessage,
						];
						$fcmService->sendToUser($clientMatter->client_id, $notificationTitle, $notificationBody, $notificationData);
					} catch (\Exception $e) {
						Log::warning('Failed to send push notification for matter reopened', [
							'client_id' => $clientMatter->client_id,
							'matter_id' => $matterId,
							'error' => $e->getMessage()
						]);
					}
				}

				return response()->json([
					'status' => true,
					'message' => 'Matter has been successfully reopened.',
					'redirect_url' => route('clients.clientsmatterslist')
				]);
			}

			return response()->json(['status' => false, 'message' => 'Failed to reopen matter.'], 500);

		} catch (\Exception $e) {
			Log::error('Error reopening client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while reopening the matter.'
			], 500);
		}
	}

	/**
	 * Permanently delete a closed client matter. Only allowed if matter was created more than 1 year ago.
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function deleteClientMatter(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			$oneYearAgo = now()->subYear();
			$createdAt = $clientMatter->created_at ? \Carbon\Carbon::parse($clientMatter->created_at) : null;

			if (!$createdAt || $createdAt->gt($oneYearAgo)) {
				return response()->json([
					'status' => false,
					'message' => 'Matter can only be deleted one year after creation. Matter created on ' . ($createdAt ? $createdAt->format('d/m/Y') : 'N/A') . '.'
				], 422);
			}

			$clientId = $clientMatter->client_id;
			$clientMatter->delete();

			// Activity Feed: omit when delete is invoked with Client Portal context (source/current_tab).
			if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
				$activityLog = new ActivitiesLog;
				$activityLog->client_id = $clientId;
				$activityLog->created_by = Auth::user()->id;
				$activityLog->subject = 'Matter Deleted';
				$activityLog->description = 'Matter #' . $matterId . ' was permanently deleted from closed matters.';
				$activityLog->activity_type = 'stage';
				$activityLog->task_status = 0;
				$activityLog->pin = 0;
				$activityLog->source = 'client_portal_web';
				$activityLog->save();
			}

			return response()->json([
				'status' => true,
				'message' => 'Matter has been permanently deleted.',
				'matter_id' => (int) $matterId
			]);

		} catch (\Exception $e) {
			Log::error('Error deleting client matter: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while deleting the matter.'
			], 500);
		}
	}

	/**
	 * Update matter deadline. Accepts matter_id, set_deadline (bool), and deadline (date when set).
	 *
	 * @param Request $request
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function updateClientMatterDeadline(Request $request)
	{
		try {
			$matterId = $request->input('matter_id');
			$setDeadline = filter_var($request->input('set_deadline'), FILTER_VALIDATE_BOOLEAN);
			$deadline = $request->input('deadline');

			if (!$matterId) {
				return response()->json(['status' => false, 'message' => 'Matter ID is required'], 422);
			}

			$clientMatter = ClientMatter::find($matterId);

			if (!$clientMatter) {
				return response()->json(['status' => false, 'message' => 'Client matter not found.'], 404);
			}

			if ($setDeadline) {
				$request->validate(['deadline' => 'required|date']);
				$clientMatter->deadline = $deadline;
			} else {
				$clientMatter->deadline = null;
			}

			$clientMatter->save();

			return response()->json([
				'status' => true,
				'message' => $setDeadline ? 'Deadline has been set.' : 'Deadline has been cleared.',
				'deadline' => $clientMatter->deadline ? $clientMatter->deadline->format('Y-m-d') : null,
			]);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'status' => false,
				'message' => 'Please select a valid date.',
				'errors' => $e->errors(),
			], 422);
		} catch (\Exception $e) {
			Log::error('Error updating matter deadline: ' . $e->getMessage(), [
				'matter_id' => $request->input('matter_id'),
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'status' => false,
				'message' => 'An error occurred while updating the deadline.'
			], 500);
		}
	}

	// LEGACY METHOD - Still used by some JavaScript but outputs HTML directly (old pattern)
	// TODO: Refactor to return JSON and handle rendering in frontend
	public function getMatterLogs(Request $request){
		$id = $request->id ?? $request->client_matter_id;
		$clientMatter = ClientMatter::with('workflowStage')->find($id);

		if (!$clientMatter || !$clientMatter->workflowStage) {
			return response()->json(['error' => 'Matter not found'], 404);
		}

		$workflowId = $clientMatter->workflowStage->w_id ?? $clientMatter->workflow_id;
		$currentStage = $clientMatter->workflowStage;
		$stagesquery = \App\Models\WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
		foreach($stagesquery as $stages){
			$stage1 = '';

			$workflowstagess = \App\Models\WorkflowStage::where('name', $currentStage->name)->when($workflowId, fn($q) => $q->where('w_id', $workflowId))->first();

			$prevdata = $workflowstagess ? \App\Models\WorkflowStage::where('id', '<', $workflowstagess->id)->when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id','Desc')->get() : collect();
			$stagearray = array();
			foreach($prevdata as $pre){
				$stagearray[] = $pre->id;
			}

			if(in_array($stages->id, $stagearray)){
				$stage1 = 'app_green';
			}
			if($clientMatter->matter_status == 0){
				$stage1 = 'app_green';
			}
			$stagname = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $stages->name)));
			?>

			<div class="accordion cus_accrodian">
				<div class="accordion-header collapsed <?php echo $stage1; ?> <?php if($currentStage->name == $stages->name && $clientMatter->matter_status == 1){ echo  'app_blue'; }  ?>" role="button" data-toggle="collapse" data-target="#<?php echo $stagname; ?>_accor" aria-expanded="false">
					<h4><?php echo $stages->name; ?></h4>
					<div class="accord_hover">
						<a title="Add Note" class="openappnote" data-app-type="<?php echo $stages->name; ?>" data-id="<?php echo $clientMatter->id; ?>" href="javascript:;"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-file-alt'); ?></a>
						<!-- opendocnote REMOVED - workflow checklist upload flow dead (no modal, no handler) -->
						<a data-app-type="<?php echo $stages->name; ?>" title="Email" data-id="<?php echo $clientMatter->id; ?>" data-email="" data-name="" class="openclientemail" title="Compose Mail" href="javascript:;"><?php echo \App\Helpers\IconHelper::fromLegacy('fa fa-envelope'); ?></a>
					</div>
				</div>
				<?php
				$applicationlists = \App\Models\ActivitiesLog::where('client_id', $clientMatter->client_id)
					->where('use_for', 'matter')
					->where('subject', 'like', '%Stage: ' . $stages->name . '%')
					->orderby('created_at', 'DESC')->get();
				?>
				<div class="accordion-body collapse" id="<?php echo $stagname; ?>_accor" data-parent="#accordion" style="">
					<div class="activity_list">
					<?php foreach($applicationlists as $applicationlist){
						$staff = \App\Models\Staff::where('id',$applicationlist->created_by)->first();
					?>
						<div class="activity_col">
							<div class="activity_txt_time">
								<span class="span_txt"><b><?php echo $staff ? $staff->first_name : 'System'; ?></b> <?php echo $applicationlist->description; ?></span>
								<span class="span_time"><?php echo date('d D, M Y h:i A', strtotime($applicationlist->created_at)); ?></span>
							</div>
							<?php if($applicationlist->subject != ''){ ?>
							<div class="app_description">
								<div class="app_card">
									<div class="app_title"><?php echo $applicationlist->subject; ?></div>
								</div>
								<?php if($applicationlist->description != ''){ ?>
								<div class="log_desc">
									<?php echo $applicationlist->description; ?>
								</div>
								<?php } ?>
							</div>
							<?php } ?>
						</div>
					<?php } ?>
					</div>
				</div>
			</div>
		<?php } ?>
		<?php
	}

	public function addNote(Request $request){
		$noteid = $request->noteid;
		$type = $request->type;
		$clientMatter = ClientMatter::find($noteid);

		$obj = new ActivitiesLog;
		$obj->client_id = $clientMatter ? $clientMatter->client_id : null;
		$obj->created_by = Auth::user()->id;
		$obj->subject = $request->title;
		$obj->description = $request->description;
		$obj->activity_type = 'note';
		$obj->use_for = 'matter';
		$saved = $obj->save();
		if($saved){
			$response['status'] 	= 	true;
			$response['message']	=	'Note successfully added';
		}else{
			$response['status'] 	= 	false;
			$response['message']	=	'Please try again';
		}
		echo json_encode($response);
	}

	public function getMatterNotes(Request $request){
		$noteid = $request->id;
		$clientMatter = ClientMatter::find($noteid);

		$lists = ActivitiesLog::where('activity_type','note')
			->where('use_for','matter')
			->where('client_id', $clientMatter ? $clientMatter->client_id : null)
			->orderby('created_at', 'DESC')->get();

		ob_start();
			?>
			<div class="note_term_list">
				<?php
				foreach($lists as $list){
					$staff = \App\Models\Staff::where('id', $list->created_by)->first();
				?>
					<div class="note_col" id="note_id_<?php echo $list->id; ?>">
						<div class="note_content">
						<h4><a class="viewmatternote" data-id="<?php echo $list->id; ?>" href="javascript:;"><?php echo @$list->subject == "" ? config('constants.empty') : Str::limit(@$list->subject, 19, '...'); ?></a></h4>
						<p><?php echo @$list->description == "" ? config('constants.empty') : Str::limit(@$list->description, 15, '...'); ?></p>
						</div>
						<div class="extra_content">
							<div class="left">
								<div class="author">
									<a href="#"><?php echo $staff ? substr($staff->first_name, 0, 1) : '?'; ?></a>
								</div>
								<div class="note_modify">
									<small>Last Modified <span><?php echo date('Y-m-d', strtotime($list->updated_at)); ?></span></small>
								</div>
							</div>
							<div class="right">

							</div>
						</div>
					</div>
				<?php } ?>
				</div>
				<div class="clearfix"></div>
			<?php
			echo ob_get_clean();

	}

	public function clientPortalSendmail(Request $request){
		$requestData = $request->all();
		$user_id = @Auth::user()->id;
		$subject = $requestData['subject'];
		$message = $requestData['message'];
		$to = $requestData['to'];

	$client = \App\Models\Admin::where('email', $requestData['to'])->first();
		if (!$client) {
			return response()->json(['status' => false, 'message' => 'Client not found'], 404);
		}
		$subject = str_replace('{Client First Name}', $client->first_name, $subject);
		$message = str_replace('{Client First Name}', $client->first_name, $message);
		$message = str_replace('{Client Assignee Name}', $client->first_name, $message);
		$message = str_replace('{Company Name}', optional(Auth::user())->company_name ?? '', $message);
		$message .= '<br><br>Consumer guide: <a href="https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf">https://www.mara.gov.au/get-help-visa-subsite/FIles/consumer_guide_english.pdf</a>';
			$array = array();
			$ccarray = array();
			if(isset($requestData['email_cc']) && !empty($requestData['email_cc'])){
				foreach($requestData['email_cc'] as $cc){
					$clientcc = \App\Models\Admin::Where('id', $cc)->first();
					$ccarray[] = $clientcc;
				}
			}
				$sent = $this->send_compose_template($message, 'digitrex', $to, $subject, 'support@digitrex.live', $array, $ccarray ?? []);
			if($sent){
				$clientMatter = ClientMatter::find($request->noteid);
				$objs = new ActivitiesLog;
				$objs->client_id = $clientMatter ? $clientMatter->client_id : null;
				$objs->created_by = Auth::user()->id;
				$objs->subject = '<b>Subject : '.$subject.'</b>';
				$objs->description = '<b>To: '.$to.'</b></br>'.$message;
				$objs->activity_type = 'email';
				$objs->use_for = 'matter';
				$saved = $objs->save();
				$response['status'] 	= 	true;
				$response['message']	=	'Email Sent Successfully';
			}else{
				$response['status'] 	= 	true;
				$response['message']	=	'Please try again';
			}

		echo json_encode($response);
	}

	public function updateintake(Request $request){
		// intakedate was on applications table which has been removed
		echo json_encode(['status' => true, 'message' => 'Date field removed with applications table.']);
	}

	public function updateexpectwin(Request $request){
		// expect_win_date was on applications table - use client_matters.deadline instead
		$obj = ClientMatter::find($request->appid ?? $request->client_matter_id);
		if ($obj && Schema::hasColumn('client_matters', 'deadline')) {
			$obj->deadline = $request->from;
			$saved = $obj->save();
			echo json_encode(['status' => $saved, 'message' => $saved ? 'Date successfully updated.' : 'Please try again']);
		} else {
			echo json_encode(['status' => true, 'message' => 'Date field migrated to matter deadline.']);
		}
	}

	public function updatedates(Request $request){
		// start_date/end_date were on applications - use client_matters.deadline
		$obj = ClientMatter::find($request->appid ?? $request->client_matter_id);
		if ($obj && Schema::hasColumn('client_matters', 'deadline')) {
			$obj->deadline = $request->from;
			$saved = $obj->save();
			if ($saved) {
				$d = $obj->deadline ? date_parse($obj->deadline) : null;
				echo json_encode(['status' => true, 'message' => 'Date successfully updated.', 'dates' => $d ? ['date' => sprintf('%02d', $d['day']), 'month' => date('M', strtotime($obj->deadline)), 'year' => $d['year']] : []]);
			} else {
				echo json_encode(['status' => false, 'message' => 'Please try again']);
			}
		} else {
			echo json_encode(['status' => true, 'message' => 'Date fields migrated to matter.']);
		}
	}

	public function discontinueMatter(Request $request){
		if (! $this->currentStaffMayDiscontinueOrReopenMatter()) {
			echo json_encode([
				'status' => false,
				'message' => 'Only Super admin, Admin, or Migration Agent users can discontinue matters.'
			]);
			return;
		}

		$obj = ClientMatter::find($request->diapp_id ?? $request->client_matter_id);
		if (!$obj) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}

		$saved = false;
		DB::transaction(function () use ($obj, &$saved) {
			$obj->matter_status = 0;
			$saved = $obj->save();

			if ($saved) {
				app(MatterEmailBodyCleanupService::class)->clearBodiesForMatter((int) $obj->id);
			}
		});

		echo json_encode(['status' => $saved, 'message' => $saved ? 'Matter successfully discontinued.' : 'Please try again']);
	}

	/**
	 * Roles allowed to discontinue a matter, reopen it, or legacy-revert it (config: crm.matter_discontinue_role_ids).
	 */
	private function currentStaffMayDiscontinueOrReopenMatter(): bool
	{
		$user = Auth::user();
		if (! $user) {
			return false;
		}

		$role = (int) ($user->role ?? 0);

		return in_array($role, config('crm.matter_discontinue_role_ids', [1, 17, 16]), true);
	}

	public function revertMatter(Request $request){
		if (! $this->currentStaffMayDiscontinueOrReopenMatter()) {
			echo json_encode([
				'status' => false,
				'message' => 'Only Super admin, Admin, or Migration Agent users can revert matters.'
			]);
			return;
		}

		$obj = ClientMatter::with('workflowStage')->find($request->revapp_id ?? $request->client_matter_id);
		if (!$obj) {
			echo json_encode(['status' => false, 'message' => 'Matter not found']);
			return;
		}
		$obj->matter_status = 1;
		$saved = $obj->save();
		$stage = $obj->workflowStage;
		$workflowId = $stage->w_id ?? $obj->workflow_id;
		$stages = \App\Models\WorkflowStage::when($workflowId, fn($q) => $q->where('w_id', $workflowId))->orderBy('id')->get();
		$idx = $stages->search(fn($s) => $s->id == ($stage->id ?? 0)) + 1;
		$width = $stages->count() > 0 ? round(($idx / $stages->count()) * 100) : 0;
		$lastStage = $stages->last();
		$displayback = $lastStage && $stage && $lastStage->name == $stage->name;
		echo json_encode(['status' => $saved, 'width' => $width, 'displaycomplete' => $displayback, 'message' => $saved ? 'Matter successfully reverted.' : 'Please try again']);
	}

	public function application_ownership(Request $request){
		// ratio was on applications - client_matters does not have ratio
		echo json_encode(['status' => true, 'message' => 'Ownership ratio field removed with applications table.', 'ratio' => $request->ratio ?? 0]);
	}

	// Removed legacy method: saleforcast

	// REMOVED - Unused method (no references found in views or JavaScript)
	// This method returned application dropdown options for a client but was never used
	// public function getapplicationbycid(Request $request){ ... }


	public function applicationsavefee(Request $request){
		// Fee options functionality has been removed
		$response = [
			'status' => false,
			'message' => 'Application fee options feature has been removed.'
		];
		return response()->json($response);
	}

	// REMOVED - Application PDF export functionality (view file deleted, was broken and unused)
	// public function exportapplicationpdf(Request $request, $id){
	// 	$applications = \App\Models\Application::where('id', $id)->first();
	// 	$cleintname = \App\Models\Admin::whereIn('type', ['client', 'lead'])->where('id',@$applications->client_id)->first();
	// 	$pdf = PDF::setOptions([
	// 		'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true,
	// 		'logOutputFile' => storage_path('logs/log.htm'),
	// 		'tempDir' => storage_path('logs/')
	// 		])->loadView('emails.application',compact(['cleintname','applications','productdetail','PartnerBranch','partnerdetail']));
	// 	return $pdf->stream('application.pdf');
	// }

	/**
	 * Get checklist options from portal_document_checklists (Personal + Visa)
	 * For use in Add New Checklist type-ahead input
	 */
	public function getDocumentChecklistsOptions(Request $request)
	{
		$search = $request->get('q', '');
		$checklists = DB::table('portal_document_checklists')
			->where('status', 1)
			->whereIn('doc_type', [1, 2]) // 1=Personal, 2=Visa
			->when($search, function ($q) use ($search) {
				$q->where('name', 'like', '%' . $search . '%');
			})
			->orderBy('doc_type')
			->orderBy('name')
			->limit(50)
			->get(['id', 'name', 'doc_type']);

		$results = $checklists->map(function ($item) {
			return [
				'id'   => $item->name,
				'text' => $item->name,
				'name' => $item->name,
			];
		});

		return response()->json(['results' => $results]);
	}

	/**
	 * POST /add-checklists
	 * Adds one or more checklist items to cp_doc_checklists for a given client matter and workflow stage.
	 */
	public function addChecklist(Request $request)
	{
		$request->validate([
			'client_matter_id'    => 'required|integer',
			'wf_stage'            => 'required|string|max:255',
			'cp_checklist_names'  => 'required|array|min:1',
			'cp_checklist_names.*'=> 'required|string|max:255',
			'description'         => 'nullable|string|max:1000',
			'allow_client'        => 'nullable|integer|in:0,1',
			'is_required'         => 'nullable|integer|in:0,1',
		]);

		$clientMatterId = (int) $request->client_matter_id;
		$wfStage        = trim($request->wf_stage);
		$names          = array_filter(array_map('trim', $request->cp_checklist_names));
		$description    = $request->description ? trim($request->description) : null;
		$allowClient    = $request->has('allow_client') ? 1 : 0;
		$isRequired     = $request->has('is_required') ? 1 : 0;

		$matter = DB::table('client_matters')->where('id', $clientMatterId)->first();
		if (!$matter) {
			return response()->json(['success' => false, 'message' => 'Matter not found.'], 404);
		}

		$matterNo = $matter->client_unique_matter_no ?? 'ID: ' . $clientMatterId;

		$stage     = DB::table('workflow_stages')->where('name', $wfStage)->first();
		$wfStageId = $stage ? $stage->id : null;

		$inserted  = [];
		$now       = now();
		$adminUser = Auth::guard('admin')->user();
		$userId    = $adminUser ? $adminUser->id : null;

		foreach ($names as $name) {
			$insertPayload = [
				'user_id'           => $userId,
				'client_matter_id'  => $clientMatterId,
				'client_id'         => $matter->client_id,
				'wf_stage'          => $wfStage,
				'wf_stage_id'       => $wfStageId,
				'cp_checklist_name' => $name,
				'description'       => $description,
				'allow_client'      => $allowClient,
				'created_at'        => $now,
				'updated_at'        => $now,
			];
			if (Schema::hasColumn('cp_doc_checklists', 'is_required')) {
				$insertPayload['is_required'] = $isRequired;
			}
			$newId = DB::table('cp_doc_checklists')->insertGetId($insertPayload);
			$inserted[] = DB::table('cp_doc_checklists')->where('id', $newId)->first();

			if ($allowClient === 1 && !empty($matter->client_id) && Schema::hasTable('cp_action_requires')) {
				$itemMessage = 'New checklist "' . $name . '" added for matter ' . $matterNo;
				DB::table('cp_action_requires')->insert([
					'type'                => 'checklist_upload',
					'client_id'           => (int) $matter->client_id,
					'client_matter_id'    => $clientMatterId,
					'checklist_id'        => (int) $newId,
					'sender_id'           => $userId,
					'receiver_id'         => (int) $matter->client_id,
					'module_id'           => $clientMatterId,
					'url'                 => '/documents?allowed_checklist_id=' . $newId,
					'notification_type'   => 'checklist_added',
					'message'             => $itemMessage,
					'created_at'          => $now,
					'updated_at'          => $now,
					'sender_status'       => 1,
					'receiver_status'     => 0,
					'seen'                => 0,
				]);
			}
		}

		$count = count($inserted);

		// When "Allow For Client" is set, notify client (in-app notification + push) so they see new checklist(s)
		if ($count > 0 && $allowClient === 1 && !empty($matter->client_id)) {
			$namesPreview = implode(', ', array_slice($names, 0, 3));
			if ($count > 3) {
				$namesPreview .= '...';
			}
			$notificationMessage = $count > 1
				? "{$count} new checklist items added for matter {$matterNo}: {$namesPreview}"
				: "New checklist \"{$namesPreview}\" added for matter {$matterNo}";

			DB::table('notifications')->insert([
				'sender_id'      => $userId,
				'receiver_id'    => $matter->client_id,
				'module_id'      => $clientMatterId,
				'url'            => '/documents',
				'notification_type' => 'checklist_added',
				'message'        => $notificationMessage,
				'created_at'     => $now,
				'updated_at'     => $now,
				'sender_status'  => 1,
				'receiver_status' => 0,
				'seen'           => 0,
			]);

			// Broadcast notification count for live bell badge (client portal / mobile)
			try {
				$clientCount = DB::table('notifications')->where('receiver_id', $matter->client_id)->where('receiver_status', 0)->count();
				broadcast(new \App\Events\NotificationCountUpdated($matter->client_id, $clientCount, $notificationMessage, '/documents'));
			} catch (\Exception $e) {
				Log::warning('Failed to broadcast notification count after checklist add', ['client_id' => $matter->client_id, 'error' => $e->getMessage()]);
			}

			// Push notification to client mobile app
			try {
				$fcmService = new FCMService();
				$pushTitle = $count > 1 ? 'New checklists added' : 'New checklist added';
				$pushBody = $count > 1
					? "{$count} checklist items added for matter {$matterNo}"
					: "Checklist \"{$namesPreview}\" added for matter {$matterNo}";
				$pushData = [
					'type'             => 'checklist_added',
					'clientMatterId'   => (string) $clientMatterId,
					'matterNo'         => $matterNo,
					'checklistCount'   => (string) $count,
				];
				$fcmService->sendToUser($matter->client_id, $pushTitle, $pushBody, $pushData);
			} catch (\Exception $e) {
				Log::warning('Failed to send push notification for checklist add', [
					'client_id' => $matter->client_id,
					'error'    => $e->getMessage(),
				]);
			}
		}

		// Create action for Action page Client Portal tab so it appears in the list
		$clientMatter = ClientMatter::find($clientMatterId);
		if ($clientMatter) {
			$namesPreview = implode(', ', array_slice($names, 0, 5));
			if (count($names) > 5) {
				$namesPreview .= '...';
			}
			$desc = $count > 1
				? 'New checklists added for matter ' . $matterNo . ': ' . $namesPreview
				: 'New checklist added for matter ' . $matterNo . ': ' . $namesPreview;
			$this->createClientPortalAction($clientMatter, $desc);
		}

		return response()->json([
			'success' => true,
			'message' => $count . ' checklist' . ($count > 1 ? 's' : '') . ' added successfully.',
			'data'    => $inserted,
		]);
	}

	// checklistupload REMOVED - workflow checklist upload flow dead (no UI triggers it)

	public function deleteClientPortalDocs(Request $request){
		// Check if we're deleting by list_id (new method) or by id (old method for backward compatibility)
		if($request->has('list_id') && $request->list_id){
			// Delete all documents with the same cp_list_id
			$listId = $request->list_id;

			// Collect all matching documents before deletion so we can remove their S3 files
			$docsToDelete = Document::workflowChecklist()->where('cp_list_id', $listId)->get();

			// Get first document to get client_matter_id for response
			$appdoc = $docsToDelete->first();
			
			if($appdoc){
				// Remove each file from S3 (best-effort — failures are logged, never block DB delete)
				foreach ($docsToDelete as $docForS3) {
					$this->deleteS3File($docForS3->myfile);
				}

				// Delete all documents with this cp_list_id
				$res = Document::workflowChecklist()->where('cp_list_id', $listId)->delete();
				
				if($res){
				$response['status'] 	= 	true;
				$response['message'] 	= 	'Record removed successfully';

				// Notify client (for List Notifications API)
				$clientMatterId = $appdoc->client_matter_id ?? null;
				$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
				if ($clientMatter && !empty($clientMatter->client_id)) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
					$docList = DB::table('cp_doc_checklists')->where('id', $appdoc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($appdoc->file_name ?? 'Document');
				$notificationMessage = 'Document "' . $docType . '" removed for matter ' . $matterNo;
				DB::table('notifications')->insert([
						'sender_id' => Auth::guard('admin')->id(),
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $clientMatter->id,
						'url' => '/documents',
						'notification_type' => 'document_deleted',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);

					// Create action for Action page Client Portal tab
					$clientMatterModel = ClientMatter::find($clientMatterId);
					if ($clientMatterModel) {
						$desc = 'Document "' . $docType . '" deleted for matter ' . $matterNo;
						$this->createClientPortalAction($clientMatterModel, $desc);
					}
				}

				$clientMatterId = $appdoc->client_matter_id ?? null;
				$doclists = $clientMatterId ? Document::workflowChecklist()->where('client_matter_id', $clientMatterId)->orderBy('created_at','DESC')->get() : collect();
		$doclistdata = '';
		foreach($doclists as $doclist){
			$docdata = CpDocChecklist::where('id', $doclist->cp_list_id)->first();
			$fileUrl = ($doclist->myfile && str_starts_with($doclist->myfile, 'http')) ? $doclist->myfile : URL::to('/public/img/documents').'/'.$doclist->file_name;
			$docStatus = $doclist->cp_doc_status ?? 0;
			$doclistdata .= '<tr id="">';
				$doclistdata .= '<td>' . IconHelper::fromLegacy('fa fa-file') . ' '. $doclist->file_name.'<br>'.@$docdata->cp_checklist_name.'</td>';
				$doclistdata .= '<td>';
				$docType = $doclist->doc_type ?? '';
				if($docType == 'application'){ $doclistdata .= 'Application'; }else if($docType == 'acceptance'){ $doclistdata .=  'Acceptance'; }else if($docType == 'payment'){ $doclistdata .=  'Payment'; }else if($docType == 'formi20'){ $doclistdata .=  'Form I 20'; }else if($docType == 'visaapplication'){ $doclistdata .=  'Visa Application'; }else if($docType == 'interview'){ $doclistdata .=  'Interview'; }else if($docType == 'enrolment'){ $doclistdata .=  'Enrolment'; }else if($docType == 'courseongoing'){ $doclistdata .=  'Course Ongoing'; }else{ $doclistdata .= $docType; }
				$doclistdata .= '</td>';
				$staff = \App\Models\Staff::where('id', $doclist->user_id)->first();

			$doclistdata .= '<td><span style="    position: relative;background: rgb(3, 169, 244);font-size: .8rem;height: 24px;line-height: 24px;min-width: 24px;width: 24px;color: #fff;display: block;font-weight: 600;letter-spacing: 1px;text-align: center;border-radius: 50%;overflow: hidden;">'.($staff ? substr($staff->first_name, 0, 1) : '?').'</span>'.($staff ? $staff->first_name : 'System').'</td>';
			$doclistdata .= '<td>'.date('Y-m-d',strtotime($doclist->created_at)).'</td>';
			$doclistdata .= '<td>';
			if($docStatus == 1){
				$doclistdata .= '<span class="check">' . IconHelper::fromLegacy('fa fa-eye') . '</span>';
			}
				$doclistdata .= '<div class="dropdown d-inline">
					<button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
					<div class="dropdown-menu">
						<a target="_blank" class="dropdown-item" href="'.$fileUrl.'">Preview</a>
						<a data-id="'.$doclist->id.'" class="dropdown-item deletenote" data-href="deleteclientportaldocs" href="javascript:;">Delete</a>
						<a download class="dropdown-item" href="'.$fileUrl.'">Download</a>';
						if($docStatus == 0){
							$doclistdata .= '<a data-id="'.$doclist->id.'" class="dropdown-item publishdoc" href="javascript:;">Publish Document</a>';
						}else{
							$doclistdata .= '<a data-id="'.$doclist->id.'"  class="dropdown-item unpublishdoc" href="javascript:;">Unpublish Document</a>';
						}

					$doclistdata .= '</div>
				</div>
			</td>';
			$doclistdata .= '</tr>';
		}
		$clientMatterId = $appdoc->client_matter_id ?? null;
		$applicationuploadcount = $clientMatterId ? DB::select("SELECT COUNT(DISTINCT cp_list_id) AS cnt FROM documents WHERE cp_list_id IS NOT NULL AND client_matter_id = " . (int)$clientMatterId) : [((object)['cnt' => 0])];
		$response['status'] 	= 	true;

		$response['doclistdata']	=	$doclistdata;
		$response['client_portal_upload_count']	=	@$applicationuploadcount[0]->cnt;

		$checklistItems = $clientMatterId ? CpDocChecklist::where('client_matter_id', $clientMatterId)->get() : collect();
			$checklistdata = '<table class="table"><tbody>';
			foreach($checklistItems as $checklistItem){
				$appcount = Document::workflowChecklist()->where('cp_list_id', $checklistItem->id)->count();
				$checklistdata .= '<tr>';
				if($appcount >0){
					$checklistdata .= '<td><span class="check">' . IconHelper::fromLegacy('fa fa-check') . '</span></td>';
				}else{
					$checklistdata .= '<td><span class="round"></span></td>';
				}

				$checklistdata .= '<td>'.@$checklistItem->cp_checklist_name.'</td>';
				$checklistdata .= '<td><div class="circular-box cursor-pointer"><button class="transparent-button paddingNone">'.$appcount.'</button></div></td>';
			$checklistdata .= '</tr>';
		}
		$checklistdata .= '</tbody></table>';
		$response['checklistdata']	=	$checklistdata;
		$response['type']	=	$appdoc->doc_type ?? $appdoc->type ?? '';
			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'Please try again';
			}
			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'No Record found with this list_id';
			}
			echo json_encode($response);
			return;
		}
		
		// Backward compatibility: Delete by document id (old method)
		$docToDelete = Document::workflowChecklist()->where('id', $request->note_id)->first();
		if($docToDelete){
			$appdoc = $docToDelete;
			$res = $docToDelete->delete();
			if($res){
				$response['status'] 	= 	true;
				$response['message'] 	= 	'Record removed successfully';

				// Notify client (for List Notifications API)
				$clientMatterId = $appdoc->client_matter_id ?? null;
				$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
				if ($clientMatter && !empty($clientMatter->client_id)) {
					$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
					$docList = DB::table('cp_doc_checklists')->where('id', $appdoc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($appdoc->file_name ?? 'Document');
				$notificationMessage = 'Document "' . $docType . '" removed for matter ' . $matterNo;
				DB::table('notifications')->insert([
						'sender_id' => Auth::guard('admin')->id(),
						'receiver_id' => $clientMatter->client_id,
						'module_id' => $clientMatter->id,
						'url' => '/documents',
						'notification_type' => 'document_deleted',
						'message' => $notificationMessage,
						'created_at' => now(),
						'updated_at' => now(),
						'sender_status' => 1,
						'receiver_status' => 0,
						'seen' => 0
					]);

					// Create action for Action page Client Portal tab
					$clientMatterModel = ClientMatter::find($clientMatterId);
					if ($clientMatterModel) {
						$desc = 'Document "' . $docType . '" deleted for matter ' . $matterNo;
						$this->createClientPortalAction($clientMatterModel, $desc);
					}
				}

				$clientMatterId = $appdoc->client_matter_id ?? null;
				$doclists = $clientMatterId ? Document::workflowChecklist()->where('client_matter_id', $clientMatterId)->orderBy('created_at','DESC')->get() : collect();
		$doclistdata = '';
		foreach($doclists as $doclist){
			$docdata = CpDocChecklist::where('id', $doclist->cp_list_id)->first();
			$fileUrl = ($doclist->myfile && str_starts_with($doclist->myfile, 'http')) ? $doclist->myfile : URL::to('/public/img/documents').'/'.$doclist->file_name;
			$docStatus = $doclist->cp_doc_status ?? 0;
			$doclistdata .= '<tr id="">';
				$doclistdata .= '<td>' . IconHelper::fromLegacy('fa fa-file') . ' '. $doclist->file_name.'<br>'.@$docdata->cp_checklist_name.'</td>';
				$doclistdata .= '<td>';
				$docType = $doclist->doc_type ?? '';
				if($docType == 'application'){ $doclistdata .= 'Application'; }else if($docType == 'acceptance'){ $doclistdata .=  'Acceptance'; }else if($docType == 'payment'){ $doclistdata .=  'Payment'; }else if($docType == 'formi20'){ $doclistdata .=  'Form I 20'; }else if($docType == 'visaapplication'){ $doclistdata .=  'Visa Application'; }else if($docType == 'interview'){ $doclistdata .=  'Interview'; }else if($docType == 'enrolment'){ $doclistdata .=  'Enrolment'; }else if($docType == 'courseongoing'){ $doclistdata .=  'Course Ongoing'; }else{ $doclistdata .= $docType; }
				$doclistdata .= '</td>';
				$staff = \App\Models\Staff::where('id', $doclist->user_id)->first();

			$doclistdata .= '<td><span style="    position: relative;background: rgb(3, 169, 244);font-size: .8rem;height: 24px;line-height: 24px;min-width: 24px;width: 24px;color: #fff;display: block;font-weight: 600;letter-spacing: 1px;text-align: center;border-radius: 50%;overflow: hidden;">'.($staff ? substr($staff->first_name, 0, 1) : '?').'</span>'.($staff ? $staff->first_name : 'System').'</td>';
			$doclistdata .= '<td>'.date('Y-m-d',strtotime($doclist->created_at)).'</td>';
			$doclistdata .= '<td>';
			if($docStatus == 1){
				$doclistdata .= '<span class="check">' . IconHelper::fromLegacy('fa fa-eye') . '</span>';
			}
				$doclistdata .= '<div class="dropdown d-inline">
					<button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
					<div class="dropdown-menu">
						<a target="_blank" class="dropdown-item" href="'.$fileUrl.'">Preview</a>
						<a data-id="'.$doclist->id.'" class="dropdown-item deletenote" data-href="deleteclientportaldocs" href="javascript:;">Delete</a>
						<a download class="dropdown-item" href="'.$fileUrl.'">Download</a>';
						if($docStatus == 0){
							$doclistdata .= '<a data-id="'.$doclist->id.'" class="dropdown-item publishdoc" href="javascript:;">Publish Document</a>';
						}else{
							$doclistdata .= '<a data-id="'.$doclist->id.'"  class="dropdown-item unpublishdoc" href="javascript:;">Unpublish Document</a>';
						}

					$doclistdata .= '</div>
				</div>
			</td>';
			$doclistdata .= '</tr>';
		}

		$response['status'] 	= 	true;

		$response['doclistdata']	=	$doclistdata;

			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'Please try again';
			}
		}else{
			$response['status'] 	= 	false;
			$response['message'] 	= 	'No Record found';
		}
		echo json_encode($response);
	}

	public function publishdoc(Request $request){
		$doc = Document::workflowChecklist()->where('id', $request->appid)->first();
		if($doc){
			$doc->cp_doc_status = (int) $request->status;
			$saved = $doc->save();
			if($saved){
				$response['status'] 	= 	true;
				$response['message'] 	= 	'Record updated successfully';
				$clientMatterId = $doc->client_matter_id ?? null;
				$doclists = $clientMatterId ? Document::workflowChecklist()->where('client_matter_id', $clientMatterId)->orderBy('created_at','DESC')->get() : collect();
		$doclistdata = '';
		foreach($doclists as $doclist){
			$docdata = CpDocChecklist::where('id', $doclist->cp_list_id)->first();
			$fileUrl = ($doclist->myfile && str_starts_with($doclist->myfile, 'http')) ? $doclist->myfile : URL::to('/public/img/documents').'/'.$doclist->file_name;
			$docStatus = $doclist->cp_doc_status ?? 0;
			$doclistdata .= '<tr id="">';
				$doclistdata .= '<td>' . IconHelper::fromLegacy('fa fa-file') . ' '. $doclist->file_name.'<br>'.@$docdata->cp_checklist_name.'</td>';
				$doclistdata .= '<td>';
				$docType = $doclist->doc_type ?? '';
				if($docType == 'application'){ $doclistdata .= 'Application'; }else if($docType == 'acceptance'){ $doclistdata .=  'Acceptance'; }else if($docType == 'payment'){ $doclistdata .=  'Payment'; }else if($docType == 'formi20'){ $doclistdata .=  'Form I 20'; }else if($docType == 'visaapplication'){ $doclistdata .=  'Visa Application'; }else if($docType == 'interview'){ $doclistdata .=  'Interview'; }else if($docType == 'enrolment'){ $doclistdata .=  'Enrolment'; }else if($docType == 'courseongoing'){ $doclistdata .=  'Course Ongoing'; }else{ $doclistdata .= $docType; }
				$doclistdata .= '</td>';
				$staff = \App\Models\Staff::where('id', $doclist->user_id)->first();

			$doclistdata .= '<td><span style="    position: relative;background: rgb(3, 169, 244);font-size: .8rem;height: 24px;line-height: 24px;min-width: 24px;width: 24px;color: #fff;display: block;font-weight: 600;letter-spacing: 1px;text-align: center;border-radius: 50%;overflow: hidden;">'.($staff ? substr($staff->first_name, 0, 1) : '?').'</span>'.($staff ? $staff->first_name : 'System').'</td>';
			$doclistdata .= '<td>'.date('Y-m-d',strtotime($doclist->created_at)).'</td>';
			$doclistdata .= '<td>';
			if($docStatus == 1){
				$doclistdata .= '<span class="check">' . IconHelper::fromLegacy('fa fa-eye') . '</span>';
			}
				$doclistdata .= '<div class="dropdown d-inline">
					<button class="btn btn-primary dropdown-toggle" type="button" id="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
					<div class="dropdown-menu">
						<a target="_blank" class="dropdown-item" href="'.$fileUrl.'">Preview</a>
						<a data-id="'.$doclist->id.'" class="dropdown-item deletenote" data-href="deleteclientportaldocs" href="javascript:;">Delete</a>
						<a download class="dropdown-item" href="'.$fileUrl.'">Download</a>';
						if($docStatus == 0){
							$doclistdata .= '<a data-id="'.$doclist->id.'" class="dropdown-item publishdoc" href="javascript:;">Publish Document</a>';
						}else{
							$doclistdata .= '<a data-id="'.$doclist->id.'"  class="dropdown-item unpublishdoc" href="javascript:;">Unpublish Document</a>';
						}

					$doclistdata .= '</div>
				</div>
			</td>';
			$doclistdata .= '</tr>';
		}

		$response['status'] 	= 	true;

		$response['doclistdata']	=	$doclistdata;

			}else{
				$response['status'] 	= 	false;
				$response['message'] 	= 	'Please try again';
			}
		}else{
			$response['status'] 	= 	false;
			$response['message'] 	= 	'No Record found';
		}
		echo json_encode($response);
	}

	public function getapplications(Request $request){
		$client_id = $request->client_id;
		$matters = ClientMatter::where('client_id', '=', $client_id)->orderBy('id','desc')->get();
		ob_start();
		?>
		<option value="">Choose Matter</option>
		<?php
		foreach($matters as $matter){
			$label = $matter->client_unique_matter_no ?? 'Matter #' . $matter->id;
			?>
		<option value="<?php echo $matter->id; ?>"><?php echo e($label); ?></option>
			<?php
		}
		return ob_get_clean();
	}

	// REMOVED - Standalone migration index page (not linked from anywhere, orphaned page)
	// public function migrationindex(Request $request)
	// {
	// }

	// REMOVED - Applications import functionality (only used by removed applications index page)
	// public function import(Request $request){
	// }

	public function approveDocument(Request $request){
		$response = ['status' => false, 'message' => 'Error approving document.'];
		
		try {
			$documentId = $request->input('document_id');
			
			if (!$documentId) {
				$response['message'] = 'Document ID is required.';
				return response()->json($response);
			}
			
			// Update document cp_doc_status to 1 (Approved)
			$updated = DB::table('documents')
				->where('id', $documentId)
				->whereNotNull('cp_list_id')
				->update([
					'cp_doc_status' => 1,
					'updated_at' => now()
				]);
			
			if ($updated) {
				// Log activity (Client Portal Documents tab - website)
				$doc = DB::table('documents')->where('id', $documentId)->first();
				if ($doc) {
					$clientMatterId = $doc->client_matter_id ?? null;
					$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
					if ($clientMatter && !empty($clientMatter->client_id)) {
						if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
							DB::table('activities_logs')->insert([
								'client_id' => $clientMatter->client_id,
								'created_by' => Auth::guard('admin')->id(),
								'subject' => 'Approved document in Client Portal (Documents tab)',
								'description' => 'Document approved via Client Portal tab (website) for document ID: ' . $documentId,
								'task_status' => 0,
								'pin' => 0,
								'source' => 'client_portal_web',
								'created_at' => now(),
								'updated_at' => now()
							]);
						}

						// Notify client (for List Notifications API)
						$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
						$docList = DB::table('cp_doc_checklists')->where('id', $doc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($doc->file_name ?? 'Document');
					$notificationMessage = 'Document "' . $docType . '" approved for matter ' . $matterNo;
						DB::table('notifications')->insert([
							'sender_id' => Auth::guard('admin')->id(),
							'receiver_id' => $clientMatter->client_id,
							'module_id' => $clientMatter->id,
							'url' => '/documents',
							'notification_type' => 'document_approved',
							'message' => $notificationMessage,
							'created_at' => now(),
							'updated_at' => now(),
							'sender_status' => 1,
							'receiver_status' => 0,
							'seen' => 0
						]);

						// Create action for Action page Client Portal tab
						$clientMatterModel = ClientMatter::find($clientMatterId);
						if ($clientMatterModel) {
							$desc = 'Document "' . $docType . '" approved for matter ' . $matterNo;
							$this->createClientPortalAction($clientMatterModel, $desc);
						}
					}
				}
				$response['status'] = true;
				$response['message'] = 'Document approved successfully!';
			} else {
				$response['message'] = 'Document not found or could not be updated.';
			}
		} catch (\Exception $e) {
			$response['message'] = 'An error occurred: ' . $e->getMessage();
		}

		return response()->json($response);
	}

	public function rejectDocument(Request $request){
		$response = ['status' => false, 'message' => 'Error rejecting document.'];
		
		try {
			$documentId = $request->input('document_id');
			$rejectReason = $request->input('reject_reason');
			
			if (!$documentId) {
				$response['message'] = 'Document ID is required.';
				return response()->json($response);
			}
			
			if (!$rejectReason || trim($rejectReason) === '') {
				$response['message'] = 'Rejection reason is required.';
				return response()->json($response);
			}
			
			// Update cp_doc_status to 2 (Rejected) and cp_rejection_reason
			$updateData = [
				'cp_doc_status' => 2,
				'cp_rejection_reason' => trim($rejectReason),
				'updated_at' => now()
			];
			
			// Update document status to 2 (Rejected)
			$updated = DB::table('documents')
				->where('id', $documentId)
				->whereNotNull('cp_list_id')
				->update($updateData);
			
			if ($updated) {
				// Log activity (Client Portal Documents tab - website)
				$doc = DB::table('documents')->where('id', $documentId)->first();
				if ($doc) {
					$clientMatterId = $doc->client_matter_id ?? null;
					$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
					if ($clientMatter && !empty($clientMatter->client_id)) {
						if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
							DB::table('activities_logs')->insert([
								'client_id' => $clientMatter->client_id,
								'created_by' => Auth::guard('admin')->id(),
								'subject' => 'Rejected document in Client Portal (Documents tab)',
								'description' => 'Document rejected via Client Portal tab (website) for document ID: ' . $documentId . (trim($rejectReason ?? '') !== '' ? '. Reason: ' . trim($rejectReason) : ''),
								'task_status' => 0,
								'pin' => 0,
								'source' => 'client_portal_web',
								'created_at' => now(),
								'updated_at' => now()
							]);
						}

						// Notify client (for List Notifications API)
						$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
						$docList = DB::table('cp_doc_checklists')->where('id', $doc->cp_list_id)->first();
$docType = $docList ? $docList->cp_checklist_name : ($doc->file_name ?? 'Document');
					$notificationMessage = 'Document "' . $docType . '" rejected for matter ' . $matterNo;
						DB::table('notifications')->insert([
							'sender_id' => Auth::guard('admin')->id(),
							'receiver_id' => $clientMatter->client_id,
							'module_id' => $clientMatter->id,
							'url' => '/documents',
							'notification_type' => 'document_rejected',
							'message' => $notificationMessage,
							'created_at' => now(),
							'updated_at' => now(),
							'sender_status' => 1,
							'receiver_status' => 0,
							'seen' => 0
						]);

						// Create action for Action page Client Portal tab
						$clientMatterModel = ClientMatter::find($clientMatterId);
						if ($clientMatterModel) {
							$desc = 'Document "' . $docType . '" rejected for matter ' . $matterNo;
							$this->createClientPortalAction($clientMatterModel, $desc);
						}
					}
				}
				$response['status'] = true;
				$response['message'] = 'Document rejected successfully!';
			} else {
				$response['message'] = 'Document not found or could not be updated.';
			}
		} catch (\Exception $e) {
			$response['message'] = 'An error occurred: ' . $e->getMessage();
		}
		
		return response()->json($response);
	}

	public function downloadDocument(Request $request){
		$response = ['status' => false, 'message' => 'Error downloading document.'];
		
		try {
			$documentId = $request->input('document_id');
			
			if (!$documentId) {
				$response['message'] = 'Document ID is required.';
				return response()->json($response);
			}
			
			// Get document from database (workflow checklist docs only)
			$document = DB::table('documents')
				->where('id', $documentId)
				->whereNotNull('cp_list_id')
				->first();
			
			if (!$document) {
				$response['message'] = 'Document not found.';
				return response()->json($response);
			}
			
			$fileName = $document->file_name ?: 'document.pdf';
			$fileContent = false;
			
			// Prefer S3/URL (myfile) when available
			if ($document->myfile && str_starts_with((string) $document->myfile, 'http')) {
				$fileUrl = $document->myfile;
				$fileContent = @file_get_contents($fileUrl);
			}
			
			// Fallback: local file (workflow checklist via website upload)
			if ($fileContent === false && $fileName) {
				$localPath = config('constants.documents') . '/' . $fileName;
				if (file_exists($localPath)) {
					$fileContent = file_get_contents($localPath);
				}
			}
			
			// Retry S3 via cURL if file_get_contents failed
			if ($fileContent === false && $document->myfile && str_starts_with((string) $document->myfile, 'http')) {
				$ch = curl_init($document->myfile);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				$fileContent = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if ($httpCode !== 200 || $fileContent === false) {
					$fileContent = false;
				}
			}
			
			if ($fileContent === false) {
				$response['message'] = 'Document not found or could not be retrieved.';
				return response()->json($response);
			}
			
			$fileUrl = $document->myfile ?? '';
			
			// Determine content type based on file extension or file URL
			$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			
			// If extension is empty, try to get it from URL (for S3 links)
			if (empty($extension) && $fileUrl) {
				$urlPath = parse_url($fileUrl, PHP_URL_PATH);
				if ($urlPath) {
					$urlExtension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
					if (!empty($urlExtension)) {
						$extension = $urlExtension;
					}
				}
			}
			
			// Default to PDF if extension still empty
			if (empty($extension)) {
				$extension = 'pdf';
			}
			
			$contentType = 'application/octet-stream';
			
			if ($extension === 'pdf') {
				$contentType = 'application/pdf';
			} elseif (in_array($extension, ['jpg', 'jpeg'])) {
				$contentType = 'image/jpeg';
			} elseif ($extension === 'png') {
				$contentType = 'image/png';
			} elseif ($extension === 'doc') {
				$contentType = 'application/msword';
			} elseif ($extension === 'docx') {
				$contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			}
			
			// Ensure filename has proper extension
			if (empty(pathinfo($fileName, PATHINFO_EXTENSION))) {
				$fileName .= '.' . $extension;
			}
			
			// Ensure filename is properly encoded
			$encodedFileName = rawurlencode($fileName);
			
			// Notify client (for List Notifications API)
			$clientMatterId = $document->client_matter_id ?? null;
			$clientMatter = $clientMatterId ? DB::table('client_matters')->where('id', $clientMatterId)->first() : null;
			if ($clientMatter && !empty($clientMatter->client_id) && Auth::guard('admin')->check()) {
				$matterNo = $clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatter->id;
				$docList = DB::table('cp_doc_checklists')->where('id', $document->cp_list_id)->first();
				$docType = $docList ? $docList->cp_checklist_name : ($document->file_name ?? 'Document');
				$notificationMessage = 'Document "' . $docType . '" downloaded for matter ' . $matterNo;
				DB::table('notifications')->insert([
					'sender_id' => Auth::guard('admin')->id(),
					'receiver_id' => $clientMatter->client_id,
					'module_id' => $clientMatter->id,
					'url' => '/documents',
					'notification_type' => 'document_downloaded',
					'message' => $notificationMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);
			}
			
			// Return file as download with proper headers to force download
			return response($fileContent, 200)
				->header('Content-Type', $contentType)
				->header('Content-Disposition', 'attachment; filename="' . addslashes($fileName) . '"; filename*=UTF-8\'\'' . $encodedFileName)
				->header('Content-Length', strlen($fileContent))
				->header('Cache-Control', 'no-cache, no-store, must-revalidate')
				->header('Pragma', 'no-cache')
				->header('Expires', '0')
				->header('X-Content-Type-Options', 'nosniff');
				
		} catch (\Exception $e) {
			$response['message'] = 'An error occurred: ' . $e->getMessage();
			return response()->json($response);
		}
	}

	/**
     * Get Messages for a Client Matter
     * GET /clients/matter-messages
     * 
     * Retrieves all messages for a specific client matter for admin view
     * Used in the client portal application tab
     */
	public function getMatterMessages(Request $request)
	{
		try {
			$request->validate([
				'client_matter_id' => 'required|integer|min:1'
			]);

			$clientMatterId = $request->input('client_matter_id');
			$currentUserId = Auth::guard('admin')->id();

			if (!$currentUserId) {
				return response()->json([
					'success' => false,
					'message' => 'Unauthorized'
				], 401);
			}

			// Get all messages for this client matter, ordered by created_at ascending (oldest first)
			$messages = DB::table('messages')
				->where('client_matter_id', $clientMatterId)
				->orderBy('created_at', 'asc')
				->orderBy('id', 'asc')
				->get()
				->map(function ($message) use ($currentUserId) {
					// Get sender info (from admins or staff table)
					$sender = null;
					if ($message->sender_id) {
						$sender = DB::table('admins')
							->where('id', $message->sender_id)
							->select('id', 'first_name', 'last_name', DB::raw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) as full_name"))
							->first();
						if (!$sender) {
							$sender = DB::table('staff')
								->where('id', $message->sender_id)
								->select('id', 'first_name', 'last_name', DB::raw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) as full_name"))
								->first();
						}
					}

					// Get all recipients for this message
					$recipients = MessageRecipient::where('message_id', $message->id)
						->get()
						->map(function ($recipient) {
							$recipientUser = DB::table('admins')
								->where('id', $recipient->recipient_id)
								->select('id', 'first_name', 'last_name', DB::raw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) as full_name"))
								->first();
							
							return [
								'recipient_id' => $recipient->recipient_id,
								'recipient_name' => $recipient->recipient,
								'is_read' => $recipient->is_read,
								'read_at' => $recipient->read_at,
								'client' => $recipientUser
							];
						});

					// Determine if message is from current user (sent) or to current user (received)
					$isSent = ($message->sender_id == $currentUserId);
					
					// For sent messages: check if any recipient has read (for WhatsApp-style read receipt icon)
					$readByRecipient = false;
					$readAtEarliest = null;
					if ($isSent && $recipients->isNotEmpty()) {
						$readByRecipient = $recipients->contains(fn ($r) => (bool) ($r['is_read'] ?? false));
						if ($readByRecipient) {
							$readRecipients = $recipients->filter(fn ($r) => (bool) ($r['is_read'] ?? false));
							$readAtEarliest = $readRecipients->min('read_at');
						}
					}
					
					// Generate sender initials
					$senderInitials = '';
					if ($sender) {
						$firstInitial = $sender->first_name ? strtoupper(substr($sender->first_name, 0, 1)) : '';
						$lastInitial = $sender->last_name ? strtoupper(substr($sender->last_name, 0, 1)) : '';
						$senderInitials = $firstInitial . $lastInitial;
					}

					// Load attachments from message_attachments table
					$attachments = [];
					if (Schema::hasTable('message_attachments')) {
						$attachmentsRows = DB::table('message_attachments')
							->where('message_id', $message->id)
							->get();
						foreach ($attachmentsRows as $att) {
							$attachments[] = [
								'id' => $att->id,
								'type' => $att->type ?? 'document',
								'filename' => $att->original_filename ?? $att->filename,
								'url' => route('clients.message-attachment-download', ['id' => $att->id]),
								'size' => $att->size ?? null,
							];
						}
					}

					return [
						'id' => $message->id,
						'message' => $message->message,
						'sender_id' => $message->sender_id,
						'sender_name' => $message->sender,
						'sender' => $sender,
						'sender_initials' => $senderInitials,
						'sent_at' => $message->sent_at ? $message->sent_at : $message->created_at,
						'created_at' => $message->created_at,
						'client_matter_id' => $message->client_matter_id,
						'recipients' => $recipients,
						'is_sent' => $isSent,
						'read_by_recipient' => $readByRecipient,
						'read_at' => $readAtEarliest,
						'attachments' => $attachments
					];
				});

			return response()->json([
				'success' => true,
				'data' => [
					'messages' => $messages->values(), // Ensure it's a proper array
					'total' => $messages->count()
				]
			], 200);

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $e->errors()
			], 422);
		} catch (\Exception $e) {
			Log::error('Get Matter Messages Error: ' . $e->getMessage(), [
				'client_matter_id' => $request->input('client_matter_id'),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Failed to fetch messages',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Mark Message as Read (Web Route)
	 * POST /clients/messages/{id}/mark-read
	 *
	 * Marks a message as read for the current user (staff) when they view it in the Client Portal Messages tab.
	 * Uses session-based auth. Broadcasts MessageUpdated/MessageReceived so the sender (client on mobile) sees "Read".
	 */
	public function markMessageAsRead(Request $request, $id)
	{
		try {
			$currentUserId = Auth::guard('admin')->id();
			if (!$currentUserId) {
				return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
			}

			$message = DB::table('messages')->where('id', $id)->first();
			if (!$message) {
				return response()->json(['success' => false, 'message' => 'Message not found'], 404);
			}

			$recipientRecord = MessageRecipient::where('message_id', $id)
				->where('recipient_id', $currentUserId)
				->first();

			if (!$recipientRecord) {
				return response()->json(['success' => false, 'message' => 'You are not authorized to mark this message as read'], 403);
			}

			if (!$recipientRecord->is_read) {
				MessageRecipient::where('message_id', $id)
					->where('recipient_id', $currentUserId)
					->update([
						'is_read' => true,
						'read_at' => now(),
						'updated_at' => now()
					]);

				$updatedRecipient = MessageRecipient::where('message_id', $id)
					->where('recipient_id', $currentUserId)
					->first();

				$messageForBroadcast = [
					'id' => $message->id,
					'message' => $message->message,
					'sender' => $message->sender,
					'sender_id' => $message->sender_id,
					'recipient_id' => (int) $currentUserId,
					'is_read' => true,
					'read_at' => $updatedRecipient->read_at,
					'sent_at' => $message->sent_at,
					'client_matter_id' => $message->client_matter_id
				];

				try {
					broadcast(new MessageUpdated($messageForBroadcast, $message->sender_id));
				} catch (\Exception $e) {
					Log::warning('Failed to broadcast message update to sender', [
						'sender_id' => $message->sender_id,
						'message_id' => $id,
						'error' => $e->getMessage()
					]);
				}

				try {
					broadcast(new MessageReceived($id, $message->sender_id));
				} catch (\Exception $e) {
					Log::warning('Failed to broadcast read status to sender', [
						'sender_id' => $message->sender_id,
						'message_id' => $id,
						'error' => $e->getMessage()
					]);
				}

				try {
					$unreadCount = MessageRecipient::where('recipient_id', $currentUserId)
						->where('is_read', false)
						->count();
					broadcast(new UnreadCountUpdated($currentUserId, $unreadCount));
				} catch (\Exception $e) {
					Log::warning('Failed to broadcast unread count update', [
						'user_id' => $currentUserId,
						'error' => $e->getMessage()
					]);
				}
			}

			return response()->json(['success' => true, 'message' => 'Message marked as read'], 200);

		} catch (\Exception $e) {
			Log::error('Mark Message as Read Error: ' . $e->getMessage(), [
				'user_id' => Auth::guard('admin')->id(),
				'message_id' => $id,
				'trace' => $e->getTraceAsString()
			]);
			return response()->json([
				'success' => false,
				'message' => 'Failed to mark message as read',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
     * Send Message to Client (Web Route)
     * POST /clients/send-message
     * 
     * Sends a message to the client associated with the client matter
     * Uses session-based authentication for web admin users
     */
	public function sendMessageToClient(Request $request)
	{
		try {
			$hasFiles = $request->hasFile('attachments') || $request->hasFile('attachments.*');
			$messageText = $request->input('message', '');
			$messageText = is_string($messageText) ? trim($messageText) : '';

			$rules = [
				'client_matter_id' => 'required|integer|min:1',
				'message' => ['nullable', 'string', 'max:5000'],
				'attachments' => ['nullable', 'array'],
				'attachments.*' => ['file', 'max:10240'], // 10MB per file
			];
			if (!$hasFiles) {
				$rules['message'] = ['required', 'string', 'max:5000'];
			}
			$request->validate($rules);

			$admin = Auth::guard('admin')->user();
			if (!$admin) {
				return response()->json([
					'success' => false,
					'message' => 'Unauthorized'
				], 401);
			}

			$senderId = $admin->id;
			$message = $messageText ?: '';
			$clientMatterId = (int) $request->input('client_matter_id');

			// Get client matter info to find the client_id
			$clientMatter = DB::table('client_matters')
				->where('id', $clientMatterId)
				->first();

			if (!$clientMatter) {
				return response()->json([
					'success' => false,
					'message' => 'Client matter not found'
				], 404);
			}

			$clientId = $clientMatter->client_id;
			
			if (!$clientId) {
				return response()->json([
					'success' => false,
					'message' => 'No client associated with this matter'
				], 422);
			}

			// Get sender information
			$sender = DB::table('admins')
				->where('id', $senderId)
				->select('id', 'first_name', 'last_name', DB::raw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) as full_name"))
				->first();

			$senderName = $sender ? $sender->full_name : 'Admin';
			$senderInitials = '';
			if ($sender) {
				$firstInitial = $sender->first_name ? strtoupper(substr($sender->first_name, 0, 1)) : '';
				$lastInitial = $sender->last_name ? strtoupper(substr($sender->last_name, 0, 1)) : '';
				$senderInitials = $firstInitial . $lastInitial;
			}

			// Get recipient information
			$recipientUser = DB::table('admins')
				->where('id', $clientId)
				->select('id', 'first_name', 'last_name', DB::raw("(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) as full_name"))
				->first();

			if (!$recipientUser) {
				return response()->json([
					'success' => false,
					'message' => 'Client user not found'
				], 404);
			}

			// Create message record
			$messageData = [
				'message' => $message,
				'sender' => $senderName,
				'sender_id' => $senderId,
				'sent_at' => now(),
				'client_matter_id' => $clientMatterId,
				'created_at' => now(),
				'updated_at' => now()
			];

			$messageId = DB::table('messages')->insertGetId($messageData);

			if ($messageId) {
				// Handle file attachments
				$attachmentsForResponse = [];
				if ($request->hasFile('attachments')) {
					$files = $request->file('attachments');
					if (!is_array($files)) {
						$files = [$files];
					}
					$allowedImages = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
					$allowedDocs = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv'];
					$allowedMimes = array_merge($allowedImages, $allowedDocs);
					$attachmentStorage = app(MessageAttachmentStorageService::class);

					foreach ($files as $file) {
						if (!$file->isValid()) continue;
						$mime = $file->getMimeType();
						if (!in_array($mime, $allowedMimes)) continue;

						$type = in_array($mime, $allowedImages) ? 'image' : 'document';
						$ext = $file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
						$safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '_' . uniqid() . '.' . ($ext ?: 'bin');
						$path = $attachmentStorage->storeUploadedFile($file, $safeName);
						if (!$path) continue;

						$attInsertId = DB::table('message_attachments')->insertGetId([
							'message_id' => $messageId,
							'filename' => $safeName,
							'original_filename' => $file->getClientOriginalName(),
							'path' => $path,
							'mime_type' => $mime,
							'type' => $type,
							'size' => $file->getSize(),
							'created_at' => now(),
							'updated_at' => now(),
						]);

						$attachmentsForResponse[] = [
							'id' => $attInsertId,
							'type' => $type,
							'filename' => $file->getClientOriginalName(),
							'url' => route('clients.message-attachment-download', ['id' => $attInsertId]),
							'path' => $path,
							'size' => $file->getSize(),
						];
					}
				}

				// Insert recipient into pivot table
				MessageRecipient::insert([
					'message_id' => $messageId,
					'recipient_id' => $clientId,
					'recipient' => $recipientUser->full_name,
					'is_read' => false,
					'read_at' => null,
					'created_at' => now(),
					'updated_at' => now()
				]);

				// Activity Feed: omit when sending from Client Portal tab (request marks source / current_tab).
				if (!$this->shouldOmitActivitiesLogForClientPortalWebContext($request)) {
					DB::table('activities_logs')->insert([
						'client_id' => $clientId,
						'created_by' => $senderId,
						'subject' => 'Message sent to client',
						'description' => 'Message sent from Client Portal tab (website) to client ' . $recipientUser->full_name . ' for matter ID: ' . $clientMatterId,
						'task_status' => 0,
						'pin' => 0,
						'source' => 'client_portal_web',
						'created_at' => now(),
						'updated_at' => now()
					]);
				}

				// Actor name for notification and Action page: Super admin or PERSON ASSISTING / staff name
				$actor = Auth::guard('admin')->user();
				$actorName = ($actor && (int) $actor->role === 1) ? 'Super admin' : ($actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'Staff');
				if ($actorName === '') {
					$actorName = 'Staff';
				}
				$matterName = $clientMatter->client_unique_matter_no ?? ('ID: ' . $clientMatterId);
				$messageExcerpt = $message !== '' ? (mb_strlen($message) > 80 ? mb_substr($message, 0, 77) . '...' : $message) : ($hasFiles ? 'attachment(s)' : 'message');
				$actionMessage = $actorName . ' sent message - "' . $messageExcerpt . '" in ' . $matterName . '.';

				// Create action for Action page Client Portal tab (message format: Actor sent message - "..." in MatterName)
				$clientMatterModel = ClientMatter::find($clientMatterId);
				if ($clientMatterModel) {
					$this->createClientPortalAction($clientMatterModel, $actionMessage);
				}

				// Notify client (for List Notifications API) with same message format
				DB::table('notifications')->insert([
					'sender_id' => $senderId,
					'receiver_id' => $clientId,
					'module_id' => $clientMatterId,
					'url' => '/messages',
					'notification_type' => 'message',
					'message' => $actionMessage,
					'created_at' => now(),
					'updated_at' => now(),
					'sender_status' => 1,
					'receiver_status' => 0,
					'seen' => 0
				]);

				// Broadcast notification count update for live bell badge (client receives new notification)
				try {
					$clientCount = (int) DB::table('notifications')->where('receiver_id', $clientId)->where('receiver_status', 0)->count();
					broadcast(new \App\Events\NotificationCountUpdated($clientId, $clientCount, $actionMessage, '/messages'));
				} catch (\Exception $e) {
					Log::warning('Failed to broadcast notification count to client', ['client_id' => $clientId, 'error' => $e->getMessage()]);
				}

				// Also broadcast to sender (agent) so bell flashes with current count as send feedback
				try {
					$senderCount = DB::table('notifications')->where('receiver_id', $senderId)->where('receiver_status', 0)->count();
					broadcast(new \App\Events\NotificationCountUpdated($senderId, $senderCount));
				} catch (\Exception $e) {
					Log::warning('Failed to broadcast notification count to sender', ['sender_id' => $senderId, 'error' => $e->getMessage()]);
				}

				// Broadcast message via Laravel Reverb (sender/sender_name for frontend display)
				$senderDisplayStr = $senderName ?: 'Agent';
				$messageForBroadcast = [
					'id' => $messageId,
					'message' => $message,
					'sender' => $senderDisplayStr,
					'sender_name' => $senderDisplayStr,
					'sender_id' => $senderId,
					'sender_initials' => $senderInitials ?: strtoupper(substr($senderDisplayStr, 0, 1)),
					'sender_shortname' => $senderInitials ?: strtoupper(substr($senderDisplayStr, 0, 1)),
					'sent_at' => now()->toISOString(),
					'created_at' => now()->toISOString(),
					'client_matter_id' => $clientMatterId,
					'attachments' => $attachmentsForResponse,
					'recipients' => [[
						'recipient_id' => $clientId,
						'recipient' => $recipientUser->full_name
					]]
				];

				// Broadcast to client only; sender uses HTTP response + addMessageToDisplay (no socket echo).
				if (class_exists('\App\Events\MessageSent')) {
					try {
						broadcast(new \App\Events\MessageSent($messageForBroadcast, $clientId));
					} catch (\Exception $e) {
						Log::warning('Failed to broadcast message to client', [
							'client_id' => $clientId,
							'message_id' => $messageId,
							'error' => $e->getMessage(),
							'broadcast_driver' => config('broadcasting.default')
						]);
					}
				}

				// Send push notification to client
				try {
					$fcmService = new FCMService();
					$matterNo = $clientMatter ? ($clientMatter->client_unique_matter_no ?? 'ID: ' . $clientMatterId) : 'ID: ' . $clientMatterId;
					
					// Prepare notification title and body (mb_substr for emoji-safe truncation)
					$notificationTitle = 'New Message';
					$notificationBody = $message ? (mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message) : ($attachmentsForResponse ? 'You have a new message with attachment(s)' : 'You have a new message');
					
					// Prepare notification data payload
					$notificationData = [
						'type' => 'chat',
						'userId' => (string)$senderId,
						'messageId' => (string)$messageId,
						'clientMatterId' => (string)$clientMatterId,
						'senderName' => $senderName,
						'matterNo' => $matterNo
					];
					
					// Send push notification to client
					try {
						$fcmService->sendToUser($clientId, $notificationTitle, $notificationBody, $notificationData);
					} catch (\Exception $e) {
						// Log error but don't fail the message sending
						Log::warning('Failed to send push notification to client', [
							'client_id' => $clientId,
							'message_id' => $messageId,
							'error' => $e->getMessage()
						]);
					}
				} catch (\Exception $e) {
					// Log error but don't fail the message sending
					Log::error('Failed to send push notification', [
						'message_id' => $messageId,
						'client_id' => $clientId,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString()
					]);
				}

				return response()->json([
					'success' => true,
					'message' => 'Message sent successfully',
					'data' => [
						'message_id' => $messageId,
						'message' => $messageForBroadcast
					]
				], 201);
			} else {
				return response()->json([
					'success' => false,
					'message' => 'Failed to send message'
				], 500);
			}

		} catch (\Illuminate\Validation\ValidationException $e) {
			return response()->json([
				'success' => false,
				'message' => 'Validation failed',
				'errors' => $e->errors()
			], 422);
		} catch (\Exception $e) {
			Log::error('Send Message Error: ' . $e->getMessage(), [
				'user_id' => Auth::guard('admin')->id(),
				'client_matter_id' => $request->input('client_matter_id'),
				'trace' => $e->getTraceAsString()
			]);

			return response()->json([
				'success' => false,
				'message' => 'Failed to send message',
				'error' => $e->getMessage()
			], 500);
		}
	}

	/**
	 * Download message attachment
	 * GET /clients/message-attachment/{id}/download
	 * Serves the file from storage (works without storage:link symlink)
	 */
	public function downloadMessageAttachment(Request $request, $id)
	{
		$admin = Auth::guard('admin')->user();
		if (!$admin) {
			abort(403, 'Unauthorized');
		}

		$att = DB::table('message_attachments')->where('id', $id)->first();
		if (!$att) {
			abort(404, 'Attachment not found');
		}

		$message = DB::table('messages')->where('id', $att->message_id)->first();
		if (!$message) {
			abort(404, 'Message not found');
		}

		$storage = app(MessageAttachmentStorageService::class);
		if (!$storage->exists($att->path)) {
			Log::warning('Message attachment file not found', ['path' => $att->path, 'id' => $id]);
			abort(404, 'File not found');
		}

		$mime = $att->mime_type ?? 'application/octet-stream';
		$filename = $att->original_filename ?? $att->filename ?? 'download';

		return response()->streamDownload(function () use ($att, $storage) {
			$content = $storage->get($att->path);
			if ($content !== null) {
				echo $content;
			}
		}, $filename, [
			'Content-Type' => $mime,
			'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
		]);
	}

	/**
	 * GET /api/client-portal/checklist-documents
	 * Returns documents for a given cp_doc_checklists entry.
	 */
	public function getChecklistDocuments(Request $request)
	{
		$checklistId   = $request->get('checklist_id');
		$clientMatterId = $request->get('client_matter_id');

		if (!$checklistId) {
			return response()->json(['success' => false, 'message' => 'checklist_id is required.'], 422);
		}

		$documents = DB::table('documents')
			->where('cp_list_id', $checklistId)
			->where('type', 'workflow_checklist')
			->when($clientMatterId, fn($q) => $q->where('client_matter_id', $clientMatterId))
			->select('id', 'file_name', 'myfile', 'cp_doc_status', 'cp_rejection_reason', 'created_at')
			->orderBy('id', 'asc')
			->get();

		return response()->json(['success' => true, 'documents' => $documents]);
	}

	/**
	 * POST /api/client-portal/delete-document
	 * Deletes a single document by ID.
	 */
	public function deleteChecklistDocument(Request $request)
	{
		$documentId = $request->get('document_id');

		if (!$documentId) {
			return response()->json(['success' => false, 'message' => 'document_id is required.'], 422);
		}

		$document = DB::table('documents')->where('id', $documentId)->first();

		if (!$document) {
			return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
		}

		$clientMatterId = $document->client_matter_id ?? null;
		$cpListId = $document->cp_list_id ?? null;
		$checklistName = 'checklist';
		if ($cpListId) {
			$cl = DB::table('cp_doc_checklists')->where('id', $cpListId)->first();
			$checklistName = $cl->cp_checklist_name ?? $checklistName;
		} elseif (!empty($document->checklist)) {
			$checklistName = $document->checklist;
		}

		// Remove the file from S3 before deleting the DB record (best-effort)
		$this->deleteS3File($document->myfile);

		$deleted = DB::table('documents')->where('id', $documentId)->delete();

		if ($deleted) {
			if ($clientMatterId) {
				$this->notifyClientAndCreateActionForDocumentStatusChangeByMatter((int) $clientMatterId, $checklistName, 'deleted');
			}
			return response()->json(['success' => true]);
		}

		return response()->json(['success' => false, 'message' => 'Failed to delete document.'], 500);
	}

	/**
	 * POST /api/client-portal/update-document-status
	 * Updates cp_doc_status (1=Approved, 2=Rejected) on a document.
	 */
	public function updateChecklistDocumentStatus(Request $request)
	{
		$documentId      = $request->get('document_id');
		$status          = (int) $request->get('status');
		$rejectionReason = $request->get('rejection_reason', '');

		if (!$documentId || !in_array($status, [1, 2])) {
			return response()->json(['success' => false, 'message' => 'document_id and valid status (1 or 2) are required.'], 422);
		}

		$data = ['cp_doc_status' => $status];
		if ($status === 2) {
			$data['cp_rejection_reason'] = $rejectionReason;
		} else {
			$data['cp_rejection_reason'] = null;
		}

		$updated = DB::table('documents')->where('id', $documentId)->update($data);

		if ($updated !== false) {
			$this->notifyClientAndCreateActionForDocumentStatusChange($documentId, $status === 1 ? 'approved' : 'rejected');
			return response()->json(['success' => true]);
		}

		return response()->json(['success' => false, 'message' => 'Document not found.'], 404);
	}

	/**
	 * Notify client (List Notifications API + badge) and create Client Portal action for document approve/reject/delete from website.
	 * Message format: "{Super admin or PERSON ASSISTING name} approved/rejected/deleted document of {ChecklistName} checklist in {MatterName}."
	 */
	private function notifyClientAndCreateActionForDocumentStatusChange(int $documentId, string $action): void
	{
		$doc = DB::table('documents')->where('id', $documentId)->first();
		if (!$doc || !$doc->client_matter_id) {
			return;
		}
		$clientMatter = DB::table('client_matters')->where('id', $doc->client_matter_id)->first();
		if (!$clientMatter || empty($clientMatter->client_id)) {
			return;
		}
		$checklistRow = $doc->cp_list_id ? DB::table('cp_doc_checklists')->where('id', $doc->cp_list_id)->first() : null;
		$checklistName = $checklistRow->cp_checklist_name ?? ($doc->checklist ?? 'checklist');
		$matterName = $clientMatter->client_unique_matter_no ?? ('ID: ' . $clientMatter->id);

		$actor = Auth::guard('admin')->user();
		$actorName = ($actor && (int) $actor->role === 1) ? 'Super admin' : ($actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'Staff');
		if ($actorName === '') {
			$actorName = 'Staff';
		}

		$notificationType = $action === 'approved' ? 'document_approved' : ($action === 'rejected' ? 'document_rejected' : 'document_deleted');
		$message = $actorName . ' ' . $action . ' document of ' . $checklistName . ' checklist in ' . $matterName . '.';

		DB::table('notifications')->insert([
			'sender_id'         => Auth::guard('admin')->id(),
			'receiver_id'       => $clientMatter->client_id,
			'module_id'         => (int) $clientMatter->id,
			'url'               => '/documents',
			'notification_type' => $notificationType,
			'message'           => $message,
			'created_at'        => now(),
			'updated_at'        => now(),
			'sender_status'     => 1,
			'receiver_status'   => 0,
			'seen'              => 0,
		]);

		try {
			$clientCount = (int) DB::table('notifications')->where('receiver_id', $clientMatter->client_id)->where('receiver_status', 0)->count();
			broadcast(new \App\Events\NotificationCountUpdated($clientMatter->client_id, $clientCount, $message, '/documents'));
		} catch (\Exception $e) {
			Log::warning('Document status change: broadcast failed', ['client_id' => $clientMatter->client_id, 'error' => $e->getMessage()]);
		}

		$clientMatterModel = ClientMatter::find($clientMatter->id);
		if ($clientMatterModel) {
			$this->createClientPortalAction($clientMatterModel, $message);
		}
	}

	/**
	 * Notify client and create Client Portal action when document is deleted (document no longer exists).
	 */
	private function notifyClientAndCreateActionForDocumentStatusChangeByMatter(int $clientMatterId, string $checklistName, string $action): void
	{
		$clientMatter = DB::table('client_matters')->where('id', $clientMatterId)->first();
		if (!$clientMatter || empty($clientMatter->client_id)) {
			return;
		}
		$matterName = $clientMatter->client_unique_matter_no ?? ('ID: ' . $clientMatter->id);
		$actor = Auth::guard('admin')->user();
		$actorName = ($actor && (int) $actor->role === 1) ? 'Super admin' : ($actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'Staff');
		if ($actorName === '') {
			$actorName = 'Staff';
		}
		$notificationType = 'document_deleted';
		$message = $actorName . ' ' . $action . ' document of ' . $checklistName . ' checklist in ' . $matterName . '.';

		DB::table('notifications')->insert([
			'sender_id'         => Auth::guard('admin')->id(),
			'receiver_id'       => $clientMatter->client_id,
			'module_id'         => (int) $clientMatter->id,
			'url'               => '/documents',
			'notification_type' => $notificationType,
			'message'           => $message,
			'created_at'        => now(),
			'updated_at'        => now(),
			'sender_status'     => 1,
			'receiver_status'   => 0,
			'seen'              => 0,
		]);

		try {
			$clientCount = (int) DB::table('notifications')->where('receiver_id', $clientMatter->client_id)->where('receiver_status', 0)->count();
			broadcast(new \App\Events\NotificationCountUpdated($clientMatter->client_id, $clientCount, $message, '/documents'));
		} catch (\Exception $e) {
			Log::warning('Document delete: broadcast failed', ['client_id' => $clientMatter->client_id, 'error' => $e->getMessage()]);
		}

		$clientMatterModel = ClientMatter::find($clientMatter->id);
		if ($clientMatterModel) {
			$this->createClientPortalAction($clientMatterModel, $message);
		}
	}

	/**
	 * Delete a file from S3 using its stored full URL.
	 *
	 * Best-effort: if the URL is not an S3 URL, or if deletion fails for any
	 * reason, the error is logged but never propagated — DB deletion always proceeds.
	 */
	private function deleteS3File(?string $myfile): void
	{
		if (!$myfile || !str_starts_with($myfile, 'http')) {
			return;
		}

		try {
			$baseUrl = rtrim(Storage::disk('s3')->url(''), '/');
			if (!$baseUrl || !str_starts_with($myfile, $baseUrl . '/')) {
				return;
			}
			$key = substr($myfile, strlen($baseUrl) + 1);
			if ($key) {
				Storage::disk('s3')->delete($key);
			}
		} catch (\Exception $e) {
			Log::warning('S3 file deletion failed: ' . $e->getMessage(), ['myfile' => $myfile]);
		}
	}

	/**
	 * Return personal or visa document categories for the Move Document modal.
	 * Called from the CRM web session (auth:admin), so Sanctum token is not needed.
	 */
	public function getDocumentCategoriesForMove(Request $request)
	{
		$type     = $request->get('type');         // 'personal' or 'visa'
		$clientId = (int) $request->get('client_id');
		$matterId = (int) $request->get('matter_id');

		try {
			if ($type === 'personal') {
				$isCompany = (bool) DB::table('admins')->where('id', $clientId)->value('is_company');
				$typeScope = $isCompany ? ['company', 'both'] : ['personal', 'both'];
				$categories = DB::table('personal_document_types')
					->where('status', 1)
					->whereIn('type', $typeScope)
					->where(function ($q) use ($clientId) {
						$q->whereNull('client_id')
						  ->orWhere('client_id', $clientId);
					})
					->orderBy('id', 'asc')
					->select('id', 'title')
					->get();

				return response()->json(['success' => true, 'categories' => $categories]);
			}

			if ($type === 'visa') {
				$categories = DB::table('visa_document_types')
					->where('status', 1)
					->where(function ($q) use ($clientId, $matterId) {
						$q->where(function ($q2) {
								$q2->whereNull('client_id')->whereNull('client_matter_id');
							})
						  ->orWhere(function ($q2) use ($clientId) {
								$q2->where('client_id', $clientId)->whereNull('client_matter_id');
							})
						  ->orWhere(function ($q2) use ($clientId, $matterId) {
								$q2->where('client_id', $clientId)->where('client_matter_id', $matterId);
							});
					})
					->orderBy('id', 'asc')
					->select('id', 'title')
					->get();

				return response()->json(['success' => true, 'categories' => $categories]);
			}

			return response()->json(['success' => false, 'message' => 'Invalid type.'], 422);

		} catch (\Exception $e) {
			Log::error('getDocumentCategoriesForMove error: ' . $e->getMessage());
			return response()->json(['success' => false, 'message' => 'Failed to load categories.'], 500);
		}
	}
}
