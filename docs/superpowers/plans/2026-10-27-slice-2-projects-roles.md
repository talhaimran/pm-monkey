# Slice 2: Projects and Roles Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** Projects with a KEY, per-project roles (Admin/Member/Viewer) and email invitations, available through the web UI, /api/v1, and MCP tools that share the same services.

**Architecture:** `ProjectService` and `MembershipService` hold all logic. Web controllers, API controllers and MCP tools only validate (Form Request rules), authorize (`ProjectPolicy`), call the service, and format the result (`ProjectResource`).

**Spec:** SPEC.md

## Global Constraints
- Business logic only in app/Services; Form Requests, Policies, API Resources; backed enum `ProjectRole`.
- Multi-step writes in DB transactions. No N+1.
- Destructive API/MCP actions require `confirm: true`. Viewers and read-only tokens can never write.
- Errors list valid options and say how to fix them.

## Decisions
- Project key: `^[A-Z][A-Z0-9]{1,9}$`, uppercased on input, unique, and fixed after creation (issue keys depend on it).
- Token abilities are `read` and `write`. `User::canWrite()` is true for session auth (no token) or a token with `write`. Project-scoped tokens come in slice 7.
- Non-members get a 404 for a project, with a message listing the keys they can access. Implemented as a ValidationException with status 404, so MCP shows the same message.
- An invitation is accepted by a logged-in user whose email matches, from the Projects page. The email links to /projects. No token, because email match is the gate.
- Only Admins manage members. A project must always keep at least one Admin.
- MCP in this slice: list_projects, get_project (includes members), create_project, update_project, delete_project. Membership changes are web/API only.

## Data
- `projects`: id, key unique, name, description null, timestamps
- `project_user`: project_id, user_id, role, timestamps; unique(project_id, user_id), index(user_id)
- `project_invitations`: project_id, email, role, invited_by, timestamps; unique(project_id, email)

## Interfaces
- `ProjectRole` enum: Admin='admin', Member='member', Viewer='viewer'; `canWrite()`, `canManage()`; `values(): array`
- `Project::roleFor(User): ?ProjectRole`
- `ProjectService`: `create(User, array): Project`, `update(Project, array): Project`, `delete(Project): void`, `resolve(User, string key): Project`, `listFor(User)`
- `MembershipService`: `invite(Project, User inviter, string email, ProjectRole): ProjectInvitation`, `accept(ProjectInvitation, User): void`, `revoke(ProjectInvitation)`, `changeRole(Project, User, ProjectRole)`, `remove(Project, User)`, `resolveMember(Project, string email): User`

## Routes
- API (auth:sanctum, prefix api/v1): GET/POST projects; GET/PATCH/DELETE projects/{key}; GET projects/{key}/members; POST projects/{key}/invitations; PATCH/DELETE projects/{key}/members/{email}
- Web (auth): GET/POST /projects; GET /projects/{project:key}/settings; PATCH/DELETE /projects/{project:key}; POST /projects/{project:key}/invitations; DELETE .../invitations/{invitation}; PATCH/DELETE .../members/{user}; POST /invitations/{invitation}/accept

## Tasks (TDD)
- [ ] Tests: ProjectApiTest, MembershipApiTest, ProjectWebTest, ProjectMcpTest. Run them and see them fail.
- [ ] Migrations, models, enum, services, policy, requests, resource
- [ ] API controllers and routes; MCP server and tools
- [ ] Inertia pages: Projects/Index, Projects/Settings; nav link
- [ ] Migrate, run Pest, run npm run build, update PROGRESS.md, commit
