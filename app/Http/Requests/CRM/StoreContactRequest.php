<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class StoreContactRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'mobile'       => ['nullable', 'string', 'max:50'],
            'job_title'    => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'customer_id'  => ['nullable', $this->inCompany('customers')],
            'supplier_id'  => ['nullable', $this->inCompany('suppliers')],
            'address'      => ['nullable', 'string', 'max:2000'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'source'       => ['nullable', 'in:website,referral,social_media,cold_call,trade_show,advertisement,other'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:5000'],
            'is_active'    => ['boolean'],
        ];
    }
}
