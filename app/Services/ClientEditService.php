<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientCharacter;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientEoiReference;
use App\Models\ClientExperience;
use App\Models\ClientMatter;
use App\Models\ClientOccupation;
use App\Models\ClientPassportInformation;
use App\Models\ClientQualification;
use App\Models\ClientRelationship;
use App\Models\ClientSpouseDetail;
use App\Models\ClientTestScore;
use App\Models\ClientTravelInformation;
use App\Models\ClientVisaCountry;
use App\Models\Country;
use App\Models\Matter;
use App\Models\Staff;

/**
 * ClientEditService
 *
 * Handles data preparation for client edit page with optimized queries.
 * Eliminates N+1 query problems by eager loading relationships and
 * loading dropdown data once.
 *
 * Used by:
 * - ClientsController@edit
 * - ClientPersonalDetailsController@clientdetailsinfo
 */
class ClientEditService
{
    /**
     * Get all data needed for client edit page with optimized queries
     *
     * @return array<string, mixed>
     */
    public function getClientEditData(int $clientId): array
    {
        $clientData = $this->getClientData($clientId);

        if (! $clientData) {
            return [
                'fetchedData' => null,
            ];
        }

        return [
            'fetchedData' => $clientData,
            'clientContacts' => $this->getClientContacts($clientId, $clientData),
            'emails' => $this->getClientEmails($clientId, $clientData),
            'visaCountries' => $this->getVisaCountries($clientId),
            'clientAddresses' => $this->getClientAddresses($clientId),
            'qualifications' => $this->getQualifications($clientId),
            'experiences' => $this->getExperiences($clientId),
            'clientOccupations' => $this->getOccupations($clientId),
            'testScores' => $this->getTestScores($clientId),
            'ClientSpouseDetail' => $this->getSpouseDetail($clientId), // Keep for backward compatibility
            'clientPassports' => $this->getPassports($clientId),
            'clientTravels' => $this->getTravels($clientId),
            'clientCharacters' => $this->getCharacters($clientId),
            'clientPartners' => $this->getRelationships($clientId),
            'clientEoiReferences' => $this->getEoiReferences($clientId),

            // Dropdown data - loaded ONCE to prevent N+1 queries
            'visaTypes' => $this->getVisaTypes(),
            'countries' => $this->getCountries(),
            'latestMatterRefNo' => $this->getLatestMatterRefNo($clientId, (string) $clientData->type),
            'detailsVerifiedByName' => $this->resolveDetailsVerifiedByName($clientData),
        ];
    }

    /**
     * Get client basic data with partner (and company, when needed) eager loaded.
     * Company graph is skipped for individual clients.
     */
    protected function getClientData(int $clientId)
    {
        $client = Admin::query()
            ->whereIn('type', ['client', 'lead'])
            ->with([
                'partner',
                'detailsVerifiedByStaff:id,first_name,last_name,email',
            ])
            ->find($clientId);

        if ($client && $client->is_company) {
            $client->load([
                'company.contactPerson',
                'company.tradingNames',
                'company.directors.directorClient',
                'company.nominations.nominatedClient',
                'company.sponsorships',
                'company.financials',
            ]);
        }

        return $client;
    }

    /**
     * Get client contact numbers
     * Falls back to admins table if no records in client_contacts
     * Always returns ClientContact models for consistency
     */
    protected function getClientContacts(int $clientId, ?Admin $admin = null)
    {
        $contacts = ClientContact::where('client_id', $clientId)->get();
        if ($contacts->isNotEmpty()) {
            return $contacts;
        }

        $admin ??= Admin::where('id', $clientId)->first();
        if ($admin && ! empty($admin->phone)) {
            $clientContact = new ClientContact;
            $clientContact->id = null;
            $clientContact->client_id = $clientId;
            $clientContact->contact_type = $admin->contact_type ?? 'Personal';
            $clientContact->country_code = $admin->country_code ?? '';
            $clientContact->phone = $admin->phone;
            $clientContact->is_verified = false;
            $clientContact->verified_at = null;
            $clientContact->verified_by = null;
            $clientContact->exists = false;

            return collect([$clientContact]);
        }

        return collect();
    }

    /**
     * Get client email addresses
     * Falls back to admins table if no records in client_emails
     * Always returns ClientEmail models for consistency
     */
    protected function getClientEmails(int $clientId, ?Admin $admin = null)
    {
        $emails = ClientEmail::where('client_id', $clientId)->get();
        if ($emails->isNotEmpty()) {
            return $emails;
        }

        $admin ??= Admin::where('id', $clientId)->first();
        if ($admin && ! empty($admin->email)) {
            return collect([$this->makeAdminEmailFallback($clientId, $admin)]);
        }

        return collect();
    }

    /**
     * Unsaved ClientEmail from the admins row, matching getClientContacts().
     */
    protected function makeAdminEmailFallback(int $clientId, Admin $admin): ClientEmail
    {
        $clientEmail = new ClientEmail;
        $clientEmail->id = null;
        $clientEmail->client_id = $clientId;
        $clientEmail->email = $admin->email;
        $clientEmail->email_type = $admin->email_type ?? 'Personal';
        $clientEmail->is_verified = false;
        $clientEmail->verified_at = null;
        $clientEmail->verified_by = null;
        $clientEmail->exists = false;

        return $clientEmail;
    }

    /**
     * Get visa countries with eager loaded matter relationship
     * Prevents N+1 query when accessing visa->matter in blade
     */
    protected function getVisaCountries(int $clientId)
    {
        return ClientVisaCountry::where('client_id', $clientId)
            ->with(['matter:id,title,nick_name'])
            ->orderBy('visa_expiry_date', 'desc')
            ->get() ?? [];
    }

    /**
     * Get client addresses
     */
    protected function getClientAddresses(int $clientId)
    {
        return ClientAddress::where('client_id', $clientId)
            ->orderedForDisplay()
            ->get() ?? [];
    }

    /**
     * Get educational qualifications
     */
    protected function getQualifications(int $clientId)
    {
        return ClientQualification::where('client_id', $clientId)->orderByRaw('finish_date DESC NULLS LAST')->get() ?? [];
    }

    /**
     * Get work experiences
     */
    protected function getExperiences(int $clientId)
    {
        return ClientExperience::where('client_id', $clientId)->orderedForDisplay()->get() ?? [];
    }

    /**
     * Get occupations
     */
    protected function getOccupations(int $clientId)
    {
        return ClientOccupation::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get test scores
     */
    protected function getTestScores(int $clientId)
    {
        return ClientTestScore::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get spouse details
     */
    protected function getSpouseDetail(int $clientId)
    {
        return ClientSpouseDetail::where('client_id', $clientId)->first() ?? [];
    }

    /**
     * Get passport information
     */
    protected function getPassports(int $clientId)
    {
        return ClientPassportInformation::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get travel information ordered by arrival date (oldest first)
     * NULL dates are placed at the end
     */
    protected function getTravels(int $clientId)
    {
        return ClientTravelInformation::where('client_id', $clientId)
            ->orderByRaw('travel_arrival_date DESC NULLS LAST, created_at DESC')
            ->get() ?? [];
    }

    /**
     * Get character information
     */
    protected function getCharacters(int $clientId)
    {
        return ClientCharacter::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get family relationships with eager loaded related client
     * Prevents N+1 query when accessing partner->relatedClient in blade
     */
    protected function getRelationships(int $clientId)
    {
        return ClientRelationship::where('client_id', $clientId)
            ->with(['relatedClient:id,first_name,last_name,email,phone,client_id'])
            ->get() ?? [];
    }

    /**
     * Get EOI references
     */
    protected function getEoiReferences(int $clientId)
    {
        return ClientEoiReference::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get visa types for dropdown
     * Loaded once and passed to view to prevent multiple queries
     */
    protected function getVisaTypes()
    {
        return Matter::select('id', 'title', 'nick_name')
            ->where('title', 'not like', '%skill assessment%')
            ->where('status', 1)
            ->orderBy('title', 'ASC')
            ->get();
    }

    /**
     * Get countries for dropdown
     * Loaded once and passed to view to prevent N+1 query in passport loop
     */
    protected function getCountries()
    {
        return Country::query()
            ->whereDialCodePresent()
            ->select('id', 'name', 'sortname', 'phonecode')
            ->orderBy('name', 'ASC')
            ->get();
    }

    protected function getLatestMatterRefNo(int $clientId, string $type): ?string
    {
        if ($type !== 'client') {
            return null;
        }

        return ClientMatter::where('client_id', $clientId)
            ->where('matter_status', 1)
            ->orderByDesc('id')
            ->value('client_unique_matter_no');
    }

    protected function resolveDetailsVerifiedByName(Admin $client): ?string
    {
        if (! $client->details_verified_by) {
            return null;
        }

        $name = $client->detailsVerifiedByStaff?->full_name;
        if (filled($name)) {
            return $name;
        }

        return Staff::query()->whereKey($client->details_verified_by)->first()?->full_name;
    }
}
