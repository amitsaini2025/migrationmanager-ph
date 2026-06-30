{{-- Test Score Field Component --}}
@props(['index', 'testScore'])

<div class="repeatable-section">
    <button type="button" class="remove-item-btn" title="Remove Test" onclick="removeTestScoreField(this)">
        @icon('trash-2', ['class' => 'icon-sm'])
    </button>
    
    <input type="hidden" name="test_score_id[{{ $index }}]" value="{{ $testScore->id ?? '' }}">
    
    <div class="content-grid" style="grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px;">
        <div class="form-group">
            <label>Test Type</label>
            <select name="test_type_hidden[{{ $index }}]" class="test-type-selector" onchange="updateTestScoreValidation(this, {{ $index }})">
                <option value="">Select Test Type</option>
                <option value="IELTS" {{ ($testScore->test_type ?? '') == 'IELTS' ? 'selected' : '' }}>IELTS</option>
                <option value="IELTS_A" {{ ($testScore->test_type ?? '') == 'IELTS_A' ? 'selected' : '' }}>IELTS Academic</option>
                <option value="PTE" {{ ($testScore->test_type ?? '') == 'PTE' ? 'selected' : '' }}>PTE</option>
                <option value="TOEFL" {{ ($testScore->test_type ?? '') == 'TOEFL' ? 'selected' : '' }}>TOEFL</option>
                <option value="CAE" {{ ($testScore->test_type ?? '') == 'CAE' ? 'selected' : '' }}>CAE</option>
                <option value="OET" {{ ($testScore->test_type ?? '') == 'OET' ? 'selected' : '' }}>OET</option>
                <option value="CELPIP" {{ ($testScore->test_type ?? '') == 'CELPIP' ? 'selected' : '' }}>CELPIP General</option>
                <option value="MET" {{ ($testScore->test_type ?? '') == 'MET' ? 'selected' : '' }}>Michigan English Test (MET)</option>
                <option value="LANGUAGECERT" {{ ($testScore->test_type ?? '') == 'LANGUAGECERT' ? 'selected' : '' }}>LANGUAGECERT Academic</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Listening</label>
            <input type="text" 
                   name="listening[{{ $index }}]" 
                   class="listening" 
                   value="{{ $testScore->listening ?? '' }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Reading</label>
            <input type="text" 
                   name="reading[{{ $index }}]" 
                   class="reading" 
                   value="{{ $testScore->reading ?? '' }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Writing</label>
            <input type="text" 
                   name="writing[{{ $index }}]" 
                   class="writing" 
                   value="{{ $testScore->writing ?? '' }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Speaking</label>
            <input type="text" 
                   name="speaking[{{ $index }}]" 
                   class="speaking" 
                   value="{{ $testScore->speaking ?? '' }}" 
                   placeholder="Score" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Overall</label>
            <input type="text" 
                   name="overall_score[{{ $index }}]" 
                   class="overall_score" 
                   value="{{ $testScore->overall_score ?? '' }}" 
                   placeholder="Overall" 
                   maxlength="5">
        </div>
        
        <div class="form-group">
            <label>Test Date</label>
            <input type="text" 
                   name="test_date[{{ $index }}]" 
                   class="test_date date-picker" 
                   value="{{ $testScore && $testScore->test_date ? date('d/m/Y', strtotime($testScore->test_date)) : '' }}" 
                   placeholder="dd/mm/yyyy">
        </div>
        
        <div class="form-group">
            <label>Reference No</label>
            <input type="text" 
                   name="test_reference_no[{{ $index }}]" 
                   value="{{ $testScore->test_reference_no ?? '' }}" 
                   placeholder="Reference No.">
        </div>
        
        <div class="form-group" style="align-items: center;">
            <label style="margin-bottom: 0;">Relevant Test</label>
            <input type="checkbox" 
                   name="relevant_test_hidden[{{ $index }}]" 
                   value="1" 
                   {{ ($testScore->relevant_test ?? false) ? 'checked' : '' }} 
                   style="margin-left: 10px;">
        </div>
    </div>
</div>
