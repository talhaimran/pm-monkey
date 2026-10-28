# Slice 5: Backlog and Sprints Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** A backlog with drag-to-rank, and sprints that can be created, dated, started and completed. One sprint is active per project; on completion, unfinished issues go to the backlog or to a planned sprint the user picks.

**Spec:** SPEC.md

## Decisions
- `sprints`: project_id, name (unique per project, case-insensitive; "backlog" and "active" are reserved), goal, start_date, end_date, state (planned|active|completed), started_at, completed_at. A unique nullable `active_project_id` (set while active; a generated column is impossible with cascading FKs) enforces one active sprint at the database level, behind the service check.
- `issues.sprint_id` (nullOnDelete). `sprint` becomes an issue field in the shared input shape (sprint name, or null for the backlog) and is included in activity diffs. Completed sprints can't receive issues.
- "Done" = the issue sits in the project's last column (`Project::doneColumnId()`).
- The backlog shows non-completed sprints (active first, then planned in creation order) and the backlog section (no sprint). Epics are hidden, and done issues are hidden from the backlog section. Everything is ordered by the slice 4 `rank`.
- Rank input: `sprint` (a name, or "backlog"; omit to stay in the current section) plus `position` or `after`, like board moves. Section changes are logged through IssueService::update.
- Sprints are addressed by name or id. Default name: "{KEY} Sprint N". Starting defaults start_date to today and end_date to start + 14 days. Completing needs `confirm: true` and `move_open_issues_to` ("backlog" or a planned sprint name); each moved issue is logged.
- Members and admins (contribute) manage sprints. Only planned sprints can be deleted (their issues return to the backlog). Completed sprints are read-only.
- The board and the issue filter gain `sprint`: a name, "active" or "backlog".

## Interfaces
- `SprintState` enum; `Sprint` model; `Project::doneColumnId(): ?int`
- `SprintService`: create, update, delete, start, complete(Sprint, User, string $moveTo), resolve(Project, string), summary data (issue_count, points_total, points_done)
- `BacklogService`: `backlog(Project): array`, `rank(Issue, User, array $input): Issue`
- API: GET/POST projects/{key}/sprints, GET/PATCH/DELETE projects/{key}/sprints/{sprint}, POST .../start, POST .../complete, GET projects/{key}/backlog, POST issues/{key}/rank
- MCP: list_sprints, create_sprint, update_sprint, delete_sprint, start_sprint, complete_sprint, get_backlog, rank_backlog; `sprint` field on create_issue/update_issue

## Tasks (TDD)
- [ ] Tests: SprintApiTest, BacklogApiTest, SprintMcpAndWebTest
- [ ] Migration, enum, model, SprintService, BacklogService, IssueService sprint field and filter
- [ ] API + MCP
- [ ] Projects/Backlog page (dnd across sections, sprint actions, complete dialog), board sprint filter, nav links
- [ ] Browser check, Pest, build, PROGRESS.md, commit
