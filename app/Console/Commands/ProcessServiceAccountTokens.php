<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Staff;
use App\Services\ServiceAccountTokenService;

class ProcessServiceAccountTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Targets Staff (CRM ops users), not Admin (clients/leads).
     * Bulk mode requires --all so a bare run cannot mint tokens for the whole table by accident.
     *
     * @var string
     */
    protected $signature = 'service-account:generate-token
                            {staff_id? : Staff ID to generate a token for}
                            {--all : Generate tokens for all active staff}
                            {--sync : Generate synchronously instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate service account token for staff member(s)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $staffId = $this->argument('staff_id');
        $sync = $this->option('sync');
        $all = $this->option('all');

        if ($staffId) {
            $staff = Staff::find($staffId);
            if (! $staff) {
                $this->error("Staff with ID {$staffId} not found.");
                return 1;
            }

            if ($sync) {
                $this->generateTokenSync($staff);
            } else {
                $this->generateTokenAsync($staff);
            }

            return 0;
        }

        if (! $all) {
            $this->error('Provide a staff_id, or pass --all to generate tokens for all active staff.');
            $this->line('Example: php artisan service-account:generate-token 42 --sync');
            $this->line('Example: php artisan service-account:generate-token --all');

            return 1;
        }

        $staffMembers = Staff::where('status', 1)->get();

        if ($staffMembers->isEmpty()) {
            $this->info('No active staff found.');
            return 0;
        }

        $this->info("Found {$staffMembers->count()} active staff member(s).");

        foreach ($staffMembers as $staff) {
            if ($sync) {
                $this->generateTokenSync($staff);
            } else {
                $this->generateTokenAsync($staff);
            }
        }

        return 0;
    }

    /**
     * Generate token synchronously
     */
    private function generateTokenSync(Staff $staff): void
    {
        $this->info("Generating token synchronously for staff: {$staff->email}");

        $service = new ServiceAccountTokenService();
        $result = $service->generateTokenSync($staff);

        if ($result) {
            $this->info("Token generated successfully for {$staff->email}");
            $this->line('Token: ' . ($result['token'] ?? 'N/A'));
        } else {
            $this->error("Failed to generate token for {$staff->email}");
        }
    }

    /**
     * Generate token asynchronously
     */
    private function generateTokenAsync(Staff $staff): void
    {
        $this->info("Dispatching token generation job for staff: {$staff->email}");

        $service = new ServiceAccountTokenService();
        $service->generateTokenInBackground($staff);

        $this->info("Job dispatched successfully for {$staff->email}");
    }
}
