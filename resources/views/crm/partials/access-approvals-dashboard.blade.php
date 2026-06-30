@php
    $dashStaff = Auth::user();
    $dashCrmAccessIsApprover = $dashStaff instanceof \App\Models\Staff
        && app(\App\Services\CrmAccess\CrmAccessService::class)->isApprover($dashStaff);
    $dashCrmAccessPending = $dashCrmAccessIsApprover
        ? \App\Models\ClientAccessGrant::query()
            ->where('status', 'pending')
            ->where('grant_type', 'supervisor_approved')
            ->where('staff_id', '!=', (int) $dashStaff->id)
            ->count()
        : 0;
@endphp
@if($dashCrmAccessIsApprover)
<section class="access-approvals-dashboard focus-container" style="margin-bottom: 1.5rem;">
    <div class="focus-header access-approvals-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
        <h3 style="margin: 0;">
            @icon('shield-user', ['aria-hidden' => 'true'])
            Access approvals
            @if($dashCrmAccessPending > 0)
                <span class="badge badge-access-pending ml-1" id="crm-access-approvals-count">{{ $dashCrmAccessPending }}</span>
            @endif
        </h3>
        <a href="{{ route('crm.access.queue') }}" class="btn btn-sm btn-outline-secondary">
            @icon('inbox', ['class' => 'mr-1', 'aria-hidden' => 'true']) Full access queue
        </a>
    </div>
    <div id="crm-access-dashboard-mini-queue" class="access-approvals-queue px-1 py-2" style="min-height: 2rem;">Loading…</div>
</section>
@push('scripts')
@php
    $miniQueueUrl = route('crm.access.queue.mini');
    $approveTemplateUrl = str_replace('999999999', '__ID__', route('crm.access.approve', ['grant' => 999999999]));
    $rejectTemplateUrl = str_replace('999999999', '__ID__', route('crm.access.reject', ['grant' => 999999999]));
@endphp
<script>
(function () {
    var miniUrl = @json($miniQueueUrl);
    var reasonLabels = @json(config('crm_access.quick_reason_options', []));
    var approveTpl = @json($approveTemplateUrl);
    var rejectTpl = @json($rejectTemplateUrl);
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';
    var fmtWhen = typeof window.formatGrantWhen === 'function'
        ? window.formatGrantWhen
        : function (v) { return v == null || v === '' ? '' : String(v); };

    function renderDashboardMini() {
        var box = document.getElementById('crm-access-dashboard-mini-queue');
        if (!box) return;
        box.innerHTML = 'Loading…';
        fetch(miniUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // Keep the header counter in sync after approve/reject without page refresh.
                var pendingCount = (data && typeof data.pending_count === 'number') ? data.pending_count : null;
                var badge = document.getElementById('crm-access-approvals-count');
                if (pendingCount != null) {
                    if (pendingCount > 0) {
                        if (!badge) {
                            var h3 = box.closest('section') ? box.closest('section').querySelector('h3') : null;
                            if (h3) {
                                var span = document.createElement('span');
                                span.id = 'crm-access-approvals-count';
                                span.className = 'badge badge-access-pending ml-1';
                                span.textContent = String(pendingCount);
                                h3.appendChild(span);
                                badge = span;
                            }
                        } else {
                            badge.textContent = String(pendingCount);
                            badge.style.display = '';
                        }
                    } else if (badge) {
                        badge.parentNode && badge.parentNode.removeChild(badge);
                    }
                }
                var items = data.items || [];
                if (items.length === 0) {
                    box.innerHTML = '<span class="access-approvals-empty">No pending supervisor requests.</span>';
                    return;
                }
                var html = '';
                items.forEach(function (g) {
                    var req = g.staff ? (g.staff.first_name + ' ' + g.staff.last_name).trim() : ('#' + g.staff_id);
                    var rec = g.admin ? (g.admin.first_name + ' ' + g.admin.last_name).trim() : ('#' + g.admin_id);
                    var rc = g.quick_reason_code || '';
                    var reasonTxt = rc && reasonLabels[rc] ? String(reasonLabels[rc]).replace(/</g, '&lt;') : '';
                    var note = g.requester_note ? String(g.requester_note).replace(/</g, '&lt;').slice(0, 120) : '';
                    var detail = '';
                    if (reasonTxt && note) {
                        detail = reasonTxt + ' · ' + note;
                    } else {
                        detail = reasonTxt || note;
                    }
                    html += '<div class="cag-grant-card" data-grant-mini="' + g.id + '">' +
                        '<div class="cag-grant-primary">' + rec + ' <span class="cag-grant-record">(' + g.record_type + ' #' + g.admin_id + ')</span></div>' +
                        '<div class="cag-grant-meta">' + fmtWhen(g.requested_at) + ' · ' + req + '</div>' +
                        (detail ? '<div class="cag-grant-detail">' + detail + '</div>' : '') +
                        '<div class="cag-grant-actions">' +
                        '<button type="button" class="btn btn-sm btn-success py-1 px-2 js-cag-mini-approve" data-id="' + g.id + '">Approve</button> ' +
                        '<button type="button" class="btn btn-sm btn-outline-danger py-1 px-2 js-cag-mini-reject" data-id="' + g.id + '">Reject</button>' +
                        '</div></div>';
                });
                box.innerHTML = html;
            })
            .catch(function () {
                box.innerHTML = '<span class="text-danger">Could not load access queue.</span>';
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('crm-access-dashboard-mini-queue')) {
            renderDashboardMini();
        }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.matches('.js-cag-mini-approve') && !e.target.matches('.js-cag-mini-reject')) return;
        var inDashboard = e.target.closest('#crm-access-dashboard-mini-queue');
        if (!inDashboard) return;
        if (e.target.matches('.js-cag-mini-approve')) {
            var id = e.target.getAttribute('data-id');
            fetch(approveTpl.replace('__ID__', id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '{}'
            }).then(function () { renderDashboardMini(); });
        }
        if (e.target.matches('.js-cag-mini-reject')) {
            var id2 = e.target.getAttribute('data-id');
            var reason = window.prompt('Reject reason (optional):') || '';
            fetch(rejectTpl.replace('__ID__', id2), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ reason: reason })
            }).then(function () { renderDashboardMini(); });
        }
    });
})();
</script>
@endpush
@endif
