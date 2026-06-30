
<div class="section-body clientDetailCls">
    <form action="{{ url('clients/clientdetailsinfo') }}" method="POST" name="edit-clients" autocomplete="off" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="id" value="{{ @$fetchedData->id }}">
        <input type="hidden" name="type" value="{{ @$fetchedData->type }}">
        <div class="row">
            <div class="col-12 col-md-12 col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Client Details info</h4>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-12 col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-12 col-lg-12">
                                <div class="row">
                                    <div class="col-4 col-md-4 col-lg-4">
                                        <div class="form-group">
                                            <label for="first_name">First Name <span class="span_req">*</span></label>
                                            <input type="text" name="first_name" value="{{ @$fetchedData->first_name }}" class="form-control" data-valid="required" autocomplete="off" placeholder="">
                                            @if ($errors->has('first_name'))
                                                <span class="custom-error" role="alert">
                                                    <strong>{{ @$errors->first('first_name') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <input type="hidden" name="route" value="{{url()->previous()}}">

                                    <div class="col-4 col-md-4 col-lg-4">
                                        <div class="form-group">
                                            <label for="last_name">Last Name <span class="span_req"></span></label>
                                            <input type="text" name="last_name" value="{{ @$fetchedData->last_name }}" class="form-control" autocomplete="off" placeholder="">
                                            @if ($errors->has('last_name'))
                                                <span class="custom-error" role="alert">
                                                    <strong>{{ @$errors->first('last_name') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-4 col-md-4 col-lg-4">
                                        <div class="form-group">
                                            <label style="display:block;" for="gender">Gender <span class="span_req">*</span></label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="male" value="Male" name="gender" @if(@$fetchedData->gender == "Male") checked @endif>
                                                <label class="form-check-label" for="male">Male</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="female" value="Female" name="gender" @if(@$fetchedData->gender == "Female") checked @endif>
                                                <label class="form-check-label" for="female">Female</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" id="other" value="Other" name="gender" @if(@$fetchedData->gender == "Other") checked @endif>
                                                <label class="form-check-label" for="other">Other</label>
                                            </div>
                                            @if ($errors->has('gender'))
                                                <span class="custom-error" role="alert">
                                                    <strong>{{ @$errors->first('gender') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-3 col-md-3 col-lg-3">
                                        <div class="form-group">
                                            <label for="dob">Date of Birth</label>
                                            <div class="input-group" style="width: 90%;">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        @icon('fa-calendar-alt')
                                                    </div>
                                                </div>
                                                <?php
                                                    if(isset($fetchedData->dob) && $fetchedData->dob != '' && !empty($fetchedData->dob)){
                                                        $dob = date('d/m/Y', strtotime($fetchedData->dob));
                                                    }
                                                ?>
                                                <input type="text" name="dob" value="{{ @$dob }}" class="form-control dobdatepickers" data-valid="" autocomplete="off" placeholder="">
                                                @if ($errors->has('dob'))
                                                    <span class="custom-error" role="alert">
                                                        <strong>{{ @$errors->first('dob') }}</strong>
                                                     </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-3 col-md-3 col-lg-3">
                                        <div class="form-group" style="width: 90%;">
                                            <label for="age">Age</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text">
                                                        @icon('fa-calendar-alt')
                                                    </div>
                                                </div>
                                                <input type="text" name="age" value="{{ @$fetchedData->age }}" class="form-control" data-valid="" autocomplete="off" placeholder="">
                                                @if ($errors->has('age'))
                                                    <span class="custom-error" role="alert">
                                                        <strong>{{ @$errors->first('age') }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-3 col-md-3 col-lg-3">
                                        <div class="form-group">
                                            <label for="client_id">Client ID</label>
                                            <input type="text" name="client_id" value="{{ @$fetchedData->client_id }}" class="form-control" data-valid="" autocomplete="off" id="checkclientid" placeholder="" readonly>
                                            @if ($errors->has('client_id'))
                                                <span class="custom-error" role="alert">
                                                    <strong>{{ @$errors->first('client_id') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-3 col-md-3 col-lg-3">
                                        <div class="form-group">
                                            <label for="marital_status">Marital Status</label>
                                            <select style="padding: 0px 5px;width: 165px;" name="marital_status" id="marital-status-select" class="form-control">
                                                <option value="">Select Marital Status</option>
                                                <option value="Never Married" @if(@$fetchedData->marital_status == "Never Married" || @$fetchedData->marital_status == "Single") selected @endif>Never Married</option>
                                                <option value="Engaged" @if(@$fetchedData->marital_status == "Engaged") selected @endif>Engaged</option>
                                                <option value="Married" @if(@$fetchedData->marital_status == "Married") selected @endif>Married</option>
                                                <option value="De Facto" @if(@$fetchedData->marital_status == "De Facto" || @$fetchedData->marital_status == "Defacto" || @$fetchedData->marital_status == "De facto") selected @endif>De Facto</option>
                                                <option value="Separated" @if(@$fetchedData->marital_status == "Separated") selected @endif>Separated</option>
                                                <option value="Divorced" @if(@$fetchedData->marital_status == "Divorced") selected @endif>Divorced</option>
                                                <option value="Widowed" @if(@$fetchedData->marital_status == "Widowed") selected @endif>Widowed</option>
                                            </select>
                                            @if ($errors->has('marital_status'))
                                            <span class="custom-error" role="alert">
                                                <strong>{{ @$errors->first('marital_status') }}</strong>
                                            </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>


                                <meta name="csrf-token" content="{{ csrf_token() }}">
                                <title>Contact Fields</title>
                                <!--<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">-->
                                <style>
                                    .error-border {
                                        border: 2px solid red; /* Red border for incomplete rows */
                                    }
                                    .error-message {
                                        color: red;
                                        font-size: 0.875em;
                                        display: none; /* Hide error message by default */
                                    }
                                    .readonly {
                                        background-color: #f5f5f5; /* Light grey background for readonly fields */
                                        cursor: not-allowed;
                                    }
                                    button.btn.btn-primary.add-button {
                                        margin: 33px 9px 8px 8px;
                                    }
                                    button.btn.btn-primary {
                                        margin: 3px 2px -10px;
                                    }
                                </style>

                                @php
                                    $countriesPhoneData = \App\Models\Country::getAllWithPhoneCodes()
                                        ->map(static fn ($c) => [
                                            'id' => $c->id,
                                            'name' => $c->name,
                                            'sortname' => $c->sortname,
                                            'phonecode' => $c->phonecode,
                                        ])
                                        ->values();
                                @endphp
                                <script src="{{ asset('js/shared/phone-country-select-builder.js') }}"></script>
                                <script>
                                    window.countriesData = @json($countriesPhoneData);
                                    window.phonePopularIsoCodes = @json(config('phone.popular_countries'));
                                    window.phoneDefaultDialCode = @json(config('phone.default_country_code'));

                                    function buildCountryCodeSelectHtml(selectedDial) {
                                        var dialOpts = typeof window.buildPhoneDialCodeOptionsHtml === 'function'
                                            ? window.buildPhoneDialCodeOptionsHtml(selectedDial || window.phoneDefaultDialCode, { showPlaceholder: false })
                                            : '<option value="+61" selected>+61 (Australia)</option>';
                                        return '<select name="country_code[]" class="country-code-input">' + dialOpts + '</select>';
                                    }

                                    function freezeContactRowCountryCode($row) {
                                        var $cc = $row.find('.country-code-input');
                                        if (!$cc.length || $cc.is(':disabled')) {
                                            return;
                                        }
                                        var $wrap = $cc.closest('.country_code');
                                        if (!$wrap.find('input[type="hidden"][name="country_code[]"]').length) {
                                            $('<input type="hidden" class="country-code-frozen" name="country_code[]">')
                                                .val($cc.val() || '')
                                                .insertAfter($cc);
                                        }
                                        $cc.prop('disabled', true).removeAttr('name');
                                    }
                                </script>

                                <div id="contact-wrapper">
                                    @if(count($clientContacts) > 0)
                                        @foreach($clientContacts as $index => $contact)
                                            <div class="contact-group row mb-3" id="row-{{ $index }}">
                                                <input type="hidden" name="contact_id[]" value="{{ $contact->id }}" class="contact-id">
                                                <div class="col-sm-3">
                                                    <div class="form-group">
                                                        <label for="contact_type_{{ $index }}">Contact Type <span style="color:#ff0000;">*</span></label>
                                                        <select style="padding: 0px 5px;" name="contact_type[]" id="contact_type_{{ $index }}" class="form-control contactCls" data-valid="required" disabled>
                                                            <option value="Personal" {{ $contact->contact_type == 'Personal' ? 'selected' : '' }}>Personal</option>
                                                            <option value="Office" {{ $contact->contact_type == 'Office' ? 'selected' : '' }}>Office</option>
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="contact_type_hidden[]" id="contact_type_hidden_{{ $index }}" value="">
                                                </div>
                                                <div class="col-sm-3">
                                                    <div class="form-group">
                                                        <label for="phone_{{ $index }}">Contact No.<span style="color:#ff0000;">*</span></label>
                                                        <div class="cus_field_input">
                                                            <div class="country_code">
                                                                @include('partials.country-code-select', [
                                                                    'name' => 'country_code[]',
                                                                    'selected' => $contact->country_code,
                                                                    'selectClass' => 'country-code-input',
                                                                    'showPlaceholder' => false,
                                                                    'disabled' => true,
                                                                ])
                                                            </div>
                                                            <input type="text" name="phone[]" value="{{ $contact->phone }}" class="form-control tel_input" id="phone_{{ $index }}" placeholder="Enter Phone Number" autocomplete="off" readonly>
                                                            <div class="error-message" id="error-phone_{{ $index }}">Phone number must be min 10 digits.</div>
                                                            <div class="duplicate-error" id="duplicate-phone_{{ $index }}" style="display:none;color:red;">This phone number is already in use.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-3">
                                                    <button type="button" class="btn btn-primary add-button" id="add_contact_button_{{ $index }}" data-index="{{ $index }}" disabled>+</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif

                                    <script>
                                    $(document).ready(function() {
                                        var contactIndexAtPageLoad = @json(count($clientContacts));
                                        if( contactIndexAtPageLoad >0){
                                            var contactIndexByDefault = contactIndexAtPageLoad;
                                        } else {
                                            var contactIndexByDefault = 0;
                                        }

                                        var newConRowByDefault = `
                                        <div class="contact-group row mb-3" id="row-${contactIndexByDefault}">
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="contact_type_${contactIndexByDefault}">Contact Type <span style="color:#ff0000;">*</span></label>
                                                    <select style="padding: 0px 5px;" name="contact_type[]" id="contact_type_${contactIndexByDefault}" class="form-control contactCls" data-valid="required">
                                                        <option value="Personal">Personal</option>
                                                        <option value="Office">Office</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="contact_type_hidden[]" id="contact_type_hidden_${contactIndexByDefault}" value="">
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="phone_${contactIndexByDefault}">Contact No.<span style="color:#ff0000;">*</span></label>
                                                    <div class="cus_field_input">
                                                         <div class="country_code">
                                                            ${buildCountryCodeSelectHtml(window.phoneDefaultDialCode)}
                                                        </div>
                                                        <input type="text" name="phone[]" class="form-control tel_input" id="phone_${contactIndexByDefault}" placeholder="Enter Phone Number" autocomplete="off">
                                                        <div class="error-message" id="error-phone_${contactIndexByDefault}">Phone number must be min 10 digits.</div>
                                                        <div class="duplicate-error" id="duplicate-phone_${contactIndexByDefault}" style="display:none;color:red;">This phone number is already in use.</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <button type="button" class="btn btn-primary add-button" id="add_contact_button_${contactIndexByDefault}" data-index="${contactIndexByDefault}">+</button>
                                            </div>
                                        </div>`;

                                        contactIndexByDefault++;
                                        $('#contact-wrapper').append(newConRowByDefault);

                                        if( $('.contactCls').length >0 ) {
                                            $('.contactCls').each(function(index) {
                                                var contact_type_id =  $(this).attr('id');
                                                var contact_type_id_arr  = contact_type_id.split("_");
                                                var contact_type_val_per_index = $('#contact_type_'+contact_type_id_arr[2]).val();
                                                $('#contact_type_hidden_'+contact_type_id_arr[2]).val(contact_type_val_per_index);
                                            });

                                            $(document).on('change', '.contactCls', function() {
                                                var contact_type_id =  $(this).attr('id');
                                                var contact_type_id_arr  = contact_type_id.split("_");
                                                var contact_type_val_per_index = $('#contact_type_'+contact_type_id_arr[2]).val();
                                                $('#contact_type_hidden_'+contact_type_id_arr[2]).val(contact_type_val_per_index);
                                            });
                                        }
                                    });
                                    </script>

                                </div>

                                $(document).ready(function() {
                                    var contactIndex = @json(count($clientContacts)+1);
                                    //console.log('contactIndex=='+contactIndex);

                                    function addNewContactRow(afterIndex) {
                                        var newContactRow = `
                                        <div class="contact-group row mb-3" id="row-${contactIndex}">
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="contact_type_${contactIndex}">Contact Type <span style="color:#ff0000;">*</span></label>
                                                    <select style="padding: 0px 5px;" name="contact_type[]" id="contact_type_${contactIndex}" class="form-control contactCls" data-valid="required">
                                                        <option value="Personal">Personal</option>
                                                        <option value="Office">Office</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="contact_type_hidden[]" id="contact_type_hidden_${contactIndex}" value="">
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="phone_${contactIndex}">Contact No.<span style="color:#ff0000;">*</span></label>
                                                    <div class="cus_field_input">
                                                         <div class="country_code">
                                                            ${buildCountryCodeSelectHtml(window.phoneDefaultDialCode)}
                                                        </div>
                                                        <input type="text" name="phone[]" class="form-control tel_input" id="phone_${contactIndex}" placeholder="Enter Phone Number" autocomplete="off">
                                                        <div class="error-message" id="error-phone_${contactIndex}">Phone number must be min 10 digits.</div>
                                                        <div class="duplicate-error" id="duplicate-phone_${contactIndex}" style="display:none;color:red;">This phone number is already in use.</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-sm-3">
                                                <button type="button" class="btn btn-primary add-button" id="add_contact_button_${contactIndex}" data-index="${contactIndex}">+</button>
                                            </div>
                                        </div>`;

                                        $('#contact-wrapper').append(newContactRow); // Append new row at the end


                                        if( $('.contactCls').length >0 ) {
                                            $('.contactCls').each(function(index) {
                                                var contact_type_id =  $(this).attr('id');
                                                var contact_type_id_arr  = contact_type_id.split("_");
                                                var contact_type_val_per_index = $('#contact_type_'+contact_type_id_arr[2]).val();
                                                $('#contact_type_hidden_'+contact_type_id_arr[2]).val(contact_type_val_per_index);
                                            });

                                            $(document).on('change', '.contactCls', function() {
                                                var contact_type_id =  $(this).attr('id');
                                                var contact_type_id_arr  = contact_type_id.split("_");
                                                var contact_type_val_per_index = $('#contact_type_'+contact_type_id_arr[2]).val();
                                                $('#contact_type_hidden_'+contact_type_id_arr[2]).val(contact_type_val_per_index);
                                            });
                                        }
                                        contactIndex++; // Increment index for next row
                                    }

                                    function validatePhoneNumber($input) {
                                        var phone = $input.val().trim();
                                        var $row = $input.closest('.contact-group');
                                        var $error = $row.find('.error-message');
                                        
                                        // Standardized phone validation
                                        var phoneRegex = /^[0-9]{7,15}$/;
                                        
                                        if (phone.length >= 7 && phone.length <= 15 && phoneRegex.test(phone)) {
                                            $input.removeClass('error-border');
                                            $error.hide();
                                            return true;
                                        } else {
                                            $input.addClass('error-border');
                                            $error.show();
                                            return false;
                                        }
                                    }

                                    function checkPhoneNumberUniqueness(phone, $input) {
                                        var $row = $input.closest('.contact-group');
                                        var $duplicateError = $row.find('.duplicate-error');
                                        var duplicateError_id = $duplicateError.attr('id');
                                        //console.log('duplicateError_id=='+duplicateError_id);
                                        var duplicateError_id_arr = duplicateError_id.split("_");
                                        $.ajax({
                                            url: '{{ route("check.phone") }}', // Ensure route is correct
                                            method: 'POST',
                                            data: {
                                                phone: phone,
                                                _token: '{{ csrf_token() }}'
                                            },
                                            success: function(response) {
                                                if (response.status === 'exists') {
                                                    //$duplicateError.show();
                                                    //$('#'+duplicateErrorId).show();

                                                    $('#phone_'+duplicateError_id_arr[1]).attr('data-valid', 'required');
                                                    $('#duplicate-phone_'+duplicateError_id_arr[1]).show();
                                                    $('#add_contact_button_'+duplicateError_id_arr[1]).prop('disabled', true);
                                                    $input.addClass('error-border');
                                                    $('.btn_submit').prop('disabled', true);
                                                } else {
                                                    //$duplicateError.hide();
                                                    $('#duplicate-phone_'+duplicateError_id_arr[1]).hide();
                                                    $('#add_contact_button_'+duplicateError_id_arr[1]).prop('disabled', false);
                                                    $input.removeClass('error-border');
                                                    $('.btn_submit').prop('disabled', false);
                                                }
                                            },
                                            error: function() {
                                                alert('An error occurred while checking the phone number.');
                                            }
                                        });
                                    }

                                    $(document).on('blur input', 'input.tel_input', function() {
                                        var $this = $(this);
                                        if (validatePhoneNumber($this)) {
                                            checkPhoneNumberUniqueness($this.val().trim(), $this);
                                        }
                                    });

                                    $(document).on('click', '.add-button', function() {
                                        var index = $(this).data('index');
                                        //console.log('index=='+index);
                                        var $currentRow = index === 'new' ? $('#row-new') : $(`#row-${index}`);
                                        if (validatePhoneNumber($currentRow.find('input.tel_input'))) {
                                            if (index === 'new') {
                                                addNewContactRow('new'); // Add a new row at the end
                                                freezeContactRowCountryCode($currentRow);
                                                $currentRow.find('button').prop('disabled', true); // Disable current row's button
                                                $currentRow.find('input.tel_input').prop('readonly', true);
                                                $currentRow.find('select.contactCls').prop('disabled', true);

                                            } else {
                                                var isConfirmed = confirm("Are you sure you want to freeze the current row?");
                                                if (isConfirmed) {
                                                    addNewContactRow(index); // Add a new row after the current row
                                                    freezeContactRowCountryCode($currentRow);
                                                    $currentRow.find('button').prop('disabled', true); // Disable current row's button
                                                    $currentRow.find('input.tel_input').prop('readonly', true);
                                                    $currentRow.find('select.contactCls').prop('disabled', true);
                                                }
                                            }
                                        } else {
                                            alert("Please correct the errors in the current row before adding a new one.");
                                        }
                                    });
                                });
                                </script>


                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Email Fields</title>
                                <style>
                                    .error-border {
                                        border: 2px solid red; /* Red border for invalid fields */
                                    }
                                    .error-message {
                                        color: red;
                                        font-size: 0.875em;
                                        display: none; /* Hide error message by default */
                                    }
                                    .readonly {
                                        background-color: #f5f5f5; /* Light grey background for readonly fields */

                                    }
                                    button#add-email-0 {
                                        margin: 1px 0px -11px;
                                    }
                                </style>

                                <div id="email-fields-wrapper">
                                    @if(count($emails) > 0)
                                        @foreach($emails as $email_index => $email_val)
                                        <div class="email-fields row mb-3" id="row-{{ $email_index }}">
                                            <input type="hidden" name="email_id[]" value="<?php echo $email_val->id;?>">
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="email_type_{{ $email_index }}">Email Type <span style="color:#ff0000;">*</span></label>
                                                    <select style="padding: 0px 5px;" name="email_type[]" id="email_type_{{ $email_index }}" class="form-control emailTypeCls" data-valid="required" disabled>
                                                        <option value="Personal" {{ $email_val->email_type == 'Personal' ? 'selected' : '' }}>Personal</option>
                                                        <option value="Work" {{ $email_val->email_type == 'Work' ? 'selected' : '' }}>Work</option>
                                                        <option value="Business" {{ $email_val->email_type == 'Business' ? 'selected' : '' }}>Business</option>
                                                        <option value="Secondary" {{ $email_val->email_type == 'Secondary' ? 'selected' : '' }}>Secondary</option>
                                                        <option value="Additional" {{ $email_val->email_type == 'Additional' ? 'selected' : '' }}>Additional</option>
                                                        <option value="Sister" {{ $email_val->email_type == 'Sister' ? 'selected' : '' }}>Sister</option>
                                                        <option value="Brother" {{ $email_val->email_type == 'Brother' ? 'selected' : '' }}>Brother</option>
                                                        <option value="Father" {{ $email_val->email_type == 'Father' ? 'selected' : '' }}>Father</option>
                                                        <option value="Mother" {{ $email_val->email_type == 'Mother' ? 'selected' : '' }}>Mother</option>
                                                        <option value="Uncle" {{ $email_val->email_type == 'Uncle' ? 'selected' : '' }}>Uncle</option>
                                                        <option value="Auntie" {{ $email_val->email_type == 'Auntie' ? 'selected' : '' }}>Auntie</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="email_type_hidden[]" id="email_type_hidden_{{ $email_index }}" value="">
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="email_{{ $email_index }}">Email <span style="color:#ff0000;">*</span></label>
                                                    <input type="email" name="email[]" value="{{ $email_val->email }}" class="form-control email-input" id="email_{{ $email_index }}" placeholder="Enter Email" autocomplete="off" readonly>
                                                    <div class="error-message" id="error-email_{{ $email_index }}">Please enter a valid email address.</div>
                                                    <div class="duplicate-error" id="duplicate-email_{{ $email_index }}" style="display:none;color:red;">This email is already taken.</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-1 d-flex align-items-center">
                                                <button type="button" class="btn btn-primary add-email-row" id="add-email-{{ $email_index }}" data-row-id="{{ $email_index }}" disabled>+</button>
                                            </div>
                                        </div>
                                        @endforeach
                                    @endif

                                    <!-- Blank row for adding new emails -->
                                    <script>
                                    $(document).ready(function() {
                                        var emailIndexAtPageLoad = @json(count($emails));
                                        if( emailIndexAtPageLoad >0){
                                            var emailIndexByDefault = emailIndexAtPageLoad;
                                        } else {
                                            var emailIndexByDefault = 0;
                                        }

                                        var newEmailRowByDefault = `<div class="email-fields row mb-3" id="row-${emailIndexByDefault}">
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="email_type_${emailIndexByDefault}">Email Type <span style="color:#ff0000;">*</span></label>
                                                    <select style="padding: 0px 5px;" name="email_type[]" id="email_type_${emailIndexByDefault}" class="form-control emailTypeCls" data-valid="required">
                                                        <option value="Personal">Personal</option>
                                                        <option value="Work">Work</option>
                                                        <option value="Business">Business</option>
                                                        <option value="Secondary">Secondary</option>
                                                        <option value="Additional">Additional</option>
                                                        <option value="Sister">Sister</option>
                                                        <option value="Brother">Brother</option>
                                                        <option value="Father">Father</option>
                                                        <option value="Mother">Mother</option>
                                                        <option value="Uncle">Uncle</option>
                                                        <option value="Auntie">Auntie</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="email_type_hidden[]" id="email_type_hidden_${emailIndexByDefault}" value="">
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="email_${emailIndexByDefault}">Email <span style="color:#ff0000;">*</span></label>
                                                    <input type="email" name="email[]" class="form-control email-input" id="email_${emailIndexByDefault}" placeholder="Enter Email" autocomplete="off">
                                                    <div class="error-message" id="error-email_${emailIndexByDefault}">Please enter a valid email address.</div>
                                                    <div class="duplicate-error" id="duplicate-email_${emailIndexByDefault}" style="display:none;color:red;">This email is already taken.</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-1 d-flex align-items-center">
                                                <button type="button" class="btn btn-primary new-email-row-btn" id="new-email-row-${emailIndexByDefault}" data-row-id="${emailIndexByDefault}">+</button>
                                            </div>
                                        </div>`;
                                        emailIndexByDefault++;
                                        $('#email-fields-wrapper').append(newEmailRowByDefault);
                                        if( $('.emailTypeCls').length >0 ) {
                                            $('.emailTypeCls').each(function(index) {
                                                var email_type_id =  $(this).attr('id');
                                                var email_type_id_arr  = email_type_id.split("_");
                                                var email_type_val_per_index = $('#email_type_'+email_type_id_arr[2]).val();
                                                $('#email_type_hidden_'+email_type_id_arr[2]).val(email_type_val_per_index);
                                            });

                                            $(document).on('change', '.emailTypeCls', function() {
                                                var email_type_id =  $(this).attr('id');
                                                var email_type_id_arr  = email_type_id.split("_");
                                                var email_type_val_per_index = $('#email_type_'+email_type_id_arr[2]).val();
                                                $('#email_type_hidden_'+email_type_id_arr[2]).val(email_type_val_per_index);
                                            });
                                        }
                                    });
                                    </script>

                                </div>

                                <script>
                                $(document).ready(function() {
                                    var emailIndex = {{ count($emails)+1 }}; // Start index for new rows based on existing emails
                                    //console.log('emailIndex_addnew='+emailIndex)
                                    function addNewEmailRow() {
                                        var newEmailRow = `<div class="email-fields row mb-3" id="row-${emailIndex}">
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="email_type_${emailIndex}">Email Type <span style="color:#ff0000;">*</span></label>
                                                    <select style="padding: 0px 5px;" name="email_type[]" id="email_type_${emailIndex}" class="form-control emailTypeCls" data-valid="required">
                                                        <option value="Personal">Personal</option>
                                                        <option value="Work">Work</option>
                                                        <option value="Business">Business</option>
                                                        <option value="Secondary">Secondary</option>
                                                        <option value="Additional">Additional</option>
                                                        <option value="Sister">Sister</option>
                                                        <option value="Brother">Brother</option>
                                                        <option value="Father">Father</option>
                                                        <option value="Mother">Mother</option>
                                                        <option value="Uncle">Uncle</option>
                                                        <option value="Auntie">Auntie</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="email_type_hidden[]" id="email_type_hidden_${emailIndex}" value="">
                                            </div>
                                            <div class="col-sm-3">
                                                <div class="form-group">
                                                    <label for="email_${emailIndex}">Email <span style="color:#ff0000;">*</span></label>
                                                    <input type="email" name="email[]" class="form-control email-input" id="email_${emailIndex}" placeholder="Enter Email" autocomplete="off">
                                                    <div class="error-message" id="error-email_${emailIndex}">Please enter a valid email address.</div>
                                                    <div class="duplicate-error" id="duplicate-email_${emailIndex}" style="display:none;color:red;">This email is already taken.</div>
                                                </div>
                                            </div>
                                            <div class="col-sm-1 d-flex align-items-center">
                                                <button type="button" class="btn btn-primary new-email-row-btn" id="new-email-row-${emailIndex}" data-row-id="${emailIndex}">+</button>
                                            </div>
                                        </div>`;

                                        $('#email-fields-wrapper').append(newEmailRow);

                                        if( $('.emailTypeCls').length >0 ) {
                                            $('.emailTypeCls').each(function(index) {
                                                var email_type_id =  $(this).attr('id');
                                                var email_type_id_arr  = email_type_id.split("_");
                                                var email_type_val_per_index = $('#email_type_'+email_type_id_arr[2]).val();
                                                $('#email_type_hidden_'+email_type_id_arr[2]).val(email_type_val_per_index);
                                            });

                                            $(document).on('change', '.emailTypeCls', function() {
                                                var email_type_id =  $(this).attr('id');
                                                var email_type_id_arr  = email_type_id.split("_");
                                                var email_type_val_per_index = $('#email_type_'+email_type_id_arr[2]).val();
                                                $('#email_type_hidden_'+email_type_id_arr[2]).val(email_type_val_per_index);
                                            });
                                        }
                                        emailIndex++; // Increment index for next row
                                    }

                                    function validateCurrentRow() {
                                        var $currentRow = $('.email-fields:last');
                                        var email = $currentRow.find('input[name="email[]"]').val().trim();
                                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Simple email validation regex
                                        var $error = $currentRow.find('.error-message');

                                        if (!email || !emailRegex.test(email)) {
                                            $error.show();
                                            return false;
                                        }

                                        // Check for duplicate email in existing rows
                                        var isDuplicate = false;
                                        $('input[name="email[]"]').each(function() {
                                            if ($(this).val().trim() === email && $(this).attr('id') !== $currentRow.find('input[name="email[]"]').attr('id')) {
                                                isDuplicate = true;
                                                return false; // Exit loop
                                            }
                                        });
                                        if (isDuplicate) {
                                            alert('This email is already entered.');
                                            $error.show();
                                            return false;
                                        }
                                        $error.hide();
                                        return true;
                                    }

                                    function makePreviousRowsReadonly() {
                                        $('.email-fields').each(function() {
                                            if (!$(this).is('#row-new')) {
                                                //$(this).find('input, select').addClass('readonly').prop('readonly', true).attr('disabled', true);
                                                $(this).find('input').attr('readonly', true);
                                                $(this).find('select').prop('disabled', true);
                                            }
                                        });
                                    }

                                    $(document).on('click', '.new-email-row-btn', function() {
                                        if (validateCurrentRow()) {
                                            var isConfirmed = confirm("Are you sure want to Freeze current row?");
                                            if (isConfirmed) {
                                                makePreviousRowsReadonly(); // Make previous rows readonly
                                                addNewEmailRow();
                                            }
                                        } else {
                                            alert("Please correct the errors in the current row before adding a new one.");
                                        }
                                    });

                                    $(document).on('input', 'input.email-input', function() {
                                        var $currentRow = $(this).closest('.email-fields');
                                        var email = $(this).val().trim();
                                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Simple email validation regex
                                        var $error = $currentRow.find('.error-message');

                                        if (email && emailRegex.test(email)) {
                                            $error.hide();
                                        } else {
                                            $error.show();
                                        }
                                    });

                                      // Event on blur for email input
                                    $(document).on('blur input', 'input.email-input', function() {
                                        var $this = $(this);
                                        var email = $this.val().trim();
                                        var email_id_attr = $(this).attr('id');
                                        //console.log('email_id_attr='+email_id_attr);

                                        var $duplicateError = $this.siblings('.duplicate-error');
                                        var duplicateErrorId = $this.siblings('.duplicate-error').attr('id');
                                        //console.log('duplicateErrorId='+duplicateErrorId);

                                        var email_id_attr_arr = email_id_attr.split('_');
                                        //console.log('email_id_attr_arr='+email_id_attr_arr[1]);

                                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                                        // Validate email format
                                        if (email && emailRegex.test(email)) {
                                            $this.siblings('.error-message').hide();

                                            // Perform AJAX check for email uniqueness
                                            $.ajax({
                                                url: '{{ route("check.email") }}',
                                                method: 'POST',
                                                data: {
                                                    email: email,
                                                    _token: '{{ csrf_token() }}'
                                                },
                                                success: function(response) {
                                                    if (response.status === 'exists') {
                                                        //$('#duplicate-email_new').show();
                                                        $('#'+duplicateErrorId).show();
                                                        $('#'+email_id_attr).attr('data-valid', 'required');
                                                        $('#new-email-row-'+email_id_attr_arr[1]).prop('disabled', true);
                                                        $('.btn_submit').prop('disabled', true);
                                                    } else {
                                                        //$duplicateError.hide(); // Email is available
                                                        $('#'+duplicateErrorId).hide();
                                                        $('.btn_submit').prop('disabled', false);
                                                        $('#new-email-row-'+email_id_attr_arr[1]).prop('disabled', false);
                                                    }
                                                },
                                                error: function() {
                                                    alert('An error occurred while checking the email.');
                                                }
                                            });
                                        } else {
                                            $this.siblings('.error-message').show(); // Invalid email format
                                            $duplicateError.hide(); // Hide duplicate error if any
                                        }
                                    });
                                });
                                </script>
                            </div>
                        </div>


                        <div class="fields row mb-3" id="row">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="visa_country">Country of Passport <span style="color:#ff0000;">*</span></label>
                                    <select name="visa_country[]" id="visa_country" class="form-control field-input" data-valid="required">
                                        <option value="India" {{ @$fetchedData->country_passport == 'India' ? 'selected' : '' }}>India</option>
                                        <option value="Australia" {{ @$fetchedData->country_passport == 'Australia' ? 'selected' : '' }}>Australia</option>

                                        <?php
                                        foreach (\App\Models\Country::all() as $list) {
                                            // Skip India and Australia since they've already been added manually
                                            if ($list->name == 'India' || $list->name == 'Australia') {
                                                continue;
                                            }
                                            $selected = (@$fetchedData->country_passport == $list->name) ? 'selected' : '';
                                            ?>
                                            <option value="{{ $list->name }}" {{ $selected }}>{{ $list->name }}</option>
                                            <?php
                                        }
                                        ?>
                                        </select>

                                </div>
                            </div>
                        </div>

                        <meta name="csrf-token" content="{{ csrf_token() }}">
                        <title>Country and Visa Fields</title>
                        <style>
                        .error-border {
                            border: 2px solid red; /* Red border for incomplete rows */
                        }
                        .readonly {
                            background-color: #f5f5f5; /* Light grey background for readonly fields */
                            cursor: not-allowed;
                        }
                        /*.disabled-button {
                            cursor: not-allowed;
                            opacity: 0.5;
                        }*/
                        .description-container {
                            display: none; /* Initially hide the description field */
                        }
                        </style>

                        <div id="fields-wrapper">
                            <?php //echo count($visaCountries);?>
                            @if(count($visaCountries) > 0)
                                @foreach($visaCountries as $key => $visaCountry)

                                    @if($fetchedData->country_passport != 'Australia')
                                    <div class="fields row mb-3 visa-full-cu" id="row-{{ $key }}">
                                        <input type="hidden" name="visa_id[]" value="{{ $visaCountry->id }}">
                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label for="visa_type_{{ $key }}">Visa Type <span style="color:#ff0000;">*</span></label>
                                                <select name="visa_type[]" id="visa_type_{{ $key }}" class="form-control field-input visaTypeCls" data-valid="required" disabled>
                                                    <option value="">Select Visa Type</option>
                                                    <!--<option value="Student Visa" {{--$visaCountry->visa_type == 'Student Visa' ? 'selected' : ''--}}>Student Visa</option>
                                                    <option value="Work Visa" {{--$visaCountry->visa_type == 'Work Visa' ? 'selected' : ''--}}>Work Visa</option>
                                                    <option value="Tourist Visa" {{--$visaCountry->visa_type == 'Tourist Visa' ? 'selected' : ''--}}>Tourist Visa</option>-->
                                                    @php
                                                        $matterQuery = \App\Models\Matter::select('id','title','nick_name')
                                                            ->where('status',1)
                                                            ->orderby('id','ASC')
                                                            ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                                        $matterList = $matterQuery->get();
                                                    @endphp
                                                    @foreach($matterList as $matterlist)
                                                    <option value="{{ $matterlist->id }}" {{ $visaCountry->visa_type == $matterlist->id ? 'selected' : '' }}>{{ $matterlist->title }} ({{ @$matterlist->nick_name }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="hidden" name="visa_type_hidden[]" id="visa_type_hidden_{{ $key }}" value="">
                                        </div>

                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label for="visa_expiry_date_{{ $key }}">Visa Expiry Date</label>
                                                <input type="date" name="visa_expiry_date[]" id="visa_expiry_date_{{ $key }}" value="{{ $visaCountry->visa_expiry_date }}" class="form-control field-input" readonly>
                                            </div>
                                        </div>

                                        <div class="col-sm-3 description-container" id="description-container-{{ $key }}">
                                            <div class="form-group">
                                                <label for="visa_description_{{ $key }}">Visa Description</label>
                                                <input type="text" name="visa_description[]" id="visa_description_{{ $key }}"   value="{{ $visaCountry->visa_description }}" class="form-control field-input" rows="3" readonly>{{ $visaCountry->description }}
                                            </div>
                                        </div>

                                        <div class="col-sm-1 d-flex align-items-center">
                                            <button type="button" class="btn btn-primary add-row-visacountry" id="add-row-visacountry-{{ $key }}" data-row-id="{{ $key }}" disabled>+</button>
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            @endif

                            <script>
                            $(document).ready(function() {
                                var visaIndexAtPageLoad = @json(count($visaCountries));
                                if( visaIndexAtPageLoad >0){
                                    var visaIndexByDefault = visaIndexAtPageLoad;
                                } else {
                                    var visaIndexByDefault = 0;
                                }
                                //console.log('visaIndexByDefault='+visaIndexByDefault);

                                var newVisaRowByDefault = `<div class="fields row mb-3 visa-full-cu" id="row-${visaIndexByDefault}">

                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="visa_type_${visaIndexByDefault}">Visa Type <span style="color:#ff0000;">*</span></label>
                                        <select name="visa_type[]" id="visa_type_${visaIndexByDefault}" class="form-control field-input visaTypeCls" data-valid="required" >
                                            <option value="">Select Visa Type</option>
                                            <!--<option value="Student Visa">Student Visa</option>
                                            <option value="Work Visa">Work Visa</option>
                                            <option value="Tourist Visa">Tourist Visa</option>-->
                                            @php
                                                $matterQuery = \App\Models\Matter::select('id','title','nick_name')
                                                    ->where('status',1)
                                                    ->orderby('id','ASC')
                                                    ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                                $matterList = $matterQuery->get();
                                            @endphp
                                            @foreach($matterList as $matterlist)
                                            <option value="{{ $matterlist->id }}">{{ $matterlist->title }} ({{ @$matterlist->nick_name }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="hidden" name="visa_type_hidden[]" id="visa_type_hidden_${visaIndexByDefault}" value="">
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="visa_expiry_date_${visaIndexByDefault}">Visa Expiry Date</label>
                                        <input type="date" name="visa_expiry_date[]" id="visa_expiry_date_${visaIndexByDefault}" class="form-control field-input">
                                    </div>
                                </div>
                                <div class="col-sm-3 description-container" id="description-container-${visaIndexByDefault}">
                                    <div class="form-group">
                                        <label for="visa_description_${visaIndexByDefault}">Visa Description</label>
                                        <input type="text" name="visa_description[]" id="visa_description_${visaIndexByDefault}" class="form-control field-input" rows="3">
                                    </div>
                                </div>
                                <div class="col-sm-1 d-flex align-items-center">
                                    <button type="button" class="btn btn-primary add-row-visacountry" id="add-row-visacountry-${visaIndexByDefault}" data-row-id="${visaIndexByDefault}" >+</button>
                                </div>
                                </div>`;

                                visaIndexByDefault++;
                                $('#fields-wrapper').append(newVisaRowByDefault);


                                if( $('.visaTypeCls').length >0 ) {
                                    $('.visaTypeCls').each(function(index) {
                                        var visa_type_id =  $(this).attr('id');
                                        var visa_type_id_arr  = visa_type_id.split("_");
                                        var visa_type_val_per_index = $('#visa_type_'+visa_type_id_arr[2]).val();
                                        $('#visa_type_hidden_'+visa_type_id_arr[2]).val(visa_type_val_per_index);
                                    });

                                    $(document).on('change', '.visaTypeCls', function() {
                                        var visa_type_id =  $(this).attr('id');
                                        var visa_type_id_arr  = visa_type_id.split("_");
                                        var visa_type_val_per_index = $('#visa_type_'+visa_type_id_arr[2]).val();
                                        $('#visa_type_hidden_'+visa_type_id_arr[2]).val(visa_type_val_per_index);
                                    });
                                }
                            });
                            </script>
                        </div>

                        <script>
                        $(document).ready(function() {
                            function toggleFieldsBasedOnCountryAtCountryChange()
                            {
                                var selectedCountry = $('#visa_country').val(); // Get the selected country
                                //console.log('selectedCountry='+selectedCountry);
                                if (selectedCountry === 'Australia') {
                                    // Hide the div with the class 'visa-full-cu'
                                    $('.visa-full-cu').hide();
                                    if( $('.visa-full-cu').length >0 ) {
                                        $('.visa-full-cu').each(function(index) {
                                            $('#visa_type_'+index).removeAttr('data-valid');
                                        });
                                    }
                                } else {
                                    // Show the div with the class 'visa-full-cu'
                                    $('.visa-full-cu').remove();
                                    var addZeroVisaRow = `<div class="fields row mb-3 visa-full-cu" id="row-0">

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="visa_type_1">Visa Type <span style="color:#ff0000;">*</span></label>
                                            <select name="visa_type[]" id="visa_type_0" class="form-control field-input visaTypeCls" data-valid="required" >
                                                <option value="">Select Visa Type</option>
                                                <!--<option value="Student Visa">Student Visa</option>
                                                <option value="Work Visa">Work Visa</option>
                                                <option value="Tourist Visa">Tourist Visa</option>-->
                                                @php
                                                    $matterQuery = \App\Models\Matter::select('id','title','nick_name')
                                                        ->where('status',1)
                                                        ->orderby('id','ASC')
                                                        ->forClientType((bool) (isset($fetchedData) && $fetchedData->is_company));
                                                    $matterList = $matterQuery->get();
                                                @endphp
                                                @foreach($matterList as $matterlist)
                                                <option value="{{ $matterlist->id }}">{{ $matterlist->title }} ({{ @$matterlist->nick_name }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input type="hidden" name="visa_type_hidden[]" id="visa_type_hidden_0" value="">
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="visa_expiry_date_0">Visa Expiry Date</label>
                                            <input type="date" name="visa_expiry_date[]" id="visa_expiry_date_0" class="form-control field-input">
                                        </div>
                                    </div>
                                    <div class="col-sm-3 description-container" id="description-container-0">
                                        <div class="form-group">
                                            <label for="visa_description_0">Visa Description</label>
                                            <input type="text" name="visa_description[]" id="visa_description_0" class="form-control field-input" rows="3">
                                        </div>
                                    </div>
                                    <div class="col-sm-1 d-flex align-items-center">
                                        <button type="button" class="btn btn-primary add-row-visacountry" id="add-row-visacountry-0" data-row-id="0" >+</button>
                                    </div>
                                    </div>`;
                                    $('#fields-wrapper').append(addZeroVisaRow);
                                }
                            }

                            // Trigger when the country dropdown changes
                            $('#visa_country').on('change', function() {
                                toggleFieldsBasedOnCountryAtCountryChange(); // Check the selected country and toggle visibility accordingly
                            });

                            // Initial check on page load
                            //toggleFieldsBasedOnCountryOnPageLoad(); // Check on page load
                        });

                        $(document).ready(function() {
                            //var rowIndex = {{ $visaCountries->count() ? $visaCountries->count() : 1 }}; // Start index for new rows
                            var rowIndex = @json(count($visaCountries)+1);
                            //console.log('rowIndex='+rowIndex);
                            // Function to check if all fields in a row are filled
                            function validateRow(rowId) {
                                var isValid = true;
                                $('#row-' + rowId + ' .field-input').each(function() {
                                    if ($(this).val() === "" && $(this).attr('data-valid') === "required") {
                                        isValid = false;
                                        return false; // Exit each loop if any field is empty and required
                                    }
                                });
                                return isValid;
                            }

                            // Enable or disable the + button based on row validation
                            function toggleAddButton(rowId) {
                                var selectedCountry = $('#visa_country').val();
                                if (validateRow(rowId) && selectedCountry !== 'Australia') {
                                    $('#add-row-visacountry-' + rowId).removeClass('disabled-button').prop('disabled', false);
                                } else {
                                    $('#add-row-visacountry-' + rowId).addClass('disabled-button').prop('disabled', true);
                                }
                            }

                            // Show the description field based on Visa Type selection
                            function toggleDescriptionField(rowId) {
                                var visaType = $('#visa_type_' + rowId).val();
                                if (visaType) { // Show the textarea if any visa type is selected
                                    $('#description-container-' + rowId).show();
                                } else {
                                    $('#description-container-' + rowId).hide();
                                }
                            }

                            // Function to disable/enable fields based on country selection
                            function toggleFields(rowId) {
                                var selectedCountry = $('#visa_country').val();
                                if (selectedCountry === 'Australia') {
                                    $('#row-' + rowId + ' .field-input').attr('disabled', true).addClass('readonly');
                                    $('#add-row-visacountry-' + rowId).addClass('disabled-button').prop('disabled', true);
                                } else {
                                    $('#row-' + rowId + ' .field-input').attr('disabled', false).removeClass('readonly');
                                    toggleAddButton(rowId); // Re-evaluate the add button
                                }
                            }

                            // Validate and enable the + button when fields change
                            $(document).on('change', '.field-input, #visa_country', function() {
                                var rowId = $(this).closest('.fields').attr('id').split('-')[1];
                                toggleFields(rowId); // Check if fields need to be disabled
                                toggleAddButton(rowId);
                                toggleDescriptionField(rowId);
                            });

                            // Handle click event for adding a new row
                            $(document).on('click', '.add-row-visacountry', function() {
                                var currentRowId = $(this).data('row-id');
                                var selectedCountry = $('#visa_country').val();

                                if (validateRow(currentRowId) && selectedCountry !== 'Australia') {
                                    var newVisaRow = `<div class="fields row mb-3 visa-full-cu" id="row-${rowIndex}">

                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label for="visa_type_${rowIndex}">Visa Type <span style="color:#ff0000;">*</span></label>
                                                <select name="visa_type[]" id="visa_type_${rowIndex}" class="form-control field-input visaTypeCls" data-valid="required" >
                                                    <option value="">Select Visa Type</option>
                                                    <!--<option value="Student Visa">Student Visa</option>
                                                    <option value="Work Visa">Work Visa</option>
                                                    <option value="Tourist Visa">Tourist Visa</option>-->
                                                    @foreach(\App\Models\Matter::select('id','title','nick_name')->where('status',1)->orderby('id','ASC')->get() as $matterlist)
                                                    <option value="{{ $matterlist->id }}">{{ $matterlist->title }} ({{ @$matterlist->nick_name }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <input type="hidden" name="visa_type_hidden[]" id="visa_type_hidden_${rowIndex}" value="">
                                        </div>
                                        <div class="col-sm-3">
                                            <div class="form-group">
                                                <label for="visa_expiry_date_${rowIndex}">Visa Expiry Date</label>
                                                <input type="date" name="visa_expiry_date[]" id="visa_expiry_date_${rowIndex}" class="form-control field-input">
                                            </div>
                                        </div>
                                        <div class="col-sm-3 description-container" id="description-container-${rowIndex}">
                                            <div class="form-group">
                                                <label for="visa_description_${rowIndex}">Visa Description</label>
                                                <input type="text" name="visa_description[]" id="visa_description_${rowIndex}" class="form-control field-input" rows="3">
                                            </div>
                                        </div>
                                        <div class="col-sm-1 d-flex align-items-center">
                                            <button type="button" class="btn btn-primary add-row-visacountry disabled-button" id="add-row-visacountry-${rowIndex}" data-row-id="${rowIndex}" >+</button>
                                        </div>
                                    </div>`;

                                    $('#fields-wrapper').append(newVisaRow);

                                    if( $('.visaTypeCls').length >0 ) {
                                        $('.visaTypeCls').each(function(index) {
                                            var visa_type_id =  $(this).attr('id');
                                            var visa_type_id_arr  = visa_type_id.split("_");
                                            var visa_type_val_per_index = $('#visa_type_'+visa_type_id_arr[2]).val();
                                            $('#visa_type_hidden_'+visa_type_id_arr[2]).val(visa_type_val_per_index);
                                        });

                                        $(document).on('change', '.visaTypeCls', function() {
                                            var visa_type_id =  $(this).attr('id');
                                            var visa_type_id_arr  = visa_type_id.split("_");
                                            var visa_type_val_per_index = $('#visa_type_'+visa_type_id_arr[2]).val();
                                            $('#visa_type_hidden_'+visa_type_id_arr[2]).val(visa_type_val_per_index);
                                        });
                                    }

                                    if( $('.visaTypeCls').length >0 ) {
                                        var visaTypeClsLength = $('.visaTypeCls').length;
                                        var updtestvisaTypeLength = visaTypeClsLength-1;
                                        //console.log('updtestvisaTypeLength='+updtestvisaTypeLength);
                                        $('.visaTypeCls').each(function(index) {
                                            //console.log('index='+index);
                                            if( index < updtestvisaTypeLength ){
                                                $('#add-row-visacountry-'+index).attr('disabled', true);
                                                $('#visa_type_'+index).attr('disabled', true);
                                                $('#visa_expiry_date_'+index).attr('readonly', true);
                                                $('#visa_description_'+index).attr('readonly', true);
                                            } else {
                                                $('#add-row-visacountry-'+index).attr('disabled', false);
                                                $('#visa_type_'+index).attr('disabled', false);
                                                $('#visa_expiry_date_'+index).attr('readonly', false);
                                                $('#visa_description_'+index).attr('readonly', false);
                                            }
                                        });
                                    }
                                    rowIndex++; // Increment index for new rows
                                }
                            });

                            // Initial state setup for existing rows
                            $('.fields').each(function() {
                                var rowId = $(this).attr('id').split('-')[1];
                                toggleFields(rowId);
                                toggleAddButton(rowId);
                                toggleDescriptionField(rowId);
                            });

                            // Initial check on page load
                            toggleFieldsBasedOnCountryOnPageLoad(); // Check on page load

                            // Function to show or hide fields based on the selected country at Page load
                            function toggleFieldsBasedOnCountryOnPageLoad() {
                                var selectedCountry = $('#visa_country').val(); // Get the selected country
                                if (selectedCountry === 'Australia') {
                                    // Hide the div with the class 'visa-full-cu'
                                    $('.visa-full-cu').hide();
                                    if( $('.visa-full-cu').length >0 ) {
                                        $('.visa-full-cu').each(function(index) {
                                            $('#visa_type_'+index).removeAttr('data-valid');
                                        });
                                    }
                                } else {
                                    // Show the div with the class 'visa-full-cu'
                                    $('.visa-full-cu').show();
                                    if( $('.visa-full-cu').length >0 ) {
                                        $('.visa-full-cu').each(function(index) {
                                            //console.log(index);
                                            $('#visa_type_'+index).attr('data-valid', 'required');
                                            $('#visa_type_'+index).attr('disabled', true);
                                        });
                                    }
                                }
                            }


                            if( $('.visaTypeCls').length >0 ) {
                                var visaTypeClsLength = $('.visaTypeCls').length;
                                var updtestvisaTypeLength = visaTypeClsLength-1;
                                //console.log('updtestvisaTypeLength='+updtestvisaTypeLength);
                                $('.visaTypeCls').each(function(index) {
                                    //console.log('index='+index);
                                    if( index < updtestvisaTypeLength ){
                                        $('#add-row-visacountry-'+index).attr('disabled', true);
                                        $('#visa_type_'+index).attr('disabled', true);
                                    } else {
                                        $('#add-row-visacountry-'+index).attr('disabled', false);
                                        $('#visa_type_'+index).attr('disabled', false);
                                    }
                                });
                            }
                        });
                        </script>


                        {{-- Address Auto-Fill Section with Backend API Integration --}}
                        {{-- This section handles address autocomplete using postcode lookup via backend proxy --}}
                        <meta name="csrf-token" content="{{ csrf_token() }}">
                        <title>Address Auto-Fill</title>
                        <style>
                            /* Autocomplete dropdown styling */
                            .autocomplete-items {
                                border: 1px solid #d4d4d4;
                                border-bottom: none;
                                border-top: none;
                                z-index: 99;
                                top: 100%;
                                left: 0;
                                right: 0;
                                background-color: white;
                                max-height: 100px;
                                overflow-y: auto;
                                position: absolute;
                            }
                            .autocomplete-item {
                                padding: 10px;
                                cursor: pointer;
                            }
                            .autocomplete-item:hover {
                                background-color: #d3d3d3;
                            }
                            .error-message {
                                color: red;
                                margin-top: 10px;
                            }
                            .error-row {
                                border: 2px solid red;
                            }
                            .error-border {
                                border: 1px solid red;
                            }
                            .form-control {
                                border-radius: 0.25rem;
                            }
                        </style>

                        {{-- Address Fields Container - Supports multiple addresses with dynamic row addition --}}
                        <div id="address-fields-wrapper">
                            {{-- Display existing client addresses (readonly mode) --}}
                            @if(count($clientAddresses) > 0)
                                @foreach($clientAddresses as $index => $address)
                                <div class="address-fields row mb-3" id="row-{{ $index }}">
                                    <input type="hidden" name="address_id[]" value="{{ $address->id }}" class="address-id">
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="zip">Post Code</label>
                                            <input type="text" name="zip[]" value="{{ $address->zip }}" class="form-control postal_code" autocomplete="off" placeholder="Enter Post Code" readonly>
                                            <div class="autocomplete-items"></div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="address">Address</label>
                                            <input type="text" name="address[]" value="{{ $address->address }}" class="form-control address-input" autocomplete="off" placeholder="Search Box" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="regional_code">Regional Code Info</label>
                                            <input type="text" name="regional_code[]" value="{{ $address->regional_code }}" class="form-control regional_code_info" placeholder="Regional Code info" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-2 d-flex align-items-center">
                                        <button type="button" class="btn btn-primary add-row-btn" disabled>+</button>
                                    </div>
                                </div>
                                @endforeach
                            @endif

                            <!-- Blank row for adding new addresses -->
                            <div class="address-fields row mb-3">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="zip">Post Code</label>
                                        <input type="text" name="zip[]" class="form-control postal_code" autocomplete="off" placeholder="Enter Post Code">
                                        <div class="autocomplete-items"></div>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <input type="text" name="address[]" class="form-control address-input" autocomplete="off" placeholder="Search Box">
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="form-group">
                                        <label for="regional_code">Regional Code Info</label>
                                        <input type="text" name="regional_code[]"  class="form-control regional_code_info" placeholder="Regional Code info" readonly>
                                    </div>
                                </div>
                                <div class="col-sm-2 d-flex align-items-center">
                                    <button type="button" class="btn btn-primary add-row-btn">+</button>
                                </div>
                            </div>

                            <div id="error-message" class="error-message" style="display: none;"></div>
                        </div>

                        <script>
                        /**
                         * Address Management Script
                         * Features:
                         * - Dynamic address row addition
                         * - Postcode-based address lookup via backend API
                         * - Australian regional code calculation
                         * - Row validation and freezing mechanism
                         */
                        $(document).ready(function() {
                            // Setup CSRF token for all AJAX requests
                            $.ajaxSetup({
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                }
                            });

                            // Add a new editable address row to the form
                            function addNewAddressRow() {
                                $('.address-fields:last').removeClass('error-row');

                                // Make all previous rows readonly
                                $('.address-fields input').prop('readonly', true);

                                var newAddressRow = `<div class="address-fields row mb-3">
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="zip">Post Code</label>
                                            <input type="text" name="zip[]" class="form-control postal_code" autocomplete="off" placeholder="Enter Post Code">
                                            <div class="autocomplete-items"></div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="address">Address</label>
                                            <input type="text" name="address[]" class="form-control address-input" autocomplete="off" placeholder="Search Box">
                                        </div>
                                    </div>
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="regional_code">Regional Code Info</label>
                                            <input type="text" name="regional_code[]"  class="form-control regional_code_info" placeholder="Regional Code info" readonly>
                                        </div>
                                    </div>
                                    <div class="col-sm-2 d-flex align-items-center">
                                        <button type="button" class="btn btn-primary add-row-btn">+</button>
                                    </div>
                                </div>`;
                                $('#address-fields-wrapper').append(newAddressRow);
                                $('#error-message').hide();
                            }


                            // Validate that current address row has required fields filled
                            function validateCurrentRow() {
                                var $currentRow = $('.address-fields:last');
                                var zip = $currentRow.find('input[name="zip[]"]').val().trim();
                                var address = $currentRow.find('input[name="address[]"]').val().trim();

                                // Remove error class initially
                                $currentRow.find('input').removeClass('error-border');

                                if (zip === '' || address === '') {
                                    if (zip === '') {
                                        $currentRow.find('input[name="zip[]"]').addClass('error-border');
                                    }
                                    if (address === '') {
                                        $currentRow.find('input[name="address[]"]').addClass('error-border');
                                    }
                                    return false;
                                }
                                return true;
                            }

                            // Freeze (lock) the current address row to prevent further editing
                            function freezeCurrentAddressRow() {
                                var $currentRow = $('.address-fields:last');
                                // Set the current row's fields to readonly
                                $currentRow.find('input').attr('readonly', true);
                                $currentRow.find('button').prop('disabled', true);
                                $currentRow.addClass('frozen'); // Add visual indicator class
                            }

                            $(document).on('click', '.add-row-btn', function() {
                                if (validateCurrentRow()) {
                                    var isConfirmed = confirm("Are you sure you want to freeze the current row?");
                                    if (isConfirmed) {
                                        freezeCurrentAddressRow(); // Freeze the current row
                                        addNewAddressRow();
                                    }
                                }
                            });

                            /**
                             * Calculate Australian Regional Code based on postcode
                             * Returns regional classification for visa purposes
                             * @param {string|number} postCode - Australian postcode
                             * @returns {string} Regional classification (e.g., "Regional City of NSW")
                             */
                            function getRegionalCodeInfo(postCode)
                            {
                                // NSW Regional City postcodes: 2259, 2264 to 2308, 2500 to 2526, 2528 to 2535 and 2574
                                if(
                                    ( postCode ==2259)
                                    ||
                                    ( postCode >=2264 && postCode <= 2308 )
                                    ||
                                    ( postCode >=2500 && postCode <= 2526 )
                                    ||
                                    ( postCode >=2528 && postCode <= 2535 )
                                    ||
                                    ( postCode == 2574)
                                ){
                                    var postCodeInfo = "Regional City of NSW";
                                }

                                //2250 to 2258, 2260 to 2263, 2311 to 2490, 2527, 2536 to 2551, 2575 to 2739, 2753 to 2754, 2756 to 2758 and 2773 to 2898
                                else if(
                                    ( postCode >=2250 && postCode <= 2258 )
                                    ||
                                    ( postCode >=2260 && postCode <= 2263 )
                                    ||
                                    ( postCode >=2311 && postCode <= 2490 )
                                    ||
                                    ( postCode == 2527)
                                    ||
                                    ( postCode >=2536 && postCode <= 2551 )
                                    ||
                                    ( postCode >=2575 && postCode <= 2739 )
                                    ||
                                    ( postCode >=2753 && postCode <= 2754 )
                                    ||
                                    ( postCode >=2756 && postCode <= 2758 )
                                    ||
                                    ( postCode >=2773 && postCode <= 2898 )
                                ){
                                    var postCodeInfo = "Regional Centre NSW";
                                }

                                //3211 to 3232, 3235, 3240, 3328, 3330 to 3333, 3340 and 3342
                                else if(
                                    ( postCode >=3211 && postCode <= 3232 )
                                    ||
                                    ( postCode == 3235)
                                    ||
                                    ( postCode == 3240 )
                                    ||
                                    ( postCode == 3328)
                                    ||
                                    ( postCode >=3330 && postCode <= 3333 )
                                    ||
                                    ( postCode == 3340)
                                    ||
                                    ( postCode == 3342)
                                ){
                                    var postCodeInfo = "Regional City of VIC";
                                }

                                //3097 to 3099, 3139, 3233 to 3234, 3236 to 3239, 3241 to 3325, 3329, 3334, 3341,
                                //3345 to 3424, 3430 to 3799, 3809 to 3909, 3912 to 3971 and 3978 to 3996
                                else if(
                                    ( postCode >=3097 && postCode <= 3099 )
                                    ||
                                    ( postCode == 3139)
                                    ||
                                    ( postCode >= 3233 && postCode <= 3234 )
                                    ||
                                    ( postCode >= 3236 && postCode <= 3239 )
                                    ||
                                    ( postCode >= 3241 && postCode <= 3325 )
                                    ||
                                    ( postCode == 3329 )
                                    ||
                                    ( postCode == 3334 )
                                    ||
                                    ( postCode == 3341 )
                                    ||
                                    ( postCode >= 3345 && postCode <= 3424 )
                                    ||
                                    ( postCode >= 3430 && postCode <= 3799 )
                                    ||
                                    ( postCode >= 3809 && postCode <= 3909 )
                                    ||
                                    ( postCode >= 3912 && postCode <= 3971 )
                                    ||
                                    ( postCode >= 3978 && postCode <= 3996 )
                                ){
                                    var postCodeInfo = "Regional Centre VIC";
                                }

                                //4019 to 4022*, 4025*, 4037*, 4074*, 4076 to 4078*, 4207 to 4275, 4300 to 4301*,
                                //4303 to 4305*, 4500 to 4506*, 4508 to 4512*, 4514 to 4516*, 4517 to 4519, 4521*,
                                //4550 to 4551, 4553 to 4562, 4564 to 4569 and 4571 to 4575
                                else if(
                                    ( postCode >=4019 && postCode <= 4022 )
                                    ||
                                    ( postCode == 4025)
                                    ||
                                    ( postCode == 4037)
                                    ||
                                    ( postCode == 4074 )
                                    ||
                                    ( postCode >= 4076 && postCode <= 4078 )
                                    ||
                                    ( postCode >= 4207 && postCode <= 4275 )
                                    ||
                                    ( postCode >= 4300 && postCode <= 4301 )
                                    ||
                                    ( postCode >= 4303 && postCode <= 4305 )
                                    ||
                                    ( postCode >= 4500 && postCode <= 4506 )
                                    ||
                                    ( postCode >= 4508 && postCode <= 4512 )
                                    ||
                                    ( postCode >= 4514 && postCode <= 4516 )
                                    ||
                                    ( postCode >= 4517 && postCode <= 4519 )
                                    ||
                                    ( postCode == 4521 )
                                    ||
                                    ( postCode >= 4550 && postCode <= 4551 )
                                    ||
                                    ( postCode >= 4553 && postCode <= 4562 )
                                    ||
                                    ( postCode >= 4564 && postCode <= 4569 )
                                    ||
                                    ( postCode >= 4571 && postCode <= 4575 )
                                ){
                                    var postCodeInfo = "Regional City of QLD";
                                }

                                //4124, 4125, 4133, 4183 to 4184, 4280 to 4287, 4306 to 4498, 4507, 4552, 4563,
                                //4570 and 4580 to 4895
                                else if(
                                    ( postCode == 4124 )
                                    ||
                                    ( postCode == 4125)
                                    ||
                                    ( postCode == 4133)
                                    ||
                                    ( postCode >= 4183 && postCode <= 4184 )
                                    ||
                                    ( postCode >= 4280 && postCode <= 4287 )
                                    ||
                                    ( postCode >= 4306 && postCode <= 4498 )
                                    ||
                                    ( postCode == 4507)
                                    ||
                                    ( postCode == 4552 )
                                    ||
                                    ( postCode == 4563 )
                                    ||
                                    ( postCode == 4570 )
                                    ||
                                    ( postCode >= 4580 && postCode <= 4895 )
                                ){
                                    var postCodeInfo = "Regional Centre QLD";
                                }

                                //6000 to 6038, 6050 to 6083, 6090 to 6182, 6208 to 6211, 6214 and 6556 to 6558
                                else if(
                                    ( postCode >= 6000 && postCode <= 6038 )
                                    ||
                                    ( postCode >= 6050 && postCode <= 6083 )
                                    ||
                                    ( postCode >= 6090 && postCode <= 6182 )
                                    ||
                                    ( postCode >= 6208 && postCode <= 6211 )
                                    ||
                                    ( postCode == 6214 )
                                    ||
                                    ( postCode >= 6556 && postCode <= 6558 )
                                ){
                                    var postCodeInfo = "Regional City of WA";
                                }

                                //5000 to 5171, 5173 to 5174, 5231 to 5235, 5240 to 5252, 5351 and 5950 to 5960
                                else if(
                                    ( postCode >= 5000 && postCode <= 5171 )
                                    ||
                                    ( postCode >= 5173 && postCode <= 5174 )
                                    ||
                                    ( postCode >= 5231 && postCode <= 5235 )
                                    ||
                                    ( postCode >= 5240 && postCode <= 5252 )
                                    ||
                                    ( postCode == 5351 )
                                    ||
                                    ( postCode >= 5950 && postCode <= 5960 )
                                ){
                                    var postCodeInfo = "Regional City of SA";
                                }
                                //7000, 7004 to 7026, 7030 to 7109, 7140 to 7151 and 7170 to 7177
                                else if(
                                    ( postCode == 7000 )
                                    ||
                                    ( postCode >= 7004 && postCode <= 7026 )
                                    ||
                                    ( postCode >= 7030 && postCode <= 7109 )
                                    ||
                                    ( postCode >= 7140 && postCode <= 7151 )
                                    ||
                                    ( postCode >= 7170 && postCode <= 7177 )
                                ){
                                    var postCodeInfo = "Regional City of TAS";
                                }
                                else {
                                    var postCodeInfo = '';
                                }
                                return postCodeInfo;
                            }

                            // Handle postcode input - trigger address lookup when 4+ characters entered
                            $(document).on('input', '.postal_code', function() {
                                var postcode = $(this).val();
                                var $row = $(this).closest('.address-fields');

                                if (postcode.length > 3) {
                                    // Call backend API to fetch address suggestions
                                    $.ajax({
                                        url: '{{ route("clients.updateAddress") }}',
                                        method: 'POST',
                                        data: { postcode: postcode },
                                        success: function(response) {
                                            var suggestions = response.localities.locality || [];
                                            var $autocomplete = $row.find('.autocomplete-items').empty();

                                            if (Array.isArray(suggestions)) {
                                                suggestions.forEach(function(suggestion) {
                                                    var fullAddress = (suggestion.location || '') + (suggestion.state ? ', ' + suggestion.state : '');

                                                    var $item = $('<div class="autocomplete-item"></div>')
                                                        .text(fullAddress)
                                                        .data('address', {
                                                            fullAddress: fullAddress,
                                                            postcode: suggestion.postcode || ''
                                                        })
                                                        .appendTo($autocomplete);

                                                    $item.on('click', function() {
                                                    var addressData = $(this).data('address');

                                                    // Remove any trailing comma from the full address
                                                    var fullAddress = addressData.fullAddress.trim().replace(/,\s*$/, '');

                                                    $row.find('.address-input').val(fullAddress);
                                                    $row.find('.postal_code').val(addressData.postcode);

                                                    var regional_code_info = getRegionalCodeInfo(addressData.postcode);
                                                    $row.find('.regional_code_info').val(regional_code_info);

                                                    $autocomplete.empty();
                                                });

                                                });
                                            } else {
                                                var fullAddress = (suggestions.location || '') + (suggestions.state ? ', ' + suggestions.state : '');
                                                var $item = $('<div class="autocomplete-item"></div>')
                                                    .text(fullAddress)
                                                    .data('address', {
                                                        fullAddress: fullAddress,
                                                        postcode: suggestions.postcode || ''
                                                    })
                                                    .appendTo($autocomplete);

                                                $item.on('click', function() {
                                                    var addressData = $(this).data('address');
                                                    $row.find('.address-input').val(addressData.fullAddress);
                                                    $row.find('.postal_code').val(addressData.postcode);

                                                    var regional_code_info = getRegionalCodeInfo(addressData.postcode);
                                                    $row.find('.regional_code_info').val(regional_code_info);
                                                    $autocomplete.empty();
                                                });
                                            }
                                        },
                                        error: function() {
                                            alert('Error fetching address details.');
                                        }
                                    });
                                } else {
                                    $row.find('.autocomplete-items').empty();
                                }
                            });
                        });
                        </script>


                        <div id="map" style="display:none;"></div>
                        <hr style="border-color: #000;"/>
                        <div class="row">

                            @if($fetchedData->visa_type!="Citizen" && $fetchedData->visa_type!="PR")
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label for="related_files">Similar related files</label>
                                    <select multiple class="form-control js-data-example-ajaxcc" name="related_files[]">

                                    </select>
                                    @if ($errors->has('related_files'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('related_files') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>


                            <meta name="csrf-token" content="{{ csrf_token() }}">
                            <title>Qualification Management</title>
                            <style>
                                .error-message {
                                    color: red;
                                    margin-top: 10px;
                                }
                                .error-row {
                                    border: 2px solid red; /* Red border for incomplete rows */
                                }
                                button.btn.btn-primary.add-client-experience {
                                    margin: 24px 0 -6px 31px;
                                }
                                button.btn.btn-primary.add-qualification-btn {
                                    margin: 31px 4px 19px 4px;
                                }
                                button.btn.btn-primary.add-job-btn {
                                    margin: 31px 2px -10px;
                                }
                            </style>

                            <div class="col-sm-12">
                                <h5 style="color: #FFFFFF;">Client Qualification</h5>
                                <div id="qualification-fields-wrapper">
                                    <!-- Existing Qualification Fields -->
                                    @if(count($qualifications) > 0)
                                        @foreach($qualifications as $index => $qualification)
                                        <div class="qualification-fields row mb-6" id="row-{{ $index }}">
                                            <input type="hidden" name="qualification_id[]" value="{{ $qualification->id }}" class="qualification-id">

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="level_{{ $index }}">Level</label>
                                                    <select name="level[]" id="level_{{ $index }}" class="form-control levelCls" disabled>
                                                        <option value="">Select Level</option>
                                                        <option value="Certificate I" @if($qualification->level == "Certificate I") selected @endif>Certificate I</option>
                                                        <option value="Certificate II" @if($qualification->level == "Certificate II") selected @endif>Certificate II</option>
                                                        <option value="Certificate III" @if($qualification->level == "Certificate III") selected @endif>Certificate III</option>
                                                        <option value="Certificate IV" @if($qualification->level == "Certificate IV") selected @endif>Certificate IV</option>
                                                        <option value="Diploma" @if($qualification->level == "Diploma") selected @endif>Diploma</option>
                                                        <option value="Advanced Diploma" @if($qualification->level == "Advanced Diploma") selected @endif>Advanced Diploma</option>
                                                        <option value="Associate Degree" @if($qualification->level == "Associate Degree") selected @endif>Associate Degree</option>
                                                        <option value="Bachelor Degree" @if($qualification->level == "Bachelor Degree") selected @endif>Bachelor Degree</option>
                                                        <option value="Bachelor Honours Degree" @if($qualification->level == "Bachelor Honours Degree") selected @endif>Bachelor Honours Degree</option>
                                                        <option value="Graduate Certificate" @if($qualification->level == "Graduate Certificate") selected @endif>Graduate Certificate</option>
                                                        <option value="Graduate Diploma" @if($qualification->level == "Graduate Diploma") selected @endif>Graduate Diploma</option>
                                                        <option value="Masters Degree" @if($qualification->level == "Masters Degree") selected @endif>Masters Degree</option>
                                                        <option value="Doctoral Degree" @if($qualification->level == "Doctoral Degree") selected @endif>Doctoral Degree</option>
                                                        <option value="11" @if($qualification->level == "11") selected @endif>+2</option>
                                                        <!-- Other options here -->
                                                    </select>
                                                </div>
                                                <input type="hidden" name="level_hidden[]" id="level_hidden_{{ $index }}" value="">

                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="name_{{ $index }}">Name</label>
                                                    <input type="text" name="name[]" value="{{ $qualification->name }}" class="form-control name-input" id="name_{{ $index }}" placeholder="Enter Name" readonly>
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="country_{{ $index }}">Country</label>
                                                    <select name="country[]" id="country_{{ $index }}" class="form-control countryCls" disabled>
                                                        <option value="Australia" {{ @$qualification->country == 'Australia' ? 'selected' : '' }}>Australia </option>
                                                        <option value="India" {{ @$qualification->country == 'India' ? 'selected' : '' }}>India</option>
                                                        <?php
                                                        foreach (\App\Models\Country::all() as $list) {
                                                            // Skip India and Australia since they've already been added manually
                                                            if ($list->name == 'Australia' || $list->name == 'India') {
                                                                continue;
                                                            }
                                                            $selected = (@$qualification->country == $list->name) ? 'selected' : '';
                                                            ?>
                                                            <option value="{{ $list->name }}" {{ $selected }}>{{ $list->name }}</option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="country_hidden[]" id="country_hidden_{{ $index }}" value="">
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="start_date_{{ $index }}">Start Date</label>
                                                    <input type="date" name="start_date[]" value="{{ $qualification->start_date }}" class="form-control start-date-input" id="start_date_{{ $index }}" placeholder="Enter Start Date" readonly>
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="finish_date_{{ $index }}">Finish Date</label>
                                                    <input type="date" name="finish_date[]" value="{{ $qualification->finish_date }}" class="form-control finish-date-input" id="finish_date_{{ $index }}" placeholder="Enter Finish Date" readonly>
                                                </div>
                                            </div>

                                            <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="relevant_qualification_{{ $index }}">Relevant</label>
                                                    <select name="relevant_qualification[]" class="form-control relevantQualificationCls" id="relevant_qualification_{{ $index }}" disabled>
                                                        <option value="">Select</option>
                                                        <option value="Yes" {{ @$qualification->relevant_qualification == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                        <option value="No" {{ @$qualification->relevant_qualification == 'No' ? 'selected' : '' }}>No</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="relevant_qualification_hidden[]" id="relevant_qualification_hidden_{{ $index }}" value="">
                                            </div>

                                            <!-- Plus Button for Adding New Rows -->
                                            <div class="col-sm-1">
                                                <button type="button" class="btn btn-primary add-qualification-btn" data-index="{{ $index }}" disabled>+</button>
                                            </div>
                                        </div>
                                        @endforeach
                                    @endif

                                    <script>
                                    $(document).ready(function() {
                                        var qualificationIndexAtPageLoad = @json(count($qualifications));
                                        if( qualificationIndexAtPageLoad >0){
                                            var qualiIndexByDefault = qualificationIndexAtPageLoad;
                                        } else {
                                            var qualiIndexByDefault = 0;
                                        }
                                        const newqualiRowByDefault = `
                                        <div class="qualification-fields row mb-6" id="row-${qualiIndexByDefault}">
                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="level_${qualiIndexByDefault}">Level</label>
                                                    <select name="level[]" id="level_${qualiIndexByDefault}" class="form-control levelCls">
                                                        <option value="">Select Level</option>
                                                        <option value="Certificate I">Certificate I</option>
                                                        <option value="Certificate II">Certificate II</option>
                                                        <option value="Certificate III">Certificate III</option>
                                                        <option value="Certificate IV">Certificate IV</option>
                                                        <option value="Diploma">Diploma</option>
                                                        <option value="Advanced Diploma">Advanced Diploma</option>
                                                        <option value="Associate Degree">Associate Degree</option>
                                                        <option value="Bachelor Degree">Bachelor Degree</option>
                                                        <option value="Bachelor Honours Degree">Bachelor Honours Degree</option>
                                                        <option value="Graduate Certificate">Graduate Certificate</option>
                                                        <option value="Graduate Diploma">Graduate Diploma</option>
                                                        <option value="Masters Degree">Masters Degree</option>
                                                        <option value="Doctoral Degree">Doctoral Degree</option>
                                                        <option value="11">+2</option>
                                                    </select>
                                                </div>
                                                 <input type="hidden" name="level_hidden[]" id="level_hidden_${qualiIndexByDefault}" value="">
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="name_${qualiIndexByDefault}">Name</label>
                                                    <input type="text" name="name[]" class="form-control name-input" id="name_${qualiIndexByDefault}" placeholder="Enter Name">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="country_${qualiIndexByDefault}">Country</label>
                                                    <select name="country[]" id="country_${qualiIndexByDefault}" class="form-control countryCls">
                                                        <!-- Manually add Australia and India first -->
                                                        <option value="Australia" {{ @$qualification->country == 'Australia' ? 'selected' : '' }}>Australia </option>
                                                        <option value="India" {{ @$qualification->country == 'India' ? 'selected' : '' }}>India</option>

                                                        <?php
                                                        foreach (\App\Models\Country::all() as $list) {
                                                        // Skip India and Australia since they've already been added manually
                                                        if ($list->name == 'Australia' || $list->name == 'India') {
                                                        continue;
                                                        }
                                                        $selected = (@$qualification->country == $list->name) ? 'selected' : '';
                                                        ?>
                                                        <option value="{{ $list->name }}" {{ $selected }}>{{ $list->name }}</option>
                                                    <?php
                                                    }
                                                    ?>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="country_hidden[]" id="country_hidden_${qualiIndexByDefault}" value="">
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="start_date_${qualiIndexByDefault}">Start Date</label>
                                                    <input type="date" name="start_date[]" class="form-control start-date-input" id="start_date_${qualiIndexByDefault}" placeholder="Enter Start Date">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="finish_date_${qualiIndexByDefault}">Finish Date</label>
                                                    <input type="date" name="finish_date[]" class="form-control finish-date-input" id="finish_date_${qualiIndexByDefault}" placeholder="Enter Finish Date">
                                                </div>
                                            </div>

                                            <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="relevant_qualification_${qualiIndexByDefault}">Relevant</label>
                                                    <select name="relevant_qualification[]" id="relevant_qualification_${qualiIndexByDefault}" class="form-control relevantQualificationCls">
                                                        <option value="">Select</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                    <input type="hidden" name="relevant_qualification_hidden[]" id="relevant_qualification_hidden_${qualiIndexByDefault}" value="">
                                            </div>

                                            <div class="col-sm-1">
                                                <button type="button" class="btn btn-primary add-qualification-btn" data-index="${qualiIndexByDefault}">+</button>
                                            </div>
                                        </div>`;

                                        qualiIndexByDefault++;
                                        $('#qualification-fields-wrapper').append(newqualiRowByDefault);

                                        if( $('.levelCls').length >0 ) {
                                            $('.levelCls').each(function(index) {
                                                var level_type_id =  $(this).attr('id');
                                                var level_type_id_arr  = level_type_id.split("_");
                                                var level_type_val_per_index = $('#level_'+level_type_id_arr[1]).val();
                                                $('#level_hidden_'+level_type_id_arr[1]).val(level_type_val_per_index);
                                            });

                                            $(document).on('change', '.levelCls', function() {
                                                var level_type_id =  $(this).attr('id');
                                                var level_type_id_arr  = level_type_id.split("_");
                                                var level_type_val_per_index = $('#level_'+level_type_id_arr[1]).val();
                                                $('#level_hidden_'+level_type_id_arr[1]).val(level_type_val_per_index);
                                            });
                                        }

                                        if( $('.countryCls').length >0 ) {
                                            $('.countryCls').each(function(index) {
                                                var country_type_id =  $(this).attr('id');
                                                var country_type_id_arr  = country_type_id.split("_");
                                                var country_type_val_per_index = $('#country_'+country_type_id_arr[1]).val();
                                                $('#country_hidden_'+country_type_id_arr[1]).val(country_type_val_per_index);
                                            });

                                            $(document).on('change', '.countryCls', function() {
                                                var country_type_id =  $(this).attr('id');
                                                var country_type_id_arr  = country_type_id.split("_");
                                                var country_type_val_per_index = $('#country_'+country_type_id_arr[1]).val();
                                                $('#country_hidden_'+country_type_id_arr[1]).val(country_type_val_per_index);
                                            });
                                        }

                                        if( $('.relevantQualificationCls').length >0 ) {
                                            $('.relevantQualificationCls').each(function(index) {
                                                var relevant_quali_id =  $(this).attr('id');
                                                var relevant_quali_id_arr  = relevant_quali_id.split("_");
                                                var relevant_qualification_val_per_index = $('#relevant_qualification_'+relevant_quali_id_arr[2]).val();
                                                $('#relevant_qualification_hidden_'+relevant_quali_id_arr[2]).val(relevant_qualification_val_per_index);
                                            });

                                            $(document).on('change', '.relevantQualificationCls', function() {
                                                var relevant_quali_id =  $(this).attr('id');
                                                var relevant_quali_id_arr  = relevant_quali_id.split("_");
                                                var relevant_qualification_val_per_index = $('#relevant_qualification_'+relevant_quali_id_arr[2]).val();
                                                $('#relevant_qualification_hidden_'+relevant_quali_id_arr[2]).val(relevant_qualification_val_per_index);
                                            });
                                        }
                                    });
                                    </script>
                                    <!-- Always include a blank row for adding new qualifications -->

                                </div>
                            </div>

                            <script>
                            $(document).ready(function() {
                                let qualificationIndex = @json(count($qualifications)+1);
                                //console.log('quali-index='+qualificationIndex);

                                function addNewQualificationRow() {
                                    //console.log('newrowindex='+qualificationIndex);
                                    // Set all input fields in the previous row to readonly
                                    //$('.qualification-fields:last').find('input, select').attr('readonly', true);

                                    // Validate the current row before adding a new one
                                    const currentRow = $('.qualification-fields:last');
                                    if (!validateFields(currentRow)) {
                                        alert('Please fill in all required fields before adding a new row.');
                                        return;
                                    }

                                    // Display a confirmation dialog before freezing the current row
                                    if (confirm("Are you sure you want to freeze the current row?")) {
                                        // Set all input fields in the previous row to readonly if the user confirms
                                        currentRow.find('input').attr('readonly', true);
                                        currentRow.find('select').prop('disabled', true);
                                        currentRow.find('.add-qualification-btn').prop('disabled', true);
                                    }

                                    const newQualificationRow = `
                                        <div class="qualification-fields row mb-6" id="row-${qualificationIndex}">
                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="level_${qualificationIndex}">Level</label>
                                                    <select name="level[]" id="level_${qualificationIndex}" class="form-control levelCls">
                                                        <option value="">Select Level</option>
                                                        <option value="Certificate I">Certificate I</option>
                                                        <option value="Certificate II">Certificate II</option>
                                                        <option value="Certificate III">Certificate III</option>
                                                        <option value="Certificate IV">Certificate IV</option>
                                                        <option value="Diploma">Diploma</option>
                                                        <option value="Advanced Diploma">Advanced Diploma</option>
                                                        <option value="Associate Degree">Associate Degree</option>
                                                        <option value="Bachelor Degree">Bachelor Degree</option>
                                                        <option value="Bachelor Honours Degree">Bachelor Honours Degree</option>
                                                        <option value="Graduate Certificate">Graduate Certificate</option>
                                                        <option value="Graduate Diploma">Graduate Diploma</option>
                                                        <option value="Masters Degree">Masters Degree</option>
                                                        <option value="Doctoral Degree">Doctoral Degree</option>
                                                        <option value="11">+2</option>
                                                    </select>
                                                </div>
                                                 <input type="hidden" name="level_hidden[]" id="level_hidden_${qualificationIndex}" value="">
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="name_${qualificationIndex}">Name</label>
                                                    <input type="text" name="name[]" class="form-control name-input" id="name_${qualificationIndex}" placeholder="Enter Name">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="country_${qualificationIndex}">Country</label>
                                                    <select name="country[]" id="country_${qualificationIndex}" class="form-control countryCls">
                                                        <!-- Manually add Australia and India first -->
                                                        <option value="Australia" {{ @$qualification->country == 'Australia' ? 'selected' : '' }}>Australia </option>
                                                        <option value="India" {{ @$qualification->country == 'India' ? 'selected' : '' }}>India</option>

                                                        <?php
                                                        foreach (\App\Models\Country::all() as $list) {
                                                        // Skip India and Australia since they've already been added manually
                                                        if ($list->name == 'Australia' || $list->name == 'India') {
                                                        continue;
                                                        }
                                                        $selected = (@$qualification->country == $list->name) ? 'selected' : '';
                                                        ?>
                                                        <option value="{{ $list->name }}" {{ $selected }}>{{ $list->name }}</option>
                                                    <?php
                                                    }
                                                    ?>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="country_hidden[]" id="country_hidden_${qualificationIndex}" value="">
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="start_date_${qualificationIndex}">Start Date</label>
                                                    <input type="date" name="start_date[]" class="form-control start-date-input" id="start_date_${qualificationIndex}" placeholder="Enter Start Date">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="finish_date_${qualificationIndex}">Finish Date</label>
                                                    <input type="date" name="finish_date[]" class="form-control finish-date-input" id="finish_date_${qualificationIndex}" placeholder="Enter Finish Date">
                                                </div>
                                            </div>

                                            <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="relevant_qualification_${qualificationIndex}">Relevant</label>
                                                    <select name="relevant_qualification[]" id="relevant_qualification_${qualificationIndex}" class="form-control relevantQualificationCls">
                                                        <option value="">Select</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="relevant_qualification_hidden[]" id="relevant_qualification_hidden_${qualificationIndex}" value="">
                                            </div>

                                            <div class="col-sm-1">
                                                <button type="button" class="btn btn-primary add-qualification-btn" data-index="${qualificationIndex}">+</button>
                                            </div>
                                        </div>`;

                                    qualificationIndex++;
                                    $('#qualification-fields-wrapper').append(newQualificationRow);

                                    if( $('.levelCls').length >0 ) {
                                        $('.levelCls').each(function(index) {
                                            var level_type_id =  $(this).attr('id');
                                            var level_type_id_arr  = level_type_id.split("_");
                                            var level_type_val_per_index = $('#level_'+level_type_id_arr[1]).val();
                                            $('#level_hidden_'+level_type_id_arr[1]).val(level_type_val_per_index);
                                        });

                                        $(document).on('change', '.levelCls', function() {
                                            var level_type_id =  $(this).attr('id');
                                            var level_type_id_arr  = level_type_id.split("_");
                                            var level_type_val_per_index = $('#level_'+level_type_id_arr[1]).val();
                                            $('#level_hidden_'+level_type_id_arr[1]).val(level_type_val_per_index);
                                        });
                                    }

                                    if( $('.countryCls').length >0 ) {
                                        $('.countryCls').each(function(index) {
                                            var country_type_id =  $(this).attr('id');
                                            var country_type_id_arr  = country_type_id.split("_");
                                            var country_type_val_per_index = $('#country_'+country_type_id_arr[1]).val();
                                            $('#country_hidden_'+country_type_id_arr[1]).val(country_type_val_per_index);
                                        });

                                        $(document).on('change', '.countryCls', function() {
                                            var country_type_id =  $(this).attr('id');
                                            var country_type_id_arr  = country_type_id.split("_");
                                            var country_type_val_per_index = $('#country_'+country_type_id_arr[1]).val();
                                            $('#country_hidden_'+country_type_id_arr[1]).val(country_type_val_per_index);
                                        });
                                    }

                                    if( $('.relevantQualificationCls').length >0 ) {
                                        $('.relevantQualificationCls').each(function(index) {
                                            var relevant_quali_id =  $(this).attr('id');
                                            var relevant_quali_id_arr  = relevant_quali_id.split("_");
                                            var relevant_qualification_val_per_index = $('#relevant_qualification_'+relevant_quali_id_arr[2]).val();
                                            $('#relevant_qualification_hidden_'+relevant_quali_id_arr[2]).val(relevant_qualification_val_per_index);
                                        });

                                        $(document).on('change', '.relevantQualificationCls', function() {
                                            var relevant_quali_id =  $(this).attr('id');
                                            var relevant_quali_id_arr  = relevant_quali_id.split("_");
                                            var relevant_qualification_val_per_index = $('#relevant_qualification_'+relevant_quali_id_arr[2]).val();
                                            $('#relevant_qualification_hidden_'+relevant_quali_id_arr[2]).val(relevant_qualification_val_per_index);
                                        });
                                    }
                                }

                                function validateFields(row) {
                                    let isValid = true;
                                    $(row).find('select, input').each(function() {
                                        if ($(this).attr('type') !== 'date') {  // Exclude date inputs
                                            if ($(this).val() === '') {
                                                $(this).addClass('invalid');
                                                isValid = false;
                                            } else {
                                                $(this).removeClass('invalid');
                                            }
                                        }
                                    });
                                    return isValid;
                                }

                                $(document).on('click', '.add-qualification-btn', function() {
                                    addNewQualificationRow();
                                });

                                // Event listener to remove the red border on input or select change


                                // Initial setup: set the first row to editable if no qualifications exist
                                if (qualificationIndex === 0) {
                                    $('.qualification-fields:first').find('input, select').attr('readonly', false);
                                }
                            });
                            </script>

                            <meta name="csrf-token" content="{{ csrf_token() }}">
                            <div class="col-sm-12">
                                <h5 style="color: #FFFFFF;">Client Experience</h5>
                                <div id="job-fields-wrapper">
                                    <!-- Existing Job Fields (Edit Mode) -->
                                    @if(!empty($experiences))
                                        @foreach($experiences as $index => $experience)
                                            <div class="job-fields row mb-6" id="row_{{ $index }}">
                                                <input type="hidden" name="job_id[]" value="{{ $experience->id }}" class="job_id">

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_title_{{ $index }}">Job Title</label>
                                                        <input type="text" name="job_title[]" id="job_title_{{ $index }}" class="form-control" value="{{ $experience->job_title }}" placeholder="Enter Job Title" readonly>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_code_{{ $index }}">Code</label>
                                                        <input type="text" name="job_code[]" id="job_code_{{ $index }}" class="form-control" value="{{ $experience->job_code }}" placeholder="Enter Code" readonly>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_country_{{ $index }}">Country</label>
                                                        <select name="job_country[]" id="job_country_{{ $index }}" class="form-control jobcountryCls" disabled>
                                                            <option value="Australia" {{ $experience->job_country == 'Australia' ? 'selected' : '' }}>Australia</option>
                                                            <option value="India" {{ $experience->job_country == 'India' ? 'selected' : '' }}>India</option>
                                                            @foreach(\App\Models\Country::all() as $list)
                                                                @if($list->name != 'Australia' && $list->name != 'India')
                                                                    <option value="{{ $list->name }}" {{ $experience->job_country == $list->name ? 'selected' : '' }}>{{ $list->name }}</option readonly>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        <input type="hidden" name="job_country_hidden[]" id="job_country_hidden_{{ $index }}" value="">
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_start_date_{{ $index }}">Start Date</label>
                                                        <input type="date" name="job_start_date[]" id="job_start_date_{{ $index }}" class="form-control" value="{{ $experience->job_start_date }}" placeholder="Enter Start Date" readonly>
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_finish_date_{{ $index }}">Finish Date</label>
                                                        <input type="date" name="job_finish_date[]" id="job_finish_date_{{ $index }}" class="form-control" value="{{ $experience->job_finish_date }}" placeholder="Enter Finish Date" readonly>
                                                    </div>
                                                </div>

                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="relevant_experience_{{ $index }}">Relevant</label>
                                                        <select name="relevant_experience[]" class="form-control relevantExperienceCls" id="relevant_experience_{{ $index }}" disabled>
                                                            <option value="">Select</option>
                                                            <option value="Yes" {{ $experience->relevant_experience == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                            <option value="No"  {{ $experience->relevant_experience	== 'No' ? 'selected' : '' }}>No</option>
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="relevant_experience_hidden[]" id="relevant_experience_hidden_{{ $index }}" value="">
                                                </div>

                                                <!-- Add Row Button for New Rows -->

                                                <div class="col-sm-1">
                                                    <button type="button" class="btn btn-primary add-job-btn" data-index="{{ $index }}" disabled>+</button>
                                                </div>

                                            </div>
                                        @endforeach
                                    @endif

                                    <!-- Always include a blank row for adding new job details -->

                                    <script>
                                    $(document).ready(function() {
                                        //var expIndexAtPageLoad = '<?php echo json_encode($experiences); ?>';
                                        var expIndexAtPageLoad = {{ count($experiences) }};
                                        if( expIndexAtPageLoad >0){
                                            var expIndexByDefault = expIndexAtPageLoad;
                                        } else {
                                            var expIndexByDefault = 0;
                                        }
                                        //console.log('expIndexByDefault='+expIndexByDefault);

                                        const newExpRowByDefault = `
                                            <div class="job-fields row mb-6" id="row-${expIndexByDefault}">
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_title_${expIndexByDefault}">Job Title</label>
                                                        <input type="text" name="job_title[]" id="job_title_${expIndexByDefault}" class="form-control" placeholder="Enter Job Title">
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_code_${expIndexByDefault}">Code</label>
                                                        <input type="text" name="job_code[]" class="form-control job-code-input" id="job_code_${expIndexByDefault}" placeholder="Enter Code">
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_country_${expIndexByDefault}">Country</label>
                                                        <select name="job_country[]" id="job_country_${expIndexByDefault}" class="form-control jobcountryCls">
                                                            <option value="Australia">Australia</option>
                                                            <option value="India">India</option>
                                                            @foreach(\App\Models\Country::all() as $list)
                                                                @if($list->name != 'Australia' && $list->name != 'India')
                                                                    <option value="{{ $list->name }}">{{ $list->name }}</option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        <input type="hidden" name="job_country_hidden[]" id="job_country_hidden_${expIndexByDefault}" value="">

                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_start_date_${expIndexByDefault}">Start Date</label>
                                                        <input type="date" name="job_start_date[]" class="form-control job-start-date-input" id="job_start_date_${expIndexByDefault}" placeholder="Enter Start Date">
                                                    </div>
                                                </div>

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="job_finish_date_${expIndexByDefault}">Finish Date</label>
                                                        <input type="date" name="job_finish_date[]" class="form-control job-finish-date-input" id="job_finish_date_${expIndexByDefault}" placeholder="Enter Finish Date">
                                                    </div>
                                                </div>

                                                 <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="relevant_experience_${expIndexByDefault}">Relevant</label>
                                                        <select name="relevant_experience[]" class="form-control relevantExperienceCls" id="relevant_experience_${expIndexByDefault}">
                                                            <option value="">Select</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="relevant_experience_hidden[]" id="relevant_experience_hidden_${expIndexByDefault}" value="">
                                                </div>

                                                <div class="col-sm-1">
                                                    <button type="button" class="btn btn-primary add-job-btn" data-index="${expIndexByDefault}">+</button>
                                                </div>
                                            </div>
                                        `;

                                        $('#job-fields-wrapper').append(newExpRowByDefault);
                                        expIndexByDefault++;

                                        if( $('.jobcountryCls').length >0 ) {
                                            $('.jobcountryCls').each(function(index) {
                                                var job_country_type_id =  $(this).attr('id');
                                                var job_country_type_id_arr  = job_country_type_id.split("_");
                                                var job_country_type_val_per_index = $('#job_country_'+job_country_type_id_arr[2]).val();
                                                $('#job_country_hidden_'+job_country_type_id_arr[2]).val(job_country_type_val_per_index);
                                            });

                                            $(document).on('change', '.jobcountryCls', function() {
                                                var job_country_type_id =  $(this).attr('id');
                                                var job_country_type_id_arr  = job_country_type_id.split("_");
                                                var job_country_type_val_per_index = $('#job_country_'+job_country_type_id_arr[2]).val();
                                                $('#job_country_hidden_'+job_country_type_id_arr[2]).val(job_country_type_val_per_index);
                                            });
                                        }

                                        if( $('.relevantExperienceCls').length >0 ) {
                                            $('.relevantExperienceCls').each(function(index) {
                                                var rel_exp_type_id =  $(this).attr('id');
                                                var rel_exp_type_id_arr  = rel_exp_type_id.split("_");
                                                var rel_exp_type_val_per_index = $('#relevant_experience_'+rel_exp_type_id_arr[2]).val();
                                                $('#relevant_experience_hidden_'+rel_exp_type_id_arr[2]).val(rel_exp_type_val_per_index);
                                            });

                                            $(document).on('change', '.relevantExperienceCls', function() {
                                                var rel_exp_type_id =  $(this).attr('id');
                                                var rel_exp_type_id_arr  = rel_exp_type_id.split("_");
                                                var rel_exp_type_val_per_index = $('#relevant_experience_'+rel_exp_type_id_arr[2]).val();
                                                $('#relevant_experience_hidden_'+rel_exp_type_id_arr[2]).val(rel_exp_type_val_per_index);
                                            });
                                        }
                                    });
                                    </script>
                                </div>
                            </div>

                            <style>
                                .invalid {
                                    border: 1px solid red;
                                }
                            </style>
                            <script>
                            $(document).ready(function() {
                                let jobIndex = {{ count($experiences)+1 }} || 0; // Start with the number of existing experiences or 0 if none
                                //console.log('jobIndex='+jobIndex);

                                function validateFields(row) {
                                    let isValid = true;
                                    $(row).find('select, input').each(function() {
                                        if ($(this).attr('type') !== 'date') {  // Exclude date inputs
                                            if ($(this).val() === '') {
                                                $(this).addClass('invalid');
                                                isValid = false;
                                            } else {
                                                $(this).removeClass('invalid');
                                            }
                                        }
                                    });
                                    return isValid;
                                }

                                function addNewJobRow() {
                                    const currentRow = $('.job-fields:last');
                                    if (!validateFields(currentRow)) {
                                        alert('Please fill in all required fields before adding a new row.');
                                        return;
                                    }

                                    if (confirm("Are you sure you want to freeze the current row?")) {
                                        currentRow.find('input').attr('readonly', true);
                                        currentRow.find('select').prop('disabled', true);
                                        currentRow.find('.add-job-btn').prop('disabled', true);
                                    }

                                    const newJobRow = `
                                        <div class="job-fields row mb-6" id="row-${jobIndex}">
                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="job_title_${jobIndex}">Job Title</label>
                                                    <input type="text" name="job_title[]" id="job_title_${jobIndex}" class="form-control" placeholder="Enter Job Title">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="job_code_${jobIndex}">Code</label>
                                                    <input type="text" name="job_code[]" class="form-control job-code-input" id="job_code_${jobIndex}" placeholder="Enter Code">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="job_country_${jobIndex}">Country</label>
                                                    <select name="job_country[]" id="job_country_${jobIndex}" class="form-control jobcountryCls">
                                                        <option value="Australia" >Australia</option>
                                                        <option value="India">India</option>
                                                        @foreach(\App\Models\Country::all() as $list)
                                                            @if($list->name != 'Australia' && $list->name != 'India')
                                                                <option value="{{ $list->name }}">{{ $list->name }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                    <input type="hidden" name="job_country_hidden[]" id="job_country_hidden_${jobIndex}" value="">

                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="job_start_date_${jobIndex}">Start Date</label>
                                                    <input type="date" name="job_start_date[]" class="form-control job-start-date-input" id="job_start_date_${jobIndex}" placeholder="Enter Start Date">
                                                </div>
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="job_finish_date_${jobIndex}">Finish Date</label>
                                                    <input type="date" name="job_finish_date[]" class="form-control job-finish-date-input" id="job_finish_date_${jobIndex}" placeholder="Enter Finish Date">
                                                </div>
                                            </div>

                                             <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="relevant_experience_${jobIndex}">Relevant</label>
                                                    <select name="relevant_experience[]" class="form-control relevantExperienceCls" id="relevant_experience_${jobIndex}">
                                                        <option value="">Select</option>
                                                        <option value="Yes">Yes</option>
                                                        <option value="No">No</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="relevant_experience_hidden[]" id="relevant_experience_hidden_${jobIndex}" value="">
                                            </div>

                                            <div class="col-sm-1">
                                                <button type="button" class="btn btn-primary add-job-btn" data-index="${jobIndex}">+</button>
                                            </div>
                                        </div>
                                    `;

                                    $('#job-fields-wrapper').append(newJobRow);
                                    jobIndex++;

                                    if( $('.jobcountryCls').length >0 ) {
                                        $('.jobcountryCls').each(function(index) {
                                            var job_country_type_id =  $(this).attr('id');
                                            var job_country_type_id_arr  = job_country_type_id.split("_");
                                            var job_country_type_val_per_index = $('#job_country_'+job_country_type_id_arr[2]).val();
                                            $('#job_country_hidden_'+job_country_type_id_arr[2]).val(job_country_type_val_per_index);
                                        });

                                        $(document).on('change', '.jobcountryCls', function() {
                                            var job_country_type_id =  $(this).attr('id');
                                            var job_country_type_id_arr  = job_country_type_id.split("_");
                                            var job_country_type_val_per_index = $('#job_country_'+job_country_type_id_arr[2]).val();
                                            $('#job_country_hidden_'+job_country_type_id_arr[2]).val(job_country_type_val_per_index);
                                        });
                                    }

                                    if( $('.relevantExperienceCls').length >0 ) {
                                        $('.relevantExperienceCls').each(function(index) {
                                            var rel_exp_type_id =  $(this).attr('id');
                                            var rel_exp_type_id_arr  = rel_exp_type_id.split("_");
                                            var rel_exp_type_val_per_index = $('#relevant_experience_'+rel_exp_type_id_arr[2]).val();
                                            $('#relevant_experience_hidden_'+rel_exp_type_id_arr[2]).val(rel_exp_type_val_per_index);
                                        });

                                        $(document).on('change', '.relevantExperienceCls', function() {
                                            var rel_exp_type_id =  $(this).attr('id');
                                            var rel_exp_type_id_arr  = rel_exp_type_id.split("_");
                                            var rel_exp_type_val_per_index = $('#relevant_experience_'+rel_exp_type_id_arr[2]).val();
                                            $('#relevant_experience_hidden_'+rel_exp_type_id_arr[2]).val(rel_exp_type_val_per_index);
                                        });
                                    }
                                }

                                $(document).on('click', '.add-job-btn', function() {
                                    addNewJobRow();
                                });
                            });

                            </script>


                            <!------------------------------------  start occupation  --------------------------->

                            <div class="col-sm-12">
                                <div id="occupation-fields-wrapper">
                                    <h5 style="color: #FFFFFF;">Occupation</h5>
                                    <!-- Pre-existing rows will be filled with data -->
                                    @if(count($clientOccupations) > 0)
                                        @foreach($clientOccupations as $index => $occupation)
                                        <div class="occupation-fields row mb-3" id="row-{{ $index }}">
                                            <input type="hidden" name="occupation_id[]" value="{{ $occupation->id }}" class="occupation-id">

                                            <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="skill_assessment_{{ $index }}">Skill</label>
                                                    <select name="skill_assessment[]" class="form-control skill-assessment-input skillCls" id="skill_assessment_{{ $index }}" required disabled>
                                                        <option value="">Select</option>
                                                        <option value="Yes" {{ $occupation->skill_assessment == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                        <option value="No" {{ $occupation->skill_assessment == 'No' ? 'selected' : '' }}>No</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="skill_assessment_hidden[]" id="skill_assessment_hidden_{{ $index }}" value="">
                                            </div>

                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="nomi_occupation_{{ $index }}">Occupation</label>
                                                    <input type="text" name="nomi_occupation[]" value="{{ $occupation->nomi_occupation }}" class="form-control nomi_occupation" id="nomi_occupation_{{ $index }}" autocomplete="off" placeholder="Enter Occupation" readonly>
                                                    <div class="autocomplete-items"></div>
                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="occupation_code_{{ $index }}">Occupation Code</label>
                                                    <input type="text" name="occupation_code[]" value="{{ $occupation->occupation_code }}" class="form-control occupation_code" id="occupation_code_{{ $index }}" autocomplete="off" placeholder="Occupation Code" readonly>
                                                </div>
                                            </div>
                                            <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="list_{{ $index }}">List</label>
                                                    <input type="text" name="list[]" value="{{ $occupation->list }}" class="form-control list" id="list_{{ $index }}" autocomplete="off" placeholder="List" readonly>
                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="form-group">
                                                    <label for="visa_subclass_{{ $index }}">Visa Subclass</label>
                                                    <input type="text" name="visa_subclass[]" value="{{ $occupation->visa_subclass }}" class="form-control visa_subclass" id="visa_subclass_{{ $index }}" autocomplete="off" placeholder="Visa Subclass" readonly>
                                                </div>
                                            </div>


                                            <div class="col-sm-2 date-wrapper">
                                                <div class="form-group">
                                                    <label for="date_new">Date</label>
                                                    <input type="date" name="dates[]" class="form-control date-input" id="date_new" value="{{ $occupation->dates }}" readonly>
                                                </div>
                                            </div>

                                            <div class="col-sm-1">
                                                <div class="form-group">
                                                    <label for="relevant_occupation_{{ $index }}">Relevant</label>
                                                    <select name="relevant_occupation[]" class="form-control relevantOccupationCls" id="relevant_occupation_{{ $index }}" required disabled>
                                                        <option value="">Select</option>
                                                        <option value="Yes" {{ $occupation->relevant_occupation == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                        <option value="No"  {{ $occupation->relevant_occupation == 'No' ? 'selected' : '' }} >No</option>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="relevant_occupation_hidden[]" class="relevantOccupationCls_hidden" id="relevant_occupation_hidden_{{ $index }}" value="">
                                            </div>

                                            <div class="col-sm-1 d-flex align-items-center">
                                                <button type="button" class="btn btn-primary add-row-btn" disabled>+</button>
                                            </div>
                                        </div>
                                        @endforeach
                                    @endif

                                    <!-- Blank row for adding new occupations -->

                                    <script>
                                    $(document).ready(function() {
                                        var occupationIndexAtPageLoad = @json(count($clientOccupations));
                                        if( occupationIndexAtPageLoad >0){
                                            var occuIndexByDefault = occupationIndexAtPageLoad;
                                        } else {
                                            var occuIndexByDefault = 0;
                                        }


                                        const newOccuRowByDefault = `<div class="occupation-fields row mb-3" id="row-${occuIndexByDefault}">
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="skill_assessment_new">Skill</label>
                                                        <select name="skill_assessment[]" id="skill_assessment_${occuIndexByDefault}" class="form-control skill-assessment-input skillCls" id="skill_assessment_new" required>
                                                            <option value="">Select</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <input type="hidden" name="skill_assessment_hidden[]" id="skill_assessment_hidden_${occuIndexByDefault}" value="">
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="nomi_occupation_new">Occupation</label>
                                                        <input type="text" name="nomi_occupation[]" class="form-control nomi_occupation" id="nomi_occupation_new" autocomplete="off" placeholder="Enter Occupation">
                                                        <div class="autocomplete-items"></div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="occupation_code_new">Occupation Code</label>
                                                        <input type="text" name="occupation_code[]" class="form-control occupation_code" id="occupation_code_new" autocomplete="off" placeholder="Occupation Code">
                                                    </div>
                                                </div>
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="list_new">List</label>
                                                        <input type="text" name="list[]" class="form-control list" id="list_new" autocomplete="off" placeholder="List">
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="visa_subclass_new">Visa Subclass</label>
                                                        <input type="text" name="visa_subclass[]" class="form-control visa_subclass" id="visa_subclass_new" autocomplete="off" placeholder="Visa Subclass">
                                                    </div>
                                                </div>
                                                <div class="col-sm-2 date-wrapper">
                                                    <div class="form-group">
                                                        <label for="date_new">Date</label>
                                                        <input type="date" name="dates[]" class="form-control date-input" id="date_new">
                                                    </div>
                                                </div>
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="relevant_occupation_${occuIndexByDefault}">Relevant</label>
                                                        <select name="relevant_occupation[]" id="relevant_occupation_${occuIndexByDefault}" class="form-control relevantOccupationCls" required>
                                                            <option value="">Select</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <input type="hidden" name="relevant_occupation_hidden[]" id="relevant_occupation_hidden_${occuIndexByDefault}" value="">
                                                    </div>
                                                </div>
                                                <div class="col-sm-1 d-flex align-items-center">
                                                    <button type="button" class="btn btn-primary add-row-btn-skill">+</button>
                                                </div>
                                            </div>`;

                                        occuIndexByDefault++;
                                        $('#occupation-fields-wrapper').append(newOccuRowByDefault);

                                        if( $('.skillCls').length >0 ) {
                                            $('.skillCls').each(function(index) {
                                                var skill_assesment_id =  $(this).attr('id');
                                                var skill_assesment_id_arr  = skill_assesment_id.split("_");
                                                var skill_assesment_val_per_index = $('#skill_assessment_'+skill_assesment_id_arr[2]).val();
                                                $('#skill_assessment_hidden_'+skill_assesment_id_arr[2]).val(skill_assesment_val_per_index);
                                            });

                                            $(document).on('change', '.skillCls', function() {
                                                var skill_assesment_id =  $(this).attr('id');
                                                var skill_assesment_id_arr  = skill_assesment_id.split("_");
                                                var skill_assesment_val_per_index = $('#skill_assessment_'+skill_assesment_id_arr[2]).val();
                                                $('#skill_assessment_hidden_'+skill_assesment_id_arr[2]).val(skill_assesment_val_per_index);
                                            });
                                        }

                                        if( $('.relevantOccupationCls').length >0 ) {
                                            $('.relevantOccupationCls').each(function(index) {
                                                var relevant_occupation_id =  $(this).attr('id');
                                                var relevant_occupation_id_arr  = relevant_occupation_id.split("_");
                                                var relevant_occupation_val_per_index = $('#relevant_occupation_'+relevant_occupation_id_arr[2]).val();
                                                $('#relevant_occupation_hidden_'+relevant_occupation_id_arr[2]).val(relevant_occupation_val_per_index);
                                            });

                                            $(document).on('change', '.relevantOccupationCls', function() {
                                                var relevant_occupation_id =  $(this).attr('id');
                                                var relevant_occupation_id_arr  = relevant_occupation_id.split("_");
                                                var relevant_occupation_val_per_index = $('#relevant_occupation_'+relevant_occupation_id_arr[2]).val();
                                                $('#relevant_occupation_hidden_'+relevant_occupation_id_arr[2]).val(relevant_occupation_val_per_index);
                                            });
                                        }

                                    });
                                    </script>

                                    <div id="error-message" class="error-message" style="display: none;"></div>
                                </div>

                                <style>
                                    /* Custom invalid field border */
                                    .custom-invalid {
                                        border: 2px solid red;
                                    }

                                    /* Reset default Bootstrap validation styles */
                                    .is-invalid {
                                        box-shadow: none;
                                    }
                                </style>
                                <script>
                                    $(document).ready(function() {

                                        let occupationIndex = @json(count($clientOccupations)+1);
                                        //console.log('occu-index='+occupationIndex);

                                        // Function to toggle the date field based on skill assessment value
                                        function toggleDateField($row) {
                                            var skillAssessment = $row.find('.skill-assessment-input').val();
                                            var $dateInput = $row.find('.date-wrapper');
                                            if (skillAssessment === 'Yes') {
                                                $dateInput.show();
                                                //$row.find('.date-wrapper input').addClass('custom-invalid');
                                            } else {
                                                $dateInput.hide();
                                                $row.find('.date-wrapper input').removeClass('custom-invalid');
                                            }
                                        }


                                        // Initialize date field visibility for existing rows
                                        $('.occupation-fields').each(function() {
                                            toggleDateField($(this));
                                        });

                                        // Event listener for skill assessment dropdown change
                                        $(document).on('change', '.skill-assessment-input', function() {
                                            var $row = $(this).closest('.occupation-fields');
                                            toggleDateField($row);
                                        });

                                         // Validate current row
                                        /*function validateCurrentRowSkill() {
                                            var $currentRow = $('.occupation-fields:last');
                                            var nomiOccupation = $currentRow.find('input[name="nomi_occupation[]"]').val().trim();
                                            if (nomiOccupation === '') {
                                                $currentRow.addClass('error-row');
                                                return false;
                                            }
                                            return true;
                                        }*/

                                        // Function to validate and apply red border to blank fields
                                        function validateCurrentRowSkill() {
                                            var $currentRow = $('.occupation-fields:last');
                                            var isValid = true;

                                            // Validate if fields are not empty, add red border if empty
                                            $currentRow.find('input, select').each(function() {
                                                if ($(this).val() === '') {
                                                    //console.log('ifff');
                                                    $(this).addClass('custom-invalid');
                                                    isValid = false;
                                                } else { //console.log('elseee');
                                                    $(this).removeClass('custom-invalid');
                                                }

                                                if( $currentRow.find('select').val() == 'No' ){
                                                    $currentRow.find('input.date-input').removeClass('custom-invalid');
                                                } else if( $currentRow.find('select').val() == 'Yes' ) {
                                                    $currentRow.find('input.date-input').addClass('custom-invalid');
                                                }
                                            });
                                            return isValid;
                                        }



                                        // Make fields read-only
                                        function makeRowReadOnlySkill($row) {
                                            $row.find('input, select').prop('readonly', true);
                                            $row.find('select').prop('disabled', true);
                                        }

                                        // Function to add new row
                                        function addNewRowSkill() {
                                            //console.log('newrowoccupationindex='+occupationIndex);
                                            $('.occupation-fields:last').removeClass('error-row');

                                            var newOccupationRow = `<div class="occupation-fields row mb-3" id="row-${occupationIndex}">
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="skill_assessment_new">Skill</label>
                                                        <select name="skill_assessment[]" id="skill_assessment_${occupationIndex}" class="form-control skill-assessment-input skillCls" id="skill_assessment_new" required>
                                                            <option value="">Select</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <input type="hidden" name="skill_assessment_hidden[]" id="skill_assessment_hidden_${occupationIndex}" value="">
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="nomi_occupation_new">Occupation</label>
                                                        <input type="text" name="nomi_occupation[]" class="form-control nomi_occupation" id="nomi_occupation_new" autocomplete="off" placeholder="Enter Occupation">
                                                        <div class="autocomplete-items"></div>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="occupation_code_new">Occupation Code</label>
                                                        <input type="text" name="occupation_code[]" class="form-control occupation_code" id="occupation_code_new" autocomplete="off" placeholder="Occupation Code">
                                                    </div>
                                                </div>
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="list_new">List</label>
                                                        <input type="text" name="list[]" class="form-control list" id="list_new" autocomplete="off" placeholder="List">
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="visa_subclass_new">Visa Subclass</label>
                                                        <input type="text" name="visa_subclass[]" class="form-control visa_subclass" id="visa_subclass_new" autocomplete="off" placeholder="Visa Subclass">
                                                    </div>
                                                </div>
                                                <div class="col-sm-2 date-wrapper">
                                                    <div class="form-group">
                                                        <label for="date_new">Date</label>
                                                        <input type="date" name="dates[]" class="form-control date-input" id="date_new">
                                                    </div>
                                                </div>
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="relevant_occupation_${occupationIndex}">Relevant</label>
                                                        <select name="relevant_occupation[]" id="relevant_occupation_${occupationIndex}" class="form-control relevantOccupationCls" required>
                                                            <option value="">Select</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <input type="hidden" name="relevant_occupation_hidden[]" id="relevant_occupation_hidden_${occupationIndex}" value="">
                                                    </div>
                                                </div>
                                                <div class="col-sm-1 d-flex align-items-center">
                                                    <button type="button" class="btn btn-primary add-row-btn-skill">+</button>
                                                </div>
                                            </div>`;

                                            occupationIndex++;
                                            $('#occupation-fields-wrapper').append(newOccupationRow);

                                            if( $('.skillCls').length >0 ) {
                                                $('.skillCls').each(function(index) {
                                                    var skill_assesment_id =  $(this).attr('id');
                                                    var skill_assesment_id_arr  = skill_assesment_id.split("_");
                                                    var skill_assesment_val_per_index = $('#skill_assessment_'+skill_assesment_id_arr[2]).val();
                                                    $('#skill_assessment_hidden_'+skill_assesment_id_arr[2]).val(skill_assesment_val_per_index);
                                                });

                                                $(document).on('change', '.skillCls', function() {
                                                    var skill_assesment_id =  $(this).attr('id');
                                                    var skill_assesment_id_arr  = skill_assesment_id.split("_");
                                                    var skill_assesment_val_per_index = $('#skill_assessment_'+skill_assesment_id_arr[2]).val();
                                                    $('#skill_assessment_hidden_'+skill_assesment_id_arr[2]).val(skill_assesment_val_per_index);
                                                });
                                            }

                                            if( $('.relevantOccupationCls').length >0 ) {
                                                $('.relevantOccupationCls').each(function(index) {
                                                    var relevant_occupation_id =  $(this).attr('id');
                                                    var relevant_occupation_id_arr  = relevant_occupation_id.split("_");
                                                    var relevant_occupation_val_per_index = $('#relevant_occupation_'+relevant_occupation_id_arr[2]).val();
                                                    $('#relevant_occupation_hidden_'+relevant_occupation_id_arr[2]).val(relevant_occupation_val_per_index);
                                                });

                                                $(document).on('change', '.relevantOccupationCls', function() {
                                                    var relevant_occupation_id =  $(this).attr('id');
                                                    var relevant_occupation_id_arr  = relevant_occupation_id.split("_");
                                                    var relevant_occupation_val_per_index = $('#relevant_occupation_'+relevant_occupation_id_arr[2]).val();
                                                    $('#relevant_occupation_hidden_'+relevant_occupation_id_arr[2]).val(relevant_occupation_val_per_index);
                                                });
                                            }
                                            $('#error-message').hide();
                                        }


                                        // Add row on button click
                                        $(document).on('click', '.add-row-btn-skill', function() {
                                            if (validateCurrentRowSkill()) {
                                                var isConfirmed = confirm("Are you sure you want to freeze the current row?");
                                                if (isConfirmed) {
                                                    var $currentRow = $(this).closest('.occupation-fields');
                                                    makeRowReadOnlySkill($currentRow); // Make current row read-only
                                                    addNewRowSkill();
                                                    $currentRow.find('.add-row-btn-skill').prop('disabled', true);
                                                }
                                            }
                                        });

                                        // Autocomplete functionality
                                        $(document).on('input', '.nomi_occupation', function() {
                                            var occupation = $(this).val();
                                            var $row = $(this).closest('.occupation-fields');

                                            if (occupation.length > 2) {
                                                $.ajax({
                                                    url: '{{ route("clients.updateOccupation") }}',
                                                    method: 'POST',
                                                    data: { occupation: occupation },
                                                    success: function(response) {
                                                        var suggestions = response.occupations || [];
                                                        var $autocomplete = $row.find('.autocomplete-items').empty();

                                                        if (suggestions.length > 0) {
                                                            suggestions.forEach(function(suggestion) {
                                                                var $item = $('<div class="autocomplete-item"></div>')
                                                                    .text(suggestion.occupation)
                                                                    .data('occupation', {
                                                                        occupation_code: suggestion.occupation_code || '',
                                                                        list: suggestion.list || '',
                                                                        visa_subclass: suggestion.visa_subclass || ''
                                                                    })
                                                                    .appendTo($autocomplete);

                                                                $item.on('click', function() {
                                                                    var occupationData = $(this).data('occupation');
                                                                    $row.find('.nomi_occupation').val($(this).text()); // Add full text to the input
                                                                    $row.find('.occupation_code').val(occupationData.occupation_code);
                                                                    $row.find('.list').val(occupationData.list);
                                                                    $row.find('.visa_subclass').val(occupationData.visa_subclass);
                                                                    $autocomplete.empty();
                                                                });
                                                            });
                                                        } else {
                                                            $autocomplete.html('<div class="autocomplete-item">No results found</div>');
                                                        }
                                                    },
                                                    error: function() {
                                                        alert('Error fetching occupation details.');
                                                    }
                                                });
                                            } else {
                                                $row.find('.autocomplete-items').empty();
                                            }
                                        });
                                    });

                                </script>
                            </div>

                            <!------------------------------------  End occupation  --------------------------->

                            <style>
                            button.btn.btn-primary.add-test-score {
                                margin: 31px 6px 14px 14px;
                            }
                            .form-group.occupation-date-cu {
                                margin: -10px 4px 28px 4px;
                            }

                            .error-msg {
                            color: red;
                            font-size: 12px;
                            display: none;
                            }

                            </style>

                            <!------------------------------------  Start Test Scores  --------------------------->
                            <div class="col-sm-12">
                                <h5 style="color: #FFFFFF;">Test Scores</h5>
                                <div id="test-score-fields-wrapper">
                                    @if(count($testScores) > 0)
                                        @foreach($testScores as $index => $testScore)
                                            <div class="test-score-fields form-row mb-3" id="row-{{ $index }}">

                                                <input type="hidden" name="test_score_id[]"  value="<?php echo $testScore->id; ?>">

                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="test_type_{{ $index }}">Test Type <span style="color:#ff0000;">*</span></label>
                                                        <select name="test_type[]" class="form-control test-type-input testTypeCls" id="test_type_{{ $index }}" required disabled>
                                                            <option value="">Select Test Type</option>
                                                            <option value="IELTS Academic" {{ $testScore->test_type == 'IELTS Academic' ? 'selected' : '' }}>IELTS Academic</option>
                                                            <option value="IELTS General Training" {{ $testScore->test_type == 'IELTS General Training' ? 'selected' : '' }}>IELTS General Training</option>
                                                            <option value="TOEFL iBT" {{ $testScore->test_type == 'TOEFL iBT' ? 'selected' : '' }}>TOEFL iBT</option>
                                                            <option value="PTE Academic" {{ $testScore->test_type == 'PTE Academic' ? 'selected' : '' }}>PTE Academic</option>
                                                            <option value="OET" {{ $testScore->test_type == 'OET' ? 'selected' : '' }}>OET</option>
                                                        </select>
                                                        <div class="error-msg" id="test_type_error_{{ $index }}">Please select a test type</div>
                                                    </div>
                                                    <input type="hidden" name="test_type_hidden[]" class="testTypeHiddenCls" id="test_type_hidden_{{ $index }}" value="">
                                                </div>
                                                <!-- Listening Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="listening_{{ $index }}">Listening</label>
                                                        <input type="text" name="listening[]" class="form-control listening-input" id="listening_{{ $index }}" value="{{ $testScore->listening }}" readonly>
                                                        <div class="error-msg" id="listening_error_{{ $index }}"></div>
                                                    </div>
                                                </div>
                                                <!-- Reading Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="reading_{{ $index }}">Reading</label>
                                                        <input type="text" name="reading[]" class="form-control reading-input" id="reading_{{ $index }}" value="{{ $testScore->reading }}" readonly>
                                                        <div class="error-msg" id="reading_error_{{ $index }}"></div>
                                                    </div>
                                                </div>
                                                <!-- Writing Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="writing_{{ $index }}">Writing</label>
                                                        <input type="text" name="writing[]" class="form-control writing-input" id="writing_{{ $index }}" value="{{ $testScore->writing }}" readonly>
                                                        <div class="error-msg" id="writing_error_{{ $index }}"></div>
                                                    </div>
                                                </div>
                                                <!-- Speaking Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="speaking_{{ $index }}">Speaking</label>
                                                        <input type="text" name="speaking[]" class="form-control speaking-input" id="speaking_{{ $index }}" value="{{ $testScore->speaking }}" readonly>
                                                        <div class="error-msg" id="speaking_error_{{ $index }}"></div>
                                                    </div>
                                                </div>
                                                <!-- Overall Score Input -->
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="overall_score_{{ $index }}">Overall Score</label>
                                                        <input type="text" name="overall_score[]" class="form-control overall-score-input" id="overall_score_{{ $index }}" value="{{ $testScore->overall_score }}" readonly>
                                                        <div class="error-msg" id="overall_error_{{ $index }}"></div>
                                                    </div>
                                                </div>
                                                <!-- Date Input -->
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="test_date_{{ $index }}">Date of Test</label>
                                                        <input type="date" name="test_date[]" class="form-control test-date-input" id="test_date_{{ $index }}" value="{{ $testScore->test_date }}" readonly>
                                                    </div>
                                                </div>

                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="relevant_test_{{ $index }}">Relevant</label>
                                                        <select name="relevant_test[]" class="form-control relevantTestCls" id="relevant_test_{{ $index }}" required disabled>
                                                            <option value="" >Select</option>
                                                            <option value="Yes" {{ $testScore->relevant_test == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                            <option value="No"  {{ $testScore->relevant_test == 'No' ? 'selected' : '' }}>No</option>
                                                        </select>
                                                        <div class="error-msg" id="relevant_test_error_{{ $index }}">Please select</div>
                                                    </div>
                                                    <input type="hidden" name="relevant_test_hidden[]" id="relevant_test_hidden_{{ $index }}" value="">
                                                </div>
                                                <div class="col-sm-1">
                                                    <button type="button" class="btn btn-primary add-test-score" data-index="{{ $index }}" disabled>+</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif

                                    <!-- New Test Score Fields -->
                                    <script>
                                        $(document).ready(function() {
                                            var testScoreIndexAtPageLoad = @json(count($testScores));
                                            if( testScoreIndexAtPageLoad >0){
                                                var testScoreByDefault = testScoreIndexAtPageLoad;
                                            } else {
                                                var testScoreByDefault = 0;
                                            }
                                            const newTestScoreRowByDefault = `<div class="test-score-fields form-row mb-3" id="row-${testScoreByDefault}">
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="test_type_new">Test Type <span style="color:#ff0000;">*</span></label>
                                                        <select name="test_type[]" class="form-control test-type-input testTypeCls" id="test_type_${testScoreByDefault}" required>
                                                            <option value="">Select Test Type</option>
                                                            <option value="IELTS Academic">IELTS Academic</option>
                                                            <option value="IELTS General Training">IELTS General Training</option>
                                                            <option value="TOEFL iBT">TOEFL iBT</option>
                                                            <option value="PTE Academic">PTE Academic</option>
                                                            <option value="OET">OET</option>
                                                        </select>
                                                        <div class="error-msg" id="test_type_error_new">Please select a test type</div>
                                                    </div>
                                                    <input type="hidden" name="test_type_hidden[]" class="testTypeHiddenCls" id="test_type_hidden_${testScoreByDefault}" value="">
                                                </div>

                                                <!-- Listening Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="listening_new">Listening</label>
                                                        <input type="text" name="listening[]" class="form-control listening-input" id="listening_new">
                                                        <div class="error-msg" id="listening_error_new"></div>
                                                    </div>
                                                </div>

                                                <!-- Reading Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="reading_new">Reading</label>
                                                        <input type="text" name="reading[]" class="form-control reading-input" id="reading_new">
                                                        <div class="error-msg" id="reading_error_new"></div>
                                                    </div>
                                                </div>

                                                <!-- Writing Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="writing_new">Writing</label>
                                                        <input type="text" name="writing[]" class="form-control writing-input" id="writing_new">
                                                        <div class="error-msg" id="writing_error_new"></div>
                                                    </div>
                                                </div>

                                                <!-- Speaking Input -->
                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="speaking_new">Speaking</label>
                                                        <input type="text" name="speaking[]" class="form-control speaking-input" id="speaking_new">
                                                        <div class="error-msg" id="speaking_error_new"></div>
                                                    </div>
                                                </div>

                                                <!-- Overall Score Input -->
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="overall_score_new">Overall Score</label>
                                                        <input type="text" name="overall_score[]" class="form-control overall-score-input" id="overall_score_new">
                                                        <div class="error-msg" id="overall_error_new"></div>
                                                    </div>
                                                </div>

                                                <!-- Date Input -->
                                                <div class="col-sm-2">
                                                    <div class="form-group">
                                                        <label for="test_date_new">Date of Test</label>
                                                        <input type="date" name="test_date[]" class="form-control test-date-input" id="test_date_new">
                                                    </div>
                                                </div>

                                                <div class="col-sm-1">
                                                    <div class="form-group">
                                                        <label for="relevant_test_${testScoreByDefault}">Relevant</label>
                                                        <select name="relevant_test[]" class="form-control relevantTestCls" id="relevant_test_${testScoreByDefault}" required>
                                                            <option value="" >Select</option>
                                                            <option value="Yes">Yes</option>
                                                            <option value="No">No</option>
                                                        </select>
                                                        <div class="error-msg" id="relevant_test_error_${testScoreByDefault}">Please select</div>
                                                    </div>
                                                    <input type="hidden" name="relevant_test_hidden[]" class="relevantTestHiddenCls" id="relevant_test_hidden_${testScoreByDefault}" value="">
                                                </div>

                                                <div class="col-sm-1">
                                                    <button type="button" class="btn btn-primary add-test-score" data-index="new">+</button>
                                                </div>
                                            </div>`;

                                            testScoreByDefault++;
                                            $('#test-score-fields-wrapper').append(newTestScoreRowByDefault);

                                            if( $('.testTypeCls').length >0 ) {
                                                $('.testTypeCls').each(function(index) {
                                                    var test_type_id =  $(this).attr('id');
                                                    var test_type_id_arr  = test_type_id.split("_");
                                                    var test_type_val_per_index = $('#test_type_'+test_type_id_arr[2]).val();
                                                    $('#test_type_hidden_'+test_type_id_arr[2]).val(test_type_val_per_index);
                                                });

                                                $(document).on('change', '.testTypeCls', function() {
                                                    var test_type_id =  $(this).attr('id');
                                                    var test_type_id_arr  = test_type_id.split("_");
                                                    var test_type_val_per_index = $('#test_type_'+test_type_id_arr[2]).val();
                                                    $('#test_type_hidden_'+test_type_id_arr[2]).val(test_type_val_per_index);
                                                });
                                            }

                                            if( $('.relevantTestCls').length >0 ) {
                                                $('.relevantTestCls').each(function(index) {
                                                    var relevant_test_id =  $(this).attr('id');
                                                    var relevant_test_id_arr  = relevant_test_id.split("_");
                                                    var relevant_test_val_per_index = $('#relevant_test_'+relevant_test_id_arr[2]).val();
                                                    $('#relevant_test_hidden_'+relevant_test_id_arr[2]).val(relevant_test_val_per_index);
                                                });

                                                $(document).on('change', '.relevantTestCls', function() {
                                                    var relevant_test_id =  $(this).attr('id');
                                                    var relevant_test_id_arr  = relevant_test_id.split("_");
                                                    var relevant_test_val_per_index = $('#relevant_test_'+relevant_test_id_arr[2]).val();
                                                    $('#relevant_test_hidden_'+relevant_test_id_arr[2]).val(relevant_test_val_per_index);
                                                });
                                            }

                                            /*if( $('.test-score-fields').length >0 ) {
                                                var updtestScoreLength = index-1;
                                                console.log('index@@='+ index);
                                                console.log('updtestScoreLength@@@='+ updtestScoreLength);
                                                $('#row-'+index).find('input').attr('readonly', true);  // Make input fields read-only
                                                $('#row-'+index).find('select').attr('disabled', true);  // Disable select fields
                                            }*/
                                        });
                                        </script>

                                </div>
                            </div>
                            <script>
                            $(document).ready(function() {

                                //var index = {{ count($testScores) > 0 ? count($testScores) : 0 }};
                                var index = @json(count($testScores)+1);
                                //console.log('index='+index);
                                // Function to validate a single row
                                function validateRowTestScore(row) {
                                    let isValid = true;
                                    $(row).find('select[required], input[required]').each(function() {
                                        const value = $(this).val();
                                        if (!value) {
                                            isValid = false;
                                            $(this).next('.error-msg').show();
                                        } else {
                                            $(this).next('.error-msg').hide();
                                        }
                                    });
                                    return isValid;
                                }

                                // Function to add a new test score row
                                function addNewTestScoreRow() {
                                    // Validate existing rows before adding a new row
                                    let allRowsValid = true;
                                    $('#test-score-fields-wrapper .test-score-fields').each(function() {
                                        if (!validateRowTestScore(this)) {
                                            allRowsValid = false;
                                        }
                                    });

                                    if (!allRowsValid) {
                                        alert('Please fill out all required fields in the existing rows before adding a new row.');
                                        return;
                                    }
                                    var newRow = $('.test-score-fields:last').clone();
                                    //var newRow = $('#row-new-test').clone(); // Clone the new row template
                                    //newRow.removeAttr('id'); // Remove ID from cloned row to avoid duplication
                                    newRow.removeAttr('id');
                                    newRow.attr('id', 'row-'+index);
                                    newRow.find('select.testTypeCls').removeAttr('id');
                                    newRow.find('select.testTypeCls').attr('id', 'test_type_'+index);

                                    newRow.find('input.testTypeHiddenCls').removeAttr('id');
                                    newRow.find('input.testTypeHiddenCls').attr('id', 'test_type_hidden_'+index);

                                    newRow.find('select.relevantTestCls').removeAttr('id');
                                    newRow.find('select.relevantTestCls').attr('id', 'relevant_test_'+index);

                                    newRow.find('input.relevantTestHiddenCls').removeAttr('id');
                                    newRow.find('input.relevantTestHiddenCls').attr('id', 'relevant_test_hidden_'+index);


                                    //newRow.removeClass('test_score_row_0');

                                    // Update IDs and names of inputs and selects in the new row
                                    newRow.find('input, select').each(function() {
                                        var name = $(this).attr('name');
                                        var id = $(this).attr('id');
                                        if (id) {
                                            $(this).attr('id', id.replace('_new', '_' + index));
                                        }
                                        if (name) {
                                            $(this).attr('name', name.replace('[]', '[' + index + ']'));
                                        }
                                    });

                                    // Update error message IDs
                                    newRow.find('.error-msg').each(function() {
                                        var id = $(this).attr('id');
                                        if (id) {
                                            $(this).attr('id', id.replace('_new', '_' + index));
                                        }
                                    });

                                    // Reset input values
                                    newRow.find('input').val('');
                                    newRow.find('select').val('');

                                    // Append the new row to the container
                                    newRow.appendTo('#test-score-fields-wrapper');

                                    if( $('.testTypeCls').length >0 ) {
                                        $('.testTypeCls').each(function(index) {
                                            var test_type_id =  $(this).attr('id');
                                            var test_type_id_arr  = test_type_id.split("_");
                                            var test_type_val_per_index = $('#test_type_'+test_type_id_arr[2]).val();
                                            $('#test_type_hidden_'+test_type_id_arr[2]).val(test_type_val_per_index);
                                        });

                                        $(document).on('change', '.testTypeCls', function() {
                                            var test_type_id =  $(this).attr('id');
                                            var test_type_id_arr  = test_type_id.split("_");
                                            var test_type_val_per_index = $('#test_type_'+test_type_id_arr[2]).val();
                                            $('#test_type_hidden_'+test_type_id_arr[2]).val(test_type_val_per_index);
                                        });
                                    }

                                    if( $('.relevantTestCls').length >0 ) {
                                        $('.relevantTestCls').each(function(index) {
                                            var relevant_test_id =  $(this).attr('id');
                                            var relevant_test_id_arr  = relevant_test_id.split("_");
                                            var relevant_test_val_per_index = $('#relevant_test_'+relevant_test_id_arr[2]).val();
                                            $('#relevant_test_hidden_'+relevant_test_id_arr[2]).val(relevant_test_val_per_index);
                                        });

                                        $('.relevantTestCls').each(function(index) {
                                            var relevant_test_id =  $(this).attr('id');
                                            var relevant_test_id_arr  = relevant_test_id.split("_");
                                            var relevant_test_val_per_index = $('#relevant_test_'+relevant_test_id_arr[2]).val();
                                            $('#relevant_test_hidden_'+relevant_test_id_arr[2]).val(relevant_test_val_per_index);
                                        });
                                    }

                                    if( $('.test-score-fields').length >0 ) {
                                        var updindex = index-1;
                                        //console.log('index='+ index);
                                        //console.log('updindex='+ updindex);
                                        $('#test-score-fields-wrapper #row-'+updindex).find('input').attr('readonly', true);  // Make input fields read-only
                                        $('#test-score-fields-wrapper #row-'+updindex).find('select').attr('disabled', true);
                                        $('#test-score-fields-wrapper #row-'+updindex).find('button').attr('disabled', true);
                                    }
                                    // Increment index
                                    index++;
                                }

                                // Event handler for adding new rows
                                $('#test-score-fields-wrapper').on('click', '.add-test-score', function() {
                                    addNewTestScoreRow();
                                });

                                // Initial validation for the new row
                                //$('#test_type_new').on('change', function() {
                                    // Add validation logic for new rows if necessary
                                //});

                                // Function to validate input fields based on test type (existing logic)
                                function validateDecimal(input, min, max, step, errorId) {
                                    const value = parseFloat($(input).val());
                                    if (isNaN(value) || value < min || value > max || (value * 10) % (step * 10) !== 0) {
                                        showError(errorId, `Value must be between ${min} and ${max} with steps of ${step}.`);
                                    } else {
                                        hideError(errorId);
                                    }
                                }

                                function validateWholeNumber(input, min, max, errorId) {
                                    const value = parseInt($(input).val(), 10);
                                    if (isNaN(value) || value < min || value > max) {
                                        showError(errorId, `Value must be between ${min} and ${max}.`);
                                    } else {
                                        hideError(errorId);
                                    }
                                }

                                function validateIncrement(input, min, max, increment, errorId) {
                                    const value = parseFloat($(input).val());
                                    if (isNaN(value) || value < min || value > max || (value % increment !== 0)) {
                                        showError(errorId, `Value must be between ${min} and ${max} with increments of ${increment}.`);
                                    } else {
                                        hideError(errorId);
                                    }
                                }

                                function showError(errorId, message) {
                                    $('#' + errorId).text(message).show();
                                }

                                function hideError(errorId) {
                                    $('#' + errorId).hide();
                                }
                            });
                            </script>


                            <script>
                            $(document).ready(function() {

                                /*$(document).on('click', '.test-type-input', function() {
                                    const selectedTest = $(this).val();
                                    const selectedTestId = $(this).attr('id');
                                    var selectedTestIdArr = selectedTestId.split("_");
                                    //console.log('selectedTestId='+selectedTestIdArr[2]);

                                    var test_score_fields_id = $(this).closest('.test-score-fields').attr('id');
                                    //console.log('test_score_fields_id='+test_score_fields_id);

                                    var listening_input_id = $('#'+test_score_fields_id).find('.listening-input').attr('id');
                                    //console.log('listening_input_id='+listening_input_id);


                                    // Find the closest parent row
                                    var parentRow = $(this).closest('.test-score-fields');

                                    // Find the listening input within the same row
                                    var listeningInput = parentRow.find('.listening-input');

                                    // Log the listening input ID (or the element itself)
                                    console.log('Listening Input ID:', listeningInput.attr('id'));


                                    const listeningField = $('.listening-input');

                                    //const listeningField = $('#listening_new');
                                    const readingField = $('#reading_new');
                                    const writingField = $('#writing_new');
                                    const speakingField = $('#speaking_new');
                                    const overallField = $('#overall_score_new');

                                    function resetValidation() {
                                        $('.error-msg').hide();
                                        listeningField.val('');
                                        readingField.val('');
                                        writingField.val('');
                                        speakingField.val('');
                                        overallField.val('');
                                        listeningField.prop('readonly', false);
                                        readingField.prop('readonly', false);
                                        writingField.prop('readonly', false);
                                        speakingField.prop('readonly', false);
                                        overallField.prop('readonly', false);
                                    }

                                    resetValidation(); // Reset all fields when a new test type is selected

                                    // Clear previous event handlers
                                    listeningField.off('input');
                                    readingField.off('input');
                                    writingField.off('input');
                                    speakingField.off('input');
                                    overallField.off('input');

                                    // Validate based on selected test type
                                    if (selectedTest === 'IELTS Academic' || selectedTest === 'IELTS General Training') {
                                        listeningField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, 'listening_error_new');
                                        });
                                        readingField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, 'reading_error_new');
                                        });
                                        writingField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, 'writing_error_new');
                                        });
                                        speakingField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, 'speaking_error_new');
                                        });
                                        overallField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, 'overall_error_new');
                                        });
                                    } else if (selectedTest === 'PTE Academic') {
                                        listeningField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, 'listening_error_new');
                                        });
                                        readingField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, 'reading_error_new');
                                        });
                                        writingField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, 'writing_error_new');
                                        });
                                        speakingField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, 'speaking_error_new');
                                        });
                                        overallField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, 'overall_error_new');
                                        });
                                    } else if (selectedTest === 'TOEFL iBT') {
                                        listeningField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, 'listening_error_new');
                                        });
                                        readingField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, 'reading_error_new');
                                        });
                                        writingField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, 'writing_error_new');
                                        });
                                        speakingField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, 'speaking_error_new');
                                        });
                                        overallField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, 'overall_error_new');
                                        });
                                    } else if (selectedTest === 'OET') {
                                        // Allow only A, B, C, C++, D for OET fields
                                        const validGrades = ['A', 'B', 'C', 'C++', 'D'];

                                        listeningField.on('input', function() {
                                            validateGrade(this, validGrades, 'listening_error_new');
                                        });
                                        readingField.on('input', function() {
                                            validateGrade(this, validGrades, 'reading_error_new');
                                        });
                                        writingField.on('input', function() {
                                            validateGrade(this, validGrades, 'writing_error_new');
                                        });
                                        speakingField.on('input', function() {
                                            validateGrade(this, validGrades, 'speaking_error_new');
                                        });
                                        overallField.on('input', function() {
                                            validateGrade(this, validGrades, 'overall_error_new');
                                        });
                                    }
                                });

                                function validateDecimal(input, min, max, step, errorId) {
                                    const value = parseFloat($(input).val());
                                    if (isNaN(value) || value < min || value > max || (value * 10) % (step * 10) !== 0) {
                                        showError(errorId, `Value must be between ${min} and ${max} with steps of ${step}.`);
                                    } else {
                                        hideError(errorId);
                                    }
                                }

                                function validateWholeNumber(input, min, max, errorId) {
                                    const value = parseInt($(input).val(), 10);
                                    if (isNaN(value) || value < min || value > max) {
                                        showError(errorId, `Value must be between ${min} and ${max}.`);
                                    } else {
                                        hideError(errorId);
                                    }
                                }

                                // Validation function for grades A, B, C, C++, D
                                function validateGrade(input, validGrades, errorId) {
                                    const value = $(input).val(); // Keep it case-sensitive
                                    if (validGrades.includes(value)) {
                                        hideError(errorId);
                                    } else {
                                        showError(errorId, `Invalid grade. Only uppercase A, B, C, C++, D are allowed.`);
                                    }
                                }*/

                                $(document).on('change', '.test-type-input', function() {
                                    const selectedTest = $(this).val();

                                    // Find the closest parent row to get the relevant inputs
                                    const parentRow = $(this).closest('.test-score-fields');

                                    const listeningField = parentRow.find('.listening-input');
                                    const readingField = parentRow.find('.reading-input');
                                    const writingField = parentRow.find('.writing-input');
                                    const speakingField = parentRow.find('.speaking-input');
                                    const overallField = parentRow.find('.overall-input');

                                    function resetValidation() {
                                        parentRow.find('.error-msg').hide();
                                        listeningField.val('').prop('readonly', false);
                                        readingField.val('').prop('readonly', false);
                                        writingField.val('').prop('readonly', false);
                                        speakingField.val('').prop('readonly', false);
                                        overallField.val('').prop('readonly', false);
                                    }

                                    resetValidation(); // Reset all fields when a new test type is selected

                                    // Clear previous event handlers
                                    listeningField.off('input');
                                    readingField.off('input');
                                    writingField.off('input');
                                    speakingField.off('input');
                                    overallField.off('input');

                                    // Validate based on selected test type
                                    if (selectedTest === 'IELTS Academic' || selectedTest === 'IELTS General Training') {
                                        listeningField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, parentRow.find('#listening_error_new'));
                                        });
                                        readingField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, parentRow.find('#reading_error_new'));
                                        });
                                        writingField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, parentRow.find('#writing_error_new'));
                                        });
                                        speakingField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, parentRow.find('#speaking_error_new'));
                                        });
                                        overallField.on('input', function() {
                                            validateDecimal(this, 1, 9, 0.5, parentRow.find('#overall_error_new'));
                                        });
                                    } else if (selectedTest === 'PTE Academic') {
                                        listeningField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, parentRow.find('#listening_error_new'));
                                        });
                                        readingField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, parentRow.find('#reading_error_new'));
                                        });
                                        writingField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, parentRow.find('#writing_error_new'));
                                        });
                                        speakingField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, parentRow.find('#speaking_error_new'));
                                        });
                                        overallField.on('input', function() {
                                            validateWholeNumber(this, 0, 90, parentRow.find('#overall_error_new'));
                                        });
                                    } else if (selectedTest === 'TOEFL iBT') {
                                        listeningField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, parentRow.find('#listening_error_new'));
                                        });
                                        readingField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, parentRow.find('#reading_error_new'));
                                        });
                                        writingField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, parentRow.find('#writing_error_new'));
                                        });
                                        speakingField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, parentRow.find('#speaking_error_new'));
                                        });
                                        overallField.on('input', function() {
                                            validateWholeNumber(this, 0, 30, parentRow.find('#overall_error_new'));
                                        });
                                    } else if (selectedTest === 'OET') {
                                        // Allow only A, B, C, C++, D for OET fields
                                        const validGrades = ['A', 'B', 'C', 'C++', 'D'];

                                        listeningField.on('input', function() {
                                            validateGrade(this, validGrades, parentRow.find('#listening_error_new'));
                                        });
                                        readingField.on('input', function() {
                                            validateGrade(this, validGrades, parentRow.find('#reading_error_new'));
                                        });
                                        writingField.on('input', function() {
                                            validateGrade(this, validGrades, parentRow.find('#writing_error_new'));
                                        });
                                        speakingField.on('input', function() {
                                            validateGrade(this, validGrades, parentRow.find('#speaking_error_new'));
                                        });
                                        overallField.on('input', function() {
                                            validateGrade(this, validGrades, parentRow.find('#overall_error_new'));
                                        });
                                    }
                                });

                                function validateDecimal(input, min, max, step) {
                                    const value = parseFloat($(input).val());
                                    const errorElement = $(input).closest('.form-group').find('.error-msg');

                                    if (isNaN(value) || value < min || value > max || (value * 10) % (step * 10) !== 0) {
                                        errorElement.show().text(`Value must be between ${min} and ${max} with steps of ${step}.`);
                                    } else {
                                        errorElement.hide();
                                    }
                                }

                                function validateWholeNumber(input, min, max) {
                                    const value = parseInt($(input).val(), 10);
                                    const errorElement = $(input).closest('.form-group').find('.error-msg');

                                    if (isNaN(value) || value < min || value > max) {
                                        errorElement.show().text(`Value must be between ${min} and ${max}.`);
                                    } else {
                                        errorElement.hide();
                                    }
                                }

                                function validateGrade(input, validGrades) {
                                    const value = $(input).val();
                                    const errorElement = $(input).closest('.form-group').find('.error-msg');

                                    if (validGrades.includes(value)) {
                                        errorElement.hide();
                                    } else {
                                        errorElement.show().text(`Invalid grade. Only uppercase A, B, C, C++, D are allowed.`);
                                    }
                                }



                                function showError(errorId, message) {
                                    $('#' + errorId).text(message).show();
                                }

                                function hideError(errorId) {
                                    $('#' + errorId).hide();
                                }

                                // Add more validation logic for test_date if needed
                            });
                            </script>

                            <!------------------------------------  end Test Scores  --------------------------->

                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>Form with Conditional Date Input</title>
                            <style>
                            .hidden-date {
                                display: none;
                            }
                            .date-field {
                                margin-top: 10px;
                            }
                            input#naati-date-input {
                                margin: -41px 1px 13px 81px;
                            }
                            div#naati-date {
                                margin: -8px 16px 27px -18px;
                            }
                            div#py-date {
                                margin: -8px 16px 27px -18px;
                            }
                            input#py-date-input {
                                margin: -41px 1px 13px 81px;
                            }
                                .col-sm-12.date-naati-cu {
                                margin: -37px 4px 1px 153px;
                            }
                                .col-sm-12.date-ny-cu {
                                margin: -37px 4px 1px 153px;
                            }
                            select#naati-test-select {
                                margin: 1px -63px;
                            }
                            select#py-test-select{
                                margin: 1px -63px;
                            }

                            button.btn.btn-primary.add-skill-assessment {
                                margin: 32px 11px 7px;
                            }
                            </style>

                            @csrf
                            <div class="col-sm-12">
                                <!-- NAATI Test Section -->
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Have you given NAATI test?</label>
                                    <div class="col-sm-3">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <select class="form-control" name="naati_test" id="naati-test-select">
                                                    <option value="0" {{ $fetchedData->naati_test == '0' ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{ $fetchedData->naati_test == '1' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-12  date-naati-cu">
                                                <div class="date-field {{ $fetchedData->naati_test == '1' ? '' : 'hidden-date' }}" id="naati-date">
                                                    <label for="naati-date-input" class="mt-3">Test Date:</label>
                                                    <input type="date" class="form-control" id="naati-date-input" name="naati_date" value="{{ $fetchedData->naati_date }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PY Test Section -->
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Have you given PY test?</label>
                                    <div class="col-sm-3">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <select class="form-control" name="py_test" id="py-test-select">
                                                    <option value="0" {{ $fetchedData->py_test == '0' ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{ $fetchedData->py_test == '1' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                            </div>
                                            <div class="col-sm-12 date-ny-cu ">
                                                <div class="date-field {{ $fetchedData->py_test == '1' ? '' : 'hidden-date' }}" id="py-date">
                                                    <label for="py-date-input" class="mt-3">Test Date:</label>
                                                    <input type="date" class="form-control" id="py-date-input" name="py_date" value="{{ $fetchedData->py_date }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <!-- First Conditional Question -->
                                <div class="row" id="spouse-english-question" style="display:none;">
                                    <label class="col-sm-3 col-form-label" style="color: #FFFFFF;font-size: 15px;font-weight: bold;"><b>Does your spouse have an English score?</b> <?php //echo $ClientSpouseDetail->spouse_english_score; dd('@@@@');?></label>
                                    <div class="col-sm-3">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <select class="form-control" name="spouse_english_score" id="english-score-select">
                                                    <option value="0" {{ isset($ClientSpouseDetail->spouse_english_score) && $ClientSpouseDetail->spouse_english_score == '0' ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{ isset($ClientSpouseDetail->spouse_english_score) && $ClientSpouseDetail->spouse_english_score == '1' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Conditional Fields for English Score -->
                                <div id="english-score-fields" class="row col-sm-12" style="display: none;">
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="spouse_test_type">Test Type <span style="color:#ff0000;">*</span></label>
                                            <select name="spouse_test_type" class="form-control" id="spouse_test_type" required>
                                                <option value="">Select Test Type</option>
                                                <option value="IELTS Academic" {{ isset($ClientSpouseDetail->spouse_test_type) && $ClientSpouseDetail->spouse_test_type == 'IELTS Academic' ? 'selected' : '' }}>IELTS Academic</option>
                                                <option value="IELTS General Training" {{ isset($ClientSpouseDetail->spouse_test_type) && $ClientSpouseDetail->spouse_test_type == 'IELTS General Training' ? 'selected' : '' }}>IELTS General Training</option>
                                                <option value="TOEFL iBT" {{ isset($ClientSpouseDetail->spouse_test_type) && $ClientSpouseDetail->spouse_test_type == 'TOEFL iBT' ? 'selected' : '' }}>TOEFL iBT</option>
                                                <option value="PTE Academic" {{ isset($ClientSpouseDetail->spouse_test_type) && $ClientSpouseDetail->spouse_test_type == 'PTE Academic' ? 'selected' : '' }}>PTE Academic</option>
                                                <option value="OET" {{ isset($ClientSpouseDetail->spouse_test_type) && $ClientSpouseDetail->spouse_test_type == 'OET' ? 'selected' : '' }}>OET</option>
                                            </select>
                                        </div>

                                    </div>

                                    <div class="col-sm-1">
                                        <div class="form-group">
                                            <label for="spouse_listening_score">Listening</label>
                                            <input type="text" name="spouse_listening_score" class="form-control" id="spouse_listening_score" value="{{ isset($ClientSpouseDetail->spouse_listening_score) ? $ClientSpouseDetail->spouse_listening_score : '' }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-1">
                                        <div class="form-group">
                                            <label for="spouse_reading_score">Reading</label>
                                            <input type="text" name="spouse_reading_score" class="form-control" id="spouse_reading_score" value="{{ isset($ClientSpouseDetail->spouse_reading_score) ? $ClientSpouseDetail->spouse_reading_score : '' }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-1">
                                        <div class="form-group">
                                            <label for="spouse_writing_score">Writing</label>
                                            <input type="text" name="spouse_writing_score" class="form-control" id="spouse_writing_score" value="{{ isset($ClientSpouseDetail->spouse_writing_score) ? $ClientSpouseDetail->spouse_writing_score : '' }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-1">
                                        <div class="form-group">
                                            <label for="spouse_speaking_score">Speaking</label>
                                            <input type="text" name="spouse_speaking_score" class="form-control" id="spouse_speaking_score" value="{{ isset($ClientSpouseDetail->spouse_speaking_score) ? $ClientSpouseDetail->spouse_speaking_score : '' }}">
                                        </div>
                                    </div>

                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="spouse_overall_score">Overall Score</label>
                                            <input type="text" name="spouse_overall_score" class="form-control" id="spouse_overall_score" value="{{ isset($ClientSpouseDetail->spouse_overall_score) ? $ClientSpouseDetail->spouse_overall_score : '' }}">
                                        </div>
                                    </div>

                                    <!-- Additional Field: English Test Date -->
                                    <div class="col-sm-2">
                                        <div class="form-group">
                                            <label for="spouse_test_date">Test Date</label>
                                            <input type="date" name="spouse_test_date" class="form-control" id="spouse_test_date" value="{{ isset($ClientSpouseDetail->spouse_test_date) ? $ClientSpouseDetail->spouse_test_date : '' }}">
                                        </div>
                                    </div>
                                </div>

                                <!-- Second Conditional Question -->
                                <div class="row" id="spouse-skill-assessment-question" style="display:none;margin-top: 5px;">
                                    <label class="col-sm-3 col-form-label" style="color: #FFFFFF;font-size: 15px;font-weight: bold;"><b>Does your spouse have a skill assessment?</b></label>
                                    <div class="col-sm-3">
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <select class="form-control" name="spouse_skill_assessment" id="skill-assessment-select">
                                                    <option value="0" {{  isset($ClientSpouseDetail->spouse_skill_assessment) &&  $ClientSpouseDetail->spouse_skill_assessment == '0' ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{  isset($ClientSpouseDetail->spouse_skill_assessment) &&  $ClientSpouseDetail->spouse_skill_assessment == '1' ? 'selected' : '' }}>Yes</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Conditional Fields for Skill Assessment -->
                                <div id="skill-assessment-fields" class="row col-sm-12" style="display: none;">
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="spouse_skill_assessment_status">Skill Assessment Status <span style="color:#ff0000;">*</span></label>
                                            <select name="spouse_skill_assessment_status" class="form-control" id="spouse_skill_assessment_status" required>
                                                <option value="">Select</option>
                                                <option value="Yes" {{ isset($ClientSpouseDetail->spouse_skill_assessment_status) && $ClientSpouseDetail->spouse_skill_assessment_status == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                <option value="No" {{ isset($ClientSpouseDetail->spouse_skill_assessment_status) && $ClientSpouseDetail->spouse_skill_assessment_status == 'No' ? 'selected' : '' }}>No</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="spouse_nomi_occupation">Nominated Occupation</label>
                                            <input type="text" name="spouse_nomi_occupation" class="form-control" id="spouse_nomi_occupation" value="{{ isset($ClientSpouseDetail->spouse_nomi_occupation) ? $ClientSpouseDetail->spouse_nomi_occupation : '' }}">
                                        </div>
                                    </div>

                                    <!-- Additional Field: Assessment Authority -->
                                    <div class="col-sm-3">
                                        <div class="form-group">
                                            <label for="spouse_assessment_date">Assessment Date</label>
                                            <input type="date" name="spouse_assessment_date" class="form-control" id="spouse_assessment_date" value="{{ isset($ClientSpouseDetail->spouse_assessment_date) ? $ClientSpouseDetail->spouse_assessment_date : '' }}">
                                        </div>
                                    </div>
                                </div>

                                <script>
                                    $(document).ready(function() {
                                        var maritalStatus = $('#marital-status-select').val();
                                        if (maritalStatus === 'Married' || maritalStatus === 'De Facto' || maritalStatus === 'Defacto') {
                                            $('#spouse-english-question').show();
                                            $('#english-score-fields').show();
                                            $('#spouse-skill-assessment-question').show();
                                            $('#skill-assessment-fields').show();
                                        } else {
                                            $('#spouse-english-question').hide();
                                            $('#english-score-fields').hide();
                                            $('#spouse-skill-assessment-question').hide();
                                            $('#skill-assessment-fields').hide();
                                        }

                                        // Show or hide spouse questions based on Marital Status
                                        $('#marital-status-select').change(function() {
                                            var maritalStatus = $(this).val();
                                            if (maritalStatus === 'Married' || maritalStatus === 'De Facto' || maritalStatus === 'Defacto') {
                                                $('#spouse-english-question').show();
                                                $('#spouse-skill-assessment-question').show();

                                                var right_section_height = $('#clientdetailform').height();
                                                right_section_height = right_section_height+200;
                                                //console.log('right_section_height='+right_section_height);
                                                $('.right_section').css({"maxHeight":right_section_height});
                                            } else {
                                                $('#spouse-english-question').hide();
                                                $('#english-score-fields').hide();
                                                $('#spouse-skill-assessment-question').hide();
                                                $('#skill-assessment-fields').hide();
                                            }

                                        });

                                        // Show or hide English score fields based on selection
                                        $('#english-score-select').change(function() {
                                            var englishScore = $(this).val();
                                            if (englishScore === '1') { // Yes selected
                                                $('#english-score-fields').show();
                                            } else {
                                                $('#english-score-fields').hide();
                                            }
                                        });

                                        // Show or hide Skill assessment fields based on selection
                                        $('#skill-assessment-select').change(function() {
                                            var skillAssessment = $(this).val();
                                            if (skillAssessment === '1') { // Yes selected
                                                $('#skill-assessment-fields').show();
                                            } else {
                                                $('#skill-assessment-fields').hide();
                                            }
                                        });
                                    });
                                </script>


                                <script>
                                $(document).ready(function() {
                                    function toggleDateField(selectId, dateFieldId) {
                                        var selectedValue = $(selectId).val();
                                        if (selectedValue === '1') {
                                            $(dateFieldId).slideDown(); // Show the date field
                                        } else {
                                            $(dateFieldId).slideUp(); // Hide the date field
                                        }
                                    }

                                    // Initialize the form fields based on existing values
                                    function initializeForm() {
                                        toggleDateField('#naati-test-select', '#naati-date');
                                        toggleDateField('#py-test-select', '#py-date');
                                    }

                                    // Event listener for NAATI test select field
                                    $('#naati-test-select').change(function() {
                                        toggleDateField('#naati-test-select', '#naati-date');
                                    });

                                    // Event listener for PY test select field
                                    $('#py-test-select').change(function() {
                                        toggleDateField('#py-test-select', '#py-date');
                                    });

                                    // Initialize form on page load
                                    initializeForm();

                                    //Datepicker show on whole date field not only on icon
                                    $(document).on('focus click', '.field-input, .start-date-input, .finish-date-input, .job-start-date-input, .job-finish-date-input, .date-input, .test-date-input, #naati-date-input, #py-date-input, #spouse_test_date, #spouse_assessment_date', function() {
                                    this.showPicker();
                                    });
                                });
                                </script>

                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="total_points">Total Points</label>
                                        <input type="number" name="total_points" value="{{ @$fetchedData->total_points }}" class="form-control" data-valid="" autocomplete="off" placeholder="">

                                        @if ($errors->has('total_points'))
                                            <span class="custom-error" role="alert">
                                                <strong>{{ @$errors->first('total_points') }}</strong>
                                            </span>
                                        @endif
                                    </div>
                                </div>

                            </div>
                            <hr style="border-color: #000;"/>
                            <div class="row " id="internal">


                            </div>

                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="lead_source">Source <span style="color:#ff0000;">*</span></label>
                                    <select style="padding: 0px 5px;" name="source" id="lead_source" class="form-control mm-select" data-valid="">
                                        <option value="">- Source -</option>
                                        <option value="Sub Agent">Sub Agent</option>
                                        @foreach(\App\Models\Source::all() as $sources)
                                            <option value="{{$sources->name}}" @if(@$fetchedData->source == $sources->name) selected @endif>{{$sources->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('lead_source'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('lead_source') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-sm-3 is_subagent" style="display:none;">
                                <div class="form-group">
                                    <label for="subagent">Sub Agent <span class="span_req">*</span></label>
                                    <select class="form-control mm-select" name="subagent">
                                        <option>-- Choose a sub agent --</option>
                                        @foreach(\App\Models\AgentDetails::where('status', 1)->orderBy('agent_name')->get() as $agentlist)
                                            <option <?php if(@$fetchedData->agent_id == $agentlist->id){ echo 'selected'; } ?> value="{{$agentlist->id}}">{{$agentlist->agent_name ?? $agentlist->business_name ?? 'Agent'}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('subagent'))
                                        <span class="custom-error" role="alert">
                                            <strong>{{ @$errors->first('subagent') }}</strong>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label for="tags_label">Tags/Label </label>
                                    <?php
                                    $explodee = [];
                                    if($fetchedData->tagname != ''){
                                        $explodee = explode(',', $fetchedData->tagname);
                                    }
                                    
                                    // Separate normal and red tags
                                    $normalTags = \App\Models\Tag::normal()->orderBy('name')->get();
                                    $redTags = \App\Models\Tag::red()->orderBy('name')->get();
                                    ?>
                                    <select multiple class="form-control mm-select" name="tagname[]">
                                        <option value="">-- Search & Select tag --</option>
                                        @if($normalTags->count() > 0)
                                            <optgroup label="Normal Tags">
                                                @foreach($normalTags as $tags)
                                                    <option <?php if(in_array($tags->id, $explodee)){ echo 'selected'; } ?> value="{{$tags->id}}">{{$tags->name}}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if($redTags->count() > 0)
                                            <optgroup label="Red Tags (Hidden)">
                                                @foreach($redTags as $tags)
                                                    <option <?php if(in_array($tags->id, $explodee)){ echo 'selected'; } ?> value="{{$tags->id}}" style="color: #dc3545;">{{$tags->name}} {!! \App\Helpers\IconHelper::fromLegacy('fas fa-exclamation-triangle') !!}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    </select>
                                    <small class="form-text text-muted">
                                        @icon('fa-info-circle') Red tags are hidden by default on client detail pages.
                                    </small>
                                </div>
                            </div>

                            @endif
                            <div class="col-sm-12">
                                <div class="form-group float-right">
                                    <button type="submit" class="btn btn-primary btn_submit" onClick="customValidate('edit-clients')">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
