@extends('layouts.crm_client_detail')

@section('title', isset($occupation) ? 'Edit Occupation' : 'Add Occupation')

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
                        @icon('fa-briefcase') 
                        {{ isset($occupation) ? 'Edit Occupation' : 'Add New Occupation' }}
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('adminconsole.database.anzsco.index') }}" class="btn btn-secondary btn-sm">
                            @icon('fa-arrow-left') Back to List
                        </a>
                    </div>
                </div>
                
                <form id="occupationForm" method="POST" 
                      action="{{ isset($occupation) ? route('adminconsole.database.anzsco.edit', $occupation->id) : route('adminconsole.database.anzsco.store') }}">
                    @csrf
                    @if(isset($occupation))
                        @method('PUT')
                    @endif
                    
                    <div class="card-body">
                        <div class="row">
                            <!-- ANZSCO Code -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="anzsco_code">ANZSCO Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="anzsco_code" name="anzsco_code" 
                                           value="{{ old('anzsco_code', $occupation->anzsco_code ?? '') }}" 
                                           placeholder="e.g., 261313" required maxlength="10">
                                    <small class="form-text text-muted">6-digit occupation code</small>
                                </div>
                            </div>

                            <!-- Occupation Title -->
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="occupation_title">Occupation Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="occupation_title" name="occupation_title" 
                                           value="{{ old('occupation_title', $occupation->occupation_title ?? '') }}" 
                                           placeholder="e.g., Software Engineer" required maxlength="255">
                                </div>
                            </div>

                            <!-- Skill Level -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="skill_level">Skill Level</label>
                                    <select class="form-control" id="skill_level" name="skill_level">
                                        <option value="">Select</option>
                                        @for($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" 
                                                {{ old('skill_level', $occupation->skill_level ?? '') == $i ? 'selected' : '' }}>
                                                Level {{ $i }}
                                            </option>
                                        @endfor
                                    </select>
                                    <small class="form-text text-muted">1 = Highest, 5 = Lowest</small>
                                </div>
                            </div>

                            <!-- Assessing Authority -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="assessing_authority">Assessing Authority</label>
                                    <input type="text" class="form-control" id="assessing_authority" name="assessing_authority" 
                                           value="{{ old('assessing_authority', $occupation->assessing_authority ?? '') }}" 
                                           placeholder="e.g., ACS, VETASSESS, TRA" maxlength="255">
                                    <small class="form-text text-muted">Skill assessment body</small>
                                </div>
                            </div>

                            <!-- Assessment Validity -->
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="assessment_validity_years">Validity (Years)</label>
                                    <input type="number" class="form-control" id="assessment_validity_years" 
                                           name="assessment_validity_years" min="1" max="10"
                                           value="{{ old('assessment_validity_years', $occupation->assessment_validity_years ?? 3) }}">
                                    <small class="form-text text-muted">Default: 3 years</small>
                                </div>
                            </div>

                            <!-- Occupation Lists -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Occupation Lists</label>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="is_on_mltssl" 
                                                       name="is_on_mltssl" value="1"
                                                       {{ old('is_on_mltssl', $occupation->is_on_mltssl ?? false) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="is_on_mltssl">
                                                    <span class="badge badge-success">MLTSSL</span>
                                                    <small class="d-block text-muted">Medium and Long-term Strategic Skills List</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="is_on_stsol" 
                                                       name="is_on_stsol" value="1"
                                                       {{ old('is_on_stsol', $occupation->is_on_stsol ?? false) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="is_on_stsol">
                                                    <span class="badge badge-info">STSOL</span>
                                                    <small class="d-block text-muted">Short-term Skilled Occupation List</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="is_on_rol" 
                                                       name="is_on_rol" value="1"
                                                       {{ old('is_on_rol', $occupation->is_on_rol ?? false) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="is_on_rol">
                                                    <span class="badge badge-warning">ROL</span>
                                                    <small class="d-block text-muted">Regional Occupation List</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="is_on_csol" 
                                                       name="is_on_csol" value="1"
                                                       {{ old('is_on_csol', $occupation->is_on_csol ?? false) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="is_on_csol">
                                                    <span class="badge badge-secondary">CSOL</span>
                                                    <small class="d-block text-muted">Core Skills Occupation List</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Alternate Titles -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="alternate_titles">Alternate Titles</label>
                                    <input type="text" class="form-control" id="alternate_titles" name="alternate_titles" 
                                           value="{{ old('alternate_titles', $occupation->alternate_titles ?? '') }}" 
                                           placeholder="e.g., Developer, Programmer, Coder (comma-separated)">
                                    <small class="form-text text-muted">Other common names for this occupation (comma-separated)</small>
                                </div>
                            </div>

                            <!-- Additional Info -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="additional_info">Additional Information</label>
                                    <textarea class="form-control" id="additional_info" name="additional_info" 
                                              rows="3" placeholder="Any additional notes or requirements">{{ old('additional_info', $occupation->additional_info ?? '') }}</textarea>
                                </div>
                            </div>

                            <!-- Active Status -->
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="is_active" 
                                               name="is_active" value="1"
                                               {{ old('is_active', $occupation->is_active ?? true) ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="is_active">
                                            <strong>Active</strong>
                                            <small class="d-block text-muted">Only active occupations appear in autocomplete</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            @icon('fa-save') {{ isset($occupation) ? 'Update' : 'Save' }} Occupation
                        </button>
                        <a href="{{ route('adminconsole.database.anzsco.index') }}" class="btn btn-secondary">
                            @icon('fa-times') Cancel
                        </a>
                    </div>
                </form>
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
    $('#occupationForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var url = form.attr('action');
        var method = form.find('input[name="_method"]').val() || 'POST';
        
        $.ajax({
            url: url,
            type: method,
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(function() {
                        window.location.href = '{{ route("adminconsole.database.anzsco.index") }}';
                    }, 1500);
                } else {
                    toastr.error(response.message || 'Error saving occupation');
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    $.each(errors, function(key, value) {
                        toastr.error(value[0]);
                    });
                } else {
                    toastr.error('Error saving occupation');
                }
            }
        });
    });
});
</script>
@endpush

