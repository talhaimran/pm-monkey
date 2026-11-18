<?php

namespace App\Services;

use App\Enums\Entitlement;
use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Enums\ProjectRole;
use App\Enums\SprintState;
use App\Models\Column;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function __construct(private CurrentWorkspace $current, private AuditLogger $audit, private Entitlements $entitlements, private WorkspaceUsage $usage) {}

    public function listFor(User $user, int $perPage = 25, ?int $page = null): LengthAwarePaginator
    {
        return $user->accessibleProjects()->orderBy('key')->paginate(max(1, min($perPage, 100)), page: $page);
    }

    /** @param array{key: string, name: string, description?: ?string} $data */
    public function create(User $creator, array $data): Project
    {
        $workspace = $this->current->get();

        return $this->entitlements->locked($workspace, function () use ($creator, $data, $workspace) {
            $this->entitlements->ensure($workspace, Entitlement::Projects);
            // The creator becomes the project's Admin: a billed seat if they were a Viewer.
            $this->entitlements->ensurePerson($workspace, $this->usage->isBilled($workspace, $creator->email), true);

            $project = new Project([...$data, 'key' => strtoupper($data['key'])]);
            $project->workspace_id = $this->current->get()->id;
            $project->save();
            $project->members()->attach($creator, ['role' => ProjectRole::Admin->value]);
            $project->columns()->createMany(
                collect(Column::DEFAULTS)->map(fn ($name, $i) => ['name' => $name, 'position' => $i])->all()
            );
            $this->audit->log('project.created', $project, ['name' => $project->name], $this->current->get(), $creator);

            return $project;
        });
    }

    /** @param array{name?: string, description?: ?string} $data */
    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project;
    }

    public function delete(Project $project): void
    {
        // Issues first: they restrict column deletes, so the project cascade can't remove them in any order.
        DB::transaction(function () use ($project) {
            // Deleting a project is permanent: its issues go for good, including soft-deleted ones.
            $project->issues()->withTrashed()->whereNotNull('parent_id')->forceDelete();
            $project->issues()->withTrashed()->forceDelete();
            $project->delete();
            $this->audit->log('project.deleted', $project, ['name' => $project->name], $project->workspace()->first());
        });
    }

    /** Valid values for issue fields, used to build pickers and to explain errors. */
    public function issueOptions(Project $project): array
    {
        return [
            'columns' => $project->columns()->pluck('name')->all(),
            'members' => $project->members()->orderBy('name')->get(['users.id', 'name', 'email'])
                ->map(fn ($u) => ['name' => $u->name, 'email' => $u->email])->all(),
            'labels' => $project->labels()->get(['id', 'name', 'color'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->all(),
            'sprints' => $project->sprints()->where('state', '!=', SprintState::Completed->value)->orderBy('id')->pluck('name')->all(),
            'types' => IssueType::values(),
            'priorities' => IssuePriority::values(),
        ];
    }

    /**
     * Find a project the user belongs to by key (case-insensitive).
     * Non-members get the same 404 as a missing project, so keys don't leak.
     */
    public function resolve(User $user, string $key): Project
    {
        $project = $user->accessibleProjects()->where('key', strtoupper($key))->first();

        if ($project) {
            return $project;
        }

        $keys = $user->accessibleProjects()->orderBy('key')->limit(50)->pluck('key');
        $hint = $keys->isEmpty()
            ? 'You are not a member of any project yet; create one first.'
            : 'Projects you can access: '.$keys->implode(', ').'.';

        throw ValidationException::withMessages([
            'project' => "Project \"{$key}\" was not found. {$hint}",
        ])->status(404);
    }
}
