# Slice 6: List View, Search, Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** A paginated, sortable issue list with the board's filters; search across projects; "My issues" and per-project summaries (status counts, sprint points progress); keyboard shortcuts (c, /, Esc); a light/dark toggle.

**Spec:** SPEC.md

## Decisions
- `IssueService::filter()` takes a nullable Project, so one filter serves the per-project list, the board and cross-project search. A key in `q` (e.g. API-12) matches by project key and number.
- `IssueService::sort(Builder, ?string)`: key, title, type, priority (enum order), status (column position), assignee (name), story_points, due_date, created, updated; prefix `-` for descending. The default is `-key`. Unknown sorts return 422 listing the options.
- The list includes epics (unlike the board). It's paginated, 25 per page by default, 100 max (MCP max 50).
- Search: `GET /api/v1/issues` across the user's projects, with an optional `project` plus all filters. In the web UI, `/search?q=` jumps straight to the issue when q is an exact key the user can see.
- My issues: assigned to me, not in the project's last (done) column, ordered by priority desc, due date (nulls last), then key.
- `DashboardService::summaries(Collection $projects)` uses batched queries (no per-project loops): status counts in column order, counts by type, open/overdue counts, and active sprint progress (issues done/total, points done/total).
- Shortcuts live in AuthenticatedLayout: `/` focuses the header search, `c` dispatches a `pm:create-issue` window event that project pages (board, backlog, list) listen for, and Esc closes modals (Headless UI already does). Keys are ignored while typing in a field.
- Dark mode: Tailwind `darkMode: 'class'`. An inline script in app.blade.php applies the saved choice before paint (localStorage `theme` = light|dark, otherwise the system setting). The header toggle cycles light → dark → system.

## Interfaces
- `IssueService::filter(Builder, ?Project, array)`, `IssueService::sort(Builder, ?string)`, `IssueService::SORTS`
- `DashboardService`: `myIssues(User, int $limit)`, `summaries(Collection $projects): array<int, array>`
- API: GET projects/{key}/issues, GET issues (search), GET me/issues, GET projects/{key}/summary
- MCP: search_issues, my_issues, project_summary
- Web: /projects/{key}/issues (Projects/Issues), /search (Search), /dashboard (Dashboard)

## Tasks (TDD)
- [ ] Tests: IssueListApiTest (filters, sorts, pagination, search, my issues, summary), ListMcpAndWebTest
- [ ] Services, requests, controllers, routes, MCP tools
- [ ] Inertia pages, shortcuts, theme toggle, nav links
- [ ] Browser check, Pest, build, PROGRESS.md, commit
