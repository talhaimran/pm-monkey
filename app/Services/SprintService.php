<?php

namespace App\Services;

use App\Enums\SprintState;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintService
{
    public const DEFAULT_LENGTH_DAYS = 14;

    public function __construct(private ActivityLogger $activity) {}

    /** @param array{name?: ?string, goal?: ?string, start_date?: ?string, end_date?: ?string} $data */
    public function create(Project $project, array $data): Sprint
    {
        $name = trim($data['name'] ?? '') ?: $this->defaultName($project);
        $this->ensureUnique($project, $name);
        $this->ensureDateOrder($data['start_date'] ?? null, $data['end_date'] ?? null);

        return $project->sprints()->create([...$data, 'name' => $name, 'state' => SprintState::Planned]);
    }

    public function update(Sprint $sprint, array $data): Sprint
    {
        $this->ensureNotCompleted($sprint);
        if (isset($data['name'])) {
            $this->ensureUnique($sprint->project, $data['name'], $sprint);
            $data['name'] = trim($data['name']);
        }
        $this->ensureDateOrder(
            array_key_exists('start_date', $data) ? $data['start_date'] : $sprint->start_date?->toDateString(),
            array_key_exists('end_date', $data) ? $data['end_date'] : $sprint->end_date?->toDateString(),
        );
        $sprint->update($data);

        return $sprint;
    }

    /** Planned sprints only; their issues go back to the backlog. */
    public function delete(Sprint $sprint): void
    {
        if ($sprint->state !== SprintState::Planned) {
            throw ValidationException::withMessages(['sprint' => "{$sprint->name} is {$sprint->state->value}; only planned sprints can be deleted."]);
        }

        DB::transaction(function () use ($sprint) {
            $sprint->issues()->update(['sprint_id' => null]);
            $sprint->delete();
        });
    }

    /** @param array{start_date?: ?string, end_date?: ?string} $data */
    public function start(Sprint $sprint, array $data): Sprint
    {
        return DB::transaction(function () use ($sprint, $data) {
            Project::whereKey($sprint->project_id)->lockForUpdate()->first();

            if ($sprint->state !== SprintState::Planned) {
                throw ValidationException::withMessages(['sprint' => "{$sprint->name} is {$sprint->state->value}; only planned sprints can be started."]);
            }

            $active = $sprint->project->sprints()->where('state', SprintState::Active)->first();
            if ($active) {
                throw ValidationException::withMessages(['sprint' => "{$active->name} is already active. Complete it before starting {$sprint->name}."]);
            }

            $start = Carbon::parse($data['start_date'] ?? $sprint->start_date ?? today());
            $end = Carbon::parse($data['end_date'] ?? $sprint->end_date ?? $start->copy()->addDays(self::DEFAULT_LENGTH_DAYS));
            $this->ensureDateOrder($start->toDateString(), $end->toDateString());

            $sprint->update([
                'state' => SprintState::Active,
                'start_date' => $start,
                'end_date' => $end,
                'started_at' => now(),
                'active_project_id' => $sprint->project_id,
            ]);

            return $sprint;
        });
    }

    /**
     * Complete the active sprint. Issues not in the done column move to the backlog or to a planned sprint.
     *
     * @return int number of unfinished issues moved
     */
    public function complete(Sprint $sprint, User $actor, string $moveTo): int
    {
        if ($sprint->state !== SprintState::Active) {
            throw ValidationException::withMessages(['sprint' => "{$sprint->name} is {$sprint->state->value}; only the active sprint can be completed."]);
        }

        $project = $sprint->project;
        $target = $this->resolveCompletionTarget($sprint, $moveTo);

        return DB::transaction(function () use ($sprint, $actor, $project, $target) {
            $open = $sprint->issues()->where('column_id', '!=', $project->doneColumnId())->get();

            foreach ($open as $issue) {
                $issue->update(['sprint_id' => $target?->id]);
                $this->activity->log($issue, $actor, 'updated', ['sprint' => ['from' => $sprint->name, 'to' => $target?->name]]);
            }

            $sprint->update(['state' => SprintState::Completed, 'completed_at' => now(), 'active_project_id' => null]);

            return $open->count();
        });
    }

    /** The project's active sprint with issue/point stats, or null. */
    public function active(Project $project): ?Sprint
    {
        return $this->withStats($project->sprints()->getQuery(), $project)->where('state', SprintState::Active->value)->first();
    }

    /** Find a sprint by id or name (case-insensitive); 404 lists the project's sprints. */
    public function resolve(Project $project, string $ref): Sprint
    {
        $sprints = $project->sprints()->orderBy('id')->get();
        $sprint = $sprints->first(fn ($s) => (string) $s->id === trim($ref) || strcasecmp($s->name, trim($ref)) === 0);

        if (! $sprint) {
            $names = $sprints->pluck('name')->take(-30)->implode(', ') ?: 'none yet';
            throw ValidationException::withMessages([
                'sprint' => "Sprint \"{$ref}\" was not found in {$project->key}. Sprints: {$names}.",
            ])->status(404);
        }

        return $sprint->setRelation('project', $project);
    }

    /** Adds issue_count, points_total and points_done to a sprint query. */
    public function withStats(Builder $query, Project $project): Builder
    {
        $done = $project->doneColumnId();

        return $query
            ->withCount('issues')
            ->withSum('issues as points_total', 'story_points')
            ->withSum(['issues as points_done' => fn ($q) => $q->where('column_id', $done)], 'story_points');
    }

    private function resolveCompletionTarget(Sprint $sprint, string $moveTo): ?Sprint
    {
        if (strcasecmp(trim($moveTo), 'backlog') === 0) {
            return null;
        }

        $planned = $sprint->project->sprints()->where('state', SprintState::Planned)->orderBy('id')->get();
        $target = $planned->first(fn ($s) => (string) $s->id === trim($moveTo) || strcasecmp($s->name, trim($moveTo)) === 0);

        if (! $target) {
            throw ValidationException::withMessages([
                'move_open_issues_to' => "\"{$moveTo}\" is not a planned sprint. Valid targets: ".collect(['backlog'])->merge($planned->pluck('name'))->implode(', ').'.',
            ]);
        }

        return $target;
    }

    private function defaultName(Project $project): string
    {
        $n = $project->sprints()->count() + 1;
        while ($project->sprints()->where('name', "{$project->key} Sprint {$n}")->exists()) {
            $n++;
        }

        return "{$project->key} Sprint {$n}";
    }

    private function ensureUnique(Project $project, string $name, ?Sprint $except = null): void
    {
        if ($project->sprints()->where('name', trim($name))->when($except, fn ($q) => $q->whereKeyNot($except->id))->exists()) {
            throw ValidationException::withMessages(['name' => "{$project->key} already has a sprint named \"{$name}\"."]);
        }
    }

    private function ensureDateOrder(?string $start, ?string $end): void
    {
        if ($start && $end && Carbon::parse($end)->lt(Carbon::parse($start))) {
            throw ValidationException::withMessages(['end_date' => 'The end date must be on or after the start date.']);
        }
    }

    private function ensureNotCompleted(Sprint $sprint): void
    {
        if ($sprint->state === SprintState::Completed) {
            throw ValidationException::withMessages(['sprint' => "{$sprint->name} is completed and can no longer be changed."]);
        }
    }
}
