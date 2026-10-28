<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\GetProject;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\UpdateProject;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('PM Monkey')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
    PM Monkey is a Jira-like project tracker. Projects are addressed by their short KEY (e.g. WEB); issues by KEY-NUMBER (e.g. WEB-12).
    Start with list_projects to see what you can access. Results are a short summary followed by JSON; lists are paginated (pass page=N).
    Destructive tools (delete_*) require confirm: true. Read-only tokens and Viewers cannot write.
    MD)]
class PmServer extends Server
{
    public int $maxPaginationLength = 50;

    protected array $tools = [
        ListProjects::class,
        GetProject::class,
        CreateProject::class,
        UpdateProject::class,
        DeleteProject::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
