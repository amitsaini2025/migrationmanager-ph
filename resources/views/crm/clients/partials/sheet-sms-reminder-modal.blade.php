{{-- Send SMS popup for visa sheet checklist SMS reminder --}}
<div id="sheetSmsModal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal sheet-sms-reminder-modal" tabindex="-1" role="dialog" aria-labelledby="sheetSmsReminderLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sheetSmsReminderLabel">
                    @icon('fa-sms') SMS Reminder
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="sheetSmsForm">
                    @csrf
                    <input type="hidden" name="client_id" id="sheet_sms_client_id" value="">
                    <input type="hidden" name="client_matter_id" id="sheet_sms_matter_id" value="">
                    <input type="hidden" name="checklist_reminder" id="sheet_sms_checklist_reminder" value="1">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label for="sheet_sms_phone">Send To <span class="span_req">*</span></label>
                                <select class="form-control" id="sheet_sms_phone" name="phone" required>
                                    <option value="">Select phone number...</option>
                                </select>
                                <small class="form-text text-muted">
                                    @icon('fa-info-circle')
                                    Australian numbers will use Cellcast, international numbers will use Twilio
                                </small>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="sheet_sms_template">Quick Template (Optional)</label>
                                <select class="form-control" id="sheet_sms_template">
                                    <option value="">Type your own message or select a template...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label for="sheet_sms_message">Message <span class="span_req">*</span></label>
                                <textarea class="sms-compose-message" id="sheet_sms_message" name="message" rows="5" maxlength="320" required></textarea>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted">
                                        <span id="sheet_sms_char_count">0</span> / <span id="sheet_sms_char_max">160</span> chars
                                    </small>
                                    <small>
                                        <span id="sheet_sms_segment_badge" class="badge badge-success">1 SMS</span>
                                        <span id="sheet_sms_chars_remaining" class="text-muted">&nbsp;&middot;&nbsp; 160 left in this SMS</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" id="sheetSendSmsBtn">
                                @icon('fa-paper-plane') Send SMS
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
