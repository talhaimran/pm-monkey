<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\IssueResource;
use App\Mcp\Resources\ProjectResource;
use App\Mcp\Tools\AddComment;
use App\Mcp\Tools\CreateIssue;
use App\Mcp\Tools\CreateLabel;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\DeleteComment;
use App\Mcp\Tools\DeleteIssue;
use App\Mcp\Tools\DeleteLabel;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\GetIssue;
use App\Mcp\Tools\GetIssueActivity;
use App\Mcp\Tools\GetProject;
use App\Mcp\Tools\ListLabels;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\UpdateComment;
use App\Mcp\Tools\UpdateIssue;
use App\Mcp\Tools\UpdateLabel;
use App\Mcp\Tools\UpdateProject;
use App\Services\ActivityLogger;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;

#[Name('PM Monkey')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
    PM Monkey is a Jira-like project tracker. Projects are addressed by their short KEY (e.g. WEB); issues by KEY-NUMBER (e.g. WEB-12).
    Start with list_projects to see what you can access. Issue fields take names, not ids: status is a column name,
    assignee a member email, labels label names, epic/parent issue keys. Errors list the valid options. Results are a short summary followed by JSON; lists are paginated (pass page=N).
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
        CreateIssue::class,
        GetIssue::class,
        UpdateIssue::class,
        DeleteIssue::class,
        GetIssueActivity::class,
        AddComment::class,
        UpdateComment::class,
        DeleteComment::class,
        ListLabels::class,
        CreateLabel::class,
        UpdateLabel::class,
        DeleteLabel::class,
    ];

    protected array $resources = [
        IssueResource::class,
        ProjectResource::class,
    ];

    /** Runs for every MCP request (and in tests); tags activity as coming from MCP. */
    public function createContext(): ServerContext
    {
        app()->instance(ActivityLogger::VIA_BINDING, 'mcp');

        return parent::createContext();
    }

    protected array $prompts = [];
}
