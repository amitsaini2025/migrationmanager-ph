<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientCharacter;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientMatter;
use App\Models\ClientPassportInformation;
use App\Models\ClientTestScore;
use App\Models\ClientTravelInformation;
use App\Models\ClientVisaCountry;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ClientLeadListExportService
{
    public const EXPORT_LIMIT = 10000;

    public const CHUNK_SIZE = 200;

    /**
     * Export list as CSV, or as a ZIP of batch CSV files when over the per-file limit.
     */
    public function export(Builder $query, string $recordType, string $filenamePrefix): StreamedResponse
    {
        $totalMatching = $this->countMatching($query);
        $batchCount = $this->calculateBatchCount($totalMatching);

        if ($batchCount <= 1) {
            return $this->streamCsv($query, $recordType, $filenamePrefix, $totalMatching);
        }

        return $this->streamZipBatches($query, $recordType, $filenamePrefix, $totalMatching, $batchCount);
    }

    public function calculateBatchCount(int $totalMatching): int
    {
        if ($totalMatching <= 0) {
            return 0;
        }

        return (int) ceil($totalMatching / self::EXPORT_LIMIT);
    }

    /**
     * @return list<string>
     */
    public function getHeaders(string $recordType): array
    {
        $headers = [
            'Client ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Country Code',
            'Type',
            'Status',
            'Lead Status',
            'Follow-up Date',
            'Source',
            'Tag Name',
            'DOB',
            'Gender',
            'Marital Status',
            'Address',
            'City',
            'State',
            'Country',
            'Zip',
            'Passport Number',
            'Passport Country',
            'Passport Issue Date',
            'Passport Expiry',
            'Visa Type',
            'Visa Description',
            'Visa Expiry',
            'Is Company',
            'Company Name',
            'Company Website',
            'Assigned Staff',
            'Agent ID',
            'Additional Addresses',
            'Additional Contacts',
            'Additional Emails',
            'Travel History',
            'Visa History',
            'Character Records',
            'Test Scores',
        ];

        if ($recordType === 'client') {
            $headers[] = 'Active Matters Count';
        }

        $headers[] = 'Created At';
        $headers[] = 'Updated At';

        return $headers;
    }

    public function streamCsv(Builder $query, string $recordType, string $filenamePrefix, ?int $totalMatching = null): StreamedResponse
    {
        $headers = $this->getHeaders($recordType);
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.csv';
        $totalMatching = $totalMatching ?? $this->countMatching($query);

        return response()->streamDownload(function () use ($query, $headers, $recordType, $totalMatching) {
            @set_time_limit(0);

            $out = fopen('php://output', 'w');
            $this->writeCsvBatch($out, $query, $headers, $recordType, $totalMatching, 1, 1, 0, $totalMatching, true);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Export-Total-Matching' => (string) $totalMatching,
            'X-Export-Expected-Count' => (string) $totalMatching,
            'X-Export-Batch-Count' => '1',
            'X-Export-Limit' => (string) self::EXPORT_LIMIT,
            'X-Export-Capped' => '0',
        ]);
    }

    protected function streamZipBatches(
        Builder $query,
        string $recordType,
        string $filenamePrefix,
        int $totalMatching,
        int $batchCount
    ): StreamedResponse {
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '_batches.zip';
        $headers = $this->getHeaders($recordType);

        return response()->streamDownload(function () use ($query, $recordType, $filenamePrefix, $totalMatching, $batchCount, $headers) {
            @set_time_limit(0);

            $zipPath = tempnam(sys_get_temp_dir(), 'crm_export_zip_');
            if ($zipPath === false) {
                throw new \RuntimeException('Unable to create temporary export file.');
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($zipPath);
                throw new \RuntimeException('Unable to create export ZIP archive.');
            }

            for ($batchNumber = 1; $batchNumber <= $batchCount; $batchNumber++) {
                $offset = ($batchNumber - 1) * self::EXPORT_LIMIT;
                $batchLimit = min(self::EXPORT_LIMIT, $totalMatching - $offset);
                $csvContent = $this->buildCsvBatchString(
                    $query,
                    $headers,
                    $recordType,
                    $totalMatching,
                    $batchNumber,
                    $batchCount,
                    $offset,
                    $batchLimit
                );

                $zip->addFromString(
                    sprintf('%s_batch_%d_of_%d.csv', $filenamePrefix, $batchNumber, $batchCount),
                    $csvContent
                );
            }

            $zip->close();

            $handle = fopen($zipPath, 'rb');
            if ($handle !== false) {
                while (! feof($handle)) {
                    echo fread($handle, 8192);
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
                fclose($handle);
            }

            @unlink($zipPath);
        }, $filename, [
            'Content-Type' => 'application/zip',
            'X-Export-Total-Matching' => (string) $totalMatching,
            'X-Export-Expected-Count' => (string) $totalMatching,
            'X-Export-Batch-Count' => (string) $batchCount,
            'X-Export-Limit' => (string) self::EXPORT_LIMIT,
            'X-Export-Capped' => '0',
        ]);
    }

    /**
     * @param  resource  $out
     */
    protected function writeCsvBatch(
        $out,
        Builder $query,
        array $headers,
        string $recordType,
        int $totalMatching,
        int $batchNumber,
        int $batchCount,
        int $offset,
        int $batchLimit,
        bool $flushStream = false
    ): int {
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, $headers);

        $exportedCount = $this->chunkRecords(
            $this->buildBatchQuery($query, $offset),
            $recordType,
            function (Admin $admin, array $batch) use ($out, $recordType, $flushStream) {
                fputcsv($out, $this->buildRow($admin, $recordType, $batch));

                if ($flushStream) {
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            },
            $batchLimit
        );

        $this->writeExportSummary($out, $totalMatching, $exportedCount, $batchNumber, $batchCount);

        return $exportedCount;
    }

    protected function buildCsvBatchString(
        Builder $query,
        array $headers,
        string $recordType,
        int $totalMatching,
        int $batchNumber,
        int $batchCount,
        int $offset,
        int $batchLimit
    ): string {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create temporary CSV buffer.');
        }

        $this->writeCsvBatch($handle, $query, $headers, $recordType, $totalMatching, $batchNumber, $batchCount, $offset, $batchLimit);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content === false ? '' : $content;
    }

    protected function buildBatchQuery(Builder $query, int $offset): Builder
    {
        $batchQuery = (clone $query)->orderBy('id');

        if ($offset <= 0) {
            return $batchQuery;
        }

        $startId = (clone $query)->orderBy('id')->offset($offset)->limit(1)->value('id');
        if ($startId !== null) {
            $batchQuery->where('id', '>=', $startId);
        }

        return $batchQuery;
    }

    public function countMatching(Builder $query): int
    {
        return (int) (clone $query)->count();
    }

    /**
     * @return array{total_matching: int, expected_export_count: int, capped: bool, limit: int}
     */
    public function buildExportPreview(Builder $query): array
    {
        $totalMatching = $this->countMatching($query);
        $batchCount = $this->calculateBatchCount($totalMatching);

        return [
            'total_matching' => $totalMatching,
            'expected_export_count' => $totalMatching,
            'batch_count' => $batchCount,
            'capped' => $batchCount > 1,
            'limit' => self::EXPORT_LIMIT,
        ];
    }

    protected function writeExportSummary(
        $out,
        int $totalMatching,
        int $exportedCount,
        ?int $batchNumber = null,
        ?int $batchTotal = null
    ): void {
        fputcsv($out, []);
        fputcsv($out, ['Export Summary']);
        if ($batchNumber !== null && $batchTotal !== null) {
            fputcsv($out, ['Export batch', $batchNumber . ' of ' . $batchTotal]);
            fputcsv($out, ['Records in this file', $exportedCount]);
        }
        fputcsv($out, ['Total matching records', $totalMatching]);
        fputcsv($out, ['Total exported in this file', $exportedCount]);
        fputcsv($out, [
            'Export complete for this file',
            $exportedCount > 0 ? 'Yes' : 'No',
        ]);
        if ($batchNumber !== null && $batchTotal !== null && $batchTotal > 1) {
            fputcsv($out, [
                'All batches required',
                'Yes (' . $batchTotal . ' files in ZIP download)',
            ]);
        }
        fputcsv($out, ['Exported at', now()->format('Y-m-d H:i:s')]);
    }

    /**
     * @return int Number of rows exported
     */
    protected function chunkRecords(Builder $query, string $recordType, callable $callback, ?int $maxRecords = null): int
    {
        $count = 0;
        $staffNames = [];
        $limit = $maxRecords ?? self::EXPORT_LIMIT;

        (clone $query)
            ->with(['company'])
            ->orderBy('id')
            ->chunkById(self::CHUNK_SIZE, function ($chunk) use ($callback, $recordType, &$count, &$staffNames, $limit) {
                $clientIds = $chunk->pluck('id')->map(fn ($id) => (int) $id)->all();

                $userIds = $chunk->pluck('user_id')->filter()->unique()->values()->all();
                $missingIds = array_diff($userIds, array_keys($staffNames));

                if ($missingIds !== []) {
                    Staff::query()
                        ->whereIn('id', $missingIds)
                        ->get(['id', 'first_name', 'last_name'])
                        ->each(function (Staff $staff) use (&$staffNames) {
                            $staffNames[$staff->id] = trim($staff->first_name . ' ' . $staff->last_name);
                        });
                }

                $batch = $this->loadBatchContext($clientIds, $recordType);

                foreach ($chunk as $admin) {
                    if ($count >= $limit) {
                        return false;
                    }

                    $admin->assignedStaffName = $admin->user_id
                        ? ($staffNames[$admin->user_id] ?? '')
                        : '';

                    $callback($admin, $batch);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Preload related records for a chunk of client/lead IDs (avoids N+1 exportClient calls).
     *
     * @param  list<int>  $clientIds
     * @return array<string, mixed>
     */
    protected function loadBatchContext(array $clientIds, string $recordType): array
    {
        if ($clientIds === []) {
            return [
                'addresses' => collect(),
                'contacts' => collect(),
                'emails' => collect(),
                'passports' => collect(),
                'travel' => collect(),
                'visas' => collect(),
                'latest_visas' => collect(),
                'character' => collect(),
                'test_scores' => collect(),
                'matter_counts' => collect(),
            ];
        }

        $addresses = ClientAddress::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy('client_id');

        $contacts = ClientContact::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy('client_id');

        $emails = ClientEmail::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy('client_id');

        $passports = ClientPassportInformation::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->keyBy('client_id');

        $travel = ClientTravelInformation::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy('client_id');

        $visas = ClientVisaCountry::query()
            ->with('matter')
            ->whereIn('client_id', $clientIds)
            ->orderBy('id')
            ->get()
            ->groupBy('client_id');

        $latestVisas = $visas->map(function (Collection $items) {
            return $items->sortByDesc('id')->first();
        });

        $character = ClientCharacter::query()
            ->whereIn('client_id', $clientIds)
            ->get()
            ->groupBy('client_id');

        $testScores = ClientTestScore::query()
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('updated_at')
            ->get()
            ->groupBy('client_id');

        $matterCounts = collect();
        if ($recordType === 'client') {
            $matterCounts = ClientMatter::query()
                ->selectRaw('client_id, COUNT(*) as aggregate')
                ->whereIn('client_id', $clientIds)
                ->where('matter_status', 1)
                ->groupBy('client_id')
                ->pluck('aggregate', 'client_id');
        }

        return [
            'addresses' => $addresses,
            'contacts' => $contacts,
            'emails' => $emails,
            'passports' => $passports,
            'travel' => $travel,
            'visas' => $visas,
            'latest_visas' => $latestVisas,
            'character' => $character,
            'test_scores' => $testScores,
            'matter_counts' => $matterCounts,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $batch
     * @return list<scalar|null>
     */
    public function buildRow(Admin $admin, string $recordType, ?array $batch = null): array
    {
        if ($batch === null) {
            $batch = $this->loadBatchContext([(int) $admin->id], $recordType);
        }

        $clientId = (int) $admin->id;
        $passport = $batch['passports'][$clientId] ?? null;
        $latestVisa = $batch['latest_visas'][$clientId] ?? null;
        [$visaType, $visaDescription, $visaExpiry] = $this->resolveLatestVisaSummary($latestVisa);

        $row = [
            $admin->client_id,
            $admin->first_name,
            $admin->last_name,
            $admin->email,
            $admin->phone,
            $admin->country_code,
            $admin->type,
            $this->formatStatus($admin->status),
            $admin->lead_status,
            $this->formatDateTime($admin->followup_date),
            $admin->source,
            $admin->tagname,
            $admin->dob,
            $admin->gender,
            $admin->marital_status,
            $admin->address,
            $admin->city,
            $admin->state,
            $admin->country,
            $admin->zip,
            $passport?->passport ?? ($admin->passport_number ?? null),
            $passport?->passport_country ?? ($admin->country_passport ?? null),
            $this->formatDateValue($passport?->passport_issue_date),
            $this->formatDateValue($passport?->passport_expiry_date),
            $visaType,
            $visaDescription,
            $visaExpiry,
            $admin->is_company ? 'Yes' : 'No',
            $admin->company?->company_name,
            $admin->company?->company_website,
            $admin->assignedStaffName ?? '',
            $admin->agent_id ?? null,
            $this->formatAddresses($this->mapAddresses($batch['addresses'][$clientId] ?? collect())),
            $this->formatContacts($this->mapContacts($batch['contacts'][$clientId] ?? collect())),
            $this->formatEmails($this->mapEmails($batch['emails'][$clientId] ?? collect())),
            $this->formatTravel($this->mapTravel($batch['travel'][$clientId] ?? collect())),
            $this->formatVisaHistory($this->mapVisas($batch['visas'][$clientId] ?? collect())),
            $this->formatCharacter($this->mapCharacter($batch['character'][$clientId] ?? collect())),
            $this->formatTestScores($this->mapTestScores($batch['test_scores'][$clientId] ?? collect())),
        ];

        if ($recordType === 'client') {
            $row[] = (int) ($batch['matter_counts'][$clientId] ?? 0);
        }

        $row[] = $this->formatDateTime($admin->created_at);
        $row[] = $this->formatDateTime($admin->updated_at);

        return $row;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    protected function resolveLatestVisaSummary(?ClientVisaCountry $visa): array
    {
        if (! $visa) {
            return [null, null, null];
        }

        $matter = $visa->matter;
        $visaType = $matter ? ($matter->nick_name ?? $matter->title) : (string) $visa->visa_type;

        return [
            $visaType,
            $visa->visa_description,
            $this->formatDateValue($visa->visa_expiry_date),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapAddresses(Collection $items): array
    {
        return $items->map(fn (ClientAddress $address) => [
            'address' => $address->address,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'suburb' => $address->suburb,
            'city' => $address->suburb,
            'state' => $address->state,
            'country' => $address->country,
            'zip' => $address->zip,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapContacts(Collection $items): array
    {
        return $items->map(fn (ClientContact $contact) => [
            'contact_type' => $contact->contact_type,
            'country_code' => $contact->country_code,
            'phone' => $contact->phone,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapEmails(Collection $items): array
    {
        return $items->map(fn (ClientEmail $email) => [
            'email_type' => $email->email_type,
            'email' => $email->email,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapTravel(Collection $items): array
    {
        return $items->map(fn (ClientTravelInformation $travel) => [
            'travel_country_visited' => $travel->travel_country_visited,
            'travel_arrival_date' => $travel->travel_arrival_date,
            'travel_departure_date' => $travel->travel_departure_date,
            'travel_purpose' => $travel->travel_purpose,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapVisas(Collection $items): array
    {
        return $items->map(function (ClientVisaCountry $visa) {
            $matter = $visa->matter;

            return [
                'visa_type' => $visa->visa_type,
                'visa_type_matter_title' => $matter?->title,
                'visa_type_matter_nick_name' => $matter?->nick_name,
                'visa_description' => $visa->visa_description,
                'visa_expiry_date' => $this->formatDateValue($visa->visa_expiry_date),
                'visa_grant_date' => $this->formatDateValue($visa->visa_grant_date),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapCharacter(Collection $items): array
    {
        return $items->map(fn (ClientCharacter $record) => [
            'type_of_character' => $record->type_of_character,
            'character_detail' => $record->character_detail,
            'character_date' => $record->character_date,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function mapTestScores(Collection $items): array
    {
        return $items->map(fn (ClientTestScore $score) => [
            'test_type' => $score->test_type,
            'listening' => $score->listening,
            'reading' => $score->reading,
            'writing' => $score->writing,
            'speaking' => $score->speaking,
            'overall_score' => $score->overall_score,
            'test_date' => $this->formatDateValue($score->test_date),
        ])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $addresses
     */
    protected function formatAddresses(array $addresses): string
    {
        return collect($addresses)->map(function (array $address) {
            $parts = array_filter([
                $address['address_line_1'] ?? $address['address'] ?? null,
                $address['address_line_2'] ?? null,
                $address['suburb'] ?? $address['city'] ?? null,
                $address['state'] ?? null,
                $address['country'] ?? null,
                $address['zip'] ?? null,
            ]);

            return implode(', ', $parts);
        })->filter()->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    protected function formatContacts(array $contacts): string
    {
        return collect($contacts)->map(function (array $contact) {
            $phone = trim(($contact['country_code'] ?? '') . ' ' . ($contact['phone'] ?? ''));

            return trim(($contact['contact_type'] ?? 'Phone') . ': ' . $phone);
        })->filter()->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $emails
     */
    protected function formatEmails(array $emails): string
    {
        return collect($emails)->map(function (array $email) {
            return trim(($email['email_type'] ?? 'Email') . ': ' . ($email['email'] ?? ''));
        })->filter()->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $travel
     */
    protected function formatTravel(array $travel): string
    {
        return collect($travel)->map(function (array $item) {
            $parts = array_filter([
                $item['travel_country_visited'] ?? null,
                $item['travel_arrival_date'] ?? null,
                $item['travel_departure_date'] ?? null,
                $item['travel_purpose'] ?? null,
            ]);

            return implode(', ', $parts);
        })->filter()->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $visas
     */
    protected function formatVisaHistory(array $visas): string
    {
        return collect($visas)->map(function (array $visa) {
            $label = $visa['visa_type_matter_nick_name']
                ?? $visa['visa_type_matter_title']
                ?? $visa['visa_type']
                ?? 'Visa';

            $parts = array_filter([
                $label,
                $visa['visa_description'] ?? null,
                isset($visa['visa_grant_date']) ? 'Grant: ' . $visa['visa_grant_date'] : null,
                isset($visa['visa_expiry_date']) ? 'Expiry: ' . $visa['visa_expiry_date'] : null,
            ]);

            return implode(', ', $parts);
        })->filter()->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    protected function formatCharacter(array $records): string
    {
        return collect($records)->map(function (array $record) {
            $parts = array_filter([
                $record['type_of_character'] ?? null,
                $record['character_detail'] ?? null,
                $record['character_date'] ?? null,
            ]);

            return implode(', ', $parts);
        })->filter()->implode(' | ');
    }

    /**
     * @param  array<int, array<string, mixed>>  $scores
     */
    protected function formatTestScores(array $scores): string
    {
        return collect($scores)->map(function (array $score) {
            $parts = array_filter([
                $score['test_type'] ?? null,
                isset($score['overall_score']) ? 'Overall: ' . $score['overall_score'] : null,
                isset($score['listening']) ? 'L:' . $score['listening'] : null,
                isset($score['reading']) ? 'R:' . $score['reading'] : null,
                isset($score['writing']) ? 'W:' . $score['writing'] : null,
                isset($score['speaking']) ? 'S:' . $score['speaking'] : null,
                $score['test_date'] ?? null,
            ]);

            return implode(', ', $parts);
        })->filter()->implode(' | ');
    }

    protected function formatStatus(mixed $status): string
    {
        if ($status === null || $status === '') {
            return '';
        }

        return (string) $status === '1' ? 'Active' : 'Inactive';
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    protected function formatDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return is_scalar($value) ? (string) $value : null;
        }
    }
}
