<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Retire Kunal calendar from CRM navigation and consultant pickers.
     * Keeps the consultant row for historical booking_appointments.consultant_id references.
     */
    public function up(): void
    {
        DB::table('appointment_consultants')
            ->where('calendar_type', 'kunal')
            ->update([
                'is_active' => false,
                'show_in_filter' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('appointment_consultants')
            ->where('calendar_type', 'kunal')
            ->update([
                'is_active' => true,
                'show_in_filter' => true,
                'updated_at' => now(),
            ]);
    }
};
