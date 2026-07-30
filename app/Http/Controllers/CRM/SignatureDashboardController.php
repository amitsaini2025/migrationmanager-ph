<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\ActivitiesLog;
use App\Models\Document;
use App\Models\Admin;
use App\Models\Lead;
use App\Services\SignatureService;
use App\Services\SignatureAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SignatureDashboardController extends Controller
{
    protected $signatureService;
    protected $analyticsService;

    public function __construct(SignatureService $signatureService, SignatureAnalyticsService $analyticsService)
    {
        $this->signatureService = $signatureService;
        $this->analyticsService = $analyticsService;
    }

    public function index(Request $request)
    {
        $staff = Auth::guard('admin')->user();
        
        // Signature dashboard list: same rows for every role (no allocation filter).
        // Previous: ->visible($staff) hid client-linked docs per StaffClientVisibility.
        $query = Document::with(['creator', 'signers', 'client', 'lead'])
            ->forSignatureWorkflow()
            ->notArchived()
            ->orderBy('created_at', 'desc');

        // Apply filters based on scope
        $query->when($request->has('scope'), function ($q) use ($request, $staff) {
            return match($request->scope) {
                'my_documents' => $q->forUser($staff->id),
                'team' => $q, // All documents (global access)
                'organization' => $q, // All documents (global access)
                default => $q
            };
        })
        // Apply status filters
        ->when($request->has('tab'), function ($q) use ($request) {
            return match($request->tab) {
                'pending' => $q->byStatus('sent'),
                'signed' => $q->byStatus('signed'),
                'sent_by_me' => $q->where('created_by', auth('admin')->id()),
                default => $q
            };
        });

        // Additional filters using modern Laravel syntax
        $query->when($request->filled('status'), fn($q) => $q->byStatus($request->status))
              ->when($request->filled('association'), function ($q) use ($request) {
                  return $request->association === 'associated' 
                      ? $q->associated() 
                      : $q->adhoc();
              })
              ->when($request->filled('search'), function ($q) use ($request) {
                  $search = $request->search;
                  return $q->where(function($subQ) use ($search) {
                      $subQ->where('file_name', 'like', "%{$search}%")
                           ->orWhereHas('signers', fn($sq) => $sq->where('email', 'like', "%{$search}%"));
                  });
              });

        $documents = $query->paginate(20);

        // Dashboard card counts: one aggregate query (same filters as Document scopes).
        $counts = $this->signatureDashboardCounts($staff);

        // Provide errors variable for the layout
        $errors = $request->session()->get('errors') ?? new \Illuminate\Support\MessageBag();

        // Admins (clients) + leads for attach modal — IDs must match associateWithCategory (client_id / lead_id).
        $clients = $this->signatureAttachClients();
        $leads = $this->signatureAttachLeads();

        return view('crm.signatures.dashboard', compact('documents', 'counts', 'staff', 'errors', 'clients', 'leads'));
    }

    public function create(Request $request)
    {
        $staff = Auth::guard('admin')->user();
        
        // Check authorization
        $this->authorize('create', Document::class);
        
        // Get clients and leads for association dropdown
        $clients = Admin::whereIn('type', ['client', 'lead'])->whereNull('is_deleted')->get(['id', 'first_name', 'last_name', 'email']);
        $leads = Lead::get(['id', 'first_name', 'last_name', 'email']);

        // Get active email accounts for template picker
        $emailAccounts = \App\Models\Email::where('status', true)
            ->select('id', 'email', 'display_name')
            ->orderBy('email')
            ->get();

        // Check if we're sending an existing document for signing
        $document = null;
        if ($request->has('document_id')) {
            $document = Document::with('signatureFields')->findOrFail($request->document_id);
            // For existing documents, we'll allow adding signers even if status is not signature_placed
            // Staff can still add signers and the system will handle the workflow
        }

        // Provide errors variable for the layout
        $errors = request()->session()->get('errors') ?? new \Illuminate\Support\MessageBag();

        return view('crm.signatures.create', compact('clients', 'leads', 'emailAccounts', 'staff', 'errors', 'document'));
    }

    public function store(Request $request)
    {
        // Check authorization
        $this->authorize('create', Document::class);
        
        // Check if we're using an existing document
        if ($request->has('document_id')) {
            $request->validate([
                'document_id' => 'required|integer|exists:documents,id',
                'signer_email' => 'required|email',
                'signer_name' => 'required|string|min:2|max:100',
                'email_template' => 'nullable|string',
                'email_subject' => 'nullable|string|max:255',
                'email_message' => 'nullable|string|max:1000',
                'from_email' => 'nullable|email',
                'client_matter_id' => 'nullable|integer|exists:client_matters,id',
                'selected_client_id' => 'nullable|integer|exists:admins,id',
            ]);
            
            $document = Document::findOrFail($request->document_id);
            
            // Check for duplicate signer
            $existingSigner = $document->signers()->where('email', $request->signer_email)->first();
            if ($existingSigner && $existingSigner->status === 'pending') {
                return redirect()->back()->withErrors(['signer_email' => 'A signing link has already been sent to this email address.']);
            }

            // Create new signer
            $signer = $document->signers()->create([
                'email' => $request->signer_email,
                'name' => $request->signer_name,
                'token' => Str::random(64),
                'status' => 'pending',
                'reminder_count' => 0, // PostgreSQL NOT NULL constraint - must set this field
                'email_template' => $request->email_template ?? 'emails.signature.send',
                'email_subject' => $request->email_subject,
                'email_message' => $request->email_message,
                'from_email' => $request->from_email,
            ]);
            
            // Associate document with matter if specified, or with lead/client if no matter
            if ($request->has('client_matter_id') && $request->client_matter_id) {
                // Get the matter details
                $matter = \DB::table('client_matters')
                    ->where('id', $request->client_matter_id)
                    ->first();
                    
                if ($matter) {
                    // Associate document with the client
                    $client = Admin::find($matter->client_id);
                    if ($client) {
                        $document->update([
                            'client_id' => $client->id,
                            'lead_id' => null,
                        ]);
                        
                        // Create a note about the matter association
                        \App\Models\SignatureActivity::create([
                            'document_id' => $document->id,
                            'created_by' => auth('admin')->id(),
                            'action_type' => 'associated',
                            'note' => "Document associated with matter: {$matter->client_unique_matter_no}",
                            'metadata' => [
                                'matter_id' => $matter->id,
                                'matter_number' => $matter->client_unique_matter_no,
                                'client_id' => $client->id
                            ]
                        ]);
                    }
                }
            } elseif ($request->has('selected_client_id') && $request->selected_client_id) {
                // Associate document with selected client/lead (no specific matter)
                $entity = Admin::find($request->selected_client_id);
                if ($entity) {
                    $entityType = ($entity->type === 'lead') ? 'lead' : 'client';
                    
                    $document->update(
                        $entityType === 'client'
                            ? ['client_id' => $entity->id, 'lead_id' => null]
                            : ['lead_id' => $entity->id, 'client_id' => null]
                    );
                    
                    // Create a note about the association
                    $folderName = ($entityType === 'lead') ? 'personal documents' : 'general documents';
                    \App\Models\SignatureActivity::create([
                        'document_id' => $document->id,
                        'created_by' => auth('admin')->id(),
                        'action_type' => 'associated',
                        'note' => "Document associated with {$entityType}: {$entity->first_name} {$entity->last_name} (folder: {$folderName})",
                        'metadata' => [
                            'entity_id' => $entity->id,
                            'entity_type' => $entityType,
                            'folder' => $folderName
                        ]
                    ]);
                }
            }

            // Do NOT automatically send email or update status
            // Staff must manually click "Send for Signature" when ready
            
            // Build success message
            $successMessage = "Signer added successfully: {$signer->email}. Click 'Send for Signature' when ready to send the signing link.";
            if ($request->has('client_matter_id') && $request->client_matter_id) {
                $matter = \DB::table('client_matters')->where('id', $request->client_matter_id)->first();
                if ($matter) {
                    $successMessage .= " Document associated with matter: {$matter->client_unique_matter_no}";
                }
            } elseif ($request->has('selected_client_id') && $request->selected_client_id) {
                $entity = Admin::find($request->selected_client_id);
                if ($entity) {
                    $entityType = ($entity->type === 'lead') ? 'lead' : 'client';
                    $folderName = ($entityType === 'lead') ? 'personal documents' : 'general documents';
                    $successMessage .= " Document associated with {$entityType}: {$entity->first_name} {$entity->last_name} (folder: {$folderName})";
                }
            }
            
            return redirect()->route('signatures.show', $document->id)
                ->with('success', $successMessage);
        } else {
            $request->validate([
                'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
                'signer_email' => 'required|email',
                'signer_name' => 'required|string|min:2|max:100',
                'association_type' => 'nullable|string|in:client,lead',
                'association_id' => 'nullable|integer',
                'client_matter_id' => 'nullable|integer',
            ]);
            
            // Handle file upload for new documents
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents', $fileName, 'public');

            $storedExtension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf'));

            // Create document
            $document = Document::create([
                'file_name' => $fileName,
                'filetype' => $storedExtension,
                'myfile' => $filePath,
                'status' => 'draft',
                'client_id' => Auth::guard('admin')->id(),
            ]);
        }

        $staff = Auth::guard('admin')->user();

        // Set association if provided
        if ($request->association_type && $request->association_id) {
            $this->signatureService->associate(
                $document, 
                $request->association_type, 
                $request->association_id
            );
        }

        // Store signer information in session for later use
        session([
            'pending_document_signer' => [
                'email' => $request->signer_email,
                'name' => $request->signer_name,
                'email_subject' => $request->email_subject,
                'email_message' => $request->email_message,
                'email_template' => $request->email_template,
                'from_email' => $request->from_email,
            ]
        ]);

        // Redirect to signature placement page
        return redirect()->route('documents.edit', $document->id)
            ->with('success', 'Document uploaded! Now place signature fields on the document.');
    }

    public function show($id)
    {
        $document = Document::with(['creator', 'signers', 'lead', 'signatureFields', 'signatureActivities.creator'])
            ->findOrFail($id);

        // Check authorization using policy
        $this->authorize('view', $document);

        // Get active email accounts for template picker
        $emailAccounts = \App\Models\Email::where('status', true)
            ->select('id', 'email', 'display_name')
            ->orderBy('email')
            ->get();

        // Admins (clients) + leads for attach — same source as signature dashboard.
        $clients = $this->signatureAttachClients();
        $leads = $this->signatureAttachLeads();

        // Provide errors variable for the layout
        $errors = request()->session()->get('errors') ?? new \Illuminate\Support\MessageBag();

        return view('crm.signatures.show', compact('document', 'errors', 'emailAccounts', 'clients', 'leads'));
    }

    public function sendReminder(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        
        // Check authorization
        $this->authorize('sendReminder', $document);
        
        $signerId = $request->signer_id;
        $signer = $document->signers()->findOrFail($signerId);

        // Use service to send reminder
        $success = $this->signatureService->remind($signer);

        if ($success && $document->client_id) {
            ActivitiesLog::create([
                'client_id' => $document->client_id,
                'created_by' => Auth::guard('admin')->id(),
                'subject' => 'sent reminder for document signature',
                'description' => '<ul><li><strong>Document:</strong> ' . htmlspecialchars($document->checklist ?? $document->file_name ?? 'Document') . '</li><li><strong>Reminded:</strong> ' . htmlspecialchars($signer->name . ' (' . $signer->email . ')') . '</li></ul>',
                'activity_type' => 'signature',
                'task_status' => 0,
                'pin' => 0,
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            if ($success) {
                $signer->refresh();

                return response()->json([
                    'success' => true,
                    'message' => 'Reminder sent successfully!',
                    'reminder_count' => (int) $signer->reminder_count,
                    'last_reminder_sent_at' => $signer->last_reminder_sent_at
                        ? $signer->last_reminder_sent_at->format('M d, Y g:i A')
                        : null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminder. Please check limits and try again.',
            ], 422);
        }

        if ($success) {
            return back()->with('success', 'Reminder sent successfully!');
        } else {
            return back()->with('error', 'Failed to send reminder. Please check limits and try again.');
        }
    }

    public function cancelSignature(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        
        // Check authorization
        $this->authorize('sendReminder', $document);
        
        // Validate request
        $request->validate([
            'signer_id' => 'required|integer|exists:signers,id'
        ]);
        
        $signerId = $request->signer_id;
        $signer = $document->signers()->findOrFail($signerId);
        
        // Verify signer belongs to this document
        if ($signer->document_id !== $document->id) {
            return back()->with('error', 'Invalid signer for this document.');
        }
        
        // Check if already signed - cannot cancel signed documents
        if ($signer->status === 'signed') {
            return back()->with('error', 'Cannot cancel signature. Document has already been signed.');
        }
        
        // Check if already cancelled
        if ($signer->status === 'cancelled') {
            return back()->with('info', 'Signature has already been cancelled.');
        }
        
        // Only allow cancellation of pending signatures
        if ($signer->status !== 'pending') {
            return back()->with('error', 'Can only cancel pending signatures.');
        }
        
        try {
            // Update signer status to cancelled
            $signer->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);
            
            // If no more pending signers, reset document status (so action bar hides for visa docs)
            $pendingCount = $document->signers()->where('status', 'pending')->count();
            if ($pendingCount === 0) {
                $document->update([
                    'status' => null,
                    'signature_doc_link' => null,
                ]);
            }
            
            if ($document->client_id) {
                ActivitiesLog::create([
                    'client_id' => $document->client_id,
                    'created_by' => Auth::guard('admin')->id(),
                    'subject' => 'cancelled signature request',
                    'description' => '<ul><li><strong>Document:</strong> ' . htmlspecialchars($document->checklist ?? $document->file_name ?? 'Document') . '</li><li><strong>Cancelled for:</strong> ' . htmlspecialchars($signer->name . ' (' . $signer->email . ')') . '</li></ul>',
                    'activity_type' => 'signature',
                    'task_status' => 0,
                    'pin' => 0,
                ]);
            }
            
            // Create activity log entry
            \App\Models\SignatureActivity::create([
                'document_id' => $document->id,
                'created_by' => auth('admin')->id(),
                'note' => "Signature cancelled for {$signer->name} ({$signer->email})",
                'action_type' => 'signature_cancelled',
                'metadata' => [
                    'signer_id' => $signer->id,
                    'signer_name' => $signer->name,
                    'signer_email' => $signer->email,
                    'cancelled_at' => now()->toIso8601String()
                ]
            ]);
            
            return back()->with('success', 'Signature cancelled successfully. The signer will no longer be able to sign this document.');
        } catch (\Exception $e) {
            \Log::error('Error cancelling signature', [
                'document_id' => $document->id,
                'signer_id' => $signerId,
                'error' => $e->getMessage()
            ]);
            return back()->with('error', 'An error occurred while cancelling the signature. Please try again.');
        }
    }

    public function sendForSignature(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        
        // Check authorization
        $this->authorize('sendReminder', $document);
        
        // Check if document has signers
        $pendingSigners = $document->signers()->where('status', 'pending')->get();

        // Self-heal: visa/nomination action-bar docs may be "placed" without a signer
        // (e.g. nomination docs before the placement flow created one). Create from client email.
        if ($pendingSigners->isEmpty()
            && in_array($document->doc_type, ['visa', 'nomination'], true)
            && $document->status === 'placed'
            && !empty($document->client_id)
        ) {
            $client = $document->client;
            $recipient = $client?->resolveSigningRecipient();
            if (!$recipient) {
                $message = 'Client email is required to send for signature. Please add a real email on the company/client profile.';
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }
                return back()->with('error', $message);
            }
            $signer = $document->signers()->create([
                'email' => $recipient['email'],
                'name' => $recipient['name'],
                'token' => \Illuminate\Support\Str::random(64),
                'status' => 'pending',
                'reminder_count' => 0,
            ]);
            $signingUrl = url("/sign/{$document->id}/{$signer->token}");
            $document->update([
                'signature_doc_link' => json_encode([
                    ['email' => $signer->email, 'name' => $signer->name, 'url' => $signingUrl],
                ]),
            ]);
            $pendingSigners = $document->signers()->where('status', 'pending')->get();
        }
        
        if ($pendingSigners->isEmpty()) {
            $message = 'No pending signers found for this document.';
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        // Upgrade placeholder (@lead.internal) signers and strip trailing punctuation on emails
        $recipient = $document->client?->resolveSigningRecipient();
        $signerEmailFixed = false;
        foreach ($pendingSigners as $signer) {
            $updates = [];
            if (Admin::isInternalPlaceholderEmail($signer->email)) {
                if (!$recipient) {
                    $message = 'Cannot send signature email: company has a placeholder (@lead.internal) address. Please add a real email on the company/contact profile.';
                    if ($request->ajax() || $request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => $message], 422);
                    }
                    return back()->with('error', $message);
                }
                $updates['email'] = $recipient['email'];
                $updates['name'] = $recipient['name'] ?: $signer->name;
            } else {
                $clean = Admin::sanitizeEmailAddress($signer->email);
                if ($clean !== '' && $clean !== $signer->email) {
                    $updates['email'] = $clean;
                }
            }
            if ($updates !== []) {
                $signer->update($updates);
                $signer->refresh();
                $signerEmailFixed = true;
            }
        }
        if ($signerEmailFixed) {
            $signatureLinks = $pendingSigners->map(function ($s) use ($document) {
                return [
                    'email' => $s->email,
                    'name' => $s->name,
                    'url' => url("/sign/{$document->id}/{$s->token}"),
                ];
            })->values()->all();
            $document->update(['signature_doc_link' => json_encode($signatureLinks)]);
        }
        
        // Allow sending for any status except 'signed', 'void', or 'archived'
        $blockedStatuses = ['signed', 'void', 'archived'];
        if (in_array($document->status, $blockedStatuses)) {
            $message = "Document cannot be sent for signature because it is {$document->status}.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }
        
        $emailsSent = 0;
        $errors = [];
        
        foreach ($pendingSigners as $signer) {
            try {
                // Generate signing URL
                $signingUrl = url("/sign/{$document->id}/{$signer->token}");
                
                // Use the signer's stored email template
                $template = $signer->email_template ?? 'emails.signature.send';
                
                // Use the signer's stored email settings, or fallback to defaults
                $subject = $signer->email_subject ?? 'Document Signature Request from Bansal Migration';
                $message = $signer->email_message ?? "Please review and sign the attached document.";
                
                // Prepare template data
                $templateData = [
                    'signerName' => $signer->name,
                    'documentTitle' => $document->display_title,
                    'signingUrl' => $signingUrl,
                    'emailMessage' => $message,
                    'documentType' => 'document',
                    'dueDate' => null,
                ];

                $fromAddress = $signer->from_email ?: config('mail.from.address');
                $fromName = config('mail.from.name', 'Bansal Migration');
                $emailSignature = '';
                if (!empty($fromAddress)) {
                    $emailAccount = \App\Models\Email::where('status', true)->where('email', $fromAddress)->first();
                    if ($emailAccount) {
                        $fromName = $emailAccount->display_name ?: $fromName;
                        $emailSignature = $emailAccount->email_signature ?: '';
                    }
                }
                $templateData['emailSignature'] = $emailSignature;

                try {
                    Mail::mailer('sendgrid')->send($template, $templateData, function ($mail) use ($signer, $subject, $fromAddress, $fromName) {
                        $mail->to($signer->email, $signer->name)
                            ->subject($subject)
                            ->from($fromAddress, $fromName);
                    });

                    // Create activity note for successful email
                    \App\Models\SignatureActivity::create([
                        'document_id' => $document->id,
                        'created_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id() ?? 1,
                        'action_type' => 'email_sent',
                        'note' => "Email sent successfully to {$signer->name} ({$signer->email})",
                        'metadata' => [
                            'signer_email' => $signer->email,
                            'signer_name' => $signer->name,
                            'subject' => $subject,
                            'status' => 'sent_via_sendgrid',
                        ]
                    ]);
                } catch (\Exception $emailException) {
                    // Create activity note for failed email
                    \App\Models\SignatureActivity::create([
                        'document_id' => $document->id,
                        'created_by' => \Illuminate\Support\Facades\Auth::guard('admin')->id() ?? 1,
                        'action_type' => 'email_failed',
                        'note' => "Failed to send email to {$signer->name} ({$signer->email}): {$emailException->getMessage()}",
                        'metadata' => [
                            'signer_email' => $signer->email,
                            'signer_name' => $signer->name,
                            'error' => $emailException->getMessage(),
                        ]
                    ]);
                    throw $emailException;
                }
                
                $emailsSent++;
                
            } catch (\Exception $e) {
                $errors[] = "Failed to send email to {$signer->email}: " . $e->getMessage();
            }
        }
        
        // Update document status
        $document->update(['status' => 'sent']);
        
        if ($emailsSent > 0 && $document->client_id) {
            $recipients = $pendingSigners->map(fn($s) => $s->name . ' (' . $s->email . ')')->implode(', ');
            ActivitiesLog::create([
                'client_id' => $document->client_id,
                'created_by' => Auth::guard('admin')->id(),
                'subject' => 'sent document for signature',
                'description' => '<ul><li><strong>Document:</strong> ' . htmlspecialchars($document->checklist ?? $document->file_name ?? 'Document') . '</li><li><strong>Sent to:</strong> ' . htmlspecialchars($recipients) . '</li></ul>',
                'activity_type' => 'signature',
                'task_status' => 0,
                'pin' => 0,
            ]);
        }
        
        if ($emailsSent > 0) {
            $message = "Document sent for signature! {$emailsSent} signing link(s) sent successfully.";
            if (!empty($errors)) {
                $message .= " However, some emails failed: " . implode(', ', $errors);
            }
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $message]);
            }
            return back()->with('success', $message);
        }

        $message = 'Failed to send any emails. Errors: ' . implode(', ', $errors);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }
        return back()->with('error', $message);
    }

    public function copyLink($id)
    {
        $document = Document::findOrFail($id);
        
        // Check authorization
        $this->authorize('view', $document);
        
        $signer = $document->signers()->first();
        
        if (!$signer) {
            return back()->with('error', 'No signer found for this document.');
        }

        $signingUrl = url("/sign/{$document->id}/{$signer->token}");
        
        return back()->with('success', "Signing link copied to clipboard: {$signingUrl}");
    }

    public function suggestAssociation(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $matches = [];
        
        // Find all clients and leads with this email (both are in admins table with type = 'client' or 'lead')
        $entities = Admin::where('email', $request->email)
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->get();
            
        foreach ($entities as $entity) {
            // Determine if it's a client or lead based on type field
            $entityType = ($entity->type === 'lead') ? 'lead' : 'client';
            
            if ($entityType === 'client') {
                // Get client's matters
                $matters = \DB::table('client_matters')
                    ->where('client_id', $entity->id)
                    ->join('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                    ->select(
                        'client_matters.id',
                        'client_matters.client_unique_matter_no',
                        'matters.title as matter_title',
                        'client_matters.matter_status'
                    )
                    ->orderBy('client_matters.created_at', 'desc')
                    ->get()
                    ->map(function($matter) {
                        return [
                            'id' => $matter->id,
                            'label' => $matter->client_unique_matter_no . ' - ' . $matter->matter_title,
                            'status' => $matter->matter_status
                        ];
                    })
                    ->toArray();
            } else {
                // Leads don't have matters
                $matters = [];
            }

            $matches[] = [
                'type' => $entityType,
                'id' => $entity->id,
                'name' => trim("{$entity->first_name} {$entity->last_name}"),
                'email' => $entity->email,
                'matters' => $matters,
                'has_matters' => count($matters) > 0
            ];
        }

        return response()->json([
            'success' => true,
            'matches' => $matches,
            'match' => count($matches) === 1 ? $matches[0] : null // For backward compatibility
        ]);
    }

    public function getClientMatters($id)
    {
        try {
            $matters = \DB::table('client_matters')
                ->where('client_id', $id)
                ->join('matters', 'client_matters.sel_matter_id', '=', 'matters.id')
                ->select(
                    'client_matters.id',
                    'client_matters.client_unique_matter_no',
                    'matters.title as matter_title',
                    'client_matters.matter_status'
                )
                ->orderBy('client_matters.created_at', 'desc')
                ->get()
                ->map(function($matter) {
                    return [
                        'id' => $matter->id,
                        'client_unique_matter_no' => $matter->client_unique_matter_no,
                        'matter_title' => $matter->matter_title,
                        'matter_status' => $matter->matter_status
                    ];
                });

            return response()->json([
                'success' => true,
                'matters' => $matters
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading matters'
            ], 500);
        }
    }

    /**
     * Document category folders for attach modal (PersonalDocumentType / VisaDocumentType ids).
     */
    public function getDocumentCategories(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer',
            'doc_category' => 'required|string|in:visa,personal',
            'matter_id' => 'nullable|integer',
        ]);

        $entityId = (int) $request->entity_id;
        $docCategory = $request->doc_category;
        $matterId = $request->filled('matter_id') ? (int) $request->matter_id : null;

        try {
            if ($docCategory === 'personal') {
                $categories = \App\Models\PersonalDocumentType::query()
                    ->select('id', 'title')
                    ->where('status', 1)
                    ->where(function ($query) use ($entityId) {
                        $query->whereNull('client_id')->orWhere('client_id', $entityId);
                    })
                    ->whereIn('type', ['personal', 'both'])
                    ->orderBy('title')
                    ->get()
                    ->map(fn ($c) => ['id' => (string) $c->id, 'title' => $c->title]);
            } else {
                $categoriesQuery = \App\Models\VisaDocumentType::query()
                    ->select('id', 'title')
                    ->where('status', 1)
                    ->where(function ($query) use ($entityId, $matterId) {
                        $query->where(function ($q) {
                            $q->whereNull('client_id')->whereNull('client_matter_id');
                        })
                            ->orWhere(function ($q) use ($entityId) {
                                $q->where('client_id', $entityId)->whereNull('client_matter_id');
                            });
                        if ($matterId) {
                            $query->orWhere(function ($q) use ($entityId, $matterId) {
                                $q->where('client_id', $entityId)->where('client_matter_id', $matterId);
                            });
                        }
                    })
                    ->orderBy('title');

                $categories = $categoriesQuery->get()
                    ->map(fn ($c) => ['id' => (string) $c->id, 'title' => $c->title]);
            }

            return response()->json([
                'success' => true,
                'categories' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading categories',
            ], 500);
        }
    }

    /**
     * Active document checklists for the selected attach category.
     */
    public function getDocumentChecklists(Request $request)
    {
        $request->validate([
            'doc_category' => 'required|string|in:visa,personal',
        ]);

        $docType = $request->doc_category === 'personal' ? 1 : 2;

        $checklists = \App\Models\DocumentChecklist::query()
            ->select('id', 'name')
            ->where('status', 1)
            ->where('doc_type', $docType)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'checklists' => $checklists,
        ]);
    }

    /**
     * Associate a document with a client or lead (post-signing)
     */
    public function associate(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        
        // Check authorization
        $this->authorize('update', $document);

        $request->validate([
            'entity_type' => 'required|string|in:client,lead',
            'entity_id' => 'required|integer',
            'matter_id' => 'nullable|integer|exists:client_matters,id',
            'doc_category' => 'required|string|in:visa,personal',
            'folder_name' => 'required|string|max:64',
            'checklist' => [
                'required',
                'string',
                'max:255',
                Rule::exists('portal_document_checklists', 'name')
                    ->where('status', 1)
                    ->where('doc_type', $request->doc_category === 'personal' ? 1 : 2),
            ],
            'note' => 'nullable|string|max:500',
        ]);

        $success = $this->signatureService->associateWithCategory(
            $document,
            $request->entity_type,
            $request->entity_id,
            $request->matter_id,
            $request->doc_category,
            $request->note,
            $request->folder_name,
            $request->checklist
        );

        if ($success) {
            $message = 'Document successfully attached to ' . $request->entity_type . '!';
            if ($request->matter_id) {
                $matter = \App\Models\ClientMatter::find($request->matter_id);
                $message .= ' (Matter: ' . ($matter->client_unique_matter_no ?? '#' . $matter->id) . ')';
            }
            return back()->with('success', $message);
        } else {
            return back()->with('error', 'Failed to attach document. Please try again.');
        }
    }


    /**
     * Detach a document from its current association
     */
    public function detach(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        
        // Check authorization (admin only)
        $this->authorize('update', $document);

        $staff = Auth::guard('admin')->user();
        if ($staff->role !== 1) {
            return back()->with('error', 'Only administrators can detach documents.');
        }

        $request->validate([
            'reason' => 'nullable|string|max:500'
        ]);

        $success = $this->signatureService->detach(
            $document,
            $request->reason
        );

        if ($success) {
            return back()->with('success', 'Document successfully detached!');
        } else {
            return back()->with('error', 'Failed to detach document. Please try again.');
        }
    }


    /**
     * Bulk archive documents
     */
    public function bulkArchive(Request $request)
    {
        // Decode JSON if necessary
        $ids = is_string($request->ids) ? json_decode($request->ids, true) : $request->ids;
        
        $request->merge(['ids' => $ids]);
        $request->validate(['ids' => 'required|array|min:1']);
        
        try {
            $count = Document::whereIn('id', $ids)
                ->notArchived()
                ->update(['status' => 'archived']);
            
            return back()->with('success', "Successfully archived {$count} document(s)");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to archive documents: ' . $e->getMessage());
        }
    }

    /**
     * Bulk void documents
     */
    public function bulkVoid(Request $request)
    {
        // Decode JSON if necessary
        $ids = is_string($request->ids) ? json_decode($request->ids, true) : $request->ids;
        
        $request->merge(['ids' => $ids]);
        $request->validate([
            'ids' => 'required|array|min:1',
            'reason' => 'nullable|string|max:500'
        ]);
        
        try {
            $staff = Auth::guard('admin')->user();
            $documents = Document::whereIn('id', $ids)->get();
            $count = 0;
            $skipped = 0;
            
            foreach ($documents as $doc) {
                // Check authorization using Gate instead of policy directly
                if ($staff->can('void', $doc)) {
                    if ($this->signatureService->void($doc, $request->reason)) {
                        $count++;
                    }
                } else {
                    $skipped++;
                }
            }
            
            $message = "Successfully voided {$count} document(s)";
            if ($skipped > 0) {
                $message .= " ({$skipped} skipped due to permissions)";
            }
            
            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to void documents: ' . $e->getMessage());
        }
    }

    /**
     * Bulk resend reminders
     */
    public function bulkResend(Request $request)
    {
        // Decode JSON if necessary
        $ids = is_string($request->ids) ? json_decode($request->ids, true) : $request->ids;
        
        $request->merge(['ids' => $ids]);
        $request->validate(['ids' => 'required|array|min:1']);
        
        try {
            $documents = Document::with('signers')->whereIn('id', $ids)->get();
            $sent = 0;
            $skipped = 0;
            
            foreach ($documents as $doc) {
                foreach ($doc->signers as $signer) {
                    if ($signer->status === 'pending') {
                        if ($this->signatureService->remind($signer)) {
                            $sent++;
                        } else {
                            $skipped++;
                        }
                    }
                }
            }
            
            $message = "Sent {$sent} reminder(s)";
            if ($skipped > 0) {
                $message .= " ({$skipped} skipped due to limits)";
            }
            
            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send reminders: ' . $e->getMessage());
        }
    }


    /**
     * Preview email template before sending
     */
    public function previewEmail(Request $request)
    {
        $request->validate([
            'template' => 'required|string',
            'signer_name' => 'required|string',
            'document_title' => 'nullable|string',
            'message' => 'nullable|string',
        ]);

        $template = $request->template;
        $documentTitle = $request->document_title ?: 'Your Document';
        
        // Mock data for preview
        $templateData = [
            'signerName' => $request->signer_name,
            'documentTitle' => $documentTitle,
            'signingUrl' => url('/sign/preview/token-preview'),
            'message' => $request->message ?: 'Please review and sign the attached document.',
            'documentType' => 'document',
            'dueDate' => now()->addDays(7)->format('F j, Y'),
            'reminderNumber' => 1, // For reminder template
        ];

        try {
            $html = view($template, $templateData)->render();
            
            return response()->json([
                'success' => true,
                'html' => $html
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to render template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Signature dashboard stat cards: one aggregate query using the same filters as Document scopes.
     */
    private function signatureDashboardCounts($staff): array
    {
        $row = Document::query()
            ->forSignatureWorkflow()
            ->notArchived()
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS c_total, ' .
                'COALESCE(SUM(CASE WHEN documents.status = ? THEN 1 ELSE 0 END), 0) AS c_pending, ' .
                'COALESCE(SUM(CASE WHEN documents.status = ? THEN 1 ELSE 0 END), 0) AS c_signed, ' .
                'COALESCE(SUM(CASE WHEN documents.created_by = ? THEN 1 ELSE 0 END), 0) AS c_sent_by_me',
                ['sent', 'signed', $staff->id]
            )
            ->first();

        $total = (int) ($row->c_total ?? 0);
        $pending = (int) ($row->c_pending ?? 0);
        $signed = (int) ($row->c_signed ?? 0);
        $sentByMe = (int) ($row->c_sent_by_me ?? 0);

        return [
            'sent_by_me' => $sentByMe,
            'visible_to_me' => $total,
            'pending' => $pending,
            'signed' => $signed,
            'overdue' => 0,
            'all' => $total,
            'all_pending' => $pending,
        ];
    }

    /**
     * CRM clients (admins.type = client) for attach modal — IDs match documents.client_id / associate().
     */
    private function signatureAttachClients()
    {
        return Admin::query()
            ->where('type', 'client')
            ->whereNull('is_deleted')
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    private function signatureAttachLeads()
    {
        return Lead::query()
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}