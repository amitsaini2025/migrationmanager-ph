@php
    $personalDetailsTabFragmentUrl = route('clients.detail.personaldetails-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Personal Details content loads on first open. --}}
<div class="tab-pane" id="personaldetails-tab"
     data-personaldetails-lazy="1"
     data-personaldetails-url="{{ $personalDetailsTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-personaldetails-lazy-placeholder style="padding: 24px; color: #6c757d;">
            Loading personal details&hellip;
        </div>
    </div>
</div>
