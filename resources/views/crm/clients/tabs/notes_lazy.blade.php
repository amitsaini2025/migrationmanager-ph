@php
    $notesTabFragmentUrl = route('clients.detail.notes-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Notes content loads on first open. --}}
<div class="tab-pane" id="noteterm-tab"
     data-noteterm-lazy="1"
     data-noteterm-url="{{ $notesTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-noteterm-lazy-placeholder style="padding: 24px; color: #6c757d;">
            @include('crm.clients.tabs.partials.lazy_loading', ['message' => 'Loading notes…'])
        </div>
    </div>
</div>
