<?php

namespace App\Http\Requests\Issues;

use Illuminate\Foundation\Http\FormRequest;

class LabelRequest extends FormRequest
{
    public function rules(): array
    {
        return self::rulesFor(creating: $this->isMethod('post'));
    }

    /** Creating needs a name; updates may send only a color. */
    public static function rulesFor(bool $creating): array
    {
        return [
            'name' => [...($creating ? ['required'] : ['sometimes', 'required']), 'string', 'max:50'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return ['color.regex' => 'The color must be a hex color like #3b82f6.'];
    }
}
