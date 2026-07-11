<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cp_doc_checklists')) {
            return;
        }

        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            if (!Schema::hasColumn('cp_doc_checklists', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('allow_client');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cp_doc_checklists')) {
            return;
        }

        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            if (Schema::hasColumn('cp_doc_checklists', 'is_required')) {
                $table->dropColumn('is_required');
            }
        });
    }
};
