<?php

namespace App\Traits;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Models\Admin;
use App\Models\Staff;
use App\Models\Company;
use App\Models\Lead;
use App\Models\ActivitiesLog;
// use App\Models\OnlineForm; // REMOVED: OnlineForm model has been deleted
use Illuminate\Support\Facades\Auth;
use App\Helpers\PhoneHelper;
use App\Helpers\IconHelper;
use App\Models\CheckinLog;
use App\Models\Note;
use App\Models\BookingAppointment;
// clientServiceTaken model removed - table client_service_takens does not exist
use App\Models\AccountClientReceipt;

use App\Models\Matter;
use App\Models\ClientMatter;
use App\Models\Branch;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use App\Services\ClientReferenceService;
use App\Services\MergeClientRecordsService;
use App\Support\ActionTaskGroup;
use App\Support\AppointmentActivityDescription;
use App\Support\NoteDescriptionHtml;
use App\Support\StaffClientVisibility;
use App\Support\WorkflowAssignment;
use App\Services\LegalCrm\LegalCrmApiClient;

use DateTime;
use DateTimeZone;

use App\Models\ClientAddress; // Import the ClientAddress model
use App\Models\ClientContact; // Import the ClientAddress model
use App\Models\ClientEmail; // Import the ClientAddress model
use App\Models\ClientQualification; // Import the ClientAddress model
use App\Models\ClientExperience; // Import the ClientAddress model
use App\Models\ClientTestScore; // Import the ClientAddress model
use App\Models\ClientVisaCountry; // Import the ClientAddress model
use App\Models\ClientOccupation; // Import the ClientAddress model
use App\Models\ClientSpouseDetail; // Import the ClientAddress model
use App\Models\AppointmentConsultant; // Import the AppointmentConsultant model
use App\Support\BansalSchedulingServiceType;

use App\Models\ClientPoint;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;

use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientRelationship;
use App\Models\EmailTemplate;
use App\Models\SmsTemplate;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

use App\Models\Form956;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Models\CostAssignmentForm;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use App\Models\ClientEoiReference;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Mail\HubdocInvoiceMail;
use App\Services\MatterEmailBodyCleanupService;
use App\Services\EmailLogListService;
use App\Services\Sms\UnifiedSmsManager;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\ClientExportService;
use App\Services\ClientLeadListExportService;
use App\Services\FCMService;
use App\Services\ClientImportService;
use App\Services\JobReadyAgreementFeeTablePatcher;
use App\Services\PsaAgreementSection4SummaryTablePatcher;
use App\Services\PsaAgreementServiceTypeRowPatcher;
use App\Services\VisaAgreementAmountTablePatcher;
use App\Services\VisaAgreementServiceTypeRowPatcher;
use App\Services\CompanyAgreementDocxPatcher;
use App\Services\CompanyVisaAgreementMacroBuilder;
use App\Services\VisaAgreementApplicantAddressResolver;
use App\Services\VisaAgreementTemplateResolver;
use App\Traits\ClientAuthorization;
use App\Traits\ClientHelpers;
use App\Traits\ClientQueries;
use App\Traits\LogsClientActivity;

trait CreatesClients
{

    // NOTE: Client creation is done via lead conversion, not direct creation
    // The create() method has been removed as clients are created by converting leads
    // See: LeadConversionController for lead-to-client conversion

    public function store(Request $request)
    {   //dd($request->all());
        $requestData = $request->all();
        
        try {
            // Validate the request data
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'dob' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The date of birth cannot be a future date.');
                            }
                        } catch (\Exception $e) {
                            // Format validation handles invalid dates
                        }
                    }
                ],
                'dob_verified' => 'nullable|in:1',
                'dob_verify_document' => 'nullable|string|max:255',
                'age' => 'nullable|string',
                'gender' => 'nullable|in:Male,Female,Other',
                'marital_status' => 'nullable|in:Never Married,Engaged,Married,De Facto,Separated,Divorced,Widowed',

                'phone_verified' => 'nullable|in:1',
                'contact_type_hidden.*' => 'nullable|in:Personal,Office,Work,Mobile,Business,Secondary,Father,Mother,Brother,Sister,Uncle,Aunt,Cousin,Others,Partner,Not In Use',
                'country_code.*' => 'nullable|string|max:10',
                'phone.*' => 'nullable|string|max:20',
                'email_type_hidden.*' => 'nullable|in:Personal,Work,Business,Secondary,Additional,Sister,Brother,Father,Mother,Uncle,Auntie',
                'email.*' => 'nullable|email|max:255',
                'visa_country.*' => 'nullable|string|max:255',
                'passports.*.passport_number' => 'nullable|string|max:50',
                'passports.*.issue_date' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The document issue date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'passports.*.expiry_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'visas.*.visa_type' => 'nullable|exists:matters,id',
                'visas.*.expiry_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'visas.*.grant_date' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The visa grant date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'visas.*.description' => 'nullable|string|max:255',
                'visa_expiry_verified' => 'nullable|in:1',
                'is_current_address' => 'nullable|in:1',
                'address.*' => 'nullable|string|max:1000',
                'zip.*' => 'nullable|string|max:20',
                'regional_code.*' => 'nullable|string|max:50',
                'address_start_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The address start date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'address_end_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The address end date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'travel_country_visited.*' => 'nullable|string|max:255',
                'travel_arrival_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The travel arrival date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'travel_departure_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The travel departure date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'travel_purpose.*' => 'nullable|string|max:500',
                'level_hidden.*' => 'nullable|string|max:255',
                'name.*' => 'nullable|string|max:255',
                'country_hidden.*' => 'nullable|string|max:255',
                'start_date.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'finish_date.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'relevant_qualification_hidden.*' => 'nullable|in:1',
                'job_title.*' => 'nullable|string|max:255',
                'job_code.*' => 'nullable|string|max:50',
                'job_country_hidden.*' => 'nullable|string|max:255',
                'job_start_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The employment start date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'job_finish_date.*' => [
                    'nullable',
                    'regex:/^\d{2}\/\d{2}\/\d{4}$/',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The employment finish date cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'relevant_experience_hidden.*' => 'nullable|in:1',
                'nomi_occupation.*' => 'nullable|string|max:500',
                'occupation_code.*' => 'nullable|string|max:500',
                'list.*' => 'nullable|string|max:500',
                'visa_subclass.*' => 'nullable|string|max:500',
                'dates.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'expiry_dates.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'relevant_occupation_hidden.*' => 'nullable|in:1',
                'test_type_hidden.*' => 'nullable|in:IELTS,IELTS_A,PTE,TOEFL,CAE,OET,CELPIP,MET,LANGUAGECERT',
                'listening.*' => 'nullable|string|max:10',
                'reading.*' => 'nullable|string|max:10',
                'writing.*' => 'nullable|string|max:10',
                'speaking.*' => 'nullable|string|max:10',
                'overall_score.*' => 'nullable|string|max:10',
                'test_date.*' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'relevant_test_hidden.*' => 'nullable|in:1',
                'naati_test' => 'nullable|in:1',
                'naati_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'py_test' => 'nullable|in:1',
                'py_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'spouse_has_english_score' => 'nullable|in:Yes,No',
                'spouse_has_skill_assessment' => 'nullable|in:Yes,No',
                'spouse_test_type' => 'nullable|in:IELTS,IELTS_A,PTE,TOEFL,CAE,OET,CELPIP,MET,LANGUAGECERT',
                'spouse_listening_score' => 'nullable|string|max:10',
                'spouse_reading_score' => 'nullable|string|max:10',
                'spouse_writing_score' => 'nullable|string|max:10',
                'spouse_speaking_score' => 'nullable|string|max:10',
                'spouse_overall_score' => 'nullable|string|max:10',
                'spouse_test_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'spouse_skill_assessment_status' => 'nullable|string|max:255',
                'spouse_nomi_occupation' => 'nullable|string|max:255',
                'spouse_assessment_date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'criminal_charges.*.details' => 'nullable|string|max:1000',
                'criminal_charges.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'military_service.*.details' => 'nullable|string|max:1000',
                'military_service.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'intelligence_work.*.details' => 'nullable|string|max:1000',
                'intelligence_work.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'visa_refusals.*.details' => 'nullable|string|max:1000',
                'visa_refusals.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'deportations.*.details' => 'nullable|string|max:1000',
                'deportations.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'citizenship_refusals.*.details' => 'nullable|string|max:1000',
                'citizenship_refusals.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'health_declarations.*.details' => 'nullable|string|max:1000',
                'health_declarations.*.date' => 'nullable|regex:/^\d{2}\/\d{2}\/\d{4}$/',
                'source' => 'nullable|in:SubAgent,Others',
                'partner_details.*' => 'nullable|string|max:255',
                'partner_relationship_type.*' => 'nullable|in:Husband,Wife,Ex-Husband,Ex-Wife,Defacto,Engaged',
                'partner_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'partner_email.*' => 'nullable|email|max:255',
                'partner_first_name.*' => 'nullable|string|max:255',
                'partner_last_name.*' => 'nullable|string|max:255',
                'partner_phone.*' => 'nullable|string|max:20',
                'children_details.*' => 'nullable|string|max:255',
                'children_relationship_type.*' => 'nullable|in:Son,Daughter,Step Son,Step Daughter',
                'children_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'children_email.*' => 'nullable|email|max:255',
                'children_first_name.*' => 'nullable|string|max:255',
                'children_last_name.*' => 'nullable|string|max:255',
                'children_phone.*' => 'nullable|string|max:20',
                'parent_details.*' => 'nullable|string|max:255',
                'parent_relationship_type.*' => 'nullable|in:Father,Mother,Step Father,Step Mother,Mother-in-law,Father-in-law',
                'parent_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'parent_email.*' => 'nullable|email|max:255',
                'parent_first_name.*' => 'nullable|string|max:255',
                'parent_last_name.*' => 'nullable|string|max:255',
                'parent_phone.*' => 'nullable|string|max:20',
                'siblings_details.*' => 'nullable|string|max:255',
                'siblings_relationship_type.*' => 'nullable|in:Brother,Sister,Step Brother,Step Sister',
                'siblings_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'siblings_email.*' => 'nullable|email|max:255',
                'siblings_first_name.*' => 'nullable|string|max:255',
                'siblings_last_name.*' => 'nullable|string|max:255',
                'siblings_phone.*' => 'nullable|string|max:20',
                'others_details.*' => 'nullable|string|max:255',
                'others_relationship_type.*' => 'nullable|in:Cousin,Friend,Uncle,Aunt,Grandchild,Granddaughter,Grandparent,Niece,Nephew,Grandfather,Son-in-law,Daughter-in-law,Brother-in-law,Sister-in-law',
                'others_company_type.*' => 'nullable|in:Accompany Member,Non-Accompany Member',
                'others_email.*' => 'nullable|email|max:255',
                'others_first_name.*' => 'nullable|string|max:255',
                'others_last_name.*' => 'nullable|string|max:255',
                'others_phone.*' => 'nullable|string|max:20',
                'type' => 'required|in:lead,client',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        }

        // Custom validation: Check if at least one unique email is provided
        if (empty($validated['email']) || !array_filter($validated['email'])) {
            return redirect()->back()
                ->withErrors(['email' => 'At least one email address is required.'])
                ->withInput();
        }

        // Check if at least one email is unique (not already in database)
        $hasUniqueEmail = false;
        foreach ($validated['email'] as $email) {
            if (!empty($email) && !Admin::where('email', $email)->exists()) {
                $hasUniqueEmail = true;
                break;
            }
        }
        
        if (!$hasUniqueEmail) {
            return redirect()->back()
                ->withErrors(['email' => 'At least one unique email address is required.'])
                ->withInput();
        }

        // Custom validation: Check if at least one phone is provided
        if (empty($validated['phone']) || !array_filter($validated['phone'])) {
            return redirect()->back()
                ->withErrors(['phone' => 'At least one phone number is required.'])
                ->withInput();
        }

        // Check if at least one phone is unique (not already in database)
        $hasUniquePhone = false;
        foreach ($validated['phone'] as $index => $phone) {
            if (!empty($phone)) {
                $countryCode = $validated['country_code'][$index] ?? '';
                $fullPhone = $countryCode . $phone;
                if (!Admin::where('phone', $fullPhone)->exists()) {
                    $hasUniquePhone = true;
                    break;
                }
            }
        }
        
        if (!$hasUniquePhone) {
            return redirect()->back()
                ->withErrors(['phone' => 'At least one unique phone number is required.'])
                ->withInput();
        }

        // Check for duplicate Personal phone types
        if (!empty($validated['contact_type_hidden'])) {
            $personalPhoneCount = array_count_values($validated['contact_type_hidden'])['Personal'] ?? 0;
            if ($personalPhoneCount > 1) {
                return redirect()->back()->withErrors(['phone' => 'Only one phone number can be marked as Personal.'])->withInput();
            }
        }

        foreach ($validated['phone'] as $index => $phone) {
            if (!empty($phone)) {
                if (PhoneHelper::formatForStorage($validated['country_code'][$index] ?? '') === '') {
                    return redirect()->back()
                        ->withErrors(['country_code' => 'Please select a valid country code for each phone number.'])
                        ->withInput();
                }
            }
        }

        // Check for duplicate Personal email types
        if (!empty($validated['email_type_hidden'])) {
            $personalEmailCount = array_count_values($validated['email_type_hidden'])['Personal'] ?? 0;
            if ($personalEmailCount > 1) {
                return redirect()->back()->withErrors(['email' => 'Only one email address can be marked as Personal.'])->withInput();
            }
        }

        // Custom validation: DOB Verify Document is required when DOB is verified
        if (isset($validated['dob_verified']) && $validated['dob_verified'] === '1' && empty($requestData['dob_verify_document'])) {
            return redirect()->back()
                ->withErrors(['dob_verify_document' => 'DOB Verify Document is required when DOB is verified.'])
                ->withInput();
        }

        // Get the last email and email type
        $lastEmail = null;
        $lastEmailType = null;
        if (!empty($validated['email_type_hidden']) && !empty($validated['email'])) {
            $emailCount = count($validated['email']);
            for ($i = $emailCount - 1; $i >= 0; $i--) {
                if (!empty($validated['email'][$i])) {
                    $lastEmail = $validated['email'][$i];
                    $lastEmailType = $validated['email_type_hidden'][$i];
                    break;
                }
            }
        }

        // Get the last contact type and phone number
        $lastContactType = null;
        $lastPhone = null;
        $lastCountryCode = null;
        if (!empty($validated['contact_type_hidden']) && !empty($validated['phone'])) {
            $phoneCount = count($validated['phone']);
            for ($i = $phoneCount - 1; $i >= 0; $i--) {
                if (!empty($validated['phone'][$i])) {
                    $lastContactType = $validated['contact_type_hidden'][$i];
                    $lastCountryCode = PhoneHelper::formatForStorage($validated['country_code'][$i] ?? '');
                    $lastPhone = $validated['phone'][$i];
                    break;
                }
            }
        }

        // Handle special cases for duplicate email and phone
        $timestamp = time();
        $modifiedEmail = $lastEmail;
        $modifiedPhone = $lastPhone;
        $emailModified = false;
        $phoneModified = false;

                        // Check for duplicate email and handle special case
                if ($lastEmail) {
                    if (Admin::where('email', $lastEmail)->exists()) {
                        // Special case: allow demo@gmail.com to be duplicated with timestamp
                        if ($lastEmail === 'demo@gmail.com') {
                            // Add timestamp to local part (before @ symbol)
                            $emailParts = explode('@', $lastEmail);
                            $localPart = $emailParts[0];
                            $domainPart = $emailParts[1];
                            $modifiedEmail = $localPart . '_' . $timestamp . '@' . $domainPart;
                            $emailModified = true;
                        } else {
                            return redirect()->back()->withErrors(['email' => 'The provided email is already in use.'])->withInput();
                        }
                    }
                }

        // Check for duplicate phone and handle special case
        if ($lastPhone) {
            if (Admin::where('phone', $lastPhone)->exists()) {
                // Special case: allow 4444444444 to be duplicated with timestamp
                if ($lastPhone === '4444444444') {
                    $modifiedPhone = $lastPhone . '_' . $timestamp;
                    $phoneModified = true;
                } else {
                    return redirect()->back()->withErrors(['phone' => 'The provided phone number is already in use.'])->withInput();
                }
            }
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Generate client_counter and client_id using centralized service
            // This prevents race conditions and duplicate references
            $referenceService = app(ClientReferenceService::class);
            $reference = $referenceService->generateClientReference($validated['first_name']);
            $client_id = $reference['client_id'];
            $client_current_counter = $reference['client_counter'];

            // Create the main client/lead record in the admins table
            // Use Lead model if type is 'lead', otherwise use Admin model for clients
            $client = ($validated['type'] === 'lead') ? new \App\Models\Lead() : new Admin();
            $client->first_name = $validated['first_name'];
            $client->last_name = $validated['last_name'] ?? null;
            $client->dob = $validated['dob'] ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['dob']))) : null;

            $currentDateTime = \Carbon\Carbon::now();
            $currentUserId = Auth::user()->id;

            // DOB verification
            if (isset($validated['dob_verified']) && $validated['dob_verified'] === '1') {
                $client->dob_verified_date = $currentDateTime;
                $client->dob_verified_by = $currentUserId;
                
                // Recalculate age when DOB is verified (ensures age is current)
                if ($client->dob && $client->dob !== null) {
                    try {
                        $dobDate = \Carbon\Carbon::parse($client->dob);
                        $client->age = $dobDate->diff(\Carbon\Carbon::now())->format('%y years %m months');
                    } catch (\Exception $e) {
                        // If calculation fails, use provided age or keep existing
                        $client->age = $validated['age'] ?? null;
                    }
                } else {
                    $client->age = $validated['age'] ?? null;
                }
            } else {
                $client->dob_verified_date = null;
                $client->dob_verified_by = null;
                $client->age = $validated['age'] ?? null;
            }
            $client->gender = $validated['gender'] ?? null;
            $client->marital_status = $validated['marital_status'] ?? null;
            $client->country_passport = $validated['visa_country'][0] ?? null;
            $client->client_counter = $client_current_counter;
            $client->client_id = $client_id;
            $client->email = $modifiedEmail;
            $client->email_type = $lastEmailType ?? null;


            $client->country_code = $lastCountryCode ?? null;
            $client->contact_type = $lastContactType ?? null;
            $client->phone = $modifiedPhone;

            if (isset($validated['phone_verified']) && $validated['phone_verified'] === '1') {
                $client->phone_verified_date = $currentDateTime;
                $client->phone_verified_by = $currentUserId;
            } else {
                $client->phone_verified_date = null;
                $client->phone_verified_by = null;
            }

            $client->naati_test = isset($validated['naati_test']) ? 1 : 0;
            $client->naati_date = $validated['naati_date'] ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['naati_date']))) : null;
            $client->py_test = isset($validated['py_test']) ? 1 : 0;
            $client->py_date = $validated['py_date'] ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['py_date']))) : null;
            $client->source = $validated['source'] ?? null;
            $client->type = $validated['type'];
            // Staff id for lead list visibility (restrictLeadListQuery) and assignment; same for clients
            $client->user_id = $currentUserId;

            $client->dob_verify_document = $requestData['dob_verify_document'];

            $client->created_at = now();
            $client->updated_at = now();

            // Visa Expiry Verification
            if (isset($validated['visa_expiry_verified']) && $validated['visa_expiry_verified'] === '1') {
                if (isset($validated['visa_country'][0]) && $validated['visa_country'][0] === 'Australia') {
                    $client->visa_expiry_verified_at = null;
                    $client->visa_expiry_verified_by = null;
                } else {
                    $client->visa_expiry_verified_at = $currentDateTime;
                    $client->visa_expiry_verified_by = $currentUserId;
                }
            } else {
                $client->visa_expiry_verified_at = null;
                $client->visa_expiry_verified_by = null;
            }

            $client->save();

            // Save phone numbers
            if (!empty($validated['contact_type_hidden']) && !empty($validated['phone'])) {
                foreach ($validated['contact_type_hidden'] as $index => $contact_type) {
                    if (!empty($validated['phone'][$index])) {
                        $phoneToSave = $validated['phone'][$index];
                        
                        // If this is the last phone and it was modified, use the modified version
                        if ($index === array_key_last($validated['phone']) && $phoneModified) {
                            $phoneToSave = $modifiedPhone;
                        }
                        
                        ClientContact::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'contact_type' => $contact_type,
                            'country_code' => PhoneHelper::formatForStorage($validated['country_code'][$index] ?? ''),
                            'phone' => $phoneToSave,
                            'is_verified' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save email addresses
            if (!empty($validated['email_type_hidden']) && !empty($validated['email'])) {
                foreach ($validated['email_type_hidden'] as $index => $email_type) {
                    if (!empty($validated['email'][$index])) {
                        $emailToSave = $validated['email'][$index];
                        
                        // If this is the last email and it was modified, use the modified version
                        if ($index === array_key_last($validated['email']) && $emailModified) {
                            $emailToSave = $modifiedEmail;
                        }
                        
                        ClientEmail::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'email_type' => $email_type,
                            'email' => $emailToSave,
                            'is_verified' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save passports
            if (!empty($validated['passports'])) {
                foreach ($validated['passports'] as $index => $passport) {
                    if (!empty($passport['passport_number'])) {
                        ClientPassportInformation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'passport' => $passport['passport_number'],
                            'passport_issue_date' => !empty($passport['issue_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $passport['issue_date'])))
                                : null,
                            'passport_expiry_date' => !empty($passport['expiry_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $passport['expiry_date'])))
                                : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save visa details
            if (!empty($validated['visas']) && isset($validated['visa_country'][0]) && $validated['visa_country'][0] !== 'Australia') {
                foreach ($validated['visas'] as $index => $visa) {
                    if (!empty($visa['visa_type'])) {
                        ClientVisaCountry::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'visa_type' => $visa['visa_type'],
                            'visa_expiry_date' => !empty($visa['expiry_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $visa['expiry_date'])))
                                : null,
                            'visa_grant_date' => !empty($visa['grant_date'])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $visa['grant_date'])))
                                : null,
                            'visa_description' => $visa['description'] ?? null,
                            'visa_expiry_verified_at' => isset($validated['visa_expiry_verified']) ? now() : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save addresses
            if (!empty($validated['address'])) {
                $count = count($validated['address']);
                if ($count > 0) {
                    $lastIndex = $count - 1;
                    $lastAddress = $validated['address'][$lastIndex];
                    $lastZip = $validated['zip'][$lastIndex];

                    if (!empty($lastAddress) || !empty($lastZip)) {
                        $client->address = $lastAddress;
                        $client->zip = $lastZip;
                        $client->save();
                    }

                    $isCurrentAddress = isset($validated['is_current_address']) && $validated['is_current_address'] === '1';
                    $reversedKeys = array_reverse(array_keys($validated['address']));
                    $lastIndexInLoop = count($reversedKeys) - 1;

                    foreach ($reversedKeys as $index => $key) {
                        $addr = $validated['address'][$key] ?? null;
                        $zip = $validated['zip'][$key] ?? null;
                        $regional_code = $validated['regional_code'][$key] ?? null;
                        $start_date = $validated['address_start_date'][$key] ?? null;
                        $end_date = $validated['address_end_date'][$key] ?? null;

                        $formatted_start_date = null;
                        if (!empty($start_date)) {
                            try {
                                $date = \Carbon\Carbon::createFromFormat('d/m/Y', $start_date);
                                $formatted_start_date = $date->format('Y-m-d');
                            } catch (\Exception $e) {
                                throw new \Exception('Invalid Address Start Date format: ' . $start_date);
                            }
                        }

                        $formatted_end_date = null;
                        if (!empty($end_date)) {
                            try {
                                $date = \Carbon\Carbon::createFromFormat('d/m/Y', $end_date);
                                $formatted_end_date = $date->format('Y-m-d');
                            } catch (\Exception $e) {
                                throw new \Exception('Invalid Address End Date format: ' . $end_date);
                            }
                        }

                        if (!empty($addr) || !empty($zip)) {
                            $isCurrent = ($index === $lastIndexInLoop && $isCurrentAddress) ? 1 : 0;
                            ClientAddress::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'address' => $addr,
                                'zip' => $zip,
                                'regional_code' => $regional_code,
                                'start_date' => $formatted_start_date,
                                'end_date' => $formatted_end_date,
                                'is_current' => $isCurrent,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // Save travel history
            if (!empty($validated['travel_country_visited'])) {
                foreach ($validated['travel_country_visited'] as $index => $country) {
                    if (!empty($country)) {
                        ClientTravelInformation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'travel_country_visited' => $country,
                            'travel_arrival_date' => !empty($validated['travel_arrival_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['travel_arrival_date'][$index])))
                                : null,
                            'travel_departure_date' => !empty($validated['travel_departure_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['travel_departure_date'][$index])))
                                : null,
                            'travel_purpose' => $validated['travel_purpose'][$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
                
                // Log activity for travel history creation
                $newTravels = ClientTravelInformation::where('client_id', $client->id)->get();
                $travelDisplay = [];
                foreach ($newTravels as $travel) {
                    $display = [];
                    if ($travel->travel_country_visited) {
                        $display[] = 'Country: ' . $travel->travel_country_visited;
                    }
                    if ($travel->travel_arrival_date) {
                        $display[] = 'Arrival: ' . date('d/m/Y', strtotime($travel->travel_arrival_date));
                    }
                    if ($travel->travel_departure_date) {
                        $display[] = 'Departure: ' . date('d/m/Y', strtotime($travel->travel_departure_date));
                    }
                    if ($travel->travel_purpose) {
                        $display[] = 'Purpose: ' . $travel->travel_purpose;
                    }
                    $travelDisplay[] = !empty($display) ? implode(', ', $display) : 'Travel record';
                }
                $travelDisplayStr = !empty($travelDisplay) ? implode(' | ', $travelDisplay) : '(empty)';
                
                $this->logClientActivityWithChanges(
                    $client->id,
                    'added travel information',
                    ['Travel Information' => [
                        'old' => '(empty)',
                        'new' => $travelDisplayStr
                    ]],
                    'activity'
                );
            }

            // Save qualifications
            if (!empty($validated['level_hidden'])) {
                foreach ($validated['level_hidden'] as $index => $level) {
                    if (!empty($level)) {
                        ClientQualification::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'level' => $level,
                            'name' => $validated['name'][$index] ?? null,
                            'qual_college_name' => $requestData['qual_college_name'][$index] ?? null,
                            'qual_campus' => $requestData['qual_campus'][$index] ?? null,
                            'country' => $validated['country_hidden'][$index] ?? null,
                            'qual_state' => $requestData['qual_state'][$index] ?? null,
                            'start_date' => !empty($validated['start_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['start_date'][$index])))
                                : null,
                            'finish_date' => !empty($validated['finish_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['finish_date'][$index])))
                                : null,
                            'relevant_qualification' => isset($validated['relevant_qualification_hidden'][$index]) ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save work experiences
            if (!empty($validated['job_title'])) {
                foreach ($validated['job_title'] as $index => $job_title) {
                    if (!empty($job_title)) {
                        ClientExperience::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'job_title' => $job_title,
                            'job_code' => $validated['job_code'][$index] ?? null,
                            'job_country' => $validated['job_country_hidden'][$index] ?? null,
                            'job_start_date' => !empty($validated['job_start_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['job_start_date'][$index])))
                                : null,
                            'job_finish_date' => !empty($validated['job_finish_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['job_finish_date'][$index])))
                                : null,
                            'relevant_experience' => isset($validated['relevant_experience_hidden'][$index]) ? 1 : 0,
                            'job_emp_name' => $requestData['job_emp_name'][$index] ?? null,
                            'job_state' => $requestData['job_state'][$index] ?? null,
                            'job_type' => $requestData['job_type'][$index] ?? null,
                            'fte_multiplier' => 1.00,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save occupations
            if (!empty($validated['nomi_occupation'])) {
                foreach ($validated['nomi_occupation'] as $index => $nomi_occupation) {
                    if (!empty($nomi_occupation)) {
                        ClientOccupation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'nomi_occupation' => $nomi_occupation,
                            'occupation_code' => $validated['occupation_code'][$index] ?? null,
                            'list' => $validated['list'][$index] ?? null,
                            //'visa_subclass' => $validated['visa_subclass'][$index] ?? null,
                            'occ_reference_no' => $requestData['occ_reference_no'][$index] ?? null,
                            'dates' => !empty($validated['dates'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['dates'][$index])))
                                : null,
                            'expiry_dates' => !empty($validated['expiry_dates'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['expiry_dates'][$index])))
                                : null,
                            //'relevant_occupation' => isset($validated['relevant_occupation_hidden'][$index]) ? 1 : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save test scores
            if (!empty($validated['test_type_hidden'])) {
                foreach ($validated['test_type_hidden'] as $index => $test_type) {
                    if (!empty($test_type)) {
                        ClientTestScore::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'test_type' => $test_type,
                            'listening' => $validated['listening'][$index] ?? null,
                            'reading' => $validated['reading'][$index] ?? null,
                            'writing' => $validated['writing'][$index] ?? null,
                            'speaking' => $validated['speaking'][$index] ?? null,
                            'overall_score' => $validated['overall_score'][$index] ?? null,
                            'test_date' => !empty($validated['test_date'][$index])
                                ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['test_date'][$index])))
                                : null,
                            'relevant_test' => isset($validated['relevant_test_hidden'][$index]) ? 1 : 0,
                            'test_reference_no' => $requestData['test_reference_no'][$index] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            // Save spouse details
            if (isset($validated['marital_status']) && $validated['marital_status'] === 'Married') {
                ClientSpouseDetail::create([
                    'client_id' => $client->id,
                    'admin_id' => Auth::user()->id,
                    'spouse_has_english_score' => $validated['spouse_has_english_score'] ?? 'No',
                    'spouse_has_skill_assessment' => $validated['spouse_has_skill_assessment'] ?? 'No',
                    'spouse_test_type' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_test_type'] ?? null) : null,
                    'spouse_listening_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_listening_score'] ?? null) : null,
                    'spouse_reading_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_reading_score'] ?? null) : null,
                    'spouse_writing_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_writing_score'] ?? null) : null,
                    'spouse_speaking_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_speaking_score'] ?? null) : null,
                    'spouse_overall_score' => $validated['spouse_has_english_score'] === 'Yes' ? ($validated['spouse_overall_score'] ?? null) : null,
                    'spouse_test_date' => $validated['spouse_has_english_score'] === 'Yes' && !empty($validated['spouse_test_date'])
                        ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['spouse_test_date'])))
                        : null,
                    'spouse_skill_assessment_status' => $validated['spouse_has_skill_assessment'] === 'Yes' ? ($validated['spouse_skill_assessment_status'] ?? null) : null,
                    'spouse_nomi_occupation' => $validated['spouse_has_skill_assessment'] === 'Yes' ? ($validated['spouse_nomi_occupation'] ?? null) : null,
                    'spouse_assessment_date' => $validated['spouse_has_skill_assessment'] === 'Yes' && !empty($validated['spouse_assessment_date'])
                        ? date('Y-m-d', strtotime(str_replace('/', '-', $validated['spouse_assessment_date'])))
                        : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Save character and history details
            $characterSections = [
                'criminal_charges' => 1,
                'military_service' => 2,
                'intelligence_work' => 3,
                'visa_refusals' => 4,
                'deportations' => 5,
                'citizenship_refusals' => 6,
                'health_declarations' => 7,
            ];

            foreach ($characterSections as $field => $typeOfCharacter) {
                if (!empty($validated[$field])) {
                    foreach ($validated[$field] as $index => $record) {
                        if (!empty($record['details'])) {
                            ClientCharacter::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'type_of_character' => $typeOfCharacter,
                                'character_detail' => $record['details'],
                                'character_date' => !empty($record['date'])
                                    ? date('Y-m-d', strtotime(str_replace('/', '-', $record['date'])))
                                    : null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // Update Partner Handling to include all family member types
            $familyTypes = [
                'partner' => ['Husband', 'Wife', 'Ex-Wife', 'Defacto', 'Engaged'],
                'children' => ['Son', 'Daughter', 'Step Son', 'Step Daughter'],
                'parent' => ['Father', 'Mother', 'Step Father', 'Step Mother', 'Mother-in-law', 'Father-in-law'],
                'siblings' => ['Brother', 'Sister', 'Step Brother', 'Step Sister'],
                'others' => ['Cousin', 'Friend', 'Uncle', 'Aunt', 'Grandchild', 'Granddaughter', 'Grandparent', 'Niece', 'Nephew', 'Grandfather', 'Son-in-law', 'Daughter-in-law', 'Brother-in-law', 'Sister-in-law'],
            ];

            // Function to get reciprocal relationship based on gender
            $getReciprocalRelationship = function($relationshipType, $currentGender, $relatedGender, $clientGender = '') {
                switch ($relationshipType) {
                    // Partner relationships
                    case 'Husband':
                        return 'Wife';
                    case 'Wife':
                        return 'Husband';
                    case 'Ex-Wife':
                        return 'Ex-Husband';
                    case 'Defacto':
                        return 'Defacto';
                    case 'Engaged':
                        return 'Engaged';
                    
                    // Parent-Child relationships
                    case 'Son':
                        return $relatedGender === 'Female' ? 'Mother' : 'Father';
                    case 'Daughter':
                        return $relatedGender === 'Female' ? 'Mother' : 'Father';
                    case 'Step Son':
                        return $relatedGender === 'Female' ? 'Step Mother' : 'Step Father';
                    case 'Step Daughter':
                        return $relatedGender === 'Female' ? 'Step Mother' : 'Step Father';
                    case 'Father':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    case 'Mother':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    case 'Step Father':
                        return $relatedGender === 'Female' ? 'Step Daughter' : 'Step Son';
                    case 'Step Mother':
                        return $relatedGender === 'Female' ? 'Step Daughter' : 'Step Son';
                    case 'Mother-in-law':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    case 'Father-in-law':
                        return $relatedGender === 'Female' ? 'Daughter' : 'Son';
                    
                    // Sibling relationships
                    case 'Brother':
                        return $relatedGender === 'Female' ? 'Sister' : 'Brother';
                    case 'Sister':
                        return $relatedGender === 'Female' ? 'Sister' : 'Brother';
                    case 'Step Brother':
                        return $relatedGender === 'Female' ? 'Step Sister' : 'Step Brother';
                    case 'Step Sister':
                        return $relatedGender === 'Female' ? 'Step Sister' : 'Step Brother';
                    
                    // Other relationships
                    case 'Cousin':
                        return 'Cousin';
                    case 'Friend':
                        return 'Friend';
                    case 'Uncle':
                        return $relatedGender === 'Female' ? 'Niece' : 'Nephew';
                    case 'Aunt':
                        return $relatedGender === 'Female' ? 'Niece' : 'Nephew';
                    case 'Grandchild':
                        return $relatedGender === 'Female' ? 'Grandmother' : 'Grandfather';
                    case 'Granddaughter':
                        return $relatedGender === 'Female' ? 'Grandmother' : 'Grandfather';
                    case 'Grandparent':
                        return $relatedGender === 'Female' ? 'Granddaughter' : 'Grandson';
                    case 'Grandfather':
                        return $relatedGender === 'Female' ? 'Granddaughter' : 'Grandson';
                    case 'Grandmother':
                        return $relatedGender === 'Female' ? 'Granddaughter' : 'Grandson';
                    case 'Niece':
                        return $relatedGender === 'Female' ? 'Aunt' : 'Uncle';
                    case 'Nephew':
                        return $relatedGender === 'Female' ? 'Aunt' : 'Uncle';
                    // In-law reciprocals (based on client's gender when adding an "other")
                    case 'Son-in-law':
                        return $clientGender === 'Female' ? 'Mother-in-law' : 'Father-in-law';
                    case 'Daughter-in-law':
                        return $clientGender === 'Female' ? 'Mother-in-law' : 'Father-in-law';
                    case 'Brother-in-law':
                        return $clientGender === 'Female' ? 'Sister-in-law' : 'Brother-in-law';
                    case 'Sister-in-law':
                        return $clientGender === 'Female' ? 'Sister-in-law' : 'Brother-in-law';
                    
                    default:
                        return $relationshipType; // Fallback to same relationship type
                }
            };

            // Clear existing relationships for the client
            foreach ($familyTypes as $type => $relationships) {
                if (!empty($requestData["{$type}_details"]) || !empty($requestData["{$type}_relationship_type"])) {
                    $detailsArray = $requestData["{$type}_details"] ?? [];
                    $relationshipTypeArray = $requestData["{$type}_relationship_type"] ?? [];
                    $partnerIdArray = $requestData["{$type}_id"] ?? [];
                    $emailArray = $requestData["{$type}_email"] ?? [];
                    $firstNameArray = $requestData["{$type}_first_name"] ?? [];
                    $lastNameArray = $requestData["{$type}_last_name"] ?? [];
                    $phoneArray = $requestData["{$type}_phone"] ?? [];
                    $companyArray = $requestData["{$type}_company_type"] ?? [];
                    $genderArray = $requestData["{$type}_gender"] ?? [];
                    //$dobArray = $requestData["{$type}_dob"] ?? [];

                    $dobArray = [];
                    if (!empty($requestData["{$type}_dob"]) && is_array($requestData["{$type}_dob"])) {
                        foreach ($requestData["{$type}_dob"] as $dobIndex => $dobValue) {
                            if (!empty($dobValue)) {
                                try {
                                    $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $dobValue);
                                    $dobArray[$dobIndex] = $dobDate->format('Y-m-d'); // Convert to Y-m-d for storage
                                } catch (\Exception $e) {
                                    return redirect()->back()->withErrors(['dob' => 'Invalid Date of Birth format: ' . $dobValue . '. Must be in dd/mm/yyyy format.'])->withInput();
                                }
                            }
                        }
                    }

                    foreach ($detailsArray as $key => $details) {
                        $relationshipType = $relationshipTypeArray[$key] ?? null;
                        $partnerId = $partnerIdArray[$key] ?? null;
                        $email = $emailArray[$key] ?? null;
                        $firstName = $firstNameArray[$key] ?? null;
                        $lastName = $lastNameArray[$key] ?? null;
                        $phone = $phoneArray[$key] ?? null;
                        $companyType = $companyArray[$key] ?? null;
                        $gender = $genderArray[$key] ?? null;
                        $dob = $dobArray[$key] ?? null;

                        // Skip if neither details nor relationship type is provided
                        if (empty($details) && empty($relationshipType)) {
                            continue;
                        }

                        // Ensure relationship type is provided
                        if (empty($relationshipType)) {
                            throw new \Exception("Relationship type is required for {$type} entry at index {$key}.");
                        }
                        //dd($partnerId);
                        // Determine if we need to save extra fields (when related_client_id is not set)
                        $relatedClientId = $partnerId && is_numeric($partnerId) ? $partnerId : null;
                        $saveExtraFields = !$relatedClientId;

                        // Prepare data for the primary relationship
                        $partnerData = [
                            'admin_id' => Auth::user()->id,
                            'client_id' => $client->id,
                            'related_client_id' => $relatedClientId ? $relatedClientId : null,
                            'details' => $saveExtraFields ? $details : ($relatedClientId ? $details : null),
                            'relationship_type' => $relationshipType,
                            'company_type' => $companyType,
                            'email' => $saveExtraFields ? $email : null,
                            'first_name' => $saveExtraFields ? $firstName : null,
                            'last_name' => $saveExtraFields ? $lastName : null,
                            'phone' => $saveExtraFields ? $phone : null,
                            'gender' => $gender, // Always save gender as it's now a main field
                            'dob' => $saveExtraFields ? $dob : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        // Save the primary relationship
                        $newPartner = ClientRelationship::create($partnerData);

                        // Create reciprocal relationship if related_client_id is set
                        if ($relatedClientId) {
                            $relatedClient = Admin::find($relatedClientId);
                            if ($relatedClient) {
                                // Get the reciprocal relationship type (for "others" in-laws, uses client's gender)
                                $reciprocalRelationshipType = $getReciprocalRelationship($relationshipType, $gender, $relatedClient->gender ?? 'Male', $type === 'others' ? ($client->gender ?? '') : '');
                                
                                ClientRelationship::create([
                                    'admin_id' => Auth::user()->id,
                                    'client_id' => $relatedClientId,
                                    'related_client_id' => $client->id,
                                    //'details' => $details,
                                    'details' => "{$client->first_name} {$client->last_name} ({$client->email}, {$client->phone}, {$client->client_id})",
                                    'relationship_type' => $reciprocalRelationshipType,
                                    'company_type' => $companyType,
                                    'email' => null,
                                    'first_name' => null,
                                    'last_name' => null,
                                    'phone' => null,
                                    'gender' =>  $client->gender ? $client->gender : null, // Save gender for reciprocal relationship too
                                    'dob' => null,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                            }
                        }
                    }
                }
            }

            // Commit the transaction
            DB::commit();

            // Redirect with success message
            if ($validated['type'] === 'lead') {
                return redirect()->route('leads.index')->with('success', 'Lead created successfully.');
            } else {
                return redirect()->route('clients.index')->with('success', 'Client created successfully.');
            }
        } catch (\Exception $e) {
            // Roll back the transaction on error
            DB::rollBack();

            // Log the error for debugging
            Log::error('Lead/Client creation failed: ' . $e->getMessage());

            // Redirect back with error message
            if ($validated['type'] === 'lead') {
                return redirect()->back()->withErrors(['error' => 'Failed to create lead. Please try again: ' . $e->getMessage()])->withInput();
            } else {
                return redirect()->back()->withErrors(['error' => 'Failed to create client. Please try again: ' . $e->getMessage()])->withInput();
            }
        }
    }

    // getNextCounter method moved to ClientHelpers trait

}
