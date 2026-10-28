# Slice 7: Tokens UI, Idempotency, Rate Limiting, Bulk Ops Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** People create API tokens in a settings page, scoped to projects and read-only or read-write, and can revoke them and see when each was last used. The API gets Idempotency-Key replay on POSTs, a per-token rate limit, and bulk issue create/update (API and MCP).

**Spec:** SPEC.md

## Decisions
- Scope is stored in Sanctum abilities with no migration: `read`, optional `write`, and `project:{id}` per allowed project. No `project:` abilities means all of the user's projects.
- `User::accessibleProjects()` = projects() narrowed by the current token's scope. Every lookup uses it: ProjectService::resolve/listFor, cross-project issue lists, the dashboard and comment lookup by id. Out-of-scope projects return the same 404 as non-membership. Scoped tokens can't create projects (403 with a reason).
- Tokens are managed on the web only (`/settings/tokens`), so a token can't mint tokens. The plain token is shown once via a session flash. Revoking checks ownership (404 otherwise).
- Idempotency: `Idempotent` middleware on the `/api/v1` POST routes, active only when an `Idempotency-Key` header is present. Cache key = token id (or user id) + method + path + key. It stores status, body and a request hash for 24h. A replay returns the stored response with `Idempotent-Replayed: true`. The same key with a different body gets 422; a key still in progress gets 409 (cache lock).
- Rate limit: named limiter `api`, `config('pm.api_rate_limit')` (default 120/min, env `PM_API_RATE_LIMIT`), keyed by token id, then user id, then IP. Applied to `/api/v1` and to the MCP web route in slice 8.
- Bulk: `POST /projects/{key}/issues/bulk` {issues: [...]} and `PATCH /issues/bulk` {updates: [{key, ...}]}, max 50 each, all-or-nothing in one transaction. Item errors are re-keyed as `issues.{i}.{field}` / `updates.{i}.{field}`. Each update is authorized per issue. MCP: bulk_create_issues, bulk_update_issues.

## Interfaces
- `User::accessibleProjects(): BelongsToMany`, `User::tokenProjectIds(): ?array`
- `TokenService`: `create(User, string $name, bool $write, ?array $projectKeys): NewAccessToken`, `revoke(User, int $id)`, `list(User)`
- `IssueService::createMany(Project, User, array)`, `IssueService::updateMany(User, array)`
- `App\Http\Middleware\Idempotent`

## Tasks (TDD)
- [ ] Tests: TokenTest, IdempotencyTest, RateLimitTest, BulkIssueTest
- [ ] accessibleProjects + replacements, TokenService + web controller/page, flash sharing
- [ ] Idempotent middleware, rate limiter, bulk requests/service/controllers/MCP tools
- [ ] Browser check (create, copy, revoke token; real API call shows last used), Pest, build, PROGRESS.md, commit
