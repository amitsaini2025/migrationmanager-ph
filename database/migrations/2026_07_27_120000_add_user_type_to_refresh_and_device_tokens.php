<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow refresh_tokens / device_tokens to reference either admins (clients)
     * or staff without an admins-only foreign key (A2).
     */
    public function up(): void
    {
        $this->dropUserIdForeignIfExists('refresh_tokens');
        $this->dropUserIdForeignIfExists('device_tokens');

        if (Schema::hasTable('refresh_tokens') && ! Schema::hasColumn('refresh_tokens', 'user_type')) {
            Schema::table('refresh_tokens', function (Blueprint $table) {
                // admin = App\Models\Admin (client portal); staff = App\Models\Staff
                $table->string('user_type', 20)->default('admin')->after('user_id');
                $table->index(['user_type', 'user_id', 'is_revoked'], 'refresh_tokens_user_type_user_id_revoked_index');
            });
        }

        if (Schema::hasTable('device_tokens') && ! Schema::hasColumn('device_tokens', 'user_type')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->string('user_type', 20)->default('admin')->after('user_id');
                $table->index(['user_type', 'user_id', 'is_active'], 'device_tokens_user_type_user_id_active_index');
            });
        }

        // Existing rows are client-portal (admins) tokens
        if (Schema::hasColumn('refresh_tokens', 'user_type')) {
            DB::table('refresh_tokens')->whereNull('user_type')->update(['user_type' => 'admin']);
        }
        if (Schema::hasColumn('device_tokens', 'user_type')) {
            DB::table('device_tokens')->whereNull('user_type')->update(['user_type' => 'admin']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('refresh_tokens') && Schema::hasColumn('refresh_tokens', 'user_type')) {
            Schema::table('refresh_tokens', function (Blueprint $table) {
                $table->dropIndex('refresh_tokens_user_type_user_id_revoked_index');
                $table->dropColumn('user_type');
            });
        }

        if (Schema::hasTable('device_tokens') && Schema::hasColumn('device_tokens', 'user_type')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropIndex('device_tokens_user_type_user_id_active_index');
                $table->dropColumn('user_type');
            });
        }

        // Restore admins FK only for rows that still point at admins
        if (Schema::hasTable('refresh_tokens')) {
            $this->nullOrphanUserIds('refresh_tokens', 'admins');
            Schema::table('refresh_tokens', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('admins')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('device_tokens')) {
            $this->nullOrphanUserIds('device_tokens', 'admins');
            // device_tokens.user_id is NOT NULL — delete orphans instead of nulling
            DB::table('device_tokens')
                ->whereNotIn('user_id', DB::table('admins')->select('id'))
                ->delete();
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('admins')->onDelete('cascade');
            });
        }
    }

    private function dropUserIdForeignIfExists(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $constraints = DB::select("
                SELECT tc.constraint_name
                FROM information_schema.table_constraints AS tc
                JOIN information_schema.key_column_usage AS kcu
                  ON tc.constraint_name = kcu.constraint_name
                 AND tc.table_schema = kcu.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY'
                  AND tc.table_schema = current_schema()
                  AND tc.table_name = ?
                  AND kcu.column_name = 'user_id'
            ", [$table]);

            foreach ($constraints as $constraint) {
                DB::statement('ALTER TABLE '.$table.' DROP CONSTRAINT IF EXISTS '.$constraint->constraint_name);
            }

            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['user_id']);
            });
        } catch (\Throwable $e) {
            // FK may already be absent on some environments
        }
    }

    private function nullOrphanUserIds(string $table, string $parentTable): void
    {
        // refresh_tokens.user_id is NOT NULL; only used conceptually — down() re-adds FK
        // after deleting staff-typed rows that cannot satisfy admins FK.
        DB::table($table)
            ->where('user_type', 'staff')
            ->delete();

        DB::table($table)
            ->whereNotIn('user_id', DB::table($parentTable)->select('id'))
            ->delete();
    }
};
