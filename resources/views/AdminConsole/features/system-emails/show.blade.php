@extends('layouts.crm_client_detail')
@section('title', 'System Email — Detail')

@section('styles')
<style>
    .meta-grid dt { font-weight: 600; color: #666; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .meta-grid dd { margin-bottom: 0.75rem; word-break: break-all; }
    .section-heading { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em;
                          color: #888; border-bottom: 1px solid #eee; padding-bottom: 0.4rem;
                          margin-bottom: 1rem; }
    .email-delivery-badge { font-size: 0.78rem; font-weight: 600; }
    .delivery-reason { color: #6b4423; font-size: 0.875rem; margin-top: 0.25rem; }
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

                    <div class="mb-3">
                        <a href="{{ route('adminconsole.features.system-emails.index') }}" class="btn btn-sm btn-secondary">
                            @icon('fa-arrow-left') Back to System Emails
                        </a>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                @icon('fa-envelope-open-text')
                                {{ $email->subject ?: '(no subject)' }}
                            </h4>
                            <div class="card-header-action">
                                <span class="badge badge-secondary">{{ $categoryLabel }}</span>
                                @include('partials.email-delivery-status-badge', [
                                    'status' => $email->delivery_status ?? 'pending',
                                    'reason' => $email->status_reason,
                                ])
                            </div>
                        </div>

                        <div class="card-body">
                            <p class="section-heading">Email Details</p>
                            <dl class="row meta-grid">
                                <dt class="col-sm-3">Date &amp; Time</dt>
                                <dd class="col-sm-9">{{ $email->created_at->format('d M Y, H:i:s') }}</dd>

                                <dt class="col-sm-3">Category</dt>
                                <dd class="col-sm-9">{{ $categoryLabel }}</dd>

                                <dt class="col-sm-3">Triggered By</dt>
                                <dd class="col-sm-9">
                                    @if($email->uploader)
                                        {{ $email->uploader->first_name }} {{ $email->uploader->last_name }}
                                    @else
                                        System (automated)
                                    @endif
                                </dd>

                                <dt class="col-sm-3">From</dt>
                                <dd class="col-sm-9">{{ $email->from_mail ?: '—' }}</dd>

                                <dt class="col-sm-3">To</dt>
                                <dd class="col-sm-9">{{ $toDisplay ?: '—' }}</dd>

                                @if($ccDisplay)
                                <dt class="col-sm-3">CC</dt>
                                <dd class="col-sm-9">{{ $ccDisplay }}</dd>
                                @endif

                                <dt class="col-sm-3">Delivery Status</dt>
                                <dd class="col-sm-9">
                                    @include('partials.email-delivery-status-badge', [
                                        'status' => $email->delivery_status ?? 'pending',
                                        'reason' => $email->status_reason,
                                    ])
                                    @if($email->delivered_at)
                                        <div class="text-muted mt-1" style="font-size:0.875rem;">
                                            Delivered at {{ $email->delivered_at->format('d M Y, H:i:s') }}
                                        </div>
                                    @endif
                                    @if($email->status_reason && in_array($email->delivery_status, ['bounced', 'dropped', 'deferred', 'blocked', 'send_failed'], true))
                                        <div class="delivery-reason">{{ e($email->status_reason) }}</div>
                                    @endif
                                </dd>
                            </dl>

                            @if($email->client || $email->clientMatter)
                            <p class="section-heading mt-3">Client &amp; Matter</p>
                            <dl class="row meta-grid">
                                @if($email->client)
                                <dt class="col-sm-3">Client / Lead</dt>
                                <dd class="col-sm-9">
                                    {{ $email->client->first_name }} {{ $email->client->last_name }}
                                    <a href="{{ route('clients.detail', $email->client_id) }}" target="_blank"
                                       class="btn btn-xs btn-outline-primary ml-2" style="font-size:0.75rem;padding:1px 6px;">
                                        @icon('fa-external-link-alt') Open CRM
                                    </a>
                                </dd>
                                @endif
                                @if($email->clientMatter)
                                <dt class="col-sm-3">Matter</dt>
                                <dd class="col-sm-9">
                                    {{ $email->clientMatter->matter->title ?? $email->clientMatter->matter->nick_name ?? 'Matter #' . $email->client_matter_id }}
                                </dd>
                                @endif
                            </dl>
                            @endif
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">@icon('fa-stream') Activity Timeline</h4>
                        </div>
                        <div class="card-body">
                            @include('partials.email-event-timeline', [
                                'sentAt' => $email->created_at,
                                'events' => $email->sendgridEvents,
                                'deliveryStatus' => $email->delivery_status,
                            ])
                        </div>
                    </div>

                    @if($email->message)
                    <div class="card">
                        <div class="card-header"><h4 class="mb-0">@icon('fa-align-left') Message</h4></div>
                        <div class="card-body">{!! $email->message !!}</div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </section>
</div>
@endsection
