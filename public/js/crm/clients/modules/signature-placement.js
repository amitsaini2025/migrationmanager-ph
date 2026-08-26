/**
 * Shared signature-field placement modal.
 * Loaded on client and company detail so Visa Documents / Nomination
 * can open it without the Checklists tab fragment.
 * Requires: jQuery. Uses: crmI (optional)
 */
(function() {
    'use strict';

    if (window.__signaturePlacementUiBound) {
        return;
    }

    function liveJquery() {
        return window.jQuery;
    }

    function showSignatureModal($modal) {
        if (!$modal || !$modal.length) {
            return false;
        }
        var $jq = liveJquery();
        if ($jq && typeof $jq.fn.modal === 'function') {
            $jq($modal.get()).modal('show');
            return true;
        }
        if (typeof $modal.modal === 'function') {
            $modal.modal('show');
            return true;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal && $modal[0]) {
            bootstrap.Modal.getOrCreateInstance($modal[0]).show();
            return true;
        }
        return false;
    }

    function hideSignatureModal($modal) {
        if (!$modal || !$modal.length) {
            return false;
        }
        var $jq = liveJquery();
        if ($jq && typeof $jq.fn.modal === 'function') {
            $jq($modal.get()).modal('hide');
            return true;
        }
        if (typeof $modal.modal === 'function') {
            $modal.modal('hide');
            return true;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal && $modal[0]) {
            var instance = bootstrap.Modal.getInstance($modal[0]);
            if (instance) {
                instance.hide();
            }
            return true;
        }
        return false;
    }

    function saveButtonLabel() {
        var icon = (typeof window.crmI === 'function')
            ? window.crmI('fas fa-save', { class: 'mr-1' })
            : '';
        return icon + 'Save Signature Locations';
    }

    var sigState = {
        documentId: null,
        pdfPages: 1,
        pagesDimensions: {},
        pdfWidthMM: 210,
        pdfHeightMM: 297,
        currentPage: 1,
        signatureFields: [],
        selectedFieldIndex: -1,
        isDragging: false,
        dragFieldIndex: -1,
        dragOffsetX: 0,
        dragOffsetY: 0
    };

    function openSignaturePlacementModal(docId) {
        var $ = liveJquery();
        if (!$ || !docId) {
            return;
        }
        if (!$('#signaturePlacementModal').length) {
            return;
        }
        sigState.documentId = docId;
        sigState.signatureFields = [];
        sigState.currentPage = 1;
        sigState.selectedFieldIndex = -1;
        $('#signaturePlacementModal').removeData('lastSaveSource');
        $('#signaturePlacementModal').removeData('skipReloadOnHide');
        showSignatureModal($('#signaturePlacementModal'));
        $('#signature-placement-loading').show();
        $('#signature-placement-content').hide();
        $('#signature-placement-error').hide();

        $.ajax({
            url: '/documents/' + docId + '/signature-placement-data',
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        }).done(function(data) {
            if (!data.success) {
                $('#signature-placement-loading').hide();
                $('#signature-placement-error').text(data.message || 'Failed to load document.').show();
                return;
            }
            sigState.pdfPages = data.pdfPages || 1;
            sigState.pagesDimensions = data.pagesDimensions || {};
            sigState.pdfWidthMM = data.pdfWidthMM || 210;
            sigState.pdfHeightMM = data.pdfHeightMM || 297;
            sigState.signatureFields = (data.existingFields || []).map(function(f, i) {
                var toDecimal = function(v) { return (v > 1 ? v / 100 : v) || 0; };
                return {
                    id: Date.now() + i,
                    page_number: f.page_number,
                    x_percent: toDecimal(f.x_percent),
                    y_percent: toDecimal(f.y_percent),
                    w_percent: Math.max(0.05, toDecimal(f.w_percent)),
                    h_percent: Math.max(0.03, toDecimal(f.h_percent))
                };
            });

            $('#signature-placement-loading').hide();
            $('#signature-placement-content').show();
            $('#sig-preview-image').attr('src', '/debug-pdf-page/' + docId + '/1');
            if (sigState.pdfPages > 1) {
                $('#signature-page-nav').show();
                $('#sig-prev-page').prop('disabled', true);
                $('#sig-next-page').prop('disabled', sigState.pdfPages <= 1);
            } else {
                $('#signature-page-nav').hide();
            }
            sigState.currentPage = 1;
            updateSigPageInfo();
            updateSigForm();
            updateSigPreview();
            bindSigEvents();
        }).fail(function(xhr) {
            $('#signature-placement-loading').hide();
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load document.';
            $('#signature-placement-error').text(msg).show();
        });
    }

    function updateSigPageInfo() {
        var $ = liveJquery();
        $('#sig-page-info').text('Page ' + sigState.currentPage + ' of ' + sigState.pdfPages);
        $('#sig-prev-page').prop('disabled', sigState.currentPage <= 1);
        $('#sig-next-page').prop('disabled', sigState.currentPage >= sigState.pdfPages);
    }

    function getSigDisplayDims() {
        var $ = liveJquery();
        var $img = $('#sig-preview-image');
        return { width: $img.length ? $img[0].clientWidth : 0, height: $img.length ? $img[0].clientHeight : 0 };
    }

    function sigSwitchPage(p) {
        var $ = liveJquery();
        if (p < 1 || p > sigState.pdfPages) {
            return;
        }
        sigState.currentPage = p;
        $('#sig-preview-image').attr('src', '/debug-pdf-page/' + sigState.documentId + '/' + p);
        updateSigPageInfo();
        updateSigPreview();
    }

    function sigAddField(page, x, y) {
        var dims = getSigDisplayDims();
        var w = 150, h = 75;
        var xP = dims.width ? x / dims.width : 0;
        var yP = dims.height ? y / dims.height : 0;
        var wP = dims.width ? w / dims.width : 0.2;
        var hP = dims.height ? h / dims.height : 0.1;
        sigState.signatureFields.push({
            id: Date.now(),
            page_number: page,
            x_percent: xP,
            y_percent: yP,
            w_percent: wP,
            h_percent: hP
        });
        updateSigForm();
        updateSigPreview();
        sigState.selectedFieldIndex = sigState.signatureFields.length - 1;
    }

    function updateSigForm() {
        var $ = liveJquery();
        var html = '';
        sigState.signatureFields.forEach(function(f, i) {
            var rowClass = 'd-flex justify-content-between align-items-center mb-2 p-2 border rounded sig-field-row';
            if (i === sigState.selectedFieldIndex) {
                rowClass += ' sig-field-row-selected';
            }
            var editBtnClass = (i === sigState.selectedFieldIndex)
                ? 'btn btn-primary btn-sm sig-edit-field mr-1'
                : 'btn btn-outline-secondary btn-sm sig-edit-field mr-1';
            html += '<div class="' + rowClass + '" data-index="' + i + '">';
            html += '<span class="small">Signature ' + (i + 1) + ' (Pg ' + f.page_number + ')</span>';
            html += '<div><button type="button" class="' + editBtnClass + '" data-index="' + i + '">Edit</button>';
            html += '<button type="button" class="btn btn-outline-danger btn-sm sig-delete-field" data-index="' + i + '">Delete</button></div></div>';
        });
        $('#sig-fields-container').html(html || '<small class="text-muted">No fields. Click on the document or Add Signature Field.</small>');
    }

    function updateSigPreview() {
        var $ = liveJquery();
        var $container = $('#sig-fields-preview');
        $container.empty();
        var dims = getSigDisplayDims();
        sigState.signatureFields.forEach(function(f, i) {
            if (f.page_number !== sigState.currentPage) {
                return;
            }
            var previewClass = 'sig-field-preview';
            if (i === sigState.selectedFieldIndex) {
                previewClass += ' sig-field-preview-selected';
            }
            var $el = $('<div class="' + previewClass + '" data-index="' + i + '"></div>');
            $el.css({
                left: (f.x_percent * dims.width) + 'px',
                top: (f.y_percent * dims.height) + 'px',
                width: (f.w_percent * dims.width) + 'px',
                height: (f.h_percent * dims.height) + 'px'
            });
            $el.html('<span class="sig-field-label">Signature ' + (i + 1) + '</span>');
            $container.append($el);
        });
    }

    function bindSigEvents() {
        var $ = liveJquery();
        $('#sig-preview-container').off('click.sig').on('click.sig', function(e) {
            if ($(e.target).is('#sig-preview-image')) {
                var rect = e.target.getBoundingClientRect();
                sigAddField(sigState.currentPage, e.clientX - rect.left, e.clientY - rect.top);
            }
        });
        $('#sig-add-field').off('click.sig').on('click.sig', function() {
            var dims = getSigDisplayDims();
            sigAddField(sigState.currentPage, dims.width / 2, dims.height / 2);
        });
        $('#sig-prev-page').off('click.sig').on('click.sig', function() { sigSwitchPage(sigState.currentPage - 1); });
        $('#sig-next-page').off('click.sig').on('click.sig', function() { sigSwitchPage(sigState.currentPage + 1); });
        $(document).off('click.sig', '.sig-delete-field').on('click.sig', '.sig-delete-field', function(e) {
            e.preventDefault();
            var i = parseInt($(this).data('index'));
            if (!isNaN(i) && confirm('Delete this signature field?')) {
                sigState.signatureFields.splice(i, 1);
                sigState.selectedFieldIndex = -1;
                updateSigForm();
                updateSigPreview();
            }
        });
        $(document).off('click.sig', '.sig-edit-field').on('click.sig', '.sig-edit-field', function(e) {
            e.preventDefault();
            var i = parseInt($(this).data('index'));
            if (!isNaN(i) && sigState.signatureFields[i]) {
                sigState.selectedFieldIndex = i;
                if (sigState.signatureFields[i].page_number !== sigState.currentPage) {
                    sigSwitchPage(sigState.signatureFields[i].page_number);
                }
                updateSigForm();
                updateSigPreview();
            }
        });
        $('#sig-preview-image').off('load.sig').on('load.sig', function() { updateSigPreview(); });

        function getSigPointerCoords(e) {
            var clientX = e.clientX, clientY = e.clientY, offsetX = e.offsetX, offsetY = e.offsetY;
            if (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length) {
                var t = e.originalEvent.touches[0];
                clientX = t.clientX;
                clientY = t.clientY;
                var target = e.target;
                var tr = target.getBoundingClientRect();
                offsetX = t.clientX - tr.left;
                offsetY = t.clientY - tr.top;
            }
            return { clientX: clientX, clientY: clientY, offsetX: offsetX, offsetY: offsetY };
        }
        $(document).off('mousedown.sig touchstart.sig', '.sig-field-preview').on('mousedown.sig touchstart.sig', '.sig-field-preview', function(e) {
            e.preventDefault();
            var coords = (e.type === 'touchstart')
                ? getSigPointerCoords(e)
                : { clientX: e.clientX, clientY: e.clientY, offsetX: e.offsetX, offsetY: e.offsetY };
            var i = parseInt($(this).data('index'));
            if (isNaN(i) || !sigState.signatureFields[i]) {
                return;
            }
            sigState.isDragging = true;
            sigState.dragFieldIndex = i;
            sigState.dragOffsetX = coords.offsetX;
            sigState.dragOffsetY = coords.offsetY;
            $(this).addClass('dragging');
        });
        $(document).off('mousemove.sig touchmove.sig').on('mousemove.sig touchmove.sig', function(e) {
            if (!sigState.isDragging || sigState.dragFieldIndex < 0) {
                return;
            }
            if (e.type === 'touchmove') {
                e.preventDefault();
            }
            var coords = (e.type === 'touchmove')
                ? getSigPointerCoords(e)
                : { clientX: e.clientX, clientY: e.clientY };
            var f = sigState.signatureFields[sigState.dragFieldIndex];
            if (!f || f.page_number !== sigState.currentPage) {
                return;
            }
            var $img = $('#sig-preview-image');
            if (!$img.length) {
                return;
            }
            var rect = $img[0].getBoundingClientRect();
            var w = rect.width, h = rect.height;
            if (!w || !h) {
                return;
            }
            var localX = coords.clientX - rect.left - sigState.dragOffsetX;
            var localY = coords.clientY - rect.top - sigState.dragOffsetY;
            var maxX = w * (1 - f.w_percent);
            var maxY = h * (1 - f.h_percent);
            localX = Math.max(0, Math.min(localX, maxX));
            localY = Math.max(0, Math.min(localY, maxY));
            f.x_percent = localX / w;
            f.y_percent = localY / h;
            var $el = $('#sig-fields-preview .sig-field-preview[data-index="' + sigState.dragFieldIndex + '"]');
            if ($el.length) {
                $el.css({ left: (f.x_percent * w) + 'px', top: (f.y_percent * h) + 'px' }).addClass('dragging');
            } else {
                updateSigPreview();
                $('#sig-fields-preview .sig-field-preview[data-index="' + sigState.dragFieldIndex + '"]').addClass('dragging');
            }
        });
        $(document).off('mouseup.sig touchend.sig touchcancel.sig').on('mouseup.sig touchend.sig touchcancel.sig', function() {
            if (sigState.isDragging) {
                sigState.isDragging = false;
                sigState.dragFieldIndex = -1;
                $('.sig-field-preview').removeClass('dragging');
            }
        });

        $('#sig-save-btn').off('click.sig').on('click.sig', function() {
            if (sigState.signatureFields.length === 0) {
                alert('Please add at least one signature field.');
                return;
            }
            var signatures = sigState.signatureFields.map(function(f) {
                return {
                    page_number: parseInt(f.page_number, 10),
                    x_percent: parseFloat((f.x_percent * 100).toFixed(2)),
                    y_percent: parseFloat((f.y_percent * 100).toFixed(2)),
                    w_percent: parseFloat((f.w_percent * 100).toFixed(2)),
                    h_percent: parseFloat((f.h_percent * 100).toFixed(2))
                };
            });
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span>Saving...');
            var postData = {
                _method: 'PATCH',
                _token: ($('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()),
                signatures: signatures
            };
            $.ajax({
                url: '/documents/' + sigState.documentId,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(postData),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function(resp) {
                if (resp && resp.source) {
                    $('#signaturePlacementModal').data('lastSaveSource', resp.source);
                } else {
                    $('#signaturePlacementModal').removeData('lastSaveSource');
                }

                if (resp && resp.success && resp.redirect_url) {
                    $('#signaturePlacementModal').data('skipReloadOnHide', true);
                    hideSignatureModal($('#signaturePlacementModal'));
                    alert(resp.message || 'Signature fields saved. The signing link is now available.');
                    window.location.href = resp.redirect_url;
                    return;
                }

                hideSignatureModal($('#signaturePlacementModal'));
                if (resp && resp.success) {
                    alert(resp.message || 'Signature fields saved. The signing link is now available.');
                } else {
                    alert((resp && resp.message) ? resp.message : 'An error occurred.');
                }
            }).fail(function(xhr) {
                var msg = 'Failed to save signature fields.';
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseJSON.errors) {
                        msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
                    }
                } else if (xhr.status === 419) {
                    msg = 'Session expired. Please refresh the page and try again.';
                } else if (xhr.responseText && xhr.responseText.length < 200) {
                    msg = xhr.responseText;
                }
                alert(msg);
            }).always(function() {
                $btn.prop('disabled', false).html(saveButtonLabel());
            });
        });
    }

    function bindSignaturePlacementUi() {
        var $ = liveJquery();
        if (!$ || window.__signaturePlacementUiBound) {
            return;
        }
        if (!$('#signaturePlacementModal').length) {
            return;
        }

        window.__signaturePlacementUiBound = true;

        $(document).off('click.signaturePlacementUi', '.btn-place-signature-fields')
            .on('click.signaturePlacementUi', '.btn-place-signature-fields', function() {
                openSignaturePlacementModal($(this).data('document-id'));
            });

        $(document).off('openSignaturePlacementModal.signaturePlacementUi')
            .on('openSignaturePlacementModal.signaturePlacementUi', function(e, data) {
                if (data && data.documentId) {
                    openSignaturePlacementModal(data.documentId);
                }
            });

        $('#signaturePlacementModal').off('hidden.bs.modal.signaturePlacementUi')
            .on('hidden.bs.modal.signaturePlacementUi', function() {
                sigState.isDragging = false;
                sigState.dragFieldIndex = -1;
                $('.sig-field-preview').removeClass('dragging');
                $('#sig-preview-image').attr('src', '');

                if ($('#signaturePlacementModal').data('skipReloadOnHide')) {
                    $('#signaturePlacementModal').removeData('skipReloadOnHide');
                    $('#signaturePlacementModal').removeData('lastSaveSource');
                    return;
                }

                var source = $('#signaturePlacementModal').data('lastSaveSource');
                var tab = null;
                if (source === 'visa_documents') {
                    tab = 'visadocuments';
                } else if (source === 'nomination_documents') {
                    tab = 'nominationdocuments';
                } else if (source) {
                    tab = 'checklists';
                } else {
                    tab = $('.client-nav-button.active').data('tab')
                        || localStorage.getItem('activeTab')
                        || null;
                    if (!tab) {
                        var parts = (window.location.pathname || '').split('/').filter(Boolean);
                        var last = parts.length ? parts[parts.length - 1] : '';
                        if (['visadocuments', 'nominationdocuments', 'checklists', 'personaldocuments'].indexOf(last) !== -1) {
                            tab = last;
                        }
                    }
                    if (!tab) {
                        tab = 'checklists';
                    }
                }
                localStorage.setItem('activeTab', tab);
                $('#signaturePlacementModal').removeData('lastSaveSource');
                location.reload();
            });
    }

    function startSignaturePlacementBoot() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        bindSignaturePlacementUi();
    }

    window.openSignaturePlacementModal = openSignaturePlacementModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startSignaturePlacementBoot);
    } else {
        startSignaturePlacementBoot();
    }
})();
