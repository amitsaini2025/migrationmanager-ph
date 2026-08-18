<?php

namespace Tests\Unit;

use App\Support\ClientDetailAccountTab;
use Illuminate\Database\Schema\Blueprint;
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
