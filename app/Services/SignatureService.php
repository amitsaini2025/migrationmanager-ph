<?php

namespace App\Services;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Email;
use App\Models\Lead;
use App\Models\SignatureActivity;
use App\Models\Signer;
use App\Support\DocumentStoredFilename;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureService
{
    protected EmailConfigService $emailConfigService;

    protected SystemEmailLogService $systemEmailLog;

    public function __construct(EmailConfigService $emailConfigService, SystemEmailLogService $systemEmailLog)
    {
        $this->emailConfigService = $emailConfigService;
        $this->systemEmailLog = $systemEmailLog;
    }

    /**
     * Send a document for signature
     *
     * @param  array  $signers  Array of ['email' => '', 'name' => '']
     * @param  array  $options  Additional options (subject, message, from_email, template, attachments)
     */
    public function send(Document $document, array $signers, array $options = []): bool
    {
        try {
            $createdSigners = [];

            foreach ($signers as $signerData) {
                $signer = $document->signers()->create([
                    'email' => $signerData['email'],
                    'name' => $signerData['name'],
                    'token' => Str::random(64),
                    'status' => 'pending',
                ]);

                $createdSigners[] = $signer;
            }

            // Update document status and tracking
            $document->update([
                'status' => 'sent',
            ]);

            // Send emails to all signers
            foreach ($createdSigners as $signer) {
                $this->sendSigningEmail($document, $signer, $options);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send document for signature', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve sender details from selected sender or signature defaults.
     * Preferred from_email (UI / signer) always wins; otherwise use
     * mail.noreply.address so CRM global from stays unchanged.
     */
    protected function resolveFrom(?string $preferredFrom = null): array
    {
        $defaultAddress = (string) config('mail.noreply.address', 'noreply@bansalimmigration.com.au');
        $defaultName = (string) config('mail.from.name', 'Bansal Migration');

        $fromAddress = $preferredFrom ?: $defaultAddress;
        $fromName = $defaultName;
        $signature = '';

        if (! empty($fromAddress)) {
            $emailAccount = Email::where('status', true)
                ->where('email', $fromAddress)
                ->first();
            if ($emailAccount) {
                $fromName = $emailAccount->display_name ?: $fromName;
                $signature = $emailAccount->email_signature ?: '';
            }
        }

        if (empty($signature)) {
            $default = $this->emailConfigService->getDefaultAccount();
            $signature = $default['email_signature'] ?? '';
            $fromAddress = $fromAddress ?: ($default['from_address'] ?? $defaultAddress);
            $fromName = $fromName ?: ($default['from_name'] ?? $defaultName);
        }

        return [
            'from_address' => $fromAddress,
            'from_name' => $fromName,
            'email_signature' => $signature,
        ];
    }

    /**
     * Send signing email to a signer using SendGrid mailer.
     */
    protected function sendSigningEmail(Document $document, Signer $signer, array $options = []): void
    {
        $systemEmailLog = $this->systemEmailLog;
        $log = null;

        try {
            $signingUrl = url("/sign/{$document->id}/{$signer->token}");

            // Determine template based on document type or options
            $template = $options['template'] ?? 'emails.signature.send';
            if (($options['document_type'] ?? null) === 'agreement') {
                $template = 'emails.signature.send_agreement';
            }

            $subject = $options['subject'] ?? 'Document Signature Request from Bansal Migration';
            $message = $options['message'] ?? 'Please review and sign the attached document.';
            $from = $this->resolveFrom($options['from_email'] ?? $signer->from_email ?? null);

            // Prepare template data
            $templateData = [
                'signerName' => $signer->name,
                'documentTitle' => $document->display_title,
                'signingUrl' => $signingUrl,
                'emailMessage' => $message,
                'documentType' => $options['document_type'] ?? 'document',
                'dueDate' => ($options['due_at'] ?? null)?->format('F j, Y'),
                'emailSignature' => $from['email_signature'] ?? '',
            ];

            // Prepare attachments
            $attachments = [];
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                $attachments = $options['attachments'];
            }

            $systemEmailLog = $this->systemEmailLog;
            $log = $systemEmailLog->createPending([
                'category' => 'signature',
                'from_mail' => $from['from_address'],
                'to_mail' => $signer->email,
                'subject' => $subject,
                'message' => $message,
                'client_id' => $document->client_id,
                'user_id' => Auth::guard('admin')->id(),
            ]);

            Mail::mailer('sendgrid')->send($template, $templateData, function (Message $mail) use ($signer, $subject, $from, $attachments, $log, $systemEmailLog) {
                $mail->to($signer->email, $signer->name)
                    ->subject($subject)
                    ->from($from['from_address'], $from['from_name']);

                foreach ($attachments as $attachment) {
                    if (is_string($attachment) && file_exists($attachment)) {
                        $mail->attach($attachment);

                        continue;
                    }

                    if (is_array($attachment)) {
                        $path = $attachment['path'] ?? null;
                        if ($path && file_exists($path)) {
                            $options = [];
                            if (! empty($attachment['name'])) {
                                $options['as'] = $attachment['name'];
                            }
                            if (! empty($attachment['mime'])) {
                                $options['mime'] = $attachment['mime'];
                            }
                            $mail->attach($path, $options);
                        }
                    }
                }

                $systemEmailLog->attachTrackingToMailMessage($mail, $log->id);
            });

            // Create activity note for successful email delivery
            SignatureActivity::create([
                'document_id' => $document->id,
                'created_by' => Auth::guard('admin')->id() ?? 1,
                'action_type' => 'email_sent',
                'note' => "Email sent successfully to {$signer->name} ({$signer->email})",
                'metadata' => [
                    'signer_id' => $signer->id,
                    'signer_email' => $signer->email,
                    'signer_name' => $signer->name,
                    'subject' => $subject,
                    'status' => 'sent_via_sendgrid',
                    'email_account' => $from['from_address'],
                ],
            ]);

            Log::info('Signing email sent via SendGrid mailer', [
                'document_id' => $document->id,
                'signer_email' => $signer->email,
                'template' => $template,
                'email_account' => $from['from_address'],
            ]);
        } catch (\Exception $e) {
            if ($log !== null) {
                $systemEmailLog->markSendFailed($log, $e->getMessage());
            }

            // Create activity note for failed email delivery
            try {
                SignatureActivity::create([
                    'document_id' => $document->id,
                    'created_by' => Auth::guard('admin')->id() ?? 1,
                    'action_type' => 'email_failed',
                    'note' => "Failed to send email to {$signer->name} ({$signer->email}): {$e->getMessage()}",
                    'metadata' => [
                        'signer_id' => $signer->id,
                        'signer_email' => $signer->email,
                        'signer_name' => $signer->name,
                        'error' => $e->getMessage(),
                        'error_trace' => substr($e->getTraceAsString(), 0, 500), // Limit trace length
                    ],
                ]);
            } catch (\Exception $noteException) {
                // If note creation fails, just log it
                Log::warning('Failed to create email failure note', [
                    'document_id' => $document->id,
                    'error' => $noteException->getMessage(),
                ]);
            }

            Log::error('Failed to send signing email', [
                'document_id' => $document->id,
                'signer_id' => $signer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Send reminder to a signer using SendGrid mailer.
     */
    public function remind(Signer $signer, array $options = []): bool
    {
        $systemEmailLog = $this->systemEmailLog;
        $log = null;

        try {
            // Check if signature is cancelled - cannot send reminders to cancelled signers
            if ($signer->status === 'cancelled') {
                throw new \Exception('Cannot send reminder. Signature has been cancelled.');
            }

            // Check if already signed - no need to send reminders
            if ($signer->status === 'signed') {
                throw new \Exception('Cannot send reminder. Document has already been signed.');
            }

            // Check reminder limits
            if ($signer->reminder_count >= 3) {
                throw new \Exception('Maximum reminders already sent');
            }

            $document = $signer->document;
            $signingUrl = url("/sign/{$document->id}/{$signer->token}");
            $from = $this->resolveFrom($options['from_email'] ?? $signer->from_email ?? null);

            $templateData = [
                'signerName' => $signer->name,
                'documentTitle' => $document->display_title,
                'signingUrl' => $signingUrl,
                'reminderNumber' => $signer->reminder_count + 1,
                'dueDate' => null,
                'emailSignature' => $from['email_signature'] ?? '',
            ];

            $systemEmailLog = $this->systemEmailLog;
            $log = $systemEmailLog->createPending([
                'category' => 'signature_reminder',
                'from_mail' => $from['from_address'],
                'to_mail' => $signer->email,
                'subject' => 'Reminder: Please Sign Your Document - Bansal Migration',
                'client_id' => $document->client_id,
                'user_id' => Auth::guard('admin')->id(),
            ]);

            Mail::mailer('sendgrid')->send('emails.signature.reminder', $templateData, function (Message $mail) use ($signer, $from, $log, $systemEmailLog) {
                $mail->to($signer->email, $signer->name)
                    ->subject('Reminder: Please Sign Your Document - Bansal Migration')
                    ->from($from['from_address'], $from['from_name']);

                $systemEmailLog->attachTrackingToMailMessage($mail, $log->id);
            });

            // Update reminder tracking
            $signer->update([
                'last_reminder_sent_at' => now(),
                'reminder_count' => $signer->reminder_count + 1,
            ]);

            // Create activity note for reminder email
            SignatureActivity::create([
                'document_id' => $document->id,
                'created_by' => Auth::guard('admin')->id() ?? 1,
                'action_type' => 'email_sent',
                'note' => "Reminder #{$signer->reminder_count} sent to {$signer->name} ({$signer->email})",
                'metadata' => [
                    'signer_id' => $signer->id,
                    'signer_email' => $signer->email,
                    'signer_name' => $signer->name,
                    'reminder_number' => $signer->reminder_count,
                    'status' => 'sent_via_sendgrid',
                ],
            ]);

            Log::info('Reminder sent via SendGrid mailer', [
                'signer_id' => $signer->id,
                'reminder_count' => $signer->reminder_count,
                'email_account' => $from['from_address'],
            ]);

            return true;
        } catch (\Exception $e) {
            if ($log !== null) {
                $systemEmailLog->markSendFailed($log, $e->getMessage());
            }

            // Create activity note for failed reminder
            try {
                SignatureActivity::create([
                    'document_id' => $document->id,
                    'created_by' => Auth::guard('admin')->id() ?? 1,
                    'action_type' => 'email_failed',
                    'note' => "Failed to send reminder to {$signer->name} ({$signer->email}): {$e->getMessage()}",
                    'metadata' => [
                        'signer_id' => $signer->id,
                        'signer_email' => $signer->email,
                        'signer_name' => $signer->name,
                        'reminder_number' => $signer->reminder_count + 1,
                        'error' => $e->getMessage(),
                    ],
                ]);
            } catch (\Exception $noteException) {
                Log::warning('Failed to create reminder failure note', [
                    'document_id' => $document->id,
                    'error' => $noteException->getMessage(),
                ]);
            }

            Log::error('Failed to send reminder', [
                'signer_id' => $signer->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Void a document
     */
    public function void(Document $document, ?string $reason = null): bool
    {
        try {
            $document->update([
                'status' => 'voided',
            ]);

            // Optionally log the reason
            if ($reason) {
                Log::info('Document voided', [
                    'document_id' => $document->id,
                    'reason' => $reason,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to void document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Associate a document with an entity (Client or Lead)
     */
    public function associate(Document $document, string $entityType, int $entityId, ?string $note = null): bool
    {
        try {
            $updates = match ($entityType) {
                'client' => ['client_id' => $entityId, 'lead_id' => null],
                'lead' => ['lead_id' => $entityId, 'client_id' => null],
                default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}")
            };

            $document->update($updates);

            // Create audit trail entry in signature_activities
            SignatureActivity::create([
                'document_id' => $document->id,
                'created_by' => auth('admin')->id() ?? 1,
                'action_type' => 'associated',
                'note' => $note ?? "Document associated with {$entityType}",
                'metadata' => [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
            ]);

            // Create activity log on Client/Lead timeline
            if ($entityType === 'client') {
                ActivitiesLog::create([
                    'client_id' => $entityId,
                    'created_by' => auth('admin')->id() ?? 1,
                    'activity_type' => 'document',
                    'subject' => "Document #{$document->id} attached",
                    'description' => $note ?? "Document '{$document->display_title}' was attached to this client",
                    'task_status' => 0,
                    'pin' => 0,
                ]);
            }

            // Log the association
            Log::info('Document associated', [
                'document_id' => $document->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'note' => $note,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to associate document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Associate a document with category-specific storage and matter
     */
    public function associateWithCategory(Document $document, string $entityType, int $entityId, ?int $matterId, string $docCategory, ?string $note = null, ?string $folderName = null, ?string $checklist = null): bool
    {
        try {
            $documentableType = match ($entityType) {
                'client' => Admin::class,
                'lead' => Lead::class,
                default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}")
            };

            // Client document tabs query doc_type = 'visa'|'personal' (not *_documents).
            $docType = match ($docCategory) {
                'visa' => 'visa',
                'personal' => 'personal',
                default => 'general'
            };

            $updates = [
                'client_matter_id' => $matterId,
                'doc_type' => $docType,
                // Client Personal/Visa tabs also filter type = 'client'
                'type' => 'client',
            ];
            // Optional category folder id (PersonalDocumentType / VisaDocumentType id as string)
            if ($folderName !== null && $folderName !== '') {
                $updates['folder_name'] = (string) $folderName;
            }
            if ($checklist !== null && $checklist !== '') {
                $updates['checklist'] = $checklist;
            }
            if ($entityType === 'client') {
                $updates['client_id'] = $entityId;
                $updates['lead_id'] = null;
            } else {
                $updates['lead_id'] = $entityId;
                $updates['client_id'] = null;
            }

            // Match CRM client-tab naming: {clientPrefix}_{checklist}_{timestamp} + bare extension.
            // Leave myfile / signed_doc_link unchanged so signature download & preview keep working.
            if ($checklist !== null && $checklist !== '') {
                $storedName = $this->buildClientDocumentStoredName($entityType, $entityId, $checklist, $document);
                if ($storedName !== null) {
                    $updates['file_name'] = $storedName['file_name'];
                    $updates['filetype'] = $storedName['filetype'];
                }
            }

            $document->update($updates);

            // Create audit trail entry in signature_activities
            SignatureActivity::create([
                'document_id' => $document->id,
                'created_by' => auth('admin')->id() ?? 1,
                'action_type' => 'associated',
                'note' => $note ?? "Document associated with {$entityType} ({$docCategory})",
                'metadata' => [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'matter_id' => $matterId,
                    'doc_category' => $docCategory,
                    'doc_type' => $docType,
                    'folder_name' => $folderName,
                    'checklist' => $checklist,
                ],
            ]);

            // Create activity log on Client/Lead timeline
            if ($entityType === 'client') {
                $matterText = $matterId ? " (Matter: #{$matterId})" : '';
                ActivitiesLog::create([
                    'client_id' => $entityId,
                    'created_by' => auth('admin')->id() ?? 1,
                    'activity_type' => 'document',
                    'subject' => "Document #{$document->id} attached to {$docCategory} documents{$matterText}",
                    'description' => $note ?? "Document '{$document->display_title}' was attached to this client's {$docCategory} documents",
                    'task_status' => 0,
                    'pin' => 0,
                ]);
            }

            // Log the association
            Log::info('Document associated with category', [
                'document_id' => $document->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'matter_id' => $matterId,
                'doc_category' => $docCategory,
                'doc_type' => $docType,
                'checklist' => $checklist,
                'note' => $note,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to associate document with category', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build the CRM client-documents stored filename (no extension) + bare filetype.
     *
     * @return array{file_name: string, filetype: string}|null
     */
    protected function buildClientDocumentStoredName(
        string $entityType,
        int $entityId,
        string $checklist,
        Document $document
    ): ?array {
        $checklist = trim($checklist);
        if ($checklist === '') {
            return null;
        }

        $namePrefix = 'client';
        if ($entityType === 'client') {
            $admin = Admin::query()
                ->select(['id', 'client_id', 'first_name', 'is_company'])
                ->find($entityId);
            $sanitizedFirstName = $admin
                ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($admin->first_name ?? ''))
                : 'client';
            $namePrefix = DocumentStoredFilename::storedNamePrefix($admin, $sanitizedFirstName ?: 'client');
        } else {
            $lead = Lead::query()->select(['id', 'first_name'])->find($entityId);
            $namePrefix = $lead
                ? (preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($lead->first_name ?? '')) ?: 'client')
                : 'client';
        }

        return [
            'file_name' => $namePrefix.'_'.$checklist.'_'.time(),
            'filetype' => $document->getPreviewFileExtension(),
        ];
    }

    /**
     * Detach a document from its association
     */
    public function detach(Document $document, ?string $reason = null): bool
    {
        try {
            $oldClientId = $document->client_id;
            $oldLeadId = $document->lead_id;
            $entityType = $oldClientId ? 'client' : 'lead';
            $oldEntityId = $oldClientId ?? $oldLeadId;

            $document->update([
                'client_id' => null,
                'lead_id' => null,
            ]);

            // Create audit trail entry
            SignatureActivity::create([
                'document_id' => $document->id,
                'created_by' => auth('admin')->id() ?? 1,
                'action_type' => 'detached',
                'note' => $reason ?? "Document detached from {$entityType}",
                'metadata' => [
                    'old_entity_type' => $entityType,
                    'old_entity_id' => $oldEntityId,
                ],
            ]);

            // Create activity log on Client/Lead timeline
            if ($oldClientId) {
                ActivitiesLog::create([
                    'client_id' => $oldClientId,
                    'created_by' => auth('admin')->id() ?? 1,
                    'activity_type' => 'document',
                    'subject' => "Document #{$document->id} detached",
                    'description' => $reason ?? "Document '{$document->display_title}' was detached from this client",
                    'task_status' => 0,
                    'pin' => 0,
                ]);
            }

            // Log the detachment
            if ($reason) {
                Log::info('Document detached', [
                    'document_id' => $document->id,
                    'reason' => $reason,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to detach document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Auto-suggest association based on signer email
     */
    public function suggestAssociation(string $email): ?array
    {
        // Try to find matching client or lead (both are in admins table with type = 'client' or 'lead')
        $entity = Admin::where('email', $email)
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted')
            ->first();

        if ($entity) {
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
                    ->map(function ($matter) {
                        return [
                            'id' => $matter->id,
                            'label' => $matter->client_unique_matter_no.' - '.$matter->matter_title,
                            'status' => $matter->matter_status,
                        ];
                    })
                    ->toArray();
            } else {
                // Leads don't have matters
                $matters = [];
            }

            return [
                'type' => $entityType,
                'id' => $entity->id,
                'name' => trim("{$entity->first_name} {$entity->last_name}"),
                'email' => $entity->email,
                'matters' => $matters,
                'has_matters' => count($matters) > 0,
            ];
        }

        return null;
    }

    /**
     * Archive old drafts
     */
    public function archiveOldDrafts(int $daysOld = 30): int
    {
        $count = Document::where('status', 'draft')
            ->where('created_at', '<', now()->subDays($daysOld))
            ->notArchived()
            ->update(['status' => 'archived']);

        Log::info("Archived {$count} old draft documents");

        return $count;
    }

    /**
     * Get pending count for a user
     */
    public function getPendingCount(int $userId): int
    {
        return Document::forUser($userId)
            ->byStatus('sent')
            ->notArchived()
            ->count();
    }
}
