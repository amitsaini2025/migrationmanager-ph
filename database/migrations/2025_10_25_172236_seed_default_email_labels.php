<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $labels = [
            [
                'user_id' => null, // System label (available to all users)
                'name' => 'Inbox',
                'color' => '#3B82F6', // Blue
                'type' => 'system',
                'icon' => 'inbox',
                'description' => 'Received emails',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'name' => 'Sent',
                'color' => '#10B981', // Green
                'type' => 'system',
                'icon' => 'send',
                'description' => 'Sent emails',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'name' => 'Important',
                'color' => '#EF4444', // Red
                'type' => 'system',
                'icon' => 'star',
                'description' => 'Important emails',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => null,
                'name' => 'Follow Up',
                'color' => '#F59E0B', // Yellow/Orange
                'type' => 'system',
                'icon' => 'flag',
                'description' => 'Emails requiring follow up',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('email_labels')->insert($labels);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('email_labels')
            ->whereIn('name', ['Inbox', 'Sent', 'Important', 'Follow Up'])
            ->where('type', 'system')
            ->delete();
    }
};
