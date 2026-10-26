<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name'           => [$required, 'string', 'max:255'],
            'bank'           => [$required, 'string', 'max:255'],
            'account_number' => [$required, 'string', 'max:50'],
            'iban'           => ['nullable', 'string', 'max:34'],
            'swift'          => ['nullable', 'string', 'max:11'],
            'currency_id'    => [$required, 'integer', $this->inCompanyOrShared('currencies')],
            'balance'        => ['nullable', 'numeric', 'min:0'],
            'is_active'      => ['boolean'],
        ];
    }
}
