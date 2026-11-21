# Step A: Agents, part 1 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. One pull request per task; each is merged before the next starts. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every API/MCP token becomes a named agent owned by one person, with its own permission level, project list, pause/revoke and rate limit; agents can't finish work (Done) without a person; MCP and REST get `whoami`, `get_updates` and compact responses; projects get agent instructions; workspaces get AI provider settings and (last) OAuth for MCP clients.

**Architecture:** An agent is an `agents` row. Its token is still a Sanctum token whose *tokenable is the owner* (decision A-D1), with a new `personal_access_tokens.agent_id`. So `$request->user()` stays the owner and every existing policy keeps working: the owner's project role is checked first by construction. `User::agent()` returns the request's agent (or null for people), and the three agent limits (level, project list, status) are applied in the same places the old token abilities were: `User::canWrite()`, a new `User::canDelete()`, and `User::tokenProjectIds()`. The "Done needs a person" rule lives in one service (`ApprovalService`) that `BoardService::move` and `IssueService::update` call before moving an issue into the project's last column.

**Tech Stack:** Laravel 13, Pest on MySQL, Inertia + React 18 + TypeScript, Sanctum, laravel/mcp v1.1, the step U design system. New dependency, approved 2026-11-19 for PR 6 only: `laravel/passport` (MIT). No AI provider library in this step (A-D4).

**Spec:** `docs/specs/pmops-spec.md` section 8 (Modes, A1–A5, Agents data), section 4 (roles table, `agents_per_user`, `api_per_*`), section 5 (conventions) and 5.1 (agents in the UI, the AI box). Design system: `docs/design-system/components/ApprovalCard`, `Avatar` (`av--agent`), `IssuePage`, `IssueDrawer`.

## Global Constraints

- CLAUDE.md is in force: logic in `app/Services`, thin web/API/MCP, Form Requests, Policies, Pest on MySQL, the per-slice checklist, a case in `WorkspaceIsolationTest` for every new route, endpoint and MCP tool.
- "An agent sees at most what its owner sees." Check order: owner's project role → agent level → agent's project list; strictest wins.
- Agents are never assignees and never appear in assignee pickers; `assignee` inputs don't accept them.
- Agent tokens work only on API and MCP routes; they can't sign in to the web UI.
- **Never available to agents:** workspace and project settings, members and roles, workflows, field definitions, webhooks, AI keys, other agents' tokens. Settings → Agents and Settings → AI are web-only for people.
- Agent tokens are shown once and stored hashed. The AI key is encrypted, never returned by any API or Inertia prop, never logged, redacted from errors.
- Every agent and AI settings change writes an `AuditLogger` event (names below, verbatim from the spec). Approval decisions go in the issue's activity log, not the audit log.
- `/api/v1` paths don't change; existing input shapes don't change (cursor pagination is added beside `page`, A-L4).
- No entitlement row = unlimited. `agents_per_user` is checked with `Entitlements`; Cloud Free values come from the enum (2 / 10 per section 4).
- Internal identifiers stay: `pm_monkey`, the `pm` MCP handle, `PM_*` env vars, `pm.via`.
- Every screen follows 5.1: tokens, light and dark, empty states, 32px targets, sentence case; the agent avatar always sits beside words ("Talha's agent" or the agent's name).

## Decisions (confirmed by the owner 2026-11-19)

| # | Topic | Decision |
|---|---|---|
| A-D1 | Token owner | The Sanctum token stays on the owner (`tokenable = User`) and gains `agent_id`. Differs from the spec's "tokenable = agent" wording; the spec's rule "an agent sees at most what its owner sees" then holds by construction. |
| A-D2 | Settings tools | Agents lose project create/update/delete, column create/update/delete, and project members and invitations, on REST and MCP: `403` with "Only people can change project settings. Do this in the web app." Labels and sprints stay; every delete needs the delete level. The tools stay registered (tool list unchanged) and refuse agent calls, so a later "retire tools" decision is one list change. |
| A-D3 | MCP OAuth | Add `laravel/passport` in PR 6, after the rest of step A. The consent screen picks which of your agents the client acts as. OAuth tokens work on `/mcp` only; REST stays agent-token only. |
| A-D4 | AI library | Step A stores provider settings only. The library (Laravel's AI package or Prism) is chosen and added with its first caller (step 4 or B), asking first. |
| A-D5 | Delivery | Same as step 0's D1: one PR per task below, merged before the next starts. Branches `step-a/<n>-<slug>`. |

## Leanings (built as written unless the owner changes them)

| # | Topic | Leaning |
|---|---|---|
| A-L1 | Migrated write tokens | Existing tokens become agents at their read/write level (spec). A write token could delete before; as a "Read and write" agent it can't until its owner turns on "Read, write and delete". Listed in the PR body and README. |
| A-L2 | Agent display | `Agent::label()` is the agent's name; a new agent's name defaults to "{Owner first name}'s agent". Migrated agents keep their token's name. The owner's name is always shown next to it ("deploy-bot · Talha's agent") where space allows. |
| A-L3 | `get_updates` | Returns events for the calling agent after an opaque `cursor`: approval requests that were approved, rejected, expired or went stale. Later steps add kinds (notifications in step 3). |
| A-L4 | Cursor pagination | REST and MCP list endpoints accept `cursor` and return `next_cursor`; `page` keeps working. Default 25, max 50 (as today). |
| A-L5 | Approval reason | `move_issue`/`update_issue` take an optional `reason` (max 200 chars), stored in the request's `action` JSON and shown on the ApprovalCard. |
| A-L6 | Agent creates in Done | `create_issue` (and bulk) by an agent with the last column as status is refused: "Agents can't create issues in {column}. Create it in another column, then move it; a person approves the move." |
| A-L7 | Mixed update | `update_issue` by an agent that changes the status to Done *and* other fields applies the other fields and opens the approval for the status; the response carries both. |
| A-L8 | AI settings by edition | No edition check: when `PM_AI_PROVIDER` is set in `.env`, Settings → AI shows it read-only ("Configured by your server"); otherwise Owners/Admins edit it there. The monthly cap is editable either way. |
| A-L9 | Agent token expiry | Keep today's optional expiry (30, 90, 365 days or never) on agent tokens. |
| A-L10 | `agent_run_id` columns | Added in step B with runs; step A adds only `agent_id`. |
| A-L11 | Agents off workspace-wide | Workspace setting `agents_enabled` (default on). Off: every agent token gets `403 {"error":"agents_disabled"}`. Logged as `agent_policy.changed`. |
| A-L12 | Tokens with no workspace | Rows with `workspace_id` null (already refused since step 0) are left alone by the migration. |
| A-L13 | Tool grouping | No grouping in step A: every existing feature (sprints, labels) is always on. Grouping starts when a feature can be turned off per project (step 10's Scrum/Kanban). |
| A-L14 | Spec items with nothing to build yet | "Agents don't trigger other agents", "running Mode C jobs stop at their next step", "can't be mentioned" and `notifications.agent_id` land with runs (B) and mentions (step 3). |

## Review Focus

1. **The owner's role drops mid-session** (made a project Viewer, removed from a project, demoted): the agent's next write is refused at once with the owner's-role message. Test in Task 3.
2. **Reaching Done another way**: `update_issue` with `status`, `bulk_update_issues`, `PATCH /issues/{key}`, `POST /issues/{key}/move`, `create_issue` in the last column. Every path opens an approval (or is refused, A-L6); none moves the issue. Test in Task 3.
3. **Approving after the world changed**: the issue moved, was deleted, its target column was deleted, the decider lost Admin, the agent was revoked. Moved → "changed since" and a second confirm; impossible → `stale`; never a silent move. Test in Task 3.
4. **A long-lived stdio MCP process** (`LocalPmServer` boots once): pausing or revoking the agent takes effect on the next tool call, not the next restart. Test in Task 1.
5. **Secrets in odd places**: the AI key in Inertia props, API JSON, audit metadata, `laravel.log`, or a validation/exception message; a plaintext agent token in the audit log or activity. Tests in Tasks 1 and 5.

---

### Task 1 (PR 1): Agents and agent tokens (A1, backend)

Branch `step-a/1-agents`.

**Files:**
- Create: `database/migrations/2026_11_19_000001_create_agents_tables.php` (tables `agents`, `agent_projects`; `agent_id` on `personal_access_tokens`, `activities`, `comments`, `audit_logs`; data migration of existing tokens)
- Create: `app/Models/Agent.php`, `app/Enums/AgentLevel.php`, `app/Enums/AgentStatus.php`, `app/Services/AgentService.php`
- Delete (ask first, per CLAUDE.md): `app/Services/TokenService.php` once every caller uses `AgentService`
- Modify: `app/Models/User.php` (`agent()`, `canWrite()`, `canDelete()`, `tokenProjectIds()`), `app/Http/Middleware/ResolveTokenWorkspace.php`, `app/Mcp/Servers/LocalPmServer.php`, `app/Mcp/Servers/PmServer.php` (per-call status check), `app/Providers/AppServiceProvider.php` (limiter key), `app/Services/ActivityLogger.php`, `app/Services/AuditLogger.php`, `app/Services/WorkspaceMemberService.php` (revoke on removal), `app/Http/Controllers/Web/TokenController.php` (calls `AgentService` until Task 2 replaces the page), `tests/Pest.php` (`apiAs()` makes an agent)
- Test: `tests/Feature/Agents/AgentServiceTest.php`, `tests/Feature/Agents/AgentMigrationTest.php` (in `tests/Integration`, like `WorkspaceMigrationTest`), `tests/Feature/Security/AgentSecurityTest.php`, cases in `WorkspaceIsolationTest`

**Interfaces:**
- Produces:
  - `enum AgentLevel: string { Read = 'read'; Write = 'write'; Delete = 'delete'; }` with `canWrite(): bool`, `canDelete(): bool`
  - `enum AgentStatus: string { Active = 'active'; Paused = 'paused'; Revoked = 'revoked'; }`
  - `Agent` model: `workspace_id, owner_user_id, name, description, permission_level (AgentLevel), status (AgentStatus), last_used_at`; relations `owner()`, `workspace()`, `projects()` (belongsToMany via `agent_projects`), `token()` (hasOne PersonalAccessToken); `label(): string`
  - `AgentService::create(User $owner, array $data): array{0: Agent, 1: string}` (agent, plaintext token); `$data` = `name, description?, level, projects?: list<string> (keys; empty = all), expires_in_days?: int|null`
  - `AgentService::update(Agent, User $actor, array $data): Agent`; `pause(Agent, User $actor)`; `resume(Agent, User $actor)`; `revoke(Agent, ?User $actor, string $reason = 'manual')`; `regenerateToken(Agent, User $actor): string`; `revokeAllOf(User $owner, Workspace $ws)`; `listFor(User $viewer, Workspace $ws): array` (own agents; all agents for Owners/Admins, flagged)
  - `User::agent(): ?Agent` — the agent of the current access token, cached per request; null for session users
  - `User::canDelete(): bool` — session users true; agents only at `Delete` level
  - `ApiError` JSON for a paused agent: `403 {"error":"agent_paused","message":"{label} is paused. Its owner or a workspace Admin can resume it in Settings → Agents."}`

- [ ] **Step 1: Write the failing tests**
  - `AgentServiceTest`: create returns a token that authenticates `GET /api/v1/projects`; the token row has `agent_id` and is hashed; `agents_per_user` = 2 refuses the third active-or-paused agent with `plan_limit` (revoked ones don't count); name unique per owner in a workspace, error lists the owner's agent names; projects must be ones the owner can access (error lists them, like `TokenService` today); each of `agent.created`, `agent.updated` (metadata `level`, `projects` before/after), `agent.paused`, `agent.resumed`, `agent.revoked` (`by: owner|admin|system`), `agent.token_regenerated` is written once with no token text in it; regenerate makes the old token 401.
  - Permission order: a `Read` agent of a project Admin can't `POST /projects/WEB/issues` (403, read-only message); a `Write` agent of a project Viewer can't either (403, Viewer message); a `Write` agent scoped to `API` gets 404 listing valid projects for `WEB`; a `Write` agent's `DELETE /issues/WEB-1` is 403 "This agent can't delete. Its owner can allow deletes in Settings → Agents."; a `Delete` agent's is 200.
  - Paused agent: REST and HTTP MCP get the `agent_paused` 403; the owner's web session still works. Revoked: 401, token row gone, agent row kept with `status = revoked`.
  - Owner removed from the workspace: their agents are revoked with `agent.revoked` `by: system`, `reason: owner_left`.
  - Stdio (Review Focus 4): `LocalPmServer::authenticate($plain)`, then pause the agent, then the next tool call through the server fails with the paused message.
  - Rate limit per agent: with `api_per_minute` = 2, the third call in a minute is 429 with `Retry-After`; another agent of the same owner is not slowed; regenerating the token doesn't reset the counter.
  - Activity and audit rows written by an agent carry `agent_id`; activity keeps `token_name` as before.
  - Bearer token on a web route (`GET /acme/dashboard`) redirects to login (agent tokens can't use the web UI).
  - `AgentMigrationTest`: a read token scoped to `WEB` becomes a `Read` agent with project `WEB`, same name, owner and workspace; a write token with no scope becomes a `Write` agent with no projects (= all); the old plaintext token still authenticates after migrating; `down()` restores the previous schema.
- [ ] **Step 2: Run them to see them fail.** `php artisan test tests/Feature/Agents tests/Feature/Security/AgentSecurityTest.php` → FAIL (no `agents` table).
- [ ] **Step 3: Back up the dev DB** (`mysqldump pm_monkey > ~/pm_monkey-before-step-a-1.sql`), then write the migration. Columns per the spec's Agents data table; `agents.name` max 100, `description` max 500, index `(workspace_id, owner_user_id)`; `agent_projects` primary `(agent_id, project_id)`, both FKs cascade; `agent_id` nullable FK `nullOnDelete` on the four tables. Data step in chunks: one agent per token with `workspace_id`, level from `abilities` (`write` → Write, else Read), projects from `project:{id}` abilities that belong to that workspace.
- [ ] **Step 4: Implement** the enums, model, `AgentService` (create and update inside `Entitlements::locked()`, count = owner's non-revoked agents in the workspace; add `Entitlements::ensureCount(Workspace, Entitlement, int $current, int $adding = 1)` and have `ensure()` call it), the `User` methods (authority is the agent row; `abilities` is no longer read, new tokens get `['*']`), the middleware/`LocalPmServer`/`PmServer` status checks (one method `AgentService::ensureUsable(?Agent): void` used by all three; it also bumps `last_used_at` at most once a minute), the limiter key `agent:{id}`, the logger columns, and revocation in `WorkspaceMemberService::remove` and in `ResolveTokenWorkspace`'s "owner left" branch.
- [ ] **Step 5: Run the full suite** — `php artisan test` → all pass (existing token tests now run through `apiAs()` agents).
- [ ] **Step 6: Per-slice checklist and PR.** `npm test`, `npm run build`, `vendor/bin/pint --test`, security review of the diff, PROGRESS.md line, `gh pr create`.

### Task 2 (PR 2): Settings → Agents and agents in the UI (A1, web)

Branch `step-a/2-agents-ui`.

**Files:**
- Create: `resources/js/Pages/Agents/Index.tsx`, `app/Http/Controllers/Web/AgentController.php`, `app/Http/Requests/Agents/StoreAgentRequest.php`, `UpdateAgentRequest.php`, `app/Policies/AgentPolicy.php`, `resources/js/Components/AgentLabel.tsx`
- Delete (ask first): `resources/js/Pages/Tokens/Index.tsx`, `app/Http/Controllers/Web/TokenController.php`
- Modify: `routes/web.php` (`/settings/agents` routes; `/settings/tokens` 301 → `/settings/agents`; legacy redirect list), `resources/js/Components/Avatar.tsx` (`agent` variant per design system `av--agent`), activity list, comment list and audit log table (show `AgentLabel` where `agent_id` is set), `app/Http/Controllers/Web/WorkspaceSettingsController.php` + `Security.tsx` (agents on/off, A-L11), sidebar/profile link text "Agents"
- Test: `tests/Feature/Agents/AgentPageTest.php`, `tests/Feature/Security/AgentPolicyTest.php`, `WorkspaceIsolationTest` cases

**Interfaces:**
- Consumes: `AgentService` (Task 1).
- Produces: routes `agents.index|store|update|pause|resume|revoke|regenerate`; `AgentPolicy` abilities `update` (owner only), `pause`/`resume`/`revoke` (owner, or workspace Owner/Admin); `<AgentLabel agent={{name, owner}} />`; workspace setting `agents_enabled` read by `AgentService::ensureUsable`.

- [ ] **Step 1: Failing tests.** The page lists my agents with level, projects, status, last used, expiry; a workspace Admin also sees "All agents in Acme" with pause/resume/revoke but no edit or regenerate; a Member can't pause someone else's agent (403); creating shows the token exactly once (flash), never again on reload; `/acme/settings/tokens` 301s to `/acme/settings/agents`; an API/agent token gets 401/redirect on every `/settings/agents` route; with `agents_enabled` off every agent call is `403 agents_disabled` and `agent_policy.changed` is logged once with before/after; activity and comments written by an agent show the agent label in the issue page props, not the owner's name alone; agents never appear in the assignee picker props.
- [ ] **Step 2: Run them to see them fail.**
- [ ] **Step 3: Implement** the controller (thin over `AgentService`), Form Requests reusing the rules from Task 1, policy, page (design system components: table, Pill for level/status, Modal for create/edit with the token reveal, `WarningConfirm` for revoke and for turning on the delete level, `EmptyState` "No agents yet. Create one to let Claude Code, Codex or a script work in Acme."), `AgentLabel` and the avatar variant.
- [ ] **Step 4: Run the suite; check the page in the browser preview** (`app`, demo login) in light, dark and at 375px: create, reveal, pause, resume, regenerate, revoke; Admin view as alex.
- [ ] **Step 5: Per-slice checklist and PR.**

### Task 3 (PR 3): Guardrails and approval requests (A2)

Branch `step-a/3-approvals`.

**Files:**
- Create: migration `create_approval_requests_table`, `app/Models/ApprovalRequest.php`, `app/Enums/ApprovalStatus.php`, `app/Services/ApprovalService.php`, `app/Http/Controllers/Web/ApprovalController.php`, `app/Console/Commands/ExpireApprovals.php` (`approvals:expire`, scheduled hourly), `resources/js/Components/ApprovalCard.tsx`, `resources/js/Components/AiBox.tsx`
- Modify: `app/Services/BoardService.php::move`, `app/Services/IssueService.php::update|updateMany|create|createMany`, `app/Policies/ProjectPolicy.php` (new `remove` ability = `contribute` + `canDelete()`; `manage` refuses agents, A-D2), every delete route/tool switched to `remove`, `IssueController`/`BoardController` and the MCP `MoveIssue`/`UpdateIssue`/`BulkUpdateIssues`/`GetIssue` tools (pending-approval responses, A-L5 `reason`), `app/Http/Resources/IssueResource.php` (`pending_approvals`), `DashboardService` + `Dashboard.tsx` ("Pending approvals" section), `Issues/Show.tsx` and `IssueDrawer.tsx` (render `AiBox`), `routes/console.php`
- Test: `tests/Feature/Agents/ApprovalTest.php`, `tests/Feature/Agents/AgentGuardrailsTest.php`, `tests/Feature/Security/ApprovalSecurityTest.php`, `WorkspaceIsolationTest` cases

**Interfaces:**
- Consumes: `User::agent()`, `User::canDelete()` (Task 1); `AgentLabel` (Task 2).
- Produces:
  - `approval_requests` exactly as spec A2, with `issue_version` nullable (null until step 1) and `action` = `{"to_column_id":int,"to_column":string,"from_column_id":int,"reason":?string}`
  - `enum ApprovalStatus: string { Pending, Approved, Rejected, Expired, Stale }` (lower-case values)
  - `ApprovalService::requestDone(Issue $issue, User $owner, Agent $agent, Column $to, ?string $reason): ApprovalRequest` — returns the existing pending one for the same issue and target (locks the issue row)
  - `ApprovalService::needsApproval(Issue $issue, Column $to, ?Agent $agent): bool` — true when `$agent` is set and `$to` is the project's last column (before step 1, "Done" = last column)
  - `ApprovalService::approve(ApprovalRequest, User $decider, bool $confirmChanged = false): ApprovalRequest`; `reject(ApprovalRequest, User $decider, ?string $note)`; `decidableBy(User $viewer, Workspace $ws): Collection`; `expireDue(): int`
  - Response for a held move: REST `202 {"status":"pending_approval","approval_request":{id,status,issue,to,expires_at}, "issue":{...}}`; MCP summary "WEB-12 needs a person's approval to move to Done (request 41, expires …)." plus the same JSON
  - Web routes `POST /{workspace}/approvals/{id}/approve` (`confirm_changed` optional) and `/reject` (`note` optional, max 500)

- [ ] **Step 1: Failing tests.**
  - Guardrails (A-D2): an agent of a project Admin gets 403 "Only people can change project settings. Do this in the web app." on `POST/PATCH/DELETE /projects…`, columns, members and invitations, and on the matching MCP tools; label create/rename and sprint create/start/complete still work at Write; deletes need Delete level.
  - Review Focus 1: demote the owner to Viewer between two agent calls → the second write is 403 with the Viewer message.
  - Review Focus 2: each of `move_issue`, `update_issue {status}`, `bulk_update_issues`, `PATCH /issues/{key}`, `POST /issues/{key}/move` by an agent to the last column leaves the issue where it was and returns `pending_approval`; a repeat returns the same request id; `create_issue` in the last column is refused (A-L6); a mixed update applies the other fields (A-L7); a person's own move to Done is unaffected.
  - Deciding: the agent's owner can approve; an Admin of the project can; a project Member who isn't the owner gets 403; a decider who couldn't make the move themselves (Viewer owner) gets 403 with the reason. Approve moves the issue as the decider, and the activity entry carries `agent_id` and `approval_request_id`; approve and reject each add one activity entry ("approved Talha's agent's move to Done"), none in `audit_logs`.
  - Review Focus 3: issue moved since → approve without `confirm_changed` returns "WEB-12 moved from Review to In progress since this request. Approve again to move it to Done." and doesn't move; with it, moves. Issue deleted, target column deleted, or agent revoked → status `stale`, nothing moves.
  - Expiry: `travel(7)->days()` + `approvals:expire` → `expired`; an expired request can't be approved; the agent can ask again and gets a new id.
  - `get_issue` lists pending approvals; the dashboard prop `pendingApprovals` holds exactly what the viewer can decide, in the current workspace only.
- [ ] **Step 2: Run them to see them fail.**
- [ ] **Step 3: Implement** `ApprovalService` (approve inside a transaction that locks the request and the project row, then calls `BoardService::move` as the decider with the activity context), the hooks in `BoardService::move` and `IssueService::update*`/`create*` (one call to `needsApproval`, so the rule lives in one place), the policy changes, tool and controller responses, the expiry command, `ApprovalCard` (design system anatomy and states: pending, `is-stale`, expired; buttons "Approve move to Done" / "Reject") and `AiBox` (pending approvals only in step A; later steps add runs). Approving from the web flashes the usual Undo toast.
- [ ] **Step 4: Run the suite; browser check** the dashboard section, the AI box on the issue page and the drawer, approve/reject/stale, light, dark, 375px.
- [ ] **Step 5: Per-slice checklist and PR.**

### Task 4 (PR 4): MCP and REST hardening, project agent instructions (A3, A4)

Branch `step-a/4-mcp-rest`.

**Files:**
- Create: `app/Mcp/Tools/Whoami.php`, `app/Mcp/Tools/GetUpdates.php`, `app/Http/Controllers/Api/V1/MeController.php` (`GET /me` = whoami, `GET /me/updates`), migration `create_project_agent_settings_table` (`project_id` unique FK cascade, `instructions` text nullable, `mode_c_enabled` bool default true), `app/Models/ProjectAgentSettings.php`
- Modify: `PmServer` (register tools; instructions text mentions `whoami`, `get_updates`, approvals and `fields`), `RespondsWithJson` (cursor meta), list tools and `IssueListController` (A-L4 cursor, `fields`), `GetIssue`/`SearchIssues`/`MyIssues` + `IssueResource` (`fields`), `GetProject` + `ProjectResource` (`agent_instructions`), `ProjectService` + project settings page (`Projects/Settings.tsx`: "Agent instructions" textarea, max 10,000 chars), `AGENTS.md` (tool table; coding-agent snippet: put the issue key in branch names, post a short comment when done)
- Test: `tests/Feature/Agents/WhoamiTest.php`, `tests/Feature/Agents/UpdatesTest.php`, `tests/Feature/Api/CursorPaginationTest.php`, `tests/Feature/Mcp/FieldsTest.php`, `tests/Feature/Projects/AgentInstructionsTest.php`, `WorkspaceIsolationTest` cases

**Interfaces:**
- Consumes: `User::agent()`, `ApprovalRequest` (Tasks 1, 3).
- Produces: `whoami` JSON `{agent:{id,name,level,status}, owner:{name,email}, workspace:{slug,name,owner_role}, projects: list<string>|"all", rate_limit:{per_minute:{limit:?int,remaining:int}, per_day:{limit:?int,remaining:?int}}}`; `get_updates(cursor?: string, per_page?: int)` → `{updates:[{id, kind: "approval.approved"|"approval.rejected"|"approval.expired"|"approval.stale", approval_request_id, issue, to, decided_by:?string, note:?string, at}], next_cursor:?string}` (A-L3); `fields` = comma list of top-level keys, `key` always included, unknown names error listing valid ones; `ProjectAgentSettings` used again in Task 5.

- [ ] **Step 1: Failing tests.** `whoami` over MCP and `GET /api/v1/me` returns the shape above for an agent, and for the stdio server; a session user calling `GET /api/v1/me` gets `agent: null`. `get_updates` returns only this agent's decided requests, in order, and an empty list after the last cursor; another agent of the same owner sees none of them. Cursor pagination walks 60 issues in 25/25/10 with no repeats while `page=2` still works; `next_cursor` is null on the last page. `fields=key,title,status` returns only those keys on REST and MCP; `fields=nope` errors listing valid fields. `tools/list` contains no project, issue or user text (descriptions are static). `get_project` returns `agent_instructions`; only project Admins (people) edit it; `project.agent_settings_changed` logs `{"instructions_changed": true}` rather than the text, which may be long. 429 responses carry `Retry-After` on REST and MCP HTTP.
- [ ] **Step 2: Run them to see them fail.**
- [ ] **Step 3: Implement** the two tools and controller, cursor support in the shared pagination helper (`cursorPaginate` on the same ordered query; order by `id` where the current sort isn't unique), `fields` filtering in the resource layer, the settings model and page field, and the docs.
- [ ] **Step 4: Run the suite; browser check** the project settings field.
- [ ] **Step 5: Per-slice checklist and PR.**

### Task 5 (PR 5): AI provider settings and agent policy (A5)

Branch `step-a/5-ai-settings`.

**Files:**
- Create: migration `create_ai_providers_table` (spec columns; `workspace_id` unique; `key` text, `encrypted` cast; `tokens_used_month` default 0), `app/Models/AiProvider.php`, `app/Enums/AiProviderType.php` (`anthropic`, `openai`, `google`, `openai_compatible`), `app/Services/AiSettingsService.php`, `app/Http/Controllers/Web/AiSettingsController.php`, `app/Http/Requests/Workspaces/UpdateAiSettingsRequest.php`, `resources/js/Pages/Workspaces/Settings/Ai.tsx`
- Modify: `config/pm.php` + `.env.example` (`PM_AI_PROVIDER`, `PM_AI_MODEL`, `PM_AI_ENDPOINT`, `PM_AI_KEY`), `WorkspacePolicy` (`manageAi`: Owner/Admin), `WorkspaceSettingsHeader` tab "AI", `HandleInertiaRequests` shared prop `ai.enabled` (bool only), project settings page (Mode C toggle on `ProjectAgentSettings`), workspace setting `mode_c_enabled` (workspace-wide), README env table
- Test: `tests/Feature/Workspaces/AiSettingsTest.php`, `tests/Feature/Security/AiKeySecurityTest.php`, `WorkspaceIsolationTest` cases

**Interfaces:**
- Consumes: `ProjectAgentSettings` (Task 4), `agents_enabled` setting (Task 2).
- Produces: `AiSettingsService::for(Workspace): ?array{provider: AiProviderType, model: string, endpoint: ?string, source: 'env'|'workspace'}` (no key); `AiSettingsService::key(Workspace): ?string` (the only way to read the key; used by step 4/B); `update(Workspace, User, array)`, `remove(Workspace, User)`, `setCap(Workspace, User, ?int)`; `modeCEnabled(Project): bool` (workspace switch AND project switch AND a provider configured).

- [ ] **Step 1: Failing tests.** Owners/Admins can save provider, model, endpoint (required for `openai_compatible`, must be `https://` or `http://localhost…`), key; Members get 403; API/agent tokens can't reach the routes. Audit: `ai_provider.updated` (provider, model, endpoint), `ai_provider.key_changed` (no key), `ai_provider.removed`, `ai_provider.cap_changed`, `agent_policy.changed` (Mode C on/off), `project.agent_settings_changed` (Mode C on/off). With `PM_AI_PROVIDER` set the page is read-only and shows "Configured by your server" (A-L8). Review Focus 5: after saving key `sk-test-SECRET`, the string appears in no Inertia prop, no API response, no `audit_logs` row, not in `storage/logs/laravel.log` after a forced validation error on the same form, and the DB column is not plaintext. The page says "Mode C sends issue content to the chosen provider." `ai.enabled` is false without a provider.
- [ ] **Step 2: Run them to see them fail.**
- [ ] **Step 3: Implement** service, controller, request (key field `password`-type, `sometimes`: left blank keeps the old key), page (Security page style; provider select, model, endpoint, key, cap, workspace "Mode C" switch with the plain statement), the project toggle, shared prop, config and docs.
- [ ] **Step 4: Run the suite; browser check** light, dark, 375px, and with the env set.
- [ ] **Step 5: Per-slice checklist and PR.**

### Task 6 (PR 6): OAuth for MCP clients (A1, A3; `laravel/passport`)

Branch `step-a/6-mcp-oauth`.

**Files:**
- Modify: `composer.json` (`laravel/passport`; check every package it pulls in is AGPL-compatible and that it supports Laravel 13 — stop and ask if not), `routes/ai.php` (`Mcp::oauthRoutes()`; `/mcp` accepts `auth:sanctum` or the Passport guard), `config/auth.php` (Passport guard), migration (Passport tables; `agent_id` on `oauth_access_tokens`), `User::agent()` (resolve from a Passport token too), `AgentService::revoke|pause` (revoke the agent's OAuth tokens), the authorization view (restyled with design tokens, with an agent picker: one of my agents, or "Create {First}'s {client name} agent" at Write level), `Agents/Index.tsx` ("Connected: Claude Desktop, last used …")
- Test: `tests/Feature/Agents/McpOAuthTest.php`, `tests/Feature/Security/McpOAuthSecurityTest.php`, `WorkspaceIsolationTest` cases

**Interfaces:**
- Consumes: `AgentService`, `User::agent()` (Task 1).
- Produces: OAuth discovery at `/.well-known/oauth-protected-resource` and `/.well-known/oauth-authorization-server`; dynamic client registration (laravel/mcp's); an OAuth access token acts as exactly one agent in that agent's workspace.

- [ ] **Step 1: Failing tests.** Discovery metadata points at this app; the consent screen lists only my agents in the current workspace; approving issues a token that calls `tools/list` on `/mcp` as that agent (`whoami` shows it); the same token gets 401 on `/api/v1/*`; pausing the agent makes the OAuth token 403 `agent_paused`; revoking it makes it 401; creating an agent from the consent screen counts toward `agents_per_user`; a client registered by someone else can't be used to act as my agent without my consent.
- [ ] **Step 2: Run them to see them fail.**
- [ ] **Step 3: Install Passport** (`composer require laravel/passport`, record licences), then implement per the files list.
- [ ] **Step 4: Run the suite; try the flow with the MCP inspector** against `php artisan serve` (record what was and wasn't tried; a real Claude Desktop round trip is the owner's to try, like D8 in step 0).
- [ ] **Step 5: Per-slice checklist and PR.**

### Task 7 (PR 7): Docs, final security scan and review

Branch `step-a/7-docs-and-review`.

- [ ] **Step 1:** README (agents, levels, approvals, Settings → AI env vars, OAuth setup, the A-L1 note for existing write tokens), AGENTS.md (ground rules: levels, `pending_approval`, `get_updates`, `whoami`, `fields`, cursor), CLAUDE.md "Agents (step A)" section (where `User::agent()` and `ApprovalService::needsApproval` sit, the A-D1 token shape), DemoSeeder (one agent for demo with a printed token, replacing the demo API token; one pending approval).
- [ ] **Step 2:** claude-security "scan changes" over all of step A; fix every finding test-first with probes in `tests/Feature/Security`.
- [ ] **Step 3:** /ponytail-review of all of step A; fix what the owner picks.
- [ ] **Step 4:** Walk the spec's A1–A5 bullets line by line in the PR body, each mapped to a passing test file; per-slice checklist and PR.
