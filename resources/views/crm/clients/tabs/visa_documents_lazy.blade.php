@php
    $visaDocumentsTabFragmentUrl = route('clients.detail.visa-documents-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Visa Documents content loads on first open. --}}
<div class="tab-pane" id="visadocuments-tab"
     data-visadocuments-lazy="1"
     data-visadocuments-url="{{ $visaDocumentsTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-visadocuments-lazy-placeholder style="padding: 24px; color: #6c757d;">
            @include('crm.clients.tabs.partials.lazy_loading', ['message' => 'Loading visa documents…'])
        </div>
    </div>
</div>
