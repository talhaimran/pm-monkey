# Motion

pm-monkey moves things to explain change: where a card went, where a panel came from, what just happened. Timings were first measured from the Selvar demo and are pm-monkey's own now. The app animates with **Motion** (`motion/react`) for anything that moves through space and plain CSS for the small stuff; easings are given as CSS `cubic-bezier` values, which Motion takes as arrays (`[0.165, 0.84, 0.44, 1]`).

## Rules

- **Exits are fast, entrances a little slower.** Out in 160–240ms with `ease-in`; in at 450–550ms with `ease-out` or `ease-out-strong`.
- **Stagger lists, briefly.** 40ms between items (`stagger`), 20ms for long lists, and animate at most the first ~18 rows.
- **Overshoot is for small moments only.** `ease-overshoot` on dialogs opening, a nav icon pop, a successful drop. Never on drawers, pages or anything large.
- **Nothing loops on working screens.** No pulsing, rotating or shimmer outside loading states.
- **Never block input.** A person can click or type during any animation; the next action interrupts the current one.
- **Never steal focus.** Animations, including live updates from other people and agents, never move focus, never move a card someone is dragging, and never replace text someone is editing.
- **Reduced motion means zero.** When `prefers-reduced-motion: reduce` is set, every duration is 0 and sequences jump to their end state. Content still appears; only the movement goes.

## Which tool

| Use | For |
|---|---|
| Motion (`motion/react`) | Drag lift and settle, board reordering (`layout`), drawers, dialogs, the palette, toasts, list entrances (`AnimatePresence` for exits), live-update moves |
| Plain CSS transitions | Hover, focus, colour and border changes, the toggle switch, chips |
| View Transitions API | The theme toggle |

## Catalog

| Moment | What moves | Duration | Easing |
|---|---|---|---|
| App load | Sidebar slides in 28px; nav items cascade; top bar drops 14px | 700ms; 30–60ms stagger | `ease-out-strong` |
| Page change | Old content fades up 8px out; new cards rise 18px in | 180ms out; 40ms stagger in | `ease-in`, then `ease-out` |
| Nav click | Icon pops 75% → 100% | 550ms | `ease-overshoot` |
| Nav group opens | Sub-items unfold top-down (clip), slide 6px | 550ms; 40ms stagger | `ease-in-out` |
| Sidebar collapse to rail | Width and position animate; labels slide back 8px | 600ms | `ease-in-out` |
| ⌘K palette | Drops 14px, scales 97% → 100%; rows rise 8px | 380ms in, 160ms out | `ease-overshoot`, `ease-in` |
| Peek drawer | Slides 70px from the right (90px up on mobile); body rows cascade | 550ms in, 240ms out | `ease-out-strong`, `ease-in` |
| Dialog (warning confirm, conflict, plan limit) | Fades in and scales 96% → 100% | 450ms in, 180ms out | `ease-overshoot`, `ease-in` |
| Form error | Field shakes 8px sideways | 500ms | spring (stiffness 600, damping 12) |
| **Drag an issue** | Card lifts to 104% with `shadow-lift`; tilts slightly with pointer velocity; target column tints | 250ms lift | `ease-overshoot` |
| Drop | Card settles into its slot; other cards slide to new places (Motion `layout`) | 320ms | `ease-out` |
| Close as Won't do / Duplicate | Card dims to 55% in place (it stays on the board until it's cleared) | 300ms | `ease-standard` |
| Live update from someone else | A moved card slides to its new column; a changed field fades to its new value | 450ms | `ease-out` |
| Toast | Rises from the bottom centre; stays 6s with Undo, 4.2s without | 300ms | `ease-out` |
| Undo | The change reverses with the same motion as the original, played backwards | as the original | as the original |
| Theme toggle | New theme reveals as a circle from the toggle | 480ms | `ease-standard`, View Transitions API |
| Charts | Bars grow in sequence; lines draw; values tween on change | 800–1100ms | `ease-in-out` |

## Skip from Selvar

Selvar also has an always-rotating AI orb, letter-by-letter headline reveals and a ~1.5s overview intro. They suit a demo, not a screen opened many times a day; pm-monkey does not use them. AI and agent text never types out letter by letter.

## In React

- Wrap the app in `<MotionConfig reducedMotion="user">` so Motion respects the OS setting, and keep timings and easings in one `motion.ts` file that reads the duration and easing tokens.
- Board reorders: let `@dnd-kit` handle the drag itself and give cards Motion's `layout` prop so neighbours slide into place; disable `layout` on the card being dragged.
- Exits: render leaving drawers, dialogs, toasts and rows inside `AnimatePresence`.
- Clean up: never leave inline transforms on an element after its animation ends, so layout and focus behave normally.
