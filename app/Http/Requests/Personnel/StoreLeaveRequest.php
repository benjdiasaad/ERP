<?php

declare(strict_types=1);

namespace App\Http\Requests\Personnel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'personnel_id'     => ['required', 'integer', 'exists:personnels,id'],
            'type'             => ['required', Rule::in(['annual', 'sick', 'maternity', 'paternity', 'unpaid', 'compensatory'])],
            'start_date'       => ['required', 'date'],
            'end_date'         => ['required', 'date', 'after_or_equal:start_date'],
            'total_days'       => ['nullable', 'numeric', 'min:0'],
            'reason'           => ['nullable', 'string'],
            'status'           => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
