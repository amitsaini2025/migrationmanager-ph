# Staff workload and efficiency

Spec for a **My day** view in **migrationmanager2** — a registered **migration practice CRM**. **Individual staff only**: no team table, no manager “view as,” no ranking of colleagues.

The unit of work is the **matter** (`client_matters`): a visa application, ART appeal, nomination/sponsorship file, EOI/ROI, skill assessment, or bridging-visa file sitting on a **client or lead** (`admins`). Almost every open file has **three named staff** (Migration Agent, Person Responsible, Person Assisting). Staff chase **people** (calls, consults) and **files** (stages, Immi/ART, checklists, Form 956, lodgement).

This is **not** bansalcrm2’s student / college-application / partner model. There is no college contact layer. Do not query an `applications` table for ownership (that table was **removed**; visa tracking is `client_matters` — see README “Matter-based tracking”). Prefer the source rows below; do not invent new `activity_type` values without writing them at the source. See `docs/CRM_ACTIVITY_FEED.md` for how `activities_logs` is written.

Stack facts this spec assumes (README): Laravel 13 / PHP 8.3, **PostgreSQL** primary, CRM auth is **Staff-only** on guard `admin` with provider `staff` (`staff` table); `admins` holds clients/leads. Column and behaviour claims below were checked against controllers, Blade, and migrations — where code disagrees with bansalcrm2’s spec, **this file follows the code**.

---

## Agreed product brief

Efficiency and workload are built from **what staff did on clients/leads/companies and their matters**. **Hours in CRM** are a **header** (reuse session / login / presence). They are **not** the score. **Call/in-person duration is out** (no timer on notes).

### Domain model

| Entity | Table / shape | Role in workload |
|--------|---------------|------------------|
| Client / lead | `admins` (`type` client or lead) | Person contacted. Lead/client **record assignee** is `admins.user_id` (one staff). **Not** the same as the three matter roles. |
| Company client | `admins` (`is_company = 1`) | Employer (SBS, nomination, 482/494/186/407). Same note UI as personal clients. Extra work on `companies`, `company_nominations`, nomination documents. |
| Matter type | `matters` (`title`, `nick_name`, `is_for_company`, default `workflow_id`) | What kind of file this is (485/TR, 500, 600, 189/190/491, partner, parents, ART, EOI, skill assessment, bridging, employer-sponsored, General, …). |
| Matter (file) | `client_matters` | **The caseload unit.** Workflow + current stage, `matter_status` (1 open / 0 discontinued or completed), deadline, Immi/other refs, **three staff FKs**, optional `decision_outcome`. |
| Workflow stage | `workflow_stages` | Per-workflow stages. Matter stores **`workflow_stage_id`** (FK), not a free-text stage name. |
| Office visit | `checkin_logs` | Front-desk queue — **not** proof of a consult. |
| File note | `notes` (`is_action = 0`, `assigned_to` null) | Contact types live in **`task_group`** (Call, In-Person, Email, Others, Attention). Optional `matter_id`. |
| Action | `notes` (`is_action = 1`, `assigned_to` set) | Assigned work. **Same column** `task_group` with a **different** value set (Call, Checklist, Follow Up, …). Group actions share `unique_group_id`. |
| Workflow file note | `workflow_file_notes` (**third** note store) | One **append-only** row per `client_matter_id` + `workflow_stage_id` (unique). Written by `ClientPortalController::saveWorkflowFileNote` for the matter’s **current** stage only. **Not** Layer A — see §4 and §12. |

A person can have **many matters** (e.g. a 485 plus an ART appeal after refusal). One Call file-note on the client is **one contact**, even if `notes.matter_id` is set. Moving three matter stages is **three files of throughput**, still one unique client for “worked on people.” Caseload must count **matters**, split by the **role this staff holds** on each file.

### Three staff on every matter (MA / PR / PA)

Each `client_matters` row has **three separate staff ids**. They are often three different people. The same person **may** occupy two or three columns; that is still **one** matter in caseload.

| Role on the file | Column | Typical `staff.role` (defaults in `config/crm.php`) | What they usually do |
|------------------|--------|------------------------------------------------------|----------------------|
| **Migration Agent (MA)** | `sel_migration_agent` | 16; assignment dropdown also includes `is_migration_agent = 1` | Registered agent: Form 956, advice, lodgement, Immi, ART representation. |
| **Person Responsible (PR)** | `sel_person_responsible` | 12 | Owns file progress: checklists, chasing, stage moves, client comms. |
| **Person Assisting (PA)** | `sel_person_assisting` | 13 | Processing: docs, uploads, portal, supporting PR/MA. |

**Do not** treat `admins.user_id` as “the agent on the visa.” That field is the **CRM record assignee** (common for **leads** and calling-team chase). Front-desk fallback for clients can also use `admins.agent_id` (`CheckInNotificationService`) — **not** caseload ownership.

**Ghost credit:** being MA, PR, or PA does **not** increment spoke-to, docs, or stages unless **that staff** wrote the row. Drill-down should show “MA … · PR … · PA …” so shared files are visible without ranking the three.

**Caseload (matters)** = distinct `client_matters.id` where this staff equals **any** of the three columns. Also show **subcounts**: matters as MA / as PR / as PA (overlap is OK in subcounts; unique total must dedupe).

**Caseload (people)** = distinct `admins` where `user_id = staff` **or** this staff appears on an **open** matter in any of the three roles. Deduplicate. Grants (`client_access_grants`) stay **access**, not ownership.

### What a matter is (visa applications, appeals, and related files)

`matters.nick_name` / title (and `config/sheets/visa_types.php`) classify **sheets**. Workload should still count **all** open `client_matters`, including types that have no sheet.

| Family | Typical nick / title / workflow | What “progress” looks like |
|--------|----------------------------------|----------------------------|
| **Temporary graduate / TR** | tvg, pt, 485 | Checklist → 956/cost agreement → docs → draft → **lodged** → Immi request → **Decision Received** (Granted / Refused / Withdrawn). |
| **Student / visitor** | s, sse, 500; vbv/vvd/vsf, 600 | Same General-style visa pipeline. |
| **PR / skilled** | si, sn, sh, 189/190/191/491 | Same pipeline; EOI/ROI and skill assessment are often **separate** matters. |
| **Partner / parents** | pv/pa/pm; cp/ap/prt; 820/801/309/100; 103/143/804/870… | Same pipeline, long **waiting** after lodge is normal. |
| **Employer-sponsored / nomination / SBS** | en, es, 482/494/186/407; company `is_for_company` types | Company **and/or** nominee personal file. Nomination docs, LMT, workforce — not a college partner. |
| **ART (Administrative Review Tribunal)** | nick `art`; workflow **Administrative Review Tribunal** (`config/workflow.php`) | Appeal after refusal. Sheet `status_of_file`: submission pending/done, hearing invitation, **waiting for hearing**, hearing, decided, withdrawn. **Hearing wait is waiting, not idle staff.** |
| **EOI / ROI** | matter title Expression Of Interest; workflow **EOI /ROI** | Points/EOI work; Actions `EOI/ROI Amendment` and `EOI/ROI Confirmation` are **actions**, not consults. |
| **Skill assessment** | e.g. APC | Separate file; waiting on assessing authority is **waiting**. |
| **Bridging visa** | BVB / 020 workflow | Often short, high-touch; still a matter. |
| **General** | matter id 1 | Catch-all; allowed for personal and company. |

Visa **sheets** (TR, ART Matters, Visitor, Student, PR, employer-sponsored, partner, parents) are **filtered lists of matters**. Opening a sheet is **not** throughput. ART’s `status_of_file` on `client_matter_references` is **sheet operational status**, not `workload_class` and not Layer A.

### Contact (one audience: the CRM person)

**Spoke to / met** = file notes (`is_action = 0`). Type is **`notes.task_group`**, **not** `notes.title`.

The create-note modal (`resources/views/crm/clients/modals/notes.blade.php`) posts `task_group` = `Call` | `Email` | `In-Person` | `Others` | `Attention`. `ClientNotesController` writes that to `notes.task_group`. `notes.title` is often empty on create (`$request->title ?? ''`). **Do not copy bansalcrm2’s “title is the note type” rule — it is wrong here.**

| Audience | `notes.type` | `notes.client_id` | File-note `task_group` that counts as live contact |
|----------|--------------|-------------------|-----------------------------------------------------|
| Client / lead / company | `client` or `lead` | `admins.id` | `Call`, `In-Person` |

Company detail uses the same note endpoints and `not_picked_call`. Optional: `task_group = Email` file notes = “emailed (note)” under throughput, not spoke-to.

### What else to count (besides Call / In-Person file notes)

Count **unique clients** and **unique matters** first, event count second.

| Count | Why | Source |
|-------|-----|--------|
| Documents (personal / visa / nomination) | Files they worked | `documents` (`created_by` prefer / `user_id`); feed `activity_type = document`; visa/nomination rows have `client_matter_id`. Consider excluding `not_used_doc` rows |
| Email | Comms | `email_logs.user_id` (relation `uploader()` → `Staff`); feed `email`. **Exclude** `conversion_type = 'system_generated'` (receipt/invoice auto-mail) and fetched inbound (`conversion_type = 'conversion_email_fetch'` with `mail_body_type` inbox) from personal credit |
| SMS | Comms | `sms_logs.sender_id` (relation `sender()` → `Staff`); feed `sms` |
| Matter stage moves | Visa / ART / EOI progress | `activities_logs` (`activity_type = stage`, `use_for = matter`, `created_by`) |
| Decision recorded | Outcome on the file | Stage into **Decision Received** plus `client_matters.decision_outcome` (`Granted` / `Refused` / `Withdrawn`) |
| Checklist / 956 / cost agreement | Pipeline start | checklist send; Form 956; cost/visa agreement signatures (`activity_type = signature`) |
| Lead converted | Outcome | `activity_type = lead_converted` |
| Financial | Accounts | `activity_type = financial`; receipts on `client_matter_id` |
| Profile / company / nomination upkeep | Real, easy to inflate | `activity_type = activity` |
| Actions completed | Assigned work finished | `activities_logs` subject `completed action for {assignee}` with `task_status = 1`, actor `created_by` (**not** `notes`; see §5.2) — and **not** Action `task_group = Call` as a consult |
| Appointments | Bookings | appointment subjects on the feed |
| Matter created | Pipeline start | new `client_matters` / related activity |
| EOI confirmation / amendment | Portal/client-initiated | feed types `eoi_*`; Actions `EOI/ROI *` |
| Office visit handling | Reception | `office_visit_*` — diary/throughput, not Layer A |
| ART sheet reminder / comment | Appeal chase | `matter_reminders` / sheet writers with `reminded_by` — not opening the ART sheet |

### Show separately — not “met / spoke”

- Follow-ups (`is_action = 1`, `task_group = 'Follow Up'`) and other Actions
- Office visit / check-in
- **Hours in CRM** — `sessions`, `staff_login_logs`, `TrackStaffCrmActivity` (`Active in CRM (session)`), `ActiveStaffService`
- **Call not picked** — `admins.not_picked_call` + SMS (`ClientCrmFollowups`) — chase, not a consult
- Visa / ART **sheets** — views of matters
- Workflow/portal notes stored **in** `activities_logs` (`use_for = matter`, `activity_type = note`) — not file-note contact unless also in `notes`

### Do not count

- Login duration as the **score** (still show hours)
- Note **updates** as new contact (only **created** Call / In-Person file notes)
- `activities_logs` `added {Type} Notes` / `updated {Type} Notes` — audit copy of `notes`
- Action `task_group = Call` (or Checklist, Follow Up, EOI/ROI *, Client Portal) as Layer A
- Check-in / session start as “met” without an In-Person **file note**
- Inbound email as spoke-to
- Ghost credit for MA/PR/PA or `admins.user_id` without an actor write
- `client_access_grants` as caseload
- Ranking colleagues; Team today; copying bansalcrm2 college/`applications.user_id`
- ART `waiting_for_hearing` (or Immi lodge wait) as “this staff did nothing”
- Sheet page views

### Shared allocation

| Layer | Rule |
|-------|------|
| Contact / throughput | Only the staff who **wrote** the note / activity / doc / email / stage |
| Caseload (people) | `admins.user_id` **or** staff is MA/PR/PA on an open matter — dedupe |
| Caseload (matters) | Any of the three columns; unique matter id; **also** subcount by which role(s) they hold |
| Caseload (actions) | `notes.assigned_to` only — do not fan out to the other two matter roles |

### How it fits together

| Layer | Source of truth |
|-------|-----------------|
| Hours | `sessions.last_activity` + today’s `staff_login_logs` — **header** |
| Spoke to / met | File notes: `is_action = 0`, `task_group` in Call / In-Person, actor `user_id` |
| Worked on today | Distinct clients + matters from staff writes |
| Still on their plate | People + matters in MA/PR/PA; split **active / waiting / closed**; **quiet / inactive** by **this staff’s** last work |

### Workflow stages: active vs waiting vs closed

`workflow_stages` has **no** idle flag. Matter uses **`workflow_stage_id`**. Visa sheet lists (`ongoing_stages`, `lodged_stages`, `discontinue_stages`) and ART `status_of_file` are **hints**, not workload class.

| Class | Meaning | Migration examples (hints — admins classify per stage **row**) |
|-------|---------|----------------------------------------------------------------|
| **active** | We must do something next | Checklist we own, drafting, 956 chase, preparing lodgement, ART submission we owe |
| **waiting** | Ball is not with us | Awaiting client docs; **Application Lodged** / Immi processing; **Immi Request Received** (if treated as waiting on client/Immi); ART **waiting for hearing**; skill-assessment authority |
| **closed** | Out of working caseload | File Closed, Withdrawn, Refund, Discontinued (`matter_status = 0`); Ready to Close if you treat it as closed |

**Caseload** = distinct **matters** for this staff (any of three roles), split active / waiting / closed / unknown. A large **waiting** pile (lodged visas, ART hearings) is **not** low efficiency. A large **active** pile with no writes today is overload or neglect.

**Quiet / inactive** ≠ waiting. A lodged 189 can be waiting on Immi and **inactive for this PA** if they have not logged work in 14+ days.

**Throughput** = stage *moves* they logged (`activity_type = stage`). Sitting in lodged is not a move.

People with **no** open matter (`matter_status = 1`): **No matter** bucket (typical for early leads), not “waiting.”

Default unclassified stage: **`unknown`**. Dashboard excluding a hardcoded `workflow_stage_id` is a **dashboard filter**, not workload class.

See §6.1. Quiet/inactive §6.2 is **v1** even before `workload_class` is filled.

### Personal view is user-based (not role-based)

**Every** `auth:admin` staff sees **only their** My day: `Auth::guard('admin')->id()`. MA, PR, PA, calling (14), accounts (15), front desk — **same tiles**. A processor with 20 stage moves and 0 Call notes is a valid day. A calling-team staff with 15 Call notes and 0 stage moves is a valid day.

Role does **not** change whose numbers you see. No Team today. Ignore `staff_id` query params. Do not copy super-admin dashboard lists. `StaffLoginAnalyticsController` is **not** My day.

### Further ideas

Quiet/inactive (7 / 14 days) and the hours strip **are v1**.

| Idea | Layer | When |
|------|--------|------|
| **Caseload by matter family** | Caseload | Visa vs ART vs employer vs EOI vs other (`getVisaSheetType()` / `sel_matter_id`). Phase 2. |
| **Aging (days in stage)** | Caseload | Long waiting is normal (Immi/ART). Long **active** with no work by anyone — later flag. |
| **Waiting → active** | Throughput | e.g. Immi Request Received → we chase docs. Optional tag. |
| **Inbound vs outbound email** | Comms | Phase 2. Use `config('crm.company_email_domains')` if you classify. |
| **Due today / overdue** | Caseload | Actions + Follow Ups (`DashboardService::getNoteDeadlineCount` pattern). |
| **This week vs last week** | UI | Same staff only. |
| **Leave / part-time** | Fairness | Do not interpret zeros if leave is not in the CRM. |
| **Visit without In-Person note** | Quality | Warning only. |
| **Sheet reminders** | Throughput | `matter_reminders` with `reminded_by` — not sheet views. |

---

## Reading the day (and how to improve)

### Contact can be zero on a busy day

A PR/PA day of **stage moves**, **visa document uploads**, **Form 956**, **Immi request responses**, or **call not picked** can have **empty** Layer A. That is expected.

The page must **not** look idle. Keep Layer B and C visible. High hours + empty contact is a **logging or workload** signal.

An MA day of **ART hearing prep** with few calls is still work (docs, stage, signature). A calling-team day of **Call notes** with zero matters progressed is still work.

### Call not picked is chase, not a consult

Small counter **beside** contact, never inside spoke-to. Same for office visits.

### How to improve (product / UI)

- Keep hours, contact, and throughput visually separate.
- After stage move / call not picked, optional nudge for a Call / In-Person file note.
- Quiet hint when Others/Attention file notes dominate Call/In-Person today.
- Quiet (7–13d) / inactive (14+d) prompt touch of **their** matters — not a ranking of MA vs PR vs PA.
- No single “efficiency %.”

---

## 1. Goal

For **this staff member** and a date range (default **today**, `Australia/Melbourne`):

1. How long in the CRM (session / last seen) — **context**?
2. Which **people** did they speak to or meet (unique)? Which were **new** to them / to the firm?
3. Which **matters** (visa / ART / nomination / EOI / …) did they progress?
4. What other CRM work (docs, email, SMS, 956/signatures, money, actions)?
5. What is still **open**: people + matters as MA/PR/PA, by stage class, quiet/inactive, overdue actions?

Hours must not replace (2)–(5).

---

## 2. Three layers (keep them separate)

| Layer | Question | Do not mix with |
|-------|----------|-----------------|
| **Hours** | How long in CRM? | Contact or throughput score |
| **A. Contact** | Who did they talk to / sit with? | Profile edits, lodge wait, check-in, call not picked, Action Call |
| **B. Throughput** | What did they complete on people/files? | Login duration |
| **C. Caseload** | What is assigned (including quiet/inactive)? | Today’s volume |

High caseload + low throughput (waiting on Immi/ART) is normal. High profile-edit + zero contact is busy-in-file. Long session + zero Call notes: show hours **and** throughput.

---

## 3. Attribution rules

Credit the **writer**, not the MA on the matter — unless the metric is “assigned to me.”

| Store | Staff column | Use for |
|-------|----------------|---------|
| `notes` file notes (`is_action = 0`) | `user_id` | Contact via **`task_group`**; `matter_id` for drill-down |
| `notes` actions (`is_action = 1`) | `assigned_to` (open/done), `user_id` (creator) | Caseload vs assigner |
| `activities_logs` | `created_by` | Throughput (incl. stage moves and action completion); join `staff` |
| `workflow_file_notes` | `created_by` (first insert only), `updated_by` (last append) | Weak signal only — appends do **not** record per-entry authorship |
| `email_logs` | `user_id` (`uploader()`) | Staff mail; filter out system-generated / fetched inbound |
| `sms_logs` | `sender_id` | SMS |
| `documents` | `created_by` / `user_id` | Uploads; `client_matter_id` |
| `admins` | `user_id` | People caseload (record assignee) |
| `client_matters` | `sel_migration_agent`, `sel_person_responsible`, `sel_person_assisting` | Matter caseload |
| `checkin_logs` | `user_id` | Walk-in assignee, not “met” |
| `staff_login_logs` | `user_id` | Hours |
| `sessions` | `user_id` | Last activity |

Portal uploads may set `created_by` to the **client** id — exclude from staff credit.

### Exclude from staff credit

- `created_by` null (some public EOI flows)
- Inbound-only email if not staff-sent
- Login events as client work
- Matter-only `activities_logs` notes for **contact** (contact is `notes` only)

Timezone: `config('app.timezone')` (**Australia/Melbourne**).

---

## 4. Layer A — Contact (file notes are canonical)

File notes: `notes.is_action = 0`. Contact type: **`task_group`**.

| UI type | `notes.task_group` (file note) | Counts as |
|---------|--------------------------------|-----------|
| Call | `Call` | **Spoke to** |
| In-Person | `In-Person` | **Met in person** |
| Email | `Email` | Emailed (optional; not spoke-to) |
| Others | `Others` | Not contact |
| Attention | `Attention` | Not contact |

**`task_group` is overloaded.** Always filter `is_action`:

| `is_action` | `task_group` examples | Layer |
|-------------|----------------------|--------|
| 0 | Call, Email, In-Person, Others, Attention | A (Call / In-Person only) |
| 1 | Call, Checklist, Review, Query, Urgent, Personal Action, Client Portal, EOI/ROI Amendment, EOI/ROI Confirmation, Follow Up | C (queue) / B if completed — **never** Layer A |

Creating a file note also writes `activities_logs` (`added Call Notes - TGV_1`, etc.). **Count the `notes` row, not the activity row.**

Match stored values; UI uses `In-Person`. Listing code uses `stripos` on `task_group` — queries should still use **exact** `Call` / `In-Person` unless you confirm messy legacy data.

The Notes tab list (`ClientNotesController::getnotelist`) selects file notes with **`whereNull('assigned_to')`** rather than `is_action = 0`. Use **both** guards so a group Action can never leak into contact.

### Query (today, one staff)

```
notes
  WHERE user_id = :staffId
    AND is_action = 0
    AND assigned_to IS NULL
    AND type IN ('client', 'lead')
    AND created_at BETWEEN :start AND :end
    AND task_group IN ('Call', 'In-Person')
```

| Metric | Definition |
|--------|------------|
| `spoke_to_clients_count` | Distinct `client_id` with a **created** Call file note |
| `met_clients_count` | Distinct clients with created In-Person |
| `contacted_clients_live_count` | Distinct clients with Call **or** In-Person |
| `new_to_staff_client_*` | First Call or In-Person **by this staff** on that person |
| `new_to_firm_client_*` | First Call or In-Person **by any staff** |

Use **create** time. Editing a Call note is not a new contact. Company clients count as people; label company in drill-down. If `matter_id` is set, show `client_unique_matter_no` (e.g. `TGV_1`).

### Do not use for Layer A

- Actions (`is_action = 1`), including `task_group = Call` or Follow Up
- `notes.title`
- `activities_logs.subject ILIKE '%call%'` (feed heuristics + “added Call Notes”)
- Check-in without In-Person file note
- `not_picked_call`
- Matter notes in `activities_logs` (`use_for = matter`)
- **`workflow_file_notes`** — stage file notes are append-only text with no per-entry author
- ART hearing listed on the sheet without an In-Person note

### Drill-down

Name, client/lead/company, note type, time, snippet, detail link, matter ref if any, MA/PR/PA on that matter.

---

## 5. Layer B — Throughput

Primary: `activities_logs` where `created_by = :staffId` in range. Secondary: `email_logs`, `sms_logs`, `documents`, `notes`.

There is **no** `application_activities_logs`. Stage events are `activities_logs` (`activity_type = stage`, `use_for = matter`). Subjects look like `{matterNo} Stage: {fromStage}` or `Stage: {fromStage}`; description `moved the stage from A to B`. Also: `Matter Discontinued` / `Matter Reopened` / workflow changed.

### 5.1 Deduplicate against Layer A

Skip feed subjects `added {Type} Notes`, `updated {Type} Notes`, `deleted {Type} Notes`. Contact widgets = `notes`. Breakdown = rest of feed (or full feed in “Worked on”).

### 5.2 What to count

Group unique **client id**, unique **matter id** (`client_matter_id`, `notes.matter_id`, or join from stage log + current matter — stage rows are on `client_id` only; resolve matter via subject prefix or latest open matter **only if unambiguous**; prefer `client_matter_id` on docs/emails).

| Source | Meaning |
|--------|---------|
| File notes (all `task_group`) | Worked-on people; Call/In-Person already in A |
| Documents | Personal / visa / nomination uploads |
| Email / SMS | Comms |
| Stage moves | Visa/ART/EOI file progress — strong for PR/PA/MA |
| Decision Received | `decision_outcome` Granted/Refused/Withdrawn — tag on the stage event |
| Financial | Accounts |
| Signatures / 956 / cost agreement | MA-heavy |
| Actions completed | Feed row `completed action for …` (`task_status = 1`) |
| Lead conversion | |
| Appointments | |
| Profile / company / nomination fields | Not contact |
| Matter workflow notes in the feed | Throughput, not A |

**Actions completed — do not read `notes`.** There is **no** completion timestamp on `notes`: completion sets `status = '1'` only, and `AssigneeController::updateActionCompleted` updates **every row sharing `unique_group_id`**, so one click can flip several rows with no per-row time. Count the **activity row** (`subject` `completed action for {assignee}`, `task_status = 1`, `created_by` = actor, `task_group` copied, `use_for` = assignee id when actor ≠ assignee). Caveat: `task_group = 'Client Portal'` completions **skip** that log by design, so they are invisible to this metric. `notes.updated_at` is a rough proxy only.

### 5.3 Suggested throughput tiles

**Worked on today:** unique people; unique matters.

Breakdown: file notes · documents (optionally visa vs nomination vs personal) · emails · SMS · **stage moves** · **decisions recorded** · financial · signatures/956 · profile/other · actions completed · lead conversions.

Optional v1 cheap split: stage moves on ART-type matters vs other visa matters (`ClientMatter::getVisaSheetType()` / `sel_matter_id`).

### 5.4 Secondary tables

`email_logs`, `sms_logs`, `documents`, `notes`, `form956` / cost-agreement tables if feed is incomplete, `matter_reminders` only when `reminded_by` is this staff. `workflow_file_notes` only as a weak “last toucher” hint (`updated_by` + `updated_at`) — it has no feed row and no per-entry author.

Do not scan HTTP logs or “opened client detail.”

---

## 6. Layer C — Caseload (open work)

Not “today,” except overdue-as-of-today and quiet/inactive as of today.

| Metric | Source |
|--------|--------|
| People on their plate | Distinct `admins` with `user_id = staff` **or** open matter where staff is MA/PR/PA. Exclude deleted/archived per list rules. Super-admin-only locked files: only role 1. |
| Matters — unique | Distinct `client_matters` where staff in any of the three columns, `matter_status = 1`, split by stage class |
| Matters — as MA / as PR / as PA | Three counts (same matter can appear in more than one if they hold multiple roles) |
| Quiet / inactive | People and matters with no **qualifying work by this staff** in 7–13d / 14+d (§6.2) |
| No-matter people | `user_id = staff` and zero open matters |
| Open / overdue actions | `is_action = 1`, `assigned_to = staff`, `status <> '1'`. **Two date columns exist:** `action_date` (Action page due/sort, `ActionTaskGroup` follow-up windows) and `note_deadline` (dashboard ordering + the partial index). Pick `action_date` for overdue and say so; do not mix silently |
| Open Call **actions** | `task_group = 'Call'` **and** `is_action = 1` — queue, not Layer A |
| Follow Ups | `task_group = 'Follow Up'` |

Grants: **not** caseload.

### 6.1 Stage workload class

**Schema (new):** `workflow_stages.workload_class`: `active` | `waiting` | `closed` | `unknown` (default `unknown`). Per **stage row** (ART workflow vs General visa workflow can classify “Immi Request Received” differently). Match `client_matters.workflow_stage_id`.

**Admin Console:** dropdown per stage. Optional name hints (`await`, `waiting`, `immi`, `lodged`, `hearing`, `refund`, `withdrawn`, `closed`) — admins override. Frozen stage **names** (`config/workflow.php`: Checklist, Decision Received, Ready to Close, File Closed, Verification*) are **not** workload class.

**Do not** copy ART `status_of_file` into `workload_class` automatically. A matter can be workflow “Application Lodged” and sheet “waiting_for_hearing” only on ART references.

Quiet/inactive can ship before classification is complete.

### 6.2 Quiet (7–13 days) and inactive (14+ days)

By **this staff’s** last qualifying work — not page view, grant, call-not-picked alone, or a colleague’s note.

**Qualifying work:** file notes they created; `activities_logs.created_by`; documents they added; `email_logs.user_id`; `sms_logs.sender_id`. Latest timestamp in app timezone.

| Band | Meaning |
|------|---------|
| **Quiet** | Last work by this staff **7–13 days** ago |
| **Inactive** | **14+ days** ago, or never, on an allocated person / role-assigned matter |

Closed matters (`matter_status != 1`) out of working quiet/inactive lists.

### Shared-file rules (implement exactly)

1. Actor, not assignee, for A and B. PA Call note → only PA `spoke_to`.
2. People caseload: record assignee **or** any matter role. Matter caseload: any of three roles.
3. Same staff in two columns on one matter → **one** unique matter row.
4. Do not rank MA vs PR vs PA on this page.
5. Actions stay `assigned_to`.
6. Quiet/inactive clock is **this staff** only. Colleague MA note does not reset PA’s clock.
7. `admins.user_id` without a matter role still gets the **person** in caseload (calling team / lead chase).

---

## 7. Hours in CRM (prominent header)

Reuse presence. Do not rebuild login tracking.

| Signal | Source |
|--------|--------|
| Online / last seen | `sessions.last_activity` vs 5-minute window (`ActiveStaffService`) |
| In CRM today | `staff_login_logs` `Active in CRM (session)` — one row per day, use `created_at` / `updated_at` (`TrackStaffCrmActivity`, 5-minute throttle) |
| Logged in today | message like `Logged in%` |
| Previous login | latest login row before today |

Document the session-length formula in the service (e.g. first–last presence update today while session is fresh). Top strip, not equal to Spoke to.

No efficiency % from hours × notes. Do not rank by hours.

---

## 8. Follow-ups and office visits (diary, not contact)

| Source | Show as |
|--------|---------|
| Follow Up / other Actions due today | Booked / outcome |
| `checkin_logs` for this staff, date = today | Assigned walk-ins |
| `office_visit_*` | Reception |

Completed visit without In-Person **file note** does not increment `met_*`.

---

## 9. Who sees what

Always current user. Ignore `Staff.role` for whose metrics. No `staff_id` switcher. No Team today.

---

## 10. What not to build

- Call duration timers; keystroke analytics  
- One efficiency %; ranking by feed row count or hours  
- Team today / manager My day  
- Action Call as a consult; follow-up/check-in as Layer A  
- Double-count note + `added Call Notes`  
- Layer A on `notes.title` or `LIKE '%call%'`  
- Ghost credit for MA/PR/PA  
- College/application model from bansalcrm2  
- Sheet views as throughput; grants as caseload  
- Treating Immi/ART **waiting** as **inactive**  
- Using login-analytics “top staff” on this page  

---

## 11. Implementation sketch

`App\Services\StaffWorkloadService` (or split contact / throughput / caseload). Hours from `ActiveStaffService` + today’s `staff_login_logs`.

```
getDaySummary(int $staffId, Carbon $day): array
getContactEvents(...)          // notes, is_action=0, task_group
getWorkedOnClients(...)
getWorkedOnMatters(...)        // include visa-family if cheap
getOpenCaseload(int $staffId): array  // unique matters + MA/PR/PA subcounts + quiet/inactive
getHoursContext(int $staffId, Carbon $day): array
```

No DB in Blade. Date range: app timezone `startOfDay` / `endOfDay`.

### Indexes

**Already in the repo — do not re-add:**

| Index | Migration |
|-------|-----------|
| `notes (type, status, assigned_to, is_action)`, `(type, task_group, action_date)`, `(type, client_id, is_action)`, `(type, status, action_date)` | `2026_02_22_110000_rename_folloup_and_followup_date_in_notes_table.php` (recreated after the `folloup → is_action`, `followup_date → action_date` rename) |
| `notes (type, client_id, is_action, status)`, `(assigned_to, type, is_action, status)` | `2026_04_24_120000_add_performance_indexes_search_documents_notes_activities.php` |
| `notes` partial `(note_deadline, created_at DESC) WHERE type='client' AND is_action=1 AND status<>1` | `2026_08_25_224718_add_notes_dashboard_open_actions_index.php` |
| `activities_logs (task_status, created_by, created_at DESC)` and `(task_status, client_id, created_at DESC)` | `2026_04_24_120000_...` |
| `client_matters (sel_migration_agent\|sel_person_responsible\|sel_person_assisting, matter_status, workflow_stage_id, updated_at)` | `2026_04_22_120000_add_client_matters_dashboard_role_scoped_indexes.php` |
| `client_matters (client_id, sel_*)` visibility indexes | `2026_04_23_100000_add_client_matters_client_id_role_visibility_indexes.php` |

**Likely still needed** (measure with EXPLAIN first):

- `notes (user_id, is_action, created_at)` — Layer A is keyed on **author**, and no existing index leads with `user_id`  
- `activities_logs (created_by, created_at)` and/or `(created_by, activity_type, created_at)` — existing ones lead with `task_status`, so an actor+date scan cannot use them well  
- `email_logs (user_id, created_at)`; `sms_logs (sender_id, sent_at)`; `documents (created_by, created_at)`  

Postgres partial indexes are a good fit here (the repo already uses them), e.g. contact rows only: `WHERE is_action = 0 AND assigned_to IS NULL`.

### UI (phase 1)

1. Hours strip  
2. Contact: Spoke to · Met · New people  
3. Throughput: unique people / matters · stage moves · decisions · actions · docs · email/SMS — **visible when contact is 0**  
4. Call not picked (small)  
5. Caseload: unique matters · **as MA / as PR / as PA** · Active / Waiting · Quiet · Inactive  
6. Lists with matter ref + three names  
7. Optional: actions / follow-ups due today  

New CRM routes, `auth:admin`, own id only.

### Phase 2

Week vs last week; days in stage; waiting↔active tags; due strip; visit-without-note; inbound email; caseload by visa/ART/employer family; CSV.

---

## 12. Pitfalls (from this codebase)

1. **File-note type is `task_group`, not `title`.** Bansalcrm2’s title rule does not apply. Empty `title` is normal (`$obj->title = $request->title ?? ''`).  
2. **`task_group` + `is_action`.** File-note Call vs Action Call. Follow Up / EOI Action groups are never Layer A. The Notes tab also filters `assigned_to IS NULL` — use both guards.  
2a. **Actions have no completion timestamp.** `status = '1'` only, and completion updates **all rows sharing `unique_group_id`**. Use the `completed action for …` feed row (`task_status = 1`) for “completed today”; Client Portal actions skip it.  
2b. **`action_date` vs `note_deadline`.** Both exist on `notes`; the Action page and dashboard use different ones. Choose one for overdue and document it.  
2c. **`workflow_file_notes` is a third note store.** Unique per matter+stage, **append-only** stamped text; `created_by` is only set on the first insert and later appends only touch `updated_by` / `updated_at`, so per-entry authorship is unrecoverable. Writable only on the matter’s **current** stage, and it writes **no** `activities_logs` row — so this work is invisible to the feed. Do not use it for contact; treat it as a weak throughput hint at most.  
2d. **Column renames landed.** `folloup → is_action`, `followup_date → action_date` (2026-02-22). Legacy names appear only in old migrations.  
3. **File-note audit** in `activities_logs` doubles contact if counted with `notes`.  
4. **Three FKs on `client_matters`.** Caseload OR; credit still actor-only. Same person in two columns = one unique matter.  
5. **`admins.user_id` ≠ MA.** Lead/calling assignee vs matter MA/PR/PA.  
6. **Stage logs have `client_id`, not always `client_matter_id`.** Resolve matter carefully.  
7. **`workflow_stage_id` is an FK.** Some legacy code still reads `workflow_stages.w_id`; the model fillable is `workflow_id`.  
8. **ART sheet `status_of_file` ≠ workflow stage ≠ Layer A.** Waiting for hearing is not “met.”  
9. **Decision Received** requires `decision_outcome` Granted/Refused/Withdrawn — throughput/outcome, not contact.  
10. **Company + nomination docs** are still `admins.id` contact; extra throughput on company/nomination tables.  
11. **Visa sheets** are lists — not activity.  
12. **`TrackStaffCrmActivity`:** one row per day; use `updated_at` for presence span.  
13. **DB default `activity_type = 'note'`** on old feed rows.  
14. **Mail table is `email_logs`** (staff column `user_id`, relation `uploader()`). Auto-mail (`conversion_type = 'system_generated'`, receipt/invoice) and fetched inbound (`conversion_email_fetch`) must not inflate personal comms; see `scopeForCrmSentMailbox`.  
14a. **`documents.not_used_doc`** marks unused rows; decide whether they count.  
15. **`use_for = matter`** (legacy `application` migrated).  
16. Super-admin dashboard lists ≠ My day.  
17. Personal Actions may have null `client_id`.

---

## 13. Tests to write

- Call **file note** (`is_action = 0`, `task_group = Call`) increments `spoke_to`; second Call same person same day does not.  
- In-Person file note increments `met` only.  
- File note with empty `title` still counts if `task_group` is Call.  
- `is_action = 1`, `task_group = Call` does **not** increment spoke-to.  
- `added Call Notes` activity does not double contact.  
- Updated Call file note does not increment contact.  
- First-ever In-Person = new-to-firm; later Call by another staff = new-to-staff only.  
- Stage `activities_logs` with this `created_by` increments matters progressed.  
- Decision Received with Granted tags decision throughput for that actor only.  
- Timezone: 23:30 Melbourne is that calendar day.  
- Client assignee A, PR B: Call by B → only B spoke-to; A has person (if `user_id`) and B has matter.  
- MA stage move credits MA only; PA Call credits PA only.  
- Open Action on A’s client assigned to C → only C’s open-action count.  
- Group action (three rows, one `unique_group_id`) completed once → “actions completed” counts the completing actor once, not three times.  
- `task_group = 'Client Portal'` completion writes no feed row → documented as a known gap, not silently counted from `notes`.  
- A `workflow_file_notes` append does **not** increment contact, and does not credit the original `created_by` staff.  
- System-generated receipt email (`conversion_type = 'system_generated'`) does not increment that staff’s email count.  
- Waiting-class matter counts waiting for all three role holders; Call still credits author.  
- `unknown` stage class not lumped into waiting.  
- No-matter lead is not waiting.  
- Grant-only access does not increase caseload.  
- Last work 10 days ago = quiet; 14 days = inactive.  
- Colleague note does not reset this staff’s quiet clock.  
- Call not picked is not qualifying work and not spoke-to.  
- Profile/stage with zero Call notes → throughput yes, spoke-to no.  
- Company client Call counts as people contact.  
- Staff as MA **and** PR on one matter → unique matters = 1; as-MA and as-PR subcounts = 1 each.  
- ART matter in waiting-for-hearing sheet status does not increment `met_*`.

---

## 14. Summary for implementers

**Hours** = presence strip, not a score. **Contact** = created Call / In-Person **file notes** (`is_action = 0`, **`task_group`**, who logged it). Empty contact on a lodge/docs/956/call-not-picked day is expected. **Worked on** = unique people + **matters** (visa applications, ART appeals, nomination/SBS, EOI, skill assessment, bridging) from staff writes. **Open work** = people (`user_id` or MA/PR/PA) + unique matters in any of the **three roles**, with **as MA / as PR / as PA** subcounts, split active/waiting/closed when `workload_class` exists, plus quiet (7–13d) / inactive (14+d) by **this staff**. **My day** = logged-in user only. **No Team today.** **Not in v1** = timers, ranking, ghost credit, treating Immi/ART wait as failure, bansalcrm2 college/application model, Layer A on `notes.title`.

**Three code facts that will bite you if skipped:** file-note type is `notes.task_group` (with `is_action = 0` **and** `assigned_to IS NULL`); “actions completed” comes from the `completed action for …` feed row, not `notes` (no completion timestamp, group updates by `unique_group_id`); `workflow_file_notes` is an append-only per-stage store with no reliable author and no feed row.
