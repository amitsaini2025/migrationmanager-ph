<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientQualification;
use App\Models\ClientExperience;
use App\Models\ClientTestScore;
use App\Models\ClientVisaCountry;
use App\Models\ClientOccupation;
use App\Models\ClientSpouseDetail;
use App\Models\ClientPoint;
use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientRelationship;
use App\Models\ClientEoiReference;
use App\Models\ClientMatter;
use App\Models\Company;
use App\Models\CompanyDirector;
use App\Models\CompanyNomination;
use App\Models\ActivitiesLog;
use App\Models\Lead;
use App\Helpers\PhoneHelper;
use App\Services\LeadFollowUpNoteService;
use App\Traits\LogsClientActivity;

/**
 * ClientPersonalDetailsController
 * 
 * Handles personal information, family details, qualifications, occupations,
 * test scores, and points calculation for clients.
 * 
 * Maps to: resources/views/Admin/clients/tabs/personal_details.blade.php
 */
class ClientPersonalDetailsController extends Controller
{
    use EnsuresCrmRecordAccess;
    use LogsClientActivity;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function getVisaTypes()
    {
        $visaTypes = \App\Models\Matter::select('id', 'title', 'nick_name')
            ->where('title', 'not like', '%skill assessment%')
            ->where('status', 1)
            ->orderBy('title', 'ASC')
            ->get();

        return response()->json($visaTypes);
    }

    public function getCountries()
    {
        $countries = \App\Models\Country::all()->pluck('name')->toArray();

        // Ensure "India" and "Australia" are at the top of the list
        $priorityCountries = ['Australia','India'];
        $otherCountries = array_diff($countries, $priorityCountries);
        $sortedCountries = array_merge($priorityCountries, $otherCountries);

        return response()->json($sortedCountries);
    }

      //Fetch all contact list of any client at create note popup
      public function fetchClientContactNo(Request $request){ //dd($request->all());
        $this->ensureCrmRecordAccessFromRequest($request, ['client_id']);
        if( ClientContact::where('client_id', $request->client_id)->exists()){
            //Fetch All client contacts
            $clientContacts = ClientContact::select('phone', 'country_code', 'contact_type')->where('client_id', $request->client_id)->get();
            //dd($clientContacts);
            if( !empty($clientContacts) && count($clientContacts)>0 ){
                $response['status'] 	= 	true;
                $response['message']	=	'Client contact is successfully fetched.';
                $response['clientContacts']	=	$clientContacts;
            } else {
                $response['status'] 	= 	false;
                $response['message']	=	'Please try again';
                $response['clientContacts']	=	array();
            }
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
            $response['clientContacts']	=	array();
        }
        echo json_encode($response);
	}

    public function updateAddress(Request $request)
    {
        $postcode = $request->input('postcode');
        // Fetch data based on the postcode
        // Replace this with your actual API call to get address details
        $apiKey = 'acb06506-edb3-4965-856e-db81ade1b45b';
        $urlPrefix = 'digitalapi.auspost.com.au';
        $url = 'https://' . $urlPrefix . '/postcode/search.json?q=' . $postcode;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['AUTH-KEY: ' . $apiKey]);
        $response = curl_exec($ch);  //dd($response);
        // curl handles are closed automatically in recent PHP versions.
        if (!$response) {
            return response()->json(['localities' => []]);
        }
        $data = json_decode($response, true); //dd($data);
        return response()->json($data);
    }

    // Method 1: Search address using Google Places with fallback
    public function searchAddressFull(Request $request)
    {
        $query = $request->input('query');
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        
        // Try Google Places API first
        if ($apiKey) {
            $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';
            
            // Determine the best search strategy based on the query
            $searchParams = $this->getOptimalSearchParams($query);
            
            $params = http_build_query([
                'input' => $query,
                'key' => $apiKey,
                'types' => $searchParams['types'],
                'components' => 'country:au', // Restrict to Australia
                'fields' => 'place_id,description,structured_formatting'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased to 30 seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            // curl handles are closed automatically in recent PHP versions.
            
            // Log curl errors for debugging
            if ($curlError) {
                Log::error('Google Places Autocomplete API CURL Error: ' . $curlError);
            }
            
            $data = json_decode($response, true);
            
            // Check if Google API is working
            if ($httpCode === 200 && isset($data['status']) && $data['status'] !== 'REQUEST_DENIED') {
                // Post-process the results to ensure house numbers are preserved
                $data = $this->postProcessAddressResults($data, $query);
                return response()->json($data);
            }
        }
        
        // Fallback: Use free geocoding service for basic suggestions
        return $this->getFallbackAddressSuggestions($query);
    }
    
    /**
     * Get optimal search parameters based on query content
     */
    private function getOptimalSearchParams($query)
    {
        // Check if query contains a house number (starts with digits)
        if (preg_match('/^\d+[A-Za-z]?\s+/', $query)) {
            // Query has house number with street name, use geocode for better results
            return [
                'types' => 'geocode'
            ];
        } elseif (preg_match('/^\d+[A-Za-z]?$/', $query)) {
            // Only house number is typed, use establishment type to get more relevant results
            return [
                'types' => 'establishment'
            ];
        } else {
            // No house number, use address type
            return [
                'types' => 'address'
            ];
        }
    }
    
    /**
     * Post-process address results to ensure house numbers are preserved and match the query
     */
    private function postProcessAddressResults($data, $originalQuery)
    {
        if (!isset($data['predictions']) || !is_array($data['predictions'])) {
            return $data;
        }
        
        $filteredPredictions = [];
        $queryLower = strtolower(trim($originalQuery));
        
        foreach ($data['predictions'] as $prediction) {
            $description = $prediction['description'];
            $descriptionLower = strtolower($description);
            
            // Check if the prediction starts with or contains the query text
            if (strpos($descriptionLower, $queryLower) === 0 || 
                strpos($descriptionLower, $queryLower) !== false) {
                
                // Extract house number from original query if present
                preg_match('/^(\d+[A-Za-z]?)\s*(.*)/', $originalQuery, $matches);
                if (count($matches) >= 2) {
                    $houseNumber = $matches[1];
                    
                    // If the description doesn't start with the house number, prepend it
                    if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                        $prediction['description'] = $houseNumber . ' ' . $description;
                    }
                }
                
                $filteredPredictions[] = $prediction;
            }
        }
        
        // If no matching results, try a more flexible approach
        if (empty($filteredPredictions)) {
            foreach ($data['predictions'] as $prediction) {
                $description = $prediction['description'];
                
                // Extract house number from original query if present
                preg_match('/^(\d+[A-Za-z]?)\s*(.*)/', $originalQuery, $matches);
                if (count($matches) >= 2) {
                    $houseNumber = $matches[1];
                    $streetName = trim($matches[2]);
                    
                    // If we have a street name, check if the description contains it
                    if (!empty($streetName) && stripos($description, $streetName) !== false) {
                        // Ensure the description starts with the house number
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    } elseif (empty($streetName)) {
                        // If only house number is typed, prepend it to any address
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    }
                }
            }
        }
        
        // Limit to 5 results and use filtered results if available
        if (!empty($filteredPredictions)) {
            $data['predictions'] = array_slice($filteredPredictions, 0, 5);
        }
        
        return $data;
    }
    
    /**
     * Fallback address suggestions using free service
     */
    private function getFallbackAddressSuggestions($query)
    {
        try {
            // Use OpenStreetMap Nominatim API (free)
            $url = 'https://nominatim.openstreetmap.org/search';
            $params = http_build_query([
                'q' => $query . ', Australia',
                'format' => 'json',
                'limit' => 5,
                'addressdetails' => 1,
                'countrycodes' => 'au'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Migration Manager CRM');
            $response = curl_exec($ch);
            // curl handles are closed automatically in recent PHP versions.
            
            $results = json_decode($response, true);
            
            // Convert to Google Places API format for compatibility
            $predictions = [];
            if (is_array($results)) {
                foreach ($results as $result) {
                    // Format the display name to be more consistent with Google Places format
                    $formattedDescription = $this->formatFallbackAddress($result, $query);
                    
                    $predictions[] = [
                        'place_id' => 'fallback_' . md5($result['display_name']),
                        'description' => $formattedDescription,
                        'formatted_address' => $formattedDescription
                    ];
                }
            }
            
            // Post-process fallback results to ensure house numbers are preserved
            $predictions = $this->postProcessFallbackResults($predictions, $query);
            
            return response()->json([
                'status' => 'OK',
                'predictions' => $predictions
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'error_message' => 'Address service temporarily unavailable. Please enter address manually.',
                'predictions' => []
            ]);
        }
    }
    
    /**
     * Format fallback address to match Google Places format
     */
    private function formatFallbackAddress($result, $originalQuery)
    {
        $address = $result['address'] ?? [];
        $displayName = $result['display_name'] ?? '';
        
        // Extract house number from original query if present
        preg_match('/^(\d+[A-Za-z]?)\s+(.+)/', $originalQuery, $matches);
        
        if (count($matches) >= 3) {
            $houseNumber = $matches[1];
            $streetName = trim($matches[2], ', ');
            
            // Build a cleaner address format
            $parts = [];
            
            // Add house number and street
            $parts[] = $houseNumber . ' ' . $streetName;
            
            // Add suburb if available
            if (isset($address['suburb'])) {
                $parts[] = $address['suburb'];
            } elseif (isset($address['village'])) {
                $parts[] = $address['village'];
            }
            
            // Add state abbreviation
            if (isset($address['state'])) {
                $state = $address['state'];
                // Convert full state names to abbreviations
                $stateAbbr = $this->getStateAbbreviation($state);
                $parts[] = $stateAbbr;
            }
            
            // Add country
            $parts[] = 'Australia';
            
            return implode(', ', $parts);
        }
        
        return $displayName;
    }
    
    /**
     * Get state abbreviation from full state name
     */
    private function getStateAbbreviation($state)
    {
        $stateMap = [
            'New South Wales' => 'NSW',
            'Victoria' => 'VIC',
            'Queensland' => 'QLD',
            'South Australia' => 'SA',
            'Western Australia' => 'WA',
            'Tasmania' => 'TAS',
            'Northern Territory' => 'NT',
            'Australian Capital Territory' => 'ACT'
        ];
        
        return $stateMap[$state] ?? $state;
    }
    
    /**
     * Post-process fallback results to ensure house numbers are preserved and match the query
     */
    private function postProcessFallbackResults($predictions, $originalQuery)
    {
        $filteredPredictions = [];
        $queryLower = strtolower(trim($originalQuery));
        
        // Extract house number from original query if present
        preg_match('/^(\d+[A-Za-z]?)\s*(.*)/', $originalQuery, $matches);
        if (count($matches) >= 2) {
            $houseNumber = $matches[1];
            $streetName = trim($matches[2]);
            
            foreach ($predictions as $prediction) {
                $description = $prediction['description'];
                $descriptionLower = strtolower($description);
                
                // Check if the prediction starts with or contains the query text
                if (strpos($descriptionLower, $queryLower) === 0 || 
                    strpos($descriptionLower, $queryLower) !== false) {
                    
                    // Ensure the prediction starts with the house number
                    if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                        $prediction['description'] = $houseNumber . ' ' . $description;
                    }
                    
                    $filteredPredictions[] = $prediction;
                }
            }
            
            // If no matching results, try to prepend house number to relevant addresses
            if (empty($filteredPredictions)) {
                foreach ($predictions as $prediction) {
                    $description = $prediction['description'];
                    
                    if (!empty($streetName) && stripos($description, $streetName) !== false) {
                        // Ensure the description starts with the house number
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    } elseif (empty($streetName)) {
                        // If only house number is typed, prepend it to any address
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    }
                }
            }
            
            return !empty($filteredPredictions) ? array_slice($filteredPredictions, 0, 5) : $predictions;
        }
        
        return $predictions;
    }

    // Method 2: Get place details with fallback
    public function getPlaceDetails(Request $request)
    {
        $placeId = $request->input('place_id');
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        
        // Handle fallback place IDs
        if (strpos($placeId, 'fallback_') === 0) {
            return $this->getFallbackPlaceDetails($request);
        }
        
        // Try Google Places API
        if ($apiKey) {
            $url = 'https://maps.googleapis.com/maps/api/place/details/json';
            
            // Request all address fields including postal_code
            $params = http_build_query([
                'place_id' => $placeId,
                'key' => $apiKey,
                'fields' => 'address_components,formatted_address,name'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased to 30 seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            // curl handles are closed automatically in recent PHP versions.
            
            // Log curl errors for debugging
            if ($curlError) {
                Log::error('Google Places Details API CURL Error: ' . $curlError);
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode === 200 && isset($data['status']) && $data['status'] !== 'REQUEST_DENIED') {
                return response()->json($data);
            }
        }
        
        // Fallback: Return basic structure for manual entry
        return response()->json([
            'status' => 'OK',
            'result' => [
                'formatted_address' => $request->input('description', ''),
                'address_components' => []
            ]
        ]);
    }
    
    /**
     * Handle fallback place details
     */
    private function getFallbackPlaceDetails($request)
    {
        $description = $request->input('description', '');
        
        // Try to extract basic address components from the description
        $parts = explode(', ', $description);
        $addressComponents = [];
        
        if (count($parts) >= 3) {
            // More intelligent parsing for Australian addresses
            $addressComponents[] = [
                'long_name' => $parts[0],
                'short_name' => $parts[0],
                'types' => ['establishment', 'point_of_interest'] // Mark as establishment for airports/POIs
            ];
            
            // Find suburb (usually one of the middle parts that's not a number)
            $suburb = '';
            for ($i = 1; $i < count($parts) - 2; $i++) {
                if (!is_numeric($parts[$i]) && !in_array($parts[$i], ['NSW', 'VIC', 'QLD', 'SA', 'WA', 'TAS', 'NT', 'ACT'])) {
                    $suburb = $parts[$i];
                    break;
                }
            }
            
            if ($suburb) {
                $addressComponents[] = [
                    'long_name' => $suburb,
                    'short_name' => $suburb,
                    'types' => ['locality']
                ];
            }
            
            // Find state
            $state = '';
            foreach ($parts as $part) {
                if (in_array($part, ['NSW', 'VIC', 'QLD', 'SA', 'WA', 'TAS', 'NT', 'ACT'])) {
                    $state = $part;
                    break;
                }
            }
            
            if ($state) {
                $addressComponents[] = [
                    'long_name' => $state,
                    'short_name' => $state,
                    'types' => ['administrative_area_level_1']
                ];
            }
            
            // Enhanced: Find postcode (4-digit number anywhere in description)
            $postcode = '';
            // First, search through all parts for a 4-digit number
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/\b(\d{4})\b/', $part, $matches)) {
                    $postcode = $matches[1];
                    break;
                }
            }
            
            // If not found in parts, search the entire description
            if (!$postcode && preg_match('/\b(\d{4})\b/', $description, $matches)) {
                $postcode = $matches[1];
            }
            
            if ($postcode) {
                $addressComponents[] = [
                    'long_name' => $postcode,
                    'short_name' => $postcode,
                    'types' => ['postal_code']
                ];
            }
            
            $addressComponents[] = [
                'long_name' => 'Australia',
                'short_name' => 'AU',
                'types' => ['country']
            ];
        }
        
        return response()->json([
            'status' => 'OK',
            'result' => [
                'formatted_address' => $description,
                'address_components' => $addressComponents
            ]
        ]);
    }

    // Method 3: Helper to combine address
    private function combineAddress($parts)
    {
        $addressParts = array_filter([
            $parts['line1'] ?? null,
            $parts['line2'] ?? null,
            $parts['suburb'] ?? null,
            $parts['state'] ?? null,
            $parts['postcode'] ?? null,
            (($parts['country'] ?? 'Australia') !== 'Australia' ? $parts['country'] : null)
        ]);
        
        return implode(', ', $addressParts);
    }

    public function updateOccupation(Request $request)
    {
        $occupation = $request->input('occupation');

        // Example: Replace this with actual search logic based on your database schema
        $occupations = DB::table('client_occupation_lists')
            ->where('occupation', 'like', "%{$occupation}%")
            ->get(['occupation', 'occupation_code', 'list', 'visa_subclass','access_authority']);

        return response()->json(['occupations' => $occupations]);
    }

    public function saveRelationship(Request $request)
    {
        $clientId = Auth::id(); // Assuming the logged-in user is the client

        // Loop through the relationship data to insert each relationship
        foreach ($request->relationship_type as $index => $relationshipType) {
            ClientRelationship::create([
                'client_id' => $clientId,
                'relationship_type' => $relationshipType,
                'name' => $request->name[$index],
                'phone_number' => $request->phone_number[$index],
                'email_address' => $request->email_address[$index],
                'crm_reference' => $request->crm_reference[$index] ?? null,
            ]);
        }

        return response()->json(['success' => 'Relationship data saved successfully!']);
    }

    //Seach Client Relationship
    public function searchPartner(Request $request)
    {
        try {
            // Validate the incoming query
            $request->validate([
                'query' => 'required|string|min:2|max:255',
            ]);

            $query = $request->input('query');
            $excludeClient = $request->input('exclude_client');
            $queryStr = strtolower($query);

            // Only return records the caller may actually access (respects allocation + grants)
            $allClients = Admin::whereIn('type', ['client', 'lead'])
                ->select('id', 'email', 'first_name', 'last_name', 'phone', 'client_id')
                ->where(function ($q) use ($queryStr) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $queryStr . '%'])
                      ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $queryStr . '%'])
                      ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $queryStr . '%'])
                      ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . $queryStr . '%'])
                      ->orWhereRaw('LOWER(client_id) LIKE ?', ['%' . $queryStr . '%']);
                });

            \App\Support\StaffClientVisibility::restrictAdminEloquentQuery($allClients);

            $allClients = $allClients->get();

            // Filter only the exclude-client rule in PHP (search is done in SQL now)
            $filteredClients = $allClients->filter(function($client) use ($excludeClient) {
                if ($excludeClient && $client->id == $excludeClient) {
                    return false;
                }

                return true;
            });

            // Convert to array and limit results
            $partners = $filteredClients->take(20)->values()->toArray();

            return response()->json([
                'partners' => $partners,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'partners' => [],
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    // Test method to debug search functionality
    public function searchPartnerTest(Request $request)
    {
        $query = $request->input('query', 'vip');
        
        // Get total clients count
        $totalClients = Admin::whereIn('type', ['client', 'lead'])->count();
        
        // Get sample clients
        $sampleClients = Admin::whereIn('type', ['client', 'lead'])
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'client_id')
            ->limit(5)
            ->get();
        
        // Test search
        $searchResults = Admin::whereIn('type', ['client', 'lead'])
            ->where(function ($q) use ($query) {
                $queryLower = strtolower($query);
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $queryLower . '%'])
                  ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $queryLower . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $queryLower . '%']);
            })
            ->select('id', 'first_name', 'last_name', 'email', 'phone', 'client_id')
            ->get();
        
        return response()->json([
            'total_clients' => $totalClients,
            'sample_clients' => $sampleClients->toArray(),
            'search_query' => $query,
            'search_results' => $searchResults->toArray(),
            'search_count' => $searchResults->count()
        ]);
    }

    public function fetchClientMatterAssignee(Request $request)
    {
        $requestData = $request->all();
        if (!empty($requestData['client_matter_id'])) {
            $clientId = DB::table('client_matters')->where('id', $requestData['client_matter_id'])->value('client_id');
            if ($clientId) {
                $this->ensureCrmRecordAccess((int) $clientId);
            }
        }
        $matter_info = DB::table('client_matters')->where('id',$requestData['client_matter_id'])->first();
        //dd($matter_info);
        if(!empty($matter_info)) {
            $response['matter_info'] = $matter_info;
            $response['status'] 	= 	true;
            $response['message']	=	'Record is exist';
        }else{
            $response['matter_info'] 	= array();
            $response['status'] 	= 	false;
            $response['message']	=	'Record is not exist.Please try again';
        }
        echo json_encode($response);
    }

    public function updateClientMatterAssignee(Request $request){
        $response = ['status' => false, 'message' => 'Invalid request. Please try again.'];
        $requstData = $request->all();

        if (empty($requstData['client_id'])) {
            $response['message'] = 'Invalid request. Please try again.';
            return response()->json($response);
        }

        $this->ensureCrmRecordAccess((int) $requstData['client_id']);

        if (empty($requstData['selectedMatterLM'])) {
            $response['message'] = 'Please select a matter first.';
            return response()->json($response);
        }

        $obj = ClientMatter::find($requstData['selectedMatterLM']);
        if (!$obj || (int) $obj->client_id !== (int) $requstData['client_id']) {
            $response['message'] = 'Matter not found. Please try again.';
            return response()->json($response);
        }

        $obj->sel_migration_agent = $requstData['migration_agent'];
        $obj->sel_person_responsible = $requstData['person_responsible'];
        $obj->sel_person_assisting = $requstData['person_assisting'];
        $obj->user_id = $requstData['user_id'];

        if (isset($requstData['office_id']) && $requstData['office_id'] !== '') {
            $obj->office_id = $requstData['office_id'];
        }

        $saved = $obj->save();
        if ($saved) {
            $objs = new \App\Models\ActivitiesLog;
            $objs->client_id = (int) $obj->client_id;
            $objs->created_by = Auth::user()->id;
            $objs->description = '';
            $objs->subject = 'updated client matter assignee';
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();

            $agent = \App\Models\Staff::select('first_name', 'last_name')->find($obj->sel_migration_agent);
            $personResponsible = \App\Models\Staff::select('first_name', 'last_name')->find($obj->sel_person_responsible);
            $personAssisting = \App\Models\Staff::select('first_name', 'last_name')->find($obj->sel_person_assisting);
            $office = !empty($obj->office_id)
                ? \App\Models\Branch::select('office_name')->find($obj->office_id)
                : null;

            $response['status'] = true;
            $response['message'] = 'Matter assignee updated successfully.';
            $response['assignees'] = [
                'migration_agent_name' => $agent
                    ? trim($agent->first_name . ' ' . $agent->last_name)
                    : 'N/A',
                'person_responsible_name' => $personResponsible
                    ? trim($personResponsible->first_name . ' ' . $personResponsible->last_name)
                    : 'N/A',
                'person_assisting_name' => $personAssisting
                    ? trim($personAssisting->first_name . ' ' . $personAssisting->last_name)
                    : 'N/A',
                'office_name' => $office ? $office->office_name : 'No Office Assigned',
            ];
        } else {
            $response['message'] = 'Record could not be updated. Please try again.';
        }

        return response()->json($response);
    }
    
    /**
     * Decode string - helper method for decoding encoded client IDs
     */
    public function decodeString($string = null)
    {
        try {
            return convert_uudecode(base64_decode($string));
        } catch (\Exception $e) {
            return $string; // Return original if decoding fails
        }
    }

    /**
     * Methods to be moved from ClientsController:
     * 
     * - clientdetailsinfo() - Get client details
     * - getVisaTypes() - Get list of visa types
     * - getCountries() - Get list of countries
     * - saveRelationship() - Save family relationships
     * - updateAddress() - Update client address
     * - updateOccupation() - Update occupation details
     * - fetchClientContactNo() - Fetch client contact numbers
     * - fetchClientMatterAssignee() - Fetch matter assignee
     * - updateClientMatterAssignee() - Update matter assignee
     */

    public function clientdetailsinfo(Request $request, $id = NULL)
{
    //check authorization end
    if ($request->isMethod('post'))
    {
        $requestData = $request->all(); //dd($requestData);
        $this->validate($request, [
            'first_name' => 'required|max:255',
            'email' => 'required|max:255|unique:admins,email,'.$requestData['id'],
            'phone' => 'required|max:255|unique:admins,phone,'.$requestData['id'],
            'client_id' => 'required|max:255|unique:admins,client_id,'.$requestData['id']
        ]);

        if (!empty($requestData['id'])) {
            $this->ensureCrmRecordAccess((int) $requestData['id']);
        }

        $related_files = '';
        if(isset($requestData['related_files'])){
            for($i=0; $i<count($requestData['related_files']); $i++){
                $related_files .= $requestData['related_files'][$i].',';
            }
        }

        $dob = '';
        if(array_key_exists("dob",$requestData) && $requestData['dob'] != ''){
           $dobs = explode('/', $requestData['dob']);
           $dob = $dobs[2].'-'.$dobs[1].'-'. $dobs[0];
        }

        $visaExpiry = '';
        if(array_key_exists("visaExpiry",$requestData) && $requestData['visaExpiry'] != '' ){
           $visaExpirys = explode('/', $requestData['visaExpiry']);
            $visaExpiry = $visaExpirys[2].'-'.$visaExpirys[1].'-'. $visaExpirys[0];
        }
        $obj = 	Admin::find(@$requestData['id']);
        $first_name = substr(@$requestData['first_name'], 0, 4);

        $obj->first_name	=	@$requestData['first_name'];
        $obj->last_name	=	@$requestData['last_name'];
        $obj->dob	=	@$dob;
        $obj->age	=	@$requestData['age'];
        $obj->gender	=	@$requestData['gender'];
        $obj->marital_status	=	@$requestData['marital_status'];

        $naatiTest = isset($requestData['naati_test']) && $requestData['naati_test'] === '1' ? 1 : 0;
        $obj->naati_test = $naatiTest;
        $obj->naati_date = $naatiTest ? ($requestData['naati_date'] ?? null) : null;

        $pyTest = isset($requestData['py_test']) && $requestData['py_test'] === '1' ? 1 : 0;
        $obj->py_test = $pyTest;
        $obj->py_date = $pyTest ? ($requestData['py_date'] ?? null) : null;
        $obj->related_files	=	rtrim($related_files,',');
        
        // Handle tags/tagname
        if (isset($requestData['tagname']) && is_array($requestData['tagname'])) {
            // Filter out empty values and convert to comma-separated string
            $tagIds = array_filter($requestData['tagname'], function($value) {
                return !empty($value);
            });
            $obj->tagname = !empty($tagIds) ? implode(',', $tagIds) : null;
        } else {
            // If no tags are selected, set to null
            $obj->tagname = null;
        }
        
        $obj->save(); //Finally, save the object

        //Contact Type Start Code
        if(
            ( isset($requestData['contact_type_hidden']) && is_array($requestData['contact_type_hidden']) )
            &&
            ( isset($requestData['phone']) && is_array($requestData['phone']) )
        )
        {
            // Get the count of the email array
            $count_contact = count($requestData['contact_type_hidden']);
            // Save the last values for email_type_hidden and email to the Admin object
            if ($count_contact > 0 ) {
                // Get the last values for contact_type and phone
                $lastContactType = end($requestData['contact_type_hidden']);
                $lastPhone = end($requestData['phone']);
                $lastcountry_code =  end($requestData['country_code']);

                if($lastPhone != ""){
                    $lastPhone = $lastPhone;
                    $lastContactType = $lastContactType;
                    $lastcountry_code = $lastcountry_code;
                } else {
                    if($count_contact >1){
                        $lastPhone = $requestData['phone'][$count_contact-2];
                        $lastContactType = $requestData['contact_type_hidden'][$count_contact-2];
                        $lastcountry_code = $requestData['country_code'][$count_contact-2];
                    } else {
                        $lastPhone = $requestData['phone'][0];
                        $lastContactType = $requestData['contact_type_hidden'][0];
                        $lastcountry_code = $requestData['country_code'][0];
                    }
                }
                $obj->contact_type = $lastContactType;
                $obj->phone = $lastPhone;
                $obj->country_code = $lastcountry_code;
                $obj->save(); // Save the admin object with the last phone number
            }

            // Loop through each contact in the request
            foreach ($requestData['contact_type_hidden'] as $key => $contactType) {
                $contactId = $requestData['contact_id'][$key] ?? null;
                $phone = $requestData['phone'][$key] ?? null;
                $country_code = $requestData['country_code'][$key] ?? null;
                // Check if both contact_type and phone are not empty
                if (!empty($contactType) && !empty($phone)) {
                    if ($contactId) {
                        // Update existing contact if ID is provided
                        $existingContact = ClientContact::find($contactId);
                        //if ($existingContact && $existingContact->admin_id == Auth::user()->id) {
                        if ($existingContact) {
                            $existingContact->update([
                                'admin_id' => Auth::user()->id,
                                'contact_type' => $contactType,
                                'phone' => $phone,
                                'country_code' => $country_code
                            ]);
                        }
                    } else {
                        // Insert new contact if no ID is provided
                        ClientContact::create([
                            'admin_id' => Auth::user()->id, // Assigning Auth user ID to admin_id
                            'client_id' => $obj->id,
                            'contact_type' => $contactType,
                            'phone' => $phone,
                            'country_code' => $country_code,
                            'is_verified' => false
                        ]);
                    }
                }
            }
        }
        //Contact Type End Code

        //Email Type Start Code
        if (
            ( isset($requestData['email_type_hidden']) && is_array($requestData['email_type_hidden']) )
            &&
            ( isset($requestData['email']) && is_array($requestData['email']) )
        )
        {
            // Get the count of the email array
            $count_email_type = count($requestData['email_type_hidden']);
            // Save the last values for email_type_hidden and email to the Admin object
            if ($count_email_type > 0 ) {
                $lastEmailType = end($requestData['email_type_hidden']);
                $lastEmail = end($requestData['email']);
                if($lastEmail != ""){
                    $lastEmail = $lastEmail;
                    $lastEmailType = $lastEmailType;
                } else {
                    if($count_email_type >1){
                        $lastEmail = $requestData['email'][$count_email_type-2];
                        $lastEmailType = $requestData['email_type_hidden'][$count_email_type-2];
                    } else {
                        $lastEmail = $requestData['email'][0];
                        $lastEmailType = $requestData['email_type_hidden'][0];
                    }
                }
                $obj->email_type = $lastEmailType;
                $obj->email = $lastEmail;
                $obj->save();
            }

            // Loop through each email in the request
            foreach ($requestData['email_type_hidden'] as $key => $emailType) {
                $email = $requestData['email'][$key] ?? null;
                $emailId = $requestData['email_id'][$key] ?? null;

                // Check if the current row is not blank
                if (!empty($emailType) && !empty($email)) {

                    // Check if the email already exists in the current client's email list
                    $duplicateEmail = ClientEmail::where('email', $email)
                    ->where('client_id', $obj->id)
                    ->where('id', '!=', $emailId)
                    ->first();

                    if ($duplicateEmail) {
                        // If duplicate found, add error message to the session
                        return response()->json([
                            'status' => 'error',
                            'message' => 'This email is already taken: ' . $email
                        ], 422); // Unprocessable Entity
                    }

                    if ($emailId) {
                        // Update existing email if ID is provided
                        $existingEmail = ClientEmail::find($emailId);
                        if ($existingEmail && $existingEmail->client_id == $obj->id) {
                            $existingEmail->update([
                                'email_type' => $emailType,
                                'email' => $email,
                                'admin_id' => Auth::user()->id
                            ]);
                        }
                    } else {
                        // Insert new email if no ID is provided
                        ClientEmail::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $obj->id, // Assigning the correct client ID
                            'email_type' => $emailType,
                            'email' => $email,
                            'is_verified' => false
                        ]);
                    }
                }
            }
        }
        //Email Type End Code

        //Visa Country Start Code
        if (
            ( isset($requestData['visa_country']) && is_array($requestData['visa_country']) )
            ||
            ( isset($requestData['visa_type_hidden']) && is_array($requestData['visa_type_hidden']) )
        )
        {
            if( isset($requestData['visa_country']) &&  $requestData['visa_country'][0] == 'Australia')
            {

                if (ClientVisaCountry::where('client_id', $obj->id)->exists()) {
                    if ( ClientVisaCountry::where('client_id', $obj->id)->delete() ) {
                        ClientVisaCountry::create([
                            'admin_id' => Auth::user()->id, // Assigning Auth user ID to admin_id
                            'client_id' => $obj->id,
                            'visa_type' => "",
                            'visa_expiry_date' => "",
                            'visa_description' => ""
                        ]);

                        $obj->visa_type = "";
                        $obj->country_passport = $requestData['visa_country'][0];
                        $obj->visaExpiry = "";
                        $obj->save();
                    }
                }
            }
            else
            {
                //If Visa Country is not Australia
                if (ClientVisaCountry::where('client_id', $obj->id)->exists()) {
                    if ( ClientVisaCountry::where('client_id', $obj->id)->delete() ) {

                        foreach ($requestData['visa_type_hidden'] as $key => $visaType) {
                            $visa_country = $requestData['visa_country'][0] ?? null;
                            $visa_expiry_date = $requestData['visa_expiry_date'][$key] ?? null;
                            $visa_description = $requestData['visa_description'][$key] ?? null;
                            $visaId = $requestData['visa_id'][$key] ?? null;
                            // Check if the current row is not blank
                            if (!empty($visaType) || !empty($visa_country)) {
                                ClientVisaCountry::create([
                                    'admin_id' => Auth::user()->id, // Assigning Auth user ID to admin_id
                                    'client_id' => $obj->id,
                                    'visa_type' => $visaType,
                                    'visa_expiry_date' => $visa_expiry_date,
                                    'visa_description' => $visa_description
                                ]);
                            }
                        }
                        $count_visa = count($requestData['visa_type_hidden']);
                        // Save the last values for visa_type, visa_country, and visa_expiry_date to the Admin object
                        if ($count_visa > 0 ) {
                            $lastVisaCountry = $requestData['visa_country'][0];
                            $lastVisaType = end($requestData['visa_type_hidden']);
                            $lastVisaExpiryDate = end($requestData['visa_expiry_date']);
                            // Check if the last visa details are not empty before assigning
                            if (!empty($lastVisaType)  &&  !empty($lastVisaCountry)) {
                                $obj->visa_type = $lastVisaType;
                                $obj->country_passport = $lastVisaCountry;
                                $obj->visaExpiry = $lastVisaExpiryDate;
                                $obj->save();
                            }
                        }
                    }
                }
            }
        }

        //Address Start Code
        if (
            ( isset($requestData['zip']) && is_array($requestData['zip']) )
            ||
            ( isset($requestData['address']) && is_array($requestData['address']) )
        )
        {
            // Get the count of the address array
            $count = count($requestData['zip']);
            // Save the last values for address, city, state, and zip code to the Admin object
            if ($count > 0 ) {
                $secondLastAddress = $requestData['address'][$count - 1];
                $secondLastZip = $requestData['zip'][$count - 1];

                // Check if the last address details are not empty before assigning
                if (!empty($secondLastAddress)  || !empty($secondLastZip)) {
                    $obj->address = $secondLastAddress;
                    $obj->zip = $secondLastZip;
                    $obj->save();
                }
            }

            // Loop through each address in the request
            foreach ($requestData['address'] as $key => $addr) {
                $zip = $requestData['zip'][$key] ?? null;
                $addressId = $requestData['address_id'][$key] ?? null;
                $regional_code = $requestData['regional_code'][$key] ?? null;

                // Check if the current row is not blank
                if (!empty($addr) || !empty($zip)) {
                    if ($addressId) {
                        // Update existing address if ID is provided
                        $existingAddress = ClientAddress::find($addressId);
                        if ($existingAddress && $existingAddress->client_id == $obj->id) {
                            $existingAddress->update([
                                'admin_id' => Auth::user()->id,
                                'address' => $addr,
                                'zip' => $zip,
                                'regional_code' => $regional_code
                            ]);
                        }
                    } else {
                        // Insert new address if no ID is provided
                        ClientAddress::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $obj->id,
                            'address' => $addr,
                            'zip' => $zip,
                            'regional_code' => $regional_code
                        ]);
                    }
                }
            }
        }
        //Address End Code

        //Client Qualification Start Code
        if (
            ( isset($requestData['level_hidden']) && is_array($requestData['level_hidden']) )
            ||
            ( isset($requestData['name']) && is_array($requestData['name']) )
        )
        {
            // Get the count of qualification entries
            $qualificationCount = count($requestData['level_hidden']);

            // Ensure that there are at least two qualification entries to get the last one
            if ($qualificationCount > 0) {
                // Get the second last values for level and name
                $secondLastLevel = $requestData['level_hidden'][$qualificationCount - 1];
                $secondLastName = $requestData['name'][$qualificationCount - 1];

                // Save the second last qualification details to the Admin object if not empty
                if (!empty($secondLastLevel) || !empty($secondLastName)) {
                    $obj->qualification_level = $secondLastLevel;
                    $obj->qualification_name = $secondLastName;
                    $obj->save(); // Save the admin object with the second last qualification details
                }
            }

            // Loop through each qualification in the request
            foreach ($requestData['level_hidden'] as $key => $level)
            {
                $name = $requestData['name'][$key] ?? null;
                $country = $requestData['country_hidden'][$key] ?? null;
                $short = $requestData['start_date'][$key] ?? null;
                $finish = $requestData['finish_date'][$key] ?? null;
                $qualificationId = $requestData['qualification_id'][$key] ?? null;
                $relevant_qualification = $requestData['relevant_qualification_hidden'][$key] ?? null;

                // Check if the current row is not blank
                if (!empty($level) || !empty($name) ) {
                    if ($qualificationId) {
                        // Update existing qualification if ID is provided
                        $existingQualification = ClientQualification::find($qualificationId);
                        if ($existingQualification && $existingQualification->client_id == $obj->id) {
                            $existingQualification->update([
                                'admin_id' => Auth::user()->id,
                                'level' => $level,
                                'name' => $name,
                                'country' => $country,
                                'start_date' => $short,
                                'finish_date' => $finish,
                                'relevant_qualification' => $relevant_qualification
                            ]);
                        }
                    } else {
                        // Insert new qualification if no ID is provided
                        ClientQualification::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $obj->id, // Assigning the correct client ID
                            'level' => $level,
                            'name' => $name,
                            'country' => $country,
                            'start_date' => $short,
                            'finish_date' => $finish,
                            'relevant_qualification' => $relevant_qualification,
                        ]);
                    }
                }
            }
        }
        //Client Qualification End Code

        //Client Experience Start Code
        if (
            ( isset($requestData['job_title']) && is_array($requestData['job_title']) )
            ||
            ( isset($requestData['job_code']) && is_array($requestData['job_code']) )
        )
        {
            // Loop through each job in the request
            foreach ($requestData['job_title'] as $key => $jobTitle) {
                $jobCode = $requestData['job_code'][$key] ?? null;
                $jobCountry = $requestData['job_country_hidden'][$key] ?? null;
                $jobStartDate = $requestData['job_start_date'][$key] ?? null;
                $jobFinishDate = $requestData['job_finish_date'][$key] ?? null;
                $jobRelevantExp = $requestData['relevant_experience_hidden'][$key] ?? null;
                $jobId = $requestData['job_id'][$key] ?? null;

                // Check if the current row is not blank
                //if (!empty($jobTitle) && !empty($jobCode) && !empty($jobCountry)) {
                if (!empty($jobTitle) || !empty($jobCode) ) {
                    if ($jobId) {
                        // Update existing job if ID is provided
                        $existingJob = ClientExperience::find($jobId);
                        if ($existingJob && $existingJob->client_id == $obj->id) {
                            $existingJob->update([
                                'admin_id' => Auth::user()->id,
                                'job_title' => $jobTitle,
                                'job_code' => $jobCode,
                                'job_country' => $jobCountry,
                                'job_start_date' => $jobStartDate,
                                'job_finish_date' => $jobFinishDate,
                                'relevant_experience' =>$jobRelevantExp
                            ]);
                        }
                    } else {
                        // Insert new job if no duplicate exists
                        ClientExperience::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $obj->id, // Assigning the correct client ID
                            'job_title' => $jobTitle,
                            'job_code' => $jobCode,
                            'job_country' => $jobCountry,
                            'job_start_date' => $jobStartDate,
                            'job_finish_date' => $jobFinishDate,
                            'relevant_experience' =>$jobRelevantExp,
                            'fte_multiplier' => 1.00
                        ]);
                    }
                }
            }
        }
        //Client Experience End Code

        //Client Occupation Start Code
        if (
            ( isset($requestData['skill_assessment_hidden']) && is_array($requestData['skill_assessment_hidden']) )
            ||
            ( isset($requestData['nomi_occupation']) && is_array($requestData['nomi_occupation']) )
            )
        {

            // Loop through each set of data
            foreach ($requestData['skill_assessment_hidden'] as $key => $skillAssessment) {
                $nomiOccupation = $requestData['nomi_occupation'][$key] ?? null;
                $occupationCode = $requestData['occupation_code'][$key] ?? null;
                $list = $requestData['list'][$key] ?? null;
                $visaSubclass = $requestData['visa_subclass'][$key] ?? null;
                $date = $requestData['dates'][$key] ?? null;
                $occupationId = $requestData['occupation_id'][$key] ?? null; // Assuming you have IDs for updating
                $relevant_occupation = $requestData['relevant_occupation_hidden'][$key] ?? null;
                // Check if both skill_assessment and nomi_occupation are not empty
                if (!empty($skillAssessment) || !empty($nomiOccupation))
                {
                    if ($occupationId)
                    {
                        // Update existing record if ID is provided
                        $existingOccupation = ClientOccupation::find($occupationId);
                        if ($existingOccupation ) {
                            $existingOccupation->update([
                                'admin_id' => Auth::user()->id,
                                'skill_assessment' => $skillAssessment,
                                'nomi_occupation' => $nomiOccupation,
                                'occupation_code' => $occupationCode,
                                'list' => $list,
                                'visa_subclass' => $visaSubclass,
                                'dates' => $date,
                                'relevant_occupation' => $relevant_occupation
                            ]);
                        }
                    }
                    else
                    {
                        // Insert new record if no ID is provided
                        ClientOccupation::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $obj->id,
                            'skill_assessment' => $skillAssessment,
                            'nomi_occupation' => $nomiOccupation,
                            'occupation_code' => $occupationCode,
                            'list' => $list,
                            'visa_subclass' => $visaSubclass,
                            'dates' => $date,
                            'relevant_occupation' => $relevant_occupation
                        ]);
                    }
                }
            }
        }
        //Client Occupation End Code

        //Test Score Start Code
        if ( isset($requestData['test_type_hidden']) && is_array($requestData['test_type_hidden']) )
        {
            // Loop through each test score entry in the request
            foreach ($requestData['test_type_hidden'] as $key => $testType) {
                $listening = $requestData['listening'][$key] ?? null;
                $reading = $requestData['reading'][$key] ?? null;
                $writing = $requestData['writing'][$key] ?? null;
                $speaking = $requestData['speaking'][$key] ?? null;
                $overallScore = $requestData['overall_score'][$key] ?? null;
                $testDate = $requestData['test_date'][$key] ?? null;
                $testScoreId = $requestData['test_score_id'][$key] ?? null;
                $relevant_test = $requestData['relevant_test_hidden'][$key] ?? null;

                // Check if the current row is not blank (i.e., test_type and test_date are not empty)
                if (!empty($testType) ) {
                    if ($testScoreId) {
                        // Update existing test score if ID is provided
                        $existingTestScore = ClientTestScore::find($testScoreId);
                        if ($existingTestScore && $existingTestScore->client_id == $obj->id) {
                            $existingTestScore->update([
                                'admin_id' => Auth::user()->id,
                                'test_type' => $testType,
                                'listening' => $listening, // Update with text value
                                'reading' => $reading,     // Update with text value
                                'writing' => $writing,     // Update with text value
                                'speaking' => $speaking,   // Update with text value
                                'test_date' => $testDate,
                                'overall_score' => $overallScore, // Update overall_score
                                'relevant_test' => $relevant_test
                            ]);
                        }
                    } else {
                        // Check if a test score with the same type and date already exists
                        /*$existingTestScore = ClientTestScore::where('client_id', $obj->id)
                            ->where('test_type', $testType)
                            ->where('test_date', $testDate)
                            ->first();

                        if (!$existingTestScore) {*/
                            // Insert new test score if no duplicate is found
                            ClientTestScore::create([
                                'admin_id' => Auth::user()->id,
                                'client_id' => $obj->id, // Assigning the correct client ID
                                'test_type' => $testType,
                                'listening' => $listening, // Set with text value
                                'reading' => $reading,     // Set with text value
                                'writing' => $writing,     // Set with text value
                                'speaking' => $speaking,   // Set with text value
                                'test_date' => $testDate,
                                'overall_score' => $overallScore, // Set overall_score
                                'relevant_test' => $relevant_test
                            ]);
                        //}
                    }
                }
            }
        }
        //Test Score End Code

        //Spouse Detail Start Code
        if(
            (isset($requestData['spouse_english_score']) && !empty($requestData['spouse_english_score']))
            ||
            (isset($requestData['spouse_test_type']) && !empty($requestData['spouse_test_type']))
            ||
            (isset($requestData['spouse_listening_score']) && !empty($requestData['spouse_listening_score']))
            ||
            (isset($requestData['spouse_reading_score']) && !empty($requestData['spouse_reading_score']))
            ||
            (isset($requestData['spouse_writing_score']) && !empty($requestData['spouse_writing_score']))
            ||
            (isset($requestData['spouse_speaking_score']) && !empty($requestData['spouse_speaking_score']))
            ||
            (isset($requestData['spouse_overall_score']) && !empty($requestData['spouse_overall_score']))
            ||
            (isset($requestData['spouse_test_date']) && !empty($requestData['spouse_test_date']))
            ||
            (isset($requestData['spouse_skill_assessment']) && !empty($requestData['spouse_skill_assessment']))
            ||
            (isset($requestData['spouse_skill_assessment_status']) && !empty($requestData['spouse_skill_assessment_status']))
            ||
            (isset($requestData['spouse_nomi_occupation']) && !empty($requestData['spouse_nomi_occupation']))
            ||
            (isset($requestData['spouse_assessment_date']) && !empty($requestData['spouse_assessment_date']))
        )
        {

            // Extract single values from the request
            $englishScore = $requestData['spouse_english_score'];
            $testType = $requestData['spouse_test_type'];
            $listeningScore = $requestData['spouse_listening_score'];
            $readingScore = $requestData['spouse_reading_score'];
            $writingScore = $requestData['spouse_writing_score'];
            $speakingScore = $requestData['spouse_speaking_score'];
            $overallScore = $requestData['spouse_overall_score'];
            $spousetestdate = $requestData['spouse_test_date'];

            $skillAssessment = $requestData['spouse_skill_assessment'];
            $skillAssessmentStatus = $requestData['spouse_skill_assessment_status'];
            $nomiOccupation = $requestData['spouse_nomi_occupation'];
            $assessmentDate = $requestData['spouse_assessment_date'];

            if( ClientSpouseDetail::where('client_id', $obj->id)->delete() ) {
                ClientSpouseDetail::create([
                    'admin_id' => Auth::user()->id,
                    'client_id' => $obj->id,
                    'spouse_english_score' => $englishScore,
                    'spouse_test_type' => $testType,
                    'spouse_listening_score' => $listeningScore,
                    'spouse_reading_score' => $readingScore,
                    'spouse_writing_score' => $writingScore,
                    'spouse_speaking_score' => $speakingScore,
                    'spouse_overall_score' => $overallScore,
                    'spouse_test_date' => $spousetestdate,
                    'spouse_skill_assessment' => $skillAssessment,
                    'spouse_skill_assessment_status' => $skillAssessmentStatus,
                    'spouse_nomi_occupation' => $nomiOccupation,
                    'spouse_assessment_date' => $assessmentDate
                ]);
            }
        }
        //Spouse Detail End Code

        // Handle Partner Deletion
    if (isset($requestData['delete_partner_ids']) && is_array($requestData['delete_partner_ids'])) {
        Log::info('Deleting partners:', ['delete_partner_ids' => $requestData['delete_partner_ids']]);
        foreach ($requestData['delete_partner_ids'] as $partnerId) {
            $partner = ClientRelationship::find($partnerId);
            if ($partner && $partner->client_id == $obj->id) {
                // Check if this partner is used for EOI calculation
                // Match by related_client_id in client_spouse_details
                $spouseDetail = ClientSpouseDetail::where('client_id', $obj->id)
                    ->where('related_client_id', $partner->related_client_id)
                    ->first();
                
                if ($spouseDetail) {
                    // Clear EOI data since partner is being deleted
                    $spouseDetail->delete();
                    
                    // Clear points cache to recalculate without partner
                    if (class_exists('\App\Services\PointsService')) {
                        $pointsService = new \App\Services\PointsService();
                        $pointsService->clearCache($obj->id);
                    }
                    
                    Log::info('Cleared partner EOI data for deleted partner', [
                        'partner_id' => $partnerId,
                        'related_client_id' => $partner->related_client_id,
                        'client_id' => $obj->id
                    ]);
                    
                    // Log activity for audit trail
                    $this->logClientActivity(
                        $obj->id,
                        'cleared partner EOI information',
                        "Partner removed from EOI calculation (partner deleted from family section)",
                        'activity'
                    );
                }
                
                // Delete reciprocal relationship if exists
                if ($partner->related_client_id) {
                    ClientRelationship::where('client_id', $partner->related_client_id)
                        ->where('related_client_id', $obj->id)
                        ->delete();
                    Log::info('Deleted reciprocal relationship for partner:', ['partner_id' => $partnerId, 'related_client_id' => $partner->related_client_id]);
                }
                
                // Delete the partner record
                $partner->delete();
                Log::info('Deleted partner:', ['partner_id' => $partnerId]);
            } else {
                Log::warning('Partner not found or does not belong to client:', ['partner_id' => $partnerId, 'client_id' => $obj->id]);
            }
        }
    }

    // Partner Handling for client_partners table
    if (isset($requestData['partner_details']) && is_array($requestData['partner_details'])) {
        Log::info('Processing partner data:', [
            'partner_details' => $requestData['partner_details'],
            'relationship_type' => $requestData['relationship_type'] ?? [],
            'partner_id' => $requestData['partner_id'] ?? [],
            'partner_email' => $requestData['partner_email'] ?? [],
            'partner_first_name' => $requestData['partner_first_name'] ?? [],
            'partner_last_name' => $requestData['partner_last_name'] ?? [],
            'partner_phone' => $requestData['partner_phone'] ?? [],
        ]);

        $relationshipMap = [
            'Husband' => 'Wife',
            'Wife' => 'Husband',
            'Ex-Husband' => 'Ex-Husband',
            'Ex-Wife' => 'Ex-Wife',
            'Mother-in-law' => 'Mother-in-law',
            'Defacto' => 'Defacto',
        ];

        foreach ($requestData['partner_details'] as $key => $details) {
            $relationshipType = $requestData['relationship_type'][$key] ?? null;
            $partnerId = $requestData['partner_id'][$key] ?? null;
            $email = $requestData['partner_email'][$key] ?? null;
            $firstName = $requestData['partner_first_name'][$key] ?? null;
            $lastName = $requestData['partner_last_name'][$key] ?? null;
            $phone = $requestData['partner_phone'][$key] ?? null;

            // Skip if relationship_type is not provided (validation should catch this, but adding as a safety check)
            if (empty($relationshipType)) {
                Log::warning('Skipping partner entry due to missing relationship type:', ['key' => $key]);
                continue;
            }

            $relatedClientId = $partnerId && is_numeric($partnerId) ? $partnerId : null;

            // Determine if extra fields should be saved (only if related_client_id is null)
            $saveExtraFields = !$relatedClientId;

            // Prepare partner data for client_partners table
            $partnerData = [
                'admin_id' => Auth::user()->id,
                'client_id' => $obj->id,
                'related_client_id' => $relatedClientId,
                'details' => $relatedClientId ? $details : null, // Save details only if a match is found
                'relationship_type' => $relationshipType,
                'email' => $saveExtraFields ? $email : null,
                'first_name' => $saveExtraFields ? $firstName : null,
                'last_name' => $saveExtraFields ? $lastName : null,
                'phone' => $saveExtraFields ? $phone : null,
            ];

            Log::info('Prepared partner data:', ['key' => $key, 'partnerData' => $partnerData]);

            if ($partnerId && is_numeric($partnerId)) {
                // Update existing partner
                $existingPartner = ClientRelationship::find($partnerId);
                if ($existingPartner && $existingPartner->client_id == $obj->id) {
                    $existingPartner->update($partnerData);
                    Log::info('Updated existing partner:', ['partner_id' => $partnerId, 'data' => $partnerData]);

                    // Update reciprocal relationship if exists
                    if ($existingPartner->related_client_id && isset($relationshipMap[$relationshipType])) {
                        $reciprocalData = [
                            'admin_id' => Auth::user()->id,
                            'relationship_type' => $relationshipMap[$relationshipType],
                            'details' => "{$obj->first_name} {$obj->last_name} ({$obj->email}, {$obj->phone}, {$obj->client_id})",
                            'email' => null,
                            'first_name' => null,
                            'last_name' => null,
                            'phone' => null,
                        ];
                        ClientRelationship::where('client_id', $existingPartner->related_client_id)
                            ->where('related_client_id', $obj->id)
                            ->update($reciprocalData);
                        Log::info('Updated reciprocal relationship for partner:', ['partner_id' => $partnerId, 'reciprocal_data' => $reciprocalData]);
                    }
                } else {
                    Log::warning('Existing partner not found or does not belong to client:', ['partner_id' => $partnerId, 'client_id' => $obj->id]);
                }
            } else {
                // Create new partner
                $newPartner = ClientRelationship::create($partnerData);
                Log::info('Created new partner:', ['new_partner_id' => $newPartner->id, 'data' => $partnerData]);

                // Create reciprocal relationship if related_client_id is set
                if ($relatedClientId && isset($relationshipMap[$relationshipType])) {
                    $relatedClient = Admin::find($relatedClientId);
                    if ($relatedClient) {
                        $reciprocalData = [
                            'admin_id' => Auth::user()->id,
                            'client_id' => $relatedClientId,
                            'related_client_id' => $obj->id,
                            'details' => "{$obj->first_name} {$obj->last_name} ({$obj->email}, {$obj->phone}, {$obj->client_id})",
                            'relationship_type' => $relationshipMap[$relationshipType],
                            'email' => null,
                            'first_name' => null,
                            'last_name' => null,
                            'phone' => null,
                        ];
                        ClientRelationship::create($reciprocalData);
                        Log::info('Created reciprocal relationship for new partner:', ['new_partner_id' => $newPartner->id, 'reciprocal_data' => $reciprocalData]);
                    } else {
                        Log::warning('Related client not found for reciprocal relationship:', ['related_client_id' => $relatedClientId]);
                    }
                }
            }
        }

        // Debug: Log the number of partners saved
        $savedPartners = ClientRelationship::where('client_id', $obj->id)->count();
        Log::info('Total partners saved for client:', ['client_id' => $obj->id, 'count' => $savedPartners]);
    } else {
        Log::info('No partner data provided to process.');
    }

        $saved	=	$obj->save();
        if( $requestData['client_id'] != '') {
            $objs			   = 	Admin::find($obj->id);
            $objs->client_id	=	$requestData['client_id'];
            $saveds				=	$objs->save();
        }

        $route = $request->route;
        if(strpos($request->route,'?')){
            $position=strpos($request->route,'?');
            if ($position !== false) {
                $route = substr($request->route, 0, $position);
            }
        }
        //dd($route);
        if(!$saved) {
            return redirect()->back()->with('error', config('constants.server_error'));
        } else if( $route ==url('/assignee')) {
            //$subject = 'Lead status has changed to '.@$requestData['status'].' from '. Auth::user()->first_name;
            $subject = 'Lead status has changed from '. Auth::user()->first_name;
            $objs = new ActivitiesLog;
            $objs->client_id = $request->id;
            $objs->created_by = Auth::user()->id;
            $objs->subject = $subject;
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();
            return redirect()->route('assignee.index')->with('success','Assignee updated successfully');
        } else {
            // Update service taken - REMOVED (table client_service_takens does not exist)

            $clientId = $requestData['id'];
            $encodedId = base64_encode(convert_uuencode($clientId));

            $latestMatter = DB::table('client_matters')
                ->where('client_id', $clientId)
                ->where('matter_status', 1)
                ->orderByDesc('id')
                ->first();

            // Log activity for personal information update
            $this->logClientActivity(
                $clientId,
                'updated personal information',
                'Updated personal details including name, DOB, gender, marital status, and contact information',
                'activity'
            );

            $redirectUrl = $latestMatter
                ? '/clients/detail/'.$encodedId.'/'.$latestMatter->client_unique_matter_no
                : '/clients/detail/'.$encodedId;

            return Redirect::to($redirectUrl)->with('success', 'Details updated successfully');
        }
    }
    else
    {
        if(isset($id) && !empty($id))
        {
            $id = $this->decodeString($id); //dd($id);
            $targetRecord = Admin::where('id', '=', $id)->whereIn('type', ['client', 'lead'])->first(['id', 'type']);
            if ($targetRecord) {
                // Match ClientsController@detail: leads bypass row-level client rules; clients use StaffClientVisibility.
                if (($targetRecord->type ?? null) !== 'lead'
                    && ! \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $id, Auth::user())) {
                    return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
                }
                // Use service to get all data with optimized queries (prevents N+1)
                // Now returns complete data set including passports, travels, etc.
                $data = app(\App\Services\ClientEditService::class)->getClientEditData($id);

                return view('crm.clients.edit', $data);
            }

            return Redirect::to('/clients')->with('error', 'Clients Not Exist');
        } else {
            return Redirect::to('/clients')->with('error', config('constants.unauthorized'));
        }
    }
}

    /**
     * Save section data via AJAX
     */
    public function saveSection(Request $request)
    {
        try {
            $section = $request->input('section');
            $clientId = $request->input('id'); // Use 'id' instead of 'client_id' - 'id' is the database ID
            
            // Validate client exists
            $client = Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            if (! \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            switch ($section) {
                case 'basicInfo':
                    return $this->saveBasicInfoSection($request, $client);
                case 'phoneNumbers':
                    return $this->savePhoneNumbersSection($request, $client);
                case 'emailAddresses':
                    return $this->saveEmailAddressesSection($request, $client);
                case 'passportInfo':
                    return $this->savePassportInfoSection($request, $client);
                case 'visaInfo':
                    return $this->saveVisaInfoSection($request, $client);
                case 'addressInfo':
                    return $this->saveAddressInfoSection($request, $client);
                case 'travelInfo':
                    return $this->saveTravelInfoSection($request, $client);
                case 'qualificationsInfo':
                    return $this->saveQualificationsInfoSection($request, $client);
                case 'experienceInfo':
                    return $this->saveExperienceInfoSection($request, $client);
                case 'additionalInfo':
                    return $this->saveAdditionalInfoSection($request, $client);
                case 'characterInfo':
                    return $this->saveCharacterInfoSection($request, $client);
                case 'partnerInfo':
                    return $this->savePartnerInfoSection($request, $client);
                case 'partnerEoiInfo':
                    return $this->savePartnerEoiInfoSection($request, $client);
                case 'childrenInfo':
                    return $this->saveChildrenInfoSection($request, $client);
                case 'parentsInfo':
                    return $this->saveParentsInfoSection($request, $client);
                case 'siblingsInfo':
                    return $this->saveSiblingsInfoSection($request, $client);
                case 'othersInfo':
                    return $this->saveOthersInfoSection($request, $client);
                case 'eoiInfo':
                    return $this->saveEoiInfoSection($request, $client);
                case 'occupation':
                    return $this->saveOccupationInfoSection($request, $client);
                case 'test_scores':
                    return $this->saveTestScoreInfoSection($request, $client);
                case 'relatedFilesInfo':
                    return $this->saveRelatedFilesInfoSection($request, $client);
                case 'companyInfo':
                    return $this->saveCompanySection($request, $client);
                case 'contactPersonInfo':
                    return $this->saveContactPersonSection($request, $client);
                case 'trust':
                    return $this->saveTrustSection($request, $client);
                case 'sponsorship':
                    return $this->saveSponsorshipSection($request, $client);
                case 'directors':
                    return $this->saveDirectorsSection($request, $client);
                case 'financial':
                    return $this->saveFinancialSection($request, $client);
                case 'workforce':
                    return $this->saveWorkforceSection($request, $client);
                case 'operations':
                    return $this->saveOperationsSection($request, $client);
                case 'lmt':
                    return $this->saveLmtSection($request, $client);
                case 'training':
                    return $this->saveTrainingSection($request, $client);
                case 'nominations':
                    return $this->saveNominationsSection($request, $client);
                case 'leadPipeline':
                    return $this->saveLeadPipelineSection($request, $client);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid section specified'
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save company info section (company_name, has_trading_name, trading_names[], ABN, ACN, company_type, website, trust fields).
     * Only for company clients (is_company = true).
     */
    private function saveCompanySection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }

        // Normalize empty company_website to null (avoids url validation failure on empty string)
        $request->merge(['company_website' => $request->filled('company_website') ? trim($request->company_website) : null]);

        $validated = $request->validate([
            'company_name' => 'required|max:255',
            'has_trading_name' => 'nullable|in:0,1',
            'trading_names' => 'nullable|array',
            'trading_names.*' => 'nullable|string|max:255',
            'trading_name_primary' => 'nullable|integer|min:0',
            'ABN_number' => 'nullable|string|max:20',
            'ACN' => 'nullable|string|max:20',
            'company_type' => 'nullable|string|max:50',
            'company_website' => 'nullable|url|max:255',
            'trust_name' => 'nullable|string|max:255',
            'trust_abn' => 'nullable|string|max:64',
        ]);

        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => $validated['company_name']]);
        $company->company_name = $validated['company_name'];
        $company->ABN_number = !empty($validated['ABN_number']) ? preg_replace('/\D/', '', $validated['ABN_number']) : null;
        $company->ACN = !empty($validated['ACN']) ? preg_replace('/\D/', '', $validated['ACN']) : null;
        $company->company_type = Company::normalizeBusinessType($validated['company_type'] ?? null);
        $company->company_website = $validated['company_website'] ?? null;

        if (Company::isTrusteeBusinessType($company->company_type)) {
            $tnm = $validated['trust_name'] ?? null;
            $company->trust_name = ($tnm !== null && trim((string) $tnm) !== '') ? trim((string) $tnm) : null;
            $tn = $validated['trust_abn'] ?? null;
            $company->trust_abn = ($tn !== null && trim((string) $tn) !== '') ? trim((string) $tn) : null;
        } else {
            $company->trust_name = null;
            $company->trust_abn = null;
            $company->trustee_name = null;
            $company->trustee_details = null;
        }

        $hasTradingName = (int) ($validated['has_trading_name'] ?? 0) === 1;
        $company->has_trading_name = $hasTradingName;

        if ($hasTradingName && !empty($validated['trading_names'])) {
            $tradingNames = array_values(array_filter(array_map('trim', $validated['trading_names'])));
            $primaryIdx = min((int) ($validated['trading_name_primary'] ?? 0), max(0, count($tradingNames) - 1));

            $company->tradingNames()->delete();
            foreach ($tradingNames as $idx => $name) {
                if ($name !== '') {
                    $company->tradingNames()->create([
                        'trading_name' => $name,
                        'is_primary' => ($idx === $primaryIdx),
                        'sort_order' => $idx,
                    ]);
                }
            }
            $company->trading_name = $tradingNames[$primaryIdx] ?? $tradingNames[0] ?? null;
        } else {
            $company->tradingNames()->delete();
            $company->trading_name = null;
        }

        $company->save();

        return response()->json([
            'success' => true,
            'message' => 'Company information updated successfully',
        ]);
    }

    /**
     * Save contact person section (contact_person_id, contact_person_position).
     * Only for company clients (is_company = true).
     */
    private function saveContactPersonSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }

        $validated = $request->validate([
            'contact_person_id' => 'nullable|exists:admins,id',
            'contact_person_position' => 'nullable|string|max:255',
        ]);

        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $company->contact_person_id = $validated['contact_person_id'] ?? null;
        $company->contact_person_position = $validated['contact_person_position'] ?? null;
        $company->save();

        return response()->json([
            'success' => true,
            'message' => 'Contact person updated successfully',
        ]);
    }

    private function saveTrustSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        if (! Company::isTrusteeBusinessType($company->company_type)) {
            return response()->json(['success' => false, 'message' => 'Trust details only apply when Business Type is Trustee'], 400);
        }
        $validated = $request->validate([
            'trust_name' => 'nullable|string|max:255',
            'trust_abn' => 'nullable|string|max:64',
            'trustee_name' => 'nullable|string|max:255',
            'trustee_details' => 'nullable|string',
        ]);
        $tnm = $validated['trust_name'] ?? null;
        $company->trust_name = ($tnm !== null && trim((string) $tnm) !== '') ? trim((string) $tnm) : null;
        $ta = $validated['trust_abn'] ?? null;
        $company->trust_abn = ($ta !== null && trim((string) $ta) !== '') ? trim((string) $ta) : null;
        $tn = $validated['trustee_name'] ?? null;
        $company->trustee_name = ($tn !== null && trim((string) $tn) !== '') ? trim((string) $tn) : null;
        $td = $validated['trustee_details'] ?? null;
        $company->trustee_details = ($td !== null && trim((string) $td) !== '') ? trim((string) $td) : null;
        $company->save();
        return response()->json(['success' => true, 'message' => 'Trust details updated successfully']);
    }

    private function saveSponsorshipSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $request->validate([
            'sponsorship_types' => 'nullable|array',
            'sponsorship_types.*' => 'nullable|string|max:50',
            'sponsorship_statuses' => 'nullable|array',
            'sponsorship_statuses.*' => 'nullable|string|max:50',
            'sponsorship_start_dates' => 'nullable|array',
            'sponsorship_start_dates.*' => 'nullable|date',
            'sponsorship_end_dates' => 'nullable|array',
            'sponsorship_end_dates.*' => 'nullable|date',
            'sponsorship_trns' => 'nullable|array',
            'sponsorship_trns.*' => 'nullable|string|max:50',
            'sponsorship_regional' => 'nullable|array',
            'sponsorship_adverse' => 'nullable|array',
            'sponsorship_previous_notes' => 'nullable|array',
            'sponsorship_previous_notes.*' => 'nullable|string',
        ]);

        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);

        // Use array_values() to re-key all arrays to 0-based sequential indices.
        // Text [] fields are already sequential; checkbox arrays use named keys like
        // sponsorship_regional[0], sponsorship_regional[2] (sparse when unchecked rows
        // exist), so re-keying normalises them before the loop index lookup below.
        $types = array_values($request->input('sponsorship_types', []));
        $statuses = array_values($request->input('sponsorship_statuses', []));
        $startDates = array_values($request->input('sponsorship_start_dates', []));
        $endDates = array_values($request->input('sponsorship_end_dates', []));
        $trns = array_values($request->input('sponsorship_trns', []));
        $notes = array_values($request->input('sponsorship_previous_notes', []));
        // Checkboxes: convert the named-key map {0:'1', 2:'1'} into a set of indices
        // for fast membership testing without relying on sequential key access.
        $regionalChecked = array_keys($request->input('sponsorship_regional', []));
        $adverseChecked  = array_keys($request->input('sponsorship_adverse', []));

        $company->sponsorships()->delete();

        $count = max(
            count($types),
            count($statuses),
            count($startDates),
            count($endDates),
            count($trns),
            count($notes),
            1
        );

        $sort = 0;
        for ($i = 0; $i < $count; $i++) {
            $type = trim((string) ($types[$i] ?? ''));
            $status = trim((string) ($statuses[$i] ?? ''));
            $trn = trim((string) ($trns[$i] ?? ''));
            $start = ! empty($startDates[$i]) ? $startDates[$i] : null;
            $end = ! empty($endDates[$i]) ? $endDates[$i] : null;
            $note = trim((string) ($notes[$i] ?? ''));
            $regional = in_array($i, $regionalChecked);
            $adverse  = in_array($i, $adverseChecked);

            if ($type === '' && $status === '' && $trn === '' && ! $start && ! $end && $note === '' && ! $regional && ! $adverse) {
                continue;
            }

            $company->sponsorships()->create([
                'sponsorship_type' => $type !== '' ? $type : null,
                'sponsorship_status' => $status !== '' ? $status : null,
                'sponsorship_start_date' => $start,
                'sponsorship_end_date' => $end,
                'trn' => $trn !== '' ? $trn : null,
                'regional_sponsorship' => $regional,
                'adverse_information' => $adverse,
                'previous_sponsorship_notes' => $note !== '' ? $note : null,
                'sort_order' => $sort++,
            ]);
        }

        $first = $company->sponsorships()->orderBy('sort_order')->orderBy('id')->first();
        if ($first) {
            $company->sponsorship_type = $first->sponsorship_type;
            $company->sponsorship_status = $first->sponsorship_status;
            $company->sponsorship_start_date = $first->sponsorship_start_date;
            $company->sponsorship_end_date = $first->sponsorship_end_date;
            $company->trn = $first->trn;
            $company->regional_sponsorship = $first->regional_sponsorship;
            $company->adverse_information = $first->adverse_information;
            $company->previous_sponsorship_notes = $first->previous_sponsorship_notes;
        } else {
            $company->sponsorship_type = null;
            $company->sponsorship_status = null;
            $company->sponsorship_start_date = null;
            $company->sponsorship_end_date = null;
            $company->trn = null;
            $company->regional_sponsorship = false;
            $company->adverse_information = false;
            $company->previous_sponsorship_notes = null;
        }
        $company->save();

        return response()->json(['success' => true, 'message' => 'Sponsorship updated successfully']);
    }

    private function saveDirectorsSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $clientIds = $request->input('director_client_ids', []);
        $names = $request->input('director_names', []);
        $dobs = $request->input('director_dobs', []);
        $roles = $request->input('director_roles', []);
        $primaryIdx = (int) $request->input('director_primary', 0);
        $company->directors()->delete();
        $count = max(count($clientIds), count($names), 1);
        for ($i = 0; $i < $count; $i++) {
            $clientId = $clientIds[$i] ?? null;
            $name = trim($names[$i] ?? '');
            if ($clientId && $name) {
                return response()->json([
                    'success' => false,
                    'message' => "Director " . ($i + 1) . ": Provide either a searchable client/lead OR a name (not in system), not both.",
                    'errors' => ['director_client_ids' => ['Cannot have both linked director and manual name.']]
                ], 422);
            }
            if (!$clientId && !$name) {
                continue;
            }
            $directorClient = null;
            if ($clientId) {
                $directorClient = Admin::where('id', $clientId)
                    ->whereIn('type', ['client', 'lead'])
                    ->where('is_company', false)
                    ->first();
                if (!$directorClient) {
                    return response()->json([
                        'success' => false,
                        'message' => "Director " . ($i + 1) . ": Selected person not found or invalid (must be client/lead, not company).",
                        'errors' => ['director_client_ids' => ['Invalid or non-existent client/lead selected.']]
                    ], 422);
                }
            }
            $linkedDob = $directorClient && $directorClient->dob ? \Carbon\Carbon::parse($directorClient->dob)->format('Y-m-d') : null;
            $directorDob = !empty($dobs[$i]) ? $dobs[$i] : $linkedDob;
            $company->directors()->create([
                'director_client_id' => $directorClient ? $directorClient->id : null,
                'director_name' => $clientId ? null : $name,
                'director_dob' => $directorDob,
                'director_role' => $roles[$i] ?? null,
                'is_primary' => ($i === $primaryIdx),
                'sort_order' => $i,
            ]);
        }
        // Ensure at least one director is primary when we have directors
        $directors = $company->directors()->orderBy('sort_order')->get();
        if ($directors->isNotEmpty() && !$directors->contains('is_primary', true)) {
            $directors->first()->update(['is_primary' => true]);
        }
        return response()->json(['success' => true, 'message' => 'Directors updated successfully']);
    }

    private function saveFinancialSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }

        $years = $request->input('financial_year', []);
        $turnovers = $request->input('financial_annual_turnover', []);
        $wages = $request->input('financial_wages_expenditure', []);

        if (! is_array($years)) {
            $years = [];
        }
        if (! is_array($turnovers)) {
            $turnovers = [];
        }
        if (! is_array($wages)) {
            $wages = [];
        }

        $max = max(count($years), count($turnovers), count($wages), 1);

        $rows = [];
        for ($i = 0; $i < $max; $i++) {
            $rawYear = $years[$i] ?? '';
            $fy = is_string($rawYear) ? trim($rawYear) : (string) $rawYear;
            $fy = $fy === '' ? null : $fy;
            if ($fy !== null && mb_strlen($fy) > 64) {
                return response()->json([
                    'success' => false,
                    'message' => 'Financial year is too long on row ' . ($i + 1) . ' (max 64 characters).',
                ], 422);
            }

            $to = array_key_exists($i, $turnovers) && $turnovers[$i] !== '' && $turnovers[$i] !== null
                ? $turnovers[$i] : null;
            $wg = array_key_exists($i, $wages) && $wages[$i] !== '' && $wages[$i] !== null
                ? $wages[$i] : null;

            if ($fy === null && $to === null && $wg === null) {
                continue;
            }

            if ($to !== null) {
                if (! is_numeric($to) || (float) $to < 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Annual turnover must be a non-negative number (row ' . ($i + 1) . ').',
                    ], 422);
                }
            }
            if ($wg !== null) {
                if (! is_numeric($wg) || (float) $wg < 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Wages expenditure must be a non-negative number (row ' . ($i + 1) . ').',
                    ], 422);
                }
            }

            $rows[] = [
                'financial_year' => $fy,
                'annual_turnover' => $to !== null ? (float) $to : null,
                'wages_expenditure' => $wg !== null ? (float) $wg : null,
            ];
        }

        $seen = [];
        foreach ($rows as $r) {
            if ($r['financial_year'] === null) {
                continue;
            }
            $key = mb_strtolower($r['financial_year']);
            if (isset($seen[$key])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate financial year entries are not allowed.',
                    'errors' => ['financial_year' => ['Each financial year must be unique.']],
                ], 422);
            }
            $seen[$key] = true;
        }

        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);

        DB::transaction(function () use ($company, $rows) {
            $company->financials()->delete();
            $sort = 0;
            foreach ($rows as $r) {
                $company->financials()->create([
                    'financial_year' => $r['financial_year'],
                    'annual_turnover' => $r['annual_turnover'],
                    'wages_expenditure' => $r['wages_expenditure'],
                    'sort_order' => $sort++,
                ]);
            }
            $company->syncLegacyFinancialColumns();
        });

        return response()->json(['success' => true, 'message' => 'Financial details updated successfully']);
    }

    private function saveWorkforceSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $validated = $request->validate([
            'workforce_australian_citizens'      => 'nullable|integer|min:0',
            'workforce_permanent_residents'      => 'nullable|integer|min:0',
            'workforce_temp_visa_holders'        => 'nullable|integer|min:0',
            'workforce_total'                    => 'nullable|integer|min:0',
            'workforce_foreign_494'              => 'nullable|integer|min:0',
            'workforce_foreign_other_temp_activity' => 'nullable|integer|min:0',
            'workforce_foreign_overseas_students'=> 'nullable|integer|min:0',
            'workforce_foreign_working_holiday'  => 'nullable|integer|min:0',
            'workforce_foreign_other'            => 'nullable|integer|min:0',
        ]);
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $company->workforce_australian_citizens       = $validated['workforce_australian_citizens'] ?? null;
        $company->workforce_permanent_residents       = $validated['workforce_permanent_residents'] ?? null;
        $company->workforce_temp_visa_holders         = $validated['workforce_temp_visa_holders'] ?? null;
        $company->workforce_total                     = $validated['workforce_total'] ?? null;
        $company->workforce_foreign_494               = $validated['workforce_foreign_494'] ?? null;
        $company->workforce_foreign_other_temp_activity = $validated['workforce_foreign_other_temp_activity'] ?? null;
        $company->workforce_foreign_overseas_students = $validated['workforce_foreign_overseas_students'] ?? null;
        $company->workforce_foreign_working_holiday   = $validated['workforce_foreign_working_holiday'] ?? null;
        $company->workforce_foreign_other             = $validated['workforce_foreign_other'] ?? null;
        $company->save();
        return response()->json(['success' => true, 'message' => 'Workforce updated successfully']);
    }

    private function saveOperationsSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $validated = $request->validate([
            'business_operating_since' => 'nullable|date',
            'main_business_activity' => 'nullable|string|max:255',
        ]);
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $company->business_operating_since = $validated['business_operating_since'] ?? null;
        $company->main_business_activity = $validated['main_business_activity'] ?? null;
        $company->save();
        return response()->json(['success' => true, 'message' => 'Operations updated successfully']);
    }

    private function saveLmtSection($request, $client)
    {
        if (! $client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }

        $matterId = $request->input('client_matter_id');
        if ($matterId === null || $matterId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Matter is required to save Labour Market Testing details.',
            ], 422);
        }

        $matter = ClientMatter::query()
            ->where('id', (int) $matterId)
            ->where('client_id', $client->id)
            ->first();

        if (! $matter) {
            return response()->json(['success' => false, 'message' => 'Matter not found.'], 404);
        }

        if ((int) $matter->matter_status !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'LMT can only be updated for active matters.',
            ], 422);
        }

        if ($request->boolean('delete_lmt')) {
            $matter->lmt_required = null;
            $matter->lmt_start_date = null;
            $matter->lmt_end_date = null;
            $matter->lmt_notes = null;
            $matter->lmt_password = null;
            $matter->save();

            return response()->json(['success' => true, 'message' => 'Labour Market Testing details removed for this matter. You can add a new record anytime.']);
        }

        $request->merge([
            'lmt_start_date' => $request->filled('lmt_start_date') ? $request->input('lmt_start_date') : null,
            'lmt_end_date' => $request->filled('lmt_end_date') ? $request->input('lmt_end_date') : null,
        ]);

        $validated = $request->validate([
            'lmt_start_date' => 'nullable|date',
            'lmt_end_date' => 'nullable|date',
            'lmt_notes' => 'nullable|string',
            'lmt_password' => 'nullable|string|max:255',
        ]);

        $reqRaw = $request->input('lmt_required');
        $lmtRequired = null;
        if ($reqRaw === '1' || $reqRaw === 1 || $reqRaw === true) {
            $lmtRequired = true;
        } elseif ($reqRaw === '0' || $reqRaw === 0 || $reqRaw === false) {
            $lmtRequired = false;
        }

        $start = $validated['lmt_start_date'] ?? null;
        $end = $validated['lmt_end_date'] ?? null;
        if ($start !== null && $end !== null) {
            try {
                if (\Carbon\Carbon::parse($end)->lt(\Carbon\Carbon::parse($start))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'LMT end date must be on or after the start date.',
                    ], 422);
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid LMT date values.',
                ], 422);
            }
        }

        $notes = isset($validated['lmt_notes']) ? trim((string) $validated['lmt_notes']) : '';
        $notes = $notes !== '' ? $notes : null;
        $password = isset($validated['lmt_password']) ? trim((string) $validated['lmt_password']) : '';
        $password = $password !== '' ? $password : null;

        $matter->lmt_required = $lmtRequired;
        $matter->lmt_start_date = $start;
        $matter->lmt_end_date = $end;
        $matter->lmt_notes = $notes;
        $matter->lmt_password = $password;
        $matter->save();

        return response()->json(['success' => true, 'message' => 'LMT details updated successfully']);
    }

    private function saveTrainingSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $validated = $request->validate([
            'training_position_title' => 'nullable|string|max:255',
            'trainer_name' => 'nullable|string|max:255',
        ]);
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $company->training_position_title = $validated['training_position_title'] ?? null;
        $company->trainer_name = $validated['trainer_name'] ?? null;
        $company->save();
        return response()->json(['success' => true, 'message' => 'Training details updated successfully']);
    }

    private function saveNominationsSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $positionTitles = $request->input('nomination_position_titles', []);
        $anzscoCodes = $request->input('nomination_anzsco_codes', []);
        $descriptions = $request->input('nomination_descriptions', []);
        $salaries = $request->input('nomination_salaries', []);
        $durations = $request->input('nomination_durations', []);
        $nominatedClientIds = $request->input('nomination_nominated_client_ids', []);
        $personNames = $request->input('nomination_person_names', []);
        $trns = $request->input('nomination_trns', []);
        $statuses = $request->input('nomination_statuses', []);
        $dates = $request->input('nomination_dates', []);
        $expiries = $request->input('nomination_expiries', []);
        $company->nominations()->delete();
        $count = max(count($positionTitles), count($personNames), count($nominatedClientIds), 1);
        for ($i = 0; $i < $count; $i++) {
            $positionTitle = trim($positionTitles[$i] ?? '');
            $clientId = $nominatedClientIds[$i] ?? null;
            $personName = trim($personNames[$i] ?? '');
            if ($clientId && $personName) {
                return response()->json([
                    'success' => false,
                    'message' => "Nomination " . ($i + 1) . ": Provide either a searchable client/lead OR a name (not in system), not both.",
                    'errors' => ['nomination_nominated' => ['Cannot have both nominated client and nominated person name.']]
                ], 422);
            }
            if (!$clientId && !$personName && !$positionTitle) {
                continue;
            }
            $company->nominations()->create([
                'position_title' => $positionTitle ?: null,
                'anzsco_code' => $anzscoCodes[$i] ?? null,
                'position_description' => $descriptions[$i] ?? null,
                'salary' => $salaries[$i] ?? null,
                'duration' => $durations[$i] ?? null,
                'nominated_client_id' => $clientId ?: null,
                'nominated_person_name' => $personName ?: null,
                'trn' => $trns[$i] ?? null,
                'status' => $statuses[$i] ?? null,
                'nomination_date' => !empty($dates[$i]) ? $dates[$i] : null,
                'expiry_date' => !empty($expiries[$i]) ? $expiries[$i] : null,
                'sort_order' => $i,
            ]);
        }
        return response()->json(['success' => true, 'message' => 'Nominations updated successfully']);
    }

    /**
     * Lead pipeline (stage, follow-up date, assignee) from client detail Personal tab — AJAX only.
     */
    private function saveLeadPipelineSection($request, $client)
    {
        if ($client->type !== 'lead') {
            return response()->json(['success' => false, 'message' => 'Lead pipeline applies to leads only.'], 400);
        }

        // Only normalise assignee when the client actually sent the field (avoid clearing user_id on partial requests).
        if ($request->has('assigned_staff_id')) {
            $request->merge([
                'assigned_staff_id' => ($request->input('assigned_staff_id') === '' || $request->input('assigned_staff_id') === null)
                    ? null
                    : (int) $request->input('assigned_staff_id'),
            ]);
        }

        $pipelineStages = ['new', 'follow_up', 'not_qualified', 'hostile'];
        $allowedStages = $pipelineStages;
        $currentStage = (string) ($client->lead_status ?? '');
        if ($currentStage !== '' && ! in_array($currentStage, $pipelineStages, true)) {
            $allowedStages[] = $currentStage;
        }

        try {
            $validated = $request->validate([
                'lead_status' => ['required', Rule::in($allowedStages)],
                'followup_date' => 'nullable|date',
                'assigned_staff_id' => 'nullable|exists:staff,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $previousLeadStatus = $client->lead_status;

        $before = [
            'Stage' => $client->lead_status,
            'Follow-up date' => $client->followup_date ? $client->followup_date->format('Y-m-d') : null,
            'Assigned to' => $client->user_id,
        ];

        $client->lead_status = $validated['lead_status'];

        if ($request->has('followup_date')) {
            $rawFd = $request->input('followup_date');
            if ($rawFd === '' || $rawFd === null) {
                if ($client->lead_status !== 'follow_up') {
                    $client->followup_date = null;
                }
            } else {
                $client->followup_date = Carbon::parse($rawFd)->format('Y-m-d H:i:s');
            }
        }

        if ($client->lead_status !== 'follow_up') {
            $client->followup_date = null;
        }

        $client->status = LeadFollowUpNoteService::adminsStatusForLeadStatus($client->lead_status);

        if (array_key_exists('assigned_staff_id', $validated)) {
            $client->user_id = $validated['assigned_staff_id'];
        }

        $client->save();

        $lead = Lead::find($client->id);
        if ($lead) {
            app(LeadFollowUpNoteService::class)->syncNotesForLead($lead, $previousLeadStatus);
        }

        $after = [
            'Stage' => $client->lead_status,
            'Follow-up date' => $client->followup_date ? $client->followup_date->format('Y-m-d') : null,
            'Assigned to' => $client->user_id,
        ];

        $changedFields = [];
        foreach ($before as $label => $oldVal) {
            $newVal = $after[$label] ?? null;
            if ($label === 'Assigned to') {
                if ((int) $oldVal !== (int) $newVal) {
                    $changedFields[$label] = ['old' => $oldVal, 'new' => $newVal];
                }
            } elseif ($oldVal !== $newVal) {
                $changedFields[$label] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        if (! empty($changedFields)) {
            $this->logClientActivityWithChanges(
                $client->id,
                'updated lead pipeline',
                $changedFields,
                'activity'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead pipeline updated',
            'lead_status' => $client->lead_status,
            'followup_date' => $client->followup_date ? $client->followup_date->format('Y-m-d') : null,
            'assigned_staff_id' => $client->user_id,
            'record_status' => (int) $client->status,
        ]);
    }

    private function saveBasicInfoSection($request, $client)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|max:255',
                'last_name' => 'nullable|max:255',
                'client_id' => 'required|max:255|unique:admins,client_id,' . $client->id,
                'dob' => [
                    'nullable',
                    'date_format:d/m/Y',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The date of birth cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'age' => 'nullable|string',
                'gender' => 'nullable|in:Male,Female,Other',
                'marital_status' => 'nullable|in:Never Married,Engaged,Married,De Facto,Defacto,Separated,Divorced,Widowed,Single'
            ]);

            // Convert DOB format and calculate age (like the working methods)
            $dob = null;
            $age = null;
            if (!empty($validated['dob'])) {
                try {
                    $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['dob']);
                    $dob = $dobDate->format('Y-m-d');
                    $age = $dobDate->diff(\Carbon\Carbon::now())->format('%y years %m months');
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format. Must be dd/mm/yyyy.'
                    ], 422);
                }
            }

            // Map marital status values for backward compatibility
            $maritalStatus = $validated['marital_status'] ?? null;
            if ($maritalStatus === 'Defacto') {
                $maritalStatus = 'De Facto';
            }
            if ($maritalStatus === 'Single') {
                $maritalStatus = 'Never Married';
            }

            $previousLeadStatus = ($client->type === 'lead') ? $client->lead_status : null;

            if ($client->type === 'lead') {
                $request->validate([
                    'lead_status' => 'sometimes|in:new,follow_up,not_qualified,hostile',
                    'followup_date' => 'nullable|date',
                    'assigned_staff_id' => 'nullable|exists:staff,id',
                ]);

                if ($request->has('lead_status')) {
                    $client->lead_status = (string) $request->input('lead_status');
                }

                if ($request->has('followup_date')) {
                    $rawFd = $request->input('followup_date');
                    if ($rawFd === '' || $rawFd === null) {
                        if ($client->lead_status !== 'follow_up') {
                            $client->followup_date = null;
                        }
                    } else {
                        $client->followup_date = Carbon::parse($rawFd)->format('Y-m-d H:i:s');
                    }
                }

                if ($client->lead_status !== 'follow_up') {
                    $client->followup_date = null;
                }

                $client->status = LeadFollowUpNoteService::adminsStatusForLeadStatus($client->lead_status);

                if ($request->filled('assigned_staff_id')) {
                    $client->user_id = (int) $request->input('assigned_staff_id');
                }
            }

            // Track changed fields for activity log with old and new values
            $changedFields = [];
            $fieldLabels = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'client_id' => 'Client ID',
                'dob' => 'Date of Birth',
                'gender' => 'Gender',
                'marital_status' => 'Marital Status'
            ];

            // Compare and track changes with old and new values
            if ($client->first_name !== $validated['first_name']) {
                $changedFields[$fieldLabels['first_name']] = [
                    'old' => $client->first_name,
                    'new' => $validated['first_name']
                ];
            }
            if ($client->last_name !== ($validated['last_name'] ?? null)) {
                $changedFields[$fieldLabels['last_name']] = [
                    'old' => $client->last_name,
                    'new' => $validated['last_name'] ?? null
                ];
            }
            if ($client->client_id !== $validated['client_id']) {
                $changedFields[$fieldLabels['client_id']] = [
                    'old' => $client->client_id,
                    'new' => $validated['client_id']
                ];
            }
            if ($client->dob !== $dob) {
                $changedFields[$fieldLabels['dob']] = [
                    'old' => $client->dob,
                    'new' => $dob
                ];
            }
            if ($client->gender !== ($validated['gender'] ?? null)) {
                $changedFields[$fieldLabels['gender']] = [
                    'old' => $client->gender,
                    'new' => $validated['gender'] ?? null
                ];
            }
            if ($client->marital_status !== $maritalStatus) {
                $changedFields[$fieldLabels['marital_status']] = [
                    'old' => $client->marital_status,
                    'new' => $maritalStatus
                ];
            }

            // Use direct assignment pattern (like the working old methods)
            $client->first_name = $validated['first_name'];
            $client->last_name = $validated['last_name'] ?? null;
            $client->client_id = $validated['client_id'];
            $client->dob = $dob;
            $client->age = $age;
            $client->gender = $validated['gender'] ?? null;
            $client->marital_status = $maritalStatus;
            $client->save();

            if ($client->type === 'lead') {
                $lead = Lead::find($client->id);
                if ($lead) {
                    app(LeadFollowUpNoteService::class)->syncNotesForLead($lead, $previousLeadStatus);
                }
            }

            // Log activity with specific changed fields
            if (!empty($changedFields)) {
                $this->logClientActivityWithChanges(
                    $client->id,
                    'updated basic information',
                    $changedFields,
                    'activity'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Basic information updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    private function savePhoneNumbersSection($request, $client)
    {
        try {
            $phoneNumbers = json_decode($request->input('phone_numbers'), true);
            
            if (!is_array($phoneNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone numbers data'
                ], 400);
            }

            // Validate that at least one phone number is provided
            if (empty($phoneNumbers) || !array_filter(array_column($phoneNumbers, 'phone'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one phone number is required'
                ], 422);
            }

            // Check for duplicate Personal phone types (only one Personal phone allowed)
            $personalPhoneCount = 0;
            foreach ($phoneNumbers as $phoneData) {
                if (isset($phoneData['contact_type']) && $phoneData['contact_type'] === 'Personal') {
                    $personalPhoneCount++;
                }
            }
            if ($personalPhoneCount > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only one phone number can be marked as Personal'
                ], 422);
            }

            // Validate each phone number and check for duplicates within the same client
            foreach ($phoneNumbers as $index => $phoneData) {
                if (!empty($phoneData['phone'])) {
                    $contactType = $phoneData['contact_type'] ?? null;
                    $phone = $phoneData['phone'];
                    $countryCode = PhoneHelper::formatForStorage($phoneData['country_code'] ?? '');

                    if ($countryCode === '') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Phone number '.($index + 1).': please select a valid country code.',
                        ], 422);
                    }

                    // Use centralized validation
                    $validation = \App\Helpers\PhoneValidationHelper::validatePhoneNumber($phone);
                    if (!$validation['valid']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Phone number ' . ($index + 1) . ': ' . $validation['message']
                        ], 422);
                    }

                    // Skip duplicate check for placeholder numbers
                    if (!$validation['is_placeholder']) {
                        // Check for duplicate phone numbers within the same client
                        // Convert empty string to null for proper handling
                        $contactIdForCheck = !empty($phoneData['id']) ? $phoneData['id'] : null;
                        
                        $duplicatePhoneQuery = ClientContact::where('phone', $phone)
                            ->where('country_code', $countryCode)
                            ->where('client_id', $client->id);
                        
                        // Only exclude current contact ID if it's a valid ID (not empty/null)
                        if ($contactIdForCheck) {
                            $duplicatePhoneQuery->where('id', '!=', $contactIdForCheck);
                        }
                        
                        $duplicatePhone = $duplicatePhoneQuery->first();

                        if ($duplicatePhone) {
                            return response()->json([
                                'success' => false,
                                'message' => 'This phone number is already taken for this client: ' . $countryCode . $phone
                            ], 422);
                        }
                    }
                }
            }

            // Get existing phone numbers before update for change tracking
            $existingPhones = ClientContact::where('client_id', $client->id)->get()->keyBy('id');
            $oldPhoneDisplay = [];
            foreach ($existingPhones as $existing) {
                $display = ($existing->country_code ? $existing->country_code : '') . $existing->phone;
                if ($existing->contact_type) {
                    $display .= ' (' . $existing->contact_type . ')';
                }
                $oldPhoneDisplay[] = $display;
            }
            $oldPhoneDisplayStr = !empty($oldPhoneDisplay) ? implode(', ', $oldPhoneDisplay) : '(empty)';

            // Handle special cases for duplicate phone (Option 2: Add timestamp only when duplicate exists)
            $timestamp = time();

            // Process phone numbers with proper update/insert logic (like the old working system)
            $processedPhones = [];
            foreach ($phoneNumbers as $phoneData) {
                if (!empty($phoneData['phone'])) {
                    // Convert empty string to null for proper handling
                    $contactId = !empty($phoneData['id']) ? $phoneData['id'] : null;
                    $contactType = $phoneData['contact_type'] ?? null;
                    $phone = $phoneData['phone'];
                    $countryCode = PhoneHelper::formatForStorage($phoneData['country_code'] ?? '');
                    
                    // Check for duplicates across all clients and handle universal number (4444444444)
                    // Check in admins table (excluding current client)
                    $existingPhoneInAdmins = Admin::where('phone', $phone)
                        ->where('id', '!=', $client->id)
                        ->first();
                    
                    // Check in client_contacts table (excluding current client and current contact)
                    $existingPhoneInContacts = ClientContact::where('phone', $phone)
                        ->where('country_code', $countryCode)
                        ->where('client_id', '!=', $client->id)
                        ->when($contactId, function($q) use ($contactId) {
                            return $q->where('id', '!=', $contactId);
                        })
                        ->first();
                    
                    // If duplicate exists and it's a universal number, add timestamp
                    if (($existingPhoneInAdmins || $existingPhoneInContacts) && $phone === '4444444444') {
                        $phone = $phone . '_' . $timestamp;
                        Log::info('Phone number modified to: ' . $phone);
                    } else if ($existingPhoneInAdmins || $existingPhoneInContacts) {
                        // Non-universal duplicate - check if it's within the same client (allowed)
                        $duplicateInSameClient = ClientContact::where('phone', $phone)
                            ->where('country_code', $countryCode)
                            ->where('client_id', $client->id)
                            ->when($contactId, function($q) use ($contactId) {
                                return $q->where('id', '!=', $contactId);
                            })
                            ->first();
                        
                        // Only error if duplicate is in a different client
                        if (!$duplicateInSameClient) {
                            return response()->json([
                                'success' => false,
                                'message' => "Phone number '{$countryCode}{$phoneData['phone']}' is already registered for another client."
                            ], 422);
                        }
                    }

                    if ($contactId) {
                        // Update existing contact if ID is provided
                        $existingContact = ClientContact::find($contactId);
                        if ($existingContact && $existingContact->client_id == $client->id) {
                            $existingContact->update([
                                'admin_id' => Auth::user()->id,
                                'contact_type' => $contactType,
                                'phone' => $phone,
                                'country_code' => $countryCode
                            ]);
                            $processedPhones[] = $existingContact->id;
                        }
                    } else {
                        // Insert new contact if no ID is provided
                        $newContact = ClientContact::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $client->id,
                            'contact_type' => $contactType,
                            'phone' => $phone,
                            'country_code' => $countryCode,
                            'is_verified' => false
                        ]);
                        $processedPhones[] = $newContact->id;
                    }
                }
            }

            // Remove any phone numbers that were not in the processed list (like the old system)
            if (!empty($processedPhones)) {
                ClientContact::where('client_id', $client->id)
                    ->whereNotIn('id', $processedPhones)
                    ->delete();
            }

            // Update client's primary phone info (like the old system)
            // Get the last phone from processed contacts (to ensure we use modified values if any)
            $lastPhone = null;
            $lastContactType = null;
            $lastCountryCode = null;
            
            if (!empty($processedPhones)) {
                // Get the last processed phone contact to use its values (which may have been modified)
                $lastContact = ClientContact::where('client_id', $client->id)
                    ->whereIn('id', $processedPhones)
                    ->orderBy('id', 'desc')
                    ->first();
                
                if ($lastContact) {
                    $lastPhone = $lastContact->phone;
                    $lastContactType = $lastContact->contact_type;
                    $lastCountryCode = $lastContact->country_code;
                }
            }
            
            // Fallback to last phone in array if no processed phones found
            if (!$lastPhone && !empty($phoneNumbers)) {
                $lastPhoneData = end($phoneNumbers);
                if (!empty($lastPhoneData['phone'])) {
                    $lastPhone = $lastPhoneData['phone'];
                    $lastContactType = $lastPhoneData['contact_type'] ?? null;
                    $lastCountryCode = PhoneHelper::formatForStorage($lastPhoneData['country_code'] ?? '');
                }
            }

            if ($lastPhone) {
                $client->phone = $lastPhone;
                $client->contact_type = $lastContactType;
                $client->country_code = $lastCountryCode;
                $client->save();
            }

            // Get new phone numbers for change tracking
            $newPhones = ClientContact::where('client_id', $client->id)->get();
            $newPhoneDisplay = [];
            foreach ($newPhones as $newPhone) {
                $display = ($newPhone->country_code ? $newPhone->country_code : '') . $newPhone->phone;
                if ($newPhone->contact_type) {
                    $display .= ' (' . $newPhone->contact_type . ')';
                }
                $newPhoneDisplay[] = $display;
            }
            $newPhoneDisplayStr = !empty($newPhoneDisplay) ? implode(', ', $newPhoneDisplay) : '(empty)';

            // Log activity with before/after values
            $changedFields = [];
            if ($oldPhoneDisplayStr !== $newPhoneDisplayStr) {
                $changedFields['Phone Numbers'] = [
                    'old' => $oldPhoneDisplayStr,
                    'new' => $newPhoneDisplayStr
                ];
            }

            if (!empty($changedFields)) {
                $this->logClientActivityWithChanges(
                    $client->id,
                    'updated phone numbers',
                    $changedFields,
                    'activity'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Phone numbers updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving phone numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveEmailAddressesSection($request, $client)
    {
        try {
            $emails = json_decode($request->input('emails'), true);
            
            if (!is_array($emails)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email addresses data'
                ], 400);
            }

            // Get existing emails before update for change tracking
            $existingEmails = ClientEmail::where('client_id', $client->id)->get();
            $oldEmailDisplay = [];
            foreach ($existingEmails as $existing) {
                $display = $existing->email;
                if ($existing->email_type) {
                    $display .= ' (' . $existing->email_type . ')';
                }
                $oldEmailDisplay[] = $display;
            }
            $oldEmailDisplayStr = !empty($oldEmailDisplay) ? implode(', ', $oldEmailDisplay) : '(empty)';

            // Handle special cases for duplicate email (Option 2: Add timestamp only when duplicate exists)
            $timestamp = time();

            // Track which email IDs should be kept (both updated and newly created)
            $emailIdsToKeep = [];
            $primaryEmail = null;
            $primaryEmailType = 'Personal';

            // Process each email record (update existing or create new)
            foreach ($emails as $emailData) {
                if (!empty($emailData['email'])) {
                    $email = $emailData['email'];
                    $emailId = $emailData['id'] ?? null;
                    $emailId = !empty($emailId) ? (int)$emailId : null;
                    
                    // Check for duplicates and handle universal number (demo@gmail.com)
                    // Check in admins table (excluding current client)
                    $existingEmailInAdmins = Admin::where('email', $email)
                        ->where('id', '!=', $client->id)
                        ->first();
                    
                    // Check in client_emails table (excluding current client and current email)
                    $existingEmailInClientEmails = ClientEmail::where('email', $email)
                        ->where('client_id', '!=', $client->id)
                        ->when($emailId, function($q) use ($emailId) {
                            return $q->where('id', '!=', $emailId);
                        })
                        ->first();
                    
                    // If duplicate exists and it's a universal number, add timestamp
                    if (($existingEmailInAdmins || $existingEmailInClientEmails) && $email === 'demo@gmail.com') {
                        $emailParts = explode('@', $email);
                        $localPart = $emailParts[0];
                        $domainPart = $emailParts[1];
                        $email = $localPart . '_' . $timestamp . '@' . $domainPart;
                        Log::info('Email address modified to: ' . $email);
                    } else if ($existingEmailInAdmins || $existingEmailInClientEmails) {
                        // Non-universal duplicate - check if it's within the same client (allowed)
                        $duplicateInSameClient = ClientEmail::where('email', $email)
                            ->where('client_id', $client->id)
                            ->when($emailId, function($q) use ($emailId) {
                                return $q->where('id', '!=', $emailId);
                            })
                            ->first();
                        
                        // Only error if duplicate is in a different client
                        if (!$duplicateInSameClient) {
                            return response()->json([
                                'success' => false,
                                'message' => "Email address '{$emailData['email']}' is already registered for another client."
                            ], 422);
                        }
                    }
                    
                    if ($emailId) {
                        // Update existing email if ID is provided
                        $existingEmail = ClientEmail::find($emailId);
                        if ($existingEmail && $existingEmail->client_id == $client->id) {
                            $existingEmail->update([
                                'admin_id' => Auth::user()->id,
                                'email_type' => $emailData['email_type'],
                                'email' => $email // Use potentially modified email
                            ]);
                            $emailIdsToKeep[] = $emailId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newEmail = ClientEmail::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'email_type' => $emailData['email_type'],
                                'email' => $email, // Use potentially modified email
                                'is_verified' => false
                            ]);
                            $emailIdsToKeep[] = $newEmail->id;
                        }
                    } else {
                        // Create new email
                        $newEmail = ClientEmail::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'email_type' => $emailData['email_type'],
                            'email' => $email, // Use potentially modified email
                            'is_verified' => false
                        ]);
                        $emailIdsToKeep[] = $newEmail->id;
                    }
                    
                    // Set primary email for admins table update (use potentially modified email)
                    if ($emailData['email_type'] === 'Personal' || empty($primaryEmail)) {
                        $primaryEmail = $email;
                        $primaryEmailType = $emailData['email_type'];
                    }
                }
            }
            
            // Delete email records that were not in the request
            if (!empty($emailIdsToKeep)) {
                ClientEmail::where('client_id', $client->id)
                    ->whereNotIn('id', $emailIdsToKeep)
                    ->delete();
            }
            
            // Update admins table with primary email
            if (!empty($primaryEmail)) {
                $client->email = $primaryEmail;
                $client->email_type = $primaryEmailType;
                $client->save();
            }

            // Get new emails for change tracking
            $newEmails = ClientEmail::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildEmailDiff($existingEmails, $newEmails);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatEmailDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated email addresses',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Email diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newEmailDisplay = [];
                foreach ($newEmails as $newEmail) {
                    $display = $newEmail->email;
                    if ($newEmail->email_type) {
                        $display .= ' (' . $newEmail->email_type . ')';
                    }
                    $newEmailDisplay[] = $display;
                }
                $newEmailDisplayStr = !empty($newEmailDisplay) ? implode(', ', $newEmailDisplay) : '(empty)';
                
                if ($oldEmailDisplayStr !== $newEmailDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated email addresses',
                        ['Email Addresses' => [
                            'old' => $oldEmailDisplayStr,
                            'new' => $newEmailDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            // Get the newly saved emails with their IDs
            $savedEmails = ClientEmail::where('client_id', $client->id)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function($email) {
                    return [
                        'id' => $email->id,
                        'email' => $email->email,
                        'email_type' => $email->email_type,
                        'is_verified' => $email->is_verified,
                        'verified_at' => $email->verified_at
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Email addresses updated successfully',
                'emails' => $savedEmails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving email addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    private function savePassportInfoSection($request, $client)
    {
        try {
            $passports = json_decode($request->input('passports'), true);
            
            if (!is_array($passports)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid passport data'
                ], 400);
            }

            // Get existing passports before update for change tracking
            $existingPassports = ClientPassportInformation::where('client_id', $client->id)->get();
            $oldPassportDisplay = [];
            foreach ($existingPassports as $existing) {
                $display = [];
                if ($existing->passport_country) {
                    $display[] = 'Country: ' . $existing->passport_country;
                }
                if ($existing->passport) {
                    $display[] = 'Number: ' . $existing->passport;
                }
                if ($existing->passport_issue_date) {
                    $display[] = 'Issue: ' . date('d/m/Y', strtotime($existing->passport_issue_date));
                }
                if ($existing->passport_expiry_date) {
                    $display[] = 'Expiry: ' . date('d/m/Y', strtotime($existing->passport_expiry_date));
                }
                $oldPassportDisplay[] = !empty($display) ? implode(', ', $display) : 'Passport record';
            }
            $oldPassportDisplayStr = !empty($oldPassportDisplay) ? implode(' | ', $oldPassportDisplay) : '(empty)';

            // Track which passport IDs should be kept (both updated and newly created)
            $passportIdsToKeep = [];

            // Process each passport record (update existing or create new)
            foreach ($passports as $passportData) {
                if (!empty($passportData['passport_number']) || !empty($passportData['passport_country'])) {
                    // Convert date format from d/m/Y to Y-m-d if needed
                    $issueDate = null;
                    $expiryDate = null;
                    
                    if (!empty($passportData['issue_date'])) {
                        $issueDateObj = \DateTime::createFromFormat('d/m/Y', $passportData['issue_date']);
                        if ($issueDateObj && Carbon::instance($issueDateObj)->isFuture()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'The document issue date cannot be a future date.'
                            ], 422);
                        }
                        $issueDate = $issueDateObj ? $issueDateObj->format('Y-m-d') : null;
                    }
                    
                    if (!empty($passportData['expiry_date'])) {
                        $expiryDate = \DateTime::createFromFormat('d/m/Y', $passportData['expiry_date']);
                        $expiryDate = $expiryDate ? $expiryDate->format('Y-m-d') : null;
                    }
                    
                    $passportId = $passportData['id'] ?? null;
                    $passportId = !empty($passportId) ? (int)$passportId : null;
                    
                    if ($passportId) {
                        // Update existing passport
                        $existingPassport = ClientPassportInformation::find($passportId);
                        if ($existingPassport && $existingPassport->client_id == $client->id) {
                            $existingPassport->update([
                                'admin_id' => Auth::user()->id,
                                'passport_country' => $passportData['passport_country'] ?? null,
                                'passport' => $passportData['passport_number'] ?? null,
                                'passport_issue_date' => $issueDate,
                                'passport_expiry_date' => $expiryDate
                            ]);
                            $passportIdsToKeep[] = $passportId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newPassport = ClientPassportInformation::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'passport_country' => $passportData['passport_country'] ?? null,
                                'passport' => $passportData['passport_number'] ?? null,
                                'passport_issue_date' => $issueDate,
                                'passport_expiry_date' => $expiryDate
                            ]);
                            $passportIdsToKeep[] = $newPassport->id;
                        }
                    } else {
                        // Create new passport
                        $newPassport = ClientPassportInformation::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'passport_country' => $passportData['passport_country'] ?? null,
                            'passport' => $passportData['passport_number'] ?? null,
                            'passport_issue_date' => $issueDate,
                            'passport_expiry_date' => $expiryDate
                        ]);
                        $passportIdsToKeep[] = $newPassport->id;
                    }
                }
            }
            
            // Delete passport records that were not in the request
            if (!empty($passportIdsToKeep)) {
                ClientPassportInformation::where('client_id', $client->id)
                    ->whereNotIn('id', $passportIdsToKeep)
                    ->delete();
            }

            // Get the first passport's country as the primary passport country
            $primaryPassportCountry = null;
            $firstPassport = ClientPassportInformation::where('client_id', $client->id)->first();
            if ($firstPassport && !empty($firstPassport->passport_country)) {
                $primaryPassportCountry = $firstPassport->passport_country;
            }

            // Update client's primary passport country (column name is country_passport)
            // Always update the country_passport field - set to null if no passports exist
            $client->country_passport = $primaryPassportCountry;
            $client->save();

            // Get new passports for change tracking
            $newPassports = ClientPassportInformation::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildPassportDiff($existingPassports, $newPassports);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatPassportDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated passport information',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Passport diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newPassportDisplay = [];
                foreach ($newPassports as $newPassport) {
                    $display = [];
                    if ($newPassport->passport_country) {
                        $display[] = 'Country: ' . $newPassport->passport_country;
                    }
                    if ($newPassport->passport) {
                        $display[] = 'Number: ' . $newPassport->passport;
                    }
                    if ($newPassport->passport_issue_date) {
                        $display[] = 'Issue: ' . date('d/m/Y', strtotime($newPassport->passport_issue_date));
                    }
                    if ($newPassport->passport_expiry_date) {
                        $display[] = 'Expiry: ' . date('d/m/Y', strtotime($newPassport->passport_expiry_date));
                    }
                    $newPassportDisplay[] = !empty($display) ? implode(', ', $display) : 'Passport record';
                }
                $newPassportDisplayStr = !empty($newPassportDisplay) ? implode(' | ', $newPassportDisplay) : '(empty)';
                
                if ($oldPassportDisplayStr !== $newPassportDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated passport information',
                        ['Passport Information' => [
                            'old' => $oldPassportDisplayStr,
                            'new' => $newPassportDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Passport information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving passport information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveVisaInfoSection($request, $client)
    {
        try {
            $visaExpiryVerified = $request->input('visa_expiry_verified');
            $visas = json_decode($request->input('visas'), true);
            
            if (!is_array($visas)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid visa data'
                ], 400);
            }

            // Get existing visas before update for change tracking
            $existingVisas = ClientVisaCountry::where('client_id', $client->id)->get();
            $oldVisaDisplay = [];
            foreach ($existingVisas as $existing) {
                $display = [];
                if ($existing->visa_type) {
                    $display[] = 'Type: ' . $existing->visa_type;
                }
                if ($client->country_passport) {
                    $display[] = 'Country: ' . $client->country_passport;
                }
                if ($existing->visa_grant_date) {
                    $display[] = 'Grant: ' . date('d/m/Y', strtotime($existing->visa_grant_date));
                }
                if ($existing->visa_expiry_date) {
                    $display[] = 'Expiry: ' . date('d/m/Y', strtotime($existing->visa_expiry_date));
                }
                if ($existing->visa_description) {
                    $display[] = 'Desc: ' . $existing->visa_description;
                }
                $oldVisaDisplay[] = !empty($display) ? implode(', ', $display) : 'Visa record';
            }
            $oldVisaDisplayStr = !empty($oldVisaDisplay) ? implode(' | ', $oldVisaDisplay) : '(empty)';

            // Update client's visa expiry verified status using existing system
            if ($visaExpiryVerified === '1') {
                $client->visa_expiry_verified_at = now();
                $client->visa_expiry_verified_by = Auth::user()->id;
            } else {
                $client->visa_expiry_verified_at = null;
                $client->visa_expiry_verified_by = null;
            }
            $client->save();

            // Track which visa IDs should be kept (both updated and newly created)
            $visaIdsToKeep = [];

            // Process each visa record (update existing or create new)
            foreach ($visas as $visaData) {
                if (!empty($visaData['visa_type_hidden'])) {
                    // Convert date format from d/m/Y to Y-m-d if needed
                    $expiryDate = null;
                    $grantDate = null;
                    
                    if (!empty($visaData['visa_expiry_date'])) {
                        $expiryDate = \DateTime::createFromFormat('d/m/Y', $visaData['visa_expiry_date']);
                        $expiryDate = $expiryDate ? $expiryDate->format('Y-m-d') : null;
                    }
                    
                    if (!empty($visaData['visa_grant_date'])) {
                        $grantDateObj = \DateTime::createFromFormat('d/m/Y', $visaData['visa_grant_date']);
                        if ($grantDateObj && Carbon::instance($grantDateObj)->isFuture()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'The visa grant date cannot be a future date.'
                            ], 422);
                        }
                        $grantDate = $grantDateObj ? $grantDateObj->format('Y-m-d') : null;
                    }
                    
                    $visaId = $visaData['id'] ?? null;
                    $visaId = !empty($visaId) ? (int)$visaId : null;
                    
                    if ($visaId) {
                        // Update existing visa
                        $existingVisa = ClientVisaCountry::find($visaId);
                        if ($existingVisa && $existingVisa->client_id == $client->id) {
                            $existingVisa->update([
                                'admin_id' => Auth::user()->id,
                                'visa_type' => $visaData['visa_type_hidden'],
                                'visa_expiry_date' => $expiryDate,
                                'visa_grant_date' => $grantDate,
                                'visa_description' => $visaData['visa_description'] ?? null
                            ]);
                            $visaIdsToKeep[] = $visaId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newVisa = ClientVisaCountry::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'visa_type' => $visaData['visa_type_hidden'],
                                'visa_expiry_date' => $expiryDate,
                                'visa_grant_date' => $grantDate,
                                'visa_description' => $visaData['visa_description'] ?? null
                            ]);
                            $visaIdsToKeep[] = $newVisa->id;
                        }
                    } else {
                        // Create new visa
                        $newVisa = ClientVisaCountry::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'visa_type' => $visaData['visa_type_hidden'],
                            'visa_expiry_date' => $expiryDate,
                            'visa_grant_date' => $grantDate,
                            'visa_description' => $visaData['visa_description'] ?? null
                        ]);
                        $visaIdsToKeep[] = $newVisa->id;
                    }
                }
            }
            
            // Delete visa records that were not in the request
            if (!empty($visaIdsToKeep)) {
                ClientVisaCountry::where('client_id', $client->id)
                    ->whereNotIn('id', $visaIdsToKeep)
                    ->delete();
            }

            // Get new visas for change tracking
            $newVisas = ClientVisaCountry::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildVisaDiff($existingVisas, $newVisas);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatVisaDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated visa information',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Visa diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newVisaDisplay = [];
                foreach ($newVisas as $newVisa) {
                    $display = [];
                    if ($newVisa->visa_type) {
                        $display[] = 'Type: ' . $newVisa->visa_type;
                    }
                    if ($client->country_passport) {
                        $display[] = 'Country: ' . $client->country_passport;
                    }
                    if ($newVisa->visa_grant_date) {
                        $display[] = 'Grant: ' . date('d/m/Y', strtotime($newVisa->visa_grant_date));
                    }
                    if ($newVisa->visa_expiry_date) {
                        $display[] = 'Expiry: ' . date('d/m/Y', strtotime($newVisa->visa_expiry_date));
                    }
                    if ($newVisa->visa_description) {
                        $display[] = 'Desc: ' . $newVisa->visa_description;
                    }
                    $newVisaDisplay[] = !empty($display) ? implode(', ', $display) : 'Visa record';
                }
                $newVisaDisplayStr = !empty($newVisaDisplay) ? implode(' | ', $newVisaDisplay) : '(empty)';
                
                if ($oldVisaDisplayStr !== $newVisaDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated visa information',
                        ['Visa Information' => [
                            'old' => $oldVisaDisplayStr,
                            'new' => $newVisaDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Visa information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving visa information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveAddressInfoSection($request, $client)
    {
        try {
            $requestData = $request->all();
            
            Log::info('Address save request data:', $requestData);
            
            // Get existing addresses before update for change tracking
            $existingAddresses = ClientAddress::where('client_id', $client->id)->get();
            $existingAddressCount = $existingAddresses->count(); // Track count for safety check
            $oldAddressDisplay = [];
            foreach ($existingAddresses as $existing) {
                $display = [];
                if ($existing->address_line_1) {
                    $display[] = $existing->address_line_1;
                }
                if ($existing->suburb) {
                    $display[] = $existing->suburb;
                }
                if ($existing->state) {
                    $display[] = $existing->state;
                }
                if ($existing->zip) {
                    $display[] = $existing->zip;
                }
                if ($existing->country) {
                    $display[] = $existing->country;
                }
                if ($existing->start_date) {
                    $display[] = 'From: ' . date('d/m/Y', strtotime($existing->start_date));
                }
                if ($existing->end_date) {
                    $display[] = 'To: ' . date('d/m/Y', strtotime($existing->end_date));
                }
                $oldAddressDisplay[] = !empty($display) ? implode(', ', $display) : 'Address record';
            }
            $oldAddressDisplayStr = !empty($oldAddressDisplay) ? implode(' | ', $oldAddressDisplay) : '(empty)';
            
            if (isset($requestData['zip']) && is_array($requestData['zip'])) {
                // Track which address IDs should be kept (both updated and newly created)
                $addressIdsToKeep = [];
                
                // Process each address in the request
                foreach ($requestData['zip'] as $key => $zip) {
                    $address_line_1 = $requestData['address_line_1'][$key] ?? null;
                    $address_line_2 = $requestData['address_line_2'][$key] ?? null;
                    $suburb = $requestData['suburb'][$key] ?? null;
                    $state = $requestData['state'][$key] ?? null;
                    $country = $requestData['country'][$key] ?? 'Australia';
                    $regional_code = $requestData['regional_code'][$key] ?? null;
                    $start_date = $requestData['address_start_date'][$key] ?? null;
                    $end_date = $requestData['address_end_date'][$key] ?? null;
                    $address_id = $requestData['address_id'][$key] ?? null;
                    
                    // Clean up address_id - it might be empty string, null, or actual ID
                    $address_id = !empty($address_id) ? (int)$address_id : null;
                    
                    Log::info("Processing address entry $key:", [
                        'address_id' => $address_id ?: '(new)',
                        'zip' => $zip,
                        'address_line_1' => $address_line_1,
                        'suburb' => $suburb,
                        'state' => $state,
                        'country' => $country,
                        'regional_code' => $regional_code,
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ]);
                    
                    // Skip empty addresses (no address_line_1 and no zip)
                    if (empty($address_line_1) && empty($zip)) {
                        continue;
                    }
                    
                    // Date conversion
                    $formatted_start_date = null;
                    if (!empty($start_date)) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $start_date);
                            if ($date->isFuture()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'The address start date cannot be a future date.'
                                ], 422);
                            }
                            $formatted_start_date = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid Start Date format: ' . $start_date
                            ], 422);
                        }
                    }
                    
                    $formatted_end_date = null;
                    if (!empty($end_date)) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $end_date);
                            if ($date->isFuture()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'The address end date cannot be a future date.'
                                ], 422);
                            }
                            $formatted_end_date = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid End Date format: ' . $end_date
                            ], 422);
                        }
                    }
                    
                    // Create combined address for backward compatibility
                    $combined_address = $this->combineAddress([
                        'line1' => $address_line_1,
                        'line2' => $address_line_2,
                        'suburb' => $suburb,
                        'state' => $state,
                        'postcode' => $zip,
                        'country' => $country
                    ]);
                    
                    if ($address_id) {
                        // Update existing address
                        $existingAddress = ClientAddress::find($address_id);
                        if ($existingAddress && $existingAddress->client_id == $client->id) {
                            $existingAddress->update([
                                'admin_id' => Auth::user()->id,
                                'address' => $combined_address,
                                'address_line_1' => $address_line_1,
                                'address_line_2' => $address_line_2,
                                'suburb' => $suburb,
                                'state' => $state,
                                'country' => $country,
                                'zip' => $zip,
                                'regional_code' => $regional_code,
                                'start_date' => $formatted_start_date,
                                'end_date' => $formatted_end_date
                            ]);
                            // Track this ID to keep it
                            $addressIdsToKeep[] = $address_id;
                            Log::info("Updated address ID: $address_id");
                        } else {
                            // Address ID provided but doesn't exist or doesn't belong to client
                            // Create as new address instead
                            $newAddress = ClientAddress::create([
                                'admin_id' => Auth::user()->id,
                                'client_id' => $client->id,
                                'address' => $combined_address,
                                'address_line_1' => $address_line_1,
                                'address_line_2' => $address_line_2,
                                'suburb' => $suburb,
                                'state' => $state,
                                'country' => $country,
                                'zip' => $zip,
                                'regional_code' => $regional_code,
                                'start_date' => $formatted_start_date,
                                'end_date' => $formatted_end_date
                            ]);
                            // Track newly created ID to keep it
                            $addressIdsToKeep[] = $newAddress->id;
                            Log::info("Created new address (invalid ID provided), new ID: {$newAddress->id}");
                        }
                    } else {
                        // Create new address (no ID provided)
                        $newAddress = ClientAddress::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $client->id,
                            'address' => $combined_address,
                            'address_line_1' => $address_line_1,
                            'address_line_2' => $address_line_2,
                            'suburb' => $suburb,
                            'state' => $state,
                            'country' => $country,
                            'zip' => $zip,
                            'regional_code' => $regional_code,
                            'start_date' => $formatted_start_date,
                            'end_date' => $formatted_end_date
                        ]);
                        // Track newly created ID to keep it
                        $addressIdsToKeep[] = $newAddress->id;
                        Log::info("Created new address, ID: {$newAddress->id}");
                    }
                }
                
                // Delete addresses that exist in DB but were not processed/created
                // This handles the case where user removes an address from the form
                Log::info('Address IDs to keep:', $addressIdsToKeep);
                
                // CRITICAL SAFETY CHECK: Prevent accidental deletion of all addresses
                // If there were existing addresses but $addressIdsToKeep is empty after processing,
                // this indicates all submitted addresses were empty (skipped). This is suspicious
                // and could indicate a bug or accidental empty form submission - prevent deletion.
                if (!empty($addressIdsToKeep)) {
                    $deletedCount = ClientAddress::where('client_id', $client->id)
                        ->whereNotIn('id', $addressIdsToKeep)
                        ->delete();
                    Log::info("Deleted $deletedCount addresses that were not in the request");
                } elseif ($existingAddressCount > 0) {
                    // Security safeguard: If there were existing addresses but none to keep,
                    // this is suspicious - log warning and prevent deletion to avoid data loss
                    Log::warning("SECURITY: Prevented deletion of all {$existingAddressCount} addresses for client {$client->id}. " .
                        "No valid addresses in request - this may indicate an empty form submission or bug.");
                }

                // Current address = latest start_date (same order as Address Information UI)
                ClientAddress::syncIsCurrentForClient((int) $client->id);
            }
            
            // Get new addresses for change tracking
            $newAddresses = ClientAddress::where('client_id', $client->id)->get();
            $newAddressDisplay = [];
            foreach ($newAddresses as $newAddress) {
                $display = [];
                if ($newAddress->address_line_1) {
                    $display[] = $newAddress->address_line_1;
                }
                if ($newAddress->suburb) {
                    $display[] = $newAddress->suburb;
                }
                if ($newAddress->state) {
                    $display[] = $newAddress->state;
                }
                if ($newAddress->zip) {
                    $display[] = $newAddress->zip;
                }
                if ($newAddress->country) {
                    $display[] = $newAddress->country;
                }
                if ($newAddress->start_date) {
                    $display[] = 'From: ' . date('d/m/Y', strtotime($newAddress->start_date));
                }
                if ($newAddress->end_date) {
                    $display[] = 'To: ' . date('d/m/Y', strtotime($newAddress->end_date));
                }
                $newAddressDisplay[] = !empty($display) ? implode(', ', $display) : 'Address record';
            }
            $newAddressDisplayStr = !empty($newAddressDisplay) ? implode(' | ', $newAddressDisplay) : '(empty)';

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildAddressDiff($existingAddresses, $newAddresses);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    // Build HTML directly with proper formatting
                    $description = $this->formatAddressDiffForActivityLog($diffResult);
                    
                    Log::info('Creating activity log for address change', [
                        'added' => count($diffResult['added']),
                        'removed' => count($diffResult['removed']),
                        'modified' => count($diffResult['modified'])
                    ]);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated address information',
                        $description,
                        'activity'
                    );
                } else {
                    Log::info('No activity log created - addresses are identical');
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Address diff failed, using simple comparison', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                if ($oldAddressDisplayStr !== $newAddressDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated address information',
                        ['Address Information' => [
                            'old' => $oldAddressDisplayStr,
                            'new' => $newAddressDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Address information updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving address information: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error saving address information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveTravelInfoSection($request, $client)
    {
        try {
            $travels = json_decode($request->input('travels'), true);
            
            if (!is_array($travels)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid travel data'
                ], 400);
            }

            // Get existing travels BEFORE update for change tracking
            $existingTravels = ClientTravelInformation::where('client_id', $client->id)->get();
            $oldTravelDisplay = [];
            foreach ($existingTravels as $existing) {
                $display = [];
                if ($existing->travel_country_visited) {
                    $display[] = 'Country: ' . $existing->travel_country_visited;
                }
                if ($existing->travel_arrival_date) {
                    $display[] = 'Arrival: ' . date('d/m/Y', strtotime($existing->travel_arrival_date));
                }
                if ($existing->travel_departure_date) {
                    $display[] = 'Departure: ' . date('d/m/Y', strtotime($existing->travel_departure_date));
                }
                if ($existing->travel_purpose) {
                    $display[] = 'Purpose: ' . $existing->travel_purpose;
                }
                $oldTravelDisplay[] = !empty($display) ? implode(', ', $display) : 'Travel record';
            }
            $oldTravelDisplayStr = !empty($oldTravelDisplay) ? implode(' | ', $oldTravelDisplay) : '(empty)';

            // Track which travel IDs should be kept (both updated and newly created)
            $travelIdsToKeep = [];

            // Process each travel record (update existing or create new)
            foreach ($travels as $travelData) {
                if (!empty($travelData['country_visited'])) {
                    // Convert date format from d/m/Y to Y-m-d if needed
                    $arrivalDate = null;
                    $departureDate = null;
                    
                    if (!empty($travelData['arrival_date'])) {
                        $arrivalDate = \DateTime::createFromFormat('d/m/Y', $travelData['arrival_date']);
                        $arrivalDate = $arrivalDate ? $arrivalDate->format('Y-m-d') : null;
                    }
                    
                    if (!empty($travelData['departure_date'])) {
                        $departureDate = \DateTime::createFromFormat('d/m/Y', $travelData['departure_date']);
                        $departureDate = $departureDate ? $departureDate->format('Y-m-d') : null;
                    }
                    
                    $travelId = $travelData['id'] ?? null;
                    $travelId = !empty($travelId) ? (int)$travelId : null;
                    
                    if ($travelId) {
                        // Update existing travel
                        $existingTravel = ClientTravelInformation::find($travelId);
                        if ($existingTravel && $existingTravel->client_id == $client->id) {
                            $existingTravel->update([
                                'travel_country_visited' => $travelData['country_visited'],
                                'travel_arrival_date' => $arrivalDate,
                                'travel_departure_date' => $departureDate,
                                'travel_purpose' => $travelData['purpose'] ?? null
                            ]);
                            $travelIdsToKeep[] = $travelId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newTravel = ClientTravelInformation::create([
                                'client_id' => $client->id,
                                'travel_country_visited' => $travelData['country_visited'],
                                'travel_arrival_date' => $arrivalDate,
                                'travel_departure_date' => $departureDate,
                                'travel_purpose' => $travelData['purpose'] ?? null
                            ]);
                            $travelIdsToKeep[] = $newTravel->id;
                        }
                    } else {
                        // Create new travel
                        $newTravel = ClientTravelInformation::create([
                            'client_id' => $client->id,
                            'travel_country_visited' => $travelData['country_visited'],
                            'travel_arrival_date' => $arrivalDate,
                            'travel_departure_date' => $departureDate,
                            'travel_purpose' => $travelData['purpose'] ?? null
                        ]);
                        $travelIdsToKeep[] = $newTravel->id;
                    }
                }
            }
            
            // Delete travel records that were not in the request
            if (!empty($travelIdsToKeep)) {
                ClientTravelInformation::where('client_id', $client->id)
                    ->whereNotIn('id', $travelIdsToKeep)
                    ->delete();
            }

            // Get new travels for change tracking
            $newTravels = ClientTravelInformation::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildTravelDiff($existingTravels, $newTravels);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatTravelDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated travel information',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Travel diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newTravelDisplay = [];
                foreach ($newTravels as $newTravel) {
                    $display = [];
                    if ($newTravel->travel_country_visited) {
                        $display[] = 'Country: ' . $newTravel->travel_country_visited;
                    }
                    if ($newTravel->travel_arrival_date) {
                        $display[] = 'Arrival: ' . date('d/m/Y', strtotime($newTravel->travel_arrival_date));
                    }
                    if ($newTravel->travel_departure_date) {
                        $display[] = 'Departure: ' . date('d/m/Y', strtotime($newTravel->travel_departure_date));
                    }
                    if ($newTravel->travel_purpose) {
                        $display[] = 'Purpose: ' . $newTravel->travel_purpose;
                    }
                    $newTravelDisplay[] = !empty($display) ? implode(', ', $display) : 'Travel record';
                }
                $newTravelDisplayStr = !empty($newTravelDisplay) ? implode(' | ', $newTravelDisplay) : '(empty)';
                
                if ($oldTravelDisplayStr !== $newTravelDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated travel information',
                        ['Travel Information' => [
                            'old' => $oldTravelDisplayStr,
                            'new' => $newTravelDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Travel information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving travel information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveQualificationsInfoSection($request, $client)
    {
        try {
            $requestData = $request->all();
            
            // Get existing qualifications before update for change tracking
            $existingQualifications = ClientQualification::where('client_id', $client->id)->get();
            $oldQualDisplay = [];
            foreach ($existingQualifications as $existing) {
                $display = [];
                if ($existing->level) {
                    $display[] = 'Level: ' . $existing->level;
                }
                if ($existing->name) {
                    $display[] = 'Name: ' . $existing->name;
                }
                if ($existing->qual_college_name) {
                    $display[] = 'College: ' . $existing->qual_college_name;
                }
                if ($existing->country) {
                    $display[] = 'Country: ' . $existing->country;
                }
                if ($existing->start_date) {
                    $display[] = 'Start: ' . date('d/m/Y', strtotime($existing->start_date));
                }
                if ($existing->finish_date) {
                    $display[] = 'Finish: ' . date('d/m/Y', strtotime($existing->finish_date));
                }
                $oldQualDisplay[] = !empty($display) ? implode(', ', $display) : 'Qualification record';
            }
            $oldQualDisplayStr = !empty($oldQualDisplay) ? implode(' | ', $oldQualDisplay) : '(empty)';
            
            // Handle qualification deletion
            if (isset($requestData['delete_qualification_ids']) && is_array($requestData['delete_qualification_ids'])) {
                foreach ($requestData['delete_qualification_ids'] as $qualificationId) {
                    $qualification = ClientQualification::find($qualificationId);
                    if ($qualification && $qualification->client_id == $client->id) {
                        $qualification->delete();
                    }
                }
            }

            // Track which records were actually modified
            $actuallyModifiedCount = 0;
            $newRecordsCount = 0;
            
            // Handle qualification data
            if (isset($requestData['level']) && is_array($requestData['level'])) {
                foreach ($requestData['level'] as $key => $level) {
                    $name = $requestData['name'][$key] ?? null;
                    $qual_college_name = $requestData['qual_college_name'][$key] ?? null;
                    $qual_campus = $requestData['qual_campus'][$key] ?? null;
                    $country = $requestData['qual_country'][$key] ?? null;
                    $qual_state = $requestData['qual_state'][$key] ?? null;
                    $start = $requestData['start_date'][$key] ?? null;
                    $finish = $requestData['finish_date'][$key] ?? null;
                    $relevant_qualification = isset($requestData['relevant_qualification'][$key]) && $requestData['relevant_qualification'][$key] == 1 ? 1 : 0;
                    $qualificationId = $requestData['qualification_id'][$key] ?? null;

                    // Convert start_date from dd/mm/yyyy to Y-m-d for database storage
                    $formatted_start_date = null;
                    if (!empty($start)) {
                        try {
                            $startDate = \Carbon\Carbon::createFromFormat('d/m/Y', $start);
                            $formatted_start_date = $startDate->format('Y-m-d');
                        } catch (\Exception $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid Start Date format: ' . $start . '. Must be dd/mm/yyyy.'
                            ], 422);
                        }
                    }

                    // Convert finish_date from dd/mm/yyyy to Y-m-d for database storage
                    $formatted_finish_date = null;
                    if (!empty($finish)) {
                        try {
                            $finishDate = \Carbon\Carbon::createFromFormat('d/m/Y', $finish);
                            $formatted_finish_date = $finishDate->format('Y-m-d');
                        } catch (\Exception $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid Finish Date format: ' . $finish . '. Must be dd/mm/yyyy.'
                            ], 422);
                        }
                    }

                    // Only save if there's at least level or name
                    if (!empty($level) || !empty($name)) {
                        if ($qualificationId) {
                            // Update existing qualification
                            $existingQualification = ClientQualification::find($qualificationId);
                            if ($existingQualification && $existingQualification->client_id == $client->id) {
                                // Check if any field actually changed
                                $hasChanges = false;
                                if ($existingQualification->level != $level) $hasChanges = true;
                                if ($existingQualification->name != $name) $hasChanges = true;
                                if ($existingQualification->qual_college_name != $qual_college_name) $hasChanges = true;
                                if ($existingQualification->qual_campus != $qual_campus) $hasChanges = true;
                                if ($existingQualification->country != $country) $hasChanges = true;
                                if ($existingQualification->qual_state != $qual_state) $hasChanges = true;
                                if ($existingQualification->start_date != $formatted_start_date) $hasChanges = true;
                                if ($existingQualification->finish_date != $formatted_finish_date) $hasChanges = true;
                                if ($existingQualification->relevant_qualification != $relevant_qualification) $hasChanges = true;
                                
                                if ($hasChanges) {
                                    $actuallyModifiedCount++;
                                }
                                
                                $existingQualification->update([
                                    'admin_id' => Auth::user()->id,
                                    'level' => $level,
                                    'name' => $name,
                                    'qual_college_name' => $qual_college_name,
                                    'qual_campus' => $qual_campus,
                                    'country' => $country,
                                    'qual_state' => $qual_state,
                                    'start_date' => $formatted_start_date,
                                    'finish_date' => $formatted_finish_date,
                                    'relevant_qualification' => $relevant_qualification,
                                ]);
                            }
                        } else {
                            // Create new qualification
                            ClientQualification::create([
                                'admin_id' => Auth::user()->id,
                                'client_id' => $client->id,
                                'level' => $level,
                                'name' => $name,
                                'qual_college_name' => $qual_college_name,
                                'qual_campus' => $qual_campus,
                                'country' => $country,
                                'qual_state' => $qual_state,
                                'start_date' => $formatted_start_date,
                                'finish_date' => $formatted_finish_date,
                                'relevant_qualification' => $relevant_qualification,
                            ]);
                            $newRecordsCount++;
                        }
                    }
                }
            }

            // Update client's qualification_level and qualification_name with the most recent qualification
            if (isset($requestData['level']) && is_array($requestData['level'])) {
                $qualificationCount = count($requestData['level']);
                if ($qualificationCount > 0) {
                    $levelArray = array_values($requestData['level']);
                    $nameArray = array_values($requestData['name']);
                    
                    $lastLevel = $levelArray[$qualificationCount - 1] ?? null;
                    $lastName = $nameArray[$qualificationCount - 1] ?? null;

                    if (!empty($lastLevel) || !empty($lastName)) {
                        $client->qualification_level = $lastLevel;
                        $client->qualification_name = $lastName;
                        $client->save();
                    }
                }
            }

            // Get new qualifications for change tracking
            $newQualifications = ClientQualification::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildQualificationDiff($existingQualifications, $newQualifications);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatQualificationDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated educational qualifications',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Qualification diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newQualDisplay = [];
                foreach ($newQualifications as $newQual) {
                    $display = [];
                    if ($newQual->level) {
                        $display[] = 'Level: ' . $newQual->level;
                    }
                    if ($newQual->name) {
                        $display[] = 'Name: ' . $newQual->name;
                    }
                    if ($newQual->qual_college_name) {
                        $display[] = 'College: ' . $newQual->qual_college_name;
                    }
                    if ($newQual->country) {
                        $display[] = 'Country: ' . $newQual->country;
                    }
                    if ($newQual->start_date) {
                        $display[] = 'Start: ' . date('d/m/Y', strtotime($newQual->start_date));
                    }
                    if ($newQual->finish_date) {
                        $display[] = 'Finish: ' . date('d/m/Y', strtotime($newQual->finish_date));
                    }
                    $newQualDisplay[] = !empty($display) ? implode(', ', $display) : 'Qualification record';
                }
                $newQualDisplayStr = !empty($newQualDisplay) ? implode(' | ', $newQualDisplay) : '(empty)';
                
                if ($oldQualDisplayStr !== $newQualDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated educational qualifications',
                        ['Educational Qualifications' => [
                            'old' => $oldQualDisplayStr,
                            'new' => $newQualDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Qualifications information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save qualifications: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveExperienceInfoSection($request, $client)
    {
        try {
            $experiences = json_decode($request->input('experiences'), true);
            
            if (!is_array($experiences)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid experience data'
                ], 400);
            }

            // Get existing experiences before update for change tracking
            $existingExperiences = ClientExperience::where('client_id', $client->id)->get();
            $oldExpDisplay = [];
            foreach ($existingExperiences as $existing) {
                $display = [];
                if ($existing->job_title) {
                    $display[] = 'Title: ' . $existing->job_title;
                }
                if ($existing->job_code) {
                    $display[] = 'Code: ' . $existing->job_code;
                }
                if ($existing->job_emp_name) {
                    $display[] = 'Employer: ' . $existing->job_emp_name;
                }
                if ($existing->job_country) {
                    $display[] = 'Country: ' . $existing->job_country;
                }
                if ($existing->job_start_date) {
                    $display[] = 'Start: ' . date('d/m/Y', strtotime($existing->job_start_date));
                }
                if ($existing->job_finish_date) {
                    $display[] = 'Finish: ' . date('d/m/Y', strtotime($existing->job_finish_date));
                }
                $oldExpDisplay[] = !empty($display) ? implode(', ', $display) : 'Experience record';
            }
            $oldExpDisplayStr = !empty($oldExpDisplay) ? implode(' | ', $oldExpDisplay) : '(empty)';

            // Track which experience IDs should be kept (both updated and newly created)
            $experienceIdsToKeep = [];

            // Process each experience (update existing or create new)
            foreach ($experiences as $expData) {
                if (!empty($expData['job_title']) || !empty($expData['job_code']) || !empty($expData['job_emp_name'])) {
                    // Convert date format from d/m/Y to Y-m-d if needed
                    $startDate = null;
                    $endDate = null;
                    
                    if (!empty($expData['job_start_date'])) {
                        $startDateObj = \DateTime::createFromFormat('d/m/Y', $expData['job_start_date']);
                        if ($startDateObj && Carbon::instance($startDateObj)->isFuture()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'The employment start date cannot be a future date.'
                            ], 422);
                        }
                        $startDate = $startDateObj ? $startDateObj->format('Y-m-d') : null;
                    }
                    
                    if (!empty($expData['job_finish_date'])) {
                        $endDateObj = \DateTime::createFromFormat('d/m/Y', $expData['job_finish_date']);
                        if ($endDateObj && Carbon::instance($endDateObj)->isFuture()) {
                            return response()->json([
                                'success' => false,
                                'message' => 'The employment finish date cannot be a future date.'
                            ], 422);
                        }
                        $endDate = $endDateObj ? $endDateObj->format('Y-m-d') : null;
                    }
                    
                    $experienceId = $expData['id'] ?? null;
                    $experienceId = !empty($experienceId) ? (int)$experienceId : null;
                    
                    if ($experienceId) {
                        // Update existing experience
                        $existingExp = ClientExperience::find($experienceId);
                        if ($existingExp && $existingExp->client_id == $client->id) {
                            $existingExp->update([
                                'admin_id' => Auth::user()->id,
                                'job_title' => $expData['job_title'] ?? null,
                                'job_code' => $expData['job_code'] ?? null,
                                'job_country' => $expData['job_country'] ?? null,
                                'job_start_date' => $startDate,
                                'job_finish_date' => $endDate,
                                'relevant_experience' => $expData['relevant_experience'] ?? 0,
                                'job_emp_name' => $expData['job_emp_name'] ?? null,
                                'job_state' => $expData['job_state'] ?? null,
                                'job_type' => $expData['job_type'] ?? null
                            ]);
                            $experienceIdsToKeep[] = $experienceId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newExp = ClientExperience::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'job_title' => $expData['job_title'] ?? null,
                                'job_code' => $expData['job_code'] ?? null,
                                'job_country' => $expData['job_country'] ?? null,
                                'job_start_date' => $startDate,
                                'job_finish_date' => $endDate,
                                'relevant_experience' => $expData['relevant_experience'] ?? 0,
                                'job_emp_name' => $expData['job_emp_name'] ?? null,
                                'job_state' => $expData['job_state'] ?? null,
                                'job_type' => $expData['job_type'] ?? null,
                                'fte_multiplier' => 1.00
                            ]);
                            $experienceIdsToKeep[] = $newExp->id;
                        }
                    } else {
                        // Create new experience
                        $newExp = ClientExperience::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'job_title' => $expData['job_title'] ?? null,
                            'job_code' => $expData['job_code'] ?? null,
                            'job_country' => $expData['job_country'] ?? null,
                            'job_start_date' => $startDate,
                            'job_finish_date' => $endDate,
                            'relevant_experience' => $expData['relevant_experience'] ?? 0,
                            'job_emp_name' => $expData['job_emp_name'] ?? null,
                            'job_state' => $expData['job_state'] ?? null,
                            'job_type' => $expData['job_type'] ?? null,
                            'fte_multiplier' => 1.00
                        ]);
                        $experienceIdsToKeep[] = $newExp->id;
                    }
                }
            }
            
            // Delete experiences that were not in the request
            if (!empty($experienceIdsToKeep)) {
                ClientExperience::where('client_id', $client->id)
                    ->whereNotIn('id', $experienceIdsToKeep)
                    ->delete();
            }

            // Get new experiences for change tracking
            $newExperiences = ClientExperience::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildExperienceDiff($existingExperiences, $newExperiences);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatExperienceDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated work experience',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Experience diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newExpDisplay = [];
                foreach ($newExperiences as $newExp) {
                    $display = [];
                    if ($newExp->job_title) {
                        $display[] = 'Title: ' . $newExp->job_title;
                    }
                    if ($newExp->job_code) {
                        $display[] = 'Code: ' . $newExp->job_code;
                    }
                    if ($newExp->job_emp_name) {
                        $display[] = 'Employer: ' . $newExp->job_emp_name;
                    }
                    if ($newExp->job_country) {
                        $display[] = 'Country: ' . $newExp->job_country;
                    }
                    if ($newExp->job_start_date) {
                        $display[] = 'Start: ' . date('d/m/Y', strtotime($newExp->job_start_date));
                    }
                    if ($newExp->job_finish_date) {
                        $display[] = 'Finish: ' . date('d/m/Y', strtotime($newExp->job_finish_date));
                    }
                    $newExpDisplay[] = !empty($display) ? implode(', ', $display) : 'Experience record';
                }
                $newExpDisplayStr = !empty($newExpDisplay) ? implode(' | ', $newExpDisplay) : '(empty)';
                
                if ($oldExpDisplayStr !== $newExpDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated work experience',
                        ['Work Experience' => [
                            'old' => $oldExpDisplayStr,
                            'new' => $newExpDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Work experience updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving experience: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveAdditionalInfoSection($request, $client)
    {
        try {
            $naatiTest = $request->input('naati_test');
            $naatiDate = $request->input('naati_date');
            $pyTest = $request->input('py_test');
            $pyDate = $request->input('py_date');
            
            // New EOI qualification fields
            $australianStudy = $request->input('australian_study');
            $australianStudyDate = $request->input('australian_study_date');
            $specialistEducation = $request->input('specialist_education');
            $specialistEducationDate = $request->input('specialist_education_date');
            $regionalStudy = $request->input('regional_study');
            $regionalStudyDate = $request->input('regional_study_date');
            
            // Convert date format if needed
            $naatiDateFormatted = null;
            $pyDateFormatted = null;
            $australianStudyDateFormatted = null;
            $specialistEducationDateFormatted = null;
            $regionalStudyDateFormatted = null;
            
            if (!empty($naatiDate)) {
                $naatiDateObj = \DateTime::createFromFormat('d/m/Y', $naatiDate);
                $naatiDateFormatted = $naatiDateObj ? $naatiDateObj->format('Y-m-d') : null;
            }
            
            if (!empty($pyDate)) {
                $pyDateObj = \DateTime::createFromFormat('d/m/Y', $pyDate);
                $pyDateFormatted = $pyDateObj ? $pyDateObj->format('Y-m-d') : null;
            }
            
            if (!empty($australianStudyDate)) {
                $australianStudyDateObj = \DateTime::createFromFormat('d/m/Y', $australianStudyDate);
                $australianStudyDateFormatted = $australianStudyDateObj ? $australianStudyDateObj->format('Y-m-d') : null;
            }
            
            if (!empty($specialistEducationDate)) {
                $specialistEducationDateObj = \DateTime::createFromFormat('d/m/Y', $specialistEducationDate);
                $specialistEducationDateFormatted = $specialistEducationDateObj ? $specialistEducationDateObj->format('Y-m-d') : null;
            }
            
            if (!empty($regionalStudyDate)) {
                $regionalStudyDateObj = \DateTime::createFromFormat('d/m/Y', $regionalStudyDate);
                $regionalStudyDateFormatted = $regionalStudyDateObj ? $regionalStudyDateObj->format('Y-m-d') : null;
            }
            
            // Track changes with old and new values
            $changedFields = [];
            $fieldLabels = [
                'naati_test' => 'NAATI Test',
                'naati_date' => 'NAATI Date',
                'py_test' => 'PY Test',
                'py_date' => 'PY Date',
                'australian_study' => 'Australian Study',
                'australian_study_date' => 'Australian Study Date',
                'specialist_education' => 'Specialist Education',
                'specialist_education_date' => 'Specialist Education Date',
                'regional_study' => 'Regional Study',
                'regional_study_date' => 'Regional Study Date'
            ];
            
            // Compare and track changes
            if ($client->naati_test !== $naatiTest) {
                $changedFields[$fieldLabels['naati_test']] = [
                    'old' => $client->naati_test ?? null,
                    'new' => $naatiTest ?? null
                ];
            }
            if ($client->naati_date != $naatiDateFormatted) {
                $oldNaatiDate = $client->naati_date ? date('d/m/Y', strtotime($client->naati_date)) : null;
                $newNaatiDate = $naatiDateFormatted ? date('d/m/Y', strtotime($naatiDateFormatted)) : null;
                if ($oldNaatiDate !== $newNaatiDate) {
                    $changedFields[$fieldLabels['naati_date']] = [
                        'old' => $oldNaatiDate,
                        'new' => $newNaatiDate
                    ];
                }
            }
            if ($client->py_test !== $pyTest) {
                $changedFields[$fieldLabels['py_test']] = [
                    'old' => $client->py_test ?? null,
                    'new' => $pyTest ?? null
                ];
            }
            if ($client->py_date != $pyDateFormatted) {
                $oldPyDate = $client->py_date ? date('d/m/Y', strtotime($client->py_date)) : null;
                $newPyDate = $pyDateFormatted ? date('d/m/Y', strtotime($pyDateFormatted)) : null;
                if ($oldPyDate !== $newPyDate) {
                    $changedFields[$fieldLabels['py_date']] = [
                        'old' => $oldPyDate,
                        'new' => $newPyDate
                    ];
                }
            }
            if ($client->australian_study !== $australianStudy) {
                $changedFields[$fieldLabels['australian_study']] = [
                    'old' => $client->australian_study ?? null,
                    'new' => $australianStudy ?? null
                ];
            }
            if ($client->australian_study_date != $australianStudyDateFormatted) {
                $oldAusStudyDate = $client->australian_study_date ? date('d/m/Y', strtotime($client->australian_study_date)) : null;
                $newAusStudyDate = $australianStudyDateFormatted ? date('d/m/Y', strtotime($australianStudyDateFormatted)) : null;
                if ($oldAusStudyDate !== $newAusStudyDate) {
                    $changedFields[$fieldLabels['australian_study_date']] = [
                        'old' => $oldAusStudyDate,
                        'new' => $newAusStudyDate
                    ];
                }
            }
            if ($client->specialist_education !== $specialistEducation) {
                $changedFields[$fieldLabels['specialist_education']] = [
                    'old' => $client->specialist_education ?? null,
                    'new' => $specialistEducation ?? null
                ];
            }
            if ($client->specialist_education_date != $specialistEducationDateFormatted) {
                $oldSpecEdDate = $client->specialist_education_date ? date('d/m/Y', strtotime($client->specialist_education_date)) : null;
                $newSpecEdDate = $specialistEducationDateFormatted ? date('d/m/Y', strtotime($specialistEducationDateFormatted)) : null;
                if ($oldSpecEdDate !== $newSpecEdDate) {
                    $changedFields[$fieldLabels['specialist_education_date']] = [
                        'old' => $oldSpecEdDate,
                        'new' => $newSpecEdDate
                    ];
                }
            }
            if ($client->regional_study !== $regionalStudy) {
                $changedFields[$fieldLabels['regional_study']] = [
                    'old' => $client->regional_study ?? null,
                    'new' => $regionalStudy ?? null
                ];
            }
            if ($client->regional_study_date != $regionalStudyDateFormatted) {
                $oldRegStudyDate = $client->regional_study_date ? date('d/m/Y', strtotime($client->regional_study_date)) : null;
                $newRegStudyDate = $regionalStudyDateFormatted ? date('d/m/Y', strtotime($regionalStudyDateFormatted)) : null;
                if ($oldRegStudyDate !== $newRegStudyDate) {
                    $changedFields[$fieldLabels['regional_study_date']] = [
                        'old' => $oldRegStudyDate,
                        'new' => $newRegStudyDate
                    ];
                }
            }
            
            // Save all fields
            $client->naati_test = $naatiTest;
            $client->naati_date = $naatiDateFormatted;
            $client->py_test = $pyTest;
            $client->py_date = $pyDateFormatted;
            $client->australian_study = $australianStudy;
            $client->australian_study_date = $australianStudyDateFormatted;
            $client->specialist_education = $specialistEducation;
            $client->specialist_education_date = $specialistEducationDateFormatted;
            $client->regional_study = $regionalStudy;
            $client->regional_study_date = $regionalStudyDateFormatted;
            $client->save();

            // Clear points cache when EOI qualification data changes
            if (class_exists('\App\Services\PointsService')) {
                $pointsService = new \App\Services\PointsService();
                $pointsService->clearCache($client->id);
            }

            // Log activity with before/after values
            if (!empty($changedFields)) {
                $this->logClientActivityWithChanges(
                    $client->id,
                    'updated additional information',
                    $changedFields,
                    'activity'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Additional information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving additional information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveCharacterInfoSection($request, $client)
    {
        try {
            $characters = json_decode($request->input('characters'), true);
            
            if (!is_array($characters)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid character data'
                ], 400);
            }

            // Get existing characters before update for change tracking
            $existingCharacters = ClientCharacter::where('client_id', $client->id)->get();

            // Track which character IDs should be kept (both updated and newly created)
            $characterIdsToKeep = [];

            // Process each character record (update existing or create new)
            foreach ($characters as $charData) {
                if (!empty($charData['detail']) && !empty($charData['type_of_character'])) {
                    $characterId = $charData['id'] ?? null;
                    $characterId = !empty($characterId) ? (int)$characterId : null;
                    
                    if ($characterId) {
                        // Update existing character
                        $existingChar = ClientCharacter::find($characterId);
                        if ($existingChar && $existingChar->client_id == $client->id) {
                            $existingChar->update([
                                'admin_id' => Auth::id(),
                                'type_of_character' => $charData['type_of_character'],
                                'character_detail' => $charData['detail']
                            ]);
                            $characterIdsToKeep[] = $characterId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newChar = ClientCharacter::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::id(),
                                'type_of_character' => $charData['type_of_character'],
                                'character_detail' => $charData['detail']
                            ]);
                            $characterIdsToKeep[] = $newChar->id;
                        }
                    } else {
                        // Create new character
                        $newChar = ClientCharacter::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::id(),
                            'type_of_character' => $charData['type_of_character'],
                            'character_detail' => $charData['detail']
                        ]);
                        $characterIdsToKeep[] = $newChar->id;
                    }
                }
            }
            
            // Delete character records that were not in the request
            if (!empty($characterIdsToKeep)) {
                ClientCharacter::where('client_id', $client->id)
                    ->whereNotIn('id', $characterIdsToKeep)
                    ->delete();
            }

            // Get new characters for change tracking
            $newCharacters = ClientCharacter::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildCharacterDiff($existingCharacters, $newCharacters);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatCharacterDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated character information',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple count if diff fails
                Log::warning('Character diff failed, using simple count', [
                    'error' => $e->getMessage()
                ]);
                
                $characterCount = $newCharacters->count();
                $this->logClientActivity(
                    $client->id,
                    'updated character information',
                    "Updated {$characterCount} character record(s)",
                    'activity'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Character information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving character information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function savePartnerInfoSection($request, $client)
    {
        try {
            $partners = json_decode($request->input('partners'), true);
            
            if (!is_array($partners)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid partner data'
                ], 400);
            }

            // Delete existing partner records for this client (filter by partner relationship types)
            // First, get the related_client_ids that will be affected
            $existingRelationships = ClientRelationship::where('client_id', $client->id)
                ->whereIn('relationship_type', ['Husband', 'Wife', 'Ex-Wife', 'Defacto'])
                ->get();
            
            // Delete the main relationships
            ClientRelationship::where('client_id', $client->id)
                ->whereIn('relationship_type', ['Husband', 'Wife', 'Ex-Wife', 'Defacto'])
                ->delete();
            
            // Delete reciprocal relationships
            foreach ($existingRelationships as $relationship) {
                if ($relationship->related_client_id) {
                    ClientRelationship::where('client_id', $relationship->related_client_id)
                        ->where('related_client_id', $client->id)
                        ->whereIn('relationship_type', ['Husband', 'Wife', 'Ex-Wife', 'Defacto'])
                        ->delete();
                }
            }

            // Clear ClientSpouseDetail when points calculation would be stale:
            // 1. All partners removed - no partner to calculate for
            // 2. Selected partner (related_client_id) was removed but other partners remain - orphaned EOI data
            $hasValidPartners = collect($partners)->contains(fn ($p) => !empty($p['details']) || !empty($p['relationship_type']));
            $newPartnerClientIds = collect($partners)
                ->filter(fn ($p) => !empty($p['details']) || !empty($p['relationship_type']))
                ->map(fn ($p) => (!empty($p['partner_id']) && $p['partner_id'] !== '0' && is_numeric($p['partner_id'])) ? (int) $p['partner_id'] : null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $spouseDetail = ClientSpouseDetail::where('client_id', $client->id)->first();
            $shouldClearSpouse = false;
            if (!$hasValidPartners) {
                $shouldClearSpouse = true;
            } elseif ($spouseDetail && $spouseDetail->related_client_id !== null) {
                if (!in_array((int) $spouseDetail->related_client_id, $newPartnerClientIds, true)) {
                    $shouldClearSpouse = true; // Selected partner was removed from Partner section
                }
            }

            if ($shouldClearSpouse) {
                ClientSpouseDetail::where('client_id', $client->id)->delete();
                if (class_exists(\App\Services\PointsService::class)) {
                    (new \App\Services\PointsService())->clearCache($client->id);
                }
            }

            // Insert new partner records
            $partnerCount = 0;
            foreach ($partners as $partnerData) {
                if (!empty($partnerData['details']) || !empty($partnerData['relationship_type'])) {
                    // Convert DOB from dd/mm/yyyy to YYYY-mm-dd format
                    $dob = null;
                    if (!empty($partnerData['dob']) && $partnerData['dob'] !== 'dd/mm/yyyy') {
                        try {
                            $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $partnerData['dob']);
                            $dob = $dobDate->format('Y-m-d');
                        } catch (\Exception $e) {
                            // If date format is invalid, set to null
                            $dob = null;
                        }
                    }
                    
                    // Create the main relationship entry
                    ClientRelationship::create([
                        'admin_id' => Auth::id(),
                        'client_id' => $client->id,
                        'related_client_id' => (!empty($partnerData['partner_id']) && $partnerData['partner_id'] !== '0') ? $partnerData['partner_id'] : null,
                        'details' => $partnerData['details'],
                        'relationship_type' => $partnerData['relationship_type'] ?? null,
                        'gender' => $partnerData['gender'] ?? null,
                        'company_type' => $partnerData['company_type'] ?? null,
                        'last_name' => $partnerData['last_name'] ?? null,
                        'dob' => $dob,
                        'email' => $partnerData['email'] ?? null,
                        'first_name' => $partnerData['first_name'] ?? null,
                        'phone' => $partnerData['phone'] ?? null
                    ]);
                    $partnerCount++;
                    
                    // Create reciprocal relationship entry if partner_id exists (existing client)
                    if (!empty($partnerData['partner_id'])) {
                        // Get the related client's details for the reciprocal entry
                        $relatedClient = Admin::where('id', $partnerData['partner_id'])->whereIn('type', ['client', 'lead'])->first();
                        
                        if ($relatedClient) {
                            // Determine reciprocal relationship type
                            $reciprocalRelationshipType = $this->getReciprocalRelationshipType($partnerData['relationship_type']);
                            
                            // Create reciprocal entry
                            ClientRelationship::create([
                                'admin_id' => Auth::id(),
                                'client_id' => $partnerData['partner_id'],
                                'related_client_id' => $client->id,
                                'details' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ', ' . $client->phone . ', ' . $client->client_id . ')',
                                'relationship_type' => $reciprocalRelationshipType,
                                'gender' => $client->gender ?? null,
                                'company_type' => null, // Reciprocal entries should have null company_type
                                'last_name' => null,    // Reciprocal entries should have null last_name
                                'dob' => null,          // Reciprocal entries should have null dob
                                'email' => null,        // Reciprocal entries should have null email
                                'first_name' => null,   // Reciprocal entries should have null first_name
                                'phone' => null         // Reciprocal entries should have null phone
                            ]);
                        }
                    }
                }
            }

            // Log activity for partner information update
            $this->logClientActivity(
                $client->id,
                'updated partner information',
                "Updated {$partnerCount} partner/spouse record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Partner information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving partner information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reciprocal relationship type
     */
    private function getReciprocalRelationshipType($relationshipType)
    {
        switch ($relationshipType) {
            case 'Husband':
                return 'Wife';
            case 'Wife':
                return 'Husband';
            case 'Ex-Husband':
                return 'Ex-Wife';
            case 'Ex-Wife':
                return 'Ex-Husband';
            case 'Mother-in-law':
                return 'Mother-in-law'; // No specific reciprocal
            case 'Defacto':
                return 'Defacto';
            default:
                return $relationshipType; // Return same type if no specific reciprocal
        }
    }

    private function savePartnerEoiInfoSection($request, $client)
    {
        try {
            $selectedPartnerId = $request->input('selected_partner_id');
            
            if (!$selectedPartnerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a partner for EOI calculation'
                ], 400);
            }

            // Get the selected partner's data from their actual profile
            $partnerClient = \App\Models\Admin::find($selectedPartnerId);
            if (!$partnerClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected partner not found'
                ], 404);
            }

            // Get or create spouse details record
            $spouseDetail = \App\Models\ClientSpouseDetail::firstOrNew(['client_id' => $client->id]);
            
            // Auto-populate from partner's actual profile
            $spouseDetail->related_client_id = $selectedPartnerId;
            $spouseDetail->dob = $partnerClient->dob;
            
            // Check if partner is citizen/PR from their visa records
            $spouseDetail->is_citizen = 0; // Default
            $spouseDetail->has_pr = 0; // Default
            
            // Get partner's English test scores
            $partnerTestScore = $partnerClient->testScores()->latest()->first();
            if ($partnerTestScore) {
                $spouseDetail->spouse_has_english_score = 1;
                $spouseDetail->spouse_test_type = $partnerTestScore->test_type;
                $spouseDetail->spouse_listening_score = $partnerTestScore->listening;
                $spouseDetail->spouse_reading_score = $partnerTestScore->reading;
                $spouseDetail->spouse_writing_score = $partnerTestScore->writing;
                $spouseDetail->spouse_speaking_score = $partnerTestScore->speaking;
                $spouseDetail->spouse_overall_score = $partnerTestScore->overall_score;
                $spouseDetail->spouse_test_date = $partnerTestScore->test_date;
            } else {
                $spouseDetail->spouse_has_english_score = 0;
                $spouseDetail->spouse_test_type = null;
                $spouseDetail->spouse_listening_score = null;
                $spouseDetail->spouse_reading_score = null;
                $spouseDetail->spouse_writing_score = null;
                $spouseDetail->spouse_speaking_score = null;
                $spouseDetail->spouse_overall_score = null;
                $spouseDetail->spouse_test_date = null;
            }

            // Get partner's skills assessment
            $partnerOccupation = $partnerClient->occupations()->latest()->first();
            if ($partnerOccupation) {
                $spouseDetail->spouse_has_skill_assessment = 1;
                $spouseDetail->spouse_nomi_occupation = $partnerOccupation->nomi_occupation;
                $spouseDetail->spouse_assessment_date = $partnerOccupation->dates;
                $spouseDetail->spouse_skill_assessment_status = 'Valid'; // Default status
            } else {
                $spouseDetail->spouse_has_skill_assessment = 0;
                $spouseDetail->spouse_nomi_occupation = null;
                $spouseDetail->spouse_assessment_date = null;
                $spouseDetail->spouse_skill_assessment_status = null;
            }

            $spouseDetail->save();

            // Also create/update ClientPartner record to keep both tables in sync
            // This ensures the summary view displays correctly
            $partnerRelationship = ClientRelationship::where('client_id', $client->id)
                ->where('related_client_id', $selectedPartnerId)
                ->first();

            if (!$partnerRelationship) {
                // Determine relationship type from client's marital status
                $relationshipType = 'Partner'; // Default
                if ($client->marital_status === 'Married') {
                    // Determine if Husband or Wife based on client's gender if available
                    $relationshipType = 'Partner'; // Use generic Partner for married
                } elseif (in_array($client->marital_status, ['De Facto', 'Defacto'])) {
                    $relationshipType = 'Defacto';
                }
                
                // Create new ClientPartner record
                ClientRelationship::create([
                    'client_id' => $client->id,
                    'related_client_id' => $selectedPartnerId,
                    'relationship_type' => $relationshipType,
                    'details' => $partnerClient->first_name . ' ' . $partnerClient->last_name,
                ]);
                
                Log::info('Created ClientPartner record for EOI synchronization', [
                    'client_id' => $client->id,
                    'partner_id' => $selectedPartnerId,
                    'relationship_type' => $relationshipType
                ]);
            }

            // Clear points cache when partner EOI data changes
            if (class_exists('\App\Services\PointsService')) {
                $pointsService = new \App\Services\PointsService();
                $pointsService->clearCache($client->id);
            }

            // Log activity for partner EOI information update
            $this->logClientActivity(
                $client->id,
                'updated partner EOI information',
                "Updated partner EOI information for {$partnerClient->first_name} {$partnerClient->last_name}",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Partner EOI information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving partner EOI information: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPartnerEoiData($partnerId)
    {
        try {
            $this->ensureCrmRecordAccess((int) $partnerId);

            // Get the partner's data from their actual profile
            $partnerClient = \App\Models\Admin::find($partnerId);
            if (!$partnerClient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner not found'
                ], 404);
            }

            // Get partner's English test scores
            $partnerTestScore = $partnerClient->testScores()->latest()->first();
            
            // Get partner's skills assessment
            $partnerOccupation = $partnerClient->occupations()->latest()->first();
            
            // Check if partner is citizen/PR from their visa records
            $isCitizen = 0;
            $hasPR = 0;
            
            // Build the response data
            $partnerData = [
                'partner_name' => $partnerClient->first_name . ' ' . $partnerClient->last_name,
                'dob' => $partnerClient->dob ? date('d/m/Y', strtotime($partnerClient->dob)) : 'Not set',
                'is_citizen' => $isCitizen,
                'has_pr' => $hasPR,
                'english_test' => null,
                'skills_assessment' => null
            ];

            if ($partnerTestScore) {
                $partnerData['english_test'] = [
                    'test_type' => $partnerTestScore->test_type ?? 'Not set',
                    'listening' => $partnerTestScore->listening ?? 'Not set',
                    'reading' => $partnerTestScore->reading ?? 'Not set',
                    'writing' => $partnerTestScore->writing ?? 'Not set',
                    'speaking' => $partnerTestScore->speaking ?? 'Not set',
                    'overall' => $partnerTestScore->overall ?? 'Not set',
                    'test_date' => $partnerTestScore->test_date ? date('d/m/Y', strtotime($partnerTestScore->test_date)) : 'Not set'
                ];
            }

            if ($partnerOccupation) {
                $partnerData['skills_assessment'] = [
                    'has_assessment' => 'Yes',
                    'occupation' => $partnerOccupation->nomi_occupation ?? 'Not set',
                    'assessment_date' => $partnerOccupation->dates ? date('d/m/Y', strtotime($partnerOccupation->dates)) : 'Not set',
                    'status' => 'Valid'
                ];
            } else {
                $partnerData['skills_assessment'] = [
                    'has_assessment' => 'No',
                    'occupation' => 'Not set',
                    'assessment_date' => 'Not set',
                    'status' => 'Not set'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $partnerData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching partner EOI data: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveChildrenInfoSection($request, $client)
    {
        try {
            $children = json_decode($request->input('children'), true);
            
            if (!is_array($children)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid children data'
                ], 400);
            }

            // Function to get reciprocal relationship for children
            $getReciprocalRelationship = function($relationshipType, $childGender, $parentGender) {
                $reciprocalMap = [
                    'Son' => 'Father', // Default to Father
                    'Daughter' => 'Father', // Default to Father
                    'Step Son' => 'Step Father', // Default to Step Father
                    'Step Daughter' => 'Step Father', // Default to Step Father
                ];
                
                // Adjust based on parent's gender
                if (strtolower($parentGender) === 'female') {
                    $reciprocalMap = [
                        'Son' => 'Mother',
                        'Daughter' => 'Mother',
                        'Step Son' => 'Step Mother',
                        'Step Daughter' => 'Step Mother',
                    ];
                }
                
                return $reciprocalMap[$relationshipType] ?? 'Parent';
            };

            // Delete existing children records for this client (filter by children relationship types)
            ClientRelationship::where('client_id', $client->id)
                ->whereIn('relationship_type', ['Son', 'Daughter', 'Step Son', 'Step Daughter'])
                ->delete();

            // Insert new children records
            $childrenCount = 0;
            foreach ($children as $childData) {
                if (!empty($childData['details']) || !empty($childData['relationship_type'])) {
                    $relatedClientId = !empty($childData['child_id']) && $childData['child_id'] != 0 ? $childData['child_id'] : null;
                    $saveExtraFields = !$relatedClientId;
                    $childrenCount++;
                    
                    // Convert DOB from d/m/Y format to Y-m-d format for database storage
                    $dobFormatted = null;
                    if ($saveExtraFields && !empty($childData['dob']) && $childData['dob'] !== 'dd/mm/yyyy') {
                        try {
                            $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $childData['dob']);
                            $dobFormatted = $dobDate->format('Y-m-d');
                        } catch (\Exception $e) {
                            // If date format is invalid, set to null and log the error
                            Log::warning('Invalid DOB format for child: ' . $childData['dob']);
                            $dobFormatted = null;
                        }
                    }
                    
                    // Prepare details field for primary relationship
                    $primaryDetails = null;
                    if ($relatedClientId) {
                        // For existing clients, use the details from the form (which contains the constructed string)
                        $primaryDetails = $childData['details'];
                    } else {
                        // For new clients, construct details from the form data
                        $firstName = trim($childData['first_name'] ?? '');
                        $lastName = trim($childData['last_name'] ?? '');
                        $email = trim($childData['email'] ?? '');
                        $phone = trim($childData['phone'] ?? '');
                        
                        if (!empty($firstName) || !empty($lastName)) {
                            $primaryDetails = trim($firstName . ' ' . $lastName);
                            if (!empty($email)) {
                                $primaryDetails .= ' (' . $email;
                                if (!empty($phone)) {
                                    $primaryDetails .= ', ' . $phone;
                                }
                                $primaryDetails .= ')';
                            } elseif (!empty($phone)) {
                                $primaryDetails .= ' (' . $phone . ')';
                            }
                        } else {
                            $primaryDetails = $childData['details'];
                        }
                    }
                    
                    // Create the primary relationship (parent -> child)
                    $newChild = ClientRelationship::create([
                        'admin_id' => Auth::id(),
                        'client_id' => $client->id,
                        'related_client_id' => $relatedClientId,
                        'details' => $primaryDetails,
                        'relationship_type' => $childData['relationship_type'] ?? null,
                        'gender' => $childData['gender'] ?? null,
                        'company_type' => $childData['company_type'] ?? null,
                        'email' => $saveExtraFields ? ($childData['email'] ?? null) : null,
                        'first_name' => $saveExtraFields ? ($childData['first_name'] ?? null) : null,
                        'last_name' => $saveExtraFields ? ($childData['last_name'] ?? null) : null,
                        'phone' => $saveExtraFields ? ($childData['phone'] ?? null) : null,
                        'dob' => $dobFormatted
                    ]);

                    // Create reciprocal relationship if related_client_id is set and not 0
                    if ($relatedClientId) {
                        $relatedChild = \App\Models\Admin::find($relatedClientId);
                        if ($relatedChild) {
                            // Get the reciprocal relationship type based on parent's gender
                            $reciprocalRelationshipType = $getReciprocalRelationship(
                                $childData['relationship_type'] ?? 'Son', 
                                $childData['gender'] ?? 'Male', 
                                $client->gender ?? 'Male'
                            );
                            
                            // Check if reciprocal relationship already exists to avoid duplicates
                            $existingReciprocal = ClientRelationship::where('client_id', $relatedClientId)
                                ->where('related_client_id', $client->id)
                                ->where('relationship_type', $reciprocalRelationshipType)
                                ->first();
                            
                            if (!$existingReciprocal) {
                                ClientRelationship::create([
                                    'admin_id' => Auth::id(),
                                    'client_id' => $relatedClientId,
                                    'related_client_id' => $client->id,
                                    'details' => "{$client->first_name} {$client->last_name} ({$client->email}, {$client->phone}, {$client->client_id})",
                                    'relationship_type' => $reciprocalRelationshipType,
                                    'company_type' => null, // Reciprocal entries should have null company_type
                                    'gender' => $client->gender ?? null
                                ]);
                            }
                        }
                    }
                }
            }

            // Log activity for children information update
            $this->logClientActivity(
                $client->id,
                'updated children information',
                "Updated {$childrenCount} children record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Children information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving children information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveEoiInfoSection($request, $client)
    {
        try {
            $eois = json_decode($request->input('eois'), true);
            
            if (!is_array($eois)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid EOI data'
                ], 400);
            }

            // Delete existing EOI references for this client
            ClientEoiReference::where('client_id', $client->id)->delete();

            // Save new EOI references
            foreach ($eois as $eoiData) {
                if (!empty($eoiData['eoi_number']) || !empty($eoiData['subclass']) || !empty($eoiData['occupation'])) {
                    // Format submission date from d/m/Y to Y-m-d
                    $formatted_submission_date = null;
                    if (!empty($eoiData['submission_date'])) {
                        try {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $eoiData['submission_date']);
                            $formatted_submission_date = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            // If format conversion fails, try to use the date as-is
                            $formatted_submission_date = $eoiData['submission_date'];
                        }
                    }
                    
                    ClientEoiReference::create([
                        'client_id' => $client->id,
                        'admin_id' => Auth::id(),
                        'EOI_number' => $eoiData['eoi_number'] ?? null,
                        'EOI_subclass' => $eoiData['subclass'] ?? null,
                        'EOI_occupation' => $eoiData['occupation'] ?? null,
                        'EOI_point' => $eoiData['point'] ?? null,
                        'EOI_state' => $eoiData['state'] ?? null,
                        'EOI_submission_date' => $formatted_submission_date,
                        'EOI_ROI' => $eoiData['roi'] ?? null,
                    ]);
                }
            }

            // Log activity for EOI references update
            $eoiCount = count(array_filter($eois, function($eoi) { 
                return !empty($eoi['eoi_number']) || !empty($eoi['subclass']) || !empty($eoi['occupation']); 
            }));
            $this->logClientActivity(
                $client->id,
                'updated EOI references',
                "Updated {$eoiCount} EOI reference record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'EOI reference information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving EOI information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save occupation information section
     */
    private function saveOccupationInfoSection($request, $client)
    {
        try {
            $requestData = $request->all();
            
            // Handle occupation deletion
            if (isset($requestData['delete_occupation_ids']) && is_array($requestData['delete_occupation_ids'])) {
                foreach ($requestData['delete_occupation_ids'] as $occupationId) {
                    $occupation = \App\Models\ClientOccupation::find($occupationId);
                    if ($occupation && $occupation->client_id == $client->id) {
                        $occupation->delete();
                    }
                }
            }

            // Handle occupation data
            if (isset($requestData['nomi_occupation']) && is_array($requestData['nomi_occupation'])) {
                foreach ($requestData['nomi_occupation'] as $key => $nomiOccupation) {
                    if (!empty($nomiOccupation) || isset($requestData['skill_assessment_hidden'][$key])) {
                        $occupationId = $requestData['occupation_id'][$key] ?? null;
                        $anzscoOccupationId = $requestData['anzsco_occupation_id'][$key] ?? null;
                        $skillAssessment = $requestData['skill_assessment_hidden'][$key] ?? null;
                        $occupationCode = $requestData['occupation_code'][$key] ?? null;
                        $list = $requestData['list'][$key] ?? null;
                        $visaSubclass = $requestData['visa_subclass'][$key] ?? null;
                        $date = $requestData['dates'][$key] ?? null;
                        $expiryDate = $requestData['expiry_dates'][$key] ?? null;
                        $occReferenceNo = $requestData['occ_reference_no'][$key] ?? null;
                        $relevantOccupation = isset($requestData['relevant_occupation_hidden'][$key]) && $requestData['relevant_occupation_hidden'][$key] === '1' ? 1 : 0;

                        // Convert dates from dd/mm/yyyy to Y-m-d for database storage
                        $formattedDate = null;
                        if (!empty($date)) {
                            try {
                                $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $date);
                                $formattedDate = $dateObj->format('Y-m-d');
                            } catch (\Exception $e) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Invalid Assessment Date format: ' . $date
                                ], 422);
                            }
                        }

                        $formattedExpiryDate = null;
                        if (!empty($expiryDate)) {
                            try {
                                $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $expiryDate);
                                $formattedExpiryDate = $dateObj->format('Y-m-d');
                            } catch (\Exception $e) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Invalid Expiry Date format: ' . $expiryDate
                                ], 422);
                            }
                        }

                        if ($occupationId) {
                            // Update existing record
                            $existingOccupation = \App\Models\ClientOccupation::find($occupationId);
                            if ($existingOccupation && $existingOccupation->client_id == $client->id) {
                                $existingOccupation->update([
                                    'admin_id' => Auth::user()->id,
                                    'skill_assessment' => $skillAssessment,
                                    'nomi_occupation' => $nomiOccupation,
                                    'occupation_code' => $occupationCode,
                                    'list' => $list,
                                    'visa_subclass' => $visaSubclass,
                                    'dates' => $formattedDate,
                                    'expiry_dates' => $formattedExpiryDate,
                                    'occ_reference_no' => $occReferenceNo,
                                    'relevant_occupation' => $relevantOccupation,
                                    'anzsco_occupation_id' => $anzscoOccupationId
                                ]);
                            }
                        } else {
                            // Create new record
                            \App\Models\ClientOccupation::create([
                                'admin_id' => Auth::user()->id,
                                'client_id' => $client->id,
                                'skill_assessment' => $skillAssessment,
                                'nomi_occupation' => $nomiOccupation,
                                'occupation_code' => $occupationCode,
                                'list' => $list,
                                'visa_subclass' => $visaSubclass,
                                'dates' => $formattedDate,
                                'expiry_dates' => $formattedExpiryDate,
                                'occ_reference_no' => $occReferenceNo,
                                'relevant_occupation' => $relevantOccupation,
                                'anzsco_occupation_id' => $anzscoOccupationId
                            ]);
                        }
                    }
                }
            }

            // Log activity for occupation & skills update
            $occupationCount = 0;
            if (isset($requestData['nomi_occupation']) && is_array($requestData['nomi_occupation'])) {
                foreach ($requestData['nomi_occupation'] as $key => $nomiOccupation) {
                    if (!empty($nomiOccupation) || isset($requestData['skill_assessment_hidden'][$key])) {
                        $occupationCount++;
                    }
                }
            }
            $this->logClientActivity(
                $client->id,
                'updated occupation & skills',
                "Updated {$occupationCount} occupation record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Occupation information saved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving occupation information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveTestScoreInfoSection($request, $client)
    {
        try {
            $requestData = $request->all();
            
            // Handle test score deletion
            if (isset($requestData['delete_test_score_ids']) && is_array($requestData['delete_test_score_ids'])) {
                foreach ($requestData['delete_test_score_ids'] as $testScoreId) {
                    $testScore = \App\Models\ClientTestScore::find($testScoreId);
                    if ($testScore && $testScore->client_id == $client->id) {
                        $testScore->delete();
                    }
                }
            }

            // Handle test score data
            if (isset($requestData['test_type_hidden']) && is_array($requestData['test_type_hidden'])) {
                foreach ($requestData['test_type_hidden'] as $key => $testType) {
                    if (!empty($testType)) {
                        $testScoreId = $requestData['test_score_id'][$key] ?? null;
                        $listening = $requestData['listening'][$key] ?? null;
                        $reading = $requestData['reading'][$key] ?? null;
                        $writing = $requestData['writing'][$key] ?? null;
                        $speaking = $requestData['speaking'][$key] ?? null;
                        $overallScore = $requestData['overall_score'][$key] ?? null;
                        $testDate = $requestData['test_date'][$key] ?? null;
                        $testReferenceNo = $requestData['test_reference_no'][$key] ?? null;
                        $relevantTest = isset($requestData['relevant_test_hidden'][$key]) && $requestData['relevant_test_hidden'][$key] === '1' ? 1 : 0;

                        // Convert test_date from dd/mm/yyyy to Y-m-d for database storage
                        $formattedTestDate = null;
                        if (!empty($testDate)) {
                            try {
                                $dateObj = \Carbon\Carbon::createFromFormat('d/m/Y', $testDate);
                                $formattedTestDate = $dateObj->format('Y-m-d');
                            } catch (\Exception $e) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Invalid Test Date format: ' . $testDate
                                ], 422);
                            }
                        }

                        // Calculate proficiency level using the service
                        $proficiencyService = new \App\Services\EnglishProficiencyService();
                        $scores = [
                            'listening' => $listening,
                            'reading' => $reading,
                            'writing' => $writing,
                            'speaking' => $speaking,
                            'overall' => $overallScore
                        ];
                        $proficiencyResult = $proficiencyService->calculateProficiency($testType, $scores, $formattedTestDate);

                        if ($testScoreId) {
                            // Update existing record
                            $existingTestScore = \App\Models\ClientTestScore::find($testScoreId);
                            if ($existingTestScore && $existingTestScore->client_id == $client->id) {
                                $existingTestScore->update([
                                    'admin_id' => Auth::user()->id,
                                    'test_type' => $testType,
                                    'listening' => $listening,
                                    'reading' => $reading,
                                    'writing' => $writing,
                                    'speaking' => $speaking,
                                    'overall_score' => $overallScore,
                                    'proficiency_level' => $proficiencyResult['level'],
                                    'proficiency_points' => $proficiencyResult['points'],
                                    'test_date' => $formattedTestDate,
                                    'test_reference_no' => $testReferenceNo,
                                    'relevant_test' => $relevantTest
                                ]);
                            }
                        } else {
                            // Create new record
                            \App\Models\ClientTestScore::create([
                                'admin_id' => Auth::user()->id,
                                'client_id' => $client->id,
                                'test_type' => $testType,
                                'listening' => $listening,
                                'reading' => $reading,
                                'writing' => $writing,
                                'speaking' => $speaking,
                                'overall_score' => $overallScore,
                                'proficiency_level' => $proficiencyResult['level'],
                                'proficiency_points' => $proficiencyResult['points'],
                                'test_date' => $formattedTestDate,
                                'test_reference_no' => $testReferenceNo,
                                'relevant_test' => $relevantTest
                            ]);
                        }
                    }
                }
            }

            // Log activity for English test scores update
            $testScoreCount = isset($requestData['test_type_hidden']) && is_array($requestData['test_type_hidden'])
                ? count(array_filter($requestData['test_type_hidden'], function($testType) {
                    return !empty($testType);
                }))
                : 0;
            $this->logClientActivity(
                $client->id,
                'updated English test scores',
                "Updated {$testScoreCount} test score record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Test score information saved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving test score information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveRelatedFilesInfoSection($request, $client)
    {
        try {
            $relatedFiles = $request->input('related_files', []);
            
            // Log what we received
            Log::info('Save related files received data', [
                'clientId' => $client->id,
                'rawRelatedFiles' => $relatedFiles,
                'allRequestData' => $request->all()
            ]);
            
            // Convert array to comma-separated string
            $relatedFilesString = '';
            if (!empty($relatedFiles) && is_array($relatedFiles)) {
                // Filter out empty values and trim
                $relatedFiles = array_filter(
                    array_map('trim', $relatedFiles),
                    function($id) {
                        return !empty($id);
                    }
                );
                $relatedFilesString = implode(',', $relatedFiles);
            }

            // Log what we're saving
            Log::info('Saving related files', [
                'clientId' => $client->id,
                'relatedFilesArray' => $relatedFiles,
                'relatedFilesString' => $relatedFilesString
            ]);

            // Handle bidirectional relationships BEFORE updating current client
            $this->updateBidirectionalRelatedFiles($client->id, $relatedFiles);

            // Update the client's related_files field AFTER handling bidirectional relationships
            $client->related_files = $relatedFilesString;
            $client->save();

            // Log activity for related files update
            $relatedFilesCount = !empty($relatedFiles) ? count($relatedFiles) : 0;
            $this->logClientActivity(
                $client->id,
                'updated related files',
                "Updated related files: {$relatedFilesCount} file(s) linked",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Related files saved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in saveRelatedFilesInfoSection: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving related files: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateBidirectionalRelatedFiles($currentClientId, $newRelatedFileIds)
    {
        try {
            // Get current related files from database BEFORE any updates
            $currentClient = Admin::find($currentClientId);
            $currentRelatedFiles = [];
            
            if ($currentClient && !empty($currentClient->related_files)) {
                // Split by comma and filter out empty values
                $currentRelatedFiles = array_filter(
                    array_map('trim', explode(',', $currentClient->related_files)),
                    function($id) {
                        return !empty($id);
                    }
                );
            }

            // Convert new related file IDs to strings and filter empty values
            $newRelatedFileIds = array_filter(
                array_map('strval', $newRelatedFileIds),
                function($id) {
                    return !empty($id);
                }
            );

            // Convert current related files to strings for comparison
            $currentRelatedFiles = array_map('strval', $currentRelatedFiles);

            // Find files that were removed (exist in current but not in new)
            $removedFiles = array_diff($currentRelatedFiles, $newRelatedFileIds);

            // Log for debugging
            Log::info('Bidirectional update debug', [
                'currentClientId' => $currentClientId,
                'currentRelatedFiles' => $currentRelatedFiles,
                'newRelatedFileIds' => $newRelatedFileIds,
                'removedFiles' => $removedFiles,
                'example_scenario' => 'If Client A (36464) removes Client B (36465), Client B should remove Client A'
            ]);

            // Remove current client from removed files' related_files
            foreach ($removedFiles as $removedFileId) {
                if (!empty($removedFileId)) {
                    $relatedClient = Admin::find($removedFileId);
                    if ($relatedClient) {
                        $relatedFiles = [];
                        if (!empty($relatedClient->related_files)) {
                            // Split and filter existing related files
                            $relatedFiles = array_filter(
                                array_map('trim', explode(',', $relatedClient->related_files)),
                                function($id) {
                                    return !empty($id);
                                }
                            );
                        }
                        
                        // Remove current client from the list
                        $relatedFiles = array_filter($relatedFiles, function($id) use ($currentClientId) {
                            return $id != $currentClientId;
                        });
                        
                        // Update the related client's related_files
                        $relatedClient->related_files = implode(',', $relatedFiles);
                        $relatedClient->save();
                        
                        Log::info('Removed client from related files', [
                            'relatedClientId' => $removedFileId,
                            'removedClientId' => $currentClientId,
                            'newRelatedFiles' => $relatedClient->related_files
                        ]);
                    }
                }
            }

            // Add current client to new related files
            foreach ($newRelatedFileIds as $relatedFileId) {
                if (!empty($relatedFileId)) {
                    $relatedClient = Admin::find($relatedFileId);
                    if ($relatedClient) {
                        $existingRelatedFiles = [];
                        if (!empty($relatedClient->related_files)) {
                            // Split and filter existing related files
                            $existingRelatedFiles = array_filter(
                                array_map('trim', explode(',', $relatedClient->related_files)),
                                function($id) {
                                    return !empty($id);
                                }
                            );
                        }
                        
                        // Add current client if not already present
                        if (!in_array($currentClientId, $existingRelatedFiles)) {
                            $existingRelatedFiles[] = $currentClientId;
                            $relatedClient->related_files = implode(',', $existingRelatedFiles);
                            $relatedClient->save();
                            
                            Log::info('Added client to related files', [
                                'relatedClientId' => $relatedFileId,
                                'addedClientId' => $currentClientId,
                                'newRelatedFiles' => $relatedClient->related_files
                            ]);
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Error updating bidirectional related files: ' . $e->getMessage());
        }
    }

    // Test method to debug specific scenario
    public function testBidirectionalRemoval(Request $request)
    {
        try {
            $clientAId = $request->input('client_a_id', '36464');
            $clientBId = $request->input('client_b_id', '36465');
            
            // Get current state
            $clientA = Admin::find($clientAId);
            $clientB = Admin::find($clientBId);
            
            $result = [
                'client_a' => [
                    'id' => $clientAId,
                    'name' => $clientA ? $clientA->first_name . ' ' . $clientA->last_name : 'Not found',
                    'related_files' => $clientA ? $clientA->related_files : 'Not found'
                ],
                'client_b' => [
                    'id' => $clientBId,
                    'name' => $clientB ? $clientB->first_name . ' ' . $clientB->last_name : 'Not found',
                    'related_files' => $clientB ? $clientB->related_files : 'Not found'
                ]
            ];
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Parents Information Section
     */
    public function saveParentsInfoSection(Request $request)
    {
        try {
            $clientId = $request->input('id'); // Use 'id' instead of 'client_id' - 'id' is the database ID
            $client = Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $parentsData = $request->input('parents', []);
            
            // Delete existing parent relationships for this client
            ClientRelationship::where('client_id', $client->id)
                ->whereIn('relationship_type', ['Father', 'Mother', 'Step Father', 'Step Mother', 'Mother-in-law', 'Father-in-law'])
                ->delete();

            $parentsCount = 0;
            foreach ($parentsData as $parentData) {
                if (empty($parentData['relationship_type'])) {
                    continue;
                }
                $parentsCount++;

                $saveExtraFields = empty($parentData['details']) || trim($parentData['details']) === '';
                
                // Format DOB from d/m/Y to Y-m-d
                $dobFormatted = null;
                if ($saveExtraFields && !empty($parentData['dob']) && $parentData['dob'] !== 'dd/mm/yyyy') {
                    try {
                        $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $parentData['dob']);
                        $dobFormatted = $dobDate->format('Y-m-d');
                    } catch (\Exception $e) {
                        Log::warning('Invalid DOB format for parent: ' . $parentData['dob']);
                        $dobFormatted = null;
                    }
                }

                // For existing clients (details not empty), use details as-is
                // For new clients (details empty), construct details from first_name, last_name, email, phone
                $primaryDetails = '';
                if (!$saveExtraFields) {
                    // Existing client - use details as-is
                    $primaryDetails = $parentData['details'];
                } else {
                    // New client - construct details from individual fields
                    $detailsParts = [];
                    if (!empty($parentData['first_name'])) $detailsParts[] = trim($parentData['first_name']);
                    if (!empty($parentData['last_name'])) $detailsParts[] = trim($parentData['last_name']);
                    if (!empty($parentData['email'])) $detailsParts[] = trim($parentData['email']);
                    if (!empty($parentData['phone'])) $detailsParts[] = trim($parentData['phone']);
                    $primaryDetails = implode(', ', $detailsParts);
                }

                // When details-only (no expanded form): populate first_name/last_name from details
                // so the client detail page can display the name (it uses first_name/last_name, not details)
                $firstNameToSave = $saveExtraFields ? ($parentData['first_name'] ?? null) : null;
                $lastNameToSave = $saveExtraFields ? ($parentData['last_name'] ?? null) : null;
                if (!$saveExtraFields && !empty(trim($primaryDetails ?? ''))) {
                    $nameParts = preg_split('/\s+/u', trim($primaryDetails), 2);
                    $firstNameToSave = $nameParts[0] ?? null;
                    $lastNameToSave = isset($nameParts[1]) ? $nameParts[1] : null;
                }

                // Save primary parent relationship
                $parentRelationship = ClientRelationship::create([
                    'admin_id' => Auth::id(),
                    'client_id' => $client->id,
                    'related_client_id' => null,
                    'relationship_type' => $parentData['relationship_type'],
                    'details' => $primaryDetails,
                    'email' => $saveExtraFields ? ($parentData['email'] ?? null) : null,
                    'first_name' => $firstNameToSave,
                    'last_name' => $lastNameToSave,
                    'phone' => $saveExtraFields ? ($parentData['phone'] ?? null) : null,
                    'dob' => $dobFormatted,
                    'gender' => $parentData['gender'] ?? null,
                    'company_type' => $parentData['company_type'] ?? null
                ]);

                // Create reciprocal relationship if related client exists
                if (!empty($parentData['details']) && trim($parentData['details']) !== '') {
                    // Try to find related client by details
                    $relatedClient = $this->findRelatedClientByDetails($parentData['details']);
                    
                    if ($relatedClient) {
                        // Update primary relationship with related client ID
                        $parentRelationship->update(['related_client_id' => $relatedClient->id]);

                        // Determine reciprocal relationship type based on parent's gender
                        $reciprocalRelationshipType = $this->getReciprocalRelationshipForParent($parentData['relationship_type'], $parentData['gender'] ?? '');
                        
                        // Check if reciprocal relationship already exists
                        $existingReciprocal = ClientRelationship::where('client_id', $relatedClient->id)
                            ->where('related_client_id', $client->id)
                            ->where('relationship_type', $reciprocalRelationshipType)
                            ->first();

                        // Create reciprocal relationship if it doesn't exist
                        if (!$existingReciprocal) {
                            ClientRelationship::create([
                                'admin_id' => Auth::id(),
                                'client_id' => $relatedClient->id,
                                'related_client_id' => $client->id,
                                'relationship_type' => $reciprocalRelationshipType,
                                'details' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ', ' . $client->phone . ', ' . $client->client_id . ')',
                                'email' => null,
                                'first_name' => null,
                                'last_name' => null,
                                'phone' => null,
                                'dob' => null,
                                'company_type' => null, // Reciprocal entries should have null company_type
                                'gender' => $client->gender ?? null
                            ]);
                        }
                    }
                }
            }

            // Log activity for parents information update
            $this->logClientActivity(
                $client->id,
                'updated parents information',
                "Updated {$parentsCount} parent record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Parents information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving parents information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save Siblings Information Section
     */
    public function saveSiblingsInfoSection(Request $request)
    {
        try {
            $clientId = $request->input('id'); // Use 'id' instead of 'client_id' - 'id' is the database ID
            $client = Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $siblingsData = $request->input('siblings', []);
            
            // Delete existing sibling relationships for this client
            ClientRelationship::where('client_id', $client->id)
                ->whereIn('relationship_type', ['Brother', 'Sister', 'Step Brother', 'Step Sister'])
                ->delete();

            $siblingsCount = 0;
            foreach ($siblingsData as $siblingData) {
                if (empty($siblingData['relationship_type'])) {
                    continue;
                }
                $siblingsCount++;

                $saveExtraFields = empty($siblingData['details']) || trim($siblingData['details']) === '';
                
                // Format DOB from d/m/Y to Y-m-d
                $dobFormatted = null;
                if ($saveExtraFields && !empty($siblingData['dob']) && $siblingData['dob'] !== 'dd/mm/yyyy') {
                    try {
                        $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $siblingData['dob']);
                        $dobFormatted = $dobDate->format('Y-m-d');
                    } catch (\Exception $e) {
                        Log::warning('Invalid DOB format for sibling: ' . $siblingData['dob']);
                        $dobFormatted = null;
                    }
                }

                // For existing clients (details not empty), use details as-is
                // For new clients (details empty), construct details from first_name, last_name, email, phone
                $primaryDetails = '';
                if (!$saveExtraFields) {
                    // Existing client - use details as-is
                    $primaryDetails = $siblingData['details'];
                } else {
                    // New client - construct details from individual fields
                    $detailsParts = [];
                    if (!empty($siblingData['first_name'])) $detailsParts[] = trim($siblingData['first_name']);
                    if (!empty($siblingData['last_name'])) $detailsParts[] = trim($siblingData['last_name']);
                    if (!empty($siblingData['email'])) $detailsParts[] = trim($siblingData['email']);
                    if (!empty($siblingData['phone'])) $detailsParts[] = trim($siblingData['phone']);
                    $primaryDetails = implode(', ', $detailsParts);
                }

                // Save primary sibling relationship
                $siblingRelationship = ClientRelationship::create([
                    'admin_id' => Auth::id(),
                    'client_id' => $client->id,
                    'related_client_id' => null,
                    'relationship_type' => $siblingData['relationship_type'],
                    'details' => $primaryDetails,
                    'email' => $saveExtraFields ? ($siblingData['email'] ?? null) : null,
                    'first_name' => $saveExtraFields ? ($siblingData['first_name'] ?? null) : null,
                    'last_name' => $saveExtraFields ? ($siblingData['last_name'] ?? null) : null,
                    'phone' => $saveExtraFields ? ($siblingData['phone'] ?? null) : null,
                    'dob' => $dobFormatted,
                    'gender' => $siblingData['gender'] ?? null,
                    'company_type' => $siblingData['company_type'] ?? null
                ]);

                // Create reciprocal relationship if related client exists
                if (!empty($siblingData['details']) && trim($siblingData['details']) !== '') {
                    // Try to find related client by details
                    $relatedClient = $this->findRelatedClientByDetails($siblingData['details']);
                    
                    if ($relatedClient) {
                        // Update primary relationship with related client ID
                        $siblingRelationship->update(['related_client_id' => $relatedClient->id]);

                        // Determine reciprocal relationship type based on current client's gender
                        $reciprocalRelationshipType = $this->getReciprocalRelationshipForSibling($siblingData['relationship_type'], $client->gender ?? '');
                        
                        // Check if reciprocal relationship already exists
                        $existingReciprocal = ClientRelationship::where('client_id', $relatedClient->id)
                            ->where('related_client_id', $client->id)
                            ->where('relationship_type', $reciprocalRelationshipType)
                            ->first();

                        // Create reciprocal relationship if it doesn't exist
                        if (!$existingReciprocal) {
                            ClientRelationship::create([
                                'admin_id' => Auth::id(),
                                'client_id' => $relatedClient->id,
                                'related_client_id' => $client->id,
                                'relationship_type' => $reciprocalRelationshipType,
                                'details' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ', ' . $client->phone . ', ' . $client->client_id . ')',
                                'email' => null,
                                'first_name' => null,
                                'last_name' => null,
                                'phone' => null,
                                'dob' => null,
                                'company_type' => null, // Reciprocal entries should have null company_type
                                'gender' => $client->gender ?? null
                            ]);
                        }
                    }
                }
            }

            // Log activity for siblings information update
            $this->logClientActivity(
                $client->id,
                'updated siblings information',
                "Updated {$siblingsCount} sibling record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Siblings information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving siblings information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save Others Information Section
     */
    public function saveOthersInfoSection(Request $request)
    {
        try {
            $clientId = $request->input('id'); // Use 'id' instead of 'client_id' - 'id' is the database ID
            $client = Admin::where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
            
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found'
                ], 404);
            }

            $othersData = $request->input('others', []);
            
            // Delete existing other relationships for this client
            ClientRelationship::where('client_id', $client->id)
                ->whereIn('relationship_type', ['Cousin', 'Friend', 'Uncle', 'Aunt', 'Grandchild', 'Granddaughter', 'Grandparent', 'Niece', 'Nephew', 'Grandfather', 'Son-in-law', 'Daughter-in-law', 'Brother-in-law', 'Sister-in-law'])
                ->delete();

            $othersCount = 0;
            foreach ($othersData as $otherData) {
                if (empty($otherData['relationship_type'])) {
                    continue;
                }
                $othersCount++;

                $saveExtraFields = empty($otherData['details']) || trim($otherData['details']) === '';
                
                // Format DOB from d/m/Y to Y-m-d
                $dobFormatted = null;
                if ($saveExtraFields && !empty($otherData['dob']) && $otherData['dob'] !== 'dd/mm/yyyy') {
                    try {
                        $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $otherData['dob']);
                        $dobFormatted = $dobDate->format('Y-m-d');
                    } catch (\Exception $e) {
                        Log::warning('Invalid DOB format for other: ' . $otherData['dob']);
                        $dobFormatted = null;
                    }
                }

                // For existing clients (details not empty), use details as-is
                // For new clients (details empty), construct details from first_name, last_name, email, phone
                $primaryDetails = '';
                if (!$saveExtraFields) {
                    // Existing client - use details as-is
                    $primaryDetails = $otherData['details'];
                } else {
                    // New client - construct details from individual fields
                    $detailsParts = [];
                    if (!empty($otherData['first_name'])) $detailsParts[] = trim($otherData['first_name']);
                    if (!empty($otherData['last_name'])) $detailsParts[] = trim($otherData['last_name']);
                    if (!empty($otherData['email'])) $detailsParts[] = trim($otherData['email']);
                    if (!empty($otherData['phone'])) $detailsParts[] = trim($otherData['phone']);
                    $primaryDetails = implode(', ', $detailsParts);
                }

                // Save primary other relationship
                $otherRelationship = ClientRelationship::create([
                    'admin_id' => Auth::id(),
                    'client_id' => $client->id,
                    'related_client_id' => null,
                    'relationship_type' => $otherData['relationship_type'],
                    'details' => $primaryDetails,
                    'email' => $saveExtraFields ? ($otherData['email'] ?? null) : null,
                    'first_name' => $saveExtraFields ? ($otherData['first_name'] ?? null) : null,
                    'last_name' => $saveExtraFields ? ($otherData['last_name'] ?? null) : null,
                    'phone' => $saveExtraFields ? ($otherData['phone'] ?? null) : null,
                    'dob' => $dobFormatted,
                    'gender' => $otherData['gender'] ?? null,
                    'company_type' => $otherData['company_type'] ?? null
                ]);

                // Create reciprocal relationship if related client exists
                if (!empty($otherData['details']) && trim($otherData['details']) !== '') {
                    // Try to find related client by details
                    $relatedClient = $this->findRelatedClientByDetails($otherData['details']);
                    
                    if ($relatedClient) {
                        // Update primary relationship with related client ID
                        $otherRelationship->update(['related_client_id' => $relatedClient->id]);

                        // Determine reciprocal relationship type (uses other's gender or client's gender for in-laws)
                        $reciprocalRelationshipType = $this->getReciprocalRelationshipForOther($otherData['relationship_type'], $otherData['gender'] ?? '', $client->gender ?? '');
                        
                        // Check if reciprocal relationship already exists
                        $existingReciprocal = ClientRelationship::where('client_id', $relatedClient->id)
                            ->where('related_client_id', $client->id)
                            ->where('relationship_type', $reciprocalRelationshipType)
                            ->first();

                        // Create reciprocal relationship if it doesn't exist
                        if (!$existingReciprocal) {
                            ClientRelationship::create([
                                'admin_id' => Auth::id(),
                                'client_id' => $relatedClient->id,
                                'related_client_id' => $client->id,
                                'relationship_type' => $reciprocalRelationshipType,
                                'details' => $client->first_name . ' ' . $client->last_name . ' (' . $client->email . ', ' . $client->phone . ', ' . $client->client_id . ')',
                                'email' => null,
                                'first_name' => null,
                                'last_name' => null,
                                'phone' => null,
                                'dob' => null,
                                'company_type' => null, // Reciprocal entries should have null company_type
                                'gender' => $client->gender ?? null
                            ]);
                        }
                    }
                }
            }

            // Log activity for others information update
            $this->logClientActivity(
                $client->id,
                'updated other relationships',
                "Updated {$othersCount} other relationship record(s)",
                'activity'
            );

            return response()->json([
                'success' => true,
                'message' => 'Others information updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving others information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find related client by details string
     * The details string contains client information like "John Doe (john@email.com, +1234567890, CLI123)"
     */
    private function findRelatedClientByDetails($details)
    {
        if (empty($details) || trim($details) === '') {
            return null;
        }

        // Extract email, phone, and client_id from the details string
        // Format: "Name (email, phone, client_id)"
        $email = null;
        $phone = null;
        $clientId = null;
        
        // Try to extract email from parentheses
        if (preg_match('/\(([^,]+),/', $details, $matches)) {
            $email = trim($matches[1]);
        }
        
        // Try to extract phone (second item in parentheses)
        if (preg_match('/\([^,]+,([^,]+),/', $details, $matches)) {
            $phone = trim($matches[1]);
        }
        
        // Try to extract client_id (third item in parentheses)
        if (preg_match('/\([^,]+,[^,]+,([^)]+)\)/', $details, $matches)) {
            $clientId = trim($matches[1]);
        }

        // Search for client by email first (most reliable)
        if ($email) {
            // For universal email (demo@gmail.com), also search for timestamped versions
            if ($email === 'demo@gmail.com') {
                $emailLower = strtolower($email);
                $client = Admin::whereIn('type', ['client', 'lead'])
                    ->where(function($q) use ($email, $emailLower) {
                        $q->whereRaw('LOWER(email) = ?', [$emailLower])
                          ->orWhereRaw('LOWER(email) LIKE ?', ['demo_%@gmail.com']);
                    })
                    ->first();
            } else {
                $client = Admin::whereIn('type', ['client', 'lead'])
                    ->where('email', $email)
                    ->first();
            }
            if ($client) {
                return $client;
            }
        }

        // Search by client_id if email not found
        if ($clientId) {
            $client = Admin::whereIn('type', ['client', 'lead'])
                ->where('client_id', $clientId)
                ->first();
            if ($client) {
                return $client;
            }
        }

        // Search by phone if email and client_id not found
        if ($phone) {
            // For universal phone (4444444444), also search for timestamped versions
            if ($phone === '4444444444') {
                $client = Admin::whereIn('type', ['client', 'lead'])
                    ->where(function($q) use ($phone) {
                        $q->where('phone', $phone)
                          ->orWhere('phone', 'LIKE', $phone . '_%');
                    })
                    ->first();
            } else {
                $client = Admin::whereIn('type', ['client', 'lead'])
                    ->where('phone', $phone)
                    ->first();
            }
            if ($client) {
                return $client;
            }
        }

        // Do not link by name only: when Details is just a name (e.g. "Gurmail Singh") with no
        // email/phone/client_id, we do not search by first_name+last_name. That would incorrectly
        // link this parent to another client who happens to share the same name.
        return null;
    }

    /**
     * Get reciprocal relationship for parent
     */
    private function getReciprocalRelationshipForParent($parentRelationship, $parentGender)
    {
        switch ($parentRelationship) {
            case 'Father':
                return $parentGender === 'Female' ? 'Daughter' : 'Son';
            case 'Mother':
                return $parentGender === 'Female' ? 'Daughter' : 'Son';
            case 'Step Father':
                return $parentGender === 'Female' ? 'Step Daughter' : 'Step Son';
            case 'Step Mother':
                return $parentGender === 'Female' ? 'Step Daughter' : 'Step Son';
            case 'Mother-in-law':
                return $parentGender === 'Female' ? 'Daughter' : 'Son';
            case 'Father-in-law':
                return $parentGender === 'Female' ? 'Daughter' : 'Son';
            default:
                return 'Child';
        }
    }

    /**
     * Get reciprocal relationship for sibling
     * If client has a Brother, the reciprocal is Brother (if client is Male) or Sister (if client is Female)
     * If client has a Sister, the reciprocal is Sister (if client is Female) or Brother (if client is Male)
     */
    private function getReciprocalRelationshipForSibling($siblingRelationship, $clientGender)
    {
        switch ($siblingRelationship) {
            case 'Brother':
                return $clientGender === 'Female' ? 'Sister' : 'Brother';
            case 'Sister':
                return $clientGender === 'Female' ? 'Sister' : 'Brother';
            case 'Step Brother':
                return $clientGender === 'Female' ? 'Step Sister' : 'Step Brother';
            case 'Step Sister':
                return $clientGender === 'Female' ? 'Step Sister' : 'Step Brother';
            default:
                return 'Sibling';
        }
    }

    /**
     * Get reciprocal relationship for other.
     * For in-law types (Son-in-law, Daughter-in-law, Brother-in-law, Sister-in-law), reciprocal is based on client's gender.
     * For other types, reciprocal is based on the other person's gender.
     *
     * @param string $otherRelationship Relationship type on the client's record
     * @param string $otherGender Gender of the "other" person (used for Uncle/Aunt/Niece/Nephew/Grand*, etc.)
     * @param string $clientGender Gender of the client (used for Son-in-law, Daughter-in-law, Brother-in-law, Sister-in-law)
     */
    private function getReciprocalRelationshipForOther($otherRelationship, $otherGender, $clientGender = '')
    {
        switch ($otherRelationship) {
            case 'Uncle':
                return $otherGender === 'Female' ? 'Niece' : 'Nephew';
            case 'Aunt':
                return $otherGender === 'Female' ? 'Niece' : 'Nephew';
            case 'Niece':
                return $otherGender === 'Female' ? 'Aunt' : 'Uncle';
            case 'Nephew':
                return $otherGender === 'Female' ? 'Aunt' : 'Uncle';
            case 'Grandfather':
                return $otherGender === 'Female' ? 'Granddaughter' : 'Grandchild';
            case 'Grandparent':
                return $otherGender === 'Female' ? 'Granddaughter' : 'Grandchild';
            case 'Grandchild':
                return $otherGender === 'Female' ? 'Grandmother' : 'Grandfather';
            case 'Granddaughter':
                return $otherGender === 'Female' ? 'Grandmother' : 'Grandfather';
            case 'Cousin':
                return 'Cousin';
            case 'Friend':
                return 'Friend';
            // In-law reciprocals: based on client's gender
            case 'Son-in-law':
                return $clientGender === 'Female' ? 'Mother-in-law' : 'Father-in-law';
            case 'Daughter-in-law':
                return $clientGender === 'Female' ? 'Mother-in-law' : 'Father-in-law';
            case 'Brother-in-law':
                return $clientGender === 'Female' ? 'Sister-in-law' : 'Brother-in-law';
            case 'Sister-in-law':
                return $clientGender === 'Female' ? 'Sister-in-law' : 'Brother-in-law';
            default:
                return 'Other';
        }
    }

    /**
     * Build address diff showing only actual changes
     * 
     * @param Collection $oldAddresses Old addresses before save
     * @param Collection $newAddresses New addresses after save
     * @return array Array with 'added', 'removed', 'modified' keys
     */
    private function buildAddressDiff($oldAddresses, $newAddresses)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        // Normalize addresses for comparison
        $oldNormalized = $this->normalizeAddressesForComparison($oldAddresses);
        $newNormalized = $this->normalizeAddressesForComparison($newAddresses);
        
        // Find added addresses (in new but not in old)
        foreach ($newNormalized as $newKey => $newAddr) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatAddressForDisplay($newAddr);
            }
        }
        
        // Find removed addresses (in old but not in new)
        foreach ($oldNormalized as $oldKey => $oldAddr) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatAddressForDisplay($oldAddr);
            }
        }
        
        // Find modified addresses (same key but different details)
        foreach ($oldNormalized as $key => $oldAddr) {
            if (isset($newNormalized[$key])) {
                $newAddr = $newNormalized[$key];
                if ($this->isAddressModified($oldAddr, $newAddr)) {
                    $modified[] = [
                        'old' => $this->formatAddressForDisplay($oldAddr),
                        'new' => $this->formatAddressForDisplay($newAddr)
                    ];
                }
            }
        }
        
        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified
        ];
    }

    /**
     * Normalize addresses for comparison by creating a unique key
     * 
     * @param Collection $addresses
     * @return array Array keyed by comparison key
     */
    private function normalizeAddressesForComparison($addresses)
    {
        $normalized = [];
        
        foreach ($addresses as $address) {
            // Create comparison key from core address fields
            $key = strtolower(trim(
                ($address->address_line_1 ?? '') . '|' .
                ($address->suburb ?? '') . '|' .
                ($address->state ?? '') . '|' .
                ($address->zip ?? '')
            ));
            
            // Store full address object with the key
            $normalized[$key] = $address;
        }
        
        return $normalized;
    }

    /**
     * Format address for display in activity log
     * 
     * @param object $address Address object
     * @return string Formatted address string
     */
    private function formatAddressForDisplay($address)
    {
        $parts = [];
        
        if (!empty($address->address_line_1)) {
            $parts[] = $address->address_line_1;
        }
        if (!empty($address->address_line_2)) {
            $parts[] = $address->address_line_2;
        }
        if (!empty($address->suburb)) {
            $parts[] = $address->suburb;
        }
        if (!empty($address->state)) {
            $parts[] = $address->state;
        }
        if (!empty($address->zip)) {
            $parts[] = $address->zip;
        }
        if (!empty($address->country)) {
            $parts[] = $address->country;
        }
        if (!empty($address->start_date)) {
            $parts[] = 'From: ' . date('d/m/Y', strtotime($address->start_date));
        }
        if (!empty($address->end_date)) {
            $parts[] = 'To: ' . date('d/m/Y', strtotime($address->end_date));
        }
        
        return !empty($parts) ? implode(', ', $parts) : 'Address record';
    }

    /**
     * Check if an address has been modified (same location, different details)
     * 
     * @param object $oldAddr Old address
     * @param object $newAddr New address
     * @return bool True if modified
     */
    private function isAddressModified($oldAddr, $newAddr)
    {
        // Compare fields that might change
        $fieldsToCompare = [
            'address_line_2',
            'country',
            'regional_code',
            'start_date',
            'end_date'
        ];
        
        foreach ($fieldsToCompare as $field) {
            $oldValue = $oldAddr->$field ?? null;
            $newValue = $newAddr->$field ?? null;
            
            if ($oldValue !== $newValue) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format address diff result for activity log with HTML styling
     * 
     * @param array $diffResult Result from buildAddressDiff()
     * @return string HTML formatted description
     */
    private function formatAddressDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        // Removed addresses (red strikethrough)
        foreach ($diffResult['removed'] as $addr) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($addr);
            $html .= '</span>';
            $html .= '</div>';
        }
        
        // Modified addresses (old in red strikethrough → new in green)
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span>';
            $html .= ' <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span>';
            $html .= '</div>';
        }
        
        // Added addresses (green)
        foreach ($diffResult['added'] as $addr) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($addr);
            $html .= '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Build qualification diff showing only actual changes
     */
    private function buildQualificationDiff($oldQualifications, $newQualifications)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeQualificationsForComparison($oldQualifications);
        $newNormalized = $this->normalizeQualificationsForComparison($newQualifications);
        
        // Find added qualifications
        foreach ($newNormalized as $newKey => $newQual) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatQualificationForDisplay($newQual);
            }
        }
        
        // Find removed qualifications
        foreach ($oldNormalized as $oldKey => $oldQual) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatQualificationForDisplay($oldQual);
            }
        }
        
        // Find modified qualifications
        foreach ($oldNormalized as $key => $oldQual) {
            if (isset($newNormalized[$key])) {
                $newQual = $newNormalized[$key];
                if ($this->isQualificationModified($oldQual, $newQual)) {
                    $modified[] = [
                        'old' => $this->formatQualificationForDisplay($oldQual),
                        'new' => $this->formatQualificationForDisplay($newQual)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeQualificationsForComparison($qualifications)
    {
        $normalized = [];
        foreach ($qualifications as $qual) {
            $key = strtolower(trim(
                ($qual->level ?? '') . '|' .
                ($qual->name ?? '') . '|' .
                ($qual->qual_college_name ?? '')
            ));
            $normalized[$key] = $qual;
        }
        return $normalized;
    }

    private function formatQualificationForDisplay($qual)
    {
        $parts = [];
        if (!empty($qual->level)) $parts[] = 'Level: ' . $qual->level;
        if (!empty($qual->name)) $parts[] = 'Name: ' . $qual->name;
        if (!empty($qual->qual_college_name)) $parts[] = 'College: ' . $qual->qual_college_name;
        if (!empty($qual->country)) $parts[] = 'Country: ' . $qual->country;
        if (!empty($qual->start_date)) $parts[] = 'Start: ' . date('d/m/Y', strtotime($qual->start_date));
        if (!empty($qual->finish_date)) $parts[] = 'Finish: ' . date('d/m/Y', strtotime($qual->finish_date));
        return !empty($parts) ? implode(', ', $parts) : 'Qualification record';
    }

    private function isQualificationModified($oldQual, $newQual)
    {
        $fieldsToCompare = ['qual_campus', 'qual_state', 'country', 'start_date', 'finish_date', 'relevant_qualification'];
        foreach ($fieldsToCompare as $field) {
            if (($oldQual->$field ?? null) !== ($newQual->$field ?? null)) {
                return true;
            }
        }
        return false;
    }

    private function formatQualificationDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $qual) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($qual);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $qual) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($qual);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Build experience diff showing only actual changes
     */
    private function buildExperienceDiff($oldExperiences, $newExperiences)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeExperiencesForComparison($oldExperiences);
        $newNormalized = $this->normalizeExperiencesForComparison($newExperiences);
        
        // Find added experiences
        foreach ($newNormalized as $newKey => $newExp) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatExperienceForDisplay($newExp);
            }
        }
        
        // Find removed experiences
        foreach ($oldNormalized as $oldKey => $oldExp) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatExperienceForDisplay($oldExp);
            }
        }
        
        // Find modified experiences
        foreach ($oldNormalized as $key => $oldExp) {
            if (isset($newNormalized[$key])) {
                $newExp = $newNormalized[$key];
                if ($this->isExperienceModified($oldExp, $newExp)) {
                    $modified[] = [
                        'old' => $this->formatExperienceForDisplay($oldExp),
                        'new' => $this->formatExperienceForDisplay($newExp)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeExperiencesForComparison($experiences)
    {
        $normalized = [];
        foreach ($experiences as $exp) {
            $key = strtolower(trim(
                ($exp->job_title ?? '') . '|' .
                ($exp->job_code ?? '') . '|' .
                ($exp->job_emp_name ?? '')
            ));
            $normalized[$key] = $exp;
        }
        return $normalized;
    }

    private function formatExperienceForDisplay($exp)
    {
        $parts = [];
        if (!empty($exp->job_title)) $parts[] = 'Title: ' . $exp->job_title;
        if (!empty($exp->job_code)) $parts[] = 'Code: ' . $exp->job_code;
        if (!empty($exp->job_emp_name)) $parts[] = 'Employer: ' . $exp->job_emp_name;
        if (!empty($exp->job_country)) $parts[] = 'Country: ' . $exp->job_country;
        if (!empty($exp->job_start_date)) $parts[] = 'Start: ' . date('d/m/Y', strtotime($exp->job_start_date));
        if (!empty($exp->job_finish_date)) $parts[] = 'Finish: ' . date('d/m/Y', strtotime($exp->job_finish_date));
        return !empty($parts) ? implode(', ', $parts) : 'Experience record';
    }

    private function isExperienceModified($oldExp, $newExp)
    {
        $fieldsToCompare = ['job_country', 'job_state', 'job_type', 'job_start_date', 'job_finish_date', 'relevant_experience'];
        foreach ($fieldsToCompare as $field) {
            if (($oldExp->$field ?? null) !== ($newExp->$field ?? null)) {
                return true;
            }
        }
        return false;
    }

    private function formatExperienceDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $exp) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($exp);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $exp) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($exp);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Build travel diff showing only actual changes
     */
    private function buildTravelDiff($oldTravels, $newTravels)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeTravelsForComparison($oldTravels);
        $newNormalized = $this->normalizeTravelsForComparison($newTravels);
        
        foreach ($newNormalized as $newKey => $newTravel) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatTravelForDisplay($newTravel);
            }
        }
        
        foreach ($oldNormalized as $oldKey => $oldTravel) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatTravelForDisplay($oldTravel);
            }
        }
        
        foreach ($oldNormalized as $key => $oldTravel) {
            if (isset($newNormalized[$key])) {
                $newTravel = $newNormalized[$key];
                if ($this->isTravelModified($oldTravel, $newTravel)) {
                    $modified[] = [
                        'old' => $this->formatTravelForDisplay($oldTravel),
                        'new' => $this->formatTravelForDisplay($newTravel)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeTravelsForComparison($travels)
    {
        $normalized = [];
        foreach ($travels as $travel) {
            $key = strtolower(trim(
                ($travel->travel_country_visited ?? '') . '|' .
                ($travel->travel_arrival_date ?? '') . '|' .
                ($travel->travel_departure_date ?? '')
            ));
            $normalized[$key] = $travel;
        }
        return $normalized;
    }

    private function formatTravelForDisplay($travel)
    {
        $parts = [];
        if (!empty($travel->travel_country_visited)) $parts[] = 'Country: ' . $travel->travel_country_visited;
        if (!empty($travel->travel_arrival_date)) $parts[] = 'Arrival: ' . date('d/m/Y', strtotime($travel->travel_arrival_date));
        if (!empty($travel->travel_departure_date)) $parts[] = 'Departure: ' . date('d/m/Y', strtotime($travel->travel_departure_date));
        if (!empty($travel->travel_purpose)) $parts[] = 'Purpose: ' . $travel->travel_purpose;
        return !empty($parts) ? implode(', ', $parts) : 'Travel record';
    }

    private function isTravelModified($oldTravel, $newTravel)
    {
        $fieldsToCompare = ['travel_purpose'];
        foreach ($fieldsToCompare as $field) {
            if (($oldTravel->$field ?? null) !== ($newTravel->$field ?? null)) {
                return true;
            }
        }
        return false;
    }

    private function formatTravelDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $travel) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($travel);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $travel) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($travel);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Build passport diff showing only actual changes
     */
    private function buildPassportDiff($oldPassports, $newPassports)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizePassportsForComparison($oldPassports);
        $newNormalized = $this->normalizePassportsForComparison($newPassports);
        
        foreach ($newNormalized as $newKey => $newPassport) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatPassportForDisplay($newPassport);
            }
        }
        
        foreach ($oldNormalized as $oldKey => $oldPassport) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatPassportForDisplay($oldPassport);
            }
        }
        
        foreach ($oldNormalized as $key => $oldPassport) {
            if (isset($newNormalized[$key])) {
                $newPassport = $newNormalized[$key];
                if ($this->isPassportModified($oldPassport, $newPassport)) {
                    $modified[] = [
                        'old' => $this->formatPassportForDisplay($oldPassport),
                        'new' => $this->formatPassportForDisplay($newPassport)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizePassportsForComparison($passports)
    {
        $normalized = [];
        foreach ($passports as $passport) {
            $key = strtolower(trim(
                ($passport->passport_country ?? '') . '|' .
                ($passport->passport ?? '')
            ));
            $normalized[$key] = $passport;
        }
        return $normalized;
    }

    private function formatPassportForDisplay($passport)
    {
        $parts = [];
        if (!empty($passport->passport_country)) $parts[] = 'Country: ' . $passport->passport_country;
        if (!empty($passport->passport)) $parts[] = 'Number: ' . $passport->passport;
        if (!empty($passport->passport_issue_date)) $parts[] = 'Issue: ' . date('d/m/Y', strtotime($passport->passport_issue_date));
        if (!empty($passport->passport_expiry_date)) $parts[] = 'Expiry: ' . date('d/m/Y', strtotime($passport->passport_expiry_date));
        return !empty($parts) ? implode(', ', $parts) : 'Passport record';
    }

    private function isPassportModified($oldPassport, $newPassport)
    {
        $fieldsToCompare = ['passport_issue_date', 'passport_expiry_date'];
        foreach ($fieldsToCompare as $field) {
            if (($oldPassport->$field ?? null) !== ($newPassport->$field ?? null)) {
                return true;
            }
        }
        return false;
    }

    private function formatPassportDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $passport) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($passport);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $passport) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($passport);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Build visa diff showing only actual changes
     */
    private function buildVisaDiff($oldVisas, $newVisas)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeVisasForComparison($oldVisas);
        $newNormalized = $this->normalizeVisasForComparison($newVisas);
        
        foreach ($newNormalized as $newKey => $newVisa) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatVisaForDisplay($newVisa);
            }
        }
        
        foreach ($oldNormalized as $oldKey => $oldVisa) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatVisaForDisplay($oldVisa);
            }
        }
        
        foreach ($oldNormalized as $key => $oldVisa) {
            if (isset($newNormalized[$key])) {
                $newVisa = $newNormalized[$key];
                if ($this->isVisaModified($oldVisa, $newVisa)) {
                    $modified[] = [
                        'old' => $this->formatVisaForDisplay($oldVisa),
                        'new' => $this->formatVisaForDisplay($newVisa)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeVisasForComparison($visas)
    {
        $normalized = [];
        foreach ($visas as $visa) {
            $key = strtolower(trim((string) ($visa->visa_type ?? '') . '|' . ($visa->visa_expiry_date ?? '')));
            $normalized[$key] = $visa;
        }
        return $normalized;
    }

    private function formatVisaForDisplay($visa)
    {
        $parts = [];
        if (!empty($visa->visa_type)) $parts[] = 'Type: ' . $visa->visa_type;
        if (!empty($visa->visa_grant_date)) $parts[] = 'Grant: ' . date('d/m/Y', strtotime($visa->visa_grant_date));
        if (!empty($visa->visa_expiry_date)) $parts[] = 'Expiry: ' . date('d/m/Y', strtotime($visa->visa_expiry_date));
        if (!empty($visa->visa_description)) $parts[] = 'Desc: ' . $visa->visa_description;
        return !empty($parts) ? implode(', ', $parts) : 'Visa record';
    }

    private function isVisaModified($oldVisa, $newVisa)
    {
        $fieldsToCompare = ['visa_grant_date', 'visa_expiry_date', 'visa_description'];
        foreach ($fieldsToCompare as $field) {
            if (($oldVisa->$field ?? null) !== ($newVisa->$field ?? null)) {
                return true;
            }
        }
        return false;
    }

    private function formatVisaDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $visa) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($visa);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $visa) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($visa);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Build email diff showing only actual changes
     */
    private function buildEmailDiff($oldEmails, $newEmails)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeEmailsForComparison($oldEmails);
        $newNormalized = $this->normalizeEmailsForComparison($newEmails);
        
        foreach ($newNormalized as $newKey => $newEmail) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatEmailForDisplay($newEmail);
            }
        }
        
        foreach ($oldNormalized as $oldKey => $oldEmail) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatEmailForDisplay($oldEmail);
            }
        }
        
        foreach ($oldNormalized as $key => $oldEmail) {
            if (isset($newNormalized[$key])) {
                $newEmail = $newNormalized[$key];
                if ($this->isEmailModified($oldEmail, $newEmail)) {
                    $modified[] = [
                        'old' => $this->formatEmailForDisplay($oldEmail),
                        'new' => $this->formatEmailForDisplay($newEmail)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeEmailsForComparison($emails)
    {
        $normalized = [];
        foreach ($emails as $email) {
            $key = strtolower(trim($email->email ?? ''));
            $normalized[$key] = $email;
        }
        return $normalized;
    }

    private function formatEmailForDisplay($email)
    {
        $display = $email->email ?? '';
        if (!empty($email->email_type)) {
            $display .= ' (' . $email->email_type . ')';
        }
        return $display;
    }

    private function isEmailModified($oldEmail, $newEmail)
    {
        return ($oldEmail->email_type ?? null) !== ($newEmail->email_type ?? null);
    }

    private function formatEmailDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $email) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($email);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $email) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($email);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Build character diff showing only actual changes
     */
    private function buildCharacterDiff($oldCharacters, $newCharacters)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeCharactersForComparison($oldCharacters);
        $newNormalized = $this->normalizeCharactersForComparison($newCharacters);
        
        foreach ($newNormalized as $newKey => $newChar) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatCharacterForDisplay($newChar);
            }
        }
        
        foreach ($oldNormalized as $oldKey => $oldChar) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatCharacterForDisplay($oldChar);
            }
        }
        
        foreach ($oldNormalized as $key => $oldChar) {
            if (isset($newNormalized[$key])) {
                $newChar = $newNormalized[$key];
                if ($this->isCharacterModified($oldChar, $newChar)) {
                    $modified[] = [
                        'old' => $this->formatCharacterForDisplay($oldChar),
                        'new' => $this->formatCharacterForDisplay($newChar)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeCharactersForComparison($characters)
    {
        $normalized = [];
        foreach ($characters as $char) {
            $key = strtolower(trim(
                ($char->type_of_character ?? '') . '|' .
                substr($char->character_detail ?? '', 0, 50)
            ));
            $normalized[$key] = $char;
        }
        return $normalized;
    }

    private function formatCharacterForDisplay($char)
    {
        $parts = [];
        if (!empty($char->type_of_character)) $parts[] = 'Type: ' . $char->type_of_character;
        if (!empty($char->character_detail)) {
            $detail = strlen($char->character_detail) > 100 
                ? substr($char->character_detail, 0, 100) . '...' 
                : $char->character_detail;
            $parts[] = 'Detail: ' . $detail;
        }
        return !empty($parts) ? implode(', ', $parts) : 'Character record';
    }

    private function isCharacterModified($oldChar, $newChar)
    {
        return ($oldChar->character_detail ?? null) !== ($newChar->character_detail ?? null);
    }

    private function formatCharacterDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $char) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($char);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $char) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($char);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }
}
