@extends('layouts.crm_client_detail')
@section('title', 'Sent Emails')

@section('styles')
<style>
    .filter-card { margin-bottom: 1.5rem; }
    .filter-card .card-header { cursor: pointer; user-select: none; }
    .sent-emails-table th { white-space: nowrap; }
    .badge-client  { background-color: #3498db; color: #fff; }
    .badge-lead    { background-color: #f39c12; color: #fff; }
    .badge-agent   { background-color: #8e44ad; color: #fff; }
    .truncate-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .attach-badge  { font-size: 0.75rem; }
    .coverage-notice { border-left: 4px solid #f39c12; }
    .email-delivery-badge { font-size: 0.72rem; font-weight: 600; }
    .email-engagement-icons { font-size: 0.78rem; white-space: nowrap; }
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

                    {{-- Page header --}}
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h4 class="mb-0">@icon('fa-paper-plane') Sent Emails</h4>
                        <div>
                            <a href="{{ route('adminconsole.features.system-emails.dashboard') }}" class="btn btn-outline-secondary btn-sm mr-1">
                                @icon('fa-robot') System Emails
                            </a>
                            <a href="{{ route('adminconsole.features.sent-emails.dashboard') }}" class="btn btn-outline-primary btn-sm">
                                @icon('fa-chart-bar') Dashboard
                            </a>
                        </div>
                    </div>

                    {{-- Coverage notice --}}
                    <div class="alert alert-warning coverage-notice" role="alert" style="font-size:0.875rem;">
                        @icon('fa-info-circle')
                        Showing CRM-logged outgoing emails only (staff compose). System-generated emails (invoices, appointment reminders, visa expiry notices, etc.) are on the
                        <a href="{{ route('adminconsole.features.system-emails.index') }}">System Emails</a> page.
                    </div>

                    {{-- Filters --}}
                    <div class="card filter-card">
                        <div class="card-header" data-toggle="collapse" data-target="#filterBody" aria-expanded="true">
                            <h4 class="mb-0">@icon('fa-filter') Search &amp; Filters</h4>
                        </div>
                        <div class="collapse show" id="filterBody">
                            <div class="card-body">
                                <form action="{{ route('adminconsole.features.sent-emails.index') }}" method="GET" id="filterForm">
                                    <input type="hidden" name="filter" value="1">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">@icon('fa-search') Subject / From / To</label>
                                            <input type="text" name="search" class="form-control"
                                                   placeholder="Search subject, from address or recipient…"
                                                   value="{{ request('search') }}">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">@icon('fa-calendar') Date From</label>
                                            <input type="date" name="date_from" class="form-control"
                                                   value="{{ request('date_from') }}">
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">@icon('fa-calendar') Date To</label>
                                            <input type="date" name="date_to" class="form-control"
                                                   value="{{ request('date_to') }}">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">@icon('fa-user-tie') Sent By</label>
                                            <select name="sender_id" class="form-control mm-select">
                                                <option value="">All Staff</option>
                                                @foreach($staffList as $staff)
                                                    <option value="{{ $staff->id }}"
                                                        {{ request('sender_id') == $staff->id ? 'selected' : '' }}>
                                                        {{ $staff->first_name }} {{ $staff->last_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">@icon('fa-at') From Address</label>
                                            <input type="text" name="from_address" class="form-control"
                                                   placeholder="e.g. admin@domain.com.au"
                                                   value="{{ request('from_address') }}">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">@icon('fa-user') Client / Lead</label>
                                            <select name="client_id" id="se_client_id" class="form-control mm-select-ajax">
                                                <option value="">All Clients &amp; Leads</option>
                                                @if($selectedClient)
                                                    <option value="{{ $selectedClient->id }}" selected>
                                                        {{ $selectedClient->first_name }} {{ $selectedClient->last_name }}
                                                    </option>
                                                @elseif(request('client_id'))
                                                    <option value="{{ request('client_id') }}" selected>
                                                        {{ request('client_name', 'Selected') }}
                                                    </option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">@icon('fa-tag') Recipient Type</label>
                                            <select name="type" class="form-control mm-select">
                                                <option value="">All Types</option>
                                                <option value="client"  {{ request('type') === 'client'  ? 'selected' : '' }}>Client</option>
                                                <option value="lead"    {{ request('type') === 'lead'    ? 'selected' : '' }}>Lead</option>
                                                <option value="agent"   {{ request('type') === 'agent'   ? 'selected' : '' }}>Agent</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">@icon('fa-paperclip') Has Attachments</label>
                                            <select name="has_attachments" class="form-control mm-select">
                                                <option value="">Any</option>
                                                <option value="1" {{ request('has_attachments') === '1' ? 'selected' : '' }}>Yes</option>
                                                <option value="0" {{ request('has_attachments') === '0' ? 'selected' : '' }}>No</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                @icon('fa-search') Search
                                            </button>
                                            <a href="{{ route('adminconsole.features.sent-emails.index') }}" class="btn btn-secondary">
                                                @icon('fa-redo') Reset
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Results --}}
                    @if($paginator !== null)
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                @icon('fa-list') Results
                                <span class="badge badge-primary ml-2">{{ number_format($total) }}</span>
                            </h4>
                        </div>
                        <div class="card-body p-0">
                            @if($emails->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 sent-emails-table">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Date / Time</th>
                                            <th>Sent By</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Subject</th>
                                            <th>Status</th>
                                            <th>Client</th>
                                            <th>Type</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($emails as $row)
                                        <tr>
                                            <td style="white-space:nowrap;">
                                                <small>{{ \Carbon\Carbon::parse($row['created_at'])->format('d M Y') }}</small><br>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($row['created_at'])->format('H:i') }}</small>
                                            </td>
                                            <td>{{ $row['sent_by'] }}</td>
                                            <td class="truncate-cell" title="{{ $row['from_mail'] }}">
                                                {{ $row['from_mail'] }}
                                            </td>
                                            <td class="truncate-cell" title="{{ $row['to_mail'] }}">
                                                {{ $row['to_mail'] }}
                                            </td>
                                            <td class="truncate-cell" title="{{ $row['subject'] }}">
                                                {{ $row['subject'] }}
                                                @if($row['attach_count'] > 0)
                                                    <span class="badge badge-secondary attach-badge ml-1">
                                                        @icon('fa-paperclip') {{ $row['attach_count'] }}
                                                    </span>
                                                @endif
                                            </td>
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
                                                    <a href="{{ route('clients.detail', $row['client_id']) }}" target="_blank"
                                                       title="{{ $row['client_name'] }}">
                                                        {{ \Illuminate\Support\Str::limit($row['client_name'], 22) }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $row['type'] === 'client' ? 'primary' : ($row['type'] === 'lead' ? 'warning' : 'info') }}">
                                                    {{ ucfirst($row['type'] ?? '—') }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('adminconsole.features.sent-emails.show', $row['id']) }}"
                                                   class="btn btn-sm btn-info">
                                                    @icon('fa-eye')
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if($paginator && $paginator->hasPages())
                            <div class="card-footer d-flex justify-content-center">
                                {{ $paginator->links() }}
                            </div>
                            @endif

                            @else
                            <div class="p-4 text-center text-muted">
                                @icon('fa-inbox', ['class' => 'fa-2x mb-2'])
                                <p>No sent emails found matching your criteria.</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @else
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            @icon('fa-search', ['class' => 'fa-3x mb-3'])
                            <p class="lead">Use the filters above to search sent emails.</p>
                        </div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
// Collapse filters after a search has been run
@if($paginator !== null && request()->hasAny(['search','date_from','date_to','sender_id','from_address','client_id','type','has_attachments']))
document.addEventListener('DOMContentLoaded', function () {
    var filterBody = document.getElementById('filterBody');
    if (filterBody) {
        $(filterBody).collapse('show');
    }
    $('[data-toggle="tooltip"]').tooltip();
});
@else
document.addEventListener('DOMContentLoaded', function () {
    $('[data-toggle="tooltip"]').tooltip();
});
@endif
</script>
@endsection
