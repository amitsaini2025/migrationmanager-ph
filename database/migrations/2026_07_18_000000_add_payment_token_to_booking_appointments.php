<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_appointments', 'payment_token')) {
                $table->string('payment_token', 64)->nullable()->unique()->after('order_hash');
            }
            if (! Schema::hasColumn('booking_appointments', 'payment_token_expires_at')) {
                $table->dateTime('payment_token_expires_at')->nullable()->after('payment_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('booking_appointments', 'payment_token_expires_at')) {
                $table->dropColumn('payment_token_expires_at');
            }
            if (Schema::hasColumn('booking_appointments', 'payment_token')) {
                $table->dropColumn('payment_token');
            }
        });
    }
};
