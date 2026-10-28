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

    public function delete(User $user, Issue $issue): Response
    {
        return $this->projects->contribute($user, $issue->project);
    }

    public function comment(User $user, Issue $issue): Response
    {
        return $this->projects->contribute($user, $issue->project);
    }
}
