@extends('layouts.crm_client_detail')
@section('title', 'Send Document for Signature')

@section('styles')
<style>
    .send-document-container {
        max-width: 900px;
        margin: 30px auto;
        padding: 20px;
    }
    
    .page-header {
        margin-bottom: 30px;
    }
    
    .page-header h1 {
        font-size: 24px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 10px;
    }
    
    .page-header .breadcrumb {
        background: transparent;
        padding: 0;
        margin: 0;
        font-size: 14px;
    }
    
    .form-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }
    
    .form-section {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e9ecef;
    }
    
    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .form-section-title {
        font-size: 18px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-section-title i {
        color: #667eea;
    }
    
    .form-group label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
    }
    
    .form-control {
        border-radius: 6px;
        border: 1px solid #ced4da;
        padding: 10px 15px;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .file-upload-area {
        border: 2px dashed #ced4da;
        border-radius: 10px;
        padding: 40px;
        text-align: center;
        background: #f8f9fa;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .file-upload-area:hover {
        border-color: #667eea;
        background: #f0f2ff;
    }
    
    .file-upload-area.dragging {
        border-color: #667eea;
        background: #e8ecff;
    }
    
    .document-info-card {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 20px;
        background-color: #f8f9fa;
        margin-bottom: 15px;
    }
    
    .document-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .document-info i {
        font-size: 24px;
        color: #dc3545;
    }
    
    .document-info .btn {
        white-space: nowrap;
        font-size: 14px;
    }
    
    .document-info .btn-outline-primary {
        border-color: #667eea;
        color: #667eea;
    }
    
    .document-info .btn-outline-primary:hover {
        background-color: #667eea;
        color: white;
    }
    
    .document-details h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .document-meta {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 14px;
        color: #6c757d;
    }
    
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-signature-placed {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    
    .upload-date {
        font-style: italic;
    }
    
    .file-upload-icon {
        font-size: 48px;
        color: #667eea;
        margin-bottom: 15px;
    }
    
    .file-selected {
        margin-top: 15px;
        padding: 15px;
        background: #e8ecff;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .file-selected i {
        color: #28a745;
        margin-right: 10px;
    }
    
    .btn-remove-file {
        background: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .association-search {
        position: relative;
    }
    
    .association-type-selector {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .association-type-btn {
        flex: 1;
        padding: 12px;
        border: 2px solid #e9ecef;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
    }
    
    .association-type-btn:hover {
        border-color: #667eea;
    }
    
    .association-type-btn.active {
        border-color: #667eea;
        background: #e8ecff;
        color: #667eea;
    }
    
    .suggestion-box {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ced4da;
        border-radius: 6px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        margin-top: 5px;
    }
    
    .suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    
    .suggestion-item:hover {
        background: #f8f9fa;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }
    
    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        color: white;
    }
    
    .btn-cancel {
        background: #6c757d;
        color: white;
        padding: 12px 30px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        margin-right: 15px;
    }
    
    .form-help-text {
        font-size: 13px;
        color: #6c757d;
        margin-top: 5px;
    }
    
    .priority-selector {
        display: flex;
        gap: 10px;
    }
    
    .priority-option {
        flex: 1;
        padding: 10px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .priority-option input[type="radio"] {
        display: none;
    }
    
    .priority-option:hover {
        border-color: #667eea;
    }
    
    .priority-option.selected {
        border-color: #667eea;
        background: #e8ecff;
    }
    
    .match-alert {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border-left: 4px solid #4caf50;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: none;
        animation: slideDown 0.3s ease;
    }
    
    .match-alert.show {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .match-alert-icon {
        font-size: 32px;
        color: #4caf50;
    }
    
    .match-alert-content {
        flex: 1;
    }
    
    .match-alert-title {
        font-weight: 600;
        color: #2e7d32;
        margin-bottom: 5px;
    }
    
    .match-alert-text {
        color: #558b2f;
        font-size: 14px;
    }
    
    .match-alert-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-match-accept {
        background: #4caf50;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    
    .btn-match-dismiss {
        background: #757575;
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 500;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Step-by-step interface styles */
    .step-indicator {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
        padding: 20px 0;
    }

    .step-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #e9ecef;
        margin: 0 5px;
        transition: all 0.3s ease;
    }

    .step-dot.active {
        background: #667eea;
    }

    .step-dot.completed {
        background: #28a745;
    }

    .step-container {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .step-container.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Email search results styles */
    .search-results {
        margin-top: 15px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .search-results h6 {
        margin-bottom: 10px;
        color: #495057;
        font-weight: 600;
    }

    .match-item {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .match-item:hover {
        border-color: #667eea;
        box-shadow: 0 2px 4px rgba(102, 126, 234, 0.1);
    }

    .match-item.selected {
        border-color: #667eea;
        background: #e8ecff;
    }

    .match-item:last-child {
        margin-bottom: 0;
    }

    .match-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .match-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 14px;
    }

    .match-type {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .match-type.client {
        background: #d1ecf1;
        color: #0c5460;
    }

    .match-type.lead {
        background: #fff3cd;
        color: #856404;
    }

    .match-email {
        color: #6c757d;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .match-matters {
        margin-top: 8px;
    }

    .match-matters-title {
        font-size: 12px;
        font-weight: 600;
        color: #495057;
        margin-bottom: 5px;
    }

    .matter-item {
        background: #f8f9fa;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        color: #6c757d;
        margin-bottom: 3px;
        display: inline-block;
        margin-right: 5px;
    }
    
    .matter-item.clickable {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }
    
    .matter-item.clickable:hover {
        background: #e9ecef;
        border-color: #667eea;
        color: #667eea;
    }
    
    .matter-item.selected {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .no-matches {
        margin-top: 15px;
    }

    .no-matches .alert {
        margin-bottom: 0;
    }

    /* Navigation buttons */
    .step-navigation {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }

    .step-navigation .btn {
        min-width: 120px;
    }

    .step-navigation .btn-group {
        display: flex;
        gap: 10px;
    }
</style>
@endsection

@section('content')
<div class="send-document-container">
    <div class="page-header">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('signatures.index') }}">Signature Dashboard</a></li>
                @if(isset($document) && $document)
                    <li class="breadcrumb-item"><a href="{{ route('signatures.show', $document->id) }}">Document Details</a></li>
                    <li class="breadcrumb-item active">Add Signer</li>
                @else
                    <li class="breadcrumb-item active">Send New Document</li>
                @endif
            </ol>
        </nav>
        <h1>
            @if(isset($document) && $document)
                👤 Add Signer to Document
            @else
                📤 Send Document for Signature
            @endif
        </h1>
    </div>

    @if($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the following errors:</strong>
        <ul style="margin-bottom: 0; margin-top: 10px;">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    
    <!-- Match Alert Banner -->
    <div id="matchAlert" class="match-alert">
        <div class="match-alert-icon">
            @icon('fa-user-check')
        </div>
        <div class="match-alert-content">
            <div class="match-alert-title" id="matchAlertTitle">Found matching client!</div>
            <div class="match-alert-text" id="matchAlertText"></div>
        </div>
        <div class="match-alert-actions">
            <button type="button" class="btn-match-accept" onclick="acceptMatch()">
                @icon('fa-link') Link Document
            </button>
            <button type="button" class="btn-match-dismiss" onclick="dismissMatch()">
                @icon('fa-times') Dismiss
            </button>
        </div>
    </div>

    <form action="{{ route('signatures.store') }}" method="POST" enctype="multipart/form-data" id="sendDocumentForm">
        @csrf
        
        <div class="form-card">
            <!-- Step Indicators -->
            <div class="step-indicator">
                <div class="step-dot active" id="step1"></div>
                <div class="step-dot" id="step2"></div>
            </div>

            <!-- Step 1: Email Input & Search -->
            <div id="emailStep" class="step-container active">
                <h3 class="form-section-title">
                    @icon('fa-envelope')
                    Find Signer
                </h3>
                
                <div class="form-group">
                    <label for="signer_email">Signer Email <span style="color: #dc3545;">*</span></label>
                    <input type="email" class="form-control" id="signer_email" name="signer_email" 
                           placeholder="john@example.com" required value="{{ old('signer_email') }}">
                    <small class="form-help-text">Enter the email address to find existing clients/leads</small>
                </div>
                
                <div id="emailSearchResults" class="search-results" style="display: none;">
                    <h6>Found Matches:</h6>
                    <div id="matchesList"></div>
                    <div id="matterSelectionConfirmation"></div>
                </div>
                
                <div id="noMatchesMessage" class="no-matches" style="display: none;">
                    <div class="alert alert-info">
                        @icon('fa-info-circle') No existing clients or leads found with this email address.
                    </div>
                </div>
            </div>

            <!-- Step 2: Client/Lead Selection or Manual Name Entry -->
            <div id="selectionStep" class="step-container">
                <h3 class="form-section-title">
                    @icon('fa-user-check')
                    Select Signer
                </h3>
                
                <div id="clientLeadSelection"></div>
            </div>

            <!-- Step 2: Signer Information -->
            <div id="nameStep" class="step-container">
                <h3 class="form-section-title">
                    @icon('fa-user-edit')
                    Signer Information
                </h3>
                
                <div class="form-group">
                    <label for="signer_name">Signer Name <span style="color: #dc3545;">*</span></label>
                    <input type="text" class="form-control" id="signer_name" name="signer_name" 
                           placeholder="John Doe" required value="{{ old('signer_name') }}">
                    <small class="form-help-text">Enter the full name of the signer</small>
                </div>
            </div>

            <!-- Hidden fields for email configuration -->
            <input type="hidden" name="from_email" value="{{ config('mail.noreply.address') }}">
            <input type="hidden" name="email_template" value="emails.signature.send">

            <!-- Hidden fields for document and association data -->
            @if(isset($document) && $document)
                <input type="hidden" name="document_id" value="{{ $document->id }}">
            @endif
            
            <!-- Hidden fields for association data -->
            <input type="hidden" id="association_type" name="association_type" value="">
            <input type="hidden" id="association_id" name="association_id" value="">
            <input type="hidden" id="client_matter_id" name="client_matter_id" value="">

            <!-- Step Navigation -->
            <div class="step-navigation">
                <div>
                    <a href="{{ isset($document) && $document ? route('signatures.show', $document->id) : route('signatures.index') }}" 
                       class="btn btn-secondary">Cancel</a>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" id="backBtn" onclick="goBackStep()" style="display: none;">
                        @icon('fa-arrow-left') Back
                    </button>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="goNextStep()" style="display: none;">
                        Next @icon('fa-arrow-right')
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">
                        @icon('fa-user-plus') Add Signer
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Email Preview Modal -->
<div class="modal fade" id="emailPreviewModal" tabindex="-1" role="dialog" aria-labelledby="emailPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document" style="max-width: 800px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailPreviewModalLabel">
                    @icon('fa-eye') Email Preview
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="emailPreviewContent" style="padding: 0; max-height: 70vh; overflow-y: auto;">
                <div style="text-align: center; padding: 40px;">
                    @icon('fa-spinner', ['spin' => true, 'class' => 'fa-2x'])
                    <p style="margin-top: 15px;">Loading preview...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="$('#emailPreviewModal').modal('hide')">
                    @icon('fa-check') Looks Good
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ===== STEP-BY-STEP FUNCTIONALITY =====
let currentStep = 1;
let emailMatches = [];
let selectedMatch = null;
let selectedMatter = null;
let selectedClient = null;
let emailCheckTimeout = null;

// Initialize step system
document.addEventListener('DOMContentLoaded', function() {
    showStep(1);
    
    // Email field change detection with debounce
    const emailField = document.getElementById('signer_email');
    if (emailField) {
        emailField.addEventListener('input', function() {
            clearTimeout(emailCheckTimeout);
            const email = this.value.trim();
            
            // Hide previous results
            document.getElementById('emailSearchResults').style.display = 'none';
            document.getElementById('noMatchesMessage').style.display = 'none';
            
            // Only check if it's a valid-looking email
            if (email && email.includes('@') && email.includes('.')) {
                emailCheckTimeout = setTimeout(() => {
                    searchEmailMatches(email);
                }, 800); // Wait 800ms after user stops typing
            }
        });
    }
});

// Search for email matches via API
function searchEmailMatches(email) {
    fetch('{{ route('signatures.suggest-association') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.matches && data.matches.length > 0) {
            // Matches found
            emailMatches = data.matches;
            
            if (data.matches.length === 1) {
                // Auto-select single match and go to step 2
                selectedMatch = data.matches[0];
                selectedClient = data.matches[0];
                document.getElementById('signer_name').value = selectedMatch.name;
                currentStep = 2;
                showStep(2);
            } else {
                // Multiple matches, show selection
                displayEmailMatches();
            }
        } else {
            // No matches found
            emailMatches = [];
            document.getElementById('noMatchesMessage').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error checking email match:', error);
        emailMatches = [];
        document.getElementById('noMatchesMessage').style.display = 'block';
    });
}

// Display email matches
function displayEmailMatches() {
    const resultsDiv = document.getElementById('emailSearchResults');
    const matchesList = document.getElementById('matchesList');
    
    matchesList.innerHTML = '';
    
    emailMatches.forEach((match, index) => {
        const matchDiv = document.createElement('div');
        matchDiv.className = 'match-item';
        matchDiv.onclick = () => selectMatch(match, index);
        
        let mattersHtml = '';
        if (match.has_matters && match.matters.length > 0) {
            mattersHtml = `
                <div class="match-matters">
                    <div class="match-matters-title">Matters (click to select):</div>
                    ${match.matters.map(matter => `<span class="matter-item clickable" onclick="selectMatter('${match.id}', '${matter.id}', '${matter.label}')">${matter.label}</span>`).join('')}
                </div>
            `;
        }
        
        matchDiv.innerHTML = `
            <div class="match-header">
                <div class="match-name">${match.name}</div>
                <span class="match-type ${match.type}">${match.type}</span>
            </div>
            <div class="match-email">${match.email}</div>
            ${mattersHtml}
        `;
        
        matchesList.appendChild(matchDiv);
    });
    
    resultsDiv.style.display = 'block';
}

// Select a match
function selectMatch(match, index) {
    selectedMatch = match;
    selectedClient = match; // Store the selected client/lead
    
    // Update visual selection
    document.querySelectorAll('.match-item').forEach((item, i) => {
        item.classList.toggle('selected', i === index);
    });
    
    // Auto-fill name field
    document.getElementById('signer_name').value = match.name;
    
    // Auto-advance to step 2 when a match is selected
    if (currentStep === 1) {
        currentStep = 2;
        showStep(2);
    }
}

// Select a matter
function selectMatter(clientId, matterId, matterLabel) {
    selectedMatter = {
        client_id: clientId,
        matter_id: matterId,
        label: matterLabel
    };
    
    // Update visual selection
    document.querySelectorAll('.matter-item.clickable').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Find and highlight the selected matter
    const selectedMatterElement = document.querySelector(`[onclick="selectMatter('${clientId}', '${matterId}', '${matterLabel}')"]`);
    if (selectedMatterElement) {
        selectedMatterElement.classList.add('selected');
    }
    
    // Show a confirmation message
    const confirmationDiv = document.getElementById('matterSelectionConfirmation');
    if (confirmationDiv) {
        confirmationDiv.innerHTML = `
            <div class="alert alert-success" style="margin-top: 10px;">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check-circle') : '<i class="fas fa-check-circle"></i>'} Selected matter: <strong>${matterLabel}</strong>
            </div>
        `;
    }
}

// ===== STEP NAVIGATION =====
function goNextStep() {
    if (currentStep === 1) {
        // From email step
        if (emailMatches.length > 1 && !selectedMatch) {
            // Multiple matches, need to show selection step
            currentStep = 2;
            showStep(2);
        } else if (emailMatches.length === 1 && !selectedMatch) {
            // Single match, auto-select it
            selectedMatch = emailMatches[0];
            document.getElementById('signer_name').value = selectedMatch.name;
            currentStep = 2; // Go to final step
            showStep(2);
        } else if (emailMatches.length > 0 && selectedMatch) {
            // Match already selected, go to final step
            currentStep = 2;
            showStep(2);
        } else if (emailMatches.length === 0) {
            // No matches, go to name input step
            currentStep = 2;
            showStep(2);
        }
    }
}

function goBackStep() {
    if (currentStep === 2) {
        currentStep = 1;
        showStep(1);
    }
}

function showStep(step) {
    // Hide all steps
    document.querySelectorAll('.step-container').forEach(stepEl => stepEl.style.display = 'none');
    
    // Show current step
    if (step === 1) {
        document.getElementById('emailStep').style.display = 'block';
    } else if (step === 2) {
        // Determine if this is selection step or name step
        if (emailMatches.length > 1 && !selectedMatch) {
            // Show selection step
            document.getElementById('selectionStep').style.display = 'block';
            populateSelectionStep();
        } else {
            // Show name input step
            document.getElementById('nameStep').style.display = 'block';
        }
    }
    
    // Update step indicators
    document.querySelectorAll('.step-dot').forEach((dot, index) => {
        dot.classList.remove('active', 'completed');
        if (index < step - 1) {
            dot.classList.add('completed');
        } else if (index === step - 1) {
            dot.classList.add('active');
        }
    });
    
    // Update buttons
    document.getElementById('backBtn').style.display = step > 1 ? 'inline-block' : 'none';
    document.getElementById('nextBtn').style.display = step < 2 ? 'inline-block' : 'none';
    document.getElementById('submitBtn').style.display = step === 2 ? 'inline-block' : 'none';
}

// Populate the selection step with matches
function populateSelectionStep() {
    const selectionDiv = document.getElementById('clientLeadSelection');
    selectionDiv.innerHTML = '';
    
    emailMatches.forEach((match, index) => {
        const matchDiv = document.createElement('div');
        matchDiv.className = 'match-item';
        matchDiv.onclick = () => selectMatch(match, index);
        
        let mattersHtml = '';
        if (match.has_matters && match.matters.length > 0) {
            mattersHtml = `
                <div class="match-matters">
                    <div class="match-matters-title">Matters (click to select):</div>
                    ${match.matters.map(matter => `<span class="matter-item clickable" onclick="selectMatter('${match.id}', '${matter.id}', '${matter.label}')">${matter.label}</span>`).join('')}
                </div>
            `;
        }
        
        matchDiv.innerHTML = `
            <div class="match-header">
                <div class="match-name">${match.name}</div>
                <span class="match-type ${match.type}">${match.type}</span>
            </div>
            <div class="match-email">${match.email}</div>
            ${mattersHtml}
        `;
        
        selectionDiv.appendChild(matchDiv);
    });
}

// Form submission handling
document.getElementById('sendDocumentForm').addEventListener('submit', function(e) {
    // Set association data if a match was selected
    if (selectedMatch) {
        document.getElementById('association_type').value = selectedMatch.type;
        document.getElementById('association_id').value = selectedMatch.id;
        
        if (selectedMatter) {
            document.getElementById('client_matter_id').value = selectedMatter.matter_id;
        }
    }
    
    // Validate required fields
    const signerName = document.getElementById('signer_name').value.trim();
    const signerEmail = document.getElementById('signer_email').value.trim();
    
    if (!signerName || !signerEmail) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
});

// ===== EMAIL PREVIEW FEATURE =====
function previewEmail() {
    const signerName = document.getElementById('signer_name').value;
    const emailTemplate = document.getElementById('email_template').value;
    const emailMessage = document.getElementById('email_message').value;
    
    if (!signerName) {
        alert('Please enter a signer name first');
        return;
    }
    
    // Show modal
    $('#emailPreviewModal').modal('show');
    
    // Reset content
    document.getElementById('emailPreviewContent').innerHTML = `
        <div style="text-align: center; padding: 40px;">
            ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-spinner fa-spin fa-2x') : '<i class="fas fa-spinner fa-spin fa-2x"></i>'}
            <p style="margin-top: 15px;">Loading preview...</p>
        </div>
    `;
    
    // Fetch preview
    fetch('{{ route("signatures.preview-email") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            template: emailTemplate,
            signer_name: signerName,
            document_title: '{{ isset($document) ? ($document->file_name ?? "Your Document") : "Your Document" }}',
            message: emailMessage
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('emailPreviewContent').innerHTML = data.html;
        } else {
            document.getElementById('emailPreviewContent').innerHTML = `
                <div style="text-align: center; padding: 40px; color: #dc3545;">
                    ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-exclamation-triangle fa-2x') : '<i class="fas fa-exclamation-triangle fa-2x"></i>'}
                    <p style="margin-top: 15px;">Failed to load preview</p>
                    <small>${data.error || 'Unknown error'}</small>
                </div>
            `;
        }
    })
    .catch(error => {
        document.getElementById('emailPreviewContent').innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                ${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-exclamation-triangle fa-2x') : '<i class="fas fa-exclamation-triangle fa-2x"></i>'}
                <p style="margin-top: 15px;">Failed to load preview</p>
                <small>${error.message}</small>
            </div>
        `;
    });
}
</script>
@endsection

