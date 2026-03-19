<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payable_type'      => ['required', 'string', 'in:App\Models\Sales\Invoice,App\Models\Purchasing\PurchaseInvoice'],
            'payable_id'        => ['required', 'integer'],
            'direction'         => ['required', 'string', 'in:incoming,outgoing'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'bank_account_id'   => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'payment_date'      => ['required', 'date'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
