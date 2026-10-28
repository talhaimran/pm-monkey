# AGENTS.md: using PM Monkey as an AI agent

This guide is for AI agents (Claude, GPT, Qwen, local models) that **use** PM Monkey through MCP or the REST API. To **change PM Monkey's code**, read [CLAUDE.md](CLAUDE.md) and [SPEC.md](SPEC.md) instead.

Connection setup (tokens, Claude Code, Claude Desktop) is in the [README](README.md#mcp-server).

## Ground rules

- **Address things by name, never by id.** Projects by key (`WEB`), issues by key (`WEB-12`), statuses by column name (`"In Progress"`), people by email, labels by name, sprints by name. Matching is case-insensitive.
- **Wrong input tells you the fix.** An unknown status, assignee, label, sprint, project or sort returns an error that lists the valid options, e.g. `Unknown status "Doing". Valid statuses for WEB: To Do, In Progress, In Review, Done.` Retry with one of those values; don't guess again.
- **Destructive calls need `confirm: true`:** `delete_project`, `delete_issue`, `complete_sprint`. Ask the human first unless they clearly asked for it.
- **Your token sets your limits.** A read-only token or a Viewer role can't write. A project-scoped token only sees its projects; anything else looks like it doesn't exist. Errors say which limit you hit.
- **Results are a one-line summary followed by compact JSON.** Lists are paginated (`page`, `per_page` up to 50); the summary says when to pass `page=N`.
- **Everything you do is logged** on the issue as "via mcp" with your token's name, so humans can see what an agent changed.

## Data model

```
Project (key WEB, name, description)
├── members: User + role (admin | member | viewer)
├── columns: ordered board columns = the valid issue statuses; the LAST column means "done"
├── labels: name + color, unique per project
├── sprints: name, goal, start/end date, state planned → active → completed (one active at a time)
└── issues: WEB-1, WEB-2, … (numbers are never reused)
      title, description (Markdown)
      type: task | bug | story | epic
      priority: lowest | low | medium | high | highest
      status (column), assignee (member), reporter, labels[], story_points, due_date (YYYY-MM-DD)
      epic → an Epic in the same project (epics can't have an epic)
      parent → makes it a sub-task; one level only (a sub-task can't have sub-tasks; epics can't be sub-tasks)
      sprint → a planned or active sprint, or null = backlog
      rank (board/backlog order), comments[] (Markdown), activity[] (who changed what, via web | api | mcp)
```

Permissions: **Admins** manage settings, members, columns and the project itself. **Members** create and edit issues, comments, labels and sprints. **Viewers** only read. Comments are edited by their author and deleted by the author or an admin.

## Tools

| Tool | What it does | Required | Optional |
|---|---|---|---|
| `list_projects` | Projects you're in, with your role | | page, per_page |
| `get_project` | One project with members | project | |
| `create_project` | Create a project; you become admin | key, name | description |
| `update_project` | Rename or edit the description (admin) | project | name, description |
| `delete_project` | Delete a project and its issues (admin) | project, confirm | |
| `create_issue` | Create an issue, sub-task (`parent`) or epic | project, title | description, type, priority, status, assignee, labels, story_points, due_date, epic, parent, sprint |
| `get_issue` | Issue with sub-tasks, epic issues, comments | issue | |
| `update_issue` | Change only the fields you send; `null` clears | issue | title, description, type, priority, status, assignee, labels, story_points, due_date, epic, parent, sprint |
| `delete_issue` | Delete an issue and its sub-tasks | issue, confirm | |
| `get_issue_activity` | Change history, newest first | issue | page, per_page |
| `add_comment` | Markdown comment | issue, body | |
| `update_comment` | Edit your own comment | comment_id, body | |
| `delete_comment` | Delete your comment (admins: any) | comment_id | |
| `list_labels` / `create_label` / `update_label` / `delete_label` | Manage labels (by name) | project (+ name or label) | color, name |
| `get_board` | Columns with issue cards in rank order (epics hidden) | project | assignee, type, priority, label, q, sprint |
| `move_issue` | Change status and/or position on the board | issue | status, position, after |
| `list_columns` | Columns (= statuses) with counts | project | |
| `create_column` / `update_column` / `delete_column` | Manage columns (admin) | project (+ name or column) | position, name, move_issues_to |
| `list_sprints` | Sprints with dates and points | project | state, page |
| `create_sprint` | Planned sprint (default name "WEB Sprint N") | project | name, goal, start_date, end_date |
| `update_sprint` | Rename or re-date (not completed sprints) | project, sprint | name, goal, start_date, end_date |
| `delete_sprint` | Delete a planned sprint; its issues go to the backlog | project, sprint | |
| `start_sprint` | Start a planned sprint (default today, +14 days) | project, sprint | start_date, end_date |
| `complete_sprint` | Close the active sprint and move unfinished issues | project, sprint, move_open_issues_to, confirm | |
| `get_backlog` | Open sprints and backlog in rank order | project | |
| `rank_backlog` | Reorder within, or move between, backlog and sprints | issue | sprint, position, after |
| `search_issues` | Filter and sort issues across projects | | q, project, assignee, type, priority, label, sprint, sort, page, per_page |
| `my_issues` | Your open assigned issues, by priority then due date | | limit |
| `project_summary` | Counts per status and type, open/overdue, sprint progress | project | |
| `bulk_create_issues` | Up to 50 creates, all or nothing | project, issues[] | |
| `bulk_update_issues` | Up to 50 updates by key, all or nothing | updates[] | |

**Filters:** `assignee` is an email or `"unassigned"`, and `sprint` is a name, `"active"` or `"backlog"`. `q` matches title text or an exact key. **Sort** (`search_issues`) takes one of `key, title, type, priority, status, assignee, story_points, due_date, created, updated`; prefix with `-` for descending (default `-key`, newest first).

**Placement** (`move_issue`, `rank_backlog`): `position` is a 0-based index in the destination (default: bottom), or use `after: "WEB-7"` to sit directly below that issue. Send one or the other, not both.

**Resources:** `project://WEB` (members, statuses, labels: everything valid for issue fields) and `issue://WEB-12`. Read `project://KEY` once before creating issues in an unfamiliar project.

## Common workflows

**Get oriented**
1. `list_projects`, then read `project://WEB` (or `get_project` + `list_columns` + `list_labels`).
2. `project_summary {project: "WEB"}` for status counts and the active sprint.
3. `my_issues` for what's assigned to you.

**File a bug**

```json
{"name": "create_issue", "arguments": {
  "project": "WEB", "title": "Checkout button unresponsive on Safari", "type": "bug", "priority": "high",
  "labels": ["frontend"], "assignee": "alex@example.com",
  "description": "**Steps**\n1. Add item\n2. Click Checkout on Safari 18\n\nNothing happens."}}
```

**Work an issue:** `move_issue {issue: "WEB-12", status: "In Progress"}`, then `add_comment` with progress notes, then `move_issue {issue: "WEB-12", status: "Done"}` (or whatever the last column is called).

**Break down a story:** `create_issue` once per sub-task with `parent: "WEB-7"`. Don't nest deeper, since sub-tasks can't have sub-tasks. For many at once, use `bulk_create_issues`.

**Plan a sprint**
1. `get_backlog {project: "WEB"}` to see what's unscheduled, in rank order.
2. `create_sprint {project: "WEB", goal: "…"}` returns e.g. "WEB Sprint 3".
3. `rank_backlog {issue: "WEB-20", sprint: "WEB Sprint 3"}` for each pick, or `bulk_update_issues` with `sprint` on many.
4. `start_sprint {project: "WEB", sprint: "WEB Sprint 3"}`. This fails if another sprint is active; the error names it.

**Close a sprint:** check `project_summary`, confirm with the human, then
`complete_sprint {project: "WEB", sprint: "WEB Sprint 3", move_open_issues_to: "backlog", confirm: true}`. Done issues stay with the sprint; the rest move.

**Triage:** `search_issues {project: "WEB", assignee: "unassigned", sort: "-priority"}`, then `bulk_update_issues` to set assignees and priorities in one call.

**Find something:** `search_issues {q: "checkout"}` searches all your projects. `search_issues {q: "WEB-12"}` matches the key exactly.

## Example calls

**MCP over HTTP** (the same JSON-RPC body works over stdio, one message per line):

```bash
curl -s -X POST http://localhost:8000/mcp \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json, text/event-stream" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"move_issue","arguments":{"issue":"WEB-12","status":"In Review"}}}'
```

Result (abridged):

```json
{"jsonrpc":"2.0","id":1,"result":{"isError":false,"content":[{"type":"text",
  "text":"Moved WEB-12 to In Review.\n\n{\"key\":\"WEB-12\",\"status\":\"In Review\",…}"}]}}
```

An error comes back as `isError: true` with the explanation in the text:

```json
{"result":{"isError":true,"content":[{"type":"text",
  "text":"Unknown status \"Review\". Valid statuses for WEB: To Do, In Progress, In Review, Done."}]}}
```

**REST** (full reference at `/docs/api`):

```bash
# Create with retry safety
curl -s -X POST http://localhost:8000/api/v1/projects/WEB/issues \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Idempotency-Key: 6f1c…" \
  -d '{"title":"Add CSV export","type":"story","story_points":3,"sprint":"WEB Sprint 1"}'

# Bulk update
curl -s -X PATCH http://localhost:8000/api/v1/issues/bulk \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"updates":[{"key":"WEB-3","status":"Done"},{"key":"WEB-4","assignee":"alex@example.com","priority":"high"}]}'

# Destructive: must confirm
curl -s -X DELETE http://localhost:8000/api/v1/issues/WEB-9 \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"confirm":true}'
```

REST errors use HTTP status codes:

| Status | Meaning |
|---|---|
| 401 | Missing or bad token |
| 403 | Role, read-only token or scope; the message says which |
| 404 | Not found or not visible to you; the message lists what you can see |
| 422 | Invalid input; `errors.field` lists the valid options. Bulk errors are keyed `issues.3.status` / `updates.1.key` |
| 429 | Rate limited; wait for `Retry-After` |

## Etiquette for agents

- Read before you write: `get_issue` before editing someone else's issue, and `get_issue_activity` if it changed recently.
- Prefer one bulk call over many single calls, and send an `Idempotency-Key` on REST creates you might retry.
- Keep comments short and factual, and say what you changed and why. Humans see your token name on every change.
- Never complete sprints, delete things, or reassign other people's work unless a human asked you to.
