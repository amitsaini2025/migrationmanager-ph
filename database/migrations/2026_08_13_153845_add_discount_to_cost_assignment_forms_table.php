<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_assignment_forms', function (Blueprint $table) {
            if (! Schema::hasColumn('cost_assignment_forms', 'discount_enabled')) {
                $table->boolean('discount_enabled')->default(false)->after('additional_fee_1');
            }
            if (! Schema::hasColumn('cost_assignment_forms', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('discount_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cost_assignment_forms', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('cost_assignment_forms', 'discount')) {
                $columns[] = 'discount';
            }
            if (Schema::hasColumn('cost_assignment_forms', 'discount_enabled')) {
                $columns[] = 'discount_enabled';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
