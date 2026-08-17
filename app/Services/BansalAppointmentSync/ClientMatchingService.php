<?php

namespace App\Services\BansalAppointmentSync;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Services\ClientReferenceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ClientMatchingService
{
    /**
     * Find or create client from appointment data
     */
    public function findOrCreateClient(array $appointmentData): ?Admin
    {
        $email = $appointmentData['email'] ?? null;
        $phone = $appointmentData['phone'] ?? null;
        $fullName = $appointmentData['full_name'] ?? null;

        if (! $email && ! $phone) {
            Log::warning('Cannot match/create client without email or phone', [
                'appointment_id' => $appointmentData['id'] ?? null,
            ]);

            return null;
        }

        $client = Admin::findClientOrLeadByNormalizedEmail($email);
        if ($client) {
            Log::info('Found existing client by email', [
                'client_id' => $client->id,
                'email' => $email,
            ]);

            return $client;
        }

        $client = Admin::findClientOrLeadByNormalizedPhone($phone);
        if ($client) {
            Log::info('Found existing client by phone', [
                'client_id' => $client->id,
                'phone' => $phone,
            ]);

            return $client;
        }

        return $this->createNewClient($appointmentData);
    }

    /**
     * Create new client (copied from ClientsController logic)
     */
    protected function createNewClient(array $appointmentData): ?Admin
    {
        DB::beginTransaction();

        try {
            // Parse name
            $nameParts = $this->parseFullName($appointmentData['full_name'] ?? 'Unknown');
            $firstName = $nameParts['first_name'];
            $lastName = $nameParts['last_name'];

            // Generate client_counter and client_id using centralized service
            // This prevents race conditions and duplicate references
            $referenceService = app(ClientReferenceService::class);
            $reference = $referenceService->generateClientReference($firstName);
            $client_id = $reference['client_id'];
            $client_current_counter = $reference['client_counter'];

            // Create client
            $client = new Admin;
            $client->first_name = $firstName;
            $client->last_name = $lastName;
            $client->email = $appointmentData['email'] ?? null;
            $client->phone = $appointmentData['phone'] ?? null;
            $client->country_code = $this->extractCountryCode($appointmentData['phone']);
            $client->client_counter = $client_current_counter;
            $client->client_id = $client_id;
            $client->type = 'lead'; // Start as lead
            $client->source = 'Bansal Website';

            // Required NOT NULL fields (matching LeadController pattern)
            $client->password = Hash::make('LEAD_PLACEHOLDER'); // Placeholder password (NOT NULL constraint, will be overwritten if client portal activated)
            $client->status = '1'; // Default status: 1 (Active)
            $client->verified = 0; // Not verified (required NOT NULL column)

            // Client Portal fields (required NOT NULL columns, default 0 for new leads)
            $client->cp_status = 0; // Client portal status (NOT NULL, default 0 - inactive)
            $client->cp_code_verify = 0; // Client portal code verification (NOT NULL, default 0)

            // EOI Qualification fields (required NOT NULL columns, default 0 for new leads)
            $client->australian_study = 0; // Australian study requirement (NOT NULL, default 0)
            $client->specialist_education = 0; // Specialist education qualification (NOT NULL, default 0)
            $client->regional_study = 0; // Regional study qualification (NOT NULL, default 0)

            // Archive status (required NOT NULL column)
            $client->is_archived = 0; // Not archived

            $client->created_at = now();
            $client->updated_at = now();
            $client->save();

            // Create client contact entry if phone exists
            if (! empty($appointmentData['phone'])) {
                ClientContact::create([
                    'client_id' => $client->id,
                    'admin_id' => Auth::id() ?? config('app.system_user_id', 1), // System user for automated processes
                    'contact_type' => 'Mobile',
                    'country_code' => $client->country_code,
                    'phone' => $appointmentData['phone'],
                    'is_verified' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            Log::info('Created new client from appointment', [
                'client_id' => $client->id,
                'client_code' => $client_id,
                'email' => $client->email,
            ]);

            return $client;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create client from appointment', [
                'error' => $e->getMessage(),
                'appointment_data' => $appointmentData,
            ]);

            return null;
        }
    }

    /**
     * Get next counter (copied from ClientsController)
     */
    /**
     * Parse full name into first and last name
     */
    protected function parseFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [
            'first_name' => $parts[0] ?? 'Unknown',
            'last_name' => $parts[1] ?? null,
        ];
    }

    /**
     * Extract country code from phone (basic implementation)
     */
    protected function extractCountryCode(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // If phone starts with +, extract country code
        if (str_starts_with($phone, '+61')) {
            return '+61';
        }

        // Default to Australia
        return '+61';
    }
}
