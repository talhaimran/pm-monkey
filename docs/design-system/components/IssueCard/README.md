# IssueCard

The unit of work on the board: one issue, scannable in four lines.

**Anatomy:**
1. Top row: type chip (26px, tinted by the type's colour: Task slate, Story cobalt, Bug coral, Epic amber, Sub-task sky, Initiative sand, Spike teal, Incident bad), issue key (`text-2xs`, `muted`), optional flag (Blocked `bad` circle-slash, At risk `warn` triangle; how these show is decided later), ghost menu button.
2. Title: `text-md` 600, max 2 lines, then ellipsis.
3. Meta: labels / sub-issue count in `muted`, one line.
4. Foot: estimate in the project's unit ("3 pts" or "6 h"), priority bars, due date (turns `t-warn` within 2 days, `t-bad` when overdue), assignee avatar (xs; people only, never an agent).

**States:** rest (`shadow-xs`), hover (`line-2` border), focus-visible ring, `is-dragging` (lift to 104%, slight tilt, `shadow-lift`), `is-ghost` (dashed placeholder where it will land), `is-closed` (Won't do / Duplicate / Cannot reproduce: dimmed until cleared), `icard--compact` (title and key only; automatic above 12 cards in a column, each person can override).

**Types:** `icard--task`, `icard--story`, `icard--bug`, `icard--epic`, `icard--subtask`, `icard--initiative`, `icard--spike`, `icard--incident`. A custom type colour sets `--c` / `--t` on the card.

**Interaction:** click or Enter opens the peek drawer; drag with pointer or long-press (300ms) on touch. On drop the card settles and a toast offers Undo. Rule warnings (blocked, WIP limit) ask first through the warning confirm.

**Edge cases:** long single words wrap (`overflow-wrap: anywhere`); no assignee shows an empty dashed avatar; no estimate shows "–".
