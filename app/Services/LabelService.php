<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

class LabelService
{
    /** @param array{name: string, color?: ?string} $data */
    public function create(Project $project, array $data): Label
    {
        $this->ensureUnique($project, $data['name']);

        return $project->labels()->create(['name' => trim($data['name']), 'color' => $data['color'] ?? '#6b7280']);
    }

    /** @param array{name?: string, color?: ?string} $data */
    public function update(Label $label, array $data): Label
    {
        if (isset($data['name'])) {
            $this->ensureUnique($label->project, $data['name'], $label);
            $data['name'] = trim($data['name']);
        }
        $label->update(array_filter($data, fn ($v) => $v !== null));

        return $label;
    }

    public function delete(Label $label): void
    {
        $label->delete();
    }

    /** Find a label by name (case-insensitive); 404 lists the project's labels. */
    public function resolve(Project $project, string $name): Label
    {
        $label = $project->labels()->where('name', trim($name))->first();

        if ($label) {
            return $label->setRelation('project', $project);
        }

        $names = $project->labels()->pluck('name')->implode(', ') ?: 'none yet';

        throw ValidationException::withMessages([
            'label' => "Label \"{$name}\" was not found in {$project->key}. Labels: {$names}.",
        ])->status(404);
    }

    private function ensureUnique(Project $project, string $name, ?Label $except = null): void
    {
        // Case-insensitive via MySQL collation.
        $exists = $project->labels()->where('name', trim($name))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => "{$project->key} already has a label named \"{$name}\"."]);
        }
    }
}
