{{-- Work Experience Field Component --}}
@props(['index', 'experience', 'countries' => [], 'isCurrent' => false])

<div class="repeatable-section experience-entry-wrapper">
    <div class="experience-entry-header">
        @if($isCurrent)
        <div class="experience-current-toggle experience-current-readonly">
            <span class="experience-current-label">Current Experience?</span>
            <label class="switch switch-readonly" title="Current experience (ongoing / no finish date, else latest finish)">
                <input type="checkbox" checked disabled>
                <span class="slider round"></span>
            </label>
        </div>
        @endif
        <button type="button" class="remove-item-btn" title="Remove Experience" onclick="removeExperienceField(this)">
            @icon('circle-x', ['class' => 'icon-sm'])
        </button>
    </div>
    
    <input type="hidden" name="experience_id[{{ $index }}]" value="{{ $experience->id ?? '' }}">
    
    <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
        <div class="form-group">
            <label>Job Title</label>
            <input type="text" 
                   name="job_title[{{ $index }}]" 
                   value="{{ $experience->job_title ?? '' }}" 
                   placeholder="e.g., Software Engineer">
        </div>
        
        <div class="form-group">
            <label>ANZSCO Code</label>
            <input type="text" 
                   name="job_code[{{ $index }}]" 
                   value="{{ $experience->job_code ?? '' }}" 
                   placeholder="e.g., 261313">
        </div>
        
        <div class="form-group">
            <label>Employer Name</label>
            <input type="text" 
                   name="job_emp_name[{{ $index }}]" 
                   value="{{ $experience->job_emp_name ?? '' }}" 
                   placeholder="Enter employer name">
        </div>
        
        <div class="form-group">
            <label>Country</label>
            <select name="job_country_hidden[{{ $index }}]">
                <option value="">Select Country</option>
                @foreach($countries as $country)
                    <option value="{{ $country }}" {{ ($experience->job_country ?? '') == $country ? 'selected' : '' }}>
                        {{ $country }}
                    </option>
                @endforeach
            </select>
        </div>
        
        <div class="form-group">
            <label>Address</label>
            <textarea name="job_state[{{ $index }}]" 
                      rows="2" 
                      placeholder="Enter workplace address">{{ $experience->job_state ?? '' }}</textarea>
        </div>
        
        <div class="form-group">
            <label>Job Type</label>
            <select name="job_type[{{ $index }}]">
                <option value="">Select job type</option>
                <option value="Full-time" {{ ($experience->job_type ?? '') == 'Full-time' ? 'selected' : '' }}>Full-time</option>
                <option value="Part-time" {{ ($experience->job_type ?? '') == 'Part-time' ? 'selected' : '' }}>Part-time</option>
                <option value="Contract" {{ ($experience->job_type ?? '') == 'Contract' ? 'selected' : '' }}>Contract</option>
                <option value="Casual" {{ ($experience->job_type ?? '') == 'Casual' ? 'selected' : '' }}>Casual</option>
                <option value="Internship" {{ ($experience->job_type ?? '') == 'Internship' ? 'selected' : '' }}>Internship</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Start Date</label>
            <input type="text" 
                   name="job_start_date[{{ $index }}]" 
                   value="{{ $experience && $experience->job_start_date ? date('d/m/Y', strtotime($experience->job_start_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group">
            <label>Finish Date</label>
            <input type="text" 
                   name="job_finish_date[{{ $index }}]" 
                   value="{{ $experience && $experience->job_finish_date ? date('d/m/Y', strtotime($experience->job_finish_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker date-picker-past-only">
        </div>
        
        <div class="form-group" style="align-items: center;">
            <label>Relevant?</label>
            <div class="toggle-switch">
                <input type="checkbox" 
                       name="relevant_experience_hidden[{{ $index }}]" 
                       id="relevant_{{ $index }}" 
                       value="1" 
                       {{ ($experience->relevant_experience ?? 0) ? 'checked' : '' }}>
                <label for="relevant_{{ $index }}" class="toggle-label"></label>
            </div>
        </div>
    </div>
</div>
