<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Sprints\RankIssueRequest;
use App\Http\Resources\IssueResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\BacklogService;
use App\Services\IssueService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('rank_backlog')]
#[Description('Reorder an issue in the backlog or a sprint, optionally moving it between them.')]
class RankBacklog extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, IssueService $issues, BacklogService $backlog): Response
    {
        $request->validate(['issue' => 'required|string']);
        $issue = $issues->resolve($request->user(), $request->get('issue'));
        Gate::authorize('update', $issue);
        $rules = new RankIssueRequest;
        $issue = $backlog->rank($issue, $request->user(), $request->validate($rules->rules(), $rules->messages()));

        return $this->result("Ranked {$issue->key} in ".($issue->sprint->name ?? 'the backlog').'.', new IssueResource($issue));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->description('Issue key, e.g. WEB-12.')->required(),
            'sprint' => $schema->string()->description('Destination sprint name, or "backlog"; omit to stay in the current section.'),
            'position' => $schema->integer()->min(0)->description('0-based index in the destination; default bottom. Do not combine with after.'),
            'after' => $schema->string()->description('Place directly after this issue key (must be in the destination).'),
        ];
    }
}
