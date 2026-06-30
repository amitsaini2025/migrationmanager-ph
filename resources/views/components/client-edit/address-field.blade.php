{{-- Single Address Entry Component --}}
@props(['index', 'address' => null, 'showRemoveButton' => true, 'isCurrent' => false])

<div class="address-entry-wrapper" data-address-index="{{ $index }}">
    <input type="hidden" name="address_id[]" value="{{ $address->id ?? '' }}">

    <div class="address-entry-header">
        @if($isCurrent)
        <div class="address-current-toggle address-current-readonly">
            <span class="address-current-label">Current Address?</span>
            <label class="switch switch-readonly" title="Current address (based on latest start date)">
                <input type="checkbox" checked disabled>
                <span class="slider round"></span>
            </label>
        </div>
        @endif
        @if($showRemoveButton)
        <button type="button" class="remove-address-btn" onclick="removeAddressEntry(this)" title="Remove Address">
            @icon('x', ['class' => 'icon-sm'])
        </button>
        @endif
    </div>
    
    {{-- Address Search --}}
    <div class="form-group address-search-container">
        <label for="address_search_{{ $index }}">Search Address</label>
        <input type="text" 
               id="address_search_{{ $index }}" 
               name="address_search[]" 
               class="address-search-input" 
               placeholder="Start typing an address..."
               autocomplete="off"
               data-address-index="{{ $index }}"
               value="{{ $address ? ($address->address_line_1 ? $address->address_line_1 . ', ' . $address->suburb . ', ' . $address->state : $address->address) : '' }}">
    </div>
    
    {{-- Structured Address Fields --}}
    <div class="address-fields-grid">
        <div class="form-group">
            <label for="address_line_1_{{ $index }}">Address Line 1 *</label>
            <input type="text" 
                   id="address_line_1_{{ $index }}" 
                   name="address_line_1[]" 
                   value="{{ $address->address_line_1 ?? '' }}"
                   placeholder="Street number and name"
                   class="address-required">
        </div>
        
        <div class="form-group">
            <label for="address_line_2_{{ $index }}">Address Line 2</label>
            <input type="text" 
                   id="address_line_2_{{ $index }}" 
                   name="address_line_2[]" 
                   value="{{ $address->address_line_2 ?? '' }}"
                   placeholder="Apartment, suite, unit, etc.">
        </div>
    </div>
    
    <div class="address-fields-grid">
        <div class="form-group">
            <label for="suburb_{{ $index }}">Suburb *</label>
            <input type="text" 
                   id="suburb_{{ $index }}" 
                   name="suburb[]" 
                   value="{{ $address ? ($address->suburb ?? '') : '' }}"
                   placeholder="Suburb"
                   class="address-required">
        </div>
        
        <div class="form-group">
            <label for="state_{{ $index }}">State *</label>
            <input type="text" 
                   id="state_{{ $index }}" 
                   name="state[]" 
                   value="{{ $address->state ?? '' }}"
                   placeholder="State"
                   class="address-required">
        </div>
    </div>
    
    <div class="address-fields-grid">
        <div class="form-group">
            <label for="zip_{{ $index }}">Postcode *</label>
            <input type="text" 
                   id="zip_{{ $index }}" 
                   name="zip[]" 
                   value="{{ $address->zip ?? '' }}"
                   placeholder="Postcode"
                   class="address-required">
        </div>
        
        <div class="form-group">
            <label for="country_{{ $index }}">Country *</label>
            <input type="text" 
                   id="country_{{ $index }}" 
                   name="country[]" 
                   value="{{ $address ? ($address->country ?? 'Australia') : 'Australia' }}"
                   placeholder="Country"
                   class="address-required">
        </div>
    </div>
    
    <div class="form-group">
        <label for="regional_code_{{ $index }}">Regional Code</label>
        <input type="text" 
               id="regional_code_{{ $index }}" 
               name="regional_code[]" 
               value="{{ $address->regional_code ?? '' }}"
               placeholder="Regional code (auto-calculated)"
               class="regional-code-field"
               readonly>
    </div>
    
    <div class="date-fields">
        <div class="form-group">
            <label for="address_start_date_{{ $index }}">Start Date</label>
            <input type="text" 
                   id="address_start_date_{{ $index }}" 
                   name="address_start_date[]" 
                   value="{{ $address && $address->start_date ? date('d/m/Y', strtotime($address->start_date)) : '' }}"
                   placeholder="dd/mm/yyyy"
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label for="address_end_date_{{ $index }}">End Date</label>
            <input type="text" 
                   id="address_end_date_{{ $index }}" 
                   name="address_end_date[]" 
                   value="{{ $address && $address->end_date ? date('d/m/Y', strtotime($address->end_date)) : '' }}"
                   placeholder="dd/mm/yyyy"
                   class="date-picker date-picker-past-only">
        </div>
    </div>
</div>

