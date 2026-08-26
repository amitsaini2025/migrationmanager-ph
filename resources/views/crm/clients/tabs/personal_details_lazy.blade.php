@php
    $personalDetailsTabFragmentUrl = route('clients.detail.personaldetails-tab', array_filter([
        'client_id' => $encodeId,
        'client_unique_matter_ref_no' => $id1 ?? null,
    ], static function ($value) {
        return $value !== null && $value !== '';
    }));
    // Default tab: pane must already be active so activity-feed JS and first paint match today.
    $personalDetailsTabIsActive = \App\Support\ClientDetailTabs::shouldEagerRender('personaldetails', $activeTab ?? 'personaldetails');
@endphp

{{-- Lightweight shell: full Personal Details content loads on first open / default URL. --}}
<div class="tab-pane{{ $personalDetailsTabIsActive ? ' active' : '' }}" id="personaldetails-tab"
     data-personaldetails-lazy="1"
     data-personaldetails-url="{{ $personalDetailsTabFragmentUrl }}">
    <div class="card full-width">
        <div class="workflow-v2-empty" data-personaldetails-lazy-placeholder style="padding: 24px; color: #6c757d;">
            Loading personal details&hellip;
        </div>
    </div>
</div>
