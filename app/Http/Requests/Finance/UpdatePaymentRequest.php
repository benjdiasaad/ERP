<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'            => ['sometimes', 'numeric', 'min:0.01'],
            'payment_method_id' => ['sometimes', 'integer', 'exists:payment_methods,id'],
            'bank_account_id'   => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'payment_date'      => ['sometimes', 'date'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
