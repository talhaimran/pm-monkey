# CLAUDE.md

SPEC.md is the source of truth. PROGRESS.md logs slice progress and decisions made on ambiguous points. README.md covers setup and connecting MCP clients; AGENTS.md is the guide for agents using the app. Update AGENTS.md's tool table when tools change.

UI follows docs/design-system (start with README.md); spec 5.1 wins on conflicts.

## Working style (always on in this project)
- **superpowers**: check for a matching skill before acting. Use `superpowers:writing-plans` before each slice, `superpowers:test-driven-development` while building, `superpowers:systematic-debugging` on failures, and `superpowers:verification-before-completion` before every commit.
- **ponytail** (full): make the smallest complete change and add no unrequested abstractions or deps. Mark known limits as `shortcut: <limit>, <when to upgrade>`. Never cut validation, security, data-loss handling, or anything in SPEC.md.
- When they conflict, SPEC.md wins, then ponytail on code size, then superpowers on process.

## Stack
- Laravel 12+, PHP 8.3+, MySQL 8
- Inertia.js + React + TypeScript + Vite + Tailwind (Breeze, React/TS)
- Sanctum personal access tokens (abilities) for API and agent auth
- laravel/mcp: local stdio server (Artisan) + web HTTP server (Sanctum bearer)
- dedoc/scramble: OpenAPI at /docs/api and /docs/api.json
- dnd-kit (drag and drop), Pest (tests)
- No other heavy dependencies without asking the user
- Core is AGPL-3.0; never add code or dependencies with incompatible licences; ask first.

## Architecture rules
- Business logic only in `app/Services` / `app/Actions`. Inertia controllers, API controllers (`/api/v1`), and MCP tools are thin and call the same services. Never duplicate logic.
- Form Requests for validation, Policies for authorization, API Resources for JSON.
- PHP backed enums for issue type, priority, project role.
- DB transactions for multi-step writes; index foreign keys and filter columns.
- `Model::preventLazyLoading()` outside production; no N+1.
- Issues addressable by key (`WEB-12`) everywhere; accept names (assignee email, column name) where sensible; errors list valid options.
- Destructive API/MCP actions need `confirm: true`. Viewers and read-only tokens never write.
- Not-found lookups throw `ValidationException::withMessages([...])->status(404)` listing valid options, so API and MCP report the same message.
- MCP tools (`app/Mcp/Tools`, registered in `App\Mcp\Servers\PmServer`) use snake_case `#[Name]` and reuse Form Request `rules()`. Output goes through the `RespondsWithJson` trait (summary + JSON). Use `auth()->user()` rather than `$request->user()` in shared resources (MCP has no HTTP request user).
- Resources passed to Inertia must `->resolve()` nested collections; Inertia wraps nested resources in `{data: …}`.
- Issue input shape is the same for web, API and MCP: `status` column name, `assignee` email, `labels` names, `epic`/`parent` keys. `IssueService::resolveInput` maps them to ids.
- Activity `via` comes from `ActivityLogger`: the container binding `pm.via` (set by `PmServer::createContext`) means mcp, a PersonalAccessToken means api, otherwise web.
- Ordering uses `issues.rank` through `RankService::place()` (needs the project row locked in a transaction). Don't add other position columns.
- Never query `$user->projects()` for access: use `$user->accessibleProjects()` (applies the workspace and API token project scope).
- In tests, switching tokens needs `flushHeaders()` + `app('auth')->forgetGuards()`; headers and the Sanctum user persist within a test.
- MySQL JSON columns reorder keys; compare JSON arrays in tests with `toEqual`, not `toBe`.

## Workspaces (step 0)
Spec section 5 lists the conventions for all new code; these are how step 0 built them.
- Web routes live under `/{workspace}` (the `workspace` middleware, `ResolveWorkspace`, then `require-2fa`); `URL::defaults` fills `{workspace}`, so `route('projects.index')` needs no argument; tests use `w('/projects/WEB')` for paths. API and `/mcp` take the workspace from the token (`ResolveTokenWorkspace`). Only auth, profile, invitations and the workspace list sit outside the prefix.
- Read the current workspace from `CurrentWorkspace` only. New top-level tables get `workspace_id`; project children reach it through the project.
- `$user->accessibleProjects()` also applies the workspace: Owners and Admins see every project read-only (`viewAllProjects`) and need to join to write (`ProjectPolicy`).
- Workspace roles: `WorkspacePolicy` abilities. Role changes and removals go through `WorkspaceMemberService`, which locks the workspace row so the last Owner can't be lost.
- Plan limits: `Entitlements::ensure($workspace, Entitlement::X, $adding)` before anything that adds to a counted thing; it throws `PlanLimitReached` (API `plan_limit` 403, web dialog). People are counted by `WorkspaceUsage`, pending invitations included. Gate Cloud Free by entitlements, never by edition.
- Admin and security events go to `AuditLogger` (append-only `audit_logs`, workspace audit log); issue changes go to `ActivityLogger`. Neither stores secrets, tokens or codes.
- Test helpers in `tests/Pest.php`: `makeWorkspace`, `useWorkspace`, `asWorkspaceMember`, `joinWorkspace`, `makeProject`, `apiAs`, `w()`, `otp()`, `enableTwoFactor()`. Every new route, endpoint or MCP tool gets a case in `tests/Feature/Security/WorkspaceIsolationTest.php`.

## UI rules (step U)
- Tokens: `tailwind.config.js` reads `docs/design-system/tokens.json`; use the token classes (`bg-card`, `text-muted`, `tint-amber`, `rounded-ctl`, `h-control-h`…). No hex colours, no default Tailwind colours, no `/40`-style opacity on token colours (they are CSS variables). `tests/Feature/Ui/DesignTokensTest.php` enforces this.
- Motion: timings only from `resources/js/motion.ts` (`transition('drawerIn')`), never number literals in components. Reduced motion is handled there and in `app.css`.
- Icons: `lucide-react` only. Dialogs: `Modal` / `WarningConfirm` (Headless UI). Empty lists: `EmptyState`.
- Undo: a reversible web change flashes `undo` (`UndoService`), the `Toaster` sends it back with `undo_of`. Web-only; API/MCP input shapes don't carry it.
- Deletes are soft (`deleted_at` on issues and comments). `IssueService::delete($issue, $actor)` / `restore()`; project deletion is the only hard delete besides `issues:purge`.
- Issue URLs come from `resources/js/issueUrl.ts`; a page that can peek passes `'peek' => $this->peek($request)` (`PresentsIssue`) and renders `<IssueDrawer />`.
- Frontend unit tests: `npm test` (Node's built-in runner, `resources/js/**/*.test.ts`).

## Commands
- Dev servers: `composer run dev` (runs `php artisan dev`: server + queue + vite). Browser preview: `.claude/launch.json` entries `dev` (vite :5173) and `app` (artisan serve :8000).
- Demo data: `php artisan migrate:fresh --seed` (DemoSeeder) creates workspace Demo (`/demo`) with demo@example.com / password as Owner, project WEB (~29 issues, active sprint) and prints a demo API token. Re-running without fresh skips.
- Tests: `php artisan test` (Pest), one file: `php artisan test tests/Feature/XTest.php`. Tests run on MySQL db `pm_monkey_test` (phpunit.xml), not SQLite, because row locks matter.
- Lint: `vendor/bin/pint` (PHP); `npm run build` runs `tsc` as the TS check
- Build frontend: `npm run build`
- Migrate/seed: `php artisan migrate`, `php artisan migrate:fresh --seed`
- MCP: HTTP `POST /mcp` (Sanctum bearer, `throttle:api`), stdio `PM_MCP_TOKEN=… php artisan mcp:start pm` (routes/ai.php). New tools must be added to `PmServer::$tools`.
- Real-process tests live in `tests/Integration` (DatabaseTruncation, not RefreshDatabase, so spawned processes see committed rows).

## Per-slice checklist
migrate → Pest suite green → `npm run build` → security review of the diff → update PROGRESS.md → git commit.

## Security review (every slice/step)
Run the claude-security plugin's "scan changes" on the diff. If it's unavailable, review the diff yourself for: authz/Policy gaps, API token scope and read-only bypasses, cross-project data leaks, mass assignment, XSS (rendered Markdown, dangerouslySetInnerHTML, user-supplied names/labels/comments), CSRF, injection, and secrets in code or logs. Prove each real finding with a failing test first, fix it, keep the test in tests/Feature/Security/, and log it in PROGRESS.md. Never commit with an open high or critical finding.
