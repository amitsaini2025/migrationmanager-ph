/**
 * Checklist module - Application checklist, rename, upload, edit, delete
 * Extracted from detail-main.js - Phase 3c refactoring.
 * Requires: jQuery, ClientDetailConfig
 */
(function($) {
    'use strict';
    if (!$) return;

    // uploadFormData, file_explorer, .openfileupload, .opendocnote, #ddArea handlers REMOVED - workflow checklist upload flow dead

    $(document).ready(function() {
        // ---- Application checklist: open modal ----
        // NOTE: .openchecklist handler moved to detail-main.js (same pattern as Add Personal/Visa Checklist)

        // ---- Due date toggle ----
        $(document).delegate('.due_date_sec a.due_date_btn', 'click', function(){
            $('.due_date_sec .due_date_col').show();
            $(this).hide();
            $('.checklistdue_date').val(1);
        });

        $(document).delegate('.remove_col a.remove_btn', 'click', function(){
            $('.due_date_sec .due_date_col').hide();
            $('.due_date_sec a.due_date_btn').show();
            $('.checklistdue_date').val(0);
        });

        // ---- Rename checklist: Personal documents ----
        $(document).on('click', '.persdocumnetlist .renamechecklist, .persdocumnetlist a.renamechecklist', function(e){
            e.preventDefault();
            e.stopPropagation();
            var $parent = $(this).closest('.drow').find('.personalchecklist-row');
            if ($parent.length === 0) {
                console.error('Personal checklist row not found');
                return false;
            }
            var opentime = $parent.data('personalchecklistname');
            if (!opentime) {
                console.error('Personal checklist name not found');
                return false;
            }
            $parent.data('current-html', $parent.html());
            $parent.empty().append(
                $('<input style="display: inline-block;width: auto;" class="form-control opentime" type="text">').prop('value', opentime),
                $('<button class="btn btn-personalprimary btn-sm mb-1">' + crmI('fas fa-check') + '</button>'),
                $('<button class="btn btn-personaldanger btn-sm mb-1">' + crmI('far fa-trash-alt') + '</button>')
            );
            return false;
        });

        $(document).on('click', '.persdocumnetlist .btn-personaldanger', function(e){
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.personalchecklist-row');
            if (parent.length === 0) {
                console.error('Personal checklist row not found for cancel');
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

        $(document).on('click', '.persdocumnetlist .btn-personalprimary', function(e){
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.personalchecklist-row');
            if (parent.length === 0) {
                console.error('Personal checklist row not found for save');
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
                data: {"_token": $('meta[name="csrf-token"]').attr('content'),"checklist": opentime, "id": parent.data('id')},
                url: window.ClientDetailConfig.urls.renameChecklistDoc,
                success: function(result){
                    var obj = (typeof result === 'object' && result !== null) ? result : (typeof result === 'string' && result.trim() ? (function(){ try { return JSON.parse(result); } catch(e) { return null; } })() : null);
                    if (!obj) return;
                    if (obj.status) {
                        parent.empty()
                            .data('id', obj.Id)
                            .data('personalchecklistname', obj.checklist)
                            .html(obj.html || '<span style="flex: 1;">' + obj.checklist + '</span>');
                        if ($('#grid_'+obj.Id).length) {
                            $('#grid_'+obj.Id).html(obj.checklist);
                        }
                    } else {
                        parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                        parent.append($('<div class="invalid-feedback">' + obj.message + '</div>'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                    parent.append($('<div class="invalid-feedback">An error occurred while saving</div>'));
                }
            });
            return false;
        });

        // ---- Rename checklist: Visa documents ----
        $(document).on('click', '.migdocumnetlist1 .renamechecklist', function(e){
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.visachecklist-row');
            if (parent.length === 0) {
                console.error('Visa checklist row not found');
                return false;
            }
            var opentime = parent.data('visachecklistname');
            if (!opentime) return false;
            parent.data('current-html', parent.html());
            parent.empty().append(
                $('<input style="display: inline-block;width: auto;" class="form-control opentime" type="text">').prop('value', opentime),
                $('<button class="btn btn-personalprimary btn-sm mb-1">' + crmI('fas fa-check') + '</button>'),
                $('<button class="btn btn-personaldanger btn-sm mb-1">' + crmI('far fa-trash-alt') + '</button>')
            );
            return false;
        });

        $(document).on('click', '.migdocumnetlist1 .btn-visadanger', function(e){
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.visachecklist-row');
            if (parent.length === 0) return false;
            var hourid = parent.data('id');
            if (hourid) {
                parent.html(parent.data('current-html'));
            } else {
                parent.remove();
            }
            return false;
        });

        $(document).on('click', '.migdocumnetlist1 .btn-visaprimary', function(e){
            e.preventDefault();
            e.stopPropagation();
            var parent = $(this).closest('.drow').find('.visachecklist-row');
            if (parent.length === 0) return false;
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
                data: {"_token": $('meta[name="csrf-token"]').attr('content'),"checklist": opentime, "id": parent.data('id')},
                url: window.ClientDetailConfig.urls.renameChecklistDoc,
                success: function(result){
                    var obj = (typeof result === 'object' && result !== null) ? result : (typeof result === 'string' && result.trim() ? (function(){ try { return JSON.parse(result); } catch(e) { return null; } })() : null);
                    if (!obj) return;
                    if (obj.status) {
                        parent.empty()
                            .data('id', obj.Id)
                            .data('visachecklistname', obj.checklist)
                            .html(obj.html || '<span style="flex: 1;">' + obj.checklist + '</span>');
                        if ($('#grid_'+obj.Id).length) {
                            $('#grid_'+obj.Id).html(obj.checklist);
                        }
                    } else {
                        parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                        parent.append($('<div class="invalid-feedback">' + obj.message + '</div>'));
                        console.error('Failed to rename visa checklist:', obj.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    parent.find('.opentime').addClass('is-invalid').css({ 'background-image': 'none', 'padding-right': '0.75em' });
                    parent.append($('<div class="invalid-feedback">An error occurred while saving</div>'));
                }
            });
            return false;
        });

        // ---- Edit checklist (triggers inline rename UI) ----
        $(document).on('click', '.edit-checklist-btn', function(e){
            e.preventDefault();
            e.stopPropagation();
            var $drow = $(this).closest('.drow');
            var $parent = $drow.find('.personalchecklist-row').length ? $drow.find('.personalchecklist-row') : $drow.find('.visachecklist-row');
            var isVisa = $parent.hasClass('visachecklist-row');
            if ($parent.length === 0) {
                console.error('Checklist row not found');
                return false;
            }
            $parent.data('current-html', $parent.html());
            var currentChecklist = $parent.data('personalchecklistname') || $parent.data('visachecklistname') || $(this).data('checklist');
            if (!currentChecklist) {
                console.error('Checklist name not found');
                return false;
            }
            var saveBtnClass = isVisa ? 'btn-visaprimary' : 'btn-personalprimary';
            var cancelBtnClass = isVisa ? 'btn-visadanger' : 'btn-personaldanger';
            $parent.empty().append(
                $('<input style="display: inline-block;width: auto;" class="form-control opentime" type="text">').prop('value', currentChecklist),
                $('<button class="btn ' + saveBtnClass + ' btn-sm mb-1">' + crmI('fas fa-check') + '</button>'),
                $('<button class="btn ' + cancelBtnClass + ' btn-sm mb-1">' + crmI('far fa-trash-alt') + '</button>')
            );
            return false;
        });

        // ---- Delete checklist ----
        $(document).on('click', '.delete-checklist-btn', function(e){
            e.preventDefault();
            e.stopPropagation();
            var checklistId = $(this).data('id');
            var checklistName = $(this).data('checklist');
            var $row = $(this).closest('.drow');
            if (!confirm('Are you sure you want to delete the checklist "' + checklistName + '"? This action cannot be undone.')) {
                return false;
            }
            $('.custom-error-msg').html('<span class="alert alert-info">' + crmI('fa fa-clock-o') + ' Deleting checklist...</span>');
            var deleteUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.deleteChecklist) ?
                window.ClientDetailConfig.urls.deleteChecklist : (typeof site_url !== 'undefined' ? site_url + '/documents/delete-checklist' : '/documents/delete-checklist');
            $.ajax({
                type: "POST",
                url: deleteUrl,
                data: {
                    "_token": $('meta[name="csrf-token"]').attr('content'),
                    "id": checklistId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status) {
                        $('.custom-error-msg').html('<span class="alert alert-success">' + response.message + '</span>');
                        $row.remove();
                    } else {
                        $('.custom-error-msg').html('<span class="alert alert-danger">' + response.message + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $('.custom-error-msg').html('<span class="alert alert-danger">An error occurred. Please try again.</span>');
                    console.error('Error deleting checklist:', error);
                }
            });
            return false;
        });

        // ---- Visual: make renamechecklist clickable (for initial load) ----
        $('.renamechecklist').css({
            'pointer-events': 'auto',
            'cursor': 'pointer',
            'z-index': '1000'
        });
        $(document).on('mouseenter', '.renamechecklist', function(){ $(this).css('background-color', '#f8f9fa'); });
        $(document).on('mouseleave', '.renamechecklist', function(){ $(this).css('background-color', ''); });
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
