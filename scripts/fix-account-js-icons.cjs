const fs = require('fs');
const p = 'resources/views/crm/clients/tabs/account.blade.php';
let c = fs.readFileSync(p, 'utf8');

c = c.replace(/'@icon\('fa-spinner', \['spin' => true\]\)/g, "crmI('fas fa-spinner fa-spin')");
c = c.replace(
    / @icon\('fa-exclamation-triangle', \['class' => 'text-warning', 'title' => 'Receipt exceeds invoice amount - will create residual receipt'\]\)/g,
    " ' + crmI('fas fa-exclamation-triangle', { class: 'text-warning' }) + '"
);
c = c.replace(/'@icon\('([^']+)'(?:, \[[^\]]*\])?\)/g, (_, name) => `crmI('fas ${name}')`);
c = c.replace(/"@icon\('([^']+)'(?:, \[[^\]]*\])?\)/g, (_, name) => `crmI('fas ${name}')`);

// Fix broken concatenation where + ' was eaten
c = c.replace(/crmI\('fas fa-([^']+)'\) Copied!/g, "crmI('fas fa-$1') + ' Copied!");
c = c.replace(/crmI\('fas fa-([^']+)'\) Failed/g, "crmI('fas fa-$1') + ' Failed");
c = c.replace(/crmI\('fas fa-spinner fa-spin'\) Finding matches\.\.\./g, "crmI('fas fa-spinner fa-spin') + ' Finding matches...'");
c = c.replace(/crmI\('fas fa-spinner fa-spin'\) Allocating\.\.\./g, "crmI('fas fa-spinner fa-spin') + ' Allocating...'");
c = c.replace(/crmI\('fas fa-spinner fa-spin'\) Uploading\.\.\./g, "crmI('fas fa-spinner fa-spin') + ' Uploading...'");

fs.writeFileSync(p, c);
console.log('Done');
