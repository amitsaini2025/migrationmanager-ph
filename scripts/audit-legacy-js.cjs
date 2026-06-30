/**
 * List all .js files under public/js and whether they are referenced in app sources.
 *
 * Scans resources/, app/, routes/, config/, public/js (cross-refs), and scripts/
 * for path-like references: asset('js/...'), /js/..., @legacy/...
 *
 * Usage:
 *   node scripts/audit-legacy-js.cjs
 *   node scripts/audit-legacy-js.cjs --json
 *   node scripts/audit-legacy-js.cjs --verbose
 */
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PUBLIC_JS = path.join(ROOT, 'public', 'js');

const SCAN_DIRS = [
    path.join(ROOT, 'resources'),
    path.join(ROOT, 'app'),
    path.join(ROOT, 'routes'),
    path.join(ROOT, 'config'),
    path.join(ROOT, 'public', 'js'),
    path.join(ROOT, 'scripts'),
];

const SKIP_DIRS = new Set([
    'node_modules',
    'vendor',
    'storage',
    'bootstrap/cache',
    'public/build',
]);

const SCAN_EXTENSIONS = new Set(['.blade.php', '.php', '.js', '.vue', '.ts', '.tsx', '.jsx', '.cjs', '.mjs']);

/** Vendor bundles built by copy scripts — always treated as referenced until Phase 2f */
const VENDOR_ALWAYS_REFERENCED = new Set([
    'tom-select.complete.min.js',
    'datatables.min.js',
    'datatables-pdfmake.min.js',
    'flatpickr.min.js',
    'inputmask.min.js',
    'iziToast.min.js',
    'app.min.js',
]);

function walkJsFiles(dir, base = PUBLIC_JS, files = []) {
    if (!fs.existsSync(dir)) {
        return files;
    }
    for (const name of fs.readdirSync(dir)) {
        const full = path.join(dir, name);
        const relFromJs = path.relative(base, full).replace(/\\/g, '/');
        if (relFromJs.startsWith('tinymce/')) {
            continue;
        }
        const stat = fs.statSync(full);
        if (stat.isDirectory()) {
            walkJsFiles(full, base, files);
        } else if (name.endsWith('.js')) {
            files.push(relFromJs);
        }
    }
    return files.sort();
}

function walkSourceFiles(dir, files = []) {
    const stat = fs.statSync(dir, { throwIfNoEntry: false });
    if (!stat) {
        return files;
    }
    if (stat.isFile()) {
        const ext = path.extname(dir);
        if (SCAN_EXTENSIONS.has(ext)) {
            files.push(dir);
        }
        return files;
    }
    for (const name of fs.readdirSync(dir)) {
        if (SKIP_DIRS.has(name)) {
            continue;
        }
        walkSourceFiles(path.join(dir, name), files);
    }
    return files;
}

function collectSourceText() {
    const chunks = [];
    for (const dir of SCAN_DIRS) {
        for (const file of walkSourceFiles(dir)) {
            chunks.push(fs.readFileSync(file, 'utf8'));
        }
    }
    return chunks.join('\n');
}

function escapeRegExp(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function findReferences(relPath, sourceText) {
    const hits = [];
    const patterns = [
        new RegExp(`(?:asset|URL::asset)\\s*\\(\\s*['"]js/${escapeRegExp(relPath)}`, 'g'),
        new RegExp(`['"]js/${escapeRegExp(relPath)}['"]`, 'g'),
        new RegExp(`/@legacy/${escapeRegExp(relPath)}/g`),
        new RegExp(`/js/${escapeRegExp(relPath)}(?:\\?|\\s|"|'|\\)|>)`, 'g'),
    ];
    for (const re of patterns) {
        if (re.test(sourceText)) {
            hits.push(re.source.slice(0, 60));
        }
    }
    return hits.length > 0;
}

function main() {
    const json = process.argv.includes('--json');
    const verbose = process.argv.includes('--verbose');

    const jsFiles = walkJsFiles(PUBLIC_JS);
    const sourceText = collectSourceText();

    const referenced = [];
    const unreferenced = [];
    const vendorPinned = [];

    for (const rel of jsFiles) {
        const base = path.basename(rel);
        if (VENDOR_ALWAYS_REFERENCED.has(base) || VENDOR_ALWAYS_REFERENCED.has(rel)) {
            vendorPinned.push(rel);
            referenced.push({ rel, kind: 'vendor-pinned' });
            continue;
        }
        if (findReferences(rel, sourceText)) {
            referenced.push({ rel, kind: 'found' });
        } else {
            unreferenced.push(rel);
        }
    }

    if (json) {
        console.log(
            JSON.stringify(
                {
                    total: jsFiles.length,
                    referenced: referenced.map((r) => r.rel),
                    vendorPinned,
                    unreferenced,
                },
                null,
                2
            )
        );
        return;
    }

    console.log('Legacy JS audit — public/js/**/*.js (excludes tinymce/)\n');
    console.log(`Total files: ${jsFiles.length}`);
    console.log(`Referenced:  ${referenced.length} (${vendorPinned.length} vendor-pinned)`);
    console.log(`Unreferenced candidates: ${unreferenced.length}\n`);

    if (unreferenced.length) {
        console.log('--- Unreferenced (review before delete) ---');
        for (const rel of unreferenced) {
            console.log(`  ${rel}`);
        }
        console.log('');
    } else {
        console.log('No unreferenced files detected.\n');
    }

    if (verbose && referenced.length) {
        console.log('--- Referenced ---');
        for (const { rel, kind } of referenced) {
            console.log(`  [${kind}] ${rel}`);
        }
    }

    console.log('Note: dynamic loads and external docs are not scanned. See docs/PUBLIC-JS-LEGACY.md');
}

main();
