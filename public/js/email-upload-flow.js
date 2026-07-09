/**
 * Email upload progress overlay + attachment storage modal helpers.
 * Loaded before emails.js; registers handlers on window.__crmEmailUploadFlow.
 */
(function(global) {
    'use strict';

    function getDeps() {
        return global.__crmEmailUploadFlowDeps || {};
    }

    let documentCategoriesCache = null;
    let documentCategoriesCacheKey = null;

    function getDocumentCategoriesCacheKey() {
        const deps = getDeps();
        const clientId = deps.getClientId && deps.getClientId();
        const matterId = deps.getMatterId && deps.getMatterId();
        if (!clientId) {
            return null;
        }
        return String(clientId) + ':' + String(matterId || '');
    }

    async function previewEmailAttachments(file) {
        const deps = getDeps();
        const clientId = deps.getClientId && deps.getClientId();
        const csrfToken = deps.getCsrfToken && deps.getCsrfToken();
        if (!clientId || !csrfToken) {
            throw new Error('Upload context not ready');
        }

        const formData = new FormData();
        formData.append('email_files[]', file);
        formData.append('client_id', clientId);
        formData.append('type', 'client');
        formData.append('_token', csrfToken);

        const response = await fetch('/email/preview-attachments', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();
        if (!response.ok || !result.status) {
            throw new Error(result.message || 'Failed to preview attachments');
        }
        return result.attachments || [];
    }

    async function loadDocumentCategoriesForAttachmentModal() {
        const deps = getDeps();
        const clientId = deps.getClientId && deps.getClientId();
        const matterId = deps.getMatterId && deps.getMatterId();
        if (!clientId) {
            return [];
        }
        const cacheKey = getDocumentCategoriesCacheKey();
        if (documentCategoriesCache && documentCategoriesCacheKey === cacheKey) {
            return documentCategoriesCache;
        }
        try {
            let url = '/email/attachment-document-categories?client_id=' + encodeURIComponent(clientId);
            if (matterId) {
                url += '&client_matter_id=' + encodeURIComponent(matterId);
            }
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            documentCategoriesCache = (response.ok && data && data.status && data.categories) ? data.categories : [];
            documentCategoriesCacheKey = cacheKey;
        } catch (e) {
            documentCategoriesCache = [];
            documentCategoriesCacheKey = cacheKey;
        }
        return documentCategoriesCache;
    }

    async function previewBatchEmailAttachments(files) {
        const byEmail = {};
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            try {
                const attachments = await previewEmailAttachments(file);
                byEmail[file.name] = (attachments || []).map(function(att) {
                    return Object.assign({}, att, { email_filename: file.name });
                });
            } catch (previewErr) {
                console.warn('Attachment preview skipped for ' + file.name + ':', previewErr);
                byEmail[file.name] = [];
            }
        }
        return byEmail;
    }

    function flattenBatchAttachments(byEmail) {
        const flat = [];
        Object.keys(byEmail).forEach(function(emailName) {
            (byEmail[emailName] || []).forEach(function(att) {
                flat.push(att);
            });
        });
        return flat;
    }

    function groupAttachmentStorageByEmail(storageList) {
        const map = {};
        (storageList || []).forEach(function(item) {
            const emailName = item.email_filename;
            if (!emailName) {
                return;
            }
            if (!map[emailName]) {
                map[emailName] = [];
            }
            map[emailName].push({
                original_filename: item.original_filename,
                filename: item.filename,
                file_name: item.file_name,
                storage_type: item.storage_type,
                category_id: item.category_id,
                doc_type: item.doc_type
            });
        });
        return map;
    }

    function getAttachmentEmailGroups(attachments) {
        const groups = [];
        const seen = {};
        (attachments || []).forEach(function(att) {
            const emailName = att.email_filename;
            if (!emailName || seen[emailName]) {
                return;
            }
            seen[emailName] = true;
            groups.push({ key: 'email-' + groups.length, name: emailName });
        });
        return groups;
    }

    function parseCategoryOptionValue(value) {
        if (!value) {
            return { categoryId: 0, docType: null };
        }
        const parts = String(value).split(':');
        if (parts.length === 2 && (parts[0] === 'personal' || parts[0] === 'visa')) {
            return {
                docType: parts[0],
                categoryId: parseInt(parts[1], 10) || 0
            };
        }
        const legacyId = parseInt(value, 10) || 0;
        return { docType: legacyId > 0 ? 'personal' : null, categoryId: legacyId };
    }

    function buildDocumentCategoryOptionsHtml(categories) {
        const deps = getDeps();
        const escapeHtml = deps.escapeHtml || function(t) { return t; };
        const personal = [];
        const visa = [];
        (categories || []).forEach(function(cat) {
            const docType = cat.doc_type === 'visa' ? 'visa' : 'personal';
            if (docType === 'visa') {
                visa.push(cat);
            } else {
                personal.push(cat);
            }
        });

        let html = '<option value="">Select category…</option>';
        function appendGroup(label, items, docType) {
            if (!items.length) {
                return;
            }
            html += '<optgroup label="' + escapeHtml(label) + '">';
            items.forEach(function(cat) {
                const optionValue = docType + ':' + String(cat.id);
                html += '<option value="' + escapeHtml(optionValue) + '">' +
                    escapeHtml(cat.name || cat.category_name || ('Category ' + cat.id)) +
                    '</option>';
            });
            html += '</optgroup>';
        }
        appendGroup('Personal Documents', personal, 'personal');
        appendGroup('Visa Documents', visa, 'visa');
        return html;
    }

    function categorySelectionIsValid(selection) {
        return !!(selection && selection.categoryId > 0 && selection.docType);
    }

    function showAttachmentStorageModal(attachments, options) {
        options = options || {};
        const deps = getDeps();
        const escapeHtml = deps.escapeHtml || function(t) { return t; };
        const formatFileSize = deps.formatFileSize || function() { return ''; };

        return new Promise(function(resolve) {
            const modal = document.getElementById('attachmentStorageModal');
            const body = document.getElementById('attachmentStorageModalBody');
            const countEl = document.getElementById('attachmentStorageCount');
            const globalDestination = document.getElementById('attachmentStorageDestination');
            const perEmailDestination = document.getElementById('attachmentStoragePerEmail');
            const saveToDocsCheckbox = document.getElementById('attachmentSaveToDocuments');
            const categorySelect = document.getElementById('attachmentDocumentCategory');
            const confirmBtn = document.getElementById('attachmentStorageConfirm');
            const cancelBtn = document.getElementById('attachmentStorageCancel');
            const emailGroups = getAttachmentEmailGroups(attachments);
            const usePerEmailCategories = emailGroups.length > 1;
            const perEmailToggleHandlers = [];

            if (!modal || !body || !confirmBtn || !cancelBtn) {
                resolve([]);
                return;
            }

            if (countEl) {
                if ((options.emailCount || 0) > 1) {
                    countEl.textContent = attachments.length + (attachments.length === 1 ? ' file' : ' files') +
                        ' across ' + options.emailCount + ' emails';
                } else {
                    countEl.textContent = attachments.length + (attachments.length === 1 ? ' file' : ' files');
                }
            }

            function updateAttachmentCount() {
                if (!countEl || !body) {
                    return;
                }
                const count = body.querySelectorAll('tr[data-original-filename]').length;
                if ((options.emailCount || 0) > 1) {
                    countEl.textContent = count + (count === 1 ? ' file' : ' files') +
                        ' across ' + options.emailCount + ' emails';
                } else {
                    countEl.textContent = count + (count === 1 ? ' file' : ' files');
                }
            }

            function getDeleteButtonHtml() {
                const icon = typeof global.crmIconAny === 'function'
                    ? global.crmIconAny('trash-2', { class: 'attachment-storage-delete-icon' })
                    : (typeof global.crmI === 'function'
                        ? global.crmI('trash-2', { class: 'attachment-storage-delete-icon' })
                        : '<span class="attachment-storage-delete-icon" aria-hidden="true">×</span>');
                return '<button type="button" class="attachment-storage-delete-btn" aria-label="Remove attachment from upload">' +
                    icon +
                    '</button>';
            }

            function bindDeleteButtons() {
                body.querySelectorAll('.attachment-storage-delete-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const row = btn.closest('tr[data-original-filename]');
                        if (!row) {
                            return;
                        }
                        row.remove();
                        updateAttachmentCount();
                    });
                });
            }

            function renderAttachmentRow(att, showEmailLabel) {
                const stem = (att.display_name || att.filename || 'attachment').replace(/\.[^.]+$/, '');
                const emailKey = att._email_key || '';
                const emailFilenameAttr = att.email_filename
                    ? ' data-email-filename="' + escapeHtml(att.email_filename) + '"'
                    : '';
                const emailKeyAttr = emailKey ? ' data-email-key="' + escapeHtml(emailKey) + '"' : '';
                const emailLabel = showEmailLabel && att.email_filename
                    ? '<div class="attachment-storage-email-label">' + escapeHtml(att.email_filename) + '</div>'
                    : '';
                return '<tr data-original-filename="' + escapeHtml(att.filename) + '"' +
                    emailFilenameAttr + emailKeyAttr + '>' +
                    '<td>' + emailLabel + escapeHtml(att.filename) + '</td>' +
                    '<td>' + formatFileSize(att.file_size || 0) + '</td>' +
                    '<td><input type="text" class="attachment-rename-input" value="' + escapeHtml(stem) + '" aria-label="Save as"></td>' +
                    '<td class="attachment-storage-actions-col">' + getDeleteButtonHtml() + '</td>' +
                    '</tr>';
            }

            function populateGlobalCategorySelect(categories) {
                if (!categorySelect) {
                    return;
                }
                categorySelect.disabled = true;
                categorySelect.innerHTML = buildDocumentCategoryOptionsHtml(categories);
            }

            function renderPerEmailDestination(categories) {
                if (!perEmailDestination) {
                    return;
                }
                const categoryOptionsHtml = buildDocumentCategoryOptionsHtml(categories);
                perEmailDestination.innerHTML = emailGroups.map(function(group) {
                    return '<div class="attachment-storage-email-group" data-email-key="' + escapeHtml(group.key) + '">' +
                        '<div class="attachment-storage-email-group__title">' + escapeHtml(group.name) + '</div>' +
                        '<div class="attachment-storage-email-group__controls">' +
                        '<label class="attachment-storage-checkbox">' +
                        '<input type="checkbox" class="attachment-email-save-docs" data-email-key="' + escapeHtml(group.key) + '">' +
                        'Also save copies to Documents tab' +
                        '</label>' +
                        '<select class="attachment-storage-select attachment-email-category" data-email-key="' + escapeHtml(group.key) + '" aria-label="Document category" disabled>' +
                        categoryOptionsHtml +
                        '</select>' +
                        '</div></div>';
                }).join('');

                perEmailDestination.querySelectorAll('.attachment-email-save-docs').forEach(function(checkbox) {
                    function onToggle() {
                        const key = checkbox.getAttribute('data-email-key');
                        const select = perEmailDestination.querySelector('.attachment-email-category[data-email-key="' + key + '"]');
                        if (select) {
                            select.disabled = !checkbox.checked;
                        }
                    }
                    checkbox.addEventListener('change', onToggle);
                    perEmailToggleHandlers.push({ el: checkbox, fn: onToggle });
                });
            }

            if (usePerEmailCategories) {
                if (globalDestination) globalDestination.hidden = true;
                if (perEmailDestination) {
                    perEmailDestination.hidden = false;
                    renderPerEmailDestination([]);
                }
                const rowsHtml = [];
                emailGroups.forEach(function(group, groupIndex) {
                    if (groupIndex > 0) {
                        rowsHtml.push('<tr class="attachment-storage-group-spacer"><td colspan="4"></td></tr>');
                    }
                    (attachments || []).forEach(function(att) {
                        if (att.email_filename !== group.name) return;
                        rowsHtml.push(renderAttachmentRow(Object.assign({}, att, { _email_key: group.key }), false));
                    });
                });
                body.innerHTML = rowsHtml.join('');
                bindDeleteButtons();
            } else {
                if (globalDestination) globalDestination.hidden = false;
                if (perEmailDestination) {
                    perEmailDestination.hidden = true;
                    perEmailDestination.innerHTML = '';
                }
                if (saveToDocsCheckbox) saveToDocsCheckbox.checked = false;
                body.innerHTML = (attachments || []).map(function(att) {
                    return renderAttachmentRow(att, !!att.email_filename);
                }).join('');
                bindDeleteButtons();
            }

            loadDocumentCategoriesForAttachmentModal().then(function(categories) {
                if (usePerEmailCategories) {
                    renderPerEmailDestination(categories);
                } else {
                    populateGlobalCategorySelect(categories);
                }
            });

            function closeModal() {
                modal.classList.remove('active');
                modal.setAttribute('aria-hidden', 'true');
                confirmBtn.removeEventListener('click', onConfirm);
                cancelBtn.removeEventListener('click', onCancel);
                if (saveToDocsCheckbox) {
                    saveToDocsCheckbox.removeEventListener('change', onSaveToDocsToggle);
                }
                perEmailToggleHandlers.forEach(function(handler) {
                    handler.el.removeEventListener('change', handler.fn);
                });
                if (globalDestination) globalDestination.hidden = false;
                if (perEmailDestination) {
                    perEmailDestination.hidden = true;
                    perEmailDestination.innerHTML = '';
                }
            }

            function onSaveToDocsToggle() {
                if (categorySelect) {
                    categorySelect.disabled = !saveToDocsCheckbox.checked;
                }
            }

            function getPerEmailStoragePrefs() {
                const prefs = {};
                if (!perEmailDestination) return prefs;
                emailGroups.forEach(function(group) {
                    const checkbox = perEmailDestination.querySelector('.attachment-email-save-docs[data-email-key="' + group.key + '"]');
                    const select = perEmailDestination.querySelector('.attachment-email-category[data-email-key="' + group.key + '"]');
                    const saveToDocs = checkbox && checkbox.checked;
                    const selection = select ? parseCategoryOptionValue(select.value) : { categoryId: 0, docType: null };
                    prefs[group.key] = {
                        saveToDocs: saveToDocs,
                        categoryId: selection.categoryId,
                        docType: selection.docType,
                        storageType: (saveToDocs && categorySelectionIsValid(selection)) ? 'documents' : 'email'
                    };
                });
                return prefs;
            }

            function onCancel() {
                closeModal();
                resolve(null);
            }

            function onConfirm() {
                let globalStorageType = 'email';
                let globalCategoryId = 0;
                let globalDocType = null;
                if (!usePerEmailCategories) {
                    const saveToDocs = saveToDocsCheckbox && saveToDocsCheckbox.checked;
                    const globalSelection = categorySelect ? parseCategoryOptionValue(categorySelect.value) : { categoryId: 0, docType: null };
                    globalCategoryId = globalSelection.categoryId;
                    globalDocType = globalSelection.docType;
                    globalStorageType = (saveToDocs && categorySelectionIsValid(globalSelection)) ? 'documents' : 'email';
                }
                const perEmailPrefs = usePerEmailCategories ? getPerEmailStoragePrefs() : {};
                const rows = body.querySelectorAll('tr[data-original-filename]');
                const storageList = [];
                rows.forEach(function(row) {
                    const originalFilename = row.getAttribute('data-original-filename');
                    const emailFilename = row.getAttribute('data-email-filename');
                    const emailKey = row.getAttribute('data-email-key');
                    const input = row.querySelector('.attachment-rename-input');
                    const fileName = input ? input.value.trim() : '';
                    let storageType = globalStorageType;
                    let categoryId = globalStorageType === 'documents' ? globalCategoryId : null;
                    let docType = globalStorageType === 'documents' ? globalDocType : null;
                    if (usePerEmailCategories && emailKey && perEmailPrefs[emailKey]) {
                        storageType = perEmailPrefs[emailKey].storageType;
                        categoryId = storageType === 'documents' ? perEmailPrefs[emailKey].categoryId : null;
                        docType = storageType === 'documents' ? perEmailPrefs[emailKey].docType : null;
                    }
                    const entry = {
                        original_filename: originalFilename,
                        filename: originalFilename,
                        file_name: fileName || originalFilename,
                        storage_type: storageType,
                        category_id: categoryId
                    };
                    if (docType) {
                        entry.doc_type = docType;
                    }
                    if (emailFilename) {
                        entry.email_filename = emailFilename;
                    }
                    storageList.push(entry);
                });
                closeModal();
                resolve(storageList);
            }

            if (!usePerEmailCategories && saveToDocsCheckbox) {
                saveToDocsCheckbox.addEventListener('change', onSaveToDocsToggle);
            }
            confirmBtn.addEventListener('click', onConfirm);
            cancelBtn.addEventListener('click', onCancel);

            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        });
    }

    function updateEmailUploadLoading(title, message, filename, progressPercent) {
        const titleEl = document.getElementById('emailUploadLoadingTitle');
        const messageEl = document.getElementById('emailUploadLoadingMessage');
        const filenameEl = document.getElementById('emailUploadLoadingFilename');
        const progressBar = document.getElementById('emailUploadLoadingProgressBar');
        if (titleEl && title) titleEl.textContent = title;
        if (messageEl && message) messageEl.textContent = message;
        if (filenameEl) filenameEl.textContent = filename || '';
        if (progressBar) {
            const pct = Math.max(0, Math.min(100, Number(progressPercent) || 0));
            progressBar.style.width = pct + '%';
        }
    }

    function showEmailUploadLoading(title, message, filename, progressPercent) {
        const overlay = document.getElementById('emailUploadLoadingOverlay');
        if (!overlay) return;
        updateEmailUploadLoading(title, message, filename, progressPercent);
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.setAttribute('aria-busy', 'true');
        document.body.classList.add('email-upload-in-progress');
    }

    function hideEmailUploadLoading() {
        const overlay = document.getElementById('emailUploadLoadingOverlay');
        const progressBar = document.getElementById('emailUploadLoadingProgressBar');
        if (!overlay) return;
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('aria-busy', 'false');
        document.body.classList.remove('email-upload-in-progress');
        if (progressBar) progressBar.style.width = '0%';
    }

    global.__crmEmailUploadFlow = {
        previewBatchEmailAttachments: previewBatchEmailAttachments,
        flattenBatchAttachments: flattenBatchAttachments,
        groupAttachmentStorageByEmail: groupAttachmentStorageByEmail,
        showAttachmentStorageModal: showAttachmentStorageModal,
        showEmailUploadLoading: showEmailUploadLoading,
        hideEmailUploadLoading: hideEmailUploadLoading,
        updateEmailUploadLoading: updateEmailUploadLoading
    };
})(window);
