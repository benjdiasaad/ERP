<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId    = auth()->user()?->current_company_id;
        $departmentId = $this->route('department')?->id;

        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'code'        => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('departments', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($departmentId),
            ],
            'description' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'manager_id'  => ['nullable', 'integer', 'exists:personnels,id'],
            'is_active'   => ['boolean'],
        ];
    }
}
