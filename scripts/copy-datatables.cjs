/**
 * DEPRECATED — Phase 2f: CRM uses Vite vendor-libs.js + vendor-pdfmake.js. Kept for emergency rollback only.
 * Run manually: npm run copy:datatables
 *
 * Syncs DataTables core + Bootstrap 5 + Buttons + HTML5 exports from node_modules into public/.
 * Main: datatables.min.js (JSZip + buttons.html5 for Excel/HTML5).
 * pdfHtml5: datatables-pdfmake.min.js (pdfmake + vfs_fonts load after main).
 * Re-run after changing datatables.* / jszip / pdfmake versions: npm run copy:datatables
 */
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const nm = path.join(root, 'node_modules');
const pub = path.join(root, 'public');

/** Versions recorded in main bundle banner (pdfmake is only in datatables-pdfmake.min.js) */
const mainBannerPackages = [
    'datatables.net',
    'datatables.net-bs5',
    'datatables.net-buttons',
    'datatables.net-buttons-bs5',
    'jszip',
];

const jsPieces = [
    ['datatables.net', 'js', 'jquery.dataTables.min.js'],
    ['datatables.net-bs5', 'js', 'dataTables.bootstrap5.min.js'],
    ['datatables.net-buttons', 'js', 'dataTables.buttons.min.js'],
    ['datatables.net-buttons-bs5', 'js', 'buttons.bootstrap5.min.js'],
];

/** After Buttons core — enables excelHtml5 / copyHtml5 / csvHtml5 via window.JSZip; see DataTables buttons.html5 docs */
const buttonsHtml5Pieces = [
    ['jszip', 'dist', 'jszip.min.js'],
    ['datatables.net-buttons', 'js', 'buttons.html5.min.js'],
];

/** Large (~1MB+). Load immediately after datatables.min.js on pages that use pdfHtml5 (CRM layouts include both). */
const pdfMakePieces = [
    ['pdfmake', 'build', 'pdfmake.min.js'],
    ['pdfmake', 'build', 'vfs_fonts.js'],
];

function readPkgVersion(pkgName) {
    const p = path.join(nm, pkgName, 'package.json');
    if (!fs.existsSync(p)) {
        return '?';
    }
    try {
        return JSON.parse(fs.readFileSync(p, 'utf8')).version || '?';
    } catch {
        return '?';
    }
}

const versionsMain = mainBannerPackages
    .map((name) => `${name}@${readPkgVersion(name)}`)
    .join(', ');
const pdfmakeVersion = readPkgVersion('pdfmake');

const bannerMain = `/*!
 * DataTables vendor bundle (built by scripts/copy-datatables.cjs)
 * ${versionsMain}
 * Includes: core, BS5, Buttons (buttons.exportData), BS5 buttons, JSZip + buttons.html5 (Excel/HTML5 clipboard exports).
 * PDF: load datatables-pdfmake.min.js after this file for pdfHtml5.
 */`;

const bannerPdf = `/*!
 * pdfmake + vfs_fonts for DataTables pdfHtml5 (built by scripts/copy-datatables.cjs)
 * pdfmake@${pdfmakeVersion}
 * Loaded after datatables.min.js; registers vfs on window.pdfMake for pdfHtml5.
 */`;

function readBodies(pieces) {
    const bodies = [];
    for (const parts of pieces) {
        const file = path.join(nm, ...parts);
        if (!fs.existsSync(file)) {
            console.error('Missing file:', file);
            process.exit(1);
        }
        bodies.push(fs.readFileSync(file, 'utf8'));
    }
    return bodies;
}

// Semicolons between minified IIFEs avoid rare ASI edge cases; banner is not concatenated with ";\n".
function emitBundle(banner, pieceGroups, outRelJs) {
    const bodies = pieceGroups.flatMap((g) => readBodies(g));
    const out = `${banner}\n${bodies.join('\n;\n')}\n`;
    const outJs = path.join(pub, ...outRelJs.split('/'));
    fs.writeFileSync(outJs, out, 'utf8');
    console.log('Wrote', path.relative(root, outJs));
}

emitBundle(bannerMain, [jsPieces, buttonsHtml5Pieces], 'js/datatables.min.js');
emitBundle(bannerPdf, [pdfMakePieces], 'js/datatables-pdfmake.min.js');

const cssCopies = [
    [
        path.join(nm, 'datatables.net-bs5', 'css', 'dataTables.bootstrap5.min.css'),
        path.join(pub, 'css', 'dataTables.bootstrap5.min.css'),
    ],
    [
        path.join(nm, 'datatables.net-buttons-bs5', 'css', 'buttons.bootstrap5.min.css'),
        path.join(pub, 'css', 'buttons.bootstrap5.min.css'),
    ],
];

for (const [from, to] of cssCopies) {
    if (!fs.existsSync(from)) {
        console.error('Missing file:', from);
        process.exit(1);
    }
    fs.copyFileSync(from, to);
    console.log('Copied', path.relative(root, from), '->', path.relative(root, to));
}
