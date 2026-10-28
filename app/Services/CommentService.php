<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function __construct(private ActivityLogger $activity) {}

    public function add(Issue $issue, User $author, string $body): Comment
    {
        return DB::transaction(function () use ($issue, $author, $body) {
            $comment = $issue->comments()->create(['user_id' => $author->id, 'body' => $body]);
            $this->activity->log($issue, $author, 'commented');

            return $comment->setRelation('author', $author);
        });
    }

    public function update(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);

        return $comment->loadMissing('author');
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    /** Find a comment by id; comments in projects the user isn't in are reported as missing. */
    public function resolve(User $user, int|string $id): Comment
    {
        $comment = Comment::with(['issue.project', 'author'])->find($id);

        if (! $comment || ! $user->accessibleProjects()->whereKey($comment->issue->project_id)->exists()) {
            throw ValidationException::withMessages([
                'comment' => "Comment {$id} was not found. Get the issue to see its comment ids.",
            ])->status(404);
        }

        return $comment;
    }
}
