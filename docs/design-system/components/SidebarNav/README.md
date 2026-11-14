# SidebarNav

The dark sidebar: workspace switcher, primary destinations, grouped sections, counts.

**Anatomy:** workspace switcher (logo + workspace name + chevron; opens a menu with the person's workspaces, "Create workspace" on Cloud, "Workspace settings" for Owners/Admins) → nav items (42px, icon + label + optional count) → uppercase group labels (`side-faint`, 11px) → nested sub-items under the active item with a left rule.

**States:** rest `side-dim`; hover `side-hover` fill + `on-side`; current `side-pill` fill, `on-side-pill` text, `side-accent` icon, count chip `side-pill-chip`; focus-visible ring.

**Sizes:** `side-w` 256px; collapses to a 92px icon rail (`side-w-rail`) over 600ms `ease-in-out`, labels become tooltips. Under 760px it becomes a sheet sliding from the left.

**Motion:** clicking an item pops its icon (75% → 100%, 550ms overshoot); opening a group unfolds its sub-items (550ms, 40ms stagger). Use `aria-current="page"`.
