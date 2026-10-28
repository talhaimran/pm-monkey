<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\CommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\CommentService;
use App\Services\IssueService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function __construct(private IssueService $issues, private CommentService $comments) {}

    /** Add a Markdown comment to an issue. */
    public function store(CommentRequest $request, string $key): CommentResource
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('comment', $issue);

        return new CommentResource($this->comments->add($issue, $request->user(), $request->validated('body')));
    }

    /** Edit your own comment. */
    public function update(CommentRequest $request, int $comment): CommentResource
    {
        $comment = $this->comments->resolve($request->user(), $comment);
        Gate::authorize('update', $comment);

        return new CommentResource($this->comments->update($comment, $request->validated('body')));
    }

    /** Delete a comment (author or project admin). */
    public function destroy(Request $request, int $comment): Response
    {
        $comment = $this->comments->resolve($request->user(), $comment);
        Gate::authorize('delete', $comment);
        $this->comments->delete($comment);

        return response()->noContent();
    }
}
