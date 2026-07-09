/**
 * Two-step confirmation before deleting a CRM email log.
 * Returns a Promise<boolean>: true only after both steps are confirmed.
 */
(function() {
    'use strict';

    var overlay = null;
    var step1 = null;
    var step2 = null;
    var cancelBtn = null;
    var continueBtn = null;
    var backBtn = null;
    var confirmBtn = null;
    var activeResolver = null;

    function bindElements() {
        overlay = document.getElementById('emailDeleteConfirmModal');
        if (!overlay) {
            return false;
        }

        step1 = overlay.querySelector('[data-step="1"]');
        step2 = overlay.querySelector('[data-step="2"]');
        cancelBtn = document.getElementById('emailDeleteCancelBtn');
        continueBtn = document.getElementById('emailDeleteContinueBtn');
        backBtn = document.getElementById('emailDeleteBackBtn');
        confirmBtn = document.getElementById('emailDeleteConfirmBtn');

        if (!step1 || !step2 || !cancelBtn || !continueBtn || !backBtn || !confirmBtn) {
            return false;
        }

        if (overlay.dataset.bound === '1') {
            return true;
        }
        overlay.dataset.bound = '1';

        cancelBtn.addEventListener('click', function() {
            closeModal(false);
        });

        continueBtn.addEventListener('click', function() {
            showStep(2);
        });

        backBtn.addEventListener('click', function() {
            showStep(1);
        });

        confirmBtn.addEventListener('click', function() {
            closeModal(true);
        });

        overlay.addEventListener('click', function(event) {
            if (event.target === overlay) {
                closeModal(false);
            }
        });

        document.addEventListener('keydown', function(event) {
            if (!overlay.classList.contains('is-open')) {
                return;
            }
            if (event.key === 'Escape') {
                closeModal(false);
            }
        });

        return true;
    }

    function showStep(stepNumber) {
        if (!step1 || !step2) {
            return;
        }
        step1.hidden = stepNumber !== 1;
        step2.hidden = stepNumber !== 2;
    }

    function setPreview(options) {
        var subject = options.subject || '(No subject)';
        var fromMail = options.fromMail || 'Unknown sender';
        var attachmentCount = parseInt(options.attachmentCount, 10) || 0;

        var subject1 = document.getElementById('emailDeletePreviewSubject1');
        var from1 = document.getElementById('emailDeletePreviewFrom1');
        var attachmentsRow1 = document.getElementById('emailDeletePreviewAttachmentsRow1');
        var attachments1 = document.getElementById('emailDeletePreviewAttachments1');
        var subject2 = document.getElementById('emailDeletePreviewSubject2');
        var from2 = document.getElementById('emailDeletePreviewFrom2');

        if (subject1) {
            subject1.textContent = subject;
        }
        if (from1) {
            from1.textContent = fromMail;
        }
        if (subject2) {
            subject2.textContent = subject;
        }
        if (from2) {
            from2.textContent = fromMail;
        }

        if (attachmentsRow1 && attachments1) {
            if (attachmentCount > 0) {
                attachmentsRow1.hidden = false;
                attachments1.textContent = attachmentCount + ' file' + (attachmentCount === 1 ? '' : 's') + ' will be removed';
            } else {
                attachmentsRow1.hidden = true;
                attachments1.textContent = '—';
            }
        }
    }

    function closeModal(confirmed) {
        if (!overlay) {
            return;
        }

        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        showStep(1);

        if (typeof activeResolver === 'function') {
            var resolve = activeResolver;
            activeResolver = null;
            resolve(!!confirmed);
        }
    }

    function showEmailDeleteConfirm(options) {
        options = options || {};

        if (!bindElements()) {
            return Promise.resolve(window.confirm(
                'Delete this email and its attachments?\n\nThis requires two confirmations.\n\nClick OK for first confirmation.'
            ) && window.confirm('Final confirmation: permanently delete this email? This cannot be undone.'));
        }

        return new Promise(function(resolve) {
            activeResolver = resolve;
            setPreview(options);
            showStep(1);
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();
        });
    }

    window.showEmailDeleteConfirm = showEmailDeleteConfirm;
})();
