<?php

namespace App\Http\Requests\Lists;

use App\Http\Requests\Board\BoardFilterRequest;
use App\Services\IssueService;
use Illuminate\Validation\Rule;

class IssueListRequest extends BoardFilterRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sort' => ['nullable', Rule::in(self::sorts())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'project' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'sort.in' => 'The sort must be one of: '.implode(', ', self::sorts()).'.'];
    }

    /** @return list<string> */
    public static function sorts(): array
    {
        return collect(IssueService::SORTS)->flatMap(fn ($s) => [$s, "-{$s}"])->all();
    }
}
