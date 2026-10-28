<?php

namespace App\Http\Requests\Issues;

use Illuminate\Foundation\Http\FormRequest;

class BulkStoreIssueRequest extends FormRequest
{
    public const MAX = 50;

    public function rules(): array
    {
        $item = new StoreIssueRequest;

        return [
            'issues' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'issues.*' => ['array'],
            ...collect($item->rules())->mapWithKeys(fn ($rules, $field) => ["issues.*.{$field}" => $rules])->all(),
        ];
    }

    public function messages(): array
    {
        return [
            'issues.max' => 'Send at most '.self::MAX.' issues per request; split larger batches.',
            ...collect((new StoreIssueRequest)->messages())->mapWithKeys(fn ($m, $rule) => ["issues.*.{$rule}" => $m])->all(),
        ];
    }
}
