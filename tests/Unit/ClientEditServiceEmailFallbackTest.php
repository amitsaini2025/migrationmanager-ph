<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientEmail;
use App\Services\ClientEditService;
use Tests\TestCase;

class ClientEditServiceEmailFallbackTest extends TestCase
{
    public function test_admin_email_fallback_includes_verification_fields_without_persisting(): void
    {
        $admin = new Admin;
        $admin->email = 'meegallanetwork@gmail.com';
        $admin->email_type = 'Personal';

        $email = $this->makeFallback(42, $admin);

        $this->assertInstanceOf(ClientEmail::class, $email);
        $this->assertFalse($email->exists);
        $this->assertNull($email->id);
        $this->assertSame(42, $email->client_id);
        $this->assertSame('meegallanetwork@gmail.com', $email->email);
        $this->assertSame('Personal', $email->email_type);
        $this->assertFalse($email->is_verified);
        $this->assertNull($email->verified_at);
        $this->assertNull($email->verified_by);
    }

    public function test_admin_email_fallback_defaults_type_when_missing(): void
    {
        $admin = new Admin;
        $admin->email = 'lead@example.com';

        $email = $this->makeFallback(7, $admin);

        $this->assertSame('Personal', $email->email_type);
        $this->assertFalse($email->is_verified ?? false);
    }

    public function test_saved_verified_email_still_reads_as_verified(): void
    {
        $email = new ClientEmail;
        $email->id = 99;
        $email->email = 'saved@example.com';
        $email->email_type = 'Work';
        $email->is_verified = true;
        $email->exists = true;

        $this->assertTrue($email->is_verified ?? false);
        $this->assertNotEmpty($email->id);
    }

    private function makeFallback(int $clientId, Admin $admin): ClientEmail
    {
        $method = new \ReflectionMethod(ClientEditService::class, 'makeAdminEmailFallback');

        return $method->invoke(new ClientEditService, $clientId, $admin);
    }
}
