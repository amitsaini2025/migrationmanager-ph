@extends('layouts.crm_client_detail')
@section('title', 'Document Checklist')

@section('styles')
<style>
    /* Ensure dropdown menu is visible and not clipped */
    .table-responsive.common_table {
        overflow: visible !important;
    }
    
    .table-responsive.common_table .table td {
        overflow: visible !important;
    }
    
    .table-responsive.common_table .dropdown {
        position: relative;
        overflow: visible !important;
    }
    
    /* Ensure dropdown menu is fully visible */
    .table-responsive.common_table .dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        right: 0 !important;
        left: auto !important;
        z-index: 9999 !important;
        display: none !important;
        min-width: 180px !important;
    }
    
    .table-responsive.common_table .dropdown-menu.show {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* Ensure all dropdown items are visible */
    .table-responsive.common_table .dropdown-menu .dropdown-item {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        white-space: nowrap !important;
        padding: 0.5rem 1rem !important;
        height: auto !important;
        min-height: 2.25rem !important;
        line-height: 1.5 !important;
        width: 100% !important;
    }
    
    .table-responsive.common_table .dropdown-item.has-icon {
        display: flex !important;
        align-items: center !important;
    }
    
    .table-responsive.common_table .dropdown-item.has-icon i {
        margin-right: 8px !important;
        width: 16px !important;
        text-align: center !important;
    }
</style>
@endsection

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
							<h4>Document Checklist</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.documentchecklist.create')}}" class="btn btn-primary">Create Checklist</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
										<!--<th class="text-center" style="width:30px;">
											<div class="custom-checkbox custom-checkbox-table custom-control">
												<input type="checkbox" data-checkboxes="mygroup" data-checkbox-role="dad" class="custom-control-input" id="checkbox-all">
												<label for="checkbox-all" class="custom-control-label">&nbsp;</label>
											</div>
										</th>-->
										<th>Name</th>
                                        <th>Document Type</th>
										<th></th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
										<!--<td class="text-center">
											<div class="custom-checkbox custom-control">
											{{--	<input data-id="{{@$list->id}}" data-email="{{@$list->email}}" data-name="{{@$list->first_name}} {{@$list->last_name}}" type="checkbox" data-checkboxes="mygroup" class="cb-element custom-control-input" id="checkbox-{{$i}}">
												<label for="checkbox-{{$i}}" class="custom-control-label">&nbsp;</label>--}}
											</div>
										</td>-->
										<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td>
										<td>
                                            <?php
                                            if( isset($list->doc_type) && $list->doc_type !="" ){
                                                if($list->doc_type == 1 ){
                                                    echo "Personal";
                                                } else if($list->doc_type == 2 ){
                                                    echo "Visa";
                                                } else if($list->doc_type == 3 ){
                                                    echo "Nomination";
                                                }
                                            }?>
                                        </td>
                                        <td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.documentchecklist.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
													<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'portal_document_checklists')">@icon('fa-trash') Delete</a>
												</div>
											</div>
										</td>
									</tr>
								@endforeach
								</tbody>
								@else
								<tbody>
									<tr>
										<td style="text-align:center;" colspan="7">
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
	</section>
</div>

@endsection
@push('scripts')
<script>
jQuery(document).ready(function($){
	$('.cb-element').change(function () {
	if ($('.cb-element:checked').length == $('.cb-element').length){
	  $('#checkbox-all').prop('checked',true);
	}
	else {
	  $('#checkbox-all').prop('checked',false);
	}
	/* if ($('.cb-element:checked').length > 0){
			$('.is_checked_client').show();
			$('.is_checked_clientn').hide();
		}else{
			$('.is_checked_client').hide();
			$('.is_checked_clientn').show();
		} */
	});
});
</script>
@endpush
