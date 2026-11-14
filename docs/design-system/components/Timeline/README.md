# Timeline

Epics and initiatives laid out over weeks or months, with progress, today's line, and releases.

**Anatomy:** `.tl` with a month header (`.tl__months`), then one `.tl__row` per item: `.tl__label` (type icon, key, title; children indented with `is-child`) and `.tl__lane` with a `.tl__bar`. The bar takes the issue type colour through `--c`/`--t`, its position through `--x` and `--w`, and progress (done ÷ total in the project's unit) through `--p`. `.tl__today` is a coral line; `.tl__release` diamonds mark release dates.

**Behaviour:** drag a bar to move its dates, drag its ends to resize; each is a single change with Undo. Bars without dates sit in an "Unscheduled" group at the bottom. Zoom: Weeks, Months, Quarters (a `.seg`).

**Rules:** a parent's bar spans its children unless it has its own dates; dates outside the parent's show a warn dot.

**A11y:** each bar is a button that opens the peek drawer; its label gives dates and progress ("PM-90 Webhooks, 3 Nov to 12 Dec, 40% done"). Dates can be changed from the drawer, so dragging is never the only way.

**Mobile:** the label column collapses to keys; the lane scrolls sideways.
