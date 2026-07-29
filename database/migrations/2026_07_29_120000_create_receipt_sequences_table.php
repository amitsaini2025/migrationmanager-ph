<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Counter table backing receipt_id allocation.
 *
 * Receipt ids were previously allocated as MAX(receipt_id) + 1 with no locking, so two
 * concurrent saves could read the same maximum and insert the same receipt_id. Reading a
 * dedicated counter row under a row lock makes allocation atomic without depending on the
 * caller's insert already being visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipt_sequences')) {
            return;
        }

        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->integer('receipt_type')->primary();
            $table->bigInteger('last_receipt_id')->default(0);
            $table->timestamps();
        });

        if (! Schema::hasTable('account_client_receipts')) {
            return;
        }

        // Seed each existing receipt type from the highest id already issued.
        $rows = DB::table('account_client_receipts')
            ->select('receipt_type', DB::raw('MAX(receipt_id) as max_receipt_id'))
            ->whereNotNull('receipt_type')
            ->groupBy('receipt_type')
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('receipt_sequences')->insert([
                'receipt_type' => (int) $row->receipt_type,
                'last_receipt_id' => (int) ($row->max_receipt_id ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_sequences');
    }
};
