<?php

declare(strict_types=1);

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name'          => ['nullable', 'string', 'max:255'],
            'tax_id'              => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'address'             => ['nullable', 'array'],
            'address.street'      => ['nullable', 'string', 'max:255'],
            'address.city'        => ['nullable', 'string', 'max:100'],
            'address.state'       => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country'     => ['nullable', 'string', 'max:100'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'fiscal_year_start'   => ['nullable', 'integer', 'min:1', 'max:12'],
            'logo'                => ['nullable', 'string', 'max:255'],
            'settings'            => ['nullable', 'array'],
            'is_active'           => ['boolean'],
        ];
    }
}
