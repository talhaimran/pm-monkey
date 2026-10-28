<?php

namespace App\Services;

use App\Models\Column;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ColumnService
{
    /** @param array{name: string, position?: ?int} $data */
    public function create(Project $project, array $data): Column
    {
        $this->ensureUnique($project, $data['name']);

        return DB::transaction(function () use ($project, $data) {
            $columns = $project->columns()->lockForUpdate()->get();
            $column = $project->columns()->create(['name' => trim($data['name']), 'position' => $columns->count()]);
            $this->resequence($columns, $column, $data['position'] ?? $columns->count());

            return $column;
        });
    }

    /** @param array{name?: string, position?: ?int} $data */
    public function update(Column $column, array $data): Column
    {
        $project = $column->project;

        if (isset($data['name'])) {
            $this->ensureUnique($project, $data['name'], $column);
        }

        return DB::transaction(function () use ($project, $column, $data) {
            if (isset($data['name'])) {
                $column->update(['name' => trim($data['name'])]);
            }
            if (isset($data['position'])) {
                $others = $project->columns()->lockForUpdate()->get()->reject(fn ($c) => $c->is($column));
                $this->resequence($others, $column, $data['position']);
            }

            return $column->refresh();
        });
    }

    /** Delete a column; its issues move to $moveTo (required when it has any). */
    public function delete(Column $column, ?string $moveTo): void
    {
        $project = $column->project;
        $others = $project->columns()->get()->reject(fn ($c) => $c->is($column))->values();

        if ($others->isEmpty()) {
            throw ValidationException::withMessages(['column' => "{$project->key} needs at least one column; add another before deleting \"{$column->name}\"."]);
        }

        $count = $column->issues()->count();
        $target = null;

        if ($count > 0) {
            $target = $moveTo ? $others->first(fn ($c) => strcasecmp($c->name, trim($moveTo)) === 0) : null;
            if (! $target) {
                throw ValidationException::withMessages([
                    'move_issues_to' => "\"{$column->name}\" has {$count} issues. Pass move_issues_to with one of: ".$others->pluck('name')->implode(', ').'.',
                ]);
            }
        }

        DB::transaction(function () use ($column, $target, $others) {
            if ($target) {
                $column->issues()->update(['column_id' => $target->id]);
            }
            $column->delete();
            $this->resequence($others);
        });
    }

    /** Find a column by name (case-insensitive); 404 lists the project's columns. */
    public function resolve(Project $project, string $name): Column
    {
        $columns = $project->columns()->get();
        $column = $columns->first(fn ($c) => strcasecmp($c->name, trim($name)) === 0);

        if (! $column) {
            throw ValidationException::withMessages([
                'column' => "Column \"{$name}\" was not found in {$project->key}. Columns: ".$columns->pluck('name')->implode(', ').'.',
            ])->status(404);
        }

        return $column->setRelation('project', $project);
    }

    /** Write positions 0..n, optionally inserting $insert at $position. */
    private function resequence(Collection $columns, ?Column $insert = null, ?int $position = null): void
    {
        $ordered = $columns->sortBy('position')->values();

        if ($insert) {
            $ordered->splice(max(0, min($position, $ordered->count())), 0, [$insert]);
        }

        foreach ($ordered as $i => $column) {
            if ($column->position !== $i || $column->is($insert)) {
                $column->update(['position' => $i]);
            }
        }
    }

    private function ensureUnique(Project $project, string $name, ?Column $except = null): void
    {
        // Case-insensitive via MySQL collation.
        if ($project->columns()->where('name', trim($name))->when($except, fn ($q) => $q->whereKeyNot($except->id))->exists()) {
            throw ValidationException::withMessages(['name' => "{$project->key} already has a column named \"{$name}\"."]);
        }
    }
}
