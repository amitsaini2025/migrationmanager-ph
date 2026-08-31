<?php

namespace Tests\Unit\Mail;

use App\Mail\ClientDetailVerificationMail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDetailVerificationMailTest extends TestCase
{
    #[Test]
    public function it_renders_the_requested_copy_and_link(): void
    {
        $url = 'https://example.test/verify-details/abc123abc123abc123abc123abc123ab';
        $html = (new ClientDetailVerificationMail('Vipul', $url))->render();

        $this->assertStringContainsString('Hi Vipul, Bansal Immigration Consultants requests you to verify your Personal &amp; Visa details currently recorded on your file.', $html);
        $this->assertStringContainsString('Please review and confirm or request any corrections using the secure link below:', $html);
        $this->assertStringContainsString($url, $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('Registered Migration Agents', $html);
        $this->assertStringContainsString('Secure verification link', $html);
        $this->assertStringContainsString('Open verification form', $html);
        $this->assertStringContainsString('Bansal Immigration Team', $html);
        $this->assertStringNotContainsString('FIRST-TIME CLIENTS ONLY', $html);
        $this->assertStringNotContainsString('Appointment Details', $html);
        $this->assertStringNotContainsString('Need to Reschedule or Have Questions?', $html);
        $this->assertStringNotContainsString('>Cancel</a>', $html);
        $this->assertStringNotContainsString('>Reschedule</a>', $html);
        $this->assertStringNotContainsString('>Confirm</a>', $html);
    }

    #[Test]
    public function it_disables_sendgrid_click_tracking_so_the_token_url_is_not_rewritten(): void
    {
        $headers = (new ClientDetailVerificationMail(
            'Vipul',
            'https://example.test/verify-details/abc123abc123abc123abc123abc123ab',
        ))->headers();

        $smtpApi = $headers->text['X-SMTPAPI'] ?? '';
        $this->assertStringContainsString('clicktrack', $smtpApi);
        $this->assertStringContainsString('"enable":0', $smtpApi);
    }
}
