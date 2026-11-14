# Pill

Compact label for status, signals and tags.

**Anatomy:** optional 6px dot (`c-*`) + label (`text-xs`, 600, `t-*`) on a tint of the same hue (14% light, 18% dark). Height `pill-h` (24px).

**Status:** colour comes from the status category: To Do `pill--todo` (sky), In Progress `pill--progress` (amber), Done `pill--done` (good). A status with a custom colour uses a palette class (`pill--slate`, `--sky`, `--amber`, `--cobalt`, `--coral`, `--teal`, `--sand`, `--good`) or sets `--c` / `--t`. The label is always the status's own name.

**Signals:** `pill--good`, `pill--warn`, `pill--bad` for outcomes and alerts. `pill--blocked` and `pill--risk` stay as aliases of bad and warn until the spec decides how Blocked and At risk show.

**Tags:** labels use the hue the label was given, without a dot.

**Rules:** text always in the `t-*` token (5:1+); never `c-*` text. A pill is not a button; when it changes a value (status menu), wrap it in a button with an accessible name like "Status: In review".
