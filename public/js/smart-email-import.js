/**
 * Smart Email Import — front-end logic
 *
 * Depends on: jQuery, $.fn.mmSelect (global search widget), Bootstrap modals.
 * Config injected by blade into window.SmartImportConfig.
 */
(function ($) {
    'use strict';

    // -------------------------------------------------------------------------
    // Config & state
    // -------------------------------------------------------------------------
    const cfg = window.SmartImportConfig || {};
    let batchToken   = null;
    let batchItems   = [];       // [{item_id, filename, subject, from, to, date, snippet,
                                 //   attachment_count, suggestions, suggested_mail_type, status}]
    let confirming   = false;    // prevent double-submit

    // -------------------------------------------------------------------------
    // DOM refs
    // -------------------------------------------------------------------------
    const $uploadPanel     = $('#upload-panel');
    const $reviewPanel     = $('#review-panel');
    const $dropzone        = $('#dropzone-area');
    const $fileInput       = $('#file-input');
    const $fileList        = $('#file-list');
    const $selectedFilesUl = $('#selected-files-ul');
    const $fileCountLabel  = $('#file-count-label');
    const $btnAnalyze      = $('#btn-analyze');
    const $analyzeProgress = $('#analyze-progress');
    const $btnUploadMore   = $('#btn-upload-more');
    const $reviewTbody     = $('#review-tbody');
    const $selectAll       = $('#select-all-checkbox');
    const $btnConfirmHigh  = $('#btn-confirm-high-confidence');
    const $btnConfirmSel   = $('#btn-confirm-selected');
    const $bulkStatus      = $('#bulk-status-text');
    const $parseErrorsPanel= $('#parse-errors-panel');
    const $parseErrorsUl   = $('#parse-errors-ul');

    let selectedFiles = [];

    /**
     * Sanitize filename for multipart upload (WAF-safe). Matches emails.js / backend logic.
     */
    function sanitizeUploadFilename(filename) {
        if (!filename || typeof filename !== 'string') {
            return 'email_' + Date.now() + '.msg';
        }
        const lastDot = filename.lastIndexOf('.');
        const extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
        const nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;
        let sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
        sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        if (!sanitizedName) {
            sanitizedName = 'email_' + Date.now();
        }
        let sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
        if (sanitizedFilename.length > 255) {
            const maxNameLength = 255 - extension.length - (extension ? 1 : 0);
            if (maxNameLength > 0) {
                sanitizedName = sanitizedName.slice(0, maxNameLength);
                sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            } else {
                sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
            }
        }
        return sanitizedFilename;
    }

    // -------------------------------------------------------------------------
    // File selection / drag & drop
    // -------------------------------------------------------------------------
    $('#btn-browse').on('click', () => $fileInput.click());

    $fileInput.on('change', function () {
        handleFiles(Array.from(this.files));
    });

    $dropzone.on('dragover dragleave drop', function (e) {
        e.preventDefault();
        if (e.type === 'dragover') {
            $dropzone.addClass('drag-over');
        } else if (e.type === 'dragleave') {
            $dropzone.removeClass('drag-over');
        } else {
            $dropzone.removeClass('drag-over');
            handleFiles(Array.from(e.originalEvent.dataTransfer.files));
        }
    });

    $dropzone.on('click', function (e) {
        if (!$(e.target).is('#btn-browse')) {
            $fileInput.click();
        }
    });

    function handleFiles(files) {
        const msgFiles = files.filter(f => f.name.toLowerCase().endsWith('.msg'));
        if (msgFiles.length === 0) {
            alert('Please select .msg files only.');
            return;
        }
        if (msgFiles.length > 20) {
            alert('Maximum 20 files allowed per batch. The first 20 will be used.');
            msgFiles.splice(20);
        }
        selectedFiles = msgFiles;
        renderFileList();
    }

    function renderFileList() {
        $selectedFilesUl.empty();
        selectedFiles.forEach(f => {
            const size = (f.size / 1024).toFixed(1);
            $selectedFilesUl.append(
                `<li class="list-group-item py-1 px-2">
                    ${typeof crmIconAny === 'function' ? crmIconAny('far fa-envelope', { class: 'mr-2 text-muted' }) : '<i class="far fa-envelope mr-2 text-muted"></i>'}
                    <span>${escHtml(f.name)}</span>
                    <span class="text-muted ml-2 small">(${size} KB)</span>
                </li>`
            );
        });
        $fileCountLabel.text(`${selectedFiles.length} file(s) selected`);
        $fileList.show();
    }

    $('#btn-clear-files').on('click', () => {
        selectedFiles = [];
        $fileInput.val('');
        $fileList.hide();
        $selectedFilesUl.empty();
    });

    $btnUploadMore.on('click', () => {
        resetToUpload();
    });

    // -------------------------------------------------------------------------
    // Analyze
    // -------------------------------------------------------------------------
    $btnAnalyze.on('click', function () {
        if (selectedFiles.length === 0) return;
        doAnalyze();
    });

    async function doAnalyze() {
        $btnAnalyze.prop('disabled', true);
        $analyzeProgress.removeClass('d-none');

        const formData = new FormData();
        formData.append('_token', cfg.csrfToken);
        selectedFiles.forEach(f => {
            const safeName = sanitizeUploadFilename(f.name);
            formData.append('email_files[]', f, safeName);
        });

        try {
            const resp = await fetch(cfg.analyzeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': cfg.csrfToken, 'Accept': 'application/json' },
                body: formData,
                credentials: 'same-origin',
            });

            if (resp.status === 403) {
                const errorText = await resp.text();
                const isHtml = /<html[\s>]/i.test(errorText) || /<!DOCTYPE/i.test(errorText);
                let msg = 'Analyze failed: access denied.';
                if (isHtml || errorText.includes('Forbidden')) {
                    msg = 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes and try again.';
                } else {
                    try {
                        const data = JSON.parse(errorText);
                        if (data.message) {
                            msg = 'Analyze failed: ' + data.message;
                        }
                    } catch (e) { /* use default */ }
                }
                alert(msg);
                return;
            }

            const data = await resp.json();

            if (!resp.ok || !data.success) {
                alert('Analyze failed: ' + (data.message || resp.statusText));
                return;
            }

            batchToken = data.batch_token;
            batchItems = data.items;
            renderReviewTable();
            showReviewPanel();

        } catch (err) {
            console.error('Analyze error', err);
            alert('Request failed: ' + err.message);
        } finally {
            $btnAnalyze.prop('disabled', false);
            $analyzeProgress.addClass('d-none');
        }
    }

    // -------------------------------------------------------------------------
    // Review table rendering
    // -------------------------------------------------------------------------
    function showReviewPanel() {
        $uploadPanel.addClass('d-none');
        $reviewPanel.removeClass('d-none');
        $btnUploadMore.removeClass('d-none');
    }

    function resetToUpload() {
        // Destroy mmSelect instances BEFORE emptying tbody so the DOM nodes still exist
        destroyAllMmSelects();
        $reviewTbody.empty();
        $reviewPanel.addClass('d-none');
        $uploadPanel.removeClass('d-none');
        $btnUploadMore.addClass('d-none');
        selectedFiles = [];
        batchToken = null;
        batchItems = [];
        $fileInput.val('');
        $fileList.hide();
        $selectedFilesUl.empty();
        $parseErrorsPanel.addClass('d-none');
        $selectAll.prop('checked', false).prop('indeterminate', false);
        $bulkStatus.text('');
    }

    function renderReviewTable() {
        destroyAllMmSelects();
        $reviewTbody.empty();
        $parseErrorsUl.empty();

        const parseErrors = batchItems.filter(i => i.status === 'parse_error');
        const pending     = batchItems.filter(i => i.status === 'pending');

        if (parseErrors.length > 0) {
            parseErrors.forEach(i => {
                $parseErrorsUl.append(
                    `<li>${escHtml(i.filename)}: ${escHtml(i.error || 'Parse failed')}</li>`
                );
            });
            $parseErrorsPanel.removeClass('d-none');
        }

        pending.forEach(item => {
            $reviewTbody.append(buildRow(item));
        });

        // Initialise mmSelect for each row
        pending.forEach(item => initRowSelects(item));

        syncSelectAllCheckbox();
        updateBulkStatus();
    }

    function buildRow(item) {
        const topSuggestion = (item.suggestions || [])[0] || null;
        const confidence    = topSuggestion ? topSuggestion.confidence : 0;
        const confClass     = confidence >= 80 ? 'conf-high' : confidence >= 60 ? 'conf-medium' : confidence > 0 ? 'conf-low' : 'conf-none';
        const confLabel     = confidence > 0 ? `${confidence}%` : '—';

        const mailType = item.suggested_mail_type || 'inbox';

        // Suggestions dropdown options
        let suggOpts = '<option value="">— Manual —</option>';
        (item.suggestions || []).forEach((s, idx) => {
            const label = `${escHtml(s.client_name)} · ${escHtml(s.matter_no)} · ${s.confidence}%`;
            suggOpts += `<option value="${idx}">${label}</option>`;
        });

        return `
        <tr data-item-id="${escHtml(item.item_id)}" class="review-row">
            <td class="align-middle">
                <input type="checkbox" class="row-import-checkbox" title="Import this email">
            </td>
            <td class="align-middle" style="max-width:300px">
                <div class="font-weight-semibold text-truncate" style="max-width:280px" title="${escHtml(item.subject)}">
                    ${escHtml(item.subject || '(no subject)')}
                </div>
                <div class="text-muted small text-truncate" style="max-width:280px">${escHtml(item.from || '')}</div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1 btn-peek"
                    data-item-id="${escHtml(item.item_id)}">
                    ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-eye', { class: 'mr-1' }) : '<i class="fas fa-eye mr-1"></i>'}Quick Peek
                </button>
            </td>
            <td class="align-middle">
                <select class="form-control form-control-sm mail-type-select" style="width:90px">
                    <option value="inbox" ${mailType === 'inbox' ? 'selected' : ''}>Inbox</option>
                    <option value="sent"  ${mailType === 'sent'  ? 'selected' : ''}>Sent</option>
                </select>
            </td>
            <td class="align-middle">
                <div class="mb-1">
                    <select class="form-control form-control-sm suggestion-select"
                        style="width:100%;max-width:240px"
                        data-item-id="${escHtml(item.item_id)}">
                        ${suggOpts}
                    </select>
                </div>
                <select class="form-control form-control-sm client-search-select js-smart-client-search"
                    data-item-id="${escHtml(item.item_id)}"
                    style="width:100%;max-width:240px"
                    placeholder="Search client / lead…"></select>
                <input type="hidden" class="client-id-input" value="">
                <input type="hidden" class="record-type-input" value="">
            </td>
            <td class="align-middle">
                <select class="form-control form-control-sm matter-select"
                    data-item-id="${escHtml(item.item_id)}"
                    style="max-width:210px" disabled>
                    <option value="">— select client first —</option>
                </select>
                <input type="hidden" class="matter-id-input" value="">
            </td>
            <td class="align-middle text-center">
                <span class="confidence-badge ${confClass}">${confLabel}</span>
            </td>
            <td class="align-middle">
                <button type="button" class="btn btn-sm btn-outline-success btn-confirm-row"
                    data-item-id="${escHtml(item.item_id)}">
                    ${typeof crmIconAny === 'function' ? crmIconAny('fas fa-check') : '<i class="fas fa-check"></i>'}
                </button>
            </td>
        </tr>`;
    }

    // -------------------------------------------------------------------------
    // mmSelect for per-row client search
    // -------------------------------------------------------------------------
    function initRowSelects(item) {
        const $row  = $reviewTbody.find(`tr[data-item-id="${item.item_id}"]`);
        const $sel  = $row.find('.js-smart-client-search');
        const $sug  = $row.find('.suggestion-select');

        if (!$sel.length || typeof $.fn.mmSelect === 'undefined') return;

        $sel.mmSelect({
            closeOnSelect: true,
            minimumInputLength: 2,
            ajax: {
                url: cfg.clientSearchUrl,
                dataType: 'json',
                delay: 350,
                data: params => ({ q: params.term || '' }),
                processResults: data => ({ results: data.items || [] }),
                cache: false,
            },
            templateResult: formatClientOption,
            templateSelection: repo => repo.name || repo.text || '',
        });

        // Suggestion dropdown → prefill client + matter
        $sug.on('change', function () {
            const idx = parseInt(this.value, 10);
            if (isNaN(idx)) return;
            const sugg = (item.suggestions || [])[idx];
            if (!sugg) return;
            applySuggestion($row, sugg);
            syncSelectAllCheckbox();
            updateBulkStatus();
        });

        // Apply top suggestion if high-confidence
        if (item.suggestions && item.suggestions.length > 0) {
            $sug.val('0').trigger('change');
        }
    }

    function formatClientOption(repo) {
        if (repo.loading) return repo.text;
        const locked = repo.locked
            ? '<span class="ml-1" title="No access">&#128274;</span>'
            : '';
        const statusBadge = repo.status === 'Archived'
            ? '<span class="badge badge-secondary ml-1">Archived</span>'
            : `<span class="badge badge-warning ml-1 text-capitalize">${repo.status || ''}</span>`;
        return $(
            `<div class="d-flex justify-content-between align-items-center">
                <span>${escHtml(repo.name || '')}${locked}</span>
                <small class="text-muted ml-2">${escHtml(repo.email || '')}${statusBadge}</small>
            </div>`
        );
    }

    // On mmSelect select event for each row
    $(document).on('mmselect:select', '.js-smart-client-search', function (e) {
        const data    = (e.params && e.params.data) || {};
        const $sel    = $(this);
        const $row    = $sel.closest('tr');

        if (data.locked) {
            // Reset the select, open access modal
            $sel.val(null).trigger('change');
            if (typeof window.openCrmAccessModal === 'function') {
                window.openCrmAccessModal(data);
            }
            return;
        }

        // cid is always numeric admins.id — never parseInt the base64 id field
        const clientId   = data.cid;
        const recordType = data.record_type || 'client';

        if (!clientId) return;

        $row.find('.client-id-input').val(clientId);
        $row.find('.record-type-input').val(recordType);
        loadMattersForRow($row, clientId);
    });

    $(document).on('mmselect:clear', '.js-smart-client-search', function () {
        const $row = $(this).closest('tr');
        $row.find('.client-id-input').val('');
        $row.find('.record-type-input').val('');
        $row.find('.matter-select').prop('disabled', true).html('<option value="">— select client first —</option>');
        $row.find('.matter-id-input').val('');
        updateBulkStatus();
    });

    function applySuggestion($row, sugg) {
        $row.find('.client-id-input').val(sugg.client_id || '');
        $row.find('.record-type-input').val(sugg.record_type || 'client');

        // Populate matter select directly
        const $matterSel = $row.find('.matter-select');
        $matterSel.prop('disabled', false).html(
            `<option value="${sugg.client_matter_id}">${escHtml(sugg.matter_display)}</option>`
        );
        $row.find('.matter-id-input').val(sugg.client_matter_id || '');

        updateBulkStatus();
    }

    // -------------------------------------------------------------------------
    // Matter loading
    // -------------------------------------------------------------------------
    function loadMattersForRow($row, clientId) {
        const $matterSel = $row.find('.matter-select');
        $matterSel.prop('disabled', true).html('<option value="">Loading…</option>');
        $row.find('.matter-id-input').val('');

        $.getJSON(cfg.getMattersUrl + clientId)
            .done(function (data) {
                if (!data.success || !data.matters || data.matters.length === 0) {
                    $matterSel.html('<option value="">— no active matters —</option>');
                    return;
                }
                let opts = '<option value="">— select matter —</option>';
                data.matters.forEach(m => {
                    opts += `<option value="${m.id}">${escHtml(m.display_name)}</option>`;
                });
                $matterSel.html(opts).prop('disabled', false);
            })
            .fail(function () {
                $matterSel.html('<option value="">— failed to load —</option>');
            });
    }

    $(document).on('change', '.matter-select', function () {
        const $row = $(this).closest('tr');
        $row.find('.matter-id-input').val(this.value);
        updateBulkStatus();
    });

    // -------------------------------------------------------------------------
    // Quick peek modal
    // -------------------------------------------------------------------------
    $(document).on('click', '.btn-peek', function () {
        const itemId = $(this).data('item-id');
        const item   = batchItems.find(i => i.item_id === itemId);
        if (!item) return;

        $('#peek-subject').text(item.subject || '(no subject)');
        $('#peek-from').text(item.from || '—');
        $('#peek-to').text(item.to || '—');
        $('#peek-date').text(item.date || '—');
        $('#peek-attachments').text(
            item.attachment_count > 0 ? `${item.attachment_count} attachment(s)` : 'None'
        );
        $('#peek-snippet').text(item.snippet || '(no body preview)');

        const peekModal = document.getElementById('peek-modal');
        if (peekModal) {
            if (typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(peekModal).show();
            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                $(peekModal).modal('show');
            }
        }
    });

    // -------------------------------------------------------------------------
    // Select-all checkbox
    // -------------------------------------------------------------------------
    $selectAll.on('change', function () {
        const checked = this.checked;
        $reviewTbody.find('.review-row:not(.row-saved) .row-import-checkbox').each(function () {
            this.checked = checked;
        });
        updateBulkStatus();
    });

    $(document).on('change', '.row-import-checkbox', function () {
        syncSelectAllCheckbox();
        updateBulkStatus();
    });

    function syncSelectAllCheckbox() {
        const total   = $reviewTbody.find('.review-row:not(.row-saved) .row-import-checkbox').length;
        const checked = $reviewTbody.find('.review-row:not(.row-saved) .row-import-checkbox:checked').length;
        $selectAll.prop('indeterminate', checked > 0 && checked < total);
        $selectAll.prop('checked', total > 0 && checked === total);
    }

    function updateBulkStatus() {
        const pending     = $reviewTbody.find('.review-row:not(.row-saved)').length;
        const readyHigh   = countHighConfidenceReady();
        const readySel    = countSelectedReady();
        $bulkStatus.text(`${pending} row(s) remaining · ${readyHigh} high-confidence ready · ${readySel} selected ready`);
    }

    // -------------------------------------------------------------------------
    // Confirm — single row
    // -------------------------------------------------------------------------
    $(document).on('click', '.btn-confirm-row', function () {
        if (confirming) return;
        const itemId = $(this).data('item-id');
        const $row   = $reviewTbody.find(`tr[data-item-id="${itemId}"]`);
        const asgn   = buildAssignment($row);
        if (!asgn) {
            showRowError($row, 'Please select a client and matter before confirming.');
            return;
        }
        doConfirm([asgn]);
    });

    // -------------------------------------------------------------------------
    // Confirm — high confidence bulk
    // -------------------------------------------------------------------------
    $btnConfirmHigh.on('click', function () {
        if (confirming) return;
        const assignments = [];
        $reviewTbody.find('.review-row:not(.row-saved)').each(function () {
            const $row      = $(this);
            const itemId    = $row.data('item-id');
            const item      = batchItems.find(i => i.item_id === itemId);
            const topConf   = (item && item.suggestions && item.suggestions.length > 0)
                ? item.suggestions[0].confidence : 0;
            if (topConf < 80) return;
            const asgn = buildAssignment($row);
            if (asgn) assignments.push(asgn);
        });
        if (assignments.length === 0) {
            alert('No high-confidence rows with client and matter assigned.');
            return;
        }
        doConfirm(assignments);
    });

    // -------------------------------------------------------------------------
    // Confirm — selected bulk
    // -------------------------------------------------------------------------
    $btnConfirmSel.on('click', function () {
        if (confirming) return;
        const assignments = [];
        $reviewTbody.find('.review-row:not(.row-saved) .row-import-checkbox:checked').each(function () {
            const $row = $(this).closest('tr');
            const asgn = buildAssignment($row);
            if (asgn) assignments.push(asgn);
        });
        if (assignments.length === 0) {
            alert('No checked rows with client and matter assigned.');
            return;
        }
        doConfirm(assignments);
    });

    // -------------------------------------------------------------------------
    // doConfirm — shared confirm handler
    // -------------------------------------------------------------------------
    async function doConfirm(assignments) {
        confirming = true;
        setAllConfirmButtons(true);

        try {
            const resp = await fetch(cfg.confirmUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': cfg.csrfToken,
                },
                body:        JSON.stringify({ batch_token: batchToken, assignments }),
                credentials: 'same-origin',
            });
            const data = await resp.json();

            if (!resp.ok) {
                alert('Confirm failed: ' + (data.message || resp.statusText));
                return;
            }

            // Remove only successfully saved rows
            (data.saved_item_ids || []).forEach(itemId => {
                const $row = $reviewTbody.find(`tr[data-item-id="${itemId}"]`);
                destroyRowMmSelect($row);
                $row.remove();
                // Remove from local batchItems
                batchItems = batchItems.filter(i => i.item_id !== itemId);
            });

            // Mark failed rows visually
            (data.failed || []).forEach(f => {
                const $row = $reviewTbody.find(`tr[data-item-id="${f.item_id}"]`);
                $row.addClass('row-failed');
                showRowError($row, f.error || 'Import failed.');
            });

            syncSelectAllCheckbox();
            updateBulkStatus();

            if (data.saved > 0) {
                showBulkToast(`${data.saved} email(s) imported successfully.`, 'success');
            }
            if (data.failed && data.failed.length > 0) {
                showBulkToast(`${data.failed.length} email(s) failed — see rows highlighted in red.`, 'warning');
            }

        } catch (err) {
            console.error('Confirm error', err);
            alert('Request failed: ' + err.message);
        } finally {
            confirming = false;
            setAllConfirmButtons(false);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    function buildAssignment($row) {
        const itemId        = $row.data('item-id');
        const clientId      = parseInt($row.find('.client-id-input').val(), 10);
        const matterId      = parseInt($row.find('.matter-id-input').val(), 10);
        const mailType      = $row.find('.mail-type-select').val();
        const recordType    = $row.find('.record-type-input').val() || 'client';

        if (!itemId || !clientId || !matterId) return null;

        return { item_id: itemId, client_id: clientId, client_matter_id: matterId, mail_type: mailType, record_type: recordType };
    }

    function countHighConfidenceReady() {
        let count = 0;
        $reviewTbody.find('.review-row:not(.row-saved)').each(function () {
            const $row   = $(this);
            const itemId = $row.data('item-id');
            const item   = batchItems.find(i => i.item_id === itemId);
            const topConf = (item && item.suggestions && item.suggestions.length > 0)
                ? item.suggestions[0].confidence : 0;
            if (topConf >= 80 && buildAssignment($row)) count++;
        });
        return count;
    }

    function countSelectedReady() {
        let count = 0;
        $reviewTbody.find('.review-row:not(.row-saved) .row-import-checkbox:checked').each(function () {
            const $row = $(this).closest('tr');
            if (buildAssignment($row)) count++;
        });
        return count;
    }

    function setAllConfirmButtons(disabled) {
        $('.btn-confirm-row, #btn-confirm-high-confidence, #btn-confirm-selected').prop('disabled', disabled);
    }

    function showRowError($row, message) {
        $row.find('.btn-confirm-row').after(
            `<div class="text-danger small mt-1 row-error-msg">${escHtml(message)}</div>`
        );
        setTimeout(() => $row.find('.row-error-msg').remove(), 5000);
    }

    function showBulkToast(message, type) {
        const color = type === 'success' ? '#28a745' : '#ffc107';
        const $toast = $(
            `<div style="position:fixed;bottom:24px;right:24px;z-index:9999;background:${color};
                color:#fff;padding:12px 20px;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.25);
                font-weight:600;max-width:340px;">
                ${escHtml(message)}
            </div>`
        );
        $('body').append($toast);
        setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 4000);
    }

    function destroyAllMmSelects() {
        if (typeof $.fn.mmSelect !== 'undefined') {
            $reviewTbody.find('.js-smart-client-search').each(function () {
                try { $(this).mmSelect('destroy'); } catch (e) {}
            });
        }
    }

    function destroyRowMmSelect($row) {
        if (typeof $.fn.mmSelect !== 'undefined') {
            $row.find('.js-smart-client-search').each(function () {
                try { $(this).mmSelect('destroy'); } catch (e) {}
            });
        }
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

})(jQuery);
