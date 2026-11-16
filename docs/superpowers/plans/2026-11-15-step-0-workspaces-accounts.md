# Step 0: Workspaces and Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. One pull request per sub-step; each is merged before the next starts. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Put every project inside a workspace with its own roles, settings, limits and audit log, and add 2FA and Google/GitHub sign-in, without changing what existing installs see beyond the URL prefix and the switcher.

**Architecture:** A `CurrentWorkspace` service holds the workspace for the request: from the URL slug on the web, from the token on API and MCP. `User::accessibleProjects()` adds `projects.workspace_id = current`, so every existing child lookup (issue, comment, label, column, sprint) is scoped without touching it. Web routes move under `/{workspace}`; `URL::defaults` supplies the slug so existing `route()` calls in PHP and Ziggy keep working. New rules (roles, limits, audit) live in services called by web, API and MCP alike.

**Tech Stack:** Laravel 13, Pest on MySQL, Inertia + React 18 + TypeScript, the step U design system. New, approved by the spec: `laravel/fortify` (2FA only), `laravel/socialite`. `composer require --dry-run` resolved both on Laravel 13.39 (fortify v1.42.0, socialite v5.32.0).

**Spec:** `docs/specs/pmops-spec.md` sections 3, 4, 5, 5.1 and 7.0. Design system: `docs/design-system/` (SidebarNav, AuditLogTable, PlanLimitDialog, WarningConfirm, EmptyState).

## Global Constraints

- CLAUDE.md is in force: logic in `app/Services`, thin controllers and MCP tools, Form Requests, Policies, Pest on MySQL, per-slice checklist.
- Dependencies: only `laravel/fortify` and `laravel/socialite`. Do not hand-roll TOTP or OAuth. Check the licence of every package they pull in (AGPL-3.0 compatibility) at install and stop if one is not compatible.
- `workspace_id` on top-level tables only: `projects`, `personal_access_tokens`, `workspace_invitations`, `audit_logs`, `workspace_entitlements`. Child tables reach the workspace through their project.
- Get the current workspace from `CurrentWorkspace` only. Never query `$user->projects()` for access.
- `/api/v1` paths do not change. API and MCP input shapes do not change.
- No entitlement row = unlimited. Gates use entitlements, never `if edition == cloud`.
- Admin and security events go to `AuditLogger`; issue changes stay in `ActivityLogger`. Neither ever records a password, token, secret or recovery code.
- Agents and tokens never get settings tools: workspace settings, members, roles, tokens and security stay web-only.
- Every new screen follows spec 5.1: design tokens, light and dark, empty states, 32px targets, sentence case.
- Internal identifiers stay: `pm_monkey` databases, `pm` MCP handle, `PM_*` env vars, `pm.via`.

## Decisions (confirmed 2026-11-15)

| # | Topic | Decision |
|---|---|---|
| D1 | Delivery | One PR per sub-step, merged before the next starts. A CI PR comes first. |
| D2 | Workspace deletion (spec open question 3, leaning) | 7-day undo: `workspaces.deletion_scheduled_at`; members are locked out, the Owner can undo; `workspaces:purge` (daily) deletes for good after 7 days. |
| D3 | Workspace URL (spec open question 1) | Path prefix `/{slug}`. |
| D4 | Logo | No upload in step 0. `logo_path` column exists; the UI shows the workspace's initial in a tinted square. Upload arrives with attachments (step 6). |
| D5 | CI | GitHub Actions on every pull request: Pest (MySQL 8), `npm test`, `npm run build`, `vendor/bin/pint --test`. |
| D6 | Cloud default limits | When `PM_EDITION=cloud`, a new workspace gets the Free values of section 4 as `workspace_entitlements` rows with source `default`. Self-hosted gets no rows (unlimited). |
| D7 | Security review | The claude-security plugin scan runs on sub-step 1 and on the whole step in sub-step 8. Every sub-step also gets a manual review with probe tests in `tests/Feature/Security`. |
| D8 | Google/GitHub verification | Pest tests with Socialite's fake driver only. The real round trip is first tried by the owner, from README setup steps. |
| D9 | Last active | `workspace_user.last_active_at`, written at most once every 5 minutes per member (web request in the workspace, or token use). Not in the spec's table list. |
| D10 | Account pages | API tokens at `/{workspace}/settings/tokens` (that workspace's tokens only). Profile, password, 2FA and linked sign-ins stay at `/profile`. |
| D11 | Removed user | Keeps their login. With no workspace they see a "no access" page: "You are no longer a member of {workspace}. Ask an admin to invite you again." plus Log out and Profile; on Cloud also "Create workspace". |

## Defaults I chose (say if you want any changed)

| # | Topic | Default |
|---|---|---|
| E1 | Reserved slugs | A slug may not be: `login`, `logout`, `register`, `forgot-password`, `reset-password`, `verify-email`, `confirm-password`, `two-factor-challenge`, `auth`, `profile`, `dashboard`, `projects`, `issues`, `comments`, `search`, `settings`, `invitations`, `workspaces`, `api`, `mcp`, `docs`, `internal`, `up`, `build`, `storage`, `admin`, `new`. |
| E2 | Slug of a migrated install | `Str::slug(APP_NAME)`, falling back to `workspace`; padded or suffixed to fit 3–40 characters and avoid reserved words. |
| E3 | Old URLs | Every current web URL (`/dashboard`, `/projects/…`, `/issues/…`, `/search`, `/settings/tokens`) stays as a redirect to the same path under the user's current workspace, query string kept. |
| E4 | Same issue key in two workspaces | `WEB-12` resolves only inside the current workspace. Search and the dashboard never cross workspaces. |
| E5 | Tokens when a member is removed | Deleted with the membership (spec 7.0b). A token whose owner has left returns 401 and is deleted on first use (spec 7.0a). |
| E6 | stdio MCP | `PM_MCP_TOKEN` works as today; the workspace comes from that token. |
| E7 | Deleting your account | Blocked while you are the last Owner of any workspace, with the list of those workspaces. |
| E8 | Purge logging | `issue.purged` moves from the application log to `AuditLogger` in sub-step 4 (closes a step U shortcut). The log line no longer carries the issue title. |
| E9 | Audit log CSV | Exports the current filter, newest first, capped at 10,000 rows with a note when cut off. |
| E10 | Existing test suite | In sub-step 2 the tests' web URLs gain the prefix through one helper, `w('/projects/WEB')`. Assertions are not changed. "Existing suite passes" is read that way. |
| E11 | Rate limit counters | Per-day counts use the cache (`pm:api-day:{token}:{Y-m-d}`), like the per-minute limiter. |
| E12 | 2FA scope | Fortify's two-factor feature only; its routes for login, registration and password reset stay off (Breeze keeps those). Passkeys (pulled in by Fortify as a package) are not enabled. |

## Items marked (leaning) in the spec for this step

Only one: the 7-day "scheduled for deletion" period (7.0b). Confirmed as D2.

## Schema changes

New tables: `workspaces` (+ `deletion_scheduled_at`), `workspace_user` (+ `last_active_at`), `workspace_invitations`, `workspace_slug_redirects`, `workspace_entitlements`, `audit_logs`, `social_accounts`. Fortify's migration adds `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` to `users`.

Changed tables: `projects` (+ `workspace_id`; unique `key` becomes unique (`workspace_id`, `key`)), `personal_access_tokens` (+ `workspace_id`), `users` (+ `current_workspace_id`). `project_invitations` rows move to `workspace_invitations` and the table is dropped.

Columns beyond the spec's lists: `workspaces.deletion_scheduled_at` (D2), `workspace_user.last_active_at` (D9).

## Sub-steps, branches and pull requests

| PR | Branch | Contents | Spec |
|---|---|---|---|
| 0 | `chore/ci` | GitHub Actions workflow | D5 |
| 1 | `step-0/1-workspace-data` | Tables, migration of existing installs, `CurrentWorkspace`, scoped access, token binding, isolation tests, plugin scan | 7.0a |
| 2 | `step-0/2-urls-and-joining` | URL prefix, redirects, switcher, sign-up, invitations, self-hosted mode, no-access page | 7.0a |
| 3 | `step-0/3-roles-and-settings` | `WorkspacePolicy`, General and Members pages, remove member, join as Admin, deletion with 7-day undo | 7.0b |
| 4 | `step-0/4-audit-log` | `audit_logs`, `AuditLogger`, every step 0 event, audit log page, CSV | 7.0d |
| 5 | `step-0/5-entitlements` | `workspace_entitlements`, `Entitlement` enum, `WorkspaceUsage`, enforcement, plan-limit dialog and error, rate limits, Usage page, `pm:entitlements` | 7.0c |
| 6 | `step-0/6-two-factor` | Fortify 2FA, challenge, workspace enforcement, token rule | 7.0e |
| 7 | `step-0/7-social-sign-in` | Socialite, `social_accounts`, linking rules, sign-in methods setting | 7.0f |
| 8 | `step-0/8-docs-and-review` | README, AGENTS.md, CLAUDE.md, seeder, acceptance walk-through, plugin scan of the whole step | 7.0 |

Each PR: failing tests first, `php artisan test`, `npm test`, `npm run build`, `vendor/bin/pint --test`, security review, one PROGRESS.md line, browser check of new screens in light and dark and at 375px.

## Acceptance map (spec 7.0)

| Acceptance line | Proven in |
|---|---|
| Existing installs migrate with no change beyond the URL prefix and switcher; existing suite passes | PR 1 `WorkspaceMigrationTest` (demo seed before/after), PR 2 (suite with `w()` helper) |
| Isolation test covers every route, endpoint and tool that takes an id or key | PR 1 `Security/WorkspaceIsolationTest`, extended in every later PR that adds a route |
| Self-hosted allows one workspace | PR 2 `EditionTest` |
| Cloud users create, join and switch without data crossing | PR 2 `WorkspaceSwitchTest`, isolation test |
| Role rules including last Owner | PR 3 `WorkspaceRolesTest` |
| `billed_users=2`, `viewers=3`, `projects=1` enforced on web, API and MCP, including pending invitations | PR 5 `EntitlementEnforcementTest` |
| Per-token minute and day limits | PR 5 `RateLimitTest` |
| Every audit event recorded once, no secrets | PR 4 `AuditLogTest` (one test per event; a sweep asserting no secret-looking value) |
| 2FA and enforcement for password and social sign-in | PR 6 `TwoFactorTest`, PR 7 `SocialSignInTest` |
| Google and GitHub link only on verified emails | PR 7 `SocialSignInTest` |
| New screens follow 5.1 | Browser checks per PR; `DesignTokensTest` |
| Build, Pint and security review clean | Every PR; plugin scan in PRs 1 and 8 |

## Review Focus

1. **A member of workspaces A and B, removed from A while `current_workspace_id` still points at A:** the next request lands in B (or the no-access page), never in A, and their A tokens are gone. Test in PR 3.
2. **Two Owners demoting or removing each other at the same moment:** one request wins, the other fails with "a workspace needs at least one Owner". The check and the write happen under a lock on the workspace row. Test in PR 3.
3. **An invitation accepted late:** after it expired, after it was revoked, by a signed-in user with a different email, or when accepting would now exceed a limit. Each is refused with a message that says why; no membership is created. Tests in PR 2 and PR 5.
4. **A slug that collides:** with a reserved word, with another workspace, or with another workspace's old redirect slug; and a rename back to your own old slug. Tests in PR 2 and PR 3.
5. **Sign-in that skips a rule:** a social sign-in on an account with 2FA must still hit the challenge; a workspace that disallows a method must refuse entry to a session created with it; a recovery code works once. Tests in PR 6 and PR 7.

---

### PR 0: CI

**Files:** Create `.github/workflows/ci.yml`.

- Triggers: `pull_request`, and `push` to `main`. `permissions: contents: read`.
- One job on `ubuntu-latest`: MySQL 8 service (database `pm_monkey_test`, the user and password from `.env.example`); PHP matching `composer.json` with `pdo_mysql`; Node from the lockfile's engine or the current LTS; `composer install`, `npm ci`, `cp .env.example .env`, `php artisan key:generate`, then the four checks.
- Third-party actions pinned to commit SHAs, licences noted in a comment.

- [ ] Write the workflow; open the PR; the run on the PR itself must be green. If a check fails on GitHub but passes locally, fix the workflow, not the test.

### PR 1: Workspace data, scoping and isolation

**Files:**
- Create: `app/Models/{Workspace,WorkspaceInvitation}.php`, `app/Enums/WorkspaceRole.php`, `app/Services/{CurrentWorkspace,WorkspaceService}.php`, `app/Http/Middleware/ResolveTokenWorkspace.php`, migrations `2026_11_16_00000{1..4}_*`, `tests/Feature/Workspaces/WorkspaceMigrationTest.php`, `tests/Feature/Security/WorkspaceIsolationTest.php`
- Modify: `app/Models/{User,Project}.php`, `app/Services/{ProjectService,TokenService,MembershipService}.php`, `app/Mcp/Servers/{PmServer,LocalPmServer}.php`, `bootstrap/app.php`, `routes/api.php`, `config/pm.php`, `tests/Pest.php`, `database/seeders/DemoSeeder.php`

**Interfaces — produces:**
```php
enum WorkspaceRole: string { case Owner = 'owner'; case Admin = 'admin'; case Member = 'member'; }

final class CurrentWorkspace            // scoped singleton
{
    public function set(Workspace $workspace): void;
    public function get(): Workspace;    // throws LogicException when none is set
    public function id(): ?int;
    public function forget(): void;
}

WorkspaceService::create(User $owner, string $name, ?string $slug = null): Workspace   // owner becomes Owner; validates slug rules and E1
WorkspaceService::slugFor(string $name): string                                         // suggestion, unique and not reserved
User::workspaces(): BelongsToMany                                                       // pivot role, last_active_at
User::workspaceRole(Workspace $workspace): ?WorkspaceRole
User::accessibleProjects(): BelongsToMany   // now also where projects.workspace_id = CurrentWorkspace::id(); no current workspace => no rows
```
- In this PR the web still has no prefix: a web request uses `users.current_workspace_id` (set by a small `web` middleware, replaced by `ResolveWorkspace` in PR 2).
- `ResolveTokenWorkspace` (API and MCP HTTP): sets the workspace from the token's `workspace_id`; if the user is no longer a member, deletes the token and returns 401. `LocalPmServer::authenticate` does the same for stdio.
- `TokenService::create` takes projects only from the current workspace and stamps `workspace_id`.
- `config('pm.edition')` from `PM_EDITION` (`self_hosted` default).
- Migration of existing data, in one transaction, as spec 7.0a: one workspace from `APP_NAME`; lowest user id → Owner, any project Admin → Admin, others → Member; stamps projects, tokens, users; pending `project_invitations` → `workspace_invitations`, table dropped.
- Test helpers: `makeProject()` creates the admin's workspace on first use and sets it current; new `makeWorkspace(User $owner, string $slug)`.

- [ ] **Failing tests first.**
  - `WorkspaceMigrationTest`: build the pre-step-0 schema state with the demo seed's shape (3 users, project WEB with roles, a token scoped to WEB, a pending invitation), run the data migration, then assert: one workspace; roles Owner/Admin/Member as specified; every user's `accessibleProjects` keys, issue keys and token ids are identical before and after; the invitation survived with its project and role; `project_invitations` is gone.
  - `WorkspaceIsolationTest`: user `ann` in A and B, `bob` only in B; both workspaces have a project `WEB` with `WEB-1`, a comment, a label, a column, a sprint and a token. A dataset enumerates **every** registered web route, `/api/v1` route and MCP tool (read from the router and `PmServer::$tools`, so a new route without a case fails the test). For each: acting in A, B's objects are never returned or changed, and the error is the same as for something that does not exist. Also: A's token cannot name B's project on creation; a token of a user who left returns 401 and is deleted.
- [ ] Implement; `php artisan migrate`; full suite green.
- [ ] Plugin security scan of the PR diff (D7). Fix any real finding with a failing test first.
- [ ] Open PR 1.

### PR 2: URL prefix, switcher, sign-up and invitations

**Files:**
- Create: `app/Http/Middleware/ResolveWorkspace.php`, `app/Http/Controllers/Web/{WorkspaceController,WorkspaceInvitationController}.php`, `app/Services/InvitationService.php`, `app/Mail/WorkspaceInvitationMail.php`, `resources/js/Pages/Workspaces/{Create,NoAccess}.tsx`, `resources/js/Components/WorkspaceSwitcher.tsx`, tests `Workspaces/{UrlPrefixTest,EditionTest,WorkspaceSwitchTest,InvitationTest}.php`
- Modify: `routes/web.php`, `routes/auth.php`, `app/Http/Controllers/Auth/RegisteredUserController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/Layouts/AuthenticatedLayout.tsx`, `resources/js/Pages/Auth/Register.tsx`, `resources/js/types/index.d.ts`, `tests/Pest.php` and the web URLs in existing tests (E10)

**Interfaces — produces:**
```php
// ResolveWorkspace: slug → workspace (old slug in workspace_slug_redirects → 301 to the new one);
// not a member or unknown slug → the same 404; sets CurrentWorkspace, URL::defaults(['workspace' => slug]),
// users.current_workspace_id, and workspace_user.last_active_at (at most every 5 minutes).
InvitationService::invite(Workspace $w, User $inviter, string $email, WorkspaceRole $role, ?Project $project = null, ?ProjectRole $projectRole = null): WorkspaceInvitation
InvitationService::accept(string $token, User $user): Workspace     // joins, adds the project role, switches; refuses expired, revoked, wrong email
InvitationService::revoke(WorkspaceInvitation $invitation): void
InvitationService::resend(WorkspaceInvitation $invitation): void
```
- Routes: all current authenticated web routes move into `Route::prefix('{workspace}')->middleware(['auth', 'workspace'])` with the slug pattern `[a-z0-9-]{3,40}`; names unchanged. Old paths stay as redirect routes (E3). `/profile` stays global (D10); tokens move to `{workspace}/settings/tokens`.
- Inertia shared props: `workspace: {id, name, slug, role}`, `workspaces: [{name, slug}]`.
- Switcher in the sidebar's top slot (design system SidebarNav): current workspace, the user's others, "Create workspace" when `pm.edition` is `cloud`, "Workspace settings" for Owners and Admins (links land in PR 3).
- Register: on Cloud asks "Workspace name" with a suggested, editable slug; on self-hosted the first user creates the workspace, afterwards registration needs a valid invitation token, else "Ask an admin of {workspace} to invite you."
- Invitations: 14 days, hashed token, existing invite rate limit; project Admins can still invite to their project (a Member invitation with that project).
- `NoAccess` page (D11).

- [ ] **Failing tests first:** `UrlPrefixTest` (every old URL redirects with its query string; reserved and malformed slugs 404; non-member 404 identical to unknown slug; old slug redirects), `EditionTest` (self-hosted: second workspace refused, register without invitation refused after the first user; cloud: allowed), `WorkspaceSwitchTest` (switching changes what `/…/projects` lists; `current_workspace_id` follows), `InvitationTest` (Review Focus 3 cases; accept while signed in joins and switches; expiry at 14 days; rate limit kept).
- [ ] Implement; update existing tests' web URLs through `w()`; extend `WorkspaceIsolationTest` with the new routes.
- [ ] Browser: switcher, register (both editions via config), invitation accept, no-access page; light, dark, 375px.
- [ ] Open PR 2.

### PR 3: Roles, policies, General and Members

**Files:**
- Create: `app/Policies/WorkspacePolicy.php`, `app/Services/WorkspaceMemberService.php`, `app/Console/Commands/PurgeDeletedWorkspaces.php`, `app/Http/Controllers/Web/WorkspaceSettingsController.php`, `app/Http/Requests/Workspaces/*`, `resources/js/Pages/Workspaces/Settings/{General,Members}.tsx`, tests `Workspaces/{WorkspaceRolesTest,WorkspaceSettingsTest,WorkspaceDeletionTest}.php`
- Modify: `app/Policies/ProjectPolicy.php`, `app/Services/{WorkspaceService,ProjectService,MembershipService}.php`, `app/Http/Controllers/ProfileController.php`, `routes/web.php`, `routes/console.php`

**Interfaces — produces:**
```php
// WorkspacePolicy abilities: update (rename, slug), manageMembers, manageOwners, manageSecurity, viewAuditLog,
// viewAllProjects, manageProjectCreation, createProject, viewUsage, openBilling, delete.
WorkspaceMemberService::changeRole(Workspace $w, User $actor, User $member, WorkspaceRole $role): void   // last-Owner rule, Admins can't touch Owners
WorkspaceMemberService::remove(Workspace $w, User $actor, User $member, array $newAdmins = []): void     // leaves all projects, deletes their tokens there; $newAdmins: project key => email where they were the only Admin
WorkspaceMemberService::joinProjectAsAdmin(Project $project, User $actor): void
WorkspaceService::rename(Workspace $w, string $name, string $slug): Workspace                            // old slug kept as a redirect unless claimed
WorkspaceService::scheduleDeletion(Workspace $w, User $owner, string $typedSlug): void                   // sets deletion_scheduled_at = now + 7 days
WorkspaceService::cancelDeletion(Workspace $w, User $owner): void
```
- Role changes and removals run in a transaction that locks the workspace row (Review Focus 2).
- Owners and Admins list every project; those they are not in show "Join as Admin"; until they join they can view only.
- `ProjectPolicy::create` respects `settings.members_can_create_projects`.
- A workspace scheduled for deletion: members get the no-access page saying when it will be deleted; the Owner sees "Undo". `workspaces:purge` runs daily and deletes workspaces past their date (projects, issues, tokens, invitations).
- Pages under `/{workspace}/settings/`: General (name, slug, initial-letter mark, who can create projects, delete with typed slug and an export reminder), Members (role, billed or Viewer, projects, last active; invite, change role, remove; pending invitations with resend and revoke). The 2FA column arrives in PR 6.
- Account deletion blocked for a last Owner (E7).

- [ ] **Failing tests first:** `WorkspaceRolesTest` (the whole abilities table of section 4 that step 0 covers, per role; last Owner cannot leave, be removed or demoted; Admin cannot change an Owner; the concurrent case of Review Focus 2), `WorkspaceSettingsTest` (rename and slug rules, Review Focus 4; project-creation setting on web, API and MCP), `WorkspaceDeletionTest` (wrong slug refused; scheduled; members locked out; undo; purge after 7 days and not before), Review Focus 1.
- [ ] Implement; extend the isolation test; browser checks.
- [ ] Open PR 3.

### PR 4: Audit log

**Files:**
- Create: migration for `audit_logs`, `app/Models/AuditLog.php`, `app/Services/AuditLogger.php`, `app/Http/Controllers/Web/AuditLogController.php`, `resources/js/Pages/Workspaces/Settings/AuditLog.tsx`, `tests/Feature/Workspaces/AuditLogTest.php`
- Modify: the services and auth controllers that raise each event; `app/Console/Commands/PurgeDeletedIssues.php` (E8)

**Interfaces — produces:**
```php
AuditLogger::log(string $action, ?Model $target = null, array $metadata = [], ?Workspace $workspace = null, ?User $actor = null, string $actorType = 'user'): void
// workspace defaults to CurrentWorkspace; via as ActivityLogger computes it; ip and user agent from the request when there is one.
AuditLogger::forEveryWorkspaceOf(User $user, string $action, array $metadata = []): void   // sign-in events
```
- Events: exactly the list in spec 7.0d, plus `issue.purged` and `workspace.deletion_scheduled` / `workspace.deletion_cancelled` (D2). Events owned by later PRs (`2fa.*`, `auth.social_*`, `entitlement.changed`, `plan_limit.reached`) are added there with their tests.
- `workspace.settings_changed` stores before and after for the changed keys only.
- Page: design system audit log table: when, who, action, target, via, IP; filters by actor, action group (the part before the dot), date range; pagination; CSV export of the current filter (E9), which itself logs `audit_log.exported`. Owners and Admins only.
- The table is append-only: the model blocks update and delete.

- [ ] **Failing tests first:** one test per event asserting exactly one row with the right action, actor, target label and workspace; sign-in events appear in each of the user's workspaces; a sweep that performs every audited action with known secret values (password, plain token, recovery code, OAuth token) and asserts none appears anywhere in `audit_logs`; access by role; CSV content and cap.
- [ ] Implement; browser checks.
- [ ] Open PR 4.

### PR 5: Entitlements and limits

**Files:**
- Create: migration for `workspace_entitlements`, `app/Enums/Entitlement.php`, `app/Services/{Entitlements,WorkspaceUsage}.php`, `app/Exceptions/PlanLimitReached.php`, `app/Console/Commands/ManageEntitlements.php`, `resources/js/Components/PlanLimitDialog.tsx`, `resources/js/Pages/Workspaces/Settings/Usage.tsx`, tests `Workspaces/{WorkspaceUsageTest,EntitlementEnforcementTest,RateLimitTest,EntitlementCommandTest}.php`
- Modify: `InvitationService`, `MembershipService`, `WorkspaceMemberService`, `ProjectService`, `WorkspaceService::create` (D6), `AppServiceProvider` (rate limiter), `bootstrap/app.php` (render `PlanLimitReached`), MCP error handling, `HandleInertiaRequests`

**Interfaces — produces:**
```php
enum Entitlement: string   // every key of section 4, with type(), description(), enforcedInStep()
Entitlements::get(Workspace $w, Entitlement $key): int|bool|string|null            // null = unlimited
Entitlements::ensure(Workspace $w, Entitlement $key, int $adding = 1): void        // throws PlanLimitReached
Entitlements::set(Workspace $w, Entitlement $key, string $value, string $source): void
WorkspaceUsage::billedUsers(Workspace $w): int     // Owners, Admins, anyone Member or Admin in a project, pending invitations that would create one
WorkspaceUsage::viewers(Workspace $w): int
WorkspaceUsage::projects(Workspace $w): int
WorkspaceUsage::overLimit(Workspace $w): array     // key => [allowed, current]; for later steps
```
- `ensure()` is called at: inviting and accepting, a project role change from Viewer to Member or Admin, a workspace role change from Member to Admin, creating a project (web, API, MCP).
- `PlanLimitReached` renders: web → the action is blocked and the plan-limit dialog shows "Your plan allows 2 billed users. Upgrade to add more." (button to `PM_BILLING_URL` for Owners, "Ask an Owner to upgrade" for others, no button when the URL is empty); API → 403 `{"error":"plan_limit","limit":…,"allowed":…,"current":…,"message":…,"upgrade_url":…}`; MCP → the same message as a tool error.
- Rate limiter `api`: per minute and per day from `api_per_minute` / `api_per_day` of the token's workspace, falling back to `PM_API_RATE_LIMIT` per minute and no daily limit; 429 message and `Retry-After` kept.
- `php artisan pm:entitlements {workspace} --set=key=value --unset=key --list`; changes log `entitlement.changed`; a refused action logs `plan_limit.reached`.
- Usage page: each enforced key with usage and limit, or "Unlimited"; Members see only their own usage.
- The billing link itself (signed token to Platform management) belongs to step 3; here the Owner's button is a plain link to `PM_BILLING_URL`.

- [ ] **Failing tests first:** `WorkspaceUsageTest` (every combination of workspace role × project roles × pending invitation, as a dataset), `EntitlementEnforcementTest` (with `billed_users=2`, `viewers=3`, `projects=1`: each `ensure()` site on web, API and MCP, including pending invitations and Review Focus 3's "over the limit at accept time"; error shapes exactly as above; no rows = unlimited; cloud workspaces start with Free defaults, self-hosted with none), `RateLimitTest` (minute and day limits per token, fallback, 429 body), `EntitlementCommandTest`.
- [ ] Implement; browser checks of the dialog and the Usage page.
- [ ] Open PR 5.

### PR 6: Two-factor authentication

**Files:**
- Create: `app/Providers/FortifyServiceProvider.php`, `config/fortify.php` (features: two-factor only), `resources/js/Pages/Auth/TwoFactorChallenge.tsx`, `resources/js/Pages/Profile/Partials/TwoFactorForm.tsx`, `app/Http/Middleware/RequireTwoFactor.php`, `resources/js/Pages/Workspaces/Settings/Security.tsx`, `tests/Feature/Auth/TwoFactorTest.php`
- Modify: `composer.json`, `app/Models/User.php`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, `app/Services/TokenService.php`, Members page, `routes/web.php`

- `composer require laravel/fortify`; list the licence of each new package in the PR; stop if any is incompatible with AGPL-3.0.
- Profile → Security: QR code and secret, confirm with a code, 10 recovery codes shown once; regenerate or turn off with the password.
- Challenge after password sign-in (and after social sign-in in PR 7); 5 attempts per minute per user.
- `RequireTwoFactor` (inside the workspace group): when `settings.require_2fa` is on and the user has no confirmed 2FA, every workspace page redirects to setup; sign-out, profile and switching workspace stay reachable.
- Security page: the `require_2fa` switch, with the number of members who lack 2FA shown before turning it on; a note that existing tokens keep working. Sign-in methods arrive in PR 7.
- Creating a token in a workspace that requires 2FA needs 2FA on.
- Audit: `2fa.enabled`, `2fa.disabled`, `2fa.recovery_codes_regenerated`, `2fa.recovery_code_used`, each with a test in `AuditLogTest`.

- [ ] **Failing tests first** (`TwoFactorTest`): enable needs a valid code; sign-in then stops at the challenge; a wrong code is refused and the 6th attempt in a minute is throttled; a recovery code works once (Review Focus 5); disabling needs the password; enforcement redirects and its exceptions; token creation rule; the secret and codes never appear in Inertia props after confirmation or in the audit log.
- [ ] Implement; browser checks.
- [ ] Open PR 6.

### PR 7: Google and GitHub sign-in

**Files:**
- Create: migration for `social_accounts`, `app/Models/SocialAccount.php`, `app/Services/SocialSignInService.php`, `app/Http/Controllers/Auth/SocialSignInController.php`, `tests/Feature/Auth/SocialSignInTest.php`
- Modify: `composer.json`, `config/services.php`, `routes/auth.php`, `resources/js/Pages/Auth/{Login,Register}.tsx`, `resources/js/Pages/Profile/Edit.tsx`, Security page, `ResolveWorkspace`, `.env.example`

**Interfaces — produces:**
```php
SocialSignInService::configured(): array                                   // providers with keys in .env
SocialSignInService::signIn(string $provider, SocialiteUser $profile): User // links or creates under the rules below; throws ValidationException with the reason
SocialSignInService::unlink(User $user, string $provider): void             // refuses the last sign-in method
```
- Buttons only for configured providers.
- Link to an existing user only when the provider says the email is verified and it matches; otherwise refuse and say why. GitHub: the primary verified email.
- New users follow PR 2's rules: Cloud creates a workspace; self-hosted needs an invitation unless first.
- A session records the method it was created with. `ResolveWorkspace` refuses entry to a workspace whose `settings.sign_in_methods` does not include it, with a page naming the allowed methods. The Security page edits the list; at least one stays on.
- 2FA still applies after a social sign-in.
- Audit: `auth.social_linked`, `auth.social_unlinked`.

- [ ] **Failing tests first** (`SocialSignInTest`, Socialite fake driver): verified matching email links; unverified email refused with the explanation; unknown email on Cloud creates a user and workspace; on self-hosted it needs an invitation; a user with 2FA meets the challenge (Review Focus 5); unlink refuses the last method; a workspace allowing only password refuses a Google session; unconfigured provider routes 404; no provider token is stored or logged.
- [ ] Implement; README setup steps for creating the two OAuth apps (D8); browser check of the buttons with fake keys set (hidden when unset).
- [ ] Open PR 7.

### PR 8: Docs, seeder and final review

**Files:** Modify `README.md` (editions, first run, sign-in setup, `pm:entitlements`, scheduler jobs), `AGENTS.md` (tokens belong to one workspace; `plan_limit` errors), `CLAUDE.md` (section 5 conventions; web routes under `/{workspace}`; `CurrentWorkspace`; `AuditLogger` vs `ActivityLogger`), `.env.example` (`PM_EDITION`, `PM_BILLING_URL`, provider keys), `database/seeders/DemoSeeder.php` (workspace "Demo" / `demo`; demo@example.com Owner, alex Admin, sam Member and Viewer in WEB; prints the demo token), `PROGRESS.md`.

- [ ] `SeederTest` updated first for the new seed shape, then the seeder.
- [ ] Walk the acceptance paragraph line by line with evidence, as the table above.
- [ ] Plugin security scan of the whole step 0 diff (D7); `/ponytail-review` on it. Fix real findings test-first.
- [ ] Open PR 8.
