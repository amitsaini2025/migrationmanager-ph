@extends('layouts.crm_client_detail')
@section('title', 'SMS History')

@section('content')
<!-- Main Content -->
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <div class="custom-error-msg">
            </div>
            <div class="row">
                <div class="col-3 col-md-3 col-lg-3">
                    @include('../Elements/CRM/setting')
                </div>
                <div class="col-9 col-md-9 col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h4>@icon('fa-history') SMS History</h4>
                            <p class="text-muted">View and manage all SMS messages</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>To</th>
                                            <th>Message</th>
                                            <th>Status</th>
                                            <th>Provider</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($smsLogs as $sms)
                                        <tr>
                                            <td>
                                                <small>{{ $sms->created_at->format('M d, Y H:i') }}</small>
                                            </td>
                                            <td>
                                                <span style="font-family: monospace;">{{ $sms->formatted_phone ?? $sms->recipient_phone }}</span>
                                            </td>
                                            <td>
                                                <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    {{ $sms->message_content }}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $sms->status === 'sent' ? 'success' : ($sms->status === 'failed' ? 'danger' : 'warning') }}">
                                                    {{ ucfirst($sms->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $sms->provider === 'cellcast' ? 'danger' : 'info' }}">
                                                    {{ strtoupper($sms->provider) }}
                                                </span>
                                            </td>
                                            <td>
                                                <a href="{{ route('adminconsole.features.sms.history.show', $sms->id) }}" class="btn btn-sm btn-info">
                                                    @icon('fa-eye') View
                                                </a>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                @icon('fa-inbox', ['class' => 'fa-2x text-muted mb-2'])
                                                <p class="text-muted">No SMS messages found</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($smsLogs->hasPages())
                            <div class="d-flex justify-content-center">
                                {{ $smsLogs->links() }}
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
