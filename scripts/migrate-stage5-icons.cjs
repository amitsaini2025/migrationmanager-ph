/**
 * Stage 5 — Accounts, lists, and operations: migrate Font Awesome to @icon / crmIconLegacy.
 * Usage: node scripts/migrate-stage5-icons.cjs [--dry-run]
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY_RUN = process.argv.includes('--dry-run');

const FA_STYLE = new Set(['fa', 'fas', 'far', 'fab', 'fal', 'fad']);
const FA_UTILITIES = new Set([
    'fa-spin', 'fa-pulse', 'fa-fw', 'fa-lg', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x',
    'fa-xs', 'fa-sm', 'fa-ul', 'fa-li', 'fa-border', 'fa-pull-left', 'fa-pull-right',
    'fa-stack', 'fa-stack-1x', 'fa-stack-2x', 'fa-inverse',
]);

const TARGET_FILES = [
    'resources/views/crm/clients/invoicelist.blade.php',
    'resources/views/crm/clients/clientreceiptlist.blade.php',
    'resources/views/crm/clients/officereceiptlist.blade.php',
    'resources/views/crm/clients/journalreceiptlist.blade.php',
    'resources/views/crm/clients/clientsmatterslist.blade.php',
    'resources/views/crm/clients/closedmatterslist.blade.php',
    'resources/views/crm/clients/index.blade.php',
    'resources/views/crm/assignee/action.blade.php',
    'resources/views/crm/assignee/action_completed.blade.php',
    'resources/views/crm/assignee/assign_by_me.blade.php',
    'resources/views/crm/assignee/assign_to_me.blade.php',
    'resources/views/crm/assignee/completed.blade.php',
    'resources/views/crm/assignee/index.blade.php',
    'resources/views/crm/signatures/show.blade.php',
    'resources/views/crm/signatures/create.blade.php',
    'resources/views/crm/signatures/dashboard.blade.php',
    'resources/views/crm/broadcasts/index.blade.php',
    'resources/views/crm/clients/analytics-dashboard.blade.php',
];

function parseFaClassString(classStr) {
    if (!classStr || /\{\{|\$\{|<\?php|\?\>/.test(classStr)) {
        return null;
    }

    const tokens = classStr.trim().split(/\s+/).filter(Boolean);
    let stylePrefix = 'fas';
    let glyph = null;
    let spin = false;
    const extraClasses = [];

    for (const token of tokens) {
        if (FA_STYLE.has(token)) {
            stylePrefix = token;
            continue;
        }
        if (token === 'fa-spin') {
            spin = true;
            continue;
        }
        if (token.startsWith('fa-')) {
            if (FA_UTILITIES.has(token)) {
                extraClasses.push(token);
            } else if (!glyph) {
                glyph = token;
            } else {
                extraClasses.push(token);
            }
            continue;
        }
        extraClasses.push(token);
    }

    if (!glyph) {
        return null;
    }

    return { stylePrefix, glyph, spin, extraClasses };
}

function phpEscape(str) {
    return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function buildBladeIcon(parsed, extraAttrs = {}) {
    const attrs = {};
    if (parsed.spin) {
        attrs.spin = true;
    }
    if (parsed.extraClasses.length) {
        attrs.class = parsed.extraClasses.join(' ');
    }
    Object.assign(attrs, extraAttrs);

    const keys = Object.keys(attrs);
    if (keys.length === 0) {
        return `@icon('${parsed.glyph}')`;
    }

    const parts = keys.map((k) => {
        const v = attrs[k];
        if (v === true) {
            return `'${k}' => true`;
        }
        return `'${k}' => '${phpEscape(String(v))}'`;
    });

    return `@icon('${parsed.glyph}', [${parts.join(', ')}])`;
}

function parseExtraAttrs(attrStr) {
    const extra = {};
    if (!attrStr) {
        return extra;
    }

    const styleMatch = attrStr.match(/\bstyle="([^"]*)"/);
    if (styleMatch) {
        extra.style = styleMatch[1];
    }

    const titleMatch = attrStr.match(/\btitle="([^"]*)"/);
    if (titleMatch) {
        extra.title = titleMatch[1];
    }

    const ariaMatch = attrStr.match(/\baria-hidden="([^"]*)"/);
    if (ariaMatch) {
        extra['aria-hidden'] = ariaMatch[1];
    }

    return extra;
}

function convertBladeITag(match, classStr, extraAttrs) {
    const parsed = parseFaClassString(classStr);
    if (!parsed) {
        return match;
    }

    const attrs = parseExtraAttrs(extraAttrs);
    return buildBladeIcon(parsed, attrs);
}

function buildLegacyClassString(parsed) {
    const parts = [parsed.stylePrefix, parsed.glyph];
    if (parsed.spin) {
        parts.push('fa-spin');
    }
    return parts.join(' ');
}

function convertJsIconString(match, quote, classStr) {
    const parsed = parseFaClassString(classStr);
    if (!parsed) {
        return match;
    }

    const legacy = buildLegacyClassString(parsed);
    const opts = {};
    if (parsed.extraClasses.length) {
        opts.class = parsed.extraClasses.join(' ');
    }

    let call = `crmIconLegacy('${legacy}'`;
    const optKeys = Object.keys(opts);
    if (optKeys.length) {
        const optParts = optKeys.map((k) => `${k}: '${opts[k].replace(/'/g, "\\'")}'`);
        call += `, { ${optParts.join(', ')} }`;
    }
    call += ')';

    return `(typeof crmIconLegacy === 'function' ? ${call} : ${quote}<i class="${classStr}"></i>${quote})`;
}

function migrateContent(content, filePath) {
    let result = content;
    let changes = 0;
    const isBlade = filePath.endsWith('.blade.php');

    if (isBlade) {
        const bladeRe = /<i\s+class="([^"]+)"([^>]*?)>\s*<\/i>/gi;
        result = result.replace(bladeRe, (match, classStr, extraAttrs) => {
            const converted = convertBladeITag(match, classStr, extraAttrs);
            if (converted !== match) {
                changes++;
            }
            return converted;
        });

        const jsInBladeRe = /(=\s*)(['"])\\?<i class="([^"]+)"[^>]*>\s*<\/i>\2/g;
        result = result.replace(jsInBladeRe, (match, prefix, quote, classStr) => {
            const converted = prefix + convertJsIconString(match.slice(prefix.length), quote, classStr);
            if (converted !== match) {
                changes++;
            }
            return converted;
        });

        const htmlRe = /\.html\(\s*(['"])\\?<i class="([^"]+)"[^>]*>\s*<\/i>([^'"]*)\1\s*\)/g;
        result = result.replace(htmlRe, (match, quote, classStr, suffix) => {
            const parsed = parseFaClassString(classStr);
            if (!parsed) {
                return match;
            }
            changes++;
            const iconCall = convertJsIconString('', quote, classStr);
            return `.html(${iconCall} + ${quote}${suffix}${quote})`;
        });

        const tplRe = /`([^`]*?)<i class="([^"]+)"([^>]*)>\s*<\/i>([^`]*?)`/g;
        result = result.replace(tplRe, (match, before, classStr, _extra, after) => {
            if (match.includes('crmIconLegacy')) {
                return match;
            }
            const parsed = parseFaClassString(classStr);
            if (!parsed) {
                return match;
            }
            changes++;
            const legacy = buildLegacyClassString(parsed);
            return '`' + before + '${typeof crmIconLegacy === \'function\' ? crmIconLegacy(\'' + legacy + '\') : \'<i class="' + classStr + '"></i>\'}' + after + '`';
        });
    }

    return { result, changes };
}

let totalChanges = 0;

for (const rel of TARGET_FILES) {
    const filePath = path.join(ROOT, rel);
    if (!fs.existsSync(filePath)) {
        console.warn('Skip (missing):', rel);
        continue;
    }

    const content = fs.readFileSync(filePath, 'utf8');
    const { result, changes } = migrateContent(content, rel);

    if (changes > 0) {
        console.log(`${rel}: ${changes} replacements`);
        totalChanges += changes;
        if (!DRY_RUN) {
            fs.writeFileSync(filePath, result, 'utf8');
        }
    }
}

console.log(DRY_RUN ? `[dry-run] Would change ${totalChanges} icon(s)` : `Done — ${totalChanges} icon(s) migrated`);
