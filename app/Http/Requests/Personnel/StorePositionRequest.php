<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth()->user()?->current_company_id;

        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name'          => ['required', 'string', 'max:255'],
            'code'          => [
                'required',
                'string',
                'max:50',
                Rule::unique('positions', 'code')->where('company_id', $companyId),
            ],
            'description'   => ['nullable', 'string'],
            'min_salary'    => ['nullable', 'numeric', 'min:0'],
            'max_salary'    => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'is_active'     => ['boolean'],
        ];
    }
}
