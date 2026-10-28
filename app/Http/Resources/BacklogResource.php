<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps BacklogService::backlog(): open sprints with issue cards, then the backlog. */
class BacklogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cards = fn ($issues) => $issues->map(fn ($i) => BoardResource::card($i))->values()->all();

        return [
            'sprints' => $this->resource['sprints']->map(fn ($sprint) => [
                ...(new SprintResource($sprint))->resolve($request),
                'issues' => $cards($sprint->issues),
            ])->values()->all(),
            'backlog' => ['issues' => $cards($this->resource['backlog'])],
            'truncated' => $this->resource['truncated'],
        ];
    }
}
