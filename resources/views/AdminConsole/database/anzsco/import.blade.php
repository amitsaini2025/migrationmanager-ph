@extends('layouts.crm_client_detail')

@section('title', 'Import ANZSCO Data')

@section('content')
<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<div class="server-error">
				@include('../Elements/flash-message')
			</div>
			<div class="custom-error-msg">
			</div>
			<div class="row">
				<div class="col-3 col-md-3 col-lg-3">
					@include('../Elements/CRM/setting')
				</div>
				<div class="col-9 col-md-9 col-lg-9">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        @icon('fa-file-import') Import ANZSCO Occupation Data
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('adminconsole.database.anzsco.download-template') }}" class="btn btn-success btn-sm">
                            @icon('fa-download') Download Template
                        </a>
                        <a href="{{ route('adminconsole.database.anzsco.index') }}" class="btn btn-secondary btn-sm">
                            @icon('fa-arrow-left') Back to List
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5>@icon('fa-info-circle', ['class' => 'icon']) Import Instructions</h5>
                        <ul class="mb-0">
                            <li>Download the template file and fill in your occupation data</li>
                            <li>Supported formats: CSV, Excel (XLSX, XLS)</li>
                            <li>Maximum file size: 10MB</li>
                            <li>Boolean fields: Use "Yes", "Y", "1", "True", or "X" for true values</li>
                            <li>If ANZSCO code already exists, it will be updated (unless you uncheck the option below)</li>
                        </ul>
                    </div>

                    <form id="importForm" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="form-group">
                            <label for="file">Select File <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="file" name="file" 
                                       accept=".csv,.xlsx,.xls" required>
                                <label class="custom-file-label" for="file">Choose file...</label>
                            </div>
                            <small class="form-text text-muted">CSV, XLSX, or XLS file</small>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="update_existing" 
                                       name="update_existing" value="1" checked>
                                <label class="custom-control-label" for="update_existing">
                                    Update existing occupations if ANZSCO code already exists
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="importBtn">
                                @icon('fa-upload') Import Data
                            </button>
                        </div>
                    </form>

                    <!-- Progress Bar -->
                    <div id="progressContainer" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 100%">
                                Processing...
                            </div>
                        </div>
                    </div>

                    <!-- Results -->
                    <div id="resultsContainer" style="display: none;">
                        <hr>
                        <h4>Import Results</h4>
                        
                        <div id="resultsStats" class="row mb-3">
                            <div class="col-md-2">
                                <div class="info-box bg-info">
                                    <span class="info-box-icon">@icon('fa-file')</span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Rows</span>
                                        <span class="info-box-number" id="stat-total">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon">@icon('fa-plus')</span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Inserted</span>
                                        <span class="info-box-number" id="stat-inserted">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon">@icon('fa-edit')</span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Updated</span>
                                        <span class="info-box-number" id="stat-updated">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box bg-secondary">
                                    <span class="info-box-icon">@icon('fa-ban')</span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Skipped</span>
                                        <span class="info-box-number" id="stat-skipped">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="info-box bg-danger">
                                    <span class="info-box-icon">@icon('fa-exclamation-triangle')</span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Errors</span>
                                        <span class="info-box-number" id="stat-errors">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="errorsList" style="display: none;">
                            <div class="alert alert-danger">
                                <h5>@icon('fa-exclamation-triangle', ['class' => 'icon']) Errors</h5>
                                <ul id="errorsContent"></ul>
                            </div>
                        </div>

                        <div id="warningsList" style="display: none;">
                            <div class="alert alert-warning">
                                <h5>@icon('fa-exclamation-circle', ['class' => 'icon']) Warnings</h5>
                                <ul id="warningsContent"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Structure Info -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        @icon('fa-table') Template Structure
                    </h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Column Name</th>
                                <th>Description</th>
                                <th>Required</th>
                                <th>Format/Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>anzsco_code</code></td>
                                <td>6-digit ANZSCO code</td>
                                <td><span class="badge badge-danger">Required</span></td>
                                <td>261313</td>
                            </tr>
                            <tr>
                                <td><code>occupation_title</code></td>
                                <td>Official occupation name</td>
                                <td><span class="badge badge-danger">Required</span></td>
                                <td>Software Engineer</td>
                            </tr>
                            <tr>
                                <td><code>skill_level</code></td>
                                <td>ANZSCO skill level (1-5)</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>1</td>
                            </tr>
                            <tr>
                                <td><code>mltssl</code></td>
                                <td>On MLTSSL list?</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>Yes / No / 1 / 0</td>
                            </tr>
                            <tr>
                                <td><code>stsol</code></td>
                                <td>On STSOL list?</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>Yes / No / 1 / 0</td>
                            </tr>
                            <tr>
                                <td><code>rol</code></td>
                                <td>On ROL list?</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>Yes / No / 1 / 0</td>
                            </tr>
                            <tr>
                                <td><code>csol</code></td>
                                <td>On CSOL list? (Core Skills)</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>Yes / No / 1 / 0</td>
                            </tr>
                            <tr>
                                <td><code>assessing_authority</code></td>
                                <td>Skill assessment authority</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>ACS, VETASSESS, TRA</td>
                            </tr>
                            <tr>
                                <td><code>validity_years</code></td>
                                <td>Assessment validity in years</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>3 (default)</td>
                            </tr>
                            <tr>
                                <td><code>additional_info</code></td>
                                <td>Extra notes or requirements</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>Trade qualification required</td>
                            </tr>
                            <tr>
                                <td><code>alternate_titles</code></td>
                                <td>Other names (comma-separated)</td>
                                <td><span class="badge badge-secondary">Optional</span></td>
                                <td>Developer, Programmer</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
            </div>
				</div>
			</div>
		</div>
	</section>
</div>

<link rel="stylesheet" href="{{ asset('css/anzsco-admin.css') }}">

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Update file input label
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });

    // Handle form submission
    $('#importForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('update_existing', $('#update_existing').is(':checked') ? '1' : '0');
        
        // Show progress
        $('#progressContainer').show();
        $('#resultsContainer').hide();
        $('#importBtn').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("adminconsole.database.anzsco.import") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#progressContainer').hide();
                $('#resultsContainer').show();
                $('#importBtn').prop('disabled', false);
                
                // Update stats
                $('#stat-total').text(response.stats.total);
                $('#stat-inserted').text(response.stats.inserted);
                $('#stat-updated').text(response.stats.updated);
                $('#stat-skipped').text(response.stats.skipped);
                $('#stat-errors').text(response.stats.errors);
                
                // Show errors
                if (response.errors && response.errors.length > 0) {
                    $('#errorsList').show();
                    var errorsHtml = '';
                    response.errors.forEach(function(error) {
                        errorsHtml += '<li>' + error + '</li>';
                    });
                    $('#errorsContent').html(errorsHtml);
                } else {
                    $('#errorsList').hide();
                }
                
                // Show warnings
                if (response.warnings && response.warnings.length > 0) {
                    $('#warningsList').show();
                    var warningsHtml = '';
                    response.warnings.forEach(function(warning) {
                        warningsHtml += '<li>' + warning + '</li>';
                    });
                    $('#warningsContent').html(warningsHtml);
                } else {
                    $('#warningsList').hide();
                }
                
                // Success message
                if (response.success) {
                    toastr.success('Import completed successfully!');
                } else {
                    toastr.warning('Import completed with errors');
                }
            },
            error: function(xhr) {
                $('#progressContainer').hide();
                $('#importBtn').prop('disabled', false);
                
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        toastr.error(value[0]);
                    });
                } else {
                    var message = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Error processing import';
                    toastr.error(message);
                }
            }
        });
    });
});
</script>
@endpush

