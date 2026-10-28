<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Compact issue summary for lists returned to agents (see BoardResource::card). */
class IssueCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...BoardResource::card($this->resource), 'sprint' => $this->sprint?->name];
    }
}
