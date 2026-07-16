<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily CRM presence for staff using an existing session (Remember Me / still logged in).
     * Separate from staff_login_logs so login audit behaviour stays unchanged.
     */
    public function up(): void
    {
        if (Schema::hasTable('staff_activity_logs')) {
            return;
        }

        Schema::create('staff_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->index();
            $table->date('activity_date')->index();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_logs');
    }
};
