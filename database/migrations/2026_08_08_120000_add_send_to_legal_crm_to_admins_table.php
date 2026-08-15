<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds send_to_legal_crm flag on admins (0 = default, 1 = sent to Legal CRM).
     */
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (! Schema::hasColumn('admins', 'send_to_legal_crm')) {
                $table->unsignedTinyInteger('send_to_legal_crm')->default(0)
                    ->comment('0 = not sent, 2 = pending cron sync, 1 = sent to Legal CRM');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('admins')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (Schema::hasColumn('admins', 'send_to_legal_crm')) {
                $table->dropColumn('send_to_legal_crm');
            }
        });
    }
};
