<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'bank'           => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'swift'          => ['nullable', 'string', 'max:11'],
            'currency_id'    => ['required', 'integer', 'exists:currencies,id'],
            'balance'        => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ];
    }
}
