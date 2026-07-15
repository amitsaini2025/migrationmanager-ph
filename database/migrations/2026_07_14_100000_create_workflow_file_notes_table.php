<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workflow_file_notes')) {
            return;
        }

        Schema::create('workflow_file_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id')->nullable()->index();
            $table->unsignedBigInteger('client_matter_id')->index();
            $table->unsignedBigInteger('workflow_stage_id')->index();
            $table->text('body');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['client_matter_id', 'workflow_stage_id'], 'workflow_file_notes_matter_stage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_file_notes');
    }
};
