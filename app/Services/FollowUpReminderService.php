<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Note;
use App\Models\Notification;
use App\Support\ActionTaskGroup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class FollowUpReminderService
{
    public const NOTIFICATION_TYPE = 'followup_reminder';

    /**
     * @return array{due_date: string, scanned: int, sent: int, skipped: int}
     */
    public function sendDueReminders(): array
    {
        $dueDate = ActionTaskGroup::latestVisibleFollowUpAssignDate();

        $followUps = Note::query()
            ->with(['client.company'])
            ->where('is_action', 1)
            ->where('status', '<>', '1')
            ->where('type', 'client')
            ->where('task_group', ActionTaskGroup::FOLLOW_UP)
            ->whereNotNull('assigned_to')
            ->where('assigned_to', '>', 0)
            ->whereDate('action_date', $dueDate)
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($followUps as $followUp) {
            if ($this->reminderAlreadySent($followUp)) {
                $skipped++;

                continue;
            }

            try {
                $this->createReminderNotification($followUp);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Follow-up reminder failed', [
                    'note_id' => $followUp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'due_date' => $dueDate,
            'scanned' => $followUps->count(),
            'sent' => $sent,
            'skipped' => $skipped,
        ];
    }

    public static function reminderMessage(string $clientLabel, string $noteSnippet = ''): string
    {
        $message = 'Reminder: Follow Up due tomorrow for '.$clientLabel.'.';
        $snippet = trim($noteSnippet);
        if ($snippet !== '') {
            $message .= ' '.$snippet;
        }

        return $message;
    }

    public static function clientLabel(?Admin $client): string
    {
        if (! $client) {
            return 'Client';
        }

        $label = trim($client->company_name_or_personal_name);
        if ($label === '') {
            $label = trim(($client->first_name ?? '').' '.($client->last_name ?? ''));
        }

        return $label !== '' ? $label : 'Client';
    }

    public static function noteSnippet(?string $description): string
    {
        $plain = trim(html_entity_decode(strip_tags((string) $description), ENT_QUOTES, 'UTF-8'));
        if ($plain === '') {
            return '';
        }

        if (mb_strlen($plain) > 120) {
            return mb_substr($plain, 0, 117).'...';
        }

        return $plain;
    }

    private function reminderAlreadySent(Note $followUp): bool
    {
        return Notification::query()
            ->where('notification_type', self::NOTIFICATION_TYPE)
            ->where('receiver_id', $followUp->assigned_to)
            ->where('module_id', $followUp->id)
            ->exists();
    }

    private function createReminderNotification(Note $followUp): void
    {
        $clientLabel = self::clientLabel($followUp->client);
        $encodedClientId = base64_encode(convert_uuencode((string) $followUp->client_id));

        $notification = new Notification;
        $notification->sender_id = (int) ($followUp->user_id ?: $followUp->assigned_to);
        $notification->receiver_id = (int) $followUp->assigned_to;
        $notification->module_id = (int) $followUp->id;
        $notification->url = URL::to('/clients/detail/'.$encodedClientId);
        $notification->notification_type = self::NOTIFICATION_TYPE;
        $notification->message = self::reminderMessage($clientLabel, self::noteSnippet($followUp->description));
        $notification->receiver_status = 0;
        $notification->sender_status = 1;
        $notification->seen = 0;
        $notification->save();
    }
}
