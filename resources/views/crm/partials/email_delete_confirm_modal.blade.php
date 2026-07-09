<!-- Two-step email delete confirmation -->
<div class="email-delete-modal-overlay" id="emailDeleteConfirmModal" aria-hidden="true">
    <div class="email-delete-modal" role="dialog" aria-labelledby="emailDeleteModalTitle" aria-modal="true">
        <div class="email-delete-modal__step email-delete-modal__step--1" data-step="1">
            <div class="email-delete-modal__icon email-delete-modal__icon--warn" aria-hidden="true">
                <i class="fas fa-trash-alt"></i>
            </div>
            <p class="email-delete-modal__step-label">Step 1 of 2</p>
            <h3 class="email-delete-modal__title" id="emailDeleteModalTitle">Delete this email?</h3>
            <p class="email-delete-modal__lead">This will remove the email from the CRM. Linked attachments will be deleted as well.</p>
            <div class="email-delete-modal__preview" id="emailDeletePreviewStep1">
                <div class="email-delete-modal__preview-row">
                    <span class="email-delete-modal__preview-label">Subject</span>
                    <span class="email-delete-modal__preview-value" id="emailDeletePreviewSubject1">—</span>
                </div>
                <div class="email-delete-modal__preview-row">
                    <span class="email-delete-modal__preview-label">From</span>
                    <span class="email-delete-modal__preview-value" id="emailDeletePreviewFrom1">—</span>
                </div>
                <div class="email-delete-modal__preview-row" id="emailDeletePreviewAttachmentsRow1" hidden>
                    <span class="email-delete-modal__preview-label">Attachments</span>
                    <span class="email-delete-modal__preview-value" id="emailDeletePreviewAttachments1">—</span>
                </div>
            </div>
            <div class="email-delete-modal__actions">
                <button type="button" class="email-delete-modal__btn email-delete-modal__btn--cancel" id="emailDeleteCancelBtn">Cancel</button>
                <button type="button" class="email-delete-modal__btn email-delete-modal__btn--continue" id="emailDeleteContinueBtn">
                    Continue <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="email-delete-modal__step email-delete-modal__step--2" data-step="2" hidden>
            <div class="email-delete-modal__icon email-delete-modal__icon--danger" aria-hidden="true">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <p class="email-delete-modal__step-label email-delete-modal__step-label--danger">Step 2 of 2 — Final confirmation</p>
            <h3 class="email-delete-modal__title">Permanently delete this email?</h3>
            <p class="email-delete-modal__lead email-delete-modal__lead--danger">This action cannot be undone. The email and all attachments will be removed from the system.</p>
            <div class="email-delete-modal__preview email-delete-modal__preview--danger" id="emailDeletePreviewStep2">
                <div class="email-delete-modal__preview-row">
                    <span class="email-delete-modal__preview-label">Subject</span>
                    <span class="email-delete-modal__preview-value" id="emailDeletePreviewSubject2">—</span>
                </div>
                <div class="email-delete-modal__preview-row">
                    <span class="email-delete-modal__preview-label">From</span>
                    <span class="email-delete-modal__preview-value" id="emailDeletePreviewFrom2">—</span>
                </div>
            </div>
            <div class="email-delete-modal__actions">
                <button type="button" class="email-delete-modal__btn email-delete-modal__btn--back" id="emailDeleteBackBtn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Go back
                </button>
                <button type="button" class="email-delete-modal__btn email-delete-modal__btn--delete" id="emailDeleteConfirmBtn">
                    <i class="fas fa-trash-alt" aria-hidden="true"></i> Delete permanently
                </button>
            </div>
        </div>
    </div>
</div>
