# Plan limit dialog

Explains that an action would go past the workspace's plan limit (members, projects, agent runs, storage) and what can be done.

**When:** the server answers with a plan-limit error. Shown before anything is changed.

**Anatomy:** `.dialog` with the amber icon tile, a title naming the limit ("You've used all 10 seats"), one line of what's counted, a `.progress` bar at 100%, and a footer. Owners see "Upgrade plan" (primary) and "Manage members" (ghost). Everyone else sees "Ask an owner" (primary), which sends the owners a notification, and Close.

**Voice:** plain and short. Say what the limit is and what to do; no sales copy.

**A11y:** Headless UI `Dialog`; the progress bar has `role="progressbar"` with its value in words.
