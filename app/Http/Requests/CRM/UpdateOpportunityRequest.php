<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class UpdateOpportunityRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'title'               => ['sometimes', 'string', 'max:255'],
            'contact_id'          => ['nullable', $this->inCompany('contacts')],
            'customer_id'         => ['nullable', $this->inCompany('customers')],
            'description'         => ['nullable', 'string', 'max:5000'],
            'value'               => ['nullable', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'between:0,100'],
            'expected_close_date' => ['nullable', 'date'],
            'assigned_to'         => ['nullable', $this->inCompany('company_user', 'user_id', false)],
            'tags'                => ['nullable', 'array'],
            'tags.*'              => ['string', 'max:50'],
        ];
    }
}
