/**
 * Send to Client - Invoice and Receipt email functionality
 * Extracted from detail-main.js - Phase 3 refactoring.
 * Requires: jQuery, ClientDetailConfig, Swal (optional)
 */
(function() {
    'use strict';

    function showSendInProgress(title, text) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: title || 'Sending...',
                text: text || 'Please wait.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            return;
        }
        if (typeof $ !== 'undefined' && $('.popuploader').length) {
            $('.popuploader').show();
        }
    }

    function showSendResult(ok, message) {
        if (typeof $ !== 'undefined' && $('.popuploader').length) {
            $('.popuploader').hide();
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: ok ? 'success' : 'error',
                title: ok ? 'Success!' : 'Error',
                text: message,
                timer: ok ? 3000 : undefined
            });
            return;
        }
        alert((ok ? '' : 'Error: ') + message);
    }

    /**
     * Send Invoice to Client
     */
    function handleSendInvoiceToClient($btn) {
        var invoiceId = $btn.data('invoice-id');
        var invoiceNo = $btn.data('invoice-no');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Send Invoice to Client?',
                text: 'This will send invoice #' + invoiceNo + ' to the client\'s email address.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, send it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendInvoiceToClientAjax(invoiceId, invoiceNo, $btn);
                }
            });
        } else {
            if (confirm('Are you sure you want to send invoice #' + invoiceNo + ' to the client\'s email?')) {
                sendInvoiceToClientAjax(invoiceId, invoiceNo, $btn);
            }
        }
    }

    function sendInvoiceToClientAjax(invoiceId, invoiceNo, $btn) {
        var originalHtml = $btn.html();
        $btn.html(crmI('fas fa-spinner fa-spin') + ' Sending...');
        $btn.prop('disabled', true);
        showSendInProgress('Sending Invoice...', 'Sending invoice #' + invoiceNo + ' to the client.');

        $.ajax({
            url: window.ClientDetailConfig.urls.sendInvoiceToClient + '/' + invoiceId,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                if (response.status) {
                    showSendResult(true, response.message);
                } else {
                    showSendResult(false, response.message || 'Failed to send invoice.');
                }
            },
            error: function(xhr) {
                console.error('Error sending invoice to client:', xhr);
                var errorMsg = 'Failed to send invoice. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                showSendResult(false, errorMsg);
            }
        });
    }

    /**
     * Send Client Fund Receipt to Client
     */
    function handleSendClientFundReceiptToClient($btn) {
        var receiptId = $btn.data('receipt-id');
        var receiptNo = $btn.data('receipt-no');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Send Receipt to Client?',
                text: 'This will send receipt #' + receiptNo + ' to the client\'s email address.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, send it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendClientFundReceiptToClientAjax(receiptId, receiptNo, $btn);
                }
            });
        } else {
            if (confirm('Are you sure you want to send receipt #' + receiptNo + ' to the client\'s email?')) {
                sendClientFundReceiptToClientAjax(receiptId, receiptNo, $btn);
            }
        }
    }

    function sendClientFundReceiptToClientAjax(receiptId, receiptNo, $btn) {
        var originalHtml = $btn.html();
        $btn.html(crmI('fas fa-spinner fa-spin') + ' Sending...');
        $btn.prop('disabled', true);
        showSendInProgress('Sending Receipt...', 'Sending receipt #' + receiptNo + ' to the client.');

        $.ajax({
            url: window.ClientDetailConfig.urls.sendClientFundReceiptToClient + '/' + receiptId,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                if (response.status) {
                    showSendResult(true, response.message);
                } else {
                    showSendResult(false, response.message || 'Failed to send receipt.');
                }
            },
            error: function(xhr) {
                console.error('Error sending client fund receipt to client:', xhr);
                var errorMsg = 'Failed to send receipt. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                showSendResult(false, errorMsg);
            }
        });
    }

    /**
     * Send Invoice to Client Portal (Client Portal / Mobile App)
     */
    function handleSendInvoiceToClientApplication($btn) {
        var invoiceId = $btn.data('invoice-id');
        var invoiceNo = $btn.data('invoice-no');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Send to Client Portal?',
                text: 'This will send invoice #' + invoiceNo + ' to the client\'s mobile app / portal.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, send it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendInvoiceToClientApplicationAjax(invoiceId, invoiceNo, $btn);
                }
            });
        } else {
            if (confirm('Are you sure you want to send invoice #' + invoiceNo + ' to the Client Portal?')) {
                sendInvoiceToClientApplicationAjax(invoiceId, invoiceNo, $btn);
            }
        }
    }

    function sendInvoiceToClientApplicationAjax(invoiceId, invoiceNo, $btn) {
        var originalHtml = $btn.html();
        $btn.html(crmI('fas fa-spinner fa-spin') + ' Sending...');
        $btn.prop('disabled', true);

        var baseUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.sendToClientApplication)
            ? window.ClientDetailConfig.urls.sendToClientApplication
            : null;

        if (!baseUrl) {
            $btn.html(originalHtml);
            $btn.prop('disabled', false);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Configuration not available.' });
            } else {
                alert('Configuration not available.');
            }
            return;
        }

        $.ajax({
            url: baseUrl + '/' + invoiceId,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            timer: 2000
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        alert(response.message);
                        location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    } else {
                        alert('Error: ' + response.message);
                    }
                    $btn.html(originalHtml);
                    $btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error('Error sending invoice to client portal:', xhr);
                var errorMsg = 'Failed to send. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMsg
                    });
                } else {
                    alert('Error: ' + errorMsg);
                }
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Send Office Receipt to Client
     */
    function handleSendOfficeReceiptToClient($btn) {
        var receiptId = $btn.data('receipt-id');
        var receiptNo = $btn.data('receipt-no');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Send Receipt to Client?',
                text: 'This will send office receipt #' + receiptNo + ' to the client\'s email address.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, send it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendOfficeReceiptToClientAjax(receiptId, receiptNo, $btn);
                }
            });
        } else {
            if (confirm('Are you sure you want to send office receipt #' + receiptNo + ' to the client\'s email?')) {
                sendOfficeReceiptToClientAjax(receiptId, receiptNo, $btn);
            }
        }
    }

    function sendOfficeReceiptToClientAjax(receiptId, receiptNo, $btn) {
        var originalHtml = $btn.html();
        $btn.html(crmI('fas fa-spinner fa-spin') + ' Sending...');
        $btn.prop('disabled', true);
        showSendInProgress('Sending Receipt...', 'Sending office receipt #' + receiptNo + ' to the client.');

        $.ajax({
            url: window.ClientDetailConfig.urls.sendOfficeReceiptToClient + '/' + receiptId,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                if (response.status) {
                    showSendResult(true, response.message);
                } else {
                    showSendResult(false, response.message || 'Failed to send receipt.');
                }
            },
            error: function(xhr) {
                console.error('Error sending office receipt to client:', xhr);
                var errorMsg = 'Failed to send receipt. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                showSendResult(false, errorMsg);
            }
        });
    }

    /**
     * Format Hubdoc sent timestamp to match server display (d/m/Y H:i, app timezone)
     */
    function formatHubdocSentAt(dateInput) {
        if (typeof dateInput === 'string' && /^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/.test(dateInput)) {
            return dateInput;
        }

        var tz = (window.ClientDetailConfig && window.ClientDetailConfig.timezone) || 'Australia/Melbourne';
        var d = dateInput instanceof Date ? dateInput : new Date(dateInput);
        if (isNaN(d.getTime())) {
            return '';
        }

        try {
            var parts = {};
            new Intl.DateTimeFormat('en-GB', {
                timeZone: tz,
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            }).formatToParts(d).forEach(function(part) {
                if (part.type !== 'literal') {
                    parts[part.type] = part.value;
                }
            });

            return parts.day + '/' + parts.month + '/' + parts.year + ' ' + parts.hour + ':' + parts.minute;
        } catch (e) {
            var pad = function(n) {
                return String(n).padStart(2, '0');
            };
            return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }
    }

    function getHubdocMenuElements($context, invoiceId) {
        var $menu = $context.closest('.dropdown-menu');
        if (!$menu.length) {
            $menu = $context;
        }
        return {
            $menu: $menu,
            $hubdocBtn: $menu.find('.send-to-hubdoc-btn[data-invoice-id="' + invoiceId + '"]'),
            $sentTime: $menu.find('.hubdoc-sent-time'),
            $refreshBtn: $menu.find('.refresh-hubdoc-status[data-invoice-id="' + invoiceId + '"]')
        };
    }

    function updateHubdocDropdownToSent($btn, sentAt, formattedDisplay) {
        var invoiceId = $btn.data('invoice-id');
        var formatted = formattedDisplay || formatHubdocSentAt(sentAt || new Date());
        var elements = getHubdocMenuElements($btn, invoiceId);

        elements.$sentTime.remove();
        elements.$refreshBtn.remove();

        $btn
            .attr('data-hubdoc-sent', '1')
            .css('color', '#28a745')
            .html(crmI('fas fa-check') + ' Already Sent to Hubdoc')
            .prop('disabled', false);

        var $timeDiv = $('<div class="dropdown-item-text hubdoc-sent-time" style="font-size: 11px; color: #666; padding: 0.25rem 1rem;">Sent: ' + formatted + '</div>');
        $timeDiv.insertAfter($btn);

        $('<a class="dropdown-item refresh-hubdoc-status" href="javascript:;" data-invoice-id="' + invoiceId + '">' +
            crmI('fas fa-sync-alt') + ' Refresh Status</a>')
            .insertAfter($timeDiv);

        attachSendToClientHandlers();
    }

    function revertHubdocDropdownToUnsent($btn) {
        var invoiceId = $btn.data('invoice-id');
        var elements = getHubdocMenuElements($btn, invoiceId);

        elements.$sentTime.remove();
        elements.$refreshBtn.remove();

        $btn
            .removeAttr('data-hubdoc-sent')
            .removeAttr('style')
            .html(crmI('fas fa-paper-plane') + ' Send to Hubdoc')
            .prop('disabled', false);

        attachSendToClientHandlers();
    }

    /**
     * Send Invoice to Hubdoc
     */
    function handleSendToHubdoc($btn) {
        if ($btn.data('hubdoc-sent') === 1 || $btn.data('hubdoc-sent') === true) {
            return;
        }
        var invoiceId = $btn.data('invoice-id');
        if (!invoiceId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Invoice ID is missing.' });
            } else {
                alert('Invoice ID is missing.');
            }
            return;
        }

        var baseUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.sendToHubdoc)
            ? window.ClientDetailConfig.urls.sendToHubdoc
            : null;
        if (!baseUrl) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Configuration not available.' });
            } else {
                alert('Configuration not available.');
            }
            return;
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Send to Hubdoc?',
                text: 'This will send the invoice to Hubdoc for processing.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, send it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    sendToHubdocAjax(invoiceId, $btn);
                }
            });
        } else {
            if (confirm('Send this invoice to Hubdoc for processing?')) {
                sendToHubdocAjax(invoiceId, $btn);
            }
        }
    }

    function sendToHubdocAjax(invoiceId, $btn) {
        var originalHtml = $btn.html();
        $btn.html(crmI('fas fa-spinner fa-spin') + ' Sending...');
        $btn.prop('disabled', true);

        var baseUrl = window.ClientDetailConfig.urls.sendToHubdoc;
        $.ajax({
            url: baseUrl + '/' + invoiceId,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status) {
                    updateHubdocDropdownToSent($btn, response.hubdoc_sent_at, response.hubdoc_sent_at_formatted);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message || 'Invoice sent to Hubdoc successfully!',
                            timer: 2000
                        });
                    } else {
                        alert(response.message || 'Invoice sent to Hubdoc successfully!');
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Failed to send to Hubdoc.'
                        });
                    } else {
                        alert('Error: ' + (response.message || 'Failed to send to Hubdoc.'));
                    }
                    $btn.html(originalHtml);
                    $btn.prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error('Error sending invoice to Hubdoc:', xhr);
                var errorMsg = 'Failed to send to Hubdoc. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: errorMsg
                    });
                } else {
                    alert('Error: ' + errorMsg);
                }
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Refresh Hubdoc status without reloading the page
     */
    function handleRefreshHubdocStatus($btn) {
        var invoiceId = $btn.data('invoice-id');
        if (!invoiceId) return;

        var checkUrl = (window.ClientDetailConfig && window.ClientDetailConfig.urls && window.ClientDetailConfig.urls.checkHubdocStatus)
            ? window.ClientDetailConfig.urls.checkHubdocStatus
            : null;
        if (!checkUrl) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Configuration not available.' });
            } else {
                alert('Configuration not available.');
            }
            return;
        }

        var originalHtml = $btn.html();
        $btn.html(crmI('fas fa-spinner fa-spin') + ' Refreshing...');
        $btn.prop('disabled', true);

        $.ajax({
            url: checkUrl + '/' + invoiceId,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                var elements = getHubdocMenuElements($btn, invoiceId);
                var $hubdocBtn = elements.$hubdocBtn;

                if (data.hubdoc_sent && $hubdocBtn.length) {
                    updateHubdocDropdownToSent($hubdocBtn, data.hubdoc_sent_at, data.hubdoc_sent_at_formatted);
                } else if ($hubdocBtn.length) {
                    revertHubdocDropdownToUnsent($hubdocBtn);
                } else {
                    $btn.html(originalHtml);
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to refresh Hubdoc status.' });
                } else {
                    alert('Failed to refresh Hubdoc status.');
                }
            }
        });
    }

    /**
     * Document-delegated clicks cover static and dynamically inserted menu items
     * (including Hubdoc buttons). Kept as a no-op stub so existing call sites stay safe.
     */
    function attachSendToClientHandlers() {
        // no-op: handlers are document-delegated below
    }

    function bindSendToClientClick(selector, handler) {
        $(document).on('click', selector, function(e) {
            e.preventDefault();
            e.stopPropagation();
            handler($(this));
        });
    }

    bindSendToClientClick('.send-invoice-to-client', handleSendInvoiceToClient);
    bindSendToClientClick('.send-client-fund-receipt-to-client', handleSendClientFundReceiptToClient);
    bindSendToClientClick('.send-office-receipt-to-client', handleSendOfficeReceiptToClient);
    bindSendToClientClick('.send-to-client-portal-btn', handleSendInvoiceToClientApplication);
    bindSendToClientClick('.send-to-hubdoc-btn', handleSendToHubdoc);
    bindSendToClientClick('.refresh-hubdoc-status', handleRefreshHubdocStatus);

})();
