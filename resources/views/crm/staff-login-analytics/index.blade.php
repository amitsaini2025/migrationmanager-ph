@extends('layouts.crm_client_detail')
@section('title', 'Staff Login Analytics')

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-header">
            <div>
                <h1 class="mb-0">Staff Login Analytics</h1>
                <p class="mb-0 text-secondary" style="font-size: 0.95rem;">Track and analyze staff login patterns over time</p>
            </div>
        </div>

        <div class="section-body">
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="staff-filter" class="font-weight-semibold text-dark">Staff</label>
                            <select id="staff-filter" class="form-control">
                                <option value="">All Staff</option>
                                @foreach(\App\Models\Staff::where('status', 1)->orderBy('first_name')->get() as $staff)
                                    <option value="{{ $staff->id }}">{{ trim($staff->first_name . ' ' . $staff->last_name) ?: $staff->email }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="period-filter" class="font-weight-semibold text-dark">Period</label>
                            <select id="period-filter" class="form-control">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date-range-preset" class="font-weight-semibold text-dark">Quick Range</label>
                            <select id="date-range-preset" class="form-control">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="180">Last 6 Months</option>
                                <option value="365">Last Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="start-date" class="font-weight-semibold text-dark">Start Date</label>
                            <input type="date" id="start-date" class="form-control" value="{{ \Carbon\Carbon::now()->subDays(30)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-2">
                            <label for="end-date" class="font-weight-semibold text-dark">End Date</label>
                            <input type="date" id="end-date" class="form-control" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-1">
                            <label>&nbsp;</label>
                            <button type="button" id="apply-filters" class="btn btn-primary btn-block">
                                @icon('fa-filter') Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4" id="summary-cards">
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-primary">
                            @icon('fa-sign-in-alt')
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Total Logins</h4>
                            </div>
                            <div class="card-body" id="total-logins">
                                <span class="spinner-border spinner-border-sm"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-success">
                            @icon('fa-users')
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Unique Staff</h4>
                            </div>
                            <div class="card-body" id="unique-staff">
                                <span class="spinner-border spinner-border-sm"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-warning">
                            @icon('fa-exclamation-triangle')
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Failed Logins</h4>
                            </div>
                            <div class="card-body" id="failed-logins">
                                <span class="spinner-border spinner-border-sm"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-statistic-1">
                        <div class="card-icon bg-info">
                            @icon('fa-chart-line')
                        </div>
                        <div class="card-wrap">
                            <div class="card-header">
                                <h4>Avg per Day</h4>
                            </div>
                            <div class="card-body" id="avg-per-day">
                                <span class="spinner-border spinner-border-sm"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4>Login Trends</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="trendsChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h4>Hourly Distribution</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="hourlyChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mt-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Top Staff by Login Count</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="topStaffChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Success vs Failed Logins</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="successFailedChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Staff Table -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Top Staff</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="top-staff-table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Staff</th>
                                            <th>Email</th>
                                            <th>Login Count</th>
                                            <th>Last Login</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-staff-body">
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <span class="spinner-border spinner-border-sm"></span> Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let trendsChart, hourlyChart, topStaffChart, successFailedChart;

    // Filter elements
    const staffFilter = document.getElementById('staff-filter');
    const periodFilter = document.getElementById('period-filter');
    const dateRangePreset = document.getElementById('date-range-preset');
    const startDate = document.getElementById('start-date');
    const endDate = document.getElementById('end-date');
    const applyFiltersBtn = document.getElementById('apply-filters');

    // Date range preset handler
    dateRangePreset.addEventListener('change', function() {
        if (this.value !== 'custom') {
            const days = parseInt(this.value);
            endDate.value = new Date().toISOString().split('T')[0];
            startDate.value = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        }
    });

    // Apply filters
    applyFiltersBtn.addEventListener('click', loadAllData);

    function getQueryParams() {
        const params = new URLSearchParams();
        if (staffFilter.value) params.append('staff_id', staffFilter.value);
        if (startDate.value) params.append('start_date', startDate.value);
        if (endDate.value) params.append('end_date', endDate.value);
        return params.toString();
    }

    async function loadSummary() {
        try {
            const response = await fetch(`/api/staff-login-analytics/summary?${getQueryParams()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                document.getElementById('total-logins').textContent = result.data.total_logins.toLocaleString();
                document.getElementById('unique-staff').textContent = (result.data.unique_staff ?? result.data.unique_users ?? 0).toLocaleString();
                document.getElementById('failed-logins').textContent = result.data.failed_logins.toLocaleString();
                document.getElementById('avg-per-day').textContent = result.data.average_per_day.toLocaleString();
            }
        } catch (error) {
            console.error('Failed to load summary:', error);
        }
    }

    async function loadTrends() {
        try {
            const period = periodFilter.value;
            const endpoint = period === 'daily' ? 'daily' : (period === 'weekly' ? 'weekly' : 'monthly');
            const response = await fetch(`/api/staff-login-analytics/${endpoint}?${getQueryParams()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                const labels = result.data.map(item => 
                    period === 'daily' ? item.date : 
                    (period === 'weekly' ? item.label : item.label)
                );
                const counts = result.data.map(item => item.count);
                const uniqueStaff = result.data.map(item => item.unique_staff ?? item.unique_users ?? 0);

                if (trendsChart) trendsChart.destroy();
                
                const ctx = document.getElementById('trendsChart').getContext('2d');
                trendsChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Total Logins',
                            data: counts,
                            borderColor: 'rgb(102, 126, 234)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Unique Staff',
                            data: uniqueStaff,
                            borderColor: 'rgb(17, 153, 142)',
                            backgroundColor: 'rgba(17, 153, 142, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load trends:', error);
        }
    }

    async function loadHourly() {
        try {
            const response = await fetch(`/api/staff-login-analytics/hourly?${getQueryParams()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                // Fill missing hours with 0
                const hourData = {};
                for (let i = 0; i < 24; i++) {
                    hourData[i] = 0;
                }
                result.data.forEach(item => {
                    hourData[item.hour] = item.count;
                });

                const labels = Object.keys(hourData).map(h => `${h.toString().padStart(2, '0')}:00`);
                const counts = Object.values(hourData);

                if (hourlyChart) hourlyChart.destroy();
                
                const ctx = document.getElementById('hourlyChart').getContext('2d');
                hourlyChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Logins',
                            data: counts,
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: 'rgb(102, 126, 234)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load hourly data:', error);
        }
    }

    async function loadTopStaff() {
        try {
            const response = await fetch(`/api/staff-login-analytics/top-staff?${getQueryParams()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                // Update table
                const tbody = document.getElementById('top-staff-body');
                if (result.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No data available</td></tr>';
                } else {
                    tbody.innerHTML = result.data.map((staff, index) => `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${staff.staff_name}</td>
                            <td>${staff.staff_email ?? staff.user_email ?? '—'}</td>
                            <td><span class="badge badge-primary">${staff.login_count}</span></td>
                            <td>${new Date(staff.last_login).toLocaleDateString()}</td>
                        </tr>
                    `).join('');
                }

                // Update chart
                const labels = result.data.slice(0, 10).map(s => s.staff_name);
                const counts = result.data.slice(0, 10).map(s => s.login_count);

                if (topStaffChart) topStaffChart.destroy();
                
                const ctx = document.getElementById('topStaffChart').getContext('2d');
                topStaffChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Login Count',
                            data: counts,
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: 'rgb(102, 126, 234)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load top staff:', error);
        }
    }

    async function loadSuccessFailed() {
        try {
            const response = await fetch(`/api/staff-login-analytics/summary?${getQueryParams()}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'include'
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                const successful = result.data.total_logins - result.data.failed_logins;
                const failed = result.data.failed_logins;

                if (successFailedChart) successFailedChart.destroy();
                
                const ctx = document.getElementById('successFailedChart').getContext('2d');
                successFailedChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Successful', 'Failed'],
                        datasets: [{
                            data: [successful, failed],
                            backgroundColor: [
                                'rgba(17, 153, 142, 0.8)',
                                'rgba(252, 84, 75, 0.8)'
                            ],
                            borderColor: [
                                'rgb(17, 153, 142)',
                                'rgb(252, 84, 75)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load success/failed data:', error);
        }
    }

    async function loadAllData() {
        await Promise.all([
            loadSummary(),
            loadTrends(),
            loadHourly(),
            loadTopStaff(),
            loadSuccessFailed()
        ]);
    }

    // Initial load
    loadAllData();
})();
</script>
@endpush
