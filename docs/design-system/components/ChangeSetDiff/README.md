# Change-set diff

A preview of several changes at once, applied together or not at all. Used for bulk edits, multi-item deletes, imports, and agent change sets.

**When:** any change touching more than one issue or field in one go. Single changes skip it and use a toast with Undo.

**Anatomy:** `.cset` with a head (what's changing and how many: "Move 6 issues to Sprint 15"), then one `.cset__row` per change: a kind icon (edit, create, delete, link), the field, the value from → to, and the issue key. Footer outside the box: Cancel and the action restated ("Apply 6 changes").

**States:** `is-conflict` (warn tint) for a row whose issue changed since the preview was made, with the reason; the person can drop that row or apply the rest. `is-done` rows after applying, with a single Undo for the whole set.

**Rules:** rows with rule warnings (blocked, WIP) show them inline; there's no second confirm. Deletes are soft and can be restored from Deleted issues.

**A11y:** rendered as a list; each row reads as one sentence ("PM-142: Sprint, Sprint 14 to Sprint 15"). Over 50 rows, the list is grouped by field with counts.
