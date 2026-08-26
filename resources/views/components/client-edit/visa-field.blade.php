{{-- Visa Field Component --}}
@props(['index', 'visa', 'visaTypes' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Visa" onclick="removeVisaField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="visa_id[{{ $index }}]" value="{{ $visa->id ?? '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Visa Type / Subclass</label>
            <select name="visa_type_hidden[{{ $index }}]" class="visa-type-field" data-visa-select data-selected="{{ $visa->visa_type ?? '' }}">
                <option value="">Select Visa Type</option>
                @if(($visa->visa_type ?? '') !== '' && ($visa->visa_type ?? null) !== null)
                    <option value="{{ $visa->visa_type }}" selected>{{ $visa->matter?->title ?? $visa->visa_type }}{{ $visa->matter?->nick_name ? ' (' . $visa->matter->nick_name . ')' : '' }}</option>
                @endif
            </select>
        </div>
        
        <div class="form-group">
            <label>Visa Expiry Date</label>
            <input type="text" 
                   name="visa_expiry_date[{{ $index }}]" 
                   value="{{ $visa && $visa->visa_expiry_date ? date('d/m/Y', strtotime($visa->visa_expiry_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="visa-expiry-field date-picker">
        </div>
        
        <div class="form-group">
            <label>Visa Grant Date</label>
            <input type="text" 
                   name="visa_grant_date[{{ $index }}]" 
                   value="{{ $visa && $visa->visa_grant_date ? date('d/m/Y', strtotime($visa->visa_grant_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="visa-grant-field date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Visa Description</label>
            <input type="text" 
                   name="visa_description[{{ $index }}]" 
                   value="{{ $visa->visa_description ?? '' }}" 
                   class="visa-description-field" 
                   placeholder="Description">
        </div>
    </div>
</div>
