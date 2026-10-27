<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contractId = $this->route('contract')?->id;

        return [
            'personnel_id'           => ['sometimes', 'integer', $this->inCompany('personnels')],
            'reference'              => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('contracts', 'reference')->ignore($contractId),
            ],
            'type'                   => ['sometimes', Rule::in(['CDI', 'CDD', 'stage', 'freelance', 'interim'])],
            'start_date'             => ['sometimes', 'date'],
            'end_date'               => ['nullable', 'date', 'after_or_equal:start_date'],
            'salary'                 => ['sometimes', 'numeric', 'min:0'],
            'trial_period_end_date'  => ['nullable', 'date', 'after_or_equal:start_date'],
            'working_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'benefits'               => ['nullable', 'array'],
            'document_path'          => ['nullable', 'string', 'max:255'],
            'status'                 => ['sometimes', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'notes'                  => ['nullable', 'string'],
        ];
    }
}
