@extends('layouts.crm_client_detail_dashboard')

@section('content')
<style>
    .analytics-dashboard {
        padding: 20px;
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .analytics-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .analytics-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #343a40;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .date-filter {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .date-filter input {
        padding: 8px 12px;
        border: 1px solid #e3e3e3;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #6777ef;
        transition: transform 0.2s;
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .stat-card.green { border-left-color: #47c363; }
    .stat-card.orange { border-left-color: #ffa426; }
    .stat-card.red { border-left-color: #fc544b; }
    .stat-card.purple { border-left-color: #9c27b0; }
    
    .stat-label {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 8px;
        font-weight: 500;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #343a40;
        line-height: 1;
    }
    
    .stat-change {
        font-size: 12px;
        margin-top: 8px;
    }
    
    .stat-change.positive { color: #47c363; }
    .stat-change.negative { color: #fc544b; }
    
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .chart-card {
        background: white;
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .chart-card.full-width {
        grid-column: 1 / -1;
    }
    
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .chart-title {
        font-size: 18px;
        font-weight: 600;
        color: #343a40;
    }
    
    .chart-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-chart-action {
        padding: 6px 12px;
        border: 1px solid #e3e3e3;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.2s;
    }
    
    .btn-chart-action:hover {
        background: #f8f9fa;
        border-color: #6777ef;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .data-table th {
        background: #f8f9fa;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
    }
    
    .progress-bar-inline {
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        width: 100%;
    }
    
    .progress-fill {
        height: 100%;
        background: #6777ef;
        transition: width 0.3s;
    }
    
    .badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .badge-success { background: #d4edda; color: #155724; }
    .badge-warning { background: #fff3cd; color: #856404; }
    .badge-danger { background: #f8d7da; color: #721c24; }
    
    @media (max-width: 768px) {
        .chart-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .analytics-header {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }
    }
</style>

<div class="analytics-dashboard">
    <div class="analytics-header">
        <h1>@icon('fa-chart-line') Lead Analytics Dashboard</h1>
        <div class="date-filter">
            <input type="date" id="start_date" value="{{ $startDate->format('Y-m-d') }}">
            <span>to</span>
            <input type="date" id="end_date" value="{{ $endDate->format('Y-m-d') }}">
            <button class="btn btn-primary" onclick="applyDateFilter()">
                @icon('fa-filter') Apply
            </button>
            <button class="btn btn-secondary" onclick="exportReport()">
                @icon('fa-download') Export
            </button>
        </div>
    </div>
    
    <!-- Key Metrics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Leads</div>
            <div class="stat-value">{{ number_format($dashboardStats['total_leads']) }}</div>
        </div>
        <div class="stat-card green">
            <div class="stat-label">Converted</div>
            <div class="stat-value">{{ number_format($dashboardStats['converted']) }}</div>
            <div class="stat-change positive">
                {{ $dashboardStats['total_leads'] > 0 ? round(($dashboardStats['converted'] / $dashboardStats['total_leads']) * 100, 1) : 0 }}% conversion rate
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-label">Active Leads</div>
            <div class="stat-value">{{ number_format($dashboardStats['active']) }}</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-label">New This Month</div>
            <div class="stat-value">{{ number_format($dashboardStats['new_this_month']) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Pending Follow-ups</div>
            <div class="stat-value">{{ number_format($dashboardStats['pending_followups']) }}</div>
        </div>
        <div class="stat-card red">
            <div class="stat-label">Overdue Follow-ups</div>
            <div class="stat-value">{{ number_format($dashboardStats['overdue_followups']) }}</div>
        </div>
    </div>
    
    <!-- Charts Row 1 -->
    <div class="chart-grid">
        <!-- Conversion Funnel -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Conversion Funnel</h3>
            </div>
            <canvas id="funnelChart" height="300"></canvas>
        </div>
        
        <!-- Lead Stage Distribution -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">Lead Stage Distribution</h3>
            </div>
            <canvas id="qualityChart" height="300"></canvas>
        </div>
    </div>
    
    <!-- Source Performance Table -->
    <div class="chart-card full-width">
        <div class="chart-header">
            <h3 class="chart-title">Performance by Source</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Total Leads</th>
                    <th>Converted</th>
                    <th>Conversion Rate</th>
                    <th>Performance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sourcePerformance as $source)
                <tr>
                    <td><strong>{{ $source['source'] }}</strong></td>
                    <td>{{ number_format($source['total_leads']) }}</td>
                    <td>{{ number_format($source['converted']) }}</td>
                    <td>
                        <span class="badge {{ $source['conversion_rate'] >= 20 ? 'badge-success' : ($source['conversion_rate'] >= 10 ? 'badge-warning' : 'badge-danger') }}">
                            {{ $source['conversion_rate'] }}%
                        </span>
                    </td>
                    <td>
                        <div class="progress-bar-inline">
                            <div class="progress-fill" style="width: {{ min($source['conversion_rate'], 100) }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <!-- Agent Performance Table -->
    <div class="chart-card full-width">
        <div class="chart-header">
            <h3 class="chart-title">Agent Performance</h3>
            <div class="chart-actions">
                <select class="btn-chart-action" onchange="filterAgents(this.value)">
                    <option value="all">All Agents</option>
                    <option value="top">Top Performers</option>
                    <option value="needs-improvement">Needs Improvement</option>
                </select>
            </div>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Assigned Leads</th>
                    <th>Converted</th>
                    <th>Conversion Rate</th>
                    <th>Follow-ups Completed</th>
                    <th>Overdue</th>
                    <th>Avg Response Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach($agentPerformance as $agent)
                <tr>
                    <td><strong>{{ $agent['agent_name'] }}</strong></td>
                    <td>{{ number_format($agent['assigned_leads']) }}</td>
                    <td>{{ number_format($agent['converted_leads']) }}</td>
                    <td>
                        <span class="badge {{ $agent['conversion_rate'] >= 20 ? 'badge-success' : ($agent['conversion_rate'] >= 10 ? 'badge-warning' : 'badge-danger') }}">
                            {{ $agent['conversion_rate'] }}%
                        </span>
                    </td>
                    <td>{{ number_format($agent['completed_followups']) }}</td>
                    <td>
                        @if($agent['overdue_followups'] > 0)
                            <span class="badge badge-danger">{{ $agent['overdue_followups'] }}</span>
                        @else
                            <span class="badge badge-success">0</span>
                        @endif
                    </td>
                    <td>{{ $agent['avg_response_time_hours'] }}h</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Conversion Funnel Chart
const funnelCtx = document.getElementById('funnelChart').getContext('2d');
const funnelChart = new Chart(funnelCtx, {
    type: 'bar',
    data: {
        labels: ['New', 'Follow Up', 'Not Qualified', 'Hostile', 'Converted'],
            datasets: [{
                label: 'Count',
                data: [
                    {{ $conversionFunnel['new']['count'] }},
                    {{ $conversionFunnel['follow_up']['count'] }},
                    {{ $conversionFunnel['not_qualified']['count'] }},
                    {{ $conversionFunnel['hostile']['count'] }},
                    {{ $conversionFunnel['converted']['count'] }}
                ],
                backgroundColor: [
                    'rgba(103, 119, 239, 0.8)',
                    'rgba(255, 164, 38, 0.8)',
                    'rgba(108, 117, 125, 0.8)',
                    'rgba(252, 84, 75, 0.8)',
                    'rgba(71, 195, 99, 0.8)'
                ],
                borderColor: [
                    'rgba(103, 119, 239, 1)',
                    'rgba(255, 164, 38, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(252, 84, 75, 1)',
                    'rgba(71, 195, 99, 1)'
                ],
                borderWidth: 2
            }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.parsed.y || 0;
                        let percentage = context.dataIndex === 0 ? 100 : 
                            Math.round((context.parsed.y / {{ $conversionFunnel['total_leads'] }}) * 100);
                        return label + ' leads (' + percentage + '%)';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Lead Stage Distribution Chart
const qualityCtx = document.getElementById('qualityChart').getContext('2d');
const qualityChart = new Chart(qualityCtx, {
    type: 'doughnut',
    data: {
        labels: ['New', 'Follow Up', 'Not Qualified', 'Hostile', 'Converted'],
        datasets: [{
            data: [
                {{ $conversionFunnel['new']['count'] }},
                {{ $conversionFunnel['follow_up']['count'] }},
                {{ $conversionFunnel['not_qualified']['count'] }},
                {{ $conversionFunnel['hostile']['count'] }},
                {{ $conversionFunnel['converted']['count'] }}
            ],
            backgroundColor: [
                'rgba(103, 119, 239, 0.8)',
                'rgba(255, 164, 38, 0.8)',
                'rgba(108, 117, 125, 0.8)',
                'rgba(252, 84, 75, 0.8)',
                'rgba(71, 195, 99, 0.8)'
            ],
            borderColor: [
                'rgba(103, 119, 239, 1)',
                'rgba(255, 164, 38, 1)',
                'rgba(108, 117, 125, 1)',
                'rgba(252, 84, 75, 1)',
                'rgba(71, 195, 99, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.dataset.data[context.dataIndex] ?? 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                        return label + ': ' + value + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Date filter function
function applyDateFilter() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    window.location.href = `{{ route('leads.analytics.index') }}?start_date=${startDate}&end_date=${endDate}`;
}

// Export function
function exportReport() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    window.location.href = `{{ route('leads.analytics.export') }}?start_date=${startDate}&end_date=${endDate}`;
}

// Filter agents
function filterAgents(filter) {
    // Implement client-side filtering or reload with filter param
    // TODO: Add filter implementation
    window.location.href = '{{ route("leads.analytics.index") }}?filter=' + filter;
}
</script>
@endsection

