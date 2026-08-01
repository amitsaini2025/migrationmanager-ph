<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProcessServiceAccountTokensCommandTest extends TestCase
{
    public function test_bare_run_requires_staff_id_or_all_flag(): void
    {
        $this->artisan('service-account:generate-token')
            ->expectsOutputToContain('Provide a staff_id, or pass --all')
            ->assertExitCode(1);
    }
}
