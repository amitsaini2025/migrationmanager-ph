/**
 * Isolated Verify Link sender — does not touch appointment / email / SMS handlers.
 */
(function () {
    'use strict';

    function toast(type, message) {
        if (typeof iziToast !== 'undefined') {
            iziToast[type]({ message: message, position: 'topRight' });
            return;
        }
        window.alert(message);
    }

    function bind() {
        if (typeof window.jQuery === 'undefined') {
            return;
        }

        window.jQuery(document).on('click', '.send-verify-link', function (event) {
            event.preventDefault();
            var $btn = window.jQuery(this);
            if ($btn.data('busy')) {
                return;
            }

            var config = window.ClientDetailConfig || {};
            var url = (config.urls && config.urls.sendVerifyLink) || '';
            var clientId = config.clientId;
            if (!url || !clientId) {
                toast('error', 'Unable to send verification link.');
                return;
            }

            $btn.data('busy', true);
            window.jQuery.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token: config.csrfToken,
                    client_id: clientId
                },
                success: function (res) {
                    toast('success', (res && res.message) || 'Verification link sent.');
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not send verification link.';
                    toast('error', msg);
                },
                complete: function () {
                    $btn.data('busy', false);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
