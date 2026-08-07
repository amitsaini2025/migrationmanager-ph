<link rel="stylesheet" href="{{ URL::asset('css/workflow-tab.css') }}?v={{ time() }}">

@php
    $workflowTabFragmentUrl = !empty($encodeId)
        ? route('clients.detail.workflow-tab', array_filter([
            'client_id' => $encodeId,
            'client_unique_matter_ref_no' => $id1 ?? null,
        ], static function ($value) {
            return $value !== null && $value !== '';
        }))
        : '';
@endphp

<!-- Workflow Tab — full content (eager URL / fragment endpoint) -->
<div class="tab-pane" id="workflow-tab"
     @if($workflowTabFragmentUrl !== '') data-workflow-url="{{ $workflowTabFragmentUrl }}" @endif>
    <div class="workflow-v2">
        <div class="card full-width workflow-tab-container">
            @include('crm.clients.tabs.partials.workflow-tab-body')
        </div>
    </div>
</div>
