/**
 * Stage 7 — migrate remaining JS FA icon strings to crmIconAny / crmIconLegacy.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const TARGETS = [
    'public/js/emails.js',
    'public/js/dashboard-optimized.js',
    'public/js/address-autocomplete.js',
    'public/js/clients/english-proficiency.js',
    'public/js/scripts.js',
    'public/js/crm/clients/sidebar-tabs.js',
    'public/js/crm/clients/modules/ledger-dragdrop.js',
    'public/js/crm/clients/modules/accounts.js',
    'public/js/smart-email-import.js',
];

const ICON_TAG_RE = /<i\s+class="([^"]*)\b(fas|far|fab|fa)\s+(fa-[^"]*?)([^"]*)"([^>]*)><\/i>/gi;

function toCrmIconAny(classStr, extraAttrs) {
    const attrs = extraAttrs.trim();
    let opts = '';
    if (attrs) {
        const cls = attrs.match(/class="([^"]*)"/);
        if (cls) {
            opts = ", { class: '" + cls[1].replace(/'/g, "\\'") + "' }";
        }
    }
    return "${typeof crmIconAny === 'function' ? crmIconAny('" + classStr.replace(/'/g, "\\'") + "'" + opts + ") : '<i class=\"" + classStr + "\"></i>'}";
}

function migrateFile(rel) {
    const fp = path.join(ROOT, rel);
    let c = fs.readFileSync(fp, 'utf8');
    let count = 0;

    // Template literal / HTML string <i class="fas fa-x">
    c = c.replace(ICON_TAG_RE, (match, prefix, style, glyph, suffix, extra) => {
        count++;
        const classStr = (prefix + style + ' ' + glyph + suffix).replace(/\s+/g, ' ').trim();
        return toCrmIconAny(classStr, extra);
    });

    // jQuery .html('<i class="fas fa-minus"></i>')
    c = c.replace(
        /\.html\('<i class="((?:fas|far|fab|fa)\s+[^"]+)"[^']*'\)/g,
        (match, classStr) => {
            count++;
            return ".html((typeof crmIconAny === 'function' ? crmIconAny('" + classStr + "') : '<i class=\"" + classStr + "\"></i>'))";
        }
    );

    // innerHTML = '<i class="...">'
    c = c.replace(
        /innerHTML\s*=\s*'<i class="((?:fas|far|fab|fa)\s+[^"]+)"[^']*'/g,
        (match, classStr) => {
            count++;
            return "innerHTML = (typeof crmIconAny === 'function' ? crmIconAny('" + classStr + "') : '<i class=\"" + classStr + "\"></i>') + '";
        }
    );

    if (count > 0) {
        fs.writeFileSync(fp, c);
    }
    return count;
}

let total = 0;
for (const rel of TARGETS) {
    if (!fs.existsSync(path.join(ROOT, rel))) {
        console.log('SKIP:', rel);
        continue;
    }
    const n = migrateFile(rel);
    if (n) console.log(n, rel);
    total += n;
}
console.log('Total JS replacements:', total);
