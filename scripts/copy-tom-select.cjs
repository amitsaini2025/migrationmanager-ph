/**
 * DEPRECATED — Phase 2f: CRM uses Vite vendor-libs.js. Kept for emergency rollback only.
 * Run manually: npm run copy:tom-select
 *
 * Copies Tom Select (complete bundle + Bootstrap 5 theme) from node_modules to public/.
 * Re-run after changing tom-select version: npm run copy:tom-select
 */
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const nm = path.join(root, 'node_modules', 'tom-select', 'dist');
const pubJs = path.join(root, 'public', 'js');
const pubCss = path.join(root, 'public', 'css');

const pairs = [
  [path.join(nm, 'js', 'tom-select.complete.min.js'), path.join(pubJs, 'tom-select.complete.min.js')],
  [path.join(nm, 'css', 'tom-select.bootstrap5.min.css'), path.join(pubCss, 'tom-select.bootstrap5.min.css')],
];

for (const [src, dest] of pairs) {
  if (!fs.existsSync(src)) {
    console.error('Missing:', src);
    process.exit(1);
  }
  fs.copyFileSync(src, dest);
  console.log('Copied', path.basename(dest));
}
