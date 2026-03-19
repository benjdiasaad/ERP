<?php

declare(strict_types=1);

namespace App\Http\Requests\Caution;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCautionTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['sometimes', 'string', 'max:255'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'default_percentage'   => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
