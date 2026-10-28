Build a lightweight, self-hosted, Jira-like project management tool in this directory. It must be fully usable by humans (web UI) AND by AI models (Claude, GPT, Qwen, local LLMs) through an MCP server and a documented REST API.

## Before writing code
1. Save this whole prompt as SPEC.md in the repo root. It is the source of truth.
2. Create CLAUDE.md with the stack, architecture rules, and the commands to run tests, lint, and dev servers. Keep it updated as you go.
3. Check what's installed (php -v, composer, node, npm, mysql). If anything is missing, tell me exactly what to install and stop.
4. Show me a short plan (slices below) and wait for my "go".

## Stack
- Latest stable Laravel (12+), PHP 8.3+, MySQL 8
- Inertia.js + React + TypeScript + Vite + Tailwind (Laravel Breeze, React/TS variant)
- Sanctum personal access tokens with abilities for API/agent auth
- Official laravel/mcp package for the MCP server
- dedoc/scramble for OpenAPI docs
- dnd-kit for drag and drop
- Pest for tests
- No other heavy dependencies without asking me

## Architecture rules
- All business logic lives in app/Services or app/Actions. Inertia controllers, API controllers (/api/v1), and MCP tools are thin and call the same services. Never duplicate logic.
- Form Requests for validation, Policies for authorization, API Resources for JSON
- PHP backed enums for issue type, priority, and roles
- DB transactions for multi-step writes; proper indexes; no N+1 (Model::preventLazyLoading() outside production)

## Features (MVP)
- Projects with a short KEY; issues numbered per project (WEB-1, WEB-2), safe under concurrency
- Issues: title, Markdown description, type (Task/Bug/Story/Epic), status (board column), priority (Lowest to Highest), assignee, reporter, labels, story points, due date, parent epic, one level of sub-tasks, Markdown comments
- Activity log per issue: who changed what, when, and whether via web or via API/agent (with token name)
- Kanban board: default columns To Do / In Progress / In Review / Done, configurable per project; drag between and within columns; filters (assignee, type, priority, label) and quick search
- Backlog with drag-to-rank; sprints (create, set dates, start, complete); one active sprint per project; on completion, unfinished issues go to backlog or next sprint (user chooses)
- Paginated, sortable list view with the same filters
- Per-project roles: Admin, Member, Viewer; invite by email (Mail log driver)
- Dashboard: "My issues" across projects; per-project status counts and sprint points progress
- Keyboard shortcuts (c = create, / = search, Esc = close), responsive, light/dark mode
- Out of scope: workflows engine, custom fields, plugins, time tracking, SSO, email notifications, real-time collaboration

## AI agent access (required)
- MCP: register a local (stdio, Artisan) server and a web (HTTP, Sanctum bearer) server. BEFORE writing MCP code, read the installed laravel/mcp source in vendor/ and its official docs, and follow them exactly. Do not invent APIs.
- MCP tools for every core action (CRUD for projects, issues, sub-tasks, comments, labels, sprints, columns; move_issue; rank_backlog; start/complete sprint; search_issues; my_issues; project_summary), each with a clear name, one-line description, and strict input schema with enums
- MCP resources like issue://WEB-12 and project metadata
- Results: concise JSON plus a short summary, paginated and capped
- Sanctum tokens: created in a settings page, scoped to projects and read-only or read-write, revocable, show last used
- Scramble docs at /docs/api and /docs/api.json
- Issues addressable by key (WEB-12) everywhere; accept names where sensible (assignee email, column name) and return errors that list valid options and explain the fix
- Bulk create/update, Idempotency-Key support on creates, per-token rate limiting
- Destructive actions (delete project/issue, complete sprint) require confirm: true via API/MCP; Viewers and read-only tokens can never write

## How to work
Build in vertical slices. Each slice is complete end to end (migration, model, service, policy, request, web UI, API, MCP tool, Pest tests) before starting the next:
1. Scaffold: Laravel, Breeze, packages, MySQL config, CLAUDE.md
2. Projects and roles
3. Issues, key generation, comments, labels, sub-tasks, activity log
4. Board and columns with drag and drop
5. Backlog and sprints
6. List view, search, dashboard
7. Sanctum tokens UI, idempotency, rate limiting, bulk ops
8. MCP servers (local and web), tested by actually calling tools
9. Seeders: demo user, one project, ~30 issues, active sprint, one demo API token printed to console
10. README (setup, .env, migrate, seed, dev/build, connecting Claude Desktop and Claude Code to both MCP transports) and AGENTS.md (data model, tools, common workflows, example calls)

After each slice: run migrations, run the Pest suite, run npm run build, fix all failures, then git commit with a clear message. Don't move on with failing tests. Keep a short progress log in PROGRESS.md.

## Rules
- Write complete files; no TODOs or placeholders
- If something is ambiguous, pick the simplest reasonable option, note it in PROGRESS.md, and keep going
- Ask me only when blocked (missing software, credentials, or a decision that changes the spec)
- At the end, give me a summary of what works, how to run it, and any known gaps
