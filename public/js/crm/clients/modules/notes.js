/**
 * Notes module - Create, edit, view notes; getallnotes; mmSelect template helpers
 * Extracted from detail-main.js - Phase 3b refactoring.
 * Requires: jQuery, ClientDetailConfig, clearEditor, setEditorContent, adjustActivityFeedHeight
 */
(function($) {
    'use strict';
    if (!$) return;

    var baseUrl = (typeof site_url !== 'undefined' ? site_url : (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.base ? window.ClientDetailConfig.urls.base : ''));

    function safeParse(r) {
        if (typeof r === 'object' && r !== null) return r;
        if (typeof r === 'string' && r.trim()) { try { return JSON.parse(r); } catch(e) { return null; } }
        return null;
    }

    function formatRepo(repo) {
        if (repo.loading) {
            return repo.text;
        }
        var $container = $(
            "<div class='mm-result-repository ag-flex ag-space-between ag-align-center'>" +
            "<div class='ag-flex ag-flex-column col-hr-1'><div class='ag-flex'><span class='mm-result-repository__title text-semi-bold'></span>&nbsp;</div>" +
            "<div class='ag-flex ag-align-center'><small class='mm-result-repository__description'></small></div></div></div>" +
            "<div class='ag-flex ag-flex-column ag-align-end'><span class='ui label yellow mm-result-repository__statistics'></span></div></div>"
        );
        $container.find(".mm-result-repository__title").text(repo.name);
        $container.find(".mm-result-repository__description").text(repo.email);
        $container.find(".mm-result-repository__statistics").append(repo.status);
        return $container;
    }

    function formatRepoSelection(repo) {
        return repo.name || repo.text;
    }

    window.formatRepo = formatRepo;
    window.formatRepoSelection = formatRepoSelection;

    function getallnotes() {
        var notesUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.getNotes) ? window.ClientDetailConfig.urls.getNotes : baseUrl + '/get-notes';
        $.ajax({
            url: notesUrl,
            type: 'GET',
            data: { clientid: window.ClientDetailConfig.clientId, type: 'client' },
            success: function(responses) {
                $('.popuploader').hide();
                $('.note_term_list').html(responses);

                if (typeof window.filterNotes === 'function') {
                    window.filterNotes();
                } else if (typeof window.refreshNotesScopeTabs === 'function') {
                    window.refreshNotesScopeTabs();
                }

                if (typeof adjustActivityFeedHeight === 'function') {
                    adjustActivityFeedHeight();
                }
            },
            error: function(xhr, status, error) {
                $('.popuploader').hide();
                console.error('[getallnotes] Failed to refresh notes:', status, error);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Notes refreshed but some data may be outdated. Please refresh the page.');
                }
            }
        });
    }

    window.getallnotes = getallnotes;

    $(document).ready(function() {
        $(document).delegate('.create_note_d', 'click', function() {
            // Reset type select and clear any leftover phone/extra fields from a previous edit
            $('#create_note_d select[name="task_group"]').val('');
            $('#create_note_d .additional-fields-container').html('');

            $('#create_note_d').modal('show');
            $('#create_note_d input[name="mailid"]').val(0);
            $('#create_note_d input[name="title"]').val("Matter Discussion");
            $('#create_note_d input[name="noteid"]').val('');
            $('#create_note_d #appliationModalLabel').html('Create Note');

            // Pre-select the currently active matter so notes are saved under the right matter
            var activeMatterId = $('#sel_matter_id_client_detail').val() || '';
            $('#create_note_d select[name="matter_id"]').val(activeMatterId);

            if ($(this).attr('datatype') == 'note') {
                $('.is_not_note').hide();
            } else {
                var datasubject = $(this).attr('datasubject');
                var datamailid = $(this).attr('datamailid');
                $('#create_note_d input[name="title"]').val(datasubject);
                $('#create_note_d input[name="mailid"]').val(datamailid);
                $('.is_not_note').show();
            }
        });

        $(document).delegate('.create_note', 'click', function() {
            // Reset type select and clear any leftover phone/extra fields from a previous edit
            $('#create_note select[name="task_group"]').val('');
            $('#create_note .additional-fields-container').html('');

            $('#create_note').modal('show');
            $('#create_note input[name="mailid"]').val(0);
            $('#create_note input[name="title"]').val('');
            $('#create_note #appliationModalLabel').html('Create Note');
            $('#create_note input[name="noteid"]').val('');
            if (typeof clearEditor === 'function') {
                clearEditor("#create_note .tinymce-editor");
            }
            $("#create_note .tinymce-editor").val('');
            if ($(this).attr('datatype') == 'note') {
                $('.is_not_note').hide();
            } else {
                var datasubject = $(this).attr('datasubject');
                var datamailid = $(this).attr('datamailid');
                $('#create_note input[name="title"]').val(datasubject);
                $('#create_note input[name="mailid"]').val(datamailid);
                $('.is_not_note').show();
            }
        });

        if ($('#create_note').length && $('.js-data-example-ajaxcc').length) {
            $('.js-data-example-ajaxcc').mmSelect({
                multiple: true,
                closeOnSelect: false,
                dropdownParent: $('#create_note'),
                ajax: {
                    url: window.ClientDetailConfig.urls.getRecipients,
                    dataType: 'json',
                    processResults: function(data) {
                        return { results: data.items };
                    },
                    cache: true
                },
                templateResult: formatRepo,
                templateSelection: formatRepoSelection
            });
        }

        $(document).on('click', '.opennoteform', function(e) {
            e.preventDefault();
            if ($('#create_note').length === 0) {
                console.error('Modal #create_note not found!');
                return;
            }
            $('#create_note').modal('show');
            $('#create_note #appliationModalLabel').html('Edit Note');
            var v = $(this).attr('data-id');
            $('#create_note input[name="noteid"]').val(v);
            $('.popuploader').show();
            $.ajax({
                url: window.ClientDetailConfig.urls.getNoteDetail,
                type: 'GET',
                dataType: 'json',
                data: { note_id: v },
                success: function(response) {
                    $('.popuploader').hide();
                    var res = safeParse(response);
                    if (!res || !res.status) return;
                    var taskGroup = res.data.task_group || '';
                    var savedPhone = (res.data.mobile_number != null ? String(res.data.mobile_number) : '').trim();

                    $('#create_note select[name="task_group"]').val(taskGroup);
                    $("#create_note .tinymce-editor").val(res.data.description);
                    if (typeof setEditorContent === 'function') {
                        setEditorContent("#create_note .tinymce-editor", res.data.description);
                    }

                    $('#create_note select[name="task_group"]').trigger('change');

                    if (taskGroup === 'Call' && savedPhone) {
                        var tries = 0;
                        var maxTries = 80;
                        var interval = setInterval(function() {
                            tries++;
                            var $sel = $('#create_note #mobileNumber');
                            if ($sel.length && $sel.find('option').length > 1) {
                                $sel.val(savedPhone);
                                if ($sel.val() !== savedPhone) {
                                    var esc = $('<div/>').text(savedPhone).html();
                                    $sel.append('<option value="' + esc + '">' + esc + '</option>');
                                    $sel.val(savedPhone);
                                }
                                clearInterval(interval);
                            } else if (tries >= maxTries) {
                                clearInterval(interval);
                            }
                        }, 50);
                    }
                },
                error: function(xhr, status, error) {
                    $('.popuploader').hide();
                    console.error('Error fetching note details:', error);
                }
            });
        });

        $(document).delegate('.viewnote', 'click', function() {
            $('#view_note').modal('show');
            var v = $(this).attr('data-id');
            $('#view_note input[name="noteid"]').val(v);
            $('.popuploader').show();
            $.ajax({
                url: window.ClientDetailConfig.urls.viewNoteDetail,
                type: 'GET',
                dataType: 'json',
                data: { note_id: v },
                success: function(response) {
                    $('.popuploader').hide();
                    var res = safeParse(response);
                    if (!res || !res.status) return;
                    $('#view_note .modal-body .note_content h5').html(res.data.title);
                    $("#view_note .modal-body .note_content p").html(res.data.description);
                },
                error: function() {
                    $('.popuploader').hide();
                }
            });
        });

        $(document).delegate('.viewmatternote', 'click', function() {
            $('#view_matter_note').modal('show');
            var v = $(this).attr('data-id');
            $('#view_matter_note input[name="noteid"]').val(v);
            $('.popuploader').show();
            $.ajax({
                url: (window.ClientDetailConfig.urls.viewMatterNote || window.ClientDetailConfig.urls.viewApplicationNote),
                type: 'GET',
                dataType: 'json',
                data: { note_id: v },
                success: function(response) {
                    $('.popuploader').hide();
                    var res = safeParse(response);
                    if (!res || !res.status) return;
                    $('#view_matter_note .modal-body .note_content h5').html(res.data.title);
                    $("#view_matter_note .modal-body .note_content p").html(res.data.description);
                },
                error: function() {
                    $('.popuploader').hide();
                }
            });
        });
    });

})(typeof jQuery !== 'undefined' ? jQuery : null);
