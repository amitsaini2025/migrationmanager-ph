@props(['workload' => []])

@php
    $completed = $workload['completed_excl_call'] ?? [];
    $updated = $workload['updated'] ?? [];
    $pending = $workload['pending'] ?? [];
    $callCompleted = $workload['call_completed'] ?? [];
    $callNotes = $workload['contact_today']['call_notes'] ?? [];
    $inPerson = $workload['contact_today']['in_person'] ?? [];

    $pendingExtras = [];
    if (($pending['call'] ?? 0) > 0 || ($pending['other'] ?? 0) > 0) {
        $pendingExtras[] = 'Call: '.($pending['call'] ?? 0).' · Other: '.($pending['other'] ?? 0);
    }
    if (($callNotes['total'] ?? 0) > 0) {
        $noteLine = '📞 '.$callNotes['total'].' call notes today';
        if (($callNotes['new'] ?? 0) > 0 || ($callNotes['returning'] ?? 0) > 0) {
            $noteLine .= ' ('.($callNotes['new'] ?? 0).' new · '.($callNotes['returning'] ?? 0).' returning)';
        }
        $pendingExtras[] = $noteLine;
    }
    if (($inPerson['total'] ?? 0) > 0) {
        $pendingExtras[] = '👤 '.$inPerson['total'].' in-person today';
    }
@endphp

<section class="workload-strip" aria-label="My workload today">
    <div class="workload-strip-header">
        <h2>My Workload — Today</h2>
        <span class="workload-strip-date">{{ $workload['date_label'] ?? '' }} ({{ $workload['timezone'] ?? config('app.timezone') }})</span>
    </div>
    <div class="workload-cards">
        <x-dashboard.workload-card
            title="Completed (excl. Call)"
            metric="completed_excl_call"
            :data="$completed"
            icon="fa-check-circle"
            icon-class="icon-success"
        />
        <x-dashboard.workload-card
            title="Updated"
            metric="updated"
            :data="$updated"
            icon="fa-edit"
            icon-class="icon-active"
        />
        <x-dashboard.workload-card
            title="Pending"
            metric="pending"
            :data="$pending"
            icon="fa-hourglass-half"
            icon-class="icon-pending"
            :route="route('assignee.action')"
            :extra-lines="$pendingExtras"
        />
        <x-dashboard.workload-card
            title="Call completed"
            metric="call_completed"
            :data="$callCompleted"
            icon="fa-phone"
            icon-class="icon-call"
            :route="route('assignee.action_completed', ['group_type' => 'Call'])"
        />
    </div>
    <p class="workload-legend">Clients / leads / personal on each card. New = record created in last {{ config('crm.workload.new_record_days', 14) }} days. Returning = no live contact in {{ config('crm.workload.returning_gap_days', 365) }}+ days.</p>
</section>

<div class="modal fade" id="workloadDrilldownModal" tabindex="-1" role="dialog" aria-labelledby="workloadDrilldownModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="workloadDrilldownModalLabel">Workload details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="workloadDrilldownLoading" class="text-center py-4" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="workloadDrilldownEmpty" class="text-muted py-3" style="display:none;">No items for today.</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" id="workloadDrilldownTable" style="display:none;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Person</th>
                                <th>Type</th>
                                <th>Class</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
