<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function listFor(User $user, int $perPage = 25, ?int $page = null): LengthAwarePaginator
    {
        return $user->projects()->orderBy('key')->paginate(max(1, min($perPage, 100)), page: $page);
    }

    /** @param array{key: string, name: string, description?: ?string} $data */
    public function create(User $creator, array $data): Project
    {
        return DB::transaction(function () use ($creator, $data) {
            $project = Project::create([...$data, 'key' => strtoupper($data['key'])]);
            $project->members()->attach($creator, ['role' => ProjectRole::Admin->value]);

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
        $project->delete();
    }

    /**
     * Find a project the user belongs to by key (case-insensitive).
     * Non-members get the same 404 as a missing project, so keys don't leak.
     */
    public function resolve(User $user, string $key): Project
    {
        $project = $user->projects()->where('key', strtoupper($key))->first();

        if ($project) {
            return $project;
        }

        $keys = $user->projects()->orderBy('key')->limit(50)->pluck('key');
        $hint = $keys->isEmpty()
            ? 'You are not a member of any project yet; create one first.'
            : 'Projects you can access: '.$keys->implode(', ').'.';

        throw ValidationException::withMessages([
            'project' => "Project \"{$key}\" was not found. {$hint}",
        ])->status(404);
    }
}
