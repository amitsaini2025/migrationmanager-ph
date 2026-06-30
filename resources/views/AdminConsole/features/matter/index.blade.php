@extends('layouts.crm_client_detail')
@section('title', 'Matter')

@section('content')
<style>
    /* Scoped to this page only — avoids breaking sidebar/header dropdowns and global .table styles */
    .matter-index-page .filter_panel {
        margin-bottom: 30px;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        display: none;
    }

    .matter-index-page .filter_panel h4 {
        color: #4a5568 !important;
        font-size: 1.1rem;
        margin-bottom: 20px;
        font-weight: 600;
    }

    .matter-index-page .table thead th {
        background-color: #f8f9fa !important;
        color: #343a40 !important;
        font-weight: 600 !important;
        border-bottom: 2px solid #dee2e6 !important;
        padding: 12px 15px !important;
    }

    .matter-index-page .table tbody td {
        color: #495057 !important;
        padding: 12px 15px !important;
        border-bottom: 1px solid #dee2e6 !important;
    }

    .matter-index-page .form-group label {
        color: #495057 !important;
        font-weight: 500 !important;
        margin-bottom: 8px !important;
    }

    .matter-index-page .card-header h4 {
        color: #343a40 !important;
        font-weight: 600 !important;
        margin: 0 !important;
    }

    .matter-index-page .dropdown {
        position: relative;
    }

    .matter-index-page .dropdown-menu {
        min-width: 200px !important;
        max-width: 280px !important;
        width: auto !important;
        z-index: 1060 !important;
        background-color: #fff !important;
        border: 1px solid #e9ecef !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        max-height: min(85vh, 520px) !important;
        overflow-y: auto !important;
    }

    .matter-index-page .dropdown-item {
        padding: 8px 12px !important;
        font-size: 0.9rem !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        max-width: 100% !important;
    }

    .matter-index-page .dropdown-item i {
        margin-right: 6px !important;
        width: 14px !important;
        text-align: center !important;
    }

    .matter-index-page .dropdown-item.has-icon {
        display: flex !important;
        align-items: center !important;
        padding: 8px 12px !important;
        font-size: 0.8rem !important;
        line-height: 1.2 !important;
        position: relative !important;
        min-height: 32px !important;
        max-width: 100% !important;
        overflow: visible !important;
        text-overflow: clip !important;
        white-space: nowrap !important;
    }

    .matter-index-page .dropdown-item.has-icon i {
        width: 14px !important;
        height: 14px !important;
        flex-shrink: 0 !important;
        text-align: center !important;
        display: inline-block !important;
        margin-right: 8px !important;
        position: static !important;
    }

    .matter-index-page .dropdown-menu .dropdown-item {
        visibility: visible !important;
        opacity: 1 !important;
    }

    .matter-index-page .table-responsive.common_table {
        overflow: visible !important;
    }

    .matter-index-page .table tbody tr {
        position: relative !important;
    }

    .matter-index-page .table tbody tr td:last-child {
        overflow: visible !important;
        position: relative !important;
    }

    .matter-index-page .dropdown-item span,
    .matter-index-page .dropdown-item {
        white-space: nowrap !important;
        overflow: visible !important;
        text-overflow: clip !important;
    }

    /*
     * Matter list only: allow menus to paint past .main-content { overflow: hidden }
     * and past the card body / footer (grey bar) without affecting other CRM pages.
     */
    .matter-index-layout > .main-content {
        overflow: visible !important;
    }

    .matter-index-layout .matter-index-page .card,
    .matter-index-layout .matter-index-page .card-body {
        overflow: visible !important;
    }
</style>
<div class="crm-container matter-index-layout">
	<div class="main-content">
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
				<div class="matter-index-page">
					<div class="card">
						<div class="card-header">
							<h4>All Matters</h4>
                            <div class="card-header-action">
                                <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn mr-2">@icon('fa-filter') Filter</a>
                                <a href="{{route('adminconsole.features.matter.create')}}" class="btn btn-primary">Create Matter</a>
							</div>
						</div>
						<div class="card-body">
                            <div class="filter_panel"><h4>Search</h4>
                                <form action="{{route('adminconsole.features.matter.index')}}" method="get">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="matter_id" class="col-form-label" style="color:#495057 !important; font-weight: 500 !important;">Matter Name</label>
                                                <select name="matter_id" id="matter_id" class="form-control mm-select">
                                                    <option value="">Select Matter</option>
                                                    @foreach ($activeMatters as $matter)
                                                        <option value="{{ $matter->id }}" {{ (string) old('matter_id', Request::get('matter_id')) === (string) $matter->id ? 'selected' : '' }}>
                                                            {{ $matter->title }}@if(!empty($matter->nick_name)) ({{ $matter->nick_name }})@endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="client" class="col-form-label" style="color:#495057 !important; font-weight: 500 !important;">Client</label>
                                                <select name="client" id="client" class="form-control">
                                                    <option value="">All</option>
                                                    <option value="personal" {{ old('client', Request::get('client')) === 'personal' ? 'selected' : '' }}>Personal</option>
                                                    <option value="company" {{ old('client', Request::get('client')) === 'company' ? 'selected' : '' }}>Company</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4" style="margin-top:35px;">
                                            <button type="submit" class="btn btn-primary btn-theme-lg">Search</button>
                                            <a class="btn btn-info" href="{{route('adminconsole.features.matter.index')}}">Reset</a>
                                        </div>
                                    </div>
                                </form>
                            </div>

							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Matter Name</th>
										<th>Client</th>
										<th></th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
										<td>{{ @$list->title == "" ? config('constants.empty') : Str::limit(@$list->title, '50', '...') }}</td>
										<td>{{ $list->isForCompany() ? 'Company' : 'Personal' }}</td>
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle matter-action-dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.matter.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
													<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'matters')">@icon('fa-trash') Delete</a>
													<?php
													$hasTemplate = \App\Models\EmailTemplate::forMatter($list->id)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->exists();
													?>
													@if($hasTemplate)
													<?php
													$Template_info = \App\Models\EmailTemplate::forMatter($list->id)->ofType(\App\Models\EmailTemplate::TYPE_MATTER_FIRST)->first();
													?>
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.matteremailtemplate.edit', [$Template_info->id, $list->id])}}">@icon('fa-edit') Edit First Email</a>
													@else
													<a class="dropdown-item has-icon" href="{{ route('adminconsole.features.matteremailtemplate.create', ['matter_id' => @$list->id]) }}">@icon('fa-edit') Create First Email</a>
													@endif

													<a class="dropdown-item has-icon" href="{{route('upload_checklists.matter', @$list->id)}}">@icon('fa-list') Matter Checklist</a>
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.matterotheremailtemplate.index', @$list->id)}}">@icon('fa-envelope') Email Templates</a>
												</div>
											</div>
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
										<td style="text-align:center;" colspan="3">
											No Record found
										</td>
									</tr>
								</tbody>
								@endif
							</table>
						</div>

                        <div class="card-footer">
							{!! $lists->appends(\Request::except('page'))->render() !!}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
    $('.matter-index-page .filter_btn').on('click', function(){
		$('.matter-index-page .filter_panel').toggle();
	});

    if (typeof $.fn.mmSelect !== 'undefined') {
        $('#matter_id').mmSelect({
            placeholder: 'Select Matter',
            allowClear: true,
            width: '100%'
        });
    }

    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
        document.querySelectorAll('.matter-index-page .matter-action-dropdown-toggle').forEach(function (el) {
            bootstrap.Dropdown.getOrCreateInstance(el, {
                /* Escapes .main-content / card overflow so the full menu clears the footer */
                popperConfig: { strategy: 'fixed' }
            });
        });
    }

	$('.cb-element').change(function () {
        if ($('.cb-element:checked').length == $('.cb-element').length){
            $('#checkbox-all').prop('checked',true);
        } else {
            $('#checkbox-all').prop('checked',false);
        }
    });
});
</script>
@endpush
