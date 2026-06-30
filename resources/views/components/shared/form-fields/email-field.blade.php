{{-- Shared Email Address Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'email' => null, 'mode' => 'create'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Email" onclick="removeEmailField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    @if($mode === 'edit' && $email?->id)
        <input type="hidden" name="email_id[{{ $index }}]" value="{{ $email->id }}">
    @endif
    
    <div class="content-grid">
        <div class="form-group">
            <label>Type</label>
            <select name="{{ $mode === 'edit' ? 'email_type_hidden' : 'email_type' }}[{{ $index }}]" class="email-type-selector">
                <option value="Personal" {{ ($email->email_type ?? old("email_type.$index", 'Personal')) == 'Personal' ? 'selected' : '' }}>Personal</option>
                <option value="Work" {{ ($email->email_type ?? old("email_type.$index")) == 'Work' ? 'selected' : '' }}>Work</option>
                <option value="Business" {{ ($email->email_type ?? old("email_type.$index")) == 'Business' ? 'selected' : '' }}>Business</option>
                <option value="Mobile" {{ ($email->email_type ?? old("email_type.$index")) == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                <option value="Secondary" {{ ($email->email_type ?? old("email_type.$index")) == 'Secondary' ? 'selected' : '' }}>Secondary</option>
                <option value="Father" {{ ($email->email_type ?? old("email_type.$index")) == 'Father' ? 'selected' : '' }}>Father</option>
                <option value="Mother" {{ ($email->email_type ?? old("email_type.$index")) == 'Mother' ? 'selected' : '' }}>Mother</option>
                <option value="Brother" {{ ($email->email_type ?? old("email_type.$index")) == 'Brother' ? 'selected' : '' }}>Brother</option>
                <option value="Sister" {{ ($email->email_type ?? old("email_type.$index")) == 'Sister' ? 'selected' : '' }}>Sister</option>
                <option value="Uncle" {{ ($email->email_type ?? old("email_type.$index")) == 'Uncle' ? 'selected' : '' }}>Uncle</option>
                <option value="Aunt" {{ ($email->email_type ?? old("email_type.$index")) == 'Aunt' ? 'selected' : '' }}>Aunt</option>
                <option value="Cousin" {{ ($email->email_type ?? old("email_type.$index")) == 'Cousin' ? 'selected' : '' }}>Cousin</option>
                <option value="Others" {{ ($email->email_type ?? old("email_type.$index")) == 'Others' ? 'selected' : '' }}>Others</option>
                <option value="Partner" {{ ($email->email_type ?? old("email_type.$index")) == 'Partner' ? 'selected' : '' }}>Partner</option>
                <option value="Not In Use" {{ ($email->email_type ?? old("email_type.$index")) == 'Not In Use' ? 'selected' : '' }}>Not In Use</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" 
                   name="email[{{ $index }}]" 
                   value="{{ $email->email ?? old("email.$index") }}" 
                   placeholder="Email Address">
        </div>
    </div>
</div>

