@extends('layouts.crm_client_detail')
@section('title', 'Create SMS Template')

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
                            <h4>@icon('fa-plus') Create SMS Template</h4>
                            <div class="card-header-action">
                                <a href="{{ route('adminconsole.features.sms.templates.index') }}" class="btn btn-secondary">
                                    @icon('fa-arrow-left') Back to Templates
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="templateForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="title">Template Title <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="title" name="title" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="category">Category</label>
                                            <select class="form-control" id="category" name="category">
                                                <option value="">-- Select Category --</option>
                                                <option value="notification">Notification</option>
                                                <option value="reminder">Reminder</option>
                                                <option value="verification">Verification</option>
                                                <option value="manual">Manual</option>
                                            </select>
                                            <small class="form-text text-muted">Controls logging type and activity label.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Brief description of when to use this template"></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Message Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="Enter your template message here..." required></textarea>
                                    <small class="form-text text-muted">
                                        <span id="charCount">0</span>/1600 characters
                                        <br>Use variables like {client_name}, {appointment_date}, etc.
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="variables">Available Variables (JSON)</label>
                                    <textarea class="form-control" id="variables" name="variables" rows="3" placeholder='{"client_name": "Client Name", "appointment_date": "Appointment Date"}'></textarea>
                                    <small class="form-text text-muted">Define variables that can be used in the message template</small>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                                        <label class="form-check-label" for="is_active">
                                            Active (can be used for sending SMS)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" id="saveBtn">
                                        @icon('fa-save') Create Template
                                    </button>
                                    <a href="{{ route('adminconsole.features.sms.templates.index') }}" class="btn btn-secondary">
                                        @icon('fa-times') Cancel
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
    // Character count
    $('#message').on('input', function() {
        $('#charCount').text($(this).val().length);
    });
    
    // Form submission
    $('#templateForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            title: $('#title').val(),
            message: $('#message').val(),
            description: $('#description').val(),
            category: $('#category').val(),
            variables: $('#variables').val(),
            is_active: $('#is_active').is(':checked') ? 1 : 0,
            _token: $('input[name="_token"]').val()
        };
        
        $('#saveBtn').prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Creating...');
        
        $.ajax({
            url: '{{ route("adminconsole.features.sms.templates.store") }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Template created successfully!');
                    window.location.href = '{{ route("adminconsole.features.sms.templates.index") }}';
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                if (response && response.message) {
                    alert('Error: ' + response.message);
                } else {
                    alert('An error occurred while creating the template');
                }
            },
            complete: function() {
                $('#saveBtn').prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-save') : '<i class="fas fa-save"></i>') + ' Create Template');
            }
        });
    });
});
</script>
@endsection
