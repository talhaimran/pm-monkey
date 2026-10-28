<?php

namespace App\Http\Resources;

use App\Models\ProjectInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectInvitation */
class ProjectInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role->value,
            'project' => $this->whenLoaded('project', fn () => ['key' => $this->project->key, 'name' => $this->project->name]),
            'created_at' => $this->created_at,
        ];
    }
}
