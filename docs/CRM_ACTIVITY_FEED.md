# CRM Activity Feed — Implementation Details

This document describes how this CRM records staff/system actions on a **client or lead** and shows them as a chronological **Activity Feed** on the client/company detail page.

Use it as a spec to rebuild the same feature in another CRM. The feed’s table is `activities_logs`. That table is **not** the same thing as file notes or Actions (`notes`).

Logging is **explicit at call sites**. It is not a global observer, and it is **not** literally every mutation (several company-profile saves do not write a row).

---

## 1. What this feature is

A **client-scoped timeline**. After many successful writes — profile edits, notes, SMS, documents, workflow stage moves, invoices, office visits, lead conversion, Actions, and so on — code inserts a row into `activities_logs`.

The client and company detail pages show a right-hand **Activity Feed** sidebar (`#activity-feed`). It loads those rows newest-first via `GET /get-activities`.

Each feed item answers:

- **Who** (`created_by` → staff name; portal uploads may use the client id)
- **What** (`subject`, usually verb-first, usually without the client name)
- **Details** (`description`, often HTML: diffs, amounts, appointment chips)
- **When** (`created_at`)
- **Kind** (`activity_type` for icon, colour, CSS class, and filters)

The row is already tied to `client_id`, so Action/profile subjects should **not** embed the client display name. The UI prepends the actor: `Jane Smith updated basic information`.

Exceptions exist (see §6.3): some portal/signature subjects already name the signer; some stage subjects start with the matter number.

---

## 2. What this feature is not

Do not mix these up when porting.

| Feature | Table | Purpose |
|---------|--------|---------|
| **Activity Feed** (this doc) | `activities_logs` | Timeline of what happened on a client/lead |
| **Notes** (Notes tab) | `notes` (`is_action = 0`) | Staff file notes (Call, Email, In-person, Attention, Others) |
| **Actions** (legacy: task / followup) | `notes` (`is_action = 1`) | Assigned work items with due date and category |
| **Admin Console “Activity Search”** | `notes` (actions only) | Search/export **Actions**, not the activity feed |
| **Staff CRM presence** | `staff_login_logs` via `TrackStaffCrmActivity` | Daily “Active in CRM (session)” heartbeat, throttled 5 minutes. Not on the client feed |

Action assign/complete/delete **also** write `activities_logs` rows (subjects like `Set action for {assignee}`), but the Action itself lives in `notes`.

### Dual use of `activities_logs` (do not miss this)

The same table stores **matter workflow notes** (`activity_type = 'note'` and `use_for = 'matter'`), created from Client Portal `addNote`. Those rows are listed in matter-note UI **and** appear in the Personal Details feed, because the feed query is only `where client_id = ?`.

When porting, prefer **two tables** (audit log vs matter notes), or filter the feed with `where (use_for is null or use_for <> 'matter')` if you want a pure audit trail.

---

## 3. Architecture

```
Staff / system / portal action
        │
        ▼
┌──────────────────────────────────────────┐
│  Write path (explicit, after save)       │
│  LogsClientActivity helpers              │
│  or ActivitiesLog::create / new+save     │
│  or DB::table('activities_logs')->insert │
└──────────────────────────────────────────┘
        │
        ▼
┌──────────────────────────────────────────┐
│  activities_logs                         │
└──────────────────────────────────────────┘
        │
        ▼
GET /get-activities?id={clientId}&page=1&per_page=40
        │
        ▼
┌──────────────────────────────────────────┐
│  #activity-feed sidebar                  │
│  window.loadActivities() builds HTML     │
│  activity-feed.js: filters + scroll      │
└──────────────────────────────────────────┘
```

There is **no observer** that auto-logs Eloquent saves. If logging throws, most call sites catch and continue so the original save still succeeds. A few older writers (Action assign, cost forms) do **not** wrap logging in try/catch.

Default auth guard is `admin` (`config/auth.php`). The trait uses `Auth::user()->id ?? Auth::id()`, which resolves to staff under that guard.

### Core files (this CRM)

| Role | Path |
|------|------|
| Model | `app/Models/ActivitiesLog.php` |
| Write helper | `app/Traits/LogsClientActivity.php` |
| Feed API | `ClientsController::activities()` — `GET /get-activities` (`clients.activities`) |
| Pin / delete (legacy endpoints) | `ClientsController::pinactivitylog`, `deleteactivitylog` |
| Convert feed/completion text → note | `ClientsController::convertActivityToNote` — `POST /convert-activity-to-note` |
| Feed chrome | `resources/views/crm/clients/tabs/activity_feed.blade.php` |
| Activity nav stub | `resources/views/crm/clients/tabs/activityfeed_tab.blade.php` (empty; list is the sidebar) |
| Unused item partial | `resources/views/crm/clients/tabs/partials/_activity_item.blade.php` — **not included anywhere**. Live items are built in JS from JSON. |
| Filters / infinite scroll | `public/js/crm/clients/tabs/activity-feed.js` |
| Feed loader | `window.loadActivities` in `resources/views/crm/clients/detail.blade.php` and `companies/detail.blade.php` |
| Legacy alias | `getallactivities()` in `public/js/crm/clients/detail-main.js` (delegates to `loadActivities` when present) |
| HTML sanitise | `app/Support/NoteDescriptionHtml.php` |
| Appointment payload | `app/Support/AppointmentActivityDescription.php` |
| Action subject helper | `app/Support/ActionTaskGroup::assignActivitySubject()` |
| Styles | `public/css/client-detail.css` + `resources/views/layouts/crm_client_detail.blade.php` |

### Trait users

`ClientsController`, `ClientPersonalDetailsController`, `ClientDocumentsController`, `ClientNotesController`, `EmailUploadController`.

### Direct writers (incomplete but the important ones)

`AssigneeController`, `DashboardService`, `BookingAppointmentsController`, `ClientPortalController`, `ClientAccountsController`, `DocumentController`, `PublicDocumentController`, `SignatureDashboardController`, `SignatureService`, `OfficeVisitController`, `UnifiedSmsManager`, `Lead`, `ClientImportService`, `AppointmentSyncService`, `ClientEoiRoiController`, `EoiRoiSheetController`, `CRMUtilityController` (checklist email), `API\ClientPortalDocumentController`, `API\ClientPortalAppointmentController`.

---

## 4. Data model

### 4.1 Table: `activities_logs`

Original table is legacy (no create-migration in this repo). Columns in use:

| Column | Type / notes | Required |
|--------|----------------|----------|
| `id` | PK. PostgreSQL sequence was repaired separately | yes |
| `client_id` | Client/lead id (`admins.id` here) | yes |
| `created_by` | Staff id; portal document upload may store the **client** id | usually |
| `subject` | Short phrase. Prefer verb-first, no client name | yes |
| `description` | HTML or plain text | optional |
| `sms_log_id` | FK to `sms_logs` when type is `sms` | optional |
| `activity_type` | VARCHAR(64). **DB default is `'note'`** (not `activity`) | yes if you care about filters |
| `source` | Origin. Seen values: `client_portal`, `client_portal_web`, `crm_emails`. NULL = legacy/unset | optional |
| `use_for` | VARCHAR(64). `'matter'` for workflow/matter-note/email rows; otherwise often assignee staff id (or empty string) | optional |
| `followup_date` | Copied from Action `notes.action_date` | optional |
| `task_group` | Copied from Action category (Call, Checklist, Follow Up, …) | optional |
| `task_status` | NOT NULL. `0` normal; `1` when logging a **completed** Action | yes — set `0` unless Action completion |
| `pin` | NOT NULL boolean/int. `0` default | yes — set `0` unless pinning |
| `created_at` / `updated_at` | timestamps | yes |

PostgreSQL NOT NULL on `task_status` and `pin` is why most `create()` calls set them. Portal document insert (`API\ClientPortalDocumentController`) omits both — that is a live footgun.

The Eloquent model extends `Authenticatable` and uses `Notifiable` / `Sortable`. That is historical; **do not copy**. Use a normal `Model`.

### 4.2 Indexes in this CRM

| Index | Why |
|-------|-----|
| `(client_id, created_at)` | Feed: latest N for one client |
| `(client_id, activity_type)` | Type lookups |
| `activity_type` | Filters |
| `sms_log_id` | SMS join |
| `(client_id, source)` | Portal vs CRM origin |
| `(task_status, created_by, created_at DESC)` | Action-related listing |
| `(task_status, client_id, created_at DESC)` | Per-client action logs |

### 4.3 Relationships

- `client()` — belongsTo client/lead on `client_id` (`Admin` in this app)
- `creator()` / `staff()` — belongsTo `Staff` on `created_by` (feed eager-loads `staff`)
- `smsLog()` — belongsTo `SmsLog` on `sms_log_id`

---

## 5. Activity types

`activity_type` drives icon, colour, CSS class (`activity-type-{type}`), and filter buttons.

**Always set it.** If you `new ActivitiesLog` and save without it, the DB default `'note'` applies. Action assign/complete, appointment status changes, cost-agreement rows, and checklist emails currently fall into that trap, so they show under the **Notes** filter unless subject heuristics catch them.

| Type | When this CRM sets it | Icon | Colour |
|------|------------------------|------|--------|
| `activity` | Trait default: profile edits, appointments created via helper | bolt | primary |
| `note` | File notes; also DB default; also matter notes (`use_for = matter`) | sticky-note (subtype by subject) | warning |
| `email` | Uploaded .eml; Client Portal compose | envelope | primary |
| `document` | Upload, rename, move, delete, attach, portal upload | file-alt | info |
| `signature` | Send for sign, signed, reminder | file-signature | info |
| `financial` | Invoice, receipt, ledger, payment, account | dollar-sign | success |
| `sms` | SMS manager | sms | info |
| `stage` | Workflow stage / matter workflow events | route | primary |
| `lead_converted` | Lead → client | user-check | success |
| `office_visit_checkin` | Check-in created | (no dedicated icon; falls back) | secondary |
| `office_visit_attend` | Session started | same | secondary |
| `office_visit_complete` | Session completed | same | secondary |
| `eoi_confirmation` | Client confirmed EOI | same | secondary |
| `eoi_amendment` | Client requested EOI amendment | same | secondary |

Widen the column to at least VARCHAR(64). `office_visit_complete` overflowed VARCHAR(20).

### Icon map only — not written today

The model maps `followup_scheduled`, `followup_completed`, `followup_rescheduled`, `followup_cancelled` to calendar icons. **No current writer sets those types.** Action lifecycle uses subjects (`Set action for …`, `completed action for …`) and usually the DB default type `note`. When porting, either set real types (`action_assigned`, `action_completed`) or keep using `activity`.

### Note subtypes (UI only)

When `activity_type === 'note'`, the feed inspects **subject** (not a DB column):

| Subject contains | Extra class | Icon |
|------------------|-------------|------|
| `call` | `activity-type-note-call` | phone |
| `email` | `activity-type-note-email` | envelope |
| `in-person` | `activity-type-note-in-person` | user-friends |
| `attention` | `activity-type-note-attention` | exclamation-triangle |
| `others` | `activity-type-note-others` | ellipsis-h |

### Legacy rows with empty `activity_type`

The UI infers from subject:

- `uploaded email:` → email
- invoice / receipt / ledger / payment / account → financial
- `document` (except receipt-document phrases) → document

Do not rely on this for new rows.

### Filter button mapping (important)

The **Activity** button is not `activity_type = activity` only. Client-side it shows:

- `activity-type-activity`
- `activity-type-sms`
- `activity-type-stage`

Notes / Emails / Documents / Signatures / Financial match their types (plus subject heuristics). There is no SMS or Stage button.

---

## 6. Write path

### 6.1 Shared helper (preferred)

```php
trait LogsClientActivity
{
    protected function logClientActivity(
        $clientId,
        $subject,
        $description = '',
        $activityType = 'activity'
    ): ActivitiesLog;

    protected function logClientActivityWithChanges(
        $clientId,
        $subject,
        array $changedFields = [],
        $activityType = 'activity',
        string $descriptionPrefix = ''
    ): ActivitiesLog;
}
```

`logClientActivity` always sets:

- `created_by` = current auth user id
- `task_status` = 0
- `pin` = 0
- `activity_type` = argument (default **`activity`**, unlike the DB default `note`)

`logClientActivityWithChanges` builds HTML then calls `logClientActivity`.

**Detailed changes** (array of `['old' => …, 'new' => …]` keyed by field label):

```html
<div>
  <div><strong>First name:</strong>
    <span style="color:#dc3545;text-decoration:line-through;">John</span>
    →
    <span style="color:#28a745;font-weight:600;">Jon</span>
  </div>
</div>
```

Empty values render as `(empty)`. Dates `YYYY-MM-DD` render as `dd/mm/YYYY`. Values are HTML-escaped.

**Simple changes** (list of field names):

- 1 field: `Updated <strong>Phone</strong>`
- N fields: `Updated <strong>A, B</strong> and <strong>C</strong>`

`$descriptionPrefix` is prepended (call notes: phone HTML then the change block).

### 6.2 Direct create (extra columns)

```php
ActivitiesLog::create([
    'client_id' => $clientId,
    'created_by' => Auth::id(),
    'subject' => 'Office visit check-in created',
    'description' => 'Check-in created for office visit: ' . $purpose,
    'activity_type' => 'office_visit_checkin', // do not omit
    'task_status' => 0,
    'pin' => 0,
    'source' => 'crm',          // optional
    'use_for' => 'matter',      // optional
    'sms_log_id' => $smsLogId,  // SMS only
]);
```

### 6.3 Subject conventions

- Prefer verb-first, past tense, staff as actor: `updated basic information`.
- Do **not** include the client name on Action/profile rows. The feed prints `{Staff Name} {subject}`.
- Skip staff prefix when `displaySubjectWithoutStaffPrefix()` is true: `activity_type === 'document'` **and** subject contains `signed document` or `signed cost agreement`. Public signing uses type `signature` and subject `client signed document`, so that helper does **not** apply there (staff/owner name is still prepended).
- Matter-scoped document/email rows often append ` - {matterReference}`.
- Workflow (Workflow tab) often prefixes the matter number: `{TGV_1} Stage: {fromStage}`.
- Email upload truncates subject at 100 chars.
- Action subjects use the **assignee** name, not the client: `Set action for Jane Smith` / `Set followup for Jane Smith`.

### 6.4 Description conventions

- Prefer HTML (`<p>`, `<strong>`, diffs, `<ul>` for signature details).
- Sanitize on **read** with `NoteDescriptionHtml::forDisplay` (HTML Purifier). Office XML paste is escaped, not rendered.
- Appointment create wraps details in `<div class="appointment-activity-detail">` so the feed can style them.
- Stage rows put the move text in `description` and use a dedicated layout (staff + timestamp in the header).
- Action completion may embed a convert-to-note icon **inside** the description HTML (`class="convert-activity-to-note"`).

### 6.5 When **not** to write a feed row

From the Client Portal **tab** (`source=client_portal` or `current_tab` in `client_portal` / `application`), stage/matter/document/staff-message flows **skip** `activities_logs` so the Personal Details feed is not duplicated. Workflow tab still logs (`source = client_portal_web`).

**Client Portal Action category:** completing or assigning an Action with `task_group = 'Client Portal'` does **not** write a feed row (notifications still fire).

Several company-profile saves (company info, directors, workforce, LMT, etc.) currently have **no** `logClientActivity` call.

---

## 7. What gets logged (event catalog)

Always log **after** the underlying save succeeds. Port the domains you have.

### Profile / personal details (`activity` via trait)

| Event | Typical subject |
|-------|-----------------|
| Basic info | `updated basic information` |
| Phones | `updated phone numbers` |
| Emails | `updated email addresses` |
| Passport | `updated passport information` |
| Visa | `updated visa information` |
| Address | `updated address information` |
| Travel | `updated travel information` |
| Qualifications | `updated educational qualifications` |
| Work experience | `updated work experience` |
| Additional info | `updated additional information` |
| Character | `updated character information` |
| Partner | `updated partner information` |
| Partner EOI | `updated partner EOI information` / `cleared partner EOI information` |
| Children / parents / siblings / others | `updated {children\|parents\|siblings\|other relationships} information` |
| Occupation & skills | `updated occupation & skills` |
| English tests | `updated English test scores` |
| Related files | `updated related files` |
| EOI references | `updated EOI references` |
| Lead pipeline | `updated lead pipeline` |
| Full personal form | `updated personal information` |
| Details verified | `verified client details` / `verified lead details` / `verified company details` |

Use `logClientActivityWithChanges` when you have old/new values.

### Notes tab (`note` via trait)

| Event | Subject pattern |
|-------|-----------------|
| Create | `added {Type} Notes` or `added {Type} Notes - {matterRef}` (`Client`/`Lead` prefix when no matter) |
| Update | `updated {Type} Notes` / `updated {Type} Notes - {matterRef}` |
| Delete | `deleted {Type} Notes` |

`{Type}` is the note category: Call, Email, In-person, Attention, Others.

Call notes prepend: `<p class="activity-note-call-number"><strong>Number:</strong> …</p>`.

These are **audit copies** of `notes` rows. They are not the Notes tab store.

### Matter notes (same table, `note` + `use_for = matter`)

Client Portal `addNote` writes the note **into** `activities_logs` (subject = title). Listed by `getMatterNotes`. Also visible in the feed.

### Documents (`document`)

| Event | Subject pattern |
|-------|-----------------|
| Upload | `uploaded {checklistName} - {matterRef}` |
| Upload labelled | `uploaded {Personal\|Visa\|Nomination} Document: {name} - {matterRef}` |
| Bulk upload | `bulk uploaded {n} documents - {matterRef}` |
| Add checklist | `added Personal Checklist` / `added Visa Checklist - {matterRef}` / `added Nomination Checklist - {matterRef}` |
| Rename | `renamed Document - {matterRef}` |
| Move | `moved document: {name} - {matterRef}` |
| Delete | `deleted {Personal\|Visa\|…}: {name} - {matterRef}` |
| Delete checklist | `deleted Checklist: {name} - {matterRef}` |
| Signature attach | `Document #{id} attached` |
| Portal upload | `updated {doc_type} document` (`source = client_portal`, `created_by` = client) |

### Email (`email`) and related

| Event | Type | Subject |
|-------|------|---------|
| Upload .eml / parsed email | `email` | `uploaded Email: {subject} - {matterRef}` |
| Client Portal compose | `email` | HTML `Subject : {subject}` (`use_for = matter`) |
| Delete CRM email log | `activity` | `Deleted email message` (`source` = `crm_emails`) |
| Checklist email | *(unset → `note`)* | `Checklist sent to client` / `Document Checklist sent to client` |

### SMS (`sms`)

Created in `UnifiedSmsManager` with `sms_logs`. Link via `sms_log_id`. Logging failure must not fail the send.

Subjects (sent vs failed): `sent SMS` / `failed to send SMS`, plus `verification` / `notification` / `reminder` variants. Description includes To, provider badge, full message, error.

Inbound SMS is not a separate subject in `getActivitySubject()`.

### Financial (`financial`)

Invoices, client/office/journal receipts, ledger deposits/withdrawals, payments, reversals, account documents. Example: `added client funds ledger. Reference no- {transNo}`. Description holds amounts and dates.

### Workflow (`stage`, `use_for = matter`)

| Event | Subject | Extra |
|-------|---------|-------|
| Next / previous (legacy endpoints) | `Stage: {fromStageName}` | description `moved the stage from <b>A</b> to <b>B</b>` |
| Workflow tab next/back | `{matterNo} Stage: {fromStageName}` | `source = client_portal_web`; may include decision outcome |
| Workflow changed | `{matterNo} Workflow changed to {name}` | |
| Discontinue / reopen / delete | `Matter Discontinued` / `Matter Reopened` / `Matter Deleted` | skipped from Client Portal tab |

Stage items use a distinct feed layout.

### Appointments (`activity` when using the helper; often unset type otherwise)

| Event | Subject |
|-------|---------|
| Create (helper / sync / backfill) | `scheduled a free appointment` / `scheduled a paid appointment` / `scheduled an appointment` |
| Status change (CRM) | `Booking appointment status updated` |
| Status change (mobile) | `Appointment status updated via mobile app` (`source = client_portal`) |
| Reschedule / meeting type / language | built from what changed (e.g. `Appointment rescheduled: …`) |
| Consultant assigned | set in `BookingAppointmentsController` |

Create description is structured HTML (`.appointment-activity-detail`): category, type, query, datetime, language, location.

### Signatures (`signature`)

| Event | Subject |
|-------|---------|
| Place fields and send | `placed signature fields and sent cost agreement for signature` |
| Client signed (public) | `client signed document` |
| Reminder | `sent reminder for document signature` |
| Dashboard send | similar `signature` rows in `SignatureDashboardController` |

Related: `created visa agreement`, `{created\|updated} cost assignment form`, `deleted cost assignment form` (type often unset).

### Office visit

| Type | Subject |
|------|---------|
| `office_visit_checkin` | `Office visit check-in created` |
| `office_visit_attend` | `Office visit session started` |
| `office_visit_complete` | session-complete subject in `OfficeVisitController::complete_session` |

### EOI / ROI

| Type | Subject |
|------|---------|
| `eoi_confirmation` | `EOI Details Confirmed by Client` |
| `eoi_amendment` | `EOI Amendment Requested by Client` |

`created_by` may be null (public confirmation link, `auth:admin` absent).

### Lead conversion (`lead_converted`)

Subject: `Lead converted to Client`.

### Actions (copied onto the feed; type usually omitted → `note`)

Do **not** log Client Portal category.

| Event | Subject |
|-------|---------|
| Assign | `Set action for {assignee}` or `Set followup for {assignee}` |
| Complete | `completed action for {assignee}` |
| Complete (reassign flow) | `Action completed for {assignee}` |
| New action after complete | `New action assigned for {assignee}` |
| Update | `Updated action for {assignee}` |
| Delete incomplete | `deleted action for {assignee}` |
| Delete completed | `deleted completed action for {assignee}` |

Also set `followup_date`, `task_group`, `task_status` (`1` if completed), and `use_for` = assignee id when the actor is not the assignee.

### Import / export

Client import copies historical activity rows (and can add office-visit / note fallbacks). Export includes `activity_type`.

---

## 8. Read path (feed API)

### Route

```
GET /get-activities
name: clients.activities
```

Listed in `VerifyCsrfToken` except (GET). Requires authenticated staff. Uses `StaffClientVisibility::canAccessClientOrLead`.

This CRM returns JSON via `header` + `echo` + `exit`. When porting, use a `JsonResponse`.

### Query params

| Param | Default | Notes |
|-------|---------|-------|
| `id` | required | Client/lead id |
| `page` | 1 | 1-based |
| `per_page` | 40 | Clamped 1–100 |
| `staff` / `user` | optional | Filter by staff **first_name** only (case-insensitive LIKE) |
| `keyword` | optional | LIKE on `subject` or `description` |

No server-side `activity_type` filter. Type chips are client-side on already-loaded rows.

### Query

```
ActivitiesLog::where('client_id', $id)
    ->with('staff')
    ->orderBy('created_at', 'DESC')
    ->skip(($page - 1) * $perPage)
    ->take($perPage + 1)   // +1 to detect has_more
```

Does **not** exclude matter notes, Actions, or portal rows.

### JSON shape

```json
{
  "status": true,
  "data": [
    {
      "activity_id": 123,
      "subject": "updated basic information",
      "subject_without_staff_prefix": false,
      "createdname": "J",
      "name": "Jane Smith",
      "message": "<div>…sanitized HTML…</div>",
      "date": "14 Aug 2026, 07:58 PM",
      "created_at_ymd": "2026-08-14",
      "followup_date": "15 Aug 2026",
      "task_group": "",
      "pin": 0,
      "activity_type": "activity"
    }
  ],
  "page": 1,
  "per_page": 40,
  "has_more": true
}
```

- `message` = `description` after `NoteDescriptionHtml::forDisplay`
- `date` = `d M Y, H:i A`
- `followup_date` = `d M Y` if midnight, else `d M Y, H:i A`
- Missing staff → `name` = `Unknown`
- Failure: `{ "status": false, "message": "…" }` (`Client ID is required`, `unauthorized`, `Client not found`)

### Other endpoints (weak / leftover)

| Method | Path | Behaviour |
|--------|------|-----------|
| GET | `/pinactivitylog` | Toggle `pin`. Existence check only. Current `loadActivities` HTML **does not** render a pin control. `detail-main.js` still has a `.pinactivitylog` click handler. |
| GET | `/deleteactivitylog` | Hard delete. Existence check only. Not wired in the current feed item HTML. |
| POST | `/convert-activity-to-note` | Creates a `notes` row; then logs `converted activity to note`. Triggered from `.convert-activity-to-note` (Action completion description, and a modal in `addclientmodal.blade.php`). Unused `_activity_item` partial also had this for subjects containing `added a note` / `updated a note`. |

When porting: either implement pin/delete with the same visibility check as the feed, or omit them.

---

## 9. UI

### Placement

Sidebar `#activity-feed` on **client** and **company** detail (`@include('crm.clients.tabs.activity_feed')`). One instance. The Activity nav tab (`activityfeed-tab`) is empty; the list stays in the sidebar.

### Chrome

1. Header: “Activity Feed”, refresh, expand-width checkbox.
2. **Wide mode**: search, date from/to (flatpickr `Y-m-d`), Apply / Reset. Sidebar ~50% width.
3. Type buttons: All, Activity, Notes, Emails, Documents, Signatures, Financial.
4. List `.feed-list` of `.feed-item.activity`.
5. Load more + infinite scroll (within 120px of bottom). If the first page does not fill the pane, keep loading.

Items are **not** server-rendered. `loadActivities` concatenates HTML from the JSON. After load it calls `ActivityFeed.reapplyCurrentFilter` and `enhanceAppointmentActivityRows`.

### Item layout (non-stage)

```
[icon]  {Staff Name} {subject}
        {description HTML}
        {optional task_group / followup_date}
        14 Aug 2026, 07:58 PM
```

Stage:

```
[route icon]  {Staff Name}                    14 Aug 2026, 07:58
              moved the stage from A to B
```

Appointment rows add `feed-item--appointment` and a calendar icon when the description contains `appointment-activity-detail`.

### After mutations

Successful note/document/account/appointment saves should call `loadActivities({ reset: true })`. Older code calls `getallactivities()`, which now forwards to `loadActivities` when it exists.

---

## 10. Implementation recipe (other CRM)

Minimum viable port:

1. **Table** with columns in §4.1. Default `task_status = 0`, `pin = false`, **`activity_type = 'activity'`** (do not copy this CRM’s DB default `'note'`). Index `(client_id, created_at)`.
2. **Plain Eloquent model** (`client`, `staff`, icon map, `displaySubjectWithoutStaffPrefix`, `formatFollowupDateForDisplay`). Do not extend `Authenticatable`.
3. **Trait** `LogsClientActivity`. Use it from every mutator you care about. Always pass `activity_type`.
4. **Explicit log calls** after each successful write. Wrap in try/catch.
5. **GET feed API** paginated, visibility-checked, Laravel `JsonResponse`.
6. **Sidebar UI** with type filters, infinite scroll, staff+subject headline, sanitized HTML.
7. **Sanitize HTML** on read.
8. **Subject rules**: verb-first, no client name on audit rows, prepend staff in the UI.
9. **Separate matter notes** from the audit table if you can.

Then add types in this order:

1. Profile field updates with old → new diffs  
2. Notes create/update/delete  
3. Documents upload/delete  
4. Emails and SMS  
5. Financial  
6. Workflow stage  
7. Actions assign/complete  
8. Appointments / signatures / office visit  

### Suggested schema (Laravel)

```php
Schema::create('activities_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('client_id')->index();
    $table->unsignedBigInteger('created_by')->nullable()->index();
    $table->string('subject');
    $table->longText('description')->nullable();
    $table->unsignedBigInteger('sms_log_id')->nullable()->index();
    $table->string('activity_type', 64)->default('activity')->index();
    $table->string('source', 50)->nullable();
    $table->string('use_for', 64)->nullable();
    $table->timestamp('followup_date')->nullable();
    $table->string('task_group')->nullable();
    $table->unsignedTinyInteger('task_status')->default(0);
    $table->boolean('pin')->default(false);
    $table->timestamps();

    $table->index(['client_id', 'created_at']);
    $table->index(['client_id', 'activity_type']);
    $table->index(['client_id', 'source']);
});
```

Map `client_id` / `created_by` to the other CRM’s client and user tables.

### Filter buttons to ship

All · Activity · Notes · Emails · Documents · Signatures · Financial.

If you ship SMS/stage, either add buttons or fold them into Activity as this CRM does.

---

## 11. Pitfalls learned in this CRM

- **Always set `task_status` and `pin`.** PostgreSQL NOT NULL will reject the insert. Portal document upload currently omits them.
- **Always set `activity_type`.** DB default is `'note'`, so Action/appointment/cost rows land in the Notes filter.
- **`activity_type` must be ≥ 64 chars.**
- **Do not put the client name in Action/profile `subject`.** The UI prefixes the actor.
- **Logging should not fail the parent action.** Wrap in try/catch.
- **Paginate.** Page size 40, `has_more` via `take(perPage + 1)`.
- **Sanitize HTML.** Notes and Word paste land in `description`.
- **Same table, two jobs.** Matter notes (`use_for = matter`) mix into the feed.
- **Client Portal tab vs Workflow tab.** Decide once whether portal-tab actions duplicate the feed.
- **Client Portal Actions** skip the feed by design.
- **Actions vs Activity Feed vs Activity Search.** Search is Actions (`notes.is_action = 1`). Name them differently in the other CRM.
- **Staff presence middleware** is unrelated. Do not write client feed rows on every HTTP request.
- **No global observer.**
- **Staff filter is first_name only.**
- **Pin/delete endpoints exist but the current feed renderer does not use them.**
- **`_activity_item.blade.php` is unused.** Do not treat it as the live template.
- **Do not extend Authenticatable** for this model.
- **Do not copy `echo` + `exit` JSON.** Use `JsonResponse`.

---

## 12. Tests worth copying

- Follow-up date display (`tests/Unit/Models/ActivitiesLogFollowupDateTest.php`)
- Appointment description HTML (`tests/Unit/Support/AppointmentActivityDescriptionTest.php`)
- Appointment activity backfill (`tests/Unit/Support/AppointmentActivityLogBackfillTest.php`)

When adding the feature elsewhere, cover: create via trait, change-diff HTML escaping, feed pagination `has_more`, visibility denial, omitted `activity_type` not defaulting to note, and type-filter CSS classes (including Activity = activity+sms+stage if you copy that behaviour).
