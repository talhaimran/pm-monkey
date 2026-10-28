<?php

namespace App\Http\Resources;

use App\Models\Column;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Column */
class ColumnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'issue_count' => $this->whenCounted('issues'),
        ];
    }
}
