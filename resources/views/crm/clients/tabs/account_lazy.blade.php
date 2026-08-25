@php
    $accountTabFragmentUrl = route('clients.detail.account-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Account content loads on first open. --}}
<div class="tab-pane" id="account-tab"
     data-account-lazy="1"
     data-account-url="{{ $accountTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-account-lazy-placeholder style="padding: 24px; color: #6c757d;">
            Loading account&hellip;
        </div>
    </div>
</div>
