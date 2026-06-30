{{-- Phone Number Field Component --}}
@props(['index', 'contact'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Phone" onclick="removePhoneField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="contact_id[{{ $index }}]" value="{{ $contact->id ?? '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Type</label>
            <select name="contact_type_hidden[{{ $index }}]" class="contact-type-selector">
                <option value="Personal" {{ $contact->contact_type == 'Personal' ? 'selected' : '' }}>Personal</option>
                <option value="Work" {{ $contact->contact_type == 'Work' ? 'selected' : '' }}>Work</option>
                <option value="Mobile" {{ $contact->contact_type == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                <option value="Business" {{ $contact->contact_type == 'Business' ? 'selected' : '' }}>Business</option>
                <option value="Secondary" {{ $contact->contact_type == 'Secondary' ? 'selected' : '' }}>Secondary</option>
                <option value="Father" {{ $contact->contact_type == 'Father' ? 'selected' : '' }}>Father</option>
                <option value="Mother" {{ $contact->contact_type == 'Mother' ? 'selected' : '' }}>Mother</option>
                <option value="Brother" {{ $contact->contact_type == 'Brother' ? 'selected' : '' }}>Brother</option>
                <option value="Sister" {{ $contact->contact_type == 'Sister' ? 'selected' : '' }}>Sister</option>
                <option value="Uncle" {{ $contact->contact_type == 'Uncle' ? 'selected' : '' }}>Uncle</option>
                <option value="Aunt" {{ $contact->contact_type == 'Aunt' ? 'selected' : '' }}>Aunt</option>
                <option value="Cousin" {{ $contact->contact_type == 'Cousin' ? 'selected' : '' }}>Cousin</option>
                <option value="Others" {{ $contact->contact_type == 'Others' ? 'selected' : '' }}>Others</option>
                <option value="Partner" {{ $contact->contact_type == 'Partner' ? 'selected' : '' }}>Partner</option>
                <option value="Not In Use" {{ $contact->contact_type == 'Not In Use' ? 'selected' : '' }}>Not In Use</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Number</label>
            <div class="cus_field_input flex-container">
                <div class="country_code">
                    @php
                        use App\Helpers\PhoneHelper;
                        $ccRaw = $contact->country_code ?? '';
                        $selectedDial = ($ccRaw === null || trim((string) $ccRaw) === '')
                            ? PhoneHelper::getDefaultCountryCode()
                            : PhoneHelper::normalizeCountryCode($ccRaw);
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
                       value="{{ $contact->phone }}" 
                       placeholder="Phone Number" 
                       class="phone-number-input phone-width" 
                       autocomplete="off">
            </div>
        </div>
    </div>
</div>

