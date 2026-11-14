# PmOps: product and build spec

For Claude Code, working in the `talhaimran/PM-Ops` repo. Version 1.3, Nov 5, 2026. Author: Talha.

Changes in 1.3: a UI section (5.1) that makes the PmOps design system part of every step; a new UI foundation step (U) before step 0's screens; Linear/Selvar style replaces the Jira-style UI; full issue page plus a peek drawer; Undo, confirm and preview rules; soft delete for issues; agents are never assignees; status and issue type colours; Motion for animation; "Focus" grouping for My issues; empty states; board density and totals in the project's unit. Changes in 1.2: a workspace status library from step 1 (7.4); workflow data model (7.3); approval requests (A2); audit events listed per feature; Kanban handling of Won't do; @mention groups moved to step 5; detail restored from `design-decisions.md`. 1.1 added the Platform management contract.

This one document replaces the four planning docs (`design-decisions.md`, `agents-spec.md`, `editions-and-cloud.md`, `build-spec-step-0.md`). "PmOps" is the working product name; the GitHub repo is renamed to `talhaimran/PM-Ops` and user-facing text (README, AGENTS.md, layouts, `APP_NAME`, the MCP server display name) says "PmOps"; internal identifiers keep their names (`pm_monkey` databases, the `pm` MCP handle, `PM_*` env vars, `pm.via`, `Pm*` classes).

---

## 0. How to use this spec

1. Save this file in the repo as `docs/specs/pmops-spec.md`. Add one line to the top of `SPEC.md`: "The MVP below is built. Further work follows `docs/specs/pmops-spec.md`."
2. Build **one step at a time, in the order of section 6**. Don't start a step until the previous one is merged.
3. Before each step, use `superpowers:writing-plans` to write a plan for that step from this spec and show it to the user. Wait for "go".
4. Follow `CLAUDE.md` as always: services hold the logic, web/API/MCP stay thin, Form Requests, Policies, Pest on MySQL, and the per-slice checklist (migrate → tests → `npm run build` → security review → PROGRESS.md → commit).
5. **Stop and ask** when this spec is silent, contradicts itself, contradicts `CLAUDE.md`, or needs a dependency that isn't listed as approved in that step.
6. Items marked **(leaning)** are proposals the user hasn't confirmed. Build them as written unless the user says otherwise, and list them in the plan for that step so the user can change them.
7. Section 7 onwards holds the detailed requirements per feature. Section 6 says which feature sections belong to which step. **Every step with screens also follows section 5.1** and the design system it points to.

---

## 1. Product

PmOps is a lightweight project tracker that people use through a calm, fast, keyboard-friendly web UI and AI agents use through MCP and a REST API. Humans and agents work on the same issues, under the same rules.

### Principles (apply to every step)

1. **Agents are controlled by their user.** An agent does only what its owner asks on a ticket. It never picks up work on its own. Agents can't be @mentioned, assigned or directed by anyone else.
2. **Same rules for people and agents.** Web, REST and MCP call the same services, so permissions, workflow rules, plan limits and edit conflict checks always apply, whoever acts.
3. **Visible and reversible.** Every change records who made it, through which channel (`via`), and which agent. Agents can be paused or revoked at any time. In the web UI, single changes can be undone from a toast (5.1).
4. **Warn, don't block** by default. Soft rules (failing tests, blocked issues, WIP limits, date conflicts) warn. In the web UI a warning needs confirmation; in API/MCP responses it comes back in a `warnings` array and the action still happens. Hard blocks are used only where a decision says so.
5. **Vendor-neutral AI.** PmOps has no AI of its own and never resells AI. Workspaces bring their own provider and key.
6. **Same features in every edition.** Self-hosted is never feature-limited. Cloud differs only in limits, hosting and billing.
7. **Simple over Jira.** One layer of configuration, readable names (keys like `severity`, not `customfield_10042`), errors that list valid options and explain the fix.
8. **Calm by default.** A neutral workspace with colour only in small accents, good defaults instead of admin-only layout setup, and no fields shown just because they exist (5.1).

---

## 2. Current state of the repo

Built (see `README.md`, `PROGRESS.md`): Laravel 13 + MySQL, Inertia + React 18 + TypeScript + Tailwind 3 (Breeze), with `@headlessui/react` and `@dnd-kit` already installed. The UI is currently Jira-style: colour tokens are CSS variables defined in `tailwind.config.js` (light, and dark via the `.dark` class) and there's a theme toggle. Projects with keys and per-project roles (Admin, Member, Viewer); issues (Task, Bug, Story, Epic) with priority, assignee, labels, story points, due date, epic and one level of sub-tasks; Markdown comments; activity log with `via` (web/api/mcp) and token name; Kanban board with configurable columns; backlog and sprints; list view, search, dashboard; Sanctum tokens scoped to projects and read/write; Idempotency-Key, per-token rate limit, bulk create/update; 36 MCP tools over HTTP and stdio; Scramble OpenAPI docs.

Facts later steps depend on:
- All project access goes through `User::accessibleProjects()`. Never query `projects()` for access.
- An issue counts as done when it's in the project's **last column** (`Project.php`). Step 1 replaces this.
- `IssueType` is a PHP enum; epics use `epic_id`, sub-tasks `parent_id`.
- Issues are deleted for real today; step U adds soft delete (5.1).
- There's no workspace concept, no notifications, no attachments, no links, no history tables for charts.

---

## 3. Editions and architecture

| | Self-hosted | Cloud Free | Cloud Paid |
|---|---|---|---|
| Source | Public repo, **AGPL-3.0** | Hosted | Hosted |
| Price | Free | Free | $6 per billed user/month (annual), $7.50 monthly |
| Trial | n/a | 14 days of Paid on sign-up (no card) | n/a |
| Workspaces | Exactly one per install | One per sign-up; users can belong to many | Same |
| Features | All | All, within limits (section 4) | All |
| AI | Own provider in `.env` | Own provider in Settings → AI | Same |
| Support | Community | Community | Email |

**Two repos:**

| Repo | Visibility | Holds |
|---|---|---|
| **Core** (this repo) | Public, AGPL-3.0 | The whole product, workspaces, accounts, entitlements check, internal admin API, system events |
| **Platform management** | Private, separate Laravel app | Stripe billing (Cashier), seats, add-ons, trials, dunning, staff console, workspace lifecycle; pushes entitlements to the core |

- **No private code runs in the core.** Self-hosted and Cloud run the same public build. `PM_EDITION=self_hosted|cloud` switches between one and many workspaces.
- **Platform management → core:** internal admin API with a service token (step 3).
- **Core → Platform management:** signed, retried **system events** using the webhook machinery (step 3).
- **Billing link:** "Billing" in workspace settings opens Platform management through a short-lived signed link. Shown only to Owners and only when `PM_BILLING_URL` is set.
- If Platform management is down, the core keeps working on its last entitlements. A nightly reconciliation in Platform management fixes drift.
- **CLA:** outside contributions to the public repo need a Contributor Licence Agreement (e.g. CLA Assistant) before the first one is merged. Not a code task; flag it to the user when preparing the repo for contributions.

Platform management has its own spec (`platform-management-spec.md`) and its own repo; section 9 lists the contract this repo builds for it.

---

## 4. Workspaces, roles, billing terms and plan limits

### Workspace and roles

A **workspace** holds projects, users, groups, shared workflows and fields, agents, AI settings and entitlements. Two layers of roles:

- **Workspace role:** Owner, Admin, Member.
- **Project role** (exists today): Admin, Member, Viewer.

| Ability | Owner | Admin | Member |
|---|---|---|---|
| Billing and plan, add-ons, payment method | ✅ | – | – |
| Delete the workspace | ✅ | – | – |
| Add/remove Owners, transfer ownership | ✅ | – | – |
| Rename, slug, logo | ✅ | ✅ | – |
| Invite/remove/deactivate users, change workspace roles (up to Admin) | ✅ | ✅ | – |
| User groups | ✅ | ✅ | – |
| Security settings (require 2FA, sign-in methods) | ✅ | ✅ | – |
| Audit log | ✅ | ✅ | – |
| AI provider, agent policy (turn agents/runs off, pause any agent) | ✅ | ✅ | – |
| Shared libraries (workflows, fields, issue types, templates), project categories | ✅ | ✅ | – |
| See every project, join as project Admin, archive/restore/delete any project | ✅ | ✅ | – |
| "Who can create projects" setting | ✅ | ✅ | – |
| Usage and limits | ✅ | ✅ | Own usage only |
| Create projects | ✅ | ✅ | If allowed (default: allowed) |
| Own agents, personal dashboards, private saved filters, member directory | ✅ | ✅ | ✅ |

Rules: at least one Owner always; the last Owner can't leave, be removed or demoted; Admins can't change Owners; agents never have workspace roles and never get settings tools; every role change goes in the audit log. On self-hosted the first registered user becomes Owner.

### Billing terms

- **Billed users:** workspace Owners and Admins, plus anyone who is Member or Admin in at least one project. Pending invitations that would create one count too.
- **Viewers:** every other workspace user (only Viewer in all their projects, or in none). Free.
- **Agents** are never billed. Agent tokens can't sign in to the web UI.
- Adding a billed user is charged immediately, pro-rated. Removing one gives no credit; the seat can be reused until renewal. Viewer → Member counts as adding.
- Storage on Paid: 10 GB per billed user, pooled. Add-ons (start: +50 GB for $5/month) are billed monthly, also on annual plans.
- Failed payment: 2 weeks of Stripe retries, then the workspace drops to Free.

### Plan limits (entitlements)

The core stores per-workspace **entitlements** and checks them. **No entitlement row = unlimited/enabled** (self-hosted default). Platform management sets the values below on Cloud.

| Entitlement key | Free | Paid | Enforced in step |
|---|---|---|---|
| `billed_users` | 2 | unlimited | 0 |
| `viewers` | 3 | unlimited | 0 |
| `projects` | 1 | unlimited | 0 |
| `api_per_minute` / `api_per_day` (per agent token) | 60 / 2,000 | 120 / 10,000 | 0 (moved per agent in A) |
| `agents_per_user` | 2 | 10 | A |
| `agent_runs_per_day` (per user) | 20 | 200 | A |
| `storage_mb` | 1,024 | 10,240 × billed users + add-ons | 6 |
| `max_file_mb` | 10 | 25 | 6 |
| `saved_filters_per_user` | 2 | unlimited | 4 |
| `dashboards_per_user` | 1 | unlimited | 10 |
| `history_days` (reports and activity shown) | 90 | unlimited | 10 |
| `custom_fields_mode` / `custom_fields_enabled_max` / `custom_fields_own_max` | `catalog_only` / 3 / 0 | `full` / unlimited / 25 | 11 |
| `webhooks`, `github` (bool) | false | true | 7 |
| `plan`, `trial_ends_at`, `billing_notice` (informational, section 9) | set by Platform management | set by Platform management | 3 |

- **Hitting a limit blocks the action** with an upgrade prompt (web) or a `plan_limit` error (API/MCP, section 7.0c).
- **Dropping to Free** (downgrade, trial end, failed payment) makes everything over the limit **read-only**; nothing is deleted. **(leaning)** projects beyond the limit, least recently active first, become read-only; users beyond the billed limit, most recently added first, act as Viewers until resolved. Built with Platform management, not before.
- **Export is always available**, on every plan and in every state.
- 2FA, enforced 2FA and the audit log are on every plan.
- **Inactive Free workspaces** (12 months without activity) are archived by Platform management after warning emails at 30 and 7 days; sign-in offers "Restore". Archived workspaces and audit log entries don't expire for now.

---

## 5. Conventions for all new code

In addition to `CLAUDE.md`:

- **Workspace scope.** Every new top-level table has `workspace_id`. Child tables reach the workspace through their project. Get the current workspace from the `CurrentWorkspace` service only.
- **Plan limits.** Call `Entitlements::ensure($workspace, Entitlement::X, $adding)` before any action that increases a limited count. Define new keys in the `Entitlement` enum.
- **Audit vs activity.** Admin and security events go to `AuditLogger` (workspace audit log). Changes to issues go to `ActivityLogger` (per-issue activity). Both record actor, agent (once agents exist), `via` (`web`, `api`, `mcp`, `agent_run`, `cli`) and never secrets.
- **Warnings.** Soft rules return `warnings: [{code, message, …}]` in API/MCP responses and need confirmation in the web UI. Don't throw for warnings.
- **Big actions need `confirm: true`** in API/MCP: deletes, bulk edits over 25 issues, completing a sprint, releasing a version. Without it, return a preview.
- **Deletes are soft.** Deleting an issue sets `deleted_at` (5.1); every query, search, board, export and MCP tool leaves deleted issues out.
- **Edit conflicts.** Once step 1 lands, every update to an issue or comment carries the version it read; a mismatch returns a conflict that shows what changed.
- **Agents never get settings tools.** Workspace and project settings, members, roles, workflows, field definitions, webhooks, AI keys and tokens stay web-only for people.
- **Readable input everywhere.** Accept issue keys, emails, column/label/version names; return errors that list the valid options.
- **Cloud Free gates** use entitlements, never `if edition == cloud`.
- **Every new MCP tool** is a thin wrapper over the same service, added to `PmServer::$tools` and documented in `AGENTS.md`.
- **History for charts.** From step 1, status changes, sprint scope changes and estimate changes are recorded in their history tables (7.13).
- **Statuses, not columns.** From step 1 an issue has a status from the workspace status library (7.4). Done-ness, history, rules and reports use the status and its category; never a column's position.
- **Audit every settings change.** Any change to workspace, project, agent, AI provider, workflow, field, webhook or GitHub settings writes an `AuditLogger` event. Each feature section lists its events; add one for any settings change a section forgets.
- **Approvals.** Anything an agent may not do alone (moving to Done today) goes through `approval_requests` (A2), never a feature-specific queue.
- **UI.** Every screen follows section 5.1 and the design system. New UI pieces become design system components first, then React components.

### 5.1 UI and design system

**Source of truth:** the **PmOps design system** (a Design System artifact in the user's claude.ai account; start with its `README.md`). It holds the tokens (colours in light and dark, type, spacing, radius, shadows), the components with their anatomy, states and keyboard behaviour, the motion page and the accessibility page. When this spec and the design system disagree, this spec wins; flag the difference so the design system gets fixed.

**Style:** calm, fast and keyboard-friendly, in the style of Linear and the Selvar demo. This replaces the current Jira-style UI. Selvar is a visual reference only: write all CSS and components from the design system, never copy Selvar's stylesheet or code.

**Stack and approved dependencies (step U):**
- Keep React 18, TypeScript, Inertia and Tailwind 3. Move the design system's tokens into the existing `tailwind.config.js` token pattern (CSS variables switched by `.dark`, exposed as Tailwind colours), replacing the Jira tokens. Spacing, radius, shadow and type scales come from the design system too.
- Keep `@headlessui/react` for dialogs, menus, listboxes and comboboxes (focus and keyboard handling) and `@dnd-kit` for drag and drop.
- Approved new dependencies: `motion` (animation, MIT), `lucide-react` (icons, 1.8px stroke as in the design system) and `@fontsource/urbanist` (self-hosted font files, OFL). Anything else: ask first.

**Motion:** Motion for anything that moves through space (drag lift and settle, board reordering, the drawer, dialogs, the palette, toasts); plain CSS for hover, focus and colour changes, and the browser's View Transitions for the theme toggle. Timings and easings come from the design system's motion page, kept in one `motion.ts` file. **One reduced-motion switch:** when the OS asks for reduced motion, every duration is 0 and content still appears. Nothing loops or plays on its own on working screens.

**Accessibility:** WCAG 2.2 AA. The design system's accessibility page lists pass/fail checks (focus rings, keyboard reach, colour never alone, target sizes, dialogs, motion, live regions, text size); they're part of "done" for every UI slice.

**Themes and sizes:** light and dark themes on every screen (keep the toggle). Under 760px the sidebar becomes a sheet and drawers and dialogs rise from the bottom.

**Issue view:**
- The **full issue page** at `/{workspace}/issues/{KEY}` is the canonical view and holds every panel (description, fields, child issues, links, attachments, development, test cases, watchers, activity, the AI box).
- A **peek drawer** opens the same issue over the board, list view and search, so people keep their place. It shows the summary, key fields, description and the AI box, with "Open full page" and "Copy link". Opening it adds `?issue={KEY}` to the current URL so reload and back work; shared links always use the full page URL.
- **The AI box** (in both): pending approval requests for the issue, the latest agent run with its proposed change set, and the "Ask agent" entry (8, Mode C). Parts that need an AI provider are hidden without one. It never shows an automatically generated summary.

**Agents in the UI:** agents are **never assignees** and never appear in assignee pickers. Wherever an agent acted (activity, comments, reactions, approvals, notifications) it shows with the design system's agent avatar and the label "{Owner}'s agent" (or its name).

**Undo, confirm and preview:**
- **Single changes you can reverse** (move, status, assignee, field edit, delete of one issue or comment) apply at once and show a toast with **Undo** (6s; ⌘Z/Ctrl+Z also undoes while it shows).
- **Rule warnings** (blocked issue, failing tests, WIP limit, date conflicts, plan limits) need an inline confirm before the change, as principle 4 says. Undo never replaces this.
- **Deletes of several items and bulk edits** show a preview first ("Delete 12 issues", "Change priority on 34 issues"), then a toast with Undo for the whole batch.
- **How Undo works:** it's a new change in the opposite direction through the same services, so permissions, rules and edit conflict checks apply and it's logged like any change ("undid move"). If the issue changed since (someone else, or an agent), Undo stops and the toast says what changed instead of overwriting it.

**Soft delete for issues (step U):**
- `deleted_at` on issues (and on comments). Deleting hides the issue everywhere; its key is never reused; its links, attachments and history stay until it's purged.
- Restored by Undo, or by project Admins from "Deleted issues" in project settings. **(leaning)** purged for good after 30 days.
- API and MCP deletes are soft too; `confirm: true` stays required.
- Activity events `issue.deleted`, `issue.restored`; purges are logged as `issue.purged`.

**Colour of statuses and types:** a status takes its colour from its category by default (To Do, In Progress, Done); Admins can set an optional custom colour per status (`statuses.color`, 7.4). Each issue type has an icon and a colour (`issue_types.color`, 7.1). Colour is never the only signal: always beside a label or a distinct icon.

**Live updates (step 3 onwards):** changes from other people and agents animate in gently (a card slides to its new column, a field fades to its new value). They never take focus, never move a card someone is dragging, and never replace text someone is editing (edit conflict protection, 7.11, handles that).

**Board:**
- Column totals show the sum of estimates in the project's unit (points or hours, 7.13), plus the issue count.
- Above 12 cards in a column, cards switch to the compact density (title and key); each user can override the density. Above 20 cards, the column shows the first 20 and "Show N more".

**Empty states:** every list, board, panel and settings page has one. One line saying what goes here and how to add it, with the action and its shortcut: "No issues yet. Create your first issue · C"; "Nothing in review. Drag an issue here."; "No issues match these filters." with "Clear filters". No product tours.

**My issues "Focus" grouping (from step 2, once links exist):** the default grouping for My issues. Each open issue sits in the first group it qualifies for: waiting on you (approval requests from your agents, unanswered @mentions) → blocking others → urgent or overdue → due in the next 3 days → in progress → to do. People can switch to status, priority, project or sprint, and the choice is remembered.

**Words:** "sprint", not "cycle". Sentence case, verbs on buttons, numbers with units ("13 pts", "3 days left"), as in the design system's voice section.

**Components:** the design system covers the app shell (sidebar, workspace switcher), buttons and fields, pills and chips, the issue card, board column, issue drawer, toast, command palette, KPI tile and avatars, plus the components later steps need: approval request card, change-set diff, edit conflict dialog, plan-limit dialog, warning confirm, agent run panel, notification inbox, filter bar chips, audit log table, timeline and dashboard widgets. Build each from its design system page in the step that first needs it.

**Decided for later** (don't build yet): the ⌘K palette and shortcut registry as a cross-cutting requirement; the final keyboard map (e.g. Space to peek, M to move a card); the status stepper in the issue view; Blocked and At risk as flags on cards; triage inbox, project health, sprint capacity (step 10 or later); sample data for new workspaces.

---

## 6. Build order

| Step | Contents | Sections |
|---|---|---|
| **U** | UI foundation: design system tokens in Tailwind (light and dark), Urbanist, Lucide, Motion with the reduced-motion switch, app shell with the dark sidebar, toast with Undo, peek drawer and full issue page, dialog and warning-confirm patterns, empty states; soft delete for issues; restyle the existing screens (board, backlog, list, issue, dashboard, projects, auth, profile, tokens) | 5.1 |
| **0** | Workspaces and accounts: workspaces, global accounts and switcher, workspace roles and settings, entitlements, audit log, 2FA, Google/GitHub sign-in | 7.0 |
| **A** | Agents, part 1: agent identity and tokens, one action path for web/REST/MCP, approval requests, MCP and REST hardening, project agent instructions, AI provider settings | 8 (A1–A5) |
| **1** | Workspace status library and status categories, resolution, edit conflict protection, start recording history | 7.4, 7.11 (conflicts), 7.13 (history) |
| **2** | Issue links (with `start_date`) and cloning; "Focus" grouping for My issues | 7.5, 7.9, 5.1 |
| **3** | Watchers, in-app notifications, @mentions, reactions, live updates (Reverb); internal event system, system events and internal admin API | 7.10, 7.11 |
| **B** | Agents, part 2: in-app agent runs (needs notifications and live updates), runs from list view, custom roles, usage reporting | 8 (A6–A8) |
| **4** | Search and filters: filter bar, full-text, saved filters, bulk edit, CSV export, plain-English search | 7.12 |
| **5** | Admin extras: user groups (including @mention and assign by group), project archiving/templates/categories, CSV import, full project export, offboarding | 7.15 |
| **6** | Attachments and images | 7.8 |
| **7** | Webhooks and GitHub integration | 7.14 |
| **8** | Versions and releases | 7.7 |
| **9** | Issue hierarchy and configurable issue types | 7.1 |
| **10** | Planning and reporting: estimation unit, Scrum/Kanban, board improvements, reports, timeline, dashboards | 7.13 |
| **11** | Custom fields and the predefined catalog | 7.6 |
| **12** | Workflows | 7.3 |
| **13** | Test cases | 7.2 |

Why this order: the UI foundation first, so step 0's switcher and settings pages, and everything after, are built once in the new design; workspaces next because every later table needs `workspace_id`; agents part 1 next because the action path, permission checks and AI provider are used by almost everything after; step 1 before workflows because rules target statuses and their categories, and history recorded from step 1 must point at statuses that workflows will reuse; notifications before in-app runs, approvals and webhooks because they share the event system; plain-English search (step 4) needs the AI provider from A5.

**Step U acceptance:** every existing screen uses the design system tokens and components in light and dark; no Jira tokens remain; the existing test suite passes; Undo works for a board move, a status change, a field edit and a delete, and stops with a message when the issue changed meanwhile; deleted issues disappear from every screen, API and MCP response and can be restored; the peek drawer and full issue page share one URL scheme; with reduced motion on, nothing animates; the accessibility page's checks pass on the board, issue page and drawer; `npm run build`, Pint and the security review are clean.

---

## 7. Feature requirements

Section numbers below match the build order table. Each section lists decisions first, then behaviour, agents and data. Screens in every section follow 5.1.

### 7.0 Step 0: workspaces and accounts

**Out of scope for step 0:** Stripe and Platform management; internal admin API and system events (step 3); agent identity and `whoami` (step A); groups, archiving, templates, categories, import, export, offboarding (step 5); Microsoft sign-in, SSO/SCIM, restricted issues, Jira import (later).

**New dependencies approved for step 0** (stop and ask if either is incompatible with Laravel 13; don't hand-roll TOTP or OAuth):
- `laravel/fortify` for 2FA only (TOTP, recovery codes, challenge). Keep Breeze's auth controllers and routes.
- `laravel/socialite` for Google and GitHub.

**Decisions:**
1. **Web URLs get a workspace prefix:** `/{workspace-slug}/…` (e.g. `/acme/issues/WEB-12`). Old unprefixed URLs redirect to the user's current workspace.
2. **API and MCP get the workspace from the token.** Each token belongs to one workspace; `/api/v1` paths don't change.
3. **`workspace_id` on top-level tables only:** `projects`, `personal_access_tokens`, `workspace_invitations`, `audit_logs`, and future top-level tables. Scoping `accessibleProjects()` by workspace protects every child lookup.
4. `PM_EDITION=self_hosted` (default) allows one workspace; `cloud` allows many.
5. Pending invitations count toward limits.

#### 7.0a Workspaces and memberships

**New tables:**

| Table | Columns |
|---|---|
| `workspaces` | id, name, slug (unique, lowercase, 3–40, letters/digits/hyphens), logo_path, settings JSON (`members_can_create_projects` default true, `require_2fa` default false, `sign_in_methods` default all), suspended_at, archived_at, timestamps |
| `workspace_user` | workspace_id, user_id, role (`owner`/`admin`/`member`), timestamps; unique (workspace_id, user_id) |
| `workspace_invitations` | id, workspace_id, email, role (`admin`/`member`), project_id, project_role, invited_by, token (hashed), expires_at, timestamps; unique (workspace_id, email) |
| `workspace_slug_redirects` | old_slug (unique), workspace_id, created_at |

**Existing tables:** `projects` gets `workspace_id` and the `key` unique index becomes (`workspace_id`, `key`); `personal_access_tokens` gets `workspace_id`; `project_invitations` folds into `workspace_invitations` (rows migrated, table dropped); `users` gets `current_workspace_id`. New backed enum `WorkspaceRole`.

**Migration of existing installs** (one transaction): create one workspace named from `APP_NAME` (fallback "Workspace"); add all users (lowest id → Owner, anyone who is Admin in any project → Admin, others → Member); set `workspace_id` on projects and tokens and `current_workspace_id` on users; move pending invitations. Test against the demo seed: before and after, every user sees the same projects, issues and tokens.

**Current workspace:**
- Web: `ResolveWorkspace` middleware reads the slug, checks membership (404 with the same message as an unknown slug), binds the workspace, updates `current_workspace_id`. Old slugs in `workspace_slug_redirects` redirect.
- API/MCP: the token's workspace. If the user has left it, the token returns 401 and is deleted.
- `User::accessibleProjects()` adds `projects.workspace_id = current`. `TokenService` only accepts projects from the token's workspace.
- **Required:** `tests/Feature/Security/WorkspaceIsolationTest.php`. With a user in workspaces A and B and a user only in B, every web route, API endpoint and MCP tool that takes a project key, issue key, comment id, label, column, sprint or token id never shows or changes B's data through A, and errors don't reveal that it exists.

**Sign-up and joining:**
- Cloud: register adds "Workspace name" (slug suggested, editable); the user becomes Owner. "Create workspace" in the switcher.
- Self-hosted: the first person to register creates the workspace. After that, `/register` works only with a valid invitation; otherwise "Ask an admin of {workspace} to invite you."
- Owners/Admins invite with a workspace role and optionally a project and project role. Project Admins can still invite to their project (creates a Member invitation for that project). Invitations expire after 14 days; keep the existing invite rate limit. Accepting while signed in joins and switches.

**Switcher** at the top of the sidebar (design system SidebarNav): workspace name/logo, list of the user's workspaces, "Create workspace" (Cloud), "Workspace settings" (Owners/Admins). Inertia shared props: `workspace` and `workspaces`.

#### 7.0b Roles and settings

- `WorkspacePolicy` implements the abilities table in section 4 that step 0 covers (billing link, delete, Owners, rename/slug/logo, members, security, audit log, all projects, project-creation setting, create projects, usage page). Later abilities are added in their steps.
- Removing a user removes them from all projects in the workspace and deletes their tokens there; if they're a project's only Admin, the remover picks a new one first (reuse `MembershipService::soleAdminProjectKeys`).
- Owners/Admins see every project; ones they're not in show "Join as Admin" (logged). Until they join they can view only.
- `ProjectPolicy::create` respects `members_can_create_projects`.
- Deleting a workspace: the Owner types the slug, is reminded to export, and everything in it is deleted. **(leaning)** a 7-day "scheduled for deletion" period with undo instead of immediate deletion.
- Renaming the slug keeps the old one as a permanent redirect unless another workspace claims it.
- **Settings pages** under `/{workspace}/settings/`: General (name, slug, logo, who can create projects, delete), Members (role, billed/Viewer, projects, 2FA, last active; invite, change role, remove; pending invitations: resend, revoke), Security (require 2FA, allowed sign-in methods: password, Google, GitHub; at least one stays on), Audit log, Usage and limits.

#### 7.0c Entitlements

- Table `workspace_entitlements`: workspace_id, key, value (string, cast by the service), source (`plan`, `override`, `default`), timestamps; unique (workspace_id, key).
- `Entitlement` enum holds every key from section 4 with its type and description, enforced or not, so Platform management can set them from day one.
- `WorkspaceUsage` service counts billed users, Viewers and projects (definitions in section 4), and exposes `overLimit()` for later. Unit-test every role combination.
- `Entitlements::ensure()` at: inviting and accepting, Viewer → Member/Admin project role changes, Member → Admin workspace role changes, creating a project (web, API, MCP).
- `PlanLimitReached`:
  - Web: blocked action plus the plan-limit dialog "Your plan allows 2 billed users. Upgrade to add more." The button links to `PM_BILLING_URL` for Owners, "Ask an Owner to upgrade" for others, no button on self-hosted.
  - API/MCP: 403 `{"error":"plan_limit","limit":"billed_users","allowed":2,"current":2,"message":"…","upgrade_url":"…"}`; MCP returns the same message as a tool error.
- Rate limits: extend the `api` limiter to per minute and per day from `api_per_minute` / `api_per_day`, falling back to `PM_API_RATE_LIMIT` per minute and no daily limit. Keep the 429 message and `Retry-After`.
- `php artisan pm:entitlements {workspace} --set=key=value --unset=key --list`.
- Usage page: each enforced key with usage and limit, or "Unlimited".

#### 7.0d Audit log

- Table `audit_logs`: id, workspace_id (nullable for pre-workspace events), actor_user_id, actor_type (`user`/`system`/`staff`), via, action, target_type, target_id, target_label (kept if the target is deleted), metadata JSON, ip, user_agent, created_at. Indexes on (workspace_id, created_at), (workspace_id, action), (workspace_id, actor_user_id). Append-only; no expiry for now.
- Step 0 events: `auth.login`, `auth.login_failed`, `auth.logout`, `auth.password_changed`, `auth.password_reset`, `auth.social_linked`, `auth.social_unlinked`; `2fa.enabled`, `2fa.disabled`, `2fa.recovery_codes_regenerated`, `2fa.recovery_code_used`; `workspace.created`, `workspace.updated`, `workspace.settings_changed` (before/after), `workspace.deleted`; `member.invited`, `member.invitation_revoked`, `member.joined`, `member.role_changed`, `member.removed`, `owner.added`, `owner.removed`; `project.created`, `project.deleted`, `project.admin_joined`; `token.created`, `token.revoked`; `entitlement.changed`, `plan_limit.reached`; `audit_log.exported`.
- Sign-in events go to every workspace the user belongs to. Test that no password, token, secret or recovery code is ever logged.
- UI at `/{workspace}/settings/audit-log` (design system audit log table): when, who, action, target, via, IP; filters by actor, action group, date; pagination; CSV export. Owners/Admins only.
- One `AuditLogger` service called from services.

#### 7.0e Two-factor authentication

- Profile → Security: QR code and secret, confirm with a code, 10 one-time recovery codes shown once; regenerate codes or turn 2FA off with the password.
- Challenge after password or social sign-in; 5 code attempts per minute per user.
- A workspace with `require_2fa` sends members without 2FA to setup before they can use it (they can still sign out or switch workspace). Turning it on shows how many members lack 2FA.
- Creating a token in such a workspace needs 2FA on. Existing tokens keep working; the Security page says so.

#### 7.0f Google and GitHub sign-in

- Buttons for each provider configured in `.env`; unconfigured ones are hidden.
- `social_accounts`: user_id, provider (plain string, so Microsoft can be added later without a migration), provider_user_id, email, timestamps; unique (provider, provider_user_id).
- Link only on a **verified** provider email that matches an existing user; refuse unverified emails with an explanation. GitHub: primary verified email.
- New users follow 0a rules (Cloud creates a workspace; self-hosted needs an invitation unless first).
- Workspace sign-in methods apply before entering that workspace. Users can link/unlink but never remove their last sign-in method. 2FA still applies.

#### Step 0 config and docs

| Variable | Default | Purpose |
|---|---|---|
| `PM_EDITION` | `self_hosted` | One workspace or many |
| `PM_BILLING_URL` | empty | Platform management billing page; empty hides billing links |
| `GOOGLE_CLIENT_ID` / `_SECRET` | empty | Google sign-in |
| `GITHUB_CLIENT_ID` / `_SECRET` | empty | GitHub sign-in |
| `PM_API_RATE_LIMIT` | 120 | Fallback per-minute limit (existing) |

Update `README.md` (editions, first run, sign-in setup, `pm:entitlements`), `AGENTS.md` (tokens belong to one workspace; `plan_limit` errors), `CLAUDE.md` (section 5 conventions; web routes under `/{workspace}`), and the seeder (workspace "Demo" / `demo`; demo@example.com Owner, alex Admin, sam Member and Viewer in WEB; print the demo token).

**Sub-steps, one commit each:** (1) data, migration, `CurrentWorkspace`, scoped access, token binding, isolation tests; (2) URL prefix, redirects, switcher, sign-up, invitations, self-hosted mode; (3) roles, policies, General and Members pages; (4) audit log; (5) entitlements, enforcement, rate limits, Usage page, Artisan command; (6) 2FA; (7) Google/GitHub sign-in; (8) docs, seeder, final security review.

**Acceptance:** existing installs migrate with no change beyond the URL prefix and switcher, and the existing suite passes; the isolation test covers every route, endpoint and tool that takes an id or key; self-hosted allows one workspace; Cloud users can create, join and switch workspaces without data crossing; role rules including last-Owner hold; with `billed_users=2`, `viewers=3`, `projects=1` the limits are enforced on web, API and MCP including pending invitations; per-token minute and day limits work; every audit event is recorded once with no secrets; 2FA and enforcement work for password and social sign-in; Google and GitHub sign-in link only on verified emails; new screens follow 5.1; `npm run build`, Pint and the security review are clean.

---

### 7.1 Issue hierarchy and configurable issue types (step 9)

- **One `parent_id` for every level.** Migrate `epic_id` into `parent_id`. A parent is exactly one level above its child.
- **Levels:** Initiative (2) → Epic (1) → Story/Task/Bug (0) → Sub-task (−1). An optional Goal (3) can be added later.
- **Issue types move to an `issue_types` table** (workspace, name, icon, colour, level, workflow, fields, description template; see 7.6). Admins rename and add types at any level, and pick each type's icon and colour.
- **Sub-task becomes its own type** (default "Sub-task" at −1). Existing Stories/Tasks/Bugs with a parent migrate to it, so levels stay strict.
- Parents in another project of the same workspace only at level 2 and above.
- **Rollups on parents:** % of children done, estimate done / total (project unit, 7.13), earliest and latest due dates.
- UI: breadcrumb (Initiative › Epic › Story), a "Child issues" panel at every level, a parent filter in list view and search. Boards, backlog and sprints stay at level 0.
- Agents: one `parent` field on create/update; `get_issue_tree` returns a whole branch.
- Until this step, the enum's types get a fixed icon and colour from the design system.

### 7.2 Test cases (step 13)

**Decisions:** test cases are a **reusable library**, not sub-tasks. Moving a story to Done with linked tests that are Failed or Not run **warns, doesn't block**. **Run history is kept.**

- **Library (per project):** title, preconditions, ordered steps (action + expected result), labels. Never on the board.
- **Coverage links:** many stories ↔ many test cases. On a story, "Add test case" creates or links one. A test case lists the stories it covers.
- **Test runs:** one record per run: test case, context (story, suite run or sprint), runner, `via`, per-step results, overall result (Passed / Failed / Blocked), notes, optional bug link, **snapshot of the steps** at run time. The current result is the latest run.
- **Regression suites:** named sets built by hand or from a saved filter (e.g. a label). "Start suite run" creates a run per test and shows progress.
- **Moving to Done:** web shows a warning confirm listing problem tests; API/MCP complete the move with `warnings`; the activity log records the override.
- **Failed step → "Create bug"** pre-filled with the step and expected result. Offer only, never automatic. **(leaning)**
- One project per test case for now. **(leaning)**
- MCP: `create_test_case`, `link_test_case`, `start_suite_run`, `record_test_run`, `get_test_history`, `list_suites`.
- Tables: `test_cases`, `test_steps`, `issue_test_case`, `test_suites`, `suite_test_case`, `test_runs` (with `steps_snapshot` JSON), `test_run_steps`.

### 7.3 Workflows (step 12)

**Decisions:**
- Only **project Admins and workspace Owners/Admins** create or edit workflows. Others see the diagram and rules read-only. Web-only.
- **Agents are limited by default.** Every workflow ships with this rule, which Admins can change: *agents can move issues to any status except Done-category statuses; moving to Done needs a person.* An agent's attempt creates an **approval request** (A2: inbox, 7.10, and dashboard; approve or reject in one click; the agent's owner or a project Admin decides) and returns `pending_approval`. Until this step, the same rule is hard-coded in `move_issue` (step A).
- **Shared library:** workflows live at workspace level; each project picks one per issue type. No partial overrides; Admins can "Make a copy for this project". **Editing a shared workflow needs project Admin in every project that uses it, or workspace Owner/Admin.** Anyone else who tries to edit is offered "Make a copy for this project". Before saving, preview the impact on every project (e.g. issues in a removed status and where to move them).

**Principles:** open by default (every move allowed until a rule is added); each rule **warns or blocks**; blocks give the reason and Admins can override (logged); rules are shown as plain sentences in one "Workflow" tab (typing a rule in English is later); rules can target who acts (person, any agent, a specific agent) and custom fields (7.6, e.g. "require Severity before In Progress"); templates: Simple, Software (with Review), Bug (Triage → Fix → Verify).

- **Statuses come from the workspace status library** built in step 1 (7.4). A workflow uses a set of library statuses plus transitions between them. Adding a status to a workflow gives it a board column automatically in projects that use it; Admins can map several statuses to one column.
- **Versioned:** every save creates a new version; edits are audited; any earlier version can be restored (restoring is a new version, with the same impact preview).
- MCP `get_transitions`: for one issue, every status it can move to, with warnings, blocks, approval needs and required fields.
- Status changes emit webhook events (7.14). Automation rules are future.
- **Data (leaning):** `workflows` (workspace_id, name, description, current_version_id, created_by, timestamps); `workflow_versions` (workflow_id, number, definition JSON with statuses, transitions and rules, created_by, created_at; append-only); `project_workflows` (project_id, issue_type_id, workflow_id; unique project + issue type); `workflow_rule_overrides` (issue_id, workflow_version_id, rule id, from/to status, user_id, reason, created_at). Rules live inside the version's JSON so a rollback restores statuses, transitions and rules together.
- **Audit events:** `workflow.created`, `workflow.updated` (version number, summary of changes), `workflow.restored`, `workflow.copied`, `workflow.deleted`, `workflow.assigned` (project, issue type), `workflow.rule_overridden`.

### 7.4 Status category and resolution (step 1)

- **Workspace status library (new in step 1):** statuses are workspace-level records (name, category, optional colour), shared by every project, like Jira. Projects don't own statuses; their board columns map to them. Workflows (step 12) reuse the same statuses, so nothing recorded from step 1 has to be migrated again.
- **Status category:** To Do, In Progress, Done (fixed). Every status has exactly one. Columns have no category of their own; a column shows the issues whose status maps to it.
- **Status colour:** taken from the category by default (design system category colours); workspace Owners/Admins can set a custom colour per status from the design system palette. Colour always sits beside the status name (5.1).
- **Columns ↔ statuses:** each column maps to one or more statuses (one by default). Each issue has a `status_id`; its column is the one its status maps to. Dropping a card on a column sets the column's first status. A status appears in at most one column per project.
- **Managing statuses (leaning):** project Admins pick a library status or create a new one when editing columns (new statuses join the library). Workspace Owners/Admins can rename statuses, set colours and merge duplicates in Settings → Statuses, which shows where each is used ("Used by 3 projects, 412 issues"). A status can be deleted only when no issue or column uses it. Names are unique per workspace.
- **Migration:** for each existing column, reuse a library status with the same name and category, or create one. Category: first column → to_do, last → done, others → in_progress (same behaviour as today). If the name already exists with a different category, create "<name> (<project key>)" and note it in `PROGRESS.md`. Set `status_id` on every issue from its current column.
- **Resolution:** Done, Won't do, Duplicate, Cannot reproduce. Fixed list for now.
- **Moving an issue into a Done-category status (e.g. dragging to its column) resolves it as Done immediately, no popup.** A "Resolved: Done ▾" chip shows on the card for a few seconds to change it; the move's Undo toast reverses both the move and the resolution.
- Entering Done always sets a resolution; leaving Done clears it and logs a reopen. No "Done but unresolved" issues. `resolved_at` is recorded.
- **Won't do / Duplicate / Cannot reproduce stay on the board dimmed** until the sprint ends (Scrum), or until Done clearing removes them after N days (Kanban, 7.13).
- "Close as…" on the issue page; Duplicate asks for the original issue and creates a `duplicates` link (needs 7.5; until step 2, record the original's key in the activity log).
- Downstream: sprint progress and velocity count only resolution = Done; sprint completion carries over issues whose category isn't Done; dashboard, list and search gain category and resolution filters; "My issues" defaults to not done. Replace every use of the last-column rule.
- Agents: `move_issue`/`update_issue` take a status name (column names still accepted when the column has one status) and `resolution` (default Done on a move to Done); Duplicate without `duplicate_of` returns an error explaining the fix; responses include `status`, `category`, `resolution`, `resolved_at`.
- Data: `statuses` (workspace_id, name, category, color (nullable; null = category colour), description, timestamps; unique (workspace_id, name)); `column_statuses` (column_id, status_id, position; unique (column_id, status_id)); `status_id`, `resolution`, `resolved_at` on issues.
- Audit events: `status.created`, `status.renamed`, `status.recoloured`, `status.merged`, `status.deleted`.

### 7.5 Issue links (step 2)

| Link | Reverse | Meaning |
|---|---|---|
| blocks | is blocked by | Can't continue until the other is resolved |
| precedes | follows | Planned order, finish-to-start |
| relates to | relates to | Loose connection |
| duplicates | is duplicated by | Same problem twice |
| clones | is cloned by | Created by copying |

**Decisions:** blocks and precedes/follows are **separate**; **finish-to-start only** for now; custom link types later. **Date shifting:** when a predecessor's due date moves and a successor would conflict, warn with the affected successors and new dates (**Shift dates** / **Keep dates**) plus **"Remember my choice"** (always shift / never / ask every time), changeable in settings.

- **Blocks:** "Blocked" badge and "Blocked by" list; moving a blocked issue to In Progress warns; the badge clears when all blockers resolve; filters `blocked`, `blocked by KEY`.
- **Precedes/follows:** starting a successor before its predecessor is resolved warns; a successor scheduled to start before its predecessor's due date warns on both. Shifting moves each conflicting successor later by just enough, keeping its duration, down the chain; resolved issues never move; each shift is logged ("dates shifted because WEB-12 moved").
- **New `start_date` field on issues.**
- Duplicates come from "Close as Duplicate"; clones from Clone (7.9).
- Links across projects in the same workspace are allowed. No loops for blocks and precedes (the error names the loop). Deleting an issue hides its links with it; restoring brings them back. Adding/removing is logged on both issues.
- With links in place, My issues gets its "Focus" grouping (5.1).
- Agents: `link_issues` / `unlink_issues` (`{"from":"WEB-12","type":"blocks","to":"WEB-15"}`; reverse names accepted); `get_issue` returns links grouped by type with each linked issue's category; `update_issue` takes `shift_successors: true|false`, defaulting to the owner's saved choice; with "ask every time" the date change is saved, nothing shifts, and the response lists proposed dates as a warning; the agent reports back to its user and calls again with `shift_successors: true` if they agree. Users can ask an agent to "work on what's ready", meaning open issues that aren't blocked and whose predecessors are resolved (filters in 7.12).
- Data: `issue_links` (source, target, type, created_by, agent, via; stored once in forward direction); `start_date`; per-user setting `date_shift: ask|always|never` (default ask).

### 7.6 Custom fields and issue types (step 11)

**Decisions:** shared workspace library (edit rule as workflows); required on create (per issue type) and required before a status (workflow rule); **cap of 25 own fields per workspace**, catalog fields don't count; when capped, point Admins to the catalog and to archiving; Cloud Free: catalog only, up to 3 enabled (entitlements).

- **v1 types:** short text, long text (Markdown), number, date, single select, multi-select, user, checkbox, URL. Later: formula, issue picker, cascading select.
- **Predefined catalog, off until enabled per issue type:**

| Group | Field | Type |
|---|---|---|
| Bugs | Severity (S1–S4) | single select |
| Bugs | Environment (Production, Staging, Development) | single select |
| Bugs | Browser / device | multi-select |
| Bugs | Root cause | long text |
| Bugs | Customer impact (None, Some, Many, All) | single select |
| Planning | Team | single select |
| Planning | Effort (XS, S, M, L, XL) | single select |
| Planning | Business value | number |
| Planning | Risk (Low, Medium, High) | single select |
| Planning | Target launch date | date |
| Requirements | Acceptance criteria | long text |
| Requirements | Customer | short text |
| Requirements | Requested by | user |
| People | Reviewer | user |
| People | QA owner | user |
| Links | Design link | URL |
| Links | Spec / document | URL |
| Status | Approved | checkbox |
| Status | Blocked reason | short text |

  Workspaces can edit catalog select options. (No Affected version / Target release: built-in versions replace them. No Pull request: GitHub links PRs.)
- **Issue types bundle** name, icon, colour, level, workflow, fields (order, required on create, defaults) and a description template (Bug: *Steps to reproduce / Expected / Actual*). Built-in: Task, Story, Bug, Epic, Sub-task, Initiative; optional Spike, Incident; each with sensible catalog fields on (Bug: Severity, Environment, Affects version) and a default icon and colour from the design system.
- **One layer of configuration:** the issue type decides fields, order and required. No screens or schemes.
- Fields work everywhere: filter, sort and search on select, user, date, number and checkbox fields; up to 2–3 on board cards (e.g. a severity badge); list view columns; workflow rules. The issue view hides empty fields behind "Show N more fields" (5.1, calm by default).
- **Archive, don't delete:** archived fields are hidden from forms, values kept; delete only when unused; show usage ("Used by 412 issues in 3 projects"); warn on near-duplicate names.
- Changing an issue's type keeps shared field values; others are listed in a confirm dialog and kept in the activity log.
- Agents: readable keys; help text doubles as agent guidance; `describe_issue_type` returns the fields as JSON schema; invalid values list valid options.
- Data: `custom_fields` (workspace, key, name, type, options, help, catalog/custom, archived), `issue_type_fields` (type, field, order, required, default), `issue_field_values` with a separate indexed column per value kind (text, number, date, user), so filtering and sorting stay fast.
- Audit events: `field.created`, `field.updated` (including option changes), `field.archived`, `field.restored`, `field.deleted`; `issue_type.created`, `issue_type.updated` (including icon and colour), `issue_type.deleted`.

### 7.7 Versions and releases (step 8)

- Versions belong to one project: name, description, start date, release date, status Unreleased → Released → Archived.
- Issues have **Fix versions** (where work ships) and **Affects versions** (where a bug was found); both multi, built in.
- **Release page:** progress by category, open vs resolved, warnings (blocked issues, failing tests, open PRs), burnup (7.13). **Release** warns about open issues, offers to move them to the next unreleased version, then sets Released and the date. Archived versions leave pickers.
- **AI release notes:** drafted from resolved issues (features, fixes, improvements) using the workspace's AI provider, only when a person asks; the user edits and publishes.
- Filters for both version fields in list view, search and the board; release markers on the timeline.
- Agents: `list_versions`, `create_version`, `update_version`, `release_version` (`confirm: true`), `draft_release_notes`; issue create/update accept `fix_versions` and `affects_versions` by name.
- Data: `versions` (project_id, name, description, start_date, release_date, status, released_at), `issue_fix_versions`, `issue_affects_versions`. Components are future.

### 7.8 Attachments and images (step 6)

- Limits from entitlements (`max_file_mb`, `storage_mb`). Videos allowed within the size limit.
- Paste or drop images into descriptions and comments (inline Markdown). Attachments panel for any file, with thumbnails and viewers for images, PDFs and video.
- Uploader and Admins can delete (with Undo); uploads and deletes go in the activity log. Owners/Admins are warned near the storage limit; at the limit uploads are refused with an upgrade/add-on prompt on Cloud.
- Agents: REST upload and MCP base64 (same limits); `get_issue` lists attachments; agents can download images; read-only agents can't upload.
- **Security from day one:** Laravel filesystem (local by default, S3-compatible in production); never a public URL, downloads check access and use short-lived signed links; SVG/HTML and other active content always served as downloads; strip EXIF.
- Data: `attachments` (issue, comment, uploader, agent, via, name, MIME, size, path, deleted_at); storage used tracked per workspace (soft-deleted files count until purged).

### 7.9 Cloning (step 2)

- Copies title, description, type, labels, versions, estimate, custom field values and sub-tasks into a new issue **in the same project**, linked with `clones`. Options: include sub-tasks, attachments, links. Comments and history are never copied.
- Agents: `clone_issue`. Moving issues and cloning across projects are future.

### 7.10 Watchers and in-app notifications (step 3)

**Decisions:** in-app notifications now; **email notifications out of scope** (account, invitation, billing and security emails stay); voting deferred; **your own agent's changes go into a daily summary**.

- **Watch** button with count; clicking the count lists watchers. Auto-watch: reporter, assignee, commenters, @mentioned people (anyone can stop watching; each user can turn auto-watch off). Members can add watchers; Viewers only themselves. "Watching" filter and dashboard section.
- **Inbox** (design system notification inbox): bell with unread count; each item names the issue, the change and who made it, including agents ("Alex's agent moved WEB-12 to In Review").
- **Triggers:** comments, status, assignee, @mentions, attachments, resolve/reopen, approval requests, finished or failed agent runs. Label-type edits don't notify.
- **Batching:** several changes to one issue within a few minutes become one notification; @mentions and approvals are never batched. Never notify people about their own changes.
- **Daily summary** of your own agent's changes ("Your agent updated 14 issues yesterday", with the list); approval requests from your agent notify you immediately. Project Admins aren't notified of every approval request; they see them in their pending approvals list (A2).
- Mark read / all read, and a link straight to the issue; new items arrive live (7.11).
- Agents: an agent watching counts as its owner watching; `watch_issue` / `unwatch_issue`; `get_updates` returns changes on watched issues plus the user's mentions since a given time.
- Data: `issue_watchers` (issue, user, source: auto/manual), `notifications` (user, issue, event, actor, agent, via, batched changes, read_at), per-user auto-watch setting.
- **Also in step 3 (infrastructure):** an internal event bus that notifications, webhooks (7.14) and **system events** for Platform management all use; plus the **internal admin API**, system events, the **billing link** and the **billing banners** (from `plan`, `trial_ends_at`, `billing_notice`), all exactly as in section 9. All enabled only when configured.

### 7.11 Collaboration (step 1 for conflicts, step 3 for the rest)

**Decisions:** people can't @mention someone else's agent; agents can mention people; real-time = **edit conflict protection + live board and issue page** (no presence or co-editing); reactions in v1; threaded replies deferred.

- **@mentions:** `@` lists project members (user groups join the list in step 5, 7.15); mentions show as highlighted names; immediate notification (never batched) and auto-watch; mentioning a non-member offers Admins "Invite Sam to this project?" and tells Members Sam can't see it. Agent mentions show as "Alex's agent mentioned you" and are rate-limited per agent. Agents can't be mentioned.
- **Issue keys auto-link** in any text with a hover preview (a reference, not a formal link).
- **Edit conflict protection (step 1):** issues and comments have a `version`; web and API send the version they read; a mismatch stops the save. Web: the edit conflict dialog, "Changed by Alex's agent 30 seconds ago. Review changes?", with both versions. API/MCP: conflict error with the current version and what changed. Board moves just apply. Undo uses the same check (5.1).
- **Live updates (step 3):** cards, new issues, comments, reactions, fields and the bell update without refresh, using **Laravel Reverb** (approved; needs its own process in production, document it in README), falling back to polling every 30 seconds. Updates only reach users who can see the project in that workspace. They animate as 5.1 says and never take focus.
- **Reactions:** fixed set (👍 ✅ 👀 🎉 ❤️ 😄); hover shows who; no notifications; doesn't change last-updated. Agents can react (e.g. 👀 as "seen, working on it"), shown as "Alex's agent"; `add_reaction` / `remove_reaction`.
- Data: `version` on issues and comments; `comment_reactions` (comment, user, agent, via, emoji); `mentions` (target, mentioned user or group, by, agent, via).

### 7.12 Search and filters (step 4)

**Decisions:** filter bar in v1, query language later; **plain-English search v1**; subscriptions later; saved filters private or shared with a project (no workspace-wide sharing yet); CSV export v1; Cloud Free 2 saved filters per user.

- **One filter model** for the filter bar, plain-English search, API/MCP and the later query language.
- **Filter bar** (design system filter chips): chips with multiple values each; current filter kept in the URL.
- **Plain-English search:** text → filter chips shown for checking before relying on the results; uses the workspace's AI provider (8, A5); hidden without one; sends only the text plus field names and options, never issue content. **Web UI only:** agents already understand plain English and build the JSON filter for `search_issues` themselves, so there's no MCP tool for it.
- **Filterable fields:** project, type, status, category, resolution, priority, labels; assignee, reporter, watcher, mentioned, `me`, unassigned, group; created, updated, resolved, due, start (exact, ranges, relative, overdue); sprint (current, open, by name), parent/epic, fix/affects version, estimate; blocked, blocked by, predecessors not done, linked to; has open PR, PR merged, has branch; text in title, description and comments; has attachments; custom fields; changed by an agent, via, by agent. Fields from later steps join as they're built.
- **Full-text:** MySQL full-text index on title, description, comments; relevance ranking. Scout + Meilisearch later if needed.
- **Saved filters:** private by default or shared with a project (anyone can save private filters; Members and Admins can share; the owner and project Admins edit or delete shared ones); star to sidebar; reused as board quick filters, regression suites, dashboard widgets, webhook filters.
- **Bulk edit (web):** checkboxes and "select all N matching"; assignee, status, priority, labels, sprint, versions, estimate, custom fields, delete; preview ("Change priority on 34 issues"), then one Undo for the batch (5.1); workflow rules and conflicts apply per issue and skipped issues are listed with reasons; one batched notification per issue.
- **CSV export:** current results and visible columns, permission-checked, capped at 10,000 rows, every plan.
- Agents: `search_issues` takes a schema-checked JSON filter and returns the filter used; `list_saved_filters` / `run_saved_filter`.
- Data: `saved_filters` (workspace, owner, name, filter JSON, visibility, project), `saved_filter_stars`; full-text indexes.

### 7.13 Planning and reporting (history in step 1, the rest in step 10)

**Decisions:** timeline v1; all six reports v1; AI sprint summary and people-vs-agents split later; swimlanes, WIP limits and Done clearing v1; Scrum/Kanban setting v1; configurable dashboards v1 (personal and project-shared); **hour estimates v1 without time logging**.

- **History (start in step 1):** `issue_status_history` (issue, from_status_id, to_status_id, to_category, at, who, agent, via), `sprint_scope_events` (issue joined/left a sprint with its estimate at that moment), `estimate_history` (so burndown shows re-estimates correctly). Backfill from the activity log, mapping old column names to the statuses created by the step 1 migration.
- **Estimation:** project setting story points or hours (halves allowed); one unit per project for rollups, capacity, charts and board column totals (5.1); switching keeps both values (issues without a value in the new unit show unestimated, with a confirm dialog). Agents: `estimate` on create/update; `get_project` returns the unit. Time logging is out of scope.
- **Timeline** (design system timeline): per project, epics and initiatives as bars from start/due dates, expandable; dependency arrows from precedes/follows; release markers and a today line; drag to change dates (date-shift warning from 7.5); zoom weeks/months/quarters; progress per bar; undated issues in a side panel. Agents: `get_timeline`.
- **Reports** (only resolution = Done counts as completed):

| Report | Shows | Available in |
|---|---|---|
| Burndown | Work remaining in the sprint, day by day, against an ideal line | Scrum |
| Sprint report | Completed, not completed, added mid-sprint, removed | Scrum |
| Velocity | Committed vs completed, last 6–8 sprints | Scrum |
| Burnup | Completed vs total scope over time, with scope changes visible | Versions and epics (both modes) |
| Cumulative flow | Issues per status over time | Both (mainly Kanban) |
| Cycle time | Time from first In Progress to Done, with averages and outliers | Both (mainly Kanban) |

  Each report has a date range and filters (e.g. assignee, type); export the chart as an image and the data as CSV. Agents: `get_report`.
- **Board:** swimlanes (none / assignee / epic / priority, remembered per user per board). Optional WIP limit per column, set by project Admins: going over turns the column header to the warning colour and moving a card in asks for confirmation (warn, don't block); agents get a warning. **Done clearing:** resolved issues leave the board at sprint end (Scrum) or after N days in a Done-category status (Kanban; default 14, set per project). Cleared issues **stay in search, list view and reports**. Density and "Show N more" as in 5.1.
- **Scrum vs Kanban:** project setting "Sprints on/off" (existing projects on). On: backlog, sprints, burndown, sprint report, velocity. Off: a continuous board, Done clearing after N days, cumulative flow and cycle time front and centre. Turning sprints off with an active sprint asks to complete it first; turning them on starts with an empty backlog plan. Multiple boards per project later.
- **Dashboards** (design system dashboard widgets and KPI tiles): several personal dashboards (1 on Free); Members and Admins can share a dashboard with a project (same rules as saved filters). The current fixed dashboard becomes the default personal one. A grid where widgets are added, removed, resized and rearranged by dragging. Widgets: saved filter (list or count); assigned to me, watching, my mentions; sprint progress and burndown; velocity; cumulative flow and cycle time; breakdown chart (pie or bar) by status, priority, assignee, type or a custom select; created vs resolved over time; version progress; pending approvals (A2); recent activity in a project. Widgets show only what each viewer may see (on a shared dashboard each person sees their own permitted issues) and refresh live. Agents: `get_dashboard`.
- Free shows 90 days of report and activity history (`history_days`).
- Data: the three history tables; project settings (estimation unit, sprints on/off, Done-clearing days); `column_wip_limits` (column, limit); `dashboards` (workspace, owner, name, visibility: private/project, project); `dashboard_widgets` (dashboard, type, settings JSON, position, size); per-user board preferences (swimlanes, density).

### 7.14 Webhooks and GitHub (step 7)

**Decisions:** webhooks v1; GitHub v1 **linking only** (no automatic status moves); not on Cloud Free (entitlements); automation rules, Slack, issues from email and plugins deferred.

- **Webhooks** (project Admins, web-only): URL, events, and an optional saved filter or simple condition (e.g. only bugs). Events: issue created/updated/moved/resolved/reopened/deleted/restored, comment added/edited/deleted, link added/removed, attachment added, sprint started/completed, version released. Payload: event, timestamp, the issue's current data, changes (old → new), actor with `via`, agent flag and agent name. Security: per-webhook secret, HMAC-SHA256 signature header, HTTPS only (HTTP only for local dev), private/internal addresses blocked, secrets shown once and stored encrypted. Reliability: sent from a queue; retries with backoff for up to 24 hours; a 30-day delivery log per webhook (status, response code, timing, payload) with Resend; auto-disable after repeated failures with an inbox notice to Admins; "Send test event".
- **GitHub:** connect one or more repos per project (GitHub webhook URL and secret to paste, plus a read-only token). Keys in branch names, commit messages and PR titles/descriptions link automatically (several keys, several issues). Development panel on the issue page: branches, commits (latest few, with "show all"), PRs with state (open, draft, merged, closed) and check status (passing, failing, pending); each item links to GitHub. PR badge on cards and lists. Activity entries for PR opened/merged/closed (shown as "GitHub") without moving status. Filters (7.12) and release warnings (7.7). `get_issue` includes dev links. `AGENTS.md` tells coding agents to put the issue key in branch names. Webhook verified with its secret; token encrypted.
- Data: `webhooks` (project, URL, events, filter, encrypted secret, active, failure count), `webhook_deliveries` (webhook, event, payload, status, response code, attempts, timestamps), `github_connections` (project, repo, encrypted token, webhook secret), `dev_links` (issue, type: branch/commit/PR, external id, title, state, check status, URL, updated_at). Full GitHub App, GitLab and Bitbucket later.
- Audit events: `webhook.created`, `webhook.updated`, `webhook.deleted`, `webhook.disabled` (automatic), `webhook.secret_rotated`; `github.connected`, `github.disconnected`, `github.token_updated`.

### 7.15 Admin extras (step 5)

- **User groups:** e.g. "Design", "QA". Add a group to a project with one role; @mention and assign by group (groups join the `@` list from 7.11 in this step, and `mentions` can then target a group). Owners/Admins manage them.
- **Project archiving:** read-only, hidden from lists and pickers, restorable; still in history and export.
- **Project templates:** built-in (Scrum software, Kanban, Bug tracking) or from an existing project's setup (workflows, fields, columns, labels, agent instructions), without issues.
- **Project categories** to group projects in the sidebar.
- **CSV import:** upload, map columns to fields, preview, import (reuses bulk create).
- **Full project export:** everything (issues, comments, attachments, links, sprints, versions, history) as JSON plus files; every plan, every state. Soft-deleted issues are left out.
- **Offboarding:** deactivating a user signs them out, revokes their agents and tokens, keeps their history, and offers to reassign their open issues.
- New audit events for all of the above.

---

## 8. Agents (steps A and B)

### Modes

- **Mode A, external agents:** the user's own tool (Claude Code, Codex, Cursor, a script) calls PmOps through MCP or REST with an agent token. PmOps just answers.
- **Mode C, in-app agent runs:** the user asks an agent to do PM work on an issue inside PmOps; a background job runs it with the workspace's AI provider.

Both go through the same services (permissions, workflow rules, conflict checks, activity log).

### Agent identity (A1)

- An agent is a named identity **owned by one user**, shown as "Talha's agent" or by its name ("Talha's QA agent"), with the design system's agent avatar. Not a workspace member, and **never an assignee**: assignee pickers and `assignee` inputs on REST/MCP don't accept agents.
- Created in Settings → Agents: name, description, permission level, projects. A user can have several (limit `agents_per_user`).
- **One token per agent**, separate from the user's login; shown once, stored hashed. Pausing or revoking an agent never affects its owner. OAuth for MCP clients, plain token for scripts.
- **Agent tokens work only on API and MCP routes**; they can't sign in to the web UI.
- An agent sees at most what its owner sees; can't be mentioned, assigned or directed by others; is revoked automatically when its owner leaves the workspace (history kept).
- **Permission levels:** Read only; Read and write (default: create/edit, comment, move status except Done, attach); Read, write and delete (off unless the owner turns it on). Never above the owner's project role (a Viewer's agent is read only).
- **Migration:** each existing Sanctum token becomes an agent named after the token, with the same projects and read/write level, owned by the token's user, in the token's workspace. Existing token strings keep working.
- **Audit events:** `agent.created`, `agent.updated` (level, projects), `agent.paused`, `agent.resumed`, `agent.revoked` (by owner, Admin or automatically on leaving), `agent.token_regenerated`.

### One action path and guardrails (A2)

- Web, REST and MCP call the same services. Permission check order: owner's project role → agent level → agent's project list; strictest wins.
- **Moving to Done needs a person** (hard-coded in `move_issue` until workflows, 7.3): an agent's attempt creates an approval request and returns `pending_approval` with the request id. The issue doesn't move until a person approves.
- **Approval requests:**
  - **Who decides:** the agent's owner, or any Admin of the issue's project. The decider must be allowed to make the move themselves. One click to approve or reject, with an optional note for reject.
  - **Where they show:** the owner gets an immediate inbox notification (step 3 onwards). From step A, the current dashboard gets a fixed **"Pending approvals"** section listing requests the viewer can decide (their own agents' requests, plus requests in projects they administer); it becomes the pending approvals widget in step 10. The issue page and peek drawer show the request in the AI box (5.1). Each request uses the design system's approval request card.
  - **Approving re-checks everything:** permissions, workflow rules and the issue's version at request time. If the issue changed since, the decider sees what changed and confirms again; if the move is no longer possible, the request is marked `stale`.
  - **Expiry:** pending requests expire after **7 days** (status `expired`); the agent can ask again. Only one pending request per issue and target status; a repeat attempt returns the existing one.
  - The applied move is recorded as made by the decider, with the agent and request linked in the activity log.
  - Agents can check a request with `get_issue` (pending approvals listed) and get its outcome through `get_updates`.
  - Data: `approval_requests` (workspace_id, issue_id, agent_id, owner_user_id, action JSON (target status, resolution, `duplicate_of`), issue_version, status: pending/approved/rejected/expired/stale, decided_by, decided_at, note, expires_at, timestamps).
  - **Before step 1:** statuses, resolutions and issue versions don't exist yet, so "Done" means the project's last column, the action stores the target column, and approval re-checks only permissions and whether the issue moved meanwhile. Step 1's migration converts pending requests to the new statuses.
  - Audit: approvals and rejections go in the issue's activity log, not the workspace audit log.
- `confirm: true` for big actions (section 5).
- **Never available to agents:** workspace and project settings, members and roles, workflows, field definitions, webhooks, AI keys, other agents' tokens.
- Pause/revoke by the owner or a workspace Admin: paused tokens get `403 agent_paused`; running Mode C jobs stop at their next step.
- Agents don't trigger other agents, and an agent's own change never re-triggers it.
- The web UI's Undo (5.1) undoes a person's own changes. An agent's changes are reverted like any edit, by a person changing them back (or discarding a Mode C change set before it's applied).

### MCP and REST hardening (A3)

- MCP at `/mcp` (Streamable HTTP) on every install, OAuth or agent token. Keep stdio.
- **About 20 core tools** so the agent's context stays small: `get_issue`, `search_issues`, `create_issue`, `update_issue`, `move_issue`, `add_comment`, `get_transitions`, `get_issue_tree`, `get_updates`, `list_projects`, `get_project`, `describe_issue_type`, `attach_file`, `link_issues`, `list_saved_filters`, `run_saved_filter`, `get_report`, `watch_issue`, `add_reaction`, `whoami`. Tools for features not built yet are added in their steps. Feature tools are grouped and listed only when the project uses that feature. Existing tools stay available until the user agrees to retire them.
- Compact responses: `fields` parameter, pagination (default 25), ids plus keys, no HTML; user-written text in its own fields, never in tool descriptions.
- REST mirrors MCP under `/api/v1`, cursor pagination, `Retry-After` on 429.
- `whoami`: agent, owner, permission level, projects, workspace (slug, name, owner's workspace role), current rate-limit usage.
- Rate limits move from per token to **per agent**, from `api_per_minute` / `api_per_day`; only that agent is slowed.

### Project agent instructions (A4)

- A per-project "agent instructions" text (e.g. "always fill Acceptance criteria; bugs need Severity"), returned by `get_project` and used in Mode C.
- Docs: an `AGENTS.md` snippet telling coding agents to put the issue key in branch names and post a short comment when they finish.

### AI provider settings (A5)

- Providers: Anthropic, OpenAI, Google, any OpenAI-compatible endpoint (covers Ollama and company gateways).
- Self-hosted: provider, model and key in `.env`. Cloud: Settings → AI (Owners/Admins); key encrypted, never returned by any API, never logged, redacted from errors.
- One connection serves Mode C, plain-English search and AI release notes. Without it those are hidden; Mode A still works.
- Optional monthly token cap per workspace.
- Use a provider-agnostic PHP library: choose at build time between Laravel's first-party AI tooling and Prism, and **ask the user before adding it**.
- Settings → AI says plainly that Mode C sends issue content to the chosen provider; Admins can turn Mode C off per project.
- **Audit events:** `ai_provider.updated` (provider, model, endpoint; never the key), `ai_provider.key_changed`, `ai_provider.removed`, `ai_provider.cap_changed`; `agent_policy.changed` (agents or Mode C turned on/off workspace-wide); `project.agent_settings_changed` (instructions, Mode C on/off).

### Mode C: in-app runs (B: A6–A8, after step 3)

- **Built-in roles** (project-management work only; no code, no running code, no browsing):

| Role | Does | Tools |
|---|---|---|
| Triage | Checks a new bug for missing info; suggests type, priority, Severity, labels, likely duplicates | read, search, propose fields, comment |
| Writer | Drafts or tightens description and acceptance criteria | read, propose description/fields |
| Planner | Breaks an epic or story into child issues with estimates | read, propose child issues and links |
| QA | Drafts test cases from acceptance criteria | read, propose test cases and coverage |
| Summariser | Summarises a thread or catches the user up | read only |
| Release notes | Drafts notes from a version's resolved issues | read only |
| Custom (Admins, A7) | Own instructions and a chosen tool subset | any non-admin tools |

- Roles that depend on later features appear when those are built: QA with test cases (step 13), Release notes with versions (step 8).
- **Starting a run:** "Ask agent" in the AI box on every issue (pick a role or type an instruction). From list view on selected issues, one run each, at most 25 per request (A7). Always started by a user; never automatic. Limit `agent_runs_per_day`.
- **How it runs:** queued job gathers the issue, parent, children, links, last 20 comments, the type's field schema, project agent instructions and role instructions. It loops provider ↔ tools through the services, with limits of 15 steps, a token budget and a 3-minute timeout (Admin-configurable). Progress shows live in the agent run panel (Reverb).
- **Result:** a comment from the agent plus a **change set** (field changes, new issues, links, test cases) shown in the design system's change-set diff. **Apply all**, apply one by one, or discard. Default is **propose** for every role, with a per-role switch to apply directly within the agent's level. **(leaning)** Cancel any time. Applied changes go through conflict protection; anything changed meanwhile is flagged.
- **Prompt injection:** issue content is passed as clearly marked untrusted data, separate from instructions; each role gets only its tools; change sets are proposed by default; the run panel shows what was read and called; links in issues are never fetched in v1. Mode A relies on server-side limits (levels, Done approval, `confirm`, no admin tools).
- Notifications: finished or failed runs notify the starter.
- **Usage (A8):** tokens per run, per agent and per month in Settings.
- Run logs keep full detail for 30 days, then a summary (who, when, issue, tokens). **(leaning)**

### Agents data

| Table | Key columns |
|---|---|
| `agents` | workspace_id, owner_user_id, name, description, permission_level, status (active/paused/revoked), last_used_at |
| `approval_requests` | see A2 |
| `agent_projects` | agent_id, project_id (none = all of the owner's) |
| Sanctum tokens / Passport clients | tokenable = agent |
| `ai_providers` | workspace_id, provider, model, endpoint, encrypted key, monthly_token_cap, tokens_used_month |
| `agent_roles` | workspace_id (null = built in), name, instructions, allowed_tools JSON, apply_mode |
| `project_agent_settings` | project_id, instructions, mode_c_enabled |
| `agent_runs` | issue_id, user_id, agent_id, role_id, instruction, status, steps JSON, tokens_in, tokens_out, error, started_at, finished_at |
| `agent_run_changes` | run_id, kind, target, before, after, status (proposed/applied/discarded/conflict) |

`activity_log`, `notifications` and `audit_logs` gain `agent_id` and `agent_run_id`; `via` gains `agent_run`.

---

## 9. Platform management (private repo; not built here)

The Cloud business side (plans, Stripe billing, seats, add-ons, trials, dunning, staff console, lifecycle jobs) is a separate private Laravel app, specified in `platform-management-spec.md`. This repo only builds the **contract** it relies on, in step 3 (7.10). Keep the contract identical in both specs; change it in both or not at all. Shared JSON schemas for every admin API call and system event live in `docs/contract/` in both repos, and tests in each repo validate against them.

**Internal admin API** (`/internal/v1`, enabled only when `PM_INTERNAL_API_TOKEN` is set; Bearer service token, with `PM_INTERNAL_API_TOKEN_NEXT` also accepted for rotation; `Idempotency-Key` on every write; every write recorded in the workspace audit log with actor type `staff` or `system`):

| Method and path | Purpose |
|---|---|
| `GET /workspaces?updated_since=&cursor=` | List workspaces with usage (reconciliation) |
| `GET /workspaces/{id}` | Name, slug, state, Owners, entitlements, usage |
| `GET /workspaces/{id}/usage` | Billed users, Viewers, pending billed invitations, projects, storage MB, agents, API calls (30 days), last activity |
| `PUT /workspaces/{id}/entitlements` | Replace plan entitlements (`source: plan`) |
| `PUT` / `DELETE /workspaces/{id}/entitlements/{key}` | Set or remove an override (`source: override`, with reason) |
| `POST /workspaces/{id}/suspend` / `unsuspend` | Suspended = read-only with a notice |
| `POST /workspaces/{id}/archive` / `restore` | Free inactivity archiving |
| `POST /workspaces/{id}/support-sessions` | Staff impersonation: staff name/email, target user, reason, minutes (max 60) → one-time sign-in URL; logged as `staff.support_session_started`; a banner shows during the session |

**System events** (sent to `PM_SYSTEM_EVENTS_URL`, HMAC-SHA256 signed with `PM_SYSTEM_EVENTS_SECRET`, timestamp header, retried for up to 24 hours). Every event has `id`, `type`, `occurred_at`, `workspace_id` and a usage snapshot (billed users, Viewers, pending billed invitations, projects, storage MB): `workspace.created` (name, slug, first Owner), `workspace.updated`, `workspace.deleted`, `workspace.owner_changed`, `member.added` / `member.removed` / `member.role_changed` (user, roles, billed or not, before/after), `project.created` / `project.archived` / `project.deleted`, `storage.changed` (at most hourly), `workspace.activity` (at most daily).

**Billing link:** "Billing" (Owners only) redirects to `PM_BILLING_URL/session?token=…`. The token is HMAC-SHA256 signed with `PM_BILLING_LINK_SECRET` and holds workspace id, user id, email, name, workspace role, issued-at, expiry (5 minutes) and a single-use nonce.

**Billing entitlement keys** (informational, set by Platform management, shown by the core):

| Key | Values | Core shows |
|---|---|---|
| `plan` | `free`, `trial`, `paid` | Plan name on Usage and limits |
| `trial_ends_at` | date or empty | "Trial: N days left" banner to Owners |
| `billing_notice` | empty, `past_due`, `canceling`, `read_only` | Payment-problem, cancellation-date or over-limit banner, with the Billing link for Owners |

**Sign-up flow:** the core creates the workspace with default entitlements and sends `workspace.created`; Platform management creates the Stripe customer, starts the 14-day trial and pushes Paid entitlements; at trial end without a card, it pushes Free.

---

## 10. Deferred and out of scope

**Deferred (future features):** components (with component leads and auto-assignment); moving issues between projects and cross-project cloning (key redirects, a mapping screen for type, status and fields); voting (one vote per person, sort by votes; agents can't vote); threaded replies (one level, resolvable threads); presence and co-editing; query language (e.g. `assignee = me AND updated > -7d`, with autocomplete); filter subscriptions (in-app alerts or a daily digest); workspace-wide saved filters; AI sprint summary; people-vs-agents report split; multiple boards per project; automation rules (including "PR merged → Done"; open question then: does an automation triggered by a person's merge count as a person for the Done rule?); full GitHub App, GitLab, Bitbucket; Slack; issues from email; plugins; editable resolutions; custom link types; start-to-start, finish-to-finish and lag; formula, issue-picker and cascading fields; Microsoft sign-in; SSO and SCIM; restricted issues; Jira import; custom roles; autonomous agents; handing off from Mode C to a coding agent; per-user AI keys; a model per role.

**UI decided for later** (5.1): the ⌘K palette and shortcut registry as a cross-cutting requirement; the final keyboard map; the status stepper; Blocked and At risk flags on cards; triage inbox, project health and sprint capacity (step 10 or later); Hill Charts; sample data for new workspaces.

**Out of scope:** email notifications; time logging (worklogs, time spent, remaining).

---

## 11. Open questions

None blocks step U or step 0 sub-steps 1–4. Ask the user when the relevant step is planned.

| # | Question | Leaning | Step |
|---|---|---|---|
| 1 | Workspace URL: path prefix or subdomain? | Path prefix `/{slug}` | 0 |
| 2 | Over-limit read-only rules after a downgrade | As in section 4 | Platform management |
| 3 | Workspace deletion: immediate or 7-day undo? | 7-day undo | 0 |
| 4 | Domains (e.g. `app.<domain>` for the product, `<domain>` for marketing and billing); final product name (PmOps for now) | — | Before launch |
| 5 | Mode C default: propose or apply? | Propose, per-role switch | B |
| 6 | Run log retention (30 days full detail) | Enough for now | B |
| 7 | Test case shared across projects? | No | 13 |
| 8 | Failed test step creates a bug automatically? | Offer only | 13 |
| 9 | Who creates, renames and merges library statuses? | Project Admins pick or create while editing columns; workspace Owners/Admins rename, recolour, merge and delete (7.4) | 1 |
| 10 | Workflow data stored as versioned JSON snapshots? | Yes (7.3) | 12 |
| 11 | How long are soft-deleted issues kept before purging? | 30 days | U |

**Decided in 1.3:** Linear/Selvar style replaces Jira-style; the repo stays React; full issue page plus peek drawer; agents are never assignees; status colour by category with an optional custom colour; issue types keep the spec's list with a colour next to the icon; Undo for single changes, confirm for rule warnings, preview for bulk and multi-item deletes; soft delete; Motion plus plain CSS; CSS written fresh with Selvar as reference only; UI foundation step before step 0; "sprint", not "cycle". **Decided in 1.2:** statuses are a workspace library from step 1; agent Done requests can be approved by the agent's owner or a project Admin; pending requests expire after 7 days.
