# Icon migration — Font Awesome → Lucide

**Status:** Complete (Stage 9 cleanup — 2026-06-30)  
**Related:** [TECH_UPDATE.md](TECH_UPDATE.md) Track 3 · [ICON-AUDIT.md](ICON-AUDIT.md) (generated)

---

## Goal

Replace Font Awesome 5 font icons with [Lucide](https://lucide.dev/) SVG icons across the CRM, using a shared abstraction so Blade, PHP, and JS render icons consistently.

---

## Phase checklist

| Phase | Scope | Status |
|-------|--------|--------|
| **0 — Prep** | npm package, `config/icons.php`, audit script, docs | **Done** |
| **1 — Infrastructure** | `IconHelper`, `@icon`, `lucide-init.js`, `crmIcon()` | **Done** |
| **2 — Global shell** | Navbar, layouts, sortable, email partials, dashboard shell, emails.css | **Done** |
| **3 — Core screens** | Client detail/edit, accounts, leads, AdminConsole | **Done** |
| **4 — Dynamic sources** | DB icons, controllers, JS templates | **Done** |
| **5 — Edge pages** | EOI sheets, exception page | **Done** |
| **6 — Cleanup** | Remove FA assets, CSS pass, audit gate | **Done** |

---

## Configuration

### `config/icons.php`

| Key | Purpose |
|-----|---------|
| `defaults` | Default Lucide size, stroke width, CSS class |
| `spinners` | FA spinner tokens → Lucide `loader-2` (with spin animation) |
| `brands` | Brand glyphs with no Lucide equivalent |
| `legacy` | `fa-*` token → Lucide icon name |

**Lookup rule:** Strip style prefix (`fas`, `far`, `fab`, `fa`) and use the glyph token (`fa-trash` → `trash-2`).

### npm

```bash
npm install          # installs lucide
npm run audit:icons  # regenerates docs/ICON-AUDIT.md; exits 1 if unmapped tokens
npm run audit:icons:strict  # also fails on raw <i class="fas fa-*"> in resources/ and app/
```

---

## Naming conventions

### Blade

```blade
@icon('trash-2', ['class' => 'icon-sm'])
@icon('fa-trash')  {{-- resolves via config/icons.php legacy map --}}
```

### PHP

```php
IconHelper::render('inbox');
IconHelper::fromLegacy('fas fa-inbox'); // → inbox
IconHelper::brand('google');            // brand SVG, see below
```

### JavaScript

```js
crmIcon('loader-2', { className: 'icon-spin' });
crmIconLegacy('fas fa-spinner fa-spin');
lucide.createIcons(); // after injecting data-lucide nodes
```

### CSS classes

| Class | Use |
|-------|-----|
| `.lucide` / `.icon` | Base sizing (`1em × 1em`, vertical align) |
| `.icon-sm` | 14px |
| `.icon-lg` | 20px |
| `.icon-spin` | Rotation animation (replaces `fa-spin`) |

---

## Brand icons (intentional exception)

Lucide does **not** ship brand logos (Google, Facebook, etc.).

| FA token | Strategy |
|----------|----------|
| `fab fa-google` | `IconHelper::brand('google')` — inline SVG in `IconHelper` / brand partial. Only used on client detail (Google review). |

The standalone FA bundle (`public/icons/font-awesome/`) has been **removed**. CRM layouts no longer load FA CSS.

---

## Spinner / loading states

Font Awesome pattern (legacy string only — rendered as Lucide):

```html
<i class="fas fa-spinner fa-spin"></i>  <!-- via crmIconLegacy / fromLegacy -->
```

Lucide pattern:

```html
<i data-lucide="loader-2" class="icon-spin"></i>
```

---

## Database & user-configured icons

`email_labels.icon` stores FA class strings (e.g. `fas fa-inbox`). Runtime resolution uses `IconHelper::fromLegacy()` for all rows.

---

## Sortable columns

`config/columnsortable.php` and `SortableHelper` use `fa fa-sort*`. Mapped in `legacy`:

- `fa-sort` → `arrow-up-down`
- `fa-sort-up` → `arrow-up`
- `fa-sort-down` → `arrow-down`

`SortableHelper::linkWithIcon()` uses `IconHelper::fromLegacy()`.

---

## Stage 9 cleanup (complete)

| Action | Detail |
|--------|--------|
| Removed FA `<link>` | `crm_client_detail.blade.php`, `crm_client_detail_dashboard.blade.php` |
| Deleted FA bundle | `public/icons/font-awesome/` (css + fonts) |
| Deleted duplicate fonts | `public/fonts/fa-brands-400.svg`, `fa-regular-400.svg`, `fa-solid-900.svg` |
| EOI sheets | FA CDN removed in Stage 8; Bootstrap CDN only |
| CSS pass | `emails.css`, `client-forms.css` — no `.fa-*` selectors; `client-detail.css` already clean |
| Login / non-CRM | `public/css/app.min.css` retains embedded FA 5 CSS + `public/fonts/webfonts/` for legacy pages (e.g. `crm-login.blade.php`) |
| Audit gate | `npm run audit:icons` exits 1 on unmapped tokens; optional `--strict` for raw `<i class="fas fa-*">` |

Legacy FA class strings may remain in JS/Blade as arguments to `crmIcon()` / `crmIconLegacy()` / `IconHelper::fromLegacy()` — these resolve at runtime to Lucide SVG.

---

## Audit workflow

```bash
npm run audit:icons
```

Review `docs/ICON-AUDIT.md` for **unmapped** tokens and add them to `config/icons.php` → `legacy`.

---

## File inventory (post-migration)

| Asset | Location |
|-------|----------|
| Lucide (Vite) | `resources/js/lucide-init.js`, `resources/css/icons.css` |
| Lucide package | `node_modules/lucide` (npm) |
| Mapping config | `config/icons.php` |
| Audit script | `scripts/audit-icons.cjs` |
| Legacy FA (login only) | Embedded in `public/css/app.min.css` → `public/fonts/webfonts/` |

~214 unique FA tokens referenced in app code; all mapped in `config/icons.php`. See [ICON-AUDIT.md](ICON-AUDIT.md).

---

## Revision log

| Date | Change |
|------|--------|
| 2026-06-30 | Stage 9: removed FA bundle + layout links, CSS pass, audit exit gate, docs complete |
| 2026-06-30 | Stages 3–8: core screens, dynamic sources, EOI sheets, JS/PHP migration |
| 2026-06-30 | Phase 2: navbar, layouts, SortableHelper, email partials, dashboard shell, populoader |
| 2026-06-30 | Phase 1: IconHelper, @icon, lucide-init.js, icons.css, layout wiring |
| 2026-06-30 | Phase 0: config, audit script, npm package |
