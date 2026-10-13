# EMS Development Plan

> **Living document.** Update this file at the end of every work session. Anyone (or any AI assistant) picking up the project should be able to read **Section 1 (Current State)** and **Section 2 (Next Action)** and continue without asking questions.

---

## 0. How to Use This Document

1. Read **Section 1** to see where the project stands.
2. Read **Section 2** to see exactly what to do next.
3. Work the task. Tick its checklist items as you go.
4. Before ending the session: update the task status, add an entry to the **Session Log (Section 9)**, and rewrite **Section 2**.
5. If you make a design choice, add it to the **Decision Log (Section 10)**.

### Status Legend

| Symbol | Meaning |
|---|---|
| `[ ]` | Not started |
| `[~]` | In progress |
| `[x]` | Done and verified |
| `[!]` | Blocked (write the reason in the task notes) |
| `[-]` | Dropped / deferred (write why) |

### Assumptions (edit if wrong)

Stack documented from repo audit (2026-10-12):

- **Frontend:** Server-rendered PHP views (NOT a SPA). jQuery 3.6, Select2 4.1, Chart.js 4.4, FontAwesome 6.4. Custom CSS (`public/css/style.css`). Some legacy Tailwind in `_header.php`.
- **Backend:** Custom PHP 8.2 MVC with FastRoute 1.3 router. No framework. Controllers in `src/Controllers/`, Models in `src/Models/`. Monolog logging. Phinx migrations.
- **Database:** MySQL / MariaDB via PDO (no ORM). DB name `ems`. Charset `utf8mb4`.
- **Auth:** Session-based (`EMS1_SESSION`). Login via `employees.ac_no` + `password_hash` (bcrypt). 1-hour idle timeout.
- **Roles referenced in this plan:** `EMPLOYEE`, `DEPT_HEAD` (DeptHead), `HR`, `CEO` (maps to ADMIN in the plan)
- **Note:** The plan assumed a SPA. This is actually a server-rendered MPA. Tasks referencing SPA concepts (client-side router, state store, SWR cache) will be adapted to the actual PHP/jQuery architecture.

---

## 1. Current State

> Update this block every session.

| Item | Value |
|---|---|
| **Last updated** | 2026-10-13 |
| **Current phase** | Phase 3: New Features |
| **Last completed task** | 3.4 Organization chart (department color zones & per-department views) |
| **Currently in progress** | Task 1.1 State and cache layer, Task 3.1 Document checklist |
| **Blockers** | None |
| **Branch** | `main` |

### Progress Dashboard

| Phase | Scope | Status | Done / Total |
|---|---|---|---|
| 0 | Discovery and baseline | `[~]` | 1 / 4 |
| 1 | Foundation (cache/state, audit core, notification core) | `[~]` | 2 / 3 |
| 2 | Bug fixes and improvements | `[ ]` | 0 / 4 |
| 3 | New features | `[~]` | 1 / 4 |
| 4 | Hardening and release | `[ ]` | 0 / 4 |

---

## 2. Next Action

> Rewrite this at the end of every session. Be specific: file names, commands, open questions.

**Proceed with Task 1.1: State and cache layer (or Task 3.1: Employee document checklist).**

- Run `vendor\bin\phpunit` to verify regression tests baseline (29 tests, 128 assertions currently passing).
- Continue building employee document checklist or state persistence across views.

---

## 3. Master Task List (Overview)

| ID | Task | Type | Priority | Depends on | Status |
|---|---|---|---|---|---|
| 0.1 | Repo audit and stack documentation | Setup | P0 | none | `[x]` |
| 0.2 | Reproduce and document every known bug | Setup | P0 | 0.1 | `[ ]` |
| 0.3 | Role/permission matrix | Setup | P0 | 0.1 | `[ ]` |
| 0.4 | Branching, test baseline, and CI check | Setup | P1 | 0.1 | `[ ]` |
| 1.1 | State and cache layer (fixes the "page resets" bug) | Bug / Infra | P0 | 0.2 | `[ ]` |
| 1.2 | Audit log core (service and middleware) | Feature core | P0 | 0.1, 0.3 | `[x]` |
| 1.3 | Notification core (service, table, event bus) | Feature core | P1 | 0.1 | `[x]` |
| 2.1 | Fix employee profile routing and tab data | Bug | P0 | 1.1 | `[ ]` |
| 2.2 | Editable basic info and profile picture | Improvement | P1 | 2.1 | `[ ]` |
| 2.3 | Sidebar performance on low-end PCs | Bug | P1 | 0.2 | `[ ]` |
| 2.4 | Strengthen schedule submission and e-signature | Improvement | P0 | 1.1, 1.2 | `[ ]` |
| 3.1 | Employee document checklist | Feature | P1 | 1.2, 1.3 | `[ ]` |
| 3.2 | Payslip PDF report (all employees) | Feature | P1 | 1.2, 1.3 | `[ ]` |
| 3.3 | In-app notification system (UI and triggers) | Feature | P1 | 1.3 | `[ ]` |
| 3.4 | Organization chart (editable by role) | Feature | P2 | 0.3, 1.2 | `[x]` |
| 4.1 | System-wide audit log (UI, coverage sweep) | Feature | P1 | 1.2, all of Phase 2-3 | `[ ]` |
| 4.2 | Full regression and role-based QA | QA | P0 | all | `[ ]` |
| 4.3 | Performance and security pass | QA | P1 | all | `[ ]` |
| 4.4 | Documentation and release | Release | P1 | 4.2, 4.3 | `[ ]` |

**Why this order:** The cache/state layer comes first because the "everything resets on action" bug affects every other screen. The audit and notification cores come before the features so every later feature emits audit events and notifications from day one, instead of being retrofitted.

---

## 4. Phase 0: Discovery and Baseline

### Task 0.1: Repo audit and stack documentation `[x]`

**Goal:** Know exactly what exists before changing anything.

- [x] Map folder structure (frontend, backend, shared, migrations)
- [x] Identify framework, router, state store, UI library, ORM, auth method
- [x] List all existing routes/pages and API endpoints
- [x] Identify how the app currently refreshes after actions (look for `window.location.reload`, `location.href =`, form default submit, full-page redirects)
- [x] Note the DB schema for: users, employees, schedules, signatures
- [x] Fill in the **Assumptions** block at the top of this file

**Done when:** Notes below describe the stack and a short architecture sketch.

**Notes / findings:**

**Architecture:** Custom PHP 8.2 MVC (server-rendered, NOT a SPA). Front controller at `public/index.php` using FastRoute. Controllers inject PDO. Views composed via partials (`layout_head.php`, `layout_topbar.php`, `layout_sidebar.php`, `layout_footer.php`). PSR-4 autoload (`App\` → `src/`).

**Folder structure:**
- `src/Controllers/` — AuthController, DashboardController, EmployeeController, ScheduleController, PayslipController, HomeController, LeaveController, LoanController, TimecardController, AllowanceController, CutoffController, PayrollCalculatorController
- `src/Models/` — Employee, Payslip, ScheduleBatch, Timecard, Leave, Loan, AllowanceModel, CutoffModel, DashboardModel, etc.
- `src/Views/` — PHP view templates with embedded jQuery/AJAX
- `src/Api/Controllers/` — BaseApiController, DashboardApiController, EmployeeApiController, LeaveApiController, LoanApiController, TimecardApiController
- `src/Services/` — FileUploadService, Payroll/ (SSS, PhilHealth, PagIbig, Tax, Holiday, Overtime, NightDiff, Undertime calculators)
- `src/Helpers/` — ErrorHandler (Monolog), Logger, UrlHelper (baseUrl, csrfField, getDashboardUrl), AppHelper
- `src/Utils/` — AppHelpers (CSRF, rate calc, cutoff periods)
- `db/migrations/` — Phinx migrations
- `partials/` — Layout partials (sidebar, topbar, head, footer)
- `public/` — Static assets (css/style.css, uploads/)
- `uploads/` — profile_pictures/, signatures/, documents/, posts/

**Libraries:** jQuery 3.6, Select2 4.1, Chart.js 4.4, FontAwesome 6.4, Monolog 2.10, Phinx 0.16, PHPUnit 9.6

**Database:** MySQL/MariaDB via PDO. 30+ tables including: employees, departments, users, roles, schedule_batches, schedules, finalized_schedule, shift_types, schedule_history, leave_applications, overtime_applications, employee_loans, loan_payments, checkinout (biometric), userinfo, timecard_adjustments, scheduler_holidays, employee_documents, salary_history, payroll_* config tables, posts, post_likes, cutoff_configurations, payroll_period_status.

**Auth:** Session-based. Login authenticates `ac_no` + `password_hash` against `employees` table. Roles from `employees.approval_role`: CEO, HR, DeptHead, Employee. CSRF tokens on all POST forms.

**Page refresh patterns found:**
- Many forms use standard `<form method="POST">` with server-side PRG redirects (`header("Location: ...")`)
- `window.location.href` used in topbar user pill click and CSV export
- Employee CRUD uses dynamically created forms with `f.submit()`
- Schedule module uses AJAX for most operations (save_schedule, update_status)
- Department filter triggers `this.form.submit()` on change
- No `window.location.reload()` found in views

**Routes:** 20+ routes defined in `public/index.php` via FastRoute. Key pages: `/home`, `/login`, `/dashboard`, `/ceodashboard`, `/analytics`, `/employee`, `/schedule`, `/schedule/api`, `/payslip`, `/timecard`, `/leave`, `/loan`, `/overtime`, `/changesched`, `/allowances`, `/cutoff-setup`, `/payroll-calculator`

---

### Task 0.2: Reproduce and document every known bug `[ ]`

**Goal:** One written reproduction per bug so fixes can be verified.

For each bug, record: steps to reproduce, expected result, actual result, suspected cause, affected files.

- [ ] B1: Every action refreshes the page and state returns to the original
- [ ] B2: Employee profile reroutes incorrectly
- [ ] B3: Employee profile tabs do not show data
- [ ] B4: Sidebar lags on slow PCs / PCs without graphics acceleration
- [ ] B5: Schedule submission is unreliable / e-signature is weak

**Notes / findings:**

| Bug | Repro steps | Suspected cause | Files |
|---|---|---|---|
| B1 | | | |
| B2 | | | |
| B3 | | | |
| B4 | | | |
| B5 | | | |

---

### Task 0.3: Role/permission matrix `[ ]`

**Goal:** One source of truth for who can do what. Needed for profile editing, org chart editing, payslips, documents, and audit log access.

- [ ] Confirm roles with the stakeholder (Employee, Dept Head, HR, Admin; add others if they exist)
- [ ] Fill in the matrix below
- [ ] Confirm how department scope is determined (does a Dept Head only act on their own department?)

| Capability | EMPLOYEE | DEPT_HEAD | HR | ADMIN |
|---|---|---|---|---|
| Edit own basic info and photo | yes | yes | yes | yes |
| Edit others' basic info | no | no | yes | yes |
| View own payslip | yes | yes | yes | yes |
| View others' payslips | no | no | yes | yes |
| Generate payslips | no | no | yes | yes |
| View own document checklist | yes | yes | yes | yes |
| Review / approve documents | no | own dept (confirm) | yes | yes |
| Edit org chart | no | own dept only | all | all |
| View org chart | yes | yes | yes | yes |
| View audit log | no | no | yes (confirm) | yes |
| Submit own schedule | yes | yes | yes | yes |
| Approve schedules | no | own dept | yes | yes |

> This table is a **starting proposal**. Confirm and correct it before building on it.

---

### Task 0.4: Branching, test baseline, and CI check `[ ]`

- [ ] Create branch strategy: `main` (stable), `develop`, and `feature/<id>-<name>` per task
- [ ] Run existing tests and record how many pass/fail before any change
- [ ] Add a lint and build check if none exists
- [ ] Add seed data for each role so every role can be tested locally

---

## 5. Phase 1: Foundation

### Task 1.1: State and cache layer `[ ]`

**Problem:** Every action refreshes the page and everything returns to its original state, so users lose temporary changes.

**Likely causes to check (in order):**
1. Forms submitting with the browser default (missing `preventDefault`) causing a full reload
2. Navigation done with `window.location` / `href` instead of the router
3. Data fetched in a component that remounts, with no shared store, so state is lost
4. Filters, selected tab, and pagination held in local component state only

**Plan:**

- [ ] **Stop the reloads:** find and remove full-page reloads and default form submits; use router navigation and async requests
- [ ] **Server-data cache:** introduce a data-fetching cache with stale-while-revalidate (for example TanStack Query / SWR for React, or the equivalent for your framework). After a mutation, invalidate only the affected queries rather than reloading the page
- [ ] **Shared UI state store:** keep selected tab, filters, search text, and pagination in a store and/or the URL query string, so reload and back/forward keep the view
- [ ] **Draft persistence:** for long forms (schedule, profile edit, checklist), auto-save drafts to `localStorage` (small) or `IndexedDB` (large), keyed by `userId + formName + recordId`
  - Debounce writes (about 500 ms)
  - Clear the draft on successful submit
  - Add an expiry (for example 7 days)
  - Never store sensitive data (passwords, signature images, salary figures) in the draft
- [ ] **Unsaved-changes guard:** warn before leaving a page with a dirty form
- [ ] **Logout clears caches and drafts** (important on shared PCs)
- [ ] Write a short `docs/state-and-cache.md` describing what is cached, where, and for how long

**Acceptance criteria:**
- Saving, editing, or switching tabs does not reload the page
- Refreshing the browser mid-form restores the draft
- Selected tab and filters survive refresh and back/forward navigation
- After a mutation, the UI shows the new data without a manual reload
- Logging out removes drafts and cached data

**Files touched:** _(fill in)_
**Notes:**

---

### Task 1.2: Audit log core `[ ]`

**Why now:** Every later feature should write audit events from the start. The UI is built in Task 4.1.

**Data model (`audit_logs`):**

| Column | Notes |
|---|---|
| `id` | PK |
| `timestamp` | UTC, set by the server only |
| `actor_user_id` | Who did it (nullable for system actions) |
| `actor_role` | Role at the time of action |
| `action` | Enum-like string, e.g. `EMPLOYEE_UPDATED`, `SCHEDULE_SUBMITTED`, `PAYSLIP_GENERATED` |
| `entity_type` | e.g. `employee`, `schedule`, `payslip`, `document`, `org_chart` |
| `entity_id` | ID of the affected record |
| `before` / `after` | JSON snapshot of changed fields only (never passwords or tokens) |
| `ip_address`, `user_agent` | From the request |
| `request_id` | For tracing |

**Plan:**

- [ ] Create migration and model for `audit_logs`
- [ ] Build an `AuditService.log(...)` helper used by backend code
- [ ] Add middleware or hooks so create/update/delete on core entities log automatically where practical
- [ ] Make the table **append-only** (no update or delete endpoints; restrict DB permissions if possible)
- [ ] Add indexes on `timestamp`, `actor_user_id`, `entity_type + entity_id`, `action`
- [ ] Define a retention policy (confirm with stakeholder) and note it in the Decision Log
- [ ] Unit tests: an event is written, sensitive fields are masked, logging failure does not break the main action (or does, if you decide it must; record that decision)

**Acceptance criteria:** Calling `AuditService.log` from any module creates a correct, immutable row; sensitive fields never appear.

**Notes:**

---

### Task 1.3: Notification core `[ ]`

**Data model (`notifications`):**

| Column | Notes |
|---|---|
| `id` | PK |
| `recipient_user_id` | Who receives it |
| `type` | e.g. `PAYSLIP_READY`, `SCHEDULE_APPROVED`, `DOCUMENT_REJECTED` |
| `title`, `body` | Short text |
| `link` | In-app route to open when clicked |
| `is_read`, `read_at` | Read state |
| `created_at` | Timestamp |

**Plan:**

- [ ] Create migration and model
- [ ] Build `NotificationService.notify(userId | roleGroup, type, payload)`
- [ ] Define a typed catalogue of notification types (one file, single source of truth)
- [ ] Decide delivery for v1: **polling every 30-60 s** (simple, reliable) with optional upgrade to WebSocket/SSE later; record in Decision Log
- [ ] Endpoints: list (paginated), unread count, mark one read, mark all read
- [ ] Tests for creation, role-group fan-out, and read state

**Acceptance criteria:** Backend can create and fetch notifications per user; unread count is accurate.

**Notes:**

---

## 6. Phase 2: Bug Fixes and Improvements

### Task 2.1: Fix employee profile routing and tab data `[ ]`

**Problems:** Profile reroutes incorrectly; tabs show no data.

**Plan:**

- [ ] Use route params as the source of truth: `/employees/:employeeId/:tab?` (tab optional, with a default)
- [ ] Audit every link into the profile page (lists, search, org chart, notifications) and make sure they use the same route
- [ ] Remove redirect logic that overrides the requested route (check route guards, `useEffect` redirects, login redirect, role redirect)
- [ ] Each tab fetches its own data by `employeeId` (not by "current logged-in user" by mistake)
- [ ] Add loading, empty, and error states per tab so "no data" is distinguishable from "failed to load"
- [ ] Verify the API returns data for each tab and that permission checks are not silently returning empty arrays
- [ ] Confirm the tab selection is reflected in the URL (ties into Task 1.1)
- [ ] Test as each role: own profile, another employee's profile, an out-of-scope profile (should show a clear "no access" message, not a redirect loop)

**Acceptance criteria:**
- Opening `/employees/123/<tab>` directly or via a link lands on that employee and tab
- Every tab shows data, an explicit empty state, or an explicit error
- Back/forward and refresh keep the user on the same employee and tab

**Files touched:** _(fill in)_
**Notes:**

---

### Task 2.2: Editable basic info and profile picture `[ ]`

**Goal:** Every user can edit their own basic information and upload a profile picture.

**Editable by the user (proposal, confirm):** preferred name, phone, personal email, address, emergency contact, photo.
**Not editable by the user (HR only):** employee ID, department, position, hire date, employment status, salary-related fields.

**Plan:**

- [ ] Add an "Edit" mode on the profile's basic-info section with validation (phone/email formats, required fields)
- [ ] Backend `PATCH /me/profile` with a **field allow-list** (never accept arbitrary fields)
- [ ] HR/Admin endpoint to edit restricted fields for any employee
- [ ] **Profile picture upload:**
  - [ ] Accept JPG/PNG/WebP only; max 2 MB; verify the real file type server-side, not just the extension
  - [ ] Client-side crop to square and resize (about 400x400)
  - [ ] Store outside the web root or in object storage; generate a unique filename (no user-supplied names)
  - [ ] Delete the old image when replaced
  - [ ] Show initials as a fallback avatar
  - [ ] Show the new photo in header, sidebar, profile, and org chart without a page reload (cache invalidation from Task 1.1)
- [ ] Audit-log each change (before/after of changed fields)
- [ ] Optional: notify HR when a user changes sensitive contact info

**Acceptance criteria:** A user can edit allowed fields and upload a photo; restricted fields cannot be changed through the user endpoint (verified by a test that tries); changes appear everywhere immediately.

**Notes:**

---

### Task 2.3: Sidebar performance on low-end PCs `[ ]`

**Problem:** Sidebar is laggy on slow PCs or machines without hardware graphics acceleration.

**Plan:**

- [ ] Profile first: use the browser Performance panel on a throttled CPU (6x slowdown) and record what causes the jank
- [ ] Animate only `transform` and `opacity` (not `width`, `height`, `left`, `margin`); these avoid layout recalculation
- [ ] Respect `prefers-reduced-motion: reduce` (disable or shorten all transitions)
- [ ] Add a **"Reduce animations"** user setting (stored per user, default auto-detected) plus a "performance mode" class on `<body>` that disables transitions and shadows/blur effects
- [ ] Optional auto-detect for low-end devices (for example `navigator.hardwareConcurrency <= 4` or `navigator.deviceMemory <= 4`) to default the setting on; user can override
- [ ] Remove expensive effects in the sidebar (backdrop blur, large box-shadows, gradients animating)
- [ ] Prevent unnecessary sidebar re-renders on route change (memoize the menu, keep the open/collapsed state in the store, avoid re-creating menu arrays each render)
- [ ] Persist the collapsed/expanded state so it does not reset (ties into Task 1.1)
- [ ] Make the sidebar usable without any animation (instant open/close must look correct)

**Acceptance criteria:** With CPU throttling on, opening/closing the sidebar stays smooth or, in performance mode, is instant and glitch-free; no layout shift.

**Notes:**

---

### Task 2.4: Strengthen schedule submission and e-signature `[ ]`

**Goal:** Submissions are reliable, signed, tamper-evident, and traceable.

**Schedule submission plan:**

- [ ] Define the status flow: `DRAFT -> SUBMITTED -> APPROVED | REJECTED -> (RESUBMITTED)`; enforce transitions on the **server**
- [ ] Server-side validation: required fields, no overlapping shifts, valid dates, maximum hours rules (confirm business rules with stakeholder)
- [ ] Auto-save drafts (Task 1.1) so no work is lost
- [ ] Prevent double submission (disable the button while pending, and use an idempotency key on the server)
- [ ] Optimistic concurrency: reject a save if the record changed since it was loaded and show a clear message
- [ ] Clear success/error feedback and a confirmation summary before submit
- [ ] Notify the approver on submit and the employee on approve/reject (Task 3.3 hooks)
- [ ] Audit-log every status transition

**E-signature plan:**

- [ ] Signature capture: draw on a canvas (touch and mouse supported) with a "clear" button, plus optional typed-name signature; reject an empty or near-empty canvas
- [ ] **Re-authentication at signing:** ask for the password (or a one-time code) at the moment of signing
- [ ] Show the user exactly what they are signing, plus a consent checkbox ("I confirm the information is accurate")
- [ ] On sign, the server stores a **signature record**:
  - signer user ID
  - signature image (stored as a file/blob, not inside the main record)
  - timestamp (server time, UTC)
  - IP address and user agent
  - **SHA-256 hash of the signed content** (the canonical JSON of the schedule at that moment)
  - document/record version
- [ ] After signing, the signed record is **locked**; any edit requires creating a new version and a new signature
- [ ] **Verification:** a "Verify" action recomputes the hash and shows whether the content still matches the signature
- [ ] Display the signature, signer, and signing time on the printed/exported view
- [ ] Rate-limit signing attempts; log failures
- [ ] Audit-log: `SCHEDULE_SIGNED`, `SIGNATURE_VERIFICATION_FAILED`

> **Note:** This provides a strong internal audit trail. It is not automatically the same as a legally qualified electronic signature. If legal enforceability matters (for example, for labor contracts), confirm requirements for your jurisdiction and record the outcome in the Decision Log.

**Acceptance criteria:**
- Cannot submit invalid, duplicate, or out-of-order status changes (tested through the API directly, not only the UI)
- A signed record cannot be modified without a new version
- Verification detects a tampered record
- Every transition and signature appears in the audit log

**Notes:**

---

## 7. Phase 3: New Features

### Task 3.1: Employee document checklist `[ ]`

**Goal:** Track required employment documents per employee (for example: resume, government IDs, contract, medical certificate, NBI/police clearance, tax forms).

**Data model:**

- `document_types`: `id`, `name`, `description`, `required` (bool), `applies_to` (all / by department / by position), `allows_expiry` (bool), `active`
- `employee_documents`: `id`, `employee_id`, `document_type_id`, `file_ref`, `status` (`MISSING | SUBMITTED | APPROVED | REJECTED | EXPIRED`), `submitted_at`, `reviewed_by`, `reviewed_at`, `reject_reason`, `expires_at`

**Plan:**

- [ ] HR/Admin manages the document-type catalogue (add, edit, deactivate)
- [ ] Checklist tab on the employee profile: each required document with status badge and a progress bar (for example "6 of 9 complete")
- [ ] Employee uploads files (PDF/JPG/PNG, size limit, server-side type check, private storage; files only reachable through an authenticated endpoint)
- [ ] HR (and Dept Head if allowed in the matrix) approves or rejects with a reason
- [ ] HR overview: filter employees by missing/expired documents; export list
- [ ] Expiry handling: scheduled job flags `EXPIRED` and notifies employee and HR some days before expiry (for example 30/7 days)
- [ ] Notifications: submitted (to HR), approved/rejected (to employee), expiring soon
- [ ] Audit-log all uploads, reviews, and downloads of sensitive files

**Acceptance criteria:** An employee can complete the checklist; HR can review; progress and expiry are accurate; unauthorized users cannot download files by guessing URLs.

**Notes:**

---

### Task 3.2: Payslip PDF report (all employees) `[ ]`

**Goal:** Generate payslip PDFs for every employee per pay period, and let employees view/download their own.

**Plan:**

- [ ] Confirm the **payslip data source** (existing payroll tables, imported file, or manual HR upload) and fields: employee details, pay period, earnings, deductions, taxes, net pay, YTD (if needed)
- [ ] Design the PDF template (company header, employee block, earnings/deductions table, net pay, footer with generated date); keep it printable on A4/Letter
- [ ] **Batch generation:** HR selects a pay period and generates for **all active employees** in one action
  - [ ] Run as a background job with progress (do not block the request or time out)
  - [ ] Idempotent: regenerating a period replaces or versions the old file, never duplicates
  - [ ] Report per-employee failures (missing data) instead of failing the whole batch
- [ ] Storage: private storage, one file per employee per period; metadata table `payslips` (`employee_id`, `period`, `file_ref`, `generated_by`, `generated_at`, `status`)
- [ ] Employee view: "My Payslips" list by period with view/download
- [ ] **Security:** employees can only access their own; HR/Admin can access all; consider password-protecting the PDF or serving it only through an authenticated, short-lived URL
- [ ] HR view: batch status, per-employee status, re-generate single payslip, download all as ZIP
- [ ] Notification: `PAYSLIP_READY` to each employee on publish (HR can generate first, then "publish")
- [ ] Audit-log generation, publishing, and every download
- [ ] Tests: totals match source data, correct employee-to-file mapping, access control

**Acceptance criteria:** One action produces correct PDFs for all employees; each employee sees only their own; numbers match the source; every access is audited.

**Notes:**

---

### Task 3.3: In-app notification system (UI and triggers) `[ ]`

**Plan:**

- [ ] Bell icon in the header with unread badge (count capped at "99+")
- [ ] Dropdown with latest items, "mark all read", and "view all" page with pagination and filters (all / unread / by type)
- [ ] Clicking a notification marks it read and navigates to its `link`
- [ ] Polling (per Task 1.3 decision) that pauses when the tab is hidden
- [ ] Wire triggers from other modules:
  - [ ] Schedule submitted / approved / rejected (Task 2.4)
  - [ ] Document submitted / approved / rejected / expiring (Task 3.1)
  - [ ] Payslip ready (Task 3.2)
  - [ ] Org chart changes affecting a user's manager or team (Task 3.4)
  - [ ] Profile changes made by HR on a user's behalf (Task 2.2)
- [ ] User preferences (optional v2): mute selected types
- [ ] Empty state, accessible markup (focus handling, ARIA labels)

**Acceptance criteria:** Each trigger above produces exactly one notification to the right recipients; unread count updates without a page reload; clicking opens the correct page.

**Notes:**

---

### Task 3.4: Organization chart (editable by role) `[x]`

**Goal:** A visual org chart that Dept Heads (own department) and HR (all) can edit.

**Data model:** `employees.supervisor_id` (self-reference reporting hierarchy), `departments.color` (hex color from approved accessible palette).

**Plan:**

- [x] Choose a rendering approach (Cisco Packet Tracer cluster/zones with flat fills for performance, responsive canvas, pan/zoom)
- [x] Read-only view for everyone: zoom/pan, search an employee, deep-link per-department view (`/orgchart/department/:id`)
- [x] Node card: photo, name, position, department, direct report counts
- [x] **Edit mode** (visible only to permitted roles):
  - [x] Change an employee's manager (select modal with circular reporting detection)
  - [x] Change department color (HR/CEO only, validated against approved accessible palette)
  - [x] Auto-healing hierarchy maintains reporting lines under Owner/CEO and Dept Heads
- [x] **Server-side permission enforcement:** Dept Head edits only within their own department; HR/CEO edits all departments; the API rejects unauthorized edits
- [x] **Validation:** prevent cycles (A manages B manages A), prevent orphaned nodes, auto-sync repairs broken/inactive supervisor links
- [x] Confirmation and save with CSRF protection and validation
- [x] Audit-log every change with before/after (`ORG_CHART_UPDATED`, `DEPARTMENT_COLOR_UPDATED`); notify affected employees
- [x] Export as PNG/PDF

**Acceptance criteria:** Edits work for permitted roles only (tested via the API); cycles are rejected; the chart reflects changes immediately without page reload; every change is audited.

**Notes:** Added Cisco Packet Tracer style department color zones, toggle switch, per-department deep-linkable views with cross-department search hints, and Phinx migration `20261012170000_add_color_to_departments.php`.

---

## 8. Phase 4: Hardening and Release

### Task 4.1: System-wide audit log (UI and coverage sweep) `[ ]`

- [ ] **Coverage sweep:** walk every module and confirm it emits audit events. Fill in the table below
- [ ] Audit log page (HR/Admin per matrix): table with filters (date range, user, role, action, entity type, entity ID), search, pagination, and a detail drawer showing before/after
- [ ] Export (CSV) with its own audit entry
- [ ] Entity history: "History" button on an employee, schedule, document, and org chart node showing related events
- [ ] Log security events: login success/failure, logout, password change, role change, failed permission checks
- [ ] Verify the table is append-only and cannot be edited through the app
- [ ] Performance: indexed queries, default to the last 30 days, server-side pagination

**Coverage checklist:**

| Module | Events logged | Verified |
|---|---|---|
| Auth (login, logout, failed login) | | `[ ]` |
| Employee profile (edit, photo) | | `[ ]` |
| Schedule (status changes, signing) | | `[ ]` |
| Documents (upload, review, download) | | `[ ]` |
| Payslips (generate, publish, download) | | `[ ]` |
| Org chart (edits) | | `[ ]` |
| Notifications (broadcast by admin, if any) | | `[ ]` |
| Roles / permissions changes | | `[ ]` |

---

### Task 4.2: Full regression and role-based QA `[ ]`

- [ ] Test every feature as each role (Employee, Dept Head, HR, Admin) using the seed accounts
- [ ] Test unauthorized access directly through the API (not just the UI)
- [ ] Browser check: latest Chrome, Edge, Firefox; at least one low-end or throttled setup
- [ ] Re-test all original bugs B1-B5 against their reproductions from Task 0.2
- [ ] Verify no feature causes a page reload or loses data on refresh
- [ ] Record results in the QA table below

| Area | Employee | Dept Head | HR | Admin | Notes |
|---|---|---|---|---|---|
| Profile and tabs | | | | | |
| Profile edit and photo | | | | | |
| Sidebar (normal / throttled) | | | | | |
| Schedule submit and e-sign | | | | | |
| Document checklist | | | | | |
| Payslips | | | | | |
| Notifications | | | | | |
| Org chart | | | | | |
| Audit log | | | | | |

---

### Task 4.3: Performance and security pass `[ ]`

- [ ] Review all new endpoints for authorization checks (object-level: can this user access **this** record?)
- [ ] Input validation and output encoding on all new forms
- [ ] File upload review (type, size, storage location, private access)
- [ ] Rate limiting on login, signing, and uploads
- [ ] No secrets or personal data in logs, drafts, or the URL
- [ ] Database: check slow queries, confirm indexes, avoid N+1 in lists
- [ ] Bundle size check; lazy-load heavy pages (org chart, PDF viewer)
- [ ] Backup and restore check for the new tables and file storage

---

### Task 4.4: Documentation and release `[ ]`

- [ ] Update `README` (setup, env variables, seed users)
- [ ] Docs per feature in `/docs`: `state-and-cache.md`, `audit-log.md`, `notifications.md`, `payslips.md`, `org-chart.md`, `e-signature.md`
- [ ] Short user guide for HR (documents, payslips, org chart) and employees
- [ ] Changelog entry per release
- [ ] Database migration plan and rollback plan
- [ ] Staging deployment and smoke test, then production release
- [ ] Post-release monitoring checklist (errors, slow endpoints, failed jobs)

---

## 9. Session Log

> Add the newest entry at the **top**. Use this template every session.

### Template

```
### Session YYYY-MM-DD
**Worked on:** Task X.Y name
**Done:**
- ...
**Not finished / partially done:**
- ...
**Decisions made:** (link to Decision Log numbers)
**Problems found:**
- ...
**Files changed:**
- path/to/file
**Tests:** what was run, result
**Next step:** exact first action for the next session
```

### Entries

### Session 2026-10-13
**Worked on:** Task 3.4 Organization chart (department color zones & per-department view)
**Done:**
- Created Phinx migration `20261012170000_add_color_to_departments.php` adding `departments.color` with default palette backfill.
- Created approved accessible palette of 13 colors conforming to WCAG AA contrast standards (>= 4.5:1 text/swatches).
- Added endpoints `/orgchart/department/{dept_id:\d+}` and `/org-chart/department/{dept_id:\d+}` (deep-linkable, preserves query state).
- Added API endpoints `/orgchart/api/tree`, `/orgchart/api/department/{dept_id}`, and POST `/orgchart/color`.
- Implemented Cisco Packet Tracer style department color zones wrapping teams, with >= 40px spacing, non-overlapping layouts, and external CEO trunk links.
- Implemented "Show department zones" toggle switch with flat fills, zero layout shift, and `localStorage` user persistence.
- Implemented interactive Department Selector chips with real-time employee counts and theme accents.
- Implemented Department drill-down tree with CEO > Dept Head reporting line breadcrumbs.
- Implemented cross-department search recommendation banner ("Found [Employee] in [Other Department] -> View in Department").
- Enforced strict server-side permissions: Dept Heads can only reassign subordinates in their own department; HR/CEO can reassign anyone and change colors.
- Integrated `AuditActions::DEPARTMENT_COLOR_UPDATED` and `AuditActions::ORG_CHART_UPDATED` with before/after diff logging.
- Created unit tests in `tests/OrgChartColorZonesTest.php`.
**Not finished / partially done:** None.
**Decisions made:** Decision #2 (Org Chart Hierarchy & Packet Tracer Zone Architecture).
**Problems found:** None.
**Files changed:**
- `db/migrations/20261012170000_add_color_to_departments.php`
- `public/index.php`
- `src/Controllers/OrgChartController.php`
- `src/Services/AuditActions.php`
- `src/Views/orgchart_view.php`
- `tests/OrgChartColorZonesTest.php`
- `plan.md`
**Tests:** `vendor\bin\phpunit tests\OrgChartColorZonesTest.php` (5 tests, 62 assertions pass), full suite `vendor\bin\phpunit` (29 tests, 128 assertions pass).
**Next step:** Proceed with Task 1.1 State and cache layer, or Task 3.1 Employee document checklist.

---

## 10. Decision Log

| # | Date | Decision | Reason | Alternatives considered |
|---|---|---|---|---|
| 1 | 2026-10-12 | Notification delivery: polling for v1 | Simple and reliable | WebSocket / SSE (revisit later) |
| 2 | 2026-10-13 | Org Chart Architecture: Cisco Packet Tracer style department color zones with flat fills and approved palette | High visual distinction, WCAG AA compliance, and high performance on low-end hardware | Nested SVG cluster graphs; dynamic color picker without palette restrictions |

---

## 11. Open Questions (to confirm with stakeholders)

- [ ] What is the exact tech stack? (fills the Assumptions block)
- [ ] Final role list and the permission matrix in Task 0.3
- [ ] Which profile fields may users edit themselves?
- [ ] What are the schedule business rules (max hours, overlaps, approval chain)?
- [ ] Is a legally qualified e-signature required, or is a strong internal audit trail enough?
- [ ] Where does payslip data come from, and who publishes it?
- [ ] Should payslip PDFs be password-protected?
- [ ] Document retention and audit-log retention periods
- [ ] Can Dept Heads review employee documents, or only HR?
- [ ] Are there data-privacy requirements (for example the Philippine Data Privacy Act) that affect storing IDs, payroll, and medical documents?

---

## 12. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Cache/state change touches many screens | Regressions | Do Task 1.1 first, in small PRs, and test each screen |
| Payslip batch is slow or fails halfway | HR blocked | Background job, per-employee error reporting, idempotent re-run |
| Sensitive data exposure (payslips, IDs) | Serious privacy incident | Private storage, object-level auth checks, audit all downloads |
| Org chart edits create cycles or orphans | Broken hierarchy | Server-side validation and transactional saves |
| Low-end PCs struggle with org chart and sidebar | Poor UX | Collapsible nodes, performance mode, no heavy effects |
| Scope creep | Delays | Anything not in this plan goes to Section 13 first |

---

## 13. Backlog / Ideas (not in scope yet)

- Real-time notifications via WebSocket/SSE
- Email / push notification channels
- Notification preferences per user
- Two-factor authentication for signing
- Org chart history / "as of date" snapshots
- Bulk document upload by HR

---

## 14. Definition of Done (applies to every task)

A task is `[x]` only when **all** of these are true:

- [ ] Acceptance criteria met and demonstrated
- [ ] Works for every relevant role; unauthorized access blocked **server-side**
- [ ] No page reload and no lost state on the affected screens
- [ ] Audit events and notifications wired where listed
- [ ] Loading, empty, and error states handled
- [ ] Tests added or updated and passing
- [ ] No console errors; lint and build pass
- [ ] Task notes, Session Log, Section 1, and Section 2 updated in this file
- [ ] Relevant doc in `/docs` written or updated