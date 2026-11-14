# Conflict dialog

Shown when a save fails because someone (a person or an agent) changed the same field since the form was opened.

**When:** the server rejects a write on a stale version. Never for live updates to fields the person isn't editing; those just update in place.

**Anatomy:** `.dialog.dialog--wide` with the cobalt icon tile, title "PM-142 changed while you were editing", a `.compare` with two sides: "Your version" and "Their version" (`is-theirs`, with who and when). Changed words use `.chg-del` and `.chg-add`. Footer: "Keep theirs" (ghost), "Copy mine" (ghost), "Save mine over theirs" (primary).

**Behaviour:** nothing is lost. "Keep theirs" puts the person's text on the clipboard first. "Save mine over theirs" is logged as an overwrite.

**A11y:** Headless UI `Dialog`; the two sides are headed regions. Differences are shown with strikethrough and weight as well as colour.

**Mobile:** the two sides stack.
