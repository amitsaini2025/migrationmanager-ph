{{-- Shared Occupation Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'occupation' => null, 'mode' => 'create'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Occupation" onclick="removeOccupationField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    <input type="hidden" name="occupation_id[{{ $index }}]" value="{{ ($mode === 'edit' && $occupation?->id) ? $occupation->id : '' }}">
    <input type="hidden" name="anzsco_occupation_id[{{ $index }}]" class="anzsco_occupation_id" value="{{ $occupation->anzsco_occupation_id ?? old("anzsco_occupation_id.$index") }}">
    
    <div class="content-grid">
        <div class="form-group">
            <label>Skill Assessment</label>
            <select name="{{ $mode === 'edit' ? 'skill_assessment_hidden' : 'skill_assessment' }}[{{ $index }}]" class="skill-assessment-select">
                <option value="">Select</option>
                <option value="Yes" {{ ($occupation->skill_assessment ?? old("skill_assessment.$index")) == 'Yes' ? 'selected' : '' }}>Yes</option>
                <option value="No" {{ ($occupation->skill_assessment ?? old("skill_assessment.$index")) == 'No' ? 'selected' : '' }}>No</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Nominated Occupation</label>
            <input type="text" 
                   name="nomi_occupation[{{ $index }}]" 
                   class="nomi_occupation" 
                   value="{{ $occupation->nomi_occupation ?? old("nomi_occupation.$index") }}" 
                   placeholder="Enter Occupation">
            <div class="autocomplete-items"></div>
        </div>
        
        <div class="form-group">
            <label>Occupation Code (ANZSCO)</label>
            <input type="text" 
                   name="occupation_code[{{ $index }}]" 
                   class="occupation_code" 
                   value="{{ $occupation->occupation_code ?? old("occupation_code.$index") }}" 
                   placeholder="Enter Code">
        </div>
        
        <div class="form-group">
            <label>Assessing Authority</label>
            <input type="text" 
                   name="list[{{ $index }}]" 
                   class="list" 
                   value="{{ $occupation->list ?? old("list.$index") }}" 
                   placeholder="e.g., ACS, VETASSESS">
        </div>
        
        <div class="form-group">
            <label>Occupation Lists</label>
            <div class="occupation-lists-display" id="occupation-lists-{{ $index }}">
                @if($mode === 'edit' && isset($occupation->anzsco_occupation_id) && $occupation->anzsco_occupation_id)
                    @php
                        $anzscoOccupation = \App\Models\AnzscoOccupation::find($occupation->anzsco_occupation_id);
                    @endphp
                    @if($anzscoOccupation)
                        @if($anzscoOccupation->is_on_mltssl)
                            <span class="badge badge-success mr-1">MLTSSL</span>
                        @endif
                        @if($anzscoOccupation->is_on_stsol)
                            <span class="badge badge-info mr-1">STSOL</span>
                        @endif
                        @if($anzscoOccupation->is_on_rol)
                            <span class="badge badge-warning mr-1">ROL</span>
                        @endif
                        @if($anzscoOccupation->is_on_csol)
                            <span class="badge badge-secondary mr-1">CSOL</span>
                        @endif
                    @else
                        <span class="text-muted">Select an occupation to see lists</span>
                    @endif
                @else
                    <span class="text-muted">Select an occupation to see lists</span>
                @endif
            </div>
        </div>
        
        <div class="form-group">
            <label>Assessment Date</label>
            <input type="date" 
                   name="dates[{{ $index }}]" 
                   class="dates" 
                   value="{{ $occupation && $occupation->dates ? date('Y-m-d', strtotime($occupation->dates)) : old("dates.$index") }}">
        </div>
        
        <div class="form-group">
            <label>Expiry Date</label>
            <input type="date" 
                   name="expiry_dates[{{ $index }}]" 
                   class="expiry_dates" 
                   value="{{ $occupation && $occupation->expiry_dates ? date('Y-m-d', strtotime($occupation->expiry_dates)) : old("expiry_dates.$index") }}">
        </div>
        
        <div class="form-group">
            <label>Reference No</label>
            <input type="text" 
                   name="occ_reference_no[{{ $index }}]" 
                   value="{{ $occupation->occ_reference_no ?? old("occ_reference_no.$index") }}" 
                   placeholder="Enter Reference No.">
        </div>
        
        <div class="form-group" style="align-items: center;">
            <label style="margin-bottom: 0;">Relevant Occupation</label>
            <input type="checkbox" 
                   name="{{ $mode === 'edit' ? 'relevant_occupation_hidden' : 'relevant_occupation' }}[{{ $index }}]" 
                   value="1" 
                   {{ ($occupation->relevant_occupation ?? old("relevant_occupation.$index", false)) ? 'checked' : '' }} 
                   style="margin-left: 10px;">
        </div>
    </div>
</div>

