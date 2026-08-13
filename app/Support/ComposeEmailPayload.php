<?php

namespace App\Support;

/**
 * Normalise compose-email POST payloads (WAF-safe transport + subject placeholders).
 */
final class ComposeEmailPayload
{
    /**
     * Decode message body when the front-end sent it base64-encoded to avoid WAF/mod_security blocks
     * on HTML, URLs, and special characters in multipart POST bodies.
     */
    public static function decodeMessage(array $requestData): string
    {
        $message = (string) ($requestData['message'] ?? '');

        if (($requestData['message_encoding'] ?? '') !== 'base64') {
            return $message;
        }

        $decoded = base64_decode($message, true);

        return $decoded !== false ? $decoded : $message;
    }

    /**
     * Restore subject after front-end WAF-safe placeholder encoding.
     */
    public static function decodeSubject(array $requestData): string
    {
        $subject = (string) ($requestData['subject'] ?? '');

        return str_replace('__AMP__', '&', $subject);
    }

    /**
     * Decode signing_url when the front-end sent it base64-encoded (WAF-safe, same as message body).
     * Plain signing_url without encoding flag is still accepted for backward compatibility.
     */
    public static function decodeSigningUrl(array $requestData): ?string
    {
        $raw = trim((string) ($requestData['signing_url'] ?? ''));
        if ($raw === '') {
            return null;
        }

        if (($requestData['signing_url_encoding'] ?? '') === 'base64') {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false) {
                $raw = trim($decoded);
            }
        }

        return $raw !== '' ? $raw : null;
    }

    /**
     * Validate a document signing URL from compose email (must be http(s) with /sign/{id}/{token}).
     */
    public static function normalizeSigningUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        if (! preg_match('#/sign/\d+/[A-Za-z0-9_-]+#', $url)) {
            return null;
        }

        return $url;
    }

    /**
     * Insert a numbered Discount row before the costs total when a discount was applied.
     * Matches the existing costs table columns (number, description, amount). No discount leaves the table unchanged.
     */
    public static function applyDiscountRowToMessage(string $message, float $discount): string
    {
        if ($discount <= 0 || str_contains($message, 'js-cost-discount-email-row')) {
            return $message;
        }

        try {
            $totalRowStart = self::findCostsTotalRowStart($message);
            if ($totalRowStart === null) {
                return $message;
            }

            $formatted = number_format($discount, 2, '.', '');
            $source = self::findPreviousCostLineRow($message, $totalRowStart);
            $row = $source !== null
                ? self::buildDiscountRowFromSourceRow($source['html'], $formatted)
                : self::defaultDiscountRowHtml($formatted);
            $insertAt = $source['end'] ?? $totalRowStart;
            if (! self::isInsideOpenTable($message, $insertAt)) {
                return $message;
            }

            return substr($message, 0, $insertAt).$row.substr($message, $insertAt);
        } catch (\Throwable) {
            return $message;
        }
    }

    private static function findCostsTotalRowStart(string $message): ?int
    {
        $needles = ['GrandTotalFeesAndCosts', 'Total Applicable Costs', 'Total Applicable'];
        $pos = false;
        foreach ($needles as $needle) {
            $found = strripos($message, $needle);
            if ($found !== false) {
                $pos = $found;
                break;
            }
        }
        if ($pos === false) {
            return null;
        }

        return self::findLastTableRowOpen($message, $pos);
    }

    /**
     * @return array{html: string, end: int}|null
     */
    private static function findPreviousCostLineRow(string $message, int $totalRowStart): ?array
    {
        $searchEnd = $totalRowStart;
        for ($i = 0; $i < 6; $i++) {
            $trStart = self::findLastTableRowOpen($message, $searchEnd);
            if ($trStart === null) {
                return null;
            }

            $end = self::findMatchingTableRowEnd($message, $trStart);
            if ($end === null || $end > $totalRowStart || self::tableNestingDepth($message, $trStart) !== self::tableNestingDepth($message, $totalRowStart)) {
                $searchEnd = $trStart;

                continue;
            }

            $row = substr($message, $trStart, $end - $trStart);
            if (self::rowLooksLikeCostLine($row)) {
                return ['html' => $row, 'end' => $end];
            }

            $searchEnd = $trStart;
        }

        return null;
    }

    private static function findLastTableRowOpen(string $html, int $beforePos): ?int
    {
        $prefix = strtolower(substr($html, 0, max(0, $beforePos)));
        $pos = null;
        $offset = 0;
        while (($found = self::indexOfTableRowOpen($prefix, $offset)) !== null) {
            $pos = $found;
            $offset = $found + 3;
        }

        return $pos;
    }

    private static function findMatchingTableRowEnd(string $html, int $trStart): ?int
    {
        $lower = strtolower($html);
        $length = strlen($lower);
        $offset = $trStart;
        $depth = 0;

        while ($offset < $length) {
            $nextOpen = self::indexOfTableRowOpen($lower, $offset);
            $nextClose = strpos($lower, '</tr>', $offset);
            if ($nextClose === false) {
                return null;
            }

            if ($nextOpen !== null && $nextOpen <= $nextClose) {
                $depth++;
                $offset = $nextOpen + 3;

                continue;
            }

            $depth--;
            $end = $nextClose + 5;
            if ($depth <= 0) {
                return $end;
            }
            $offset = $end;
        }

        return null;
    }

    private static function indexOfTableRowOpen(string $lowerHtml, int $offset): ?int
    {
        $pos = $offset;
        while (($found = strpos($lowerHtml, '<tr', $pos)) !== false) {
            $next = $lowerHtml[$found + 3] ?? '';
            if ($next === '' || $next === '>' || $next === '/' || ctype_space($next)) {
                return $found;
            }
            $pos = $found + 3;
        }

        return null;
    }

    private static function isInsideOpenTable(string $html, int $insertAt): bool
    {
        return self::tableNestingDepth($html, $insertAt) > 0;
    }

    private static function tableNestingDepth(string $html, int $pos): int
    {
        $prefix = substr($html, 0, max(0, $pos));
        $opens = preg_match_all('/<table\b/i', $prefix);
        $closes = preg_match_all('/<\/table/i', $prefix);

        return max(0, $opens - $closes);
    }

    private static function rowLooksLikeCostLine(string $row): bool
    {
        if (stripos($row, 'Total Applicable') !== false || str_contains($row, 'GrandTotalFeesAndCosts')) {
            return false;
        }

        return (bool) preg_match('/\$\s*[\d,]+\.\d{2}|TotalEstimatedOthCosts|Other Costs|>\s*\d+\.\s*</i', $row);
    }

    private static function buildDiscountRowFromSourceRow(string $sourceRow, string $formattedAmount): string
    {
        $safeAmount = '-$'.htmlspecialchars($formattedAmount, ENT_QUOTES, 'UTF-8');
        if (! preg_match_all('/<td\b([^>]*)>(.*?)<\/td>/is', $sourceRow, $cells, PREG_SET_ORDER)) {
            return self::defaultDiscountRowHtml($formattedAmount);
        }

        $openTr = '<tr class="js-cost-discount-email-row">';
        if (preg_match('/<tr\b([^>]*)>/i', $sourceRow, $trMatch)) {
            $attrs = $trMatch[1];
            if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attrs)) {
                $attrs = preg_replace('/\bclass\s*=\s*(["\'])(.*?)\1/i', 'class=$1$2 js-cost-discount-email-row$1', $attrs, 1) ?? $attrs;
            } else {
                $attrs .= ' class="js-cost-discount-email-row"';
            }
            $openTr = '<tr'.$attrs.'>';
        }

        $count = count($cells);
        if ($count >= 3) {
            return $openTr
                .'<td'.$cells[0][1].'>'.self::wrapCostCellLikeSource($cells[0][2], '4.').'</td>'
                .'<td'.$cells[1][1].'>Discount</td>'
                .'<td'.$cells[$count - 1][1].'>'.self::wrapCostCellLikeSource($cells[$count - 1][2], $safeAmount).'</td>'
                .'</tr>';
        }

        if ($count === 2) {
            return $openTr
                .'<td'.$cells[0][1].'>'.self::wrapCostCellLikeSource($cells[0][2], '4.').' Discount</td>'
                .'<td'.$cells[1][1].'>'.self::wrapCostCellLikeSource($cells[1][2], $safeAmount).'</td>'
                .'</tr>';
        }

        return self::defaultDiscountRowHtml($formattedAmount);
    }

    private static function wrapCostCellLikeSource(string $sourceInner, string $replacement): string
    {
        if (preg_match('/<(strong|b)\b[^>]*>/i', $sourceInner, $match)) {
            $tag = strtolower($match[1]);

            return '<'.$tag.'>'.$replacement.'</'.$tag.'>';
        }

        return '<strong>'.$replacement.'</strong>';
    }

    private static function defaultDiscountRowHtml(string $formattedAmount): string
    {
        $safeAmount = '-$'.htmlspecialchars($formattedAmount, ENT_QUOTES, 'UTF-8');

        return '<tr class="js-cost-discount-email-row"><td><strong>4.</strong></td><td>Discount</td><td style="text-align:right;"><strong>'.$safeAmount.'</strong></td></tr>';
    }

    /**
     * HTML anchor for the service agreement signing link in checklist / first-email templates.
     */
    public static function buildServiceAgreementSignLinkHtml(string $url): string
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<a href="'.$safeUrl.'" target="_blank" rel="noopener noreferrer" style="color:#2563eb;text-decoration:underline;">Sign Service Agreement</a>';
    }

    /**
     * Ensure the message contains a clickable sign link when a signing URL was provided.
     * Replaces {PDF_url_for_sign} and repairs common TinyMCE underline-only label markup.
     */
    public static function applySigningLinkToMessage(string $message, ?string $signingUrl): string
    {
        $url = self::normalizeSigningUrl($signingUrl);
        if ($url === null) {
            return $message;
        }

        $link = self::buildServiceAgreementSignLinkHtml($url);
        $message = str_replace('{PDF_url_for_sign}', $link, $message);

        if (self::messageHasValidSignHref($message)) {
            return $message;
        }

        // Repair <a> around the label when href is missing or invalid (TinyMCE / template double-wrap).
        $message = preg_replace_callback(
            '#<a\s+(?:(?>[^>"\']+)|"[^"]*"|\'[^\']*\')*>\s*Sign Service Agreement\s*</a>#i',
            function (array $m) use ($link): string {
                if (preg_match('#\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $m[0], $hrefMatch)) {
                    $candidate = html_entity_decode($hrefMatch[2] ?? $hrefMatch[3] ?? '', ENT_QUOTES, 'UTF-8');
                    if (self::normalizeSigningUrl($candidate) !== null) {
                        return $m[0];
                    }
                }

                return $link;
            },
            $message,
            1
        );

        if (self::messageHasValidSignHref($message)) {
            return $message;
        }

        $patterns = [
            '#<span[^>]*>\s*Sign Service Agreement\s*</span>#i',
            '#<u>\s*Sign Service Agreement\s*</u>#i',
            '#<a(?![^>]*\bhref\s*=)[^>]*>\s*Sign Service Agreement\s*</a>#i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return preg_replace($pattern, $link, $message, 1);
            }
        }

        return $message;
    }

    /**
     * True when the message already contains a valid /sign/{id}/{token} href.
     */
    public static function messageHasValidSignHref(string $message): bool
    {
        if (! preg_match_all('#\bhref\s*=\s*["\']([^"\']*)["\']#i', $message, $matches)) {
            return false;
        }

        foreach ($matches[1] as $href) {
            if (self::normalizeSigningUrl(html_entity_decode($href, ENT_QUOTES, 'UTF-8')) !== null) {
                return true;
            }
        }

        return false;
    }
}
