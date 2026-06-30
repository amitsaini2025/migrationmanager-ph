@extends('layouts.crm_client_detail')
@section('title', 'Appointment Sync Dashboard')

@section('content')

<style>
html, body {
    overflow-x: hidden !important;
    max-width: 100% !important;
}

.sync-status-card {
    border-left: 4px solid;
}

.sync-status-card.success {
    border-left-color: #28a745;
}

.sync-status-card.error {
    border-left-color: #dc3545;
}

.sync-status-card.warning {
    border-left-color: #ffc107;
}

.sync-log-table td {
    font-size: 0.9rem;
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.status-indicator.success {
    background-color: #28a745;
}

.status-indicator.error {
    background-color: #dc3545;
}

.status-indicator.running {
    background-color: #007bff;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.metric-card {
    text-align: center;
    padding: 20px;
}

.metric-card h2 {
    font-size: 3rem;
    margin: 10px 0;
    font-weight: bold;
}

.metric-card p {
    color: #6c757d;
    margin: 0;
}
</style>

<div class="section-body">
    <div class="row">
        <div class="col-12">
            <!-- Back Button -->
            <div class="mb-3">
                <a href="{{ route('booking.appointments.index') }}" class="btn btn-sm btn-secondary">
                    @icon('fa-arrow-left') Back to Appointments
                </a>
                <button onclick="location.reload()" class="btn btn-sm btn-info">
                    @icon('fa-sync') Refresh
                </button>
            </div>

            <!-- Sync Status Overview -->
            <div class="card">
                <div class="card-header">
                    <h4>
                        @icon('fa-sync-alt', ['class' => 'mr-2'])
                        Appointment Sync Dashboard
                    </h4>
                    <div class="card-header-action">
                        @if(Auth::user() && in_array(Auth::user()->role, [1, 12]))
                        <button onclick="triggerManualSync()" class="btn btn-primary">
                            @icon('fa-sync-alt') Manual Sync Now
                        </button>
                        <button onclick="testConnection()" class="btn btn-info">
                            @icon('fa-plug') Test Connection
                        </button>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- System Status -->
                        <div class="col-md-4">
                            <div class="card sync-status-card {{ $systemStatus['status'] }}">
                                <div class="card-body">
                                    <h5>
                                        <span class="status-indicator {{ $systemStatus['status'] }}"></span>
                                        System Status
                                    </h5>
                                    <p class="mb-0">
                                        <strong>{{ ucfirst($systemStatus['status']) }}</strong>
                                    </p>
                                    <small class="text-muted">{{ $systemStatus['message'] ?? 'All systems operational' }}</small>
                                </div>
                            </div>
                        </div>

                        <!-- Last Sync -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5>@icon('fa-clock', ['class' => 'mr-2'])Last Sync</h5>
                                    <p class="mb-0">
                                        @if($lastSync)
                                            <strong>{{ $lastSync->created_at->diffForHumans() }}</strong><br>
                                            <small class="text-muted">{{ $lastSync->created_at->format('d M Y, h:i A') }}</small>
                                        @else
                                            <span class="text-muted">No sync yet</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Next Sync -->
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5>@icon('fa-calendar-check', ['class' => 'mr-2'])Next Scheduled Sync</h5>
                                    <p class="mb-0">
                                        <strong>{{ $nextSync ?? 'Within 10 minutes' }}</strong><br>
                                        <small class="text-muted">Runs every 10 minutes</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sync Statistics -->
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body metric-card">
                            @icon('fa-download', ['class' => 'text-primary', 'style' => 'font-size: 2rem;'])
                            <h2 class="text-primary">{{ $stats['total_synced'] ?? 0 }}</h2>
                            <p>Total Synced</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body metric-card">
                            @icon('fa-calendar-day', ['class' => 'text-success', 'style' => 'font-size: 2rem;'])
                            <h2 class="text-success">{{ $stats['today'] ?? 0 }}</h2>
                            <p>Today's Syncs</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body metric-card">
                            @icon('fa-exclamation-triangle', ['class' => 'text-warning', 'style' => 'font-size: 2rem;'])
                            <h2 class="text-warning">{{ $stats['failed'] ?? 0 }}</h2>
                            <p>Failed Syncs</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card">
                        <div class="card-body metric-card">
                            @icon('fa-check-circle', ['class' => 'text-info', 'style' => 'font-size: 2rem;'])
                            <h2 class="text-info">{{ $stats['success_rate'] ?? 100 }}%</h2>
                            <p>Success Rate</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Sync History -->
            <div class="card">
                <div class="card-header">
                    <h4>@icon('fa-history', ['class' => 'mr-2'])Recent Sync History</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped sync-log-table">
                            <thead>
                                <tr>
                                    <th width="50">ID</th>
                                    <th>Sync Time</th>
                                    <th>Status</th>
                                    <th>Fetched</th>
                                    <th>New</th>
                                    <th>Updated</th>
                                    <th>Failed</th>
                                    <th>Duration</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($syncLogs as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>
                                        {{ $log->created_at->format('d M Y') }}<br>
                                        <small class="text-muted">{{ $log->created_at->format('h:i A') }}</small>
                                    </td>
                                    <td>
                                        @if($log->status === 'success')
                                            <span class="badge badge-success">Success</span>
                                        @elseif($log->status === 'failed')
                                            <span class="badge badge-danger">Failed</span>
                                        @elseif($log->status === 'running')
                                            <span class="badge badge-info">Running</span>
                                        @else
                                            <span class="badge badge-secondary">{{ ucfirst($log->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->appointments_fetched ?? 0 }}</td>
                                    <td><span class="text-success">{{ $log->appointments_created ?? 0 }}</span></td>
                                    <td><span class="text-info">{{ $log->appointments_updated ?? 0 }}</span></td>
                                    <td>
                                        @if(($log->appointments_failed ?? 0) > 0)
                                            <span class="text-danger">{{ $log->appointments_failed }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->duration_seconds ?? 0 }}s</td>
                                    <td>
                                        @if($log->error_message)
                                            <button class="btn btn-sm btn-outline-danger" onclick="showError('{{ addslashes($log->error_message) }}')">
                                                @icon('fa-exclamation-circle') Error
                                            </button>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        No sync logs found
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    @if($syncLogs->hasPages())
                    <div class="d-flex justify-content-center mt-3">
                        {{ $syncLogs->links() }}
                    </div>
                    @endif
                </div>
            </div>

            <!-- API Configuration -->
            <div class="card">
                <div class="card-header">
                    <h4>@icon('fa-cog', ['class' => 'mr-2'])API Configuration</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>API Endpoint:</strong></p>
                            <p class="text-muted">
                                <code>{{ config('services.bansal_api.base_url') }}</code>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Sync Frequency:</strong></p>
                            <p class="text-muted">Every 10 minutes (via cron)</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Lookback Period:</strong></p>
                            <p class="text-muted">15 minutes per sync</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Timeout:</strong></p>
                            <p class="text-muted">{{ config('services.bansal_api.timeout', 30) }} seconds</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Error Detail Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Sync Error Details</h5>
                <button type="button" class="close text-white" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <pre id="errorDetails" style="white-space: pre-wrap;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function triggerManualSync() {
    if (!confirm('Start manual sync now? This may take a few minutes.')) {
        return;
    }
    
    // Show loading with SweetAlert if available, otherwise use alert
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Syncing...',
            text: 'Fetching appointments from Bansal website',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
    
    $.ajax({
        url: '{{ route("booking.sync.manual") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Sync Completed!',
                    html: `
                        <div class="text-left">
                            <p><strong>Fetched:</strong> ${response.stats.fetched}</p>
                            <p><strong>New:</strong> ${response.stats.new}</p>
                            <p><strong>Updated:</strong> ${response.stats.updated}</p>
                            <p><strong>Skipped:</strong> ${response.stats.skipped}</p>
                            <p><strong>Failed:</strong> ${response.stats.failed}</p>
                        </div>
                    `,
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                alert('Sync completed!\nFetched: ' + response.stats.fetched + '\nNew: ' + response.stats.new);
                window.location.reload();
            }
        },
        error: function(xhr) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Sync Failed',
                    text: xhr.responseJSON?.message || 'An error occurred during sync',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Sync failed: ' + (xhr.responseJSON?.message || 'An error occurred'));
            }
        }
    });
}

function testConnection() {
    // Show loading
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Testing Connection...',
            text: 'Connecting to Bansal API',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }
    
    $.ajax({
        url: '{{ url("/booking/sync/test-connection") }}',
        method: 'GET',
        success: function(response) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Connection Successful!',
                    text: response.message || 'API connection is working properly',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Connection successful!');
            }
        },
        error: function(xhr) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Failed',
                    text: xhr.responseJSON?.message || 'Could not connect to Bansal API',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Connection failed: ' + (xhr.responseJSON?.message || 'Could not connect'));
            }
        }
    });
}

function showError(errorMessage) {
    $('#errorDetails').text(errorMessage);
    $('#errorModal').modal('show');
}

// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>

@endsection

