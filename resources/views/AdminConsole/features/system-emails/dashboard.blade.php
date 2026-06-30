@extends('layouts.crm_client_detail')
@section('title', 'System Emails — Dashboard')

@section('styles')
<style>
    .stat-card { transition: box-shadow 0.2s; }
    .stat-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,0.10); }
    .top-categories-list { list-style: none; padding: 0; margin: 0; }
    .top-categories-list li { display: flex; align-items: center; justify-content: space-between;
                           padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; }
    .top-categories-list li:last-child { border-bottom: none; }
    .category-bar { height: 6px; border-radius: 3px; background: #8e44ad; display: inline-block; min-width: 4px; }
    .recent-table th { white-space: nowrap; font-size: 0.82rem; }
    .truncate-cell { max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .coverage-notice { border-left: 4px solid #3498db; }
    .email-delivery-badge { font-size: 0.72rem; font-weight: 600; }
    .email-engagement-icons { font-size: 0.78rem; white-space: nowrap; }
    .sent-emails-quick-searches .btn { font-weight: 600; border-width: 2px; }
    .sent-emails-quick-searches .btn-outline-primary { color: #1e40af; border-color: #2563eb; background-color: #eff6ff; }
    .sent-emails-quick-searches .btn-outline-primary:hover,
    .sent-emails-quick-searches .btn-outline-primary:focus { color: #fff; background-color: #2563eb; border-color: #1d4ed8; }
    .sent-emails-quick-searches .btn-outline-secondary { color: #374151; border-color: #6b7280; background-color: #f3f4f6; }
    .sent-emails-quick-searches .btn-outline-secondary:hover,
    .sent-emails-quick-searches .btn-outline-secondary:focus { color: #fff; background-color: #6b7280; border-color: #4b5563; }
    .sent-emails-quick-searches .btn-outline-danger { color: #991b1b; border-color: #dc2626; background-color: #fef2f2; }
    .sent-emails-quick-searches .btn-outline-danger:hover,
    .sent-emails-quick-searches .btn-outline-danger:focus { color: #fff; background-color: #dc2626; border-color: #991b1b; }
    .sent-emails-quick-searches .btn-outline-success { color: #166534; border-color: #15803d; background-color: #f0fdf4; }
    .sent-emails-quick-searches .btn-outline-success:hover,
    .sent-emails-quick-searches .btn-outline-success:focus { color: #fff; background-color: #15803d; border-color: #166534; }
    .sent-emails-quick-searches .btn-outline-dark { color: #1f2937; border-color: #374151; background-color: #f9fafb; }
    .sent-emails-quick-searches .btn-outline-dark:hover,
    .sent-emails-quick-searches .btn-outline-dark:focus { color: #fff; background-color: #374151; border-color: #1f2937; }
</style>
@endsection

@section('content')
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">@include('../Elements/flash-message')</div>

            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>

                <div class="col-9 col-md-9 col-lg-9">

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="mb-0">@icon('fa-robot') System Emails — Dashboard</h4>
                        <div>
                            <a href="{{ route('adminconsole.features.sent-emails.dashboard') }}" class="btn btn-outline-secondary btn-sm mr-1">
                                @icon('fa-paper-plane') CRM Sent Emails
                            </a>
                            <a href="{{ route('adminconsole.features.system-emails.index') }}" class="btn btn-outline-primary btn-sm">
                                @icon('fa-list') Search All
                            </a>
                        </div>
                    </div>

                    <div class="alert alert-info coverage-notice mb-4" role="alert" style="font-size:0.875rem;">
                        @icon('fa-info-circle')
                        Analytics for automated system emails — invoices, receipts, appointment confirmations &amp; reminders, visa expiry notices, e-signatures, and similar. Staff-composed CRM emails are on the
                        <a href="{{ route('adminconsole.features.sent-emails.dashboard') }}">Sent Emails</a> dashboard.
                    </div>

                    <div class="row">
                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                            <div class="card card-statistic-1 stat-card">
                                <div class="card-icon bg-primary">@icon('fa-paper-plane')</div>
                                <div class="card-wrap">
                                    <div class="card-header"><h4>Sent Today</h4></div>
                                    <div class="card-body">{{ number_format($stats['totalToday']) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                            <div class="card card-statistic-1 stat-card">
                                <div class="card-icon bg-success">@icon('fa-calendar-week')</div>
                                <div class="card-wrap">
                                    <div class="card-header"><h4>This Week</h4></div>
                                    <div class="card-body">{{ number_format($stats['totalWeek']) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                            <div class="card card-statistic-1 stat-card">
                                <div class="card-icon bg-info">@icon('fa-calendar-alt')</div>
                                <div class="card-wrap">
                                    <div class="card-header"><h4>This Month</h4></div>
                                    <div class="card-body">{{ number_format($stats['totalMonth']) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 col-12 mb-3">
                            <div class="card card-statistic-1 stat-card">
                                <div class="card-icon bg-danger">@icon('fa-exclamation-triangle')</div>
                                <div class="card-wrap">
                                    <div class="card-header"><h4>Failed This Month</h4></div>
                                    <div class="card-body">{{ number_format($stats['failedCount']) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-5 col-md-12 mb-3">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h4>@icon('fa-tags') Top Categories This Month</h4>
                                </div>
                                <div class="card-body">
                                    @if($stats['topCategories']->isNotEmpty())
                                        @php $maxCount = $stats['topCategories']->max('send_count'); @endphp
                                        <ul class="top-categories-list">
                                            @foreach($stats['topCategories'] as $item)
                                            <li>
                                                <div class="d-flex flex-column" style="min-width:0;flex:1;">
                                                    <span style="font-weight:500;">{{ $item['category_label'] }}</span>
                                                    <span class="category-bar mt-1"
                                                          style="width:{{ $maxCount > 0 ? round(($item['send_count'] / $maxCount) * 100) : 0 }}%;"></span>
                                                </div>
                                                <span class="badge badge-primary ml-3">{{ number_format($item['send_count']) }}</span>
                                            </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-muted text-center mt-3">No system emails logged this month yet.</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7 col-md-12 mb-3">
                            <div class="card h-100 sent-emails-quick-searches">
                                <div class="card-header">
                                    <h4>@icon('fa-bolt') Quick Searches</h4>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6 mb-2">
                                            <a href="{{ route('adminconsole.features.system-emails.index', ['filter'=>1,'date_from'=>now()->toDateString(),'date_to'=>now()->toDateString()]) }}"
                                               class="btn btn-outline-primary btn-block">
                                                @icon('fa-sun') Today's Emails
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="{{ route('adminconsole.features.system-emails.index', ['filter'=>1,'category'=>'invoice']) }}"
                                               class="btn btn-outline-secondary btn-block">
                                                @icon('fa-file-invoice') Invoices
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="{{ route('adminconsole.features.system-emails.index', ['filter'=>1,'category'=>'appointment']) }}"
                                               class="btn btn-outline-success btn-block">
                                                @icon('fa-calendar-check') Appointments
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="{{ route('adminconsole.features.system-emails.index', ['filter'=>1,'category'=>'signature']) }}"
                                               class="btn btn-outline-primary btn-block">
                                                @icon('fa-signature') E-Signatures
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="{{ route('adminconsole.features.system-emails.index', ['filter'=>1,'failed'=>1]) }}"
                                               class="btn btn-outline-danger btn-block">
                                                @icon('fa-times-circle') Failed / Undelivered
                                            </a>
                                        </div>
                                        <div class="col-6 mb-2">
                                            <a href="{{ route('adminconsole.features.system-emails.index') }}"
                                               class="btn btn-outline-dark btn-block">
                                                @icon('fa-search') Full Search
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4>@icon('fa-history') Recent System Emails</h4>
                            <div class="card-header-action">
                                <a href="{{ route('adminconsole.features.system-emails.index', ['filter'=>1]) }}" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @if($recent->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 recent-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Client</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recent as $row)
                                        <tr>
                                            <td style="white-space:nowrap;">
                                                <small>{{ \Carbon\Carbon::parse($row['created_at'])->format('d M Y') }}</small><br>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($row['created_at'])->format('H:i') }}</small>
                                            </td>
                                            <td><span class="badge badge-light">{{ $row['category_label'] }}</span></td>
                                            <td class="truncate-cell" title="{{ $row['from_mail'] }}">{{ $row['from_mail'] }}</td>
                                            <td class="truncate-cell" title="{{ $row['to_mail'] }}">{{ $row['to_mail'] }}</td>
                                            <td class="truncate-cell" title="{{ $row['subject'] }}">{{ $row['subject'] }}</td>
                                            <td>
                                                @include('partials.email-delivery-status-badge', [
                                                    'status' => $row['delivery_status'] ?? 'pending',
                                                    'reason' => $row['status_reason'] ?? null,
                                                ])
                                                @include('partials.email-engagement-icons', [
                                                    'opened_at' => $row['opened_at'] ?? null,
                                                    'clicked_at' => $row['clicked_at'] ?? null,
                                                    'spam_reported_at' => $row['spam_reported_at'] ?? null,
                                                ])
                                            </td>
                                            <td>
                                                @if($row['client_id'])
                                                    <a href="{{ route('clients.detail', $row['client_id']) }}" target="_blank">
                                                        {{ \Illuminate\Support\Str::limit($row['client_name'], 20) }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('adminconsole.features.system-emails.show', $row['id']) }}" class="btn btn-sm btn-info">
                                                    @icon('fa-eye')
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @else
                            <div class="p-4 text-center text-muted">
                                @icon('fa-inbox', ['class' => 'fa-2x mb-2 d-block'])
                                No system emails logged yet. New automated sends will appear here.
                            </div>
                            @endif
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
document.addEventListener('DOMContentLoaded', function () {
    $('[data-toggle="tooltip"]').tooltip();
});
</script>
@endsection
