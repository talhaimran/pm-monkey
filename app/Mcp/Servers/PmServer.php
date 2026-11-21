<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\IssueResource;
use App\Mcp\Resources\ProjectResource;
use App\Mcp\Tools\AddComment;
use App\Mcp\Tools\BulkCreateIssues;
use App\Mcp\Tools\BulkUpdateIssues;
use App\Mcp\Tools\CompleteSprint;
use App\Mcp\Tools\CreateColumn;
use App\Mcp\Tools\CreateIssue;
use App\Mcp\Tools\CreateLabel;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\CreateSprint;
use App\Mcp\Tools\DeleteColumn;
use App\Mcp\Tools\DeleteComment;
use App\Mcp\Tools\DeleteIssue;
use App\Mcp\Tools\DeleteLabel;
use App\Mcp\Tools\DeleteProject;
use App\Mcp\Tools\DeleteSprint;
use App\Mcp\Tools\GetBacklog;
use App\Mcp\Tools\GetBoard;
use App\Mcp\Tools\GetIssue;
use App\Mcp\Tools\GetIssueActivity;
use App\Mcp\Tools\GetProject;
use App\Mcp\Tools\ListColumns;
use App\Mcp\Tools\ListLabels;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListSprints;
use App\Mcp\Tools\MoveIssue;
use App\Mcp\Tools\MyIssues;
use App\Mcp\Tools\ProjectSummary;
use App\Mcp\Tools\RankBacklog;
use App\Mcp\Tools\SearchIssues;
use App\Mcp\Tools\StartSprint;
use App\Mcp\Tools\UpdateColumn;
use App\Mcp\Tools\UpdateComment;
use App\Mcp\Tools\UpdateIssue;
use App\Mcp\Tools\UpdateLabel;
use App\Mcp\Tools\UpdateProject;
use App\Mcp\Tools\UpdateSprint;
use App\Services\ActivityLogger;
use App\Services\AgentService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;
use Laravel\Sanctum\PersonalAccessToken;

#[Name('PmOps')]
#[Version('1.0.0')]
#[Instructions(<<<'MD'
    PmOps is a Jira-like project tracker. Projects are addressed by their short KEY (e.g. WEB); issues by KEY-NUMBER (e.g. WEB-12).
    Start with list_projects to see what you can access. Issue fields take names, not ids: status is a column name,
    assignee a member email, labels label names, epic/parent issue keys. Errors list the valid options. Results are a short summary followed by JSON; lists are paginated (pass page=N).
    Destructive tools (delete_*) require confirm: true. Read-only agents and Viewers cannot write, and only agents with the delete level can delete.
    MD)]
class PmServer extends Server
{
    // One page for tools/list: some MCP clients never request the next cursor.
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

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
        GetBoard::class,
        MoveIssue::class,
        ListColumns::class,
        CreateColumn::class,
        UpdateColumn::class,
        DeleteColumn::class,
        ListSprints::class,
        CreateSprint::class,
        UpdateSprint::class,
        DeleteSprint::class,
        StartSprint::class,
        CompleteSprint::class,
        GetBacklog::class,
        RankBacklog::class,
        SearchIssues::class,
        MyIssues::class,
        ProjectSummary::class,
        BulkCreateIssues::class,
        BulkUpdateIssues::class,
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

    public const INVALID_TOKEN = 'PM_MCP_TOKEN is missing or invalid. Create an agent in Settings → Agents (/{workspace}/settings/agents) and set PM_MCP_TOKEN in your MCP client config.';

    /**
     * Re-checks the token and the agent on every call, so a regenerated or expired token, a pause or a revoke stops a
     * long-lived stdio process at its next call, as REST would refuse it.
     */
    protected function runMethodHandle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse
    {
        try {
            app(AgentService::class)->ensureUsable(auth()->user()?->agent());
        } catch (HttpResponseException $e) {
            $error = $e->getResponse()->getData(true);

            throw new JsonRpcException($error['message'], -32001, $request->id, $error);
        }

        // After the agent check, so a revoked agent says so rather than "invalid token".
        $token = auth()->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken && ! PersonalAccessToken::whereKey($token->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
            throw new JsonRpcException(self::INVALID_TOKEN, -32001, $request->id);
        }

        return parent::runMethodHandle($request, $context);
    }
}
