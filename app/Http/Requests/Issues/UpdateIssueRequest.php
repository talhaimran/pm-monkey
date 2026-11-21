<?php

namespace App\Http\Requests\Issues;

class UpdateIssueRequest extends StoreIssueRequest
{
    public function rules(): array
    {
        // reason (A-L5): why an agent asks to move to Done, shown to the person who decides.
        return [...parent::rules(), 'title' => ['sometimes', 'required', 'string', 'max:255'], 'reason' => ['nullable', 'string', 'max:200']];
    }
}
