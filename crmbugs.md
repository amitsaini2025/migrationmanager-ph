# CRM Bug Audit

**Date:** 2026-07-26  
**Deep review:** 2026-07-26 (same day) — each item re-checked against current `master` code; live DB used where noted (pgsql).  
**Branch audited:** `master`  
**Scope:** Full application — staff CRM, Client Portal API, Admin Console, public endpoints, Python sidecar wrappers  
**Method:** Area-by-area static code review, then deep re-verification of every listed finding (controllers, routes, services, views, JS; live DB for B1/E1). Bugs only — no fixes applied.  
**Excluded:** Pure tech debt, unimplemented features with no broken UI control, and stale plan items already fixed in code (e.g. `LeadAnalyticsService` already uses `Staff`).

### Deep-review changelog
| Change | Detail |
|--------|--------|
| A1 clarified | Cookie is Laravel-encrypted at rest; plaintext still ends up in the login form `value` |
| I3 corrected | Card path **does** verify Stripe status/amount; remaining bug is weak PI↔appointment binding |
| H2 corrected | Fallback amount is **last invoice line’s** max, not invoice sum / global max |
| B1 confirmed | Live pgsql session `NOW()` is `Australia/Sydney`, not UTC |
| E1 confirmed | Live DB: all 13 stages have `workflow_id=3` and stale `w_id=2` |
| D1 clarified | Requires `auth:admin` + role 1 (not unauthenticated) |
| N2 softened | Missing `client_id` is solid; staff/client ID collision is a secondary risk |
| Q1 severity | Downgraded Medium → Low (latent unless `unix://` URL is used) |
| I1 clarified | Public wallet route is worse than sanctum wallet route |
| C3 clarified | Miswired route never persists; not “updates without id” |
| Typos | Fixed `e..g.` → `e.g.` |

---

## Summary by severity

| Severity | Count |
|----------|------:|
| Critical | 10 |
| High | 22 |
| Medium | 17 |
| Low | 2 |
| **Total** | **51** |

---

## A. Authentication & identity

### A1. Critical — CRM “Remember Me” stores password in a cookie and prefills the login form
**Status:** Verified  
**What breaks:** Checking Remember Me queues a `password` cookie and the login form re-fills `value` from `Cookie::get('password')`. Anyone who can read the decrypted cookie or view page source after load obtains the staff password. Laravel’s session remember-token already exists; this custom cookie is unsafe and redundant.  
**Nuance:** `EncryptCookies` does not except this cookie, so the cookie is Laravel-encrypted at rest (not raw plaintext in DevTools). After framework decrypt, the password is still written into the HTML `value` attribute.  
**Evidence:** `app/Http/Controllers/Auth/AdminLoginController.php` ~120–126; `resources/views/auth/admin-login.blade.php` ~40; `app/Http/Middleware/EncryptCookies.php` (`$except` empty).  
**Reproduce:** Log in with Remember Me → reopen `/login` → password field prefilled from cookie.

### A2. Critical — Staff `/api/admin-login` writes `staff.id` into `refresh_tokens.user_id` (FK → `admins`)
**Status:** Verified  
**What breaks:** After the dedicated `staff` table, `adminLogin` authenticates `Staff` but inserts `user_id = $staff->id` into `refresh_tokens`, which foreign-keys to `admins.id`. Usual outcome: FK failure → 500 on login. If `staff.id` collides with an `admins.id`, insert can succeed and `/api/refresh` loads that row via `Admin::find()`, mixing identities (and then requires client-type/`cp_status` checks that staff will fail). Same pattern risk for `device_tokens`.  
**Evidence:** `app/Http/Controllers/API/ClientPortalController.php` ~189–199, ~567–572; `database/migrations/2025_09_11_230000_create_refresh_tokens_table.php` ~27–28.  
**Reproduce:** `POST /api/admin-login` with valid staff credentials (roles 1/12/13/16). Expect 500 on refresh-token insert, or wrong identity path on refresh if IDs collide.

### A3. High — Clients with `cp_status = 2` can log in but cannot refresh or reset password
**Status:** Verified  
**What breaks:** Login allows `cp_status` in `[1, 2]` (approval pending). Refresh and password reset require `cp_status == 1`. Pending clients get a token, then refresh fails and reset is blocked — inconsistent portal lifecycle.  
**Evidence:** `app/Http/Controllers/API/ClientPortalController.php` ~45–48 (login), ~572–581 (refresh), ~353–357 (reset).  
**Reproduce:** Client with `cp_status=2` → login succeeds → `POST /api/refresh` → 401 “no longer active”.

### A4. Medium — Inactive staff can still log into the CRM
**Status:** Verified  
**What breaks:** `AdminLoginController` uses `Auth::guard('admin')->attempt()` with no `status` / archive check. `Staff` has `scopeActive()` but no global scope; Auth does not apply it. Disabled staff (`status = 0`) can still authenticate if credentials are valid. Contrast: API `adminLogin` does filter `status = 1`.  
**Evidence:** `app/Http/Controllers/Auth/AdminLoginController.php` ~60–65.  
**Reproduce:** Set staff `status = 0` → log in at `/login` → session created.

---

## B. Cross-access / client visibility

### B1. High — Grant expiry uses UTC in PHP but session-local `NOW()` in SQL list filters
**Status:** Verified (live DB)  
**What breaks:** Grants are written/checked with `Carbon::now('UTC')`, while list/document/booking restrictions use `ends_at > NOW()`. App timezone is `Australia/Melbourne`. Live environment: `DB_CONNECTION=pgsql`, session `TIMEZONE=Australia/Sydney`, so `NOW()` returns local (+10), not UTC. Active grants can disappear from lists while `hasActiveGrant()` / detail access still treat them as valid (or the reverse) near expiry boundaries.  
**Evidence:** `app/Services/CrmAccess/CrmAccessService.php` ~88–96; `app/Support/StaffClientVisibility.php` ~322–325 (and similar `NOW()` filters elsewhere); `config/app.php` timezone; live `current_setting('TIMEZONE')`.  
**Reproduce:** Non-exempt staff takes quick access → client detail works → reload restricted client list near grant expiry window; list SQL and PHP grant check can disagree.

### B2. Medium — Cross-access grants can be created for super-admin-only locked clients
**Status:** Verified  
**What breaks:** `AccessGrantController::quick` / `supervisor` do not block super-admin-only locked file IDs. A grant is created, but `canAccessClientOrLead` still denies non–role-1 users because the locked check runs before grant checks. Staff get success then still cannot open the record (confusing UX, not privilege escalation).  
**Evidence:** `AccessGrantController` ~58–70 / ~93–105 (no locked-client check) vs `StaffClientVisibility::canAccessClientOrLead` ~551–553 then grants ~563–566.  
**Reproduce:** As non–super-admin with quick access, request grant on a locked client → API success → open detail → unauthorized.

---

## C. Clients (core CRM)

### C1. Critical — Client/lead merge soft-deletes the target and leaves the source
**Status:** Verified  
**What breaks:** UI says merge A **into** B (`merge_from=A`, `merge_into=B`). Code soft-deletes **B**, copies A’s related rows **onto B**, and never deletes A. Survivor B is deleted; A remains; B’s original related data is not moved to A; A’s related data is duplicated onto a deleted record. Data corruption for anyone using Merge. No visibility/auth check beyond being logged in.  
**Evidence:** `resources/views/crm/clients/index.blade.php` ~803–809; `app/Http/Controllers/CRM/ClientsController.php` ~3771–3780.  
**Reproduce:** Clients list → check A then B → Merge → confirm. B is `is_deleted=1`; A still active; related rows copied onto deleted B.

### C2. High — Company edit page JS throws on `editClientForm` null
**Status:** Verified  
**What breaks:** Company edit includes `#emailAddressesContainer` but form id is `editCompanyForm`. Shared `edit-client.js` does `document.getElementById('editClientForm').addEventListener(...)` with no null check when the email container exists → `TypeError`, which can abort later DOMContentLoaded handlers. Remove phone/email also appends delete markers only to `editClientForm`. Some other paths already fall back to `editCompanyForm || editClientForm`; the email-container submit listener and delete-marker paths do not.  
**Evidence:** `public/js/clients/edit-client.js` ~5052–5067, ~2544; `resources/views/crm/clients/company_edit.blade.php` (`editCompanyForm` ~152, `#emailAddressesContainer` ~1025, loads `edit-client.js` ~1070).  
**Reproduce:** Open company client edit → console TypeError on `editClientForm`. Remove an email/phone on company edit → same null dereference.

### C3. High — `POST /clients/edit` (`clients.update`) never updates and gets no client id
**Status:** Verified  
**What breaks:** Route posts to `/clients/edit` with no `{id}`; both GET and POST hit `edit($id)`. On POST, `$id` is null → redirect unauthorized. Method only renders the edit view; it never persists (even if a hidden `id` is posted). Forms still `action="{{ route('clients.update') }}"`. Section AJAX saves work; native submit (Enter in a field, etc.) fails and saves nothing.  
**Evidence:** `routes/clients.php` ~46–47; `ClientsController::edit` ~1962–1991. Documented in `docs/COMPANY_EMPLOYER_SPONSORSHIP_IMPLEMENTATION_PLAN.md`.  
**Reproduce:** On personal or company edit, submit via normal POST (not section Save) → redirect to `/clients` with unauthorized; no data saved.

### C4. Low — Detail page receipt upload hint can throw if hint element missing
**Status:** Verified  
**What breaks:** After selecting a client-receipt file, code sets `hintElement.textContent` without checking that `.file-selection-hint` exists → `TypeError` if markup is absent.  
**Evidence:** `public/js/crm/clients/detail-main.js` ~783–813.  
**Reproduce:** Detail view where file input exists but `.file-selection-hint` does not → select file → console TypeError.

---

## D. Leads

### D1. Critical — `GET /leads/convert` mass-converts up to 500 leads
**Status:** Fixed  
**What breaks:** `convertToClient` ignores request body/IDs, loads `Lead::withArchived()->paginate(500)`, and converts every row. Route is `GET` under `auth:admin`; controller also requires `role == 1`. A single browser hit (or CSRF-unsafe GET) is a destructive mass conversion.  
**Evidence:** `app/Http/Controllers/CRM/Leads/LeadConversionController.php` ~32–54; `routes/web.php` ~248.  
**Reproduce:** As role 1, visit `/leads/convert`.  
**Fix:** Removed unused `GET /leads/convert` route and the mass `convertToClient` controller action. Single (`POST /leads/convert-single`) and bulk (`POST /leads/bulk-convert` with `lead_ids`) remain.

---

## E. Matters & workflows

### E1. High — “Australian Education” workflow has no stages on `workflow_id`
**Status:** Verified (live DB)  
**What breaks:** Live DB has workflows id=2 (Australian Education) and id=3 (General). **All 13 stages** have `workflow_id=3` and stale `w_id=2`. Modern stage code filters by `workflow_id`; matters on workflow 2 get no next/prev stage. Legacy `w_id` paths still see stages under the wrong key. Migrations alone only seed General; Australian Education stage linkage is data state, confirmed present.  
**Evidence:** Live query of `workflows` / `workflow_stages`; `ClientPortalController` modern queries ~3724–3728; legacy `w_id` usage ~3599–3601, ~3639–3641, ~4572–4574.  
**Reproduce:** Matter with `workflow_id=2` → Next Stage → “Already at the last stage” / no stages.

### E2. High — Signing auto-advances workflow without checklist gates
**Status:** Verified  
**What breaks:** On successful public sign, if the document has `client_matter_id`, the matter’s stage advances to the next stage (unless named “Decision Received”). This skips `WorkflowV2Display::outstandingRequiredForCurrentStage()` used by staff next-stage. Signing can jump the workflow past incomplete required checklists.  
**Evidence:** `PublicDocumentController.php` ~704–720 vs `ClientPortalController::updateClientMatterNextStage` ~3739–3750.  
**Reproduce:** Matter on stage with outstanding required checklist → sign a linked document → stage advances anyway.

### E3. Medium — Stage transitions ignore discontinued status
**Status:** Verified  
**What breaks:** `updateClientMatterNextStage` / `updateClientMatterPreviousStage` / `changeClientMatterWorkflow` / `completeWorkflowChecklist` do not check `matter_status`. Discontinued matters (`matter_status=0`) can still be advanced, rolled back, or have checklists completed.  
**Evidence:** `ClientPortalController.php` ~3680–3784, ~3913–3970, ~4094–4139, ~5076–5111.  
**Reproduce:** Discontinue matter → call next-stage / previous-stage / complete checklist with that `matter_id`.

### E4. Medium — Assignee update does not verify matter belongs to client
**Status:** Verified  
**What breaks:** `updateClientMatterAssignee` authorizes via request `client_id`, then updates `ClientMatter` by `selectedMatterLM` without ensuring that matter’s `client_id` matches. A staff user can reassign another client’s matter while passing a client they can access.  
**Evidence:** `ClientPersonalDetailsController.php` ~763–787.  
**Reproduce:** `POST /clients/updateClientMatterAssignee` with accessible `client_id` + another client’s `selectedMatterLM`.

### E5. Medium — Previous-stage progress % counts all workflows
**Status:** Verified  
**What breaks:** Next-stage progress scopes by `workflow_id`; previous-stage uses unscoped `WorkflowStage::count()` / global comparisons, so progress % after “previous” is wrong with multiple workflows.  
**Evidence:** `ClientPortalController.php` ~3975–3979 vs ~3789–3797.  
**Reproduce:** Multi-workflow DB → move previous stage → inspect returned `progress_percentage`.

---

## F. Documents & checklists

### F1. Medium — Move document does not scope categories to the document’s client
**Status:** Verified  
**What breaks:** `moveDocument` loads `PersonalDocumentType` / `VisaDocumentType` / `NominationDocumentType` by ID only. A category from another client (or unrelated matter) can be applied. Visa path validates `target_matter_id` against the document’s client, but not that the category belongs to that client/matter.  
**Evidence:** `ClientDocumentsController::moveDocument` ~2224–2283.  
**Reproduce:** Move a doc using another client’s category `target_id`.

---

## G. Electronic signatures

### G1. Critical — Cost-agreement signing accepts any token (auth bypass)
**Status:** Verified  
**What breaks:** For `doc_type === 'agreement'`, `/sign/{id}/{token}` does not require the URL token to match the stored signer token. It overwrites the pending signer’s token with whatever is in the URL (any ≥32 alphanumeric chars), then renders the signing UI. An attacker who knows/guesses an agreement document ID can open and complete signing without the emailed link. Non-agreement docs correctly look up by token.  
**Evidence:** `app/Http/Controllers/PublicDocumentController.php` ~65–78.  
**Reproduce:** Create/send pending agreement → open `/sign/{id}/{any32+alphanumeric}` → signing page loads; submit uses the newly written token.

### G2. Critical — Public PDF page endpoint has no token check
**Status:** Verified  
**What breaks:** `GET /documents/{id}/page/{page}` is public and loads page images with only the document ID. The signing view embeds these URLs with no token. Anyone who can enumerate IDs can render confidential PDFs.  
**Evidence:** `routes/documents.php` ~171–172; `PublicDocumentController::getPage` ~782–792; `resources/views/documents/sign.blade.php` ~410. Confirmed public in `route:list`.  
**Reproduce:** Open `/documents/{knownId}/page/1` while logged out.

### G3. High — Public signed-download route names overwritten / broken
**Status:** Verified  
**What breaks:** Public routes `public.documents.download.signed` and `public.documents.download_and_thankyou` are registered, then the same URIs are re-registered under `auth:admin` with different names. Final route table has **no** `public.documents.download.*` names; URI `/documents/{id}/download-signed` requires admin auth. Code/views that call the public names throw `Route [public.documents.download.signed] not defined`. Local-file thank-you download path is broken.  
**Evidence:** `routes/documents.php` ~178–182 vs ~244–248; `PublicDocumentController.php` ~1029; `resources/views/documents/index.blade.php` ~56; confirmed via `php artisan route:list`.  
**Reproduce:** Sign a doc stored under local `/storage/…`, or render any view calling `route('public.documents.download.signed', …)`.

### G4. High — Attach-to-client writes wrong `doc_type` values
**Status:** Verified  
**What breaks:** `SignatureService::associateWithCategory()` sets `doc_type` to `visa_documents` / `personal_documents`. Client document tabs query `doc_type = 'visa'|'personal'`. Attached signed docs do not appear in Personal/Visa tabs. `folder_name` is also never set, so category grouping would still fail even if types were fixed.  
**Evidence:** `app/Services/SignatureService.php` ~457–467; tabs in `personal_documents.blade.php` / `visa_documents.blade.php`.  
**Reproduce:** Signature dashboard → Attach → Personal/Visa → check client document tabs (doc missing).

### G5. Medium — Unauthenticated debug / test / converter endpoints
**Status:** Verified  
**What breaks:** Outside `auth:admin`: `/debug-pdf-page/{id}/{page}` (renders any doc page), `/test-signature`, and `/doc-to-pdf*` (convert/debug). Document content and tooling exposure without login.  
**Evidence:** `routes/documents.php` ~43–52, ~85–150.  
**Reproduce:** Hit those URLs logged out.

---

## H. Billing / accounts

### H1. Critical — Client portal invoice update marks invoices fully paid without payment verification
**Status:** Verified  
**What breaks:** `ClientPortalBillingController::updateInvoice` (sanctum-authenticated) trusts client-supplied `payment_status: "completed"` and an opaque `payment_token`, then calls `markFullyPaidFromClientPortal` (zeros balances / sets invoice paid) with no Stripe (or other gateway) verification. Authenticated clients can zero their own portal invoices.  
**Evidence:** `app/Http/Controllers/API/ClientPortalBillingController.php` ~144–206; `app/Services/InvoicePaymentSyncService.php` ~249–304; route `POST /api/billing/invoice-update` under `auth:sanctum`.  
**Reproduce:** Auth as portal client → POST `payment_status: completed` + any token for a sent unpaid invoice they own.

### H2. High — Void invoice can reverse the wrong fee transfers
**Status:** Verified  
**What breaks:** If Method 1 (by `invoice_no`) finds nothing, Method 2 matches fee transfers by `withdraw_amount = $invoiceAmount` and allows loose `invoice_no` matching (including null/empty paths). `$invoiceAmount` is overwritten per line as `max(partial_paid, withdraw)` and ends as the **last processed line’s** amount — not the invoice sum and not a global max — so multi-line invoices often miss the real transfer and/or match unrelated same-amount transfers.  
**Evidence:** `ClientAccountsController::void_invoice` ~4120–4214.  
**Reproduce:** Void a multi-line invoice whose fee transfer is linked poorly / by amount fallback → wrong or no transfer reversed.

### H3. High — Void invoice does not reverse office receipts; payments become orphaned
**Status:** Verified  
**What breaks:** Void zeros invoice lines and may void fee transfers, but office receipts (`receipt_type = 2`) for that `invoice_no` stay. After void, invoice lines are excluded from payment sync (`void_invoice` filter), so office money remains allocated to a voided invoice with no automatic reversal or reallocation.  
**Evidence:** `void_invoice` ~4077–4313; `InvoicePaymentSyncService::invoiceLinesBaseQuery` ~387–390.

### H4. High — Delete receipt does not recalculate client-fund balances
**Status:** Verified  
**What breaks:** After deleting a receipt, balance adjustment is an empty placeholder comment. Running balances on remaining ledger rows stay wrong.  
**Evidence:** `ClientAccountsController::delete_receipt` ~4757–4767.

### H5. High — Receipt ID allocation race (max+1 without lock)
**Status:** Verified  
**What breaks:** Client-fund, office, and other receipt paths do `orderBy receipt_id desc → +1` with no advisory lock (unlike invoice numbers which use `GET_LOCK` — itself MySQL-oriented on a pgsql app). Concurrent saves can collide on `receipt_id`.  
**Evidence:** `ClientAccountsController.php` ~323–324, ~1888–1889, ~2722–2725 (contrast locked `createInvoiceNumber` ~1007–1034).

### H6. Medium — `void_invoice` null-dereferences when a clicked ID has no row
**Status:** Verified  
**What breaks:** After a partial `whereIn` update (`affectedRows > 0`), the loop still processes every clicked ID. Missing `invoice_info` → `$invoice_info->client_id` fatal error mid-void.  
**Evidence:** `ClientAccountsController.php` ~4087–4102.

### H7. Medium — `printPreview` crashes on missing receipt
**Status:** Verified  
**What breaks:** Uses `if ($record_get)` on an Eloquent/Query Collection (always true as object), then accesses `$record_get[0]` even when empty → error / undefined vars into PDF.  
**Evidence:** `ClientAccountsController.php` ~4795–4812.

### H8. Medium — `sendToHubdoc` skips CRM record access check
**Status:** Verified  
**What breaks:** Loads invoice by `receipt_id` and emails PDF to Hubdoc without `ensureCrmRecordAccess`, unlike `genInvoice`. Staff outside visibility rules can send another client’s invoice.  
**Evidence:** `ClientAccountsController::sendToHubdoc` ~5673–5690.

---

## I. Payments / Stripe

### I1. Critical — Wallet payment endpoints mark appointments paid without verifying Stripe
**Status:** Verified  
**What breaks:** `recordAppointmentPaymentWallet` (`auth:sanctum`) and `recordAppointmentPaymentWithoutLoginWallet` (**public**) accept any `payment_intent_id` string and immediately write `status = succeeded` / `payment_status = completed` with no `PaymentIntent::retrieve`, amount check, or status check. Contrast: non-wallet paths call `$stripeService->recordPaymentByIntent(...)`.  
**Impact nuance:** Sanctum wallet is scoped to the user’s appointment; **public without-login wallet can mark any known `appointment_id` paid** with a forged PI id.  
**Evidence:** `ClientPortalAppointmentController.php` ~2551–2580, ~2865–2894; `routes/api.php` ~68 (public), ~271 (sanctum).  
**Reproduce:** `POST /api/appointments/record-payment-without-login-wallet` with `{appointment_id, payment_intent_id: "pi_fake", payment_type: "gpay"}` → appointment marked paid.

### I2. Critical — Unauthenticated PaymentIntent creation with arbitrary amount/currency
**Status:** Verified  
**What breaks:** Public `POST /api/payments/create-payment-intent` creates Stripe PaymentIntents from caller-controlled `amount` (min 50 cents), defaults currency to **USD** (not AUD), and returns `client_secret`. No appointment binding or auth (only API throttle). Enables charge creation / abuse of the Stripe account; combined with I1/I3 worsens free-paid or amount-mismatch flows.  
**Evidence:** `routes/api.php` ~70–117.

### I3. High — Public record-payment weakly binds PaymentIntent to appointment
**Status:** Verified (description corrected)  
**What breaks:** `recordAppointmentPaymentWithoutLogin` **does** verify via Stripe (`PaymentIntent::retrieve`, status must be `succeeded`, amount must match). Remaining weaknesses: endpoint is public; appointment looked up by id only; metadata `appointment_id` is checked **only if present** on the PI. Any succeeded PI of matching amount (e.g. from I2) can mark another unpaid appointment paid (griefing / reassignment). Stronger than wallet paths (I1); weaker than ideal binding.  
**Evidence:** `StripePaymentService::recordPaymentByIntent` ~495–527; `ClientPortalAppointmentController::recordAppointmentPaymentWithoutLogin` ~2724–2728; `routes/api.php` ~67.  
**Reproduce:** Create a succeeded PI for amount X via public create-intent → attach it to a different unpaid appointment with the same amount via without-login record-payment.

### I4. Medium — `processPayment` rolls back then tries to update the payment row
**Status:** Verified  
**What breaks:** On card/API errors, `DB::rollBack()` undoes `AppointmentPayment::create`, then `$payment->update([... 'failed' ...])` runs against a rolled-back row — failure audit trail is lost. Reliability/accounting bug, not free mark-paid.  
**Evidence:** `StripePaymentService.php` ~140–153, ~181–192, ~232–243, ~256–265.

### I5. Medium — PaymentIntent amount uses truncating cast (cent rounding bug)
**Status:** Verified  
**What breaks:** Appointment `createPaymentIntent` uses `(int) ($amount * 100)` while public/record paths use `round()`. Floating point (e.g. `19.99 * 100`) can undercharge by 1 cent vs CRM amount checks. Lower practical impact if amounts are always whole dollars.  
**Evidence:** `StripePaymentService.php` ~333–336 vs ~426, ~506.

---

## J. Appointments / Bansal sync

### J1. High — Successful payments never push status to Bansal
**Status:** Verified  
**What breaks:** After Stripe recording succeeds, code only `Log::info('... should sync with Bansal API')` inside a try/catch that never calls `syncStatus` / `updateAppointmentStatus`. CRM shows paid; website can stay unpaid. Same pattern on auth and public record-payment paths. Public pay-by-link also never syncs. (Other non-payment status-update paths do sync.)  
**Evidence:** `ClientPortalAppointmentController.php` ~2411–2417, ~2734–2740, ~2902–2908; `PublicAppointmentPaymentController.php` (no Bansal calls).

### J2. High — Sync skips existing appointments — payment/status drift permanently
**Status:** Verified  
**What breaks:** `processAppointment` returns `'skipped'` when `bansal_appointment_id` already exists, with no update of payment status, datetime, or cancellation. Cron `SyncBansalAppointments` cannot heal CRM after website payment or status changes.  
**Evidence:** `app/Services/BansalAppointmentSync/AppointmentSyncService.php` ~137–143.

### J3. Medium — `mapStatus` has no default — unknown Bansal status aborts that appointment
**Status:** Verified  
**What breaks:** PHP `match` without `default` throws `UnhandledMatchError`, counted as sync failure for that item.  
**Evidence:** `AppointmentSyncService.php` ~335–344.

---

## K. Email

### K1. High — SendGrid event webhook authorizes all requests when token env is empty
**Status:** Verified  
**What breaks:** If `SENDGRID_WEBHOOK_TOKEN` is null/empty, `authorizeRequest` returns `true`. Anyone can POST forged delivery events and mutate `EmailLog` delivery status.  
**Evidence:** `SendGridWebhookController.php` ~43–52; `config/services.php` ~40.  
**Note:** Impact is delivery-status forgery when env is unset; ensure prod always sets the token.

### K2. Medium — Checklist “sent” activity logged before the email is actually sent
**Status:** Verified  
**What breaks:** Activity logs and checklist-sent side effects run after `EmailLog` save but before `emailService->sendEmail`. On send failure, CRM history claims checklist was sent while `delivery_status` becomes `send_failed`.  
**Evidence:** `CRMUtilityController::sendmail` ~1399–1442 vs send ~1584–1611.

---

## L. SMS

### L1. High — Twilio/Cellcast webhooks accept unauthenticated status updates
**Status:** Verified  
**What breaks:** Public webhook routes update `SmsLog` from request body with no Twilio signature / Cellcast auth check. Attackers can forge delivery status (status forgery, not account takeover).  
**Evidence:** `routes/sms.php` ~20–28; `SmsWebhookController.php` ~21–98.

---

## M. CRM Sheets (EOI/ROI, ART, visa-type)

### M1. Critical — Cross-client EOI IDOR — association checks commented out
**Status:** Verified  
**What breaks:** `ClientEoiRoiController` disables AdminPolicy auth and explicitly comments out `client_id` ownership checks on show/update/delete/reveal-password/verify/send-email. Any authenticated staff (`auth:admin`) can load, mutate, delete, or decrypt EOI passwords for another client’s EOI by ID under a different `{client}` URL.  
**Evidence:** `app/Http/Controllers/CRM/ClientEoiRoiController.php` ~47–48, ~83–92, ~146–152, ~224–233, ~320–329, ~366–375.  
**Reproduce:** As staff, `GET /clients/{clientB}/eoi-roi/{eoiOwnedByClientC}/reveal-password` (or delete/update).

### M2. High — Public EOI confirm/amend has no server-side status/token lifecycle
**Status:** Verified  
**What breaks:** `processClientConfirmation` never checks existing status, never expires/invalidates the token, and never rejects replay. UI disables the button only; a crafted POST can re-confirm or flip amendment after confirmation, re-firing staff notifications. Token remains valid forever for GET pages.  
**Evidence:** `EoiRoiSheetController.php` ~853–906; view disable only in `eoi-client-confirmation.blade.php` ~234–236.  
**Reproduce:** Open confirm link → Confirm → POST again with `action=confirm` or `amend` using same token.

### M3. Medium — Null dereference if EOI client missing or confirmation date null
**Status:** Verified  
**What breaks:** Blade uses `$eoi->client->first_name` and `$eoi->client_last_confirmation->format(...)` without null-safe access. Deleted/missing client or status set without date → 500.  
**Evidence:** `resources/views/crm/clients/sheets/eoi-client-confirmation.blade.php` ~160–174.

---

## N. Client portal API (messages / documents / realtime)

### N1. High — `sendMessage` does not verify matter ownership
**Status:** Verified  
**What breaks:** Client can POST any `client_matter_id`; code loads the matter and messages that matter’s staff with no `client_matters.client_id === auth id` check. Cross-matter spam / leaking presence into other matters’ threads. (Staff→client `sendMessageToClient` is a different method and uses the matter’s `client_id` as recipient.)  
**Evidence:** `app/Http/Controllers/API/ClientPortalMessageController.php` ~321–417.  
**Reproduce:** Auth as client A → `POST /api/messages/send` with client B’s `client_matter_id`.

### N2. High — API broadcasting auth omits `client_id`
**Status:** Verified  
**What breaks:** `/api/broadcasting/auth` authorizes `private-matter.{id}` only via `sel_migration_agent|responsible|assisting` (staff FKs), not `client_id`. Legitimate clients cannot subscribe to their matter channels on the API path. Contrast: `routes/channels.php` includes `client_id`.  
**Secondary risk:** After staff/admin ID split, numeric ID overlap could theoretically let a client whose `admins.id` equals a staff assignee ID authorize as that assignee — plausible, not proven for every environment.  
**Evidence:** `routes/api.php` ~349–371; contrast `routes/channels.php` ~46–55.

### N3. Medium — Visa checklist/upload can attach arbitrary `client_matter_id`
**Status:** Verified  
**What breaks:** `addDocumentChecklist` does not verify the matter belongs to the authenticated client before writing `documents` with that matter id (scoped under the caller’s `client_id`). Creates inconsistent matter/document links.  
**Evidence:** `app/Http/Controllers/API/ClientPortalDocumentController.php` ~329–470.

---

## O. Agreements & Form 956

### O1. High — Wrong view names + destroy redirects to missing route
**Status:** Verified  
**What breaks:** Views live under `resources/views/crm/forms/*`, but controller returns `forms.index` / `forms.show` / `forms.create`. `edit` correctly uses `crm.forms.edit`. `destroy` redirects to `route('forms.index')` which is not registered (only store/show/edit/update/destroy/preview/pdf). Show/destroy/index break with View/RouteNotFound.  
**Evidence:** `Form956Controller.php` ~33–37, ~146–150, ~1083, ~1100–1105; views at `resources/views/crm/forms/`; routes in `routes/clients.php` ~378–384.

### O2. Medium — `create()` references removed `AgentDetails` class
**Status:** Verified  
**What breaks:** `use App\Models\AgentDetails` is commented out but `AgentDetails::first()` remains → fatal Error if `create()` is invoked.  
**Evidence:** `Form956Controller.php` ~6, ~43–46.

### O3. Medium — No per-record CRM access checks on show/edit/pdf/destroy
**Status:** Verified  
**What breaks:** Any authenticated staff can open/edit/delete/PDF any Form 956 by ID; no `StaffClientVisibility` / matter access (unlike sheets).  
**Evidence:** `Form956Controller.php` ~146–151, ~184+, ~1078–1105.

---

## P. Admin Console / ops

### P1. High — `service-account:generate-token` targets `Admin` (clients), not Staff
**Status:** Verified  
**What breaks:** Without `admin_id`, command runs `Admin::where('status', 1)->get()` — post staff-table migration that is essentially all active clients/leads, not staff. Wrong tokens / huge blast radius if the command is run that way. Both `Admin` and `Staff` have `HasApiTokens`; the bug is bulk targeting the wrong population, not that Admin can never hold a token.  
**Evidence:** `app/Console/Commands/ProcessServiceAccountTokens.php` ~36–51; also flagged in `docs/PLAN_DEDICATED_STAFF_TABLE.md`.

---

## Q. Python document services

### Q1. Low — `unix://` PYTHON_CONVERTER_URL is non-functional
**Status:** Verified (latent)  
**What breaks:** `isUnixSocket()` is detected but HTTP client still does normal `Http::get/post($this->apiUrl . '/health')`, which cannot talk to a Unix socket. Health checks and conversions fail when that URL scheme is configured. Default/docs mostly use `http://localhost:5000`, so this is latent unless deploy uses `unix://`.  
**Evidence:** `app/Services/PythonConverterService.php` ~23–37, ~124–128.  
**Severity note:** Downgraded from Medium → Low after deep review (code bug real; likely unused in current deploy).

---

## R. Areas reviewed with no confirmed live bugs in this pass

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

## Suggested fix priority (guidance only — not fixed in this audit)

1. Payment trust bugs (H1, I1, I2) — free mark-paid / open PaymentIntent; then tighten I3 binding  
2. E-sign auth bypass + PDF IDOR (G1, G2)  
3. Auth cookie / staff refresh-token FK (A1, A2)  
4. Client merge corruption (C1)  
5. EOI IDOR + public confirm lifecycle (M1, M2)  
6. Mass lead convert GET (D1)  
7. Bansal payment/status drift (J1, J2)  
8. Webhook auth gaps (K1, L1)  
9. Form 956 views/routes (O1–O3)  
10. Ledger void/receipt integrity (H2–H5)  
11. Remaining High/Medium items by area

---

## Verification summary

| Result | Items |
|--------|-------|
| Confirmed true | All 51 listed bugs (with clarifications above) |
| Description corrected | A1, I3, H2, B1, E1, D1, I1, C3, N2 |
| Severity changed | Q1 Medium → Low |
| Removed as false positive | None |
| Still excluded (already fixed / not bugs) | LeadAnalytics Admin query, `documents.thankyou` live path, bulk SMS stubs |

---

*End of audit. No code fixes were applied; this file is documentation only.*
