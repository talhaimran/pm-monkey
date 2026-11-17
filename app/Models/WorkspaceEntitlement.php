<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One limit of a workspace (spec 7.0c); read and written through App\Services\Entitlements, which casts the value. */
#[Fillable(['workspace_id', 'key', 'value', 'source'])]
class WorkspaceEntitlement extends Model {}
