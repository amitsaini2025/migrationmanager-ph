# Icon migration — Font Awesome → Lucide

**Status:** Phase 2 complete (global shell) · Phase 3+ pending  
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
| 3 — Core screens | Client detail/edit, accounts, leads, AdminConsole | Pending |
| 4 — Dynamic sources | DB icons, controllers, JS templates | Pending |
| 5 — Edge pages | EOI sheets, exception page | Pending |
| 6 — Cleanup | Remove FA assets | Pending |

---

## Configuration

### `config/icons.php`

| Key | Purpose |
|-----|---------|
| `defaults` | Default Lucide size, stroke width, CSS class |
| `spinners` | FA spinner tokens → Lucide `loader-2` (with spin animation in Phase 1) |
| `brands` | Brand glyphs with no Lucide equivalent |
| `legacy` | `fa-*` token → Lucide icon name |

**Lookup rule:** Strip style prefix (`fas`, `far`, `fab`, `fa`) and use the glyph token (`fa-trash` → `trash-2`).

### npm

```bash
npm install          # installs lucide
npm run audit:icons  # regenerates docs/ICON-AUDIT.md
```

---

## Naming conventions (Phase 1+)

### Blade

```blade
{{-- Target API (Phase 1) --}}
@icon('trash-2', ['class' => 'icon-sm'])

{{-- Legacy during transition --}}
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

## Brand icons

Lucide does **not** ship brand logos (Google, Facebook, etc.).

**Decision for this project:**

| FA token | Strategy |
|----------|----------|
| `fab fa-google` | `IconHelper::brand('google')` — small inline SVG stored in `IconHelper` (or a `resources/views/components/icons/brand-google.blade.php` partial). Only used on client detail (Google review). |

Do **not** keep the full Font Awesome bundle solely for one brand icon. Phase 6 removes FA entirely except documented brand partials.

---

## Spinner / loading states

Font Awesome pattern:

```html
<i class="fas fa-spinner fa-spin"></i>
```

Lucide pattern (Phase 1):

```html
<i data-lucide="loader-2" class="icon-spin"></i>
```

`fa-spinner` maps to `loader-2` in both `config/icons.php` → `spinners` and `legacy`.

---

## Database & user-configured icons

`email_labels.icon` stores FA class strings (e.g. `fas fa-inbox`). Phase 4 will:

1. Migrate seed/system rows to Lucide names (`inbox`).
2. Change admin forms from free-text FA classes to a Lucide picker or name field.
3. Use `IconHelper::fromLegacy()` for any unmigrated rows.

---

## Sortable columns

`config/columnsortable.php` and `SortableHelper` use `fa fa-sort*`. Mapped in `legacy`:

- `fa-sort` → `arrow-up-down`
- `fa-sort-up` → `arrow-up`
- `fa-sort-down` → `arrow-down`

Update `@sortablelink` / helper in Phase 2 to emit Lucide markup.

**Done in Phase 2:** `SortableHelper::linkWithIcon()` uses `IconHelper::fromLegacy()`.

---

## Phase 2 file inventory

| Area | Files migrated |
|------|----------------|
| Navbar | `resources/views/Elements/CRM/header_client_detail.blade.php` |
| Layouts | `crm_client_detail.blade.php`, `crm_client_detail_dashboard.blade.php` (broadcast banner, topbar CSS, office-visit notification JS) |
| Sortable | `app/Helpers/SortableHelper.php` |
| Email partials | `email-engagement-icons.blade.php`, `email-event-timeline.blade.php`, `EmailLogEvent::iconHtml()` |
| Dashboard | `dashboard-optimized.blade.php`, `dashboard-optimized.js`, `kpi-card`, `column-toggle`, `filter-form`, `access-approvals-dashboard` |
| Loader | `components/crm-popuploader.blade.php` |
| CSS | `public/css/emails.css` (Lucide spinner + attachment selectors alongside FA) |

Font Awesome CSS remains loaded in layouts during parallel migration.

## Audit workflow

After adding new FA icons or editing the map:

```bash
npm run audit:icons
```

Review `docs/ICON-AUDIT.md` for **unmapped** tokens and add them to `config/icons.php` → `legacy`.

---

## File inventory (Phase 0 baseline)

| Asset | Location |
|-------|----------|
| FA CSS (layouts) | `public/icons/font-awesome/css/all.min.css` |
| FA CDN | EOI confirmation sheets only |
| Lucide package | `node_modules/lucide` (npm) |
| Mapping config | `config/icons.php` |
| Audit script | `scripts/audit-icons.cjs` |

~200 Blade/PHP/JS files reference FA classes; **`npm run audit:icons`** reports **216 unique tokens** (all mapped in `config/icons.php` as of Phase 0). See [ICON-AUDIT.md](ICON-AUDIT.md).

---

## Revision log

| Date | Change |
|------|--------|
| 2026-06-30 | Phase 2: navbar, layouts, SortableHelper, email partials, dashboard shell, popuploader, emails.css Lucide selectors |
| 2026-06-30 | Phase 1: IconHelper, @icon, lucide-init.js, icons.css, layout wiring, dashboard nav proof icon |
| 2026-06-30 | Phase 1 review: fix loader-2 spin bug, crmIcon PascalCase lookup, Vite module init timing, fa-google legacy map |
