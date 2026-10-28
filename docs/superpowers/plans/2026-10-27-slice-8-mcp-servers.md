# Slice 8: MCP Servers (local stdio + web HTTP) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** Expose PmServer over stdio (`php artisan mcp:start pm`) and over HTTP (`POST /mcp`, Sanctum bearer), and prove both by calling real tools over the real transports.

**Spec:** SPEC.md

## Findings from vendor/laravel/mcp (v1.1)
- `routes/ai.php` is auto-loaded when present. `Mcp::web($uri, Server::class)` returns the POST Route (GET/DELETE answer 405); `Mcp::local($handle, Server::class)` registers a handle for `mcp:start {handle}`.
- HTTP is stateless (no session ids). The JSON response is `application/json` unless a tool streams. `ValidateMcpHeaders` only enforces `Mcp-*` headers for newer clients that send protocol `_meta`. On a 401, `AddWwwAuthenticateHeader` adds `WWW-Authenticate: Bearer realm="mcp", error="invalid_token"` for Sanctum.
- stdio reads newline-delimited JSON-RPC until stdin closes and writes one JSON line per response. `Server::start()` calls `boot()` before the transport runs.
- `tools/list` is paginated (`defaultPaginationLength` 15); some clients don't follow cursors.

## Decisions
- Web: `Mcp::web('/mcp', PmServer::class)->middleware(['auth:sanctum', 'throttle:api'])`. Tokens, scope, read-only and the rate limit behave exactly as in the REST API.
- Local: `LocalPmServer extends PmServer` authenticates in `boot()` from `PM_MCP_TOKEN` (config `pm.mcp_token`), a token created at /settings/tokens. It fails fast with a clear message if the token is missing or invalid. Using a token keeps read-only and project-scope rules identical across transports.
- `defaultPaginationLength = maxPaginationLength = 100` so every tool arrives in one `tools/list` page.
- Real-transport tests: `tests/Feature/Mcp/McpHttpTest.php` posts JSON-RPC through the HTTP kernel. `tests/Integration/McpStdioTest.php` spawns `php artisan mcp:start pm` against the test database (DatabaseTruncation, so the child process sees committed rows).

## Tasks
- [ ] Tests: McpHttpTest (401, initialize, tools/list, tools/call write/read-only/scope, resources, rate limit), McpStdioTest (real process: initialize, tools/list, create_issue logged via mcp with token name; refuses to start without a token)
- [ ] routes/ai.php, LocalPmServer, config, PmServer pagination
- [ ] Manual check: curl against the dev server and a stdio run against the dev DB
- [ ] Pest, build, PROGRESS.md, commit
