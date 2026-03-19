<?php

declare(strict_types=1);

namespace App\Http\Requests\Caution;

use Illuminate\Foundation\Http\FormRequest;

class StoreCautionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caution_type_id'   => ['required', 'exists:caution_types,id'],
            'direction'         => ['required', 'in:given,received'],
            'partner_type'      => ['required', 'in:customer,supplier,other'],
            'partner_id'        => ['required', 'integer'],
            'related_type'      => ['nullable', 'string', 'max:255'],
            'related_id'        => ['nullable', 'integer'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'issue_date'        => ['required', 'date'],
            'expiry_date'       => ['required', 'date', 'after:issue_date'],
            'bank_name'         => ['nullable', 'string', 'max:255'],
            'bank_account'      => ['nullable', 'string', 'max:255'],
            'bank_reference'    => ['nullable', 'string', 'max:255'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
