<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Widen documents.status so signature workflow values fit (e.g. archived = 8,
     * signature_placed = 16). Legacy column was varchar(6), which caused
     * signatures:archive-drafts to fail on PostgreSQL.
     */
    public function up(): void
    {
        if (! Schema::hasTable('documents') || ! Schema::hasColumn('documents', 'status')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents ALTER COLUMN status TYPE VARCHAR(50) USING status::VARCHAR(50)');

            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('status', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('documents') || ! Schema::hasColumn('documents', 'status')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            $hasLongStatus = DB::table('documents')
                ->whereNotNull('status')
                ->whereRaw('LENGTH(status) > 6')
                ->exists();

            if (! $hasLongStatus) {
                DB::statement('ALTER TABLE documents ALTER COLUMN status TYPE VARCHAR(6) USING status::VARCHAR(6)');
            }

            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->string('status', 6)->nullable()->change();
        });
    }
};
