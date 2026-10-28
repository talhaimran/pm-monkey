<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    public function __construct(private ProjectPolicy $projects) {}

    public function update(User $user, Comment $comment): Response
    {
        $allowed = $this->projects->contribute($user, $comment->issue->project);
        if ($allowed->denied()) {
            return $allowed;
        }

        return $comment->user_id === $user->id
            ? Response::allow()
            : Response::deny('Only the author can edit a comment.');
    }

    public function delete(User $user, Comment $comment): Response
    {
        $project = $comment->issue->project;
        $allowed = $this->projects->contribute($user, $project);
        if ($allowed->denied()) {
            return $allowed;
        }

        return $comment->user_id === $user->id || $project->roleFor($user)?->canManage()
            ? Response::allow()
            : Response::deny('Only the author or a project admin can delete a comment.');
    }
}
