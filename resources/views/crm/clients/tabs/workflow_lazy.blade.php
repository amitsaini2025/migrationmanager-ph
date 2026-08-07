<link rel="stylesheet" href="{{ URL::asset('css/workflow-tab.css') }}?v={{ time() }}">

@php
    $workflowTabFragmentUrl = route('clients.detail.workflow-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
@endphp

{{-- Lightweight shell: full Workflow content loads on first open (or via partial refresh). --}}
<div class="tab-pane" id="workflow-tab"
     data-workflow-lazy="1"
     data-workflow-url="{{ $workflowTabFragmentUrl }}">
    <div class="workflow-v2">
        <div class="card full-width workflow-tab-container">
            <div class="workflow-v2-empty" data-workflow-lazy-placeholder>
                Loading workflow&hellip;
            </div>
        </div>
    </div>
</div>
