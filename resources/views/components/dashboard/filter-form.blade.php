@props(['filters', 'workflowStages'])

<div class="filter-controls">
    <form id="filterForm" method="GET" action="{{ route('dashboard') }}">
        <div class="search-box">
            <input type="text" 
                   name="client_name" 
                   placeholder="Search Client Name..." 
                   value="{{ $filters['client_name'] ?? '' }}">
            @icon('search')
        </div>

        <select name="client_stage" class="stage-select">
            <option value="">All Stages</option>
            @foreach($workflowStages as $stage)
                <option value="{{ $stage->id }}" 
                        {{ (isset($filters['client_stage']) && $filters['client_stage'] == $stage->id) ? 'selected' : '' }}>
                    {{ $stage->name }}
                </option>
            @endforeach
        </select>

        <button type="submit" class="filter-button">
            @icon('filter') Filter
        </button>

        @if(isset($filters['client_name']) || isset($filters['client_stage']))
            <a href="{{ route('dashboard') }}" class="clear-filters" onclick="clearFiltersAndReset()">
                @icon('x') Clear Filters
            </a>
        @endif
    </form>
</div>
