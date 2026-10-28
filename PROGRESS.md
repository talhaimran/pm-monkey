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
