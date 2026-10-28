<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            // Listed via $user->projects the pivot already carries the role; otherwise look it up.
            // auth() rather than $request: MCP calls don't run through the HTTP request.
            'my_role' => $this->pivot->role ?? (auth()->user() ? $this->roleFor(auth()->user())?->value : null),
            // Resolved to a plain array: Inertia would wrap a nested resource in {data: [...]}.
            'members' => $this->whenLoaded('members', fn () => MemberResource::collection($this->members)->resolve()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
