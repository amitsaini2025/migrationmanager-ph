/**
 * Repair broken quote escaping in Stage 6 JS files.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

function repair(content) {
    let result = content;
    let changes = 0;

    const countReplace = (re, fn) => {
        const before = result;
        result = result.replace(re, fn);
        if (result !== before) changes++;
    };

    // Broken template literal: ${(typeof crmIconLegacy === \'function\' ? ...
    countReplace(
        /\$\{\(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('([^']*(?:\\.[^']*)*)'\\) : \\'<i class="([^"]*)"><\/i>\\'\)\}/g,
        (_, legacy, cls) => `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy.replace(/\\'/g, "'")}') : '<i class="${cls}"></i>'}`
    );

    // Broken .html('${...} suffix')
    countReplace(
        /\.html\('\$\{(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('([^']*)'\\) : \\'<i class="([^"]*)"><\/i>\\')\} ([^']*)'\)/g,
        (_, legacy, cls, suffix) =>
            `.html((typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${cls}"></i>') + ' ${suffix}')`
    );

    // Broken .html('<div...>${...} ...</div>')  — points summary style
    countReplace(
        /\.html\('<div class="([^"]*)">\$\{(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('([^']*)'\\) : \\'<i class="([^"]*)"><\/i>\\')\} ([^<]*)<\/div>'\)/g,
        (_, divClass, legacy, cls, text) =>
            `.html('<div class="${divClass}">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${cls}"></i>') + ' ${text}</div>')`
    );

    // Double fa-spin
    result = result.replace(/fa-spin fa-spin/g, () => { changes++; return 'fa-spin'; });

    // Broken dynamic icon in template
    countReplace(
        /\$\{\(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('\$\{iconClass\} points-warning-icon'\\) : \\'<i class="\$\{iconClass\} points-warning-icon"><\/i>\\'\)\}/g,
        () => `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas ' + iconClass + ' points-warning-icon') : '<i class="fas ' + iconClass + ' points-warning-icon"></i>'}`
    );

    countReplace(
        /\$\{\(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('fas \$\{icon\}'\\) : \\'<i class="fas \$\{icon\}"><\/i>\\'\)\}/g,
        () => `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas ' + icon) : '<i class="fas ' + icon + '"></i>'}`
    );

    // html = '<div...>${...}...'  (single-quoted with broken embed)
    countReplace(
        /html = '<div class="text-center text-muted py-3">\$\{(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('([^']*)'\\) : \\'<i class="([^"]*)"><\/i>\\')\} ([^<]*)<\/div>';/g,
        (_, legacy, cls, text) =>
            `html = '<div class="text-center text-muted py-3">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${cls}"></i>') + ' ${text}</div>';`
    );

    return { result, changes };
}

for (const rel of ['public/js/leads/lead-form.js', 'public/js/clients/eoi-roi.js']) {
    const fp = path.join(ROOT, rel);
    const { result, changes } = repair(fs.readFileSync(fp, 'utf8'));
    fs.writeFileSync(fp, result, 'utf8');
    console.log(`${rel}: repaired (${changes} passes)`);
}
