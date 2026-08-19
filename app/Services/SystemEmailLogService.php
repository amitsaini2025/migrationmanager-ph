<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email as SymfonyEmail;

class SystemEmailLogService
{
    public const CONVERSION_TYPE = 'system_generated';

    /** @var array<string, string> */
    public const CATEGORIES = [
        'invoice' => 'Invoice',
        'receipt' => 'Receipt',
        'appointment' => 'Appointment',
        'appointment_reminder' => 'Appointment Reminder',
        'appointment_cancellation' => 'Appointment Cancellation',
        'appointment_reschedule' => 'Appointment Reschedule',
        'appointment_client_confirmed' => 'Appointment Client Confirmed',
        'visa_reminder' => 'Visa Expiry Reminder',
        'signature' => 'E-Signature',
        'signature_reminder' => 'Signature Reminder',
        'eoi' => 'EOI/ROI Confirmation',
        'portal' => 'Client Portal',
        'hubdoc' => 'Hubdoc',
        'verification' => 'Email Verification',
        'template' => 'Template Email',
        'other' => 'Other',
    ];

    /**
     * Create a pending email_logs row for a system-generated outgoing email.
     *
     * @param  array{
     *     category: string,
     *     from_mail?: ?string,
     *     to_mail: string,
     *     subject?: ?string,
     *     message?: ?string,
     *     client_id?: ?int,
     *     client_matter_id?: ?int,
     *     user_id?: ?int,
     *     type?: ?string,
     *     has_attachments?: bool,
     * }  $data
     */
    public function createPending(array $data): EmailLog
    {
        $category = $this->normalizeCategory($data['category'] ?? 'other');

        return EmailLog::create([
            'user_id' => $data['user_id'] ?? null,
            'from_mail' => $data['from_mail'] ?? config('mail.from.address'),
            'to_mail' => is_string($data['to_mail']) ? $data['to_mail'] : $this->resolveRecipient($data['to_mail']),
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'] ?? null,
            'type' => $data['type'] ?? 'client',
            'mail_type' => 1,
            'client_id' => $data['client_id'] ?? null,
            'client_matter_id' => $data['client_matter_id'] ?? null,
            'conversion_type' => self::CONVERSION_TYPE,
            'system_email_category' => $category,
            'delivery_status' => 'pending',
        ]);
    }

    public function markSendFailed(EmailLog $log, string $reason): void
    {
        $log->update([
            'delivery_status' => 'send_failed',
            'status_reason' => Str::limit($reason, 500),
        ]);
    }

    public function attachTrackingHeader(SymfonyEmail $message, int $emailLogId): void
    {
        $payload = [
            'unique_args' => [
                'email_log_id' => (string) $emailLogId,
            ],
        ];

        $existing = $message->getHeaders()->get('X-SMTPAPI');
        if ($existing) {
            $decoded = json_decode($existing->getBodyAsString(), true);
            if (is_array($decoded)) {
                $payload = array_replace_recursive($decoded, $payload);
            }
            $message->getHeaders()->remove('X-SMTPAPI');
        }

        $message->getHeaders()->addTextHeader('X-SMTPAPI', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function attachTrackingToMailMessage(Message $message, int $emailLogId): void
    {
        $this->attachTrackingHeader($message->getSymfonyMessage(), $emailLogId);
    }

    public function applyTrackingToMailable(Mailable $mailable, int $emailLogId): Mailable
    {
        return $mailable->withSymfonyMessage(function (SymfonyEmail $message) use ($emailLogId) {
            $this->attachTrackingHeader($message, $emailLogId);
        });
    }

    /**
     * @param  array{
     *     category: string,
     *     from_mail?: ?string,
     *     to_mail: string,
     *     subject?: ?string,
     *     message?: ?string,
     *     client_id?: ?int,
     *     client_matter_id?: ?int,
     *     user_id?: ?int,
     *     type?: ?string,
     * }  $meta
     */
    public function logAndSendMailable(array $meta, Mailable $mailable, mixed $to): void
    {
        $meta['to_mail'] = is_string($meta['to_mail'] ?? null)
            ? $meta['to_mail']
            : $this->resolveRecipient($to);

        $log = $this->createPending($meta);

        try {
            Mail::mailer('sendgrid')
                ->to($to)
                ->send($this->applyTrackingToMailable($mailable, $log->id));
        } catch (\Throwable $e) {
            $this->markSendFailed($log, $e->getMessage());
            throw $e;
        }
    }

    public function categoryLabel(?string $category): string
    {
        if ($category === null || $category === '') {
            return 'Other';
        }

        return self::CATEGORIES[$category] ?? ucwords(str_replace('_', ' ', $category));
    }

    public function normalizeCategory(string $category): string
    {
        $category = strtolower(trim($category));

        return array_key_exists($category, self::CATEGORIES) ? $category : 'other';
    }

    /**
     * Resolve recipient address from mixed input (string, array of strings, Mailable Address objects).
     */
    public function resolveRecipient(mixed $to): string
    {
        if (is_string($to)) {
            return $to;
        }

        if (is_array($to)) {
            $addresses = [];
            foreach ($to as $item) {
                if (is_string($item)) {
                    $addresses[] = $item;
                } elseif (is_object($item) && isset($item->address)) {
                    $addresses[] = (string) $item->address;
                } elseif (is_array($item) && ! empty($item['address'])) {
                    $addresses[] = (string) $item['address'];
                }
            }

            return implode(', ', array_unique(array_filter($addresses)));
        }

        if (is_object($to) && method_exists($to, 'getAddress')) {
            return (string) $to->getAddress();
        }

        return (string) $to;
    }
}
