<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'service_token')) {
                $table->string('service_token')->nullable()->after('tax_number');
            }
            if (! Schema::hasColumn('staff', 'token_generated_at')) {
                $table->timestamp('token_generated_at')->nullable()->after('service_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'token_generated_at')) {
                $table->dropColumn('token_generated_at');
            }
            if (Schema::hasColumn('staff', 'service_token')) {
                $table->dropColumn('service_token');
            }
        });
    }
};
