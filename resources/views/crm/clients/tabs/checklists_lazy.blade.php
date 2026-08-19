@php
    $checklistsTabFragmentUrl = route('clients.detail.checklists-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Checklists content loads on first open. --}}
<div class="tab-pane" id="checklists-tab"
     data-checklists-lazy="1"
     data-checklists-url="{{ $checklistsTabFragmentUrl }}">
    <div class="card full-width checklists-container">
        <div class="workflow-v2-empty" data-checklists-lazy-placeholder style="padding: 24px; color: #6c757d;">
            @include('crm.clients.tabs.partials.lazy_loading', ['message' => 'Loading checklists…'])
        </div>
    </div>
</div>
