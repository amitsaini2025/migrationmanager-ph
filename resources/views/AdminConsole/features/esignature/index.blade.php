@extends('layouts.crm_client_detail')
@section('title', 'E-Signature Management')

@section('styles')
<style>
    /* Same role as .crm-container in client-detail.css (that file is not loaded on this layout) */
    .esignature-analytics-layout {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    .esignature-analytics-layout > .main-content {
        flex: 1;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    .esignature-analytics-card {
        background: #fff;
        border-radius: 12px;
    }

    .esignature-analytics-page {
        max-width: 100%;
        min-width: 0;
    }

    .analytics-dashboard {
        padding: 0;
    }
    
    
    .date-filter {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .date-filter input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .date-filter button {
        padding: 8px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
    }
    
    .date-filter button:hover {
        opacity: 0.9;
    }
    
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }
    
    .kpi-card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #667eea;
    }
    
    .kpi-card h3 {
        font-size: 14px;
        color: #6c757d;
        margin-bottom: 10px;
        font-weight: 500;
    }
    
    .kpi-card .value {
        font-size: 32px;
        font-weight: 700;
        color: #2c3e50;
    }
    
    .kpi-card .trend {
        font-size: 12px;
        margin-top: 5px;
        color: #6c757d;
    }
    
    .charts-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-container {
        background: #f8f9fb;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid #e9ecef;
        min-width: 0;
    }

    /* Chart.js needs a sized box when maintainAspectRatio is false */
    .chart-canvas-wrap {
        position: relative;
        width: 100%;
        height: 280px;
        min-height: 240px;
    }
    
    .chart-container h3 {
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
    }
    
    .data-table {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
    }
    
    .data-table h3 {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
    }
    
    .data-table .table-responsive {
        margin-top: 4px;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .data-table table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    
    .data-table th {
        text-align: left;
        padding: 12px;
        background: #f8f9fa;
        font-weight: 600;
        font-size: 13px;
        color: #6c757d;
        border-bottom: 2px solid #dee2e6;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        color: #2c3e50;
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
    }
    
    .badge-type {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .type-agreement {
        background: #e3f2fd;
        color: #1565c0;
    }
    
    .type-nda {
        background: #f3e5f5;
        color: #6a1b9a;
    }
    
    .type-contract {
        background: #fff3e0;
        color: #e65100;
    }
    
    .type-general {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .esig-progress-track {
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 6px;
    }
    
    .esig-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s ease;
        border-radius: 4px;
    }
    
    .overdue-badge {
        display: inline-block;
        padding: 3px 8px;
        background: #fee;
        color: #c00;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    
    @media (max-width: 767.98px) {
        .date-filter {
            flex-wrap: wrap;
        }
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>
@endsection

@section('content')

<div class="crm-container esignature-analytics-layout">
	<div class="main-content">
		<section class="section">
		<div class="section-body">
		<div class="server-error">
			@include('../Elements/flash-message')
		</div>
		<div class="custom-error-msg"></div>
		<div class="row">
			<div class="col-12 col-lg-3">
	        	@include('../Elements/CRM/setting')
	        </div>
			<div class="col-12 col-lg-9">
				<div class="card esignature-analytics-card border-0 shadow-sm">
					<div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 1rem;">
						<div>
							<h4 class="mb-0">Signature Analytics</h4>
							<small class="text-white-50">Performance insights &amp; metrics</small>
						</div>
						<a href="{{ route('adminconsole.features.esignature.export', ['format' => 'csv', 'start_date' => $startDate, 'end_date' => $endDate]) }}"
						   class="btn btn-sm" style="background: rgba(255,255,255,0.2); color:#fff; border: 1px solid rgba(255,255,255,0.35); font-weight:500;">
							@icon('fa-download', ['class' => 'mr-1']) Export CSV
						</a>
					</div>
					<div class="card-body esignature-analytics-page">
					<div class="analytics-dashboard">
						<!-- Date Filter -->
						<div class="mb-4">
							<form method="GET" action="{{ route('adminconsole.features.esignature.index') }}" class="date-filter">
								<label style="font-size: 14px; color: #6c757d;">From:</label>
								<input type="date" name="start_date" value="{{ $startDate }}">
								<label style="font-size: 14px; color: #6c757d;">To:</label>
								<input type="date" name="end_date" value="{{ $endDate }}">
								<button type="submit">Update</button>
							</form>
						</div>

						<!-- KPI Cards -->
						<div class="kpi-grid">
							<div class="kpi-card">
								<h3>Median Time to Sign</h3>
								<div class="value">{{ $medianHours }}h</div>
								<div class="trend">Average turnaround time</div>
							</div>
							<div class="kpi-card">
								<h3>Completion Rate</h3>
								<div class="value">{{ $completionRate }}%</div>
								<div class="trend">Documents signed vs sent</div>
							</div>
							<div class="kpi-card">
								<h3>Avg Reminders Sent</h3>
								<div class="value">{{ $avgReminders }}</div>
								<div class="trend">Per document</div>
							</div>
							<div class="kpi-card">
								<h3>Currently Overdue</h3>
								<div class="value">{{ $overdueCount }}</div>
								<div class="trend">Pending signatures</div>
							</div>
						</div>

						<!-- Charts Row -->
						<div class="charts-row">
							<!-- Signature Trend Chart -->
							<div class="chart-container">
								<h3>📈 Signature Trends</h3>
								<div class="chart-canvas-wrap">
									<canvas id="signatureTrendChart"></canvas>
								</div>
							</div>

							<!-- Document Type Chart -->
							<div class="chart-container">
								<h3>📄 Documents by Type</h3>
								<div class="chart-canvas-wrap">
									<canvas id="documentTypeChart"></canvas>
								</div>
							</div>
						</div>

						<!-- Document Type Statistics Table -->
						<div class="data-table">
							<h3>📋 Document Type Performance</h3>
							<div class="table-responsive">
							<table>
								<thead>
									<tr>
										<th>Type</th>
										<th>Total</th>
										<th>Signed</th>
										<th>Pending</th>
										<th>Completion Rate</th>
										<th>Avg Time (hours)</th>
									</tr>
								</thead>
								<tbody>
									@forelse($documentTypeStats as $stat)
									<tr>
										<td>
											<span class="badge-type type-{{ $stat->document_type }}">
												{{ ucfirst($stat->document_type) }}
											</span>
										</td>
										<td><strong>{{ $stat->total }}</strong></td>
										<td>{{ $stat->signed }}</td>
										<td>{{ $stat->pending }}</td>
										<td>
											{{ $stat->completion_rate }}%
											<div class="esig-progress-track">
												<div class="esig-progress-fill" style="width: {{ $stat->completion_rate }}%"></div>
											</div>
										</td>
										<td>{{ $stat->avg_time_hours ?? 'N/A' }}</td>
									</tr>
									@empty
									<tr>
										<td colspan="6" style="text-align: center; color: #6c757d;">No data available</td>
									</tr>
									@endforelse
								</tbody>
							</table>
							</div>
						</div>

						<!-- Top Signers Table -->
						<div class="data-table">
							<h3>🏆 Top Signers (Most Active)</h3>
							<div class="table-responsive">
							<table>
								<thead>
									<tr>
										<th>Rank</th>
										<th>Signer</th>
										<th>Email</th>
										<th>Total Documents</th>
										<th>Completed</th>
										<th>Avg Time (hours)</th>
									</tr>
								</thead>
								<tbody>
									@forelse($topSigners as $index => $signer)
									<tr>
										<td><strong>#{{ $index + 1 }}</strong></td>
										<td>{{ $signer->name }}</td>
										<td>{{ $signer->email }}</td>
										<td>{{ $signer->total_signed }}</td>
										<td>{{ $signer->completed_count }}</td>
										<td>{{ $signer->avg_time_hours ?? 'N/A' }}</td>
									</tr>
									@empty
									<tr>
										<td colspan="6" style="text-align: center; color: #6c757d;">No signer data available</td>
									</tr>
									@endforelse
								</tbody>
							</table>
							</div>
						</div>

						<!-- Overdue Documents -->
						@if($overdueDocuments->count() > 0)
						<div class="data-table">
							<h3>⚠️ Overdue Documents</h3>
							<div class="table-responsive">
							<table>
								<thead>
									<tr>
										<th>Document</th>
										<th>Owner</th>
										<th>Signer</th>
										<th>Days Overdue</th>
										<th>Reminders Sent</th>
										<th>Action</th>
									</tr>
								</thead>
								<tbody>
									@foreach($overdueDocuments as $doc)
									<tr>
										<td>
											<a href="{{ route('signatures.show', $doc['id']) }}">
												{{ $doc['title'] }}
											</a>
										</td>
										<td>{{ $doc['owner'] }}</td>
										<td>{{ $doc['signer_email'] }}</td>
										<td>
											<span class="overdue-badge">{{ $doc['days_overdue'] }} days</span>
										</td>
										<td>{{ $doc['reminder_count'] }}/3</td>
										<td>
											<a href="{{ route('signatures.show', $doc['id']) }}" class="btn btn-sm btn-primary">
												View
											</a>
										</td>
									</tr>
									@endforeach
								</tbody>
							</table>
							</div>
						</div>
						@endif

						<!-- User Performance (Admin Only) -->
						@if($user->role === 1 && $userPerformance)
						<div class="data-table">
							<h3>👥 User Performance Comparison</h3>
							<div class="table-responsive">
							<table>
								<thead>
									<tr>
										<th>User</th>
										<th>Total Sent</th>
										<th>Signed</th>
										<th>Pending</th>
										<th>Completion Rate</th>
										<th>Median Time (hours)</th>
									</tr>
								</thead>
								<tbody>
									@foreach($userPerformance as $perf)
									<tr>
										<td>
											<strong>{{ $perf['name'] }}</strong><br>
											<small style="color: #6c757d;">{{ $perf['email'] }}</small>
										</td>
										<td>{{ $perf['total_sent'] }}</td>
										<td>{{ $perf['signed'] }}</td>
										<td>{{ $perf['pending'] }}</td>
										<td>
											{{ $perf['completion_rate'] }}%
											<div class="esig-progress-track">
												<div class="esig-progress-fill" style="width: {{ $perf['completion_rate'] }}%"></div>
											</div>
										</td>
										<td>{{ $perf['median_time_hours'] }}</td>
									</tr>
									@endforeach
								</tbody>
							</table>
							</div>
						</div>
						@endif
					</div>
					</div>
				</div>
			</div>
		</div>
		</div>
		</section>
	</div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Signature Trend Chart
const trendData = @json($trendData);
const trendCtx = document.getElementById('signatureTrendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendData.labels,
        datasets: [
            {
                label: 'Sent',
                data: trendData.sent,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Signed',
                data: trendData.signed,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Document Type Chart
const docTypeData = @json($documentTypeStats);
const typeCtx = document.getElementById('documentTypeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: docTypeData.map(d => d.document_type.charAt(0).toUpperCase() + d.document_type.slice(1)),
        datasets: [{
            data: docTypeData.map(d => d.total),
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
                '#4facfe',
                '#43e97b'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>
@endsection
