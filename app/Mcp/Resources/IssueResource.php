<?php

namespace App\Mcp\Resources;

use App\Http\Resources\IssueResource as IssueJson;
use App\Services\IssueService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('issue')]
#[Description('A single issue by key, e.g. issue://WEB-12, with sub-tasks and comments.')]
#[MimeType('application/json')]
class IssueResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('issue://{key}');
    }

    public function handle(Request $request, IssueService $issues): Response
    {
        $issue = $issues->loadFull($issues->resolve($request->user(), (string) $request->get('key')));

        return Response::json((new IssueJson($issue))->resolve());
    }
}
