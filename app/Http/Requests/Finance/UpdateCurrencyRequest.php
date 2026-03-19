<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'           => ['sometimes', 'string', 'size:3', 'uppercase'],
            'name'           => ['sometimes', 'string', 'max:255'],
            'symbol'         => ['sometimes', 'string', 'max:10'],
            'exchange_rate'  => ['sometimes', 'numeric', 'min:0'],
            'is_default'     => ['boolean'],
            'is_active'      => ['boolean'],
        ];
    }
}
