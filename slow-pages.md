# Slow pages review

Measured **25 Aug 2026** against `http://127.0.0.1:8000` (local, logged-in superadmin).  
**Ideal:** HTML TTFB **under 800ms** (Google “good” TTFB). **Poor:** over **2.5s** (Google LCP “good” bar).  
Original probe did not change app code. **Lead analytics** was re-measured the same day after the aggregate-query rewrite (`/leads/analytics`): median HTML TTFB **51ms** (3 runs: 180ms warmup / 51ms / 43ms; HTTP 200). Previously **3.5s** (500), then **1.3s** after the route fix. **Admin Console — E-Signature** was re-measured the same day after the analytics rewrite (`/adminconsole/features/esignature`): median HTML TTFB **70ms** (3 runs: 70ms / 70ms / 143ms warmup; HTTP 200, ~250KB). Analytics queries alone **53ms**. Previously **~10 min**. **Dashboard** was re-measured the same day after the add-task markup rewrite (`/dashboard`): median HTML TTFB **98ms** (3 runs: 231ms warmup / 82ms / 98ms; HTTP 200, ~373KB). Previously **2.6s** (~2.5MB HTML, 252 Blade queries). **Client edit** was re-measured **26 Aug 2026** after the dropdown-HTML rewrite (`/clients/edit/{id}`): median HTML TTFB **99ms** (3 runs: 327ms warmup / 99ms / 90ms; HTTP 200, ~380KB, 31 queries). Previously **11.2s**. **Office visit create** (`/office-visits/create`, **10.9s** / 500) was a leftover stub (missing `crm.officevisits.create` view). The live create flow is **Front-desk check-in** (`/front-desk/checkin`). The dead route was removed the same day.

Times are **server HTML render only**. A real browser load is slower (CSS/JS/TinyMCE plus polling). `php artisan serve` is single-threaded, so background polls (`/get-activities`, `/fetch-notification`, `/notifications/broadcasts/unread`) often add **1–4s** on top of the numbers below.

---

## Poor (over 2.5s)

| Page | Path | TTFB |
| --- | --- | --- |
| Client detail — Account tab | `/clients/detail/.../account` | **2.9s** |
| Client detail — Checklists tab | `/clients/detail/.../checklists` | **2.7s** |

## Slow (0.8s–2.5s)

| Page | Path | TTFB |
| --- | --- | --- |
| Signature dashboard | `/signatures` | 2.4s |
| Accounts analytics | `/clients/analytics-dashboard` | 2.4s |
| Admin — Emails | `/adminconsole/features/emails` | 2.0s |
| Assigned by me | `/assigned_by_me` | 1.8s |
| EOI/ROI sheet | `/clients/sheets/eoi-roi` | 1.7s |
| Visa expiry report | `/reports/visaexpires` | 1.7s |
| SendGrid senders (AJAX, hits with client detail) | `/crm/sendgrid-senders` | 1.7s |
| Client detail (all tabs) | `/clients/detail/{id}/...` | **0.9–1.8s** (HTML ~2MB) |
| Invoice list | `/clients/invoicelist` | 1.5s |
| Client receipts | `/clients/clientreceiptlist` | 1.4s |
| Office receipts | `/clients/officereceiptlist` | 1.1s |
| Admin — Matter list | `/adminconsole/features/matter` | 1.0s |
| TR sheet | `/clients/sheets/tr` | 1.0s |
| Add lead | `/leads/create` | 1.0s |
| Actions completed | `/action_completed` | 0.9s |
| Admin — Sent emails | `/adminconsole/features/sent-emails` | 0.8–0.8s |
| Signature create | `/signatures/create` | 0.8s |

Client-detail **lazy tab fragments** (`/clients/detail-*-tab/...`) were fast (13–185ms). The full page is slow because it still ships a ~2MB layout.

---

## Also slow in the live browser (not isolated TTFB)

While using the site, these kept taking **0.5–4s** because they share the PHP worker:

- `/get-activities`
- `/fetch-notification`
- `/fetch-office-visit-notifications`
- `/notifications/broadcasts/unread`

---

**OK (under 800ms):** login, profile, bookings list/calendars, office-visit waiting/attending, **Front-desk check-in** (`/front-desk/checkin`; replaced dead `/office-visits/create`), actions, clients/leads/archived lists, **Dashboard** (`/dashboard`, **98ms**, was 2.6s), **Client edit** (`/clients/edit/{id}`, **99ms**, was 11.2s), **Lead analytics** (`/leads/analytics`, **51ms**, was 3.5s / 500 then 1.3s), most Admin Console lists, **Admin Console — E-Signature** (`/adminconsole/features/esignature`, **70ms**, was ~10 min), SMS, offices, roles, teams, ANZSCO, access-grants dashboard.
