<?php

namespace Tests\Unit;

use App\Support\ClientDetailAccountTab;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @property mixed $app
 */
class ClientDetailAccountTabTest extends TestCase
{
    #[Test]
    public function build_returns_empty_ledger_payload_when_the_client_has_no_receipts(): void
    {
        $this->createAccountTabSchema();

        $payload = ClientDetailAccountTab::build((object) [
            'id' => 1,
            'client_id' => 'C001',
        ]);

        Assert::assertNull($payload['client_selected_matter_id']);
        Assert::assertSame(0.0, $payload['calculated_balance']);
        Assert::assertSame(0.0, $payload['latest_outstanding_balance']);
        Assert::assertEmpty($payload['receipts_lists']);
        Assert::assertEmpty($payload['receipts_lists_office']);
        Assert::assertSame([], $payload['receipts_lists_invoice']);
    }

    #[Test]
    public function build_keeps_latest_invoice_per_receipt_and_skips_voided_transfers_in_balance(): void
    {
        $this->createAccountTabSchema();

        DB::table('account_client_receipts')->insert([
            [
                'client_id' => 1,
                'client_matter_id' => null,
                'receipt_id' => 10,
                'receipt_type' => 1,
                'deposit_amount' => 50,
                'withdraw_amount' => 0,
                'balance_amount' => 0,
                'void_fee_transfer' => 0,
                'invoice_status' => null,
                'invoice_no' => null,
                'uploaded_doc_id' => null,
            ],
            [
                'client_id' => 1,
                'client_matter_id' => null,
                'receipt_id' => 11,
                'receipt_type' => 1,
                'deposit_amount' => 0,
                'withdraw_amount' => 10,
                'balance_amount' => 0,
                'void_fee_transfer' => 0,
                'invoice_status' => null,
                'invoice_no' => null,
                'uploaded_doc_id' => null,
            ],
            [
                'client_id' => 1,
                'client_matter_id' => null,
                'receipt_id' => 12,
                'receipt_type' => 1,
                'deposit_amount' => 100,
                'withdraw_amount' => 0,
                'balance_amount' => 0,
                'void_fee_transfer' => 1,
                'invoice_status' => null,
                'invoice_no' => null,
                'uploaded_doc_id' => null,
            ],
            [
                'client_id' => 1,
                'client_matter_id' => null,
                'receipt_id' => 20,
                'receipt_type' => 3,
                'deposit_amount' => 0,
                'withdraw_amount' => 0,
                'balance_amount' => 10,
                'void_fee_transfer' => 0,
                'invoice_status' => 0,
                'invoice_no' => 'INV-1',
                'uploaded_doc_id' => null,
            ],
            [
                'client_id' => 1,
                'client_matter_id' => null,
                'receipt_id' => 20,
                'receipt_type' => 3,
                'deposit_amount' => 0,
                'withdraw_amount' => 0,
                'balance_amount' => 25,
                'void_fee_transfer' => 0,
                'invoice_status' => 0,
                'invoice_no' => 'INV-1',
                'uploaded_doc_id' => null,
            ],
            [
                'client_id' => 1,
                'client_matter_id' => null,
                'receipt_id' => 30,
                'receipt_type' => 2,
                'deposit_amount' => 40,
                'withdraw_amount' => 0,
                'balance_amount' => 0,
                'void_fee_transfer' => 0,
                'invoice_status' => null,
                'invoice_no' => null,
                'uploaded_doc_id' => null,
            ],
        ]);

        $payload = ClientDetailAccountTab::build((object) [
            'id' => 1,
            'client_id' => 'C001',
        ]);

        Assert::assertSame(40.0, $payload['calculated_balance']);
        Assert::assertSame(35.0, $payload['latest_outstanding_balance']);
        Assert::assertCount(3, $payload['receipts_lists']);
        Assert::assertCount(1, $payload['receipts_lists_invoice']);
        Assert::assertSame(25.0, (float) $payload['receipts_lists_invoice'][0]->balance_amount);
        Assert::assertCount(1, $payload['receipts_lists_office']);
    }

    private function createAccountTabSchema(): void
    {
        $schema = $this->app->make('db')->connection()->getSchemaBuilder();

        if (! $schema->hasTable('client_matters')) {
            $schema->create('client_matters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('client_unique_matter_no')->nullable();
                $table->unsignedInteger('matter_status')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('account_client_receipts')) {
            $schema->create('account_client_receipts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
                $table->unsignedBigInteger('receipt_id')->nullable();
                $table->unsignedInteger('receipt_type')->nullable();
                $table->decimal('deposit_amount', 12, 2)->nullable();
                $table->decimal('withdraw_amount', 12, 2)->nullable();
                $table->decimal('balance_amount', 12, 2)->nullable();
                $table->unsignedInteger('void_fee_transfer')->nullable();
                $table->unsignedInteger('invoice_status')->nullable();
                $table->string('invoice_no')->nullable();
                $table->unsignedBigInteger('uploaded_doc_id')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('documents')) {
            $schema->create('documents', function (Blueprint $table) {
                $table->id();
                $table->string('myfile')->nullable();
                $table->timestamps();
            });
        }
    }
}
