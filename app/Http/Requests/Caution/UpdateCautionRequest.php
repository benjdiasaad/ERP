<?php

declare(strict_types=1);

namespace App\Http\Requests\Caution;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCautionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'caution_type_id'   => ['sometimes', 'exists:caution_types,id'],
            'amount'            => ['sometimes', 'numeric', 'min:0'],
            'issue_date'        => ['sometimes', 'date'],
            'expiry_date'       => ['sometimes', 'date'],
            'bank_name'         => ['nullable', 'string', 'max:255'],
            'bank_account'      => ['nullable', 'string', 'max:255'],
            'bank_reference'    => ['nullable', 'string', 'max:255'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
