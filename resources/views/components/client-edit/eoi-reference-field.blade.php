{{-- EOI Reference Field Component --}}
@props(['index', 'eoi'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove EOI Reference" onclick="removeEoiField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="eoi_id[{{ $index }}]" value="{{ $eoi->id ?? '' }}">
    
    <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
        <div class="form-group">
            <label>EOI Number</label>
            <input type="text" 
                   name="EOI_number[{{ $index }}]" 
                   value="{{ $eoi->EOI_number ?? '' }}" 
                   placeholder="EOI Number">
        </div>
        
        <div class="form-group">
            <label>Subclass</label>
            <input type="text" 
                   name="EOI_subclass[{{ $index }}]" 
                   value="{{ $eoi->EOI_subclass ?? '' }}" 
                   placeholder="Subclass">
        </div>
        
        <div class="form-group">
            <label>Occupation</label>
            <input type="text" 
                   name="EOI_occupation[{{ $index }}]" 
                   value="{{ $eoi->EOI_occupation ?? '' }}" 
                   placeholder="Occupation">
        </div>
        
        <div class="form-group">
            <label>Point</label>
            <input type="text" 
                   name="EOI_point[{{ $index }}]" 
                   value="{{ $eoi->EOI_point ?? '' }}" 
                   placeholder="Point">
        </div>
        
        <div class="form-group">
            <label>State</label>
            <input type="text" 
                   name="EOI_state[{{ $index }}]" 
                   value="{{ $eoi->EOI_state ?? '' }}" 
                   placeholder="State">
        </div>
        
        <div class="form-group">
            <label>Submission Date</label>
            <input type="text" 
                   name="EOI_submission_date[{{ $index }}]" 
                   value="{{ $eoi && $eoi->EOI_submission_date ? date('d/m/Y', strtotime($eoi->EOI_submission_date)) : '' }}" 
                   placeholder="dd/mm/yyyy" 
                   class="date-picker">
        </div>
        
        <div class="form-group">
            <label>ROI</label>
            <input type="text" 
                   name="EOI_ROI[{{ $index }}]" 
                   value="{{ $eoi->EOI_ROI ?? '' }}" 
                   placeholder="ROI">
        </div>
    </div>
</div>
