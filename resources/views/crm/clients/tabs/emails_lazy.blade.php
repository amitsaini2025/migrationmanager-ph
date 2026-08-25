@php
    $emailsTabFragmentUrl = route('clients.detail.emails-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Emails UI loads on first open. --}}
<div class="tab-pane" id="emails-tab"
     data-emails-lazy="1"
     data-emails-url="{{ $emailsTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-emails-lazy-placeholder style="padding: 24px; color: #6c757d;">
            Loading emails&hellip;
        </div>
    </div>
</div>
