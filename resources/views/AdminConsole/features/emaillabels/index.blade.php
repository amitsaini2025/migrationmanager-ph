@extends('layouts.crm_client_detail')
@section('title', 'Email Labels')

@section('styles')
<style>
    /* Dropdown menu styling for Email Labels */
    .table-responsive .dropdown {
        position: relative;
    }
    
    .table-responsive .dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        left: auto !important;
        right: 0 !important;
        min-width: 180px !important;
        padding: 8px 0 !important;
        margin: 4px 0 0 !important;
        background-color: #ffffff !important;
        border: 1px solid #e9ecef !important;
        border-radius: 6px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        z-index: 9999 !important;
        display: none !important;
    }
    
    .table-responsive .dropdown-menu.show {
        display: block !important;
    }
    
    .table-responsive .dropdown-item {
        display: block !important;
        width: 100% !important;
        padding: 10px 16px !important;
        clear: both !important;
        font-weight: 500 !important;
        color: #495057 !important;
        text-align: left !important;
        white-space: nowrap !important;
        background-color: transparent !important;
        border: 0 !important;
        text-decoration: none !important;
        transition: all 0.2s ease !important;
    }
    
    .table-responsive .dropdown-item:hover:not(.text-muted) {
        color: #ffffff !important;
        background-color: #3498db !important;
    }
    
    .table-responsive .dropdown-item.has-icon {
        display: flex !important;
        align-items: center !important;
    }
    
    .table-responsive .dropdown-item.has-icon i {
        margin-right: 8px !important;
        width: 16px !important;
        text-align: center !important;
    }
    
    .table-responsive .dropdown-item.text-muted {
        color: #6c757d !important;
        cursor: not-allowed !important;
        opacity: 0.6 !important;
    }
    
    .table-responsive .dropdown-item.text-muted:hover {
        background-color: transparent !important;
        color: #6c757d !important;
    }
    
    .table-responsive {
        overflow: visible !important;
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
							<h4>Email Labels</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.emaillabels.create')}}" class="btn btn-primary">Create Email Label</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table"> 
								<table class="table text_wrap">
								<thead>
									<tr>
										<th>Label</th>
										<th>Name</th>
										<th>Type</th>
										<th>Created By</th>
										<th>Status</th>
										<th>Last Updated</th>
										<th>Action</th>
									</tr> 
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">	
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
										<td>
											<span class="badge" style="background-color: {{@$list->color}}20; border: 1px solid {{@$list->color}}; color: {{@$list->color}}; padding: 5px 10px; border-radius: 4px;">
												{!! \App\Helpers\IconHelper::renderStored(@$list->icon, [], 'tag') !!} {{@$list->name}}
											</span>
										</td>
										<td>{{ @$list->name == "" ? config('constants.empty') : Str::limit(@$list->name, '50', '...') }}</td>
										<td>
											@if(@$list->type == 'system')
												<span class="badge badge-info">System</span>
											@else
												<span class="badge badge-secondary">Custom</span>
											@endif
										</td>
										<td>{{@$list->user ? @$list->user->first_name . ' ' . @$list->user->last_name : 'System'}}</td>
										<td>
											@if(@$list->is_active)
												<span class="badge badge-success">Active</span>
											@else
												<span class="badge badge-danger">Inactive</span>
											@endif
										</td>
										<td>@if($list->updated_at != '') {{date('Y-m-d H:i', strtotime($list->updated_at))}} @else - @endif</td>
										
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" id="actionBtn_{{@$list->id}}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu" aria-labelledby="actionBtn_{{@$list->id}}">
													@if(@$list->type == 'system')
														<a class="dropdown-item has-icon text-muted" href="javascript:void(0);" style="cursor: not-allowed; pointer-events: none;">@icon('fa-edit') Edit (System labels cannot be edited)</a>
														<a class="dropdown-item has-icon text-muted" href="javascript:void(0);" style="cursor: not-allowed; pointer-events: none;">@icon('fa-trash') Delete (System labels cannot be deleted)</a>
													@else
														<a class="dropdown-item has-icon" href="{{route('adminconsole.features.emaillabels.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
														<a class="dropdown-item has-icon" href="javascript:;" onClick="deleteAction({{@$list->id}}, 'email_labels')">@icon('fa-trash') Delete</a>
													@endif
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
							{{ @$lists->links() }}
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
	});
	
	// Debug: Log the type for each row
	console.log('Email Labels loaded. Checking types...');
	$('.tdata tr').each(function() {
		var $row = $(this);
		var type = $row.find('td:eq(2) .badge').text().trim();
		var dropdownItems = $row.find('.dropdown-menu .dropdown-item').length;
		console.log('Row ID:', $row.attr('id'), 'Type:', type, 'Dropdown items:', dropdownItems);
	});
	
	// Override Bootstrap's default dropdown behavior for this page only
	$('.table-responsive .dropdown-toggle').off('click').on('click', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var $this = $(this);
		var $dropdown = $this.next('.dropdown-menu');
		var isOpen = $dropdown.hasClass('show');
		
		console.log('Dropdown clicked. Current state:', isOpen ? 'open' : 'closed');
		console.log('Dropdown items:', $dropdown.find('.dropdown-item').length);
		
		// Close ALL dropdowns first
		$('.table-responsive .dropdown-menu').removeClass('show').css('display', 'none');
		$('.table-responsive .dropdown-toggle').attr('aria-expanded', 'false');
		
		// Toggle current dropdown
		if (!isOpen) {
			$dropdown.addClass('show').css('display', 'block');
			$this.attr('aria-expanded', 'true');
			
			// Log what's inside the dropdown
			$dropdown.find('.dropdown-item').each(function(i) {
				console.log('Item ' + i + ':', $(this).text().trim());
			});
		}
	});
	
	// Close dropdown when clicking outside
	$(document).on('click', function(e) {
		if (!$(e.target).closest('.table-responsive .dropdown').length) {
			$('.table-responsive .dropdown-menu').removeClass('show').css('display', 'none');
			$('.table-responsive .dropdown-toggle').attr('aria-expanded', 'false');
		}
	});
});	
</script>
@endpush

