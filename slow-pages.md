# Slow pages review

Measured **25 Aug 2026** against `http://127.0.0.1:8000` (local, logged-in superadmin). Re-checks **26 Aug 2026**.  
**Ideal:** HTML TTFB **under 800ms** (Google “good” TTFB). **Poor:** over **2.5s** (Google LCP “good” bar).

Times are **server HTML render only**. A real browser load is slower (CSS/JS/TinyMCE). `php artisan serve` is single-threaded, so any overlapping request can add wait time on top of the numbers below.

---
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
| Invoice list | `/clients/invoicelist` | 1.5s |
| Client receipts | `/clients/clientreceiptlist` | 1.4s |
| Office receipts | `/clients/officereceiptlist` | 1.1s |
| Admin — Matter list | `/adminconsole/features/matter` | 1.0s |
| TR sheet | `/clients/sheets/tr` | 1.0s |
| Add lead | `/leads/create` | 1.0s |
| Actions completed | `/action_completed` | 0.9s |
| Admin — Sent emails | `/adminconsole/features/sent-emails` | 0.8–0.8s |
| Signature create | `/signatures/create` | 0.8s |

