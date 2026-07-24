<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whole-form "Verify details" on client/company edit (re-verifiable).
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'details_verified_at')) {
                $table->timestamp('details_verified_at')->nullable()->after('visa_expiry_verified_by');
            }
            if (! Schema::hasColumn('admins', 'details_verified_by')) {
                $table->unsignedInteger('details_verified_by')->nullable()->after('details_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'details_verified_by')) {
                $table->dropColumn('details_verified_by');
            }
            if (Schema::hasColumn('admins', 'details_verified_at')) {
                $table->dropColumn('details_verified_at');
            }
        });
    }
};
