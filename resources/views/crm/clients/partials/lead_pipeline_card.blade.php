@php
    $stageKey = $fetchedData->lead_status ?: 'new';
    $stageLabel = ($leadStageLabels[$stageKey] ?? null) ?: ucfirst(str_replace('_', ' ', (string) $stageKey));
    $assignee = null;
    if (!empty($fetchedData->user_id)) {
        $assignee = $assignableStaff->firstWhere('id', (int) $fetchedData->user_id)
            ?? \App\Models\Staff::find($fetchedData->user_id);
    }
    $followupYmd = $fetchedData->followup_date ? $fetchedData->followup_date->format('Y-m-d') : '';
    $assigneeId = (int) ($fetchedData->user_id ?? 0);
    $assigneeInStaffList = $assigneeId > 0 && $assignableStaff->firstWhere('id', $assigneeId) !== null;
    $orphanAssignee = null;
    if ($assigneeId > 0 && ! $assigneeInStaffList) {
        $orphanAssignee = \App\Models\Staff::find($assigneeId);
    }
@endphp

<style>
    #leadPipelineCard .lead-pipeline-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e9ecef;
    }
    #leadPipelineCard .lead-pipeline-card-header h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        flex: 1;
        min-width: 0;
    }
    #leadPipelineCard .lead-pipeline-open-edit--icon {
        flex-shrink: 0;
        width: 36px;
        height: 36px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #leadPipelineCard .lead-pipeline-edit-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e9ecef;
        font-weight: 600;
        color: #343a40;
    }
</style>

<div class="card" id="leadPipelineCard"
     data-client-id="{{ $fetchedData->id }}"
     data-initial-stage="{{ $stageKey }}"
     data-initial-followup="{{ $followupYmd }}"
     data-initial-assign="{{ $fetchedData->user_id ?? '' }}">

    <div id="leadPipelineView" class="lead-pipeline-view" role="region" aria-label="Lead pipeline summary">
        <div class="lead-pipeline-card-header">
            <h3>@icon('fa-route') Lead pipeline</h3>
            <button type="button"
                    class="btn btn-primary btn-sm lead-pipeline-open-edit lead-pipeline-open-edit--icon"
                    title="Edit pipeline"
                    aria-label="Edit pipeline"
                    aria-expanded="false"
                    aria-controls="leadPipelineEdit">
                @icon('fa-pen', ['aria-hidden' => 'true'])
            </button>
        </div>
        <div class="field-group">
            <span class="field-label">Stage</span>
            <span class="field-value" id="leadPipelineStageDisplay">{{ $stageLabel }}</span>
        </div>
        <div class="field-group">
            <span class="field-label">Record</span>
            <span class="field-value" id="leadPipelineRecordDisplay">{{ (int) ($fetchedData->status ?? 0) === 1 ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="field-group" id="leadPipelineFollowupRow" style="{{ $stageKey === 'follow_up' ? '' : 'display:none;' }}">
            <span class="field-label">Follow-up date</span>
            <span class="field-value" id="leadPipelineFollowupDisplay">
                @if($stageKey === 'follow_up' && $fetchedData->followup_date)
                    {{ $fetchedData->followup_date->format('d M Y') }}
                @else
                    Not set
                @endif
            </span>
        </div>
        <div class="field-group">
            <span class="field-label">Assigned to</span>
            <span class="field-value" id="leadPipelineAssigneeDisplay">
                @if($assignee)
                    {{ trim(($assignee->first_name ?? '') . ' ' . ($assignee->last_name ?? '')) }}
                @else
                    Not assigned
                @endif
            </span>
        </div>
    </div>

    <div id="leadPipelineEdit"
         class="lead-pipeline-edit"
         style="display: none;"
         role="region"
         aria-label="Edit lead pipeline"
         aria-hidden="true">
        <div class="lead-pipeline-edit-banner">
            <span id="leadPipelineEditHeading">@icon('fa-edit', ['class' => 'text-primary me-1'])Editing pipeline</span>
            <button type="button"
                    class="btn btn-link btn-sm p-0 lead-pipeline-open-edit"
                    aria-expanded="false"
                    aria-label="Discard changes and close editor">
                Close
            </button>
        </div>
        <div class="form-group">
            <label for="lead_pipeline_status_detail">Stage</label>
            <select id="lead_pipeline_status_detail" class="form-control">
                @if(! array_key_exists($stageKey, $leadStageLabels))
                    <option value="{{ $stageKey }}" selected>{{ $stageLabel }} (legacy)</option>
                @endif
                @foreach($leadStageLabels as $val => $lbl)
                    <option value="{{ $val }}" {{ $stageKey === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            <div class="text-danger small lead-pipeline-field-error" data-for="lead_status" style="display:none;"></div>
        </div>
        <div class="form-group" id="lead_pipeline_followup_wrap" style="{{ $stageKey === 'follow_up' ? '' : 'display:none;' }}">
            <label for="lead_pipeline_followup_detail">Follow-up date <span class="text-muted">(optional)</span></label>
            <input type="date" id="lead_pipeline_followup_detail" class="form-control" value="{{ $followupYmd }}">
            <div class="text-danger small lead-pipeline-field-error" data-for="followup_date" style="display:none;"></div>
        </div>
        <div class="form-group">
            <label for="lead_pipeline_assign_detail">Assigned to</label>
            <select id="lead_pipeline_assign_detail" class="form-control" aria-describedby="leadPipelineEditHeading">
                <option value="">Not assigned</option>
                @if($assigneeId > 0 && ! $assigneeInStaffList)
                    @if($orphanAssignee)
                        <option value="{{ $orphanAssignee->id }}" selected>
                            {{ trim(($orphanAssignee->first_name ?? '') . ' ' . ($orphanAssignee->last_name ?? '')) }} (not in active list)
                        </option>
                    @else
                        <option value="{{ $assigneeId }}" selected>Unknown assignee — choose another or clear</option>
                    @endif
                @endif
                @foreach($assignableStaff as $st)
                    <option value="{{ $st->id }}" {{ $assigneeInStaffList && (int) ($fetchedData->user_id ?? 0) === (int) $st->id ? 'selected' : '' }}>
                        {{ trim($st->first_name . ' ' . $st->last_name) }}
                    </option>
                @endforeach
            </select>
            <div class="text-danger small lead-pipeline-field-error" data-for="assigned_staff_id" style="display:none;"></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-primary btn-sm" id="leadPipelineSaveBtn">Save</button>
            <button type="button" class="btn btn-secondary btn-sm" id="leadPipelineCancelBtn">Cancel</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var card = document.getElementById('leadPipelineCard');
    if (!card) return;

    var labels = @json($leadStageLabels);
    var viewEl = document.getElementById('leadPipelineView');
    var editEl = document.getElementById('leadPipelineEdit');
    var openEditBtns = card.querySelectorAll('.lead-pipeline-open-edit');
    var stageSel = document.getElementById('lead_pipeline_status_detail');
    var followWrap = document.getElementById('lead_pipeline_followup_wrap');
    var followInput = document.getElementById('lead_pipeline_followup_detail');
    var assignSel = document.getElementById('lead_pipeline_assign_detail');
    var saveBtn = document.getElementById('leadPipelineSaveBtn');
    var cancelBtn = document.getElementById('leadPipelineCancelBtn');
    var stageDisplay = document.getElementById('leadPipelineStageDisplay');
    var followRow = document.getElementById('leadPipelineFollowupRow');
    var followDisplay = document.getElementById('leadPipelineFollowupDisplay');
    var assignDisplay = document.getElementById('leadPipelineAssigneeDisplay');

    function stageLabel(key) {
        return labels[key] || (key || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function toggleFollowUi() {
        var isFu = stageSel && stageSel.value === 'follow_up';
        if (followWrap) followWrap.style.display = isFu ? '' : 'none';
    }

    if (stageSel) {
        stageSel.addEventListener('change', toggleFollowUi);
    }

    function resetEditFromSnapshot() {
        if (!card || !stageSel) return;
        stageSel.value = card.getAttribute('data-initial-stage') || 'new';
        if (followInput) followInput.value = card.getAttribute('data-initial-followup') || '';
        if (assignSel) assignSel.value = card.getAttribute('data-initial-assign') || '';
        toggleFollowUi();
    }

    function setEditMode(on) {
        if (!viewEl || !editEl) return;
        var iconOpenBtn = card.querySelector('.lead-pipeline-open-edit--icon');
        var viewTriggers = card.querySelectorAll('.lead-pipeline-open-edit:not(.btn-link)');
        if (on) {
            resetEditFromSnapshot();
            viewEl.style.display = 'none';
            viewEl.setAttribute('aria-hidden', 'true');
            editEl.style.display = '';
            editEl.setAttribute('aria-hidden', 'false');
            viewTriggers.forEach(function (b) {
                b.setAttribute('aria-expanded', 'true');
            });
            toggleFollowUi();
            if (stageSel) {
                setTimeout(function () { stageSel.focus(); }, 0);
            }
        } else {
            viewEl.style.display = '';
            viewEl.setAttribute('aria-hidden', 'false');
            editEl.style.display = 'none';
            editEl.setAttribute('aria-hidden', 'true');
            viewTriggers.forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
            });
            if (iconOpenBtn) {
                setTimeout(function () { iconOpenBtn.focus(); }, 0);
            }
        }
    }

    openEditBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var open = editEl && editEl.style.display !== 'none';
            if (open) {
                resetEditFromSnapshot();
                setEditMode(false);
            } else {
                setEditMode(true);
            }
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            resetEditFromSnapshot();
            setEditMode(false);
        });
    }

    function clearErrors() {
        document.querySelectorAll('#leadPipelineCard .lead-pipeline-field-error').forEach(function (el) {
            el.style.display = 'none';
            el.textContent = '';
        });
    }

    function showErrors(errors) {
        clearErrors();
        if (!errors) return;
        Object.keys(errors).forEach(function (key) {
            var el = document.querySelector('#leadPipelineCard .lead-pipeline-field-error[data-for="' + key + '"]');
            if (el && errors[key] && errors[key][0]) {
                el.textContent = errors[key][0];
                el.style.display = 'block';
            }
        });
    }

    function monthNames(d) {
        var m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var p = String(d).split('-');
        if (p.length !== 3) return d;
        var day = parseInt(p[2], 10);
        var mon = parseInt(p[1], 10);
        if (isNaN(day) || isNaN(mon) || mon < 1 || mon > 12) return d;
        return day + ' ' + m[mon - 1] + ' ' + p[0];
    }

    function toast(msg, ok) {
        if (typeof iziToast !== 'undefined' && iziToast.show) {
            iziToast.show({
                message: msg,
                color: ok ? 'green' : 'red',
                position: 'topRight',
                timeout: 4000
            });
        } else {
            alert(msg);
        }
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            clearErrors();
            var id = card.getAttribute('data-client-id');
            var token = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var fd = new FormData();
            fd.append('_token', token || '');
            fd.append('id', id);
            fd.append('type', 'lead');
            fd.append('section', 'leadPipeline');
            fd.append('lead_status', stageSel ? stageSel.value : 'new');
            fd.append('followup_date', (stageSel && stageSel.value === 'follow_up' && followInput) ? (followInput.value || '') : '');
            fd.append('assigned_staff_id', assignSel ? (assignSel.value || '') : '');

            saveBtn.disabled = true;
            fetch('{{ url('/clients/save-section') }}', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        try {
                            return { ok: r.ok, status: r.status, data: text ? JSON.parse(text) : {} };
                        } catch (e) {
                            return { ok: false, status: r.status, data: { message: 'Invalid server response' } };
                        }
                    });
                })
                .then(function (res) {
                    saveBtn.disabled = false;
                    if (res.ok && res.data.success) {
                        toast(res.data.message || 'Saved', true);
                        var st = res.data.lead_status || (stageSel && stageSel.value);
                        if (stageDisplay) stageDisplay.textContent = stageLabel(st);
                        if (followRow) followRow.style.display = st === 'follow_up' ? '' : 'none';
                        if (followDisplay) {
                            if (st === 'follow_up' && res.data.followup_date) {
                                followDisplay.textContent = monthNames(res.data.followup_date);
                            } else if (st === 'follow_up') {
                                followDisplay.textContent = 'Not set';
                            } else {
                                followDisplay.textContent = 'Not set';
                            }
                        }
                        if (assignDisplay && assignSel) {
                            var opt = assignSel.options[assignSel.selectedIndex];
                            assignDisplay.textContent = (assignSel.value && opt) ? opt.text.trim() : 'Not assigned';
                        }
                        var recSpan = document.getElementById('leadPipelineRecordDisplay');
                        if (recSpan && typeof res.data.record_status !== 'undefined') {
                            recSpan.textContent = (res.data.record_status === 1) ? 'Active' : 'Inactive';
                        }
                        card.setAttribute('data-initial-stage', res.data.lead_status || '');
                        card.setAttribute('data-initial-followup', res.data.followup_date || '');
                        card.setAttribute('data-initial-assign', res.data.assigned_staff_id != null ? String(res.data.assigned_staff_id) : '');
                        setEditMode(false);
                    } else {
                        if (res.status === 422 && res.data.errors) {
                            showErrors(res.data.errors);
                        }
                        toast((res.data && res.data.message) || 'Save failed', false);
                    }
                })
                .catch(function () {
                    saveBtn.disabled = false;
                    toast('Network error', false);
                });
        });
    }
})();
</script>
@endpush
