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
                $message = $clientName . ' requested amendments for EOI #' . $eoiNumber;
                if ($clientNotes !== null && trim($clientNotes) !== '') {
                    $message .= '. Notes: ' . trim($clientNotes);
                }
                $notificationType = 'eoi_amendment';
            } elseif ($eventType === 'confirmation') {
                $message = $clientName . ' confirmed EOI details for EOI #' . $eoiNumber;
                $notificationType = 'eoi_confirmation';
            } else {
                return;
            }

            $matter = self::resolveClientMatter($clientId);
            $notificationUrl = url('/clients/detail/' . base64_encode(convert_uuencode((string) $clientId)));
            if ($matter && !empty($matter->client_unique_matter_no)) {
                $notificationUrl .= '/' . $matter->client_unique_matter_no;
            }
            $notificationUrl .= '/eoiroi';

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

    protected static function createPersonAssistingAction(
        int $clientId,
        ?int $matterId,
        string $description,
        ?ClientMatter $matter,
        string $taskGroup = 'Client Portal'
    ): void {
        if ($clientId <= 0 || trim($description) === '' || $matter === null) {
            return;
        }

        $personAssistingId = (int) ($matter->sel_person_assisting ?? 0);
        if ($personAssistingId <= 0 || !Staff::where('id', $personAssistingId)->exists()) {
            return;
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
    }

    protected static function resolveClientMatter(int $clientId): ?ClientMatter
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
