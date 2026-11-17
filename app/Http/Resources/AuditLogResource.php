<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'actor' => $this->actor?->name,
            'actor_type' => $this->actor_type,
            'via' => $this->via,
            'action' => $this->action,
            'target_type' => $this->target_type,
            'target_label' => $this->target_label,
            'metadata' => $this->metadata,
            'ip' => $this->ip,
        ];
    }
}
