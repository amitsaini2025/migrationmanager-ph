<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientLeadListExportService
{
    public const EXPORT_LIMIT = 10000;

    public function __construct(
        private readonly ClientExportService $clientExportService
    ) {
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

    public function streamCsv(Builder $query, string $recordType, string $filenamePrefix): StreamedResponse
    {
        $headers = $this->getHeaders($recordType);
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query, $headers, $recordType) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, $headers);

            $this->chunkRecords($query, function (Admin $admin) use ($out, $recordType) {
                fputcsv($out, $this->buildRow($admin, $recordType));
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadExcel(Builder $query, string $recordType, string $filenamePrefix): StreamedResponse
    {
        $headers = $this->getHeaders($recordType);
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(function () use ($query, $headers, $recordType) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($headers, null, 'A1');

            $rowIndex = 2;
            $this->chunkRecords($query, function (Admin $admin) use ($sheet, $recordType, &$rowIndex) {
                $sheet->fromArray($this->buildRow($admin, $recordType), null, 'A' . $rowIndex);
                $rowIndex++;
            });

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  callable(Admin): void  $callback
     */
    protected function chunkRecords(Builder $query, callable $callback): void
    {
        $count = 0;
        $staffNames = [];

        (clone $query)
            ->with(['company'])
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($callback, &$count, &$staffNames) {
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

                foreach ($chunk as $admin) {
                    if ($count >= self::EXPORT_LIMIT) {
                        return false;
                    }

                    $admin->assignedStaffName = $admin->user_id
                        ? ($staffNames[$admin->user_id] ?? '')
                        : '';

                    $callback($admin);
                    $count++;
                }
            });
    }

    /**
     * @return list<scalar|null>
     */
    public function buildRow(Admin $admin, string $recordType): array
    {
        $export = $this->clientExportService->exportClient((int) $admin->id);
        $client = $export['client'] ?? [];
        $passport = $export['passport'] ?? null;

        $row = [
            $client['client_id'] ?? $admin->client_id,
            $client['first_name'] ?? $admin->first_name,
            $client['last_name'] ?? $admin->last_name,
            $client['email'] ?? $admin->email,
            $client['phone'] ?? $admin->phone,
            $client['country_code'] ?? $admin->country_code,
            $client['type'] ?? $admin->type,
            $this->formatStatus($client['status'] ?? $admin->status),
            $client['lead_status'] ?? $admin->lead_status,
            $this->formatDateTime($admin->followup_date),
            $client['source'] ?? $admin->source,
            $client['tagname'] ?? $admin->tagname,
            $client['dob'] ?? null,
            $client['gender'] ?? null,
            $client['marital_status'] ?? $admin->marital_status,
            $client['address'] ?? $admin->address,
            $client['city'] ?? $admin->city,
            $client['state'] ?? $admin->state,
            $client['country'] ?? $admin->country,
            $client['zip'] ?? $admin->zip,
            $passport['passport_number'] ?? ($client['passport_number'] ?? null),
            $passport['passport_country'] ?? ($client['country_passport'] ?? null),
            $passport['passport_issue_date'] ?? null,
            $passport['passport_expiry_date'] ?? null,
            $client['visa_type'] ?? null,
            $client['visa_opt'] ?? null,
            $client['visaExpiry'] ?? null,
            $admin->is_company ? 'Yes' : 'No',
            $admin->company?->company_name,
            $admin->company?->company_website,
            $admin->assignedStaffName ?? '',
            $client['agent_id'] ?? $admin->agent_id ?? null,
            $this->formatAddresses($export['addresses'] ?? []),
            $this->formatContacts($export['contacts'] ?? []),
            $this->formatEmails($export['emails'] ?? []),
            $this->formatTravel($export['travel'] ?? []),
            $this->formatVisaHistory($export['visa_countries'] ?? []),
            $this->formatCharacter($export['character'] ?? []),
            $this->formatTestScores($export['test_scores'] ?? []),
        ];

        if ($recordType === 'client') {
            $row[] = ClientMatter::query()
                ->where('client_id', $admin->id)
                ->where('matter_status', 1)
                ->count();
        }

        $row[] = $this->formatDateTime($admin->created_at);
        $row[] = $this->formatDateTime($admin->updated_at);

        return $row;
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
}
