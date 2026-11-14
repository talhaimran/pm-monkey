# Accessibility

Target: **WCAG 2.2 AA**. Each rule below is a pass/fail check, and part of "done" for every UI slice (spec 5.1).

## Contrast (measured)

| Pair | Light | Dark | Result |
|---|---|---|---|
| `text` on `card` / `card-2` / `bg` | 16.8 / 15.8 / 15.0 | 15.0 / 14.0 / 16.5 | Pass |
| `muted` on `card` / `card-2` / `bg` | 6.1 / 5.7 / 5.4 | 7.4 / 6.9 / 8.2 | Pass |
| `faint` on `card` / `card-2` / `bg` | 3.9 / 3.7 / 3.5 | 5.2 / 4.8 / 5.7 | **Fails 4.5:1 in light** — non-essential text or 18px+ only |
| `on-primary` on `primary` | 6.3 | 7.8 | Pass |
| `side-dim` on `side` | 6.9 | 6.3 | Pass |
| `side-faint` on `side` | 4.4 | 3.9 | **Fails 4.5:1** — uppercase group labels only |
| Every `t-*` on its own pill tint | 5.1–6.4 | 6.9–7.9 | Pass |
| `c-amber` / `c-sky` / `c-sand` dots on `card` | 2.4 / 2.8 / 2.3 | — | **Below 3:1** — a dot must always sit next to its text label |

## Acceptance criteria

- **Focus:** every interactive element shows a 2px `primary` outline with 2px offset on `:focus-visible`. Pass = tabbing through any page shows a visible ring on every stop.
- **Keyboard:** every action reachable with the keyboard alone. Esc closes the top layer and returns focus to its trigger. (The full keyboard map, including the palette shortcut, is decided later.)
- **Board without a mouse:** a focused card can be moved between and within columns from the keyboard, and each move is announced in a polite live region ("PM-142 moved to In review, position 2 of 5"). The exact keys are decided with the keyboard map.
- **Colour is never alone:** status, type, priority and due state always pair colour with text or a distinct icon shape. Agent actions say "{Owner}'s agent" in words, not only with the agent avatar.
- **Targets:** controls are at least 32px high (24px for pills that are not buttons).
- **Dialogs and drawers:** built on Headless UI `Dialog` (or `<dialog>` with `showModal()`), trap focus, set an accessible name, restore focus on close.
- **Motion:** with `prefers-reduced-motion: reduce`, every animation duration is 0. Pass = toggle the OS setting and nothing slides, scales or types out.
- **Live updates:** toasts use `role="status"`; the Undo button is reachable by keyboard while the toast shows and the toast pauses on focus or hover. Changes from other people and agents never move focus.
- **Text:** never below 11px; issue titles wrap to two lines and truncate with an ellipsis, with the full title in the drawer and on the issue page.
- **Tables:** the audit log and list views use real `<table>` markup with `<th scope>` headers; sortable headers say their sort order.
