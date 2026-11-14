# Warning confirm

Asks before a change that breaks a rule: moving a blocked issue, closing with failing checks, going over a WIP limit, moving into Done with open sub-tasks.

**When:** only for rule warnings from the workflow service. Ordinary single changes don't ask; they show a toast with Undo. Bulk edits and multi-item deletes use the preview in the change-set diff instead.

**Anatomy:** `.dialog` with a warn icon tile, a title that names the change ("Move PM-142 to Done?"), a `.warnlist` with one row per warning (what's wrong, then a short reason in `small`), and a footer: Cancel (ghost) and the action restated ("Move anyway", primary). When a warning is a hard block (the person lacks the override permission), the action button is removed and the row says who can override.

**Behaviour:** the change isn't made until the person confirms. The confirmed change is logged with the warnings it overrode. Undo still works after it.

**A11y:** Headless UI `Dialog`; focus starts on Cancel, Esc cancels, focus returns to the control that started the change. The title is the dialog's label and the warning list its description.
