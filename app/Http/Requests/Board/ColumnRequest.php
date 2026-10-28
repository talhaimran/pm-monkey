<?php

namespace App\Http\Requests\Board;

use Illuminate\Foundation\Http\FormRequest;

class ColumnRequest extends FormRequest
{
    public function rules(): array
    {
        return self::rulesFor(creating: $this->isMethod('post'));
    }

    public static function rulesFor(bool $creating): array
    {
        return [
            'name' => [...($creating ? ['required'] : ['sometimes', 'required']), 'string', 'max:50'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
