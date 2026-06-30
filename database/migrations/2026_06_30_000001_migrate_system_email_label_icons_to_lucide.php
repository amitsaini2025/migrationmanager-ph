<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrate system email_labels.icon from Font Awesome class strings to Lucide kebab names.
 * Custom labels are left unchanged; IconHelper::renderStored() handles legacy values.
 */
return new class extends Migration
{
    private const FA_TO_LUCIDE = [
        'fas fa-inbox' => 'inbox',
        'fas fa-paper-plane' => 'send',
        'fas fa-star' => 'star',
        'fas fa-flag' => 'flag',
        'fas fa-edit' => 'pencil',
        'fas fa-trash' => 'trash-2',
        'fas fa-ban' => 'ban',
        'fas fa-archive' => 'archive',
        'fas fa-briefcase' => 'briefcase',
        'fas fa-user' => 'user',
        'fas fa-exclamation-triangle' => 'triangle-alert',
        'fas fa-tag' => 'tag',
    ];

    public function up(): void
    {
        foreach (self::FA_TO_LUCIDE as $fa => $lucide) {
            DB::table('email_labels')
                ->where('type', 'system')
                ->where('icon', $fa)
                ->update(['icon' => $lucide, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::FA_TO_LUCIDE as $fa => $lucide) {
            DB::table('email_labels')
                ->where('type', 'system')
                ->where('icon', $lucide)
                ->update(['icon' => $fa, 'updated_at' => now()]);
        }
    }
};
