<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'type'        => ['sometimes', 'in:cash,check,bank_transfer,credit_card,mobile,other'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['boolean'],
        ];
    }
}
