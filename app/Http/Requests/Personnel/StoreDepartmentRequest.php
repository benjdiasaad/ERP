<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    use ScopesToCompany;

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
            'parent_id'   => ['nullable', 'integer', $this->inCompany('departments')],
            // The manager is a user account of the company (departments.manager_id → users).
            'manager_id'  => ['nullable', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $this->user()->current_company_id)],
            'is_active'   => ['boolean'],
        ];
    }
}
