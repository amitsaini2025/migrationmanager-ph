<!-- Appointment Modal -->
<div class="modal fade add_appointment custom_modal" id="create_appoint" tabindex="-1" role="dialog" aria-labelledby="create_interestModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header" style="background: linear-gradient(135deg, #0056b3 0%, #004085 100%); color: white; border-bottom: 2px solid rgba(255,255,255,0.2); padding: 18px 24px;">
				<h5 class="modal-title" id="interestModalLabel" style="font-weight: 700; font-size: 18px;">
					@icon('fa-calendar-plus', ['class' => 'mr-2'])Schedule Appointment
				</h5>
				<button type="button" class="close" data-bs-dismiss="modal" aria-label="Close" style="color: white; opacity: 0.9; font-size: 24px; font-weight: 300;">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="post" action="{{URL::to('/add-appointment-book')}}" name="appointform" id="appointform" autocomplete="off" enctype="multipart/form-data">
				    @csrf
				    <input type="hidden" name="client_id" value="{{$fetchedData->id}}">
                    <input type="hidden" name="client_unique_id" value="{{$fetchedData->client_id}}">
					<div class="row">
						<div class="col-12 col-md-6 col-lg-6">

						</div>

						<div class="col-12 col-md-12 col-lg-12">
							<div class="form-group row align-items-center">
								<label for="client_name" class="col-sm-3 col-form-label">Client Reference No<span class="span_req">*</span></label>
                                <div class="col-sm-6">
                                    <input type="text" name="client_name" value="{{ @$fetchedData->client_id }}" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Client Reference" readonly>
                                </div>
                            </div>
						</div>

                        <input type="hidden" name="timezone" value="Australia/Melbourne">

                        <div class="col-12 col-md-12 col-lg-12 nature_of_enquiry_row" id="nature_of_enquiry">
							<div class="form-group row align-items-center">
								<label for="noe_id" class="col-sm-3 col-form-label">Nature of Enquiry<span class="span_req">*</span></label>
                                <div class="col-sm-9">
                                    <select class="form-control enquiry_item" name="noe_id" data-valid="required">
                                        <option value="">Select</option>
										<option value="1">GSM Visas: 491, 190, 189, 191</option>
										<option value="9">EOI/ROI</option>
										<option value="2">TR: 485 visa</option>
										<option value="10">Employer Sponsored Visas: 494, 482, 186, DAMA</option>
										<option value="3">JRP/Skill Assessment</option>
										<option value="4">Tourist Visa</option>
										<option value="5">Education/Course Change/Student Visa/Student Dependent Visa (for education selection for Australian onshore clients only)</option>
										<option value="6">Complex matters: ART, Protection visa, Federal Case</option>
										<option value="7">Visa Cancellation/ NOICC/ Visa refusals</option>
										<option value="11">Family Visas (Parent Visa, Partner Visa, Child Visa)</option>
										<option value="12">Citizenship</option>
										<option value="8">Anyone who is outside Australia</option>
									</select>
                                </div>
                            </div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12 services_row" id="services" style="display: none;">
							<div class="form-group">
								<label for="service_id" class="font-weight-bold text-dark mb-3">Services <span class="text-danger">*</span></label>
								<div class="row">
									
									<div class="col-md-6 mb-3 service-free-consultation">
										<div class="service-card-compact" style="border: 1.5px solid #dee2e6; border-radius: 8px; padding: 14px; background-color: #ffffff; cursor: pointer;" data-service-id="1">
											<div class="d-flex align-items-center">
												<input type="radio" class="services_item mt-1" name="radioGroup" value="1" id="service_1">
												<div class="ml-3 flex-grow-1 d-flex justify-content-between align-items-center">
													<div>
														<h6 class="mb-1 font-weight-bold" style="color: #212529; font-size: 15px;">Free Consultation</h6>
														<small class="service-duration-label" data-default-minutes="15" style="color: #6c757d; font-size: 13px;">15 minutes</small>
													</div>
													<span class="badge badge-success font-weight-bold ml-2" style="white-space: nowrap; padding: 6px 10px; font-size: 13px;">Free</span>
												</div>
											</div>
										</div>
									</div>

										<div class="col-md-6 mb-3 service-comprehensive-advice">
											<div class="service-card-compact" style="border: 1.5px solid #dee2e6; border-radius: 8px; padding: 14px; background-color: #ffffff; cursor: pointer;" data-service-id="2">
												<div class="d-flex align-items-center">
													<input type="radio" class="services_item mt-1" name="radioGroup" value="2" id="service_2">
													<div class="ml-3 flex-grow-1 d-flex justify-content-between align-items-center">
														<div>
															<h6 class="mb-1 font-weight-bold" style="color: #212529; font-size: 15px;">Comprehensive Migration Advice</h6>
															<small class="service-duration-label" data-default-minutes="30" style="color: #6c757d; font-size: 13px;">30 minutes</small>
														</div>
														<span class="badge badge-success font-weight-bold ml-2" style="white-space: nowrap; padding: 6px 10px; font-size: 13px;">$150</span>
													</div>
												</div>
											</div>
										</div>


										<div class="col-md-6 mb-3 service-overseas-enquiry">
											<div class="service-card-compact" style="border: 1.5px solid #dee2e6; border-radius: 8px; padding: 14px; background-color: #ffffff; cursor: pointer;" data-service-id="3">
												<div class="d-flex align-items-center">
													<input type="radio" class="services_item mt-1" name="radioGroup" value="3" id="service_3">
													<div class="ml-3 flex-grow-1 d-flex justify-content-between align-items-center">
														<div>
															<h6 class="mb-1 font-weight-bold" style="color: #212529; font-size: 15px;">Overseas Applicant Enquiry</h6>
															<small class="service-duration-label" data-default-minutes="30" style="color: #6c757d; font-size: 13px;">30 minutes</small>
														</div>
														<span class="badge badge-success font-weight-bold ml-2" style="white-space: nowrap; padding: 6px 10px; font-size: 13px;">$150</span>
													</div>
												</div>
											</div>
										</div>
									
								</div>
                                <input type="hidden" id="service_id" name="service_id" value="">
                                <input type="hidden" id="api_duration_minutes" name="api_duration_minutes" value="">
                            </div>
						</div>

                        <div class="col-12 col-md-12 col-lg-12 appointment_row" id="appointment_details" style="display: none;">
                            <div class="form-group inperson_address_cls">
                                <label for="inperson_address" class="heading_title">Location</label>
                                <div class="inperson_address_header" id="inperson_address_1">
                                    <label class="inperson_address_title">
                                        <input type="radio" class="inperson_address" name="inperson_address" data-val="1" value="1">
                                        <div class="inperson_address_title_span">
                                            ADELAIDE<br/><span style="font-size: 10px;">(Unit 5 5/55 Gawler Pl, Adelaide SA 5000)</span>
                                        </div>
                                    </label>

                                    <label class="inperson_address_title">
                                        <input type="radio" class="inperson_address" name="inperson_address" data-val="2" value="2">
                                        <div class="inperson_address_title_span">
                                            MELBOURNE<br/><span style="font-size: 10px;">(Next to Flight Center, Level 8/278 Collins St, Melbourne VIC 3000, Australia)</span>
                                        </div>
                                    </label>
                                </div>

                                <style>
                                    .inperson_address_header {
                                        display: flex;
                                        align-items: center;
                                        gap: 20px; /* Adjust spacing between radio options */
                                        flex-wrap: nowrap; /* Ensures everything stays in one line */
                                    }

                                    .inperson_address_title {
                                        display: flex;
                                        align-items: center;
                                        gap: 8px; /* Space between radio button and text */
                                        white-space: nowrap; /* Prevents text from breaking into multiple lines */
                                    }

                                    .inperson_address_title_span {
                                        display: inline-block;
                                        color: #828F9A;
                                    }
                                    /* Mobile Devices: Stack items vertically */
                                    @media (max-width: 768px) {
                                        .inperson_address_header {
                                            display: inline;
                                        }
                                    }
                                </style>
                            </div>

                            <div class="form-group row align-items-center appointment_details_cls" style="display: none;">
                                <div class="col-12 col-md-6 col-lg-6">
                                    <label for="appointment_details" class="heading_title">Appointment details <span class="span_req">*</span></label>
                                    <select class="form-control appointment_item" name="appointment_details" data-valid="required">
                                        <option value="">Select</option>
                                        <option value="phone"> Phone Call</option>
                                        <option value="in_person">In person</option>
                                        <option value="video_call" id="video_call_option" style="display: none;">Video Call/Zoom</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-6 col-lg-6">
                                    <label for="preferred_language" class="heading_title">Preferred Language <span class="span_req">*</span></label>
                                    <select class="form-control preferred_language" name="preferred_language" data-valid="required">
                                        <option value="">Select</option>
                                        <option value="Hindi"> Hindi</option>
                                        <option value="English">English</option>
                                        <option value="Punjabi">Punjabi</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12 row info_row" id="info" style="display: none;">
							<div class="tab_body">
                                <div class="row">
									<div class="col-12 col-md-12 col-lg-12">
                                        <div class="form-group row align-items-center">
                                            <label for="description" class="col-sm-3 col-form-label">Details Of Enquiry <span class="span_req">*</span></label>
                                            <div class="col-sm-9">
                                                <textarea class="form-control description" placeholder="Enter Details Of Enquiry" name="description" data-valid="required"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-12 col-lg-12">
                                        <!-- Date & Time Label at Top -->
                                        <div class="form-group">
                                            <label for="description" class="font-weight-bold text-dark mb-3" style="font-size: 16px;">
                                                @icon('fa-calendar-clock', ['class' => 'mr-2', 'style' => 'color: #667eea;'])
                                                Date & Time <span class="span_req">*</span>
                                            </label>

                                            <!-- Modern DateTime Container (Wider) -->
                                            <div class="modern-datetime-container-wrapper">
                                                <div class="modern-datetime-container">
                                                    <div class="datetime-content">
                                                        <div class="calendar-section">
                                                            <div class="section-header">
                                                                @icon('fa-calendar-check')
                                                                <span>Select Date</span>
                                                            </div>
                                                            <div class="calendar-wrapper">
                                                                <div id='datetimepicker' class="datePickerCls"></div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="timeslot-section">
                                                            <div class="section-header">
                                                                @icon('fa-clock')
                                                                <span>Available Time Slots</span>
                                                            </div>
                                                            <div class="timeslot-wrapper">
                                                                <!-- Hidden old container for existing JS -->
                                                                <div class="showselecteddate" style="display: none;"></div>
                                                                <div class="timeslots" style="display: none;"></div>
                                                                
                                                                <!-- New Modern UI -->
                                                                <div class="selected-date-display">
                                                                    <div class="date-icon">
                                                                        @icon('fa-calendar-day')
                                                                    </div>
                                                                    <div class="date-info">
                                                                        <div class="modern-selected-date">Select a date</div>
                                                                        <div class="modern-selected-day">from the calendar</div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="timeslots-grid">
                                                                    <!-- Time slots will be populated here -->
                                                                </div>
                                                                
                                                                <div class="no-slots-message" style="display: none;">
                                                                    <div class="no-slots-icon">
                                                                        @icon('fa-calendar-times')
                                                                    </div>
                                                                    <div class="no-slots-text">
                                                                        <h6>No Available Slots</h6>
                                                                        <p>Please select another date</p>
                                                                    </div>
                                                    </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Slot Overwrite at Bottom -->
                                            <div class="slot-overwrite-section mt-3">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" name="slot_overwrite" id="slot_overwrite" value="0">
                                                    <label class="custom-control-label" for="slot_overwrite">
                                                        @icon('fa-unlock-alt', ['class' => 'mr-2'])Slot Overwrite
                                                    </label>
                                                    <input type="hidden" name="slot_overwrite_hidden" id="slot_overwrite_hidden" value="0">
                                                </div>
                                                <div class="custom-control custom-checkbox mt-2">
                                                    <input type="checkbox" class="custom-control-input" name="send_confirmation_email" id="send_confirmation_email" value="1" checked>
                                                    <label class="custom-control-label" for="send_confirmation_email">
                                                        @icon('fa-envelope', ['class' => 'mr-2'])Send confirmation email to client
                                                    </label>
                                                </div>
                                            </div>

                                                    <div class="slotTimeOverwriteDivCls" style="display: none;">
														<?php
                                                        if (!function_exists('generateTimeDropdown')) {
                                                            // Slot Overwrite Select Time only — does not affect API/available slots.
                                                            function generateTimeDropdown($interval = 20) {
                                                                $start = new DateTime('00:00');
                                                                $end = (new DateTime('00:00'))->modify('+1 day'); // Include last slot (e.g. 11:40 PM - 12:00 AM)

                                                                $intervalDuration = new DateInterval('PT' . $interval . 'M');
                                                                $times = new DatePeriod($start, $intervalDuration, $end);

                                                                echo '<select class="slot_overwrite_time_dropdown" style="margin-left: 50px;margin-top: 50px;">';
                                                                echo '<option value="">Select Time</option>';
                                                                foreach ($times as $time) {
                                                                    // Calculate the end time for each option
                                                                    $endTime = clone $time;
                                                                    $endTime->add($intervalDuration);

                                                                    // Format both start and end times for display
                                                                    echo '<option value="' . $time->format('g:i A') . ' - ' . $endTime->format('g:i A') . '">';
                                                                    echo $time->format('g:i A') . ' - ' . $endTime->format('g:i A');
                                                                    echo '</option>';

                                                                    //echo '<option value="' . $time->format('g:i A') . '">' . $time->format('g:i A') . '</option>';
                                                                }
                                                                echo '</select>';
                                                            }
                                                        }

                                                        generateTimeDropdown(20); // Slot Overwrite: 20-minute intervals
                                                        ?>
                                                    </div>

                                                <input type="hidden"  id="timeslot_col_date" name="appoint_date" value=""  >
                                                <input type="hidden"  id="timeslot_col_time" name="appoint_time" value=""  >
                                                <span class="timeslot_col_date_time" role="alert" style="display: none;color:#f00;">Date and Time is required.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-md-12 col-lg-12 text-right pt-3" style="border-top: 1px solid #e9ecef;">
							<button onclick="customValidate('appointform')" type="button" class="btn btn-primary btn-lg px-4" id="appointform_save">
								@icon('fa-calendar-check', ['class' => 'mr-2'])Schedule Appointment
							</button>
							<button type="button" class="btn btn-outline-secondary btn-lg px-4 ml-2" data-bs-dismiss="modal">
								@icon('fa-times', ['class' => 'mr-2'])Cancel
							</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<style>
/* Appointment Modal - Enhanced Design */
.add_appointment .modal-content {
	border-radius: 8px;
	box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.add_appointment .modal-body {
	padding: 24px;
	background-color: #ffffff;
}

.add_appointment .form-group {
	margin-bottom: 18px;
}

.add_appointment .form-control {
	border: 1.5px solid #ced4da;
	border-radius: 6px;
	padding: 10px 14px;
	color: #212529;
	background-color: #ffffff;
	transition: all 0.2s ease;
}

.add_appointment .form-control:focus {
	border-color: #0056b3;
	box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.15);
	outline: none;
}

.add_appointment .form-control::placeholder {
	color: #6c757d;
}

/* Service Cards */
.service-card-compact {
	min-height: 65px;
	transition: all 0.2s ease;
}

.service-card-compact:hover {
	border-color: #0056b3 !important;
	box-shadow: 0 2px 8px rgba(0, 86, 179, 0.15);
	transform: translateY(-1px);
}

.service-card-compact.selected {
	border-color: #0056b3 !important;
	border-width: 2px;
	background-color: #e7f3ff;
	box-shadow: 0 2px 6px rgba(0, 86, 179, 0.2);
}

/* Location Selection */
.inperson_address_title {
	padding: 12px 18px;
	border: 2px solid #dee2e6;
	border-radius: 6px;
	margin-right: 12px;
	cursor: pointer;
	transition: all 0.2s ease;
	background-color: #ffffff;
}

.inperson_address_title:hover {
	border-color: #0056b3;
	background-color: #f8f9fa;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.inperson_address:checked + .inperson_address_title_span {
	color: #0056b3;
	font-weight: 600;
}

/* Form Labels */
.add_appointment .col-form-label {
	font-weight: 600;
	color: #343a40;
	font-size: 14px;
}

.add_appointment .heading_title {
	font-weight: 700;
	color: #212529;
	font-size: 15px;
	margin-bottom: 10px;
	display: block;
}

.add_appointment .span_req {
	color: #dc3545;
	font-weight: 700;
}

/* DateTime Container */
.modern-datetime-container {
	border: 1.5px solid #dee2e6;
	border-radius: 8px;
	background: #ffffff;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.datetime-content {
	display: flex;
	min-height: 320px;
}

.calendar-section {
	flex: 0 0 45%;
	padding: 18px;
	border-right: 1.5px solid #e9ecef;
	background-color: #fafbfc;
}

.timeslot-section {
	flex: 0 0 55%;
	padding: 18px;
	background-color: #ffffff;
}

.section-header {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-bottom: 14px;
	padding-bottom: 10px;
	border-bottom: 2px solid #e9ecef;
}

.section-header i {
	color: #0056b3;
	font-size: 16px;
}

.section-header span {
	font-weight: 700;
	font-size: 15px;
	color: #212529;
	letter-spacing: 0.3px;
}

.calendar-wrapper {
	padding: 12px;
	min-height: 260px;
}

/* Calendar Styling - Flatpickr inline */
.calendar-wrapper .flatpickr-calendar {
	border: none;
	background: transparent;
	box-shadow: none;
	padding: 0;
	width: 100%;
}

.calendar-wrapper .flatpickr-weekdays,
.calendar-wrapper span.flatpickr-weekday {
	background: #0056b3;
	color: #ffffff;
	font-weight: 700;
	font-size: 13px;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.calendar-wrapper .flatpickr-day {
	padding: 8px 5px;
	text-align: center;
	cursor: pointer;
	border-radius: 6px;
	color: #212529;
	font-weight: 500;
	transition: all 0.2s ease;
	height: auto;
	line-height: 1.6;
	max-width: none;
}

.calendar-wrapper .flatpickr-day:hover {
	background: #cfe2ff;
	color: #004085;
	font-weight: 600;
	border-color: #cfe2ff;
}

.calendar-wrapper .flatpickr-day.selected,
.calendar-wrapper .flatpickr-day.selected:hover {
	background: #0056b3;
	color: #ffffff;
	font-weight: 700;
	box-shadow: 0 2px 4px rgba(0, 86, 179, 0.3);
	border-color: #0056b3;
}

.calendar-wrapper .flatpickr-day.disabled,
.calendar-wrapper .flatpickr-day.flatpickr-disabled {
	color: #adb5bd;
	background-color: #f8f9fa;
	cursor: not-allowed;
	opacity: 0.6;
}

.calendar-wrapper .flatpickr-day.disabled:hover,
.calendar-wrapper .flatpickr-day.flatpickr-disabled:hover {
	background-color: #f8f9fa;
	color: #adb5bd;
}

.calendar-wrapper .flatpickr-prev-month,
.calendar-wrapper .flatpickr-next-month {
	cursor: pointer;
	padding: 6px 10px;
	color: #0056b3;
	font-weight: 700;
	border-radius: 4px;
	transition: all 0.2s ease;
}

.calendar-wrapper .flatpickr-prev-month:hover,
.calendar-wrapper .flatpickr-next-month:hover {
	background: #e7f3ff;
	color: #004085;
}

/* Timeslots */
.timeslot-wrapper {
	min-height: 260px;
	position: relative;
}

.selected-date-display {
	background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
	color: #ffffff;
	padding: 12px 16px;
	border-radius: 6px;
	margin-bottom: 14px;
	display: flex;
	align-items: center;
	gap: 10px;
	box-shadow: 0 2px 4px rgba(0, 86, 179, 0.2);
}

.date-icon {
	width: 28px;
	height: 28px;
	font-size: 14px;
	background: rgba(255, 255, 255, 0.2);
	border-radius: 4px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.date-info .modern-selected-date {
	font-weight: 700;
	font-size: 15px;
	color: #ffffff;
}

.date-info .modern-selected-day {
	font-size: 13px;
	color: rgba(255, 255, 255, 0.9);
}

.timeslots-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 10px;
	max-height: 220px;
	overflow-y: auto;
	padding: 4px;
}

.timeslots-grid::-webkit-scrollbar {
	width: 6px;
}

.timeslots-grid::-webkit-scrollbar-track {
	background: #f1f3f5;
	border-radius: 3px;
}

.timeslots-grid::-webkit-scrollbar-thumb {
	background: #adb5bd;
	border-radius: 3px;
}

.timeslots-grid::-webkit-scrollbar-thumb:hover {
	background: #868e96;
}

.timeslots-grid .timeslot {
	border: 1.5px solid #dee2e6;
	border-radius: 6px;
	padding: 10px 12px;
	text-align: center;
	cursor: pointer;
	font-size: 13px;
	font-weight: 500;
	color: #212529;
	background-color: #ffffff;
	transition: all 0.2s ease;
}

.timeslots-grid .timeslot:hover {
	border-color: #0056b3;
	background: #e7f3ff;
	color: #004085;
	font-weight: 600;
	transform: translateY(-1px);
	box-shadow: 0 2px 4px rgba(0, 86, 179, 0.15);
}

.timeslots-grid .timeslot.selected {
	border-color: #0056b3;
	background: #0056b3;
	color: #ffffff;
	font-weight: 700;
	box-shadow: 0 2px 6px rgba(0, 86, 179, 0.3);
}

.timeslots-grid .timeslot.disabled {
	color: #adb5bd;
	background-color: #f8f9fa;
	border-color: #e9ecef;
	cursor: not-allowed;
	opacity: 0.6;
}

.timeslots-grid .timeslot.disabled:hover {
	transform: none;
	box-shadow: none;
	border-color: #e9ecef;
	background-color: #f8f9fa;
	color: #adb5bd;
}

.no-slots-message {
	text-align: center;
	padding: 40px 24px;
	display: none;
	border: 2px dashed #dee2e6;
	border-radius: 8px;
	background-color: #f8f9fa;
}

.no-slots-icon {
	color: #868e96;
	font-size: 40px;
	margin-bottom: 12px;
}

.no-slots-text h6 {
	color: #495057;
	font-weight: 700;
	margin-bottom: 6px;
	font-size: 16px;
}

.no-slots-text p {
	color: #6c757d;
	font-size: 14px;
}

.timeslot-wrapper .no-slots-message {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: calc(100% - 24px);
}

/* Slot Overwrite */
.slot-overwrite-section {
	padding: 12px 14px;
	background: #f8f9fa;
	border: 1.5px solid #dee2e6;
	border-radius: 6px;
	margin-top: 14px;
}

.slot-overwrite-section .custom-control-label {
	cursor: pointer;
	color: #495057;
	font-weight: 500;
}

.slot-overwrite-section .custom-control-label i {
	color: #0056b3;
	margin-right: 6px;
}

.slot-overwrite-section .custom-control-input:checked ~ .custom-control-label {
	color: #0056b3;
	font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
	.datetime-content {
		flex-direction: column;
	}
	
	.calendar-section {
		border-right: none;
		border-bottom: 1.5px solid #e9ecef;
	}
	
	.timeslots-grid {
		grid-template-columns: repeat(2, 1fr);
	}
	
	.inperson_address_header {
		flex-direction: column;
	}
	
	.inperson_address_title {
		margin-right: 0;
		margin-bottom: 12px;
	}
	
	.add_appointment .modal-body {
		padding: 18px;
	}
}
</style>

<script>
// Service selection functionality
function selectService(serviceId) {
	// Remove selected class from all cards
	document.querySelectorAll('.service-card-compact').forEach(card => {
		card.classList.remove('selected');
	});
	
	// Add selected class to clicked card
	event.currentTarget.classList.add('selected');
	
	// Check the radio button
	document.getElementById('service_' + serviceId).checked = true;
	document.getElementById('service_id').value = serviceId;
	
	// Show appointment details section
	if (typeof $ !== 'undefined') {
	$('#appointment_details').show();
	} else {
		document.getElementById('appointment_details').style.display = 'block';
	}
}

// Function to toggle Video Call option visibility based on service selection
function toggleVideoCallOption(serviceId) {
	const videoCallOption = document.getElementById('video_call_option');
	const appointmentDetailsSelect = document.querySelector('.appointment_item');
	
	if (videoCallOption && appointmentDetailsSelect) {
		// Service ID 1 = Free Consultation - hide Video Call
		// Service ID 2 = Comprehensive Migration Advice - show Video Call
		// Service ID 3 = Overseas Applicant Enquiry - show Video Call
		if (serviceId == '1') {
			// Hide Video Call option for Free Consultation
			videoCallOption.style.display = 'none';
			// If Video Call is currently selected, reset to empty
			if (appointmentDetailsSelect.value === 'video_call') {
				appointmentDetailsSelect.value = '';
			}
		} else if (serviceId == '2' || serviceId == '3') {
			// Show Video Call option for Comprehensive Migration Advice and Overseas Applicant Enquiry
			videoCallOption.style.display = 'block';
		}
	}
}

/**
 * NOE 6 & 7: only Comprehensive Migration Advice (paid). NOE 8: hide Free only (existing).
 */
function applyServiceVisibilityForNatureOfEnquiry(selectedValue) {
	const freeWrap = document.querySelector('.service-free-consultation');
	const paidWrap = document.querySelector('.service-comprehensive-advice');
	const overseasWrap = document.querySelector('.service-overseas-enquiry');
	if (!freeWrap || !paidWrap || !overseasWrap) {
		return;
	}

	const showWrap = function (el) {
		el.style.display = '';
	};
	const hideWrap = function (el) {
		el.style.display = 'none';
	};

	if (selectedValue === '6' || selectedValue === '7') {
		hideWrap(freeWrap);
		showWrap(paidWrap);
		hideWrap(overseasWrap);
		var r1 = document.getElementById('service_1');
		var r2 = document.getElementById('service_2');
		var r3 = document.getElementById('service_3');
		if (r1) {
			r1.checked = false;
		}
		if (r3) {
			r3.checked = false;
		}
		if (r2) {
			var prevServiceId = document.getElementById('service_id').value;
			r2.checked = true;
			document.getElementById('service_id').value = '2';
			toggleVideoCallOption('2');
			if (prevServiceId !== '2' && typeof $ !== 'undefined') {
				$(r2).trigger('change');
			}
		}
		document.querySelectorAll('#create_appoint .service-card-compact').forEach(function (c) {
			c.classList.remove('selected');
		});
		var paidCard = paidWrap.querySelector('.service-card-compact');
		if (paidCard) {
			paidCard.classList.add('selected');
		}
		if (typeof $ !== 'undefined') {
			$('#appointment_details').show();
		} else {
			document.getElementById('appointment_details').style.display = 'block';
		}
		return;
	}

	if (selectedValue === '8') {
		hideWrap(freeWrap);
		showWrap(paidWrap);
		showWrap(overseasWrap);
		var freeConsultationRadio = document.getElementById('service_1');
		if (freeConsultationRadio && freeConsultationRadio.checked) {
			freeConsultationRadio.checked = false;
			document.getElementById('service_id').value = '';
			var freeConsultationCard = freeWrap.querySelector('.service-card-compact');
			if (freeConsultationCard) {
				freeConsultationCard.classList.remove('selected');
			}
			document.getElementById('appointment_details').style.display = 'none';
			document.getElementById('info').style.display = 'none';
		}
		return;
	}

	showWrap(freeWrap);
	showWrap(paidWrap);
	showWrap(overseasWrap);
}

// Auto-select radio when card is clicked using event delegation
document.addEventListener('DOMContentLoaded', function() {
	// Initialize Video Call option as hidden when modal opens
	$(document).on('shown.bs.modal', '#create_appoint', function() {
		const videoCallOption = document.getElementById('video_call_option');
		if (videoCallOption) {
			videoCallOption.style.display = 'none';
		}
		// Reset appointment details dropdown
		const appointmentDetailsSelect = document.querySelector('.appointment_item');
		if (appointmentDetailsSelect) {
			appointmentDetailsSelect.value = '';
		}
		// Reset Nature of Enquiry and show all services by default
		const enquirySelect = document.querySelector('.enquiry_item');
		if (enquirySelect) {
			enquirySelect.value = '';
		}
		// Show all service cards (undo NOE 6/7/8 visibility) when modal opens
		const freeConsultationService = document.querySelector('.service-free-consultation');
		const paidServiceWrap = document.querySelector('.service-comprehensive-advice');
		const overseasServiceWrap = document.querySelector('.service-overseas-enquiry');
		if (freeConsultationService) {
			freeConsultationService.style.display = 'block';
		}
		if (paidServiceWrap) {
			paidServiceWrap.style.display = '';
		}
		if (overseasServiceWrap) {
			overseasServiceWrap.style.display = '';
		}
		// Reset service selection
		document.querySelectorAll('.services_item').forEach(radio => {
			radio.checked = false;
		});
		document.getElementById('service_id').value = '';
		// Remove selected class from all service cards
		document.querySelectorAll('.service-card-compact').forEach(card => {
			card.classList.remove('selected');
		});
		// Reset "Send confirmation email" checkbox to checked (default)
		const sendConfirmationCheckbox = document.getElementById('send_confirmation_email');
		if (sendConfirmationCheckbox) {
			sendConfirmationCheckbox.checked = true;
		}
		// Restore static duration fallbacks until getDateTimeBackend returns
		document.querySelectorAll('.service-duration-label').forEach(function (el) {
			var def = parseInt(el.getAttribute('data-default-minutes'), 10);
			if (def > 0) {
				el.textContent = def + ' minutes';
			}
		});
		var apiDurationInput = document.getElementById('api_duration_minutes');
		if (apiDurationInput) {
			apiDurationInput.value = '';
		}
		// Hide services and appointment sections
		document.getElementById('services').style.display = 'none';
		document.getElementById('appointment_details').style.display = 'none';
		document.getElementById('info').style.display = 'none';
	});
	
	// Reset form when modal is hidden
	$(document).on('hidden.bs.modal', '#create_appoint', function() {
		const freeConsultationService = document.querySelector('.service-free-consultation');
		const paidServiceWrap = document.querySelector('.service-comprehensive-advice');
		const overseasServiceWrap = document.querySelector('.service-overseas-enquiry');
		if (freeConsultationService) {
			freeConsultationService.style.display = 'block';
		}
		if (paidServiceWrap) {
			paidServiceWrap.style.display = '';
		}
		if (overseasServiceWrap) {
			overseasServiceWrap.style.display = '';
		}
	});
	
	// Use event delegation to handle clicks on service cards
	document.addEventListener('click', function(e) {
		if (e.target.closest('.service-card-compact')) {
			const card = e.target.closest('.service-card-compact');
			const serviceId = card.getAttribute('data-service-id');
			
			if (serviceId) {
				// Remove selected class from all cards
				document.querySelectorAll('.service-card-compact').forEach(c => {
					c.classList.remove('selected');
				});
				
				// Add selected class to clicked card
				card.classList.add('selected');
				
				const radio = card.querySelector('input[type="radio"]');
				if (radio) {
					const prevServiceId = document.getElementById('service_id').value;
					radio.checked = true;
					if (prevServiceId !== serviceId && typeof $ !== 'undefined') {
						$(radio).trigger('change');
					} else {
						document.getElementById('service_id').value = serviceId;
						toggleVideoCallOption(serviceId);
						if (typeof $ !== 'undefined') {
							$('#appointment_details').show();
							$('.appointment_row').show();
						} else {
							document.getElementById('appointment_details').style.display = 'block';
						}
					}
				}
			}
		}
	});

	// Handle Nature of Enquiry selection
	document.addEventListener('change', function(e) {
		if (e.target.classList.contains('enquiry_item')) {
			var selectedValue = e.target.value;
			if (selectedValue) {
				document.getElementById('services').style.display = 'block';
				applyServiceVisibilityForNatureOfEnquiry(selectedValue);
			} else {
				document.getElementById('services').style.display = 'none';
				document.getElementById('appointment_details').style.display = 'none';
				document.getElementById('info').style.display = 'none';
			}
		}
		
		if (e.target.classList.contains('services_item')) {
			if (e.target.checked) {
				const serviceId = e.target.value;
				// Toggle Video Call option visibility based on service
				toggleVideoCallOption(serviceId);
				document.getElementById('appointment_details').style.display = 'block';
			}
		}
		
		if (e.target.classList.contains('appointment_item')) {
			var selectedValue = e.target.value;
		if (selectedValue) {
				document.getElementById('info').style.display = 'block';
		} else {
				document.getElementById('info').style.display = 'none';
			}
		}

		if (e.target.classList.contains('inperson_address') && !window._appointmentLangRefresh) {
			const preferredLanguageSelect = document.querySelector('.preferred_language');
			if (preferredLanguageSelect) {
				preferredLanguageSelect.value = '';
			}
		}
	});

	// Modern Appointment Booking Enhancement System
	(function() {
		'use strict';
		
		let timeslotObserver = null;
		let dateObserver = null;
		
		// Function to enhance timeslots with modern design
		function enhanceTimeslots() {
			const oldTimeslots = document.querySelector('.timeslots');
			const modernGrid = document.querySelector('.timeslots-grid');
			const noSlotsMsg = document.querySelector('.no-slots-message');
			
			if (!oldTimeslots || !modernGrid) return;
			
			// Get all timeslot_col elements (correct class name from detail-main.js)
			const oldSlots = oldTimeslots.querySelectorAll('.timeslot_col');
			
			if (oldSlots.length > 0) {
				// Clear modern grid
				modernGrid.innerHTML = '';
				modernGrid.style.display = 'grid';
				modernGrid.style.gridTemplateColumns = 'repeat(2, 1fr)';
				modernGrid.style.gap = '10px';
				if (noSlotsMsg) noSlotsMsg.style.display = 'none';
				
				// Copy each slot to modern grid
				oldSlots.forEach((oldSlot) => {
					const modernSlot = document.createElement('div');
					modernSlot.className = 'timeslot';
					
					// Extract time text
					const timeText = oldSlot.querySelector('span') ? 
						oldSlot.querySelector('span').textContent : 
						oldSlot.textContent;
					
					modernSlot.textContent = timeText;
					modernSlot.dataset.fromtime = oldSlot.dataset.fromtime;
					modernSlot.dataset.totime = oldSlot.dataset.totime;
					
					// Add click handler
					modernSlot.addEventListener('click', function() {
						// Remove selected from all modern slots
						modernGrid.querySelectorAll('.timeslot').forEach(s => 
							s.classList.remove('selected')
						);
						
						// Add selected to this slot
						this.classList.add('selected');
						
						// Click the corresponding old slot
						oldSlot.click();
					});
					
					// Check if old slot has active class
					if (oldSlot.classList.contains('active') || 
					    oldSlot.classList.contains('selected')) {
						modernSlot.classList.add('selected');
					}
					
					modernGrid.appendChild(modernSlot);
				});
			} else {
				// No slots available
				modernGrid.innerHTML = '';
				modernGrid.style.display = 'none';
				if (noSlotsMsg) noSlotsMsg.style.display = 'block';
			}
		}
		
		// Function to update modern date display
		function updateDateDisplay() {
			const oldDateDisplay = document.querySelector('.showselecteddate');
			const modernDate = document.querySelector('.modern-selected-date');
			const modernDay = document.querySelector('.modern-selected-day');
			
			if (!oldDateDisplay || !modernDate || !modernDay) return;
			
			const dateText = oldDateDisplay.textContent.trim();
			
			if (dateText) {
				modernDate.textContent = dateText;
				modernDay.textContent = 'Selected Date';
			} else {
				modernDate.textContent = 'Select a date';
				modernDay.textContent = 'from the calendar';
			}
		}
		
		// Initialize MutationObserver for timeslots
		function initTimeslotObserver() {
			const oldTimeslots = document.querySelector('.timeslots');
			if (!oldTimeslots) return;
			
			timeslotObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'childList') {
						enhanceTimeslots();
					}
				});
			});
			
			timeslotObserver.observe(oldTimeslots, {
				childList: true,
				subtree: true
			});
		}
		
		// Initialize MutationObserver for date display
		function initDateObserver() {
			const oldDateDisplay = document.querySelector('.showselecteddate');
			if (!oldDateDisplay) return;
			
			dateObserver = new MutationObserver(function(mutations) {
				mutations.forEach(function(mutation) {
					if (mutation.type === 'childList' || mutation.type === 'characterData') {
						updateDateDisplay();
					}
				});
			});
			
			dateObserver.observe(oldDateDisplay, {
				childList: true,
				characterData: true,
				subtree: true
			});
		}
		
		// Initialize everything when DOM is ready
		function init() {
			// Initial enhancement
			enhanceTimeslots();
			updateDateDisplay();
			
			// Start observing
			initTimeslotObserver();
			initDateObserver();
			
			// Also enhance on AJAX complete (catches datepicker init)
			if (typeof $ !== 'undefined') {
				$(document).ajaxSuccess(function(event, xhr, settings) {
					if (settings.url && (
						settings.url.includes('getDateTimeBackend') || 
						settings.url.includes('getDisabledDateTime')
					)) {
						setTimeout(function() {
							enhanceTimeslots();
							updateDateDisplay();
						}, 100);
					}
				});
			}
		}
		
		// Start when DOM is ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}
		
		// Also try init after a short delay (for modal load)
		setTimeout(init, 500);
		
	})();
});
</script>

