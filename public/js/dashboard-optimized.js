// Dashboard Optimized JavaScript
$(document).ready(function() {
    // Check if required objects are defined
    if (typeof window.dashboardRoutes === 'undefined') {
        console.error('Dashboard routes not defined. Please ensure routes are loaded before this script.');
        return;
    }
    
    if (typeof window.dashboardData === 'undefined') {
        console.warn('Dashboard data not defined. Some features may not work correctly.');
    }
    
    initializeDashboard();
    applyInitialColumnVisibility();
    initializeEventHandlers();
});

function initializeDashboard() {
    console.log('Dashboard initialized');
}

function initializeEventHandlers() {
    // Extend deadline
    $(document).on('click', '#extend_deadline', extendDeadline);
    $(document).delegate('.btn-extend_note_deadline', 'click', openExtendDeadlineModal);
    
    // Filter form
    $('#filterForm').on('submit', submitFilterForm);
    $('.stage-select').on('change', function() {
        $('#filterForm').submit();
    });

    // Column toggle
    $('#columnToggleBtn').on('click', toggleColumnDropdown);
    $('input[name="column"]').on('change', function() {
        toggleColumn($(this));
    });
    $('#toggleAllColumns').on('change', function() {
        toggleAllColumns($(this));
    });

    // Search and actions
    $('input[name="client_name"]').on('blur keypress', handleSearchInput);
    $(document).on('click', '.action-button', handleActionClick);

    // Completion notes modal - confirm task completion
    $(document).on('click', '#dashboardConfirmTaskCompletion', function() {
        if (dashboardPendingTaskId) {
            var notes = $('#dashboardCompletionNotes').val();
            $('#dashboardCompletionNotesModal').modal('hide');
            completeTask(dashboardPendingTaskId, dashboardPendingUniqueGroupId, notes);
            dashboardPendingTaskId = null;
            dashboardPendingUniqueGroupId = null;
        }
    });

    // Clear pending completion when modal is closed without completing
    $(document).on('hidden.bs.modal', '#dashboardCompletionNotesModal', function() {
        dashboardPendingTaskId = null;
        dashboardPendingUniqueGroupId = null;
    });

    initializeDashboardMattersAjaxPagination();
}

/**
 * Client matters table: load next/prev page via fragment (no full page reload).
 */
function initializeDashboardMattersAjaxPagination() {
    if (typeof window.dashboardRoutes === 'undefined' || !window.dashboardRoutes.mattersFragment) {
        return;
    }
    var dashboardBase = window.dashboardRoutes.dashboard;
    $(document).on('click', '#dashboardMattersFragment .pagination-links a[href]', function(e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.which === 2) {
            return;
        }
        var href = $(this).attr('href');
        if (!href || href === '#' || href.indexOf('javascript:') === 0) {
            return;
        }
        var targetUrl;
        try {
            targetUrl = new URL(href, window.location.origin);
        } catch (err) {
            return;
        }
        var dashUrl;
        try {
            dashUrl = new URL(dashboardBase, window.location.origin);
        } catch (err2) {
            return;
        }
        if (targetUrl.origin !== window.location.origin || targetUrl.pathname !== dashUrl.pathname) {
            return;
        }
        e.preventDefault();
        loadDashboardMattersFragment(targetUrl.toString(), true);
    });

    window.addEventListener('popstate', function(ev) {
        if (!document.getElementById('dashboardMattersFragment') || !window.dashboardRoutes.mattersFragment) {
            return;
        }
        loadDashboardMattersFragmentForHistory(ev.state);
    });
}

/**
 * Build matters fragment fetch query: filters from current location; page from history state, else URL, else 1.
 */
function buildDashboardMattersFragmentFetchQuery(historyState) {
    var fetchParams = new URLSearchParams(window.location.search);
    if (historyState && historyState.dashboardMattersAjax && historyState.page != null) {
        fetchParams.set('page', String(parseInt(historyState.page, 10) || 1));
    } else if (!fetchParams.has('page')) {
        fetchParams.set('page', '1');
    }
    return fetchParams.toString();
}

function loadDashboardMattersFragmentForHistory(state) {
    var frag = document.getElementById('dashboardMattersFragment');
    if (!frag || !window.dashboardRoutes.mattersFragment) {
        return;
    }
    var qs = buildDashboardMattersFragmentFetchQuery(state);
    var fetchUrl = window.dashboardRoutes.mattersFragment + (qs ? '?' + qs : '');
    frag.classList.add('dashboard-matters-fragment--loading');
    fetch(fetchUrl, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html',
        },
    })
        .then(function(res) {
            if (!res.ok) {
                throw new Error('Fragment request failed');
            }
            return res.text();
        })
        .then(function(html) {
            frag.innerHTML = html;
            syncDashboardMattersTotalFromFragment(frag);
            applyInitialColumnVisibility();
            if (typeof refreshLucideIcons === 'function') {
                refreshLucideIcons(frag);
            }
        })
        .catch(function() {
            window.location.reload();
        })
        .finally(function() {
            frag.classList.remove('dashboard-matters-fragment--loading');
        });
}

function loadDashboardMattersFragment(dashboardAbsoluteUrl, pushHistory) {
    var frag = document.getElementById('dashboardMattersFragment');
    if (!frag || !window.dashboardRoutes.mattersFragment) {
        return;
    }
    var u;
    try {
        u = new URL(dashboardAbsoluteUrl, window.location.origin);
    } catch (e) {
        window.location.href = dashboardAbsoluteUrl;
        return;
    }
    var qs = u.searchParams.toString();
    var fetchUrl = window.dashboardRoutes.mattersFragment + (qs ? '?' + qs : '');
    frag.classList.add('dashboard-matters-fragment--loading');
    fetch(fetchUrl, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'text/html',
        },
    })
        .then(function(res) {
            if (!res.ok) {
                throw new Error('Fragment request failed');
            }
            return res.text();
        })
        .then(function(html) {
            frag.innerHTML = html;
            syncDashboardMattersTotalFromFragment(frag);
            if (pushHistory) {
                var pageNum = parseInt(u.searchParams.get('page'), 10) || 1;
                var displayParams = new URLSearchParams(u.searchParams.toString());
                displayParams.delete('page');
                var displaySearch = displayParams.toString();
                var displayUrl = u.pathname + (displaySearch ? '?' + displaySearch : '');
                history.pushState(
                    { dashboardMattersAjax: true, page: pageNum },
                    '',
                    displayUrl
                );
            }
            applyInitialColumnVisibility();
            if (typeof refreshLucideIcons === 'function') {
                refreshLucideIcons(frag);
            }
        })
        .catch(function() {
            window.location.href = u.pathname + u.search;
        })
        .finally(function() {
            frag.classList.remove('dashboard-matters-fragment--loading');
        });
}

function syncDashboardMattersTotalFromFragment(frag) {
    var meta = frag.querySelector('.dashboard-matters-meta');
    var totalEl = document.querySelector('.cases-overview-header .total-count');
    if (!meta || !totalEl) {
        return;
    }
    var t = meta.getAttribute('data-total');
    if (t === null || t === '') {
        return;
    }
    totalEl.textContent = '(' + t + ' total)';
}

function extendDeadline() {
    if (!window.dashboardRoutes || !window.dashboardRoutes.extendDeadline) {
        console.error('Extend deadline route not defined');
        showNotification('Configuration error: Extend deadline route not available.', 'error');
        return;
    }
    
    $(".popuploader").show();
    let flag = true;
    $(".custom-error").remove();

    if ($('#assignnote').val() === '') {
        $('#assignnote').after("<span class='custom-error'>Note field is required.</span>");
        flag = false;
    }
    
    if ($('#note_deadline').val() === '') {
        $('#note_deadline').after("<span class='custom-error'>Note Deadline is required.</span>");
        flag = false;
    }

    if (flag) {
        $.ajax({
            type: 'POST',
            url: window.dashboardRoutes.extendDeadline,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            data: {
                note_id: $('#note_id').val(),
                unique_group_id: $('#unique_group_id').val(),
                description: $('#assignnote').val(),
                note_deadline: $('#note_deadline').val()
            },
            success: function(response) {
                $('.popuploader').hide();
                $('#extend_note_popup').modal('hide');
                showNotification('Deadline extended successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            },
            error: function() {
                $('.popuploader').hide();
                showNotification('Failed to extend deadline.', 'error');
            }
        });
    } else {
        $('.popuploader').hide();
    }
}

function openExtendDeadlineModal() {
    var $button = $(this);
    $('#note_id').val($button.attr("data-noteid"));
    $('#unique_group_id').val($button.attr("data-uniquegroupid"));
    $('#assignnote').val($button.attr("data-assignnote"));
    $('#note_deadline').val($button.attr("data-deadlinedate"));
    $('#extend_note_popup').modal('show');
}

function submitFilterForm(e) {
    e.preventDefault();
    const formData = $('#filterForm').serialize();
    const url = new URL(window.location.href);
    const params = new URLSearchParams(formData);
    params.delete('page');
    url.search = params.toString();
    window.location.href = url.toString();
}

function toggleColumnDropdown() {
    $('#columnDropdown').toggleClass('show');
}

function toggleColumn($checkbox) {
    const columnValue = $checkbox.val();
    const isChecked = $checkbox.is(':checked');
    
    if (isChecked) {
        $('.col-' + columnValue).removeClass('col-hidden');
    } else {
        $('.col-' + columnValue).addClass('col-hidden');
    }
    
    updateVisibleCount();
    updateToggleAllState();
    saveColumnPreferences();
}

function toggleAllColumns($checkbox) {
    const isChecked = $checkbox.is(':checked');
    $('input[name="column"]').prop('checked', isChecked);
    
    if (isChecked) {
        $('[class*="col-"]').removeClass('col-hidden');
    } else {
        $('[class*="col-"]').addClass('col-hidden');
    }
    
    updateVisibleCount();
    saveColumnPreferences();
}

function updateVisibleCount() {
    const visibleCount = $('input[name="column"]:checked').length;
    $('.visible-count').text(visibleCount);
}

function updateToggleAllState() {
    const totalColumns = $('input[name="column"]').length;
    const checkedColumns = $('input[name="column"]:checked').length;
    
    if (checkedColumns === 0) {
        $('#toggleAllColumns').prop('indeterminate', false).prop('checked', false);
    } else if (checkedColumns === totalColumns) {
        $('#toggleAllColumns').prop('indeterminate', false).prop('checked', true);
    } else {
        $('#toggleAllColumns').prop('indeterminate', true);
    }
}

function saveColumnPreferences() {
    const visibleColumns = [];
    $('input[name="column"]:checked').each(function() {
        visibleColumns.push($(this).val());
    });

    $.ajax({
        url: window.dashboardRoutes.columnPreferences,
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: { visible_columns: visibleColumns },
        success: function() {
            console.log('Column preferences saved');
        },
        error: function() {
            console.error('Failed to save column preferences');
        }
    });
}

function applyInitialColumnVisibility() {
    const visibleColumns = window.dashboardData.visibleColumns || [];
    const allColumns = ['matter', 'client_id', 'client_name', 'dob', 'migration_agent', 'person_responsible', 'person_assisting', 'stage'];
    
    allColumns.forEach(function(column) {
        if (!visibleColumns.includes(column)) {
            $('.col-' + column).addClass('col-hidden');
            $('input[name="column"][value="' + column + '"]').prop('checked', false);
        }
    });
    
    updateVisibleCount();
    updateToggleAllState();
}

function handleSearchInput(e) {
    if (e.type === 'blur' && $(this).val().trim() !== '') {
        $('#filterForm').submit();
    }
    if (e.which === 13) {
        e.preventDefault();
        $('#filterForm').submit();
    }
}

function handleActionClick() {
    window.location.href = $(this).data('href');
}

window.clearFiltersAndReset = function() {
    if (!window.dashboardRoutes || !window.dashboardRoutes.dashboard) {
        console.error('Dashboard route not defined');
        showNotification('Configuration error: Dashboard route not available.', 'error');
        return;
    }
    window.location.href = window.dashboardRoutes.dashboard;
}

window.closeNotesDeadlineAction = function(noteid, noteuniqueid) {
    console.log('closeNotesDeadlineAction called with:', { noteid, noteuniqueid });
    
    if (!window.dashboardRoutes || !window.dashboardRoutes.updateTaskCompleted) {
        console.error('Update task completed route not defined');
        showNotification('Configuration error: Update task route not available.', 'error');
        return;
    }
    
    if (confirm('Are you sure, you want to close this note deadline?')) {
        if (noteid == '' && noteuniqueid == '') {
            showNotification('Please select note to close the deadline.', 'error');
            return false;
        }
        
        console.log('Sending AJAX request to:', window.dashboardRoutes.updateTaskCompleted);
        console.log('Data:', { id: noteid, unique_group_id: noteuniqueid });
        
        $('.popuploader').show();
        $.ajax({
            type: 'post',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            url: window.dashboardRoutes.updateTaskCompleted,
            data: {'id': noteid, 'unique_group_id': noteuniqueid},
            success: function(response) {
                console.log('Success response:', response);
                $('.popuploader').hide();
                showNotification('Task completed successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            },
            error: function(xhr, status, error) {
                console.error('Error response:', xhr.responseText);
                console.error('Status:', status, 'Error:', error);
                $('.popuploader').hide();
                showNotification('Failed to complete task.', 'error');
            }
        });
    } else {
        $('.popuploader').hide();
    }
}

function showNotification(message, type = 'info') {
    // Simple notification - can be enhanced with toast library
    if (type === 'error') {
        alert('Error: ' + message);
    } else if (type === 'success') {
        alert('Success: ' + message);
    } else {
        alert(message);
    }
}

// ====================
// Microsoft To Do Style Task Functions
// ====================

/** Opens the dashboard "Add My Task" popover (same UX as the header + control). */
window.openCreateTaskModal = function() {
    var $trigger = $('.todo-container .todo-header .add_my_task').first();
    if ($trigger.length) {
        $trigger.trigger('click');
    }
};

// Open Task Detail Panel
window.openTaskDetail = function(taskId) {
    const taskItem = $(`[data-task-id="${taskId}"]`);
    if (!taskItem.length) return;
    
    const panel = $('#taskDetailPanel');
    const data = taskItem.data();
    const isPersonalAction = !data.clientId;
    
    // Populate panel with task data
    $('#taskDetailTitle').text(stripHtml(data.description));
    $('#taskDetailClientName').text(data.clientName || 'Personal Action');
    $('#taskDetailClientCode').text(data.clientCode ? `(${data.clientCode})` : '');
    $('#taskDetailClientLink').attr('href', isPersonalAction ? '/action' : (data.clientDetailUrl || '#'));
    
    // Handle deadline display
    if (data.deadline) {
        $('#taskDetailDueDate').text(formatDate(data.deadline));
        $('#taskDetailDueDate').removeClass('overdue today tomorrow this-week upcoming no-deadline')
            .addClass(data.urgency);
    } else {
        $('#taskDetailDueDate').text('No deadline set');
        $('#taskDetailDueDate').removeClass('overdue today tomorrow this-week upcoming')
            .addClass('no-deadline');
    }
    
    $('#taskDetailAssigned').text(data.assignedTo);
    $('#taskDetailDescription').html(data.description);
    
    // Set checkbox state
    $('#taskDetailComplete').prop('checked', false);
    
    // Store task info in panel
    panel.data('taskId', taskId);
    panel.data('uniqueGroupId', data.uniqueGroupId);
    panel.data('noteId', taskId);
    panel.data('description', data.description);
    panel.data('deadline', data.deadlineFormatted || '');
    
    // Show panel
    panel.addClass('active');
};

// Close Task Detail Panel
window.closeTaskDetail = function() {
    $('#taskDetailPanel').removeClass('active');
};

// Pending task completion (stored when modal opens)
var dashboardPendingTaskId = null;
var dashboardPendingUniqueGroupId = null;

// Handle Task Complete from Checkbox - open completion notes modal
window.handleTaskComplete = function(taskId, uniqueGroupId) {
    dashboardPendingTaskId = taskId;
    dashboardPendingUniqueGroupId = uniqueGroupId;
    $('#dashboardCompletionNotes').val('');
    $('#dashboardCompletionNotesModal').modal('show');
};

// Complete Task from Detail Panel - open completion notes modal
window.completeTaskFromDetail = function() {
    const panel = $('#taskDetailPanel');
    dashboardPendingTaskId = panel.data('taskId');
    dashboardPendingUniqueGroupId = panel.data('uniqueGroupId');
    $('#dashboardCompletionNotes').val('');
    $('#dashboardCompletionNotesModal').modal('show');
};

// Complete Task Function (called after modal confirm or directly)
function completeTask(taskId, uniqueGroupId, completionNotes) {
    if (!taskId) {
        showNotification('Invalid action data', 'error');
        return;
    }
    
    $('.popuploader').show();
    
    var postData = { id: taskId, unique_group_id: uniqueGroupId || '' };
    if (typeof completionNotes === 'string' && completionNotes.trim()) {
        postData.completion_notes = completionNotes.trim();
    }
    
    $.ajax({
        type: 'POST',
        url: window.dashboardRoutes.updateTaskCompleted,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: postData,
        success: function(response) {
            $('.popuploader').hide();
            if (response.success) {
                // Close detail panel
                closeTaskDetail();
                
                // Animate task removal
                const taskItem = $(`[data-task-id="${taskId}"]`);
                taskItem.css('opacity', '0.5');
                setTimeout(() => {
                    taskItem.fadeOut(300, function() {
                        $(this).remove();
                        updateTaskCount();
                    });
                }, 200);
                
                showNotification('Action completed successfully!', 'success');
            } else {
                showNotification(response.message || 'Failed to complete action', 'error');
            }
        },
        error: function(xhr, status, error) {
            $('.popuploader').hide();
            console.error('Error completing action:', error);
            showNotification('An error occurred while completing the action', 'error');
        }
    });
}

// Open Extend Modal from Task Item
window.openExtendModal = function(taskId) {
    const taskItem = $(`[data-task-id="${taskId}"]`);
    if (!taskItem.length) return;
    
    const data = taskItem.data();
    
    $('#note_id').val(taskId);
    $('#unique_group_id').val(data.uniqueGroupId);
    $('#assignnote').val(stripHtml(data.description));
    $('#note_deadline').val(data.deadlineFormatted || '');
    
    $('#extend_note_popup').modal('show');
};

// Open Add Deadline Modal for tasks without deadlines
window.openAddDeadlineModal = function(taskId) {
    openExtendModal(taskId); // Reuse the same modal
};

// Extend Task from Detail Panel
window.extendTaskFromDetail = function() {
    const panel = $('#taskDetailPanel');
    const taskId = panel.data('taskId');
    const uniqueGroupId = panel.data('uniqueGroupId');
    const description = panel.data('description');
    const deadline = panel.data('deadline');
    
    $('#note_id').val(taskId);
    $('#unique_group_id').val(uniqueGroupId);
    $('#assignnote').val(stripHtml(description));
    $('#note_deadline').val(deadline);
    
    // Close detail panel and open modal
    closeTaskDetail();
    $('#extend_note_popup').modal('show');
};

// Open Extend Modal from Task Item
window.openExtendModal = function(taskId) {
    const taskItem = $(`[data-task-id="${taskId}"]`);
    if (!taskItem.length) return;
    
    const data = taskItem.data();
    const description = stripHtml(data.description);
    const deadline = data.deadlineFormatted || formatDate(data.deadline);
    
    // Populate modal
    $('#note_id').val(taskId);
    $('#unique_group_id').val(data.uniqueGroupId);
    $('#assignnote').val(description);
    $('#note_deadline').val(deadline);
    
    // Close detail panel and open modal
    closeTaskDetail();
    $('#extend_note_popup').modal('show');
};

// Update Task Count
function updateTaskCount() {
    const count = $('.todo-task-item').length;
    $('.todo-count-badge').text(count);
    
    // Show empty state if no tasks
    if (count === 0) {
        $('.todo-task-list').hide();
        if ($('.todo-empty-state').length === 0) {
            const checkIcon = typeof crmIconAny === 'function' ? crmIconAny('fas fa-check-circle') : (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check-circle') : '<i class="fas fa-check-circle"></i>');
            const plusIcon = typeof crmIconAny === 'function' ? crmIconAny('fas fa-plus') : (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-plus') : '<i class="fas fa-plus"></i>');
            $('.todo-task-list-container').html(`
                <div class="todo-empty-state">
                    <div class="todo-empty-icon">
                        ${checkIcon}
                    </div>
                    <h4>All caught up!</h4>
                    <p>You have no tasks at the moment.</p>
                    <button type="button" class="todo-empty-add-btn" onclick="openCreateTaskModal()">
                        ${plusIcon}
                        Add a task
                    </button>
                </div>
            `);
        }
    }
}

// Helper Functions
function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    // Reset time to compare dates only
    date.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    tomorrow.setHours(0, 0, 0, 0);
    
    if (date.getTime() === today.getTime()) {
        return 'Today';
    } else if (date.getTime() === tomorrow.getTime()) {
        return 'Tomorrow';
    } else {
        const options = { weekday: 'short', month: 'short', day: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }
}

// Close panel on ESC key
$(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTaskDetail();
    }
});

// Prevent checkbox label from opening detail
$(document).on('click', '.task-detail-checkbox', function(e) {
    e.stopPropagation();
});