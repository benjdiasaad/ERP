<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth()->user()?->current_company_id;

        return [
            'name'        => ['required', 'string', 'max:255'],
            'code'        => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->where('company_id', $companyId),
            ],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'manager_id'  => ['nullable', 'integer', 'exists:personnels,id'],
            'is_active'   => ['boolean'],
        ];
    }
}
