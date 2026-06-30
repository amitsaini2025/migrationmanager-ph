{{-- Shared Phone Number Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'contact' => null, 'mode' => 'create'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    @if($mode === 'edit' && $contact?->id)
        <input type="hidden" name="contact_id[{{ $index }}]" value="{{ $contact->id }}">
    @endif
    
    <div class="content-grid">
        <div class="form-group">
            <label>Type</label>
            <select name="{{ $mode === 'edit' ? 'contact_type_hidden' : 'contact_type' }}[{{ $index }}]" class="contact-type-selector">
                <option value="Personal" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Personal' ? 'selected' : '' }}>Personal</option>
                <option value="Work" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Work' ? 'selected' : '' }}>Work</option>
                <option value="Mobile" {{ ($contact->contact_type ?? old("contact_type.$index", 'Mobile')) == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                <option value="Business" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Business' ? 'selected' : '' }}>Business</option>
                <option value="Secondary" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Secondary' ? 'selected' : '' }}>Secondary</option>
                <option value="Father" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Father' ? 'selected' : '' }}>Father</option>
                <option value="Mother" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Mother' ? 'selected' : '' }}>Mother</option>
                <option value="Brother" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Brother' ? 'selected' : '' }}>Brother</option>
                <option value="Sister" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Sister' ? 'selected' : '' }}>Sister</option>
                <option value="Uncle" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Uncle' ? 'selected' : '' }}>Uncle</option>
                <option value="Aunt" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Aunt' ? 'selected' : '' }}>Aunt</option>
                <option value="Cousin" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Cousin' ? 'selected' : '' }}>Cousin</option>
                <option value="Others" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Others' ? 'selected' : '' }}>Others</option>
                <option value="Partner" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Partner' ? 'selected' : '' }}>Partner</option>
                <option value="Not In Use" {{ ($contact->contact_type ?? old("contact_type.$index")) == 'Not In Use' ? 'selected' : '' }}>Not In Use</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Number</label>
            <div class="cus_field_input flex-container">
                <div class="country_code">
                    @php
                        use App\Helpers\PhoneHelper;
                        $selectedDial = ($contact?->country_code ?? null);
                        if ($selectedDial === null || trim((string) $selectedDial) === '') {
                            $selectedDial = old('country_code.'.$index);
                        }
                        if ($selectedDial === null || trim((string) $selectedDial) === '') {
                            $selectedDial = PhoneHelper::getDefaultCountryCode();
                        } else {
                            $selectedDial = PhoneHelper::normalizeCountryCode($selectedDial);
                        }
                    @endphp
                    @include('partials.country-code-select', [
                        'name' => 'country_code['.$index.']',
                        'selected' => $selectedDial,
                        'selectClass' => 'country-code-input',
                        'showPlaceholder' => false,
                    ])
                </div>
                <input type="tel" 
                       name="phone[{{ $index }}]" 
                       value="{{ $contact->phone ?? old("phone.$index") }}" 
                       placeholder="Phone Number" 
                       class="phone-number-input phone-width" 
                       autocomplete="off">
            </div>
        </div>
    </div>
</div>

