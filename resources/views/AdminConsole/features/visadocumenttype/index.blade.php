@extends('layouts.crm_client_detail')
@section('title', 'Visa Document Category')

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
							<h4>Visa Document Category</h4>
							<div class="card-header-action">
								<a href="{{route('adminconsole.features.visadocumenttype.create')}}" class="btn btn-primary">Add New</a>
							</div>
						</div>
						<div class="card-body">
							<div class="table-responsive common_table">
								<table class="table text_wrap">
								<thead>
									<tr>
                                        <th>Title</th>
                                        <th>Client Name</th>
                                        <th>Client Matter Name</th>
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

                                        <td><?php
                                            if(isset($list->client_matter_id) && $list->client_matter_id != ''){
                                                $clientMatterInfo = \App\Models\ClientMatter::select('sel_matter_id')->where('id', $list->client_matter_id)->first();
                                                if($clientMatterInfo){
                                                    $matterInfo = \App\Models\Matter::select('title','nick_name')->where('id', $clientMatterInfo->sel_matter_id)->first();
                                                    if($matterInfo){
                                                        echo $matterInfo->title.' ('.$matterInfo->nick_name.')';
                                                    } else {
                                                         echo 'NA';
                                                    }
                                                } else {
                                                    echo 'Common For All Client Matters';
                                                }
                                            } else {
                                                echo 'Common For All Client Matters';
                                            }?>
                                        </td>
										<td>
											<div class="dropdown d-inline">
												<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Action</button>
												<div class="dropdown-menu">
													<a class="dropdown-item has-icon" href="{{route('adminconsole.features.visadocumenttype.edit', base64_encode(convert_uuencode(@$list->id)))}}">@icon('fa-edit') Edit</a>
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
