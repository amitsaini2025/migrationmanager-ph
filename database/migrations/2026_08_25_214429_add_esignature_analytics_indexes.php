<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Speeds Admin Console e-signature analytics: the page previously scanned all
     * documents (including client uploads) and had no usable status/created_by indexes.
     */
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            if (DB::getDriverName() === 'pgsql') {
                if (! $this->pgIndexExists('documents_esignature_status_created_at_idx')) {
                    DB::statement(
                        'CREATE INDEX documents_esignature_status_created_at_idx ON documents (status, created_at) '
                        .'WHERE created_by IS NOT NULL'
                    );
                }
                if (! $this->pgIndexExists('documents_esignature_created_by_status_idx')) {
                    DB::statement(
                        'CREATE INDEX documents_esignature_created_by_status_idx ON documents (created_by, status) '
                        .'WHERE created_by IS NOT NULL'
                    );
                }
            } else {
                Schema::table('documents', function (Blueprint $table) {
                    if (! Schema::hasIndex('documents', 'documents_esignature_status_created_at_idx')) {
                        $table->index(['status', 'created_at'], 'documents_esignature_status_created_at_idx');
                    }
                    if (! Schema::hasIndex('documents', 'documents_esignature_created_by_status_idx')) {
                        $table->index(['created_by', 'status'], 'documents_esignature_created_by_status_idx');
                    }
                });
            }
        }

        if (Schema::hasTable('signers') && ! Schema::hasIndex('signers', 'signers_document_id_signed_at_idx')) {
            Schema::table('signers', function (Blueprint $table) {
                $table->index(['document_id', 'signed_at'], 'signers_document_id_signed_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            if (DB::getDriverName() === 'pgsql') {
                foreach (['documents_esignature_status_created_at_idx', 'documents_esignature_created_by_status_idx'] as $index) {
                    if ($this->pgIndexExists($index)) {
                        DB::statement('DROP INDEX IF EXISTS '.$this->quoteId($index));
                    }
                }
            } else {
                Schema::table('documents', function (Blueprint $table) {
                    if (Schema::hasIndex('documents', 'documents_esignature_status_created_at_idx')) {
                        $table->dropIndex('documents_esignature_status_created_at_idx');
                    }
                    if (Schema::hasIndex('documents', 'documents_esignature_created_by_status_idx')) {
                        $table->dropIndex('documents_esignature_created_by_status_idx');
                    }
                });
            }
        }

        if (Schema::hasTable('signers') && Schema::hasIndex('signers', 'signers_document_id_signed_at_idx')) {
            Schema::table('signers', function (Blueprint $table) {
                $table->dropIndex('signers_document_id_signed_at_idx');
            });
        }
    }

    private function pgIndexExists(string $name): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        return (bool) DB::selectOne('SELECT 1 AS ok FROM pg_indexes WHERE indexname = ?', [$name]);
    }

    private function quoteId(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
};
