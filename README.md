# PM Monkey

A lightweight, self-hosted, Jira-like project tracker that people use through a web UI and AI agents use through an MCP server and a documented REST API.

- **Projects** with short keys (`WEB`) and issues numbered per project (`WEB-12`). Roles per project: Admin, Member, Viewer.
- **Issues:** Task, Bug, Story and Epic types, priorities, assignee, labels, story points, due dates, epics and one level of sub-tasks. Descriptions and comments are Markdown, and an activity log records who changed what and whether it came via web, API or MCP (with the token name).
- **Kanban board** with drag and drop and per-project columns. **Backlog** with drag-to-rank. **Sprints:** create, start and complete, with unfinished issues moved to the backlog or the next sprint.
- **List view**, cross-project **search**, and a **dashboard** with my issues, status counts and sprint progress.
- **For agents:** 36 MCP tools over HTTP or stdio, a REST API with OpenAPI docs, project-scoped read-only or read-write tokens, Idempotency-Key, per-token rate limits, bulk create/update, and errors that list the valid options.

See [AGENTS.md](AGENTS.md) for how agents should use it. [SPEC.md](SPEC.md) is the product spec, and [PROGRESS.md](PROGRESS.md) logs the decisions made while building it.

## Requirements

- PHP 8.3+ (tested on 8.5) with `pdo_mysql`, `mbstring`, `intl`, `bcmath`
- Composer 2
- MySQL 8+
- Node 20+ and npm

On macOS: `brew install php composer mysql node && brew services start mysql`.

## Setup

```bash
git clone <this repo> pm-monkey && cd pm-monkey
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the databases and a user that matches `.env` (`pm_monkey` / `pm_monkey` by default; change both for anything beyond local use):

```bash
mysql -uroot -p -e "CREATE DATABASE pm_monkey; CREATE DATABASE pm_monkey_test;
  CREATE USER 'pm_monkey'@'localhost' IDENTIFIED BY 'pm_monkey'; CREATE USER 'pm_monkey'@'127.0.0.1' IDENTIFIED BY 'pm_monkey';
  GRANT ALL ON pm_monkey.* TO 'pm_monkey'@'localhost'; GRANT ALL ON pm_monkey_test.* TO 'pm_monkey'@'localhost';
  GRANT ALL ON pm_monkey.* TO 'pm_monkey'@'127.0.0.1'; GRANT ALL ON pm_monkey_test.* TO 'pm_monkey'@'127.0.0.1';"
```

Migrate and load the demo workspace:

```bash
php artisan migrate:fresh --seed
```

The seed prints a **demo API token** and creates:

| Login | Password | Role in WEB |
|---|---|---|
| demo@example.com | password | Admin |
| alex@example.com | password | Member |
| sam@example.com | password | Viewer |

It also creates project **WEB** (about 30 issues, labels, epics, sub-tasks, an active sprint and a planned one). Use `php artisan migrate` without `--seed` for an empty install, then register at `/register`.

### Environment

| Variable | Default | Purpose |
|---|---|---|
| `DB_*` | mysql / `pm_monkey` | Database connection. Tests use `pm_monkey_test` (see `phpunit.xml`). |
| `APP_URL` | `http://localhost` | Base URL used in links and printed endpoints. Set it to where you serve the app. |
| `MAIL_MAILER` | `log` | Invitations are written to `storage/logs/laravel.log`. Set SMTP to really send them. |
| `PM_API_RATE_LIMIT` | `120` | Requests per minute per API token (REST and MCP over HTTP). |
| `PM_IDEMPOTENCY_TTL_HOURS` | `24` | How long a response is kept for `Idempotency-Key` replay. |
| `PM_INVITE_RATE_LIMIT` | `30` | Invitation emails per hour per user (web and API). |
| `PM_MCP_TOKEN` | — | Token the stdio MCP server acts as. Usually set in the MCP client's config, not `.env`. |

## Running

```bash
composer run dev          # app server, queue worker and Vite with hot reload
```

Or separately: `php artisan serve` and `npm run dev`.

For production, run `npm run build` and serve `public/` with any PHP host over HTTPS. Then:
- Set `APP_ENV=production` and `APP_DEBUG=false`. With debug on, API and MCP errors include internal details.
- Set `SESSION_SECURE_COOKIE=true`.
- Configure real SMTP so verification and invitation emails arrive.
- Remove the `register` routes in `routes/auth.php` if strangers shouldn't be able to sign up. Registration is open by default.

The app is at <http://localhost:8000>, and the API docs are at <http://localhost:8000/docs/api> (OpenAPI JSON at `/docs/api.json`).

Keyboard shortcuts: **c** creates an issue (board, backlog and list), **/** focuses search, **Esc** closes dialogs. The sun/moon button toggles light, dark and system themes.

## Tests and lint

```bash
php artisan test          # Pest: Feature + Integration suites (MySQL test DB)
vendor/bin/pint           # PHP code style
npm run build             # TypeScript check + production build
```

`tests/Integration` spawns the real stdio MCP server, so it uses `DatabaseTruncation` instead of transactions.

## API tokens

Create tokens at **user menu → API tokens** (`/settings/tokens`):

- **Read-only** or **Read and write**. Read-only tokens (and Viewers) can never change anything.
- **All my projects** or a chosen subset. A scoped token can't see or create anything outside its projects.
- **Expiry:** 30 days, 90 days (the default), 1 year or never. Expired tokens stop working on REST and both MCP transports.
- The token is shown once. The page lists each token's scope, **last used** time and expiry, and lets you **revoke** it.

Every REST and MCP call uses the token as `Authorization: Bearer <token>`.

## REST API

The base URL is `/api/v1`, documented at `/docs/api`. Highlights:

- Issues are addressed by key everywhere: `GET /api/v1/issues/WEB-12`.
- Fields take names, not ids: `status` is a column name, `assignee` an email, `labels` label names, `epic`/`parent` issue keys, `sprint` a sprint name. Wrong values return 422 with the valid options listed.
- Destructive calls need `"confirm": true`: deleting a project or issue, and completing a sprint.
- **Idempotency:** send `Idempotency-Key: <uuid>` on any POST. A retry with the same key and body returns the original response (header `Idempotent-Replayed: true`). The same key with a different body gets 422.
- **Bulk:** `POST /api/v1/projects/WEB/issues/bulk` and `PATCH /api/v1/issues/bulk`, up to 50 items, all or nothing.
- **Rate limit:** `PM_API_RATE_LIMIT` per minute per token; a 429 says when to retry.

```bash
TOKEN=...   # from the seed output or /settings/tokens
curl -s http://localhost:8000/api/v1/me/issues -H "Authorization: Bearer $TOKEN"
curl -s -X POST http://localhost:8000/api/v1/projects/WEB/issues \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Idempotency-Key: $(uuidgen)" \
  -d '{"title":"Fix footer on iOS","type":"bug","priority":"high","status":"To Do","labels":["frontend"]}'
```

## MCP server

The same server (`App\Mcp\Servers\PmServer`, 36 tools plus `issue://{key}` and `project://{key}` resources) is available over two transports. Both act as the owner of an API token, so that token's read-only and project scope apply.

| Transport | How it runs | Auth |
|---|---|---|
| HTTP | `POST http://localhost:8000/mcp` (the app must be running) | `Authorization: Bearer <token>` header |
| stdio | `php artisan mcp:start pm` (the client starts it) | `PM_MCP_TOKEN=<token>` environment variable |

Use the **absolute path** to `php` (`which php`, e.g. `/opt/homebrew/bin/php`) and to `artisan`, because desktop apps don't inherit your shell `PATH`. The stdio server needs MySQL reachable, but not `php artisan serve`.

### Claude Code

stdio (Claude Code starts the server itself):

```bash
claude mcp add pm-monkey --env PM_MCP_TOKEN=<token> -- /opt/homebrew/bin/php /path/to/pm-monkey/artisan mcp:start pm
```

HTTP (the app must be running):

```bash
claude mcp add --transport http pm-monkey http://localhost:8000/mcp --header "Authorization: Bearer <token>"
```

Add `--scope user` to make it available in every project, or `--scope project` to write a shareable `.mcp.json`. For a shared `.mcp.json`, reference an environment variable instead of pasting the token:

```json
{
  "mcpServers": {
    "pm-monkey": {
      "type": "http",
      "url": "http://localhost:8000/mcp",
      "headers": { "Authorization": "Bearer ${PM_MONKEY_TOKEN}" }
    }
  }
}
```

Check with `claude mcp list`, or `/mcp` inside Claude Code.

### Claude Desktop

Edit `claude_desktop_config.json` (macOS: `~/Library/Application Support/Claude/`, Windows: `%APPDATA%\Claude\`), then fully quit and reopen Claude Desktop.

stdio (recommended):

```json
{
  "mcpServers": {
    "pm-monkey": {
      "command": "/opt/homebrew/bin/php",
      "args": ["/path/to/pm-monkey/artisan", "mcp:start", "pm"],
      "env": { "PM_MCP_TOKEN": "<token>" }
    }
  }
}
```

HTTP, through the `mcp-remote` bridge (a third-party npm package, needs Node), which forwards stdio to `/mcp` with your header:

```json
{
  "mcpServers": {
    "pm-monkey-http": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "http://localhost:8000/mcp", "--header", "Authorization:${PM_AUTH}"],
      "env": { "PM_AUTH": "Bearer <token>" }
    }
  }
}
```

(The header is written without a space after the colon and the value comes from `env`. This avoids an argument-quoting problem in some clients.)

### Debugging

```bash
php artisan mcp:inspector pm        # opens the MCP Inspector against the stdio server
```

If the stdio server exits at once with "PM_MCP_TOKEN is missing or invalid", the token is wrong or revoked. Create a new one at `/settings/tokens`. Raw HTTP check:

```bash
curl -s -X POST http://localhost:8000/mcp -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json, text/event-stream" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"project_summary","arguments":{"project":"WEB"}}}'
```

## Architecture

Laravel 13 with Inertia, React, TypeScript and Tailwind (Breeze). Sanctum handles tokens, laravel/mcp the MCP server, Scramble the OpenAPI docs, dnd-kit drag and drop, and Pest the tests.

All business logic lives in `app/Services`. Web controllers (`app/Http/Controllers/Web`), API controllers (`app/Http/Controllers/Api/V1`) and MCP tools (`app/Mcp/Tools`) are thin layers over the same services, Form Requests, Policies and API Resources. Conventions are in [CLAUDE.md](CLAUDE.md).

## Known gaps

Out of scope by design: a workflows engine, custom fields, plugins, time tracking, SSO, email notifications and real-time collaboration (other people's changes appear when you reload).

Also not done:
- The board and backlog load at most 500 and 1000 issues, with a notice when they're cut off.
- There's no in-app list of keyboard shortcuts.
- MCP tools don't take an idempotency key; use the REST API when you need replay safety.
