{{-- Shared Passport Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'passport' => null, 'mode' => 'create', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Passport" onclick="removePassportField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    <input type="hidden" name="passport_id[{{ $index }}]" value="{{ ($mode === 'edit' && $passport?->id) ? $passport->id : '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Country</label>
            <select name="passports[{{ $index }}][passport_country]" class="passport-country-field">
                <option value="">Select Country</option>
                <option value="India" {{ ($passport->passport_country ?? old("passports.$index.passport_country")) == 'India' ? 'selected' : '' }}>India</option>
                <option value="Australia" {{ ($passport->passport_country ?? old("passports.$index.passport_country")) == 'Australia' ? 'selected' : '' }}>Australia</option>
                @foreach($countries as $country)
                    @if(is_object($country) && $country->name != 'India' && $country->name != 'Australia')
                        <option value="{{ $country->name }}" {{ ($passport->passport_country ?? old("passports.$index.passport_country")) == $country->name ? 'selected' : '' }}>{{ $country->name }}</option>
                    @elseif(is_string($country) && $country != 'India' && $country != 'Australia')
                        <option value="{{ $country }}" {{ ($passport->passport_country ?? old("passports.$index.passport_country")) == $country ? 'selected' : '' }}>{{ $country }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Passport #</label>
            <input type="text" 
                   name="passports[{{ $index }}][passport_number]" 
                   value="{{ $passport->passport ?? old("passports.$index.passport_number") }}" 
                   placeholder="Passport Number">
        </div>
        
        <div class="form-group">
            <label>Issue Date</label>
            <input type="text" 
                   name="passports[{{ $index }}][issue_date]" 
                   value="{{ $passport && $passport->passport_issue_date ? date('d/m/Y', strtotime($passport->passport_issue_date)) : old("passports.$index.issue_date") }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Expiry Date</label>
            <input type="text" 
                   name="passports[{{ $index }}][expiry_date]" 
                   value="{{ $passport && $passport->passport_expiry_date ? date('d/m/Y', strtotime($passport->passport_expiry_date)) : old("passports.$index.expiry_date") }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker">
        </div>
    </div>
</div>

