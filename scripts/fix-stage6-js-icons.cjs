/**
 * Fix Stage 6 migration: @icon() in JS/PHP strings, migrate remaining JS icon tags.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY_RUN = process.argv.includes('--dry-run');

const SCAN_DIRS = [
    'resources/views/crm/leads',
    'resources/views/crm/booking',
    'resources/views/crm/companies',
    'resources/views/crm/clients/sheets',
    'resources/views/AdminConsole',
];
const EXTRA = ['public/js/leads/lead-form.js', 'public/js/clients/eoi-roi.js'];

function walkDir(dir, files = []) {
    const abs = path.join(ROOT, dir);
    if (!fs.existsSync(abs)) return files;
    for (const e of fs.readdirSync(abs, { withFileTypes: true })) {
        const rel = path.join(dir, e.name).replace(/\\/g, '/');
        if (e.isDirectory()) walkDir(rel, files);
        else if (e.name.endsWith('.blade.php') || e.name.endsWith('.js')) files.push(rel);
    }
    return files;
}

function collectFiles() {
    const set = new Set(EXTRA);
    for (const d of SCAN_DIRS) walkDir(d).forEach((f) => set.add(f));
    return [...set];
}

function crmI(legacy) {
    return `(typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${legacy}"></i>')`;
}

function faLegacy(glyph, spin = false) {
    const base = glyph.startsWith('fa-') ? `fas ${glyph}` : glyph;
    return spin ? `${base} fa-spin` : base;
}

function migrateJsIcons(content) {
    let result = content;
    let changes = 0;

    const re = /<i\s+class="([^"]+)"([^>]*?)>\s*<\/i>/gis;
    result = result.replace(re, (match, classStr) => {
        if (match.includes('crmIconLegacy')) return match;
        changes++;
        const spin = classStr.includes('fa-spin');
        return '${' + crmI(faLegacy(classStr.trim(), spin)).replace(/'/g, "\\'") + '}';
    });

    // Template literal without ${} wrapper fix - above adds ${} for tpl content
    // For non-template JS strings:
    const strRe = /(['"])\\?<i class="([^"]+)"[^>]*>\s*<\/i>\1/g;
    result = result.replace(strRe, (match, quote, classStr) => {
        if (match.includes('crmIconLegacy')) return match;
        changes++;
        const spin = classStr.includes('fa-spin');
        return crmI(faLegacy(classStr.trim(), spin));
    });

    // Dynamic: '<i class="fas ' + noteIcon + '"></i>'
    result = result.replace(
        /(['"])\\?<i class="(fas|far|fab|fa) '\s*\+\s*(\w+)\s*\+\s*'"[^>]*>\s*<\/i>\1/g,
        (match, quote, prefix, varName) => {
            changes++;
            return `(typeof crmIconLegacy === 'function' ? crmIconLegacy('${prefix} ' + ${varName}) : ${quote}<i class="${prefix} ${quote} + ${varName} + ${quote}${quote}></i>${quote})`;
        }
    );

    // Dynamic template: `<i class="fas ${icon}"></i>`
    result = result.replace(
        /`<i class="(fas|far|fab|fa) \$\{([^}]+)\}"([^>]*)>\s*<\/i>`/g,
        (match, prefix, varName) => {
            if (match.includes('crmIconLegacy')) return match;
            changes++;
            return `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('${prefix} ' + ${varName}) : '<i class="${prefix} ' + ${varName} + '"></i>'}`;
        }
    );

    // .html('<i class="fas fa-spinner fa-spin"></i> Saving...')
    result = result.replace(
        /\.html\(\s*['"]\\?<i class="([^"]+)"[^>]*>\s*<\/i>([^'"]*)['"]\s*\)/g,
        (match, classStr, suffix) => {
            if (match.includes('crmIconLegacy')) return match;
            changes++;
            const spin = classStr.includes('fa-spin');
            return `.html(${crmI(faLegacy(classStr.trim(), spin))} + '${suffix}')`;
        }
    );

    return { result, changes };
}

function fixBrokenAtIcon(content) {
    let result = content;
    let changes = 0;

    const replaceAll = (needle, replacement) => {
        if (result.includes(needle)) {
            result = result.split(needle).join(replacement);
            changes++;
        }
    };

    // subjectIcon = '@icon('fa-xxx')';
    result = result.replace(
        /subjectIcon = '@icon\('([^']+)'\)';/g,
        (m, glyph) => {
            changes++;
            return `subjectIcon = ${crmI(faLegacy(glyph))};`;
        }
    );

    // .html('@icon('fa-spinner', ['spin' => true]) Text')
    result = result.replace(
        /\.html\('@icon\('([^']+)'(?:, \['spin' => true\])?\) ([^']*)'\)/g,
        (m, glyph, suffix) => {
            changes++;
            const spin = m.includes('spin');
            return `.html(${crmI(faLegacy(glyph, spin))} + ' ${suffix}')`;
        }
    );

    // .prop(...).html('@icon...')
    result = result.replace(
        /\.html\('@icon\('([^']+)'(?:, \[[^\]]*\])?\) ([^']*)'\)/g,
        (m, glyph, suffix) => {
            changes++;
            const spin = m.includes('spin');
            return `.html(${crmI(faLegacy(glyph, spin))} + ' ${suffix}')`;
        }
    );

    // innerHTML = '@icon...'
    result = result.replace(
        /(\w+\.innerHTML = )'@icon\('([^']+)'(?:, \['spin' => true\])?\) ([^']*)';/g,
        (m, prefix, glyph, suffix) => {
            changes++;
            const spin = m.includes('spin');
            return `${prefix}${crmI(faLegacy(glyph, spin))} + ' ${suffix}';`;
        }
    );

    // innerHTML = `... @icon('fa-edit', ...)`
    result = result.replace(
        /display\.innerHTML = `\$\{newDisplay\} @icon\('([^']+)'(?:, \[([^\]]*)\])?\)`;/g,
        (m, glyph) => {
            changes++;
            return `display.innerHTML = \`\${newDisplay} \${${crmI(faLegacy(glyph))}}\`;`;
        }
    );

    // toggleRedTagsBtn.innerHTML = '@icon...'
    result = result.replace(
        /\.innerHTML = '@icon\('([^']+)'\)';/g,
        (m, glyph) => {
            changes++;
            return `.innerHTML = ${crmI(faLegacy(glyph))};`;
        }
    );

    // this.innerHTML = '@icon('fa-eye')';
    result = result.replace(
        /this\.innerHTML = '@icon\('([^']+)'\)';/g,
        (m, glyph) => {
            changes++;
            return `this.innerHTML = ${crmI(faLegacy(glyph))};`;
        }
    );

    // PHP string with @icon - company_details
    result = result.replace(
        /(\$\w+Str \.= [^;]+)' @icon\('([^']+)', (\[[^\]]+\])\) <br\/>';/g,
        (m, prefix, glyph, attrs) => {
            changes++;
            // Parse simple attrs for IconHelper
            return `${prefix.trimEnd()}' . \\App\\Helpers\\IconHelper::fromLegacy('fas ${glyph}', ${attrs.replace(/'/g, "'")}) . ' <br/>';`;
        }
    );

    // Simpler PHP @icon in concat
    result = result.replace(
        /' @icon\('fa-circle', \['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified'\]\) <br\/>'/g,
        () => {
            changes++;
            return " ' . \\App\\Helpers\\IconHelper::fromLegacy('far fa-circle', ['class' => 'unverified-icon fa-lg', 'style' => 'color: #6c757d;', 'title' => 'Not verified']) . ' <br/>'";
        }
    );

    // Verified check-circle in PHP strings
    result = result.replace(
        /<i class="fas fa-check-circle verified-icon fa-lg" style="color: #28a745;" title="Verified on ' \. \([^)]+\) \. '"><\/i>/g,
        () => {
            changes++;
            return "' . \\App\\Helpers\\IconHelper::fromLegacy('fas fa-check-circle', ['class' => 'verified-icon fa-lg', 'style' => 'color: #28a745;', 'title' => 'Verified on ' . ($conVal->verified_at ? $conVal->verified_at->format('M j, Y g:i A') : 'Unknown')]) . '";
        }
    );

    // html += '@icon...' in nomination documents
    result = result.replace(
        /html \+= '@icon\('([^']+)'(?:, (\[[^\]]*\]))?\)';/g,
        (m, glyph, attrs) => {
            changes++;
            const opts = attrs || '[]';
            return `html += ${crmI(faLegacy(glyph))};`;
        }
    );

    result = result.replace(
        /\.html\('@icon\('([^']+)'(?:, (\[[^\]]*\]))?\) ([^']*)'\)/g,
        (m, glyph, _attrs, suffix) => {
            changes++;
            const spin = m.includes('spin');
            return `.html(${crmI(faLegacy(glyph, spin))} + ' ${suffix}')`;
        }
    );

    // .fail(...).html('@icon...')
    result = result.replace(
        /\.html\('@icon\('([^']+)'(?:, (\[[^\]]*\]))?\) ([^']*)'\)/g,
        (m, glyph, _attrs, suffix) => {
            changes++;
            return `.html(${crmI(faLegacy(glyph))} + ' ${suffix}')`;
        }
    );

    return { result, changes };
}

let total = 0;
for (const rel of collectFiles()) {
    const fp = path.join(ROOT, rel);
    if (!fs.existsSync(fp)) continue;
    let content = fs.readFileSync(fp, 'utf8');
    let changes = 0;

    if (rel.endsWith('.js')) {
        const js = migrateJsIcons(content);
        content = js.result;
        changes += js.changes;
    }

    const fix = fixBrokenAtIcon(content);
    content = fix.result;
    changes += fix.changes;

    if (changes > 0) {
        console.log(`${rel}: ${changes} fix(es)`);
        total += changes;
        if (!DRY_RUN) fs.writeFileSync(fp, content, 'utf8');
    }
}

// Manual: emaillabels dynamic icon
const emaillabelsIndex = path.join(ROOT, 'resources/views/AdminConsole/features/emaillabels/index.blade.php');
if (fs.existsSync(emaillabelsIndex)) {
    let c = fs.readFileSync(emaillabelsIndex, 'utf8');
    const old = '<i class="{{@$list->icon ?? \'fas fa-tag\'}}"></i>';
    const neu = '{!! \\App\\Helpers\\IconHelper::fromLegacy(@$list->icon ?? \'fas fa-tag\') !!}';
    if (c.includes(old)) {
        c = c.replace(old, neu);
        if (!DRY_RUN) fs.writeFileSync(emaillabelsIndex, c, 'utf8');
        console.log('emaillabels/index: dynamic icon fix');
        total++;
    }
}

// Pin star icons (multiline)
for (const rel of [
    'resources/views/crm/clients/sheets/eoi-roi.blade.php',
    'resources/views/crm/clients/sheets/art.blade.php',
    'resources/views/crm/clients/sheets/visa-type-sheet.blade.php',
]) {
    const fp = path.join(ROOT, rel);
    if (!fs.existsSync(fp)) continue;
    let c = fs.readFileSync(fp, 'utf8');
    const pinRe = /<i class="fas fa-star pin-star \{\{ \(\$row->is_pinned[^>]+\n[^>]*><\/i>/g;
    if (pinRe.test(c)) {
        c = c.replace(
            /<i class="fas fa-star pin-star \{\{ \(\$row->is_pinned \?\? false\) \? 'pinned' : '' \}\}"\s*\n\s*title="[^"]*"><\/i>/g,
            "@icon('fa-star', ['class' => 'pin-star ' . (($row->is_pinned ?? false) ? 'pinned' : ''), 'title' => 'Pin this row'])"
        );
        if (!DRY_RUN) fs.writeFileSync(fp, c, 'utf8');
        console.log(`${rel}: pin-star fix`);
        total++;
    }
}

console.log(DRY_RUN ? `[dry-run] Would apply ${total} fix(es)` : `Done — ${total} fix(es) applied`);
