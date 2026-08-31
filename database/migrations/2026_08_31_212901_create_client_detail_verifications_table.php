<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_detail_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id')->index();
            $table->string('token_hash', 64)->unique();
            $table->string('sent_to_email', 255);
            $table->unsignedInteger('sent_by')->nullable()->index();
            $table->json('snapshot')->nullable();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamp('invalidated_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'used_at', 'invalidated_at']);
        });

        Schema::create('client_detail_verification_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('verification_id')->index();
            $table->unsignedInteger('client_id')->index();
            $table->string('field_key', 64);
            $table->text('original_value')->nullable();
            $table->text('requested_value')->nullable();
            $table->string('status', 32)->default('confirmed');
            $table->text('note')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->unsignedInteger('accepted_by')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'field_key', 'status']);
            $table->index(['verification_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_detail_verification_fields');
        Schema::dropIfExists('client_detail_verifications');
    }
};
