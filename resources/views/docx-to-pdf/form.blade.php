@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>@icon('fa-file-pdf') DOCX to PDF Converter</h4>
                </div>
                <div class="card-body">
                    @if($isHealthy)
                        <div class="alert alert-success">
                            @icon('fa-check-circle') <strong>Service Status:</strong> Conversion service is ready and available
                        </div>
                    @else
                        <div class="alert alert-danger">
                            @icon('fa-exclamation-triangle') <strong>Service Status:</strong> Conversion service is not available. Please check the Python service.
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <h6>@icon('fa-exclamation-triangle') Errors:</h6>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('docx-to-pdf.convert') }}" method="POST" enctype="multipart/form-data" id="conversionForm">
                        @csrf
                        <div class="form-group mb-3">
                            <label for="docx_file" class="form-label">
                                @icon('fa-file-word') Select DOCX or DOC File:
                            </label>
                            <input type="file" 
                                   class="form-control @error('docx_file') is-invalid @enderror" 
                                   id="docx_file" 
                                   name="docx_file" 
                                   accept=".docx,.doc" 
                                   required>
                            <div class="form-text">
                                @icon('fa-info-circle')
                                Maximum file size: 50MB. Supported formats: .docx, .doc
                            </div>
                            @error('docx_file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" 
                                    class="btn btn-primary btn-lg" 
                                    id="convertBtn"
                                    {{ !$isHealthy ? 'disabled' : '' }}>
                                @icon('fa-file-pdf')
                                <span id="btnText">Convert to PDF</span>
                                <span id="loadingText" style="display: none;">
                                    @icon('fa-spinner', ['spin' => true]) Converting...
                                </span>
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div class="row">
                        <div class="col-md-6">
                            <h6>@icon('fa-cogs') Service Information</h6>
                            <ul class="list-unstyled">
                                <li><strong>Service:</strong> Python LibreOffice Converter</li>
                                <li><strong>Quality:</strong> High-quality with full formatting</li>
                                <li><strong>Processing:</strong> Local server processing</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>@icon('fa-shield-alt') Security Features</h6>
                            <ul class="list-unstyled">
                                <li>@icon('fa-check', ['class' => 'text-success']) File validation</li>
                                <li>@icon('fa-check', ['class' => 'text-success']) Size limits enforced</li>
                                <li>@icon('fa-check', ['class' => 'text-success']) Temporary file cleanup</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Testing Section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6>@icon('fa-code') API Testing</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="testHealth()">
                                @icon('fa-heartbeat') Health Check
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="testConversion()">
                                @icon('fa-vial') Test Conversion
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearResults()">
                                @icon('fa-trash') Clear Results
                            </button>
                        </div>
                    </div>
                    <div id="apiResults" class="mt-3" style="display: none;">
                        <pre id="apiOutput" class="bg-light p-3 rounded"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('conversionForm');
    const convertBtn = document.getElementById('convertBtn');
    const btnText = document.getElementById('btnText');
    const loadingText = document.getElementById('loadingText');

    form.addEventListener('submit', function() {
        convertBtn.disabled = true;
        btnText.style.display = 'none';
        loadingText.style.display = 'inline';
    });
});

function testHealth() {
    fetch('{{ route("docx-to-pdf.health") }}')
        .then(response => response.json())
        .then(data => {
            showApiResult('Health Check Result:', data);
        })
        .catch(error => {
            showApiResult('Health Check Error:', { error: error.message });
        });
}

function testConversion() {
    fetch('{{ route("docx-to-pdf.test") }}')
        .then(response => response.json())
        .then(data => {
            showApiResult('Test Conversion Result:', data);
        })
        .catch(error => {
            showApiResult('Test Conversion Error:', { error: error.message });
        });
}

function showApiResult(title, data) {
    const resultsDiv = document.getElementById('apiResults');
    const output = document.getElementById('apiOutput');
    
    output.textContent = title + '\n' + JSON.stringify(data, null, 2);
    resultsDiv.style.display = 'block';
}

function clearResults() {
    document.getElementById('apiResults').style.display = 'none';
}
</script>

<style>
.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1.1rem;
}

#apiOutput {
    max-height: 300px;
    overflow-y: auto;
    font-size: 0.875rem;
    border: 1px solid #dee2e6;
}

.alert {
    border-radius: 0.375rem;
}

.form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>
@endsection
