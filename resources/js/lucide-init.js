import { createElement, createIcons, icons } from 'lucide';
import '../css/icons.css';

const FA_UTILITIES = new Set([
    'fa-spin', 'fa-pulse', 'fa-fw', 'fa-lg', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x',
    'fa-xs', 'fa-sm', 'fa-ul', 'fa-li', 'fa-border', 'fa-pull-left', 'fa-pull-right',
    'fa-stack', 'fa-stack-1x', 'fa-stack-2x', 'fa-inverse',
]);

const FA_STYLE_PREFIXES = new Set(['fa', 'fas', 'far', 'fab', 'fal', 'fad']);

/** Match lucide's kebab-case → PascalCase lookup (createIcons uses the same). */
function toCamelCase(string) {
    return string.replace(
        /^([A-Z])|[\s-_]+(\w)/g,
        (match, p1, p2) => (p2 ? p2.toUpperCase() : p1.toLowerCase()),
    );
}

function toPascalCase(string) {
    const camelCase = toCamelCase(string);
    return camelCase.charAt(0).toUpperCase() + camelCase.slice(1);
}

function resolveIconNode(name) {
    if (!name) {
        return null;
    }
    if (icons[name]) {
        return icons[name];
    }
    const pascal = toPascalCase(name);
    return icons[pascal] ?? null;
}

function getConfig() {
    return window.crmIconsConfig ?? {
        defaults: { size: 16, stroke_width: 2, class: 'lucide icon' },
        legacy: {},
        spinners: { 'fa-spinner': 'loader-2' },
        brands: { 'fa-google': 'google' },
    };
}

function parseLegacyClassString(classString) {
    const tokens = String(classString).trim().split(/\s+/).filter(Boolean);
    let spin = tokens.includes('fa-spin');
    let glyph = null;

    for (const token of tokens) {
        if (FA_STYLE_PREFIXES.has(token) || FA_UTILITIES.has(token)) {
            continue;
        }
        if (token.startsWith('fa-')) {
            glyph = token;
        }
    }

    const config = getConfig();

    if (glyph && config.brands?.[glyph]) {
        return { brand: config.brands[glyph], lucide: null, spin };
    }

    if (glyph === 'fa-spinner' || tokens.includes('fa-spinner')) {
        spin = true;
        return {
            brand: null,
            lucide: config.spinners?.['fa-spinner'] ?? 'loader-2',
            spin,
        };
    }

    const lucide = glyph
        ? (config.legacy?.[glyph] ?? 'circle-question-mark')
        : 'circle-question-mark';

    return { brand: null, lucide, spin };
}

function buildSvgAttrs(options = {}) {
    const config = getConfig();
    const defaults = config.defaults ?? {};
    const size = options.size ?? defaults.size ?? 16;
    const strokeWidth = options.strokeWidth ?? options.stroke_width ?? defaults.stroke_width ?? 2;

    let className = [defaults.class ?? 'lucide icon', options.className ?? options.class ?? '']
        .filter(Boolean)
        .join(' ');

    if (options.spin) {
        className += ' icon-spin';
    }

    const attrs = {
        width: size,
        height: size,
        'stroke-width': strokeWidth,
        class: className.trim(),
        'aria-hidden': 'true',
    };

    if (options.style) {
        attrs.style = options.style;
    }
    if (options.title) {
        attrs.title = options.title;
    }

    return attrs;
}

function crmIconBrand(name) {
    const el = document.getElementById('crm-icon-brand-' + name);
    if (el) {
        return el.innerHTML.trim();
    }
    return '';
}

/**
 * Render a Lucide icon as an SVG HTML string.
 *
 * @param {string} name - Lucide kebab-case name
 * @param {object} [options]
 * @returns {string}
 */
function crmIcon(name, options = {}) {
    const iconNode = resolveIconNode(name);
    if (!iconNode) {
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[crmIcon] Unknown icon:', name);
        }
        return '';
    }

    const svg = createElement(iconNode, buildSvgAttrs(options));
    return svg.outerHTML;
}

/**
 * Render from a Font Awesome class string during migration.
 *
 * @param {string} classString - e.g. "fas fa-spinner fa-spin"
 * @param {object} [options]
 * @returns {string}
 */
function crmIconLegacy(classString, options = {}) {
    const parsed = parseLegacyClassString(classString);

    if (parsed.brand) {
        return crmIconBrand(parsed.brand);
    }

    return crmIcon(parsed.lucide, { ...options, spin: parsed.spin || options.spin });
}

/**
 * Replace [data-lucide] placeholders with SVG (Blade @icon output).
 *
 * @param {Element|Document|DocumentFragment} [root]
 */
function refreshLucideIcons(root) {
    const config = getConfig();
    const defaults = config.defaults ?? {};

    createIcons({
        icons,
        root: root ?? document,
        attrs: {
            class: defaults.class ?? 'lucide icon',
            'stroke-width': defaults.stroke_width ?? 2,
        },
    });
}

function initLucideIcons() {
    refreshLucideIcons();
}

window.crmIcon = crmIcon;
window.crmIconLegacy = crmIconLegacy;
/** Shorthand for crmIconLegacy — use in template literals / dynamic HTML. */
window.crmI = function crmI(legacyClass, options) {
    if (typeof crmIconLegacy === 'function') {
        return crmIconLegacy(legacyClass, options || {});
    }
    return '<i class="' + legacyClass + '"></i>';
};
window.refreshLucideIcons = refreshLucideIcons;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLucideIcons);
} else {
    initLucideIcons();
}

export { crmIcon, crmIconLegacy, refreshLucideIcons };
