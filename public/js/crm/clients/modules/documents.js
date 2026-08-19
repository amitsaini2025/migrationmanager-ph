/**
 * Documents module - Category updates, document rename, download
 * Extracted from detail-main.js - Phase 3d refactoring.
 * Requires: jQuery, ClientDetailConfig. Uses: previewFile (global)
 */
(function($) {
    'use strict';
    if (!$) return;

    var INLINE_RENAME_BTN_STYLE = 'display:inline-flex;align-items:center;justify-content:center;min-width:28px;min-height:28px;padding:0.25rem 0.5rem;';

    function buildDocRenameField(value) {
        return [
            $('<input style="display: inline-block;width: auto;" class="form-control opentime" type="text">').prop('value', value),
            $('<button type="button" class="btn btn-primary btn-sm mb-1 doc-rename-save" style="' + INLINE_RENAME_BTN_STYLE + '">' + crmI('fas fa-check') + '</button>'),
            $('<button type="button" class="btn btn-danger btn-sm mb-1 doc-rename-cancel" style="' + INLINE_RENAME_BTN_STYLE + '">' + crmI('far fa-trash-alt') + '</button>')
        ];
    }

    function showRenameToast(type, message) {
        var msg = message || (type === 'success' ? 'Saved successfully' : 'Could not save');
        var isSuccess = type === 'success';
        var izi = (typeof window !== 'undefined' && window.iziToast) ||
            (typeof iziToast !== 'undefined' ? iziToast : null);

        if (izi) {
            if (isSuccess && typeof izi.success === 'function') {
                izi.success({ message: msg, position: 'topRight', timeout: 3000 });
                return;
            }
            if (typeof izi.error === 'function') {
                izi.error({ message: msg, position: 'topRight' });
                return;
            }
        }

        if (typeof toastr !== 'undefined') {
            if (isSuccess && typeof toastr.success === 'function') {
                toastr.success(msg);
            } else if (typeof toastr.error === 'function') {
                toastr.error(msg);
            } else {
                alert(msg);
            }
            return;
        }

        alert(msg);
    }

    $(document).ready(function() {
        // ---- Update Personal Document Category ----
        $(document).on('click', '.update-personal-cat-title', function() {
            var $editBtn = $(this);
            if ($editBtn.data('updating')) {
                return;
            }
            var id = $editBtn.data('id');
            var currentTitle = ($editBtn.attr('data-title') || $editBtn.data('title') || '').toString().trim();
            if (!currentTitle) {
                currentTitle = $editBtn.closest('.button-container').find('.subtab2-button').first().text().trim();
            }
            var newTitle = prompt('Enter new title for the category:', currentTitle);
            if (newTitle) {
                newTitle = newTitle.trim();
            }
            if (newTitle) {
                $editBtn.data('updating', true).prop('disabled', true);
                $('.popuploader').show();
                $.ajax({
                    url: window.ClientDetailConfig.urls.updatePersonalCategory,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id,
                        title: newTitle
                    },
                    success: function(response) {
                        if (response.status) {
                            alert(response.message);
                            var updated = false;
                            try {
                                if (typeof window.renamePersonalDocCategoryUi === 'function') {
                                    updated = !!window.renamePersonalDocCategoryUi(id, newTitle);
                                }
                            } catch (renameErr) {
                                console.warn('[UpdatePersDocCat] UI rename failed, falling back to reload', renameErr);
                                updated = false;
                            }
                            if (!updated) {
                                location.reload();
                                return;
                            }
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the category. Please try again.');
                    },
                    complete: function() {
                        $('.popuploader').hide();
                        $editBtn.data('updating', false).prop('disabled', false);
                    }
                });
            }
        });

        // ---- Update Visa Document Category ----
        $(document).on('click', '.update-visa-cat-title', function() {
            var $editBtn = $(this);
            if ($editBtn.data('updating')) {
                return;
            }
            var id = $editBtn.data('id');
            var currentTitle = ($editBtn.attr('data-title') || $editBtn.data('title') || '').toString().trim();
            if (!currentTitle) {
                currentTitle = $editBtn.closest('.button-container').find('.subtab6-button').first().text().trim();
            }
            var newTitle = prompt('Enter new title for the category:', currentTitle);
            if (newTitle) {
                newTitle = newTitle.trim();
            }
            if (newTitle) {
                $editBtn.data('updating', true).prop('disabled', true);
                $('.popuploader').show();
                $.ajax({
                    url: window.ClientDetailConfig.urls.updateVisaCategory,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id,
                        title: newTitle
                    },
                    success: function(response) {
                        if (response.status) {
                            alert(response.message);
                            var updated = false;
                            try {
                                if (typeof window.renameVisaDocCategoryUi === 'function') {
                                    updated = !!window.renameVisaDocCategoryUi(id, newTitle);
                                }
                            } catch (renameErr) {
                                console.warn('[UpdateVisaDocCat] UI rename failed, falling back to reload', renameErr);
                                updated = false;
                            }
                            if (!updated) {
                                location.reload();
                                return;
                            }
                        } else {
                            alert(response.message || 'Failed to update category.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the category. Please try again.');
                    },
                    complete: function() {
                        $('.popuploader').hide();
                        $editBtn.data('updating', false).prop('disabled', false);
                    }
                });
            }
        });

        // ---- Update Nomination Document Category ----
        $(document).on('click', '.update-nomination-cat-title', function() {
            var $editBtn = $(this);
            if ($editBtn.data('updating')) {
                return;
            }
            var id = $editBtn.data('id');
            var currentTitle = ($editBtn.attr('data-title') || $editBtn.data('title') || '').toString().trim();
            if (!currentTitle) {
                currentTitle = $editBtn.closest('.button-container').find('.subtab6-button').first().text().trim();
            }
            var newTitle = prompt('Enter new title for the category:', currentTitle);
            if (newTitle) {
                newTitle = newTitle.trim();
            }
            if (newTitle) {
                var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.updateNominationCategory)
                    ? window.ClientDetailConfig.urls.updateNominationCategory
                    : '';
                if (!url) {
                    alert('Nomination category update URL is not configured.');
                    return;
                }
                $editBtn.data('updating', true).prop('disabled', true);
                $('.popuploader').show();
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id,
                        title: newTitle
                    },
                    success: function(response) {
                        if (response.status) {
                            alert(response.message);
                            var updated = false;
                            try {
                                if (typeof window.renameNominationDocCategoryUi === 'function') {
                                    updated = !!window.renameNominationDocCategoryUi(id, newTitle);
                                }
                            } catch (renameErr) {
                                console.warn('[UpdateNomDocCat] UI rename failed, falling back to reload', renameErr);
                                updated = false;
                            }
                            if (!updated) {
                                location.reload();
                                return;
                            }
                        } else {
                            alert(response.message || 'Failed to update category.');
                        }
                    },
                    error: function() {
                        alert('An error occurred while updating the category. Please try again.');
                    },
                    complete: function() {
                        $('.popuploader').hide();
                        $editBtn.data('updating', false).prop('disabled', false);
                    }
                });
            }
        });

        // ---- Delete Visa Document Category ----
        $(document).on('click', '.delete-visa-cat-title', function(e) {
            e.preventDefault();
            var $deleteBtn = $(this);
            if ($deleteBtn.data('deleting')) {
                return;
            }
            var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.deleteVisaCategory)
                ? window.ClientDetailConfig.urls.deleteVisaCategory
                : '';
            if (!url) {
                alert('Visa category delete is not configured.');
                return;
            }
            var id = $deleteBtn.data('id');
            var title = $deleteBtn.data('title') || 'this category';
            var warningMessage = '⚠️ WARNING: You are about to delete the visa category "' + title + '"\n\n' +
                'This action will permanently remove the category from the system.\n\n' +
                'Requirements:\n' +
                '• Category must have no active documents\n' +
                '• Any documents in Not Used Documents for this category will also be permanently removed\n' +
                '• Default categories cannot be deleted\n' +
                '• Only authorized staff can perform this action\n\n' +
                'This action CANNOT be undone!\n\n' +
                'Do you want to proceed?';
            if (confirm(warningMessage)) {
                var confirmMessage = '⚠️ FINAL CONFIRMATION\n\n' +
                    'Are you absolutely sure you want to delete "' + title + '"?\n\n' +
                    'This will permanently delete the category and any not-used documents linked to it.\n\n' +
                    'Click OK to delete or Cancel to abort.';
                if (confirm(confirmMessage)) {
                    $deleteBtn.data('deleting', true).prop('disabled', true);
                    $('.popuploader').show();
                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: id
                        },
                        success: function(response) {
                            if (response.status) {
                                alert('✓ Success: ' + response.message);
                                var removed = false;
                                try {
                                    if (typeof window.removeVisaDocCategoryUi === 'function') {
                                        removed = !!window.removeVisaDocCategoryUi(id);
                                    }
                                } catch (removeErr) {
                                    console.warn('[DeleteVisaDocCat] UI remove failed, falling back to reload', removeErr);
                                    removed = false;
                                }
                                if (!removed) {
                                    location.reload();
                                    return;
                                }
                            } else {
                                alert('✗ Error: ' + (response.message || 'Failed to delete category.'));
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'An error occurred while deleting the category.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert('✗ Error: ' + errorMsg);
                        },
                        complete: function() {
                            $('.popuploader').hide();
                            $deleteBtn.data('deleting', false).prop('disabled', false);
                        }
                    });
                }
            }
        });

        // ---- Delete Personal Document Category ----
        $(document).on('click', '.delete-personal-cat-title', function(e) {
            e.preventDefault();
            var $deleteBtn = $(this);
            if ($deleteBtn.data('deleting')) {
                return;
            }
            var id = $deleteBtn.data('id');
            var title = $deleteBtn.data('title') || 'this category';
            var warningMessage = '⚠️ WARNING: You are about to delete the category "' + title + '"\n\n' +
                'This action will permanently remove the category from the system.\n\n' +
                'Requirements:\n' +
                '• Category must have no active documents\n' +
                '• Any documents in Not Used Documents for this category will also be permanently removed\n' +
                '• Only authorized staff can perform this action\n\n' +
                'This action CANNOT be undone!\n\n' +
                'Do you want to proceed?';
            if (confirm(warningMessage)) {
                var confirmMessage = '⚠️ FINAL CONFIRMATION\n\n' +
                    'Are you absolutely sure you want to delete "' + title + '"?\n\n' +
                    'This will permanently delete the category and any not-used documents linked to it.\n\n' +
                    'Click OK to delete or Cancel to abort.';
                if (confirm(confirmMessage)) {
                    $deleteBtn.data('deleting', true).prop('disabled', true);
                    $('.popuploader').show();
                    $.ajax({
                        url: window.ClientDetailConfig.urls.deletePersonalCategory,
                        method: 'POST',
                        data: {
                            _token: $('meta[name="csrf-token"]').attr('content'),
                            id: id
                        },
                        success: function(response) {
                            if (response.status) {
                                alert('✓ Success: ' + response.message);
                                var removed = false;
                                try {
                                    if (typeof window.removePersonalDocCategoryUi === 'function') {
                                        removed = !!window.removePersonalDocCategoryUi(id);
                                    }
                                } catch (removeErr) {
                                    console.warn('[DeletePersDocCat] UI remove failed, falling back to reload', removeErr);
                                    removed = false;
                                }
                                if (!removed) {
                                    location.reload();
                                    return;
                                }
                            } else {
                                alert('✗ Error: ' + (response.message || 'Failed to delete category.'));
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'An error occurred while deleting the category.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert('✗ Error: ' + errorMsg);
                        },
                        complete: function() {
                            $('.popuploader').hide();
                            $deleteBtn.data('deleting', false).prop('disabled', false);
                        }
                    });
                }
            }
        });

        // ---- Rename Personal Document ----
        $(document).on('click', '.persdocumnetlist .renamedoc, .persdocumnetlist a.renamedoc', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.doc-row');
            if (parent.length === 0) {
                console.error('Document row not found');
                return false;
            }
            parent.data('current-html', parent.html());
            var opentime = parent.data('name');
            if (!opentime) {
                console.error('Document name not found');
                return false;
            }
            parent.empty().append.apply(parent, buildDocRenameField(opentime));
            return false;
        });

        $(document).on('click', '.persdocumnetlist .doc-rename-cancel', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.doc-row');
            if (parent.length === 0) {
                console.error('Document row not found for cancel');
                return false;
            }
            var hourid = parent.data('id');
            if (hourid) {
                parent.html(parent.data('current-html'));
            } else {
                parent.remove();
            }
            return false;
        });

        $(document).on('click', '.persdocumnetlist .doc-rename-save', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.doc-row');
            if (parent.length === 0) {
                console.error('Document row not found for save');
                return false;
            }
            parent.find('.opentime').removeClass('is-invalid');
            parent.find('.invalid-feedback').remove();
            var opentime = parent.find('.opentime').val();
            if (!opentime) {
                parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                parent.append($("<div class='invalid-feedback'>This field is required</div>"));
                return false;
            }
            $.ajax({
                type: "POST",
                dataType: 'json',
                data: {"_token": $('meta[name="csrf-token"]').attr('content'),"filename": opentime, "id": parent.data('id')},
                url: window.ClientDetailConfig.urls.renameDoc,
                success: function(result) {
                    var obj = (typeof result === 'object' && result !== null) ? result : (typeof result === 'string' && result.trim() ? (function(){ try { return JSON.parse(result); } catch(e) { return null; } })() : null);
                    if (!obj) return;
                    if (obj.status) {
                        var previewUrl = obj.fileurl;
                        var filetype = obj.filetype;
                        var folderName = obj.folder_name;
                        var fileName = obj.filename + '.' + obj.filetype;
                        parent.empty()
                            .data('id', obj.Id)
                            .data('name', opentime)
                            .append(
                                $('<a>', {
                                    href: 'javascript:void(0);',
                                    onclick: 'previewFile(\'' + filetype + '\', \'' + previewUrl + '\', \'' + folderName + '\')'
                                }).append(
                                    $(crmI('fas fa-file-image')),
                                    ' ',
                                    $('<span>').text(fileName)
                                )
                            );
                        if ($('#grid_'+obj.Id).length) {
                            $('#grid_'+obj.Id).html(fileName);
                        }
                        var $row = $(parent).closest('.drow');
                        var dropdownMenu = $row.find('.dropdown-menu');
                        dropdownMenu.find('.dropdown-item[href^="http"]').filter(function() {
                            return $(this).text().trim() === 'Preview';
                        }).attr('href', previewUrl);
                        // Update all download links in the row (hidden + dropdown) so context menu and download use new URL after rename
                        $row.find('.download-file').attr('data-filelink', previewUrl).attr('data-filename', fileName);
                        showRenameToast('success', obj.message || obj.data || 'Document saved successfully');
                    } else {
                        parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                        parent.append($('<div class="invalid-feedback">' + obj.message + '</div>'));
                        console.error('Failed to rename document:', obj.message);
                        showRenameToast('error', obj.message || 'Please try again');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                    parent.append($('<div class="invalid-feedback">An error occurred while saving</div>'));
                    showRenameToast('error', 'An error occurred while saving');
                }
            });
            return false;
        });

        // ---- Rename Visa Document ----
        $(document).on('click', '.migdocumnetlist1 .renamedoc', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.doc-row');
            if (parent.length === 0) {
                console.error('Visa document row not found');
                return false;
            }
            parent.data('current-html', parent.html());
            var opentime = parent.data('name');
            if (!opentime) {
                console.error('Visa document name not found');
                return false;
            }
            parent.empty().append.apply(parent, buildDocRenameField(opentime));
            return false;
        });

        $(document).on('click', '.migdocumnetlist1 .doc-rename-cancel', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.doc-row');
            if (parent.length === 0) {
                console.error('Visa document row not found for cancel');
                return false;
            }
            var hourid = parent.data('id');
            if (hourid) {
                parent.html(parent.data('current-html'));
            } else {
                parent.remove();
            }
            return false;
        });

        $(document).on('click', '.migdocumnetlist1 .doc-rename-save', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.doc-row');
            if (parent.length === 0) {
                console.error('Visa document row not found for save');
                return false;
            }
            parent.find('.opentime').removeClass('is-invalid');
            parent.find('.invalid-feedback').remove();
            var opentime = parent.find('.opentime').val();
            if (!opentime) {
                parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                parent.append($("<div class='invalid-feedback'>This field is required</div>"));
                return false;
            }
            $.ajax({
                type: "POST",
                dataType: 'json',
                data: {"_token": $('meta[name="csrf-token"]').attr('content'),"filename": opentime, "id": parent.data('id')},
                url: window.ClientDetailConfig.urls.renameDoc,
                success: function(result) {
                    var obj = (typeof result === 'object' && result !== null) ? result : (typeof result === 'string' && result.trim() ? (function(){ try { return JSON.parse(result); } catch(e) { return null; } })() : null);
                    if (!obj) return;
                    if (obj.status) {
                        var previewUrl = obj.fileurl;
                        var filetype = obj.filetype;
                        var folderName = obj.folder_name;
                        var fileName = obj.filename + '.' + obj.filetype;
                        parent.empty()
                            .data('id', obj.Id)
                            .data('name', opentime)
                            .append(
                                $('<a>', {
                                    href: 'javascript:void(0);',
                                    onclick: 'previewFile(\'' + filetype + '\', \'' + previewUrl + '\', \'' + folderName + '\')'
                                }).append(
                                    $(crmI('fas fa-file-image')),
                                    ' ',
                                    $('<span>').text(fileName)
                                )
                            );
                        if ($('#grid_'+obj.Id).length) {
                            $('#grid_'+obj.Id).html(fileName);
                        }
                        var $row = $(parent).closest('.drow');
                        var dropdownMenu = $row.find('.dropdown-menu');
                        dropdownMenu.find('.dropdown-item[href^="http"]').filter(function() {
                            return $(this).text().trim() === 'Preview';
                        }).attr('href', previewUrl);
                        // Update all download links in the row (hidden + dropdown) so context menu and download use new URL after rename
                        $row.find('.download-file').attr('data-filelink', previewUrl).attr('data-filename', fileName);
                        showRenameToast('success', obj.message || obj.data || 'Document saved successfully');
                    } else {
                        parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                        parent.append($('<div class="invalid-feedback">' + obj.message + '</div>'));
                        console.error('Failed to rename visa document:', obj.message);
                        showRenameToast('error', obj.message || 'Please try again');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                    parent.append($('<div class="invalid-feedback">An error occurred while saving</div>'));
                    showRenameToast('error', 'An error occurred while saving');
                }
            });
            return false;
        });

        // ---- Download Document ----
        $(document).on('click', '.download-file', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $this = $(this);
            // Read from current DOM attributes so updated values after rename are used (jQuery .data() caches and would return old URL)
            var filelink = $this.attr('data-filelink') || $this.data('filelink');
            var filename = $this.attr('data-filename') || $this.data('filename');
            if (!filelink || !filename) {
                console.error('Missing file info - filelink:', filelink, 'filename:', filename);
                alert('Missing file info. Please try again.');
                return false;
            }
            $this.html(crmI('fas fa-spinner fa-spin') + ' Downloading...');
            $this.prop('disabled', true);
            var form = $('<form>', {
                method: 'POST',
                action: window.ClientDetailConfig.urls.downloadDocument,
                target: '_blank',
                style: 'display: none'
            });
            var token = $('meta[name="csrf-token"]').attr('content');
            if (!token) {
                console.error('CSRF token not found');
                alert('Security token not found. Please refresh the page and try again.');
                $this.html('Download').prop('disabled', false);
                return false;
            }
            form.append($('<input>', { type: 'hidden', name: '_token', value: token }));
            form.append($('<input>', { type: 'hidden', name: 'filelink', value: filelink }));
            form.append($('<input>', { type: 'hidden', name: 'filename', value: filename }));
            $('body').append(form);
            try {
                form[0].submit();
                setTimeout(function() {
                    $this.html('Download').prop('disabled', false);
                }, 2000);
            } catch (error) {
                console.error('Error submitting form:', error);
                alert('Error initiating download. Please try again.');
                $this.html('Download').prop('disabled', false);
            }
            setTimeout(function() { form.remove(); }, 1000);
            return false;
        });

        // ---- Visual: make download-file and renamedoc clickable ----
        $('.download-file, .renamedoc').css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'z-index': '1000'
        });
        $(document).on('mouseenter', '.download-file, .renamedoc', function() { $(this).css('background-color', '#f8f9fa'); });
        $(document).on('mouseleave', '.download-file, .renamedoc', function() { $(this).css('background-color', ''); });
    });

    /**
     * Append a newly created personal document category tab + empty pane without page reload.
     * Returns true on success, false if the personal documents UI is missing / incomplete.
     */
    function escapePersonalDocHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function personalDocIcon(legacyClass, options) {
        if (typeof window.crmI === 'function') {
            return window.crmI(legacyClass, options || {});
        }
        return '';
    }

    window.appendPersonalDocCategoryUi = function(category) {
        var id = category && category.id;
        var title = category && category.title;
        if (!id || !title) {
            return false;
        }

        var $tab = $('#personaldocuments-tab');
        var $nav = $tab.find('nav.subtabs2').first();
        var $content = $tab.find('.subtab2-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        if ($nav.find('.subtab2-button[data-subtab2="' + id + '"]').length) {
            $nav.find('.subtab2-button').removeClass('active');
            $content.find('.subtab2-pane').removeClass('active');
            $nav.find('.subtab2-button[data-subtab2="' + id + '"]').addClass('active');
            $content.find('[id="' + id + '-subtab2"]').addClass('active');
            return true;
        }

        var safeTitle = escapePersonalDocHtml(title);
        var canDelete = !!category.can_delete;
        var editIcon = personalDocIcon('fa-edit');
        var trashIcon = personalDocIcon('fa-trash');
        var fileIcon = personalDocIcon('fa-file-alt');
        var plusIcon = personalDocIcon('fa-plus');
        var uploadIcon = personalDocIcon('fa-upload');
        var cloudIcon = personalDocIcon('fa-cloud-upload-alt', { style: 'font-size: 48px; color: #2563eb; margin-bottom: 15px;' });

        var actionsHtml = '<div class="action-buttons" style="display: none; position: absolute;">' +
            '<button type="button" class="btn btn-sm btn-warning update-personal-cat-title" data-id="' + id + '" data-title="' + safeTitle + '">' + editIcon + '</button>';
        if (canDelete) {
            actionsHtml += '<button type="button" class="btn btn-sm btn-danger delete-personal-cat-title" data-id="' + id + '" data-title="' + safeTitle + '">' + trashIcon + '</button>';
        }
        actionsHtml += '</div>';

        var $btnWrap = $(
            '<div style="display: inline-block; position: relative;" class="button-container">' +
                '<button type="button" class="subtab2-button" data-subtab2="' + id + '">' + safeTitle + '</button>' +
                actionsHtml +
            '</div>'
        );
        $nav.append($btnWrap);

        var paneHtml =
            '<div class="subtab2-pane" id="' + id + '-subtab2">' +
                '<div class="checklist-table-container" style="vertical-align: top; margin-top: 10px; width: 760px;">' +
                    '<div class="subtab2-header" style="margin-left: 10px;">' +
                        '<h3>' + fileIcon + ' ' + safeTitle + ' Documents</h3>' +
                        '<div style="display: flex; gap: 10px;">' +
                            '<button type="button" class="btn add-checklist-btn add_education_doc" data-type="personal" data-categoryid="' + id + '">' +
                                plusIcon + ' Add Checklist' +
                            '</button>' +
                            '<button type="button" class="btn btn-info bulk-upload-toggle-btn" data-categoryid="' + id + '" data-categoryname="' + safeTitle + '">' +
                                uploadIcon + ' Bulk Upload' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bulk-upload-dropzone-container" id="bulk-upload-' + id + '" style="display: none; margin: 15px 0; padding: 20px; border: 2px dashed #4a90e2; border-radius: 8px; background-color: #f8f9fa;">' +
                        '<div class="bulk-upload-dropzone" data-categoryid="' + id + '" style="text-align: center; padding: 30px; cursor: pointer;">' +
                            cloudIcon +
                            '<p style="font-size: 16px; color: #374151; margin-bottom: 10px;">' +
                                '<strong>Drag and drop files here</strong> or <strong>click to browse</strong>' +
                            '</p>' +
                            '<p style="font-size: 14px; color: #4b5563;">You can select multiple files at once</p>' +
                            '<input type="file" class="bulk-upload-file-input" data-categoryid="' + id + '" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">' +
                        '</div>' +
                        '<div class="bulk-upload-file-list" style="display: none; margin-top: 20px;">' +
                            '<h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count">0</span></h5>' +
                            '<div class="bulk-upload-files-container"></div>' +
                        '</div>' +
                    '</div>' +
                    '<table class="checklist-table">' +
                        '<thead><tr><th>Checklist</th><th>File Name</th><th></th></tr></thead>' +
                        '<tbody class="tdata persdocumnetlist documnetlist_' + id + '"></tbody>' +
                    '</table>' +
                '</div>' +
                '<div class="grid_data griddata_' + id + '"><div class="clearfix"></div></div>' +
                '<div class="preview-pane file-preview-container preview-container-' + id + '" style="display: inline; margin-top: 15px !important; width: 499px;">' +
                    '<p style="color: #374151;">Click on a file to preview it here.</p>' +
                '</div>' +
            '</div>';

        $content.append(paneHtml);

        // Match page-load behavior (sidebar-tabs.js): list view is default, grid is hidden.
        // Leaving .grid_data visible (width:100%) squeezes the checklist column in the flex row.
        var $newPane = $content.find('[id="' + id + '-subtab2"]');
        $newPane.find('.grid_data').hide();

        $nav.find('.subtab2-button').removeClass('active');
        $content.find('.subtab2-pane').removeClass('active');
        $btnWrap.find('.subtab2-button').addClass('active');
        $newPane.addClass('active');

        // Match detail-main.js page-load preview sizing so the new pane gets natural height.
        // Scoped to this pane only so existing category preview panes are untouched.
        var $newPreview = $newPane.find('.preview-pane.file-preview-container');
        if ($newPreview.length) {
            $newPreview.css({
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
            if (typeof window.adjustPreviewContainers === 'function') {
                window.adjustPreviewContainers();
            }
        }

        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons($tab[0]);
        }

        return true;
    };

    /**
     * Rename a personal document category tab + pane labels without page reload.
     * Returns true on success, false if UI could not be updated safely.
     */
    window.renamePersonalDocCategoryUi = function(categoryId, newTitle) {
        var id = categoryId;
        var title = (newTitle == null ? '' : String(newTitle)).trim();
        if (id == null || id === '' || !title) {
            return false;
        }

        var $tab = $('#personaldocuments-tab');
        var $nav = $tab.find('nav.subtabs2').first();
        var $content = $tab.find('.subtab2-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        var $btn = $nav.find('.subtab2-button[data-subtab2="' + id + '"]');
        var $pane = $content.find('[id="' + id + '-subtab2"]');
        if (!$btn.length || !$pane.length) {
            return false;
        }

        var $btnWrap = $btn.closest('.button-container');

        $btn.text(title);
        $btnWrap.find('.update-personal-cat-title').attr('data-title', title);
        $btnWrap.find('.delete-personal-cat-title').attr('data-title', title);

        var $h3 = $pane.find('.subtab2-header h3').first();
        if ($h3.length) {
            var $icon = $h3.children().first().clone();
            $h3.empty();
            if ($icon.length) {
                $h3.append($icon);
                $h3.append(document.createTextNode(' '));
            }
            $h3.append(document.createTextNode(title + ' Documents'));
        }

        $pane.find('.bulk-upload-toggle-btn[data-categoryid="' + id + '"]').attr('data-categoryname', title);
        $pane.find('[data-doccategory]').each(function() {
            $(this).attr('data-doccategory', title);
        });
        $pane.find('input[name="doccategory"]').val(title);

        return true;
    };

    /**
     * Remove a personal document category tab + pane without page reload.
     * Returns true on success, false if UI could not be updated safely.
     */
    window.removePersonalDocCategoryUi = function(categoryId) {
        var id = categoryId;
        if (id == null || id === '') {
            return false;
        }

        var $tab = $('#personaldocuments-tab');
        var $nav = $tab.find('nav.subtabs2').first();
        var $content = $tab.find('.subtab2-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        var $btn = $nav.find('.subtab2-button[data-subtab2="' + id + '"]');
        var $pane = $content.find('[id="' + id + '-subtab2"]');
        if (!$btn.length && !$pane.length) {
            // Already gone — treat as success so we do not force a reload.
            return true;
        }

        var wasActive = $btn.hasClass('active') || $pane.hasClass('active');
        var $btnWrap = $btn.closest('.button-container');

        if ($btnWrap.length) {
            $btnWrap.remove();
        } else if ($btn.length) {
            $btn.remove();
        }
        if ($pane.length) {
            $pane.remove();
        }

        if (wasActive) {
            var $fallbackBtn = $nav.find('.subtab2-button').first();
            if ($fallbackBtn.length) {
                $nav.find('.subtab2-button').removeClass('active');
                $content.find('.subtab2-pane').removeClass('active');
                $fallbackBtn.addClass('active');
                var fallbackId = $fallbackBtn.data('subtab2');
                $content.find('[id="' + fallbackId + '-subtab2"]').addClass('active');
            }
        }

        return true;
    };

    /**
     * Append a newly created visa document category tab + empty pane without page reload.
     */
    window.appendVisaDocCategoryUi = function(category) {
        var id = category && category.id;
        var title = category && category.title;
        if (!id || !title) {
            return false;
        }

        var $tab = $('#visadocuments-tab');
        var $nav = $tab.find('nav.subtabs6').first();
        var $content = $tab.find('.subtab6-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        if ($nav.find('.subtab6-button[data-subtab6="' + id + '"]').length) {
            $nav.find('.subtab6-button').removeClass('active');
            $content.find('.subtab6-pane').removeClass('active');
            $nav.find('.subtab6-button[data-subtab6="' + id + '"]').addClass('active');
            $content.find('[id="' + id + '-subtab6"]').addClass('active');
            return true;
        }

        var safeTitle = escapePersonalDocHtml(title);
        var canDelete = !!category.can_delete;
        var matterId = category.client_matter_id
            || (window.ClientDetailConfig && window.ClientDetailConfig.matterId)
            || $('#visaclientmatterid').val()
            || '';
        var editIcon = personalDocIcon('fa-edit');
        var trashIcon = personalDocIcon('fa-trash');
        var fileIcon = personalDocIcon('fa-file-alt');
        var plusIcon = personalDocIcon('fa-plus');
        var plusIconMr = personalDocIcon('fa-plus', { class: 'mr-2' });
        var uploadIcon = personalDocIcon('fa-upload');
        var cloudIcon = personalDocIcon('fa-cloud-upload-alt', { style: 'font-size: 48px; color: #2563eb; margin-bottom: 15px;' });

        var actionsHtml = '<div class="action-buttons" style="display: none; position: absolute;">' +
            '<button type="button" class="btn btn-sm btn-warning update-visa-cat-title" data-id="' + id + '" data-title="' + safeTitle + '">' + editIcon + '</button>';
        if (canDelete) {
            actionsHtml += '<button type="button" class="btn btn-sm btn-danger delete-visa-cat-title" data-id="' + id + '" data-title="' + safeTitle + '">' + trashIcon + '</button>';
        }
        actionsHtml += '</div>';

        var $btnWrap = $(
            '<div style="display: inline-block; position: relative;" class="button-container">' +
                '<button type="button" class="subtab6-button" data-subtab6="' + id + '">' + safeTitle + '</button>' +
                actionsHtml +
            '</div>'
        );
        $nav.append($btnWrap);

        var paneHtml =
            '<div class="subtab6-pane" id="' + id + '-subtab6">' +
                '<div class="checklist-table-container" style="vertical-align: top; margin-top: 10px; width: 760px; overflow: visible;">' +
                    '<div class="subtab6-header" style="margin-left: 10px;">' +
                        '<h3>' + fileIcon + ' ' + safeTitle + ' Documents</h3>' +
                        '<div style="display: flex; gap: 10px;">' +
                            '<button type="button" class="btn btn-primary btn-sm form956CreateForm inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200" data-form956-folder="' + id + '">' +
                                plusIconMr + ' Create Form 956' +
                            '</button>' +
                            '<button type="button" class="btn add-checklist-btn add_migration_doc" data-type="visa" data-categoryid="' + id + '">' +
                                plusIcon + ' Add Checklist' +
                            '</button>' +
                            '<button type="button" class="btn btn-info bulk-upload-toggle-btn-visa" data-categoryid="' + id + '" data-categoryname="' + safeTitle + '" data-matterid="' + escapePersonalDocHtml(matterId) + '">' +
                                uploadIcon + ' Bulk Upload' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bulk-upload-dropzone-container-visa" id="bulk-upload-visa-' + id + '" style="display: none; margin: 15px 0; padding: 20px; border: 2px dashed #4a90e2; border-radius: 8px; background-color: #f8f9fa;">' +
                        '<div class="bulk-upload-dropzone-visa" data-categoryid="' + id + '" data-matterid="' + escapePersonalDocHtml(matterId) + '" style="text-align: center; padding: 30px; cursor: pointer;">' +
                            cloudIcon +
                            '<p style="font-size: 16px; color: #374151; margin-bottom: 10px;">' +
                                '<strong>Drag and drop files here</strong> or <strong>click to browse</strong>' +
                            '</p>' +
                            '<p style="font-size: 14px; color: #4b5563;">You can select multiple files at once</p>' +
                            '<input type="file" class="bulk-upload-file-input-visa" data-categoryid="' + id + '" data-matterid="' + escapePersonalDocHtml(matterId) + '" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">' +
                        '</div>' +
                        '<div class="bulk-upload-file-list-visa" style="display: none; margin-top: 20px;">' +
                            '<h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count-visa">0</span></h5>' +
                            '<div class="bulk-upload-files-container-visa"></div>' +
                        '</div>' +
                    '</div>' +
                    '<table class="checklist-table">' +
                        '<thead><tr><th>Checklist</th><th>File Name</th><th></th></tr></thead>' +
                        '<tbody class="tdata migdocumnetlist1 migdocumnetlist_' + id + '"></tbody>' +
                    '</table>' +
                '</div>' +
                '<div class="grid_data miggriddata" style="display:none;"><div class="clearfix"></div></div>' +
                '<div class="preview-pane file-preview-container preview-container-migdocumnetlist" style="display: inline; margin-top: 15px !important; width: 499px;">' +
                    '<p style="color: #374151;">Click on a file to preview it here.</p>' +
                '</div>' +
            '</div>';

        $content.append(paneHtml);

        var $newPane = $content.find('[id="' + id + '-subtab6"]');
        $newPane.find('.grid_data').hide();

        $nav.find('.subtab6-button').removeClass('active');
        $content.find('.subtab6-pane').removeClass('active');
        $btnWrap.find('.subtab6-button').addClass('active');
        $newPane.addClass('active');

        var $newPreview = $newPane.find('.preview-pane.file-preview-container');
        if ($newPreview.length) {
            $newPreview.css({
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
            if (typeof window.adjustPreviewContainers === 'function') {
                window.adjustPreviewContainers();
            }
        }

        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons($tab[0]);
        }

        return true;
    };

    /**
     * Rename a visa document category tab + pane labels without page reload.
     */
    window.renameVisaDocCategoryUi = function(categoryId, newTitle) {
        var id = categoryId;
        var title = (newTitle == null ? '' : String(newTitle)).trim();
        if (id == null || id === '' || !title) {
            return false;
        }

        var $tab = $('#visadocuments-tab');
        var $nav = $tab.find('nav.subtabs6').first();
        var $content = $tab.find('.subtab6-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        var $btn = $nav.find('.subtab6-button[data-subtab6="' + id + '"]');
        var $pane = $content.find('[id="' + id + '-subtab6"]');
        if (!$btn.length || !$pane.length) {
            return false;
        }

        var $btnWrap = $btn.closest('.button-container');
        $btn.text(title);
        $btnWrap.find('.update-visa-cat-title').attr('data-title', title);
        $btnWrap.find('.delete-visa-cat-title').attr('data-title', title);

        var $h3 = $pane.find('.subtab6-header h3').first();
        if ($h3.length) {
            var $icon = $h3.children().first().clone();
            $h3.empty();
            if ($icon.length) {
                $h3.append($icon);
                $h3.append(document.createTextNode(' '));
            }
            $h3.append(document.createTextNode(title + ' Documents'));
        }

        $pane.find('.bulk-upload-toggle-btn-visa[data-categoryid="' + id + '"]').attr('data-categoryname', title);
        $pane.find('[data-doccategory]').each(function() {
            $(this).attr('data-doccategory', title);
        });
        $pane.find('input[name="doccategory"]').val(title);

        return true;
    };

    /**
     * Remove a visa document category tab + pane without page reload.
     */
    window.removeVisaDocCategoryUi = function(categoryId) {
        var id = categoryId;
        if (id == null || id === '') {
            return false;
        }

        var $tab = $('#visadocuments-tab');
        var $nav = $tab.find('nav.subtabs6').first();
        var $content = $tab.find('.subtab6-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        var $btn = $nav.find('.subtab6-button[data-subtab6="' + id + '"]');
        var $pane = $content.find('[id="' + id + '-subtab6"]');
        if (!$btn.length && !$pane.length) {
            return true;
        }

        var wasActive = $btn.hasClass('active') || $pane.hasClass('active');
        var $btnWrap = $btn.closest('.button-container');

        if ($btnWrap.length) {
            $btnWrap.remove();
        } else if ($btn.length) {
            $btn.remove();
        }
        if ($pane.length) {
            $pane.remove();
        }

        if (wasActive) {
            var $fallbackBtn = $nav.find('.subtab6-button').first();
            if ($fallbackBtn.length) {
                $nav.find('.subtab6-button').removeClass('active');
                $content.find('.subtab6-pane').removeClass('active');
                $fallbackBtn.addClass('active');
                var fallbackId = $fallbackBtn.data('subtab6');
                $content.find('[id="' + fallbackId + '-subtab6"]').addClass('active');
            }
        }

        return true;
    };

    /**
     * Append a newly created nomination document category tab + empty pane without page reload.
     * No delete action for nomination categories.
     */
    window.appendNominationDocCategoryUi = function(category) {
        var id = category && category.id;
        var title = category && category.title;
        if (!id || !title) {
            return false;
        }

        var $tab = $('#nominationdocuments-tab');
        var $nav = $tab.find('nav.subtabs6').first();
        var $content = $tab.find('.subtab6-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        if ($nav.find('.subtab6-button[data-subtab6="' + id + '"]').length) {
            $nav.find('.subtab6-button').removeClass('active');
            $content.find('.subtab6-pane').removeClass('active');
            $nav.find('.subtab6-button[data-subtab6="' + id + '"]').addClass('active');
            $content.find('[id="' + id + '-subtab6"]').addClass('active');
            return true;
        }

        var safeTitle = escapePersonalDocHtml(title);
        var matterId = category.client_matter_id
            || (window.ClientDetailConfig && window.ClientDetailConfig.matterId)
            || $('#nominationclientmatterid').val()
            || '';
        var editIcon = personalDocIcon('fa-edit');
        var fileIcon = personalDocIcon('fa-file-alt');
        var plusIcon = personalDocIcon('fa-plus');
        var uploadIcon = personalDocIcon('fa-upload');
        var cloudIcon = personalDocIcon('fa-cloud-upload-alt', { style: 'font-size: 48px; color: #2563eb; margin-bottom: 15px;' });

        var actionsHtml = '<div class="action-buttons" style="display: none; position: absolute;">' +
            '<button type="button" class="btn btn-sm btn-warning update-nomination-cat-title" data-id="' + id + '" data-title="' + safeTitle + '">' + editIcon + '</button>' +
            '</div>';

        var $btnWrap = $(
            '<div style="display: inline-block; position: relative;" class="button-container">' +
                '<button type="button" class="subtab6-button" data-subtab6="' + id + '">' + safeTitle + '</button>' +
                actionsHtml +
            '</div>'
        );
        $nav.append($btnWrap);

        var paneHtml =
            '<div class="subtab6-pane" id="' + id + '-subtab6">' +
                '<div class="checklist-table-container" style="vertical-align: top; margin-top: 10px; width: 760px; overflow: visible;">' +
                    '<div class="subtab6-header" style="margin-left: 10px;">' +
                        '<h3>' + fileIcon + ' ' + safeTitle + ' Documents</h3>' +
                        '<div style="display: flex; gap: 10px;">' +
                            '<button type="button" class="btn add-checklist-btn add_nomination_doc" data-type="nomination" data-categoryid="' + id + '">' +
                                plusIcon + ' Add Checklist' +
                            '</button>' +
                            '<button type="button" class="btn btn-info bulk-upload-toggle-btn-nomination" data-categoryid="' + id + '" data-categoryname="' + safeTitle + '" data-matterid="' + escapePersonalDocHtml(matterId) + '">' +
                                uploadIcon + ' Bulk Upload' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="bulk-upload-dropzone-container-nomination" id="bulk-upload-nomination-' + id + '" style="display: none; margin: 15px 0; padding: 20px; border: 2px dashed #4a90e2; border-radius: 8px; background-color: #f8f9fa;">' +
                        '<div class="bulk-upload-dropzone-nomination" data-categoryid="' + id + '" data-matterid="' + escapePersonalDocHtml(matterId) + '" style="text-align: center; padding: 30px; cursor: pointer;">' +
                            cloudIcon +
                            '<p style="font-size: 16px; color: #374151; margin-bottom: 10px;">' +
                                '<strong>Drag and drop files here</strong> or <strong>click to browse</strong>' +
                            '</p>' +
                            '<p style="font-size: 14px; color: #4b5563;">You can select multiple files at once</p>' +
                            '<input type="file" class="bulk-upload-file-input-nomination" data-categoryid="' + id + '" data-matterid="' + escapePersonalDocHtml(matterId) + '" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">' +
                        '</div>' +
                        '<div class="bulk-upload-file-list-nomination" style="display: none; margin-top: 20px;">' +
                            '<h5 style="margin-bottom: 15px;">Files Selected: <span class="file-count-nomination">0</span></h5>' +
                            '<div class="bulk-upload-files-container-nomination"></div>' +
                        '</div>' +
                    '</div>' +
                    '<table class="checklist-table">' +
                        '<thead><tr><th>Checklist</th><th>File Name</th><th></th></tr></thead>' +
                        '<tbody class="tdata migdocumnetlist1 migdocumnetlist_' + id + '"></tbody>' +
                    '</table>' +
                '</div>' +
                '<div class="grid_data nomgriddata" style="display:none;"><div class="clearfix"></div></div>' +
                '<div class="preview-pane file-preview-container preview-container-nomdocumnetlist" style="display: inline; margin-top: 15px !important; width: 499px;">' +
                    '<p style="color: #374151;">Click on a file to preview it here.</p>' +
                '</div>' +
            '</div>';

        $content.append(paneHtml);

        var $newPane = $content.find('[id="' + id + '-subtab6"]');
        $newPane.find('.grid_data').hide();

        $nav.find('.subtab6-button').removeClass('active');
        $content.find('.subtab6-pane').removeClass('active');
        $btnWrap.find('.subtab6-button').addClass('active');
        $newPane.addClass('active');

        var $newPreview = $newPane.find('.preview-pane.file-preview-container');
        if ($newPreview.length) {
            $newPreview.css({
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
            if (typeof window.adjustPreviewContainers === 'function') {
                window.adjustPreviewContainers();
            }
        }

        if (typeof refreshLucideIcons === 'function') {
            refreshLucideIcons($tab[0]);
        }

        return true;
    };

    /**
     * Rename a nomination document category tab + pane labels without page reload.
     */
    window.renameNominationDocCategoryUi = function(categoryId, newTitle) {
        var id = categoryId;
        var title = (newTitle == null ? '' : String(newTitle)).trim();
        if (id == null || id === '' || !title) {
            return false;
        }

        var $tab = $('#nominationdocuments-tab');
        var $nav = $tab.find('nav.subtabs6').first();
        var $content = $tab.find('.subtab6-content').first();
        if (!$tab.length || !$nav.length || !$content.length) {
            return false;
        }

        var $btn = $nav.find('.subtab6-button[data-subtab6="' + id + '"]');
        var $pane = $content.find('[id="' + id + '-subtab6"]');
        if (!$btn.length || !$pane.length) {
            return false;
        }

        var $btnWrap = $btn.closest('.button-container');
        $btn.text(title);
        $btnWrap.find('.update-nomination-cat-title').attr('data-title', title);

        var $h3 = $pane.find('.subtab6-header h3').first();
        if ($h3.length) {
            var $icon = $h3.children().first().clone();
            $h3.empty();
            if ($icon.length) {
                $h3.append($icon);
                $h3.append(document.createTextNode(' '));
            }
            $h3.append(document.createTextNode(title + ' Documents'));
        }

        $pane.find('.bulk-upload-toggle-btn-nomination[data-categoryid="' + id + '"]').attr('data-categoryname', title);
        $pane.find('[data-doccategory]').each(function() {
            $(this).attr('data-doccategory', title);
        });
        $pane.find('input[name="doccategory"]').val(title);

        return true;
    };

})(typeof jQuery !== 'undefined' ? jQuery : null);
