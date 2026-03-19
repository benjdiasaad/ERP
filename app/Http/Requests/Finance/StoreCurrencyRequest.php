<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'size:3', 'uppercase'],
            'name'           => ['required', 'string', 'max:255'],
            'symbol'         => ['required', 'string', 'max:10'],
            'exchange_rate'  => ['required', 'numeric', 'min:0'],
            'is_default'     => ['boolean'],
            'is_active'      => ['boolean'],
        ];
    }
}
