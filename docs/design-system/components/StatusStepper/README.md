# StatusStepper

A workflow stepper for the issue view; click a step to move the issue there. **Decided later:** the spec defers the status stepper, so don't build it yet. Until then the issue view shows status as a pill inside a menu button ("Status: In progress").

**When it is built, it must:** show only the statuses in the issue type's workflow; mark steps the workflow rules block, with the reason; switch to a dropdown above about 6 statuses; pair reaching Done with the "Resolved: Done ▾" chip.

**States per step:** done (cobalt fill, check), now (cobalt ring + 4px halo, `aria-current="step"`), upcoming (`line-2` outline, number), hover (cobalt border).

**Keyboard:** each step is a button; ←/→ move focus between steps; Enter sets the status and announces it.
