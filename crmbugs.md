# CRM Bug Audit

**Original audit:** 2026-07-26  
**Status review:** 2026-08-09 — full re-verification against current codebase (`master`); open items moved to top  
**Scope:** Full application — staff CRM, Client Portal API, Admin Console, public endpoints, Python sidecar wrappers  
**Method:** Area-by-area static code review, deep re-check of every finding (controllers, routes, services, views, JS). Bugs only.  
**Excluded:** Pure tech debt, unimplemented features with no broken UI control, and stale plan items already fixed in code (e.g. `LeadAnalyticsService` already uses `Staff`).

---

## Status overview

| Status | Count | Notes |
|--------|------:|-------|
| **Open / needs fix** | **5** | See [Open bugs](#open--needs-fixing) |
| **Fixed** | **46** | See [Fixed bugs](#fixed) |
| **Total tracked** | **51** | |

### Open by severity

| Severity | Open |
|----------|-----:|
| Critical | 2 |
| High | 3 |

### Deep-review changelog (2026-07-26)

| Change | Detail |
|--------|--------|
| A1 clarified | Cookie is Laravel-encrypted at rest; plaintext still ended up in the login form `value` |
| I3 corrected | Card path **does** verify Stripe status/amount; remaining bug was weak PI↔appointment binding |
| H2 corrected | Fallback amount is **last invoice line’s** max, not invoice sum / global max |
| B1 confirmed | Live pgsql session `NOW()` is `Australia/Sydney`, not UTC |
| E1 confirmed | Live DB: all 13 stages had `workflow_id=3` and stale `w_id=2` |
| D1 clarified | Required `auth:admin` + role 1 (not unauthenticated) |
| N2 softened | Missing `client_id` is solid; staff/client ID collision is a secondary risk |
| Q1 severity | Downgraded Medium → Low (latent unless `unix://` URL is used) |
| I1 clarified | Public wallet route is worse than sanctum wallet route |
| C3 clarified | Miswired route never persisted; not “updates without id” |

### Status review changelog (2026-08-09)

| Change | Detail |
|--------|--------|
| Document reorganized | Open items at top; fixed items below |
| Verification summary corrected | Prior footer listed B1/B2/J1–J3/P1 as open — all are **fixed** in code |
| **J1 regression found** | `processAppointmentPayment` still log-only for Bansal; other payment paths fixed |
| **H1 reclassified open** | Stripe verification exists but `STRIPE_ENFORCE_PORTAL_PAYMENT_VERIFICATION` defaults **false** |
| **C1 reclassified open** | Endpoint/UI mitigated; correct merge still not implemented |
| K1, L1 confirmed open | Webhooks fail-open when env secrets unset |

---

## Open / needs fixing

Sorted by severity, then ID.  
**Code re-verified:** 2026-08-09 (live `config()` also checked in this workspace).

---

### H1. Critical — Client portal invoice update can mark invoices paid without enforced payment verification

**Status:** Open — **verified**  
**What breaks:** `ClientPortalBillingController::updateInvoice` (Sanctum) accepts `payment_status: "completed"`. Verification via `rejectionReasonForPortalPayment()` → `StripePaymentService::verifyPaymentIntentForInvoice()` runs, but failures are **only rejected** when `STRIPE_ENFORCE_PORTAL_PAYMENT_VERIFICATION=true`. Config default is **false** (`config/services.php`), so authenticated clients can still zero portal invoices with a fake token unless prod sets the env var.  
**Evidence:** `app/Http/Controllers/API/ClientPortalBillingController.php` ~176–209 (on rejection: log + optional 422, then still calls `markFullyPaidFromClientPortal`); `config/services.php` `enforce_portal_payment_verification` → `env(..., false)`.  
**Live config (this workspace):** `config('services.stripe.enforce_portal_payment_verification') === false`.  
**Reproduce:** Auth as portal client → POST `payment_status: completed` + arbitrary `payment_token` for a sent unpaid invoice (with enforcement env unset).  
**Fix needed:** Set `STRIPE_ENFORCE_PORTAL_PAYMENT_VERIFICATION=true` in production and/or change default to `true`.

---

### C1. Critical — Client/lead merge corrupts data (feature disabled, not reimplemented)

**Status:** Open (mitigated) — **verified**  
**What breaks:** UI intended merge A **into** B (`merge_from=A`, `merge_into=B`). Legacy code soft-deletes **B**, copies A’s related rows **onto B**, and never deletes A — data corruption. Merge is hidden in UI and `merge_records` returns “temporarily disabled”, but corrupt implementation remains dead code after the early return.  
**Evidence:** `ClientsController::merge_records` ~3880–3888 early return `"Merge is temporarily disabled."`; dead soft-delete of `merge_into` still at ~3893–3894; UI commented in `resources/views/crm/clients/index.blade.php` ~322–324, ~769–802.  
**Exploitability now:** Not via UI/API (disabled). Risk returns if someone re-enables without rewriting.  
**Fix needed:** Implement correct merge (survivor, related-row moves, visibility checks) before re-enabling UI.

---

### K1. High — SendGrid event webhook authorizes all requests when token env is empty

**Status:** Open — **verified**  
**What breaks:** If `SENDGRID_WEBHOOK_TOKEN` is null/empty, `authorizeRequest` returns `true`. Anyone can POST forged delivery events and mutate `EmailLog` delivery status.  
**Evidence:** `SendGridWebhookController.php` ~43–52 (`return true` when token empty); public route `POST /webhooks/sendgrid/events` in `routes/sms.php` ~34–35; `config/services.php` `webhook_token` → `env('SENDGRID_WEBHOOK_TOKEN')` (no default).  
**Live config (this workspace):** SendGrid webhook token **unset** → fail-open is active here.  
**Reproduce:** Unset token → POST to webhook URL without credentials → authorized.  
**Fix needed:** Fail closed when token unset (or reject all requests in production).

---

### L1. High — Twilio/Cellcast webhooks accept unauthenticated status updates when secrets unset

**Status:** Open (partial — validates when configured; fail-open otherwise) — **verified**  
**What breaks:** Public webhook routes update `SmsLog` from request body. When Twilio auth token or Cellcast webhook credentials are empty, validators log a warning and **return true**. Attackers can forge delivery status.  
**Evidence:** Public routes `routes/sms.php` ~20–28; `SmsWebhookController.php` ~142–144 (Twilio empty → `return true`), ~179–180 (Cellcast empty → `return true`). Env keys: `TWILIO_TOKEN`, `CELLCAST_WEBHOOK_USERNAME`, `CELLCAST_WEBHOOK_PASSWORD`.  
**Live config (this workspace):** Twilio token unset; Cellcast webhook user/pass unset → fail-open active here.  
**Reproduce:** Hit webhook URLs logged out with empty provider secrets configured.  
**Fix needed:** Fail closed in production when secrets missing; document required env vars.

---

### J1. High — `processAppointmentPayment` does not push paid status to Bansal

**Status:** Open (partial — other payment paths fixed) — **verified**  
**What breaks:** After Stripe success on `POST /api/appointments/process-payment`, code only `Log::info('Payment successful - should sync with Bansal API')` inside try/catch — resolves unused `BansalApiClient` and **never** calls `syncAppointmentPaidWithBansal`. CRM shows paid; Bansal website can stay unpaid. Contrast: `recordAppointmentPayment`, wallet paths, without-login paths (~2411, ~2665, ~2795, ~2922) **do** call it.  
**Evidence:** `ClientPortalAppointmentController.php` ~2286–2304 (log-only) vs helper ~3060–3068 (`syncStatus(..., 'paid')`).  
**Reproduce:** Pay via `POST /api/appointments/process-payment` → CRM paid → Bansal unchanged.  
**Fix needed:** Replace log-only block with `$this->syncAppointmentPaidWithBansal($appointment)` (same as sibling methods).

---

## Fixed

Grouped by area. Items previously listed as open in the 2026-07-26 footer (B1, B2, J2, J3, P1, etc.) are confirmed fixed below.

---

## A. Authentication & identity

### A1. Critical — CRM “Remember Me” stored password in cookie and prefilled login form

**Status:** Fixed  
**Fix:** Forgets `password` cookie on login/show; Remember Me persists email only; password input has no cookie prefill.  
**Evidence:** `AdminLoginController.php` ~83–84, ~135–140; `admin-login.blade.php` ~40 (no `Cookie::get('password')`).

### A2. Critical — Staff `/api/admin-login` wrote `staff.id` into `refresh_tokens.user_id` (FK → `admins`)

**Status:** Fixed  
**Fix:** Inserts `user_type = staff`; migration `2026_07_27_120000_add_user_type_to_refresh_and_device_tokens.php` adds `user_type` and drops admins-only FK; refresh branches on `user_type`.  
**Evidence:** `ClientPortalController.php` ~189–199, refresh ~577+.

### A3. High — Clients with `cp_status = 2` could log in but not refresh or reset password

**Status:** Fixed  
**Fix:** `allowedClientPortalStatuses()` `[1, 2]` used for login, refresh, forgot, and reset.  
**Evidence:** `ClientPortalController.php` ~45–48, ~429, ~865+.

### A4. Medium — Inactive staff could still log into the CRM

**Status:** Fixed  
**Fix:** After attempt, logs out when staff `status === 0` (allows `1` and legacy `NULL`).  
**Evidence:** `AdminLoginController.php` ~68–74.

---

## B. Cross-access / client visibility

### B1. High — Grant expiry used UTC in PHP but session-local `NOW()` in SQL list filters

**Status:** Fixed  
**Fix:** All grant list/restriction filters in `StaffClientVisibility` use `Carbon::now('UTC')`, matching `hasActiveGrant`.  
**Evidence:** `StaffClientVisibility.php` ~251, ~325+; `CrmAccessService.php` ~88–96.

### B2. Medium — Cross-access grants could be created for super-admin-only locked clients

**Status:** Fixed  
**Fix:** `CrmAccessService::denyIfSuperAdminOnlyLockedClient` blocks quick/supervisor create and approve.  
**Evidence:** `CrmAccessService.php` ~110, ~160, ~212, ~296+.

---

## C. Clients (core CRM)

### C2. High — Company edit page JS threw on `editClientForm` null

**Status:** Fixed  
**Fix:** `getClientEditForm()` prefers `#editCompanyForm` then `#editClientForm`; submit/delete-marker paths use it with null guards.  
**Evidence:** `public/js/clients/edit-client.js`; `company_edit.blade.php`.

### C3. High — `POST /clients/edit` never updated

**Status:** Fixed  
**Fix:** Route is `POST /clients/edit/{id}`; POST redirects with section-save guidance; persistence via `/clients/save-section`.  
**Evidence:** `routes/clients.php` ~46–49; `ClientsController::edit` ~1964–1973.

### C4. Low — Detail page receipt upload hint could throw if hint element missing

**Status:** Fixed  
**Fix:** Guards `hintElement` with early return when missing.  
**Evidence:** `public/js/crm/clients/detail-main.js` ~783–813.

---

## D. Leads

### D1. Critical — `GET /leads/convert` mass-converted up to 500 leads

**Status:** Fixed  
**Fix:** Removed unused `GET /leads/convert` route and mass `convertToClient` action. Single (`POST /leads/convert-single`) and bulk (`POST /leads/bulk-convert`) remain.  
**Evidence:** `routes/web.php` (no `/leads/convert`); `LeadConversionController.php`.

---

## E. Matters & workflows

### E1. High — “Australian Education” workflow had no stages on `workflow_id`

**Status:** Fixed (workaround)  
**Fix:** Australian Education workflow set inactive (`status=0`); admin list filters `status=1`. Re-enable only after stages linked to AE.  
**Evidence:** Live DB state noted in original audit; workflow admin filters.

### E2. High — Signing auto-advanced workflow without checklist gates

**Status:** Fixed  
**Fix:** Signing checks `outstandingRequiredForCurrentStage` and skips advance when outstanding > 0 (also skips discontinued).  
**Evidence:** `PublicDocumentController.php` ~701+.

### E3. Medium — Stage transitions ignored discontinued status

**Status:** Fixed  
**Fix:** `rejectIfClientMatterDiscontinued()` on next/prev/change workflow/complete checklist.  
**Evidence:** `ClientPortalController.php`.

### E4. Medium — Assignee update did not verify matter belongs to client

**Status:** Fixed  
**Fix:** Rejects when matter `client_id` ≠ request `client_id`.  
**Evidence:** `ClientPersonalDetailsController.php` ~763–787.

### E5. Medium — Previous-stage progress % counted all workflows

**Status:** Fixed  
**Fix:** Previous-stage progress/`isFirst` queries scoped by `workflow_id` like next-stage.  
**Evidence:** `ClientPortalController.php`.

---

## F. Documents & checklists

### F1. Medium — Move document did not scope categories to the document’s client

**Status:** Fixed  
**Fix:** Categories scoped to document’s `client_id` (plus shared null-client categories); visa matter ownership validated.  
**Evidence:** `ClientDocumentsController::moveDocument`.

---

## G. Electronic signatures

### G1. Critical — Cost-agreement signing accepted any token (auth bypass)

**Status:** Fixed  
**Fix:** All doc types require URL token to match stored signer; no overwrite path.  
**Evidence:** `PublicDocumentController.php` ~64–66.

### G2. Critical — Public PDF page endpoint had no token check

**Status:** Fixed  
**Fix:** Requires admin session or `?token=` matching a valid signer; signing view passes token.  
**Evidence:** `PublicDocumentController::getPage` ~785–801.

### G3. High — Public signed-download route names overwritten / broken

**Status:** Fixed  
**Fix:** Public route names kept; admin downloads use distinct `/admin/documents/...` URIs/names.  
**Evidence:** `routes/documents.php`.

### G4. High — Attach-to-client wrote wrong `doc_type` values

**Status:** Fixed  
**Fix:** Sets `doc_type` to `'visa'`/`'personal'` and writes `folder_name` when provided.  
**Evidence:** `SignatureService.php` ~457–473.

### G5. Medium — Unauthenticated debug / test / converter endpoints

**Status:** Fixed  
**Fix:** Routes sit inside `auth:admin` middleware group.  
**Evidence:** `routes/documents.php` ~76+.

---

## H. Billing / accounts

### H2. High — Void invoice could reverse the wrong fee transfers

**Status:** Fixed  
**Fix:** Improved void transfer matching (by invoice_no first; amount fallback corrected).

### H3. High — Void invoice did not reverse office receipts; payments became orphaned

**Status:** Fixed  
**Fix:** Void path handles office receipt reversal / sync.

### H4. High — Delete receipt did not recalculate client-fund balances

**Status:** Fixed  
**Fix:** Balance recalculation implemented after delete.

### H5. High — Receipt ID allocation race (max+1 without lock)

**Status:** Fixed  
**Fix:** Allocation serialised via `receipt_sequences` counter row.

### H6. Medium — `void_invoice` null-dereferenced when a clicked ID had no row

**Status:** Fixed  

### H7. Medium — `printPreview` crashed on missing receipt

**Status:** Fixed  

### H8. Medium — `sendToHubdoc` skipped CRM record access check

**Status:** Fixed  
**Fix:** Uses `ensureCrmRecordAccess` like sibling methods.

---

## I. Payments / Stripe

### I1. Critical — Wallet payment endpoints marked appointments paid without verifying Stripe

**Status:** Fixed  
**Fix:** Wallet paths call `recordWalletPayment` → `StripePaymentService::recordPaymentByIntent`; enforcement default **true** (`STRIPE_ENFORCE_WALLET_PAYMENT_VERIFICATION`).  
**Evidence:** `ClientPortalAppointmentController.php` ~2530+; `config/services.php`.

### I2. Critical — Unauthenticated PaymentIntent creation with arbitrary amount/currency

**Status:** Fixed  
**Fix:** With appointment, amount/currency from appointment (AUD); unbound intents rejected by default (`enforce_appointment_intent_binding` default **true**).  
**Evidence:** `routes/api.php`; `config/services.php`.

### I3. High — Public record-payment weakly bound PaymentIntent to appointment

**Status:** Fixed  
**Fix:** Rejects mismatched metadata; unbound/currency-mismatch rejected when enforcement on; claims unbound intents when allowed through cutover.  
**Evidence:** `StripePaymentService::recordPaymentByIntent`; `config/services.php`.

### I4. Medium — `processPayment` rolled back then tried to update the payment row

**Status:** Fixed  
**Fix:** After rollback, `recordFailedPayment()` creates a new failed row.

### I5. Medium — PaymentIntent amount used truncating cast (cent rounding bug)

**Status:** Fixed  
**Fix:** Uses `(int) round($amount * 100)` consistently.

---

## J. Appointments / Bansal sync

### J2. High — Sync skipped existing appointments — payment/status drift permanently

**Status:** Fixed  
**Fix:** Existing rows get selective payment/status/cancel updates via `updateExistingAppointmentPaymentAndStatus`; CRM-local paid not downgraded to unpaid pending.  
**Evidence:** `AppointmentSyncService.php` ~137–141, ~245+.

### J3. Medium — `mapStatus` had no default — unknown Bansal status aborted that appointment

**Status:** Fixed  
**Fix:** Normalize status casing/separators; map known aliases; unknown values default to `pending`.  
**Evidence:** `AppointmentSyncService.php` ~442–455.

---

## K. Email

### K2. Medium — Checklist “sent” activity logged before the email was actually sent

**Status:** Fixed  
**Fix:** Checklist activity logs and visa-sheet side effects run once on first successful `sendEmail`.  
**Evidence:** `CRMUtilityController::sendmail`.

---

## L. SMS

*(L1 is open — see [Open](#open--needs-fixing) section.)*

---

## M. CRM Sheets (EOI/ROI, ART, visa-type)

### M1. Critical — Cross-client EOI IDOR — association checks commented out

**Status:** Fixed (`ensureEoiBelongsToClient` on sensitive actions; AdminPolicy still TODO)  
**Fix:** Returns 404 when EOI `client_id` ≠ route client on show/upsert/destroy/reveal/verify/send-email.  
**Evidence:** `ClientEoiRoiController.php` ~86+, ~974+.

### M2. High — Public EOI confirm/amend had no server-side status/token lifecycle

**Status:** Fixed  
**Fix:** Rejects when status is already `confirmed` or `amendment_requested`; token kept for success page re-display only.  
**Evidence:** `EoiRoiSheetController.php` ~858–863.

### M3. Medium — Null dereference if EOI client missing or confirmation date null

**Status:** Fixed  
**Fix:** Null-safe `$eoi->client?->…` and conditional formatting when `client_last_confirmation` is null.

---

## N. Client portal API (messages / documents / realtime)

### N1. High — `sendMessage` did not verify matter ownership

**Status:** Fixed  
**Fix:** For `Admin` (client) callers, requires matter `client_id` to match auth id; otherwise 404.  
**Evidence:** `ClientPortalMessageController.php` ~374–381.

### N2. High — API broadcasting auth omitted `client_id`

**Status:** Fixed  
**Fix:** Clients authorize via `client_id`; staff via assignee columns; IDs not compared across tables.  
**Evidence:** `routes/api.php` ~442–473.

### N3. Medium — Visa checklist/upload could attach arbitrary `client_matter_id`

**Status:** Fixed  
**Fix:** Visa path checks `client_matters` exists with matching `client_id` before write.

---

## O. Agreements & Form 956

### O1. High — Wrong view names + destroy redirected to missing route

**Status:** Fixed  
**Fix:** Views use `crm.forms.*`; `forms.index` route registered; destroy redirects correctly.  
**Evidence:** `Form956Controller.php`; `routes/clients.php`.

### O2. Medium — `create()` referenced removed `AgentDetails` class

**Status:** Fixed  
**Fix:** `create()` uses logged-in staff (`Auth::user()`) as default agent.

### O3. Medium — No per-record CRM access checks on show/edit/pdf/destroy

**Status:** Fixed  
**Fix:** `assertCanAccessForm956` / `assertCanAccessClientId` via `StaffClientVisibility`.

---

## P. Admin Console / ops

### P1. High — `service-account:generate-token` targeted `Admin` (clients), not Staff

**Status:** Fixed  
**Fix:** Command targets `Staff` (`staff_id` / `--all`); bare run without id/`--all` errors instead of bulk-minting client tokens.  
**Evidence:** `ProcessServiceAccountTokens.php` ~43, ~66.

---

## Q. Python document services

### Q1. Low — `unix://` PYTHON_CONVERTER_URL was non-functional

**Status:** Fixed  
**Fix:** Unix-socket configs set `CURLOPT_UNIX_SOCKET_PATH` and request `http://localhost/…` over the socket.  
**Evidence:** `PythonConverterService.php` ~25–67.

---

## Areas reviewed with no confirmed live bugs

| Area | Notes |
|------|--------|
| Office visits / front desk | Access checks present; no clear logic crash found without DB nullability proof |
| ART / VisaType sheets | Mutating actions use `StaffClientVisibility` |
| Lead analytics | Plan doc is stale — code already uses `Staff` |
| Reports / chatbot / calculators | No clear broken routes/null bugs found in scoped review |
| Smart email import “TODO” in ClientsController | Incomplete feature path; not confirmed as live broken UX control |
| Bulk SMS / incoming SMS TODOs | Unimplemented features, not regressions |
| `documents.thankyou` redirect | Only in commented legacy code; live path uses `public.documents.thankyou` |

---

## Suggested fix priority (open items only)

1. **H1** — Enable portal payment verification in prod (`STRIPE_ENFORCE_PORTAL_PAYMENT_VERIFICATION=true`) or change default to enforced  
2. **J1** — Wire `processAppointmentPayment` to `syncAppointmentPaidWithBansal`  
3. **K1** — Fail closed SendGrid webhook when token unset  
4. **L1** — Fail closed SMS webhooks when provider secrets unset  
5. **C1** — Reimplement client/lead merge correctly before re-enabling UI  

---

## Production checklist (webhooks & payments)

| Env / setting | Risk if missing |
|---------------|-----------------|
| `SENDGRID_WEBHOOK_TOKEN` | K1 — forged email delivery events |
| Twilio auth token / Cellcast webhook credentials | L1 — forged SMS status |
| `STRIPE_ENFORCE_PORTAL_PAYMENT_VERIFICATION=true` | H1 — clients can mark invoices paid without verified Stripe PI |

---

*Audit documentation. Last status review: 2026-08-09. Fixed items verified in code; open items require code or configuration changes.*
