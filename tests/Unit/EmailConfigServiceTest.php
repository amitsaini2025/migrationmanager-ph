<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Email;
use App\Services\EmailConfigService;
class EmailConfigServiceTest extends TestCase
{

    protected EmailConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailConfigService();
    }

    /** @test */
    public function it_can_get_email_config_by_email_address()
    {
        Email::factory()->create([
            'email' => 'test@example.com',
            'display_name' => 'Test Sender',
            'status' => true
        ]);

        $config = $this->service->forAccount('test@example.com');

        $this->assertEquals('test@example.com', $config['from_address']);
        $this->assertEquals('Test Sender', $config['from_name']);
    }

    /** @test */
    public function it_throws_exception_when_email_not_found_by_id()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email configuration not found for ID: 9999');

        $this->service->forAccountById(9999);
    }

    /** @test */
    public function it_throws_exception_when_email_not_found_by_address()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Email configuration not found for: nonexistent@example.com');

        $this->service->forAccount('nonexistent@example.com');
    }

    /** @test */
    public function it_only_finds_active_emails_when_searching_by_address()
    {
        Email::factory()->create([
            'email' => 'inactive@example.com',
            'status' => false // Inactive
        ]);

        $this->expectException(\Exception::class);

        $this->service->forAccount('inactive@example.com');
    }

    /** @test */
    public function it_can_get_all_active_accounts()
    {
        Email::factory()->create([
            'email' => 'active1@example.com',
            'status' => true
        ]);
        Email::factory()->create([
            'email' => 'active2@example.com',
            'status' => true
        ]);
        Email::factory()->create([
            'email' => 'inactive@example.com',
            'status' => false
        ]);

        $accounts = $this->service->getActiveAccounts();

        $this->assertCount(2, $accounts);
        $this->assertEquals('active1@example.com', $accounts[0]->email);
        $this->assertEquals('active2@example.com', $accounts[1]->email);
    }

    /** @test */
    public function it_can_get_default_account()
    {
        Email::factory()->create([
            'id' => 1,
            'email' => 'first@example.com',
            'status' => true
        ]);
        Email::factory()->create([
            'id' => 2,
            'email' => 'second@example.com',
            'status' => true
        ]);

        $config = $this->service->getDefaultAccount();

        $this->assertNotNull($config);
        $this->assertEquals('first@example.com', $config['from_address']);
    }

    /** @test */
    public function it_returns_null_when_no_active_accounts_exist()
    {
        Email::factory()->create([
            'email' => 'inactive@example.com',
            'status' => false
        ]);

        // Clear environment variables
        putenv('MAIL_FROM_ADDRESS');

        $config = $this->service->getDefaultAccount();

        $this->assertNull($config);
    }

    /** @test */
    public function it_falls_back_to_environment_config_when_no_active_accounts()
    {
        putenv('MAIL_FROM_ADDRESS=env@example.com');
        putenv('MAIL_FROM_NAME=Environment Sender');

        $config = $this->service->getDefaultAccount();

        $this->assertNotNull($config);
        $this->assertEquals('env@example.com', $config['from_address']);
        $this->assertEquals('Environment Sender', $config['from_name']);

        putenv('MAIL_FROM_ADDRESS');
        putenv('MAIL_FROM_NAME');
    }

    /** @test */
    public function build_config_returns_only_from_address_from_name_and_email_signature()
    {
        $email = Email::factory()->create([
            'email' => 'test@example.com',
            'display_name' => 'Test User',
            'email_signature' => '<p>Signature</p>',
            'status' => true
        ]);

        $config = $this->service->forAccountById($email->id);

        $this->assertIsArray($config);
        $this->assertCount(3, $config);
        $this->assertArrayHasKey('from_address', $config);
        $this->assertArrayHasKey('from_name', $config);
        $this->assertArrayHasKey('email_signature', $config);
        $this->assertEquals('test@example.com', $config['from_address']);
        $this->assertEquals('Test User', $config['from_name']);
        $this->assertEquals('<p>Signature</p>', $config['email_signature']);
    }

    /** @test */
    public function get_default_account_falls_back_to_mail_from_address_env_var()
    {
        putenv('MAIL_FROM_ADDRESS=fallback@example.com');
        putenv('MAIL_FROM_NAME=Fallback Sender');

        $config = $this->service->getDefaultAccount();

        $this->assertNotNull($config);
        $this->assertEquals('fallback@example.com', $config['from_address']);
        $this->assertEquals('Fallback Sender', $config['from_name']);

        putenv('MAIL_FROM_ADDRESS');
        putenv('MAIL_FROM_NAME');
    }
}
