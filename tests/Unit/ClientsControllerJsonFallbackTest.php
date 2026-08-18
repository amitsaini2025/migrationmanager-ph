<?php

namespace Tests\Unit;

use App\Http\Controllers\CRM\ClientsController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use Tests\TestCase;

/**
 * @property mixed $app
 */
class ClientsControllerJsonFallbackTest extends TestCase
{
    public function test_savecostassignment_non_post_returns_json_error(): void
    {
        $controller = $this->app->make(ClientsController::class);
        $request = $this->makeRequest('/clients/savecostassignment', 'GET');

        ob_start();
        $controller->savecostassignment($request);
        $payload = json_decode((string) ob_get_clean(), true);

        Assert::assertIsArray($payload);
        Assert::assertFalse($payload['status']);
        Assert::assertSame('An error occurred. Please try again.', $payload['message']);
    }

    public function test_get_migration_agent_detail_returns_json_when_matter_is_missing(): void
    {
        $this->createAgentDetailSchema();

        $controller = $this->app->make(ClientsController::class);
        $request = $this->makeRequest('/clients/getMigrationAgentDetail', 'POST', [
            'client_matter_id' => 999999,
        ]);

        ob_start();
        $controller->getMigrationAgentDetail($request);
        $payload = json_decode((string) ob_get_clean(), true);

        Assert::assertIsArray($payload);
        Assert::assertFalse($payload['status']);
        Assert::assertSame('Record is not exist.Please try again', $payload['message']);
        Assert::assertSame('', $payload['matterInfo']);
        Assert::assertSame('', $payload['agentInfo']);
    }

    public function test_get_cost_assignment_agent_detail_returns_json_when_matter_is_missing(): void
    {
        $this->createAgentDetailSchema();

        $schema = $this->schemaBuilder();
        if (! $schema->hasTable('cost_assignment_forms')) {
            $schema->create('cost_assignment_forms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('client_matter_id')->nullable();
            });
        }

        $controller = $this->app->make(ClientsController::class);
        $request = $this->makeRequest('/clients/getCostAssignmentMigrationAgentDetail', 'POST', [
            'client_matter_id' => 999999,
            'client_id' => 1,
        ]);

        ob_start();
        $controller->getCostAssignmentMigrationAgentDetail($request);
        $payload = json_decode((string) ob_get_clean(), true);

        Assert::assertIsArray($payload);
        Assert::assertFalse($payload['status']);
        Assert::assertSame('Record is not exist.Please try again', $payload['message']);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function makeRequest(string $uri, string $method, array $parameters = []): Request
    {
        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
        ];

        if (strtoupper($method) === 'GET') {
            /** @disregard */
            return new Request($parameters, [], [], [], [], $server);
        }

        /** @disregard */
        return new Request([], $parameters, [], [], [], $server);
    }

    private function schemaBuilder(): Builder
    {
        return $this->app->make('db')->connection()->getSchemaBuilder();
    }

    private function createAgentDetailSchema(): void
    {
        $schema = $this->schemaBuilder();

        if (! $schema->hasTable('client_matters')) {
            $schema->create('client_matters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('sel_migration_agent')->nullable();
                $table->unsignedBigInteger('sel_matter_id')->nullable();
            });
        }

        if (! $schema->hasTable('matters')) {
            $schema->create('matters', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('nick_name')->nullable();
            });
        }

        if (! $schema->hasTable('staff')) {
            $schema->create('staff', function (Blueprint $table) {
                $table->id();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('company_name')->nullable();
                $table->integer('is_migration_agent')->nullable();
                $table->string('marn_number')->nullable();
                $table->string('legal_practitioner_number')->nullable();
                $table->string('business_address')->nullable();
                $table->string('business_phone')->nullable();
                $table->string('business_mobile')->nullable();
                $table->string('business_email')->nullable();
                $table->string('tax_number')->nullable();
            });
        }
    }
}
