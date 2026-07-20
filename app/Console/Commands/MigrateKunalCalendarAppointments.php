<?php

namespace App\Console\Commands;

use App\Models\ActivitiesLog;
use App\Models\AppointmentConsultant;
use App\Models\BookingAppointment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateKunalCalendarAppointments extends Command
{
    protected $signature = 'booking:migrate-kunal-appointments
                            {--dry-run : Preview changes without updating the database}
                            {--force : Skip confirmation prompt when applying changes}';

    protected $description = 'Move future Kunal calendar appointments to JRP (free) or Employer Sponsored (paid)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $kunalConsultantIds = AppointmentConsultant::query()
            ->where('calendar_type', 'kunal')
            ->pluck('id');

        if ($kunalConsultantIds->isEmpty()) {
            $this->warn('No Kunal consultant record found. Nothing to migrate.');

            return self::SUCCESS;
        }

        $jrpConsultant = AppointmentConsultant::query()
            ->where('calendar_type', 'jrp')
            ->where('is_active', true)
            ->where('location', 'melbourne')
            ->first();

        $paidConsultant = AppointmentConsultant::query()
            ->where('calendar_type', 'paid')
            ->where('is_active', true)
            ->where('location', 'melbourne')
            ->first();

        if (! $jrpConsultant || ! $paidConsultant) {
            $this->error('Active Melbourne JRP or Employer Sponsored consultant not found. Aborting.');

            return self::FAILURE;
        }

        $now = Carbon::now('Australia/Melbourne');

        $appointments = BookingAppointment::query()
            ->with('consultant')
            ->whereIn('consultant_id', $kunalConsultantIds)
            ->where('appointment_datetime', '>', $now)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) {
                $query->whereRaw('LOWER(location) = ?', ['melbourne'])
                    ->orWhere('inperson_address', 2);
            })
            ->orderBy('appointment_datetime')
            ->get();

        $stats = [
            'total' => $appointments->count(),
            'to_jrp' => 0,
            'to_paid' => 0,
            'already_correct' => 0,
            'ambiguous' => 0,
            'conflicts' => 0,
            'updated' => 0,
        ];

        $previewRows = [];

        foreach ($appointments as $appointment) {
            $bucket = $this->resolveFreeVsPaidBucket($appointment);

            if ($bucket === null) {
                $stats['ambiguous']++;
                $previewRows[] = $this->previewRow($appointment, 'ambiguous', null, null);

                continue;
            }

            $targetConsultant = $bucket === 'free' ? $jrpConsultant : $paidConsultant;
            $targetLabel = $bucket === 'free' ? 'JRP (free)' : 'Employer Sponsored (paid)';

            if ((int) $appointment->consultant_id === (int) $targetConsultant->id) {
                $stats['already_correct']++;
                $previewRows[] = $this->previewRow($appointment, 'already_correct', $targetLabel, $targetConsultant->crm_display_label);

                continue;
            }

            if ($this->hasTargetSlotConflict($appointment, (int) $targetConsultant->id)) {
                $stats['conflicts']++;
                $previewRows[] = $this->previewRow($appointment, 'conflict', $targetLabel, $targetConsultant->crm_display_label);

                continue;
            }

            if ($bucket === 'free') {
                $stats['to_jrp']++;
            } else {
                $stats['to_paid']++;
            }

            $previewRows[] = $this->previewRow($appointment, 'move', $targetLabel, $targetConsultant->crm_display_label);

            if ($dryRun) {
                continue;
            }
        }

        $this->info($dryRun ? 'Dry run — no database changes will be made.' : 'Live run — appointments will be updated.');
        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Future Kunal Melbourne (non-cancelled)', $stats['total']],
                ['Move to JRP (free)', $stats['to_jrp']],
                ['Move to Employer Sponsored (paid)', $stats['to_paid']],
                ['Already on target calendar', $stats['already_correct']],
                ['Ambiguous (skipped)', $stats['ambiguous']],
                ['Slot conflicts (skipped)', $stats['conflicts']],
            ]
        );

        if ($previewRows !== []) {
            $this->newLine();
            $this->table(
                ['ID', 'Client', 'Date (Melbourne)', 'Status', 'Action', 'Target'],
                array_map(fn (array $row) => [
                    $row['id'],
                    $row['client'],
                    $row['datetime'],
                    $row['status'],
                    $row['action'],
                    $row['target'],
                ], $previewRows)
            );
        }

        $toApply = $stats['to_jrp'] + $stats['to_paid'];

        if ($toApply === 0) {
            $this->info('No appointments need migration.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run complete. {$toApply} appointment(s) would be updated.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Update {$toApply} appointment(s)?", false)) {
            $this->info('Migration cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($appointments, $jrpConsultant, $paidConsultant, &$stats) {
            foreach ($appointments as $appointment) {
                $bucket = $this->resolveFreeVsPaidBucket($appointment);
                if ($bucket === null) {
                    continue;
                }

                $targetConsultant = $bucket === 'free' ? $jrpConsultant : $paidConsultant;

                if ((int) $appointment->consultant_id === (int) $targetConsultant->id) {
                    continue;
                }

                if ($this->hasTargetSlotConflict($appointment, (int) $targetConsultant->id)) {
                    continue;
                }

                $appointment->consultant_id = $targetConsultant->id;
                $appointment->save();

                if ($appointment->client_id) {
                    $activityLog = new ActivitiesLog;
                    $activityLog->client_id = $appointment->client_id;
                    $activityLog->created_by = null;
                    $activityLog->subject = 'Booking appointment consultant reassigned (Kunal migration)';
                    $activityLog->description = '<p><strong>Consultant assigned:</strong> '
                        . e($targetConsultant->crm_display_label) . '</p>';
                    $activityLog->task_status = 0;
                    $activityLog->pin = 0;
                    $activityLog->save();
                }

                $stats['updated']++;
            }
        });

        $this->newLine();
        $this->info("Migration complete. Updated {$stats['updated']} appointment(s).");

        return self::SUCCESS;
    }

    /**
     * @return 'free'|'paid'|null
     */
    protected function resolveFreeVsPaidBucket(BookingAppointment $appointment): ?string
    {
        if ($appointment->is_paid === false) {
            return 'free';
        }
        if ($appointment->is_paid === true) {
            return 'paid';
        }

        $serviceId = $appointment->service_id;
        if ($serviceId !== null && $serviceId !== '') {
            $sid = (int) $serviceId;
            if ($sid === 2) {
                return 'free';
            }
            if (in_array($sid, [1, 3], true)) {
                return 'paid';
            }
        }

        return null;
    }

    protected function hasTargetSlotConflict(BookingAppointment $appointment, int $targetConsultantId): bool
    {
        return BookingAppointment::query()
            ->where('consultant_id', $targetConsultantId)
            ->where('appointment_datetime', $appointment->appointment_datetime)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->exists();
    }

    /**
     * @return array{id:int,client:string,datetime:string,status:string,action:string,target:string}
     */
    protected function previewRow(
        BookingAppointment $appointment,
        string $action,
        ?string $bucketLabel,
        ?string $targetLabel
    ): array {
        $datetime = $appointment->appointment_datetime
            ? $appointment->appointment_datetime->copy()->timezone('Australia/Melbourne')->format('Y-m-d H:i')
            : 'N/A';

        $actionLabel = match ($action) {
            'move' => 'Will move',
            'already_correct' => 'Already correct',
            'ambiguous' => 'Skip (ambiguous)',
            'conflict' => 'Skip (slot conflict)',
            default => $action,
        };

        return [
            'id' => $appointment->id,
            'client' => $appointment->client_name ?? 'N/A',
            'datetime' => $datetime,
            'status' => $appointment->status ?? 'N/A',
            'action' => $actionLabel,
            'target' => $targetLabel ?? ($bucketLabel ?? '—'),
        ];
    }
}
