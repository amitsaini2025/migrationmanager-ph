<?php

namespace App\Services\LegalCrm;

use App\Models\Admin;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LegalCrmApiClient
{
    protected string $baseUrl;

    protected ?string $apiToken;

    protected int $timeout;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiToken = null,
        ?int $timeout = null
    ) {
        $this->baseUrl = rtrim((string) ($baseUrl ?: config('services.legal_crm.url', '')), '/');
        $this->apiToken = $apiToken ?? config('services.legal_crm.token');
        $this->timeout = (int) ($timeout ?: config('services.legal_crm.timeout', 30));

        if ($this->baseUrl === '') {
            throw new Exception('Legal CRM API URL not configured. Set LEGAL_CRM_API_BASE_URL in .env');
        }
    }

    /**
     * Create (or match existing) Legal CRM lead from a Migration CRM lead or client.
     * Always creates a Lead on Legal CRM (not a client).
     *
     * @return array{success: bool, lead_id?: int|null, already_exists?: bool, message?: string, data?: array}
     */
    public function createLeadFromMigrationLead(Admin $record): array
    {
        self::assertLeadHasRequiredFields($record);

        $email = strtolower(trim((string) ($record->email ?? '')));
        $phone = trim((string) ($record->phone ?? ''));
        $firstName = trim((string) ($record->first_name ?? ''));
        $lastName = trim((string) ($record->last_name ?? ''));

        if (empty($this->apiToken)) {
            throw new Exception('Legal CRM API token not configured. Set LEGAL_CRM_API_TOKEN in .env');
        }

        $payload = [
            'first_name' => $firstName !== '' ? $firstName : 'Unknown',
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email,
            'phone' => $phone,
            'country_code' => $record->country_code ?: null,
            'source' => 'Migration CRM',
            'refer_by' => 'Migration CRM',
            'lead_status' => 'new',
            'migration_lead_id' => (int) $record->id,
        ];

        return $this->createLead($payload);
    }

    /**
     * Validate record has the fields Legal CRM requires (no API call).
     */
    public static function assertLeadHasRequiredFields(Admin $record): void
    {
        $email = strtolower(trim((string) ($record->email ?? '')));
        $phone = trim((string) ($record->phone ?? ''));
        $firstName = trim((string) ($record->first_name ?? ''));
        $lastName = trim((string) ($record->last_name ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email is required to send to Legal CRM.');
        }

        if ($phone === '') {
            throw new Exception('Phone is required to send to Legal CRM.');
        }

        if ($firstName === '' && $lastName === '') {
            throw new Exception('Name is required to send to Legal CRM.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, lead_id?: int|null, already_exists?: bool, message?: string, data?: array}
     */
    public function createLead(array $payload): array
    {
        $path = (string) config('services.legal_crm.leads_path', '/migration-crm/leads');
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        try {
            $response = $this->client()->post("{$this->baseUrl}{$path}", $payload);

            $data = $response->json() ?? [];

            if (! $response->successful()) {
                $message = $this->extractErrorMessage($data, $response->status());
                throw new Exception($message);
            }

            if (($data['success'] ?? false) !== true) {
                throw new Exception($this->extractErrorMessage($data, $response->status()));
            }

            $leadId = $data['lead_id']
                ?? ($data['data']['lead_id'] ?? null)
                ?? ($data['data']['id'] ?? null);

            $alreadyExists = (bool) (
                ($data['data']['is_existing'] ?? false)
                || ($data['already_exists'] ?? false)
            );

            Log::channel('migration_legal_crm')->info('Legal CRM API createLead response', [
                'migration_lead_id' => $payload['migration_lead_id'] ?? null,
                'legal_lead_id' => $leadId !== null ? (int) $leadId : null,
                'already_exists' => $alreadyExists,
                'http_status' => $response->status(),
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'message' => $data['message'] ?? null,
            ]);

            return [
                'success' => true,
                'lead_id' => $leadId !== null ? (int) $leadId : null,
                'already_exists' => $alreadyExists,
                'message' => (string) ($data['message'] ?? 'Lead synced to Legal CRM.'),
                'data' => is_array($data['data'] ?? null) ? $data['data'] : $data,
            ];
        } catch (Exception $e) {
            Log::channel('migration_legal_crm')->error('Legal CRM API Client Error', [
                'method' => 'createLead',
                'error' => $e->getMessage(),
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'migration_lead_id' => $payload['migration_lead_id'] ?? null,
                'path' => config('services.legal_crm.leads_path', '/migration-crm/leads'),
            ]);

            throw $e;
        }
    }

    protected function client(): PendingRequest
    {
        $request = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson();

        if (! empty($this->apiToken)) {
            $request = $request->withToken($this->apiToken);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractErrorMessage(array $data, int $status): string
    {
        if (! empty($data['message']) && is_string($data['message'])) {
            return $data['message'];
        }

        if (! empty($data['error']) && is_string($data['error'])) {
            return $data['error'];
        }

        if (! empty($data['errors']) && is_array($data['errors'])) {
            $parts = [];
            foreach ($data['errors'] as $fieldErrors) {
                if (is_array($fieldErrors)) {
                    $parts[] = implode(' ', $fieldErrors);
                } elseif (is_string($fieldErrors)) {
                    $parts[] = $fieldErrors;
                }
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        return "Legal CRM API request failed (HTTP {$status}).";
    }
}
