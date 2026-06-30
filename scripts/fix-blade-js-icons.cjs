const fs = require('fs');
const files = [
    'resources/views/crm/clients/tabs/checklists.blade.php',
    'resources/views/crm/clients/tabs/personal_documents.blade.php',
    'resources/views/crm/clients/tabs/visa_documents.blade.php',
];

function fix(content) {
    let c = content;
    c = c.replace(/@icon\('fa-spinner', \['spin' => true(?:, 'class' => '([^']*)')?\]\)/g, (_, cls) =>
        cls ? `crmI('fas fa-spinner fa-spin', { class: '${cls}' })` : "crmI('fas fa-spinner fa-spin')"
    );
    c = c.replace(/@icon\('([^']+)', \['class' => '([^']*)'\]\)/g, (_, name, cls) =>
        `crmI('fas ${name}', { class: '${cls}' })`
    );
    c = c.replace(/@icon\('([^']+)'\)/g, (_, name) => `crmI('fas ${name}')`);
    c = c.replace(/crmI\('([^']+)'\)(?!\s*\+)(?!\s*;)(?!\s*\))/g, (match, cls, offset, str) => {
        const rest = str.slice(offset + match.length);
        if (/^\s*\+/.test(rest) || /^\s*[,;)\]]/.test(rest)) return match;
        if (/^\s+'/.test(rest)) return match + ' + ';
        if (/^\s+[A-Za-z<(]/.test(rest)) return match + " + '";
        return match;
    });
    return c;
}

for (const rel of files) {
    const p = rel;
    let c = fs.readFileSync(p, 'utf8');
    const out = fix(c);
    if (out !== c) {
        fs.writeFileSync(p, out);
        console.log('Fixed', rel);
    }
}
