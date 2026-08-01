<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->forbidDestructiveDatabaseTraits();
    }

    /**
     * Hard ban: RefreshDatabase / DatabaseMigrations run migrate:fresh and can wipe DBs.
     * Prefer no DB, sqlite fixtures, or DatabaseTransactions — never migrate:fresh.
     */
    protected function forbidDestructiveDatabaseTraits(): void
    {
        $traits = class_uses_recursive(static::class);

        if (isset($traits[RefreshDatabase::class]) || isset($traits[DatabaseMigrations::class])) {
            $this->fail(
                'RefreshDatabase / DatabaseMigrations are forbidden in this project '
                .'(they run migrate:fresh and can wipe databases). '
                .'Remove the trait and use non-destructive tests instead.'
            );
        }
    }
}
