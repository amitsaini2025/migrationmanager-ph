@extends('layouts.crm_client_detail')
@section('title', 'SMS Details')

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
                            <h4>@icon('fa-sms') SMS Details</h4>
                            <div class="card-header-action">
                                <a href="{{ route('adminconsole.features.sms.history') }}" class="btn btn-secondary">
                                    @icon('fa-arrow-left') Back to History
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>Recipient Phone:</strong></label>
                                        <p class="form-control-plaintext">{{ $smsLog->formatted_phone ?? $smsLog->recipient_phone }}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>Status:</strong></label>
                                        <p>
                                            <span class="badge badge-{{ $smsLog->status === 'sent' ? 'success' : ($smsLog->status === 'failed' ? 'danger' : 'warning') }}">
                                                {{ ucfirst($smsLog->status) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>Provider:</strong></label>
                                        <p>
                                            <span class="badge badge-{{ $smsLog->provider === 'cellcast' ? 'danger' : 'info' }}">
                                                {{ strtoupper($smsLog->provider) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>Sent At:</strong></label>
                                        <p class="form-control-plaintext">{{ $smsLog->created_at->format('M d, Y H:i:s') }}</p>
                                    </div>
                                </div>
                                @if($smsLog->delivered_at)
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>Delivered At:</strong></label>
                                        <p class="form-control-plaintext">{{ $smsLog->delivered_at->format('M d, Y H:i:s') }}</p>
                                    </div>
                                </div>
                                @endif
                                @if($smsLog->client)
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>Client:</strong></label>
                                        <p class="form-control-plaintext">{{ $smsLog->client->first_name }} {{ $smsLog->client->last_name }}</p>
                                    </div>
                                </div>
                                @endif
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Message Content:</strong></label>
                                <div class="form-control-plaintext" style="background: #f8f9fa; padding: 15px; border-radius: 5px; white-space: pre-wrap;">{{ $smsLog->message_content }}</div>
                            </div>
                            
                            @if($smsLog->error_message)
                            <div class="form-group">
                                <label><strong>Error Message:</strong></label>
                                <div class="alert alert-danger">{{ $smsLog->error_message }}</div>
                            </div>
                            @endif
                            
                            @if($smsLog->provider_message_id)
                            <div class="form-group">
                                <label><strong>Provider Message ID:</strong></label>
                                <p class="form-control-plaintext" style="font-family: monospace;">{{ $smsLog->provider_message_id }}</p>
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
