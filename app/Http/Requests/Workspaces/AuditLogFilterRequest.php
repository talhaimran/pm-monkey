<?php

namespace App\Http\Requests\Workspaces;

use Illuminate\Foundation\Http\FormRequest;

/** Audit log filters: actor (user id), action group (the part before the dot), and a date range. */
class AuditLogFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'actor' => ['nullable', 'integer'],
            'group' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_]+$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
