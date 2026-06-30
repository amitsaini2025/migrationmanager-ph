{{-- Travel Field Component --}}
@props(['index', 'travel', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Travel" onclick="removeTravelField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="travel_id[{{ $index }}]" value="{{ $travel->id ?? '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Country Visited</label>
            <select name="travel_country_visited[{{ $index }}]" class="travel-country-field">
                <option value="">Select Country</option>
                @foreach($countries as $country)
                    <option value="{{ $country }}" {{ ($travel->country_visited ?? '') == $country ? 'selected' : '' }}>
                        {{ $country }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Arrival Date</label>
            <input type="text" 
                   name="travel_arrival_date[{{ $index }}]" 
                   value="{{ $travel && $travel->arrival_date ? date('d/m/Y', strtotime($travel->arrival_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Departure Date</label>
            <input type="text" 
                   name="travel_departure_date[{{ $index }}]" 
                   value="{{ $travel && $travel->departure_date ? date('d/m/Y', strtotime($travel->departure_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Travel Purpose</label>
            <input type="text" 
                   name="travel_purpose[{{ $index }}]" 
                   value="{{ $travel->travel_purpose ?? '' }}" 
                   placeholder="Travel Purpose">
        </div>
    </div>
</div>
