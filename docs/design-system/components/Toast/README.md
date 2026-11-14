# Toast

Confirms a single change and offers Undo, instead of asking "Are you sure?" first.

**When:** every single change a person can reverse (move, status, assignee, field edit, deleting one issue or comment) and every bulk edit after its preview. Rule warnings (blocked, failing tests, WIP limit, plan limit) are not toasts: they ask first through the warning confirm.

**Anatomy:** `ink` pill (16px radius) with a 34px icon tile, message (`text-md` 600), optional amber Undo, dismiss button. Bottom-centre, 22px from the edge; at most 3 stacked.

**Variants:** plain; `toast--good` (amber icon tile for wins); `toast--bad` (failures, with Retry instead of Undo). When Undo can't run because the issue changed since (someone else or an agent edited it), the toast becomes `toast--bad` and says what changed: "Can't undo: Sam moved PM-142 to Done".

**How Undo works:** it makes a new change in the opposite direction through the same rules and permissions, logged like any change. It never overwrites someone else's newer change.

**Timing:** 6s with Undo, 4.2s without; pauses while hovered or focused. Rises in 300ms `ease-out`.

**A11y:** `role="status"` (polite). Undo is keyboard reachable; ⌘Z / Ctrl+Z also undoes the last change while the toast is visible.
