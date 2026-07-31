<?php

namespace App\Http\Controllers\CRM;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreForm956Request;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\ClientVisaCountry;
use App\Models\Document;
use App\Models\Form956;
use App\Models\Matter;
use App\Support\StaffClientVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use mikehaertl\pdftk\Pdf;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\JsonResponse;
use Exception;

class Form956Controller extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Ensure the current staff member may access the Form 956's client/lead.
     * Matches CRM sheet / client detail visibility rules.
     */
    protected function assertCanAccessForm956(Form956 $form): void
    {
        $this->assertCanAccessClientId((int) ($form->client_id ?? 0));
    }

    /**
     * Ensure the current staff member may access the given client/lead admin id.
     */
    protected function assertCanAccessClientId(int $clientId): void
    {
        if ($clientId <= 0 || ! StaffClientVisibility::canAccessClientOrLead($clientId, Auth::user())) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Display a listing of the forms.
     */
    public function index(): View
    {
        $forms = Form956::with(['client', 'agent'])->latest()->paginate(10);

        return view('crm.forms.index', compact('forms'));
    }

    /**
     * Show the form for creating a new form.
     */
    public function create(Request $request): View
    {
        // Default agent = logged-in staff (matches modal/store: agent_id → staff.id)
        $agent = Auth::user();
        if (! $agent) {
            abort(403, 'Unauthorized');
        }

        // Get client if client_id is provided
        $client = null;
        if ($request->has('client_id')) {
            $client = DB::table('admins')
                ->whereIn('type', ['client', 'lead'])
                ->where('id', $request->query('client_id'))
                ->first();

            // If no client is found, you might want to handle this case
            if (!$client) {
                abort(404, 'Client not found.');
            }
            $this->assertCanAccessClientId((int) $client->id);
        }

        // Get all clients (admins with type = 'client') for dropdown
        $clients = DB::table('admins')
            ->whereIn('type', ['client', 'lead'])
            ->orderBy('last_name')
            ->get();

        return view('crm.forms.create', compact('agent', 'client', 'clients'));
    }

    public function store(StoreForm956Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();
        $this->assertCanAccessClientId((int) ($validated['client_id'] ?? 0));

        try {
            $folderName = $validated['form956_folder_name'] ?? null;
            unset($validated['form956_folder_name']);

            // Create the form
            $form = Form956::create($validated);

            // When created from visa document page (folder_name provided), create only the checklist name.
            // User downloads the form, checks/updates it, then uploads to this checklist.
            $document = null;
            if ($folderName && $form->client_matter_id) {
                $form->load(['client', 'agent']);
                $agentName = $form->agent ? trim(($form->agent->first_name ?? '') . ' ' . ($form->agent->last_name ?? '')) : 'Agent';
                $agentNameDisplay = $agentName ?: 'Agent';

                $document = new Document;
                $document->user_id = Auth::user()->id;
                $document->client_id = $form->client_id;
                $document->client_matter_id = $form->client_matter_id;
                $document->form956_id = $form->id;
                $document->type = 'client';
                $document->doc_type = 'visa';
                $document->folder_name = $folderName;
                $document->checklist = '956 Form_ ' . $agentNameDisplay;
                $document->save();
            }

            // Check if the request is AJAX (from the modal)
            if ($request->ajax()) {
                $payload = [
                    'success' => true,
                    'message' => 'Form 956 created successfully.',
                    'redirect' => route('forms.show', $form),
                    'download_url' => route('forms.pdf', $form),
                    'preview_url' => route('forms.preview', $form),
                    'form_id' => (int) $form->id,
                ];

                if ($document) {
                    $payload['document'] = [
                        'id' => (int) $document->id,
                        'checklist' => $document->checklist,
                        'folder_name' => $document->folder_name,
                        'client_matter_id' => $document->client_matter_id,
                        'client_id' => (int) $document->client_id,
                        'form956_id' => (int) $form->id,
                        'doc_type' => 'visa',
                    ];
                }

                return response()->json($payload, 200);
            }

            // For non-AJAX requests, redirect as before
            return redirect()->route('forms.show', $form)->with('success', 'Form 956 created successfully.');
        } catch (\Exception $e) {
            // For AJAX requests, return error response
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['general' => ['Failed to create Form 956: ' . $e->getMessage()]]
                ], 422);
            }

            // For non-AJAX requests, redirect back with error
            return redirect()->back()->with('error', 'Failed to create Form 956.');
        }
    }

    /**
     * Display the specified form.
     */
    public function show(Form956 $form): View
    {
        $this->assertCanAccessForm956($form);
        $form->load(['client', 'agent']); //dd($form);

        return view('crm.forms.show', compact('form'));
    }

    /**
     * Extract field names from the Form 956 PDF template.
     */
    public function extractFieldNames()
    {
        $templatePath = storage_path('app/public/form956_template.pdf');

        if (!file_exists($templatePath)) {
            return response()->json(['error' => 'PDF template not found.'], 404);
        }

        try {
            $pdf = new Pdf($templatePath);
            $fields = $pdf->getDataFields();

            $fieldNames = [];
            foreach ($fields as $field) {
                if (isset($field['FieldName'])) {
                    $fieldNames[] = $field['FieldName'];
                }
            }

            return response()->json(['fields' => $fieldNames]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error extracting fields: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate PDF for the form.
     */
    public function generatePdf(Form956 $form)
    {
        $this->assertCanAccessForm956($form);
        $form->load(['client', 'agent']);
        $templatePath = storage_path('app/public/form956_template.pdf');

        if (!file_exists($templatePath)) {
            return back()->with('error', 'PDF template not found. Please contact the administrator.');
        }

        try {
            $pdf = new Pdf($templatePath);
            // Client address split
            $client_address_parts_line1 = "";
            $client_address_parts_line2 = "";
            $client_address_parts_line3 = "";
            $client_address_parts_postcode = "";

            if( $this->getClientAddress($form->client->id) != '') {
                $client_address = $this->getClientAddress($form->client->id);
                if($client_address !=  ''){
                    $client_address_parts = $this->formatAddressForPDFClient($client_address); //dd($client_address_parts);
                    if(!empty($client_address_parts)){
                        $client_address_parts_line1 = $client_address_parts['line1'];
                        $client_address_parts_line2 = $client_address_parts['line2'];
                        $client_address_parts_line3 = $client_address_parts['line3'];
                        $client_address_parts_postcode = $client_address_parts['postcode'];
                    }
                }
            }

            $dobFormated = $this->formatClientDobForForm956($form->client->dob ?? null);

            $agentDeclarationDateFormated = 'NA';
            if($form->agent_declaration_date != ''){
                $agentDecArr = explode('-',$form->agent_declaration_date);
                if(!empty($agentDecArr)){
                    $agentDeclarationDateFormated = $agentDecArr[2].'/'.$agentDecArr[1].'/'.$agentDecArr[0];
                } else{
                    $agentDeclarationDateFormated = 'NA';
                }
            }

            $clientDeclarationDateFormated = 'NA';
            if($form->client_declaration_date != ''){
                $clientDecArr = explode('-',$form->client_declaration_date);
                if(!empty($clientDecArr)){
                    $clientDeclarationDateFormated = $clientDecArr[2].'/'.$clientDecArr[1].'/'.$clientDecArr[0];
                } else{
                    $clientDeclarationDateFormated = 'NA';
                }
            }
             // Agent address split
            $agent_address_parts_line1 = "";
            $agent_address_parts_line2 = "";
            $agent_address_parts_line3 = "";
            $agent_address_parts_postcode = "";

            $agent_address = $form->agent->business_address;
            if($agent_address != ''){
                $agent_address_parts = $this->formatAddressForPDFAgent($agent_address);
                //dd($agent_address_parts);
                if(!empty($agent_address_parts)){
                    $agent_address_parts_line1 = $agent_address_parts['line1'];
                    $agent_address_parts_line2 = $agent_address_parts['line2'];
                    $agent_address_parts_line3 = $agent_address_parts['line3'];
                    $agent_address_parts_postcode = $agent_address_parts['postcode'];
                }
            }

            $date_lodged_arr_formated = "";
            if($form->date_lodged != "") {
                $date_lodged_arr = explode("-",$form->date_lodged);
                if(!empty($date_lodged_arr)){
                    $date_lodged_arr_formated =$date_lodged_arr[2].' '.$date_lodged_arr[1].' '.$date_lodged_arr[0];
                }
            }
            //dd($date_lodged_arr_formated);

            $visaSubclassLabel = $this->resolveVisaSubclassLabelForForm956($form);

            $formData = [
                // Client details
                'cc.name fam' => $form->client->last_name,
                'cc.name giv' => $form->client->first_name,
                'cc.dob' =>  $dobFormated,

                 'cc.resadd str' => $client_address_parts_line1,
                'cc.resadd sub' => $client_address_parts_line2,
                'cc.resadd cntry' => $client_address_parts_line3,
                'cc.resadd pc' => $client_address_parts_postcode,

                'cc.mob' => $this->formatClientMobileForForm956($form->client),
                // Intentionally blank: internal CRM client reference must not appear as Home Affairs Client ID
                'cc.diac id' => '',
                'cc.org name' => $form->client->company_name ? $form->client->company_name : 'NA',

                // Agent details
                'mg.name fam' => $form->agent->last_name,
                'mg.name giv' => $form->agent->first_name,
                'mg.org name' => $form->agent->company_name,
                'mg.marn' => $form->agent->marn_number ?? '',
                'mg.lpn' => $form->agent->legal_practitioner_number ?? '',
                'mg.email' => $form->agent->business_email ?? '',
                'mg.email agree' => 'on',
                'mg.comm' => 'Yes',

                'mg.resadd str' => $agent_address_parts_line1,
                'mg.resadd sub' => $agent_address_parts_line2,
                'mg.resadd cntry' => $agent_address_parts_line3,
                'mg.resadd pc' =>  $agent_address_parts_postcode,

                'mg.postal str' => 'AS ABOVE',

                'mg.mob' => $this->formatAgentMobileForForm956($form->agent),

                 // Form type
                'mg.app' => $form->form_type === 'appointment' ? 'No' : 'Yes',

                'mg.title' => $form->agent->gender === 'Male' ? 'mr' : 'ms',
                'mg.title' => $form->agent->gender === 'Female' ? 'ms' : 'mr',

                // Question 12: Person receiving immigration assistance
                'cc.person rec' => $form->assistance_visa_application == 1 ? 'visa' :
                                 ($form->assistance_sponsorship == 1 ? 'sponsor' :
                                 ($form->assistance_nomination == 1 ? 'nom' : 'visa')),

                // Agent type
                'mg.prov assist' => $form->is_registered_migration_agent ? 'reg' : ($form->is_legal_practitioner ? 'Legal' : ($form->is_exempt_person ? 'exampt' : 'Off')),

                // Exempt person reason
                'mg.reason ex' => 'Off', // exempt_person_reason column dropped Phase 4

                // Question 10: Is there another registered migration agent or legal practitioner
                'mg.oth mig' => 'No',

                 // Question 15: Application Date lodged,Not yet lodged
                'ta.lodged' => $date_lodged_arr_formated ?? '',
                'ta.not yet' => $form->not_lodged == '1' ? 'IAAAS' : 'Off',
                // Q15 Subclass: matter sel_matter_id when client_matter_id set; else client visa row heuristic
                'ta.type' => $visaSubclassLabel,
                'ta.typecancel' => $visaSubclassLabel,

                // Assistance type
                'ta.assist' => $form->assistance_visa_application ? 'Application' : ($form->assistance_cancellation ? 'Cancellation' : ($form->assistance_other ? 'Specific' : 'Off')),
                'ta.specific matter' => $form->assistance_other_details ?? '',

                // Authorized recipient
                'ar.also' => $form->is_authorized_recipient ? 'Yes' : 'No',
                'mg.ending ar' => $form->withdraw_authorized_recipient ? 'Yes' : 'No',

                // Declarations
                'mg.dec 1' => $form->agent_declared ? 'on' : 'Off', // Appointment declaration
                'mg.dec 2' => $form->is_authorized_recipient && $form->agent_declared ? 'on' : 'Off', // Authorized recipient declaration
                'cc.dec 1' => $form->client_declared ? 'on' : 'Off', // Client appointment declaration
                'cc.dec 2' => $form->is_authorized_recipient && $form->client_declared ? 'on' : 'Off', // Client authorized recipient declaration
                'mg.dec date' => $form->agent_declaration_date ? $agentDeclarationDateFormated : '',
                'cc.dec date' => $form->client_declaration_date ? $clientDeclarationDateFormated : '',
            ];

            //dd($formData);

            // Handle ending appointment declarations if form_type is withdrawal
            if ($form->form_type === 'withdrawal') {
                $formData['mg.dec 3'] = $form->agent_declared ? 'on' : 'Off'; // Ending appointment
                $formData['mg.dec 4'] = $form->withdraw_authorized_recipient && $form->agent_declared ? 'on' : 'Off'; // Withdrawal of authorized recipient
                $formData['cc.dec 3'] = $form->client_declared ? 'on' : 'Off'; // Client ending appointment
                $formData['cc.dec 4'] = $form->withdraw_authorized_recipient && $form->client_declared ? 'on' : 'Off'; // Client withdrawal of authorized recipient
            }

            $pdf->fillForm($formData)->needAppearances();

            $familyName = $form->client->family_name ?? $form->client->last_name ?? 'client';
            $filename = 'form956_' . $familyName . '_' . date('Y-m-d') . '.pdf';
            return response()->streamDownload(
                fn () => $pdf->saveAs('php://output'),
                $filename
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    public function getClientAddress($clientId)
    {
        // Try to find the address where is_current = 1 for the given client_id
        $addressRecord = DB::table('client_addresses')
            ->where('client_id', $clientId)
            ->where('is_current', 1)
            ->first();

        // If a record with is_current = 1 is found, return its address
        if ($addressRecord) {
            return $addressRecord->address;
        } else {
            // If no record with is_current = 1 is found, get the latest record by created_at
            $latestAddressRecord = DB::table('client_addresses')
                ->where('client_id', $clientId)
                ->orderByRaw(\App\Models\ClientAddress::ORDER_BY_DISPLAY_SQL)
                ->orderByDesc('id')
                ->first();

            // Return the address from the latest record, or null if no records exist
            return $latestAddressRecord ? $latestAddressRecord->address : null;
        }
    }

    /**
     * Form 956 question 13 — PDF field cc.dob is one box under DAY / MONTH / YEAR labels; use spaces only (no slashes).
     *
     * @param  string|null  $dob  Client date of birth (typically Y-m-d)
     */
    protected function formatClientDobForForm956(?string $dob): string
    {
        if ($dob === null || trim($dob) === '') {
            return 'NA';
        }

        $dobArr = explode('-', $dob);
        if (count($dobArr) < 3 || trim($dobArr[0]) === '' || trim($dobArr[1]) === '' || trim($dobArr[2]) === '') {
            return 'NA';
        }

        // Database order Y-m-d → display day month year
        $year = trim($dobArr[0]);
        $month = trim($dobArr[1]);
        $day = trim($dobArr[2]);

        return $day . ' ' . $month . ' ' . $year;
    }

    /**
     * E.164-style compact number: country code + national digits, no space (e.g. +61400434884).
     * For Australia (+61), strips a leading 0 from the national part (04… → 4…).
     */
    protected function combineCountryCodeWithPhone(string $countryCode, string $phone): string
    {
        $code = trim($countryCode);
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        if ($code === '') {
            return $phone;
        }

        // Already international: compact whitespace
        if (str_starts_with($phone, '+')) {
            return preg_replace('/\s+/', '', $phone);
        }

        $codeDigits = preg_replace('/\D+/', '', ltrim($code, '+'));
        if ($codeDigits === '') {
            return $phone;
        }

        $digitsOnly = preg_replace('/\D+/', '', $phone);
        if ($digitsOnly === '') {
            return (str_starts_with($code, '+') ? $code : '+' . $codeDigits) . $phone;
        }

        // National number already includes country code (e.g. 61400… without +)
        if (str_starts_with($digitsOnly, $codeDigits) && strlen($digitsOnly) > strlen($codeDigits)) {
            return '+' . $digitsOnly;
        }

        // Duplicate-prefix guard (raw string started with +country or country)
        if (
            str_starts_with($phone, $code)
            || str_starts_with($phone, '+' . $codeDigits)
        ) {
            return '+' . $digitsOnly;
        }

        $national = $digitsOnly;

        // Australia: drop trunk 0 (0400… → 400…)
        if ($codeDigits === '61' && str_starts_with($national, '0') && strlen($national) > 1) {
            $national = substr($national, 1);
        }

        return '+' . $codeDigits . $national;
    }

    /**
     * Form 956 question 13 — client Mobile/cell: country_code + phone.
     *
     * @param  \App\Models\Admin|object|null  $client
     */
    protected function formatClientMobileForForm956($client): string
    {
        if ($client === null) {
            return '';
        }

        return $this->combineCountryCodeWithPhone(
            (string) ($client->country_code ?? ''),
            (string) ($client->phone ?? '')
        );
    }

    /**
     * Form 956 question 6 — migration agent Mobile/cell: staff.country_code + business_mobile (or business_phone).
     *
     * @param  \App\Models\Staff|object|null  $agent
     */
    protected function formatAgentMobileForForm956($agent): string
    {
        if ($agent === null) {
            return '';
        }

        $mobile = trim((string) ($agent->business_mobile ?? ''));
        $phone = $mobile !== '' ? $mobile : trim((string) ($agent->business_phone ?? ''));

        return $this->combineCountryCodeWithPhone(
            (string) ($agent->country_code ?? ''),
            $phone
        );
    }

    /**
     * Form 956 Q15 subclass: prefer Matter linked via form's client_matter → sel_matter_id.
     * Falls back to {@see resolveClientVisaTypeLabelForForm956} when unavailable.
     */
    protected function resolveVisaSubclassLabelForForm956(Form956 $form): string
    {
        $clientMatterId = $form->client_matter_id;
        if ($clientMatterId) {
            $selMatterId = ClientMatter::query()
                ->where('id', $clientMatterId)
                ->where('client_id', $form->client_id)
                ->value('sel_matter_id');

            if ($selMatterId !== null && $selMatterId !== '') {
                $matter = Matter::query()
                    ->select('id', 'title', 'nick_name')
                    ->where('id', $selMatterId)
                    ->first();

                $label = $this->formatMatterTitleNickForForm956($matter);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return $this->resolveClientVisaTypeLabelForForm956((int) $form->client_id);
    }

    /**
     * Same display string as historical Q15 (title, or title(nick_name)).
     */
    protected function formatMatterTitleNickForForm956(?Matter $matter): string
    {
        if (! $matter) {
            return '';
        }

        $title = trim((string) ($matter->title ?? ''));
        $nick = trim((string) ($matter->nick_name ?? ''));
        if ($title === '') {
            return '';
        }

        return $nick !== '' ? $title . '(' . $nick . ')' : $title;
    }

    /**
     * Fallback Q15 label: client_visa_countries row (latest visa_expiry_date, else latest id with null expiry).
     */
    protected function resolveClientVisaTypeLabelForForm956(int $clientId): string
    {
        if ($clientId <= 0) {
            return '';
        }

        $withExpiry = ClientVisaCountry::query()
            ->select('visa_type')
            ->where('client_id', $clientId)
            ->whereNotNull('visa_expiry_date')
            ->orderByDesc('visa_expiry_date')
            ->first();

        $row = $withExpiry;
        if (! $row) {
            $row = ClientVisaCountry::query()
                ->select('visa_type')
                ->where('client_id', $clientId)
                ->whereNull('visa_expiry_date')
                ->orderByDesc('id')
                ->first();
        }

        if (! $row || $row->visa_type === null || $row->visa_type === '') {
            return '';
        }

        $matter = Matter::query()
            ->select('id', 'title', 'nick_name')
            ->where('id', $row->visa_type)
            ->first();

        return $this->formatMatterTitleNickForForm956($matter);
    }

    /**
     * Split a full address into PDF lines (street, suburb/city, country) + separate postcode.
     * Form 956 *resadd pc* must hold only the postcode; *resadd cntry* is the country row (e.g. Australia).
     *
     * @param  string  $fullAddress  Raw address string
     * @param  bool  $stripAustralia  Client addresses: normalize country row and merge state into line 2 when country was trailing
     */
    protected function splitAddressForForm956Pdf(string $fullAddress, bool $stripAustralia = false): array
    {
        $empty = ['line1' => '', 'line2' => '', 'line3' => '', 'postcode' => ''];

        if ($fullAddress === null || trim($fullAddress) === '') {
            return $empty;
        }

        $fullAddress = trim(preg_replace('/\s+/', ' ', $fullAddress));

        // Trailing country stops postcode-at-end matching; strip first so postcodes like "V3V5K8" before "Canada" parse correctly
        $trailingCountryLabel = null;
        if (preg_match('/,?\s*(Australia|AU)\s*$/i', $fullAddress)) {
            $trailingCountryLabel = 'Australia';
            $fullAddress = trim(preg_replace('/,?\s*(Australia|AU)\s*$/i', '', $fullAddress));
        } elseif (preg_match('/,?\s*Canada\s*$/i', $fullAddress)) {
            $trailingCountryLabel = 'Canada';
            $fullAddress = trim(preg_replace('/,?\s*Canada\s*$/i', '', $fullAddress));
        }

        // Australian 4-digit postcode at end of (possibly country-stripped) string
        preg_match('/\s*(\d{4})\s*$/', $fullAddress, $postcodeMatch);
        $postcode = $postcodeMatch[1] ?? '';
        $withoutPostcode = trim(preg_replace('/\s*\d{4}\s*$/', '', $fullAddress));

        if ($stripAustralia) {
            $withoutPostcode = trim(preg_replace('/\b(?:Australia|AU)\b/i', '', $withoutPostcode));
            $withoutPostcode = trim(preg_replace('/\s+/', ' ', $withoutPostcode));
        }

        $statePattern = '/\b(NSW|VIC|QLD|SA|WA|TAS|NT|ACT)\b/i';

        $result = null;

        // Comma-separated
        if (strpos($withoutPostcode, ',') !== false) {
            $parts = array_map('trim', explode(',', $withoutPostcode));
            $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

            if ($stripAustralia) {
                $parts = array_values(array_filter(
                    $parts,
                    fn ($p) => ! preg_match('/\b(?:Australia|AU)\b/i', $p)
                ));
            }

            // If country was not stripped from string (spacing/typos), drop trailing country segment before postcode detection
            [$parts, $partsCountryLabel] = $this->popTrailingCountrySegmentFromParts($parts);
            if ($trailingCountryLabel === null && $partsCountryLabel !== null) {
                $trailingCountryLabel = $partsCountryLabel;
            }

            // Postcode in its own comma segment (e.g. "..., VIC, v4v3" or intl codes) — not AU state/country
            [$parts, $segmentPostcode] = $this->extractTrailingPostcodeFromCommaParts($parts);
            if ($segmentPostcode !== '' && $postcode === '') {
                $postcode = $segmentPostcode;
            }

            $n = count($parts);
            if ($n === 0) {
                $result = array_merge($empty, ['postcode' => $postcode]);
            } elseif ($n === 1) {
                $result = $this->splitAddressSingleBlockNoComma($parts[0], $postcode, $statePattern);
            } elseif ($n === 2) {
                $result = $this->splitAddressTwoCommaParts($parts[0], $parts[1], $postcode, $statePattern);
            } else {
                // 3+ segments: e.g. "Unit 5", "55 Gawler Pl Adelaide", "SA"
                $line1 = $parts[0];
                $line3 = $parts[$n - 1];
                $line2 = trim(implode(', ', array_slice($parts, 1, $n - 2)));

                $result = [
                    'line1' => $line1,
                    'line2' => $line2,
                    'line3' => $line3,
                    'postcode' => $postcode,
                ];
            }
        } else {
            $result = $this->splitAddressSingleBlockNoComma($withoutPostcode, $postcode, $statePattern);
        }

        // PDF *resadd cntry* = country; suburb + state on line 2; *resadd pc* = postcode only
        $line3Trim = trim($result['line3'] ?? '');
        $isAuStateOnly = $line3Trim !== '' && (bool) preg_match('/^(NSW|VIC|QLD|SA|WA|TAS|NT|ACT)$/i', $line3Trim);
        if ($trailingCountryLabel !== null) {
            $result = $this->mergeStateIntoLine2AndSetCountry($result, $statePattern, $trailingCountryLabel);
        } elseif ($stripAustralia && $isAuStateOnly) {
            $result = $this->mergeStateIntoLine2AndSetCountry($result, $statePattern, 'Australia');
        }

        return $this->stripTrailingPostcodeFromLine2($result);
    }

    /**
     * Remove a trailing "Australia" / "Canada" segment from comma parts when not already stripped from the string.
     *
     * @return array{0: array<int, string>, 1: string|null}
     */
    protected function popTrailingCountrySegmentFromParts(array $parts): array
    {
        if ($parts === []) {
            return [$parts, null];
        }

        $last = trim($parts[count($parts) - 1]);
        if (preg_match('/^(Australia|AU)$/i', $last)) {
            array_pop($parts);

            return [array_values(array_filter($parts, fn ($p) => $p !== '')), 'Australia'];
        }
        if (preg_match('/^Canada$/i', $last)) {
            array_pop($parts);

            return [array_values(array_filter($parts, fn ($p) => $p !== '')), 'Canada'];
        }

        return [$parts, null];
    }

    /**
     * If the last comma-separated segment is a postcode (not AU state / country), pop it and return it.
     * Handles alphanumeric postcodes (e.g. some intl formats) and 4-digit-only segments.
     *
     * @return array{0: array<int, string>, 1: string}
     */
    protected function extractTrailingPostcodeFromCommaParts(array $parts): array
    {
        if (count($parts) < 2) {
            return [$parts, ''];
        }

        $last = trim($parts[count($parts) - 1]);
        if ($last === '') {
            return [$parts, ''];
        }

        if (preg_match('/^(NSW|VIC|QLD|SA|WA|TAS|NT|ACT)$/i', $last)) {
            return [$parts, ''];
        }

        if (preg_match('/^(Australia|AU|Canada)$/i', $last)) {
            return [$parts, ''];
        }

        if (preg_match('/^\d{4}$/', $last)) {
            array_pop($parts);

            return [array_values($parts), $last];
        }

        // Alphanumeric postcode token (no spaces), contains a digit, reasonable length (e.g. v4v3, UK-style compact)
        if (
            strlen($last) >= 3 && strlen($last) <= 12
            && preg_match('/^[A-Za-z0-9\-]+$/', $last)
            && preg_match('/\d/', $last)
        ) {
            array_pop($parts);

            return [array_values($parts), $last];
        }

        return [$parts, ''];
    }

    /**
     * Ensure postcode does not remain on line 2 (e.g. "… SA 5000") when *pc* is set.
     *
     * @param  array{line1:string,line2:string,line3:string,postcode:string}  $result
     * @return array{line1:string,line2:string,line3:string,postcode:string}
     */
    protected function stripTrailingPostcodeFromLine2(array $result): array
    {
        $pc = trim($result['postcode'] ?? '');
        if ($pc === '') {
            return $result;
        }

        $line2 = $result['line2'] ?? '';
        if ($line2 === '') {
            return $result;
        }

        $quoted = preg_quote($pc, '/');
        // "Suburb, VIC, V3V5K8" or "Suburb VIC V3V5K8"
        if (preg_match('/(?:,\s*|\s+)' . $quoted . '\s*$/', $line2)) {
            $result['line2'] = trim(preg_replace('/(?:,\s*|\s+)' . $quoted . '\s*$/', '', $line2));
        }

        return $result;
    }

    /**
     * @param  array{line1:string,line2:string,line3:string,postcode:string}  $result
     * @return array{line1:string,line2:string,line3:string,postcode:string}
     */
    protected function mergeStateIntoLine2AndSetCountry(array $result, string $statePattern, string $countryName): array
    {
        $line3 = trim($result['line3'] ?? '');
        if ($line3 !== '' && preg_match($statePattern, $line3) && ! preg_match('/\b(?:Australia|AU)\b/i', $line3)) {
            $result['line2'] = trim(($result['line2'] ?? '') . ' ' . $line3);
        }
        $result['line3'] = $countryName;

        return $result;
    }

    /**
     * One comma-free block after postcode removal (street + suburb + state in one line).
     */
    protected function splitAddressSingleBlockNoComma(string $block, string $postcode, string $statePattern): array
    {
        $line1 = $line2 = $line3 = '';

        if (preg_match($statePattern, $block, $stateMatches, PREG_OFFSET_CAPTURE)) {
            $statePos = $stateMatches[0][1];
            $line1 = trim(substr($block, 0, $statePos));
            $remaining = trim(substr($block, $statePos));
            $words = preg_split('/\s+/', $remaining);
            if (count($words) > 1) {
                $line2 = implode(' ', array_slice($words, 0, -1));
                $line3 = end($words);
            } else {
                $line3 = $remaining;
            }
        } else {
            $line1 = $block;
        }

        return [
            'line1' => $line1,
            'line2' => $line2,
            'line3' => $line3,
            'postcode' => $postcode,
        ];
    }

    /**
     * Exactly two comma-separated segments after postcode removal.
     */
    protected function splitAddressTwoCommaParts(string $first, string $second, string $postcode, string $statePattern): array
    {
        $line1 = $first;

        if (preg_match($statePattern, $second, $stateMatches, PREG_OFFSET_CAPTURE)) {
            $statePos = $stateMatches[0][1];
            $line2 = trim(substr($second, 0, $statePos));
            $line3 = trim(substr($second, $statePos));

            return [
                'line1' => $line1,
                'line2' => $line2,
                'line3' => $line3,
                'postcode' => $postcode,
            ];
        }

        $words = preg_split('/\s+/', $second);
        if (count($words) > 1) {
            return [
                'line1' => $line1,
                'line2' => implode(' ', array_slice($words, 0, -1)),
                'line3' => end($words),
                'postcode' => $postcode,
            ];
        }

        return [
            'line1' => $line1,
            'line2' => $second,
            'line3' => '',
            'postcode' => $postcode,
        ];
    }

    // Split agent business address (Form 956 question 4 / agent block)
    public function formatAddressForPDFAgent($fullAddress)
    {
        return $this->splitAddressForForm956Pdf((string) $fullAddress, false);
    }

    // Split client residential address
    public function formatAddressForPDFClient($fullAddress)
    {
        return $this->splitAddressForForm956Pdf((string) $fullAddress, true);
    }


    /**
     * Preview the PDF in browser.
    */
    public function previewPdf(Form956 $form)
    {
        $this->assertCanAccessForm956($form);
        $form->load(['client', 'agent']);  //dd($form->client);
        $templatePath = storage_path('app/public/form956_template.pdf');

        if (!file_exists($templatePath)) {
            return back()->with('error', 'PDF template not found. Please contact the administrator.');
        }

        try {

            //dd($form->agent->gender );
            $pdf = new Pdf($templatePath);

            // Client address split
            $client_address_parts_line1 = "";
            $client_address_parts_line2 = "";
            $client_address_parts_line3 = "";
            $client_address_parts_postcode = "";

            if( $this->getClientAddress($form->client->id) != '') {
                $client_address = $this->getClientAddress($form->client->id);
                if($client_address !=  ''){
                    $client_address_parts = $this->formatAddressForPDFClient($client_address); //dd($client_address_parts);
                    if(!empty($client_address_parts)){
                        $client_address_parts_line1 = $client_address_parts['line1'];
                        $client_address_parts_line2 = $client_address_parts['line2'];
                        $client_address_parts_line3 = $client_address_parts['line3'];
                        $client_address_parts_postcode = $client_address_parts['postcode'];
                    }
                }
            }



            $dobFormated = $this->formatClientDobForForm956($form->client->dob ?? null);

            $agentDeclarationDateFormated = 'NA';
            if($form->agent_declaration_date != ''){
                $agentDecArr = explode('-',$form->agent_declaration_date);
                if(!empty($agentDecArr)){
                    $agentDeclarationDateFormated = $agentDecArr[2].'/'.$agentDecArr[1].'/'.$agentDecArr[0];
                } else{
                    $agentDeclarationDateFormated = 'NA';
                }
            }

            $clientDeclarationDateFormated = 'NA';
            if($form->client_declaration_date != ''){
                $clientDecArr = explode('-',$form->client_declaration_date);
                if(!empty($clientDecArr)){
                    $clientDeclarationDateFormated = $clientDecArr[2].'/'.$clientDecArr[1].'/'.$clientDecArr[0];
                } else{
                    $clientDeclarationDateFormated = 'NA';
                }
            }

            // Agent address split
            $agent_address_parts_line1 = "";
            $agent_address_parts_line2 = "";
            $agent_address_parts_line3 = "";
            $agent_address_parts_postcode = "";

            $agent_address = $form->agent->business_address;
            if($agent_address != ''){
                $agent_address_parts = $this->formatAddressForPDFAgent($agent_address); //dd($agent_address_parts);
                if(!empty($agent_address_parts)){
                    $agent_address_parts_line1 = $agent_address_parts['line1'];
                    $agent_address_parts_line2 = $agent_address_parts['line2'];
                    $agent_address_parts_line3 = $agent_address_parts['line3'];
                    $agent_address_parts_postcode = $agent_address_parts['postcode'];
                }
            }

            $date_lodged_arr_formated = "";
            if($form->date_lodged != "") {
                $date_lodged_arr = explode("-",$form->date_lodged);
                if(!empty($date_lodged_arr)){
                    $date_lodged_arr_formated =$date_lodged_arr[2].' '.$date_lodged_arr[1].' '.$date_lodged_arr[0];
                }
            }
            //dd($date_lodged_arr_formated);
            // Pass to PDF/blade
            $visaSubclassLabel = $this->resolveVisaSubclassLabelForForm956($form);

            $formData = [
                // Client details
                'cc.name fam' => $form->client->last_name, //$form->client->family_name
                'cc.name giv' => $form->client->first_name, //$form->client->given_names
                'cc.dob' =>  $dobFormated,

                'cc.resadd str' => $client_address_parts_line1,
                'cc.resadd sub' => $client_address_parts_line2,
                'cc.resadd cntry' => $client_address_parts_line3,
                'cc.resadd pc' => $client_address_parts_postcode,

                'cc.mob' => $this->formatClientMobileForForm956($form->client),
                // Intentionally blank: internal CRM client reference must not appear as Home Affairs Client ID
                'cc.diac id' => '',
                'cc.org name' => $form->client->company_name ? $form->client->company_name : 'NA',

                // Agent details
                'mg.name fam' => $form->agent->last_name, // Split if family/given names separate
                'mg.name giv' => $form->agent->first_name,
                'mg.org name' => $form->agent->company_name,
                'mg.marn' => $form->agent->marn_number ?? '',
                'mg.lpn' => $form->agent->legal_practitioner_number ?? '',
                'mg.email' => $form->agent->business_email ?? '',
                'mg.email agree' => 'on',
                'mg.comm' => 'Yes',

                'mg.resadd str' => $agent_address_parts_line1,
                'mg.resadd sub' => $agent_address_parts_line2,
                'mg.resadd cntry' => $agent_address_parts_line3,
                'mg.resadd pc' =>  $agent_address_parts_postcode,

                'mg.postal str' => 'AS ABOVE',
                'mg.mob' => $this->formatAgentMobileForForm956($form->agent),

                // Form type
                'mg.app' => $form->form_type === 'appointment' ? 'No' : 'Yes',

                'mg.title' => $form->agent->gender === 'Male' ? 'mr' : 'ms',
                'mg.title' => $form->agent->gender === 'Female' ? 'ms' : 'mr',

                // Question 12: Person receiving immigration assistance
                'cc.person rec' => $form->assistance_visa_application == 1 ? 'visa' :
                                 ($form->assistance_sponsorship == 1 ? 'sponsor' :
                                 ($form->assistance_nomination == 1 ? 'nom' : 'visa')),

                // Agent type
                'mg.prov assist' => $form->is_registered_migration_agent ? 'reg' : ($form->is_legal_practitioner ? 'Legal' : ($form->is_exempt_person ? 'exampt' : 'Off')),

                // Exempt person reason
                'mg.reason ex' => 'Off', // exempt_person_reason column dropped Phase 4

                // Question 10: Is there another registered migration agent or legal practitioner
                'mg.oth mig' => 'No',

                 // Question 15: Application Date lodged,Not yet lodged
                'ta.lodged' => $date_lodged_arr_formated ?? '',
                'ta.not yet' => $form->not_lodged == '1' ? 'IAAAS' : 'Off',
                // Q15 Subclass: matter sel_matter_id when client_matter_id set; else client visa row heuristic
                'ta.type' => $visaSubclassLabel,
                'ta.typecancel' => $visaSubclassLabel,

                // Assistance type
                'ta.assist' => $form->assistance_visa_application ? 'Application' : ($form->assistance_cancellation ? 'Cancellation' : ($form->assistance_other ? 'Specific' : 'Off')),
                'ta.specific matter' => $form->assistance_other_details ?? '',

                // Authorized recipient
                'ar.also' => $form->is_authorized_recipient ? 'Yes' : 'No',
                'mg.ending ar' => $form->withdraw_authorized_recipient ? 'Yes' : 'No',

                // Declarations
                'mg.dec 1' => $form->agent_declared ? 'on' : 'Off', // Appointment declaration
                'mg.dec 2' => $form->is_authorized_recipient && $form->agent_declared ? 'on' : 'Off', // Authorized recipient declaration
                'cc.dec 1' => $form->client_declared ? 'on' : 'Off', // Client appointment declaration
                'cc.dec 2' => $form->is_authorized_recipient && $form->client_declared ? 'on' : 'Off', // Client authorized recipient declaration
                'mg.dec date' => $form->agent_declaration_date ? $agentDeclarationDateFormated : '',
                'cc.dec date' => $form->client_declaration_date ? $clientDeclarationDateFormated : '',
            ];

            //dd($formData);

            // Handle ending appointment declarations if form_type is withdrawal
            if ($form->form_type === 'withdrawal') {
                $formData['mg.dec 3'] = $form->agent_declared ? 'on' : 'Off'; // Ending appointment
                $formData['mg.dec 4'] = $form->withdraw_authorized_recipient && $form->agent_declared ? 'on' : 'Off'; // Withdrawal of authorized recipient
                $formData['cc.dec 3'] = $form->client_declared ? 'on' : 'Off'; // Client ending appointment
                $formData['cc.dec 4'] = $form->withdraw_authorized_recipient && $form->client_declared ? 'on' : 'Off'; // Client withdrawal of authorized recipient
            }
            //dd($formData);
            $pdf->fillForm($formData)->needAppearances();

            return response()->stream(
                fn () => $pdf->saveAs('php://output'),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="form956_preview.pdf"'
                ]
            );
        } catch (\Exception $e) {
            return back()->with('error', 'Error generating PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified form.
     */
    public function edit(Form956 $form): View
    {
        $this->assertCanAccessForm956($form);
        $form->load(['client', 'agent']);
        $clients = Admin::whereIn('type', ['client', 'lead'])->orderBy('last_name')->get();

        return view('crm.forms.edit', compact('form', 'clients'));
    }

    /**
     * Update the specified form in storage.
     */
    public function update(StoreForm956Request $request, Form956 $form): RedirectResponse
    {
        $this->assertCanAccessForm956($form);
        $validated = $request->validated();
        // If reassignment is attempted, require access to the target client as well.
        $newClientId = (int) ($validated['client_id'] ?? 0);
        if ($newClientId > 0 && $newClientId !== (int) $form->client_id) {
            $this->assertCanAccessClientId($newClientId);
        }
        $form->update($validated);

        return redirect()->route('forms.show', $form)
            ->with('success', 'Form 956 updated successfully.');
    }

    /**
     * Remove the specified form from storage.
     */
    public function destroy(Form956 $form): RedirectResponse
    {
        $this->assertCanAccessForm956($form);
        $form->delete();

        return redirect()->route('forms.index')
            ->with('success', 'Form 956 deleted successfully.');
    }
}
