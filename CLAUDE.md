# CLAUDE.md

SPEC.md is the source of truth. PROGRESS.md logs slice progress and decisions made on ambiguous points.

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

## Architecture rules
- Business logic only in `app/Services` / `app/Actions`. Inertia controllers, API controllers (`/api/v1`), and MCP tools are thin and call the same services. Never duplicate logic.
- Form Requests for validation, Policies for authorization, API Resources for JSON.
- PHP backed enums for issue type, priority, project role.
- DB transactions for multi-step writes; index foreign keys and filter columns.
- `Model::preventLazyLoading()` outside production; no N+1.
- Issues addressable by key (`WEB-12`) everywhere; accept names (assignee email, column name) where sensible; errors list valid options.
- Destructive API/MCP actions need `confirm: true`. Viewers and read-only tokens never write.

## Commands
- Dev servers: `composer run dev` (runs `php artisan dev`: server + queue + vite)
- Tests: `php artisan test` (Pest), one file: `php artisan test tests/Feature/XTest.php`. Tests run on MySQL db `pm_monkey_test` (phpunit.xml), not SQLite, because row locks matter.
- Lint: `vendor/bin/pint` (PHP); `npm run build` runs `tsc` as the TS check
- Build frontend: `npm run build`
- Migrate/seed: `php artisan migrate`, `php artisan migrate:fresh --seed`
- Local MCP server: `php artisan mcp:start pm` (exact name confirmed in slice 8)

## Per-slice checklist
migrate → Pest suite green → `npm run build` → update PROGRESS.md → git commit.
