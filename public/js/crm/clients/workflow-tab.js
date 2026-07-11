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
            reopen: urls.reopen
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

            // Workflow v2 toolbar has its own scoped styles — avoid legacy client-portal overrides
            if (btn.closest('.workflow-v2-toolbar')) {
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
        });
    }

    function refreshClientPortalTab() {
        return refreshTabPane('#client_portal-tab').then(function() {
            ensureStageNavBackButtonVisible();
            bindClientPortalSubTabDelegation();
            return refreshSidebarMatterStatus();
        }).then(function() {
            refreshActivityFeedIfVisible();
        });
    }

    function refreshWorkflowTab() {
        return refreshTabPane('#workflow-tab').then(function() {
            ensureStageNavBackButtonVisible();
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
        btn.innerHTML = crmI('fas fa-spinner fa-spin') + ' Processing...';

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

    function saveMatterDeadline(matterId, setDeadline, deadline) {
        var urls = workflowUrls();
        if (!matterId || !urls.updateDeadline) {
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
                onWorkflowTabSuccess(null);
            } else {
                alert(data.message || 'Failed to update deadline.');
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('An error occurred.');
        });
    }

    function doProceedToNextStage(matterId, decisionOutcome, decisionNote, btnEl) {
        var urls = workflowUrls();
        if (!urls.updateNextStage) {
            alert('Workflow configuration error. Please refresh the page.');
            return;
        }

        var btn = btnEl || document.getElementById('workflow-tab-proceed-to-next-stage');
        var isClientPortal = btn && btn.id === 'proceed-to-next-stage';
        var orig = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = crmI('fas fa-spinner fa-spin') + ' Processing...';
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

        document.addEventListener('change', function(e) {
            if (e.target.id === 'workflow-set-deadline') {
                var checked = e.target.checked;
                var wrapper = document.querySelector('.workflow-deadline-date-wrapper');
                var dateInput = document.getElementById('workflow-deadline-date');
                if (!wrapper || !dateInput) return;

                wrapper.style.display = checked ? 'block' : 'none';
                if (!checked) {
                    dateInput.value = '';
                    saveMatterDeadline(e.target.getAttribute('data-matter-id'), false, null);
                } else if (dateInput.value) {
                    saveMatterDeadline(e.target.getAttribute('data-matter-id'), true, dateInput.value);
                }
                return;
            }

            if (e.target.id === 'workflow-deadline-date') {
                var setDeadlineCb = document.getElementById('workflow-set-deadline');
                if (!setDeadlineCb || !setDeadlineCb.checked) return;

                var val = e.target.value;
                var matterId = e.target.getAttribute('data-matter-id');
                var wrapper = document.querySelector('.workflow-deadline-date-wrapper');
                if (val) {
                    saveMatterDeadline(matterId, true, val);
                } else {
                    setDeadlineCb.checked = false;
                    if (wrapper) wrapper.style.display = 'none';
                    saveMatterDeadline(matterId, false, null);
                }
            }
        });

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

            var nextBtn = e.target.closest('#workflow-tab-proceed-to-next-stage');
            if (nextBtn) {
                e.preventDefault();
                var matterId = nextBtn.getAttribute('data-matter-id');
                var nextStageName = (nextBtn.getAttribute('data-next-stage-name') || '').trim();
                if (!matterId) {
                    alert('Error: Matter ID not found');
                    return;
                }

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

                if (!confirm('Are you sure you want to proceed to the next stage?')) return;
                doProceedToNextStage(matterId, null, null, nextBtn);
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
    window.handleWorkflowStageUpdateSuccess = onWorkflowTabSuccess;
    window.handleClientPortalStageUpdateSuccess = onClientPortalStageUpdateSuccess;
    window.workflowTabDoProceedToNextStage = doProceedToNextStage;
    window.ensureStageNavBackButtonVisible = ensureStageNavBackButtonVisible;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            bindWorkflowTabHandlers();
            bindClientPortalSubTabDelegation();
            ensureStageNavBackButtonVisible();
        });
    } else {
        bindWorkflowTabHandlers();
        bindClientPortalSubTabDelegation();
        ensureStageNavBackButtonVisible();
    }
})();
