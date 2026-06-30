/**
 * Stage 7 — migrate FA <i> tags in PHP controllers to IconHelper::fromLegacy().
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const TARGETS = [
    'app/Http/Controllers/CRM/Clients/ClientDocumentsController.php',
    'app/Http/Controllers/CRM/ClientPortalController.php',
    'app/Http/Controllers/CRM/ClientAccountsController.php',
    'app/Http/Controllers/CRM/AssigneeController.php',
    'app/Http/Controllers/CRM/Clients/ClientNotesController.php',
    'app/Http/Controllers/CRM/OfficeVisitController.php',
    'app/Http/Controllers/CRM/CRMUtilityController.php',
    'app/Http/Controllers/CRM/ClientsController.php',
    'app/Http/Controllers/CRM/BookingAppointmentsController.php',
];

const ICON_RE = /<i\s+class="((?:fa[srb]?\s+)?fa-[^"]+)"(?:\s+[^>]*)?\s*><\/i>/gi;

function ensureUseIconHelper(content) {
    if (content.includes('IconHelper::')) {
        if (!content.includes('use App\\Helpers\\IconHelper')) {
            content = content.replace(
                /(namespace App\\Http\\Controllers[^;]+;)\n/,
                '$1\n\nuse App\\Helpers\\IconHelper;\n'
            );
        }
    }
    return content;
}

function replaceIcons(content) {
    let count = 0;
    const out = content.replace(ICON_RE, (match, classStr) => {
        count++;
        return "<?php echo \\App\\Helpers\\IconHelper::fromLegacy('" + classStr.replace(/'/g, "\\'") + "'); ?>";
    });
    return { content: fixIconsEmbeddedInStrings(ensureUseIconHelper(out)), count };
}

/** PHP tags inside quoted strings are invalid — use IconHelper::fromLegacy() concatenation. */
function fixIconsEmbeddedInStrings(content) {
    return content.replace(
        /'([^']*)<\?php echo \\App\\Helpers\\IconHelper::fromLegacy\('([^']+)'\); \?>([^']*)'/g,
        (_, before, iconClass, after) =>
            `'${before}' . IconHelper::fromLegacy('${iconClass}') . '${after}'`
    );
}

let total = 0;
for (const rel of TARGETS) {
    const fp = path.join(ROOT, rel);
    if (!fs.existsSync(fp)) {
        console.log('SKIP (missing):', rel);
        continue;
    }
    const original = fs.readFileSync(fp, 'utf8');
    const { content, count } = replaceIcons(original);
    if (count > 0) {
        fs.writeFileSync(fp, content);
        console.log(count, rel);
        total += count;
    }
}
console.log('Total icons migrated:', total);
