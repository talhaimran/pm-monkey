# CommandPalette

One place for actions, pages and issues. **Decided later:** the spec defers the palette and the shortcut registry as a cross-cutting requirement; this page is the design to use when it's built.

**Anatomy:** 62px input (`text-xl`) with search icon and an `esc` key cap → grouped results (Actions, Issues, Pages, People), each row with a 32px icon tile, title, `muted` subtitle and either its shortcut or a go-arrow → footer that teaches the keys.

**Behaviour:** fuzzy search; recent items first when empty; the selected row has `well` fill and shows its arrow. ↑/↓ move, Enter runs, Esc closes and returns focus. Every command shown here also shows its shortcut, if it has one.

**Motion:** in 380ms (drop 14px, 97% → 100%, `ease-overshoot`), rows rise 8px with a 20ms stagger; out 160ms. Width `palette-w` (640px), 10vh from the top.

**A11y:** Headless UI `Combobox` inside a `Dialog`: a combobox input controlling a `listbox`, with `aria-activedescendant` tracking the selected option.
