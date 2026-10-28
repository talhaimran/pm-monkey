# Progress

## Slice 1: Scaffold ✅
- Laravel 13.39 (spec says 12+), PHP 8.5, Homebrew MySQL. DBs: `pm_monkey` and `pm_monkey_test`; user `pm_monkey`/`pm_monkey` (local dev only).
- Breeze 2.4 React/TS with dark mode and Pest. Breeze lags Laravel 13, so these fixes were needed:
  - Bumped `@vitejs/plugin-react` ^4 → ^6 and `@types/node` ^18 → ^22.12 for Vite 8.
  - Removed the unused `@tailwindcss/vite`; Breeze uses Tailwind 3 via PostCSS.
  - Removed the `./bootstrap` import and the `window.axios` typing; Laravel 13 no longer ships `bootstrap.js` and nothing used it.
- Discarded Laravel 13's generic Boost AGENTS.md and CLAUDE.md; ours replace them.
- Sanctum (`install:api`), laravel/mcp ^1.1, dedoc/scramble ^0.13, dnd-kit installed.
- Decision: API docs (/docs/api, /docs/api.json) are public via the `viewApiDocs` gate so agents can discover the API without a token.
- Decision: the nested empty git repo `pm-monkey/` is gitignored and left untouched.
- `Model::preventLazyLoading()` is on outside production.

## Slice 2: Projects and roles ✅
- Projects (KEY `^[A-Z][A-Z0-9]{1,9}$`, uppercased, fixed after creation), `project_user` pivot with `ProjectRole` enum (admin/member/viewer), `project_invitations`.
- `ProjectService` and `MembershipService` hold all logic; web, `/api/v1` and MCP call them. Validation rules live in Form Requests; MCP tools reuse their `rules()`/`messages()`.
- `ProjectPolicy` denials carry readable reasons (role, or a read-only token). `User::canWrite()` is true for session auth, or for a token with the `write` ability.
- Lookups by key and by member email throw a 404 ValidationException that lists the valid options. API and MCP show the same message. Non-members get "not found", so project keys don't leak.
- The last admin can't be demoted or removed; the check locks the admin rows.
- Decision: invitations are accepted from /projects by the logged-in user whose email matches; there's no token link. Risk: email verification isn't enforced, so someone who registers with an invited address can accept. Revisit if the app is exposed publicly.
- Decision: MCP tools are added in each slice; slice 8 adds transports, auth and end-to-end calls. Slice 2 tools: list_projects, get_project, create_project, update_project, delete_project. Membership changes are web/API only.
- Gotcha: Inertia wraps nested JsonResources in `{data: …}`, so resources resolve nested collections to arrays. Caught in the browser; now pinned by a test.
- Only admins manage members; there's no self-service "leave project".

## Slice 3: Issues, comments, labels, sub-tasks, activity ✅
- Issues are numbered per project with `projects.issue_counter` under a row lock, plus unique(project_id, number). Checked with 20 parallel processes: numbers 1–20, no duplicates or gaps. Numbers are never reused.
- Status = board column. A `columns` table is seeded with To Do / In Progress / In Review / Done per project (the migration backfills existing projects). Column CRUD and the board come in slice 4.
- One input shape everywhere: status = column name, assignee = member email, labels = label names (must exist), epic/parent = issue keys. Unknown values return errors listing the valid options.
- Decision: two link fields. `epic_id` must point to an Epic; `parent_id` makes a sub-task, one level deep. Epics can't be sub-tasks or parents. Deleting a parent deletes its sub-tasks; deleting an epic unlinks its issues.
- Activity log: created/updated/commented, with `changes` as {field: {from, to}} in display values (descriptions truncated to 200 chars) and `via` web|api|mcp plus `token_name`. MCP is detected by `PmServer::createContext()`. No-op updates aren't logged.
- Permissions: members and admins create, edit and delete issues and labels. Comments are edited by their author and deleted by the author or an admin.
- Markdown is rendered server-side (`Str::markdown`, HTML stripped, unsafe links blocked) for web only. API and MCP return raw Markdown. Styled without @tailwindcss/typography, to avoid a dependency.
- MCP: create/get/update/delete_issue, get_issue_activity, add/update/delete_comment, list/create/update/delete_label. Resources: `issue://{key}`, `project://{key}` (statuses, labels, members).
- `ProjectService::delete` deletes issues first, because issues restrict column deletes.
- Deferred to slice 6: issue listing and search. For now the project page shows the 50 most recent issues.

## Slice 4: Board and columns ✅
- The project page (`/projects/{KEY}`) is now the Kanban board. dnd-kit drag works between and within columns (pointer and keyboard sensors) with optimistic UI; server data wins after each reload.
- One project-wide `issues.rank` (bigint, gaps of 1024) orders the board and will order the backlog in slice 5. A move takes the neighbours' midpoint, and the project renumbers when there's no gap left. All of this runs under the project row lock.
- Move input: `status` plus `position` (0-based index) or `after` (issue key). The web UI sends `after`, so reordering stays correct while filters hide cards. Status changes are logged (through IssueService::update); pure reorders aren't.
- Board filters run server-side through `IssueService::filter()`: assignee (email or "unassigned"), type, priority, label, q (title or key). The list view in slice 6 will reuse it. The board hides epics and caps at 500 issues, with a `truncated` flag.
- Columns: admins add, rename, reorder and delete them (API/MCP address columns by name). Deleting a column with issues needs `move_issues_to`. A project keeps at least one column. Moving issues out of a deleted column isn't recorded in the activity log.
- MCP: get_board, move_issue, list_columns, create_column, update_column, delete_column.
- Verified in the browser: dragged across columns (status updated and logged via web), reordered within a column (rank only, nothing logged), and the type filter.

## Slice 5: Backlog and sprints ✅
- Sprints: planned → active → completed, with a name (unique per project; "backlog" and "active" are reserved), goal and dates. Default names are "{KEY} Sprint N". Starting defaults to today through +14 days.
- One active sprint per project: a service check under the project row lock, plus a unique nullable `active_project_id` set only while active. A stored generated column would be neater, but MySQL forbids cascading FKs on its base columns (error 1215), so the service sets the column.
- "Done" = the last board column (`Project::doneColumnId()`). Completing needs `confirm: true` and `move_open_issues_to` ("backlog" or a planned sprint). Done issues stay with the sprint for history. Each moved issue gets an activity entry.
- `sprint` is now an issue field (name or id, null for the backlog) in web, API and MCP, and is logged in diffs. Completed sprints can't receive issues or be edited. Only planned sprints can be deleted; their issues return to the backlog.
- Backlog: open sprints (active first) plus unscheduled issues, ranked by the slice 4 `rank`. Epics are hidden, and done issues are hidden from the backlog section. Drag-to-rank works within and across sections (`POST /issues/{key}/rank` with `sprint`, `position` or `after`).
- Board and issue filters gain `sprint` (a name, "active" or "backlog"). The issue page has a Sprint picker.
- Members and admins manage sprints.
- Refactor: board and backlog share `useSortableLanes` (dnd-kit multi-lane logic), and `RankService::positionAfter` serves both.
- MCP: list_sprints, create_sprint, update_sprint, delete_sprint, start_sprint, complete_sprint, get_backlog, rank_backlog.
- Verified in the browser: created a sprint, dragged an issue in (logged via web), started it, then completed it with the unfinished issue returning to the backlog.

## Slice 6: List view, search, dashboard ✅
- List view at `/projects/{KEY}/issues`: paginated (25 per page, max 100), sortable headers (key, title, type, priority, status, assignee, points, due, created, updated; `-` = descending, default `-key`), and the same filters as the board plus sprint. Epics are included. Unknown sorts return 422 listing the valid ones.
- One query path: `IssueService::list()` (filter + sort + paginate) serves the web list, web search, `GET /api/v1/projects/{key}/issues`, `GET /api/v1/issues` (cross-project search) and MCP `search_issues`. `filter()` no longer needs a project: a key in `q` matches by project key and number.
- Search: the header box (focused with `/`) goes to `/search?q=`. An exact key the user can see redirects to that issue.
- Dashboard: "My issues" (open, assigned to me, by priority then due date) and a card per project with status bars, open/overdue counts and active sprint progress. `DashboardService::summaries()` uses about 6 queries in total, however many projects there are.
- API: `GET /me/issues`, `GET /projects/{key}/summary`. MCP: `search_issues`, `my_issues`, `project_summary`.
- Keyboard: `/` focuses search (on narrow screens it opens the menu first), `c` opens the create dialog on the board, backlog and list (through a `pm:create-issue` window event), and Esc closes dialogs. Shortcuts are ignored while typing.
- Light/dark: Tailwind `darkMode: 'class'`. An inline head script applies the saved choice before paint. The header toggle cycles light → dark → system, and system follows the OS live.
- Refactor: shared `IssueFilters`, `ProjectNav` (Board/Backlog/Issues/Settings tabs), `IssueTable`, `Pagination`, `useCreateShortcut`.
- Verified in the browser: dashboard, `c` and Esc on the board, `/` search jumping to WEB-3, priority sort, the theme toggle persisting light mode across a reload, and the header layout at 1100px.

## Slice 7: Tokens UI, idempotency, rate limiting, bulk ✅
- `/settings/tokens` (user menu → API tokens): create a token (name, read-only or read-write, all projects or chosen ones), see access, scope, last used and created, and revoke it. The plain token is shown once via a session flash. Tokens are managed on the web only, so a token can't mint tokens.
- Scope lives in Sanctum abilities, with no migration: `read`, `write`, `project:{id}`. No `project:` entries means all of the user's projects, including ones they join later. `User::accessibleProjects()` applies the scope to every lookup (project resolve/list, cross-project search, dashboard, comment by id). Out-of-scope projects return the same 404 as non-membership, and scoped tokens can't create projects.
- Idempotency-Key: the `Idempotent` middleware on all `/api/v1` POSTs, keyed per token (or user) + path + key. Only successful responses are stored (24h, `PM_IDEMPOTENCY_TTL_HOURS`). A replay returns `Idempotent-Replayed: true`. The same key with a different body gets 422; a concurrent duplicate gets 409 (cache lock).
- Rate limit: the `api` limiter, `PM_API_RATE_LIMIT` per minute (default 120), keyed by token → user → IP. The 429 says the limit and when to retry. The MCP web route gets it in slice 8.
- Bulk: `POST /projects/{key}/issues/bulk` and `PATCH /issues/bulk` (by key), max 50, all-or-nothing. Errors are keyed and prefixed `issues.{i}` / `updates.{i}`. MCP: `bulk_create_issues`, `bulk_update_issues`.
- Decision: MCP create tools don't take an idempotency key. The HTTP MCP endpoint is one JSON-RPC POST per call, so a per-call key would need a tool argument. Agents needing replay safety can use the REST API.
- Test gotcha: `withHeader()`/`withToken()` persist across requests in one test, and Sanctum caches the guard user. Switch tokens with `flushHeaders()` + `app('auth')->forgetGuards()` (see `asToken()` in ApiGuardsTest).
- Verified in the browser: created a WEB-only read-only token, called the real API with it (session cookie omitted: only WEB listed, write → 403 "read-only"), saw "Last used" update, and revoked it.

## Slice 8: MCP servers ✅
- Read vendor/laravel/mcp v1.1 before writing code (routes/ai.php auto-load, `Mcp::web`/`Mcp::local`, stateless HTTP transport, newline-delimited stdio, `boot()` hook, header validation, paginated tools/list). Findings are in the slice 8 plan.
- Web: `POST /mcp` with `auth:sanctum` + `throttle:api`. Tokens, project scope, read-only and the rate limit match the REST API, and a 401 includes `WWW-Authenticate: Bearer realm="mcp"`.
- Local: `php artisan mcp:start pm` runs `LocalPmServer`, which logs in during `boot()` as the owner of `PM_MCP_TOKEN`, so the token's limits apply on stdio too. A missing or invalid token stops start-up with a message telling you how to fix it.
- `tools/list` returns all 36 tools in one page (page size 100), because some clients ignore cursors.
- Tests call the real transports. `tests/Feature/Mcp/McpHttpTest.php` sends JSON-RPC through the HTTP kernel: 401, initialize, tools/list, writes logged via mcp with the token name, read-only and scope errors, resources, rate limit. `tests/Integration/McpStdioTest.php` spawns `php artisan mcp:start pm` against the test DB (new Integration suite, DatabaseTruncation).
- Manually checked against the dev app: curl to `/mcp` (36 tools, project_summary on WEB, 401 without a token) and a stdio run (initialize + my_issues) with a temporary token that was then revoked.
