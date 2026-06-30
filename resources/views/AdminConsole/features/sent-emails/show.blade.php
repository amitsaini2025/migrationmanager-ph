@extends('layouts.crm_client_detail')
@section('title', 'Sent Email — Detail')

@section('styles')
<style>
    .meta-grid dt { font-weight: 600; color: #666; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .meta-grid dd { margin-bottom: 0.75rem; word-break: break-all; }
    .preview-frame { width: 100%; height: 600px; border: 1px solid #dee2e6; border-radius: 4px; background: #fff; }
    .preview-placeholder { background: #f8f9fa; border: 1px dashed #ced4da; border-radius: 4px;
                            padding: 2.5rem; text-align: center; color: #6c757d; }
    .attach-item { display: flex; align-items: center; gap: 0.5rem;
                   padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; border-radius: 4px;
                   margin-bottom: 0.5rem; background: #fff; }
    .attach-item i { font-size: 1.1rem; }
    .type-badge-client  { background-color: #3498db; }
    .type-badge-lead    { background-color: #f39c12; }
    .type-badge-agent   { background-color: #8e44ad; }
    .section-heading    { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em;
                          color: #888; border-bottom: 1px solid #eee; padding-bottom: 0.4rem;
                          margin-bottom: 1rem; }
    .email-delivery-badge { font-size: 0.78rem; font-weight: 600; }
    .delivery-reason { color: #6b4423; font-size: 0.875rem; margin-top: 0.25rem; }
    .email-event-timeline { position: relative; padding-left: 0.25rem; }
    .email-event-item { display: flex; gap: 0.85rem; padding-bottom: 1.1rem; position: relative; }
    .email-event-item:not(:last-child)::before {
        content: ''; position: absolute; left: 0.95rem; top: 1.75rem; bottom: 0;
        width: 2px; background: #e9ecef;
    }
    .email-event-icon {
        width: 2rem; height: 2rem; border-radius: 50%; background: #f8f9fa;
        border: 1px solid #dee2e6; display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; z-index: 1;
    }
    .email-event-icon--sent { background: #e8f4fd; border-color: #b6d9f2; color: #1565c0; }
    .email-event-body { min-width: 0; flex: 1; }
    .email-event-label { font-weight: 600; color: #333; }
    .email-event-time { font-size: 0.82rem; color: #6c757d; }
    .email-event-detail { font-size: 0.82rem; margin-top: 0.2rem; word-break: break-all; }
    .email-event-detail--reason { color: #842029; }
    .email-event-empty { padding: 0.5rem 0 0.25rem 2.85rem; }
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

                    {{-- Navigation --}}
                    <div class="mb-3">
                        <a href="{{ route('adminconsole.features.sent-emails.index') }}" class="btn btn-sm btn-secondary">
                            @icon('fa-arrow-left') Back to Sent Emails
                        </a>
                    </div>

                    {{-- Metadata card --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                @icon('fa-envelope-open-text')
                                {{ $email->subject ?: '(no subject)' }}
                            </h4>
                            <div class="card-header-action">
                                <span class="badge type-badge-{{ $email->type ?? 'client' }} text-white">
                                    {{ ucfirst($email->type ?? 'client') }}
                                </span>
                                @include('partials.email-delivery-status-badge', [
                                    'status' => $email->delivery_status ?? 'pending',
                                    'reason' => $email->status_reason,
                                ])
                                @include('partials.email-engagement-icons', [
                                    'opened_at' => $email->opened_at,
                                    'clicked_at' => $email->clicked_at,
                                    'spam_reported_at' => $email->spam_reported_at,
                                ])
                            </div>
                        </div>

                        <div class="card-body">
                            <p class="section-heading">Email Details</p>
                            <dl class="row meta-grid">
                                <dt class="col-sm-3">Date &amp; Time</dt>
                                <dd class="col-sm-9">{{ $email->created_at->format('d M Y, H:i:s') }}</dd>

                                <dt class="col-sm-3">Sent By</dt>
                                <dd class="col-sm-9">
                                    @if($email->uploader)
                                        {{ $email->uploader->first_name }} {{ $email->uploader->last_name }}
                                        <small class="text-muted">&lt;{{ $email->uploader->email }}&gt;</small>
                                    @else
                                        <span class="text-muted">—</span>
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

                                <dt class="col-sm-3">Subject</dt>
                                <dd class="col-sm-9">{{ $email->subject ?: '—' }}</dd>

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

                                @if($email->template_id)
                                <dt class="col-sm-3">Template ID</dt>
                                <dd class="col-sm-9">{{ $email->template_id }}</dd>
                                @endif
                            </dl>

                            {{-- Client / Matter --}}
                            @if($email->client || $email->clientMatter)
                            <p class="section-heading mt-3">Client &amp; Matter</p>
                            <dl class="row meta-grid">
                                @if($email->client)
                                <dt class="col-sm-3">Client / Lead</dt>
                                <dd class="col-sm-9">
                                    {{ $email->client->first_name }} {{ $email->client->last_name }}
                                    @if($email->client->client_id)
                                        <small class="text-muted">({{ $email->client->client_id }})</small>
                                    @endif
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

                    {{-- Event timeline --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">@icon('fa-stream') Activity Timeline</h4>
                        </div>
                        <div class="card-body">
                            @if($email->spam_reported_at)
                                <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.875rem;">
                                    @icon('fa-exclamation-triangle')
                                    Recipient marked this email as spam on {{ $email->spam_reported_at->format('d M Y, H:i') }}.
                                </div>
                            @endif
                            @include('partials.email-event-timeline', [
                                'sentAt' => $email->created_at,
                                'events' => $email->sendgridEvents,
                                'deliveryStatus' => $email->delivery_status,
                            ])
                            <p class="text-muted mb-0 mt-2" style="font-size:0.78rem;">
                                @icon('fa-info-circle')
                                Delivery events (sent, delivered, bounced, etc.) are tracked via SendGrid.
                                Open and click tracking are disabled to maximise deliverability and preserve link integrity.
                            </p>
                        </div>
                    </div>

                    {{-- Attachments --}}
                    @if($attachments->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                @icon('fa-paperclip') Attachments
                                <span class="badge badge-secondary ml-1">{{ $attachments->count() }}</span>
                            </h4>
                        </div>
                        <div class="card-body">
                            @foreach($attachments->where('is_inline', false) as $att)
                            <div class="attach-item">
                                @if(in_array($att->content_type, ['image/jpeg','image/png','image/gif','image/webp']))
                                    @icon('fa-file-image', ['class' => 'text-info'])
                                @elseif($att->content_type === 'application/pdf')
                                    @icon('fa-file-pdf', ['class' => 'text-danger'])
                                @else
                                    @icon('fa-paperclip', ['class' => 'text-secondary'])
                                @endif
                                <span class="flex-grow-1">{{ $att->display_name ?? $att->filename }}</span>
                                @if($att->file_size)
                                <small class="text-muted">{{ $att->formatted_file_size }}</small>
                                @endif
                                @if($att->s3_key)
                                    {{-- S3 attachment: link via the document's stored URL --}}
                                    <a href="{{ $att->file_path ?? '#' }}" target="_blank" class="btn btn-xs btn-outline-secondary"
                                       style="font-size:0.75rem;padding:1px 6px;">
                                        @icon('fa-download') Download
                                    </a>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Body preview --}}
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">@icon('fa-envelope') Email Preview</h4>
                            @if($previewUrl)
                            <div class="card-header-action">
                                <a href="{{ $previewUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    @icon('fa-external-link-alt') Open Full Preview
                                </a>
                            </div>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($previewUrl)
                                <iframe src="{{ $previewUrl }}"
                                        class="preview-frame"
                                        sandbox="allow-same-origin"
                                        title="Email preview"></iframe>
                            @else
                                <div class="preview-placeholder">
                                    @icon('fa-file-alt', ['class' => 'fa-2x mb-2'])
                                    <p class="mb-0">No preview available.</p>
                                    <small>A full HTML archive is only stored for emails sent via the CRM compose window.</small>
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
