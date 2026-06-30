/**
 * Stage 4 — migrate Font Awesome <i> tags to @icon (Blade) or crmIconLegacy (JS).
 * Usage: node scripts/migrate-stage4-icons.cjs [--dry-run]
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
    'resources/views/crm/clients/detail.blade.php',
    'resources/views/crm/clients/tabs/client_portal.blade.php',
    'resources/views/crm/clients/tabs/account.blade.php',
    'resources/views/crm/clients/tabs/checklists.blade.php',
    'resources/views/crm/clients/tabs/visa_documents.blade.php',
    'resources/views/crm/clients/tabs/personal_documents.blade.php',
    'resources/views/crm/clients/edit.blade.php',
    'resources/views/crm/clients/company_edit.blade.php',
    'resources/views/crm/clients/modals/client-management.blade.php',
    'resources/views/crm/clients/modals/receipts.blade.php',
    'resources/views/crm/clients/modals/notes.blade.php',
    'resources/views/crm/clients/modals/appointment.blade.php',
    'resources/views/crm/clients/modals/applications.blade.php',
    'resources/views/crm/clients/modals/checklists.blade.php',
    'resources/views/crm/clients/modals/documents.blade.php',
    'resources/views/crm/clients/modals/edit-matter-office.blade.php',
    'resources/views/crm/clients/modals/emails.blade.php',
    'resources/views/crm/clients/modals/financial.blade.php',
    'resources/views/crm/clients/modals/forms.blade.php',
    'public/js/crm/clients/detail-main.js',
    'public/js/clients/edit-client.js',
    'public/js/custom-form-validation.js',
    'public/js/crm/clients/modules/send-to-client.js',
    'public/js/crm/clients/modules/documents.js',
    'public/js/crm/clients/modules/checklist.js',
    'public/js/crm/clients/workflow-tab.js',
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

function convertPhpIconString(match, quote, classStr) {
    const parsed = parseFaClassString(classStr);
    if (!parsed) {
        return match;
    }

    const legacyParts = [parsed.stylePrefix, parsed.glyph];
    if (parsed.spin) {
        legacyParts.push('fa-spin');
    }
    const legacyClass = legacyParts.join(' ');

    const opts = {};
    if (parsed.extraClasses.length) {
        opts.class = parsed.extraClasses.join(' ');
    }

    let optsStr = '';
    if (Object.keys(opts).length) {
        optsStr = `, ['class' => '${phpEscape(opts.class)}']`;
    }
    if (parsed.spin) {
        optsStr = `, ['spin' => true${opts.class ? `, 'class' => '${phpEscape(opts.class)}'` : ''}]`;
    }

    return `${quote}\\App\\Helpers\\IconHelper::fromLegacy('${legacyClass}'${optsStr})${quote}`;
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
    if (parsed.spin && !parsed.extraClasses.includes('fa-spin')) {
        // spin handled by legacy string
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

        const phpStrRe = /(['"])\\?<i class="([^"]+)"[^>]*>\s*<\/i>\1/g;
        result = result.replace(phpStrRe, (match, quote, classStr) => {
            const converted = convertPhpIconString(match, quote, classStr);
            if (converted !== match) {
                changes++;
            }
            return converted;
        });

        // Inline JS in blade: subjectIcon = '<i class="fas fa-sms"></i>';
        const jsInBladeRe = /(=\s*)(['"])\\?<i class="([^"]+)"[^>]*>\s*<\/i>\2/g;
        result = result.replace(jsInBladeRe, (match, prefix, quote, classStr) => {
            const converted = prefix + convertJsIconString(match.slice(prefix.length), quote, classStr);
            if (converted !== match) {
                changes++;
            }
            return converted;
        });
    }

    if (isJs) {
        // Skip if already migrated
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

        // Template literal icons
        const tplRe = /`<i class="([^"]+)"([^>]*)>\s*<\/i>`/g;
        result = result.replace(tplRe, (match, classStr) => {
            if (match.includes('crmIconLegacy')) {
                return match;
            }
            const parsed = parseFaClassString(classStr);
            if (!parsed) {
                return match;
            }
            const legacy = buildLegacyClassString(parsed);
            changes++;
            return `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${classStr}"></i>'}`;
        });

        // Dynamic: '<i class="fas fa-${icon}"></i>'
        const dynRe = /(['"])\\?<i class="(fas|far|fab|fa) fa-\$\{([^}]+)\}"[^>]*>\s*<\/i>\1/g;
        result = result.replace(dynRe, (match, quote, prefix, varName) => {
            changes++;
            return `(typeof crmIconLegacy === 'function' ? crmIconLegacy('${prefix} fa-' + ${varName}) : ${quote}<i class="${prefix} fa-\${${varName}}"></i>${quote})`;
        });

        // Dynamic note icon: '<i class="fas ' + noteIcon + '"></i>'
        const dynConcatRe = /(['"])\\?<i class="(fas|far|fab|fa) '\s*\+\s*(\w+)\s*\+\s*'"[^>]*>\s*<\/i>\1/g;
        result = result.replace(dynConcatRe, (match, quote, prefix, varName) => {
            changes++;
            return `(typeof crmIconLegacy === 'function' ? crmIconLegacy('${prefix} ' + ${varName}) : ${quote}<i class="${prefix} ${quote} + ${varName} + ${quote}${quote}></i>${quote})`;
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
