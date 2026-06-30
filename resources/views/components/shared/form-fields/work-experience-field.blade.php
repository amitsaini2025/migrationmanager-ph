{{-- Shared Work Experience Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'experience' => null, 'mode' => 'create', 'countries' => []])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Experience" onclick="removeExperienceField(this)">
        @icon('circle-x', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    <input type="hidden" name="experience_id[{{ $index }}]" value="{{ ($mode === 'edit' && $experience?->id) ? $experience->id : '' }}">
    
    <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
        <div class="form-group">
            <label>Job Title</label>
            <input type="text" 
                   name="job_title[{{ $index }}]" 
                   value="{{ $experience->job_title ?? old("job_title.$index") }}" 
                   placeholder="e.g., Software Engineer">
        </div>
        
        <div class="form-group">
            <label>ANZSCO Code</label>
            <input type="text" 
                   name="job_code[{{ $index }}]" 
                   value="{{ $experience->job_code ?? old("job_code.$index") }}" 
                   placeholder="e.g., 261313">
        </div>
        
        <div class="form-group">
            <label>Employer Name</label>
            <input type="text" 
                   name="job_emp_name[{{ $index }}]" 
                   value="{{ $experience->job_emp_name ?? old("job_emp_name.$index") }}" 
                   placeholder="Enter employer name">
        </div>
        
        <div class="form-group">
            <label>Country</label>
            <select name="{{ $mode === 'edit' ? 'job_country_hidden' : 'job_country' }}[{{ $index }}]">
                <option value="">Select Country</option>
                @foreach($countries as $country)
                    @php
                        $countryName = is_object($country) ? $country->name : $country;
                    @endphp
                    <option value="{{ $countryName }}" {{ ($experience->job_country ?? old("job_country.$index")) == $countryName ? 'selected' : '' }}>
                        {{ $countryName }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Address</label>
            <textarea name="job_state[{{ $index }}]" 
                      rows="2" 
                      placeholder="Enter workplace address">{{ $experience->job_state ?? old("job_state.$index") }}</textarea>
        </div>
        
        <div class="form-group">
            <label>Job Type</label>
            <select name="job_type[{{ $index }}]">
                <option value="">Select job type</option>
                <option value="Full-time" {{ ($experience->job_type ?? old("job_type.$index")) == 'Full-time' ? 'selected' : '' }}>Full-time</option>
                <option value="Part-time" {{ ($experience->job_type ?? old("job_type.$index")) == 'Part-time' ? 'selected' : '' }}>Part-time</option>
                <option value="Contract" {{ ($experience->job_type ?? old("job_type.$index")) == 'Contract' ? 'selected' : '' }}>Contract</option>
                <option value="Casual" {{ ($experience->job_type ?? old("job_type.$index")) == 'Casual' ? 'selected' : '' }}>Casual</option>
                <option value="Internship" {{ ($experience->job_type ?? old("job_type.$index")) == 'Internship' ? 'selected' : '' }}>Internship</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Start Date</label>
            <input type="text" 
                   name="job_start_date[{{ $index }}]" 
                   value="{{ $experience && $experience->job_start_date ? date('d/m/Y', strtotime($experience->job_start_date)) : old("job_start_date.$index") }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Finish Date</label>
            <input type="text" 
                   name="job_finish_date[{{ $index }}]" 
                   value="{{ $experience && $experience->job_finish_date ? date('d/m/Y', strtotime($experience->job_finish_date)) : old("job_finish_date.$index") }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group" style="align-items: center;">
            <label>Relevant?</label>
            <div class="toggle-switch">
                <input type="checkbox" 
                       name="{{ $mode === 'edit' ? 'relevant_experience_hidden' : 'relevant_experience' }}[{{ $index }}]" 
                       id="relevant_{{ $index }}" 
                       value="1" 
                       {{ ($experience->relevant_experience ?? old("relevant_experience.$index", 0)) ? 'checked' : '' }}>
                <label for="relevant_{{ $index }}" class="toggle-label"></label>
            </div>
        </div>
    </div>
</div>

