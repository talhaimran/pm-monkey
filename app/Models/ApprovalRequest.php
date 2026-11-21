<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spec 8 (A2): an agent's move that needs a person. Before step 1, `action` is
 * {to_column_id, to_column, from_column_id, reason} and "Done" is the project's last column.
 */
#[Fillable(['workspace_id', 'issue_id', 'agent_id', 'owner_user_id', 'action', 'issue_version', 'status', 'decided_by', 'decided_at', 'note', 'expires_at'])]
class ApprovalRequest extends Model
{
    public const DAYS = 7;

    protected function casts(): array
    {
        return [
            'action' => 'array',
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Pending and not past its expiry (the hourly sweep may not have marked it yet). */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending->value)->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === ApprovalStatus::Pending && $this->expires_at->isFuture();
    }
}
