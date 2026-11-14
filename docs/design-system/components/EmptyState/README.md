# Empty state

What a page, list, or column shows when there's nothing in it yet, or nothing matches.

**Anatomy:** `.empty` (dashed border) with an icon tile, one line saying what's empty, one line on what to do, and at most one button. Board columns use the smaller `.col__empty` instead.

**Kinds and copy:**
- First use: "No issues yet." / "Create one, or import from Jira or CSV." / Create issue
- No results: "No issues match these filters." / "Try removing a filter." / Clear filters
- Done: "You're all caught up." (no button)
- No access: "You don't have access to this project." / "Ask a project admin to add you."
- Deleted issues: "Nothing deleted in the last 30 days."

**Voice:** state the fact, then the next step. No jokes, no illustrations, no exclamation marks.

**A11y:** the text is real text, not part of an image; the button is the only focusable item.
