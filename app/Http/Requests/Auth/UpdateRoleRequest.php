<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        
        $companyId = auth()->user()?->current_company_id;

        $roleId = $this->route('role')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9_\-]+$/',
                "unique:roles,slug,{$roleId},id,company_id,{$companyId}",
            ],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, hyphens, and underscores.',
            'slug.unique' => 'A role with this slug already exists in your company.',
        ];
    }
}
