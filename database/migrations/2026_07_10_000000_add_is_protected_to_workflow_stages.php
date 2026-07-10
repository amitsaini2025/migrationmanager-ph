<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_stages') || Schema::hasColumn('workflow_stages', 'is_protected')) {
            return;
        }

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_stages') || !Schema::hasColumn('workflow_stages', 'is_protected')) {
            return;
        }

        Schema::table('workflow_stages', function (Blueprint $table) {
            $table->dropColumn('is_protected');
        });
    }
};
