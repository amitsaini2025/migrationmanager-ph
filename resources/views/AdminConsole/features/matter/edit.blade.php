@extends('layouts.crm_client_detail')
@section('title', 'Edit Matter')

@section('content')
<!-- Main Content -->
<div class="main-content">
	<section class="section">
		<div class="section-body">
			<form action="{{ route('adminconsole.features.matter.update', $fetchedData->id) }}" name="edit-matter" autocomplete="off" enctype="multipart/form-data" method="POST">
				@csrf
				@method('PUT')
				<div class="row">
					<div class="col-12 col-md-12 col-lg-12">
						<div class="card">
							<div class="card-header">
								<h4>Edit Matter</h4>
								<div class="card-header-action">
									<a href="{{route('adminconsole.features.matter.index')}}" class="btn btn-primary">@icon('fa-arrow-left') Back</a>
								</div>
							</div>
						</div>
					</div>
					<div class="col-3 col-md-3 col-lg-3">
						@include('../Elements/CRM/setting')
					</div>
					<div class="col-9 col-md-9 col-lg-9">
						<div class="card">
							<div class="card-body">
								<div id="accordion">
									<div class="accordion">
										<div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
											<h4>Matter Information</h4>
										</div>
										<div class="accordion-body collapse show" id="primary_info" data-parent="#accordion">
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="title">Title <span class="span_req">*</span></label>
														<input type="text" name="title" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Title" value="{{ old('title', @$fetchedData->title) }}">
														@if ($errors->has('title'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('title') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="nick_name">Nick Name <span class="span_req">*</span></label>
														<input type="text" name="nick_name" class="form-control" pattern="[a-zA-Z0-9 ]+" title="Only letters, numbers, and spaces are allowed" data-valid="required" autocomplete="off" placeholder="Enter Nick Name" value="{{ old('nick_name', @$fetchedData->nick_name) }}">
														@if ($errors->has('nick_name'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('nick_name') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="workflow_id">Default Workflow</label>
														<select name="workflow_id" id="workflow_id" class="form-control">
															<option value="">— Use General —</option>
															@foreach(\App\Models\Workflow::orderBy('name')->get() as $w)
															<option value="{{ $w->id }}" {{ old('workflow_id', $fetchedData->workflow_id ?? '') == $w->id ? 'selected' : '' }}>{{ $w->name }}{{ $w->matter ? ' (' . $w->matter->title . ')' : '' }}</option>
															@endforeach
														</select>
														<small class="form-text text-muted">New client matters of this type will use this workflow by default.</small>
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="is_for_company">Is this matter for companies? <span class="span_req">*</span></label>
														<select name="is_for_company" id="is_for_company" class="form-control" data-valid="required">
															<option value="0" {{ old('is_for_company', ($fetchedData->is_for_company ?? false) ? '1' : '0') == '0' ? 'selected' : '' }}>No (For Personal Clients)</option>
															<option value="1" {{ old('is_for_company', ($fetchedData->is_for_company ?? false) ? '1' : '0') == '1' ? 'selected' : '' }}>Yes (For Company Clients Only)</option>
														</select>
														<small class="form-text text-muted">
															If "Yes", this matter will only be available when creating matters for company clients. 
															If "No", it will only be available for personal clients.
														</small>
														@if ($errors->has('is_for_company'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('is_for_company') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											
											<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
												<h4>Block Fee</h4>
											</div>

											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Block_1_Description">Block 1 Description</label>
														<input type="text" name="Block_1_Description" class="form-control" autocomplete="off" placeholder="Enter Block 1 Description" value="{{ old('Block_1_Description', @$fetchedData->Block_1_Description) }}">
														@if ($errors->has('Block_1_Description'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Block_1_Description') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Block_1_Ex_Tax">Block 1 Incl. Tax</label>
														<input type="text" name="Block_1_Ex_Tax" class="form-control" autocomplete="off" placeholder="Enter Block 1 Incl. Tax" value="{{ old('Block_1_Ex_Tax', @$fetchedData->Block_1_Ex_Tax) }}">
														@if ($errors->has('Block_1_Ex_Tax'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Block_1_Ex_Tax') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>

											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Block_2_Description">Block 2 Description</label>
														<input type="text" name="Block_2_Description" class="form-control" autocomplete="off" placeholder="Enter Block 2 Description" value="{{ old('Block_2_Description', @$fetchedData->Block_2_Description) }}">
														@if ($errors->has('Block_2_Description'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Block_2_Description') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Block_2_Ex_Tax">Block 2 Incl. Tax</label>
														<input type="text" name="Block_2_Ex_Tax" class="form-control" autocomplete="off" placeholder="Enter Block 2 Incl. Tax" value="{{ old('Block_2_Ex_Tax', @$fetchedData->Block_2_Ex_Tax) }}">
														@if ($errors->has('Block_2_Ex_Tax'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Block_2_Ex_Tax') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>

											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Block_3_Description">Block 3 Description</label>
														<input type="text" name="Block_3_Description" class="form-control" autocomplete="off" placeholder="Enter Block 3 Description" value="{{ old('Block_3_Description', @$fetchedData->Block_3_Description) }}">
														@if ($errors->has('Block_3_Description'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Block_3_Description') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Block_3_Ex_Tax">Block 3 Incl. Tax</label>
														<input type="text" name="Block_3_Ex_Tax" class="form-control" autocomplete="off" placeholder="Enter Block 3 Incl. Tax" value="{{ old('Block_3_Ex_Tax', @$fetchedData->Block_3_Ex_Tax) }}">
														@if ($errors->has('Block_3_Ex_Tax'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Block_3_Ex_Tax') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											
											<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
												<h4>Department Fee</h4>
											</div>

											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="surcharge">Surcharge</label>
														<select class="form-control" name="surcharge" id="surcharge">
															<option value="">Select</option>
															<option value="Yes" {{ old('surcharge', $fetchedData->surcharge ?? '') == 'Yes' ? 'selected' : '' }}>Yes</option>
															<option value="No" {{ old('surcharge', $fetchedData->surcharge ?? '') == 'No' ? 'selected' : '' }}>No</option>
														</select>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Base_Application_Charge">Dept Base Application Charge</label>
														<input type="text" name="Dept_Base_Application_Charge" class="form-control" autocomplete="off" placeholder="Enter Dept Base Application Charge" value="{{ old('Dept_Base_Application_Charge', @$fetchedData->Dept_Base_Application_Charge) }}">
														@if ($errors->has('Dept_Base_Application_Charge'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Base_Application_Charge') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Non_Internet_Application_Charge">Dept Non Internet Application Charge</label>
														<input type="text" name="Dept_Non_Internet_Application_Charge" class="form-control" autocomplete="off" placeholder="Enter Dept Non Internet Application Charge" value="{{ old('Dept_Non_Internet_Application_Charge', @$fetchedData->Dept_Non_Internet_Application_Charge) }}">
														@if ($errors->has('Dept_Non_Internet_Application_Charge'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Non_Internet_Application_Charge') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Additional_Applicant_Charge_18_Plus">Dept Additional Applicant Charge 18 +</label>
														<input type="text" name="Dept_Additional_Applicant_Charge_18_Plus" class="form-control" autocomplete="off" placeholder="Enter Dept Additional Applicant Charge 18 Plus" value="{{ old('Dept_Additional_Applicant_Charge_18_Plus', @$fetchedData->Dept_Additional_Applicant_Charge_18_Plus) }}">
														@if ($errors->has('Dept_Additional_Applicant_Charge_18_Plus'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Additional_Applicant_Charge_18_Plus') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Additional_Applicant_Charge_Under_18">Dept Additional Applicant Charge Under 18</label>
														<input type="text" name="Dept_Additional_Applicant_Charge_Under_18" class="form-control" autocomplete="off" placeholder="Enter Dept Additional Applicant Charge Under 18" value="{{ old('Dept_Additional_Applicant_Charge_Under_18', @$fetchedData->Dept_Additional_Applicant_Charge_Under_18) }}">
														@if ($errors->has('Dept_Additional_Applicant_Charge_Under_18'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Additional_Applicant_Charge_Under_18') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Subsequent_Temp_Application_Charge">Dept Subsequent Temp Application Charge</label>
														<input type="text" name="Dept_Subsequent_Temp_Application_Charge" class="form-control" autocomplete="off" placeholder="Enter Dept Subsequent Temp Application Charge" value="{{ old('Dept_Subsequent_Temp_Application_Charge', @$fetchedData->Dept_Subsequent_Temp_Application_Charge) }}">
														@if ($errors->has('Dept_Subsequent_Temp_Application_Charge'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Subsequent_Temp_Application_Charge') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Second_VAC_Instalment_Charge_18_Plus">Dept Second VAC Instalment Charge 18 +</label>
														<input type="text" name="Dept_Second_VAC_Instalment_Charge_18_Plus" class="form-control" autocomplete="off" placeholder="Enter Dept Second VAC Instalment Charge 18 Plus" value="{{ old('Dept_Second_VAC_Instalment_Charge_18_Plus', @$fetchedData->Dept_Second_VAC_Instalment_Charge_18_Plus) }}">
														@if ($errors->has('Dept_Second_VAC_Instalment_Charge_18_Plus'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Second_VAC_Instalment_Charge_18_Plus') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Second_VAC_Instalment_Under_18">Dept Second VAC Instalment Under 18</label>
														<input type="text" name="Dept_Second_VAC_Instalment_Under_18" class="form-control" autocomplete="off" placeholder="Enter Dept Second VAC Instalment Under 18" value="{{ old('Dept_Second_VAC_Instalment_Under_18', @$fetchedData->Dept_Second_VAC_Instalment_Under_18) }}">
														@if ($errors->has('Dept_Second_VAC_Instalment_Under_18'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Second_VAC_Instalment_Under_18') }}</strong>
															</span>
														@endif
													</div>
												</div>
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Nomination_Application_Charge">Dept Nomination Application Charge</label>
														<input type="text" name="Dept_Nomination_Application_Charge" class="form-control" autocomplete="off" placeholder="Enter Dept Nomination Application Charge" value="{{ old('Dept_Nomination_Application_Charge', @$fetchedData->Dept_Nomination_Application_Charge) }}">
														@if ($errors->has('Dept_Nomination_Application_Charge'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Nomination_Application_Charge') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="Dept_Sponsorship_Application_Charge">Dept Sponsorship Application Charge</label>
														<input type="text" name="Dept_Sponsorship_Application_Charge" class="form-control" autocomplete="off" placeholder="Enter Dept Sponsorship Application Charge" value="{{ old('Dept_Sponsorship_Application_Charge', @$fetchedData->Dept_Sponsorship_Application_Charge) }}">
														@if ($errors->has('Dept_Sponsorship_Application_Charge'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('Dept_Sponsorship_Application_Charge') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
											

											<div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
												<h4>Additional Fee</h4>
											</div>
											<div class="row">
												<div class="col-12 col-md-6 col-lg-6">
													<div class="form-group">
														<label for="additional_fee_1">Additional Fee1</label>
														<input type="text" name="additional_fee_1" class="form-control" autocomplete="off" placeholder="Enter Additional Fee" value="{{ old('additional_fee_1', @$fetchedData->additional_fee_1) }}">
														@if ($errors->has('additional_fee_1'))
															<span class="custom-error" role="alert">
																<strong>{{ $errors->first('additional_fee_1') }}</strong>
															</span>
														@endif
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div class="form-group float-right">
									<button type="submit" class="btn btn-primary">Update Matter</button>
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

