<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\CommentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_comment')]
#[Description('Delete a comment (your own, or any comment if you are a project admin).')]
#[IsDestructive]
class DeleteComment extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, CommentService $comments): Response
    {
        $request->validate(['comment_id' => 'required|integer']);
        $comment = $comments->resolve($request->user(), $request->get('comment_id'));
        Gate::authorize('delete', $comment);
        $comments->delete($comment);

        return $this->result("Deleted comment {$comment->id}.", ['deleted' => $comment->id]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['comment_id' => $schema->integer()->description('Comment id from get_issue.')->required()];
    }
}
