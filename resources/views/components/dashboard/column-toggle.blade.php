@props(['visibleColumns'])

<div class="column-toggle-container">
    <button class="column-toggle-btn" type="button" id="columnToggleBtn">
        @icon('columns-3')
        <span class="visible-count">{{ count($visibleColumns) }}</span>
    </button>
    <div class="column-dropdown" id="columnDropdown">
        <div class="column-dropdown-header">
            <label class="column-toggle-all">
                <input type="checkbox" id="toggleAllColumns" {{ count($visibleColumns) == 8 ? 'checked' : '' }}>
                <span>Display All</span>
            </label>
        </div>
        <div class="column-dropdown-body">
            @php
                $columns = [
                    'matter' => 'Matter',
                    'client_id' => 'Client ID',
                    'client_name' => 'Client Name',
                    'dob' => 'DOB',
                    'migration_agent' => 'Migration Agent',
                    'person_responsible' => 'Person Responsible',
                    'person_assisting' => 'Person Assisting',
                    'stage' => 'Stage'
                ];
            @endphp
            
            @foreach($columns as $key => $label)
                <label class="column-option">
                    <input type="checkbox" name="column" value="{{ $key }}" {{ in_array($key, $visibleColumns) ? 'checked' : '' }}>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>
