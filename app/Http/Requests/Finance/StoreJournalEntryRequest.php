<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'          => ['required', 'date'],
            'description'   => ['required', 'string', 'max:1000'],
            'lines'         => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Journal entry must have at least 2 lines.',
            'lines.min' => 'Journal entry must have at least 2 lines.',
        ];
    }
}
