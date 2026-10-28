<?php

namespace App\Http\Resources;

use App\Models\Sprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sprint */
class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'goal' => $this->goal,
            'state' => $this->state->value,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'issue_count' => $this->whenCounted('issues'),
            'points_total' => $this->whenHas('points_total', fn () => (int) $this->points_total),
            'points_done' => $this->whenHas('points_done', fn () => (int) $this->points_done),
        ];
    }
}
