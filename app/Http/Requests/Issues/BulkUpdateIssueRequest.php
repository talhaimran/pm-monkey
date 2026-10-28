<?php

namespace App\Http\Requests\Issues;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateIssueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'updates' => ['required', 'array', 'min:1', 'max:'.BulkStoreIssueRequest::MAX],
            'updates.*' => ['array'],
            'updates.*.key' => ['required', 'string', 'max:30'],
            ...collect((new UpdateIssueRequest)->rules())->mapWithKeys(fn ($rules, $field) => ["updates.*.{$field}" => $rules])->all(),
        ];
    }

    public function messages(): array
    {
        return [
            'updates.max' => 'Send at most '.BulkStoreIssueRequest::MAX.' updates per request; split larger batches.',
            ...collect((new UpdateIssueRequest)->messages())->mapWithKeys(fn ($m, $rule) => ["updates.*.{$rule}" => $m])->all(),
        ];
    }
}
