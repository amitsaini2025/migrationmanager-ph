<?php

namespace Tests\Unit\Support;

use App\Support\ClientDetailVerificationFields;
use App\Support\ClientDetailVerificationUi;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDetailVerificationUiTest extends TestCase
{
    #[Test]
    public function change_request_icon_uses_request_change_hover_title(): void
    {
        $html = ClientDetailVerificationUi::icon([
            'id' => 12,
            'field_key' => 'gender',
            'status' => ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED,
            'original_value' => 'Other',
            'requested_value' => 'Male',
        ]);

        $this->assertStringContainsString('title="Request Change"', $html);
        $this->assertStringContainsString('data-change-request="1"', $html);
        $this->assertStringNotContainsString('title="Change requested', $html);
    }

    #[Test]
    public function confirmed_icon_uses_confirmed_hover_title(): void
    {
        $html = ClientDetailVerificationUi::icon([
            'field_key' => 'marital_status',
            'status' => ClientDetailVerificationFields::STATUS_CONFIRMED,
        ]);

        $this->assertStringContainsString('title="Confirmed"', $html);
        $this->assertStringNotContainsString('data-change-request', $html);
    }
}
