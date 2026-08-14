# CRM Email UI — Implementation Details

This document describes the **staff-facing email screens** in this CRM: compose, the client Emails tab, upload/import, templates, labels, and Admin Console lists. Use it as a spec to rebuild the same UI in another CRM.

**Sending / SMTP / webhooks are out of scope here.** How mail leaves the building does not matter for this rebuild. Related older docs:

| File | What it is |
|------|------------|
| `docs/CRM_EMAIL_SENDING.md` | **This file** — email UI |
| `docs/SENDGRID_CRM_EMAIL_INSTRUCTIONS.md` | Transport migration (historical) |
| `docs/CRM_EMAIL_S3_IMPLEMENTATION.md` | S3 archival of bodies/attachments |
| `docs/CRM_ACTIVITY_FEED.md` | Timeline (compose also writes an activity row) |

---

## 1. What this feature is

Staff work with email in **three places**:

1. **Compose** — a Bootstrap modal (`#emailmodal`, title “Compose Email”) on client / company / lead pages. From, To, CC, template, subject, TinyMCE body, file attachments, and a checklist-file picker.
2. **Client Emails tab** — Outlook-style two-pane mailbox (Inbox / Sent), search, labels, drag-drop `.eml` / `.msg` upload, reading pane, Reply / Reply All / Forward / Delete.
3. **Admin Console** — sender accounts, CRM/matter templates, email labels, Sent Emails, System Emails (read-only logs with delivery badges).

The client Emails tab is a **mailbox of logged mail** (uploaded files, synced mail, and sent compose). It is not a live IMAP client.

---

## 2. What this feature is not

| Surface | What it actually is |
|---------|---------------------|
| **Send Message** (`#sendmsgmodal`) | Same send endpoint, different modal: body-only “message”, not full compose |
| **Upload Mail** (`#uploadmail`) | Manual create of a logged email (From/To/Subject/body typed in). Does not send |
| **Portal / application compose** (`#matteremailmodal`) | Compose to the client’s email, To is **readonly name + hidden email**. Posts to `/client-portal/sendmail` |
| **Sheet Email Reminder** | Slim compose: To is a display-only name, no CC, no checklist table |
| **Accounts → Send invoice to client** | Not the compose modal; a dropdown action that sends a fixed invoice email |
| **Smart Email Import** | Standalone page: bulk `.eml` / `.msg` with suggested client/matter, then confirm |
| **Notes tab “Email” note type** | A file note, not outbound mail |

---

## 3. Where compose lives

Canonical markup: `resources/views/crm/clients/detail.blade.php` (`#emailmodal`).

The same pattern is copied (with small field differences) on:

| Page | Modal title | Notes |
|------|-------------|--------|
| Client detail | Compose Email | Full fields + checklist table. Envelope icon in header (`.clientemail`) opens it with To pre-filled |
| Company detail | Compose Email | Same structure |
| Lead detail / lead history | Compose Email | Same idea |
| Client list / lead list | Compose Email | Toolbar **Send Mail** (empty To) or row **Send Mail** (To = that person) |
| Visa sheet reminder | Email Reminder | No searchable To/CC; no checklist table |
| Checklists tab | (reuses `#emailmodal`) | Prefills To + matter; signature send also sets signing URL |

JS: `public/js/crm/clients/detail-main.js` (From/To/CC Tom Select, template change, `.clientemail` click). Reply/forward from the Emails tab: `public/js/emails.js` → `openComposeModal()`.

Send button: `saveComposeEmail()` saves TinyMCE then `customValidate('sendmail')`. Form posts to `clients.sendmail` (`POST /sendmail`). Rebuild can keep that contract or swap the backend.

---

## 4. Compose modal — field by field

Layout: `modal-lg`, two-column row, then full-width subject/body/attachments.

Hidden fields on client detail:

| Field | Purpose |
|-------|---------|
| `client_id` | Record the mail is about |
| `type` | `client` (lead/company variants differ) |
| `mail_type` | `1` |
| `mail_body_type` | `sent` |
| `compose_client_matter_id` | Current matter (sidebar matter picker or “general matter” checkbox) |
| `signing_url` | Service-agreement / PDF sign link for `{PDF_url_for_sign}` |

### From (required)

Empty `<select class="email-from-sendgrid" name="email_from">` (`partials/email-from-sendgrid.blade.php`). Options are filled in JS from `GET /crm/sendgrid-senders` (`partials/email-from-sendgrid-script.blade.php`). Staff pick a **display From**, not SMTP credentials.

### To (required)

Multi-select `name="email_to[]"` class `js-data-example-ajax`. Tom Select + AJAX search of CRM people (staff/client/agent style results: name, email, yellow status chip). Values are **ids**, not raw addresses.

Reply/forward / `.clientemail` destroy and re-init the control with preloaded `{id, name, email, status}` items (`initComposeEmailToField`). Closing compose resets it (`resetComposeEmailToField`).

### CC (optional)

Same AJAX search (`js-data-example-ajaxccd`, `email_cc[]`) **plus** create-on-blur for a raw email address.

### Templates (optional)

Select `.selecttemplate`. Initial options: all **CRM** templates (`EmailTemplate::crm()`), newest first, plus a blank “Select”.

On change: `GET` template by id (`ClientDetailConfig.urls.getTemplates`). Subject and TinyMCE body are replaced. Placeholders such as `{Client First Name}` are swapped using `data-client*` on the select and/or `composeMacroValues` loaded with the matter. `{PDF_url_for_sign}` becomes a “Sign Service Agreement” link when a signing URL is present.

Empty template value does **not** load a template (used so Reply/Forward does not wipe the quoted body).

### Subject / Message (required)

- Subject: `#compose_email_subject` (also class `selectedsubject`).
- Body: TinyMCE `#compose_email_message`. Re-inited whenever the modal is shown.

### Attachments

1. **File input** `attach[]` (multiple).
2. **Checklist table** `#mychecklist-datatable`: every `UploadChecklist` row with a checkbox `checklistfile[]`. Columns: checkbox, file name, link. When a matter is set, rows are **filtered** to that matter’s checklist ids; checkboxes start **unchecked**. Staff tick the files to attach.

Sheet reminder compose has (1) only.

Footer: **Send** + **Close**. Modal is `data-backdrop="static"` / `data-keyboard="false"`.

---

## 5. Behaviour when compose opens

`#emailmodal` `shown.bs.modal`:

1. Init TinyMCE.
2. Init template Tom Select. If **no** matter id, restore the original CRM template option list.
3. If a matter id **is** set, `GET` compose defaults (`getComposeDefaults` + `client_matter_id`):
   - Store `macro_values` on the modal (including `PDF_url_for_sign` if a signing URL was passed).
   - **Replace** the template dropdown with matter-specific options only: First Email first, then Matter Other Email Templates.
   - Unless `preserveReplyForwardBody` is set, auto-select the suggested template (First Email / first matter template) and trigger `change` (fills subject + body).
   - Filter checklist rows; uncheck all checklist boxes.
4. Reply/Forward sets `preserveReplyForwardBody` so quoted HTML is kept; template select is cleared without loading.

Matter id is set from:

- Client detail matter dropdown / general-matter checkbox (`.clientemail` click).
- Emails tab current matter (`emails.js`).
- Checklists tab (email reminder or signature send).

---

## 6. Other ways compose is pre-filled

| Trigger | Prefill |
|---------|---------|
| Header / row envelope (`.clientemail`) | To = that client; matter from sidebar |
| List **Send Mail** (`.emailmodal`) | Empty To |
| Emails tab **Reply** | To = original From; subject `Re:`; quoted body; From matched if possible |
| **Reply All** | To = From + other recipients |
| **Forward** | Empty or forwarded To; subject `Fwd:`; quoted body |
| Checklists **email** | To = client; matter; template after defaults |
| Checklists **signature send** | Same + `fromSignatureSend` + signing URL so First Email / sign macro applies |
| Sheet email reminder | To display-only; hidden `email_to[]`; `checklist_reminder_type=email` |

Context menu on a list item in the Emails tab also offers Reply, Forward, Apply Label, Delete.

---

## 7. Client Emails tab (Outlook layout)

Markup: `resources/views/crm/emails.blade.php`, included from `resources/views/crm/clients/tabs/emails.blade.php`. Styles: `public/css/emails.css`. Behaviour: `public/js/emails.js`.

Two panes:

```
[ Inbox | Sent ]   [ optional: Send All Email Body To S3 — role 1 only ]
[ drag-drop .eml / .msg upload ]
[ search ........ ] [ All Labels ▾ ]
[ email list + clip + labels + date ]
[ 1/1  ‹ › ]

                    [ Reply | Reply All | Forward | Delete? ]
                    Subject
                    Avatar  From / To / Cc     date
                    attachments
                    body in iframe
```

### List pane

- **Inbox / Sent** folder tabs (hidden `#mailTypeFilter` stays in sync for the API).
- Drop zone: Outlook files (`config('crm.email_upload_allowed_extensions')`, default `.msg`, `.eml`). Browse or drag. Progress + full-page overlay: “Do not close or refresh”.
- Before upload, **Save Attachments** modal: rename files, optional “Also save copies to Documents tab” + category.
- Duplicate file: Accept / Reject modal.
- Search box filters the loaded list.
- Label filter dropdown (populated from Admin labels).
- Each row: From, subject, preview snippet, colour label badges (icon + name), paperclip, date. Unread = bold / `.unread`. Active row highlighted.
- Pagination prev/next.

Empty state: “Upload … files above to get started” / reading pane “Select an item to read”.

### Reading pane

- Actions: Reply, Reply All, Forward. **Delete** only if staff role is in `config('crm.email_log_delete_role_ids')` (default 1, 12, 16). Confirm modal: `crm/partials/email_delete_confirm_modal`.
- Subject, avatar initial, From, To, Cc (Cc hidden if empty), date.
- Attachment chips + preview modal (iframe).
- Body in `#emailReadBody` iframe (not inline HTML in the page).

Right-click: Apply Label (submenu of unused labels), Reply, Forward, Delete.

**Delivery status badges are not on this tab.** They appear on Admin Sent / System Emails.

---

## 8. Upload Mail modal (legacy)

`resources/views/crm/clients/modals/emails.blade.php` → `#uploadmail`.

Fields: From, To, Subject, TinyMCE, **Create**. Posts to `/upload-mail`. This is a **typed-in log entry**, separate from drag-drop parse of Outlook files.

Same file also has `#matteremailmodal` (portal/application compose): From dropdown, To readonly client name, CC search, templates (`.selectmattertemplate`), subject, TinyMCE, attachments. Send: `saveApplicationEmail()`.

---

## 9. Smart Email Import

`resources/views/crm/emails/smart-import.blade.php`. Not on the client tab.

Staff drop up to 20 files (30 MB each), see suggested client and matter, then confirm. Same file types as the Emails tab drop zone.

---

## 10. Admin Console UI

Sidebar: `Elements/CRM/setting`. Pages:

### Sender accounts (`AdminConsole/features/emails`)

List of From identities. Create/edit: Email Id, Enable checkbox, Display Name, **User Sharing** (multi staff), HTML **Company Email Signature** (TinyMCE). These identities feed the compose From dropdown (together with verified SendGrid senders in this CRM).

### CRM Email Template

Table: Name, Subject, Action (Edit / Delete). Create: Name, Subject, Description (TinyMCE). These are the default compose template list when **no matter** is selected.

### Matter Email Template / Matter Other Email Template

Matter-scoped templates. Compose with a matter id **replaces** the CRM list with these (First Email first).

### Email Labels

CRUD for mailbox labels (name, colour, icon). Used by the Emails tab filter, list badges, and context-menu Apply Label.

### Sent Emails

Staff-composed outgoing logs only. Banner points System Emails elsewhere.

Filters: subject/from/to search, date from/to, sent-by staff, from address, client/lead picker, recipient type (client / lead / agent), has attachments.

Table: Date/time, Sent By, From, To, Subject (+ paperclip count), **Status badge**, Client link, Type badge, view.

Row view: delivery badge, spam-report icon if any, **event timeline** (Sent, then bounce/drop/defer/etc. with reason).

Dashboard: charts + same badges.

### System Emails

Same list/filter/show/dashboard pattern for **automated** mail (invoices, reminders, appointments, e-sign). Category filter instead of recipient type. Banner points Sent Emails for staff compose.

### Status chrome (Admin only)

- `partials/email-delivery-status-badge.blade.php` — pending / delivered / bounced / … Bootstrap badge + tooltip.
- `partials/email-engagement-icons.blade.php` — **spam-report only** (open/click tracking is off in this CRM).
- `partials/email-event-timeline.blade.php` — vertical timeline on the show page.

---

## 11. Rebuild checklist (UI)

Match these behaviours even if the backend is different:

- [ ] Compose modal: From, multi To (search CRM people), CC (search + free email), template, subject, rich body, multi file attach, matter checklist attach (filtered, unchecked).
- [ ] Opening compose with a matter swaps templates to matter templates and auto-loads First Email **unless** Reply/Forward.
- [ ] Template change overwrites subject + body and expands client/matter macros.
- [ ] Envelope on the person pre-fills To; list Send Mail does not.
- [ ] Emails tab: Inbox/Sent, search, labels, Outlook-file upload with attachment-rename + duplicate prompt, two-pane read, Reply/Reply All/Forward into the same compose modal with quoted body preserved.
- [ ] Role-gated delete on the mailbox.
- [ ] Admin: sender identities + signature, CRM vs matter templates, labels, Sent vs System email lists with delivery badge + timeline.
- [ ] Do **not** put open/click tracking icons on the mailbox; optional spam-report icon on Admin logs.

---

## 12. Core files (this CRM)

| Role | Path |
|------|------|
| Compose modal | `resources/views/crm/clients/detail.blade.php` (`#emailmodal`) |
| From select | `resources/views/partials/email-from-sendgrid.blade.php` + `-script` |
| Compose JS | `public/js/crm/clients/detail-main.js` |
| Emails tab shell | `resources/views/crm/emails.blade.php` |
| Emails tab JS/CSS | `public/js/emails.js`, `public/css/emails.css` |
| Upload / portal compose | `resources/views/crm/clients/modals/emails.blade.php` |
| Sheet reminder | `resources/views/crm/clients/partials/sheet-email-reminder-modal.blade.php` |
| Smart import | `resources/views/crm/emails/smart-import.blade.php` |
| Delivery badge / timeline | `resources/views/partials/email-*.blade.php` |
| Admin templates / labels / sent | `resources/views/AdminConsole/features/{crmemailtemplate,matteremailtemplate,matterotheremailtemplate,emaillabels,sent-emails,system-emails,emails}/` |
