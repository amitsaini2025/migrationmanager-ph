const fs = require('fs');
const p = 'resources/views/crm/clients/tabs/account.blade.php';
let c = fs.readFileSync(p, 'utf8');

// Insert + ' after crmI(...) when followed by text (not already concatenating)
c = c.replace(/crmI\('([^']+)'\)(?!\s*\+)(?!\s*;)(?!\s*\))/g, (match, cls, offset, str) => {
    const rest = str.slice(offset + match.length);
    if (/^\s*\+/.test(rest) || /^\s*[,;)\]]/.test(rest)) {
        return match;
    }
    if (/^\s*'/.test(rest)) {
        return match + ' + ';
    }
    if (/^\s+[A-Za-z@<]/.test(rest)) {
        return match + " + '";
    }
    return match;
});

// Remaining @icon in JS strings -> crmI
c = c.replace(/@icon\('fa-spinner', \['spin' => true(?:, 'class' => '[^']*')?\]\)/g, "crmI('fas fa-spinner fa-spin')");
c = c.replace(/@icon\('([^']+)'(?:, \[[^\]]*\])?\)/g, (_, name) => `crmI('fas ${name}')`);

// Fix doubled trailing quotes from earlier pass
c = c.replace(/\+ ' Finding matches\.\.\.''/g, "+ ' Finding matches...'");
c = c.replace(/\+ ' Allocating\.\.\.''/g, "+ ' Allocating...'");
c = c.replace(/\+ ' Uploading\.\.\.''/g, "+ ' Uploading...'");

// Fix broken: crmI('fas fa-check-circle')' +
c = c.replace(/crmI\('([^']+)'\)'\s*\+/g, "crmI('$1') + ");

fs.writeFileSync(p, c);
console.log('account.blade.php syntax repaired');
