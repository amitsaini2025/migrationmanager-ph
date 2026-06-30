/**
 * Pass 2 — migrate remaining FA icons in JS (template literals, .html(), jQuery).
 * Usage: node scripts/migrate-stage4-icons-js.cjs
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

const JS_FILES = [
    'public/js/crm/clients/detail-main.js',
    'public/js/clients/edit-client.js',
    'public/js/custom-form-validation.js',
    'public/js/crm/clients/modules/send-to-client.js',
    'public/js/crm/clients/modules/documents.js',
    'public/js/crm/clients/modules/checklist.js',
    'public/js/crm/clients/workflow-tab.js',
    'public/js/crm/clients/modules/accounts.js',
    'public/js/crm/clients/modules/ledger-dragdrop.js',
];

const FA_STYLE = new Set(['fa', 'fas', 'far', 'fab', 'fal', 'fad']);
const FA_UTILITIES = new Set([
    'fa-spin', 'fa-pulse', 'fa-fw', 'fa-lg', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x',
    'fa-xs', 'fa-sm',
]);

function parseFaClassString(classStr) {
    if (!classStr || /\$\{|\+\s*\w+/.test(classStr)) {
        return null;
    }
    const tokens = classStr.trim().split(/\s+/).filter(Boolean);
    let stylePrefix = 'fas';
    let glyph = null;
    let spin = false;
    const extraClasses = [];
    for (const token of tokens) {
        if (FA_STYLE.has(token)) { stylePrefix = token; continue; }
        if (token === 'fa-spin') { spin = true; continue; }
        if (token.startsWith('fa-')) {
            if (FA_UTILITIES.has(token)) extraClasses.push(token);
            else if (!glyph) glyph = token;
            else extraClasses.push(token);
            continue;
        }
        extraClasses.push(token);
    }
    if (!glyph) return null;
    return { stylePrefix, glyph, spin, extraClasses };
}

function legacyClass(parsed) {
    const p = [parsed.stylePrefix, parsed.glyph];
    if (parsed.spin) p.push('fa-spin');
    return p.join(' ');
}

function crmICall(parsed) {
    const legacy = legacyClass(parsed);
    const opts = {};
    if (parsed.extraClasses.length) opts.class = parsed.extraClasses.join(' ');
    if (Object.keys(opts).length) {
        const optStr = Object.entries(opts).map(([k, v]) => `${k}: '${v.replace(/'/g, "\\'")}'`).join(', ');
        return `crmI('${legacy}', { ${optStr} })`;
    }
    return `crmI('${legacy}')`;
}

function migrateJs(content) {
    let result = content;
    let changes = 0;

    // Skip already converted crmI/crmIconLegacy wrapped strings
    const iconRe = /<i\s+class="([^"]+)"([^>]*?)>\s*<\/i>/gi;
    result = result.replace(iconRe, (match, classStr, extraAttrs) => {
        if (match.includes('crmI(') || match.includes('crmIconLegacy')) return match;
        const parsed = parseFaClassString(classStr);
        if (!parsed) return match;
        changes++;
        return '${' + crmICall(parsed) + '}';
    });

    // Fix broken note icon fallback from pass 1
    result = result.replace(
        /crmIconLegacy\('fas ' \+ noteIcon\) : '<i class="fas ' \+ noteIcon \+ ''><\/i>'/g,
        "crmIconLegacy('fas ' + noteIcon) : '<i class=\"fas ' + noteIcon + '\"></i>'"
    );

    // .html('<i...') patterns outside template literals — crmI concat
    result = result.replace(
        /\.html\('(<i class="([^"]+)"[^>]*>\s*<\/i>)([^']*)'\)/g,
        (match, iconTag, classStr, suffix) => {
            const parsed = parseFaClassString(classStr);
            if (!parsed) return match;
            changes++;
            return `.html(crmI('${legacyClass(parsed)}') + '${suffix.replace(/'/g, "\\'")}')`;
        }
    );

    // jQuery $('<i>', { class: 'fas fa-file-image' })
    result = result.replace(
        /\$\('<i>',\s*\{\s*class:\s*'([^']+)'\s*\}\)/g,
        (match, classStr) => {
            const parsed = parseFaClassString(classStr);
            if (!parsed) return match;
            changes++;
            return `$(${crmICall(parsed)})`;
        }
    );

    // jQuery button with icon inside string
    result = result.replace(
        /\$\('<button([^>]*)><i class="([^"]+)"[^>]*><\/i><\/button>'\)/g,
        (match, btnAttrs, classStr) => {
            const parsed = parseFaClassString(classStr);
            if (!parsed) return match;
            changes++;
            return `$('<button${btnAttrs}>' + ${crmICall(parsed)} + '</button>')`;
        }
    );

    return { result, changes };
}

let total = 0;
for (const rel of JS_FILES) {
    const fp = path.join(ROOT, rel);
    if (!fs.existsSync(fp)) continue;
    const content = fs.readFileSync(fp, 'utf8');
    const { result, changes } = migrateJs(content);
    if (changes > 0) {
        fs.writeFileSync(fp, result, 'utf8');
        console.log(`${rel}: ${changes}`);
        total += changes;
    }
}
console.log(`Done — ${total} JS icon(s) migrated`);
