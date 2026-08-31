<?php

namespace App\Support;

use App\Helpers\IconHelper;

final class ClientDetailVerificationUi
{
    /**
     * @param  array<string, mixed>|null  $status
     */
    public static function fieldGroupClass(?array $status): string
    {
        if (($status['status'] ?? null) === ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED) {
            return 'has-change-request';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $status
     */
    public static function icon(?array $status, bool $alreadyVerified = false, bool $showUnverifiedCircle = false): string
    {
        if (($status['status'] ?? null) === ClientDetailVerificationFields::STATUS_CHANGE_REQUESTED) {
            $payload = (string) json_encode([
                'field_id' => $status['id'] ?? null,
                'key' => $status['field_key'] ?? '',
                'label' => ClientDetailVerificationFields::label((string) ($status['field_key'] ?? '')),
                'original' => $status['original_value'] ?? '',
                'requested' => $status['requested_value'] ?? '',
            ]);

            return self::wrap(
                IconHelper::fromLegacy('fas fa-exclamation-circle', [
                    'class' => 'change-request-icon fa-lg',
                    'style' => 'color: #a15c00; cursor: pointer;',
                    'aria-hidden' => 'true',
                ]),
                'Request Change',
                [
                    'data-change-request' => '1',
                    'data-change-payload' => $payload,
                    'style' => 'cursor: pointer; display: inline-flex; vertical-align: middle;',
                ]
            );
        }

        if (
            in_array($status['status'] ?? null, [
                ClientDetailVerificationFields::STATUS_CONFIRMED,
                ClientDetailVerificationFields::STATUS_ACCEPTED,
            ], true)
            || $alreadyVerified
        ) {
            return self::wrap(
                IconHelper::fromLegacy('fas fa-check-circle', [
                    'class' => 'verified-icon fa-lg',
                    'style' => 'color: #28a745;',
                    'aria-hidden' => 'true',
                ]),
                'Confirmed'
            );
        }

        if ($showUnverifiedCircle) {
            return self::wrap(
                IconHelper::fromLegacy('far fa-circle', [
                    'class' => 'unverified-icon fa-lg',
                    'style' => 'color: #6c757d;',
                    'aria-hidden' => 'true',
                ]),
                'Not verified'
            );
        }

        return '';
    }

    /**
     * Native title lives on a wrapper so Lucide SVG hydration cannot drop the hover text.
     *
     * @param  array<string, string>  $attributes
     */
    private static function wrap(string $iconHtml, string $title, array $attributes = []): string
    {
        $attributes['title'] = $title;
        $attributes['class'] = trim('verify-status-icon '.($attributes['class'] ?? ''));

        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8').'="'
                .htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8').'"';
        }

        return '<span '.implode(' ', $parts).'>'.$iconHtml.'</span>';
    }
}
