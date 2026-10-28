<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Issues\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\CommentService;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_comment')]
#[Description('Add a Markdown comment to an issue.')]
class AddComment extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, IssueService $issues, CommentService $comments): Response
    {
        $request->validate(['issue' => 'required|string']);
        $issue = $issues->resolve($request->user(), $request->get('issue'));
        Gate::authorize('comment', $issue);
        $comment = $comments->add($issue, $request->user(), $request->validate((new CommentRequest)->rules())['body']);

        return $this->result("Commented on {$issue->key} (comment id {$comment->id}).", new CommentResource($comment));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
            'body' => $schema->string()->description('Markdown text.')->required(),
        ];
    }
}
