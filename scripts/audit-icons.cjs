/**
 * Scan Blade/PHP/JS/CSS for Font Awesome class tokens (fa-*).
 * Outputs unique icon names, usage counts, and unmapped names vs config/icons.php.
 *
 * Usage: node scripts/audit-icons.cjs [--json]
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const EXTENSIONS = new Set(['.blade.php', '.php', '.js', '.css']);
const SKIP_DIRS = new Set([
    'node_modules',
    'vendor',
    'public/js/tinymce',
    'storage',
    'bootstrap/cache',
]);

/** FA utility / font-file tokens — not icon glyphs */
const UTILITY_TOKENS = new Set([
    'fa-spin',
    'fa-pulse',
    'fa-fw',
    'fa-lg',
    'fa-2x',
    'fa-3x',
    'fa-4x',
    'fa-5x',
    'fa-xs',
    'fa-sm',
    'fa-ul',
    'fa-li',
    'fa-border',
    'fa-pull-left',
    'fa-pull-right',
    'fa-stack',
    'fa-stack-1x',
    'fa-stack-2x',
    'fa-inverse',
    // Font Awesome webfont filenames referenced in CSS
    'fa-solid-900',
    'fa-regular-400',
    'fa-brands-400',
    'fa-light-300',
    'fa-duotone-900',
    // CSS transform utilities (not icons)
    'fa-rotate-90',
    'fa-rotate-180',
    'fa-rotate-270',
    'fa-flip-horizontal',
    'fa-flip-vertical',
    'fa-flip-both',
]);

/** Style prefix tokens sometimes captured by regex */
const STYLE_TOKENS = new Set(['fa', 'fas', 'far', 'fab', 'fal', 'fad']);

const TOKEN_RE = /\bfa-[a-z0-9-]+\b/g;

function walk(dir, files = []) {
    const stat = fs.statSync(dir, { throwIfNoEntry: false });
    if (!stat) {
        return files;
    }
    if (stat.isFile()) {
        const ext = path.extname(dir);
        const base = path.basename(dir).toLowerCase();
        if (!EXTENSIONS.has(ext)) {
            return files;
        }
        if (base.includes('font-awesome') || base === 'fontawesome-webfont.svg') {
            return files;
        }
        if (ext === '.css' && (base.endsWith('.min.css') || base === 'app.css')) {
            return files;
        }
        if (dir.replace(/\\/g, '/').endsWith('config/icons.php')) {
            return files;
        }
        files.push(dir);
        return files;
    }

    let entries;
    try {
        entries = fs.readdirSync(dir, { withFileTypes: true });
    } catch {
        return files;
    }

    for (const entry of entries) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (SKIP_DIRS.has(entry.name)) {
                continue;
            }
            walk(full, files);
            continue;
        }
        const ext = path.extname(entry.name);
        if (!EXTENSIONS.has(ext)) {
            continue;
        }
        const base = entry.name.toLowerCase();
        if (base.includes('font-awesome') || base === 'fontawesome-webfont.svg') {
            continue;
        }
        // Bundled vendor CSS embeds the full FA glyph list (e.g. app.min.css)
        if (ext === '.css' && (base.endsWith('.min.css') || base === 'app.css')) {
            continue;
        }
        // Skip the mapping config — it lists every known fa-* token, not usage
        if (full.replace(/\\/g, '/').endsWith('config/icons.php')) {
            continue;
        }
        files.push(full);
    }
    return files;
}

function loadLegacyMap() {
    const configPath = path.join(ROOT, 'config', 'icons.php');
    if (!fs.existsSync(configPath)) {
        return {};
    }
    const src = fs.readFileSync(configPath, 'utf8');
    const map = {};
    const re = /'((?:fa|fas|far|fab)-[a-z0-9-]+)'\s*=>\s*'([^']+)'/g;
    let m;
    while ((m = re.exec(src)) !== null) {
        map[m[1]] = m[2];
    }
    return map;
}

function main() {
    const asJson = process.argv.includes('--json');
    const scanRoots = [
        path.join(ROOT, 'resources'),
        path.join(ROOT, 'app'),
        path.join(ROOT, 'database'),
        path.join(ROOT, 'public', 'js'),
        // App-specific CSS only (not vendor bundles under public/css/*.min.css)
        path.join(ROOT, 'public', 'css', 'emails.css'),
        path.join(ROOT, 'public', 'css', 'client-detail.css'),
        path.join(ROOT, 'public', 'css', 'client-forms.css'),
        path.join(ROOT, 'config', 'columnsortable.php'),
    ].filter((p) => fs.existsSync(p));

    const files = scanRoots.flatMap((dir) => walk(dir));
    const counts = new Map();
    const fileHits = new Map();

    for (const file of files) {
        const rel = path.relative(ROOT, file).replace(/\\/g, '/');
        const content = fs.readFileSync(file, 'utf8');
        const matches = content.match(TOKEN_RE) || [];
        const seenInFile = new Set();

        for (const token of matches) {
            if (UTILITY_TOKENS.has(token) || STYLE_TOKENS.has(token)) {
                continue;
            }
            counts.set(token, (counts.get(token) || 0) + 1);
            seenInFile.add(token);
        }

        if (seenInFile.size > 0) {
            fileHits.set(rel, seenInFile.size);
        }
    }

    const sorted = [...counts.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
    const legacyMap = loadLegacyMap();
    const unmapped = sorted.filter(([token]) => !legacyMap[token]).map(([token]) => token);
    const brandIcons = sorted.filter(([token]) => token === 'fa-google' || token.startsWith('fa-') && sorted.some(([t]) => t === 'fa-google'));

    const summary = {
        scannedFiles: files.length,
        filesWithIcons: fileHits.size,
        uniqueIconTokens: sorted.length,
        totalReferences: sorted.reduce((sum, [, n]) => sum + n, 0),
        mappedInConfig: sorted.length - unmapped.length,
        unmappedCount: unmapped.length,
        brandIcons: sorted.filter(([t]) => t === 'fa-google').map(([t, n]) => ({ token: t, count: n })),
    };

    if (asJson) {
        console.log(JSON.stringify({ summary, icons: Object.fromEntries(sorted), unmapped }, null, 2));
        return;
    }

    const lines = [];
    lines.push('# Font Awesome icon audit');
    lines.push('');
    lines.push(`Generated: ${new Date().toISOString().slice(0, 10)}`);
    lines.push('');
    lines.push('Run `npm run audit:icons` to regenerate this file.');
    lines.push('');
    lines.push('## Summary');
    lines.push('');
    lines.push(`| Metric | Value |`);
    lines.push(`|--------|-------|`);
    lines.push(`| Files scanned | ${summary.scannedFiles} |`);
    lines.push(`| Files with FA icons | ${summary.filesWithIcons} |`);
    lines.push(`| Unique \`fa-*\` icon tokens | ${summary.uniqueIconTokens} |`);
    lines.push(`| Total references | ${summary.totalReferences} |`);
    lines.push(`| Mapped in \`config/icons.php\` | ${summary.mappedInConfig} |`);
    lines.push(`| Unmapped (need review) | ${summary.unmappedCount} |`);
    lines.push('');
    lines.push('## Icons by usage (descending)');
    lines.push('');
    lines.push('| Token | Count | Lucide (config) |');
    lines.push('|-------|-------|-----------------|');
    for (const [token, count] of sorted) {
        const lucide = legacyMap[token] ?? '—';
        lines.push(`| \`${token}\` | ${count} | ${lucide === '—' ? '**unmapped**' : `\`${lucide}\``} |`);
    }

    if (unmapped.length > 0) {
        lines.push('');
        lines.push('## Unmapped tokens');
        lines.push('');
        lines.push('Add entries to `config/icons.php` → `legacy` for each token below.');
        lines.push('');
        for (const token of unmapped) {
            lines.push(`- \`${token}\` (${counts.get(token)} refs)`);
        }
    }

    lines.push('');
    lines.push('## Brand icons');
    lines.push('');
    if (summary.brandIcons.length === 0) {
        lines.push('No `fa-google` or other brand tokens found in scan roots.');
    } else {
        for (const { token, count } of summary.brandIcons) {
            lines.push(`- \`${token}\`: ${count} reference(s) — see \`config/icons.php\` → \`brands\``);
        }
    }

    const outPath = path.join(ROOT, 'docs', 'ICON-AUDIT.md');
    fs.writeFileSync(outPath, lines.join('\n') + '\n', 'utf8');
    console.log(`Wrote ${path.relative(ROOT, outPath)}`);
    console.log(`  ${summary.uniqueIconTokens} unique tokens, ${summary.unmappedCount} unmapped`);

    if (process.argv.includes('--strict')) {
        const rawFaRe = /<i\s+class="fa[srb]?\s+fa-/g;
        const strictRoots = [
            path.join(ROOT, 'resources'),
            path.join(ROOT, 'app'),
        ];
        const rawHits = [];
        for (const dir of strictRoots) {
            if (!fs.existsSync(dir)) {
                continue;
            }
            for (const file of walk(dir)) {
                const content = fs.readFileSync(file, 'utf8');
                const matches = content.match(rawFaRe);
                if (matches && matches.length > 0) {
                    rawHits.push({
                        file: path.relative(ROOT, file).replace(/\\/g, '/'),
                        count: matches.length,
                    });
                }
            }
        }
        if (rawHits.length > 0) {
            console.error('');
            console.error('Strict mode: raw <i class="fas fa-*"> HTML found (use @icon / crmIcon / IconHelper):');
            for (const hit of rawHits) {
                console.error(`  ${hit.file} (${hit.count})`);
            }
            process.exit(2);
        }
        console.log('  strict: 0 raw FA <i> tags in resources/ and app/');
    }

    if (summary.unmappedCount > 0) {
        process.exit(1);
    }
}

main();
