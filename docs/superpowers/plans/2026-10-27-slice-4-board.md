# Slice 4: Board and Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** A Kanban board per project with configurable columns, drag between and within columns, and filters (assignee, type, priority, label) plus quick search, available through web, API and MCP.

**Spec:** SPEC.md

## Decisions
- One project-wide `issues.rank` (bigint, gaps of 1024) orders issues on the board and, in slice 5, in the backlog. New issues go to the bottom. A move takes the midpoint of its neighbours; when there's no gap left, the whole project is renumbered under the project row lock.
- `RankService::place(Issue, Builder $siblings, int $position)` is shared by board moves and, later, backlog ranking.
- Move input: `status` (column name, optional) plus either `position` (0-based index in the destination column as the unfiltered board shows it; default is the bottom) or `after` (key of the issue to sit after). The web board uses `after` so drags still work while filters are on. Only status changes are logged; reordering isn't.
- The board hides epics; sub-tasks show as cards. Board filters run server-side through `IssueService::filter()` (reused by the list view in slice 6): assignee (email or `unassigned`), type, priority, label, q (title text or key). Capped at 500 issues; the response says when it's truncated.
- Columns: admins create, rename, reorder (position) and delete. Deleting a column that has issues needs `move_issues_to` (the error lists the columns). A project keeps at least one column. Names are unique per project (case-insensitive), up to 50 characters. The last column counts as "done" for later slices.

## Interfaces
- `RankService::place(Issue $issue, Builder $siblings, int $position): void`, `RankService::bottom(Project): int`
- `ColumnService`: `create(Project, array)`, `update(Column, array)`, `delete(Column, ?string $moveTo)`, `resolve(Project, string)`
- `BoardService`: `board(Project, array $filters): array`, `move(Issue, User, array $input): Issue`
- `IssueService::filter(Builder, Project, array $filters): Builder`
- API: GET projects/{key}/board, GET/POST projects/{key}/columns, PATCH/DELETE projects/{key}/columns/{name}, POST issues/{key}/move
- MCP: get_board, move_issue, list_columns, create_column, update_column, delete_column

## Tasks (TDD)
- [ ] Tests: BoardApiTest, ColumnApiTest, BoardMcpTest, BoardWebTest
- [ ] Migration (rank + backfill), RankService, ColumnService, BoardService, filter
- [ ] API + MCP
- [ ] Projects/Show becomes the board (dnd-kit), filters bar, column management on settings
- [ ] Browser check (drag, filters), Pest, build, PROGRESS.md, commit
