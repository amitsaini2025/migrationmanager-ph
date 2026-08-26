<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speeds dashboard "My Actions": superadmin scans type=client + is_action=1
     * then filters status <> 1. Existing indexes lead with assigned_to or client_id.
     */
    public function up(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            if (! $this->pgIndexExists('idx_notes_dashboard_open_actions')) {
                DB::statement(
                    'CREATE INDEX idx_notes_dashboard_open_actions ON notes (note_deadline ASC NULLS LAST, created_at DESC) '
                    ."WHERE type = 'client' AND is_action = 1 AND status <> 1"
                );
            }

            return;
        }

        if (! Schema::hasIndex('notes', 'idx_notes_dashboard_open_actions')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->index(['type', 'is_action', 'status'], 'idx_notes_dashboard_open_actions');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            if ($this->pgIndexExists('idx_notes_dashboard_open_actions')) {
                DB::statement('DROP INDEX IF EXISTS '.$this->quoteId('idx_notes_dashboard_open_actions'));
            }

            return;
        }

        if (Schema::hasIndex('notes', 'idx_notes_dashboard_open_actions')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropIndex('idx_notes_dashboard_open_actions');
            });
        }
    }

    private function pgIndexExists(string $name): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return (bool) DB::selectOne('SELECT 1 AS ok FROM pg_indexes WHERE indexname = ?', [$name]);
    }

    private function quoteId(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
};
