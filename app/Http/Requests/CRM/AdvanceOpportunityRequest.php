<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

class AdvanceOpportunityRequest extends CrmFormRequest
{
    public function rules(): array
    {
        return [
            'stage'       => ['nullable', 'in:qualification,proposal,negotiation'],
            'probability' => ['nullable', 'integer', 'between:0,100'],
        ];
    }
}
