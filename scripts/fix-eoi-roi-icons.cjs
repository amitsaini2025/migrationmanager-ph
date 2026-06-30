const fs = require('fs');
const path = require('path');

const fp = path.join(__dirname, '../public/js/clients/eoi-roi.js');
let c = fs.readFileSync(fp, 'utf8');

// Broken pattern embedded in template literals or single-quoted strings
const brokenRe =
    /\$\{\(typeof crmIconLegacy === \\'function\\' \? crmIconLegacy\\('((?:[^'\\]|\\.)*)'\\) : \\'<i class="((?:[^"\\]|\\.)*)"><\/i>\\'\\)\}/g;

function fixedEmbed(glyph, cls) {
    return (
        "${typeof crmIconLegacy === 'function' ? crmIconLegacy('" +
        glyph +
        "') : '<i class=\"" +
        cls +
        "\"></i>'}"
    );
}

c = c.replace(brokenRe, (_, glyph, cls) => fixedEmbed(glyph, cls));

// .html('${...} suffix') — single-quoted string with literal ${}
c = c.replace(
    /\.html\('\$\{typeof crmIconLegacy === 'function' \? crmIconLegacy\('([^']*)'\) : '<i class="([^"]*)"><\/i>'\} ([^']*)'\)/g,
    (_, glyph, cls, suffix) =>
        ".html((typeof crmIconLegacy === 'function' ? crmIconLegacy('" +
        glyph +
        "') : '<i class=\"" +
        cls +
        "\"></i>') + ' " +
        suffix +
        "')"
);

// .html('<div...>${fixed} text</div>') after first pass
c = c.replace(
    /\.html\('<div class="([^"]*)">\$\{typeof crmIconLegacy === 'function' \? crmIconLegacy\('([^']*)'\) : '<i class="([^"]*)"><\/i>'\} ([^<]*)<\/div>'\)/g,
    (_, divClass, glyph, cls, text) =>
        ".html('<div class=\"" +
        divClass +
        "\">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('" +
        glyph +
        "') : '<i class=\"" +
        cls +
        "\"></i>') + ' " +
        text +
        "</div>')"
);

c = c.replace(
    /html = '<div class="([^"]*)">\$\{typeof crmIconLegacy === 'function' \? crmIconLegacy\('([^']*)'\) : '<i class="([^"]*)"><\/i>'\} ([^<]*)<\/div>';/g,
    (_, divClass, glyph, cls, text) =>
        "html = '<div class=\"" +
        divClass +
        "\">' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('" +
        glyph +
        "') : '<i class=\"" +
        cls +
        "\"></i>') + ' " +
        text +
        "</div>';"
);

// Dynamic iconClass (after first pass may still have broken form)
c = c.replace(
    /\$\{typeof crmIconLegacy === 'function' \? crmIconLegacy\('\$\{iconClass\} points-warning-icon'\) : '<i class="\$\{iconClass\} points-warning-icon"><\/i>'\}/g,
    "${typeof crmIconLegacy === 'function' ? crmIconLegacy(iconClass + ' points-warning-icon') : '<i class=\"' + iconClass + ' points-warning-icon\"></i>'}"
);

c = c.replace(
    /\$\{typeof crmIconLegacy === 'function' \? crmIconLegacy\('fas \$\{icon\}'\) : '<i class="fas \$\{icon\}"><\/i>'\}/g,
    "${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas ' + icon) : '<i class=\"fas ' + icon + '\"></i>'}"
);

fs.writeFileSync(fp, c);

const remaining = (c.match(/\\'function\\'/g) || []).length;
console.log('Remaining escaped patterns:', remaining);
if (remaining > 0) {
    const lines = c.split('\n');
    lines.forEach((line, i) => {
        if (line.includes("\\'function\\'")) {
            console.log('  line', i + 1, ':', line.trim().slice(0, 120));
        }
    });
}
