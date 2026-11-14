# IssueDrawer

The peek drawer: the issue opened over the board, list view or search so people keep their place. The full issue page (IssuePage) is the canonical view; the drawer is a quick look.

**Anatomy:** header (type chip, eyebrow "key · type · sprint", title `heading-3xl`, status menu (a pill inside a button, "Status: In progress"), "Open full page" and "Copy link" icon buttons, close button) → AI box (`tile-sky`) → 2×2 field grid (assignee, priority, estimate, due) → description → pinned footer with the next action as the primary button.

**AI box:** shows, in this order and only when present: pending approval requests for the issue (ApprovalCard, compact), the latest agent run with its proposed change set (ChangeSetDiff, collapsed to a count), and the "Ask agent" entry. Parts that need an AI provider are hidden without one. It never shows an automatically generated summary.

**Not in the drawer:** child issues, links, attachments, development, test cases, watchers and the full activity live on the full page. The status stepper is decided later; until then status is the pill menu in the header.

**Size:** `drawer-w` (500px), inset 12px from the viewport edges, `radius-sheet`, `shadow-pop`. Under 760px it becomes a bottom sheet.

**Motion:** in 550ms from 70px right (`ease-out-strong`), body rows cascade 40ms apart; out 240ms (`ease-in`). Switching issues while open only re-cascades the body.

**Behaviour:** Headless UI `Dialog`; Esc or the scrim closes; focus returns to the card. Opening it adds `?issue=PM-142` to the current URL, so reload and back work. "Copy link" always copies the full page URL (`/{workspace}/issues/PM-142`).
