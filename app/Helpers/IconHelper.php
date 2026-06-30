<?php

namespace App\Helpers;

use Illuminate\Support\Facades\View;

class IconHelper
{
    /** Font Awesome utility classes — not glyph names */
    private const FA_UTILITIES = [
        'fa-spin',
        'fa-pulse',
        'fa-fw',
        'fa-lg',
        'fa-2x',
        'fa-3x',
        'fa-4x',
        'fa-5x',
        'fa-xs',
        'fa-sm',
        'fa-ul',
        'fa-li',
        'fa-border',
        'fa-pull-left',
        'fa-pull-right',
        'fa-stack',
        'fa-stack-1x',
        'fa-stack-2x',
        'fa-inverse',
    ];

    private const FA_STYLE_PREFIXES = ['fa', 'fas', 'far', 'fab', 'fal', 'fad'];

    /**
     * Render a Lucide icon placeholder (hydrated to SVG by lucide-init.js).
     *
     * @param  string  $name  Lucide kebab name (e.g. trash-2) or legacy fa-* / class string
     * @param  array<string, mixed>  $attributes
     */
    public static function render(string $name, array $attributes = []): string
    {
        if (str_contains($name, ' ') || preg_match('/\b(fas|far|fab|fal|fad)\b/', $name)) {
            return self::fromLegacy($name, $attributes);
        }

        $spin = ! empty($attributes['spin']);
        unset($attributes['spin']);

        $resolved = self::resolveName($name);

        if (str_starts_with($resolved, 'brand:')) {
            return self::brand(substr($resolved, 6));
        }

        if ($spin || self::isSpinnerName($name)) {
            $attributes['class'] = trim(($attributes['class'] ?? '') . ' icon-spin');
        }

        $defaults = config('icons.defaults', []);
        $classes = trim(($defaults['class'] ?? 'lucide icon') . ' ' . ($attributes['class'] ?? ''));
        unset($attributes['class']);

        $htmlAttributes = array_merge([
            'data-lucide' => $resolved,
            'class' => $classes,
            'aria-hidden' => 'true',
        ], $attributes);

        return '<i ' . self::buildHtmlAttributes($htmlAttributes) . '></i>';
    }

    /**
     * Map a Font Awesome class string to a Lucide icon and render it.
     */
    public static function fromLegacy(string $classString, array $attributes = []): string
    {
        $parsed = self::parseLegacyClassString($classString);

        if ($parsed['brand'] !== null) {
            return self::brand($parsed['brand']);
        }

        if ($parsed['spin']) {
            $attributes['spin'] = true;
        }

        return self::render($parsed['lucide'], $attributes);
    }

    /**
     * Render a brand icon (no Lucide equivalent).
     */
    public static function brand(string $name): string
    {
        if (! preg_match('/^[a-z0-9-]+$/', $name)) {
            return '<span class="icon icon-brand icon-brand--missing" aria-hidden="true"></span>';
        }

        $view = 'components.icons.brand-' . $name;

        if (! View::exists($view)) {
            return '<span class="icon icon-brand icon-brand--missing" aria-hidden="true"></span>';
        }

        return view($view)->render();
    }

    /**
     * Resolve any icon identifier to a Lucide name or brand: key.
     */
    public static function resolveName(string $name): string
    {
        $name = trim($name);

        if (str_starts_with($name, 'brand:')) {
            return $name;
        }

        if (str_contains($name, ' ') || preg_match('/\b(fas|far|fab|fal|fad)\b/', $name)) {
            $parsed = self::parseLegacyClassString($name);

            if ($parsed['brand'] !== null) {
                return 'brand:' . $parsed['brand'];
            }

            return $parsed['lucide'];
        }

        if (str_starts_with($name, 'fa-')) {
            $brands = config('icons.brands', []);
            if (isset($brands[$name])) {
                return 'brand:' . $brands[$name];
            }

            return config('icons.legacy.' . $name) ?? $name;
        }

        return $name;
    }

    /**
     * @return array{brand: ?string, lucide: string, spin: bool}
     */
    public static function parseLegacyClassString(string $classString): array
    {
        $tokens = preg_split('/\s+/', trim($classString)) ?: [];
        $spin = in_array('fa-spin', $tokens, true);
        $glyph = null;

        foreach ($tokens as $token) {
            if (in_array($token, self::FA_STYLE_PREFIXES, true)) {
                continue;
            }
            if (in_array($token, self::FA_UTILITIES, true)) {
                continue;
            }
            if (str_starts_with($token, 'fa-')) {
                $glyph = $token;
            }
        }

        if ($glyph === 'fa-spinner' || in_array('fa-spinner', $tokens, true)) {
            $spin = true;

            return [
                'brand' => null,
                'lucide' => config('icons.spinners.fa-spinner', 'loader-2'),
                'spin' => true,
            ];
        }

        if ($glyph !== null && isset(config('icons.brands', [])[$glyph])) {
            return [
                'brand' => config('icons.brands.' . $glyph),
                'lucide' => '',
                'spin' => $spin,
            ];
        }

        $lucide = $glyph
            ? (config('icons.legacy.' . $glyph) ?? 'circle-question-mark')
            : 'circle-question-mark';

        return ['brand' => null, 'lucide' => $lucide, 'spin' => $spin];
    }

    private static function isSpinnerName(string $name): bool
    {
        return str_contains($name, 'fa-spinner');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function buildHtmlAttributes(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $parts[] = htmlspecialchars((string) $key);
                continue;
            }
            $parts[] = htmlspecialchars((string) $key) . '="' . htmlspecialchars((string) $value) . '"';
        }

        return implode(' ', $parts);
    }

    /**
     * Render an icon from a stored value (Lucide kebab name, fa-* token, or legacy class string).
     */
    public static function renderStored(?string $icon, array $attributes = [], string $fallback = 'tag'): string
    {
        $icon = trim((string) ($icon ?: $fallback));

        return self::render($icon, $attributes);
    }
}
