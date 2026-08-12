<?php

namespace Tests\Unit;

use Tests\TestCase;

class CostAssignmentLeadRedirectUrlTest extends TestCase
{
    public function test_new_matter_checklists_redirect_url_matches_clients_detail_route(): void
    {
        $clientId = 42;
        $matterRef = 'APC_3';
        $encodedId = base64_encode(convert_uuencode((string) $clientId));

        $url = route('clients.detail', [
            'client_id' => $encodedId,
            'client_unique_matter_ref_no' => $matterRef,
            'tab' => 'checklists',
        ]);

        $path = parse_url($url, PHP_URL_PATH);

        $this->assertSame(
            '/clients/detail/'.$encodedId.'/'.$matterRef.'/checklists',
            $path
        );
    }

    public function test_redirect_url_keeps_matter_segment_before_checklists_tab(): void
    {
        $encodedId = base64_encode(convert_uuencode('99'));
        $matterRef = 'GN_1';

        $url = route('clients.detail', [
            'client_id' => $encodedId,
            'client_unique_matter_ref_no' => $matterRef,
            'tab' => 'checklists',
        ]);

        $this->assertStringContainsString('/'.$matterRef.'/checklists', parse_url($url, PHP_URL_PATH));
        $this->assertStringNotContainsString('/checklists/'.$matterRef, parse_url($url, PHP_URL_PATH));
    }
}
