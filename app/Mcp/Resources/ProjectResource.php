<?php

namespace App\Mcp\Resources;

use App\Http\Resources\LabelResource;
use App\Http\Resources\ProjectResource as ProjectJson;
use App\Services\ProjectService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('project')]
#[Description('Project metadata by key, e.g. project://WEB: members, statuses (columns) and labels — the valid values for issue fields.')]
#[MimeType('application/json')]
class ProjectResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('project://{key}');
    }

    public function handle(Request $request, ProjectService $projects): Response
    {
        $project = $projects->resolve($request->user(), (string) $request->get('key'))->load(['members', 'columns', 'labels']);

        return Response::json([
            ...(new ProjectJson($project))->resolve(),
            'statuses' => $project->columns->pluck('name')->all(),
            'labels' => LabelResource::collection($project->labels)->resolve(),
        ]);
    }
}
