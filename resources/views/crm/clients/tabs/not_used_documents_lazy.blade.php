@php
    $notUsedDocumentsTabFragmentUrl = route('clients.detail.not-used-documents-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Not Used Documents content loads on first open. --}}
<div class="tab-pane" id="notuseddocuments-tab"
     data-notuseddocuments-lazy="1"
     data-notuseddocuments-url="{{ $notUsedDocumentsTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-notuseddocuments-lazy-placeholder style="padding: 24px; color: #6c757d;">
            @include('crm.clients.tabs.partials.lazy_loading', ['message' => 'Loading not used documents…'])
        </div>
    </div>
</div>
