<?php

namespace App\Services\CrmAccess;

use App\Events\BroadcastNotificationCreated;
use App\Events\NotificationCountUpdated;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\ClientAccessGrant;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\Team;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CrmAccessService
{
    public function hasGrantedSuperAdminLevelAccess(Staff $user): bool
    {
        if ((int) ($user->role ?? 0) === 1) {
            return true;
        }

        return (bool) ($user->grant_super_admin_access ?? false);
    }

    public function hasAdminConsoleLikeSuperAdminAccess(Staff $user): bool
    {
        return $this->hasGrantedSuperAdminLevelAccess($user);
    }

    public function isExemptRole(Staff $user): bool
    {
        if ($this->hasGrantedSuperAdminLevelAccess($user)) {
            return true;
        }

        $staffId = (int) ($user->id ?? 0);
        if ($staffId > 0 && in_array($staffId, config('crm_access.exempt_staff_ids', []), true)) {
            return true;
        }

        $role = (int) ($user->role ?? 0);

        return in_array($role, config('crm_access.exempt_role_ids', [1, 17]), true);
    }

    /**
     * Super Admin or CRM access approver may enable/disable quick cross-access on other staff.
     */
    public function canManageStaffQuickAccess(Staff $actor): bool
    {
        return $this->hasAdminConsoleLikeSuperAdminAccess($actor);
    }

    public function isApprover(Staff $user): bool
    {
        return $this->hasAdminConsoleLikeSuperAdminAccess($user);
    }

    /** @return list<int> */
    public function getApproverStaffIds(): array
    {
        $roleOneIds = Staff::query()
            ->where('role', 1)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $dbGrantedIds = Staff::query()
            ->where('grant_super_admin_access', 1)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($roleOneIds, $dbGrantedIds)));
    }

    public function hasActiveGrant(Staff $user, int $adminId): bool
    {
        if ((int) ($user->status ?? 0) !== 1) {
            return false;
        }

        $now = Carbon::now('UTC');

        return ClientAccessGrant::query()
            ->where('staff_id', (int) $user->id)
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now)
            ->exists();
    }

    public function requestQuickGrant(Staff $user, int $adminId, string $recordType, int $officeId, ?int $teamId, string $reasonCode): ClientAccessGrant
    {
        if (! $user->quick_access_enabled) {
            throw new CrmAccessDeniedException('Quick access is not enabled for your account.');
        }
        $reasons = config('crm_access.quick_reason_options', []);
        if (! array_key_exists($reasonCode, $reasons)) {
            throw new CrmAccessDeniedException('Invalid reason.');
        }
        $this->denyIfSuperAdminOnlyLockedClient($adminId);
        if ($this->hasDuplicateActiveQuickGrant($user, $adminId)) {
            throw new CrmAccessDeniedException('An active quick access grant already exists for this record.');
        }

        $minutes = max(1, (int) config('crm_access.quick_grant_minutes', 15));
        $starts = Carbon::now('UTC');
        $ends = $starts->copy()->addMinutes($minutes);

        $office = Branch::query()->find($officeId);
        if (! $office) {
            throw new CrmAccessDeniedException('Invalid office.');
        }

        $teamLabel = null;
        if ($teamId !== null) {
            $team = Team::query()->find($teamId);
            $teamLabel = $team ? (string) $team->name : 'Team ' . $teamId;
        }

        return ClientAccessGrant::query()->create([
            'staff_id' => (int) $user->id,
            'admin_id' => $adminId,
            'record_type' => $recordType,
            'grant_type' => 'quick',
            'access_type' => 'quick',
            'status' => 'active',
            'quick_reason_code' => $reasonCode,
            'office_id' => $officeId,
            'office_label_snapshot' => (string) $office->office_name,
            'team_id' => $teamId,
            'team_label_snapshot' => $teamLabel,
            'requested_at' => $starts,
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);
    }

    public function requestSupervisorGrant(Staff $user, int $adminId, string $recordType, int $officeId, string $reasonCode, string $note = ''): ClientAccessGrant
    {
        $quickOnly = config('crm_access.quick_access_only_role_ids', [14]);
        if (in_array((int) ($user->role ?? 0), $quickOnly, true)) {
            throw new CrmAccessDeniedException('Your role only supports quick access.');
        }

        $reasons = config('crm_access.quick_reason_options', []);
        if ($reasonCode === '' || ! array_key_exists($reasonCode, $reasons)) {
            throw new CrmAccessDeniedException('Invalid reason.');
        }

        $this->denyIfSuperAdminOnlyLockedClient($adminId);

        $maxPending = max(1, (int) config('crm_access.max_pending_supervisor_requests', 5));
        $pendingCount = ClientAccessGrant::query()
            ->where('staff_id', (int) $user->id)
            ->where('grant_type', 'supervisor_approved')
            ->where('status', 'pending')
            ->count();
        if ($pendingCount >= $maxPending) {
            throw new CrmAccessDeniedException("You already have {$maxPending} pending supervisor requests. Wait for them to be resolved before submitting more.");
        }

        $office = Branch::query()->find($officeId);
        if (! $office) {
            throw new CrmAccessDeniedException('Invalid office.');
        }

        $grant = ClientAccessGrant::query()->create([
            'staff_id' => (int) $user->id,
            'admin_id' => $adminId,
            'record_type' => $recordType,
            'grant_type' => 'supervisor_approved',
            'access_type' => 'supervisor_approved',
            'status' => 'pending',
            'quick_reason_code' => $reasonCode,
            'requester_note' => $note !== '' ? $note : null,
            'office_id' => $officeId,
            'office_label_snapshot' => (string) $office->office_name,
            'team_id' => null,
            'team_label_snapshot' => null,
            'requested_at' => Carbon::now('UTC'),
        ]);

        $this->notifyApproversOfPendingGrant($grant, $user, $adminId);

        return $grant;
    }

    public function approveGrant(Staff $approver, int $grantId): ClientAccessGrant
    {
        if (! $this->isApprover($approver)) {
            throw new CrmAccessDeniedException('Not authorized to approve.');
        }

        $grant = ClientAccessGrant::query()->findOrFail($grantId);
        if ($grant->status !== 'pending') {
            throw new CrmAccessDeniedException('Grant is not pending.');
        }
        if ((int) $grant->staff_id === (int) $approver->id) {
            throw new CrmAccessDeniedException('You cannot approve your own request.');
        }

        $this->denyIfSuperAdminOnlyLockedClient((int) $grant->admin_id);

        $hours = max(1, (int) config('crm_access.supervisor_grant_hours', 24));
        $starts = Carbon::now('UTC');
        $ends = $starts->copy()->addHours($hours);

        $grant->update([
            'status' => 'active',
            'approved_by_staff_id' => (int) $approver->id,
            'approved_at' => $starts,
            'starts_at' => $starts,
            'ends_at' => $ends,
        ]);

        $this->notifyRequesterGrantProcessed($grant->fresh(), 'approved');

        return $grant->fresh();
    }

    public function rejectGrant(Staff $approver, int $grantId, string $reason = ''): ClientAccessGrant
    {
        if (! $this->isApprover($approver)) {
            throw new CrmAccessDeniedException('Not authorized to reject.');
        }

        $grant = ClientAccessGrant::query()->findOrFail($grantId);
        if ($grant->status !== 'pending') {
            throw new CrmAccessDeniedException('Grant is not pending.');
        }
        if ((int) $grant->staff_id === (int) $approver->id) {
            throw new CrmAccessDeniedException('You cannot reject your own request.');
        }

        $grant->update([
            'status' => 'rejected',
            'approved_by_staff_id' => (int) $approver->id,
            'revoke_reason' => $reason !== '' ? $reason : null,
        ]);

        $this->notifyRequesterGrantProcessed($grant->fresh(), 'rejected');

        return $grant->fresh();
    }

    public function revokeGrantsForStaff(int $staffId, string $reason): int
    {
        return ClientAccessGrant::query()
            ->where('staff_id', $staffId)
            ->whereIn('status', ['active', 'pending'])
            ->update([
                'status' => 'revoked',
                'revoked_at' => Carbon::now('UTC'),
                'revoke_reason' => $reason,
            ]);
    }

    public function expireStaleGrants(): int
    {
        $now = Carbon::now('UTC');

        $expired = ClientAccessGrant::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update(['status' => 'expired']);

        // Also clean up pending requests that were never actioned after a generous window
        // (configurable; defaults to 14 days so stale requests don't accumulate).
        $pendingTtlDays = max(1, (int) config('crm_access.pending_ttl_days', 14));
        $pendingCutoff  = $now->copy()->subDays($pendingTtlDays);

        $pendingExpired = ClientAccessGrant::query()
            ->where('status', 'pending')
            ->where('requested_at', '<', $pendingCutoff)
            ->update(['status' => 'expired', 'revoke_reason' => 'Auto-expired: not actioned within ' . $pendingTtlDays . ' days']);

        return $expired + $pendingExpired;
    }

    /**
     * Cross-access grants cannot open super-admin-only locked clients
     * ({@see StaffClientVisibility::canAccessClientOrLead}); reject create/approve early.
     * Missing records are left to existing callers — only an existing locked client is blocked.
     */
    protected function denyIfSuperAdminOnlyLockedClient(int $adminId): void
    {
        $row = Admin::query()
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->first(['id', 'type', 'client_id']);

        if ($row && StaffClientVisibility::isSuperAdminOnlyLockedClient($row->type ?? null, $row->client_id ?? null)) {
            throw new CrmAccessDeniedException('This client is restricted to super admin.');
        }
    }

    protected function hasDuplicateActiveQuickGrant(Staff $user, int $adminId): bool
    {
        $now = Carbon::now('UTC');

        return ClientAccessGrant::query()
            ->where('staff_id', (int) $user->id)
            ->where('admin_id', $adminId)
            ->where('grant_type', 'quick')
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now)
            ->exists();
    }

    protected function notifyApproversOfPendingGrant(ClientAccessGrant $grant, Staff $requester, int $adminId): void
    {
        $senderName = trim(($requester->first_name ?? '') . ' ' . ($requester->last_name ?? ''));
        if ($senderName === '') {
            $senderName = $requester->email ?? 'Staff';
        }
        $msg = $senderName . ' requested access to record #' . $adminId;
        $url = url('/crm/access/queue');

        $approverIds = array_values(array_filter(
            $this->getApproverStaffIds(),
            fn ($id) => (int) $id !== (int) $requester->id
        ));

        if (empty($approverIds)) {
            return;
        }

        $batchUuid = (string) Str::uuid();
        $sentAt = Carbon::now();

        foreach ($approverIds as $receiverId) {
            try {
                Notification::query()->create([
                    'sender_id' => (int) $requester->id,
                    'receiver_id' => (int) $receiverId,
                    'module_id' => (int) $grant->id,
                    'url' => $url,
                    'notification_type' => 'access_request',
                    'message' => $msg,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                $unreadCount = (int) DB::table('notifications')
                    ->where('receiver_id', (int) $receiverId)
                    ->where('receiver_status', 0)
                    ->count();
                broadcast(new NotificationCountUpdated((int) $receiverId, $unreadCount, $msg, $url));
            } catch (\Throwable $e) {
                Log::warning('access_request notification failed', ['e' => $e->getMessage()]);
            }
        }

        // Rich real-time toast for all approvers simultaneously
        try {
            broadcast(new BroadcastNotificationCreated(
                batchUuid: $batchUuid,
                message: $msg,
                title: 'Access request',
                senderId: (int) $requester->id,
                senderName: $senderName,
                channelRecipientIds: $approverIds,
                payloadRecipientIds: count($approverIds) <= 50 ? $approverIds : [],
                recipientCount: count($approverIds),
                scope: 'specific',
                sentAt: $sentAt
            ));
        } catch (\Throwable $e) {
            Log::warning('access_request BroadcastNotificationCreated failed', ['e' => $e->getMessage()]);
        }
    }

    protected function notifyRequesterGrantProcessed(ClientAccessGrant $grant, string $verb): void
    {
        $receiverId = (int) $grant->staff_id;
        $hours = max(1, (int) config('crm_access.supervisor_grant_hours', 24));
        $msg = $verb === 'approved'
            ? "Your supervisor access request was approved ({$hours}h from approval)."
            : 'Your supervisor access request was rejected.';
        $title = $verb === 'approved' ? 'Access approved' : 'Access rejected';

        $senderId = (int) ($grant->approved_by_staff_id ?? 0);
        $notifUrl = url('/crm/access/my-grants');

        $senderName = 'System';
        if ($senderId > 0) {
            $approver = Staff::query()->find($senderId);
            if ($approver) {
                $senderName = trim(($approver->first_name ?? '') . ' ' . ($approver->last_name ?? '')) ?: $approver->email;
            }
        }

        try {
            Notification::query()->create([
                'sender_id' => $senderId > 0 ? $senderId : $receiverId,
                'receiver_id' => $receiverId,
                'module_id' => (int) $grant->id,
                'url' => $notifUrl,
                'notification_type' => 'access_request_' . $verb,
                'message' => $msg,
                'receiver_status' => 0,
                'seen' => 0,
            ]);
            $unreadCount = (int) DB::table('notifications')
                ->where('receiver_id', $receiverId)
                ->where('receiver_status', 0)
                ->count();
            broadcast(new NotificationCountUpdated($receiverId, $unreadCount, $msg, $notifUrl));
        } catch (\Throwable $e) {
            Log::warning('access_request result notification failed', ['e' => $e->getMessage()]);
        }

        // Rich real-time toast for the requester
        try {
            broadcast(new BroadcastNotificationCreated(
                batchUuid: (string) Str::uuid(),
                message: $msg,
                title: $title,
                senderId: $senderId > 0 ? $senderId : $receiverId,
                senderName: $senderName,
                channelRecipientIds: [$receiverId],
                payloadRecipientIds: [$receiverId],
                recipientCount: 1,
                scope: 'specific',
                sentAt: Carbon::now()
            ));
        } catch (\Throwable $e) {
            Log::warning('access_request result BroadcastNotificationCreated failed', ['e' => $e->getMessage()]);
        }
    }
}
