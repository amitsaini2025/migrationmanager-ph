{{-- Shared Test Score Field Component - Works for both Create and Edit modes --}}
@props(['index' => 0, 'testScore' => null, 'mode' => 'create'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Test" onclick="removeTestScoreField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    {{-- Only include ID in edit mode --}}
    <input type="hidden" name="test_score_id[{{ $index }}]" value="{{ ($mode === 'edit' && $testScore?->id) ? $testScore->id : '' }}">
    
    <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px;">
        <div class="form-group">
            <label>Test Type</label>
            <select name="{{ $mode === 'edit' ? 'test_type_hidden' : 'test_type' }}[{{ $index }}]" class="test-type-selector" onchange="updateTestScoreValidation(this, {{ $index }})">
                <option value="">Select Test Type</option>
                <option value="IELTS" {{ ($testScore->test_type ?? old("test_type.$index")) == 'IELTS' ? 'selected' : '' }}>IELTS</option>
                <option value="IELTS_A" {{ ($testScore->test_type ?? old("test_type.$index")) == 'IELTS_A' ? 'selected' : '' }}>IELTS Academic</option>
                <option value="PTE" {{ ($testScore->test_type ?? old("test_type.$index")) == 'PTE' ? 'selected' : '' }}>PTE</option>
                <option value="TOEFL" {{ ($testScore->test_type ?? old("test_type.$index")) == 'TOEFL' ? 'selected' : '' }}>TOEFL</option>
                <option value="CAE" {{ ($testScore->test_type ?? old("test_type.$index")) == 'CAE' ? 'selected' : '' }}>CAE</option>
                <option value="OET" {{ ($testScore->test_type ?? old("test_type.$index")) == 'OET' ? 'selected' : '' }}>OET</option>
                <option value="CELPIP" {{ ($testScore->test_type ?? old("test_type.$index")) == 'CELPIP' ? 'selected' : '' }}>CELPIP General</option>
                <option value="MET" {{ ($testScore->test_type ?? old("test_type.$index")) == 'MET' ? 'selected' : '' }}>Michigan English Test (MET)</option>
                <option value="LANGUAGECERT" {{ ($testScore->test_type ?? old("test_type.$index")) == 'LANGUAGECERT' ? 'selected' : '' }}>LANGUAGECERT Academic</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Listening</label>
            <input type="text" 
                   name="listening[{{ $index }}]" 
                   class="listening" 
                   value="{{ $testScore->listening ?? old("listening.$index") }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Reading</label>
            <input type="text" 
                   name="reading[{{ $index }}]" 
                   class="reading" 
                   value="{{ $testScore->reading ?? old("reading.$index") }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Writing</label>
            <input type="text" 
                   name="writing[{{ $index }}]" 
                   class="writing" 
                   value="{{ $testScore->writing ?? old("writing.$index") }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Speaking</label>
            <input type="text" 
                   name="speaking[{{ $index }}]" 
                   class="speaking" 
                   value="{{ $testScore->speaking ?? old("speaking.$index") }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Overall</label>
            <input type="text" 
                   name="overall_score[{{ $index }}]" 
                   class="overall_score" 
                   value="{{ $testScore->overall_score ?? old("overall_score.$index") }}" 
                   placeholder="Overall" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Test Date</label>
            <input type="text" 
                   name="test_date[{{ $index }}]" 
                   class="test_date date-picker" 
                   value="{{ $testScore && $testScore->test_date ? date('d/m/Y', strtotime($testScore->test_date)) : old("test_date.$index") }}" 
                   placeholder="dd/mm/yyyy">
        </div>
        
        <div class="form-group">
            <label>Reference No</label>
            <input type="text" 
                   name="test_reference_no[{{ $index }}]" 
                   value="{{ $testScore->test_reference_no ?? old("test_reference_no.$index") }}" 
                   placeholder="Reference No.">
        </div>
        
        <div class="form-group" style="align-items: center;">
            <label style="margin-bottom: 0;">Relevant Test</label>
            <input type="checkbox" 
                   name="{{ $mode === 'edit' ? 'relevant_test_hidden' : 'relevant_test' }}[{{ $index }}]" 
                   value="1" 
                   {{ ($testScore->relevant_test ?? old("relevant_test.$index", false)) ? 'checked' : '' }} 
                   style="margin-left: 10px;">
        </div>
    </div>
</div>

