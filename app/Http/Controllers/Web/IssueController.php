<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issues\CommentRequest;
use App\Http\Requests\Issues\DestroyIssueRequest;
use App\Http\Requests\Issues\StoreIssueRequest;
use App\Http\Requests\Issues\UpdateIssueRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\IssueResource;
use App\Models\Project;
use App\Services\CommentService;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function __construct(
        private ProjectService $projects,
        private IssueService $issues,
        private CommentService $comments,
    ) {}

    public function store(StoreIssueRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('contribute', $project);
        $issue = $this->issues->create($project, $request->user(), $request->validated());

        return redirect()->route('issues.show', $issue->key);
    }

    public function show(Request $request, string $key): Response
    {
        $issue = $this->issues->loadFull($this->issues->resolve($request->user(), $key));
        $user = $request->user();
        $data = (new IssueResource($issue))->resolve();
        $data['description_html'] = self::markdown($issue->description);
        $data['comments'] = collect($data['comments'])->map(fn ($c) => [
            ...$c,
            'body_html' => self::markdown($c['body']),
            'can_edit' => ($c['author']['id'] ?? null) === $user->id,
        ])->all();

        return Inertia::render('Issues/Show', [
            'issue' => $data,
            'project' => ['key' => $issue->project->key, 'name' => $issue->project->name],
            'activity' => ActivityResource::collection($issue->activities()->with('user')->latest('id')->limit(50)->get())->resolve(),
            'options' => $this->projects->issueOptions($issue->project),
            'can' => [
                'contribute' => $user->can('contribute', $issue->project),
                'manage' => $user->can('manage', $issue->project),
            ],
        ]);
    }

    public function update(UpdateIssueRequest $request, string $key): RedirectResponse
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('update', $issue);
        $this->issues->update($issue, $request->user(), $request->validated());

        return back();
    }

    public function destroy(DestroyIssueRequest $request, string $key): RedirectResponse
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('delete', $issue);
        $this->issues->delete($issue);

        return redirect()->route('projects.show', $issue->project->key);
    }

    public function comment(CommentRequest $request, string $key): RedirectResponse
    {
        $issue = $this->issues->resolve($request->user(), $key);
        Gate::authorize('comment', $issue);
        $this->comments->add($issue, $request->user(), $request->validated('body'));

        return back();
    }

    public function updateComment(CommentRequest $request, int $comment): RedirectResponse
    {
        $comment = $this->comments->resolve($request->user(), $comment);
        Gate::authorize('update', $comment);
        $this->comments->update($comment, $request->validated('body'));

        return back();
    }

    public function destroyComment(Request $request, int $comment): RedirectResponse
    {
        $comment = $this->comments->resolve($request->user(), $comment);
        Gate::authorize('delete', $comment);
        $this->comments->delete($comment);

        return back();
    }

    /** Raw HTML is stripped and javascript: links are dropped, so user Markdown can't inject script. */
    private static function markdown(?string $text): string
    {
        return $text === null ? '' : Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
