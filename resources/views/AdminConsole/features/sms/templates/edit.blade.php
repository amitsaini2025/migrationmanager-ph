@extends('layouts.crm_client_detail')
@section('title', 'Edit SMS Template')

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
                            <h4>@icon('fa-edit') Edit SMS Template</h4>
                            <div class="card-header-action">
                                <a href="{{ route('adminconsole.features.sms.templates.index') }}" class="btn btn-secondary">
                                    @icon('fa-arrow-left') Back to Templates
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <form id="templateForm">
                                @csrf
                                @method('PUT')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="title">Template Title <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="title" name="title" value="{{ $template->title }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="category">Category</label>
                                            <select class="form-control" id="category" name="category">
                                                <option value="">-- Select Category --</option>
                                                <option value="notification" {{ $template->category === 'notification' ? 'selected' : '' }}>Notification</option>
                                                <option value="reminder"     {{ $template->category === 'reminder'     ? 'selected' : '' }}>Reminder</option>
                                                <option value="verification" {{ $template->category === 'verification' ? 'selected' : '' }}>Verification</option>
                                                <option value="manual"       {{ $template->category === 'manual'       ? 'selected' : '' }}>Manual</option>
                                            </select>
                                            <small class="form-text text-muted">Controls logging type and activity label.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Brief description of when to use this template">{{ $template->description }}</textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Message Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="Enter your template message here..." required>{{ $template->message }}</textarea>
                                    <small class="form-text text-muted">
                                        <span id="charCount">{{ strlen($template->message) }}</span>/1600 characters
                                        <br>Use variables like {client_name}, {appointment_date}, etc.
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="variables">Available Variables (JSON)</label>
                                    <textarea class="form-control" id="variables" name="variables" rows="3" placeholder='{"client_name": "Client Name", "appointment_date": "Appointment Date"}'>{{ $template->variables }}</textarea>
                                    <small class="form-text text-muted">Define variables that can be used in the message template</small>
                                </div>
                                
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ $template->is_active ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active (can be used for sending SMS)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary" id="saveBtn">
                                        @icon('fa-save') Update Template
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
            _token: $('input[name="_token"]').val(),
            _method: 'PUT'
        };
        
        $('#saveBtn').prop('disabled', true).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Updating...');
        
        $.ajax({
            url: '{{ route("adminconsole.features.sms.templates.update", $template->id) }}',
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Template updated successfully!');
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
                    alert('An error occurred while updating the template');
                }
            },
            complete: function() {
                $('#saveBtn').prop('disabled', false).html((typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-save') : '<i class="fas fa-save"></i>') + ' Update Template');
            }
        });
    });
});
</script>
@endsection
