{{-- Shared Qualification Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'qualification' => null, 'mode' => 'create', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Qualification" onclick="removeQualificationField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    <input type="hidden" name="qualification_id[{{ $index }}]" value="{{ ($mode === 'edit' && $qualification?->id) ? $qualification->id : '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Qualification Level</label>
            <select name="level[{{ $index }}]">
                <option value="">Select Level</option>
                <option value="Certificate I" {{ ($qualification->level ?? old("level.$index")) == 'Certificate I' ? 'selected' : '' }}>Certificate I</option>
                <option value="Certificate II" {{ ($qualification->level ?? old("level.$index")) == 'Certificate II' ? 'selected' : '' }}>Certificate II</option>
                <option value="Certificate III" {{ ($qualification->level ?? old("level.$index")) == 'Certificate III' ? 'selected' : '' }}>Certificate III</option>
                <option value="Certificate IV" {{ ($qualification->level ?? old("level.$index")) == 'Certificate IV' ? 'selected' : '' }}>Certificate IV</option>
                <option value="Diploma" {{ ($qualification->level ?? old("level.$index")) == 'Diploma' ? 'selected' : '' }}>Diploma</option>
                <option value="Advanced Diploma" {{ ($qualification->level ?? old("level.$index")) == 'Advanced Diploma' ? 'selected' : '' }}>Advanced Diploma</option>
                <option value="Bachelor Degree" {{ ($qualification->level ?? old("level.$index")) == 'Bachelor Degree' ? 'selected' : '' }}>Bachelor Degree</option>
                <option value="Bachelor Honours Degree" {{ ($qualification->level ?? old("level.$index")) == 'Bachelor Honours Degree' ? 'selected' : '' }}>Bachelor Honours Degree</option>
                <option value="Graduate Certificate" {{ ($qualification->level ?? old("level.$index")) == 'Graduate Certificate' ? 'selected' : '' }}>Graduate Certificate</option>
                <option value="Graduate Diploma" {{ ($qualification->level ?? old("level.$index")) == 'Graduate Diploma' ? 'selected' : '' }}>Graduate Diploma</option>
                <option value="Masters Degree" {{ ($qualification->level ?? old("level.$index")) == 'Masters Degree' ? 'selected' : '' }}>Masters Degree</option>
                <option value="Doctoral Degree" {{ ($qualification->level ?? old("level.$index")) == 'Doctoral Degree' ? 'selected' : '' }}>Doctoral Degree</option>
                <option value="Other" {{ ($qualification->level ?? old("level.$index")) == 'Other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Qualification Name</label>
            <input type="text" 
                   name="name[{{ $index }}]" 
                   value="{{ $qualification->name ?? old("name.$index") }}" 
                   placeholder="e.g., Bachelor of Engineering">
        </div>
        
        <div class="form-group">
            <label>Institution/College Name</label>
            <input type="text" 
                   name="qual_college_name[{{ $index }}]" 
                   value="{{ $qualification->qual_college_name ?? old("qual_college_name.$index") }}" 
                   placeholder="Institution Name">
        </div>
        
        <div class="form-group">
            <label>Campus/Address</label>
            <input type="text" 
                   name="qual_campus[{{ $index }}]" 
                   value="{{ $qualification->qual_campus ?? old("qual_campus.$index") }}" 
                   placeholder="Campus/Address">
        </div>
        
        <div class="form-group">
            <label>Country</label>
            <select name="qual_country[{{ $index }}]" class="qualification-country-field">
                <option value="">Select Country</option>
                <option value="India" {{ ($qualification->country ?? old("qual_country.$index")) == 'India' ? 'selected' : '' }}>India</option>
                <option value="Australia" {{ ($qualification->country ?? old("qual_country.$index")) == 'Australia' ? 'selected' : '' }}>Australia</option>
                @foreach($countries as $country)
                    @if(is_object($country) && $country->name != 'India' && $country->name != 'Australia')
                        <option value="{{ $country->name }}" {{ ($qualification->country ?? old("qual_country.$index")) == $country->name ? 'selected' : '' }}>{{ $country->name }}</option>
                    @elseif(is_string($country) && $country != 'India' && $country != 'Australia')
                        <option value="{{ $country }}" {{ ($qualification->country ?? old("qual_country.$index")) == $country ? 'selected' : '' }}>{{ $country }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Status</label>
            <input type="text" 
                   name="qual_state[{{ $index }}]" 
                   value="{{ $qualification->qual_state ?? old("qual_state.$index") }}" 
                   placeholder="enrolled, completed, or withdrew">
        </div>
        
        <div class="form-group">
            <label>Start Date</label>
            <input type="text" 
                   name="start_date[{{ $index }}]" 
                   value="{{ $qualification && $qualification->start_date ? date('d/m/Y', strtotime($qualification->start_date)) : old("start_date.$index") }}" 
                   placeholder="dd/mm/yyyy"
                   class="date-picker">
        </div>
        
        <div class="form-group">
            <label>Finish Date</label>
            <input type="text" 
                   name="finish_date[{{ $index }}]" 
                   value="{{ $qualification && $qualification->finish_date ? date('d/m/Y', strtotime($qualification->finish_date)) : old("finish_date.$index") }}" 
                   placeholder="dd/mm/yyyy"
                   class="date-picker">
        </div>
        
        <div class="form-group" style="grid-column: span 2;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" 
                       name="relevant_qualification[{{ $index }}]" 
                       value="1"
                       {{ ($qualification->relevant_qualification ?? old("relevant_qualification.$index", 0)) == 1 ? 'checked' : '' }}
                       style="width: auto; margin: 0;">
                <span>Relevant Qualification for Migration</span>
            </label>
        </div>
    </div>
</div>

