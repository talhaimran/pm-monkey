<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Activity */
class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'changes' => $this->changes,
            'user' => IssueResource::user($this->user),
            'via' => $this->via,
            'token_name' => $this->token_name,
            'created_at' => $this->created_at,
        ];
    }
}
