/**
 * Stage 6 — Leads, booking, AdminConsole, companies, sheets.
 * Usage: node scripts/migrate-stage6-icons.cjs [--dry-run]
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

const SCAN_DIRS = [
    'resources/views/crm/leads',
    'resources/views/crm/booking',
    'resources/views/crm/companies',
    'resources/views/crm/clients/sheets',
    'resources/views/AdminConsole',
];

const EXTRA_FILES = [
    'public/js/leads/lead-form.js',
    'public/js/clients/eoi-roi.js',
];

function walkDir(dir, files = []) {
    const abs = path.join(ROOT, dir);
    if (!fs.existsSync(abs)) {
        return files;
    }
    for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
        const rel = path.join(dir, entry.name).replace(/\\/g, '/');
        if (entry.isDirectory()) {
            walkDir(rel, files);
        } else if (entry.name.endsWith('.blade.php') || entry.name.endsWith('.js')) {
            files.push(rel);
        }
    }
    return files;
}

function collectTargets() {
    const set = new Set(EXTRA_FILES);
    for (const dir of SCAN_DIRS) {
        for (const f of walkDir(dir)) {
            set.add(f);
        }
    }
    return [...set].sort();
}

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
    return buildBladeIcon(parsed, parseExtraAttrs(extraAttrs));
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
    const isJs = filePath.endsWith('.js');

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

    if (isJs) {
        const jsStrRe = /(['"])\\?<i class="([^"]+)"([^>]*)>\s*<\/i>\1/g;
        result = result.replace(jsStrRe, (match, quote, classStr) => {
            if (match.includes('crmIconLegacy')) {
                return match;
            }
            const converted = convertJsIconString(match, quote, classStr);
            if (converted !== match) {
                changes++;
            }
            return converted;
        });

        const tplRe = /`<i class="([^"]+)"([^>]*)>\s*<\/i>`/g;
        result = result.replace(tplRe, (match, classStr) => {
            if (match.includes('crmIconLegacy')) {
                return match;
            }
            const parsed = parseFaClassString(classStr);
            if (!parsed) {
                return match;
            }
            changes++;
            const legacy = buildLegacyClassString(parsed);
            return `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${classStr}"></i>'}`;
        });

        const dynRe = /(['"])\\?<i class="(fas|far|fab|fa) fa-\$\{([^}]+)\}"[^>]*>\s*<\/i>\1/g;
        result = result.replace(dynRe, (match, quote, prefix, varName) => {
            changes++;
            return `(typeof crmIconLegacy === 'function' ? crmIconLegacy('${prefix} fa-' + ${varName}) : ${quote}<i class="${prefix} fa-\${${varName}}"></i>${quote})`;
        });
    }

    return { result, changes };
}

const targets = collectTargets();
let totalChanges = 0;

for (const rel of targets) {
    const filePath = path.join(ROOT, rel);
    if (!fs.existsSync(filePath)) {
        console.warn('Skip (missing):', rel);
        continue;
    }

    const content = fs.readFileSync(filePath, 'utf8');
    if (!content.includes('<i class=')) {
        continue;
    }

    const { result, changes } = migrateContent(content, rel);

    if (changes > 0) {
        console.log(`${rel}: ${changes} replacements`);
        totalChanges += changes;
        if (!DRY_RUN) {
            fs.writeFileSync(filePath, result, 'utf8');
        }
    }
}

console.log(DRY_RUN ? `[dry-run] Would change ${totalChanges} icon(s) in ${targets.length} files scanned` : `Done — ${totalChanges} icon(s) migrated`);
