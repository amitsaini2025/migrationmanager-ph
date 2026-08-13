<?php

namespace Tests\Unit;

use App\Support\ComposeEmailPayload;
use PHPUnit\Framework\TestCase;

class ComposeEmailPayloadTest extends TestCase
{
    public function test_decode_subject_restores_amp_placeholder(): void
    {
        $this->assertSame(
            'A & B',
            ComposeEmailPayload::decodeSubject(['subject' => 'A __AMP__ B'])
        );
    }

    public function test_decode_message_returns_plain_when_not_base64(): void
    {
        $html = '<p>Hello <a href="https://example.com">link</a></p>';

        $this->assertSame(
            $html,
            ComposeEmailPayload::decodeMessage(['message' => $html])
        );
    }

    public function test_decode_message_decodes_base64_utf8_body(): void
    {
        $html = '<p>Dear Vipul,</p><p>Visit https://afpnationalpolicechecks.converga.com.au</p>';
        $encoded = base64_encode($html);

        $this->assertSame(
            $html,
            ComposeEmailPayload::decodeMessage([
                'message' => $encoded,
                'message_encoding' => 'base64',
            ])
        );
    }

    public function test_decode_message_falls_back_on_invalid_base64(): void
    {
        $this->assertSame(
            'not-valid-base64!!!',
            ComposeEmailPayload::decodeMessage([
                'message' => 'not-valid-base64!!!',
                'message_encoding' => 'base64',
            ])
        );
    }

    public function test_normalize_signing_url_accepts_valid_sign_path(): void
    {
        $url = 'https://migrationmanager.example.com/sign/237011/abc123XYZ';

        $this->assertSame($url, ComposeEmailPayload::normalizeSigningUrl($url));
    }

    public function test_normalize_signing_url_accepts_token_with_underscore(): void
    {
        $url = 'https://migrationmanager.example.com/sign/193568/alp27l8edYFdBigKvamN68w59MXfl_XF5vw2qBApPmlsEhPk8NYil3Bg21NSzdSJ9';

        $this->assertSame($url, ComposeEmailPayload::normalizeSigningUrl($url));
    }

    public function test_normalize_signing_url_rejects_non_sign_urls(): void
    {
        $this->assertNull(ComposeEmailPayload::normalizeSigningUrl('https://example.com/other/page'));
        $this->assertNull(ComposeEmailPayload::normalizeSigningUrl(''));
    }

    public function test_apply_signing_link_replaces_macro_with_anchor(): void
    {
        $url = 'https://migrationmanager.example.com/sign/1/token123';
        $message = '<p>Agreement: {PDF_url_for_sign}</p>';

        $result = ComposeEmailPayload::applySigningLinkToMessage($message, $url);

        $this->assertStringContainsString('href="'.$url.'"', $result);
        $this->assertStringContainsString('Sign Service Agreement</a>', $result);
        $this->assertStringNotContainsString('{PDF_url_for_sign}', $result);
    }

    public function test_apply_signing_link_repairs_underline_only_label(): void
    {
        $url = 'https://migrationmanager.example.com/sign/99/abc123';
        $message = '<p>Please sign: <span style="text-decoration:underline;">Sign Service Agreement</span></p>';

        $result = ComposeEmailPayload::applySigningLinkToMessage($message, $url);

        $this->assertStringContainsString('href="'.$url.'"', $result);
        $this->assertStringNotContainsString('<span style="text-decoration:underline;">Sign Service Agreement</span>', $result);
    }

    public function test_apply_signing_link_skips_when_sign_href_already_present(): void
    {
        $url = 'https://migrationmanager.example.com/sign/2/token456';
        $message = '<a href="'.$url.'">Sign Service Agreement</a>';

        $this->assertSame($message, ComposeEmailPayload::applySigningLinkToMessage($message, $url));
    }

    public function test_decode_signing_url_decodes_base64_payload(): void
    {
        $url = 'https://migrationmanager.example.com/sign/42/token_abc';
        $encoded = base64_encode($url);

        $this->assertSame(
            $url,
            ComposeEmailPayload::decodeSigningUrl([
                'signing_url' => $encoded,
                'signing_url_encoding' => 'base64',
            ])
        );
    }

    public function test_decode_signing_url_accepts_plain_value_without_encoding_flag(): void
    {
        $url = 'https://migrationmanager.example.com/sign/7/plainToken';

        $this->assertSame(
            $url,
            ComposeEmailPayload::decodeSigningUrl(['signing_url' => $url])
        );
    }

    public function test_apply_signing_link_repairs_anchor_with_invalid_href(): void
    {
        $url = 'https://migrationmanager.example.com/sign/5/realToken';
        $message = '<p>Agreement: <a href="<a href=&quot;'.$url.'&quot;>broken">Sign Service Agreement</a></p>';

        $result = ComposeEmailPayload::applySigningLinkToMessage($message, $url);

        $this->assertStringContainsString('href="'.$url.'"', $result);
        $this->assertStringContainsString('Sign Service Agreement</a>', $result);
    }

    public function test_message_has_valid_sign_href_ignores_broken_sign_like_href(): void
    {
        $url = 'https://migrationmanager.example.com/sign/3/goodToken';
        $broken = '<a href="<a href=&quot;'.$url.'&quot;>x">Sign Service Agreement</a>';
        $valid = '<a href="'.$url.'">Sign Service Agreement</a>';

        $this->assertFalse(ComposeEmailPayload::messageHasValidSignHref($broken));
        $this->assertTrue(ComposeEmailPayload::messageHasValidSignHref($valid));
    }

    public function test_apply_discount_row_inserts_before_total_when_discount_given(): void
    {
        $message = '<table><tr><td>Other Costs (estimated):</td><td>$0.00</td></tr>'
            .'<tr><td>Total Applicable Costs:</td><td>${GrandTotalFeesAndCosts}</td></tr></table>';

        $result = ComposeEmailPayload::applyDiscountRowToMessage($message, 100.00);

        $this->assertStringContainsString('js-cost-discount-email-row', $result);
        $this->assertStringContainsString('Discount', $result);
        $this->assertStringNotContainsString('Discount (-)', $result);
        $this->assertStringContainsString('<strong>4.</strong>', $result);
        $this->assertStringContainsString('<strong>-$100.00</strong>', $result);
        $this->assertTrue(
            strpos($result, 'Other Costs (estimated):') < strpos($result, 'js-cost-discount-email-row'),
            'Discount row should sit directly under Other Costs'
        );
        $this->assertTrue(
            strpos($result, 'js-cost-discount-email-row') < strpos($result, 'Total Applicable Costs:'),
            'Discount row should appear before the total row'
        );
    }

    public function test_apply_discount_row_uses_numbered_amount_column_for_three_column_table(): void
    {
        $message = '<table>'
            .'<tr><td>1.</td><td>Our professional fees for assisting you in this matter will be (including GST)</td><td style="text-align:right;">$540.00</td></tr>'
            .'<tr><td>2.</td><td>Department fee, including the card Surcharge</td><td style="text-align:right;">$1674.00</td></tr>'
            .'<tr><td>3.</td><td>Other Costs (estimated)</td><td style="text-align:right;">$0.00</td></tr>'
            .'<tr><td colspan="3"><hr></td></tr>'
            .'<tr><td></td><td>Total Applicable Costs</td><td style="text-align:right;">${GrandTotalFeesAndCosts}</td></tr>'
            .'</table>';

        $result = ComposeEmailPayload::applyDiscountRowToMessage($message, 214.00);

        $this->assertStringContainsString('<strong>4.</strong>', $result);
        $this->assertStringContainsString('<td>Discount</td>', $result);
        $this->assertStringNotContainsString('Discount (-)', $result);
        $this->assertStringContainsString('<strong>-$214.00</strong>', $result);
        $this->assertTrue(
            strpos($result, 'Other Costs (estimated)') < strpos($result, '<strong>4.</strong>'),
            'Discount should sit directly under Other Costs'
        );
        $this->assertTrue(
            strpos($result, '<strong>4.</strong>') < strpos($result, '<hr>'),
            'Discount should appear above the separator line'
        );
        $this->assertTrue(
            strpos($result, '<hr>') < strpos($result, 'Total Applicable Costs'),
            'Separator should remain above the total'
        );
        $this->assertStringContainsString('3.</td><td>Other Costs (estimated)', $result);
    }

    public function test_apply_discount_row_skips_when_no_discount(): void
    {
        $message = '<table><tr><td>Total Applicable Costs:</td><td>$2214.00</td></tr></table>';

        $this->assertSame($message, ComposeEmailPayload::applyDiscountRowToMessage($message, 0));
        $this->assertSame($message, ComposeEmailPayload::applyDiscountRowToMessage($message, -5));
    }

    public function test_apply_discount_row_skips_when_costs_are_not_in_a_table(): void
    {
        $message = '<p>Total Applicable Costs: $2214.00</p>';

        $this->assertSame($message, ComposeEmailPayload::applyDiscountRowToMessage($message, 214.00));
    }

    public function test_apply_discount_row_does_not_insert_inside_nested_table_cell(): void
    {
        $message = '<table>'
            .'<tr><td>3.</td><td>Other Costs <table><tr><td>$1.00</td></tr></table></td><td>$0.00</td></tr>'
            .'<tr><td></td><td>Total Applicable Costs</td><td>$100.00</td></tr>'
            .'</table>';

        $result = ComposeEmailPayload::applyDiscountRowToMessage($message, 10.00);

        $this->assertStringContainsString('js-cost-discount-email-row', $result);
        $discountPos = strpos($result, 'js-cost-discount-email-row');
        $nestedStart = strpos($result, '<table><tr><td>$1.00');
        $nestedEnd = strpos($result, '</table>', (int) $nestedStart);

        $this->assertNotFalse($discountPos);
        $this->assertNotFalse($nestedStart);
        $this->assertNotFalse($nestedEnd);
        $this->assertFalse(
            $discountPos > $nestedStart && $discountPos < $nestedEnd,
            'Discount row must not be spliced into a nested table inside a cost cell'
        );
        $this->assertTrue($discountPos > strpos($result, 'Other Costs'));
        $this->assertTrue($discountPos < strpos($result, 'Total Applicable Costs'));
    }
}
