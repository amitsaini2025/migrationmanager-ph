@extends('layouts.crm_client_detail')
@section('title', 'Activity Search')

@section('content')

<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg"></div>
            
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h4>@icon('fa-search') Activity Search</h4>
                            <div class="card-header-action">
                                @if(isset($totalActivities) && $totalActivities > 0)
                                    <button type="button" class="btn btn-success" onclick="exportActivities()">
                                        @icon('fa-file-export') Export Results
                                    </button>
                                @endif
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <!-- Search Form -->
                            <form action="{{ route('adminconsole.system.activity-search.index') }}" method="GET" id="searchForm">
                                <input type="hidden" name="search" value="1">
                                
                                <div class="row">
                                    <!-- Assigner Filter -->
                                    <div class="col-md-6 mb-3">
                                        <label for="assigner_id" class="form-label">
                                            @icon('fa-user-tag') Assigner (Who Created)
                                        </label>
                                        <select name="assigner_id" id="assigner_id" class="form-control mm-select">
                                            <option value="">All Assigners</option>
                                            @foreach($staffList as $staff)
                                                <option value="{{ $staff['id'] }}" 
                                                    {{ request('assigner_id') == $staff['id'] ? 'selected' : '' }}>
                                                    {{ $staff['name'] }} ({{ $staff['email'] }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <!-- Assignee Filter -->
                                    <div class="col-md-6 mb-3">
                                        <label for="assignee_id" class="form-label">
                                            @icon('fa-user-check') Assignee (Assigned To)
                                        </label>
                                        <select name="assignee_id" id="assignee_id" class="form-control mm-select">
                                            <option value="">All Assignees</option>
                                            @foreach($staffList as $staff)
                                                <option value="{{ $staff['id'] }}" 
                                                    {{ request('assignee_id') == $staff['id'] ? 'selected' : '' }}>
                                                    {{ $staff['name'] }} ({{ $staff['email'] }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Client Filter -->
                                    <div class="col-md-6 mb-3">
                                        <label for="client_id" class="form-label">
                                            @icon('fa-user') Client
                                        </label>
                                        <select name="client_id" id="client_id" class="form-control mm-select-ajax">
                                            <option value="">All Clients</option>
                                            @if(request('client_id'))
                                                <option value="{{ request('client_id') }}" selected>
                                                    {{ request('client_name', 'Selected Client') }}
                                                </option>
                                            @endif
                                        </select>
                                    </div>
                                    
                                    <!-- Activity Type Filter -->
                                    <div class="col-md-6 mb-3">
                                        <label for="activity_type" class="form-label">
                                            @icon('fa-list') Activity Type
                                        </label>
                                        <select name="activity_type" id="activity_type" class="form-control mm-select">
                                            <option value="">All Types</option>
                                            @foreach($activityTypes as $key => $label)
                                                <option value="{{ $key }}" 
                                                    {{ request('activity_type') == $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Action Category Filter -->
                                    <div class="col-md-4 mb-3">
                                        <label for="task_group" class="form-label">
                                            @icon('fa-tasks') Action Category
                                        </label>
                                        <select name="task_group" id="task_group" class="form-control mm-select">
                                            <option value="">All Categories</option>
                                            @foreach($taskGroups as $key => $label)
                                                <option value="{{ $key }}" 
                                                    {{ request('task_group') == $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <!-- Action Status Filter -->
                                    <div class="col-md-4 mb-3">
                                        <label for="task_status" class="form-label">
                                            @icon('fa-check-circle') Action Status
                                        </label>
                                        <select name="task_status" id="task_status" class="form-control mm-select">
                                            <option value="">All Statuses</option>
                                            <option value="0" {{ request('task_status') === '0' ? 'selected' : '' }}>Incomplete</option>
                                            <option value="1" {{ request('task_status') === '1' ? 'selected' : '' }}>Completed</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Keyword Filter -->
                                    <div class="col-md-4 mb-3">
                                        <label for="keyword" class="form-label">
                                            @icon('fa-search') Keyword
                                        </label>
                                        <input type="text" name="keyword" id="keyword" class="form-control" 
                                               placeholder="Search in subject/description" 
                                               value="{{ request('keyword') }}">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <!-- Date From -->
                                    <div class="col-md-6 mb-3">
                                        <label for="date_from" class="form-label">
                                            @icon('fa-calendar') Date From
                                        </label>
                                        <input type="date" name="date_from" id="date_from" class="form-control" 
                                               value="{{ request('date_from') }}">
                                    </div>
                                    
                                    <!-- Date To -->
                                    <div class="col-md-6 mb-3">
                                        <label for="date_to" class="form-label">
                                            @icon('fa-calendar') Date To
                                        </label>
                                        <input type="date" name="date_to" id="date_to" class="form-control" 
                                               value="{{ request('date_to') }}">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12 text-right">
                                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                            @icon('fa-redo') Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            @icon('fa-search') Search Activities
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <!-- Results Section -->
                            @if(request('search'))
                                <div class="mt-4">
                                    <h5 class="mb-3">
                                        @icon('fa-list-alt') Search Results 
                                        <span class="badge badge-primary">{{ $totalActivities }} activities found</span>
                                    </h5>
                                    
                                    @if($activities->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Assigner</th>
                                                        <th>Assignee</th>
                                                        <th>Client</th>
                                                        <th>Type</th>
                                                        <th>Subject</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($activities as $activity)
                                                        <tr>
                                                            <td>
                                                                <small>{{ $activity->created_at ? $activity->created_at->format('Y-m-d') : 'N/A' }}</small><br>
                                                                <small class="text-muted">{{ $activity->created_at ? $activity->created_at->format('h:i A') : '' }}</small>
                                                            </td>
                                                            <td>
                                                                <strong>{{ $activity->creator_first_name }} {{ $activity->creator_last_name }}</strong><br>
                                                                <small class="text-muted">{{ $activity->creator_email }}</small>
                                                            </td>
                                                            <td>
                                                                @if($activity->assignee_first_name)
                                                                    <strong>{{ $activity->assignee_first_name }} {{ $activity->assignee_last_name }}</strong><br>
                                                                    <small class="text-muted">{{ $activity->assignee_email }}</small>
                                                                @else
                                                                    <span class="text-muted">N/A</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($activity->client_id)
                                                                    @php
                                                                        $clientDisplayName = trim(($activity->client_first_name ?? '') . ' ' . ($activity->client_last_name ?? ''));
                                                                    @endphp
                                                                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($activity->client_id))) }}" target="_blank">
                                                                        {{ $clientDisplayName !== '' ? $clientDisplayName : 'Client #' . $activity->client_id }}
                                                                    </a>
                                                                @else
                                                                    <span class="text-muted">N/A</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @php
                                                                    $typeLabels = [
                                                                        'activity' => ['label' => 'Activity', 'class' => 'primary'],
                                                                        'sms' => ['label' => 'SMS', 'class' => 'info'],
                                                                        'email' => ['label' => 'Email', 'class' => 'primary'],
                                                                        'document' => ['label' => 'Document', 'class' => 'info'],
                                                                        'note' => ['label' => 'Note', 'class' => 'warning'],
                                                                        'financial' => ['label' => 'Financial', 'class' => 'success'],
                                                                        'lead_converted' => ['label' => 'Lead converted', 'class' => 'success'],
                                                                        'followup_scheduled' => ['label' => 'Action scheduled', 'class' => 'info'],
                                                                        'followup_completed' => ['label' => 'Action completed', 'class' => 'success'],
                                                                        'followup_rescheduled' => ['label' => 'Action rescheduled', 'class' => 'warning'],
                                                                        'followup_cancelled' => ['label' => 'Action cancelled', 'class' => 'danger'],
                                                                    ];
                                                                    $typeInfo = $typeLabels[$activity->activity_type] ?? ['label' => ucfirst($activity->activity_type ?? 'N/A'), 'class' => 'secondary'];
                                                                @endphp
                                                                <span class="badge badge-{{ $typeInfo['class'] }}">{{ $typeInfo['label'] }}</span>
                                                                @if($activity->task_group)
                                                                    <br><small class="text-muted">{{ $activity->task_group }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <strong>{{ \Illuminate\Support\Str::limit($activity->subject, 50) }}</strong>
                                                                @if($activity->description)
                                                                    <br><small class="text-muted">{!! \Illuminate\Support\Str::limit(strip_tags($activity->description), 80) !!}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($activity->task_group)
                                                                    @if($activity->task_status == 1)
                                                                        <span class="badge badge-success">@icon('fa-check') Complete</span>
                                                                    @else
                                                                        <span class="badge badge-warning">@icon('fa-clock') Pending</span>
                                                                    @endif
                                                                @else
                                                                    <span class="text-muted">-</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <button type="button" class="btn btn-sm btn-info" 
                                                                        onclick="viewActivityDetails({{ $activity->id }})"
                                                                        data-bs-toggle="tooltip" title="View Details">
                                                                    @icon('fa-eye')
                                                                </button>
                                                                @if($activity->client_id)
                                                                    <a href="{{ route('clients.detail', base64_encode(convert_uuencode($activity->client_id))) }}" 
                                                                       target="_blank" 
                                                                       class="btn btn-sm btn-primary"
                                                                       data-bs-toggle="tooltip" title="View Client">
                                                                        @icon('fa-user')
                                                                    </a>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Pagination -->
                                        <div class="mt-3">
                                            {{ $activities->links() }}
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            @icon('fa-info-circle') No activities found matching your search criteria. Try adjusting your filters.
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-light text-center">
                                    @icon('fa-search', ['style' => 'font-size: 3rem; opacity: 0.3;'])
                                    <h5 class="mt-3">Search Staff Activities</h5>
                                    <p class="text-muted">Use the filters above to search for activities by assigner, assignee, date range, and more.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Activity Details Modal -->
<div class="modal fade" id="activityDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activity Details</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="activityDetailsContent">
                <div class="text-center">
                    @icon('fa-spinner', ['spin' => true]) Loading...
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
@php
    $activitySearchJsonUrlPrefix = preg_replace('#/0$#', '', route('adminconsole.system.activity-search.activity', ['id' => 0]));
@endphp
<script>
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

$(document).ready(function() {
    // Initialize enhanced selects (mmSelect)
    $('.mm-select').mmSelect({
        placeholder: 'Select an option',
        allowClear: true,
        width: '100%'
    });
    
    // Client search with AJAX (mmSelect)
    $('#client_id').mmSelect({
        placeholder: 'Search for a client...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '{{ route("adminconsole.system.activity-search.search-clients") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 2
    });
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

function resetForm() {
    $('#searchForm')[0].reset();
    $('.mm-select').val(null).trigger('change');
    window.location.href = '{{ route("adminconsole.system.activity-search.index") }}';
}

function exportActivities() {
    // Build export URL with current filters
    const form = document.getElementById('searchForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    
    window.location.href = '{{ route("adminconsole.system.activity-search.export") }}?' + params.toString();
}

function viewActivityDetails(activityId) {
    $('#activityDetailsModal').modal('show');
    $('#activityDetailsContent').html('<div class="text-center py-3">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin') : '<i class="fas fa-spinner fa-spin"></i>') + ' Loading...</div>');
    
    var detailUrl = @json($activitySearchJsonUrlPrefix) + '/' + encodeURIComponent(activityId);
    
    $.ajax({
        url: detailUrl,
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
        },
        success: function(response) {
            if (response.status && response.data) {
                var d = response.data;
                var created = d.created_at ? (typeof formatDisplayDateTime === 'function' ? (formatDisplayDateTime(d.created_at) || d.created_at) : String(d.created_at)) : 'N/A';
                var html = '<div class="activity-details">';
                html += '<table class="table table-borderless">';
                html += '<tr><th width="30%">Activity ID:</th><td>#' + escapeHtml(activityId) + '</td></tr>';
                html += '<tr><th>Subject:</th><td>' + escapeHtml(d.subject || 'N/A') + '</td></tr>';
                html += '<tr><th>Description:</th><td>' + escapeHtml(d.description || 'N/A') + '</td></tr>';
                html += '<tr><th>Activity Type:</th><td>' + escapeHtml(d.activity_type || 'N/A') + '</td></tr>';
                html += '<tr><th>Created At:</th><td>' + escapeHtml(created) + '</td></tr>';
                html += '</table>';
                html += '</div>';
                
                $('#activityDetailsContent').html(html);
            } else {
                $('#activityDetailsContent').html('<div class="alert alert-danger">Failed to load activity details.</div>');
            }
        },
        error: function() {
            $('#activityDetailsContent').html('<div class="alert alert-danger">Error loading activity details.</div>');
        }
    });
}
</script>
@endsection
