# Audit log table

The workspace's record of who changed what: people, agents, API tokens, and the system. Settings → Audit log; Owners and Admins only.

**Anatomy:** `.table` with columns When, Actor (avatar and name; agents shown as "{Owner}'s agent" or the agent's name, tokens show the token name), Action (plain sentence: "Moved PM-142 to Done"), Target, and Source (Web, API, Agent, Import, Undo). A filter bar sits above it (actor, action, date range). Rows open a detail drawer with the before and after values.

**Rules:** read-only; rows can't be edited or deleted. Undos and restores appear as their own rows with Source "Undo" or "Restore". Overridden rule warnings are listed on the row ("overrode: blocked").

**Paging:** newest first, 50 rows a page, with "Load older". Export to CSV for the filtered range.

**A11y:** a real `<table>` with `<th scope="col">`; times have a `<time datetime>` with the exact time as its title.
