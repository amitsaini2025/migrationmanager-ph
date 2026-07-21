<?php

namespace App\Services;

use App\Events\NotificationCountUpdated;
use App\Models\ClientEoiReference;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EoiClientConfirmationNotificationService
{
    /**
     * Notify CRM staff and create assigned actions when a client confirms or requests EOI amendments.
     */
    public static function notifyStaff(ClientEoiReference $eoi, string $eventType, ?string $clientNotes = null): void
    {
        try {
            $clientId = (int) $eoi->client_id;
            if ($clientId <= 0) {
                return;
            }

            $client = $eoi->relationLoaded('client') ? $eoi->client : $eoi->client()->first();
            $clientName = $client
                ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))
                : 'Client';
            if ($clientName === '') {
                $clientName = 'Client';
            }

            $eoiNumber = $eoi->EOI_number ?? ('#' . $eoi->id);

            if ($eventType === 'amendment') {
                $message = self::buildAmendmentMessage($eoi, $clientNotes);
                $notificationType = 'eoi_amendment';
            } elseif ($eventType === 'confirmation') {
                $message = $clientName . ' confirmed EOI details for EOI #' . $eoiNumber;
                $notificationType = 'eoi_confirmation';
            } else {
                return;
            }

            $matter = self::resolveClientMatter($clientId);
            $notificationUrl = self::buildEoiNotificationUrl($clientId, $matter);

            $moduleId = $matter ? (int) $matter->id : (int) $eoi->id;

            // Bell notification: verifier (staff who verified/sent the EOI email) only.
            $notificationRecipientIds = collect();
            if (!empty($eoi->checked_by)) {
                $notificationRecipientIds->push((int) $eoi->checked_by);
            }

            foreach ($notificationRecipientIds as $receiverStaffId) {
                if (!Staff::where('id', $receiverStaffId)->exists()) {
                    continue;
                }
                try {
                    Notification::create([
                        'sender_id' => $clientId,
                        'receiver_id' => $receiverStaffId,
                        'module_id' => $moduleId,
                        'url' => $notificationUrl,
                        'notification_type' => $notificationType,
                        'message' => $message,
                        'receiver_status' => 0,
                        'seen' => 0,
                    ]);
                    $unreadCount = (int) DB::table('notifications')
                        ->where('receiver_id', $receiverStaffId)
                        ->where('receiver_status', 0)
                        ->count();
                    broadcast(new NotificationCountUpdated($receiverStaffId, $unreadCount, $message, $notificationUrl));
                } catch (\Exception $e) {
                    Log::warning('EOI client response: failed to notify staff member', [
                        'eoi_id' => $eoi->id,
                        'receiver_id' => $receiverStaffId,
                        'event_type' => $eventType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Assigned action: Person Assisting on the matter only.
            $taskGroup = $eventType === 'amendment' ? 'EOI/ROI Amendment' : 'Client Portal';
            try {
                self::createPersonAssistingAction(
                    $clientId,
                    $matter ? (int) $matter->id : null,
                    $message,
                    $matter,
                    $taskGroup
                );
            } catch (\Exception $e) {
                Log::warning('EOI client response: failed to create Action page entry', [
                    'eoi_id' => $eoi->id,
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('EOI client response: failed to notify staff', [
                'eoi_id' => $eoi->id ?? null,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Backfill missing verifier notifications and PA actions for existing amendment requests.
     *
     * @return array{action: string, notification: string, reason: string|null}
     */
    public static function backfillAmendmentRequest(
        ClientEoiReference $eoi,
        bool $withNotifications = true,
        bool $dryRun = false
    ): array {
        $result = [
            'action' => 'skipped',
            'notification' => $withNotifications ? 'skipped' : 'disabled',
            'reason' => null,
        ];

        $clientId = (int) $eoi->client_id;
        if ($clientId <= 0) {
            $result['reason'] = 'Missing client_id';

            return $result;
        }

        if ($eoi->client_confirmation_status !== 'amendment_requested') {
            $result['reason'] = 'Status is not amendment_requested';

            return $result;
        }

        $message = self::buildAmendmentMessage($eoi, $eoi->client_confirmation_notes);
        $matter = self::resolveClientMatter($clientId);
        $moduleId = $matter ? (int) $matter->id : (int) $eoi->id;
        $notificationUrl = self::buildEoiNotificationUrl($clientId, $matter);
        $eoiNumber = (string) ($eoi->EOI_number ?? $eoi->id);

        if ($matter === null) {
            $result['reason'] = 'No active client matter found';
        } else {
            $personAssistingId = (int) ($matter->sel_person_assisting ?? 0);
            if ($personAssistingId <= 0) {
                $result['action'] = 'skipped';
                $result['reason'] = ($result['reason'] ? $result['reason'] . '; ' : '') . 'No Person Assisting on matter';
            } elseif (self::hasOpenAmendmentAction($clientId, $personAssistingId, $eoiNumber)) {
                $result['action'] = 'skipped';
            } elseif ($dryRun) {
                $result['action'] = 'would_create';
            } else {
                $created = self::createPersonAssistingAction(
                    $clientId,
                    (int) $matter->id,
                    $message,
                    $matter,
                    'EOI/ROI Amendment'
                );
                $result['action'] = $created ? 'created' : 'skipped';
            }
        }

        if (!$withNotifications) {
            return $result;
        }

        $verifierId = (int) ($eoi->checked_by ?? 0);
        if ($verifierId <= 0) {
            $result['notification'] = 'skipped';
            $result['reason'] = ($result['reason'] ? $result['reason'] . '; ' : '') . 'No verifier (checked_by)';

            return $result;
        }

        if (!Staff::where('id', $verifierId)->exists()) {
            $result['notification'] = 'skipped';
            $result['reason'] = ($result['reason'] ? $result['reason'] . '; ' : '') . 'Verifier staff record not found';

            return $result;
        }

        if (self::hasAmendmentNotification($clientId, $verifierId, $eoiNumber)) {
            $result['notification'] = 'skipped';

            return $result;
        }

        if ($dryRun) {
            $result['notification'] = 'would_create';

            return $result;
        }

        try {
            Notification::create([
                'sender_id' => $clientId,
                'receiver_id' => $verifierId,
                'module_id' => $moduleId,
                'url' => $notificationUrl,
                'notification_type' => 'eoi_amendment',
                'message' => $message,
                'receiver_status' => 0,
                'seen' => 0,
            ]);
            $unreadCount = (int) DB::table('notifications')
                ->where('receiver_id', $verifierId)
                ->where('receiver_status', 0)
                ->count();
            broadcast(new NotificationCountUpdated($verifierId, $unreadCount, $message, $notificationUrl));
            $result['notification'] = 'created';
        } catch (\Exception $e) {
            Log::warning('EOI amendment backfill: failed to create verifier notification', [
                'eoi_id' => $eoi->id,
                'receiver_id' => $verifierId,
                'error' => $e->getMessage(),
            ]);
            $result['notification'] = 'failed';
            $result['reason'] = ($result['reason'] ? $result['reason'] . '; ' : '') . 'Notification error: ' . $e->getMessage();
        }

        return $result;
    }

    public static function buildAmendmentMessage(ClientEoiReference $eoi, ?string $clientNotes = null): string
    {
        $client = $eoi->relationLoaded('client') ? $eoi->client : $eoi->client()->first();
        $clientName = $client
            ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))
            : 'Client';
        if ($clientName === '') {
            $clientName = 'Client';
        }

        $eoiNumber = $eoi->EOI_number ?? ('#' . $eoi->id);
        $message = $clientName . ' requested amendments for EOI #' . $eoiNumber;
        $notes = $clientNotes ?? $eoi->client_confirmation_notes;
        if ($notes !== null && trim((string) $notes) !== '') {
            $message .= '. Notes: ' . trim((string) $notes);
        }

        return $message;
    }

    public static function hasOpenAmendmentAction(int $clientId, int $assignedTo, string $eoiNumber): bool
    {
        if ($clientId <= 0 || $assignedTo <= 0 || trim($eoiNumber) === '') {
            return false;
        }

        return Note::query()
            ->where('client_id', $clientId)
            ->where('assigned_to', $assignedTo)
            ->where('is_action', 1)
            ->where('type', 'client')
            ->where('status', '<>', '1')
            ->where('task_group', 'EOI/ROI Amendment')
            ->where('description', 'like', '%EOI #' . $eoiNumber . '%')
            ->exists();
    }

    public static function hasAmendmentNotification(int $clientId, int $receiverId, string $eoiNumber): bool
    {
        if ($clientId <= 0 || $receiverId <= 0 || trim($eoiNumber) === '') {
            return false;
        }

        return Notification::query()
            ->where('sender_id', $clientId)
            ->where('receiver_id', $receiverId)
            ->where('notification_type', 'eoi_amendment')
            ->where('message', 'like', '%' . $eoiNumber . '%')
            ->exists();
    }

    protected static function buildEoiNotificationUrl(int $clientId, ?ClientMatter $matter): string
    {
        $notificationUrl = url('/clients/detail/' . base64_encode(convert_uuencode((string) $clientId)));
        if ($matter && !empty($matter->client_unique_matter_no)) {
            $notificationUrl .= '/' . $matter->client_unique_matter_no;
        }

        return $notificationUrl . '/eoiroi';
    }

    protected static function createPersonAssistingAction(
        int $clientId,
        ?int $matterId,
        string $description,
        ?ClientMatter $matter,
        string $taskGroup = 'Client Portal'
    ): bool {
        if ($clientId <= 0 || trim($description) === '' || $matter === null) {
            return false;
        }

        $personAssistingId = (int) ($matter->sel_person_assisting ?? 0);
        if ($personAssistingId <= 0 || !Staff::where('id', $personAssistingId)->exists()) {
            return false;
        }

        $actionNote = new Note();
        $actionNote->user_id = $clientId;
        $actionNote->client_id = $clientId;
        $actionNote->matter_id = $matterId;
        $actionNote->assigned_to = $personAssistingId;
        $actionNote->description = $description;
        $actionNote->action_date = now()->toDateString();
        $actionNote->task_group = $taskGroup;
        $actionNote->type = 'client';
        $actionNote->is_action = 1;
        $actionNote->status = '0';
        $actionNote->pin = 0;
        $actionNote->unique_group_id = 'group_' . uniqid('', true);
        $actionNote->save();

        return true;
    }

    public static function resolveClientMatter(int $clientId): ?ClientMatter
    {
        $eoiMatter = ClientMatter::query()
            ->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->whereHas('matter', static function ($query) {
                $query->where(function ($q) {
                    $q->whereRaw("LOWER(COALESCE(nick_name, '')) = 'eoi'")
                        ->orWhereRaw("LOWER(COALESCE(title, '')) LIKE '%eoi%'")
                        ->orWhereRaw("LOWER(COALESCE(title, '')) LIKE '%expression of interest%'")
                        ->orWhereRaw("LOWER(COALESCE(title, '')) LIKE '%expression%'");
                });
            })
            ->orderByDesc('id')
            ->first();

        if ($eoiMatter) {
            return $eoiMatter;
        }

        return ClientMatter::query()
            ->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->first();
    }
}
