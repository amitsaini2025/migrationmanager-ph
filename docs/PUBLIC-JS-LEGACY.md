# Public JS Legacy Assets

**Purpose:** Document how `public/js/` coexists with Vite during Phase 2 modernisation.  
**Related:** `docs/PLAN_VENDOR_LIBS_VITE_CONSOLIDATION.md`, `docs/TECH_UPDATE.md`

---

## Two asset paths

| Path | Loaded via | When to use |
|------|------------|-------------|
| `resources/js/` + Vite | `@vite([...])` in Blade | New code, vendor bundles, layout entries |
| `public/js/` | `asset('js/...')`, `URL::asset('js/...')` | Legacy CRM scripts not yet migrated |

During Phase 2, **both** paths are valid. Do not delete `public/js` files until the audit script and smoke tests confirm they are unused.

---

## Vite `@legacy` alias

`vite.config.js` maps `@legacy` → `public/js/`.

Use during transition to import legacy files from Vite entries without duplicating them:

```js
// resources/js/vendor-libs.js (future Phase 2b)
import '@legacy/mm-tomselect-jquery.js';
```

Prefer npm imports for vendor libraries once bundled. Use `@legacy` only for project files still living in `public/js/`.

---

## What stays in `public/js/` (for now)

### Vendor copies (Phase 2b target — bundle via Vite)

| File | Source | Loaded by |
|------|--------|-----------|
| `tom-select.complete.min.js` | `npm run copy:tom-select` | CRM layouts |
| `datatables.min.js` | `npm run copy:datatables` | CRM layouts |
| `datatables-pdfmake.min.js` | `npm run copy:datatables` | CRM layouts |
| `flatpickr.min.js` | `npm run copy:flatpickr` | `components/flatpickr-scripts` |
| `inputmask.min.js` | `npm run copy:inputmask` | Page-specific |
| `iziToast.min.js` | Legacy (no npm copy) | CRM layouts |

### Project scripts (Phase 2c–2d migration)

| File / area | Notes |
|-------------|-------|
| `mm-tomselect-jquery.js` | jQuery bridge for Tom Select; load after `TomSelect` global |
| `crm-flatpickr.js` | CRM flatpickr init wrapper |
| `scripts.js`, `custom.js` | Global CRM helpers |
| `crm/clients/detail-main.js` + modules | Largest page surface; migrate last |
| `tinymce/` | Self-hosted editor tree; Phase 2e |

### Never bundle via Vite

| Asset | Reason |
|-------|--------|
| jQuery | Must load synchronously in layout `<head>` before vendor scripts |

---

## Bundling rules

1. **jQuery stays in `<head>`** — not imported in Vite entries.
2. **Preserve load order** — see `PLAN_VENDOR_LIBS_VITE_CONSOLIDATION.md` § Critical constraints.
3. **Expose globals** — legacy code expects `window.TomSelect`, `window.flatpickr`, `window.iziToast`, `$.fn.dataTable`, etc.
4. **One lib, one path** — do not load the same library from both Vite and `asset('js/...')` in production.
5. **Compat CSS stays separate** — `public/css/tom-select-layout-compat.css` is project CSS, not npm.

---

## Audit tooling

```bash
npm run audit:legacy-js
```

Lists `public/js/**/*.js` files with no detected reference in Blade, PHP, or JS sources. Treat output as **candidates for removal**, not automatic deletes — dynamic loads and third-party docs may be missed.

Options:

- `--json` — machine-readable output
- `--verbose` — show reference locations for referenced files

---

## Phase 2 entry map (target)

| Vite entry | Replaces |
|------------|----------|
| `resources/css/vendor-libs.css` | flatpickr, tom-select, datatables, iziToast CSS in layouts |
| `resources/js/vendor-libs.js` | flatpickr, tom-select, datatables, iziToast JS in layouts |
| `resources/js/layouts/crm-client-detail.js` | Layout inline scripts (Phase 2c) |
| `resources/js/app.js` | Already live — Echo, Alpine, FullCalendar |

---

## Copy scripts (transition)

| Script | Produces | Retire when |
|--------|----------|-------------|
| `npm run copy:tom-select` | `public/js/tom-select.complete.min.js`, CSS | Phase 2f — vendor-libs stable |
| `npm run copy:datatables` | `public/js/datatables*.min.js`, CSS | Phase 2f |
| `npm run copy:flatpickr` | `public/js/flatpickr.min.js`, CSS | Phase 2f |
| `npm run copy:inputmask` | `public/js/inputmask.min.js` | Phase 2f or page entry migration |

Until Phase 2f, `npm run build` continues to run copy steps before `vite build`.
