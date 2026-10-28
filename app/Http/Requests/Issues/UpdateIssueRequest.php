<?php

namespace App\Http\Requests\Issues;

class UpdateIssueRequest extends StoreIssueRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'title' => ['sometimes', 'required', 'string', 'max:255']];
    }
}
