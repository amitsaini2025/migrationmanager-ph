@extends('layouts.crm_client_detail')
@section('title', 'Edit Appointment - #' . $appointment->id)

@section('content')
<div class="section-body">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>
                    @icon('fa-edit', ['class' => 'mr-2'])
                    Edit Appointment - #{{ $appointment->id }}
                </h4>
                <a href="{{ route('booking.appointments.show', $appointment->id) }}" class="btn btn-sm btn-secondary">
                    @icon('fa-arrow-left') Back to Appointment Details
                </a>
            </div>

            <div class="alert alert-info">
                @icon('fa-info-circle')
                Use this form to update the appointment date, time, meeting type, and preferred language.
            </div>

            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif

            @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            @endif

            <div class="card">
                <div class="card-header bg-info text-white">
                    @icon('fa-calendar-alt') Update Date & Time
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('booking.appointments.update', $appointment->id) }}" class="needs-validation" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="appointment-date">Appointment Date</label>
                                <input type="date" class="form-control" id="appointment-date" name="appointment_date" value="{{ old('appointment_date', $appointment->appointment_datetime->format('Y-m-d')) }}" required onchange="validateWeekendDate(this)" data-original-date="{{ $appointment->appointment_datetime->format('Y-m-d') }}">
                                <div class="invalid-feedback">
                                    Please select a valid appointment date.
                                </div>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="appointment-time">Appointment Time</label>
                                <input type="time" class="form-control" id="appointment-time" name="appointment_time" value="{{ old('appointment_time', $appointment->appointment_datetime->format('H:i')) }}" required>
                                <div class="invalid-feedback">
                                    Please select a valid appointment time.
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Client</label>
                                <p class="mb-1">{{ $appointment->client_name }} <small class="text-muted">({{ $appointment->client_email }})</small></p>
                                <p class="mb-1"><small>{{ $appointment->client_phone }}</small></p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Current Schedule</label>
                                <p class="mb-1">{{ $appointment->appointment_datetime->format('l, d M Y') }}</p>
                                <p class="mb-0"><small>{{ $appointment->appointment_datetime->format('h:i A') }}</small></p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Time Zone</label>
                                <p class="mb-0">{{ $appointment->client_timezone ?? config('app.timezone') }}</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Location</label>
                                <p class="mb-0">
                                    @if($appointment->location === 'melbourne')
                                        Melbourne Office
                                    @elseif($appointment->location === 'adelaide')
                                        Adelaide Office
                                    @else
                                        {{ ucfirst($appointment->location ?? 'N/A') }}
                                    @endif
                                </p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Nature of Enquiry</label>
                                <p class="mb-0">
                                    @php
                                        $noeMap = [
                                            1 => 'GSM Visas: 491, 190, 189, 191',
                                            9 => 'EOI/ROI',
                                            2 => 'TR: 485 visa',
                                            10 => 'Employer Sponsored Visas: 494, 482, 186, DAMA',
                                            3 => 'JRP/Skill Assessment',
                                            4 => 'Tourist Visa',
                                            5 => 'Education/Course Change/Student Visa/Student Dependent Visa (for education selection only)',
                                            6 => 'Complex matters: ART, Protection visa, Federal Case',
                                            7 => 'Visa Cancellation/ NOICC/ Visa refusals',
                                            11 => 'Family Visas (Parent Visa, Partner Visa, Child Visa)',
                                            12 => 'Citizenship',
                                            8 => 'Anyone who is outside Australia',
                                        ];
                                        $noeDisplay = $appointment->noe_id ? ($noeMap[$appointment->noe_id] ?? 'N/A') : 'N/A';
                                    @endphp
                                    {{ $noeDisplay }}
                                </p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Service Type</label>
                                <p class="mb-0">
                                    @php
                                        $serviceTypeDisplay = 'N/A';
                                        if ($appointment->service_id == 2) {
                                            $serviceTypeDisplay = 'Free Service';
                                        } elseif ($appointment->service_id == 1) {
                                            $serviceTypeDisplay = 'Paid Service Migration advice';
                                        } elseif ($appointment->service_id == 3) {
                                            $serviceTypeDisplay = 'Overseas applicant enquiry';
                                        }
                                    @endphp
                                    {{ $serviceTypeDisplay }}
                                </p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Bansal ID</label>
                                <p class="mb-0">
                                    @if($appointment->bansal_appointment_id)
                                        <span style="color: #e91e63; font-weight: bold;">{{ $appointment->bansal_appointment_id }}</span>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </p>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="font-weight-bold">Sync Status</label>
                                <p class="mb-0">
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
                                    @if($appointment->sync_error)
                                        <br><small class="text-danger mt-1">{{ Str::limit($appointment->sync_error, 50) }}</small>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="meeting-type">Meeting Type <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" id="meeting-type" name="meeting_type" required style="max-width: 250px;">
                                    <option value="in_person" {{ old('meeting_type', $appointment->meeting_type) == 'in_person' ? 'selected' : '' }}>In Person</option>
                                    <option value="phone" {{ old('meeting_type', $appointment->meeting_type) == 'phone' ? 'selected' : '' }}>Phone</option>
                                    @if($appointment->is_paid)
                                    <option value="video" {{ old('meeting_type', $appointment->meeting_type) == 'video' ? 'selected' : '' }}>Video</option>
                                    @endif
                                </select>
                                <div class="invalid-feedback">
                                    Please select a meeting type.
                                </div>
                                @if(!$appointment->is_paid)
                                <small class="form-text text-muted">Video meeting type is only available for paid appointments.</small>
                                @endif
                            </div>
                            <div class="form-group col-md-4">
                                <label for="preferred-language">Preferred Language <span class="text-danger">*</span></label>
                                @php
                                    $preferredLangInput = old('preferred_language', $appointment->preferred_language ?? 'English');
                                    $preferredLangKey = strtolower(trim((string) $preferredLangInput));
                                @endphp
                                <select class="form-control form-control-sm" id="preferred-language" name="preferred_language" required style="max-width: 250px;">
                                    <option value="English" {{ $preferredLangKey === 'english' ? 'selected' : '' }}>English</option>
                                    <option value="Hindi" {{ $preferredLangKey === 'hindi' ? 'selected' : '' }}>Hindi</option>
                                    <option value="Punjabi" {{ $preferredLangKey === 'punjabi' ? 'selected' : '' }}>Punjabi</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a preferred language.
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">
                                @icon('fa-save') Update Appointment
                            </button>
                            <a href="{{ route('booking.appointments.index') }}" class="btn btn-light ml-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Validate weekend date function
window.validateWeekendDate = function(dateInput, appointmentId) {
    if (!dateInput.value) {
        return;
    }
    
    const selectedDate = new Date(dateInput.value);
    const dayOfWeek = selectedDate.getDay(); // 0 = Sunday, 6 = Saturday
    
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        // Weekend selected - reset to original date
        const originalDate = dateInput.getAttribute('data-original-date') || '{{ $appointment->appointment_datetime->format("Y-m-d") }}';
        dateInput.value = originalDate;
        
        alert('Weekends (Saturday and Sunday) are not available for appointments. Please select a weekday.');
        return false;
    }
    
    return true;
};
</script>
@endsection
