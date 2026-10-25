<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class StoreActivityRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'type'         => ['required', 'in:call,email,meeting,task,note,follow_up'],
            'subject'      => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'related_type' => ['nullable', 'in:contact,lead,opportunity,customer', 'required_with:related_id'],
            'related_id'   => ['nullable', 'integer', 'required_with:related_type'],
            'assigned_to'  => ['nullable', $this->inCompany('company_user', 'user_id', false)],
            'due_date'     => ['nullable', 'date'],
            'priority'     => ['nullable', 'in:low,medium,high'],
            'status'       => ['nullable', 'in:pending,in_progress,completed,cancelled'],
            'outcome'      => ['nullable', 'string', 'max:5000'],
        ];
    }
}
