<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceEmailManager extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $array;

    public function __construct($array)
    {
        // Queued mailables are JSON-encoded. Raw PDF bytes are binary (not UTF-8) and
        // break queue serialization — store as base64, decode again in build().
        if (!empty($array['file_content']) && empty($array['file_content_base64'])) {
            $array['file_content'] = base64_encode($array['file_content']);
            $array['file_content_base64'] = true;
        }

        $this->array = $array;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $message = $this->view($this->array['view'])
            ->from($this->array['from'], $this->array['name'])
            ->subject($this->array['subject']);

        $attachOptions = [
            'mime' => 'application/pdf',
        ];

        if (!empty($this->array['file_content'])) {
            $fileContent = $this->array['file_content'];
            if (!empty($this->array['file_content_base64'])) {
                $decoded = base64_decode($fileContent, true);
                if ($decoded !== false) {
                    $fileContent = $decoded;
                }
            }

            $message->attachData(
                $fileContent,
                $this->array['file_name'],
                $attachOptions
            );
        } elseif (!empty($this->array['file']) && is_file($this->array['file'])) {
            $message->attach($this->array['file'], array_merge($attachOptions, [
                'as' => $this->array['file_name'],
            ]));
        }

        if (!empty($this->array['email_log_id'])) {
            $emailLogId = (int) $this->array['email_log_id'];
            $message->withSymfonyMessage(function ($symfonyMessage) use ($emailLogId) {
                app(\App\Services\SystemEmailLogService::class)->attachTrackingHeader($symfonyMessage, $emailLogId);
            });
        }

        return $message;
    }
}
