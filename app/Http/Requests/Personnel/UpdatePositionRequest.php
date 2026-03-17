<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId  = auth()->user()?->current_company_id;
        $positionId = $this->route('position')?->id;

        return [
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'name'          => ['sometimes', 'string', 'max:255'],
            'code'          => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('positions', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($positionId),
            ],
            'description'   => ['nullable', 'string'],
            'min_salary'    => ['nullable', 'numeric', 'min:0'],
            'max_salary'    => ['nullable', 'numeric', 'min:0', 'gte:min_salary'],
            'is_active'     => ['boolean'],
        ];
    }
}
