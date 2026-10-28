<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\CommentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('update_comment')]
#[Description('Replace the text of one of your own comments.')]
#[IsIdempotent]
class UpdateComment extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, CommentService $comments): Response
    {
        $request->validate(['comment_id' => 'required|integer']);
        $comment = $comments->resolve($request->user(), $request->get('comment_id'));
        Gate::authorize('update', $comment);
        $comment = $comments->update($comment, $request->validate((new CommentRequest)->rules())['body']);

        return $this->result("Updated comment {$comment->id}.", new CommentResource($comment));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'comment_id' => $schema->integer()->description('Comment id from get_issue.')->required(),
            'body' => $schema->string()->description('New Markdown text.')->required(),
        ];
    }
}
