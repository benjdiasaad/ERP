<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class ConvertLeadRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'title'               => ['nullable', 'string', 'max:255'],
            'value'               => ['nullable', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'between:0,100'],
            'customer_id'         => ['nullable', $this->inCompany('customers')],
            'expected_close_date' => ['nullable', 'date'],
            'stage'               => ['nullable', 'in:prospecting,qualification,proposal,negotiation'],
        ];
    }
}
