# BoardColumn

A board column: header, summary, cards. A column shows the issues whose status maps to it.

**Anatomy:** status dot (9px rounded square in the status colour) + name + count pill + ghost add button; a summary line with the sum of estimates in the project's unit (`text-xl`, "11 pts" or "18 h") and a `muted` note (issue count, WIP limit); the card stack with `space-2` gaps.

**Colour:** from the status category by default (`col--todo` sky, `col--progress` amber, `col--done` good, with a faint `good` wash). A status with a custom colour sets `--c` on the column instead.

**States:** `is-over` while a card is dragged over it (cobalt 7% wash, cobalt border); WIP limit exceeded turns the count `warn` ("4 / 3") and moving a card in asks first (warning confirm); empty shows a dashed drop target that says what goes here and how to add it ("Nothing in review. Drag an issue here.").

**Behaviour:** columns are at least `column-min-w` (198px), scroll horizontally with snap. Above 12 cards, cards switch to the compact density; above 20, the column shows the first 20 and "Show N more". Totals and counts update immediately on drop, and back on Undo. Cards moved by someone else slide in without taking focus.
