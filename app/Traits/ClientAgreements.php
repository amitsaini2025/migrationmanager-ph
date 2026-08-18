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

trait ClientAgreements
{
    //Generate agreemnt
    public function generateagreement(Request $request)
    {
        try { //dd($request->all());
            $previousPhpWordOutputEscaping = Settings::isOutputEscapingEnabled();
            $id = $request->client_id;
            $client = Admin::findOrFail($request->client_id);
            $responsiblePerson = \App\Models\Staff::findOrFail($request->agent_id); //dd($responsiblePerson);
            if (!$responsiblePerson) {
                return response()->json([
                    'success' => false,
                    'error' => 'No responsible person found in the database.',
                    'message' => 'No responsible person found in the database.'
                ], 400);
            }

            // Ensure templates directory exists
            $templatesDir = storage_path('app/templates');
            if (!file_exists($templatesDir)) {
                mkdir($templatesDir, 0755, true);
                Log::info('Created templates directory: ' . $templatesDir);
            }
            
            // Determine template filename (company / skill / conflict / default paths + legacy fallbacks)
            $matterNickName = null;
            $templateResolution = app(VisaAgreementTemplateResolver::class)->resolve(
                $client,
                isset($request->client_matter_id) ? (string) $request->client_matter_id : null
            );
            $matterNickName = $templateResolution['matter_nick_name'];
            $templateCandidates = $templateResolution['candidates'];
            Log::info('Agreement template resolution', [
                'rule' => $templateResolution['rule'],
                'candidates' => $templateCandidates,
                'client_id' => $client->id,
                'matter_nick' => $matterNickName,
            ]);

            $templateFileName = config('visa_agreement_templates.default', 'Service_Agreement_general.docx');
            $templatePath = storage_path('app/templates/' . $templateFileName);
            foreach ($templateCandidates as $candidateBasename) {
                $candidatePath = storage_path('app/templates/' . $candidateBasename);
                if (file_exists($candidatePath)) {
                    $templateFileName = $candidateBasename;
                    $templatePath = $candidatePath;
                    break;
                }
            }

            if (!file_exists($templatePath)) {
                Log::error('Agreement template file not found at: ' . $templatePath);
                // Try fallback to default template if specific template doesn't exist
                $defaultTemplatePath = storage_path('app/templates/agreement_template.docx');
                if (file_exists($defaultTemplatePath)) {
                    $templatePath = $defaultTemplatePath;
                    $templateFileName = 'agreement_template.docx';
                    Log::info('Using default template as fallback. Matter type: ' . ($matterNickName ?? 'unknown'));
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Template file not found.',
                        'message' => 'The agreement template file (' . $templateFileName . ') is missing. Please ensure the template file is placed at: storage/app/templates/' . $templateFileName,
                        'template_path' => $templatePath,
                        'help' => 'Contact your system administrator to upload the agreement template file.'
                    ], 404);
                }
            } else {
                Log::info('Using template: ' . $templateFileName . ' for matter type: ' . ($matterNickName ?? 'default') . ' (rule: ' . ($templateResolution['rule'] ?? '') . ')');
            }

            // Option 2: Patch template so "Amount incl Surcharge" total cell uses TotalDoHAChargesInclSurcharge
            // (replaces last occurrence of ${TotalDoHASurcharges} in word/document.xml to fix the total display)
            $pathToLoad = $templatePath;
            $patchedTempPath = null; // used for cleanup after saveAs; set only when patch is applied
            $scheduleATempPath = null; // working copy so Schedule A XML injection never mutates the stored template
            try {
                $tempDir = storage_path('app/temp');
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
                $patchedTempPath = $tempDir . '/agreement_patch_' . getmypid() . '_' . time() . '.docx';
                if (@copy($templatePath, $patchedTempPath)) {
                    $zip = new \ZipArchive();
                    if ($zip->open($patchedTempPath) === true) {
                        $xml = $zip->getFromName('word/document.xml');
                        if ($xml !== false) {
                            $xmlPatchesApplied = false;
                            $isCompanyAgreementTemplate = CompanyAgreementDocxPatcher::isCompanyAgreementTemplate($templateFileName);

                            if (! $isCompanyAgreementTemplate) {
                            $oldPlaceholder = '${TotalDoHASurcharges}';
                            $newPlaceholder = '${TotalDoHAChargesInclSurcharge}';
                            $countFullPlaceholder = substr_count($xml, $oldPlaceholder);
                            $countBareName = substr_count($xml, 'TotalDoHASurcharges');
                            // Try full placeholder first (PhpWord format)
                            $lastPos = strrpos($xml, $oldPlaceholder);
                            if ($lastPos !== false) {
                                $xml = substr_replace($xml, $newPlaceholder, $lastPos, strlen($oldPlaceholder));
                                $xmlPatchesApplied = true;
                                Log::info('[AgreementMacro:TotalDoHASurcharges] DOCX patch REPLACED last exact placeholder with TotalDoHAChargesInclSurcharge', [
                                    'client_id' => $request->client_id,
                                    'client_matter_id' => $request->client_matter_id ?? null,
                                    'occurrences_${TotalDoHASurcharges}' => $countFullPlaceholder,
                                    'occurrences_substring_TotalDoHASurcharges' => $countBareName,
                                    'last_match_byte_offset' => $lastPos,
                                    'patch_mode' => 'full_placeholder',
                                    'note' => 'Clause 4 cell will merge TotalDoHAChargesInclSurcharge (charges + surcharges), same as TotalDoHASurcharges.',
                                ]);
                            } else {
                                // Word may split placeholder across XML runs; try name only (last occurrence)
                                $oldName = 'TotalDoHASurcharges';
                                $newName = 'TotalDoHAChargesInclSurcharge';
                                $lastPos = strrpos($xml, $oldName);
                                if ($lastPos !== false) {
                                    $xml = substr_replace($xml, $newName, $lastPos, strlen($oldName));
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:TotalDoHASurcharges] DOCX patch REPLACED last bare name with TotalDoHAChargesInclSurcharge', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'occurrences_${TotalDoHASurcharges}' => $countFullPlaceholder,
                                        'occurrences_substring_TotalDoHASurcharges' => $countBareName,
                                        'last_match_byte_offset' => $lastPos,
                                        'patch_mode' => 'bare_name_only',
                                        'note' => 'Clause 4 cell will merge TotalDoHAChargesInclSurcharge (charges + surcharges), same as TotalDoHASurcharges.',
                                    ]);
                                }
                            }
                            if (! $xmlPatchesApplied) {
                                Log::info('[AgreementMacro:TotalDoHASurcharges] DOCX patch skipped (no TotalDoHASurcharges found in document.xml)', [
                                    'client_id' => $request->client_id,
                                    'client_matter_id' => $request->client_matter_id ?? null,
                                    'occurrences_${TotalDoHASurcharges}' => $countFullPlaceholder,
                                    'occurrences_substring_TotalDoHASurcharges' => $countBareName,
                                ]);
                            }
                            }

                            if ($isCompanyAgreementTemplate) {
                                $companyPatch = app(CompanyAgreementDocxPatcher::class)->patchDocumentXml($xml, $templateFileName);
                                $xml = $companyPatch['xml'];
                                if ($companyPatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:Company] DOCX patch applied to company agreement template', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                        'fixes' => $companyPatch['fixes'],
                                    ]);
                                }
                            }

                            if (VisaAgreementAmountTablePatcher::supportsTemplate($templateFileName)) {
                                $amountPatch = app(VisaAgreementAmountTablePatcher::class)->patchDocumentXml($xml);
                                $xml = $amountPatch['xml'];
                                if ($amountPatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:AmountAlignment] DOCX patch aligned fee and charge amount cells', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if (PsaAgreementSection4SummaryTablePatcher::supportsTemplate($templateFileName)) {
                                $psaSection4Patch = app(PsaAgreementSection4SummaryTablePatcher::class)->patchDocumentXml($xml);
                                $xml = $psaSection4Patch['xml'];
                                if ($psaSection4Patch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:Section4] DOCX patch fixed PSA authority charges amount cell', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if (VisaAgreementServiceTypeRowPatcher::supportsTemplate($templateFileName)) {
                                $serviceTypePatch = app(VisaAgreementServiceTypeRowPatcher::class)->patchDocumentXml($xml);
                                $xml = $serviceTypePatch['xml'];
                                if ($serviceTypePatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:ServiceType] DOCX patch right-aligned Subclass on service type row', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if (PsaAgreementServiceTypeRowPatcher::supportsTemplate($templateFileName)) {
                                $psaServiceTypePatch = app(PsaAgreementServiceTypeRowPatcher::class)->patchDocumentXml($xml);
                                $xml = $psaServiceTypePatch['xml'];
                                if ($psaServiceTypePatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:ServiceType] DOCX patch right-aligned Stream on PSA service type row', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                        'template' => $templateFileName,
                                    ]);
                                }
                            }

                            if ($templateFileName === 'Service_Agreement_Job_Ready.docx') {
                                $jobReadyPatch = app(JobReadyAgreementFeeTablePatcher::class)->patchDocumentXml($xml);
                                $xml = $jobReadyPatch['xml'];
                                if ($jobReadyPatch['patched']) {
                                    $xmlPatchesApplied = true;
                                    Log::info('[AgreementMacro:JobReady] DOCX patch aligned professional fee, Section 4 summary, and authority stage charge amount cells', [
                                        'client_id' => $request->client_id,
                                        'client_matter_id' => $request->client_matter_id ?? null,
                                    ]);
                                }
                            }

                            if ($xmlPatchesApplied) {
                                $zip->deleteName('word/document.xml');
                                $zip->addFromString('word/document.xml', $xml);
                                $zip->close();
                                $pathToLoad = $patchedTempPath;
                                Log::info('Patched agreement template working copy before PhpWord merge');
                            } else {
                                $zip->close();
                            }
                        } else {
                            $zip->close();
                        }
                    }
                    if ($pathToLoad === $templatePath && file_exists($patchedTempPath)) {
                        @unlink($patchedTempPath);
                        $patchedTempPath = null;
                    }
                }
            } catch (\Throwable $patchEx) {
                Log::warning('Template patch skipped: ' . $patchEx->getMessage());
                $pathToLoad = $templatePath;
                if ($patchedTempPath && file_exists($patchedTempPath)) {
                    @unlink($patchedTempPath);
                    $patchedTempPath = null;
                }
            }

            if ($pathToLoad === $templatePath && is_file($pathToLoad)) {
                $tempDir = storage_path('app/temp');
                if (! is_dir($tempDir)) {
                    @mkdir($tempDir, 0755, true);
                }
                $scheduleATempPath = $tempDir . '/agreement_schedule_a_' . getmypid() . '_' . time() . '.docx';
                if (@copy($pathToLoad, $scheduleATempPath)) {
                    $pathToLoad = $scheduleATempPath;
                } else {
                    $scheduleATempPath = null;
                    Log::warning('Could not copy agreement template for Schedule A injection; skipping placeholder injection to protect original file.');
                }
            }

            if ($pathToLoad !== $templatePath) {
                $this->injectScheduleAFamilyPlaceholdersIfNeeded($pathToLoad);
            }

            // PhpWord 1.3 disables XML escaping by default; enable so &, <, etc. in merge values stay valid DOCX XML.
            Settings::setOutputEscapingEnabled(true);
            $templateProcessor = new TemplateProcessor($pathToLoad);

            try {
                $tplVars = $templateProcessor->getVariables();
                $tplHasTdhs = in_array('TotalDoHASurcharges', $tplVars, true);
                $tplHasTdhcis = in_array('TotalDoHAChargesInclSurcharge', $tplVars, true);
                Log::info('[AgreementMacro:TotalDoHASurcharges] PhpWord template variables after load', [
                    'client_id' => $request->client_id,
                    'client_matter_id' => $request->client_matter_id ?? null,
                    'template_loaded_from_patched_copy' => ($pathToLoad !== $templatePath),
                    'has_placeholder_TotalDoHASurcharges' => $tplHasTdhs,
                    'has_placeholder_TotalDoHAChargesInclSurcharge' => $tplHasTdhcis,
                    'total_distinct_placeholders' => count($tplVars),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[AgreementMacro:TotalDoHASurcharges] Could not read template variables: ' . $e->getMessage());
            }

            // Log the values we're trying to set
            Log::info('Generating document for client: ' . $client->client_id);
            Log::info('Template path: ' . $templatePath);

            $dobFormated = 'NA';
            if($client->dob != ''){
                $dobArr = explode('-',$client->dob);
                if(!empty($dobArr)){
                    $dobFormated = $dobArr[2].'/'.$dobArr[1].'/'.$dobArr[0];
                } else{
                    $dobFormated = 'NA';
                }
            }

            // Try to find client address
            $addressRow = null;
            $address_record_cnt = DB::table('client_addresses')->where('client_id', $id)->count();
            if ($address_record_cnt > 0) {
                $addressRow = DB::table('client_addresses')->where('client_id', $id)->where('is_current', 1)->first();
                if ($addressRow === null) {
                    $addressRow = DB::table('client_addresses')->where('client_id', $id)->orderByRaw(ClientAddress::ORDER_BY_DISPLAY_SQL)->orderByDesc('id')->first();
                }
            }

            $addressMacros = app(VisaAgreementApplicantAddressResolver::class)->resolveForTemplate($addressRow, $templateFileName);
            $client_address = $addressMacros['street'];
            $client_zip = $addressMacros['postcode'];

            //Get client matter info
            $visa_subclass = '';
            $visa_stream = '';
            $professional_fee = 0;
            $gst_fee = 0;
            $visa_application_charge = 0;

            $Block_1_Description = '';
            $Block_1_Ex_Tax = 0;
            $Block_2_Description = '';
            $Block_2_Ex_Tax = 0;
            $Block_3_Description = '';
            $Block_3_Ex_Tax = 0;

            $Blocktotalfeesincltax = 0;

            $DoHAMainApplicantChargePersonCount = 0;
            $DoHAMainApplicantCharge = 0;
            $DoHAMainApplicantSurcharge = 0;

            $DoHAAdditionalApplicantCharge18PlusPersonCount = 0;
            $DoHAAdditionalApplicantCharge18Plus = 0;
            $DoHAAdditional18PlusSurcharge = 0;

            $DoHAAdditionalApplicantChargeUnder18PersonCount = 0;
            $DoHAAdditionalApplicantChargeUnder18 = 0;
            $DoHAAdditionalUnder18Surcharge = 0;

            $DoHASecondInstalmentMainPersonCount = 0;
            $DoHASecondInstalmentMain = 0;
            $DoHASecondInstalmentMainSurcharge = 0;

            $DoHASubsequentApplicantCharge18PlusPersonCount = 0;
            $DoHASubsequentApplicantCharge18Plus = 0;
            $DoHASubsequentApplicantCharge18PlusSurcharge = 0;

            $DoHASubsequentApplicantChargeUnder18PersonCount = 0;
            $DoHASubsequentTempAppCharge = 0;
            $DoHASubsequentTempAppSurcharge = 0;

            $DoHANonInternetChargePersonCount = 0;
            $DoHANonInternetCharge = 0;
            $DoHANonInternetSurcharge = 0;

            $TotalDoHACharges = 0;
            $TotalDoHASurcharges = 0;
            $TotalDoHAChargesInclSurcharge = '0.00';
            $TotalEstimatedOtherCosts = 0;
            $GrandTotalFeesAndCosts = 0;
            $GrandTotalFeesAndCostsFormated = '0.00';
            $BlocktotalfeesincltaxFormated = '0.00';

            if( isset($request->client_matter_id) && $request->client_matter_id != '' )
            {  //dd($request->client_matter_id);
                //First check cost is assigned for this matter wrt client or not
                $cost_assignment_cnt = \App\Models\CostAssignmentForm::where('client_id',$request->client_id)->where('client_matter_id',$request->client_matter_id)->count();
	            if($cost_assignment_cnt >0)
                { //dd('iff');
                    // Get cost assignment form fee info
                    $matter_info = DB::table('cost_assignment_forms')->where('client_id', $request->client_id)->where('client_matter_id', $request->client_matter_id)->first();

                    $client_matter_info = DB::table('client_matters')->select('sel_matter_id')->where('id', $request->client_matter_id)->first();
                    // Get matter info
                    if( $client_matter_info ){ //dd($client_matter_info);
                        $matter_info_arr = DB::table('matters')->select('title','nick_name','Block_1_Description','Block_2_Description','Block_3_Description')->where('id', $client_matter_info->sel_matter_id )->first();
                    }
                    if( $matter_info_arr ) {
                        $matter_info->title = $matter_info_arr->title ?? '';
                        $matter_info->nick_name = $matter_info_arr->nick_name ?? '';
                        $matter_info->Block_1_Description = $matter_info_arr->Block_1_Description ?? '';
                        $matter_info->Block_2_Description = $matter_info_arr->Block_2_Description ?? '';
                        $matter_info->Block_3_Description = $matter_info_arr->Block_3_Description ?? '';
                    }

                }
                else
                { //dd('elsee');
                    $client_matter_info = DB::table('client_matters')->select('sel_matter_id')->where('id', $request->client_matter_id)->first();
                    // Get matter info
                    if( $client_matter_info ){ //dd($client_matter_info);
                        $matter_info = DB::table('matters')->where('id', $client_matter_info->sel_matter_id )->first();
                    }
                }

                if ($matter_info)
                { //dd($matter_info);

                    $visa_subclass = $matter_info->title ?? '';
                    $visa_stream = $matter_info->nick_name ?? '';

                    //$professional_fee = $matter_info->our_fee;
                    //$gst_fee = 0;
                    //$visa_application_charge = $matter_info->main_applicant_fee;

                    $Block_1_Description = $matter_info->Block_1_Description ?? '';
                    $Block_1_Ex_Tax = $matter_info->Block_1_Ex_Tax ?? 0;

                    $Block_2_Description = $matter_info->Block_2_Description ?? '';
                    $Block_2_Ex_Tax = $matter_info->Block_2_Ex_Tax ?? 0;

                    $Block_3_Description = $matter_info->Block_3_Description ?? '';
                    $Block_3_Ex_Tax = $matter_info->Block_3_Ex_Tax ?? 0;

                    $Blocktotalfeesincltax = floatval($Block_1_Ex_Tax) + floatval($Block_2_Ex_Tax) + floatval($Block_3_Ex_Tax);
                    $BlocktotalfeesincltaxFormated = number_format($Blocktotalfeesincltax, 2, '.', '');
                    //dd($BlocktotalfeesincltaxFormated);

                    $surchargeApply = property_exists($matter_info, 'surcharge') ? $matter_info->surcharge : null;

                    $DoHAMainApplicantChargePersonCount = ($matter_info->Dept_Base_Application_Charge_no_of_person ?? 0) ."Person" ;
                    $DoHAMainApplicantCharge = $matter_info->Dept_Base_Application_Charge_after_person ?? 0;
                    $DoHAMainApplicantSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Base_Application_Charge_after_person ?? 0),
                        $matter_info->Dept_Base_Application_Charge_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHAAdditionalApplicantCharge18PlusPersonCount = ($matter_info->Dept_Additional_Applicant_Charge_18_Plus_no_of_person ?? 0) ."Person" ;
                    $DoHAAdditionalApplicantCharge18Plus = $matter_info->Dept_Additional_Applicant_Charge_18_Plus_after_person ?? 0;
                    $DoHAAdditional18PlusSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Additional_Applicant_Charge_18_Plus_after_person ?? 0),
                        $matter_info->Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHAAdditionalApplicantChargeUnder18PersonCount = ($matter_info->Dept_Additional_Applicant_Charge_Under_18_no_of_person ?? 0) ."Person" ;
                    $DoHAAdditionalApplicantChargeUnder18 = $matter_info->Dept_Additional_Applicant_Charge_Under_18_after_person ?? 0;
                    $DoHAAdditionalUnder18Surcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Additional_Applicant_Charge_Under_18_after_person ?? 0),
                        $matter_info->Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHASecondInstalmentMainPersonCount = ($matter_info->Dept_Subsequent_Temp_Application_Charge_no_of_person ?? 0) ."Person" ;
                    $DoHASecondInstalmentMain = $matter_info->Dept_Subsequent_Temp_Application_Charge_after_person ?? 0;
                    $DoHASecondInstalmentMainSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Subsequent_Temp_Application_Charge_after_person ?? 0),
                        $matter_info->Dept_Subsequent_Temp_Application_Charge_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHASubsequentApplicantCharge18PlusPersonCount = ($matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person ?? 0) ."Person" ;
                    $DoHASubsequentApplicantCharge18Plus = $matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person ?? 0;
                    $DoHASubsequentApplicantCharge18PlusSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person ?? 0),
                        $matter_info->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHASubsequentApplicantChargeUnder18PersonCount = ($matter_info->Dept_Second_VAC_Instalment_Under_18_no_of_person ?? 0) ."Person" ;
                    $DoHASubsequentTempAppCharge = $matter_info->Dept_Second_VAC_Instalment_Under_18_after_person ?? 0;
                    $DoHASubsequentTempAppSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Second_VAC_Instalment_Under_18_after_person ?? 0),
                        $matter_info->Dept_Second_VAC_Instalment_Under_18_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $DoHANonInternetChargePersonCount = ($matter_info->Dept_Non_Internet_Application_Charge_no_of_person ?? 0) ."Person" ;
                    $DoHANonInternetCharge = $matter_info->Dept_Non_Internet_Application_Charge_after_person ?? 0;
                    $DoHANonInternetSurcharge = $this->resolveDoHaAmountInclSurcharge(
                        floatval($matter_info->Dept_Non_Internet_Application_Charge_after_person ?? 0),
                        $matter_info->Dept_Non_Internet_Application_Charge_after_person_surcharge ?? null,
                        $surchargeApply
                    );

                    $TotalDoHACharges = $matter_info->TotalDoHACharges ?? 0;
                    $TotalDoHASurcharges = $matter_info->TotalDoHASurcharges ?? 0;
                    // Section 4 "Total DOHA charges Inc Surcharges" = Total DoHA Charges + Total DoHA Surcharges only
                    $TotalDoHAChargesInclSurcharge = CompanyVisaAgreementMacroBuilder::calculateDohaChargesInclSurcharges(
                        floatval($TotalDoHACharges),
                        floatval($TotalDoHASurcharges)
                    );

                    $TotalEstimatedOtherCosts = $matter_info->additional_fee_1 ?? 0;
                    // Total Fees, Charges & Costs = professional fees + DoHA charges (incl surcharge) + estimated costs
                    $GrandTotalFeesAndCosts = floatval($Blocktotalfeesincltax) + floatval($TotalDoHAChargesInclSurcharge) + floatval($TotalEstimatedOtherCosts);
                    $GrandTotalFeesAndCostsFormated = number_format($GrandTotalFeesAndCosts, 2, '.', '');
                }
            }

            // ${TotalDoHASurcharges} / ${TotalDoHAChargesInclSurcharge}: Total DoHA Charges + Total DoHA Surcharges only
            // (Additional Fee 1 is merged via ${TotalEstimatedOthCosts}.)
            $TotalDoHASurchargesMacroSum = '0.00';
            $costRowForMacro = null;
            if (isset($request->client_matter_id) && $request->client_matter_id !== '') {
                $costRowForMacro = DB::table('cost_assignment_forms')
                    ->where('client_id', $request->client_id)
                    ->where('client_matter_id', $request->client_matter_id)
                    ->first();
                if ($costRowForMacro !== null) {
                    $dbBase = floatval($costRowForMacro->TotalDoHACharges ?? 0);
                    $dbSurchargeOnly = floatval($costRowForMacro->TotalDoHASurcharges ?? 0);
                    $TotalDoHASurchargesMacroSum = CompanyVisaAgreementMacroBuilder::calculateDohaChargesInclSurcharges(
                        $dbBase,
                        $dbSurchargeOnly
                    );
                    $TotalDoHAChargesInclSurcharge = $TotalDoHASurchargesMacroSum;
                    $GrandTotalFeesAndCostsFormated = CompanyVisaAgreementMacroBuilder::calculateGrandTotalFeesAndCosts(
                        floatval($Blocktotalfeesincltax),
                        $TotalDoHASurchargesMacroSum,
                        floatval($TotalEstimatedOtherCosts)
                    );
                    Log::info('[AgreementMacro:TotalDoHASurcharges] DoHA incl surcharge (charges + surcharges only)', [
                        'client_id' => $request->client_id,
                        'client_matter_id' => $request->client_matter_id,
                        'template' => $templateFileName,
                        'TotalDoHACharges' => $dbBase,
                        'TotalDoHASurcharges_surcharge_only' => $dbSurchargeOnly,
                        'macro_TotalDoHASurcharges_merge_value' => $TotalDoHASurchargesMacroSum,
                        'GrandTotalFeesAndCosts' => $GrandTotalFeesAndCostsFormated,
                    ]);
                } else {
                    Log::warning('[AgreementMacro:TotalDoHASurcharges] No cost_assignment_forms row for client/matter — macro stays 0.00', [
                        'client_id' => $request->client_id,
                        'client_matter_id' => $request->client_matter_id,
                    ]);
                }
            } else {
                Log::warning('[AgreementMacro:TotalDoHASurcharges] Missing client_matter_id — macro stays 0.00', [
                    'client_id' => $request->client_id,
                ]);
            }

            $scheduleAFamily = $this->buildScheduleAFamilyMacroStrings((int) $id);

            // Replace placeholders
            $replacements = [
                'ClientID' => $client->client_id,
                'ApplicantGivenNames' => $client->first_name,
                'ApplicantSurname' => $client->last_name,
                'ApplicantDOB' => $dobFormated,
                'ApplicantResidentialAddressStreet1and2' => $client_address,
                'ApplicantResidentialAddressPostcode' => $client_zip,
                //'ApplicantResidentialAddressSuburbAndTown' => '',
                //'ApplicantResidentialAddressState' => '',
                //'ApplicantResidentialAddressCountry' => '',
                'Contact_ContactEmail' => $client->email,
                'Contact_ContactMobile' => $client->phone ?? '',
                'ApplicantHomePhone_Number' => $client->phone ?? '',

                'VisaApplyingFor' => $visa_subclass,
                'VisaApplyingForStream' => $visa_stream,

                'Block1IncTax' => number_format($professional_fee, 2),
                'TotalAgentFeeGST' => number_format($gst_fee ?? 0, 2),
                'TotalAgentFeeIncTax' => number_format($professional_fee + ($gst_fee ?? 0), 2),
                'BaseApplicationCharge' => number_format($visa_application_charge, 2),
                'DOHABaseApplicationChargeIncCCSurcharge' => number_format($visa_application_charge, 2),

                'AgentName' => $responsiblePerson->first_name,
                'AgentSurName' => $responsiblePerson->last_name,
                'AgentTitle' => $responsiblePerson->company_name,
                'MARN' => $responsiblePerson->marn_number,

                'Visa_apply' => $visa_subclass,
                'visa_apply' => $visa_subclass,

                'Block1description'=>$Block_1_Description,
                'Block1feesincltax'=>$Block_1_Ex_Tax,
                'Block2description'=>$Block_2_Description,
                'Block2feesincltax'=>$Block_2_Ex_Tax,
                'Block3description'=>$Block_3_Description,
                'Block3feesincltax'=>$Block_3_Ex_Tax,
                'Blocktotalfeesincltax'=>$BlocktotalfeesincltaxFormated,

                'DoHAMainApplicantChargePersonCount'=>$DoHAMainApplicantChargePersonCount,
                'DoHAMainApplicantCharge'=>$DoHAMainApplicantCharge,
                'DoHAMainApplicantSurcharge'=>$DoHAMainApplicantSurcharge,

                'DoHAAdditionalApplicantCharge18PlusPersonCount'=>$DoHAAdditionalApplicantCharge18PlusPersonCount,
                'DoHAAdditionalApplicantCharge18Plus'=>$DoHAAdditionalApplicantCharge18Plus,
                'DoHAAdditional18PlusSurcharge'=>$DoHAAdditional18PlusSurcharge,

                'DoHAAdditionalApplicantChargeUnder18PersonCount'=>$DoHAAdditionalApplicantChargeUnder18PersonCount,
                'DoHAAdditionalApplicantChargeUnder18'=>$DoHAAdditionalApplicantChargeUnder18,
                'DoHAAdditionalUnder18Surcharge'=>$DoHAAdditionalUnder18Surcharge,

                'DoHASecondInstalmentMainPersonCount'=>$DoHASecondInstalmentMainPersonCount,
                'DoHASecondInstalmentMain'=>$DoHASecondInstalmentMain,
                'DoHASecondInstalmentMainSurcharge'=>$DoHASecondInstalmentMainSurcharge,

                'DoHASubsequentApplicantCharge18PlusPersonCount'=>$DoHASubsequentApplicantCharge18PlusPersonCount,
                'DoHASubsequentApplicantCharge18Plus'=>$DoHASubsequentApplicantCharge18Plus,
                'DoHASubsequentApplicantCharge18PlusSurcharge'=>$DoHASubsequentApplicantCharge18PlusSurcharge,

                'DoHASubsequentApplicantChargeUnder18PersonCount'=>$DoHASubsequentApplicantChargeUnder18PersonCount,
                'DoHASubsequentTempAppCharge'=>$DoHASubsequentTempAppCharge,
                'DoHASubsequentTempAppSurcharge'=>$DoHASubsequentTempAppSurcharge,

                'DoHANonInternetChargePersonCount'=>$DoHANonInternetChargePersonCount,
                'DoHANonInternetCharge'=>$DoHANonInternetCharge,
                'DoHANonInternetSurcharge'=>$DoHANonInternetSurcharge,

                'TotalDoHACharges'=>$TotalDoHACharges,
                'TotalDoHASurcharges'=>$TotalDoHASurchargesMacroSum,
                'TotalDoHAChargesInclSurcharge'=>$TotalDoHAChargesInclSurcharge,

                'TotalEstimatedOthCosts'=>$TotalEstimatedOtherCosts,
                'GrandTotalFeesAndCosts'=>$GrandTotalFeesAndCostsFormated,

                'ScheduleA_PartnerList' => $scheduleAFamily['partners'],
                'ScheduleA_ChildList' => $scheduleAFamily['children'],
            ];

            if ($client->isCompany()) {
                $replacements = array_merge(
                    $replacements,
                    app(CompanyVisaAgreementMacroBuilder::class)->build($client, $costRowForMacro)
                );
            }

            // Log each replacement
            foreach ($replacements as $key => $value) {
                // FIX: Handle NULL values properly - convert to empty string
                $safeValue = $value ?? '';
                Log::info("Setting {$key} to: {$safeValue}");
                $templateProcessor->setValue($key, $safeValue);
            }

            // FIX: Set ALL remaining template variables to empty string to prevent corruption
            // This prevents unreplaced ${VariableName} placeholders from remaining in the document
            // which causes Microsoft Word to show "cannot open file" error
            try {
                $allTemplateVars = $templateProcessor->getVariables();
                $fixedVarsCount = 0;
                foreach ($allTemplateVars as $templateVar) {
                    // Only set if not already in replacements array
                    if (! isset($replacements[$templateVar]) && $this->isSafePhpWordTemplateVariableName($templateVar)) {
                        $templateProcessor->setValue($templateVar, '');
                        $fixedVarsCount++;
                    } elseif (! isset($replacements[$templateVar])) {
                        Log::warning('Skipped clearing malformed agreement template variable', [
                            'variable_preview' => substr($templateVar, 0, 80),
                        ]);
                    }
                }
                Log::info("Fixed {$fixedVarsCount} unreplaced template variables to prevent document corruption");
            } catch (\Exception $e) {
                // Log error but don't fail - continue with document generation
                Log::warning('Could not fix unreplaced variables: ' . $e->getMessage());
            }

            // Create the output directory if it doesn't exist - use public directory for web access
            $outputDir = storage_path('app/public/agreements');
            //  $outputDir = public_path('agreements');
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputPath = $outputDir . '/agreement_' . $client->client_id . '.docx'; //dd($outputPath);
            $templateProcessor->saveAs($outputPath);
            Settings::setOutputEscapingEnabled($previousPhpWordOutputEscaping);

            // Remove patched temp template if Option 2 was used
            if (isset($patchedTempPath) && $patchedTempPath !== null && file_exists($patchedTempPath)) {
                @unlink($patchedTempPath);
            }
            if (isset($scheduleATempPath) && $scheduleATempPath !== null && file_exists($scheduleATempPath)) {
                @unlink($scheduleATempPath);
            }

            Log::info('Document generated successfully at: ' . $outputPath);
            
            // FIX: Validate the generated document to ensure it's not corrupted
            // This catches document corruption issues before user tries to open it
            try {
                $validationDoc = \PhpOffice\PhpWord\IOFactory::load($outputPath);
                Log::info('Document validation passed - file is valid');
                unset($validationDoc); // Free memory
            } catch (\Exception $validationException) {
                // Document is corrupted - delete it and return error
                Log::error('Generated document failed validation: ' . $validationException->getMessage());
                if (file_exists($outputPath)) {
                    unlink($outputPath);
                }
                return response()->json([
                    'success' => false,
                    'error' => 'Document validation failed.',
                    'message' => 'The generated document appears to be corrupted. Please ensure all client matter and cost assignment data is complete before generating the agreement.',
                    'technical_details' => $validationException->getMessage()
                ], 500);
            }

            // Upload to S3 and get download URL
            $fileName = 'agreement_' . $client->client_id . '_' . time() . '.docx';
            $s3Path = $client->client_id . '/cost_assignment_form/' . $fileName;
            $downloadUrl = null;
            $s3UploadSuccess = false;
            
            // Try to upload to S3
            try {
                $uploadResult = Storage::disk('s3')->put($s3Path, file_get_contents($outputPath));
                
                if ($uploadResult) {
                    // Get the S3 URL
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    $downloadUrl = $disk->url($s3Path);
                    
                    // Verify the URL is not empty
                    if (!empty($downloadUrl)) {
                        $s3UploadSuccess = true;
                        Log::info('Document uploaded to S3 successfully. URL: ' . $downloadUrl);
                    } else {
                        Log::warning('S3 upload succeeded but URL is empty');
                    }
                } else {
                    Log::warning('S3 upload returned false');
                }
            } catch (\Exception $s3Exception) {
                Log::error('S3 upload failed: ' . $s3Exception->getMessage());
                Log::error($s3Exception->getTraceAsString());
            }
            
            // If S3 upload failed or URL is empty, use local file as fallback
            if (!$s3UploadSuccess || empty($downloadUrl)) {
                // File is already in public storage (storage/app/public/agreements)
                // Generate public URL using the storage path
                // The file is saved as: agreement_{client_id}.docx
                $localFileName = basename($outputPath);
                $relativePath = 'agreements/' . $localFileName;
                $downloadUrl = asset('storage/' . $relativePath);
                
                // Verify the file exists before returning the URL
                if (!file_exists($outputPath)) {
                    throw new \Exception('Document was generated but file not found at: ' . $outputPath);
                }
                
                Log::info('Using local file as fallback. URL: ' . $downloadUrl);
                // Keep the local file for download (don't delete it)
            } else {
                // Clean up local file only if S3 upload was successful
                if (file_exists($outputPath)) {
                    unlink($outputPath);
                }
            }
            
            // Verify download URL is set
            if (empty($downloadUrl)) {
                Log::error('Download URL is empty after all attempts. Output path: ' . $outputPath);
                throw new \Exception('Failed to generate download URL. Document was created but could not be made available for download.');
            }
            
            // Log the final response for debugging
            Log::info('Returning success response with download_url: ' . $downloadUrl);
            
            // Log activity
            $matter = \App\Models\ClientMatter::find($request->client_matter_id);
            $matterName = $matter ? $matter->title : 'N/A';
            
            $activity = new \App\Models\ActivitiesLog;
            $activity->client_id = $request->client_id;
            $activity->created_by = Auth::user()->id;
            $activity->subject = 'created visa agreement';
            $activity->description = '<p>Visa agreement has been created for matter: <strong>' . $matterName . '</strong></p>';
            $activity->task_status = 0;
            $activity->pin = 0;
            $activity->save();
            
            // Return the download URL as JSON
            $response = [
                'success' => true,
                'download_url' => $downloadUrl,
                'filename' => $fileName,
                'message' => 'Document generated successfully'
            ];
            
            // Double-check response structure before returning
            if (!isset($response['success']) || !isset($response['download_url'])) {
                Log::error('Response structure is invalid: ' . json_encode($response));
                throw new \Exception('Invalid response structure generated.');
            }
            
            return response()->json($response);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if (isset($previousPhpWordOutputEscaping)) {
                Settings::setOutputEscapingEnabled($previousPhpWordOutputEscaping);
            }
            Log::error('Model not found in generateagreement: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Client or agent not found.',
                'message' => 'Client or agent not found.'
            ], 404);
        } catch (\Exception $e) {
            if (isset($previousPhpWordOutputEscaping)) {
                Settings::setOutputEscapingEnabled($previousPhpWordOutputEscaping);
            }
            Log::error('Error generating document: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Error generating document: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ensure Schedule A partner/child merge fields exist in Service_Agreement_general-style templates.
     * Idempotent: skips when placeholders are already present or sample paragraphs were removed.
     */
    protected function injectScheduleAFamilyPlaceholdersIfNeeded(string $docxPath): void
    {
        if (! is_file($docxPath)) {
            return;
        }

        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }

        $original = $xml;

        if (! str_contains($xml, 'ScheduleA_PartnerList')) {
            $partnerRun = '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t>${ScheduleA_PartnerList}</w:t></w:r>';
            $partner = ' partner: </w:t></w:r></w:p>';
            if (substr_count($xml, $partner) === 1) {
                $xml = str_replace($partner, ' partner: </w:t></w:r>' . $partnerRun . '</w:p>', $xml);
            }
        }

        if (! str_contains($xml, 'ScheduleA_ChildList')) {
            $oldSampleChildren = '<w:p w14:paraId="76AE0EE9" w14:textId="19E35E7E" w:rsidR="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:proofErr w:type="spellStart"/><w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t>Gdsf</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t xml:space="preserve"> (1/1/19xx)</w:t></w:r></w:p>'
                . '<w:p w14:paraId="0E3F9316" w14:textId="76EFE0F1" w:rsidR="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr><w:t>Sf</w:t></w:r></w:p>'
                . '<w:p w14:paraId="3BC3631A" w14:textId="0BAE766C" w:rsidR="00A81247" w:rsidRPr="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr><w:t>sf</w:t></w:r>'
                . '<w:bookmarkStart w:id="3" w:name="_Hlk95643751"/></w:p>';

            $newChildParagraph = '<w:p w14:paraId="76AE0EE9" w14:textId="19E35E7E" w:rsidR="002E3F6E" w:rsidRDefault="002E3F6E" w:rsidP="002E3F6E">'
                . '<w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="1"/><w:numId w:val="39"/></w:numPr>'
                . '<w:spacing w:before="480"/><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr></w:pPr>'
                . '<w:r><w:rPr><w:rFonts w:cstheme="minorHAnsi"/><w:b/><w:bCs/><w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-US"/></w:rPr>'
                . '<w:t>${ScheduleA_ChildList}</w:t></w:r><w:bookmarkStart w:id="3" w:name="_Hlk95643751"/></w:p>';

            if (str_contains($xml, $oldSampleChildren)) {
                $xml = str_replace($oldSampleChildren, $newChildParagraph, $xml);
            }
        }

        if ($xml !== $original) {
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $xml);
            Log::info('Injected Schedule A family placeholders into agreement template', ['path' => $docxPath]);
        }

        $zip->close();
    }

    /**
     * Build multi-line merge values for Schedule A (partner rows; lettered child rows).
     *
     * @return array{partners: string, children: string}
     */
    protected function buildScheduleAFamilyMacroStrings(int $clientAdminId): array
    {
        $partnerTypes = ['Husband', 'Wife', 'Ex-Husband', 'Ex-Wife', 'Defacto', 'Engaged'];
        $childTypes = ['Son', 'Daughter', 'Step Son', 'Step Daughter'];

        $rows = ClientRelationship::query()
            ->where('client_id', $clientAdminId)
            ->with(['relatedClient:id,first_name,last_name,client_id,dob,email'])
            ->orderBy('id')
            ->get();

        $partnerLines = [];
        foreach ($rows as $r) {
            if (in_array($r->relationship_type, $partnerTypes, true)) {
                $partnerLines[] = $this->formatVisaAgreementFamilyLineForPartner($r);
            }
        }

        $childLines = [];
        $letterIndex = 0;
        foreach ($rows as $r) {
            if (in_array($r->relationship_type, $childTypes, true)) {
                if ($letterIndex < 26) {
                    $prefix = chr(ord('a') + $letterIndex) . '. ';
                } else {
                    $prefix = (string) ($letterIndex + 1) . '. ';
                }
                $childLines[] = $prefix . $this->formatVisaAgreementFamilyLineForChild($r);
                $letterIndex++;
            }
        }

        return [
            'partners' => $partnerLines === [] ? 'No' : implode("\n", $partnerLines),
            'children' => $childLines === [] ? 'No' : implode("\n", $childLines),
        ];
    }

    protected function formatVisaAgreementFamilyLineForPartner(ClientRelationship $relationship): string
    {
        $name = $this->agreementFamilyMemberDisplayName($relationship);
        $rel = trim((string) ($relationship->relationship_type ?? ''));
        $line = $rel !== '' ? $name . ' — ' . $rel : $name;

        $email = $this->agreementPartnerEmailForDisplay($relationship);
        if ($email !== '' && stripos($line, $email) === false) {
            $line .= ' — ' . $email;
        }

        return $line;
    }

    protected function formatVisaAgreementFamilyLineForChild(ClientRelationship $relationship): string
    {
        $name = $this->agreementFamilyMemberDisplayName($relationship);
        $dob = $this->agreementFormatDobSuffixForRelationship($relationship);
        $line = $dob !== '' ? $name . ' (' . $dob . ')' : $name;
        $rel = trim((string) ($relationship->relationship_type ?? ''));

        return $rel !== '' ? $line . ' — ' . $rel : $line;
    }

    protected function agreementFormatDobSuffixForRelationship(ClientRelationship $relationship): string
    {
        $raw = $relationship->dob;
        if (($raw === null || $raw === '') && $relationship->relationLoaded('relatedClient') && $relationship->relatedClient) {
            $raw = $relationship->relatedClient->dob ?? null;
        }
        if ($raw === null || $raw === '') {
            return '';
        }
        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Plain-text display name aligned with client detail Relationships / family forms.
     */
    protected function agreementFamilyMemberDisplayName(ClientRelationship $relationship): string
    {
        if (! empty($relationship->related_client_id)) {
            $relatedClientInfo = $relationship->relatedClient;
            if ($relatedClientInfo) {
                $relatedClientId = (string) ($relatedClientInfo->client_id ?? '');
                $clientFirstName = trim((string) ($relatedClientInfo->first_name ?? ''));
                $clientLastName = trim((string) ($relatedClientInfo->last_name ?? ''));

                if ($clientFirstName === '' && $clientLastName === '') {
                    return 'Client ID: ' . $relatedClientId;
                }
                if ($clientFirstName === '') {
                    return trim($clientLastName . ' — ' . $relatedClientId);
                }
                if ($clientLastName === '') {
                    return trim($clientFirstName . ' — ' . $relatedClientId);
                }

                return trim($clientFirstName . ' ' . $clientLastName . ' — ' . $relatedClientId);
            }

            return 'Client not found';
        }

        $firstName = trim((string) ($relationship->first_name ?? ''));
        $lastName = trim((string) ($relationship->last_name ?? ''));
        if ($firstName === '' && $lastName === '') {
            $detailsFallback = trim((string) ($relationship->details ?? ''));

            return $detailsFallback !== '' ? $detailsFallback : 'Name not provided';
        }
        if ($firstName === '') {
            return $lastName;
        }
        if ($lastName === '') {
            return $firstName;
        }

        return $firstName . ' ' . $lastName;
    }

    /**
     * Email for Schedule A partner lines: relationship row, linked profile, or extracted from details.
     */
    protected function agreementPartnerEmailForDisplay(ClientRelationship $relationship): string
    {
        $fromColumn = trim((string) ($relationship->email ?? ''));
        if ($fromColumn !== '') {
            return $fromColumn;
        }

        if ($relationship->relationLoaded('relatedClient') && $relationship->relatedClient) {
            $fromClient = trim((string) ($relationship->relatedClient->email ?? ''));
            if ($fromClient !== '') {
                return $fromClient;
            }
        }

        $details = (string) ($relationship->details ?? '');
        if ($details !== '' && preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $details, $m)) {
            return $m[0];
        }

        if ($details !== '' && preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/', $details, $m)) {
            return $m[0];
        }

        return '';
    }

    /**
     * PhpWord variable names must be simple tokens; malformed names (e.g. from broken ${...} in DOCX) must not be cleared.
     */
    protected function isSafePhpWordTemplateVariableName(string $name): bool
    {
        if ($name === '' || strlen($name) > 64) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    /**
     * Resolve per-line "amount incl surcharge" for agreement macros.
     *
     * Some legacy or partial saves left *_after_person_surcharge at 0 while *_after_person had
     * the base total. Recalculate only in that case using the same rule as cost assignment save:
     * surcharge only when the cost assignment `surcharge` flag is Yes (1.4% of line base).
     */
    protected function resolveDoHaAmountInclSurcharge(float $afterPerson, $storedInclSurcharge, $surchargeFlag): float
    {
        $base = $afterPerson;
        $stored = floatval($storedInclSurcharge ?? 0);

        if ($base <= 0) {
            return $stored;
        }

        if ($stored > 0) {
            return $stored;
        }

        if (is_string($surchargeFlag) && trim($surchargeFlag) === 'Yes') {
            return round($base * 0.014, 2) + $base;
        }

        return $base;
    }


    //Upload agreement in PDF
    public function uploadAgreement(Request $request, Admin $admin)
    {
        //1. Validate only PDF files (max 10MB)
        $request->validate([
            'agreement_doc' => 'required|mimes:pdf|max:10240', // 10MB max
        ]);

        $requestData = $request->all();
        $pdfFile = $request->file('agreement_doc');

        //2. Get file details
        $originalName = $pdfFile->getClientOriginalName();
        $size = $pdfFile->getSize();
        $timestampedName = time() . '_' . $originalName;

        //3. Build S3 path using client ID (admin is the client record)
        $clientUniqueId = $admin->client_id ?? "";
        $s3Path = $clientUniqueId . '/agreement/' . $timestampedName;

        //4. Upload directly to S3
        Storage::disk('s3')->put($s3Path, file_get_contents($pdfFile));

        //5. Save document details in DB
        $originalInfo = pathinfo($originalName);
        $doc = new \App\Models\Document;
        $doc->file_name = $originalInfo['filename']; // e.g., "passport" (without extension)
        $doc->filetype = 'pdf';
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $doc->myfile = $disk->url($s3Path);
        $doc->myfile_key = $timestampedName;
        $doc->user_id = Auth::user()->id;
        $doc->client_id = $admin->id;
        $doc->type = 'client';
        $doc->file_size = $size;
        $doc->doc_type = 'agreement';
        $doc->client_matter_id = $requestData['clientmatterid'];
        $saved = $doc->save();

        //6. Log activity if saved
        if ($saved) {
            $docName = htmlspecialchars($originalInfo['filename'] ?? pathinfo($originalName, PATHINFO_FILENAME));
            $desc = '<ul><li><strong>Document:</strong> ' . $docName . '.pdf</li><li><strong>Next:</strong> Place signature fields in the modal</li></ul>';
            \App\Models\ActivitiesLog::create([
                'client_id' => $admin->id,
                'created_by' => Auth::user()->id,
                'subject' => 'uploaded visa agreement PDF for signature',
                'description' => $desc,
                'activity_type' => 'signature',
                'task_status' => 0,
                'pin' => 0,
            ]);
        }

        //7. Return success response with document ID for signature placement
        return response()->json([
            'status' => true,
            'message' => 'PDF agreement uploaded successfully!',
            'document_id' => $doc->id,
            'edit_url' => route('documents.edit', $doc->id)
        ]);
    }

}
