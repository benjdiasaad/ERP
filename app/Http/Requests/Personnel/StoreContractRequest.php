<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personnel_id'           => ['required', 'integer', 'exists:personnels,id'],
            'reference'              => ['nullable', 'string', 'max:100', 'unique:contracts,reference'],
            'type'                   => ['required', Rule::in(['CDI', 'CDD', 'stage', 'freelance', 'interim'])],
            'start_date'             => ['required', 'date'],
            'end_date'               => ['nullable', 'date', 'after_or_equal:start_date'],
            'salary'                 => ['required', 'numeric', 'min:0'],
            'trial_period_end'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'working_hours_per_week' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'benefits'               => ['nullable', 'array'],
            'document_path'          => ['nullable', 'string', 'max:255'],
            'status'                 => ['nullable', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'notes'                  => ['nullable', 'string'],
        ];
    }
}
