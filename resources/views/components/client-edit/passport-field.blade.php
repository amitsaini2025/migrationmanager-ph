{{-- Passport Field Component --}}
@props(['index', 'passport', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Passport" onclick="removePassportField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="passport_id[{{ $index }}]" value="{{ $passport->id ?? '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Country</label>
            <select name="passports[{{ $index }}][passport_country]" class="passport-country-field" data-country-select="priority" data-selected="{{ $passport->passport_country ?? '' }}">
                <option value="">Select Country</option>
                @if(($passport->passport_country ?? '') !== '')
                    <option value="{{ $passport->passport_country }}" selected>{{ $passport->passport_country }}</option>
                @endif
            </select>
        </div>
        
        <div class="form-group">
            <label>Passport #</label>
            <input type="text" 
                   name="passports[{{ $index }}][passport_number]" 
                   value="{{ $passport->passport ?? '' }}" 
                   placeholder="Passport Number">
        </div>
        
        <div class="form-group">
            <label>Issue Date</label>
            <input type="text" 
                   name="passports[{{ $index }}][issue_date]" 
                   value="{{ $passport && $passport->passport_issue_date ? date('d/m/Y', strtotime($passport->passport_issue_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Expiry Date</label>
            <input type="text" 
                   name="passports[{{ $index }}][expiry_date]" 
                   value="{{ $passport && $passport->passport_expiry_date ? date('d/m/Y', strtotime($passport->passport_expiry_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker">
        </div>
    </div>
</div>
