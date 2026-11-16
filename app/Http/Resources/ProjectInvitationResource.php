<?php

namespace App\Http\Resources;

use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkspaceInvitation */
class ProjectInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->project_role?->value,
            'project' => $this->whenLoaded('project', fn () => ['key' => $this->project->key, 'name' => $this->project->name]),
            'created_at' => $this->created_at,
        ];
    }
}
