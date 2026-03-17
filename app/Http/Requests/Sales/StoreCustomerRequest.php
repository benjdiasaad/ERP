<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'             => ['required', 'in:individual,company'],
            'name'             => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'first_name'       => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'last_name'        => ['required_if:type,individual', 'nullable', 'string', 'max:100'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'mobile'           => ['nullable', 'string', 'max:30'],
            'address'          => ['nullable', 'string', 'max:500'],
            'city'             => ['nullable', 'string', 'max:100'],
            'state'            => ['nullable', 'string', 'max:100'],
            'country'          => ['nullable', 'string', 'max:100'],
            'postal_code'      => ['nullable', 'string', 'max:20'],
            'tax_id'           => ['nullable', 'string', 'max:50'],
            'ice'              => ['nullable', 'string', 'max:50'],
            'rc'               => ['nullable', 'string', 'max:50'],
            'payment_terms_id' => ['nullable', 'integer', 'exists:payment_terms,id'],
            'credit_limit'     => ['nullable', 'numeric', 'min:0'],
            'currency_id'      => ['nullable', 'integer', 'exists:currencies,id'],
            'notes'            => ['nullable', 'string'],
            'is_active'        => ['boolean'],
        ];
    }
}
