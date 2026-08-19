<link rel="stylesheet" href="{{ URL::asset('css/workflow-tab.css') }}?v={{ time() }}">

@php
    $portalTabFragmentUrl = route('clients.detail.client-portal-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Client Portal content loads on first open (or via partial refresh). --}}
<div class="tab-pane" id="client_portal-tab"
     data-portal-lazy="1"
     data-portal-url="{{ $portalTabFragmentUrl }}">
    <div class="card full-width client-portal-container">
        <div class="workflow-v2-empty" data-portal-lazy-placeholder>
            @include('crm.clients.tabs.partials.lazy_loading', ['message' => 'Loading client portal…'])
        </div>
    </div>
</div>
