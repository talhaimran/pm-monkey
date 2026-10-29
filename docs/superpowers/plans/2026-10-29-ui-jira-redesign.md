# UI: Jira-style Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle the Inertia/React web UI to look and behave like Jira Software Cloud (company-managed Scrum), frontend only.

**Architecture:** Colour tokens are CSS variables declared once in `tailwind.config.js` (a small `addBase` plugin with `:root` and `.dark` blocks) and exposed as Tailwind colours, so one class (`bg-surface`) works in both themes and most `dark:` classes go away. Existing Breeze components and pages are restyled in place. Four new shared components (IssueTypeIcon, PriorityIcon, StatusLozenge, Avatar) each used in 3+ places. One new prop (`active_sprint` on the board) through `SprintService`.

**Tech Stack:** Laravel 13, Inertia, React, TypeScript, Tailwind 3 (+ @tailwindcss/forms), dnd-kit, Pest. No new dependencies.

**Spec:** the user's redesign brief (2026-10-29 conversation) + SPEC.md (unchanged requirements: keyboard shortcuts, dark mode, responsiveness, roles).

## Global Constraints

- Frontend only. Routes, controllers, services, Inertia props, REST API and MCP output unchanged, except `active_sprint` on `Projects/Board` (Task 3, with a test).
- No new npm/composer dependencies; icons are inline SVG inside the four shared components.
- No Atlassian logo, "Jira" name or proprietary assets. Keep `ApplicationLogo` and "PM Monkey".
- Light tokens: text #172B4D, subtle #44546F, page #FFFFFF, sunken #F7F8F9, border #DCDFE4, primary #0C66E4 (hover #0055CC), selected #E9F2FF, danger #C9372C, success #22A06B.
- Dark tokens: text #B6C2CF, subtle #9FADBC, page #1D2125, sunken #161A1D, raised #22272B, border #38414A, primary #579DFF (hover #85B8FF), selected #1C2B41, danger #F87168, success #4BCE97, neutral button #2C333A.
- System font stack, 14px base, dense spacing; remove Figtree (`tailwind.config.js` and the bunny.net `<link>` in `resources/views/app.blade.php`).
- Keep c, /, Esc, dark mode toggle, mobile layout, and every role check (`can.*`).
- Shortcuts are marked `shortcut: <limit>, <when to upgrade>` in code and listed in PROGRESS.md.
- Per step: `npm run build`, `php artisan test`, manual security review of the diff (user: claude-security plugin runs once, at the end), `migrate:fresh --seed`, screenshots (light + dark, 1440 + 1024), PROGRESS.md "UI: Jira-style redesign", one commit.

## Review Focus

1. Long titles, label lists and names (60+ chars, 10 labels) must wrap or truncate without breaking cards, rows or the sidebar. Checked in each screenshot pass with a seeded long-title issue edited in the browser.
2. Viewer role and read-only state: no Create, drag handles, inline edit or sprint buttons appear for a Viewer (sam@example.com). Checked once per affected screen in the browser; existing Pest role tests stay green.
3. Dark mode contrast: every token pair readable; no hard-coded `bg-white` / `text-gray-*` left on restyled screens (`grep -rn "gray-\|indigo-" resources/js` shrinks each step and is empty after Task 7).
4. Keyboard: "c" on board/backlog/list, "/" on desktop and mobile, Esc closes modals and dropdowns, top-bar "+ Create" is reachable by Tab with a visible focus ring.
5. User content: Markdown still rendered only from server-side `Str::markdown(..., ['html_input' => 'strip', 'allow_unsafe_links' => false])`; label names, user names, sprint names render as text (no new `dangerouslySetInnerHTML`). Checked in every security review.

---

### Task 0: Process change in CLAUDE.md (own commit, before UI work)

**Files:** Modify `CLAUDE.md` (Per-slice checklist; new "Security review (every slice/step)" section, text verbatim from the brief).

- [ ] Replace checklist line with: migrate → Pest suite green → `npm run build` → security review of the diff → update PROGRESS.md → git commit.
- [ ] Add the "## Security review (every slice/step)" section verbatim.
- [ ] Commit: `docs: add security review to the per-slice checklist`.

### Task 1: Tokens and shared components

**Files:**
- Modify: `tailwind.config.js`, `resources/css/app.css` (14px base: `html { font-size: 14px }` would shrink rem spacing too, so instead `body { @apply text-sm text-ink bg-surface }`), `resources/views/app.blade.php` (drop font link)
- Modify: `PrimaryButton.tsx`, `SecondaryButton.tsx`, `DangerButton.tsx`, `TextInput.tsx`, `Checkbox.tsx`, `InputLabel.tsx`, `InputError.tsx`, `Modal.tsx`, `Dropdown.tsx`, `selectClass` in `IssueFormModal.tsx`
- Create: `resources/js/Components/IssueTypeIcon.tsx`, `PriorityIcon.tsx`, `StatusLozenge.tsx`, `Avatar.tsx`

**Interfaces (Produces):**
- Tailwind colours: `ink`, `ink-subtle`, `surface`, `sunken`, `raised`, `line`, `brand`, `brand-hover`, `selected`, `danger`, `success`, `neutral`, `neutral-hover`, plus lozenge pairs `loz-todo`/`loz-todo-ink`, `loz-progress`/`loz-progress-ink`, `loz-done`/`loz-done-ink`.
- `IssueTypeIcon({ type: string; subtask?: boolean; className?: string })`: 16px square SVG with `<title>` for screen readers. story green bookmark, bug red dot, task blue check, epic purple lightning, subtask light blue.
- `PriorityIcon({ priority: string })`: highest/high red up chevrons (double/single), medium orange equals, low/lowest blue down chevrons; `aria-label="Priority: high"`.
- `StatusLozenge({ status: string; columns?: string[] })`: uppercase pill, `last column → done (green)`, `first column or no columns → todo (gray)`, otherwise `progress (blue)`.
- `Avatar({ name: string; email: string; size?: 'sm' | 'md' })`: initials, background picked from 8 fixed colours by a char-code sum of the lowercased email; `title={name}`.
- Buttons: Primary = brand bg / white text, Secondary = neutral gray, Danger = danger bg; new `subtle` look via `SecondaryButton` prop? **No**: subtle is a class string on the few links that need it, no new prop.

- [ ] Add tokens + font stack, restyle Breeze components, add the four components.
- [ ] Use the four components in Board cards (replacing `typeColors`/`priorityArrows`/initials there) so the build proves them.
- [ ] Checks per Global Constraints (screenshots: board + login), PROGRESS.md, commit `feat(ui): Jira-style tokens and shared icons`.

### Task 2: App shell

**Files:** Modify `Layouts/AuthenticatedLayout.tsx`, `Components/ProjectNav.tsx`, `NavLink.tsx`, `ResponsiveNavLink.tsx`, `ThemeToggle.tsx`, `useCreateShortcut.ts`; every page that passes `header` (Board, Backlog, Issues, Settings, Issues/Show, Dashboard, Projects/Index, Search, Profile/Edit, Tokens/Index).

**Interfaces:**
- `AuthenticatedLayout` props become `{ header?: ReactNode; project?: { key: string; name: string } }`. With `project`, renders the left sidebar (`ProjectNav`) at `lg:` and above, and adds the project links to the mobile menu.
- `ProjectNav({ projectKey, name })` is the sidebar: project avatar (key initials on brand colour) + name, Backlog, Board, Issues, divider, Project settings. Active = `bg-selected text-brand` with a 2px brand left bar, `aria-current="page"`.
- New `PageHeader` is **not** added; `header` stays a ReactNode. Breadcrumbs + title + actions are a small inline block per page. (shortcut: no shared header component, duplicated ~5 lines on 4 project pages; extract if a 5th appears.)
- "+ Create": dispatches `CREATE_ISSUE_EVENT` as a cancelable event; `useCreateShortcut` listeners call `preventDefault()`. If nobody handled it (no project page), the layout visits `projects.index`. Same path as "c".
- Top bar 56px (`h-14`): logo, "Your work" (dashboard), "Projects", "+ Create", search (`/` hint as a `<kbd>`), theme toggle, avatar menu (Profile, API tokens, Log out).

- [ ] Implement; confirm "/" still focuses desktop search and the mobile one, "c" still opens create on board/backlog/list.
- [ ] Checks (screenshots: board shell at 1440 + 1024, mobile menu at 375), PROGRESS.md, commit `feat(ui): Jira-style top bar and project sidebar`.

### Task 3: Board

**Files:**
- Modify: `Pages/Projects/Board.tsx`, `Components/IssueFilters.tsx`
- Create: `Components/CompleteSprintModal.tsx` (moved out of `Backlog.tsx` unchanged; now used by Board and Backlog)
- Modify: `app/Services/SprintService.php` (add `active(Project $project): ?Sprint`), `app/Http/Controllers/Web/BoardController.php` (`'active_sprint' => $sprint ? (new SprintResource($sprint))->resolve() : null`, using `withStats` like the backlog so counts match)
- Test: `tests/Feature/Board/BoardMcpAndWebTest.php`

**Interfaces:**
- Produces: Board prop `active_sprint: { id; name; goal; start_date; end_date; ... } | null` (same shape as a backlog sprint).
- `IssueFilters` keeps its props; looks like Jira's toolbar: search box (with icon), assignee `Avatar` buttons as toggles (`aria-pressed`), Type / Label / Priority as compact selects, sprint select kept on list pages, "Clear filters" subtle button only when a filter is set.
- Cards: title, label chips, footer with `IssueTypeIcon` + key (struck through in the last column like Jira), points pill, `PriorityIcon`, `Avatar`. Dragged card: `rotate-2`, larger shadow, brand outline; source slot shows a dashed sunken placeholder.
- Title: active sprint name (or "{KEY} board" when none), sprint dates subtitle, "Complete sprint" (needs `can.contribute`) opens `CompleteSprintModal` with `options.sprints` minus the active one.

- [ ] **Failing test** in `BoardMcpAndWebTest.php`:

```php
test('board page includes the active sprint', function () {
    $sprint = $this->project->sprints()->create(['name' => 'WEB Sprint 1']);
    app(SprintService::class)->start($sprint, []);

    $this->actingAs($this->admin)->get('/projects/WEB')->assertInertia(fn (Assert $page) => $page
        ->where('active_sprint.name', 'WEB Sprint 1'));
    $this->project->sprints()->update(['state' => 'completed', 'active_project_id' => null]);
    $this->actingAs($this->admin)->get('/projects/WEB')->assertInertia(fn (Assert $page) => $page->where('active_sprint', null));
});
```

- [ ] Run, see it fail; add `SprintService::active`, controller prop; run, see it pass.
- [ ] Restyle board, filters, cards; move CompleteSprintModal.
- [ ] Checks (screenshots: board light/dark 1440/1024, dragging card), PROGRESS.md, commit `feat(ui): Jira-style board with active sprint header`.

**Decision (kept as a deliberate difference unless you say otherwise):** the board keeps showing all non-epic issues by default, with the existing sprint filter offering "active sprint". Jira's Scrum board shows only the active sprint, but switching the default changes the web board's behaviour and its tests.

### Task 4: Issue page

**Files:** Modify `Pages/Issues/Show.tsx` only.

- Left (`lg:col-span-8`): breadcrumbs (Projects / Project / parent or epic / KEY), inline-editable title (existing patch), Description (existing editor, Markdown HTML still from `description_html`), Child issues (sub-tasks or epic issues as rows with `IssueTypeIcon`, key, title, `StatusLozenge`), Activity tabs "Comments" / "History" (comment box on top; history keeps "via API"/"via MCP" + token name).
- Right (`lg:col-span-4`): status as a brand/lozenge-coloured `<select>` (existing picker), bordered "Details" panel with existing `sidebarField` rows in Jira order (Assignee, Reporter, Labels, Sprint, Story points, Priority, Due date, Epic/Parent), then "Created … / Updated …" text.
- Delete stays in a "…" action next to the title for `can.manage`/existing rule.
- shortcut: issue opens as a page, not a modal over the board (`?selectedIssue=`), upgrade when people ask to triage without leaving the board.

- [ ] Restyle; confirm both `dangerouslySetInnerHTML` sites are unchanged (server-sanitised).
- [ ] Checks (screenshots incl. History tab with an MCP entry), PROGRESS.md, commit `feat(ui): Jira-style issue view`.

### Task 5: Backlog

**Files:** Modify `Pages/Projects/Backlog.tsx` (uses `Components/CompleteSprintModal.tsx` from Task 3).

- Each sprint: bordered `sunken` container, header row with collapse chevron (local state, trivial), name, dates, "N issues", points, Start sprint (planned, no other active) / Complete sprint (active), "…" edit/delete.
- Backlog section below, header "Backlog (N issues)" + "Create sprint".
- Rows: one line, `IssueTypeIcon`, key, title (truncate), `StatusLozenge` with `options.columns`, points pill, `PriorityIcon`, `Avatar`. Existing `useSortableLanes` drag unchanged.

- [ ] Restyle; drag a row between sprint and backlog in the browser to confirm rank still saves.
- [ ] Checks, PROGRESS.md, commit `feat(ui): Jira-style backlog`.

### Task 6: Issues list and Search

**Files:** Modify `Components/IssueTable.tsx`, `Pages/Projects/Issues.tsx`, `Pages/Search.tsx`, `Components/Pagination.tsx`.

- `IssueTable` gains optional `columns?: string[]` passed through to `StatusLozenge` (project list passes `options.columns`; Search does not).
- Columns in order: Type (icon), Key, Summary, Status, Assignee (`Avatar` + name), Priority (icon + label), Points, Updated. Due date dropped from the table (Jira's default list doesn't show it; still sortable via the filters `sort` select). Sticky header, 36px rows, hover `bg-sunken`.
- shortcut: Search results across projects show every status as a gray lozenge, because rows don't carry their project's columns; upgrade by adding a status category to `IssueResource`.

- [ ] Restyle; checks, PROGRESS.md, commit `feat(ui): Jira-style issue list and search`.

### Task 7: The rest

**Files:** `Pages/Dashboard.tsx`, `Pages/Projects/Index.tsx`, `Pages/Projects/Settings.tsx`, `Pages/Tokens/Index.tsx`, `Pages/Profile/*`, `Pages/Auth/*`, `Layouts/GuestLayout.tsx`, `Components/IssueFormModal.tsx` (Jira create dialog look). `Welcome.tsx` only gets tokens if it is reachable (it is the `/` guest page).

- Swap remaining `gray-*`/`indigo-*`/`dark:` classes for tokens and the shared components; no layout rewrites.
- [ ] `grep -rn "indigo-\|gray-[0-9]" resources/js` returns nothing (or only commented exceptions).
- [ ] Checks, PROGRESS.md, commit `feat(ui): Jira-style dashboard, projects, settings, tokens and auth`.

### Task 8: Finish

- [ ] Full-repository security scan with the `claude-security:claude-security` agent ("scan repository"); fall back to a manual CLAUDE.md-checklist review if it isn't usable. Real findings: failing test in `tests/Feature/Security/`, fix, PROGRESS.md, commit.
- [ ] Report: per-screen summary, screenshots, shortcut list, security findings, deliberate differences from Jira.

## Optional extras

Only done if trivial while in the file; otherwise listed as shortcuts:
- Collapsible sidebar: likely trivial (a button + `localStorage` flag in the layout). Will do in Task 2 if under ~15 lines.
- Inline "+ Create issue" at the bottom of board columns: opens the existing `IssueFormModal` preset to that status. Needs `IssueFormModal` to accept an initial status; do it in Task 3 if `IssueFormModal` already takes defaults, else shortcut.
- Issue modal with `?selectedIssue=`: shortcut (see Task 4).
- "Your work" grouped by status: Dashboard rows already carry `status`; group client-side in Task 7 if trivial.
