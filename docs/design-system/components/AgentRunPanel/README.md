# Agent run panel

Shows one agent run: what started it, each step, the tools it called, and what it changed or asked to change.

**When:** opened from the AI box ("View run"), from activity entries made by an agent, and from Settings → Agents → Runs.

**Anatomy:** `.run` with a head (agent avatar, run title, "Started by Talha · 2 min ago · 12k tokens", and Stop while running), then `.run__steps`: one row per step with a state dot, a short description, tool names in `code`, and a duration. Changes made show as a link to the change-set diff; requests waiting show the approval card inline.

**States per step:** done (good dot), now (cobalt ring), waiting for approval, failed (bad dot, with the error in one line and Retry), skipped.

**Rules:** the panel never shows raw prompts or hidden reasoning; it shows what the agent did. Stop ends the run after the current step; nothing already applied is undone automatically.

**A11y:** the step list is an ordered list; the current step has `aria-current="step"`. New steps are announced politely and never move focus.
