{{-- Shared Travel Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'travel' => null, 'mode' => 'create', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Travel" onclick="removeTravelField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    <input type="hidden" name="travel_id[{{ $index }}]" value="{{ ($mode === 'edit' && $travel?->id) ? $travel->id : '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Country Visited</label>
            <select name="travel_country_visited[{{ $index }}]" class="travel-country-field">
                <option value="">Select Country</option>
                @foreach($countries as $country)
                    @php
                        $countryName = is_object($country) ? $country->name : $country;
                    @endphp
                    <option value="{{ $countryName }}" {{ ($travel->country_visited ?? old("travel_country_visited.$index")) == $countryName ? 'selected' : '' }}>
                        {{ $countryName }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Arrival Date</label>
            <input type="text" 
                   name="travel_arrival_date[{{ $index }}]" 
                   value="{{ $travel && $travel->arrival_date ? date('d/m/Y', strtotime($travel->arrival_date)) : old("travel_arrival_date.$index") }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Departure Date</label>
            <input type="text" 
                   name="travel_departure_date[{{ $index }}]" 
                   value="{{ $travel && $travel->departure_date ? date('d/m/Y', strtotime($travel->departure_date)) : old("travel_departure_date.$index") }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Travel Purpose</label>
            <input type="text" 
                   name="travel_purpose[{{ $index }}]" 
                   value="{{ $travel->travel_purpose ?? old("travel_purpose.$index") }}" 
                   placeholder="Travel Purpose">
        </div>
    </div>
</div>

