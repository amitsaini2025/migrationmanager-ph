# Plan: Vendor Library Vite Consolidation (Phase 2)

**Status:** Phase 2b applied — Phase 2f (copy script retirement) pending soak + QA  
**Created:** 2026-06-30  
**Scope:** Move copied `public/js` vendor assets (Tom Select, DataTables, flatpickr, iziToast, inputmask) into Vite bundles; retire copy scripts when stable.  
**Parent doc:** `docs/TECH_UPDATE.md` — Track 1 (Phase 2) + Track 2 consolidation + Track 5

---

## Executive summary

Tom Select **works today** via npm → `copy-tom-select.cjs` → `public/js/`. This plan does **not** change select behaviour. It changes **how** shared vendor JS/CSS is built and loaded.

**Target:** One (or two) Vite entry points replace ~8 raw `<script>` / `<link>` tags per CRM layout, with hashed filenames and a single `npm run build` path.

**Do not start** until Phase 2a foundation is done and stakeholders sign off on a maintenance window for layout changes.

---

## Current state (audit 2026-06-30)

| Library | npm package | Copy script | public/ artifact | Loaded in layouts |
|---------|-------------|-------------|------------------|-------------------|
| Tom Select | `tom-select@^2.6.1` | `copy:tom-select` | `js/tom-select.complete.min.js`, `css/tom-select.bootstrap5.min.css` | `crm_client_detail`, `crm_client_detail_dashboard` |
| Tom Select bridge | — (project) | — | `js/mm-tomselect-jquery.js` | same layouts |
| Tom Select compat CSS | — (project) | — | `css/tom-select-layout-compat.css` | same layouts |
| DataTables | `datatables.net*` + jszip + pdfmake | `copy:datatables` | `js/datatables.min.js`, `js/datatables-pdfmake.min.js`, BS5 CSS | same layouts |
| flatpickr | `flatpickr@^4.6.13` | `copy:flatpickr` (manual) | `js/flatpickr.min.js`, `css/flatpickr.min.css` | via `components/flatpickr-*` |
| inputmask | `inputmask@^5.0.9` | `copy:inputmask` (manual) | `js/inputmask.min.js` | page-specific |
| iziToast | — (legacy in public/) | none | `js/iziToast.min.js`, `css/iziToast.min.css` | same layouts |
| jQuery | CDN 3.7.1 | — | — | `<head>` synchronous |
| Bootstrap JS | CDN 5.3.7 | — | — | before `scripts.js` |
| TinyMCE | self-hosted | — | `public/js/tinymce/` | same layouts (**Phase 2e**, out of scope here) |

**Vite today** (`vite.config.js`): `app.css`, `fullcalendar-v6.css`, `icons.css`, `app.js`, `lucide-init.js` only.

**Layouts affected:** All CRM pages extend one of:

- `resources/views/layouts/crm_client_detail.blade.php` (~100+ views)
- `resources/views/layouts/crm_client_detail_dashboard.blade.php` (~10 views)

Both layouts share the same vendor `<head>` CSS and footer `<script>` block pattern.

---

## Goals

1. **Single build path** — `npm run build` = `vite build` only (no copy steps for bundled libs).
2. **Fewer HTTP requests** — one vendor JS + one vendor CSS entry instead of 6–8 separate files.
3. **Version integrity** — browser loads exactly what `package.json` resolves; no stale `public/` copies.
4. **Preserve behaviour** — `.mmSelect()`, DataTables exports, flatpickr init, iziToast toasts unchanged.
5. **Safe rollback** — keep copy scripts and `public/` files until one full release cycle passes.

---

## Non-goals (this plan)

- Migrating `detail-main.js`, `scripts.js`, or other page JS into Vite (Phase 2c–2d).
- TinyMCE 7 npm migration (Phase 2e).
- Bootstrap CSS via Vite / FOUC fix (Phase 2a — can run in parallel but separate PR).
- Changing Tom Select API or re-testing Select2 migration (see smoke matrix in `TECH_UPDATE.md` Track 2).
- Bundling **jQuery** (must stay synchronous in `<head>` per project convention).

---

## Critical constraints

### Load order (must preserve)

```
<head>
  jQuery 3.7.1          ← synchronous, NOT in Vite
  … app CSS …
</head>
<body>
  …
  app.min.js (legacy bootstrap bundle in public/)
  vendor-libs.js        ← NEW: DataTables, flatpickr, Tom Select, iziToast
  datatables-pdfmake    ← optional separate chunk (large ~1MB+)
  tinymce               ← stays raw until Phase 2e
  crm-flatpickr.js      ← project init; stays until Phase 2c
  mm-tomselect-jquery   ← after TomSelect global exists
  bootstrap.bundle
  scripts.js / custom.js
  … page scripts …
  @vite app.js          ← expects window.iziToast, window.Echo, etc.
</body>
```

### Globals required by existing code

| Global | Set by | Used by |
|--------|--------|---------|
| `$` / `jQuery` | CDN `<head>` | DataTables, mm-tomselect, most CRM JS |
| `TomSelect` | vendor-libs | `mm-tomselect-jquery.js` |
| `$.fn.mmSelect` | mm-tomselect bridge | 40+ Blade/JS files |
| `$.fn.dataTable` | vendor-libs | tables across CRM |
| `flatpickr` | vendor-libs | `crm-flatpickr.js`, inline inits |
| `iziToast` | vendor-libs | `scripts.js`, layout inline, `app.js` |
| `JSZip` / `pdfMake` | vendor-libs (+ pdf chunk) | DataTables HTML5 / PDF export |

### Files that stay in `public/` (even after Phase 2b)

- `public/css/tom-select-layout-compat.css` — project overrides; not from npm.
- `public/js/mm-tomselect-jquery.js` — until Phase 2c moves it into `resources/js/vendor/mm-tomselect-jquery.js` and imports after Tom Select.
- `public/js/crm-flatpickr.js` — project init wrapper.
- `public/js/tinymce/` — Phase 2e.

---

## Phased apply plan

### Phase 0 — Pre-flight (no code deploy)

- [ ] Approve this plan and pick apply window (low-traffic day).
- [ ] Create branch: `feat/phase-2-vendor-libs`.
- [ ] Run Tom Select smoke matrix from `TECH_UPDATE.md` Track 2 on **current** production-like env; record baseline pass/fail.
- [ ] Capture baseline metrics: Network tab script count + total transfer on client detail page load.
- [ ] Confirm `npm run build` and `npm run dev` succeed on Node ≥22.

---

### Phase 2a — Foundation (prerequisite)

**Status:** ✅ Done (2026-06-30)  
**Can deploy independently:** Yes (low risk if limited to config/docs)

| # | Task | Files | Done |
|---|------|-------|------|
| 2a.1 | Add `docs/PUBLIC-JS-LEGACY.md` — bundling rules, `@legacy` alias, what stays in `public/js` | new doc | ✅ |
| 2a.2 | Extend `vite.config.js`: `resolve.alias['@legacy']` → `public/js` | `vite.config.js` | ✅ |
| 2a.3 | Add `npm run audit:legacy-js` — list unreferenced `public/js/*.js` | `scripts/audit-legacy-js.cjs`, `package.json` | ✅ |
| 2a.4 | (Optional) Bootstrap CSS via Vite in `<head>` — separate PR | layouts, `resources/css/` | — |

**Exit criteria:** Vite config documented; audit script runs; no layout vendor changes yet. ✅

---

### Phase 2b — Vendor bundle (core of this plan)

**Status:** ✅ Applied (2026-06-30) — copy scripts retained for rollback  
**Deploy:** Staged — feature flag or parallel load recommended (see Rollback)

#### Step 2b.1 — Create Vite entries

**Done:**

- `resources/js/vendor-libs.js` — Tom Select, flatpickr, DataTables, JSZip, iziToast, mm-tomselect bridge
- `resources/css/vendor-libs.css` — vendor CSS bundle
- `resources/js/vendor/jquery-global-shim.js` — uses CDN jQuery global for DataTables
- `vite.config.js` — entries + `@legacy-css` alias

#### Step 2b.2 — Wire layouts (both CRM layouts)

**Done:** `crm_client_detail.blade.php`, `crm_client_detail_dashboard.blade.php`

Removed raw tags for: datatables.min.js, flatpickr, tom-select, iziToast (JS + CSS).  
Kept: `datatables-pdfmake.min.js`, `crm-flatpickr.js`, `tom-select-layout-compat.css`.

#### Step 2b.3 — Dual-run period (recommended)

**Active:** `npm run build` still runs copy scripts; layouts load Vite bundle only (not `public/` copies).

#### Step 2b.4 — Update `package.json` build script

**Pending** — remove copy steps after soak period (Phase 2f gate).

#### Step 2b.5 — Retire copy scripts (Phase 2f gate)

**Pending** — see criteria below.

**Exit criteria:** Client detail loads with ≤2 vendor Vite tags; Tom Select / DataTables / flatpickr / iziToast work; build has no copy steps.

---

### Phase 2c — Layout entries (follow-on, not blocking 2b)

- Move `mm-tomselect-jquery.js` → `resources/js/vendor/mm-tomselect-jquery.js`; import at end of `vendor-libs.js`.
- Move `crm-flatpickr.js` into vendor or layout entry.
- Extract layout inline scripts into `resources/js/layouts/crm-client-detail.js`.

---

## Testing checklist

Run on **both** layouts after each deploy step.

### Tom Select (from TECH_UPDATE.md)

| Screen / feature | Verify |
|------------------|--------|
| Global search | AJAX select, keyboard nav |
| Compose email (To/CC) | Recipient search, pre-fill on reply/forward |
| Change Matter Assignee modal | Dropdown positioning (`dropdownParent: 'body'`) |
| Add Application / Create In Person Client modals | Selects init after modal open |
| Client portal — Add Checklist | Dropdown visible inside modal |
| Leads list filters | AJAX matter/client selects |
| Assign Staff popover | Staff multi-select |
| Partner/product forms | Dependent selects |
| Audit log filters | Date + staff selects |
| DOB search | Date/select behaviour |

### Other vendors

| Feature | Verify |
|---------|--------|
| DataTables list pages | Sort, filter, pagination |
| DataTables Excel export | buttons.html5 / JSZip |
| DataTables PDF export | pdfHtml5 (if pdf chunk loaded) |
| Date fields | flatpickr opens, format matches `dataformat` |
| Toasts | iziToast on form save, notification bell |
| Vite app.js | Echo connects; notification bell updates |

### Build / deploy

- [ ] `npm run build` succeeds in CI
- [ ] `public/build/manifest.json` contains `vendor-libs` entries
- [ ] Production: `@vite` resolves hashed assets (run `php artisan view:cache` if used)
- [ ] Hard refresh + empty cache — no 404 on vendor chunks

---

## Risks and mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Vite bundle loads before jQuery | High — DataTables/mmSelect break | Keep jQuery in `<head>`; vendor-libs `@vite` after jQuery; never import jQuery in vendor-libs |
| `mm-tomselect-jquery.js` runs before `TomSelect` global | High | Import Tom Select in vendor-libs first; bridge script immediately after |
| pdfmake bundle bloats initial load | Medium | Keep pdfmake as separate lazy chunk or raw script |
| iziToast not on npm | Low | Add npm dep or transitional `@legacy` import |
| Dev vs prod path mismatch | Medium | Test `npm run dev` and `npm run build` on same pages |
| Double-loading vendors during dual-run | Medium | Never load both Vite + raw script for same lib in production |
| CSS order / Bootstrap vars | Low | Keep `tom-select-layout-compat.css` after vendor CSS |

---

## Rollback plan

1. Revert Blade changes in both layouts to raw `asset('js/...')` tags.
2. Restore `"build": "npm run copy:datatables && npm run copy:tom-select && vite build"` if copy scripts were removed.
3. Run `npm run copy:tom-select && npm run copy:datatables`.
4. Clear view/cache CDN if applicable.

**Rollback time estimate:** <30 minutes (Blade revert only if copy scripts retained).

---

## Recommended PR sequence

| PR | Contents | Risk |
|----|----------|------|
| PR1 | Phase 2a: vite alias, audit script, PUBLIC-JS-LEGACY.md | Low |
| PR2 | Phase 2b: vendor-libs entries + layout wire-up (both layouts) | Medium |
| PR3 | Phase 2b: remove copy steps from build; deprecate public copies | Low (after soak) |
| PR4 | Phase 2c: mm-tomselect into resources, layout entry start | Medium |

**Do not combine PR2 with detail-main.js migration.**

---

## Success metrics

| Metric | Before (typical) | Target |
|--------|------------------|--------|
| Vendor `<script>` tags per CRM layout | ~6–8 | 1–2 (`vendor-libs` + optional pdf chunk) |
| Vendor `<link>` tags (excl. compat) | ~5 | 1 (`vendor-libs.css`) + compat |
| `npm run build` steps | copy ×2 + vite | vite only |
| Tom Select smoke matrix | baseline | 100% pass |

---

## Approval

| Role | Name | Date | Approved |
|------|------|------|----------|
| Dev lead | | | ☐ |
| QA | | | ☐ |

**Until approved:** Do not merge Phase 2b layout changes. Current copy-to-public approach remains the production path.
