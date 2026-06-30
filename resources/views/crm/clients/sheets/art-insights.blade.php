@extends('layouts.crm_client_detail')
@section('title', 'ART Submission and Hearing Files - Insights')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/listing-container.css') }}">
<style>
    .sheet-tabs {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 0;
        margin: 0 -20px 20px -20px;
        display: flex;
        gap: 0;
        border-radius: 8px 8px 0 0;
    }
    .sheet-tab { flex: 1; padding: 15px 20px; text-align: center; color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 600; font-size: 15px; transition: all 0.3s ease; border-bottom: 3px solid transparent; }
    .sheet-tab:hover { color: #fff; background: rgba(255,255,255,0.1); text-decoration: none; }
    .sheet-tab.active { color: #fff; background: rgba(255,255,255,0.15); border-bottom-color: #fff; }
    .sheet-tab i { margin-right: 8px; }
    .insights-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .insight-card {
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
    }
    .insight-card:hover { box-shadow: 0 4px 16px rgba(102, 126, 234, 0.15); transform: translateY(-2px); }
    .insight-card-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 12px; }
    .insight-card-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .insight-card-icon.success { background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white; }
    .insight-card-icon.info { background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%); color: white; }
    .insight-card-icon.warning { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); color: white; }
    .insight-card-title { font-size: 13px; color: #6c757d; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .insight-card-value { font-size: 32px; font-weight: 700; color: #495057; line-height: 1; }
    .breakdown-section { background: white; border: 1px solid #e9ecef; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .breakdown-section h5 { font-weight: 700; color: #495057; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #667eea; }
    .breakdown-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-bottom: 8px; background: #f8f9fa; border-radius: 8px; }
    .breakdown-label { font-weight: 600; color: #495057; display: flex; align-items: center; gap: 8px; }
    .breakdown-value { font-size: 18px; font-weight: 700; color: #667eea; }
    .breakdown-bar { height: 8px; background: #e9ecef; border-radius: 4px; margin-top: 8px; overflow: hidden; }
    .breakdown-bar-fill { height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.5s ease; }
    .chart-section { background: white; border: 1px solid #e9ecef; border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .chart-section h5 { font-weight: 700; color: #495057; margin-bottom: 20px; }
    .monthly-chart { display: flex; align-items: flex-end; gap: 12px; height: 200px; padding: 20px 0; }
    .monthly-bar { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .monthly-bar-column { width: 100%; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); border-radius: 4px 4px 0 0; position: relative; transition: all 0.3s; min-height: 10px; }
    .monthly-bar-value { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 12px; font-weight: 700; color: #667eea; }
    .monthly-bar-label { font-size: 11px; color: #6c757d; font-weight: 600; text-align: center; white-space: nowrap; }
</style>
@endsection

@section('content')
<div class="listing-container">
    <section class="listing-section" style="padding-top: 40px;">
        <div class="listing-section-body">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h4>@icon('fa-chart-bar') ART Submission and Hearing Files - Insights</h4>
                    <div class="card-header-actions">
                        <a href="{{ route('clients.index') }}" class="btn btn-theme btn-theme-sm" title="Back to Clients">
                            @icon('fa-arrow-left') Back to Clients
                        </a>
                    </div>
                </div>

                <div class="sheet-tabs">
                    <a href="{{ route('clients.sheets.art') }}" class="sheet-tab">
                        @icon('fa-list') List
                    </a>
                    <a href="{{ route('clients.sheets.art.insights') }}" class="sheet-tab active">
                        @icon('fa-chart-bar') Insights
                    </a>
                </div>

                <div class="card-body">
                    @if($activeFilterCount > 0)
                        <div class="alert alert-info">
                            @icon('fa-info-circle') Showing insights for filtered data ({{ $activeFilterCount }} filter(s) active).
                            <a href="{{ route('clients.sheets.art.insights') }}" class="alert-link">View all data</a>
                        </div>
                    @endif

                    <div class="insights-grid">
                        <div class="insight-card">
                            <div class="insight-card-icon primary">
                                @icon('fa-gavel')
                            </div>
                            <div class="insight-card-title">Total ART Records</div>
                            <div class="insight-card-value">{{ $insights['total_records'] }}</div>
                        </div>
                        <div class="insight-card">
                            <div class="insight-card-icon info">
                                @icon('fa-calendar-week')
                            </div>
                            <div class="insight-card-title">Last 7 Days</div>
                            <div class="insight-card-value">{{ $insights['recent_submissions_7d'] }}</div>
                        </div>
                        <div class="insight-card">
                            <div class="insight-card-icon warning">
                                @icon('fa-calendar-alt')
                            </div>
                            <div class="insight-card-title">Last 30 Days</div>
                            <div class="insight-card-value">{{ $insights['recent_submissions_30d'] }}</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="breakdown-section">
                                <h5>@icon('fa-tasks', ['class' => 'mr-2'])By Status</h5>
                                @if(!empty($insights['by_status']))
                                    @php $maxStatusCount = max(array_values($insights['by_status'])); @endphp
                                    @foreach($insights['by_status'] as $status => $count)
                                        @php
                                            $statusLabels = [
                                                'submission_pending' => 'Submission Pending',
                                                'submission_done' => 'Submission Done',
                                                'hearing_invitation_sent' => 'Hearing Invitation Sent',
                                                'waiting_for_hearing' => 'Waiting for Hearing',
                                                'hearing' => 'Hearing',
                                                'decided' => 'Decided',
                                                'withdrawn' => 'Withdrawn',
                                            ];
                                            $label = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                                        @endphp
                                        <div class="breakdown-item">
                                            <div class="breakdown-label">
                                                @icon('fa-circle', ['style' => 'font-size: 8px; color: #667eea;'])
                                                {{ $label }}
                                            </div>
                                            <div class="breakdown-value">{{ $count }}</div>
                                        </div>
                                        <div class="breakdown-bar">
                                            <div class="breakdown-bar-fill" style="width: {{ $maxStatusCount > 0 ? ($count / $maxStatusCount * 100) : 0 }}%;"></div>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted">No data available</p>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="breakdown-section">
                                <h5>@icon('fa-user-tie', ['class' => 'mr-2'])By Agent</h5>
                                @if(!empty($insights['by_agent']))
                                    @php $maxAgentCount = max(array_values($insights['by_agent'])); @endphp
                                    @foreach($insights['by_agent'] as $agent => $count)
                                        <div class="breakdown-item">
                                            <div class="breakdown-label">
                                                @icon('fa-circle', ['style' => 'font-size: 8px; color: #667eea;'])
                                                {{ trim($agent) ?: 'Not assigned' }}
                                            </div>
                                            <div class="breakdown-value">{{ $count }}</div>
                                        </div>
                                        <div class="breakdown-bar">
                                            <div class="breakdown-bar-fill" style="width: {{ $maxAgentCount > 0 ? ($count / $maxAgentCount * 100) : 0 }}%;"></div>
                                        </div>
                                    @endforeach
                                @else
                                    <p class="text-muted">No data available</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="chart-section">
                        <h5>@icon('fa-chart-line', ['class' => 'mr-2'])Submissions Over Last 6 Months</h5>
                        @if(!empty($insights['submissions_by_month']))
                            @php $maxMonthlyCount = max(array_values($insights['submissions_by_month'])); @endphp
                            <div class="monthly-chart">
                                @foreach($insights['submissions_by_month'] as $month => $count)
                                    <div class="monthly-bar">
                                        <div class="monthly-bar-column" style="height: {{ $maxMonthlyCount > 0 ? ($count / $maxMonthlyCount * 100) : 10 }}%;">
                                            @if($count > 0)
                                                <div class="monthly-bar-value">{{ $count }}</div>
                                            @endif
                                        </div>
                                        <div class="monthly-bar-label">{{ $month }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted">No data available</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
