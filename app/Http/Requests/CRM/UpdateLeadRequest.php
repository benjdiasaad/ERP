<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class UpdateLeadRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'title'               => ['sometimes', 'string', 'max:255'],
            'status'              => ['sometimes', 'in:new,contacted,unqualified,lost'],
            'lost_reason'         => ['nullable', 'string', 'max:2000'],
            'contact_id'          => ['nullable', $this->inCompany('contacts')],
            'description'         => ['nullable', 'string', 'max:5000'],
            'source'              => ['nullable', 'in:website,referral,social_media,cold_call,trade_show,advertisement,other'],
            'estimated_value'     => ['nullable', 'numeric', 'min:0'],
            'probability'         => ['nullable', 'integer', 'between:0,100'],
            'assigned_to'         => ['nullable', $this->inCompany('company_user', 'user_id', false)],
            'expected_close_date' => ['nullable', 'date'],
            'tags'                => ['nullable', 'array'],
            'tags.*'              => ['string', 'max:50'],
        ];
    }
}
