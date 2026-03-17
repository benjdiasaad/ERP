<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'             => ['sometimes', 'in:individual,company'],
            'name'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'first_name'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name'        => ['sometimes', 'nullable', 'string', 'max:100'],
            'email'            => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone'            => ['sometimes', 'nullable', 'string', 'max:30'],
            'mobile'           => ['sometimes', 'nullable', 'string', 'max:30'],
            'address'          => ['sometimes', 'nullable', 'string', 'max:500'],
            'city'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'country'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'tax_id'           => ['sometimes', 'nullable', 'string', 'max:50'],
            'ice'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'rc'               => ['sometimes', 'nullable', 'string', 'max:50'],
            'payment_terms_id' => ['sometimes', 'nullable', 'integer', 'exists:payment_terms,id'],
            'credit_limit'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency_id'      => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'notes'            => ['sometimes', 'nullable', 'string'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }
}
