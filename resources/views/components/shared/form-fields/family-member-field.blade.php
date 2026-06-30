{{-- Shared Family Member Field Component (Partners & Children) - Works for both Create and Edit modes --}}
@props(['index' => 0, 'member' => null, 'mode' => 'create', 'type' => 'partner', 'relationshipOptions' => []])

@php
    $fieldPrefix = $type === 'partner' ? 'partner' : 'children';
    $relationshipIdField = $type === 'partner' ? 'relationship_id' : 'children_relationship_id';
    $memberIdField = $type === 'partner' ? 'partner_id' : 'children_id';
@endphp

<div class="repeatable-section">
    <button type="button" 
            class="remove-item-btn" 
            title="Remove {{ ucfirst($type) }}" 
            onclick="removePartnerRow(this, '{{ $type }}', {{ $member->id ?? 0 }})">
        @icon('circle-x', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="{{ $memberIdField }}[{{ $index }}]" class="partner-id" value="{{ $member->related_client_id ?? old("$memberIdField.$index") }}">
    
    {{-- Only include relationship ID in edit mode --}}
    @if($mode === 'edit' && $member?->id)
        <input type="hidden" name="{{ $relationshipIdField }}[{{ $index }}]" value="{{ $member->id }}">
    @endif
    
    <div class="content-grid">
        <div class="form-group">
            <label>Details</label>
            <input type="text" 
                   name="{{ $fieldPrefix }}_details[{{ $index }}]" 
                   class="partner-details" 
                   value="@if($member && $member->relatedClient && $member->related_client_id && $member->related_client_id != 0){{ $member->relatedClient->first_name . ' ' . $member->relatedClient->last_name . ' (' . $member->relatedClient->email . ', ' . $member->relatedClient->phone . ', ' . $member->relatedClient->client_id . ')' }}@elseif($member)
@php
    $firstName = trim($member->first_name ?? '');
    $lastName = trim($member->last_name ?? '');
    
    if (empty($firstName) && empty($lastName)) {
        $displayName = $member->details ?: '';
    } elseif (empty($firstName)) {
        $displayName = $lastName;
    } elseif (empty($lastName)) {
        $displayName = $firstName;
    } else {
        $displayName = $firstName . ' ' . $lastName;
    }
@endphp
{{ $displayName }}@else{{ old("$fieldPrefix"."_details.$index") }}@endif" 
                   placeholder="Search by Name, Email, Client ID, or Phone" 
                   readonly>
            <div class="autocomplete-items"></div>
        </div>
        
        <div class="form-group">
            <label>Relationship Type <span class="text-danger">*</span></label>
            <select name="{{ $fieldPrefix }}_relationship_type[{{ $index }}]" required>
                <option value="">Select Relationship</option>
                @foreach($relationshipOptions as $option)
                    <option value="{{ $option }}" {{ ($member->relationship_type ?? old("$fieldPrefix"."_relationship_type.$index")) == $option ? 'selected' : '' }}>
                        {{ $option }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Gender <span class="text-danger">*</span></label>
            <select name="{{ $fieldPrefix }}_gender[{{ $index }}]" required>
                <option value="">Select Gender</option>
                <option value="Male" {{ ($member->gender ?? old("$fieldPrefix"."_gender.$index")) == 'Male' ? 'selected' : '' }}>Male</option>
                <option value="Female" {{ ($member->gender ?? old("$fieldPrefix"."_gender.$index")) == 'Female' ? 'selected' : '' }}>Female</option>
                <option value="Other" {{ ($member->gender ?? old("$fieldPrefix"."_gender.$index")) == 'Other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Company Type <span class="text-danger">*</span></label>
            <select name="{{ $fieldPrefix }}_company_type[{{ $index }}]" required>
                <option value="">Select Company Type</option>
                <option value="Accompany Member" {{ ($member->company_type ?? old("$fieldPrefix"."_company_type.$index")) == 'Accompany Member' ? 'selected' : '' }}>Accompany Member</option>
                <option value="Non-Accompany Member" {{ ($member->company_type ?? old("$fieldPrefix"."_company_type.$index")) == 'Non-Accompany Member' ? 'selected' : '' }}>Non-Accompany Member</option>
            </select>
        </div>
    </div>
    
    <div class="partner-extra-fields hidden-fields">
        <div class="content-grid single-row">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="{{ $fieldPrefix }}_email[{{ $index }}]" value="{{ $member->email ?? old("$fieldPrefix"."_email.$index") }}" placeholder="Email">
            </div>
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="{{ $fieldPrefix }}_first_name[{{ $index }}]" value="{{ $member->first_name ?? old("$fieldPrefix"."_first_name.$index") }}" placeholder="First Name">
            </div>
            <div class="form-group">
                <label>Last Name <span class="text-danger">*</span></label>
                <input type="text" name="{{ $fieldPrefix }}_last_name[{{ $index }}]" value="{{ $member->last_name ?? old("$fieldPrefix"."_last_name.$index") }}" placeholder="Last Name">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="{{ $fieldPrefix }}_phone[{{ $index }}]" value="{{ $member->phone ?? old("$fieldPrefix"."_phone.$index") }}" placeholder="Phone">
            </div>
            <div class="form-group">
                <label>DOB <span class="text-danger">*</span></label>
                <input type="text" name="{{ $fieldPrefix }}_dob[{{ $index }}]" value="{{ $member && $member->dob ? \Carbon\Carbon::parse($member->dob)->format('d/m/Y') : old("$fieldPrefix"."_dob.$index") }}" placeholder="dd/mm/yyyy" class="date-picker">
            </div>
        </div>
    </div>
</div>

