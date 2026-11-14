<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Http\Resources\IssueResource;
use App\Models\Issue;
use App\Models\User;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** What the issue view needs, shared by the full page (/issues/{KEY}) and the peek drawer (?issue={KEY}). */
trait PresentsIssue
{
    /** @return array{issue: array<string, mixed>, options: array<string, mixed>, can: array<string, bool>} */
    protected function issueView(Issue $issue, User $user): array
    {
        $issue = app(IssueService::class)->loadFull($issue);
        $data = (new IssueResource($issue))->resolve();
        $data['description_html'] = self::markdown($issue->description);
        $data['comments'] = collect($data['comments'])->map(fn ($c) => [
            ...$c,
            'body_html' => self::markdown($c['body']),
            'can_edit' => ($c['author']['id'] ?? null) === $user->id,
        ])->all();

        return [
            'issue' => $data,
            'options' => app(ProjectService::class)->issueOptions($issue->project),
            'can' => [
                'contribute' => $user->can('contribute', $issue->project),
                'manage' => $user->can('manage', $issue->project),
            ],
        ];
    }

    /** The `peek` page prop: the issue named by ?issue=, or null when there is none the user can see. */
    protected function peek(Request $request): ?array
    {
        $issue = app(IssueService::class)->peek($request->user(), $request->query('issue'));

        return $issue ? $this->issueView($issue, $request->user()) : null;
    }

    /** Raw HTML is stripped and javascript: links are dropped, so user Markdown can't inject script. */
    private static function markdown(?string $text): string
    {
        return $text === null ? '' : Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
