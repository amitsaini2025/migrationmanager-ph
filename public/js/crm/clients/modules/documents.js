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
            var id = $(this).data('id');
            var newTitle = prompt('Enter new title for the category:');
            if (newTitle) {
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
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    }
                });
            }
        });

        // ---- Update Visa Document Category ----
        $(document).on('click', '.update-visa-cat-title', function() {
            var id = $(this).data('id');
            var newTitle = prompt('Enter new title for the category:');
            if (newTitle) {
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
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    }
                });
            }
        });

        // ---- Update Nomination Document Category ----
        $(document).on('click', '.update-nomination-cat-title', function() {
            var id = $(this).data('id');
            var newTitle = prompt('Enter new title for the category:');
            if (newTitle) {
                var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.updateNominationCategory)
                    ? window.ClientDetailConfig.urls.updateNominationCategory
                    : '';
                if (!url) {
                    alert('Nomination category update URL is not configured.');
                    return;
                }
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
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    }
                });
            }
        });

        // ---- Delete Visa Document Category ----
        $(document).on('click', '.delete-visa-cat-title', function(e) {
            e.preventDefault();
            var url = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.deleteVisaCategory)
                ? window.ClientDetailConfig.urls.deleteVisaCategory
                : '';
            if (!url) {
                alert('Visa category delete is not configured.');
                return;
            }
            var id = $(this).data('id');
            var title = $(this).data('title') || 'this category';
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
                                location.reload();
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
                        }
                    });
                }
            }
        });

        // ---- Delete Personal Document Category ----
        $(document).on('click', '.delete-personal-cat-title', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var title = $(this).data('title') || 'this category';
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
                                location.reload();
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

})(typeof jQuery !== 'undefined' ? jQuery : null);
