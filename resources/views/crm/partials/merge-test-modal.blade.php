@php
    $mergeSearchType = $mergeSearchType ?? 'lead';
@endphp
<div class="modal fade" id="mergeTestModal" tabindex="-1" role="dialog" aria-labelledby="mergeTestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="mergeTestModalLabel">Merge Record</h4>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Selected record: <strong id="mergeTestSelectedLabel"></strong></p>
                <div class="form-group">
                    <label for="mergeTestSearch">Search by name, email, phone, or client reference</label>
                    <input type="text" id="mergeTestSearch" class="form-control" placeholder="Type at least 2 characters" autocomplete="off">
                </div>
                <div id="mergeTestSearchStatus" class="text-muted small mb-2"></div>
                <div id="mergeTestResults" class="list-group mb-3" style="max-height: 240px; overflow-y: auto;"></div>
                <div id="mergeTestDirectionWrap" style="display:none;">
                    <p class="mb-2">Choose merge direction:</p>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="merge_test_direction" id="mergeTestDirSelectedIntoFound" value="selected_into_found" checked>
                        <label class="form-check-label" for="mergeTestDirSelectedIntoFound" id="mergeTestDirSelectedIntoFoundLabel"></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="merge_test_direction" id="mergeTestDirFoundIntoSelected" value="found_into_selected">
                        <label class="form-check-label" for="mergeTestDirFoundIntoSelected" id="mergeTestDirFoundIntoSelectedLabel"></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="mergeTestStartBtn" disabled>Start Merge</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var mergeSearchType = @json($mergeSearchType);
    var searchUrl = @json(route('client.merge_records.search'));
    var mergeUrl = @json(url('/merge_records'));
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var selectedRecord = null;
    var foundRecord = null;
    var searchTimer = null;

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function recordTypeLabel(record) {
        if (!record) {
            return '';
        }
        if (record.type_label) {
            return record.type_label;
        }
        return record.type === 'client' ? 'Client' : 'Lead';
    }

    function recordLabel(record) {
        if (!record) {
            return '';
        }
        var typeLabel = recordTypeLabel(record);
        return $.trim((record.name || '') + ' (' + (record.client_id || '') + ')' + (typeLabel ? ' — ' + typeLabel : ''));
    }

    function resetMergeTestModal() {
        foundRecord = null;
        $('#mergeTestSearch').val('');
        $('#mergeTestSearchStatus').text('');
        $('#mergeTestResults').empty();
        $('#mergeTestDirectionWrap').hide();
        $('#mergeTestStartBtn').prop('disabled', true);
        $('input[name="merge_test_direction"][value="selected_into_found"]').prop('checked', true);
    }

    function updateDirectionLabels() {
        if (!selectedRecord || !foundRecord) {
            return;
        }
        var selected = recordLabel(selectedRecord);
        var found = recordLabel(foundRecord);
        $('#mergeTestDirSelectedIntoFoundLabel').text('Merge ' + selected + ' into ' + found + ' (keep ' + found + ')');
        $('#mergeTestDirFoundIntoSelectedLabel').text('Merge ' + found + ' into ' + selected + ' (keep ' + selected + ')');
        $('#mergeTestDirectionWrap').show();
        $('#mergeTestStartBtn').prop('disabled', false);
    }

    function renderResults(results) {
        var $list = $('#mergeTestResults').empty();
        if (!results || !results.length) {
            $('#mergeTestSearchStatus').text('No matching records found.');
            return;
        }
        $('#mergeTestSearchStatus').text(results.length + ' matching record(s). Select one.');
        results.forEach(function (item) {
            var meta = [];
            if (item.email) {
                meta.push(item.email);
            }
            if (item.phone) {
                meta.push(item.phone);
            }
            var typeLabel = recordTypeLabel(item);
            var badgeClass = item.type === 'client' ? 'badge-success' : 'badge-info';
            var $btn = $('<button type="button" class="list-group-item list-group-item-action merge-test-result"></button>');
            $btn.attr('data-id', item.id);
            $btn.data('record', item);
            $btn.html(
                '<strong>' + escapeHtml(item.name) + '</strong> (' + escapeHtml(item.client_id) + ') ' +
                '<span class="badge ' + badgeClass + '">' + escapeHtml(typeLabel) + '</span>' +
                (meta.length ? '<br><small>' + escapeHtml(meta.join(' · ')) + '</small>' : '')
            );
            $list.append($btn);
        });
    }

    $(document).delegate('.listing-container .is_checked_client_merge_test', 'click', function () {
        var $checked = $('.listing-container .cb-element:checked');
        if ($checked.length !== 1) {
            return;
        }
        var $row = $checked.first();
        selectedRecord = {
            id: $row.data('id'),
            name: $.trim($row.attr('data-name') || ''),
            email: $row.attr('data-email') || '',
            phone: $row.attr('data-phone') || '',
            client_id: $row.attr('data-clientid') || '',
            type: mergeSearchType
        };
        resetMergeTestModal();
        $('#mergeTestSelectedLabel').text(recordLabel(selectedRecord));
        $('#mergeTestModal').modal('show');
    });

    $(document).on('input', '#mergeTestSearch', function () {
        var q = $.trim($(this).val());
        foundRecord = null;
        $('#mergeTestDirectionWrap').hide();
        $('#mergeTestStartBtn').prop('disabled', true);
        clearTimeout(searchTimer);
        if (q.length < 2) {
            $('#mergeTestResults').empty();
            $('#mergeTestSearchStatus').text(q.length ? 'Type at least 2 characters.' : '');
            return;
        }
        $('#mergeTestSearchStatus').text('Searching...');
        searchTimer = setTimeout(function () {
            $.ajax({
                type: 'get',
                url: searchUrl,
                data: {
                    q: q,
                    exclude_id: selectedRecord ? selectedRecord.id : 0,
                    type: mergeSearchType
                },
                success: function (response) {
                    renderResults(response && response.results ? response.results : []);
                },
                error: function () {
                    $('#mergeTestSearchStatus').text('Search failed. Please try again.');
                    $('#mergeTestResults').empty();
                }
            });
        }, 300);
    });

    $(document).on('click', '#mergeTestResults .merge-test-result', function () {
        $('#mergeTestResults .merge-test-result').removeClass('active');
        $(this).addClass('active');
        foundRecord = $(this).data('record');
        updateDirectionLabels();
    });

    $(document).on('click', '#mergeTestStartBtn', function () {
        if (!selectedRecord || !foundRecord) {
            return;
        }
        var direction = $('input[name="merge_test_direction"]:checked').val();
        var mergeFrom = selectedRecord.id;
        var mergeInto = foundRecord.id;
        if (direction === 'found_into_selected') {
            mergeFrom = foundRecord.id;
            mergeInto = selectedRecord.id;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            type: 'post',
            url: mergeUrl,
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: { merge_from: mergeFrom, merge_into: mergeInto },
            success: function (response) {
                var obj = (typeof response === 'string') ? $.parseJSON(response) : response;
                if (obj && obj.status) {
                    location.reload(true);
                } else {
                    alert((obj && obj.message) ? obj.message : 'Merge failed. Please try again.');
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                alert('Merge failed. Please try again.');
                $btn.prop('disabled', false);
            }
        });
    });
})();
</script>
