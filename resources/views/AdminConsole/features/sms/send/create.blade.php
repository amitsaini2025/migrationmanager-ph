@extends('layouts.crm_client_detail')
@section('title', 'Send SMS')

@section('content')
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg">
            </div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h4>@icon('fa-paper-plane') Send SMS</h4>
                        </div>
                        <div class="card-body">
                            <form id="smsForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="phone" name="phone" placeholder="+61412345678" required>
                                            <small class="form-text text-muted">Include country code (e.g., +61 for Australia)</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="template_id">Use Template (Optional)</label>
                                            <select class="form-control" id="template_id" name="template_id">
                                                <option value="">Select a template...</option>
                                                <!-- Templates will be loaded via AJAX -->
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="4" maxlength="320" placeholder="Enter your message here..." required></textarea>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <small class="text-muted">
                                            <span id="charCount">0</span> / <span id="charMax">160</span> chars
                                        </small>
                                        <small>
                                            <span id="segmentBadge" class="badge badge-success">1 SMS</span>
                                            <span id="charsRemaining" class="text-muted"> &nbsp;·&nbsp; 160 left in this SMS</span>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" id="sendBtn">
                                        @icon('fa-paper-plane') Send SMS
                                    </button>
                                    <a href="{{ route('adminconsole.features.sms.dashboard') }}" class="btn btn-secondary">
                                        @icon('fa-arrow-left') Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Load templates
    loadTemplates();
    
    // Character counter
    $('#message').on('input', function() {
        var len     = $(this).val().length;
        var segSize = 160;
        var segs    = Math.max(1, Math.ceil(len / segSize));
        var left    = (segs * segSize) - len;

        $('#charCount').text(len);
        $('#charMax').text(segs * segSize);
        $('#charsRemaining').html('&nbsp;&middot;&nbsp; ' + left + ' left in this SMS');
        $('#segmentBadge')
            .text(segs + ' SMS')
            .removeClass('badge-success badge-warning')
            .addClass(segs === 1 ? 'badge-success' : 'badge-warning');
    });
    
    // Template selection
    $('#template_id').on('change', function() {
        if ($(this).val()) {
            loadTemplateContent($(this).val());
        }
    });
    
    // Form submission
    $('#smsForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            phone: $('#phone').val(),
            message: $('#message').val(),
            template_id: $('#template_id').val(),
            _token: $('input[name="_token"]').val()
        };
        
        $('#sendBtn').prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Sending...');
        
        $.ajax({
            url: '{{ route("adminconsole.features.sms.send") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('SMS sent successfully!');
                    $('#smsForm')[0].reset();
                    // Re-run counter so badge/max/remaining all reset to their initial state
                    $('#message').trigger('input');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                if (response && response.message) {
                    alert('Error: ' + response.message);
                } else {
                    alert('An error occurred while sending SMS');
                }
            },
            complete: function() {
                $('#sendBtn').prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-paper-plane') : '<i class="fas fa-paper-plane"></i>') + ' Send SMS');
            }
        });
    });
    
    function loadTemplates() {
        $.ajax({
            url: '{{ route("clients.sms.templates.active") }}',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const select = $('#template_id');
                    response.data.forEach(function(template) {
                        $('<option></option>').val(template.id).text(template.title).appendTo(select);
                    });
                }
            }
        });
    }
    
    function loadTemplateContent(templateId) {
        $.ajax({
            url: '/clients/sms-template/' + templateId + '/compose',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    var msg = response.data.message || '';
                    var smsBodyMaxChars = 320;
                    if (msg.length > smsBodyMaxChars) {
                        msg = msg.slice(0, smsBodyMaxChars);
                        alert('Template message was shortened to ' + smsBodyMaxChars + ' characters (2 SMS max).');
                    }
                    $('#message').val(msg).trigger('input');
                }
            }
        });
    }
});
</script>
@endsection
