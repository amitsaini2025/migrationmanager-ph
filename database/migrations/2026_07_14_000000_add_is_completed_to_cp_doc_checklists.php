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
            if (!Schema::hasColumn('cp_doc_checklists', 'is_completed')) {
                $table->boolean('is_completed')->default(false)->after('is_required');
            }
            if (!Schema::hasColumn('cp_doc_checklists', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_completed');
            }
            if (!Schema::hasColumn('cp_doc_checklists', 'completed_by')) {
                $table->unsignedInteger('completed_by')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cp_doc_checklists')) {
            return;
        }

        Schema::table('cp_doc_checklists', function (Blueprint $table) {
            foreach (['completed_by', 'completed_at', 'is_completed'] as $column) {
                if (Schema::hasColumn('cp_doc_checklists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
