# PmOps design system

A calm, fast, keyboard-friendly issue tracker for small teams, where people and AI agents work the same board.

This design system is the source of truth for PmOps' UI (spec `pmops-spec.md`, section 5.1). The Selvar demo (a sales-pipeline template) and Linear are visual references only: the tokens started from values observed in Selvar and were remapped for issue tracking, and every stylesheet and component here is written for PmOps. Never copy Selvar's CSS or code. The app is **React 18 + TypeScript on Laravel (Inertia), styled with Tailwind 3**; components here are framework-free HTML + CSS classes (`components/bundle.css`) with guidelines for building them as React components.

## Principles

1. **Calm by default.** The workspace is neutral (`bg`, `card`, `card-2`). Colour appears only in small accents: status dots, type icons, avatar tints. If a screen feels busy, remove colour before removing content. Empty fields stay hidden until someone asks for them.
2. **Keyboard friendly, mouse friendly.** Every control is reachable by keyboard and shows a visible focus ring. The full keyboard map and the ⌘K palette as a cross-cutting rule are decided later; build shortcuts that exist today (C to create) and show them where they apply.
3. **Peek first, full page when you need it.** The full issue page (`/{workspace}/issues/{KEY}`) is the canonical view. From the board, list and search, an issue opens in a peek drawer so people keep their place; "Open full page" is always one click away.
4. **Motion explains, never decorates.** Things move to show where they came from and where they went: a card lifting, a drawer sliding from the right, an Undo toast. No looping or ambient animation on working screens.
5. **Undo for single changes, confirm for warnings, preview for bulk.** A move, status or field change applies at once and shows a toast with Undo. A rule warning (blocked issue, failing tests, WIP limit, plan limit) asks first. Bulk edits and multi-item deletes show a preview first.
6. **People and agents are told apart.** Agents are never assignees. Where an agent acted, it shows the agent avatar (`.av--agent`) and the words "{Owner}'s agent". Its work, approvals and proposals live in the AI box (`tile-sky`), which never shows an automatic summary.

## Voice

Short, plain, specific. Sentence case everywhere ("Move to In review", not "Move To In Review"). Verbs on buttons ("Create issue", "Start sprint"), never "OK" or "Submit". Say "sprint", never "cycle". Numbers carry units ("13 pts", "6 h", "3 days left"). Empty states say what goes here and how to add it in one line, with the shortcut: "No issues yet. Create your first issue · C". No product tours.

## Colour

- **Surfaces:** `bg` behind everything, `card` for things you act on, `card-2` for containers (board columns, kbd keys), `well` for insets (segmented track, selected palette row). Borders are hairlines: `line`, or `line-2` for inputs and controls.
- **Text:** `text` for primary, `muted` for secondary (the smallest colour for anything a person must read), `faint` for timestamps and placeholders only. `faint` is under 4.5:1 on light surfaces.
- **Brand:** `primary` blue for the one primary action per view, links, focus rings and selection.
- **Ink:** `ink` / `on-ink` for the dark button and toasts.
- **Sidebar:** always dark in both themes, with its own set (`side`, `on-side`, `side-dim`, `side-pill`…).
- **Status colour comes from the status category:** To Do `sky` · In Progress `amber` · Done `good`. Workspaces name their own statuses, so there's no fixed status list. Owners and Admins can give a status a custom colour from the palette (`slate`, `sky`, `amber`, `cobalt`, `coral`, `teal`, `sand`, `good`); set it as `--c` / `--t` on the pill, column or dot.
- **Signals:** `good`, `warn`, `bad`, `info` for outcomes and alerts (success, due soon, overdue, destructive, notices). How Blocked and At risk show on cards is decided later; until then use the existing card flags.
- **Issue type (card icon chip):** Task `slate` · Story `cobalt` · Bug `coral` · Epic `amber` · Sub-task `sky` · Initiative `sand` · Spike `teal` · Incident `bad`. Admins can change a type's colour; the icon always comes with it.
- **Pills and chips** tint their strong colour into `card` (14% light, 18% dark) and set text in the matching `t-*` token, which passes 5:1+ in both themes. Never put `c-*` text on a tint.
- **Avatars** get one of seven pairs (`av-*` + `av-*-t`; pastel in light, a deep tint with light initials in dark) by hashing the person's id.

## Type

One family: **Urbanist** (400–800), falling back to the system UI font. The app self-hosts it with `@fontsource/urbanist`; previews here load it from Google Fonts. Body is 14px / 500. Weight carries hierarchy more than size: titles 700, UI labels 600, body 500. Display sizes tighten letter-spacing to −0.02em. Use the type styles (`display`, `heading-*`, `text-*`, `body`); do not add sizes.

## Space, shape, depth

- **Spacing:** a 2px-based scale. The layout gap is `space-4` (16px); cards pad `space-5`; issue cards pad `space-3` and sit `space-2` apart; columns sit `space-2-5` apart.
- **Radius grows with size:** 7 (kbd) → 10 (small controls) → 12 (controls) → 15 (issue cards) → 18 (tiles) → 20 (columns) → 22 (cards, palette, dialogs) → 24 (drawers) → pill.
- **Shadows are soft and few:** `shadow-xs` on issue cards, `shadow` on cards, `shadow-pop` on anything floating, `shadow-lift` only while dragging.

## Layout

Sidebar 256px (92px collapsed rail) with the workspace switcher at the top, plus content with a 16–32px fluid gutter. Page header: breadcrumb, a one-line summary ("38 open issues · 112 pts"), the page title (`display`), then a toolbar row (filter bar, view switcher on the right). The board scrolls horizontally with snap; columns are at least 198px. The full issue page has a main column (title, description, child issues, links, activity) and a 320px side column (fields, AI box). Under 760px: the sidebar becomes a sheet, drawers and dialogs rise from the bottom.

## Board density

Column totals show the sum of estimates in the project's unit (points or hours) and the issue count. Above 12 cards in a column, cards switch to the compact density (`icard--compact`); each person can override it. Above 20 cards, a column shows the first 20 and "Show N more".

## Iconography

Line icons at 1.8px stroke, round caps and joins, 16px in controls (17px in buttons, 20px in the palette input), drawn in `currentColor`. The app uses **lucide-react**; previews here use inline Lucide-style paths. Icons always sit beside a text label or carry an `aria-label`.

## Using it in React (the PM-Ops repo)

- **Tokens:** move them into `tailwind.config.js`, keeping the repo's pattern: each colour becomes a CSS variable, switched by the `.dark` class (this page's `data-theme="dark"`), and exposed as a Tailwind colour. Spacing, radius, shadow, type, duration and easing tokens go in the same config.
- **Components:** build each one as a React + TypeScript component that renders the documented markup, states and keyboard behaviour, styled with Tailwind classes that read the tokens (or the classes in `components/bundle.css`).
- **Libraries already in the repo:** `@headlessui/react` for dialogs, menus, listboxes and comboboxes (focus trapping and keyboard handling); `@dnd-kit` for drag and drop.
- **Libraries approved in the spec:** `motion` for anything that moves through space (see Motion), `lucide-react` for icons, `@fontsource/urbanist` for the font.
- **Rule:** read sizes, colours and timings from the tokens, never hard-code them. Nothing else gets added without asking.

## Sections

**Motion** has every timing and easing with the reduced-motion rule. **Accessibility** has the contrast results and testable acceptance criteria. Each component's page has anatomy, states and keyboard behaviour. Components that later spec steps need (approval card, change-set diff, conflict dialog, plan-limit dialog, warning confirm, agent run panel, notification inbox, filter bar, audit log table, timeline, dashboard widget), plus the issue page and empty state, are here so each step builds them the same way.
