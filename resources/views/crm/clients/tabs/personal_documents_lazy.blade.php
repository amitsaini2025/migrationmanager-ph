@php
    $personalDocumentsTabFragmentUrl = route('clients.detail.personaldocuments-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Personal Documents UI loads on first open. --}}
<div class="tab-pane" id="personaldocuments-tab"
     data-personaldocuments-lazy="1"
     data-personaldocuments-url="{{ $personalDocumentsTabFragmentUrl }}">
    <div class="card full-width documentalls-container">
        <div class="workflow-v2-empty" data-personaldocuments-lazy-placeholder style="padding: 24px; color: #6c757d;">
            Loading personal documents&hellip;
        </div>
    </div>
</div>
