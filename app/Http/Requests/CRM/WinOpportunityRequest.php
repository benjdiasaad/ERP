<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class WinOpportunityRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'value'             => ['nullable', 'numeric', 'min:0'],
            'actual_close_date' => ['nullable', 'date'],
            'won_quote_id'      => ['nullable', $this->inCompany('quotes')],
        ];
    }
}
