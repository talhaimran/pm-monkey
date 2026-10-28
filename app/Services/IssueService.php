<?php

namespace App\Services;

use App\Enums\IssuePriority;
use App\Enums\IssueType;
use App\Enums\SprintState;
use App\Models\Column;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueService
{
    /** Relations needed to render an issue (IssueResource) and its activity diff. */
    public const DISPLAY = ['project', 'column', 'assignee', 'reporter', 'epic', 'parent', 'labels', 'sprint'];

    public function __construct(
        private ProjectService $projects,
        private ActivityLogger $activity,
        private RankService $ranks,
    ) {}

    /**
     * Create an issue. $data uses the shared input shape: status = column name, assignee = email,
     * labels = names, epic/parent = issue keys.
     */
    public function create(Project $project, User $actor, array $data): Issue
    {
        return DB::transaction(function () use ($project, $actor, $data) {
            [$attributes, $labelIds] = $this->resolveInput($project, $data, null);

            $issue = Issue::create([
                'type' => IssueType::Task,
                'priority' => IssuePriority::Medium,
                'column_id' => $project->columns()->value('id'),
                ...$attributes,
                'project_id' => $project->id,
                'number' => $this->nextNumber($project),
                'rank' => $this->ranks->bottom($project),
                'reporter_id' => $actor->id,
            ]);
            $issue->labels()->sync($labelIds ?? []);
            $issue->setRelation('project', $project);
            $this->activity->log($issue, $actor, 'created');

            return $issue->load(array_slice(self::DISPLAY, 1));
        });
    }

    public function update(Issue $issue, User $actor, array $data): Issue
    {
        return DB::transaction(function () use ($issue, $actor, $data) {
            $issue->loadMissing(self::DISPLAY);
            $before = $this->snapshot($issue);
            [$attributes, $labelIds] = $this->resolveInput($issue->project, $data, $issue);

            $issue->update($attributes);
            if ($labelIds !== null) {
                $issue->labels()->sync($labelIds);
            }
            $issue->load(array_slice(self::DISPLAY, 1));

            $after = $this->snapshot($issue);
            $changes = collect($before)
                ->filter(fn ($value, $field) => $value !== $after[$field])
                ->map(fn ($value, $field) => ['from' => $value, 'to' => $after[$field]])
                ->all();

            if ($changes) {
                $this->activity->log($issue, $actor, 'updated', $changes);
            }

            return $issue;
        });
    }

    /** Sub-tasks cascade at the DB level; issues in a deleted epic are unlinked. */
    public function delete(Issue $issue): void
    {
        $issue->delete();
    }

    /** Find an issue by key ("WEB-12", case-insensitive) in a project the user belongs to. */
    public function resolve(User $user, string $key): Issue
    {
        if (! preg_match('/^([A-Za-z][A-Za-z0-9]{1,9})-(\d+)$/', trim($key), $m)) {
            throw ValidationException::withMessages([
                'issue' => "\"{$key}\" is not an issue key. Issue keys look like WEB-12.",
            ])->status(404);
        }

        $project = $this->projects->resolve($user, $m[1]);
        $issue = $project->issues()->where('number', $m[2])->first();

        if (! $issue) {
            throw ValidationException::withMessages([
                'issue' => "Issue {$project->key}-{$m[2]} was not found. Search the project's issues to find the right key.",
            ])->status(404);
        }

        return $issue->setRelation('project', $project);
    }

    /** Load what IssueResource needs for the full single-issue view. */
    public function loadFull(Issue $issue): Issue
    {
        return $issue->loadMissing(self::DISPLAY)->load([
            'subtasks' => fn ($q) => $q->with('column')->orderBy('number'),
            'epicIssues' => fn ($q) => $q->with('column')->orderBy('number')->limit(100),
            'comments' => fn ($q) => $q->with('author')->oldest()->limit(100),
        ]);
    }

    /** Sort keys accepted by sort(); prefix with "-" for descending. */
    public const SORTS = ['key', 'title', 'type', 'priority', 'status', 'assignee', 'story_points', 'due_date', 'created', 'updated'];

    /**
     * Apply board/list/search filters: assignee (email or "unassigned"), type, priority, label (name),
     * sprint (name, "active" or "backlog"), q (title text, or an issue key such as WEB-12).
     */
    public function filter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['assignee'] ?? null, fn ($q, $assignee) => strtolower($assignee) === 'unassigned'
                ? $q->whereNull('assignee_id')
                : $q->whereHas('assignee', fn ($u) => $u->where('email', $assignee)))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($filters['sprint'] ?? null, fn ($q, $sprint) => match (strtolower($sprint)) {
                'backlog' => $q->whereNull('sprint_id'),
                'active' => $q->whereHas('sprint', fn ($s) => $s->where('state', SprintState::Active->value)),
                default => $q->whereHas('sprint', fn ($s) => $s->where('name', $sprint)),
            })
            ->when($filters['label'] ?? null, fn ($q, $label) => $q->whereHas('labels', fn ($l) => $l->where('name', $label)))
            ->when(trim($filters['q'] ?? ''), fn ($q, $search) => preg_match('/^([A-Za-z][A-Za-z0-9]{1,9})-(\d+)$/', $search, $m)
                ? $q->where('number', $m[2])->whereHas('project', fn ($p) => $p->where('key', $m[1]))
                : $q->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'));
    }

    /**
     * Filtered, sorted, paginated issues in one project, or across all of the user's projects.
     * $params: filters (see filter()), sort, per_page (max 100), page.
     */
    public function list(User $user, ?Project $project, array $params): LengthAwarePaginator
    {
        $query = $project
            ? $project->issues()->getQuery()
            : Issue::whereIn('project_id', $user->projects()->select('projects.id'));

        return $this->sort($this->filter($query, $params), $params['sort'] ?? null)
            ->with(self::DISPLAY)
            ->paginate(min((int) ($params['per_page'] ?? 25), 100), page: $params['page'] ?? null);
    }

    /** Order by one of SORTS ("-" prefix = descending); ties break by id in the same direction. */
    public function sort(Builder $query, ?string $sort): Builder
    {
        $sort = $sort ?: '-key';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        match ($field) {
            'key' => $query->orderBy('issues.project_id', $direction)->orderBy('issues.number', $direction),
            'priority' => $query->orderByRaw('FIELD(issues.priority, '.$this->quoted(IssuePriority::values()).') '.$direction),
            'type' => $query->orderByRaw('FIELD(issues.type, '.$this->quoted(IssueType::values()).') '.$direction),
            'status' => $query->orderBy(Column::select('position')->whereColumn('columns.id', 'issues.column_id'), $direction),
            'assignee' => $query->orderBy(User::select('name')->whereColumn('users.id', 'issues.assignee_id'), $direction),
            'created' => $query->orderBy('issues.created_at', $direction),
            'updated' => $query->orderBy('issues.updated_at', $direction),
            default => $query->orderBy("issues.{$field}", $direction),
        };

        return $query->orderBy('issues.id', $direction);
    }

    private function quoted(array $values): string
    {
        return collect($values)->map(fn ($v) => "'{$v}'")->implode(', ');
    }

    private function nextNumber(Project $project): int
    {
        // Row lock serialises concurrent creates in the same project; unique(project_id, number) is the backstop.
        $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
        $locked->increment('issue_counter');

        return $locked->issue_counter;
    }

    /**
     * Turn the shared input shape into column values. Only keys present in $data are touched.
     *
     * @return array{0: array<string, mixed>, 1: ?list<int>}
     */
    private function resolveInput(Project $project, array $data, ?Issue $issue): array
    {
        $attributes = collect($data)->only(['title', 'description', 'story_points', 'due_date'])->all();
        $labelIds = null;

        if (isset($data['type'])) {
            $attributes['type'] = IssueType::from($data['type']);
        }
        if (isset($data['priority'])) {
            $attributes['priority'] = IssuePriority::from($data['priority']);
        }
        if (isset($data['status'])) {
            $attributes['column_id'] = $this->resolveColumnId($project, $data['status']);
        }
        if (array_key_exists('assignee', $data)) {
            $attributes['assignee_id'] = $data['assignee'] === null ? null : $this->resolveAssigneeId($project, $data['assignee']);
        }
        if (array_key_exists('sprint', $data)) {
            $attributes['sprint_id'] = $data['sprint'] === null ? null : $this->resolveOpenSprintId($project, (string) $data['sprint']);
        }
        if (array_key_exists('labels', $data)) {
            $labelIds = $this->resolveLabelIds($project, $data['labels'] ?? []);
        }

        $epic = $parent = null;
        if (array_key_exists('epic', $data)) {
            $epic = $data['epic'] === null ? null : $this->resolveLinked($project, $data['epic'], 'epic');
            $attributes['epic_id'] = $epic?->id;
        }
        if (array_key_exists('parent', $data)) {
            $parent = $data['parent'] === null ? null : $this->resolveLinked($project, $data['parent'], 'parent');
            $attributes['parent_id'] = $parent?->id;
        }

        if (array_intersect(['type', 'epic', 'parent'], array_keys($data))) {
            $this->validateLinks($project, $issue, $attributes, $epic, $parent);
        }

        return [$attributes, $labelIds];
    }

    private function validateLinks(Project $project, ?Issue $issue, array $attributes, ?Issue $epic, ?Issue $parent): void
    {
        $type = $attributes['type'] ?? $issue?->type ?? IssueType::Task;
        $epic ??= array_key_exists('epic_id', $attributes) ? null : $issue?->epic;
        $parent ??= array_key_exists('parent_id', $attributes) ? null : $issue?->parent;
        $key = fn (Issue $i) => "{$project->key}-{$i->number}";
        $fail = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($epic) {
            if ($type === IssueType::Epic) {
                $fail('epic', 'Epics cannot belong to another epic.');
            }
            if ($epic->type !== IssueType::Epic) {
                $fail('epic', "{$key($epic)} is a {$epic->type->value}, not an epic. Use the parent field to make a sub-task.");
            }
        }

        if ($parent) {
            if ($type === IssueType::Epic) {
                $fail('parent', 'Epics cannot be sub-tasks.');
            }
            if ($issue && $parent->is($issue)) {
                $fail('parent', 'An issue cannot be its own parent.');
            }
            if ($parent->type === IssueType::Epic) {
                $fail('parent', "{$key($parent)} is an epic. Use the epic field to link to an epic.");
            }
            if ($parent->parent_id) {
                $fail('parent', "{$key($parent)} is already a sub-task; sub-tasks are only one level deep.");
            }
            if ($issue?->subtasks()->exists()) {
                $fail('parent', "{$key($issue)} has sub-tasks, so it cannot become a sub-task.");
            }
        }

        if ($issue && $type === IssueType::Epic && $issue->type !== IssueType::Epic && $issue->subtasks()->exists()) {
            $fail('type', "{$key($issue)} has sub-tasks, so it cannot become an epic.");
        }
        if ($issue && $type !== IssueType::Epic && $issue->type === IssueType::Epic && $issue->epicIssues()->exists()) {
            $fail('type', "{$key($issue)} has issues linked to it as an epic; unlink them before changing its type.");
        }
    }

    private function resolveOpenSprintId(Project $project, string $ref): int
    {
        $sprints = $project->sprints()->orderBy('id')->get(['id', 'name', 'state']);
        $sprint = $sprints->first(fn ($s) => (string) $s->id === trim($ref) || strcasecmp($s->name, trim($ref)) === 0);
        $open = $sprints->where('state', '!=', SprintState::Completed);

        if (! $sprint || $sprint->state === SprintState::Completed) {
            $reason = $sprint ? "{$sprint->name} is completed" : "Unknown sprint \"{$ref}\"";
            $valid = $open->isEmpty() ? "{$project->key} has no open sprints; create one first" : 'Open sprints: '.$open->pluck('name')->implode(', ');
            throw ValidationException::withMessages(['sprint' => "{$reason}. {$valid}. Use null for the backlog."]);
        }

        return $sprint->id;
    }

    private function resolveColumnId(Project $project, string $name): int
    {
        $columns = $project->columns()->get(['id', 'name']);
        $column = $columns->first(fn ($c) => strcasecmp($c->name, trim($name)) === 0);

        if (! $column) {
            throw ValidationException::withMessages([
                'status' => "Unknown status \"{$name}\". Valid statuses for {$project->key}: ".$columns->pluck('name')->implode(', ').'.',
            ]);
        }

        return $column->id;
    }

    private function resolveAssigneeId(Project $project, string $email): int
    {
        $id = $project->members()->where('email', strtolower(trim($email)))->value('users.id');

        if (! $id) {
            $emails = $project->members()->orderBy('email')->limit(50)->pluck('email')->implode(', ');
            throw ValidationException::withMessages([
                'assignee' => "{$email} is not a member of {$project->key}. Assign one of: {$emails}.",
            ]);
        }

        return $id;
    }

    /** @return list<int> */
    private function resolveLabelIds(Project $project, array $names): array
    {
        $labels = $project->labels()->get(['id', 'name']);
        $byName = $labels->keyBy(fn ($l) => Str::lower($l->name));
        $missing = collect($names)->reject(fn ($n) => $byName->has(Str::lower(trim($n))));

        if ($missing->isNotEmpty()) {
            $valid = $labels->isEmpty() ? "{$project->key} has no labels yet" : "Labels in {$project->key}: ".$labels->pluck('name')->implode(', ');
            throw ValidationException::withMessages([
                'labels' => 'Unknown labels: '.$missing->implode(', ')."; {$valid}. Create a label first to use it.",
            ]);
        }

        return collect($names)->map(fn ($n) => $byName[Str::lower(trim($n))]->id)->unique()->values()->all();
    }

    private function resolveLinked(Project $project, string $key, string $field): Issue
    {
        $prefix = $project->key.'-';
        $number = Str::startsWith(Str::upper(trim($key)), $prefix) ? Str::after(Str::upper(trim($key)), $prefix) : null;
        $linked = ctype_digit((string) $number) ? $project->issues()->where('number', $number)->first() : null;

        if (! $linked) {
            throw ValidationException::withMessages([
                $field => "Issue \"{$key}\" was not found in {$project->key}. Links must point to an issue in the same project (e.g. {$prefix}1).",
            ]);
        }

        return $linked;
    }

    /** Human-readable values used for activity diffs. */
    private function snapshot(Issue $issue): array
    {
        $key = fn (?Issue $i) => $i ? "{$issue->project->key}-{$i->number}" : null;

        return [
            'title' => $issue->title,
            'description' => $issue->description === null ? null : Str::limit($issue->description, 200),
            'type' => $issue->type->value,
            'priority' => $issue->priority->value,
            'status' => $issue->column->name,
            'assignee' => $issue->assignee?->email,
            'labels' => $issue->labels->pluck('name')->sort()->values()->implode(', ') ?: null,
            'story_points' => $issue->story_points,
            'due_date' => $issue->due_date?->format('Y-m-d'),
            'epic' => $key($issue->epic),
            'parent' => $key($issue->parent),
            'sprint' => $issue->sprint?->name,
        ];
    }
}
