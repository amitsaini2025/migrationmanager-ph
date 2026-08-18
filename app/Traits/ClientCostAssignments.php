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

trait ClientCostAssignments
{
    //Get Migration Agent Detail
    public function getMigrationAgentDetail(Request $request)
    {
        $requestData = 	$request->all();
        $response = [
            'status' => false,
            'message' => 'Record is not exist.Please try again',
            'matterInfo' => '',
            'agentInfo' => '',
        ];
        $client_matter_id = $requestData['client_matter_id'];
        $clientMatterInfo = DB::table('client_matters')->select('sel_migration_agent','sel_matter_id')->where('id',$client_matter_id)->first();
        //dd($clientMatterInfo);
        if($clientMatterInfo) {
            //get matter name
            $matterInfo = DB::table('matters')->select('title','nick_name')->where('id',$clientMatterInfo->sel_matter_id)->first();
            //dd($matterInfo);
            if($matterInfo){
                $response['matterInfo'] = $matterInfo;
            } else {
                $response['matterInfo'] = "";
            }

            $sel_migration_agent = $clientMatterInfo->sel_migration_agent;
            $agentInfo = DB::table('staff')->select(
                'id as agentId',
                'first_name',
                'last_name',
                'company_name',
                'is_migration_agent',
                'marn_number',
                'legal_practitioner_number',
                'business_address',
                'business_phone',
                'business_mobile',
                'business_email',
                'tax_number'
            )->where('id', $sel_migration_agent)->first();
            //dd($agentInfo);
            if($agentInfo){
                $response['agentInfo'] 	= $agentInfo;
                $response['status'] 	= 	true;
                $response['message']	=	'Record is exist';
            } else {
                $response['agentInfo'] 	= "";
                $response['status'] 	= 	false;
                $response['message']	=	'Record is not exist.Please try again';
            }
        }
        echo json_encode($response);
    }

    //Get Visa agreemnt Migration Agent Detail
    public function getVisaAggreementMigrationAgentDetail(Request $request)
    {
        $requestData = 	$request->all();
        $response = [
            'status' => false,
            'message' => 'Record is not exist.Please try again',
            'matterInfo' => '',
            'agentInfo' => '',
        ];
        $client_matter_id = $requestData['client_matter_id'];
        $clientMatterInfo = DB::table('client_matters')->select('sel_migration_agent','sel_matter_id')->where('id',$client_matter_id)->first();
        //dd($clientMatterInfo);
        if($clientMatterInfo) {
            //get matter name
            $matterInfo = DB::table('matters')->select('title','nick_name')->where('id',$clientMatterInfo->sel_matter_id)->first();
            //dd($matterInfo);
            if($matterInfo){
                $response['matterInfo'] = $matterInfo;
            } else {
                $response['matterInfo'] = "";
            }

            $sel_migration_agent = $clientMatterInfo->sel_migration_agent;
            $agentInfo = DB::table('staff')->select(
                'id as agentId',
                'first_name',
                'last_name',
                'company_name',
                'is_migration_agent',
                'marn_number',
                'legal_practitioner_number',
                'business_address',
                'business_phone',
                'business_mobile',
                'business_email',
                'tax_number'
            )->where('id', $sel_migration_agent)->first();
            //dd($agentInfo);
            if($agentInfo){
                $response['agentInfo'] 	= $agentInfo;
                $response['status'] 	= 	true;
                $response['message']	=	'Record is exist';
            } else {
                $response['agentInfo'] 	= "";
                $response['status'] 	= 	false;
                $response['message']	=	'Record is not exist.Please try again';
            }
        }
        echo json_encode($response);
    }

    //Get Cost assignment Migration Agent Detail
    public function getCostAssignmentMigrationAgentDetail(Request $request)
    {
        $requestData = 	$request->all(); //dd($requestData);
        $response = [
            'status' => false,
            'message' => 'Record is not exist.Please try again',
            'matterInfo' => '',
            'cost_assignment_matterInfo' => '',
            'agentInfo' => '',
        ];
        $client_matter_id = $requestData['client_matter_id'];
        $clientMatterInfo = DB::table('client_matters')->select('sel_migration_agent','sel_matter_id')->where('id',$client_matter_id)->first();
        //dd($clientMatterInfo);
        if($clientMatterInfo) {
            //get matter name
            $matterInfo = DB::table('matters')->where('id',$clientMatterInfo->sel_matter_id)->first();
            //dd($matterInfo);
            if($matterInfo){
                $response['matterInfo'] = $matterInfo;
            } else {
                $response['matterInfo'] = "";
            }

            //get cost assignment matter fee
            $costassignmentmatterInfo = DB::table('cost_assignment_forms')->where('client_id',$requestData['client_id'])->where('client_matter_id',$requestData['client_matter_id'])->first();
            //dd($costassignmentmatterInfo);
            if($matterInfo){
                $response['cost_assignment_matterInfo'] = $costassignmentmatterInfo;
            } else {
                $response['cost_assignment_matterInfo'] = "";
            }

            $sel_migration_agent = $clientMatterInfo->sel_migration_agent;
            $agentInfo = DB::table('staff')->select(
                'id as agentId',
                'first_name',
                'last_name',
                'company_name',
                'is_migration_agent',
                'marn_number',
                'legal_practitioner_number',
                'business_address',
                'business_phone',
                'business_mobile',
                'business_email',
                'tax_number'
            )->where('id', $sel_migration_agent)->first();
            //dd($agentInfo);
            if($agentInfo){
                $response['agentInfo'] 	= $agentInfo;
                $response['status'] 	= 	true;
                $response['message']	=	'Record is exist';
            } else {
                $response['agentInfo'] 	= "";
                $response['status'] 	= 	false;
                $response['message']	=	'Record is not exist.Please try again';
            }
        }
        echo json_encode($response);
    }

    //Store Cost Assignment Form Values
    public function savecostassignment(Request $request)
    {   //dd( $request->all());
        $response = ['status' => false, 'message' => 'An error occurred. Please try again.'];
        $saved = false;
        if ($request->isMethod('post'))
        {
            $requestData = $request->all(); //dd($requestData);
            $discountFields = CostAssignmentForm::discountFieldsFromRequest(
                $request->boolean('discount_enabled'),
                $requestData['discount'] ?? 0
            );

            $adminClient = Admin::find($requestData['client_id'] ?? null);
            $isCompanyClient = $adminClient && (bool) $adminClient->is_company;
            $safLevyForSave = null;
            if ($isCompanyClient) {
                $safLevyRaw = isset($requestData['saf_levy']) ? trim((string) $requestData['saf_levy']) : '';
                $safLevyForSave = $safLevyRaw === '' ? null : $safLevyRaw;
            }

            if( isset($requestData['surcharge']) && $requestData['surcharge'] != '') {
                $surcharge = $requestData['surcharge'];
            } else {
                $surcharge = 'Yes';
            }

            $Dept_Base_Application_Charge = floatval($requestData['Dept_Base_Application_Charge'] ?? 0); //dd($Dept_Base_Application_Charge);
            $Dept_Base_Application_Charge_no_of_person = intval($requestData['Dept_Base_Application_Charge_no_of_person'] ?? 1); //dd($Dept_Base_Application_Charge_no_of_person);
            $Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge * $Dept_Base_Application_Charge_no_of_person;
            $Dept_Base_Application_Charge_after_person = floatval($Dept_Base_Application_Charge_after_person); //dd($Dept_Base_Application_Charge_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Base_Application_Surcharge = round($Dept_Base_Application_Charge_after_person * 0.014, 2);
            } else {
                $Dept_Base_Application_Surcharge = 0;
            }
            
            // Step 3: Final total after surcharge
            $Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person + $Dept_Base_Application_Surcharge; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

            $Dept_Non_Internet_Application_Charge = floatval($requestData['Dept_Non_Internet_Application_Charge'] ?? 0); //dd($Dept_Non_Internet_Application_Charge);
            $Dept_Non_Internet_Application_Charge_no_of_person = intval($requestData['Dept_Non_Internet_Application_Charge_no_of_person'] ?? 0); //dd($Dept_Non_Internet_Application_Charge_no_of_person);
            $Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge * $Dept_Non_Internet_Application_Charge_no_of_person;
            $Dept_Non_Internet_Application_Charge_after_person = floatval($Dept_Non_Internet_Application_Charge_after_person); //dd($Dept_Non_Internet_Application_Charge_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Non_Internet_Application_Surcharge = round($Dept_Non_Internet_Application_Charge_after_person * 0.014, 2);
            } else {
                $Dept_Non_Internet_Application_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Surcharge + $Dept_Non_Internet_Application_Charge_after_person; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

            $Dept_Additional_Applicant_Charge_18_Plus = floatval($requestData['Dept_Additional_Applicant_Charge_18_Plus'] ?? 0);
            $Dept_Additional_Applicant_Charge_18_Plus_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'] ?? 0);
            $Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus * $Dept_Additional_Applicant_Charge_18_Plus_no_of_person;
            $Dept_Additional_Applicant_Charge_18_Plus_after_person = floatval($Dept_Additional_Applicant_Charge_18_Plus_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Additional_Applicant_Charge_18_Surcharge = round($Dept_Additional_Applicant_Charge_18_Plus_after_person * 0.014, 2);
            } else {
                $Dept_Additional_Applicant_Charge_18_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Surcharge + $Dept_Additional_Applicant_Charge_18_Plus_after_person;

            $Dept_Additional_Applicant_Charge_Under_18 = floatval($requestData['Dept_Additional_Applicant_Charge_Under_18'] ?? 0);
            $Dept_Additional_Applicant_Charge_Under_18_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'] ?? 0);
            $Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18 * $Dept_Additional_Applicant_Charge_Under_18_no_of_person;
            $Dept_Additional_Applicant_Charge_Under_18_after_person = floatval($Dept_Additional_Applicant_Charge_Under_18_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Additional_Applicant_Charge_Under_18_Surcharge = round($Dept_Additional_Applicant_Charge_Under_18_after_person * 0.014, 2);
            } else {
                $Dept_Additional_Applicant_Charge_Under_18_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_Surcharge + $Dept_Additional_Applicant_Charge_Under_18_after_person;

            $Dept_Subsequent_Temp_Application_Charge = floatval($requestData['Dept_Subsequent_Temp_Application_Charge'] ?? 0);
            $Dept_Subsequent_Temp_Application_Charge_no_of_person = intval($requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'] ?? 0);
            $Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge * $Dept_Subsequent_Temp_Application_Charge_no_of_person;
            $Dept_Subsequent_Temp_Application_Charge_after_person = floatval($Dept_Subsequent_Temp_Application_Charge_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Subsequent_Temp_Application_Surcharge = round($Dept_Subsequent_Temp_Application_Charge_after_person * 0.014, 2);
            } else {
                $Dept_Subsequent_Temp_Application_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Surcharge + $Dept_Subsequent_Temp_Application_Charge_after_person;

            $Dept_Second_VAC_Instalment_Charge_18_Plus = floatval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'] ?? 0);
            $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'] ?? 0);
            $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus * $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person;
            $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = floatval($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Second_VAC_Instalment_18_Plus_Surcharge = round($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person * 0.014, 2);
            } else {
                $Dept_Second_VAC_Instalment_18_Plus_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_18_Plus_Surcharge + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;

            $Dept_Second_VAC_Instalment_Under_18 = floatval($requestData['Dept_Second_VAC_Instalment_Under_18'] ?? 0);
            $Dept_Second_VAC_Instalment_Under_18_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'] ?? 0);
            $Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18 * $Dept_Second_VAC_Instalment_Under_18_no_of_person;
            $Dept_Second_VAC_Instalment_Under_18_after_person = floatval($Dept_Second_VAC_Instalment_Under_18_after_person);

            if( $surcharge == 'Yes'){
                // Step 2: Calculate 1.4% surcharge
                $Dept_Second_VAC_Instalment_Under_18_Surcharge = round($Dept_Second_VAC_Instalment_Under_18_after_person * 0.014, 2);
            } else {
                $Dept_Second_VAC_Instalment_Under_18_Surcharge = 0;
            }
            // Step 3: Final total after surcharge
            $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_Surcharge + $Dept_Second_VAC_Instalment_Under_18_after_person;

            // Get Nomination and Sponsorship charges (no person multiplier for these)
            $Dept_Nomination_Application_Charge = floatval($requestData['Dept_Nomination_Application_Charge'] ?? 0);
            $Dept_Sponsorship_Application_Charge = floatval($requestData['Dept_Sponsorship_Application_Charge'] ?? 0);

            $TotalDoHACharges = $Dept_Base_Application_Charge_after_person
                                + $Dept_Additional_Applicant_Charge_18_Plus_after_person
                                + $Dept_Additional_Applicant_Charge_Under_18_after_person
                                + $Dept_Subsequent_Temp_Application_Charge_after_person
                                + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person
                                + $Dept_Second_VAC_Instalment_Under_18_after_person
                                + $Dept_Non_Internet_Application_Charge_after_person
                                + $Dept_Nomination_Application_Charge
                                + $Dept_Sponsorship_Application_Charge;

            if ($isCompanyClient && $safLevyForSave !== null) {
                $TotalDoHACharges += (float) str_replace(',', '', (string) $safLevyForSave);
            }

            // Calculate surcharge as 1.4% of total DoHA charges (matching frontend calculation)
            if( $surcharge == 'Yes'){
                $TotalDoHASurcharges = round($TotalDoHACharges * 0.014, 2);
            } else {
                $TotalDoHASurcharges = 0;
            }

            $TotalBLOCKFEE = $requestData['Block_1_Ex_Tax'] + $requestData['Block_2_Ex_Tax'] +  $requestData['Block_3_Ex_Tax'];

            $cost_assignment_cnt = \App\Models\CostAssignmentForm::where('client_id',$requestData['client_id'])->where('client_matter_id',$requestData['client_matter_id'])->count();
            $savedFormId = null;
            //dd($surcharge);
            if($cost_assignment_cnt >0){
                //update
                $costAssignment = \App\Models\CostAssignmentForm::where('client_id', $requestData['client_id'])
                ->where('client_matter_id', $requestData['client_matter_id'])
                ->first();
                if ($costAssignment) {
                    $savedFormId = $costAssignment->id;
                    $saved = $costAssignment->update([
                        'agent_id' => $requestData['agent_id'],
                        'surcharge' => $surcharge,
                        
                        'Dept_Base_Application_Charge' => $requestData['Dept_Base_Application_Charge'],
                        'Dept_Base_Application_Charge_no_of_person' => $requestData['Dept_Base_Application_Charge_no_of_person'],
                        'Dept_Base_Application_Charge_after_person' => $Dept_Base_Application_Charge_after_person,
                        'Dept_Base_Application_Charge_after_person_surcharge' => $Dept_Base_Application_Charge_after_person_surcharge,

                        'Dept_Non_Internet_Application_Charge' => $requestData['Dept_Non_Internet_Application_Charge'],
                        'Dept_Non_Internet_Application_Charge_no_of_person' => $requestData['Dept_Non_Internet_Application_Charge_no_of_person'],
                        'Dept_Non_Internet_Application_Charge_after_person' => $Dept_Non_Internet_Application_Charge_after_person,
                        'Dept_Non_Internet_Application_Charge_after_person_surcharge' => $Dept_Non_Internet_Application_Charge_after_person_surcharge,

                        'Dept_Additional_Applicant_Charge_18_Plus' => $requestData['Dept_Additional_Applicant_Charge_18_Plus'],
                        'Dept_Additional_Applicant_Charge_18_Plus_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'],
                        'Dept_Additional_Applicant_Charge_18_Plus_after_person' => $Dept_Additional_Applicant_Charge_18_Plus_after_person,
                        'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge' => $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge,

                        'Dept_Additional_Applicant_Charge_Under_18' => $requestData['Dept_Additional_Applicant_Charge_Under_18'],
                        'Dept_Additional_Applicant_Charge_Under_18_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'],
                        'Dept_Additional_Applicant_Charge_Under_18_after_person' => $Dept_Additional_Applicant_Charge_Under_18_after_person,
                        'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge' => $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge,

                        'Dept_Subsequent_Temp_Application_Charge' => $requestData['Dept_Subsequent_Temp_Application_Charge'],
                        'Dept_Subsequent_Temp_Application_Charge_no_of_person' => $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'],
                        'Dept_Subsequent_Temp_Application_Charge_after_person' => $Dept_Subsequent_Temp_Application_Charge_after_person,
                        'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge' => $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge,

                        'Dept_Second_VAC_Instalment_Charge_18_Plus' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'],
                        'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'],
                        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person,
                        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge,

                        'Dept_Second_VAC_Instalment_Under_18' => $requestData['Dept_Second_VAC_Instalment_Under_18'],
                        'Dept_Second_VAC_Instalment_Under_18_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'],
                        'Dept_Second_VAC_Instalment_Under_18_after_person' => $Dept_Second_VAC_Instalment_Under_18_after_person,
                        'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge' => $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge,

                        'Dept_Nomination_Application_Charge' => $requestData['Dept_Nomination_Application_Charge'],
                        'Dept_Sponsorship_Application_Charge' => $requestData['Dept_Sponsorship_Application_Charge'],
                        'saf_levy' => $isCompanyClient ? $safLevyForSave : $costAssignment->saf_levy,
                        'Block_1_Ex_Tax' => $requestData['Block_1_Ex_Tax'],
                        'Block_2_Ex_Tax' => $requestData['Block_2_Ex_Tax'],
                        'Block_3_Ex_Tax' => $requestData['Block_3_Ex_Tax'],
                        'additional_fee_1' => $requestData['additional_fee_1'],
                        'discount_enabled' => $discountFields['discount_enabled'],
                        'discount' => $discountFields['discount'],
                        'TotalDoHACharges' => $TotalDoHACharges,
                        'TotalDoHASurcharges' => $TotalDoHASurcharges,
                        'TotalBLOCKFEE' => $TotalBLOCKFEE
                    ]);
                }
            }
            else
            {
                //insert
                $obj = new CostAssignmentForm;

                $obj->client_id = $requestData['client_id'];
                $obj->client_matter_id = $requestData['client_matter_id'];
                $obj->agent_id = $requestData['agent_id'];
                $obj->surcharge = $surcharge;
                
                $obj->Dept_Base_Application_Charge = $requestData['Dept_Base_Application_Charge'];
                $obj->Dept_Base_Application_Charge_no_of_person = $requestData['Dept_Base_Application_Charge_no_of_person'];
                $obj->Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge_after_person;
                $obj->Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person_surcharge;

                $obj->Dept_Non_Internet_Application_Charge = $requestData['Dept_Non_Internet_Application_Charge'];
                 $obj->Dept_Non_Internet_Application_Charge_no_of_person = $requestData['Dept_Non_Internet_Application_Charge_no_of_person'];
                $obj->Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge_after_person;
                $obj->Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Charge_after_person_surcharge;

                $obj->Dept_Additional_Applicant_Charge_18_Plus = $requestData['Dept_Additional_Applicant_Charge_18_Plus'];
                $obj->Dept_Additional_Applicant_Charge_18_Plus_no_of_person = $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'];
                $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus_after_person;
                $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge;

                $obj->Dept_Additional_Applicant_Charge_Under_18 = $requestData['Dept_Additional_Applicant_Charge_Under_18'];
                $obj->Dept_Additional_Applicant_Charge_Under_18_no_of_person = $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'];
                $obj->Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18_after_person;
                $obj->Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge;

                $obj->Dept_Subsequent_Temp_Application_Charge = $requestData['Dept_Subsequent_Temp_Application_Charge'];
                $obj->Dept_Subsequent_Temp_Application_Charge_no_of_person = $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'];
                $obj->Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge_after_person;
                $obj->Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge;

                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'];
                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'];
                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;
                $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge;

                $obj->Dept_Second_VAC_Instalment_Under_18 = $requestData['Dept_Second_VAC_Instalment_Under_18'];
                $obj->Dept_Second_VAC_Instalment_Under_18_no_of_person = $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'];
                $obj->Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18_after_person;
                $obj->Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge;

                $obj->Dept_Nomination_Application_Charge = $requestData['Dept_Nomination_Application_Charge'];
                $obj->Dept_Sponsorship_Application_Charge = $requestData['Dept_Sponsorship_Application_Charge'];
                if ($isCompanyClient) {
                    $obj->saf_levy = $safLevyForSave;
                }

                $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'];
                $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'];
                $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'];
                $obj->additional_fee_1 = $requestData['additional_fee_1'];
                $obj->discount_enabled = $discountFields['discount_enabled'];
                $obj->discount = $discountFields['discount'];
                $obj->TotalDoHACharges = $TotalDoHACharges;
                $obj->TotalDoHASurcharges = $TotalDoHASurcharges;
                $obj->TotalBLOCKFEE = $TotalBLOCKFEE;
                $saved = $obj->save();
                if ($saved) {
                    $savedFormId = $obj->id;
                }
            }
            if (!$saved) {
                $response['status'] 	= 	false;
                $response['message']	=	'Cost assignment not added successfully.Please try again';
            } else {
                $response['status'] 	= 	true;
                $response['message']	=	'Cost assignment added successfully';
                
                // Log activity
                $action = ($cost_assignment_cnt > 0) ? 'updated' : 'created';
                $additionalFee1 = floatval($requestData['additional_fee_1'] ?? 0);
                $blockFee = floatval($TotalBLOCKFEE);
                $deptCharges = floatval($TotalDoHACharges);
                $surcharges = floatval($TotalDoHASurcharges);
                $response['action'] = $action;
                $response['form_id'] = $savedFormId;
                $response['client_matter_id'] = $requestData['client_matter_id'] ?? null;
                $response['totals'] = [
                    'block_fee' => $blockFee,
                    'dept_charges' => $deptCharges,
                    'surcharges' => $surcharges,
                    'additional_fee_1' => $additionalFee1,
                    'discount_enabled' => $discountFields['discount_enabled'],
                    'discount' => $discountFields['discount'],
                    'total_cost' => CostAssignmentForm::calculateTotalCost(
                        $blockFee,
                        $deptCharges,
                        $surcharges,
                        $additionalFee1,
                        $discountFields['discount']
                    ),
                ];
                $matter = \App\Models\ClientMatter::find($requestData['client_matter_id']);
                $matterName = $matter ? $matter->title : 'N/A';
                
                $activity = new \App\Models\ActivitiesLog;
                $activity->client_id = $requestData['client_id'];
                $activity->created_by = Auth::user()->id;
                $activity->subject = $action . ' cost assignment form';
                $activity->description = '<p>Cost assignment form has been ' . $action . ' for matter: <strong>' . $matterName . '</strong></p>';
                $activity->task_status = 0;
                $activity->pin = 0;
                $activity->save();
            }
        }
        echo json_encode($response);
    }

    public function deletecostagreement(Request $request)
    {
        $cost_agreement_id = $request->input('cost_agreement_id');
        
        if (!$cost_agreement_id) {
            return response()->json([
                'status' => false,
                'message' => 'Cost agreement ID is required'
            ]);
        }

        $costAssignment = \App\Models\CostAssignmentForm::find($cost_agreement_id);
        
        if (!$costAssignment) {
            return response()->json([
                'status' => false,
                'message' => 'Cost agreement not found'
            ]);
        }

        $client_id = $costAssignment->client_id;
        $matter = \App\Models\ClientMatter::find($costAssignment->client_matter_id);
        $matterName = $matter ? $matter->title : 'N/A';

        // Delete the cost assignment
        $deleted = $costAssignment->delete();

        if ($deleted) {
            // Log activity
            $activity = new \App\Models\ActivitiesLog;
            $activity->client_id = $client_id;
            $activity->created_by = Auth::user()->id;
            $activity->subject = 'deleted cost assignment form';
            $activity->description = '<p>Cost assignment form has been deleted for matter: <strong>' . $matterName . '</strong></p>';
            $activity->task_status = 0;
            $activity->pin = 0;
            $activity->save();

            return response()->json([
                'status' => true,
                'message' => 'Cost agreement deleted successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete cost agreement'
            ]);
        }
    }

    //save reference
    public function savereferences(Request $request)
    { 
        // Step 1: Validate required fields - client_id is mandatory
        $validated = $request->validate([
            'client_id' => 'required|integer|exists:admins,id',
            'department_reference' => 'nullable|string|max:255',
            'other_reference' => 'nullable|string|max:255',
            'client_matter_id' => 'nullable|integer|exists:client_matters,id',
            'client_unique_matter_no' => 'nullable|string|max:255',
        ]);

        // Step 2: Find the matter - ALWAYS filter by client_id first for security
        // Priority: 1) Use client_unique_matter_no from URL (id1), 2) Use client_matter_id from dropdown, 3) Get latest active matter
        $matter = null;
        $lookupMethod = '';
        $clientId = (int)$request->client_id; // Ensure integer type
        
        if ($request->has('client_unique_matter_no') && !empty($request->client_unique_matter_no)) {
            // Priority 1: Use client_unique_matter_no from URL (id1) - MUST match client_id
            $matter = \App\Models\ClientMatter::where('client_id', $clientId)
                ->where('client_unique_matter_no', $request->client_unique_matter_no)
                ->first();
            $lookupMethod = 'client_unique_matter_no: ' . $request->client_unique_matter_no;
        } elseif ($request->has('client_matter_id') && !empty($request->client_matter_id)) {
            // Priority 2: Use the matter ID from dropdown - MUST match client_id
            $matter = \App\Models\ClientMatter::where('client_id', $clientId)
                ->where('id', (int)$request->client_matter_id)
                ->first();
            $lookupMethod = 'client_matter_id: ' . $request->client_matter_id;
        } else {
            // Priority 3: Fallback - Get latest active matter - MUST match client_id
            $matter = \App\Models\ClientMatter::where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderBy('id', 'desc')
                ->first();
            $lookupMethod = 'latest active matter';
        }

        // Step 3: Verify matter exists and belongs to the client_id (double security check)
        if (!$matter) {
            Log::error('References save - Matter not found', [
                'client_id' => $clientId,
                'client_unique_matter_no' => $request->client_unique_matter_no ?? 'not provided',
                'client_matter_id' => $request->client_matter_id ?? 'not provided',
                'lookup_method' => $lookupMethod
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found for given client_id and matter information.'
            ], 404);
        }
        
        // Additional security check: Ensure the found matter actually belongs to the client_id
        if ($matter->client_id != $clientId) {
            Log::error('References save - Security violation: Matter does not belong to client', [
                'matter_id' => $matter->id,
                'matter_client_id' => $matter->client_id,
                'requested_client_id' => $clientId
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Security violation: Matter does not belong to the specified client.'
            ], 403);
        }
        
        Log::info('References save - Matter found', [
            'matter_id' => $matter->id,
            'client_id' => $matter->client_id,
            'client_unique_matter_no' => $matter->client_unique_matter_no,
            'lookup_method' => $lookupMethod,
            'current_department_reference' => $matter->department_reference,
            'current_other_reference' => $matter->other_reference
        ]);

        // Step 3: Perform the update - convert empty strings to null
        $deptRefInput = $request->input('department_reference', '');
        $otherRefInput = $request->input('other_reference', '');
        $deptRef = !empty($deptRefInput) && trim($deptRefInput) !== '' ? trim($deptRefInput) : null;
        $otherRef = !empty($otherRefInput) && trim($otherRefInput) !== '' ? trim($otherRefInput) : null;
        
        // Direct assignment and save (fields are in fillable, so this is safe)
        $matter->department_reference = $deptRef;
        $matter->other_reference = $otherRef;
        $saved = $matter->save();
        
        if (!$saved) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save references.'
            ], 500);
        }
        
        // Refresh to get latest values from database
        $matter->refresh();

        // Log for debugging
        Log::info('References saved', [
            'matter_id' => $matter->id,
            'client_id' => $request->client_id,
            'client_unique_matter_no' => $matter->client_unique_matter_no,
            'department_reference' => $matter->department_reference,
            'other_reference' => $matter->other_reference,
            'saved' => $saved
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'References updated successfully.',
            'data' => [
                'matter_id' => $matter->id,
                'client_unique_matter_no' => $matter->client_unique_matter_no,
                'department_reference' => $matter->department_reference,
                'other_reference' => $matter->other_reference
            ]
        ]);
    }

    //Check star client
    public function checkStarClient(Request $request)
    {
        $admin = \App\Models\Admin::find($request->admin_id);

        if (!$admin) {
            return response()->json(['status' => 'error', 'message' => 'Client not found']);
        }

        // is_star_client column dropped Phase 4 - always return not_star
        return response()->json(['status' => 'not_star']);
    }

    //Fetch client matter assignee


    //Delete Personal Doucment Category
    /*public function deletePersonalDocCategory(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:personal_document_types,id',
        ]);

        $category = PersonalDocumentType::findOrFail($request->id);

        // Check if the category is client-generated
        if ($category->client_id !== null) {
            $category->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Only client-generated categories can be deleted.']);
    }*/

    //Check same client_id and same client matter is already exist in db or not
    public function checkCostAssignment(Request $request)
    {
        $exists = \App\Models\CostAssignmentForm::where('client_id', $request->client_id)
                    ->where('client_matter_id', $request->client_matter_id)
                    ->exists();

        return response()->json(['exists' => $exists]);
    }

    //Store Cost Assignment Form Values of Lead
    public function savecostassignmentlead(Request $request)
    {   
        $response = ['status' => false, 'message' => 'An error occurred. Please try again.'];
        if ($request->isMethod('post'))
        {
            $requestData = $request->all(); //dd($requestData);
            $discountFields = CostAssignmentForm::discountFieldsFromRequest(
                $request->boolean('discount_enabled'),
                $requestData['discount'] ?? 0
            );
            $clientForMatter = Admin::find($requestData['client_id'] ?? null);
            if (!$clientForMatter || ! in_array($clientForMatter->type, ['client', 'lead'], true)) {
                $response['message'] = 'Invalid client.';
                echo json_encode($response);
                return;
            }
            if (! StaffClientVisibility::canAccessClientOrLead((int) $clientForMatter->id, Auth::user())) {
                $response['message'] = config('constants.unauthorized');
                echo json_encode($response);
                return;
            }
            $isCompanyClientLead = (bool) $clientForMatter->is_company;
            $safLevyForSaveLead = null;
            if ($isCompanyClientLead) {
                $safLevyRawLead = isset($requestData['saf_levy']) ? trim((string) $requestData['saf_levy']) : '';
                $safLevyForSaveLead = $safLevyRawLead === '' ? null : $safLevyRawLead;
            }
            $matterId = (int) ($requestData['matter_id'] ?? 0);
            if (! Matter::allowedForClientIsCompany($matterId, (bool) $clientForMatter->is_company)) {
                $response['message'] = 'This matter type is not valid for this client record.';
                echo json_encode($response);
                return;
            }
            //insert into client matter table
            $obj5 = new ClientMatter();
            $obj5->user_id = Auth::user()->id;
            $obj5->client_id = $requestData['client_id'];
            $obj5->office_id = $requestData['office_id'] ?? optional(Auth::user())->office_id ?? null;
            $obj5->sel_migration_agent = $requestData['migration_agent'];
            $obj5->sel_person_responsible = $requestData['person_responsible'];
            $obj5->sel_person_assisting = $requestData['person_assisting'];
            $obj5->sel_matter_id = $requestData['matter_id'];
            
            $client_matters_cnt_per_client = DB::table('client_matters')->select('id')->where('sel_matter_id',$requestData['matter_id'])->where('client_id',$requestData['client_id'])->count();
            $client_matters_current_no = $client_matters_cnt_per_client+1;
            if($requestData['matter_id'] == 1) {
                $obj5->client_unique_matter_no = 'GN_'.$client_matters_current_no;
            } else {
                $matterInfo = Matter::select('nick_name')->where('id', '=', $requestData['matter_id'])->first();
                $prefix = ($matterInfo && $matterInfo->nick_name) ? $matterInfo->nick_name : 'Matter';
                $obj5->client_unique_matter_no = $prefix."_".$client_matters_current_no;
            }
            $matterType = Matter::find($requestData['matter_id']);
            $workflowId = WorkflowAssignment::resolveWorkflowIdForNewClientMatter($matterType);
            $firstStageId = WorkflowAssignment::firstStageIdForWorkflow($workflowId);
            $obj5->workflow_id = $workflowId;
            $obj5->workflow_stage_id = $firstStageId;
            $obj5->matter_status = 1; // Active by default
            $saved5 = $obj5->save();
            $lastInsertedId = $obj5->id; // ← This gets the last inserted ID
            if ($saved5) {
                \App\Support\WorkflowStageChecklistSync::ensureSeededForMatter($obj5);
                // Saving an active matter for a lead auto-promotes admins.type to 'client'
                // via the ClientMatter::saved hook (Admin::promoteLeadWithActiveMatterToClient).

                if( isset($requestData['surcharge']) && $requestData['surcharge'] != '') {
                    $surcharge = $requestData['surcharge'];
                } else {
                    $surcharge = 'Yes';
                }

                $Dept_Base_Application_Charge = floatval($requestData['Dept_Base_Application_Charge'] ?? 0); //dd($Dept_Base_Application_Charge);
                $Dept_Base_Application_Charge_no_of_person = intval($requestData['Dept_Base_Application_Charge_no_of_person'] ?? 1); //dd($Dept_Base_Application_Charge_no_of_person);
                $Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge * $Dept_Base_Application_Charge_no_of_person;
                $Dept_Base_Application_Charge_after_person = floatval($Dept_Base_Application_Charge_after_person); //dd($Dept_Base_Application_Charge_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Base_Application_Surcharge = round($Dept_Base_Application_Charge_after_person * 0.014, 2);
                } else {
                    $Dept_Base_Application_Surcharge = 0;
                }
            
                // Step 3: Final total after surcharge
                $Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person + $Dept_Base_Application_Surcharge; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

                $Dept_Non_Internet_Application_Charge = floatval($requestData['Dept_Non_Internet_Application_Charge'] ?? 0); //dd($Dept_Non_Internet_Application_Charge);
                $Dept_Non_Internet_Application_Charge_no_of_person = intval($requestData['Dept_Non_Internet_Application_Charge_no_of_person'] ?? 0); //dd($Dept_Non_Internet_Application_Charge_no_of_person);
                $Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge * $Dept_Non_Internet_Application_Charge_no_of_person;
                $Dept_Non_Internet_Application_Charge_after_person = floatval($Dept_Non_Internet_Application_Charge_after_person); //dd($Dept_Non_Internet_Application_Charge_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Non_Internet_Application_Surcharge = round($Dept_Non_Internet_Application_Charge_after_person * 0.014, 2);
                } else {
                    $Dept_Non_Internet_Application_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Surcharge + $Dept_Non_Internet_Application_Charge_after_person; //dd($Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge);

                $Dept_Additional_Applicant_Charge_18_Plus = floatval($requestData['Dept_Additional_Applicant_Charge_18_Plus'] ?? 0);
                $Dept_Additional_Applicant_Charge_18_Plus_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'] ?? 0);
                $Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus * $Dept_Additional_Applicant_Charge_18_Plus_no_of_person;
                $Dept_Additional_Applicant_Charge_18_Plus_after_person = floatval($Dept_Additional_Applicant_Charge_18_Plus_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Additional_Applicant_Charge_18_Surcharge = round($Dept_Additional_Applicant_Charge_18_Plus_after_person * 0.014, 2);
                } else {
                    $Dept_Additional_Applicant_Charge_18_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Surcharge + $Dept_Additional_Applicant_Charge_18_Plus_after_person;

                $Dept_Additional_Applicant_Charge_Under_18 = floatval($requestData['Dept_Additional_Applicant_Charge_Under_18'] ?? 0);
                $Dept_Additional_Applicant_Charge_Under_18_no_of_person = intval($requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'] ?? 0);
                $Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18 * $Dept_Additional_Applicant_Charge_Under_18_no_of_person;
                $Dept_Additional_Applicant_Charge_Under_18_after_person = floatval($Dept_Additional_Applicant_Charge_Under_18_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Additional_Applicant_Charge_Under_18_Surcharge = round($Dept_Additional_Applicant_Charge_Under_18_after_person * 0.014, 2);
                } else {
                    $Dept_Additional_Applicant_Charge_Under_18_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_Surcharge + $Dept_Additional_Applicant_Charge_Under_18_after_person;

                $Dept_Subsequent_Temp_Application_Charge = floatval($requestData['Dept_Subsequent_Temp_Application_Charge'] ?? 0);
                $Dept_Subsequent_Temp_Application_Charge_no_of_person = intval($requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'] ?? 0);
                $Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge * $Dept_Subsequent_Temp_Application_Charge_no_of_person;
                $Dept_Subsequent_Temp_Application_Charge_after_person = floatval($Dept_Subsequent_Temp_Application_Charge_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Subsequent_Temp_Application_Surcharge = round($Dept_Subsequent_Temp_Application_Charge_after_person * 0.014, 2);
                } else {
                    $Dept_Subsequent_Temp_Application_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Surcharge + $Dept_Subsequent_Temp_Application_Charge_after_person;

                $Dept_Second_VAC_Instalment_Charge_18_Plus = floatval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'] ?? 0);
                $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'] ?? 0);
                $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus * $Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person;
                $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = floatval($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Second_VAC_Instalment_18_Plus_Surcharge = round($Dept_Second_VAC_Instalment_Charge_18_Plus_after_person * 0.014, 2);
                } else {
                    $Dept_Second_VAC_Instalment_18_Plus_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_18_Plus_Surcharge + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;

                $Dept_Second_VAC_Instalment_Under_18 = floatval($requestData['Dept_Second_VAC_Instalment_Under_18'] ?? 0);
                $Dept_Second_VAC_Instalment_Under_18_no_of_person = intval($requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'] ?? 0);
                $Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18 * $Dept_Second_VAC_Instalment_Under_18_no_of_person;
                $Dept_Second_VAC_Instalment_Under_18_after_person = floatval($Dept_Second_VAC_Instalment_Under_18_after_person);

                if( $surcharge == 'Yes'){
                    // Step 2: Calculate 1.4% surcharge
                    $Dept_Second_VAC_Instalment_Under_18_Surcharge = round($Dept_Second_VAC_Instalment_Under_18_after_person * 0.014, 2);
                } else {
                    $Dept_Second_VAC_Instalment_Under_18_Surcharge = 0;
                }
                // Step 3: Final total after surcharge
                $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_Surcharge + $Dept_Second_VAC_Instalment_Under_18_after_person;

                // Get Nomination and Sponsorship charges (no person multiplier for these)
                $Dept_Nomination_Application_Charge = floatval($requestData['Dept_Nomination_Application_Charge'] ?? 0);
                $Dept_Sponsorship_Application_Charge = floatval($requestData['Dept_Sponsorship_Application_Charge'] ?? 0);

                $TotalDoHACharges = $Dept_Base_Application_Charge_after_person
                                    + $Dept_Additional_Applicant_Charge_18_Plus_after_person
                                    + $Dept_Additional_Applicant_Charge_Under_18_after_person
                                    + $Dept_Subsequent_Temp_Application_Charge_after_person
                                    + $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person
                                    + $Dept_Second_VAC_Instalment_Under_18_after_person
                                    + $Dept_Non_Internet_Application_Charge_after_person
                                    + $Dept_Nomination_Application_Charge
                                    + $Dept_Sponsorship_Application_Charge;

                if ($isCompanyClientLead && $safLevyForSaveLead !== null) {
                    $TotalDoHACharges += (float) str_replace(',', '', (string) $safLevyForSaveLead);
                }

                // Calculate surcharge as 1.4% of total DoHA charges (matching frontend calculation)
                if( $surcharge == 'Yes'){
                    $TotalDoHASurcharges = round($TotalDoHACharges * 0.014, 2);
                } else {
                    $TotalDoHASurcharges = 0;
                }

                $TotalBLOCKFEE = $requestData['Block_1_Ex_Tax'] + $requestData['Block_2_Ex_Tax'] +  $requestData['Block_3_Ex_Tax'];

                $cost_assignment_cnt = \App\Models\CostAssignmentForm::where('client_id',$requestData['client_id'])->where('client_matter_id',$lastInsertedId)->count();
                //dd($surcharge);
                if($cost_assignment_cnt >0)
                {
                    //update
                    $costAssignment = \App\Models\CostAssignmentForm::where('client_id', $requestData['client_id'])
                    ->where('client_matter_id', $lastInsertedId)
                    ->first();
                    if ($costAssignment) 
                    {
                        $saved = $costAssignment->update([
                            'agent_id' => $requestData['agent_id'],
                            'surcharge' => $surcharge,
                            
                            'Dept_Base_Application_Charge' => $requestData['Dept_Base_Application_Charge'],
                            'Dept_Base_Application_Charge_no_of_person' => $requestData['Dept_Base_Application_Charge_no_of_person'],
                            'Dept_Base_Application_Charge_after_person' => $Dept_Base_Application_Charge_after_person,
                            'Dept_Base_Application_Charge_after_person_surcharge' => $Dept_Base_Application_Charge_after_person_surcharge,

                            'Dept_Non_Internet_Application_Charge' => $requestData['Dept_Non_Internet_Application_Charge'],
                            'Dept_Non_Internet_Application_Charge_no_of_person' => $requestData['Dept_Non_Internet_Application_Charge_no_of_person'],
                            'Dept_Non_Internet_Application_Charge_after_person' => $Dept_Non_Internet_Application_Charge_after_person,
                            'Dept_Non_Internet_Application_Charge_after_person_surcharge' => $Dept_Non_Internet_Application_Charge_after_person_surcharge,

                            'Dept_Additional_Applicant_Charge_18_Plus' => $requestData['Dept_Additional_Applicant_Charge_18_Plus'],
                            'Dept_Additional_Applicant_Charge_18_Plus_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'],
                            'Dept_Additional_Applicant_Charge_18_Plus_after_person' => $Dept_Additional_Applicant_Charge_18_Plus_after_person,
                            'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge' => $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge,

                            'Dept_Additional_Applicant_Charge_Under_18' => $requestData['Dept_Additional_Applicant_Charge_Under_18'],
                            'Dept_Additional_Applicant_Charge_Under_18_no_of_person' => $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'],
                            'Dept_Additional_Applicant_Charge_Under_18_after_person' => $Dept_Additional_Applicant_Charge_Under_18_after_person,
                            'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge' => $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge,

                            'Dept_Subsequent_Temp_Application_Charge' => $requestData['Dept_Subsequent_Temp_Application_Charge'],
                            'Dept_Subsequent_Temp_Application_Charge_no_of_person' => $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'],
                            'Dept_Subsequent_Temp_Application_Charge_after_person' => $Dept_Subsequent_Temp_Application_Charge_after_person,
                            'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge' => $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge,

                            'Dept_Second_VAC_Instalment_Charge_18_Plus' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'],
                            'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'],
                            'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person,
                            'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge' => $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge,

                            'Dept_Second_VAC_Instalment_Under_18' => $requestData['Dept_Second_VAC_Instalment_Under_18'],
                            'Dept_Second_VAC_Instalment_Under_18_no_of_person' => $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'],
                            'Dept_Second_VAC_Instalment_Under_18_after_person' => $Dept_Second_VAC_Instalment_Under_18_after_person,
                            'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge' => $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge,

                            'Dept_Nomination_Application_Charge' => $requestData['Dept_Nomination_Application_Charge'],
                            'Dept_Sponsorship_Application_Charge' => $requestData['Dept_Sponsorship_Application_Charge'],
                            'saf_levy' => $isCompanyClientLead ? $safLevyForSaveLead : $costAssignment->saf_levy,
                            'Block_1_Ex_Tax' => $requestData['Block_1_Ex_Tax'],
                            'Block_2_Ex_Tax' => $requestData['Block_2_Ex_Tax'],
                            'Block_3_Ex_Tax' => $requestData['Block_3_Ex_Tax'],
                            'additional_fee_1' => $requestData['additional_fee_1'],
                            'discount_enabled' => $discountFields['discount_enabled'],
                            'discount' => $discountFields['discount'],
                            'TotalDoHACharges' => $TotalDoHACharges,
                            'TotalDoHASurcharges' => $TotalDoHASurcharges,
                            'TotalBLOCKFEE' => $TotalBLOCKFEE
                        ]);
                    }
                }
                else
                {
                    //insert
                    $obj = new CostAssignmentForm;
                    $obj->client_id = $requestData['client_id'];
                    $obj->client_matter_id = $lastInsertedId;
                    $obj->agent_id = $requestData['migration_agent'];
                    $obj->surcharge = $surcharge;
                    
                    $obj->Dept_Base_Application_Charge = $requestData['Dept_Base_Application_Charge'];
                    $obj->Dept_Base_Application_Charge_no_of_person = $requestData['Dept_Base_Application_Charge_no_of_person'];
                    $obj->Dept_Base_Application_Charge_after_person = $Dept_Base_Application_Charge_after_person;
                    $obj->Dept_Base_Application_Charge_after_person_surcharge = $Dept_Base_Application_Charge_after_person_surcharge;

                    $obj->Dept_Non_Internet_Application_Charge = $requestData['Dept_Non_Internet_Application_Charge'];
                    $obj->Dept_Non_Internet_Application_Charge_no_of_person = $requestData['Dept_Non_Internet_Application_Charge_no_of_person'];
                    $obj->Dept_Non_Internet_Application_Charge_after_person = $Dept_Non_Internet_Application_Charge_after_person;
                    $obj->Dept_Non_Internet_Application_Charge_after_person_surcharge = $Dept_Non_Internet_Application_Charge_after_person_surcharge;

                    $obj->Dept_Additional_Applicant_Charge_18_Plus = $requestData['Dept_Additional_Applicant_Charge_18_Plus'];
                    $obj->Dept_Additional_Applicant_Charge_18_Plus_no_of_person = $requestData['Dept_Additional_Applicant_Charge_18_Plus_no_of_person'];
                    $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person = $Dept_Additional_Applicant_Charge_18_Plus_after_person;
                    $obj->Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge = $Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge;

                    $obj->Dept_Additional_Applicant_Charge_Under_18 = $requestData['Dept_Additional_Applicant_Charge_Under_18'];
                    $obj->Dept_Additional_Applicant_Charge_Under_18_no_of_person = $requestData['Dept_Additional_Applicant_Charge_Under_18_no_of_person'];
                    $obj->Dept_Additional_Applicant_Charge_Under_18_after_person = $Dept_Additional_Applicant_Charge_Under_18_after_person;
                    $obj->Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge = $Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge;

                    $obj->Dept_Subsequent_Temp_Application_Charge = $requestData['Dept_Subsequent_Temp_Application_Charge'];
                    $obj->Dept_Subsequent_Temp_Application_Charge_no_of_person = $requestData['Dept_Subsequent_Temp_Application_Charge_no_of_person'];
                    $obj->Dept_Subsequent_Temp_Application_Charge_after_person = $Dept_Subsequent_Temp_Application_Charge_after_person;
                    $obj->Dept_Subsequent_Temp_Application_Charge_after_person_surcharge = $Dept_Subsequent_Temp_Application_Charge_after_person_surcharge;

                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus'];
                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person = $requestData['Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person'];
                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person;
                    $obj->Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge = $Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge;

                    $obj->Dept_Second_VAC_Instalment_Under_18 = $requestData['Dept_Second_VAC_Instalment_Under_18'];
                    $obj->Dept_Second_VAC_Instalment_Under_18_no_of_person = $requestData['Dept_Second_VAC_Instalment_Under_18_no_of_person'];
                    $obj->Dept_Second_VAC_Instalment_Under_18_after_person = $Dept_Second_VAC_Instalment_Under_18_after_person;
                    $obj->Dept_Second_VAC_Instalment_Under_18_after_person_surcharge = $Dept_Second_VAC_Instalment_Under_18_after_person_surcharge;

                    $obj->Dept_Nomination_Application_Charge = $requestData['Dept_Nomination_Application_Charge'];
                    $obj->Dept_Sponsorship_Application_Charge = $requestData['Dept_Sponsorship_Application_Charge'];
                    if ($isCompanyClientLead) {
                        $obj->saf_levy = $safLevyForSaveLead;
                    }

                    $obj->Block_1_Ex_Tax = $requestData['Block_1_Ex_Tax'];
                    $obj->Block_2_Ex_Tax = $requestData['Block_2_Ex_Tax'];
                    $obj->Block_3_Ex_Tax = $requestData['Block_3_Ex_Tax'];
                    $obj->additional_fee_1 = $requestData['additional_fee_1'];
                    $obj->discount_enabled = $discountFields['discount_enabled'];
                    $obj->discount = $discountFields['discount'];
                    $obj->TotalDoHACharges = $TotalDoHACharges;
                    $obj->TotalDoHASurcharges = $TotalDoHASurcharges;
                    $obj->TotalBLOCKFEE = $TotalBLOCKFEE;
                    $saved = $obj->save();
                }
                if (!$saved) 
                {
                    $response['status'] 	= 	false;
                    $response['message']	=	'Cost assignment not added successfully.Please try again';
                } 
                else 
                {
                    $response['status'] 	= 	true;
                    $response['message']	=	'Cost assignment added successfully';
                    // Redirect to the newly created matter checklists tab (Create Checklist / Lead cost assignment).
                    // Additive fields only — existing clients that ignore them keep working.
                    $response['client_unique_matter_no'] = $obj5->client_unique_matter_no;
                    $response['redirect_url'] = route('clients.detail', [
                        'client_id' => base64_encode(convert_uuencode((string) $requestData['client_id'])),
                        'client_unique_matter_ref_no' => $obj5->client_unique_matter_no,
                        'tab' => 'checklists',
                    ]);
                }
            }
        }
        echo json_encode($response);
    }

    //Get Cost assignment Migration Agent Detail Lead
    public function getCostAssignmentMigrationAgentDetailLead(Request $request)
    {
        $requestData = 	$request->all(); //dd($requestData);
        //get matter info
		$matterInfo = DB::table('matters')->where('id',$requestData['client_matter_id'])->first();
		//dd($matterInfo);
		if($matterInfo){
			$response['matterInfo'] = $matterInfo;
			$response['status'] 	= 	true;
			$response['message']	=	'Record is exist';
		} else {
			$response['matterInfo'] = "";
			$response['status'] 	= 	false;
			$response['message']	=	'Record is not exist.Please try again';
		}

		//get cost assignment matter fee
		$costassignmentmatterInfo = DB::table('cost_assignment_forms')->where('client_id',$requestData['client_id'])->where('client_matter_id',$requestData['client_matter_id'])->first();
		//dd($costassignmentmatterInfo);
		if($costassignmentmatterInfo){
			$response['cost_assignment_matterInfo'] = $costassignmentmatterInfo;
		} else {
			$response['cost_assignment_matterInfo'] = "";
		}
		echo json_encode($response);
    }


}
