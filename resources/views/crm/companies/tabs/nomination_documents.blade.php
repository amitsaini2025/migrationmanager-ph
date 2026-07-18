           <!-- Nomination Documents Tab (Matter-Specific) -->
           <div class="tab-pane" id="nominationdocuments-tab">
                <div class="card full-width documentalls-container">
                    <?php
                    $client_selected_matter_id1 = null;
                    $matter_cnt = \App\Models\ClientMatter::select('id')->where('client_id',$fetchedData->id)->where('matter_status',1)->count();
                    if( $matter_cnt >0 ) {
                        //if client unique reference id is present in url
                        if( isset($id1) && $id1 != "") {
                            // Only resolve by ref if it matches an active matter; a discontinued
                            // matter ref here would make all active-matter documents invisible.
                            $matter_get_id = \App\Models\ClientMatter::select('id')
                                ->where('client_id',$fetchedData->id)
                                ->where('client_unique_matter_no',$id1)
                                ->where('matter_status', 1)
                                ->first();
                            // Fall back to latest active matter if the ref belongs to a discontinued one
                            if (!$matter_get_id) {
                                $matter_get_id = \App\Models\ClientMatter::select('id')
                                    ->where('client_id', $fetchedData->id)
                                    ->where('matter_status', 1)
                                    ->orderBy('id', 'desc')
                                    ->first();
                            }
                        } else {
                            $matter_get_id = \App\Models\ClientMatter::select('id')
                                ->where('client_id', $fetchedData->id)
                                ->where('matter_status', 1)
                                ->orderBy('id', 'desc')
                                ->first();
                        }
                        if($matter_get_id ){
                            $client_selected_matter_id1 = $matter_get_id->id;
                        }
                    }

                    /*$nominationDocCatList = \App\Models\NominationDocumentType::select('id', 'title','client_id','client_matter_id')
                    ->where('status', 1)
                    ->where(function($query) use ($client_selected_matter_id1) {
                        $query->whereNull('client_matter_id')
                            ->orWhere('client_matter_id', (int) $client_selected_matter_id1);
                    })
                    ->orderBy('id', 'ASC')
                    ->get();*/


                    $SelectedClientId = $fetchedData->id;
                    $nominationDocCatList = \App\Models\NominationDocumentType::select('id', 'title', 'client_id', 'client_matter_id')
                        ->where('status', 1)
                        ->where(function($query) use ($SelectedClientId,$client_selected_matter_id1) {
                            $query->where(function($q) {
                                    // 1️⃣ Both client_id and client_matter_id are NULL
                                    $q->whereNull('client_id')
                                    ->whereNull('client_matter_id');
                                })
                                ->orWhere(function($q) use ($SelectedClientId) {
                                    // 2️⃣ client_id matches and client_matter_id is NULL
                                    $q->where('client_id', $SelectedClientId)
                                    ->whereNull('client_matter_id');
                                })
                                ->orWhere(function($q) use ($SelectedClientId, $client_selected_matter_id1) {
                                    // 3️⃣ client_id matches and client_matter_id matches
                                    $q->where('client_id', $SelectedClientId)
                                    ->where('client_matter_id', $client_selected_matter_id1);
                                });
                        })
                        ->orderByRaw("
                            CASE
                                WHEN (client_id IS NULL AND client_matter_id IS NULL) THEN 1
                                WHEN (client_id = ? AND client_matter_id = ?) THEN 2
                                WHEN (client_id = ? AND client_matter_id IS NULL) THEN 3
                                ELSE 4
                            END, id ASC
                        ", [$SelectedClientId, $client_selected_matter_id1, $SelectedClientId])
                        ->get();

                    $firstNominationDocCatId = optional($nominationDocCatList->first())->id;

                    $nominationMatterLmtRow = null;
                    $nominationMatterHasLmt = false;
                    if (($fetchedData->is_company ?? false) && $client_selected_matter_id1) {
                        $nominationMatterLmtRow = \App\Models\ClientMatter::query()
                            ->where('id', $client_selected_matter_id1)
                            ->where('client_id', $fetchedData->id)
                            ->first();
                        if ($nominationMatterLmtRow) {
                            $nominationMatterHasLmt = $nominationMatterLmtRow->lmt_required !== null
                                || ! empty($nominationMatterLmtRow->lmt_start_date)
                                || ! empty($nominationMatterLmtRow->lmt_end_date)
                                || (trim((string) ($nominationMatterLmtRow->lmt_notes ?? '')) !== '')
                                || (trim((string) ($nominationMatterLmtRow->lmt_password ?? '')) !== '');
                        }
                    }
                    $nominationLmtReqAttr = '';
                    if ($nominationMatterLmtRow && $nominationMatterLmtRow->lmt_required !== null) {
                        $nominationLmtReqAttr = $nominationMatterLmtRow->lmt_required ? '1' : '0';
                    }
                    $nominationLmtPopoverHtml = '';
                    if ($nominationMatterHasLmt && $nominationMatterLmtRow) {
                        $parts = [];
                        if ($nominationMatterLmtRow->lmt_required !== null) {
                            $parts[] = '<strong>LMT Required:</strong> ' . ($nominationMatterLmtRow->lmt_required ? 'Yes' : 'No');
                        }
                        if ($nominationMatterLmtRow->lmt_start_date) {
                            $parts[] = '<strong>Start:</strong> ' . e($nominationMatterLmtRow->lmt_start_date->format('d/m/Y'));
                        }
                        if ($nominationMatterLmtRow->lmt_end_date) {
                            $parts[] = '<strong>End:</strong> ' . e($nominationMatterLmtRow->lmt_end_date->format('d/m/Y'));
                        }
                        if (trim((string) ($nominationMatterLmtRow->lmt_notes ?? '')) !== '') {
                            $parts[] = '<strong>Notes:</strong><br>' . nl2br(e($nominationMatterLmtRow->lmt_notes));
                        }
                        if (trim((string) ($nominationMatterLmtRow->lmt_password ?? '')) !== '') {
                            $parts[] = '<strong>Password:</strong> ' . e($nominationMatterLmtRow->lmt_password);
                        }
                        $nominationLmtPopoverHtml = '<div class="text-start small nomination-lmt-popover-body">' . implode('<br>', $parts) . '</div>';
                    }
                    ?>

                    <!-- Visa Documents Content -->
                    <div class="nomination-documents-content" id="nomination-documents-content">
                        <!-- Visa Document Type Subtabs Container -->
                        <div class="subtab-header-container" style="background-color: #4a90e2; padding: 10px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <nav class="subtabs6" style="display: flex; gap: 5px; flex-wrap: wrap; flex: 1;">
                                <?php foreach ($nominationDocCatList as $catVal): ?>
                                    <?php
                                    $id = $catVal->id;
                                    $isActive = ($firstNominationDocCatId && $id == $firstNominationDocCatId) ? 'active' : '';
                                    $folderName = $id;
                                    $isClientGenerated = $catVal->client_matter_id !== null;
                                    ?>
                                    <div style="display: inline-block; position: relative;" class="button-container">
                                        <button class="subtab6-button <?= $isActive ?>" data-subtab6="<?= $id ?>">
                                            <?= htmlspecialchars($catVal->title) ?>
                                        </button>
                                        <?php if ($isClientGenerated): ?>
                                            <div class="action-buttons" style="display: none; position: absolute; top: 0; right: -8px;">
                                                <button class="btn btn-sm btn-warning update-nomination-cat-title" data-id="<?= $id ?>" style="padding: 2px 0px 2px 6px;">@icon('fa-edit', ['aria-hidden' => 'true'])</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </nav>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: flex-end;">
                                    <button type="button" class="btn add-nomination-doc-category-btn add-nomination-doc-category" data-type="nomination" data-categoryid="">
                                        @icon('fa-plus') Add Category
                                    </button>
                                    <!-- Add link to Not Used Documents -->
                                    <button type="button" class="btn btn-secondary client-nav-button client-nav-button--inline" data-tab="notuseddocuments">
                                        @icon('fa-folder-minus') Not Used Documents
                                    </button>
                                </div>
                                @if(($fetchedData->is_company ?? false) && $client_selected_matter_id1)
                                <button type="button"
                                    class="btn btn-sm btn-primary"
                                    id="nominationLmtOpenBtn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#nominationLmtModal"
                                    data-lmt-matter-id="{{ (int) $client_selected_matter_id1 }}"
                                    data-lmt-has-data="{{ $nominationMatterHasLmt ? '1' : '0' }}"
                                    data-lmt-required="{{ $nominationLmtReqAttr }}"
                                    data-lmt-start="{{ ($nominationMatterLmtRow && $nominationMatterLmtRow->lmt_start_date) ? $nominationMatterLmtRow->lmt_start_date->format('Y-m-d') : '' }}"
                                    data-lmt-end="{{ ($nominationMatterLmtRow && $nominationMatterLmtRow->lmt_end_date) ? $nominationMatterLmtRow->lmt_end_date->format('Y-m-d') : '' }}"
                                    onclick="window.setupNominationLmtModal(this)"
                                    style="align-self: flex-end;">
                                    @icon('fa-clipboard-check') Labour Market Test
                                </button>
                                @endif
                            </div>
                        </div>

                        @if(($fetchedData->is_company ?? false) && $client_selected_matter_id1)
                        <script type="application/json" id="nomination-lmt-initial-notes">{!! json_encode(($nominationMatterLmtRow ? $nominationMatterLmtRow->lmt_notes : null) ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                        <script type="application/json" id="nomination-lmt-initial-password">{!! json_encode(($nominationMatterLmtRow ? $nominationMatterLmtRow->lmt_password : null) ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
                        <div id="nomination-lmt-popover-template" style="display:none" aria-hidden="true">{!! $nominationLmtPopoverHtml !!}</div>

                        <div class="modal fade" id="nominationLmtModal" tabindex="-1" role="dialog" aria-labelledby="nominationLmtModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="nominationLmtModalLabel">Labour Market Testing (LMT)</h5>
                                        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="nomination_lmt_required">LMT required</label>
                                            <select id="nomination_lmt_required" class="form-control">
                                                <option value="">Not set</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="nomination_lmt_start_date">Start date</label>
                                            <input type="date" id="nomination_lmt_start_date" class="form-control" value="">
                                        </div>
                                        <div class="form-group">
                                            <label for="nomination_lmt_end_date">End date</label>
                                            <input type="date" id="nomination_lmt_end_date" class="form-control" value="">
                                        </div>
                                        <div class="form-group">
                                            <label for="nomination_lmt_notes">Notes</label>
                                            <textarea id="nomination_lmt_notes" class="nomination-lmt-notes-input" rows="3" placeholder="Optional notes"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="nomination_lmt_password">Password</label>
                                            <input type="text" id="nomination_lmt_password" class="form-control" placeholder="LMT password" autocomplete="off">
                                        </div>
                                        <p class="text-muted small mb-0" id="nominationLmtModalError" style="display:none;color:#dc3545!important;"></p>
                                    </div>
                                    <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                                        <div>
                                            <button type="button" class="btn btn-outline-danger" id="nominationLmtDeleteBtn" style="display:none;" onclick="window.deleteNominationLmtDetail()">
                                                @icon('fa-trash-alt') Delete
                                            </button>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary" id="nominationLmtSaveBtn" onclick="window.submitNominationLmtDetail()">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <style>
                            /* Avoid global textarea.form-control{height:64px!important} so drag-resize works */
                            #nominationLmtModal textarea.nomination-lmt-notes-input {
                                display: block;
                                width: 100%;
                                padding: 0.375rem 0.75rem;
                                font-size: 1rem;
                                font-weight: 400;
                                line-height: 1.5;
                                color: #495057;
                                background-color: #fff;
                                border: 1px solid #ced4da;
                                border-radius: 0.25rem;
                                min-height: 64px;
                                resize: vertical;
                                overflow-y: auto;
                                max-height: 60vh;
                                box-sizing: border-box;
                            }
                            #nominationLmtModal textarea.nomination-lmt-notes-input:focus {
                                color: #495057;
                                background-color: #fff;
                                border-color: #80bdff;
                                outline: 0;
                                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
                            }
                        </style>
                        <script>
                        (function () {
                            window._nominationLmtInitialPassword = '';

                            function nominationLmtMatterId() {
                                var b = document.getElementById('nominationLmtOpenBtn');
                                return b ? String(b.getAttribute('data-lmt-matter-id') || '') : '';
                            }

                            function nominationLmtReadJsonScript(id) {
                                var el = document.getElementById(id);
                                if (!el || !el.textContent) return '';
                                try {
                                    var v = JSON.parse(el.textContent);
                                    return v === null || v === undefined ? '' : String(v);
                                } catch (e) {
                                    return '';
                                }
                            }

                            window.nominationLmtSyncEndDateFromStart = function () {
                                var sd = document.getElementById('nomination_lmt_start_date');
                                var ed = document.getElementById('nomination_lmt_end_date');
                                if (!sd || !ed || !sd.value) return;
                                var parts = sd.value.split('-');
                                if (parts.length !== 3) return;
                                var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
                                if (isNaN(d.getTime())) return;
                                d.setDate(d.getDate() + 28);
                                var y = d.getFullYear();
                                var m = String(d.getMonth() + 1).padStart(2, '0');
                                var day = String(d.getDate()).padStart(2, '0');
                                ed.value = y + '-' + m + '-' + day;
                            };

                            function nominationLmtPasswordChanged() {
                                var pw = document.getElementById('nomination_lmt_password');
                                if (!pw) return false;
                                return pw.value !== (window._nominationLmtInitialPassword || '');
                            }

                            window.setupNominationLmtModal = function (btnEl) {
                                var err = document.getElementById('nominationLmtModalError');
                                if (err) { err.style.display = 'none'; err.textContent = ''; }
                                var btn = btnEl || document.getElementById('nominationLmtOpenBtn');
                                var req = document.getElementById('nomination_lmt_required');
                                var sd = document.getElementById('nomination_lmt_start_date');
                                var ed = document.getElementById('nomination_lmt_end_date');
                                var nt = document.getElementById('nomination_lmt_notes');
                                var pw = document.getElementById('nomination_lmt_password');
                                var del = document.getElementById('nominationLmtDeleteBtn');
                                if (!req || !sd || !ed || !nt || !pw) return;
                                var has = btn && btn.getAttribute('data-lmt-has-data') === '1';
                                if (del) del.style.display = has ? 'inline-block' : 'none';
                                if (has && btn) {
                                    req.value = btn.getAttribute('data-lmt-required') || '';
                                    sd.value = btn.getAttribute('data-lmt-start') || '';
                                    ed.value = btn.getAttribute('data-lmt-end') || '';
                                    nt.value = nominationLmtReadJsonScript('nomination-lmt-initial-notes');
                                    pw.value = nominationLmtReadJsonScript('nomination-lmt-initial-password');
                                } else {
                                    req.value = '';
                                    sd.value = '';
                                    ed.value = '';
                                    nt.value = '';
                                    pw.value = '';
                                }
                                window._nominationLmtInitialPassword = pw.value;
                            };
                            window.deleteNominationLmtDetail = function () {
                                if (!window.confirm('Remove all Labour Market Testing details for this matter?')) return;
                                var delBtn = document.getElementById('nominationLmtDeleteBtn');
                                var token = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                                var fd = new FormData();
                                fd.append('_token', token || '');
                                fd.append('id', String({{ (int) $fetchedData->id }}));
                                fd.append('type', {!! json_encode($fetchedData->type) !!});
                                fd.append('section', 'lmt');
                                fd.append('client_matter_id', nominationLmtMatterId());
                                fd.append('delete_lmt', '1');
                                if (delBtn) delBtn.disabled = true;
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
                                    if (delBtn) delBtn.disabled = false;
                                    if (res.ok && res.data.success) {
                                        if (typeof iziToast !== 'undefined' && iziToast.show) {
                                            iziToast.show({ message: res.data.message || 'Removed', color: 'green', position: 'topRight', timeout: 3500 });
                                        } else { alert(res.data.message || 'Removed'); }
                                        window.location.reload();
                                        return;
                                    }
                                    var msg = (res.data && res.data.message) ? res.data.message : 'Could not delete LMT details.';
                                    if (typeof iziToast !== 'undefined' && iziToast.show) {
                                        iziToast.show({ message: msg, color: 'red', position: 'topRight', timeout: 5000 });
                                    } else { alert(msg); }
                                })
                                .catch(function () {
                                    if (delBtn) delBtn.disabled = false;
                                    alert('Network error. Please try again.');
                                });
                            };
                            window.submitNominationLmtDetail = function () {
                                if (nominationLmtPasswordChanged()) {
                                    if (!window.confirm('Do you want to save/update the password?')) {
                                        return;
                                    }
                                }
                                var err = document.getElementById('nominationLmtModalError');
                                var btn = document.getElementById('nominationLmtSaveBtn');
                                var token = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                                if (err) { err.style.display = 'none'; err.textContent = ''; }
                                var fd = new FormData();
                                fd.append('_token', token || '');
                                fd.append('id', String({{ (int) $fetchedData->id }}));
                                fd.append('type', {!! json_encode($fetchedData->type) !!});
                                fd.append('section', 'lmt');
                                fd.append('client_matter_id', nominationLmtMatterId());
                                fd.append('lmt_required', document.getElementById('nomination_lmt_required').value);
                                fd.append('lmt_start_date', document.getElementById('nomination_lmt_start_date').value || '');
                                fd.append('lmt_end_date', document.getElementById('nomination_lmt_end_date').value || '');
                                fd.append('lmt_notes', document.getElementById('nomination_lmt_notes').value || '');
                                fd.append('lmt_password', document.getElementById('nomination_lmt_password').value || '');
                                if (btn) btn.disabled = true;
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
                                    if (btn) btn.disabled = false;
                                    if (res.ok && res.data.success) {
                                        if (typeof iziToast !== 'undefined' && iziToast.show) {
                                            iziToast.show({ message: res.data.message || 'Saved', color: 'green', position: 'topRight', timeout: 3500 });
                                        } else { alert(res.data.message || 'Saved'); }
                                        var modalEl = document.getElementById('nominationLmtModal');
                                        if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                            var inst = bootstrap.Modal.getInstance(modalEl);
                                            if (inst) inst.hide();
                                        } else if (window.jQuery && window.jQuery(modalEl).modal) {
                                            window.jQuery(modalEl).modal('hide');
                                        }
                                        window.location.reload();
                                        return;
                                    }
                                    var msg = (res.data && res.data.message) ? res.data.message : 'Could not save LMT details.';
                                    if (err) {
                                        err.textContent = msg;
                                        err.style.display = 'block';
                                    } else if (typeof iziToast !== 'undefined' && iziToast.show) {
                                        iziToast.show({ message: msg, color: 'red', position: 'topRight', timeout: 5000 });
                                    } else { alert(msg); }
                                })
                                .catch(function () {
                                    if (btn) btn.disabled = false;
                                    var msg = 'Network error. Please try again.';
                                    if (err) { err.textContent = msg; err.style.display = 'block'; }
                                    else { alert(msg); }
                                });
                            };
                            function initNominationLmtPopover() {
                                var openBtn = document.getElementById('nominationLmtOpenBtn');
                                var tpl = document.getElementById('nomination-lmt-popover-template');
                                if (!openBtn || !tpl || openBtn.getAttribute('data-lmt-has-data') !== '1') return;
                                var html = (tpl.innerHTML || '').trim();
                                if (!html || typeof bootstrap === 'undefined' || !bootstrap.Popover) return;
                                var pop = bootstrap.Popover.getOrCreateInstance(openBtn, {
                                    html: true,
                                    sanitize: false,
                                    trigger: 'manual',
                                    placement: 'bottom',
                                    title: 'Labour Market Testing',
                                    content: html,
                                    container: 'body'
                                });
                                var hideT = null;
                                openBtn.addEventListener('mouseenter', function () {
                                    if (hideT) { clearTimeout(hideT); hideT = null; }
                                    pop.show();
                                });
                                openBtn.addEventListener('mouseleave', function () {
                                    hideT = setTimeout(function () { pop.hide(); }, 200);
                                });
                                openBtn.addEventListener('shown.bs.popover', function () {
                                    var tip = document.querySelector('body > .popover');
                                    if (!tip) return;
                                    tip.addEventListener('mouseenter', function () {
                                        if (hideT) { clearTimeout(hideT); hideT = null; }
                                    });
                                    tip.addEventListener('mouseleave', function () {
                                        pop.hide();
                                    });
                                });
                            }
                            function bindNominationLmtStartDateAutoEnd() {
                                var sd = document.getElementById('nomination_lmt_start_date');
                                if (!sd || sd.getAttribute('data-lmt-end-bound') === '1') return;
                                sd.setAttribute('data-lmt-end-bound', '1');
                                sd.addEventListener('change', window.nominationLmtSyncEndDateFromStart);
                            }
                            if (document.readyState === 'loading') {
                                document.addEventListener('DOMContentLoaded', function () {
                                    initNominationLmtPopover();
                                    bindNominationLmtStartDateAutoEnd();
                                });
                            } else {
                                initNominationLmtPopover();
                                bindNominationLmtStartDateAutoEnd();
                            }
                        })();
                        </script>
                        @endif

                        <!-- Subtab6 Contents -->
                        <div class="subtab6-content">
                            <?php foreach ($nominationDocCatList as $catVal):
                                $id = $catVal->id;
                                $isActive = ($firstNominationDocCatId && $id == $firstNominationDocCatId) ? 'active' : '';
                                $folderName = $id;
                                ?>
                                <div class="subtab6-pane <?= $isActive ?>" id="<?= $id ?>-subtab6">
                                    <div class="checklist-table-container" style="vertical-align: top; margin-top: 10px; width: 760px; overflow: visible;">
                                        <div class="subtab6-header" style="margin-left: 10px;">
                                            <h3>@icon('fa-file-alt') <?= htmlspecialchars($catVal->title) ?> Documents</h3>
                                            <div style="display: flex; gap: 10px;">
<button type="button" class="btn add-checklist-btn add_nomination_doc" data-type="nomination" data-categoryid="<?= $id ?>">
                                                    @icon('fa-plus') Add Checklist
                                                </button>
                                                <button type="button" class="btn btn-info bulk-upload-toggle-btn-nomination" data-categoryid="<?= $id ?>" data-categoryname="<?= htmlspecialchars($catVal->title) ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>">
                                                    @icon('fa-upload') Bulk Upload
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Bulk Upload Dropzone for Visa (Hidden by default) -->
                                        <div class="bulk-upload-dropzone-container-nomination" id="bulk-upload-nomination-<?= $id ?>" style="display: none; margin: 15px 0; padding: 20px; border: 2px dashed #4a90e2; border-radius: 8px; background-color: #f8f9fa;">
                                            <div class="bulk-upload-dropzone-nomination" data-categoryid="<?= $id ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>" style="text-align: center; padding: 30px; cursor: pointer;">
                                                @icon('fa-cloud-upload-alt', ['style' => 'font-size: 48px; color: #2563eb; margin-bottom: 15px;'])
                                                <p style="font-size: 16px; color: #374151; margin-bottom: 10px;">
                                                    <strong>Drag and drop files here</strong> or <strong>click to browse</strong>
                                                </p>
                                                <p style="font-size: 14px; color: #4b5563;">You can select multiple files at once</p>
                                                <input type="file" class="bulk-upload-file-input-nomination" data-categoryid="<?= $id ?>" data-matterid="<?= $client_selected_matter_id1 ?? '' ?>" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                            </div>
                                            <div class="bulk-upload-file-list-nomination" style="display: none; margin-top: 20px;">
                                                <h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count-nomination">0</span></h5>
                                                <div class="bulk-upload-files-container-nomination"></div>
                                            </div>
                                        </div>
                                        <table class="checklist-table">
                                            <thead>
                                                <tr>
                                                    <th>Checklist</th>
                                                    <th>File Name</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody class="tdata migdocumnetlist1 migdocumnetlist_<?= $id ?>">
                                                <?php
                                                 $documents = \App\Models\Document::with('signers')->where('client_id', $fetchedData->id)
                                                    ->whereNull('not_used_doc')
                                                    ->where('doc_type', 'nomination')
                                                    ->where('folder_name', $folderName)
                                                    ->where('type', 'client')
                                                    ->orderBy('created_at', 'DESC')
                                                    ->get();
                                                 $parentDocs = $documents->filter(fn($d) => !str_ends_with($d->checklist ?? '', '_signed'));
                                                 $signedByParent = $documents->filter(fn($d) => str_ends_with($d->checklist ?? '', '_signed'))
                                                    ->groupBy(fn($d) => ($d->folder_name ?? '') . '|' . ($d->client_matter_id ?? '') . '|' . substr($d->checklist ?? '', 0, -7));
                                                 // Keys that still have an active (visible) parent row — signed docs under these are rendered in the main loop
                                                 $parentKeysWithActiveParent = $parentDocs->map(fn($d) => ($d->folder_name ?? '') . '|' . ($d->client_matter_id ?? '') . '|' . ($d->checklist ?? ''))->unique()->values();
                                                 // Signed groups with no active parent (parent moved to not used or deleted) — show signed rows standalone
                                                 $orphanSignedKeys = $signedByParent->keys()->filter(fn($k) => !$parentKeysWithActiveParent->contains($k))->sortBy(fn($k) => $signedByParent->get($k)->min('created_at'));
                                                ?>
                                                <?php foreach ($parentDocs as $visaKey => $fetch): ?>
                                                    <?php
                                                    $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
                                                    $isForm956 = !empty($fetch->form956_id);

                                                    // Build file URL: once a file is uploaded to a Form 956 row, use it directly
                                                    // so the preview shows the actual uploaded (agent-signed) PDF, not the server-regenerated form.
                                                    if ($isForm956 && !empty($fetch->myfile)) {
                                                        $fileUrl = $fetch->myfile;
                                                        $downloadUrl = $fetch->myfile;
                                                    } elseif ($isForm956) {
                                                        $fileUrl = url()->route('forms.preview', $fetch->form956_id);
                                                        $downloadUrl = url()->route('forms.pdf', $fetch->form956_id);
                                                    } elseif (!empty($fetch->myfile) && strpos($fetch->myfile, 'http') === 0) {
                                                        $fileUrl = $fetch->myfile;
                                                        $downloadUrl = $fetch->myfile;
                                                    } else {
                                                        $fileUrl = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/' . $fetchedData->id . '/nomination/' . ($fetch->myfile ?? '');
                                                        $downloadUrl = $fileUrl;
                                                    }
                                                    ?>
                                                    <tr class="drow" data-matterid="<?= $fetch->client_matter_id ?>" data-catid="<?= $fetch->folder_name ?>" id="id_<?= $fetch->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $fetch->id ?>" data-visachecklistname="<?= htmlspecialchars($fetch->checklist) ?>" class="visachecklist-row" title="Uploaded by: <?= htmlspecialchars($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($fetch->checklist) ?></span>
                                                                <div class="checklist-actions" style="display: flex; gap: 5px;">
                                                                    <?php if (!$fetch->file_name): ?>
                                                                    <a href="javascript:;" class="edit-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= htmlspecialchars($fetch->checklist) ?>" title="Edit Checklist Name" style="color: #007bff; cursor: pointer;">
                                                                        @icon('fa-edit')
                                                                    </a>
                                                                    <a href="javascript:;" class="delete-checklist-btn" data-id="<?= $fetch->id ?>" data-checklist="<?= htmlspecialchars($fetch->checklist) ?>" title="Delete Checklist" style="color: #dc3545; cursor: pointer;">
                                                                        @icon('fa-trash')
                                                                    </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php if ($fetch->file_name): ?>
                                                                <?php
                                                                $displayFileName = $fetch->file_name . '.' . ($fetch->filetype ?? '');
                                                                $fileUrlJs = addslashes($fileUrl);
                                                                $downloadUrlJs = addslashes($downloadUrl ?? $fileUrl);
                                                                ?>
                                                                <div data-id="<?= $fetch->id ?>" data-name="<?= htmlspecialchars($fetch->file_name) ?>" class="doc-row" title="Uploaded by: <?= htmlspecialchars($admin->first_name ?? 'NA') ?> on <?= date('d/m/Y H:i', strtotime($fetch->created_at)) ?>" oncontextmenu="showNominationFileContextMenu(event, <?= $fetch->id ?>, '<?= htmlspecialchars($fetch->filetype ?? 'pdf') ?>', '<?= $fileUrlJs ?>', '<?= $id ?>', '<?= $fetch->status ?? 'draft' ?>'); return false;">
                                                                    <a href="javascript:void(0);" onclick="previewFile('<?= $fetch->filetype ?? 'pdf' ?>','<?= $fileUrlJs ?>','preview-container-nomdocumnetlist')">
                                                                        @icon('fa-file-image') <span><?= htmlspecialchars($displayFileName) ?></span>
                                                                    </a>
                                                                </div>
                                                            <?php elseif ($isForm956): ?>
                                                                <div class="form956-download-upload" style="display: flex; flex-direction: column; gap: 10px;" data-download-url="<?= e($downloadUrl) ?>" data-doc-id="<?= $fetch->id ?>">
                                                                    <p class="mb-0" style="font-size: 12px; color: #374151;">Form 956 PDF downloads automatically. Check, update, then upload your completed form below.</p>
                                                                    <div class="migration_upload_document" style="display: inline-block;">
                                                                        <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?= $fetch->id ?>">
                                                                            @csrf
                                                                            <input type="hidden" name="clientid" value="<?= $fetchedData->id ?>">
                                                                            <input type="hidden" name="client_matter_id" value="<?= $fetch->client_matter_id ?? '' ?>">
                                                                            <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                            <input type="hidden" name="type" value="client">
                                                                            <input type="hidden" name="doctype" value="nomination">
                                                                            <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">
                                                                            <div class="document-drag-drop-zone nomination-doc-drag-zone" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" data-formid="mig_upload_form_<?= $fetch->id ?>">
                                                                                <div class="drag-zone-inner">
                                                                                    @icon('fa-cloud-upload-alt')
                                                                                    <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                                </div>
                                                                            </div>
                                                                            <input class="migdocupload d-none" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" type="file" name="document_upload" style="display: none;"/>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="migration_upload_document" style="display: inline-block;">
                                                                    <form method="POST" enctype="multipart/form-data" id="mig_upload_form_<?= $fetch->id ?>">
                                                                        @csrf
                                                                        <input type="hidden" name="clientid" value="<?= $fetchedData->id ?>">
                                                                        <input type="hidden" name="client_matter_id" value="<?= $fetch->client_matter_id ?? '' ?>">
                                                                        <input type="hidden" name="fileid" value="<?= $fetch->id ?>">
                                                                        <input type="hidden" name="type" value="client">
                                                                        <input type="hidden" name="doctype" value="nomination">
                                                                        <input type="hidden" name="doccategory" value="<?= $catVal->title ?>">
                                                                        <div class="document-drag-drop-zone nomination-doc-drag-zone" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" data-formid="mig_upload_form_<?= $fetch->id ?>">
                                                                            <div class="drag-zone-inner">
                                                                                @icon('fa-cloud-upload-alt')
                                                                                <span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span>
                                                                            </div>
                                                                        </div>
                                                                        <input class="migdocupload d-none" data-fileid="<?= $fetch->id ?>" data-doccategory="<?= $id ?>" type="file" name="document_upload" style="display: none;"/>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($fetch->myfile): ?>
                                                                <a class="renamechecklist" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="renamedoc" data-id="<?= $fetch->id ?>" href="javascript:;" style="display: none;"></a>
                                                                <a class="download-file" data-filelink="<?= e($downloadUrl ?? $fileUrl) ?>" data-filename="<?= e($fetch->myfile_key ?: basename($fetch->myfile ?? '')) ?>" data-id="<?= $fetch->id ?>" href="#" style="display: none;"></a>
                                                                <a class="notuseddoc" data-id="<?= $fetch->id ?>" data-doctype="nomination" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                    $docStatus = $fetch->status ?? '';
                                                    $showSigActionBar = in_array($docStatus, ['placed', 'sent']) && $fetch->doc_type === 'nomination' && $fetch->file_name && ($fetch->filetype ?? '') === 'pdf';
                                                    if ($showSigActionBar):
                                                        $signingUrl = null;
                                                        if ($fetch->signature_doc_link) {
                                                            $links = json_decode($fetch->signature_doc_link, true);
                                                            $signingUrl = is_array($links) && isset($links[0]['url']) ? $links[0]['url'] : null;
                                                        }
                                                        $pendingSigner = $fetch->signers()->whereIn('status', ['pending'])->first();
                                                        $signerId = $pendingSigner ? $pendingSigner->id : null;
                                                    ?>
                                                    <tr class="visa-sig-action-bar" data-matterid="<?= $fetch->client_matter_id ?>" data-doc-id="<?= $fetch->id ?>" data-signer-id="<?= $signerId ?>" style="background: #f8f9fa; border-left: 4px solid #4a90e2;">
                                                        <td colspan="3" style="padding: 10px 16px;">
                                                            <div class="d-flex flex-wrap align-items-center gap-2" style="flex-wrap: wrap;">
                                                                <button type="button" class="btn btn-sm btn-primary visa-sig-send-btn" data-doc-id="<?= $fetch->id ?>" <?= $docStatus === 'sent' ? 'disabled' : '' ?>>
                                                                    @icon('fa-paper-plane', ['class' => 'mr-1']) Send
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary visa-sig-revise-btn" data-doc-id="<?= $fetch->id ?>">
                                                                    @icon('fa-edit', ['class' => 'mr-1']) Revise
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-outline-danger visa-sig-remove-btn" data-doc-id="<?= $fetch->id ?>">
                                                                    @icon('fa-times', ['class' => 'mr-1']) Remove
                                                                </button>
                                                                <?php if ($docStatus === 'sent' && $signingUrl && $signerId): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-info visa-sig-reminder-btn" data-doc-id="<?= $fetch->id ?>" data-signer-id="<?= $signerId ?>">
                                                                    @icon('fa-bell', ['class' => 'mr-1']) Reminder
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php
                                                    $signedKey = ($fetch->folder_name ?? '') . '|' . ($fetch->client_matter_id ?? '') . '|' . ($fetch->checklist ?? '');
                                                    $signedDocs = $signedByParent->get($signedKey, collect());
                                                    foreach ($signedDocs as $signedDoc):
                                                        $signedAdmin = \App\Models\Staff::where('id', $signedDoc->user_id)->first();
                                                        $signedIsForm956 = !empty($signedDoc->form956_id);
                                                        if ($signedIsForm956 && !empty($signedDoc->myfile)) {
                                                            $signedFileUrl = $signedDoc->myfile;
                                                            $signedDownloadUrl = $signedDoc->myfile;
                                                        } elseif ($signedIsForm956) {
                                                            $signedFileUrl = url()->route('forms.preview', $signedDoc->form956_id);
                                                            $signedDownloadUrl = url()->route('forms.pdf', $signedDoc->form956_id);
                                                        } else {
                                                            $signedFileUrl = url()->route('documents.preview.signed', $signedDoc->id);
                                                            $signedDownloadUrl = $signedDoc->signed_doc_link ?? $signedDoc->myfile;
                                                        }
                                                        $signedDisplayName = ($signedDoc->file_name ?? 'signed') . '.' . ($signedDoc->filetype ?? 'pdf');
                                                    ?>
                                                    <tr class="drow visa-signed-row" data-matterid="<?= $signedDoc->client_matter_id ?>" data-catid="<?= $signedDoc->folder_name ?>" id="id_<?= $signedDoc->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" class="visachecklist-row" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($signedDoc->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <?php
                                                            $signedFileUrlJs = addslashes($signedFileUrl);
                                                            ?>
                                                            <div data-id="<?= $signedDoc->id ?>" data-name="<?= htmlspecialchars($signedDoc->file_name ?? '') ?>" class="doc-row" title="Signed document" oncontextmenu="showNominationFileContextMenu(event, <?= $signedDoc->id ?>, '<?= htmlspecialchars($signedDoc->filetype ?? 'pdf') ?>', '<?= $signedFileUrlJs ?>', '<?= $id ?>', '<?= $signedDoc->status ?? 'signed' ?>'); return false;">
                                                                <a href="javascript:void(0);" onclick="previewFile('<?= $signedDoc->filetype ?? 'pdf' ?>','<?= $signedFileUrlJs ?>','preview-container-nomdocumnetlist')">
                                                                    @icon('fa-file-image') <span><?= htmlspecialchars($signedDisplayName) ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="renamedoc" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="download-file" data-filelink="<?= e($signedDownloadUrl) ?>" data-filename="<?= e($signedDoc->getSignedDownloadFilename()) ?>" data-id="<?= $signedDoc->id ?>" href="#" style="display: none;"></a>
                                                            <a class="notuseddoc" data-id="<?= $signedDoc->id ?>" data-doctype="nomination" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                                <?php
                                                // Orphan signed docs: parent moved to not used or deleted — show signed row(s) so signed version still displays
                                                foreach ($orphanSignedKeys as $orphanKey):
                                                    $signedDocs = $signedByParent->get($orphanKey, collect());
                                                    foreach ($signedDocs as $signedDoc):
                                                        $signedAdmin = \App\Models\Staff::where('id', $signedDoc->user_id)->first();
                                                        $signedIsForm956 = !empty($signedDoc->form956_id);
                                                        if ($signedIsForm956 && !empty($signedDoc->myfile)) {
                                                            $signedFileUrl = $signedDoc->myfile;
                                                            $signedDownloadUrl = $signedDoc->myfile;
                                                        } elseif ($signedIsForm956) {
                                                            $signedFileUrl = url()->route('forms.preview', $signedDoc->form956_id);
                                                            $signedDownloadUrl = url()->route('forms.pdf', $signedDoc->form956_id);
                                                        } else {
                                                            $signedFileUrl = url()->route('documents.preview.signed', $signedDoc->id);
                                                            $signedDownloadUrl = $signedDoc->signed_doc_link ?? $signedDoc->myfile;
                                                        }
                                                        $signedDisplayName = ($signedDoc->file_name ?? 'signed') . '.' . ($signedDoc->filetype ?? 'pdf');
                                                        $signedFileUrlJs = addslashes($signedFileUrl);
                                                ?>
                                                    <tr class="drow visa-signed-row" data-matterid="<?= $signedDoc->client_matter_id ?>" data-catid="<?= $signedDoc->folder_name ?>" id="id_<?= $signedDoc->id ?>">
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" class="visachecklist-row" style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="flex: 1;"><?= htmlspecialchars($signedDoc->checklist) ?></span>
                                                            </div>
                                                        </td>
                                                        <td style="white-space: initial;">
                                                            <div data-id="<?= $signedDoc->id ?>" data-name="<?= htmlspecialchars($signedDoc->file_name ?? '') ?>" class="doc-row" title="Signed document" oncontextmenu="showNominationFileContextMenu(event, <?= $signedDoc->id ?>, '<?= htmlspecialchars($signedDoc->filetype ?? 'pdf') ?>', '<?= $signedFileUrlJs ?>', '<?= $id ?>', '<?= $signedDoc->status ?? 'signed' ?>'); return false;">
                                                                <a href="javascript:void(0);" onclick="previewFile('<?= $signedDoc->filetype ?? 'pdf' ?>','<?= $signedFileUrlJs ?>','preview-container-nomdocumnetlist')">
                                                                    @icon('fa-file-image') <span><?= htmlspecialchars($signedDisplayName) ?></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a class="renamechecklist" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="renamedoc" data-id="<?= $signedDoc->id ?>" href="javascript:;" style="display: none;"></a>
                                                            <a class="download-file" data-filelink="<?= e($signedDownloadUrl) ?>" data-filename="<?= e($signedDoc->getSignedDownloadFilename()) ?>" data-id="<?= $signedDoc->id ?>" href="#" style="display: none;"></a>
                                                            <a class="notuseddoc" data-id="<?= $signedDoc->id ?>" data-doctype="nomination" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="grid_data nomgriddata" style="display:none;">
                                        <?php foreach ($nominationDocCatList as $catVal):
                                            $id = $catVal->id;
                                            $documents = \App\Models\Document::where('client_id', $fetchedData->id)
                                                ->whereNull('not_used_doc')
                                                ->where('doc_type', 'nomination')
                                                ->where('folder_name', $id)
                                                ->where('type', 'client')
                                                ->orderBy('updated_at', 'DESC')
                                                ->get();
                                            foreach ($documents as $fetch):
                                                if ($fetch->myfile):
                                                    $admin = \App\Models\Staff::where('id', $fetch->user_id)->first();
                                                    ?>
                                                    <div class="grid_list" id="gid_<?= $fetch->id ?>">
                                                        <div class="grid_col">
                                                            <div class="grid_icon">
                                                                @icon('fa-file-image')
                                                            </div>
                                                            <div class="grid_content">
                                                                <span id="grid_<?= $fetch->id ?>" class="gridfilename"><?= htmlspecialchars($fetch->file_name) ?></span>
                                                                <div class="dropdown d-inline dropdown_ellipsis_icon">
                                                                    <a class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">@icon('fa-ellipsis-v')</a>
                                                                    <div class="dropdown-menu">
                                                                        <a target="_blank" class="dropdown-item" href="<?= $fetch->myfile ?>">Preview</a>
                                                                        <a href="#" class="dropdown-item download-file" data-filelink="<?= $fetch->myfile ?>" data-filename="<?= $fetch->myfile_key ?>">Download</a>
                                                                        <a data-id="<?= $fetch->id ?>" class="dropdown-item notuseddoc" data-doctype="nomination" data-href="notuseddoc" href="javascript:;">Not Used</a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <div class="clearfix"></div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="preview-pane file-preview-container preview-container-nomdocumnetlist" style="display: inline;margin-top: 15px !important; width: 499px;">
                                        <p style="color: #374151;">Click on a file to preview it here.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Context Menu for Visa Documents -->
            <div id="nominationFileContextMenu" class="context-menu" style="display: none; position: fixed; background: white; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 10000; min-width: 180px;">
                <div id="nomination-context-send-signature" class="context-menu-item" onclick="handleNominationContextAction('send-for-signature')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; display: none;">
                    @icon('fa-pen-fancy', ['style' => 'margin-right: 8px;']) Send for Signature
                </div>
                <div class="context-menu-item" onclick="handleNominationContextAction('rename-checklist')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-edit', ['style' => 'margin-right: 8px;']) Rename Checklist
                </div>
                <div class="context-menu-item" onclick="handleNominationContextAction('rename-doc')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-file-text', ['style' => 'margin-right: 8px;']) Rename File Name
                </div>
                <div class="context-menu-item" onclick="handleNominationContextAction('move')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-arrows-alt', ['style' => 'margin-right: 8px;']) Move Document
                </div>
                <div class="context-menu-item" onclick="handleNominationContextAction('preview')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-eye', ['style' => 'margin-right: 8px;']) Preview
                </div>
                <div id="nomination-context-pdf-option" class="context-menu-item" onclick="handleNominationContextAction('pdf')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee; display: none;">
                    @icon('fa-file-pdf', ['style' => 'margin-right: 8px;']) PDF
                </div>
                <div class="context-menu-item" onclick="handleNominationContextAction('download')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-download', ['style' => 'margin-right: 8px;']) Download
                </div>
                <div class="context-menu-item" onclick="handleNominationContextAction('not-used')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;">
                    @icon('fa-trash', ['style' => 'margin-right: 8px;']) Not Used
                </div>
            </div>

            <!-- Move Visa Document Modal (shared with personal docs or separate if needed) -->
            <div class="modal fade" id="moveNominationDocumentModal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Move Document</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                <label>Move to:</label>
                                <select id="moveNominationTargetType" class="form-control" style="margin-bottom: 15px;">
                                    <option value="">-- Select Destination --</option>
                                    <option value="personal">Personal Documents</option>
                                    <option value="visa">Visa Documents</option>
                                    <option value="nomination">Nomination Documents</option>
                                </select>
                            </div>
                            
                            <!-- For Personal Documents: Show Categories -->
                            <div class="form-group" id="moveNominationPersonalCategoryContainer" style="display: none;">
                                <label>Select Personal Category:</label>
                                <select id="moveNominationPersonalCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            
                            <!-- For Visa Documents: Show Categories (like Personal Documents) -->
                            <div class="form-group" id="moveNominationVisaCategoryContainer" style="display: none;">
                                <label>Select Visa Category:</label>
                                <select id="moveNominationVisaCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>

                            <div class="form-group" id="moveNominationNominationCategoryContainer" style="display: none;">
                                <label>Select Nomination Category:</label>
                                <select id="moveNominationNominationCategoryId" class="form-control">
                                    <option value="">-- Select Category --</option>
                                </select>
                            </div>
                            
                            <div id="moveNominationDocumentError" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmMoveNominationDocument">Move Document</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                let currentNominationContextFile = null;
                let currentNominationContextData = {};

                function showNominationFileContextMenu(event, fileId, fileType, fileUrl, categoryId, fileStatus) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    currentNominationContextFile = fileId;
                    currentNominationContextData = {
                        fileId: fileId,
                        fileType: fileType,
                        fileUrl: fileUrl,
                        categoryId: categoryId,
                        fileStatus: fileStatus
                    };

                    const menu = document.getElementById('nominationFileContextMenu');
                    
                    // Show/hide PDF option based on file type
                    const pdfOption = document.getElementById('nomination-context-pdf-option');
                    const sendSigOption = document.getElementById('nomination-context-send-signature');
                    const fileExt = fileType.toLowerCase();
                    if (['jpg', 'png', 'jpeg'].includes(fileExt)) {
                        pdfOption.style.display = 'block';
                    } else {
                        pdfOption.style.display = 'none';
                    }
                    // Show "Send for Signature" only for PDF and when not already signed
                    if (fileExt === 'pdf' && fileStatus !== 'signed') {
                        sendSigOption.style.display = 'block';
                    } else {
                        sendSigOption.style.display = 'none';
                    }


                    // Position menu at cursor (position: fixed uses viewport coordinates)
                    const MENU_WIDTH = 180;
                    const MENU_HEIGHT = 350;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const offset = 5;
                    
                    let menuLeft = event.clientX + offset;
                    let menuTop = event.clientY + offset;
                    
                    // Check right edge - if menu would go beyond viewport, show to the left of cursor
                    if (menuLeft + MENU_WIDTH > viewportWidth) {
                        menuLeft = event.clientX - MENU_WIDTH - offset;
                    }
                    
                    // Check bottom edge - if menu would go beyond viewport, show above cursor
                    if (menuTop + MENU_HEIGHT > viewportHeight) {
                        menuTop = event.clientY - MENU_HEIGHT - offset;
                    }
                    
                    // Keep menu inside viewport (left/top edges)
                    menuLeft = Math.max(offset, menuLeft);
                    menuTop = Math.max(offset, menuTop);
                    
                    menu.style.left = menuLeft + 'px';
                    menu.style.top = menuTop + 'px';

                    menu.style.display = 'block';

                    // Hide menu when clicking elsewhere
                    setTimeout(() => {
                        document.addEventListener('click', hideNominationContextMenu);
                    }, 100);
                }

                function hideNominationContextMenu() {
                    const menu = document.getElementById('nominationFileContextMenu');
                    menu.style.display = 'none';
                    document.removeEventListener('click', hideNominationContextMenu);
                }

                function handleNominationContextAction(action) {
                    if (!currentNominationContextFile) return;

                    hideNominationContextMenu();

                    switch(action) {
                        case 'send-for-signature':
                            if (typeof $ !== 'undefined') {
                                $(document).trigger('openSignaturePlacementModal', { documentId: currentNominationContextFile });
                            }
                            break;
                        case 'rename-checklist':
                            $('.renamechecklist[data-id="' + currentNominationContextFile + '"]').click();
                            break;
                        case 'rename-doc':
                            $('.renamedoc[data-id="' + currentNominationContextFile + '"]').click();
                            break;
                        case 'move':
                            openMoveNominationDocumentModal(currentNominationContextFile, 'nomination');
                            break;
                        case 'preview':
                            // Prefer context menu fileUrl (preview route for signed docs; direct URL for unsigned). Fallback to download link for compatibility.
                            var $previewLink = $('.download-file[data-id="' + currentNominationContextFile + '"]').first();
                            var previewUrl = currentNominationContextData.fileUrl || ($previewLink.length ? $previewLink.attr('data-filelink') : null);
                            if (previewUrl) window.open(previewUrl, '_blank');
                            break;
                        case 'pdf':
                            const pdfUrl = '{{ URL::to('/document/download/pdf') }}/' + currentNominationContextFile;
                            window.open(pdfUrl, '_blank');
                            break;
                        case 'download':
                            // Prefer finding by document ID so we use the current link (updated after rename); fallback to filelink match
                            let $downloadBtn = $('.download-file[data-id="' + currentNominationContextFile + '"]');
                            if ($downloadBtn.length === 0) {
                                $downloadBtn = $('.download-file[data-filelink="' + currentNominationContextData.fileUrl + '"]');
                            }
                            if ($downloadBtn.length > 0) {
                                $downloadBtn.first().click();
                            } else {
                                console.error('Download button not found for file ID:', currentNominationContextFile);
                                alert('Download link not found. Please refresh the page and try again.');
                            }
                            break;
                        case 'not-used':
                            $('.notuseddoc[data-id="' + currentNominationContextFile + '"]').click();
                            break;
                    }
                }

                // ============================================================================
                // MOVE VISA DOCUMENT FUNCTIONALITY
                // ============================================================================
                let currentMoveNominationDocumentId = null;
                let currentMoveNominationDocumentType = null;

                function openMoveNominationDocumentModal(documentId, currentType) {
                    currentMoveNominationDocumentId = documentId;
                    currentMoveNominationDocumentType = currentType;
                    
                    // Reset modal
                    $('#moveNominationTargetType').val('');
                    $('#moveNominationPersonalCategoryContainer').hide();
                    $('#moveNominationVisaCategoryContainer').hide();
                    $('#moveNominationNominationCategoryContainer').hide();
                    $('#moveNominationPersonalCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveNominationVisaCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveNominationNominationCategoryId').empty().append('<option value="">-- Select Category --</option>');
                    $('#moveNominationDocumentError').hide();
                    
                    // Show modal
                    $('#moveNominationDocumentModal').modal('show');
                }

                // Handle target type change for visa documents
                $(document).on('change', '#moveNominationTargetType', function() {
                    const targetType = $(this).val();
                    
                    // Hide all containers first
                    $('#moveNominationPersonalCategoryContainer').hide();
                    $('#moveNominationVisaCategoryContainer').hide();
                    $('#moveNominationNominationCategoryContainer').hide();
                    $('#moveNominationDocumentError').hide();
                    
                    if (!targetType) {
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        // Load personal document categories from DOM (like personal tab)
                        const categories = [];
                        $('.subtab2-button').each(function() {
                            const catId = $(this).data('subtab2');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        
                        $('#moveNominationPersonalCategoryId').empty().append('<option value="">-- Select Category --</option>');
                        if (categories.length > 0) {
                            categories.forEach(cat => {
                                $('#moveNominationPersonalCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                            });
                        } else {
                            $('#moveNominationPersonalCategoryId').append('<option value="">No categories found</option>');
                        }
                        $('#moveNominationPersonalCategoryContainer').show();
                        
                    } else if (targetType === 'visa') {
                        const categories = [];
                        $('#visadocuments-tab .subtab6-button').each(function() {
                            const catId = $(this).data('subtab6');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        const finishVisaCats = function(cats) {
                            $('#moveNominationVisaCategoryId').empty().append('<option value="">-- Select Category --</option>');
                            if (cats.length > 0) {
                                cats.forEach(cat => {
                                    $('#moveNominationVisaCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                                });
                            } else {
                                $('#moveNominationVisaCategoryId').append('<option value="">No categories found</option>');
                            }
                            $('#moveNominationVisaCategoryContainer').show();
                        };
                        if (categories.length > 0) {
                            finishVisaCats(categories);
                        } else if (typeof window.ClientDetailConfig !== 'undefined' && window.ClientDetailConfig.clientId) {
                            const matterId = $('#sel_matter_id_client_detail').val() || '';
                            $.get('{{ URL::to('/get-visa-categories') }}', {
                                client_id: window.ClientDetailConfig.clientId,
                                matter_id: matterId
                            }).done(function(data) {
                                const cats = (data || []).map(function(c) {
                                    return { id: c.id, title: c.title };
                                });
                                finishVisaCats(cats);
                            }).fail(function() {
                                finishVisaCats([]);
                            });
                        } else {
                            finishVisaCats([]);
                        }
                    } else if (targetType === 'nomination') {
                        const categories = [];
                        $('#nominationdocuments-tab .subtab6-button').each(function() {
                            const catId = $(this).data('subtab6');
                            const catTitle = $(this).text().trim();
                            if (catId && catTitle) {
                                categories.push({ id: catId, title: catTitle });
                            }
                        });
                        $('#moveNominationNominationCategoryId').empty().append('<option value="">-- Select Category --</option>');
                        if (categories.length > 0) {
                            categories.forEach(cat => {
                                $('#moveNominationNominationCategoryId').append(`<option value="${cat.id}">${cat.title}</option>`);
                            });
                        } else {
                            $('#moveNominationNominationCategoryId').append('<option value="">No categories found</option>');
                        }
                        $('#moveNominationNominationCategoryContainer').show();
                    }
                });

                // --- Visa Signature Action Bar: Send, Revise, Remove, Reminder ---
                $(document).on('click', '.visa-sig-send-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var docId = $(this).data('doc-id');
                    if (!docId) return;
                    var $btn = $(this);
                    var sendLabel = (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-paper-plane') : '<i class="fas fa-paper-plane"></i>') + ' Send';
                    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Sending...');
                    $.ajax({
                        url: '{{ url("/signatures") }}/' + docId + '/send',
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}' },
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .done(function(resp) {
                            if (resp && resp.success === false) {
                                var errMsg = resp.message || 'Failed to send';
                                if (typeof iziToast !== 'undefined' && iziToast.show) {
                                    iziToast.show({ message: errMsg, color: 'red', position: 'topRight', timeout: 5000 });
                                } else { alert(errMsg); }
                                $btn.prop('disabled', false).html(sendLabel);
                                return;
                            }
                            var okMsg = (resp && resp.message) ? resp.message : 'Document sent for signature successfully.';
                            if (typeof iziToast !== 'undefined' && iziToast.show) {
                                iziToast.show({ message: okMsg, color: 'green', position: 'topRight', timeout: 4000 });
                            } else { alert(okMsg); }
                            $btn.prop('disabled', true).html(sendLabel);
                        })
                        .fail(function(xhr) {
                            var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to send';
                            if (typeof iziToast !== 'undefined' && iziToast.show) {
                                iziToast.show({ message: errMsg, color: 'red', position: 'topRight', timeout: 5000 });
                            } else { alert(errMsg); }
                            $btn.prop('disabled', false).html(sendLabel);
                        });
                });
                $(document).on('click', '.visa-sig-revise-btn', function() {
                    var docId = $(this).data('doc-id');
                    if (docId) $(document).trigger('openSignaturePlacementModal', { documentId: docId });
                });
                $(document).on('click', '.visa-sig-remove-btn', function() {
                    if (!confirm('Remove signature request? The client will no longer be able to sign this document.')) return;
                    var $bar = $(this).closest('.visa-sig-action-bar');
                    var docId = $bar.data('doc-id');
                    var signerId = $bar.data('signer-id');
                    if (!docId || !signerId) { alert('Unable to remove.'); return; }
                    $.post('{{ url("/signatures") }}/' + docId + '/cancel', { _token: '{{ csrf_token() }}', signer_id: signerId })
                        .done(function() { location.reload(); })
                        .fail(function(xhr) { alert(xhr.responseJSON?.message || 'Failed to remove'); });
                });
                $(document).on('click', '.visa-sig-reminder-btn', function() {
                    var docId = $(this).data('doc-id');
                    var signerId = $(this).data('signer-id');
                    if (!docId || !signerId) return;
                    $.post('{{ url("/signatures") }}/' + docId + '/reminder', { _token: '{{ csrf_token() }}', signer_id: signerId })
                        .done(function() { alert('Reminder sent.'); location.reload(); })
                        .fail(function(xhr) { alert(xhr.responseJSON?.message || 'Failed to send reminder'); });
                });

                // Handle move visa document confirmation
                $(document).on('click', '#confirmMoveNominationDocument', function() {
                    const targetType = $('#moveNominationTargetType').val();
                    let targetId = null;
                    const $error = $('#moveNominationDocumentError');
                    const $btn = $(this);
                    
                    // Validate based on target type
                    if (!targetType) {
                        $error.text('Please select a destination type').show();
                        return;
                    }
                    
                    if (targetType === 'personal') {
                        targetId = $('#moveNominationPersonalCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a personal category').show();
                            return;
                        }
                    } else if (targetType === 'visa') {
                        targetId = $('#moveNominationVisaCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a visa category').show();
                            return;
                        }
                    } else if (targetType === 'nomination') {
                        targetId = $('#moveNominationNominationCategoryId').val();
                        if (!targetId) {
                            $error.text('Please select a nomination category').show();
                            return;
                        }
                    }
                    
                    // Disable button and show loading
                    $btn.prop('disabled', true).text('Moving...');
                    $error.hide();
                    
                    // Make AJAX request
                    $.ajax({
                        url: '{{ URL::to('/documents/move') }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            document_id: currentMoveNominationDocumentId,
                            target_type: targetType,
                            target_id: targetId
                        },
                        success: function(response) {
                            if (response.status) {
                                // Close modal
                                $('#moveNominationDocumentModal').modal('hide');
                                
                                // Show success message using alert
                                alert(response.message || 'Document moved successfully');
                                
                                // Reload the page to refresh document lists
                                location.reload();
                            } else {
                                $error.text(response.message || 'Failed to move document').show();
                                $btn.prop('disabled', false).text('Move Document');
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'An error occurred while moving the document';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            $error.text(errorMsg).show();
                            $btn.prop('disabled', false).text('Move Document');
                        }
                    });
                });

                // Reset button state when modal is closed
                $('#moveNominationDocumentModal').on('hidden.bs.modal', function() {
                    $('#confirmMoveNominationDocument').prop('disabled', false).text('Move Document');
                });

                // Hide context menu on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideNominationContextMenu();
                    }
                });

                // Form 956: Auto-download PDF when visa documents tab is shown (downloads once per URL per page load)
                // Exposed as a global so sidebar-tabs.js can call it directly (stopImmediatePropagation blocks delegated click events)
                window.autoDownloadForm956Pdfs = function() {
                    var $containers = $('#nominationdocuments-tab .form956-download-upload[data-download-url]').filter(':visible');
                    if ($containers.length === 0) return;
                    var seenUrls = {};
                    var downloadIdx = 0;
                    $containers.each(function() {
                        var url = $(this).data('download-url');
                        var docId = $(this).data('doc-id');
                        var lsKey = 'form956_dl_' + docId;
                        // Skip if already downloaded in a previous session or this session
                        if (!url || seenUrls[url]) return;
                        if (docId && localStorage.getItem(lsKey)) return;
                        seenUrls[url] = true;
                        var $el = $(this);
                        var idx = downloadIdx++;
                        (function(u, key, $container) {
                            setTimeout(function() {
                                fetch(u, { credentials: 'same-origin' })
                                    .then(function(r) { return r.blob(); })
                                    .then(function(blob) {
                                        var a = document.createElement('a');
                                        a.href = URL.createObjectURL(blob);
                                        a.download = 'Form956.pdf';
                                        document.body.appendChild(a);
                                        a.click();
                                        document.body.removeChild(a);
                                        URL.revokeObjectURL(a.href);
                                        if (key) localStorage.setItem(key, '1');
                                    })
                                    .catch(function() {
                                        window.open(u, '_blank');
                                        if (key) localStorage.setItem(key, '1');
                                    });
                            }, idx * 800);
                        })(url, lsKey, $el);
                    });
                };
            </script>

            <script>
                // ============================================================================
                // VISA DOCUMENTS - DRAG AND DROP INITIALIZATION
                // ============================================================================
                console.log('🚀 Nomination Documents Tab Script Loading...');
                
                function initNominationDocDragDrop() {
                    console.log('🔄 Initializing Nomination Doc Drag & Drop...');
                    console.log('📊 Drop zones found:', $('.nomination-doc-drag-zone').length);
                    console.log('📊 Visible drop zones:', $('.nomination-doc-drag-zone:visible').length);
                    
                    // Check each drop zone
                    $('.nomination-doc-drag-zone').each(function(index) {
                        var $zone = $(this);
                        var fileid = $zone.data('fileid');
                        var formid = $zone.data('formid');
                        var isVisible = $zone.is(':visible');
                        console.log('🔍 Drop zone #' + index + ':', {
                            fileid: fileid,
                            formid: formid,
                            visible: isVisible,
                            hasFileInput: $('#' + formid).find('.migdocupload').length > 0
                        });
                    });
                    
                    // IMPORTANT: Remove ALL handlers (including those from detail-main.js)
                    $('.nomination-doc-drag-zone').off('click');
                    $('.nomination-doc-drag-zone').off('dragenter');
                    $('.nomination-doc-drag-zone').off('dragover');
                    $('.nomination-doc-drag-zone').off('dragleave');
                    $('.nomination-doc-drag-zone').off('drop');
                    
                    // Also remove delegated event handlers
                    $(document).off('click', '.nomination-doc-drag-zone');
                    $(document).off('dragenter', '.nomination-doc-drag-zone');
                    $(document).off('dragover', '.nomination-doc-drag-zone');
                    $(document).off('dragleave', '.nomination-doc-drag-zone');
                    $(document).off('drop', '.nomination-doc-drag-zone');
                    
                    // Attach handlers DIRECTLY to each drop zone element
                    $('.nomination-doc-drag-zone').each(function() {
                        var $zone = $(this);
                        
                        // Click handler
                        $zone.on('click', function(e) {
                            console.log('🎯 DIRECT CLICK HANDLER - nomination-doc-drag-zone clicked');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            
                            var fileid = $(this).data('fileid');
                            var formid = $(this).data('formid');
                            console.log('📂 File ID:', fileid, 'Form ID:', formid);
                            
                            var fileInput = $('#' + formid).find('.migdocupload');
                            console.log('📁 File input found:', fileInput.length > 0);
                            
                            if (fileInput.length > 0) {
                                console.log('✅ Triggering file input click...');
                                fileInput[0].click();
                            } else {
                                console.error('❌ File input not found for fileid:', fileid);
                            }
                            
                            return false;
                        });
                        
                        // Dragenter handler
                        $zone.on('dragenter', function(e) {
                            console.log('🔥 DIRECT DRAGENTER HANDLER (VISA)');
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        // Dragover handler
                        $zone.on('dragover', function(e) {
                            console.log('🔥 DIRECT DRAGOVER HANDLER (VISA)');
                            var event = e.originalEvent || e;
                            event.preventDefault();
                            event.stopPropagation();
                            
                            if (event.dataTransfer) {
                                event.dataTransfer.dropEffect = 'copy';
                            }
                            
                            $(this).addClass('drag_over');
                            return false;
                        });
                        
                        // Dragleave handler
                        $zone.on('dragleave', function(e) {
                            console.log('⚠️ DIRECT DRAGLEAVE HANDLER (VISA)');
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = this.getBoundingClientRect();
                            var x = e.originalEvent.clientX;
                            var y = e.originalEvent.clientY;
                            
                            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                                $(this).removeClass('drag_over');
                            }
                            return false;
                        });
                        
                        // Drop handler
                        $zone.on('drop', function(e) {
                            console.log('🎯 DIRECT DROP HANDLER (VISA)');
                            var event = e.originalEvent || e;
                            event.preventDefault();
                            event.stopPropagation();
                            event.stopImmediatePropagation();
                            
                            $(this).removeClass('drag_over');
                            
                            var files = event.dataTransfer ? event.dataTransfer.files : null;
                            if (files && files.length > 0) {
                                console.log('📄 File dropped:', files[0].name);
                                
                                var fileid = $(this).data('fileid');
                                var formid = $(this).data('formid');
                                var fileInput = $('#' + formid).find('.migdocupload')[0];
                                
                                if (fileInput) {
                                    try {
                                        var dataTransfer = new DataTransfer();
                                        dataTransfer.items.add(files[0]);
                                        fileInput.files = dataTransfer.files;
                                        console.log('✅ File assigned using DataTransfer');
                                    } catch(err) {
                                        console.warn('⚠️ Fallback to direct assignment');
                                        try {
                                            fileInput.files = files;
                                        } catch(err2) {
                                            console.error('❌ Could not assign file:', err2);
                                        }
                                    }
                                    
                                    $(fileInput).trigger('change');
                                    console.log('✅ Change event triggered');
                                } else {
                                    console.error('❌ File input not found');
                                }
                            }
                            return false;
                        });
                    });
                    
                    // Prevent default drag behavior on document
                    $(document).off('dragover.visadoc').on('dragover.visadoc', function(e) {
                        if ($(e.target).closest('.nomination-doc-drag-zone').length > 0) {
                            return;
                        }
                        e.preventDefault();
                    });
                    
                    $(document).off('drop.visadoc').on('drop.visadoc', function(e) {
                        if ($(e.target).closest('.nomination-doc-drag-zone').length > 0) {
                            return;
                        }
                        e.preventDefault();
                    });
                    
                    console.log('✅ Visa doc drag-drop handlers attached');
                }
                
                // Initialize on DOM ready
                $(document).ready(function() {
                    console.log('✅ Visa Documents DOM Ready');
                    initNominationDocDragDrop();
                });
                
                // Re-initialize when Visa Documents tab is shown
                $(document).on('click', '.client-nav-button[data-tab="nominationdocuments"]', function() {
                    console.log('📂 Visa Documents tab clicked, reinitializing...');
                    setTimeout(function() {
                        initNominationDocDragDrop();
                    }, 200);
                });
                
                // Also check if tab is already active (e.g., direct URL navigation)
                if ($('#nominationdocuments-tab').hasClass('active')) {
                    console.log('📂 Visa Documents tab already active on load');
                    setTimeout(function() {
                        initNominationDocDragDrop();
                    }, 500);
                }
                
                // ============================================================================
                // VISA BULK UPLOAD FUNCTIONALITY
                // ============================================================================
                
                let bulkUploadNominationFiles = {};
                let currentVisaCategoryId = null;
                let currentVisaMatterId = <?= $client_selected_matter_id1 ?? 'null' ?>;
                let currentNominationClientId = <?= $fetchedData->id ?>;
                
                // Toggle bulk upload dropzone for visa
                $(document).on('click', '.bulk-upload-toggle-btn-nomination', function() {
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const dropzoneContainer = $('#bulk-upload-nomination-' + categoryId);
                    
                    // Hide all other dropzones first
                    $('.bulk-upload-dropzone-container-nomination').not('#bulk-upload-nomination-' + categoryId).slideUp();
                    $('.bulk-upload-toggle-btn-nomination').not(this).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-upload') : '<i class="fas fa-upload"></i>') + ' Bulk Upload');
                    
                    if (dropzoneContainer.is(':visible')) {
                        dropzoneContainer.slideUp();
                        $(this).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-upload') : '<i class="fas fa-upload"></i>') + ' Bulk Upload');
                        // Clear files if closing
                        bulkUploadNominationFiles[categoryId] = [];
                        dropzoneContainer.find('.bulk-upload-file-list-nomination').hide();
                        dropzoneContainer.find('.bulk-upload-files-container-nomination').empty();
                        dropzoneContainer.find('.file-count-nomination').text('0');
                    } else {
                        dropzoneContainer.slideDown();
                        $(this).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-times') : '<i class="fas fa-times"></i>') + ' Close');
                        currentVisaCategoryId = categoryId;
                        currentVisaMatterId = matterId || null;
                    }
                });
                
                // Initialize bulk upload files array for each visa category
                $('.bulk-upload-dropzone-nomination').each(function() {
                    const categoryId = $(this).data('categoryid');
                    if (!bulkUploadNominationFiles[categoryId]) {
                        bulkUploadNominationFiles[categoryId] = [];
                    }
                });
                
                // Click to browse files for visa
                $(document).on('click', '.bulk-upload-dropzone-nomination', function(e) {
                    if (!$(e.target).is('input')) {
                        const categoryId = $(this).data('categoryid');
                        $(this).closest('.bulk-upload-dropzone-container-nomination').find('.bulk-upload-file-input-nomination[data-categoryid="' + categoryId + '"]').click();
                    }
                });
                
                // File input change for visa
                $(document).on('change', '.bulk-upload-file-input-nomination', function() {
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const files = this.files;
                    
                    if (files.length > 0) {
                        handleBulkVisaFilesSelected(categoryId, matterId, files);
                    }
                });
                
                // Attach DIRECT handlers to visa bulk upload dropzones for highest priority
                function initVisaBulkUploadDragDrop() {
                    console.log('🔄 Initializing Visa Bulk Upload Drag & Drop...');
                    console.log('📊 Visa bulk upload zones found:', $('.bulk-upload-dropzone-nomination').length);
                    
                    $('.bulk-upload-dropzone-nomination').each(function() {
                        var $zone = $(this);
                        var elem = this;
                        
                        // Remove old native listeners if they exist
                        if (elem._visaBulkDragOver) {
                            elem.removeEventListener('dragover', elem._visaBulkDragOver);
                        }
                        if (elem._visaBulkDrop) {
                            elem.removeEventListener('drop', elem._visaBulkDrop);
                        }
                        if (elem._visaBulkDragEnter) {
                            elem.removeEventListener('dragenter', elem._visaBulkDragEnter);
                        }
                        if (elem._visaBulkDragLeave) {
                            elem.removeEventListener('dragleave', elem._visaBulkDragLeave);
                        }
                        
                        // Dragover handler (REQUIRED for drop to work)
                        elem._visaBulkDragOver = function(e) {
                            console.log('🔥 NATIVE VISA BULK DRAGOVER');
                            e.preventDefault();
                            e.stopPropagation();
                            e.dataTransfer.dropEffect = 'copy';
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragover', elem._visaBulkDragOver);
                        
                        // Dragenter handler
                        elem._visaBulkDragEnter = function(e) {
                            console.log('🔥 NATIVE VISA BULK DRAGENTER');
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.addClass('drag_over');
                        };
                        elem.addEventListener('dragenter', elem._visaBulkDragEnter);
                        
                        // Dragleave handler
                        elem._visaBulkDragLeave = function(e) {
                            console.log('⚠️ NATIVE VISA BULK DRAGLEAVE');
                            e.preventDefault();
                            e.stopPropagation();
                            
                            var rect = elem.getBoundingClientRect();
                            if (e.clientX <= rect.left || e.clientX >= rect.right || 
                                e.clientY <= rect.top || e.clientY >= rect.bottom) {
                                $zone.removeClass('drag_over');
                            }
                        };
                        elem.addEventListener('dragleave', elem._visaBulkDragLeave);
                        
                        // Drop handler
                        elem._visaBulkDrop = function(e) {
                            console.log('🎯 NATIVE VISA BULK DROP');
                            e.preventDefault();
                            e.stopPropagation();
                            $zone.removeClass('drag_over');
                            
                            var files = e.dataTransfer ? e.dataTransfer.files : null;
                            console.log('📄 Visa files dropped:', files ? files.length : 0);
                            
                            if (files && files.length > 0) {
                                var categoryId = $zone.data('categoryid');
                                var matterId = $zone.data('matterid');
                                console.log('📂 Category ID:', categoryId, 'Matter ID:', matterId);
                                handleBulkVisaFilesSelected(categoryId, matterId, files);
                            } else {
                                console.error('❌ No files in visa drop event');
                            }
                        };
                        elem.addEventListener('drop', elem._visaBulkDrop);
                        
                        console.log('✅ Attached native handlers to visa bulk dropzone:', $zone.data('categoryid'));
                    });
                }
                
                // Initialize visa bulk upload drag-drop when container becomes visible
                $(document).on('click', '.bulk-upload-toggle-btn-nomination', function() {
                    setTimeout(function() {
                        initVisaBulkUploadDragDrop();
                    }, 300); // Wait for slideDown animation
                });
                
                // Also initialize on DOM ready for any visible dropzones
                $(document).ready(function() {
                    initVisaBulkUploadDragDrop();
                });
                
                // Keep delegated handlers as fallback
                $(document).on('dragover', '.bulk-upload-dropzone-nomination', function(e) {
                    console.log('🔥 DELEGATED VISA BULK DRAGOVER');
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    if (e.originalEvent && e.originalEvent.dataTransfer) {
                        e.originalEvent.dataTransfer.dropEffect = 'copy';
                    }
                    return false;
                });
                
                $(document).on('dragenter', '.bulk-upload-dropzone-nomination', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag_over');
                    return false;
                });
                
                $(document).on('dragleave', '.bulk-upload-dropzone-nomination', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var rect = this.getBoundingClientRect();
                    var x = e.originalEvent.clientX;
                    var y = e.originalEvent.clientY;
                    if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                        $(this).removeClass('drag_over');
                    }
                    return false;
                });
                
                $(document).on('drop', '.bulk-upload-dropzone-nomination', function(e) {
                    console.log('🎯 DELEGATED VISA BULK DROP');
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag_over');
                    
                    const categoryId = $(this).data('categoryid');
                    const matterId = $(this).data('matterid');
                    const files = e.originalEvent && e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                    
                    console.log('📄 Visa files dropped:', files ? files.length : 0);
                    
                    if (files && files.length > 0) {
                        handleBulkVisaFilesSelected(categoryId, matterId, files);
                    } else {
                        console.error('❌ No files in visa drop event');
                    }
                    return false;
                });
                
                // Handle visa files selected
                function handleBulkVisaFilesSelected(categoryId, matterId, files) {
                    if (!bulkUploadNominationFiles[categoryId]) {
                        bulkUploadNominationFiles[categoryId] = [];
                    }
                    
                    // Validate and add files to array
                    const invalidFiles = [];
                    const maxSize = 50 * 1024 * 1024; // 50MB
                    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    
                    Array.from(files).forEach(file => {
                        // Check file size
                        if (file.size > maxSize) {
                            invalidFiles.push(file.name + ' (exceeds 50MB)');
                            return;
                        }
                        
                        // Check file extension
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (!allowedExtensions.includes(ext)) {
                            invalidFiles.push(file.name + ' (invalid file type)');
                            return;
                        }

                        // Check filename characters (same rules as single upload; WAF-safe name applied on POST)
                        if (typeof mmIsAllowedDocumentFilename === 'function' ? !mmIsAllowedDocumentFilename(file.name) : !/^[a-zA-Z0-9_\-\.\s\$\(\),&+']+$/.test(file.name)) {
                            invalidFiles.push(file.name + ' (invalid characters in name)');
                            return;
                        }
                        
                        // Check if file already exists
                        const exists = bulkUploadNominationFiles[categoryId].some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            bulkUploadNominationFiles[categoryId].push(file);
                        }
                    });
                    
                    if (invalidFiles.length > 0) {
                        alert('The following files were skipped:\n' + invalidFiles.join('\n'));
                    }
                    
                    if (bulkUploadNominationFiles[categoryId].length === 0) {
                        alert('No valid files selected. Please select PDF, JPG, PNG, DOC, or DOCX files under 50MB.');
                        return;
                    }
                    
                    // Show file list
                    const container = $('#bulk-upload-nomination-' + categoryId);
                    container.find('.bulk-upload-file-list-nomination').show();
                    container.find('.file-count-nomination').text(bulkUploadNominationFiles[categoryId].length);
                    
                    // Show mapping interface
                    showBulkVisaUploadMapping(categoryId, matterId);
                }
                
                // Show visa mapping interface
                function showBulkVisaUploadMapping(categoryId, matterId) {
                    currentVisaCategoryId = categoryId;
                    currentVisaMatterId = matterId || null;
                    const files = bulkUploadNominationFiles[categoryId];
                    
                    if (files.length === 0) {
                        return;
                    }
                    
                    // Get existing checklists for this visa category
                    getExistingVisaChecklists(categoryId, function(checklists) {
                        // Call backend to get auto-matches
                        getAutoVisaChecklistMatches(categoryId, files, checklists, function(matches) {
                            displayVisaMappingInterface(files, checklists, matches);
                        });
                    });
                }
                
                // Get existing visa checklists
                function getExistingVisaChecklists(categoryId, callback) {
                    const checklists = [];
                    const checklistNames = new Set();
                    
                    $('.migdocumnetlist_' + categoryId + ' .visachecklist-row').each(function() {
                        const checklistName = $(this).data('visachecklistname');
                        const checklistId = $(this).closest('tr').attr('id').replace('id_', '');
                        
                        if (checklistName && !checklistNames.has(checklistName)) {
                            checklistNames.add(checklistName);
                            checklists.push({
                                id: checklistId,
                                name: checklistName
                            });
                        }
                    });
                    
                    callback(checklists);
                }
                
                // Get auto-checklist matches for visa from backend
                function getAutoVisaChecklistMatches(categoryId, files, checklists, callback) {
                    const fileData = Array.from(files).map(file => ({
                        name: file.name,
                        size: file.size,
                        type: file.type
                    }));
                    
                    const checklistNames = checklists.map(c => c.name);
                    
                    $.ajax({
                        url: '{{ route("clients.documents.getAutoChecklistMatches") }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            clientid: currentNominationClientId,
                            categoryid: categoryId,
                            files: fileData,
                            checklists: checklistNames
                        },
                        success: function(response) {
                            if (response.status) {
                                callback(response.matches || {});
                            } else {
                                callback({});
                            }
                        },
                        error: function() {
                            callback({});
                        }
                    });
                }
                
                // Display visa mapping interface (reuse the same modal)
                function displayVisaMappingInterface(files, checklists, matches) {
                    const modal = $('#bulk-upload-mapping-modal');
                    const tableContainer = $('#bulk-upload-mapping-table');
                    
                    let html = '<table class="table table-bordered" style="width: 100%;">';
                    html += '<thead><tr><th style="width: 25%;">File Name</th><th style="width: 45%;">Checklist Assignment</th><th style="width: 20%;">Status</th><th style="width: 10%; text-align: center;">Actions</th></tr></thead>';
                    html += '<tbody>';
                    
                    Array.from(files).forEach((file, index) => {
                        const fileName = file.name;
                        const fileSize = formatFileSize(file.size);
                        const match = matches[fileName] || null;
                        
                        let selectedChecklist = '';
                        let statusClass = 'manual';
                        let statusText = 'Manual selection';
                        
                        if (match && match.checklist) {
                            selectedChecklist = match.checklist;
                            statusClass = match.confidence === 'high' ? 'auto-matched' : 'manual';
                            statusText = match.confidence === 'high' ? 'Auto-matched' : 'Suggested';
                        }
                        
                        html += '<tr class="bulk-upload-file-item" data-file-index="' + index + '" data-file-name="' + escapeHtml(fileName) + '">';
                        html += '<td>';
                        html += '<div class="file-info">';
                        html += (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-file') : '<i class="fas fa-file"></i>');
                        html += '<div>';
                        html += '<div class="file-name">' + escapeHtml(fileName) + '</div>';
                        html += '<div class="file-size">' + fileSize + '</div>';
                        html += '</div>';
                        html += '</div>';
                        html += '</td>';
                        html += '<td>';
                        html += '<select class="form-control checklist-select" data-file-index="' + index + '" data-file-name="' + escapeHtml(fileName) + '">';
                        html += '<option value="">-- Select Checklist --</option>';
                        html += '<option value="__NEW__">+ Create New Checklist</option>';
                        checklists.forEach(checklist => {
                            const selected = selectedChecklist === checklist.name ? 'selected' : '';
                            html += '<option value="' + escapeHtml(checklist.name) + '" ' + selected + '>' + escapeHtml(checklist.name) + '</option>';
                        });
                        html += '</select>';
                        html += '<input type="text" class="form-control mt-2 new-checklist-input" data-file-index="' + index + '" placeholder="Enter new checklist name" style="display: none;">';
                        html += '</td>';
                        html += '<td>';
                        html += '<span class="match-status ' + statusClass + '">' + statusText + '</span>';
                        html += '</td>';
                        html += '<td style="text-align: center;">';
                        html += '<button type="button" class="btn btn-sm btn-danger remove-bulk-file" data-file-index="' + index + '" title="Remove file">';
                        html += (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-trash-alt') : '<i class="fas fa-trash-alt"></i>');
                        html += '</button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    tableContainer.html(html);
                    
                    // Handle new checklist option
                    $(document).off('change', '.checklist-select').on('change', '.checklist-select', function() {
                        const fileIndex = $(this).data('file-index');
                        const value = $(this).val();
                        const newInput = $('.new-checklist-input[data-file-index="' + fileIndex + '"]');
                        
                        if (value === '__NEW__') {
                            newInput.show();
                            newInput.attr('required', true);
                            $(this).closest('tr').find('.match-status').removeClass('auto-matched manual').addClass('new-checklist').text('New checklist');
                        } else {
                            newInput.hide();
                            newInput.removeAttr('required');
                            if (value) {
                                $(this).closest('tr').find('.match-status').removeClass('new-checklist').addClass('manual').text('Manual selection');
                            }
                        }
                    });
                    
                    // Handle remove file button for visa documents
                    $(document).off('click', '.remove-bulk-file').on('click', '.remove-bulk-file', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const $row = $(this).closest('tr');
                        const fileName = $row.data('file-name');
                        const categoryId = currentVisaCategoryId;
                        
                        // Confirm before removing
                        if (!confirm('Are you sure you want to remove "' + fileName + '" from the upload list?')) {
                            return;
                        }
                        
                        // Find and remove the file from the array by matching file name
                        const fileArray = bulkUploadNominationFiles[categoryId];
                        const fileIndex = fileArray.findIndex(f => f.name === fileName);
                        
                        if (fileIndex > -1) {
                            fileArray.splice(fileIndex, 1);
                        }
                        
                        // Remove the row
                        $row.remove();
                        
                        // Update file count
                        const remainingCount = fileArray.length;
                        const container = $('#bulk-upload-nomination-' + categoryId);
                        container.find('.file-count-nomination').text(remainingCount);
                        
                        // If no files left, hide the file list and modal
                        if (remainingCount === 0) {
                            $('#bulk-upload-mapping-modal').hide();
                            container.find('.bulk-upload-file-list-nomination').hide();
                            container.find('.bulk-upload-files-container-nomination').empty();
                            alert('All files have been removed. Please select files again to upload.');
                        } else {
                            // Reindex remaining rows to maintain correct file indices
                            $('#bulk-upload-mapping-table tbody tr').each(function(newIndex) {
                                $(this).attr('data-file-index', newIndex);
                                $(this).find('.checklist-select').attr('data-file-index', newIndex);
                                $(this).find('.new-checklist-input').attr('data-file-index', newIndex);
                                $(this).find('.remove-bulk-file').attr('data-file-index', newIndex);
                            });
                        }
                    });
                    
                    // Update the confirm button to handle visa upload
                    $('#confirm-bulk-upload').off('click').on('click', function() {
                        confirmVisaBulkUpload();
                    });
                    
                    modal.show();
                }
                
                // Confirm visa bulk upload
                function confirmVisaBulkUpload() {
                    const categoryId = currentVisaCategoryId;
                    const matterId = currentVisaMatterId;
                    const files = bulkUploadNominationFiles[categoryId];
                    const mappings = [];
                    const autoCreate = $('#auto-create-unmatched').is(':checked');
                    
                    // Collect mappings in order of files
                    Array.from(files).forEach((file, fileIndex) => {
                        const fileName = file.name;
                        const selectElement = $('.checklist-select[data-file-index="' + fileIndex + '"]');
                        
                        if (selectElement.length === 0) {
                            mappings.push(null);
                            return;
                        }
                        
                        const checklist = selectElement.val();
                        
                        let mapping = null;
                        
                        if (checklist === '__NEW__') {
                            const newChecklistName = selectElement.closest('tr').find('.new-checklist-input').val();
                            if (newChecklistName) {
                                mapping = {
                                    type: 'new',
                                    name: newChecklistName.trim()
                                };
                            } else if (autoCreate) {
                                mapping = {
                                    type: 'new',
                                    name: extractChecklistNameFromFile(fileName)
                                };
                            }
                        } else if (checklist) {
                            mapping = {
                                type: 'existing',
                                name: checklist
                            };
                        } else if (autoCreate) {
                            mapping = {
                                type: 'new',
                                name: extractChecklistNameFromFile(fileName)
                            };
                        }
                        
                        if (!mapping) {
                            const matchStatus = selectElement.closest('tr').find('.match-status');
                            if (matchStatus.hasClass('auto-matched') || matchStatus.hasClass('manual')) {
                                const selectedOption = selectElement.find('option:selected');
                                if (selectedOption.val() && selectedOption.val() !== '__NEW__') {
                                    mapping = {
                                        type: 'existing',
                                        name: selectedOption.val()
                                    };
                                }
                            }
                        }
                        
                        mappings.push(mapping);
                    });
                    
                    // Validate all files have mappings
                    const unmappedFiles = [];
                    mappings.forEach((mapping, index) => {
                        if (!mapping || !mapping.name) {
                            unmappedFiles.push(files[index].name);
                        }
                    });
                    
                    if (unmappedFiles.length > 0 && !autoCreate) {
                        alert('Please map all files to checklists or enable "Auto-create checklist for unmatched files"');
                        return;
                    }
                    
                    // Fill in any missing mappings with auto-create
                    mappings.forEach((mapping, index) => {
                        if (!mapping || !mapping.name) {
                            mappings[index] = {
                                type: 'new',
                                name: extractChecklistNameFromFile(files[index].name)
                            };
                        }
                    });
                    
                    // Upload files
                    uploadBulkNominationFiles(categoryId, matterId, files, mappings);
                }
                
                function uploadBulkNominationFiles(categoryId, matterId, files, mappings) {
                    const formData = new FormData();
                    formData.append('_token', '{{ csrf_token() }}');
                    formData.append('clientid', currentNominationClientId);
                    formData.append('categoryid', categoryId);
                    formData.append('matterid', matterId || '');
                    formData.append('doctype', 'nomination');
                    formData.append('type', 'client');
                    
                    // Add files (sanitize multipart filename for WAF — same as single upload)
                    Array.from(files).forEach((file, index) => {
                        if (typeof mmAppendBulkDocumentUploadFile === 'function') {
                            mmAppendBulkDocumentUploadFile(formData, file);
                        } else {
                            const safeName = (typeof mmSanitizeDocumentUploadFilename === 'function')
                                ? mmSanitizeDocumentUploadFilename(file.name)
                                : String(file.name).replace(/[^a-zA-Z0-9\-_.]/g, '_');
                            formData.append('files[]', file, safeName);
                        }
                        const mapping = mappings[index] || { type: 'new', name: extractChecklistNameFromFile(file.name) };
                        formData.append('mappings[]', JSON.stringify(mapping));
                    });
                    
                    // Show progress
                    $('#bulk-upload-progress').show();
                    $('#bulk-upload-progress-bar').css('width', '0%').text('0%');
                    $('#confirm-bulk-upload').prop('disabled', true);
                    
                    $.ajax({
                        url: '{{ route("clients.documents.bulkUploadNominationDocuments") }}',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: function() {
                            const xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function(e) {
                                if (e.lengthComputable) {
                                    const percentComplete = (e.loaded / e.total) * 100;
                                    $('#bulk-upload-progress-bar').css('width', percentComplete + '%').text(Math.round(percentComplete) + '%');
                                }
                            }, false);
                            return xhr;
                        },
                        success: function(response) {
                            if (response.status) {
                                let message = response.message || 'Files uploaded successfully!';
                                if (response.errors && response.errors.length > 0) {
                                    message += '\n\nWarnings:\n' + response.errors.join('\n');
                                }
                                alert(message);
                                location.reload();
                            } else {
                                let errorMsg = 'Error: ' + (response.message || 'Upload failed');
                                if (response.errors && response.errors.length > 0) {
                                    errorMsg += '\n\nDetails:\n' + response.errors.join('\n');
                                }
                                alert(errorMsg);
                                $('#bulk-upload-progress').hide();
                                $('#confirm-bulk-upload').prop('disabled', false);
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Upload failed';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert('Error: ' + errorMsg);
                            $('#bulk-upload-progress').hide();
                            $('#confirm-bulk-upload').prop('disabled', false);
                        }
                    });
                }
            </script>

            <style>
                .context-menu-item:hover {
                    background-color: #f8f9fa;
                }

                /* Bulk Upload Dropzone Styles for Visa */
                .bulk-upload-dropzone-nomination {
                    position: relative;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .bulk-upload-dropzone-nomination * {
                    pointer-events: none;
                }
                
                .bulk-upload-dropzone-nomination.drag_over {
                    border-color: #28a745;
                    background-color: #e8f5e9;
                }

                /* Drag and Drop Zone Styles */
                .document-drag-drop-zone {
                    border: 2px dashed #ccc;
                    border-radius: 4px;
                    padding: 15px 20px;
                    text-align: center;
                    background-color: #f9f9f9;
                    cursor: pointer !important;
                    transition: all 0.3s ease;
                    min-height: 60px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 5px 0;
                    position: relative;
                    z-index: 1;
                }
                
                /* Make all child elements transparent to pointer events so drag events reach the dropzone */
                .document-drag-drop-zone * {
                    pointer-events: none;
                }

                .document-drag-drop-zone:hover {
                    border-color: #007bff;
                    background-color: #f0f8ff;
                }

                .document-drag-drop-zone.drag_over {
                    border-color: #28a745;
                    background-color: #e8f5e9;
                    border-width: 3px;
                }

                .drag-zone-inner {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    color: #374151;
                }

                .drag-zone-inner i {
                    font-size: 20px;
                    color: #2563eb;
                }

                .drag-zone-text {
                    font-size: 14px;
                    color: inherit;
                }

                .document-drag-drop-zone.uploading {
                    pointer-events: none;
                    opacity: 0.6;
                }

                .document-drag-drop-zone.uploading .drag-zone-text::after {
                    content: ' Uploading...';
                    font-weight: bold;
                    color: #007bff;
                }

                /* Bulk Upload File List Styles */
                #bulk-upload-mapping-table table tbody tr {
                    border-bottom: 1px solid #dee2e6;
                }

                #bulk-upload-mapping-table table tbody tr td {
                    padding: 15px 10px !important;
                }

                .bulk-upload-file-item {
                    vertical-align: top;
                }

                .bulk-upload-file-item td {
                    padding: 12px 8px !important;
                    vertical-align: top !important;
                }

                .bulk-upload-file-item .file-info {
                    display: flex;
                    align-items: flex-start;
                    gap: 10px;
                    min-height: 40px;
                }

                .bulk-upload-file-item .file-info > div {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .bulk-upload-file-item .file-name {
                    font-weight: 500;
                    color: #333;
                    word-break: break-word;
                    overflow-wrap: break-word;
                    white-space: normal;
                    line-height: 1.4;
                    display: block;
                }

                .bulk-upload-file-item .file-size {
                    font-size: 12px;
                    color: #4b5563;
                }

                .bulk-upload-file-item .checklist-select {
                    min-width: 200px;
                }

                .bulk-upload-file-item .match-status {
                    font-size: 12px;
                    padding: 2px 8px;
                    border-radius: 3px;
                }

                .match-status.auto-matched {
                    background-color: #d4edda;
                    color: #155724;
                }

                .match-status.manual {
                    background-color: #fff3cd;
                    color: #856404;
                }

                .match-status.new-checklist {
                    background-color: #cce5ff;
                    color: #004085;
                }

                .remove-bulk-file {
                    padding: 4px 8px;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }

                .remove-bulk-file:hover {
                    background-color: #c82333;
                    border-color: #bd2130;
                    transform: scale(1.1);
                }

                .remove-bulk-file i {
                    pointer-events: none;
                }
            </style>

