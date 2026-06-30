@extends('layouts.crm_client_detail')
@section('title', 'Personal Document Category')

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
							<h4>Personal Document Category</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.personaldocumenttype.create')}}" class="btn btn-primary">Add New</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
                                        <th>Title</th>
                                        <th>Client Name</th>
                                        <th></th>
									</tr>
								</thead>
								@if(@$totalData !== 0)
								<?php $i=0; ?>
								<tbody class="tdata">
								@foreach (@$lists as $list)
									<tr id="id_{{@$list->id}}">
                                        <td><?php if(isset($list->title)){ echo $list->title; }?></td>
                                        <td><?php
                                            if(isset($list->client_id) && $list->client_id != ''){
                                                $admin = \App\Models\Admin::select('first_name','last_name')->where('id', $list->client_id)->first();
                                                if($admin){
                                                    echo $admin->first_name.' '.$admin->last_name;
                                                } else {
                                                    echo 'NA';
                                                }
                                            } else {
                                                echo 'Common For All Clients';
                                            }?>
                                        </td>
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.personaldocumenttype.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
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
});
</script>
@endpush
