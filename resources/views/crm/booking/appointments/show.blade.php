@extends('layouts.crm_client_detail')
@section('title', 'Appointment Details - #' . $appointment->id)

@section('content')

<style>
html, body {
    overflow-x: hidden !important;
    max-width: 100% !important;
}

.info-card {
    margin-bottom: 20px;
}

.info-card .card-header {
    font-weight: bold;
}

.info-row {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #6c757d;
}

.info-value {
    color: #212529;
}

.action-buttons .btn {
    margin: 5px;
}

.note-item {
    background: #f8f9fa;
    padding: 10px;
    border-left: 3px solid #007bff;
    margin-bottom: 10px;
}

.note-item .note-meta {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 5px;
}
</style>

<div class="section-body">
    <div class="row">
        <div class="col-12">
            <!-- Back Button -->
            <div class="mb-3">
                <a href="{{ route('booking.appointments.index') }}" class="btn btn-sm btn-secondary">
                    @icon('fa-arrow-left') Back to List
                </a>
                <a href="{{ route('booking.sync.dashboard') }}" class="btn btn-sm btn-info">
                    @icon('fa-sync') Sync Status
                </a>
            </div>

            <!-- Main Card -->
            <div class="card">
                <div class="card-header">
                    <h4>
                        @icon('fa-calendar-check', ['class' => 'mr-2'])
                        Appointment Details - #{{ $appointment->id }}
                    </h4>
                    <div class="card-header-action">
                        @php
                            $statusClass = match($appointment->status) {
                                'pending' => 'warning',
                                'confirmed' => 'success',
                                'completed' => 'info',
                                'cancelled' => 'danger',
                                'no_show' => 'dark',
                                default => 'secondary'
                            };
                        @endphp
                        <span class="badge badge-{{ $statusClass }} badge-lg">
                            {{ ucfirst($appointment->status) }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <!-- Client Information -->
                            <div class="card info-card">
                                <div class="card-header bg-primary text-white">
                                    @icon('fa-user') Client Information
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Name:</div>
                                            <div class="col-8 info-value">{{ $appointment->client_name }}</div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Email:</div>
                                            <div class="col-8 info-value">
                                                <a href="mailto:{{ $appointment->client_email }}">{{ $appointment->client_email }}</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Phone:</div>
                                            <div class="col-8 info-value">
                                                <a href="tel:{{ $appointment->client_phone }}">{{ $appointment->client_phone }}</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Timezone:</div>
                                            <div class="col-8 info-value">{{ $appointment->client_timezone ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                    @if($appointment->client_id)
                                    @php
                                        $clientDetailParams = [base64_encode(convert_uuencode($appointment->client_id))];
                                        $latestMatterRef = optional($latestClientMatter)->client_unique_matter_no;
                                        if (!empty($latestMatterRef)) {
                                            $clientDetailParams[] = $latestMatterRef;
                                        }
                                    @endphp
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-12">
                                                <a href="{{ route('clients.detail', $clientDetailParams) }}" class="btn btn-sm btn-info btn-block" target="_blank">
                                                    @icon('fa-external-link-alt') View Client Profile
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Appointment Details -->
                            <div class="card info-card">
                                <div class="card-header bg-info text-white">
                                    @icon('fa-calendar') Appointment Details
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Date & Time:</div>
                                            <div class="col-8 info-value">
                                                <strong>{{ $appointment->appointment_datetime->format('l, d M Y') }}</strong><br>
                                                <small>{{ $appointment->appointment_datetime->format('h:i A') }}</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Duration:</div>
                                            <div class="col-8 info-value">{{ $appointment->duration_minutes }} minutes</div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Location:</div>
                                            <div class="col-8 info-value">
                                                {{ ucfirst($appointment->location) }}
                                                @if($appointment->location === 'inperson' && $appointment->inperson_address)
                                                    <br><small>{{ $appointment->inperson_address }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Meeting Type:</div>
                                            <div class="col-8 info-value">{{ ucfirst($appointment->meeting_type ?? 'N/A') }}</div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Language:</div>
                                            <div class="col-8 info-value">{{ $appointment->preferred_language ?? 'English' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Service Information -->
                            <div class="card info-card">
                                <div class="card-header bg-secondary text-white">
                                    @icon('fa-briefcase') Service Information
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Service Type:</div>
                                            <div class="col-8 info-value">{{ $appointment->service_type ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Enquiry Type:</div>
                                            <div class="col-8 info-value">{{ $appointment->enquiry_type ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Consultant:</div>
                                            <div class="col-8 info-value">
                                                @if($appointment->consultant)
                                                    {{ $appointment->consultant->crm_display_label }}
                                                    @if($appointment->consultant->show_in_filter !== false && $appointment->consultant->crm_display_label !== $appointment->consultant->calendar_type_display)
                                                        <br><small class="text-muted">{{ $appointment->consultant->calendar_type_display }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">Not Assigned</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @if($appointment->enquiry_details)
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-12 info-label">Enquiry Details:</div>
                                            <div class="col-12 info-value mt-2">
                                                <div class="alert alert-light">
                                                    {{ $appointment->enquiry_details }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-md-6">
                            <!-- Payment Information -->
                            @if($appointment->is_paid)
                            <div class="card info-card">
                                <div class="card-header bg-success text-white">
                                    @icon('fa-dollar-sign') Payment Information
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Status:</div>
                                            <div class="col-8 info-value">
                                                <span class="badge badge-success">Paid</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Amount:</div>
                                            <div class="col-8 info-value">${{ number_format($appointment->amount, 2) }}</div>
                                        </div>
                                    </div>
                                    @if($appointment->discount_amount > 0)
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Discount:</div>
                                            <div class="col-8 info-value text-success">-${{ number_format($appointment->discount_amount, 2) }}</div>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Final Amount:</div>
                                            <div class="col-8 info-value">
                                                <strong>${{ number_format($appointment->final_amount, 2) }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    @if($appointment->promo_code)
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Promo Code:</div>
                                            <div class="col-8 info-value">
                                                <span class="badge badge-info">{{ $appointment->promo_code }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Payment Method:</div>
                                            <div class="col-8 info-value">{{ ucfirst($appointment->payment_method ?? 'N/A') }}</div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-4 info-label">Paid At:</div>
                                            <div class="col-8 info-value">
                                                {{ $appointment->paid_at ? $appointment->paid_at->format('d M Y, h:i A') : 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Admin Notes -->
                            <div class="card info-card">
                                <div class="card-header bg-warning text-dark">
                                    @icon('fa-sticky-note') Admin Notes
                                    <button class="btn btn-sm btn-light float-right" onclick="addNote()">
                                        @icon('fa-plus') Add
                                    </button>
                                </div>
                                <div class="card-body">
                                    @if($appointment->admin_notes)
                                        @php
                                            $notes = is_string($appointment->admin_notes) ? json_decode($appointment->admin_notes, true) : $appointment->admin_notes;
                                        @endphp
                                        @if(is_array($notes) && count($notes) > 0)
                                            @foreach($notes as $note)
                                            <div class="note-item">
                                                <div class="note-meta">
                                                    <strong>{{ $note['author'] ?? 'Admin' }}</strong> - 
                                                    {{ $note['created_at'] ?? now()->format('d M Y, h:i A') }}
                                                </div>
                                                <div class="note-content">
                                                    {{ $note['content'] ?? $note }}
                                                </div>
                                            </div>
                                            @endforeach
                                        @else
                                            <p class="text-muted">{{ $appointment->admin_notes }}</p>
                                        @endif
                                    @else
                                        <p class="text-muted">No admin notes yet.</p>
                                    @endif
                                </div>
                            </div>

                            <!-- Sync Information -->
                            <div class="card info-card">
                                <div class="card-header bg-light">
                                    @icon('fa-sync') Sync Information
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-5 info-label">Bansal ID:</div>
                                            <div class="col-7 info-value">
                                                <code>{{ $appointment->bansal_appointment_id }}</code>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-5 info-label">Order Hash:</div>
                                            <div class="col-7 info-value">
                                                <small><code>{{ $appointment->order_hash }}</code></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-5 info-label">First Synced:</div>
                                            <div class="col-7 info-value">
                                                {{ $appointment->synced_from_bansal_at ? $appointment->synced_from_bansal_at->format('d M Y, h:i A') : 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-5 info-label">Last Updated:</div>
                                            <div class="col-7 info-value">
                                                {{ $appointment->last_synced_at ? $appointment->last_synced_at->format('d M Y, h:i A') : 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-5 info-label">Sync Status:</div>
                                            <div class="col-7 info-value">
                                                @php
                                                    $syncStatus = $appointment->sync_status ?? 'new';
                                                    $syncStatusClass = 'secondary';
                                                    $syncStatusText = ucfirst($syncStatus);
                                                    
                                                    switch($syncStatus) {
                                                        case 'synced':
                                                            $syncStatusClass = 'success';
                                                            $syncStatusText = 'Synced';
                                                            break;
                                                        case 'error':
                                                            $syncStatusClass = 'danger';
                                                            $syncStatusText = 'Error';
                                                            break;
                                                        case 'new':
                                                            $syncStatusClass = 'warning';
                                                            $syncStatusText = 'New';
                                                            break;
                                                        default:
                                                            $syncStatusClass = 'secondary';
                                                    }
                                                @endphp
                                                <span class="badge badge-{{ $syncStatusClass }}">{{ $syncStatusText }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification History -->
                            <div class="card info-card">
                                <div class="card-header bg-info text-white">
                                    @icon('fa-bell') Notification History
                                </div>
                                <div class="card-body">
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-6 info-label">Confirmation Email:</div>
                                            <div class="col-6 info-value">
                                                @if($appointment->confirmation_email_sent)
                                                    <span class="badge badge-success">Sent</span><br>
                                                    <small>{{ $appointment->confirmation_email_sent_at?->format('d M Y') }}</small>
                                                @else
                                                    <span class="badge badge-secondary">Not Sent</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="row">
                                            <div class="col-6 info-label">Reminder SMS:</div>
                                            <div class="col-6 info-value">
                                                @if($appointment->reminder_sms_sent)
                                                    <span class="badge badge-success">Sent</span><br>
                                                    <small>{{ $appointment->reminder_sms_sent_at?->format('d M Y') }}</small>
                                                @else
                                                    <span class="badge badge-secondary">Not Sent</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            @icon('fa-bolt') Quick Actions
                        </div>
                        <div class="card-body text-center action-buttons">
                            @if($appointment->status === 'pending')
                            <button class="btn btn-success" onclick="updateStatus('confirmed')">
                                @icon('fa-check') Confirm Appointment
                            </button>
                            @endif
                            
                            @if(in_array($appointment->status, ['pending', 'confirmed']))
                            <button class="btn btn-primary" onclick="markCompleteAppointment()">
                                @icon('fa-check-circle') Mark Completed
                            </button>
                            <button class="btn btn-danger" onclick="cancelAppointment()">
                                @icon('fa-times') Cancel
                            </button>
                            @endif
                            
                            <!--<button class="btn btn-warning" onclick="sendReminder()">
                                @icon('fa-envelope') Send Reminder
                            </button>-->
                            
                            <button class="btn btn-info" onclick="sendSMS()">
                                @icon('fa-sms') Send SMS
                            </button>
                            
                            <button class="btn btn-secondary" onclick="window.print()">
                                @icon('fa-print') Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStatus(newStatus) {
    if (!confirm('Update appointment status to ' + newStatus + '?')) {
        return;
    }
    
    $.ajax({
        url: '{{ route("booking.appointments.update-status", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            status: newStatus
        },
        success: function(response) {
            if (response.success) {
                alert('Status updated successfully!');
                window.location.reload();
            }
        },
        error: function() {
            alert('Failed to update status');
        }
    });
}

function cancelAppointment() {
    const reason = prompt('Please enter cancellation reason (required):');
    if (!reason || reason.trim() === '') {
        alert('Cancellation reason is required. Operation cancelled.');
        return;
    }
    
    $.ajax({
        url: '{{ route("booking.appointments.update-status", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            status: 'cancelled',
            cancellation_reason: reason.trim()
        },
        success: function(response) {
            if (response.success) {
                alert('Appointment cancelled successfully!');
                window.location.reload();
            }
        },
        error: function() {
            alert('Failed to cancel appointment');
        }
    });
}

function markCompleteAppointment() {
    if (!confirm('Mark appointment as completed?')) {
        return;
    }
    
    $.ajax({
        url: '{{ route("booking.appointments.update-status", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            status: 'completed'
        },
        success: function(response) {
            if (response.success) {
                alert('Appointment completed successfully!');
                window.location.reload();
            }
        },
        error: function() {
            alert('Failed to complete appointment');
        }
    });
}

function sendReminder() {
    if (!confirm('Send appointment reminder to {{ $appointment->client_email }}?')) {
        return;
    }
    
    $.ajax({
        url: '{{ route("booking.appointments.send-reminder", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (response.success) {
                alert('Reminder sent successfully!');
            }
        },
        error: function() {
            alert('Failed to send reminder');
        }
    });
}

function sendSMS() {
    if (!confirm('Send SMS reminder to {{ $appointment->client_phone }}?')) {
        return;
    }
    
    // Implement SMS sending functionality
    alert('SMS functionality will be implemented');
}

function addNote() {
    const note = prompt('Enter admin note:');
    if (!note) return;
    
    $.ajax({
        url: '{{ route("booking.appointments.add-note", $appointment->id) }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            note: note
        },
        success: function(response) {
            if (response.success) {
                alert('Note added successfully!');
                window.location.reload();
            }
        },
        error: function() {
            alert('Failed to add note');
        }
    });
}
</script>

@endsection

