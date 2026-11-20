<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IssuePolicy
{
    public function __construct(private ProjectPolicy $projects) {}

    public function update(User $user, Issue $issue): Response
    {
        return $this->projects->contribute($user, $issue->project);
    }

    /** Owner's role first (contribute), then the agent's level: an agent deletes only at the Delete level. */
    public function delete(User $user, Issue $issue): Response
    {
        $contribute = $this->projects->contribute($user, $issue->project);

        return $contribute->denied() || $user->canDelete()
            ? $contribute
            : Response::deny("This agent can't delete. Its owner can allow deletes in Settings → Agents.");
    }

    public function comment(User $user, Issue $issue): Response
    {
        return $this->projects->contribute($user, $issue->project);
    }
}
