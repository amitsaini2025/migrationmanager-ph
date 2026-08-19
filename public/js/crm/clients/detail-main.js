    // Global flag to prevent redirects during page initialization
    var isInitializing = true;

    /**
     * Safely parse AJAX response - handles both pre-parsed objects (dataType:'json')
     * and raw strings, and guards against empty/invalid JSON to prevent "Unexpected end of input".
     */
    function safeParseJsonResponse(response) {
        if (typeof response === 'object' && response !== null) return response;
        if (typeof response === 'string' && response.trim()) {
            try { return JSON.parse(response); } catch(e) { console.error('Invalid JSON response:', e); return null; }
        }
        return null;
    }
    window.safeParseJsonResponse = safeParseJsonResponse;

    function removeDocumentRowFromSourceTab(res) {
        var docId = res && res.doc_id;
        if (!docId) {
            return;
        }

        if (res.doc_type === 'personal') {
            var categoryId = res.doc_category || '';
            if (categoryId) {
                $('.documnetlist_' + categoryId + ' #id_' + docId).remove();
                $('.griddata_' + categoryId + ' #gid_' + docId).remove();
            }
        } else if (res.doc_type === 'visa' || res.doc_type === 'nomination') {
            $('.migdocumnetlist1 #id_' + docId).remove();
        }

        $('#id_' + docId).remove();
        $('#gid_' + docId).remove();
    }

    function buildNotUsedDocumentRow(res) {
        if (!res || !res.docInfo) {
            return null;
        }

        var doc = res.docInfo;
        if ($('.notuseddocumnetlist #id_' + doc.id).length) {
            return null;
        }

        var fileUrl = '';
        var filePreviewPath = '';
        if (doc.myfile_key && doc.myfile_key !== '') {
            fileUrl = doc.myfile || '';
            filePreviewPath = doc.myfile || '';
        } else if (doc.myfile) {
            var awsBucket = window.ClientDetailConfig?.aws?.bucket || '';
            var awsRegion = window.ClientDetailConfig?.aws?.region || 'ap-southeast-2';
            var clientId = window.ClientDetailConfig?.clientId || '';
            fileUrl = 'https://' + awsBucket + '.s3.' + awsRegion + '.amazonaws.com/' + clientId + '/' + doc.doc_type + '/' + doc.myfile;
            filePreviewPath = fileUrl;
        }

        var uploadedBy = res.Added_By || 'NA';
        var uploadedDate = doc.created_at ? formatClientDocDateTime(doc.created_at) : '';
        var uploadTitle = 'Uploaded by: ' + uploadedBy + (uploadedDate ? ' on ' + uploadedDate : '');
        var badgeClass = doc.doc_type === 'personal' ? 'primary' : 'success';
        var docTypeLabel = doc.doc_type ? doc.doc_type.charAt(0).toUpperCase() + doc.doc_type.slice(1) : 'N/A';
        var fileName = doc.file_name || '';
        var fileExt = doc.filetype || '';
        var categoryLabel = res.category_label || '';

        var $tr = $('<tr class="drow"></tr>').attr('id', 'id_' + doc.id);
        var $checklistTd = $('<td style="white-space: initial;"></td>');
        $checklistTd.append($('<span></span>').attr('title', uploadTitle).text(doc.checklist || 'N/A'));
        if (categoryLabel) {
            $checklistTd.append(
                $('<small style="display: block; margin-top: 4px; color: #6b7280; font-size: 12px;"></small>').text(categoryLabel)
            );
        }
        $tr.append($checklistTd);

        var $typeTd = $('<td style="white-space: initial;"></td>');
        $typeTd.append($('<span class="badge badge-' + badgeClass + '"></span>').text(docTypeLabel));
        $tr.append($typeTd);

        var $fileTd = $('<td style="white-space: initial;"></td>');
        if (fileName) {
            var $docRow = $('<div class="doc-row"></div>')
                .attr('data-id', doc.id)
                .attr('data-name', fileName)
                .attr('title', uploadTitle);
            $docRow.on('contextmenu', function(event) {
                event.preventDefault();
                if (typeof showNotUsedFileContextMenu === 'function') {
                    showNotUsedFileContextMenu(event, doc.id, fileExt, fileUrl, doc.doc_type, doc.status || 'draft');
                }
                return false;
            });
            var $link = $('<a href="javascript:void(0);"></a>').on('click', function() {
                if (typeof previewFile === 'function') {
                    previewFile(fileExt, filePreviewPath, 'preview-container-notuseddocumnetlist');
                }
            });
            if (typeof crmI === 'function') {
                $link.append(crmI('fas fa-file-image'));
            }
            $link.append(document.createTextNode(' '));
            $link.append($('<span></span>').text(fileName + (fileExt ? '.' + fileExt : '')));
            $docRow.append($link);
            $fileTd.append($docRow);
        } else {
            $fileTd.text('N/A');
        }
        $tr.append($fileTd);

        var $actionsTd = $('<td></td>');
        $actionsTd.append(
            $('<a class="deletenote" href="javascript:;" style="display: none;"></a>')
                .attr('data-id', doc.id)
                .attr('data-doccategory', doc.doc_type)
                .attr('data-href', 'deletedocs')
        );
        $actionsTd.append(
            $('<a class="backtodoc" href="javascript:;" style="display: none;"></a>')
                .attr('data-id', doc.id)
                .attr('data-doctype', doc.doc_type)
                .attr('data-href', 'backtodoc')
        );
        $tr.append($actionsTd);

        return $tr;
    }

    function showClientDocFeedback(type, message, title) {
        var kind = type || 'success';
        var msg = message || '';
        if (!msg) {
            return;
        }
        if (typeof iziToast !== 'undefined' && typeof iziToast[kind] === 'function') {
            iziToast[kind]({
                title: title || (kind === 'success' ? 'Success' : (kind === 'warning' ? 'Notice' : 'Error')),
                message: msg,
                position: 'topRight'
            });
            return;
        }
        if (typeof toastr !== 'undefined' && typeof toastr[kind] === 'function') {
            toastr[kind](msg);
            return;
        }
        alert(msg);
    }

    function applyNotUsedMoveSuccess(res) {
        removeDocumentRowFromSourceTab(res);
        var $row = buildNotUsedDocumentRow(res);
        if ($row) {
            $('.notuseddocumnetlist').append($row);
        }
        // Stay on the current Personal/Visa documents tab; Not Used list is updated in the background.
        if (typeof getallactivities === 'function') {
            getallactivities();
        }
        showClientDocFeedback('success', 'Document moved to Not Used Documents successfully.');
    }

    function escapeClientDocUiAttr(value) {
        return String(value == null ? '' : value)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'");
    }

    function escapeClientDocUiHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function resolveClientDocFileUrl(doc) {
        if (!doc) {
            return '';
        }
        if (doc.myfile_key && doc.myfile_key !== '') {
            return doc.myfile || '';
        }
        if (doc.myfile && String(doc.myfile).indexOf('http') === 0) {
            return doc.myfile;
        }
        if (doc.myfile) {
            var awsBucket = window.ClientDetailConfig?.aws?.bucket || '';
            var awsRegion = window.ClientDetailConfig?.aws?.region || 'ap-southeast-2';
            var clientId = window.ClientDetailConfig?.clientId || '';
            return 'https://' + awsBucket + '.s3.' + awsRegion + '.amazonaws.com/' + clientId + '/' + (doc.doc_type || 'personal') + '/' + doc.myfile;
        }
        return '';
    }

    function activateClientDocumentsTabAndCategory(tabId, categoryId, subtabAttr) {
        var apply = function() {
            if (!tabId) {
                return;
            }
            if (typeof SidebarTabs !== 'undefined' && typeof SidebarTabs.activateTab === 'function') {
                SidebarTabs.activateTab(tabId);
            } else {
                $('.client-nav-button').removeClass('active');
                $('.tab-pane').removeClass('active');
                $('.client-nav-button[data-tab="' + tabId + '"]').addClass('active');
                $('#' + tabId + '-tab').addClass('active');
                localStorage.setItem('activeTab', tabId);
            }
            if (!categoryId || !subtabAttr) {
                return;
            }
            var $tab = $('#' + tabId + '-tab');
            $tab.find('.' + subtabAttr + '-button').removeClass('active');
            $tab.find('.' + subtabAttr + '-pane').removeClass('active');
            $tab.find('.' + subtabAttr + '-button[data-' + subtabAttr + '="' + categoryId + '"]').addClass('active');
            $tab.find('[id="' + categoryId + '-' + subtabAttr + '"]').addClass('active');
        };

        var loader = (typeof window.ensureClientDetailDocumentTabLoaded === 'function')
            ? window.ensureClientDetailDocumentTabLoaded(tabId)
            : Promise.resolve();

        Promise.resolve(loader).then(apply).catch(function(err) {
            console.error('[Documents] Failed to load tab before category switch', err);
            apply();
        });
    }

    function restorePersonalDocRowFromNotUsed(doc, categoryId) {
        var $tab = $('#personaldocuments-tab');
        var $tbody = $tab.find('.documnetlist_' + categoryId);
        if (!$tab.length || !$tbody.length || !doc || !doc.id) {
            return false;
        }
        if ($tbody.find('#id_' + doc.id).length) {
            return true;
        }

        var checklist = doc.checklist || 'Document';
        var fileName = doc.file_name || '';
        var fileType = doc.filetype || 'pdf';
        var fileUrl = resolveClientDocFileUrl(doc);
        var status = doc.status || 'draft';
        var catTitle = $tab.find('.subtab2-button[data-subtab2="' + categoryId + '"]').first().text().trim() || '';
        var iconHtml = (typeof crmI === 'function') ? crmI('fa-file-image') : '';
        var displayName = fileName ? (fileName + (fileType ? ('.' + fileType) : '')) : '';
        var fileCellHtml;

        if (fileName && fileUrl) {
            fileCellHtml =
                '<div data-id="' + doc.id + '" data-name="' + escapeClientDocUiHtml(fileName) + '" class="doc-row" ' +
                'oncontextmenu="showFileContextMenu(event, ' + doc.id + ', \'' + escapeClientDocUiAttr(fileType) + '\', \'' + escapeClientDocUiAttr(fileUrl) + '\', \'' + escapeClientDocUiAttr(categoryId) + '\', \'' + escapeClientDocUiAttr(status) + '\'); return false;">' +
                '<a href="javascript:void(0);" onclick="previewFile(\'' + escapeClientDocUiAttr(fileType) + '\',\'' + escapeClientDocUiAttr(fileUrl) + '\',\'preview-container-' + escapeClientDocUiAttr(categoryId) + '\')">' +
                iconHtml + ' <span>' + escapeClientDocUiHtml(displayName) + '</span></a></div>';
        } else {
            fileCellHtml = '<span style="color:#6b7280;">N/A</span>';
        }

        var rowHtml =
            '<tr class="drow" id="id_' + doc.id + '">' +
                '<td style="white-space: initial;">' +
                    '<div data-id="' + doc.id + '" data-personalchecklistname="' + escapeClientDocUiHtml(checklist) + '" class="personalchecklist-row" ' +
                    'style="display: flex; align-items: center; gap: 8px;" ' +
                    'oncontextmenu="showPersonalChecklistContextMenu(event, ' + doc.id + '); return false;">' +
                    '<span style="flex: 1;">' + escapeClientDocUiHtml(checklist) + '</span></div>' +
                '</td>' +
                '<td style="white-space: initial;">' + fileCellHtml + '</td>' +
                '<td>' +
                    '<a class="renamechecklist" data-id="' + doc.id + '" href="javascript:;" style="display: none;"></a>' +
                    (fileName
                        ? '<a class="renamedoc" data-id="' + doc.id + '" href="javascript:;" style="display: none;"></a>' +
                          '<a class="download-file" data-filelink="' + escapeClientDocUiHtml(fileUrl) + '" data-filename="' + escapeClientDocUiHtml(fileName) + '" data-id="' + doc.id + '" href="#" style="display: none;"></a>' +
                          '<a class="notuseddoc" data-id="' + doc.id + '" data-doctype="personal" data-doccategory="' + escapeClientDocUiHtml(catTitle) + '" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>'
                        : '') +
                '</td>' +
            '</tr>';

        $tbody.prepend(rowHtml);
        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons($tbody.find('#id_' + doc.id)[0]);
        }
        return true;
    }

    function restoreVisaDocRowFromNotUsed(doc, categoryId) {
        var $tab = $('#visadocuments-tab');
        var $tbody = $tab.find('.migdocumnetlist_' + categoryId);
        if (!$tbody.length) {
            $tbody = $tab.find('[id="' + categoryId + '-subtab6"] tbody.migdocumnetlist1').first();
        }
        if (!$tab.length || !$tbody.length || !doc || !doc.id) {
            return false;
        }
        if ($tbody.find('#id_' + doc.id).length) {
            return true;
        }

        var checklist = doc.checklist || 'Document';
        var fileName = doc.file_name || '';
        var fileType = doc.filetype || 'pdf';
        var fileUrl = resolveClientDocFileUrl(doc);
        var status = doc.status || 'draft';
        var matterId = doc.client_matter_id || '';
        var catTitle = $tab.find('.subtab6-button[data-subtab6="' + categoryId + '"]').first().text().trim() || '';
        var iconHtml = (typeof crmI === 'function') ? crmI('fa-file-image') : '';
        var displayName = fileName ? (fileName + (fileType ? ('.' + fileType) : '')) : '';
        var fileCellHtml;

        if (fileName && fileUrl) {
            fileCellHtml =
                '<div data-id="' + doc.id + '" data-name="' + escapeClientDocUiHtml(fileName) + '" class="doc-row" ' +
                'oncontextmenu="showVisaFileContextMenu(event, ' + doc.id + ', \'' + escapeClientDocUiAttr(fileType) + '\', \'' + escapeClientDocUiAttr(fileUrl) + '\', \'' + escapeClientDocUiAttr(categoryId) + '\', \'' + escapeClientDocUiAttr(status) + '\'); return false;">' +
                '<a href="javascript:void(0);" onclick="previewFile(\'' + escapeClientDocUiAttr(fileType) + '\',\'' + escapeClientDocUiAttr(fileUrl) + '\',\'preview-container-migdocumnetlist\')">' +
                iconHtml + ' <span>' + escapeClientDocUiHtml(displayName) + '</span></a></div>';
        } else {
            fileCellHtml = '<span style="color:#6b7280;">N/A</span>';
        }

        var rowHtml =
            '<tr class="drow" data-matterid="' + escapeClientDocUiHtml(matterId) + '" data-catid="' + escapeClientDocUiHtml(categoryId) + '" id="id_' + doc.id + '">' +
                '<td style="white-space: initial;">' +
                    '<div data-id="' + doc.id + '" data-visachecklistname="' + escapeClientDocUiHtml(checklist) + '" class="visachecklist-row" ' +
                    'style="display: flex; align-items: center; gap: 8px;" ' +
                    'oncontextmenu="showVisaChecklistContextMenu(event, ' + doc.id + '); return false;">' +
                    '<span style="flex: 1;">' + escapeClientDocUiHtml(checklist) + '</span></div>' +
                '</td>' +
                '<td style="white-space: initial;">' + fileCellHtml + '</td>' +
                '<td>' +
                    '<a class="renamechecklist" data-id="' + doc.id + '" href="javascript:;" style="display: none;"></a>' +
                    (fileName
                        ? '<a class="renamedoc" data-id="' + doc.id + '" href="javascript:;" style="display: none;"></a>' +
                          '<a class="download-file" data-filelink="' + escapeClientDocUiHtml(fileUrl) + '" data-filename="' + escapeClientDocUiHtml(fileName) + '" data-id="' + doc.id + '" href="#" style="display: none;"></a>' +
                          '<a class="notuseddoc" data-id="' + doc.id + '" data-doctype="visa" data-doccategory="' + escapeClientDocUiHtml(catTitle) + '" data-href="documents/not-used" href="javascript:;" style="display: none;"></a>'
                        : '') +
                '</td>' +
            '</tr>';

        $tbody.prepend(rowHtml);
        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons($tbody.find('#id_' + doc.id)[0]);
        }
        return true;
    }

    function applyBackToDocSuccess(res) {
        if (!res || !res.doc_id) {
            return;
        }

        $('.notuseddocumnetlist #id_' + res.doc_id).remove();

        var doc = res.docInfo || null;
        var docType = res.doc_type || (doc && doc.doc_type) || '';
        var categoryId = doc && doc.folder_name ? doc.folder_name : '';
        var destTabId = docType === 'personal' ? 'personaldocuments' : (docType === 'visa' ? 'visadocuments' : '');

        var finishBackToDoc = function() {
            var restored = false;

            try {
                if (docType === 'personal' && categoryId) {
                    restored = restorePersonalDocRowFromNotUsed(doc, categoryId);
                } else if (docType === 'visa' && categoryId) {
                    restored = restoreVisaDocRowFromNotUsed(doc, categoryId);
                }
            } catch (restoreErr) {
                console.warn('[BackToDoc] Soft restore failed', restoreErr);
                restored = false;
            }

            try {
                if (docType === 'personal') {
                    activateClientDocumentsTabAndCategory('personaldocuments', categoryId, 'subtab2');
                } else if (docType === 'visa') {
                    activateClientDocumentsTabAndCategory('visadocuments', categoryId, 'subtab6');
                }
                var $restoredRow = $('#id_' + res.doc_id);
                if ($restoredRow.length && $restoredRow[0].scrollIntoView) {
                    $restoredRow[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            } catch (navErr) {
                console.warn('[BackToDoc] Soft tab switch failed; document was still restored on server', navErr);
            }

            if (typeof getallactivities === 'function') {
                getallactivities();
            }

            var docTypeLabel = docType === 'personal' ? 'Personal Documents' : (docType === 'visa' ? 'Visa Documents' : 'Documents');
            showClientDocFeedback('success', 'Document moved back to ' + docTypeLabel + ' successfully.');
            if (!restored) {
                showClientDocFeedback('warning', 'If the document is not visible, refresh the page.');
            }
        };

        if (destTabId && typeof window.ensureClientDetailDocumentTabLoaded === 'function') {
            window.ensureClientDetailDocumentTabLoaded(destTabId).then(finishBackToDoc).catch(function(err) {
                console.error('[BackToDoc] Failed to load destination tab', err);
                finishBackToDoc();
            });
            return;
        }

        finishBackToDoc();
    }

    function clientDetailDocFilenameMessage() {
        return (typeof mmDocumentFilenameValidationMessage === 'function')
            ? mmDocumentFilenameValidationMessage()
            : "File name can only contain letters, numbers, dashes (-), underscores (_), spaces, dots (.), dollar signs ($), parentheses (( )), commas (,), ampersands (&), apostrophes ('), and plus signs (+). Please rename the file and try again.";
    }

    function clientDetailIsAllowedDocumentFilename(name) {
        if (typeof mmIsAllowedDocumentFilename === 'function') {
            return mmIsAllowedDocumentFilename(name);
        }
        return /^[a-zA-Z0-9_\-\.\s\$\(\),&+']+$/.test(name || '');
    }

    function clientDetailApplyWafSafeDocumentUpload(formData, file) {
        if (typeof mmSetDocumentUploadFile === 'function') {
            mmSetDocumentUploadFile(formData, file);
            return;
        }
        try {
            formData.delete('document_upload');
        } catch (e) { /* ignore */ }
        var safeName = (typeof mmSanitizeDocumentUploadFilename === 'function')
            ? mmSanitizeDocumentUploadFilename(file.name)
            : String(file.name).replace(/[^a-zA-Z0-9\-_.]/g, '_');
        formData.set('document_upload', file, safeName);
    }

    /** Build escaped HTML anchor for service agreement signing links in compose templates. */
    window.mmBuildServiceAgreementSignLink = function(url) {
        if (!url) {
            return '';
        }
        var safe = String(url)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        return '<a href="' + safe + '" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;">Sign Service Agreement</a>';
    };

    /** Snapshot of CRM template <option>s for #emailmodal (restored when compose opens without a matter). */
    function ensureComposeTemplateCrmOptionsSnapshot($select) {
        if (!$select || !$select.length || $select.data('composeCrmOptionsHtml')) {
            return;
        }
        $select.data('composeCrmOptionsHtml', $select.html());
    }

    function restoreComposeEmailTemplateCrmOptions($select) {
        if (!$select || !$select.length) {
            return;
        }
        ensureComposeTemplateCrmOptionsSnapshot($select);
        var html = $select.data('composeCrmOptionsHtml');
        if (html) {
            $select.html(html);
        }
    }

    /**
     * Compose Email template Tom Select: dropdown on body so positionDropdown() runs inside modals.
     * Re-init on each #emailmodal show (see detail.blade.php shown.bs.modal handler).
     */
    window.restoreComposeEmailTemplateCrmOptions = restoreComposeEmailTemplateCrmOptions;

    window.initComposeEmailTemplateSelect = function ($select) {
        if (!$select || !$select.length || typeof $.fn.mmSelect === 'undefined') {
            return;
        }
        ensureComposeTemplateCrmOptionsSnapshot($select);
        if ($select[0].tomselect) {
            $select.mmSelect('destroy');
        }
        $select.mmSelect({
            dropdownParent: $('body'),
            dropdownCssClass: 'mm-compose-email-template-dropdown',
            width: '100%',
        });
    };

    /** After <option>s are changed via jQuery, refresh Tom Select's option cache. */
    window.syncComposeEmailTemplateSelectFromDom = function ($select) {
        if (!$select || !$select.length) {
            return;
        }
        var ts = $select[0].tomselect;
        if (!ts) {
            window.initComposeEmailTemplateSelect($select);
            return;
        }
        ts.sync(true);
        if (typeof ts.refreshOptions === 'function') {
            ts.refreshOptions(false);
        }
    };

    function formatClientDocDateTime(iso) {
        if (typeof window.formatDisplayDateTime === 'function') {
            return window.formatDisplayDateTime(iso) || '';
        }
        if (!iso) return '';
        var d = new Date(iso);
        return isNaN(d.getTime()) ? String(iso) : d.toLocaleString();
    }

    // Utilities (see utils/): Flatpickr, Editor - flatpickr-helpers.js, editor-helpers.js | DOM - dom-helpers.js (adjustActivityFeedHeight, adjustPreviewContainers, downloadFile)

    $(document).ready(function() {
        // Run on load
        adjustActivityFeedHeight();
        // Run on resize (for responsiveness)
        $(window).on('resize', function () {
            adjustActivityFeedHeight();
        });

        // Save reference click (chip UI in modules/references.js)

        $(document).delegate('.saveReferenceValue', 'click', function() {

            let department_reference = $('#department_reference').val() || '';

            let other_reference = $('#other_reference').val() || '';

            let client_id = window.ClientDetailConfig.clientId;

            let selectedMatter = $('#sel_matter_id_client_detail').val();
            
            // Get matter ID from URL if available (matches page load logic)
            let matterIdFromUrl = window.ClientDetailConfig.matterId || '';

            // Validation: Ensure client_id is present (required for security)
            if (!client_id || client_id === '' || client_id === 'null' || client_id === null) {
                alert('Error: Client ID is missing. Please refresh the page and try again.');
                console.error('Client ID is missing:', client_id);
                return;
            }

            // Only require at least ONE reference (not both)
            if(department_reference.trim() == '' && other_reference.trim() == ''){

                alert('Please enter at least one reference value');

            } else {

                $.ajax({

                    url: window.ClientDetailConfig.urls.referencesStore,

                    type: 'POST',

                    data: {

                        department_reference: department_reference,

                        other_reference: other_reference,

                        client_id: client_id, // Required - always sent

                        client_matter_id: selectedMatter || null,
                        
                        client_unique_matter_no: matterIdFromUrl || null,

                        _token: window.ClientDetailConfig.csrfToken

                    },

                    success: function (response) {

                        // Don't reload - the chips are already updated
                        // location.reload();

                    },

                    error: function (xhr) {

                        alert('Error saving data');

                        console.error(xhr.responseText);

                    }

                });

            }

        });

       // On page load, check if the URL contains a matter ID and set the dropdown/checkbox state

        var currentUrl = window.location.href;

        var urlSegments = currentUrl.split('/');

        // Use the matter reference already resolved by PHP so we never accidentally
        // pick the tab-name segment (e.g. "visadocuments") from a URL like
        // /clients/detail/{encodeId}/191PR_1/visadocuments.
        var matterIdInUrl = (window.ClientDetailConfig && window.ClientDetailConfig.matterRefNo)
            ? window.ClientDetailConfig.matterRefNo
            : null;



        if (matterIdInUrl === null) {

            // Case 1: No matter ID in URL - Don't auto-select any matter

            selectedMatter = '';

            //console.log('No matter ID in URL, no matter selected - showing all notes');

        }

        else {

            // Case 2: Matter ID exists in URL

            let matchFound = false;



            // a) First check the dropdown for a matching option

            $('#sel_matter_id_client_detail option').each(function() {

                var uniqueMatterNo = $(this).data('clientuniquematterno');

                if (uniqueMatterNo === matterIdInUrl) {

                    $('#sel_matter_id_client_detail').val($(this).val()).trigger('change');

                    selectedMatter = $(this).val();

                    matchFound = true;

                    //console.log('Matter ID found in URL, selected matching dropdown option:', selectedMatter);

                }

            });



            // If no matching option in dropdown, proceed with further checks

            if (!matchFound) {

                // b) Check for a matching checkbox

                let checkboxMatchFound = false;

                $('.general_matter_checkbox_client_detail').each(function() {

                    var uniqueMatterNo = $(this).data('clientuniquematterno');

                    if (uniqueMatterNo === matterIdInUrl) {

                        $(this).prop('checked', true).trigger('change');

                        selectedMatter = $(this).val();

                        checkboxMatchFound = true;

                        //console.log('Matter ID in URL, checked matching checkbox:', selectedMatter);

                        return false; // Exit the loop once a match is found

                    }

                });



                // If no matching checkbox, check the first non-empty dropdown option

                if (!checkboxMatchFound) {

                    let firstNonEmptyOption = $('#sel_matter_id_client_detail option').filter(function() {

                        return $(this).val() !== '';

                    }).first();



                    if (firstNonEmptyOption.length) {

                        // If a non-empty option exists in the dropdown, select it

                        $('#sel_matter_id_client_detail').val(firstNonEmptyOption.val()).trigger('change');

                        selectedMatter = firstNonEmptyOption.val();

                        //console.log('Matter ID in URL, no checkbox match, selected first non-empty dropdown option:', selectedMatter);

                    } else {

                        // If no non-empty dropdown options, check the first checkbox

                        let firstCheckbox = $('.general_matter_checkbox_client_detail').first();

                        if (firstCheckbox.length) {

                            firstCheckbox.prop('checked', true).trigger('change');

                            selectedMatter = firstCheckbox.val();

                            //console.log('Matter ID in URL, no dropdown match, checked first checkbox:', selectedMatter);

                        } else {

                            selectedMatter = '';

                            //console.log('Matter ID in URL, no matches in dropdown or checkboxes');

                        }

                    }

                }

            }

        }

        

        // Set flag to false after initialization is complete

        setTimeout(function() {

            isInitializing = false;

        }, 100);



        // When Matter AI tab is clicked









        // Activity Feed Width Toggle - Moved to activity-feed.js



    });



    // REMOVED: Duplicate tab switching code - now handled by sidebar-tabs.js



    // Download document - see modules/documents.js



        //JavaScript to Show File Selection Hint

    document.addEventListener('DOMContentLoaded', function() {

        // Trigger file input click when "Add Document" button is clicked

        const addDocumentBtn = document.querySelector('.add-document-btn');
        if (addDocumentBtn) {
            addDocumentBtn.addEventListener('click', function() {

                document.querySelector('.docclientreceiptupload').click();

            });
        }



        // Show file selection hint when files are selected

        const docClientReceiptUpload = document.querySelector('.docclientreceiptupload');
        if (docClientReceiptUpload) {
            docClientReceiptUpload.addEventListener('change', function(e) {

            const files = e.target.files;

            const hintElement = document.querySelector('.file-selection-hint');
            if (!hintElement) {
                return;
            }

            if (files.length > 0) {

                if (files.length === 1) {

                    // Show the file name if only one file is selected

                    hintElement.textContent = `${files[0].name} selected`;

                } else {

                    // Show the number of files if multiple files are selected

                    hintElement.textContent = `${files.length} Files selected`;

                }

            } else {

                // Clear the hint if no files are selected

                hintElement.textContent = '';

            }

            });
        }





        // Trigger file input click when "Add Document" button is clicked

        const addDocumentBtn1 = document.querySelector('.add-document-btn1');
        if (addDocumentBtn1) {
            addDocumentBtn1.addEventListener('click', function() {

                document.querySelector('.docofficereceiptupload').click();

            });
        }

        // Ledger and Office Receipt drag & drop - see modules/ledger-dragdrop.js

    });



    document.addEventListener('DOMContentLoaded', function () {

        const radios = document.querySelectorAll('input[name="receipt_type"]');

        const forms = document.querySelectorAll('.form-type');



        radios.forEach(radio => {

            radio.addEventListener('change', function () {

                const $modal = $('#createreceiptmodal');
                const isQuickReceiptMode = $modal.length && $modal.data('quick-receipt-mode');

                forms.forEach(form => form.style.display = 'none');

                const selected = this.value;

                if (!isQuickReceiptMode) {
                    // Clear all forms before showing selected one (prevents data leakage between forms)
                    document.querySelectorAll('.form-type').forEach(form => {
                        // Clear input fields, but preserve hidden system fields (client_id, matter_id, etc)
                        form.querySelectorAll('input[type="text"], textarea').forEach(field => {
                            if (!field.name.includes('client_id') &&
                                !field.name.includes('matter_id') &&
                                !field.name.includes('loggedin_staffid') &&
                                !field.name.includes('loggedin_userid') && // backward compat
                                !field.name.includes('receipt_type') &&
                                !field.name.includes('client')) {
                                field.value = '';
                            }
                        });
                        // Clear select dropdowns except migration agent
                        form.querySelectorAll('select').forEach(field => {
                            if (!field.id || !field.id.includes('agent_id')) {
                                field.selectedIndex = 0;
                            }
                        });
                    });
                }

                const targetForm = document.getElementById(selected + '_form');
                if (targetForm) {
                    targetForm.style.display = 'block';
                }

                let selectedMatter;

                if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                    selectedMatter = $('.general_matter_checkbox_client_detail').val();

                } else {

                    selectedMatter = $('#sel_matter_id_client_detail').val();

                }

                if(selected == 'office_receipt'){

                    if (!isQuickReceiptMode) {
                        listOfInvoice();
                    }

                    $('#client_matter_id_office').val(selectedMatter);

                }

                else if(selected == 'invoice_receipt'){

                    var $fn = $('#invoice_receipt_form input[name="function_type"]');
                    if($fn.val() == '' || $fn.val() == 'add' ) {

                        $fn.val("add");

                    }

                    $('#client_matter_id_invoice').val(selectedMatter);

                }

                else if(selected == 'client_receipt'){

                    if (!isQuickReceiptMode) {
                        listOfInvoice();
                        clientLedgerBalanceAmount(selectedMatter);
                    }

                    $('#client_matter_id_ledger').val(selectedMatter);

                }

            });

        });

    });



    function getTopInvoiceNoFromDB(type) {

        $.ajax({

            type:'post',

            url: window.ClientDetailConfig.urls.getTopInvoiceNo,

            sync:true,

            data: {type:type},

success: function(response){

                var obj = safeParseJsonResponse(response);
                if (!obj) return;
                $('.invoice_no').val(obj.max_receipt_id);

                $('.unique_invoice_no').text(obj.max_receipt_id);

            }

        });

    }



    function getTopReceiptValInDB(type) {

        $.ajax({

            type:'post',

            url: window.ClientDetailConfig.urls.getTopReceiptVal,

            sync:true,

            data: {type:type},

            success: function(response){

                var obj = safeParseJsonResponse(response);
                if (!obj) return;
                if(obj.receipt_type == 1){ //client receipt

                    if(obj.record_count >0){

                        $('#top_value_db').val(obj.record_count);

                    } else {

                        $('#top_value_db').val(obj.record_count);

                    }

                }



                if(obj.receipt_type == 2){ //office receipt

                    if(obj.record_count >0){

                        $('#office_top_value_db').val(obj.record_count);

                    } else {

                        $('#office_top_value_db').val(obj.record_count);

                    }

                }



                if(obj.receipt_type == 4){ //journal receipt

                    if(obj.record_count >0){

                        $('#journal_top_value_db').val(obj.record_count);

                    } else {

                        $('#journal_top_value_db').val(obj.record_count);

                    }

                }



                if(obj.receipt_type == 3){ //invoice receipt

                    if(obj.record_count >0){

                        $('#invoice_top_value_db').val(obj.record_count);

                    } else {

                        $('#invoice_top_value_db').val(obj.record_count);

                    }

                }

            }

        });

    }



    // listOfInvoice, loadInvoicesForQuickReceipt, populateQuickReceiptOfficeForm - see modules/invoices.js



    // clientLedgerBalanceAmount - see modules/accounts.js




    // downloadFile - see utils/dom-helpers.js


$(document).ready(function() {

    

    









    //Send message

    $(document).delegate('.sendmsg', 'click', function(){

        $('#sendmsgmodal').modal('show');

        var client_id = $(this).attr('data-id');

        $('#sendmsg_client_id').val(client_id);

    });



    // Tags modal: open for normal tags

    $(document).delegate('.opentagspopup', 'click', function(e){

        e.preventDefault();

        var entityId = $(this).attr('data-id');

        if (entityId) {

            $('#tags_clients #client_id').val(entityId);

            $('#tags_clients #create_new_as_red').val('0');

            $('#tags_clients #tags_red_mode_hint').hide();

            $('#tags_clients').modal('show');

        }

    });



    // Tags modal: open for red tags

    $(document).delegate('.openredtagspopup', 'click', function(e){

        e.preventDefault();

        var entityId = $(this).attr('data-id');

        if (entityId) {

            $('#tags_clients #client_id').val(entityId);

            $('#tags_clients #create_new_as_red').val('1');

            $('#tags_clients #tags_red_mode_hint').show();

            $('#tags_clients').modal('show');

        }

    });



    // Change Matter Assignee: fetch current assignees, then open modal so Tom Select initializes with correct values

    $(document).delegate('.changeMatterAssignee', 'click', function(e){

        e.preventDefault();

        var matterId = $('.general_matter_checkbox_client_detail').is(':checked') ? $('.general_matter_checkbox_client_detail').val() : $('#sel_matter_id_client_detail').val();

        if (!matterId) {

            if (typeof iziToast !== 'undefined' && iziToast.warning) {

                iziToast.warning({ title: 'Select Matter', message: 'Please select a matter first.', position: 'topRight' });

            } else { alert('Please select a matter first.'); }

            return;

        }

        $('#selectedMatterLM').val(matterId);

        var fetchUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.fetchClientMatterAssignee) || '/clients/fetchClientMatterAssignee';

        var assigneeSelectors = '#change_sel_migration_agent_id, #change_sel_person_responsible_id, #change_sel_person_assisting_id, #change_office_id';

        function destroyChangeMatterAssigneeMmSelect() {

            if (typeof $.fn.mmSelect === 'undefined') {

                return;

            }

            $(assigneeSelectors).each(function() {

                if (this.tomselect) {

                    $(this).mmSelect('destroy');

                }

            });

        }

        $.ajax({

            type: 'post',

            url: fetchUrl,

            data: { _token: $('meta[name="csrf-token"]').attr('content'), client_matter_id: matterId },

            success: function(res){

                var info = (typeof res === 'string' ? (function(){ try { return JSON.parse(res); } catch(e){ return {}; } })() : res) || {};

                if (info.status === false) {

                    var msg = info.message || 'Could not load this matter.';

                    if (typeof iziToast !== 'undefined' && iziToast.warning) {

                        iziToast.warning({ title: 'Matter', message: msg, position: 'topRight' });

                    } else {

                        alert(msg);

                    }

                    return;

                }

                var raw = info.matter_info;

                var m = (raw && typeof raw === 'object' && !Array.isArray(raw)) ? raw : {};

                destroyChangeMatterAssigneeMmSelect();

                $('#change_sel_migration_agent_id').val(m.sel_migration_agent ? String(m.sel_migration_agent) : '');

                $('#change_sel_person_responsible_id').val(m.sel_person_responsible ? String(m.sel_person_responsible) : '');

                $('#change_sel_person_assisting_id').val(m.sel_person_assisting ? String(m.sel_person_assisting) : '');

                $('#change_office_id').val(m.office_id ? String(m.office_id) : '');

                $('#changeMatterAssigneeModal').modal('show');

            },

            error: function() {

                if (typeof iziToast !== 'undefined' && iziToast.error) {

                    iziToast.error({ title: 'Error', message: 'Could not load assignees for this matter. Please try again.', position: 'topRight' });

                } else {

                    alert('Could not load assignees for this matter. Please try again.');

                }

            }

        });

    });



    // Change Matter Assignee modal: Tom Select only positions dropdown when dropdownParent is "body" (see mm-tomselect-jquery.js).

    $(document).on('shown.bs.modal', '#changeMatterAssigneeModal', function(){

        $('#change_sel_migration_agent_id, #change_sel_person_responsible_id, #change_sel_person_assisting_id, #change_office_id').each(function(){

            var $el = $(this);

            if (this.tomselect) {

                $el.mmSelect('destroy');

            }

            $el.mmSelect({

                dropdownParent: $('body'),

                dropdownCssClass: 'mm-change-matter-assignee-dropdown',

                minimumResultsForSearch: 0,

                width: '100%'

            });

        });

    });



    // Convert Lead to Client modal: re-init mmSelect with dropdownParent so menus render inside modal
    $(document).on('shown.bs.modal', '#convertLeadToClientModal', function(){

        var $modal = $(this);

        $('#sel_migration_agent_id, #sel_person_responsible_id, #sel_person_assisting_id, #sel_office_id, #sel_matter_id').each(function(){

            var $el = $(this);

            if ($el.data('mmSelect')) $el.mmSelect('destroy');

            $el.mmSelect({ dropdownParent: $modal, minimumResultsForSearch: 0, width: '100%' });

        });

    });



    // Add Personal / Visa / Nomination checklist modals: Tom Select after modal is shown (reliable
    // positioning); dropdown on body + .mm-checklist-create-dropdown z-index (see modals/checklists CSS).
    function initDocChecklistModalSelect($select) {

        if (!$select.length || typeof $.fn.mmSelect !== 'function') {

            return;

        }

        if ($select.data('mmSelect')) {

            $select.mmSelect('destroy');

        }

        $select.mmSelect({

            dropdownParent: $('body'),

            dropdownCssClass: 'mm-checklist-create-dropdown',

            minimumResultsForSearch: 0,

            width: '100%'

        });

    }

    $(document).on('shown.bs.modal', '#openeducationdocsmodal', function () {

        initDocChecklistModalSelect($('#checklist'));

    });

    $(document).on('shown.bs.modal', '#openmigrationdocsmodal', function () {

        initDocChecklistModalSelect($('#visa_checklist'));

    });

    $(document).on('shown.bs.modal', '#opennominationdocsmodal', function () {

        initDocChecklistModalSelect($('#nomination_checklist'));

    });

    $(document).on('hidden.bs.modal', '#openeducationdocsmodal', function () {

        var $el = $('#checklist');

        if ($el.data('mmSelect')) {

            $el.mmSelect('destroy');

        }

    });

    $(document).on('hidden.bs.modal', '#openmigrationdocsmodal', function () {

        var $el = $('#visa_checklist');

        if ($el.data('mmSelect')) {

            $el.mmSelect('destroy');

        }

    });

    $(document).on('hidden.bs.modal', '#opennominationdocsmodal', function () {

        var $el = $('#nomination_checklist');

        if ($el.data('mmSelect')) {

            $el.mmSelect('destroy');

        }

    });



    // Tags modal: add tag pill(s) from input on comma or Enter

    $(document).on('keydown', '#tags_modal_container #tag_input', function(e){

        var $input = $(this);

        var val = ($input.val() || '').trim();

        if (e.which === 188 || e.which === 13) {

            e.preventDefault();

            if (val) {

                var parts = val.split(',').map(function(t){ return t.trim(); }).filter(function(t){ return t.length > 0; });

                var $container = $('#tags_modal_container .tags-pills-inner');

                var existing = [];

                $container.find('.tag-pill').each(function(){ existing.push($(this).attr('data-tag-name')); });

                parts.forEach(function(tagName){

                    if (existing.indexOf(tagName) === -1) {

                        existing.push(tagName);

                        var esc = $('<div>').text(tagName).html();

                        var $pill = $('<span class="tag-pill" data-tag-name="' + esc + '"><span class="tag-pill-text">' + esc + '</span><button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button></span>');

                        $pill.insertBefore($input);

                    }

                });

                $input.val('');

                $('#tags_validation').val('1');

            }

            if (e.which === 188) return false;

        }

    });



    // Tags modal: add tag(s) from input on blur (comma-separated)

    $(document).on('blur', '#tags_modal_container #tag_input', function(){

        var $input = $(this);

        var val = ($input.val() || '').trim();

        if (!val) return;

        var parts = val.split(',').map(function(t){ return t.trim(); }).filter(function(t){ return t.length > 0; });

        if (parts.length === 0) return;

        var $container = $('#tags_modal_container .tags-pills-inner');

        var existing = [];

        $container.find('.tag-pill').each(function(){ existing.push($(this).attr('data-tag-name')); });

        parts.forEach(function(tagName){

            if (existing.indexOf(tagName) === -1) {

                existing.push(tagName);

                var $pill = $('<span class="tag-pill" data-tag-name="' + $('<div>').text(tagName).html() + '"><span class="tag-pill-text">' + $('<div>').text(tagName).html() + '</span><button type="button" class="tag-pill-remove" aria-label="Remove tag">&times;</button></span>');

                $pill.insertBefore($input);

            }

        });

        $input.val('');

        $('#tags_validation').val('1');

    });



    // Tags modal: remove tag pill on X click

    $(document).delegate('#tags_modal_container .tag-pill-remove', 'click', function(e){

        e.preventDefault();

        $(this).closest('.tag-pill').remove();

        var count = $('#tags_modal_container .tag-pill').length;

        $('#tags_validation').val(count > 0 ? '1' : '');

    });



    // Tags form: collect tags from pills and submit

    $(document).on('submit', '#stags_matter', function(e){

        var $form = $(this);

        var $container = $form.find('#tags_modal_container');

        if ($container.length) {

            e.preventDefault();

            var tags = [];

            $container.find('.tag-pill').each(function(){

                var n = $(this).attr('data-tag-name');

                if (n) tags.push(n);

            });

            $form.find('input[name="tag[]"]').remove();

            tags.forEach(function(tag){

                $('<input type="hidden" name="tag[]">').val(tag).appendTo($form);

            });

            $form[0].submit();

        }

    });



    // Initialize Sidebar Tabs Management

        if (typeof SidebarTabs !== 'undefined' && window.ClientDetailConfig) {

            SidebarTabs.init({

                clientId: window.ClientDetailConfig.encodeId,

                matterId: window.ClientDetailConfig.matterId,

                activeTab: window.ClientDetailConfig.activeTab,

                selectedMatter: ''

            });

        } else {

            console.error('[DetailMain] SidebarTabs or ClientDetailConfig not available');

        }

    

    

    // REMOVED: Duplicate popstate handler - now handled by sidebar-tabs.js



    //Handle Client Portal tab click specifically

    function showClientMatterPortalData(selectedMatter){

        // Get client_id from the current page

        var clientId = window.ClientDetailConfig.clientId;

        

        // Show loading message in the client portal tab

        $('#client_portal-tab').html('<h4>Please wait, upserting matter record...</h4>');

        

        // Step 1: Insert/Update client_matters record (loadMatterUpsert)

        $.ajax({

            url: window.ClientDetailConfig.urls.loadMatterUpsert || window.ClientDetailConfig.urls.loadApplicationInsertUpdate,

            type: 'POST',

            data: {

                client_id: clientId,

                client_matter_id: selectedMatter

            },

            headers: {

                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

            },

            success: function(upsertResponse) {

                if(upsertResponse.status && upsertResponse.client_matter_id) {

                    // Update loading message

                    $('#client_portal-tab').html('<h4>Please wait, loading client portal details...</h4>');

                    var clientMatterId = upsertResponse.client_matter_id;

                    // Step 2: Call getClientPortalDetail route with the client_matter_id from upsert response

                    $.ajax({

                        url: window.ClientDetailConfig.urls.getClientPortalDetail || window.ClientDetailConfig.urls.getApplicationDetail,

                        type: 'GET',

                        data: {id: clientMatterId},

                        success: function(response){

                            // Display the response directly in the client portal tab

                            $('#client_portal-tab').html(response);

                            

                            $('.popuploader').hide();

                            // Render only inside the Client Portal tab to avoid leaking into Personal Details

                            $('#client_portal-tab').html(response);

                            if (typeof refreshLucideIcons === 'function') {
                                refreshLucideIcons(document.getElementById('client_portal-tab'));
                            }

                            // Initialize Flatpickr for matter date fields
                            if (typeof flatpickr !== 'undefined') {
                                $('#client_portal-tab .datepicker').each(function() {
                                    if (!$(this).data('flatpickr')) {
                                        const element = this;
                                        const $this = $(this);
                                        flatpickr(element, {
                                            dateFormat: 'Y-m-d', // YYYY-MM-DD format for backend
                                            allowInput: true,
                                            clickOpens: true,
                                            locale: { firstDayOfWeek: 1 },
                                            onChange: function(selectedDates, dateStr, instance) {
                                                $this.val(dateStr);
                                                // Persist intake date via AJAX on change
                                                if (dateStr && clientMatterId) {
                                                    $('.popuploader').show();
                                                    $.ajax({
                                                        url: window.ClientDetailConfig.urls.updateIntake,
                                                        method: "GET",
                                                        dataType: "json",
                                                        data: {from: dateStr, appid: clientMatterId},
                                                        success: function(result) {
                                                            $('.popuploader').hide();

                                                        },
                                                        error: function() {
                                                            $('.popuploader').hide();
                                                        }
                                                    });
                                                }
                                            }
                                        });
                                    }
                                });
                            }

                            // Initialize Flatpickr for expectdatepicker
                            initFlatpickrForClass('.expectdatepicker', {
                                dateFormat: 'Y-m-d' // YYYY-MM-DD format
                            });

                            // Initialize Flatpickr for startdatepicker
                            initFlatpickrForClass('.startdatepicker', {
                                dateFormat: 'Y-m-d' // YYYY-MM-DD format
                            });

                            // Initialize Flatpickr for enddatepicker
                            initFlatpickrForClass('.enddatepicker', {
                                dateFormat: 'Y-m-d' // YYYY-MM-DD format
                            });

                        },

                        error: function(xhr, status, error) {

                            console.error('Error loading client portal details:', error);

                            $('#client_portal-tab').html('<h4>Error loading client portal details. Please try again.</h4>');
                            $('.popuploader').hide();

                        }

                    });

                } else {

                    $('#client_portal-tab').html('<h4>Error upserting matter. Please try again.</h4>');
                    $('.popuploader').hide();

                }

            },

            error: function(xhr, status, error) {

                console.error('Error upserting matter:', error);

                $('#client_portal-tab').html('<h4>Error upserting matter. Please try again.</h4>');
                $('.popuploader').hide();

            }

        });

    }
   // renderClientFundsLedger - see modules/accounts.js



    // Ledger edit (handleEditLedgerEntry, updateLedgerEntryBtn) - see modules/accounts.js

    // Document/Notes/Form subtab switching - see modules/subtabs.js

    // REMOVED: Old email filtering system (dead code - filter UI elements no longer exist)
    // The modern email interface (emails.js) now handles all email filtering





    // Initialize Activity Feed visibility on page load (details tabs + Activity sidebar tab)

    if ($('#personaldetails-tab').hasClass('active') || $('#companydetails-tab').hasClass('active') || $('#activityfeed-tab').hasClass('active')) {

        $('#activity-feed').show();

        if (!$('#activityfeed-tab').hasClass('active')) {
            $('#main-content').css('flex', '1');
        } else {
            $('#main-content').hide();
            $('.crm-container').addClass('crm-container--activity-tab');
        }

        

        // Adjust Activity Feed height on initial load

        setTimeout(function() {

            adjustActivityFeedHeight();

        }, 150);

    } else {

        $('#activity-feed').hide();

        //$('#main-content').css('flex', '0 0 100%');

    }

});
    function previewFile(fileType, fileUrl, containerId) {

        //console.log('fileType='+fileType);

        //console.log('fileUrl='+fileUrl);

        //console.log('containerId='+containerId);

        const container = $(`.${containerId}`);



        // Show loading state

        container.html(`

            <div class="preview-content" style="flex: 1; display: flex; align-items: center; justify-content: center;">

                <div style="text-align: center;">

                    ${crmI('fas fa-spinner fa-spin', { size: 32, class: 'icon-spin' })}

                    <p style="margin-top: 10px; color: #666;">Loading preview...</p>

                </div>

            </div>

        `);



        // Determine content based on file type

        let content = '';



        if (fileType.toLowerCase().match(/(jpg|jpeg|png|gif)$/)) {

            const img = new Image();

            img.onload = function() {

                container.html(`

                    <div class="preview-content" style="flex: 1; overflow: auto; text-align: center;">

                        <img src="${fileUrl}" alt="Document Preview" style="max-width: 100%; max-height: calc(100vh - 300px); margin: auto; display: block;" />

                    </div>

                `);

            };

            img.src = fileUrl;

        } else if (fileType.toLowerCase() === 'pdf') {

            container.html(`

                <div class="preview-content" style="flex: 1; overflow: hidden;width: 475px !important;">

                    <iframe src="${fileUrl}" type="application/pdf" style="width: 100%; height: calc(100vh - 100px); border: none;"></iframe>

                </div>

            `);

        }

        else if (fileType.toLowerCase().match(/^(docx?|xlsx?|pptx?)$/)) {

            const officeViewerUrl = `https://view.officeapps.live.com/op/embed.aspx?src=${encodeURIComponent(fileUrl)}`;

            container.html(`

                <div class="preview-content" style="flex: 1; overflow: hidden; width: 100%;">

                    <iframe src="${officeViewerUrl}" class="doc-viewer" style="width: 100%; height: calc(100vh - 100px); border: none;"></iframe>

                </div>

            `);

        }

        else {

            container.html(`

                <div class="preview-content" style="flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column;">

                    ${crmI('fas fa-file', { size: 48 })}

                    <p style="margin-bottom: 15px;">Preview not available for this file type.</p>

                    <a href="${fileUrl}" target="_blank" class="btn btn-primary">Open File</a>

                </div>

            `);

        }

    }



    // Update preview container styles when the document is ready

        // Style all preview containers

        $('.preview-pane.file-preview-container').css({

            'display': 'flex',

            'flex-direction': 'column',

            'margin-top': '15px',

            'width': '499px',

            'min-height': '500px',

            'height': 'calc(100vh - 200px)',

            'border': '1px solid #dee2e6',

            'border-radius': '4px',

            'padding': '15px',

            'background': '#fff',

            'position': 'sticky',

            'top': '20px'

        });



        // Handle window resize

        $(window).resize(function() {

            adjustPreviewContainers();

        }).resize(); // Trigger on load




    // adjustPreviewContainers - see utils/dom-helpers.js









    jQuery(document).ready(function($){



        // Matter dropdown (dropdownCssClass for wrapping long names)
        $('#sel_matter_id_client_detail').mmSelect({
            dropdownCssClass: 'matter-dropdown-wrap'
        });



        // Compose Email template select: init on #emailmodal shown (initComposeEmailTemplateSelect), not here.



        //mail preview click update mail_is_read bit

        $('.mail_preview_modal').on('click', function(){

            var mail_report_id = $(this).attr('memail_id');

            $.ajaxSetup({

                headers: {

                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                }

            });

            $.ajax({

                url: window.ClientDetailConfig.urls.base + '/clients/updatemailreadbit',

                method: "POST",

                data: {mail_report_id:mail_report_id},

                dataType: 'json',

                success: function(response) {

                }

            });

        });



        //inbox mail reassign Model popup code start

        $(document).on('click', '.inbox_reassignemail_modal', function() {

            var val = $(this).attr('memail_id');

            $('#inbox_reassignemail_modal #memail_id').val(val);

            var staff_mail = $(this).attr('staff_mail') || $(this).attr('user_mail');

            $('#inbox_reassignemail_modal #staff_mail').val(staff_mail);

            var uploaded_doc_id = $(this).attr('uploaded_doc_id');

            $('#inbox_reassignemail_modal #uploaded_doc_id').val(uploaded_doc_id);

            $('#inbox_reassignemail_modal').modal('show');

        });



        // Inbox reassign modals – client + matter selects

        $('#reassign_client_id').mmSelect();

        $('#reassign_client_matter_id').mmSelect();



        $(document).delegate('#reassign_client_id', 'change', function(){

            let selected_client_id = $(this).val();

            



            if (selected_client_id != "") {

                $('.popuploader').show();

                $.ajaxSetup({

                    headers: {

                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                    }

                });

                $.ajax({

                    url: window.ClientDetailConfig.urls.base + '/clients/listAllMattersWRTSelClient',

                    method: "POST",

                    data: {client_id:selected_client_id},

                    dataType: 'json',

success: function(response) {

                        $('.popuploader').hide();

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        var matterlist = '<option value="">Select Client Matter</option>';

                        $.each(obj.clientMatetrs, function(index, subArray) {

                            matterlist += '<option value="'+subArray.id+'">'+subArray.title+'('+subArray.client_unique_matter_no+')</option>';

                        });

                        $('#reassign_client_matter_id').html(matterlist);

                    },

                    error: function() {
                        $('.popuploader').hide();
                    }

                });

                $('#reassign_client_matter_id').prop('disabled', false).mmSelect();

            } else {

                $('#reassign_client_matter_id').prop('disabled', true).mmSelect();

            }

        });





        //sent mail reassign Model popup code start

        $(document).on('click', '.sent_reassignemail_modal', function() {

            var val = $(this).attr('memail_id');

            $('#sent_reassignemail_modal #memail_id').val(val);

            var staff_mail = $(this).attr('staff_mail') || $(this).attr('user_mail');

            $('#sent_reassignemail_modal #staff_mail').val(staff_mail);

            var uploaded_doc_id = $(this).attr('uploaded_doc_id');

            $('#sent_reassignemail_modal #uploaded_doc_id').val(uploaded_doc_id);

            $('#sent_reassignemail_modal').modal('show');

        });



        $('.sent_mail_preview_modal').on('click', function(){

            var memail_subject = $(this).attr('memail_subject');

            $('#sent_mail_preview_modal #memail_subject').html(memail_subject);



            var memail_message = $(this).attr('memail_message');

            $('#sent_mail_preview_modal #memail_message').html(memail_message);



            $('#sent_mail_preview_modal').modal('show');

        });



        // Inbox reassign modals – client + matter selects

        $('#reassign_sent_client_id').mmSelect();

        $('#reassign_sent_client_matter_id').mmSelect();



        $(document).delegate('#reassign_sent_client_id', 'change', function(){

            let selected_client_id = $(this).val();

            if (selected_client_id != "") {

                $('.popuploader').show();

                $.ajaxSetup({

                    headers: {

                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

                    }

                });

                $.ajax({

                    url: window.ClientDetailConfig.urls.base + '/clients/listAllMattersWRTSelClient',

                    method: "POST",

                    data: {client_id:selected_client_id},

                    dataType: 'json',

success: function(response) {

                        $('.popuploader').hide();

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        var matterlist = '<option value="">Select Client Matter</option>';

                        $.each(obj.clientMatetrs, function(index, subArray) {

                            matterlist += '<option value="'+subArray.id+'">'+subArray.title+'('+subArray.client_unique_matter_no+')</option>';

                        });

                        $('#reassign_sent_client_matter_id').html(matterlist);

                    },

                    error: function() {
                        $('.popuploader').hide();
                    }

                });

                $('#reassign_sent_client_matter_id').prop('disabled', false).mmSelect();

            } else {

                $('#reassign_sent_client_matter_id').prop('disabled', true).mmSelect();

            }

        });















        // Handle click event on the action / followup button

        window.followupLocalDateString = function() {

            var now = new Date();

            var month = String(now.getMonth() + 1).padStart(2, '0');

            var day = String(now.getDate()).padStart(2, '0');

            return now.getFullYear() + '-' + month + '-' + day;

        };



        window.applyAssignStaffPopupMode = function(mode) {

            var $modal = $('#create_action_popup');

            var isFollowup = mode === 'followup';

            var $date = $modal.find('#popoverdatetime');

            var today = window.followupLocalDateString();

            $modal.attr('data-popup-mode', isFollowup ? 'followup' : 'action');

            $modal.find('.js-action-group-field').toggle(!isFollowup);

            $modal.find('.js-action-deadline-field').toggle(!isFollowup);

            $modal.find('.js-action-date-field').toggleClass('col-md-6', !isFollowup).toggleClass('col-12', isFollowup);

            $('#create_action_popupLabel').text(isFollowup ? 'Create Followup' : 'Assign Staff');

            $modal.find('.js-assign-staff-label').text(isFollowup ? 'Create Followup' : 'Assign Staff');

            if (isFollowup) {

                $date.attr('min', today);

                if (!$date.val() || $date.val() < today) {

                    $date.val(today);

                }

            } else {

                $date.removeAttr('min');

            }

        };



        $(document).on('change input', '#create_action_popup #popoverdatetime', function() {

            if ($('#create_action_popup').attr('data-popup-mode') !== 'followup') {

                return;

            }

            var today = window.followupLocalDateString();

            if (!this.value || this.value < today) {

                this.value = today;

            }

        });



        $(document).delegate('.btn-assignstaff, .btn-assignuser, .btn-create-action, .btn-create-followup', 'click', function(){

            // Get the value from the #note_description TinyMCE editor

            var note_description = getEditorContent('#note_description');



            // Preserve formatting while cleaning HTML tags and entities

            var clean_note_description = note_description

                .replace(/<br\s*\/?>/gi, '\n')  // Convert <br> tags to line breaks

                .replace(/<p[^>]*>/gi, '\n')    // Convert <p> tags to line breaks

                .replace(/<\/p>/gi, '')         // Remove closing </p> tags

                .replace(/<div[^>]*>/gi, '\n')  // Convert <div> tags to line breaks

                .replace(/<\/div>/gi, '')       // Remove closing </div> tags

                .replace(/<strong[^>]*>/gi, '**')  // Convert <strong> to ** for bold

                .replace(/<\/strong>/gi, '**')     // Close bold with **

                .replace(/<b[^>]*>/gi, '**')       // Convert <b> to ** for bold

                .replace(/<\/b>/gi, '**')          // Close bold with **

                .replace(/<em[^>]*>/gi, '*')       // Convert <em> to * for italic

                .replace(/<\/em>/gi, '*')          // Close italic with *

                .replace(/<i[^>]*>/gi, '*')        // Convert <i> to * for italic

                .replace(/<\/i>/gi, '*')           // Close italic with *

                .replace(/<u[^>]*>/gi, '__')       // Convert <u> to __ for underline

                .replace(/<\/u>/gi, '__')          // Close underline with __

                .replace(/<[^>]*>/g, '')           // Strip all remaining HTML tags

                .replace(/&nbsp;/g, ' ')           // Convert &nbsp; to regular spaces

                .replace(/&amp;/g, '&')            // Convert &amp; to &

                .replace(/&lt;/g, '<')             // Convert &lt; to <

                .replace(/&gt;/g, '>')             // Convert &gt; to >

                .replace(/&quot;/g, '"')           // Convert &quot; to "

                .replace(/&#39;/g, "'")            // Convert &#39; to '

                .replace(/\n\s*\n/g, '\n')         // Remove multiple consecutive line breaks

                .trim();                           // Remove leading/trailing whitespace



            // Display the clean value in an alert

            //alert(clean_note_description);



            // Transfer the original HTML content to the #assignnote field (preserving formatting)

            // If #assignnote is a TinyMCE editor, use setEditorContent, otherwise use val()

            if (isEditorInitialized('#assignnote')) {

                setEditorContent('#assignnote', note_description);

            } else {

                $('#assignnote').val(clean_note_description);

            }



            // Close the #create_note_d modal

            $('#create_note_d').modal('hide');



            window.applyAssignStaffPopupMode($(this).hasClass('btn-create-followup') ? 'followup' : 'action');



            // Show the #create_action_popup modal

            $('#create_action_popup').modal('show');

        });







        // Toggle dropdown menu on button click

        $('.dropdown-toggle').on('click', function() {

            $(this).parent().toggleClass('show');

        });



        // Close the dropdown if clicked outside

        $(document).on('click', function(e) {

            if (!$(e.target).closest('.dropdown-multi-select').length) {

                $('.dropdown-multi-select').removeClass('show');

            }

        });



        // Handle checkbox click events

        $('.checkbox-item').on('change', function() {

            var selectedValues = [];



            // Collect selected checkboxes values

            $('.checkbox-item:checked').each(function() {

                selectedValues.push($(this).val());

            });



            // Set the selected values in the hidden select dropdown

            $('#rem_cat').val(selectedValues).trigger('change');

        });



        // Handle "Select All" functionality (If needed, you can include this part)

        $('#select-all').on('change', function() {

            if ($(this).is(':checked')) {

                // Select all checkboxes

                $('.checkbox-item').prop('checked', true).trigger('change');

            } else {

                // Deselect all checkboxes

                $('.checkbox-item').prop('checked', false).trigger('change');

            }

        });





        //Matter selection - unified dropdown approach

        var selectedMatter = '';



        //Note: General matter checkbox handlers removed - now using unified dropdown approach





        //Convert lead to client popup and select matter

        $(document).delegate('#general_matter_checkbox_new', 'change', function(){

            if (this.checked) {

                $('#sel_matter_id').prop('disabled', true).trigger('change');

                $('#sel_matter_id').removeAttr('data-valid').trigger('change');

            } else {

                $('#sel_matter_id').prop('disabled', false).trigger('change');

                $('#sel_matter_id').attr('data-valid', 'required').trigger('change');

            }

        });
        //Client detail page Select general matter checkbox and assign matter id

        $(document).delegate('.general_matter_checkbox_client_detail', 'change', function(){

            if (this.checked) {

                $('#sel_matter_id_client_detail').prop('disabled', true).trigger('change');

                $('#sel_matter_id_client_detail').removeAttr('data-valid').trigger('change');

                selectedMatter = $(this).val();

               



                var uniqueMatterNo = $(this).data('clientuniquematterno');

                

                // Get the active tab and sub tab

                var activeTab = $('.tab-button.active, .vertical-tab-button.active, .client-nav-button.active').data('tab') || 'personaldetails';

                var activeSubTab = $('.subtab-button.active').data('subtab');

                

            // Skip redirect during initialization

            if (isInitializing) {

                return;

            }

                

                // Build new URL

                var clientId = window.ClientDetailConfig.encodeId;

                var baseUrl = '/clients/detail/' + clientId;

                var currentUrl = window.location.href;



                var newUrl;

                if (selectedMatter != '' && uniqueMatterNo) {

                    // Append the new matter ID and active tab to the base URL

                    newUrl = baseUrl + '/' + uniqueMatterNo + '/' + activeTab;

                } else {

                    // If no matter is selected, redirect to the base URL with just the tab

                    newUrl = baseUrl + '/' + activeTab;

                }

                

                // Only redirect if the URL is actually changing to prevent infinite loops

                if (currentUrl.split('?')[0] !== newUrl && !currentUrl.endsWith(newUrl)) {

                    window.location.href = newUrl;

                    return; // Exit early to prevent further execution

                }



                if( activeTab == 'noteterm' ) {

                    var applyNotesMatterFilter = function() {
                        if (typeof window.filterNotes === 'function') {
                            window.filterNotes();
                        }
                    };

                    if (typeof window.ensureNotesTabLoaded === 'function') {
                        window.ensureNotesTabLoaded().then(applyNotesMatterFilter).catch(function(err) {
                            console.error('[MatterChange] Failed to load Notes tab', err);
                            applyNotesMatterFilter();
                        });
                    } else {
                        applyNotesMatterFilter();
                    }

                }

                else if( activeTab == 'visadocuments') {

                    var applyVisaMatterFilter = function() {
                        if (typeof SidebarTabs !== 'undefined' && SidebarTabs.filterVisaDocumentsByMatter) {
                            SidebarTabs.filterVisaDocumentsByMatter(selectedMatter);
                        }
                    };

                    if (typeof window.ensureVisaDocumentsTabLoaded === 'function') {
                        window.ensureVisaDocumentsTabLoaded().then(applyVisaMatterFilter).catch(function(err) {
                            console.error('[MatterChange] Failed to load Visa Documents tab', err);
                            applyVisaMatterFilter();
                        });
                    } else {
                        applyVisaMatterFilter();
                    }

                }

                else if( activeTab == 'nominationdocuments') {

                    if (typeof SidebarTabs !== 'undefined' && SidebarTabs.filterNominationDocumentsByMatter) {
                        SidebarTabs.filterNominationDocumentsByMatter(selectedMatter);
                    }

                }




            } else {

                $('#sel_matter_id_client_detail').prop('disabled', false).trigger('change');

                $('#sel_matter_id_client_detail').attr('data-valid', 'required').trigger('change');

                selectedMatter = "";

            }

        });



        /**
         * Compare current browser location to the relative path we would navigate to for
         * "sync URL with matter" (e.g. /clients/detail/{id}/{matterNo}/{tab}).
         * Decodes path segments so Core%20Skills_1 and Core Skills_1 match — avoids
         * full-page redirects when the matter dropdown fires change but the URL already
         * represents the same route (e.g. Checklists > Edit > Amend cost assignment).
         */
        function clientDetailPathMatchesHref(relativePath) {
            var candidate = (relativePath || '').split('?')[0];
            if (candidate.indexOf('/') !== 0) {
                candidate = '/' + candidate;
            }
            try {
                var curPath = new URL(window.location.href, window.location.origin).pathname;
                var normalize = function (pathname) {
                    return pathname.split('/').map(function (seg) {
                        if (!seg) {
                            return seg;
                        }
                        try {
                            return decodeURIComponent(seg);
                        } catch (e) {
                            return seg;
                        }
                    }).join('/');
                };
                return normalize(curPath) === normalize(candidate);
            } catch (e) {
                return window.location.href.split('?')[0].endsWith(candidate);
            }
        }

        //Select matter drop down chnage

        $('#sel_matter_id_client_detail').on('change', function() {

            selectedMatter = $(this).val();

            var uniqueMatterNo = $(this).find('option:selected').data('clientuniquematterno');

            var currentUrl = window.location.href;

            // Get the active tab

            var activeTab = $('.tab-button.active, .vertical-tab-button.active, .client-nav-button.active').data('tab') || 'personaldetails';



             // Get the active sub tab

            var activeSubTab = $('.subtab-button.active').data('subtab');

            

        // Skip redirect during initialization

        if (isInitializing) {

            return;

        }



        // Prevent redirect when "Select Matters" placeholder is selected

        if (selectedMatter === '' || selectedMatter === null) {


            return;

        }



        // Split the URL into segments

        var urlSegments = currentUrl.split('/');

        var baseUrl;

        var clientId = window.ClientDetailConfig.encodeId;

        

        // Build new URL with matter and tab

        baseUrl = '/clients/detail/' + clientId;



        var newUrl;

        if (selectedMatter != '' && uniqueMatterNo) {

            // Append the new matter ID and active tab to the base URL

            newUrl = baseUrl + '/' + uniqueMatterNo + '/' + activeTab;

        } else {

            // If no matter is selected, redirect to the base URL with just the tab

            newUrl = baseUrl + '/' + activeTab;

        }

        

        // Only redirect if the URL is actually changing to prevent infinite loops
        // (compare normalized paths — avoids spurious reload when encoding differs, e.g. %20 vs space)

        if (!clientDetailPathMatchesHref(newUrl)) {

            window.location.href = newUrl;

            return; // Exit early to prevent further execution

        }



            if( activeTab == 'noteterm' ) {

                var applyNotesMatterFilterOnChange = function() {
                    if (typeof window.filterNotes === 'function') {
                        window.filterNotes();
                    }
                };

                if (typeof window.ensureNotesTabLoaded === 'function') {
                    window.ensureNotesTabLoaded().then(applyNotesMatterFilterOnChange).catch(function(err) {
                        console.error('[MatterChange] Failed to load Notes tab', err);
                        applyNotesMatterFilterOnChange();
                    });
                } else {
                    applyNotesMatterFilterOnChange();
                }

            }

            else if( activeTab == 'documentalls' && activeSubTab == 'migrationdocuments') {

                if(selectedMatter != "" ) {

                    $('#migrationdocuments-subtab .migdocumnetlist1').find('.drow').each(function() {

                        if ($(this).data('matterid') == selectedMatter) {

                            $(this).show();

                        } else {

                            $(this).hide();

                        }

                    });

                }  else {

                    $(this).hide();

                }

            }

            else if (activeTab == 'visadocuments') {

                var applyVisaMatterFilterOnChange = function() {
                    if (typeof SidebarTabs !== 'undefined' && SidebarTabs.filterVisaDocumentsByMatter) {
                        SidebarTabs.filterVisaDocumentsByMatter(selectedMatter);
                    }
                };

                if (typeof window.ensureVisaDocumentsTabLoaded === 'function') {
                    window.ensureVisaDocumentsTabLoaded().then(applyVisaMatterFilterOnChange).catch(function(err) {
                        console.error('[MatterChange] Failed to load Visa Documents tab', err);
                        applyVisaMatterFilterOnChange();
                    });
                } else {
                    applyVisaMatterFilterOnChange();
                }

            }

            else if (activeTab == 'nominationdocuments') {

                if (typeof SidebarTabs !== 'undefined' && SidebarTabs.filterNominationDocumentsByMatter) {
                    SidebarTabs.filterNominationDocumentsByMatter(selectedMatter);
                }

            }




            //var activeTab = $('.nav-item .nav-link.active');

            /*if( activeTab.attr('id') == 'noteterm-tab' ) {

                // Trigger click on the active tab

                activeTab.trigger('click');

            }

            else if( activeTab.attr('id') == 'migrationdocuments-tab' ) {

                // Trigger click on the active tab

                activeTab.trigger('click');

            }*/

        });





        //Tab click

        $(document).delegate('#client_tabs a', 'click', function(){

            // Get the target tab's href

            var target = $(this).attr('href');



            // Reset the visibility and classes

            $('.left_section').hide(); // Hide the left section by default

            $('.right_section').parent().removeClass('col-8 col-md-8 col-lg-8').addClass('col-12 col-md-12 col-lg-12');



            // Adjust based on the selected tab

            if (target === '#activities') {

                $('.left_section').show(); // Show the left section for Activities tab

                $('.left_section').removeClass('col-4 col-md-4 col-lg-4').addClass('col-4 col-md-4 col-lg-4');

                $('.right_section').parent().removeClass('col-12 col-md-12 col-lg-12').addClass('col-8 col-md-8 col-lg-8');

            }



            else if (target === '#noteterm') {

                if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                    selectedMatter = $('.general_matter_checkbox_client_detail').val();

                } else {

                    selectedMatter = $('#sel_matter_id_client_detail').val();

                }

                

                if(target == '#noteterm' ){

                    if(selectedMatter != "" ) {

                        $(target).find('.note_col').each(function() {

                            if ($(this).data('matterid') == selectedMatter) {

                                $(this).show();

                            } else {

                                $(this).hide();

                            }

                        });

                    }  else {

                        //alert('Please select matter from matter drop down.');

                        $(target).find('.note_col').each(function() {

                            $(this).hide();

                        });

                    }

                }

            }



            else if (target === '#migrationdocuments') { //alert('migrationdocuments');

                if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                    selectedMatter = $('.general_matter_checkbox_client_detail').val();

                } else {

                    selectedMatter = $('#sel_matter_id_client_detail').val();

                }

                if(target == '#migrationdocuments' ){

                    if(selectedMatter != "" ) {

                        $(target).find('.drow').each(function() {

                            if ($(this).data('matterid') == selectedMatter) {

                                $(this).show();

                            } else {

                                $(this).hide();

                            }

                        });

                    }  else {

                        //alert('Please select matter from matter drop down.');

                        $(target).find('.drow').each(function() {

                            $(this).hide();

                        });

                    }

                }

            }




            else if (target === '#clientdetailform') {

                var right_section_height = $('#clientdetailform').height();

                right_section_height = right_section_height+200;

                $('.right_section').css({"maxHeight":right_section_height});

            }

        });



        $(document).delegate('.general_matter_checkbox_client_detail', 'click', function(){

            // Uncheck all checkboxes

            $('.general_matter_checkbox_client_detail').not(this).prop('checked', false);

        });

        //Matter checkbox end





        //create client receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields');
        initFlatpickrForClass('.report_entry_date_fields', {
            defaultDate: new Date()
        });



        /*$(document).delegate('.openproductrinfo', 'click', function(){

            var clonedval = $('.clonedrow').html();

            $('.productitem').append('<tr class="product_field_clone">'+clonedval+'</tr>');

            // Initialize Flatpickr for new date fields
            initFlatpickrForClass('.report_date_fields,.report_entry_date_fields');

           // $('.report_entry_date_fields').last().datepicker({ format: 'dd/mm/yyyy',todayHighlight: true,autoclose: true }).datepicker('setDate', new Date());

        });*/



        $(document).delegate('.openproductrinfo', 'click', function() {

            var clonedval = $('.clonedrow').html();

            var $newRow = $('<tr class="product_field_clone">' + clonedval + '</tr>');

            // Reset invoice column (placeholder visible until Fee Transfer)

            $newRow.find('.invoice_no_cls').hide().removeAttr('data-valid').val('');

            $newRow.find('.ledger-invoice-placeholder').show();

            $('.productitem').append($newRow);

            // Initialize Flatpickr for new date fields
            initFlatpickrForClass('.report_date_fields,.report_entry_date_fields');

            toggleLedgerEftposSurchargeRow($newRow);

            //$('.report_entry_date_fields').last().datepicker({ format: 'dd/mm/yyyy',todayHighlight: true,autoclose: true }).datepicker('setDate', new Date());

        });





        $(document).delegate('.removeitems', 'click', function(){

            var $tr    = $(this).closest('.product_field_clone');

            var trclone = $('.product_field_clone').length;

            if(trclone > 0){

                $tr.remove();

            }

            grandtotalAccountTab();

        });



        $(document).delegate('.deposit_amount_per_row,.withdraw_amount_per_row', 'keyup', function(){

            grandtotalAccountTab();

        });



        $.ajaxSetup({

            headers: {

                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

            }

        });
        function getInfoByReceiptId11(receiptid) {

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getInfoByReceiptId,

                sync:true,

                data: {receiptid:receiptid},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.status){

                        $('#invoice_receipt_form input[name="function_type"]').val("edit");

                        $('#createreceiptmodal').modal('show');



                        const invoiceRadio = document.querySelector('input[name="receipt_type"][value="invoice_receipt"]');

                        if (invoiceRadio) {

                            invoiceRadio.checked = true;



                            // Manually trigger the change event

                            invoiceRadio.dispatchEvent(new Event('change'));

                        }



                        if(obj.record_get){

                            var record_get = obj.record_get;

                            //var trRows_office = "";

                            var sum = 0;

                            $('#invoice_receipt_form .productitem_invoice tr.clonedrow_invoice').remove();

                            $('#invoice_receipt_form .productitem_invoice tr.product_field_clone_invoice').remove();

                            if (record_get.length > 0 && record_get[0].client_matter_id != null && record_get[0].client_matter_id !== '') {
                                $('#client_matter_id_invoice').val(record_get[0].client_matter_id);
                            }

                            $.each(record_get, function(index, subArray) {

                                var value_sum = parseFloat(subArray.withdraw_amount);

                                if (!isNaN(value_sum)) {

                                    sum += value_sum;

                                }



                                var rowCls = index < 1 ? 'clonedrow_invoice' : 'product_field_clone_invoice';



                                //var trRows_office = '<tr class="'+rowCls+'"><td><input name="id[]" type="hidden" value="'+subArray.id+'" /><input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="'+subArray.trans_date+'" /></td><td><input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="'+subArray.entry_date+'" /></td><td><select class="form-control gst_included_cls" name="gst_included[]"><option value="">Select</option><option value="Yes">Yes</option><option value="No">No</option></select></td><td><select class="form-control payment_type_cls" name="payment_type[]"><option value="">Select</option><option value="Professional Fee">Professional Fee</option><option value="Department Charges">Department Charges</option><option value="Surcharge">Surcharge</option><option value="Disbursements">Disbursements</option><option value="Other Cost">Other Cost</option></select></td><td><input data-valid="required" class="form-control" name="description[]" type="text" value="'+subArray.description+'" /></td><td><span class="currencyinput">$</span><input data-valid="required" class="form-control withdraw_amount_invoice_per_row" name="withdraw_amount[]" type="text" value="'+subArray.withdraw_amount+'" /></td><td><a class="removeitems_invoice" href="javascript:;"><i class="fa fa-times"></i></a></td></tr>';

                                var trRows_office = `<tr class="${rowCls}">

                                    <td>

                                        <input name="id[]" type="hidden" value="${subArray.id}" />

                                        <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="${subArray.trans_date}" />

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="${subArray.entry_date}" />

                                    </td>



                                    <td>

                                        <select class="form-control gst_included_cls" name="gst_included[]">

                                            <option value="">Select</option>

                                            <option value="Yes">Yes</option>

                                            <option value="No">No</option>

                                        </select>

                                    </td>

                                    <td>

                                        <select class="form-control payment_type_invoice_per_row" name="payment_type[]">

                                            <option value="">Select</option>

                                            <option value="Professional Fee">Professional Fee</option>

                                            <option value="Department Charges">Department Charges</option>

                                            <option value="Surcharge">Surcharge</option>

                                            <option value="Disbursements">Disbursements</option>

                                            <option value="Other Cost">Other Cost</option>

                                            <option value="Discount">Discount</option>

                                        </select>

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control" name="description[]" type="text" value="${subArray.description}" />

                                    </td>

                                    <td>

                                        <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>

                                        <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row payment_type_invoice_per_row" name="withdraw_amount[]" type="text" value="${subArray.withdraw_amount}" />

                                    </td>

                                    <td>

                                        <a class="removeitems_invoice" href="javascript:;">${crmI('fa fa-times')}</a>

                                    </td>

                                </tr>`;



                                let $newRow = $(trRows_office);

                                $('#invoice_receipt_form .productitem_invoice').append($newRow);



                                // Set selected values

                                $newRow.find('.gst_included_cls').val(subArray.gst_included);

                                $newRow.find('.payment_type_invoice_per_row').val(subArray.payment_type);



                                // Initialize Flatpickr for invoice date fields
                                initFlatpickrForClass('.report_date_fields_invoice');
                                initFlatpickrForClass('.report_entry_date_fields_invoice:last', {
                                    defaultDate: new Date()
                                });

                                if(index <1 ){
                                    var invNo = subArray.trans_no || subArray.invoice_no || '';
                                    if ($('.unique_invoice_no').length) {
                                        $('.unique_invoice_no').text(invNo);
                                    }
                                    if ($('.invoice_no').length) {
                                        $('.invoice_no').val(invNo);
                                    }
                                    $('#invoice_receipt_form input[name="receipt_id"]').val(subArray.receipt_id);
                                }

                            });

                            $('#invoice_receipt_form .total_withdraw_amount_all_rows_invoice').text("$"+sum.toFixed(2));

                        }

                    }

                }

            });

        }



        function getInfoByReceiptId(receiptid) {

            $.ajax({

                type: 'post',

                url: window.ClientDetailConfig.urls.getInfoByReceiptId,

                sync: true,

                data: { receiptid: receiptid },

                success: function (response) {

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if (obj.status) {

                        $('#invoice_receipt_form input[name="function_type"]').val("edit");

                        $('#createreceiptmodal').modal('show');



                        const invoiceRadio = document.querySelector('input[name="receipt_type"][value="invoice_receipt"]');

                        if (invoiceRadio) {

                            invoiceRadio.checked = true;

                            invoiceRadio.dispatchEvent(new Event('change'));

                        }



                        if (obj.record_get) {

                            var record_get = obj.record_get;

                            var sum = 0;

                            $('#invoice_receipt_form .productitem_invoice tr.clonedrow_invoice').remove();

                            $('#invoice_receipt_form .productitem_invoice tr.product_field_clone_invoice').remove();

                            if (record_get.length > 0 && record_get[0].client_matter_id != null && record_get[0].client_matter_id !== '') {
                                $('#client_matter_id_invoice').val(record_get[0].client_matter_id);
                            }

                            $.each(record_get, function (index, subArray) {

                                var value_sum = parseFloat(subArray.withdraw_amount);

                                if (!isNaN(value_sum)) {

                                    sum += value_sum;

                                }



                                var rowCls = index < 1 ? 'clonedrow_invoice' : 'product_field_clone_invoice';



                                var trRows_office = `<tr class="${rowCls}">

                                    <td>

                                        <input name="id[]" type="hidden" value="${subArray.id}" />

                                        <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="${subArray.trans_date}" />

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="${subArray.entry_date}" />

                                    </td>

                                    <td>

                                        <select class="form-control gst_included_cls" name="gst_included[]">

                                            <option value="">Select</option>

                                            <option value="Yes" ${subArray.gst_included === 'Yes' ? 'selected' : ''}>Yes</option>

                                            <option value="No" ${subArray.gst_included === 'No' ? 'selected' : ''}>No</option>

                                        </select>

                                    </td>

                                    <td>

                                        <select class="form-control payment_type_invoice_per_row" name="payment_type[]">

                                            <option value="">Select</option>

                                            <option value="Professional Fee" ${subArray.payment_type === 'Professional Fee' ? 'selected' : ''}>Professional Fee</option>

                                            <option value="Department Charges" ${subArray.payment_type === 'Department Charges' ? 'selected' : ''}>Department Charges</option>

                                            <option value="Surcharge" ${subArray.payment_type === 'Surcharge' ? 'selected' : ''}>Surcharge</option>

                                            <option value="Disbursements" ${subArray.payment_type === 'Disbursements' ? 'selected' : ''}>Disbursements</option>

                                            <option value="Other Cost" ${subArray.payment_type === 'Other Cost' ? 'selected' : ''}>Other Cost</option>

                                            <option value="Discount" ${subArray.payment_type === 'Discount' ? 'selected' : ''}>Discount</option>

                                        </select>

                                    </td>

                                    <td>

                                        <input data-valid="required" class="form-control" name="description[]" type="text" value="${subArray.description}" />

                                    </td>

                                    <td>

                                        <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>

                                        <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row payment_type_invoice_per_row" name="withdraw_amount[]" type="text" value="${subArray.withdraw_amount}" />

                                    </td>

                                    <td>

                                        <a class="removeitems_invoice" href="javascript:;">${crmI('fa fa-times')}</a>

                                    </td>

                                </tr>`;



                                let $newRow = $(trRows_office);

                                $('#invoice_receipt_form .productitem_invoice').append($newRow);



                                // Initialize Flatpickr for invoice date fields
                                initFlatpickrForClass('.report_date_fields_invoice');
                                initFlatpickrForClass('.report_entry_date_fields_invoice:last', {
                                    defaultDate: new Date()
                                });



                                if (index < 1) {
                                    var invNo = subArray.trans_no || subArray.invoice_no || '';
                                    if ($('.unique_invoice_no').length) {
                                        $('.unique_invoice_no').text(invNo);
                                    }
                                    if ($('.invoice_no').length) {
                                        $('.invoice_no').val(invNo);
                                    }
                                    $('#invoice_receipt_form input[name="receipt_id"]').val(subArray.receipt_id);
                                }

                            });



                            $('#invoice_receipt_form .total_withdraw_amount_all_rows_invoice').text("$" + sum.toFixed(2));

                        }

                    }

                }

            });

        }

        window.getInfoByReceiptId = getInfoByReceiptId;

        $(document).on('click', '.updatedraftinvoice', function (e) {
            e.preventDefault();
            var rid = $(this).data('receiptid');
            if (rid === undefined || rid === null || String(rid).trim() === '') {
                return;
            }
            getInfoByReceiptId(rid);
        });




        $(document).on('change', '.client_fund_ledger_type', function () {

            var $row = $(this).closest('tr');

            var ledgerType = $(this).val();



            var $depositInput = $row.find('.deposit_amount_per_row');

            var $withdrawInput = $row.find('.withdraw_amount_per_row');

            var $invoiceInput = $row.find('.invoice_no_cls');



            // Invoice show/hide based on Fee Transfer

            if (ledgerType === 'Fee Transfer') {

                $row.find('.ledger-invoice-placeholder').hide();

                $invoiceInput.show().attr('data-valid', 'required');

                listOfInvoice();

            } else {

                $row.find('.ledger-invoice-placeholder').show();

                $invoiceInput.hide().removeAttr('data-valid').val('');

            }



            if (ledgerType !== "") {

                var fundType = (ledgerType === 'Deposit') ? 'deposit' : 'withdraw';



                if (fundType === 'deposit') {

                    $depositInput.removeAttr('readonly').attr('data-valid', 'required').val("");

                    $withdrawInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                } else if (fundType === 'withdraw') {

                    $withdrawInput.removeAttr('readonly').attr('data-valid', 'required').val("");

                    $depositInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                } else {

                    $depositInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                    $withdrawInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                }

            } else {

                $depositInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

                $withdrawInput.attr('readonly', 'readonly').removeAttr('data-valid').val("");

            }

            toggleLedgerEftposSurchargeRow($row);

        });





        function toggleLedgerEftposSurchargeRow($row) {

            var pm = $row.find('.ledger-payment-method').val();

            var ledgerType = $row.find('.client_fund_ledger_type').val();

            var $block = $row.find('.ledger-eftpos-surcharge-block');

            if (pm === 'EFTPOS' && ledgerType === 'Deposit') {

                $block.show();

            } else {

                $block.hide();

                $row.find('.ledger-eftpos-surcharge-input').val('');

            }

        }



        function toggleOfficeEftposSurchargeRow($row) {

            var pm = $row.find('.office-receipt-payment-method').val();

            var $block = $row.find('.office-eftpos-surcharge-block');

            if (pm === 'EFTPOS') {

                $block.show();

            } else {

                $block.hide();

                $row.find('.office-eftpos-surcharge-input').val('');

            }

        }

        window.toggleOfficeEftposSurchargeRow = toggleOfficeEftposSurchargeRow;

        window.toggleLedgerEftposSurchargeRow = toggleLedgerEftposSurchargeRow;



        $(document).on('change', '.ledger-payment-method', function() {

            toggleLedgerEftposSurchargeRow($(this).closest('tr'));

            grandtotalAccountTab();

        });



        $(document).on('change', '.office-receipt-payment-method', function() {

            toggleOfficeEftposSurchargeRow($(this).closest('tr'));

            grandtotalAccountTab_office();

        });



        $(document).on('keyup input', '.ledger-eftpos-surcharge-input', function() {

            grandtotalAccountTab();

        });



        $(document).on('keyup input', '.office-eftpos-surcharge-input', function() {

            grandtotalAccountTab_office();

        });





        function grandtotalAccountTab() {

            var total_deposit_amount_all_rows = 0;

            var total_withdraw_amount_all_rows = 0;



            $('.productitem tr').each(function() {

                var $row = $(this);



                // Handle deposit amount

                var depositVal = $row.find('.deposit_amount_per_row').val();

                var depositAmount = parseFloat(depositVal) || 0; // fallback to 0 if NaN

                if ($row.find('.ledger-eftpos-surcharge-block').is(':visible')) {

                    var sur = parseFloat($row.find('.ledger-eftpos-surcharge-input').val()) || 0;

                    total_deposit_amount_all_rows += depositAmount + sur;

                } else {

                    total_deposit_amount_all_rows += depositAmount;

                }



                // Handle withdraw amount

                var withdrawVal = $row.find('.withdraw_amount_per_row').val();

                var withdrawAmount = parseFloat(withdrawVal) || 0; // fallback to 0 if NaN

                total_withdraw_amount_all_rows += withdrawAmount;

            });



            $('.total_deposit_amount_all_rows').html("$" + total_deposit_amount_all_rows.toFixed(2));

            $('.total_withdraw_amount_all_rows').html("$" + total_withdraw_amount_all_rows.toFixed(2));

        }



        //create client receipt changes end





        //create invoice receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields_invoice');
        initFlatpickrForClass('.report_entry_date_fields_invoice', {
            defaultDate: new Date()
        });





        $(document).delegate('.openproductrinfo_invoice', 'click', function(){

            var clonedval_invoice = `<td>

                            <input name="id[]" type="hidden" value="" />

                            <input data-valid="required" class="form-control report_date_fields_invoice" name="trans_date[]" type="text" value="" />

                        </td>

                        <td>

                            <input data-valid="required" class="form-control report_entry_date_fields_invoice" name="entry_date[]" type="text" value="" />

                        </td>



                        <td>

                            <select class="form-control" name="gst_included[]">

                                <option value="">Select</option>

                                <option value="Yes">Yes</option>

                                <option value="No">No</option>

                            </select>

                        </td>



                        <td>

                            <select class="form-control payment_type_invoice_per_row" name="payment_type[]">

                                <option value="">Select</option>

                                <option value="Professional Fee">Professional Fee</option>

                                <option value="Department Charges">Department Charges</option>

                                <option value="Surcharge">Surcharge</option>

                                <option value="Disbursements">Disbursements</option>

                                <option value="Other Cost">Other Cost</option>

                                <option value="Discount">Discount</option>



                            </select>

                        </td>

                        <td>

                            <input data-valid="required" class="form-control" name="description[]" type="text" value="" />

                        </td>



                        <td>

                            <span class="currencyinput" style="display: inline-block;color: #34395e;">$</span>

                            <input data-valid="required" style="display: inline-block;" class="form-control withdraw_amount_invoice_per_row payment_type_invoice_per_row" name="withdraw_amount[]" type="text" value="" />

                        </td>



                        <td>

                            <a class="removeitems_invoice" href="javascript:;">${crmI('fa fa-times')}</a>

                        </td>>`;



                //var clonedval_invoice = $('.clonedrow_invoice').html();

                $('.productitem_invoice').append('<tr class="product_field_clone_invoice">'+clonedval_invoice+'</tr>');

                // Initialize Flatpickr for invoice date fields
                initFlatpickrForClass('.report_date_fields_invoice,.report_entry_date_fields_invoice');
                initFlatpickrForClass('.report_entry_date_fields_invoice:last', {
                    defaultDate: new Date()
                });

        });



        $(document).delegate('.removeitems_invoice', 'click', function(){

            var $tr_invoice    = $(this).closest('.product_field_clone_invoice');

            var trclone_invoice = $('.product_field_clone_invoice').length;

            if(trclone_invoice > 0){

                $tr_invoice.remove();

            }

            grandtotalAccountTab_invoice();

        });



        $(document).delegate('.withdraw_amount_invoice_per_row, .payment_type_invoice_per_row', 'blur', function() {

            grandtotalAccountTab_invoice();

        });



      



        function grandtotalAccountTab_invoice() {

            var total_withdraw_amount_all_rows_invoice = 0;



            // Loop through only visible rows

            $('.productitem_invoice tr:visible').each(function(index) {

                var $row = $(this);



                // Get the withdraw amount from the input field

                var withdrawVal = $row.find('.withdraw_amount_invoice_per_row').val();

                // Get the payment type from the select field

                var paymentType = $row.find('select[name="payment_type[]"]').val();



                if (withdrawVal) {

                    // Remove currency symbols, commas, and spaces

                    withdrawVal = withdrawVal.replace(/[^0-9.-]+/g, '');

                    var withdrawAmount = parseFloat(withdrawVal) || 0; // Fallback to 0 if NaN



                    // Adjust total based on payment type

                    if (paymentType === 'Discount') {

                        total_withdraw_amount_all_rows_invoice -= withdrawAmount;

                    } else {

                        total_withdraw_amount_all_rows_invoice += withdrawAmount;

                    }




                } else {


                }

            });



            //console.log('Total calculated: ' + total_withdraw_amount_all_rows_invoice);

            $('.total_withdraw_amount_all_rows_invoice').html('$' + total_withdraw_amount_all_rows_invoice.toFixed(2));

        }





        //create invoice changes end





        //create office receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields_office');
        initFlatpickrForClass('.report_entry_date_fields_office', {
            defaultDate: new Date()
        });



        $(document).delegate('.openproductrinfo_office', 'click', function(){

            var clonedval_office = $('.clonedrow_office').html();

            var $newOfficeRow = $('<tr class="product_field_clone_office">' + clonedval_office + '</tr>');

            $('.productitem_office').append($newOfficeRow);

            // Initialize Flatpickr for office receipt date fields
            initFlatpickrForClass('.report_date_fields_office,.report_entry_date_fields_office');
            initFlatpickrForClass('.report_entry_date_fields_office:last', {
                defaultDate: new Date()
            });

            toggleOfficeEftposSurchargeRow($newOfficeRow);

        });



        $(document).delegate('.removeitems_office', 'click', function(){

            var $tr_office    = $(this).closest('.product_field_clone_office');

            var trclone_office = $('.product_field_clone_office').length;

            if(trclone_office > 0){

                $tr_office.remove();

            }

            grandtotalAccountTab_office();

        });



        $(document).delegate('.total_deposit_amount_office', 'keyup', function(){

            grandtotalAccountTab_office();

        });



        function grandtotalAccountTab_office() {

            var total_deposit_amount_all_rows = 0;

            $('.productitem_office tr').each(function() {

                var $row = $(this);



                // Handle deposit amount

                var depositVal = $row.find('.total_deposit_amount_office').val();

                var depositAmount = parseFloat(depositVal) || 0; // fallback to 0 if NaN

                if ($row.find('.office-eftpos-surcharge-block').is(':visible')) {

                    var surO = parseFloat($row.find('.office-eftpos-surcharge-input').val()) || 0;

                    total_deposit_amount_all_rows += depositAmount + surO;

                } else {

                    total_deposit_amount_all_rows += depositAmount;

                }

            });



            $('.total_deposit_amount_all_rows_office').html("$" + total_deposit_amount_all_rows.toFixed(2));

        }

        window.grandtotalAccountTab_office = grandtotalAccountTab_office;

        //create office receipt changes end





        //create journal receipt start - Initialize Flatpickr
        initFlatpickrForClass('.report_date_fields_journal');
        initFlatpickrForClass('.report_entry_date_fields_journal', {
            defaultDate: new Date()
        });



        $(document).delegate('.openproductrinfo_journal', 'click', function(){

            var clonedval_journal = $('.clonedrow_journal').html();

            $('.productitem_journal').append('<tr class="product_field_clone_journal">'+clonedval_journal+'</tr>');

            // Initialize Flatpickr for journal receipt date fields
            initFlatpickrForClass('.report_date_fields_journal');
            initFlatpickrForClass('.report_entry_date_fields_journal:last', {
                defaultDate: new Date()
            });

        });



        $(document).delegate('.removeitems_journal', 'click', function(){

            var $tr_journal    = $(this).closest('.product_field_clone_journal');

            var trclone_journal = $('.product_field_clone_journal').length;

            if(trclone_journal > 0){

                $tr_journal.remove();

            }

            grandtotalAccountTab_journal();

        });



        $(document).delegate('.total_withdrawal_amount_journal,.total_deposit_amount_journal', 'keyup', function(){

            grandtotalAccountTab_journal();

        });



        $(document).delegate('.total_withdrawal_amount_journal', 'blur', function(){

            if( $(this).val() != ""){

                var randomNumber = $('#journal_top_value_db').val();

                randomNumber = Number(randomNumber);

                randomNumber = randomNumber + 1; 

                $('#journal_top_value_db').val(randomNumber);

                randomNumber = "Trans"+randomNumber;

                $(this).closest('tr').find('.unique_trans_no_journal').val(randomNumber);

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val(randomNumber);

            } else {

                $(this).closest('tr').find('.unique_trans_no_journal').val();

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val();

            }

        });



        $(document).delegate('.total_deposit_amount_journal', 'blur', function(){

            if( $(this).val() != ""){

                var randomNumber = $('#journal_top_value_db').val();

                randomNumber = Number(randomNumber);

                randomNumber = randomNumber + 1; 

                $('#journal_top_value_db').val(randomNumber);

                randomNumber = "Rec"+randomNumber;

                $(this).closest('tr').find('.unique_trans_no_journal').val(randomNumber);

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val(randomNumber);

            } else {

                $(this).closest('tr').find('.unique_trans_no_journal').val();

                $(this).closest('tr').find('.unique_trans_no_hidden_journal').val();

            }

        });



        function grandtotalAccountTab_journal(){

            var total_withdrawal_amount_all_rows_journal = 0;

            $('.productitem_journal tr').each(function(){

            if($(this).find('.total_withdrawal_amount_journal').val() != ''){

                    var withdrawal_amount_per_row_journal = $(this).find('.total_withdrawal_amount_journal').val();

                }else{

                    var withdrawal_amount_per_row_journal = 0;

                }

                total_withdrawal_amount_all_rows_journal += parseFloat(withdrawal_amount_per_row_journal);

            });

            $('.total_withdraw_amount_all_rows_journal').html("$"+total_withdrawal_amount_all_rows_journal.toFixed(2));

        }

        //create journal receipt changes end



        // Initialize Flatpickr for education service start date
        if (typeof flatpickr !== 'undefined') {
            const eduDateEl = $('#edu_service_start_date')[0];
            if (eduDateEl && !$(eduDateEl).data('flatpickr')) {
                flatpickr(eduDateEl, {
                    dateFormat: 'd/m/Y',
                    allowInput: true,
                    clickOpens: true,
                    defaultDate: $(eduDateEl).val() || null,
                    locale: { firstDayOfWeek: 1 }
                });
            }
        }



        $('.filter_btn').on('click', function(){

            $('.filter_panel').toggle();

        });



        // Service type toggle REMOVED - form #createservicetaken deleted (modal removed in Phase 2)



        //Set Tom Select wrapper width for change assignee

        $('#changeassignee').mmSelect();

        $('#changeassignee').next('.ts-wrapper').first().css('width', '220px');



        var windowsize = $(window).width();

        if(windowsize > 2000){

            $('.add_note').css('width','980px');

        }



        // --- not picked call button code start ---

        $(document).delegate('.not_picked_call', 'click', function (e) {

            var clientName = window.ClientDetailConfig.clientFirstName || 'client';

            clientName = clientName.charAt(0).toUpperCase() + clientName.slice(1).toLowerCase(); //alert(clientName);



            var message = (window.ClientDetailConfig.notPickedCallSmsDefault || '').trim();
            if (!message) {
                message = 'Hi ' + clientName + ',\n\nWe tried reaching you but couldn\'t connect. Please call us at 0396021330 or let us know a suitable time.\n\nPlease do not reply via SMS.\n\nBansal Immigration';
            }

            $('#messageText').val(message); // Set dynamic message text

            $('#notPickedCallModal').modal('show'); // Show Modal Window



            $('.sendMessage').on('click', function () {

                var message = $('#messageText').val();

                var not_picked_call = 1;

                $.ajax({

                    url: window.ClientDetailConfig.urls.notPickedCall,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },

                    type: 'POST',

                    dataType: 'json',

                    data: {

                        id: window.ClientDetailConfig.clientId,

                        not_picked_call: not_picked_call,

                        message: message

                    },

                    success: function (response) {

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;

                        if (obj.not_picked_call == 1) {

                            alert(obj.message);

                        } else {

                            alert(obj.message);

                        }

                        getallactivities();

                        $('#notPickedCallModal').modal('hide'); // Hide Modal Window

                    }

                });

            });

        });



        // --- not picked call button code end ---

        // Appointment booking, time slots, getDisabledDateTime, calendar UI - see modules/appointments.js

        $('.manual_email_phone_verified').on('change', function(){

            if( $(this).is(":checked") ) {

                $('.manual_email_phone_verified').val(1);

                var manual_email_phone_verified = 1;

            } else {

                $('.manual_email_phone_verified').val(0);

                var manual_email_phone_verified = 0;

            }



            var client_id = window.ClientDetailConfig.clientId; //alert(site_url);

            $.ajax({

                url: site_url+'/clients/update-email-verified',

                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},

                type:'POST',

                data:{manual_email_phone_verified:manual_email_phone_verified,client_id:client_id},

                success: function(responses){

                    location.reload();

                }

            });

        });



        //alert('ready');

        $('#feather-icon').click(function(){

            var windowsize = $(window).width(); 

            if($('.main-sidebar').width() == 65){

                if(windowsize > 2000){

                    $('.add_note,.last_updated_date').css('width','980px');

                } else {

                    $('.add_note').css('width','338px');

                    $('.last_updated_date').css('width','348px');

                }



            } else if($('.main-sidebar').width() == 250) {

                if(windowsize > 2000){

                    $('.add_note,.last_updated_date').css('width','1040px');

                } else {

                    $('.add_note').css('width','433px');

                    $('.last_updated_date').css('width','442px');

                }

            }

        });

        //set height of right side section

        var left_upper_height = $('.left_section_upper').height();

        //var left_section_lower = $('.left_section_lower').height();

        var left_section_lower = 0;

        var total_left  = left_upper_height + left_section_lower;

        total_left = total_left +25;



        var right_section_height = $('.right_section').height();

       



        //alert(left_upper_height+'==='+left_section_lower+'==='+total_left+'==='+right_section_height);

        if(right_section_height >total_left ){ 

            var total_left_px = total_left+'px';

            $('.right_section').css({"maxHeight":total_left_px});

            $('.right_section').css({"overflow": 'scroll' });

        } else {  

            var total_left_px = total_left+'px';

            $('.right_section').css({"maxHeight":total_left_px});

        }





        let css_property =

            {

                "display": "none",

            }

        $('#create_note_d').hide();

        $('.main-footer').css(css_property);







        $(document).delegate('.uploadmail','click', function(){

            $('#maclient_id').val(window.ClientDetailConfig.clientId);

            $('#uploadmail').modal('show');

        });



        $(document).delegate('.uploadAndFetchMail','click', function(){

            $('#maclient_id_fetch').val(window.ClientDetailConfig.clientId);

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#upload_inbox_mail_client_matter_id').val(hidden_client_matter_id);

            $('#uploadAndFetchMailModel').modal('show');

        });

        // Handle uploadAndFetchMail form submission via AJAX
        function sanitizeEmailUploadFilename(filename) {
            if (!filename || typeof filename !== 'string') {
                return 'email_' + Date.now() + '.msg';
            }
            var lastDot = filename.lastIndexOf('.');
            var extension = lastDot >= 0 ? filename.slice(lastDot + 1) : '';
            var nameWithoutExt = lastDot >= 0 ? filename.slice(0, lastDot) : filename;
            var sanitizedName = nameWithoutExt.replace(/[^a-zA-Z0-9\-_.]/g, '_');
            sanitizedName = sanitizedName.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
            if (!sanitizedName) {
                sanitizedName = 'email_' + Date.now();
            }
            var sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
            if (sanitizedFilename.length > 255) {
                var maxNameLength = 255 - extension.length - (extension ? 1 : 0);
                if (maxNameLength > 0) {
                    sanitizedName = sanitizedName.slice(0, maxNameLength);
                    sanitizedFilename = extension ? sanitizedName + '.' + extension : sanitizedName;
                } else {
                    sanitizedFilename = 'email_' + Date.now() + (extension ? '.' + extension : '');
                }
            }
            return sanitizedFilename;
        }

        function buildEmailUploadFormData(form) {
            var formData = new FormData(form);
            var fileInput = form.querySelector('input[name="email_files[]"]');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                return formData;
            }
            var rebuilt = new FormData();
            formData.forEach(function(value, key) {
                if (key !== 'email_files[]') {
                    rebuilt.append(key, value);
                }
            });
            Array.from(fileInput.files).forEach(function(file) {
                rebuilt.append('email_files[]', file, sanitizeEmailUploadFilename(file.name));
            });
            return rebuilt;
        }

        function resolveEmailUploadAjaxError(xhr) {
            var errorMessage = 'An unexpected error occurred. Please try again.';
            if (xhr.status === 403) {
                var responseText = xhr.responseText || '';
                var isHtml = /<html[\s>]/i.test(responseText) || /<!DOCTYPE/i.test(responseText);
                if (isHtml || (responseText.indexOf('Forbidden') !== -1 && !(xhr.responseJSON && xhr.responseJSON.message))) {
                    return 'The server blocked this upload (security filter). Rename files to remove special characters such as apostrophes and try again.';
                }
            }
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errors = xhr.responseJSON.errors;
                var errorHtml = '<span class="alert alert-danger">';
                for (var field in errors) {
                    errorHtml += errors[field][0] + '<br>';
                }
                errorHtml += '</span>';
                return errorHtml;
            }
            if (xhr.responseJSON && xhr.responseJSON.message) {
                return xhr.responseJSON.message;
            }
            return errorMessage;
        }

        $(document).on('submit', '#uploadAndFetchMail', function(e) {
            e.preventDefault();
            
            var formData = buildEmailUploadFormData(this);
            $('.popuploader').show();
            $('.custom-error-msg').html('');
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('.popuploader').hide();
                    if (response.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        $('#uploadAndFetchMailModel').modal('hide');
                        // Reload the page to show the uploaded emails
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    var resolved = resolveEmailUploadAjaxError(xhr);
                    if (typeof resolved === 'string' && resolved.indexOf('<span') === 0) {
                        $('.custom-error-msg').html(resolved);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + resolved + '</span>');
                    }
                }
            });
        });

        // Handle uploadSentAndFetchMail form submission via AJAX
        $(document).on('submit', '#uploadSentAndFetchMail', function(e) {
            e.preventDefault();
            
            var formData = buildEmailUploadFormData(this);
            $('.popuploader').show();
            $('.custom-error-msg').html('');
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('.popuploader').hide();
                    if (response.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        $('#uploadSentAndFetchMailModel').modal('hide');
                        // Reload the page to show the uploaded emails
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr) {
                    $('.popuploader').hide();
                    var resolved = resolveEmailUploadAjaxError(xhr);
                    if (typeof resolved === 'string' && resolved.indexOf('<span') === 0) {
                        $('.custom-error-msg').html(resolved);
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + resolved + '</span>');
                    }
                }
            });
        });







        // Set up CSRF token for all AJAX requests

        $.ajaxSetup({

            headers: {

                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')

            }

        });



        // Form 956 PDF: single download on create (not on visa tab reload — see visa_documents + sidebar-tabs)
        window.downloadForm956PdfOnce = function(url, onDone) {
            if (!url) {
                if (typeof onDone === 'function') {
                    onDone();
                }
                return;
            }
            fetch(url, { credentials: 'same-origin' })
                .then(function(r) {
                    if (!r.ok) {
                        throw new Error('PDF request failed');
                    }
                    return r.blob();
                })
                .then(function(blob) {
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'Form956.pdf';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(a.href);
                    if (typeof onDone === 'function') {
                        onDone();
                    }
                })
                .catch(function() {
                    window.open(url, '_blank');
                    if (typeof onDone === 'function') {
                        onDone();
                    }
                });
        };

        /**
         * Soft-insert Form 956 checklist row into the active Visa category (no full reload).
         * Returns true when the row is present / inserted; false when UI update is not possible.
         */
        window.appendForm956ChecklistRow = function(response) {
            var doc = response && response.document;
            if (!doc || !doc.id || !doc.folder_name) {
                // Created without a visa checklist folder — nothing to insert.
                return true;
            }

            var categoryId = doc.folder_name;
            var $visaTab = $('#visadocuments-tab');
            var $tbody = $visaTab.find('.migdocumnetlist_' + categoryId);
            if (!$tbody.length) {
                $tbody = $visaTab.find('[id="' + categoryId + '-subtab6"] tbody.migdocumnetlist1').first();
            }
            if (!$visaTab.length || !$tbody.length) {
                return false;
            }
            if ($tbody.find('#id_' + doc.id).length) {
                return true;
            }

            var checklist = doc.checklist || '956 Form';
            var matterId = doc.client_matter_id || '';
            var clientId = doc.client_id || (window.ClientDetailConfig && window.ClientDetailConfig.clientId) || '';
            var downloadUrl = response.download_url || response.preview_url || '';
            var catTitle = $visaTab.find('.subtab6-button[data-subtab6="' + categoryId + '"]').first().text().trim() || '';
            var csrf = $('meta[name="csrf-token"]').attr('content') || '';
            var cloudIcon = (typeof crmI === 'function') ? crmI('fa-cloud-upload-alt') : '';

            function escAttr(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }
            function escText(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            var fileCellHtml =
                '<div class="form956-download-upload" style="display: flex; flex-direction: column; gap: 10px;" ' +
                'data-download-url="' + escAttr(downloadUrl) + '" data-doc-id="' + escAttr(doc.id) + '">' +
                '<p class="mb-0" style="font-size: 12px; color: #374151;">The Form 956 PDF downloads when you create it. Check, update, then upload your completed form below.</p>' +
                '<div class="migration_upload_document" style="display: inline-block;">' +
                '<form method="POST" enctype="multipart/form-data" id="mig_upload_form_' + escAttr(doc.id) + '">' +
                '<input type="hidden" name="_token" value="' + escAttr(csrf) + '">' +
                '<input type="hidden" name="clientid" value="' + escAttr(clientId) + '">' +
                '<input type="hidden" name="client_matter_id" value="' + escAttr(matterId) + '">' +
                '<input type="hidden" name="fileid" value="' + escAttr(doc.id) + '">' +
                '<input type="hidden" name="type" value="client">' +
                '<input type="hidden" name="doctype" value="visa">' +
                '<input type="hidden" name="doccategory" value="' + escAttr(catTitle) + '">' +
                '<div class="document-drag-drop-zone visa-doc-drag-zone" data-fileid="' + escAttr(doc.id) + '" data-doccategory="' + escAttr(categoryId) + '" data-formid="mig_upload_form_' + escAttr(doc.id) + '">' +
                '<div class="drag-zone-inner">' + cloudIcon +
                '<span class="drag-zone-text">Drag file here or <strong>click to browse</strong></span></div></div>' +
                '<input class="migdocupload d-none" data-fileid="' + escAttr(doc.id) + '" data-doccategory="' + escAttr(categoryId) + '" type="file" name="document_upload" style="display: none;"/>' +
                '</form></div></div>';

            var rowHtml =
                '<tr class="drow" data-matterid="' + escAttr(matterId) + '" data-catid="' + escAttr(categoryId) + '" id="id_' + escAttr(doc.id) + '">' +
                    '<td style="white-space: initial;">' +
                        '<div data-id="' + escAttr(doc.id) + '" data-visachecklistname="' + escAttr(checklist) + '" class="visachecklist-row" ' +
                        'style="display: flex; align-items: center; gap: 8px;" ' +
                        'oncontextmenu="showVisaChecklistContextMenu(event, ' + parseInt(doc.id, 10) + '); return false;">' +
                        '<span style="flex: 1;">' + escText(checklist) + '</span></div>' +
                    '</td>' +
                    '<td style="white-space: initial;">' + fileCellHtml + '</td>' +
                    '<td>' +
                        '<a class="renamechecklist" data-id="' + escAttr(doc.id) + '" href="javascript:;" style="display: none;"></a>' +
                        '<a class="delete-checklist-btn" data-id="' + escAttr(doc.id) + '" data-checklist="' + escAttr(checklist) + '" href="javascript:;" style="display: none;"></a>' +
                    '</td>' +
                '</tr>';

            $tbody.prepend(rowHtml);

            // Keep destination category visible.
            $visaTab.find('.subtab6-button').removeClass('active');
            $visaTab.find('.subtab6-pane').removeClass('active');
            $visaTab.find('.subtab6-button[data-subtab6="' + categoryId + '"]').addClass('active');
            $visaTab.find('[id="' + categoryId + '-subtab6"]').addClass('active');

            if (typeof refreshLucideIcons === 'function') {
                refreshLucideIcons($tbody.find('#id_' + doc.id)[0]);
            }
            if (typeof window.initVisaDocDragDrop === 'function') {
                window.initVisaDocDragDrop();
            }

            var $row = $tbody.find('#id_' + doc.id);
            if ($row.length && $row[0].scrollIntoView) {
                try {
                    $row[0].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                } catch (e) {}
            }

            return true;
        };

        // Handle form submission via AJAX

        $('#createForm956').on('submit', function(e) {

            e.preventDefault();

            var $btn = $(this).find('button[type="submit"]');

            $btn.prop('disabled', true);

            $.ajax({

                url: $(this).attr('action'),

                method: 'POST',

                data: $(this).serialize(),

                dataType: 'json',

                success: function(response) {

                    if (response.success) {

                        // Hide the modal

                        $('#form956CreateFormModel').modal('hide');

                        // Download PDF once, then soft-insert checklist row (reload only if UI update fails).
                        window.downloadForm956PdfOnce(response.download_url, function() {
                            var inserted = false;
                            try {
                                inserted = !!window.appendForm956ChecklistRow(response);
                            } catch (uiErr) {
                                console.warn('[Form956] Soft checklist insert failed, falling back to reload', uiErr);
                                inserted = false;
                            }

                            if (!inserted && response.document) {
                                location.reload();
                                return;
                            }

                            $btn.prop('disabled', false);

                            if (typeof getallactivities === 'function') {
                                getallactivities();
                            }
                            if (typeof showClientDocFeedback === 'function') {
                                showClientDocFeedback('success', response.message || 'Form 956 created successfully.');
                            } else if (typeof iziToast !== 'undefined' && iziToast.success) {
                                iziToast.success({
                                    title: 'Success',
                                    message: response.message || 'Form 956 created successfully.',
                                    position: 'topRight'
                                });
                            } else {
                                alert(response.message || 'Form 956 created successfully.');
                            }
                        });

                    }

                },

                error: function(xhr) {

                    $('.custom-error-msg').html('');

                    if (xhr.responseJSON && xhr.responseJSON.errors) {

                        let errors = xhr.responseJSON.errors;

                        for (let field in errors) {

                            $('.custom-error-msg').append('<p class="text-red-600">' + errors[field][0] + '</p>');

                        }

                    } else {

                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while creating Form 956. Please try again.';

                        $('.custom-error-msg').append('<p class="text-red-600">' + msg + '</p>');

                    }

                },

                complete: function(xhr, textStatus) {

                    if (textStatus === 'error' || textStatus === 'parsererror' || textStatus === 'timeout') {

                        $btn.prop('disabled', false);

                        return;

                    }

                    try {

                        var r = xhr.responseJSON;

                        if (!r && xhr.responseText) {

                            r = JSON.parse(xhr.responseText);

                        }

                        if (!r || !r.success) {

                            $btn.prop('disabled', false);

                        }

                    } catch (err) {

                        $btn.prop('disabled', false);

                    }

                }

            });

        });



        // Handle not lodged checkbox

        const notLodgedInput = document.querySelector('input[name="not_lodged"]');
        if (notLodgedInput) {
            notLodgedInput.addEventListener('change', function() {

            const dateLodgedInput = document.getElementById('date_lodged');

            dateLodgedInput.disabled = this.checked;

            if (this.checked) {

                dateLodgedInput.value = '';

            }

            });
        }



        // Populate agent details when the modal opens

        $(document).delegate('.form956CreateForm', 'click', function() {

            $('#form956_client_id').val(window.ClientDetailConfig.clientId);

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#form956_client_matter_id').val(hidden_client_matter_id);

            // When clicked from visa document page, set folder for checklist placement
            var folderId = $(this).data('form956-folder');
            $('#form956_folder_name').val(folderId || '');

            // Matter is required for agent details and for saving Form 956 to visa checklist
            if (!hidden_client_matter_id || hidden_client_matter_id === '' || hidden_client_matter_id === null) {
                alert('Please select a matter before creating Form 956.\n\nA matter is required to populate agent information and to save the form to the visa document checklist.');
                return;
            }

            getMigrationAgentDetail(hidden_client_matter_id);

            $('#form956CreateFormModel').modal('show');

        });
        //Get Migration Agent Detail

        function getMigrationAgentDetail(client_matter_id) {

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getMigrationAgentDetail,

                sync:true,

                data: {client_matter_id:client_matter_id},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.agentInfo){

                        $('#agent_id').val(obj.agentInfo.agentId);

                        if(obj.agentInfo.last_name != ''){

                            var agentFullName = obj.agentInfo.first_name+' '+obj.agentInfo.last_name;

                        } else {

                            var agentFullName =  obj.agentInfo.first_name;

                        }

                        $('#agent_name').val(agentFullName);

                        $('#agent_name_label').html(agentFullName);



                        $('#business_name').val(obj.agentInfo.company_name);

                        $('#business_name_label').html(obj.agentInfo.company_name);



                        $('#application_type').val(obj.matterInfo.title);

                        $('#application_type_label').html(obj.matterInfo.title);

                    }

                }

            });

        }



        /** Toggle loading state on Create Visa Agreement buttons (checklists tab). */
        function setVisaAgreementCreateButtonLoading($btn, isLoading) {
            if (!$btn || !$btn.length) {
                return;
            }
            if (isLoading) {
                if ($btn.data('visaAgreementCreateLoading')) {
                    return;
                }
                $btn.data('visaAgreementCreateLoading', true);
                $btn.data('visaAgreementCreateHtml', $btn.html());
                $btn.prop('disabled', true).attr('aria-busy', 'true');
                $btn.html(crmI('fas fa-spinner fa-spin', { class: 'mr-1' }) + 'Creating...');
            } else {
                if (!$btn.data('visaAgreementCreateLoading')) {
                    return;
                }
                $btn.data('visaAgreementCreateLoading', false);
                var saved = $btn.data('visaAgreementCreateHtml');
                if (saved !== undefined) {
                    $btn.html(saved);
                }
                $btn.removeData('visaAgreementCreateHtml');
                $btn.prop('disabled', false).removeAttr('aria-busy');
            }
        }

        // Direct AJAX submission for visa agreement

        $(document).delegate('.visaAgreementCreateForm', 'click', function() {

            var $btn = $(this);

            var client_id = window.ClientDetailConfig.clientId;

            // Prefer checklist row matter (avoids sidebar matter sync, which reloads the page when URL matter differs).
            var client_matter_id = $btn.data('client-matter-id');
            if (!client_matter_id) {
                if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                    client_matter_id = $('.general_matter_checkbox_client_detail').val();
                } else {
                    client_matter_id = $('#sel_matter_id_client_detail').val();
                }
            }

            // FIX: Validate that a matter is selected before proceeding
            // This prevents generating corrupted agreements without matter data
            if (!client_matter_id || client_matter_id === '' || client_matter_id === null) {
                alert('Please select a matter before generating the visa agreement.\n\nA matter is required to populate visa details, fees, and agent information.');
                return false;
            }

            setVisaAgreementCreateButtonLoading($btn, true);

            // First check if cost assignment exists

            $.ajax({

                url: window.ClientDetailConfig.urls.checkCostAssignment,

                type: "POST",

                data: {

                    client_id: client_id,

                    client_matter_id: client_matter_id,

                    _token: $('meta[name="csrf-token"]').attr('content')

                },

                success: function (response) {

                    if (response.exists) {

                        // Get agent details and then submit via AJAX

                        $.ajax({

                            type: 'post',

                            url: window.ClientDetailConfig.urls.getVisaAgreementAgent,

                            data: {client_matter_id: client_matter_id},

                            success: function(agentResponse) {

                                var obj = safeParseJsonResponse(agentResponse);
                                if (!obj) {
                                    setVisaAgreementCreateButtonLoading($btn, false);
                                    return;
                                }
                                if(obj.agentInfo) {

                                    // Prepare form data for AJAX submission

                                    var formData = {

                                        _token: $('meta[name="csrf-token"]').attr('content'),

                                        client_id: client_id,

                                        client_matter_id: client_matter_id,

                                        agent_id: obj.agentInfo.agentId,

                                        agent_name: obj.agentInfo.last_name != '' ?

                                            obj.agentInfo.first_name + ' ' + obj.agentInfo.last_name :

                                            obj.agentInfo.first_name,

                                        business_name: obj.agentInfo.company_name || ''

                                    };



                                    // Submit via AJAX

                                    $.ajax({

                                        url: window.ClientDetailConfig.urls.generateAgreement,

                                        method: 'POST',

                                        data: formData,

                                        success: function(response) {

                                            try {

                                            // Handle successful response

                                            if (response.success && response.download_url) {

                                                // Use window.open for download - single method to prevent duplicates

                                                try {

                                                    // Primary method: window.open for download

                                                    var downloadWindow = window.open(response.download_url, '_blank');

                                                    

                                                    // Check if window.open was blocked or failed immediately

                                                    if (!downloadWindow || downloadWindow.closed) {

                                                        // Fallback: Use direct link click only if window.open was blocked

                                                        var link = document.createElement('a');

                                                        link.href = response.download_url;

                                                        link.download = 'visa_agreement_' + new Date().getTime() + '.docx';

                                                        link.target = '_blank';

                                                        link.style.display = 'none';

                                                        document.body.appendChild(link);

                                                        link.click();

                                                        // Clean up after a short delay

                                                        setTimeout(function() {

                                                            document.body.removeChild(link);

                                                        }, 100);

                                                    }

                                                    

                                                    // Show success message

                                                    alert('Visa agreement generated successfully!');

                                                } catch (error) {

                                                    console.error('Download error:', error);

                                                    

                                                    // Last resort: Direct link approach only if window.open throws an error

                                                    try {

                                                        var link = document.createElement('a');

                                                        link.href = response.download_url;

                                                        link.download = 'visa_agreement_' + new Date().getTime() + '.docx';

                                                        link.target = '_blank';

                                                        link.style.display = 'none';

                                                        document.body.appendChild(link);

                                                        link.click();

                                                        setTimeout(function() {

                                                            document.body.removeChild(link);

                                                        }, 100);

                                                        

                                                        alert('Visa agreement generated successfully!');

                                                    } catch (fallbackError) {

                                                        console.error('Fallback download error:', fallbackError);

                                                        alert('Visa agreement generated successfully! Please check your downloads folder or browser download settings.');

                                                    }

                                                }

                                            } else {

                                                alert('Document generated but no download URL returned.');

                                            }

                                            } finally {

                                                setVisaAgreementCreateButtonLoading($btn, false);

                                            }

                                        },

                                        error: function(xhr) {

                                            setVisaAgreementCreateButtonLoading($btn, false);

                                            // Handle errors

                                            if (xhr.responseJSON && xhr.responseJSON.message) {

                                                alert('Error: ' + xhr.responseJSON.message);

                                            } else {

                                                alert('Error generating visa agreement.');

                                            }

                                        }

                                    });

                                } else {

                                    setVisaAgreementCreateButtonLoading($btn, false);

                                    alert("Agent information not found.");

                                }

                            },

                            error: function() {

                                setVisaAgreementCreateButtonLoading($btn, false);

                                alert("Error fetching agent details.");

                            }

                        });

                    } else {

                        setVisaAgreementCreateButtonLoading($btn, false);

                        alert("Please first create Cost Assignment.");

                    }

                },

                error: function() {

                    setVisaAgreementCreateButtonLoading($btn, false);

                    alert("Error checking cost assignment.");

                }

            });

        });



         //Get Visa agreement Migration Agent Detail

        function getVisaAggreementMigrationAgentDetail(client_matter_id) {

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getVisaAgreementAgent,

                sync:true,

                data: {client_matter_id:client_matter_id},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.agentInfo){

                        $('#visaagree_agent_id').val(obj.agentInfo.agentId);

                        if(obj.agentInfo.last_name != ''){

                            var agentFullName = obj.agentInfo.first_name+' '+obj.agentInfo.last_name;

                        } else {

                            var agentFullName =  obj.agentInfo.first_name;

                        }

                        $('#visaagree_agent_name').val(agentFullName);

                        $('#visaagree_agent_name_label').html(agentFullName);



                        $('#visaagree_business_name').val(obj.agentInfo.company_name);

                        $('#visaagree_business_name_label').html(obj.agentInfo.company_name);

                    }

                }

            });

        }



        // Handle form submission via AJAX

        $('#visaagreementform11').on('submit', function(e) {

            e.preventDefault();

            let form = $(this);

            $.ajax({

                url: form.attr('action'),

                method: 'POST',

                data: form.serialize(),

                success: function(response) {

                    // Hide modal if needed

                    $('#visaAgreementCreateFormModel').modal('hide');



                    // Redirect to download URL

                    if (response.download_url) {

                        window.location.href = response.download_url;

                    } else {

                        alert('Document generated but no download URL returned.');

                    }

                },

                error: function(xhr) {

                    $('.custom-error-msg').html('');

                    let errors = xhr.responseJSON?.errors || {};

                    for (let field in errors) {

                        $('.custom-error-msg').append('<p class="text-red-600">' + errors[field][0] + '</p>');

                    }

                }

            });

        });





        // Note: costAssignmentCreateForm click handler and switchToCostAssignmentList
        // removed — Form Generation tab no longer exists. Cost assignment create/amend
        // now happens exclusively via the modal in the Checklists tab.



         //Get Cost assignment Migration Agent Detail
        // modalContainer: optional selector (e.g. '#costAssignmentCreateFormModel') to scope field updates to a specific container (for modal edit)
        // onLoadedCallback: optional function called after data is loaded (e.g. to show modal)
        function getCostAssignmentMigrationAgentDetail(client_id,client_matter_id, modalContainer, onLoadedCallback) {

            var $scope = (modalContainer && $(modalContainer).length) ? $(modalContainer) : $(document);

            $.ajax({

                type:'post',

                url: window.ClientDetailConfig.urls.getCostAssignmentAgent,

                sync:true,

                data: {client_id:client_id,client_matter_id:client_matter_id},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) return;
                    if(obj.agentInfo){

                        $scope.find('#costassign_agent_id').val(obj.agentInfo.agentId);

                        if(obj.agentInfo.last_name != ''){

                            var agentFullName = obj.agentInfo.first_name+' '+obj.agentInfo.last_name;

                        } else {

                            var agentFullName =  obj.agentInfo.first_name;

                        }

                        //$('#costassign_agent_name').val(agentFullName);

                        $scope.find('#costassign_agent_name_label').html(agentFullName);



                        //$('#costassign_business_name').val(obj.agentInfo.company_name);

                        $scope.find('#costassign_business_name_label').html(obj.agentInfo.company_name);

                        $scope.find('#costassign_client_matter_name_label').html(obj.matterInfo.title);



                        //Fetch matter related cost assignments

                        if(obj.cost_assignment_matterInfo){

                            $scope.find('#surcharge').val(obj.cost_assignment_matterInfo.surcharge).trigger('change');

                            $scope.find('#Dept_Base_Application_Charge').val(obj.cost_assignment_matterInfo.Dept_Base_Application_Charge);

                            $scope.find('#Dept_Base_Application_Charge_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Base_Application_Charge_no_of_person);



                            $scope.find('#Dept_Non_Internet_Application_Charge').val(obj.cost_assignment_matterInfo.Dept_Non_Internet_Application_Charge);

                            $scope.find('#Dept_Non_Internet_Application_Charge_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Non_Internet_Application_Charge_no_of_person);



                            $scope.find('#Dept_Additional_Applicant_Charge_18_Plus').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_18_Plus);

                            $scope.find('#Dept_Additional_Applicant_Charge_18_Plus_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_18_Plus_no_of_person);



                            $scope.find('#Dept_Additional_Applicant_Charge_Under_18').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_Under_18);

                            $scope.find('#Dept_Additional_Applicant_Charge_Under_18_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_Under_18_no_of_person);



                            $scope.find('#Dept_Subsequent_Temp_Application_Charge').val(obj.cost_assignment_matterInfo.Dept_Subsequent_Temp_Application_Charge);

                            $scope.find('#Dept_Subsequent_Temp_Application_Charge_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Subsequent_Temp_Application_Charge_no_of_person);



                            $scope.find('#Dept_Second_VAC_Instalment_Charge_18_Plus').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Charge_18_Plus);

                            $scope.find('#Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person);



                            $scope.find('#Dept_Second_VAC_Instalment_Under_18').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Under_18);

                            $scope.find('#Dept_Second_VAC_Instalment_Under_18_no_of_person').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Under_18_no_of_person);



                            $scope.find('#Dept_Nomination_Application_Charge').val(obj.cost_assignment_matterInfo.Dept_Nomination_Application_Charge);

                            $scope.find('#Dept_Sponsorship_Application_Charge').val(obj.cost_assignment_matterInfo.Dept_Sponsorship_Application_Charge);

                            $scope.find('#saf_levy').val(obj.cost_assignment_matterInfo.saf_levy != null ? obj.cost_assignment_matterInfo.saf_levy : '');



                            $scope.find('#TotalDoHACharges').val(obj.cost_assignment_matterInfo.TotalDoHACharges);

                            $scope.find('#TotalDoHASurcharges').val(obj.cost_assignment_matterInfo.TotalDoHASurcharges);



                            $scope.find('#Block_1_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_1_Ex_Tax);

                            $scope.find('#Block_2_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_2_Ex_Tax);

                            $scope.find('#Block_3_Ex_Tax').val(obj.cost_assignment_matterInfo.Block_3_Ex_Tax);



                            $scope.find('#additional_fee_1').val(obj.cost_assignment_matterInfo.additional_fee_1);

                            $scope.find('#TotalBLOCKFEE').val(obj.cost_assignment_matterInfo.TotalBLOCKFEE);

                            applyCostAssignmentDiscountUi(
                                $scope,
                                isCostAssignmentDiscountEnabled(obj.cost_assignment_matterInfo.discount_enabled),
                                obj.cost_assignment_matterInfo.discount
                            );

                        } else {

                            $scope.find('#surcharge').val(obj.matterInfo.surcharge).trigger('change');

                            $scope.find('#Dept_Base_Application_Charge').val(obj.matterInfo.Dept_Base_Application_Charge);
                            $scope.find('#Dept_Base_Application_Charge_no_of_person').val(1);

                            $scope.find('#Dept_Non_Internet_Application_Charge').val(obj.matterInfo.Dept_Non_Internet_Application_Charge);
                            $scope.find('#Dept_Non_Internet_Application_Charge_no_of_person').val(0);

                            $scope.find('#Dept_Additional_Applicant_Charge_18_Plus').val(obj.matterInfo.Dept_Additional_Applicant_Charge_18_Plus);
                            $scope.find('#Dept_Additional_Applicant_Charge_18_Plus_no_of_person').val(0);

                            $scope.find('#Dept_Additional_Applicant_Charge_Under_18').val(obj.matterInfo.Dept_Additional_Applicant_Charge_Under_18);
                            $scope.find('#Dept_Additional_Applicant_Charge_Under_18_no_of_person').val(0);

                            $scope.find('#Dept_Subsequent_Temp_Application_Charge').val(obj.matterInfo.Dept_Subsequent_Temp_Application_Charge);
                            $scope.find('#Dept_Subsequent_Temp_Application_Charge_no_of_person').val(0);

                            $scope.find('#Dept_Second_VAC_Instalment_Charge_18_Plus').val(obj.matterInfo.Dept_Second_VAC_Instalment_Charge_18_Plus);
                            $scope.find('#Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person').val(0);

                            $scope.find('#Dept_Second_VAC_Instalment_Under_18').val(obj.matterInfo.Dept_Second_VAC_Instalment_Under_18);
                            $scope.find('#Dept_Second_VAC_Instalment_Under_18_no_of_person').val(0);

                            $scope.find('#Dept_Nomination_Application_Charge').val(obj.matterInfo.Dept_Nomination_Application_Charge);

                            $scope.find('#Dept_Sponsorship_Application_Charge').val(obj.matterInfo.Dept_Sponsorship_Application_Charge);

                            $scope.find('#saf_levy').val('');



                            $scope.find('#Block_1_Ex_Tax').val(obj.matterInfo.Block_1_Ex_Tax);

                            $scope.find('#Block_2_Ex_Tax').val(obj.matterInfo.Block_2_Ex_Tax);

                            $scope.find('#Block_3_Ex_Tax').val(obj.matterInfo.Block_3_Ex_Tax);



                            $scope.find('#additional_fee_1').val(obj.matterInfo.additional_fee_1);

                            $scope.find('#TotalBLOCKFEE').val(obj.matterInfo.TotalBLOCKFEE);

                            $scope.find('#TotalDoHACharges').val(obj.matterInfo.TotalDoHACharges);

                            $scope.find('#TotalDoHASurcharges').val(obj.matterInfo.TotalDoHASurcharges);

                            applyCostAssignmentDiscountUi($scope, false, '0.00');

                        }

                        // Initialize calculation handlers and trigger calculations after data is loaded
                        // When modalContainer provided, pass it so handlers bind to modal fields
                        setTimeout(function() {
                            if (typeof window.initializeCostAssignmentCalculations === 'function') {
                                window.initializeCostAssignmentCalculations(modalContainer);
                            }
                            if (typeof window.calculateTotalBlockFee === 'function') {
                                window.calculateTotalBlockFee(modalContainer);
                            }
                            if (typeof window.calculateTotalDoHACharges === 'function') {
                                window.calculateTotalDoHACharges(modalContainer);
                            }
                            if (typeof window.calculateTotalDoHASurcharges === 'function') {
                                window.calculateTotalDoHASurcharges(modalContainer);
                            }
                            if (typeof onLoadedCallback === 'function') {
                                onLoadedCallback();
                            }
                        }, 100);

                    } else if (typeof onLoadedCallback === 'function') {
                        // No agentInfo - still show modal (e.g. for error display)
                        onLoadedCallback();
                    }

                }

            });

        }

        // Make function available globally for subtab handlers
        window.getCostAssignmentMigrationAgentDetail = getCostAssignmentMigrationAgentDetail;

        function isCostAssignmentDiscountEnabled(value) {
            return value === true || value === 1 || value === '1' || value === 'true';
        }

        function applyCostAssignmentDiscountUi($scope, enabled, amount) {
            var isEnabled = !!enabled;
            var $checkbox = $scope.find('.js-cost-discount-enabled');
            var $wrap = $scope.find('.js-cost-discount-field-wrap');
            var $input = $scope.find('.js-cost-discount-amount');
            $checkbox.prop('checked', isEnabled);
            $input.val(isEnabled && amount !== undefined && amount !== null && amount !== '' ? amount : '0.00');
            if (isEnabled) {
                $wrap.show();
            } else {
                $wrap.hide();
            }
        }

        function bindCostAssignmentDiscountToggle($scope) {
            $scope.find('.js-cost-discount-enabled').off('change.costDiscount').on('change.costDiscount', function() {
                var on = $(this).is(':checked');
                applyCostAssignmentDiscountUi($scope, on, on ? $scope.find('.js-cost-discount-amount').val() : '0.00');
            });
        }

        function applyDiscountRowToEmailHtml(html, discountAmount) {
            try {
                var discount = parseFloat(discountAmount);
                if (!html || isNaN(discount) || discount <= 0) {
                    return html;
                }
                if (html.indexOf('js-cost-discount-email-row') !== -1) {
                    return html;
                }
                var needles = ['GrandTotalFeesAndCosts', 'Total Applicable Costs', 'Total Applicable'];
                var lower = html.toLowerCase();
                var pos = -1;
                for (var i = 0; i < needles.length; i++) {
                    var found = lower.lastIndexOf(needles[i].toLowerCase());
                    if (found !== -1) {
                        pos = found;
                        break;
                    }
                }
                if (pos === -1) {
                    return html;
                }
                var totalTrStart = lastEmailTableRowOpen(html, pos);
                if (totalTrStart === -1) {
                    return html;
                }
                var formatted = discount.toFixed(2);
                var source = findPreviousEmailCostLineRow(html, totalTrStart);
                var row = source ? buildDiscountEmailRowFromSource(source.html, formatted) : defaultDiscountEmailRow(formatted);
                var insertAt = source ? source.end : totalTrStart;
                if (!isInsideOpenEmailTable(html, insertAt)) {
                    return html;
                }
                return html.substring(0, insertAt) + row + html.substring(insertAt);
            } catch (err) {
                return html;
            }
        }

        function isEmailTableRowOpenAt(lower, index) {
            if (lower.substr(index, 3) !== '<tr') {
                return false;
            }
            var next = lower.charAt(index + 3);
            return next === '' || next === '>' || next === '/' || /\s/.test(next);
        }

        function lastEmailTableRowOpen(html, beforePos) {
            var lower = html.toLowerCase();
            var pos = -1;
            var i = 0;
            while (i < beforePos) {
                var found = lower.indexOf('<tr', i);
                if (found === -1 || found >= beforePos) {
                    break;
                }
                if (isEmailTableRowOpenAt(lower, found)) {
                    pos = found;
                }
                i = found + 3;
            }
            return pos;
        }

        function matchingEmailTableRowEnd(html, trStart) {
            var lower = html.toLowerCase();
            var offset = trStart;
            var depth = 0;
            while (offset < lower.length) {
                var nextOpen = -1;
                var search = offset;
                while (search < lower.length) {
                    var found = lower.indexOf('<tr', search);
                    if (found === -1) {
                        break;
                    }
                    if (isEmailTableRowOpenAt(lower, found)) {
                        nextOpen = found;
                        break;
                    }
                    search = found + 3;
                }
                var nextClose = lower.indexOf('</tr>', offset);
                if (nextClose === -1) {
                    return -1;
                }
                if (nextOpen !== -1 && nextOpen <= nextClose) {
                    depth++;
                    offset = nextOpen + 3;
                    continue;
                }
                depth--;
                var end = nextClose + 5;
                if (depth <= 0) {
                    return end;
                }
                offset = end;
            }
            return -1;
        }

        function isInsideOpenEmailTable(html, insertAt) {
            return emailTableNestingDepth(html, insertAt) > 0;
        }

        function emailTableNestingDepth(html, pos) {
            var prefix = html.substring(0, Math.max(0, pos));
            var opens = prefix.match(/<table\b/gi);
            var closes = prefix.match(/<\/table/gi);
            return Math.max(0, (opens ? opens.length : 0) - (closes ? closes.length : 0));
        }

        function findPreviousEmailCostLineRow(html, totalTrStart) {
            var searchEnd = totalTrStart;
            for (var i = 0; i < 6; i++) {
                var trStart = lastEmailTableRowOpen(html, searchEnd);
                if (trStart === -1) {
                    return null;
                }
                var end = matchingEmailTableRowEnd(html, trStart);
                if (end === -1 || end > totalTrStart || emailTableNestingDepth(html, trStart) !== emailTableNestingDepth(html, totalTrStart)) {
                    searchEnd = trStart;
                    continue;
                }
                var row = html.substring(trStart, end);
                if (emailRowLooksLikeCostLine(row)) {
                    return { html: row, end: end };
                }
                searchEnd = trStart;
            }
            return null;
        }

        function emailRowLooksLikeCostLine(row) {
            var lower = row.toLowerCase();
            if (lower.indexOf('total applicable') !== -1 || row.indexOf('GrandTotalFeesAndCosts') !== -1) {
                return false;
            }
            return /\$\s*[\d,]+\.\d{2}|TotalEstimatedOthCosts|Other Costs|>\s*\d+\.\s*</i.test(row);
        }

        function buildDiscountEmailRowFromSource(sourceRow, formatted) {
            var tdRe = /<td\b([^>]*)>([\s\S]*?)<\/td>/gi;
            var cells = [];
            var match;
            while ((match = tdRe.exec(sourceRow)) !== null) {
                cells.push({ attrs: match[1], html: match[2] });
            }
            if (!cells.length) {
                return defaultDiscountEmailRow(formatted);
            }
            var openTr = '<tr class="js-cost-discount-email-row">';
            var trMatch = sourceRow.match(/<tr\b([^>]*)>/i);
            if (trMatch) {
                var attrs = trMatch[1] || '';
                if (/\bclass\s*=/i.test(attrs)) {
                    attrs = attrs.replace(/\bclass\s*=\s*(["'])(.*?)\1/i, 'class=$1$2 js-cost-discount-email-row$1');
                } else {
                    attrs += ' class="js-cost-discount-email-row"';
                }
                openTr = '<tr' + attrs + '>';
            }
            var amount = '-$' + formatted;
            if (cells.length >= 3) {
                return openTr + '<td' + cells[0].attrs + '>' + wrapEmailCostCellLikeSource(cells[0].html, '4.') + '</td><td' + cells[1].attrs + '>Discount</td><td' + cells[cells.length - 1].attrs + '>' + wrapEmailCostCellLikeSource(cells[cells.length - 1].html, amount) + '</td></tr>';
            }
            if (cells.length === 2) {
                return openTr + '<td' + cells[0].attrs + '>' + wrapEmailCostCellLikeSource(cells[0].html, '4.') + ' Discount</td><td' + cells[1].attrs + '>' + wrapEmailCostCellLikeSource(cells[1].html, amount) + '</td></tr>';
            }
            return defaultDiscountEmailRow(formatted);
        }

        function wrapEmailCostCellLikeSource(sourceInner, replacement) {
            var match = String(sourceInner || '').match(/<(strong|b)\b[^>]*>/i);
            if (match) {
                var tag = match[1].toLowerCase();
                return '<' + tag + '>' + replacement + '</' + tag + '>';
            }
            return '<strong>' + replacement + '</strong>';
        }

        function defaultDiscountEmailRow(formatted) {
            return '<tr class="js-cost-discount-email-row"><td><strong>4.</strong></td><td>Discount</td><td style="text-align:right;"><strong>-$' + formatted + '</strong></td></tr>';
        }
        window.applyDiscountRowToEmailHtml = applyDiscountRowToEmailHtml;

        function composeEmailBodyHasVisibleText(html) {
            return String(html || '')
                .replace(/<[^>]*>/g, ' ')
                .replace(/&nbsp;/gi, ' ')
                .replace(/\s+/g, ' ')
                .trim().length > 0;
        }

        function writeComposeEmailMessage(html) {
            var content = html || '';
            $('#compose_email_message').val(content);
            try {
                if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                    tinymce.get('compose_email_message').setContent(content);
                    return;
                }
                if (typeof setTinyMCEContent === 'function') {
                    setTinyMCEContent('compose_email_message', content);
                }
            } catch (err) {
                $('#compose_email_message').val(content);
            }
        }

        function setComposeEmailTemplateBody(html, fallbackHtml) {
            var primary = html || '';
            var fallback = fallbackHtml || primary;
            writeComposeEmailMessage(primary);
            var attempts = 0;
            var verify = function() {
                attempts++;
                var current = '';
                if (typeof tinymce !== 'undefined' && tinymce.get('compose_email_message')) {
                    current = tinymce.get('compose_email_message').getContent() || '';
                } else {
                    current = $('#compose_email_message').val() || '';
                    if (typeof setTinyMCEContent === 'function') {
                        setTinyMCEContent('compose_email_message', primary);
                    }
                }
                if (composeEmailBodyHasVisibleText(current) || attempts >= 4) {
                    if (!composeEmailBodyHasVisibleText(current) && fallback && fallback !== primary) {
                        writeComposeEmailMessage(fallback);
                    }
                    return;
                }
                setTimeout(verify, 150);
            };
            setTimeout(verify, 150);
        }



        // Initialize calculation handlers for Cost Assignment form
        // containerScope: optional selector to scope to modal (e.g. '#costAssignmentCreateFormModel')
        function initializeCostAssignmentCalculations(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);
            // Remove any existing handlers to prevent duplicates
            $scope.find('#Block_1_Ex_Tax, #Block_2_Ex_Tax, #Block_3_Ex_Tax').off('input change keyup');
            $scope.find('#Dept_Base_Application_Charge, #Dept_Non_Internet_Application_Charge, #Dept_Additional_Applicant_Charge_18_Plus, #Dept_Additional_Applicant_Charge_Under_18, #Dept_Subsequent_Temp_Application_Charge, #Dept_Second_VAC_Instalment_Charge_18_Plus, #Dept_Second_VAC_Instalment_Under_18, #Dept_Nomination_Application_Charge, #Dept_Sponsorship_Application_Charge, #saf_levy').off('input change keyup');
            $scope.find('#Dept_Base_Application_Charge_no_of_person, #Dept_Non_Internet_Application_Charge_no_of_person, #Dept_Additional_Applicant_Charge_18_Plus_no_of_person, #Dept_Additional_Applicant_Charge_Under_18_no_of_person, #Dept_Subsequent_Temp_Application_Charge_no_of_person, #Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person, #Dept_Second_VAC_Instalment_Under_18_no_of_person').off('input change keyup');
            $scope.find('#surcharge').off('change');
            bindCostAssignmentDiscountToggle($scope);

            // Calculate Total Block Fee when Block fields change
            $scope.find('#Block_1_Ex_Tax, #Block_2_Ex_Tax, #Block_3_Ex_Tax').on('input change keyup', function() {
                calculateTotalBlockFee(containerScope);
            });

            // Calculate Total DoHA Charges when Department fields change
            $scope.find('#Dept_Base_Application_Charge, #Dept_Non_Internet_Application_Charge, #Dept_Additional_Applicant_Charge_18_Plus, #Dept_Additional_Applicant_Charge_Under_18, #Dept_Subsequent_Temp_Application_Charge, #Dept_Second_VAC_Instalment_Charge_18_Plus, #Dept_Second_VAC_Instalment_Under_18, #Dept_Nomination_Application_Charge, #Dept_Sponsorship_Application_Charge, #saf_levy').on('input change keyup', function() {
                calculateTotalDoHACharges(containerScope);
            });

            // Recalculate when person counts change
            $scope.find('#Dept_Base_Application_Charge_no_of_person, #Dept_Non_Internet_Application_Charge_no_of_person, #Dept_Additional_Applicant_Charge_18_Plus_no_of_person, #Dept_Additional_Applicant_Charge_Under_18_no_of_person, #Dept_Subsequent_Temp_Application_Charge_no_of_person, #Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person, #Dept_Second_VAC_Instalment_Under_18_no_of_person').on('input change keyup', function() {
                calculateTotalDoHACharges(containerScope);
            });

            // Calculate Total DoHA Surcharges when surcharge selection changes
            $scope.find('#surcharge').on('change', function() {
                calculateTotalDoHASurcharges(containerScope);
            });

            // Initial calculations
            calculateTotalBlockFee(containerScope);
            calculateTotalDoHACharges(containerScope);
            calculateTotalDoHASurcharges(containerScope);
        }

        function getPersonCount(value, fallback) {
            var parsed = parseFloat(value);
            return isNaN(parsed) ? fallback : parsed;
        }

        /** SAF Levy input (company cost assignment only); empty or missing field => 0 */
        function parseSafLevyAmount($field) {
            if (!$field || !$field.length) {
                return 0;
            }
            var raw = ($field.val() || '').toString().replace(/,/g, '').trim();
            if (raw === '') {
                return 0;
            }
            var n = parseFloat(raw);
            return isNaN(n) ? 0 : n;
        }

        // Calculate Total Block Fee
        // containerScope: optional selector for modal scoping
        function calculateTotalBlockFee(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);
            var block1 = parseFloat($scope.find('#Block_1_Ex_Tax').val()) || 0;
            var block2 = parseFloat($scope.find('#Block_2_Ex_Tax').val()) || 0;
            var block3 = parseFloat($scope.find('#Block_3_Ex_Tax').val()) || 0;
            var total = block1 + block2 + block3;
            $scope.find('#TotalBLOCKFEE').val(total.toFixed(2));
        }

        // Calculate Total DoHA Charges
        // containerScope: optional selector for modal scoping
        function calculateTotalDoHACharges(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);
            var total = 0;

            // Dept Base Application Charge (with person multiplier)
            var baseCharge = parseFloat($scope.find('#Dept_Base_Application_Charge').val()) || 0;
            var basePersons = getPersonCount($scope.find('#Dept_Base_Application_Charge_no_of_person').val(), 1);
            total += baseCharge * basePersons;

            // Dept Non Internet Application Charge (with person multiplier)
            var nonInternetCharge = parseFloat($scope.find('#Dept_Non_Internet_Application_Charge').val()) || 0;
            var nonInternetPersons = getPersonCount($scope.find('#Dept_Non_Internet_Application_Charge_no_of_person').val(), 0);
            total += nonInternetCharge * nonInternetPersons;

            // Dept Additional Applicant Charge 18+ (with person multiplier)
            var add18PlusCharge = parseFloat($scope.find('#Dept_Additional_Applicant_Charge_18_Plus').val()) || 0;
            var add18PlusPersons = getPersonCount($scope.find('#Dept_Additional_Applicant_Charge_18_Plus_no_of_person').val(), 0);
            total += add18PlusCharge * add18PlusPersons;

            // Dept Additional Applicant Charge Under 18 (with person multiplier)
            var addUnder18Charge = parseFloat($scope.find('#Dept_Additional_Applicant_Charge_Under_18').val()) || 0;
            var addUnder18Persons = getPersonCount($scope.find('#Dept_Additional_Applicant_Charge_Under_18_no_of_person').val(), 0);
            total += addUnder18Charge * addUnder18Persons;

            // Dept Subsequent Temp Application Charge (with person multiplier)
            var subsequentCharge = parseFloat($scope.find('#Dept_Subsequent_Temp_Application_Charge').val()) || 0;
            var subsequentPersons = getPersonCount($scope.find('#Dept_Subsequent_Temp_Application_Charge_no_of_person').val(), 0);
            total += subsequentCharge * subsequentPersons;

            // Dept Second VAC Instalment 18+ (with person multiplier)
            var vac18PlusCharge = parseFloat($scope.find('#Dept_Second_VAC_Instalment_Charge_18_Plus').val()) || 0;
            var vac18PlusPersons = getPersonCount($scope.find('#Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person').val(), 0);
            total += vac18PlusCharge * vac18PlusPersons;

            // Dept Second VAC Instalment Under 18 (with person multiplier)
            var vacUnder18Charge = parseFloat($scope.find('#Dept_Second_VAC_Instalment_Under_18').val()) || 0;
            var vacUnder18Persons = getPersonCount($scope.find('#Dept_Second_VAC_Instalment_Under_18_no_of_person').val(), 0);
            total += vacUnder18Charge * vacUnder18Persons;

            // Dept Nomination Application Charge (no person multiplier)
            var nominationCharge = parseFloat($scope.find('#Dept_Nomination_Application_Charge').val()) || 0;
            total += nominationCharge;

            // Dept Sponsorship Application Charge (no person multiplier)
            var sponsorshipCharge = parseFloat($scope.find('#Dept_Sponsorship_Application_Charge').val()) || 0;
            total += sponsorshipCharge;

            total += parseSafLevyAmount($scope.find('#saf_levy'));

            $scope.find('#TotalDoHACharges').val(total.toFixed(2));
            
            // Recalculate surcharges when charges change
            calculateTotalDoHASurcharges(containerScope);
        }

        // Calculate Total DoHA Surcharges
        // containerScope: optional selector for modal scoping
        function calculateTotalDoHASurcharges(containerScope) {
            var $scope = (containerScope && $(containerScope).length) ? $(containerScope) : $(document);
            var surcharge = $scope.find('#surcharge').val();
            var totalSurcharges = 0;

            if (surcharge === 'Yes') {
                // Calculate surcharge based on applicable charges
                // Surcharge is 1.4% of the total DoHA charges
                var totalCharges = parseFloat($scope.find('#TotalDoHACharges').val()) || 0;
                
                // Surcharge rate: 1.4%
                var surchargeRate = 0.014; // 1.4%
                totalSurcharges = totalCharges * surchargeRate;
            }

            $scope.find('#TotalDoHASurcharges').val(totalSurcharges.toFixed(2));
        }

        // Make calculation functions globally available
        window.initializeCostAssignmentCalculations = initializeCostAssignmentCalculations;
        window.calculateTotalBlockFee = calculateTotalBlockFee;
        window.calculateTotalDoHACharges = calculateTotalDoHACharges;
        window.calculateTotalDoHASurcharges = calculateTotalDoHASurcharges;



        // Update checklist Cost Breakdown in place after amend (no full page reload).
        function formatChecklistMoney(amount) {
            var n = parseFloat(amount);
            if (isNaN(n)) {
                n = 0;
            }
            return '$' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function updateChecklistCostBreakdown(obj) {
            if (!obj || !obj.totals) {
                return false;
            }

            var $card = $();
            if (obj.form_id) {
                $card = $('.checklist-item-wrapper[data-id="' + obj.form_id + '"]');
            }
            if (!$card.length && obj.client_matter_id) {
                $card = $('.checklist-item-wrapper[data-client-matter-id="' + obj.client_matter_id + '"]');
            }
            if (!$card.length) {
                return false;
            }

            var totals = obj.totals;
            var blockFee = parseFloat(totals.block_fee) || 0;
            var deptCharges = parseFloat(totals.dept_charges) || 0;
            var surcharges = parseFloat(totals.surcharges) || 0;
            var additionalFee = parseFloat(totals.additional_fee_1) || 0;
            var discount = parseFloat(totals.discount) || 0;
            var totalCost = parseFloat(totals.total_cost);
            if (isNaN(totalCost)) {
                totalCost = blockFee + deptCharges + surcharges + additionalFee - discount;
            }

            $card.find('.js-cost-block-fee').text(formatChecklistMoney(blockFee));
            $card.find('.js-cost-dept').text(formatChecklistMoney(deptCharges));
            $card.find('.js-cost-total').text(formatChecklistMoney(totalCost));

            var $surchargeRow = $card.find('.js-cost-surcharge-row');
            if ($surchargeRow.length) {
                $card.find('.js-cost-surcharge').text(formatChecklistMoney(surcharges));
                if (surcharges > 0) {
                    $surchargeRow.show();
                } else {
                    $surchargeRow.hide();
                }
            }

            var $additionalRow = $card.find('.js-cost-additional-row');
            if ($additionalRow.length) {
                $card.find('.js-cost-additional').text(formatChecklistMoney(additionalFee));
                if (additionalFee > 0) {
                    $additionalRow.show();
                } else {
                    $additionalRow.hide();
                }
            }

            var $discountRow = $card.find('.js-cost-discount-row');
            if ($discountRow.length) {
                $card.find('.js-cost-discount').text('-' + formatChecklistMoney(discount));
                if (discount > 0) {
                    $discountRow.show();
                } else {
                    $discountRow.hide();
                }
            }

            var $badge = $card.find('.js-checklist-total-badge');
            if ($badge.length) {
                var badgeHtml = $badge.html() || '';
                if (/\$[\d,]+\.\d{2}/.test(badgeHtml)) {
                    $badge.html(badgeHtml.replace(/\$[\d,]+\.\d{2}/, formatChecklistMoney(totalCost)));
                } else {
                    $badge.text(formatChecklistMoney(totalCost));
                }
            }

            return true;
        }

        // Handle form submission via AJAX

        $(document).on('submit', '#costAssignmentform', function(e) {

            e.preventDefault();

            $.ajax({

                url: $(this).attr('action'),

                method: 'POST',

                data: $(this).serialize(),

                dataType: 'json',

                success: function(response) {

                    // Check if response is already an object or needs parsing

                    var obj = safeParseJsonResponse(response);

                    if (obj && obj.status) {

                        // If form was in the cost assignment modal (amend from checklist), close modal and stay on checklists tab
                        var $modal = $('#costAssignmentCreateFormModel');
                        if ($modal.length && $modal.hasClass('show')) {
                            $modal.modal('hide');
                            localStorage.setItem('activeTab', 'checklists');
                        } else {
                            // Form Generation tab removed; stay on checklists
                            localStorage.setItem('activeTab', 'checklists');
                        }

                        // Amend existing checklist card in place; reload only for create / missing card
                        if (obj.action === 'updated' && updateChecklistCostBreakdown(obj)) {
                            showClientDocFeedback('success', obj.message || 'Cost assignment updated successfully.');
                            return;
                        }

                        location.reload();

                    }

                },

                error: function(xhr, status, error) {

                    //console.log('Error:', xhr, status, error); // Debug log

                    $('.custom-error-msg').html('');

                    if (xhr.responseJSON && xhr.responseJSON.errors) {

                        let errors = xhr.responseJSON.errors;

                        for (let field in errors) {

                            $('.custom-error-msg').append('<p class="text-red-600">' + errors[field][0] + '</p>');

                        }

                    } else {

                        $('.custom-error-msg').append('<p class="text-red-600">An error occurred while submitting the form.</p>');

                    }

                }

            });

        });

        //Lead Section Start

            // Populate agent details when the modal opens

            $(document).delegate('.costAssignmentCreateFormLead', 'click', function() {

                $('#cost_assignment_lead_id').val(window.ClientDetailConfig.clientId);

                // Guard: native selects still work if Tom Select bridge is unavailable
                if (typeof $.fn.mmSelect === 'function') {
                    $('#sel_migration_agent_id_lead,#sel_person_responsible_id_lead,#sel_person_assisting_id_lead,#sel_office_id_lead,#sel_matter_id_lead').mmSelect({
                        dropdownParent: $('#costAssignmentCreateFormModelLead')
                    });
                }

                $('#costAssignmentCreateFormModelLead').modal('show');

            });



            $(document).delegate('#sel_matter_id_lead','change', function(){

                var client_matter_id = $(this).val();

                var client_id = window.ClientDetailConfig.clientId;

                if (client_id && client_matter_id) {

                    getCostAssignmentMigrationAgentDetailLead(client_id, client_matter_id);

                }

            });



            //Get Cost assignment Migration Agent Detail

            function getCostAssignmentMigrationAgentDetailLead(client_id,client_matter_id) {

                $.ajax({

                    type:'post',

                    url: window.ClientDetailConfig.urls.getCostAssignmentAgentLead,

                    sync:true,

                    data: {client_id:client_id,client_matter_id:client_matter_id},

                    success: function(response){

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        //Fetch matter related cost assignments

                        if(obj.cost_assignment_matterInfo){

                            $('#surcharge_lead').val(obj.cost_assignment_matterInfo.surcharge);

                            $('#Dept_Base_Application_Charge_lead').val(obj.cost_assignment_matterInfo.Dept_Base_Application_Charge);

                            $('#Dept_Base_Application_Charge_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Base_Application_Charge_no_of_person);



                            $('#Dept_Non_Internet_Application_Charge_lead').val(obj.cost_assignment_matterInfo.Dept_Non_Internet_Application_Charge);

                            $('#Dept_Non_Internet_Application_Charge_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Non_Internet_Application_Charge_no_of_person);



                            $('#Dept_Additional_Applicant_Charge_18_Plus_lead').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_18_Plus);

                            $('#Dept_Additional_Applicant_Charge_18_Plus_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_18_Plus_no_of_person);



                            $('#Dept_Additional_Applicant_Charge_Under_18_lead').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_Under_18);

                            $('#Dept_Additional_Applicant_Charge_Under_18_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Additional_Applicant_Charge_Under_18_no_of_person);



                            $('#Dept_Subsequent_Temp_Application_Charge_lead').val(obj.cost_assignment_matterInfo.Dept_Subsequent_Temp_Application_Charge);

                            $('#Dept_Subsequent_Temp_Application_Charge_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Subsequent_Temp_Application_Charge_no_of_person);



                            $('#Dept_Second_VAC_Instalment_Charge_18_Plus_lead').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Charge_18_Plus);

                            $('#Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person);



                            $('#Dept_Second_VAC_Instalment_Under_18_lead').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Under_18);

                            $('#Dept_Second_VAC_Instalment_Under_18_no_of_person_lead').val(obj.cost_assignment_matterInfo.Dept_Second_VAC_Instalment_Under_18_no_of_person);



                            $('#Dept_Nomination_Application_Charge_lead').val(obj.cost_assignment_matterInfo.Dept_Nomination_Application_Charge);

                            $('#Dept_Sponsorship_Application_Charge_lead').val(obj.cost_assignment_matterInfo.Dept_Sponsorship_Application_Charge);

                            $('#saf_levy_lead').val(obj.cost_assignment_matterInfo.saf_levy != null ? obj.cost_assignment_matterInfo.saf_levy : '');



                            $('#TotalDoHACharges_lead').val(obj.cost_assignment_matterInfo.TotalDoHACharges);

                            $('#TotalDoHASurcharges_lead').val(obj.cost_assignment_matterInfo.TotalDoHASurcharges);



                            $('#Block_1_Ex_Tax_lead').val(obj.cost_assignment_matterInfo.Block_1_Ex_Tax);

                            $('#Block_2_Ex_Tax_lead').val(obj.cost_assignment_matterInfo.Block_2_Ex_Tax);

                            $('#Block_3_Ex_Tax_lead').val(obj.cost_assignment_matterInfo.Block_3_Ex_Tax);



                            $('#additional_fee_1_lead').val(obj.cost_assignment_matterInfo.additional_fee_1);

                            $('#TotalBLOCKFEE_lead').val(obj.cost_assignment_matterInfo.TotalBLOCKFEE);

                            applyCostAssignmentDiscountUi(
                                $('#costAssignmentCreateFormModelLead'),
                                isCostAssignmentDiscountEnabled(obj.cost_assignment_matterInfo.discount_enabled),
                                obj.cost_assignment_matterInfo.discount
                            );

                        }

                        else {

                            $('#surcharge_lead').val(obj.matterInfo.surcharge);

                            $('#Dept_Base_Application_Charge_lead').val(obj.matterInfo.Dept_Base_Application_Charge);
                            $('#Dept_Base_Application_Charge_no_of_person_lead').val(1);

                            $('#Dept_Non_Internet_Application_Charge_lead').val(obj.matterInfo.Dept_Non_Internet_Application_Charge);
                            $('#Dept_Non_Internet_Application_Charge_no_of_person_lead').val(0);

                            $('#Dept_Additional_Applicant_Charge_18_Plus_lead').val(obj.matterInfo.Dept_Additional_Applicant_Charge_18_Plus);
                            $('#Dept_Additional_Applicant_Charge_18_Plus_no_of_person_lead').val(0);

                            $('#Dept_Additional_Applicant_Charge_Under_18_lead').val(obj.matterInfo.Dept_Additional_Applicant_Charge_Under_18);
                            $('#Dept_Additional_Applicant_Charge_Under_18_no_of_person_lead').val(0);

                            $('#Dept_Subsequent_Temp_Application_Charge_lead').val(obj.matterInfo.Dept_Subsequent_Temp_Application_Charge);
                            $('#Dept_Subsequent_Temp_Application_Charge_no_of_person_lead').val(0);

                            $('#Dept_Second_VAC_Instalment_Charge_18_Plus_lead').val(obj.matterInfo.Dept_Second_VAC_Instalment_Charge_18_Plus);
                            $('#Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person_lead').val(0);

                            $('#Dept_Second_VAC_Instalment_Under_18_lead').val(obj.matterInfo.Dept_Second_VAC_Instalment_Under_18);
                            $('#Dept_Second_VAC_Instalment_Under_18_no_of_person_lead').val(0);

                            $('#Dept_Nomination_Application_Charge_lead').val(obj.matterInfo.Dept_Nomination_Application_Charge);

                            $('#Dept_Sponsorship_Application_Charge_lead').val(obj.matterInfo.Dept_Sponsorship_Application_Charge);

                            $('#saf_levy_lead').val('');



                            $('#Block_1_Ex_Tax_lead').val(obj.matterInfo.Block_1_Ex_Tax);

                            $('#Block_2_Ex_Tax_lead').val(obj.matterInfo.Block_2_Ex_Tax);

                            $('#Block_3_Ex_Tax_lead').val(obj.matterInfo.Block_3_Ex_Tax);



                            $('#additional_fee_1_lead').val(obj.matterInfo.additional_fee_1);

                            $('#TotalBLOCKFEE_lead').val(obj.matterInfo.TotalBLOCKFEE);

                            $('#TotalDoHACharges_lead').val(obj.matterInfo.TotalDoHACharges);

                            $('#TotalDoHASurcharges_lead').val(obj.matterInfo.TotalDoHASurcharges);

                            applyCostAssignmentDiscountUi($('#costAssignmentCreateFormModelLead'), false, '0.00');

                        }

                        // Initialize calculation handlers for Lead form (Total Block Fee, Total DoHA Charges, Total DoHA Surcharges)
                        if (typeof window.initializeCostAssignmentCalculationsLead === 'function') {
                            window.initializeCostAssignmentCalculationsLead();
                        } else {
                            calculateTotalBlockFeeLead();
                            calculateTotalDoHAChargesLead();
                            calculateTotalDoHASurchargesLead();
                        }

                    }

                });

            }

        // Lead form calculation functions (uses _lead suffix IDs)
        function initializeCostAssignmentCalculationsLead() {
            $('#Block_1_Ex_Tax_lead, #Block_2_Ex_Tax_lead, #Block_3_Ex_Tax_lead').off('input change keyup');
            $('#Dept_Base_Application_Charge_lead, #Dept_Non_Internet_Application_Charge_lead, #Dept_Additional_Applicant_Charge_18_Plus_lead, #Dept_Additional_Applicant_Charge_Under_18_lead, #Dept_Subsequent_Temp_Application_Charge_lead, #Dept_Second_VAC_Instalment_Charge_18_Plus_lead, #Dept_Second_VAC_Instalment_Under_18_lead, #Dept_Nomination_Application_Charge_lead, #Dept_Sponsorship_Application_Charge_lead, #saf_levy_lead').off('input change keyup');
            $('#Dept_Base_Application_Charge_no_of_person_lead, #Dept_Non_Internet_Application_Charge_no_of_person_lead, #Dept_Additional_Applicant_Charge_18_Plus_no_of_person_lead, #Dept_Additional_Applicant_Charge_Under_18_no_of_person_lead, #Dept_Subsequent_Temp_Application_Charge_no_of_person_lead, #Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person_lead, #Dept_Second_VAC_Instalment_Under_18_no_of_person_lead').off('input change keyup');
            $('#surcharge_lead').off('change');
            bindCostAssignmentDiscountToggle($('#costAssignmentCreateFormModelLead'));

            $('#Block_1_Ex_Tax_lead, #Block_2_Ex_Tax_lead, #Block_3_Ex_Tax_lead').on('input change keyup', function() { calculateTotalBlockFeeLead(); });
            $('#Dept_Base_Application_Charge_lead, #Dept_Non_Internet_Application_Charge_lead, #Dept_Additional_Applicant_Charge_18_Plus_lead, #Dept_Additional_Applicant_Charge_Under_18_lead, #Dept_Subsequent_Temp_Application_Charge_lead, #Dept_Second_VAC_Instalment_Charge_18_Plus_lead, #Dept_Second_VAC_Instalment_Under_18_lead, #Dept_Nomination_Application_Charge_lead, #Dept_Sponsorship_Application_Charge_lead, #saf_levy_lead').on('input change keyup', function() { calculateTotalDoHAChargesLead(); });
            $('#Dept_Base_Application_Charge_no_of_person_lead, #Dept_Non_Internet_Application_Charge_no_of_person_lead, #Dept_Additional_Applicant_Charge_18_Plus_no_of_person_lead, #Dept_Additional_Applicant_Charge_Under_18_no_of_person_lead, #Dept_Subsequent_Temp_Application_Charge_no_of_person_lead, #Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person_lead, #Dept_Second_VAC_Instalment_Under_18_no_of_person_lead').on('input change keyup', function() { calculateTotalDoHAChargesLead(); });
            $('#surcharge_lead').on('change', function() { calculateTotalDoHASurchargesLead(); });

            calculateTotalBlockFeeLead();
            calculateTotalDoHAChargesLead();
            calculateTotalDoHASurchargesLead();
        }
        function calculateTotalBlockFeeLead() {
            var block1 = parseFloat($('#Block_1_Ex_Tax_lead').val()) || 0;
            var block2 = parseFloat($('#Block_2_Ex_Tax_lead').val()) || 0;
            var block3 = parseFloat($('#Block_3_Ex_Tax_lead').val()) || 0;
            var total = block1 + block2 + block3;
            $('#TotalBLOCKFEE_lead').val(total.toFixed(2));
        }
        function calculateTotalDoHAChargesLead() {
            var total = 0;
            var baseCharge = parseFloat($('#Dept_Base_Application_Charge_lead').val()) || 0;
            var basePersons = getPersonCount($('#Dept_Base_Application_Charge_no_of_person_lead').val(), 1);
            total += baseCharge * basePersons;
            var nonInternetCharge = parseFloat($('#Dept_Non_Internet_Application_Charge_lead').val()) || 0;
            var nonInternetPersons = getPersonCount($('#Dept_Non_Internet_Application_Charge_no_of_person_lead').val(), 0);
            total += nonInternetCharge * nonInternetPersons;
            var add18PlusCharge = parseFloat($('#Dept_Additional_Applicant_Charge_18_Plus_lead').val()) || 0;
            var add18PlusPersons = getPersonCount($('#Dept_Additional_Applicant_Charge_18_Plus_no_of_person_lead').val(), 0);
            total += add18PlusCharge * add18PlusPersons;
            var addUnder18Charge = parseFloat($('#Dept_Additional_Applicant_Charge_Under_18_lead').val()) || 0;
            var addUnder18Persons = getPersonCount($('#Dept_Additional_Applicant_Charge_Under_18_no_of_person_lead').val(), 0);
            total += addUnder18Charge * addUnder18Persons;
            var subsequentCharge = parseFloat($('#Dept_Subsequent_Temp_Application_Charge_lead').val()) || 0;
            var subsequentPersons = getPersonCount($('#Dept_Subsequent_Temp_Application_Charge_no_of_person_lead').val(), 0);
            total += subsequentCharge * subsequentPersons;
            var vac18PlusCharge = parseFloat($('#Dept_Second_VAC_Instalment_Charge_18_Plus_lead').val()) || 0;
            var vac18PlusPersons = getPersonCount($('#Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person_lead').val(), 0);
            total += vac18PlusCharge * vac18PlusPersons;
            var vacUnder18Charge = parseFloat($('#Dept_Second_VAC_Instalment_Under_18_lead').val()) || 0;
            var vacUnder18Persons = getPersonCount($('#Dept_Second_VAC_Instalment_Under_18_no_of_person_lead').val(), 0);
            total += vacUnder18Charge * vacUnder18Persons;
            total += parseFloat($('#Dept_Nomination_Application_Charge_lead').val()) || 0;
            total += parseFloat($('#Dept_Sponsorship_Application_Charge_lead').val()) || 0;
            total += parseSafLevyAmount($('#saf_levy_lead'));
            $('#TotalDoHACharges_lead').val(total.toFixed(2));
            calculateTotalDoHASurchargesLead();
        }
        function calculateTotalDoHASurchargesLead() {
            var surcharge = $('#surcharge_lead').val();
            var totalSurcharges = 0;
            if (surcharge === 'Yes') {
                var totalCharges = parseFloat($('#TotalDoHACharges_lead').val()) || 0;
                totalSurcharges = totalCharges * 0.014;
            }
            $('#TotalDoHASurcharges_lead').val(totalSurcharges.toFixed(2));
        }
        window.initializeCostAssignmentCalculationsLead = initializeCostAssignmentCalculationsLead;
        window.calculateTotalBlockFeeLead = calculateTotalBlockFeeLead;
        window.calculateTotalDoHAChargesLead = calculateTotalDoHAChargesLead;
        window.calculateTotalDoHASurchargesLead = calculateTotalDoHASurchargesLead;

        //Lead Section End



        //Open Agreement model window

        $(document).delegate('.finalizeAgreementConvertToPdf', 'click', function() {

            var $btn = $(this);

            // Prefer checklist row matter (avoids sidebar matter sync, which reloads the page when URL matter differs).
            var hidden_client_matter_id_assignment = $btn.data('client-matter-id');
            if (!hidden_client_matter_id_assignment) {
                if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                    hidden_client_matter_id_assignment = $('.general_matter_checkbox_client_detail').val();
                } else {
                    hidden_client_matter_id_assignment = $('#sel_matter_id_client_detail').val();
                }
            }

            $('#agreemnt_clientmatterid').val(hidden_client_matter_id_assignment);

            $('#agreementModal').modal('show');

        });



        // Prevent form from submitting (no Upload button; upload is triggered on file select/drop)
        $(document).on('submit', '#agreementUploadForm', function(e) { e.preventDefault(); });

        // Agreement modal: single upload function used for auto-upload on drop or browse
        function doAgreementUpload() {
            var form = document.getElementById('agreementUploadForm');
            if (!form || !form.agreement_doc || !form.agreement_doc.files || !form.agreement_doc.files.length) return;
            var formData = new FormData(form);
            $('.popuploader').show();
            $('#agreementUploadError').hide();
            $.ajax({
                url: window.ClientDetailConfig.urls.uploadAgreement,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {'X-CSRF-TOKEN': window.ClientDetailConfig.csrfToken},
                success: function(response) {
                    $('.popuploader').hide();
                    if (response.status) {
                        $('#agreementModal').modal('hide');
                        if (response.document_id) {
                            $(document).trigger('openSignaturePlacementModal', { documentId: response.document_id });
                        } else {
                            localStorage.setItem('activeTab', 'checklists');
                            setTimeout(function() { location.reload(); }, 1000);
                        }
                    } else {
                        $('#agreementUploadError').text(response.message || 'Upload failed.').show();
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'An error occurred while uploading the agreement.';
                    $('#agreementUploadError').text(msg).show();
                }
            });
        }

        // Agreement modal: drag-and-drop and click-to-browse; auto-upload on file set
        (function() {
            var $form = $('#agreementUploadForm');
            var $input = $form.find('input[name="agreement_doc"]');
            var $dropZone = $('#agreementDropZone');
            var $fileName = $('#agreementFileName');
            var $err = $('#agreementUploadError');

            function setAgreementFile(file) {
                if (!file) return;
                var name = file.name || 'File chosen';
                var isPdf = file.type === 'application/pdf' || (name.toLowerCase().indexOf('.pdf') === name.length - 4);
                if (!isPdf) {
                    $err.text('Please upload a PDF file.').show();
                    return;
                }
                $err.hide();
                var dt = new DataTransfer();
                dt.items.add(file);
                $input[0].files = dt.files;
                $fileName.text(name);
                $dropZone.addClass('agreement-drop-zone--over');
                setTimeout(function() { $dropZone.removeClass('agreement-drop-zone--over'); }, 300);
                doAgreementUpload();
            }

            function clearAgreementUploadState() {
                $input.val('');
                $fileName.text('');
                $err.hide();
                $dropZone.removeClass('agreement-drop-zone--over');
            }

            $dropZone.on('click', function(e) {
                if ($(e.target).closest('.agreement-file-input').length) return;
                e.preventDefault();
                $input[0].click();
            });
            $dropZone.on('keydown', function(e) { if (e.which === 13 || e.which === 32) { e.preventDefault(); $input[0].click(); } });

            $dropZone.on('dragenter', function(e) { e.preventDefault(); e.stopPropagation(); $dropZone.addClass('agreement-drop-zone--over'); });
            $dropZone.on('dragover', function(e) { e.preventDefault(); e.stopPropagation(); });
            $dropZone.on('dragleave', function(e) {
                e.preventDefault();
                if (!$dropZone[0].contains(e.relatedTarget)) $dropZone.removeClass('agreement-drop-zone--over');
            });
            $dropZone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $dropZone.removeClass('agreement-drop-zone--over');
                var file = (e.originalEvent && e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files) ? e.originalEvent.dataTransfer.files[0] : null;
                if (file) setAgreementFile(file);
            });

            $(document).on('change', '#agreementUploadForm input[name="agreement_doc"]', function() {
                var f = this.files && this.files[0];
                if (f) {
                    var isPdf = f.type === 'application/pdf' || (f.name && f.name.toLowerCase().indexOf('.pdf') === f.name.length - 4);
                    if (!isPdf) {
                        $err.text('Please upload a PDF file.').show();
                        return;
                    }
                    $fileName.text(f.name);
                    $err.hide();
                    doAgreementUpload();
                } else {
                    $fileName.text('');
                }
            });

            $('#agreementModal').on('hidden.bs.modal', function() { clearAgreementUploadState(); });
        })();

        $(document).delegate('.uploadSentAndFetchMail','click', function(){

            $('#maclient_id_fetch_sent').val(window.ClientDetailConfig.clientId);

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#upload_sent_mail_client_matter_id').val(hidden_client_matter_id);

            $('#uploadSentAndFetchMailModel').modal('show');

        });



        $(document).delegate('.addnewprevvisa','click', function(){

            var $clone = $('.multiplevisa:eq(0)').clone(true,true);



            $clone.find('.lastfiledcol').after('<div class="col-md-4"><a href="javascript:;" class="removenewprevvisa btn btn-danger btn-sm">Remove</a></div>');

            $clone.find("input:text").val("");

            $clone.find("input.visadatesse").val("");

            $('.multiplevisa:last').after($clone);

        });



        $('#note_deadline_checkbox').on('click', function() {

            if ($(this).is(':checked')) {

                $('#note_deadline').prop('disabled', false);

                $('#note_deadline_checkbox').val(1);

            } else {

                $('#note_deadline').prop('disabled', true);

                $('#note_deadline_checkbox').val(0);

            }

        });



        $(document).on('change', '#noteTypeSimple, #noteTypeEnhanced', function() {

            var selectedValue = $(this).val();

            var $form = $(this).closest('form');

            var additionalFields = $form.find('.additional-fields-container').first();



            // Clear any existing fields

            additionalFields.html("");



            if(selectedValue === "Call") {

                additionalFields.append(`

                    <div class="form-group" style="margin-top:10px;">

                        <label for="mobileNumber">Mobile Number:</label>

                        <select name="mobileNumber" id="mobileNumber" class="form-control" data-valid="required"></select>

                        <span id="mobileNumberError" class="text-danger"></span>

                    </div>

                `);



                //Fetch all contact list of any client at create note popup

                var client_id = $form.find('input[name="client_id"]').val() || $('#client_id').val() || (window.ClientDetailConfig && window.ClientDetailConfig.clientId);

                $('.popuploader').show();

                $.ajax({

                    url: window.ClientDetailConfig.urls.fetchClientContactNo,

                    method: "POST",

                    data: {client_id:client_id},

                    dataType: 'json',

                    success: function(response) {

                        $('.popuploader').hide();

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        var contactlist = '<option value="">Select Contact</option>';

                        $.each(obj.clientContacts, function(index, subArray) {

                            contactlist += '<option value="'+subArray.phone+'">'+subArray.phone+'</option>';

                        });

                        $('#mobileNumber').append(contactlist);

                    },

                    error: function() {
                        $('.popuploader').hide();
                    }

                });

            }

        });





        var activeLink = $('.nav-link.active');

        if (activeLink.length > 0) {

            var href = activeLink.attr('href');

            if(href == '#activities' ) {

                $('.filter_btn').css('display','inline-block');

                $('.filter_panel').css('display','none');

            } else {

                $('.filter_btn,.filter_panel').css('display','none');

            }

        } else {

            $('.filter_btn,.filter_panel').css('display','none');

        }





        $(document).delegate('.nav-link','click', function(){

            var activeLink = $('.nav-link.active');

            if (activeLink.length > 0) {

                var href = activeLink.attr('href');

                if(href == '#activities' ) {

                    $('.filter_btn').css('display','inline-block');

                    $('.filter_panel').css('display','none');

                } else {

                    $('.filter_btn,.filter_panel').css('display','none');

                }

            } else {

                $('.filter_btn,.filter_panel').css('display','none');

            }

        });



        /*$(document).delegate('.btn-assignuser','click', function(){

            var note_description = $('#note_description').val();

            // Remove <p> tags using regex

            var cleanedText = note_description.replace(/<\/?p>/g, '');

            // cleanedText = cleanedText.replace(/<\/?p>/g, '');

            $('#assignnote').val(cleanedText);

        });*/



        $(document).delegate('.removenewprevvisa','click', function(){

            $(this).parent().parent().parent().remove();

        });



        // assignStaff function is now handled in addclientmodal.blade.php to avoid conflicts

        $(document).on('click', '#assignStaff', function(e) {

            e.preventDefault();

            e.stopPropagation();

            

            $(".popuploader").show();

            let flag = true;

            let error = "";

            $(".custom-error").remove();

            var isFollowupMode = $('#create_action_popup').attr('data-popup-mode') === 'followup';



            // Get all checked assignee IDs from checkboxes

            let selectedAssignees = [];

            $('.checkbox-item:checked').each(function() {

                selectedAssignees.push($(this).val());

            });

            

            var selectedValues = selectedAssignees; // Use the checked values

            

            // Validation - Check if at least one assignee is selected

            if (selectedAssignees.length === 0) {

                $('.popuploader').hide();

                error = "At least one assignee must be selected.";

                $('#dropdownMenuButton').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                flag = false;

            }

            

            // Check if assignnote field is empty (handle both regular textarea and TinyMCE)

            var assignnoteValue = '';

            if (isEditorInitialized('#assignnote')) {

                assignnoteValue = getEditorContent('#assignnote');

            } else {

                assignnoteValue = $('#assignnote').val();

            }

            

            if (assignnoteValue.trim() === '') {

                $('.popuploader').hide();

                error = "Note field is required.";

                $('#assignnote').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                flag = false;

            }

            

            if (!isFollowupMode && $('#task_group').val() === '') {

                $('.popuploader').hide();

                error = "Group field is required.";

                $('#task_group').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                flag = false;

            }



            if (isFollowupMode) {

                var followupDate = $('#create_action_popup #popoverdatetime').val();

                var followupToday = window.followupLocalDateString();

                if (!followupDate || followupDate < followupToday) {

                    $('.popuploader').hide();

                    error = "Followup date cannot be in the past.";

                    $('#create_action_popup #popoverdatetime').after("<span class='custom-error' role='alert' style='color: red; font-size: 12px; display: block; margin-top: 5px;'>" + error + "</span>");

                    flag = false;

                }

            }



            if (flag) {

                $.ajax({

                    type: 'POST',

                    url: window.ClientDetailConfig.urls.followupStore,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },

                    data: {

                        note_type: 'follow_up',

                        description: assignnoteValue,

                        client_id: $('#assign_client_id').val(),

                        followup_datetime: $('#popoverdatetime').val(),

                        rem_cat: selectedValues,

                        task_group: isFollowupMode ? 'Follow Up' : $('#task_group option:selected').val(),

                        note_deadline_checkbox: isFollowupMode ? '' : $('#note_deadline_checkbox').val(),

                        note_deadline: isFollowupMode ? '' : $('#note_deadline').val()

                    },

                    success: function(response) {

                        $('.popuploader').hide();

                        $('#create_action_popup').modal('hide');

                        var obj = safeParseJsonResponse(response);
                        if (!obj) return;
                        if (obj.success) {

                            var successMessage = isFollowupMode ? 'Followup created successfully.' : 'Action assigned successfully.';
                            if (typeof mmShowToastMsg === 'function') {
                                mmShowToastMsg('success', successMessage, 'Success');
                            } else if (typeof iziToast !== 'undefined' && typeof iziToast.success === 'function') {
                                iziToast.success({ title: 'Success', message: successMessage, position: 'topRight', timeout: 3000 });
                            } else if (typeof showClientDocFeedback === 'function') {
                                showClientDocFeedback('success', successMessage);
                            }

                            $("[data-role=popover]").each(function() {

                                (($(this).popover('hide').data('bs.popover') || {}).inState || {}).click = false; // fix for BS 3.3.6

                            });

                            

                            // Reset form fields after successful submission

                            $('#assignnote').val('');

                            $('#task_group').val('');

                            $('#popoverdatetime').val((new Date().toISOString().split('T')[0]));

                            $('#note_deadline').val((new Date().toISOString().split('T')[0]));

                            $('#note_deadline_checkbox').prop('checked', false);

                            $('#note_deadline').prop('disabled', true);

                            $('.checkbox-item').prop('checked', false);

                            if (typeof window.applyAssignStaffPopupMode === 'function') {

                                window.applyAssignStaffPopupMode('action');

                            }

                            

                            // Reset assignee selection

                            if (typeof updateSelectedStaff === 'function') {

                                updateSelectedStaff();

                            } else if (typeof updateSelectedUsers === 'function') {

                                updateSelectedUsers();

                            }

                            if (typeof updateHiddenSelect === 'function') {

                                updateHiddenSelect();

                            }

                            
                            
                            // Call the functions to refresh the data
                            // Add a small delay to ensure database transaction is committed
                            setTimeout(function() {
                                if (typeof window.loadActivities === 'function') {
                                    if (window.ActivityFeedState && window.ActivityFeedState.loading) {
                                        window.ActivityFeedState.pendingReset = true;
                                    } else {
                                        window.loadActivities({ reset: true });
                                    }
                                } else if (typeof getallactivities === 'function') {
                                    try {
                                        getallactivities();
                                    } catch (e) {
                                        console.error('Error refreshing Activity Feed:', e);
                                    }
                                }

                                // Refresh notes list
                                if (typeof getallnotes === 'function') {
                                    try {
                                        getallnotes();
                                    } catch (e) {
                                        console.error('Error refreshing notes:', e);
                                    }
                                }
                            }, 400);

                        } else {

                            // Handle failure

                            alert('Error: ' + (obj.message || 'Something went wrong'));

                        }

                    },

                    error: function(xhr, status, error) {

                        $('.popuploader').hide();

                        console.error('Ajax error:', error);

                        alert('Error: ' + error);

                    }

                });

            } else {

                $('.popuploader').hide();

            }

        });



        function getallactivities(){
            window.getallactivities = getallactivities;

            if (typeof window.loadActivities === 'function') {
                window.loadActivities({ reset: true });
                $('.popuploader').hide();
                return;
            }

            $.ajax({

                url: site_url+'/get-activities',

                type:'GET',

                dataType:'json', // Fixed: changed from dataType to dataType (case-sensitive)

                data:{id:window.ClientDetailConfig.clientId, page: 1, per_page: 40},

                success: function(responses){
                    try {
                        var ress = safeParseJsonResponse(responses);
                        if (!ress) {
                            $('.popuploader').hide();
                            return;
                        }

                        if (!ress.data || !$.isArray(ress.data)) {
                            ress.data = [];
                        }

                    var html = '';



                    $.each(ress.data, function (k, v) {

                        var activityType = v.activity_type ?? '';
                        var noteSubtypeClass = '';
                        var subjectIcon;
                        var iconClass = '';
                        var subject = v.subject ?? '';
                        var subjectLower = subject.toLowerCase();
                        var rawMessage = v.message ?? '';
                        var isAppointmentActivity = String(rawMessage).indexOf('appointment-activity-detail') !== -1;

                        if (activityType === 'sms') {
                            subjectIcon = crmI('fas fa-sms');
                            iconClass = 'feed-icon-sms';
                        } else if (activityType === 'note') {
                            var noteIcon = 'fa-sticky-note';
                            if (subjectLower.indexOf('call') !== -1) { noteIcon = 'fa-phone'; noteSubtypeClass = ' activity-type-note-call'; }
                            else if (subjectLower.indexOf('email') !== -1) { noteIcon = 'fa-envelope'; noteSubtypeClass = ' activity-type-note-email'; }
                            else if (subjectLower.indexOf('in-person') !== -1) { noteIcon = 'fa-user-friends'; noteSubtypeClass = ' activity-type-note-in-person'; }
                            else if (subjectLower.indexOf('attention') !== -1) { noteIcon = 'fa-exclamation-triangle'; noteSubtypeClass = ' activity-type-note-attention'; }
                            else if (subjectLower.indexOf('others') !== -1) { noteIcon = 'fa-ellipsis-h'; noteSubtypeClass = ' activity-type-note-others'; }
                            subjectIcon = crmI('fas ' + noteIcon);
                            iconClass = 'feed-icon-note';
                        } else if (activityType === 'activity') {
                            if (isAppointmentActivity) {
                                subjectIcon = crmI('fas fa-calendar-check');
                                iconClass = 'feed-icon-appointment';
                            } else {
                                subjectIcon = crmI('fas fa-bolt');
                                iconClass = 'feed-icon-activity';
                            }
                        } else if (activityType === 'stage') {
                            subjectIcon = crmI('fas fa-route');
                            iconClass = 'feed-icon-stage';
                        } else if (activityType === 'financial') {
                            subjectIcon = crmI('fas fa-dollar-sign');
                            iconClass = 'feed-icon-financial';
                        } else if (activityType === 'email') {
                            subjectIcon = crmI('fas fa-envelope');
                            iconClass = 'feed-icon-email';
                        } else if (activityType === 'signature') {
                            subjectIcon = crmI('fas fa-file-signature');
                            iconClass = 'feed-icon-signature';
                        } else if (activityType === 'document') {
                            subjectIcon = crmI('fas fa-file-alt');
                            iconClass = '';
                        } else if (/uploaded email:/i.test(subjectLower)) {
                            subjectIcon = crmI('fas fa-envelope');
                            iconClass = 'feed-icon-email';
                        } else if (subjectLower.includes('invoice') || subjectLower.includes('receipt') || subjectLower.includes('ledger') || subjectLower.includes('payment') || subjectLower.includes('account')) {
                            subjectIcon = crmI('fas fa-dollar-sign');
                            iconClass = 'feed-icon-financial';
                        } else if (subjectLower.includes('document') && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
                            subjectIcon = crmI('fas fa-file-alt');
                            iconClass = '';
                        } else if (subjectLower.includes('document')) {
                            subjectIcon = crmI('fas fa-file-alt');
                            iconClass = '';
                        } else {
                            subjectIcon = crmI('fas fa-sticky-note');
                            iconClass = '';
                        }

                        var description = v.message ?? '';
                        var taskGroup = v.task_group ?? '';
                        var followupDate = v.followup_date ?? '';
                        var date = v.date ?? '';
                        var fullName = v.name ?? '';
                        var activityTypeClass = activityType ? 'activity-type-' + activityType : '';
                        if (!activityTypeClass) {
                            if (/uploaded email:/i.test(subjectLower)) {
                                activityTypeClass = 'activity-type-email';
                            } else if (subjectLower.includes('invoice') || subjectLower.includes('receipt') || subjectLower.includes('ledger') || subjectLower.includes('payment') || subjectLower.includes('account')) {
                                activityTypeClass = 'activity-type-financial';
                            } else if (subjectLower.includes('document') && !/(receipt document|journal receipt document|client receipt document|office receipt document)/i.test(subjectLower)) {
                                activityTypeClass = 'activity-type-document';
                            }
                        }
                        var headline = v.subject_without_staff_prefix === true ? subject : (fullName + ' ' + subject);
                        var feedItemClass = activityType === 'stage' ? 'feed-item--stage' : 'feed-item--email';
                        var createdAtYmd = v.created_at_ymd || '';
                        var appointmentFeedClass = isAppointmentActivity ? ' feed-item--appointment' : '';

                        var innerContent;
                        if (activityType === 'stage') {
                            innerContent =
                                '<div class="feed-item-stage">' +
                                    '<div class="feed-item-stage-header">' +
                                        '<span class="feed-item-staff">' + fullName + '</span>' +
                                        '<span class="feed-timestamp">' + date + '</span>' +
                                    '</div>' +
                                    '<div class="feed-item-stage-body">' + (v.message ? v.message : '') + '</div>' +
                                '</div>';
                        } else {
                            innerContent =
                                '<p><strong>' + headline + '</strong></p>' +
                                (description !== '' ? '<p>' + description + '</p>' : '') +
                                (taskGroup !== '' ? '<p>' + taskGroup + '</p>' : '') +
                                (followupDate !== '' ? '<p>' + followupDate + '</p>' : '') +
                                '<span class="feed-timestamp">' + date + '</span>';
                        }

                        html += `
                            <li class="feed-item ${feedItemClass} activity ${activityTypeClass}${noteSubtypeClass}${appointmentFeedClass}" id="activity_${v.activity_id}" data-created-at="${createdAtYmd}">
                                <span class="feed-icon ${iconClass}">
                                    ${subjectIcon}
                                </span>
                                <div class="feed-content">
                                    ${innerContent}
                                </div>
                            </li>
                        `;

                    });



                    $('.feed-list').html(html);

                    //$('.activities').html(html);

                    $('.popuploader').hide();

                    

                    // Adjust Activity Feed height after content update

                    adjustActivityFeedHeight();

                    if (window.ActivityFeed && typeof window.ActivityFeed.reapplyCurrentFilter === 'function') {
                        window.ActivityFeed.reapplyCurrentFilter();
                    }
                    if (window.ActivityFeed && typeof window.ActivityFeed.enhanceAppointmentActivityRows === 'function') {
                        window.ActivityFeed.enhanceAppointmentActivityRows();
                    }

                    } catch (error) {
                        console.error('Error processing activities:', error);
                        $('.popuploader').hide();
                    }

                },

                error: function() {
                    $('.popuploader').hide();
                }

            });

        }



        // .publishdoc, .unpublishdoc, #confirmpublishdocModal .acceptpublishdoc REMOVED - workflow checklist unused

        $(document).delegate('.openassigneeshow', 'click', function(){

            $('.assigneeshow').show();

        });



        $(document).delegate('.closeassigneeshow', 'click', function(){

            $('.assigneeshow').hide();

        });



        $(document).delegate('.saveassignee', 'click', function(){

            var appliid = $(this).attr('data-id');

            $('.popuploader').show();

            $.ajax({

                url: site_url+'/clients/change_assignee',

                type:'GET',

                data:{id: appliid,assinee: $('#changeassignee').val()},

                success: function(response){

                    var obj = safeParseJsonResponse(response);
                    if (!obj) {
                        $('.popuploader').hide();
                        return;
                    }
                    if(obj.status){

                        alert(obj.message);

                        location.reload();

                    }else{

                        alert(obj.message);
                        $('.popuploader').hide();

                    }

                },

                error: function() {
                    $('.popuploader').hide();
                }

            });

        });







        var notuse_doc_id = '';

        var notuse_doc_href = '';

        var notuse_doc_type = '';



        $(document).off('click.notuseddoc', '.notuseddoc').on('click.notuseddoc', '.notuseddoc', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if ($('#confirmNotUseDocModal').length === 0) {
                console.error('Modal #confirmNotUseDocModal not found!');
                return;
            }

            notuse_doc_id = $(this).attr('data-id');
            notuse_doc_href = $(this).attr('data-href');
            notuse_doc_type = $(this).attr('data-doctype');
            $('#confirmNotUseDocModal').modal('show');
        });

        $(document).off('click.notusedconfirm', '#confirmNotUseDocModal .accept').on('click.notusedconfirm', '#confirmNotUseDocModal .accept', function() {
            if (!notuse_doc_id) {
                return;
            }

            $('.popuploader').show();

            $.ajax({
                url: window.ClientDetailConfig.urls.admin + '/documents/not-used',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    doc_id: notuse_doc_id,
                    doc_type: notuse_doc_type,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) {
                        showClientDocFeedback('error', 'Invalid response while moving document. Please refresh the page.');
                        return;
                    }

                    $('#confirmNotUseDocModal').modal('hide');

                    if (res.status) {
                        try {
                            applyNotUsedMoveSuccess(res);
                        } catch (err) {
                            console.error('Error updating UI after moving document to Not Used tab', err);
                            showClientDocFeedback('warning', 'Document moved, but the page needs a refresh to show the latest list.');
                        }
                    } else {
                        console.error('Failed to move document to Not Used tab', res);
                        showClientDocFeedback('error', res.message || 'Failed to move document');
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('AJAX error moving document to Not Used tab', {status: status, error: error});
                    showClientDocFeedback('error', 'Error moving document. Please try again.');
                }
            });
        });





        var backto_doc_id = '';

        var backto_doc_href = '';

        var backto_doc_type = '';

        // Delegated binding so dynamically added Not Used rows (soft move) also work.
        $(document).off('click.backtodoc', '.backtodoc').on('click.backtodoc', '.backtodoc', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if ($('#confirmBackToDocModal').length === 0) {
                console.error('Modal #confirmBackToDocModal not found!');
                return;
            }

            backto_doc_id = $(this).attr('data-id');
            backto_doc_href = $(this).attr('data-href');
            backto_doc_type = $(this).attr('data-doctype');
            $('#confirmBackToDocModal').modal('show');
        });

        $(document).off('click.backtodocconfirm', '#confirmBackToDocModal .accept').on('click.backtodocconfirm', '#confirmBackToDocModal .accept', function() {
            if (!backto_doc_id) {
                return;
            }

            $('.popuploader').show();

            $.ajax({
                url: window.ClientDetailConfig.urls.admin + '/documents/back-to-doc',
                type: 'POST',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    doc_id: backto_doc_id,
                    doc_type: backto_doc_type,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) {
                        showClientDocFeedback('error', 'Invalid response while restoring document. Please refresh the page.');
                        return;
                    }
                    $('#confirmBackToDocModal').modal('hide');

                    if (res.status) {
                        try {
                            applyBackToDocSuccess(res);
                        } catch (err) {
                            console.error('Error updating UI after restoring document from Not Used', err);
                            $('.notuseddocumnetlist #id_' + (res.doc_id || backto_doc_id)).remove();
                            showClientDocFeedback('warning', 'Document restored, but the page needs a refresh to show the latest list.');
                        }
                        backto_doc_id = '';
                        backto_doc_type = '';
                    } else {
                        console.error('Failed to move document back', res);
                        showClientDocFeedback('error', res.message || 'Failed to move document back');
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('AJAX error moving document back', {status: status, error: error});
                    showClientDocFeedback('error', 'Error moving document back. Please try again.');
                }
            });
        });





        var notid = '';

        var delhref = '';

        $('.deletenote').off('click').on('click', function(e) { 

            e.preventDefault();

            e.stopPropagation();

            $('#confirmModal').modal('show');

            notid = $(this).attr('data-id');

            delhref = $(this).attr('data-href');

           

        });



        $(document).on('click', '.deletenote', function(e) {

            e.preventDefault();

            e.stopPropagation();

            $('#confirmModal').modal('show');

            notid = $(this).attr('data-id');

            delhref = $(this).attr('data-href');

            

        });



        // Cost Agreement Deletion Handler

        var costAgreementId = '';

        $('.deleteCostAgreement').off('click').on('click', function(e) { 

            e.preventDefault();

            e.stopPropagation();

            $('#confirmCostAgreementModal').modal('show');

            costAgreementId = $(this).attr('data-id');

        });



        $(document).on('click', '.deleteCostAgreement', function(e) {

            e.preventDefault();

            e.stopPropagation();

            $('#confirmCostAgreementModal').modal('show');

            costAgreementId = $(this).attr('data-id');

        });



        // Cost Agreement Deletion Confirmation Handler

        $(document).delegate('#confirmCostAgreementModal .acceptCostAgreementDelete', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.deleteCostagreement,

                type:'GET',

                dataType:'json',

                data:{cost_agreement_id:costAgreementId},

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmCostAgreementModal').modal('hide');

                    if(res.status){

                        // Remove the table row from the DOM

                        $('button[data-id="'+costAgreementId+'"]').closest('tr').remove();

                        

                        // Check if there are any remaining rows, if not show empty message

                        if($('.costform-table tbody tr').length === 0){

                            $('.costform-table').closest('.bg-white').html('<p class="text-gray-600 text-center py-6">No Cost Assignment records found for this client.</p>');

                        }

                        

                        // Show success message

                        alert('Cost Agreement deleted successfully!');

                    } else {

                        alert('Error: ' + (res.message || 'Failed to delete Cost Agreement'));

                    }

                },

                error: function(xhr, status, error) {

                    $('.popuploader').hide();

                    $('#confirmCostAgreementModal').modal('hide');

                    alert('Error: Failed to delete Cost Agreement. Please try again.');

                }

            });

        });
        $(document).delegate('#confirmModal .accept', 'click', function(){

            $('.popuploader').show();

            // Determine the correct URL based on delhref
            var deleteUrl;
            if(delhref == 'deletenote'){
                deleteUrl = window.ClientDetailConfig.urls.deleteNote;
            } else if(delhref == 'deleteclientportaldocs'){
                // Workflow checklist unused - route removed; no-op
                $('.popuploader').hide();
                $('#confirmModal').modal('hide');
                return;
            } else {
                deleteUrl = window.ClientDetailConfig.urls.admin + '/documents/delete';
            }

            $.ajax({

                url: deleteUrl,

                type:'GET',

                dataType:'json',

                data:{note_id:notid},

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmModal').modal('hide');

                    if(res.status){

                        $('#note_id_'+notid).remove();

                        if(res.status == true){

                            $('#id_'+notid).remove();

                        }



                        if(delhref == 'deletedocs'){

                            $('.documnetlist_'+res.doc_categry+' #id_'+notid).remove();

                        }

                        // deleteservices block REMOVED - route and controller method no longer exist; /get-services route also removed

                        // DEPRECATED: Appointment system removed - deleteappointment route no longer exists
                        if(delhref == 'deleteappointment'){

                            // Commented out - appointment system removed
                            /*
                            $.ajax({

                                url: site_url+'/get-appointments',

                                type:'GET',

                                data:{clientid:window.ClientDetailConfig.clientId},

                                success: function(responses){

                                    $('.appointmentlist').html(responses);

                                }

                            });
                            */
                            console.warn('deleteappointment route has been removed - appointment system deprecated');

                        } else if(delhref == 'deleteclientportaldocs'){
                            // REMOVED - workflow checklist unused
                        } else if(delhref == 'deletenote'){

                            getallnotes();

                            

                        } else {

                            getallnotes();

                            

                        }

                        getallactivities();

                    }

                },

                error: function() {
                    $('.popuploader').hide();
                }

            });

        });







        var activitylogid = '';

        var delloghref = '';

        $(document).delegate('.deleteactivitylog', 'click', function(){

            $('#confirmLogModal').modal('show');

            activitylogid = $(this).attr('data-id');

            delloghref = $(this).attr('data-href');

        });



        $(document).delegate('#confirmLogModal .accept', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.admin + '/' + delloghref,

                type:'GET',

                dataType:'json',

                data:{activitylogid:activitylogid},

                success:function(response){

                    $('.popuploader').hide();

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('#confirmLogModal').modal('hide');

                    //location.reload();

                    if(res.status){

                        $('#activity_'+activitylogid).remove();

                        if(res.status == true){

                            $('#activity_'+activitylogid).remove();

                        }

                        getallactivities();

                    }

                },

                error: function() {
                    $('.popuploader').hide();
                }

            });

        });





        $(document).on('click', '.pinnote', function(e) {

            e.preventDefault();

            var noteId = $(this).attr('data-id');

            if (!noteId) {

                console.error('[PinNote] Missing data-id on pinnote element');

                return;

            }

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.pinNote,

                type:'GET',

                dataType:'json',

                data:{note_id: noteId},

                success:function(response){

                    if (response && response.status) {

                        if (typeof getallnotes === 'function') {

                            getallnotes();

                        }
                        $('.popuploader').hide();

                    } else {

                        $('.popuploader').hide();

                        if (typeof toastr !== 'undefined') {

                            toastr.error(response && response.message ? response.message : 'Failed to pin note');

                        } else {

                            alert(response && response.message ? response.message : 'Failed to pin note');

                        }

                    }

                },

                error: function(xhr, status, error) {

                    $('.popuploader').hide();

                    console.error('[PinNote] AJAX error:', status, error, xhr.responseText);

                    if (typeof toastr !== 'undefined') {

                        toastr.error('Failed to pin note. Please try again.');

                    } else {

                        alert('Failed to pin note. Please try again.');

                    }

                }

            });

        });



        //Pin activity log click

        $(document).delegate('.pinactivitylog', 'click', function(){

            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.pinActivityLog + '/',

                type:'GET',

                dataType:'json',

                data:{activity_id:$(this).attr('data-id')},

                success:function(response){

                    getallactivities();
                    $('.popuploader').hide();

                },

                error: function() {
                    $('.popuploader').hide();
                }

            });

        });



        // createapplicationnewinvoice handler removed - Create Invoice from Schedule flow unused



        $('.js-data-example-ajaxcontact').mmSelect({

                multiple: true,

                closeOnSelect: false,

                dropdownParent: $('#opentaskmodal'),

                ajax: {

                    url: window.ClientDetailConfig.urls.getRecipients,

                    dataType: 'json',

                    processResults: function (data) {

                    // Transforms the top-level key of the response object from 'items' to 'results'

                    return {

                        results: data.items

                    };



                    },

                    cache: true



                },

            templateResult: formatRepo,

            templateSelection: formatRepoSelection

        });



        //Function is used for complete the session

        $(document).delegate('.complete_session', 'click', function(){

            var client_id = $(this).attr('data-clientid'); //alert(client_id);

            if(client_id !=""){

                $.ajax({

                    type:'post',

                    url: window.ClientDetailConfig.urls.updateSessionCompleted,

                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},

                    data: {client_id:client_id },

                    success: function(response){

                        var obj = safeParseJsonResponse(response);
                        location.reload();

                    }

                });

            }

        });



        function composeEmailRecipientAjaxConfig() {
            return {
                url: window.ClientDetailConfig.urls.getRecipients,
                dataType: 'json',
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return {
                        results: data.items
                    };
                },
                cache: true
            };
        }

        function composeEmailCcCreateOption(input) {
            var value = $.trim(String(input || ''));
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                return false;
            }
            return {
                id: value,
                name: value,
                email: value,
                text: value
            };
        }

        /** Compose Email To/CC: dropdown on body so Tom Select positionDropdown() runs in modals. */
        function composeEmailRecipientDropdownOptions() {
            return {
                dropdownParent: $('body'),
                dropdownCssClass: 'mm-compose-email-recipients-dropdown'
            };
        }

        /** Compose Email "To" field: shared Tom Select options (ajax + formatRepo) */
        function emailModalRecipientsTomSelectBase() {
            return $.extend({
                multiple: true,
                closeOnSelect: false,
                ajax: composeEmailRecipientAjaxConfig(),
                templateResult: formatRepo,
                templateSelection: formatRepoSelection
            }, composeEmailRecipientDropdownOptions());
        }

        /** Compose Email CC: CRM search + optional raw email addresses */
        function emailModalCcTomSelectBase() {
            return $.extend({}, emailModalRecipientsTomSelectBase(), {
                create: composeEmailCcCreateOption,
                createFilter: function (input) {
                    return composeEmailCcCreateOption(input) !== false;
                },
                createOnBlur: true
            });
        }

        function buildComposeRecipientSelectItem(recipient) {
            var id = String(recipient.id);
            var name = recipient.name || recipient.email || id;
            var email = recipient.email || '';
            var status = recipient.status || 'Client';

            return {
                id: id,
                name: name,
                email: email,
                text: name,
                html: "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +
                    "<div  class='ag-flex ag-align-start'>" +
                    "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'>" + name + "</span>&nbsp;</div>" +
                    "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>" + email + "</small ></div>" +
                    "</div>" +
                    "</div>" +
                    "<div class='ag-flex ag-flex-column ag-align-end'>" +
                    "<span class='ui label yellow mm-result-repository__statistics'>" + status + "</span>" +
                    "</div>" +
                    "</div>",
                title: name
            };
        }

        /** Pre-fill compose To via Tom Select (reply/forward from email tab). */
        window.initComposeEmailToField = function (selectedIds, recipients) {
            var $toSelect = $('#emailmodal .js-data-example-ajax');
            if (!$toSelect.length) {
                return;
            }
            if ($toSelect[0].tomselect) {
                $toSelect.mmSelect('destroy');
            }
            var data = (recipients || []).map(buildComposeRecipientSelectItem);
            $toSelect.mmSelect($.extend(emailModalRecipientsTomSelectBase(), { data: data }));
            var ids = (selectedIds || []).map(String);
            if (ids.length) {
                $toSelect.val(ids);
                $toSelect.trigger('change');
            } else {
                $toSelect.val(null);
                $toSelect.trigger('change');
            }
            $('#emailmodal').data('composeToFieldCustomized', true);
        };

        /** Restore default ajax-only To field after reply/forward compose closes. */
        window.resetComposeEmailToField = function () {
            var $toSelect = $('#emailmodal .js-data-example-ajax');
            if (!$toSelect.length || !$('#emailmodal').data('composeToFieldCustomized')) {
                return;
            }
            if ($toSelect[0].tomselect) {
                $toSelect.mmSelect('destroy');
            }
            $toSelect.mmSelect(emailModalRecipientsTomSelectBase());
            $toSelect.val(null);
            $toSelect.trigger('change');
            $('#emailmodal').removeData('composeToFieldCustomized');
        };

        window.resetComposeEmailCcField = function () {
            var $ccSelect = $('#emailmodal .js-data-example-ajaxccd');
            if (!$ccSelect.length) {
                return;
            }
            if ($ccSelect[0].tomselect) {
                $ccSelect[0].tomselect.clear(true);
            } else {
                $ccSelect.val(null);
                $ccSelect.trigger('change');
            }
        };



        $(document).delegate('.clientemail', 'click', function(){

            if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                var selectedMatterL = $('.general_matter_checkbox_client_detail').val();

            } else {

                var selectedMatterL = $('#sel_matter_id_client_detail').val();

            }

            $('#emailmodal #compose_client_matter_id').val(selectedMatterL);

            $('#emailmodal').modal('show');

            var array = [];

            var data = [];



            var id = $(this).attr('data-id');

            array.push(id);

            var email = $(this).attr('data-email');

            var name = $(this).attr('data-name');

            var status = 'Client';



            data.push({

                id: id,

                name: name,

                email: email,

                text: name,

                html:  "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +



                "<div  class='ag-flex ag-align-start'>" +

                    "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +

                    "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>"+email+"</small ></div>" +



                "</div>" +

                "</div>" +

                "<div class='ag-flex ag-flex-column ag-align-end'>" +



                    "<span class='ui label yellow mm-result-repository__statistics'>"+ status +



                    "</span>" +

                "</div>" +

                "</div>",

                title: name

            });



            $('#emailmodal .js-data-example-ajax').mmSelect($.extend(emailModalRecipientsTomSelectBase(), { data: data }))



            $('#emailmodal .js-data-example-ajax').val(array);

            $('#emailmodal .js-data-example-ajax').trigger('change');

        });



        $(document).delegate('.send-google-review', 'click', function(){

            var $btn = $(this);

            var templateId = $btn.data('template-id');

            if (!templateId) {

                if (typeof iziToast !== 'undefined') {

                    iziToast.warning({ message: 'Google Review template not found. Please create a CRM Email Template with name containing "Google Review" or alias "google_review".', position: 'topRight' });

                } else {

                    alert('Google Review template not found. Please create a CRM Email Template with name containing "Google Review" or alias "google_review" in Admin Console.');

                }

                return;

            }

            $('#emailmodal #compose_client_matter_id').val('');

            $('#emailmodal').modal('show');

            var array = [];

            var data = [];

            var id = $btn.attr('data-id');

            array.push(id);

            var email = $btn.attr('data-email');

            var name = $btn.attr('data-name');

            var status = 'Client';

            data.push({

                id: id,

                name: name,

                email: email,

                text: name,

                html:  "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +

                    "<div  class='ag-flex ag-align-start'>" +

                    "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'>"+name+"</span>&nbsp;</div>" +

                    "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'>"+email+"</small ></div>" +

                    "</div>" +

                    "</div>" +

                    "<div class='ag-flex ag-flex-column ag-align-end'>" +

                    "<span class='ui label yellow mm-result-repository__statistics'>"+ status +

                    "</span>" +

                    "</div>" +

                    "</div>",

                title: name

            });

            $('#emailmodal .js-data-example-ajax').mmSelect($.extend(emailModalRecipientsTomSelectBase(), { data: data }));

            $('#emailmodal .js-data-example-ajax').val(array);

            $('#emailmodal .js-data-example-ajax').trigger('change');

            $('#emailmodal').one('shown.bs.modal', function(){

                var $templateSelect = $('#emailmodal select.selecttemplate');

                if ($templateSelect.length && templateId) {

                    $templateSelect.val(templateId).trigger('change');

                }

            });

        });



        $(document).delegate('.change_client_status', 'click', function(e){



            var v = $(this).attr('rating');

            $('.change_client_status').removeClass('active');

            $(this).addClass('active');



            $.ajax({

                url: window.ClientDetailConfig.urls.changeClientStatus,

                type:'GET',

                dataType:'json',

                data:{id:window.ClientDetailConfig.clientId,rating:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    if(res.status){



                        $('.custom-error-msg').html('<span class="alert alert-success">'+res.message+'</span>');

                        getallactivities();

                    }else{

                        $('.custom-error-msg').html('<span class="alert alert-danger">'+response.message+'</span>');

                    }



                }

            });

        });



        /*$(document).delegate('.selecttemplate', 'change', function(){

            var v = $(this).val();

            $.ajax({

                url: window.ClientDetailConfig.urls.getTemplates,

                type:'GET',

                dataType:'json',

                data:{id:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('.selectedsubject').val(res.subject);

                    clearEditor("#emailmodal .tinymce-editor");

                    setEditorContent("#emailmodal .tinymce-editor", res.description);

                    $("#emailmodal .tinymce-editor").val(res.description);

                }

            });

        });*/



        $(document).delegate('.selecttemplate', 'change', function(){

            var client_id = $(this).data('clientid'); //alert(client_id);

            var client_firstname = $(this).data('clientfirstname'); //alert(client_firstname);

            if (client_firstname) {

                client_firstname = client_firstname.charAt(0).toUpperCase() + client_firstname.slice(1);

            }

            var client_reference_number = $(this).data('clientreference_number'); //alert(client_reference_number);

            var company_name = 'Bansal Education Group';

            var visa_valid_upto = $(this).data('clientvisaExpiry');

            if ( visa_valid_upto != '' && visa_valid_upto != '0000-00-00') {

                visa_valid_upto = visa_valid_upto;

            } else {

                visa_valid_upto = '';

            }



            var clientassignee_name = $(this).data('clientassignee_name');

            if ( clientassignee_name != '') {

                clientassignee_name = clientassignee_name;

            } else {

                clientassignee_name = '';

            }



            var v = $(this).val();
            if (!v) {
                return;
            }

            $.ajax({

                url: window.ClientDetailConfig.urls.getTemplates,

                type:'GET',

                dataType:'json',

                data:{id:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;



                    // Replace {Client First Name} with actual client name

                    //var subjct_message = res.subject

                    //.replace('{Client First Name}', client_firstname)

                    //.replace(/Ref:\s*\.{1,}\s*/, 'Ref: ' + client_reference_number)

                    //.replace(/Ref_\s*-{1,}\s*/, 'Ref_' + client_reference_number)

                    //.replace('{client reference}', client_reference_number);



                    var subjct_message = res.subject.replace('{Client First Name}', client_firstname).replace('{client reference}', client_reference_number);

                    var subjct_description = res.description
                    .replace('{Client First Name}', client_firstname)
                    .replace('{Company Name}', company_name)
                    .replace('{Visa Valid Upto}', visa_valid_upto)
                    .replace('{Client Assignee Name}', clientassignee_name)
                    .replace('{client reference}', client_reference_number);

                    // Apply First email macro values when available (from getComposeDefaults)
                    var macroVals = $('#emailmodal').data('composeMacroValues');
                    var descriptionBeforeDiscount = subjct_description;
                    if (macroVals) {
                        var repl = function(str) {
                            if (!str) return '';
                            str = str.replace(/\{ClientID\}/g, macroVals.ClientID || '');
                            str = str.replace(/\{ApplicantGivenNames\}/g, macroVals.ApplicantGivenNames || macroVals.client_firstname || client_firstname || '');
                            str = str.replace(/\{visa_apply\}/g, macroVals.visa_apply || '');
                            str = str.replace(/\{Blocktotalfeesincltax\}/g, macroVals.Blocktotalfeesincltax || '');
                            str = str.replace(/\$\{Blocktotalfeesincltax\}/g, macroVals.Blocktotalfeesincltax || '');
                            str = str.replace(/\{TotalDoHASurcharges\}/g, macroVals.TotalDoHASurcharges || '');
                            str = str.replace(/\$\{TotalDoHASurcharges\}/g, macroVals.TotalDoHASurcharges || '');
                            str = str.replace(/\{TotalEstimatedOthCosts\}/g, macroVals.TotalEstimatedOthCosts || '');
                            str = str.replace(/\$\{TotalEstimatedOthCosts\}/g, macroVals.TotalEstimatedOthCosts || '');
                            str = str.replace(/\{DiscountAmount\}/g, macroVals.DiscountAmount || '');
                            str = str.replace(/\$\{DiscountAmount\}/g, macroVals.DiscountAmount || '');
                            str = str.replace(/\{GrandTotalFeesAndCosts\}/g, macroVals.GrandTotalFeesAndCosts || '');
                            str = str.replace(/\$\{GrandTotalFeesAndCosts\}/g, macroVals.GrandTotalFeesAndCosts || '');
                            var pdfUrl = macroVals.PDF_url_for_sign || '';
                            var pdfLink = typeof window.mmBuildServiceAgreementSignLink === 'function'
                                ? window.mmBuildServiceAgreementSignLink(pdfUrl)
                                : '';
                            if (pdfUrl && $('#compose_signing_url').length) {
                                $('#compose_signing_url').val(pdfUrl);
                            }
                            str = str.replace(/\{PDF_url_for_sign\}/g, pdfLink);
                            return str;
                        };
                        subjct_message = repl(subjct_message);
                        subjct_description = repl(subjct_description);
                        descriptionBeforeDiscount = subjct_description;
                        try {
                            if (typeof applyDiscountRowToEmailHtml === 'function') {
                                subjct_description = applyDiscountRowToEmailHtml(subjct_description, macroVals.DiscountAmount);
                            } else if (typeof window.applyDiscountRowToEmailHtml === 'function') {
                                subjct_description = window.applyDiscountRowToEmailHtml(subjct_description, macroVals.DiscountAmount);
                            }
                        } catch (discountErr) {
                            subjct_description = descriptionBeforeDiscount;
                        }
                    }

                    $('.selectedsubject').val(subjct_message);
                    setComposeEmailTemplateBody(subjct_description, descriptionBeforeDiscount);

                }

            });

        });



        $(document).delegate('.selectmattertemplate', 'change', function(){

            var v = $(this).val();

            $.ajax({

                url: window.ClientDetailConfig.urls.getTemplates,

                type:'GET',

                dataType:'json',

                data:{id:v},

                success: function(response){

                    var res = safeParseJsonResponse(response);
                    if (!res) return;
                    $('.selectedappsubject').val(res.subject);

                    // Set content in TinyMCE editor
                    if (typeof setTinyMCEContent === 'function') {
                        setTinyMCEContent('matter_email_message', res.description);
                    } else if (typeof tinymce !== 'undefined' && tinymce.get('matter_email_message')) {
                        tinymce.get('matter_email_message').setContent(res.description);
                    } else {
                        $("#matter_email_message").val(res.description);
                    }

                }

            });

        });



        $('#emailmodal .js-data-example-ajax').mmSelect(emailModalRecipientsTomSelectBase());



        $('#emailmodal .js-data-example-ajaxccd').mmSelect(emailModalCcTomSelectBase());

        $('.js-data-example-ajaxccapp').mmSelect(emailModalCcTomSelectBase());



        function formatRepo (repo) {

            if (repo.loading) {

                return repo.text;

            }



            var $container = $(

                "<div  class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +



                    "<div  class='ag-flex ag-align-start'>" +

                    "<div  class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span  class='mm-result-repository__title text-semi-bold'></span>&nbsp;</div>" +

                    "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small ></div>" +



                    "</div>" +

                    "</div>" +

                    "<div class='ag-flex ag-flex-column ag-align-end'>" +



                    "<span class='ui label yellow mm-result-repository__statistics'>" +



                    "</span>" +

                    "</div>" +

                "</div>"

                );



            $container.find(".mm-result-repository__title").text(repo.name);

            $container.find(".mm-result-repository__description").text(repo.email);

            $container.find(".mm-result-repository__statistics").append(repo.status);

            return $container;

        }



        function formatRepoSelection (repo) {

            // Tom Select's remove_button hook calls appendChild on render.item output; a falsy/empty string
            // makes render() return null and crashes (Cannot read properties of null reading appendChild).

            if (!repo) {

                return '(recipient)';

            }

            var label = (repo.name || repo.text || repo.email || '').toString().trim();

            if (label) {

                return label;

            }

            var id = repo.id != null ? repo.id : repo.value;

            if (id !== undefined && id !== '') {

                return '#' + String(id);

            }

            return '(recipient)';

        }



        /* $(".table-2").dataTable({

            "searching": false,

            "lengthChange": false,

        "columnDefs": [

            { "sortable": false, "targets": [0, 2, 3] }

        ],

        order: [[1, "desc"]] //column indexes is zero based



        }); */

        // Custom search: filter checklist table by matter when composeChecklistFilterIds is set (register once)
        if (!window.composeChecklistSearchRegistered && $.fn.dataTable && $.fn.dataTable.ext) {
            window.composeChecklistSearchRegistered = true;
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if ($(settings.nTable).attr('id') !== 'mychecklist-datatable') return true;
            var filterIds = window.composeChecklistFilterIds;
            if (filterIds === undefined || filterIds === null) return true;
            var rowNode = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex].nTr : null;
            if (!rowNode) return true;
            var id = $(rowNode).attr('data-checklist-id') || $(rowNode).find('.checklistfile-cb').val();
            if (!id) return true;
            var idNum = parseInt(id, 10);
            return filterIds.some(function(f) { return f == idNum || String(f) === String(id); });
            });
        }

        $('#mychecklist-datatable').dataTable({"searching": true,});

        $(".invoicetable").dataTable({

            "searching": false,

            "lengthChange": false,

            "columnDefs": [

            { "sortable": false, "targets": [0, 2, 3] }

        ],

        order: [[1, "desc"]] //column indexes is zero based



        });





        $(document).delegate('#intrested_workflow', 'change', function(){
			var v = $('#intrested_workflow option:selected').val();

            if(v != ''){

                $('.popuploader').show();

                $.ajax({

                    url: window.ClientDetailConfig.urls.getPartner,

                    type:'GET',

                    data:{cat_id:v},

                    success:function(response){

                        $('.popuploader').hide();

                        $('#intrested_partner').html(response);



                        $("#intrested_partner").val('').trigger('change');

                    $("#intrested_product").val('').trigger('change');

                    $("#intrested_branch").val('').trigger('change');

                    },

                    error: function() {
                        $('.popuploader').hide();
                    }

                });

            }

	    });



        $(document).delegate('#edit_intrested_workflow', 'change', function(){



                    var v = $('#edit_intrested_workflow option:selected').val();



                    if(v != ''){

                            $('.popuploader').show();

            $.ajax({

                url: window.ClientDetailConfig.urls.getPartner,

                type:'GET',

                data:{cat_id:v},

                success:function(response){

                    $('.popuploader').hide();

                    $('#edit_intrested_partner').html(response);



                    $("#edit_intrested_partner").val('').trigger('change');

                $("#edit_intrested_product").val('').trigger('change');

                $("#edit_intrested_branch").val('').trigger('change');

                },

                error: function() {
                    $('.popuploader').hide();
                }

            });

                    }

        });



        // REMOVED: Interested partner/product/branch dropdowns (orphaned - no routes exist)
        // The add_interested_service modal exists but has no UI triggers
        // Routes getProduct and getBranch were never implemented





        



        // Ensure the event listener is attached to all .add-document buttons

        $(document).on('click', '.add-document', function(e) {

            e.preventDefault(); // Prevent default anchor behavior

            var fileid = $(this).data('fileid');

            $('#upload_form_' + fileid).find('.docupload').click();

        });



        // Use on() instead of delegate() for better compatibility
        $(document).on('change', '.docupload', function () {

            var fileInput = this;

            var file = fileInput.files[0];

            if (!file) {

                return;
            }


            var fileidL = $(this).attr("data-fileid");
            var doccategoryL = $(this).attr("data-doccategory");
            

            var $form = $(this).closest('form');
            if (!$form.length) {
                console.error('❌ Form not found for file input');
                alert('Error: Upload form not found. Please refresh the page.');
                return;
            }

            var formData = new FormData($form[0]);

            if (!clientDetailIsAllowedDocumentFilename(file.name)) {
                alert(clientDetailDocFilenameMessage());
                $(this).val('');
                return false;
            }

            clientDetailApplyWafSafeDocumentUpload(formData, file);

            // Show immediate feedback that upload is starting

            $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Uploading document...</span>');



            $.ajax({

                url: site_url + '/documents/upload-edu-document',

                type: 'POST',

                dataType: 'json',

                data: formData,

                contentType: false,

                processData: false,

                success: function (ress) {

                    if (ress.status) {

                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');



                        var row = $('#id_' + fileidL);

                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();



                        // Replace upload TD content (Column 1 = File Name)

                        var uploadTd = row.find('td').eq(1);

                        uploadTd.html(

                            '<div data-id="' + fileidL + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="showFileContextMenu(event, ' + fileidL + ', \'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + doccategoryL + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +

                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'preview-container-' + doccategoryL + '\')">' +

                                    crmI('fas fa-file-image') + ' <span>' + ress.filename + '</span>' +

                                '</a>' +

                            '</div>'

                        );



                        // Add hidden elements for context menu actions (Column 2 = Actions)

                        var actionTd = row.find('td').eq(2);

                        actionTd.html(

                            '<a class="renamechecklist" data-id="' + fileidL + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="renamedoc" data-id="' + fileidL + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="download-file" data-filelink="' + ress.fileurl + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +

                            '<a class="notuseddoc" data-id="' + fileidL + '" data-doctype="' + ress.doctype + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'

                        );

                        

                        // Ensure the row has the proper class for event delegation

                        row.addClass('drow');

                    } else {

                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');

                    }

                    getallactivities();

                }

            }).fail(function(xhr, status, error) {
                console.error('❌ AJAX Upload Error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed: ' + (error || 'Please try again') + '</span>');
            }).always(function() {
                // Clear input after upload attempt (success or failure)
                fileInput.value = '';
            });

        });




        // --- DRAG AND DROP: Personal & Visa Documents ---

        // Prevent browser's default drag behavior (required for file drops to work)
        // This must be on document level, but we let drop zones handle their own events
        $(document).on('dragover', function(e) {
            // Allow drop zones to handle their own dragover events
            if ($(e.target).closest('.personal-doc-drag-zone, .visa-doc-drag-zone, .nomination-doc-drag-zone, .bulk-upload-dropzone, .bulk-upload-dropzone-visa, .bulk-upload-dropzone-nomination').length) {
                return; // Let the drop zone handler take over
            }
            // For other areas, prevent default to allow file drops
            e.preventDefault();
        });

        $(document).on('drop', function(e) {
            // Allow drop zones to handle their own drop events
            if ($(e.target).closest('.personal-doc-drag-zone, .visa-doc-drag-zone, .nomination-doc-drag-zone, .bulk-upload-dropzone, .bulk-upload-dropzone-visa, .bulk-upload-dropzone-nomination').length) {
                return; // Let the drop zone handler take over
            }
            // For other areas, prevent default to prevent browser from opening file
            e.preventDefault();
        });

        // Personal Documents - Drag and Drop Handlers
        
        // Debug: Check if handlers are being attached

        
        $(document).on('dragover', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $(document).on('dragenter', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $(document).on('dragleave', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            // Only remove class if leaving the drop zone itself, not child elements
            var rect = this.getBoundingClientRect();
            var x = e.originalEvent.clientX;
            var y = e.originalEvent.clientY;
            
            if (x <= rect.left || x >= rect.right || y <= rect.top || y >= rect.bottom) {
                $(this).removeClass('drag_over');
            }
            return false;
        });
        
        $(document).on('drop', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {

                handlePersonalDocDragDrop($(this), files[0]);
            } else {
                console.error('❌ No files in drop event');
            }
            return false;
        });
        
        $(document).on('click', '.personal-doc-drag-zone', function(e) {

            e.preventDefault();
            e.stopPropagation();
            var fileid = $(this).data('fileid');

            var fileInput = $('#upload_form_' + fileid).find('.docupload');

            if (fileInput.length > 0) {
                fileInput.click();
            } else {
                console.error('❌ File input not found for fileid:', fileid);
            }
            return false;
        });
        
        // Visa Documents - Drag and Drop Handlers
        
        $(document).delegate('.visa-doc-drag-zone', 'dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });
        
        $(document).delegate('.visa-doc-drag-zone', 'dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            return false;
        });
        
        $(document).delegate('.visa-doc-drag-zone', 'drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                handleVisaDocDragDrop($(this), files[0]);
            }
            return false;
        });
        
        $(document).delegate('.visa-doc-drag-zone', 'click', function(e) {
            e.preventDefault();
            var fileid = $(this).data('fileid');
            var fileInput = $('#mig_upload_form_' + fileid).find('.migdocupload');
            fileInput.click();
        });

        $(document).delegate('.nomination-doc-drag-zone', 'dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('drag_over');
            return false;
        });

        $(document).delegate('.nomination-doc-drag-zone', 'dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');
            return false;
        });

        $(document).delegate('.nomination-doc-drag-zone', 'drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drag_over');

            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                handleVisaDocDragDrop($(this), files[0]);
            }
            return false;
        });

        $(document).delegate('.nomination-doc-drag-zone', 'click', function(e) {
            e.preventDefault();
            var fileid = $(this).data('fileid');
            var fileInput = $('#mig_upload_form_' + fileid).find('.migdocupload');
            fileInput.click();
        });
        
        // Personal Documents - Upload Handler
        
        function handlePersonalDocDragDrop(dragZone, file) {
            var fileid = dragZone.data('fileid');
            var doccategory = dragZone.data('doccategory');
            var formId = dragZone.data('formid');
            var form = $('#' + formId);
            
            // Validate filename
            if (!clientDetailIsAllowedDocumentFilename(file.name)) {
                alert(clientDetailDocFilenameMessage());
                return false;
            }
            
            // Create FormData with all form fields
            var formData = new FormData(form[0]);
            
            // Override the file input with dragged file (WAF-safe multipart name)
            clientDetailApplyWafSafeDocumentUpload(formData, file);
            
            // Visual feedback
            dragZone.addClass('uploading');
            $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Uploading document...</span>');
            
            // Upload via AJAX
            $.ajax({
                url: site_url + '/documents/upload-edu-document',
                type: 'POST',
                dataType: 'json',
                data: formData,
                contentType: false,
                processData: false,
                success: function(ress) {
                    dragZone.removeClass('uploading');
                    
                    if (ress.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');
                        
                        var row = $('#id_' + fileid);
                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();
                        
                        // Replace upload TD content (Column 1 = File Name)
                        var uploadTd = row.find('td').eq(1);
                        uploadTd.html(
                            '<div data-id="' + fileid + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="showFileContextMenu(event, ' + fileid + ', \'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + doccategory + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +
                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'preview-container-' + doccategory + '\')">' +
                                    crmI('fas fa-file-image') + ' <span>' + ress.filename + '</span>' +
                                '</a>' +
                            '</div>'
                        );
                        
                        // Add hidden elements for context menu actions (Column 2 = Actions)
                        var actionTd = row.find('td').eq(2);
                        actionTd.html(
                            '<a class="renamechecklist" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                            '<a class="renamedoc" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                            '<a class="download-file" data-filelink="' + ress.fileurl + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +
                            '<a class="notuseddoc" data-id="' + fileid + '" data-doctype="' + ress.doctype + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'
                        );
                        
                        row.addClass('drow');
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');
                    }
                    
                    getallactivities();
                },
                error: function(xhr, status, error) {
                    dragZone.removeClass('uploading');
                    $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');
                    console.error('Personal doc upload error:', error);
                }
            });
        }
        
        // Visa Documents - Upload Handler
        
        function handleVisaDocDragDrop(dragZone, file) {
            var fileid = dragZone.data('fileid');
            var visa_doc_cat = dragZone.data('doccategory');
            var formId = dragZone.data('formid');
            var form = $('#' + formId);
            var laneDocType = (form.find('input[name="doctype"]').val() || 'visa').toLowerCase();
            var uploadUrl = laneDocType === 'nomination'
                ? site_url + '/documents/upload-nomination-document'
                : site_url + '/documents/upload-visa-document';
            var previewPane = laneDocType === 'nomination' ? 'preview-container-nomdocumnetlist' : 'preview-container-migdocumnetlist';
            var contextMenuFn = laneDocType === 'nomination' ? 'showNominationFileContextMenu' : 'showVisaFileContextMenu';
            
            // Validate filename
            if (!clientDetailIsAllowedDocumentFilename(file.name)) {
                alert(clientDetailDocFilenameMessage());
                return false;
            }
            
            // Create FormData with all form fields
            var formData = new FormData(form[0]);
            
            // Override the file input with dragged file (WAF-safe multipart name)
            clientDetailApplyWafSafeDocumentUpload(formData, file);
            
            // Add extra data
            formData.append('visa_doc_cat', visa_doc_cat);
            
            // Visual feedback
            dragZone.addClass('uploading');
            $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Uploading document...</span>');
            
            // Upload via AJAX
            $.ajax({
                url: uploadUrl,
                type: 'POST',
                dataType: 'json',
                data: formData,
                contentType: false,
                processData: false,
                success: function(ress) {
                    dragZone.removeClass('uploading');
                    
                    if (ress.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');
                        
                        var row = $('#id_' + fileid);
                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();
                        
                        // Replace upload TD content (Column 1 = File Name)
                        var uploadTd = row.find('td').eq(1);
                        uploadTd.html(
                            '<div data-id="' + fileid + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="' + contextMenuFn + '(event, ' + fileid + ', \'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + visa_doc_cat + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +
                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + previewPane + '\')">' +
                                    crmI('fas fa-file-image') + ' <span>' + ress.filename + '</span>' +
                                '</a>' +
                            '</div>'
                        );
                        
                        // Add hidden elements for context menu actions (Column 2 = Actions)
                        var actionTd = row.find('td').eq(2);
                        actionTd.html(
                            '<a class="renamechecklist" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                            '<a class="renamedoc" data-id="' + fileid + '" href="javascript:;" style="display: none;"></a>' +
                            '<a class="download-file" data-filelink="' + ress.fileurl + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +
                            '<a class="notuseddoc" data-id="' + fileid + '" data-doctype="' + laneDocType + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'
                        );
                        
                        row.addClass('drow');
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');
                    }
                    
                    getallactivities();
                },
                error: function(xhr, status, error) {
                    dragZone.removeClass('uploading');
                    $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');
                    console.error('Visa doc upload error:', error);
                }
            });
        }





        $(document).delegate('.add_education_doc', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            $("#doccategory").val($(this).attr('data-categoryid'));

            $("#folder_name").val($(this).attr('data-categoryid'));

            $('.create_education_docs').modal('show');

        });



        //Add Personal Document category

        $(document).delegate('.add_personal_doc_cat', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            $('.addpersonaldoccatmodel').modal('show');

        });

        //Add Visa Document category

        $(document).delegate('.add-visa-doc-category', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            let selectedMatterFM;



            if ($('.general_matter_checkbox_client_detail').is(':checked')) {

                // If checkbox is checked, get its value

                selectedMatterFM = $('.general_matter_checkbox_client_detail').val();

            } else {

                // If checkbox is not checked, get the value from the dropdown

                selectedMatterFM = $('#sel_matter_id_client_detail').val();

            }

            $('#visaclientmatterid').val(selectedMatterFM);

            $('.addvisadoccatmodel').modal('show');

        });

        $(document).delegate('.add-nomination-doc-category', 'click', function (e) {

            e.preventDefault();

            let selectedMatterFM;

            if ($('.general_matter_checkbox_client_detail').is(':checked')) {
                selectedMatterFM = $('.general_matter_checkbox_client_detail').val();
            } else {
                selectedMatterFM = $('#sel_matter_id_client_detail').val();
            }

            $('#nominationclientmatterid').val(selectedMatterFM);

            $('.addnominationdoccatmodel').modal('show');

        });





        $(document).delegate('.add_migration_doc', 'click', function (e) {

            e.preventDefault(); // Prevent default button behavior and page refresh

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#hidden_client_matter_id').val(hidden_client_matter_id);

            $("#visa_folder_name").val($(this).attr('data-categoryid'));

            $('.create_migration_docs').modal('show');

        });

        $(document).delegate('.add_nomination_doc', 'click', function (e) {

            e.preventDefault();

            var hidden_client_matter_id = $('#sel_matter_id_client_detail').val();

            $('#hidden_nomination_client_matter_id').val(hidden_client_matter_id);

            $("#nomination_folder_name").val($(this).attr('data-categoryid'));

            $('.create_nomination_docs').modal('show');

        });


        // .openchecklist handler REMOVED - workflow checklist unused

        $(document).delegate('.migdocupload', 'click', function() {

            $(this).attr("value", "");

        });



        





        $(document).delegate('.migdocupload', 'change', function() {

            var fileInput = this.files[0];



            if (!fileInput) return; // Prevent empty uploads



            var fileName = fileInput.name;

            if (!clientDetailIsAllowedDocumentFilename(fileName)) {
                alert(clientDetailDocFilenameMessage());
                $(this).val('');
                return false;
            }

            var fileidL1 = $(this).attr("data-fileid");

           



            var visa_doc_cat = $(this).attr("data-doccategory");

            



            // Show immediate feedback that upload is starting

            $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Uploading document...</span>');

            

            // Create FormData before clearing the input

            var $form = $('#mig_upload_form_'+fileidL1);
            var laneDocType = ($form.find('input[name="doctype"]').val() || 'visa').toLowerCase();
            var uploadUrl = laneDocType === 'nomination'
                ? site_url+'/documents/upload-nomination-document'
                : site_url+'/documents/upload-visa-document';
            var previewPane = laneDocType === 'nomination' ? 'preview-container-nomdocumnetlist' : 'preview-container-migdocumnetlist';
            var contextMenuFn = laneDocType === 'nomination' ? 'showNominationFileContextMenu' : 'showVisaFileContextMenu';
            var formData = new FormData($form[0]);

            clientDetailApplyWafSafeDocumentUpload(formData, fileInput);

            // Append extra data manually
            formData.append('visa_doc_cat', visa_doc_cat);
            
            // Clear the file input after creating FormData to allow next upload
            $(this).val('');

            

            $.ajax({

                url: uploadUrl,

                type:'POST',

                dataType: 'json',

                data: formData,

                contentType: false,

                processData: false,

                success: function(ress) {

                    if (ress.status) {

                        $('.custom-error-msg').html('<span class="alert alert-success">' + ress.message + '</span>');



                        var row = $('#id_' + fileidL1);

                        var docNameWithoutExt = ress.filename.replace(/\.[^/.]+$/, "").replace(/\s+/g, "_").toLowerCase();



                        // Replace upload TD content (Column 1 = File Name)

                        var uploadTd = row.find('td').eq(1);

                        uploadTd.html(

                            '<div data-id="' + fileidL1 + '" data-name="' + docNameWithoutExt + '" class="doc-row" title="Uploaded by: ' + (ress.uploaded_by || 'Staff') + (ress.uploaded_at ? ' on ' + formatClientDocDateTime(ress.uploaded_at) : '') + '" oncontextmenu="' + contextMenuFn + '(event, ' + fileidL1 + ', \'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + visa_doc_cat + '\', \'' + (ress.status_value || 'draft') + '\'); return false;">' +

                                '<a href="javascript:void(0);" onclick="previewFile(\'' + ress.filetype + '\', \'' + ress.fileurl + '\', \'' + previewPane + '\')">' +

                                    crmI('fas fa-file-image') + ' <span>' + ress.filename + '</span>' +

                                '</a>' +

                            '</div>'

                        );



                        // Add hidden elements for context menu actions (Column 2 = Actions)

                        var actionTd = row.find('td').eq(2);

                        actionTd.html(

                            '<a class="renamechecklist" data-id="' + fileidL1 + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="renamedoc" data-id="' + fileidL1 + '" href="javascript:;" style="display: none;"></a>' +

                            '<a class="download-file" data-filelink="' + ress.fileurl + '" data-filename="' + ress.filekey + '" href="#" style="display: none;"></a>' +

                            '<a class="notuseddoc" data-id="' + fileidL1 + '" data-doctype="' + laneDocType + '" data-href="notuseddoc" href="javascript:;" style="display: none;"></a>'

                        );

                        

                        // Ensure the row has the proper class for event delegation

                        row.addClass('drow');

                    } else {

                        $('.custom-error-msg').html('<span class="alert alert-danger">' + ress.message + '</span>');

                    }

                    getallactivities();

                },

                error: function(xhr, status, error) {

                    $('.custom-error-msg').html('<span class="alert alert-danger">Upload failed. Please try again.</span>');

                    console.error('Upload error:', error);

                    getallactivities();

                }

            });

        });

    }); // End jQuery(document).ready from line ~2366
