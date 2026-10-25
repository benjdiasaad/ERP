<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class AssignLeadRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'assigned_to' => ['present', 'nullable', $this->inCompany('company_user', 'user_id', false)],
        ];
    }
}
