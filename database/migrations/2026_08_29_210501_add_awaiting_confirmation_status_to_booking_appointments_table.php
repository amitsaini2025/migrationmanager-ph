<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATUSES = [
        'pending',
        'awaiting_confirmation',
        'paid',
        'confirmed',
        'completed',
        'cancelled',
        'no_show',
        'rescheduled',
    ];

    /**
     * @var list<string>
     */
    private const PREVIOUS_STATUSES = [
        'pending',
        'paid',
        'confirmed',
        'completed',
        'cancelled',
        'no_show',
        'rescheduled',
    ];

    public function up(): void
    {
        $this->applyStatusValues(self::STATUSES);
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_appointments')) {
            DB::table('booking_appointments')
                ->where('status', 'awaiting_confirmation')
                ->update(['status' => 'pending']);
        }

        $this->applyStatusValues(self::PREVIOUS_STATUSES);
    }

    /**
     * @param  list<string>  $statuses
     */
    private function applyStatusValues(array $statuses): void
    {
        $quoted = "'".implode("', '", $statuses)."'";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE booking_appointments DROP CONSTRAINT IF EXISTS booking_appointments_status_check');
            DB::statement("ALTER TABLE booking_appointments ADD CONSTRAINT booking_appointments_status_check CHECK (status IN ({$quoted}))");

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `booking_appointments` MODIFY COLUMN `status` ENUM({$quoted}) DEFAULT 'pending'");
        }
    }
};
