<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL stored Laravel enums as varchar(longest original value).
     * awaiting_confirmation is 21 characters; the column was varchar(11).
     */
    private const NEW_LENGTH = 32;

    private const PREVIOUS_LENGTH = 11;

    public function up(): void
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasColumn('booking_appointments', 'status')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE booking_appointments ALTER COLUMN status TYPE VARCHAR('.self::NEW_LENGTH.') USING status::VARCHAR('.self::NEW_LENGTH.')'
            );
            DB::statement("ALTER TABLE booking_appointments ALTER COLUMN status SET DEFAULT 'pending'");

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->string('status', self::NEW_LENGTH)->default('pending')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('booking_appointments') || ! Schema::hasColumn('booking_appointments', 'status')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $hasLongStatus = DB::table('booking_appointments')
                ->whereRaw('LENGTH(status) > ?', [self::PREVIOUS_LENGTH])
                ->exists();

            if (! $hasLongStatus) {
                DB::statement(
                    'ALTER TABLE booking_appointments ALTER COLUMN status TYPE VARCHAR('.self::PREVIOUS_LENGTH.') USING status::VARCHAR('.self::PREVIOUS_LENGTH.')'
                );
            }

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('booking_appointments', function (Blueprint $table) {
            $table->string('status', self::PREVIOUS_LENGTH)->default('pending')->change();
        });
    }
};
