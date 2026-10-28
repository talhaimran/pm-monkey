# Slice 3: Issues, Comments, Labels, Sub-tasks, Activity Log

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (native). Steps use checkbox (`- [ ]`) syntax.

**Goal:** Issues numbered per project (WEB-1…), safe under concurrency, with comments, labels, epic links, one level of sub-tasks, and an activity log recording who changed what and whether it came via web, API or MCP (with the token name).

**Architecture:** `IssueService`, `CommentService`, `LabelService` and `ActivityLogger` hold the logic. Every interface accepts the same input shape (names and keys, not IDs), so web, API and MCP share one Form Request each.

**Spec:** SPEC.md

## Decisions
- Status is a board column. A `columns` table is created now (project_id, name, position) and seeded with To Do / In Progress / In Review / Done on project create. Column CRUD and the board UI come in slice 4.
- Issue numbers: `projects.issue_counter` incremented under `lockForUpdate` in the create transaction, with unique(project_id, number) as a backstop.
- Two link fields: `epic_id` (target must be an Epic; Epics can't have one) and `parent_id` (sub-task). Sub-tasks are one level deep: the parent can't be a sub-task, a sub-task can't have sub-tasks, and Epics can't be parents or sub-tasks.
- Shared input shape: `status` = column name (case-insensitive), `assignee` = member email (or null), `labels` = label names (must exist; the error lists valid names), `epic`/`parent` = issue key. Unknown values return errors listing valid options.
- Activity rows store `action` (created/updated/commented), `changes` {field: {from, to}} using display values (descriptions truncated to 200 chars), `via` (web|api|mcp) and `token_name`. MCP is flagged by `PmServer::createContext()`; a real personal access token means api; otherwise web.
- Writers (admin/member, write token) can create, update and delete issues and labels. Comments: the author edits; the author or an admin deletes.
- Markdown is rendered server-side with `Str::markdown` (league/commonmark, already installed; raw HTML stripped, unsafe links blocked), only for web props. API and MCP return raw Markdown.
- Issue listing and search come in slice 6. This slice has get-by-key, a project page with recent issues, and an issue page.

## Data
- `columns`: project_id, name, position; unique(project_id, name)
- `projects.issue_counter` unsigned int default 0
- `issues`: project_id, number, title, description, type, priority, column_id, assignee_id?, reporter_id, story_points?, due_date?, epic_id?, parent_id?; unique(project_id, number); index(project_id, column_id), assignee_id, epic_id, parent_id
- `labels`: project_id, name, color; unique(project_id, name). `issue_label` pivot.
- `comments`: issue_id, user_id, body
- `activities`: issue_id, user_id?, action, changes json?, via, token_name?, created_at

## Interfaces
- Enums: `IssueType` (task, bug, story, epic), `IssuePriority` (lowest, low, medium, high, highest)
- `IssueService`: `create(Project, User, array): Issue`, `update(Issue, User, array): Issue`, `delete(Issue): void`, `resolve(User, string key): Issue`
- `CommentService`: `add(Issue, User, string): Comment`, `update(Comment, string)`, `delete(Comment)`
- `LabelService`: `create(Project, array)`, `update(Label, array)`, `delete(Label)`, `resolve(Project, string name)`
- `ActivityLogger`: `log(Issue, User, string action, ?array changes)`
- MCP: create_issue, get_issue, update_issue, delete_issue, add_comment, update_comment, delete_comment, list_labels, create_label, update_label, delete_label, get_issue_activity; resource `issue://{key}`

## Tasks (TDD)
- [ ] Tests: IssueApiTest (keys and concurrency, links, name resolution errors, activity via), CommentApiTest, LabelApiTest, IssueMcpTest, IssueWebTest
- [ ] Migrations, enums, models, services, policies, requests, resources
- [ ] API controllers and routes, MCP tools and resource
- [ ] Inertia: Projects/Show (recent issues + create modal), Issues/Show (fields, sub-tasks, comments, activity), labels on settings
- [ ] Browser check, Pest, build, PROGRESS.md, commit
