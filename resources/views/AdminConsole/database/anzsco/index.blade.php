@extends('layouts.crm_client_detail')

@section('title', 'ANZSCO Occupations')

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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        @icon('fa-briefcase') ANZSCO Occupations Database
                    </h3>
                    <div>
                        <a href="{{ route('adminconsole.database.anzsco.import') }}" class="btn btn-info btn-sm mr-2">
                            @icon('fa-file-import') Import Data
                        </a>
                        <a href="{{ route('adminconsole.database.anzsco.create') }}" class="btn btn-primary btn-sm">
                            @icon('fa-plus') Add Occupation
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>Status</label>
                            <select id="statusFilter" class="form-control">
                                <option value="">All</option>
                                <option value="1" selected>Active Only</option>
                                <option value="0">Inactive Only</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Occupation List</label>
                            <select id="listFilter" class="form-control">
                                <option value="">All Lists</option>
                                <option value="mltssl">MLTSSL</option>
                                <option value="stsol">STSOL</option>
                                <option value="rol">ROL</option>
                                <option value="csol">CSOL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>&nbsp;</label>
                            <div>
                                <button type="button" id="resetFilters" class="btn btn-secondary">
                                    @icon('fa-redo') Reset Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="anzscoTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Code</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Occupation Title</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Skill Level</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Lists</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Assessing Authority</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Validity (Years)</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Status</th>
                                    <th style="background-color: #f8f9fa; font-weight: 600;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this -->
                            </tbody>
                        </table>
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
    // Initialize DataTable
    var table = $('#anzscoTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("adminconsole.database.anzsco.data") }}',
            data: function(d) {
                d.status = $('#statusFilter').val();
                d.list = $('#listFilter').val();
            }
        },
        columns: [
            { data: 'anzsco_code', name: 'anzsco_code' },
            { data: 'occupation_title', name: 'occupation_title' },
            { data: 'skill_level', name: 'skill_level', orderable: true },
            { data: 'lists', name: 'lists', orderable: false, searchable: false },
            { data: 'assessing_authority', name: 'assessing_authority' },
            { data: 'assessment_validity_years', name: 'assessment_validity_years' },
            { data: 'status', name: 'is_active', orderable: false, searchable: false },
            { data: 'actions', name: 'actions', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]]
    });

    // Filter handlers
    $('#statusFilter, #listFilter').on('change', function() {
        table.draw();
    });

    $('#resetFilters').on('click', function() {
        $('#statusFilter').val('1');
        $('#listFilter').val('');
        table.draw();
    });

    // Status toggle handler
    $(document).on('change', '.status-toggle', function() {
        var checkbox = $(this);
        var id = checkbox.data('id');
        var isActive = checkbox.is(':checked');

        $.ajax({
            url: '/adminconsole/anzsco/' + id + '/toggle-status',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                    checkbox.prop('checked', !isActive);
                }
            },
            error: function() {
                toastr.error('Error updating status');
                checkbox.prop('checked', !isActive);
            }
        });
    });

    // Delete handler
    $(document).on('click', '.delete-occupation', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var title = $(this).data('title');

        if (confirm('Are you sure you want to delete "' + title + '"?')) {
            $.ajax({
                url: '/adminconsole/anzsco/' + id,
                type: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        table.draw();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function() {
                    toastr.error('Error deleting occupation');
                }
            });
        }
    });
});
</script>
@endpush

