<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class LoseOpportunityRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'lost_reason'       => ['required', 'string', 'max:2000'],
            'actual_close_date' => ['nullable', 'date'],
        ];
    }
}
