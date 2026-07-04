{{-- Cases requiring attention list (lazy-loaded AJAX fragment). Expects: $cases_requiring_attention_data, $count --}}
@if(count($cases_requiring_attention_data) > 0)
    <ul class="case-list">
        @foreach($cases_requiring_attention_data as $case)
            <x-dashboard.case-item :case="$case" />
        @endforeach
    </ul>
@else
    <div class="empty-state-modern">
        @icon('fa-thumbs-up', ['class' => 'empty-state-icon'])
        <h4>Great Work!</h4>
        <p>No cases requiring immediate attention.</p>
    </div>
@endif
