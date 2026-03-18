<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'type'                => ['required', 'in:individual,company'],
            'email'               => ['nullable', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'mobile'              => ['nullable', 'string', 'max:30'],
            'address'             => ['nullable', 'string', 'max:500'],
            'city'                => ['nullable', 'string', 'max:100'],
            'state'               => ['nullable', 'string', 'max:100'],
            'country'             => ['nullable', 'string', 'max:100'],
            'postal_code'         => ['nullable', 'string', 'max:20'],
            'tax_id'              => ['nullable', 'string', 'max:50'],
            'ice'                 => ['nullable', 'string', 'max:50'],
            'rc'                  => ['nullable', 'string', 'max:50'],
            'payment_term_id'     => ['nullable', 'integer', 'exists:payment_terms,id'],
            'credit_limit'        => ['nullable', 'numeric', 'min:0'],
            'bank_name'           => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:100'],
            'bank_iban'           => ['nullable', 'string', 'max:50'],
            'bank_swift'          => ['nullable', 'string', 'max:20'],
            'notes'               => ['nullable', 'string'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }
}
