/**
 * Workflow Tab - partial refresh after status changes (no full page reload)
 */
(function() {
    'use strict';

    var initialized = false;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function workflowUrls() {
        var urls = (window.ClientDetailConfig && window.ClientDetailConfig.urls) || {};
        return {
            updateNextStage: urls.updateNextStage,
            updatePreviousStage: urls.updatePreviousStage,
            updateDeadline: urls.updateDeadline,
            changeWorkflow: urls.changeWorkflow,
            discontinue: urls.discontinue,
            reopen: urls.reopen,
            completeWorkflowChecklist: urls.completeWorkflowChecklist,
            saveWorkflowFileNote: urls.saveWorkflowFileNote
        };
    }

    function getActiveTabId() {
        return document.querySelector('.client-nav-button.active')?.getAttribute('data-tab') || '';
    }

    function ensureStageNavBackButtonVisible() {
        ['back-to-previous-stage', 'workflow-tab-back-to-previous-stage'].forEach(function(btnId) {
            var btn = document.getElementById(btnId);
            if (!btn) return;

            btn.style.setProperty('display', 'inline-block', 'important');
            btn.style.setProperty('visibility', 'visible', 'important');
            btn.style.setProperty('opacity', '1', 'important');

            // Workflow v2 header/toolbar use scoped icon styles — avoid legacy client-portal overrides
            if (btn.closest('.workflow-v2-toolbar') || btn.closest('.workflow-v2-header')) {
                return;
            }

            btn.style.setProperty('color', '#3490dc', 'important');
            btn.style.setProperty('border-color', '#3490dc', 'important');
            btn.style.setProperty('background-color', '#ffffff', 'important');
        });
    }

    function refreshActivityFeedIfVisible() {
        try {
            if (typeof $ === 'undefined' || !$('#activity-feed').length) {
                return;
            }
            if (!$('#activity-feed').is(':visible')) {
                return;
            }
            if (typeof window.loadActivities === 'function') {
                window.loadActivities();
            }
            if (typeof getallactivities === 'function') {
                getallactivities();
            }
        } catch (err) {
            console.warn('[WorkflowTab] Activity feed refresh skipped', err);
        }
    }

    function refreshWorkflowV2Icons(root) {
        if (typeof refreshLucideIcons !== 'function') {
            return;
        }
        var target = root || document.getElementById('workflow-tab') || document.getElementById('client_portal-tab');
        if (target) {
            refreshLucideIcons(target);
        }
    }

    function refreshTabPane(tabSelector) {
        var currentTab = document.querySelector(tabSelector);
        if (!currentTab) {
            return Promise.resolve();
        }

        var wasActive = currentTab.classList.contains('active');

        return fetch(window.location.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to refresh tab: ' + tabSelector);
            }
            return response.text();
        })
        .then(function(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var newTab = doc.querySelector(tabSelector);
            if (!newTab) {
                throw new Error('Tab fragment not found: ' + tabSelector);
            }
            if (!currentTab.parentNode) {
                throw new Error('Tab element no longer in document: ' + tabSelector);
            }
            if (wasActive) {
                newTab.classList.add('active');
            }
            currentTab.replaceWith(newTab);
            refreshWorkflowV2Icons(newTab);
        });
    }

    function refreshSidebarMatterStatus() {
        return fetch(window.location.href, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        })
        .then(function(response) {
            if (!response.ok) {
                return;
            }
            return response.text();
        })
        .then(function(html) {
            if (!html) {
                return;
            }
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var newBadge = doc.querySelector('.matter-status-badge');
            var currentBadge = document.querySelector('.matter-status-badge');
            if (newBadge && currentBadge) {
                currentBadge.innerHTML = newBadge.innerHTML;
            }
        })
        .catch(function(err) {
            console.warn('[WorkflowTab] Sidebar status refresh skipped', err);
        });
    }

    function bindClientPortalSubTabDelegation() {
        if (bindClientPortalSubTabDelegation.initialized) {
            return;
        }
        bindClientPortalSubTabDelegation.initialized = true;

        document.addEventListener('click', function(e) {
            var tabLink = e.target.closest('.client-portal-tab-link');
            if (!tabLink) {
                return;
            }

            var portalTab = document.getElementById('client_portal-tab');
            if (!portalTab || !portalTab.contains(tabLink)) {
                return;
            }

            e.preventDefault();

            var tabItem = tabLink.closest('.client-portal-tab-item');
            if (!tabItem) {
                return;
            }

            var targetTab = tabItem.getAttribute('data-tab');
            portalTab.querySelectorAll('.client-portal-tab-item').forEach(function(item) {
                item.classList.remove('active');
            });
            portalTab.querySelectorAll('.client-portal-tab-pane').forEach(function(pane) {
                pane.classList.remove('active');
            });
            tabItem.classList.add('active');

            var targetPane = document.getElementById(targetTab + '-tab');
            if (targetPane) {
                targetPane.classList.add('active');
            }
            if (targetTab === 'activities' && typeof refreshWorkflowV2Icons === 'function') {
                refreshWorkflowV2Icons(targetPane);
            }
        });
    }

    function refreshClientPortalTab() {
        return refreshTabPane('#client_portal-tab').then(function() {
            ensureStageNavBackButtonVisible();
            bindClientPortalSubTabDelegation();
            initWorkflowV2StageNavigation();
            return refreshSidebarMatterStatus();
        }).then(function() {
            refreshActivityFeedIfVisible();
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function parseWorkflowV2StagesData(root) {
        var scope = root || document.getElementById('workflow-tab') || document;
        var scriptEl = scope.querySelector
            ? scope.querySelector('#workflow-v2-stages-data')
            : document.getElementById('workflow-v2-stages-data');
        if (!scriptEl || !scriptEl.textContent) {
            return null;
        }
        try {
            return JSON.parse(scriptEl.textContent);
        } catch (err) {
            console.error('[WorkflowTab] Failed to parse workflow stage data', err);
            return null;
        }
    }

    function getWorkflowTabAdvanceButton() {
        var workflowTab = document.getElementById('workflow-tab');
        if (workflowTab) {
            return workflowTab.querySelector('.js-workflow-advance-btn.workflow-v2-advance-btn')
                || workflowTab.querySelector('#workflow-tab-proceed-to-next-stage')
                || workflowTab.querySelector('.js-workflow-advance-btn');
        }
        return document.getElementById('workflow-tab-proceed-to-next-stage')
            || document.getElementById('proceed-to-next-stage');
    }

    function getWorkflowAdvanceButtons(root) {
        var scope = root || document.getElementById('workflow-tab') || document;
        var buttons = [];
        if (scope.querySelectorAll) {
            scope.querySelectorAll('.js-workflow-advance-btn, #workflow-tab-proceed-to-next-stage, #workflow-tab-proceed-to-next-stage-header').forEach(function(btn) {
                buttons.push(btn);
            });
        }
        if (!buttons.length) {
            var fallback = getWorkflowTabAdvanceButton();
            if (fallback) {
                buttons.push(fallback);
            }
        }
        return buttons;
    }

    function setWorkflowAdvanceButtonVisible(btn, isCurrentStage) {
        if (!btn) {
            return;
        }
        var advanceDisplay = btn.classList.contains('workflow-v2-advance-btn') ? '' : 'inline-flex';
        btn.style.display = isCurrentStage ? advanceDisplay : 'none';
    }

    function setWorkflowAdvanceButtonState(btn, isCurrentStage, outstandingRequired, isLastStage) {
        var buttons = btn ? [btn] : [];
        // Prefer syncing every Workflow tab advance control (header + footer).
        var scope = (btn && btn.closest) ? (btn.closest('#workflow-tab') || document.getElementById('workflow-tab')) : document.getElementById('workflow-tab');
        if (scope) {
            buttons = getWorkflowAdvanceButtons(scope);
        }
        if (!buttons.length && btn) {
            buttons = [btn];
        }

        var blocked = !!isLastStage || ((parseInt(outstandingRequired, 10) || 0) > 0);
        buttons.forEach(function(advanceBtn) {
            setWorkflowAdvanceButtonVisible(advanceBtn, isCurrentStage);
            advanceBtn.disabled = blocked;
            if (blocked && (parseInt(outstandingRequired, 10) || 0) > 0) {
                advanceBtn.setAttribute('title', 'Complete all required checklist items to advance');
                advanceBtn.setAttribute('data-tooltip', 'Complete all required checklist items to advance');
            } else if (!isLastStage) {
                advanceBtn.setAttribute('title', 'Proceed to Next Stage');
                advanceBtn.setAttribute('data-tooltip', 'Proceed to Next Stage');
            }
        });
    }

    function isWorkflowChecklistInteractive(root) {
        var scope = root || document.getElementById('workflow-tab') || document;
        var body = scope.querySelector
            ? scope.querySelector('#workflow-v2-body')
            : document.getElementById('workflow-v2-body');
        return !!(body && body.getAttribute('data-checklist-interactive') === '1');
    }

    function getWorkflowMatterId(root) {
        var scope = root || document.getElementById('workflow-tab') || document;
        var body = scope.querySelector
            ? scope.querySelector('#workflow-v2-body')
            : document.getElementById('workflow-v2-body');
        return body ? (body.getAttribute('data-matter-id') || '') : '';
    }

    function getActiveChecklistIndex(rows) {
        if (!rows || !rows.length) {
            return -1;
        }
        for (var i = 0; i < rows.length; i++) {
            if (!rows[i].done) {
                return i;
            }
        }
        return -1;
    }

    function renderWorkflowV2Checklist(rows, options) {
        options = options || {};
        var interactive = !!options.interactive;
        var readOnly = options.readOnly !== false && !interactive;

        if (!rows || rows.length === 0) {
            return '<div class="workflow-v2-checklist-empty" id="workflow-v2-checklist-empty">'
                + 'No checklist items for this stage. '
                + 'Add items from Client Portal &rarr; Documents, or configure templates in Admin Console.'
                + '</div>';
        }

        var activeIndex = getActiveChecklistIndex(rows);
        var html = '<div class="workflow-v2-checklist" id="workflow-v2-checklist">';
        rows.forEach(function(item, index) {
            var done = !!item.done;
            var required = !!item.required;
            var itemId = item.id != null ? item.id : '';
            var isActive = interactive && !readOnly && !done && activeIndex === index && !!itemId;
            var disabled = !isActive;
            var itemClass = 'workflow-v2-checklist-item'
                + (done ? ' is-done' : '')
                + (isActive ? ' is-active-item' : '')
                + (disabled && !done ? ' is-locked-item' : '');

            html += '<div class="' + itemClass + '"'
                + ' data-checklist-id="' + escapeHtml(itemId) + '"'
                + ' data-checklist-index="' + index + '"'
                + ' data-required="' + (required ? '1' : '0') + '">'
                + '<input type="checkbox" class="workflow-v2-checklist-checkbox"'
                + (done ? ' checked' : '')
                + (disabled ? ' disabled' : '')
                + ' data-checklist-id="' + escapeHtml(itemId) + '"'
                + ' aria-label="' + escapeHtml(item.label) + '">'
                + '<span class="workflow-v2-checklist-label">' + escapeHtml(item.label) + '</span>';
            if (required) {
                html += '<span class="workflow-v2-required-badge">Required</span>';
            }
            html += '</div>';
        });
        html += '</div>';
        return html;
    }

    function updateWorkflowV2Outstanding(outstanding, root) {
        var scope = root || document.getElementById('workflow-tab') || document;
        var wrap = scope.querySelector
            ? scope.querySelector('#workflow-v2-footer-outstanding')
            : document.getElementById('workflow-v2-footer-outstanding');
        var textEl = scope.querySelector
            ? scope.querySelector('#workflow-v2-outstanding-text')
            : document.getElementById('workflow-v2-outstanding-text');
        if (!wrap || !textEl) {
            return;
        }

        var count = parseInt(outstanding, 10) || 0;
        wrap.classList.toggle('is-clear', count === 0);
        textEl.textContent = count > 0
            ? (count + ' Required item' + (count === 1 ? '' : 's') + ' outstanding')
            : 'All required items complete';
    }

    function showWorkflowV2Stage(stageId, root) {
        var scope = root || document.getElementById('workflow-tab') || document;
        var data = parseWorkflowV2StagesData(scope);
        if (!data || !data.stages) {
            return;
        }

        var stage = data.stages.find(function(s) {
            return String(s.id) === String(stageId);
        });
        if (!stage) {
            return;
        }

        var panel = scope.querySelector
            ? scope.querySelector('#workflow-v2-panel')
            : document.getElementById('workflow-v2-panel');
        var eyebrow = scope.querySelector('#workflow-v2-panel-eyebrow');
        var title = scope.querySelector('#workflow-v2-panel-title');
        var badges = scope.querySelector('#workflow-v2-panel-badges');
        var pendingFrom = scope.querySelector('#workflow-v2-pending-from');
        var completionRule = scope.querySelector('#workflow-v2-panel-completion-rule');
        var completionRuleText = scope.querySelector('#workflow-v2-completion-rule-text');
        var checklistContainer = scope.querySelector('#workflow-v2-checklist-container');
        var fileNote = scope.querySelector('#workflow-v2-file-note-section');
        var advanceBtn = scope.id === 'workflow-tab'
            ? (scope.querySelector('.js-workflow-advance-btn.workflow-v2-advance-btn')
                || scope.querySelector('#workflow-tab-proceed-to-next-stage')
                || scope.querySelector('.js-workflow-advance-btn'))
            : getWorkflowTabAdvanceButton();

        if (panel) {
            panel.setAttribute('data-view-stage-id', String(stage.id));
        }
        if (eyebrow) {
            eyebrow.textContent = 'Stage ' + stage.index + ' of ' + (data.totalStages || data.stages.length);
        }
        if (title) {
            title.textContent = stage.name || 'N/A';
        }

        var display = stage.stageDisplay || {};
        var isCurrentStage = String(stage.id) === String(data.currentStageId);
        if (badges) {
            if (display.pending_from) {
                badges.style.display = '';
                if (pendingFrom) {
                    pendingFrom.textContent = display.pending_from;
                }
            } else {
                badges.style.display = 'none';
            }
        }
        if (completionRule) {
            if (display.completion_rule) {
                completionRule.style.display = '';
                if (completionRuleText) {
                    completionRuleText.textContent = display.completion_rule;
                }
            } else {
                completionRule.style.display = 'none';
            }
        }
        if (checklistContainer) {
            var interactive = isWorkflowChecklistInteractive(scope);
            checklistContainer.setAttribute('data-readonly', (interactive && isCurrentStage) ? '0' : '1');
            checklistContainer.innerHTML = renderWorkflowV2Checklist(stage.checklistRows || [], {
                interactive: interactive && isCurrentStage,
                readOnly: !interactive || !isCurrentStage
            });
        }
        if (fileNote) {
            fileNote.style.display = display.file_note_section ? '' : 'none';
            var historyEl = fileNote.querySelector('#workflow-v2-file-note-history');
            var fileNoteTextarea = fileNote.querySelector('#workflow-v2-file-note-input')
                || fileNote.querySelector('textarea');
            var noteBody = stage.fileNoteBody || '';
            if (historyEl) {
                historyEl.textContent = noteBody;
                historyEl.classList.toggle('is-empty', !noteBody);
                historyEl.style.display = noteBody ? '' : 'none';
            }
            if (fileNoteTextarea) {
                // New-entry field: clear when switching stages; history shown above
                fileNoteTextarea.value = '';
                fileNoteTextarea.disabled = isWorkflowChecklistInteractive(scope) && !isCurrentStage;
            }
        }
        updateWorkflowV2Outstanding(stage.outstandingRequired, scope);

        var currentStageData = data.stages.find(function(s) {
            return String(s.id) === String(data.currentStageId);
        });
        var currentOutstanding = currentStageData
            ? (currentStageData.outstandingRequired || 0)
            : (stage.outstandingRequired || 0);
        var hasNextStage = !!(currentStageData && data.stages.some(function(s) {
            return s.index > currentStageData.index;
        }));
        setWorkflowAdvanceButtonState(
            advanceBtn,
            isCurrentStage,
            currentOutstanding,
            !hasNextStage
        );

        var stageItems = scope.querySelectorAll
            ? scope.querySelectorAll('.workflow-v2-stage-item[data-stage-id]')
            : document.querySelectorAll('.workflow-v2-stage-item[data-stage-id]');
        stageItems.forEach(function(item) {
            var isViewing = String(item.getAttribute('data-stage-id')) === String(stage.id);
            item.classList.toggle('is-viewing', isViewing);
            item.setAttribute('aria-current', isViewing ? 'step' : 'false');
        });
    }

    var workflowV2StageNavBound = false;

    function bindWorkflowV2StageNavigation(root) {
        if (workflowV2StageNavBound) {
            return;
        }
        workflowV2StageNavBound = true;

        document.addEventListener('click', function(e) {
            var item = e.target.closest('.workflow-v2-stage-item[data-stage-id]');
            if (!item) {
                return;
            }
            var scope = item.closest('#workflow-tab') || item.closest('#client_portal-tab');
            if (!scope) {
                return;
            }
            updateWorkflowV2StageFromItem(item, scope.id === 'workflow-tab' ? scope : (scope.querySelector('#workflow-tab') || scope));
        });

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            var item = e.target.closest('.workflow-v2-stage-item[data-stage-id]');
            if (!item) {
                return;
            }
            var scope = item.closest('#workflow-tab') || item.closest('#client_portal-tab');
            if (!scope) {
                return;
            }
            e.preventDefault();
            updateWorkflowV2StageFromItem(item, scope.id === 'workflow-tab' ? scope : scope);
        });
    }

    function updateWorkflowV2StageFromItem(item, root) {
        if (!item) {
            return;
        }
        if (item.classList.contains('is-locked') || item.getAttribute('aria-disabled') === 'true') {
            return;
        }
        var stageId = item.getAttribute('data-stage-id');
        if (!stageId) {
            return;
        }
        var scope = root || item.closest('#workflow-tab') || document.getElementById('workflow-tab') || document;
        if (isWorkflowChecklistInteractive(scope)) {
            var data = parseWorkflowV2StagesData(scope);
            var stage = data && data.stages
                ? data.stages.find(function(s) { return String(s.id) === String(stageId); })
                : null;
            if (stage && stage.isFuture) {
                return;
            }
            var status = item.getAttribute('data-stage-status');
            if (status === 'future') {
                return;
            }
        }
        showWorkflowV2Stage(stageId, scope);
    }

    function applyChecklistCompletionToStageData(scope, payload) {
        var data = parseWorkflowV2StagesData(scope);
        if (!data || !data.stages || !payload) {
            return data;
        }
        var currentId = data.currentStageId;
        data.stages.forEach(function(stage) {
            if (String(stage.id) !== String(currentId)) {
                return;
            }
            if (payload.checklistRows) {
                stage.checklistRows = payload.checklistRows;
            }
            if (typeof payload.outstandingRequired !== 'undefined') {
                stage.outstandingRequired = payload.outstandingRequired;
            }
            if (typeof payload.activeChecklistIndex !== 'undefined') {
                stage.activeChecklistIndex = payload.activeChecklistIndex;
            }
        });
        var scriptEl = scope.querySelector
            ? scope.querySelector('#workflow-v2-stages-data')
            : document.getElementById('workflow-v2-stages-data');
        if (scriptEl) {
            scriptEl.textContent = JSON.stringify(data);
        }
        return data;
    }

    function completeWorkflowChecklistItem(checkbox, root) {
        var urls = workflowUrls();
        if (!urls.completeWorkflowChecklist) {
            alert('Workflow configuration error. Please refresh the page.');
            checkbox.checked = false;
            return;
        }

        var checklistId = checkbox.getAttribute('data-checklist-id');
        var matterId = getWorkflowMatterId(root);
        if (!checklistId || !matterId) {
            checkbox.checked = false;
            alert('Checklist item is not ready to complete yet.');
            return;
        }

        if (!confirm('Mark this checklist item as complete? This cannot be undone.')) {
            checkbox.checked = false;
            return;
        }

        checkbox.disabled = true;
        checkbox.checked = true;

        fetch(urls.completeWorkflowChecklist, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                matter_id: matterId,
                checklist_id: checklistId
            })
        })
        .then(function(r) {
            return r.json().then(function(data) {
                return { ok: r.ok, data: data };
            });
        })
        .then(function(result) {
            var data = result.data || {};
            if (!result.ok || !data.status) {
                checkbox.checked = false;
                checkbox.disabled = false;
                alert(data.message || 'Failed to complete checklist item.');
                return;
            }

            var scope = root || document.getElementById('workflow-tab') || document;
            applyChecklistCompletionToStageData(scope, data);
            var panel = scope.querySelector
                ? scope.querySelector('#workflow-v2-panel')
                : document.getElementById('workflow-v2-panel');
            var viewStageId = panel
                ? panel.getAttribute('data-view-stage-id')
                : null;
            if (viewStageId) {
                showWorkflowV2Stage(viewStageId, scope);
            }
        })
        .catch(function(err) {
            console.error(err);
            checkbox.checked = false;
            checkbox.disabled = false;
            alert('An error occurred while completing the checklist item.');
        });
    }

    var workflowChecklistBound = false;

    function bindWorkflowChecklistInteractions() {
        if (workflowChecklistBound) {
            return;
        }
        workflowChecklistBound = true;

        document.addEventListener('click', function(e) {
            var checkbox = e.target.closest('.workflow-v2-checklist-checkbox');
            if (!checkbox) {
                return;
            }

            var scope = document.getElementById('workflow-tab');
            if (!scope || !scope.contains(checkbox) || !isWorkflowChecklistInteractive(scope)) {
                return;
            }

            // Always intercept — browser may already flip checked before this handler runs
            e.preventDefault();

            if (checkbox.disabled) {
                return;
            }

            var item = checkbox.closest('.workflow-v2-checklist-item');
            if (item && item.classList.contains('is-done')) {
                checkbox.checked = true;
                return;
            }

            // Reset any native toggle; completion flow owns checked state after confirm
            checkbox.checked = false;
            completeWorkflowChecklistItem(checkbox, scope);
        });
    }

    var workflowFileNoteBound = false;

    function applyFileNoteToStageData(scope, stageId, body) {
        var data = parseWorkflowV2StagesData(scope);
        if (!data || !data.stages) {
            return;
        }
        data.stages.forEach(function(stage) {
            if (String(stage.id) === String(stageId)) {
                stage.fileNoteBody = body || '';
            }
        });
        var scriptEl = scope.querySelector
            ? scope.querySelector('#workflow-v2-stages-data')
            : document.getElementById('workflow-v2-stages-data');
        if (scriptEl) {
            scriptEl.textContent = JSON.stringify(data);
        }
    }

    /**
     * Save pending Workflow tab file note (if any). Resolves even when there is nothing to save.
     * Rejects only when a note was entered but save failed.
     */
    function savePendingWorkflowFileNote() {
        var scope = document.getElementById('workflow-tab');
        if (!scope || !isWorkflowChecklistInteractive(scope)) {
            return Promise.resolve(null);
        }

        var urls = workflowUrls();
        var textarea = scope.querySelector('#workflow-v2-file-note-input');
        var fileNoteSection = scope.querySelector('#workflow-v2-file-note-section');
        if (!textarea || !fileNoteSection || fileNoteSection.style.display === 'none' || textarea.disabled) {
            return Promise.resolve(null);
        }

        var note = String(textarea.value || '').trim();
        if (!note) {
            return Promise.resolve(null);
        }

        if (!urls.saveWorkflowFileNote) {
            return Promise.reject(new Error('Workflow configuration error. Please refresh the page.'));
        }

        var matterId = getWorkflowMatterId(scope);
        var panel = scope.querySelector('#workflow-v2-panel');
        var stageId = panel ? panel.getAttribute('data-view-stage-id') : '';
        var data = parseWorkflowV2StagesData(scope);
        if (!stageId && data) {
            stageId = data.currentStageId ? String(data.currentStageId) : '';
        }

        if (!matterId || !stageId) {
            return Promise.reject(new Error('Unable to save file note for this stage.'));
        }

        return fetch(urls.saveWorkflowFileNote, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                matter_id: matterId,
                workflow_stage_id: stageId,
                note: note
            })
        })
        .then(function(r) {
            return r.json().then(function(payload) {
                return { ok: r.ok, data: payload };
            });
        })
        .then(function(result) {
            var payload = result.data || {};
            if (!result.ok || !payload.status) {
                throw new Error(payload.message || 'Failed to save file note.');
            }
            var body = payload.file_note_body || '';
            applyFileNoteToStageData(scope, stageId, body);
            var historyEl = scope.querySelector('#workflow-v2-file-note-history');
            if (historyEl) {
                historyEl.textContent = body;
                historyEl.classList.toggle('is-empty', !body);
                historyEl.style.display = body ? '' : 'none';
            }
            textarea.value = '';
            return payload;
        });
    }

    function proceedWorkflowTabAfterFileNote(matterId, nextStageName, nextBtn) {
        savePendingWorkflowFileNote()
            .then(function() {
                if (nextStageName && nextStageName.toLowerCase() === 'decision received') {
                    document.getElementById('decision-received-matter-id').value = matterId;
                    document.getElementById('decision-outcome').value = '';
                    document.getElementById('decision-note').value = '';
                    var outcomeErr = document.querySelector('.decision-outcome-error strong');
                    var noteErr = document.querySelector('.decision-note-error strong');
                    if (outcomeErr) outcomeErr.textContent = '';
                    if (noteErr) noteErr.textContent = '';
                    $('#decision-received-modal').modal('show');
                    return;
                }
                doProceedToNextStage(matterId, null, null, nextBtn);
            })
            .catch(function(err) {
                alert((err && err.message) ? err.message : 'Failed to save file note.');
            });
    }

    function initWorkflowV2StageNavigation() {
        var workflowTab = document.getElementById('workflow-tab');
        if (!workflowTab) {
            return;
        }

        bindWorkflowV2StageNavigation(workflowTab);
        bindWorkflowChecklistInteractions();
        refreshWorkflowV2Icons(workflowTab);
        var panel = workflowTab.querySelector('#workflow-v2-panel');
        if (panel) {
            var viewStageId = panel.getAttribute('data-view-stage-id');
            if (viewStageId) {
                showWorkflowV2Stage(viewStageId, workflowTab);
            } else {
                var advanceBtn = workflowTab.querySelector('#workflow-tab-proceed-to-next-stage');
                var data = parseWorkflowV2StagesData(workflowTab);
                var currentStageData = data && data.stages
                    ? data.stages.find(function(s) {
                        return String(s.id) === String(data.currentStageId);
                    })
                    : null;
                var outstanding = currentStageData ? (currentStageData.outstandingRequired || 0) : 0;
                var hasNext = !!(currentStageData && data.stages.some(function(s) {
                    return s.index > currentStageData.index;
                }));
                setWorkflowAdvanceButtonState(advanceBtn, true, outstanding, !hasNext);
            }
        }
    }

    function refreshWorkflowTab() {
        return refreshTabPane('#workflow-tab').then(function() {
            ensureStageNavBackButtonVisible();
            initWorkflowV2StageNavigation();
            refreshActivityFeedIfVisible();
        });
    }

    function onWorkflowTabSuccess(message) {
        if (message) {
            alert(message);
        }
        return refreshWorkflowTab().catch(function(err) {
            console.error('[WorkflowTab] Partial refresh failed, falling back to full reload', err);
            window.location.reload();
        });
    }

    function onClientPortalStageUpdateSuccess(message) {
        if (message) {
            alert(message);
        }
        return refreshClientPortalTab().catch(function(err) {
            console.error('[WorkflowTab] Client portal partial refresh failed, falling back to full reload', err);
            window.location.reload();
        });
    }

    function onBackToPreviousStageSuccess(btn, message) {
        var activeTab = getActiveTabId();
        if (message) {
            alert(message);
        }

        if (activeTab === 'workflow' || btn.id === 'workflow-tab-back-to-previous-stage') {
            return refreshWorkflowTab().catch(function(err) {
                console.error('[WorkflowTab] Partial refresh after back failed, falling back to full reload', err);
                window.location.reload();
            });
        }

        if (activeTab === 'client_portal' || btn.id === 'back-to-previous-stage') {
            return refreshClientPortalTab().catch(function(err) {
                console.error('[WorkflowTab] Client portal partial refresh after back failed, falling back to full reload', err);
                window.location.reload();
            });
        }

        window.location.reload();
    }

    function handleBackToPreviousStage(btn) {
        var urls = workflowUrls();
        if (!urls.updatePreviousStage) {
            alert('Workflow configuration error. Please refresh the page.');
            return;
        }

        var matterId = btn.getAttribute('data-matter-id');
        if (!matterId) {
            alert('Error: Matter ID not found');
            return;
        }
        if (!confirm('Are you sure you want to move back to the previous stage?')) {
            return;
        }

        var orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = crmI('fas fa-spinner fa-spin');

        var payload = { matter_id: matterId };
        if (getActiveTabId() === 'client_portal' || btn.id === 'back-to-previous-stage') {
            payload.source = 'client_portal';
        }

        fetch(urls.updatePreviousStage, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status) {
                onBackToPreviousStageSuccess(
                    btn,
                    data.message || 'Matter has been successfully moved to the previous stage.'
                );
            } else {
                alert(data.message || 'Failed to move to previous stage.');
                btn.disabled = false;
                btn.innerHTML = orig;
                if (data.is_first_stage) {
                    btn.disabled = true;
                }
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('An error occurred.');
            btn.disabled = false;
            btn.innerHTML = orig;
        });
    }

    function saveMatterDeadline(matterId, setDeadline, deadline, onComplete) {
        var urls = workflowUrls();
        if (!matterId || !urls.updateDeadline) {
            if (typeof onComplete === 'function') {
                onComplete(false);
            }
            return;
        }

        var payload = { matter_id: matterId, set_deadline: setDeadline };
        if (setDeadline && deadline) {
            payload.deadline = deadline;
        }

        fetch(urls.updateDeadline, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status) {
                if (typeof $ !== 'undefined') {
                    $('#workflow-set-deadline-modal').modal('hide');
                }
                onWorkflowTabSuccess(null);
                if (typeof onComplete === 'function') {
                    onComplete(true);
                }
            } else {
                alert(data.message || 'Failed to update deadline.');
                if (typeof onComplete === 'function') {
                    onComplete(false);
                }
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('An error occurred.');
            if (typeof onComplete === 'function') {
                onComplete(false);
            }
        });
    }

    function doProceedToNextStage(matterId, decisionOutcome, decisionNote, btnEl) {
        var urls = workflowUrls();
        if (!urls.updateNextStage) {
            alert('Workflow configuration error. Please refresh the page.');
            return;
        }

        var btn = btnEl || getWorkflowTabAdvanceButton();
        var isClientPortal = btn && btn.id === 'proceed-to-next-stage';
        var orig = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = crmI('fas fa-spinner fa-spin');
        }

        var payload = { matter_id: matterId };
        if (isClientPortal) {
            payload.source = 'client_portal';
        }
        if (decisionOutcome) payload.decision_outcome = decisionOutcome;
        if (decisionNote) payload.decision_note = decisionNote;

        fetch(urls.updateNextStage, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status) {
                var successMessage = data.message || 'Matter has been successfully moved to the next stage.';
                if (isClientPortal) {
                    onClientPortalStageUpdateSuccess(successMessage);
                } else {
                    onWorkflowTabSuccess(successMessage);
                }
            } else {
                alert(data.message || 'Failed to move to next stage.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (data.is_last_stage) btn.disabled = true;
                }
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('An error occurred.');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        });
    }

    function bindWorkflowTabHandlers() {
        if (initialized) {
            return;
        }
        initialized = true;

        document.addEventListener('click', function(e) {
            var clientPortalNextBtn = e.target.closest('#proceed-to-next-stage');
            if (clientPortalNextBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();

                var cpMatterId = clientPortalNextBtn.getAttribute('data-matter-id');
                var cpNextStageName = (clientPortalNextBtn.getAttribute('data-next-stage-name') || '').trim();
                if (!cpMatterId) {
                    alert('Error: Matter ID not found');
                    return;
                }

                if (cpNextStageName && cpNextStageName.toLowerCase() === 'decision received') {
                    document.getElementById('decision-received-matter-id').value = cpMatterId;
                    document.getElementById('decision-outcome').value = '';
                    document.getElementById('decision-note').value = '';
                    var cpOutcomeErr = document.querySelector('.decision-outcome-error strong');
                    var cpNoteErr = document.querySelector('.decision-note-error strong');
                    if (cpOutcomeErr) cpOutcomeErr.textContent = '';
                    if (cpNoteErr) cpNoteErr.textContent = '';
                    $('#decision-received-modal').modal('show');
                    return;
                }

                if (!confirm('Are you sure you want to proceed to the next stage?')) return;
                doProceedToNextStage(cpMatterId, null, null, clientPortalNextBtn);
                return;
            }

            var nextBtn = e.target.closest('#workflow-tab-proceed-to-next-stage, #workflow-tab-proceed-to-next-stage-header, #workflow-tab .js-workflow-advance-btn');
            if (nextBtn) {
                e.preventDefault();
                if (nextBtn.disabled) {
                    return;
                }
                var matterId = nextBtn.getAttribute('data-matter-id');
                var nextStageName = (nextBtn.getAttribute('data-next-stage-name') || '').trim();
                if (!matterId) {
                    alert('Error: Matter ID not found');
                    return;
                }

                var workflowTab = document.getElementById('workflow-tab');
                if (workflowTab && isWorkflowChecklistInteractive(workflowTab)) {
                    var stageData = parseWorkflowV2StagesData(workflowTab);
                    var currentStage = stageData && stageData.stages
                        ? stageData.stages.find(function(s) {
                            return String(s.id) === String(stageData.currentStageId);
                        })
                        : null;
                    if (currentStage && (parseInt(currentStage.outstandingRequired, 10) || 0) > 0) {
                        alert('Complete all required checklist items before advancing to the next stage.');
                        return;
                    }
                }

                if (!confirm('Are you sure you want to proceed to the next stage?')) return;
                proceedWorkflowTabAfterFileNote(matterId, nextStageName, nextBtn);
                return;
            }

            var prevBtn = e.target.closest('#workflow-tab-back-to-previous-stage, #back-to-previous-stage');
            if (prevBtn) {
                e.preventDefault();
                e.stopImmediatePropagation();
                handleBackToPreviousStage(prevBtn);
                return;
            }

            var changeWorkflowBtn = e.target.closest('#workflow-tab-change-workflow');
            if (changeWorkflowBtn) {
                e.preventDefault();
                var matterIdCw = changeWorkflowBtn.getAttribute('data-matter-id');
                var currentWorkflowId = changeWorkflowBtn.getAttribute('data-current-workflow-id');
                if (!matterIdCw) {
                    alert('Error: Matter ID not found');
                    return;
                }
                document.getElementById('change-workflow-matter-id').value = matterIdCw;
                var select = document.getElementById('change-workflow-select');
                if (select && currentWorkflowId) {
                    select.value = currentWorkflowId;
                }
                $('#change-workflow-modal').modal('show');
                return;
            }

            var discontinueBtn = e.target.closest('#workflow-tab-discontinue');
            if (discontinueBtn) {
                e.preventDefault();
                var matterIdDisc = discontinueBtn.getAttribute('data-matter-id');
                if (!matterIdDisc) {
                    alert('Error: Matter ID not found');
                    return;
                }
                document.getElementById('discontinue-matter-id').value = matterIdDisc;
                document.getElementById('discontinue-reason').value = '';
                document.getElementById('discontinue-notes').value = '';
                var discErr = document.querySelector('.discontinue-reason-error strong');
                if (discErr) discErr.textContent = '';
                $('#discontinue-matter-modal').modal('show');
                return;
            }

            var setDeadlineBtn = e.target.closest('#workflow-set-deadline');
            if (setDeadlineBtn) {
                e.preventDefault();
                var matterIdDl = setDeadlineBtn.getAttribute('data-matter-id');
                if (!matterIdDl) {
                    alert('Error: Matter ID not found');
                    return;
                }

                var matterIdInput = document.getElementById('workflow-set-deadline-matter-id');
                var dateInput = document.getElementById('workflow-set-deadline-date');
                var clearBtn = document.getElementById('workflow-set-deadline-clear');
                var errEl = document.querySelector('.workflow-set-deadline-error strong');
                if (!matterIdInput || !dateInput) {
                    return;
                }

                var currentDeadline = setDeadlineBtn.getAttribute('data-current-deadline') || '';
                matterIdInput.value = matterIdDl;
                dateInput.value = currentDeadline;
                if (errEl) errEl.textContent = '';
                if (clearBtn) {
                    clearBtn.style.display = currentDeadline ? '' : 'none';
                }
                $('#workflow-set-deadline-modal').modal('show');
                return;
            }

            var setDeadlineSubmit = e.target.closest('#workflow-set-deadline-submit');
            if (setDeadlineSubmit) {
                e.preventDefault();
                var matterIdSave = document.getElementById('workflow-set-deadline-matter-id')?.value;
                var deadlineVal = document.getElementById('workflow-set-deadline-date')?.value;
                var deadlineErr = document.querySelector('.workflow-set-deadline-error strong');
                if (deadlineErr) deadlineErr.textContent = '';

                if (!matterIdSave) {
                    alert('Error: Matter ID not found');
                    return;
                }
                if (!deadlineVal) {
                    if (deadlineErr) deadlineErr.textContent = 'Please select a date.';
                    return;
                }

                var origDl = setDeadlineSubmit.innerHTML;
                setDeadlineSubmit.disabled = true;
                setDeadlineSubmit.innerHTML = crmI('fas fa-spinner fa-spin');
                saveMatterDeadline(matterIdSave, true, deadlineVal, function(success) {
                    setDeadlineSubmit.disabled = false;
                    setDeadlineSubmit.innerHTML = origDl;
                    if (!success && deadlineErr) {
                        deadlineErr.textContent = 'Failed to save deadline.';
                    }
                });
                return;
            }

            var setDeadlineClear = e.target.closest('#workflow-set-deadline-clear');
            if (setDeadlineClear) {
                e.preventDefault();
                var matterIdClear = document.getElementById('workflow-set-deadline-matter-id')?.value;
                if (!matterIdClear) {
                    alert('Error: Matter ID not found');
                    return;
                }
                if (!confirm('Clear the deadline for this matter?')) {
                    return;
                }

                var origClear = setDeadlineClear.innerHTML;
                setDeadlineClear.disabled = true;
                setDeadlineClear.innerHTML = crmI('fas fa-spinner fa-spin');
                saveMatterDeadline(matterIdClear, false, null, function(success) {
                    setDeadlineClear.disabled = false;
                    setDeadlineClear.innerHTML = origClear;
                    if (!success) {
                        alert('Failed to clear deadline.');
                    }
                });
                return;
            }

            var reopenBtn = e.target.closest('.matter-detail-reopen-btn');
            if (reopenBtn) {
                e.preventDefault();
                var matterIdReopen = reopenBtn.getAttribute('data-matter-id');
                if (!matterIdReopen) return;
                if (!confirm('Reopen this matter? It will be moved back to active matters.')) return;

                reopenBtn.disabled = true;
                var origReopen = reopenBtn.innerHTML;
                reopenBtn.innerHTML = crmI('fas fa-spinner fa-spin') + ' Reopening...';

                var urlsReopen = workflowUrls();
                fetch(urlsReopen.reopen, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        matter_id: matterIdReopen,
                        current_tab: getActiveTabId()
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.status) {
                        if (getActiveTabId() === 'workflow') {
                            onWorkflowTabSuccess(null);
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(data.message || 'Failed to reopen matter.');
                        reopenBtn.disabled = false;
                        reopenBtn.innerHTML = origReopen;
                    }
                })
                .catch(function() {
                    alert('An error occurred. Please try again.');
                    reopenBtn.disabled = false;
                    reopenBtn.innerHTML = origReopen;
                });
                return;
            }

            var changeWorkflowSubmit = e.target.closest('#change-workflow-submit');
            if (changeWorkflowSubmit) {
                e.preventDefault();
                var matterIdSubmit = document.getElementById('change-workflow-matter-id')?.value;
                var workflowId = document.getElementById('change-workflow-select')?.value;
                if (!matterIdSubmit || !workflowId) {
                    alert('Please select a workflow.');
                    return;
                }

                var urlsCw = workflowUrls();
                var origCw = changeWorkflowSubmit.innerHTML;
                changeWorkflowSubmit.disabled = true;
                changeWorkflowSubmit.innerHTML = crmI('fas fa-spinner fa-spin') + ' Processing...';

                fetch(urlsCw.changeWorkflow, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ matter_id: matterIdSubmit, workflow_id: workflowId })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    changeWorkflowSubmit.disabled = false;
                    changeWorkflowSubmit.innerHTML = origCw;
                    if (data.status) {
                        $('#change-workflow-modal').modal('hide');
                        onWorkflowTabSuccess(data.message || 'Workflow changed successfully.');
                    } else {
                        alert(data.message || 'Failed to change workflow.');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    changeWorkflowSubmit.disabled = false;
                    changeWorkflowSubmit.innerHTML = origCw;
                    alert('An error occurred.');
                });
                return;
            }

            var discontinueSubmit = e.target.closest('#discontinue-matter-submit');
            if (discontinueSubmit) {
                e.preventDefault();
                var reasonSelect = document.getElementById('discontinue-reason');
                var reason = reasonSelect ? reasonSelect.value : '';
                var matterIdDiscSubmit = document.getElementById('discontinue-matter-id')?.value;
                var notes = document.getElementById('discontinue-notes')?.value || '';
                var errEl = document.querySelector('.discontinue-reason-error strong');

                if (!reason || reason.trim() === '') {
                    if (errEl) errEl.textContent = 'Please select a reason for discontinuing.';
                    return;
                }
                if (errEl) errEl.textContent = '';

                var origDisc = discontinueSubmit.innerHTML;
                discontinueSubmit.disabled = true;
                discontinueSubmit.innerHTML = crmI('fas fa-spinner fa-spin') + ' Processing...';

                var urlsDisc = workflowUrls();
                fetch(urlsDisc.discontinue, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        matter_id: matterIdDiscSubmit,
                        discontinue_reason: reason,
                        discontinue_notes: notes,
                        current_tab: getActiveTabId() || 'personaldetails'
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    discontinueSubmit.disabled = false;
                    discontinueSubmit.innerHTML = origDisc;
                    if (data.status) {
                        $('#discontinue-matter-modal').modal('hide');
                        alert(data.message || 'Matter has been discontinued.');
                        var clientEncodeId = window.ClientDetailConfig ? window.ClientDetailConfig.encodeId : null;
                        if (data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else if (clientEncodeId) {
                            window.location.href = '/clients/detail/' + clientEncodeId;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert(data.message || 'Failed to discontinue matter.');
                    }
                })
                .catch(function(err) {
                    console.error(err);
                    discontinueSubmit.disabled = false;
                    discontinueSubmit.innerHTML = origDisc;
                    alert('An error occurred.');
                });
            }
        });
    }

    window.refreshWorkflowTab = refreshWorkflowTab;
    window.refreshClientPortalTab = refreshClientPortalTab;
    window.ensureWorkflowV2StageIcons = refreshWorkflowV2Icons;
    window.handleWorkflowStageUpdateSuccess = onWorkflowTabSuccess;
    window.handleClientPortalStageUpdateSuccess = onClientPortalStageUpdateSuccess;
    window.workflowTabDoProceedToNextStage = doProceedToNextStage;
    window.ensureStageNavBackButtonVisible = ensureStageNavBackButtonVisible;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bindWorkflowTabHandlers();
            bindClientPortalSubTabDelegation();
            ensureStageNavBackButtonVisible();
            initWorkflowV2StageNavigation();
        });
    } else {
        bindWorkflowTabHandlers();
        bindClientPortalSubTabDelegation();
        ensureStageNavBackButtonVisible();
        initWorkflowV2StageNavigation();
    }
})();
