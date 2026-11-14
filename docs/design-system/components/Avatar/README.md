# Avatar

Initials in a pastel disc; the person's colour comes from hashing their id into one of seven `av-*` pairs.

**Sizes:** xs 24 (issue cards), sm 32 (lists, stacks), md 40 (drawer), lg 52 (profile).

**Agents:** `av--agent` is a rounded square in `ink` with a spark icon. Agents are never assignees, so it never appears in assignee slots or pickers; it shows only where an agent acted (activity, comments, reactions, approvals, notifications), always beside the words "{Owner}'s agent" or the agent's name.

**Stacks:** overlap 7px with a 2px `card` ring; show at most 3, then "+N". Always give the full name in a tooltip or `title`.
