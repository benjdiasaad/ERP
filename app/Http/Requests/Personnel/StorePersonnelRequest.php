<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth()->user()?->current_company_id;

        return [
            'user_id'                   => ['nullable', 'integer', 'exists:users,id'],
            'department_id'             => ['nullable', 'integer', 'exists:departments,id'],
            'position_id'               => ['nullable', 'integer', 'exists:positions,id'],
            'matricule'                 => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('personnels', 'matricule')->where('company_id', $companyId),
            ],
            'first_name'                => ['required', 'string', 'max:255'],
            'last_name'                 => ['required', 'string', 'max:255'],
            'email'                     => ['nullable', 'email', 'max:255'],
            'phone'                     => ['nullable', 'string', 'max:50'],
            'mobile'                    => ['nullable', 'string', 'max:50'],
            'gender'                    => ['nullable', Rule::in(['male', 'female', 'other'])],
            'birth_date'                => ['nullable', 'date'],
            'birth_place'               => ['nullable', 'string', 'max:255'],
            'nationality'               => ['nullable', 'string', 'max:100'],
            'national_id'               => ['nullable', 'string', 'max:100'],
            'social_security_number'    => ['nullable', 'string', 'max:100'],
            'address'                   => ['nullable', 'string'],
            'city'                      => ['nullable', 'string', 'max:100'],
            'country'                   => ['nullable', 'string', 'max:100'],
            'photo_path'                => ['nullable', 'string', 'max:255'],
            'employment_type'           => ['nullable', Rule::in(['full_time', 'part_time', 'freelance', 'intern'])],
            'hire_date'                 => ['nullable', 'date'],
            'termination_date'          => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status'                    => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'terminated'])],
            'bank_name'                 => ['nullable', 'string', 'max:255'],
            'bank_account'              => ['nullable', 'string', 'max:100'],
            'bank_iban'                 => ['nullable', 'string', 'max:50'],
            'emergency_contact_name'    => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone'   => ['nullable', 'string', 'max:50'],
            'emergency_contact_relation'=> ['nullable', 'string', 'max:100'],
            'notes'                     => ['nullable', 'string'],
        ];
    }
}
