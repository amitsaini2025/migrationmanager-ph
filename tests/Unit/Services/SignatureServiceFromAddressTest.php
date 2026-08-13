<?php

namespace Tests\Unit\Services;

use App\Services\EmailConfigService;
use App\Services\SignatureService;
use App\Services\SystemEmailLogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SignatureServiceFromAddressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('emails')) {
            Schema::create('emails', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->string('display_name')->nullable();
                $table->boolean('status')->default(true);
                $table->text('email_signature')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    #[Test]
    public function resolve_from_defaults_to_signature_mail_address_not_global_from(): void
    {
        config([
            'mail.from.address' => 'info@bansalimmigration.com.au',
            'mail.from.name' => 'Bansal Immigration',
            'mail.noreply.address' => 'noreply@bansalimmigration.com.au',
        ]);

        $emailConfig = Mockery::mock(EmailConfigService::class);
        $emailConfig->shouldReceive('getDefaultAccount')->andReturn([
            'from_address' => 'info@bansalimmigration.com.au',
            'from_name' => 'Default Account',
            'email_signature' => '<p>Team</p>',
        ]);

        $service = new SignatureService(
            $emailConfig,
            app(SystemEmailLogService::class)
        );

        $method = new ReflectionMethod(SignatureService::class, 'resolveFrom');
        $method->setAccessible(true);

        $resolved = $method->invoke($service, null);

        $this->assertSame('noreply@bansalimmigration.com.au', $resolved['from_address']);
        $this->assertSame('Bansal Immigration', $resolved['from_name']);
        $this->assertNotSame(config('mail.from.address'), $resolved['from_address']);
    }

    #[Test]
    public function resolve_from_keeps_explicit_preferred_from_email(): void
    {
        config([
            'mail.from.address' => 'info@bansalimmigration.com.au',
            'mail.from.name' => 'Bansal Immigration',
            'mail.noreply.address' => 'noreply@bansalimmigration.com.au',
        ]);

        $emailConfig = Mockery::mock(EmailConfigService::class);
        $emailConfig->shouldReceive('getDefaultAccount')->andReturn([
            'from_address' => 'info@bansalimmigration.com.au',
            'from_name' => 'Default Account',
            'email_signature' => '<p>Team</p>',
        ]);

        $service = new SignatureService(
            $emailConfig,
            app(SystemEmailLogService::class)
        );

        $method = new ReflectionMethod(SignatureService::class, 'resolveFrom');
        $method->setAccessible(true);

        $resolved = $method->invoke($service, 'agent@bansalimmigration.com.au');

        $this->assertSame('agent@bansalimmigration.com.au', $resolved['from_address']);
    }
}
