<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Generated (EC-YYYY-NNNNN) when not given.
            'reference'     => ['nullable', 'string', 'max:50', Rule::unique('journal_entries', 'reference')->where('company_id', $this->user()->current_company_id)],
            'date'          => ['required', 'date'],
            'description'   => ['nullable', 'string', 'max:1000'],
            // Lines may be added later: posting requires balanced lines.
            'lines'         => ['nullable', 'array'],
            'lines.*.chart_of_account_id' => ['required', 'integer', $this->inCompany('chart_of_accounts')],
            'lines.*.debit'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.sort_order'  => ['nullable', 'integer'],
        ];
    }
}
