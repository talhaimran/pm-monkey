# Approval card

An agent's request to make a change that needs a person's yes. Lives in the issue's AI box, in the Approvals inbox, and in notifications.

**When:** an agent action is set to "needs approval" by the project's agent policy. Never for changes the agent was allowed to make on its own; those appear in activity with the agent avatar.

**Anatomy:** `.approval` (sky tile) with a head (agent avatar, "Triage agent wants to…", expiry in `small`), the change itself (`.approval__move`: from pill → to pill, or a one-line field change; more than one field links to the change-set diff), the agent's reason in one line, and a footer: Approve (primary, small) and Reject (ghost, small), plus "View details".

**States:** pending; `is-stale` (butter tile) when the issue changed after the request, with "PM-142 changed since. Review before approving."; expired (read-only, "Expired"). Requests expire after 7 days.

**Rules:** the approver needs permission to make the change themselves. Approving runs it through the same services as a person's change, then shows the usual toast with Undo.

**A11y:** the agent is named in words ("Triage agent (AI)"), not only by avatar. Buttons say what they do: "Approve move to Done".
