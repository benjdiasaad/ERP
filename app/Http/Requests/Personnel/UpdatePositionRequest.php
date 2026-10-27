<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId  = auth()->user()?->current_company_id;
        $positionId = $this->route('position')?->id;

        return [
            'department_id' => ['sometimes', 'integer', $this->inCompany('departments')],
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
            'salary_min'    => ['nullable', 'numeric', 'min:0'],
            'salary_max'    => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'is_active'     => ['boolean'],
        ];
    }
}
