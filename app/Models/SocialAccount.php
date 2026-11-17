<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Google or GitHub account linked to a user (spec 7.0f). Holds no provider token. */
#[Fillable(['provider', 'provider_user_id', 'email'])]
class SocialAccount extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
