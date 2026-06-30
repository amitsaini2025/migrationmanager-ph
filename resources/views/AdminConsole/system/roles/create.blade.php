@extends('layouts.crm_client_detail')
@section('title', 'Create Roles and Permissions')

@section('content')
<style>
	.main-content .section-body .card .card-body .form-group{margin-bottom:10px;}
	.main-content .section-body .card .card-body .form-group .inner_checkbox .custom-checkbox{ display: inline-block;margin-right: 10px;}
</style>
<!-- Main Content --> 
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ route('adminconsole.system.roles.store') }}" method="POST" name="add-userrole" autocomplete="off" enctype="multipart/form-data">
				@csrf
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Add Roles and Permissions</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.system.roles.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
								</div>
							</div>
						</div>
					</div>	
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-body">
								<div class="row">
									<div class="col-12 col-md-6 col-lg-6">
										<div class="form-group">
											<label for="name">Name </label>
											<input type="text" name="name" value="{{ old('name') }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Name" />
										</div>	
									</div>	
									<div class="col-12 col-md-6 col-lg-6">
										<div class="form-group">
											<label for="description">Description </label>
											<textarea class="form-control" name="description"></textarea>
										</div>
									</div>
								</div>
								<div id="accordion" class="role_accordion">
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse"
										  data-bs-target="#panel-body-1" aria-expanded="true">
											<h4>OFFICE & TEAMS</h4>
										</div>
										<div class="accordion-body collapse show" id="panel-body-1" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="office_team" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="office_team" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[1]" class="office_team"> Can create new offices, edit and archive all the associated offices.</label></li>
												<li><label><input type="checkbox" name="module_access[2]" class="office_team"> Can only view associated office details and its information.</label></li>
												<li><label><input type="checkbox" name="module_access[3]" class="office_team"> Can invite users, cancel invitations, edit and change their status.</label></li>
												<li><label><input type="checkbox" name="module_access[4]" class="office_team"> Can only view users list and details of associated offices.</label></li>
												<li><label><input type="checkbox" name="module_access[5]" class="office_team"> Can access Service Page.</label></li>
												<li><label><input type="checkbox" name="module_access[6]" class="office_team"> Can manage Roles and Permissions</label></li>
											</ul>
										</div>
									</div>
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-2">
											<h4>WORKFLOWS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-2" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="workflows" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="workflows" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
										  <ul>
												<li><label><input type="checkbox" name="module_access[81]" class="workflows"> Can add, edit and delete Workflow and its stages.</label></li>
											</ul>
										</div>
									</div>
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-3">
											<h4>PARTNERS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-3" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="partners" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="partners" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											 <ul>
												<li><label><input type="checkbox" name="module_access[7]" class="partners"> Can add and edit partners.</label></li>
												<li><label><input type="checkbox" name="module_access[8]" class="partners"> Can only view partners information without commission percentage.</label></li>
												<li><label><input type="checkbox" name="module_access[9]" class="partners"> Can view partner's commission percentage.</label></li>
												<li><label><input type="checkbox" name="module_access[10]" class="partners"> Can delete partner.</label></li>
												<li><label><input type="checkbox" name="module_access[11]" class="partners"> Can delete partner's primary contact</label></li>
											</ul>
										</div>
									</div>

									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-4">
											<h4>PRODUCTS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-4" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="products" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="products" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[12]" class="products"> Can add and edit products.</label></li>
												<li><label><input type="checkbox" name="module_access[13]" class="products"> Can only view product's Information.</label></li>
												<li><label><input type="checkbox" name="module_access[14]" class="products"> Can delete products.</label></li>
											</ul>
										</div>
									</div>

									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-6">
											<h4>CLIENTS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-6" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="clients" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="clients" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[20]" class="clients"> Can view all the clients of all the associated offices. Can assign clients to any users of the associated offices, respectively.</label></li>
												<li><label><input type="checkbox" name="module_access[21]" class="clients"> Can add, edit and archive the clients.</label></li>
												<li><label><input type="checkbox" name="module_access[22]" class="clients"> Can only edit and archive assigned clients.</label></li>
												<li><label><input type="checkbox" name="module_access[23]" class="clients"> Can only view assigned clients.</label></li>
												<li><label><input type="checkbox" name="module_access[24]" class="clients">  Can delete client.</label></li>
												<li><label><input type="checkbox" name="module_access[25]" class="clients">  Can delete client's comments.</label></li>
												<li><label><input type="checkbox" name="module_access[26]" class="clients">  Can delete client's interested services.</label></li>
											</ul>
										</div>
									</div>

									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-7">
											<h4>INTERESTED SERVICES</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-7" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="interested_service" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="interested_service" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[30]" class="interested_service"> Can view commission in product fees of Interested Services.</label></li>
												<li><label><input type="checkbox" name="module_access[31]" class="interested_service">  Can edit commission in product fees of Interested Services.</label></li>
									{{-- Removed legacy sales forecast permissions (interested services) --}}
											</ul>
										</div>
									</div>

									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-8">
											<h4>MATTERS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-8" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="applications" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="applications" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[34]" class="applications"> Can create matters.</label></li>
												<li><label><input type="checkbox" name="module_access[35]" class="applications">  Can delete matters.</label></li>
												{{-- Payment schedule permissions (36-39) removed - feature unused --}}
												<li><label><input type="checkbox" name="module_access[40]" class="applications"> Can view/edit assigned and added matter by the users of primary office.</label></li>
												<li><label><input type="checkbox" name="module_access[41]" class="applications"> Can view/edit assigned and added matter by the users of secondary office.</label></li>
									{{-- Removed legacy sales forecast permissions (applications) --}}
									{{-- Removed application fee permissions (module_access[42], [43]) - fee options feature removed --}}
											</ul>
										</div>
									</div>
									
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-9">
											<h4>ACCOUNTS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-9" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="accounts" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="accounts" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[46]" class="accounts"> Can create invoices of associated offices.</label></li>
												<li><label><input type="checkbox" name="module_access[47]" class="accounts"> Can add, edit, delete and make/revert payments of clients invoices of associated offices.</label></li>
												<li><label><input type="checkbox" name="module_access[48]" class="accounts"> Can add, edit, delete and make/revert payments of invoices of only assigned clients.</label></li>
												<li><label><input type="checkbox" name="module_access[49]" class="accounts"> Can view invoices of only assigned clients. schedule.</label></li>
												<li><label><input type="checkbox" name="module_access[50]" class="accounts"> Can view invoices of all the clients of associated offices and shared matters.</label></li>
												<li><label><input type="checkbox" name="module_access[51]" class="accounts">  Can view income shared receivables of associated offices.</label></li>
												<li><label><input type="checkbox" name="module_access[52]" class="accounts"> Can make payments, revert and delete payables of income shared offices.</label></li>
												<li><label><input type="checkbox" name="module_access[53]" class="accounts"> Can view income shared payables.</label></li>
											</ul>
										</div>
									</div>
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-10">
											<h4>QUOTATIONS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-10" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="quotations" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="quotations" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[54]" class="quotations"> Can create quotation templates.</label></li>
												<li><label><input type="checkbox" name="module_access[55]" class="quotations"> Can edit quotation templates.</label></li>
												<li><label><input type="checkbox" name="module_access[56]" class="quotations"> Can delete quotation templates.</label></li>
												<li><label><input type="checkbox" name="module_access[57]" class="quotations"> Can create quotations.</label></li>
												<li><label><input type="checkbox" name="module_access[58]" class="quotations"> Can edit quotations.</label></li>
												<li><label><input type="checkbox" name="module_access[59]" class="quotations"> Can archive quotations.</label></li>
												<li><label><input type="checkbox" name="module_access[60]" class="quotations"> Can view quotations.</label></li>
												<li><label><input type="checkbox" name="module_access[61]" class="quotations"> Can delete quotations.</label></li>
											</ul>
										</div>
									</div>
									
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-11">
											<h4>REPORTS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-11" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="reports" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="reports" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[62]" class="reports"> Can view Client and Matter Reports.</label></li>
												<li><label><input type="checkbox" name="module_access[63]" class="reports"> Can view Invoice Report.</label></li>
												<li><label><input type="checkbox" name="module_access[64]" class="reports"> Can view Office Check-In Report.</label></li>
									{{-- Removed legacy sales forecast report permissions --}}
												<li><label><input type="checkbox" name="module_access[67]" class="reports"> Can view personal task report.</label></li>
												<li><label><input type="checkbox" name="module_access[68]" class="reports"> Can view all task report.</label></li>
												<li><label><input type="checkbox" name="module_access[69]" class="reports"> Can export all reports.</label></li>
											</ul>
										</div>
									</div>
									
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-12">
											<h4>APPOINTMENTS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-12" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="appointments" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="appointments" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[70]" class="appointments"> Can manage Partners appointments.</label></li>
											</ul>
										</div>
									</div>
									
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-13">
											<h4>TASKS</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-13" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="tasks" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="tasks" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[82]" class="tasks"> Can create tasks.</label></li>
											</ul>
										</div>
									</div>
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-14">
											<h4>OFFICE CHECK-IN</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-14" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="office_checkin" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="office_checkin" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[71]" class="office_checkin"> Can add office check-ins.</label></li>
												<li><label><input type="checkbox" name="module_access[72]" class="office_checkin"> Can edit office check-ins.</label></li>
												<li><label><input type="checkbox" name="module_access[73]" class="office_checkin"> Can view office check-ins assigned only.</label></li>
												<li><label><input type="checkbox" name="module_access[74]" class="office_checkin"> Can view all office check-ins.</label></li>
												<li><label><input type="checkbox" name="module_access[75]" class="office_checkin"> Can archive office check-ins.</label></li>
												<li><label><input type="checkbox" name="module_access[76]" class="office_checkin"> Can delete office check-ins.</label></li>
											</ul>
										</div>
									</div>
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#panel-body-15">
											<h4>DOCUMENT CHECKLIST</h4>
										</div>
										<div class="accordion-body collapse" id="panel-body-15" data-parent="#accordion">
											<div class="select_toggle">
												<a href="javascript:;" data-class="document_checklist" class="btn btn-primary select_all">Select All</a>
												<a href="javascript:;" data-class="document_checklist" class="btn btn-secondary deselect_all">Deselect All</a>
											</div>
											<ul>
												<li><label><input type="checkbox" name="module_access[77]" class="document_checklist"> Can add and rename document type.</label></li>
												<li><label><input type="checkbox" name="module_access[78]" class="document_checklist"> Can activate/deactivate document type.</label></li>
												<li><label><input type="checkbox" name="module_access[79]" class="document_checklist"> Can add and edit document checklist</label></li>
												<li><label><input type="checkbox" name="module_access[80]" class="document_checklist"> Can activate/deactivate document checklist</label></li>
											</ul>
										</div> 
									</div>
								
								</div>
								<div class="form-group float-right">
									<button type="submit" class="btn btn-primary">Save</button>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</section>
</div>
@endsection
@push('scripts')
<script>
	jQuery(document).ready(function($){
		$('.select_toggle a.select_all').on('click', function(){
			$selectval = $(this).attr('data-class'); 
			$('.'+$selectval).prop('checked', true);
		});
		$('.select_toggle a.deselect_all').on('click', function(){
			$deselectval = $(this).attr('data-class'); 
			$('.'+$deselectval).prop('checked', false);
		});
	});
</script>
@endpush