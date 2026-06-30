{{-- Email Address Field Component --}}
@props(['index', 'email'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Email" onclick="removeEmailField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="email_id[{{ $index }}]" value="{{ $email->id }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Type</label>
            <select name="email_type_hidden[{{ $index }}]" class="email-type-selector">
                <option value="Personal" {{ $email->email_type == 'Personal' ? 'selected' : '' }}>Personal</option>
                <option value="Work" {{ $email->email_type == 'Work' ? 'selected' : '' }}>Work</option>
                <option value="Business" {{ $email->email_type == 'Business' ? 'selected' : '' }}>Business</option>
                <option value="Mobile" {{ $email->email_type == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                <option value="Secondary" {{ $email->email_type == 'Secondary' ? 'selected' : '' }}>Secondary</option>
                <option value="Father" {{ $email->email_type == 'Father' ? 'selected' : '' }}>Father</option>
                <option value="Mother" {{ $email->email_type == 'Mother' ? 'selected' : '' }}>Mother</option>
                <option value="Brother" {{ $email->email_type == 'Brother' ? 'selected' : '' }}>Brother</option>
                <option value="Sister" {{ $email->email_type == 'Sister' ? 'selected' : '' }}>Sister</option>
                <option value="Uncle" {{ $email->email_type == 'Uncle' ? 'selected' : '' }}>Uncle</option>
                <option value="Aunt" {{ $email->email_type == 'Aunt' ? 'selected' : '' }}>Aunt</option>
                <option value="Cousin" {{ $email->email_type == 'Cousin' ? 'selected' : '' }}>Cousin</option>
                <option value="Others" {{ $email->email_type == 'Others' ? 'selected' : '' }}>Others</option>
                <option value="Partner" {{ $email->email_type == 'Partner' ? 'selected' : '' }}>Partner</option>
                <option value="Not In Use" {{ $email->email_type == 'Not In Use' ? 'selected' : '' }}>Not In Use</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" 
                   name="email[{{ $index }}]" 
                   value="{{ $email->email }}" 
                   placeholder="Email Address">
        </div>
    </div>
</div>

