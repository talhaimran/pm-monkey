<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lists\IssueListRequest;
use App\Http\Resources\IssueResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\DashboardService;
use App\Services\IssueService;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IssueListController extends Controller
{
    public function __construct(private ProjectService $projects, private IssueService $issues, private DashboardService $dashboard) {}

    public function index(IssueListRequest $request, Project $project): Response
    {
        Gate::authorize('view', $project);
        $params = array_filter($request->validated());

        return Inertia::render('Projects/Issues', [
            'project' => (new ProjectResource($project))->resolve(),
            'issues' => IssueResource::collection($this->issues->list($request->user(), $project, $params))->response()->getData(true),
            'filters' => (object) $params,
            'sorts' => IssueService::SORTS,
            'options' => $this->projects->issueOptions($project),
            'can' => ['contribute' => $request->user()->can('contribute', $project)],
        ]);
    }

    public function search(IssueListRequest $request): Response|RedirectResponse
    {
        $params = array_filter($request->validated());
        $q = trim($params['q'] ?? '');

        // An exact key the user can see goes straight to the issue.
        if (preg_match('/^[A-Za-z][A-Za-z0-9]{1,9}-\d+$/', $q)) {
            try {
                return redirect()->route('issues.show', $this->issues->resolve($request->user(), $q)->key);
            } catch (ValidationException) {
                // Not found: fall through to the result list (which will be empty).
            }
        }

        return Inertia::render('Search', [
            'issues' => IssueResource::collection($this->issues->list($request->user(), null, $params))->response()->getData(true),
            'filters' => (object) $params,
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();
        $projects = $user->accessibleProjects()->orderBy('key')->limit(50)->get();
        $summaries = $this->dashboard->summaries($projects);

        return Inertia::render('Dashboard', [
            'myIssues' => IssueResource::collection($this->dashboard->myIssues($user, 50))->resolve(),
            'projects' => $projects->map(fn ($p) => [
                'key' => $p->key,
                'name' => $p->name,
                'my_role' => $p->pivot->role,
                'summary' => $summaries[$p->id],
            ])->all(),
        ]);
    }
}
