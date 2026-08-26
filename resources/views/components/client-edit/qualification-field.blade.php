{{-- Qualification Field Component --}}
@props(['index', 'qualification', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Qualification" onclick="removeQualificationField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="qualification_id[{{ $index }}]" value="{{ $qualification->id ?? '' }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Qualification Level</label>
            <select name="level[{{ $index }}]">
                <option value="">Select Level</option>
                <option value="Certificate I" {{ ($qualification->level ?? '') == 'Certificate I' ? 'selected' : '' }}>Certificate I</option>
                <option value="Certificate II" {{ ($qualification->level ?? '') == 'Certificate II' ? 'selected' : '' }}>Certificate II</option>
                <option value="Certificate III" {{ ($qualification->level ?? '') == 'Certificate III' ? 'selected' : '' }}>Certificate III</option>
                <option value="Certificate IV" {{ ($qualification->level ?? '') == 'Certificate IV' ? 'selected' : '' }}>Certificate IV</option>
                <option value="Diploma" {{ ($qualification->level ?? '') == 'Diploma' ? 'selected' : '' }}>Diploma</option>
                <option value="Advanced Diploma" {{ ($qualification->level ?? '') == 'Advanced Diploma' ? 'selected' : '' }}>Advanced Diploma</option>
                <option value="Bachelor Degree" {{ ($qualification->level ?? '') == 'Bachelor Degree' ? 'selected' : '' }}>Bachelor Degree</option>
                <option value="Bachelor Honours Degree" {{ ($qualification->level ?? '') == 'Bachelor Honours Degree' ? 'selected' : '' }}>Bachelor Honours Degree</option>
                <option value="Graduate Certificate" {{ ($qualification->level ?? '') == 'Graduate Certificate' ? 'selected' : '' }}>Graduate Certificate</option>
                <option value="Graduate Diploma" {{ ($qualification->level ?? '') == 'Graduate Diploma' ? 'selected' : '' }}>Graduate Diploma</option>
                <option value="Masters Degree" {{ ($qualification->level ?? '') == 'Masters Degree' ? 'selected' : '' }}>Masters Degree</option>
                <option value="Doctoral Degree" {{ ($qualification->level ?? '') == 'Doctoral Degree' ? 'selected' : '' }}>Doctoral Degree</option>
                <option value="Other" {{ ($qualification->level ?? '') == 'Other' ? 'selected' : '' }}>Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Qualification Name</label>
            <input type="text" 
                   name="name[{{ $index }}]" 
                   value="{{ $qualification->name ?? '' }}" 
                   placeholder="e.g., Bachelor of Engineering">
        </div>
        
        <div class="form-group">
            <label>Institution/College Name</label>
            <input type="text" 
                   name="qual_college_name[{{ $index }}]" 
                   value="{{ $qualification->qual_college_name ?? '' }}" 
                   placeholder="Institution Name">
        </div>
        
        <div class="form-group">
            <label>Campus/Address</label>
            <input type="text" 
                   name="qual_campus[{{ $index }}]" 
                   value="{{ $qualification->qual_campus ?? '' }}" 
                   placeholder="Campus/Address">
        </div>
        
        <div class="form-group">
            <label>Country</label>
            <select name="qual_country[{{ $index }}]" class="qualification-country-field" data-country-select="priority" data-selected="{{ $qualification->country ?? '' }}">
                <option value="">Select Country</option>
                @if(($qualification->country ?? '') !== '')
                    <option value="{{ $qualification->country }}" selected>{{ $qualification->country }}</option>
                @endif
            </select>
        </div>
        
                    <div class="form-group">
                        <label>Status</label>
                        <input type="text" 
                               name="qual_state[{{ $index }}]" 
                               value="{{ $qualification->qual_state ?? '' }}" 
                               placeholder="enrolled, completed, or withdrew">
                    </div>
        
        <div class="form-group">
            <label>Start Date</label>
            <input type="text" 
                   name="start_date[{{ $index }}]" 
                   value="{{ $qualification->start_date ? date('d/m/Y', strtotime($qualification->start_date)) : '' }}" 
                   placeholder="dd/mm/yyyy"
                   class="date-picker">
        </div>
        
        <div class="form-group">
            <label>Finish Date</label>
            <input type="text" 
                   name="finish_date[{{ $index }}]" 
                   value="{{ $qualification->finish_date ? date('d/m/Y', strtotime($qualification->finish_date)) : '' }}" 
                   placeholder="dd/mm/yyyy"
                   class="date-picker">
        </div>
        
        <div class="form-group" style="grid-column: span 2;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" 
                       name="relevant_qualification[{{ $index }}]" 
                       value="1"
                       {{ ($qualification->relevant_qualification ?? 0) == 1 ? 'checked' : '' }}
                       style="width: auto; margin: 0;">
                <span>Relevant Qualification for Migration</span>
            </label>
        </div>
    </div>
</div>
