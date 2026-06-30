/**
 * Fix Stage 5 migration: @icon() inside JS strings/PHP returns must use crmIconLegacy / IconHelper.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DRY_RUN = process.argv.includes('--dry-run');

const FILES = [
    'resources/views/crm/assignee/action.blade.php',
    'resources/views/crm/assignee/assign_by_me.blade.php',
    'resources/views/crm/assignee/assign_to_me.blade.php',
    'resources/views/crm/assignee/index.blade.php',
    'resources/views/crm/clients/clientsmatterslist.blade.php',
    'resources/views/crm/clients/closedmatterslist.blade.php',
    'resources/views/crm/clients/clientreceiptlist.blade.php',
    'resources/views/crm/clients/officereceiptlist.blade.php',
    'resources/views/crm/clients/journalreceiptlist.blade.php',
    'resources/views/crm/clients/analytics-dashboard.blade.php',
    'resources/views/crm/signatures/show.blade.php',
    'resources/views/crm/signatures/create.blade.php',
    'resources/views/crm/broadcasts/index.blade.php',
];

function crmI(legacy) {
    return `(typeof crmIconLegacy === 'function' ? crmIconLegacy('${legacy}') : '<i class="${legacy.replace('fa ', 'fa ')}"></i>')`;
}

function fixContent(content) {
    let result = content;
    let changes = 0;

    const replace = (re, fn) => {
        result = result.replace(re, (...args) => {
            changes++;
            return fn(...args);
        });
    };

    // PHP sort icon helpers
    replace(
        /return '@icon\('fa-sort', \['class' => 'text-muted'\]\)';/g,
        () => "return \\App\\Helpers\\IconHelper::fromLegacy('fas fa-sort', ['class' => 'text-muted']);"
    );
    replace(
        /return '@icon\('fa-sort-up'\)';/g,
        () => "return \\App\\Helpers\\IconHelper::fromLegacy('fas fa-sort-up');"
    );
    replace(
        /return '@icon\('fa-sort-down'\)';/g,
        () => "return \\App\\Helpers\\IconHelper::fromLegacy('fas fa-sort-down');"
    );
    replace(
        /if \(\$currentSort !== \$column\) return '@icon\('fa-sort', \['class' => 'text-muted'\]\)';/g,
        () => "if ($currentSort !== $column) return \\App\\Helpers\\IconHelper::fromLegacy('fas fa-sort', ['class' => 'text-muted']);"
    );
    replace(
        /\? '@icon\('fa-sort-up'\)'/g,
        () => "? \\App\\Helpers\\IconHelper::fromLegacy('fas fa-sort-up')"
    );
    replace(
        /: '@icon\('fa-sort-down'\)';/g,
        () => ": \\App\\Helpers\\IconHelper::fromLegacy('fas fa-sort-down');"
    );

    // jQuery .html() patterns
    const htmlPatterns = [
        ["@icon('fa-spinner', ['spin' => true]) Completing...", 'fa fa-spinner fa-spin', ' Completing...'],
        ["@icon('fa-check') Complete Task", 'fa fa-check', ' Complete Task'],
        ["@icon('fa-check') Yes", 'fa fa-check', ' Yes'],
        ["@icon('fa-spinner', ['spin' => true]) Saving...", 'fa fa-spinner fa-spin', ' Saving...'],
        ["@icon('fa-spinner', ['spin' => true]) Reopening...", 'fa fa-spinner fa-spin', ' Reopening...'],
        ["@icon('fa-redo') Reopen", 'fa fa-redo', ' Reopen'],
    ];

    for (const [broken, legacy, suffix] of htmlPatterns) {
        const needle = `.html('${broken}')`;
        const replacement = `.html(${crmI(legacy)} + '${suffix}')`;
        if (result.includes(needle)) {
            result = result.split(needle).join(replacement);
            changes++;
        }
    }

    // innerHTML assignments
    const innerPatterns = [
        ["@icon('fa-spinner', ['spin' => true]) Searching...", 'fa fa-spinner fa-spin', ' Searching...'],
        ["@icon('fa-spinner', ['spin' => true]) Adding Signer...", 'fa fa-spinner fa-spin', ' Adding Signer...'],
    ];
    for (const [broken, legacy, suffix] of innerPatterns) {
        const needle = `innerHTML = '${broken}';`;
        const replacement = `innerHTML = ${crmI(legacy)} + '${suffix}';`;
        if (result.includes(needle)) {
            result = result.split(needle).join(replacement);
            changes++;
        }
    }

    // Popover title
    replace(
        /title: '@icon\('fa-plus-circle'\) Add New Task',/g,
        () => `title: ${crmI('fa fa-plus-circle')} + ' Add New Task',`
    );

    // Popover label strings in action.blade.php
    const labelIcons = [
        ['fa-user', ' Select Assignee'],
        ['fa-tag', ' Task Group'],
        ['fa-comment', ' Task Description'],
        ['fa-calendar', ' Follow-up date'],
    ];
    for (const [icon, label] of labelIcons) {
        const needle = `'<label class="control-label">@icon('${icon}')${label}</label>' +`;
        const replacement = `'\'<label class="control-label">\' + ${crmI('fa ' + icon)} + '${label}</label>\' +`.replace(/\\'/g, "'");
        // simpler approach:
        const simpleNeedle = `<label class="control-label">@icon('${icon}')${label}</label>`;
        const simpleRepl = `<label class="control-label">' + ${crmI('fa ' + icon)} + '${label}</label>`;
        if (result.includes(simpleNeedle)) {
            result = result.split(simpleNeedle).join(simpleRepl);
            changes++;
        }
    }

    if (result.includes("'@icon('fa-save') Update Task'")) {
        result = result.replace(
            "'@icon('fa-save') Update Task'",
            `'\' + ${crmI('fa fa-save')} + ' Update Task'`
        );
        changes++;
    }

    // Analytics dashboard chart error strings
    const chartIcons = [
        ['fa-info-circle', ' No trend data available for the selected period'],
        ['fa-exclamation-triangle', ' Error loading chart'],
        ['fa-info-circle', ' No payment method data for this period'],
        ['fa-info-circle', ' No allocation data available'],
    ];
    for (const [icon, text] of chartIcons) {
        const needle = `@icon('${icon}')${text}`;
        if (result.includes(needle)) {
            result = result.split(needle).join(`' + ${crmI('fa ' + icon)} + '${text}`);
            changes++;
        }
    }

    // Broadcasts template literal status counts
    replace(
        /activeStaffCount\.innerHTML = `@icon\('fa-circle', \['class' => 'status-dot-online'\]\)(<span class="count-text">[^<]+<\/span> online)`;/g,
        (m, rest) => `activeStaffCount.innerHTML = ${crmI('fas fa-circle status-dot-online')}${rest};`
    );

    // Broadcasts office info in template
    if (result.includes("@icon('fa-building', ['class' => 'mr-1'])")) {
        result = result.replace(
            "@icon('fa-building', ['class' => 'mr-1'])",
            "' + (typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-building mr-1') : '<i class=\"fas fa-building mr-1\"></i>') + '"
        );
        changes++;
    }

    // Signatures template literals
    replace(
        /@icon\('fa-check-circle'\) Selected matter:/g,
        () => `' + ${crmI('fas fa-check-circle')} + ' Selected matter:`
    );
    replace(
        /@icon\('fa-spinner', \['spin' => true, 'class' => 'fa-2x'\]\)/g,
        () => `' + ${crmI('fas fa-spinner fa-spin fa-2x')} + '`
    );
    replace(
        /@icon\('fa-exclamation-triangle', \['class' => 'fa-2x'\]\)/g,
        () => `' + ${crmI('fas fa-exclamation-triangle fa-2x')} + '`
    );

    // Broadcasts history template @icon in backtick strings (read_at line)
    replace(
        /@icon\('fa-check', \['class' => 'mr-1'\]\)\$\{formatDate\(item\.read_at\)\}/g,
        () => `\${typeof crmIconLegacy === 'function' ? crmIconLegacy('fas fa-check mr-1') : '<i class="fas fa-check mr-1"></i>'}\${formatDate(item.read_at)}`
    );

    // Timeline dynamic icon
    replace(
        /<i class="\{\{ \$activity\['icon'\] \}\}"><\/i>/g,
        () => `{!! \\App\\Helpers\\IconHelper::fromLegacy($activity['icon']) !!}`
    );

    // Invoicelist aging icon
    replace(
        /<i class="fas <\?php echo \$agingIcon; \?>"><\/i>/g,
        () => `<?php echo \\App\\Helpers\\IconHelper::fromLegacy('fas ' . $agingIcon); ?>`
    );

    // Analytics dynamic arrow
    replace(
        /<i class="fas fa-arrow-\{\{ \$dashboardStats\['monthly_stats'\]\['trends'\]\['deposits'\]\['direction'\] == 'up' \? 'up' : 'down' \}\}"><\/i>/g,
        () => `{!! \\App\\Helpers\\IconHelper::fromLegacy('fas fa-arrow-' . ($dashboardStats['monthly_stats']['trends']['deposits']['direction'] == 'up' ? 'up' : 'down')) !!}`
    );
    replace(
        /<i class="fas fa-arrow-\{\{ \$dashboardStats\['monthly_stats'\]\['trends'\]\['office_receipts'\]\['direction'\] == 'up' \? 'up' : 'down' \}\}"><\/i>/g,
        () => `{!! \\App\\Helpers\\IconHelper::fromLegacy('fas fa-arrow-' . ($dashboardStats['monthly_stats']['trends']['office_receipts']['direction'] == 'up' ? 'up' : 'down')) !!}`
    );

    // CSS: sort caret selectors in list files
    const cssReplacements = [
        ['.listing-container .fas.fa-check-circle', '.listing-container [data-lucide="circle-check"]'],
        ['.fas.fa-check-circle', '[data-lucide="circle-check"]'],
        ['.listing-container .sortable-header.sort-asc .sort-icon .fa-caret-up', '.listing-container .sortable-header.sort-asc .sort-icon [data-lucide="chevron-up"]'],
        ['.listing-container .sortable-header.sort-desc .sort-icon .fa-caret-down', '.listing-container .sortable-header.sort-desc .sort-icon [data-lucide="chevron-down"]'],
        ['.sortable-header.sort-asc .sort-icon .fa-caret-up', '.sortable-header.sort-asc .sort-icon [data-lucide="chevron-up"]'],
        ['.sortable-header.sort-desc .sort-icon .fa-caret-down', '.sortable-header.sort-desc .sort-icon [data-lucide="chevron-down"]'],
    ];
    for (const [from, to] of cssReplacements) {
        if (result.includes(from)) {
            result = result.split(from).join(to);
            changes++;
        }
    }

    return { result, changes };
}

let total = 0;
for (const rel of FILES) {
    const filePath = path.join(ROOT, rel);
    if (!fs.existsSync(filePath)) continue;
    const content = fs.readFileSync(filePath, 'utf8');
    const { result, changes } = fixContent(content);
    if (changes > 0) {
        console.log(`${rel}: ${changes} fix(es)`);
        total += changes;
        if (!DRY_RUN) fs.writeFileSync(filePath, result, 'utf8');
    }
}

console.log(DRY_RUN ? `[dry-run] Would apply ${total} fix(es)` : `Done — ${total} fix(es) applied`);
