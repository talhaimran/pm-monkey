<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\DestroyProjectRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ColumnResource;
use App\Http\Resources\LabelResource;
use App\Http\Resources\ProjectInvitationResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Projects/Index', [
            'projects' => ProjectResource::collection($user->accessibleProjects()->orderBy('key')->get())->resolve(),
            'invitations' => ProjectInvitationResource::collection(
                ProjectInvitation::with('project')->where('email', strtolower($user->email))->latest()->get()
            )->resolve(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Gate::authorize('create', Project::class);
        $project = $this->projects->create($request->user(), $request->validated());

        return redirect()->route('projects.settings', $project->key);
    }

    public function settings(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);
        $project->load(['members' => fn ($q) => $q->orderBy('name'), 'invitations']);
        $canManage = $request->user()->can('manage', $project);

        return Inertia::render('Projects/Settings', [
            'project' => (new ProjectResource($project))->resolve(),
            'invitations' => $canManage ? ProjectInvitationResource::collection($project->invitations)->resolve() : [],
            'roles' => ProjectRole::values(),
            'labels' => LabelResource::collection($project->labels()->get())->resolve(),
            'columns' => ColumnResource::collection($project->columns()->withCount('issues')->get())->resolve(),
            'can' => ['manage' => $canManage, 'contribute' => $request->user()->can('contribute', $project)],
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        $this->projects->update($project, $request->validated());

        return back();
    }

    public function destroy(DestroyProjectRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);
        $this->projects->delete($project);

        return redirect()->route('projects.index');
    }
}
