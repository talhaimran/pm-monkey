# Step U: UI Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task (the user chose inline execution: one session, sub-step by sub-step, a short report after each). Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Replace the Jira-style UI with the PmOps design system on every existing screen, and add Undo toasts, soft delete, the peek drawer and one reduced-motion switch.

**Architecture:** `docs/design-system/tokens.json` stays the single source of tokens: `tailwind.config.js` reads it and emits CSS variables switched by `.dark`, and `resources/js/motion.ts` reads its duration and easing tokens. Existing component files are restyled in place (no files deleted, no renames), plus a small set of new components. Soft delete uses Laravel's `SoftDeletes`; Undo is an ordinary update, move or restore sent back through the same services with a staleness check.

**Tech Stack:** Laravel 13, Pest on MySQL, Inertia + React 18 + TypeScript, Tailwind 3, `@headlessui/react`, `@dnd-kit`. New: `motion`, `lucide-react`, `@fontsource/urbanist` (approved; nothing else).

**Spec:** `docs/specs/pmops-spec.md` sections 5.1 and 6 (step U row and the "Step U acceptance" paragraph). Design system: `docs/design-system/` (README first).

## Global Constraints

- Spec wins over the design system; CLAUDE.md is in force (services hold logic, thin controllers/tools, Form Requests, Policies, Pest on MySQL).
- One commit per task below, on `step-u/ui-foundation`. After every commit: `php artisan test`, `npm run build`. Per task: security review of the diff, one line in PROGRESS.md.
- No dependency beyond `motion`, `lucide-react`, `@fontsource/urbanist`. No schema change beyond `deleted_at` on `issues` and `comments`.
- No file is deleted. Existing component files keep their names (`PrimaryButton`, `StatusLozenge`, …) and are restyled in place.
- Sizes, colours and timings come from tokens; no hex colours and no hard-coded durations in `resources/js`.
- WCAG 2.2 AA; light and dark on every screen; under 760px the sidebar is a sheet and drawers/dialogs rise from the bottom.
- Words: "sprint", sentence case, verbs on buttons, numbers with units ("13 pts").
- Toast: 6s with Undo, 4.2s without, at most 3 stacked, `role="status"`.
- Internal identifiers (`pm_monkey` DBs, `pm` handle, `PM_*`, `pm.via`) are not touched.

## Decisions for you to confirm or change

These are my defaults where the spec is silent or marked (leaning). Reply with changes alongside "go".

| # | Topic | Default in this plan |
|---|---|---|
| D1 | **(leaning) 30-day purge** | `php artisan issues:purge`, scheduled daily, force-deletes issues (and comments) soft-deleted more than 30 days ago. |
| D2 | Where `issue.purged` is logged | Activity rows are deleted with the issue, and the audit log arrives in step 0. Until then: a `Log::info('issue.purged', …)` line (key, title, project, deleted_at). |
| D3 | Activity action names | Spec says `issue.deleted` / `issue.restored` / `issue.purged`; existing rows use unprefixed `created` / `updated` / `commented`. Default: unprefixed `deleted`, `restored`, `undid`, to match existing data. Say if you want the prefixed names (I would then not rename the old ones). |
| D4 | Sub-tasks of a deleted issue | Today they are hard-deleted with the parent. Default: soft-deleted with the parent (same timestamp) and restored with it. Issues in a deleted epic keep `epic_id`; the epic just doesn't show until restored. |
| D5 | Who can restore | Anyone who may delete the issue or comment (so Undo works for the person who deleted it). The "Deleted issues" list in project settings is Admin-only, as the spec says. No API/MCP restore endpoint in step U. |
| D6 | "Changed meanwhile" check for Undo | Edit-conflict versions arrive in step 1. Until then Undo carries the id of the activity row its change wrote; if the issue has a newer activity row, Undo stops with "Can't undo: {name} {what changed}". `shortcut:` a rank-only reorder by someone else writes no activity and is not detected. |
| D7 | Undo is web-only | `undo_of` is validated in the web controllers only, so API/MCP input shapes don't change. |
| D8 | URL scheme | Full page stays `/issues/{KEY}` (no workspaces until step 0, which adds the prefix). Peek is `?issue={KEY}` on board, project list and search. |
| D9 | Board totals and density (spec 5.1 "Board") | Section 6's build-order table gives "board improvements" to step 10. Default: step U shows the points sum and issue count in each column header (it is part of the column's anatomy); compact density above 12 and "Show N more" above 20 wait for step 10. |
| D10 | Warning confirm | No rule warnings exist yet (workflows are step 12). Default: build the component and use it now for the confirms that already exist (complete sprint with open issues; delete project, column, label, sprint, token, account). Single issue/comment deletes lose their confirm and get a toast with Undo, as 5.1 says. |
| D11 | Sidebar | Dark sidebar, 256px, sheet under 760px. No workspace switcher (step 0): its slot shows the logo and "PmOps". The 92px collapsed rail is not built. |
| D12 | AI box | Nothing to show before steps A/B, so it is not rendered. |
| D13 | Motion scope | Built: drawer, dialog, toast, drag lift/drop settle, theme toggle (View Transitions). Not built: app-load cascade, page-change fade, nav icon pop, nav group unfold, form-error shake, charts, palette. |
| D14 | Frontend tests | No JS test runner is installed and none is approved. Pure logic (`motion.ts`, `undo.ts`, contrast of token pairs) is tested with Node's built-in runner (`node --test`, zero dependencies; Node 26 runs `.ts` directly). Rendering, focus and keyboard checks are done in the browser preview with a scripted audit, and I report the output. If you want Vitest + Testing Library or Playwright/axe instead, that is a new dev dependency: say so. |
| D15 | Density/theme preferences | Stay in `localStorage` (no schema change). |

## Design system / spec differences found

Spec wins in each case; listed so you can fix the design system.

1. **Status wording** — decided 2026-11-14: keep the sentence-case "In progress" wording of the previews. The spec and `tokens.json` usage notes still say "In Progress" and should be changed to match. Step U shows each project's own column names, so no code depends on this yet.
2. **Agent label** — decided 2026-11-14: "{Owner}'s agent" (or its name) stands; these pages still need fixing: `IssuePage`, `ApprovalCard`, `AuditLogTable`, `AgentRunPanel`, `Card`, `NotificationInbox` say "Triage agent (AI)"; spec and `Avatar` README say "{Owner}'s agent" or its name.
3. **Empty-state copy:** `EmptyState` says "Create one, or import from Jira or CSV." (import is step 5); spec says "No issues yet. Create your first issue · C".
4. **Avatar tokens had no dark values** — fixed in PR 5 (`docs/design-system-pmops`): dark pairs added, contrast 5.5–6.1. U1 needs that PR merged into this branch first.
5. **`side-faint` fails AA** (4.4 / 3.9) but is assigned to sidebar group labels; spec asks for WCAG 2.2 AA. The plan uses `side-dim` for those labels.
6. **`faint` fails AA in light** (3.5–3.9); the plan uses it only for placeholders and decorative text.
7. **Old name** — fixed in PR 5: the design system is now titled PmOps. Example keys (`PM-142`) are unchanged.
8. **Workspace URL and switcher:** both documents show `/{workspace}/issues/{KEY}` and a workspace switcher; neither exists before step 0 (see D8, D11).
9. **Issue page extras:** Watch, Move, Clone, Linked issues, Attachments, Reporter, "Show 6 more fields" belong to later steps; step U shows only what exists.
10. **Motion catalog vs "nothing plays on its own":** the app-load cascade (700ms on every load) sits uneasily with spec 5.1; not built (D13).
11. **Toast `Retry` variant** has no spec text; built only as the failure style, without Retry.

## Spec 5.1 coverage map

| Spec 5.1 item | Task |
|---|---|
| Tokens into `tailwind.config.js` (CSS variables, `.dark`), replacing Jira tokens; spacing, radius, shadow, type scales | U1 |
| Urbanist (`@fontsource/urbanist`) | U1 |
| Lucide icons, 1.8px stroke | U3 |
| Motion; one `motion.ts`; one reduced-motion switch; nothing loops | U2 (then used in U3, U4, U7, U8, U9) |
| View Transitions for the theme toggle; keep the toggle | U4 |
| Accessibility checks part of "done" | every UI task; audited in U13 |
| Light and dark on every screen | U1 tokens; checked per screen task |
| Under 760px: sidebar sheet, drawers and dialogs from the bottom | U3 (dialog), U4 (sidebar), U8 (drawer) |
| Full issue page + peek drawer, `?issue={KEY}`, "Open full page", "Copy link" | U8 |
| AI box | not in step U (D12) |
| Agents never assignees / agent avatar | no agents yet; nothing to build |
| Undo toast, 6s, ⌘Z/Ctrl+Z; Undo is a reverse change through the same services; stops when the issue changed | U6 (backend), U7 (frontend) |
| Rule warnings need a confirm | U3 component (D10) |
| Bulk preview then Undo | no bulk UI exists in the web app yet (step 4); not built |
| Soft delete, key never reused, restore by Undo or Admin, purge (leaning), API/MCP soft, events | U5 |
| Status colour from category | U3 `StatusLozenge` keeps first/middle/last column → To Do/In Progress/Done colours until step 1 |
| Type icon and colour, colour never alone | U3 |
| Live updates | step 3 |
| Board totals / density | U9 (D9) |
| Empty states on every list, board, panel, settings page | U3 component; used in U8–U12 |
| Focus grouping | step 2 |
| Words | every screen task |
| Components built from their design system page | see screen map |

## Acceptance paragraph map (spec section 6)

| Acceptance line | Proven by |
|---|---|
| Every existing screen uses the tokens and components in light and dark | U4, U8–U12; U13 screenshots of each screen in both themes |
| No Jira tokens remain | U1 `tests/Feature/Ui/DesignTokensTest.php` |
| Existing test suite passes | `php artisan test` after every commit |
| Undo works for a board move, a status change, a field edit and a delete | U6 `UndoTest.php` (4 tests), U7 browser check |
| …and stops with a message when the issue changed meanwhile | U6 `UndoTest.php` "stops when the issue changed" |
| Deleted issues disappear from every screen, API and MCP response and can be restored | U5 `SoftDeleteTest.php` |
| Peek drawer and full page share one URL scheme | U8 `IssuePeekTest.php` + `issueUrl()` used by both |
| With reduced motion on, nothing animates | U2 `motion.test.ts`, CSS rule guard, browser check |
| Accessibility checks pass on board, issue page and drawer | U13 scripted audit + keyboard walk-through, output quoted |
| `npm run build`, Pint and the security review are clean | every task; U13 final run |

## Screen → design system components

| Screen (file) | Components |
|---|---|
| App shell (`Layouts/AuthenticatedLayout.tsx`, `ProjectNav.tsx`) | SidebarNav, Avatar, Toast, buttons |
| Board (`Pages/Projects/Board.tsx`) | BoardColumn, IssueCard, Pill, Avatar, FilterBar (chip styling only), EmptyState (`col__empty`), IssueDrawer, Toast |
| Backlog (`Pages/Projects/Backlog.tsx`) | Card, IssueCard (compact rows), Pill, Avatar, EmptyState, WarningConfirm (complete sprint), Toast |
| List (`Pages/Projects/Issues.tsx`, `IssueTable.tsx`) and Search (`Pages/Search.tsx`) | table markup from AuditLogTable rules (`<th scope>`, `aria-sort`), Pill, Avatar, FilterBar (chip styling), EmptyState, IssueDrawer |
| Issue page (`Pages/Issues/Show.tsx`) | IssuePage, Pill (status menu), Avatar, IssueCard (compact sub-tasks), Timeline-style activity list, EmptyState, Toast |
| Dashboard (`Pages/Dashboard.tsx`) | DashboardWidget, KpiTile, Card, Pill, EmptyState |
| Projects (`Pages/Projects/Index.tsx`, `Settings.tsx`) | Card, buttons and fields, Avatar, Pill, EmptyState, WarningConfirm (deletes) |
| Auth (`Layouts/GuestLayout.tsx`, `Pages/Auth/*`) | Card, buttons and fields |
| Profile (`Pages/Profile/*`) | Card, buttons and fields, WarningConfirm (delete account) |
| Tokens (`Pages/Tokens/Index.tsx`) | Card, table rules, Pill, EmptyState, WarningConfirm (revoke) |
| Welcome (`Pages/Welcome.tsx`) | Card, buttons (it still carries 32 skeleton colours; restyled so no stray colours remain) |

## Schema changes

One migration, `database/migrations/2026_11_14_000001_add_soft_deletes.php`:
- `issues.deleted_at` nullable timestamp, indexed.
- `comments.deleted_at` nullable timestamp, indexed.

Nothing else. `unique(project_id, number)` and `projects.issue_counter` already guarantee a key is never reused.

## Review Focus

1. **Undo into something that no longer exists** (the previous column, sprint or assignee was removed meanwhile): Undo fails with the normal "valid options" error and the toast shows it; nothing is half-applied. Test in U6.
2. **Soft-deleted issues inside a column or sprint that is deleted or completed:** deleting a column moves its deleted issues too (no FK failure); completing a sprint does not carry deleted issues over or count them. Tests in U5.
3. **Restoring a sub-task whose parent is still deleted:** refused with a message naming the parent. Test in U5.
4. **`?issue=` with a key the user can't see, a deleted key, or junk:** the page renders normally with no drawer and no hint the issue exists. Test in U8.
5. **⌘Z while typing:** the shortcut is ignored when focus is in an input, textarea or contenteditable, so native text undo still works; with two toasts it undoes the newest only, once. Test in U7 (`undo.test.ts`).

> **Status (2026-11-14):** built and merged in PR 4. Boxes are ticked for what was done; where the build differs from a step (no `ShellTest`, no `BoardTotalsTest`, native status select, cross-fade theme toggle) it is listed in PROGRESS.md under "Shortcuts from step U".

---

### Task U1: Tokens, font and base styles

**Files:**
- Modify: `tailwind.config.js`, `resources/css/app.css`, `resources/js/app.tsx`, `package.json`, `package-lock.json`, every `.tsx` under `resources/js` that uses a Jira token class
- Create: `tests/Feature/Ui/DesignTokensTest.php`, `resources/js/tokens.test.ts`

**Interfaces — produces:**
- Tailwind colours named exactly as the design tokens (`bg`, `card`, `card-2`, `well`, `line`, `line-2`, `text`, `muted`, `faint`, `ink`, `on-ink`, `primary`, `on-primary`, `side*`, `c-*`, `t-*`, `av-*`, `tile-*`), each `var(--<name>)`, light on `:root`, dark on `.dark`.
- `spacing` (`0.5`…`8` from `space-*`), `borderRadius` (`xs sm ctl item tile col card sheet pill`), `boxShadow` (`xs DEFAULT pop lift`), `fontSize` (`display`, `heading-4xl`…`text-2xs`, `body` with line height, weight, tracking), `height`/`width` sizes (`control`, `control-sm`, `input`, `pill`, `side`, `drawer`), `transitionDuration` and `transitionTimingFunction` from `dur-*` / `ease-*`, screen `shell: '760px'`.
- npm script `"test": "node --test \"resources/js/**/*.test.ts\""`.

- [x] **Step 1: failing guard test** `tests/Feature/Ui/DesignTokensTest.php`:
  - `it('has no Jira tokens')`: `tailwind.config.js` does not contain `Jira`, `loz-`, `ink-subtle`, `sunken`, `raised`, `input-line`, `brand`; no file in `resources/js` matches `/\b(bg|text|border|ring|divide|outline|fill|stroke|placeholder|shadow)-(ink-subtle|surface|sunken|raised|input-line|brand|brand-hover|on-brand|selected|neutral|neutral-hover|danger|danger-subtle|warning-subtle|warning-ink|success|loz-[a-z-]+)\b/`.
  - `it('has no raw colours in components')`: no `#[0-9a-fA-F]{3,8}\b` and no `(gray|indigo|red|green|blue)-\d{2,3}` class in `resources/js/**/*.tsx`.
  - `it('exposes every colour token')`: each `color.tokens[].name` in `docs/design-system/tokens.json` appears in the built CSS variable list (assert against `tailwind.config.js` reading the file, i.e. the config contains `tokens.json` and no inline colour table).
- [x] **Step 2:** run `php artisan test tests/Feature/Ui` → FAIL.
- [x] **Step 3:** `npm install motion lucide-react @fontsource/urbanist`; confirm each installed `package.json` licence is MIT / ISC / OFL-1.1 and quote it in the report. Stop if not.
- [x] **Step 4:** rewrite `tailwind.config.js` to read `docs/design-system/tokens.json` (`readFileSync` + `JSON.parse`) and build the theme above. Every colour token has light and dark values once PR 5 is merged; a token with only `light` uses it for both.
- [x] **Step 5:** `app.tsx` imports `@fontsource/urbanist/{400,500,600,700,800}.css`; `app.css` base: `body` = `bg-bg text-text text-body font-sans antialiased`; `:focus-visible` = 2px `primary` outline, 2px offset; `@media (prefers-reduced-motion: reduce)` sets `transition-duration`, `animation-duration` to `0s !important` on `*, ::before, ::after` and disables `::view-transition-*` animation.
- [x] **Step 6:** mechanical class rename across `resources/js` so the build stays green: `ink→text`, `ink-subtle→muted`, `surface→bg`, `sunken→card-2`, `raised→card`, `line→line`, `input-line→line-2`, `brand→primary`, `brand-hover→primary`, `on-brand→on-primary`, `selected→well`, `neutral→card-2`, `neutral-hover→well`, `danger→c-bad` (text: `t-bad`), `danger-subtle→c-bad/15`, `warning-subtle→c-warn/15`, `warning-ink→t-warn`, `success→c-good`, `loz-todo*→c-sky/t-sky`, `loz-progress*→c-amber/t-amber`, `loz-done*→c-good/t-good`, `shadow-card→shadow-xs`. Screens are properly restyled in later tasks; this step only removes the old names.
- [x] **Step 7:** `resources/js/tokens.test.ts` (`node --test`): contrast ratio ≥ 4.5 for `text`, `muted` on `card`, `card-2`, `bg`; `on-primary` on `primary`; `side-dim` on `side`; each `t-*` on its 14% (light) / 18% (dark) tint over `card`, in both themes.
- [x] **Step 8:** `php artisan test`, `npm test`, `npm run build`, `vendor/bin/pint --test` → all pass. Open the board in the preview in light and dark; report a screenshot.
- [x] **Step 9:** commit `feat(ui): design system tokens, Urbanist and base styles`.

### Task U2: `motion.ts` and the reduced-motion switch

**Files:**
- Create: `resources/js/motion.ts`, `resources/js/motion.test.ts`
- Modify: `resources/js/app.tsx`, `tsconfig.json` (only if `resolveJsonModule` is off)

**Interfaces — produces:**
```ts
export type Moment = 'drawerIn' | 'drawerOut' | 'dialogIn' | 'dialogOut' | 'toast' | 'lift' | 'drop' | 'theme';
export function prefersReducedMotion(): boolean;                       // the one switch (matchMedia)
export function transition(moment: Moment, reduced?: boolean): { duration: number; ease: [number, number, number, number] }; // seconds
export const stagger: number;                                           // seconds, 0 when reduced
```
Values (from `motion.md`): drawerIn 550ms `ease-out-strong`; drawerOut 240ms `ease-in`; dialogIn 450ms `ease-overshoot`; dialogOut 180ms `ease-in`; toast 300ms `ease-out`; lift 250ms `ease-overshoot`; drop 320ms `ease-out`; theme 480ms `ease-standard`. Durations that match a `dur-*` token read it; 240/300/320/480 are catalog values not in `tokens.json` and are named constants in this file only. Easings are parsed from the `ease-*` token strings.

- [x] **Step 1: failing tests** `motion.test.ts`: `transition(m, true).duration === 0` for every `Moment`; `transition('drawerIn', false)` equals `{ duration: 0.55, ease: [0.165, 0.84, 0.44, 1] }`; `transition('dialogIn', false).ease` equals `[0.34, 1.56, 0.64, 1]`.
- [x] **Step 2:** `npm test` → FAIL. Implement `motion.ts`. `npm test` → PASS.
- [x] **Step 3:** wrap the Inertia app in `<MotionConfig reducedMotion="user">` in `app.tsx`. Components must take timings only from `transition()` (add to the guard test: no `duration:` number literal in `resources/js/**/*.tsx`).
- [x] **Step 4:** full verification; commit `feat(ui): motion timings and one reduced-motion switch`.

### Task U3: Primitives restyled in place

**Files:**
- Modify: `Components/{PrimaryButton,SecondaryButton,DangerButton,TextInput,InputLabel,InputError,Checkbox,Dropdown,Modal,StatusLozenge,Avatar,IssueTypeIcon,PriorityIcon,Pagination,NavLink,ResponsiveNavLink,CompleteSprintModal}.tsx`
- Create: `Components/EmptyState.tsx`, `Components/WarningConfirm.tsx`

**Interfaces — produces:**
```ts
// EmptyState.tsx
export default function EmptyState(props: { icon: LucideIcon; title: string; hint?: string; action?: { label: string; onClick: () => void; shortcut?: string }; compact?: boolean }): JSX.Element;
// WarningConfirm.tsx — Headless UI Dialog, focus starts on Cancel
export default function WarningConfirm(props: { open: boolean; title: string; warnings: { text: string; reason?: string }[]; confirmLabel: string; onConfirm: () => void; onCancel: () => void; processing?: boolean }): JSX.Element;
```
- Buttons: 40px (`h-control`), `rounded-ctl`; primary = `primary`/`on-primary`; secondary = `card` with `line-2`; danger = `c-bad`.
- `StatusLozenge` renders the Pill: 24px, dot + label, tint 14% light / 18% dark, text `t-*`; To Do `sky`, In Progress `amber`, Done `good`.
- `Avatar`: hashes the email into one of the seven `av-*` pairs; sizes xs 24, sm 32, md 40, lg 52.
- `IssueTypeIcon`: lucide icon in a tinted chip: Task `slate`, Story `cobalt`, Bug `coral`, Epic `amber`, sub-task `sky`; always with `aria-label`. `PriorityIcon`: lucide `Signal*` icons + label. All lucide icons use `strokeWidth={1.8}`; the 21 inline `<svg>`s are replaced.
- `Modal`: Motion + `AnimatePresence`, `transition('dialogIn'|'dialogOut')`, `rounded-card`, `shadow-pop`; under `shell` it is a bottom sheet (`rounded-t-sheet`).
- `CompleteSprintModal` uses `WarningConfirm` when the sprint has open issues.

- [x] **Step 1:** extend the guard test: no `<svg` in `resources/js/**/*.tsx` except `ApplicationLogo.tsx`; no `outline-none` without `focus-visible:` on the same element.
- [x] **Step 2:** run → FAIL; restyle each component from its design system page; run → PASS.
- [x] **Step 3:** browser check in light and dark: a dialog opens, traps focus, Esc returns focus to its trigger; report screenshots.
- [x] **Step 4:** full verification; commit `feat(ui): buttons, fields, pills, avatars, icons, dialog, empty state, warning confirm`.

### Task U4: App shell with the dark sidebar

**Files:**
- Modify: `Layouts/AuthenticatedLayout.tsx`, `Components/ProjectNav.tsx`, `Components/ThemeToggle.tsx`, `Layouts/GuestLayout.tsx`

- Sidebar: `side` background, 256px, always dark. Top slot: logo + "PmOps". Items (42px, icon + label): Your work, Projects, then the current project's Board, Backlog, Issues, Settings (what `ProjectNav` shows today) under an uppercase group label in `side-dim`. Current item: `side-pill` fill, `aria-current="page"`. Bottom: search, Create (`C`), theme toggle, account menu (Profile, API tokens, Log out).
- Under 760px: a top bar with a menu button opens the sidebar as a Headless UI `Dialog` sheet from the left.
- `ThemeToggle`: lucide `Sun`/`Moon`/`Monitor` with an `aria-label` naming the next state; switches inside `document.startViewTransition` when available and not reduced-motion; otherwise switches at once.
- Existing shortcuts (`/`, `c`) keep working.

- [x] **Step 1:** `tests/Feature/Ui/ShellTest.php`: authenticated pages still render (`dashboard`, `projects.index`, board) with status 200 and the same Inertia components (guards the layout props it depends on).
- [x] **Step 2:** build the shell; browser check at 1280px and 375px, light and dark, keyboard tab order through the sidebar; screenshots.
- [x] **Step 3:** full verification; commit `feat(ui): app shell with dark sidebar and mobile sheet`.

### Task U5: Soft delete for issues and comments

**Files:**
- Create: `database/migrations/2026_11_14_000001_add_soft_deletes.php`, `app/Console/Commands/PurgeDeletedIssues.php`, `tests/Feature/Issues/SoftDeleteTest.php`
- Modify: `app/Models/{Issue,Comment}.php`, `app/Services/{IssueService,CommentService,ColumnService,SprintService}.php`, `app/Policies/*` (restore abilities), `app/Http/Controllers/Web/{IssueController,ProjectController}.php`, `routes/web.php`, `routes/console.php`, `README.md`, `AGENTS.md` (delete tools are soft)

**Interfaces — produces:**
```php
IssueService::delete(Issue $issue, User $actor): Activity      // soft; logs 'deleted'; soft-deletes sub-tasks with the same timestamp
IssueService::restore(Issue $issue, User $actor): Issue        // logs 'restored'; restores sub-tasks deleted at the same timestamp
IssueService::resolveDeleted(User $user, string $key): Issue   // same 404 messages as resolve()
IssueService::deleted(Project $project): Collection           // newest first, for project settings
CommentService::delete(Comment $comment): void                 // soft
CommentService::restore(Comment $comment): Comment
```
Routes: `POST /issues/{key}/restore` (`issues.restore`), `POST /comments/{comment}/restore` (`comments.restore`). Project settings gets a `deletedIssues` prop (Admins only). Callers of `IssueService::delete` (web, API, MCP) pass the actor.

- [x] **Step 1: failing tests** `SoftDeleteTest.php`:
  - deleting sets `deleted_at`, keeps the row, logs `deleted` with the actor and `via`
  - a deleted issue is absent from: board, backlog, project list, search, dashboard counts, column `issues_count`, sprint counts; `GET /api/v1/issues/{key}` returns 404; MCP `get_issue`, `search_issues`, `get_board`, `get_backlog`, `my_issues`, `project_summary` leave it out
  - API and MCP deletes are soft and still need `confirm: true`
  - the next issue created gets a new number (key not reused)
  - `restore` brings back the issue and its sub-tasks, logs `restored`
  - restoring a sub-task whose parent is deleted fails with a message naming the parent (Review Focus 3)
  - deleting a column that holds deleted issues moves them too; completing a sprint ignores deleted issues (Review Focus 2)
  - a Viewer and a read-only token cannot delete or restore; a member of another project gets the normal 404
  - deleted comments disappear from the issue page, API and MCP; restore brings them back
  - `issues:purge` force-deletes issues and comments deleted more than 30 days ago, leaves newer ones, and writes the `issue.purged` log line
- [x] **Step 2:** run → FAIL; migrate; implement; run → PASS.
- [x] **Step 3:** schedule `issues:purge` daily in `routes/console.php`. Update README and AGENTS.md (delete is soft; restored from the web UI).
- [x] **Step 4:** full verification; security review focus: cross-project restore, read-only token, mass assignment of `deleted_at`. Commit `feat: soft delete for issues and comments with restore and 30-day purge`.

### Task U6: Undo on the server

**Files:**
- Create: `app/Services/UndoService.php`, `tests/Feature/Issues/UndoTest.php`
- Modify: `app/Services/{IssueService,BoardService}.php`, `app/Http/Controllers/Web/{IssueController,BoardController}.php`, `app/Http/Middleware/HandleInertiaRequests.php`

**Interfaces — produces:**
```php
// Throws ValidationException (409) "Can't undo: {name} {summary}" when the issue has an activity newer than $activityId.
UndoService::guard(Issue $issue, int $activityId): void
// Request data that reverses an update of $fields, in the shared input shape (status name, assignee email, label names, epic/parent keys, sprint name).
UndoService::forUpdate(Issue $before, array $fields): array
// Request data that puts the issue back: ['status' => name, 'after' => key|null].
UndoService::forMove(Issue $before): array
IssueService::update(Issue $issue, User $actor, array $data, string $action = 'updated'): Issue
```
Web controllers flash `undo` after update, move, delete and comment delete:
```ts
type UndoFlash = { message: string; method: 'patch' | 'post'; url: string; data: Record<string, unknown> }; // data includes undo_of: <activity id>
```
Shared as `flash.undo` in `HandleInertiaRequests`. A request with `undo_of` (validated in the web controller: integer) calls `guard`, runs the same service with action `undid`, and flashes no further `undo`. Messages: "WEB-12 moved to Done", "WEB-12 updated", "WEB-12 deleted", "Comment deleted".

- [x] **Step 1: failing tests** `UndoTest.php`:
  - board move: posting the flashed `data` to the flashed `url` returns the issue to its previous column and position; activity has `undid`
  - status change from the issue page: same
  - field edit (title, long description over 200 chars, assignee, labels): previous values restored exactly
  - delete: flashed undo restores the issue
  - stops when the issue changed: another user updates the issue after the change; the undo request returns 409 with "Can't undo: {their name}" and changes nothing
  - Review Focus 1: the previous column was deleted meanwhile → error lists valid columns; the issue is unchanged
  - `undo_of` pointing at another issue's activity is rejected; API `PATCH /api/v1/issues/{key}` ignores/rejects `undo_of` (input shape unchanged)
  - a second identical undo request returns 409 (the first one wrote a newer activity)
- [x] **Step 2:** run → FAIL; implement; run → PASS.
- [x] **Step 3:** full verification; commit `feat: undo as a reverse change with a staleness check`.

### Task U7: Toast with Undo

**Files:**
- Create: `resources/js/undo.ts`, `resources/js/undo.test.ts`, `Components/Toaster.tsx`
- Modify: `Layouts/AuthenticatedLayout.tsx`, `types/index.d.ts`, `Pages/Issues/Show.tsx` and `Pages/Projects/Board.tsx` (remove the confirm before single issue/comment deletes)

**Interfaces — produces:**
```ts
// undo.ts — pure store, no React
export type Toast = { id: number; message: string; tone: 'plain' | 'good' | 'bad'; undo?: UndoFlash };
export function push(toasts: Toast[], t: Omit<Toast, 'id'>): Toast[];   // keeps the newest 3
export function lifetime(t: Toast): number;                              // 6000 with undo, 4200 without
export function undoTarget(toasts: Toast[]): Toast | undefined;          // newest toast that has undo
export function isTyping(el: EventTarget | null): boolean;               // input, textarea, select, contenteditable
```
`Toaster`: bottom-centre, 22px from the edge, `ink`/`on-ink`, `role="status"`, Motion `transition('toast')` inside `AnimatePresence`; timer pauses on hover and focus; Undo and Dismiss are real buttons; ⌘Z / Ctrl+Z triggers `undoTarget` unless `isTyping`. Undo sends `router[method](url, data, { preserveScroll: true })`; a 409 turns the toast into the `bad` tone with the server's message.

- [x] **Step 1: failing tests** `undo.test.ts`: `push` keeps 3 newest; `lifetime` 6000 / 4200; `undoTarget` returns the newest undoable and `undefined` after it is removed (Review Focus 5: one undo per toast); `isTyping` true for input, textarea, contenteditable.
- [x] **Step 2:** `npm test` → FAIL; implement; → PASS.
- [x] **Step 3:** browser check: move a card → toast → Undo returns it; ⌘Z does the same; delete an issue → Undo restores; with focus in the comment box ⌘Z does not trigger Undo; Tab reaches the Undo button. Quote what was observed.
- [x] **Step 4:** full verification; commit `feat(ui): toast with Undo`.

### Task U8: Full issue page and peek drawer

**Files:**
- Create: `Components/IssueView.tsx` (shared body), `Components/IssueDrawer.tsx`, `resources/js/issueUrl.ts`, `tests/Feature/Issues/IssuePeekTest.php`
- Modify: `Pages/Issues/Show.tsx`, `Pages/Projects/{Board,Issues}.tsx`, `Pages/Search.tsx`, `Components/IssueTable.tsx`, `app/Http/Controllers/Web/{BoardController,IssueListController}.php`, `app/Services/IssueService.php`

**Interfaces — produces:**
```php
// null when $key is empty, malformed, deleted, or not accessible to $user. Never throws.
IssueService::peek(User $user, ?string $key): ?Issue
```
Board, project list and search controllers add an Inertia prop `peek` (the same `IssueResource` the full page uses, or `null`) from `?issue=`.
```ts
export const issueUrl = (key: string) => route('issues.show', key);   // the only place the full URL is built
export const peekUrl = (key: string) => string;                        // current URL with ?issue=KEY
```
- `IssueView` renders title, status menu (Headless UI `Menu` holding a Pill), fields (assignee, priority, estimate "N pts", due, labels, sprint), description; `full` adds sub-tasks, activity and comments. Used by both the page and the drawer.
- Page: `.ipage` layout, main + 320px side; under 760px the side moves above the description; one `h1`; header actions: Copy link, overflow menu with Delete (soft, toast with Undo).
- Drawer: Headless UI `Dialog`, 500px, 12px inset, `rounded-sheet`, `shadow-pop`, `transition('drawerIn'|'drawerOut')`; bottom sheet under 760px; "Open full page" → `issueUrl`, "Copy link" copies `issueUrl`; Esc/scrim removes `?issue` (`router.get` with `preserveState`, `preserveScroll`, `only: ['peek']`); focus returns to the card or row.
- Cards and rows open the drawer on click; Cmd/Ctrl-click and the key link go to the full page.

- [x] **Step 1: failing tests** `IssuePeekTest.php`: board, list and search with `?issue=WEB-1` return `peek.key === 'WEB-1'`; without it `peek` is null; Review Focus 4: a key from a project the user isn't in, a deleted key and `?issue=junk` all give `peek === null` with status 200; `/issues/WEB-1` still renders `Issues/Show`.
- [x] **Step 2:** run → FAIL; implement; run → PASS.
- [x] **Step 3:** browser check: open from the board, reload keeps it open, Back closes it, focus returns to the card, light/dark, 375px sheet.
- [x] **Step 4:** full verification; commit `feat(ui): full issue page and peek drawer sharing one URL scheme`.

### Task U9: Board

**Files:** Modify `Pages/Projects/Board.tsx`, `Components/{IssueFilters,useSortableLanes}.tsx|ts`

- Column: `card-2`, `rounded-col`, min 198px, header = 9px status square + name + count pill, then "N pts" (`text-xl`) with "M issues" in `muted`; horizontal scroll with snap; empty column shows "Nothing in {column}. Drag an issue here."
- Card: IssueCard anatomy (type chip, key, 2-line title, labels line, foot with estimate, priority, due date turning `t-warn` within 2 days and `t-bad` when overdue, assignee avatar xs); `shadow-xs`, `rounded-item`.
- Drag: lift to 104% with `shadow-lift` (`transition('lift')`); neighbours use Motion `layout` with `transition('drop')`, disabled on the dragged card; target column tints.
- Keyboard: `@dnd-kit` `KeyboardSensor` with custom announcements in a polite live region: "{KEY} moved to {column}, position {n} of {m}".
- Empty project: "No issues yet. Create your first issue · C". Filtered to nothing: "No issues match these filters." with "Clear filters".

- [x] **Step 1:** `tests/Feature/Board/BoardTotalsTest.php`: the board payload gives each column `points` (sum of `story_points`, deleted issues excluded) and `count`. Run → FAIL; add to `BoardService::board`; → PASS.
- [x] **Step 2:** restyle; browser check: drag with mouse, move with keyboard and read the live region text, toast + Undo, both themes.
- [x] **Step 3:** full verification; commit `feat(ui): board in the design system with keyboard moves announced`.

### Task U10: Backlog, list and search

**Files:** Modify `Pages/Projects/{Backlog,Issues}.tsx`, `Pages/Search.tsx`, `Components/{IssueTable,IssueFilters,Pagination}.tsx`

- `IssueTable`: real `<table>`, `<th scope="col">`, sortable headers are buttons with `aria-sort`; rows open the drawer.
- Backlog: sprint sections as Cards with "N issues · M pts"; rows as compact issue cards; drag as on the board.
- Empty states: backlog "No issues in the backlog. Create your first issue · C"; sprint "Nothing planned. Drag an issue here."; list/search "No issues match these filters." + "Clear filters".

- [x] **Step 1:** restyle; existing `Lists` and `Sprints` feature tests must stay green. Browser check in both themes and at 375px.
- [x] **Step 2:** full verification; commit `feat(ui): backlog, list and search in the design system`.

### Task U11: Dashboard, projects and project settings

**Files:** Modify `Pages/Dashboard.tsx`, `Pages/Projects/{Index,Settings}.tsx`, `Components/IssueFormModal.tsx`

- Dashboard: KPI tiles for the existing counts, widgets for assigned issues and recent activity; empty: "You're all caught up."
- Projects: cards with project avatar; empty: "No projects yet. Create your first project."
- Settings: sections as Cards; destructive actions through `WarningConfirm`; new Admin-only "Deleted issues" section listing `deletedIssues` (key, title, deleted when, by whom) with a Restore button each; empty: "Nothing deleted in the last 30 days."

- [x] **Step 1:** `tests/Feature/Projects/DeletedIssuesTest.php`: an Admin sees `deletedIssues`; a Member does not get the prop; Restore from the list works. Run → FAIL; implement; → PASS.
- [x] **Step 2:** restyle; browser check; full verification; commit `feat(ui): dashboard, projects and settings with the deleted issues list`.

### Task U12: Auth, profile, tokens and welcome

**Files:** Modify `Layouts/GuestLayout.tsx`, `Pages/Auth/*.tsx`, `Pages/Profile/**/*.tsx`, `Pages/Tokens/Index.tsx`, `Pages/Welcome.tsx`

- Guest pages: centred Card on `bg`, logo + "PmOps", fields 42px (`h-input`), one primary button.
- Tokens: table rules as in U10; the new-token reveal stays as is functionally; revoke through `WarningConfirm`; empty: "No tokens yet. Create a token to use the API or MCP."
- Profile: three Cards; delete account through `WarningConfirm` (password field kept).
- Welcome: reduced to logo, one line, "Log in" / "Register" buttons.

- [x] **Step 1:** restyle; `Auth`, `ProfileTest`, `Tokens` tests stay green; browser check in both themes.
- [x] **Step 2:** full verification; commit `feat(ui): auth, profile, tokens and welcome in the design system`.

### Task U13: Acceptance audit and PR

**Files:** Modify `PROGRESS.md`, `CLAUDE.md` (token and component notes replacing Jira ones), `README.md` if screenshots or commands changed

- [x] **Step 1:** walk the acceptance paragraph line by line with evidence (table above): command output, test names, screenshots of every screen in light and dark.
- [x] **Step 2:** accessibility audit on the board, issue page and drawer, in the browser preview, quoting output:
  - script: every `a, button, input, select, textarea, [tabindex]` is ≥ 32px high (pills that are not buttons ≥ 24px) and no text is under 11px
  - keyboard walk: Tab reaches every control with a visible 2px ring; Esc closes the top layer and focus returns to its trigger
  - board move by keyboard announces in the live region
  - dialogs and the drawer have an accessible name and trap focus; toast is `role="status"` and pauses on focus
  - status, type, priority and due state each show text or a distinct icon beside the colour
  - reduced motion: state plainly what could and could not be checked in the preview (the OS setting cannot be toggled from here; `motion.test.ts` and the CSS rule are the proof, and I ask you to flip the OS setting once)
- [x] **Step 3:** `php artisan test`, `npm test`, `npm run build`, `vendor/bin/pint --test`, security review of the whole branch diff, `/ponytail-review` on the branch diff. Fix findings with a failing test first.
- [x] **Step 4:** push, `gh pr create`, stop.
