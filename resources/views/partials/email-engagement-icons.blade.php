{{--
  Engagement icons: only spam-report is shown.
  Open and click tracking are disabled for deliverability — open pixels are pre-fetched by
  Gmail and Apple Mail making data unreliable, and click-tracking rewrites links through
  SendGrid's shared domain which can harm spam scores and break signed/portal links.
  Delivery status (delivered, bounced, etc.) is tracked reliably via SMTP delivery events.
--}}
@if(!empty($spam_reported_at))
    <span class="email-engagement-icons ml-1">
        @icon('fa-exclamation-triangle', ['class' => 'text-danger', 'data-toggle' => 'tooltip', 'title' => 'Marked as spam ' . \Carbon\Carbon::parse($spam_reported_at)->format('d M Y, H:i')])
    </span>
@endif
