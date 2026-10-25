<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class MergeContactRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'contact_ids'   => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'distinct', $this->inCompany('contacts')],
        ];
    }
}
