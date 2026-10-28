<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\Tools\Concerns\RespondsWithJson;
use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('List projects you are a member of, with your role in each.')]
#[IsReadOnly]
class ListProjects extends Tool
{
    use RespondsWithJson;

    public function handle(Request $request, ProjectService $projects): Response
    {
        $data = $request->validate(['page' => 'integer|min:1', 'per_page' => 'integer|min:1|max:50']);

        return $this->paginated('projects', $projects->listFor($request->user(), $data['per_page'] ?? 25, $data['page'] ?? 1), ProjectResource::class);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1)->description('Page number, default 1.'),
            'per_page' => $schema->integer()->min(1)->max(50)->description('Items per page, default 25, max 50.'),
        ];
    }
}
