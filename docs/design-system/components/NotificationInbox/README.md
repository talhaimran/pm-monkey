# Notification inbox

The bell's popover: mentions, assignments, approval requests, and changes to issues the person watches.

**Anatomy:** `.inbox` with a head ("Inbox", a `.seg` for All / Mentions / Approvals, "Mark all read" small ghost button), then `.inbox__list`. Each `.inbox__item` has an unread dot, the actor's avatar (agent avatar for agents), one line of what happened with the issue key, and the time in `small`. Approval requests show Approve / Reject inline.

**Behaviour:** opening an item opens the issue in the peek drawer and marks it read. New items arrive live at the top without moving focus or scroll. Grouped: several changes to one issue by one actor within 10 minutes become one item ("Sam changed 3 fields on PM-142").

**Empty:** "You're all caught up."

**A11y:** Headless UI `Popover`; items are links. The bell's label includes the count ("Inbox, 3 unread").
